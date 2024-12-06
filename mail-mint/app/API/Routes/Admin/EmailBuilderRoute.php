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

use Mint\MRM\Admin\API\Controllers\AnalyticsController;
use Mint\MRM\Admin\API\Controllers\EmailBuilderController;
use WP_REST_Server;

/**
 * Manages Analytics module API
 *
 * @package /app/API/Routes
 * @since 1.0.0
 */
class EmailBuilderRoute {
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
	protected $rest_base = 'email-builder';


	/**
	 * AnalyticsController class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $controller;



	/**
	 * Register API endpoints routes for analytics module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		$this->controller = EmailBuilderController::get_instance();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/timer',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'create_countdown_timer',
					),
					'permission_callback' => '__return_true',
				),
			)
		);

	}
}