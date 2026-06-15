<?php
/**
 * Mail Mint
 *
 * Campaign analytics controller for the Free plugin.
 *
 * Serves the regular-campaign analytics overview on Mail Mint Free. The matching
 * route is only registered when Mail Mint Pro is absent (see AnalyticsRoute), so
 * this controller never overlaps Pro's analytics surface.
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-01-20 11:03:17
 * @modify date 2024-01-20 11:03:17
 * @package /app/API/Controllers/Admin
 * @since 1.23.3
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Admin\API\Controllers\AdminBaseController;
use Mint\MRM\API\Actions\CampaignAnalyticsActionCreator;
use MRM\Common\MrmCommon;
use WP_REST_Request;

/**
 * CampaignAnalyticsController class.
 *
 * Handles REST API requests for the regular-campaign analytics overview.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since 1.23.3
 */
class CampaignAnalyticsController extends AdminBaseController {

	/**
	 * Analytics action instance for processing analytics requests.
	 *
	 * @var \Mint\MRM\API\Actions\CampaignAnalyticsAction
	 * @since 1.23.3
	 */
	protected $action;

	/**
	 * Constructor.
	 *
	 * Initializes the analytics action via its creator.
	 *
	 * @since 1.23.3
	 */
	public function __construct() {
		$creator      = new CampaignAnalyticsActionCreator();
		$this->action = $creator->makeAction();
	}

	/**
	 * Handle campaign analytics header request.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response The response containing the retrieved analytics data.
	 *
	 * @since 1.23.3
	 */
	public function handle_campaign_analytics_request( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$params = filter_var_array( $params );
		$result = $this->action->format_and_retrieve_campaign_analytics( $params );

		do_action( 'mailmint_campaign_analytics', isset( $params['campaign_id'] ) ? $params['campaign_id'] : 0 );

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Data has been retrieved successfully.', 'mrm' ),
				'results' => $result,
			)
		);
	}

	/**
	 * Handle campaign analytics overview request.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return \WP_REST_Response The response containing the overview metrics.
	 *
	 * @since 1.23.3
	 */
	public function handle_campaign_analytics_overview( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$params = filter_var_array( $params );
		$result = $this->action->format_and_retrieve_campaign_overview( $params );

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Data has been retrieved successfully.', 'mrm' ),
				'results' => $result,
			)
		);
	}

}
