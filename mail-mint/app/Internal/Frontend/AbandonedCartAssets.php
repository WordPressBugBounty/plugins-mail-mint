<?php
/**
 * Mail Mint
 *
 * Enqueues the storefront assets that capture cart and checkout data for
 * abandoned cart tracking, plus the GDPR consent notice.
 *
 * @package /app/Internal/Frontend
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\Frontend;

use Mint\MRM\Internal\AbandonedCart\AbandonedCart;
use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use MRM\Common\MrmCommon;

/**
 * Manages the abandoned cart frontend assets.
 *
 * @package /app/Internal/Frontend
 * @since 1.31.0
 */
class AbandonedCartAssets {

	/**
	 * Initializes class functionalities.
	 *
	 * @since 1.31.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Load the abandoned cart storefront assets.
	 *
	 * @return void
	 * @since 1.31.0
	 */
	public function enqueue_scripts() {
		// An older Mail Mint Pro is still serving its own copy of these assets.
		if ( AbandonedCart::is_owned_by_pro() ) {
			return;
		}

		// The visitor opted out of cart tracking via the GDPR consent notice.
		if ( isset( $_COOKIE['mint_ab_cart_skip_track'] ) && 'yes' === $_COOKIE['mint_ab_cart_skip_track'] ) {
			return;
		}

		if ( ! MrmCommon::is_wc_active() || ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) ) {
			return;
		}

		$settings = CartCommon::get_abandoned_cart_settings();

		// Nothing to capture while tracking is switched off.
		if ( empty( $settings['enable'] ) ) {
			return;
		}

		wp_enqueue_style(
			'mailmint-abandoned-cart',
			MRM_DIR_URL . 'assets/frontend/css/abandoned-cart.css',
			array(),
			MRM_VERSION
		);

		wp_enqueue_script(
			'mailmint-abandoned-cart',
			MRM_DIR_URL . 'assets/frontend/js/abandoned-cart.js',
			array( 'jquery' ),
			MRM_VERSION,
			true
		);

		wp_localize_script(
			'mailmint-abandoned-cart',
			'MintFrontendVars',
			array(
				'ajaxurl'           => admin_url( 'admin-ajax.php' ),
				'rest_url'          => esc_url_raw( rest_url() ),
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'abandoned_setting' => $settings,
				'is_checkout_block' => CartCommon::is_checkout_block(),
			)
		);
	}
}
