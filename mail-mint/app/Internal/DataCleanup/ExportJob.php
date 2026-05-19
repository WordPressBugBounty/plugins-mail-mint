<?php
/**
 * Class ExportJob
 *
 * Action Scheduler job that exports eligible rows to a CSV file in chunks.
 * Implements keyset pagination, UTF-8 BOM writing, self-chaining, and
 * auto-dispatch of DeleteJob on completion.
 *
 * @package Mint\MRM\Internal\DataCleanup
 * @since 1.0.0
 */

namespace Mint\MRM\Internal\DataCleanup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSV export for the Data Cleanup feature.
 *
 * Each invocation processes one chunk of up to CHUNK_SIZE rows for a single
 * category, writes them to disk via fputcsv(), updates job state progress,
 * and self-chains to the next chunk when more rows remain.
 *
 * @since 1.0.0
 */
class ExportJob {

	/**
	 * Number of rows to process per chunk.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CHUNK_SIZE = 10000;

	/**
	 * Export file TTL in seconds (24 hours).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const EXPORT_TTL = 86400;

	/**
	 * Hook name for the scheduled file-deletion cron event.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DELETE_FILE_HOOK = 'mailmint_cleanup_delete_export_file';

	/**
	 * Entry point called by Action Scheduler.
	 *
	 * Receives the job arguments array and delegates to process_chunk().
	 *
	 * @since 1.0.0
	 * @param array $args {
	 *     Job arguments.
	 *
	 *     @type string[] $categories     Selected category keys.
	 *     @type int      $retention_days Days of data to keep.
	 *     @type string   $action         'export_then_delete' or 'delete_only'.
	 *     @type string   $export_file    Absolute path to the CSV file (set after first chunk).
	 *     @type int      $last_id        Last processed primary key for keyset pagination.
	 *     @type int      $category_index Index into $categories for the current category.
	 * }
	 * @return void
	 */
	public static function handle( array $args ): void {
		$instance = new self();
		$instance->process_chunk( $args );
	}

	/**
	 * Processes one chunk of rows for the current category.
	 *
	 * @since 1.0.0
	 * @param array $args Job arguments (see handle() for shape).
	 * @return void
	 */
	public function process_chunk( array $args ): void {
		try {
			$state_manager = new JobStateManager();
			$state         = $state_manager->get_state();

			// Abort if the job was cancelled or failed externally.
			if ( ! in_array( $state['status'], array( 'exporting' ), true ) ) {
				return;
			}

			$categories     = isset( $args['categories'] ) ? (array) $args['categories'] : $state['categories'];
			$retention_days = isset( $args['retention_days'] ) ? (int) $args['retention_days'] : (int) $state['retention_days'];
			$action         = isset( $args['action'] ) ? $args['action'] : $state['action'];
			$category_index = isset( $args['category_index'] ) ? (int) $args['category_index'] : 0;
			$last_id        = isset( $args['last_id'] ) ? (int) $args['last_id'] : 0;
			$export_file    = isset( $args['export_file'] ) ? $args['export_file'] : null;

			// Initialise the export file on the very first chunk.
			if ( empty( $export_file ) ) {
				$export_file = $this->create_export_file();
				if ( ! $export_file ) {
					throw new \RuntimeException( 'Failed to create export file.' );
				}
			}

			$ref_date = $this->compute_ref_date( $retention_days );
			$category = isset( $categories[ $category_index ] ) ? $categories[ $category_index ] : null;

			if ( null === $category ) {
				// All categories processed — finalise.
				$this->complete_export( $export_file, $action, $state_manager );
				return;
			}

			// Write a blank separator row before each new category section (except the first).
			if ( $category_index > 0 && $last_id === 0 ) {
				$this->write_blank_row( $export_file );
			}

			$rows_written = $this->export_category_chunk( $category, $ref_date, $last_id, $export_file );

			// Update progress.
			$new_rows_exported = (int) $state['rows_exported'] + $rows_written;
			$total_rows        = max( 1, (int) $state['total_rows'] );
			$export_progress   = min( 99, (int) round( ( $new_rows_exported / $total_rows ) * 100 ) );

			$state_manager->update_state(
				array(
					'rows_exported'   => $new_rows_exported,
					'export_progress' => $export_progress,
				)
			);

			if ( $rows_written < self::CHUNK_SIZE ) {
				// This category is exhausted — move to the next one.
				$next_args = array(
					'categories'     => $categories,
					'retention_days' => $retention_days,
					'action'         => $action,
					'export_file'    => $export_file,
					'last_id'        => 0,
					'category_index' => $category_index + 1,
				);
			} else {
				// More rows remain in this category — continue with next chunk.
				$next_args = array(
					'categories'     => $categories,
					'retention_days' => $retention_days,
					'action'         => $action,
					'export_file'    => $export_file,
					'last_id'        => $this->get_last_processed_id( $category, $ref_date, $last_id ),
					'category_index' => $category_index,
				);
			}

			// Check if all categories are done.
			if ( $next_args['category_index'] >= count( $categories ) ) {
				$this->complete_export( $export_file, $action, $state_manager );
				return;
			}

			// Self-chain to next chunk.
			as_enqueue_async_action(
				DataCleanupScheduler::EXPORT_HOOK,
				array( $next_args ),
				DataCleanupScheduler::GROUP
			);

		} catch ( \Exception $e ) {
			$state_manager = new JobStateManager();
			$state_manager->transition(
				'failed',
				array( 'error' => $e->getMessage() )
			);
			throw $e;
		}
	}

	/**
	 * Creates the export directory and file, writes the UTF-8 BOM.
	 *
	 * @since 1.0.0
	 * @return string|false Absolute path to the created file, or false on failure.
	 */
	private function create_export_file() {
		$upload_dir = wp_upload_dir();
		$export_dir = trailingslashit( $upload_dir['basedir'] ) . 'mail-mint/exports';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$timestamp   = gmdate( 'YmdHis' );
		$export_file = trailingslashit( $export_dir ) . 'cleanup-' . $timestamp . '.csv';

		// Write UTF-8 BOM so Excel opens the file correctly.
		$handle = fopen( $export_file, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return false;
		}

		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $export_file;
	}

	/**
	 * Computes the reference date cutoff from retention_days.
	 *
	 * Uses current_time( 'mysql' ) so the cutoff is in WordPress local time,
	 * matching the preview controller and MySQL's NOW().
	 *
	 * @since 1.0.0
	 * @param int $retention_days Days of data to keep.
	 * @return string MySQL datetime string in local time.
	 */
	private function compute_ref_date( int $retention_days ): string {
		return date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . " -{$retention_days} days" ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
	}

	/**
	 * Dispatches the correct category export method and returns rows written.
	 *
	 * @since 1.0.0
	 * @param string $category    Category key.
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed primary key (keyset pagination).
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written in this chunk.
	 */
	private function export_category_chunk( string $category, string $ref_date, int $last_id, string $export_file ): int {
		switch ( $category ) {
			case 'broadcast_emails':
				return $this->export_broadcast_emails( $ref_date, $last_id, $export_file );
			case 'email_engagement':
				return $this->export_broadcast_email_meta( $ref_date, $last_id, $export_file );
			case 'automation_log':
				return $this->export_automation_log( $ref_date, $last_id, $export_file );
			case 'automation_jobs':
				return $this->export_automation_jobs( $ref_date, $last_id, $export_file );
			case 'form_submissions':
				return $this->export_form_submissions( $ref_date, $last_id, $export_file );
			case 'abandoned_carts':
				return $this->export_abandoned_carts( $ref_date, $last_id, $export_file );
			case 'download_tracking':
				return $this->export_lead_magnet_download_tracking( $ref_date, $last_id, $export_file );
			default:
				return 0;
		}
	}

	/**
	 * Returns the highest primary key processed in the last chunk for keyset pagination.
	 *
	 * @since 1.0.0
	 * @param string $category Category key.
	 * @param string $ref_date Cutoff datetime string.
	 * @param int    $last_id  Previous last_id.
	 * @return int The new last_id to use for the next chunk.
	 */
	private function get_last_processed_id( string $category, string $ref_date, int $last_id ): int {
		global $wpdb;

		$table_map = array(
			'broadcast_emails' => array( 'table' => $wpdb->prefix . 'mint_broadcast_emails', 'col' => 'id', 'where' => "status = 'sent' AND scheduled_at < %s AND id > %d" ),
			'email_engagement' => array( 'table' => $wpdb->prefix . 'mint_broadcast_email_meta', 'col' => 'mint_email_id', 'where' => "created_at < %s AND mint_email_id > %d" ),
			'automation_log'   => array( 'table' => $wpdb->prefix . 'mint_automation_log', 'col' => 'id', 'where' => "status IN ('completed','exited') AND created_at < %s AND id > %d" ),
			'automation_jobs'  => array( 'table' => $wpdb->prefix . 'mint_automation_jobs', 'col' => 'id', 'where' => "status = 'completed' AND created_at < %s AND id > %d" ),
			'form_submissions' => array( 'table' => $wpdb->prefix . 'mint_form_submissions', 'col' => 'id', 'where' => "created_at < %s AND id > %d" ),
			'abandoned_carts'  => array( 'table' => $wpdb->prefix . 'mint_abandoned_carts', 'col' => 'id', 'where' => "status IN ('recovered','lost') AND created_at < %s AND id > %d" ),
			'download_tracking' => array( 'table' => $wpdb->prefix . 'mint_lead_magnet_download_tracking', 'col' => 'id', 'where' => "(is_used = 1 OR expires_at < NOW()) AND created_at < %s AND id > %d" ),
		);

		if ( ! isset( $table_map[ $category ] ) ) {
			return $last_id;
		}

		$cfg = $table_map[ $category ];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max_id = $wpdb->get_var( $wpdb->prepare( "SELECT MAX({$cfg['col']}) FROM {$cfg['table']} WHERE {$cfg['where']} ORDER BY {$cfg['col']} LIMIT %d", $ref_date, $last_id, self::CHUNK_SIZE ) );

		return $max_id ? (int) $max_id : $last_id;
	}


	// -------------------------------------------------------------------------
	// Category export methods
	// -------------------------------------------------------------------------

	/**
	 * Exports a chunk of mint_broadcast_emails rows to CSV.
	 *
	 * Columns: email_address, first_name, last_name, campaign_title,
	 *          automation_name, email_type, status, sent_date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed id for keyset pagination.
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written.
	 */
	private function export_broadcast_emails( string $ref_date, int $last_id, string $export_file ): int {
		global $wpdb;

		$be_table   = $wpdb->prefix . 'mint_broadcast_emails';
		$c_table    = $wpdb->prefix . 'mint_contacts';
		$camp_table = $wpdb->prefix . 'mint_campaigns';
		$auto_table = $wpdb->prefix . 'mint_automations';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					be.email_address,
					c.first_name,
					c.last_name,
					camp.title     AS campaign_title,
					auto.name      AS automation_name,
					be.email_type,
					be.status,
					be.scheduled_at AS sent_date
				FROM {$be_table} be
				LEFT JOIN {$c_table} c    ON be.contact_id    = c.id
				LEFT JOIN {$camp_table} camp ON be.campaign_id = camp.id
				LEFT JOIN {$auto_table} auto ON be.automation_id = auto.id
				WHERE be.status = 'sent'
				  AND be.scheduled_at < %s
				  AND be.id > %d
				ORDER BY be.id
				LIMIT %d",
				$ref_date,
				$last_id,
				self::CHUNK_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$this->write_rows_to_csv(
			$export_file,
			$rows,
			array( 'email_address', 'first_name', 'last_name', 'campaign_title', 'automation_name', 'email_type', 'status', 'sent_date' ),
			$last_id === 0
		);

		return count( $rows );
	}

	/**
	 * Exports a chunk of mint_broadcast_email_meta rows to CSV (pivoted).
	 *
	 * Columns: email_address, campaign_title, is_open, open_device, open_ip,
	 *          is_click, click_device, click_ip, is_unsubscribe, date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed mint_email_id for keyset pagination.
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written.
	 */
	private function export_broadcast_email_meta( string $ref_date, int $last_id, string $export_file ): int {
		global $wpdb;

		$bem_table  = $wpdb->prefix . 'mint_broadcast_email_meta';
		$be_table   = $wpdb->prefix . 'mint_broadcast_emails';
		$camp_table = $wpdb->prefix . 'mint_campaigns';

		// The meta table has no 'type' column — pivot only on meta_key values.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					be.email_address                                                                           AS email_address,
					camp.title                                                                                 AS campaign_title,
					MAX(CASE WHEN bem.meta_key = 'is_open'        THEN bem.meta_value END)                    AS is_open,
					MAX(CASE WHEN bem.meta_key = 'is_click'       THEN bem.meta_value END)                    AS is_click,
					MAX(CASE WHEN bem.meta_key = 'is_unsubscribe' THEN bem.meta_value END)                    AS is_unsubscribe,
					bem.created_at                                                                             AS date
				FROM {$bem_table} bem
				INNER JOIN {$be_table} be     ON bem.mint_email_id = be.id
				LEFT  JOIN {$camp_table} camp ON be.campaign_id    = camp.id
				WHERE bem.created_at < %s
				  AND bem.mint_email_id > %d
				GROUP BY bem.mint_email_id
				ORDER BY bem.mint_email_id
				LIMIT %d",
				$ref_date,
				$last_id,
				self::CHUNK_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$this->write_rows_to_csv(
			$export_file,
			$rows,
			array( 'email_address', 'campaign_title', 'is_open', 'is_click', 'is_unsubscribe', 'date' ),
			$last_id === 0
		);

		return count( $rows );
	}


	/**
	 * Exports a chunk of mint_automation_log rows to CSV.
	 *
	 * Columns: email, automation_name, step_id, status, execution_count, created_date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed id for keyset pagination.
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written.
	 */
	private function export_automation_log( string $ref_date, int $last_id, string $export_file ): int {
		global $wpdb;

		$al_table   = $wpdb->prefix . 'mint_automation_log';
		$auto_table = $wpdb->prefix . 'mint_automations';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					al.email,
					a.name             AS automation_name,
					al.step_id,
					al.status,
					al.count           AS execution_count,
					al.created_at      AS created_date
				FROM {$al_table} al
				LEFT JOIN {$auto_table} a ON al.automation_id = a.id
				WHERE al.status IN ('completed', 'exited')
				  AND al.created_at < %s
				  AND al.id > %d
				ORDER BY al.id
				LIMIT %d",
				$ref_date,
				$last_id,
				self::CHUNK_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$this->write_rows_to_csv(
			$export_file,
			$rows,
			array( 'email', 'automation_name', 'step_id', 'status', 'execution_count', 'created_date' ),
			$last_id === 0
		);

		return count( $rows );
	}

	/**
	 * Exports a chunk of mint_automation_jobs rows to CSV.
	 *
	 * Columns: automation_name, next_step_id, status, created_date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed id for keyset pagination.
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written.
	 */
	private function export_automation_jobs( string $ref_date, int $last_id, string $export_file ): int {
		global $wpdb;

		$aj_table   = $wpdb->prefix . 'mint_automation_jobs';
		$auto_table = $wpdb->prefix . 'mint_automations';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					a.name         AS automation_name,
					aj.next_step_id,
					aj.status,
					aj.created_at  AS created_date
				FROM {$aj_table} aj
				LEFT JOIN {$auto_table} a ON aj.automation_id = a.id
				WHERE aj.status = 'completed'
				  AND aj.created_at < %s
				  AND aj.id > %d
				ORDER BY aj.id
				LIMIT %d",
				$ref_date,
				$last_id,
				self::CHUNK_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$this->write_rows_to_csv(
			$export_file,
			$rows,
			array( 'automation_name', 'next_step_id', 'status', 'created_date' ),
			$last_id === 0
		);

		return count( $rows );
	}


	/**
	 * Exports a chunk of mint_form_submissions rows to CSV.
	 *
	 * Columns: form_title, contact_email, status, browser, device, ip,
	 *          city, country, utm_source, utm_medium, utm_campaign, created_date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed id for keyset pagination.
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written.
	 */
	private function export_form_submissions( string $ref_date, int $last_id, string $export_file ): int {
		global $wpdb;

		$fs_table = $wpdb->prefix . 'mint_form_submissions';
		$f_table  = $wpdb->prefix . 'mint_forms';
		$c_table  = $wpdb->prefix . 'mint_contacts';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					f.title        AS form_title,
					c.email        AS contact_email,
					fs.status,
					fs.browser,
					fs.device,
					fs.ip,
					fs.city,
					fs.country,
					fs.utm_source,
					fs.utm_medium,
					fs.utm_campaign,
					fs.created_at  AS created_date
				FROM {$fs_table} fs
				LEFT JOIN {$f_table} f ON fs.form_id    = f.id
				LEFT JOIN {$c_table} c ON fs.contact_id = c.id
				WHERE fs.created_at < %s
				  AND fs.id > %d
				ORDER BY fs.id
				LIMIT %d",
				$ref_date,
				$last_id,
				self::CHUNK_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$this->write_rows_to_csv(
			$export_file,
			$rows,
			array( 'form_title', 'contact_email', 'status', 'browser', 'device', 'ip', 'city', 'country', 'utm_source', 'utm_medium', 'utm_campaign', 'created_date' ),
			$last_id === 0
		);

		return count( $rows );
	}

	/**
	 * Exports a chunk of mint_abandoned_carts rows to CSV.
	 *
	 * Columns: email, automation_name, status, cart_total, provider, created_date.
	 * LONGTEXT columns (items, checkout_data) are excluded.
	 *
	 * @since 1.0.0
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed id for keyset pagination.
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written.
	 */
	private function export_abandoned_carts( string $ref_date, int $last_id, string $export_file ): int {
		global $wpdb;

		$ac_table   = $wpdb->prefix . 'mint_abandoned_carts';
		$auto_table = $wpdb->prefix . 'mint_automations';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ac.email,
					a.name         AS automation_name,
					ac.status,
					ac.total       AS cart_total,
					ac.provider,
					ac.created_at  AS created_date
				FROM {$ac_table} ac
				LEFT JOIN {$auto_table} a ON ac.automation_id = a.id
				WHERE ac.status IN ('recovered', 'lost')
				  AND ac.created_at < %s
				  AND ac.id > %d
				ORDER BY ac.id
				LIMIT %d",
				$ref_date,
				$last_id,
				self::CHUNK_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$this->write_rows_to_csv(
			$export_file,
			$rows,
			array( 'email', 'automation_name', 'status', 'cart_total', 'provider', 'created_date' ),
			$last_id === 0
		);

		return count( $rows );
	}

	/**
	 * Exports a chunk of mint_lead_magnet_download_tracking rows to CSV.
	 *
	 * Columns: email, is_used, expires_at, ip_address, created_date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date    Cutoff datetime string.
	 * @param int    $last_id     Last processed id for keyset pagination.
	 * @param string $export_file Absolute path to the CSV file.
	 * @return int Number of rows written.
	 */
	private function export_lead_magnet_download_tracking( string $ref_date, int $last_id, string $export_file ): int {
		global $wpdb;

		$lmdt_table = $wpdb->prefix . 'mint_lead_magnet_download_tracking';

		// The table stores email directly — no contact join needed.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					lmdt.email,
					lmdt.is_used,
					lmdt.expires_at,
					lmdt.ip_address,
					lmdt.created_at AS created_date
				FROM {$lmdt_table} lmdt
				WHERE (lmdt.is_used = 1 OR lmdt.expires_at < NOW())
				  AND lmdt.created_at < %s
				  AND lmdt.id > %d
				ORDER BY lmdt.id
				LIMIT %d",
				$ref_date,
				$last_id,
				self::CHUNK_SIZE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$this->write_rows_to_csv(
			$export_file,
			$rows,
			array( 'email', 'is_used', 'expires_at', 'ip_address', 'created_date' ),
			$last_id === 0
		);

		return count( $rows );
	}


	// -------------------------------------------------------------------------
	// Completion, self-chaining, and file lifecycle
	// -------------------------------------------------------------------------

	/**
	 * Finalises the export: transitions state to export_ready, stores file info,
	 * schedules file deletion, and optionally dispatches DeleteJob.
	 *
	 * @since 1.0.0
	 * @param string          $export_file   Absolute path to the completed CSV file.
	 * @param string          $action        'export_then_delete' or 'delete_only'.
	 * @param JobStateManager $state_manager State manager instance.
	 * @return void
	 */
	private function complete_export( string $export_file, string $action, JobStateManager $state_manager ): void {
		$upload_dir      = wp_upload_dir();
		$export_file_url = str_replace(
			trailingslashit( $upload_dir['basedir'] ),
			trailingslashit( $upload_dir['baseurl'] ),
			$export_file
		);

		// Transition to export_ready and store file info.
		$state_manager->transition(
			'export_ready',
			array(
				'export_progress'        => 100,
				'export_file'            => $export_file,
				'export_file_url'        => $export_file_url,
				'export_file_created_at' => current_time( 'mysql' ),
			)
		);

		// Schedule file deletion after 24 hours.
		$delete_at = time() + self::EXPORT_TTL;
		as_schedule_single_action(
			$delete_at,
			self::DELETE_FILE_HOOK,
			array( array( 'export_file' => $export_file ) ),
			DataCleanupScheduler::GROUP
		);

		// Register the file-deletion handler if not already registered.
		if ( ! has_action( self::DELETE_FILE_HOOK ) ) {
			add_action( self::DELETE_FILE_HOOK, array( self::class, 'handle_delete_export_file' ) );
		}

		// Auto-dispatch DeleteJob when workflow is export_then_delete.
		if ( 'export_then_delete' === $action ) {
			$state = $state_manager->get_state();
			$scheduler = new DataCleanupScheduler();
			$scheduler->dispatch_delete(
				array(
					'categories'     => $state['categories'],
					'retention_days' => $state['retention_days'],
					'action'         => $action,
				)
			);
		}
	}

	/**
	 * Action Scheduler callback that deletes the export file after Export_TTL.
	 *
	 * Clears export_file and export_file_url from job state after deletion.
	 *
	 * @since 1.0.0
	 * @param array $args Arguments containing 'export_file' path.
	 * @return void
	 */
	public static function handle_delete_export_file( array $args ): void {
		$export_file = isset( $args['export_file'] ) ? $args['export_file'] : '';

		if ( $export_file && file_exists( $export_file ) ) {
			wp_delete_file( $export_file );
		}

		// Clear file references from job state.
		$state_manager = new JobStateManager();
		$state         = $state_manager->get_state();

		if ( isset( $state['export_file'] ) && $state['export_file'] === $export_file ) {
			$state_manager->clear_export_file();
		}
	}

	// -------------------------------------------------------------------------
	// CSV helper
	// -------------------------------------------------------------------------

	/**
	 * Appends a single blank line to the CSV file as a visual separator between category sections.
	 *
	 * @since 1.0.0
	 * @param string $export_file Absolute path to the CSV file.
	 * @return void
	 */
	private function write_blank_row( string $export_file ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $export_file, 'ab' );
		if ( $handle ) {
			fwrite( $handle, "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}

	/**
	 * Appends rows to the CSV file using fputcsv().
	 *
	 * Writes a header row only when $write_header is true (first chunk of a category).
	 * Does NOT accumulate rows in memory — writes directly to disk.
	 *
	 * @since 1.0.0
	 * @param string $export_file  Absolute path to the CSV file.
	 * @param array  $rows         Array of associative arrays (column => value).
	 * @param array  $columns      Ordered list of column names for the header.
	 * @param bool   $write_header Whether to write the header row before data rows.
	 * @return void
	 */
	private function write_rows_to_csv( string $export_file, array $rows, array $columns, bool $write_header ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $export_file, 'ab' );

		if ( ! $handle ) {
			throw new \RuntimeException( "Failed to open export file for writing: {$export_file}" );
		}

		if ( $write_header ) {
			fputcsv( $handle, $columns ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		}

		foreach ( $rows as $row ) {
			$ordered = array();
			foreach ( $columns as $col ) {
				$ordered[] = isset( $row[ $col ] ) ? $row[ $col ] : '';
			}
			fputcsv( $handle, $ordered ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}
}
