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
use Mint\MRM\DataBase\Tables\FormSchema;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;
use MRM\Common\MrmCommon;
use Mint\MRM\DataBase\Models\CampaignModel as ModelsCampaign;
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
	 * Retrieves total counts or relevant metrics for contacts, campaigns, automations, and forms.
	 *
	 * @param string $filter Optional. Filter to apply on the data (e.g., 'all', 'today', 'this_week', etc.). Default 'all'.
	 *
	 * @return array Associative array with keys: 'contacts', 'campaigns', 'automations', 'forms'.
	 * 
	 * @since 1.18.0
	 */
	public static function get_top_cards_data( $filter = 'all' ) {
		global $wpdb;
		$contact_table    = $wpdb->prefix . ContactSchema::$table_name;
		$form_table       = $wpdb->prefix . FormSchema::$table_name;
		$campaign_table   = $wpdb->prefix . CampaignSchema::$campaign_table;
		$automation_table = $wpdb->prefix . AutomationMetaSchema::$table_name;

		$contact_data    = self::fetch_data( $contact_table, $filter );
		$campaign_data   = self::fetch_data( $campaign_table, $filter );
		$form_data       = self::fetch_data( $form_table, $filter );
		$automation_data = self::fetch_automation_data( $automation_table, $filter );

		return array(
			'contacts'    => $contact_data,
			'campaigns'   => $campaign_data,
			'automations' => $automation_data,
			'forms'       => $form_data,
		);
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
	private static function fetch_data( string $table_name, string $filter ) {
		global $wpdb;

		$table_name = esc_sql($table_name);

		$callback_function = 'get_where_query_for_' . $filter;

		if (! method_exists(__CLASS__, $callback_function)) {
			return array(
				'current'  => 0,
				'previous' => 0,
				'change'   => 0.0,
				'trend'    => 'equal',
			);
		}

		$conditions = call_user_func(array(__CLASS__, $callback_function));

		$where_current  = isset($conditions['conditions_1']) ? $conditions['conditions_1'] : '';
		$where_previous = isset($conditions['conditions_2']) ? $conditions['conditions_2'] : '';

		if (empty($where_current) || empty($where_previous)) {
			return array(
				'current'  => 0,
				'previous' => 0,
				'change'   => 0.0,
				'trend'    => 'equal',
			);
		}

		$query_current  = "SELECT COUNT(`id`) FROM `{$table_name}` WHERE {$where_current}";
		$query_previous = "SELECT COUNT(`id`) FROM `{$table_name}` WHERE {$where_previous}";

		$current_data  = (float) $wpdb->get_var($query_current);
		$previous_data = (float) $wpdb->get_var($query_previous);

		$diff_rate = ($previous_data > 0)
			? (($current_data - $previous_data) / $previous_data) * 100
			: ($current_data > 0 ? 100.0 : 0.0);

		$formatted_diff = number_format($diff_rate, 2) . '%';

		// Calculate trend
		$trend = 'equal';
		if ($current_data > $previous_data) {
			$trend = 'up';
		} elseif ($current_data < $previous_data) {
			$trend = 'down';
		}

		return array(
			'current'  => number_format($current_data),
			'previous' => number_format($previous_data),
			'change'   => $formatted_diff,
			'trend'    => $trend,
		);
	}

	/**
	 * Perform database query and fetch required data
	 *
	 * @param string $table_name Database table name.
	 * @param string $filter Filter name (MONTH, WEEK, YEAR).
	 *
	 * @return float[]
	 */
	private static function fetch_automation_data( string $table_name, string $filter ) {
		global $wpdb;

		// Sanitize table name (or use whitelist depending on your project)
		$table_name = esc_sql($table_name);

		// Prepare callback method for conditions
		$callback_function = 'get_where_query_for_' . $filter;

		// Get query conditions using the callback function
		$conditions = call_user_func(array(__CLASS__, $callback_function));

		$where_current  = isset($conditions['conditions_1']) ? $conditions['conditions_1'] : '';
		$where_previous = isset($conditions['conditions_2']) ? $conditions['conditions_2'] : '';

		// Table names can't be parameterized in prepare(), so use esc_sql() and interpolate
		$query_current  = "SELECT COUNT(`id`) FROM `{$table_name}` WHERE {$where_current} AND meta_key = %s AND meta_value = %s";
		$query_previous = "SELECT COUNT(`id`) FROM `{$table_name}` WHERE {$where_previous} AND meta_key = %s AND meta_value = %s";

		$current_data  = (float) $wpdb->get_var($wpdb->prepare($query_current, 'source', 'mint'));
		$previous_data = (float) $wpdb->get_var($wpdb->prepare($query_previous, 'source', 'mint'));

		// Calculate percentage difference
		$diff_rate = ($previous_data > 0)
			? (($current_data - $previous_data) / $previous_data) * 100
			: ($current_data > 0 ? 100.0 : 0.0);

		$formatted_diff = number_format($diff_rate, 2) . '%';

		// Determine trend: 'up', 'down', or 'equal'
		$trend = 'equal';
		if ($current_data > $previous_data) {
			$trend = 'up';
		} elseif ($current_data < $previous_data) {
			$trend = 'down';
		}

		return array(
			'current'  => number_format($current_data), // Adds thousands separator (e.g., 19,747)
			'previous' => number_format($previous_data),
			'change'   => $formatted_diff,
			'trend'    => $trend,
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
	 * Get last five campaign analytics data (archived and running)
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_recent_campaign_performance() {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		$sql = 'SELECT `id`, `title`, `updated_at`, `type` FROM %1s WHERE `status` IN (%s, %s) ORDER BY updated_at DESC LIMIT 0, 5';

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $campaigns_table, 'archived', 'active' ), ARRAY_A ); //phpcs:ignore
		if ( empty( $results ) ) {
			return array();
		}

		// Prepare campaigns data.
		$campaigns = array();
		foreach ( $results as $campaign ) {
			$campaign_id      = isset( $campaign['id'] ) ? (int) $campaign['id'] : 0;
			$title            = isset( $campaign['title'] ) ? $campaign['title'] : '';
			$total_bounced    = EmailModel::count_delivered_status_on_campaign( $campaign_id, 'failed' );
			$total_recipients = ModelsCampaign::get_campaign_meta_value( $campaign_id, 'total_recipients' );
			$campaigns[]      = array(
				'id'               => $campaign_id,
				'title'            => $title,
				'type'             => isset( $campaign['type'] ) ? $campaign['type'] : '',
				'total_recipients' => $total_recipients,
				'open_rate'        => ModelsCampaign::prepare_campaign_open_rate( $campaign_id, $total_recipients, $total_bounced ) . '%',
				'click_rate'       => ModelsCampaign::prepare_campaign_click_rate( $campaign_id, $total_recipients, $total_bounced ) . '%',
				'unsubscribe'      => EmailModel::count_unsubscribe_on_campaign( $campaign_id ),
			);
		}
		return $campaigns;
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
		$results = AutomationModel::get_all('id', 'desc', 0, 5, '', 'active');
		if ( ! isset( $results['data'] ) || empty( $results['data'] ) ) {
			return array();
		}
		
		$automations = array();

		if ( isset($results['data'] ) ) {
			$automations = array_map(
				function( $automation ) {
					if ( is_array( $automation ) && !empty( $automation ) ) {
						$created_at    = isset( $automation['created_at'] ) ? $automation['created_at'] : '';
						$automation_id = isset( $automation['id'] ) ? $automation['id'] : '';

						$automation['created_ago'] = human_time_diff( strtotime( $created_at ), current_time( 'timestamp' ) );
						$automation['entered']   = HelperFunctions::count_total_enterance( $automation_id );
						$automation['completed']   = HelperFunctions::count_completed_automation($automation_id);
						$automation['processing']  = $automation['entered'] - $automation['completed'];
					}
					return $automation;
				},
				$results['data']
			);
		}

		return $automations;
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

		$boarding_steps = [
			[
				'label'     => __('Create a List', 'mrm'),
				'completed' => ContactGroupModel::get_groups_count('lists') > 0,
				'link'      => 'lists',
			],
			[
				'label'     => __('Create or Import Contacts', 'mrm'),
				'completed' => ContactModel::get_contacts_count() > 0,
				'link'      => 'contacts',
			],
			[
				'label'     => __('Complete Email Settings', 'mrm'),
				'completed' => get_option('_mrm_email_settings'),
				'link'      => 'settings/email-settings',
			],
			[
				'label'     => __('Create a Campaign', 'mrm'),
				'completed' => CampaignModel::get_campaign_count() > 0,
				'link'      => 'campaigns/regular',
			],
			[
				'label'     => __('Create a Form', 'mrm'),
				'completed' => FormModel::get_form_count() > 0,
				'link'      => 'forms',
			],
			[
				'label'     => __('Create a Automation', 'mrm'),
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
