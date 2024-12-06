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

use Mint\MRM\Admin\API\Controllers\ListController;
use WP_REST_Server;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * [Handle List Module related API callbacks]
 *
 * @desc Handle List Module related API callbacks
 * @package /app/API/Routes
 * @since 1.0.0
 */
class ListRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $rest_base = 'lists';

	/**
	 * ListController class object
	 *
	 * @var ListController
	 */
	protected $controller;

	/**
	 * Initialize responsible controller for this route
	 */
	public function __construct() {
		$this->controller = new ListController();
	}

	/**
	 * Register API endpoints routes for lists module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		/**
		 * List create endpoint
		 * Get and search lists endpoint
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'create_or_update' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_contact_cats' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_all' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
				),
			)
		);

		/**
		 * List delete endpoint
		 * Get and search lists endpoint
		 *
		 * @return void
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_all' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contact_cats_delete'),
				),
			)
		);

		/**
		 * List update endpoint
		 * List get endpoint
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base  . '/(?P<list_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this->controller, 'create_or_update' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contact_cats'),
					'args'                => array(
						'list_id' => array(
							'type'     			=> 'integer',
							'required' 			=> true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * List delete endpoint
		 *
		 * @return void
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base  . '/(?P<list_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_single' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contact_cats_delete'),
					'args'                => array(
						'list_id' => array(
							'type'     			=> 'integer',
							'required' 			=> true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/select',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_lists_for_dropdown' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
				),
			)
		);
	}

}
