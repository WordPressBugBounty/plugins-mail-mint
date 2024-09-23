<?php
/**
 * REST API List Controller
 *
 * Handles requests to the lists endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\API\Actions\ListActionCreator;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use WP_REST_Request;
use MRM\Common\MrmCommon;
use WP_REST_Response;

/**
 * This is the main class that controls the lists feature. Its responsibilities are:
 *
 * - Create or update a list
 * - Delete single or multiple lists
 * - Retrieve single or multiple lists
 * - Assign or removes lists from the contact
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class ListController extends AdminBaseController {

	/**
	 * Function used to handle create  or update requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_RESPONSE
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {

		// Get values from the API request.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new ListActionCreator();
		$action   = $creator->makeAction();
		$response = $action->create_or_update( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Function used to handle paginated get and search requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {

		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new ListActionCreator();
		$action   = $creator->makeAction();
		$response = $action->get_all( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Function used to handle a single get request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {
		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new ListActionCreator();
		$action   = $creator->makeAction();
		$response = $action->get_single( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Function used to handle delete requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new ListActionCreator();
		$action   = $creator->makeAction();
		$response = $action->delete_single( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Function used to handle delete requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new ListActionCreator();
		$action   = $creator->makeAction();
		$response = $action->delete_all( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Function used to return all list to custom select dropdown
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_lists_for_dropdown() {
		$creator  = new ListActionCreator();
		$action   = $creator->makeAction();
		$response = $action->get_lists_for_dropdown();
		return new WP_REST_Response( $response );
	}
}
