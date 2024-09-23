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

use Mint\MRM\Admin\API\Controllers\CampaignController;
use WP_REST_Server;

/**
 * [Manage Campaign related API]
 *
 * @desc Manage Campaign related API
 * @package /app/API/Routes
 * @since 1.0.0
 */
class CampaignRoute {

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
	protected $rest_base = 'campaigns';


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
		$this->controller = CampaignController::get_instance();

		/**
		 * Campaign multiple interaction endpoints
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Register the REST API route to delete multiple campaigns.
		 *
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
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
		 * Campaign single interaction endpoints
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_single',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'create_or_update',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),
			)
		);

		/**
		 * Register the REST API route to delete a campaign.
		 *
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_single',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),

			)
		);

		/**
		 * Update campaign status
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/status-update',
			array(

				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'status_update',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),

			)
		);

		/**
		 * Delete a campaign email
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/email/(?P<email_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'delete_campaign_email',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),

			)
		);

		// Get subscriber lists from tag/list ids
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/subscribers/(?P<campaign_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_subscribers',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),

			)
		);

		// Campaign duplication endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/duplicate/(?P<campaign_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'rest_campaign_duplicate_callback',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),

			)
		);

		// Campaign Notice remove endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/hide-smtp-notice/',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'hide_smtp_notice',
					),
					'permission_callback' => array(
						$this->controller,
						'rest_permissions_check',
					),
				),

			)
		);
	}

}
