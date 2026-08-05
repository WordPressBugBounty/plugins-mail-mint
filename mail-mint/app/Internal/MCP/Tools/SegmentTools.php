<?php
/**
 * MCP Segment Tools
 *
 * Provides tag and list CRUD tools:
 *   - manage-tag (create/update/delete/merge)
 *   - manage-list (create/update/delete/merge)
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;
use Mint\MRM\Database\Repositories\ContactGroupRepository;

class SegmentTools {

    public static function definitions(): array {
        return [

            'mail-mint/manage-tag' => [
                'label'       => __( 'Manage Tag', 'mail-mint' ),
                'description' => 'Create, update, or delete a tag. Action: create, update, delete.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'action' ],
                    'properties' => [
                        'action'  => [ 'type' => 'string', 'enum' => [ 'create', 'update', 'delete' ] ],
                        'tag_id'  => [ 'type' => 'integer', 'description' => 'Required for update and delete.' ],
                        'title'   => [ 'type' => 'string', 'description' => 'Required for create; optional for update.' ],
                        'confirm' => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this destructive action (delete). Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'manageTag' ],
                'permission_callback' => function () {
                    return PermissionManager::current_user_can( 'mint_manage_contact_cats' )()
                        || PermissionManager::current_user_can( 'mint_manage_contact_cats_delete' )();
                },
                'annotations' => [ 'destructive' ],
            ],

            'mail-mint/list-tags' => [
                'label'       => __( 'List Tags', 'mail-mint' ),
                'description' => 'Browse all tags with contact counts, paginated. Use this when get-crm-context\'s top 20 is not enough and you do not know exact names (otherwise resolve-segments is cheaper).',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'   => [ 'type' => 'string', 'description' => 'Substring match on title.' ],
                        'sort_by'  => [ 'type' => 'string', 'enum' => [ 'title', 'id', 'created_at', 'contact_count' ], 'default' => 'title' ],
                        'sort_type'=> [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'ASC' ],
                        'page'     => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page' => [ 'type' => 'integer', 'default' => 50 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listTags' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/list-lists' => [
                'label'       => __( 'List Lists', 'mail-mint' ),
                'description' => 'Browse all contact lists with contact counts, paginated. Use this when get-crm-context\'s top 20 is not enough and you do not know exact names (otherwise resolve-segments is cheaper).',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'   => [ 'type' => 'string', 'description' => 'Substring match on title.' ],
                        'sort_by'  => [ 'type' => 'string', 'enum' => [ 'title', 'id', 'created_at', 'contact_count' ], 'default' => 'title' ],
                        'sort_type'=> [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'ASC' ],
                        'page'     => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page' => [ 'type' => 'integer', 'default' => 50 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listLists' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/resolve-segments' => [
                'label'       => __( 'Resolve Segments', 'mail-mint' ),
                'description' => 'Resolve tag and/or list names to their IDs. Use this when get-crm-context did not include a tag/list you need (it only returns the top 20). Matches are case-insensitive and exact by title; unmatched names are returned under "unresolved".',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'tag_names'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                        'list_names' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'resolveSegments' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/manage-list' => [
                'label'       => __( 'Manage List', 'mail-mint' ),
                'description' => 'Create, update, or delete a contact list. Action: create, update, delete.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'action' ],
                    'properties' => [
                        'action'  => [ 'type' => 'string', 'enum' => [ 'create', 'update', 'delete' ] ],
                        'list_id' => [ 'type' => 'integer', 'description' => 'Required for update and delete.' ],
                        'title'   => [ 'type' => 'string', 'description' => 'Required for create; optional for update.' ],
                        'confirm' => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this destructive action (delete). Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'manageList' ],
                'permission_callback' => function () {
                    return PermissionManager::current_user_can( 'mint_manage_contact_cats' )()
                        || PermissionManager::current_user_can( 'mint_manage_contact_cats_delete' )();
                },
                'annotations' => [ 'destructive' ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function manageTag( array $params ): array|\WP_Error {
        return self::manageGroup( 'tags', $params, 'tag_id' );
    }

    public static function manageList( array $params ): array|\WP_Error {
        return self::manageGroup( 'lists', $params, 'list_id' );
    }

    public static function listTags( array $params ): array|\WP_Error {
        return self::listGroups( 'tags', $params );
    }

    public static function listLists( array $params ): array|\WP_Error {
        return self::listGroups( 'lists', $params );
    }

    /**
     * Paginated browse of contact groups (tags or lists) with member counts.
     */
    private static function listGroups( string $type, array $params ): array {
        global $wpdb;

        $paging      = MCPHelper::paginationFromInput( $params );
        $search      = sanitize_text_field( $params['search'] ?? '' );
        $sort_by_in  = sanitize_key( $params['sort_by'] ?? 'title' );
        $sort        = strtoupper( $params['sort_type'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
        $sort_column = [
            'title'         => 'g.title',
            'id'            => 'g.id',
            'created_at'    => 'g.created_at',
            'contact_count' => 'contact_count',
        ][ $sort_by_in ] ?? 'g.title';

        $groups_table = $wpdb->prefix . 'mint_contact_groups';
        $pivot_table  = $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactGroupPivotSchema::$table_name;

        $where = 'g.type = %s';
        $args  = [ $type ];
        if ( '' !== $search ) {
            $where .= ' AND g.title LIKE %s';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$groups_table} AS g WHERE {$where}", $args )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT g.id, g.title, g.created_at, COUNT(p.contact_id) AS contact_count
                FROM {$groups_table} AS g
                LEFT JOIN {$pivot_table} AS p ON p.group_id = g.id
                WHERE {$where}
                GROUP BY g.id, g.title, g.created_at
                ORDER BY {$sort_column} {$sort}
                LIMIT %d OFFSET %d",
                array_merge( $args, [ $paging['per_page'], $paging['offset'] ] )
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return [
            $type         => array_map(
                static function ( $row ) {
                    return [
                        'id'            => (int) $row['id'],
                        'title'         => $row['title'],
                        'contact_count' => (int) $row['contact_count'],
                        'created_at'    => $row['created_at'],
                    ];
                },
                is_array( $rows ) ? $rows : []
            ),
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => (int) ceil( $total / $paging['per_page'] ),
        ];
    }

    public static function resolveSegments( array $params ): array|\WP_Error {
        $tag_names  = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $params['tag_names'] ?? [] ) ) ) );
        $list_names = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $params['list_names'] ?? [] ) ) ) );

        if ( empty( $tag_names ) && empty( $list_names ) ) {
            return MCPHelper::error( 'missing_param', 'Provide tag_names and/or list_names.' );
        }

        return [
            'tags'       => self::resolveNames( 'tags', $tag_names ),
            'lists'      => self::resolveNames( 'lists', $list_names ),
            'unresolved' => [
                'tags'  => self::unresolvedNames( 'tags', $tag_names ),
                'lists' => self::unresolvedNames( 'lists', $list_names ),
            ],
        ];
    }

    /**
     * Resolve a set of group titles (case-insensitive, exact) to [{id,title}] for a type.
     */
    private static function resolveNames( string $type, array $names ): array {
        if ( empty( $names ) ) {
            return [];
        }
        global $wpdb;
        $table        = $wpdb->prefix . 'mint_contact_groups';
        $placeholders = implode( ', ', array_fill( 0, count( $names ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title FROM {$table} WHERE type = %s AND LOWER(title) IN ({$placeholders})",
                array_merge( [ $type ], array_map( 'strtolower', $names ) )
            ),
            ARRAY_A
        );
        return array_map(
            static function ( $r ) {
                return [ 'id' => (int) $r['id'], 'title' => $r['title'] ];
            },
            is_array( $rows ) ? $rows : []
        );
    }

    /**
     * Return the supplied names that did NOT match any group title of the given type.
     */
    private static function unresolvedNames( string $type, array $names ): array {
        if ( empty( $names ) ) {
            return [];
        }
        $resolved = array_map(
            static function ( $r ) {
                return strtolower( $r['title'] );
            },
            self::resolveNames( $type, $names )
        );
        return array_values( array_filter(
            $names,
            static function ( $n ) use ( $resolved ) {
                return ! in_array( strtolower( $n ), $resolved, true );
            }
        ) );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    // Maps plural DB type → singular hook suffix (avoids fragile rtrim('s') pattern).
    private const TYPE_HOOK_SUFFIX = [
        'tags'  => 'tag',
        'lists' => 'list',
    ];

    private static function manageGroup( string $type, array $params, string $id_key ): array|\WP_Error {
        $action      = $params['action'] ?? '';
        $id          = (int) ( $params[ $id_key ] ?? 0 );
        $hook_suffix = self::TYPE_HOOK_SUFFIX[ $type ] ?? rtrim( $type, 's' );

        // Route through the repository so pivot cleanup, transactions, and cache
        // invalidation are handled consistently (no raw $wpdb in the tool layer).
        $repo = new ContactGroupRepository( $type );

        switch ( $action ) {
            case 'create':
                if ( ! PermissionManager::current_user_can( 'mint_manage_contact_cats' )() ) {
                    return MCPHelper::error( 'permission_denied', 'You do not have permission to create ' . $type . '.' );
                }
                $title = sanitize_text_field( $params['title'] ?? '' );
                if ( ! $title ) {
                    return MCPHelper::error( 'missing_param', 'title is required for create.' );
                }
                $new_id = (int) $repo->create( [ 'title' => $title ] );
                if ( ! $new_id ) {
                    return MCPHelper::error( 'insert_failed', 'Failed to create ' . $type . '.' );
                }
                do_action( 'mailmint_' . $hook_suffix . '_created', $new_id );
                return [ 'result' => 'created', $id_key => $new_id ];

            case 'update':
                if ( ! PermissionManager::current_user_can( 'mint_manage_contact_cats' )() ) {
                    return MCPHelper::error( 'permission_denied', 'You do not have permission to update ' . $type . '.' );
                }
                if ( ! $id ) {
                    return MCPHelper::error( 'missing_param', $id_key . ' is required for update.' );
                }
                if ( ! $repo->find( $id ) ) {
                    return MCPHelper::error( 'not_found', ucfirst( $hook_suffix ) . ' not found.' );
                }
                $title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
                if ( '' === $title ) {
                    return MCPHelper::error( 'missing_param', 'title is required for update.' );
                }
                $repo->update( $id, [ 'title' => $title ] );
                do_action( 'mailmint_' . $hook_suffix . '_updated', $id );
                return [ 'result' => 'updated', $id_key => $id ];

            case 'delete':
                if ( ! PermissionManager::current_user_can( 'mint_manage_contact_cats_delete' )() ) {
                    return MCPHelper::error( 'permission_denied', 'You do not have permission to delete ' . $type . '.' );
                }
                if ( ! $id ) {
                    return MCPHelper::error( 'missing_param', $id_key . ' is required for delete.' );
                }
                if ( ! $repo->find( $id ) ) {
                    return MCPHelper::error( 'not_found', ucfirst( $hook_suffix ) . ' not found.' );
                }
                // destroy() removes pivot memberships in a transaction.
                $repo->destroy( $id );
                do_action( 'mailmint_' . $hook_suffix . '_deleted', $id );
                return [ 'result' => 'deleted', $id_key => $id ];

            default:
                return MCPHelper::error( 'invalid_action', 'Unknown action: ' . $action );
        }
    }
}
