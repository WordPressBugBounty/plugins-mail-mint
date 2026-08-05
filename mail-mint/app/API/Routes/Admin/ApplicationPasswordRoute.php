<?php
/**
 * Application Password Route
 *
 * Registers the application-password management endpoints used by the MCP
 * "Connect a client" flow, at /mrm/v1/settings/mcp/app-passwords.
 *
 * @package Mint\MRM\Admin\API\Routes
 */

namespace Mint\MRM\Admin\API\Routes;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Admin\API\Controllers\ApplicationPasswordController;
use Mint\MRM\Utilities\Helper\PermissionManager;

class ApplicationPasswordRoute {

	protected $namespace = 'mrm/v1';
	protected $rest_base = 'settings';

	public function register_routes(): void {
		$controller = ApplicationPasswordController::get_instance();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/mcp/app-passwords',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $controller, 'get' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $controller, 'create_or_update' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
					'args'                => array(
						'name' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/mcp/app-passwords/(?P<uuid>[A-Za-z0-9\-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $controller, 'revoke' ),
					'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
					'args'                => array(
						'uuid' => array(
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => function ( $value ) {
								return is_string( $value ) && (bool) preg_match( '/^[A-Za-z0-9\-]+$/', $value );
							},
						),
					),
				),
			)
		);
	}
}
