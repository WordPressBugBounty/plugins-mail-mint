<?php
/**
 * Class JobStateManager
 *
 * Manages the state machine for the Data Cleanup background job.
 * Reads and writes job state to wp_options using a defined state schema.
 *
 * @package Mint\MRM\Internal\DataCleanup
 * @since 1.0.0
 */

namespace Mint\MRM\Internal\DataCleanup;

/**
 * Manages job state for the Data Cleanup feature.
 *
 * Provides atomic read/write operations on the `_mrm_cleanup_job_state` wp_options key,
 * enforces valid state machine transitions, computes progress percentages, and detects
 * stale (timed-out) jobs.
 *
 * @since 1.0.0
 */
class JobStateManager {

	/**
	 * The wp_options key used to store the cleanup job state.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_KEY = '_mrm_cleanup_job_state';

	/**
	 * Staleness timeout in seconds (30 minutes).
	 *
	 * If a job's started_at is older than this value and the status is still
	 * 'exporting' or 'deleting', the job is considered stale and auto-reset to 'failed'.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const STALENESS_TIMEOUT = 1800;

	/**
	 * Valid state machine transitions map.
	 *
	 * Keys are the "from" status; values are arrays of allowed "to" statuses.
	 * Any state can transition to 'failed' or 'idle' (handled separately in is_valid_transition).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $valid_transitions = array(
		'idle'         => array( 'exporting', 'deleting' ),
		'exporting'    => array( 'export_ready', 'failed', 'idle' ),
		'export_ready' => array( 'deleting', 'failed', 'idle' ),
		'deleting'     => array( 'done', 'failed', 'idle' ),
		'done'         => array( 'idle' ),
		'failed'       => array( 'idle' ),
	);

	/**
	 * Returns the default idle state schema.
	 *
	 * @since 1.0.0
	 * @return array The default idle state.
	 */
	public function get_default_state(): array {
		return array(
			'status'                 => 'idle',
			'action'                 => null,
			'categories'             => array(),
			'retention_days'         => 90,
			'total_rows'             => 0,
			'rows_exported'          => 0,
			'rows_deleted'           => 0,
			'export_progress'        => 0,
			'delete_progress'        => 0,
			'export_file'            => null,
			'export_file_url'        => null,
			'export_file_created_at' => null,
			'started_at'             => null,
			'error'                  => null,
		);
	}

	/**
	 * Reads the current job state from wp_options.
	 *
	 * Returns the default idle state if no state has been stored yet.
	 *
	 * @since 1.0.0
	 * @return array The current job state.
	 */
	public function get_state(): array {
		$state = get_option( self::OPTION_KEY, false );

		if ( false === $state || ! is_array( $state ) ) {
			return $this->get_default_state();
		}

		// Merge with defaults to ensure all keys are present (forward-compatibility).
		return array_merge( $this->get_default_state(), $state );
	}

	/**
	 * Writes the given state to wp_options atomically.
	 *
	 * Uses autoload=false to avoid loading this option on every page request.
	 *
	 * @since 1.0.0
	 * @param array $state The full state array to persist.
	 * @return bool True on success, false on failure.
	 */
	public function set_state( array $state ): bool {
		return update_option( self::OPTION_KEY, $state, false );
	}

	/**
	 * Merges the given updates into the current state and saves.
	 *
	 * @since 1.0.0
	 * @param array $updates Associative array of fields to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_state( array $updates ): bool {
		$current = $this->get_state();
		$new     = array_merge( $current, $updates );
		return $this->set_state( $new );
	}

	/**
	 * Resets the job state to idle by deleting the wp_options entry.
	 *
	 * Deleting the option (rather than writing an idle state) keeps wp_options clean.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function reset_to_idle(): bool {
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Checks whether a transition from one status to another is valid.
	 *
	 * Any status can transition to 'failed' or 'idle' (cancel/reset).
	 *
	 * @since 1.0.0
	 * @param string $from The current status.
	 * @param string $to   The desired new status.
	 * @return bool True if the transition is allowed, false otherwise.
	 */
	public function is_valid_transition( string $from, string $to ): bool {
		// Any state can go to 'failed' or 'idle'.
		if ( 'failed' === $to || 'idle' === $to ) {
			return true;
		}

		if ( ! isset( self::$valid_transitions[ $from ] ) ) {
			return false;
		}

		return in_array( $to, self::$valid_transitions[ $from ], true );
	}

	/**
	 * Performs a validated state transition.
	 *
	 * Reads the current state, validates the transition, merges any extra fields,
	 * and persists the updated state.
	 *
	 * @since 1.0.0
	 * @param string $new_status The target status to transition to.
	 * @param array  $extra      Optional additional fields to merge into the state.
	 * @return bool True on success, false if the transition is invalid or the write fails.
	 */
	public function transition( string $new_status, array $extra = array() ): bool {
		$current = $this->get_state();
		$from    = isset( $current['status'] ) ? $current['status'] : 'idle';

		if ( ! $this->is_valid_transition( $from, $new_status ) ) {
			return false;
		}

		$updates = array_merge(
			$extra,
			array( 'status' => $new_status )
		);

		return $this->update_state( $updates );
	}

	/**
	 * Checks whether the current job has exceeded the staleness timeout.
	 *
	 * A job is considered stale when its status is 'exporting' or 'deleting'
	 * and its started_at timestamp is more than STALENESS_TIMEOUT seconds ago.
	 *
	 * @since 1.0.0
	 * @return bool True if the job is stale, false otherwise.
	 */
	public function is_stale(): bool {
		$state = $this->get_state();

		if ( ! in_array( $state['status'], array( 'exporting', 'deleting' ), true ) ) {
			return false;
		}

		if ( empty( $state['started_at'] ) ) {
			return false;
		}

		$started_timestamp = strtotime( $state['started_at'] );

		if ( false === $started_timestamp ) {
			return false;
		}

		$elapsed = time() - $started_timestamp;

		return $elapsed > self::STALENESS_TIMEOUT;
	}

	/**
	 * Checks for staleness and transitions to 'failed' if detected.
	 *
	 * If the current job is stale, sets status to 'failed' with a timeout error message.
	 *
	 * @since 1.0.0
	 * @return bool True if staleness was detected and handled, false otherwise.
	 */
	public function check_and_handle_staleness(): bool {
		if ( ! $this->is_stale() ) {
			return false;
		}

		$this->transition(
			'failed',
			array(
				'error' => 'Job timed out after 30 minutes. The server may have encountered an error.',
			)
		);

		return true;
	}

	/**
	 * Clears the export file path and URL from the stored job state.
	 *
	 * Called after the export file has been deleted from disk (either by the
	 * scheduled cron event after Export_TTL or by the cancel endpoint).
	 * Sets both `export_file` and `export_file_url` to null in the persisted state.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function clear_export_file(): bool {
		return $this->update_state(
			array(
				'export_file'     => null,
				'export_file_url' => null,
			)
		);
	}

	/**
	 * Calculates the export progress as a percentage (0–100).
	 *
	 * Returns 0 if total_rows is zero to avoid division by zero.
	 *
	 * @since 1.0.0
	 * @return int Export progress percentage.
	 */
	public function calculate_export_progress(): int {
		$state      = $this->get_state();
		$total_rows = (int) $state['total_rows'];

		if ( $total_rows <= 0 ) {
			return 0;
		}

		$rows_exported = (int) $state['rows_exported'];
		$progress      = (int) round( ( $rows_exported / $total_rows ) * 100 );

		return min( 100, max( 0, $progress ) );
	}

	/**
	 * Calculates the delete progress as a percentage (0–100).
	 *
	 * Returns 0 if total_rows is zero to avoid division by zero.
	 *
	 * @since 1.0.0
	 * @return int Delete progress percentage.
	 */
	public function calculate_delete_progress(): int {
		$state      = $this->get_state();
		$total_rows = (int) $state['total_rows'];

		if ( $total_rows <= 0 ) {
			return 0;
		}

		$rows_deleted = (int) $state['rows_deleted'];
		$progress     = (int) round( ( $rows_deleted / $total_rows ) * 100 );

		return min( 100, max( 0, $progress ) );
	}
}
