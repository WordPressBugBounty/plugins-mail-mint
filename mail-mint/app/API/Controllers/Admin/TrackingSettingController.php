<?php
/**
 * REST API Tracking Setting Controller
 *
 * Handles GET and POST requests to the tracking settings endpoint.
 * Reads and writes the mail-mint_allow_tracking / linno_telemetry_allow_tracking
 * options via the Linno SDK's set_optin_state() method, which is invoked through
 * the mailmint_tracking_consent_changed action registered in mail-mint.php.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;

/**
 * Controls the usage-tracking consent setting.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class TrackingSettingController extends SettingBaseController {

	use Singleton;

	/**
	 * Option key written by the Linno SDK for this plugin.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'mail-mint_allow_tracking';

	/**
	 * Return the current tracking consent state.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get( WP_REST_Request $request ) {
		$raw_value     = get_option( self::OPTION_KEY, 'no' );
		$allow_tracking = ( 'yes' === $raw_value ) ? 'yes' : 'no';

		return $this->get_success_response_data(
			array( 'allow_tracking' => $allow_tracking )
		);
	}

	/**
	 * Update the tracking consent state.
	 *
	 * Fires the mailmint_tracking_consent_changed action so that the Linno SDK
	 * client registered in mail-mint.php can call set_optin_state() and write
	 * to both linno_telemetry_allow_tracking and mail-mint_allow_tracking.
	 *
	 * @param WP_REST_Request $request Request object with allow_tracking ('yes'|'no').
	 * @return \WP_REST_Response
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params         = $request->get_json_params();
		$raw_value      = isset( $params['allow_tracking'] ) ? $params['allow_tracking'] : '';
		$allow_tracking = ( 'yes' === $raw_value ) ? 'yes' : 'no';

		do_action( 'mailmint_tracking_consent_changed', $allow_tracking );

		return $this->get_success_response(
			__( 'Usage tracking setting has been saved.', 'mrm' )
		);
	}
}
