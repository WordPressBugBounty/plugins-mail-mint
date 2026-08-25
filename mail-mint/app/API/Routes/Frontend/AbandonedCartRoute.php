<?php
/**
 * Abandoned Cart storefront REST API Routes
 *
 * Registers /mint-mail/v1/abandoned-cart/*.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.31.0
 */

namespace Mint\MRM\Frontend\API\Routes;

use Mint\MRM\Frontend\API\Controllers\AbandonedCartController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbandonedCartRoute
 *
 * @package Mint\MRM\Frontend\API\Routes
 * @since   1.31.0
 */
class AbandonedCartRoute extends FrontendRoute {

	/**
	 * Route base.
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
	 * Register the storefront abandoned cart routes.
	 *
	 * @return void
	 * @since 1.31.0
	 */
	public function register_routes() {
		$this->controller = new AbandonedCartController();

		// POST /mint-mail/v1/abandoned-cart/update-checkout
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update-checkout',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'update_checkout' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'email'                => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_email',
						),
						'checkout_fields_data' => array(
							'required' => false,
							'type'     => 'string',
						),
					),
				),
			)
		);

		// POST /mint-mail/v1/abandoned-cart/skip-track
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/skip-track',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_abandoned_cart' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);
	}
}
