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

use Mint\MRM\Admin\API\Controllers\ContactImportController;
use Mint\MRM\Utilities\Helper\PermissionManager;
use WP_REST_Server;

/**
 * [Handle contact import related API callbacks]
 *
 * @desc Handle contact import related API callbacks
 * @package /app/API/Routes
 * @since 1.4.9
 */
class ContactImportRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.4.9
	 */
	protected $rest_base = 'contacts/import';

	/**
	 * ContactImportController class object
	 *
	 * @var ContactImportController
	 */
	protected $controller;

	/**
	 * Initialize responsible controller for this route
	 */
	public function __construct() {
		$this->controller = new ContactImportController();
	}

	/**
	 * Register API endpoints routes for contact import
	 *
	 * @return void
	 * @since 1.4.9
	 */
	public function register_routes() {
		/**
		 * Contact import mailchimp send attrs endpoint
		 *
		 * @return void
		 * @since 1.4.9
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/mailchimp/attrs',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'get_mailchimp_lists_attributes' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
			)
		);

		/**
		 * Contact import mailchimp get members endpoint
		 *
		 * @return void
		 * @since 1.4.9
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/mailchimp/members',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'handle_mailchimp_member_headers' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
			)
		);

		/**
		 * Customers count endpoint for EDD
		 *
		 * @return void
		 * @since 1.4.9
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/total/edd',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_edd_contacts_total' ),
					'permission_callback' => PermissionManager::current_user_can('mint_read_contacts'),
				),
			)
		);

		/**
		 * Contact import csv send attrs endpoint
		 *
		 * @return void
		 * @since 1.5.1
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/csv/attrs',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'validate_and_import_contact_attributes' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'delimiter' => array(
							'description' 		=> __( 'The delimiter used to separate values in the CSV file being imported.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * Register a REST route for importing raw contact attributes data.
		 *
		 * @return void
		 * @since 1.5.2
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/raw/attrs',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'import_contacts_raw_get_attrs' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'delimiter' => array(
							'description' 		=> __( 'The delimiter used to separate values in the data being imported.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * Register a REST route for retrieving and formatting 
		 * native WordPress roles.
		 *
		 * @return void
		 * @since 1.5.4
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/native/wp/roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'retrieve_and_format_native_wp_roles' ),
					'permission_callback' => PermissionManager::current_user_can('mint_read_contacts'),
				),
			)
		);

		/**
		 * Register a REST route for importing contacts with native WordPress user roles.
		 *
		 * @return void
		 * @since 1.5.4
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/native/wp',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'retrieve_wordpress_user_map_fields' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'roles' => array(
							'description' 		=> __( 'The mapping of WordPress user roles for the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Registers a REST route for importing contacts from a CSV file.
		 *
		 * @return void
		 * @since 1.5.4
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/csv',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'import_contacts_from_csv' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'map' => array(
							'description' 		=> __( 'The mapping of data fields in the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						),
						'file' => array(
							'description' 		=> __( 'The uploaded file name to import content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'headers' => array(
							'description' 		=> __( 'The header fields in the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						),
						'data' => array(
							'description' 		=> __( 'Data of the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						),
					),
				),
			)
		);

		/**
		 * Registers a REST route for importing contacts from Raw data file.
		 *
		 * @return void
		 * @since 1.5.5
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/raw',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'import_contacts_from_raw' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'map' => array(
							'description' 		=> __( 'The mapping of data fields in the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						),
						'headers' => array(
							'description' 		=> __( 'The header fields in the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						),
						'raw' => array(
							'description' 		=> __( 'Data of the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						),
					),
				),
			)
		);

		/**
		 * Registers a REST route for inserting contacts from WordPress roles.
		 *
		 * @return void
		 * @since 1.5.7
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/wordpress',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'insert_native_wp_contacts' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'roles' => array(
							'description' 		=> __( 'The WordPress roles in the imported content.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for mapping contacts with LearnDash courses.
		 *
		 * @access public
		 * @since 1.8.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/learndash/map',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'map_contacts_with_learndash' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedCourses' => array(
							'description' 		=> __( 'The selected courses from which to import contacts.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for inserting LearnDash contacts.
		 *
		 * @access public
		 * @since 1.8.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/learndash',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'insert_learndash_contacts' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedCourses' => array(
							'description' 		=> __( 'The selected courses from which to import contacts.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for mapping contacts with Tutor LMS courses.
		 *
		 * @access public
		 * @since 1.8.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tutorlms/map',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'map_contacts_with_tutorlms' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedCourses' => array(
							'description' 		=> __( 'The selected courses from which to import contacts.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for inserting Tutor LMS contacts.
		 *
		 * @access public
		 * @since 1.8.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/tutorlms',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'insert_tutorlms_contacts' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedCourses' => array(
							'description' 		=> __( 'The selected courses from which to import contacts.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for mapping contacts with MemberPress levels.
		 *
		 * @access public
		 * @since 1.8.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/memberpress/map',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'map_contacts_with_memberpress' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedLevels' => array(
							'description' 		=> __( 'The selected level from which to import contacts.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for inserting MemberPress members.
		 *
		 * @access public
		 * @since 1.8.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/memberpress',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'insert_memberpress_contacts' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedLevels' => array(
							'description' 		=> __( 'The selected level from which to import contacts.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for mapping contacts with LifterLMS courses or memberships.
		 *
		 * @access public
		 * @since 1.11.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/lifterlms/map',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'map_contacts_with_lifterlms' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedCourses' => array(
							'description' 		=> __( 'The selected courses from which to import contacts.', 'mrm' ),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * Register a REST route for inserting LifterLMS contacts.
		 *
		 * @access public
		 * @since 1.12.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/lifterlms',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this->controller, 'insert_lifterlms_contacts'),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
					'args'                => array(
						'selectedCourses' => array(
							'description' 		=> __('The selected courses from which to import contacts.', 'mrm'),
							'required'    		=> true,
							'type'              => 'array',
							'sanitize_callback' => 'rest_sanitize_array',
						)
					),
				),
			)
		);

		/**
		 * mail poet contact import
		 *
		 * @return void
		 * @since 1.17.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/mailpoet',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array($this->controller,'get_mail_poet_contact_fields'),
					'permission_callback' => PermissionManager::current_user_can('mint_read_contacts'),
				),
			)
		);

		/**
		 * Register a REST route for inserting MailPoet contacts.
		 *
		 * @access public
		 * @since 1.17.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/mailpoet',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'insert_mail_poet_contacts' ),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
			)
		);

		/**
		 * Customers count endpoint for fluentbooking
		 *
		 * @return void
		 * @since 1.16.6
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fluentbooking',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_fluentbooking_customers',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_contacts'),
				),
			)
		);

		/**
		 * Contact import endpoint for EDD customers
		 *
		 * @return void
		 * @since 1.16.6
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/native/fluentbooking',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'import_contacts_fluentbooking',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_contacts'),
				),
			)
		);
	}
}
