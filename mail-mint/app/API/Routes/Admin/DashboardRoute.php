<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-11-26 03:36:00
 * @modify date 2022-11-26 03:36:00
 * @package /app/API/Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\DashboardController;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * [Handle Dashboard Module related API callbacks]
 *
 * @desc Handle Dashboard Module related API callbacks
 * @package /app/API/Routes
 * @since 1.0.0
 */
class DashboardRoute {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $namespace = 'mrm/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $rest_base = 'reports';


	/**
	 * MRM_dashboard class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $controller;



	/**
	 * Register API endpoints routes for dashboard module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		$this->controller = DashboardController::get_instance();

		/**
		 *  Register the route for getting dashboard stats.
		 *  This route will return the stats for the dashboard cards.
		 *  The stats will include data like total campaigns, total automations, total subscribers,
		 *  total revenue, etc.
		 *  The route will be accessible via GET request.
		 *  The permission callback will check if the current user has the capability to view the dashboard
		 *
		 * @return void
		 * @since 1.0.0
		 * @since 1.18.0 Update the API endpoint to include performance data
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dashboard/cards',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_dashboard_stats',
					),
					'permission_callback' => PermissionManager::current_user_can( 'mint_view_dashboard' ),
				),
			)
		);

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/dashboard/performance',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array(
                        $this->controller,
						'get_dashboard_performance',
                    ),
                    'permission_callback' => PermissionManager::current_user_can( 'mint_view_dashboard' ),
                ),
            )
        );

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dashboard/metrics',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_dashboard_metrics',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_view_dashboard'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/hide-banner-temporarily', 
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'hide_community_banner_temporarily' ),
					'permission_callback' => function () {
						return current_user_can('manage_options');
					},
				),
		));

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/hide-banner-permanently',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array($this->controller, 'hide_community_banner_permanently'),
					'permission_callback' => function () {
						return current_user_can('manage_options');
					},
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/hide-checklist',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array($this->controller, 'hide_checklist'),
					'permission_callback' => function () {
						return current_user_can('manage_options');
					},
				),
			)
		);
	}
}
