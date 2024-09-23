<?php
/**
 * Mail Mint Automation Routes
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 *
 * @package /app/API/Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\AutomationController;
use Mint\MRM\Admin\API\Controllers\AutomationLogController;
use WP_REST_Server;

/**
 * [Manage Automation related API]
 *
 * @desc Manage Automation related API
 *
 * @package /app/API/Routes
 * @since 1.0.0
 */
class AutomationLogRoute {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected $namespace = 'mrm/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected $rest_base = 'automation/log';


	/**
	 * AutomationController class object
	 *
	 * @var object
	 *
	 * @since 1.0.0
	 */
	protected $controller;



	/**
	 * Register API endpoints routes for lists module
	 *
	 * @return void
	 * @since  1.0.0
	 */
	public function register_routes() {
		$this->controller = AutomationLogController::get_instance();

		/**
		 * Automation single interaction endpoints
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Automation single interaction report endpoints
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/performance/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_automation_performance_analytics',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Automation single interaction report endpoints
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/overall/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_automation_overall_analytics',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);
	}

}
