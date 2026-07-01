<?php
/**
 * Mail Mint
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since 1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Provides lazy-loaded integration data via REST API instead of wp_localize_script.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since 1.20.0
 */
class IntegrationDataController extends AdminBaseController {

	public function rest_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	public function get_post_types( WP_REST_Request $request ): WP_REST_Response {
		return $this->get_success_response( '', 200, MrmCommon::get_all_post_types() );
	}

	public function get_tutor_courses( WP_REST_Request $request ): WP_REST_Response {
		$data = HelperFunctions::get_tutor_lms_courses();
		return $this->get_success_response( '', 200, $data ?: array() );
	}

	public function get_tutor_lessons( WP_REST_Request $request ): WP_REST_Response {
		$data = HelperFunctions::get_tutor_lms_lessons();
		return $this->get_success_response( '', 200, $data ?: array() );
	}

	public function get_lifter_courses( WP_REST_Request $request ): WP_REST_Response {
		$data = HelperFunctions::get_lifter_lms_courses();
		return $this->get_success_response( '', 200, $data ?: array() );
	}

	public function get_lifter_memberships( WP_REST_Request $request ): WP_REST_Response {
		$data = HelperFunctions::get_lifter_lms_memberships();
		return $this->get_success_response( '', 200, $data ?: array() );
	}

	public function get_contact_custom_fields( WP_REST_Request $request ): WP_REST_Response {
		return $this->get_success_response( '', 200, MrmCommon::get_contact_custom_fields() );
	}

	public function get_buddypress_groups( WP_REST_Request $request ): WP_REST_Response {
		$data = HelperFunctions::get_buddypress_groups();
		return $this->get_success_response( '', 200, $data ?: array() );
	}

	public function get_buddypress_member_types( WP_REST_Request $request ): WP_REST_Response {
		$data = HelperFunctions::get_buddypress_member_types();
		return $this->get_success_response( '', 200, $data ?: array() );
	}
}
