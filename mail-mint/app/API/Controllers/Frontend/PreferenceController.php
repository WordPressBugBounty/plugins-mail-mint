<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package Mint\MRM\API\Controllers
 */

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\API\Actions\PreferenceActionCreator;
use MRM\Common\MrmCommon;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;



/**
 * [Manages Frontend Route ]
 *
 * @desc Manages preference update
 * @since 1.0.0
 */
class PreferenceController extends FrontendBaseController {

	/**
	 * Preference update from email
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function mrm_preference_update_by_user( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$params = filter_var_array( $params );

		// In REST API context cookies aren't applied automatically; check manually.
		$logged_in = is_user_logged_in();
		if ( ! $logged_in ) {
			$user_id = wp_validate_auth_cookie( '', 'logged_in' );
			if ( $user_id ) {
				wp_set_current_user( $user_id );
				$logged_in = true;
			}
		}

		// Logged-in requests must carry a valid nonce to prevent CSRF.
		// Non-logged-in requests are authorised by the contact_hash token in the payload.
		if ( $logged_in && ! wp_verify_nonce( $params['wp_nonce'] ?? '', 'wp_rest' ) ) {
			return new WP_Error(
				'mailmint_unauthorized_request',
				__( 'Nonce verification failed.', 'mrm' )
			);
		}

		$action_creator = new PreferenceActionCreator();
		$action         = $action_creator->makeAction();
		$response       = $action->update_preference( $params );
		return new WP_REST_Response( $response );
	}
}
