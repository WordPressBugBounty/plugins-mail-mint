<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package Mint\MRM\API\Controllers
 */

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\API\Actions\FormActionCreator;
use MRM\Common\MrmCommon;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;


/**
 * [Manages Form Route ]
 *
 * @desc Manages form submission
 * @since 1.0.0
 */
class FormSubmissionController extends FrontendBaseController {

	/**
	 * Hnadle form submission request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response|WP_Error
	 * @since 1.0.0
	 */
	public function mrm_submit_form( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$params = filter_var_array( $params );
		$nonce  = $params['wp_nonce'];

		// In REST API context, WordPress doesn't authenticate users via cookies unless
		// the X-WP-Nonce header is present. For frontend form submissions, we manually
		// check the auth cookie to determine if the user is logged in before verifying the nonce.
		$logged_in = is_user_logged_in();
		if ( ! $logged_in ) {
			$user_id = wp_validate_auth_cookie( '', 'logged_in' );
			if ( $user_id ) {
				wp_set_current_user( $user_id );
				$logged_in = true;
			}
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'mailmint_unauthorized_submission',
				__( 'Seems like you are a bot! Try again later', 'mrm' )
			);
		}
		$action_creator = new FormActionCreator();
		$action         = $action_creator->makeAction();
		$response       = $action->handle_form_submission( $params );
		return new WP_REST_Response( $response );
	}

}
