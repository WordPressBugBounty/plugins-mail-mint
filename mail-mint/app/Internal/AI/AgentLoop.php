<?php
/**
 * AgentLoop — one frontend-driven iteration of the Mint AI agent.
 *
 * Each step() call performs exactly ONE model request plus its tool batch,
 * then returns; the React client immediately requests the next step while
 * status is 'running'. This bounds every HTTP request to a single model
 * round-trip — the design constraint is shared-hosting PHP timeouts, which
 * rules out running the whole agentic loop in one request.
 *
 * States returned by step():
 *   running              — tools executed; client should call step() again
 *   pending_confirmation — a destructive tool awaits user approval (confirm())
 *   done                 — final assistant text produced
 *   error                — provider or loop failure (message included)
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI;

defined( 'ABSPATH' ) || exit;

class AgentLoop {

    /**
     * Upper bound on autonomous model calls per user turn. Raised from 12 to
     * give a fully autonomous agent room to complete a multi-stage task
     * (research → decide → build → verify) from one prompt without stalling.
     * The client-side loop cap in useAgentLoop.js is kept in sync.
     */
    private const MAX_STEPS_PER_TURN = 25;

    /**
     * Run one loop iteration for a conversation.
     */
    public static function step( int $conversation_id ): array {
        $conversation = ConversationStore::getConversation( $conversation_id );
        if ( ! $conversation ) {
            return self::errorState( 'Conversation not found.' );
        }
        if ( (int) $conversation['user_id'] !== get_current_user_id() ) {
            return self::errorState( 'This conversation belongs to another user.' );
        }
        if ( 'awaiting_confirmation' === $conversation['status'] ) {
            return [
                'status'  => 'pending_confirmation',
                'pending' => self::pendingForClient( $conversation['pending'] ),
            ];
        }

        $messages = ConversationStore::getMessages( $conversation_id );
        if ( empty( $messages ) ) {
            return self::errorState( 'Conversation has no messages yet.' );
        }

        // Turn budget: stop runaway loops gracefully.
        if ( ConversationStore::assistantStepsThisTurn( $messages ) >= self::MAX_STEPS_PER_TURN ) {
            $note = 'I hit the step limit for this request. Here is where things stand — tell me to continue if you want me to keep going.';
            ConversationStore::appendMessage( $conversation_id, 'assistant', [ 'text' => $note, 'tool_calls' => [] ] );
            ConversationStore::updateConversation( $conversation_id, [ 'status' => 'idle' ] );
            return [ 'status' => 'done', 'message' => $note ];
        }

        $provider = AIInit::activeProvider();
        if ( is_wp_error( $provider ) ) {
            return self::errorState( $provider->get_error_message() );
        }

        $system = SystemPrompt::build( (string) $conversation['context_type'], (int) $conversation['context_id'] );
        $tools  = ToolGateway::toolDefinitions();

        $response = $provider->chat( $system, $messages, $tools );
        if ( is_wp_error( $response ) ) {
            // Surface rate limits etc. without poisoning the conversation.
            return self::errorState( $response->get_error_message(), $response->get_error_code() );
        }

        // Persist the assistant message (with provider-raw payload for replay).
        ConversationStore::appendMessage(
            $conversation_id,
            'assistant',
            [
                'text'       => $response['text'],
                'tool_calls' => $response['tool_calls'],
            ],
            [
                'provider' => $provider->slug(),
                'raw'      => $response['raw'],
                'usage'    => $response['usage'],
            ]
        );

        if ( empty( $response['tool_calls'] ) ) {
            ConversationStore::updateConversation( $conversation_id, [ 'status' => 'idle' ] );
            return [
                'status'  => 'done',
                'message' => $response['text'],
            ];
        }

        // Execute safe calls now; queue destructive ones for confirmation.
        $executed = [];
        $pending  = [];
        foreach ( $response['tool_calls'] as $call ) {
            if ( ToolGateway::requiresConfirmation( $call['name'] ) ) {
                $pending[] = $call + [
                    'summary' => ToolGateway::describeCall( $call['name'], $call['arguments'] ),
                    // Structured twin of the summary — the confirmation card
                    // renders this so the user never meets raw tool JSON.
                    'details' => ToolGateway::describeCallDetails( $call['name'], $call['arguments'] ),
                ];
                continue;
            }
            $result = ToolGateway::executeTool( $call['name'], $call['arguments'] );
            ConversationStore::appendMessage( $conversation_id, 'tool', [
                'tool_call_id' => $call['id'],
                'name'         => $call['name'],
                'content'      => $result['content'],
                'is_error'     => $result['is_error'],
            ] );
            $executed[] = [
                'tool'     => $call['name'],
                'is_error' => $result['is_error'],
            ];
            self::bindCampaignContext( $conversation_id, $call['name'], $result );
        }

        if ( ! empty( $pending ) ) {
            ConversationStore::updateConversation( $conversation_id, [
                'status'  => 'awaiting_confirmation',
                'pending' => $pending,
            ] );
            return [
                'status'         => 'pending_confirmation',
                'assistant_text' => $response['text'],
                'executed'       => $executed,
                'pending'        => self::pendingForClient( $pending ),
            ];
        }

        ConversationStore::updateConversation( $conversation_id, [ 'status' => 'idle' ] );
        return [
            'status'         => 'running',
            'assistant_text' => $response['text'],
            'executed'       => $executed,
        ];
    }

    /**
     * Resolve a pending confirmation: execute or refuse the queued calls.
     */
    public static function confirm( int $conversation_id, bool $approve, string $deny_reason = '' ): array {
        $conversation = ConversationStore::getConversation( $conversation_id );
        if ( ! $conversation ) {
            return self::errorState( 'Conversation not found.' );
        }
        if ( (int) $conversation['user_id'] !== get_current_user_id() ) {
            return self::errorState( 'This conversation belongs to another user.' );
        }
        if ( 'awaiting_confirmation' !== $conversation['status'] || empty( $conversation['pending'] ) ) {
            return self::errorState( 'Nothing is awaiting confirmation.' );
        }

        $executed = [];
        foreach ( (array) $conversation['pending'] as $call ) {
            if ( $approve ) {
                // The user approved this destructive call — pass the server-side
                // hard-stop token so the ability wrapper actually executes it.
                $result = ToolGateway::executeTool(
                    (string) $call['name'],
                    array_merge( (array) $call['arguments'], [ 'confirm' => true ] )
                );
            } else {
                $result = [
                    'content'  => wp_json_encode( [
                        'error'   => 'denied_by_user',
                        'message' => 'The user declined this action.' . ( $deny_reason ? ' Reason: ' . $deny_reason : '' ),
                    ] ),
                    'is_error' => true,
                ];
            }
            ConversationStore::appendMessage( $conversation_id, 'tool', [
                'tool_call_id' => (string) $call['id'],
                'name'         => (string) $call['name'],
                'content'      => $result['content'],
                'is_error'     => $result['is_error'],
            ] );
            $executed[] = [
                'tool'     => (string) $call['name'],
                'is_error' => $result['is_error'],
                'approved' => $approve,
            ];
        }

        ConversationStore::updateConversation( $conversation_id, [
            'status'  => 'idle',
            'pending' => null,
        ] );

        return [
            'status'   => 'running',
            'executed' => $executed,
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * When the agent creates or composes a campaign, bind the conversation
     * to it — the copilot UI uses context_id to drive the live preview.
     */
    private static function bindCampaignContext( int $conversation_id, string $tool_name, array $result ): void {
        if ( $result['is_error'] || ! in_array( $tool_name, [ 'mail-mint/upsert-campaign', 'mail-mint/compose-campaign-email' ], true ) ) {
            return;
        }
        $decoded = json_decode( (string) $result['content'], true );
        if ( ! empty( $decoded['campaign_id'] ) ) {
            ConversationStore::updateConversation( $conversation_id, [
                'context_type' => 'campaign',
                'context_id'   => (int) $decoded['campaign_id'],
            ] );
        }
    }

    /**
     * Normalize queued destructive calls for the confirmation UI. Public
     * because reloading a conversation must render the same card the live turn
     * did — the REST read path goes through here too.
     *
     * @param mixed $pending Stored pending calls.
     * @return array Client-shaped pending calls.
     */
    public static function pendingForClient( $pending ): array {
        return array_map(
            static function ( $call ) {
                $name = (string) ( $call['name'] ?? '' );
                return [
                    'tool'      => $name,
                    'summary'   => (string) ( $call['summary'] ?? '' ),
                    'arguments' => $call['arguments'] ?? [],
                    // Rebuilt when absent so a conversation queued before this
                    // shipped still renders the humanized card on reload.
                    'details'   => is_array( $call['details'] ?? null )
                        ? $call['details']
                        : ToolGateway::describeCallDetails( $name, (array) ( $call['arguments'] ?? [] ) ),
                ];
            },
            is_array( $pending ) ? $pending : []
        );
    }

    private static function errorState( string $message, string $code = 'ai_error' ): array {
        return [
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
        ];
    }
}
