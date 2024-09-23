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
 * [Manage interactions table schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class InteractionSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_interactions';


	/**
	 * Get the schema of Interaction table
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
            `hash` VARCHAR(60) NOT NULL,
            `mode` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 - Email 2 - SMS',
            `type` TINYINT(2) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 - Automation 2 - Campaign 3 - Note 4 - Email 5 - SMS',
            `open_rate` INT(3) UNSIGNED NOT NULL DEFAULT 0,
            `click_rate` INT(3) UNSIGNED NOT NULL DEFAULT 0,
            `step_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Step ID',
            `created_by` BIGINT(10) UNSIGNED NOT NULL DEFAULT 1,
            `first_open` TIMESTAMP DEFAULT NULL,
            `first_click` TIMESTAMP DEFAULT NULL,
            `status` TINYINT(2) UNSIGNED DEFAULT 1 COMMENT '1 - Draft 2 - Send 3 - Error 4 - Bounced',
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            PRIMARY KEY (`id`),
            KEY `contact_id` (`contact_id`)
         );";
	}
}
