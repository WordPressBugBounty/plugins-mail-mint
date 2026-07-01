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

use DOMDocument;
use MailMint\App\Helper;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\DataBase\Models\MessageModel;
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
	 * Prepare a compact "Broadcasts …" sentence for a recurring campaign list row.
	 *
	 * Differs from prepare_recurring_schedule_sentence() in wording and time format:
	 * it leads with "Broadcasts" and uses 24-hour time (e.g. "Broadcasts daily at 00:15"),
	 * which is what the recurring campaign list Description column renders.
	 *
	 * @param array $recurring_properties Recurring schedule properties (the stored `recurring_properties` meta).
	 *
	 * @return string The broadcast cadence sentence, or an empty string when no schedule is configured.
	 * @since 1.24.0
	 */
	public static function prepare_recurring_broadcast_sentence( $recurring_properties ) {
		$schedule = isset( $recurring_properties['schedule'] ) ? $recurring_properties['schedule'] : array();
		if ( empty( $schedule ) ) {
			return '';
		}

		$repeat       = isset( $schedule['recurringRepeat'] ) ? $schedule['recurringRepeat'] : '';
		$recurring_at = isset( $schedule['recurringAt'] ) ? $schedule['recurringAt'] : '';
		$recurring_on = isset( $schedule['recurringOn'] ) ? (array) $schedule['recurringOn'] : array();
		$every        = isset( $schedule['recurringEvery'] ) ? (int) $schedule['recurringEvery'] : 1;
		$time         = $recurring_at ? gmdate( 'H:i', strtotime( $recurring_at ) ) : '';

		if ( 'daily' === $repeat ) {
			if ( $every > 1 ) {
				/* translators: %1$d: number of days, %2$s: time of day */
				return sprintf( esc_html__( 'Broadcasts every %1$d days at %2$s', 'mrm' ), $every, $time );
			}
			/* translators: %s: time of day */
			return sprintf( esc_html__( 'Broadcasts daily at %s', 'mrm' ), $time );
		}

		if ( 'weekly' === $repeat ) {
			$days = implode( ', ', array_map( 'ucfirst', $recurring_on ) );
			if ( $every > 1 ) {
				/* translators: %1$d: number of weeks, %2$s: days of week, %3$s: time of day */
				return sprintf( esc_html__( 'Broadcasts every %1$d weeks on %2$s at %3$s', 'mrm' ), $every, $days, $time );
			}
			/* translators: %1$s: days of week, %2$s: time of day */
			return sprintf( esc_html__( 'Broadcasts weekly on %1$s at %2$s', 'mrm' ), $days, $time );
		}

		if ( 'monthly' === $repeat ) {
			usort(
				$recurring_on,
				function ( $a, $b ) {
					return $a - $b;
				}
			);
			$days = implode(
				', ',
				array_map(
					function ( $day ) {
						return Helper::get_ordinal_suffix( $day );
					},
					$recurring_on
				)
			);
			if ( $every > 1 ) {
				/* translators: %1$d: number of months, %2$s: days of month, %3$s: time of day */
				return sprintf( esc_html__( 'Broadcasts every %1$d months on the %2$s at %3$s', 'mrm' ), $every, $days, $time );
			}
			/* translators: %1$s: days of month, %2$s: time of day */
			return sprintf( esc_html__( 'Broadcasts monthly on the %1$s at %2$s', 'mrm' ), $days, $time );
		}

		return '';
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

		// Record the click against the individual broadcast row first so click
		// performance can be rebuilt per recurring run. This only needs the broadcast
		// row id + URL, so it runs regardless of whether the campaign can be resolved
		// below — independent of the campaign-wide blob path. $email_id is the
		// mint_broadcast_emails row id (the same id used for is_click meta).
		self::track_broadcast_row_clicked_url( $email_id, $target_url );

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

	/**
	 * Record a clicked URL against a single broadcast email row.
	 *
	 * Stores a serialized url => { count, last_clicked } map in one
	 * `clicked_urls` meta row per broadcast email (the UNIQUE(mint_email_id,
	 * meta_key) constraint allows only one row per key, so multiple URLs are folded
	 * into a single serialized value). This row-level record is what lets recurring
	 * per-run click performance be rebuilt by scoping to an occurrence's email_id.
	 *
	 * Only sends made after this tracking shipped populate the map; historical runs
	 * have no row-level URLs and fall back to an empty per-run breakdown.
	 *
	 * @param int    $broadcast_email_id The mint_broadcast_emails row id.
	 * @param string $target_url         The clicked URL.
	 * @return void
	 * @since 1.25.1
	 */
	private static function track_broadcast_row_clicked_url( $broadcast_email_id, $target_url ) {
		if ( empty( $broadcast_email_id ) || empty( $target_url ) ) {
			return;
		}

		$existing = MessageModel::get_email_meta_value_by_key( 'clicked_urls', $broadcast_email_id );
		$urls     = $existing ? maybe_unserialize( $existing ) : array();
		if ( ! is_array( $urls ) ) {
			$urls = array();
		}

		if ( isset( $urls[ $target_url ] ) ) {
			$urls[ $target_url ]['count']++;
			$urls[ $target_url ]['last_clicked'] = current_time( 'mysql' );
		} else {
			$urls[ $target_url ] = array(
				'count'        => 1,
				'last_clicked' => current_time( 'mysql' ),
			);
		}

		MessageModel::insert_or_update_email_meta( 'clicked_urls', maybe_serialize( $urls ), $broadcast_email_id );
	}

	/**
	 * Return sanitized UTM params for a campaign, or an empty array when UTM is disabled.
	 *
	 * Used by the redirect handler to append UTM params at click-time.
	 *
	 * @since 1.21.0
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return array Associative array of utm_* keys ready for add_query_arg(), empty when disabled.
	 */
	public static function get_utm_params( int $campaign_id ): array {
		$raw = CampaignModel::get_campaign_meta_value( $campaign_id, 'utm_params' );
		if ( empty( $raw ) ) {
			return array();
		}
		$utm = json_decode( $raw, true );
		if ( empty( $utm['status'] ) ) {
			return array();
		}
		$map = array(
			'utm_source'   => $utm['source'] ?? '',
			'utm_medium'   => $utm['medium'] ?? '',
			'utm_campaign' => $utm['campaign'] ?? '',
			'utm_term'     => $utm['term'] ?? '',
			'utm_content'  => $utm['content'] ?? '',
		);
		return array_filter( $map );
	}

	/**
	 * Extract URLs from the HTML content of an email body.
	 *
	 * This function parses the HTML content of an email body to extract all URLs from anchor tags.
	 *
	 * @param string $email_body The HTML content of the email body.
	 *
	 * @return array An array of URLs extracted from the email body.
	 * @since 1.16.5
	 */
	public static function extract_urls_from_html($email_body) {
		if ( ! is_string( $email_body ) || '' === trim( $email_body ) ) {
			return array();
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML($email_body);
		libxml_clear_errors();

		$urls = array();
		foreach ($dom->getElementsByTagName('a') as $tag) {
			if ($tag->hasAttribute('href')) {
				$href = trim($tag->getAttribute('href'));
				// Exclude empty, anchor-only hrefs, and placeholder URLs.
				if (!empty($href) && $href !== '#' && !preg_match('/\{\{.*\}\}/', $href)) {
					$urls[] = $href;
				}
			}
		}
		return $urls;
	}
}
