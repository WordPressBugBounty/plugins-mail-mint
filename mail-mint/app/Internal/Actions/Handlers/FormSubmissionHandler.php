<?php
/**
 * Handles storing form submission data after a Mail Mint form is submitted.
 *
 * Hooks into `mailmint_after_form_submit` to write one row into
 * `mint_form_submissions` and one row per field into `mint_form_entry_details`.
 *
 * @package MailMint\App\Actions\Handlers
 * @author  [MRM Team]
 * @email   [support@getwpfunnels.com]
 * @since   1.16.0
 */

namespace MailMint\App\Actions\Handlers;

use Mint\MRM\DataBase\Models\FormSubmissionModel;
use MRM\Common\MrmCommon;

/**
 * FormSubmissionHandler class.
 *
 * @since 1.16.0
 */
class FormSubmissionHandler {

	/**
	 * Store submission data after a form is submitted.
	 *
	 * Called via the `mailmint_after_form_submit` action hook.
	 *
	 * @param int    $form_id    ID of the submitted form.
	 * @param int    $contact_id ID of the contact created/updated during submission.
	 * @param object $contact    ContactData object passed by the form action.
	 *
	 * @return void
	 *
	 * @since 1.16.0
	 */
	public function store( $form_id, $contact_id, $contact ) {
		$submission_data = $this->build_submission_data( $form_id, $contact_id );
		$submission_id   = FormSubmissionModel::insert( $submission_data );

		if ( ! $submission_id ) {
			return;
		}

		$entry_fields = $this->build_entry_fields();
		if ( ! empty( $entry_fields ) ) {
			FormSubmissionModel::insert_entry_details_bulk( $submission_id, $entry_fields );
		}
	}


	/**
	 * Build the data array for the mint_form_submissions row.
	 *
	 * @param int $form_id    Form ID.
	 * @param int $contact_id Contact ID.
	 *
	 * @return array
	 *
	 * @since 1.16.0
	 */
	private function build_submission_data( $form_id, $contact_id ) {
		$ip      = $this->get_ip();
		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; //phpcs:ignore

		$data = array(
			'form_id'      => (int) $form_id,
			'contact_id'   => $contact_id ? (int) $contact_id : null,
			'user_id'      => get_current_user_id() ?: null,
			'status'       => 'unread',
			'ip'           => $ip,
			'browser'      => $this->parse_browser( $ua ),
			'device'       => $this->parse_device( $ua ),
			'source_url'   => $this->get_source_url(),
			'utm_source'   => $this->get_utm( 'utm_source' ),
			'utm_medium'   => $this->get_utm( 'utm_medium' ),
			'utm_campaign' => $this->get_utm( 'utm_campaign' ),
			'utm_term'     => $this->get_utm( 'utm_term' ),
			'utm_content'  => $this->get_utm( 'utm_content' ),
		);

		// Strip null values so DB defaults apply for optional columns.
		return array_filter( $data, function( $v ) {
			return ! is_null( $v );
		} );
	}


	/**
	 * Build the entry-detail rows from the raw POST data.
	 *
	 * Parses the URL-encoded `post_data` string that the frontend JS
	 * serialises and sends, storing every submitted field as-is.
	 *
	 * @return array Array of ['field_name', 'field_type', 'field_value'] maps.
	 *
	 * @since 1.16.0
	 */
	private function build_entry_fields() {
		$fields = array();

		$raw_post_data = isset( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : ''; //phpcs:ignore
		if ( ! $raw_post_data ) {
			return $fields;
		}

		parse_str( $raw_post_data, $post_data );

		foreach ( $post_data as $key => $value ) {
			$fields[] = array(
				'field_name'  => sanitize_text_field( $key ),
				'field_type'  => $this->infer_field_type( $key, $value ),
				'field_value' => is_array( $value ) ? wp_json_encode( $value ) : sanitize_textarea_field( $value ),
			);
		}

		return $fields;
	}


	/**
	 * Get the visitor's IP address, respecting the anonymize_ip compliance setting.
	 *
	 * @return string|null
	 *
	 * @since 1.16.0
	 */
	private function get_ip() {
		$compliance   = get_option( '_mint_compliance', array() );
		$anonymize_ip = isset( $compliance['anonymize_ip'] ) ? $compliance['anonymize_ip'] : 'no';

		if ( 'yes' === $anonymize_ip ) {
			return null;
		}

		$ip = MrmCommon::get_user_ip();
		return ( 'UNKNOWN' === $ip ) ? null : $ip;
	}


	/**
	 * Get the page URL where the form was submitted.
	 *
	 * Prefers the `source_url` query param sent by the frontend JS,
	 * falling back to HTTP_REFERER.
	 *
	 * @return string|null
	 *
	 * @since 1.16.0
	 */
	private function get_source_url() {
		// Sent explicitly by the JS (see frontend.js).
		if ( ! empty( $_POST['source_url'] ) ) { //phpcs:ignore
			return esc_url_raw( wp_unslash( $_POST['source_url'] ) ); //phpcs:ignore
		}
		// Fallback: HTTP referrer header.
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) { //phpcs:ignore
			return esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ); //phpcs:ignore
		}
		return null;
	}


	/**
	 * Get a UTM parameter from the POST data sent by the frontend JS.
	 *
	 * @param string $key UTM key (e.g. 'utm_source').
	 *
	 * @return string|null
	 *
	 * @since 1.16.0
	 */
	private function get_utm( $key ) {
		if ( ! empty( $_POST[ $key ] ) ) { //phpcs:ignore
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); //phpcs:ignore
		}
		return null;
	}


	/**
	 * Extract a simplified browser name from a User-Agent string.
	 *
	 * @param string $ua User-Agent header value.
	 *
	 * @return string|null Browser name, or null if unrecognised.
	 *
	 * @since 1.16.0
	 */
	private function parse_browser( $ua ) {
		if ( ! $ua ) {
			return null;
		}

		if ( false !== strpos( $ua, 'Edg/' ) || false !== strpos( $ua, 'Edge/' ) ) {
			return 'Edge';
		}
		if ( false !== strpos( $ua, 'OPR/' ) || false !== strpos( $ua, 'Opera/' ) ) {
			return 'Opera';
		}
		if ( false !== strpos( $ua, 'Chrome/' ) ) {
			return 'Chrome';
		}
		if ( false !== strpos( $ua, 'Firefox/' ) ) {
			return 'Firefox';
		}
		if ( false !== strpos( $ua, 'Safari/' ) && false === strpos( $ua, 'Chrome' ) ) {
			return 'Safari';
		}
		if ( false !== strpos( $ua, 'MSIE ' ) || false !== strpos( $ua, 'Trident/' ) ) {
			return 'Internet Explorer';
		}

		return 'Other';
	}


	/**
	 * Detect the device brand/manufacturer from a User-Agent string.
	 *
	 * Returns brand names like 'Apple', 'Samsung', 'Google', 'Huawei', etc.
	 * Falls back to 'Desktop' when no mobile/tablet brand is matched.
	 *
	 * @param string $ua User-Agent header value.
	 *
	 * @return string|null Brand name, 'Desktop', or null when UA is empty.
	 *
	 * @since 1.16.0
	 */
	private function parse_device( $ua ) {
		if ( ! $ua ) {
			return null;
		}

		// Apple — iPhone, iPad, iPod all contain 'iPhone OS' or 'CPU OS' (iPad) or 'iPod'.
		if ( preg_match( '/iphone|ipad|ipod/i', $ua ) ) {
			return 'Apple';
		}

		// Samsung phones/tablets send 'SM-' or 'Samsung' in the UA.
		if ( preg_match( '/samsung|SM-[A-Z0-9]+/i', $ua ) ) {
			return 'Samsung';
		}

		// Google Pixel phones.
		if ( preg_match( '/pixel\s?\d|nexus\s?\d/i', $ua ) ) {
			return 'Google';
		}

		// Huawei / Honor.
		if ( preg_match( '/huawei|honor|HMA-|VOG-|CLT-|ELE-|PCT-/i', $ua ) ) {
			return 'Huawei';
		}

		// Xiaomi / Redmi / POCO.
		if ( preg_match( '/xiaomi|redmi|poco|MI\s|MIX\s/i', $ua ) ) {
			return 'Xiaomi';
		}

		// OnePlus.
		if ( preg_match( '/oneplus|IN2\d{3}|BE2\d{3}|LE2\d{3}/i', $ua ) ) {
			return 'OnePlus';
		}

		// Sony Xperia.
		if ( preg_match( '/sony|xperia/i', $ua ) ) {
			return 'Sony';
		}

		// LG.
		if ( preg_match( '/\bLG-|\bLG\b/i', $ua ) ) {
			return 'LG';
		}

		// Motorola / Moto.
		if ( preg_match( '/motorola|moto\s[a-z]/i', $ua ) ) {
			return 'Motorola';
		}

		// Nokia.
		if ( preg_match( '/nokia/i', $ua ) ) {
			return 'Nokia';
		}

		// BlackBerry.
		if ( preg_match( '/blackberry|bb\d+/i', $ua ) ) {
			return 'BlackBerry';
		}

		// Generic Android device when no brand was matched above.
		if ( preg_match( '/android/i', $ua ) ) {
			return 'Android';
		}

		// Windows Phone.
		if ( preg_match( '/windows phone|iemobile|wpdesktop/i', $ua ) ) {
			return 'Windows Phone';
		}

		return 'Desktop';
	}


	/**
	 * Infer a simple field type from its key and value.
	 *
	 * @param string $key   Field key/name.
	 * @param mixed  $value Submitted value.
	 *
	 * @return string
	 *
	 * @since 1.16.0
	 */
	private function infer_field_type( $key, $value ) {
		if ( is_array( $value ) ) {
			return 'checkbox';
		}
		if ( false !== strpos( $key, 'phone' ) || false !== strpos( $key, 'mobile' ) ) {
			return 'phone';
		}
		if ( false !== strpos( $key, 'date' ) ) {
			return 'date';
		}
		if ( false !== strpos( $key, 'url' ) || false !== strpos( $key, 'website' ) ) {
			return 'url';
		}
		return 'text';
	}
}
