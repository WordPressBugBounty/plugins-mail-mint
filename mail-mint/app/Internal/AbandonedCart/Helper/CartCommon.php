<?php
/**
 * Class CartCommon
 *
 * Provides common helper functions related to abandoned cart functionality.
 *
 * Moved from Mail Mint Pro in 1.31.0 when cart tracking became a Free feature.
 * Recovery-specific behaviour (the restore handler and the automation triggers)
 * remains in Pro; this class only covers capture, formatting and reporting.
 *
 * @package Mint\MRM\Internal\AbandonedCart\Helper
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Helper;

use DOMDocument;
use DOMXPath;
use Mint\MRM\Database\Enums\ContactStatus;
use Mint\MRM\Internal\AbandonedCart\Scheduler\AbandonedCartScheduler;
use MintMail\App\Internal\Automation\Action\AutomationAction;
use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;
use WC_Blocks_Utils;
use WC_Customer;
use WC_Order;
use WC_Product;
use WC_Session;

/**
 * Class CartCommon
 *
 * Provides common helper functions related to abandoned cart functionality.
 */
class CartCommon {

	/**
	 * The option key for storing abandoned cart settings in WordPress options.
	 *
	 * @var string
	 * @since 1.5.0
	 */
	protected static $option_key = '_mint_abandoned_cart_settings';

	/**
	 * Status given to a contact created from an abandoned cart when the site has not
	 * chosen otherwise.
	 *
	 * Transactional rather than subscribed: the address came from a checkout field, so
	 * there is no marketing consent to infer from it. It still reaches recovery mail,
	 * because an automation Send Email step marked transactional delivers to this status.
	 *
	 * @var string
	 * @since 1.32.0
	 */
	const DEFAULT_NEW_CONTACT_STATUS = ContactStatus::TRANSACTIONAL;

	/**
	 * Returns the default configuration for abandoned carts.
	 *
	 * The default configuration includes various settings such as enabling abandoned cart functionality,
	 * wait periods, exclude periods, GDPR consent requirement, lists, and tags.
	 *
	 * @return array An array containing the default abandoned cart configuration.
	 *
	 * @since 1.5.0
	 */
	public static function abandoned_cart_default_configuration() {
		return array(
			'enable'             => false,
			'wait_periods'       => 15,
			'exclude_periods'    => 7,
			'gdpr_consent'       => 'require',
			'consent_text'       => 'Your email and cart are saved so we can send you email reminders about this order. {{no_thanks label="No Thanks"}}',
			'lists'              => array(),
			'tags'               => array(),
			'disable_roles'      => array(),
			'recovered_statuses' => self::default_recovered_statuses(),
			'cool_off_period'    => 10,
			'enable_blacklist'   => false,
			'blacklist_emails'   => array(),
			'new_contact_status' => self::DEFAULT_NEW_CONTACT_STATUS,
		);
	}

	/**
	 * The statuses a cart-created contact may be given.
	 *
	 * Deliberately narrower than the full contact status list. `pending` is excluded
	 * because a cart contact never sees a double opt-in confirmation flow — the address
	 * was typed into a checkout, not a signup form — so leaving them pending only means
	 * recovery mail silently never sends.
	 *
	 * @return string[]
	 * @since 1.32.0
	 */
	public static function new_contact_status_options() {
		return array( ContactStatus::TRANSACTIONAL, ContactStatus::SUBSCRIBED );
	}

	/**
	 * The status to give a contact created from an abandoned cart.
	 *
	 * Resolved at read time rather than trusting the stored option: sites that saved
	 * their cart settings before this setting existed hold an array with no
	 * `new_contact_status` key at all, and get_option()'s default only applies when the
	 * whole row is missing.
	 *
	 * @return string One of new_contact_status_options().
	 * @since 1.32.0
	 */
	public static function get_new_contact_status() {
		$settings = self::get_abandoned_cart_settings();
		$status   = isset( $settings['new_contact_status'] ) ? sanitize_key( $settings['new_contact_status'] ) : '';

		return in_array( $status, self::new_contact_status_options(), true ) ? $status : self::DEFAULT_NEW_CONTACT_STATUS;
	}

	/**
	 * Persist a "Status for New Contacts" value onto sites that predate the setting.
	 *
	 * A store that was ALREADY capturing carts *without* double opt-in is grandfathered
	 * onto `subscribed`. Before this setting existed such a cart contact was created as
	 * `subscribed`, which is what let a recovery automation deliver even with "Mark this
	 * email as Transactional" unticked — and that box defaults to unticked on every Send
	 * Email step. Seeding `transactional` there would have stopped recovery mail silently,
	 * with no error and nothing in the UI to explain it.
	 *
	 * Everyone else takes `transactional`:
	 *
	 * - Double opt-in ON. The cart contact used to be created as `pending`, so unmarked
	 *   Send Email steps were already skipped and no recovery mail was going out. Seeding
	 *   `subscribed` here would not preserve that — it would START mailing contacts who
	 *   never confirmed, on a site whose admin deliberately required confirmation.
	 *   `transactional` keeps delivery exactly as quiet as it is today while leaving the
	 *   contact reachable by a step the admin explicitly marks transactional.
	 * - Not capturing carts yet. No behaviour to preserve, so take the safer default.
	 *
	 * An existing value is never overwritten, and a site that has never saved cart settings
	 * at all has no row to write to — its read path resolves to the default instead.
	 *
	 * @return void
	 * @since 1.32.0
	 */
	public static function maybe_seed_new_contact_status() {
		$settings = get_option( self::$option_key );

		if ( ! is_array( $settings ) || isset( $settings['new_contact_status'] ) ) {
			return;
		}

		$grandfathered = ! empty( $settings['enable'] ) && ! MrmCommon::is_double_optin_enable();

		$settings['new_contact_status'] = $grandfathered
			? ContactStatus::SUBSCRIBED
			: self::DEFAULT_NEW_CONTACT_STATUS;

		update_option( self::$option_key, $settings );
	}

	/**
	 * The WooCommerce order statuses that mark a tracked cart as recovered by default.
	 *
	 * Mirrors the statuses WooCommerce itself treats as a completed sale, so the
	 * out-of-the-box behaviour matches what a store owner would expect without
	 * visiting the setting.
	 *
	 * @return array List of status slugs, without the `wc-` prefix.
	 * @since 1.31.0
	 */
	public static function default_recovered_statuses() {
		return array( 'processing', 'completed' );
	}

	/**
	 * Returns the order statuses configured to mark a cart as recovered.
	 *
	 * Resolved at read time rather than relying on the option default: sites that
	 * saved their cart settings before this option existed hold an array with no
	 * `recovered_statuses` key at all, and get_option()'s default only applies when
	 * the whole row is missing. Falling back here keeps those sites on the previous
	 * behaviour instead of silently recovering nothing.
	 *
	 * @return array List of status slugs, without the `wc-` prefix.
	 * @since 1.31.0
	 */
	public static function get_recovered_statuses() {
		$settings = self::get_abandoned_cart_settings();
		$statuses = isset( $settings['recovered_statuses'] ) ? $settings['recovered_statuses'] : self::default_recovered_statuses();

		if ( ! is_array( $statuses ) ) {
			return self::default_recovered_statuses();
		}

		return array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );
	}

	/**
	 * Whether a WooCommerce order status counts as a successful cart recovery.
	 *
	 * @param string $order_status Order status slug, with or without the `wc-` prefix.
	 *
	 * @return bool True when the status marks the cart recovered.
	 * @since 1.31.0
	 */
	public static function is_win_order_status( $order_status ) {
		$order_status = (string) preg_replace( '/^wc-/', '', (string) $order_status );
		$result       = in_array( $order_status, self::get_recovered_statuses(), true );

		/**
		 * Filters whether an order status marks a tracked cart as recovered.
		 *
		 * @param bool   $result       Whether the status counts as a recovery.
		 * @param string $order_status The order status slug, without the `wc-` prefix.
		 *
		 * @since 1.31.0
		 */
		return (bool) apply_filters( 'mint_abandoned_cart_is_win_status', $result, $order_status );
	}

	/**
	 * The number of days a customer's recent order should suppress further cart
	 * tracking activity for, i.e. the cool-off period.
	 *
	 * Resolved at read time for the same reason as get_recovered_statuses(): sites
	 * that saved their cart settings before this option existed hold an array with
	 * no `cool_off_period` key, and get_option()'s default only covers a missing row.
	 *
	 * @return int Number of days. 0 means the cool-off period is disabled.
	 * @since 1.31.0
	 */
	public static function get_cool_off_period_days() {
		$settings = self::get_abandoned_cart_settings();
		$days     = isset( $settings['cool_off_period'] ) ? $settings['cool_off_period'] : 10;

		return max( 0, (int) $days );
	}

	/**
	 * Whether the given email has a recent qualifying WooCommerce order that should
	 * put further cart tracking activity for that customer on hold.
	 *
	 * A customer who already bought does not need another "you left something in
	 * your cart" nudge for a few days — the same order that would otherwise mark
	 * the cart recovered also earns a quiet period, so the setting doubles as a
	 * spam guard on whatever consumes it (e.g. a recovery automation).
	 *
	 * Reuses the recovered-statuses setting as the qualifying order statuses rather
	 * than introducing a second status list: an order that counts as a recovery is
	 * exactly the kind of order that should also trigger the cool-off.
	 *
	 * @param string $email Customer email address to check.
	 *
	 * @return bool True when a qualifying order exists within the cool-off window.
	 * @since 1.31.0
	 */
	public static function is_within_cool_off_period( $email ) {
		if ( empty( $email ) ) {
			return false;
		}

		$days = self::get_cool_off_period_days();

		if ( ! $days ) {
			return false;
		}

		$cool_off_since = current_time( 'timestamp' ) - $days * DAY_IN_SECONDS; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		return self::customer_has_order( $email, 0, $cool_off_since, self::get_recovered_statuses() );
	}

	/**
	 * The WooCommerce order statuses that should stop cart emails going out.
	 *
	 * A wider set than the recovered statuses on purpose: the two questions are
	 * different. "Should we count this as a recovery?" answers to the store owner's
	 * configured win statuses. "Should we stop telling this customer they left something
	 * behind?" answers to whether they have an order at all — a bank transfer sitting at
	 * pending, an off-site gateway that has not called back yet, or a card that was
	 * declined are all cases where the customer has been through checkout and a cart
	 * nudge would be wrong.
	 *
	 * Two statuses are excluded, and both matter:
	 *
	 * `checkout-draft` is created by the WooCommerce Checkout Block as soon as a customer
	 * starts filling the form, before they have committed to anything. Treating it as a
	 * reason to stay quiet would suppress nearly every cart email on a Blocks store.
	 *
	 * `cancelled` means the customer or the shop abandoned the order deliberately, so
	 * chasing that cart again is legitimate.
	 *
	 * @return array List of status slugs, without the `wc-` prefix.
	 * @since 1.31.1
	 */
	public static function get_suppressing_order_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		$excluded = array( 'cancelled', 'checkout-draft' );
		$statuses = array();

		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$slug = (string) preg_replace( '/^wc-/', '', $slug );

			if ( in_array( $slug, $excluded, true ) ) {
				continue;
			}

			$statuses[] = $slug;
		}

		/**
		 * Filters the order statuses that suppress further abandoned cart emails.
		 *
		 * @param array $statuses List of status slugs, without the `wc-` prefix.
		 *
		 * @since 1.31.1
		 */
		return (array) apply_filters( 'mint_abandoned_cart_suppressing_order_statuses', $statuses );
	}

	/**
	 * Whether the owner of this cart has placed an order since the cart was created.
	 *
	 * @param array $cart Cart row; needs email, user_id and created_at.
	 *
	 * @return bool True when an order exists and cart emails should stop.
	 * @since 1.31.1
	 */
	public static function has_order_since( $cart ) {
		if ( empty( $cart ) || ! is_array( $cart ) ) {
			return false;
		}

		$email   = ! empty( $cart['email'] ) ? $cart['email'] : '';
		$user_id = ! empty( $cart['user_id'] ) ? (int) $cart['user_id'] : 0;

		if ( '' === $email && $user_id <= 0 ) {
			return false;
		}

		/*
		 * Scope the search to the cart's own lifetime, with a little slack for clock skew
		 * between the cart write and the order write. Without a floor, a customer who
		 * bought once a year ago would never receive a cart email again — a total feature
		 * kill that in the field is indistinguishable from "abandoned cart is broken".
		 */
		$created_at = ! empty( $cart['created_at'] ) ? strtotime( $cart['created_at'] ) : 0;
		$since      = $created_at ? $created_at - 5 * MINUTE_IN_SECONDS : 0;

		return self::customer_has_order( $email, $user_id, $since, self::get_suppressing_order_statuses() );
	}

	/**
	 * Whether a customer has a qualifying order at or after the given time.
	 *
	 * The single order-lookup path for the cart feature, shared by the send-suppression
	 * gate and the cool-off period so the two cannot drift apart.
	 *
	 * No HPOS branching: wc_get_orders() abstracts custom-table and legacy post storage,
	 * and supports both customer_id and billing_email as query args under either. The
	 * registered customer is tried first, matching the precedence the recovery handlers
	 * already use, because a logged-in shopper may check out with a different billing
	 * address than their account email.
	 *
	 * @param string $email    Billing email to match, may be empty.
	 * @param int    $user_id  Registered customer ID to match, 0 to skip.
	 * @param int    $since    Unix timestamp floor; 0 means no floor.
	 * @param array  $statuses Qualifying order statuses, without the `wc-` prefix.
	 *
	 * @return bool
	 * @since 1.31.1
	 */
	public static function customer_has_order( $email, $user_id, $since, $statuses = array() ) {
		if ( ! function_exists( 'wc_get_orders' ) || empty( $statuses ) ) {
			return false;
		}

		$email   = is_string( $email ) ? strtolower( trim( $email ) ) : '';
		$user_id = (int) $user_id;
		$since   = (int) $since;

		if ( '' === $email && $user_id <= 0 ) {
			return false;
		}

		// A five-email sequence can process several jobs in one worker run, and each one
		// would otherwise repeat the same lookup.
		static $memo = array();

		$key = md5( $email . '|' . $user_id . '|' . $since . '|' . implode( ',', $statuses ) );

		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}

		$base = array(
			'status'  => $statuses,
			'limit'   => 1,
			'return'  => 'ids',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( $since > 0 ) {
			$base['date_created'] = '>=' . $since;
		}

		$lookups = array();

		if ( $user_id > 0 ) {
			$lookups[] = array( 'customer_id' => $user_id );
		}

		if ( '' !== $email ) {
			$lookups[] = array( 'billing_email' => $email );
		}

		$found = false;

		foreach ( $lookups as $lookup ) {
			$orders = wc_get_orders( array_merge( $base, $lookup ) );

			if ( ! empty( $orders ) ) {
				$found = true;
				break;
			}
		}

		$memo[ $key ] = $found;

		return $found;
	}

	/**
	 * Whether the given email is blacklisted from abandoned cart tracking.
	 *
	 * Pro-gated: the setting can only be enabled while Mail Mint Pro is active, so a
	 * site that had it configured before Pro was deactivated does not silently keep
	 * blocking tracking for those addresses.
	 *
	 * @param string $email Email address to check.
	 *
	 * @return bool True when the email (or its domain) is on the blacklist.
	 * @since 1.31.0
	 */
	public static function is_email_blacklisted( $email ) {
		if ( empty( $email ) || ! MrmCommon::is_mailmint_pro_active() ) {
			return false;
		}

		$settings = self::get_abandoned_cart_settings();

		if ( empty( $settings['enable_blacklist'] ) ) {
			return false;
		}

		$entries = isset( $settings['blacklist_emails'] ) ? $settings['blacklist_emails'] : array();

		if ( empty( $entries ) || ! is_array( $entries ) ) {
			return false;
		}

		$email  = strtolower( trim( $email ) );
		$domain = substr( strrchr( $email, '@' ), 1 );

		foreach ( $entries as $entry ) {
			$entry = strtolower( ltrim( trim( (string) $entry ), '@' ) );

			if ( '' === $entry ) {
				continue;
			}

			if ( $entry === $email || ( $domain && $entry === $domain ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the WooCommerce order statuses a cart can be marked recovered on.
	 *
	 * Terminal failure states are excluded: an order that was refunded, failed or
	 * cancelled did not recover the cart, so offering them would only let an admin
	 * configure a contradiction.
	 *
	 * @return array List of `[ 'id' => slug, 'label' => name ]` entries.
	 * @since 1.31.0
	 */
	public static function get_selectable_order_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		$excluded = array( 'refunded', 'failed', 'cancelled', 'checkout-draft' );
		$statuses = array();

		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$slug = (string) preg_replace( '/^wc-/', '', $slug );

			if ( in_array( $slug, $excluded, true ) ) {
				continue;
			}

			$statuses[] = array(
				'id'    => $slug,
				'label' => $label,
			);
		}

		return $statuses;
	}

	/**
	 * Retrieves the abandoned cart settings.
	 *
	 * @return array The abandoned cart settings.
	 *
	 * @since 1.5.0
	 */
	public static function get_abandoned_cart_settings() {
		$default  = self::abandoned_cart_default_configuration();
		$settings = get_option( self::$option_key, $default );
		return is_array( $settings ) && ! empty( $settings ) ? $settings : $default;
	}

	/**
	 * Create a random abandoned cart token.
	 *
	 * This function generates a random token of the specified length.
	 *
	 * @param int  $length          The length of the token (default: 25).
	 * @param bool $case_sensitive  Whether to include uppercase letters in the token (default: true).
	 *
	 * @return string The generated token.
	 * @since 1.5.0
	 */
	public static function create_abandoned_cart_token( $length = 25, $case_sensitive = true ) {
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';

		if ( $case_sensitive ) {
			$chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		}

		$token        = '';
		$chars_length = strlen( $chars );

		for ( $i = 0; $i < $length; $i++ ) {
			$token .= $chars[ random_int( 0, $chars_length - 1 ) ];
		}

		return $token;
	}

	/**
	 * Calculate the total value of abandoned cart items.
	 *
	 * This function calculates the total value of abandoned cart items by iterating through each item in the cart
	 * and multiplying the product price by the quantity.
	 *
	 * @param array $data The data containing the cart items.
	 *
	 * @return array The updated data with the calculated total.
	 * @since 1.5.0
	 */
	public static function get_abandoned_cart_totals( $data ) {
		$calculated_total = 0;

		$cart_items = isset( $data['items'] ) ? maybe_unserialize( $data['items'] ) : array();

		if ( !empty( $cart_items ) ) {
			foreach ( $cart_items as $key => $item ) {
				$product_id = !empty( $item[ 'variation_id' ] ) ? (int) $item[ 'variation_id' ] : $item[ 'product_id' ];

				$product  = wc_get_product( $product_id );
				$price    = $product->get_price();
				$quantity = isset( $item[ 'quantity' ] ) ? $item[ 'quantity' ] : 0;

				// Calculate the line total by multiplying the price by the quantity.
				$line_total = (float) $price * (int) $quantity;

				// Add the line total to the calculated total.
				$calculated_total += $line_total;
			}
		}

		// Update the data array with the calculated total.
		$data['total'] = wc_format_decimal( $calculated_total, wc_get_price_decimals() );
		// Return the updated data.
		return $data;
	}

	/**
	 * Prepare checkout data for abandoned cart.
	 *
	 * This function retrieves the necessary checkout data from the customer object
	 * and returns it in a formatted array for the abandoned cart.
	 *
	 * @param array $data The abandoned cart data.
	 * @return array The formatted checkout data.
	 * @since 1.5.0
	 */
	public static function prepare_checkout_data( $data ) {
		$customer = new WC_Customer( $data['user_id'] );

		if ( empty( $customer ) ) {
			return array(
				'fields' => array(),
			);
		}

		return array(
			'fields' => array(
				'billing_first_name'  => $customer->get_billing_first_name(),
				'billing_last_name'   => $customer->get_billing_last_name(),
				'billing_company'     => $customer->get_billing_company(),
				'billing_country'     => $customer->get_billing_country(),
				'billing_address_1'   => $customer->get_billing_address_1(),
				'billing_address_2'   => $customer->get_billing_address_2(),
				'billing_city'        => $customer->get_billing_city(),
				'billing_state'       => $customer->get_billing_state(),
				'billing_postcode'    => $customer->get_billing_postcode(),
				'billing_phone'       => $customer->get_billing_phone(),
				'billing_email'       => $customer->get_billing_email(),
				'shipping_first_name' => $customer->get_shipping_first_name(),
				'shipping_last_name'  => $customer->get_shipping_last_name(),
				'shipping_company'    => $customer->get_shipping_company(),
				'shipping_country'    => $customer->get_shipping_country(),
				'shipping_address_1'  => $customer->get_shipping_address_1(),
				'shipping_address_2'  => $customer->get_shipping_address_2(),
				'shipping_city'       => $customer->get_shipping_city(),
				'shipping_state'      => $customer->get_shipping_state(),
				'shipping_postcode'   => $customer->get_shipping_postcode(),
			),
		);
	}


	/**
	 * Checks if the provided timestamp is within the specified wait time in minutes.
	 *
	 * @param int|string $timestamp The timestamp to compare.
	 * @return bool True if the provided timestamp is within the wait time; otherwise, false.
	 * @since 1.5.0
	 */
	public static function is_within_wait_time_minutes( $timestamp ) {
		if ( !$timestamp ) {
			return false;
		}
		$setting            = self::get_abandoned_cart_settings();
		$wait_time          = !empty( $setting['wait_periods'] ) ? $setting['wait_periods'] : 15;
		$current_time       = current_time( 'timestamp' ); // phpcs:ignore.
		$provided_time      = strtotime( $timestamp ); // Convert provided timestamp to Unix timestamp format.
		$time_difference    = abs( $current_time - $provided_time ); // Calculate the absolute time difference.
		$minutes_difference = round( $time_difference / 60 ); // Convert time difference to minutes.
		return $minutes_difference <= $wait_time;
	}

	/**
	 * Enqueues a user after adding to cart.
	 *
	 * @param array $user_data The user data to enqueue.
	 * @param int   $abandoned_cart_id The abandoned cart ID.
	 * @return void
	 * @since 1.5.0
	 */
	public static function enqueue_user_after_add_to_cart( $user_data, $abandoned_cart_id ) {
		if ( empty( $user_data ) ) {
			return;
		}

		$action_scheduler = new AbandonedCartScheduler();
		if ( $action_scheduler->hasScheduledAction( ABANDONED_CART_SCHEDULER, MINT_ABANDONED_CART_GROUP . '_' . $abandoned_cart_id ) ) {
			return;
		}

		$time = self::get_seconds_until_next_wait_time();
		$action_scheduler->schedule( time() + $time, ABANDONED_CART_SCHEDULER, MINT_ABANDONED_CART_GROUP . '_' . $abandoned_cart_id, $user_data );
	}

	/**
	 * Retrieves the number of seconds until the next wait time for abandoned cart.
	 *
	 * @return int The number of seconds until the next wait time.
	 * @since 1.5.0
	 */
	public static function get_seconds_until_next_wait_time() {
		$setting   = self::get_abandoned_cart_settings();
		$wait_time = !empty( $setting['wait_periods'] ) ? $setting['wait_periods'] : 15;
		return ceil( ( $wait_time * 60 ) );
	}

	/**
	 * Enqueues cart creation time for scheduling abandoned cart creation.
	 *
	 * @param array $enqueue_data The data to be enqueued for abandoned cart creation.
	 * @param int   $abandoned_cart_id The abandoned cart ID.
	 *
	 * @return void
	 * @since 1.5.0
	 */
	public static function enqueue_cart_creation_time( $enqueue_data, $abandoned_cart_id ) {
		if ( empty( $enqueue_data ) ) {
			return;
		}
		$action_scheduler = new AbandonedCartScheduler();
		if ( $action_scheduler->hasScheduledAction( CART_CREATION_SCHEDULER, MINT_ABANDONED_CART_GROUP . '_' . $abandoned_cart_id ) ) {
			return;
		}
		$time = self::get_seconds_until_next_exclude_time();
		$action_scheduler->schedule( time() + $time, CART_CREATION_SCHEDULER, MINT_ABANDONED_CART_GROUP . '_' . $abandoned_cart_id, $enqueue_data );
	}

	/**
	 * Retrieves the number of seconds until the next exclude time for abandoned cart processing.
	 *
	 * @return int The number of seconds until the next exclude time.
	 * @since 1.5.0
	 */
	public static function get_seconds_until_next_exclude_time() {
		$setting   = self::get_abandoned_cart_settings();
		$wait_time = !empty( $setting['exclude_periods'] ) ? $setting['exclude_periods'] : 7;
		return (int) ceil( ( $wait_time * 24 * 60 * 60 ) );
	}

	/**
	 * Retrieve the order ID from an item.
	 *
	 * @param mixed $item The item object.
	 *
	 * @return string The order ID if found, or an empty string.
	 *
	 * @since 1.5.0
	 */
	public static function get_order_id( $item ) {
		if ( empty( $item['meta']['order_id'] ) ) {
			return array();
		}
		$order_id = !empty( $item['meta']['order_id'] ) ? $item['meta']['order_id'] : '';
		$obj      = wc_get_order( $order_id );
		if ( $obj instanceof WC_Order ) {
			return array(
				'order_id'        => $order_id,
				'order_edit_link' => $obj->get_edit_order_url(),
			);
		}
		return array();
	}


	/**
	 * Retrieves currency information.
	 *
	 * @since 1.5.0
	 *
	 * @return array An array containing currency information.
	 */
	public static function get_currency() {
		$currency        = get_woocommerce_currency();
		$symbols         = get_woocommerce_currency_symbols();
		$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';
		return array(
			'code'              => $currency,
			'precision'         => wc_get_price_decimals(),
			'symbol'            => html_entity_decode( $currency_symbol ),
			'symbolPosition'    => get_option( 'woocommerce_currency_pos' ),
			'decimalSeparator'  => wc_get_price_decimal_separator(),
			'thousandSeparator' => wc_get_price_thousand_separator(),
			'priceFormat'       => html_entity_decode( get_woocommerce_price_format() ),
		);
	}

	/**
	 * Build the same structured currency shape as get_currency(), from a stored code.
	 *
	 * Used when displaying a cart captured before the currency it was tracked in was
	 * persisted, or on a site whose active currency has since changed — the row's own
	 * stored code takes precedence over whatever the store is currently set to.
	 *
	 * @param string $code A currency code (e.g. 'USD').
	 *
	 * @return array An array containing currency information.
	 * @since 1.31.0
	 */
	public static function get_currency_by_code( $code ) {
		$symbols         = get_woocommerce_currency_symbols();
		$currency_symbol = isset( $symbols[ $code ] ) ? $symbols[ $code ] : '';
		return array(
			'code'              => $code,
			'precision'         => wc_get_price_decimals(),
			'symbol'            => html_entity_decode( $currency_symbol ),
			'symbolPosition'    => get_option( 'woocommerce_currency_pos' ),
			'decimalSeparator'  => wc_get_price_decimal_separator(),
			'thousandSeparator' => wc_get_price_thousand_separator(),
			'priceFormat'       => html_entity_decode( get_woocommerce_price_format() ),
		);
	}

	/**
	 * Retrieves the names of items.
	 *
	 * @param array $items The items to retrieve names for.
	 * @return array An array containing the names of the items.
	 * @since 1.5.0
	 */
	public static function get_items( $items ) {
		if ( empty( $items ) ) {
			return '';
		}
		$hide_free_products = self::hide_free_products();
		$names              = array();
		foreach ( $items as $key => $value ) {
			if ( true === $hide_free_products && empty( $value['line_total'] ) ) {
				continue;
			}
			if ( ! $value['product_id'] ) {
				continue;
			}

			$product = wc_get_product( $value['product_id'] );

			$names[ $product->get_id() ] = $product->get_name();
		}
		return $names;
	}

	/**
	 * Retrieves the preview data for an item and checkout data.
	 *
	 * @param array $item          The item data.
	 * @param array $checkout_data The checkout data.
	 * @param bool  $with_media    Whether to resolve product ids and thumbnails. The cart
	 *                             lists never render images, so this stays off there and
	 *                             the extra attachment lookups only run for the details page.
	 *
	 * @return array The preview data.
	 * @since 1.5.0
	 */
	public static function get_preview_data( $item, $checkout_data, $with_media = false ) {
		$data          = array();
		$billing       = array();
		$shipping      = array();
		$others        = array();
		$products      = array();
		$products_data = !empty( $item['items'] ) ? $item['items'] : array();
		$nice_names    = self::get_woocommerce_default_checkout_nice_names();

		if ( is_array( $checkout_data ) && count( $checkout_data ) > 0 ) {
			$fields = ( isset( $checkout_data['fields'] ) ) ? $checkout_data['fields'] : array();
			if ( class_exists( 'WooCommerce' ) ) {
				$available_gateways = WC()->payment_gateways->payment_gateways();
			}

			if ( !empty( $fields ) ) {
				$field    = self::process_checkout_fields( $fields, $nice_names, $available_gateways, $others );
				$billing  = !empty( $field['billing'] ) ? $field['billing'] : array();
				$shipping = !empty( $field['shipping'] ) ? $field['shipping'] : array();
				$others   = !empty( $field['other'] ) ? $field['other'] : array();
			}

			$others = self::process_checkout_data( $checkout_data, $nice_names, $others );
		}

		$product_total = 0;
		if ( is_array( $products_data ) ) {
			$hide_free_products = self::hide_free_products();
			foreach ( $products_data as $product_data ) {
				if ( true === $hide_free_products && empty( $product_data['line_total'] ) ) {
					continue;
				}
				if ( !$product_data['product_id'] ) {
					continue;
				}

				$product = wc_get_product( $product_data['product_id'] );
				// A cart can outlive its products; skip the deleted ones instead of fataling.
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$product_price = self::calculate_product_price( $product );
				$product_row   = array(
					'name'  => $product->get_formatted_name(),
					'qty'   => $product_data['quantity'],
					'price' => $product_price,
				);

				if ( $with_media ) {
					$product_row['product_id'] = $product->get_id();
					$product_row['image']      = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
				}

				$products[] = $product_row;

				$product_total += floatval($product_price);
			}
		}

		$data['billing']  = self::format_billing_address( $billing );
		$data['shipping'] = self::format_shipping_address( $shipping );
		$data['others']   = $others;
		$data['products'] = $products;
		$data['currency'] = html_entity_decode( get_woocommerce_currency_symbol() );
		$data['discount'] = '';
		if ( !empty( $item['meta']['abandoned_cart_meta'] ) ) {
			$get_meta = self::get_abandoned_cart_meta( $item['meta']['abandoned_cart_meta'] );
			$data     = array_merge( $data, $get_meta );
		}
		$data['total'] = !empty( $item['total'] ) ? $item['total'] : 0;
		return $data;
	}

	/**
	 * Formats the recovery emails a cart's automation has queued or sent.
	 *
	 * The automation log records every step a cart passed through — triggers, delays,
	 * tag changes — but the details page only cares about the emails. Each log row is
	 * therefore resolved back to its step so non-email steps can be dropped and the
	 * email's own subject read out of the step settings.
	 *
	 * @param array $log_rows Raw automation log rows for one automation and email.
	 *
	 * @return array The email rows, each with subject, status and timestamp.
	 * @since 1.31.0
	 */
	public static function formatted_recovery_emails( $log_rows ) {
		if ( empty( $log_rows ) || ! is_array( $log_rows ) ) {
			return array();
		}

		$emails = array();
		$steps  = array();
		foreach ( $log_rows as $row ) {
			if ( empty( $row['automation_id'] ) || empty( $row['step_id'] ) ) {
				continue;
			}

			// One step can appear on several log rows, so each is looked up only once.
			$cache_key = $row['automation_id'] . ':' . $row['step_id'];
			if ( ! isset( $steps[ $cache_key ] ) ) {
				$steps[ $cache_key ] = HelperFunctions::get_step_data( $row['automation_id'], $row['step_id'] );
			}

			$step = $steps[ $cache_key ];
			if ( empty( $step['key'] ) || 'sendMail' !== $step['key'] ) {
				continue;
			}

			$settings = ! empty( $step['settings'] ) ? $step['settings'] : array();
			$subject  = ! empty( $settings['message_data']['subject'] ) ? $settings['message_data']['subject'] : '';

			/*
			 * updated_at is when the step last moved — the send time for a completed email.
			 * A row that has not run yet has only its queue time, so fall back to created_at.
			 */
			$moved_at = ! empty( $row['updated_at'] ) ? $row['updated_at'] : $row['created_at'];

			$emails[] = array(
				'id'            => $row['id'],
				'automation_id' => $row['automation_id'],
				'step_id'       => $row['step_id'],
				'subject'       => $subject,
				'status'        => ! empty( $row['status'] ) ? $row['status'] : '',
				'scheduled_at'  => ! empty( $moved_at ) ? gmdate( 'F d, Y h:i a', strtotime( $moved_at ) ) : '',
			);
		}

		// The log is queried newest first; a sequence reads better from its first email down.
		return array_reverse( $emails );
	}

	/**
	 * Reads a checkout field, tolerating both key styles.
	 *
	 * The classic checkout posts `billing_first_name` while the block checkout posts
	 * `billing-first_name`, so every lookup has to try both.
	 *
	 * @param array  $fields The submitted checkout fields.
	 * @param string $key    Field key without its group prefix, e.g. `first_name`.
	 * @param string $group  Field group, `billing` or `shipping`.
	 *
	 * @return string The field value, or an empty string when it was never filled in.
	 * @since 1.31.0
	 */
	private static function get_checkout_field( $fields, $key, $group ) {
		foreach ( array( $group . '_' . $key, $group . '-' . $key ) as $candidate ) {
			if ( ! empty( $fields[ $candidate ] ) && is_scalar( $fields[ $candidate ] ) ) {
				return (string) $fields[ $candidate ];
			}
		}
		return '';
	}

	/**
	 * Builds the billing and shipping addresses as discrete labelled fields.
	 *
	 * `get_preview_data()` returns addresses as one WooCommerce-formatted HTML blob, which
	 * is all a compact preview needs. The cart details page lists each field on its own
	 * row, so it needs the parts back out — and country/state codes resolved to names.
	 *
	 * @param array $checkout_data The unserialized checkout data for the cart.
	 *
	 * @return array {
	 *     @type array $billing  Billing fields keyed by field name.
	 *     @type array $shipping Shipping fields keyed by field name.
	 * }
	 * @since 1.31.0
	 */
	public static function get_structured_address( $checkout_data ) {
		$address = array(
			'billing'  => array(),
			'shipping' => array(),
		);

		$fields = ( is_array( $checkout_data ) && ! empty( $checkout_data['fields'] ) ) ? $checkout_data['fields'] : array();
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $address;
		}

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$first_name = self::get_checkout_field( $fields, 'first_name', $group );
			$last_name  = self::get_checkout_field( $fields, 'last_name', $group );
			$country    = self::get_checkout_field( $fields, 'country', $group );
			$state      = self::get_checkout_field( $fields, 'state', $group );

			$address[ $group ] = array(
				'full_name'  => trim( $first_name . ' ' . $last_name ),
				'email'      => self::get_checkout_field( $fields, 'email', $group ),
				'phone'      => self::get_checkout_field( $fields, 'phone', $group ),
				'company'    => self::get_checkout_field( $fields, 'company', $group ),
				'address_1'  => self::get_checkout_field( $fields, 'address_1', $group ),
				'address_2'  => self::get_checkout_field( $fields, 'address_2', $group ),
				'city'       => self::get_checkout_field( $fields, 'city', $group ),
				'postcode'   => self::get_checkout_field( $fields, 'postcode', $group ),
				'country'    => self::get_country_name( $country ),
				'state'      => self::get_state_name( $country, $state ),
			);
		}

		return $address;
	}

	/**
	 * Resolves a country code to its display name.
	 *
	 * @param string $code The two letter country code.
	 *
	 * @return string The country name, or the code itself when it cannot be resolved.
	 * @since 1.31.0
	 */
	private static function get_country_name( $code ) {
		if ( empty( $code ) || ! class_exists( 'WooCommerce' ) || ! WC()->countries ) {
			return $code;
		}
		$countries = WC()->countries->get_countries();
		return ! empty( $countries[ $code ] ) ? $countries[ $code ] : $code;
	}

	/**
	 * Resolves a state code to its display name within a country.
	 *
	 * @param string $country The two letter country code.
	 * @param string $code    The state code.
	 *
	 * @return string The state name, or the code itself when it cannot be resolved.
	 * @since 1.31.0
	 */
	private static function get_state_name( $country, $code ) {
		if ( empty( $code ) || empty( $country ) || ! class_exists( 'WooCommerce' ) || ! WC()->countries ) {
			return $code;
		}
		$states = WC()->countries->get_states( $country );
		return ( is_array( $states ) && ! empty( $states[ $code ] ) ) ? $states[ $code ] : $code;
	}

	/**
	 * Retrieves the abandoned cart meta data.
	 *
	 * @param mixed $abandoned_cart_meta The abandoned cart meta data.
	 * @return array The formatted abandoned cart meta data.
	 * @since 1.5.0
	 */
	public static function get_abandoned_cart_meta( $abandoned_cart_meta ) {
		$abandoned_cart_meta = maybe_unserialize( $abandoned_cart_meta );
		$shipping_total      = !empty( $abandoned_cart_meta['shipping_total'] ) ? $abandoned_cart_meta['shipping_total'] : 0;
		$shipping_tax_total  = !empty( $abandoned_cart_meta['shipping_tax_total'] ) ? $abandoned_cart_meta['shipping_tax_total'] : 0;
		$total_tax           = !empty( $abandoned_cart_meta['total_tax'] ) ? $abandoned_cart_meta['total_tax'] : 0;
		$fee_total           = !empty( $abandoned_cart_meta['fee_total'] ) ? $abandoned_cart_meta['fee_total'] : 0;

		$data['shipping_total']     = $shipping_total;
		$data['shipping_tax_total'] = $shipping_tax_total;
		$data['total_tax']          = $total_tax;
		$data['coupon']             = $abandoned_cart_meta['coupons'];
		$data['discount']           = self::calculate_discount( $abandoned_cart_meta );
		$data['fee_total']          = $fee_total;

		return $data;
	}


	/**
	 * Processes the checkout fields and returns the updated "others" array.
	 *
	 * @since 1.5.0
	 *
	 * @param array $fields             The checkout fields.
	 * @param array $nice_names         The nice names array.
	 * @param array $available_gateways The available gateways.
	 * @param array $others             The initial "others" array.
	 *
	 * @return array The updated "others" array.
	 */
	public static function process_checkout_fields( $fields, $nice_names, $available_gateways, $others ) {
		$shipping = array();
		$billing  = array();
		foreach ( $fields as $key => $value ) {
			if ( 'billing_phone' === $key || 'billing-phone' === $key ) {
				if (isset($nice_names[$key])) {
					$others[$nice_names[$key]] = $value;
				}
				continue;
			}
			if ( false !== strpos( $key, 'billing' ) && isset( $nice_names[ $key ] ) ) {
				$key             = preg_replace('/^billing[_-]/', '', $key);
				$billing[ $key ] = $value;
				continue;
			}
			if ( false !== strpos( $key, 'shipping' ) && isset( $nice_names[ $key ] ) ) {
				$key              = preg_replace('/^shipping[_-]/', '', $key);
				$shipping[ $key ] = $value;
				continue;
			}

			if ( 'payment_method' === $key ) {
				if ( isset( $available_gateways[ $value ] ) && 'yes' === $available_gateways[ $value ]->enabled ) {
					$value = $available_gateways[ $value ]->method_title;
				}
				if ( isset( $nice_names[ $key ] ) ) {
					$others[ $nice_names[ $key ] ] = $value;
				}
				continue;
			}

			if (preg_match('/^radio-control-wc-payment-method-options-(.+)$/', $key, $matches)) {
				$last_word = $matches[1];
				if (isset($available_gateways[$last_word]) && 'yes' === $available_gateways[$last_word]->enabled) {
					$value = $available_gateways[$last_word]->method_title;
				}

				if (isset($nice_names['payment_method'])) {
					$others[$nice_names['payment_method']] = $value;
				}
				continue;
			}

			if ( isset( $nice_names[ $key ] ) ) {
				$others[ $nice_names[ $key ] ] = $value;
			}
		}
		$data = array(
			'other'    => $others,
			'billing'  => $billing,
			'shipping' => $shipping,
		);
		return $data;
	}

	/**
	 * Processes the checkout data and returns the updated "others" array.
	 *
	 * @since 1.5.0
	 *
	 * @param array $checkout_data The checkout data.
	 * @param array $nice_names     The nice names array.
	 * @param array $others         The initial "others" array.
	 *
	 * @return array The updated "others" array.
	 */
	public static function process_checkout_data( $checkout_data, $nice_names, $others ) {
		if ( empty( $checkout_data ) ) {
			return $others;
		}
		foreach ( $checkout_data as $key => $value ) {
			isset( $nice_names[ $key ] ) ? $others[ $nice_names[ $key ] ] = $value : '';
		}
		return $others;
	}

	/**
	 * Calculates the product price based on the provided product data.
	 *
	 * @since 1.5.0
	 *
	 * @param object $product The product data.
	 *
	 * @return float The calculated product price.
	 */
	public static function calculate_product_price( $product ) {
		return $product->get_price();
	}

	/**
	 * Formats the billing address and returns the formatted address.
	 *
	 * @since 1.5.0
	 *
	 * @param array $billing The billing address data.
	 *
	 * @return string The formatted billing address.
	 */
	public static function format_billing_address( $billing ) {
		if ( isset( $billing['country'] ) && isset( $billing['state'] ) ) {
			$country_states = WC()->countries->get_states( $billing['country'] );
			if ( ( is_array( $country_states ) && isset( $country_states[ $billing['state'] ] ) ) ) {
				$billing['state'] = $country_states[ $billing['state'] ];
			}
		}
		/**
		 * Forces the display of the country in a formatted address.
		 *
		 * By default, WooCommerce may omit the country from the formatted address if it matches the shop's base country.
		 * This filter allows you to override that behavior and always display the country in the formatted address.
		 *
		 * @param bool $force_display_country Whether to force the display of the country. Default is true.
		 */

		add_filter( 'woocommerce_formatted_address_force_country_display', '__return_true' );

		return WC()->countries->get_formatted_address( $billing );
	}

	/**
	 * Formats the shipping address and returns the formatted address.
	 *
	 * @since 1.5.0
	 *
	 * @param array $shipping The shipping address data.
	 *
	 * @return string The formatted shipping address.
	 */
	public static function format_shipping_address( $shipping ) {
		if ( isset( $shipping['country'] ) && isset( $shipping['state'] ) ) {
			$country_states = WC()->countries->get_states( $shipping['country'] );
			if ( is_array( $country_states ) && isset( $country_states[ $shipping['state'] ] ) ) {
				$shipping['state'] = $country_states[ $shipping['state'] ];
			}
		}
		/**
		 * Forces the display of the country in a formatted address.
		 *
		 * By default, WooCommerce may omit the country from the formatted address if it matches the shop's base country.
		 * This filter allows you to override that behavior and always display the country in the formatted address.
		 *
		 * @param bool $force_display_country Whether to force the display of the country. Default is true.
		 */

		add_filter( 'woocommerce_formatted_address_force_country_display', '__return_false' );

		return WC()->countries->get_formatted_address( $shipping );
	}

	/**
	 * Calculates the discount amount based on the provided item.
	 *
	 * @since 1.5.0
	 *
	 * @param mixed $item The item data.
	 *
	 * @return float The calculated discount amount.
	 */
	private static function calculate_discount( $item ) {
		$coupon_data = maybe_unserialize( $item['coupons'] );
		$discount    = 0;

		if ( is_array( $coupon_data ) && count( $coupon_data ) !== 0 ) {
			foreach ( $coupon_data as $key => $coupon ) {
				$discount += isset( $coupon['coupon_discount_totals'] ) && is_numeric( $coupon['coupon_discount_totals'] ) ? number_format( $coupon['coupon_discount_totals'], 2, '.', '' ) : 0;
			}
		}

		return $discount;
	}

	/**
	 * Calculates the total amount based on the product total and item data.
	 *
	 * @since 1.5.0
	 *
	 * @param float $product_total The product total amount.
	 * @param mixed $item          The item data.
	 *
	 * @return string The calculated total amount.
	 */
	private static function calculate_total( $product_total, $item ) {
		$shipping_total = $item->shipping_total + $item->shipping_tax_total;
		$total          = round( $product_total, 2 );
		$total         += $shipping_total;
		return number_format( $total, 2, '.', '' );
	}

	/**
	 * Retrieves the default nice names for WooCommerce checkout fields.
	 *
	 * @return array An array containing the default nice names for checkout fields.
	 * @since 1.5.0
	 */
	public static function get_woocommerce_default_checkout_nice_names() {
		/**
		 * Filter the default checkout field names and labels.
		 *
		 * The filtered array contains key-value pairs where the key represents the field name
		 * and the value represents the field label.
		 *
		 * @param array $default_fields An array of default field names and labels.
		 * @return array The filtered array of field names and labels.
		 */
		return apply_filters(
			'mailmint_default_checkout_nice_names',
			array(
				'billing_first_name'  => __( 'First Name', 'mrm' ),
				'billing_last_name'   => __( 'Last Name', 'mrm' ),
				'billing_company'     => __( 'Company', 'mrm' ),
				'billing_address_1'   => __( 'Address 1', 'mrm' ),
				'billing_address_2'   => __( 'Address 2', 'mrm' ),
				'billing_city'        => __( 'City', 'mrm' ),
				'billing_postcode'    => __( 'Postal/Zip Code', 'mrm' ),
				'billing_state'       => __( 'State', 'mrm' ),
				'billing_country'     => __( 'Country', 'mrm' ),
				'billing_phone'       => __( 'Phone Number', 'mrm' ),
				'billing_email'       => __( 'Email Address', 'mrm' ),

				'shipping_first_name' => __( 'First Name', 'mrm' ),
				'shipping_last_name'  => __( 'Last Name', 'mrm' ),
				'shipping_company'    => __( 'Company', 'mrm' ),
				'shipping_address_1'  => __( 'Address 1', 'mrm' ),
				'shipping_address_2'  => __( 'Address 2', 'mrm' ),
				'shipping_city'       => __( 'City', 'mrm' ),
				'shipping_postcode'   => __( 'Postal/Zip Code', 'mrm' ),
				'shipping_state'      => __( 'State', 'mrm' ),
				'shipping_country'    => __( 'Country', 'mrm' ),
				'shipping_phone'      => __( 'Phone', 'mrm' ),

				'current_page_id'     => __( 'WordPress Page ID', 'mrm' ),
				'payment_method'      => __( 'Payment Method', 'mrm' ),
			)
		);
	}


	/**
	 * Retrieves the count of abandoned cart data.
	 *
	 * @return array An array containing the counts for different abandoned cart statuses.
	 * @since 1.5.0
	 */
	public static function get_count_abandoned_cart_data() {
		$count_status = CartModel::get_abandoned_cart_status_count();
		$abandoned    = !empty( $count_status['abandoned'] ) ? $count_status['abandoned'] : 0;
		$pending      = !empty( $count_status['pending'] ) ? $count_status['pending'] : 0;
		$lost         = !empty( $count_status['lost'] ) ? $count_status['lost'] : 0;
		$recovered    = !empty( $count_status['recovered'] ) ? $count_status['recovered'] : 0;

		$total = array(
			'abandoned' => intval( $abandoned ) + intval( $pending ),
			'recovered' => intval( $recovered ),
			'lost'      => intval( $lost ),
		);

		return $total;
	}
	/**
	 * Format a comma-separated string into a formatted string with trimmed elements enclosed in single quotes.
	 *
	 * @param string $status The comma-separated string to be formatted.
	 * @return string The formatted string.
	 * @since 1.5.0
	 */
	public static function format_status( $status ) {
		if ( '' === $status ) {
			return '';
		}
		$status_array      = explode( ',', $status ); // Convert status string to an array.
		$status_trim_array = array_map(
			function( $status ) {
				return "'" . trim( $status ) . "'";
			},
			$status_array
		);
		$status_formatted  = implode( ',', $status_trim_array );
		return $status_formatted;
	}

	/**
	 * Hide_free_products.
	 *
	 * @return bool
	 * @since 1.5.0
	 */
	public static function hide_free_products() {
		/**
		 * Filters whether to hide free products in item display.
		 *
		 * Use this filter to modify the behavior of displaying free products in an item display.
		 * By default, free products are not hidden.
		 *
		 * @param bool $hide_free_products Whether to hide free products. Default is false.
		 * @return bool Modified value indicating whether to hide free products.
		 * @since 1.5.0
		 */
		return apply_filters( 'mailmint_items_display_hide_free_products', false );
	}

	/**
	 * Summary: Generates a formatted table block containing the cart items.
	 *
	 * Description: This function generates an HTML table block that displays the cart items with product details, quantities, and prices.
	 * The table is styled with CSS and uses localization for text labels. It supports WooCommerce integration and handles different providers.
	 *
	 * @access public
	 *
	 * @param array $cart_details The cart details including the total, provider, and items.
	 *
	 * @return string The formatted HTML table block.
	 * @since 1.5.0
	 */
	public static function generate_cart_items_table_block_from_placeholder( $cart_details ) {
		$text_align = is_rtl() ? 'right' : 'left';
		$cart_total = isset( $cart_details['total'] ) ? $cart_details['total'] : 0;
		$provider   = isset( $cart_details['provider'] ) ? $cart_details['provider'] : 0;
		$cart_items = isset( $cart_details['items'] ) ? maybe_unserialize( $cart_details['items'] ) : array();

		ob_start(); ?>

		<div>
			<table class="td" cellspacing="0" cellpadding="6"
				style="color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;width: 100%;font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif" border="1">
				<thead>
					<tr>
						<th class="td" scope="col"
							style="text-align:
							<?php
							echo esc_attr( $text_align );
							?>
							; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 5px; min-width: 55px">
							<?php
							esc_html_e( 'Product', 'mrm' );
							?>
							</th>
						<th class="td" scope="col"
							style="text-align:
							<?php
							echo esc_attr( $text_align );
							?>
							; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 5px; min-width: 85px">
							<?php
							esc_html_e( 'Quantity', 'mrm' );
							?>
							</th>
						<th class="td" scope="col"
							style="text-align:
							<?php
							echo esc_attr( $text_align );
							?>
							; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 5px; min-width: 85px">
							<?php
							esc_html_e( 'Price', 'mrm' );
							?>
							</th>
					</tr>
				</thead>
				<tbody>
				<?php

				if ( !is_array( $cart_items ) || empty( $cart_items ) ) {
					ob_end_clean();
					return;
				}

				if ( 'WC' === $provider ) {
					foreach ( $cart_items as $cart_item ) {
						$product_id = !empty( $cart_item[ 'variation_id' ] ) ? (int) $cart_item[ 'variation_id' ] : $cart_item[ 'product_id' ];
						$product    = wc_get_product( $product_id );
						if ( $product && $product->is_in_stock() ) {
							$image_url = empty( wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' )[0] ) ? MRM_DIR_URL . 'admin/assets/images/mint-placeholder.png' : wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' )[0]; // phpcs:ignore
							?>
							<tr class='cart_item'>
								<td class="td"
									style="text-align:
									<?php
									echo esc_attr( $text_align );
									?>
									; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 5px; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
									<img alt="<?php echo $product->get_name(); // phpcs:ignore ?>" style="width: 60px;height: 60px;border: none;font-size: 14px;font-weight: bold;text-decoration: none;text-transform: capitalize;vertical-align: middle;margin-right: 10px;max-width: 100%;"
										src="<?php echo $image_url; // phpcs:ignore ?>">
										<span style="display: inline-block; padding-top: 5px; width: 70%">
											<?php
												echo esc_html( $product->get_name() );
											?>
										</span>
								</td>
								<td class="td"
									style="text-align:
									<?php
									echo esc_attr( $text_align );
									?>
									; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 5px; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
									<?php
									echo esc_html( $cart_item[ 'quantity' ] );
									?>
									</td>
								<td class="td"
									style="text-align:
									<?php
									echo esc_attr( $text_align );
									?>
									; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 5px; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
									<?php
									echo wc_price( $cart_item[ 'quantity' ] * $product->get_price() ); // phpcs:ignore
									?>
									</td>
							</tr>
							<?php
						}
					}
				}
				?>
				</tbody>
				<tfoot>
				<tr>
					<th class="td" scope="row" colspan="2"
						style="text-align:
						<?php
						echo esc_attr( $text_align );
						?>
						; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 12px;">
						<?php esc_html_e( 'Total:', 'mrm' ); ?>
					</th>
					<td class="td"
						style="text-align:
						<?php
						echo esc_attr( $text_align );
						?>
						; color: #636363;border: 1px solid #e5e5e5;vertical-align: middle;padding: 12px;">
						<?php
						echo wc_price( $cart_total ); // phpcs:ignore
						?>
						</td>
				</tr>
				</tfoot>
			</table>
		</div>

		<?php
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}

	/**
	 * Summary: Retrieves the cart recovery URL.
	 *
	 * Description: Retrieves the cart recovery URL based on the cart details and automation ID.
	 *
	 * @access public
	 *
	 * @param array $cart_details  The cart details.
	 * @param int   $automation_id The automation ID.
	 * @param int   $checkout_page_id   WooCommerce checkout page ID.
	 *
	 * @return string The cart recovery URL.
	 * @since 1.5.0
	 */
	public static function get_cart_recovery_url( $cart_details, $automation_id, $checkout_page_id ) {
		$token    = isset( $cart_details['token'] ) ? $cart_details['token'] : '';
		$cart_url = !empty( $checkout_page_id ) ? get_permalink( $checkout_page_id ) : home_url();

		if ( empty( $token ) ) {
			return $cart_url;
		}

		$query_args = array(
			'mint-ac-token' => $token,
		);

		if ( absint( $automation_id ) ) {
			$query_args['automation-id'] = $automation_id;
		}

		return add_query_arg( $query_args, $cart_url );
	}

	/**
	 * Summary: Retrieves the details of the current cart.
	 *
	 * Description: Retrieves the details of the current cart from the WooCommerce session.
	 *
	 * @access public
	 *
	 * @param WC_Session $wc_session The WooCommerce session object.
	 *
	 * @return array An array containing the details of the current cart.
	 * @since 1.5.0
	 */
	public static function get_current_cart_details( $wc_session ) {
		$data = array();

		$data['coupons']        = self::get_current_cart_coupon_details( $wc_session );
		$data['fee_total']      = isset( $wc_session->cart_totals['fee_total'] ) ? $wc_session->cart_totals['fee_total'] : 0;
		$data['fee_tax']        = isset( $wc_session->cart_totals['fee_tax'] ) ? $wc_session->cart_totals['fee_tax'] : 0;
		$data['shipping_total'] = isset( $wc_session->cart_totals['shipping_total'] ) ? $wc_session->cart_totals['shipping_total'] : 0;
		$data['shipping_tax']   = isset( $wc_session->cart_totals['shipping_tax'] ) ? $wc_session->cart_totals['shipping_tax'] : 0;
		$data['total_tax']      = isset( $wc_session->cart_totals['total_tax'] ) ? $wc_session->cart_totals['total_tax'] : 0;

		return $data;
	}

	/**
	 * Summary: Retrieves the details of the currently applied coupons in the cart.
	 *
	 * Description: Retrieves the details of the currently applied coupons in the cart from the WooCommerce session.
	 *
	 * @access public
	 *
	 * @param WC_Session $wc_session The WooCommerce session object.
	 *
	 * @return array An array containing the details of the currently applied coupons in the cart.
	 * @since 1.5.0
	 */
	public static function get_current_cart_coupon_details( $wc_session ) {
		// Check if the $wc_session object is valid.
		if ( ! isset( $wc_session ) || ! is_object( $wc_session ) ) {
			return array();
		}
		// Retrieve the applied coupons and discounts from the WooCommerce session.
		$coupons                    = $wc_session->applied_coupons;
		$coupon_discount_totals     = $wc_session->coupon_discount_totals;
		$coupon_discount_tax_totals = $wc_session->coupon_discount_tax_totals;

		$merged_data = array();

		// Iterate through each coupon.
		foreach ( (array) $coupons as $coupon ) {
			$merged_data[ $coupon ] = array(
				'coupon_discount_totals'     => $coupon_discount_totals[ $coupon ],
				'coupon_discount_tax_totals' => $coupon_discount_tax_totals[ $coupon ],
			);
		}
		// Return the merged coupon data.
		return $merged_data;
	}

	/**
	 * Summary: Formats the cart details.
	 *
	 * Description: Formats the cart details by merging the meta values based on the meta key.
	 *
	 * @access public
	 *
	 * @param array $cart_detail The cart detail array.
	 *
	 * @return array The formatted cart detail array.
	 * @since 1.5.0
	 */
	public static function get_formatted_cart_detail( $cart_detail ) {
		$output_array = array();

		if ( is_array( $cart_detail ) ) {
			foreach ( $cart_detail as $item ) {
				$meta_key   = isset( $item['meta_key'] ) ? $item['meta_key'] : '';
				$meta_value = isset( $item['meta_value'] ) ? maybe_unserialize( $item['meta_value'] ) : array();

				if ( ! empty( $meta_key ) ) {
					$output_array[ $meta_key ] = isset( $output_array[ $meta_key ] ) ? array_merge( $output_array[ $meta_key ], $meta_value ) : $meta_value;
				}

				$output_array = array_merge( $output_array, $item );
			}
			unset( $output_array[ 'meta_key' ], $output_array[ 'meta_value' ] );
		}

		return $output_array;
	}

	/**
	 * Summary: Retrieves the checkout data from the provided array.
	 *
	 * Description: Retrieves and unserialize the checkout data from the provided array, if it is not empty.
	 *
	 * @access public
	 *
	 * @param array $data The array containing the checkout data.
	 *
	 * @return mixed The unserialize checkout data, or an empty value if it is empty.
	 * @since 1.5.0
	 */
	public static function get_checkout_data( $data ) {
		$checkout_data = isset( $data['checkout_data'] ) ? $data['checkout_data'] : array();

		if ( ! empty( $checkout_data ) ) {
			$checkout_data = maybe_unserialize( $checkout_data );
		}

		return $checkout_data;
	}

	/**
	 * Summary: Retrieves the WooCommerce checkout page ID.
	 *
	 * Description: Retrieves the WooCommerce checkout page ID using the 'wc_get_page_id' function if it exists, otherwise returns 0.
	 *
	 * @access public
	 *
	 * @return int|false The WooCommerce checkout page ID if found, false otherwise.
	 * @since 1.5.0
	 */
	public static function get_woocommerce_checkout_page_id() {
		return function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
	}

	/**
	 * Summary: Prefills address fields with restored data.
	 *
	 * Description: This function pre-fills the address fields with the restored data from the cart, if available.
	 *
	 * @access public
	 *
	 * @param array      $address_fields An array containing the address fields.
	 * @param string     $field_prefix   The field prefix to identify the fields to pre-fill.
	 * @param WC_Session $session        The WooCommerce session object.
	 *
	 * @return array The modified array of address fields.
	 * @since 1.5.0
	 */
	public static function prefill_fields_with_restored_data( $address_fields, $field_prefix, $session ) {
		if ( is_null( $session ) ) {
			return $address_fields;
		}

		$data = $session->get( 'mint_restored_cart_detail' );

		if ( empty( $data ) ) {
			return $address_fields;
		}

		if ( 'billing' !== $field_prefix ) {
			$address_fields['billing_email']['default'] = $data['email'];
		}

		$checkout_data = self::get_checkout_data( $data );
		if ( is_array( $checkout_data ) && isset( $checkout_data['fields'] ) && is_array( $checkout_data['fields'] ) ) {
			unset( $checkout_data['current_page_id'] );
			foreach ( $checkout_data['fields'] as $field_name => $field_value ) {
				if ( false !== strpos( $field_name, $field_prefix ) ) {
					continue;
				}

				if ( ! isset( $address_fields[ $field_name ] ) ) {
					continue;
				}
				$address_fields[ $field_name ]['default'] = $field_value;
			}
		}

		return $address_fields;
	}

	/**
	 * Summary: Retrieves a dummy recovery URL for testing purposes.
	 *
	 * Description: The recovery URL is generated with a unique token and includes a query parameter to indicate it is a test.
	 *
	 * @access public
	 *
	 * @return string The dummy recovery URL.
	 * @since 1.5.0
	 */
	public static function get_dummy_recovery_url() {
		$cart_url = add_query_arg(
			array(
				'mint-ac-token'          => uniqid(),
				'mint-cart-restore-test' => 'yes',
			),
			wc_get_page_permalink( 'checkout' )
		);

		return esc_url_raw( $cart_url );
	}

	/**
	 * Summary: Retrieves dummy cart details for testing purposes.
	 *
	 * Description: This static method retrieves dummy cart details by fetching a specified number of
	 * published products from WooCommerce and calculates the total price. It returns an array
	 * containing the calculated total, provider information, and an array of cart item details.
	 *
	 * @access public
	 *
	 * @return array An array containing the calculated total, provider information, and cart item details.
	 * @since 1.5.0
	 */
	public static function get_dummy_cart_detail() {
		$args             = array(
			'status'  => 'publish',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'limit'   => 3,
		);
		$items            = array();
		$calculated_total = 0;

		$products = wc_get_products( $args );
		if ( count( $products ) > 0 ) {
			foreach ( $products as $product ) {
				if ( is_a( $product, 'WC_Product' ) ) {
					$items[]           = array(
						'product_id' => $product->get_id(),
						'quantity'   => 1,
					);
					$calculated_total += $product->get_price();
				}
			}
		}

		$calculated_total = wc_format_decimal( $calculated_total, wc_get_price_decimals() );

		return array(
			'total'    => $calculated_total,
			'provider' => 'WC',
			'items'    => $items,
		);
	}

	/**
	 * Formats the automation log data for display.
	 *
	 * @param array $automation_data The automation log data to be formatted.
	 * @return array The formatted automation log data.
	 * @since 1.5.0
	 */
	public static function formatted_automation_log( $automation_data ) {
		if ( empty( $automation_data ) || !is_array( $automation_data ) ) {
			return array();
		}
		$action         = AutomationAction::get_instance()->supported_actions();
		$formatted_data = array();
		foreach ( $automation_data as $data ) {
			$step_data = HelperFunctions::get_step_data( $data['automation_id'], $data['step_id'] );
			$key       = !empty( $step_data['key'] ) ? $step_data['key'] : '';
			$type      = !empty( $step_data['step_type'] ) ? $step_data['step_type'] : '';
			$step_name = isset( $action[ $key ] ) ? $action[ $key ] : '';
			if ( 'wc_abandoned_cart' === $key && 'trigger' === $type ) {
				$step_name = __( 'Abandoned Cart', 'mrm' );
			}
			$formatted_data[] = array(
				'id'            => $data['id'],
				'automation_id' => $data['automation_id'],
				'step_id'       => $data['step_id'],
				'step_key'      => $key,
				'step_name'     => $step_name,
				'step_type'     => $type,
				'email'         => $data['email'],
				'count'         => $data['count'],
				'status'        => $data['status'],
				'started_on'    => gmdate( 'F d, Y h:i:s a', strtotime( $data['created_at'] ) ),
				'last_run'      => gmdate( 'F d, Y h:i:s a', strtotime( $data['updated_at'] ) ),
			);
		}
		return $formatted_data;
	}

	/**
	 * Formats an array of data in snake_case and re-keys it with the 'label' column as keys.
	 *
	 * @param array $data An array of data to be formatted in snake_case.
	 *                    Each element of the array should have a 'label' key, which will be used as the new keys.
	 *
	 * @return array The formatted data with snake_case keys.
	 * @since 1.5.0
	 */
	public static function format_data_with_label( $data ) {
		$formatted_data = array_map(
			function ( $item ) {
				unset( $item['label'] );
				return $item;
			},
			$data
		);

		return array_combine( array_column( $data, 'label' ), $formatted_data );
	}

	/**
	 * Get an array containing months as keys with empty arrays as values.
	 *
	 * @return array An array containing months as keys and empty arrays as values.
	 * @since 1.5.0
	 */
	public static function get_months_multi_dimensional_array() {
		return array(
			'Jan' => array(),
			'Feb' => array(),
			'Mar' => array(),
			'Apr' => array(),
			'May' => array(),
			'Jun' => array(),
			'Jul' => array(),
			'Aug' => array(),
			'Sep' => array(),
			'Oct' => array(),
			'Nov' => array(),
			'Dec' => array(),
		);
	}

	/**
	 * Get an array containing months as keys with empty arrays as values.
	 *
	 * @return array An array containing months as keys and empty arrays as values.
	 * @since 1.5.0
	 */
	public static function get_months_array() {
		return array(
			'Jan' => 0,
			'Feb' => 0,
			'Mar' => 0,
			'Apr' => 0,
			'May' => 0,
			'Jun' => 0,
			'Jul' => 0,
			'Aug' => 0,
			'Sep' => 0,
			'Oct' => 0,
			'Nov' => 0,
			'Dec' => 0,
		);
	}


	/**
	 * Get the WHERE query condition based on the provided filter.
	 *
	 * @param string $filter The filter key to determine the condition.
	 * @return array The database WHERE condition.
	 * @since 1.5.0
	 */
	public static function get_where_query( $filter ) {
		$condition = self::get_where_condition_array();
		return !empty( $condition[ $filter ] ) ? $condition[ $filter ] : array();
	}

	/**
	 * Get an array of WHERE conditions based on filters.
	 *
	 * @return array An array of database WHERE conditions for different filters.
	 * @since
	 */
	public static function get_where_condition_array() {
		return array(
			'all'     => '1=1',
			'monthly' => '(EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now()))',
			'weekly'  => '(YEARWEEK(created_at)=YEARWEEK(NOW()) OR YEARWEEK(updated_at)=YEARWEEK(NOW()))',
			'yearly'  => '(YEAR(created_at)=YEAR(NOW()) OR YEAR(updated_at)=YEAR(NOW()))',
		);
	}
	/**
	 * Retrieve an array containing 'abandoned', 'recovered', and 'lost' data merged with the given months.
	 *
	 * @param array $result An array containing data with 'label', 'abandoned', 'recovered', and 'lost' keys.
	 * @param array $dayes An array containing months data to be merged.
	 *
	 * @return array An associative array containing 'abandoned', 'recovered', and 'lost' data merged with months.
	 * @since 1.5.0
	 */
	public static function get_formated_line_chart( $result, $dayes ) {
		$abandoned = array_column( $result, 'abandoned', 'label' );
		$recovered = array_column( $result, 'recovered', 'label' );
		$lost      = array_column( $result, 'lost', 'label' );
		return array(
			'abandoned' => array_merge( $dayes, $abandoned ),
			'recovered' => array_merge( $dayes, $recovered ),
			'lost'      => array_merge( $dayes, $lost ),
		);
	}

	/**
	 * Get an array of monthly days initialized to 0 based on the current month.
	 *
	 * @return array An associative array containing monthly days initialized to 0.
	 * @since 1.5.0
	 */
	public static function get_monthly_days() {
		$current_datetime    = current_datetime();
		$current_month       = date_format( $current_datetime, 'n' );
		$current_month_label = date_format( $current_datetime, 'M' );

		if ( 2 === (int) $current_month ) {
			$days = 28;
		} elseif ( 8 === (int) $current_month || ( 0 !== (int) $current_month % 2 && 9 > (int) $current_month ) || ( 0 === (int) $current_month % 2 && 9 < (int) $current_month ) ) {
			$days = 31;
		} else {
			$days = 30;
		}

		$monthly_days = array();

		for ( $day = 1; $day <= $days; $day++ ) {
			$monthly_days[ $current_month_label . ' ' . $day ] = 0;
		}

		return $monthly_days;
	}

	/**
	 * Get an array of weekdays initialized to 0 based on the given start of the week.
	 *
	 * @param string $start_of_week A string representing the starting date of the week in 'Y-m-d' format.
	 *
	 * @return array An associative array containing weekdays initialized to 0.
	 * @since 1.5.0
	 */
	public static function get_week_days( $start_of_week ) {
		$week_days = array();
		$interval  = 0;

		while ( $interval < 7 ) {
			$label               = gmdate( 'M j', strtotime( $start_of_week . '+' . $interval . ' day' ) );
			$week_days[ $label ] = 0;
			$interval++;
		}

		return $week_days;
	}

	/**
	 * Generate cart items from a cart block in an email body.
	 *
	 * This function processes an email body containing a cart block, adjusts the layout for cart items, and replaces the
	 * content with cart item details. It retrieves cart items from the provided cart details and injects them into the email body.
	 *
	 * @access public
	 *
	 * @param array  $cart_details An array of cart details.
	 * @param string $email_body The email body containing the cart block.
	 * @return string The updated email body with cart item details.
	 * @since 1.5.11
	 * @since 1.9.2 Added support for multiple cart blocks in the email body.
	 */
	public static function generate_cart_items_from_cart_block( $cart_details, $email_body ) {
		if ( !class_exists( 'DOMDocument' ) ) {
			return $email_body;
		}

		if ( empty( $email_body ) ) {
			return $email_body;
		}
		// Initialize variables.
		$output     = '';
		$cart_items = isset( $cart_details['items'] ) ? maybe_unserialize( $cart_details['items'] ) : array();

		// Calculate common style values outside the loop.
		$total_cart_items = count( $cart_items );

		if ( false !== strpos( $email_body, '33.333333333333336' ) ) {
			switch ( $total_cart_items ) {
				case 1:
					$email_body = str_replace( '33.333333333333336', '100', $email_body );
					break;
				case 2:
					$email_body = str_replace( '33.333333333333336', '50', $email_body );
					break;
			}
		}

		// Load the email body into a DOMDocument for manipulation.
		$dom = new DOMDocument();
		@$dom->loadHTML( $email_body );

		$xpath = new DOMXPath( $dom );

		$cart_blocks = $xpath->query( '//div[contains(@class, "mint-cart-block")]' );

		if ( 0 === $cart_blocks->length ) {
			return $email_body;
		}

		$first_child_content = '';

		if ( $cart_blocks->length > 0 ) {
			$block = $cart_blocks->item( 0 ); // Get the first node.

			// Query for the direct children of the block.
			$children = $xpath->query( 'div', $block );

			$total_children = $children->length; // This is the total number of children.

			if ( $total_children > 0 ) {
				$first_child         = $children->item( 0 ); // This is the first child.
				$first_child_content = $dom->saveHTML( $first_child ); // This is the HTML content of the first child.
			}
		}

		// Process each cart item and replace placeholders in the email body.
		foreach ( $cart_items as $cart_item ) {
			$product_id = !empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : $cart_item['product_id'];
			$product    = wc_get_product( $product_id );

			$doc = new DOMDocument();
			$doc->loadHTML( $first_child_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
			$doc->substituteEntities = false; // phpcs:ignore
			$elements                = $doc->getElementsByTagName( 'td' );
			// Update the content for product title, price, and image.
			foreach ( $elements as $element ) {
				if ( $element->hasAttribute( 'class' ) && strpos( $element->getAttribute( 'class' ), 'mint-cart-item-title' ) !== false ) {
					$inner_div = $element->getElementsByTagName( 'div' )->item( 0 );
					if ( $inner_div ) {
						$inner_div->nodeValue = $product->get_title(); // phpcs:ignore
					}
				}

				if ( $element->hasAttribute( 'class' ) && strpos( $element->getAttribute( 'class' ), 'mint-cart-item-price' ) !== false ) {
					$inner_div = $element->getElementsByTagName( 'div' )->item( 0 );
					if ( $inner_div ) {
						$inner_div->nodeValue = wc_price( $product->get_price() ); // phpcs:ignore
					}
				}

				if ( $element->hasAttribute( 'class' ) && strpos( $element->getAttribute( 'class' ), 'mint-cart-item-image' ) !== false ) {
					$imgs = $element->getElementsByTagName( 'img' );
					foreach ( $imgs as $img ) {
						$img->setAttribute( 'src', empty( wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' )[0] ) ? MRM_DIR_URL . 'admin/assets/images/mint-placeholder.png' : wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' )[0] );
					}
				}
			}

			$current_content = $doc->saveHTML();
			$output         .= $current_content;
		}

		foreach ( $cart_blocks as $index => $order_block ) {
			// Remove the original content.
			while ( $order_block->firstChild ) { // phpcs:ignore
				$order_block->removeChild( $order_block->firstChild ); // phpcs:ignore
			}

			// Create a new DOMDocument for the updated content.
			$new_doc = new DOMDocument();
			@$new_doc->loadHTML( $output );

			// Import the nodes of the updated content to the original DOMDocument.
			foreach ( $new_doc->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) {
				$order_block->appendChild( $dom->importNode( $node, true ) );
			}
		}

		// Clean up and return the updated email body.
		$email_body = $dom->saveHTML();
		$email_body = str_replace( '%7B%7B', '{{', $email_body );
		$email_body = str_replace( '%7D%7D', '}}', $email_body );
		return $email_body;
	}

	/**
	 * Generate cart items from a cart block in an email body.
	 *
	 * This function processes an email body containing a cart block, adjusts the layout for cart items, and replaces the
	 * content with cart item details. It retrieves cart items from the provided cart details and injects them into the email body.
	 *
	 * @access public
	 *
	 * @param array  $cart_details An array of cart details.
	 * @param string $email_body The email body containing the cart block.
	 * @return string The updated email body with cart item details.
	 * @since 1.5.11
	 */
	public static function generate_cart_items_from_cart_table_block( $cart_details, $email_body ) {
		if ( !class_exists( 'DOMDocument' ) ) {
			return $email_body;
		}

		if ( empty( $email_body ) ) {
			return $email_body;
		}

		// Initialize variables.
		$output = '';
		// Clean up the email body and retrieve cart items.
		$cart_items = isset( $cart_details['items'] ) ? maybe_unserialize( $cart_details['items'] ) : array();
		$cart_total = isset( $cart_details['total'] ) ? $cart_details['total'] : 0;

		// Load the email body into a DOMDocument for manipulation.
		$dom = new DOMDocument();

		@$dom->loadHTML( $email_body );

		$xpath = new DOMXPath( $dom );

		$cart_blocks = $xpath->query( '//tbody[contains(@class, "mint-cart-grid-block")]' );

		if ( 0 === $cart_blocks->length ) {
			return $email_body;
		}

		$first_child_content = '';
		// Iterate through cart blocks to adjust the layout.
		if ( $cart_blocks->length > 0 ) {
			$block = $cart_blocks->item( 0 ); // Get the first node.

			// Query for the direct children of the block.
			$children = $xpath->query( 'tr', $block );

			$total_children = $children->length; // This is the total number of children.

			if ( $total_children > 0 ) {
				$first_child         = $children->item( 0 ); // This is the first child.
				$first_child_content = $dom->saveHTML( $first_child ); // This is the HTML content of the first child.
			}
		}

		// Process each cart item and replace placeholders in the email body.
		foreach ( $cart_items as $cart_item ) {
			$product_id = !empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : $cart_item['product_id'];
			$product    = wc_get_product( $product_id );

			$doc = new DOMDocument();
			$doc->loadHTML( $first_child_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
			$doc->substituteEntities = false; // phpcs:ignore
			$elements                = $doc->getElementsByTagName( 'td' );
			// Update the content for product title, price, and image.
			foreach ( $elements as $element ) {
				if ( $element->hasAttribute( 'class' ) && strpos( $element->getAttribute( 'class' ), 'mint-cart-grid-title' ) !== false ) {
					// Find the text node within the td element and replace its value.
					foreach ( $element->childNodes as $childNode ) { // phpcs:ignore
						$childNode->nodeValue = $product->get_title(); // phpcs:ignore
					}

					// Find the img element and replace its src attribute.
					$imgs = $element->getElementsByTagName( 'img' );
					foreach ( $imgs as $img ) {
						$img->setAttribute( 'src', empty( wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' )[0] ) ? MRM_DIR_URL . 'admin/assets/images/mint-placeholder.png' : wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' )[0] );
					}
				}
				if ( $element->hasAttribute( 'class' ) && strpos( $element->getAttribute( 'class' ), 'mint-cart-grid-quantity' ) !== false ) {
					// Replace the text.
					$element->nodeValue = $cart_item['quantity']; // phpcs:ignore
				}
				if ( $element->hasAttribute( 'class' ) && strpos( $element->getAttribute( 'class' ), 'mint-cart-grid-price' ) !== false ) {
					// Replace the product price.
					$element->nodeValue = wc_price( $cart_item[ 'quantity' ] * $product->get_price() ); // phpcs:ignore
				}
			}

			$current_content = $doc->saveHTML();
			$output         .= $current_content;
		}

		// Replace the content of cart blocks in the email body with updated cart items.
		foreach ( $cart_blocks as $cart_block ) {
			$cart_block->nodeValue = $output; // phpcs:ignore
		}

		// Clean up and return the updated email body.
		$email_body = $dom->saveHTML();
		$dom        = new DOMDocument();

		@$dom->loadHTML( $email_body );

		$xpath  = new DOMXPath( $dom );
		$footer = $xpath->query( '//tfoot[contains(@class, "mint-cart-grid-footer")]' );
		if ( $footer->length > 0 ) {
			$footer_element = $footer->item( 0 );

			// Find the span element within the footer.
			$span_element = $xpath->query( './/span[@class="woocommerce-Price-amount amount"]', $footer_element );

			if ( $span_element->length > 0 ) {
				$span_element = $span_element->item( 0 );
				// Replace the content of the span element.
				$span_element->nodeValue = wc_price( $cart_total ); // phpcs:ignore
			}
		}
		$email_body = $dom->saveHTML();
		$email_body = str_replace( '%7B%7B', '{{', $email_body );
		$email_body = str_replace( '%7D%7D', '}}', $email_body );
		return $email_body;
	}

	/**
	 * Checks if the checkout page contains the WooCommerce checkout block.
	 *
	 * This function utilizes the WooCommerce Blocks utility to determine if the
	 * checkout page has the 'woocommerce/checkout' block present.
	 *
	 * Resolves the currently viewed page first (e.g. a WPFunnels funnel checkout step,
	 * which is a different post than the default WooCommerce Checkout page), falling
	 * back to the default checkout page only when no queried page is available.
	 *
	 * @param int $page_id Optional. The page ID to check. Defaults to the current queried page.
	 *
	 * @return bool True if the checkout page contains the WooCommerce checkout block, false otherwise.
	 * @since 1.15.4
	 */
	public static function is_checkout_block( $page_id = 0 ) {
		if ( ! $page_id ) {
			$page_id = get_queried_object_id();
		}
		if ( ! $page_id ) {
			$page_id = wc_get_page_id( 'checkout' );
		}
		return WC_Blocks_Utils::has_block_in_page( $page_id, 'woocommerce/checkout' );
	}
}
