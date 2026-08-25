<?php
/**
 * Abandoned Cart Settings REST API Route
 *
 * Registers /mrm/v1/settings/abandoned-cart.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.31.0
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\AbandonedCartSettingController;
use Mint\MRM\Utilities\Helper\PermissionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbandonedCartSettingRoute
 *
 * @package Mint\MRM\Admin\API\Routes
 * @since   1.31.0
 */
class AbandonedCartSettingRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.31.0
	 */
	protected $rest_base = 'settings';

	/**
	 * AbandonedCartSettingController instance.
	 *
	 * @var AbandonedCartSettingController
	 * @since 1.31.0
	 */
	protected $controller;

	/**
	 * Register the abandoned cart settings routes.
	 *
	 * @return void
	 * @since 1.31.0
	 */
	public function register_routes() {
		$this->controller = new AbandonedCartSettingController();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/abandoned-cart',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'insert_or_update' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
				),
			)
		);
	}
}
