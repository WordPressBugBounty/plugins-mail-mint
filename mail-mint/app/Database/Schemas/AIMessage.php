<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/Database/Schemas
 */

namespace Mint\MRM\DataBase\Tables;

require_once MRM_DIR_PATH . 'app/Interfaces/Schema.php';

use Mint\MRM\Interfaces\Schema;

/**
 * AI copilot message schema.
 *
 * One row per normalized message (user / assistant / tool). `content` is the
 * normalized JSON payload; `meta` may carry provider-native raw blocks for
 * loop replay plus usage data.
 *
 * @package /app/Database/Schemas
 * @since 1.17.0
 */
class AIMessageSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.17.0
	 */
	public static $table_name = 'mint_ai_messages';

	/**
	 * Get the schema of the AI messages table
	 *
	 * @return string
	 * @since 1.17.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `conversation_id` BIGINT UNSIGNED NOT NULL,
            `role` VARCHAR(20) NOT NULL,
            `content` LONGTEXT NULL,
            `meta` LONGTEXT NULL,
            `created_at` TIMESTAMP NULL,
             INDEX `ai_message_conversation_index` (`conversation_id` ASC)
         ) ";
	}
}
