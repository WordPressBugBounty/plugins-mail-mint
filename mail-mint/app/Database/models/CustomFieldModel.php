<?php
/**
 * Manage Custom Fields related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use Mint\MRM\DataBase\Tables\CustomFieldSchema;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * FormModel class
 *
 * Manage Custom Fields related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class CustomFieldModel {

	use Singleton;

	/**
	 * Insert fields information to database
	 *
	 * @param mixed $field Field object.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function insert( $field ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;

		try {
			$wpdb->insert(
				$fields_table,
				array(
					'title'      => $field->get_title(),
					'slug'       => $field->get_slug(),
					'type'       => $field->get_type(),
					'meta'       => $field->get_meta(),
					'created_at' => current_time( 'mysql' ),
				)
			); // db call ok. ; no-cache ok.
			return $wpdb->insert_id;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Update fields information to database
	 *
	 * @param object $field Field object.
	 * @param int    $id Field ID.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update( $field, $id ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;

		try {
			$wpdb->update(
				$fields_table,
				array(
					'title'      => $field->get_title(),
					'slug'       => $field->get_slug(),
					'type'       => $field->get_type(),
					'meta'       => $field->get_meta(),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $id )
			); // db call ok. ; no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Run SQL query to get fields from database
	 *
	 * @param int    $offset offset.
	 * @param int    $limit limiting value.
	 * @param string $search search parameter.
	 * @param string $order_by sorting order.
	 * @param string $order_type sorting order type.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all( $offset = 0, $limit = 20, $search = '', $order_by = 'id', $order_type = 'DESC' ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;

		$search_terms = null;
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "WHERE `title` LIKE '%%$search%%'";
		}
		// Return field froups for list view.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $fields_table {$search_terms} ORDER BY %s %s  LIMIT %d, %d", $order_by, $order_type, $offset, $limit );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'data' => $results,
		);
	}

	/**
	 * Run SQL query to get fields from database
	 *
	 * @return array
	 * @since 1.0.8
	 */
	public static function get_custom_fields_to_map() {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( 'SELECT title as name, slug, meta FROM %1s ', $fields_table ), ARRAY_A ); //phpcs:ignore
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Delete a field from the database
	 *
	 * @param mixed $id      Field ID.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy( $id ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;

		try {
			$wpdb->delete( $fields_table, array( 'id' => $id ), array( '%d' ) ); // db call ok. ; no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Run SQL query to get a single field
	 *
	 * @param int $id   Field ID.
	 *
	 * @return object an object of results if successfull, NULL otherwise
	 * @since 1.0.0
	 */
	public static function get( $id ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;

		try {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$select_query = $wpdb->prepare( "SELECT * FROM $fields_table WHERE id = %d", array( $id ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$select_result = $wpdb->get_row( $select_query ); // db call ok. ; no-cache ok.
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

			return $select_result;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Check existing custom fields
	 *
	 * @param mixed $slug slug value.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_field_exist( $slug ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT * FROM $fields_table WHERE slug = %s", array( $slug ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$select_result = $wpdb->get_results( $select_query ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		if ( $select_result ) {
			return true;
		}
		return false;
	}

	/**
	 * Get custom field id by slug
	 *
	 * @param mixed $slug slug value.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_id_by_slug( $slug ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT `id` FROM $fields_table WHERE slug = %s", array( $slug ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$select_result = $wpdb->get_var( $select_query ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		return $select_result;
	}

	/**
	 * Run SQL query to map custom fields with segmentation
	 *
	 * @return array
	 * @since 1.0.8
	 */
	public static function get_custom_fields_to_segment() {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( 'SELECT title as label, slug as value, type, meta FROM %1s ', $fields_table ), ARRAY_A ); //phpcs:ignore
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Prepare Custom Select Field Data.
	 *
	 * This function is used to prepare data for a custom select field, specifically for checkboxField type.
	 * It checks if the field with the given key exists in the custom fields table, and if so, processes the provided value.
	 * If the field is of type 'checkboxField', it ensures that the value is an array (serialized), even if it was provided as a string.
	 *
	 * @access public
	 *
	 * @param string $key   The key or slug of the custom field.
	 * @param mixed  $value The value of the custom field.
	 *
	 * @return mixed The processed value, possibly serialized as an array for checkboxField type, or the original value.
	 * @since 1.5.7
	 */
	public static function prepare_custom_select_field_data( $key, $value ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;
		$result       = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $fields_table WHERE slug = %s AND type = 'checkboxField'", $key ) );  //phpcs:ignore
		if ( $result ) {
			if ( !is_array( $value ) ) {
				$new_value = explode( ',', $value );

				// If $array is empty (i.e., $inputString didn't contain a comma), add $inputString as the single element.
				if ( empty( $new_value ) ) {
					$new_value = array( $value );
				}
				return maybe_serialize( $new_value );
			}
			return maybe_serialize( $value );
		}
		return $value;
	}

	/**
	 * Get custom fields by type
	 *
	 * @param mixed $type type value.
	 *
	 * @return array
	 * @since 1.11.0
	 */
	public static function get_custom_fields_by_type( $type ) {
		global $wpdb;
		$fields_table = $wpdb->prefix . CustomFieldSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( 'SELECT title, slug FROM %1s WHERE type = %s', $fields_table, $type ), ARRAY_A ); //phpcs:ignore
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

}
