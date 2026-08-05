<?php
/**
 * Segment REST Route (Free)
 *
 * Handles API route registration for segment field-types and preview contact filtering.
 *
 * @package Mint\MRM\Admin\API\Routes
 * @since 1.25.0
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\SegmentController;
use WP_REST_Server;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * Handle Segment module field-types and contact filter preview endpoints.
 *
 * @package Mint\MRM\Admin\API\Routes
 * @since 1.25.0
 */
class SegmentRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.25.0
	 */
	protected $rest_base = 'segments';

	/**
	 * SegmentController instance.
	 *
	 * @var SegmentController
	 */
	protected $controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller = new SegmentController();
	}

	/**
	 * Register API routes for segment filtering and field types.
	 *
	 * @return void
	 * @since 1.25.0
	 */
	public function register_routes() {
		// Get current segment contacts preview.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/contacts',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(
						$this->controller,
						'get_preview_contacts',
					),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
				),
			)
		);

		// Get segmentation field types.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/field-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(
						$this->controller,
						'retrieve_available_field_types',
					),
					'permission_callback' => PermissionManager::current_user_can( 'mint_read_contacts' ),
				),
			)
		);
	}
}
