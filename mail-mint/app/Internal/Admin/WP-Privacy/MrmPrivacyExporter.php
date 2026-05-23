<?php
/**
 * WordPress personal data exporter for Mail Mint.
 *
 * Registers with wp_privacy_personal_data_exporters so site owners can fulfil
 * Subject Access Requests (GDPR Article 15) through the standard WP interface.
 *
 * @package Mint\MRM\Internal\Admin
 * @since   1.0.0
 */

namespace Mint\MRM\Internal\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WP personal data export for Mail Mint contacts.
 *
 * @since 1.0.0
 */
class MrmPrivacyExporter {

	/**
	 * Register this exporter with WordPress.
	 *
	 * Hooked to wp_privacy_personal_data_exporters. Bails early when the
	 * personal_data_export compliance toggle is disabled.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 * @since 1.0.0
	 */
	public static function register( array $exporters ): array {
		$compliance = get_option( '_mint_compliance', array() );
		$enabled    = isset( $compliance['personal_data_export'] ) ? $compliance['personal_data_export'] : 'yes';

		if ( 'yes' !== $enabled ) {
			return $exporters;
		}

		$exporters['mail-mint'] = array(
			'exporter_friendly_name' => __( 'Mail Mint Data', 'mrm' ),
			'callback'               => array( self::class, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Export all Mail Mint personal data for the given email address.
	 *
	 * @param string $email_address Subject email address.
	 * @param int    $page          Page number (unused — all data returned in one pass).
	 * @return array{data: array, done: bool}
	 * @since 1.0.0
	 */
	public static function export( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$email   = sanitize_email( $email_address );
		$export  = array();

		// --- Contact record ---
		$contact = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name, status, source, created_at FROM {$wpdb->prefix}mint_contacts WHERE email = %s LIMIT 1",
				$email
			)
		);

		if ( ! $contact ) {
			return array( 'data' => array(), 'done' => true );
		}

		$contact_id = (int) $contact->id;

		$contact_data = array();
		foreach ( (array) $contact as $key => $value ) {
			if ( ! is_null( $value ) && '' !== $value ) {
				$contact_data[] = array(
					'name'  => self::label( $key ),
					'value' => (string) $value,
				);
			}
		}

		$export[] = array(
			'group_id'    => 'mail-mint-contact',
			'group_label' => __( 'Mail Mint — Contact', 'mrm' ),
			'item_id'     => 'contact-' . $contact_id,
			'data'        => $contact_data,
		);

		// --- Contact meta ---
		$meta_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->prefix}mint_contact_meta WHERE contact_id = %d",
				$contact_id
			),
			ARRAY_A
		);

		if ( ! empty( $meta_rows ) ) {
			$meta_data = array();
			foreach ( $meta_rows as $row ) {
				$meta_data[] = array(
					'name'  => esc_html( $row['meta_key'] ),
					'value' => esc_html( $row['meta_value'] ),
				);
			}
			$export[] = array(
				'group_id'    => 'mail-mint-contact-meta',
				'group_label' => __( 'Mail Mint — Contact Meta', 'mrm' ),
				'item_id'     => 'contact-meta-' . $contact_id,
				'data'        => $meta_data,
			);
		}

		// --- Email history ---
		$emails = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT be.email_type, be.status, be.scheduled_at,
				        camp.title AS campaign_title,
				        auto.name  AS automation_name
				 FROM {$wpdb->prefix}mint_broadcast_emails be
				 LEFT JOIN {$wpdb->prefix}mint_campaigns camp ON be.campaign_id   = camp.id
				 LEFT JOIN {$wpdb->prefix}mint_automations auto ON be.automation_id = auto.id
				 WHERE be.contact_id = %d
				 ORDER BY be.scheduled_at DESC",
				$contact_id
			),
			ARRAY_A
		);

		foreach ( $emails as $i => $row ) {
			$export[] = array(
				'group_id'    => 'mail-mint-email-history',
				'group_label' => __( 'Mail Mint — Email History', 'mrm' ),
				'item_id'     => 'email-' . $contact_id . '-' . $i,
				'data'        => self::row_to_data( $row ),
			);
		}

		// --- Form submissions ---
		$submissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT fs.status, fs.browser, fs.device, fs.ip, fs.city, fs.country,
				        fs.utm_source, fs.utm_medium, fs.utm_campaign, fs.created_at,
				        f.title AS form_title
				 FROM {$wpdb->prefix}mint_form_submissions fs
				 LEFT JOIN {$wpdb->prefix}mint_forms f ON fs.form_id = f.id
				 WHERE fs.contact_id = %d
				 ORDER BY fs.created_at DESC",
				$contact_id
			),
			ARRAY_A
		);

		foreach ( $submissions as $i => $row ) {
			$export[] = array(
				'group_id'    => 'mail-mint-form-submissions',
				'group_label' => __( 'Mail Mint — Form Submissions', 'mrm' ),
				'item_id'     => 'form-submission-' . $contact_id . '-' . $i,
				'data'        => self::row_to_data( $row ),
			);
		}

		// --- Automation log ---
		$logs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT al.status, al.step_id, al.count AS execution_count, al.created_at,
				        auto.name AS automation_name
				 FROM {$wpdb->prefix}mint_automation_log al
				 LEFT JOIN {$wpdb->prefix}mint_automations auto ON al.automation_id = auto.id
				 WHERE al.email = %s
				 ORDER BY al.created_at DESC",
				$email
			),
			ARRAY_A
		);

		foreach ( $logs as $i => $row ) {
			$export[] = array(
				'group_id'    => 'mail-mint-automation-log',
				'group_label' => __( 'Mail Mint — Automation History', 'mrm' ),
				'item_id'     => 'automation-log-' . $contact_id . '-' . $i,
				'data'        => self::row_to_data( $row ),
			);
		}

		// --- Abandoned carts (table may only exist when pro is active) ---
		$carts_table = $wpdb->prefix . 'mint_abandoned_carts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $carts_table ) ) === $carts_table ) {
			$carts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT status, total AS cart_total, provider, created_at FROM {$carts_table} WHERE email = %s ORDER BY created_at DESC",
					$email
				),
				ARRAY_A
			);

			foreach ( $carts as $i => $row ) {
				$export[] = array(
					'group_id'    => 'mail-mint-abandoned-carts',
					'group_label' => __( 'Mail Mint — Abandoned Carts', 'mrm' ),
					'item_id'     => 'abandoned-cart-' . $contact_id . '-' . $i,
					'data'        => self::row_to_data( $row ),
				);
			}
		}

		// --- Download tracking ---
		$downloads_table = $wpdb->prefix . 'mint_lead_magnet_download_tracking';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $downloads_table ) ) === $downloads_table ) {
			$downloads = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT is_used, expires_at, ip_address, created_at FROM {$downloads_table} WHERE email = %s ORDER BY created_at DESC",
					$email
				),
				ARRAY_A
			);

			foreach ( $downloads as $i => $row ) {
				$export[] = array(
					'group_id'    => 'mail-mint-download-tracking',
					'group_label' => __( 'Mail Mint — Download Tracking', 'mrm' ),
					'item_id'     => 'download-' . $contact_id . '-' . $i,
					'data'        => self::row_to_data( $row ),
				);
			}
		}

		return array(
			'data' => $export,
			'done' => true,
		);
	}

	/**
	 * Convert an associative row array into WP exporter data pairs, skipping nulls.
	 *
	 * @param array $row Associative array of column => value.
	 * @return array
	 * @since 1.0.0
	 */
	private static function row_to_data( array $row ): array {
		$data = array();
		foreach ( $row as $key => $value ) {
			if ( ! is_null( $value ) && '' !== $value ) {
				$data[] = array(
					'name'  => self::label( $key ),
					'value' => esc_html( (string) $value ),
				);
			}
		}
		return $data;
	}

	/**
	 * Convert a snake_case column name to a human-readable label.
	 *
	 * @param string $key Column name.
	 * @return string
	 * @since 1.0.0
	 */
	private static function label( string $key ): string {
		return ucwords( str_replace( '_', ' ', $key ) );
	}
}
