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
		 * Dashboard no id endpoint
		 * Get Campaigns Report
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_reports',
					),
					'permission_callback' => PermissionManager::current_user_can( 'mint_view_dashboard' ),
				),
			)
		);

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/campaign-reports',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array(
                        $this->controller,	
                        'get_campaign_analytics_data',
                    ),
                    'permission_callback' => PermissionManager::current_user_can( 'mint_view_dashboard' ),
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
	}
}
