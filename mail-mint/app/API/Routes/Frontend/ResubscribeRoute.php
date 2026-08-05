<?php
/**
 * Frontend REST route for AJAX-based resubscribe.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.24.5
 */

namespace Mint\MRM\Frontend\API\Routes;

use WP_REST_Server;
use Mint\MRM\Frontend\API\Controllers\ResubscribeController;

/**
 * Registers the POST mint-mail/v1/resubscribe endpoint.
 *
 * @since 1.24.5
 */
class ResubscribeRoute extends FrontendRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.24.5
	 */
	protected $rest_base = 'resubscribe';

	/**
	 * Controller instance.
	 *
	 * @var ResubscribeController
	 * @since 1.24.5
	 */
	protected $controller;

	/**
	 * Constructor.
	 *
	 * @since 1.24.5
	 */
	public function __construct() {
		$this->controller = new ResubscribeController();
	}

	/**
	 * Register REST API route.
	 *
	 * @return void
	 * @since 1.24.5
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this->controller, 'resubscribe' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'hash' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}
}
