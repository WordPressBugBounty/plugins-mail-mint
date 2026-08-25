<?php
/**
 * Class WooCommerceCouponApplied
 *
 * Handles the WooCommerce "woocommerce_applied_coupon" hook.
 * Keeps the tracked cart's coupon/discount meta in sync when a coupon is applied
 * on the cart page, not just at checkout.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Hooks\WooCommerce;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;

/**
 * Class WooCommerceCouponApplied
 *
 * Handles the WooCommerce "woocommerce_applied_coupon" hook.
 */
class WooCommerceCouponApplied {

	/**
	 * WooCommerceCouponApplied constructor.
	 *
	 * @param string $key The key associated with the action hook.
	 *
	 * @since 1.31.0
	 */
	public function __construct( $key ) {
		add_action( $key, array( $this, 'handle_coupon_applied' ), 10, 1 );
	}

	/**
	 * Handles the action triggered by the "woocommerce_applied_coupon" hook.
	 *
	 * Fires on both the cart page and the checkout page. The checkout page's own JS
	 * capture already refreshes this meta via `updated_checkout`, so this hook is what
	 * closes the gap for coupons applied on the cart page, where no such event exists.
	 *
	 * @param string $coupon_code The coupon code that was applied.
	 *
	 * @since 1.31.0
	 */
	public function handle_coupon_applied( $coupon_code ) {
		$settings = CartCommon::get_abandoned_cart_settings();

		if ( isset( $settings['enable'] ) && ! $settings['enable'] ) {
			return;
		}

		if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
			return;
		}

		$email   = '';
		$user    = wp_get_current_user();
		$user_id = ( isset( $user->ID ) ? (int) $user->ID : 0 );
		if ( $user_id ) {
			$user_data = get_userdata( $user_id );
			$email     = $user_data->user_email;
		}

		$session_key = WC()->session->get_customer_id();

		// insert_or_update() returns an insert ID on create but an affected-rows count on
		// update, so the cart ID is always resolved by a fresh lookup rather than trusted
		// from its return value.
		CartModel::insert_or_update(
			array(
				'user_id'     => $user_id,
				'email'       => $email,
				'items'       => maybe_serialize( WC()->cart->get_cart() ),
				'provider'    => 'WC',
				'status'      => 'pending',
				'session_key' => $session_key,
			)
		);

		$cart_detail = CartModel::get_cart_details_by_key_and_status( 'session_key', $session_key, array( 'pending' ) );
		$cart_id     = isset( $cart_detail['id'] ) ? $cart_detail['id'] : 0;

		if ( ! $cart_id ) {
			return;
		}

		CartModel::update_cart_meta(
			$cart_id,
			'abandoned_cart_meta',
			maybe_serialize( CartCommon::get_current_cart_details( WC()->session ) )
		);
	}
}
