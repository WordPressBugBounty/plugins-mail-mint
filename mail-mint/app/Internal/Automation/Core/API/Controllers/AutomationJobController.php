<?php
/**
 * REST API Automation Job Controller
 *
 * Handles requests to the Automation endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\EmailModel;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use Exception;
use MRM\Common\MrmCommon;

/**
 * This is the main class that controls the automation job feature. Its responsibilities are:
 *
 * - Create or update automation
 * - Delete single or multiple Automation
 * - Retrieve single or multiple automations
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class AutomationJobController extends AdminBaseController {

	use Singleton;


	/**
	 * Automation object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args = array();


	/**
	 * Automation array from API response
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $automation_data;


	/**
	 * Get and send response to create or update a automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		try {
			if ( $this->automation_data ) {
				$data['automations'] = $this->automation_data;
				return $this->get_success_response( __( 'Automation Steps has been saved successfully', 'mrm' ), 201, $data );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Failed to save automation step', 'mrm' ), 400 );
		}
	}


	/**
	 * Request for deleting a single automation by ID
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		return $this->get_error_response( __( 'Failed to Delete', 'mrm' ), 400 );
	}


	/**
	 * Request for deleting multiple automations by ID
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Get all automations
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function use to get single automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}

}
