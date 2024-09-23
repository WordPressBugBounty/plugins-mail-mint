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
 * [Manage form table schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class FormSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_forms';


	/**
	 * Get the schema of Form table
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `title` VARCHAR(255),
            `form_body` longtext,
            `form_position` longtext,
            `group_ids` longtext,
            `status` VARCHAR(255) NOT NULL,
            `template_id` bigint(20) unsigned NULL,
            `created_by` BIGINT(20),
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
    		INDEX `form_id_index` (`id` ASC),
    		INDEX `form_title_index` (`title` ASC)
        ) ";
	}
}
