<?php
/**
 * MCP Template Tools
 *
 * Provides email-template discovery tools:
 *   - list-email-templates (read)  — built-in catalog + user-saved templates
 *   - get-email-template   (read)  — metadata for one template
 *
 * Template content (builder JSON) is intentionally NOT returned: trees are
 * huge and AI callers compose email content via compose-campaign-email,
 * which generates valid builder JSON itself. These tools exist for
 * discovery — "what designs exist" — and for referencing templates in
 * conversation with the user.
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\Admin\EmailTemplates\DefaultEmailTemplates;
use Mint\MRM\Internal\MCP\Helpers\EmailComposer;
use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;

class TemplateTools {

    public static function definitions(): array {
        return [

            'mail-mint/list-email-templates' => [
                'label'       => __( 'List Email Templates', 'mail-mint' ),
                'description' => 'List available email template designs: the built-in catalog (source=default) and user-saved templates (source=saved). Returns metadata only. Also returns the compose-campaign-email composer capabilities (style presets and section types).',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'source'   => [ 'type' => 'string', 'enum' => [ 'all', 'default', 'saved' ], 'default' => 'all' ],
                        'search'   => [ 'type' => 'string', 'description' => 'Substring match on title.' ],
                        'page'     => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page' => [ 'type' => 'integer', 'default' => 20 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listEmailTemplates' ],
                'permission_callback' => function () {
                    return PermissionManager::current_user_can( 'mint_read_campaigns' )()
                        || PermissionManager::current_user_can( 'mint_manage_email_templates' )();
                },
                'annotations' => [ 'readonly' ],
            ],

            'mail-mint/get-email-template' => [
                'label'       => __( 'Get Email Template', 'mail-mint' ),
                'description' => 'Get metadata for one email template by source (default or saved) and ID.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'source', 'template_id' ],
                    'properties' => [
                        'source'      => [ 'type' => 'string', 'enum' => [ 'default', 'saved' ] ],
                        'template_id' => [ 'type' => 'integer' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getEmailTemplate' ],
                'permission_callback' => function () {
                    return PermissionManager::current_user_can( 'mint_read_campaigns' )()
                        || PermissionManager::current_user_can( 'mint_manage_email_templates' )();
                },
                'annotations' => [ 'readonly' ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function listEmailTemplates( array $params ): array|\WP_Error {
        $paging = MCPHelper::paginationFromInput( $params );
        $source = sanitize_key( $params['source'] ?? 'all' );
        $search = strtolower( sanitize_text_field( $params['search'] ?? '' ) );

        $templates = [];

        if ( 'saved' !== $source ) {
            foreach ( self::defaultCatalog() as $template ) {
                $templates[] = $template;
            }
        }
        if ( 'default' !== $source ) {
            foreach ( self::savedTemplates() as $template ) {
                $templates[] = $template;
            }
        }

        if ( '' !== $search ) {
            $templates = array_values( array_filter(
                $templates,
                static function ( $t ) use ( $search ) {
                    return false !== strpos( strtolower( $t['title'] ), $search );
                }
            ) );
        }

        $total = count( $templates );
        $page  = array_slice( $templates, $paging['offset'], $paging['per_page'] );

        return [
            'templates'   => $page,
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => (int) ceil( $total / $paging['per_page'] ),
            'composer'    => EmailComposer::capabilities(),
            'note'        => 'Templates are design references. To write email content into a campaign, use compose-campaign-email (it generates builder JSON itself); importing a template design happens in the visual editor.',
        ];
    }

    public static function getEmailTemplate( array $params ): array|\WP_Error {
        $source      = sanitize_key( $params['source'] ?? '' );
        $template_id = (int) ( $params['template_id'] ?? 0 );

        if ( ! $template_id || ! in_array( $source, [ 'default', 'saved' ], true ) ) {
            return MCPHelper::error( 'missing_param', 'source (default|saved) and template_id are required.' );
        }

        if ( 'default' === $source ) {
            foreach ( self::defaultCatalog() as $template ) {
                if ( $template['id'] === $template_id ) {
                    return $template;
                }
            }
            return MCPHelper::error( 'not_found', 'Default template not found.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'mint_email_templates';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, title, editor_type, email_type, status, customizable, created_at, updated_at,
                        (json_content IS NOT NULL AND json_content != '') AS has_json_content
                FROM {$table} WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );
        if ( ! $row ) {
            return MCPHelper::error( 'not_found', 'Saved template not found.' );
        }

        return [
            'source'           => 'saved',
            'id'               => (int) $row['id'],
            'title'            => $row['title'],
            'editor_type'      => $row['editor_type'],
            'email_type'       => $row['email_type'],
            'status'           => $row['status'],
            'customizable'     => (bool) $row['customizable'],
            'has_json_content' => (bool) $row['has_json_content'],
            'created_at'       => $row['created_at'],
            'updated_at'       => $row['updated_at'],
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Metadata-only view of the built-in template catalog.
     */
    private static function defaultCatalog(): array {
        $catalog = [];
        foreach ( (array) DefaultEmailTemplates::get_default_templates() as $template ) {
            if ( ! is_array( $template ) || ! isset( $template['id'] ) ) {
                continue;
            }
            $catalog[] = [
                'source'     => 'default',
                'id'         => (int) $template['id'],
                'title'      => (string) ( $template['title'] ?? '' ),
                'categories' => array_values( (array) ( $template['emailCategories'] ?? [] ) ),
                'industry'   => array_values( (array) ( $template['industry'] ?? [] ) ),
                'is_pro'     => ! empty( $template['is_pro'] ),
            ];
        }
        return $catalog;
    }

    /**
     * Metadata-only view of user-saved templates from mint_email_templates.
     */
    private static function savedTemplates(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'mint_email_templates';

        // Table is created on install; guard for very old installs anyway.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT id, title, editor_type, email_type, status, created_at FROM {$table} ORDER BY id DESC LIMIT 500",
            ARRAY_A
        );

        return array_map(
            static function ( $row ) {
                return [
                    'source'      => 'saved',
                    'id'          => (int) $row['id'],
                    'title'       => $row['title'],
                    'editor_type' => $row['editor_type'],
                    'email_type'  => $row['email_type'],
                    'status'      => $row['status'],
                    'created_at'  => $row['created_at'],
                ];
            },
            is_array( $rows ) ? $rows : []
        );
    }
}
