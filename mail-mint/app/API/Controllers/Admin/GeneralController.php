<?php
/**
 * General Controller Class
 *
 * Handles requests to the field groups endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use MRM\Common\MrmCommon;

/**
 * This is the main class that controls contact groups count.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class GeneralController {

	use Singleton;

	/**
	 * Function used to handle paginated get and search requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_general_count( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$lists    = ContactGroupModel::get_all( 'lists' );
		$tags     = ContactGroupModel::get_all( 'tags' );
		$segments = ContactGroupModel::get_all( 'segments' );
		$contacts = ContactModel::get_all();

		$data = array(
			'total_contacts' => isset( $contacts['count'] ) ? $contacts['count'] : 0,
			'total_lists'    => isset( $lists['count'] ) ? $lists['count'] : 0,
			'total_tags'     => isset( $tags['count'] ) ? $tags['count'] : 0,
			'total_segments' => isset( $segments['count'] ) ? $segments['count'] : 0,
		);
		if ( isset( $lists ) && isset( $tags ) && isset( $contacts ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $data );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}

	/**
	 * User accessability check for REST API
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function rest_permissions_check() {
		if (!MrmCommon::rest_check_manager_permissions() ) {
            return new \WP_Error('MailMint_rest_cannot_edit', __('Sorry, you cannot edit this resource.', 'mrm'), ['status' => rest_authorization_required_code()]);
        }
		return true;
	}

	/**
	 * Prepare success response for REST API
	 *
	 * @param string $message Response success message.
	 * @param int    $code Response success code.
	 * @param mixed  $data Response success data.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_success_response( $message = '', $code = 0, $data = null ) {
		$response = array(
			'code'    => $code,
			'message' => $message,
			'data'    => $data,
		);

		return rest_ensure_response( $response );
	}


	/**
	 * Prepare error response for REST API
	 *
	 * @param string $message Response success message.
	 * @param int    $code Response success code.
	 * @param mixed  $wp_error Response success wp_error.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_error_response( $message = '', $code = 0, $wp_error = null ) {
		if ( 0 !== absint( $code ) ) {
			$this->response_code = $code;
		} elseif ( empty( $code ) ) {
			$this->response_code = 500;
		}

		$data = array();
		if ( $wp_error instanceof \WP_Error ) {
			$message = $wp_error->get_error_message();
			$data    = $wp_error->get_error_data();
		}

		return new \WP_Error(
			$this->response_code,
			$message,
			array(
				'status'     => $this->response_code,
				'error_data' => $data,
			)
		);
	}
	
}
