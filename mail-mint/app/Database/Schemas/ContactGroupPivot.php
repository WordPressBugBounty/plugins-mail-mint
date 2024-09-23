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
 * [Manage contact group pivot table schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class ContactGroupPivotSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_contact_group_relationship';

	/**
	 * Get the schema of Contact group pivot table
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `contact_id` BIGINT UNSIGNED NOT NULL,
            `group_id` BIGINT UNSIGNED NOT NULL COMMENT 'list_id or tag_id or segment_id',
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `contact_id_pivot_index` (`contact_id` ASC),
            INDEX `group_id_pivot_index` (`group_id` ASC)
        ) ";
	}
}
