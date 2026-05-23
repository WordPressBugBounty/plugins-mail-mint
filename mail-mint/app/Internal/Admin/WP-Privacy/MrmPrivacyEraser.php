<?php
/**
 * WordPress personal data eraser for Mail Mint.
 *
 * Registers with wp_privacy_personal_data_erasers so site owners can fulfil
 * right-to-erasure requests (GDPR Article 17) through the standard WP interface.
 *
 * @package Mint\MRM\Internal\Admin
 * @since   1.0.0
 */

namespace Mint\MRM\Internal\Admin;

use Mint\MRM\DataBase\Models\ContactModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WP personal data erasure for Mail Mint contacts.
 *
 * @since 1.0.0
 */
class MrmPrivacyEraser {

	/**
	 * Register this eraser with WordPress.
	 *
	 * Hooked to wp_privacy_personal_data_erasers. Bails early when the
	 * personal_data_erase compliance toggle is disabled.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 * @since 1.0.0
	 */
	public static function register( array $erasers ): array {
		$compliance = get_option( '_mint_compliance', array() );
		$enabled    = isset( $compliance['personal_data_erase'] ) ? $compliance['personal_data_erase'] : 'yes';

		if ( 'yes' !== $enabled ) {
			return $erasers;
		}

		$erasers['mail-mint'] = array(
			'eraser_friendly_name' => __( 'Mail Mint Data', 'mrm' ),
			'callback'             => array( self::class, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Erase all Mail Mint personal data for the given email address.
	 *
	 * Deletes the contact record (ContactModel::destroy handles meta, notes,
	 * group pivots), then removes broadcast emails, form submissions,
	 * automation log entries, abandoned carts, and download tracking rows
	 * that reference the email address.
	 *
	 * @param string $email_address Subject email address.
	 * @param int    $page          Page number (unused — all data handled in one pass).
	 * @return array{items_removed: int, items_retained: int, messages: array, done: bool}
	 * @since 1.0.0
	 */
	public static function erase( string $email_address, int $page = 1 ): array {
		global $wpdb;

		$email   = sanitize_email( $email_address );
		$removed = 0;

		$contact_id = ContactModel::get_id_by_email( $email );

		if ( $contact_id ) {
			// Removes contact + meta + notes + group pivots.
			ContactModel::destroy( $contact_id );
			$removed++;

			// Broadcast emails sent to this contact.
			$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . 'mint_broadcast_emails',
				array( 'contact_id' => $contact_id ),
				array( '%d' )
			);
			$removed += $deleted ? (int) $deleted : 0;

			// Form submissions.
			$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . 'mint_form_submissions',
				array( 'contact_id' => $contact_id ),
				array( '%d' )
			);
			$removed += $deleted ? (int) $deleted : 0;
		}

		// Automation log is keyed by email, not contact_id.
		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'mint_automation_log',
			array( 'email' => $email ),
			array( '%s' )
		);
		$removed += $deleted ? (int) $deleted : 0;

		// Abandoned carts (pro feature — only if table exists).
		$carts_table = $wpdb->prefix . 'mint_abandoned_carts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $carts_table ) ) === $carts_table ) {
			$deleted = $wpdb->delete( $carts_table, array( 'email' => $email ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$removed += $deleted ? (int) $deleted : 0;
		}

		// Download tracking.
		$downloads_table = $wpdb->prefix . 'mint_lead_magnet_download_tracking';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $downloads_table ) ) === $downloads_table ) {
			$deleted = $wpdb->delete( $downloads_table, array( 'email' => $email ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$removed += $deleted ? (int) $deleted : 0;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
