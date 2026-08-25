<?php
/**
 * Class AbandonedCart
 *
 * Bootstraps abandoned cart tracking: the WooCommerce capture hooks, the status
 * schedulers that move a cart from pending to abandoned to lost, and the order
 * status watcher that marks a cart recovered once the customer buys.
 *
 * Recovery *campaigns* — the one-click restore link, the automation triggers and
 * the per-automation attribution that goes with them — remain a Mail Mint Pro
 * feature. Free detects and reports recovery; Pro acts on it.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\AbandonedCart;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Scheduler\AbandonedCartRunScheduler;
use Mint\MRM\Internal\AbandonedCart\Scheduler\CartGroupJanitor;
use MRM\Common\MrmCommon;

/**
 * Class AbandonedCart
 *
 * Bootstraps abandoned cart tracking.
 */
class AbandonedCart {

	/**
	 * Pro's cart class, used to detect whether it knows how to defer to Free.
	 *
	 * @var string
	 * @since 1.31.0
	 */
	const PRO_CART_CLASS = 'MailMintPro\\Mint\\Internal\\AbandonedCart\\AbandonedCart';

	/**
	 * The hooks to be registered and their corresponding classes.
	 *
	 * @var array
	 * @since 1.31.0
	 */
	protected $wc_hooks = array(
		'woocommerce_add_to_cart'            => 'WooCommerceAddToCart',
		'woocommerce_cart_item_removed'      => 'WooCommerceCartItemRemoved',
		'woocommerce_cart_item_restored'     => 'WooCommerceCartItemRestored',
		'woocommerce_cart_item_set_quantity' => 'WooCommerceCartItemSetQuantity',
		'woocommerce_new_order'              => 'WooCommerceNewOrder',
		'woocommerce_order_status_changed'   => 'WooCommerceOrderStatusChanged',
		'wp_login'                           => 'WooCommerceUserLogin',
		'woocommerce_applied_coupon'         => 'WooCommerceCouponApplied',
		'woocommerce_removed_coupon'         => 'WooCommerceCouponRemoved',
	);

	/**
	 * Constructor for the AbandonedCart class.
	 *
	 * Registers the WooCommerce capture hooks and the status schedulers, unless an
	 * older Mail Mint Pro is still running its own copy of the tracking module.
	 *
	 * @since 1.31.0
	 */
	public function __construct() {
		/*
		 * Runs before the ownership check on purpose: both plugins read the same
		 * `_mint_abandoned_cart_settings` row, so a site still on the legacy Pro tracker
		 * needs the setting seeded too. No-ops once the key is present, and grandfathers
		 * stores that were already capturing carts onto their previous status.
		 */
		CartCommon::maybe_seed_new_contact_status();

		/*
		 * Also ahead of the ownership check. Every version of cart tracking, Pro's included,
		 * schedules into a group per cart, so the group rows accumulate on a site running the
		 * legacy Pro tracker exactly as they do here — and the sweep only ever touches rows
		 * Action Scheduler has already finished with, so it is safe to run either way.
		 */
		new CartGroupJanitor();

		if ( self::is_owned_by_pro() ) {
			return;
		}

		if ( MrmCommon::is_wc_active() ) {
			foreach ( $this->wc_hooks as $key => $value ) {
				$class_name = 'Mint\\MRM\\Internal\\AbandonedCart\\Hooks\\WooCommerce\\' . $value;
				if ( class_exists( $class_name ) ) {
					new $class_name( $key );
				}
			}
		}
		new AbandonedCartRunScheduler();
	}

	/**
	 * Whether an older Mail Mint Pro still owns cart tracking.
	 *
	 * Free and Pro guard the handover from both sides so the plugins can be updated in
	 * either order. Without this, a site that updates Free first would run two sets of
	 * capture hooks and enqueue every Action Scheduler job twice.
	 *
	 * This asks whether the installed Pro knows how to step aside, rather than comparing
	 * version numbers. Pro's own guard keys off MRM_VERSION, so a version-based check here
	 * would deadlock whenever Pro shipped the deferral code under an unbumped version —
	 * both sides would stand down and nothing would track the carts.
	 *
	 * @param string $pro_class Test seam only. One PHP process can hold just one class of
	 *                          a given name, so a test cannot exercise both a legacy and a
	 *                          modern Pro without being able to name a stand-in. No
	 *                          production caller passes this.
	 *
	 * @return bool True when Pro is active and predates the handover.
	 * @since 1.31.0
	 */
	public static function is_owned_by_pro( $pro_class = self::PRO_CART_CLASS ) {
		return MrmCommon::is_mailmint_pro_active()
			&& ! method_exists( $pro_class, 'is_cart_owned_by_free' );
	}
}
