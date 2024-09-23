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

use Mint\MRM\Admin\API\Controllers\MessageController;

/**
 * [Handle Send Email Module related API callbacks]
 *
 * @desc Handle Send Email Module related API callbacks
 * @package /app/API/Routes
 * @since 1.0.0
 */
class SendEmailRoute {

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
	protected $rest_base = 'messages';


	/**
	 * MRM_Email class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $mailer;


	/**
	 * Register API endpoints routes for send email module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		$this->mailer = MessageController::get_instance();

		/**
		 * Send Email create endpoint
		 * Get Send Email endpoint
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<contact_id>[\d]+)',
			array(
				array(
					'methods'  => \WP_REST_Server::CREATABLE,
					'callback' => array(
						$this->mailer,
						'create_or_update',
					),
				),

			)
		);
	}

}
