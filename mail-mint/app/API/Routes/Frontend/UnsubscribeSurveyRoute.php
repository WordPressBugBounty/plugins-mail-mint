<?php
/**
 * Frontend REST route for unsubscribe survey submission.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.20.0
 */

namespace Mint\MRM\Frontend\API\Routes;

use WP_REST_Server;
use Mint\MRM\Frontend\API\Controllers\UnsubscribeSurveyController;

/**
 * Registers the POST mint-mail/v1/unsubscribe-survey endpoint.
 *
 * @since 1.20.0
 */
class UnsubscribeSurveyRoute extends FrontendRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.20.0
	 */
	protected $rest_base = 'unsubscribe-survey';

	/**
	 * Controller instance.
	 *
	 * @var UnsubscribeSurveyController
	 * @since 1.20.0
	 */
	protected $controller;

	/**
	 * Constructor.
	 *
	 * @since 1.20.0
	 */
	public function __construct() {
		$this->controller = new UnsubscribeSurveyController();
	}

	/**
	 * Register REST API route.
	 *
	 * @return void
	 * @since 1.20.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this->controller, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'hash'        => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reason'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'reason_text' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}
}
