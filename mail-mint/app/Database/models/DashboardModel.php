<?php
/**
 * Manage contact dashboard related database operation.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use Mint\MRM\DataBase\Tables\AutomationMetaSchema;
use Mint\MRM\DataBase\Tables\AutomationSchema;
use Mint\MRM\DataBase\Tables\FormSchema;
use Mint\MRM\DataBase\Tables\FormMetaSchema;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\CampaignEmailBuilderSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;
use MRM\Common\MrmCommon;
use Mint\MRM\DataBase\Models\CampaignModel as ModelsCampaign;
use Mint\MRM\DataBase\Tables\EmailTemplatesSchema;
use MintMail\App\Internal\Automation\AutomationModel;
use MintMail\App\Internal\Automation\HelperFunctions;

/**
 * DashboardModel class
 *
 * Manage contact dashboard related database operation.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class DashboardModel {

	/**
	 * Get top card data for the dashboard based on a filter.
	 *
	 * Retrieves metrics for the five stat cards: contacts, unsubscribed, emails sent, open rate, click rate.
	 *
	 * @param string $filter     Filter key: last_7_days | last_30_days | last_90_days | custom.
	 * @param string $start_date Used only when $filter === 'custom'.
	 * @param string $end_date   Used only when $filter === 'custom'.
	 *
	 * @return array Keys: contacts, unsubscribed, emails_sent, open_rate, click_rate.
	 * 
	 * @since 1.18.0
	 */
	public static function get_top_cards_data( $filter = 'last_30_days', $start_date = '', $end_date = '' ) {
		global $wpdb;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;
		$email_stats   = self::fetch_email_stats_for_filter( $filter, $start_date, $end_date );

		return array(
			'contacts'     => self::fetch_data( $contact_table, $filter, $start_date, $end_date ),
			'unsubscribed' => self::fetch_unsubscribed_contacts_for_filter( $contact_table, $filter, $start_date, $end_date ),
			'emails_sent'  => $email_stats['sent'],
			'open_rate'    => $email_stats['open_rate'],
			'click_rate'   => $email_stats['click_rate'],
		);
	}

	/**
	 * Fetch unsubscribed contact counts for a given filter period.
	 *
	 * @param string $contact_table Full table name including prefix.
	 * @param string $filter        Filter key (e.g. 'last_30_days').
	 *
	 * @return array current, previous, change, trend.
	 *
	 * @since 1.19.0
	 */
	private static function fetch_unsubscribed_contacts_for_filter( $contact_table, $filter, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$contact_table = esc_sql( $contact_table );
		$conditions    = self::resolve_where_conditions( $filter, $start_date, $end_date );

		if ( empty( $conditions ) ) {
			return array( 'current' => 0, 'previous' => 0, 'change' => '0.00%', 'trend' => 'equal' );
		}

		$where_current  = $conditions['conditions_1'];
		$where_previous = $conditions['conditions_2'];

		$current_count  = (float) $wpdb->get_var( "SELECT COUNT(`id`) FROM `{$contact_table}` WHERE status = 'unsubscribed' AND {$where_current}" ); //phpcs:ignore
		$previous_count = (float) $wpdb->get_var( "SELECT COUNT(`id`) FROM `{$contact_table}` WHERE status = 'unsubscribed' AND {$where_previous}" ); //phpcs:ignore

		return self::format_email_metric( $current_count, $previous_count );
	}

	/**
	 * Fetch email sent count, open rate, and click rate for a given filter period.
	 *
	 * @param string $filter Filter key (e.g. 'last_30_days').
	 *
	 * @return array sent, open_rate, click_rate — each with current, previous, change, trend.
	 *
	 * @since 1.19.0
	 */
	private static function fetch_email_stats_for_filter( $filter, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$empty = array(
			'sent'       => array( 'current' => '0', 'previous' => '0', 'change' => '0.00%', 'trend' => 'equal', 'delivered_rate' => '0.00%' ),
			'open_rate'  => array( 'current' => '0.00%', 'previous' => '0.00%', 'change' => '0.00%', 'trend' => 'equal' ),
			'click_rate' => array( 'current' => '0.00%', 'previous' => '0.00%', 'change' => '0.00%', 'trend' => 'equal' ),
		);

		$conditions = self::resolve_where_conditions( $filter, $start_date, $end_date );
		if ( empty( $conditions ) ) {
			return $empty;
		}

		$where_current  = $conditions['conditions_1'];
		$where_previous = $conditions['conditions_2'];

		$emails_table = esc_sql( $wpdb->prefix . 'mint_broadcast_emails' );
		$meta_table   = esc_sql( $wpdb->prefix . 'mint_broadcast_email_meta' );

		$current_sent  = (float) $wpdb->get_var( "SELECT COUNT(*) FROM `{$emails_table}` WHERE {$where_current}" ); //phpcs:ignore
		$previous_sent = (float) $wpdb->get_var( "SELECT COUNT(*) FROM `{$emails_table}` WHERE {$where_previous}" ); //phpcs:ignore

		$current_delivered  = (float) $wpdb->get_var( "SELECT COUNT(*) FROM `{$emails_table}` WHERE status = 'sent' AND {$where_current}" ); //phpcs:ignore
		$previous_delivered = (float) $wpdb->get_var( "SELECT COUNT(*) FROM `{$emails_table}` WHERE status = 'sent' AND {$where_previous}" ); //phpcs:ignore

		$delivered_rate = $current_sent > 0 ? round( ( $current_delivered / $current_sent ) * 100, 1 ) : 0.0;

		$bm_current  = str_replace( array( 'created_at', 'updated_at' ), array( 'bm.created_at', 'bm.updated_at' ), $where_current );
		$bm_previous = str_replace( array( 'created_at', 'updated_at' ), array( 'bm.created_at', 'bm.updated_at' ), $where_previous );

		$current_open  = (float) $wpdb->get_var( "SELECT COUNT(DISTINCT bm.mint_email_id) FROM `{$meta_table}` bm WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1' AND {$bm_current}" ); //phpcs:ignore
		$previous_open = (float) $wpdb->get_var( "SELECT COUNT(DISTINCT bm.mint_email_id) FROM `{$meta_table}` bm WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1' AND {$bm_previous}" ); //phpcs:ignore

		$current_click  = (float) $wpdb->get_var( "SELECT COUNT(DISTINCT bm.mint_email_id) FROM `{$meta_table}` bm WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1' AND {$bm_current}" ); //phpcs:ignore
		$previous_click = (float) $wpdb->get_var( "SELECT COUNT(DISTINCT bm.mint_email_id) FROM `{$meta_table}` bm WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1' AND {$bm_previous}" ); //phpcs:ignore

		$sent_data = self::format_email_metric( $current_sent, $previous_sent );
		$sent_data['delivered_rate'] = $delivered_rate . '%';

		return array(
			'sent'       => $sent_data,
			'open_rate'  => self::format_email_metric( $current_open, $previous_open ),
			'click_rate' => self::format_email_metric( $current_click, $previous_click ),
		);
	}

	/**
	 * Calculate the percentage-point change between two rate values.
	 *
	 * @param float $current  Current rate (0–100).
	 * @param float $previous Previous rate (0–100).
	 *
	 * @return string Formatted change string, e.g. "+2.10%".
	 *
	 * @since 1.19.0
	 */
	private static function calc_rate_change( $current, $previous ) {
		$diff = $current - $previous;
		$sign = $diff >= 0 ? '+' : '';
		return $sign . number_format( $diff, 2 ) . '%';
	}

	/**
	 * Perform database query and fetch required data
	 *
	 * @param string $table_name Database table name.
	 * @param string $filter Filter name (MONTH, WEEK, YEAR).
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 *
	 * @return float[]
	 *
	 * @since 1.18.0
	 */
	private static function fetch_data( string $table_name, string $filter, string $start_date = '', string $end_date = '' ) {
		global $wpdb;

		$table_name = esc_sql( $table_name );
		$empty      = array( 'current' => 0, 'previous' => 0, 'change' => '0.00%', 'trend' => 'equal' );

		$conditions = self::resolve_where_conditions( $filter, $start_date, $end_date );
		if ( empty( $conditions ) ) {
			return $empty;
		}

		$where_current  = $conditions['conditions_1'];
		$where_previous = $conditions['conditions_2'];

		$current_data  = (float) $wpdb->get_var( "SELECT COUNT(`id`) FROM `{$table_name}` WHERE {$where_current}" );  //phpcs:ignore
		$previous_data = (float) $wpdb->get_var( "SELECT COUNT(`id`) FROM `{$table_name}` WHERE {$where_previous}" ); //phpcs:ignore

		$diff_rate      = $previous_data > 0 ? ( ( $current_data - $previous_data ) / $previous_data ) * 100 : ( $current_data > 0 ? 100.0 : 0.0 );
		$formatted_diff = number_format( $diff_rate, 2 ) . '%';

		$trend = 'equal';
		if ( $current_data > $previous_data ) {
			$trend = 'up';
		} elseif ( $current_data < $previous_data ) {
			$trend = 'down';
		}

		return array(
			'current'  => number_format( $current_data ),
			'previous' => number_format( $previous_data ),
			'change'   => $formatted_diff,
			'trend'    => $trend,
		);
	}


	/**
	 * Resolve WHERE conditions for any filter, including custom date ranges.
	 *
	 * Centralises the dispatch logic so every fetch method stays DRY.
	 * Returns an array with 'conditions_1' (current period) and 'conditions_2'
	 * (previous/comparison period), or an empty array when the filter is unknown.
	 *
	 * @param string $filter     Filter key: last_7_days | last_30_days | last_90_days | last_60_days | custom | all.
	 * @param string $start_date Used only when $filter === 'custom'.
	 * @param string $end_date   Used only when $filter === 'custom'.
	 *
	 * @return array
	 *
	 * @since 1.19.0
	 */
	private static function resolve_where_conditions( $filter, $start_date = '', $end_date = '' ) {
		if ( 'custom' === $filter ) {
			if ( empty( $start_date ) || empty( $end_date ) ) {
				return array();
			}
			return self::get_where_query_for_custom( $start_date, $end_date );
		}

		$callback = 'get_where_query_for_' . $filter;
		if ( ! method_exists( __CLASS__, $callback ) ) {
			return array();
		}

		$conditions = call_user_func( array( __CLASS__, $callback ) );
		if ( empty( $conditions['conditions_1'] ) || empty( $conditions['conditions_2'] ) ) {
			return array();
		}

		return $conditions;
	}

	/**
	 * Get where query for last 90 days filter.
	 *
	 * @return string[]
	 *
	 * @since 1.19.0
	 */
	private static function get_where_query_for_last_90_days() {
		return array(
			'conditions_1' => 'created_at >= CURDATE() - INTERVAL 89 DAY',
			'conditions_2' => 'created_at >= CURDATE() - INTERVAL 179 DAY AND created_at < CURDATE() - INTERVAL 89 DAY',
		);
	}

	/**
	 * Get where query for custom date range
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 *
	 * @return array
	 */
	private static function get_where_query_for_custom( $start_date, $end_date ) {
		global $wpdb;

		$start_date = date_format( date_create( $start_date ), 'Y-m-d H:i:s' );
		$end_date   = date_format( date_create( $end_date ), 'Y-m-d' ) . ' 23:59:59';
		$range      = strtotime( $end_date ) - strtotime( $start_date );
		$time_span  = round( $range / ( 60 * 60 * 24 ) );

		$prev_start_date = date_create( $start_date );
		date_sub( $prev_start_date, date_interval_create_from_date_string( $time_span . ' days' ) );
		$prev_start_date = date_format( $prev_start_date, 'Y-m-d' );

		$prev_end_date = date_create( $end_date );
		date_sub( $prev_end_date, date_interval_create_from_date_string( $time_span . ' days' ) );
		$prev_end_date = date_format( $prev_end_date, 'Y-m-d' );

		$conditions1 = $wpdb->prepare( '((created_at BETWEEN %s AND %s) OR (updated_at BETWEEN %s AND %s))', $start_date, $end_date, $start_date, $end_date ); //phpcs:ignore
		$conditions2 = $wpdb->prepare( '((created_at BETWEEN %s AND %s) OR (updated_at BETWEEN %s AND %s))', $prev_start_date, $prev_end_date, $prev_start_date, $prev_end_date ); //phpcs:ignore

		return array(
			'conditions_1' => $conditions1,
			'conditions_2' => $conditions2,
		);
	}

	/**
	 * Get where query for weekly filter
	 *
	 * @return string[]
	 *
	 * @since 1.0.0
	 */
	private static function get_where_query_for_last_7_days() {
		return array(
			// Current 7 days (including today)
			'conditions_1' => "created_at >= CURDATE() - INTERVAL 6 DAY",

			// Previous 7 days
			'conditions_2' => "created_at >= CURDATE() - INTERVAL 13 DAY AND created_at < CURDATE() - INTERVAL 6 DAY"
		);
	}

	/**
	 * Get where query for all filter
	 *
	 * @return string[]
	 *
	 * @since 1.0.0
	 */
	private static function get_where_query_for_all() {
		return array(
			'conditions_1' => '1=1',
			'conditions_2' => '1=1',
		);
	}

	/**
	 * Get where query for monthly filter
	 *
	 * @return string[]
	 *
	 * @since 1.0.0
	 */
	private static function get_where_query_for_last_30_days() {
		return array(
			// Current 30 days (including today)
			'conditions_1' => "created_at >= CURDATE() - INTERVAL 29 DAY",

			// Previous 30 days
			'conditions_2' => "created_at >= CURDATE() - INTERVAL 59 DAY AND created_at < CURDATE() - INTERVAL 29 DAY"
		);
	}

	/**
	 * Get where query for yearly filter
	 *
	 * @return string[]
	 *
	 * @since 1.0.0
	 */
	private static function get_where_query_for_last_60_days() {
		return array(
			// Current 60 days (including today)
			'conditions_1' => "created_at >= CURDATE() - INTERVAL 59 DAY",

			// Previous 60 days
			'conditions_2' => "created_at >= CURDATE() - INTERVAL 119 DAY AND created_at < CURDATE() - INTERVAL 59 DAY"
		);
	}

	/**
	 * Return campaign and automation based revenue reports
	 *
	 * @param mixed $filter Variable to filter revenue data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_revenue_reports( $filter ) {
		$cam_labels = array();
		$cam_values = array();
		$aut_labels = array();
		$aut_values = array();

		$order_ids = EmailModel::get_all_order_ids_from_email( $filter, 'campaign', 'automation' );

		$campaign_revenue = EmailModel::get_order_total_from_email( $filter, 'campaign' );
		if ( ! empty( $campaign_revenue ) ) {
			$cam_labels = array_keys( $campaign_revenue );
			$cam_values = array_values( $campaign_revenue );
		}

		$automation_revenue = EmailModel::get_order_total_from_email( $filter, 'automation' );
		if ( ! empty( $automation_revenue ) ) {
			$aut_labels = array_keys( $automation_revenue );
			$aut_values = array_values( $automation_revenue );
		}

		$total_revenue = 0;
		if ( !empty( $order_ids ) ) {
			$total_revenue = EmailModel::get_total_revenue_from_email( $order_ids );
		}

		$total_revenue = MrmCommon::price_format_with_WC_currency( $total_revenue );

		$campaign_max   = ! empty( $cam_values ) ? max( $cam_values ) : 0;
		$automation_max = ! empty( $aut_values ) ? max( $aut_values ) : 0;

		$max = max( array( $campaign_max, $automation_max ) );

		return array(
			'campaign_revenue'   => array(
				'labels' => $cam_labels,
				'values' => $cam_values,
			),
			'automation_revenue' => array(
				'labels' => $aut_labels,
				'values' => $aut_values,
			),
			'max_today'          => $max,
			'total_revenue'      => html_entity_decode( $total_revenue ), //phpcs:ignore
		);
	}

	/**
	 * Get last five campaign analytics data (archived and running, optionally including drafts)
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_recent_campaign_performance() {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		$sql     = 'SELECT `id`, `title`, `status`, `updated_at`, `type` FROM %1s WHERE `status` IN (%s, %s, %s) ORDER BY updated_at DESC LIMIT 0, 5';
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $campaigns_table, 'active', 'schedule', 'archived' ), ARRAY_A ); //phpcs:ignore

		if ( empty( $results ) ) {
			$draft_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(`id`) FROM %1s WHERE `status` = %s', $campaigns_table, 'draft' ) ); //phpcs:ignore
			return array(
				'items'       => array(),
				'draft_count' => $draft_count,
			);
		}

		// Prepare campaigns data.
		$campaigns = array();
		foreach ( $results as $campaign ) {
			$campaign_id      = isset( $campaign['id'] ) ? (int) $campaign['id'] : 0;
			$title            = isset( $campaign['title'] ) ? $campaign['title'] : '';
			$status           = isset( $campaign['status'] ) ? $campaign['status'] : '';
			$total_bounced    = EmailModel::count_delivered_status_on_campaign( $campaign_id, 'failed' );
			$total_recipients = ModelsCampaign::get_campaign_meta_value( $campaign_id, 'total_recipients' );
			$campaigns[]      = array(
				'id'               => $campaign_id,
				'title'            => $title,
				'status'           => $status,
				'type'             => isset( $campaign['type'] ) ? $campaign['type'] : '',
				'total_recipients' => $total_recipients,
				'open_rate'        => ModelsCampaign::prepare_campaign_open_rate( $campaign_id, $total_recipients, $total_bounced ) . '%',
				'click_rate'       => ModelsCampaign::prepare_campaign_click_rate( $campaign_id, $total_recipients, $total_bounced ) . '%',
				'unsubscribe'      => EmailModel::count_unsubscribe_on_campaign( $campaign_id ),
			);
		}
		return array(
			'items'       => $campaigns,
			'draft_count' => 0,
		);
	}

	/**
	 * Get performance data for the 5 most recent active automations.
	 *
	 * Retrieves basic metrics for each automation, including how long ago it was created,
	 * how many contacts entered it, how many completed it, and how many are still processing.
	 *
	 * @return array List of recent automation data with performance metrics.
	 * 
	 * @since 1.18.0
	 */
	public static function get_recent_automation_performance() {
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;

		$results = AutomationModel::get_all('id', 'desc', 0, 5, '', 'active');
		if ( ! isset( $results['data'] ) || empty( $results['data'] ) ) {
			$draft_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(`id`) FROM %1s WHERE `status` = %s', $automation_table, 'draft' ) ); //phpcs:ignore
			return array(
				'items'       => array(),
				'draft_count' => $draft_count,
			);
		}

		$automations = array_map(
			function( $automation ) {
				if ( is_array( $automation ) && !empty( $automation ) ) {
					$created_at    = isset( $automation['created_at'] ) ? $automation['created_at'] : '';
					$automation_id = isset( $automation['id'] ) ? $automation['id'] : '';

					$automation['created_ago'] = human_time_diff( strtotime( $created_at ), current_time( 'timestamp' ) );
					$automation['entered']     = HelperFunctions::count_total_enterance( $automation_id );
					$automation['completed']   = HelperFunctions::count_completed_automation( $automation_id );
					$automation['processing']  = $automation['entered'] - $automation['completed'];
				}
				return $automation;
			},
			$results['data']
		);

		return array(
			'items'       => $automations,
			'draft_count' => 0,
		);
	}

	/**
	 * Get recent published forms with submission counts.
	 * Falls back to a draft count when no published forms exist.
	 *
	 * @return array { items: array, draft_count: int }
	 * @since 1.18.0
	 */
	public static function get_recent_form_performance() {
		global $wpdb;
		$form_table      = $wpdb->prefix . FormSchema::$table_name;
		$form_meta_table = $wpdb->prefix . FormMetaSchema::$table_name;

		$results = $wpdb->get_results( //phpcs:ignore
			$wpdb->prepare(
				"SELECT f.id, f.title, f.status, IFNULL(m.meta_value, 0) AS entries FROM %1s AS f LEFT JOIN %1s AS m ON f.id = m.form_id AND m.meta_key = %s WHERE f.status = %s ORDER BY f.id DESC LIMIT 5",
				$form_table,
				$form_meta_table,
				'entries',
				'published'
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			$draft_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(`id`) FROM %1s WHERE `status` = %s', $form_table, 'draft' ) ); //phpcs:ignore
			return array(
				'items'       => array(),
				'draft_count' => $draft_count,
			);
		}

		return array(
			'items'       => $results,
			'draft_count' => 0,
		);
	}

	/**
	 * Get onboarding checklist stats for the dashboard.
	 *
	 * Calculates how many onboarding steps are completed and returns the full checklist with progress info.
	 *
	 * @return array Contains total steps, completed count, checklist steps, percentage, and show flag.
	 * 
	 * @since 1.18.0
	 */
	public static function get_onboarding_stats() {

		global $wpdb;
		$email_builder_table = $wpdb->prefix . EmailTemplatesSchema::$table_name;

		$boarding_steps = [
			[
				'label'     => __('Add your contacts', 'mrm'),
				'completed' => ContactModel::get_contacts_count() > 0,
				'link'      => 'contacts',
			],
			[
				'label'     => __('Create your first email', 'mrm'),
				'completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$email_builder_table}" ) > 0,
				'link'      => 'campaigns/regular/create',
			],
			[
				'label'     => __('Send your first campaign', 'mrm'),
				'completed' => CampaignModel::get_sent_campaign_count() > 0,
				'link'      => 'campaigns/regular',
			],
			[
				'label'     => __('Create a form', 'mrm'),
				'completed' => FormModel::get_form_count() > 0,
				'link'      => 'forms',
			],
			[
				'label'     => __('Set up automation', 'mrm'),
				'completed' => AutomationModel::get_automation_count() > 0,
				'link'      => 'automations',
			],
		];		

		$completed = 0;
		$total     = count($boarding_steps);

		foreach ($boarding_steps as $step) {
			if ($step['completed']) {
				$completed++;
			}
		}

		$percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

		return [
			'total'      => $total,
			'completed'  => $completed,
			'steps'      => $boarding_steps,
			'percentage' => $percentage,
			'show'       => self::should_show_checklist(),      
		];
	}

	/**
	 * Prepare dashboard metrics based on the selected metric type.
	 *
	 * Returns email or revenue data for the given date range.
	 *
	 * @param string $metric     The type of metric to fetch ('emails' or 'revenue').
	 * @param string $start_date Optional. Start date for the data range.
	 * @param string $end_date   Optional. End date for the data range.
	 *
	 * @return array Requested metric data or an empty array if metric type is invalid.
	 * 
	 * @since 1.18.0
	 */
	public static function prepare_dashboard_metrics( $metric, $start_date = '', $end_date = '' ) {
		if ( 'emails' === $metric ) {
			return self::get_emails_data( $start_date, $end_date );
		} elseif ( 'revenue' === $metric ) {
			return self::get_revenue_data( $start_date, $end_date );
		} else {
			return [];
		}
	}

	/**
	 * Get email performance metrics within a date range or all-time.
	 *
	 * Returns counts for sent, opened, and unsubscribed emails along with trend info.
	 * If no date range is given, it returns all-time totals without trends.
	 *
	 * @param string $start_date Optional. Start date for the report (Y-m-d).
	 * @param string $end_date   Optional. End date for the report (Y-m-d).
	 *
	 * @return array Email stats including sent, open, and unsubscribe data.
	 * 
	 * @since 1.18.0
	 */
	private static function get_emails_data($start_date = '', $end_date = '')
	{
		global $wpdb;

		$emails_table = $wpdb->prefix . 'mint_broadcast_emails';
		$meta_table   = $wpdb->prefix . 'mint_broadcast_email_meta';

		// If no dates → fetch all-time totals (no trend calculation)
		if (empty($start_date) && empty($end_date)) {
			// Total sent emails (all time)
			$total_sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$emails_table} WHERE status = 'sent'");

			// Total opened emails (all time)
			$total_open = (int) $wpdb->get_var("
				SELECT COUNT(DISTINCT bm.mint_email_id)
				FROM {$meta_table} bm
				INNER JOIN {$emails_table} be ON be.id = bm.mint_email_id
				WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'
			");

			// Total unsubscribed emails (adjust if needed based on your data)
			$total_unsub = (int) $wpdb->get_var("
				SELECT COUNT(DISTINCT bm.mint_email_id)
				FROM {$meta_table} bm
				INNER JOIN {$emails_table} be ON be.id = bm.mint_email_id
				WHERE bm.meta_key = 'is_unsubscribe' AND bm.meta_value = '1'
			");

			return array(
				'sent'        => array(
					'current'  => number_format($total_sent),
					'previous' => 0,
					'change'   => 0.0,
					'trend'    => 'equal',
				),
				'open'        => array(
					'current'  => number_format($total_open),
					'previous' => 0,
					'change'   => 0.0,
					'trend'    => 'equal',
				),
				'unsubscribe' => array(
					'current'  => number_format($total_unsub),
					'previous' => 0,
					'change'   => 0.0,
					'trend'    => 'equal',
				),
			);
		}

		// Date filter is provided → Calculate with trend and percentage
		$start_date      = esc_sql($start_date);
		$end_date        = esc_sql($end_date);
		$interval_days   = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);

		$previous_start  = date('Y-m-d', strtotime($start_date . " -{$interval_days} days"));
		$previous_end    = date('Y-m-d', strtotime($start_date . " -1 day"));

		// Sent Emails Count
		$current_sent  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$emails_table} WHERE status = 'sent' AND created_at BETWEEN '{$start_date}' AND '{$end_date}'");
		$previous_sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$emails_table} WHERE status = 'sent' AND created_at BETWEEN '{$previous_start}' AND '{$previous_end}'");

		// Open Emails Count
		$current_open  = (int) $wpdb->get_var("
			SELECT COUNT(DISTINCT bm.mint_email_id)
			FROM {$meta_table} bm
			INNER JOIN {$emails_table} be ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'
			AND be.created_at BETWEEN '{$start_date}' AND '{$end_date}'
		");

		$previous_open = (int) $wpdb->get_var("
			SELECT COUNT(DISTINCT bm.mint_email_id)
			FROM {$meta_table} bm
			INNER JOIN {$emails_table} be ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'
			AND be.created_at BETWEEN '{$previous_start}' AND '{$previous_end}'
		");

		// Unsubscribe Count → If you have this meta key, otherwise skip/remove this
		$current_unsub  = (int) $wpdb->get_var("
			SELECT COUNT(DISTINCT bm.mint_email_id)
			FROM {$meta_table} bm
			INNER JOIN {$emails_table} be ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_unsubscribe' AND bm.meta_value = '1'
			AND be.created_at BETWEEN '{$start_date}' AND '{$end_date}'
		");

		$previous_unsub = (int) $wpdb->get_var("
			SELECT COUNT(DISTINCT bm.mint_email_id)
			FROM {$meta_table} bm
			INNER JOIN {$emails_table} be ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_unsubscribe' AND bm.meta_value = '1'
			AND be.created_at BETWEEN '{$previous_start}' AND '{$previous_end}'
		");

		return array(
			'sent'        => self::format_email_metric($current_sent, $previous_sent),
			'open'        => self::format_email_metric($current_open, $previous_open),
			'unsubscribe' => self::format_email_metric($current_unsub, $previous_unsub),
		);
	}

	/**
	 * Format email metric data with change percentage and trend.
	 *
	 * Calculates the percentage change and trend (up, down, or equal) between current and previous values.
	 *
	 * @param float|int $current  Current period value.
	 * @param float|int $previous Previous period value.
	 *
	 * @return array Formatted metric with current, previous, change percentage, and trend direction.
	 * 
	 *  @since 1.18.0
	 */
	private static function format_email_metric($current, $previous)
	{
		$current = (float) $current;
		$previous = (float) $previous;

		if ($previous == 0) {
			$change = $current > 0 ? 100.0 : 0.0;
		} else {
			$change = (($current - $previous) / $previous) * 100;
		}

		$trend = 'equal';
		if ($current > $previous) {
			$trend = 'up';
		} elseif ($current < $previous) {
			$trend = 'down';
		}

		return array(
			'current'  => number_format($current),
			'previous' => number_format($previous),
			'change'   => number_format($change, 2) . '%',
			'trend'    => $trend,
		);
	}

	/**
	 * Get revenue metrics within a date range or all-time.
	 *
	 * Returns total orders, revenue, and average order value (AOV), with trend and percentage change.
	 * Automatically adjusts query based on HPOS or legacy WooCommerce storage.
	 *
	 * @param string $start_date Optional. Start date for the report (Y-m-d).
	 * @param string $end_date   Optional. End date for the report (Y-m-d).
	 *
	 * @return array Revenue metrics including orders, revenue, and AOV.
	 * 
	 * @since 1.18.0
	 */
	private static function get_revenue_data($start_date = '', $end_date = ''){
		global $wpdb;

		$is_hpos = MrmCommon::is_hpos_enable();

		$emails_meta_table   = $wpdb->prefix . 'mint_broadcast_email_meta';
		$orders_table        = $is_hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->prefix . 'posts';
		$order_id_column     = $is_hpos ? 'id' : 'ID';
		$order_type_column   = $is_hpos ? 'type' : 'post_type';
		$order_status_column = $is_hpos ? 'status' : 'post_status';
		$meta_table          = $is_hpos ? '' : $wpdb->prefix . 'postmeta';
		$meta_key_clause     = $is_hpos ? '' : "AND pm.meta_key = '_order_total'";

		// All time data if no dates provided
		if (empty($start_date) && empty($end_date)) {
			$order_ids = $wpdb->get_col("
			SELECT DISTINCT bm.meta_value
			FROM {$emails_meta_table} bm
			INNER JOIN {$orders_table} o ON o.{$order_id_column} = bm.meta_value
			WHERE bm.meta_key = 'order_id'
			AND o.{$order_type_column} = 'shop_order'
			AND o.{$order_status_column} IN ('wc-completed', 'wc-processing', 'wc-wpfnl-main-order')");

			if (empty($order_ids)) {
				return self::empty_orders_response();
			}

			$order_ids_in = implode(',', array_map('intval', $order_ids));

			$total_orders = count($order_ids);

			if ($is_hpos) {
				$total_revenue = (float) $wpdb->get_var("
				SELECT SUM(total_amount)
				FROM {$orders_table}
				WHERE {$order_id_column} IN ({$order_ids_in})");
			} else {
				$total_revenue = (float) $wpdb->get_var("
				SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
				FROM {$meta_table} pm
				WHERE pm.post_id IN ({$order_ids_in})
				{$meta_key_clause}");
			}

			$aov = $total_orders > 0 ? $total_revenue / $total_orders : 0;

			return array(
				'orders'  => self::format_email_metric($total_orders, 0),
				'revenue' => self::format_email_metric($total_revenue, 0),
				'aov'     => self::format_email_metric($aov, 0),
			);
		}

		// Dates provided → calculate current and previous
		$start_date      = esc_sql($start_date);
		$end_date        = esc_sql($end_date);
		$interval_days   = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
		$previous_start  = date('Y-m-d', strtotime($start_date . " -{$interval_days} days"));
		$previous_end    = date('Y-m-d', strtotime($start_date . " -1 day"));

		$current_data  = self::fetch_orders_metric($start_date, $end_date, $is_hpos);
		$previous_data = self::fetch_orders_metric($previous_start, $previous_end, $is_hpos);

		return array(
			'orders'  => self::format_email_metric($current_data['count'], $previous_data['count']),
			'revenue' => self::format_email_metric($current_data['revenue'], $previous_data['revenue']),
			'aov'     => self::format_email_metric($current_data['aov'], $previous_data['aov']),
		);
	}

	/**
	 * Fetch order metrics for a date range.
	 *
	 * @param string $start  Start date (Y-m-d).
	 * @param string $end    End date (Y-m-d).
	 * @param bool   $is_hpos Whether WooCommerce HPOS is enabled.
	 *
	 * @return array Returns order count, total revenue, and average order value.
	 * 
	 * @since 1.18.0
	 */
	private static function fetch_orders_metric($start, $end, $is_hpos)
	{
		global $wpdb;

		$emails_meta_table   = $wpdb->prefix . 'mint_broadcast_email_meta';
		$orders_table        = $is_hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->prefix . 'posts';
		$order_id_column     = $is_hpos ? 'id' : 'ID';
		$order_type_column   = $is_hpos ? 'type' : 'post_type';
		$order_date_column   = $is_hpos ? 'date_created_gmt' : 'post_date';
		$order_status_column = $is_hpos ? 'status' : 'post_status';
		$meta_table          = $is_hpos ? '' : $wpdb->prefix . 'postmeta';
		$meta_key_clause     = $is_hpos ? '' : "AND pm.meta_key = '_order_total'";
		$start_datetime      = $start . ' 00:00:00';
		$end_datetime        = $end . ' 23:59:59';

		$order_ids = $wpdb->get_col($wpdb->prepare("
			SELECT DISTINCT bm.meta_value
			FROM {$emails_meta_table} bm
			INNER JOIN {$orders_table} o ON o.{$order_id_column} = bm.meta_value
			WHERE bm.meta_key = 'order_id'
			AND o.{$order_type_column} = 'shop_order'
			AND o.{$order_status_column} IN ('wc-completed', 'wc-processing', 'wc-wpfnl-main-order')
			AND o.{$order_date_column} BETWEEN %s AND %s
		", $start_datetime, $end_datetime));


		if (empty($order_ids)) {
			return array('count' => 0, 'revenue' => 0.0, 'aov' => 0.0);
		}

		$order_ids_in = implode(',', array_map('intval', $order_ids));

		$total_orders = count($order_ids);

		if ($is_hpos) {
			$total_revenue = (float) $wpdb->get_var("
			SELECT SUM(total_amount)
			FROM {$orders_table}
			WHERE {$order_id_column} IN ({$order_ids_in})
		");
		} else {
			$total_revenue = (float) $wpdb->get_var("
			SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2)))
			FROM {$meta_table} pm
			WHERE pm.post_id IN ({$order_ids_in})
			  {$meta_key_clause}
		");
		}

		$aov = $total_orders > 0 ? $total_revenue / $total_orders : 0;

		return array(
			'count'   => $total_orders,
			'revenue' => $total_revenue,
			'aov'     => $aov,
		);
	}

	/**
	 * Return a default empty response for order metrics.
	 *
	 * @return array Metrics for orders, revenue, and AOV all set to zero.
	 *
	 * @since 1.18.0
	 */
	private static function empty_orders_response()
	{
		return array(
			'orders'  => self::format_email_metric(0, 0),
			'revenue' => self::format_email_metric(0, 0),
			'aov'     => self::format_email_metric(0, 0),
		);
	}

	/**
	 * Resolve a date window from a filter key.
	 *
	 * Returns an array with 'start' and 'end' as Y-m-d strings.
	 * Falls back to the last 30 days when the filter is unrecognised.
	 *
	 * @param string $filter     One of: last_7_days, last_30_days, last_60_days, all, custom.
	 * @param string $start_date Used only when $filter === 'custom'.
	 * @param string $end_date   Used only when $filter === 'custom'.
	 *
	 * @return array{ start: string, end: string }
	 *
	 * @since 1.19.0
	 */
	private static function resolve_date_window( $filter, $start_date = '', $end_date = '' ) {
		$today = date( 'Y-m-d' );

		switch ( $filter ) {
			case 'last_7_days':
				return array(
					'start' => date( 'Y-m-d', strtotime( '-6 days' ) ),
					'end'   => $today,
				);
			case 'last_90_days':
				return array(
					'start' => date( 'Y-m-d', strtotime( '-89 days' ) ),
					'end'   => $today,
				);
			case 'last_60_days':
				return array(
					'start' => date( 'Y-m-d', strtotime( '-59 days' ) ),
					'end'   => $today,
				);
			case 'custom':
				if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
					return array(
						'start' => date( 'Y-m-d', strtotime( $start_date ) ),
						'end'   => date( 'Y-m-d', strtotime( $end_date ) ),
					);
				}
				// fall through to default
			case 'last_30_days':
			default:
				return array(
					'start' => date( 'Y-m-d', strtotime( '-29 days' ) ),
					'end'   => $today,
				);
		}
	}

	/**
	 * Build an ordered list of date labels (e.g. "Mar 1") for a date window.
	 *
	 * @param string $start Y-m-d start date.
	 * @param string $end   Y-m-d end date.
	 *
	 * @return string[] Array of label strings.
	 *
	 * @since 1.19.0
	 */
	private static function build_date_labels( $start, $end ) {
		$labels   = array();
		$current  = strtotime( $start );
		$finish   = strtotime( $end );

		while ( $current <= $finish ) {
			$labels[] = date( 'M j', $current );
			$current  = strtotime( '+1 day', $current );
		}

		return $labels;
	}

	/**
	 * Build week-start labels (e.g. "Mar 1") for a date window.
	 * Each entry represents the Monday (or $start if mid-week) that begins each 7-day bucket.
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return string[]
	 */
	private static function build_week_labels( $start, $end ) {
		$labels  = array();
		$current = strtotime( $start );
		$finish  = strtotime( $end );

		while ( $current <= $finish ) {
			$labels[] = date( 'M j', $current );
			$current  = strtotime( '+7 days', $current );
		}

		return $labels;
	}

	/**
	 * Get the Email Performance Section (EPS) data for the dashboard.
	 *
	 * Returns deliverability metrics + daily chart series and (when WooCommerce is
	 * active) WooCommerce revenue/order metrics + daily chart series.
	 *
	 * @param string $filter     Filter key: last_7_days | last_30_days | last_60_days | custom | all.
	 * @param string $start_date Used only when $filter === 'custom'.
	 * @param string $end_date   Used only when $filter === 'custom'.
	 *
	 * @return array
	 *
	 * @since 1.19.0
	 */
	public static function get_eps_data( $filter = 'last_30_days', $start_date = '', $end_date = '', $granularity = 'daily' ) {
		global $wpdb;

		$window = self::resolve_date_window( $filter, $start_date, $end_date );
		$start  = $window['start'];
		$end    = $window['end'];

		$use_weekly = ( 'weekly' === $granularity );
		$labels     = $use_weekly
			? self::build_week_labels( $start, $end )
			: self::build_date_labels( $start, $end );

		$deliverability = self::get_eps_deliverability( $start, $end, $labels, $use_weekly );

		/**
		 * Filter to provide WooCommerce EPS (Email Performance Section) data.
		 *
		 * Pro plugin hooks into this filter to supply WooCommerce revenue and order metrics
		 * attributed to email sends. Returns null when pro is not active or WC is not available.
		 *
		 * @param null|array $woocommerce  WooCommerce metrics array, or null if unavailable.
		 * @param string     $start        Start date (Y-m-d).
		 * @param string     $end          End date (Y-m-d).
		 * @param string[]   $labels       Chart date labels.
		 * @param bool       $use_weekly   Whether to aggregate data in weekly buckets.
		 *
		 * @since 1.19.0
		 */
		$woocommerce = apply_filters( 'mint_eps_woocommerce_data', null, $start, $end, $labels, $use_weekly );

		return array(
			'deliverability' => $deliverability,
			'woocommerce'    => $woocommerce,
		);
	}

	/**
	 * Build deliverability summary stats and per-day chart series.
	 *
	 * @param string   $start  Y-m-d.
	 * @param string   $end    Y-m-d.
	 * @param string[] $labels Ordered date labels (same length as chart arrays).
	 *
	 * @return array
	 *
	 * @since 1.19.0
	 */
	private static function get_eps_deliverability( $start, $end, $labels, $use_weekly = false ) {
		global $wpdb;

		$emails_table = esc_sql( $wpdb->prefix . 'mint_broadcast_emails' );
		$meta_table   = esc_sql( $wpdb->prefix . 'mint_broadcast_email_meta' );

		$start_dt = $start . ' 00:00:00';
		$end_dt   = $end   . ' 23:59:59';

		// ── Summary stats ────────────────────────────────────────────────────
		$total_sent = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$emails_table}` WHERE created_at BETWEEN %s AND %s",
			$start_dt, $end_dt
		) );

		$total_delivered = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$emails_table}` WHERE status = 'sent' AND created_at BETWEEN %s AND %s",
			$start_dt, $end_dt
		) );

		// Count distinct campaigns/sends in range (use campaign_id grouping).
		$sends_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT campaign_id) FROM `{$emails_table}` WHERE created_at BETWEEN %s AND %s",
			$start_dt, $end_dt
		) );

		$total_bounced = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$emails_table}` WHERE status = 'failed' AND created_at BETWEEN %s AND %s",
			$start_dt, $end_dt
		) );

		$total_opened = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT bm.mint_email_id)
			 FROM `{$meta_table}` bm
			 WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'
			   AND bm.created_at BETWEEN %s AND %s",
			$start_dt, $end_dt
		) );

		$total_spam = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT bm.mint_email_id)
			 FROM `{$meta_table}` bm
			 INNER JOIN `{$emails_table}` be ON be.id = bm.mint_email_id
			 WHERE bm.meta_key = 'is_spam' AND bm.meta_value = '1'
			   AND be.created_at BETWEEN %s AND %s",
			$start_dt, $end_dt
		) );

		$delivered_rate = $total_sent > 0 ? round( ( $total_delivered / $total_sent ) * 100, 1 ) : 0.0;
		$bounce_rate    = $total_sent > 0 ? round( ( $total_bounced  / $total_sent ) * 100, 1 ) : 0.0;
		$spam_rate      = $total_sent > 0 ? round( ( $total_spam     / $total_sent ) * 100, 2 ) : 0.0;
		$open_rate      = $total_sent > 0 ? round( ( $total_opened   / $total_sent ) * 100, 1 ) : 0.0;

		// Best send time: hour-of-day with most opens.
		$best_hour_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT HOUR(bm.created_at) AS hr, COUNT(*) AS cnt
			 FROM `{$meta_table}` bm
			 WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'
			   AND bm.created_at BETWEEN %s AND %s
			 GROUP BY hr
			 ORDER BY cnt DESC
			 LIMIT 1",
			$start_dt, $end_dt
		) );

		$best_send_time = '—';
		if ( $best_hour_row ) {
			$hr             = (int) $best_hour_row->hr;
			$day_row        = $wpdb->get_row( $wpdb->prepare(
				"SELECT DAYOFWEEK(bm.created_at) AS dow, COUNT(*) AS cnt
				 FROM `{$meta_table}` bm
				 WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'
				   AND HOUR(bm.created_at) = %d
				   AND bm.created_at BETWEEN %s AND %s
				 GROUP BY dow
				 ORDER BY cnt DESC
				 LIMIT 1",
				$hr, $start_dt, $end_dt
			) );

			$days = array( '', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
			$day  = $day_row ? ( $days[ (int) $day_row->dow ] ?? 'Mon' ) : 'Mon';
			$ampm = $hr >= 12 ? 'pm' : 'am';
			$h12  = $hr % 12 ?: 12;
			$best_send_time = "{$day} {$h12}{$ampm}";
		}

		// ── Per-day chart series ─────────────────────────────────────────────
		$daily_sent = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) AS day, COUNT(*) AS cnt
			 FROM `{$emails_table}`
			 WHERE created_at BETWEEN %s AND %s
			 GROUP BY day",
			$start_dt, $end_dt
		), OBJECT_K );

		$daily_opened = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(bm.created_at) AS day, COUNT(DISTINCT bm.mint_email_id) AS cnt
			 FROM `{$meta_table}` bm
			 WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'
			   AND bm.created_at BETWEEN %s AND %s
			 GROUP BY day",
			$start_dt, $end_dt
		), OBJECT_K );

		$daily_bounced = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) AS day, COUNT(*) AS cnt
			 FROM `{$emails_table}`
			 WHERE status = 'failed' AND created_at BETWEEN %s AND %s
			 GROUP BY day",
			$start_dt, $end_dt
		), OBJECT_K );

		$sent_chart   = array();
		$open_chart   = array();
		$bounce_chart = array();

		$current_day = strtotime( $start );
		$finish_day  = strtotime( $end );
		$step        = $use_weekly ? '+7 days' : '+1 day';

		while ( $current_day <= $finish_day ) {
			$bucket_end = $use_weekly
				? min( strtotime( '+6 days', $current_day ), $finish_day )
				: $current_day;

			$bucket_sent = 0;
			$bucket_open = 0;
			$bucket_bnc  = 0;

			for ( $d = $current_day; $d <= $bucket_end; $d = strtotime( '+1 day', $d ) ) {
				$day_key      = date( 'Y-m-d', $d );
				$bucket_sent += isset( $daily_sent[ $day_key ] )    ? (int) $daily_sent[ $day_key ]->cnt    : 0;
				$bucket_open += isset( $daily_opened[ $day_key ] )  ? (int) $daily_opened[ $day_key ]->cnt  : 0;
				$bucket_bnc  += isset( $daily_bounced[ $day_key ] ) ? (int) $daily_bounced[ $day_key ]->cnt : 0;
			}

			$sent_chart[]   = $bucket_sent;
			$open_chart[]   = $bucket_open;
			$bounce_chart[] = $bucket_bnc;

			$current_day = strtotime( $step, $current_day );
		}

		return array(
			'sent_count'      => number_format( $total_sent ),
			'sends_count'     => $sends_count,
			'delivered_count' => number_format( $total_delivered ),
			'delivered_rate'  => $delivered_rate . '%',
			'bounce_count'    => number_format( $total_bounced ),
			'bounce_rate'     => $bounce_rate . '%',
			'spam_count'      => number_format( $total_spam ),
			'spam_rate'       => $spam_rate . '%',
			'open_rate'       => $open_rate . '%',
			'best_send_time'  => $best_send_time,
			'chart_labels'    => $labels,
			'sent_chart'      => $sent_chart,
			'open_chart'      => $open_chart,
			'bounce_chart'    => $bounce_chart,
		);
	}

	/**
	 * Determine whether to show the onboarding checklist.
	 *
	 * Checks if the onboarding checklist is permanently hidden via a transient.
	 *
	 * @return bool True if the checklist should be shown, false otherwise.
	 * 
	 * @since 1.18.0
	 */
	private static function should_show_checklist()
	{
		// Check if the user has completed the onboarding checklist.
		$permanently_hidden = get_transient('mint_hide_checklist');
		if ($permanently_hidden) {
			return false;
		}

		return true;
	}
}
