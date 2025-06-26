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
     *  - It checks if the community banner should be shown and if there is an active SMTP plugin.
     *  - The response includes the performance data and the status of the community banner
     *    and SMTP warning.
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
				'campaigns'      => $campaigns,
				'automations'    => $automations,
				'onboarding'     => $onboarding,
				'show_community' => $this->should_show_community_banner(),
				'smtp_warning'   => MrmCommon::find_active_smtp_plugin(),
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

	/**
	 * Check if community banner should be shown.
	 * 
	 * Description:
	 * - If the user has permanently hidden the banner, it will not be shown.
	 * - If the banner is temporarily hidden, it will not be shown.
	 * - If neither of the above conditions are met, the banner will be shown.
	 *
	 * @return bool
	 * @since 1.17.9
	 */
	private function should_show_community_banner(){
		// Check if user has permanently hidden the banner.
		$permanently_hidden = get_transient('wpfnl_community_banner_permanently_hidden');
		if ($permanently_hidden) {
			return false;
		}

		// Check if banner is temporarily hidden.
		$temporarily_hidden = get_transient('wpfnl_community_banner_temporarily_hidden');
		if ($temporarily_hidden) {
			return false;
		}

		return true;
	}

	/**
	 * Hide community banner temporarily.
	 * 
	 * Description:
	 * - Set a transient for 7 days to hide the banner.
	 *
	 * @return \WP_REST_Response
	 * @since 1.17.9
	 */
	public function hide_community_banner_temporarily(){
		// Set transient for 7 days.
		set_transient('wpfnl_community_banner_temporarily_hidden', true, 7 * DAY_IN_SECONDS);
		return rest_ensure_response([
			'success' => true
		]);
	}

	/**
	 * Hide community banner permanently.
	 *
	 * Description:
	 * - Set a permanent transient to hide the banner.
	 *
	 * @return \WP_REST_Response
	 * @since 1.17.9
	 */
	public function hide_community_banner_permanently(){
		// Set permanent transient (0 = no expiration).
		set_transient('wpfnl_community_banner_permanently_hidden', true, 0);
		return rest_ensure_response([
			'success' => true
		]);
	}

	public function hide_checklist() {
		// Set a transient to hide the checklist.
		set_transient('mint_hide_checklist', true, 0);
		return rest_ensure_response([
			'success' => true
		]);
	}
}
