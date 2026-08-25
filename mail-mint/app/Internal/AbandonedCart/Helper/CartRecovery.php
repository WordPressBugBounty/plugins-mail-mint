<?php
/**
 * Class CartRecovery
 *
 * The single write path for marking a tracked cart recovered.
 *
 * Three separate handlers can detect a recovery — Free's order-status watcher, Free's
 * new-order handler, and Pro's restore-token checkout hook — and before this class each
 * wrote the row its own way. Centralising it means the concurrency guard, the attribution
 * source, the job cleanup and the sibling closure all happen the same way whichever
 * handler gets there first.
 *
 * @package Mint\MRM\Internal\AbandonedCart\Helper
 * @since 1.31.1
 */

namespace Mint\MRM\Internal\AbandonedCart\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartRecovery
 *
 * Marks carts recovered and records how.
 */
final class CartRecovery {

	/**
	 * How a cart came to be recovered.
	 *
	 * `link` means the customer used the recovery link; `organic` means they came back on
	 * their own; `fast_purchase` means they bought before the cart was ever considered
	 * abandoned; `manual` means an admin or API call did it.
	 *
	 * @since 1.31.1
	 */
	const SOURCE_LINK          = 'link';
	const SOURCE_ORGANIC       = 'organic';
	const SOURCE_FAST_PURCHASE = 'fast_purchase';
	const SOURCE_MANUAL        = 'manual';

	/**
	 * Sources that a later `link` detection is allowed to overwrite.
	 *
	 * @var array
	 * @since 1.31.1
	 */
	const UPGRADABLE_SOURCES = array( self::SOURCE_ORGANIC, self::SOURCE_FAST_PURCHASE );

	/**
	 * Mark a cart as recovered by an order.
	 *
	 * @param array     $cart   The cart row, read immediately before this call.
	 * @param \WC_Order $order  The order that recovered it.
	 * @param string    $source One of the SOURCE_* constants.
	 *
	 * @return bool True when this call performed the recovery.
	 * @since 1.31.1
	 */
	public static function mark_recovered( $cart, $order, $source = self::SOURCE_ORGANIC ) {
		if ( empty( $cart['id'] ) || ! $order instanceof \WC_Order ) {
			return false;
		}

		$cart_id = (int) $cart['id'];
		$status  = isset( $cart['status'] ) ? (string) $cart['status'] : '';

		// Already recovered by whoever won the race — the only thing still worth doing is
		// improving the attribution. See maybe_upgrade_source().
		if ( 'recovered' === $status ) {
			return self::maybe_upgrade_source( $cart_id, $source );
		}

		/**
		 * Fires before a tracked cart is marked as recovered.
		 *
		 * @param array     $cart  The cart row about to be updated.
		 * @param \WC_Order $order The order that recovered it.
		 *
		 * @since 1.31.0
		 */
		do_action( 'mailmint_before_abandoned_cart_recovered', $cart, $order );

		$data = array(
			'status'          => 'recovered',
			'recovery_source' => $source,
			'recovered_at'    => current_time( 'mysql' ),
		);

		/*
		 * Constrain the write to the status the row was read at, so two handlers firing on
		 * the same request — Pro's checkout hook and Free's status watcher — settle the row
		 * exactly once instead of both claiming it.
		 */
		$updated = CartModel::update_columns(
			$data,
			array(
				'id'     => $cart_id,
				'status' => $status,
			)
		);

		if ( ! $updated ) {
			return false;
		}

		CartModel::update_cart_meta( $cart_id, 'order_id', $order->get_id() );

		// This cart is settled: its own status-transition jobs have nothing left to do.
		CartModel::cancel_cart_jobs( $cart_id );

		/*
		 * Close the customer's other open carts before firing the action below, so any
		 * listener that goes looking at the table sees a settled picture. Without this the
		 * recovery handlers — which only ever resolve one row — would leave the siblings
		 * emailing a customer who has already bought.
		 */
		$closed = CartModel::close_open_siblings(
			isset( $cart['email'] ) ? $cart['email'] : '',
			isset( $cart['user_id'] ) ? $cart['user_id'] : 0,
			$cart_id
		);

		/**
		 * Fires after a tracked cart has been marked as recovered.
		 *
		 * @param array     $cart   The cart row that was updated.
		 * @param \WC_Order $order  The order that recovered it.
		 * @param string    $source How the recovery was detected.
		 * @param array     $closed IDs of sibling carts closed alongside it.
		 *
		 * @since 1.31.0
		 */
		do_action( 'mailmint_after_abandoned_cart_recovered', $cart, $order, $source, $closed );

		return true;
	}

	/**
	 * Improve the recorded source on a cart that is already recovered.
	 *
	 * On an order that lands straight in a winning status, Pro's checkout handler and
	 * Free's status watcher both fire in the same request, and on the block checkout the
	 * status change can even precede the Store API hook. The status guard in
	 * mark_recovered() makes the second writer a no-op, which is correct for the status —
	 * but it would leave the source decided by hook order, i.e. by chance.
	 *
	 * Only `link` is treated as an upgrade, because only the restore-token flow can prove
	 * the recovery link was used; the other sources are inferences.
	 *
	 * @param int    $cart_id Abandoned cart ID.
	 * @param string $source  The source the calling handler detected.
	 *
	 * @return bool False always — no recovery was performed by this call.
	 * @since 1.31.1
	 */
	private static function maybe_upgrade_source( $cart_id, $source ) {
		if ( self::SOURCE_LINK !== $source ) {
			return false;
		}

		$cart = CartModel::get_cart_details_by_id( $cart_id );

		$current = isset( $cart['recovery_source'] ) ? (string) $cart['recovery_source'] : '';

		if ( '' === $current || in_array( $current, self::UPGRADABLE_SOURCES, true ) ) {
			CartModel::update_columns(
				array( 'recovery_source' => self::SOURCE_LINK ),
				array( 'id' => $cart_id )
			);
		}

		return false;
	}
}
