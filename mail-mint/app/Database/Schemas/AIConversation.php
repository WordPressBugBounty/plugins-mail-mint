<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/Database/Schemas
 */

namespace Mint\MRM\DataBase\Tables;

use Mint\MRM\Interfaces\Schema;

/**
 * AI copilot conversation schema.
 *
 * One row per chat session between a WP user and Mint AI. `pending` holds
 * tool calls awaiting user confirmation (JSON); `status` is the loop state.
 *
 * @package /app/Database/Schemas
 * @since 1.17.0
 */
class AIConversationSchema implements Schema {

	/**
	 * Table name
	 *
	 * @var string
	 * @since 1.17.0
	 */
	public static $table_name = 'mint_ai_conversations';

	/**
	 * Get the schema of the AI conversations table
	 *
	 * @return string
	 * @since 1.17.0
	 */
	public function get_sql() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NULL,
            `provider` VARCHAR(50) NOT NULL,
            `context_type` VARCHAR(50) NOT NULL DEFAULT 'dashboard',
            `context_id` BIGINT UNSIGNED NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'idle',
            `pending` LONGTEXT NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
             INDEX `ai_conversation_user_index` (`user_id` ASC),
             INDEX `ai_conversation_context_index` (`context_type` ASC, `context_id` ASC)
         ) ";
	}
}
