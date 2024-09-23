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

use Mint\MRM\Admin\API\Controllers\TagController;
use WP_REST_Server;

/**
 * [Manage Tag Module related API callbacks]
 *
 * @desc Manage Tag Module related API callbacks
 * @package /app/API/Routes
 * @since 1.0.0
 */
class TagRoute extends AdminRoute {

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $rest_base = 'tags';

	/**
	 * TagController class object
	 *
	 * @var TagController
	 * @since 1.0.0
	 */
	protected $controller;

	/**
	 * Constructor function for creating a new instance of the class.
	 * 
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->controller = new TagController();
	}


	/**
	 * Register API endpoints routes for tags module
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {

		/**
		 * Create Tag endpoint
		 * Read Tag endpoint
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'create_or_update' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_all' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				)
			)
		);

		/**
		 * Delete Tag endpoint
		 *
		 * @since 1.8.2
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_all' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		/**
		 * Tag update endpoint
		 * Tag delete endpoint
		 * Tag read endpoind
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<tag_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this->controller, 'create_or_update' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'tag_id' => array(
							'type'     			=> 'integer',
							'required' 			=> true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * Tag delete endpoint
		 *
		 * @since 1.0.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<tag_id>[\d]+)/delete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'delete_single' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'tag_id' => array(
							'type'     			=> 'integer',
							'required' 			=> true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/select',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_tags_for_dropdown' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);
	}

}
