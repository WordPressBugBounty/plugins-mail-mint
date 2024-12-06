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

use Mint\MRM\Admin\API\Controllers\FieldGroupController;
use WP_REST_Server;

/**
 * [Manage Custom Field Group related API]
 *
 * @desc Manage Custom Field Group related API
 * @package /app/API/Routes
 * @since 1.0.0
 */
class FieldGroupRoute {

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
	protected $rest_base = 'field-groups';


	/**
	 * FieldGroupController class object
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
		$this->controller = FieldGroupController::get_instance();

		/**
		 * Field group create endpoint
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
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all',
					),
					'permission_callback' => '__return_true',
				),
			)
		);

		/**
		 * Field group update endpoint
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<group_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_single',
					),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

}
