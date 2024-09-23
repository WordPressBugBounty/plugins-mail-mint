<?php
/**
 * ContactGroupPivotModel class.
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
use Mint\MRM\DataBase\Tables\ContactSchema;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * ContactGroupPivotModel class
 *
 * Manage contact and group relationship related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class ContactGroupPivotModel {

	use Singleton;


	/**
	 * Run SQL query to insert contact and groups relation
	 *
	 * @param array $pivot_ids get all the ids.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function add_groups_to_contact( $pivot_ids ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		if ( empty( $pivot_ids ) ) {
			return false;
		}

		$contact_ids = array();
		$group_id    = 0;

		if ( is_array( $pivot_ids ) && count( $pivot_ids ) ) {
			foreach ( $pivot_ids as $id ) {
				if ( !in_array( $id['contact_id'], $contact_ids, true ) ) {
					$contact_ids[] = $id['contact_id'];
					$group_id      = $id['group_id'];
				}
			}
		}
		$group_exists    = ContactGroupModel::is_group_exist_by_id( $group_id );
		$contacts_exists = ContactModel::is_contact_ids_exists( $contact_ids );

		if ( !$group_exists || !$contacts_exists ) {
			return false;
		}

		try {
			if ( is_array( $pivot_ids ) && count( $pivot_ids ) ) {
				foreach ( $pivot_ids as $id ) {
					$wpdb->insert(
						$table_name,
						array(
							'contact_id' => $id['contact_id'],
							'group_id'   => $id['group_id'],
							'created_at' => current_time( 'mysql' ),
						)
					); // db call ok.
				}
				return true;
			}
			return false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Returns list of contacts related to a group
	 *
	 * @param mixed $ids group ids.
	 * @param int   $offset offset per batch.
	 * @param int   $per_batch per batch count.
	 * @param bool  $count_only count only.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_contacts_to_group( $ids, $offset = 0, $per_batch = 0, $count_only = false ) {
		if ( empty( $ids ) ) {
			return array();
		}
		global $wpdb;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;
		$pivot_table   = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		$ids = implode( ', ', $ids );

		if ( $count_only ) {
			$select_fields      = 'COUNT(DISTINCT cgp.contact_id)';
			$get_query_function = 'get_var';
		} else {
			$select_fields      = 'DISTINCT cgp.contact_id';
			$get_query_function = 'get_results';
		}

		try {
			$select_query = $wpdb->prepare( "SELECT %1s FROM %1s AS cgp JOIN %1s AS c ON cgp.contact_id = c.id AND c.status = %s WHERE cgp.group_id IN( %1s )", $select_fields, $pivot_table, $contact_table, 'subscribed',$ids ); //phpcs:ignore
			if ( $per_batch ) {
				$select_query = $select_query . $wpdb->prepare( ' LIMIT %d, %d', (int) $offset, (int) $per_batch );
			}
			return $wpdb->$get_query_function( $select_query );
		} catch ( \Exception $e ) {
			return array();
		}
	}


	/**
	 * Run SQL query to delete contacts and groups relation from pivot table
	 *
	 * @param mixed $contact_id individual contact id.
	 * @param mixed $groups all group ids to delete.
	 *
	 * @return bool
	 * @since 1.0.0
	 * @since 1.9.4 Added the ability to remove a contact from multiple groups.
	 */
	public static function delete_groups_to_contact( $contact_id, $groups ) {
		if ( !$contact_id || empty( $groups ) ) {
			return false;
		}

		/**
		 * Fires after a contact is removed from one or more mailing lists.
		 *
		 * This action hook allows developers to execute custom code after a contact
		 * has been removed from one or more mailing lists.
		 *
		 * @param array $groups    An array of mailing list IDs from which the contact was removed.
		 * @param array $contact_id The ID of the contact that was removed from the mailing lists.
		 * @since 1.9.4
		 */
		do_action( 'mint_list_removed', $groups, array( $contact_id ) );

		/**
		 * Fires after a contact is removed from one or more mailing tags.
		 *
		 * This action hook allows developers to execute custom code after a contact
		 * has been removed from one or more mailing tags.
		 *
		 * @param array $groups    An array of mailing list IDs from which the contact was removed.
		 * @param array $contact_id The ID of the contact that was removed from the mailing tags.
		 * @since 1.9.4
		 */
		do_action( 'mint_tag_removed', $groups, array( $contact_id ) );

		global $wpdb;
		$pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$groups      = implode( ',', array_map( 'intval', $groups ) );
		try {
            return $wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE `contact_id` = %d AND `group_id` IN (%1s)', $pivot_table, (int) $contact_id, $groups ) ); //phpcs:ignore
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Run SQL Query to get group ids related to a contact
	 *
	 * @param mixed $contact_id contact id to get groups.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_groups_to_contact( $contact_id ) {
		global $wpdb;
		$pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		try {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT group_id FROM %1s WHERE contact_id = %d', $pivot_table, (int) $contact_id ) ); //phpcs:ignore
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Returns list of contacts related to a group
	 *
	 * @param array $groups group ids.
	 * @param int   $offset offset of the data.
	 * @param int   $limit limit of the data.
	 *
	 * @return array|false
	 * @since 1.0.0
	 */
	public static function get_contacts_to_campaign( $groups, $offset, $limit ) {
		global $wpdb;
		$pivot_table   = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;

		try {
			$groups        = implode( ',', array_map( 'intval', $groups ) );
			$select_query = $wpdb->prepare('SELECT * FROM %1s ', $contact_table); //phpcs:ignore
			$select_query .= $wpdb->prepare('INNER JOIN %1s ON %1s.contact_id = %1s.id ', $pivot_table, $pivot_table, $contact_table); //phpcs:ignore
			$select_query .= $wpdb->prepare('WHERE %1s.group_id in (%1s) LIMIT %d, %d', $pivot_table, $groups, (int)$offset, (int)$limit); //phpcs:ignore
			return $wpdb->get_results( $select_query ); //phpcs:ignore
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Returns list of contacts related to a group
	 *
	 * @param mixed $groups group ids.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_contacts_count_to_campaign( $groups ) {
		global $wpdb;
		$pivot_table   = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$contact_table = $wpdb->prefix . ContactSchema::$table_name;

		try {
			$groups        = implode( ',', array_map( 'intval', $groups ) );
			$select_query = $wpdb->prepare('SELECT COUNT(*) as total FROM %1s ', $contact_table); //phpcs:ignore
			$select_query .= $wpdb->prepare('INNER JOIN %1s ON %1s.contact_id = %1s.id ', $pivot_table, $pivot_table, $contact_table); //phpcs:ignore
			$select_query .= $wpdb->prepare('WHERE %1s.group_id in (%1s) ', $pivot_table, $groups); //phpcs:ignore

			return $wpdb->get_var( $select_query ); //phpcs:ignore
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Removes specified groups from multiple contacts.
	 *
	 * This function removes the specified groups from multiple contacts based on the provided group IDs and contact IDs.
	 *
	 * @param array $items       An array containing group information with IDs to be removed.
	 * @param array $contact_ids An array containing contact IDs from which groups will be removed.
	 *
	 * @access public
	 *
	 * @return bool Returns true if the removal is successful, otherwise false.
	 * @since 1.5.1
	 * @since 1.9.4 Added the ability to remove groups from multiple contacts.
	 */
	public static function remove_groups_from_contacts( $items, $contact_ids ) {
		// Check if either items or contact IDs are empty.
		if ( empty( $items ) || empty( $contact_ids ) ) {
			return false;
		}

		$groups = array_column( $items, 'id' );
		/**
		 * Fires after a contact is removed from one or more mailing lists.
		 *
		 * This action hook allows developers to execute custom code after a contact
		 * has been removed from one or more mailing lists.
		 *
		 * @param array $groups      An array of mailing list IDs from which the contact was removed.
		 * @param array $contact_ids The ID of the contact that was removed from the mailing lists.
		 * @since 1.9.4
		 */
		do_action( 'mint_list_removed', $groups, $contact_ids );

		/**
		 * Fires after a contact is removed from one or more mailing tags.
		 *
		 * This action hook allows developers to execute custom code after a contact
		 * has been removed from one or more mailing tags.
		 *
		 * @param array $groups      An array of mailing list IDs from which the contact was removed.
		 * @param array $contact_ids The ID of the contact that was removed from the mailing tags.
		 * @since 1.9.4
		 */
		do_action( 'mint_tag_removed', $groups, $contact_ids );

		global $wpdb;
		$pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		// Iterate through each item (group) to be removed.
		foreach ( $items as $item ) {
			$group_id = $item['id'];

			foreach ( $contact_ids as $contact_id ) {
				// Construct the SQL query to delete the group relationship.
				$sql = $wpdb->prepare(
					"DELETE FROM {$pivot_table} WHERE group_id = %d AND contact_id = %d", //phpcs:ignore
					$group_id,
					$contact_id
				);
				$wpdb->query( $sql ); //phpcs:ignore
			}
		}
		return true;
	}

	/**
	 * Checks if a contact belongs to one or more specified groups.
	 *
	 * This method checks whether a contact with the given ID belongs to any of the specified groups.
	 *
	 * @param int   $contact_id The ID of the contact to check.
	 * @param array $group_ids  An array containing the IDs of the groups to check against.
	 * @return bool True if the contact belongs to any of the specified groups, false otherwise.
	 * @since 1.9.4
	 */
	public static function is_group_exist_to_contact( $contact_id, $group_ids ) {
		global $wpdb;
		$pivot_table  = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

		$query = $wpdb->prepare( " SELECT COUNT(*) FROM {$pivot_table} WHERE contact_id = %d AND group_id IN ( $placeholders ) ", $contact_id, ...$group_ids );
		$count = $wpdb->get_var( $query );
		return $count > 0 ? true : false;
	}
}
