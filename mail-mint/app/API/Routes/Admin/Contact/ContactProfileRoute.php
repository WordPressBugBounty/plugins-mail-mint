<?php
/**
 * Mail Mint
 *
 * @author [getwpfunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/API/Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use WP_REST_Server;
use Mint\MRM\Admin\API\Controllers\ContactProfileController;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * [Handle contact profile related API callbacks]
 *
 * @desc Handle contact profile related API callbacks
 * @package /app/API/Routes
 * @since 1.8.0
 */
class ContactProfileRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.8.0
	 */
	protected $rest_base = 'contacts';

	/**
	 * ContactProfileController class object
	 *
	 * @var ContactProfileController
	 */
	protected $controller;

	/**
	 * Initialize responsible controller for this route
	 */
	public function __construct() {
		$this->controller = new ContactProfileController();
	}

	/**
	 * Register API endpoints routes for contact related forms
	 *
	 * @return void
	 * @since 1.8.0
	 */
	public function register_routes() {

		/**
		 * Contact forms retrieve endpoints
		 *
		 * @return void
		 * @since 1.8.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/forms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_contact_forms' ),
					'permission_callback' => PermissionManager::current_user_can('mint_read_contacts'),
				),
			)
		);

		/**
		 * Endpoint to delete form from contact profile
		 *
		 * @return void
		 * @since 1.8.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/forms/(?P<contact_meta_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_contact_forms' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
			)
		);

		/**
		 * Register REST routes for managing notes.
		 *
		 * @return void
		 * @since 1.7.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/notes',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'create_or_update_note' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_notes_to_contact' ),
					'permission_callback' => PermissionManager::current_user_can('mint_read_contacts'),
				)
			)
		);

		/**
		 * Register REST routes to update note.
		 *
		 * @return void
		 * @since 1.7.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/notes/(?P<note_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this->controller, 'create_or_update_note' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				)
			)
		);

		/**
		 * Register REST routes to delete note.
		 *
		 * @return void
		 * @since 1.8.2
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/notes/(?P<note_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_contact_note' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
			)
		);

		/**
		 * Register a REST route to retrieve emails associated with a specific contact.
		 *
		 * @return void
		 * @since 1.7.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/emails',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_contact_emails' ),
					'permission_callback' => PermissionManager::current_user_can('mint_read_contacts'),
				),
			)
		);

		/**
		 * Register a REST route to retrieve emails associated with a specific contact.
		 *
		 * @return void
		 * @since 1.8.2
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/emails/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_contact_emails' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
			)
		);

		/**
		 * Register REST routes for delete email.
		 *
		 * @return void
		 * @since 1.7.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<contact_id>[\d]+)/emails/(?P<email_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_contact_email' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				)
			)
		);
	}

}
