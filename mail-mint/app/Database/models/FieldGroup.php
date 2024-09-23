<?php
/**
 * Manage Field Groups related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use MRM\DB\Tables\CustomFieldGroup;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * FieldGroup class
 *
 * Manage Field Groups related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class FieldGroup {

	use Singleton;

	/**
	 * Insert group information to database
	 *
	 * @param int $group Field group object.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function insert( $group ) {
		global $wpdb;
		$group_table = $wpdb->prefix . CustomFieldGroup::$table_name;

		try {
			$wpdb->insert(
				$group_table,
				array(
					'title'      => $group->get_title(),
					'created_at' => current_time( 'mysql' ),
				)
			); // db call ok; no-cache ok.
			return $wpdb->insert_id;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Update group information to database
	 *
	 * @param object $group Field group object.
	 * @param int    $id group id.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update( $group, $id ) {
		global $wpdb;
		$group_table = $wpdb->prefix . CustomFieldGroup::$table_name;

		try {
			$wpdb->update(
				$group_table,
				array(
					'title'      => $group->get_title(),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $id )
			); // db call ok. no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Run SQL query to get groups from database
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all() {
		global $wpdb;
		$group_table = $wpdb->prefix . CustomFieldGroup::$table_name;

		// Return field froups for list view.
		try {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$select_query = $wpdb->prepare( "SELECT * FROM $group_table ORDER BY id ASC" );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$query_results = $wpdb->get_results( $select_query ); // db call ok. no-cache ok.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared

			return array(
				'data' => $query_results,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}


	/**
	 * Delete a group from the database
	 *
	 * @param mixed $id      Field group ID.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy( $id ) {
		global $wpdb;
		$group_table = $wpdb->prefix . CustomFieldGroup::$table_name;

		try {
			$wpdb->delete( $group_table, array( 'id' => $id ), array( '%d' ) ); // db call ok. no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Run SQL query to get a single field group
	 *
	 * @param int $id   Group ID.
	 *
	 * @return array an array of results if successfull, NULL otherwise
	 * @since 1.0.0
	 */
	public static function get( $id ) {
		global $wpdb;
		$group_table = $wpdb->prefix . CustomFieldGroup::$table_name;

		try {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$select_query = $wpdb->prepare( "SELECT * FROM $group_table WHERE id = %d", array( $id ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$select_result = $wpdb->get_results( $select_query ); // db call ok. no-cache ok.
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			return $select_result;
		} catch ( \Exception $e ) {
			return false;
		}
	}

}
