<?php
/**
 * Manage Automation Step related database operations
 *
 * @package MintMail\App\Internal\Automation
 * @namespace MintMail\App\Internal\Automation
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace MintMail\App\Internal\Automation;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use Mint\MRM\DataBase\Tables\AutomationLogSchema;
use Mint\MRM\DataBase\Tables\AutomationStepMetaSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;
use wpdb;

/**
 * AutomationStepModel class
 *
 * Manage Automation step database related operations.
 *
 * @package MintMail\App\Internal\Automation
 * @namespace MintMail\App\Internal\Automation
 *
 * @version 1.0.0
 */
class AutomationStepModel {
	use Singleton;

	/**
	 * Create or update automation step
	 *
	 * @param array $payload payload.
	 * @return int Step id.
	 */
	public function create_or_update( $payload ) {
		try {
			if ( isset( $payload['automation_id'], $payload['step_id'], $payload['key'], $payload['type'], $payload['next_step_id'] ) ) {
				$automation_step_id = '';
				if ( empty( $payload['id'] ) ) {
					if ( !$this->is_automation_step_exist_by_step_id( $payload['step_id'] ) ) {
						$automation_step_id = $this->create( $payload );
					}
				} else {
					$response = $this->update( $payload );
					if ( $response ) {
						$automation_step_id = $payload['id'];
					}
				}
				if ( $automation_step_id ) {
					return $automation_step_id;
				} else {
					return false;
				}
			}
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Create automation step
	 *
	 * @param array $payload payload.
	 * @return mixed.
	 */
	public function create( $payload ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		try {
			if ( 'sendEmail' === $payload['key'] ) {
				if ( isset( $payload['settings']['message_data']['body'] ) ) {
					$payload['settings']['message_data']['body'] = html_entity_decode( $payload['settings']['message_data']['body'] ); //phpcs:ignore
				}
			}
			$wpdb->insert(
				$automation_step_table,
				array(
					'automation_id' => $payload['automation_id'],
					'step_id'       => $payload['step_id'],
					'key'           => $payload['key'],
					'type'          => $payload['type'],
					'settings'      => isset( $payload['settings'] ) ? serialize( $payload['settings'] ) : array(), //phpcs:ignore
					'next_step_id'  => is_array( $payload['next_step_id'] ) ? serialize( $payload['next_step_id'] ) : $payload['next_step_id'], //phpcs:ignore
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				)
			); // db call ok.
			$automation_step_id = $wpdb->insert_id;
			return $automation_step_id;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Create automation step
	 *
	 * @param array $payload payload.
	 * @return bool.
	 */
	public function update( $payload ) {
		try {
			global $wpdb;
			$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;

			if ( isset( $payload['created_at'] ) ) {
				unset( $payload['created_at'] );
			}
			if ( isset( $payload['settings'] ) ) {
				$payload['settings'] = serialize( $payload['settings'] ); //phpcs:ignore
			}

			if ( isset( $payload['next_step_id'] ) ) {
				$payload['next_step_id'] = is_array( $payload['next_step_id'] ) ? serialize( $payload['next_step_id'] ) : $payload['next_step_id']; //phpcs:ignore
			}
			$payload['updated_at'] = current_time( 'mysql' );

			if ( 'sendMail' === $payload['key'] ) {
				$settings = maybe_unserialize( $payload['settings'] );
				if ( isset( $settings['message_data']['body'] ) ) {
					$settings['message_data']['body'] = html_entity_decode( $settings['message_data']['body'] ); //phpcs:ignore
					$payload['settings']              = serialize( $settings ); //phpcs:ignore
				}
			}
			$updated = $wpdb->update(
				$automation_step_table,
				$payload,
				array( 'id' => $payload['id'] )
			); // db call ok. ; no-cache ok.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $updated ) {
				$updated_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $automation_step_table WHERE id = %d", $payload['id'] ) );
				return $updated_id;
			} else {
				return false;
			}
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Delete step by id
	 *
	 * @param int $id Set Automation ID.
	 */
	public function destroy( $id ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		if ( ! $this->is_automation_step_exist( $id ) ) {
			return false;
		}
		self::delete_child_row_by_step_id( $id );
		return $wpdb->delete( $automation_step_table, array( 'id' => $id ) ); // db call ok. ; no-cache ok.
	}


	/**
	 * Check existing automation step on database
	 *
	 * @param mixed $id  id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_automation_step_exist( $id ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $automation_step_table WHERE id = %d", array( $id ) );
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
	 * Check existing automation step on database
	 *
	 * @param mixed $step_id step id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function is_automation_step_exist_by_step_id( $step_id ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $automation_step_table WHERE step_id = %s", array( $step_id ) );
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
	 * Delete all row from child table by step ids
	 *
	 * @param string $step_ids Automation Step IDs.
	 * @return bool
	 */
	public static function delete_all_child_row_by_step_ids( $step_ids ) {
		if ( $step_ids ) {
			global $wpdb;
			$automation_log_table       = $wpdb->prefix . AutomationLogSchema::$table_name;
			$automation_step_meta_table = $wpdb->prefix . AutomationStepMetaSchema::$table_name;
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE step_id IN(%1s)', $automation_log_table, $step_ids ) ); //  phpcs:ignore.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE automation_step_id IN(%1s)', $automation_step_meta_table, $step_ids ) ); //  phpcs:ignore.
			return true;
		}
		return false;
	}


	/**
	 * Delete row from child table by step id
	 *
	 * @param int $step_id Automation Step ID.
	 * @return bool
	 */
	public static function delete_child_row_by_step_id( $step_id ) {
		if ( $step_id ) {
			global $wpdb;
			$automation_log_table       = $wpdb->prefix . AutomationLogSchema::$table_name;
			$automation_step_meta_table = $wpdb->prefix . AutomationStepMetaSchema::$table_name;

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->delete( $automation_log_table, array( 'step_id' => $step_id ) ); // db call ok. ; no-cache ok. //  phpcs:ignore.
			$wpdb->delete( $automation_step_meta_table, array( 'automation_step_id' => $step_id ) ); // db call ok. ; no-cache ok. //  phpcs:ignore.
			return true;
		}
		return false;
	}

	/**
	 * Retrieve an array of step IDs for a given automation ID.
	 *
	 * @param int $automation_id The ID of the automation to retrieve step IDs for.
	 *
	 * @return string A comma-separated string of step IDs associated with the given automation ID.
	 * @since 1.1.2
	 */
	public static function get_step_ids_by_automation_id( $automation_id ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		$ids                   = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $automation_step_table WHERE automation_id = %d", $automation_id ), ARRAY_A ); //phpcs:ignore.
		$ids                   = implode( ',', array_column( $ids, 'id' ) );
		return $ids;
	}

	/**
	 * Retrieve an array of step IDs for a given automation IDs.
	 *
	 * @param int $automation_ids The ID of the automation to retrieve step IDs for.
	 *
	 * @return string A comma-separated string of step IDs associated with the given automation ID.
	 * @since 1.1.2
	 */
	public static function get_step_ids_by_automation_ids( $automation_ids ) {
		global $wpdb;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;
		$ids                   = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $automation_step_table WHERE automation_id IN(%1s)", $automation_ids ), ARRAY_A ); //phpcs:ignore.
		$ids                   = implode( ',', array_column( $ids, 'id' ) );
		return $ids;
	}

	/**
	 * Retrieve step IDs associated with a specific campaign ID.
	 *
	 * This method queries the database to find step IDs that are associated with a particular campaign ID.
	 *
	 * @access public
	 *
	 * @param int $campaign_id The ID of the campaign to retrieve step IDs for.
	 *
	 * @return array An array containing step IDs associated with the specified campaign ID.
	 * @since 1.5.7
	 */
	public static function get_step_ids_by_campaign_id( $campaign_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . AutomationStepSchema::$table_name;
		$rows       = $wpdb->get_results( $wpdb->prepare( "SELECT step_id, settings FROM $table_name WHERE `key` = %s", 'sequence' ), ARRAY_A ); //phpcs:ignore.
		$step_ids   = array();

		foreach ( $rows as $row ) {
			$settings = isset( $row['settings'] ) ? maybe_unserialize( $row['settings'] ) : array();
			if ( is_array( $settings ) && isset( $settings['sequence_settings']['id'] ) && $settings['sequence_settings']['id'] === $campaign_id ) {
				$step_ids[] = $row['step_id'];
			}
		}
		return $step_ids;
	}

	/**
	 * Get the total number of recipients for an automation email.
	 *
	 * This function retrieves the total number of recipients to whom the specified
	 * automation email has been sent.
	 *
	 * @param int $email_id The ID of the automation email for which to retrieve the
	 *                      total number of recipients.
	 *
	 * @return int The total number of recipients to whom the automation email has been sent.
	 * @since 1.6.4
	 */
	public static function get_total_recipients_for_automation_email( $email_id ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(`id`) as total_sent FROM $email_table WHERE email_id = %d",$email_id ) );//  phpcs:ignore
	}
}
