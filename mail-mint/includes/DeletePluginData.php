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
			$wpdb->prefix . 'mint_form_submissions',
			$wpdb->prefix . 'mint_abandoned_carts',
			$wpdb->prefix . 'mint_abandoned_carts_meta',
			$wpdb->prefix . 'mint_lead_magnets',
			$wpdb->prefix . 'mint_lead_magnet_download_tracking',
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

		$option_ids = $wpdb->get_col( //phpcs:ignore
			$wpdb->prepare(
				"SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				'%mrm%',
				'%mailmint%',
				'%mintmail%',
				'%mail_mint%',
				'%_mint_compliance%',
				'%mint%'
			)
		);

		if ( ! empty( $option_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $option_ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_id IN ($placeholders)", $option_ids ) ); //phpcs:ignore
		}
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
