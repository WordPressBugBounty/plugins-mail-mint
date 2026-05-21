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

use Mint\MRM\Admin\API\Controllers\FormController;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * [Handle Form Module related API callbacks]
 *
 * @desc Handle Form Module related API callbacks
 * @package /app/API/Routes
 * @since 1.0.0
 */
class FormRoute {

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
	protected $rest_base = 'forms';


	/**
	 * MRM_form class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $controller;



	/**
	 * Register API endpoints routes for form module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		$this->controller = New FormController;

		/**
		 * Form no id endpoint
		 * Create, All Forms
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms'),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Form no id endpoint
		 * Delete Multiple
		 *
		 * @return void
		 * @since 1.0.0
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms_delete'),
				),
			)
		);

		/**
		 * Form with id endpoint
		 * Update, Single Form
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<form_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms'),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Form with id endpoint
		 * Single Delete
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<form_id>[\d]+)/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_single',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms_delete'),
				),
			)
		);

		/**
		 * Route for from list only id and title
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/form-list',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all_id_title',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Route for update status of a form
		 *
		 * @return void
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update-status/(?P<form_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'form_status_update',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms'),
				),
			)
		);

		/**
		 * Route for get settings of a form
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-form-settings/(?P<form_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_form_settings',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Route for get id, title, group_ids
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-title-group/(?P<form_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_title_group',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Route for get id and body
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-form-body/(?P<form_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_form_body',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Route to get all form templates
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get-form-templates',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_form_templates',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Route to list entries for a specific form.
		 *
		 * @return void
		 * @since 1.16.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<form_id>[\d]+)/entries',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_form_entries',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Route to retrieve a single form entry.
		 *
		 * @return void
		 * @since 1.16.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<form_id>[\d]+)/entries/(?P<entry_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_form_entry',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_forms'),
				),
			)
		);

		/**
		 * Route to mark a single form entry as read.
		 *
		 * @return void
		 * @since 1.16.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<form_id>[\d]+)/entries/(?P<entry_id>[\d]+)/mark-read',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'mark_form_entry_read',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms'),
				),
			)
		);

		/**
		 * Route to update the status of a single form entry.
		 *
		 * @return void
		 * @since 1.16.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<form_id>[\d]+)/entries/(?P<entry_id>[\d]+)/update-status',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'update_form_entry_status',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms'),
				),
			)
		);

		/**
		 * Route to delete one or more form entries.
		 *
		 * @return void
		 * @since 1.16.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<form_id>[\d]+)/entries/delete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_form_entries',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms_delete'),
				),
			)
		);

		/**
		 * Route to duplicate a form
		 *
		 * @return void
		 *  @since 1.16.2
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'duplicate_form',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms'),
				),
			)
		);

		/**
		 * Route to save unsaved form state for preview (transient-based, like MailPoet).
		 *
		 * @return void
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/preview-editor/(?P<form_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'preview_editor',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_forms'),
				),
			)
		);

		/**
		 * Route to get all form templates
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import-form-template/(?P<form_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'import_form_template',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);


		/**
		 * Route for get id and body
		 *
		 * @return void
		 * @since 1.0.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<action>[a-zA-Z0-9-_]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_form_list_by_search',
					),
					'permission_callback' => PermissionManager::current_user_is_mint_user(),
				),
			)
		);
	}
}
