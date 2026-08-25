<?php
/**
 * Class WooCommerceNewOrder
 *
 * Handles the WooCommerce "woocommerce_new_order" hook.
 * This class is responsible for handling actions related to new orders.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.5.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Hooks\WooCommerce;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;
use Mint\MRM\Internal\AbandonedCart\Helper\CartRecovery;

/**
 * Class WooCommerceNewOrder
 *
 * Handles the WooCommerce "woocommerce_new_order" hook.
 *
 * Catches the customer who buys before their cart was ever considered abandoned. Its job
 * is to record the order against the cart and, when the order already counts as a sale,
 * mark the cart recovered — never to delete the row.
 */
class WooCommerceNewOrder {

	/**
	 * WooCommerceNewOrder constructor.
	 *
	 * Initializes the WooCommerceNewOrder class by setting up the action hook for "woocommerce_new_order".
	 *
	 * @param string $key The key associated with the action hook.
	 *
	 * @since 1.5.0
	 */
	public function __construct( $key ) {
		add_action( $key, array( $this, 'handle_new_order' ), 10, 2 );
	}

	/**
	 * Handles the action triggered by the "woocommerce_new_order" hook.
	 *
	 * This method is called when a new order is created.
	 * It receives the order ID and the order instance as parameters.
	 *
	 * @param int    $order_id The ID of the new order.
	 * @param object $order    The order instance.
	 *
	 * @since 1.5.0
	 */
	public function handle_new_order( $order_id, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		// Third parties fire woocommerce_new_order with all sorts of second arguments,
		// and the block checkout fires it for a draft order that has no billing email yet.
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$cart_details = CartModel::find_open_cart_for_order( $order );

		if ( empty( $cart_details['id'] ) ) {
			return;
		}

		$cart_id = (int) $cart_details['id'];

		/*
		 * Record the order against the cart whatever its payment state. Attribution should
		 * not depend on how the order eventually resolves.
		 */
		CartModel::update_cart_meta( $cart_id, 'order_id', $order->get_id() );

		if ( CartCommon::is_win_order_status( $order->get_status() ) ) {
			CartRecovery::mark_recovered( $cart_details, $order, CartRecovery::SOURCE_FAST_PURCHASE );
			return;
		}

		/*
		 * The order exists but has not reached a status the store counts as a sale — an
		 * offline gateway, an off-site redirect that has not called back, a declined card.
		 * Leave the status alone and let WooCommerceOrderStatusChanged recover the cart if
		 * and when payment lands. CartGate stops the emails in the meantime, which is why
		 * this method no longer needs to do anything destructive.
		 *
		 * It previously deleted the row outright when the order arrived inside the wait
		 * window. That destroyed the analytics for the fastest-converting customers and,
		 * worse, defeated the send guard downstream: a missing row could not be read, so
		 * the guard let the queued email through and the customer was chased for a cart
		 * they had already paid for.
		 */
	}
}


