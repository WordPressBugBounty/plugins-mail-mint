<?php
/**
 * Manage Automation related database operations
 *
 * @package MintMail\App\Internal\Automation
 * @namespace MintMail\App\Internal\Automation
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace MintMail\App\Internal\Automation;

use MailMintPro\App\Utilities\Helper\Analytics;
use Mint\MRM\Admin\API\Controllers\MessageController;
use Mint\MRM\DataBase\Tables\AutomationLogSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\DataBase\Tables\AutomationSchema;
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use Mint\MRM\DataBase\Tables\EmailMetaSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;
use MintMail\App\Internal\Automation\AutomationStepModel;
use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;
use wpdb;

/**
 * AutomationLogModel class
 *
 * Manage Automation database related operations.
 *
 * @package MintMail\App\Internal\Automation
 * @namespace MintMail\App\Internal\Automation
 *
 * @version 1.0.0
 */
class AutomationLogModel {

	use Singleton;

	/**
	 * Create or update automation log
	 *
	 * @param array $payload payload.
	 * @return int Automation id.
	 */
	public function create_or_update( $payload ) {
		try {
			if ( isset( $payload['automation_id'], $payload['step_id'], $payload['email'], $payload['status'] ) ) {
				$exist_log = $this->is_automation_log_exist( $payload['email'] );
				if ( !$exist_log ) {
					$this->create( $payload );
				} else {
					$payload['count'] = isset( $exist_log['count'] ) ? (int) $exist_log['count'] + 1 : 1;
					$this->create( $payload );
				}
			}
		} catch ( \Exception $e ) {
			return false;
		}

		return false;
	}


	/**
	 * Create automation log
	 *
	 * @param array $payload payload.
	 * @return int Automation id.
	 */
	private function create( $payload ) {
		try {
			global $wpdb;
			$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;
			$wpdb->insert(
				$automation_log_table,
				array(
					'automation_id' => $payload['automation_id'],
					'step_id'       => $payload['step_id'],
					'email'         => $payload['email'],
					'count'         => 1,
					'status'        => $payload['status'],
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				)
			); // db call ok.

			return $wpdb->insert_id;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Update automation log
	 *
	 * @param array $payload payload.
	 * @return int Automation id.
	 */
	private function update( $payload ) {
		try {
			global $wpdb;
			$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;

			if ( isset( $payload['created_at'] ) ) {
				unset( $payload['created_at'] );
			}

			$payload['updated_at'] = current_time( 'mysql' );
			$updated               = $wpdb->update(
				$automation_log_table,
				$payload,
				array( 'ID' => $payload['id'] )
			); // db call ok. ; no-cache ok.

			if ( $updated ) {
				return true;
			} else {
				return false;
			}
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Run SQL query to get or search automations automation database
	 *
	 * @param int    $offset offset.
	 * @param int    $limit limit.
	 * @param string $search search.
	 * @param string $status status.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all( $offset = 0, $limit = 10, $search = '', $status = '' ) {
		global $wpdb;
		$automation_table      = $wpdb->prefix . AutomationSchema::$table_name;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		$search_terms          = null;
		$condition             = 'WHERE';

		// Search automation by name.
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "WHERE name LIKE '%%$search%%'";
			$condition    = 'AND';
		}

		// Prepare sql results for list view.
		try {
			// Return automations in list view.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 'all' === $status ) {
				$select_query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_table {$search_terms} ORDER BY id DESC LIMIT %d, %d", array( $offset, $limit ) ), ARRAY_A ); // db call ok. ; no-cache ok.
				if ( is_array( $select_query ) ) {
					foreach ( $select_query as $key =>$data ) {
						if ( isset( $data['id'] ) ) {
							$select_query[ $key ]['steps'] = HelperFunctions::get_all_automation_step_by_id( $data['id'] );
						}
					}
				}
				$count_query = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) as total FROM $automation_table" ) ); // db call ok. ; no-cache ok.
			} else {
				$select_query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_table {$search_terms} {$condition} status=%s ORDER BY id DESC LIMIT %d, %d", array( $status, $offset, $limit ) ), ARRAY_A ); // db call ok. ; no-cache ok.
				if ( is_array( $select_query ) ) {
					foreach ( $select_query as $key =>$data ) {
						if ( isset( $data['id'] ) ) {
							$select_query[ $key ]['steps'] = HelperFunctions::get_all_automation_step_by_id( $data['id'] );
						}
					}
				}
				$count_query = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) as total FROM $automation_table WHERE status=%s", array( $status ) ) ); // db call ok. ; no-cache ok.
			}

			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$count       = (int) $count_query;
			$total_pages = ceil( $count / $limit );

			return array(
				'data'        => $select_query,
				'total_pages' => $total_pages,
				'count'       => $count,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Delete a automation automation the database
	 *
	 * @param mixed $id automation id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy( $id ) {
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;

		if ( ! self::is_automation_exist( $id ) ) {
			return false;
		}
		return $wpdb->delete( $automation_table, array( 'id' => $id ) ); // db call ok. ; no-cache ok.
	}

	/**
	 * Check existing form on database
	 *
	 * @param mixed $id Form id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_automation_exist( $id ) {
		global $wpdb;
		$form_table = $wpdb->prefix . AutomationSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $form_table WHERE id = %d", array( $id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( $results ) {
			return true;
		}
		return false;
	}

	/**
	 * Delete multiple forms
	 *
	 * @param array $automation_ids form ids.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy_all( $automation_ids ) {
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationLogSchema::$table_name;

		if ( is_array( $automation_ids ) && count( $automation_ids ) > 0 ) {
			$automation_ids = implode( ',', array_map( 'intval', $automation_ids ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE id IN(%1s)', $automation_table, $automation_ids ) ); //  phpcs:ignore.
			return $result;
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return false;
	}

	/**
	 * Check existing automation on database
	 *
	 * @param string $email user email.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function is_automation_log_exist( $email ) {
		global $wpdb;
		$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $automation_log_table WHERE email = %s", array( $email ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( $results ) {
			return isset( $results[0] ) ? $results[0] : false;
		}
		return false;
	}



	/**
	 * Run SQL query to get a automation from automation database
	 *
	 * @param int $id automation id.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_single( $id ) {
		try {
			$enterance        = HelperFunctions::count_total_enterance( $id );
			$completed        = HelperFunctions::count_completed_automation( $id );
			$exited           = HelperFunctions::count_exited_automation( $id );
			$steps            = HelperFunctions::get_all_automation_step_by_id( $id );
			$total_email_send = 0;
			$step_data        = array();
			if ( is_array( $steps ) ) {
				foreach ( $steps as $step ) {
					if ( isset( $step['step_id'] ) ) {
						$step_enterance = HelperFunctions::count_total_enterance_in_step( $step['step_id'] );
						$step_completed = HelperFunctions::count_completed_step( $step['step_id'] );
						$step_exited    = HelperFunctions::count_exited_step( $step['step_id'] );
						$data           = array(
							'step_id'   => $step['step_id'],
							'key'       => $step['key'],
							'type'      => $step['type'],
							'enterance' => $step_enterance,
							'completed' => $step_completed,
							'exited'    => $step_exited,
						);

						if ( 'sendMail' === $step['key'] ) {
							$data['send']     = HelperFunctions::count_sent_mail( $step['step_id'] );
							$total_email_send = $total_email_send + $data['send'];
							$data['opend']    = HelperFunctions::count_opend_mail( $step['step_id'] );
							$data['clicked']  = HelperFunctions::count_clicked_mail( $step['step_id'] );
						}

						$step_data[ $step['step_id'] ] = $data;
					}
				}
			}

			$response = array(
				'enterance' => $enterance,
				'completed' => $completed,
				'exited'    => $exited,
				'step_data' => $step_data,
			);

			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array(
				'data' => $response,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}


	/**
	 * Run SQL query to get a automation from automation database
	 *
	 * @param int    $id automation id.
	 * @param string $filter Filter by Week/Month/Year.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_automation_performance_analytics( $id, $filter ) {
		try {
			$performance_data  = HelperFunctions::count_performance_data( $id, $filter );
			$performance_array = array();

			if ( 'weekly' === $filter ) {
				$week_start_end = get_weekstartend( current_time( 'mysql' ) );
				$start_of_week  = date_i18n( 'Y-m-d', $week_start_end['start'] );

				$week_days = array();
				$interval  = 0;
				while ( $interval < 7 ) {
					$label               = gmdate( 'M d', strtotime( $start_of_week . '+' . $interval . 'day' ) );
					$week_days[ $label ] = 0;
					$interval++;
				}

				$performance_array = self::process_date_based_array( $id, $filter, $week_days, $performance_data );
			} else {
				$current_datetime    = current_datetime();
				$current_month       = date_format( $current_datetime, 'n' );
				$current_month_label = date_format( $current_datetime, 'M' );

				if ( 2 === (int) $current_month ) {
					$days = 28;
				} elseif ( 8 === (int) $current_month || ( 0 !== (int) $current_month % 2 && 9 > (int) $current_month ) || ( 0 === (int) $current_month % 2 && 9 < (int) $current_month ) ) {
					$days = 31;
				} else {
					$days = 30;
				}

				$monthly_days = array();

				for ( $day = 1; $day <= $days; $day++ ) {
					// Use sprintf to pad with 0 if needed.
					$day_label = $current_month_label . ' ' . sprintf( '%02d', $day );

					$monthly_days[ $day_label ] = 0;
				}

				$performance_array = self::process_date_based_array( $id, $filter, $monthly_days, $performance_data );
			}
			$response = array(
				'performance' => $performance_array,
			);

			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array(
				'data' => $response,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Process an associative array based on date data to create a performance array.
	 *
	 * This function processes the given associative array of date-based data and creates a performance array.
	 * It matches the provided bar titles with corresponding dates in the performance data array and calculates
	 * various performance metrics like total_sent, total_open, total_click, open_rate, and click_rate.
	 *
	 * @param int   $automation_id The ID of the automation.
	 * @param array $filter The filter to apply.
	 * @param array $date_array The array of dates.
	 * @param array $performance_data The performance data.
	 *
	 * @return array An array containing calculated performance metrics for each bar title.
	 * @since 1.0.0
	 */
	public static function process_date_based_array( $automation_id, $filter, $date_array, $performance_data ) {
		$total_email_send  = HelperFunctions::count_total_email_sent( $automation_id, $filter );
		$performance_array = array();

		foreach ( $date_array as $bar_title => $value ) {
			$matched_date = array_search(
				$bar_title,
				array_map(
					function ( $date ) use ( $bar_title ) {
						return gmdate( 'M d', strtotime( $date ) );
					},
					array_keys( $performance_data )
				),
				true
			);

			if ( false !== $matched_date ) {
				// Get the original date.
				$original_date                   = array_keys( $performance_data )[ $matched_date ];
				$performance_array[ $bar_title ] = array(
					'total_sent'  => $performance_data[ $original_date ]['total_sent'],
					'total_open'  => $performance_data[ $original_date ]['total_open'],
					'total_click' => $performance_data[ $original_date ]['total_click'],
					'open_rate'   => $total_email_send['total_sent'] > 0 ? ( $performance_data[ $original_date ]['total_open'] / $total_email_send['total_sent'] ) * 100 : 0,
					'click_rate'  => $total_email_send['total_sent'] > 0 ? ( $performance_data[ $original_date ]['total_click'] / $total_email_send['total_sent'] ) * 100 : 0,
					'bar_title'   => $bar_title,
				);
			} else {
				$performance_array[ $bar_title ] = array(
					'total_sent'  => 0,
					'total_open'  => 0,
					'total_click' => 0,
					'open_rate'   => 0,
					'click_rate'  => 0,
					'bar_title'   => $bar_title,
				);
			}
		}

		return $performance_array;
	}

	/**
	 * Run SQL query to get a automation from automation database
	 *
	 * @param int    $id automation id.
	 * @param string $filter Filter by Week/Month/Year.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_automation_overall_analytics( $id, $filter ) {
		try {
			$total_email_send     = HelperFunctions::count_total_email_sent( $id, $filter );
			$subscriber_completed = HelperFunctions::count_completed_subscribers( $id, $filter );
			$entrance             = HelperFunctions::count_total_entrance_with_filter( $id, $filter );

			$overall_data = array(
				'subscriber_completed' => $subscriber_completed,
				'email_sent'           => $total_email_send['total_sent'],
				'entrance'             => $entrance,
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array(
				'data' => $overall_data,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Prepare automation statistics for a specific campaign.
	 *
	 * This function retrieves various statistics for a given campaign, such as the total number of emails sent,
	 * open rate, click rate, and total number of unsubscribes.
	 *
	 * @param int $campaign_id The ID of the campaign to retrieve statistics for.
	 *
	 * @return array An array containing the following statistics:
	 * - 'total_recipients': The total number of emails sent in the campaign.
	 * - 'open_rate': The percentage of opened emails relative to the total sent.
	 * - 'click_rate': The percentage of clicked emails relative to the total sent.
	 * - 'total_unsubscribe': The total number of unsubscribed recipients.
	 *
	 * @since 1.5.7
	 */
	public static function prepare_automation_statistics_for_campaign( $campaign_id ) {
		global $wpdb;

		$step_ids         = AutomationStepModel::get_step_ids_by_campaign_id( $campaign_id );
		$placeholders     = implode( ', ', array_fill( 0, count( $step_ids ), '%s' ) );
		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$response = array(
			'total_recipients'  => 0,
			'open_rate'         => number_format( (float) ( 0 ), 2, '.', '' ),
			'click_rate'        => number_format( (float) ( 0 ), 2, '.', '' ),
			'total_unsubscribe' => 0,
		);

		if ( empty( $placeholders ) ) {
			return $response;
		}

		$response['total_recipients'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(`id`) as total_sent FROM $email_table WHERE step_id IN ($placeholders)", ...$step_ids ) );//  phpcs:ignore

		$select_query_open     = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(mail.id) AS total_open FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail_meta.meta_key = 'is_open' AND mail.step_id IN ($placeholders)", ...$step_ids )); //  phpcs:ignore
		$response['open_rate'] = $select_query_open > 0 ? ( $select_query_open / $response['total_recipients'] ) * 100 : 0;
		$response['open_rate'] = number_format( (float) ( $response['open_rate'] ), 2, '.', '' );

		$select_query_click     = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(mail.id) AS total_click FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail_meta.meta_key = 'is_click' AND mail.step_id IN ($placeholders)", ...$step_ids )); //  phpcs:ignore
		$response['click_rate'] = $select_query_click > 0 ? ( $select_query_click / $response['total_recipients'] ) * 100 : 0;
		$response['click_rate'] = number_format( (float) ( $response['click_rate'] ), 2, '.', '' );

		$response['total_unsubscribe'] = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_unsubscribe FROM {$email_meta_table} WHERE meta_key = 'is_unsubscribe' AND meta_value = 1 AND mint_email_id IN (SELECT id FROM {$email_table} WHERE step_id IN ($placeholders))", ...$step_ids  ) ); //phpcs:ignore
		return $response;
	}

	/**
	 * Prepare analytics data for emails in an automation sequence.
	 *
	 * @param int $campaign_id The ID of the automation sequence (campaign) for which
	 *                           analytics are retrieved.
	 * @param int $email_id      An ID containing information about each email in the
	 *                           automation sequence.
	 *
	 * @return array An array containing detailed analytics data for each email in the
	 *               automation sequence. The structure includes information about delivered,
	 *               bounced, open rates, click rates, performance in the last 24 hours,
	 *               devices, unsubscribes, and orders.
	 * @since 1.6.4
	 */
	public static function prepare_analytics_for_automation_sequence( $campaign_id, $email_id ) {
		$total_recipients = AutomationStepModel::get_total_recipients_for_automation_email( $email_id );
		// Calculate delivered and bounced emails.
		$total_delivered = MessageController::prepare_delivered_reports( $email_id, $total_recipients );
		$total_bounced   = MessageController::prepare_bounced_reports( $email_id, $total_recipients );
		$bounced         = isset( $total_bounced['total_bounced'] ) ? $total_bounced['total_bounced'] : '';
		$delivered       = isset( $total_delivered['total_delivered'] ) ? $total_delivered['total_delivered'] : '';

		// Calculate click and open rate.
		$open_rate  = MessageController::prepare_open_rate_reports( $email_id, $bounced, $total_recipients );
		$click_rate = MessageController::prepare_click_rate_reports( $email_id, $bounced, $total_recipients );
		$ctor       = MessageController::prepare_click_to_open_rate_reports( $click_rate, $open_rate );

		// Calculate last 24 hours performance.
		$last_day = MessageController::prepare_last_day_reports( $email_id );

		// Calculate total unsubscribe.
		$unsubscribe = MessageController::prepare_unsubscribe_reports( $email_id, $bounced, $delivered );

		// Calculate order reports.
		$orders = MessageController::prepare_order_reports( $email_id, 'automation' );

		// Return the email metrics reports.
		$metrics = array_merge( $total_delivered, $total_bounced, $open_rate, $click_rate, $unsubscribe, $orders, $ctor );

		// Prepare campaign summery.
		$summery = Analytics::prepare_automation_summery( $campaign_id );

		return array(
			'recipients' => $total_recipients,
			'metrics'    => $metrics,
			'last_day'   => $last_day,
			'summery'    => $summery,
		);
	}
}
