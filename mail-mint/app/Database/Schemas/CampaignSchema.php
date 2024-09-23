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

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * [Manage campaign schema]
 *
 * @desc Manage plugin's assets
 * @package /app/Database/Schemas
 * @since 1.0.0
 */
class CampaignSchema {

	/**
	 * Table name for mint_campaigns
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $campaign_table = 'mint_campaigns';

	/**
	 * Table name for mint_campaigns_meta
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $campaign_meta_table = 'mint_campaigns_meta';

	/**
	 * Table name for mint_campaign_emails
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $campaign_emails_table = 'mint_campaign_emails';

	/**
	 * Table name for mint_campaign_emails_meta
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $campaign_emails_meta_table = 'mint_campaign_emails_meta';

	/**
	 * Create tables on plugin activation
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function get_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// campaigns table.
		$campaign_table = $wpdb->prefix . self::$campaign_table;
		$this->create_campaign_table( $campaign_table, $charset_collate );

		// campaign meta table.
		$campaign_meta_table = $wpdb->prefix . self::$campaign_meta_table;
		$this->create_campaign_meta_table( $campaign_meta_table, $charset_collate );

		// campaign emails table.
		$campaign_emails_table = $wpdb->prefix . self::$campaign_emails_table;
		$this->create_campaign_emails_table( $campaign_emails_table, $charset_collate );

		// campaign emails meta table.
		$campaign_emails_meta_table = $wpdb->prefix . self::$campaign_emails_meta_table;
		$this->create_campaign_emails_meta_table( $campaign_emails_meta_table, $charset_collate );
	}


	/**
	 * Create Campaign table
	 *
	 * @param mixed $table Campaign table name.
	 * @param mixed $charset_collate Collation and Character Set.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create_campaign_table( $table, $charset_collate ) {
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `title` VARCHAR(192) NULL,
            `status`  varchar(50) NOT NULL,
            `type` varchar(50) NOT NULL,
            `scheduled_at` TIMESTAMP NULL,
            `created_by` bigint(20) unsigned NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `cid_index` (`id` ASC),
            INDEX `ctitle_index` (`title` ASC)
         ) $charset_collate;";

		dbDelta( $sql );
	}


	/**
	 * Create Campaign meta table
	 *
	 * @param mixed $table Campaign meta table name.
	 * @param mixed $charset_collate Collation and Character Set.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create_campaign_meta_table( $table, $charset_collate ) {
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `campaign_id` BIGINT(20) NOT NULL,
            `meta_key` VARCHAR(50) NOT NULL,
            `meta_value` longtext,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `campaign_id_index` (`campaign_id` ASC)
         ) $charset_collate;";
		dbDelta( $sql );
	}


	/**
	 * Create Campaign emails table
	 *
	 * @param mixed $table Campaign emails table name.
	 * @param mixed $charset_collate Collation and Character Set.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create_campaign_emails_table( $table, $charset_collate ) {
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `campaign_id` BIGINT(20) NOT NULL,
            `delay` INT(10) DEFAULT 0,
            `delay_count` INT(10) NULL,
            `delay_value` VARCHAR(192) NULL,
            `send_time` BIGINT(20) DEFAULT 0,   
            `sender_email` VARCHAR(192),
            `sender_name` VARCHAR(192), 
            `reply_email` VARCHAR(192),
            `reply_name` VARCHAR(192),
            `email_index` INT(10),
            `email_subject` VARCHAR(200),
            `email_preview_text` VARCHAR(200) NULL,
            `template_id` bigint(20) unsigned NULL,
			`status` VARCHAR(50) NOT NULL DEFAULT 'draft' COMMENT 'scheduling, scheduled, sent, draft',
            `scheduled_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `campaign_id_index` (`campaign_id` ASC)
         ) $charset_collate;";

		dbDelta( $sql );
	}


	/**
	 * Create Campaign emails meta table
	 *
	 * @param mixed $table Campaign emails meta table name.
	 * @param mixed $charset_collate Collation and Character Set.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create_campaign_emails_meta_table( $table, $charset_collate ) {
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `campaign_emails_id` BIGINT(20) NOT NULL,
            `meta_key` VARCHAR(50) NOT NULL,
            `meta_value` longtext,
            INDEX `campaign_emails_id_index` (`campaign_emails_id` ASC)
         ) $charset_collate;";
		dbDelta( $sql );
	}
}
