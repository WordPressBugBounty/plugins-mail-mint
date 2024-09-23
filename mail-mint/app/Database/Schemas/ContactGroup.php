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
 * [Manage contact group schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class ContactGroupSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_contact_groups';

	/**
	 * Get the schema of ContactGroup table
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `type` VARCHAR(192) NOT NULL COMMENT 'tags, lists, segments',
            `data` longtext, 
            `created_at` TIMESTAMP,
            `updated_at` TIMESTAMP,
            INDEX `type` (`type`)
        ) ";
	}
}
