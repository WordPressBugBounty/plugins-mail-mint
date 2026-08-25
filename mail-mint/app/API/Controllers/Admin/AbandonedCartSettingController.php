<?php
/**
 * Mail Mint
 *
 * Reads and writes the abandoned cart settings.
 *
 * @package /app/API/Controllers/Admin
 * @since   1.31.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use MRM\Common\MrmCommon;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the abandoned cart settings API callbacks.
 *
 * @package /app/API/Controllers/Admin
 * @since 1.31.0
 */
class AbandonedCartSettingController extends AdminBaseController {

	/**
	 * The option key used to store the abandoned cart settings.
	 *
	 * @var string
	 * @since 1.31.0
	 */
	private $option_key = '_mint_abandoned_cart_settings';

	/**
	 * Insert or update the abandoned cart settings.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 * @since 1.31.0
	 */
	public function insert_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( ! is_array( $params ) || empty( $params ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'Missing or empty parameters', 'mrm' ),
				)
			);
		}

		if ( isset( $params['_locale'] ) ) {
			unset( $params['_locale'] );
		}

		$params['recovered_statuses'] = $this->sanitize_recovered_statuses( $params );
		$params['cool_off_period']    = isset( $params['cool_off_period'] ) ? max( 0, (int) $params['cool_off_period'] ) : 10;
		$params['blacklist_emails']   = $this->sanitize_blacklist_emails( $params );
		$params['new_contact_status'] = $this->sanitize_new_contact_status( $params );

		// Blacklisting is a Pro feature. A Free site (or a direct API call bypassing
		// the locked UI) cannot persist it as enabled, even if submitted.
		$params['enable_blacklist'] = ! empty( $params['enable_blacklist'] ) && MrmCommon::is_mailmint_pro_active();

		update_option( $this->option_key, $params );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Abandoned cart settings have been successfully saved.', 'mrm' ),
			)
		);
	}

	/**
	 * Normalises the order statuses a cart may be marked recovered on.
	 *
	 * An absent key falls back to the defaults so a client that predates this
	 * setting cannot blank it out, while an explicitly empty array is honoured —
	 * that is a deliberate choice to stop recovering carts automatically.
	 *
	 * @param array $params The submitted settings.
	 *
	 * @return array Sanitised list of status slugs.
	 * @since 1.31.0
	 */
	private function sanitize_recovered_statuses( $params ) {
		if ( ! isset( $params['recovered_statuses'] ) ) {
			return CartCommon::default_recovered_statuses();
		}

		if ( ! is_array( $params['recovered_statuses'] ) ) {
			return array();
		}

		$allowed  = wp_list_pluck( CartCommon::get_selectable_order_statuses(), 'id' );
		$statuses = array_map( 'sanitize_key', $params['recovered_statuses'] );

		// Only filter against the allow list when WooCommerce is available to supply one.
		if ( ! empty( $allowed ) ) {
			$statuses = array_intersect( $statuses, $allowed );
		}

		return array_values( array_unique( array_filter( $statuses ) ) );
	}

	/**
	 * Normalises the status given to contacts created from a cart.
	 *
	 * Falls back to the default for anything outside the two-value allow list, so a
	 * client that predates the setting — or one posting a status the cart path has no
	 * business assigning, such as 'pending' or 'unsubscribed' — cannot persist it.
	 *
	 * @param array $params The submitted settings.
	 *
	 * @return string One of CartCommon::new_contact_status_options().
	 * @since 1.32.0
	 */
	private function sanitize_new_contact_status( $params ) {
		$status = isset( $params['new_contact_status'] ) ? sanitize_key( $params['new_contact_status'] ) : '';

		return in_array( $status, CartCommon::new_contact_status_options(), true )
			? $status
			: CartCommon::DEFAULT_NEW_CONTACT_STATUS;
	}

	/**
	 * Normalises the submitted blacklist into a list of lowercase emails/domains.
	 *
	 * Accepts either the array shape the REST client sends, or a newline-delimited
	 * string, so a raw textarea value posted without client-side splitting still saves.
	 *
	 * @param array $params The submitted settings.
	 *
	 * @return array Sanitised list of email addresses and/or bare domains.
	 * @since 1.31.0
	 */
	private function sanitize_blacklist_emails( $params ) {
		if ( ! isset( $params['blacklist_emails'] ) ) {
			return array();
		}

		$entries = $params['blacklist_emails'];

		if ( is_string( $entries ) ) {
			$entries = preg_split( '/[\r\n,]+/', $entries );
		}

		if ( ! is_array( $entries ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $entries as $entry ) {
			$entry = strtolower( ltrim( trim( (string) $entry ), '@' ) );

			if ( '' === $entry ) {
				continue;
			}

			$sanitized[] = is_email( $entry ) ? sanitize_email( $entry ) : sanitize_text_field( $entry );
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Retrieve the abandoned cart settings.
	 *
	 * @return \WP_REST_Response
	 * @since 1.31.0
	 */
	public function get() {
		$settings = get_option( $this->option_key, CartCommon::abandoned_cart_default_configuration() );

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'No settings found', 'mrm' ),
				)
			);
		}

		// Sites that saved their settings before this option existed have no such key.
		// Backfilling it here stops the UI from rendering every status unticked and then
		// persisting that as an explicit "never recover" choice on the next save.
		if ( ! isset( $settings['recovered_statuses'] ) ) {
			$settings['recovered_statuses'] = CartCommon::default_recovered_statuses();
		}

		if ( ! isset( $settings['cool_off_period'] ) ) {
			$settings['cool_off_period'] = 10;
		}

		if ( ! isset( $settings['enable_blacklist'] ) ) {
			$settings['enable_blacklist'] = false;
		}

		if ( ! isset( $settings['blacklist_emails'] ) ) {
			$settings['blacklist_emails'] = array();
		}

		// Same backfill reasoning as recovered_statuses: an option row saved before this
		// setting existed has no such key, and an unresolved value would render the
		// dropdown blank and then persist that blank on the next save.
		$settings['new_contact_status'] = CartCommon::get_new_contact_status();

		// Reflect current licensing even if a value was persisted while Pro was active
		// and Pro has since been deactivated — the stored option is not the source of truth.
		$settings['enable_blacklist'] = ! empty( $settings['enable_blacklist'] ) && MrmCommon::is_mailmint_pro_active();

		return rest_ensure_response(
			array(
				'success' => true,
				'results' => $settings,
			)
		);
	}
}
