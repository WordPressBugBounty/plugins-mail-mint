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

		// This nonce validation can't validate logged-in users.
		// It can only validate logged-out users.
		if ( !wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'mailmint_unauthorized_submission', __( 'Seems like you are a bot! Try again later', 'mrm' ) );
		}
		$action_creator = new FormActionCreator();
		$action         = $action_creator->makeAction();
		$response       = $action->handle_form_submission( $params );
		return new WP_REST_Response( $response );
	}

}
