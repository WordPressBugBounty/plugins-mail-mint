<?php
/**
 * Reusable multi-batch processing loop pattern.
 *
 * Provides a generic claim → process → status update → repeat loop
 * within time/memory limits, decoupled from any domain-specific logic.
 * Parameterized by callbacks for fetch, claim, process, and completion.
 *
 * @package Mint\MRM\Internal\Cron\Traits
 * @since 1.20.0
 */

namespace Mint\MRM\Internal\Cron\Traits;

use Mint\App\Internal\Cron\BackgroundProcessHelper;

/**
 * Trait BatchProcessTrait
 *
 * @since 1.20.0
 */
trait BatchProcessTrait {

	/**
	 * Execute a multi-batch processing loop with time and memory guards.
	 *
	 * The loop fetches batches of items, claims them atomically, processes
	 * each item, and repeats until an exit condition is met. On non-complete
	 * exit, the schedule_next callback is invoked to continue processing
	 * in a new Action Scheduler job.
	 *
	 * @param array $config {
	 *     Configuration array with callbacks and limits.
	 *
	 *     @type callable $fetch_batch        Returns the next batch of items (array). Empty = done.
	 *     @type callable $claim_batch        Atomically claims items. Returns bool (true = claimed).
	 *     @type callable $process_item       Processes a single item from the batch.
	 *     @type callable $on_batch_complete  Called after each batch is fully processed.
	 *     @type callable $on_all_complete    Called when fetch_batch returns empty (all done).
	 *     @type callable $schedule_next      Called on non-complete exit to schedule continuation.
	 *     @type int      $max_wall_time      Max seconds for the loop. Default 240.
	 *     @type float    $memory_threshold   Memory fraction (0-1) to trigger exit. Default 0.8.
	 * }
	 *
	 * @return array {
	 *     @type int    $batches_processed Number of batches completed.
	 *     @type int    $items_processed   Total items processed across all batches.
	 *     @type string $exit_reason       One of: complete, time_limit, memory_limit, claim_failed, scheduled_next.
	 * }
	 *
	 * @since 1.20.0
	 */
	protected function runBatchLoop( array $config ): array {
		$fetch_batch       = $config['fetch_batch'];
		$claim_batch       = $config['claim_batch'];
		$process_item      = $config['process_item'];
		$on_batch_complete = $config['on_batch_complete'];
		$on_all_complete   = $config['on_all_complete'];
		$schedule_next     = $config['schedule_next'];

		$max_wall_time    = $config['max_wall_time'] ?? 240;
		$memory_threshold = $config['memory_threshold'] ?? 0.8;

		$start_time        = time();
		$batches_processed = 0;
		$items_processed   = 0;
		$exit_reason       = 'complete';

		while ( true ) {
			// Check time budget before starting a new batch.
			$elapsed = time() - $start_time;
			if ( $elapsed >= $max_wall_time ) {
				$exit_reason = 'time_limit';
				break;
			}

			// Check memory before fetching.
			if ( $this->batchMemoryExceeded( $memory_threshold ) ) {
				$exit_reason = 'memory_limit';
				break;
			}

			// Fetch next batch.
			$batch = $fetch_batch();
			if ( empty( $batch ) ) {
				$on_all_complete();
				$exit_reason = 'complete';
				break;
			}

			// Attempt atomic claim.
			$claimed = $claim_batch( $batch );
			if ( ! $claimed ) {
				// Claim can fail due to race with another worker; reschedule quickly.
				$schedule_next();
				$exit_reason = 'claim_failed';
				break;
			}

			// Process each item in the batch.
			foreach ( $batch as $item ) {
				$process_item( $item );
				++$items_processed;
			}

			++$batches_processed;
			$on_batch_complete();

			// One-batch-per-job: release the runner quickly and let Action Scheduler
			// enforce frequency by scheduling the next job with a delayed timestamp.
			$schedule_next();
			$exit_reason = 'scheduled_next';
			break;
		}

		// Schedule continuation only for guard exits reached before a successful batch.
		if ( in_array( $exit_reason, array( 'time_limit', 'memory_limit' ), true ) ) {
			$schedule_next();
		}

		return array(
			'batches_processed' => $batches_processed,
			'items_processed'   => $items_processed,
			'exit_reason'       => $exit_reason,
		);
	}

	/**
	 * Check if memory usage exceeds the given threshold.
	 *
	 * Uses BackgroundProcessHelper::memory_exceeded() for consistency
	 * with the existing codebase, but also checks against the custom
	 * threshold when it differs from the default 90%.
	 *
	 * @param float $threshold Memory fraction (0-1). Default 0.8.
	 *
	 * @return bool True if memory usage exceeds the threshold.
	 *
	 * @since 1.20.0
	 */
	private function batchMemoryExceeded( float $threshold = 0.8 ): bool {
		// BackgroundProcessHelper uses a fixed 90% threshold internally.
		// For custom thresholds, do a direct check.
		$memory_limit   = BackgroundProcessHelper::get_memory_limit() * $threshold;
		$current_memory = memory_get_usage( true );

		return $current_memory >= $memory_limit;
	}
}
