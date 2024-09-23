<?php
/**
 * Class Scheduler
 *
 * Represents the CartScheduler
 *
 * @package MailMintPro\Mint\Internal\AbandonedCart
 * @since 1.5.0
 */

namespace Mint\MRM\Scheduler;

/**
 * Abstract class representing an action scheduler in the Mint\MRM\Scheduler namespace.
 */
abstract class  AbstractActionScheduler {

	/**
	 * Private constant representing the group ID for the abandoned cart functionality.
	 *
	 * This constant is assigned the value of the `MINT_ABANDONED_CART_GROUP` constant,
	 * which represents the group name for the abandoned cart functionality in the Mint system.
	 * It is used internally within the class or scope to reference the abandoned cart group ID.
	 *
	 * @since 1.5.0
	 */
	private const GROUPID = '';



	/**
	 * Enqueue Scheduler
	 *
	 * @param string $hook Get Hook.
	 * @param string $group_id Pass Argument.
	 * @param array  $args Pass Argument.
	 * @return int
	 * @since 1.5.0
	 */
	public function enqueue( string $hook, string $group_id, array $args = array() ): int {
		return as_enqueue_async_action( $hook, $args, $group_id );
	}

	/**
	 * Enqueue Scheduler
	 *
	 * @param int    $timestamp Set Timestamp.
	 * @param string $hook Get Hook.
	 * @param string $group_id Pass Argument.
	 * @param array  $args Pass Argument.
	 * @return int
	 * @since 1.5.0
	 */
	public function schedule( int $timestamp, string $hook, string $group_id, array $args = array() ): int {
		return as_schedule_single_action( $timestamp, $hook, $args, $group_id );
	}

	/**
	 * Enqueue Scheduler.
	 *
	 * @param string $hook Get Hook.
	 * @param string $group_id Pass Argument.
	 * @param array  $args Pass Argument.
	 * @return bool
	 * @since 1.5.0
	 */
    public function hasScheduledAction( string $hook, string $group_id, array $args = array() ): bool { //phpcs:ignore
		return as_has_scheduled_action( $hook, $args, $group_id );
	}

}
