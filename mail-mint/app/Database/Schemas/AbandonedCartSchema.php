<?php
/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @package /app/Database/Schemas
 */

namespace Mint\MRM\DataBase\Tables;

/**
 * Manage abandoned cart schema.
 *
 * Owns the two cart-tracking tables. The structure was intentionally identical to the
 * one Mail Mint Pro shipped up to 1.30.1, so sites that already had Pro data kept it
 * untouched when Free took ownership of the feature.
 *
 * Since 1.31.1 it also carries the recovery attribution columns and the composite
 * lookup indexes. Note the CREATE TABLE IF NOT EXISTS below: dbDelta will not alter a
 * table that already exists, so anything added here reaches fresh installs only.
 * Existing sites — including every site that already had these tables from Pro — are
 * served by DatabaseMigrator::maybe_upgrade_abandoned_cart_columns() and
 * build_abandoned_cart_indexes(), both of which are self-healing rather than version
 * gated, and both of which must be kept in step with this definition.
 *
 * @package /app/Database/Schemas
 * @since 1.31.0
 */
class AbandonedCartSchema {

	/**
	 * Table name.
	 *
	 * @var string
	 * @since 1.31.0
	 */
	public static $table_name = 'mint_abandoned_carts';

	/**
	 * Meta table name.
	 *
	 * @var string
	 * @since 1.31.0
	 */
	public static $meta_table_name = 'mint_abandoned_carts_meta';

	/**
	 * Create tables on plugin activation.
	 *
	 * Runs dbDelta directly rather than returning SQL, because this schema creates two
	 * tables. Returns an empty string so Upgrade::upgrade_schema() can concatenate the
	 * charset collate onto it harmlessly.
	 *
	 * @return string
	 * @since 1.31.0
	 */
	public function get_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $this->abandoned_carts_sql( $wpdb->prefix . self::$table_name, $charset_collate ) );
		dbDelta( $this->abandoned_carts_meta_sql( $wpdb->prefix . self::$meta_table_name, $charset_collate ) );

		return '';
	}

	/**
	 * Generate the SQL statement for the abandoned carts table.
	 *
	 * @param string $table           Fully prefixed table name.
	 * @param string $charset_collate Charset and collation clause.
	 *
	 * @return string
	 * @since 1.31.0
	 */
	public function abandoned_carts_sql( $table, $charset_collate ) {
		return "CREATE TABLE IF NOT EXISTS {$table} (
			`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			`email` VARCHAR(255),
			`user_id` BIGINT(20),
			`automation_id` BIGINT(20),
			`status` VARCHAR(50) NOT NULL,
			`items` LONGTEXT,
			`provider` VARCHAR(32),
			`currency` VARCHAR(10),
			`total` VARCHAR(32),
			`token` VARCHAR(64),
			`session_key` VARCHAR(64),
			`checkout_data` LONGTEXT,
			`recovery_source` VARCHAR(20) NULL DEFAULT NULL,
			`recovered_at` TIMESTAMP NULL,
			`created_at` TIMESTAMP NULL,
			`updated_at` TIMESTAMP NULL,
			INDEX `abandoned_cart_id` (`id` ASC),
			INDEX `abandoned_cart_email` (`email` ASC),
			INDEX `idx_ac_session_status` (`session_key`(64), `status`(20)),
			INDEX `idx_ac_email_status` (`email`(150), `status`(20)),
			INDEX `idx_ac_status_created` (`status`(20), `created_at`),
			INDEX `idx_ac_token` (`token`(64))
		) $charset_collate;";
	}

	/**
	 * Generate the SQL statement for the abandoned carts meta table.
	 *
	 * @param string $table           Fully prefixed table name.
	 * @param string $charset_collate Charset and collation clause.
	 *
	 * @return string
	 * @since 1.31.0
	 */
	public function abandoned_carts_meta_sql( $table, $charset_collate ) {
		return "CREATE TABLE IF NOT EXISTS {$table} (
			`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			`abandoned_cart_id` BIGINT(20),
			`meta_key` VARCHAR(255),
			`meta_value` LONGTEXT,
			`created_at` TIMESTAMP NULL,
			`updated_at` TIMESTAMP NULL,
			INDEX `abandoned_cart_id` (`abandoned_cart_id` ASC)
		) $charset_collate;";
	}
}
