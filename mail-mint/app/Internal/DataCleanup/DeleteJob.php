<?php
/**
 * Class DeleteJob
 *
 * Action Scheduler job that deletes eligible rows from the database in chunks.
 * Enforces safe deletion rules, child-before-parent deletion order, and
 * writes _data_cleaned flags to wp_options before any DELETE executes.
 *
 * @package Mint\MRM\Internal\DataCleanup
 * @since 1.0.0
 */

namespace Mint\MRM\Internal\DataCleanup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles chunked database deletion for the Data Cleanup feature.
 *
 * Each invocation processes one chunk of up to CHUNK_SIZE rows for a single
 * table in the required deletion order (child tables before parent tables).
 * After each chunk the job state is updated and the next chunk is self-chained
 * via as_enqueue_async_action.
 *
 * Safe deletion rules are enforced per-table so that no operationally critical
 * rows (e.g. status = 'processing', 'active', 'abandoned') are ever removed.
 *
 * @since 1.0.0
 */
class DeleteJob {

	/**
	 * Number of rows to delete per chunk.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CHUNK_SIZE = 10000;

	/**
	 * Ordered list of table-step keys that drive the deletion sequence.
	 *
	 * Child tables appear before their parent tables to maintain referential
	 * integrity. Each key maps to a delete_* method on this class.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const TABLE_STEPS = array(
		'form_entry_details',
		'form_submission_meta',
		'form_submissions',
		'broadcast_email_meta',
		'broadcast_emails',
		'automation_log',
		'automation_jobs',
		'abandoned_carts_meta',
		'abandoned_carts',
		'download_tracking',
	);

	/**
	 * Maps each table-step to the category key it belongs to.
	 *
	 * Used to skip steps whose category was not selected by the user.
	 *
	 * @since 1.0.0
	 * @var array<string,string>
	 */
	const STEP_CATEGORY_MAP = array(
		'form_entry_details'   => 'form_submissions',
		'form_submission_meta' => 'form_submissions',
		'form_submissions'     => 'form_submissions',
		'broadcast_email_meta' => 'email_engagement',
		'broadcast_emails'     => 'broadcast_emails',
		'automation_log'       => 'automation_log',
		'automation_jobs'      => 'automation_jobs',
		'abandoned_carts_meta' => 'abandoned_carts',
		'abandoned_carts'      => 'abandoned_carts',
		'download_tracking'    => 'download_tracking',
	);

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
	 *     @type int      $step_index     Index into TABLE_STEPS for the current table.
	 *     @type int      $rows_deleted   Running total of rows deleted so far.
	 * }
	 * @return void
	 */
	public static function handle( array $args ): void {
		$instance = new self();
		$instance->process_chunk( $args );
	}

	/**
	 * Processes one deletion chunk for the current table step.
	 *
	 * Reads job state, validates it is still in 'deleting' status, resolves the
	 * current table step, writes _data_cleaned flags if this is the first chunk
	 * for a category, executes the DELETE, updates progress, and self-chains.
	 *
	 * @since 1.0.0
	 * @param array $args Job arguments (see handle() for shape).
	 * @return void
	 */
	public function process_chunk( array $args ): void {
		$state_manager = new JobStateManager();

		try {
			$state = $state_manager->get_state();

			// Abort if the job was cancelled or failed externally.
			if ( 'deleting' !== $state['status'] ) {
				return;
			}

			$categories     = isset( $args['categories'] ) ? (array) $args['categories'] : (array) $state['categories'];
			$retention_days = isset( $args['retention_days'] ) ? (int) $args['retention_days'] : (int) $state['retention_days'];
			$step_index     = isset( $args['step_index'] ) ? (int) $args['step_index'] : 0;
			$rows_deleted   = isset( $args['rows_deleted'] ) ? (int) $args['rows_deleted'] : (int) $state['rows_deleted'];

			$all_steps = self::TABLE_STEPS;
			$total_steps = count( $all_steps );

			// Advance past steps whose category was not selected.
			while ( $step_index < $total_steps ) {
				$step     = $all_steps[ $step_index ];
				$category = isset( self::STEP_CATEGORY_MAP[ $step ] ) ? self::STEP_CATEGORY_MAP[ $step ] : '';
				if ( in_array( $category, $categories, true ) ) {
					break;
				}
				++$step_index;
			}

			// All steps done — complete the job.
			if ( $step_index >= $total_steps ) {
				$this->complete_deletion( $state_manager );
				return;
			}

			$step     = $all_steps[ $step_index ];
			$category = self::STEP_CATEGORY_MAP[ $step ];
			$ref_date = $this->compute_ref_date( $retention_days );

			// Write _data_cleaned flags before the very first DELETE for this category.
			$flags_written = $this->maybe_write_data_cleaned_flags( $step, $category, $ref_date, $args );
			if ( false === $flags_written ) {
				// Flag write failed — abort this category, record error, and move on.
				$state_manager->update_state(
					array(
						'error' => sprintf(
							/* translators: %s: category key */
							'Failed to write _data_cleaned flags for category: %s. Skipping deletion for this category.',
							$category
						),
					)
				);
				// Skip to the next category by advancing past all steps for this category.
				$step_index = $this->advance_past_category( $step_index, $category, $all_steps );
				$this->enqueue_next_chunk( $categories, $retention_days, $args, $step_index, $rows_deleted );
				return;
			}

			// Execute the deletion chunk.
			$deleted = $this->delete_step_chunk( $step, $ref_date );

			$rows_deleted += $deleted;

			// Update progress in job state.
			$total_rows      = max( 1, (int) $state['total_rows'] );
			$delete_progress = min( 99, (int) round( ( $rows_deleted / $total_rows ) * 100 ) );

			$state_manager->update_state(
				array(
					'rows_deleted'    => $rows_deleted,
					'delete_progress' => $delete_progress,
				)
			);

			// If fewer rows than CHUNK_SIZE were deleted, this step is exhausted.
			if ( $deleted < self::CHUNK_SIZE ) {
				$next_step_index = $step_index + 1;
				// Advance past non-selected categories.
				while ( $next_step_index < $total_steps ) {
					$next_step     = $all_steps[ $next_step_index ];
					$next_category = isset( self::STEP_CATEGORY_MAP[ $next_step ] ) ? self::STEP_CATEGORY_MAP[ $next_step ] : '';
					if ( in_array( $next_category, $categories, true ) ) {
						break;
					}
					++$next_step_index;
				}

				if ( $next_step_index >= $total_steps ) {
					$this->complete_deletion( $state_manager );
					return;
				}

				$this->enqueue_next_chunk( $categories, $retention_days, $args, $next_step_index, $rows_deleted );
			} else {
				// More rows remain in this step — continue same step.
				$this->enqueue_next_chunk( $categories, $retention_days, $args, $step_index, $rows_deleted );
			}
		} catch ( \Exception $e ) {
			$state_manager->transition(
				'failed',
				array( 'error' => $e->getMessage() )
			);
			throw $e;
		}
	}

	// -------------------------------------------------------------------------
	// _data_cleaned flag helpers (Task 6.1)
	// -------------------------------------------------------------------------

	/**
	 * Writes _data_cleaned flags to wp_options for all affected campaign and
	 * automation IDs in the given category, but only on the first chunk of each
	 * table step (i.e. when no prior deletion has occurred for this step).
	 *
	 * Returns true if flags were written successfully (or if no flags are needed),
	 * false if a flag write failed.
	 *
	 * @since 1.0.0
	 * @param string $step     Current table-step key.
	 * @param string $category Category key for this step.
	 * @param string $ref_date Cutoff datetime string.
	 * @param array  $args     Current job args (used to detect first chunk per step).
	 * @return bool True on success or not-applicable, false on write failure.
	 */
	private function maybe_write_data_cleaned_flags( string $step, string $category, string $ref_date, array $args ): bool {
		// Only write flags on the very first chunk of the first step for a category.
		// We detect "first chunk of category" by checking whether the previous step
		// belonged to a different category.
		$all_steps  = self::TABLE_STEPS;
		$step_index = (int) ( isset( $args['step_index'] ) ? $args['step_index'] : 0 );

		// If this is not the first step for this category, flags were already written.
		if ( $step_index > 0 ) {
			$prev_step     = $all_steps[ $step_index - 1 ];
			$prev_category = isset( self::STEP_CATEGORY_MAP[ $prev_step ] ) ? self::STEP_CATEGORY_MAP[ $prev_step ] : '';
			if ( $prev_category === $category ) {
				// Same category as previous step — flags already written.
				return true;
			}
		}

		// Determine which IDs need flags based on category.
		switch ( $category ) {
			case 'broadcast_emails':
			case 'email_engagement':
				return $this->write_campaign_flags( $ref_date );

			case 'automation_log':
			case 'automation_jobs':
				return $this->write_automation_flags( $ref_date, $category );

			default:
				// No flags needed for form_submissions, abandoned_carts, download_tracking.
				return true;
		}
	}

	/**
	 * Queries all campaign IDs affected by the broadcast_emails deletion and
	 * writes _mrm_campaign_{id}_data_cleaned flags to wp_options.
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return bool True if all flags written successfully, false on any failure.
	 */
	private function write_campaign_flags( string $ref_date ): bool {
		global $wpdb;

		$be_table = $wpdb->prefix . 'mint_broadcast_emails';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$campaign_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT campaign_id FROM {$be_table} WHERE status = 'sent' AND scheduled_at < %s AND campaign_id IS NOT NULL",
				$ref_date
			)
		);

		if ( empty( $campaign_ids ) ) {
			return true;
		}

		foreach ( $campaign_ids as $campaign_id ) {
			$campaign_id = (int) $campaign_id;
			if ( $campaign_id <= 0 ) {
				continue;
			}
			$result = update_option( "_mrm_campaign_{$campaign_id}_data_cleaned", true, false );
			// update_option returns false both on failure AND when the value is unchanged.
			// We verify by reading back the option.
			if ( false === $result ) {
				$stored = get_option( "_mrm_campaign_{$campaign_id}_data_cleaned" );
				if ( true !== $stored ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Queries all automation IDs affected by the automation_log or automation_jobs
	 * deletion and writes _mrm_automation_{id}_data_cleaned flags to wp_options.
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @param string $category 'automation_log' or 'automation_jobs'.
	 * @return bool True if all flags written successfully, false on any failure.
	 */
	private function write_automation_flags( string $ref_date, string $category ): bool {
		global $wpdb;

		if ( 'automation_log' === $category ) {
			$table  = $wpdb->prefix . 'mint_automation_log';
			$where  = "status IN ('completed', 'exited') AND created_at < %s AND automation_id IS NOT NULL";
		} else {
			$table  = $wpdb->prefix . 'mint_automation_jobs';
			$where  = "status = 'completed' AND created_at < %s AND automation_id IS NOT NULL";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$automation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT automation_id FROM {$table} WHERE {$where}",
				$ref_date
			)
		);

		if ( empty( $automation_ids ) ) {
			return true;
		}

		foreach ( $automation_ids as $automation_id ) {
			$automation_id = (int) $automation_id;
			if ( $automation_id <= 0 ) {
				continue;
			}
			$result = update_option( "_mrm_automation_{$automation_id}_data_cleaned", true, false );
			if ( false === $result ) {
				$stored = get_option( "_mrm_automation_{$automation_id}_data_cleaned" );
				if ( true !== $stored ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Advances the step index past all steps belonging to the given category.
	 *
	 * Used when a category's flag write fails and we need to skip it entirely.
	 *
	 * @since 1.0.0
	 * @param int      $step_index   Current step index.
	 * @param string   $category     Category to skip.
	 * @param string[] $all_steps    Full TABLE_STEPS array.
	 * @return int The new step index after skipping the category.
	 */
	private function advance_past_category( int $step_index, string $category, array $all_steps ): int {
		$total = count( $all_steps );
		while ( $step_index < $total ) {
			$step     = $all_steps[ $step_index ];
			$cat      = isset( self::STEP_CATEGORY_MAP[ $step ] ) ? self::STEP_CATEGORY_MAP[ $step ] : '';
			if ( $cat !== $category ) {
				break;
			}
			++$step_index;
		}
		return $step_index;
	}

	// -------------------------------------------------------------------------
	// Deletion dispatcher (Tasks 6.2 – 6.8)
	// -------------------------------------------------------------------------

	/**
	 * Dispatches the correct deletion method for the given table step.
	 *
	 * @since 1.0.0
	 * @param string $step     Table-step key from TABLE_STEPS.
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted in this chunk.
	 */
	private function delete_step_chunk( string $step, string $ref_date ): int {
		switch ( $step ) {
			case 'broadcast_emails':
				return $this->delete_broadcast_emails( $ref_date );
			case 'broadcast_email_meta':
				return $this->delete_broadcast_email_meta( $ref_date );
			case 'automation_log':
				return $this->delete_automation_log( $ref_date );
			case 'automation_jobs':
				return $this->delete_automation_jobs( $ref_date );
			case 'form_entry_details':
				return $this->delete_form_entry_details( $ref_date );
			case 'form_submission_meta':
				return $this->delete_form_submission_meta( $ref_date );
			case 'form_submissions':
				return $this->delete_form_submissions( $ref_date );
			case 'abandoned_carts_meta':
				return $this->delete_abandoned_carts_meta( $ref_date );
			case 'abandoned_carts':
				return $this->delete_abandoned_carts( $ref_date );
			case 'download_tracking':
				return $this->delete_download_tracking( $ref_date );
			default:
				return 0;
		}
	}

	/**
	 * Deletes a chunk of mint_broadcast_emails rows.
	 *
	 * Safe filter: status = 'sent' AND scheduled_at < ref_date.
	 * NEVER deletes rows with status IN ('scheduled', 'failed').
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_broadcast_emails( string $ref_date ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mint_broadcast_emails';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'sent' AND scheduled_at < %s LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_broadcast_email_meta rows.
	 *
	 * Safe filter: created_at < ref_date AND mint_email_id IN (eligible broadcast_emails).
	 * Must be deleted BEFORE the parent mint_broadcast_emails rows.
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_broadcast_email_meta( string $ref_date ): int {
		global $wpdb;

		$meta_table = $wpdb->prefix . 'mint_broadcast_email_meta';
		$be_table   = $wpdb->prefix . 'mint_broadcast_emails';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE bem FROM {$meta_table} bem
				INNER JOIN {$be_table} be ON bem.mint_email_id = be.id
				WHERE bem.created_at < %s
				  AND be.status = 'sent'
				  AND be.scheduled_at < %s
				LIMIT %d",
				$ref_date,
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_automation_log rows.
	 *
	 * Safe filter: status IN ('completed', 'exited') AND created_at < ref_date.
	 * NEVER deletes rows with status IN ('processing', 'hold').
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_automation_log( string $ref_date ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mint_automation_log';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('completed', 'exited') AND created_at < %s LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_automation_jobs rows.
	 *
	 * Safe filter: status = 'completed' AND created_at < ref_date.
	 * NEVER deletes rows with status IN ('active', 'processing').
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_automation_jobs( string $ref_date ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mint_automation_jobs';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'completed' AND created_at < %s LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_form_entry_details rows (child of mint_form_submissions).
	 *
	 * Must be deleted BEFORE mint_form_submission_meta and mint_form_submissions.
	 * Filter: submission_id IN (eligible form_submissions with created_at < ref_date).
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_form_entry_details( string $ref_date ): int {
		global $wpdb;

		$details_table     = $wpdb->prefix . 'mint_form_entry_details';
		$submissions_table = $wpdb->prefix . 'mint_form_submissions';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE fed FROM {$details_table} fed
				INNER JOIN {$submissions_table} fs ON fed.submission_id = fs.id
				WHERE fs.created_at < %s
				LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_form_submission_meta rows (child of mint_form_submissions).
	 *
	 * Must be deleted BEFORE mint_form_submissions.
	 * Filter: form_submission_id IN (eligible form_submissions with created_at < ref_date).
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_form_submission_meta( string $ref_date ): int {
		global $wpdb;

		$meta_table        = $wpdb->prefix . 'mint_form_submission_meta';
		$submissions_table = $wpdb->prefix . 'mint_form_submissions';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE fsm FROM {$meta_table} fsm
				INNER JOIN {$submissions_table} fs ON fsm.form_submission_id = fs.id
				WHERE fs.created_at < %s
				LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_form_submissions rows (parent).
	 *
	 * Must be deleted AFTER mint_form_entry_details and mint_form_submission_meta.
	 * Filter: created_at < ref_date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_form_submissions( string $ref_date ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mint_form_submissions';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_abandoned_carts_meta rows (child of mint_abandoned_carts).
	 *
	 * Must be deleted BEFORE mint_abandoned_carts.
	 * Filter: cart_id IN (eligible abandoned_carts with status IN ('recovered','lost') AND created_at < ref_date).
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_abandoned_carts_meta( string $ref_date ): int {
		global $wpdb;

		$meta_table  = $wpdb->prefix . 'mint_abandoned_carts_meta';
		$carts_table = $wpdb->prefix . 'mint_abandoned_carts';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE acm FROM {$meta_table} acm
				INNER JOIN {$carts_table} ac ON acm.cart_id = ac.id
				WHERE ac.status IN ('recovered', 'lost')
				  AND ac.created_at < %s
				LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_abandoned_carts rows (parent).
	 *
	 * Safe filter: status IN ('recovered', 'lost') AND created_at < ref_date.
	 * NEVER deletes rows with status = 'abandoned'.
	 * Must be deleted AFTER mint_abandoned_carts_meta.
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_abandoned_carts( string $ref_date ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mint_abandoned_carts';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN ('recovered', 'lost') AND created_at < %s LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Deletes a chunk of mint_lead_magnet_download_tracking rows.
	 *
	 * Safe filter: (is_used = 1 OR expires_at < NOW()) AND created_at < ref_date.
	 *
	 * @since 1.0.0
	 * @param string $ref_date Cutoff datetime string.
	 * @return int Number of rows deleted.
	 */
	private function delete_download_tracking( string $ref_date ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mint_lead_magnet_download_tracking';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE (is_used = 1 OR expires_at < NOW()) AND created_at < %s LIMIT %d",
				$ref_date,
				self::CHUNK_SIZE
			)
		);

		return $result ? (int) $result : 0;
	}

	// -------------------------------------------------------------------------
	// Chunking, self-chaining, and completion (Tasks 6.9 – 6.10)
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the next deletion chunk via Action Scheduler (self-chaining).
	 *
	 * Passes the updated step_index and rows_deleted so the next invocation
	 * knows where to resume.
	 *
	 * @since 1.0.0
	 * @param string[] $categories     Selected category keys.
	 * @param int      $retention_days Days of data to keep.
	 * @param array    $args           Current job args (for action/other fields).
	 * @param int      $step_index     Step index for the next chunk.
	 * @param int      $rows_deleted   Running total of rows deleted.
	 * @return void
	 */
	private function enqueue_next_chunk( array $categories, int $retention_days, array $args, int $step_index, int $rows_deleted ): void {
		$next_args = array(
			'categories'     => $categories,
			'retention_days' => $retention_days,
			'action'         => isset( $args['action'] ) ? $args['action'] : 'delete_only',
			'step_index'     => $step_index,
			'rows_deleted'   => $rows_deleted,
		);

		as_enqueue_async_action(
			DataCleanupScheduler::DELETE_HOOK,
			array( $next_args ),
			DataCleanupScheduler::GROUP
		);
	}

	/**
	 * Finalises the deletion job by transitioning state to 'done'.
	 *
	 * @since 1.0.0
	 * @param JobStateManager $state_manager State manager instance.
	 * @return void
	 */
	private function complete_deletion( JobStateManager $state_manager ): void {
		$state_manager->transition(
			'done',
			array( 'delete_progress' => 100 )
		);
	}

	/**
	 * Computes the reference date cutoff from retention_days.
	 *
	 * @since 1.0.0
	 * @param int $retention_days Days of data to keep.
	 * @return string MySQL datetime string (UTC).
	 */
	private function compute_ref_date( int $retention_days ): string {
		return date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . " -{$retention_days} days" ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
	}
}
