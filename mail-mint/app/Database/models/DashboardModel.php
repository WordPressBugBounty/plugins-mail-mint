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

use DateTime;
use Mint\MRM\DataBase\Tables\AutomationMetaSchema;
use Mint\MRM\DataBase\Tables\ContactMetaSchema;
use Mint\MRM\DataBase\Tables\FormSchema;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;
use Mint\MRM\DataBase\Models\CampaignModel as ModelsCampaign;



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

	use Singleton;

	/**
	 * Get top card data
	 *
	 * @param string $filter Filter by all, monthly, weekly, yearly and custom range.
	 * @param string $start_date Start date for custom range.
	 * @param string $end_date End date for custom range.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_top_cards_data( $filter = 'all', $start_date = '', $end_date = '' ) {
		global $wpdb;
		$contact_table    = $wpdb->prefix . ContactSchema::$table_name;
		$form_table       = $wpdb->prefix . FormSchema::$table_name;
		$campaign_table   = $wpdb->prefix . CampaignSchema::$campaign_table;
		$automation_table = $wpdb->prefix . AutomationMetaSchema::$table_name;

		$contact_data    = self::fetch_data( $contact_table, $filter, $start_date, $end_date );
		$campaign_data   = self::fetch_data( $campaign_table, $filter, $start_date, $end_date );
		$form_data       = self::fetch_data( $form_table, $filter, $start_date, $end_date );
		$automation_data = self::fetch_automation_data( $automation_table, $filter, $start_date, $end_date );

		return array(
			'contact_data'    => $contact_data,
			'campaign_data'   => $campaign_data,
			'form_data'       => $form_data,
			'automation_data' => $automation_data,
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
	 */
	private static function fetch_data( string $table_name, string $filter, string $start_date = '', string $end_date = '' ) {
		global $wpdb;

		$callback_function = 'get_where_query_for_' . $filter;
		$conditions        = self::$callback_function( $start_date, $end_date );

		$conditions1 = ! empty( $conditions[ 'conditions_1' ] ) ? $conditions[ 'conditions_1' ] : '1<>1';
		$conditions2 = ! empty( $conditions[ 'conditions_2' ] ) ? $conditions[ 'conditions_2' ] : '1<>1';

		$current_data  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(`id`) FROM %1s WHERE %1s", array( $table_name, $conditions1 ) ) ); //phpcs:ignore
		$previous_data = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(`id`) FROM %1s WHERE %1s", array( $table_name, $conditions2 ) ) ); //phpcs:ignore

		$diff_rate = ( $current_data - $previous_data ) * 100.00;
		$diff_rate = $previous_data > 0 ? $diff_rate / $previous_data : $diff_rate;

		return array(
			'current'  => $current_data,
			'previous' => $previous_data,
			'rate'     => $diff_rate - intval( $diff_rate ) != 0 ? number_format( $diff_rate, 2 ) : $diff_rate, //phpcs:ignore
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
	 */
	private static function fetch_automation_data( string $table_name, string $filter, string $start_date = '', string $end_date = '' ) {
		global $wpdb;

		$callback_function = 'get_where_query_for_' . $filter;
		$conditions        = self::$callback_function( $start_date, $end_date );

		$conditions1 = ! empty( $conditions[ 'conditions_1' ] ) ? $conditions[ 'conditions_1' ] : '1<>1';
		$conditions2 = ! empty( $conditions[ 'conditions_2' ] ) ? $conditions[ 'conditions_2' ] : '1<>1';

		$query1 = $wpdb->prepare( "SELECT COUNT(`id`) FROM %1s WHERE %1s AND meta_key = %s AND meta_value = %s", array( $table_name, $conditions1, 'source', 'mint' ) ); //phpcs:ignore
		$query2 = $wpdb->prepare( "SELECT COUNT(`id`) FROM %1s WHERE %1s AND meta_key = %s AND meta_value = %s", array( $table_name, $conditions2, 'source', 'mint' ) ); //phpcs:ignore

		$current_data  = (float) $wpdb->get_var( $query1 ); //phpcs:ignore
		$previous_data = (float) $wpdb->get_var( $query2 ); //phpcs:ignore

		$diff_rate = ( $current_data - $previous_data ) * 100.00;
		$diff_rate = $previous_data > 0 ? $diff_rate / $previous_data : $diff_rate;

		return array(
			'current'  => $current_data,
			'previous' => $previous_data,
			'rate'     => $diff_rate - intval( $diff_rate ) != 0 ? number_format( $diff_rate, 2 ) : $diff_rate, //phpcs:ignore
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
	private static function get_where_query_for_weekly() {
		return array(
			'conditions_1' => '(YEARWEEK(created_at)=YEARWEEK(NOW()) OR YEARWEEK(updated_at)=YEARWEEK(NOW()))',
			'conditions_2' => '(YEARWEEK(created_at)=YEARWEEK(NOW())-1 OR YEARWEEK(updated_at)=YEARWEEK(NOW())-1)',
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
	private static function get_where_query_for_monthly() {
		return array(
			'conditions_1' => '(EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now()))',
			'conditions_2' => '(EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now()) - 1)',
		);
	}

	/**
	 * Get where query for yearly filter
	 *
	 * @return string[]
	 *
	 * @since 1.0.0
	 */
	private static function get_where_query_for_yearly() {
		return array(
			'conditions_1' => '(YEAR(created_at)=YEAR(NOW()) OR YEAR(updated_at)=YEAR(NOW()))',
			'conditions_2' => '(YEAR(created_at)=YEAR(NOW())-1 OR YEAR(updated_at)=YEAR(NOW())-1)',
		);
	}

	/**
	 * Get top email campaign data
	 *
	 * @param string $filter Filter by monthly, weekly, yearly and custom range.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_email_campaign_data( $filter ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		$date_field = 'updated_at';
		switch ( $filter ) {
			case 'monthly':
				$date_condition = 'EXTRACT(YEAR_MONTH FROM ' . $date_field . ') = EXTRACT(YEAR_MONTH FROM now())';
				break;
			case 'weekly':
				$date_condition = 'YEARWEEK(' . $date_field . ') = YEARWEEK(NOW())';
				break;
			case 'yearly':
				$date_condition = 'YEAR(' . $date_field . ') = YEAR(NOW())';
				break;
			default:
				$date_condition = '1=1';
				break;
		}

		if ( ! empty( $date_condition ) ) {
			$curr_draft_campaigns = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND %1s", array( $campaign_table, 'archived', $date_condition ) ) ); //phpcs:ignore
			$curr_sent_campaigns  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND %1s", array( $campaign_table, 'active', $date_condition ) ) ); //phpcs:ignore

			return array(
				'completed' => $curr_draft_campaigns,
				'running'   => $curr_sent_campaigns,
			);
		}

		return array();
	}

	/**
	 * Get contact chart data
	 *
	 * @param string $filter Filter by monthly, weekly, yearly and custom range.
	 * @param string $start_date Start date for custom range.
	 * @param string $end_date End date for custom range.
	 *
	 * @return array|void
	 * @since 1.0.0
	 */
	public static function get_contact_chart_data( $filter, $start_date = '', $end_date = '' ) {
		global $wpdb;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;

		if ( 'monthly' === $filter ) {
			$curr_month_pending_contacts      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now())", array( $contact_table, 'pending' ) ) ); //phpcs:ignore
			$curr_month_subscribed_contacts   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now())", array( $contact_table, 'subscribed' ) ) ); //phpcs:ignore
			$curr_month_unsubscribed_contacts = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now())", array( $contact_table, 'unsubscribed' ) ) ); //phpcs:ignore

			$contact_chart_data = array(
				'total_subscriber'   => $curr_month_subscribed_contacts,
				'total_unsubscriber' => $curr_month_unsubscribed_contacts,
				'total_pending'      => $curr_month_pending_contacts,
			);

			$contact_chart_data['total_contacts'] = $curr_month_subscribed_contacts + $curr_month_unsubscribed_contacts + $curr_month_pending_contacts;
			return $contact_chart_data;
		} elseif ( 'all' === $filter ) {
			$curr_month_pending_contacts      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s", array( $contact_table, 'pending' ) ) ); //phpcs:ignore
			$curr_month_subscribed_contacts   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s", array( $contact_table, 'subscribed' ) ) ); //phpcs:ignore
			$curr_month_unsubscribed_contacts = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s", array( $contact_table, 'unsubscribed' ) ) ); //phpcs:ignore

			$contact_chart_data = array(
				'total_subscriber'   => $curr_month_subscribed_contacts,
				'total_unsubscriber' => $curr_month_unsubscribed_contacts,
				'total_pending'      => $curr_month_pending_contacts,
			);

			$contact_chart_data['total_contacts'] = $curr_month_subscribed_contacts + $curr_month_unsubscribed_contacts + $curr_month_pending_contacts;
			return $contact_chart_data;
		} elseif ( 'weekly' === $filter ) {
			$curr_week_pending_contacts      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND YEARWEEK(created_at)=YEARWEEK(NOW())", array( $contact_table, 'pending' ) ) ); //phpcs:ignore
			$curr_week_subscribed_contacts   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND YEARWEEK(created_at)=YEARWEEK(NOW())", array( $contact_table, 'subscribed' ) ) ); //phpcs:ignore
			$curr_week_unsubscribed_contacts = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND YEARWEEK(created_at)=YEARWEEK(NOW())", array( $contact_table, 'unsubscribed' ) ) ); //phpcs:ignore

			$contact_chart_data = array(
				'total_subscriber'   => $curr_week_subscribed_contacts,
				'total_unsubscriber' => $curr_week_unsubscribed_contacts,
				'total_pending'      => $curr_week_pending_contacts,
			);

			$contact_chart_data['total_contacts'] = $curr_week_subscribed_contacts + $curr_week_unsubscribed_contacts + $curr_week_pending_contacts;
			return $contact_chart_data;
		} elseif ( 'yearly' === $filter ) {
			$curr_year_pending_contacts      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND YEAR(created_at)=YEAR(NOW())", array( $contact_table, 'pending' ) ) ); //phpcs:ignore
			$curr_year_subscribed_contacts   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND YEAR(created_at)=YEAR(NOW())", array( $contact_table, 'subscribed' ) ) ); //phpcs:ignore
			$curr_year_unsubscribed_contacts = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM %1s WHERE status=%s AND YEAR(created_at)=YEAR(NOW())", array( $contact_table, 'unsubscribed' ) ) ); //phpcs:ignore

			$contact_chart_data = array(
				'total_subscriber'   => $curr_year_subscribed_contacts,
				'total_unsubscriber' => $curr_year_unsubscribed_contacts,
				'total_pending'      => $curr_year_pending_contacts,
			);

			$contact_chart_data['total_contacts'] = $curr_year_subscribed_contacts + $curr_year_unsubscribed_contacts + $curr_year_pending_contacts;
			return $contact_chart_data;
		}
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
	 * Get subscribers for current year (monthly)
	 *
	 * @return array|object|\stdClass[]|null
	 *
	 * @since 1.0.0
	 */
	public static function get_subscribers_for_yearly() {
		global $wpdb;
		$contact_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = "SELECT DATE_FORMAT(created_at, '%b') AS label";
		$query .= ', COUNT(contact_id) as subscribers ';
		$query .= 'FROM %1s ';
		$query .= 'WHERE meta_key = %s ';
		$query .= 'AND meta_value = %s ';
		$query .= 'AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 1 YEAR) AND NOW() ';
		$query .= "GROUP BY DATE_FORMAT(created_at, '%b') ";
		$query .= "ORDER BY DATE_FORMAT(created_at, '%b') ASC";

		$result = $wpdb->get_results( $wpdb->prepare( $query, array( $contact_meta_table, 'status_changed', 'subscribed' ) ), ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'subscribers', 'label' );

		$months = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$months[date('M', strtotime("-$i month"))] = 0;
		}

		$months = array_reverse( $months, true );

		return array(
			'new_subscribers'   => array_merge( $months, $result ),
			'total_subscribers' => self::get_total_subscribers(),
		);
	}

	/**
	 * Get subscribers for current month (daily)
	 *
	 * @return array|\stdClass[]
	 *
	 * @since 1.0.0
	 */
	public static function get_subscribers_for_monthly() {
		global $wpdb;
		$contact_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = "SELECT DATE_FORMAT(created_at, '%b %e') AS label";
		$query .= ', COUNT(contact_id) as subscribers ';
		$query .= 'FROM %1s ';
		$query .= 'WHERE meta_key = %s ';
		$query .= 'AND meta_value = %s ';
		$query .= 'AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW() ';
		$query .= "GROUP BY DATE_FORMAT(created_at, '%b %e') ";
		$query .= "ORDER BY DATE_FORMAT(created_at, '%b %e') ASC";

		$result = $wpdb->get_results( $wpdb->prepare( $query, array( $contact_meta_table, 'status_changed', 'subscribed' ) ), ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'subscribers', 'label' );

		$monthly_days = array();

		for ( $i = 0; $i < 30; $i++ ) {
			$monthly_days[date('M j', strtotime("-$i day"))] = 0;
		}
		$monthly_days = array_reverse( $monthly_days, true );

		return array(
			'new_subscribers'   => array_merge( $monthly_days, $result ),
			'total_subscribers' => self::get_total_subscribers(),
		);
	}

	/**
	 * Get subscribers for current week (daily)
	 *
	 * @return array|\stdClass[]
	 *
	 * @since 1.0.0
	 */
	public static function get_subscribers_for_weekly() {
		global $wpdb;
		$contact_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = "SELECT DATE_FORMAT(created_at, '%b %e') AS label";
		$query .= ', COUNT(contact_id) as subscribers ';
		$query .= $wpdb->prepare( "FROM %1s ", array( $contact_meta_table ) ); //phpcs:ignore
		$query .= $wpdb->prepare( "WHERE meta_key = %s ", array( 'status_changed' ) ); //phpcs:ignore
		$query .= $wpdb->prepare( "AND meta_value = %s ", array( 'subscribed' ) ); //phpcs:ignore
		$query .= "AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 6 DAY) AND NOW() ";
		$query .= "GROUP BY DATE_FORMAT(created_at, '%b %e') ";
		$query .= "ORDER BY DATE_FORMAT(created_at, '%c %e') ASC";
		$result = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore

		$result = array_column( $result, 'subscribers', 'label' );

		$week_days = array();
		$interval  = 0;
		while ( $interval < 7 ) {
			$label               = date( 'M j', strtotime( '-' . $interval . 'day' ) ); //phpcs:ignore
			$week_days[ $label ] = 0;
			$interval++;
		}
		$week_days = array_reverse( $week_days, true );

		return array(
			'new_subscribers'   => array_merge( $week_days, $result ),
			'total_subscribers' => self::get_total_subscribers(),
		);

		return array(
			'new_subscribers'   => array(),
			'total_subscribers' => array(),
		);
	}

	/**
	 * Get subscribers for all filter tag
	 *
	 * @return array|\stdClass[]
	 *
	 * @since 1.0.0
	 */
	public static function get_subscribers_for_all() {
		global $wpdb;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;

		$first_date = $wpdb->get_row( $wpdb->prepare( 'SELECT created_at FROM %1s LIMIT 1', $contact_table ), ARRAY_A ); //phpcs:ignore
		$first_date = isset( $first_date['created_at'] ) ? $first_date['created_at'] : '';
		$prev_date  = new DateTime( $first_date );
		$curt_date  = new DateTime();
		$interval   = $prev_date->diff( $curt_date );
		$days       = $interval->days;

		if ( $days <= 7 ) {
			return self::get_subscribers_for_weekly();
		} elseif ( $days > 7 && $days <= 31 ) {
			return self::get_subscribers_for_monthly();
		} elseif ( $days > 31 && $days <= 365 ) {
			return self::get_subscribers_for_yearly();
		} elseif ( $days > 365 && $days <= 1460 ) {
			return self::get_subscribers_for_quarterly( $first_date );
		} else {
			return self::get_subscribers_for_all_yearly( $first_date );
		}
	}

	/**
	 * Retrieves the subscriber growth rate for every quarter from the current year to the next four years.
	 *
	 * @param string $first_date First date while data inserted on contacts table.
	 * @return array An array containing the yearly subscriber growth rate data.
	 * @since 1.0.0
	 */
	public static function get_subscribers_for_quarterly( $first_date ) {
		global $wpdb;
		$contact_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = "SELECT CONCAT(YEAR(created_at), ' Q', QUARTER(created_at)) AS label";
		$query .= ', COUNT(contact_id) as subscribers ';
		$query .= 'FROM %1s ';
		$query .= 'WHERE meta_key = %s ';
		$query .= 'AND meta_value = %s ';
		$query .= 'AND created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)';
		$query .= 'GROUP BY YEAR(created_at), QUARTER(created_at) ';
		$query .= 'ORDER BY YEAR(created_at) ASC, QUARTER(created_at) ASC';
		$result = $wpdb->get_results( $wpdb->prepare( $query, array( $contact_meta_table, 'status_changed', 'subscribed' ) ), ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'subscribers', 'label' );

		$current_year = gmdate( 'Y', strtotime( $first_date ) );

		$quarters = array();
		$count    = 0;

		for ( $year = $current_year; $year < $current_year + 4; $year++ ) {
			for ( $quarter = 1; $quarter <= 4; $quarter++ ) {
				$count++;
				if ( $count > 20 ) {
					break 2;
				}

				$label       = $year . ' Q' . $quarter;
				$subscribers = isset( $result[ $label ] ) ? $result[ $label ] : 0;

				$quarters[ $label ] = $subscribers;
			}
		}

		return array(
			'new_subscribers'   => $quarters,
			'total_subscribers' => self::get_total_subscribers(),
		);
	}

	/**
	 * Retrieves the subscriber growth rate for every year from the current year to the next five years.
	 *
	 * @param string $first_date First date while data inserted on contacts table.
	 * @return array An array containing the yearly subscriber growth rate data.
	 * @since 1.0.0
	 */
	public static function get_subscribers_for_all_yearly( $first_date ) {
		global $wpdb;
		$contact_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = "SELECT DATE_FORMAT(created_at, '%Y') AS label";
		$query .= ', COUNT(contact_id) as subscribers ';
		$query .= 'FROM %1s ';
		$query .= 'WHERE meta_key = %s ';
		$query .= 'AND meta_value = %s ';
		$query .= 'AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 5 YEAR) AND DATE_ADD(NOW(), INTERVAL 1 YEAR) ';
		$query .= "GROUP BY DATE_FORMAT(created_at, '%Y') ";
		$query .= "ORDER BY DATE_FORMAT(created_at, '%Y') ASC";

		$result = $wpdb->get_results( $wpdb->prepare( $query, array( $contact_meta_table, 'status_changed', 'subscribed' ) ), ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'subscribers', 'label' );

		$start_year = gmdate( 'Y', strtotime( $first_date ) );
		$last_year  = $start_year + 5;

		$yearly_data = array();
		for ( $year = $start_year; $year <= $last_year; $year++ ) {
			$yearly_data[ $year ] = isset( $result[ $year ] ) ? $result[ $year ] : 0;
		}

		return array(
			'new_subscribers'   => $yearly_data,
			'total_subscribers' => self::get_total_subscribers(),
		);
	}

	/**
	 * Get total subscribers till date
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	public static function get_total_subscribers() {
		global $wpdb;
		$contact_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = 'SELECT COUNT(contact_id) total_subscribers ';
		$query .= 'FROM %1s ';
		$query .= 'WHERE meta_key = %s ';
		$query .= 'AND meta_value = %s';

		return $wpdb->get_var( $wpdb->prepare( $query, array( $contact_meta_table, 'status_changed', 'subscribed' ) ) ); //phpcs:ignore
	}

	/**
	 * Helper function to get subscriber growth
	 *
	 * @param string $filter Filter option.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public static function get_subscribers_report( string $filter ) {
		$get_subscribers = 'get_subscribers_for_' . strtolower( $filter );
		$subscribers     = self::$get_subscribers();

		$total_subscribers = ! empty( $subscribers[ 'total_subscribers' ] ) ? $subscribers[ 'total_subscribers' ] : 0;
		$labels            = array();
		$values            = array();

		if ( ! empty( $subscribers[ 'new_subscribers' ] ) ) {
			$labels = array_keys( $subscribers[ 'new_subscribers' ] );
			$values = array_values( $subscribers[ 'new_subscribers' ] );
		}

		$existing_subscribers = self::prepare_existing_subscribers( $total_subscribers, $labels, $values, $filter );

		$existing_max = ! empty( $existing_subscribers[ 'values' ] ) ? max( $existing_subscribers[ 'values' ] ) : 0;
		$new_max      = ! empty( $values ) ? max( $values ) : 0;

		$max = max( array( $existing_max, $new_max ) );

		return array(
			'new_subscribers'      => array(
				'labels' => $labels,
				'values' => $values,
			),
			'existing_subscribers' => self::prepare_existing_subscribers( $total_subscribers, $labels, $values, $filter ),
			'total_subscribers'    => $total_subscribers,
			'max_today'            => $max,
		);
	}

	/**
	 * Prepare existing users
	 *
	 * @param int    $total_subscribers Total subscribers.
	 * @param array  $labels Labels.
	 * @param array  $new_values New subscribers count.
	 * @param string $filter Filter.
	 *
	 * @return array|array[]
	 *
	 * @since 1.0.0
	 */
	public static function prepare_existing_subscribers( $total_subscribers, $labels, $new_values, $filter ) {
		global $wpdb;
		$contact_table        = $wpdb->prefix . ContactSchema::$table_name;
		$existing_subscribers = array( 'values' => array() );
		$label                = '';

		$first_date = $wpdb->get_row( $wpdb->prepare( 'SELECT created_at FROM %1s LIMIT 1', $contact_table ), ARRAY_A ); //phpcs:ignore
		$first_date = isset( $first_date['created_at'] ) ? $first_date['created_at'] : '';
		$prev_date  = new DateTime( $first_date );
		$curt_date  = new DateTime();
		$interval   = $prev_date->diff( $curt_date );
		$days       = $interval->days;

		if ( 'yearly' === strtolower( $filter ) ) {
			$label = date_format( current_datetime(), 'M' );
		} elseif ( 'monthly' === strtolower( $filter ) || 'weekly' === strtolower( $filter ) ) {
			$label = date_format( current_datetime(), 'M j' );
		} else {
			switch ( true ) {
				case ( $days <= 7 ):
					$label = date_format( current_datetime(), 'M j' );
					break;
				case ( $days <= 31 ):
					$label = date_format( current_datetime(), 'M j' );
					break;
				case ( $days <= 365 ):
					$label = date_format( current_datetime(), 'M' );
					break;
				default:
					$label = date_format( current_datetime(), 'M' );
					break;
			}
		}

		$label_index = array_search( $label, $labels, true );

		for ( $key = 0; $key <= $label_index; $key++ ) {
			$total = 0;

			for ( $index = $key; $index <= $label_index; $index ++ ) {
				$total += isset( $new_values[ $index ] ) ? (int) $new_values[ $index ] : 0;
			}
			$existing_subscribers[ 'values' ][] = $total_subscribers - $total;
		}

		return $existing_subscribers;
	}


	/**
	 * Get last five campaign analytics data (archived and running)
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaigns_short_analytics() {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		$sql = 'SELECT `id`, `title`, `updated_at`, `type` FROM %1s WHERE `status` IN (%s, %s) ORDER BY updated_at DESC LIMIT 0, 5';

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $campaigns_table, 'archived', 'active' ) ); //phpcs:ignore

		$campaigns = array();
		foreach ( $results as $campaign ) {
			$campaign_id      = $campaign->id;
			$title            = $campaign->title;
			$total_bounced    = EmailModel::count_delivered_status_on_campaign( $campaign_id, 'failed' );
			$total_recipients = ModelsCampaign::get_campaign_meta_value( $campaign_id, 'total_recipients' );
			$campaigns[]      = array(
				'id'               => $campaign_id,
				'title'            => $title,
				'type'             => $campaign->type,
				'updated_at'       => gmdate( get_option( 'date_format' ), strtotime( $campaign->updated_at ) ),
				'total_recipients' => $total_recipients,
				'open_rate'        => ModelsCampaign::prepare_campaign_open_rate( $campaign_id, $total_recipients, $total_bounced ),
				'click_rate'       => ModelsCampaign::prepare_campaign_click_rate( $campaign_id, $total_recipients, $total_bounced ),
				'unsubscribe'      => EmailModel::count_unsubscribe_on_campaign( $campaign_id ),
			);
		}
		return $campaigns;
	}

}
