<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2023-06-19 11:03:17
 * @modify date 2023-06-19 11:03:17
 * @package /app/API/Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\GeneralFieldController;
use WP_REST_Server;

/**
 * GeneralFieldRoute class.
 *
 * This class represents the REST API route for managing general fields in the Mint MRM Admin area.
 * It handles registering the route and defining the callback for retrieving all general fields.
 * 
 * @since 1.5.0
 */
class GeneralFieldRoute extends AdminRoute {

	/**
	 * The base path for the REST API route.
	 *
	 * @var string
	 * @since 1.5.0
	 */
	protected $rest_base = 'settings/general-fields';

	/**
	 * The controller instance for handling the route callbacks.
	 *
	 * @var GeneralFieldController
	 * @since 1.5.0
	 */
	protected $controller;

		/**
	 * Initializes a new instance of the GeneralFieldRoute class.
	 * @since 1.5.0
	 */
	public function __construct() {
		$this->controller = new GeneralFieldController();
	}


	/**
	 * Registers the REST API routes for the GeneralFieldRoute class.
	 * @since 1.5.0
	 * @return void
	 */
	public function register_routes() {

		/**
		 * Register the REST API route for retrieving all general fields.
		 *
		 * @param string   $namespace    The namespace for the REST route.
		 * @param string   $rest_base    The base path for the REST route.
		 * @param array    $route_args   Additional arguments for the REST route.
		 *   - methods             (string|array) The HTTP methods supported by the route.
		 *   - callback            (callable) The callback function to handle the request.
		 *   - permission_callback (callable) The callback function to check the permission for accessing the route.
		 * @since 1.5.0
		 */
		register_rest_route(
			$this->namespace,
			$this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_all' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		/**
		 * Field group update endpoint
		 *
		 * @since 1.5.0
		*/
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/(?P<slug>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_single' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'slug' => array(
							'type'     			=> 'string',
							'required' 			=> true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this->controller, 'create_or_update' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'slug' => array(
							'type'     			=> 'string',
							'required' 			=> true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

}
