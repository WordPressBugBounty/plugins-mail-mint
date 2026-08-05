<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/API/Routes/Admin
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\CustomFieldController;
use Mint\MRM\Utilities\Helper\PermissionManager;
use WP_REST_Server;

/**
 * [Manage Custom Fields related API]
 *
 * @desc Manage Custom Fields related API
 * @package /app/API/Routes/Admin
 * @since 1.24.5
 */
class CustomFieldRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.24.5
	 */
	protected $rest_base = 'settings/custom-fields';

	/**
	 * CustomFieldController class object
	 *
	 * @var CustomFieldController
	 * @since 1.24.5
	 */
	protected $controller;

	/**
	 * Initializes a new instance of the CustomFieldRoute class.
	 *
	 * @since 1.24.5
	 */
	public function __construct() {
		$this->controller = new CustomFieldController();
	}

	/**
	 * Register API endpoints routes for the custom fields module.
	 *
	 * @return void
	 * @since 1.24.5
	 */
	public function register_routes() {

		/**
		 * Field group create endpoint
		 *
		 * @since 1.24.5
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
					'permission_callback' => PermissionManager::current_user_can_any( array( 'mint_manage_settings', 'mint_manage_forms' ) ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all',
					),
					'permission_callback' => PermissionManager::current_user_can_any( array( 'mint_manage_settings', 'mint_read_forms' ) ),
				),
			)
		);

		/**
		 * Field group update endpoint
		 *
		 * @since 1.24.5
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<field_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => PermissionManager::current_user_can_any( array( 'mint_manage_settings', 'mint_manage_forms' ) ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => PermissionManager::current_user_can_any( array( 'mint_manage_settings', 'mint_read_forms' ) ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<field_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_single',
					),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
				),
			)
		);

		/**
		 * Field reorder endpoint
		 *
		 * @since 1.24.5
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reorder',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'reorder',
					),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
				),
			)
		);
	}

}
