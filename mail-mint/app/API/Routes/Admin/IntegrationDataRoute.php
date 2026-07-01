<?php
/**
 * Mail Mint
 *
 * @package /app/API/Routes
 * @since 1.0.0
 */

namespace Mint\MRM\Admin\API\Routes;

use Mint\MRM\Admin\API\Controllers\IntegrationDataController;
use Mint\MRM\Utilities\Helper\PermissionManager;
use WP_REST_Server;

/**
 * REST endpoints for integration data that is lazily loaded by the frontend
 * instead of being dumped into wp_localize_script on every page load.
 *
 * @package Mint\MRM\Admin\API\Routes
 * @since 1.20.0
 */
class IntegrationDataRoute extends AdminRoute {

	protected $rest_base = 'integration-data';

	protected $controller;

	public function __construct() {
		$this->controller = new IntegrationDataController();
	}

	public function register_routes() {
		$readable = array( 'methods' => WP_REST_Server::READABLE );
		$perm     = PermissionManager::current_user_can( 'mint_read_contacts' );

		$endpoints = array(
			'post-types'           => 'get_post_types',
			'tutor-courses'        => 'get_tutor_courses',
			'tutor-lessons'        => 'get_tutor_lessons',
			'lifter-courses'       => 'get_lifter_courses',
			'lifter-memberships'   => 'get_lifter_memberships',
			'contact-custom-fields' => 'get_contact_custom_fields',
			'buddypress-groups'       => 'get_buddypress_groups',
			'buddypress-member-types' => 'get_buddypress_member_types',
		);

		foreach ( $endpoints as $path => $method ) {
			register_rest_route(
				$this->namespace,
				$this->rest_base . '/' . $path,
				array(
					array_merge( $readable, array(
						'callback'            => array( $this->controller, $method ),
						'permission_callback' => $perm,
					) ),
				)
			);
		}
	}
}
