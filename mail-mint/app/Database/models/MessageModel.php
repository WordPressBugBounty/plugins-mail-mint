<?php
/**
 * Manage message related databse operation.
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
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use Mint\MRM\DataBase\Tables\CampaignEmailBuilderSchema;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;
use Mint\MRM\DataBase\Tables\EmailMetaSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;
use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;


/**
 * EmailModel class
 *
 * Manage message related databse operation.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class EmailModel {

	/**
	 * SQL query to create a new email
	 *
	 * @param mixed $email Mint email array.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function insert( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . EmailSchema::$table_name;

		$email['created_at'] = current_time( 'mysql' );
		unset( $email['email_subject'] );
		unset( $email['email_body'] );
		$wpdb->insert(
			$table,
			$email
		); // db call ok. ; no-cache ok.
		return $wpdb->insert_id;
	}


	/**
	 * SQL query to insert a new row on broadcast email meta
	 *
	 * @param mixed $email_meta Mint email meta array.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function insert_broadcast_email_meta( $email_meta ) {
		global $wpdb;
		$table = $wpdb->prefix . EmailMetaSchema::$table_name;
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$wpdb->insert(
			$table,
			$email_meta
		); // db call ok. ; no-cache ok.
		return $wpdb->insert_id;
	}


	/**
	 * SQL query to update row on broadcast email meta
	 *
	 * @param mixed $email_meta Mint email meta array.
	 * @param mixed $key Mint email meta key.
	 * @param mixed $email_id Mint broadcast email id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update_broadcast_email_meta( $email_meta, $key, $email_id ) {
		global $wpdb;
		$table = $wpdb->prefix . EmailMetaSchema::$table_name;
		$wpdb->update(
			$table,
			$email_meta,
			array(
				'mint_email_id' => $email_id,
				'meta_key'      => $key,
			)
		); // db call ok. no-cache ok.
		return true;
	}


	/**
	 * SQL query to get all emails relate to a contact or all users
	 *
	 * @param mixed $offset offset.
	 * @param mixed $limit limit.
	 * @param mixed $search search.
	 * @param mixed $contact_id contact id.
	 * @return bool\array
	 * @since 1.0.0
	 */
	public static function get_emails_to_contact( $offset = 0, $limit = 10, $search = '', $contact_id = null ) {
		global $wpdb;
		$table_name   = $wpdb->prefix . EmailSchema::$table_name;
		$search_terms = null;

		// Search email by address, or subject.
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "WHERE email_address LIKE '%" . $search . "%' OR email_subject LIKE '%" . $search . "%'";
		}

		if ( ! empty( $contact_id ) ) {
			$search_terms = "WHERE contact_id = {$contact_id}";
		}

		if ( ! empty( $search ) && ! empty( $contact_id ) ) {
			$search_terms = "WHERE email_address LIKE '%" . $search . "%' OR email_subject LIKE '%" . $search . "%' AND contact_id = '%" . $contact_id . "%'";
		}

		// Prepare sql results for list view.
		try {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
			$select_query = $wpdb->prepare( "SELECT * FROM {$table_name} %s ORDER BY id DESC LIMIT %d, %d", array( $search_terms, $offset, $limit ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared 
			$query_results = $wpdb->get_results( $select_query ); // db call ok. no-cache ok.
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared 
			$results = json_decode( wp_json_encode( $query_results ), true );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
			$count_query = $wpdb->prepare( "SELECT COUNT(*) as total FROM {$table_name} %s", $search_terms );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared 
			$count_data = $wpdb->get_results( $count_query ); // db call ok. no-cache ok.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared 
			$count_array = json_decode( wp_json_encode( $count_data ), true );

			$count       = (int) $count_array['0']['total'];
			$total_pages = ceil( $count / $limit );

			return array(
				'data'        => $results,
				'total_pages' => $total_pages,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}



	/**
	 * SQL query to get all emails relate to a contact
	 *
	 * @param mixed $contact_id contact id.
	 * @return bool\array
	 * @since 1.0.0
	 */
	public static function get_messages( $contact_id ) {
		global $wpdb;
		$message_table_name = $wpdb->prefix . EmailSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
		$sql = $wpdb->prepare( "SELECT * FROM {$message_table_name} WHERE `contact_id` = %d", $contact_id );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared 

		try {
			$messages = $wpdb->get_results( $sql, ARRAY_A ); // db call ok. no-cache ok.
			$index    = 0;

			foreach ( $messages as $message ) {
				if ( isset( $message['created_at'] ) ) {
					$messages[ $index ]['created_time'] = $message['created_at'];
					$messages[ $index ]['created_at']   = human_time_diff( strtotime( $message['created_at'] ), time() );
					$index++;
				}
			}
			return $messages;
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * SQL query to update emails
	 *
	 * @param int $message_id message id.
	 * @param int $key key.
	 * @param int $value value.
	 * @return void
	 * @since 1.0.0
	 */
	public static function update( $message_id, $key, $value ) {
		global $wpdb;
		$msg_table_name = $wpdb->prefix . EmailSchema::$table_name;
		$wpdb->update( $msg_table_name, array( $key => $value ), array( 'id' => $message_id ) ); // db call ok. no-cache ok.
	}


	/**
	 * SQL query to update emails by hash
	 *
	 * @param int $hash email random hash.
	 * @param int $args argumnents to update.
	 * @return void
	 * @since 1.0.0
	 */
	public static function update_email_by_hash( $hash, $args ) {
		global $wpdb;
		$msg_table_name = $wpdb->prefix . EmailSchema::$table_name;
		$wpdb->update( $msg_table_name, $args, array( 'email_hash' => $hash ) ); // db call ok. no-cache ok.
	}


	/**
	 * SQL query to update broadcast email meta
	 *
	 * @param string $key Meta key.
	 * @param string $value Meta value.
	 * @param int    $email_id Email id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function insert_or_update_email_meta( $key, $value, $email_id ) {
		$meta_exist = self::is_email_meta_exist( $key, $email_id );
		if ( ! $meta_exist ) {
			$meta_args = array(
				'mint_email_id' => $email_id,
				'meta_key'      => $key,
				'meta_value'    => $value,
				'created_at'    => current_time( 'mysql' ),
			);
			return self::insert_broadcast_email_meta( $meta_args );
		} else {
			$meta_args = array(
				'meta_value' => $value,
				'updated_at' => current_time( 'mysql' ),
			);
			return self::update_broadcast_email_meta( $meta_args, $key, $email_id );
		}
	}


	/**
	 * SQL query only to insert broadcast email meta
	 *
	 * @param string $key Meta key.
	 * @param string $value Meta value.
	 * @param int    $email_id Email id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function insert_email_meta( $key, $value, $email_id ) {
		$meta_args = array(
			'mint_email_id' => $email_id,
			'meta_key'      => $key,
			'meta_value'    => $value,
			'created_at'    => current_time( 'mysql' ),
		);
		return self::insert_broadcast_email_meta( $meta_args );
	}


	/**
	 * SQL query to get broadcast email meta
	 *
	 * @param string $key Meta key.
	 * @param int    $email_id Email id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_email_meta_exist( $key, $email_id ) {
		global $wpdb;
		$table_name   = $wpdb->prefix . EmailMetaSchema::$table_name;
		$select_query = $wpdb->prepare( "SELECT id FROM $table_name WHERE mint_email_id = %d AND meta_key = %s", array( $email_id, $key ) ); //phpcs:ignore
		$results      = $wpdb->get_var( $select_query ); // db call ok. ; no-cache ok.
		if ( $results ) {
			return true;
		}
		return false;
	}

	/**
	 * Run sql query to get emails information for a contact
	 *
	 * @param int $contact_id contact id.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_regular_emails_to_contact_details( $contact_id, $step_ids ) {
		global $wpdb;
		$broadcast_emails = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_meta   = $wpdb->prefix . EmailMetaSchema::$table_name;
		$campaign_emails  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder    = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		if ( empty( $step_ids ) ) {
			return array();
		}
		// Create a placeholder for the step_ids in the WHERE clause
		$step_ids_placeholder = implode( ',', array_map( 'intval', $step_ids ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare(
			"SELECT
			ce.id AS campaign_email_id,
			ce.email_subject AS email_subject,
			
			ceb.id AS email_builder_id,
			ceb.email_body AS email_body,
			
			be.id AS broadcast_email_id,
			be.campaign_id AS broadcast_email_campaign_id,
			be.email_id AS broadcast_email_email_id,
			be.contact_id AS broadcast_email_contact_id,
			be.email_address AS email_address,
			be.status AS status,
			be.created_at AS broadcast_email_created_at
		
		FROM $campaign_emails ce
		
		LEFT JOIN $email_builder ceb ON ce.id = ceb.email_id
		
		LEFT JOIN $broadcast_emails be ON ce.id = be.email_id WHERE be.contact_id = %d AND be.campaign_id IS NULL AND be.automation_id IS NULL AND be.id IN ($step_ids_placeholder) ORDER BY be.id DESC",
			$contact_id
		);

		return $wpdb->get_results( $wpdb->prepare( $select_query ), ARRAY_A ); //phpcs:ignore
	}


	/**
	 * Run sql query to get emails information for a contact
	 *
	 * @param int $contact_id contact id.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_emails_to_contact_details( $contact_id, $step_ids ) {
		global $wpdb;
		$broadcast_emails = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_meta   = $wpdb->prefix . EmailMetaSchema::$table_name;
		$campaign_emails  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder    = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		if ( empty( $step_ids ) ) {
			return array();
		}

		// Create a placeholder for the step_ids in the WHERE clause
		$step_ids_placeholder = implode( ',', array_map( 'intval', $step_ids ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare(
			"SELECT
			ce.id AS campaign_email_id,
			ce.email_subject AS email_subject,
			
			ceb.id AS email_builder_id,
			ceb.email_body AS email_body,
			
			be.id AS broadcast_email_id,
			be.campaign_id AS broadcast_email_campaign_id,
			be.email_id AS broadcast_email_email_id,
			be.contact_id AS broadcast_email_contact_id,
			be.email_address AS email_address,
			be.status AS status,
			be.created_at AS broadcast_email_created_at
		
		FROM $campaign_emails ce
		
		LEFT JOIN $email_builder ceb ON ce.id = ceb.email_id
		
		LEFT JOIN $broadcast_emails be ON ce.id = be.email_id WHERE be.contact_id = %d AND be.campaign_id IS NOT NULL AND be.id IN ($step_ids_placeholder) ORDER BY be.id DESC",
			$contact_id
		);

		return $wpdb->get_results( $wpdb->prepare( $select_query ), ARRAY_A ); //phpcs:ignore
	}

	/**
	 * Get automation emails associated with a contact's details.
	 *
	 * @param int $contact_id The ID of the contact for whom to fetch automation emails.
	 *
	 * @return array An array containing information about automation emails associated with the contact.
	 *
	 * @since 1.6.2
	 */
	public static function get_automation_emails_to_contact_details( $contact_id, $email_ids ) {
		global $wpdb;
		$broadcast_emails      = $wpdb->prefix . EmailSchema::$table_name;
		$campaign_emails       = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder         = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		$email_table           = $wpdb->prefix . EmailSchema::$table_name;
		$automation_step_table = $wpdb->prefix . AutomationStepSchema::$table_name;

		if ( empty( $email_ids ) ) {
			return array();
		}

		// Create a placeholder for the step_ids in the WHERE clause.
		$email_ids_placeholder = implode( ',', array_map( 'intval', $email_ids ) );

		$select_query = $wpdb->prepare(
			"SELECT id, `step_id` FROM {$email_table} WHERE automation_id IS NOT NULL AND contact_id = %d AND id IN ($email_ids_placeholder) ORDER BY id DESC",
			$contact_id
		);

		$results      = $wpdb->get_results( $select_query, ARRAY_A ); // Use get_col to directly get an array of step IDs
		$result_array = array();

		foreach ( $results as $result ) {
			$data = $wpdb->get_row(
				$wpdb->prepare( "SELECT `settings` FROM $automation_step_table WHERE step_id = %s", $result['step_id'] ),
				ARRAY_A
			);

			$settings = isset( $data['settings'] ) ? maybe_unserialize( $data['settings'] ) : array();
			if ( !isset( $settings['sequence_settings'] ) ) {
				$broadcast_email = HelperFunctions::get_broadcast_email_by_step_id( $result['step_id'], $contact_id, $result['id'] );
				if ( ! empty( $broadcast_email ) ) {
					$message_data = isset( $settings['message_data'] ) ? maybe_unserialize( $settings['message_data'] ) : array();

					$result_array[] = array(
						'email_subject'              => isset( $message_data['subject'] ) ? $message_data['subject'] : '',
						'email_body'                 => isset( $message_data['body'] ) ? $message_data['body'] : '',
						'status'                     => isset( $broadcast_email['status'] ) ? $broadcast_email['status'] : '',
						'email_address'              => isset( $broadcast_email['email_address'] ) ? $broadcast_email['email_address'] : '',
						'broadcast_email_created_at' => isset( $broadcast_email['created_at'] ) ? $broadcast_email['created_at'] : '',
						'broadcast_email_id'         => isset( $broadcast_email['id'] ) ? $broadcast_email['id'] : '',
					);
				}
			}
		}

		return $result_array;
	}

	public static function get_automation_sequence_emails_to_contact_details( $contact_id, $email_ids ) {
		global $wpdb;
		$broadcast_emails = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_meta   = $wpdb->prefix . EmailMetaSchema::$table_name;
		$campaign_emails  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder    = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		if ( empty( $email_ids ) ) {
			return array();
		}

		// Create a placeholder for the step_ids in the WHERE clause
		$step_ids_placeholder = implode( ',', array_map( 'intval', $email_ids ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare(
			"SELECT
			ce.id AS campaign_email_id,
			ce.email_subject AS email_subject,
			
			ceb.id AS email_builder_id,
			ceb.email_body AS email_body,
			
			be.id AS broadcast_email_id,
			be.campaign_id AS broadcast_email_campaign_id,
			be.email_id AS broadcast_email_email_id,
			be.contact_id AS broadcast_email_contact_id,
			be.email_address AS email_address,
			be.status AS status,
			be.created_at AS broadcast_email_created_at
		
		FROM $campaign_emails ce
		
		LEFT JOIN $email_builder ceb ON ce.id = ceb.email_id
		
		LEFT JOIN $broadcast_emails be ON ce.id = be.email_id WHERE be.contact_id = %d AND be.campaign_id IS NULL AND be.automation_id IS NOT NULL AND be.id IN ($step_ids_placeholder) ORDER BY be.id DESC",
			$contact_id
		);

		return $wpdb->get_results( $wpdb->prepare( $select_query ), ARRAY_A ); //phpcs:ignore
	}

	/**
	 * Summary: Counts the number of emails with a specific open or click condition for a single contact.
	 *
	 * @access public
	 * @static
	 *
	 * @param int    $contact_id The ID of the contact for which the count is performed.
	 * @param string $condition  The email condition to count (e.g., 'is_open' or 'is_click').
	 * @param string $filter     The time period filter for counting emails ('lifetime', 'month', or 'year'). Default is 'lifetime'.
	 *
	 * @return int Returns the total count of emails with the specified open or click condition for the specified contact within the specified time period.
	 * @since 1.0.0
	 * @modified 1.7.0 Added support for time filtering.
	 */
	public static function count_email_open_click_on_contact( $contact_id, $condition, $filter = 'lifetime' ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table  = $wpdb->prefix . EmailMetaSchema::$table_name;

		$where_clause = '';

		// Apply date filter based on $filter parameter.
		if ( 'month' === $filter ) {
			$last_month_date = date( 'Y-m-d', strtotime( '-30 days' ) );
			$where_clause   .= " AND mail_meta.created_at >= '{$last_month_date}'";
		} elseif ( 'year' === $filter ) {
			$last_year_date = date( 'Y-m-d', strtotime( '-1 year' ) );
			$where_clause  .= " AND mail_meta.created_at >= '{$last_year_date}'";
		}
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mail.id) as total FROM $email_table as mail INNER JOIN $meta_table as mail_meta ON mail.id=mail_meta.mint_email_id WHERE mail.contact_id = %d AND mail_meta.meta_key = %s {$where_clause}", array( $contact_id, $condition ) ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count delivered emails
	 *
	 * @param int    $email_id mint email id.
	 * @param string $status Email sending status.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_delivered_status( $email_id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(status) as total_delivered FROM $table_name WHERE email_id = %d AND status = %s", array( $email_id, $status ) ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count total emails opening
	 *
	 * @param int $email_id mint email id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_email_open( $email_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_open FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_open' AND meta_value = 1 AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count total emails opening on campaign
	 *
	 * @param int $campaign_id mint campaign id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function calculate_open_rate_on_campaign( $campaign_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_open FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_open' AND meta_value = 1 AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE campaign_id = %d)", array( $campaign_id )  ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count total emails click on campaign
	 *
	 * @param int $campaign_id mint campaign id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function calculate_click_rate_on_campaign( $campaign_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_click FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_click' AND meta_value = 1 AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE campaign_id = %d)", array( $campaign_id )  ) ); //phpcs:ignore
	}

	/**
	 * Run sql query to count total emails click on campaign
	 *
	 * @param int $campaign_id mint campaign id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function calculate_bounched_on_campaign( $campaign_id ) {
		global $wpdb;
		$broadcast_email_table = $wpdb->prefix . EmailSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) as total_click FROM {$broadcast_email_table} WHERE status = 'failed' AND campaign_id = %d", array( $campaign_id )  ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count delivered emails on campaign
	 *
	 * @param int    $campaign_id mint campaign id.
	 * @param string $status Email sending status.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_delivered_status_on_campaign( $campaign_id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(status) as total_delivered FROM $table_name WHERE campaign_id = %d AND status = %s", array( $campaign_id, $status ) ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count total unsubscribe
	 *
	 * @param int $email_id mint email id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_unsubscribe( $email_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_unsubscribe FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_unsubscribe' AND meta_value = 1 AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count total unsubscribe on campaign
	 *
	 * @param int $campaign_id mint campaign id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_unsubscribe_on_campaign( $campaign_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_unsubscribe FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_unsubscribe' AND meta_value = 1 AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE campaign_id = %d)", array( $campaign_id )  ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count total emails opening on every hour
	 *
	 * @param int $email_id mint email id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_per_hour_total_email_open( $email_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$result = $wpdb->get_results( $wpdb->prepare( "SELECT date_format(created_at,'%H %p') as label, COUNT(mint_email_id) as total_open FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_open' AND meta_value = 1 AND created_at >= now() - INTERVAL 1 DAY AND HOUR(created_at) AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d) GROUP BY date_format(created_at,'%H %p')", array( $email_id ) ), ARRAY_A ); //phpcs:ignore
		return array_column( $result, 'total_open', 'label' );
	}


	/**
	 * Run sql query to count total click on every hour
	 *
	 * @param int $email_id mint email id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_per_hour_total_link_click( $email_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$result = $wpdb->get_results( $wpdb->prepare( "SELECT date_format(created_at,'%H %p') as label, COUNT(mint_email_id) as total_click FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_click' AND meta_value = 1 AND created_at >= now() - INTERVAL 1 DAY AND HOUR(created_at) AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d) GROUP BY date_format(created_at,'%H %p')", array( $email_id ) ), ARRAY_A ); //phpcs:ignore
		return array_column( $result, 'total_click', 'label' );
	}

	/**
	 * Count the total number of unsubscribes per hour for a specific email.
	 *
	 * Retrieves the count of unsubscribes per hour within the last 24 hours
	 * for a specific email.
	 *
	 * @param int $email_id The ID of the email for which to count total unsubscribes.
	 * @return array An associative array where keys are hour labels formatted as 'H AM/PM'
	 *               and values are the corresponding total number of unsubscribes.
	 * @since 1.9.0
	 */
	public static function count_per_hour_total_unsubscribe( $email_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$result = $wpdb->get_results( $wpdb->prepare( "SELECT date_format(created_at,'%H %p') as label, COUNT(mint_email_id) as total_unsubscribe FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_unsubscribe' AND meta_value = 1 AND created_at >= now() - INTERVAL 1 DAY AND HOUR(created_at) AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d) GROUP BY date_format(created_at,'%H %p')", array( $email_id ) ), ARRAY_A ); //phpcs:ignore
		return array_column( $result, 'total_unsubscribe', 'label' );
	}

	/**
	 * Run sql query to count total emails opening on speceific time range
	 *
	 * @param int    $email_id mint email id.
	 * @param string $meta_key mint email meta key.
	 * @param int    $start_time Start time to count open or click.
	 * @param int    $end_time End time to count open or click.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_four_hours_email_open( $email_id, $meta_key, $start_time, $end_time ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) FROM {$broadcast_email_meta_table} WHERE meta_key = %s AND meta_value = 1 AND created_at >= now() - INTERVAL 1 DAY AND HOUR(created_at) BETWEEN %d AND %d AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $meta_key, $start_time, $end_time, $email_id ) ) ); //phpcs:ignore
	}


	/**
	 * Run sql query to count total clicks on emails
	 *
	 * @param int $email_id mint email id.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function count_email_click( $email_id ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_click FROM {$broadcast_email_meta_table} WHERE meta_key = 'is_click' AND meta_value = 1 AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
	}


	/**
	 * Get broadcast email id by email hash
	 *
	 * @param string $hash Email address hash.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public static function get_broadcast_email_by_hash( $hash ) {
		global $wpdb;
		$email_table  = $wpdb->prefix . EmailSchema::$table_name;
		$select_query = $wpdb->prepare( "SELECT `id` FROM {$email_table} WHERE email_hash=%s", $hash ); //phpcs:ignore
		return $wpdb->get_var( $select_query ); // db call ok. ; no-cache ok.
	}


	/**
	 * Prepare total number of open based on user agent or devices
	 *
	 * @param mixed $email_id Email id.
	 * @param mixed $total_open Total email open.
	 * @return object|array
	 *
	 * @since 1.0.0
	 */
	public static function count_total_email_open_on_device( $email_id, $total_open ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$desktop      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_open FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_open_agent' AND meta_value = 'desktop' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
		$mobile       = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_open FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_open_agent' AND meta_value = 'mobile' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
		$tab 		  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_open FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_open_agent' AND meta_value = 'tab' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
		$unidentified = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_open FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_open_agent' AND meta_value = 'unidentified' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore

		$divide_by    = 0 == $total_open ? 1 : $total_open; //phpcs:ignore
		return array(
			'desktop'      => number_format( (float) ( $desktop / $divide_by ) * 100, 2, '.', '' ),
			'mobile'       => number_format( (float) ( $mobile / $divide_by ) * 100, 2, '.', '' ),
			'tab'          => number_format( (float) ( $tab / $divide_by ) * 100, 2, '.', '' ),
			'unidentified' => number_format( (float) ( $unidentified / $divide_by ) * 100, 2, '.', '' ),
		);
	}


	/**
	 * Prepare total number of click based on user agent or devices
	 *
	 * @param mixed $email_id Email id.
	 * @param mixed $total_click Total email click.
	 * @return object|array
	 *
	 * @since 1.0.0
	 */
	public static function count_total_email_click_on_device( $email_id, $total_click ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$desktop      = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_click FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_click_agent' AND meta_value = 'desktop' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
		$mobile       = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_click FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_click_agent' AND meta_value = 'mobile' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
		$tab 		  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_click FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_click_agent' AND meta_value = 'tab' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore
		$unidentified = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_click FROM {$broadcast_email_meta_table} WHERE meta_key = 'user_click_agent' AND meta_value = 'unidentified' AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d)", array( $email_id )  ) ); //phpcs:ignore

		$divide_by    = 0 == $total_click ? 1 : $total_click; //phpcs:ignore
		return array(
			'desktop'      => number_format( (float) ( $desktop / $divide_by ) * 100, 2, '.', '' ),
			'mobile'       => number_format( (float) ( $mobile / $divide_by ) * 100, 2, '.', '' ),
			'tab'          => number_format( (float) ( $tab / $divide_by ) * 100, 2, '.', '' ),
			'unidentified' => number_format( (float) ( $unidentified / $divide_by ) * 100, 2, '.', '' ),
		);
	}

	/**
	 * Summary: Counts the number of emails with a specific delivery status for a single contact.
	 *
	 * @access public
	 * @static
	 *
	 * @param int    $contact_id The ID of the contact for which the count is performed.
	 * @param string $status     The delivery status to count (e.g., 'sent' or 'failed').
	 * @param string $filter     The time period filter for counting emails ('lifetime', 'month', or 'year'). Default is 'lifetime'.
	 *
	 * @return int Returns the total count of emails with the specified delivery status for the specified contact within the specified time period.
	 * @since 1.0.0
	 * @modified 1.7.0 Added support for time filtering.
	 */
	public static function count_delivered_status_single_contact( $contact_id, $status, $filter = 'lifetime' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailSchema::$table_name;

		$where_clause = '';

		// Apply date filter based on $filter parameter.
		if ( 'month' === $filter ) {
			$last_month_date = date( 'Y-m-d', strtotime( '-30 days' ) );
			$where_clause   .= " AND created_at >= '{$last_month_date}'";
		} elseif ( 'year' === $filter ) {
			$last_year_date = date( 'Y-m-d', strtotime( '-1 year' ) );
			$where_clause  .= " AND created_at >= '{$last_year_date}'";
		}

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(status) as total_delivered FROM $table_name WHERE contact_id = %d AND status = %s {$where_clause}", array( $contact_id, $status ) ) ); //phpcs:ignore
	}

	/**
	 * Summary: Retrieves the timestamp of the last opened email for a single contact based on a specific condition.
	 *
	 * Description: This static method retrieves the timestamp of the last opened email for a single contact by querying the database.
	 *
	 * @access public
	 * @static
	 *
	 * @param int    $contact_id The ID of the contact for which the last opened email is to be retrieved.
	 * @param string $condition  The condition used to filter the emails (e.g., 'opened').
	 *
	 * @return string Returns the timestamp of the last opened email for the specified contact.
	 *
	 * @since 1.7.0
	 */
	public static function last_opened_email_single_contact( $contact_id, $condition ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table  = $wpdb->prefix . EmailMetaSchema::$table_name;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT mail_meta.created_at, mail_meta.updated_at FROM $email_table as mail 
										INNER JOIN $meta_table as mail_meta ON mail.id=mail_meta.mint_email_id 
										WHERE mail.contact_id = %d AND mail_meta.meta_key = %s 
										ORDER BY mail_meta.id 
										DESC LIMIT 1 ",
										array( $contact_id, $condition ) ), ARRAY_A ); //phpcs:ignore
		if ( empty( $result ) ) {
			return;
		}
		return !empty( $result['updated_at'] ) ? $result['updated_at'] : $result['created_at'];
	}

	/**
	 * Summary: Retrieves the timestamp of the last clicked email for a single contact based on a specific condition.
	 *
	 * Description: This static method retrieves the timestamp of the last clicked email for a single contact by querying the database.
	 *
	 * @access public
	 * @static
	 *
	 * @param int    $contact_id The ID of the contact for which the last clicked email is to be retrieved.
	 * @param string $condition  The condition used to filter the emails (e.g., 'clicked').
	 *
	 * @return string Returns the timestamp of the last clicked email for the specified contact.
	 *
	 * @since 1.7.0
	 */
	public static function last_clicked_email_single_contact( $contact_id, $condition ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table  = $wpdb->prefix . EmailMetaSchema::$table_name;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT mail_meta.created_at, mail_meta.updated_at FROM $email_table as mail 
										INNER JOIN $meta_table as mail_meta ON mail.id=mail_meta.mint_email_id 
										WHERE mail.contact_id = %d AND mail_meta.meta_key = %s 
										ORDER BY mail_meta.id 
										DESC LIMIT 1 ",
										array( $contact_id, $condition ) ), ARRAY_A ); //phpcs:ignore
		if ( empty( $result ) ) {
			return;
		}
		return !empty( $result['updated_at'] ) ? $result['updated_at'] : $result['created_at'];
	}

	/**
	 * Summary: Retrieves the timestamp of the last email sent to a single contact.
	 *
	 * Description: This static method retrieves the timestamp of the last email sent to a specific contact by querying the database.
	 *
	 * @access public
	 * @static
	 *
	 * @param int $contact_id The ID of the contact for which the last sent email timestamp is to be retrieved.
	 *
	 * @return string Returns the timestamp of the last email sent to the specified contact.
	 *
	 * @since 1.7.0
	 */
	public static function last_email_sent_single_contact( $contact_id ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_at FROM $email_table
										WHERE contact_id = %d
										ORDER BY created_at 
										DESC LIMIT 1",
										array( $contact_id ) ) ); //phpcs:ignore
	}

	/**
	 * Run SQL Query to get a single contact information by hash
	 *
	 * @param mixed $hash Contact email address hash.
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function get_contact_id_by_hash( $hash ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_row( $wpdb->prepare( "SELECT `contact_id` FROM {$email_table} WHERE email_hash = %s", array( $hash ) ), ARRAY_A ); //phpcs:ignore
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Return revenue array for week or month or year
	 *
	 * @param mixed $filter Response filter variable.
	 * @param mixed $email_type Campaign or automation email type.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_total_from_email( $filter, $email_type ) {
		$revenue_arr = array();
		if ( 'weekly' === $filter ) {
			$order_ids = self::get_order_ids_for_week( $email_type );
			if ( !empty( $order_ids ) ) {
				$revenue_arr = self::get_order_total_for_week( $order_ids );
			}
		} elseif ( 'all' === $filter ) {
			global $wpdb;
			$contact_table = $wpdb->prefix . ContactSchema::$table_name;

			$first_date = $wpdb->get_row( $wpdb->prepare( 'SELECT created_at FROM %1s LIMIT 1', $contact_table ), ARRAY_A ); //phpcs:ignore
			$first_date = isset( $first_date['created_at'] ) ? $first_date['created_at'] : '';
			$prev_date  = new DateTime( $first_date );
			$curt_date  = new DateTime();
			$interval   = $prev_date->diff( $curt_date );
			$days       = $interval->days;

			if ( $days <= 7 ) {
				$order_ids = self::get_order_ids_for_week( $email_type );
				if ( !empty( $order_ids ) ) {
					$revenue_arr = self::get_order_total_for_week( $order_ids );
				}
			} elseif ( $days > 7 && $days <= 31 ) {
				$order_ids = self::get_order_ids_for_month( $email_type );
				if ( !empty( $order_ids ) ) {
					$revenue_arr = self::get_order_total_for_month( $order_ids );
				}
			} elseif ( $days > 31 && $days <= 365 ) {
				$order_ids = self::get_order_ids_for_year( $email_type );
				if ( !empty( $order_ids ) ) {
					$revenue_arr = self::get_order_total_for_year( $order_ids );
				}
			} elseif ( $days > 365 && $days <= 1460 ) {
				$order_ids = self::get_order_ids_for_quarterly( $first_date, $email_type );
				if ( !empty( $order_ids ) ) {
					$revenue_arr = self::get_order_total_for_quarterly( $order_ids, $first_date );
				}
			} else {
				$order_ids = self::get_order_ids_for_all_yearly( $first_date, $email_type );
				if ( !empty( $order_ids ) ) {
					$revenue_arr = self::get_order_total_for_all_yearly( $order_ids, $first_date );
				}
			}
		} elseif ( 'monthly' === $filter ) {
			$order_ids = self::get_order_ids_for_month( $email_type );
			if ( !empty( $order_ids ) ) {
				$revenue_arr = self::get_order_total_for_month( $order_ids );
			}
		} else {
			$order_ids = self::get_order_ids_for_year( $email_type );
			if ( !empty( $order_ids ) ) {
				$revenue_arr = self::get_order_total_for_year( $order_ids );
			}
		}

		return $revenue_arr;
	}


	/**
	 * Prepare order total or revenue for week
	 *
	 * @param mixed $order_ids array of all order ids.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_total_for_week( $order_ids ) {
		global $wpdb;

		$query  = "SELECT DATE_FORMAT(date_created, '%b %e') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$wpdb->prefix}wc_order_stats ";
		$query .= 'WHERE (order_id IN (' . implode( ',', $order_ids ) . ') OR parent_id IN (' . implode( ',', $order_ids ) . ')) ';
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= "GROUP BY DATE_FORMAT(date_created, '%b %e') ";
		$query .= "ORDER BY DATE_FORMAT(date_created, '%c %e') ASC";

		$result = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'order_total', 'label' );

		$week_days = array();
		$interval  = 0;
		while ( $interval < 7 ) {
			$label               = date( 'M j', strtotime( '-' . $interval . 'day' ) );
			$week_days[ $label ] = 0;
			$interval++;
		}
		$week_days = array_reverse( $week_days, true );
		return array_merge( $week_days, $result );
	}


	/**
	 * Prepare order total or revenue for week
	 *
	 * @param mixed $order_ids array of all order ids.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_total_for_month( $order_ids ) {
		global $wpdb;

		$query  = "SELECT DATE_FORMAT(date_created, '%b %e') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$wpdb->prefix}wc_order_stats ";
		$query .= 'WHERE (order_id IN (' . implode( ',', $order_ids ) . ') OR parent_id IN (' . implode( ',', $order_ids ) . ')) ';
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= "GROUP BY DATE_FORMAT(date_created, '%b %e') ";
		$query .= "ORDER BY DATE_FORMAT(date_created, '%b %e') ASC";

		$result = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'order_total', 'label' );

		$monthly_days = array();
		for ($i = 0; $i < 30; $i++) {
			$monthly_days[date('M j', strtotime("-$i day"))] = 0;
		}
		$monthly_days = array_reverse( $monthly_days, true );

		return array_merge( $monthly_days, $result );
	}


	/**
	 * Get order total by quarter for the current year and the next 4 years.
	 *
	 * @param array  $order_ids The IDs of the orders to calculate the total for.
	 * @param string $first_date The date of the first order.
	 * @return array An array of the total order amounts for each quarter, indexed by quarter label.
	 * @since 1.0.0
	 */
	public static function get_order_total_for_quarterly( $order_ids, $first_date ) {
		global $wpdb;

		$query  = "SELECT CONCAT(YEAR(date_created), ' Q', QUARTER(date_created)) AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$wpdb->prefix}wc_order_stats ";
		$query .= 'WHERE (order_id IN (' . implode( ',', $order_ids ) . ') OR parent_id IN (' . implode( ',', $order_ids ) . ')) ';
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= 'AND date_created >= DATE_SUB(NOW(), INTERVAL 5 YEAR)';
		$query .= "GROUP BY CONCAT(YEAR(date_created), ' Q', QUARTER(date_created)) ";
		$query .= "ORDER BY CONCAT(YEAR(date_created), ' Q', QUARTER(date_created)) ASC";
		$result = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'order_total', 'label' );

		$quarters     = array();
		$current_year = gmdate( 'Y', strtotime( $first_date ) );
		$count        = 0;

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
		return array_merge( $quarters, $result );
	}

	/**
	 * Get order total by quarter for the current year and the next 4 years.
	 *
	 * @param array  $order_ids The IDs of the orders to calculate the total for.
	 * @param string $first_date The date of the first order.
	 * @return array An array of the total order amounts for each quarter, indexed by quarter label.
	 * @since 1.0.0
	 */
	public static function get_order_total_for_all_yearly( $order_ids, $first_date ) {
		global $wpdb;

		$query  = "SELECT DATE_FORMAT(date_created, '%Y') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$wpdb->prefix}wc_order_stats ";
		$query .= 'WHERE (order_id IN (' . implode( ',', $order_ids ) . ') OR parent_id IN (' . implode( ',', $order_ids ) . ')) ';
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= 'AND date_created BETWEEN DATE_SUB(NOW(), INTERVAL 5 YEAR) AND DATE_ADD(NOW(), INTERVAL 1 YEAR) ';
		$query .= 'GROUP BY YEAR(date_created) ';
		$query .= 'ORDER BY YEAR(date_created) ASC';

		$result = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'order_total', 'label' );

		$start_year = gmdate( 'Y', strtotime( $first_date ) );
		$last_year  = $start_year + 5;

		$years = array();
		for ( $year = $start_year; $year < $last_year; $year++ ) {
			$years[ $year ] = isset( $result[ $year ] ) ? $result[ $year ] : 0;
		}
		return $years;
	}

	/**
	 * Prepare order total or revenue for week
	 *
	 * @param mixed $order_ids array of all order ids.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_total_for_year( $order_ids ) {
		global $wpdb;

		$query  = "SELECT DATE_FORMAT(date_created, '%b') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$wpdb->prefix}wc_order_stats ";
		$query .= 'WHERE (order_id IN (' . implode( ',', $order_ids ) . ') OR parent_id IN (' . implode( ',', $order_ids ) . ')) ';
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= "GROUP BY DATE_FORMAT(date_created, '%b') ";
		$query .= "ORDER BY DATE_FORMAT(date_created, '%b') ASC";

		$result = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
		$result = array_column( $result, 'order_total', 'label' );
		$months = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$months[date('M', strtotime("-$i month"))] = 0;
		}

		$months = array_reverse( $months, true );
		return array_merge( $months, $result );
	}


	/**
	 * Return broadcast email meta data
	 *
	 * @param mixed $key email meta key.
	 * @param mixed $value email meta value.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_broadcast_email_meta( $key, $value ) {
		global $wpdb;
		$meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$meta_table} WHERE meta_key = %s AND meta_value = %d", array( $key, $value ) ), ARRAY_A ); //phpcs:ignore
	}


	/**
	 * Return total revenue generated through campaign and automation
	 *
	 * @param mixed $order_ids array of all order ids.
	 * @return int|float
	 * @since 1.0.0
	 */
	public static function get_total_revenue_from_email( $order_ids ) {
		global $wpdb;

		$query = "SELECT SUM(total_sales) as total_sales FROM {$wpdb->prefix}wc_order_stats WHERE (order_id IN (".implode(',', $order_ids).") OR parent_id IN (".implode(',', $order_ids).")) AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order')"; //phpcs:ignore
		return $wpdb->get_var( $query ); //phpcs:ignore
	}


	/**
	 * Return all order ids generated through campaign and automation
	 *
	 * @param mixed $filter Variable to filter revenue data.
	 * @param mixed $first First email type.
	 * @param mixed $second Second to filter revenue data.
	 *
	 * @return int|float
	 * @since 1.0.0
	 */
	public static function get_all_order_ids_from_email( $filter, $first = '', $second = '' ) {
		if ( 'weekly' === $filter ) {
			return self::get_order_ids_for_week( $first, $second );
		} elseif ( 'monthly' === $filter ) {
			return self::get_order_ids_for_month( $first, $second );
		} elseif ( 'all' === $filter ) {
			return self::get_order_ids_for_all( $first, $second );
		} else {
			return self::get_order_ids_for_year( $first, $second );
		}
	}

	/**
	 * Return order ids for week
	 *
	 * @param mixed $first First email type.
	 * @param mixed $second Second to filter revenue data.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_ids_for_week( $first = '', $second = '' ) {
		global $wpdb;
		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$condition = '';

		if ( empty( $second ) ) {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}') ";
		} else {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}' OR email_type = '{$second}') ";
		}

		$query  = 'SELECT meta_value ';
		$query .= "FROM {$email_meta_table} ";
		$query .= "WHERE meta_key =  'order_id' ";
		$query .= $condition;
		$query .= "AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 7 DAY) AND NOW() ";
		return $wpdb->get_col( $query ); //phpcs:ignore
	}

	/**
	 * Return order ids for week
	 *
	 * @param mixed $first First email type.
	 * @param mixed $second Second to filter revenue data.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_ids_for_all( $first = '', $second = '' ) {
		global $wpdb;
		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;
		$contact_table    = $wpdb->prefix . ContactSchema::$table_name;

		$first_date = $wpdb->get_row( $wpdb->prepare( 'SELECT created_at FROM %1s LIMIT 1', $contact_table ), ARRAY_A ); //phpcs:ignore
		$first_date = isset( $first_date['created_at'] ) ? $first_date['created_at'] : '';
		$prev_date  = new DateTime( $first_date );
		$curt_date  = new DateTime();
		$interval   = $prev_date->diff( $curt_date );
		$days       = $interval->days;

		if ( $days <= 7 ) {
			return self::get_order_ids_for_week( $first, $second );
		} elseif ( $days > 7 && $days <= 31 ) {
			return self::get_order_ids_for_month( $first, $second );
		} elseif ( $days > 31 && $days <= 365 ) {
			return self::get_order_ids_for_year( $first, $second );
		} elseif ( $days > 365 && $days <= 1460 ) {
			return self::get_order_ids_for_quarterly( $first_date, $first, $second );
		} else {
			return self::get_order_ids_for_all_yearly( $first_date, $first, $second );
		}

		$condition = '';

		if ( empty( $second ) ) {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}') ";
		} else {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}' OR email_type = '{$second}') ";
		}

		$week_start_end = get_weekstartend( current_time( 'mysql' ), get_option( 'start_of_week', 1 ) );

		if ( ! empty( $week_start_end[ 'start' ] ) && ! empty( $week_start_end[ 'end' ] ) ) {
			$start_of_week = date_i18n( 'Y-m-d', $week_start_end[ 'start' ] );
			$end_of_week   = date_i18n( 'Y-m-d', $week_start_end[ 'end' ] );

			$query  = 'SELECT meta_value ';
			$query .= "FROM {$email_meta_table} ";
			$query .= "WHERE meta_key =  'order_id' ";
			$query .= $condition;
			$query .= "AND DATE_FORMAT(created_at, '%Y-%m-%d') >= '{$start_of_week}' ";
			$query .= "AND DATE_FORMAT(created_at, '%Y-%m-%d') <= '{$end_of_week}' ";
			return $wpdb->get_col( $query ); //phpcs:ignore
		}

		return array();
	}

	/**
	 * Prepare order ids for month
	 *
	 * @param mixed $first First email type.
	 * @param mixed $second Second to filter revenue data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_ids_for_month( $first = '', $second = '' ) {
		global $wpdb;
		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$condition = '';

		if ( empty( $second ) ) {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}') ";
		} else {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}' OR email_type = '{$second}') ";
		}

		$query  = 'SELECT meta_value ';
		$query .= "FROM {$email_meta_table} ";
		$query .= "WHERE meta_key =  'order_id' ";
		$query .= $condition;
		$query .= 'AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW() ';

		return $wpdb->get_col( $query ); //phpcs:ignore
	}

	/**
	 * Prepare order ids for month
	 *
	 * @param mixed $first_date First entry date on contacts table.
	 * @param mixed $first First email type.
	 * @param mixed $second Second to filter revenue data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_ids_for_quarterly( $first_date, $first = '', $second = '' ) {
		global $wpdb;
		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$condition = '';
		$years     = 4;
		$end_date  = date( 'Y-m-d H:i:s', strtotime( "+{$years} years", strtotime( $first_date ) ) ); //phpcs:ignore

		if ( empty( $second ) ) {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}') ";
		} else {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}' OR email_type = '{$second}') ";
		}

		$query  = 'SELECT meta_value ';
		$query .= "FROM {$email_meta_table} ";
		$query .= "WHERE meta_key =  'order_id' ";
		$query .= $condition;
		$query .= $wpdb->prepare( 'AND created_at BETWEEN %s AND %s ', $first_date, $end_date );

		return $wpdb->get_col( $query ); //phpcs:ignore
	}

	/**
	 * Prepare order ids for month
	 *
	 * @param mixed $first_date First entry date on contacts table.
	 * @param mixed $first First email type.
	 * @param mixed $second Second to filter revenue data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_ids_for_all_yearly( $first_date, $first = '', $second = '' ) {
		global $wpdb;
		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$condition = '';
		$years     = 5;
		$end_date  = date( 'Y-m-d H:i:s', strtotime( "+{$years} years", strtotime( $first_date ) ) ); //phpcs:ignore

		if ( empty( $second ) ) {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}') ";
		} else {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}' OR email_type = '{$second}') ";
		}

		$query  = 'SELECT meta_value ';
		$query .= "FROM {$email_meta_table} ";
		$query .= "WHERE meta_key =  'order_id' ";
		$query .= $condition;
		$query .= $wpdb->prepare( 'AND created_at BETWEEN %s AND %s ', $first_date, $end_date );

		return $wpdb->get_col( $query ); //phpcs:ignore
	}

	/**
	 * Prepare order ids for year
	 *
	 * @param mixed $first First email type.
	 * @param mixed $second Second to filter revenue data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_order_ids_for_year( $first = '', $second = '' ) {
		global $wpdb;
		$email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$condition = '';

		if ( empty( $second ) ) {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}') ";
		} else {
			$condition = "AND mint_email_id IN (SELECT id FROM {$email_table} WHERE email_type = '{$first}' OR email_type = '{$second}') ";
		}

		$query  = 'SELECT meta_value ';
		$query .= "FROM {$email_meta_table} ";
		$query .= "WHERE meta_key =  'order_id' ";
		$query .= $condition;
		$query .= 'AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 1 YEAR) AND NOW() ';

		return $wpdb->get_col( $query ); //phpcs:ignore
	}


	/**
	 * Return total order placed through a campaign email
	 *
	 * @param mixed $email_id Campaign email id.
	 * @return int|float
	 * @since 1.0.0
	 */
	public static function count_total_orders_to_campaign_email( $email_id, $type ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(mint_email_id) as total_order FROM {$broadcast_email_meta_table} WHERE meta_key = %s AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_id = %d AND email_type = %s)", array( 'order_id', $email_id, $type ) ) ); //phpcs:ignore
	}


	/**
	 * Return total revenue generated through a campaign email
	 *
	 * @param mixed $email_id Campaign email id.
	 * @return int|float
	 * @since 1.0.0
	 */
	public static function count_total_revenue_to_campaign_email( $email_id, $type ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		$results   = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$broadcast_email_meta_table} WHERE meta_key = %s AND mint_email_id IN (SELECT id FROM {$broadcast_email_table} WHERE email_type = %s AND email_id = %d)", array( 'order_id', $type, $email_id ) ), ARRAY_A ); //phpcs:ignore
		$order_ids = array_column( $results, 'meta_value' );
		if ( empty( $order_ids ) ) {
			return 0;
		}
		return self::get_total_revenue_from_email( $order_ids );
	}

	/**
	 * Remove scheduled emails in mint_broadcast_emails for a specific campaign/automation id
	 *
	 * @param string     $column_name [automation_id/campaign_id].
	 * @param string|int $value Automation/Campaign ID.
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public static function delete_scheduled_emails( $column_name, $value ) {
		global $wpdb;
		$broadcast_table = $wpdb->prefix . EmailSchema::$table_name;
		self::delete_scheduled_emails_meta( $column_name, $value );
		$wpdb->delete( $broadcast_table, array( $column_name => $value ) ); //phpcs:ignore
		return true;
	}

	/**
	 * Remove scheduled email meta in mint_broadcast_email_meta for a specific campaign/automation id
	 *
	 * @param string     $column_name [automation_id/campaign_id].
	 * @param string|int $value Automation/Campaign ID.
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public static function delete_scheduled_emails_meta( $column_name, $value ) {
		global $wpdb;
		$broadcast_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;
		$sql           = $wpdb->prepare( 'SELECT `id` FROM %1s WHERE %1s = %d', $broadcast_table, $column_name, $value ); //phpcs:ignore
		$broadcast_ids = $wpdb->get_col( $sql ); //phpcs:ignore
		if ( empty( $broadcast_ids ) ) {
			return true;
		}
		$broadcast_ids = implode( ',', array_map( 'absint', $broadcast_ids ) );
		$id_sql        = $wpdb->prepare( 'DELETE FROM %1s WHERE `mint_email_id` IN(%1s)', $broadcast_meta_table, $broadcast_ids ); //phpcs:ignore
		return $wpdb->query( $id_sql ); //phpcs:ignore
	}

	/**
	 * Get the total number of recipients for a recurring campaign.
	 *
	 * @param int $campaign_id The ID of the recurring campaign.
	 *
	 * @return int The total number of recipients for the specified recurring campaign.
	 * @since 1.7.0
	 */
	public static function recurring_campaign_total_recipients( $campaign_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(campaign_id) as total_recipients FROM $table_name WHERE campaign_id = %d", array( $campaign_id ) ) ); //phpcs:ignore
	}

	/**
	 * Retrieve broadcast email IDs associated with a specific contact.
	 *
	 * @param int $contact_id The ID of the contact for whom broadcast email IDs are retrieved.
	 * @param int $offset     The offset for paginating through the results.
	 * @param int $limit      The maximum number of broadcast email IDs to retrieve per page.
	 *
	 * @return array An array containing the IDs of broadcast emails sent to the specified contact.
	 * @since 1.7.0
	 */
	public static function get_broadcast_email_ids_to_contact( $contact_id, $offset, $limit ) {
		global $wpdb;
		$table_name   = $wpdb->prefix . EmailSchema::$table_name;
		$select_query = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE contact_id = %d ORDER BY id DESC LIMIT %d, %d",
			$contact_id,
			$offset,
			$limit
		);
		return $wpdb->get_col( $select_query ); // Use get_col to directly get an array of step IDs
	}

	/**
	 * Get the total count of broadcast email IDs sent to a specific contact.
	 *
	 * @param int $contact_id The ID of the contact for whom the total count of broadcast email IDs is retrieved.
	 *
	 * @return int The total count of broadcast email IDs sent to the specified contact.
	 * @since 1.7.0
	 */
	public static function total_broadcast_email_ids_to_contact( $contact_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) as total FROM $table_name WHERE contact_id = %d", $contact_id ) ); // Use get_col to directly get an array of step IDs
	}

	/**
	 * Retrieves the opened time for broadcast emails associated with a contact.
	 *
	 * @param int   $contact_id The ID of the contact.
	 * @param array $emails     An array of broadcast emails with associated data.
	 *
	 * @return array An updated array of broadcast emails with added 'email_opened_time' field.
	 * @since 1.7.0
	 */
	public static function get_broadcast_emails_open_time( $contact_id, $emails ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailMetaSchema::$table_name;

		// Extract mint_email_id values from the array.
		$mint_email_ids = array_column( $emails, 'broadcast_email_id' );

		if ( empty( $mint_email_ids ) ) {
			return array();
		}

		// Convert mint_email_ids to a comma-separated string for the SQL query.
		$mint_email_ids_string = implode( ',', $mint_email_ids );
		// Query to fetch data.
		$query = $wpdb->prepare(
			"SELECT created_at, updated_at, mint_email_id
			FROM $table_name
			WHERE mint_email_id IN ($mint_email_ids_string)
			AND meta_key = 'is_open'
			AND meta_value = 1"
		);

		// Fetch results.
		$results = $wpdb->get_results( $query, ARRAY_A );
		// Combine the array and database results.
		foreach ( $emails as &$email ) {
			foreach ( $results as $result ) {
				if ( $result['mint_email_id'] == $email['broadcast_email_id'] ) {
					$email_opened_time = !empty( $result['updated_at'] ) ? $result['updated_at'] : $result['created_at'];
					// Add the 'created_at' value to the array.
					$email['email_opened_time'] = MrmCommon::date_time_format_with_core( $email_opened_time );
				}
			}
		}
		return $emails;
	}

	/**
	 * Retrieves the clicked time for broadcast emails associated with a contact.
	 *
	 * @param int   $contact_id The ID of the contact.
	 * @param array $emails     An array of broadcast emails with associated data.
	 *
	 * @return array An updated array of broadcast emails with added 'email_clicked_time' field.
	 * @since 1.7.0
	 */
	public static function get_broadcast_emails_click_time( $contact_id, $emails ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailMetaSchema::$table_name;

		// Extract mint_email_id values from the array.
		$mint_email_ids = array_column( $emails, 'broadcast_email_id' );

		if ( empty( $mint_email_ids ) ) {
			return array();
		}

		// Convert mint_email_ids to a comma-separated string for the SQL query.
		$mint_email_ids_string = implode( ',', $mint_email_ids );
		// Query to fetch data.
		$query = $wpdb->prepare(
			"SELECT created_at, updated_at, mint_email_id
			FROM $table_name
			WHERE mint_email_id IN ($mint_email_ids_string)
			AND meta_key = 'is_click'
			AND meta_value = 1"
		);

		// Fetch results.
		$results = $wpdb->get_results( $query, ARRAY_A );
		// Combine the array and database results.
		foreach ( $emails as &$email ) {
			foreach ( $results as $result ) {
				if ( $result['mint_email_id'] == $email['broadcast_email_id'] ) {
					$email_clicked_time = !empty( $result['updated_at'] ) ? $result['updated_at'] : $result['created_at'];
					// Add the 'created_at' value to the array.
					$email['email_clicked_time'] = MrmCommon::date_time_format_with_core( $email_clicked_time );
				}
			}
		}
		return $emails;
	}

	/**
	 * Summary: Deletes a broadcast email associated with a specific contact ID.
	 *
	 * @access public
	 * @static
	 *
	 * @param int $email_id   The ID of the broadcast email to be deleted.
	 * @param int $contact_id The ID of the contact associated with the broadcast email.
	 *
	 * @return bool Returns true if the deletion is successful, false otherwise.
	 *
	 * @since 1.7.0
	 */
	public static function delete_broadcast_email_by_contact_id( $email_id, $contact_id ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table  = $wpdb->prefix . EmailMetaSchema::$table_name;

		$wpdb->delete( $meta_table, array( 'mint_email_id' => $email_id ) );
		return $wpdb->delete(
			$email_table,
			array(
				'id'         => $email_id,
				'contact_id' => $contact_id,
			)
		); // db call ok. ; no-cache ok.
	}

	/**
	 * Summary: Deletes multiple broadcast emails associated with a specific contact ID.
	 *
	 * @access public
	 * @static
	 *
	 * @param array $email_ids   The array of the broadcast emails to be deleted.
	 * @param int   $contact_id The ID of the contact associated with the broadcast email.
	 *
	 * @return bool Returns true if the deletion is successful, false otherwise.
	 *
	 * @since 1.7.0
	 */
	public static function delete_multiple_broadcast_email_by_contact_id( $email_ids, $contact_id ) {
		global $wpdb;
		$email_table = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table  = $wpdb->prefix . EmailMetaSchema::$table_name;

		$ids = implode( ',', array_map( 'intval', $email_ids ) );
		foreach ( $email_ids as $email_id ) {
			$wpdb->delete( $meta_table, array( 'mint_email_id' => $email_id ) );
		}
		return $wpdb->query( "DELETE FROM $email_table WHERE id IN ( $ids )" ); // db call ok. ; no-cache ok.
	}

	/**
	 * Count the number of emails with a specific status for a given campaign.
	 *
	 * This function queries the database to get the count of emails with a specific status associated with a given campaign.
	 *
	 * @param int    $campaign_id The ID of the campaign for which to count the emails.
	 * @param string $status The status of the emails to count.
	 * @return int The count of emails with the specified status for the given campaign.
	 *
	 * @since 1.9.0
	 */
	public static function count_delivered_status_on_automation_sequence( $campaign_id, $status ) {
		global $wpdb;
		$email_table    = $wpdb->prefix . EmailSchema::$table_name;
		$campaign_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		return $wpdb->get_var( $wpdb->prepare("SELECT COUNT(id) FROM {$email_table} WHERE email_id IN (SELECT id FROM {$campaign_table} WHERE campaign_id = %d) AND status = %s", $campaign_id, $status) ); //phpcs:ignore
	}

	/**
	 * Count the number of emails with a specific metric for a given campaign.
	 *
	 * This function queries the database to get the count of emails with a specific metric (meta_key) associated with a given campaign.
	 *
	 * @param int    $campaign_id The ID of the campaign for which to count the emails.
	 * @param string $type The type of metric to count (e.g., 'opened', 'clicked', etc.).
	 * @return int The count of emails with the specified metric for the given campaign.
	 *
	 * @since 1.9.0
	 */
	public static function count_email_metrics_on_automation_sequence( $campaign_id, $type ) {
		global $wpdb;
		$broadcast_email_table      = $wpdb->prefix . EmailSchema::$table_name;
		$broadcast_email_meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;
		$campaign_table             = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$query = $wpdb->prepare( " SELECT COUNT(mint_email_id) FROM {$broadcast_email_meta_table} WHERE meta_key = %s AND meta_value = %d AND mint_email_id IN ( SELECT id FROM {$broadcast_email_table} WHERE email_id IN ( SELECT id FROM {$campaign_table} WHERE campaign_id = %d ) )", $type, 1, $campaign_id );
		return $wpdb->get_var( $query );
	}

	/**
	 * Get the total count of broadcast email IDs sent to a specific campaign.
	 *
	 * @param int    $campaign_id The ID of the campaign for whom the total count of broadcast email IDs is retrieved.
	 * @param string $email_status The status of the emails to count.
	 *
	 * @return int The total count of broadcast email IDs sent to the specified campaign.
	 * @since 1.9.0
	 */
	public static function count_broadcast_email_ids_to_campaign( $campaign_id, $email_status = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . EmailSchema::$table_name;

		// Prepare SQL query to count emails.
		$query = "SELECT COUNT(id) as total FROM $table_name WHERE campaign_id = %d";

		// Add email status condition to the query if $email_status is not empty
		if ( !empty( $email_status ) ) {
			$query .= $wpdb->prepare( ' AND status = %s', $email_status );
		}

		return $wpdb->get_var( $wpdb->prepare( $query, $campaign_id ) );
	}

	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}
