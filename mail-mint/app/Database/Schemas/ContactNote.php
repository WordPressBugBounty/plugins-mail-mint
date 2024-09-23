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
 * [Manage contact note table schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class ContactNoteSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_contact_note';


	/**
	 * Get the schema of Contact note table
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `contact_id` BIGINT(20) UNSIGNED NOT NULL,
            `type` VARCHAR(255) NOT NULL,
            `title` VARCHAR(255),
            `description` longtext,
            `created_by` BIGINT(20),
            `status` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `is_public` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            PRIMARY KEY (`id`),
            KEY `contact_id` (`contact_id`)
         ) ";
	}
}
