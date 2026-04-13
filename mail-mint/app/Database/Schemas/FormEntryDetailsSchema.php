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
 * Manage form entry details schema.
 *
 * @package /app/Database/Schemas
 * @since 1.16.0
 */
class FormEntryDetailsSchema {

	/**
	 * Table name.
	 *
	 * @var string
	 * @since 1.16.0
	 */
	public static $table_name = 'mint_form_entry_details';

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
			submission_id   BIGINT UNSIGNED NOT NULL,
			field_name      VARCHAR(255) NOT NULL,
			field_type      VARCHAR(50) DEFAULT NULL,
			field_value     LONGTEXT DEFAULT NULL,
			PRIMARY KEY (id),
			INDEX idx_submission_id    (submission_id),
			INDEX idx_field_name       (field_name),
			INDEX idx_submission_field (submission_id, field_name)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
