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
	 */
	public function get_reports( WP_REST_Request $request ) {
		// Get query params from the API.
		$params     = MrmCommon::get_api_params_values( $request );
		$filter     = ! empty( $params[ 'filter' ] ) ? $params[ 'filter' ] : 'all';
		$start_date = ! empty( $params[ 'start_date' ] ) ? $params[ 'start_date' ] : gmdate( 'Y-m-d' );
		$end_date   = ! empty( $params[ 'end_date' ] ) ? $params[ 'end_date' ] : gmdate( 'Y-m-d' );

		$top_cards_data = DashboardModel::get_top_cards_data( $filter, $start_date, $end_date );
		$campaign       = DashboardModel::get_email_campaign_data( $filter, $start_date, $end_date );
		$subscribers    = DashboardModel::get_subscribers_report( $filter );
		$revenue        = MrmCommon::is_wc_active() ? DashboardModel::get_revenue_reports( $filter ) : array();
		$contact        = DashboardModel::get_contact_chart_data( $filter );

		$contact[ 'success' ] = true;

		$card_data = array(
			'contact_data'    => !empty( $top_cards_data[ 'contact_data' ] ) ? $top_cards_data[ 'contact_data' ] : array(),
			'campaign_data'   => !empty( $top_cards_data[ 'campaign_data' ] ) ? $top_cards_data[ 'campaign_data' ] : array(),
			'form_data'       => !empty( $top_cards_data[ 'form_data' ] ) ? $top_cards_data[ 'form_data' ] : array(),
			'automation_data' => !empty( $top_cards_data[ 'automation_data' ] ) ? $top_cards_data[ 'automation_data' ] : array(),
		);

		$response = [
			'success' => true,
			'data'    => [
				'card_data'             => $card_data,
				'campaign'              => $campaign,
				'subscribers'           => $subscribers,
				'revenue'               => $revenue,
				'contact'               => $contact,
			]
		];

		return rest_ensure_response( $response );
	}


    /**
     * Get campaign analytics data
     *
     * @param WP_REST_Request $request
     * @return \WP_Error|\WP_REST_Response
     * @since 1.0.0
     */
	public function get_campaign_analytics_data( WP_REST_Request $request ) {
	    $campaigns = DashboardModel::get_campaigns_short_analytics();
        $response = [
            'success'               => true,
            'campaign_analytics'    => $campaigns
        ];
        return rest_ensure_response( $response );
    }
}
