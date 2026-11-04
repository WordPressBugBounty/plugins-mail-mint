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
use MintMail\App\Internal\Automation\HelperFunctions;
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
			do_action( 'mailmint_automation_log_overall_analytics', $params );
			if ( isset( $log['data'] ) ) {
				return $this->get_success_response( __( 'Query Successfulls', 'mrm' ), 200, $log['data'] );
			}
			return $this->get_error_response( 'Failed to Get Data', 400 );
		}
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}

	/**
	 * Stop one contact's run of an automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function stop_run( WP_REST_Request $request ) {
		$params        = MrmCommon::get_api_params_values( $request );
		$automation_id = isset( $params['id'] ) ? (int) $params['id'] : 0;
		$identifier    = isset( $params['identifier'] ) ? sanitize_text_field( $params['identifier'] ) : '';

		if ( ! $automation_id || '' === $identifier ) {
			return $this->get_error_response( __( 'Automation ID and run identifier are required.', 'mrm' ), 400 );
		}

		$result = HelperFunctions::stop_automation_run( $automation_id, $identifier );
		if ( 0 === $result['cancelled_actions'] && 0 === $result['exited_steps'] ) {
			return $this->get_error_response( __( 'This run has nothing left to stop.', 'mrm' ), 404 );
		}

		return $this->get_success_response( __( 'The contact has been stopped in this automation.', 'mrm' ), 200, $result );
	}

	/**
	 * Resume one contact's failed or stopped run from its first unfinished step
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response|\WP_Error
	 * @since 1.32.0
	 */
	public function resume_run( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$result = HelperFunctions::resume_automation_run(
			isset( $params['id'] ) ? (int) $params['id'] : 0,
			isset( $params['identifier'] ) ? sanitize_text_field( $params['identifier'] ) : ''
		);
		if ( is_wp_error( $result ) ) {
			return $this->get_error_response( $result->get_error_message(), 400 );
		}
		return $this->get_success_response( __( 'The run has been resumed.', 'mrm' ), 200, $result );
	}

	/**
	 * Start a fresh run for the contact of an earlier run
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response|\WP_Error
	 * @since 1.32.0
	 */
	public function restart_run( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$result = HelperFunctions::restart_automation_run(
			isset( $params['id'] ) ? (int) $params['id'] : 0,
			isset( $params['identifier'] ) ? sanitize_text_field( $params['identifier'] ) : ''
		);
		if ( is_wp_error( $result ) ) {
			return $this->get_error_response( $result->get_error_message(), 400 );
		}
		return $this->get_success_response( __( 'The contact has been restarted in this automation.', 'mrm' ), 200, $result );
	}

	/**
	 * Start a test run of an automation for an existing contact or a new email
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response|\WP_Error
	 * @since 1.32.0
	 */
	public function test_run( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$result = HelperFunctions::start_test_run(
			isset( $params['id'] ) ? (int) $params['id'] : 0,
			isset( $params['email'] ) ? $params['email'] : '',
			! empty( $params['skip_delays'] )
		);
		if ( is_wp_error( $result ) ) {
			return $this->get_error_response( $result->get_error_message(), 400 );
		}
		return $this->get_success_response( __( 'Test started. The emails will arrive in a moment.', 'mrm' ), 200, $result );
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
