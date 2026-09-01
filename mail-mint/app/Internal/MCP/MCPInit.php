<?php
/**
 * MCPInit — bootstraps the Mail Mint MCP surface.
 *
 * Responsibilities:
 *   - Register the "mail-mint" ability category.
 *   - Register all free abilities via AbilitiesRegistrar.
 *   - Create the custom MCP server at POST /wp-json/mrm/mcp.
 *   - Register cache-invalidation hooks for get-crm-context.
 *
 * @package Mint\MRM\Internal\MCP
 */

namespace Mint\MRM\Internal\MCP;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\MCP\Tools\ContextTools;
use Mint\MRM\Internal\MCP\Observability\MintMcpObservabilityHandler;

class MCPInit {

    /**
     * Wire up all action/filter hooks.
     */
    public function init(): void {
        add_action( 'wp_abilities_api_categories_init', [ $this, 'registerCategory' ] );
        add_action( 'wp_abilities_api_init',            [ $this, 'registerAbilities' ] );
        add_action( 'mcp_adapter_init',                 [ $this, 'registerCustomServer' ] );

        // Cache invalidation for get-crm-context
        $invalidate = [ ContextTools::class, 'invalidateCache' ];
        foreach ( [
            'mailmint_tag_created', 'mailmint_tag_updated', 'mailmint_tag_deleted',
            'mailmint_list_created', 'mailmint_list_updated', 'mailmint_list_deleted',
            'mailmint_custom_field_added', 'mailmint_custom_field_updated', 'mailmint_custom_field_deleted',
        ] as $hook ) {
            add_action( $hook, $invalidate );
        }
    }

    /**
     * Register the "mail-mint" ability category.
     */
    public function registerCategory(): void {
        wp_register_ability_category( 'mail-mint', [
            'label'       => __( 'Mail Mint', 'mail-mint' ),
            'description' => __( 'Contact, campaign, and automation abilities for Mail Mint.', 'mail-mint' ),
        ] );
    }

    /**
     * Register all free abilities and fire the extension hook.
     */
    public function registerAbilities(): void {
        AbilitiesRegistrar::register();

        // Pro plugin hooks here to register its own tools
        do_action( 'mail_mint/mcp_loaded' );
    }

    /**
     * Create the custom MCP server via the adapter.
     *
     * @param \WP\MCP\Adapter $adapter
     */
    public function registerCustomServer( $adapter ): void {
        $ability_names = apply_filters(
            'mail_mint/mcp_ability_names',
            array_keys( AbilitiesRegistrar::getDefinitions() )
        );

        $namespace = apply_filters( 'mail_mint/mcp_server_namespace', 'mrm' );
        $route     = apply_filters( 'mail_mint/mcp_server_route', 'mcp' );

        // Default audit logging: the Null handler records nothing, so for a surface that can
        // delete contacts and send email we log tool traffic by default. See the handler for
        // why the adapter's own error-log sink is not used. Override via the filter.
        $observability = apply_filters(
            'mail_mint/mcp_observability_handler',
            MintMcpObservabilityHandler::class
        );

        $adapter->create_server(
            'mail-mint',
            $namespace,
            $route,
            __( 'Mail Mint MCP Server', 'mail-mint' ),
            __( 'AI agent tools for Mail Mint contacts, campaigns, and automations.', 'mail-mint' ),
            MRM_VERSION,
            [ '\WP\MCP\Transport\HttpTransport' ],
            '\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
            $observability,
            array_values( array_unique( array_filter( (array) $ability_names ) ) ),
            [], // resources
            [], // prompts
            [ $this, 'checkTransportPermission' ]
        );
    }

    /**
     * Transport-level auth gate.
     *
     * The adapter default only requires the 'read' capability (any logged-in subscriber).
     * Since per-tool permission_callbacks already enforce fine-grained caps, this adds a
     * defense-in-depth floor: the requester must at least be able to view the Mail Mint
     * dashboard. Per-tool callbacks still reject anything beyond that. Filterable so a site
     * can tighten or loosen it.
     *
     * @param \WP_REST_Request $request The incoming MCP request.
     * @return bool
     */
    public function checkTransportPermission( $request ): bool {
        $capability = apply_filters( 'mail_mint/mcp_transport_capability', 'mint_view_dashboard', $request );
        $allowed    = current_user_can( $capability );

        /**
         * Filter the final transport-level allow decision for the Mail Mint MCP endpoint.
         *
         * @param bool             $allowed Whether the request may reach the MCP server.
         * @param \WP_REST_Request $request The incoming request.
         */
        return (bool) apply_filters( 'mail_mint/mcp_transport_allowed', $allowed, $request );
    }

    /**
     * Return the full URL of the MCP REST endpoint.
     *
     * @return string
     */
    public static function getEndpointUrl(): string {
        $namespace = apply_filters( 'mail_mint/mcp_server_namespace', 'mrm' );
        $route     = apply_filters( 'mail_mint/mcp_server_route', 'mcp' );
        return get_rest_url( null, trailingslashit( $namespace ) . $route );
    }

    /**
     * Return the total number of registered free tools.
     *
     * @return int
     */
    public static function getToolCount(): int {
        $names = apply_filters(
            'mail_mint/mcp_ability_names',
            array_keys( AbilitiesRegistrar::getDefinitions() )
        );
        return count( array_values( array_unique( array_filter( (array) $names ) ) ) );
    }
}
