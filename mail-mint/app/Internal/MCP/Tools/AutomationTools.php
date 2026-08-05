<?php
/**
 * MCP Automation Tools
 *
 * Provides automation-related tools:
 *   - list-automations, get-automation, list-automation-contacts,
 *     get-automation-capabilities (read)
 *   - upsert-automation, set-automation-status,
 *     update-automation-contact-status (write)
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;
use MRM\Common\MrmCommon;
use MintMail\App\Internal\Automation\AutomationModel as AutomationStore;
use MintMail\App\Internal\Automation\Action\AutomationAction;
use MintMail\App\Internal\Automation\Connector;
use MintMail\App\Internal\Automation\HelperFunctions;
use Mint\MRM\Database\Repositories\ContactGroupRepository;

class AutomationTools {

    public static function definitions(): array {
        return [

            'mail-mint/list-automations' => [
                'label'       => __( 'List Automations', 'mail-mint' ),
                'description' => 'List automations with contact counts. Supports search, status filtering, sorting, and pagination.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'   => [ 'type' => 'string' ],
                        'status'   => [ 'type' => 'string', 'enum' => [ 'active', 'inactive', 'all' ], 'default' => 'all', 'description' => 'Single status value. The underlying model only supports one status at a time.' ],
                        'sort_by'  => [ 'type' => 'string', 'default' => 'id' ],
                        'sort_type'=> [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
                        'page'     => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page' => [ 'type' => 'integer', 'default' => 10 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listAutomations' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_automations' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/get-automation' => [
                'label'       => __( 'Get Automation', 'mail-mint' ),
                'description' => 'Get automation details with all steps. Set include_bodies=true to include email body content (increases response size).',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'automation_id' ],
                    'properties' => [
                        'automation_id' => [ 'type' => 'integer' ],
                        'include_bodies'=> [ 'type' => 'boolean', 'default' => false, 'description' => 'Include email body HTML in steps. Off by default to save context.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getAutomation' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_automations' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/list-automation-contacts' => [
                'label'       => __( 'List Automation Contacts', 'mail-mint' ),
                'description' => 'List contacts that have progressed through a given automation, with their per-step status (processing, completed, exited). Progress is tracked per email address in the automation log; a contact may appear once per step they have reached.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'automation_id' ],
                    'properties' => [
                        'automation_id' => [ 'type' => 'integer' ],
                        'status'        => [ 'type' => 'string', 'enum' => [ 'processing', 'completed', 'exited', 'hold', 'pending', 'fail' ], 'description' => 'Filter by automation-log step status.' ],
                        'page'          => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page'      => [ 'type' => 'integer', 'default' => 20 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listAutomationContacts' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_automations' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/get-automation-capabilities' => [
                'label'       => __( 'Get Automation Capabilities', 'mail-mint' ),
                'description' => 'Discover which automation triggers (grouped by connector) and which action types are available on this site, including Pro-registered ones. Call this before upsert-automation — trigger and action keys must come from this registry.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [],
                ],
                'execute_callback'    => [ self::class, 'getAutomationCapabilities' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_automations' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/upsert-automation' => [
                'label'       => __( 'Upsert Automation', 'mail-mint' ),
                'description' => 'Create or update an automation: one trigger followed by sequential action steps, optionally including ONE level of conditional branching. Created as draft; enable with set-automation-status. Always call get-automation-capabilities first — validate keys AND read its "branching" field: only emit a condition step when branching.available is true (otherwise explain branching.message to the user and build the linear part). Common action settings shapes: delay {"delay_settings":{"delay":1,"unit":"minutes|hours|days|weeks"}}; addTag {"tag_settings":{"tags":[{"id":N}]}}; addList {"list_settings":{"lists":[{"id":N}]}}; sendMail {"message_data":{"subject":"...","body":"<html>","sender_name":"","sender_email":"","reply_name":"","reply_email":"","email_preview_text":""}}. Branching: a step with key "condition" takes "condition" (rule set) plus "yes" and "no" arrays of action steps; see branching_guide in get-automation-capabilities for the exact rule shape. For "opened within N days" put a delay before the condition and check engagement — the arms rejoin automatically after the branch.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'name', 'trigger' ],
                    'properties' => [
                        'automation_id' => [ 'type' => 'integer', 'description' => 'Provide to update an existing automation. WARNING: updating replaces the step list.' ],
                        'name'          => [ 'type' => 'string' ],
                        'trigger'       => [
                            'type'       => 'object',
                            'required'   => [ 'key' ],
                            'properties' => [
                                'key'      => [ 'type' => 'string', 'description' => 'A trigger key from get-automation-capabilities.' ],
                                'settings' => [ 'type' => 'object', 'description' => 'Trigger configuration (e.g. {"form_id":5} for form triggers). Shape depends on the trigger.' ],
                            ],
                        ],
                        'steps'         => [
                            'type'  => 'array',
                            'description' => 'Ordered steps executed after the trigger. Each is a linear action, or one "condition" branching step (only when capabilities.branching.available is true).',
                            'items' => [
                                'type'       => 'object',
                                'required'   => [ 'key' ],
                                'properties' => [
                                    'key'       => [ 'type' => 'string', 'description' => 'An action key from get-automation-capabilities (e.g. delay, sendMail, addTag, addList), or "condition" for a branch.' ],
                                    'settings'  => [ 'type' => 'object' ],
                                    'ref'       => [ 'type' => 'string', 'description' => 'Optional stable label for this step (e.g. "welcome_email"). A condition rule can reference this step\'s email by ref instead of an id — see branching_guide.' ],
                                    'condition' => [ 'type' => 'array', 'description' => 'Only for key="condition": the rule set (array of OR-groups, each an array of AND-rules). See branching_guide.' ],
                                    'yes'       => [ 'type' => 'array', 'description' => 'Only for key="condition": ordered action steps run when the condition matches. Same shape as steps items (no nested conditions).' ],
                                    'no'        => [ 'type' => 'array', 'description' => 'Only for key="condition": ordered action steps run when the condition does NOT match.' ],
                                ],
                            ],
                        ],
                        'idempotency_key' => [ 'type' => 'string', 'description' => 'Optional. Pass a stable key to make retries safe.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'upsertAutomation' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_automations' ),
                'annotations'         => [],
            ],

            'mail-mint/set-automation-status' => [
                'label'       => __( 'Set Automation Status', 'mail-mint' ),
                'description' => 'Change an automation status: active (starts acting on real site events), pause, or draft.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'automation_id', 'status' ],
                    'properties' => [
                        'automation_id' => [ 'type' => 'integer' ],
                        'status'        => [ 'type' => 'string', 'enum' => [ 'active', 'pause', 'draft' ] ],
                        'confirm'       => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this action. Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'setAutomationStatus' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_automations' ),
                'annotations'         => [ 'destructive' ],
            ],

            'mail-mint/update-automation-contact-status' => [
                'label'       => __( 'Update Automation Contact Status', 'mail-mint' ),
                'description' => 'Remove (exit) a contact from an automation so its remaining steps no longer run for them. Mail Mint tracks automation progress per email at the step level — there is no per-contact "resume" or "jump to step" state to manipulate — so "exit" is the only supported action. To (re)enroll a contact, use the Pro trigger-automation tool.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'automation_id', 'action' ],
                    'properties' => [
                        'automation_id' => [ 'type' => 'integer' ],
                        'contact_id'    => [ 'type' => 'integer', 'description' => 'Contact to exit (provide this or email).' ],
                        'email'         => [ 'type' => 'string', 'format' => 'email', 'description' => 'Contact email to exit (alternative to contact_id).' ],
                        'action'        => [ 'type' => 'string', 'enum' => [ 'exit' ], 'description' => 'Only "exit" is supported. resume/advance are not representable in the automation-log schema.' ],
                        'confirm'       => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this action. Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'updateAutomationContactStatus' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_automations' ),
                'annotations'         => [ 'destructive' ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function listAutomations( array $params ): array|\WP_Error {
        $paging  = MCPHelper::paginationFromInput( $params );
        $search  = sanitize_text_field( $params['search'] ?? '' );
        $sort_by = sanitize_key( $params['sort_by'] ?? 'id' );
        $sort    = strtoupper( $params['sort_type'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $status  = sanitize_text_field( $params['status'] ?? 'all' );

        $response = AutomationStore::get_all( $sort_by, $sort, $paging['offset'], $paging['per_page'], $search, $status );

        // get_all returns ['data' => [...], 'total_pages' => N, 'count' => N]
        $rows        = is_array( $response ) ? ( $response['data'] ?? [] ) : [];
        $automations = array_map( [ self::class, 'formatAutomation' ], $rows );
        $total       = is_array( $response ) ? (int) ( $response['count'] ?? 0 ) : 0;

        return [
            'automations' => $automations,
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => (int) ceil( $total / $paging['per_page'] ),
        ];
    }

    public static function getAutomation( array $params ): array|\WP_Error {
        $automation_id = (int) ( $params['automation_id'] ?? 0 );
        if ( ! $automation_id ) {
            return MCPHelper::error( 'missing_param', 'automation_id is required.' );
        }

        $response = AutomationStore::get_single( $automation_id );
        // get_single returns ['data' => [0 => automation_row]] or null
        $automation = is_array( $response ) ? ( $response['data'][0] ?? null ) : null;
        if ( ! $automation ) {
            return MCPHelper::error( 'not_found', 'Automation not found.' );
        }

        $formatted = self::formatAutomation( $automation );

        if ( empty( $params['include_bodies'] ) && isset( $formatted['steps'] ) ) {
            $formatted['steps'] = array_map( function ( $step ) {
                unset( $step['body'], $step['email_body'] );
                return $step;
            }, (array) $formatted['steps'] );
        }

        return $formatted;
    }

    public static function listAutomationContacts( array $params ): array|\WP_Error {
        global $wpdb;

        $automation_id = (int) ( $params['automation_id'] ?? 0 );
        if ( ! $automation_id ) {
            return MCPHelper::error( 'missing_param', 'automation_id is required.' );
        }

        $paging = MCPHelper::paginationFromInput( $params );
        $status = sanitize_key( $params['status'] ?? '' );

        // Per-contact automation progress lives in mint_automation_log, keyed by email
        // (NOT contact_id — the jobs table only tracks automation-level progress).
        $log_table      = $wpdb->prefix . 'mint_automation_log';
        $contacts_table = $wpdb->prefix . 'mint_contacts';

        $where_conditions = [ 'l.automation_id = %d', "l.email <> ''", 'l.email IS NOT NULL' ];
        $where_values     = [ $automation_id ];

        if ( $status ) {
            $where_conditions[] = 'l.status = %s';
            $where_values[]     = $status;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where_conditions );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.id AS log_id, l.step_id, l.status, l.identifier, l.created_at, l.updated_at,
                        l.email, c.id AS contact_id, c.first_name, c.last_name
                 FROM {$log_table} l
                 LEFT JOIN {$contacts_table} c ON c.email = l.email
                 {$where_sql}
                 ORDER BY l.id DESC
                 LIMIT %d OFFSET %d",
                array_merge( $where_values, [ $paging['per_page'], $paging['offset'] ] )
            ),
            ARRAY_A
        );

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$log_table} l {$where_sql}",
                $where_values
            )
        );

        return [
            'contacts'    => $rows ?: [],
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => (int) ceil( $total / max( 1, $paging['per_page'] ) ),
            'note'        => 'Rows are per-step automation-log entries keyed by email. A contact reaching multiple steps appears multiple times.',
        ];
    }

    public static function updateAutomationContactStatus( array $params ): array|\WP_Error {
        global $wpdb;

        $automation_id = (int) ( $params['automation_id'] ?? 0 );
        $action        = sanitize_key( $params['action'] ?? '' );

        if ( ! $automation_id ) {
            return MCPHelper::error( 'missing_param', 'automation_id is required.' );
        }

        // Only "exit" is representable: the schema tracks per-step log rows keyed by email,
        // not a per-contact job pointer, so resume/advance cannot be done without corrupting
        // automation-level state shared across all contacts.
        if ( 'exit' !== $action ) {
            return MCPHelper::error(
                'not_supported',
                'Only action="exit" is supported. Mail Mint tracks automation progress per step (in mint_automation_log), not as a per-contact job pointer, so "resume"/"advance_now" are not representable. To re-enroll a contact, use the Pro trigger-automation tool.'
            );
        }

        // Resolve the contact email (the log's natural key).
        $email      = sanitize_email( $params['email'] ?? '' );
        $contact_id = (int) ( $params['contact_id'] ?? 0 );
        if ( ! $email && $contact_id ) {
            $contact = \Mint\MRM\DataBase\Models\ContactModel::get( $contact_id );
            if ( $contact ) {
                $contact = is_object( $contact ) ? (array) $contact : $contact;
                $email   = sanitize_email( $contact['email'] ?? '' );
            }
        }

        if ( ! $email ) {
            return MCPHelper::error( 'missing_param', 'Provide a valid contact_id or email to exit.' );
        }

        $log_table = $wpdb->prefix . 'mint_automation_log';

        // Scope the delete to THIS automation only (destroy_by_email() would wipe every
        // automation's log rows for the email).
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$log_table} WHERE automation_id = %d AND email = %s",
                $automation_id,
                $email
            )
        );

        if ( false === $deleted ) {
            return MCPHelper::error( 'db_error', 'Failed to remove the contact from the automation.' );
        }

        if ( 0 === (int) $deleted ) {
            return MCPHelper::error( 'not_found', 'This contact has no progress recorded in that automation.' );
        }

        return [
            'result'        => 'exited',
            'automation_id' => $automation_id,
            'email'         => $email,
            'rows_removed'  => (int) $deleted,
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Whether the AI copilot may build conditional (branched) automations, and if not, why.
     *
     * Branching executes only through Mail Mint Pro's Condition action (registered on the
     * 'mint_supported_automation_actions' filter), which additionally requires an active
     * license. Returns a reason code the model turns into an accurate message + upsell
     * instead of silently deferring to the visual editor.
     *
     * @param array $action_keys Keys from the runtime-filtered supported_actions() registry.
     * @return array{available:bool,reason:?string,message?:string,upsell_url?:string}
     *   reason ∈ 'pro_not_installed' | 'license_inactive' | null.
     */
    private static function branchingAvailability( array $action_keys ): array {
        // 'condition' is present only when Pro is active and registered its action.
        if ( ! in_array( 'condition', $action_keys, true ) || ! MrmCommon::is_mailmint_pro_active() ) {
            return [
                'available'  => false,
                'reason'     => 'pro_not_installed',
                'message'    => 'Conditional branching needs Mail Mint Pro, which is not installed or active on this site.',
                'upsell_url' => 'https://getwpfunnels.com/pricing/',
            ];
        }
        if ( ! MrmCommon::is_mailmint_pro_license_active() ) {
            return [
                'available'  => false,
                'reason'     => 'license_inactive',
                'message'    => 'Conditional branching needs an active Mail Mint Pro license — Pro is installed but the license is inactive or expired.',
                'upsell_url' => 'https://getwpfunnels.com/pricing/',
            ];
        }
        return [ 'available' => true, 'reason' => null ];
    }

    /**
     * How-to + a copyable example for building a conditional branch via upsert-automation.
     * Kept out of the base capabilities payload and returned only when branching is available,
     * so sites without Pro do not pay the token cost for guidance they cannot use.
     *
     * @return array
     */
    private static function branchingGuide(): array {
        return [
            'how'      => 'Add ONE step to steps[] with key "condition". Give it "condition" (the rule set), "yes" (actions when it matches) and "no" (actions when it does not). Both arms automatically rejoin whatever step follows the condition. Arms hold plain actions only — no nested conditions.',
            'rule_set' => 'condition is an array of OR-groups; each group is an array of AND-rules. So [[A,B],[C]] means (A AND B) OR C.',
            'timing'   => 'There is no "within N hours" operator. For "opened the email within 48 hours", put a delay (e.g. 2 days) BEFORE the condition, then check email engagement — contacts who opened during the wait take the yes arm.',
            'example'  => [
                'name'    => 'Welcome + open branch',
                'trigger' => [ 'key' => 'wp_user_register' ],
                'steps'   => [
                    [ 'key' => 'addTag', 'settings' => [ 'tag_settings' => [ 'tags' => [ [ 'id' => 1 ] ] ] ] ],
                    [ 'key' => 'sendMail', 'ref' => 'welcome_email', 'settings' => [ 'message_data' => [ 'subject' => 'Welcome!', 'body' => '<p>Hi</p>' ] ] ],
                    [ 'key' => 'delay', 'settings' => [ 'delay_settings' => [ 'delay' => 2, 'unit' => 'days' ] ] ],
                    [
                        'key'       => 'condition',
                        'condition' => [
                            [
                                [
                                    'action'          => 'Email Engagement',
                                    'param'           => 'engagement_email_open',
                                    'name'            => 'Email opens',
                                    'condition_label' => 'Any of the emails',
                                    'condition_value' => 'any_of_the_emails',
                                    // source "automation" + value = the "ref" you put on the sendMail step above;
                                    // the server swaps it for that step\'s real id.
                                    'value'           => [ [ 'value' => 'welcome_email', 'label' => 'Welcome!', 'source' => 'automation' ] ],
                                    'segmentValue'    => [],
                                    'action_type'     => 'email_engagement_picker',
                                ],
                            ],
                        ],
                        'yes'       => [
                            [ 'key' => 'addTag', 'settings' => [ 'tag_settings' => [ 'tags' => [ [ 'id' => 2 ] ] ] ] ],
                        ],
                        'no'        => [
                            [ 'key' => 'addTag', 'settings' => [ 'tag_settings' => [ 'tags' => [ [ 'id' => 3 ] ] ] ] ],
                        ],
                    ],
                ],
            ],
            'engagement_params' => [ 'engagement_email_open', 'engagement_email_not_open', 'engagement_email_clicked', 'engagement_email_not_clicked' ],
            'engagement_operators' => [ 'any_emails (no value)', 'any_of_the_emails', 'all_of_the_emails' ],
            'note'     => 'Resolve tag/list ids first (they are validated). To check engagement with an email sent EARLIER in this same automation, put a "ref" on that sendMail step and use source "automation" with value=<that ref> — the server resolves it to the real id. To check a standalone campaign email, use source "campaign" with the real campaign email id (integer).',
        ];
    }

    public static function getAutomationCapabilities( array $params ): array|\WP_Error {
        // Both registries are runtime-filtered, so Pro connectors/actions appear
        // automatically when Pro is active — never hardcode these lists.
        $triggers_by_connector = Connector::get_instance()->get_triggers();
        $actions               = AutomationAction::get_instance()->supported_actions();

        $connectors = [];
        foreach ( (array) $triggers_by_connector as $connector_name => $triggers ) {
            // A connector may register several entries that resolve to the same
            // canonical trigger_name (e.g. the daily/once anniversary variants).
            // Collapse them so the model sees one key per trigger.
            $seen = [];
            $list = [];
            foreach ( (array) $triggers as $t ) {
                $t  = (array) $t;
                $tn = self::triggerName( $t );
                if ( '' === $tn || isset( $seen[ $tn ] ) ) {
                    continue;
                }
                $seen[ $tn ] = true;
                $list[]      = [
                    'key'   => $tn,
                    'label' => $t['label'] ?? '',
                ];
            }
            $connectors[] = [
                'connector' => $connector_name,
                'triggers'  => $list,
            ];
        }

        $action_list = [];
        foreach ( (array) $actions as $key => $label ) {
            $action_list[] = [ 'key' => $key, 'label' => is_string( $label ) ? $label : $key ];
        }

        $branching = self::branchingAvailability( array_keys( (array) $actions ) );
        if ( $branching['available'] ) {
            // Only ship the (token-heavy) guide when branches can actually be built.
            $branching['branching_guide'] = self::branchingGuide();
        }

        return [
            'connectors'  => $connectors,
            'actions'     => $action_list,
            'statuses'    => [ 'draft', 'active', 'pause' ],
            'branching'   => $branching,
            'note'        => $branching['available']
                ? 'upsert-automation supports linear flows AND one level of conditional branching: add a step with key "condition" (see the branching_guide).'
                : 'upsert-automation supports linear flows only on this site (' . $branching['reason'] . '). Do not emit a "condition" step — explain the reason to the user and offer the upgrade, then build the linear part.',
            // Best-practice defaults so an autonomous build is expert, not
            // arbitrary. Only references Free action keys; adapt to the site's
            // own engagement (get-site-analytics) when known.
            'recommended' => [
                'delay'        => [
                    'delay' => 3,
                    'unit'  => 'days',
                    'note'  => 'Typical spacing between marketing touches; use 1–2 days for welcome flows. Never chain two sends with no delay.',
                ],
                'flow_templates' => [
                    're_engagement' => 'trigger → sendMail ("we miss you") → delay 3 days → sendMail (value/incentive) → delay 3 days → sendMail ("last chance"). Add an exit/goal condition so contacts who re-engage stop receiving the sequence.',
                    'welcome'       => 'trigger → sendMail (welcome, fires immediately) → delay 2 days → sendMail (getting started / first value).',
                ],
            ],
        ];
    }

    public static function upsertAutomation( array $params ): array|\WP_Error {
        $automation_id = (int) ( $params['automation_id'] ?? 0 );
        $name          = sanitize_text_field( $params['name'] ?? '' );
        $trigger       = is_array( $params['trigger'] ?? null ) ? $params['trigger'] : [];
        $trigger_key   = sanitize_text_field( $trigger['key'] ?? '' );
        $steps_in      = is_array( $params['steps'] ?? null ) ? $params['steps'] : [];

        if ( '' === $name ) {
            return MCPHelper::error( 'missing_param', 'name is required.' );
        }
        if ( '' === $trigger_key ) {
            return MCPHelper::error( 'missing_param', 'trigger.key is required.' );
        }
        if ( count( $steps_in ) > 30 ) {
            return MCPHelper::error( 'invalid_state', 'Too many steps (max 30).' );
        }

        // Validate the trigger against the runtime registry and resolve it to its
        // canonical trigger_name — the value the automation engine matches at dispatch
        // (see get_supported_mm_triggers()). The legacy 'key' (a listening hook) is also
        // accepted as input for robustness, but storage always uses the canonical name;
        // storing the listening hook produces automations that never fire.
        $valid_trigger = false;
        foreach ( (array) Connector::get_instance()->get_triggers() as $triggers ) {
            foreach ( (array) $triggers as $t ) {
                $t  = (array) $t;
                $tn = self::triggerName( $t );
                if ( $tn === $trigger_key || ( $t['key'] ?? '' ) === $trigger_key ) {
                    $trigger_key   = $tn; // Normalise to the canonical trigger_name.
                    $valid_trigger = true;
                    break 2;
                }
            }
        }
        if ( ! $valid_trigger ) {
            return MCPHelper::error( 'invalid_state', sprintf( 'Unknown trigger key "%s". Use get-automation-capabilities to discover valid keys.', $trigger_key ) );
        }

        $valid_actions = array_keys( (array) AutomationAction::get_instance()->supported_actions() );

        // Enforce the Pro/license gate up front, with an accurate reason, before doing any
        // work: conditional branching executes only through Pro's Condition action.
        $has_condition = false;
        foreach ( $steps_in as $step ) {
            if ( is_array( $step ) && 'condition' === ( $step['key'] ?? '' ) ) {
                $has_condition = true;
                break;
            }
        }
        if ( $has_condition ) {
            $branching = self::branchingAvailability( $valid_actions );
            if ( ! $branching['available'] ) {
                return MCPHelper::error(
                    'invalid_state',
                    ( $branching['message'] ?? 'Conditional branching is not available on this site.' )
                        . ' Reason code: ' . $branching['reason'] . '. Build the linear steps only and tell the user why the branch was skipped, sharing the upgrade link.'
                );
            }
        }

        if ( $automation_id && empty( AutomationStore::get_single( $automation_id )['data'] ) ) {
            return MCPHelper::error( 'not_found', 'Automation not found.' );
        }

        $idem = MCPHelper::idempotentHit( 'mail-mint/upsert-automation', $params );
        if ( null !== $idem ) {
            return $idem;
        }

        // Build the step list. Each top-level step is either linear (trigger → action → …)
        // or a "condition" logical step whose yes/no arms rejoin the linear spine. The AI
        // writes segment references as {"id":N}; enrichSegmentTitles backfills the title the
        // visual builder renders and collects unknown ids so we can reject them (otherwise
        // the builder shows "Assigned Tag: undefined").
        $missing_segments = [];

        // Pre-generate an id for the trigger and each top-level step so a step can point at
        // the one after it — a condition's scalar next_step_id is the branch merge point.
        $top_ids = [ self::generateStepId() ];
        foreach ( $steps_in as $unused ) {
            $top_ids[] = self::generateStepId();
        }

        // Step ids are generated here, not by the AI — so an engagement condition cannot
        // reference an in-automation email by its (unknown) id. Instead the AI tags a step
        // with a stable "ref"; this map lets a condition point at that step's real id.
        $ref_map = [];
        foreach ( $steps_in as $i => $step ) {
            $ref = is_array( $step ) ? sanitize_text_field( $step['ref'] ?? '' ) : '';
            if ( '' !== $ref ) {
                $ref_map[ $ref ] = $top_ids[ $i + 1 ];
            }
        }

        $steps   = [];
        $steps[] = [
            'step_id'      => $top_ids[0],
            'key'          => $trigger_key,
            'type'         => 'trigger',
            'settings'     => is_array( $trigger['settings'] ?? null ) ? $trigger['settings'] : [],
            'next_step_id' => $top_ids[1] ?? '',
        ];
        foreach ( $steps_in as $i => $step ) {
            $key      = sanitize_text_field( is_array( $step ) ? ( $step['key'] ?? '' ) : '' );
            $step_id  = $top_ids[ $i + 1 ];
            $merge_id = $top_ids[ $i + 2 ] ?? ''; // Where both branches and the spine continue.

            if ( '' === $key || ! in_array( $key, $valid_actions, true ) ) {
                return MCPHelper::error( 'invalid_state', sprintf( 'steps[%d].key "%s" is not a registered action. Use get-automation-capabilities.', $i, $key ) );
            }

            if ( 'condition' === $key ) {
                // parent_index is this condition's zero-based position in $steps (trigger is 0).
                $built = self::buildConditionStep( $step, $step_id, $merge_id, $i + 1, $valid_actions, $missing_segments, $ref_map );
                if ( is_wp_error( $built ) ) {
                    return $built;
                }
                $steps[] = $built;
                continue;
            }

            $steps[] = [
                'step_id'      => $step_id,
                'key'          => $key,
                'type'         => 'action',
                'settings'     => self::enrichSegmentTitles( is_array( $step['settings'] ?? null ) ? $step['settings'] : [], $missing_segments ),
                'next_step_id' => $merge_id,
            ];
        }

        if ( ! empty( $missing_segments ) ) {
            return MCPHelper::error(
                'invalid_state',
                'These tag/list ids do not exist: ' . implode( ', ', array_unique( $missing_segments ) ) . '. Create the tag/list first with manage-tag/manage-list, or resolve real ids with resolve-segments, then retry.'
            );
        }

        $payload = [
            'name'         => $name,
            'author'       => get_current_user_id(),
            'trigger_name' => $trigger_key,
            'status'       => 'draft',
            'steps'        => $steps,
        ];
        if ( $automation_id ) {
            $payload['id'] = $automation_id;
        }

        $saved_id = AutomationStore::get_instance()->create_or_update( $payload );
        if ( ! $saved_id ) {
            return MCPHelper::error( 'insert_failed', 'Failed to save the automation.' );
        }

        // Mirror the metas the REST controller writes on save. The automation list query
        // (AutomationStore::get_all) inner-joins on meta source=mint, so without this the
        // automation is persisted but never appears on the list page. enable_stats and
        // _at_most_date complete the UI contract for stats/date features.
        HelperFunctions::update_automation_meta( $saved_id, 'source', 'mint' );
        HelperFunctions::update_automation_meta( $saved_id, 'enable_stats', false );
        HelperFunctions::update_automation_meta( $saved_id, '_at_most_date', maybe_serialize( '' ) );

        $result = [
            'result'        => $automation_id ? 'updated' : 'created',
            'automation_id' => (int) $saved_id,
            'status'        => 'draft',
            'step_count'    => count( $steps ),
            'next_steps'    => 'Review with get-automation, then enable via set-automation-status status=active.',
        ];
        MCPHelper::idempotentStore( 'mail-mint/upsert-automation', $params, $result );
        return $result;
    }

    /**
     * Backfill human-readable titles onto tag/list references in a step's settings.
     *
     * The AI emits segment references as {"id":N} only. The visual automation builder
     * (add-tag/remove-tag/add-list/remove-list) renders each reference by its `title`,
     * so an id-only reference renders as "undefined". Load each referenced id via the
     * ContactGroupRepository and inject its current title, matching the native {id,title}
     * shape the builder stores. The DB title always wins over any title the AI sent.
     * Ids that resolve to no record are appended to $missing so the caller can reject.
     *
     * @param array $settings Raw step settings from the AI.
     * @param array $missing  Collector (by reference) for referenced ids with no matching record.
     * @return array Settings with segment titles backfilled.
     */
    private static function enrichSegmentTitles( array $settings, array &$missing ): array {
        // settings key => [ inner array key, contact-group type ].
        $maps = [
            'tag_settings'  => [ 'tags', 'tags' ],
            'list_settings' => [ 'lists', 'lists' ],
        ];
        foreach ( $maps as $settings_key => [ $items_key, $group_type ] ) {
            if ( empty( $settings[ $settings_key ][ $items_key ] ) || ! is_array( $settings[ $settings_key ][ $items_key ] ) ) {
                continue;
            }
            $repo  = new ContactGroupRepository( $group_type );
            $items = $settings[ $settings_key ][ $items_key ];
            foreach ( $items as $idx => $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }
                $id = (int) ( $item['id'] ?? 0 );
                if ( ! $id ) {
                    continue; // No id to resolve (e.g. a name-only or malformed entry).
                }
                $row = $repo->find( $id );
                if ( $row && ! empty( $row['title'] ) ) {
                    $items[ $idx ]['title'] = $row['title'];
                } else {
                    $missing[] = $id;
                }
            }
            $settings[ $settings_key ][ $items_key ] = $items;
        }
        return $settings;
    }

    /**
     * Build a single conditional (logical) step with yes/no arms for the automation payload.
     *
     * The AI supplies the condition rules plus the ordered actions for each arm; this method
     * assigns every step id, chains each arm, wires the branch pointers (logical_next_step_id)
     * and the shared merge continuation (next_step_id), and stamps the per-child branch meta
     * (popover_type/parent_index/condition_type) that AutomationStore persists as conditional_data.
     * Only one level of branching is supported — arms must contain plain actions, not nested
     * conditions.
     *
     * @param array  $step          Raw condition step from the AI ({key, condition|settings, yes[], no[]}).
     * @param string $step_id       Pre-assigned id for the condition step itself.
     * @param string $merge_id      Id of the step the branches rejoin (next top-level step), or ''.
     * @param int    $parent_index  Zero-based index of the condition step in the payload steps array.
     * @param array  $valid_actions Registered action keys.
     * @param array  $missing       Collector (by reference) for unknown tag/list ids.
     * @param array  $ref_map       Map of step "ref" → generated step_id, for email-engagement rules.
     * @return array|\WP_Error The logical step payload, or an error.
     */
    private static function buildConditionStep( array $step, string $step_id, string $merge_id, int $parent_index, array $valid_actions, array &$missing, array $ref_map = [] ): array|\WP_Error {
        // Rules may arrive as a bare `condition` array or nested under `settings.rules.condition`.
        $rules = $step['condition'] ?? ( $step['settings']['rules']['condition'] ?? null );
        if ( ! is_array( $rules ) || empty( $rules ) ) {
            return MCPHelper::error( 'invalid_state', 'A condition step needs a non-empty "condition" rule set (see branching_guide in get-automation-capabilities).' );
        }
        $rules = self::resolveEmailRefs( $rules, $ref_map );

        $arms = [ 'yes' => [], 'no' => [] ];
        foreach ( array_keys( $arms ) as $arm ) {
            $children_in = array_values( is_array( $step[ $arm ] ?? null ) ? $step[ $arm ] : [] );
            $child_ids   = [];
            foreach ( $children_in as $unused ) {
                $child_ids[] = self::generateStepId();
            }
            foreach ( $children_in as $ci => $child ) {
                $child_key = sanitize_text_field( is_array( $child ) ? ( $child['key'] ?? '' ) : '' );
                if ( 'condition' === $child_key ) {
                    return MCPHelper::error( 'invalid_state', 'Nested conditions are not supported by the assistant — keep each branch to plain actions, or build deeper logic in the visual editor.' );
                }
                if ( '' === $child_key || ! in_array( $child_key, $valid_actions, true ) ) {
                    return MCPHelper::error( 'invalid_state', sprintf( 'condition.%s[%d].key "%s" is not a registered action. Use get-automation-capabilities.', $arm, $ci, $child_key ) );
                }
                $arms[ $arm ][] = [
                    'step_id'        => $child_ids[ $ci ],
                    'key'            => $child_key,
                    'type'           => 'action',
                    'settings'       => self::enrichSegmentTitles( is_array( $child['settings'] ?? null ) ? $child['settings'] : [], $missing ),
                    // Chain within the arm; the last child rejoins the merge point.
                    'next_step_id'   => $child_ids[ $ci + 1 ] ?? $merge_id,
                    'popover_type'   => 'condition',
                    'parent_index'   => $parent_index,
                    'condition_type' => $arm,
                ];
            }
        }

        if ( empty( $arms['yes'] ) && empty( $arms['no'] ) ) {
            return MCPHelper::error( 'invalid_state', 'A condition step needs at least one action in its "yes" or "no" branch.' );
        }

        return [
            'step_id'              => $step_id,
            'key'                  => 'condition',
            'type'                 => 'logical',
            'settings'             => [ 'rules' => [ 'condition' => $rules ] ],
            // The store splits these two into the serialized next_step_id column on save.
            'next_step_id'         => $merge_id,
            'logical_next_step_id' => [
                'yes' => $arms['yes'][0]['step_id'] ?? '',
                'no'  => $arms['no'][0]['step_id'] ?? '',
            ],
            'node_data'            => $arms,
        ];
    }

    /**
     * Resolve symbolic email "ref"s inside an email-engagement condition rule set to the
     * real, server-generated step ids.
     *
     * The AI cannot know the id an in-automation email will get, so it references it by the
     * "ref" it put on that sendMail step (value entry {"value":"<ref>","source":"automation"}).
     * Here we swap that ref for the generated step_id. Non-matching values and campaign-source
     * values (which carry real campaign email ids) are left untouched.
     *
     * @param array $rules   The two-level condition rule set.
     * @param array $ref_map Map of ref → generated step_id.
     * @return array The rule set with automation-source refs resolved.
     */
    private static function resolveEmailRefs( array $rules, array $ref_map ): array {
        if ( empty( $ref_map ) ) {
            return $rules;
        }
        foreach ( $rules as $g => $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            foreach ( $group as $r => $rule ) {
                if ( ! is_array( $rule ) || empty( $rule['value'] ) || ! is_array( $rule['value'] ) ) {
                    continue;
                }
                foreach ( $rule['value'] as $v => $email ) {
                    if ( is_array( $email )
                        && 'automation' === ( $email['source'] ?? '' )
                        && isset( $ref_map[ $email['value'] ?? '' ] ) ) {
                        $rules[ $g ][ $r ]['value'][ $v ]['value'] = $ref_map[ $email['value'] ];
                    }
                }
            }
        }
        return $rules;
    }

    public static function setAutomationStatus( array $params ): array|\WP_Error {
        $automation_id = (int) ( $params['automation_id'] ?? 0 );
        $status        = sanitize_text_field( $params['status'] ?? '' );

        if ( ! $automation_id ) {
            return MCPHelper::error( 'missing_param', 'automation_id is required.' );
        }
        if ( ! in_array( $status, [ 'active', 'pause', 'draft' ], true ) ) {
            return MCPHelper::error( 'invalid_action', 'status must be one of: active, pause, draft.' );
        }

        $existing = AutomationStore::get_single( $automation_id );
        if ( empty( $existing['data'] ) ) {
            return MCPHelper::error( 'not_found', 'Automation not found.' );
        }

        HelperFunctions::update_status( $automation_id, $status );

        return [
            'result'        => 'status_changed',
            'automation_id' => $automation_id,
            'status'        => $status,
        ];
    }

    /**
     * Resolve a registry trigger entry to its canonical trigger_name.
     *
     * A connector entry's 'key' may be an underlying listening hook (e.g.
     * mint_when_contact_opens_an_email) rather than the runtime trigger name the
     * automation engine matches at dispatch (mint_opens_an_email). Entries that
     * expose an explicit 'trigger_name' use it; otherwise 'key' already is the
     * canonical name (Free connectors, WooCommerce, etc.).
     *
     * @param array $trigger A trigger registry entry.
     * @return string The canonical trigger_name.
     */
    private static function triggerName( array $trigger ): string {
        $name = $trigger['trigger_name'] ?? '';
        if ( '' === $name ) {
            $name = $trigger['key'] ?? '';
        }
        return (string) $name;
    }

    /**
     * Generate a short random step id matching the frontend's format.
     */
    private static function generateStepId(): string {
        return strtolower( wp_generate_password( 5, false, false ) );
    }

    private static function formatAutomation( $automation ): array {
        if ( is_object( $automation ) ) {
            $automation = (array) $automation;
        }

        $steps = $automation['steps'] ?? [];
        if ( is_string( $steps ) ) {
            $steps = json_decode( $steps, true ) ?: [];
        }

        return [
            'id'          => (int) ( $automation['id'] ?? 0 ),
            'title'       => $automation['name'] ?? $automation['title'] ?? '',
            'status'      => $automation['status'] ?? '',
            'trigger_name'=> $automation['trigger_name'] ?? '',
            'created_at'  => $automation['created_at'] ?? '',
            'updated_at'  => $automation['updated_at'] ?? '',
            'steps'       => $steps,
        ];
    }

}
