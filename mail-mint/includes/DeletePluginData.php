<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /includes/
 */

namespace Mint\MRM\Includes;

/**
 * Responsible class to delete all plugin data on plugin uninstallation
 *
 * @package /includes/
 * @since 1.0.0
 */
class DeletePluginData {

	/**
	 * Initialize process
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function init() {
		$should_delete = get_option( '_mrm_general_plugin_data_delete', 'no' );
		if ( 'yes' === $should_delete ) {
			self::delete_all_db_tables();
			self::delete_all_option_values();
			self::delete_all_saved_templates();
		}
	}

	/**
	 * Performs table deletion of MRM Plugin
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_all_db_tables() {
		global $wpdb;

		$sql = 'DROP TABLE IF EXISTS %1s';

		$mailmint_tables = array(
			$wpdb->prefix . 'mint_campaign_email_builder',
			$wpdb->prefix . 'mint_campaign_emails',
			$wpdb->prefix . 'mint_campaign_emails_meta',
			$wpdb->prefix . 'mint_campaigns',
			$wpdb->prefix . 'mint_campaigns_meta',
			$wpdb->prefix . 'mint_contact_group_relationship',
			$wpdb->prefix . 'mint_contact_groups',
			$wpdb->prefix . 'mint_contact_meta',
			$wpdb->prefix . 'mint_contact_note',
			$wpdb->prefix . 'mint_contacts',
			$wpdb->prefix . 'mint_custom_fields',
			$wpdb->prefix . 'mint_form_meta',
			$wpdb->prefix . 'mint_forms',
			$wpdb->prefix . 'mint_broadcast_emails',
			$wpdb->prefix . 'mint_broadcast_email_meta',
			$wpdb->prefix . 'mint_automation_jobs',
			$wpdb->prefix . 'mint_automation_meta',
			$wpdb->prefix . 'mint_automation_step_meta',
			$wpdb->prefix . 'mint_automation_steps',
			$wpdb->prefix . 'mint_automations',
			$wpdb->prefix . 'mint_automation_log',
		);
		$mailmint_tables = implode( ', ', $mailmint_tables );

		$wpdb->query( $wpdb->prepare( $sql, $mailmint_tables ) ); //phpcs:ignore
	}

	/**
	 * Performs data deletion from wp_options table added by MRM plugin
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_all_option_values() {
		global $wpdb;

		$option_ids_sql      = "SELECT `option_id` FROM {$wpdb->options} WHERE `option_name` LIKE '%mrm%' OR `option_name` LIKE '%mailmint%' OR `option_name` LIKE '%mintmail%' OR `option_name` LIKE '%mail_mint%' OR `option_name` LIKE '%_mint_compliance%'";
		$mailmint_option_ids = $wpdb->get_results( $option_ids_sql, ARRAY_A ); //phpcs:ignore

		$mailmint_option_ids = array_column( $mailmint_option_ids, 'option_id' );
		$mailmint_option_ids = implode( ', ', $mailmint_option_ids );
		$option_sql          = "DELETE FROM {$wpdb->options} WHERE `option_id` IN ({$mailmint_option_ids})";

		$wpdb->query( $option_sql ); //phpcs:ignore
	}

	/**
	 * Performs data deletion from wp_posts and wp_post_meta table added by MRM plugin
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete_all_saved_templates() {
		$template_ids = get_posts(
			array(
				'fields'      => 'ids',
				'numberposts' => - 1,
				'post_status' => 'draft',
				'post_type'   => 'mint_email_template',
			)
		);

		foreach ( $template_ids as $template_id ) {
			wp_delete_post( $template_id );
		}
	}
}

DeletePluginData::init();
