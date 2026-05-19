<?php
/**
 * Class DataCleanupScheduler
 *
 * Dispatches and cancels Action Scheduler jobs for the Data Cleanup feature.
 * Extends AbstractActionScheduler and uses the `mailmint_data_cleanup` group.
 *
 * @package Mint\MRM\Internal\DataCleanup
 * @since 1.0.0
 */

namespace Mint\MRM\Internal\DataCleanup;

use Mint\MRM\Scheduler\AbstractActionScheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages Action Scheduler dispatch and cancellation for Data Cleanup jobs.
 *
 * Provides typed helper methods for enqueueing and cancelling the export and
 * delete background jobs, keeping all scheduling concerns in one place.
 *
 * @since 1.0.0
 */
class DataCleanupScheduler extends AbstractActionScheduler {

	/**
	 * Action Scheduler group for all data cleanup jobs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const GROUP = 'mailmint_data_cleanup';

	/**
	 * Action Scheduler hook for the export job.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const EXPORT_HOOK = 'mailmint_data_cleanup_export';

	/**
	 * Action Scheduler hook for the delete job.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DELETE_HOOK = 'mailmint_data_cleanup_delete';

	/**
	 * Registers the action hooks that Action Scheduler will fire.
	 *
	 * Call this method during plugin initialisation (e.g. from App::init()).
	 * ExportJob and DeleteJob are implemented in tasks 5 and 6 respectively.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::EXPORT_HOOK, array( ExportJob::class, 'handle' ) );
		add_action( self::DELETE_HOOK, array( DeleteJob::class, 'handle' ) ); // phpcs:ignore -- DeleteJob defined in task 6
		add_action( ExportJob::DELETE_FILE_HOOK, array( ExportJob::class, 'handle_delete_export_file' ) );
	}

	/**
	 * Enqueues an async export job with the given arguments.
	 *
	 * Wraps $args in an outer array so Action Scheduler passes the entire
	 * associative array as a single parameter to ExportJob::handle().
	 *
	 * @since 1.0.0
	 * @param array $args Arguments forwarded to ExportJob::handle().
	 * @return int The Action Scheduler action ID.
	 */
	public function dispatch_export( array $args = array() ): int {
		return $this->enqueue( self::EXPORT_HOOK, self::GROUP, array( $args ) );
	}

	/**
	 * Enqueues an async delete job with the given arguments.
	 *
	 * Wraps $args in an outer array so Action Scheduler passes the entire
	 * associative array as a single parameter to DeleteJob::handle().
	 *
	 * @since 1.0.0
	 * @param array $args Arguments forwarded to DeleteJob::handle().
	 * @return int The Action Scheduler action ID.
	 */
	public function dispatch_delete( array $args = array() ): int {
		return $this->enqueue( self::DELETE_HOOK, self::GROUP, array( $args ) );
	}

	/**
	 * Schedules a single future action (used for file-deletion cron events).
	 *
	 * @since 1.0.0
	 * @param int    $timestamp Unix timestamp when the action should fire.
	 * @param string $hook      The action hook to fire.
	 * @param array  $args      Arguments to pass to the hook callback.
	 * @return int The Action Scheduler action ID.
	 */
	public function schedule_single( int $timestamp, string $hook, array $args = array() ): int {
		return $this->schedule( $timestamp, $hook, self::GROUP, $args );
	}

	/**
	 * Cancels all pending export jobs in the data cleanup group.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cancel_export_jobs(): void {
		as_unschedule_all_actions( self::EXPORT_HOOK, array(), self::GROUP );
	}

	/**
	 * Cancels all pending delete jobs in the data cleanup group.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cancel_delete_jobs(): void {
		as_unschedule_all_actions( self::DELETE_HOOK, array(), self::GROUP );
	}

	/**
	 * Cancels all pending jobs (both export and delete) in the data cleanup group.
	 *
	 * Used by the cancel endpoint to stop any in-flight or queued actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cancel_all_jobs(): void {
		$this->cancel_export_jobs();
		$this->cancel_delete_jobs();
	}

	/**
	 * Checks whether an export job is already scheduled or running.
	 *
	 * @since 1.0.0
	 * @return bool True if a pending export action exists.
	 */
	public function has_export_job(): bool {
		return $this->hasScheduledAction( self::EXPORT_HOOK, self::GROUP );
	}

	/**
	 * Checks whether a delete job is already scheduled or running.
	 *
	 * @since 1.0.0
	 * @return bool True if a pending delete action exists.
	 */
	public function has_delete_job(): bool {
		return $this->hasScheduledAction( self::DELETE_HOOK, self::GROUP );
	}
}
