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

namespace Mint\MRM\Frontend\API\Routes;

use Mint\MRM\Frontend\API\Actions\ActionCreator;
use WP_REST_Server;
use Mint\MRM\Frontend\API\Controllers\CookieController;


/**
 * Manages Cookie
 *
 * @package /app/API/Routes
 * @since 1.0.0
 */
class CookieRoute extends FrontendRoute {
	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $rest_base = 'mint-form-cookie-submit';


	/**
	 * CookieController class object
	 *
	 * @var CookieController
	 */
	protected $controller;

	/**
	 * Initialize responsible controller for this route
	 */
	public function __construct() {
		$this->controller = new CookieController();
	}


	/**
	 * Register API endpoints routes for Frontend module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this->controller, 'set_mint_mail_cookie_for_form' ),
				'permission_callback' => array(
					$this->controller,
					'rest_permissions_check',
				),
				'args'                => array(
					'wp_nonce' => array(
						'type'     => 'string',
						'required' => true,
					),
					'form_id'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}
}
