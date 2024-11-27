<?php
/**
 * Handles advanced settings for the MRM plugin.
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-11-25 11:03:17
 * @modify date 2024-11-25 11:03:17
 * @package /app/API/Actions/Admin
 */

namespace Mint\MRM\Admin\API\Controllers;

use MRM\Common\MrmCommon;
use WP_REST_Request;

/**
 * Class AdvancedSettingController
 *
 * Handles advanced settings for the MRM plugin.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since   1.15.5
 */
class AdvancedSettingController extends SettingBaseController {

	/**
	 * Option key for storing advanced settings.
	 *
	 * @var string
	 * @since 1.15.5
	 */
	private $option_key = '_mint_advanced_settings';

	/**
	 * Create or update advanced settings.
	 *
	 * @param WP_REST_Request $request The request object containing the parameters.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 * 
	 * @since 1.15.5
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

        if ( is_array( $params ) && ! empty( $params ) ) {
            if ( update_option( $this->option_key, $params ) ) {
				return $this->get_success_response( __( 'Advanced settings have been successfully saved.', 'mrm' ) );
			}
			return $this->get_error_response( __( 'No changes have been made.', 'mrm' ) );
        }
	}

	/**
	 * Retrieve advanced settings.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The response object containing the settings data.
	 * 
	 * @since 1.15.5
	 */
	public function get( WP_REST_Request $request ) {
		$default  = MrmCommon::default_advanced_settings();
		$settings = get_option( $this->option_key, $default );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : $default;

		return $this->get_success_response_data( $settings );
	}

	/**
	 * Delete transients and trigger an action after clearing.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The response object indicating success.
	 * 
	 * @since 1.15.5
	 */
	public function delete_transients( WP_REST_Request $request ) {

		/**
		 * Trigger an action after clearing transients.
		 *
		 * This action allows other functions to hook into the process after transients have been cleared.
		 *
		 * @since 1.15.5
		 */
		do_action('mailmint_after_clear_transient');
		return $this->get_success_response( __( 'Transients have been successfully cleared.', 'mrm' ) );
	}
}
