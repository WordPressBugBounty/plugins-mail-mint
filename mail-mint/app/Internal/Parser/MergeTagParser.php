<?php

/**
 * MergeTagParser class.
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-07-09 09:03:17
 * @modify date 2024-07-09 09:03:17
 * @package Mint\MRM\Internal\Parser
 */

namespace Mint\MRM\Internal\Parser;

use MailMintPro\Mint\Internal\AbandonedCart\Helper\Common;
use MailMintPro\Mint\Internal\AbandonedCart\Helper\Model;
use Mint\Utilities\Arr;
use MailMintPro\App\Internal\EmailCustomization\Parser\EddMergeTagParser;
use MailMintPro\App\Internal\EmailCustomization\Parser\LearnDashTagParser;
use MailMintPro\App\Internal\EmailCustomization\Parser\WCMergeTagParser;
use MailMintPro\App\Internal\EmailCustomization\Parser\FBMergeTagParser;
use MRM\Common\MrmCommon;

/**
 * Class MergeTagParser
 *
 * The MergeTagParser class is responsible for parsing merge tags in a given string.
 * It supports both instance and static method calls.
 *
 * @since 1.13.4
 */
class MergeTagParser
{

	/**
	 * Parse the given template string with the provided data.
	 *
	 * @param string|array $template_string The template string to parse.
	 * @param array        $data            The data to use for parsing.
	 * @param int|null     $post_id         The post ID to use for parsing.
	 * @param int|null     $order_id        The order ID to use for parsing.
	 * @param array        $params          The additional parameters to use for parsing.
	 *
	 * @return string|array The parsed string or array of strings.
	 *
	 * @since 1.13.4
	 */
	public function parse($template_string, $data, $post_id = null, $order_id = null, $params = array())
	{
		$result    = array();
		$is_single = false;
		if (!is_array($template_string)) {
			$is_single = true;
		}

		foreach ((array) $template_string as $key => $string) {
			$result[$key] = $this->parse_merge_tag($string, $data, $post_id, $order_id, $params);
		}

		if ($is_single) {
			return reset($result);
		}

		return $result;
	}

	/**
	 * Parse the merge tags in the given string.
	 *
	 * @param string $string The string to parse.
	 * @param array  $data   The data to use for parsing.
	 * @param int|null $post_id The post ID to use for parsing.
	 * @param int|null $order_id The order ID to use for parsing.
	 * @param array  $params The additional parameters to use for parsing.
	 *
	 * @return string The parsed string.
	 *
	 * @since 1.13.4
	 */
	public function parse_merge_tag($string, $data, $post_id, $order_id, $params){
		return preg_replace_callback(
			'/({{|##)+(.*?)(}}|##)/',
			function ($matches) use ($data, $post_id, $order_id, $params) {
				return $this->replace($matches, $data, $post_id, $order_id, $params);
			},
			$string
		);
	}

	/**
	 * Replace the merge tag with the actual value.
	 *
	 * @param array $matches The matches from the preg_replace_callback.
	 * @param array $contact The contact data to use for parsing.
	 * @param int   $post_id The post ID to use for parsing.
	 * @param int   $order_id The order ID to use for parsing.
	 * @param array $params The additional parameters to use for parsing.
	 *
	 * @return string The replaced value.
	 *
	 * @since 1.13.4
	 */
	protected function replace($matches, $contact, $post_id, $order_id, $params){
		if (empty($matches[2])) {
			return apply_filters('mint_merge_tag_fallback', $matches[0], $contact);
		}
		$matches[2] = trim($matches[2]);
		$matched    = explode('.', $matches[2]);
		if (count($matched) <= 1) {
			return apply_filters('mint_merge_tag_fallback', $matches[0], $contact);
		}
		$data_key  = trim(array_shift($matched));
		$value_key = trim(implode('.', $matched));

		if (!$value_key) {
			return apply_filters('mint_merge_tag_fallback', $matches[0], $contact);
		}
		$value_keys = explode('|', $value_key);
		$value_key     = $value_keys[0];
		$default_value = '';
		$transformer   = '';
		$value_counts  = count($value_keys);

		if ($value_counts >= 3) {
			$default_value = trim($value_keys[1]);
			$transformer   = trim($value_keys[2]);
		} elseif (2 === $value_counts) {
			$default_value = trim($value_keys[1]);
		}

		$value = '';
		switch ($data_key) {
			case 'contact':
			case 'custom':
				$value = $this->get_contact_value($contact, $value_key, $default_value);
				break;
			case 'business':
				$value = $this->get_business_value($value_key, $default_value);
				break;
			case 'post':
				$value = $this->get_post_value($value_key, $default_value, $post_id);
				break;
			case 'user':
				$wp_user_id = isset($params['wp_user_id']) ? $params['wp_user_id'] : 0;
				if (!$wp_user_id) {
					$email   = isset($contact['email']) ? $contact['email'] : '';
					$wp_user = get_user_by('email', $email);
					if ($wp_user) {
						$wp_user_id = $wp_user->ID;
					} else {
						$wp_user_id = 0;
					}
				}

				$value = $this->get_wp_user_value($value_key, $default_value, $wp_user_id);
				break;
			case 'site':
				$value = $this->get_site_value($value_key, $default_value);
				break;
			case 'url':
				$value = $this->get_url_value($value_key, $default_value, $contact);
				break;
			case 'link':
				$value = $this->get_link_value($value_key, $default_value, $contact);
				break;
			case 'order_details':
				$value = $this->get_order_details_value($value_key, $default_value, $order_id);
				break;
			case 'customer':
				$value = $this->get_customer_details_value($value_key, $default_value, $order_id);
				break;
			case 'billing':
				$value = $this->get_billing_details_value($value_key, $default_value, $order_id);
				break;
			case 'shipping':
				$value = $this->get_shipping_details_value($value_key, $default_value, $order_id);
				break;
			case 'cart':
				$abandoned_id = isset($params['abandoned_id']) ? $params['abandoned_id'] : 0;
				$value = $this->get_cart_abandonment_value($value_key, $default_value, $abandoned_id);
				break;
			case 'edd':
				$edd_parser = new EddMergeTagParser( $value_key, $default_value, $params );
				$value      = $edd_parser->parse_edd_merge_tag();
				break;
			case 'edd_customer':
				$edd_parser = new EddMergeTagParser( $value_key, $default_value, $params );
				$value      = $edd_parser->parse_edd_customer_merge_tag();
				break;
			case 'edd_billing':
				$edd_parser = new EddMergeTagParser( $value_key, $default_value, $params );
				$value      = $edd_parser->parse_edd_billing_merge_tag();
				break;
			case 'wc_subscription':
				$wc_parser = new WCMergeTagParser( $value_key, $default_value, $params );
				$value     = $wc_parser->parse_wc_subscription_merge_tag();
				break;
			case 'product':
				$wc_parser = new WCMergeTagParser( $value_key, $default_value, $params );
				$value     = $wc_parser->parse_wc_product_merge_tag();
				break;
			case 'wc_shipment_tracking':
				$params['order_id'] = $order_id;
				$wc_parser = new WCMergeTagParser( $value_key, $default_value, $params );
				$value     = $wc_parser->parse_wc_shipment_tracking_merge_tag();
				break;
			case 'wc_adv_shipment':
				$params['order_id'] = $order_id;
				$wc_parser = new WCMergeTagParser($value_key, $default_value, $params);
				$value     = $wc_parser->parse_wc_adv_shipment_tracking_merge_tag();
				break;
			case 'review':
				$wc_parser = new WCMergeTagParser( $value_key, $default_value, $params );
				$value     = $wc_parser->parse_wc_review_merge_tag();
				break;
			case 'wc_membership':
				$wc_parser = new WCMergeTagParser(  $value_key, $default_value, $params );
				$value     = $wc_parser->parse_wc_membership_merge_tag();
				break;
			case 'ld':
				$wc_parser = new LearnDashTagParser( $value_key, $default_value, $params, $contact );
				$value     = $wc_parser->parse_learn_dash_merge_tag();
				break;
			case 'fb_booking':
				$fb_parser = new FBMergeTagParser($value_key, $default_value, $params);
				$value     = $fb_parser->parse_fluent_booking_merge_tag();
				break;
			case 'fb_guest':
				$guest_parser = new FBMergeTagParser($value_key, $default_value, $params);
				$value        = $guest_parser->parse_fluent_booking_guest_merge_tag();
				break;
			case 'fb_event':
				$event_parser = new FBMergeTagParser($value_key, $default_value, $params);
				$value        = $event_parser->parse_fluent_booking_event_merge_tag();
				break;
			case 'fb_host':
				$host_parser = new FBMergeTagParser($value_key, $default_value, $params);
				$value       = $host_parser->parse_fluent_booking_host_merge_tag();
				break;
			default:
				$value = apply_filters('mint_merge_tag_group_callback_' . $data_key, $matches[0], $value_key, $default_value, $contact);
		}

		if ($transformer && is_string($transformer) && $value) {
			switch ($transformer) {
				case 'trim':
					return trim($value);
				case 'ucfirst':
					return ucfirst($value);
				case 'strtolower':
					return strtolower($value);
				case 'strtoupper':
					return strtoupper($value);
				case 'ucwords':
					return ucwords($value);
				case 'concat_first': // usage: {{contact.first_name||concat_first|Hi.
					if (isset($value_keys[3])) {
						$value = trim($value_keys[3] . ' ' . $value);
					}
					return $value;
				case 'concat_last': // usage: {{contact.first_name||concat_last|, => FIRST_NAME.
					if (isset($value_keys[3])) {
						$value = trim($value . '' . $value_keys[3]);
					}
					return $value;
				case 'show_if': // usage {{contact.first_name||show_if|First name exist.
					if (isset($value_keys[3])) {
						$value = $value_keys[3];
					}
					return $value;
				default:
					return $value;
			}
		}

		return $value;
	}

	/**
	 * Get the value from the cart abandonment data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param int    $abandoned_id    The abandoned cart ID to use for parsing.
	 *
	 * @return string The value.
	 *
	 * @since 1.14.4
	 */
	protected function get_cart_abandonment_value($value_key, $default_value, $abandoned_id){
		// Check if the method exists in the Model class.
		if (!method_exists(Model::class, 'get_cart_details_by_id')) {
			return $default_value;
		}
		$cart_details = Model::get_cart_details_by_id( $abandoned_id );
		
		switch ($value_key) {
			case 'billing_email':
				return !empty( $cart_details['email'] ) ? $cart_details['email'] : $default_value;
			case 'items':
				$cart_details = Common::get_abandoned_cart_totals($cart_details);
				return Common::generate_cart_items_table_block_from_placeholder($cart_details);
			case 'recovery_url':
				$automation_id = isset($cart_details['automation_id']) ? $cart_details['automation_id'] : '';
				$checkout_id   = Common::get_woocommerce_checkout_page_id();
				$cart_url      = Common::get_cart_recovery_url($cart_details, $automation_id, $checkout_id);
				return !empty($cart_url) ? $cart_url : $default_value;
			case 'total':
				$cart_details = Common::get_abandoned_cart_totals($cart_details);
				return !empty($cart_details['total']) ? $cart_details['total'] : $default_value;
			case 'currency':
				return get_woocommerce_currency_symbol();
			case 'billing_first_name':
			case 'billing_last_name':
			case 'billing_address_1':
			case 'billing_address_2':
			case 'billing_company':
			case 'billing_city':
			case 'billing_state':
			case 'billing_postcode':
			case 'billing_country':
			case 'billing_phone':
			case 'shipping_first_name':
			case 'shipping_last_name':
			case 'shipping_address_1':
			case 'shipping_address_2':
			case 'shipping_company':
			case 'shipping_city':
			case 'shipping_state':
			case 'shipping_postcode':
			case 'shipping_country':
			case 'shipping_phone':
				$checkout_data   = isset($cart_details['checkout_data']) ? maybe_unserialize($cart_details['checkout_data']) : array();
				$checkout_fields = isset($checkout_data['fields']) ? $checkout_data['fields'] : array();
				$checkout_fields = array_combine(
					array_map(function ($key) {
						return str_replace('-', '_', $key);
					}, array_keys($checkout_fields)),
					$checkout_fields
				);
				$value           = Arr::get($checkout_fields, $value_key);
				return ($value) ? $value : $default_value;
			case 'abandoned_date':
				return MrmCommon::date_time_format_with_core( $cart_details['created_at'] ) ? MrmCommon::date_time_format_with_core( $cart_details['created_at'] ) : $default_value;
			default:
				return $default_value;
		}
	}

	/**
	 * Get the value from the shipping details data.
	 * 
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param int    $order_id    The order ID to use for parsing.
	 * 
	 * @return string The value.
	 * 
	 * @since 1.15.0
	 */
	protected function get_shipping_details_value($value_key, $default_value, $order_id){
		$order = wc_get_order($order_id);

		if (false === is_a($order, 'WC_Order')) {
			return $default_value;
		}

		switch ($value_key) {
			case 'first_name':
				return !empty($order->get_shipping_first_name()) ? $order->get_shipping_first_name() : $default_value;
			case 'last_name':
				return !empty($order->get_shipping_last_name()) ? $order->get_shipping_last_name() : $default_value;
			case 'company':
				return !empty($order->get_shipping_company()) ? $order->get_shipping_company() : $default_value;
			case 'address':
				return !empty($order->get_formatted_shipping_address()) ? $order->get_formatted_shipping_address() : $default_value;
			case 'address_1':
				return !empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() : $default_value;
			case 'address_2':
				return !empty($order->get_shipping_address_2()) ? $order->get_shipping_address_2() : $default_value;
			case 'city':
				return !empty($order->get_shipping_city()) ? $order->get_shipping_city() : $default_value;
			case 'state':
				return !empty($order->get_shipping_state()) ? $order->get_shipping_state() : $default_value;
			case 'postcode':
				return !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : $default_value;
			case 'country':
				return !empty($order->get_shipping_country()) ? $order->get_shipping_country() : $default_value;
			case 'email':
				return !empty($order->get_billing_email()) ? $order->get_billing_email() : $default_value;
			case 'phone':
				return !empty($order->get_shipping_phone()) ? $order->get_shipping_phone() : $default_value;
			case 'method':
				return !empty($order->get_shipping_method()) ? $order->get_shipping_method() : $default_value;
			default:
				return $default_value;
		}
	}

	/**
	 * Get the value from the billing details data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param int    $order_id    The order ID to use for parsing.
	 *
	 * @return string The value.
	 *
	 * @since 1.15.0
	 */
	protected function get_billing_details_value($value_key, $default_value, $order_id){
		$order = wc_get_order($order_id);

		if (false === is_a($order, 'WC_Order')) {
			return $default_value;
		}

		switch ($value_key) {
			case 'first_name':
				return !empty($order->get_billing_first_name()) ? $order->get_billing_first_name() : $default_value;
			case 'last_name':
				return !empty($order->get_billing_last_name()) ? $order->get_billing_last_name() : $default_value;
			case 'company':
				return !empty($order->get_billing_company()) ? $order->get_billing_company() : $default_value;
			case 'address':
				return !empty($order->get_formatted_billing_address()) ? $order->get_formatted_billing_address() : $default_value;
			case 'address_1':
				return !empty($order->get_billing_address_1()) ? $order->get_billing_address_1() : $default_value;
			case 'address_2':
				return !empty($order->get_billing_address_2()) ? $order->get_billing_address_2() : $default_value;
			case 'city':
				return !empty($order->get_billing_city()) ? $order->get_billing_city() : $default_value;
			case 'state':
				return !empty($order->get_billing_state()) ? $order->get_billing_state() : $default_value;
			case 'postcode':
				return !empty($order->get_billing_postcode()) ? $order->get_billing_postcode() : $default_value;
			case 'country':
				return !empty($order->get_billing_country()) ? $order->get_billing_country() : $default_value;
			case 'email':
				return !empty($order->get_billing_email()) ? $order->get_billing_email() : $default_value;
			case 'phone':
				return !empty($order->get_billing_phone()) ? $order->get_billing_phone() : $default_value;
			default:
				return $default_value;
		}
	}

	/**
	 * Get the value from the customer details data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param int    $order_id    The order ID to use for parsing.
	 *
	 * @return string The value.
	 *
	 * @since 1.15.0
	 */
	protected function get_customer_details_value($value_key, $default_value, $order_id){
		$order = wc_get_order($order_id);

		if (false === is_a($order, 'WC_Order')) {
			return $default_value;
		}

		switch ($value_key) {
			case 'name':
				return !empty($order->get_formatted_billing_full_name()) ? $order->get_formatted_billing_full_name() : $default_value;
			case 'note':
				$notes       = $order->get_customer_order_notes();
				$latest_note = reset($notes);
				return !empty($latest_note->comment_content) ? wpautop( wptexturize( make_clickable( $latest_note->comment_content ) ) ) : $default_value;
			case 'first_name':
				return !empty($order->get_billing_first_name()) ? $order->get_billing_first_name() : $default_value;
			case 'last_name':
				return !empty($order->get_billing_last_name()) ? $order->get_billing_last_name() : $default_value;
			default:
				return $default_value;
		}
	}

	/**
	 * Get the value from the order details data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param int    $order_id    The order ID to use for parsing.
	 *
	 * @return string The value.
	 *
	 * @since 1.15.0
	 */
	protected function get_order_details_value($value_key, $default_value, $order_id){
		$order = wc_get_order($order_id);

		if (false === is_a($order, 'WC_Order')) {
			return $default_value;
		}

		switch ($value_key) {
			case 'order_id':
				return !empty(intval($order->get_id())) ? intval($order->get_id()) : $default_value;
			case 'order_number':
				return !empty($order->get_order_number()) ? $order->get_order_number() : $default_value;
			case 'order_date':
				$date_format = get_option('date_format', 'Y-m-d');
				return date_i18n($date_format, strtotime($order->get_date_created()));
			case 'order_currency':
				return !empty($order->get_currency()) ? $order->get_currency() : $default_value;
			case 'order_discount':
				return !empty($order->get_discount_to_display()) ? $order->get_discount_to_display() : $default_value;
			case 'order_total':
				return $order->get_formatted_order_total();
			case 'order_status':
				return !empty(wc_get_order_status_name($order->get_status())) ? wc_get_order_status_name($order->get_status()) : $default_value;
			case 'order_note':
				$notes       = $order->get_customer_order_notes();
				$latest_note = reset($notes);
				return !empty($latest_note->comment_content) ? wpautop( wptexturize( make_clickable( $latest_note->comment_content ) ) ) : $default_value;
			case 'order_fully_refunded':
			case 'order_partial_refund':
				$refunds     = $order->get_refunds();
				$refund_html = '';
				if (is_array($refunds) && !empty($refunds)) {
					foreach ($refunds as $id => $refund) {
						$refund_html .= '<div>' . wc_price('-' . $refund->get_amount(), array('currency' => $order->get_currency())) . '</div>';
					}
				}
				return !empty($refund_html) ? $refund_html : $default_value;
			case 'order_received_url':
				if ($order->get_customer_id()) {
					return $order->get_view_order_url();
				}
				return $order->get_checkout_order_received_url();
			case 'order_shipping':
				return !empty($order->get_shipping_to_display()) ? $order->get_shipping_to_display() : $default_value;
			case 'order_subtotal':
				return !empty($order->get_subtotal_to_display()) ? $order->get_subtotal_to_display() : $default_value;
			case 'order_tax':
				if ('excl' === get_option('woocommerce_tax_display_cart') && wc_tax_enabled()) {
					if ('itemized' === get_option('woocommerce_tax_total_display')) {
						$taxes = array();
						foreach ($order->get_tax_totals() as $key => $tax) {
							$taxes[] = $tax->label . ' : ' . $tax->formatted_amount;
						}
						$tax = implode(',', $taxes);
					} else {
						$tax = wc_price($order->get_total_tax(), array('currency' => $order->get_currency()));
					}
				}
				return !empty($tax) ? $tax : $default_value;
			case 'payment_method':
				return !empty($order->get_payment_method_title()) ? $order->get_payment_method_title() : $default_value;
			case 'items_count':
				return !empty($order->get_item_count()) ? $order->get_item_count() : $default_value;
			case 'ordered_items_table':
				return $this->get_ordered_items_table($order);
			default:
				return $default_value;
		}
	}

	/**
	 * Get the ordered items table.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 *
	 * @return string The ordered items table.
	 *
	 * @since 1.15.0
	 */
	private function get_ordered_items_table($order){
		$order_items = $order->get_items(apply_filters('woocommerce_purchase_order_item_types', 'line_item'));
		ob_start();
		?>
		<div class="wp-block-table">
			<table class="woo_order_table">
				<thead>
					<tr>
						<th style="text-align: left;"><?php esc_html_e('Product', 'mrm'); ?></th>
						<th style="text-align: left;"><?php esc_html_e('Total', 'mrm'); ?></th>
					</tr>
				</thead>

				<tbody>
					<?php
					foreach ($order_items as $item_id => $item) {
						$product = $item->get_product();
					?>
						<tr>
							<td style="text-align: left; padding: 5px 10px; border: 1px solid #5f5f5f;">
								<?php
								$is_visible = $product && $product->is_visible();
								$product_permalink = apply_filters('woocommerce_order_item_permalink', $is_visible ? $product->get_permalink($item) : '', $item, $order);

								echo wp_kses_post(apply_filters('woocommerce_order_item_name', $product_permalink ? sprintf('<a href="%s">%s</a>', $product_permalink, $item->get_name()) : $item->get_name(), $item, $is_visible));

								$qty = $item->get_quantity();
								$refunded_qty = $order->get_qty_refunded_for_item($item_id);

								if ($refunded_qty) {
									$qty_display = '<del>' . esc_html($qty) . '</del> <ins>' . esc_html($qty - ($refunded_qty * -1)) . '</ins>';
								} else {
									$qty_display = esc_html($qty);
								}
								echo apply_filters('woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf('&times;&nbsp;%s', $qty_display) . '</strong>', $item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								wc_display_item_meta($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</td>
							<td style="text-align: left; border: 1px solid #5f5f5f;">
								<?php echo $order->get_formatted_line_subtotal($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
								?>
							</td>
						</tr>
					<?php } ?>
				</tbody>

				<tfoot>
					<?php
					foreach ($order->get_order_item_totals() as $key => $total) {
					?>
						<tr>
							<th style="text-align: right;border: 1px solid #5f5f5f;"><?php echo esc_html($total['label']); ?></th>
							<td style="text-align: left;border: 1px solid #5f5f5f;"><?php echo ('payment_method' === $key) ? esc_html($total['value']) : wp_kses_post($total['value']); ?></td>
						</tr>
					<?php
					}
					?>
				</tfoot>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the value from the contact data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 *
	 * @return string The value.
	 *
	 * @since 1.13.4
	 */
	protected function get_site_value($value_key, $default_value)
	{
		$value = get_bloginfo($value_key);

		if (!$value) {
			return $default_value;
		}
		return $value;
	}

	/**
	 * Get the value from the post data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param int    $post_id    The post ID to use for parsing.
	 *
	 * @return string The value.
	 *
	 * @since 1.13.4
	 */
	protected function get_post_value($value_key, $default_value, $post_id)
	{
		$post = get_post($post_id);
		if (! $post) {
			return $default_value;
		}

		switch ($value_key) {
			case 'title':
				return $post->post_title;
			case 'author':
				return get_the_author_meta('display_name', $post->post_author);
			case 'date':
				return MrmCommon::date_time_format_with_core($post->post_modified);
			case 'excerpt':
				return get_the_excerpt($post);
			case 'image':
				return $this->get_the_post_thumbnail($post_id);
			case 'image_url':
				return $this->get_the_post_thumbnail_url($post_id);
			case 'link':
				return get_permalink($post);
			case 'link_with_title':
				return "<a href='" . get_permalink($post) . "' target='_blank'>" . $post->post_title . "</a>";
			case 'full_content':
				return $this->get_full_content($post);
			case 'cats':
				return $this->get_post_categories($post);
			default:
				return $default_value;
		}
	}

	/**
	 * Get the post image.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string The image URL.
	 *
	 * @since 1.13.4
	 */
	protected function get_the_post_thumbnail($post_id)
	{
		$post_thumbnail      = '';
		$post_thumbnail_link = '';
		$post_link           = get_permalink($post_id);

		if ((function_exists('has_post_thumbnail')) && (has_post_thumbnail($post_id))) {
			$post_image_size = get_option('mint_post_image_size', 'thumbnail');

			switch ($post_image_size) {
				case 'full':
					$post_thumbnail = get_the_post_thumbnail($post_id, 'full');
					break;
				case 'medium':
					$post_thumbnail = get_the_post_thumbnail($post_id, 'medium');
					break;
				case 'thumbnail':
				default:
					$post_thumbnail = get_the_post_thumbnail($post_id, 'thumbnail');
					break;
			}
		}

		if ('' !== $post_thumbnail) {
			$post_thumbnail_link = "<a href='" . $post_link . "' target='_blank'>" . $post_thumbnail . "</a>";
		}

		return $post_thumbnail_link;
	}

	/**
	 * Get the post image URL.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string The image URL.
	 *
	 * @since 1.13.4
	 */
	protected function get_the_post_thumbnail_url($post_id)
	{
		$post_thumbnail_url = '';
		if ((function_exists('has_post_thumbnail')) && (has_post_thumbnail($post_id))) {
			$post_thumbnail_url = get_the_post_thumbnail_url($post_id);
		}
		return $post_thumbnail_url;
	}

	/**
	 * Get the post full content.
	 *
	 * @param object $post The post Object.
	 *
	 * @return string The post description.
	 *
	 * @since 1.13.4
	 */
	protected function get_full_content($post)
	{
		$content = $post->post_content;
		$content = wpautop($content);
		$content = apply_filters('the_content', $content);
		$content = str_replace(']]>', ']]&gt;', $content);
		return $content;
	}

	/**
	 * Get the post categories.
	 *
	 * @param object $post The post Object.
	 *
	 * @return string The post categories.
	 *
	 * @since 1.13.4
	 */
	protected function get_post_categories($post)
	{
		$taxonomies = get_object_taxonomies($post);
		$post_cats  = array();

		if (! empty($taxonomies)) {
			foreach ($taxonomies as $taxonomy) {
				$taxonomy_object = get_taxonomy($taxonomy);
				// Check if taxonomy is hierarchical e.g. have parent-child relationship like categories.
				if ($taxonomy_object->hierarchical) {
					$post_terms = get_the_terms($post, $taxonomy);
					if (is_array($post_terms) && ! empty($post_terms)) {
						foreach ($post_terms as $term) {
							$term_name   = $term->name;
							$post_cats[] = $term_name;
						}
					}
				}
			}
		}
		return implode(', ', $post_cats);
	}

	/**
	 * Get the value from the URL data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param array  $contact    The contact data.
	 *
	 * @return string The value.
	 *
	 * @since 1.13.4
	 */
	protected function get_url_value($value_key, $default_value, $contact)
	{
		switch ($value_key) {
			case 'home':
				return home_url();
			case 'shop':
				return MrmCommon::is_wc_active() ? wc_get_page_permalink('shop') : home_url();
			case 'my_account':
				return MrmCommon::is_wc_active() ? wc_get_page_permalink('myaccount') : home_url();
			case 'checkout':
				return MrmCommon::is_wc_active() ? wc_get_checkout_url() : home_url();
			case 'reset_password':
				return MrmCommon::is_wc_active() ? wc_get_page_permalink('lost_password') : esc_url(wp_lostpassword_url());
			case 'payment_url':
				return $default_value;
			default:
				return $default_value;
		}
	}

	/**
	 * Get the value from the contact data.
	 *
	 * @param array  $contact    The contact data.
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 *
	 * @return string The value.
	 *
	 * @since 1.13.4
	 */
	protected function get_contact_value($contact, $value_key, $default_value)
	{
		if (!$contact) {
			// We don't have contact.
			return '';
		}

		$value_keys = explode('.', $value_key);

		if (1 === count($value_keys)) {

			if ('firstName' === $value_key) {
				$value_key = 'first_name';
			} else if ('lastName' === $value_key) {
				$value_key = 'last_name';
			} else if ('companyName' === $value_key) {
				$value_key = 'company';
			} else if ('address_1' === $value_key) {
				$value_key = 'address_line_1';
			} else if ('address_2' === $value_key) {
				$value_key = 'address_line_2';
			}

			$value = Arr::get($contact, $value_key);
			return ($value) ? $value : $default_value;
		}
		return $default_value;
	}

	/**
	 * Get the value from the business data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 *
	 * @return string The value.
	 *
	 * @since 1.13.4
	 */
	protected function get_business_value($value_key, $default_value){
		$business = get_option('_mrm_business_basic_info_setting', MrmCommon::business_settings_default_configuration());

		if ( is_array($business) && empty($business) ) {
			return $default_value;
		}

		if ( 'address' === $value_key ) {
			return !empty( MrmCommon::get_business_full_address() ) ? MrmCommon::get_business_full_address() : $default_value;
		}

		if ( 'logo_image' === $value_key) {
			$image = '';
			if ( isset( $business['logo_url'] ) && !empty( $business['logo_url'] ) ) {
				$image = '<img style="width:60px; height:60px; margin:auto" src="' . $business['logo_url'] . '">';
			}
			return ($image) ? $image : $default_value;
		}

		// Transform the business array.
		$transformed_array = array(
			'name'     => $business['business_name'],
			'phone'    => $business['phone'],
			'logo_url' => $business['logo_url'],
		);

		// Merge the address details.
		$business = array_merge($transformed_array, $business['business_address']);
		$value    = Arr::get($business, $value_key);
		return ($value) ? $value : $default_value;
	}

	/**
	 * Get the value from the link data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param array  $contact    The contact data.
	 *
	 * @return string The value.
	 *
	 * @since 1.13.4
	 */
	protected function get_link_value($value_key, $default_value, $contact)
	{
		if (!$contact) {
			return $default_value;
		}

		switch ($value_key) {
			case 'subscribe':
				return add_query_arg(
					array_filter(
						array(
							'mrm'   => 1,
							'route' => 'confirmation',
							'hash'  => $contact['hash'],
						)
					),
					site_url('/')
				);
			case 'subscribe_html':
				if (!$default_value) {
					$default_value = 'Subscribe';
				}

				$url = add_query_arg(
					array_filter(
						array(
							'mrm'   => 1,
							'route' => 'confirmation',
							'hash'  => $contact['hash'],
						)
					),
					site_url('/')
				);

				return '<a class="mint-sub-url" href="' . $url . '">' . $default_value . '</a>';
			case 'unsubscribe':
				return add_query_arg(
					array_filter(
						array(
							'mrm'   => 1,
							'route' => 'unsubscribe',
							'hash'  => $contact['hash'],
						)
					),
					site_url('/')
				);
			case 'unsubscribe_html':
				if (!$default_value) {
					$default_value = 'Unsubscribe';
				}

				$url = add_query_arg(
					array_filter(
						array(
							'mrm'   => 1,
							'route' => 'unsubscribe',
							'hash'  => $contact['hash'],
						)
					),
					site_url('/')
				);

				return '<a class="mint-unsub-url" href="' . $url . '">' . $default_value . '</a>';
			case 'preference':
				return add_query_arg(
					array_filter(
						array(
							'mrm'   => 1,
							'route' => 'mrm-preference',
							'hash'  => $contact['hash'],
						)
					),
					MrmCommon::get_default_preference_page_id_title()
				);
			case 'preference_html':
				if (!$default_value) {
					$default_value = 'Manage your preference';
				}

				$url = add_query_arg(
					array_filter(
						array(
							'mrm'   => 1,
							'route' => 'mrm-preference',
							'hash'  => $contact['hash'],
						)
					),
					MrmCommon::get_default_preference_page_id_title()
				);

				return '<a class="mint-pref-url" href="' . $url . '">' . $default_value . '</a>';
			default:
				return $default_value;
		}
	}

	/**
	 * Get the value from the WP user data.
	 *
	 * @param string $value_key  The key to get the value.
	 * @param string $default_value The default value to return if the key is not found.
	 * @param int    $wp_user_id  The WP user ID to use for parsing.
	 *
	 * @return string The value.
	 *
	 * @since 1.18.2
	 */
	private function get_wp_user_value( $value_key, $default_value, $wp_user_id ) {
		$wp_user = get_user_by( 'id', $wp_user_id );
		if ( ! $wp_user ) {
			return $default_value;
		}

		switch ( $value_key ) {
			case 'email':
				return ! empty( $wp_user->user_email ) ? $wp_user->user_email : $default_value;
			case 'id':
				return ! empty( $wp_user->ID ) ? $wp_user->ID : $default_value;
			case 'username':
				return ! empty( $wp_user->user_login ) ? $wp_user->user_login : $default_value;
			default:
				return $default_value;
		}
	}
}
