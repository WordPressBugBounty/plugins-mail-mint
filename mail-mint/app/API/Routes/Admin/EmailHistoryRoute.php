<?php
/**
 * Mail Mint - Email History Route
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.20.0
 */

namespace Mint\MRM\Admin\API\Routes;

use WP_REST_Server;
use Mint\MRM\Admin\API\Controllers\EmailHistoryController;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * Registers REST API routes for the Email History feature.
 *
 * @since 1.20.0
 */
class EmailHistoryRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'email-history';

	/**
	 * Controller instance.
	 *
	 * @var EmailHistoryController
	 */
	protected $controller;

	/**
	 * Initialize controller.
	 */
	public function __construct() {
		$this->controller = new EmailHistoryController();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 * @since 1.20.0
	 */
	public function register_routes() {

		// GET  mrm/v1/email-history  — list with search, filter, pagination.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_all' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

		// POST mrm/v1/email-history/delete  — bulk delete.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_all' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns_delete' ),
				),
			)
		);

		// POST mrm/v1/email-history/{id}/delete  — single delete.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_single' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns_delete' ),
				),
			)
		);

		// GET  mrm/v1/email-history/{id}  — single record with full body for preview.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_single' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

		// POST mrm/v1/email-history/{id}/resend  — resend email to original recipient.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/resend',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'resend' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns' ),
				),
			)
		);
	}
}
