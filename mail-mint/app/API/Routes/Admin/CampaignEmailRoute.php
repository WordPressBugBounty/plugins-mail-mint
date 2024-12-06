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

use Mint\MRM\Admin\API\Controllers\CampaignEmailController;
use Mint\MRM\Utilities\Helper\PermissionManager;
use WP_REST_Server;

/**
 * [Manage Campaign email builder related API]
 *
 * @desc Manage Campaign email builder related API
 * @package /app/API/Routes
 * @since 1.0.0
 */
class CampaignEmailRoute {

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
	protected $rest_base = 'campaign';


	/**
	 * CampaignController class object
	 *
	 * @var object
	 * @since 1.0.0
	 */
	protected $controller;



	/**
	 * Register API endpoints routes for lists module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		$this->controller = CampaignEmailController::get_instance();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/email/create/',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_email',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/email/(?P<email_index>[\d]+)(?:/(?P<email_id>[\d]+))?',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/email/',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'create_new_campaign_email',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		/**
		 * Campaign send test email
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sendTest',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'send_test_email',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		// File send api.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/mediaUpload',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'upload_media',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/email-builder/(?P<email_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_email_builder_data',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/email/template(?:/(?P<per_batch>[\d]+))?(?:/(?P<offset>[\d]+))?(?:/(?P<user_id>[\d]+))?(?:/(?P<template_id>[\d]+))?',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'save_campaign_email_template',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_email_templates',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/email/template(?:/(?P<per_batch>[\d]+))?(?:/(?P<offset>[\d]+))?(?:/(?P<user_id>[\d]+))?(?:/(?P<template_id>[\d]+))?/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_template',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/email/default-template/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_default_email_templates',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);
	}

}
