<?php
/**
 * Mail Mint
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.16.0
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\OnboardingController;
use Mint\MRM\Utilities\Helper\PermissionManager;

/**
 * Register onboarding-specific REST API routes.
 *
 * @package Mint\MRM\Admin\API\Routes
 * @since   1.16.0
 */
class OnboardingRoute {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 * @since 1.16.0
	 */
	protected $namespace = 'mrm/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 * @since 1.16.0
	 */
	protected $rest_base = 'onboarding';

	/**
	 * OnboardingController instance.
	 *
	 * @var OnboardingController
	 * @since 1.16.0
	 */
	protected $controller;

	/**
	 * Register REST API routes for onboarding.
	 *
	 * @since 1.16.0
	 */
	public function register_routes() {
		$this->controller = OnboardingController::get_instance();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/send-test-email',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'send_test_email' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/accept-consent',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'accept_consent' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/setup-data',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_setup_data' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/wizard-status',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'update_wizard_status' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'status' => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'pending', 'dismissed', 'hidden' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/setup-issues',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_setup_issues' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/setup-issues/dismiss',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'dismiss_setup_issues' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'keys' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'string' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/host-mail-confirmed',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'confirm_host_mail' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
					'args'                => array(
						'confirmed' => array(
							'required' => true,
							'type'     => 'boolean',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/complete',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'complete_wizard' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/getting-started',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this->controller, 'get_getting_started' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/getting-started/hide',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this->controller, 'hide_getting_started' ),
					'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
				),
			)
		);
	}
}
