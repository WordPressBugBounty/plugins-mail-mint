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
		$params         = MrmCommon::get_api_params_values( $request );
		$params         = filter_var_array( $params );
		$action_creator = new PreferenceActionCreator();
		$action         = $action_creator->makeAction();
		$response       = $action->update_preference( $params );
		return new WP_REST_Response( $response );
	}
}
