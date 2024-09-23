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
use Mint\MRM\DataBase\Tables\AutomationSchema;
/**
 * [Manage Automation step schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class AutomationStepSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_automation_steps';


	/**
	 * Get the schema of Automation Steps table
	 *
	 * @return string
	 * @since  1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table            = $wpdb->prefix . self::$table_name;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;
		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `automation_id` BIGINT UNSIGNED NOT NULL,
            `step_id` VARCHAR(190) NOT NULL UNIQUE,
            `key` VARCHAR(192) NOT NULL,
            `type` VARCHAR(50) NOT NULL COMMENT 'TRIGGER, ACTION, PENDING',
            `settings` longtext,
            `next_step_id` VARCHAR(192) NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
             INDEX `automation_step_id` (`step_id` ASC),
             INDEX `automation_step_type` (`type` ASC),
			 INDEX `automation_id` (`automation_id` ASC)
         ) ";
	}
}
