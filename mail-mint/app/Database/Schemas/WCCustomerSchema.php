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
 * Manage the WooCommerce customer aggregate schema.
 *
 * Owns `mint_wc_customers`, the per-email rollup of WooCommerce order history
 * (first/last order date, order count, lifetime value, AOV, purchased products,
 * categories, tags and coupons) that backs the WooCommerce segmentation filters
 * and the Pro WooCommerce automation conditions.
 *
 * Until 1.31.2 this table was created only by
 * DatabaseMigrator::mm_update_1140_migrate_woocommerce_order_custom_table(), a
 * version-gated migration that a fresh install can never reach: activation stamps
 * mail_mint_db_version with MRM_DB_VERSION, which has been >= 1.14.0 since the very
 * commit that added the migration, so its `< 1.14.0` gate is always false. Fresh
 * installs therefore ran without the table, and every read against it errored. The
 * structure below is byte-for-byte the one that migration creates, so sites that did
 * get the table keep it untouched.
 *
 * Note the CREATE TABLE IF NOT EXISTS: dbDelta will not alter a table that already
 * exists, so anything added here reaches fresh installs only. Existing sites are
 * served by DatabaseMigrator::maybe_create_wc_customers_table(), which is
 * self-healing rather than version gated and must be kept in step with this
 * definition.
 *
 * @package /app/Database/Schemas
 * @since 1.31.2
 */
class WCCustomerSchema {

	/**
	 * Table name.
	 *
	 * @var string
	 * @since 1.31.2
	 */
	public static $table_name = 'mint_wc_customers';

	/**
	 * Create the table on plugin activation.
	 *
	 * Runs dbDelta directly rather than returning SQL so that the identical call also
	 * serves DatabaseMigrator::maybe_create_wc_customers_table(). Returns an empty
	 * string so Upgrade::upgrade_schema() can concatenate the charset collate onto it
	 * harmlessly.
	 *
	 * @return string
	 * @since 1.31.2
	 */
	public function get_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $this->wc_customers_sql( $wpdb->prefix . self::$table_name, $charset_collate ) );

		return '';
	}

	/**
	 * Generate the SQL statement for the WooCommerce customers table.
	 *
	 * @param string $table           Fully prefixed table name.
	 * @param string $charset_collate Charset and collation clause.
	 *
	 * @return string
	 * @since 1.31.2
	 */
	public function wc_customers_sql( $table, $charset_collate ) {
		return "CREATE TABLE IF NOT EXISTS {$table} (
			`id` INT(12) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			`email_address` VARCHAR(255),
			`l_order_date` DATETIME,
			`f_order_date` DATETIME,
			`total_order_count` INT(7),
			`total_order_value` DOUBLE,
			`aov` DOUBLE,
			`purchased_products` LONGTEXT NULL,
			`purchased_products_cats` LONGTEXT NULL,
			`purchased_products_tags` LONGTEXT NULL,
			`used_coupons` LONGTEXT NULL,
			INDEX `email_address` (`email_address`),
			INDEX `l_order_date` (`l_order_date`),
			INDEX `f_order_date` (`f_order_date`),
			INDEX `total_order_count` (`total_order_count`),
			INDEX `total_order_value` (`total_order_value`)
		) $charset_collate;";
	}
}
