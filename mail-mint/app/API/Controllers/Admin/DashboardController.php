<?php
/**
 * REST API Dashboard Controller
 *
 * Handles requests to the dashboard endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\DashboardModel;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use MRM\Common\MrmCommon;

/**
 * This is the main class that controls the dashboard feature. Its responsibilities are:
 *
 * - Get full dashboard data stats
 * - Get single data
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class DashboardController {

	use Singleton;

	/**
	 * Dashboard object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args;
	
	
	/**
	 * Get revenue data from campaign and automation
	 * 
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 * 
	 *  @since 1.18.0
	 */
	public function get_dashboard_stats( WP_REST_Request $request ) {
		// Get query params from the API.
		$params = MrmCommon::get_api_params_values( $request );
		$filter = isset( $params[ 'filter' ] ) ? $params[ 'filter' ] : 'all';

		// Prepare the response data.
		$response = [
			'success' => true,
			'data'    => [
				'stats' => DashboardModel::get_top_cards_data($filter),
			]
		];

		return rest_ensure_response( $response );
	}


    /**
     * Get dashboard performance data.
	 * 
	 *  Description:
     *  - This method retrieves the performance data for campaigns, automations, and onboarding.
	 *  - It checks if there is an active SMTP plugin.
	 *  - The response includes performance data and SMTP warning status.
     *
     * @param WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     * @since 1.18.0
     */
	public function get_dashboard_performance() {
	    $campaigns   = DashboardModel::get_recent_campaign_performance();
		$automations = DashboardModel::get_recent_automation_performance();
		$onboarding  = DashboardModel::get_onboarding_stats();

		$response = [
			'success' => true,
			'data'    => [
				'campaigns'    => $campaigns,
				'automations'  => $automations,
				'onboarding'   => $onboarding,
				'smtp_warning' => MrmCommon::find_active_smtp_plugin(),
			]
		];

		return rest_ensure_response($response);
    }

	/**
	 * Get dashboard metrics.
	 *
	 *  Description:
	 *  - This method retrieves the metrics for the dashboard based on the provided parameters.
	 *  - It allows filtering by metric type (e.g., emails, subscribers) and date range.
	 *
	 * @param WP_REST_Request $request The request object containing the parameters.
	 * 
	 * @return \WP_Error|\WP_REST_Response The response containing the metrics data.
	 *
	 * @since 1.18.0
	*/
	public function get_dashboard_metrics( WP_REST_Request $request ) {
		// Get query params from the API.
		$params     = MrmCommon::get_api_params_values( $request );
		$metric     = isset( $params['metric'] ) ? $params['metric'] : 'emails';
		$start_date = isset( $params['start_date'] ) ? $params['start_date'] : '';
		$end_date   = isset( $params['end_date'] ) ? $params['end_date'] : '';

		// Prepare the response data.
		$response = [
			'success' => true,
			'data'    => [
				$metric => DashboardModel::prepare_dashboard_metrics( $metric, $start_date, $end_date ),
			]
		];

		return rest_ensure_response( $response );
	}

	public function hide_checklist() {
		// Set a transient to hide the checklist.
		set_transient('mint_hide_checklist', true, 0);
		return rest_ensure_response([
			'success' => true
		]);
	}
}
