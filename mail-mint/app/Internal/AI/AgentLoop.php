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
     *
     * @param int           $conversation_id Conversation to advance.
     * @param callable|null $on_delta        When given, function( string $text_chunk ): void — the
     *                                       model turn streams via ProviderInterface::chatStream()
     *                                       instead of chat(), and this is invoked with assistant
     *                                       text as it arrives. Persistence, tool execution, and the
     *                                       return shape are otherwise IDENTICAL to the non-streaming
     *                                       path — the SSE route is the only caller that passes this.
     */
    public static function step( int $conversation_id, ?callable $on_delta = null ): array {
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

        // Failed turns are persisted so the thread keeps showing them, but they
        // are UI artifacts — never real model output — so they are stripped
        // before the history goes back to a provider. Replaying one leaves the
        // history ending on an assistant turn, which Gemini rejects outright
        // ("Requests ending with a model turn are not supported."), and feeds
        // every provider an error string as if the model had said it.
        $history = self::providerHistory( $messages );
        if ( empty( $history ) ) {
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
            return self::errorState( $provider->get_error_message(), 'ai_error', $conversation_id );
        }

        $system = SystemPrompt::build( (string) $conversation['context_type'], (int) $conversation['context_id'] );
        $tools  = ToolGateway::toolDefinitions();

        $response = null !== $on_delta
            ? $provider->chatStream( $system, $history, $tools, $on_delta )
            : $provider->chat( $system, $history, $tools );
        if ( is_wp_error( $response ) ) {
            // Persisted (not just returned) so the failure stays visible in the
            // thread — retrying must not silently erase evidence it happened.
            $error_data = $response->get_error_data();
            return self::errorState(
                $response->get_error_message(),
                $response->get_error_code(),
                $conversation_id,
                is_array( $error_data ) ? ( $error_data['retry_after'] ?? null ) : null
            );
        }

        $text       = trim( (string) ( $response['text'] ?? '' ) );
        $tool_calls = (array) ( $response['tool_calls'] ?? [] );

        // If the model returned empty text and no tool calls, provide a clear fallback
        // so the user receives a helpful answer instead of an empty thread turn.
        if ( '' === $text && empty( $tool_calls ) ) {
            $text = __( "I couldn't generate a response for this prompt. Please try asking again or rephrasing your prompt.", 'mrm' );
        }

        // Persist the assistant message (with provider-raw payload for replay).
        ConversationStore::appendMessage(
            $conversation_id,
            'assistant',
            [
                'text'       => $text,
                'tool_calls' => $tool_calls,
            ],
            [
                'provider' => $provider->slug(),
                'raw'      => $response['raw'],
                'usage'    => $response['usage'],
            ]
        );

        if ( empty( $tool_calls ) ) {
            ConversationStore::updateConversation( $conversation_id, [ 'status' => 'idle' ] );
            return [
                'status'  => 'done',
                'message' => $text,
            ];
        }

        // Execute safe calls now; queue destructive ones for confirmation.
        // Campaign tools created/composed earlier in this conversation (even
        // within this same tool_calls batch) are bound here — a model that
        // omits campaign_id on a later call must not spawn a second campaign.
        $executed           = [];
        $pending            = [];
        $bound_campaign_id  = 'campaign' === $conversation['context_type'] ? (int) $conversation['context_id'] : 0;
        foreach ( $response['tool_calls'] as $call ) {
            if (
                $bound_campaign_id
                && in_array( $call['name'], [ 'mail-mint/upsert-campaign', 'mail-mint/compose-campaign-email' ], true )
                && empty( $call['arguments']['campaign_id'] )
            ) {
                $call['arguments']['campaign_id'] = $bound_campaign_id;
            }

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
            $bound_campaign_id = self::bindCampaignContext( $conversation_id, $call['name'], $result ) ?: $bound_campaign_id;
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
    private static function bindCampaignContext( int $conversation_id, string $tool_name, array $result ): ?int {
        if ( $result['is_error'] || ! in_array( $tool_name, [ 'mail-mint/upsert-campaign', 'mail-mint/compose-campaign-email' ], true ) ) {
            return null;
        }
        $decoded = json_decode( (string) $result['content'], true );
        if ( ! empty( $decoded['campaign_id'] ) ) {
            $campaign_id = (int) $decoded['campaign_id'];
            ConversationStore::updateConversation( $conversation_id, [
                'context_type' => 'campaign',
                'context_id'   => $campaign_id,
            ] );
            return $campaign_id;
        }
        return null;
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

    /**
     * The conversation history as a provider should see it: persisted failure
     * turns (assistant messages flagged is_error) removed. They exist only so
     * the thread can render what went wrong; replaying them would both end the
     * history on an assistant turn — which Gemini rejects with HTTP 400,
     * "Requests ending with a model turn are not supported" — and pass the
     * error text off as something the model itself produced.
     */
    private static function providerHistory( array $messages ): array {
        return array_values(
            array_filter(
                $messages,
                static function ( $message ) {
                    if ( 'assistant' !== ( $message['role'] ?? '' ) ) {
                        return true;
                    }
                    $content = $message['content'] ?? [];
                    if ( is_string( $content ) ) {
                        $content = json_decode( $content, true );
                    }
                    return ! ( is_array( $content ) && ! empty( $content['is_error'] ) );
                }
            )
        );
    }

    /**
     * Build the error response. When $conversation_id is given (i.e. the
     * conversation is in a state where a turn genuinely failed, not a
     * pre-turn validation guard), the failure is also persisted as an
     * assistant message so it stays in the thread's history — a later
     * retry must not make it look like nothing went wrong.
     */
    private static function errorState( string $message, string $code = 'ai_error', int $conversation_id = 0, ?int $retry_after = null ): array {
        if ( $conversation_id ) {
            ConversationStore::appendMessage( $conversation_id, 'assistant', [
                'text'       => $message,
                'tool_calls' => [],
                'is_error'   => true,
            ] );
            ConversationStore::updateConversation( $conversation_id, [ 'status' => 'idle' ] );
        }
        return [
            'status'      => 'error',
            'code'        => $code,
            'message'     => $message,
            'retry_after' => $retry_after,
        ];
    }
}
