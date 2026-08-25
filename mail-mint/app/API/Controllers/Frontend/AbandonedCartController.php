<?php
/**
 * Mail Mint
 *
 * Storefront callbacks for abandoned cart tracking: capturing checkout field data
 * as the customer types, and handling the GDPR opt-out.
 *
 * These endpoints are public by necessity — guests are the primary audience for cart
 * tracking — so writes are bound to the caller's own WooCommerce session rather than
 * to whatever email address they submit.
 *
 * @package /app/API/Controllers/Frontend
 * @since   1.31.0
 */

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;
use Mint\MRM\API\Controllers\BaseController;
use MRM\Common\MrmCommon;
use WC_Session_Handler;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the storefront abandoned cart API callbacks.
 *
 * @package /app/API/Controllers/Frontend
 * @since 1.31.0
 */
class AbandonedCartController extends BaseController {

	/**
	 * Capture checkout field data against the caller's cart.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 * @since 1.31.0
	 */
	public function update_checkout( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( ! MrmCommon::is_wc_active() ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'WooCommerce is not active.', 'mrm' ),
				)
			);
		}

		$settings = CartCommon::get_abandoned_cart_settings();
		if ( empty( $settings['enable'] ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'Cart tracking is disabled.', 'mrm' ),
				)
			);
		}

		// The visitor opted out of cart tracking via the GDPR consent notice.
		if ( isset( $_COOKIE['mint_ab_cart_skip_track'] ) && 'yes' === $_COOKIE['mint_ab_cart_skip_track'] ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Cart tracking has been opted out successfully.', 'mrm' ),
				)
			);
		}

		$woocommerce          = WC();
		$woocommerce->session = new WC_Session_Handler();
		$woocommerce->session->init();

		$session_key  = $woocommerce->session->get_customer_id();
		$cart_totals  = $woocommerce->session->cart_totals;
		$meta_details = CartCommon::get_current_cart_details( $woocommerce->session );

		$cart_email = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';

		/*
		 * Checkout fields are captured as the customer types, so this endpoint is called
		 * with half-finished addresses ("j@", "jo@gm") on the way to a valid one.
		 * sanitize_email() does not reject those. Discard anything that isn't a real
		 * address and keep tracking the cart anonymously — the next keystroke will send a
		 * better value. Returning an error instead would surface a failed request on a
		 * live checkout page for what is completely normal typing.
		 */
		if ( ! empty( $cart_email ) && ! is_email( $cart_email ) ) {
			$cart_email = '';
		}

		if ( $this->is_role_excluded( $cart_email, $settings ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'User role is disabled to track abandoned cart.', 'mrm' ),
				)
			);
		}

		if ( CartCommon::is_email_blacklisted( $cart_email ) ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Email is blacklisted from cart tracking.', 'mrm' ),
				)
			);
		}

		/*
		 * Resolve the row by session first.
		 *
		 * Looking up by the submitted email would let any caller target — and overwrite —
		 * another customer's pending cart simply by knowing their address. The email is
		 * only trusted as a fallback when it belongs to the logged-in user making the call.
		 */
		$cart_details = CartModel::get_cart_details_by_key_and_status( 'session_key', $session_key, array( 'pending' ) );

		if ( empty( $cart_details ) && $cart_email && $this->owns_email( $cart_email ) ) {
			$cart_details = CartModel::get_cart_details_by_key_and_status( 'email', $cart_email, array( 'pending' ) );
		}

		$cart_id       = isset( $cart_details['id'] ) ? $cart_details['id'] : null;
		$checkout_data = maybe_serialize(
			array(
				'fields' => isset( $params['checkout_fields_data'] ) ? json_decode( $params['checkout_fields_data'], true ) : array(),
			)
		);

		if ( ! $cart_id ) {
			$user    = wp_get_current_user();
			$user_id = isset( $user->ID ) ? (int) $user->ID : 0;

			if ( $user_id ) {
				$user_data  = get_userdata( $user_id );
				$cart_email = $user_data->user_email;
			}

			$data = array(
				'user_id'  => $user_id,
				'email'    => $cart_email,
				'items'    => maybe_serialize( $woocommerce->session->cart ),
				'provider' => 'WC',
				'currency' => get_woocommerce_currency(),
				'status'   => 'pending',
			);

			$data                  = CartCommon::get_abandoned_cart_totals( $data );
			$data['token']         = CartCommon::create_abandoned_cart_token( 32 );
			$data['session_key']   = $session_key;
			$data['created_at']    = current_time( 'mysql' );
			$data['checkout_data'] = $checkout_data;

			$cart_id = CartModel::insert( $data );

			if ( ! $cart_id ) {
				return rest_ensure_response(
					array(
						'success' => false,
						'message' => __( 'Failed to track the cart.', 'mrm' ),
					)
				);
			}

			$this->store_cart_meta( $cart_id, $meta_details );
			CartModel::schedule_cart_jobs( $cart_id, $cart_email, $session_key );

			return rest_ensure_response(
				array(
					'success' => true,
					'message' => __( 'Cart has been tracked successfully.', 'mrm' ),
				)
			);
		}

		$cart_details['session_key']   = $session_key;
		$cart_details['total']         = isset( $cart_totals['total'] ) ? $cart_totals['total'] : 0;
		$cart_details['updated_at']    = current_time( 'mysql' );
		$cart_details['email']         = $cart_email ? $cart_email : $cart_details['email'];
		$cart_details['checkout_data'] = $checkout_data;

		CartModel::update( $cart_details, $cart_id );
		$this->store_cart_meta( $cart_id, $meta_details );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Checkout data has been updated.', 'mrm' ),
			)
		);
	}

	/**
	 * Opt the caller out of cart tracking and drop their pending cart.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return \WP_REST_Response
	 * @since 1.31.0
	 */
	public function delete_abandoned_cart( WP_REST_Request $request ) {
		if ( ! MrmCommon::is_wc_active() ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'WooCommerce is not active.', 'mrm' ),
				)
			);
		}

		$woocommerce          = WC();
		$woocommerce->session = new WC_Session_Handler();
		$woocommerce->session->init();

		$session_key  = $woocommerce->session->get_customer_id();
		$cart_details = CartModel::get_cart_details_by_key( 'session_key', $session_key, 'pending' );

		if ( ! empty( $cart_details['id'] ) ) {
			CartModel::delete( $cart_details['id'] );
		}

		/**
		 * Filters how long the cart tracking opt-out is remembered, in days.
		 *
		 * @param int $days Number of days. Default 7.
		 * @since 1.31.0
		 */
		$cookie_days = (int) apply_filters( 'mailmint_ab_cart_opt_out_cookie_validity', 7 );

		setcookie( 'mint_ab_cart_skip_track', 'yes', time() + ( DAY_IN_SECONDS * $cookie_days ), COOKIEPATH, COOKIE_DOMAIN );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Cart has been opted out successfully.', 'mrm' ),
			)
		);
	}

	/**
	 * Store the cart meta rows that accompany a captured cart.
	 *
	 * @param int   $cart_id      Abandoned cart ID.
	 * @param array $meta_details Serialisable cart meta (coupons, fees, shipping, tax).
	 *
	 * @return void
	 * @since 1.31.0
	 */
	private function store_cart_meta( $cart_id, $meta_details ) {
		CartModel::update_cart_meta( $cart_id, 'abandoned_cart_meta', maybe_serialize( $meta_details ) );

		$checkout_page_id = isset( $_COOKIE['mint_checkout_page_id'] )
			? absint( $_COOKIE['mint_checkout_page_id'] )
			: wc_get_page_id( 'checkout' );

		CartModel::update_cart_meta( $cart_id, 'checkout_page_id', $checkout_page_id );
	}

	/**
	 * Whether the given email belongs to an excluded user role.
	 *
	 * @param string $email    Email address to check.
	 * @param array  $settings Abandoned cart settings.
	 *
	 * @return bool
	 * @since 1.31.0
	 */
	private function is_role_excluded( $email, $settings ) {
		$disable_roles = isset( $settings['disable_roles'] ) ? $settings['disable_roles'] : array();

		if ( empty( $disable_roles ) || empty( $email ) ) {
			return false;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return false;
		}

		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $disable_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the caller is logged in as the owner of the given email address.
	 *
	 * @param string $email Email address to check.
	 *
	 * @return bool
	 * @since 1.31.0
	 */
	private function owns_email( $email ) {
		$user = wp_get_current_user();

		return $user && $user->exists() && strtolower( $user->user_email ) === strtolower( $email );
	}

	/**
	 * Default permission callback.
	 *
	 * Public by design: guests are the primary audience for cart tracking, so there is
	 * no capability to check. Writes are constrained to the caller's own WooCommerce
	 * session inside the callbacks.
	 *
	 * @return bool
	 * @since 1.31.0
	 */
	public function rest_permissions_check() {
		return true;
	}
}
