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
 * [Manage message table schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class EmailSchema implements Schema {


	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $table_name = 'mint_broadcast_emails';

	/**
	 * Get the schema of Messages table
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `campaign_id` BIGINT UNSIGNED NULL,
                `automation_id` BIGINT UNSIGNED NULL,
                `step_id` VARCHAR(192) NULL,
                `email_id` VARCHAR(192) NULL,
                `contact_id` BIGINT UNSIGNED NULL COMMENT 'Set NULL on contact delete',
                `email_type` ENUM('campaign', 'automation', 'regular'),
                `email_address` VARCHAR(192) NOT NULL,
                `email_hash` VARCHAR(192) NULL,
                `email_headers` TEXT NULL,
                `status` VARCHAR(50) NOT NULL COMMENT 'scheduled, sent, failed',
                `scheduled_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `campaign_id_index` (`campaign_id` ASC),
                INDEX `automation_id_index` (`automation_id` ASC),
                INDEX `email_id_index` (`email_id` ASC),
                INDEX `contact_id_index` (`contact_id` ASC)
            ) ";
	}
}
