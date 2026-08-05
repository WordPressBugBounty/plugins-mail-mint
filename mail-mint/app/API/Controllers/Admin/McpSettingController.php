<?php
/**
 * REST API MCP Setting Controller
 *
 * GET  /mrm/v1/settings/mcp  — returns current MCP status and endpoint info.
 * POST /mrm/v1/settings/mcp  — enables or disables the MCP server.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

defined( 'ABSPATH' ) || exit;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Internal\MCP\MCPInit;
use Mint\MRM\Internal\MCP\AbilitiesRegistrar;
use MRM\Common\MrmCommon;
use WP_REST_Request;

class McpSettingController extends SettingBaseController {

    use Singleton;

    /**
     * GET /mrm/v1/settings/mcp
     *
     * Returns:
     *   mcp_enabled      — 'yes' | 'no'
     *   endpoint_url     — full REST URL of the MCP server
     *   tools_count      — number of registered free tools
     *   adapter_available — whether wp_register_ability exists
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get( WP_REST_Request $request ) {
        $enabled          = get_option( '_mrm_mcp_enabled', 'yes' );
        $adapter_available = function_exists( 'wp_register_ability' );

        $data = [
            'mcp_enabled'       => $enabled,
            'endpoint_url'      => MCPInit::getEndpointUrl(),
            'tools_count'       => MCPInit::getToolCount(),
            'adapter_available' => $adapter_available,
        ];

        return $this->get_success_response_data( $data );
    }

    /**
     * POST /mrm/v1/settings/mcp
     *
     * Accepts: { mcp_enabled: 'yes' | 'no' }
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function create_or_update( WP_REST_Request $request ) {
        $params  = MrmCommon::get_api_params_values( $request );
        $enabled = isset( $params['mcp_enabled'] ) ? $params['mcp_enabled'] : null;

        if ( ! in_array( $enabled, [ 'yes', 'no' ], true ) ) {
            return $this->get_error_response( __( 'mcp_enabled must be "yes" or "no".', 'mail-mint' ) );
        }

        update_option( '_mrm_mcp_enabled', $enabled );

        return $this->get_success_response(
            'yes' === $enabled
                ? __( 'MCP server has been enabled.', 'mail-mint' )
                : __( 'MCP server has been disabled.', 'mail-mint' )
        );
    }
}
