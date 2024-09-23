<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Database/Schemas
 */

namespace Mint\MRM\DataBase\Tables;

require_once MRM_DIR_PATH . 'app/Interfaces/Schema.php';

use Mint\MRM\Interfaces\Schema;

/**
 * [Manage custom field table schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class CustomFieldSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_custom_fields';

	/**
	 * Create the table.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;

		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(255) NOT NULL,
                `group_id` BIGINT(20) DEFAULT 1,
                `type` VARCHAR(192) NOT NULL COMMENT 'text-input, text-number, text-area, dropdown, radio-button, checkbox date',
                `meta` TEXT NOT NULL,
                `created_at` TIMESTAMP,
                `updated_at` TIMESTAMP
            ) ";
	}
}
