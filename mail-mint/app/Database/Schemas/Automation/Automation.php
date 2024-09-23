<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-12-06 08:53:17
 * @modify date 2022-12-06 08:53:17
 * @package /app/Database/Schemas
 */

namespace Mint\MRM\DataBase\Tables;

require_once MRM_DIR_PATH . 'app/Interfaces/Schema.php';

use Mint\MRM\Interfaces\Schema;

/**
 * [Manage Automation schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class AutomationSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_automations';


	/**
	 * Get the schema of Automation table
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `name` VARCHAR(192) NULL,
            `author` BIGINT(20),
            `trigger_name` VARCHAR(192) NULL,
            `status` VARCHAR(50) NOT NULL COMMENT 'DRAFT, ENABLED',
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
             INDEX `automation_id` (`id` ASC),
             INDEX `automation_trigger_name` (`trigger_name` ASC)
         ) ";
	}
}
