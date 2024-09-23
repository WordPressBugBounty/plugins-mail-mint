<?php
/**
 * REST API Automation Step Controller
 *
 * Handles requests to the Automation Step endpoint.
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
 * This is the main class that controls the automation step feature. Its responsibilities are:
 *
 * - Create or update automation steps
 * - Delete single or multiple automationsteps
 * - Retrieve single or multiple automation steps
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class AutomationStepController extends AdminBaseController {

	use Singleton;


	/**
	 * AutomationStep object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args = array();


	/**
	 * AutomationStep array from API response
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $step_data;


	/**
	 * Get and send response to create or update a automation step
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		try {
			if ( $this->step_data ) {
				$data['automation_steps'] = $this->step_data;
				return $this->get_success_response( __( 'Automation Steps has been saved successfully', 'mrm' ), 201, $data );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Failed to save automation step', 'mrm' ), 400 );
		}
	}


	/**
	 * Request for deleting a single step by step ID
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
	 * Request for deleting multiple steps by step ID
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
	 * Get all steps by automation ID
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function use to get single step
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {
		// Get values from REST API JSON.
		$params = MrmCommon::get_api_params_values( $request );
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}

}
