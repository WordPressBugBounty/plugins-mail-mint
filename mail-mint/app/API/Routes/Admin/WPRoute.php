<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/API/Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\WPController;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * [Manage WP Page related API]
 *
 * @desc Manage WP Page related API
 * @package /app/API/Routes
 * @since 1.0.0
 */
class WPRoute {
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
	protected $rest_base = 'wp';


	/**
	 * CampaignController class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $controller;


	/**
	 * Register API endpoints routes for lists module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		$this->controller = WPController::get_instance();

		// Get wp pages api endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/pages',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_pages',
					),
					'permission_callback' => PermissionManager::current_user_can('manage_options'),
				),
			)
		);

		// Get wp posts api endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/posts',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_posts',
					),
					'permission_callback' => PermissionManager::current_user_can('manage_options'),
				),
			)
		);

		// Get wc products api endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_products',
					),
					'permission_callback' => PermissionManager::current_user_can('manage_options'),
				),
			)
		);

		/**
		 * Registers a custom REST API route for retrieving categories.
		 *
		 * @since 1.5.11
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/categories',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_categories' ),
					'permission_callback' => PermissionManager::current_user_can('manage_options'),
				),
			)
		);

		/**
		 * Registers a custom REST API route for retrieving tags.
		 *
		 * @since 1.5.11
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tags',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_tags' ),
					'permission_callback' => PermissionManager::current_user_can('manage_options'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/admins',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_admins' ),
					'permission_callback' => PermissionManager::current_user_can('manage_options'),
				),
			)
		);
	}
}