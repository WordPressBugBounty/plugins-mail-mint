<?php
/**
 * REST API AI Chat Controller
 *
 * Drives the Mint AI copilot conversation loop:
 *
 *   POST /mrm/v1/ai/conversations                 — start a conversation (+first message)
 *   GET  /mrm/v1/ai/conversations                 — list mine (optionally by context)
 *   GET  /mrm/v1/ai/conversations/{id}            — full message history
 *   PATCH  /mrm/v1/ai/conversations/{id}          — rename (update title)
 *   DELETE /mrm/v1/ai/conversations/{id}          — delete
 *   POST /mrm/v1/ai/conversations/{id}/messages   — append a user message
 *   POST /mrm/v1/ai/conversations/{id}/step       — run ONE agent-loop iteration
 *   POST /mrm/v1/ai/conversations/{id}/confirm    — approve/deny pending destructive tools
 *
 * @package Mint\MRM\Admin\API\Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

defined( 'ABSPATH' ) || exit;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\API\Controllers\BaseController;
use Mint\MRM\Internal\AI\AgentLoop;
use Mint\MRM\Internal\AI\ConversationStore;
use Mint\MRM\Internal\AI\Settings\AISettings;
use MRM\Common\MrmCommon;
use WP_REST_Request;

class AIChatController extends BaseController {

    use Singleton;

    /**
     * Default permission check (route-level callbacks gate each endpoint).
     *
     * @return bool
     */
    public function rest_permissions_check() {
        return current_user_can( 'manage_options' );
    }

    private const CONTEXT_TYPES = [ 'dashboard', 'campaign', 'automation', 'insights' ];

    /**
     * Progress labels per tool — powers the "Building your idea… 30%" UI.
     */
    private const PROGRESS_MAP = [
        'mail-mint/get-crm-context'         => [ 'Reading your CRM…', 10 ],
        'mail-mint/resolve-segments'        => [ 'Finding your audience…', 20 ],
        'mail-mint/list-tags'               => [ 'Finding your audience…', 20 ],
        'mail-mint/list-lists'              => [ 'Finding your audience…', 20 ],
        'mail-mint/upsert-campaign'         => [ 'Creating campaign draft…', 35 ],
        'mail-mint/list-email-templates'    => [ 'Picking a design…', 45 ],
        'mail-mint/compose-campaign-email'  => [ 'Writing your email…', 70 ],
        'mail-mint/send-test-email'         => [ 'Sending test email…', 85 ],
        'mail-mint/get-campaign-analytics'  => [ 'Crunching the numbers…', 50 ],
        'mail-mint/get-site-analytics'      => [ 'Crunching the numbers…', 50 ],
        'mail-mint/upsert-automation'       => [ 'Building your automation…', 60 ],
    ];

    public function create_conversation( WP_REST_Request $request ) {
        $params  = MrmCommon::get_api_params_values( $request );
        $message = trim( (string) ( $params['message'] ?? '' ) );
        if ( '' === $message ) {
            return $this->get_error_response( __( 'message is required.', 'mrm' ), 400 );
        }

        if ( ! AISettings::isEnabled() ) {
            return $this->get_error_response( __( 'The AI Assistant is turned off. Enable it under Settings → AI.', 'mrm' ), 400 );
        }

        $provider = AISettings::getActiveProvider();
        if ( '' === $provider ) {
            return $this->get_error_response( __( 'No AI provider connected. Connect one first.', 'mrm' ), 400 );
        }

        $context_type = in_array( $params['context_type'] ?? '', self::CONTEXT_TYPES, true ) ? $params['context_type'] : 'dashboard';
        $context_id   = (int) ( $params['context_id'] ?? 0 );

        $conversation_id = ConversationStore::createConversation(
            get_current_user_id(),
            $provider,
            $context_type,
            $context_id,
            mb_substr( $message, 0, 120 )
        );
        ConversationStore::appendMessage( $conversation_id, 'user', [ 'text' => $message ] );

        do_action( 'mailmint_ai_conversation_started', $conversation_id, $context_type );

        return $this->get_success_response( '', 201, [
            'conversation_id' => $conversation_id,
            'status'          => 'running',
        ] );
    }

    public function list_conversations( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        $limit  = isset( $params['limit'] ) ? max( 1, (int) $params['limit'] ) : 50;
        $offset = isset( $params['offset'] ) ? max( 0, (int) $params['offset'] ) : 0;

        $rows   = ConversationStore::listConversations(
            get_current_user_id(),
            sanitize_key( $params['context_type'] ?? '' ),
            (int) ( $params['context_id'] ?? 0 ),
            $limit,
            $offset
        );
        return $this->get_success_response( '', 200, [ 'conversations' => $rows ] );
    }

    public function get_conversation( WP_REST_Request $request ) {
        $conversation = $this->ownConversation( $request );
        if ( is_wp_error( $conversation ) ) {
            return $this->get_error_response( $conversation->get_error_message(), 404 );
        }

        $messages = array_map(
            static function ( $row ) {
                $content = json_decode( (string) $row['content'], true );
                return [
                    'id'         => (int) $row['id'],
                    'role'       => $row['role'],
                    'text'       => is_array( $content ) ? (string) ( $content['text'] ?? '' ) : '',
                    'tool_calls' => is_array( $content ) ? array_map(
                        static function ( $call ) {
                            return [ 'tool' => $call['name'] ?? '' ];
                        },
                        (array) ( $content['tool_calls'] ?? [] )
                    ) : [],
                    'tool_name'  => is_array( $content ) ? (string) ( $content['name'] ?? '' ) : '',
                    'is_error'   => is_array( $content ) && ! empty( $content['is_error'] ),
                    // For creator tools (campaign/form/automation), a descriptor the
                    // thread uses to render an inline preview card.
                    'preview'    => 'tool' === $row['role'] ? self::previewDescriptor( $content ) : null,
                    'created_at' => $row['created_at'],
                ];
            },
            ConversationStore::getMessages( (int) $conversation['id'] )
        );

        return $this->get_success_response( '', 200, [
            'conversation' => [
                'id'           => (int) $conversation['id'],
                'title'        => $conversation['title'],
                'provider'     => $conversation['provider'],
                'context_type' => $conversation['context_type'],
                'context_id'   => $conversation['context_id'] ? (int) $conversation['context_id'] : null,
                'status'       => $conversation['status'],
                // Same shape the step endpoint returns, so reopening a thread
                // that is awaiting confirmation renders the identical card.
                'pending'      => AgentLoop::pendingForClient( $conversation['pending'] ),
            ],
            'messages'     => $messages,
        ] );
    }

    /**
     * PATCH /mrm/v1/ai/conversations/{id} — rename (update title).
     */
    public function rename_conversation( WP_REST_Request $request ) {
        $conversation = $this->ownConversation( $request );
        if ( is_wp_error( $conversation ) ) {
            return $this->get_error_response( $conversation->get_error_message(), 404 );
        }

        $params = MrmCommon::get_api_params_values( $request );
        $title  = trim( (string) ( $params['title'] ?? '' ) );
        if ( '' === $title ) {
            return $this->get_error_response( __( 'title is required.', 'mrm' ), 400 );
        }

        $title = mb_substr( sanitize_text_field( $title ), 0, 250 );
        ConversationStore::updateConversation( (int) $conversation['id'], [ 'title' => $title ] );

        return $this->get_success_response( '', 200, [ 'title' => $title ] );
    }

    /**
     * DELETE /mrm/v1/ai/conversations/{id}.
     */
    public function delete_conversation( WP_REST_Request $request ) {
        $conversation = $this->ownConversation( $request );
        if ( is_wp_error( $conversation ) ) {
            return $this->get_error_response( $conversation->get_error_message(), 404 );
        }

        ConversationStore::deleteConversation( (int) $conversation['id'] );

        return $this->get_success_response( '', 200, [ 'deleted' => true ] );
    }

    public function append_message( WP_REST_Request $request ) {
        $conversation = $this->ownConversation( $request );
        if ( is_wp_error( $conversation ) ) {
            return $this->get_error_response( $conversation->get_error_message(), 404 );
        }
        if ( 'awaiting_confirmation' === $conversation['status'] ) {
            return $this->get_error_response( __( 'Resolve the pending confirmation first.', 'mrm' ), 409 );
        }

        $params  = MrmCommon::get_api_params_values( $request );
        $message = trim( (string) ( $params['message'] ?? '' ) );
        if ( '' === $message ) {
            return $this->get_error_response( __( 'message is required.', 'mrm' ), 400 );
        }

        ConversationStore::appendMessage( (int) $conversation['id'], 'user', [ 'text' => $message ] );

        return $this->get_success_response( '', 200, [ 'status' => 'running' ] );
    }

    public function step( WP_REST_Request $request ) {
        $conversation = $this->ownConversation( $request );
        if ( is_wp_error( $conversation ) ) {
            return $this->get_error_response( $conversation->get_error_message(), 404 );
        }

        $state = AgentLoop::step( (int) $conversation['id'] );

        return $this->get_success_response( '', 200, $this->decorateState( $state ) );
    }

    public function confirm( WP_REST_Request $request ) {
        $conversation = $this->ownConversation( $request );
        if ( is_wp_error( $conversation ) ) {
            return $this->get_error_response( $conversation->get_error_message(), 404 );
        }

        $params = MrmCommon::get_api_params_values( $request );
        $state  = AgentLoop::confirm(
            (int) $conversation['id'],
            ! empty( $params['approve'] ),
            sanitize_text_field( $params['deny_reason'] ?? '' )
        );

        return $this->get_success_response( '', 200, $this->decorateState( $state ) );
    }

    /**
     * GET /mrm/v1/ai/campaign-preview/{campaign_id}
     *
     * Live preview payload for the copilot split view: first email step's
     * subject, preview text, and compiled HTML.
     */
    public function campaign_preview( WP_REST_Request $request ) {
        $campaign_id = (int) $request->get_param( 'campaign_id' );
        if ( ! $campaign_id ) {
            return $this->get_error_response( __( 'campaign_id is required.', 'mrm' ), 400 );
        }

        $repo     = new \Mint\MRM\Database\Repositories\CampaignRepository();
        $campaign = $repo->find( $campaign_id );
        if ( ! $campaign ) {
            return $this->get_error_response( __( 'Campaign not found.', 'mrm' ), 404 );
        }
        $campaign = is_object( $campaign ) ? (array) $campaign : $campaign;

        $emails = $repo->getCampaignEmails( $campaign_id );
        $first  = $emails[0] ?? [];

        // Prefer the email-builder JSON tree so the client can compile the same
        // responsive MJML output the builder produces. The stored email_body is
        // only a fallback (classic editor, or a legacy row with no JSON): for an
        // AI-composed email it holds a server-side hand-rolled HTML that diverges
        // from the MJML render, which is why the raw preview looked broken until
        // a manual builder save overwrote it. See EmailComposer::buildHtml().
        $json_content = isset( $first['email_json'] ) && is_array( $first['email_json'] ) ? $first['email_json'] : null;

        return $this->get_success_response( '', 200, [
            'campaign_id'  => $campaign_id,
            'title'        => $campaign['title'] ?? '',
            'type'         => $campaign['type'] ?? 'regular',
            'status'       => $campaign['status'] ?? '',
            'subject'      => $first['email_subject'] ?? '',
            'preview_text' => $first['email_preview_text'] ?? '',
            'json_content' => $json_content,
            'email_body'   => $first['body_data'] ?? '',
            'has_content'  => ! empty( $json_content['content'] ) || ! empty( $first['body_data'] ),
        ] );
    }

    /**
     * GET /mrm/v1/ai/form-preview/{form_id}
     *
     * Inline-preview payload for a form created by the assistant: title,
     * status, and a manifest of its input fields (label + type).
     */
    public function form_preview( WP_REST_Request $request ) {
        $form_id = (int) $request->get_param( 'form_id' );
        if ( ! $form_id ) {
            return $this->get_error_response( __( 'form_id is required.', 'mrm' ), 400 );
        }

        $form = \Mint\MRM\Internal\MCP\Tools\FormTools::getForm( [ 'form_id' => $form_id ] );
        if ( is_wp_error( $form ) ) {
            return $this->get_error_response( $form->get_error_message(), 404 );
        }

        $fields = array_map(
            static function ( $field ) {
                $block = (string) ( $field['block'] ?? 'text' );
                return [
                    'label' => (string) ( $field['name'] ?? ucfirst( str_replace( '-', ' ', $block ) ) ),
                    'type'  => $block,
                ];
            },
            (array) ( $form['fields'] ?? [] )
        );

        return $this->get_success_response( '', 200, [
            'form_id' => $form_id,
            'title'   => (string) ( $form['title'] ?? '' ),
            'status'  => (string) ( $form['status'] ?? '' ),
            'fields'  => $fields,
        ] );
    }

    /**
     * GET /mrm/v1/ai/automation-preview/{automation_id}
     *
     * Inline-preview payload for an automation: a normalized list of flow
     * nodes (trigger → action steps) the thread renders as a vertical flow.
     */
    public function automation_preview( WP_REST_Request $request ) {
        $automation_id = (int) $request->get_param( 'automation_id' );
        if ( ! $automation_id ) {
            return $this->get_error_response( __( 'automation_id is required.', 'mrm' ), 400 );
        }

        $automation = \Mint\MRM\Internal\MCP\Tools\AutomationTools::getAutomation( [ 'automation_id' => $automation_id ] );
        if ( is_wp_error( $automation ) ) {
            return $this->get_error_response( $automation->get_error_message(), 404 );
        }

        return $this->get_success_response( '', 200, [
            'automation_id' => $automation_id,
            'title'         => (string) ( $automation['title'] ?? '' ),
            'status'        => (string) ( $automation['status'] ?? '' ),
            'nodes'         => $this->automationNodes( (array) ( $automation['steps'] ?? [] ) ),
        ] );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Map a creator tool's result to an inline-preview descriptor, or null.
     *
     * @param array|null $content Decoded tool message content.
     * @return array|null { kind: 'campaign'|'form'|'automation', id: int }
     */
    private static function previewDescriptor( $content ): ?array {
        if ( ! is_array( $content ) || ! empty( $content['is_error'] ) ) {
            return null;
        }

        $map = [
            'mail-mint/upsert-campaign'        => [ 'campaign', 'campaign_id' ],
            'mail-mint/compose-campaign-email' => [ 'campaign', 'campaign_id' ],
            'mail-mint/create-form'            => [ 'form', 'form_id' ],
            'mail-mint/update-form'            => [ 'form', 'form_id' ],
            'mail-mint/upsert-automation'      => [ 'automation', 'automation_id' ],
        ];

        $tool = (string) ( $content['name'] ?? '' );
        if ( ! isset( $map[ $tool ] ) ) {
            return null;
        }

        [ $kind, $id_key ] = $map[ $tool ];
        $result = json_decode( (string) ( $content['content'] ?? '' ), true );
        $id     = is_array( $result ) ? (int) ( $result[ $id_key ] ?? 0 ) : 0;

        return $id ? [ 'kind' => $kind, 'id' => $id ] : null;
    }

    /**
     * Normalize persisted automation steps into renderable flow nodes.
     *
     * Each persisted step is { step_id, key, type: trigger|action|logical, settings }.
     * A logical (condition) step also carries node_data.{yes,no} — arrays of child steps
     * for each branch — which are mapped recursively into a `branches` key so the inline
     * preview can render the yes/no arms.
     *
     * @param array $steps Persisted steps.
     * @return array<int, array{kind:string,label:string,detail:string,branches?:array}>
     */
    private function automationNodes( array $steps ): array {
        $nodes = [];
        foreach ( $steps as $step ) {
            $step = (array) $step;
            $key  = (string) ( $step['key'] ?? '' );
            $type = (string) ( $step['type'] ?? 'action' );
            $set  = (array) ( $step['settings'] ?? [] );

            if ( 'trigger' === $type ) {
                $nodes[] = [
                    'type'    => 'trigger',
                    'iconKey' => 'trigger',
                    'label'   => __( 'Trigger', 'mrm' ),
                    'detail'  => $this->humanize( $key ),
                ];
                continue;
            }

            if ( 'logical' === $type || 'condition' === $key ) {
                $node_data = (array) ( $step['node_data'] ?? [] );
                $nodes[]   = [
                    'type'     => 'logical',
                    'iconKey'  => 'condition',
                    'label'    => __( 'Check Condition', 'mrm' ),
                    'detail'   => $this->conditionDetail( $set ),
                    'branches' => [
                        'yes' => $this->automationNodes( (array) ( $node_data['yes'] ?? [] ) ),
                        'no'  => $this->automationNodes( (array) ( $node_data['no'] ?? [] ) ),
                    ],
                ];
                continue;
            }

            $nodes[] = [
                'type'    => 'action',
                'iconKey' => $key,
                'label'   => $this->actionLabel( $key ),
                'detail'  => $this->actionDetail( $key, $set ),
            ];
        }
        return $nodes;
    }

    /**
     * A one-line summary of a condition step's first rule (e.g. "Email opens · Any of the emails").
     */
    private function conditionDetail( array $settings ): string {
        $groups = (array) ( $settings['rules']['condition'] ?? [] );
        $first  = [];
        foreach ( $groups as $group ) {
            if ( is_array( $group ) && ! empty( $group ) ) {
                $first = (array) $group[0];
                break;
            }
        }
        if ( empty( $first ) ) {
            return '';
        }
        $name  = (string) ( $first['name'] ?? $first['action'] ?? '' );
        $label = (string) ( $first['condition_label'] ?? '' );
        return trim( $name . ( '' !== $label ? ' · ' . $label : '' ) );
    }

    /**
     * The step title, matching the labels the real automation canvas shows.
     */
    private function actionLabel( string $key ): string {
        $labels = [
            'delay'      => __( 'Time Delay', 'mrm' ),
            'sendMail'   => __( 'Send An Email', 'mrm' ),
            'addTag'     => __( 'Assign Tag(s)', 'mrm' ),
            'removeTag'  => __( 'Remove Tag(s)', 'mrm' ),
            'addList'    => __( 'Add To List(s)', 'mrm' ),
            'removeList' => __( 'Remove From List(s)', 'mrm' ),
        ];
        return $labels[ $key ] ?? $this->humanize( $key );
    }

    /**
     * The step subtitle, matching the phrasing the real automation canvas shows
     * (e.g. "Assigned Tag: New Comer", "Wait for 2 days").
     */
    private function actionDetail( string $key, array $settings ): string {
        switch ( $key ) {
            case 'delay':
                $d    = (array) ( $settings['delay_settings'] ?? $settings );
                $num  = (int) ( $d['delay'] ?? 0 );
                $unit = (string) ( $d['unit'] ?? 'minutes' );
                return $num ? sprintf( __( 'Wait for %1$d %2$s', 'mrm' ), $num, $unit ) : '';
            case 'sendMail':
                $m = (array) ( $settings['message_data'] ?? $settings );
                return (string) ( $m['subject'] ?? '' );
            case 'addTag':
            case 'removeTag':
                $prefix = 'addTag' === $key ? __( 'Assigned Tag:', 'mrm' ) : __( 'Removed Tag:', 'mrm' );
                return $this->segmentNames( $prefix, (array) ( $settings['tag_settings']['tags'] ?? [] ) );
            case 'addList':
            case 'removeList':
                $prefix = 'addList' === $key ? __( 'Assigned List:', 'mrm' ) : __( 'Removed List:', 'mrm' );
                return $this->segmentNames( $prefix, (array) ( $settings['list_settings']['lists'] ?? [] ) );
            default:
                return '';
        }
    }

    /**
     * Join tag/list titles into a "Prefix A, B" subtitle, mirroring the canvas.
     */
    private function segmentNames( string $prefix, array $items ): string {
        $names = array_values( array_filter( array_map(
            static function ( $item ) {
                return is_array( $item ) ? (string) ( $item['title'] ?? '' ) : '';
            },
            $items
        ) ) );
        if ( empty( $names ) ) {
            return $prefix . ' ' . (string) count( $items );
        }
        return $prefix . ' ' . implode( ', ', $names );
    }

    /**
     * Turn a snake/camel key into a Title-cased phrase.
     */
    private function humanize( string $key ): string {
        $key = str_replace( [ '_', '-' ], ' ', $key );
        // Insert a space before each interior uppercase letter (camelCase split),
        // without regex per project convention.
        $spaced = '';
        foreach ( str_split( $key ) as $index => $char ) {
            if ( $index > 0 && ctype_upper( $char ) ) {
                $spaced .= ' ';
            }
            $spaced .= $char;
        }
        return ucwords( trim( $spaced ) );
    }

    /**
     * Resolve {id} to the current user's conversation.
     *
     * @return array|\WP_Error
     */
    private function ownConversation( WP_REST_Request $request ) {
        $conversation_id = (int) $request->get_param( 'conversation_id' );
        $conversation    = $conversation_id ? ConversationStore::getConversation( $conversation_id ) : null;
        if ( ! $conversation || (int) $conversation['user_id'] !== get_current_user_id() ) {
            return new \WP_Error( 'not_found', __( 'Conversation not found.', 'mrm' ) );
        }
        return $conversation;
    }

    /**
     * Attach progress labels for executed tools so the UI can animate.
     */
    private function decorateState( array $state ): array {
        $progress = null;
        foreach ( (array) ( $state['executed'] ?? [] ) as $event ) {
            $tool = (string) ( $event['tool'] ?? '' );
            if ( isset( self::PROGRESS_MAP[ $tool ] ) ) {
                [ $label, $pct ] = self::PROGRESS_MAP[ $tool ];
                if ( null === $progress || $pct > $progress['pct'] ) {
                    $progress = [ 'label' => $label, 'pct' => $pct ];
                }
            }
        }
        if ( 'done' === ( $state['status'] ?? '' ) ) {
            $progress = [ 'label' => __( 'Done', 'mrm' ), 'pct' => 100 ];
        }
        if ( null !== $progress ) {
            $state['progress'] = $progress;
        }
        return $state;
    }
}
