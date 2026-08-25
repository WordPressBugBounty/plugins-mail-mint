<?php
/**
 * Class WooCommerceAddToCart
 *
 * Handles the WooCommerce "woocommerce_add_to_cart" hook.
 * This class is responsible for handling actions related to adding items to the cart.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.5.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Hooks\WooCommerce;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;

/**
 * Class WooCommerceAddToCart
 *
 * Handles the WooCommerce "woocommerce_add_to_cart" hook.
 */
class WooCommerceAddToCart {

	/**
	 * WooCommerceAddToCart constructor.
	 *
	 * Initializes the WooCommerceAddToCart class by setting up the action hook for "woocommerce_add_to_cart".
	 *
	 * @param string $key The key associated with the action hook.
	 *
	 * @since 1.5.0
	 */
	public function __construct( $key ) {
		add_action( $key, array( $this, 'handle_add_to_cart_action' ), 10, 6 );
	}

	/**
	 * Handles the action triggered by the "woocommerce_add_to_cart" hook.
	 *
	 * This method is called when an item is added to the cart. It receives parameters related to the cart item
	 * such as the cart ID, product ID, quantity, variation ID, variation data, and additional cart item data.
	 *
	 * @param int   $cart_id          The ID of the cart.
	 * @param int   $product_id       The ID of the product being added to the cart.
	 * @param int   $request_quantity The requested quantity of the product being added.
	 * @param int   $variation_id     The ID of the product variation, if applicable.
	 * @param array $variation        The data of the product variation, if applicable.
	 * @param array $cart_item_data   Additional cart item data.
	 *
	 * @since 1.5.0
	 */
	public function handle_add_to_cart_action( $cart_id, $product_id, $request_quantity, $variation_id, $variation, $cart_item_data ) {
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

