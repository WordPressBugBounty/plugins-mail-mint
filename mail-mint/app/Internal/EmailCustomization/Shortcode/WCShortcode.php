<?php

namespace Mint\App\Internal\EmailCustomization\Shortcode;

use MRM\Common\MrmCommon;
use stdClass;

class WCShortcode {

	private $password_reset_key;
	private $user_object;
	private $order_object;

	public function __construct() {
		// Constructor.
	}

	public function set_data( $data ) {
		$this->password_reset_key = '';

		if ( !empty( $data['user_object'] ) && $data['user_object'] instanceof \WP_User ) {
			$this->user_object        = $data['user_object'];
			$this->password_reset_key = ( isset( $data['password_reset_key'] ) && $data['password_reset_key'] === true ) ? get_password_reset_key( $this->user_object ) : '';
		} else {
			$this->user_object               = new stdClass();
			$this->user_object->ID           = 0;
			$this->user_object->display_name = 'Guest';
			$this->user_object->user_login   = '';
			$this->user_object->user_email   = '';
			$this->user_object->first_name   = '';
			$this->user_object->last_name    = '';
		}

		if ( !empty( $data['order_object'] ) && $data['order_object'] instanceof \WC_Order ) {
			$this->order_object = $data['order_object'];

			if ( !( $this->user_object instanceof \WP_User ) ) {
				$this->user_object->ID           = 0;
				$this->user_object->display_name = ( !empty( $this->order_object->get_shipping_first_name() ) ) ? $this->order_object->get_shipping_first_name() . ' ' . $this->order_object->get_shipping_last_name() : 'Guest';
				$this->user_object->user_email   = $this->order_object->get_billing_email();
				$this->user_object->first_name   = $this->order_object->get_shipping_first_name();
				$this->user_object->last_name    = $this->order_object->get_shipping_last_name();
			}
		}
	}

	public function replace( $string ) {
		$shortcodes = self::get_shortcodes();

		if ( !empty( $shortcodes ) ) {
			foreach ( $shortcodes as $key => $value ) {
				if ( false !== strpos( $string, $key ) ) {
					$string = str_replace( $key, $value, $string );
				}
			}
		}

		return $string;
	}

	private function get_shortcodes() {
		$date_format    = get_option( 'date_format', 'Y-m-d' );
		$shop_url       = MrmCommon::is_wc_active() ? wc_get_page_permalink( 'shop' ) : home_url();
		$my_account_url = MrmCommon::is_wc_active() ? wc_get_page_permalink( 'myaccount' ) : home_url();
		$checkout_url   = MrmCommon::is_wc_active() ? wc_get_checkout_url() : home_url();

		$shortcodes = array(
			'{admin_email}'          => get_option( 'admin_email', '' ),
			'{{site.title}}'         => get_bloginfo( 'name' ),
			'{{url.home}}'           => home_url(),
			'{{site.url}}'           => site_url(),
			'{{url.shop}}'           => $shop_url ? $shop_url : home_url(),
			'{{url.my_account}}'     => $my_account_url ? $my_account_url : home_url(),
			'{{url.checkout}}'       => $checkout_url ? $checkout_url : home_url(),

			'{{customer.name}}'      => ( !empty( $this->user_object->display_name ) ) ? $this->user_object->display_name : '',
			'{{customer.note}}'      => '',
			'{{customer.first_name}}' => ( !empty( $this->user_object->first_name ) ) ? $this->user_object->first_name : $this->user_object->display_name,
			'{{customer.last_name}}' => ( !empty( $this->user_object->last_name ) ) ? $this->user_object->last_name : '',

			'{{order_details.order_date}}'           => '',
			'{{order_details.order_discount}}'       => '',
			'{{order_details.order_fully_refunded}}' => '',
			'{{order_details.order_number}}'         => '',
			'{{order_details.order_partial_refund}}' => '',
			'{{order_details.order_received_url}}'   => '',
			'{{order_details.order_shipping}}'       => '',
			'{{order_details.order_subtotal}}'       => '',
			'{{order_details.order_total}}'          => '',
			'{{order_details.order_tax}}'            => '',
			'{{order_details.payment_method}}'       => '',

			'{{url.payment}}'   => '',
			'{{user.email}}'    => $this->user_object->user_email,
			'{{user.id}}'       => $this->user_object->ID,
			'{{user.username}}' => $this->user_object->user_login,

			'{{billing.first_name}}' => '',
			'{{billing.last_name}}'  => '',
			'{{billing.company}}'    => '',
			'{{billing.country}}'    => '',
			'{{billing.address}}'    => '',
			'{{billing.address_1}}'  => '',
			'{{billing.address_2}}'  => '',
			'{{billing.postcode}}'   => '',
			'{{billing.city}}'       => '',
			'{{billing.state}}'      => '',
			'{{billing.email}}'      => '',
			'{{billing.phone}}'      => '',

			'{{shipping.first_name}}' => '',
			'{{shipping.last_name}}'  => '',
			'{{shipping.company}}'    => '',
			'{{shipping.country}}'    => '',
			'{{shipping.address}}'    => '',
			'{{shipping.address_1}}'  => '',
			'{{shipping.address_2}}'  => '',
			'{{shipping.postcode}}'   => '',
			'{{shipping.city}}'       => '',
			'{{shipping.state}}'      => '',
			'{{shipping.method}}'     => '',
			'{{shipping.email}}'      => '',
			'{{shipping.phone}}'      => '',

			'{{url.reset_password}}' => MrmCommon::is_wc_active() ? esc_url(
				add_query_arg(
					array(
						'key' => $this->password_reset_key,
						'id'  => $this->user_object->ID,
					),
					wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
				)
			) : esc_url( wp_lostpassword_url() ),
		);

		if ( !empty( $this->order_object ) ) {
			$refunds     = $this->order_object->get_refunds();
			$refund_html = '';
			if ( $refunds ) {
				foreach ( $refunds as $id => $refund ) {
					$refund_html .= '<div>' . wc_price( '-' . $refund->get_amount(), array( 'currency' => $this->order_object->get_currency() ) ) . '</div>';
				}
			}

			// set order tax
			if ( 'excl' === get_option( 'woocommerce_tax_display_cart' ) && wc_tax_enabled() ) {
				if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
					$taxes = array();
					foreach ( $this->order_object->get_tax_totals() as $key => $tax ) {
						$taxes[] = $tax->label . ' : ' . $tax->formatted_amount;
					}
					$tax = implode( ',', $taxes );
				} else {
					$tax = wc_price( $this->order_object->get_total_tax(), array( 'currency' => $this->order_object->get_currency() ) );
				}
			}

			$notes       = $this->order_object->get_customer_order_notes();
			$latest_note = reset($notes);
			$latest_note = !empty($latest_note->comment_content) ? wpautop( wptexturize( make_clickable( $latest_note->comment_content ) ) ) : '';

			$shortcodes['{{customer.name}}']      = $this->order_object->get_formatted_billing_full_name();
			$shortcodes['{{customer.note}}']      = $latest_note;
			$shortcodes['{{customer.first_name}}'] = $this->order_object->get_billing_first_name();
			$shortcodes['{{customer.last_name}}'] = $this->order_object->get_billing_last_name();

			$shortcodes['{{order_details.order_id}}']             = intval( $this->order_object->get_id() );
			$shortcodes['{{order_details.order_date}}']           = date_i18n( $date_format, strtotime( $this->order_object->get_date_created() ) );
			$shortcodes['{{order_details.order_discount}}']       = $this->order_object->get_discount_to_display();
			$shortcodes['{{order_details.order_fully_refunded}}'] = $refund_html;
			$shortcodes['{{order_details.order_number}}']         = $this->order_object->get_order_number();
			$shortcodes['{{order_details.order_partial_refund}}'] = $refund_html;
			$shortcodes['{{order_details.order_received_url}}']   = $this->order_object->get_checkout_order_received_url();
			$shortcodes['{{order_details.order_shipping}}']       = $this->order_object->get_shipping_to_display();
			$shortcodes['{{order_details.order_subtotal}}']       = $this->order_object->get_subtotal_to_display();
			$shortcodes['{{order_details.order_total}}']          = $this->order_object->get_formatted_order_total();
			$shortcodes['{{order_details.order_tax}}']            = ( isset( $tax ) ) ? $tax : '';
			$shortcodes['{{order_details.payment_method}}']       = ( !empty( $this->order_object->get_payment_method_title() ) ) ? $this->order_object->get_payment_method_title() : '';
			$shortcodes['{{url.payment}}']                        = $this->order_object->get_checkout_payment_url();

			$shortcodes['{{billing.first_name}}'] = $this->order_object->get_billing_first_name();
			$shortcodes['{{billing.last_name}}']  = $this->order_object->get_billing_last_name();
			$shortcodes['{{billing.company}}']    = $this->order_object->get_billing_company();
			$shortcodes['{{billing.country}}']    = $this->order_object->get_billing_country();
			$shortcodes['{{billing.address}}']    = $this->order_object->get_formatted_billing_address();
			$shortcodes['{{billing.address_1}}']  = $this->order_object->get_billing_address_1();
			$shortcodes['{{billing.address_2}}']  = $this->order_object->get_billing_address_2();
			$shortcodes['{{billing.postcode}}']   = $this->order_object->get_billing_postcode();
			$shortcodes['{{billing.city}}']       = $this->order_object->get_billing_city();
			$shortcodes['{{billing.state}}']      = $this->order_object->get_billing_state();
			$shortcodes['{{billing.email}}']      = $this->order_object->get_billing_email();
			$shortcodes['{{billing.phone}}']      = $this->order_object->get_billing_phone();

			$shortcodes['{{shipping.first_name}}'] = $this->order_object->get_shipping_first_name();
			$shortcodes['{{shipping.last_name}}']  = $this->order_object->get_shipping_last_name();
			$shortcodes['{{shipping.company}}']    = $this->order_object->get_shipping_company();
			$shortcodes['{{shipping.country}}']    = $this->order_object->get_shipping_country();
			$shortcodes['{{shipping.address}}']    = $this->order_object->get_formatted_shipping_address();
			$shortcodes['{{shipping.address_1}}']  = $this->order_object->get_shipping_address_1();
			$shortcodes['{{shipping.address_2}}']  = $this->order_object->get_shipping_address_2();
			$shortcodes['{{shipping.postcode}}']   = $this->order_object->get_shipping_postcode();
			$shortcodes['{{shipping.city}}']       = $this->order_object->get_shipping_city();
			$shortcodes['{{shipping.state}}']      = $this->order_object->get_shipping_state();
			$shortcodes['{{shipping.method}}']     = $this->order_object->get_shipping_method();
			$shortcodes['{{shipping.email}}']      = $this->order_object->get_billing_email();
			$shortcodes['{{shipping.phone}}']      = $this->order_object->get_shipping_phone();
		}

		return $shortcodes;
	}
}
