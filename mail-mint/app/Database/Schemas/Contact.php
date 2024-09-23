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
 * [Manage contact schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class ContactSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_contacts';


	/**
	 * Get the schema of Contact table
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `wp_user_id` BIGINT UNSIGNED NULL,
            `hash` VARCHAR(90) NULL,
            `email` VARCHAR(190) NOT NULL UNIQUE,
            `first_name` VARCHAR(192) NULL,
            `last_name` VARCHAR(192) NULL,
            `scores` INT UNSIGNED NOT NULL,
            `source` VARCHAR(50) NULL,
            `status` VARCHAR(50) NOT NULL,
            `stage` VARCHAR(50) NOT NULL,
            `last_activity` TIMESTAMP NULL,
            `created_by` BIGINT(20),
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
             INDEX `contact_email_index` (`email` ASC),
             INDEX `contact_id_index` (`id` ASC)
         ) ";
	}
}
