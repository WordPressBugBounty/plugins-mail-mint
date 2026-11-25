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
 * Manage the Easy Digital Downloads customer aggregate schema.
 *
 * Owns `mint_edd_customers`, the per-email rollup of EDD purchase history
 * (first/last order date, order count, lifetime value, AOV, purchased downloads,
 * purchased price variations, categories and tags) that backs the EDD
 * segmentation filters.
 *
 * This mirrors WCCustomerSchema exactly. Two product-identity columns are kept so
 * segments can match at either granularity:
 *   - `purchased_downloads`  : JSON array of bare download IDs ("123") — product level.
 *   - `purchased_price_ids`  : JSON array of "<download_id>:<price_id>" ("123:1") — plan
 *                              level. Non-variable downloads use price_id 0.
 *
 * Note the CREATE TABLE IF NOT EXISTS: dbDelta will not alter a table that already
 * exists, so anything added here reaches fresh installs only. Existing sites are
 * served by DatabaseMigrator::maybe_create_edd_customers_table(), which is
 * self-healing rather than version gated and must be kept in step with this
 * definition.
 *
 * @package /app/Database/Schemas
 * @since 1.31.3
 */
class EDDCustomerSchema {

	/**
	 * Table name.
	 *
	 * @var string
	 * @since 1.31.3
	 */
	public static $table_name = 'mint_edd_customers';

	/**
	 * Create the table on plugin activation.
	 *
	 * Runs dbDelta directly rather than returning SQL so that the identical call also
	 * serves DatabaseMigrator::maybe_create_edd_customers_table(). Returns an empty
	 * string so Upgrade::upgrade_schema() can concatenate the charset collate onto it
	 * harmlessly.
	 *
	 * @return string
	 * @since 1.31.3
	 */
	public function get_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $this->edd_customers_sql( $wpdb->prefix . self::$table_name, $charset_collate ) );

		return '';
	}

	/**
	 * Generate the SQL statement for the EDD customers table.
	 *
	 * @param string $table           Fully prefixed table name.
	 * @param string $charset_collate Charset and collation clause.
	 *
	 * @return string
	 * @since 1.31.3
	 */
	public function edd_customers_sql( $table, $charset_collate ) {
		return "CREATE TABLE IF NOT EXISTS {$table} (
			`id` INT(12) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
			`email_address` VARCHAR(255),
			`l_order_date` DATETIME,
			`f_order_date` DATETIME,
			`total_order_count` INT(7),
			`total_order_value` DOUBLE,
			`aov` DOUBLE,
			`purchased_downloads` LONGTEXT NULL,
			`purchased_price_ids` LONGTEXT NULL,
			`purchased_downloads_cats` LONGTEXT NULL,
			`purchased_downloads_tags` LONGTEXT NULL,
			INDEX `email_address` (`email_address`),
			INDEX `l_order_date` (`l_order_date`),
			INDEX `f_order_date` (`f_order_date`),
			INDEX `total_order_count` (`total_order_count`),
			INDEX `total_order_value` (`total_order_value`)
		) $charset_collate;";
	}
}
