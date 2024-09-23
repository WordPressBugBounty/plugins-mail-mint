<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package Mint\MRM\API\Controllers
 */

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\API\Actions\CookieActionCreator;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Manages callback controllers for CookieRoute
 *
 * @since 1.0.0
 */
class CookieController extends FrontendBaseController {

	/**
	 * Set Cookies for Individual form.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 */
	public function set_mint_mail_cookie_for_form( WP_REST_Request $request ) {
		$params         = MrmCommon::get_api_params_values( $request );
		$params         = filter_var_array( $params );
		$action_creator = new CookieActionCreator();
		$action         = $action_creator->makeAction();
		$response       = $action->set_cookie_for_form( $params );
		return new WP_REST_Response( $response );
	}

}
