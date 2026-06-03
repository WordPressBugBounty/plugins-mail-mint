<?php
/**
 * DatabaseIndexes
 *
 * Adds performance indexes required by the Data Cleanup feature.
 * Each index is added only if it does not already exist (idempotent).
 *
 * @package Mint\MRM\Internal\DataCleanup
 * @since   1.0.0
 */

namespace Mint\MRM\Internal\DataCleanup;

/**
 * Class DatabaseIndexes
 *
 * Responsible for creating the composite indexes used by the Data Cleanup
 * export and delete queries. Safe to run multiple times — each ALTER TABLE
 * is guarded by a SHOW INDEX check so it is a no-op when the index already
 * exists.
 *
 * @package Mint\MRM\Internal\DataCleanup
 * @since   1.0.0
 */
class DatabaseIndexes {

	/**
	 * Index definitions.
	 *
	 * Each entry describes one index to create:
	 *   'table'   – bare table name without the wpdb prefix (e.g. 'mint_broadcast_emails')
	 *   'name'    – index name
	 *   'columns' – comma-separated column list for the index
	 *
	 * @var array[]
	 * @since 1.0.0
	 */
	private static $indexes = array(
		array(
			'table'   => 'mint_broadcast_emails',
			'name'    => 'idx_status_scheduled',
			'columns' => 'status, scheduled_at',
		),
		array(
			'table'   => 'mint_automation_log',
			'name'    => 'idx_status_created',
			'columns' => 'status, created_at',
		),
		array(
			'table'   => 'mint_automation_log',
			'name'    => 'idx_email_status',
			'columns' => 'email, status',
		),
		array(
			'table'   => 'mint_automation_jobs',
			'name'    => 'idx_status_created',
			'columns' => 'status, created_at',
		),
		array(
			'table'   => 'mint_abandoned_carts',
			'name'    => 'idx_status_created',
			'columns' => 'status, created_at',
		),
		array(
			'table'   => 'mint_form_submissions',
			'name'    => 'idx_created',
			'columns' => 'created_at',
		),
		array(
			'table'   => 'mint_lead_magnet_download_tracking',
			'name'    => 'idx_used_expires_created',
			'columns' => 'is_used, expires_at, created_at',
		),
	);

	/**
	 * Create all Data Cleanup performance indexes.
	 *
	 * Iterates over the index definitions and adds each one only when it is
	 * not already present on the table.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function create_indexes() {
		global $wpdb;

		foreach ( self::$indexes as $index ) {
			$table = $wpdb->prefix . $index['table'];

			// Skip if the table does not exist (e.g. pro-only tables on free installs).
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				continue;
			}

			// Skip if the index already exists.
			if ( self::index_exists( $table, $index['name'] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `{$index['name']}` ({$index['columns']})" );
		}
	}

	/**
	 * Check whether a named index already exists on a table.
	 *
	 * @param string $table      Full table name (including wpdb prefix).
	 * @param string $index_name Index name to look up.
	 *
	 * @return bool True when the index exists, false otherwise.
	 * @since 1.0.0
	 */
	private static function index_exists( $table, $index_name ) {
		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SHOW INDEX FROM `%1s` WHERE Key_name = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$table,
				$index_name
			)
		);

		return ! empty( $results );
	}
}
