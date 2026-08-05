<?php
/**
 * ToolGateway — bridges the agent loop to the WordPress Abilities registry.
 *
 * The AI copilot and the external MCP server share ONE tool surface: every
 * 'mail-mint' category ability (Free + Pro, registered via
 * wp_register_ability). WP_Ability::execute() enforces input validation and
 * the per-tool permission_callback as the logged-in user, so the loop never
 * bypasses the permission model.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI;

defined( 'ABSPATH' ) || exit;

class ToolGateway {

    /**
     * Cap on a single tool result fed back to the model — protects the
     * context window from a 200-row list-contacts response.
     */
    private const RESULT_CHAR_LIMIT = 12000;

    /**
     * Tools the loop never auto-executes; the user must approve in the UI.
     */
    private const ALWAYS_CONFIRM = [
        'mail-mint/change-campaign-status',
        'mail-mint/delete-contact',
        'mail-mint/set-automation-status',
        // Building/replacing an automation rewrites the whole flow, so the user
        // reviews and approves it before it is applied to the canvas.
        'mail-mint/upsert-automation',
    ];

    /**
     * Normalized tool definitions the current user may call.
     *
     * @return array [ {name, description, input_schema, destructive} ]
     */
    public static function toolDefinitions(): array {
        $definitions = [];
        foreach ( self::abilities() as $ability ) {
            if ( true !== $ability->check_permissions( [] ) ) {
                continue;
            }
            $schema = $ability->get_input_schema();
            $definitions[] = [
                'name'         => $ability->get_name(),
                'description'  => $ability->get_description(),
                'input_schema' => ! empty( $schema ) ? self::normalizeSchema( $schema ) : [ 'type' => 'object', 'properties' => new \stdClass() ],
                'destructive'  => self::isDestructive( $ability ),
            ];
        }
        return $definitions;
    }

    /**
     * Whether a tool requires explicit user confirmation before execution.
     */
    public static function requiresConfirmation( string $tool_name ): bool {
        if ( in_array( $tool_name, self::ALWAYS_CONFIRM, true ) ) {
            return true;
        }
        $ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $tool_name ) : null;
        return $ability ? self::isDestructive( $ability ) : true;
    }

    /**
     * Execute a tool as the current user. Returns a STRING result payload
     * (JSON) ready to feed back to the model, plus an error flag.
     *
     * @return array{content:string,is_error:bool}
     */
    public static function executeTool( string $tool_name, array $arguments ): array {
        if ( ! function_exists( 'wp_get_ability' ) ) {
            return [ 'content' => 'Abilities API unavailable on this site.', 'is_error' => true ];
        }

        $ability = wp_get_ability( $tool_name );
        if ( ! $ability || ! str_starts_with( $tool_name, 'mail-mint/' ) ) {
            return [ 'content' => sprintf( 'Unknown tool "%s".', $tool_name ), 'is_error' => true ];
        }

        $result = $ability->execute( $arguments );

        if ( is_wp_error( $result ) ) {
            return [
                'content'  => wp_json_encode( [
                    'error'   => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ] ),
                'is_error' => true,
            ];
        }

        $encoded = wp_json_encode( $result );
        if ( false === $encoded ) {
            $encoded = '"<unencodable result>"';
        }
        if ( strlen( $encoded ) > self::RESULT_CHAR_LIMIT ) {
            $encoded = substr( $encoded, 0, self::RESULT_CHAR_LIMIT )
                . '"...[truncated — narrow the query with filters or smaller per_page]"';
        }

        do_action( 'mailmint_ai_tool_executed', $tool_name, $arguments, $result );

        return [ 'content' => $encoded, 'is_error' => false ];
    }

    /**
     * A short human-readable summary for the confirmation UI.
     *
     * Kept as a flat string for back-compat (older clients, logs). The
     * confirmation card renders {@see describeCallDetails()} instead — raw tool
     * names and JSON arguments never belong in front of a marketer.
     */
    public static function describeCall( string $tool_name, array $arguments ): string {
        $details = self::describeCallDetails( $tool_name, $arguments );
        $line    = $details['title'];
        if ( '' !== $details['subject'] ) {
            $line .= ': ' . $details['subject'];
        }
        return '' !== $details['note'] ? $line . ' — ' . $details['note'] : $line;
    }

    /**
     * Structured, plain-English description of a queued tool call.
     *
     * The confirmation card is the only place a user meets the tool layer, so
     * every field here is written for them, not for the model:
     *   title   — what will happen, as a verb phrase ("Create a new list")
     *   subject — the thing it happens to ("My Member")
     *   note    — the consequence, especially anything irreversible
     *   risk    — low | medium | high, drives the card's tone and CTA wording
     *   fields  — label/value pairs replacing the raw argument JSON
     *   raw     — the JSON, kept for the collapsed "technical details" view
     *
     * Dependency-free and total: any tool without a specific hint still gets a
     * readable title and humanized fields, and partial arguments never throw.
     *
     * @param string $tool_name Fully-qualified ability name.
     * @param array  $arguments Tool arguments as the model produced them.
     * @return array{tool:string,action:string,kind:string,title:string,subject:string,note:string,risk:string,fields:array,raw:string}
     */
    public static function describeCallDetails( string $tool_name, array $arguments ): array {
        $short = str_replace( 'mail-mint/', '', $tool_name );
        $raw   = (string) wp_json_encode( $arguments );
        if ( strlen( $raw ) > 400 ) {
            $raw = substr( $raw, 0, 400 ) . '…';
        }

        return array_merge(
            [
                'tool'    => $short,
                'action'  => isset( $arguments['action'] ) ? (string) $arguments['action'] : '',
                'kind'    => 'generic',
                'title'   => self::humanizeToolName( $short ),
                'subject' => self::guessSubject( $arguments ),
                'note'    => '',
                // An unrecognised tool only reaches confirmation because it is
                // annotated destructive — treat it as high risk, not unknown.
                'risk'    => 'high',
                'fields'  => self::humanizeArguments( $arguments ),
            ],
            self::describeKnownCall( $tool_name, $arguments ),
            [ 'raw' => $raw ]
        );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Per-tool overrides for {@see describeCallDetails()}. Returns only the
     * keys it wants to override; [] means "no specific hint, use the defaults".
     *
     * @param string $tool_name Fully-qualified ability name.
     * @param array  $arguments Tool arguments.
     * @return array Partial descriptor.
     */
    private static function describeKnownCall( string $tool_name, array $arguments ): array {
        $action = isset( $arguments['action'] ) ? (string) $arguments['action'] : '';
        $title  = isset( $arguments['title'] ) ? (string) $arguments['title'] : '';

        switch ( $tool_name ) {
            case 'mail-mint/manage-list':
            case 'mail-mint/manage-tag':
                return self::describeGroupCall( 'mail-mint/manage-list' === $tool_name, $action, $title, $arguments );

            case 'mail-mint/change-campaign-status':
                return self::describeCampaignCall( $action, $arguments );

            case 'mail-mint/delete-contact':
                $id        = isset( $arguments['contact_id'] ) ? (int) $arguments['contact_id'] : 0;
                $with_logs = ! empty( $arguments['delete_activity'] );
                return [
                    'kind'    => 'contact',
                    'action'  => 'delete',
                    'title'   => __( 'Delete a contact', 'mrm' ),
                    'subject' => self::guessSubject( $arguments ) ?: self::refLabel( $id ),
                    'note'    => $with_logs
                        ? __( 'Their activity history goes too. This cannot be undone.', 'mrm' )
                        : __( 'This cannot be undone.', 'mrm' ),
                    'risk'    => 'high',
                    'fields'  => self::fields( [
                        __( 'Contact', 'mrm' )          => self::guessSubject( $arguments ) ?: self::refLabel( $id ),
                        __( 'Activity history', 'mrm' ) => $with_logs ? __( 'Deleted as well', 'mrm' ) : __( 'Kept', 'mrm' ),
                    ] ),
                ];

            case 'mail-mint/set-automation-status':
                $id     = isset( $arguments['automation_id'] ) ? (int) $arguments['automation_id'] : 0;
                $status = isset( $arguments['status'] ) ? (string) $arguments['status'] : '';
                $base   = [
                    'kind'    => 'automation',
                    'action'  => $status,
                    'subject' => self::guessSubject( $arguments ) ?: self::refLabel( $id ),
                    'fields'  => self::fields( [
                        __( 'Automation', 'mrm' ) => self::guessSubject( $arguments ) ?: self::refLabel( $id ),
                        __( 'New status', 'mrm' ) => $status,
                    ] ),
                ];
                if ( 'active' === $status ) {
                    return $base + [
                        'title' => __( 'Turn on an automation', 'mrm' ),
                        'note'  => __( 'It starts reacting to live site activity and can send real emails.', 'mrm' ),
                        'risk'  => 'high',
                    ];
                }
                if ( 'pause' === $status ) {
                    return $base + [
                        'title' => __( 'Pause an automation', 'mrm' ),
                        'note'  => __( 'It stops reacting to new activity. You can turn it back on any time.', 'mrm' ),
                        'risk'  => 'medium',
                    ];
                }
                return $base + [
                    'title' => __( 'Change an automation status', 'mrm' ),
                    'risk'  => 'medium',
                ];

            case 'mail-mint/delete-contact-note':
                return [
                    'kind'   => 'note',
                    'action' => 'delete',
                    'title'  => __( 'Delete a contact note', 'mrm' ),
                    'note'   => __( 'This cannot be undone.', 'mrm' ),
                    'risk'   => 'high',
                ];

            case 'mail-mint/upsert-automation':
                $name        = isset( $arguments['name'] ) ? (string) $arguments['name'] : '';
                $is_update   = ! empty( $arguments['automation_id'] );
                $trigger     = is_array( $arguments['trigger'] ?? null ) ? $arguments['trigger'] : [];
                $steps       = is_array( $arguments['steps'] ?? null ) ? $arguments['steps'] : [];
                $trigger_key = isset( $trigger['key'] ) ? (string) $trigger['key'] : '';

                $fields = [];
                if ( '' !== $trigger_key ) {
                    $fields[] = [ 'label' => __( 'When', 'mrm' ), 'value' => self::humanizeKey( $trigger_key ) ];
                }
                foreach ( $steps as $i => $step ) {
                    $fields[] = [
                        /* translators: %d: step position in the flow. */
                        'label' => sprintf( __( 'Step %d', 'mrm' ), $i + 1 ),
                        'value' => self::describeAutomationStep( is_array( $step ) ? $step : [] ),
                    ];
                }

                return [
                    'kind'    => 'automation',
                    'action'  => $is_update ? 'update' : 'create',
                    'title'   => $is_update ? __( 'Update this automation', 'mrm' ) : __( 'Build this automation', 'mrm' ),
                    'subject' => $name,
                    'note'    => __( 'Saved as a draft — review the flow, then turn it on when ready. Nothing sends until you do.', 'mrm' ),
                    'risk'    => 'medium',
                    'fields'  => $fields,
                ];
        }

        return [];
    }

    /**
     * One human-readable line for a single automation step, for the
     * confirmation card's flow preview (e.g. "Wait 2 days", "Send email: …").
     *
     * @param array $step A step from upsert-automation's steps[] argument.
     * @return string
     */
    private static function describeAutomationStep( array $step ): string {
        $key      = isset( $step['key'] ) ? (string) $step['key'] : '';
        $settings = is_array( $step['settings'] ?? null ) ? $step['settings'] : [];

        switch ( $key ) {
            case 'addTag':
                return __( 'Add tag(s)', 'mrm' );
            case 'removeTag':
                return __( 'Remove tag(s)', 'mrm' );
            case 'addList':
                return __( 'Add to list(s)', 'mrm' );
            case 'removeList':
                return __( 'Remove from list(s)', 'mrm' );
            case 'delay':
                $delay = is_array( $settings['delay_settings'] ?? null ) ? $settings['delay_settings'] : [];
                $n     = isset( $delay['delay'] ) ? (int) $delay['delay'] : 0;
                $unit  = isset( $delay['unit'] ) ? (string) $delay['unit'] : '';
                /* translators: 1: amount, 2: unit (minutes/hours/days/weeks). */
                return $n && $unit ? sprintf( __( 'Wait %1$d %2$s', 'mrm' ), $n, $unit ) : __( 'Wait', 'mrm' );
            case 'sendMail':
                $subject = $settings['message_data']['subject'] ?? '';
                /* translators: %s: email subject line. */
                return '' !== $subject ? sprintf( __( 'Send email: %s', 'mrm' ), $subject ) : __( 'Send an email', 'mrm' );
            case 'condition':
                return __( 'If / else branch', 'mrm' );
            default:
                return '' !== $key ? self::humanizeKey( $key ) : __( 'Step', 'mrm' );
        }
    }

    /**
     * Descriptor for manage-list / manage-tag. Creating and renaming are
     * everyday edits; deleting cascades to every contact, so only that one
     * carries the hard warning.
     *
     * @param bool   $is_list   True for a list, false for a tag.
     * @param string $action    create | update | delete.
     * @param string $title     Title argument, when supplied.
     * @param array  $arguments Tool arguments.
     * @return array Partial descriptor.
     */
    private static function describeGroupCall( bool $is_list, string $action, string $title, array $arguments ): array {
        $kind    = $is_list ? 'list' : 'tag';
        $id      = (int) ( $arguments[ $is_list ? 'list_id' : 'tag_id' ] ?? 0 );
        $subject = '' !== $title ? $title : self::refLabel( $id );
        $noun    = $is_list ? __( 'list', 'mrm' ) : __( 'tag', 'mrm' );

        if ( 'delete' === $action ) {
            return [
                'kind'    => $kind,
                'action'  => 'delete',
                /* translators: %s: list or tag. */
                'title'   => sprintf( __( 'Delete a %s', 'mrm' ), $noun ),
                'subject' => $subject,
                'note'    => __( 'It is removed from every contact it was applied to. This cannot be undone.', 'mrm' ),
                'risk'    => 'high',
                'fields'  => self::fields( [ ucfirst( $noun ) => $subject ] ),
            ];
        }

        if ( 'update' === $action ) {
            return [
                'kind'    => $kind,
                'action'  => 'update',
                /* translators: %s: list or tag. */
                'title'   => sprintf( __( 'Rename a %s', 'mrm' ), $noun ),
                'subject' => $subject,
                'note'    => __( 'Contacts already in it stay where they are.', 'mrm' ),
                'risk'    => 'low',
                'fields'  => self::fields( [
                    ucfirst( $noun )       => self::refLabel( $id ),
                    __( 'New name', 'mrm' ) => $title,
                ] ),
            ];
        }

        return [
            'kind'    => $kind,
            'action'  => 'create',
            /* translators: %s: list or tag. */
            'title'   => sprintf( __( 'Create a new %s', 'mrm' ), $noun ),
            'subject' => $subject,
            'note'    => __( 'Nothing existing changes; it starts empty.', 'mrm' ),
            'risk'    => 'low',
            'fields'  => self::fields( [ __( 'Name', 'mrm' ) => $subject ] ),
        ];
    }

    /**
     * Descriptor for change-campaign-status — the highest-stakes tool in the
     * set, because scheduling or resuming puts mail in real inboxes.
     *
     * @param string $action    Campaign action.
     * @param array  $arguments Tool arguments.
     * @return array Partial descriptor.
     */
    private static function describeCampaignCall( string $action, array $arguments ): array {
        $id      = isset( $arguments['campaign_id'] ) ? (int) $arguments['campaign_id'] : 0;
        $subject = self::guessSubject( $arguments ) ?: self::refLabel( $id );
        $when    = ! empty( $arguments['scheduled_at'] ) ? (string) $arguments['scheduled_at'] : '';
        $base    = [
            'kind'    => 'campaign',
            'action'  => $action,
            'subject' => $subject,
        ];

        switch ( $action ) {
            case 'schedule':
                return $base + [
                    'title'  => __( 'Schedule a campaign to send', 'mrm' ),
                    'note'   => __( 'Real contacts will receive this email. Sending cannot be undone.', 'mrm' ),
                    'risk'   => 'high',
                    'fields' => self::fields( [
                        __( 'Campaign', 'mrm' ) => $subject,
                        __( 'Sends', 'mrm' )    => '' !== $when ? $when : __( 'As soon as it is approved', 'mrm' ),
                    ] ),
                ];
            case 'resume':
                return $base + [
                    'title'  => __( 'Resume a campaign', 'mrm' ),
                    'note'   => __( 'It can start sending to real contacts right away.', 'mrm' ),
                    'risk'   => 'high',
                    'fields' => self::fields( [ __( 'Campaign', 'mrm' ) => $subject ] ),
                ];
            case 'delete':
                return $base + [
                    'title'  => __( 'Delete a campaign', 'mrm' ),
                    'note'   => __( 'This cannot be undone.', 'mrm' ),
                    'risk'   => 'high',
                    'fields' => self::fields( [ __( 'Campaign', 'mrm' ) => $subject ] ),
                ];
            case 'pause':
                return $base + [
                    'title'  => __( 'Pause a campaign', 'mrm' ),
                    'note'   => __( 'Sending stops and the campaign moves to archived.', 'mrm' ),
                    'risk'   => 'medium',
                    'fields' => self::fields( [ __( 'Campaign', 'mrm' ) => $subject ] ),
                ];
            case 'unschedule':
                return $base + [
                    'title'  => __( 'Unschedule a campaign', 'mrm' ),
                    'note'   => __( 'It goes back to draft and will not send.', 'mrm' ),
                    'risk'   => 'medium',
                    'fields' => self::fields( [ __( 'Campaign', 'mrm' ) => $subject ] ),
                ];
            case 'duplicate':
                return $base + [
                    'title'  => __( 'Duplicate a campaign', 'mrm' ),
                    'note'   => __( 'You get a new draft copy. Nothing sends.', 'mrm' ),
                    'risk'   => 'low',
                    'fields' => self::fields( [ __( 'Copy of', 'mrm' ) => $subject ] ),
                ];
        }

        return $base + [
            'title'  => __( 'Change a campaign status', 'mrm' ),
            'risk'   => 'medium',
            'fields' => self::fields( [
                __( 'Campaign', 'mrm' )   => $subject,
                __( 'New status', 'mrm' ) => $action,
            ] ),
        ];
    }

    /**
     * Build label/value field rows, dropping empties so the card never renders
     * a dangling label.
     *
     * @param array $pairs label => value.
     * @return array [ {label, value} ]
     */
    private static function fields( array $pairs ): array {
        $fields = [];
        foreach ( $pairs as $label => $value ) {
            if ( null === $value || '' === $value ) {
                continue;
            }
            $fields[] = [
                'label' => (string) $label,
                'value' => (string) $value,
            ];
        }
        return $fields;
    }

    /**
     * Generic argument humanizer for tools without a specific descriptor:
     * `{"per_page":50}` becomes "Per page: 50" rather than JSON.
     *
     * @param array $arguments Tool arguments.
     * @return array [ {label, value} ]
     */
    private static function humanizeArguments( array $arguments ): array {
        $pairs = [];
        foreach ( $arguments as $key => $value ) {
            // Internal safety gate — noise on a card that IS the safety gate.
            if ( 'confirm' === $key ) {
                continue;
            }
            if ( is_bool( $value ) ) {
                $value = $value ? __( 'Yes', 'mrm' ) : __( 'No', 'mrm' );
            } elseif ( is_array( $value ) || is_object( $value ) ) {
                $value = (string) wp_json_encode( $value );
            }
            if ( ! is_scalar( $value ) ) {
                continue;
            }
            $value = (string) $value;
            if ( strlen( $value ) > 120 ) {
                $value = substr( $value, 0, 120 ) . '…';
            }
            $pairs[ self::humanizeKey( (string) $key ) ] = $value;
        }
        return self::fields( $pairs );
    }

    /**
     * `scheduled_at` → "Scheduled at", `campaign_id` → "Campaign ID".
     *
     * @param string $key Argument key.
     * @return string Human label.
     */
    private static function humanizeKey( string $key ): string {
        $key = trim( str_replace( '_', ' ', $key ) );
        $key = (string) preg_replace( '/\bid\b/i', 'ID', $key );
        return ucfirst( $key );
    }

    /**
     * `upsert-campaign` → "Upsert campaign".
     *
     * @param string $short Tool name without the mail-mint/ prefix.
     * @return string Human title.
     */
    private static function humanizeToolName( string $short ): string {
        return ucfirst( trim( str_replace( '-', ' ', $short ) ) );
    }

    /**
     * The most name-like argument, used as the card's subject line so the user
     * reads "My Member" instead of an opaque record id.
     *
     * @param array $arguments Tool arguments.
     * @return string Subject, or '' when nothing name-like was supplied.
     */
    private static function guessSubject( array $arguments ): string {
        foreach ( [ 'title', 'name', 'email', 'subject' ] as $key ) {
            if ( ! empty( $arguments[ $key ] ) && is_scalar( $arguments[ $key ] ) ) {
                return (string) $arguments[ $key ];
            }
        }
        return '';
    }

    /**
     * Fallback subject for a record the model referenced only by id.
     *
     * @param int $id Record id.
     * @return string Reference label.
     */
    private static function refLabel( int $id ): string {
        return $id > 0 ? sprintf( '#%d', $id ) : __( 'the selected record', 'mrm' );
    }

    /**
     * @return \WP_Ability[]
     */
    private static function abilities(): array {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return [];
        }
        return array_filter(
            wp_get_abilities(),
            static function ( $ability ) {
                return str_starts_with( $ability->get_name(), 'mail-mint/' );
            }
        );
    }

    private static function isDestructive( $ability ): bool {
        $annotations = $ability->get_meta_item( 'annotations', [] );
        return is_array( $annotations ) && ! empty( $annotations['destructive'] );
    }

    /**
     * Coerce a JSON Schema so it serializes the way strict validators (OpenAI's
     * function schema) require: an empty `properties` map must encode as a JSON
     * object `{}`, but PHP's empty array `[]` encodes as an array. Convert any
     * empty `properties` to stdClass, recursively, so tools that declare no
     * input parameters (and Pro-registered tools we don't control) don't get
     * rejected with "[] is not of type 'object'".
     *
     * @param mixed $schema Schema node.
     * @return mixed Normalized schema node.
     */
    private static function normalizeSchema( $schema ) {
        if ( ! is_array( $schema ) ) {
            return $schema;
        }
        foreach ( $schema as $key => $value ) {
            if ( 'properties' === $key && is_array( $value ) ) {
                if ( empty( $value ) ) {
                    $schema[ $key ] = new \stdClass();
                    continue;
                }
                foreach ( $value as $prop_key => $prop_schema ) {
                    $value[ $prop_key ] = self::normalizeSchema( $prop_schema );
                }
                $schema[ $key ] = $value;
                continue;
            }
            if ( is_array( $value ) ) {
                $schema[ $key ] = self::normalizeSchema( $value );
            }
        }
        return $schema;
    }
}
