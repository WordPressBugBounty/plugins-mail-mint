<?php
/**
 * REST API Base Controller
 *
 * Core base controller for managing and interacting with REST API items.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\API\Controllers;

use WP_REST_Request;
use MRM\Common\MrmCommon;

/**
 * This is the core class that defines abstract function for child controllers
 */
abstract class BaseController extends \WP_REST_Controller {

	/**
	 * REST API response code
	 *
	 * @var integer
	 * @since 1.0.0
	 */
	private $response_code = 200;

	/**
	 * Prepare success response for REST API
	 *
	 * @param string $message Response success message.
	 * @param int    $code Response success code.
	 * @param mixed  $data Response data on success.
	 *
	 * @return array|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
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
	 * Prepare success response with data for REST API
	 *
	 * @param array $response Array of settings information.
	 *
	 * @return array|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_success_response_data( $response ) {
		$response['success'] = true;
		return rest_ensure_response( $response );
	}


	/**
	 * Prepare error response for REST API
	 *
	 * @param string $message Response error message.
	 * @param int    $code Response error code.
	 * @param mixed  $wp_error Response data on error.
	 *
	 * @return \WP_Error
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


	/**
	 * Default permission_callback
	 *
	 * @return bool
	 */
	abstract public function rest_permissions_check();

}
