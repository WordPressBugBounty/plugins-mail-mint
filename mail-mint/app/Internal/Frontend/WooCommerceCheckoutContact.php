<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Frontend
 */

namespace Mint\MRM\Internal\Frontend;

use Mint\MRM\Admin\API\Controllers\MessageController;
use Mint\MRM\Admin\API\Controllers\TagController;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataStores\ContactData;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * Manages assigning WC user in mrm contact from WC checkout
 *
 * @package /app/Internal/Frontend
 * @since 1.0.0
 */
class WooCommerceCheckoutContact {

	use Singleton;

	/**
	 * WooCommerce settings from wp_options table
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $setting_options;

	/**
	 * Initialize actions
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		$this->setting_options = get_option( '_mrm_woocommerce_settings', array() );
		$checkbox_enabled      = isset( $this->setting_options[ 'enable' ] ) ? $this->setting_options[ 'enable' ] : false;
		if ( $checkbox_enabled ) {
			add_action( 'woocommerce_checkout_before_terms_and_conditions', array( $this, 'add_consent_checkbox' ) );
			add_action( 'woocommerce_checkout_create_order', array( $this, 'add_consent_to_order_meta' ) );
			add_action( 'woocommerce_new_order', array( $this, 'register_user_as_contact' ), 10, 2 );

			add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'register_block_consent_checkbox' ), 1 );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_block_consent_to_order_meta' ), 10, 2 );
		}
	}

	/**
	 * Add consent checkbox to add contact after checkout [before checkout button]
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_consent_checkbox() {
		$label      = isset( $this->setting_options[ 'checkbox_label' ] ) ? $this->setting_options[ 'checkbox_label' ] : __( 'Register me as a contact after checkout.', 'mrm' );
		$user_email = is_user_logged_in() ? wp_get_current_user()->user_email : false;

		if ( $user_email && ! ContactModel::is_contact_exist( $user_email ) || ! is_user_logged_in() ) {
			woocommerce_form_field(
				'mintmail_woocommerce_optin_consent',
				[
					'type'        => 'checkbox',
					'label'       => esc_html( $label ),
					'input_class' => [ 'mintmrm_add_contact_consent_checkbox' ],
					'label_class' => [ 'woocommerce-form__label', 'woocommerce-form__label-for-checkbox', 'checkbox' ],
				],
				0
			);
		}
	}

	/**
	 * Register the opt-in consent checkbox for the WooCommerce block checkout.
	 * Classic checkout uses add_consent_checkbox() via the shortcode hook instead.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_block_consent_checkbox() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$label = $this->setting_options['checkbox_label'] ?? __( 'I would like to receive exclusive emails with discounts and product information.', 'mrm' );

		woocommerce_register_additional_checkout_field(
			[
				'id'                => 'mailmint/optin-consent',
				'label'             => wp_strip_all_tags( $label ),
				'location'          => 'order',
				'type'              => 'checkbox',
				'required'          => false,
				'sanitize_callback' => array( $this, 'sanitize_block_consent_checkbox' ),
			]
		);
	}

	/**
	 * Normalize the block checkout consent checkbox value to '1' or '0'.
	 *
	 * @param mixed $value Raw value from Store API request.
	 * @return string
	 */
	public function sanitize_block_consent_checkbox( $value ) {
		return ( true === $value || '1' === $value || 1 === $value || 'true' === $value ) ? '1' : '0';
	}

	/**
	 * Map block checkout consent to the same order meta key used by classic checkout,
	 * so register_user_as_contact() works identically for both checkout types.
	 *
	 * @param \WC_Order       $order   Order being processed.
	 * @param \WP_REST_Request $request Store API request.
	 * @return void
	 */
	public function save_block_consent_to_order_meta( $order, $request ) {
		if ( ! $order instanceof \WC_Order || ! $request instanceof \WP_REST_Request ) {
			return;
		}

		$additional = (array) ( $request->get_param( 'additional_fields' ) ?? [] );

		if ( ! array_key_exists( 'mailmint/optin-consent', $additional ) && $request->get_body() ) {
			$body = json_decode( $request->get_body(), true );
			if ( is_array( $body ) && ! empty( $body['additional_fields'] ) ) {
				$additional = array_merge( $additional, (array) $body['additional_fields'] );
			}
		}

		$value    = $additional['mailmint/optin-consent'] ?? null;
		$accepted = ( true === $value || '1' === $value || 1 === $value || 'true' === $value );
		$order->update_meta_data( '_mrm_newsletter_subscription', $accepted ? 'Yes' : 'No' );
	}

	/**
	 * Register current user as Mint MRM contact
	 *
	 * @param int|string       $order_id WooCommerce Order ID.
	 * @param \WC_Order|object $order WooCommerce Order Object.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_user_as_contact( $order_id, $order ) {
		$consent_accepted = 'Yes' === $order->get_meta( '_mrm_newsletter_subscription' );
		$logged_in_user_data = is_user_logged_in() ? wp_get_current_user() : array();

		if ( $consent_accepted ) {
			if ( ! empty( $logged_in_user_data ) ) {
				$user_email      = $logged_in_user_data->user_email;
				$wc_user_id      = $logged_in_user_data->ID;
				$user_first_name = $logged_in_user_data->first_name;
				$user_last_name  = $logged_in_user_data->last_name;
				$phone           = $logged_in_user_data->get( 'billing_phone' );
				$address_line_1  = $logged_in_user_data->get( 'billing_address_1' );
				$address_line_2  = $logged_in_user_data->get( 'billing_address_2' );
				$company         = $logged_in_user_data->get( 'billing_company' );
				$postcode        = $logged_in_user_data->get( 'billing_postcode' );
				$country         = $logged_in_user_data->get( 'billing_country' );
				$state           = $logged_in_user_data->get( 'billing_state' );
				$city            = $logged_in_user_data->get( 'billing_city' );
			} else {
				$user_email      = WC()->checkout()->get_value( 'billing_email' );
				$user_first_name = WC()->checkout()->get_value( 'billing_first_name' );
				$user_last_name  = WC()->checkout()->get_value( 'billing_last_name' );
				$phone           = WC()->checkout()->get_value( 'billing_phone' );
				$address_line_1  = WC()->checkout()->get_value( 'billing_address_1' );
				$address_line_2  = WC()->checkout()->get_value( 'billing_address_2' );
				$company         = WC()->checkout()->get_value( 'billing_company' );
				$postcode        = WC()->checkout()->get_value( 'billing_postcode' );
				$country         = WC()->checkout()->get_value( 'billing_country' );
				$state           = WC()->checkout()->get_value( 'billing_state' );
				$city            = WC()->checkout()->get_value( 'billing_city' );
			}

			if ( $user_email && ! ContactModel::is_contact_exist( $user_email ) ) {
				$double_optin        = get_option( '_mrm_optin_settings', array() );
				$double_optin        = isset( $double_optin[ 'enable' ] ) ? $double_optin[ 'enable' ] : true;
				$subscription_status = $double_optin ? 'pending' : 'subscribed';

				$setting_tags  = isset( $this->setting_options[ 'tags' ] ) ? $this->setting_options[ 'tags' ] : array();
				$setting_lists = isset( $this->setting_options[ 'lists' ] ) ? $this->setting_options[ 'lists' ] : array();

				$user_data = array(
					'email'          => $user_email,
					'first_name'     => $user_first_name,
					'last_name'      => $user_last_name,
					'phone'          => $phone,
					'address_line_1' => $address_line_1,
					'address_line_2' => $address_line_2,
					'company_name'   => $company,
					'source'         => esc_html__( 'WooCommerce Checkout', 'mrm' ),
					'status'         => $subscription_status,
					'lists'          => $setting_lists,
					'tags'           => $setting_tags,
					'meta_fields'    => array(
						'phone_number'   => $phone,
						'address_line_1' => $address_line_1,
						'address_line_2' => $address_line_2,
						'country'        => $country,
						'state'          => $state,
						'city'           => $city,
						'postal'         => $postcode,
						'company'        => $company,
					),
				);

				$contact    = new ContactData( $user_email, $user_data );
				$contact_id = ContactModel::insert( $contact );

				if ( $contact_id ) {
					if ( ! empty( $setting_tags ) ) {
						ContactGroupModel::set_tags_to_contact( $setting_tags, $contact_id );
					}

					if ( ! empty( $setting_lists ) ) {
						ContactGroupModel::set_lists_to_contact( $setting_lists, $contact_id );
					}

					$meta_data = array();

					if ( ! empty( $wc_user_id ) ) {
						$meta_data[ 'meta_fields' ][ '_wc_customer_id' ] = $wc_user_id;
					}
					$is_ip_store = get_option( '_mint_compliance' );
					$is_ip_store = isset( $is_ip_store['anonymize_ip'] ) ? $is_ip_store['anonymize_ip'] : 'no';
					if ( 'yes' === $is_ip_store ) {
						$meta_data[ 'meta_fields' ][ '_ip_address' ] = MrmCommon::get_user_ip();
					}

					if ( !empty( $meta_data ) ) {
						ContactModel::update_meta_fields( $contact_id, $meta_data );
					}

					if ( $double_optin ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
				}
			}
		}
	}

	/**
	 * Add subscription consent message in order meta
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_consent_to_order_meta( $order ) {
		$consent_accepted = WC()->checkout()->get_value( 'mintmail_woocommerce_optin_consent' );
		$order->update_meta_data( '_mrm_newsletter_subscription', $consent_accepted ? 'Yes' : 'No' );
	}
}
