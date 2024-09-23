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

use Mint\MRM\Admin\API\Controllers\ContactController;

/**
 * [Handle Contact Module related API callbacks]
 *
 * @desc Handle Contact Module related API callbacks
 * @package /app/API/Routes
 * @since 1.0.0
 */
class ContactRoute {

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
	protected $rest_base = 'contacts';


	/**
	 * MRM_Contact_Controller class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $controller;


	/**
	 * Register API endpoints routes for contact module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		$this->controller = ContactController::get_instance();

		/**
		 * Contact create endpoint
		 * Contact get all endpoint
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				)
			)
		);

		/**
		 * Register the REST API route to delete multiple contacts.
		 *
		 * @return void
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_all',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Contact update endpoint
		 * Single contact endpoint
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<contact_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Register the REST API route to delete a single contact.
		 *
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<contact_id>[\d]+)/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_single',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				)
			)
		);

		/**
		 * Add tags, lists, and segments from contact
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<contact_id>[\d]+)/groups',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'set_groups',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Register the REST API route to delete contacts groups.
		 *
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<contact_id>[\d]+)/groups/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_groups',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Register a REST route for updating a contact's avatar.
		 *
		 * @since 1.5.18
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<contact_id>[\d]+)/avatar',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'update_contact_avatar',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Assign multiple tags, lists to multiple contacts
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/groups',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'set_groups_to_multiple',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_contact_groups_count',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Remove tag(s), list(s) from multiple contacts
		 *
		 * @return void
		 * @since 1.5.1
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/groups/remove',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'remove_groups_from_multiple_contacts',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
					'args'                => array(
						'tags' => array(
							'type'     => 'array',
							'required' => false,
						),
						'lists' => array(
							'type'    => 'array',
							'required' => false,
						),
						'contact_ids' => array(
							'type'     => 'array',
							'required' => true,
						),
					),
				),
			)
		);

		/**
		 * Contact import endpoint for WooCommerce customers
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import/native/wc',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'import_contacts_native_wc',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Customers count endpoint for WooCommerce
		 *
		 * @return void
		 * @since 1.4.9
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import/wc',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_native_wc_customers',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Contact import endpoint for EDD customers
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import/native/edd',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'import_contacts_native_edd',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Send double opt-in message to contact
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<contact_id>[\d]+)/send-double-opt-in',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'send_double_opt_in',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Filtered list for of contacts
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/filter',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'get_filtered_contacts',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Total Contact Result for of contact index page
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/total-count',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_total_count',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Contact import endpoint
		 * This endpoint saves the raw data to database with correct mappings
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import/mailchimp',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'import_contacts_mailchimp' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		/**
		 * Send double optin message to multiple contacts
		 *
		 * @return void
		 * @since 1.5.1
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/send-double-optin',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'send_double_optin_to_multiple_contacts',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
					'args'                => array(
						'contact_ids' => array(
							'type'     => 'array',
							'required' => true,
						),
					),
				),
			)
		);

		/**
		 * Change status for multiple contacts
		 *
		 * @return void
		 * @since 1.5.1
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/change-status',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'change_status_to_multiple_contacts',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
					'args'                => array(
						'contact_ids' => array(
							'type'     => 'array',
							'required' => true,
						),
						'status' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
	}

}
