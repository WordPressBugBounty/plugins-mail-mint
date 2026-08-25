<?php
/**
 * Class WooCommerceOrderStatusChanged
 *
 * Handles the WooCommerce "woocommerce_order_status_changed" hook to mark a
 * tracked cart as recovered once the customer's order reaches a status the
 * store treats as a completed sale.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Hooks\WooCommerce;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;
use Mint\MRM\Internal\AbandonedCart\Helper\CartRecovery;

/**
 * Class WooCommerceOrderStatusChanged
 *
 * Detects cart recovery from the order lifecycle rather than from the checkout
 * request, so a cart still counts as recovered when the customer comes back and
 * buys days later, pays through an offline gateway, or has the order completed
 * manually by an admin.
 */
class WooCommerceOrderStatusChanged {

	/**
	 * The cart statuses that can still transition to recovered.
	 *
	 * `lost` is included deliberately: a cart the scheduler gave up on is still a
	 * recovery when the customer eventually buys, and counting it keeps the
	 * recovered figures honest.
	 *
	 * @var array
	 * @since 1.31.0
	 */
	const RECOVERABLE_STATUSES = array( 'pending', 'abandoned', 'lost' );

	/**
	 * WooCommerceOrderStatusChanged constructor.
	 *
	 * @param string $key The key associated with the action hook.
	 *
	 * @since 1.31.0
	 */
	public function __construct( $key ) {
		add_action( $key, array( $this, 'handle_order_status_changed' ), 10, 4 );
	}

	/**
	 * Handles the action triggered by the "woocommerce_order_status_changed" hook.
	 *
	 * @param int       $order_id   The ID of the order.
	 * @param string    $from       The previous order status.
	 * @param string    $to         The new order status.
	 * @param \WC_Order $order      The order object.
	 *
	 * @since 1.31.0
	 */
	public function handle_order_status_changed( $order_id, $from, $to, $order = null ) {
		if ( ! CartCommon::is_win_order_status( $to ) ) {
			return;
		}

		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$cart_details = $this->find_recoverable_cart( $order );

		if ( empty( $cart_details['id'] ) ) {
			return;
		}

		/*
		 * `organic` because this handler cannot tell how the customer got here — it fires
		 * for any qualifying order. Pro's restore-token handler is the only path that can
		 * prove the recovery link was used, and CartRecovery lets it upgrade the source
		 * afterwards if it fires second.
		 */
		CartRecovery::mark_recovered( $cart_details, $order, CartRecovery::SOURCE_ORGANIC );
	}

	/**
	 * Finds a tracked cart belonging to the customer who placed the order.
	 *
	 * Matches on the registered customer first, because a logged-in shopper may
	 * check out with a different billing address than the account email, and only
	 * then falls back to the billing email for guest checkouts.
	 *
	 * @param \WC_Order $order The order object.
	 *
	 * @return array|false The cart row, or false when the customer has none tracked.
	 * @since 1.31.0
	 */
	private function find_recoverable_cart( $order ) {
		return CartModel::find_open_cart_for_order( $order, self::RECOVERABLE_STATUSES );
	}

}
