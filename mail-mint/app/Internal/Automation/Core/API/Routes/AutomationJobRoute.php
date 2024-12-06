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

use Mint\MRM\Admin\API\Controllers\AutomationJobController;
use Mint\MRM\Utilities\Helper\PermissionManager;
use WP_REST_Server;

/**
 * [Manage Automation related API]
 *
 * @desc Manage Automation related API
 *
 * @package /app/API/Routes
 * @since 1.0.0
 */
class AutomationJobRoute {

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
	protected $rest_base = 'automation/job';


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
		$this->controller = AutomationJobController::get_instance();

		/**
		 * Automation multiple interaction endpoints
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_automations'),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_automations'),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array(
						$this->controller,
						'delete_all',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_automations'),
				),
			)
		);

		/**
		 * Automation single interaction endpoints
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<automation_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_automations'),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_automations'),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array(
						$this->controller,
						'delete_single',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_automations'),
				),

			)
		);

		/**
		 * Update automation status
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/status-update',
			array(

				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'status_update',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_automations'),
				),

			)
		);
	}

}
