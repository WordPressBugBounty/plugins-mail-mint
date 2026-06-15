<?php
/**
 * Mail Mint
 *
 * Campaign analytics route for the Free plugin.
 *
 * Registers the regular-campaign analytics overview endpoints that ship with
 * Mail Mint Free. When Mail Mint Pro is installed it provides the full analytics
 * route set (overview plus activity/click-performance drill-downs), so this route
 * stands down entirely to avoid double-registering against any Pro version.
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/API/Routes/Admin
 * @since 1.23.3
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\CampaignAnalyticsController;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * AnalyticsRoute class.
 *
 * Handles campaign analytics related API callbacks for Mail Mint Free.
 *
 * @package /app/API/Routes/Admin
 * @since 1.23.3
 */
class AnalyticsRoute {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 * @since 1.23.3
	 */
	protected $namespace = 'mrm/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.23.3
	 */
	protected $rest_base = 'campaign/analytics';

	/**
	 * Controller instance.
	 *
	 * @var CampaignAnalyticsController
	 * @since 1.23.3
	 */
	protected $controller;

	/**
	 * Register API endpoint routes for the campaign analytics module.
	 *
	 * Bails out when Mail Mint Pro is present, since Pro registers the full
	 * analytics route set. The class_exists check detects Pro's route regardless
	 * of the installed Pro version or plugin update order, preventing duplicate
	 * route registration.
	 *
	 * @return void
	 * @since 1.23.3
	 */
	public function register_routes() {
		// Pro ships the complete analytics route set; never double-register against it.
		if ( class_exists( 'MailMintPro\\Mint\\Admin\\API\\Routes\\AnalyticsRoute' ) ) {
			return;
		}

		$this->controller = new CampaignAnalyticsController();

		/**
		 * Register REST route for retrieving the campaign analytics header
		 * (campaign record plus its email steps).
		 *
		 * @since 1.23.3
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<campaign_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'handle_campaign_analytics_request' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

		/**
		 * Register REST route for retrieving the campaign analytics overview metrics.
		 *
		 * @since 1.23.3
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<campaign_id>[\d]+)/overview/(?P<email_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'handle_campaign_analytics_overview' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

	}
}
