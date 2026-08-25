<?php
/**
 * Class WooCommerceUserLogin
 *
 * Handles the WordPress "wp_login" hook to re-associate a guest's pending
 * abandoned cart with the account they just logged into.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Hooks\WooCommerce;

use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;
use WC_Session_Handler;

/**
 * Class WooCommerceUserLogin
 *
 * Re-keys a guest's tracked cart to the logged-in user, rather than leaving it
 * orphaned under the pre-login session once subsequent capture events start
 * writing a real email against it.
 */
class WooCommerceUserLogin {

	/**
	 * WooCommerceUserLogin constructor.
	 *
	 * @param string $key The key associated with the action hook.
	 *
	 * @since 1.31.0
	 */
	public function __construct( $key ) {
		add_action( $key, array( $this, 'handle_user_login' ), 10, 2 );
	}

	/**
	 * Handles the action triggered by the "wp_login" hook.
	 *
	 * WooCommerce's session cookie is independent of the WP auth cookie and normally
	 * persists through login in the same browser, so the pending cart's session_key is
	 * still valid here — it just needs user_id/email filled in. WC()->session cannot be
	 * trusted to already be populated at this point in the request, so a fresh session
	 * handler is initialized to read it, the same defensive pattern the storefront REST
	 * controller uses.
	 *
	 * @param string  $user_login The username of the user that logged in.
	 * @param \WP_User $user      The user object of the user that logged in.
	 *
	 * @since 1.31.0
	 */
	public function handle_user_login( $user_login, $user ) {
		if ( empty( $user->ID ) || empty( $user->user_email ) ) {
			return;
		}

		$woocommerce          = WC();
		$woocommerce->session = new WC_Session_Handler();
		$woocommerce->session->init();

		$session_key = $woocommerce->session->get_customer_id();

		$cart_detail = CartModel::get_cart_details_by_key_and_status( 'session_key', $session_key, array( 'pending' ) );

		if ( empty( $cart_detail['id'] ) || ! empty( $cart_detail['user_id'] ) ) {
			return;
		}

		CartModel::update(
			array(
				'user_id' => (int) $user->ID,
				'email'   => $user->user_email,
			),
			$cart_detail['id']
		);
	}
}
