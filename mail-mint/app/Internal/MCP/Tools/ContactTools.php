<?php
/**
 * MCP Contact Tools
 *
 * Provides 8 contact-related tools:
 *   - list-contacts, get-contact (read)
 *   - upsert-contact, bulk-upsert-contacts, delete-contact,
 *     apply-segments-to-contacts, add-contact-note, delete-contact-note (write)
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\MRM\DataBase\Models\NoteModel;
use Mint\MRM\DataStores\ContactData;
use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;

class ContactTools {

    // -----------------------------------------------------------------------
    // Tool definitions
    // -----------------------------------------------------------------------

    public static function definitions(): array {
        return [

            'mail-mint/list-contacts' => [
                'label'       => __( 'List Contacts', 'mail-mint' ),
                'description' => 'Filter and paginate contacts. Supports search, tag/list filtering, status, contact_type, date ranges, engagement filters (no_opens_in_days / opened_in_days / no_clicks_in_days / clicked_in_days — based on Mail Mint email opens and clicks), sorting, and inline custom fields.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'               => [ 'type' => 'string', 'description' => 'Search by name or email.' ],
                        'tags'                 => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Filter by tag IDs.' ],
                        'lists'                => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Filter by list IDs.' ],
                        'statuses'             => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'e.g. ["subscribed","pending"]' ],
                        'contact_type'         => [ 'type' => 'string', 'enum' => [ 'lead', 'customer' ] ],
                        'created_after'        => [ 'type' => 'string', 'description' => 'ISO 8601 date.' ],
                        'created_before'       => [ 'type' => 'string', 'description' => 'ISO 8601 date.' ],
                        'no_opens_in_days'     => [ 'type' => 'integer', 'description' => 'Engagement filter: only contacts who have NOT opened any Mail Mint email in the last N days ("inactive"/"lapsed"). Note: contacts never sent an email also match — combine with statuses or created_before to refine.' ],
                        'no_clicks_in_days'    => [ 'type' => 'integer', 'description' => 'Engagement filter: only contacts who have NOT clicked any Mail Mint email in the last N days.' ],
                        'opened_in_days'       => [ 'type' => 'integer', 'description' => 'Engagement filter: only contacts who HAVE opened a Mail Mint email in the last N days (engaged/active).' ],
                        'clicked_in_days'      => [ 'type' => 'integer', 'description' => 'Engagement filter: only contacts who HAVE clicked a Mail Mint email in the last N days.' ],
                        'sort_by'              => [ 'type' => 'string', 'default' => 'id' ],
                        'sort_type'            => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
                        'page'                 => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page'             => [ 'type' => 'integer', 'default' => 10 ],
                        'include_custom_fields'=> [ 'type' => 'boolean', 'default' => false ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listContacts' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/get-contact' => [
                'label'       => __( 'Get Contact', 'mail-mint' ),
                'description' => 'Get full contact profile by contact_id OR email. Includes notes, tags, lists, and optionally custom_fields.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id'           => [ 'type' => 'integer' ],
                        'email'                => [ 'type' => 'string', 'format' => 'email' ],
                        'include_custom_fields'=> [ 'type' => 'boolean', 'default' => true ],
                        'include_notes'        => [ 'type' => 'boolean', 'default' => true ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getContact' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/upsert-contact' => [
                'label'       => __( 'Upsert Contact', 'mail-mint' ),
                'description' => 'Create or update a contact. Resolves an existing contact by contact_id or email. Supports add_tags, remove_tags, add_lists, remove_lists, custom_fields, and (on update) new_email to rename. Idempotent: a concurrent duplicate create resolves to an update.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'email' ],
                    'properties' => [
                        'contact_id'     => [ 'type' => 'integer' ],
                        'email'          => [ 'type' => 'string', 'format' => 'email', 'description' => 'Used to look up the contact (and to set the email when creating).' ],
                        'new_email'      => [ 'type' => 'string', 'format' => 'email', 'description' => 'When updating, change the contact\'s email to this address. Rejected if it collides with another contact.' ],
                        'first_name'     => [ 'type' => 'string' ],
                        'last_name'      => [ 'type' => 'string' ],
                        'status'         => [ 'type' => 'string', 'enum' => [ 'pending', 'subscribed', 'unsubscribed', 'complained', 'bounced', 'inactive', 'transactional' ] ],
                        'contact_type'   => [ 'type' => 'string', 'enum' => [ 'lead', 'customer' ] ],
                        'add_tags'       => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Tag IDs to add.' ],
                        'remove_tags'    => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        'add_lists'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        'remove_lists'   => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        'custom_fields'  => [ 'type' => 'object', 'description' => 'Key-value pairs of custom field slug → value.' ],
                        'if_exists'      => [ 'type' => 'string', 'enum' => [ 'merge', 'skip', 'error' ], 'default' => 'merge', 'description' => 'Upsert is inherently idempotent: it resolves an existing contact by email, so retries update rather than duplicate.' ],
                        'double_optin'   => [ 'type' => 'boolean', 'default' => false ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'upsertContact' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_contacts' ),
                'annotations'         => [],
            ],

            'mail-mint/bulk-upsert-contacts' => [
                'label'       => __( 'Bulk Upsert Contacts', 'mail-mint' ),
                'description' => 'Upsert up to 500 contacts in a single call. Returns per-row results: created, updated, skipped, invalid.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'contacts' ],
                    'properties' => [
                        'contacts' => [
                            'type'      => 'array',
                            'maxItems'  => 500,
                            'items'     => [
                                'type'       => 'object',
                                'required'   => [ 'email' ],
                                'properties' => [
                                    'email'         => [ 'type' => 'string' ],
                                    'first_name'    => [ 'type' => 'string' ],
                                    'last_name'     => [ 'type' => 'string' ],
                                    'status'        => [ 'type' => 'string' ],
                                    'add_tags'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                                    'add_lists'     => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                                    'custom_fields' => [ 'type' => 'object' ],
                                ],
                            ],
                        ],
                        'if_exists' => [ 'type' => 'string', 'enum' => [ 'merge', 'skip', 'error' ], 'default' => 'merge' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'bulkUpsertContacts' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_contacts' ),
                'annotations'         => [ 'bulk' ],
            ],

            'mail-mint/delete-contact' => [
                'label'       => __( 'Delete Contact', 'mail-mint' ),
                'description' => 'Hard-delete a contact by contact_id. Optionally also delete associated activity logs.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'contact_id' ],
                    'properties' => [
                        'contact_id'      => [ 'type' => 'integer' ],
                        'delete_activity' => [ 'type' => 'boolean', 'default' => false ],
                        'confirm'         => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this destructive action. Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'deleteContact' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_contacts_delete' ),
                'annotations'         => [ 'destructive' ],
            ],

            'mail-mint/apply-segments-to-contacts' => [
                'label'       => __( 'Apply Segments to Contacts', 'mail-mint' ),
                'description' => 'Add or remove tags/lists across many contacts. Accepts contact_ids (array, max 5000) or a filter object. When the filter supplies both tags and lists they are intersected (contact must match a tag AND a list). The filter path is capped at 5000 contacts per call; the response includes matched_total and a truncated flag so you can detect and page over larger sets. Supports dry_run to preview counts.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => 5000 ],
                        'filter'      => [
                            'type'       => 'object',
                            'properties' => [
                                'tags'     => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                                'lists'    => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                                'statuses' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                            ],
                        ],
                        'add_tags'    => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        'remove_tags' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        'add_lists'   => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        'remove_lists'=> [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        'dry_run'     => [ 'type' => 'boolean', 'default' => false ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'applySegmentsToContacts' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_contacts' ),
                'annotations'         => [ 'bulk' ],
            ],

            'mail-mint/add-contact-note' => [
                'label'       => __( 'Add Contact Note', 'mail-mint' ),
                'description' => 'Add a note, call log, email record, or meeting entry to a contact.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'contact_id', 'description' ],
                    'properties' => [
                        'contact_id'  => [ 'type' => 'integer' ],
                        'type'        => [ 'type' => 'string', 'enum' => [ 'note', 'call', 'email', 'meeting' ], 'default' => 'note' ],
                        'description' => [ 'type' => 'string', 'description' => 'HTML or plain text.' ],
                        'created_by'  => [ 'type' => 'integer', 'description' => 'WP user ID. Defaults to current user.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'addContactNote' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_contacts' ),
                'annotations'         => [],
            ],

            'mail-mint/delete-contact-note' => [
                'label'       => __( 'Delete Contact Note', 'mail-mint' ),
                'description' => 'Delete a single contact note by note_id.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'note_id' ],
                    'properties' => [
                        'note_id' => [ 'type' => 'integer' ],
                        'confirm' => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this destructive action. Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'deleteContactNote' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_contacts' ),
                'annotations'         => [ 'destructive' ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function listContacts( array $params ): array|\WP_Error {
        global $wpdb;

        $paging         = MCPHelper::paginationFromInput( $params );
        $search         = sanitize_text_field( $params['search'] ?? '' );
        $tags           = array_values( array_filter( array_map( 'intval', (array) ( $params['tags'] ?? [] ) ) ) );
        $lists          = array_values( array_filter( array_map( 'intval', (array) ( $params['lists'] ?? [] ) ) ) );
        $statuses       = ! empty( $params['statuses'] ) ? array_map( 'sanitize_text_field', (array) $params['statuses'] ) : [];
        $contact_type   = sanitize_text_field( $params['contact_type'] ?? '' );
        $created_after  = sanitize_text_field( $params['created_after'] ?? '' );
        $created_before = sanitize_text_field( $params['created_before'] ?? '' );
        $sort_by        = sanitize_key( $params['sort_by'] ?? 'id' );
        $sort_dir       = strtoupper( $params['sort_type'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $include_cf     = ! empty( $params['include_custom_fields'] );

        // Engagement filters (Hybrid boundary: opens/clicks over Mail Mint's own
        // send log only; advanced rule-based segmentation stays in Pro).
        $no_opens_days  = isset( $params['no_opens_in_days'] )  ? max( 0, (int) $params['no_opens_in_days'] )  : 0;
        $no_clicks_days = isset( $params['no_clicks_in_days'] ) ? max( 0, (int) $params['no_clicks_in_days'] ) : 0;
        $opened_days    = isset( $params['opened_in_days'] )    ? max( 0, (int) $params['opened_in_days'] )    : 0;
        $clicked_days   = isset( $params['clicked_in_days'] )   ? max( 0, (int) $params['clicked_in_days'] )   : 0;

        // Build ALL filters into one query so totals and pagination are DB-accurate.
        // (Previously contact_type/date/sort were applied per-page in PHP, producing
        // wrong totals and ordering.)
        $contact_table = $wpdb->prefix . 'mint_contacts';
        $pivot_table   = $wpdb->prefix . 'mint_contact_group_relationship';

        $joins   = '';
        $where   = [ '1=1' ];
        $prepare = [];

        // tags/lists narrow via the group pivot (membership in ANY of the supplied group ids).
        $group_ids = array_values( array_unique( array_merge( $tags, $lists ) ) );
        if ( ! empty( $group_ids ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $group_ids ), '%d' ) );
            $joins       .= " INNER JOIN {$pivot_table} gp ON gp.contact_id = c.id AND gp.group_id IN ({$placeholders}) ";
            $prepare      = array_merge( $prepare, $group_ids );
        }

        if ( ! empty( $statuses ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
            $where[]      = "c.status IN ({$placeholders})";
            $prepare      = array_merge( $prepare, $statuses );
        }

        if ( '' !== $contact_type ) {
            $where[]   = 'c.stage = %s';
            $prepare[] = $contact_type;
        }

        if ( '' !== $search ) {
            $like      = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]   = "( c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR CONCAT(c.first_name, ' ', c.last_name) LIKE %s )";
            $prepare[] = $like;
            $prepare[] = $like;
            $prepare[] = $like;
            $prepare[] = $like;
        }

        $after_ts = $created_after ? strtotime( $created_after ) : false;
        if ( $after_ts ) {
            $where[]   = 'c.created_at >= %s';
            $prepare[] = gmdate( 'Y-m-d H:i:s', $after_ts );
        }
        $before_ts = $created_before ? strtotime( $created_before ) : false;
        if ( $before_ts ) {
            $where[]   = 'c.created_at <= %s';
            $prepare[] = gmdate( 'Y-m-d H:i:s', $before_ts );
        }

        // Engagement (open/click) filters via a correlated EXISTS on the broadcast
        // log. EXISTS (rather than a JOIN) keeps COUNT(DISTINCT c.id) accurate.
        // Event time matches BroadcastRepository's last_opened definition:
        // COALESCE(meta.updated_at, meta.created_at).
        $broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
        $bmeta_table     = $wpdb->prefix . 'mint_broadcast_email_meta';
        foreach ( [
            [ $no_opens_days,  false, 'is_open' ],
            [ $opened_days,    true,  'is_open' ],
            [ $no_clicks_days, false, 'is_click' ],
            [ $clicked_days,   true,  'is_click' ],
        ] as $engagement ) {
            list( $days, $must_exist, $meta_key ) = $engagement;
            if ( $days > 0 ) {
                $where[]   = self::engagementClause( $must_exist, $meta_key, $broadcast_table, $bmeta_table );
                $prepare[] = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
            }
        }

        $where_sql = implode( ' AND ', $where );

        // Whitelist sort column to avoid injection via sort_by.
        $allowed_sort = [ 'id', 'email', 'first_name', 'last_name', 'created_at', 'updated_at', 'status', 'stage' ];
        $order_col    = in_array( $sort_by, $allowed_sort, true ) ? $sort_by : 'id';

        // Count (DISTINCT because the pivot join can fan out).
        $count_sql = "SELECT COUNT(DISTINCT c.id) FROM {$contact_table} c {$joins} WHERE {$where_sql}";
        $total     = (int) ( empty( $prepare )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, $prepare ) ) );

        // Page of rows. Tags/lists are intentionally NOT joined per-row here (that would be
        // N+1 across the page); use get-contact for a contact's full tag/list membership.
        $rows_sql     = "SELECT DISTINCT c.* FROM {$contact_table} c {$joins} WHERE {$where_sql} ORDER BY c.{$order_col} {$sort_dir} LIMIT %d OFFSET %d";
        $rows_prepare = array_merge( $prepare, [ $paging['per_page'], $paging['offset'] ] );
        $data         = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_prepare ), ARRAY_A );
        $data         = is_array( $data ) ? $data : [];

        $result = [
            'data'         => $data,
            'total'        => $total,
            'current_page' => $paging['page'],
            'per_page'     => $paging['per_page'],
            'last_page'    => (int) ceil( max( 1, $total ) / $paging['per_page'] ),
        ];

        return MCPHelper::formatContactList( $result, $include_cf );
    }

    /**
     * Build a correlated EXISTS / NOT EXISTS clause matching contacts by their
     * Mail Mint email engagement. The only dynamic value is the cutoff (bound
     * via %s by the caller); $meta_key is a hardcoded literal and the table
     * names come from $wpdb->prefix, so the interpolation is safe.
     *
     * @param bool   $must_exist      true → contact HAS the event in-window; false → NOT.
     * @param string $meta_key        'is_open' or 'is_click'.
     * @param string $broadcast_table Broadcast emails table (prefixed).
     * @param string $bmeta_table     Broadcast email meta table (prefixed).
     * @return string SQL fragment with one %s placeholder for the cutoff datetime.
     */
    private static function engagementClause( bool $must_exist, string $meta_key, string $broadcast_table, string $bmeta_table ): string {
        $inner = "SELECT 1 FROM {$broadcast_table} be_e"
            . " INNER JOIN {$bmeta_table} bm_e ON bm_e.mint_email_id = be_e.id"
            . " AND bm_e.meta_key = '{$meta_key}' AND bm_e.meta_value = '1'"
            . " WHERE be_e.contact_id = c.id"
            . " AND COALESCE(bm_e.updated_at, bm_e.created_at) >= %s";

        return ( $must_exist ? 'EXISTS' : 'NOT EXISTS' ) . " ( {$inner} )";
    }

    public static function getContact( array $params ): array|\WP_Error {
        $contact_id = (int) ( $params['contact_id'] ?? 0 );
        $email      = sanitize_email( $params['email'] ?? '' );

        if ( ! $contact_id && ! $email ) {
            return MCPHelper::error( 'missing_param', 'Provide contact_id or email.' );
        }

        if ( ! $contact_id && $email ) {
            $contact_id = (int) ContactModel::get_id_by_email( $email );
        }

        if ( ! $contact_id ) {
            return MCPHelper::error( 'not_found', 'Contact not found.' );
        }

        $contact = ContactModel::get( $contact_id );
        if ( ! $contact ) {
            return MCPHelper::error( 'not_found', 'Contact not found.' );
        }

        $contact = is_object( $contact ) ? (array) $contact : $contact;

        // get_tags_to_contact / get_lists_to_contact expect the full contact array
        // and return the same array augmented with 'tags'/'lists' keys.
        $contact_with_tags  = ContactGroupModel::get_tags_to_contact( $contact );
        $contact_with_lists = ContactGroupModel::get_lists_to_contact( $contact_with_tags );
        $contact            = $contact_with_lists;

        $options = [
            'include_notes'         => $params['include_notes'] ?? true,
            'include_custom_fields' => $params['include_custom_fields'] ?? true,
        ];

        return MCPHelper::formatContactForMCP( $contact, $options );
    }

    public static function upsertContact( array $params ): array|\WP_Error {
        $email = sanitize_email( $params['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            return MCPHelper::error( 'invalid_email', 'A valid email address is required.' );
        }

        $contact_id = (int) ( $params['contact_id'] ?? 0 );
        if ( ! $contact_id ) {
            $contact_id = (int) ContactModel::get_id_by_email( $email );
        }

        $if_exists = $params['if_exists'] ?? 'merge';

        if ( $contact_id ) {
            if ( 'error' === $if_exists ) {
                return MCPHelper::error( 'already_exists', 'Contact with this email already exists.' );
            }
            if ( 'skip' === $if_exists ) {
                return [ 'result' => 'skipped', 'contact_id' => $contact_id ];
            }

            // Optional email rename: only if new_email is valid and not already taken.
            $email_to_set = $email;
            if ( ! empty( $params['new_email'] ) ) {
                $new_email = sanitize_email( $params['new_email'] );
                if ( ! is_email( $new_email ) ) {
                    return MCPHelper::error( 'invalid_email', 'new_email is not a valid email address.' );
                }
                if ( strtolower( $new_email ) !== strtolower( $email ) ) {
                    $collision = (int) ContactModel::get_id_by_email( $new_email );
                    if ( $collision && $collision !== $contact_id ) {
                        return MCPHelper::error( 'email_taken', 'Another contact already uses new_email.' );
                    }
                    $email_to_set = $new_email;
                }
            }

            // Merge: update the contact
            $update_data = array_filter( [
                'email'      => $email_to_set,
                'first_name' => isset( $params['first_name'] ) ? sanitize_text_field( $params['first_name'] ) : null,
                'last_name'  => isset( $params['last_name'] ) ? sanitize_text_field( $params['last_name'] ) : null,
                'status'     => $params['status'] ?? null,
                'stage'      => $params['contact_type'] ?? null,
            ], fn( $v ) => $v !== null );

            ContactModel::update( $update_data, $contact_id );

            self::applyGroupChanges( $contact_id, $params );
            self::saveCustomFields( $contact_id, $params['custom_fields'] ?? [] );

            do_action( 'mailmint_contact_updated_by_mcp', $contact_id, $params );

            return [ 'result' => 'updated', 'contact_id' => $contact_id ];
        }

        // Create new contact
        $contact_data = new ContactData( $email, [
            'first_name' => sanitize_text_field( $params['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $params['last_name'] ?? '' ),
            'status'     => $params['status'] ?? 'subscribed',
            'stage'      => $params['contact_type'] ?? 'lead',
            'source'     => 'AI Assistant',
        ] );

        $new_id = (int) ContactModel::insert( $contact_data );
        if ( ! $new_id ) {
            // insert() returns 0 (not false) when a unique-email constraint rejects the row,
            // e.g. a concurrent create between our get_id_by_email() check and the insert.
            // Re-resolve and treat it as an update so the call is idempotent under races.
            $existing_id = (int) ContactModel::get_id_by_email( $email );
            if ( $existing_id ) {
                self::applyGroupChanges( $existing_id, $params );
                self::saveCustomFields( $existing_id, $params['custom_fields'] ?? [] );
                return [ 'result' => 'updated', 'contact_id' => $existing_id ];
            }
            return MCPHelper::error( 'insert_failed', 'Failed to create contact.' );
        }

        self::applyGroupChanges( $new_id, $params );
        self::saveCustomFields( $new_id, $params['custom_fields'] ?? [] );

        if ( ! empty( $params['double_optin'] ) ) {
            do_action( 'mailmint_send_double_optin_email', $new_id );
        }

        do_action( 'mailmint_contacts_saved', $new_id, $params );

        return [ 'result' => 'created', 'contact_id' => $new_id ];
    }

    public static function bulkUpsertContacts( array $params ): array|\WP_Error {
        $contacts  = $params['contacts'] ?? [];
        $if_exists = $params['if_exists'] ?? 'merge';

        if ( empty( $contacts ) || ! is_array( $contacts ) ) {
            return MCPHelper::error( 'missing_contacts', 'contacts array is required.' );
        }

        if ( count( $contacts ) > 500 ) {
            return MCPHelper::error( 'too_many', 'Maximum 500 contacts per bulk call.' );
        }

        $summary = [ 'created' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0 ];
        $rows    = [];

        foreach ( $contacts as $i => $c ) {
            $row_params = array_merge( (array) $c, [ 'if_exists' => $if_exists ] );
            $result     = self::upsertContact( $row_params );

            if ( is_wp_error( $result ) ) {
                $summary['invalid']++;
                $rows[] = [ 'index' => $i, 'email' => $c['email'] ?? '', 'error' => $result->get_error_message() ];
            } else {
                // Defensive: only increment known summary buckets so an unexpected
                // result value can never throw an undefined-index.
                $bucket = $result['result'] ?? '';
                if ( isset( $summary[ $bucket ] ) ) {
                    $summary[ $bucket ]++;
                }
                $rows[] = [ 'index' => $i, 'email' => $c['email'] ?? '', 'result' => $bucket, 'contact_id' => $result['contact_id'] ?? 0 ];
            }
        }

        return array_merge( $summary, [ 'rows' => $rows ] );
    }

    public static function deleteContact( array $params ): array|\WP_Error {
        $contact_id      = (int) ( $params['contact_id'] ?? 0 );
        $delete_activity = ! empty( $params['delete_activity'] );

        if ( ! $contact_id ) {
            return MCPHelper::error( 'missing_param', 'contact_id is required.' );
        }

        $existing = ContactModel::get( $contact_id );
        if ( ! $existing ) {
            return MCPHelper::error( 'not_found', 'Contact not found.' );
        }

        $contact_arr   = is_object( $existing ) ? (array) $existing : $existing;
        $contact_email = $contact_arr['email'] ?? '';

        do_action( 'mailmint_before_delete_contact', $contact_id );

        if ( $delete_activity ) {
            global $wpdb;
            // Broadcast/send history is keyed by contact_id (schema keeps it on delete by
            // default; an explicit delete_activity request opts to remove it).
            $wpdb->delete( $wpdb->prefix . 'mint_broadcast_emails', [ 'contact_id' => $contact_id ], [ '%d' ] );
            // Automation progress is tracked by EMAIL, not contact_id (mint_automation_log
            // has no contact_id column; mint_automation_jobs is automation-level, not per
            // contact, so it must NOT be touched here).
            if ( $contact_email ) {
                $wpdb->delete( $wpdb->prefix . 'mint_automation_log', [ 'email' => $contact_email ], [ '%s' ] );
            }
        }

        // ContactModel::destroy() already removes contact_meta, contact_note, and the
        // group pivot rows, so we don't duplicate those deletes here.
        ContactModel::destroy( $contact_id );
        do_action( 'mailmint_after_delete_contact', $contact_id );

        return [ 'result' => 'deleted', 'contact_id' => $contact_id ];
    }

    public static function applySegmentsToContacts( array $params ): array|\WP_Error {
        $validation = MCPHelper::validateUniversalFilter( $params );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        $add_tags    = array_map( 'intval', (array) ( $params['add_tags'] ?? [] ) );
        $remove_tags = array_map( 'intval', (array) ( $params['remove_tags'] ?? [] ) );
        $add_lists   = array_map( 'intval', (array) ( $params['add_lists'] ?? [] ) );
        $remove_lists= array_map( 'intval', (array) ( $params['remove_lists'] ?? [] ) );
        $dry_run     = ! empty( $params['dry_run'] );

        $contact_ids   = [];
        $filter_cap    = 5000;
        $matched_total = null; // total matched by the filter, before the cap.

        if ( ! empty( $params['contact_ids'] ) ) {
            $contact_ids = array_map( 'intval', (array) $params['contact_ids'] );
        } elseif ( ! empty( $params['filter'] ) ) {
            $filter   = $params['filter'];
            $statuses = (array) ( $filter['statuses'] ?? [] );          // must be array, not string
            $f_tags   = array_map( 'intval', (array) ( $filter['tags'] ?? [] ) );
            $f_lists  = array_map( 'intval', (array) ( $filter['lists'] ?? [] ) );

            $response = ContactModel::get_filtered_contacts(
                $statuses,
                $f_tags,
                $f_lists,
                $filter_cap,
                0,
                ''
            );
            // get_filtered_contacts returns ['data' => [...], 'count' => N, 'total_pages' => N].
            // NOTE: when both tags and lists are supplied the model intersects them (AND),
            // not unions (OR).
            $matched_total = is_array( $response ) ? (int) ( $response['count'] ?? 0 ) : 0;
            $contact_ids   = wp_list_pluck( (array) ( $response['data'] ?? [] ), 'id' );
        }

        $contact_ids = array_values( array_unique( array_filter( array_map( 'intval', $contact_ids ) ) ) );

        // Surface a truncation warning instead of silently applying to only the first $filter_cap.
        $truncated = ( null !== $matched_total && $matched_total > count( $contact_ids ) );

        if ( $dry_run ) {
            return [
                'dry_run'       => true,
                'contact_count' => count( $contact_ids ),
                'matched_total' => $matched_total ?? count( $contact_ids ),
                'truncated'     => $truncated,
                'cap'           => $filter_cap,
                'add_tags'      => $add_tags,
                'remove_tags'   => $remove_tags,
                'add_lists'     => $add_lists,
                'remove_lists'  => $remove_lists,
            ];
        }

        // set_tags/lists_to_multiple_contacts expects each group as ['id' => N]
        if ( ! empty( $add_tags ) ) {
            $tags_for_model = array_map( fn( $id ) => [ 'id' => $id ], $add_tags );
            ContactGroupModel::set_tags_to_multiple_contacts( $tags_for_model, $contact_ids );
        }
        if ( ! empty( $add_lists ) ) {
            $lists_for_model = array_map( fn( $id ) => [ 'id' => $id ], $add_lists );
            ContactGroupModel::set_lists_to_multiple_contacts( $lists_for_model, $contact_ids );
        }
        // remove_groups_from_contacts expects [['id' => N], ...] and an array of contact IDs
        if ( ! empty( $remove_tags ) ) {
            $items = array_map( fn( $id ) => [ 'id' => $id ], $remove_tags );
            ContactGroupPivotModel::remove_groups_from_contacts( $items, $contact_ids );
        }
        if ( ! empty( $remove_lists ) ) {
            $items = array_map( fn( $id ) => [ 'id' => $id ], $remove_lists );
            ContactGroupPivotModel::remove_groups_from_contacts( $items, $contact_ids );
        }

        return [
            'result'        => 'applied',
            'contact_count' => count( $contact_ids ),
            'matched_total' => $matched_total ?? count( $contact_ids ),
            'truncated'     => $truncated,
            'cap'           => $filter_cap,
        ];
    }

    public static function addContactNote( array $params ): array|\WP_Error {
        $contact_id  = (int) ( $params['contact_id'] ?? 0 );
        $description = wp_kses_post( $params['description'] ?? '' );

        if ( ! $contact_id ) {
            return MCPHelper::error( 'missing_param', 'contact_id is required.' );
        }
        if ( ! $description ) {
            return MCPHelper::error( 'missing_param', 'description is required.' );
        }

        $note = [
            'type'        => sanitize_text_field( $params['type'] ?? 'note' ),
            'description' => $description,
            'created_by'  => (int) ( $params['created_by'] ?? get_current_user_id() ),
        ];

        // NoteModel::insert() calls $wpdb->insert() and returns rows-affected (1) or false.
        // Capture insert_id immediately after — before any other query can overwrite it.
        global $wpdb;
        $inserted = NoteModel::insert( $note, $contact_id );
        $note_id  = (int) $wpdb->insert_id;

        if ( ! $inserted || ! $note_id ) {
            return MCPHelper::error( 'insert_failed', 'Failed to add note.' );
        }

        return [ 'result' => 'created', 'note_id' => $note_id ];
    }

    public static function deleteContactNote( array $params ): array|\WP_Error {
        $note_id = (int) ( $params['note_id'] ?? 0 );
        if ( ! $note_id ) {
            return MCPHelper::error( 'missing_param', 'note_id is required.' );
        }

        global $wpdb;
        $note_table = $wpdb->prefix . 'mint_contact_note';
        $exists     = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$note_table} WHERE id = %d LIMIT 1", $note_id ) );
        if ( ! $exists ) {
            return MCPHelper::error( 'not_found', 'Note not found.' );
        }

        NoteModel::destroy( $note_id );

        return [ 'result' => 'deleted', 'note_id' => $note_id ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private static function applyGroupChanges( int $contact_id, array $params ): void {
        $add_tags     = array_filter( array_map( 'intval', (array) ( $params['add_tags'] ?? [] ) ) );
        $remove_tags  = array_filter( array_map( 'intval', (array) ( $params['remove_tags'] ?? [] ) ) );
        $add_lists    = array_filter( array_map( 'intval', (array) ( $params['add_lists'] ?? [] ) ) );
        $remove_lists = array_filter( array_map( 'intval', (array) ( $params['remove_lists'] ?? [] ) ) );

        if ( $add_tags ) {
            ContactGroupModel::set_tags_to_contact( $add_tags, $contact_id );
        }
        if ( $add_lists ) {
            ContactGroupModel::set_lists_to_contact( $add_lists, $contact_id );
        }
        if ( $remove_tags ) {
            $items = array_map( fn( $id ) => [ 'id' => $id ], $remove_tags );
            ContactGroupPivotModel::remove_groups_from_contacts( $items, [ $contact_id ] );
        }
        if ( $remove_lists ) {
            $items = array_map( fn( $id ) => [ 'id' => $id ], $remove_lists );
            ContactGroupPivotModel::remove_groups_from_contacts( $items, [ $contact_id ] );
        }
    }

    private static function saveCustomFields( int $contact_id, $custom_fields ): void {
        if ( empty( $custom_fields ) || ! is_array( $custom_fields ) ) {
            return;
        }
        // update_meta_fields expects ['meta_fields' => ['slug' => value, ...]]
        $meta = [];
        foreach ( $custom_fields as $slug => $value ) {
            $meta[ sanitize_key( $slug ) ] = $value;
        }
        ContactModel::update_meta_fields( $contact_id, [ 'meta_fields' => $meta ] );
    }
}
