<?php
/**
 * REST API General Setting Controller
 *
 * Handles requests to the general setting endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\API\Actions\ComplianceAction;
use MRM\Common\MrmCommon;
use WP_REST_Request;

/**
 * This is the main class that controls the general setting feature. Its responsibilities are:
 *
 * - Create or update general settings
 * - Retrieve general settings from options table
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class ComplianceSettingController extends SettingBaseController {

    use Singleton;

    /**
     * Update General global settings into wp_options table
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     * @return array|WP_REST_Response
     * @since 1.0.0
     */
    public function create_or_update( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        if ( is_array( $params ) && ! empty( $params ) ) {
            update_option( '_mint_compliance',$params);
            return $this->get_success_response( __( 'compliance settings have been successfully saved.', 'mrm' ) );
        }
        return $this->get_error_response( __( 'No changes have been made.', 'mrm' ) );
    }

    /**
     * Get General global settings from wp_option table
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     * @return array|WP_REST_Response
     * @since 1.0.0
     */
    public function get( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        $compliance_actions  = new ComplianceAction();
        $settings   = $compliance_actions->get_compliance();
        return $this->get_success_response_data( $settings );
    }
}
