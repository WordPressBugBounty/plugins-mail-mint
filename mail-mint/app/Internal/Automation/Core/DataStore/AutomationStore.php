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

use Mint\MRM\DataBase\Models\EmailModel;
use Mint\MRM\DataBase\Tables\AutomationMetaSchema;
use Mint\MRM\DataBase\Tables\AutomationLogSchema;
use Mint\MRM\DataBase\Tables\AutomationJobSchema;
use Mint\MRM\DataBase\Tables\AutomationStepMetaSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\DataBase\Tables\AutomationSchema;
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use MintMail\App\Internal\Automation\AutomationStepModel;
use MintMail\App\Internal\Automation\HelperFunctions;
use MintMailPro\Mint_Pro_Helper;
use wpdb;

/**
 * AutomationModel class
 *
 * Manage Automation database related operations.
 *
 * @package MintMail\App\Internal\Automation
 * @namespace MintMail\App\Internal\Automation
 *
 * @version 1.0.0
 */
class AutomationModel {

	use Singleton;

	/**
	 * Create or update automation
	 *
	 * @param array $payload payload.
	 * @return int Automation id.
	 */
	public function create_or_update( $payload ) {
		try {
			if ( isset( $payload['name'], $payload['author'], $payload['trigger_name'], $payload['status'] ) ) {
				$automation_id = '';
				$saved_steps   = array();
				if ( isset( $payload['id'] ) ) {
					$saved_steps = HelperFunctions::get_all_step_by_automation_id( $payload['id'] );
					$response    = $this->update( $payload );
					if ( $response ) {
						$automation_id = $payload['id'];
					}
				} else {
					$automation_id = $this->create( $payload );
				}

				if ( $automation_id ) {
					if ( !empty( $payload['steps'] ) && is_array( $payload['steps'] ) ) {
						$steps            = $payload['steps'];
						$updated_step_ids = array();

						foreach ( $steps as $key =>$step ) {
							if ( isset( $step['step_id'] ) ) {
								$step_data = array(
									'id'            => isset( $step['id'] ) ? $step['id'] : '',
									'automation_id' => $automation_id,
									'step_id'       => $step['step_id'],
									'key'           => $step['key'],
									'type'          => $step['type'],
									'settings'      => isset( $step['settings'] ) ? $step['settings'] : array(),
									'next_step_id'  => isset( $step['next_step_id'] ) ? $step['next_step_id'] : '',
								);
								if ( 'logical' === $step['type'] ) {
									$yes_steps                 = !empty( $step['node_data']['yes'] ) ? $step['node_data']['yes'] : array();
									$no_steps                  = !empty( $step['node_data']['no'] ) ? $step['node_data']['no'] : array();
									$step_data['next_step_id'] = array(
										'logical_next_step_id' => isset( $step['logical_next_step_id'] ) ? $step['logical_next_step_id'] : array(),
										'next_step_id' => isset( $step['next_step_id'] ) ? $step['next_step_id'] : '',
									);
									foreach ( $yes_steps as $value => $logical_step ) {
										$logical_step_data = array(
											'id'           => isset( $logical_step['id'] ) ? $logical_step['id'] : '',
											'automation_id' => $automation_id,
											'step_id'      => $logical_step['step_id'],
											'key'          => $logical_step['key'],
											'type'         => $logical_step['type'],
											'settings'     => isset( $logical_step['settings'] ) ? $logical_step['settings'] : array(),
											'next_step_id' => isset( $logical_step['next_step_id'] ) ? $logical_step['next_step_id'] : '',
										);
										if ( !empty( $logical_step['id'] ) ) {
											array_push( $updated_step_ids, $logical_step['id'] );
										}
										$logical_step_id                          = AutomationStepModel::get_instance()->create_or_update( $logical_step_data );
										$step['node_data']['yes'][ $value ]['id'] = $logical_step_id;
										$step_meta_value                          = array(
											'popover_type' => $logical_step['popover_type'],
											'parent_index' => $logical_step['parent_index'],
											'condition_type' => $logical_step['condition_type'],
										);
										HelperFunctions::update_automation_step_meta( $logical_step_id, 'conditional_data', maybe_serialize( $step_meta_value ) );
									}
									foreach ( $no_steps as $no_value => $logical_step ) {
										$logical_step_data = array(
											'id'           => isset( $logical_step['id'] ) ? $logical_step['id'] : '',
											'automation_id' => $automation_id,
											'step_id'      => $logical_step['step_id'],
											'key'          => $logical_step['key'],
											'type'         => $logical_step['type'],
											'settings'     => isset( $logical_step['settings'] ) ? $logical_step['settings'] : array(),
											'next_step_id' => isset( $logical_step['next_step_id'] ) ? $logical_step['next_step_id'] : '',
										);
										if ( !empty( $logical_step['id'] ) ) {
											array_push( $updated_step_ids, $logical_step['id'] );
										}
										$logical_step_id                            = AutomationStepModel::get_instance()->create_or_update( $logical_step_data );
										$step['node_data']['no'][ $no_value ]['id'] = $logical_step_id;
										$step_meta_value                            = array(
											'popover_type' => $logical_step['popover_type'],
											'parent_index' => $logical_step['parent_index'],
											'condition_type' => $logical_step['condition_type'],
										);
										HelperFunctions::update_automation_step_meta( $logical_step_id, 'conditional_data', maybe_serialize( $step_meta_value ) );
									}
								}
								if ( !empty( $step['id'] ) ) {
									array_push( $updated_step_ids, $step['id'] );
								}
								$step_id = AutomationStepModel::get_instance()->create_or_update( $step_data );
								if ( 'logical' === $step['type'] ) {
									HelperFunctions::update_automation_step_meta( $step_id, 'conditional_node_step', maybe_serialize( $step['node_data'] ) );
								}
							}
						}
						if ( !empty( $saved_steps ) ) {
							foreach ( $saved_steps as $saved_step ) {
								if ( isset( $saved_step['id'] ) && !in_array( $saved_step['id'], $updated_step_ids ) ) { //phpcs:ignore
									AutomationStepModel::get_instance()->destroy( $saved_step['id'] );
								}
							}
						}
					}

					if ( 'wc_customer_winback' === $payload['trigger_name'] && 'active' === $payload['status'] ) {
						$trigger = !empty( $payload['steps'] ) ? $payload['steps'][0] : array();
						$step_id = isset( $trigger['step_id'] ) ? $trigger['step_id'] : '';

						// Extract settings from the trigger.
						$current = date('Y-m-d');
						$time    = isset( $trigger['settings']['wc_customer_winback_settings']['time_to_check'] ) ? $trigger['settings']['wc_customer_winback_settings']['time_to_check'] : new \DateTime( 'now', wp_timezone() );
						$time    = $current . substr( $time, 10 );
						$time    = Mint_Pro_Helper::get_action_scheduler_starting_timestamp( $time );

						// Prepare arguments for the recurring action callback.
						$args = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_id,
							'offset'        => 0,
							'per_page'      => 20,
						);

						// Check if a scheduled action with the same automation_id exists.
						$group = 'mailmint-process-customer-win-back-' . $automation_id;
						if ( as_has_scheduled_action( 'mailmint_process_customer_win_back_daily', $args, $group ) ) {
							// Unschedule all events with the hook 'mailmint_process_customer_win_back_daily' from the group $group.
    						as_unschedule_all_actions('mailmint_process_customer_win_back_daily', $args, $group);
						}

						/**
						 * Action: mailmint_process_customer_win_back_daily
						 * 
						 * Summary: Fires when processing customer win back steps.
						 * 
						 * Description: This action is triggered when processing customer win back steps.
						 * It provides an opportunity for developers to hook into the re-engage process and perform additional actions or custom logic.
						 * 
						 * @param array $args An array containing information about the customer win back steps being processed.
						 * @since 1.12.0
						 */
						as_schedule_recurring_action( $time, DAY_IN_SECONDS, 'mailmint_process_customer_win_back_daily', $args, $group );
					}

					// Schedule events for anniversary automation.
					if ( 'mint_anniversary_reminder' === $payload['trigger_name'] && 'active' === $payload['status'] ) {
						$trigger = !empty( $payload['steps'] ) ? $payload['steps'][0] : array();
						$step_id = isset( $trigger['step_id'] ) ? $trigger['step_id'] : '';

						// Extract settings from the trigger.
						$current = date('Y-m-d');
						$time    = isset( $trigger['settings']['anniversary']['time_to_check'] ) ? $trigger['settings']['anniversary']['time_to_check'] : new \DateTime( 'now', wp_timezone() );
						$time    = $current . substr($time, 10);
						$time    = Mint_Pro_Helper::get_action_scheduler_starting_timestamp( $time );

						// Prepare arguments for the recurring action callback.
						$args = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_id,
							'offset'        => 0,
							'per_page'      => 20,
						);

						// Check if a scheduled action with the same automation_id exists.
						$group = 'mailmint-process-contact-anniversary-' . $automation_id;
						if ( as_has_scheduled_action( 'mailmint_process_contact_anniversary_daily', $args, $group ) ) {
							// Unschedule all events with the hook 'mailmint_process_contact_anniversary_daily' from the group $group.
    						as_unschedule_all_actions('mailmint_process_contact_anniversary_daily', $args, $group);
						}

						/**
						 * Action: mailmint_process_contact_anniversary_daily
						 * 
						 * Summary: Fires when processing contact anniversary steps.
						 * 
						 * Description: This action is triggered when processing contact anniversary steps.
						 * It provides an opportunity for developers to hook into the anniversary process and perform additional actions or custom logic.
						 * 
						 * @param array $args An array containing information about the contact anniversary steps being processed.
						 * @since 1.11.0
						 */
						as_schedule_recurring_action( $time, DAY_IN_SECONDS, 'mailmint_process_contact_anniversary_daily', $args, $group );
					}

					// Schedule events for WooCommerce Subscription automation.
					if ( 'wcs_subscription_before_renewal' === $payload['trigger_name'] && 'active' === $payload['status'] ) {
						$trigger = !empty( $payload['steps'] ) ? $payload['steps'][0] : array();
						$step_id = isset( $trigger['step_id'] ) ? $trigger['step_id'] : '';

						// Extract settings from the trigger.
						$current = date('Y-m-d');
						$time    = isset( $trigger['settings']['product_settings']['time_to_check'] ) ? $trigger['settings']['product_settings']['time_to_check'] : new \DateTime( 'now', wp_timezone() );
						$time    = $current . substr($time, 10);
						$time    = Mint_Pro_Helper::get_action_scheduler_starting_timestamp( $time );

						// Prepare arguments for the recurring action callback.
						$args = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_id,
							'offset'        => 0,
							'per_page'      => 20,
						);

						// Check if a scheduled action with the same automation_id exists.
						$group = 'mailmint-process-wcs-renewal-' . $automation_id;
						if ( as_has_scheduled_action( 'mailmint_process_wcs_renewal_daily', $args, $group ) ) {
							// Unschedule all events with the hook 'mailmint_process_wcs_renewal_daily' from the group $group.
    						as_unschedule_all_actions('mailmint_process_wcs_renewal_daily', $args, $group);
						}

						/**
						 * Action: mailmint_process_wcs_renewal_daily
						 * 
						 * Summary: Fires when processing WooCommerce Subscription renewal.
						 * 
						 * Description: This action is triggered when processing WooCommerce Subscription renewal.
						 * It provides an opportunity for developers to hook into the WooCommerce Subscription renewal process and perform additional actions or custom logic.
						 * 
						 * @param array $args An array containing information about the WooCommerce Subscription renewal steps being processed.
						 * @since 1.3.3
						 */
						as_schedule_recurring_action( $time, DAY_IN_SECONDS, 'mailmint_process_wcs_renewal_daily', $args, $group );
					}

					// Schedule events for WooCommerce Subscription End automation.
					if ( 'wcs_subscription_before_end' === $payload['trigger_name'] && 'active' === $payload['status'] ) {
						$trigger = !empty( $payload['steps'] ) ? $payload['steps'][0] : array();
						$step_id = isset( $trigger['step_id'] ) ? $trigger['step_id'] : '';

						// Extract settings from the trigger.
						$current = date('Y-m-d');
						$time    = isset( $trigger['settings']['product_settings']['time_to_check'] ) ? $trigger['settings']['product_settings']['time_to_check'] : new \DateTime( 'now', wp_timezone() );
						$time    = $current . substr($time, 10);
						$time    = Mint_Pro_Helper::get_action_scheduler_starting_timestamp( $time );

						// Prepare arguments for the recurring action callback.
						$args = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_id,
							'offset'        => 0,
							'per_page'      => 20,
						);

						// Check if a scheduled action with the same automation_id exists.
						$group = 'mailmint-process-wcs-end-' . $automation_id;
						if ( as_has_scheduled_action( 'mailmint_process_wcs_end_daily', $args, $group ) ) {
							// Unschedule all events with the hook 'mailmint_process_wcs_end_daily' from the group $group.
    						as_unschedule_all_actions('mailmint_process_wcs_end_daily', $args, $group);
						}

						/**
						 * Action: mailmint_process_wcs_end_daily
						 * 
						 * Summary: Fires when processing WooCommerce Subscription end.
						 * 
						 * Description: This action is triggered when processing WooCommerce Subscription end.
						 * It provides an opportunity for developers to hook into the WooCommerce Subscription end process and perform additional actions or custom logic.
						 * 
						 * @param array $args An array containing information about the WooCommerce Subscription end steps being processed.
						 * @since 1.3.3
						 */
						as_schedule_recurring_action( $time, DAY_IN_SECONDS, 'mailmint_process_wcs_end_daily', $args, $group );
					}
					return $automation_id;
				}
			}
		} catch ( \Exception $e ) {
			return false;
		}

		return false;
	}



	/**
	 * Create automation
	 *
	 * @param array $payload payload.
	 * @return int Automation id.
	 */
	public function create( $payload ) {
		try {
			global $wpdb;
			$automations_table = $wpdb->prefix . AutomationSchema::$table_name;
			$wpdb->insert(
				$automations_table,
				array(
					'name'         => $payload['name'],
					'author'       => $payload['author'],
					'trigger_name' => $payload['trigger_name'],
					'status'       => $payload['status'],
					'created_at'   => current_time( 'mysql' ),
					'updated_at'   => current_time( 'mysql' ),
				)
			); // db call ok.
			do_action( 'mailmint_'.$payload['trigger_name'].'_automation_created' );
			return $wpdb->insert_id;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Update automation
	 *
	 * @param array $payload payload.
	 * @return bool Automation id.
	 */
	private function update( array $payload ) {
		try {
			global $wpdb;
			$automations_table = $wpdb->prefix . AutomationSchema::$table_name;

			if ( isset( $payload['created_at'] ) ) {
				unset( $payload['created_at'] );
			}
			if ( isset( $payload['steps'] ) ) {
				unset( $payload['steps'] );
			}
			if ( isset( $payload['created_ago'] ) ) {
				unset( $payload['created_ago'] );
			}
			if ( isset( $payload['_locale'] ) ) {
				unset( $payload['_locale'] );
			}

			if ( isset( $payload['rest_route'] ) ) {
				unset( $payload['rest_route'] );
			}

			$payload['updated_at'] = current_time( 'mysql' );
			$updated               = $wpdb->update(
				$automations_table,
				$payload,
				array( 'ID' => $payload['id'] )
			); // db call ok. ; no-cache ok.
			do_action('mailmint_' . $payload['trigger_name'] . '_automation_updated', $payload['id']);
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
	 * @param string $order_by sorting order.
	 * @param string $order_type sorting order type.
	 * @param int    $offset offset.
	 * @param int    $limit limit.
	 * @param string $search search.
	 * @param string $status status.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all( $order_by, $order_type, $offset = 0, $limit = 10, $search = '', $status = '' ) {
		global $wpdb;
		$automation_table      = $wpdb->prefix . AutomationSchema::$table_name;
		$automation_meta_table = $wpdb->prefix . AutomationMetaSchema::$table_name;
		$search_terms          = null;
		$condition             = 'WHERE';

		// Search automation by name.
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "WHERE automation.name LIKE '%%$search%%'";
			$condition    = 'AND';
		}

		// Prepare sql results for list view.
		try {
			// Return automations in list view.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( 'all' === $status ) {
				$select_query = $wpdb->get_results( $wpdb->prepare( "SELECT automation.id,automation.name,automation.status,automation.created_at FROM $automation_table as automation LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id {$search_terms} {$condition} meta.meta_key = %s AND meta.meta_value = %s ORDER BY automation.$order_by $order_type LIMIT %d, %d", array( 'source', 'mint', $offset, $limit ) ), ARRAY_A ); // db call ok. ; no-cache ok.
				$count_query  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $automation_table as automation LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id {$search_terms} {$condition} meta.meta_key  = %s AND  meta.meta_value  = %s", array( 'source', 'mint' ) ) ); // db call ok. ; no-cache ok.
			} else {
				$select_query = $wpdb->get_results( $wpdb->prepare( "SELECT automation.id,automation.name,automation.status,automation.created_at FROM $automation_table as automation LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id {$search_terms} {$condition} meta.meta_key = %s AND meta.meta_value = %s AND automation.status = %s ORDER BY automation.$order_by $order_type LIMIT %d, %d", array( 'source', 'mint', $status, $offset, $limit ) ), ARRAY_A ); // db call ok. ; no-cache ok.
				$count_query  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $automation_table as automation LEFT JOIN $automation_meta_table AS meta ON automation.id = meta.automation_id {$search_terms} {$condition} meta.meta_key  = %s AND  meta.meta_value  = %s AND automation.status = %s", array( 'source', 'mint', $status ) ) ); // db call ok. ; no-cache ok.
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
		self::delete_child_row_by_autoamtion_id( $id );
		EmailModel::delete_scheduled_emails( 'automation_id', $id );
		return $wpdb->delete( $automation_table, array( 'id' => $id ) ); // db call ok. ; no-cache ok.
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
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;

		if ( is_array( $automation_ids ) && count( $automation_ids ) > 0 ) {
			foreach ( $automation_ids as $automation_id ) {
				EmailModel::delete_scheduled_emails( 'automation_id', $automation_id );
			}
			$automation_ids = implode( ',', array_map( 'intval', $automation_ids ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE id IN(%1s)', $automation_table, $automation_ids ) ); //  phpcs:ignore.
			self::delete_all_child_row_by_autoamtion_ids( $automation_ids );
			return $result;
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return false;
	}

	/**
	 * Check existing automation on database
	 *
	 * @param mixed $id automation id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_automation_exist( $id ) {
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $automation_table WHERE id = %d", array( $id ) );
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
	 * Run SQL query to get a automation from automation database
	 *
	 * @param int $id automation id.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_single( $id ) {
		try {
			global $wpdb;
			$automation_table = $wpdb->prefix . AutomationSchema::$table_name;
			$select_query     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_table as automation WHERE automation.id = %d", array( $id ) ), ARRAY_A ); //  phpcs:ignore
			if ( is_array( $select_query ) ) {
				foreach ( $select_query as $key =>$data ) {
					if ( isset( $data['id'] ) ) {
						$select_query[ $key ]['steps'] = HelperFunctions::get_all_automation_step_by_id( $id );
						if ( !empty( $select_query[ $key ]['steps'] ) ) {
							foreach ( $select_query[ $key ]['steps'] as $step_key => $step ) {
								$step_enterance = HelperFunctions::count_total_enterance_in_step( $step['step_id'] );
								$step_completed = HelperFunctions::count_completed_step( $step['step_id'] );
								$step_exited    = HelperFunctions::count_exited_step( $step['step_id'] );
								$select_query[ $key ]['steps'][ $step_key ]['enterance'] = $step_enterance;
								$select_query[ $key ]['steps'][ $step_key ]['completed'] = $step_completed;
								$select_query[ $key ]['steps'][ $step_key ]['exited']    = $step_exited;
							}
						}
					}
				}
			}

			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array(
				'data' => $select_query,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}


	/**
	 * Delete all row from child table by automation ids
	 *
	 * @param string $automation_ids Automation IDs.
	 * @return bool
	 */
	public static function delete_all_child_row_by_autoamtion_ids( $automation_ids ) {
		if ( $automation_ids ) {
			global $wpdb;
			$automation_meta_table = $wpdb->prefix . AutomationMetaSchema::$table_name;
			$automation_log_table  = $wpdb->prefix . AutomationLogSchema::$table_name;
			$automation_job_table  = $wpdb->prefix . AutomationJobSchema::$table_name;
			$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE automation_id IN(%1s)', $automation_meta_table, $automation_ids ) ); //  phpcs:ignore.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE automation_id IN(%1s)', $automation_log_table, $automation_ids ) ); //  phpcs:ignore.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE automation_id IN(%1s)', $automation_job_table, $automation_ids ) ); //  phpcs:ignore.
			$step_ids = AutomationStepModel::get_step_ids_by_automation_ids( $automation_ids );
			AutomationStepModel::delete_all_child_row_by_step_ids( $step_ids );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE automation_id IN(%1s)', $automation_step_table, $automation_ids ) ); //  phpcs:ignore.

			return true;
		}
		return false;
	}


	/**
	 * Delete row from child table by automation id
	 *
	 * @param int $automation_id Automation ID.
	 * @return bool
	 */
	public static function delete_child_row_by_autoamtion_id( $automation_id ) {
		if ( $automation_id ) {
			global $wpdb;
			$automation_meta_table = $wpdb->prefix . AutomationMetaSchema::$table_name;
			$automation_log_table  = $wpdb->prefix . AutomationLogSchema::$table_name;
			$automation_job_table  = $wpdb->prefix . AutomationJobSchema::$table_name;
			$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->delete( $automation_meta_table, array( 'automation_id' => $automation_id ) ); // db call ok. ; no-cache ok. //  phpcs:ignore.
			$wpdb->delete( $automation_log_table, array( 'automation_id' => $automation_id ) ); // db call ok. ; no-cache ok. //  phpcs:ignore.
			$wpdb->delete( $automation_job_table, array( 'automation_id' => $automation_id ) ); // db call ok. ; no-cache ok. //  phpcs:ignore.
			$step_ids = AutomationStepModel::get_step_ids_by_automation_id( $automation_id );
			AutomationStepModel::delete_all_child_row_by_step_ids( $step_ids );
			$wpdb->delete( $automation_step_table, array( 'automation_id' => $automation_id ) ); // db call ok. ; no-cache ok. //  phpcs:ignore.

			return true;
		}
		return false;
	}

	public static function search_automation( $params ){
		global $wpdb;
		$term  = isset($params['term']) ? $params['term'] : '';
		$table = $wpdb->prefix . AutomationSchema::$table_name;

		// Prepare the search string with wildcards for a LIKE query.
		$search = '%' . $wpdb->esc_like($term) . '%';

		// Query to fetch id as value and name as label.
		$query = $wpdb->prepare("SELECT id AS value, name AS label
			FROM {$table}
			WHERE name LIKE %s
		", $search);

		// Execute the query and return the results
		return $wpdb->get_results($query, ARRAY_A);
	}

	public static function get_automation_count(){
		global $wpdb;
		$table_name = $wpdb->prefix . AutomationSchema::$table_name;
		return absint($wpdb->get_var($wpdb->prepare('SELECT COUNT(`id`) FROM %1s', $table_name))); //phpcs:ignore
	}
}
