<?php
/**
 * REST API Tag Controller
 *
 * Handles requests to the tags endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use WP_REST_Request;
use Mint\MRM\API\Actions\TagActionCreator;
use MRM\Common\MrmCommon;
use WP_REST_Response;

/**
 * This is the main class that controls the tags feature. Its responsibilities are:
 *
 * - Create or update a tag
 * - Delete single or multiple tags
 * - Retrieve single or multiple tags
 * - Assign or removes tags and lists from the contact
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class TagController extends AdminBaseController {

	/**
	 * Tag object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args;

	/**
	 * Get and send response to create a new tag
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {

		// Get values from the API request.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new TagActionCreator();
		$action   = $creator->makeAction();
		$response = $action->create_or_update( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Delete request for tags
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new TagActionCreator();
		$action   = $creator->makeAction();
		$response = $action->delete_single( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Delete multiple tags
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new TagActionCreator();
		$action   = $creator->makeAction();
		$response = $action->delete_all( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Get all tags request for tags
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {

		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$params   = filter_var_array( $params );
		$creator  = new TagActionCreator();
		$action   = $creator->makeAction();
		$response = $action->get_all( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Function used to return all tag to custom select dropdown
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_tags_for_dropdown() {
		$creator  = new TagActionCreator();
		$action   = $creator->makeAction();
		$response = $action->get_tags_for_dropdown();
		return new WP_REST_Response( $response );
	}
}
