<?php
/**
 * Manage Contact Module database related operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use Mint\MRM\DataBase\Tables\ContactGroupPivotSchema;
use Mint\MRM\DataBase\Tables\ContactMetaSchema;
use Mint\MRM\DataBase\Tables\ContactNoteSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;
use Mint\MRM\DataStores\ContactData;
use Mint\MRM\Utilites\Helper\Contact;
use MRM\Common\MrmCommon;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\Utilities\Arr;

/**
 * ContactModel class
 *
 * Manage Contact Module database related operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class ContactModel {

	use Singleton;

	/**
	 * Insert contact information to database
	 *
	 * @param ContactData $contact contact.
	 *
	 * @return bool|int
	 * @since 1.0.0
	 */
	public static function insert( ContactData $contact ) {
		global $wpdb;

		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		try {
			$wpdb->insert(
				$contacts_table,
				array(
					'email'      => $contact->get_email(),
					'first_name' => $contact->get_first_name(),
					'last_name'  => $contact->get_last_name(),
					'status'     => $contact->get_status(),
					'source'     => $contact->get_source(),
					'hash'       => MrmCommon::get_rand_hash( $contact->get_email() ),
					'created_by' => $contact->get_created_by(),
					'wp_user_id' => $contact->get_wp_user_id(),
					'created_at' => current_time( 'mysql' ),
				)
			); // db call ok. ; no-cache ok.

			$insert_id = $wpdb->insert_id;

			$meta_fields['meta_fields'] = array();

			if ( ! empty( $contact->get_meta_fields() ) ) {
				$meta_fields['meta_fields'] = $contact->get_meta_fields();
			}

			if ( 'subscribed' === $contact->get_status() ) {
				$meta_fields[ 'meta_fields' ][ 'status_changed' ] = $contact->get_status();
			}

			if ( !empty( $meta_fields['meta_fields'] ) ) {
				self::update_meta_fields( $insert_id, $meta_fields );
			}

			/**
			 * Fires after a contact is created.
			 *
			 * @param int $insert_id The ID of the newly created contact.
			 * @since 1.14.4
			 */
			do_action('mint_after_contact_creation', $insert_id);
			return $insert_id;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Update a contact information
	 *
	 * @param mixed $args Entity and value to update.
	 * @param mixed $contact_id Contact ID.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update( $args, $contact_id ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		if ( !empty( $args[ 'status' ] ) && 'subscribed' === $args[ 'status' ] ) {
			$args[ 'meta_fields' ][ 'status_changed' ] = $args[ 'status' ];
		}

		if ( ! empty( $args['meta_fields'] ) ) {
			self::update_meta_fields( $contact_id, $args );
		}

		$args['updated_at'] = current_time( 'mysql' );
		unset(
			$args[ 'meta_fields' ],
			$args[ 'contact_id' ],
			$args[ 'total_spent' ],
			$args[ 'total_orders' ],
			$args[ 'click_rate' ],
			$args[ 'open_rate' ],
			$args[ 'activities' ],
			$args[ 'messages' ],
			$args[ 'notes' ],
			$args[ 'avatar_url' ],
			$args[ 'added_by_login' ],
			$args[ 'created_at' ],
			$args[ 'tags' ],
			$args[ 'lists' ],
			$args[ 'total_clicked' ],
			$args[ 'last_opened' ],
			$args[ 'total_sent' ],
			$args[ 'total_opened' ],
			$args[ '_locale' ],
			$args[ 'general_fields' ],
			$args[ 'customer_summery' ],
			$args[ 'groups' ]
		);

		try {
			$wpdb->update(
				$contacts_table,
				$args,
				array( 'ID' => $contact_id )
			); // db call ok. ; no-cache ok.
		} catch ( \Exception $e ) {
			return false;
		}
		return true;
	}

	/**
	 * Update a contact information
	 *
	 * @param mixed $contact_id     Contact ID.
	 * @param mixed $args           Entity and value to update.
	 *
	 * @return bool|void
	 * @since 1.0.0
	 */
	public static function update_meta_fields( $contact_id, $args ) {
		if ( !$contact_id || empty( $args ) ) {
			return false;
		}
		global $wpdb;
		$contacts_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;
		foreach ( $args['meta_fields'] as $key => $value ) {
			$value = CustomFieldModel::prepare_custom_select_field_data( $key, $value );

			if ( self::is_contact_meta_exist( $contact_id, $key ) ) {
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$wpdb->update(
					$contacts_meta_table,
					array(
						'meta_value' => $value,
						'updated_at' => current_time( 'mysql' ),
					),
					array(
						'meta_key'   => $key,
						'contact_id' => $contact_id,
					)
				); // db call ok. ; no-cache ok.
			} else {
				$wpdb->insert(
					$contacts_meta_table,
					array(
						'contact_id' => $contact_id,
						'meta_key'   => $key,
						'meta_value' => $value,
						'created_at' => current_time( 'mysql' ),
					)
				); // db call ok. ; no-cache ok.
			}
		}
	}


	/**
	 * Check existing contact through an email address
	 *
	 * @param string $email email.
	 *
	 * @return string|null
	 * @since 1.0.0
	 */
	public static function is_contact_exist( $email ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT `id` FROM $contacts_table WHERE email = %s", array( $email ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_var( $select_query ); // db call ok. ; no-cache ok.
	}

	/**
	 * Check existing contact through an email address
	 *
	 * @param array $ids contact id array.
	 *
	 * @return bool
	 * @since 1.0.4
	 */
	public static function is_contact_ids_exists( $ids ) {
		if ( empty( $ids ) ) {
			return false;
		}
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		$placeholders = array_fill( 0, count( $ids ), '%d' ); // Create placeholders for the prepare statement.

		// Generate the query string.
		$query = $wpdb->prepare( "SELECT COUNT(`id`) FROM {$contacts_table} WHERE `id` IN (" . implode( ', ', $placeholders ) . ")", $ids ); //phpcs:ignore

		$total = $wpdb->get_var( $query ); //phpcs:ignore

		if ( count( $ids ) === intval( $total ) ) {
			// All IDs exist in the database.
			return true;
		}
		return false;
	}

	/**
	 * Check existing contact through an email address and contact ID
	 *
	 * @param string $email email.
	 * @param int    $contact_id contact id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_contact_exist_by_id( $email, $contact_id ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		$select_query = $wpdb->prepare( "SELECT * FROM $contacts_table WHERE email = %s AND id = %d", array( $email, $contact_id ) );
		$results      = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.
		if ( $results ) {
			return true;
		}
		return false;
	}


	/**
	 * Delete a contact
	 *
	 * @param mixed $id contact id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy( $id ) {
		global $wpdb;
		$contacts_table            = $wpdb->prefix . ContactSchema::$table_name;
		$contact_meta_table        = $wpdb->prefix . ContactMetaSchema::$table_name;
		$contact_note_table        = $wpdb->prefix . ContactNoteSchema::$table_name;
		$contact_group_pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		try {
			self::remove_form_entries_for_deleted_contact( $id );
			$wpdb->delete( $contact_meta_table, array( 'contact_id' => $id ) ); // db call ok. ; no-cache ok.
			$wpdb->delete( $contact_note_table, array( 'contact_id' => $id ) ); // db call ok. ; no-cache ok.
			$wpdb->delete( $contact_group_pivot_table, array( 'contact_id' => $id ) ); // db call ok. ; no-cache ok.
			$wpdb->delete( $contacts_table, array( 'id' => $id ) ); // db call ok. ; no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Delete multiple contacts
	 *
	 * @param array $contact_ids contact id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy_all( $contact_ids ) {
		global $wpdb;
		$contacts_table            = $wpdb->prefix . ContactSchema::$table_name;
		$contact_meta_table        = $wpdb->prefix . ContactMetaSchema::$table_name;
		$contact_note_table        = $wpdb->prefix . ContactNoteSchema::$table_name;
		$contact_group_pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		try {
			if ( !empty( $contact_ids ) ) {
				foreach ( $contact_ids as $id ) {
					self::remove_form_entries_for_deleted_contact( $id );
				}
			}
			$contact_ids = implode( ',', array_map( 'intval', $contact_ids ) );

			$wpdb->query( "DELETE FROM $contacts_table WHERE id IN($contact_ids)" ); // db call ok. ; no-cache ok.
			$wpdb->query( "DELETE FROM $contact_meta_table WHERE contact_id IN($contact_ids)" ); // db call ok. ; no-cache ok.
			$wpdb->query( "DELETE FROM $contact_note_table WHERE contact_id IN($contact_ids)" ); // db call ok. ; no-cache ok.
			$wpdb->query( "DELETE FROM $contact_group_pivot_table WHERE contact_id IN($contact_ids)" ); // db call ok. ; no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Run SQL query to get or search contacts from database
	 *
	 * @param int    $offset offset.
	 * @param int    $limit limit.
	 * @param string $search search.
	 * @param array  $selected_contacts selected contact ids.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all( $offset = 0, $limit = 10, $search = '', $selected_contacts = array() ) {
		global $wpdb;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;
		$search_terms  = null;
		$export_terms  = null;
		// Search contacts by email, first name or last name.
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "WHERE (`hash` LIKE '%%$search%%' 
             OR `email` LIKE '%%$search%%' OR `first_name` LIKE '%%$search%%' OR `last_name` LIKE '%%$search%%'
             OR concat(`first_name`, ' ', `last_name`) LIKE '%%$search%%'
             OR `source` LIKE '%%$search%%' 
             OR `status` LIKE '%%$search%%' 
             OR `stage` LIKE '%%$search%%')";
		}

		if ( !empty( $selected_contacts ) ) {
			$contacts     = implode( ',', $selected_contacts );
			$export_terms = 'WHERE id IN (' . $contacts . ')';
		}

		// Prepare sql results for list view.
		$query_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $contact_table $search_terms $export_terms ORDER BY id DESC  LIMIT %d, %d", array( $offset, $limit ) ), ARRAY_A ); // db call ok. ; no-cache ok.
		$results       = array();

		foreach ( $query_results as $query_result ) {
			$q_id      = isset( $query_result['id'] ) ? $query_result['id'] : '';
			$new_meta  = self::get_meta( $q_id );

			if (isset($query_result['source']) && preg_match('/Form-(\d+)/', $query_result['source'], $matches)) {
				$form_id = $matches[1];
				// Assuming you have a method to get the form title by ID
				$form_title = FormModel::get_form_title( $form_id );
				$query_result['source'] =  'Mail Mint Form - ' . $form_title;
			}

			$results[] = array_merge( $query_result, $new_meta );
		}

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) as total FROM $contact_table $search_terms" ) ); // db call ok. ; no-cache ok.

		return array(
			'data'        => $results,
			'total_pages' => ceil( $count / $limit ),
			'total_count' => $count,
		);
	}


	/**
	 * Run SQL Query to get a single contact information
	 *
	 * @param mixed $id Contact ID.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get( $id ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		try {
			$contacts_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $contacts_table WHERE id = %d", array( $id ) ), ARRAY_A ); // db call ok. ; no-cache ok.
			$new_meta        = self::get_meta( $id );

			if ( is_array( $contacts_result ) && is_array( $new_meta ) ) {
				$contacts_result = array_merge( $contacts_result, $new_meta );
			}

			return is_array( $contacts_result ) ? $contacts_result : array();
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Run SQL Query to get a single contact email only
	 *
	 * @param mixed $ids Contact IDs.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_single_email( $ids ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		if ( is_array( $ids ) ) {
			$ids = ! empty( $ids ) ? implode( ', ', $ids ) : 0;
		}

		$sql = $wpdb->prepare( "SELECT `id`, `email` FROM %1s WHERE `id` IN( %1s ) AND `status` = %s", $contacts_table, $ids, 'subscribed' ); //phpcs:ignore
		return $wpdb->get_results( $sql, ARRAY_A ); // db call ok. ; no-cache ok.
	}


	/**
	 * Returns contact meta data
	 *
	 * @param int $id   Contact ID.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_meta( $id ) {
		global $wpdb;
		$contacts_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$meta_results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM $contacts_meta_table  WHERE contact_id = %d", array( $id ) ), ARRAY_A ); // db call ok. ; no-cache ok.

		$new_meta['meta_fields'] = array();
		foreach ( $meta_results as $result ) {
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$new_meta['meta_fields'][ $result['meta_key'] ] = maybe_unserialize( $result['meta_value'] );
		}

		return $new_meta;
	}


	/**
	 * Check existing contact through an email address
	 *
	 * @param string $contact_id contact id.
	 * @param string $key meta key.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_contact_meta_exist( $contact_id, $key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactMetaSchema::$table_name;

		try {
			$select_query = $wpdb->prepare( "SELECT * FROM $table_name WHERE contact_id = %d AND meta_key=%s", array( $contact_id, $key ) );
			$results      = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.
			if ( ! empty( $results ) ) {
				return true;
			}
		} catch ( \Throwable $th ) {
			return false;
		}
	}


	/**
	 * Check existing contact through an email address
	 *
	 * @param string $contact_id contact id.
	 * @param string $meta_key meta key.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_contact_meta_key_exist( $contact_id, $meta_key ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactMetaSchema::$table_name;

		try {
			$select_query = $wpdb->prepare( "SELECT * FROM $table_name WHERE contact_id = %d AND meta_key=%s", array( $contact_id, $meta_key ) );
			$results      = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.
			if ( ! empty( $results ) ) {
				return true;
			}
		} catch ( \Throwable $th ) {
			return false;
		}
	}


	/**
	 * Run SQL Query to get filtered Contacts
	 *
	 * @param mixed  $status status.
	 * @param mixed  $tags_ids tags ids.
	 * @param mixed  $lists_ids lists ids.
	 * @param int    $limit limit.
	 * @param int    $offset offset.
	 * @param string $search search.
	 *
	 * @return array|bool
	 * @since 1.0.0
	 */
	public static function get_filtered_contacts( $status, $tags_ids, $lists_ids, $limit = 10, $offset = 0, $search = '' ) {
		global $wpdb;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;
		$pivot_table   = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		// Prepare sql results for list view.
		try {
			$tags     = implode( ',', array_map( 'intval', $tags_ids ) );
			$lists    = implode( ',', array_map( 'intval', $lists_ids ) );
			$statuses = implode( '","', $status );

			$status_arr = 'status IN ("' . $statuses . '")';

			$and = 'AND';

			$contact_filter_query = "( $pivot_table.group_id IN ($tags) AND  tt1.group_id IN ($lists)
            AND $status_arr )";

			if ( count( $tags_ids ) === 0 && count( $lists_ids ) === 0 && count( $status ) === 0 ) {
				$and                  = '';
				$contact_filter_query = '';
			} elseif ( count( $tags_ids ) === 0 && count( $lists_ids ) === 0 && count( $status ) !== 0 ) {
				$contact_filter_query = "( $status_arr )";
			} elseif ( count( $tags_ids ) === 0 && count( $lists_ids ) !== 0 && count( $status ) === 0 ) {
				$contact_filter_query = " (tt1.group_id IN ($lists))";
			} elseif ( count( $tags_ids ) === 0 && count( $lists_ids ) !== 0 && count( $status ) !== 0 ) {
				$contact_filter_query = " (tt1.group_id IN ($lists) AND $status_arr)";
			} elseif ( count( $tags_ids ) !== 0 && count( $lists_ids ) === 0 && count( $status ) === 0 ) {
				$contact_filter_query = " ($pivot_table.group_id IN ($tags))";
			} elseif ( count( $tags_ids ) !== 0 && count( $lists_ids ) === 0 && count( $status ) !== 0 ) {
				$contact_filter_query = "( $pivot_table.group_id IN ($tags) AND $status_arr )";
			} elseif ( count( $tags_ids ) !== 0 && count( $lists_ids ) !== 0 && count( $status ) === 0 ) {
				$contact_filter_query = "( $pivot_table.group_id IN ($tags) AND  tt1.group_id IN ($lists))";
			}

			$search = $wpdb->esc_like( $search );
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery
			$select_query = $wpdb->prepare(
				"SELECT * , $contact_table.id FROM $contact_table 
            LEFT JOIN $pivot_table ON ($contact_table.id = $pivot_table.contact_id)  
            LEFT JOIN $pivot_table AS tt1 ON ($contact_table.id = tt1.contact_id)
            WHERE (`hash` LIKE '%%$search%%' OR `email` LIKE '%%$search%%' OR
                 `first_name` LIKE '%%$search%%' OR `last_name` LIKE '%%$search%%' OR concat(`first_name`, ' ', `last_name`) LIKE '%%$search%%'
                 OR `source` LIKE '%%$search%%' OR `status` LIKE '%%$search%%' OR 
                 `stage` LIKE '%%$search%%') $and $contact_filter_query
                 GROUP BY $contact_table.id
                LIMIT $offset, $limit"
			);

			$query_results = $wpdb->get_results( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
			$results       = array();

			foreach ( $query_results as $query_result ) {
				$q_id      = isset( $query_result['id'] ) ? $query_result['id'] : '';
				$new_meta  = self::get_meta( $q_id );

				if (isset($query_result['source']) && preg_match('/Form-(\d+)/', $query_result['source'], $matches)) {
					$form_id = $matches[1];
					// Assuming you have a method to get the form title by ID
					$form_title = FormModel::get_form_title($form_id);
					$query_result['source'] =  'Mail Mint Form - ' . $form_title;
				}

				$results[] = array_merge( $query_result, $new_meta );
			}

			$count_query = $wpdb->prepare(
				"SELECT COUNT(*) AS total FROM $contact_table
            LEFT JOIN $pivot_table ON ($contact_table.id = $pivot_table.contact_id)  
            LEFT JOIN $pivot_table AS tt1 ON ($contact_table.id = tt1.contact_id)
            WHERE 
            (`hash` LIKE '%%$search%%' OR `email` LIKE '%%$search%%' OR
                 `first_name` LIKE '%%$search%%' OR `last_name` LIKE '%%$search%%' 
                 OR `source` LIKE '%%$search%%' OR `status` LIKE '%%$search%%' OR 
                 `stage` LIKE '%%$search%%') $and $contact_filter_query
                GROUP BY $contact_table.id
            "
			);
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery

			$count_result = $wpdb->get_results( $count_query ); // db call ok. ; no-cache ok.

			$count = (int) count( $count_result );

			$total_pages = ceil( $count / $limit );

			$data = array(
				'data'        => json_decode( wp_json_encode( $results ), true ),
				'total_pages' => $total_pages,
				'count'       => $count,
			);

			return $data;
		} catch ( \Exception $e ) {
			return null;
		}
	}


	/**
	 * Return custiom fields for mapping
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function mrm_contact_custom_attributes() {
		global $wpdb;
		$contacts_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$select_query = $wpdb->prepare(
			"SELECT DISTINCT meta_key FROM $contacts_meta_table WHERE meta_key NOT IN ('first_name', 
                                                                                                                    'last_name',
                                                                                                                    'email',
                                                                                                                    'date_of_birth',
                                                                                                                    'company_name',
                                                                                                                    'address_line_1',	
                                                                                                                    'address_line_2',
                                                                                                                    'postal_code',
                                                                                                                    'city',	
                                                                                                                    'state',
                                                                                                                    'country',
                                                                                                                    'phone',
                                                                                                                    'timezone'
                                                                                                                    )"
		);
		$results      = json_decode( wp_json_encode( $wpdb->get_results( $select_query ) ), true ); // db call ok. ; no-cache ok.

		$custom_fields = array_map(
			function( $result ) {
				return $result['meta_key'];
			},
			$results
		);
		return $custom_fields;
	}

	/**
	 * Get Total Number of contacts
	 *
	 * @param int $contact_id contact id.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function get_total_count( $contact_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactSchema::$table_name;

		$select_query       = $wpdb->prepare( "SELECT COUNT(*) as total FROM $table_name" );
		$total_subscribed   = $wpdb->prepare( "SELECT COUNT(*) as subscribed FROM $table_name WHERE status='subscribed'" );
		$total_unsubscribed = $wpdb->prepare( "SELECT COUNT(*) as unsubscribed FROM $table_name WHERE status='unsubscribed'" );
		$total_pending      = $wpdb->prepare( "SELECT COUNT(*) as pending FROM $table_name WHERE status='pending'" );

		$contacts     = json_decode( wp_json_encode( $wpdb->get_results( $select_query ) ), true ); // db call ok. ; no-cache ok.
		$subscribed   = json_decode( wp_json_encode( $wpdb->get_results( $total_subscribed ) ), true ); // db call ok. ; no-cache ok.
		$unsubscribed = json_decode( wp_json_encode( $wpdb->get_results( $total_unsubscribed ) ), true ); // db call ok. ; no-cache ok.
		$pending      = json_decode( wp_json_encode( $wpdb->get_results( $total_pending ) ), true ); // db call ok. ; no-cache ok.

		$results = array(
			'total_contacts'     => $contacts[0]['total'],
			'total_subscribed'   => $subscribed[0]['subscribed'],
			'total_unsubscribed' => $unsubscribed[0]['unsubscribed'],
			'total_pending'      => $pending[0]['pending'],
		);

		if ( ! empty( $results ) ) {
			return $results;
		}
		return false;
	}


	/**
	 * Return total number of contacts
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_contacts_count() {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactSchema::$table_name;
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(`id`) FROM %1s', $table_name ) ) ); //phpcs:ignore
	}


	/**
	 * Return total number of contacts based on status
	 *
	 * @param string $status status.
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_contacts_status_count( $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactSchema::$table_name;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE status= %s", array( $status ) ) ) ); // db call ok. ; no-cache ok.
	}

	/**
	 * Return total number of contacts based on status
	 *
	 * @return array
	 * @since 1.4.10
	 */
	public static function get_contact_total() {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactSchema::$table_name;
        $get_total = $wpdb->get_row(  //phpcs:ignore
			$wpdb->prepare(
				'SELECT
            COUNT(CASE WHEN status = %s THEN 1 END) AS subscribed,
            COUNT(CASE WHEN status = %s THEN 1 END) AS pending,
            COUNT(CASE WHEN status = %s THEN 1 END) AS unsubscribed
        FROM %1s', array( 'subscribed', 'pending', 'unsubscribed', $table_name )), ARRAY_A);  //phpcs:ignore
		if ( !empty( $get_total ) ) {
			return $get_total;
		}
		return array();
	}


	/**
	 * Run SQL Query to get a single contact information by hash
	 *
	 * @param mixed $hash Contact email address hash.
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function get_by_hash( $hash ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $contacts_table WHERE hash = %s", array( $hash ) ), ARRAY_A ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Run SQL Query to get a single contact email address
	 *
	 * @param mixed $contact_id Contact ID.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_email_by_id( $contact_id ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		return $wpdb->get_row( $wpdb->prepare( "SELECT email FROM $contacts_table WHERE id = %d", array( $contact_id ) ), ARRAY_A ); //phpcs:ignore
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Update contact subscription status
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $status Updated contact status.
	 *
	 * @return void
	 */
	public static function update_subscription_status( $contact_id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactSchema::$table_name;

		$wpdb->update( //phpcs:ignore
			$table_name,
			array( 'status' => $status ),
			array( 'id' => $contact_id )
		);
	}


	/**
	 * Run SQL Query to get a single contact email address
	 *
	 * @param string $email Contact ID.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_id_by_email( $email ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;
		$contact_id =  $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $contacts_table WHERE email = %s", array( $email ) ) ); //phpcs:ignore
		return $contact_id;
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get user by wp user id
	 *
	 * @param string|int $user_id WP User ID.
	 *
	 * @return string|null
	 */
	public static function get_user_id_by_wp_user_id( $user_id ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		$query  = 'SELECT `id` FROM %1s ';
		$query .= 'WHERE wp_user_id=%d';

		$mailmint_user_id = $wpdb->get_var( $wpdb->prepare( $query, $contacts_table, (int)$user_id ) ); //phpcs:ignore

		if ( $mailmint_user_id ) {
			return $mailmint_user_id;
		}

		$contacts_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = 'SELECT `contact_id` FROM %1s ';
		$query .= 'WHERE `meta_key`=%s ';
		$query .= 'AND `meta_value`=%d';

		return $wpdb->get_var( $wpdb->prepare( $query, $contacts_meta_table, '_wc_customer_id', (int)$user_id ) ); //phpcs:ignore
	}


	/**
	 * Return contact information via email address
	 *
	 * @param mixed $email contact email address.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_contact_by_email( $email ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, first_name, last_name, status, created_at, email ,hash FROM $contacts_table WHERE email = %s", array( $email ) ), ARRAY_A ); //phpcs:ignore
	}

	/**
	 * Prepare contact information for WooCommerce order edit page
	 *
	 * @param string $email contact email address.
	 * @param string $shop_name Shop name.
	 *
	 * @return array
	 * @since 1.0.0
	 * @since 1.9.1 Retrieve contact meta fields through contact_id.
	 */
	public function contact_information_to_shop_order( $email, $shop_name ) {
		$contact    = self::get_contact_by_email( $email );
		$contact_id = isset( $contact['id'] ) ? $contact['id'] : '';
		$contact    = self::get( $contact_id );

		// Get and merge tags and lists.
		if ( $contact ) {
			$contact = ContactGroupModel::get_tags_to_contact( $contact );
			$contact = ContactGroupModel::get_lists_to_contact( $contact );

			if ( isset( $contact[ 'created_at' ] ) ) {
				$contact[ 'created_at' ] = MrmCommon::date_time_format_with_core( $contact[ 'created_at' ] );
			}

			$contact['avatar_url'] = Contact::get_avatar_url( $contact );

			if ( 'wc' === $shop_name ) {
				$contact = MrmCommon::get_wc_customer_revenue_history( $email, $contact );
			} elseif ( 'edd' === $shop_name ) {
				$contact = MrmCommon::get_edd_customer_revenue_history( $email, $contact );
			}
		}

		return $contact;
	}

	/**
	 * Return contact source via id
	 *
	 * @param mixed $id contact id.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_contact_source_by_id( $id ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;
        return $wpdb->get_row( $wpdb->prepare( "SELECT source FROM $contacts_table WHERE id = %d", array( $id ) ), ARRAY_A ); //phpcs:ignore
	}

	/**
	 *  /**
	 * Get source form id which is added by form.
	 *
	 * @param string $source Get Contact source.
	 * @return string
	 */
	public static function get_form_id_by_source( $source ) {
		if ( !$source ) {
			return null;
		}
		$source_form = explode( '-', $source );
		$form_id     = isset( $source_form['1'] ) ? $source_form['1'] : null;
		return $form_id;
	}

	/**
	 * Check contact source is form or not.
	 *
	 * @param string $source Get contact source.
	 * @return bool|null
	 */
	public static function is_contact_source_by_form( $source ) {
		if ( !$source ) {
			return null;
		}
		if ( preg_match( '/form/i', $source ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Remove form entries when contact is deleted
	 *
	 * @param int $id Contact id.
	 * @return bool
	 */
	public static function remove_form_entries_for_deleted_contact( $id ) {
		if ( !$id && !is_numeric( $id ) ) {
			return false;
		}

		$source = self::get_contact_source_by_id( $id );
		if ( !isset( $source['source'] ) ) {
			return false;
		}

		$is_source_form = self::is_contact_source_by_form( $source['source'] );
		if ( !$is_source_form ) {
			return false;
		}

		$form_id = self::get_form_id_by_source( $source['source'] );
		if ( !$form_id ) {
			return false;
		}

		$entries = FormModel::get_form_meta_value_with_key( $form_id, 'entries' );
		if ( isset( $entries['meta_fields']['entries'] ) ) {
			$entries_count       = isset( $entries['meta_fields']['entries'] ) ? $entries['meta_fields']['entries'] : 0;
			$args['meta_fields'] = array(
				'entries' => $entries_count - 1,
			);
			return FormModel::update_meta_fields( $form_id, $args );
		}
		return false;
	}

	/**
	 * Updates the status of multiple contacts.
	 *
	 * This function updates the status of the specified contacts with the provided status.
	 *
	 * @access public
	 *
	 * @param array  $contact_ids An array containing the IDs of the contacts to update.
	 * @param string $status      The status to set for the contacts.
	 *
	 * @return int|false The number of rows affected by the update operation or false on failure.
	 * @since 1.5.1
	 */
	public static function update_contact_status( $contact_ids, $status ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		// Convert the IDs array into a comma-separated string.
		$ids_str      = implode( ',', $contact_ids );
		$update_query = $wpdb->prepare( "UPDATE {$contacts_table} SET status = %s WHERE id IN ($ids_str)", $status ); //phpcs:ignore
		return $wpdb->query( $update_query ); //phpcs:ignore
	}

	/**
	 * Return contact email address by contact id
	 *
	 * @param mixed $contact_id contact id.
	 *
	 * @return array
	 * @since 1.7.0
	 */
	public static function get_contact_email_by_id( $contact_id ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT email FROM $contacts_table WHERE id = %d", array( $contact_id ) ) ); //phpcs:ignore
	}

	/**
	 * Summary: Insert form submission into contact meta table.
	 *
	 * Description: This static method inserts a form submission record into the contact meta table.
	 *
	 * @access public
	 *
	 * @param int    $contact_id The ID of the contact associated with the form submission.
	 * @param string $form_id    The ID or identifier of the submitted form.
	 *
	 * @return int|false Returns the number of rows affected on success; otherwise, false.
	 *
	 * @since 1.7.1
	 */
	public static function insert_form_submission( $contact_id, $form_id ) {
		global $wpdb;
		$table = $wpdb->prefix . ContactMetaSchema::$table_name;
		$args  = array(
			'contact_id' => $contact_id,
			'meta_key'   => '_form_id',
			'meta_value' => $form_id,
			'created_at' => current_time( 'mysql' ),
		);
		return $wpdb->insert( $table, $args ); //phpcs:ignore
	}

	/**
	 * Retrieves the full name of a contact.
	 *
	 * This method retrieves the contact associated with the provided email address and returns the contact's full name.
	 * If no contact is found, the method returns null.
	 *
	 * @param string $email The email address of the contact.
	 * @return string|null The full name of the contact, or null if no contact is found.
	 *
	 * @since 1.9.0
	 */
	public static function get_contact_full_name( $email ) {
		$contact = self::get_contact_by_email( $email );
		if ( !empty( $contact ) ) {
			return $contact['first_name'] . ' ' . $contact['last_name'];
		}
	}

	public static function get_meta_value_by_key( $meta_key, $contact_id ){
		global $wpdb;
		$table_name = $wpdb->prefix . ContactMetaSchema::$table_name;
    	return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $table_name WHERE meta_key = %s AND contact_id = %d", $meta_key, $contact_id ) );
	}

	/**
	 * Run SQL Query to get a single contact information by email.
	 *
	 * @param mixed $email Contact email address.
	 *
	 * @return array
	 * @since 1.14.5
	 */
	public static function get_contact_data_by_email( $email ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		$contact    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $contacts_table WHERE email = %s", array( $email ) ), ARRAY_A ); // db call ok. ; no-cache ok.
		$contact_id = isset( $contact['id'] ) ? $contact['id'] : '';
		$new_meta   = self::get_meta( $contact_id );

		if ( is_array( $contact ) && is_array( $new_meta ) ) {
			$contact = array_merge( $contact, $new_meta );
		}

		if (isset($contact['meta_fields']) && is_array($contact['meta_fields'])) {
			$contact = array_merge($contact, $contact['meta_fields']);
			unset($contact['meta_fields']);
		}

		return is_array( $contact ) ? $contact : array();
	}

	/**
	 * Records an unsubscribe event for a contact based on the provided data.
	 *
	 * This function updates the contact's status and records the reason for the unsubscribe event.
	 * If the contact does not exist, it creates a new contact record with the provided data.
	 *
	 * @param array $data {
	 *     The data for the unsubscribe event.
	 *
	 *     @type string $email    The email address of the contact.
	 *     @type string $status   The new status of the contact (e.g., 'bounced', 'unsubscribed'). Default 'bounced'.
	 *     @type string $reason   The reason for the status change. Default empty string.
	 *     @type string $provider The email service provider (e.g., 'Mailgun', 'SendGrid'). Default empty string.
	 * }
	 * @return bool True if the unsubscribe event was recorded successfully, false otherwise.
	 * @since 1.15.0
	 */
	public static function record_unsubscribe($data){
		if(empty($data['email']) || !is_email($data['email'])) {
			return false;
		}

		$contact  = self::get_contact_by_email($data['email']);
		$status   = isset($data['status']) ? $data['status'] : 'bounced';
		$reason   = isset($data['reason']) ? $data['reason'] : '';
		$provider = isset($data['provider']) ? $data['provider'] : '';

		$key = $status === 'unsubscribed' ? 'unsubscribe_reason' : 'reason';

		$user_data = array(
			'meta_fields' => array(
				$key => $reason,
			),
			'status' => $status,
			'source' => $provider,
		);

		if ($contact) {
			$old_status = isset($contact['status']) ? $contact['status'] : 'subscribed';
			ContactModel::update($user_data, $contact['id']);

			/*
			 * Fires when a contact's status is changed to 'bounced', 'unsubscribed', or 'pending'.
			 *
			 * @param int    $contact_id The ID of the contact.
			 * @param string $old_status The previous status of the contact.
			 * @since 1.15.0
			 */
			do_action('mint_subscriber_status_to_' . $status, $contact['id'], $old_status);
		} else {
			$contact = new ContactData($data['email'], $user_data);
			ContactModel::insert($contact);
		}

		return true;
	}
}
