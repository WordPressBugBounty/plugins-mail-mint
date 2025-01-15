<?php
/**
 * Helper functions
 *
 * @package MintMail\App\Internal\Automation
 */

namespace MintMail\App\Internal\Automation;

use DateTime;
use FluentForm\App\Helpers\Helper;
use Jet_Form_Builder\Classes\Tools;
use Mint\MRM\DataBase\Models\FormModel;
use Mint\MRM\DataBase\Tables\AutomationSchema;
use Mint\MRM\DataBase\Tables\AutomationStepMetaSchema;
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use Mint\MRM\DataBase\Tables\AutomationJobSchema;
use Mint\MRM\DataBase\Tables\AutomationLogSchema;
use Mint\MRM\DataBase\Tables\AutomationMetaSchema;
use Mint\MRM\DataBase\Tables\EmailMetaSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\CampaignEmailBuilderSchema;
use MRM\Common\MrmCommon;
use MailMintPro\App\Utilities\Helper\MintAutomaticCoupon;

/**
 * Helper functions
 *
 * @package MintMail\App\Internal\Automation;
 */
class HelperFunctions { //phpcs:ignore
	/**
	 * Get all automations
	 *
	 * @param string $trigger Set Trigger Name.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all_automations_by_tigger( $trigger = '' ) {
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;
		$order_by         = 'id';
		$order_type       = 'desc';
		$results          = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_table WHERE trigger_name = %s AND status = %s ORDER BY $order_by $order_type ", $trigger, 'active' ), ARRAY_A ); // phpcs:ignore.
		if ( $results ) {
			return $results;
		}
		return false;
	}

	/**
	 * Get all automations
	 *
	 * @param string $trigger Set Trigger Name.
	 * @param int    $automation_id get automation id.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_specific_automation_by_trigger( $trigger, $automation_id ) {
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;
        $results          = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_table WHERE id =%d AND trigger_name = %s AND status = %s", $automation_id, $trigger, 'active' ), ARRAY_A ); // phpcs:ignore.
		if ( $results ) {
			return $results;
		}
		return array();
	}

	/**
	 * Update automation status.
	 *
	 * @param int    $automation_id Automation ID.
	 * @param string $status Automation status.
	 * @return bool
	 */
	public static function update_status( $automation_id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . AutomationSchema::$table_name;

		if ( $automation_id && $status ) {
			return $wpdb->update( $table_name, array( 'status' => $status ), array( 'id' => $automation_id ) ); //phpcs:ignore
		}
		return false;
	}

	/**
	 * Get all automation step
	 *
	 * @param int $automation_id Set Automation ID .
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all_automation_step_by_id( $automation_id = '' ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		global $wpdb;
		$automation_table           = $wpdb->prefix . AutomationSchema::$table_name;
		$automation_step_table      = $wpdb->prefix . AutomationStepSchema::$table_name;
		$automation_step_meta_table = $wpdb->prefix . AutomationStepMetaSchema::$table_name;
		$results                    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *, step.id FROM $automation_step_table as step LEFT JOIN $automation_step_meta_table as meta ON step.id = meta.automation_step_id WHERE step.automation_id = %d",
				$automation_id
			),
			ARRAY_A
		);
		if ( $results ) {
			foreach ( $results as $key =>$result ) {
				if ( isset( $result['settings'] ) ) {
					$results[ $key ]['settings'] = maybe_unserialize( $result['settings'] );
				}
				if ( 'logical' === $result['type'] && 'condition' === $result['key'] && isset( $result['next_step_id'] ) ) {
					$logical_next_step                       = maybe_unserialize( $result['next_step_id'] );
					$results[ $key ]['next_step_id']         = $logical_next_step['next_step_id'];
					$results[ $key ]['logical_next_step_id'] = $logical_next_step['logical_next_step_id'];
					$condition_node                          = maybe_unserialize( $result['meta_value'] );
					$condition_node_yes                      = $condition_node['yes'];
					$condition_node_no                       = $condition_node['no'];
					$results[ $key ]['node_data']            = self::condition_node_step_analysis( $condition_node_yes, $condition_node_no );
				}
			}
			$formatted_steps = self::get_formatted_steps( $automation_id, $results );
			if ( !empty( $formatted_steps ) ) {
				return $formatted_steps;
			}
			return $results;
		}
		return array();
	}

	/**
	 * Retrieve the automation steps to index for a given automation ID.
	 *
	 * @param int $automation_id The ID of the automation.
	 * @return array An array of automation steps or an empty array if no steps found.
	 * @since 1.3.1
	 */
	public static function get_automations_steps_to_index( $automation_id = '' ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		global $wpdb;
		$automation_step_table      = $wpdb->prefix . AutomationStepSchema::$table_name;
		$automation_step_meta_table = $wpdb->prefix . AutomationStepMetaSchema::$table_name;
		$results                    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *, step.id FROM $automation_step_table as step LEFT JOIN $automation_step_meta_table as meta ON step.id = meta.automation_step_id WHERE step.automation_id = %d",
				$automation_id
			),
			ARRAY_A
		);

		if ( $results ) {
			$formatted_steps = self::get_formatted_steps( $automation_id, $results );
			if ( !empty( $formatted_steps ) ) {
				return $formatted_steps;
			}
			return $results;
		}
		return array();
	}

	/**
	 * Get conditional node analytics.
	 *
	 * @param array $condition_node_yes Get Node Yes step.
	 * @param array $condition_node_no Get Node No Step.
	 * @return array[]
	 */
	public static function condition_node_step_analysis( $condition_node_yes, $condition_node_no ) {
		if ( !empty( $condition_node_no ) ) {
			foreach ( $condition_node_no as $step_key => $step ) {
				$step_enterance                              = self::count_total_enterance_in_step( $step['step_id'] );
				$step_completed                              = self::count_completed_step( $step['step_id'] );
				$step_exited                                 = self::count_exited_step( $step['step_id'] );
				$condition_node_no[ $step_key ]['enterance'] = $step_enterance;
				$condition_node_no[ $step_key ]['completed'] = $step_completed;
				$condition_node_no[ $step_key ]['exited']    = $step_exited;
			}
		}
		if ( !empty( $condition_node_yes ) ) {
			foreach ( $condition_node_yes as $step_key => $step ) {
				$step_enterance                               = self::count_total_enterance_in_step( $step['step_id'] );
				$step_completed                               = self::count_completed_step( $step['step_id'] );
				$step_exited                                  = self::count_exited_step( $step['step_id'] );
				$condition_node_yes[ $step_key ]['enterance'] = $step_enterance;
				$condition_node_yes[ $step_key ]['completed'] = $step_completed;
				$condition_node_yes[ $step_key ]['exited']    = $step_exited;
			}
		}

		$node_data = array(
			'yes' => $condition_node_yes,
			'no'  => $condition_node_no,
		);
		return $node_data;
	}

	/**
	 * Get All step by Automation ID.
	 *
	 * @param int $automation_id Get automaiton ID .
	 * @return array|object|\stdClass[]|null
	 */
	public static function get_all_step_by_automation_id( $automation_id = '' ) {
		global $wpdb;
		$automation_table           = $wpdb->prefix . AutomationSchema::$table_name;
		$automation_step_table      = $wpdb->prefix . AutomationStepSchema::$table_name;
		$automation_step_meta_table = $wpdb->prefix . AutomationStepMetaSchema::$table_name;
		$results                    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *, step.id FROM $automation_step_table as step LEFT JOIN $automation_step_meta_table as meta ON step.id = meta.automation_step_id WHERE step.automation_id = %d",
				$automation_id
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Get steps with proper formation
	 *
	 * @param int   $automation_id Get Autamation ID.
	 * @param array $steps Get all Steps.
	 *
	 * @return array
	 */
	public static function get_formatted_steps( $automation_id, $steps ) {
		if ( $steps ) {
			$first_step = self::get_next_step( $automation_id );
			if ( $first_step ) {
				$formatted_steps = array();
				$key             = array_search( $first_step['step_id'], array_column( $steps, 'step_id' ) ); //phpcs:ignore
				if ( false !== $key ) {
					array_push( $formatted_steps, $steps[ $key ] );
				}

				$current_setp = $first_step['step_id'];
				for ( $i =0; $i < count( $steps ); $i++ ) { //phpcs:ignore
					$next_step = self::get_next_step( $automation_id, $current_setp ); //phpcs:ignore
					if ( is_array( $next_step ) && isset( $next_step['step_id'] ) ) {
						$key   = array_search( $next_step['step_id'], array_column( $steps, 'step_id' ) ); //phpcs:ignore
						$index = array_search( $next_step['step_id'], array_column( $formatted_steps, 'step_id' ) ); //phpcs:ignore
						if ( false !== $key && false === $index ) {
							array_push( $formatted_steps, $steps[ $key ] );
							$current_setp = $next_step['step_id'];
						}
					}
				}
				return $formatted_steps;
			}
		}
		return array();
	}

	/**
	 * Get next step
	 *
	 * @param int    $automation_id automation id.
	 * @param string $step_id automation id.
	 *
	 * @since 1.0.0
	 */
	public static function get_next_step( $automation_id, $step_id = '' ) {
		if ( $automation_id ) {
			global $wpdb;
			if ( $step_id ) {
				$automation_table      = $wpdb->prefix . AutomationSchema::$table_name;
				$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
				$results               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d and step.step_id = %s", $automation_id, $step_id ), ARRAY_A ); // phpcs:ignore.
				if ( is_array( $results ) && isset( $results[0] ) ) {
					if ( 'condition' === $results[0]['key'] ) {
						$next_step_data = maybe_unserialize( $results[0]['next_step_id'] );
						$step_data      = self::get_step_data( $automation_id, $next_step_data['next_step_id'] );
					} else {
						$step_data = self::get_step_data( $automation_id, $results[0]['next_step_id'] );
					}
					if ( is_array( $step_data ) && !empty( $step_data ) ) {
						$condition_step = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_data['step_id'],
							'step_type'     => $step_data['step_type'],
							'key'           => $step_data['key'],
						);
						return $condition_step;
					}
					return '';
				}
			} else {
				$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
				$results               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d AND step.type = %s", $automation_id ,'trigger'), ARRAY_A ); // phpcs:ignore.
				if ( $results ) {
					if ( is_array( $results ) ) {
						foreach ( $results as $step ) {
							if ( isset( $step['step_id'] ) ) {
								return array(
									'automation_id' => $automation_id,
									'step_id'       => $step['step_id'],
									'step_type'     => $step['type'],
									'key'           => $step['key'],
								);
							}
						}
					}
				}
			}
		}
		return false;
	}

	/**
	 * Get next step after logical step.
	 *
	 * @param int     $automation_id automation id.
	 * @param boolean $maybe_matched rule match.
	 * @param string  $step_id step id.
	 *
	 * @since 1.0.0
	 */
	public static function get_next_step_after_logical_step( $automation_id, $maybe_matched, $step_id = '' ) {
		if ( $automation_id ) {
			global $wpdb;
			if ( $step_id ) {
				$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
				$results               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d and step.step_id = %s", $automation_id, $step_id ), ARRAY_A ); // phpcs:ignore.
				if ( is_array( $results ) && isset( $results[0] ) ) {
					if ( 'condition' === $results[0]['key'] ) {
						$next_step_data = maybe_unserialize( $results[0]['next_step_id'] );
						$condition_type = $maybe_matched ? 'yes' : 'no';
						$step_data      = self::get_step_data( $automation_id, $next_step_data['logical_next_step_id'][ $condition_type ] );
					}
					if ( is_array( $step_data ) && !empty( $step_data ) ) {
						$condition_step = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_data['step_id'],
							'step_type'     => $step_data['step_type'],
							'key'           => $step_data['key'],
						);
						return $condition_step;
					}
					return '';
				}
			} else {
				$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
				$results               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d", $automation_id ), ARRAY_A ); // phpcs:ignore.
				if ( $results ) {
					if ( is_array( $results ) ) {
						foreach ( $results as $step ) {
							if ( isset( $step['step_id'] ) ) {
								$key = array_search( $step['step_id'], array_column( $results, 'next_step_id' ) ); //phpcs:ignore
								if ( false === $key ) {
									return array(
										'automation_id' => $automation_id,
										'step_id'       => $step['step_id'],
										'step_type'     => $step['type'],
										'key'           => $step['key'],
									);
								}
							}
						}
					}
				}
			}
		}
		return false;
	}


	/**
	 * Get next step
	 *
	 * @param int    $automation_id automation id.
	 * @param string $step_id automation id.
	 *
	 * @since 1.0.0
	 */
	public static function get_prev_step( $automation_id, $step_id = '' ) {
		if ( $automation_id ) {
			global $wpdb;
			if ( $step_id ) {
				$automation_table      = $wpdb->prefix . AutomationSchema::$table_name;
				$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
				$results               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d and step.next_step_id = %s", $automation_id, $step_id ), ARRAY_A ); // phpcs:ignore.
				if ( is_array( $results ) && isset( $results[0] ) ) {
					if ( 'condition' === $results[0]['key'] ) {
						$next_step_data = maybe_unserialize( $results[0]['next_step_id'] );
						$step_data      = self::get_step_data( $automation_id, $next_step_data['next_step_id'] );
					} else {
						$step_data = self::get_step_data( $automation_id, $results[0]['step_id'] );
					}

					if ( is_array( $step_data ) && !empty( $step_data ) ) {
						return array(
							'automation_id' => $automation_id,
							'step_id'       => $step_data['step_id'],
							'step_type'     => $step_data['step_type'],
							'key'           => $step_data['key'],
						);
					}

					return '';
				}
			}
		}
		return false;
	}


	/**
	 * Get step key
	 *
	 * @param int $automation_id Automation ID.
	 * @param int $step_id Step ID.
	 */
	public static function get_step_data( $automation_id, $step_id ) {
		global $wpdb;
		$automation_table      = $wpdb->prefix . AutomationSchema::$table_name;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		$results               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d and step.step_id = %s", $automation_id, $step_id ), ARRAY_A );  // phpcs:ignore.
		if ( is_array( $results ) && isset( $results[0] ) ) {
			return array(
				'automation_id' => $automation_id,
				'step_id'       => $results[0]['step_id'],
				'step_type'     => $results[0]['type'],
				'key'           => $results[0]['key'],
				'settings'      => maybe_unserialize( $results[0]['settings'] ),
				'next_step_id'  => $results[0]['next_step_id'],
			);
		}
		return false;
	}


	/**
	 * Get step key
	 *
	 * @param int $automation_id Automation ID.
	 * @param int $step_ids Step ID.
	 */
	public static function get_conditional_next_step_data( $automation_id, $step_ids ) {
		global $wpdb;
		$automation_table      = $wpdb->prefix . AutomationSchema::$table_name;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		$setp_data             = array();
		$step_ids              = maybe_unserialize( $step_ids );
		if ( is_array( $step_ids ) ) {
			if ( !empty( $step_ids['logical_next_step_id']['yes'] ) ) {
				$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d and step.step_id = %s", $automation_id, $step_ids['logical_next_step_id']['yes'] ), ARRAY_A ); // phpcs:ignore.
				if ( is_array( $results ) && isset( $results[0] ) ) {
					$setp_data['node_data']['yes'] = array(
						'automation_id' => $automation_id,
						'step_id'       => $results[0]['step_id'],
						'step_type'     => $results[0]['type'],
						'key'           => $results[0]['key'],
						'settings'      => maybe_unserialize( $results[0]['settings'] ),
						'next_step_id'  => $results[0]['next_step_id'],
					);
				}
			}

			if ( !empty( $step_ids['logical_next_step_id']['no'] ) ) {
				$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.automation_id = %d and step.step_id = %s", $automation_id, $step_ids['logical_next_step_id']['no'] ), ARRAY_A ); // phpcs:ignore.
				if ( is_array( $results ) && isset( $results[0] ) ) {
					$setp_data['node_data']['no'] = array(
						'automation_id' => $automation_id,
						'step_id'       => $results[0]['step_id'],
						'step_type'     => $results[0]['type'],
						'key'           => $results[0]['key'],
						'next_step_id'  => $results[0]['next_step_id'],
					);
				}
			}

			return $setp_data;
		}

		return false;
	}


	/**
	 * Update or insert job
	 *
	 * @param int $automation_id Automation ID.
	 * @param int $next_step_id Automation Next Step ID.
	 * @param int $status Automation Status.
	 */
	public static function update_job( $automation_id, $next_step_id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . AutomationJobSchema::$table_name;
		$results    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name as job WHERE job.automation_id = %d", $automation_id ), ARRAY_A ); // phpcs:ignore.
		if ( count( $results ) ) {
			$payload    = array(
				'next_step_id' => $next_step_id,
				'status'       => $status,
				'updated_at'   => current_time( 'mysql' ),
			);
			$table_name = $wpdb->prefix . AutomationJobSchema::$table_name;
			return $wpdb->update( $table_name, $payload, array( 'automation_id' => $automation_id ) ); // db call ok. ; no-cache ok.

		} else {
			global $wpdb;
			$table_name           = $wpdb->prefix . AutomationJobSchema::$table_name;
			$job['automation_id'] = $automation_id;
			$job['next_step_id']  = $next_step_id;
			$job['created_at']    = current_time( 'mysql' );
			$job['updated_at']    = current_time( 'mysql' );
			$inserted             = $wpdb->insert( $table_name, $job ); // db call ok. ; no-cache ok.
			if ( $inserted ) {
				return true;
			}
		}
	}

	/**
	 * Count steps by automation id
	 *
	 * @param int $id automation id.
	 * @return int
	 * @since 2.0.0
	 */
	public static function count_step_by_automation_id( $id ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		$rowcount              = $wpdb->get_var( "SELECT COUNT(*) FROM {$automation_step_table} WHERE automation_id = {$id}" ); //phpcs:ignore
		return $rowcount;
	}


	/**
	 * Update or insert log
	 *
	 * @param array $payload payload.
	 * @return void
	 * @since 1.0.0
	 */
	public static function update_log( $payload ) {
		if ( isset( $payload['automation_id'], $payload['step_id'], $payload['email'], $payload['status'], $payload['identifier'] ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . AutomationLogSchema::$table_name;
			$results    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} as log WHERE log.email = %s AND log.step_id = %s AND log.identifier = %s", $payload['email'], $payload['step_id'],$payload['identifier'] ), ARRAY_A ); // phpcs:ignore.
			if ( count( $results ) ) {
				if ( isset( $payload['created_at'] ) ) {
					unset( $payload['created_at'] );
				}
				$payload['id']         = !empty( $results[0]['id'] ) ? $results[0]['id'] : '';
				$payload['count']      = 1;
				$payload['updated_at'] = current_time( 'mysql' );

				$wpdb->update(
					$table_name,
					$payload,
					array( 'ID' => $payload['id'] )
				); // db call ok. ; no-cache ok.

			} else {
				$wpdb->insert(
					$table_name,
					array(
						'automation_id' => $payload['automation_id'],
						'step_id'       => $payload['step_id'],
						'email'         => $payload['email'],
						'count'         => 1,
						'status'        => $payload['status'],
						'identifier'    => isset( $payload['identifier'] ) ? $payload['identifier'] : null,
						'created_at'    => current_time( 'mysql' ),
						'updated_at'    => current_time( 'mysql' ),
					)
				); // db call ok.
			}
		}
	}

	/**
	 * Check if wc is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function is_wc_active() {
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { //phpcs:ignore
			return true;
		} elseif ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Check if wpfunnels is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function is_wpf_active() {
		$is_wpf_pro_activated = apply_filters( 'is_wpf_pro_active', false );
		return $is_wpf_pro_activated;
	}

	/**
	 * Check if wpfunnels is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function is_wpf_free_pro_active() {
		$is_wpf_pro_activated  = apply_filters( 'is_wpf_pro_active', false );
		$is_wpf_free_activated = false;
        if ( in_array( 'wpfunnels/wpfnl.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { //phpcs:ignore
			$is_wpf_free_activated = true;
		} elseif ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'wpfunnels/wpfnl.php' ) ) {
				$is_wpf_free_activated = true;
			}
		}
		if ( $is_wpf_pro_activated && $is_wpf_free_activated ) {
			return true;
		}
		return false;
	}

	/**
	 * Get automation id by stepID.
	 *
	 * @param string $id StepID.
	 * @return mix
	 * @since 1.0.0
	 */
	public static function get_automation_id_by_step_id( $id ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		$results               = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $automation_step_table as step WHERE step.id = %d", $id ), ARRAY_A ); // phpcs:ignore.
		if ( $results ) {
			foreach ( $results as $key =>$result ) {
				if ( isset( $result['automation_id'] ) ) {
					return $result['automation_id'];
				}
			}
		}
		return false;
	}


	/**
	 * Count the number of completed automations with a specific ID.
	 *
	 * This function retrieves the automation log entries associated with the provided ID and counts the number
	 * of completed automations based on the log entries and automation steps.
	 *
	 * @param int $id The ID of the automation.
	 * @return int The count of completed automations.
	 * @since 1.0.0
	 */
	public static function count_completed_automation( $id ) {
		global $wpdb;
		$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;

		$select_query = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT log.email, log.identifier
				FROM $automation_log_table AS log
				WHERE log.automation_id = %s
				GROUP BY log.identifier",
				$id
			),
			ARRAY_A
		);

		$completed = 0;

		if ( is_array( $select_query ) ) {
			$identifier_list = array_column( $select_query, 'identifier' );

			$steps     = self::get_automations_steps_to_index( $id );
			$steps_yes = 0;
			$steps_no  = 0;
			foreach ( $steps as $step ) {
				if ( 'logical' === $step['type'] && 'condition' === $step['key'] && isset( $step['next_step_id'] ) ) {
					$condition_node = maybe_unserialize( $step['meta_value'] );
					$steps_yes      = count( $condition_node['yes'] );
					$steps_no       = count( $condition_node['no'] );
				}
			}

			if ( !empty( $identifier_list ) ) {
				$count_query = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT log.identifier, COUNT(log.id) as count
						FROM $automation_log_table AS log
						WHERE log.automation_id = %s
						AND log.identifier IN (" . implode( ',', array_fill( 0, count( $identifier_list ), '%s' ) ) . ')
						AND log.status = %s
						GROUP BY log.identifier',
						array_merge( array( $id ), $identifier_list, array( 'completed' ) )
					),
					ARRAY_A
				);

				if ( is_array( $count_query ) ) {
					foreach ( $count_query as $data ) {
						if ( isset( $data['identifier'] ) ) {
							$total_steps_yes = count( $steps ) + $steps_yes;
							$total_steps_no  = count( $steps ) + $steps_no;
							if ( $data['count'] == $total_steps_yes || $data['count'] == $total_steps_no ) { //  phpcs:ignore
								$completed++;
							}
						}
					}
				}
			}
		}

		return $completed;
	}


	/**
	 * Count the total number of unique entrances for a specific automation.
	 *
	 * @param int $id The ID of the automation.
	 *
	 * @return int The total number of unique entrances.
	 * @since 1.3.1
	 */
	public static function count_total_enterance( $id ) {
		global $wpdb;
		$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(sub.count) AS total_count
				FROM (
					SELECT COUNT(DISTINCT log.identifier) AS count
					FROM $automation_log_table AS log
					WHERE log.automation_id = %s
					GROUP BY log.email
				) AS sub",
				$id
			)
		);

		$enterance = $result ? intval( $result ) : 0;
		return $enterance;
	}


	/**
	 * Count exited autoamtion
	 *
	 * @param string $id Automation ID.
	 */
	public static function count_exited_automation( $id ) {
		$total_enter     = self::count_total_enterance( $id );
		$total_completed = self::count_completed_automation( $id );
		$exited          = $total_enter - $total_completed;
		return $exited;
	}



	/**
	 * Count completed step
	 *
	 * @param string $step_id Automation Step ID.
	 */
	public static function count_completed_step( $step_id ) {
		global $wpdb;
		$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;
		$select_query     = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT( DISTINCT log.identifier) AS count FROM $automation_log_table AS log WHERE log.step_id = %s AND status = %s GROUP BY log.email ORDER BY count DESC", $step_id, 'completed' ), ARRAY_A ); //  phpcs:ignore
		$completed            = 0;
		if ( is_array( $select_query ) ) {
			foreach ( $select_query as $key =>$data ) {
				if ( isset( $data['count'] ) ) {
					$completed = $completed + $data['count'];
				}
			}
		}
		return $completed;
	}


	/**
	 * Count total enterance in step
	 *
	 * @param string $step_id Automation Step ID.
	 */
	public static function count_total_enterance_in_step( $step_id ) {
		global $wpdb;
		$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;
		$status               = array(
			'hold',
			'completed',
		);
		$select_query     = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT( DISTINCT log.identifier) AS count FROM $automation_log_table AS log WHERE log.step_id = %s AND log.status IN (" . self::escape_array($status) . ") GROUP BY log.email ORDER BY count DESC", $step_id ), ARRAY_A ); //  phpcs:ignore
		$enterance            = 0;
		if ( is_array( $select_query ) ) {
			foreach ( $select_query as $key =>$data ) {
				if ( isset( $data['count'] ) ) {
					$enterance = $enterance + $data['count'];
				}
			}
		}
		return $enterance;
	}


	/**
	 * Count exited autoamtion email
	 *
	 * @param string $step_id Automation Step ID.
	 */
	public static function count_exited_step( $step_id ) {
		global $wpdb;
		$exited          = 0;
		$total_completed = self::count_completed_step( $step_id );
		$enterance       = self::count_total_enterance_in_step( $step_id );
		$exited          = $enterance - $total_completed;
		return $exited;
	}


	/**
	 * Count sent autoamtion email
	 *
	 * @param string $step_id Automation Step ID.
	 */
	public static function count_sent_mail( $step_id ) {
		global $wpdb;
		$table        = $wpdb->prefix . EmailSchema::$table_name;
		$select_query     = $wpdb->get_results( $wpdb->prepare( "SELECT mail.id AS id FROM $table AS mail WHERE mail.step_id = %s AND mail.status = %s", $step_id, 'sent' ), ARRAY_A ); //  phpcs:ignore
		$count        = 0;
		if ( is_array( $select_query ) ) {
			$count = count( $select_query );
		}
		return $count;
	}


	/**
	 * Count opened autoamtion email
	 *
	 * @param string $step_id Automation Step ID.
	 */
	public static function count_opend_mail( $step_id ) {
		global $wpdb;
		$table        = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table   = $wpdb->prefix . EmailMetaSchema::$table_name;
		$select_query = $wpdb->get_results( $wpdb->prepare( "SELECT mail.id AS id FROM $table AS mail INNER JOIN $meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.step_id = %s AND mail_meta.meta_key = %s", $step_id, "is_open" ), ARRAY_A ); //  phpcs:ignore
		$opend        = 0;
		if ( is_array( $select_query ) ) {
			$opend = count( $select_query );
		}
		return $opend;
	}


	/**
	 * Count clicked autoamtion email
	 *
	 * @param string $step_id Automation Step ID.
	 */
	public static function count_clicked_mail( $step_id ) {
		global $wpdb;
		$table        = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table   = $wpdb->prefix . EmailMetaSchema::$table_name;
		$select_query = $wpdb->get_results( $wpdb->prepare( "SELECT mail.id AS id FROM $table AS mail INNER JOIN $meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.step_id = %s AND mail_meta.meta_key = %s", $step_id, "is_click" ), ARRAY_A ); //  phpcs:ignore
		$clicked      = 0;
		if ( is_array( $select_query ) ) {
			$clicked = count( $select_query );
		}
		return $clicked;
	}


	/**
	 * Update/insert automation meta.
	 *
	 * @param string $automation_id Automation ID.
	 * @param string $meta_key Meta Key.
	 * @param string $meta_value Meta value.
	 * @since 1.2.7
	 */
	public static function update_automation_meta( $automation_id, $meta_key, $meta_value ) {
		global $wpdb;
		$automation_meta_table = $wpdb->prefix . AutomationMetaSchema::$table_name;
		$select_query          = $wpdb->prepare( "SELECT * FROM $automation_meta_table WHERE automation_id = %d AND meta_key = %s", array( $automation_id, $meta_key ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		if ( is_array( $results ) && !empty( $results ) ) {
			try {
				$payload               = array(
					'id'         => isset( $results[0]->id ) ? $results[0]->id : '',
					'meta_key'   => $meta_key, // phpcs:ignore.
					'meta_value' => $meta_value, // phpcs:ignore.
				);
				$payload['updated_at'] = current_time( 'mysql' );
				$updated               = $wpdb->update(
					$automation_meta_table,
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
		} else {
			try {
				$wpdb->insert(
					$automation_meta_table,
					array(
						'automation_id' => $automation_id,
						'meta_key'      => $meta_key, // phpcs:ignore.
						'meta_value'    => $meta_value, // phpcs:ignore.
						'created_at'    => current_time( 'mysql' ),
						'updated_at'    => current_time( 'mysql' ),
					)
				); // db call ok.
				return $wpdb->insert_id;
			} catch ( \Exception $e ) {
				return false;
			}
		}
	}


	/**
	 * Update/insert automation meta.
	 *
	 * @param string $automation_id Automation ID.
	 * @param string $meta_key Meta Key.
	 * @since 1.2.7
	 */
	public static function get_automation_meta( $automation_id, $meta_key ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		global $wpdb;
		$automation_meta_table = $wpdb->prefix . AutomationMetaSchema::$table_name;
		$select_query          = $wpdb->prepare( "SELECT meta_key,meta_value FROM $automation_meta_table WHERE automation_id = %d AND meta_key = %s", array( $automation_id, $meta_key ) );
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		return $results;
	}

	/**
	 * Update/insert automation meta.
	 *
	 * @param string $automation_step_id Automation ID.
	 * @param string $meta_key Meta Key.
	 * @since 1.2.7
	 */
	public static function get_automation_step_meta( $automation_step_id, $meta_key ) {
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		global $wpdb;
		$automation_step_meta_table = $wpdb->prefix . AutomationStepMetaSchema::$table_name;
		$select_query               = $wpdb->prepare( "SELECT * FROM $automation_step_meta_table WHERE automation_step_id = %d AND meta_key = %s", array( $automation_step_id, $meta_key ) );

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_row( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		return $results;
	}

	/**
	 * Update/insert automation meta.
	 *
	 * @param string $automation_step_id Automation ID.
	 * @param string $meta_key Meta Key.
	 * @param string $meta_value Meta value.
	 */
	public static function update_automation_step_meta( $automation_step_id, $meta_key, $meta_value ) {
		global $wpdb;
		$automation_step_meta_table = $wpdb->prefix . AutomationStepMetaSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared

		$select_query = $wpdb->prepare( "SELECT * FROM $automation_step_meta_table WHERE automation_step_id = %d AND meta_key = %s", array( $automation_step_id, $meta_key ) );
		$results      = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.

		if ( is_array( $results ) && !empty( $results ) ) {
			try {
				$payload               = array(
					'id'         => isset( $results[0]->id ) ? $results[0]->id : '',
					'meta_key'   => $meta_key, // phpcs:ignore.
					'meta_value' => $meta_value, // phpcs:ignore.
				);
				$payload['updated_at'] = current_time( 'mysql' );
				$updated               = $wpdb->update(
					$automation_step_meta_table,
					$payload,
					array( 'ID' => $payload['id'] )
				); // db call ok. ; no-cache ok.

				if ( $updated ) {
					$updated_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT ID FROM $automation_step_meta_table WHERE ID = %d",
							$payload['id']
						)
					);
					return $updated_id;
				} else {
					return false;
				}
			} catch ( \Exception $e ) {
				return false;
			}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		} else {
			try {
				$wpdb->insert(
					$automation_step_meta_table,
					array(
						'automation_step_id' => $automation_step_id,
						'meta_key'      => $meta_key, // phpcs:ignore.
						'meta_value'    => $meta_value, // phpcs:ignore.
						'created_at'         => current_time( 'mysql' ),
						'updated_at'         => current_time( 'mysql' ),
					)
				); // db call ok.
				return $wpdb->insert_id;
			} catch ( \Exception $e ) {
				return false;
			}
		}
	}


	/**
	 * Count total completed subscriber
	 *
	 * @param int    $id Automation ID.
	 * @param string $filter Filter by Week/Month/Year.
	 */
	public static function count_completed_subscribers( $id, $filter ) {
		global $wpdb;
		$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;
		$day                  = 7;
		if ( 'weekly' === $filter ) {
			$day = 7;
		} elseif ( 'monthly' === $filter ) {
			$day = 30;
		} elseif ( 'yearly' === $filter ) {
			$day = 365;
		}
		$select_query     = $wpdb->get_results( $wpdb->prepare( "SELECT log.email, log.identifier as identifier FROM $automation_log_table AS log WHERE log.automation_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY) GROUP BY log.identifier ORDER BY count DESC", $id, $day ), ARRAY_A ); //  phpcs:ignore
		$completed    = 0;
		if ( is_array( $select_query ) ) {
			foreach ( $select_query as $key =>$data ) {
				if ( isset( $data['identifier'] ) ) {
					$count_query     = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT( log.id ) as count FROM $automation_log_table AS log WHERE log.automation_id = %s AND log.identifier = %s AND log.status = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY) ", $id, $data['identifier'], 'completed', $day ), ARRAY_A ); //  phpcs:ignore
					$steps       = self::get_all_automation_step_by_id( $id );
					$steps_yes   = 0;
					$steps_no    = 0;
					foreach ( $steps as $step ) {
						if ( 'logical' === $step['type'] && 'condition' === $step['key'] && isset( $step['next_step_id'] ) ) {
							$condition_node = maybe_unserialize( $step['meta_value'] );
							$steps_yes      = count( $condition_node['yes'] );
							$steps_no       = count( $condition_node['no'] );
						}
					}
					if ( is_array( $count_query ) && isset( $count_query[0]['count'] ) && is_array( $steps ) ) {
						$total_steps_yes = count( $steps ) + $steps_yes;
						$total_steps_no  = count( $steps ) + $steps_no;
                        if ( $count_query[0]['count'] == $total_steps_yes || $count_query[0]['count'] == $total_steps_no ) { //phpcs:ignore
							$completed ++;
						}
					}
				}
			}
		}
		return $completed;
	}

	/**
	 * Count total email sent to subscribers
	 *
	 * @param int    $id Automation ID.
	 * @param string $filter Filter by Week/Month/Year.
	 */
	public static function count_total_email_sent( $id, $filter ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		$result      = array();

		$day = 7;
		if ( 'weekly' === $filter ) {
			$day = 7;
		} elseif ( 'monthly' === $filter ) {
			$day = 30;
		} elseif ( 'yearly' === $filter ) {
			$day = 365;
		}
		$select_query_sent = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(`id`) as total_sent FROM $email_table WHERE automation_id = %d AND email_type = %s AND status = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY) ORDER BY created_at DESC", $id, 'automation', 'sent', $day ), ARRAY_A ); //phpcs:ignore
		if ( is_array( $select_query_sent ) ) {
			foreach ( $select_query_sent as $key =>$data ) {
				$result = $data;
			}
		}
		return $result;
	}

	/**
	 * Count total enterance with filter
	 *
	 * @param int    $id Automation ID.
	 * @param string $filter Filter by Week/Month/Year.
	 */
	public static function count_total_entrance_with_filter( $id, $filter ) {
		global $wpdb;
		$automation_log_table = $wpdb->prefix . AutomationLogSchema::$table_name;
		$day                  = 7;
		if ( 'weekly' === $filter ) {
			$day = 7;
		} elseif ( 'monthly' === $filter ) {
			$day = 30;
		} elseif ( 'yearly' === $filter ) {
			$day = 365;
		}
		$select_query     = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT( DISTINCT log.identifier) AS count FROM $automation_log_table AS log WHERE log.automation_id = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY) GROUP BY log.email ORDER BY count DESC", $id, $day ), ARRAY_A ); //  phpcs:ignore
		$enterance    = 0;
		if ( is_array( $select_query ) ) {
			foreach ( $select_query as $key =>$data ) {
				if ( isset( $data['count'] ) ) {
					$enterance = $enterance + $data['count'];
				}
			}
		}
		return $enterance;
	}

	/**
	 * Count total email sent
	 *
	 * @param int    $id Automation ID.
	 * @param string $filter Filter by Week/Month/Year.
	 */
	public static function count_performance_data( $id, $filter ) {
		global $wpdb;

		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$result = array();

		if ( 'weekly' === $filter ) {
			$select_query_sent  = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(`id`) as total_sent, DATE(created_at) as created_at FROM $email_table WHERE automation_id = %d AND email_type = %s AND status = %s AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY created_at DESC", $id, 'automation', 'sent' ), ARRAY_A ); //  phpcs:ignore
			$select_query_open  = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(mail.id) AS total_open, DATE(mail_meta.created_at) as created_at FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.automation_id = %d AND mail_meta.meta_key = %s AND mail_meta.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(mail_meta.created_at)", $id, "is_open" ), ARRAY_A ); //  phpcs:ignore
			$select_query_click = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(mail.id) AS total_click, DATE(mail_meta.created_at) as created_at FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.automation_id = %d AND mail_meta.meta_key = %s  AND mail_meta.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(mail_meta.created_at)", $id, "is_click" ), ARRAY_A ); //  phpcs:ignore
			$result             = array(
				$select_query_sent,
				$select_query_open,
				$select_query_click,
			);

			$result = array_merge( $select_query_sent, $select_query_open, $select_query_click );

			$arr = array();

			foreach ( $result as $item ) {
				if ( !isset( $arr[ $item['created_at'] ]['total_sent'] ) && isset( $item['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = $item['total_sent'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_sent'] ) && 0 === $arr[ $item['created_at'] ]['total_sent'] && isset( $item['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = $item['total_sent'];
				}

				if ( !isset( $arr[ $item['created_at'] ]['total_open'] ) && isset( $item['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = $item['total_open'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_open'] ) && 0 === $arr[ $item['created_at'] ]['total_open'] && isset( $item['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = $item['total_open'];
				}

				if ( !isset( $arr[ $item['created_at'] ]['total_click'] ) && isset( $item['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = $item['total_click'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_click'] ) && 0 === $arr[ $item['created_at'] ]['total_click'] && isset( $item['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = $item['total_click'];
				}
			}

			return $arr;
		} elseif ( 'monthly' === $filter ) {
			$select_query_sent  = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(`id`) as total_sent, DATE(created_at) as created_at FROM $email_table WHERE automation_id = %d AND email_type = %s AND status = %s AND created_at < CURDATE()+1 AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY created_at DESC", $id, 'automation', 'sent' ), ARRAY_A ); //  phpcs:ignore
			$select_query_open  = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(mail.id) AS total_open, DATE(mail_meta.created_at) as created_at FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.automation_id = %d AND mail_meta.meta_key = %s AND mail_meta.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(mail_meta.created_at)", $id, "is_open" ), ARRAY_A ); //  phpcs:ignore
			$select_query_click = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(mail.id) AS total_click, DATE(mail_meta.created_at) as created_at FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.automation_id = %d AND mail_meta.meta_key = %s AND mail_meta.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(mail_meta.created_at)", $id, "is_click" ), ARRAY_A ); //  phpcs:ignore

			$result = array(
				$select_query_sent,
				$select_query_open,
				$select_query_click,
			);

			$result = array_merge( $select_query_sent, $select_query_open, $select_query_click );

			$arr = array();

			foreach ( $result as $item ) {
				if ( !isset( $arr[ $item['created_at'] ]['total_sent'] ) && isset( $item['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = $item['total_sent'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_sent'] ) && 0 === $arr[ $item['created_at'] ]['total_sent'] && isset( $item['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = $item['total_sent'];
				}

				if ( !isset( $arr[ $item['created_at'] ]['total_open'] ) && isset( $item['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = $item['total_open'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_open'] ) && 0 === $arr[ $item['created_at'] ]['total_open'] && isset( $item['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = $item['total_open'];
				}

				if ( !isset( $arr[ $item['created_at'] ]['total_click'] ) && isset( $item['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = $item['total_click'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_click'] ) && 0 === $arr[ $item['created_at'] ]['total_click'] && isset( $item['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = $item['total_click'];
				}
			}

			return $arr;
		} elseif ( 'yearly' === $filter ) {
			$select_query_sent  = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(`id`) as total_sent, DATE(created_at) as created_at FROM $email_table WHERE automation_id = %d AND email_type = %s AND status = %s AND created_at < CURDATE()+1 AND created_at > DATE_SUB(NOW(), INTERVAL 365 DAY) GROUP BY DATE(created_at) ORDER BY created_at DESC", $id, 'automation', 'sent' ), ARRAY_A ); //  phpcs:ignore
			$select_query_open  = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(mail.id) AS total_open, DATE(mail_meta.created_at) as created_at FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.automation_id = %d AND mail_meta.meta_key = %s  AND mail_meta.created_at > DATE_SUB(NOW(), INTERVAL 365 DAY) GROUP BY DATE(mail_meta.created_at)", $id, "is_open" ), ARRAY_A ); //  phpcs:ignore
			$select_query_click = $wpdb->get_results( $wpdb->prepare( "SELECT COUNT(mail.id) AS total_click, DATE(mail_meta.created_at) as created_at FROM $email_table AS mail INNER JOIN $email_meta_table AS mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.automation_id = %d AND mail_meta.meta_key = %s AND mail_meta.created_at > DATE_SUB(NOW(), INTERVAL 365 DAY) GROUP BY DATE(mail_meta.created_at)", $id, "is_click" ), ARRAY_A ); //  phpcs:ignore

			$result = array(
				$select_query_sent,
				$select_query_open,
				$select_query_click,
			);

			$result = array_merge( $select_query_sent, $select_query_open, $select_query_click );

			$arr = array();

			foreach ( $result as $item ) {
				if ( !isset( $arr[ $item['created_at'] ]['total_sent'] ) && isset( $item['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = $item['total_sent'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_sent'] ) && 0 === $arr[ $item['created_at'] ]['total_sent'] && isset( $item['total_sent'] ) ) {
					$arr[ $item['created_at'] ]['total_sent'] = $item['total_sent'];
				}

				if ( !isset( $arr[ $item['created_at'] ]['total_open'] ) && isset( $item['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = $item['total_open'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_open'] ) && 0 === $arr[ $item['created_at'] ]['total_open'] && isset( $item['total_open'] ) ) {
					$arr[ $item['created_at'] ]['total_open'] = $item['total_open'];
				}

				if ( !isset( $arr[ $item['created_at'] ]['total_click'] ) && isset( $item['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = $item['total_click'];
				} elseif ( !isset( $arr[ $item['created_at'] ]['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = 0;
				} elseif ( isset( $arr[ $item['created_at'] ]['total_click'] ) && 0 === $arr[ $item['created_at'] ]['total_click'] && isset( $item['total_click'] ) ) {
					$arr[ $item['created_at'] ]['total_click'] = $item['total_click'];
				}
			}

			return $arr;
		}

		return $result;
	}

	/**
	 * Get sequences from campaign table
	 *
	 * @return array
	 * @since  1.0.0
	 */
	public static function get_sequences() {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT id as value, title as label FROM $campaign_table WHERE type = %s AND status = %s ", 'automation', 'created' ), ARRAY_A ); // phpcs:ignore.
		$default        = array(
			array(
				'value' => '',
				'label' => 'Select Sequence',
			),
		);
		return array_merge( $default, $results );
	}


	/**
	 * Get sequence from campaign table by id
	 *
	 * @param int $id campaign id.
	 * @return array
	 * @since  1.0.0
	 */
	public static function get_sequence_by_id( $id ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $campaign_table WHERE id = %d AND type = %s AND status = %s ", $id, 'sequence', 'schedule' ), ARRAY_A ); // phpcs:ignore.
		if ( is_array( $results ) && isset( $results[0] ) ) {
			return $results[0];
		}
		return array();
	}


	/**
	 * Get email by campaign ID
	 *
	 * @param int $id campaign id.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_emails_by_campaign_id( $id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $campaign_emails_table WHERE campaign_id = %d", $id ), ARRAY_A ); // phpcs:ignore.
		return $results;
	}

	/**
	 * Get campaign email by email ID
	 *
	 * @param int $id email id.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_email_by_email_id( $id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $campaign_emails_table WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore.
		if ( is_array( $results ) && isset( $results[0] ) ) {
			return $results[0];
		}
		return array();
	}


	/**
	 * Get email body by email ID
	 *
	 * @param int $id email id.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_email_body_by_email_id( $id ) {
		global $wpdb;
		$emails_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $emails_table WHERE email_id = %d", $id ), ARRAY_A ); // phpcs:ignore.
		if ( is_array( $results ) && isset( $results[0] ) ) {
			return $results[0];
		}
		return array();
	}



	/**
	 * Maybe site user
	 *
	 * @param string $email Email.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function maybe_user( $email ) {
		$contact = ContactModel::get_contact_by_email( $email );
		if ( isset( $contact['status'] ) && 'subscribed' === $contact['status'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Get Contact Id for automation from brodcast table
	 *
	 * @param string $email contact email address.
	 * @return mixed|null
	 */
	public static function get_contact_id_by_broadcast_table( $email ) {
		$contact = ContactModel::get_contact_by_email( $email );
		if ( isset( $contact['id'] ) ) {
			return $contact['id'];
		}
		return null;
	}


	/**
	 * Check if edd is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 * @since 1.10.7 Added EDD Pro check.
	 */
	public static function is_edd_active() {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
		if (in_array('easy-digital-downloads/easy-digital-downloads.php', $active_plugins) || 
			in_array('easy-digital-downloads-pro/easy-digital-downloads.php', $active_plugins)) { //phpcs:ignore
			return true;
		} elseif (function_exists('is_plugin_active')) {
			if (is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') || 
				is_plugin_active('easy-digital-downloads-pro/easy-digital-downloads.php')) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if tutor lms is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function is_tutor_active() {
        if ( in_array( 'tutor/tutor.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { //phpcs:ignore
			return true;
		} elseif ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'tutor/tutor.php' ) ) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Check if gravity form is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function is_gform_active() {
		if ( in_array( 'gravityforms/gravityforms.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { //phpcs:ignore
			return true;
		} elseif ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Get all gravity forms
	 */
	public static function get_gform_forms() {
		if ( self::is_gform_active() ) {
			if ( class_exists( 'GFFormsModel' ) ) {
				$forms = \GFFormsModel::get_forms( true, 'title', 'ASC', false );
				$formatted_forms = array(
					array(
						'value' => '',
						'label' => 'Select form',
						'fields' => array(),
					),
				);

				if ( is_array( $forms ) ) {
					foreach ($forms as $form) {
						$formatted_forms[] = array(
							'value' => $form->id,
							'label' => $form->title,
							'fields' => self::get_gravity_form_fields($form->id),
						);
					}
					return $formatted_forms;
				}
			}
		}
		return false;
	}

	/**
	 * Get all Mail Mint forms
	 *
	 * @return array|object|\stdClass[]|\string[][]|null
	 *
	 * @since 1.0.0
	 */
	public static function get_mailmint_forms() {
		$forms = FormModel::get_all_forms( 'id AS value, title AS label' );
		return array_merge(
			array(
				array(
					'value' => '',
					'label' => 'Select form',
				),
			),
			$forms
		);
	}


	/**
	 * Update automation steps status by automation id
	 *
	 * @param int   $automation_id Aitomation  ID.
	 * @param array $payload Array of status and user email.
	 * @return void
	 */
	public static function update_automation_steps_status( $automation_id, $payload ) {
		$steps = self::get_all_automation_step_by_id( $automation_id );
		if ( is_array( $steps ) && !empty( $payload['email'] ) ) {
			foreach ( $steps as $step ) {
				if ( isset( $step['step_id'], $step['type'] ) && 'action' === $step['type'] ) {
					$data = array(
						'automation_id' => $automation_id,
						'step_id'       => $step['step_id'],
						'status'        => isset( $payload['status'] ) ? $payload['status'] : 'processing',
						'email'         => $payload['email'],
						'identifier'    => $payload['identifier'],
					);
					self::update_log( $data );
				}
			}
		}
	}

	/**
	 * Get automation log data by email
	 *
	 * @param string $email User email.
	 * @param array  $status Array of status.
	 */
	public static function get_automaiton_log_data_by_email( $email, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . AutomationLogSchema::$table_name;

		$offset      = 0;
		$batch_size  = 500;
		$all_results = array();

		do {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name as log WHERE log.status IN (" . self::escape_array( $status ) . ') AND log.email = %s LIMIT %d OFFSET %d', $email, $batch_size, $offset ), ARRAY_A ); // phpcs:ignore.

			if ( !empty( $results ) ) {
				$all_results = array_merge( $all_results, $results );
			}

			$offset += $batch_size;
		} while ( !empty( $results ) );

		return $all_results;
	}


	/**
	 * Array to string conversion
	 *
	 * @param array $arr escape options.
	 * @return string
	 */
	public static function escape_array( $arr ) {
		global $wpdb;
		$escaped = array();
		foreach ( $arr as $k => $v ) {
			if ( is_numeric( $v ) ) {
				$escaped[] = $wpdb->prepare( '%d', $v );
			} else {
				$escaped[] = $wpdb->prepare( '%s', $v );
			}
		}
		return implode( ',', $escaped );
	}


	/**
	 * Check if edd is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function is_jetform_active() {
        if ( in_array( 'jetformbuilder/jet-form-builder.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { //phpcs:ignore
			return true;
		} elseif ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'jetformbuilder/jet-form-builder.php' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all Mail Mint forms
	 *
	 * @return array|object|\stdClass[]|\string[][]|null
	 *
	 * @since 1.0.0
	 */
	public static function get_jetform_forms() {
		if ( self::is_jetform_active() ) {
			if ( class_exists( 'Jet_Form_Builder\Classes\Tools' ) ) {
				$forms       = Tools::get_forms_list_for_js();

				$formatted_forms = array(
					array(
						'value' => '',
						'label' => 'Choose a form',
						'fields' => array(),
					),
				);

				if ( is_array( $forms ) ) {
					foreach ($forms as $form) {
						$formatted_forms[] = array(
							'value' => $form['value'],
							'label' => $form['label'],
							'fields' => self::get_jet_builder_form_fields($form['value']),
						);
					}

					return $formatted_forms;
				}
			}
		}
		return false;
	}

	/**
	 * Check if the Fluent Forms plugin is active on the WordPress site.
	 *
	 * @return bool Returns true if the Fluent Forms plugin is active, false otherwise.
	 * @since  1.2.5
	 */
	public static function is_fluentform_active() {
		if ( defined( 'FLUENTFORM' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if the Contact Form 7 plugin is active on the WordPress site.
	 *
	 * @return bool Returns true if the Contact Form 7 plugin is active, false otherwise.
	 * @since  1.5.17
	 */
	public static function is_contact_form_7_active() {
		if ( defined( 'WPCF7_VERSION' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if LearnDash LMS is active.
	 *
	 * @return bool True if LearnDash is active, false otherwise.
	 * @since 1.7.1
	 */
	public static function is_learndash_lms_active() {
		// Check if LearnDash version constant is defined.
		if ( defined( 'LEARNDASH_VERSION' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Retrieves the list of Fluent Forms and formats them as an array of options
	 * for a dropdown select input
	 *
	 * @return array|false Array of formatted form options or false if Fluent Form plugin is not active
	 *
	 * @since 1.2.5
	 */
	public static function get_fluentform_forms() {
		if ( self::is_fluentform_active() ) {
			$forms = Helper::getForms();

			if ( is_array( $forms ) ) {
				// Use array_map function to format each form object as an array of options.
				foreach ( $forms as $key => $value ) {
					$formatted_forms[] = array(
						'value' => $key,
						'label' => $value,
						'fields' => self::get_fluent_form_fields( $key ),
					);
				}
			}

			return $formatted_forms;
		}
		return false;
	}

	/**
	 * Retrieve a list of Tutor LMS courses for use in a select field.
	 *
	 * This method checks if Tutor LMS is active, and if so, retrieves a list of published courses
	 * to be used as options in a select field. Each course is formatted as an associative array with
	 * 'value' representing the course ID and 'label' representing the course title.
	 *
	 * @return array|false An array of formatted courses or false if Tutor LMS is not active.
	 * @since 1.5.14
	 */
	public static function get_tutor_lms_courses() {
		if ( self::is_tutor_active() ) {
			$posts = get_posts(
				array(
					'post_type'      => 'courses',
					'posts_per_page' => 99,
					'orderby'        => 'created_at',
					'order'          => 'DESC',
					'post_status'    => 'publish',
				)
			);

			if ( is_array( $posts ) ) {
				// Use array_map function to format each form object as an array of options.
				$formatted_courses = array();
				foreach ( $posts as $post ) {
					$formatted_courses[] = array(
						'value' => $post->ID,
						'label' => $post->post_title,
					);
				}
			}
			return $formatted_courses;
		}
		return false;
	}

	/**
	 * Retrieve a list of Tutor LMS lessons for use in a select field.
	 *
	 * This method checks if Tutor LMS is active, and if so, retrieves a list of published lessons
	 * to be used as options in a select field.
	 *
	 * @return array|false An array of formatted lessons or false if Tutor LMS is not active.
	 * @since 1.8.1
	 */
	public static function get_tutor_lms_lessons() {
		if ( self::is_tutor_active() ) {
			$posts = get_posts(
				array(
					'post_type'   => 'lesson',
					'numberposts' => -1,
				)
			);

			if ( is_array( $posts ) ) {
				// Use array_map function to format each form object as an array of options.
				$formatted_courses = array();
				foreach ( $posts as $post ) {
					$formatted_courses[] = array(
						'value' => $post->ID,
						'label' => $post->post_title,
					);
				}
			}
			return $formatted_courses;
		}
		return false;
	}

	/**
	 * Get LearnDash courses as an array of options.
	 *
	 * @return array|false An array of formatted course options if LearnDash is active, false otherwise.
	 * @since 1.7.1
	 */
	public static function get_learndash_courses() {
		if ( self::is_learndash_lms_active() ) {
			$posts = get_posts(
				array(
					'post_type'   => 'sfwd-courses',
					'numberposts' => -1,
					'orderby'     => 'created_at',
					'order'       => 'DESC',
					'post_status' => 'publish',
				)
			);

			if ( is_array( $posts ) ) {
				// Use array_map function to format each form object as an array of options.
				$formatted_courses[] = array(
					'value'   => 0,
					'label'   => __( 'Select', 'mrm' ),
					'lessons' => array(),
				);
				foreach ( $posts as $post ) {
					$formatted_courses[] = array(
						'value'   => $post->ID,
						'label'   => $post->post_title,
						'lessons' => self::get_learndash_lessons_by_course( $post->ID ),
					);
				}
			}

			return $formatted_courses;
		}
		return false;
	}

	/**
	 * Get LearnDash quizzes as an array of options.
	 *
	 * Description: Retrieves LearnDash quizzes and formats them as an array of options.
	 *
	 * @access public
	 * @return array|false Array of formatted quiz options or false if LearnDash is not active.
	 * @since 1.8.0
	 */
	public static function get_learndash_quizzes() {
		if ( self::is_learndash_lms_active() ) {
			$posts = get_posts(
				array(
					'post_type'   => learndash_get_post_type_slug( 'quiz' ),
					'numberposts' => -1,
					'orderby'     => 'created_at',
					'order'       => 'DESC',
					'post_status' => 'publish',
				)
			);

			if ( is_array( $posts ) ) {
				// Use array_map function to format each form object as an array of options.
				$formatted_quizzes[] = array(
					'value' => 0,
					'label' => __( 'Select', 'mrm' ),
				);
				foreach ( $posts as $post ) {
					$formatted_quizzes[] = array(
						'value' => $post->ID,
						'label' => $post->post_title,
					);
				}
			}

			return $formatted_quizzes;
		}
		return false;
	}

	/**
	 * Summary: Get WooCommerce coupons.
	 *
	 * Description: This static method retrieves WooCommerce coupons using the MintAutomaticCoupon class.
	 * It checks for the active status of WooCommerce, the activation status of MailMint Pro, and the compatibility of MailMint Pro version.
	 *
	 * @access public
	 *
	 * @return array Returns an array of WooCommerce coupons if conditions are met; otherwise, an empty array.
	 *
	 * @since 1.7.1
	 */
	public static function get_woocommerce_coupons() {
		$wc_active = MrmCommon::is_wc_active();
		if ( $wc_active && MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.7.0' ) ) {
			return MintAutomaticCoupon::get_woocommerce_coupons();
		} else {
			return array();
		}
	}

	/**
	 * Get LearnDash lessons for a given course and format them into an array.
	 *
	 * @param int $course_id The ID of the LearnDash course.
	 *
	 * @return array An array of lessons with 'value' as the lesson ID and 'label' as the lesson title.
	 * @since 1.6.1
	 */
	public static function get_learndash_lessons_by_course( $course_id ) {
		if ( !$course_id ) {
			return array();
		}

		$lessons = learndash_get_lesson_list( $course_id );

		$formatted_lessons = array();
		foreach ( $lessons as $lesson ) {
			$formatted_lessons[] = array(
				'value'  => strval( $lesson->ID ),
				'label'  => $lesson->post_title,
				'topics' => self::get_learndash_topics_by_course( $course_id, $lesson->ID ),

			);
		}
		return $formatted_lessons;
	}

	/**
	 * Get LearnDash topics for a given course and format them into an array.
	 *
	 * @param int $course_id The ID of the LearnDash course.
	 * @param int $lesson_id The ID of the LearnDash lesson.
	 *
	 * @return array An array of topics with 'value' as the lesson ID and 'label' as the lesson title.
	 * @since 1.11.0
	 */
	public static function get_learndash_topics_by_course( $course_id, $lesson_id ) {
		if ( !$course_id || !$lesson_id ) {
			return array();
		}

		$topics = learndash_get_topic_list( $lesson_id, $course_id );

		$formatted_topics = array();
		foreach ( $topics as $topic ) {
			$formatted_topics[] = array(
				'value'  => strval( $topic->ID ),
				'label'  => $topic->post_title,
			);
		}
		return $formatted_topics;
	}

	/**
	 * Wrapper function to find and return data related to sending an email in a larger automation or workflow system.
	 *
	 * @param mixed $autamation_data An array containing data related to an automation or workflow system.
	 *
	 * @return array
	 * @since 1.1.2
	 */
	public static function find_send_mail_action( $autamation_data ) {
		$step = self::get_prev_step( $autamation_data['automation_id'], $autamation_data['step_id'] );
		$data = self::find_send_mail_recursion( $step, $autamation_data );
		return $data;
	}

	/**
	 * Recursively find the step in an automation or workflow system that sends an email.
	 *
	 * @param mixed $step An array representing the current step in the automation or workflow system.
	 * @param mixed $autamation_data An array containing data related to the automation or workflow system.
	 *
	 * @return array|null
	 * @since 1.1.2
	 */
	private static function find_send_mail_recursion( $step, $autamation_data ) {
		if ( isset( $step['key'] ) && 'sendMail' === $step['key'] ) {
			return $step;
		} elseif ( isset( $step['key'] ) && 'trigger' === $step['step_type'] ) {
			return $step;
		} else {
			$step = self::get_prev_step( $step['automation_id'], $step['step_id'] );
			if ( isset( $step['key'] ) && 'sendMail' === $step['key'] ) {
				return $step;
			} else {
				self::find_send_mail_recursion( $step, $autamation_data );
			}
		}
	}

	/**
	 * Calculates the time difference in seconds between two date/time strings.
	 *
	 * @param string $datetime1 The first date/time string in 'Y-m-d H:i:s' format.
	 * @param string $datetime2 The second date/time string in 'Y-m-d H:i:s' format.
	 *
	 * @return int The time difference in seconds.
	 * @since 1.2.7
	 */
	public static function get_time_diff_in_seconds( $datetime1, $datetime2 ) {
		// phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed
		// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found
		$datetime1 = new DateTime( $datetime1 );
		$datetime2 = new DateTime( $datetime2 );
		$interval  = $datetime1->diff( $datetime2 );
		$seconds   = ( $interval->days * 24 * 60 * 60 ) +
					 ( $interval->h * 60 * 60 ) +
					 ( $interval->i * 60 ) +
				   $interval->s;
		return $seconds;
	}

	/**
	 * Get the date from a specific key in a conditional step array.
	 *
	 * @param array  $automation_meta An array of automation meta data.
	 * @param string $key The key to search in the conditional step array.
	 * @return string|null Returns the date string if found, otherwise null.
	 * @since 1.2.7
	 */
	public static function get_date_from_conditional_step_array( $automation_meta, $key ) {
		$meta_value = isset( $automation_meta[0]['meta_value'] ) ? maybe_unserialize( $automation_meta[0]['meta_value'] ) : '';

		foreach ( $meta_value as $item ) {
			if ( array_key_exists( $key, $item ) ) {
				$format = 'n/j/Y, h:i:s A';
				$date   = DateTime::createFromFormat( $format, $item[ $key ] );
				if ( $date ) {
					return $item[ $key ];
				}
				return null;
			}
		}

		return null;
	}

	/**
	 * Run SQL Query to get email address by email id
	 *
	 * @param mixed $email_id Email id from the broadcast email meta.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_email_address_by_email_id( $email_id ) {
		global $wpdb;
		$emails_table = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT email_address FROM $emails_table WHERE id = %d", array( $email_id ) ) ); //phpcs:ignore
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Retrieves an automation step record from the database by step ID.
	 *
	 * @param string $step_id The unique ID of the automation step to retrieve.
	 *
	 * @return array|null The automation step record as an associative array, or null if not found.
	 * @since 1.2.6
	 */
	public static function get_automation_step_by_step_id( $step_id ) {
		global $wpdb;
		$step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $step_table WHERE step_id = %s", array( $step_id ) ), ARRAY_A ); //phpcs:ignore
	}

    /**
     * Extracts the first 130 characters from the given text.
     *
     * @param string $text The input text to extract from.
     * @return string The extracted text.
     * @since 1.4.1
     */
    public static function extract_text( $text ) {
        $extracted_text = substr( $text, 0, 170 );
        return $extracted_text;
    }

	/**
	 * Clone an automation and update its name and status.
	 *
	 * @param array $original_automation The original automation to duplicate.
	 * @param int   $automation_id The original automation id to duplicate.
	 * @return int|false The new automation or false on failure.
	 *
	 * @since 1.2.6
	 */
	public static function clone_automation( $original_automation, $automation_id ) {
		// Create a copy of the original automation and update its name and status.
		$new_automation           = !empty( $original_automation['data'][0] ) ? $original_automation['data'][0] : array();
		$new_automation['name']   = self::extract_text( $new_automation['name'] ) . ' [Duplicate]';
		$new_automation['status'] = 'draft';

		// Remove the ID to allow a new one to be assigned when inserted into the database.
		unset( $new_automation['id'] );

		// Insert the new automation and get its ID.
		$new_automation_id = AutomationModel::get_instance()->create( $new_automation );
		if ( empty( $new_automation_id ) ) {
			return false;
		}

		// Get the at-most-date meta for the original automation and update it for the new automation.
		$most_date_meta = self::get_automation_meta( $automation_id, '_at_most_date' );
		$most_date_meta = isset( $most_date_meta[0]['meta_value'] ) ? $most_date_meta[0]['meta_value'] : maybe_serialize( array() );
		self::update_automation_meta( $new_automation_id, '_at_most_date', $most_date_meta );
		self::update_automation_meta( $new_automation_id, 'source', 'mint' );

		$new_automation['id'] = $new_automation_id;
		return $new_automation;
	}

	/**
	 * Updates the step data for "yes" node with new IDs and returns the updated $step_data.
	 *
	 * @param array      $yes_steps  The "yes" steps to process.
	 * @param array      $step_data  The existing step data.
	 * @param int|string $key The key of the current step in the parent node.
	 * @param int        $automation_id  The ID of the automation to be updated.
	 * @param string     $conditional_type  The type of the automation to be updated.
	 *
	 * @return array  The updated step data.
	 */
	public static function generate_automation_node_steps_data( $yes_steps, $step_data, $key, $automation_id, $conditional_type ) {
		foreach ( $yes_steps as $_key => $_step ) {
			$random           = substr( md5( mt_rand() ), 0, 5 ); //phpcs:ignore
			$next_step_random = substr( md5( mt_rand() ), 0, 5 ); //phpcs:ignore

			if ( isset( $_step['step_id'] ) ) {
				$step_data[ $key ]['node_data'][ $conditional_type ][ $_key ] = array(
					'automation_id' => $automation_id,
					'key'           => $_step['key'],
					'type'          => $_step['type'],
					'settings'      => isset( $_step['settings'] ) ? $_step['settings'] : array(),
					'old_id'        => $_step['id'],
				);

				if ( isset( $yes_steps[ $_key +1 ] ) ) {
					$step_data[ $key ]['node_data'][ $conditional_type ][ $_key ]['next_step_id'] = $next_step_random;
				} else {
					$step_data[ $key ]['node_data'][ $conditional_type ][ $_key ]['next_step_id'] = array();
				}

				if ( 0 === $_key ) {
					$step_data[ $key ]['node_data'][ $conditional_type ][ $_key ]['step_id'] = $random;
					$step_data[ $key ]['logical_next_step_id'][ $conditional_type ]          = $step_data[ $key ]['node_data'][ $conditional_type ][ $_key ]['step_id'];
				} else {
					$step_data[ $key ]['node_data'][ $conditional_type ][ $_key ]['step_id'] = $step_data[ $key ]['node_data'][ $conditional_type ][ $_key -1 ]['next_step_id'];
				}
			}
		}

		return $step_data;
	}

	/**
	 * Prepares the logical steps for an automation duplication.
	 *
	 * @param mixed $step_data The original step data for the automation.
	 * @param mixed $step The step data to be duplicated.
	 * @param mixed $key The key of the step in the step data array.
	 * @param mixed $automation_id The ID of the automation being duplicated.
	 *
	 * @return array The updated step data.
	 * @since 1.2.6
	 */
	public static function update_step_data_on_automation_duplication( $step_data, $step, $key, $automation_id ) {
		$step_data[ $key ]['node_data']  = $step['node_data'];
		$step_data[ $key ]['meta_key']   = $step['meta_key']; //phpcs:ignore
		$step_data[ $key ]['meta_value'] = $step['meta_value']; //phpcs:ignore

		$yes_steps = !empty( $step_data[ $key ]['node_data']['yes'] ) ? $step_data[ $key ]['node_data']['yes'] : array();
		$no_steps  = !empty( $step_data[ $key ]['node_data']['no'] ) ? $step_data[ $key ]['node_data']['no'] : array();

		$step_data[ $key ]['next_step_id'] = isset( $step_data[ $key ]['next_step_id'] ) ? $step_data[ $key ]['next_step_id'] : '';

		$step_data[ $key ]['logical_next_step_id'] = isset( $step['logical_next_step_id'] ) ? $step['logical_next_step_id'] : array();

		// Updates the step data for "yes" node with new IDs and returns the updated $step_data.
		$step_data = self::generate_automation_node_steps_data( $yes_steps, $step_data, $key, $automation_id, 'yes' );

		// Generate no node steps data for an automation.
		$step_data = self::generate_automation_node_steps_data( $no_steps, $step_data, $key, $automation_id, 'no' );
		return $step_data;
	}

	/**
	 * Creates duplicate steps for an automation and saves them in the database.
	 *
	 * @param array $step_data     An array of data for the steps to duplicate.
	 * @param int   $automation_id The ID of the automation to duplicate the steps for.
	 *
	 * @return void
	 * @since 1.2.6
	 */
	public static function create_duplicate_automation_steps( array $step_data, int $automation_id ): void {
		foreach ( $step_data as $step_key => $dup_step ) {
			if ( isset( $dup_step['step_id'] ) ) {
				$duplicate_step = array(
					'automation_id' => $automation_id,
					'step_id'       => $dup_step['step_id'],
					'key'           => $dup_step['key'],
					'type'          => $dup_step['type'],
					'settings'      => isset( $dup_step['settings'] ) ? $dup_step['settings'] : array(),
					'next_step_id'  => isset( $dup_step['next_step_id'] ) ? $dup_step['next_step_id'] : '',
				);

				if ( 'logical' === $dup_step['type'] ) {
					$yes_steps = !empty( $dup_step['node_data']['yes'] ) ? $dup_step['node_data']['yes'] : array();
					$no_steps  = !empty( $dup_step['node_data']['no'] ) ? $dup_step['node_data']['no'] : array();

					$duplicate_step['next_step_id'] = array(
						'logical_next_step_id' => isset( $dup_step['logical_next_step_id'] ) ? $dup_step['logical_next_step_id'] : array(),
						'next_step_id'         => isset( $dup_step['next_step_id'] ) ? $dup_step['next_step_id'] : '',
					);
					if ( isset( $step_data[ $step_key +1 ] ) ) {
						$yes_step_last_index = count( $dup_step['node_data']['yes'] ) - 1;
						if ( $yes_step_last_index >= 0 ) {
							$yes_steps[ $yes_step_last_index ]['next_step_id'] = $step_data[ $step_key +1 ]['step_id'];
						}
						$no_step_last_index = count( $dup_step['node_data']['no'] ) - 1;
						if ( $no_step_last_index >= 0 ) {
							$no_steps[ $no_step_last_index ]['next_step_id'] = $step_data[ $step_key +1 ]['step_id'];
						}
					}
					$dup_step = self::update_duplicate_automation_node_steps_data( $yes_steps, $automation_id, 'yes', $dup_step );
					$dup_step = self::update_duplicate_automation_node_steps_data( $no_steps, $automation_id, 'no', $dup_step );
				}
				$duplicate_step_id    = AutomationStepModel::get_instance()->create_or_update( $duplicate_step );
				$automation_step_data = self::get_automation_step_by_step_id( $dup_step['step_id'] );

				if ( is_array( $automation_step_data ) && 'condition' === $automation_step_data['key'] ) {
                    $yes_steps = !empty( $dup_step['node_data']['yes'] ) ? $dup_step['node_data']['yes'] : array();
                    $no_steps  = !empty( $dup_step['node_data']['no'] ) ? $dup_step['node_data']['no'] : array();

                    $duplicate_step['next_step_id'] = array(
                        'logical_next_step_id' => isset( $dup_step['logical_next_step_id'] ) ? $dup_step['logical_next_step_id'] : array(),
                        'next_step_id'         => isset( $dup_step['next_step_id'] ) ? $dup_step['next_step_id'] : '',
                    );
                    if ( isset( $step_data[ $step_key +1 ] ) ) {
                        $yes_step_last_index = count( $dup_step['node_data']['yes'] ) - 1;
                        if ( $yes_step_last_index >= 0 ) {
                            $yes_steps[ $yes_step_last_index ]['next_step_id'] = $step_data[ $step_key +1 ]['step_id'];
                        }
                        $no_step_last_index = count( $dup_step['node_data']['no'] ) - 1;
                        if ( $no_step_last_index >= 0 ) {
                            $no_steps[ $no_step_last_index ]['next_step_id'] = $step_data[ $step_key +1 ]['step_id'];
                        }
                    }
                    $dup_step['node_data']['yes'] = $yes_steps;
                    $dup_step['node_data']['no']  = $no_steps;
					self::update_automation_step_meta( $duplicate_step_id, 'conditional_node_step', maybe_serialize( $dup_step['node_data'] ) );
				}
			}
		}
	}

	/**
	 * Updates the yes node steps data for a duplicated automation.
	 *
	 * @param array  $yes_steps An array of yes node steps data.
	 * @param int    $automation_id The ID of the duplicated automation.
	 * @param string $type Conditional node type.
	 * @param array  $dup_step duplicate steps.
	 *
	 * @return $dup_step
	 * @since 1.2.6
	 */
	public static function update_duplicate_automation_node_steps_data( $yes_steps, $automation_id, $type, $dup_step ) {
		foreach ( $yes_steps as $value => $logical_step ) {
			$logical_step_data = array(
				'id'            => isset( $logical_step['id'] ) ? $logical_step['id'] : '',
				'automation_id' => $automation_id,
				'step_id'       => isset( $logical_step['step_id'] ) ? $logical_step['step_id'] : '',
				'key'           => isset( $logical_step['key'] ) ? $logical_step['key'] : '',
				'type'          => isset( $logical_step['type'] ) ? $logical_step['type'] : '',
				'settings'      => isset( $logical_step['settings'] ) ? $logical_step['settings'] : array(),
				'next_step_id'  => isset( $logical_step['next_step_id'] ) ? $logical_step['next_step_id'] : '',
			);

			$logical_step_id                                = AutomationStepModel::get_instance()->create_or_update( $logical_step_data );
			$dup_step['node_data'][ $type ][ $value ]['id'] = $logical_step_id;

			$_step_meta       = self::get_automation_step_meta( $logical_step['old_id'], 'conditional_data' );
			$_step_meta_value = isset( $_step_meta['meta_value'] ) ? maybe_unserialize( $_step_meta['meta_value'] ) : '';

			$dup_step['node_data'][ $type ][ $value ]['popover_type']   = isset( $_step_meta_value['popover_type'] ) ? $_step_meta_value['popover_type'] : '';
			$dup_step['node_data'][ $type ][ $value ]['parent_index']   = isset( $_step_meta_value['parent_index'] ) ? $_step_meta_value['parent_index'] : '';
			$dup_step['node_data'][ $type ][ $value ]['condition_type'] = isset( $_step_meta_value['condition_type'] ) ? $_step_meta_value['condition_type'] : '';

			$step_meta_value = array(
				'popover_type'   => isset( $_step_meta_value['popover_type'] ) ? $_step_meta_value['popover_type'] : '',
				'parent_index'   => isset( $_step_meta_value['parent_index'] ) ? $_step_meta_value['parent_index'] : '',
				'condition_type' => isset( $_step_meta_value['condition_type'] ) ? $_step_meta_value['condition_type'] : '',
			);
			self::update_automation_step_meta( $logical_step_id, 'conditional_data', maybe_serialize( $step_meta_value ) );
		}
        return $dup_step;
	}

	/**
	 * Updates step data with new IDs and next step IDs based on given step and key
	 *
	 * @param array $steps         Array of steps.
	 * @param array $step_data     Array of step data.
	 * @param int   $key           Key of the step to be updated.
	 * @param array $step          Array containing information about the step to be updated.
	 * @param int   $automation_id ID of the automation.
	 *
	 * @return array Array of updated step data.
	 * @since 1.2.6
	 */
	public static function generate_individual_step_data( array $steps, array $step_data, int $key, array $step, int $automation_id ): array {
		$random           = substr( md5( mt_rand() ), 0, 5 ); //phpcs:ignore
		$next_step_random = substr( md5( mt_rand() ), 0, 5 ); //phpcs:ignore

		if ( isset( $step['step_id'] ) ) {
			$step_data[ $key ] = array(
				'automation_id' => $automation_id,
				'key'           => $step['key'],
				'type'          => $step['type'],
				'settings'      => isset( $step['settings'] ) ? $step['settings'] : array(),
				'old_id'        => $step['id'],
			);

			if ( isset( $steps[ $key + 1 ] ) ) {
				$step_data[ $key ]['next_step_id'] = ( 'logical' === $step_data[ $key ]['type'] && 'condition' === $step_data[ $key ]['key'] ) ? $next_step_random : $next_step_random;
			} else {
				$step_data[ $key ]['next_step_id'] = array();
			}

			if ( 0 === $key ) {
				$step_data[ $key ]['step_id'] = $random;
			} else {
				$step_data[ $key ]['step_id'] = ( 'logical' === $step_data[ $key - 1 ]['type'] && 'condition' === $step_data[ $key - 1 ]['key'] ) ? $step_data[ $key - 1 ]['next_step_id'] : $step_data[ $key - 1 ]['next_step_id'];
			}

			if ( 'logical' === $step['type'] && 'condition' === $step['key'] ) {
				$step_data = self::update_step_data_on_automation_duplication( $step_data, $step, $key, $automation_id );
			}
		}

		return $step_data;
	}

    /**
     * Checks if an automation is active based on its ID.
     *
     * @param int $automation_id The ID of the automation.
     *
     * @return bool True if the automation is active, false otherwise.
     * @since 1.4.5
     */
    public static function is_automation_active( $automation_id ) {
		if ( !$automation_id ) {
            return false;
		}
        global $wpdb;
        $automation_table = $wpdb->prefix . AutomationSchema::$table_name;
        $results          = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $automation_table WHERE id = %d and status = %s", $automation_id, 'active' ), ARRAY_A ); //phpcs:ignore
		if ( !empty( $results['id'] ) ) {
			return true;
		}
        return false;
    }

    /**
     * Checks if a step exists in an automation based on the automation ID and step ID.
     *
     * @param int    $automation_id The ID of the automation.
     * @param string $step_id       The ID of the step.
     *
     * @return bool True if the step exists in the automation, false otherwise.
     * @since 1.4.5
     */
    public static function step_exist_in_automation( $automation_id, $step_id ) {
        if ( !$automation_id || !$step_id ) {
            return false;
        }
        global $wpdb;
        $automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
        $results               = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $automation_step_table as step WHERE step.automation_id = %d and step.step_id = %s", $automation_id, $step_id ), ARRAY_A ); //phpcs:ignore
        if ( !empty( $results['id'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Checks if a step exists in an active automation based on the automation ID and step ID.
     *
     * @param int    $automation_id The ID of the automation.
     * @param string $step_id       The ID of the step.
     *
     * @return bool True if the step exists in an active automation, false otherwise.
     * @since 1.4.5
     */
    public static function step_exist_with_active_automation( $automation_id, $step_id ) {
        if ( !$automation_id || !$step_id ) {
            return false;
        }
        $automation_active = self::is_automation_active( $automation_id );
        $step_id_exist     = self::step_exist_in_automation( $automation_id, $step_id );
        if ( $step_id_exist && $automation_active ) {
            return true;
        }
        return false;
    }

	/**
	 * Get broadcast email information by step ID and contact ID.
	 *
	 * @param string $step_id    The step ID associated with the broadcast email.
	 * @param int    $contact_id The ID of the contact for whom the broadcast email is intended.
	 * @param int    $broadcast_id The ID of the broadcast email.
	 *
	 * @return array|null An associative array containing the broadcast email information, or null if not found.
	 *
	 * @since 1.6.2
	 */
	public static function get_broadcast_email_by_step_id( $step_id, $contact_id, $broadcast_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE step_id = %s AND contact_id = %d AND id = %d ", $step_id, $contact_id, $broadcast_id ), ARRAY_A );  // phpcs:ignore
	}

	/**
	 * Check if MemberPress is active.
	 *
	 * @return bool True if MemberPress is active, false otherwise.
	 * @since 1.8.0
	 */
	public static function is_memberpress_active() {
		// Check if LearnDash version constant is defined.
		if ( defined( 'MEPR_PLUGIN_NAME' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Retrieve formatted MemberPress membership levels.
	 *
	 * This function checks if MemberPress is active and retrieves all membership levels,
	 * formatting them into an array suitable for use in form elements like dropdowns.
	 *
	 * @return array|false An array of formatted membership levels with 'value' and 'label' keys.
	 * @since 1.8.0
	 */
	public static function get_mp_membership_levels() {
		if ( self::is_memberpress_active() ) {
			$levels           = \MeprCptModel::all( 'MeprProduct' );
			$formatted_levels = array();
			foreach ( $levels as $level ) {
				$formatted_levels[] = array(
					'value' => strval( $level->ID ),
					'label' => $level->post_title,
				);
			}

			return $formatted_levels;
		}
		return false;
    }

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active, false otherwise.
	 * @since 1.10.0
	 */
	public static function get_woocommerce_order_statuses() {
		if ( ! self::is_wc_active() ) {
			return false;
		}

		$order_statuses = wc_get_order_statuses();

		$formatted_statuses[] = array(
			'value'   => 0,
			'label'   => __( 'Select status', 'mrm' ),
		);
		foreach ( $order_statuses as $key => $value ) {
			$formatted_statuses[] = array(
				'value' => str_replace( 'wc-', '', $key ),
				'label' => $value,
			);
		}

		return $formatted_statuses;
	}

	/**
	 * Check if LifterLMS is active.
	 *
	 * @return bool True if LifterLMS is active, false otherwise.
	 * @since 1.8.0
	 */
	public static function is_lifter_lms_active() {
		// Check if LearnDash version constant is defined.
		if ( defined( 'LLMS_PLUGIN_FILE' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Retrieve a list of LifterLMS courses for use in a select field.
	 *
	 * This method checks if LifterLMS is active, and if so, retrieves a list of published courses
	 * to be used as options in a select field. Each course is formatted as an associative array with
	 * 'value' representing the course ID and 'label' representing the course title.
	 *
	 * @return array|false An array of formatted courses or false if LifterLMS is not active.
	 * @since 1.12.0
	 */
	public static function get_lifter_lms_courses() {
		if ( self::is_lifter_lms_active() ) {
			$courses = get_posts(
				array(
					'post_type'   => 'course',
					'numberposts' => -1,
					'post_status' => 'publish',
				)
			);

			$formatted_courses = array();
			foreach ( $courses as $course ) {
				$formatted_courses[] = [
					'value'    => strval( $course->ID ),
					'label' => $course->post_title
				];
			}
        	return $formatted_courses;
		}
		return false;
	}

	/**
	 * Retrieve a list of LifterLMS memberships for use in a select field.
	 *
	 * This method checks if LifterLMS is active, and if so, retrieves a list of published memberships
	 * to be used as options in a select field. Each course is formatted as an associative array with
	 * 'value' representing the course ID and 'label' representing the course title.
	 *
	 * @return array|false An array of formatted memberships or false if LifterLMS is not active.
	 * @since 1.12.0
	 */
	public static function get_lifter_lms_memberships() {
		if ( self::is_lifter_lms_active() ) {
			$courses = get_posts(
				array(
					'post_type'   => 'llms_membership',
					'numberposts' => -1,
					'post_status' => 'publish',
				)
			);

			$formatted_courses = array();
			foreach ( $courses as $course ) {
				$formatted_courses[] = [
					'value'    => strval( $course->ID ),
					'label' => $course->post_title
				];
			}

        	return $formatted_courses;
		}
		return false;
	}

	/**
	 * Get the list of Fluent Form fields.
	 *
	 * @param int $form_id The ID of the Fluent Form.
	 * 
	 * @return array The list of Fluent Form fields.
	 * @since 1.15.1
	 */
	private static function get_fluent_form_fields( $form_id ) {
		$form = wpFluent()->table('fluentform_forms')->find($form_id);

		if (! $form) {
			return array();
		}

		$form_fields = json_decode($form->form_fields);
		$fields_data = array();
		if (empty($form_fields)) {
			return $fields_data;
		}

		$column_field_data = array();
		foreach ($form_fields as $field_key => $fields) {
			if (isset($fields->attributes) && isset($fields->attributes->type) && (('submit' === $fields->attributes->type))) {
				continue;
			}

			if (is_array($fields) && 0 < count($fields)) {
				foreach ($fields as $f_key => $field) {
					$field_label = ! empty($field->settings->label) ? $field->settings->label : '';
					$field_label = empty($field_label) && ! empty($field->settings->admin_field_label) ? $field->settings->admin_field_label : $field_label;
					$field_label = empty($field_label) && ! empty($field->attributes->name) ? $field->attributes->name : $field_label;

					if (isset(
						$field->attributes
					) && isset($field->attributes->type)) {
						$fields_data[$field->attributes->name] = $field_label;
					}

					/** in case fields are in column */
					if (isset(
						$field->columns
					) && ! empty($field->columns)) {
						$column_field_data[] = self::get_fluent_col_fields($field->columns);
					}

					/** if both the setting label and admin field label blank then continue  */
					if ((isset($field->settings->label) && empty($field->settings->label)) && (isset($field->settings->admin_field_label) && empty($field->settings->admin_field_label))) {
						continue;
					}

					/** empty field data then get from setting */
					if (empty($fields_data[$field->attributes->name])) {
						$fields_data[$field->attributes->name] = $field_label;
					}

					/** Getting child fields */
					if (isset(
						$field->fields
					) && ! empty($field->fields)) {
						foreach ($field->fields as $child_field) {
							if (false === $child_field->settings->visible) {
								continue;
							}
							$label = ! empty($child_field->settings->label) ? $child_field->settings->label : '';
							$label = empty($label) && ! empty($child_field->attributes->placeholder) ? $child_field->attributes->placeholder : $label;
							$label = empty($label) && ! empty($child_field->attributes->name) ? $child_field->attributes->name : $label;

							$fields_data[$field->attributes->name . ':' . $child_field->attributes->name] = $label;
							unset($fields_data[$field->attributes->name]);
						}
					}
				}
			}
		}

		if (
			! empty($column_field_data)
		) {
			foreach ($column_field_data as $cf_key => $column_field) {
				$fields_data = array_merge($fields_data, $column_field);
			}
		}

		$fields_data        = array_filter($fields_data);
		$transformed_fields = array();
		foreach ($fields_data as $key => $label) {
			$type = strpos($key, ':') !== false ? substr($key, strpos($key, ':') + 1) : $key;
			$transformed_fields[] = array(
				'type' => $type,
				'label' => $label,
				'value' => $type
			);
		}
		return $transformed_fields;
	}

	/**
	 * Get the list of Fluent Form fields.
	 * 
	 * @param array $col_fields The list of column fields.
	 *
	 * @return array The list of Fluent Form fields.
	 * @since 1.15.1
	 */
	private static function get_fluent_col_fields($col_fields){
		$fields_data = array();

		foreach ($col_fields as $col_key => $col_field) {

			if (! isset(
				$col_field->fields
			) || empty($col_field->fields)) {
				continue;
			}

			foreach ($col_field->fields as $f_key => $field) {
				if (isset($field->attributes) && isset($field->attributes->type)) {
					$field_label = ! empty($field->settings->label) ? $field->settings->label : '';
					$field_label = empty($field_label) && ! empty($field->settings->admin_field_label) ? $field->settings->admin_field_label : $field_label;

					$fields_data[$field->attributes->name] = ! empty($field->attributes->name) ? $field->attributes->name : $field_label;
				}

				if (isset(
					$field->fields
				) && ! empty($field->fields)) {
					foreach ($field->fields as $child_field) {
						if (false === $child_field->settings->visible
						) {
							continue;
						}
						$label = ! empty($child_field->settings->label) ? $child_field->settings->label : '';
						$label = empty($label) && ! empty($child_field->attributes->placeholder) ? $child_field->attributes->placeholder : $label;
						$label = empty($label) && ! empty($child_field->attributes->name) ? $child_field->attributes->name : $label;
						$fields_data[$field->attributes->name . ':' . $child_field->attributes->name] = $label;

						unset($fields_data[$field->attributes->name]);
					}
				}
			}
		}

		return $fields_data;
	}

	/**
	 * Get the list of Gravity Form fields.
	 *
	 * @param int $form_id The ID of the Gravity Form.
	 * 
	 * @return array The list of Gravity Form fields.
	 * @since 1.15.1
	 */
	public static function get_gravity_form_fields($form_id){
		if (empty($form_id)) {
			return array();
		}

		$form = \GFAPI::get_form($form_id);

		if ( empty( $form ) || ! is_array( $form ) || ! isset( $form['fields'] ) ) {
			return array();
		}

		$fields = array();
		foreach ($form['fields'] as $field) {
			$fields[] = array(
				'type'  => $field->type,
				'label' => $field->label,
				'value' => $field->id
			);
		}

		return $fields;
	}

	/**
	 * Get the list of JetFormBuilder fields.
	 *
	 * @param int $form_id The ID of the JetFormBuilder form.
	 * 
	 * @return array The list of JetFormBuilder fields.
	 * @since 1.15.5
	 */
	private static function get_jet_builder_form_fields($form_id){
		if (empty($form_id)) {
			return array();
		}

		$blocks = \Jet_Form_Builder\Blocks\Block_Helper::get_blocks_by_post( $form_id );

		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return array();
		}

		$fields = array();
		foreach ($blocks as $block) {
			$attrs = isset($block['attrs']) ? $block['attrs'] : array();

			if( empty( $attrs ) || ! is_array( $blocks ) || empty( $block['blockName'] ) || empty( $attrs['label'] )  ) {
				continue;
			}

			$fields[] = array(
				'type'  => $block['blockName'],
				'label' => isset($attrs['label']) ? $attrs['label'] : '',
				'value' => isset($attrs['name']) ? $attrs['name'] : ''
			);
		}

		return $fields;
	}
}


