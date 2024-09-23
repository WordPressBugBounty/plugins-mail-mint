<?php
/**
 * REST API Automation Controller
 *
 * Handles requests to the Automation endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use MintMail\App\Internal\Automation\AutomationLogModel;
use MRM\Common\MrmCommon;

/**
 * This is the main class that controls the automation feature. Its responsibilities are:
 *
 * - Create or update automation
 * - Delete single or multiple Automation
 * - Retrieve single or multiple automations
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class AutomationLogController extends AdminBaseController {

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
	 * Function use to get single automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		if ( !empty( $params['id'] ) ) {
			$log = AutomationLogModel::get_single( $params['id'] );
			if ( isset( $log['data'] ) ) {
				return $this->get_success_response( __( 'Query Successfulls', 'mrm' ), 200, $log['data'] );
			}
			return $this->get_error_response( 'Failed to Get Data', 400 );
		}
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}


	/**
	 * Function use to get single automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_automation_performance_analytics( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( !empty( $params['id'] ) ) {
			$filter     = isset( $params['filter'] ) ? $params['filter'] : 'weekly';
			$log_report = AutomationLogModel::get_automation_performance_analytics( $params['id'], $filter );
			if ( isset( $log_report['data'] ) ) {
				return $this->get_success_response( __( 'Query Successfulls', 'mrm' ), 200, $log_report['data'] );
			}
			return $this->get_error_response( 'Failed to Get Data', 400 );
		}
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}

	/**
	 * Function use to get single automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_automation_overall_analytics( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( !empty( $params['id'] ) ) {
			$filter     = isset( $params['filter'] ) ? $params['filter'] : 'weekly';
			$log_report = AutomationLogModel::get_automation_overall_analytics( $params['id'], $filter );
			if ( isset( $log_report['data'] ) ) {
				return $this->get_success_response( __( 'Query Successfulls', 'mrm' ), 200, $log_report['data'] );
			}
			return $this->get_error_response( 'Failed to Get Data', 400 );
		}
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}


	/**
	 * Delete a single object
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return void
	 */
	public function delete_single( WP_REST_Request $request ) {
	}


	/**
	 * Delete all or multiple objects
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return void
	 */
	public function delete_all( WP_REST_Request $request ) {
	}



	/**
	 * Get all or multipla objects
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return void
	 */
	public function get_all( WP_REST_Request $request ) {
	}


	/**
	 * Create or update an object
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return void
	 */
	public function create_or_update( WP_REST_Request $request ) {
	}

}
