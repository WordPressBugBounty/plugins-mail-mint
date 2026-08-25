<?php
/**
 * Abandoned Cart REST API Routes
 *
 * Registers the cart list, single cart details and delete endpoints under
 * /mrm/v1/abandoned-cart/*.
 *
 * Analytics and the automation endpoints (/analytics, /automation/{id},
 * /abandoned-automation, /manually-run-automation) are deliberately NOT registered
 * here — they belong to cart recovery, which remains a Mail Mint Pro feature, and Pro
 * registers them on this same namespace.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.31.0
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\AbandonedCartController;
use Mint\MRM\Utilities\Helper\PermissionManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbandonedCartRoute
 *
 * @package Mint\MRM\Admin\API\Routes
 * @since   1.31.0
 */
class AbandonedCartRoute extends AdminRoute {

	/**
	 * Route base for all abandoned cart endpoints.
	 *
	 * @var string
	 * @since 1.31.0
	 */
	protected $rest_base = 'abandoned-cart';

	/**
	 * AbandonedCartController instance.
	 *
	 * @var AbandonedCartController
	 * @since 1.31.0
	 */
	protected $controller;

	/**
	 * Registers the abandoned cart REST routes.
	 *
	 * @return void
	 * @since 1.31.0
	 */
	public function register_routes() {
		$this->controller = new AbandonedCartController();

		$lists = array(
			'recoverable' => 'get_recoverable_carts',
			'recovered'   => 'get_recovered_carts',
			'lost'        => 'get_losts_carts',
		);

		foreach ( $lists as $slug => $callback ) {
			// GET /mrm/v1/abandoned-cart/{recoverable|recovered|lost}
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/' . $slug,
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( $this->controller, $callback ),
						'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
					),
				)
			);

			// POST /mrm/v1/abandoned-cart/{recoverable|recovered|lost}/delete
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/' . $slug . '/delete',
				array(
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( $this->controller, 'delete_all' ),
						'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
					),
				)
			);
		}

		// GET /mrm/v1/abandoned-cart/details/{id}
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/details/(?P<abandoned_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_single_cart' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
				),
			)
		);

		// POST /mrm/v1/abandoned-cart/{id}/delete
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<abandoned_id>\d+)/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_single' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
				),
			)
		);
	}
}
