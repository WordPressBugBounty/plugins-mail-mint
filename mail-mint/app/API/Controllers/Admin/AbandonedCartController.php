<?php
/**
 * Mail Mint
 *
 * Handles the abandoned cart list and delete REST callbacks.
 *
 * @package /app/API/Controllers/Admin
 * @since   1.31.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;
use MRM\Common\MrmCommon;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages abandoned cart API callbacks.
 *
 * @package /app/API/Controllers/Admin
 * @since 1.31.0
 */
class AbandonedCartController extends AdminBaseController {

	/**
	 * Retrieve the recoverable carts.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.31.0
	 */
	public function get_recoverable_carts( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$result = CartModel::get_all_abandoned_cart( $params, 'pending,abandoned' );

		/**
		 * Fires after the recoverable carts have been queried.
		 *
		 * @param array $params Query parameters from the request.
		 * @since 1.31.0
		 */
		do_action( 'mailmint_abandoned_cart_get_recoverable_carts', $params );

		if ( isset( $result ) ) {
			return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $result );
		}

		return $this->get_error_response( __( 'Failed to get data.', 'mrm' ), 400 );
	}

	/**
	 * Retrieve the recovered carts.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.31.0
	 */
	public function get_recovered_carts( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$result = CartModel::get_all_abandoned_cart( $params, 'recovered' );

		if ( isset( $result ) ) {
			return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $result );
		}

		return $this->get_error_response( __( 'Failed to get data.', 'mrm' ), 400 );
	}

	/**
	 * Retrieve the lost carts.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.31.0
	 */
	public function get_losts_carts( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$result = CartModel::get_all_abandoned_cart( $params, 'lost' );

		if ( isset( $result ) ) {
			return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $result );
		}

		return $this->get_error_response( __( 'Failed to get data.', 'mrm' ), 400 );
	}

	/**
	 * Retrieve a single abandoned cart with its checkout preview.
	 *
	 * Powers the cart details page, which is deep-linkable and therefore cannot rely
	 * on the row data already loaded by a list request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.31.0
	 */
	public function get_single_cart( WP_REST_Request $request ) {
		$params       = MrmCommon::get_api_params_values( $request );
		$abandoned_id = isset( $params['abandoned_id'] ) ? absint( $params['abandoned_id'] ) : 0;

		if ( ! $abandoned_id ) {
			return $this->get_error_response( __( 'Invalid cart ID.', 'mrm' ), 400 );
		}

		$result = CartModel::get_single_abandoned_cart( $abandoned_id );

		if ( empty( $result ) ) {
			return $this->get_error_response( __( 'Cart not found.', 'mrm' ), 404 );
		}

		return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $result );
	}

	/**
	 * Delete a single abandoned cart.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.31.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['abandoned_id'] ) ) {
			$success = CartModel::delete( $params['abandoned_id'] );
			if ( $success ) {
				return $this->get_success_response( __( 'Abandoned Cart has been deleted successfully.', 'mrm' ), 200 );
			}
		}

		return $this->get_error_response( __( 'Failed to delete.', 'mrm' ), 400 );
	}

	/**
	 * Delete multiple abandoned carts.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.31.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['abandoned_ids'] ) ) {
			$success = CartModel::destroy_all( $params['abandoned_ids'] );
			if ( $success ) {
				return $this->get_success_response( __( 'Abandoned carts has been deleted successfully.', 'mrm' ), 200 );
			}
		}

		return $this->get_error_response( __( 'Failed to delete.', 'mrm' ), 400 );
	}
}
