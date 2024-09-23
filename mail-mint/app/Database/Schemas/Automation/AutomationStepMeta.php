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
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
/**
 * [Manage Automation Step Meta schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class AutomationStepMetaSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_automation_step_meta';


	/**
	 * Get the schema of Automation step meta table
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table                 = $wpdb->prefix . self::$table_name;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `automation_step_id` BIGINT UNSIGNED NOT NULL,
            `meta_key` VARCHAR(50) DEFAULT NULL,    
            `meta_value` longtext,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
			INDEX `id` (`id` ASC),
			INDEX `automation_step_id` (`automation_step_id` ASC)
         ) ";
	}
}
