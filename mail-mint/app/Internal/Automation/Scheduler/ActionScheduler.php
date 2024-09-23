<?php
/**
 * Action scheduler
 *
 * @package MintMail\App\Internal\Automation;
 */

namespace MintMail\App\Internal\Automation;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action scheduler
 *
 * @package MintMail\App\Internal\Automation;
 */
class ActionScheduler {
	private const GROUPID = MINT_AUTOMATION_GROUP;

	/**
	 * Enqueue Scheduler
	 *
	 * @param string $hook Get Hook.
	 * @param array  $args Pass Argument.
	 * @return int
	 */
	public function enqueue( string $hook, array $args = array() ): int {
		return as_enqueue_async_action( $hook, $args, self::GROUPID );
	}

	/**
	 * Enqueue Scheduler
	 *
	 * @param int    $timestamp Set Timestamp.
	 * @param string $hook Get hook.
	 * @param array  $args Get args.
	 * @return int
	 */
	public function schedule( int $timestamp, string $hook, array $args = array() ): int {
		return as_schedule_single_action( $timestamp, $hook, $args, self::GROUPID );
	}

	/**
	 * Enqueue Scheduler.
	 *
	 * @param string $hook Get hook.
	 * @param array  $args Get args.
	 * @return bool
	 */
	public function hasScheduledAction( string $hook, array $args = array() ): bool { //phpcs:ignore
		return as_has_scheduled_action( $hook, $args, self::GROUPID );
	}
}
