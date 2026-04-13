<?php

/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @package /app/Database/Schemas
 */

namespace Mint\MRM\DataBase\Tables;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Manage form submissions schema.
 *
 * @package /app/Database/Schemas
 * @since 1.16.0
 */
class FormSubmissionsSchema {

	/**
	 * Table name.
	 *
	 * @var string
	 * @since 1.16.0
	 */
	public static $table_name = 'mint_form_submissions';

	/**
	 * Create tables on plugin activation.
	 *
	 * @return void
	 * @since 1.16.0
	 */
	public function get_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = $wpdb->prefix . self::$table_name;

		$sql = "CREATE TABLE {$table} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id         BIGINT UNSIGNED NOT NULL,
			user_id         BIGINT UNSIGNED DEFAULT NULL,
			contact_id      BIGINT UNSIGNED DEFAULT NULL,
			source_url      TEXT DEFAULT NULL,
			status          VARCHAR(45) DEFAULT 'unread',
			browser         VARCHAR(45) DEFAULT NULL,
			device          VARCHAR(45) DEFAULT NULL,
			ip              VARCHAR(45) DEFAULT NULL,
			city            VARCHAR(100) DEFAULT NULL,
			country         VARCHAR(100) DEFAULT NULL,
			utm_source      VARCHAR(191) DEFAULT NULL,
			utm_medium      VARCHAR(191) DEFAULT NULL,
			utm_campaign    VARCHAR(191) DEFAULT NULL,
			utm_term        VARCHAR(191) DEFAULT NULL,
			utm_content     VARCHAR(191) DEFAULT NULL,
			created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_form_id    (form_id),
			INDEX idx_user_id    (user_id),
			INDEX idx_contact_id (contact_id),
			INDEX idx_status     (status),
			INDEX idx_created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
