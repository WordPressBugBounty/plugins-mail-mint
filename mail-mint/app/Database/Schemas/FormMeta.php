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
 * [Manage form meta table schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class FormMetaSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_form_meta';


	/**
	 * Get the schema of Form meta table
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table      = $wpdb->prefix . self::$table_name;
		$form_table = $wpdb->prefix . FormSchema::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `form_id` BIGINT UNSIGNED NOT NULL,
            `meta_key` VARCHAR(50) DEFAULT NULL,    
            `meta_value` longtext,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `meta_key_index` (`meta_key` ASC)
         ) ";
	}
}
