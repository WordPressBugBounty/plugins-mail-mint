<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin
 */

namespace Mint\MRM\Internal\Admin;

use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * [Manage WooCommerce order details]
 *
 * @desc Manage WooCommerce order details
 * @package /app/Internal/Admin
 * @since 1.0.0
 */
class WooCommerceOrderDetails {

	use Singleton;

	/**
	 * Initialize actions
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		add_action(
			'woocommerce_admin_order_data_after_order_details',
			array(
				$this,
				'render_subscription_consent_message',
			)
		);
	}

	/**
	 * Add subscription consent message in order details page
	 *
	 * @param \WC_Order $order Order object of WooCommerce.
	 * @return void
	 * @since 1.0.0
	 */
	public function render_subscription_consent_message( \WC_Order $order ) {
        $mrm_newsletter_subscription = MrmCommon::get_mailmint_newsletter_subscription_hpos($order, '_mrm_newsletter_subscription');
		if ( 'Yes' === $mrm_newsletter_subscription ) :
			?>
			<p class="form-field form-field-wide">
                <p class="form-field order_note">
                    <strong>
                    <?php esc_html_e( 'Newsletter subscription:', 'mrm' ); ?>
                    </strong>
                    <?php echo esc_html( $mrm_newsletter_subscription ); ?>
				</p>
			</p>
			<?php
		endif;
	}
}
