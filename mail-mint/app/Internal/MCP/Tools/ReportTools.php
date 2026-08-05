<?php
/**
 * MCP Report Tools
 *
 * Provides reporting/observability tools:
 *   - get-site-analytics  — dashboard KPIs + deliverability over a period
 *   - list-email-history  — paginated, filterable broadcast email log
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\DataBase\Models\DashboardModel;
use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;

class ReportTools {

    private const FILTERS = [ 'last_7_days', 'last_30_days', 'last_60_days', 'last_90_days', 'custom', 'all' ];

    public static function definitions(): array {
        return [

            'mail-mint/get-site-analytics' => [
                'label'       => __( 'Get Site Analytics', 'mail-mint' ),
                'description' => 'Site-wide KPIs for a period with previous-period comparison: contact growth, unsubscribes, emails sent, open/click rates. Set include_deliverability=true for bounce/spam rates, best send time, and per-day sent/open/bounce chart series. For one campaign\'s numbers, use get-campaign-analytics instead.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'filter'     => [ 'type' => 'string', 'enum' => self::FILTERS, 'default' => 'last_30_days' ],
                        'start_date' => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Required when filter=custom.' ],
                        'end_date'   => [ 'type' => 'string', 'description' => 'YYYY-MM-DD. Required when filter=custom.' ],
                        'include_deliverability' => [ 'type' => 'boolean', 'default' => false ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getSiteAnalytics' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_view_dashboard' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/list-email-history' => [
                'label'       => __( 'List Email History', 'mail-mint' ),
                'description' => 'Browse the broadcast email log: every email Mail Mint sent or scheduled, filterable by contact, campaign, automation, status, type, and date range. The go-to tool for "did my email send?" and failed-send investigations.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id'    => [ 'type' => 'integer' ],
                        'email_address' => [ 'type' => 'string', 'description' => 'Filter by recipient address (exact match).' ],
                        'campaign_id'   => [ 'type' => 'integer' ],
                        'automation_id' => [ 'type' => 'integer' ],
                        'status'        => [ 'type' => 'string', 'enum' => [ 'scheduled', 'sending', 'sent', 'failed' ] ],
                        'type'          => [ 'type' => 'string', 'enum' => [ 'campaign', 'automation', 'regular' ], 'description' => 'regular = one-off/direct emails.' ],
                        'since'         => [ 'type' => 'string', 'description' => 'ISO date — only emails created at/after this.' ],
                        'until'         => [ 'type' => 'string', 'description' => 'ISO date — only emails created at/before this.' ],
                        'page'          => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page'      => [ 'type' => 'integer', 'default' => 20 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listEmailHistory' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
                'annotations'         => [ 'readonly' ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function getSiteAnalytics( array $params ): array|\WP_Error {
        $filter     = in_array( $params['filter'] ?? '', self::FILTERS, true ) ? $params['filter'] : 'last_30_days';
        $start_date = sanitize_text_field( $params['start_date'] ?? '' );
        $end_date   = sanitize_text_field( $params['end_date'] ?? '' );

        if ( 'custom' === $filter && ( '' === $start_date || '' === $end_date ) ) {
            return MCPHelper::error( 'missing_param', 'start_date and end_date are required when filter=custom.' );
        }

        $result = [
            'filter' => $filter,
            'kpis'   => DashboardModel::get_top_cards_data( $filter, $start_date, $end_date ),
        ];

        if ( ! empty( $params['include_deliverability'] ) ) {
            $eps                      = DashboardModel::get_eps_data( $filter, $start_date, $end_date, 'daily' );
            $result['deliverability'] = is_array( $eps ) ? ( $eps['deliverability'] ?? $eps ) : [];
        }

        return $result;
    }

    public static function listEmailHistory( array $params ): array|\WP_Error {
        global $wpdb;

        $paging = MCPHelper::paginationFromInput( $params );

        $broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
        $contacts_table  = $wpdb->prefix . 'mint_contacts';

        $where = [ '1=1' ];
        $args  = [];

        if ( ! empty( $params['contact_id'] ) ) {
            $where[] = 'b.contact_id = %d';
            $args[]  = (int) $params['contact_id'];
        }
        if ( ! empty( $params['email_address'] ) ) {
            $where[] = 'b.email_address = %s';
            $args[]  = sanitize_email( $params['email_address'] );
        }
        if ( ! empty( $params['campaign_id'] ) ) {
            $where[] = 'b.campaign_id = %d';
            $args[]  = (int) $params['campaign_id'];
        }
        if ( ! empty( $params['automation_id'] ) ) {
            $where[] = 'b.automation_id = %d';
            $args[]  = (int) $params['automation_id'];
        }
        if ( ! empty( $params['status'] ) && in_array( $params['status'], [ 'scheduled', 'sending', 'sent', 'failed' ], true ) ) {
            $where[] = 'b.status = %s';
            $args[]  = $params['status'];
        }
        if ( ! empty( $params['type'] ) && in_array( $params['type'], [ 'campaign', 'automation', 'regular' ], true ) ) {
            $where[] = 'b.email_type = %s';
            $args[]  = $params['type'];
        }
        if ( ! empty( $params['since'] ) ) {
            $since = gmdate( 'Y-m-d H:i:s', strtotime( (string) $params['since'] ) );
            $where[] = 'b.created_at >= %s';
            $args[]  = $since;
        }
        if ( ! empty( $params['until'] ) ) {
            $until = gmdate( 'Y-m-d H:i:s', strtotime( (string) $params['until'] ) );
            $where[] = 'b.created_at <= %s';
            $args[]  = $until;
        }

        $where_sql = implode( ' AND ', $where );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var(
            empty( $args )
                ? "SELECT COUNT(*) FROM {$broadcast_table} AS b WHERE {$where_sql}"
                : $wpdb->prepare( "SELECT COUNT(*) FROM {$broadcast_table} AS b WHERE {$where_sql}", $args )
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.id, b.campaign_id, b.automation_id, b.contact_id, b.email_address,
                        b.email_type, b.status, b.scheduled_at, b.created_at, b.updated_at,
                        c.first_name, c.last_name
                FROM {$broadcast_table} AS b
                LEFT JOIN {$contacts_table} AS c ON c.id = b.contact_id
                WHERE {$where_sql}
                ORDER BY b.created_at DESC
                LIMIT %d OFFSET %d",
                array_merge( $args, [ $paging['per_page'], $paging['offset'] ] )
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $emails = array_map(
            static function ( $row ) {
                return [
                    'id'            => (int) $row['id'],
                    'email_address' => $row['email_address'],
                    'contact_id'    => $row['contact_id'] ? (int) $row['contact_id'] : null,
                    'contact_name'  => trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) ),
                    'type'          => $row['email_type'],
                    'status'        => $row['status'],
                    'campaign_id'   => $row['campaign_id'] ? (int) $row['campaign_id'] : null,
                    'automation_id' => $row['automation_id'] ? (int) $row['automation_id'] : null,
                    'scheduled_at'  => $row['scheduled_at'],
                    'created_at'    => $row['created_at'],
                ];
            },
            is_array( $rows ) ? $rows : []
        );

        return [
            'emails'      => $emails,
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => (int) ceil( $total / $paging['per_page'] ),
        ];
    }
}
