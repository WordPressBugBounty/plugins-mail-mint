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
use Mint\MRM\Utilities\Helper\PermissionManager;
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_all',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns_delete'),
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns_delete'),
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
				),

			)
		);

		/**
		 * Resolve the next scheduled run time for a recurring campaign.
		 *
		 * @since 1.24.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/recurring/next-run',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_recurring_next_run',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		/**
		 * List the per-occurrence run history for a recurring campaign.
		 *
		 * @since 1.24.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/recurring/runs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_recurring_runs',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		/**
		 * Toggle whether a campaign appears in the public archive.
		 *
		 * @since 1.24.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/archive-visibility',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'update_archive_visibility',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
				),
			)
		);

		/**
		 * Update the social share-bar visibility for an archived campaign.
		 *
		 * @since 1.25.0
		*/
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/share-visibility',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(
						$this->controller,
						'update_share_visibility',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
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
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
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
					'permission_callback' => PermissionManager::current_user_can('mint_manage_campaigns'),
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
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),

			)
		);

		/**
		 * Campaign progress endpoint
		 *
		 * @since 1.18.10
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<campaign_id>[\d]+)/progress',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'get_progress',
					),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/search',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_campaign_by_name'),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/for-segment',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_campaigns_for_segment' ),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<campaign_id>[\d]+)/urls',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array($this->controller, 'get_urls_from_campaign'),
					'permission_callback' => PermissionManager::current_user_can('mint_read_campaigns'),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/recipient-lists',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_recipient_lists_for_campaign' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/recipient-tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_recipient_tags_for_campaign' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/recipient-segments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_recipient_segments_for_campaign' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

		/**
		 * Get URLs from a campaign email for the Campaign Actions feature.
		 * Pro extends this via the 'mint_campaign_clicked_links' filter.
		 *
		 * @since 1.24.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<campaign_id>[\d]+)/clicked-links',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_clicked_links' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
				),
			)
		);

		/**
		 * Apply/remove tags to contacts filtered by email interaction.
		 * Requires Pro plugin — dispatches via 'mint_campaign_do_actions' filter.
		 *
		 * @since 1.24.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<campaign_id>[\d]+)/campaign-actions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'do_campaign_actions' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns' ),
				),
			)
		);

	}

}
