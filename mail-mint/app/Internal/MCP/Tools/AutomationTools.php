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
use Mint\MRM\Internal\MCP\Helpers\EmailComposer;
use Mint\MRM\Internal\MCP\Helpers\EmailPlaybooks;
use Mint\MRM\Utilities\Helper\PermissionManager;
use MRM\Common\MrmCommon;
use MintMail\App\Internal\Automation\AutomationModel as AutomationStore;
use MintMail\App\Internal\Automation\AutomationStepModel;
use MintMail\App\Internal\Automation\Action\AutomationAction;
use MintMail\App\Internal\Automation\Connector;
use MintMail\App\Internal\Automation\HelperFunctions;
use MintMail\App\Internal\Automation\TriggerCatalog;
use MintMail\App\Internal\Automation\TriggerAvailability;
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
                'description' => 'The complete catalogue of automation triggers Mail Mint supports, plus the action types available on this site. Every trigger is returned: those usable right now under "triggers.available", and those that exist but need something first under "triggers.unavailable_groups" (each group states exactly what is missing and how to fix it). A trigger listed as unavailable STILL EXISTS — report what it needs, never that Mail Mint lacks it. Call this before upsert-automation; trigger and action keys must come from here. To match a plain-language goal ("abandoned cart recovery") to trigger keys, use find-automation-trigger instead of scanning this list.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'include_unavailable' => [ 'type' => 'boolean', 'default' => true, 'description' => 'Set false to omit triggers this site cannot use yet. Only do this when you have already told the user what is unavailable — omitting them is what makes an assistant wrongly claim a trigger does not exist.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getAutomationCapabilities' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_automations' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/find-automation-trigger' => [
                'label'       => __( 'Find Automation Trigger', 'mail-mint' ),
                'description' => 'Search the full trigger catalogue by plain-language goal ("abandoned cart recovery", "someone buys a subscription", "student finishes a course") and get back the matching trigger keys ranked by relevance, each with its availability. ALWAYS call this before telling a user that Mail Mint has no trigger for something — Mail Mint ships 80+ triggers across WooCommerce, LMS, form, and funnel integrations, and most are invisible until their integration is active. If a match comes back unavailable, tell the user the trigger exists and what it needs; do not tell them it does not exist.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'query' ],
                    'properties' => [
                        'query'             => [ 'type' => 'string', 'description' => 'The user\'s goal in their own words, e.g. "recover abandoned carts" or "when a subscription is about to renew".' ],
                        'limit'             => [ 'type' => 'integer', 'default' => 8, 'description' => 'Maximum matches to return (1-25).' ],
                        'only_available'    => [ 'type' => 'boolean', 'default' => false, 'description' => 'Restrict to triggers usable on this site right now. Leave false so you can still tell the user about triggers that exist but need Pro or another plugin.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'findAutomationTrigger' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_automations' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/upsert-automation' => [
                'label'       => __( 'Upsert Automation', 'mail-mint' ),
                'description' => 'Create or update an automation: one trigger followed by sequential action steps, optionally including ONE level of conditional branching. Created as draft; enable with set-automation-status. Always call get-automation-capabilities first — validate keys AND read its "branching" field: only emit a condition step when branching.available is true (otherwise explain branching.message to the user and build the linear part). Common action settings shapes: delay {"delay_settings":{"delay":1,"unit":"minutes|hours|days|weeks"}}; addTag {"tag_settings":{"tags":[{"id":N}]}}; addList {"list_settings":{"lists":[{"id":N}]}}; sendMail — do NOT hand-write message_data; pass the step\'s "subject", "preview_text" and structured "content" instead and Mail Mint composes both the sent HTML and the builder tree (sender fields default to the site\'s email settings). Branching: a step with key "condition" takes "condition" (rule set) plus "yes" and "no" arrays of action steps; see branching_guide in get-automation-capabilities for the exact rule shape. For "opened within N days" put a delay before the condition and check engagement — the arms rejoin automatically after the branch.',
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
                                    'key'          => [ 'type' => 'string', 'description' => 'An action key from get-automation-capabilities (e.g. delay, sendMail, addTag, addList), or "condition" for a branch.' ],
                                    'settings'     => [ 'type' => 'object' ],
                                    'subject'      => [ 'type' => 'string', 'description' => 'sendMail only: the email subject. Keep it under ~50 characters.' ],
                                    'preview_text' => [ 'type' => 'string', 'description' => 'sendMail only: the inbox preview line. Complements the subject, never repeats it.' ],
                                    'content'      => [
                                        'type'        => 'object',
                                        'description' => 'sendMail only: structured email content, SAME shape as compose-campaign-email — {hero:{heading,subheading}, sections:[{type:heading|paragraph|bullets|button|image|divider|spacer, ...}], footer_text, include_footer}. Mail Mint renders it into both the sent HTML and the visual builder tree. ALWAYS pass this for every sendMail step: writing raw HTML into settings.message_data.body produces a step that sends but shows as an empty design in the builder, and omitting content entirely means Mail Mint substitutes generic best-practice copy the user then has to rewrite. Use the trigger\'s merge tags in the copy ({{contact.first_name}} always; {{cart.items}}, {{cart.total}} and {{cart.recovery_url}} for abandoned cart triggers).',
                                    ],
                                    'style'        => [ 'type' => 'object', 'description' => 'sendMail only: {preset} plus optional colour overrides, same as compose-campaign-email.' ],
                                    'ref'          => [ 'type' => 'string', 'description' => 'Optional stable label for this step (e.g. "welcome_email"). A condition rule can reference this step\'s email by ref instead of an id — see branching_guide.' ],
                                    'condition'    => [ 'type' => 'array', 'description' => 'Only for key="condition": the rule set (array of OR-groups, each an array of AND-rules). See branching_guide.' ],
                                    'yes'          => [ 'type' => 'array', 'description' => 'Only for key="condition": ordered action steps run when the condition matches. Same shape as steps items (no nested conditions).' ],
                                    'no'           => [ 'type' => 'array', 'description' => 'Only for key="condition": ordered action steps run when the condition does NOT match.' ],
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

            'mail-mint/compose-automation-email' => [
                'label'       => __( 'Compose Automation Email', 'mail-mint' ),
                'description' => 'Write or rewrite the email on ONE sendMail step of an existing automation, without touching the rest of the flow. Use this to personalise an email Mail Mint filled from its best-practice playbook, or to change one email\'s copy after the automation is built — upsert-automation would replace every step. Takes the same structured content as compose-campaign-email and writes both the sent HTML and the visual builder tree, so the step is editable in the builder afterwards.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'automation_id', 'step_id', 'subject', 'content' ],
                    'properties' => [
                        'automation_id' => [ 'type' => 'integer' ],
                        'step_id'       => [ 'type' => 'string', 'description' => 'The sendMail step to write. Get it from get-automation — each step reports its step_id.' ],
                        'subject'       => [ 'type' => 'string' ],
                        'preview_text'  => [ 'type' => 'string', 'description' => 'Inbox preview line. Complements the subject rather than repeating it.' ],
                        'content'       => [ 'type' => 'object', 'description' => 'Structured content: {hero:{heading,subheading}, sections:[...], footer_text, include_footer}. Same shape as compose-campaign-email.' ],
                        'style'         => [ 'type' => 'object', 'description' => '{preset} plus optional colour overrides.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'composeAutomationEmail' ],
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
            // An automation email's HTML lives at settings.message_data.body, not at
            // the top level, so the original unset() here stripped nothing and every
            // get-automation call returned several KB of markup per email step. Strip
            // where the body actually is, and leave a marker so the model can tell an
            // email that exists from one that is genuinely empty.
            $formatted['steps'] = array_map( [ self::class, 'stripStepBodies' ], (array) $formatted['steps'] );
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

    /**
     * The site's automation building blocks.
     *
     * Reads TriggerCatalog (every trigger the visual builder offers, generated
     * from the builder registry) rather than the connector registry this tool
     * used to read. The connector arrays are hand-maintained and nothing else
     * in the plugin consumes them, so they had drifted 51 triggers behind the
     * builder — including all three abandoned-cart triggers. Reading them made
     * the assistant tell users that triggers they could see on screen did not
     * exist. The catalogue cannot drift: a test fails when it does.
     */
    public static function getAutomationCapabilities( array $params ): array|\WP_Error {
        $include_unavailable = ! isset( $params['include_unavailable'] ) || (bool) $params['include_unavailable'];

        $available   = [];
        $unavailable = [];

        foreach ( TriggerAvailability::all() as $key => $trigger ) {
            $verdict = $trigger['availability'];
            $entry   = [
                'key'      => $key,
                'label'    => $trigger['label'],
                'category' => TriggerCatalog::category_label( $trigger['category'] ),
            ];

            if ( $verdict['available'] ) {
                if ( '' !== $trigger['description'] ) {
                    $entry['description'] = $trigger['description'];
                }
                $available[] = $entry;
                continue;
            }

            $entry['status'] = $verdict['status'];
            $unavailable[]   = [ 'entry' => $entry, 'verdict' => $verdict ];
        }

        // Actions are still runtime-registered through the documented
        // `mint_supported_automation_actions` filter, which Pro extends. That
        // registry IS authoritative for actions, so it stays the source here.
        $actions     = AutomationAction::get_instance()->supported_actions();
        $action_list = [];
        foreach ( (array) $actions as $key => $label ) {
            $action_list[] = [ 'key' => $key, 'label' => is_string( $label ) ? $label : $key ];
        }

        $branching = self::branchingAvailability( array_keys( (array) $actions ) );
        if ( $branching['available'] ) {
            // Only ship the (token-heavy) guide when branches can actually be built.
            $branching['branching_guide'] = self::branchingGuide();
        }

        $triggers = [
            'available'       => $available,
            'available_count' => count( $available ),
            'total_count'     => count( TriggerCatalog::all() ),
        ];

        if ( $include_unavailable ) {
            $triggers['unavailable_groups'] = self::groupUnavailableTriggers( $unavailable );
            $triggers['unavailable_note']   = 'These triggers are part of Mail Mint and appear in the automation builder. They are not usable on THIS site yet. When a user asks for one, say it exists and name what is missing from "missing" — never say Mail Mint does not have it.';
        }

        return [
            'triggers'    => $triggers,
            'actions'     => $action_list,
            'statuses'    => [ 'draft', 'active', 'pause' ],
            'branching'   => $branching,
            'note'        => $branching['available']
                ? 'upsert-automation supports linear flows AND one level of conditional branching: add a step with key "condition" (see the branching_guide).'
                : 'upsert-automation supports linear flows only on this site (' . $branching['reason'] . '). Do not emit a "condition" step — explain the reason to the user and offer the upgrade, then build the linear part.',
            'lookup_hint' => 'Matching a user goal to a trigger? Call find-automation-trigger with their own words instead of guessing from this list.',
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
                    'abandoned_cart'=> 'wc_abandoned_cart → delay 1 hour → sendMail (cart reminder) → delay 1 day → sendMail (incentive) → delay 2 days → sendMail (last chance). Requires WooCommerce, Mail Mint Pro, and cart tracking enabled.',
                ],
            ],
        ];
    }

    /**
     * Collapses unavailable triggers into one group per distinct set of missing
     * requirements.
     *
     * Eighty-odd trigger entries each repeating "needs Mail Mint Pro and
     * WooCommerce" wastes context and buries the point. One group per reason
     * keeps the payload small while still naming every trigger, which is what
     * stops the assistant concluding a trigger does not exist.
     *
     * @param array $unavailable Pairs of { entry, verdict }.
     * @return array
     */
    private static function groupUnavailableTriggers( array $unavailable ): array {
        $groups = [];

        foreach ( $unavailable as $item ) {
            $verdict = $item['verdict'];
            $bucket  = $verdict['status'] . '|' . implode( '+', $verdict['missing'] );

            if ( ! isset( $groups[ $bucket ] ) ) {
                $groups[ $bucket ] = [
                    'status'   => $verdict['status'],
                    'missing'  => $verdict['missing'],
                    'remedies' => $verdict['remedies'],
                    'triggers' => [],
                ];
                if ( isset( $verdict['upgrade_url'] ) ) {
                    $groups[ $bucket ]['upgrade_url'] = $verdict['upgrade_url'];
                }
                if ( 'not_dispatched' === $verdict['status'] ) {
                    $groups[ $bucket ]['warning'] = 'Known bug: these appear in the builder but nothing fires them. Do not build automations on these keys.';
                }
            }

            $entry = $item['entry'];
            unset( $entry['status'] );
            $groups[ $bucket ]['triggers'][] = $entry;
        }

        return array_values( $groups );
    }

    /**
     * Ranks the trigger catalogue against a plain-language goal.
     *
     * Exists because the assistant's failure mode was never a bad key — it was
     * concluding from an incomplete list that no trigger existed and telling
     * the user so. Giving it one call that searches everything, available or
     * not, removes the guess.
     */
    public static function findAutomationTrigger( array $params ): array|\WP_Error {
        $query = trim( (string) ( $params['query'] ?? '' ) );
        if ( '' === $query ) {
            return MCPHelper::error( 'missing_param', 'query is required.' );
        }

        $limit          = (int) ( $params['limit'] ?? 8 );
        $limit          = max( 1, min( 25, $limit ) );
        $only_available = ! empty( $params['only_available'] );

        $terms = self::searchTerms( $query );
        if ( empty( $terms ) ) {
            return MCPHelper::error( 'invalid_state', 'query had no searchable words. Pass the user\'s goal in plain language.' );
        }

        $scored = [];
        foreach ( TriggerAvailability::all() as $key => $trigger ) {
            $verdict = $trigger['availability'];
            if ( $only_available && ! $verdict['available'] ) {
                continue;
            }

            $score = self::scoreTrigger( $terms, $key, $trigger );
            if ( $score <= 0 ) {
                continue;
            }

            $match = [
                'key'          => $key,
                'label'        => $trigger['label'],
                'category'     => TriggerCatalog::category_label( $trigger['category'] ),
                'package'      => $trigger['package'],
                'available'    => $verdict['available'],
                'status'       => $verdict['status'],
                'score'        => $score,
            ];
            if ( '' !== $trigger['description'] ) {
                $match['description'] = $trigger['description'];
            }
            if ( ! $verdict['available'] ) {
                $match['reason']   = $verdict['reason'];
                $match['missing']  = $verdict['missing'];
                $match['remedies'] = $verdict['remedies'];
                if ( isset( $verdict['upgrade_url'] ) ) {
                    $match['upgrade_url'] = $verdict['upgrade_url'];
                }
            }
            $scored[] = $match;
        }

        // Rank by score, then put usable triggers ahead of ones that need setup,
        // then by key so identical scores stay in a stable order.
        usort(
            $scored,
            function ( $a, $b ) {
                if ( $a['score'] !== $b['score'] ) {
                    return $b['score'] <=> $a['score'];
                }
                if ( $a['available'] !== $b['available'] ) {
                    return $a['available'] ? -1 : 1;
                }
                return strcmp( $a['key'], $b['key'] );
            }
        );

        $matches = array_slice( $scored, 0, $limit );

        if ( empty( $matches ) ) {
            return [
                'query'      => $query,
                'matches'    => [],
                'categories' => array_values( array_unique( array_map(
                    function ( $trigger ) {
                        return TriggerCatalog::category_label( $trigger['category'] );
                    },
                    TriggerCatalog::all()
                ) ) ),
                'guidance'   => 'No trigger matched those words. This means Mail Mint has no trigger for that specific event — say so plainly, list the categories above as what IS covered, and suggest the closest alternative. Do not guess a key.',
            ];
        }

        $unavailable = array_values( array_filter( $matches, function ( $m ) { return ! $m['available']; } ) );

        $result = [
            'query'       => $query,
            'matches'     => $matches,
            'total_count' => count( TriggerCatalog::all() ),
        ];

        if ( ! empty( $unavailable ) ) {
            $result['guidance'] = 'Some matches are not usable on this site yet. They DO exist in Mail Mint — tell the user the trigger exists, name what "missing" lists, and give the remedy. Never answer that Mail Mint has no such trigger.';
        }

        // A low top score means the query only glanced off shared vocabulary
        // (every order trigger matches "order"). Say so rather than letting a
        // weak match read as a confident answer.
        if ( $matches[0]['score'] < 6 ) {
            $result['match_quality'] = 'weak';
            $result['guidance']      = trim(
                ( $result['guidance'] ?? '' )
                . ' No trigger closely matches this goal — the results above only share some wording. Confirm with the user which of these they mean, or tell them Mail Mint has no trigger for that exact event, before building anything.'
            );
        }

        return $result;
    }

    /**
     * Normalises a query into scoreable terms.
     *
     * Synonyms bridge the gap between how users describe a goal ("cart
     * recovery", "win back customers") and the vocabulary the trigger labels
     * use, which is the gap the assistant kept falling into.
     */
    private static function searchTerms( string $query ): array {
        $synonyms = [
            'abandoned'    => [ 'abandoned', 'cart' ],
            'abandon'      => [ 'abandoned', 'cart' ],
            'cart'         => [ 'cart', 'abandoned' ],
            'recover'      => [ 'recovered', 'cart', 'abandoned' ],
            'recovery'     => [ 'recovered', 'cart', 'abandoned' ],
            'checkout'     => [ 'cart', 'order' ],
            'purchase'     => [ 'order', 'purchase' ],
            'buy'          => [ 'order', 'purchase' ],
            'bought'       => [ 'order', 'purchase' ],
            'sale'         => [ 'order', 'purchase' ],
            'sold'         => [ 'order', 'purchase' ],
            'refund'       => [ 'refunded', 'order' ],
            'winback'      => [ 'winback', 'customer' ],
            'lapsed'       => [ 'winback', 'inactive' ],
            'dormant'      => [ 'inactive', 'winback' ],
            'reengage'     => [ 'inactive', 'winback' ],
            'birthday'     => [ 'anniversary' ],
            'signup'       => [ 'registration', 'submitted', 'contact' ],
            'signs'        => [ 'registration', 'submitted' ],
            'register'     => [ 'registration' ],
            'subscribe'    => [ 'subscription', 'list' ],
            'subscriber'   => [ 'subscription', 'contact' ],
            'renew'        => [ 'renewal', 'subscription' ],
            'renewal'      => [ 'renewal', 'subscription' ],
            'trial'        => [ 'trial', 'subscription' ],
            'membership'   => [ 'membership', 'member' ],
            'course'       => [ 'course', 'enrolls', 'completes' ],
            'student'      => [ 'course', 'student', 'enrolls' ],
            'enroll'       => [ 'enrolls', 'course' ],
            'enrolled'     => [ 'enrolls', 'course' ],
            'lesson'       => [ 'lesson', 'course' ],
            'quiz'         => [ 'quiz', 'course' ],
            'form'         => [ 'form', 'submitted' ],
            'submit'       => [ 'submitted', 'form' ],
            'submission'   => [ 'submitted', 'form' ],
            'optin'        => [ 'optin', 'form', 'submitted' ],
            'review'       => [ 'review' ],
            'wishlist'     => [ 'wishlist' ],
            'price'        => [ 'price', 'dropped' ],
            'discount'     => [ 'price', 'dropped' ],
            'tag'          => [ 'tag' ],
            'list'         => [ 'list' ],
            'segment'      => [ 'segment' ],
            'webhook'      => [ 'webhook' ],
            'click'        => [ 'click', 'link' ],
            'open'         => [ 'open', 'email' ],
            'welcome'      => [ 'registration', 'contact', 'created' ],
            'booking'      => [ 'booking' ],
            'appointment'  => [ 'booking' ],
            'funnel'       => [ 'funnel' ],
            'upsell'       => [ 'upsell', 'funnel' ],
            'downsell'     => [ 'downsell', 'funnel' ],
            'post'         => [ 'post', 'publish' ],
            'blog'         => [ 'post', 'publish' ],
        ];

        $stopwords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'can', 'do', 'does',
            'for', 'from', 'has', 'have', 'how', 'i', 'if', 'in', 'is', 'it', 'me', 'my',
            'of', 'on', 'or', 'send', 'set', 'so', 'someone', 'that', 'the', 'their',
            'them', 'then', 'they', 'this', 'to', 'up', 'want', 'was', 'we', 'what',
            'when', 'who', 'will', 'with', 'you', 'your', 'automation', 'trigger',
            'email', 'emails', 'mail', 'create', 'make', 'build', 'new', 'customer',
            'contact', 'user', 'people', 'person',
        ];

        $words = preg_split( '/[^a-z0-9]+/', strtolower( $query ), -1, PREG_SPLIT_NO_EMPTY );
        $terms = [];

        foreach ( (array) $words as $word ) {
            if ( strlen( $word ) < 3 || in_array( $word, $stopwords, true ) ) {
                continue;
            }
            $terms[] = $word;
            if ( isset( $synonyms[ $word ] ) ) {
                foreach ( $synonyms[ $word ] as $expanded ) {
                    $terms[] = $expanded;
                }
            }
        }

        return array_values( array_unique( $terms ) );
    }

    /**
     * Weighs one trigger against the query terms.
     *
     * The key and label carry the most signal, the category groups related
     * triggers, and the description catches phrasing the label misses.
     *
     * Terms match at a word start rather than anywhere in the string. Plain
     * substring matching looked fine until a query containing "rig" scored
     * every trigger whose description mentions "trigger"; anchoring to word
     * starts keeps the useful stemming ("recover" → "recovered", "enroll" →
     * "enrolls") without the nonsense.
     */
    private static function scoreTrigger( array $terms, string $key, array $trigger ): int {
        $haystacks = [
            3 => strtolower( str_replace( '_', ' ', $key ) ),
            4 => strtolower( $trigger['label'] ),
            2 => strtolower( TriggerCatalog::category_label( $trigger['category'] ) ),
            1 => strtolower( $trigger['description'] ),
        ];

        $score = 0;
        foreach ( $terms as $term ) {
            $pattern = '/\\b' . preg_quote( $term, '/' ) . '/';
            foreach ( $haystacks as $weight => $haystack ) {
                if ( '' !== $haystack && preg_match( $pattern, $haystack ) ) {
                    $score += $weight;
                }
            }
        }
        return $score;
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

        // Resolve the trigger to the canonical trigger_name the engine matches at
        // dispatch. Legacy connector 'key' values (listening hooks such as
        // mailmint_process_contact_anniversary_daily) are still accepted as input
        // for robustness, but storage always uses the canonical name — storing a
        // listening hook produces an automation that never fires.
        $trigger_key = self::canonicalTriggerKey( $trigger_key );

        // Validate against the catalogue, not the connector registry. The registry
        // is missing dozens of triggers the builder offers, so validating on it
        // rejected keys that are perfectly valid (wc_abandoned_cart among them).
        $definition = TriggerCatalog::get( $trigger_key );
        if ( null === $definition ) {
            // A handful of connector-registry triggers (AffiliateWP, some legacy
            // WPFunnels names) dispatch at runtime but have no step in the visual
            // builder. An automation built on one is invisible and uneditable in
            // the UI — it renders as "Unknown trigger" — so refuse it and say why
            // rather than reporting it as a nonexistent key.
            if ( self::isRegisteredWithoutBuilderStep( $trigger_key ) ) {
                return MCPHelper::error(
                    'invalid_state',
                    sprintf(
                        'The trigger "%s" exists in Mail Mint\'s runtime registry but has no step in the automation builder, so an automation using it would show "Unknown trigger" and could not be edited. Tell the user it is not usable from here and pick a catalogued trigger instead — call find-automation-trigger to find one.',
                        $trigger_key
                    )
                );
            }

            return MCPHelper::error(
                'invalid_state',
                sprintf(
                    'Unknown trigger key "%s". Call find-automation-trigger with the user\'s goal in plain language to get the right key — do not tell the user Mail Mint lacks the feature until that search comes back empty.',
                    $trigger_key
                )
            );
        }

        // A catalogued trigger the site cannot run yet must not be stored: the
        // automation would look correct in the builder and silently never fire.
        // Fail with the specific missing requirement so the assistant relays a
        // real answer instead of guessing.
        $availability = TriggerAvailability::for_trigger( $trigger_key );
        if ( ! $availability['available'] ) {
            return MCPHelper::error(
                'invalid_state',
                $availability['reason'] . ' ' . implode( ' ', $availability['remedies'] )
                    . ' Tell the user this trigger EXISTS and what it needs — do not tell them Mail Mint has no such trigger.',
                array_filter(
                    [
                        'trigger_key' => $trigger_key,
                        'status'      => $availability['status'],
                        'missing'     => $availability['missing'],
                        'upgrade_url' => $availability['upgrade_url'] ?? null,
                    ]
                )
            );
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

        // Count email steps before building any, so playbook copy knows whether it
        // is writing the first touch or the last-chance one. Condition arms count
        // too — an email inside a branch is still an email the user will receive.
        $email_total   = self::countEmailSteps( $steps_in );
        $email_seen    = 0;
        $composed_notes = [];

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
                $built = self::buildConditionStep( $step, $step_id, $merge_id, $i + 1, $valid_actions, $missing_segments, $ref_map, $trigger_key, $email_seen, $email_total, $composed_notes );
                if ( is_wp_error( $built ) ) {
                    return $built;
                }
                $steps[] = $built;
                continue;
            }

            $settings = self::enrichSegmentTitles( is_array( $step['settings'] ?? null ) ? $step['settings'] : [], $missing_segments );

            if ( self::isEmailStep( $key ) ) {
                ++$email_seen;
                $settings = self::composeEmailStep( $key, $step, $settings, $trigger_key, $email_seen, $email_total, $composed_notes, sprintf( 'steps[%d]', $i ) );
                if ( is_wp_error( $settings ) ) {
                    return $settings;
                }
            }

            $steps[] = [
                'step_id'      => $step_id,
                'key'          => $key,
                'type'         => 'action',
                'settings'     => $settings,
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
            'email_count'   => $email_seen,
            'next_steps'    => 'Review with get-automation, then enable via set-automation-status status=active.',
        ];

        if ( ! empty( $composed_notes ) ) {
            $result['email_notes'] = $composed_notes;

            $review = array_values( array_filter( $composed_notes, function ( $note ) {
                return ! empty( $note['needs_review'] );
            } ) );
            if ( ! empty( $review ) ) {
                $result['emails_need_review'] = count( $review );
                $result['guidance']           = sprintf(
                    '%d email(s) were filled with Mail Mint\'s best-practice copy because none was supplied. Tell the user exactly which steps those are and that the copy should be personalised before activating — then offer to rewrite them with compose-automation-email.',
                    count( $review )
                );
            }
        }
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
    private static function buildConditionStep( array $step, string $step_id, string $merge_id, int $parent_index, array $valid_actions, array &$missing, array $ref_map, string $trigger_key, int &$email_seen, int $email_total, array &$composed_notes ): array|\WP_Error {
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
                $child_settings = self::enrichSegmentTitles( is_array( $child['settings'] ?? null ) ? $child['settings'] : [], $missing );
                if ( self::isEmailStep( $child_key ) ) {
                    ++$email_seen;
                    $child_settings = self::composeEmailStep(
                        $child_key,
                        is_array( $child ) ? $child : [],
                        $child_settings,
                        $trigger_key,
                        $email_seen,
                        $email_total,
                        $composed_notes,
                        sprintf( 'condition.%s[%d]', $arm, $ci )
                    );
                    if ( is_wp_error( $child_settings ) ) {
                        return $child_settings;
                    }
                }

                $arms[ $arm ][] = [
                    'step_id'        => $child_ids[ $ci ],
                    'key'            => $child_key,
                    'type'           => 'action',
                    'settings'       => $child_settings,
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
     * Maps a caller-supplied trigger key onto its canonical trigger_name.
     *
     * Most keys are already canonical. The exceptions are the legacy connector
     * entries that pair a listening hook ('key') with the name the engine
     * dispatches ('trigger_name') — for example the anniversary and inactive
     * subscriber triggers. Those pairings live only in the connector registry,
     * so it is still consulted here purely as an alias table.
     *
     * @param string $key Trigger key as supplied by the caller.
     * @return string The canonical trigger_name, or the input unchanged.
     */
    private static function canonicalTriggerKey( string $key ): string {
        if ( null !== TriggerCatalog::get( $key ) ) {
            return $key;
        }

        foreach ( (array) Connector::get_instance()->get_triggers() as $triggers ) {
            foreach ( (array) $triggers as $t ) {
                $t = (array) $t;
                if ( ( $t['key'] ?? '' ) === $key ) {
                    $canonical = self::triggerName( $t );
                    if ( '' !== $canonical ) {
                        return $canonical;
                    }
                }
            }
        }

        return $key;
    }

    /**
     * Writes the email on ONE sendMail step of an existing automation.
     *
     * upsert-automation replaces the whole step list, so it is the wrong tool
     * for "make the second email friendlier". This edits a single step in
     * place, writing both representations the builder and the mailer need.
     */
    public static function composeAutomationEmail( array $params ): array|\WP_Error {
        $automation_id = (int) ( $params['automation_id'] ?? 0 );
        $step_id       = sanitize_text_field( (string) ( $params['step_id'] ?? '' ) );
        $subject       = sanitize_text_field( (string) ( $params['subject'] ?? '' ) );
        $preview_text  = sanitize_text_field( (string) ( $params['preview_text'] ?? '' ) );

        if ( ! $automation_id ) {
            return MCPHelper::error( 'missing_param', 'automation_id is required.' );
        }
        if ( '' === $step_id ) {
            return MCPHelper::error( 'missing_param', 'step_id is required. Call get-automation to list each step and its step_id.' );
        }
        if ( '' === $subject ) {
            return MCPHelper::error( 'missing_param', 'subject is required.' );
        }
        if ( ! is_array( $params['content'] ?? null ) ) {
            return MCPHelper::error( 'missing_param', 'content object is required.' );
        }

        $steps = HelperFunctions::get_all_step_by_automation_id( $automation_id );
        if ( empty( $steps ) || ! is_array( $steps ) ) {
            return MCPHelper::error( 'not_found', 'Automation not found, or it has no steps.' );
        }

        $target = null;
        foreach ( $steps as $step ) {
            if ( isset( $step['step_id'] ) && $step['step_id'] === $step_id ) {
                $target = $step;
                break;
            }
        }
        if ( null === $target ) {
            return MCPHelper::error(
                'not_found',
                sprintf( 'No step "%s" on automation %d. Call get-automation and use a step_id from its steps array.', $step_id, $automation_id )
            );
        }

        $key = (string) ( $target['key'] ?? '' );
        if ( ! self::isEmailStep( $key ) ) {
            return MCPHelper::error(
                'invalid_state',
                sprintf( 'Step "%s" is a "%s" step, which has no email to write. Only sendMail and sendMailNotification steps do.', $step_id, $key )
            );
        }

        $composed = EmailComposer::compose(
            (array) $params['content'],
            is_array( $params['style'] ?? null ) ? $params['style'] : []
        );
        if ( is_wp_error( $composed ) ) {
            return $composed;
        }

        $settings_key = self::emailSettingsKey( $key );
        $settings     = maybe_unserialize( $target['settings'] ?? [] );
        $settings     = is_array( $settings ) ? $settings : [];
        $email        = is_array( $settings[ $settings_key ] ?? null ) ? $settings[ $settings_key ] : [];

        $email['subject']   = $subject;
        $email['body']      = $composed['html'];
        $email['json_body'] = [
            'content' => $composed['json'],
            'editor'  => 'advanced-builder',
        ];
        if ( 'notification_email' === $settings_key ) {
            $email['preview_text'] = $preview_text;
        } else {
            $email['email_preview_text'] = $preview_text;
        }
        // The copy is the user's now, not the playbook's.
        unset( $email['mint_ai_needs_review'] );

        $settings[ $settings_key ] = array_merge( self::defaultSenderFields( $settings_key ), $email );

        $updated = AutomationStepModel::get_instance()->update(
            [
                'id'       => (int) ( $target['id'] ?? 0 ),
                'key'      => $key,
                'settings' => $settings,
            ]
        );
        if ( ! $updated ) {
            return MCPHelper::error( 'update_failed', 'Failed to save the email onto that step.' );
        }

        return [
            'result'        => 'updated',
            'automation_id' => $automation_id,
            'step_id'       => $step_id,
            'subject'       => $subject,
            'next_steps'    => 'The step now has both a sendable HTML body and an editable builder design. Review with get-automation.',
        ];
    }

    /* -----------------------------------------------------------------------
     * Automation email composition
     *
     * An automation step's email lives in two fields that must agree:
     *   message_data.body      — the HTML actually sent
     *   message_data.json_body — {content, editor}, the builder tree
     *
     * The visual builder renders a step's design ONLY when json_body is present
     * (send-mail/edit.js), so an email written as bare `body` HTML shows up as
     * an empty step even though it would send fine. upsert-automation used to
     * pass settings straight through, so the model's inline HTML — when it
     * bothered to write any — produced exactly that: a flow that looks complete
     * and an email that looks missing.
     *
     * Composing both here, from the same EmailComposer campaigns use, is what
     * makes an automation email real in the builder AND in the inbox.
     * --------------------------------------------------------------------- */

    /**
     * Which action keys carry an email the user will receive.
     *
     * @param string $key Action step key.
     * @return bool
     */
    private static function isEmailStep( string $key ): bool {
        return in_array( $key, [ 'sendMail', 'sendMailNotification' ], true );
    }

    /**
     * Where a step key keeps its email payload.
     *
     * @param string $key Action step key.
     * @return string Settings sub-key.
     */
    private static function emailSettingsKey( string $key ): string {
        return 'sendMailNotification' === $key ? 'notification_email' : 'message_data';
    }

    /**
     * Counts the email steps in a submitted step list, including condition arms.
     *
     * @param array $steps_in Raw steps as supplied by the caller.
     * @return int At least 1, so a lone email is treated as a first touch.
     */
    private static function countEmailSteps( array $steps_in ): int {
        $total = 0;
        foreach ( $steps_in as $step ) {
            if ( ! is_array( $step ) ) {
                continue;
            }
            $key = (string) ( $step['key'] ?? '' );
            if ( self::isEmailStep( $key ) ) {
                ++$total;
                continue;
            }
            if ( 'condition' === $key ) {
                foreach ( [ 'yes', 'no' ] as $arm ) {
                    foreach ( (array) ( $step[ $arm ] ?? [] ) as $child ) {
                        if ( is_array( $child ) && self::isEmailStep( (string) ( $child['key'] ?? '' ) ) ) {
                            ++$total;
                        }
                    }
                }
            }
        }
        return max( 1, $total );
    }

    /**
     * Produces a send-ready email for one step.
     *
     * Content precedence:
     *   1. `content` on the step (structured, same shape as compose-campaign-email)
     *   2. existing `message_data.body` HTML the caller wrote by hand
     *   3. the trigger's playbook — never nothing
     *
     * @param string $key         Action step key.
     * @param array  $step        Raw step as supplied by the caller.
     * @param array  $settings    Settings built so far for this step.
     * @param string $trigger_key Canonical trigger_name, for playbook selection.
     * @param int    $position    1-based index among this automation's email steps.
     * @param int    $total       Total email steps.
     * @param array  $notes       Collects per-step notes for the tool response.
     * @param string $label       How to name this step in errors.
     * @return array|\WP_Error Settings with a composed email.
     */
    private static function composeEmailStep( string $key, array $step, array $settings, string $trigger_key, int $position, int $total, array &$notes, string $label ) {
        $settings_key = self::emailSettingsKey( $key );
        $email        = is_array( $settings[ $settings_key ] ?? null ) ? $settings[ $settings_key ] : [];

        $content = is_array( $step['content'] ?? null ) ? $step['content'] : null;
        $style   = is_array( $step['style'] ?? null ) ? $step['style'] : [];

        $subject      = sanitize_text_field( (string) ( $step['subject'] ?? $email['subject'] ?? '' ) );
        $preview_text = sanitize_text_field( (string) ( $step['preview_text'] ?? $email['email_preview_text'] ?? $email['preview_text'] ?? '' ) );

        $existing_body = trim( (string) ( $email['body'] ?? '' ) );
        $from_playbook = false;

        if ( null === $content && '' === $existing_body ) {
            // Nothing to send. Rather than storing a step that mails an empty
            // message, fall back to expert copy for this trigger and flag it.
            $plan          = EmailPlaybooks::for_step( $trigger_key, $position, $total );
            $content       = $plan['content'];
            $from_playbook = true;
            if ( '' === $subject ) {
                $subject = $plan['subject'];
            }
            if ( '' === $preview_text ) {
                $preview_text = $plan['preview_text'];
            }
            $notes[] = [
                'step'        => $label,
                'source'      => 'playbook',
                'playbook'    => $plan['playbook'],
                'needs_review'=> true,
                'message'     => sprintf(
                    'No email content was supplied for %s, so Mail Mint filled it with best-practice copy for this trigger. Tell the user to review and personalise it.',
                    $label
                ),
            ];
        }

        if ( null !== $content ) {
            $composed = EmailComposer::compose( $content, $style );
            if ( is_wp_error( $composed ) ) {
                return MCPHelper::error(
                    'invalid_content',
                    sprintf( '%s email content is invalid: %s', $label, $composed->get_error_message() )
                );
            }
            $email['body'] = $composed['html'];
            // The builder reads {content, editor}; without it the step renders
            // as an empty design even though body would send correctly.
            $email['json_body'] = [
                'content' => $composed['json'],
                'editor'  => 'advanced-builder',
            ];
        } elseif ( empty( $email['json_body'] ) ) {
            // Hand-written HTML with no builder tree: keep the HTML (it sends)
            // but say so, because the step will not render in the visual editor.
            $notes[] = [
                'step'    => $label,
                'source'  => 'raw_html',
                'message' => sprintf(
                    '%s was given raw HTML instead of structured content, so it will send but cannot be edited in the visual builder. Pass "content" instead to make it editable.',
                    $label
                ),
            ];
        }

        if ( '' === $subject ) {
            $plan    = EmailPlaybooks::for_step( $trigger_key, $position, $total );
            $subject = $plan['subject'];
        }

        $email['subject'] = $subject;
        if ( 'notification_email' === $settings_key ) {
            $email['preview_text'] = $preview_text;
        } else {
            $email['email_preview_text'] = $preview_text;
        }

        // Fill sender identity from the site's email settings when the caller
        // left it out — the builder defaults these too, and an empty From on a
        // live automation is a deliverability problem, not a cosmetic one.
        $settings[ $settings_key ] = array_merge( self::defaultSenderFields( $settings_key ), $email );

        if ( $from_playbook ) {
            $settings[ $settings_key ]['mint_ai_needs_review'] = true;
        }

        return $settings;
    }

    /**
     * Sender defaults pulled from the site's configured email settings.
     *
     * @param string $settings_key Which email shape to build defaults for.
     * @return array
     */
    private static function defaultSenderFields( string $settings_key ): array {
        $email_settings = get_option( '_mrm_email_settings', [] );
        $from_email     = is_array( $email_settings ) ? ( $email_settings['from_email'] ?? '' ) : '';
        $from_name      = is_array( $email_settings ) ? ( $email_settings['from_name'] ?? '' ) : '';
        $reply_email    = is_array( $email_settings ) ? ( $email_settings['reply_email'] ?? '' ) : '';
        $reply_name     = is_array( $email_settings ) ? ( $email_settings['reply_name'] ?? '' ) : '';

        if ( 'notification_email' === $settings_key ) {
            return [
                'from_email'   => $from_email,
                'from_name'    => $from_name,
                'preview_text' => '',
                'recipients'   => [],
                'body'         => '',
                'json_body'    => '',
            ];
        }

        return [
            'sender_email'       => $from_email,
            'sender_name'        => $from_name,
            'reply_email'        => $reply_email,
            'reply_name'         => $reply_name,
            'email_preview_text' => '',
            'body'               => '',
            'json_body'          => '',
            'make_transactional' => false,
        ];
    }

    /**
     * Removes email markup from a step for listing purposes.
     *
     * Replaces the body with a short marker rather than dropping it silently —
     * "has_email_body: false" is the signal that a step needs composing, and an
     * absent key would be indistinguishable from an empty one.
     *
     * @param mixed $step One step from a formatted automation.
     * @return mixed
     */
    private static function stripStepBodies( $step ) {
        if ( ! is_array( $step ) ) {
            return $step;
        }

        unset( $step['body'], $step['email_body'] );

        foreach ( [ 'message_data', 'notification_email' ] as $email_key ) {
            if ( ! isset( $step['settings'][ $email_key ] ) || ! is_array( $step['settings'][ $email_key ] ) ) {
                continue;
            }
            $email = &$step['settings'][ $email_key ];
            $email['has_email_body'] = '' !== trim( (string) ( $email['body'] ?? '' ) );
            $email['is_editable_in_builder'] = ! empty( $email['json_body'] );
            unset( $email['body'], $email['json_body'] );
            unset( $email );
        }

        // Condition arms carry their own steps.
        foreach ( [ 'yes', 'no' ] as $arm ) {
            if ( isset( $step['node_data'][ $arm ] ) && is_array( $step['node_data'][ $arm ] ) ) {
                $step['node_data'][ $arm ] = array_map( [ self::class, 'stripStepBodies' ], $step['node_data'][ $arm ] );
            }
        }

        return $step;
    }

    /**
     * Whether a key is dispatched by a connector but absent from the builder.
     *
     * @param string $key Canonical trigger_name.
     * @return bool
     */
    private static function isRegisteredWithoutBuilderStep( string $key ): bool {
        foreach ( (array) Connector::get_instance()->get_triggers() as $triggers ) {
            foreach ( (array) $triggers as $t ) {
                $t = (array) $t;
                if ( self::triggerName( $t ) === $key || ( $t['key'] ?? '' ) === $key ) {
                    return true;
                }
            }
        }
        return false;
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
