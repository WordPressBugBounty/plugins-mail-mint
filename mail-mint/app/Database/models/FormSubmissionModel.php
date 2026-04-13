<?php
/**
 * Manage form submission related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 */

namespace Mint\MRM\DataBase\Models;

use Mint\MRM\DataBase\Tables\FormSubmissionsSchema;
use Mint\MRM\DataBase\Tables\FormEntryDetailsSchema;

/**
 * FormSubmissionModel class
 *
 * Manage form submission related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class FormSubmissionModel {

	/**
	 * Insert a new form submission record.
	 *
	 * @param array $data Associative array of column => value pairs.
	 *                    Expected keys: form_id, user_id, contact_id, source_url,
	 *                    status, browser, device, ip, city, country,
	 *                    utm_source, utm_medium, utm_campaign, utm_term, utm_content.
	 *
	 * @return int|false The inserted row ID on success, false on failure.
	 *
	 * @since 1.16.0
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$table = $wpdb->prefix . FormSubmissionsSchema::$table_name;

		$result = $wpdb->insert( $table, $data ); // db call ok.

		return $result ? $wpdb->insert_id : false;
	}


	/**
	 * Insert a single form entry detail row.
	 *
	 * @param int    $submission_id The ID of the parent form submission.
	 * @param string $field_name    The form field name/key.
	 * @param string $field_type    The field type (e.g. 'text', 'email', 'select').
	 * @param mixed  $field_value   The submitted value (arrays are JSON-encoded).
	 *
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.16.0
	 */
	public static function insert_entry_detail( $submission_id, $field_name, $field_type, $field_value ) {
		global $wpdb;

		$table = $wpdb->prefix . FormEntryDetailsSchema::$table_name;

		if ( is_array( $field_value ) || is_object( $field_value ) ) {
			$field_value = wp_json_encode( $field_value );
		}

		return (bool) $wpdb->insert( // db call ok.
			$table,
			array(
				'submission_id' => (int) $submission_id,
				'field_name'    => sanitize_text_field( $field_name ),
				'field_type'    => sanitize_text_field( (string) $field_type ),
				'field_value'   => $field_value,
			)
		);
	}


	/**
	 * Insert multiple entry detail rows for a submission in a single query.
	 *
	 * @param int   $submission_id The ID of the parent form submission.
	 * @param array $fields        Array of arrays, each with keys:
	 *                             'field_name', 'field_type', 'field_value'.
	 *
	 * @return void
	 *
	 * @since 1.16.0
	 */
	public static function insert_entry_details_bulk( $submission_id, array $fields ) {
		foreach ( $fields as $field ) {
			$field_name  = isset( $field['field_name'] ) ? $field['field_name'] : '';
			$field_type  = isset( $field['field_type'] ) ? $field['field_type'] : '';
			$field_value = isset( $field['field_value'] ) ? $field['field_value'] : '';

			if ( '' === $field_name ) {
				continue;
			}

			self::insert_entry_detail( $submission_id, $field_name, $field_type, $field_value );
		}
	}


	/**
	 * Get a single submission by ID.
	 *
	 * @param int $submission_id Submission primary key.
	 *
	 * @return array|null Row as associative array, or null if not found.
	 *
	 * @since 1.16.0
	 */
	public static function get( $submission_id ) {
		global $wpdb;

		$table = $wpdb->prefix . FormSubmissionsSchema::$table_name;

		return $wpdb->get_row( // db call ok.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", (int) $submission_id ),
			ARRAY_A
		);
	}


	/**
	 * Get all entry detail rows for a submission.
	 *
	 * @param int $submission_id Submission primary key.
	 *
	 * @return array Array of rows as associative arrays.
	 *
	 * @since 1.16.0
	 */
	public static function get_entry_details( $submission_id ) {
		global $wpdb;

		$table = $wpdb->prefix . FormEntryDetailsSchema::$table_name;

		return $wpdb->get_results( // db call ok.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE submission_id = %d", (int) $submission_id ),
			ARRAY_A
		);
	}


	/**
	 * Get paginated submissions for a form, each with its field values keyed by field_name.
	 *
	 * Returns:
	 * {
	 *   data: [ { id, form_id, contact_id, source_url, status, browser, device, ip,
	 *              created_at, fields: { field_name: field_value, ... } }, ... ],
	 *   count: <total rows>,
	 *   total_pages: <n>
	 * }
	 *
	 * @param int    $form_id    Form ID to filter by.
	 * @param int    $page       1-based page number.
	 * @param int    $per_page   Rows per page.
	 * @param string $order_by   Column to sort by ('created_at' or 'id'). Default 'created_at'.
	 * @param string $order_type Sort direction ('ASC' or 'DESC'). Default 'DESC'.
	 * @param string $search     Optional email string to filter by.
	 *
	 * @return array
	 *
	 * @since 1.16.0
	 */
	public static function get_form_entries( $form_id, $page = 1, $per_page = 25, $order_by = 'created_at', $order_type = 'DESC', $search = '', $read_status = '' ) {
		global $wpdb;

		$submissions_table   = $wpdb->prefix . FormSubmissionsSchema::$table_name;
		$entry_details_table = $wpdb->prefix . FormEntryDetailsSchema::$table_name;

		$offset = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		// Whitelist order_by and order_type to prevent SQL injection.
		$allowed_order_by = array( 'created_at', 'id' );
		$order_by_col     = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'created_at';
		$order_type_sql   = 'ASC' === strtoupper( (string) $order_type ) ? 'ASC' : 'DESC';

		$search = sanitize_text_field( (string) $search );

		// Build status clause.
		// 'all' (or empty) => exclude trashed so only read+unread show.
		// 'read', 'unread', 'trashed' => filter to that exact status.
		$allowed_statuses = array( 'read', 'unread', 'trashed' );
		if ( in_array( $read_status, $allowed_statuses, true ) ) {
			$status_clause = $wpdb->prepare( ' AND status = %s', $read_status );
		} else {
			$status_clause = " AND status != 'trashed'";
		}

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			// Total count with search.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var( // db call ok.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d{$status_clause} AND id IN (SELECT submission_id FROM {$entry_details_table} WHERE field_name = 'email' AND field_value LIKE %s)",
					(int) $form_id,
					$like
				)
			);

			// Paginated submission rows with search.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$submissions = $wpdb->get_results( // db call ok.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$submissions_table} WHERE form_id = %d{$status_clause} AND id IN (SELECT submission_id FROM {$entry_details_table} WHERE field_name = 'email' AND field_value LIKE %s) ORDER BY {$order_by_col} {$order_type_sql} LIMIT %d OFFSET %d",
					(int) $form_id,
					$like,
					(int) $per_page,
					(int) $offset
				),
				ARRAY_A
			);
		} else {
			// Total count.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var( // db call ok.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d{$status_clause}",
					(int) $form_id
				)
			);

			// Paginated submission rows.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$submissions = $wpdb->get_results( // db call ok.
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$submissions_table} WHERE form_id = %d{$status_clause} ORDER BY {$order_by_col} {$order_type_sql} LIMIT %d OFFSET %d",
					(int) $form_id,
					(int) $per_page,
					(int) $offset
				),
				ARRAY_A
			);
		}

		if ( empty( $submissions ) ) {
			return array(
				'data'        => array(),
				'count'       => $count,
				'total_pages' => 0,
			);
		}

		// Collect all submission IDs for a single JOIN query.
		$ids         = array_column( $submissions, 'id' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$details = $wpdb->get_results( // db call ok.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT submission_id, field_name, field_value FROM {$entry_details_table} WHERE submission_id IN ({$placeholders})",
				...$ids
			),
			ARRAY_A
		);

		// Index details by submission_id.
		$details_map = array();
		foreach ( $details as $detail ) {
			$details_map[ $detail['submission_id'] ][ $detail['field_name'] ] = $detail['field_value'];
		}

		// Attach fields to each submission row.
		foreach ( $submissions as &$submission ) {
			$submission['fields'] = isset( $details_map[ $submission['id'] ] ) ? $details_map[ $submission['id'] ] : array();
		}
		unset( $submission );

		return array(
			'data'        => $submissions,
			'count'       => $count,
			'total_pages' => (int) ceil( $count / $per_page ),
		);
	}

	/**
	 * Fetch a single form submission with its field values and adjacent entry IDs.
	 *
	 * Returns:
	 * {
	 *   submission: { id, form_id, user_id, contact_id, source_url, status, browser, device, ip, created_at, ... },
	 *   fields:     { field_name: field_value, ... },
	 *   prev_id:    <int|null>,
	 *   next_id:    <int|null>,
	 * }
	 *
	 * @param int $form_id  Form ID.
	 * @param int $entry_id Submission ID.
	 *
	 * @return array|false  False when entry not found or does not belong to the form.
	 * @since 1.16.0
	 */
	public static function get_single_entry( $form_id, $entry_id ) {
		global $wpdb;

		$submissions_table   = $wpdb->prefix . FormSubmissionsSchema::$table_name;
		$entry_details_table = $wpdb->prefix . FormEntryDetailsSchema::$table_name;

		// Fetch the submission row.
		$submission = $wpdb->get_row( // db call ok.
			$wpdb->prepare(
				"SELECT * FROM {$submissions_table} WHERE id = %d AND form_id = %d",
				(int) $entry_id,
				(int) $form_id
			),
			ARRAY_A
		);

		if ( empty( $submission ) ) {
			return false;
		}

		// Fetch field values.
		$details = $wpdb->get_results( // db call ok.
			$wpdb->prepare(
				"SELECT field_name, field_value FROM {$entry_details_table} WHERE submission_id = %d",
				(int) $entry_id
			),
			ARRAY_A
		);

		$fields = array();
		foreach ( $details as $detail ) {
			$fields[ $detail['field_name'] ] = $detail['field_value'];
		}

		// Adjacent IDs (ordered by created_at DESC to match list page ordering).
		$prev_id = $wpdb->get_var( // db call ok.
			$wpdb->prepare(
				"SELECT id FROM {$submissions_table} WHERE form_id = %d AND id > %d ORDER BY id ASC LIMIT 1",
				(int) $form_id,
				(int) $entry_id
			)
		);
		$next_id = $wpdb->get_var( // db call ok.
			$wpdb->prepare(
				"SELECT id FROM {$submissions_table} WHERE form_id = %d AND id < %d ORDER BY id DESC LIMIT 1",
				(int) $form_id,
				(int) $entry_id
			)
		);

		return array(
			'submission' => $submission,
			'fields'     => $fields,
			'prev_id'    => $prev_id ? (int) $prev_id : null,
			'next_id'    => $next_id ? (int) $next_id : null,
		);
	}

	/**
	 * Mark a form submission as read.
	 *
	 * @param int $entry_id Submission ID.
	 *
	 * @return bool True on success, false on failure.
	 * @since 1.16.0
	 */
	public static function mark_as_read( $entry_id ) {
		return self::update_status( $entry_id, 'read' );
	}

	/**
	 * Update the status of a form submission.
	 *
	 * @param int    $entry_id Submission ID.
	 * @param string $status   New status ('read'|'unread'|'trashed').
	 *
	 * @return bool True on success, false on failure.
	 * @since 1.16.0
	 */
	public static function update_status( $entry_id, $status ) {
		global $wpdb;

		$allowed = array( 'read', 'unread', 'trashed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$table = $wpdb->prefix . FormSubmissionsSchema::$table_name;

		return false !== $wpdb->update( // db call ok.
			$table,
			array( 'status' => $status ),
			array( 'id'     => (int) $entry_id )
		);
	}

	/**
	 * Delete one or more form submissions and their associated entry detail rows.
	 *
	 * @param array $ids Array of submission IDs to delete.
	 *
	 * @return bool True on success, false when $ids is empty or query fails.
	 * @since 1.16.0
	 */
	public static function delete_entries( array $ids ) {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$ids          = array_values( array_unique( array_map( 'absint', $ids ) ) );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$submissions_table   = $wpdb->prefix . FormSubmissionsSchema::$table_name;
		$entry_details_table = $wpdb->prefix . FormEntryDetailsSchema::$table_name;

		// Remove detail rows first.
		$wpdb->query( // db call ok.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$entry_details_table} WHERE submission_id IN ({$placeholders})",
				...$ids
			)
		);

		// Remove submission rows.
		$result = $wpdb->query( // db call ok.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$submissions_table} WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		return false !== $result;
	}
}
