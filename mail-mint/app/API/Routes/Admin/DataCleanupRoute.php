<?php
/**
 * Data Cleanup REST API Routes
 *
 * Registers all /mrm/v1/data-cleanup/* endpoints.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\DataCleanupController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DataCleanupRoute
 *
 * Registers the four data-cleanup REST endpoints under the mrm/v1 namespace.
 * All endpoints require the manage_options capability.
 *
 * @package Mint\MRM\Admin\API\Routes
 * @since   1.0.0
 */
class DataCleanupRoute extends AdminRoute {

	/**
	 * Route base for all data-cleanup endpoints.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'data-cleanup';

	/**
	 * DataCleanupController instance.
	 *
	 * @since 1.0.0
	 * @var DataCleanupController
	 */
	protected $controller;

	/**
	 * Registers all data-cleanup REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		$this->controller = new DataCleanupController();

		// GET /mrm/v1/data-cleanup/preview
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/preview',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'preview' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'categories'     => array(
							'required'          => true,
							'type'              => 'array',
							'items'             => array(
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_key',
							),
							'description'       => __( 'Array of category keys to preview.', 'mrm' ),
						),
						'retention_days' => array(
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 30,
							'sanitize_callback' => 'absint',
							'description'       => __( 'Number of days of data to retain (minimum 30).', 'mrm' ),
						),
					),
				),
			)
		);

		// POST /mrm/v1/data-cleanup/start
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/start',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'start' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'action'         => array(
							'required'          => true,
							'type'              => 'string',
							'enum'              => array( 'export_then_delete', 'delete_only' ),
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Cleanup action: export_then_delete or delete_only.', 'mrm' ),
						),
						'categories'     => array(
							'required'          => true,
							'type'              => 'array',
							'minItems'          => 1,
							'items'             => array(
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_key',
							),
							'description'       => __( 'Array of category keys to clean up (at least one required).', 'mrm' ),
						),
						'retention_days' => array(
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 30,
							'sanitize_callback' => 'absint',
							'description'       => __( 'Number of days of data to retain (minimum 30).', 'mrm' ),
						),
					),
				),
			)
		);

		// GET /mrm/v1/data-cleanup/status
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'status' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		// POST /mrm/v1/data-cleanup/cancel
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cancel',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'cancel' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);
	}
}
