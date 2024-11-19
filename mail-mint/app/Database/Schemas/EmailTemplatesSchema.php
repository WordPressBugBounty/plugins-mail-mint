<?php

/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-11-13 11:03:17
 * @modify date 2024-11-13 11:03:17
 * @package /app/Database/Schemas
 */

namespace Mint\MRM\DataBase\Tables;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Manage email templates schema.
 *
 * @package /app/Database/Schemas
 * @since 1.15.2
 */
class EmailTemplatesSchema{
    /**
     * Table name.
     *
     * @var string
     * @since 1.15.2
     */
    public static $table_name = 'mint_email_templates';

    /**
     * Create tables on plugin activation.
     *
     * @return void
     * @since 1.15.2
     */
    public function get_sql(){
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Email templates table.
        $templates_table = $wpdb->prefix . self::$table_name;
        $this->create_email_templates_table($templates_table, $charset_collate);
    }


    /**
     * Create Email templates table.
     *
     * @param mixed $table Email templates table name.
     * @param mixed $charset_collate Collation and Character Set.
     *
     * @return void
     * @since 1.15.2
     */
    public function create_email_templates_table($table, $charset_collate){
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `title` VARCHAR(250) NOT NULL,
            `thumbnail` longtext NULL,
            `thumbnail_data` longtext NULL,
            `html_content` longtext NULL,
            `json_content` longtext NULL,
            `editor_type` VARCHAR(50) NOT NULL,
            `email_type` VARCHAR(50) NOT NULL,
            `customizable` TINYINT(1) NOT NULL DEFAULT 0,
            `author_id` BIGINT UNSIGNED NOT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
            `newsletter_type` VARCHAR(50) NULL,
            `newsletter_id` BIGINT UNSIGNED NULL,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            INDEX `template_id_index` (`id` ASC)
         ) $charset_collate;";
        dbDelta($sql);
    }
}
