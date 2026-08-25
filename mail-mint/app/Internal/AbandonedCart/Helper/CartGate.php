<?php
/**
 * Class CartGate
 *
 * Decides whether a queued cart email may still be sent.
 *
 * This is the versioned contract between Free and Pro for cart email suppression. Free
 * owns the cart data; Pro owns the automations that send the mail. Pro asks this class
 * before every queued cart step, guarded by a class_exists() check so an older Free
 * cannot fatal a newer Pro.
 *
 * Every path fails closed. The bug this class exists to fix was the opposite: the guard
 * it replaces stopped a send only when it successfully read a cart row whose status was
 * `recovered`, so a row that had been deleted — which is exactly what used to happen when
 * a customer bought inside the wait window — returned nothing, satisfied no condition,
 * and let the email go out after the purchase.
 *
 * @package Mint\MRM\Internal\AbandonedCart\Helper
 * @since 1.31.1
 */

namespace Mint\MRM\Internal\AbandonedCart\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartGate
 *
 * Suppression policy for abandoned cart emails.
 */
final class CartGate {

	/**
	 * Cart statuses in which a cart may still be emailed.
	 *
	 * An allow-list rather than a block-list, so `recovered`, `lost`, an empty string and
	 * any status added in future all stop the send by construction instead of needing to
	 * be remembered here.
	 *
	 * @var array
	 * @since 1.31.1
	 */
	const OPEN_STATUSES = array( 'pending', 'abandoned' );

	/**
	 * Automation triggers that legitimately fire on a closed cart, and the one status
	 * each of them fires on.
	 *
	 * Without this, widening the block to every non-open status would silence the
	 * lost-cart automation entirely: Pro's `wc_abandoned_cart_lost` trigger reads the cart
	 * with status `lost`, so its own emails would be suppressed the moment they were
	 * queued.
	 *
	 * @var array
	 * @since 1.31.1
	 */
	const TERMINAL_TRIGGERS = array(
		'wc_abandoned_cart_recovered' => 'recovered',
		'wc_abandoned_cart_lost'      => 'lost',
	);

	/**
	 * Suppression reason slugs.
	 *
	 * @since 1.31.1
	 */
	const REASON_NO_CART_ID    = 'no_cart_id';
	const REASON_CART_MISSING  = 'cart_missing';
	const REASON_STATUS_CLOSED = 'status_closed';
	const REASON_ORDER_EXISTS  = 'order_exists';

	/**
	 * Whether a queued cart email may still be sent.
	 *
	 * @param int   $cart_id Abandoned cart ID.
	 * @param array $context Optional. `trigger_name` and `automation_id`.
	 *
	 * @return bool True only when it is safe to send.
	 * @since 1.31.1
	 */
	public static function is_mailable( $cart_id, array $context = array() ) {
		return '' === self::suppression_reason( $cart_id, $context );
	}

	/**
	 * Why a queued cart email should be suppressed, if it should.
	 *
	 * IMPORTANT for callers: a REASON_NO_CART_ID result means "this is not a cart
	 * automation at all", not "block it". Resolve the cart ID first and pass the payload
	 * through untouched when it is 0 — treating a missing ID as a suppression would block
	 * every automation on the site, not just cart ones.
	 *
	 * @param int   $cart_id Abandoned cart ID.
	 * @param array $context Optional. `trigger_name` and `automation_id`.
	 *
	 * @return string Empty string when mailable, otherwise a REASON_* slug.
	 * @since 1.31.1
	 */
	public static function suppression_reason( $cart_id, array $context = array() ) {
		$cart_id      = (int) $cart_id;
		$trigger_name = isset( $context['trigger_name'] ) ? (string) $context['trigger_name'] : '';

		$reason = '';
		$cart   = array();

		if ( $cart_id <= 0 ) {
			$reason = self::REASON_NO_CART_ID;
		} elseif ( 'wc_abandoned_cart_recovered' === $trigger_name ) {
			/*
			 * Full bypass, not just a status bypass. This automation exists *because* the
			 * cart closed and an order was placed, so both the status check and the order
			 * check below would always block it.
			 */
			$reason = '';
		} else {
			$cart = CartModel::get_cart_by_id( $cart_id );

			if ( empty( $cart ) || ! is_array( $cart ) ) {
				// The row is gone. Nothing can be verified, so nothing is sent.
				$reason = self::REASON_CART_MISSING;
			} else {
				$status = isset( $cart['status'] ) ? (string) $cart['status'] : '';

				$terminal_ok = isset( self::TERMINAL_TRIGGERS[ $trigger_name ] )
					&& self::TERMINAL_TRIGGERS[ $trigger_name ] === $status;

				if ( ! $terminal_ok && ! in_array( $status, self::OPEN_STATUSES, true ) ) {
					$reason = self::REASON_STATUS_CLOSED;
				} elseif ( CartCommon::has_order_since( $cart ) ) {
					/*
					 * Checked after the status test because it costs a WooCommerce order
					 * query while the status test costs an indexed row read that has
					 * already happened. Note this runs even for `wc_abandoned_cart_lost`:
					 * if the customer bought, a "we lost your cart" email is still wrong.
					 */
					$reason = self::REASON_ORDER_EXISTS;
				}
			}
		}

		/**
		 * Filters the reason a cart email is being suppressed.
		 *
		 * Return an empty string to allow the send, or a non-empty slug to block it.
		 *
		 * @param string $reason  Empty when mailable, otherwise a REASON_* slug.
		 * @param int    $cart_id Abandoned cart ID.
		 * @param array  $cart    The cart row, empty when it could not be read.
		 * @param array  $context Caller context.
		 *
		 * @since 1.31.1
		 */
		$reason = (string) apply_filters( 'mint_abandoned_cart_suppression_reason', $reason, $cart_id, $cart, $context );

		if ( '' !== $reason && self::REASON_NO_CART_ID !== $reason ) {
			/**
			 * Fires when a queued cart email is suppressed.
			 *
			 * @param int    $cart_id Abandoned cart ID.
			 * @param string $reason  The REASON_* slug.
			 * @param array  $context Caller context.
			 *
			 * @since 1.31.1
			 */
			do_action( 'mint_abandoned_cart_email_suppressed', $cart_id, $reason, $context );
		}

		return $reason;
	}

	/**
	 * Resolve the cart ID out of an automation or sequence payload.
	 *
	 * The same value reaches this code at three different depths, because the step filter,
	 * the re-wrapped step data and the sequence scheduler each nest it differently. The
	 * paths are listed explicitly rather than searched for recursively: `abandoned_id` also
	 * appears inside unrelated sub-arrays such as a webhook body template, and picking one
	 * of those up would silently gate the wrong automation. When a fourth shape appears,
	 * add it here with a test rather than making the search fuzzier.
	 *
	 * @param mixed $payload Automation step or sequence payload.
	 *
	 * @return int Cart ID, or 0 when the payload is not cart-related.
	 * @since 1.31.1
	 */
	public static function resolve_cart_id( $payload ) {
		if ( ! is_array( $payload ) ) {
			return 0;
		}

		$paths = array(
			array( 'abandoned_id' ),
			array( 'data', 'abandoned_id' ),
			array( 'data', 'data', 'abandoned_id' ),
		);

		foreach ( $paths as $path ) {
			$cursor = $payload;
			$found  = true;

			foreach ( $path as $segment ) {
				if ( ! is_array( $cursor ) || ! isset( $cursor[ $segment ] ) ) {
					$found = false;
					break;
				}
				$cursor = $cursor[ $segment ];
			}

			if ( $found && is_scalar( $cursor ) && (int) $cursor > 0 ) {
				return (int) $cursor;
			}
		}

		return 0;
	}

	/**
	 * Resolve the cart ID out of a cart-lifecycle payload.
	 *
	 * Kept separate from resolve_cart_id() on purpose. The cart lifecycle actions
	 * (`mailmint_after_abandoned_cart_recovered` / `_lost`) key on `id`, but automation
	 * step payloads also carry an `id` that belongs to an automation step row — so
	 * teaching resolve_cart_id() to read `id` too would make an ordinary sendMail step
	 * resolve to an unrelated cart and gate it.
	 *
	 * @param mixed $payload Cart row, or a payload wrapping one.
	 *
	 * @return int Cart ID, or 0.
	 * @since 1.31.1
	 */
	public static function resolve_closed_cart_id( $payload ) {
		if ( ! is_array( $payload ) ) {
			return (int) $payload > 0 ? (int) $payload : 0;
		}

		if ( isset( $payload['id'] ) && (int) $payload['id'] > 0 ) {
			return (int) $payload['id'];
		}

		if ( isset( $payload['data']['id'] ) && (int) $payload['data']['id'] > 0 ) {
			return (int) $payload['data']['id'];
		}

		return 0;
	}
}
