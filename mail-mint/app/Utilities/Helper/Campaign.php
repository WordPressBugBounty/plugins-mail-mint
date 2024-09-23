<?php
/**
 * Campaign helper.
 *
 * @package Mint\MRM\Utilites\Helper
 * @namespace Mint\MRM\Utilites\Helper
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\Utilites\Helper;

use MailMint\App\Helper;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;

/**
 * Campaign class
 *
 * Campaign helper class.
 *
 * @package Mint\MRM\Utilites\Helper
 * @namespace Mint\MRM\Utilites\Helper
 *
 * @version 1.7.0
 */
class Campaign {

	/**
	 * Prepare a human-readable sentence for a recurring schedule based on provided properties.
	 *
	 * @param array $recurring_properties An array containing properties for the recurring schedule.
	 *
	 * @return string The prepared recurring schedule sentence.
	 * @since 1.7.0
	 */
	public static function prepare_recurring_schedule_sentence( $recurring_properties ) {
		$recurring_at = isset( $recurring_properties['schedule']['recurringAt'] ) ? $recurring_properties['schedule']['recurringAt'] : '';
		$recurring_on = isset( $recurring_properties['schedule']['recurringOn'] ) ? $recurring_properties['schedule']['recurringOn'] : array();
		$repeat       = isset( $recurring_properties['schedule']['recurringRepeat'] ) ? $recurring_properties['schedule']['recurringRepeat'] : array();
		$frequency    = isset( $recurring_properties['schedule']['recurringEvery'] ) ? $recurring_properties['schedule']['recurringEvery'] : null;
		$frequency    = ( $frequency > 1 ) ? "{$frequency}" : '';
		// Convert time format to AM/PM.
		$recurring_at = gmdate( 'h:i A', strtotime( $recurring_at ) );

		if ( 'daily' === $repeat ) {
			$daily = ( $frequency > 1 ) ? 'days' : 'day';
			/* translators: %1$s: Frequency, %2$s: Daily, %3$s: Recurring at */
			return sprintf( esc_html__( 'Every %1$s %2$s at %3$s', 'mrm' ), $frequency, $daily, $recurring_at );
		}

		if ( 'weekly' === $repeat ) {
			// Convert recurringOn array to a string.
			$days_of_week = implode( ', ', array_map( 'ucfirst', $recurring_on ) );
			$weekly       = ( $frequency > 1 ) ? 'weeks' : 'week';
			/* translators: %1$s: Frequency, %2$s: Weekly, %3$s: Days of week, %4$s: Recurring at */
			return sprintf( esc_html__( 'Every %1$s %2$s, on the %3$s at %4$s', 'mrm' ), $frequency, $weekly, $days_of_week, $recurring_at );
		}

		if ( 'monthly' === $repeat ) {
			// Sort the recurringOn array.
			usort(
				$recurring_on,
				function ( $a, $b ) {
					return $a - $b;
				}
			);
			// Format the recurringOn array into a more natural language.
			$recurring_on_formatted = array_map(
				function ( $day ) {
					return Helper::get_ordinal_suffix( $day );
				},
				$recurring_on
			);

			$monthly = ( $frequency > 1 ) ? 'months' : 'month';
			/* translators: %1$s: Frequency, %2$s: Monthly, %3$s: Recurring on, %4$s: Recurring at */
			return sprintf( esc_html__( 'Every %1$s %2$s, on the %3$s at %4$s', 'mrm' ), $frequency, $monthly, implode( ', ', $recurring_on_formatted ), $recurring_at );
		}
	}

	/**
	 * Track email link click performance.
	 *
	 * @param int    $email_id   Email ID.
	 * @param string $target_url Target URL.
	 *
	 * @return void
	 * @since 1.9.0
	 */
	public static function track_email_link_click_performance( $email_id, $target_url ) {
		global $wpdb;
		$email_table    = $wpdb->prefix . EmailSchema::$table_name;
		$campaign_email = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$campaign_email_id = $wpdb->get_var( $wpdb->prepare( "SELECT email_id FROM {$email_table} WHERE id = %d", $email_id ) ); //phpcs:ignore
		$campaign_id       = $wpdb->get_var( $wpdb->prepare( "SELECT campaign_id FROM {$campaign_email} WHERE id = %d", $campaign_email_id ) ); //phpcs:ignore

		if ( ! $campaign_id ) {
			return;
		}

		$click_performance = CampaignModel::get_campaign_meta_value( $campaign_id, 'click_performance' );
		$click_performance = maybe_unserialize( $click_performance );

		// Check if click_performance is an array, if not initialize it as an empty array.
		if ( !is_array( $click_performance ) ) {
			$click_performance = array();
		}

		// Check if the target_url exists in the click_performance array.
		if ( isset( $click_performance[ $target_url ] ) ) {
			// If it does, increment the count and update the last clicked time.
			$click_performance[ $target_url ]['count']++;
			$click_performance[ $target_url ]['last_clicked'] = current_time( 'mysql' );
		} else {
			// If it doesn't, add the target_url to the array with a count of 1 and the current time as the last clicked time.
			$click_performance[ $target_url ] = array(
				'count'        => 1,
				'last_clicked' => current_time( 'mysql' ),
			);
		}

		CampaignModel::insert_or_update_campaign_meta( $campaign_id, 'click_performance', maybe_serialize( $click_performance ) );
	}
}
