<?php
/**
 * Class WooCommerceCartItemRestored
 *
 * Handles the WooCommerce "woocommerce_cart_item_restored" hook.
 * This class is responsible for handling actions related to restoring previously removed items in the cart.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.5.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Hooks\WooCommerce;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;

/**
 * Class WooCommerceCartItemRestored
 *
 * Handles the WooCommerce "woocommerce_cart_item_restored" hook.
 * This class is responsible for handling actions related to restoring previously removed items in the cart.
 */
class WooCommerceCartItemRestored {

	/**
	 * WooCommerceCartItemRestored constructor.
	 *
	 * Initializes the WooCommerceCartItemRestored class by setting up the action hook for "woocommerce_cart_item_restored".
	 *
	 * @param string $key The key associated with the action hook.
	 *
	 * @since 1.5.0
	 */
	public function __construct( $key ) {
		add_action( $key, array( $this, 'handle_cart_item_restored' ), 10, 2 );
	}

	/**
	 * Handles the action triggered by the "woocommerce_cart_item_restored" hook.
	 *
	 * This method is called when a previously removed item is restored to the cart.
	 * It receives the cart item key and the cart instance as parameters.
	 *
	 * @param string $cart_item_key The key of the cart item being restored.
	 * @param object $cart          The cart instance.
	 *
	 * @since 1.5.0
	 */
	public function handle_cart_item_restored( $cart_item_key, $cart ) {
		$settings = CartCommon::get_abandoned_cart_settings();

		// Check if abandoned cart feature is disabled in settings.
		if ( isset( $settings['enable'] ) && !$settings['enable'] ) {
			return;
		}

		// Check if the user ID is not 0 (indicating a valid user).
		$email   = '';
		$user    = wp_get_current_user();
		$user_id = ( isset( $user->ID ) ? (int) $user->ID : 0 );
		if ( $user_id ) {
			$user_data = get_userdata( $user_id );
			$email     = $user_data->user_email;
		}

		if ( is_null( WC()->cart ) ) return;

		CartModel::insert_or_update(
			array(
				'user_id'     => $user_id,
				'email'       => $email,
				'items'       => maybe_serialize( WC()->cart->get_cart() ),
				'provider'    => 'WC',
				'currency'    => get_woocommerce_currency(),
				'status'      => 'pending',
				'session_key' => WC()->session->get_customer_id(),
			)
		);
	}
}


