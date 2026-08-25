<?php
/**
 * Class WooCommerceCouponRemoved
 *
 * Handles the WooCommerce "woocommerce_removed_coupon" hook.
 * Keeps the tracked cart's coupon/discount meta in sync when a coupon is removed
 * on the cart page, not just at checkout.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Hooks\WooCommerce;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;

/**
 * Class WooCommerceCouponRemoved
 *
 * Handles the WooCommerce "woocommerce_removed_coupon" hook.
 */
class WooCommerceCouponRemoved {

	/**
	 * WooCommerceCouponRemoved constructor.
	 *
	 * @param string $key The key associated with the action hook.
	 *
	 * @since 1.31.0
	 */
	public function __construct( $key ) {
		add_action( $key, array( $this, 'handle_coupon_removed' ), 10, 1 );
	}

	/**
	 * Handles the action triggered by the "woocommerce_removed_coupon" hook.
	 *
	 * Only refreshes meta for a cart that's already being tracked — removing a coupon
	 * on its own is not a signal to start tracking a previously-untracked visitor.
	 *
	 * @param string $coupon_code The coupon code that was removed.
	 *
	 * @since 1.31.0
	 */
	public function handle_coupon_removed( $coupon_code ) {
		$settings = CartCommon::get_abandoned_cart_settings();

		if ( isset( $settings['enable'] ) && ! $settings['enable'] ) {
			return;
		}

		if ( is_null( WC()->session ) ) {
			return;
		}

		$session_key = WC()->session->get_customer_id();
		$cart_detail = CartModel::get_cart_details_by_key_and_status( 'session_key', $session_key, array( 'pending' ) );
		$cart_id     = isset( $cart_detail['id'] ) ? $cart_detail['id'] : 0;

		if ( ! $cart_id ) {
			return;
		}

		if ( ! is_null( WC()->cart ) ) {
			CartModel::update(
				array(
					'items'      => maybe_serialize( WC()->cart->get_cart() ),
					'updated_at' => current_time( 'mysql' ),
				),
				$cart_id
			);
		}

		CartModel::update_cart_meta(
			$cart_id,
			'abandoned_cart_meta',
			maybe_serialize( CartCommon::get_current_cart_details( WC()->session ) )
		);
	}
}
