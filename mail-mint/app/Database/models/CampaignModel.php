<?php
/**
 * Manage Campaign related database operations
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use MailMint\App\Helper;
use Mint\MRM\DataBase\Tables\CampaignEmailBuilderSchema;
use Mint\MRM\DataBase\Tables\CustomFieldSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;
use MailMintPro\Mint\Internal\Admin\Segmentation\FilterSegmentContacts;

/**
 * CampaignModel class
 *
 * Manage Contact Module database related operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class CampaignModel {



	use Singleton;

	/**
	 * Check existing campaign on database
	 *
	 * @param mixed $id Campaign id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_campaign_exist( $id ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $campaign_table WHERE id = %d", array( $id ) );
		$results      = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.

		if ( $results ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if a specific meta key exists for a campaign.
	 *
	 * This function checks if a given meta key exists for a specific campaign ID in the database.
	 *
	 * @param string $key         The meta key to check for.
	 * @param int    $campaign_id The ID of the campaign.
	 *
	 * @return bool True if the meta key exists, false otherwise.
	 * @since 1.6.0
	 */
	public static function is_campaign_meta_exist( $key, $campaign_id ) {
		global $wpdb;
		$table_name   = $wpdb->prefix . CampaignSchema::$campaign_meta_table;
		$select_query = $wpdb->prepare( "SELECT id FROM $table_name WHERE campaign_id = %d AND meta_key = %s", array( $campaign_id, $key ) ); //phpcs:ignore
		$results      = $wpdb->get_var( $select_query ); // db call ok. ; no-cache ok.
		if ( $results ) {
			return true;
		}
		return false;
	}

	/**
	 * Run SQL query to insert campaign information into database
	 *
	 * @param mixed $args araguments to insert data.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function insert( $args ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		unset( $args['rest_route'] );
		unset( $args['recipients'] );
		unset( $args['emails'] );
		unset( $args['_locale'] );
		$args['created_at'] = current_time( 'mysql', 1 );
		$args['updated_at'] = current_time( 'mysql', 1 );
		$args['title']      = $args['title'] ? $args['title'] : 'No title';

		$result = $wpdb->insert(
			$campaign_table,
			$args
		); // db call ok. ; no-cache ok.

		return $result ? self::get( $wpdb->insert_id ) : false;
	}


	/**
	 * Run SQL query to update campaign recipients information into database
	 *
	 * @param array $recipients recipients ids.
	 * @param int   $campaign_id camapaign id.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function insert_campaign_recipients( $recipients, $campaign_id ) {
		global $wpdb;
		$campaign_meta_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$inserted = $wpdb->insert(
			$campaign_meta_table,
			array(
				'meta_key'    => 'recipients',
				'meta_value'  => $recipients,
				'campaign_id' => $campaign_id,
			)
		); // db call ok. ; no-cache ok.
		if ( $inserted ) {
			return $wpdb->insert_id;
		}
		return false;
	}


	/**
	 * Run SQL query to update campaign emails information into database
	 *
	 * @param string $email email.
	 * @param int    $campaign_id campaign id.
	 * @param int    $index index no.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function insert_campaign_emails( $email, $campaign_id, $index ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		unset( $email['toError'] );
		unset( $email['senderEmailError'] );
		unset( $email['email_body'] );
		unset( $email['email_json'] );
		unset( $email['email_address'] );
		unset( $email['contact_id'] );
		unset( $email['email_hash'] );

		$email['campaign_id'] = $campaign_id;
		$email['created_at']  = current_time( 'mysql' );
		$email['email_index'] = $index;
		$inserted             = $wpdb->insert( $fields_table, $email ); // db call ok. ; no-cache ok.
		if ( $inserted ) {
			return $wpdb->insert_id;
		}
		return false;
	}


	/**
	 * Run SQL query to update campaign information into database
	 *
	 * @param object $args arguments.
	 * @param int    $id campaign id.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update( $args, $id ) {
		if ( 'schedule' === $args['status'] && 'recurring' === $args['type'] ) {
			if ( !empty( $args[ 'scheduled_at' ] ) ) {
				$args['scheduled_at'] = gmdate( 'Y-m-d 00:00:01', strtotime( $args['scheduled_at'] ) );
			}
		}
		global $wpdb;
		$fields_table       = $wpdb->prefix . CampaignSchema::$campaign_table;
		$args['updated_at'] = current_time( 'mysql' );
		unset( $args['campaign_id'] );
		unset( $args['rest_route'] );
		unset( $args['recipients'] );
		unset( $args['emails'] );
		unset( $args['totalRecipients'] );
		unset( $args['_locale'] );
		unset( $args['recurringData'] );
		$result = $wpdb->update( $fields_table, $args, array( 'id' => $id ) ); // db call ok. ; no-cache ok.
		return $result ? self::get( $id ) : false;
	}


	/**
	 * Insert campaign meta information into the database.
	 *
	 * @param int    $campaign_id The ID of the campaign.
	 * @param string $key         The meta key.
	 * @param mixed  $value       The meta value.
	 *
	 * @return int|false The ID of the inserted record, or false on failure.
	 * @since 1.6.0
	 */
	public static function insert_campaign_meta( $campaign_id, $key, $value ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		global $wpdb;
		$table     = $wpdb->prefix . CampaignSchema::$campaign_meta_table;
		$meta_args = array(
			'campaign_id' => $campaign_id,
			'meta_key'    => $key,
			'meta_value'  => $value,
			'created_at'  => current_time( 'mysql' ),
		);

		$wpdb->insert(
			$table,
			$meta_args
		); // db call ok. ; no-cache ok.
		return $wpdb->insert_id;
	}

	/**
	 * Update campaign meta information in the database.
	 *
	 * This function updates the specified meta key for a campaign with a new value.
	 *
	 * @param int    $campaign_id The ID of the campaign.
	 * @param string $key         The meta key to update.
	 * @param mixed  $value       The new value for the meta key.
	 *
	 * @return false|int The number of rows updated, or false on failure.
	 * @since 1.6.0
	 */
	public static function update_campaign_meta( $campaign_id, $key, $value ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		global $wpdb;
		$fields_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;

		return $wpdb->update(
			$fields_table,
			array(
				'meta_value' => $value,
			),
			array(
				'meta_key'    => $key,
				'campaign_id' => $campaign_id,
			)
		); // db call ok. ; no-cache ok.
	}

	/**
	 * Insert or update campaign meta information in the database.
	 *
	 * @param int    $campaign_id The ID of the campaign.
	 * @param string $key         The meta key.
	 * @param mixed  $value       The meta value.
	 *
	 * @return int|false The ID of the inserted or updated record, or false on failure.
	 * @since 1.6.0
	 */
	public static function insert_or_update_campaign_meta( $campaign_id, $key, $value ) {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		$meta_exist = self::is_campaign_meta_exist( $key, $campaign_id );
		if ( ! $meta_exist ) {
			return self::insert_campaign_meta( $campaign_id, $key, $value );
		} else {
			return self::update_campaign_meta( $campaign_id, $key, $value );
		}
	}

	/**
	 * Run SQL query to update campaign recipients into database
	 *
	 * @param string $recipients recipients ids.
	 * @param int    $campaign_id campaign id.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update_campaign_recipients( $recipients, $campaign_id ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;

		return $wpdb->update(
			$fields_table,
			array(
				'meta_value' => $recipients,
			),
			array(
				'meta_key'    => 'recipients',
				'campaign_id' => $campaign_id,
			)
		); // db call ok. ; no-cache ok.
	}


	/**
	 * Run SQL query to update campaign emails into database
	 *
	 * @param array $email email.
	 * @param int   $campaign_id campaign id.
	 * @param int   $index index.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update_campaign_emails( $email, $campaign_id, $index ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		unset( $email['email_body'] );
		unset( $email['email_json'] );
		$campaign_email = self::get_campaign_email_by_index( $campaign_id, $email );
		if ( $campaign_email ) {
			$wpdb->update(
				$fields_table,
				$email,
				array(
					'campaign_id' => $campaign_id,
					'id'          => $email['id'],
				)
			); // db call ok. ; no-cache ok.
		} else {
			return self::insert_campaign_emails( $email, $campaign_id, $index );
		}
		return true;
	}


	/**
	 * Run SQL Query to get a single campaign information
	 *
	 * @param mixed $id campaign ID.
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function get( $id ) {
		global $wpdb;
		$campaign_table      = $wpdb->prefix . CampaignSchema::$campaign_table;
		$campaign_meta_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;

		$select_query = $wpdb->prepare( "SELECT * FROM $campaign_table as CT LEFT JOIN $campaign_meta_table as CMT on CT.id = CMT.campaign_id WHERE CT.id = %d", $id );

		$campaign           = $wpdb->get_row( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
		$campaign['id']     = $id;
		$campaign['emails'] = self::get_campaign_email( $id );
		return $campaign;
	}


	/**
	 * Run SQL query to get or search campaigns from database
	 *
	 * @param mixed  $wpdb global variable to access WP database.
	 * @param int    $offset offset.
	 * @param int    $limit limit.
	 * @param string $search search.
	 * @param string $order_by sorting order.
	 * @param string $order_type sorting order type.
	 * @param string $filter filter value.
	 * @param string $filter_type filter type.
	 * @param string $status status filter value.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public static function get_all( $wpdb, $offset = 0, $limit = 10, $search = '', $order_by = 'id', $order_type = 'desc', $filter = '', $filter_type = '', $status = '' ) {
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		// Prepare search terms for query.
		$search_terms = array();
		if ( ! empty( $search ) ) {
			$search_terms[] = $wpdb->prepare( '`title` LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		// Prepare filter terms for query.
		$filter_terms = array();
		if ( ! empty( $filter ) && 'all' !== $filter ) {
			$filter_terms[] = $wpdb->prepare( "{$filter_type} = %s", $filter );
		}

		// Prepare status filter terms for query.
		$status_terms = array();
		if ( ! empty( $status ) && 'all' !== $status ) {
			$status_terms[] = $wpdb->prepare( 'status = %s', $status );
		}

		$where = '';
		if ( !empty( $search_terms ) || !empty( $filter_terms ) || !empty( $status_terms ) ) {
			$where = 'WHERE ' . implode( ' AND ', array_merge( $search_terms, $filter_terms, $status_terms ) );
		}

		// Prepare sql results for list view.
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $campaign_table $where ORDER BY $order_by $order_type  LIMIT %d, %d", $offset, $limit ), ARRAY_A ); // db call ok. ; no-cache ok.

		$count       = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) as total FROM $campaign_table $where" ) ); // db call ok. ; no-cache ok.
		$total_pages = ceil( $count / $limit );
		return array(
			'campaigns'   => $results,
			'total_pages' => $total_pages,
			'count'       => $count,
		);
	}

	/**
	 * Returns campaign email data for Cron background process
	 *
	 * @param int    $id   campaign ID.
	 * @param string $scheduled_at date.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_email_for_background( $id, $scheduled_at = null ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$campaign_emails_query = $wpdb->prepare( "SELECT * FROM {$campaign_emails_table} WHERE `campaign_id` = %d AND `status` = %s", $id, 'scheduled' );
		if ( $scheduled_at ) {
			$campaign_emails_query .= $wpdb->prepare( 'AND `scheduled_at` <= %s', $scheduled_at );
		}
		return $wpdb->get_results( $campaign_emails_query, ARRAY_A ); // db call ok. ; no-cache ok.
	}


	/**
	 * Returns campaign email data
	 *
	 * @param int $id   campaign ID.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_email( $id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder_table   = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		$campaign_emails_query = $wpdb->prepare(
			"SELECT
                                               CET.id, delay, delay_count, delay_value,
                                               send_time, sender_email, sender_name, reply_email, reply_name,
                                               email_index, email_subject, email_preview_text,
                                               template_id, CET.status, scheduled_at,
                                               EBT.json_data, EBT.email_body as body_data
                                               FROM $campaign_emails_table
                                               as CET LEFT JOIN $email_builder_table
                                               as EBT
                                               on CET.id = EBT.email_id
                                               WHERE CET.campaign_id = %d",
			$id
		);
		$emails                = $wpdb->get_results( $campaign_emails_query, ARRAY_A ); // db call ok. ; no-cache ok.
		if ( ! empty( $emails ) ) {
			$emails = array_map(
				function ( $email ) {
					$email_json          = isset( $email['json_data'] ) ? $email['json_data'] : '';
					$email['email_json'] = unserialize( $email_json );  		 //phpcs:ignore
					return $email;
				},
				$emails
			);
		}

		return $emails;
	}


	/**
	 * Return duplicate campaign data.
	 *
	 * @param mixed $campaign_id Get campaign ID.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_to_duplicate( $campaign_id ) {
		global $wpdb;
		$campaign_table      = $wpdb->prefix . CampaignSchema::$campaign_table;
		$campaign_meta_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;

		$select_query = $wpdb->prepare( "SELECT * FROM $campaign_table as CT LEFT JOIN $campaign_meta_table as CMT on CT.id = CMT.campaign_id WHERE CT.id = %d", $campaign_id );

		$campaign           = $wpdb->get_row( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
		$campaign['id']     = $campaign_id;
		$campaign['emails'] = self::get_campaign_email_to_duplicate( $campaign_id );
		return $campaign;
	}


	/**
	 * Returns duplicate campaign email data
	 *
	 * @param int $id   campaign ID.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_email_to_duplicate( $id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder_table   = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		$campaign_emails_query = $wpdb->prepare(
			"SELECT
                                               CET.id, delay, delay_count, delay_value,
                                               send_time, sender_email, sender_name, reply_email, reply_name,
                                               email_index, email_subject, email_preview_text,
                                               template_id, CET.status, scheduled_at,
                                               EBT.json_data, EBT.email_body
                                               FROM $campaign_emails_table
                                               as CET LEFT JOIN $email_builder_table
                                               as EBT
                                               on CET.id = EBT.email_id
                                               WHERE CET.campaign_id = %d",
			$id
		);
		$emails                = $wpdb->get_results( $campaign_emails_query, ARRAY_A ); // db call ok. ; no-cache ok.
		return $emails;
	}



	/**
	 * Get an email by its index for a specific campaign
	 *
	 * @param mixed $campaign_id campaign id.
	 * @param mixed $email index of the email.
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function get_campaign_email_by_index( $campaign_id, $email ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_id              = isset( $email['id'] ) ? $email['id'] : '';
		$campaign_emails_query = $wpdb->prepare(
			"SELECT
                                    id,email_index
                                     FROM $campaign_emails_table
                                     WHERE campaign_id = %d AND id = %d",
			$campaign_id,
			$email_id
		);
		return $wpdb->get_row( $campaign_emails_query ); // db call ok. ; no-cache ok.
	}


	/**
	 * Delete a campaign from the database
	 *
	 * @param mixed $id Campaign id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy( $id ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		try {
			$scheduled_email_delete = EmailModel::delete_scheduled_emails( 'campaign_id', $id );

			// Deletes child rows in campaign_meta_table and campaign_emails_table for a given campaign ID.
			self::delete_child_row_campaign_id( $id );

			if ( $scheduled_email_delete ) {
				return $wpdb->delete( $campaign_table, array( 'id' => $id ) ); // db call ok. ; no-cache ok.
			}
			return false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Deletes child rows in campaign_meta_table and campaign_emails_table for a given campaign ID.
	 *
	 * @param mixed $campaign_id Campaign ID for which the child rows are to be deleted.
	 *
	 * @return void
	 * @since 1.2.2
	 */
	public static function delete_child_row_campaign_id( $campaign_id ) {
		global $wpdb;
		$campaign_meta_table   = $wpdb->prefix . CampaignSchema::$campaign_meta_table;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$wpdb->delete( $campaign_meta_table, array( 'campaign_id' => $campaign_id ) ); //  phpcs:ignore.
		$email_ids = CampaignEmailBuilderModel::get_email_ids_by_campaign_id( $campaign_id );
		CampaignEmailBuilderModel::delete_all_child_row_by_email_ids( $email_ids );
		$wpdb->delete( $campaign_emails_table, array( 'campaign_id' => $campaign_id ) ); //  phpcs:ignore.
	}

	/**
	 * Delete multiple campaigns from the database
	 *
	 * @param array $ids multiple campaigns.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy_all( $ids ) {
		global $wpdb;

		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		if ( is_array( $ids ) && !empty( $ids ) ) {
			foreach ( $ids as $id ) {
				EmailModel::delete_scheduled_emails( 'campaign_id', $id );
			}
			$ids = implode( ',', array_map( 'intval', $ids ) );
			self::delete_all_child_row_by_campaign_ids( $ids );
			return $wpdb->query( "DELETE FROM $campaign_table WHERE id IN ( $ids )" ); // db call ok. ; no-cache ok.
		}
		return false;
	}

	/**
	 * Deletes all child rows associated with the given campaign IDs.
	 *
	 * @param array $campaign_ids An array of campaign IDs to delete child rows for.
	 * @return bool
	 * @since 1.2.2
	 */
	public static function delete_all_child_row_by_campaign_ids( $campaign_ids ) {
		if ( $campaign_ids ) {
			global $wpdb;
			$campaign_meta_table   = $wpdb->prefix . CampaignSchema::$campaign_meta_table;
			$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE campaign_id IN(%1s)', $campaign_meta_table, $campaign_ids ) ); //  phpcs:ignore.
			$email_ids = CampaignEmailBuilderModel::get_step_ids_by_campaign_ids( $campaign_ids );
			CampaignEmailBuilderModel::delete_all_child_row_by_email_ids( $email_ids );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE campaign_id IN(%1s)', $campaign_emails_table, $campaign_ids ) ); //  phpcs:ignore.

			return true;
		}
		return false;
	}


	/**
	 * Delete a email from campaign
	 *
	 * @param int $campaign_id campaign id.
	 * @param int $email_id email id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function remove_email_from_campaign( $campaign_id, $email_id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		return $wpdb->delete(
			$campaign_emails_table,
			array(
				'id'          => $email_id,
				'campaign_id' => $campaign_id,
			)
		); // db call ok. ; no-cache ok.
	}


	/**
	 * Get campaign email id by email index of that campaign
	 *
	 * @param int $campaign_id campaign id.
	 * @param int $email_index email index.
	 * @return object|array
	 *
	 * @since 1.0.0
	 */
	public static function get_email_by_index( $campaign_id, $email_index ) {
		global $wpdb;
		$email_table  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$select_query = $wpdb->prepare( "SELECT * FROM {$email_table} WHERE campaign_id=%s AND email_index=%s", $campaign_id, $email_index );
		return $wpdb->get_row( $select_query ); // db call ok. ; no-cache ok.
	}

	/**
	 * Get campaign email by email id of that campaign
	 *
	 * @param int $campaign_id campaign id.
	 * @param int $email_index email index.
	 * @return object|array
	 *
	 * @since 1.0.0
	 */
	public static function get_campaign_email_by_id( $campaign_id, $email_index ) {
		global $wpdb;
		$email_table  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$select_query = $wpdb->prepare( "SELECT * FROM {$email_table} WHERE campaign_id=%s AND id=%s", $campaign_id, $email_index ); // db call ok. ; no-cache ok.
		return $wpdb->get_row( $select_query ); // db call ok. ; no-cache ok.
	}


	/**
	 * Returns all publish campaigns
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_publish_campaign_id() {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		$select_query = $wpdb->prepare( "SELECT `id` FROM {$campaign_table} WHERE `type` IN( %s, %s ) AND `status` = %s", 'regular', 'sequence', 'active' );
		return $wpdb->get_results( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
	}


	/**
	 * Returns all schedule campaigns
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_schedule_campaign() {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		$sql_query      = "SELECT * FROM {$campaign_table} ";
		$sql_query     .= 'WHERE `status` = %s ';
		$sql_query     .= 'AND `scheduled_at` <= %s ';
		$sql_query      = $wpdb->prepare( $sql_query, 'schedule', current_time( 'mysql' ) ); //phpcs:ignore
		return $wpdb->get_results( $sql_query, ARRAY_A ); //phpcs:ignore
	}


	/**
	 * Update a campaign status
	 *
	 * @param mixed $campaign_id campaign id.
	 * @param mixed $status status.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update_campaign_status( $campaign_id, $status ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		return $wpdb->update(
			$fields_table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $campaign_id )
		); // db call ok. ; no-cache ok.
	}


	/**
	 * Update a campaign email status
	 *
	 * @param mixed $campaign_id campaign id.
	 * @param mixed $email_id email id.
	 * @param mixed $status status.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update_campaign_email_status( $campaign_id, $email_id, $status ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		return $wpdb->update(
			$campaign_emails_table,
			array(
				'status' => $status,
			),
			array(
				'id'          => $email_id,
				'campaign_id' => $campaign_id,
			)
		); // db call ok. ; no-cache ok.
	}


	/**
	 * Get email template data from email builder
	 *
	 * @param mixed $campaign_id campaign id.
	 * @param mixed $email_id email id.
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function get_campaign_email_to_builder( $campaign_id, $email_id ) {
		global $wpdb;
		$email_table  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$select_query = $wpdb->prepare( "SELECT * FROM {$email_table} WHERE campaign_id=%s AND id=%s", $campaign_id, $email_id );
		return $wpdb->get_row( $select_query ); // db call ok. ; no-cache ok.
	}


	/**
	 * Get email template data from email builder
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function get_last_inserted_campaign_email() {
		global $wpdb;
		$email_table  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$select_query = $wpdb->prepare( "SELECT id FROM {$email_table} ORDER BY id DESC LIMIT 1" );
		return $wpdb->get_var( $select_query ); // db call ok. ; no-cache ok.
	}


	/**
	 * Return campaign's emaild meta value
	 *
	 * @param mixed $email_id email id.
	 * @param mixed $key meta key.
	 *
	 * @return bool|int
	 * @since 1.0.0
	 */
	public static function get_campaign_email_meta( $email_id, $key ) {
		global $wpdb;
		$email_meta_table = $wpdb->prefix . CampaignSchema::$campaign_emails_meta_table;
		$select_query     = $wpdb->prepare( "SELECT meta_value FROM {$email_meta_table} WHERE campaign_emails_id=%d AND meta_key=%s", $email_id, $key );
		$meta_data        = $wpdb->get_col( $select_query ); // db call ok. ; no-cache ok.
		return isset( $meta_data[0] ) ? $meta_data[0] : false;
	}


	/**
	 * Update campaign's email meta fields
	 *
	 * @param mixed $email_id email id.
	 * @param mixed $key meta key.
	 * @param mixed $value meta value.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function update_campaign_email_meta( $email_id, $key, $value ) {
		global $wpdb;
		$email_meta_table = $wpdb->prefix . CampaignSchema::$campaign_emails_meta_table;
		$is_meta          = self::get_campaign_email_meta( $email_id, $key );

		if ( ! $is_meta ) {
			$wpdb->insert(
				$email_meta_table,
				array(
					'campaign_emails_id' => $email_id,
					'meta_key'           => $key,
					'meta_value'         => $value,
				)
			); // db call ok.
		} else {
			$wpdb->update(
				$email_meta_table,
				array(
					'meta_value' => $value,
				),
				array(
					'campaign_emails_id' => $email_id,
					'meta_key'           => $key,
				)
			); // db call ok. ; no-cache ok.
		}
	}


	/**
	 * Return campaign's email information to analytics
	 *
	 * @param mixed $campaign_id campaign id.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_email_ids( $campaign_id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$select_query = $wpdb->prepare( "SELECT id FROM {$campaign_emails_table} WHERE campaign_id = %d", $campaign_id );
		return $wpdb->get_results( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
	}

	/**
	 * Get attributes of a campaign email to be sent.
	 *
	 * This function retrieves the attributes of a campaign email, including its subject, preview text, and body,
	 * for a given campaign ID and email ID from the database.
	 *
	 * @param int $campaign_id The ID of the campaign.
	 * @param int $email_id    The ID of the email.
	 *
	 * @return array|null An array containing the campaign email attributes if found, or null if not found.
	 *
	 * @since 1.5.20
	 */
	public static function get_campaign_email_attributes_to_sent( $campaign_id, $email_id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder_table   = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		$select_query = $wpdb->prepare(
			"SELECT ce.id AS campaign_email_id, ce.campaign_id, ce.email_subject, ce.email_preview_text, ceb.id AS email_builder_id, ceb.editor_type, ceb.email_body FROM $campaign_emails_table
		as ce JOIN $email_builder_table
		as ceb
		on ce.id = ceb.email_id
		WHERE ce.campaign_id = %d 
		AND ceb.email_id = %d",
			$campaign_id,
			$email_id
		);
		return $wpdb->get_row( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
	}


	/**
	 * Prepare email open rate for specific campaign
	 *
	 * @param mixed $campaign_id Campaign ID.
	 * @param mixed $total_recipients Total recipients.
	 * @param mixed $total_bounced Total bounced.
	 *
	 * @return float
	 * @since 1.0.0
	 */
	public static function prepare_campaign_open_rate( $campaign_id, $total_recipients, $total_bounced ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$email_count  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $campaign_emails_table WHERE campaign_id = %d", $campaign_id ) ); // db call ok. ; no-cache ok.
		$total_opened = EmailModel::calculate_open_rate_on_campaign( $campaign_id );
		$divide_by    = ( $total_recipients * $email_count ) - $total_bounced;
		$divide_by    = 0 === $divide_by ? 1 : $divide_by;
		return number_format( (float) ( $total_opened / $divide_by ) * 100, 2, '.', '' );
	}

	/**
	 * Prepare email click rate for specific campaign
	 *
	 * @param mixed $campaign_id Campaign ID.
	 * @param mixed $total_recipients Total recipients.
	 * @param mixed $total_bounced Total bounced.
	 *
	 * @return float
	 * @since 1.0.0
	 */
	public static function prepare_campaign_click_rate( $campaign_id, $total_recipients, $total_bounced ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$email_count   = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $campaign_emails_table WHERE campaign_id = %d", $campaign_id ) ); // db call ok. ; no-cache ok.
		$total_clicked = EmailModel::calculate_click_rate_on_campaign( $campaign_id );

		$divide_by = ( $total_recipients * $email_count ) - $total_bounced;
		$divide_by = 0 === $divide_by ? 1 : $divide_by;
		return number_format( (float) ( $total_clicked / $divide_by ) * 100, 2, '.', '' );
	}


	/**
	 * Returns campaign meta value
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $key Campaign meta key.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_meta_value( $campaign_id, $key ) {
		global $wpdb;
		$campaign_meta_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $campaign_meta_table WHERE campaign_id = %d AND meta_key = %s", array( $campaign_id, $key ) ) ); // db call ok. ; no-cache ok.
		return $meta_value ?: 0;
	}

	/**
	 * Returns specific campaign value
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $key Campaign value key.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_campaign_value( $campaign_id, $key ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;

		return $wpdb->get_var( $wpdb->prepare( "SELECT $key FROM $campaign_table  WHERE id = %d", array( $campaign_id ) ) ); // db call ok. ; no-cache ok.
	}

	/**
	 * Replace custom placeholders from email subject
	 *
	 * @param string $data String value of email subject/preview/body text.
	 * @param int    $contact_id MRM contact id.
	 *
	 * @return array|string
	 * @since 1.0.0
	 */
	public static function replace_test_mail_dynamic_placeholders( string $data, int $contact_id ) {
		$contact = ContactModel::get( $contact_id );
		if ( empty( $contact ) ) {
			$user_info  = get_userdata( $contact_id );
			$first_name = $user_info->first_name;
			$last_name  = $user_info->last_name;
		} else {
			$first_name = isset( $contact['first_name'] ) ? $contact['first_name'] : '';
			$last_name  = isset( $contact['last_name'] ) ? $contact['last_name'] : '';
		}
		$email       = isset( $contact['email'] ) ? $contact['email'] : '';
		$company     = isset( $contact['meta_fields']['company'] ) ? $contact['meta_fields']['company'] : '';
		$designation = isset( $contact['meta_fields']['designation'] ) ? $contact['meta_fields']['designation'] : '';
		$city        = isset( $contact['meta_fields']['city'] ) ? $contact['meta_fields']['city'] : '';
		$state       = isset( $contact['meta_fields']['state'] ) ? $contact['meta_fields']['state'] : '';
		$country     = isset( $contact['meta_fields']['country'] ) ? $contact['meta_fields']['country'] : '';
		$address_1   = isset( $contact['meta_fields']['address_line_1'] ) ? $contact['meta_fields']['address_line_1'] : '';
		$address_2   = isset( $contact['meta_fields']['address_line_2'] ) ? $contact['meta_fields']['address_line_2'] : '';
		$hash        = isset( $contact['hash'] ) ? $contact['hash'] : '#';
		$meta_fields = !empty( $contact['meta_fields'] ) ? $contact['meta_fields'] : array();
		$data        = Helper::replace_placeholder_email_subject_preview( $data, $first_name, $last_name, $email, $city, $state, $country, $company, $designation, $meta_fields );
		$data        = Helper::replace_placeholder_email_body( $data, $first_name, $last_name, $email, $address_1, $address_2, $company, $designation, $meta_fields );
		$data        = Helper::replace_placeholder_business_setting( $data, $hash );

		// Replace subscribe link.
		$subscribe_url = site_url( '?mrm=1&route=confirmation&hash=' . $hash );

		$data = str_replace( '{{subscribe_link}}', $subscribe_url, $data );
		$data = str_replace( '{{link.subscribe}}', $subscribe_url, $data );

		$subscribe_text     = Helper::get_pipe_text( 'link.subscribe_html', $data, $subscribe_url );
		$subscribe_url_html = '<a href ="' . $subscribe_url . '">' . $subscribe_text . '</a>';

		$data = Helper::replace_pipe_data( 'link.subscribe_html', $data, $subscribe_url_html );
		$data = str_replace( '{{link.subscribe_html|' . $subscribe_text . '}}', $subscribe_url_html, $data );

		$data = str_replace( '#subscribe_link#', $subscribe_url, $data );
		/**
		 * Summary: Applies the 'mint_test_email_preview' filter to the provided data.
		 *
		 * Description: The 'mint_test_email_preview' filter allows modifying or manipulating the data before it is used or displayed.
		 *
		 * @param string mixed $data The data to be filtered.
		 *
		 * @return mixed The filtered data.
		 * @since 1.5.0
		 */
		$data = apply_filters( 'mint_test_email_preview', $data );
		return $data;
	}

	/**
	 * Replace image placeholder
	 *
	 * @param array $data get data.
	 * @return mixed
	 */
	private static function replace_image_tag_with_placeholder_for_test( $data ) {
		$image = '<img style="width:120px; height:120px" src="' . $data[ 'logo_url' ] . '">';
		return $image;
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

	/**
	 * Schedule async as schedule for scheduling campaign emails
	 *
	 * @param int        $campaign_id Campaign id.
	 * @param array      $email Campaign email.
	 * @param string     $status Campaign status.
	 * @param string     $schedule Campaign schedule datetime.
	 * @param int|string $offset Offset.
	 * @param int|string $per_batch Fetch per batch.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function schedule_campaign_action( int $campaign_id, array $email, string $status, string $schedule = '', $offset = 0, $per_batch = 200 ) {
		if ( $campaign_id && ! empty( $email ) ) {
			$args  = array(
				array(
					'campaign_id'     => $campaign_id,
					'campaign_status' => $status,
					'email'           => $email,
					'offset'          => $offset,
					'per_batch'       => $per_batch,
				),
			);
			$group = 'mailmint-campaign-schedule-' . $campaign_id;

			if ( defined( 'MAILMINT_SCHEDULE_EMAILS' ) && ! as_has_scheduled_action( MAILMINT_SCHEDULE_EMAILS, $args, $group ) ) {
				if ( empty( $email[ 'delay_count' ] ) && empty( $schedule ) ) {
					as_enqueue_async_action( MAILMINT_SCHEDULE_EMAILS, $args, $group );
				} else {
					if ( ! empty( $schedule ) ) {
						$current_date   = new \DateTime( 'now', wp_timezone() );
						$scheduled_date = date_create( gmdate( 'Y-m-d H:i:s', strtotime( $schedule ) ), wp_timezone() );
						$date_diff      = date_diff( $scheduled_date, $current_date );
						$date_diff      = '+' . $date_diff->y . 'year' . $date_diff->m . 'month' . $date_diff->d . 'day' . $date_diff->h . 'hour' . $date_diff->i . 'minute' . ( $date_diff->s + 1 ) . 'second';
						$scheduled_at   = strtotime( $date_diff );
					} else {
						$scheduled_at = strtotime( '+' . $email[ 'delay_count' ] . ' ' . str_replace( 's', '', $email[ 'delay_value' ] ) );
					}
					as_schedule_single_action( $scheduled_at, MAILMINT_SCHEDULE_EMAILS, $args, $group );
				}
			}
		}
	}

	/**
	 * Get the first campaign id
	 *
	 * @param int $campaign_id Campaign id.
	 *
	 * @return array|object|\stdClass|void|null
	 *
	 * @since 1.0.0
	 */
	public static function get_first_campaign_email( int $campaign_id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$query  = 'SELECT `id`, `email_subject`, `email_preview_text`, ';
		$query .= '`sender_email`, `sender_name`, `reply_email`, ';
		$query .= '`reply_name`, `delay_count`, `delay_value` ';
		$query .= "FROM {$campaign_emails_table} ";
		$query .= 'WHERE `campaign_id` = %d ';
		$query .= 'AND `status` = %s ';
		$query .= 'ORDER BY `id` ASC ';
		$query .= 'LIMIT 1';
		$query  = $wpdb->prepare( $query, $campaign_id, 'scheduling' );

		return $wpdb->get_row( $query, ARRAY_A ); //phpcs:ignore
	}

	/**
	 * Get the last campaign id
	 *
	 * @param int $campaign_id Campaign id.
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	public static function get_last_campaign_email_id( int $campaign_id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$query  = "SELECT `id` FROM {$campaign_emails_table} ";
		$query .= 'WHERE `campaign_id` = %d ';
		$query .= 'ORDER BY `id` DESC ';
		$query .= 'LIMIT 1';
		$query  = $wpdb->prepare( $query, $campaign_id );

		return $wpdb->get_var( $query ); //phpcs:ignore
	}

	/**
	 * Schedule async action for sending emails
	 *
	 * @param int $campaign_id Campaign id.
	 * @param int $email_id Campaign Email id.
	 * @param int $batch Batch number.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function schedule_async_send_email_action( int $campaign_id, int $email_id, int $batch = 1 ) {
		if ( $campaign_id ) {
			$args  = array(
				array(
					'campaign_id' => $campaign_id,
					'email_id'    => $email_id,
					'batch'       => $batch,
				),
			);
			$group = 'mailmint-campaign-email-sending-' . $campaign_id;

			if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) && ! as_has_scheduled_action( MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group ) ) {
				as_enqueue_async_action( MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group );
			}
		}
	}

	/**
	 * Schedule async action for sending emails
	 *
	 * @param int    $campaign_id Campaign id.
	 * @param int    $email_id Campaign Email id.
	 * @param int    $batch Batch number.
	 * @param string $frequency_time The time delay in minutes before sending the email.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function schedule_single_send_email_action_delay( $campaign_id, $email_id, $batch, $frequency_time ) {
		if ( $campaign_id ) {
			$args  = array(
				array(
					'campaign_id' => $campaign_id,
					'email_id'    => $email_id,
					'batch'       => $batch,
				),
			);
			$group = 'mailmint-campaign-email-sending-' . $campaign_id;

			if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) && ! as_has_scheduled_action( MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group ) ) {
				as_schedule_single_action( time() + ( (int) $frequency_time * 60 ), MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group );
			}
		}
	}

	/**
	 * Removes all campaign actions
	 *
	 * @param string|int $campaign_id Campaign id.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function unschedule_campaign_actions( $campaign_id ) {
		$campaign_group            = 'mailmint-campaign-schedule-' . $campaign_id;
		$campaign_send_email_group = 'mailmint-campaign-email-sending-' . $campaign_id;
		$recurring_campaign_group  = 'mailmint-recurring-campaign-schedule-' . $campaign_id;
		MrmCommon::delete_as_actions( $campaign_group );
		MrmCommon::delete_as_actions( $campaign_send_email_group );
		MrmCommon::delete_as_actions( $recurring_campaign_group );
	}

	/**
	 * Retrieves all custom fields.
	 *
	 * This function retrieves all custom fields and returns an array of field slugs.
	 *
	 * @access public
	 *
	 * @return array Returns an array containing custom field slugs.
	 * @since 1.5.1
	 */
	public static function get_all_customfield() {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;

		// Return field froups for list view.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT slug FROM $fields_table WHERE type = 'text' OR type = 'textArea'  ORDER BY  %s  ", 'DESC' );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$formate_slug = array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$results
		);
		return $formate_slug;
	}

	/**
	 * Get the type of a campaign based on its ID.
	 *
	 * @param int $campaign_id The ID of the campaign.
	 *
	 * @return string|false The type of the campaign or false if not found.
	 * @since 1.7.0
	 */
	public static function get_campaign_type( $campaign_id ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$select_query = $wpdb->prepare( "SELECT type FROM $campaign_table WHERE id = %d", array( $campaign_id ) );
		return $wpdb->get_var( $select_query ); // db call ok. ; no-cache ok.
	}

	/**
	 * Get campaign details for analytics.
	 *
	 * This function retrieves campaign details for analytics purposes, including the
	 * emails associated with the campaign.
	 *
	 * @param int $id The ID of the campaign for which analytics data is requested.
	 *
	 * @return array The campaign details including associated emails.
	 *
	 * @since 1.9.0
	 */
	public static function get_campaign_to_analytics( $id ) {
		global $wpdb;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_table;
		$select_query   = $wpdb->prepare( "SELECT * FROM $campaign_table WHERE id = %d", $id );

		$campaign           = $wpdb->get_row( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
		$campaign['emails'] = self::get_campaign_emails_to_analytics( $id );
		return $campaign;
	}

	/**
	 * Get campaign emails for analytics.
	 *
	 * This function retrieves emails associated with a campaign for analytics purposes.
	 *
	 * @param int $id The ID of the campaign for which emails are requested.
	 *
	 * @return array An array containing details of emails associated with the campaign.
	 *
	 * @since 1.9.0
	 */
	public static function get_campaign_emails_to_analytics( $id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder_table   = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		$campaign_emails_query = $wpdb->prepare(
			"SELECT
                                               CET.id, send_time, sender_email, sender_name,
                                               email_index, email_subject, email_preview_text, CET.status, scheduled_at,
                                               EBT.email_body as body_data
                                               FROM $campaign_emails_table
                                               as CET LEFT JOIN $email_builder_table
                                               as EBT
                                               on CET.id = EBT.email_id
                                               WHERE CET.campaign_id = %d",
			$id
		);
		return $wpdb->get_results( $campaign_emails_query, ARRAY_A ); // db call ok. ; no-cache ok.
	}

	/**
	 * Get campaign recipients email
	 * 
	 * This function retrieves the email addresses of the recipients of a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $offset Offset.
	 * @param int $per_batch Fetch per batch.
	 *
	 * @return array
	 *
	 * @since 1.13.6
	 */
	public static function get_campaign_recipients_email( $campaign_id, $offset = 0, $per_batch = 0 ){
		$all_recipients = self::get_campaign_meta_value( $campaign_id, 'recipients' );
		$all_recipients = maybe_unserialize( $all_recipients );
		$contacts       = array();

		if ( !empty( $all_recipients['segments'] ) ) {
			$segment_id = isset( $all_recipients['segments'][0]['id'] ) ? $all_recipients['segments'][0]['id'] : 0;

			if (class_exists('MailMintPro\Mint\Internal\Admin\Segmentation\FilterSegmentContacts')) {
				$segment_data = FilterSegmentContacts::get_segment($segment_id, 'id, email, status', $offset, $per_batch);

				if (!empty($segment_data['contacts']['data'])) {
					foreach ($segment_data['contacts']['data'] as $contact) {
						if ($contact['status'] === 'subscribed') {
							$contacts[$contact['id']] = $contact;
						}
					}
				}
			}

			return array_values( $contacts );
		} else {
			$group_ids = array_merge(
				$all_recipients['lists'] ?? array(),
				$all_recipients['tags'] ?? array()
			);

			if (!empty($group_ids)) {
				$recipients_ids = ContactGroupPivotModel::get_contacts_to_group(array_column($group_ids, 'id'), $offset, $per_batch);
				$recipients_ids = array_column($recipients_ids, 'contact_id');
				$contacts = ContactModel::get_single_email($recipients_ids);
			}

			return $contacts;
		}
	}
}
