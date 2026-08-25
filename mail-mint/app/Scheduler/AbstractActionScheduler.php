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
	 * Whether a pending or in-progress action exists for a hook.
	 *
	 * $args defaults to null rather than an empty array, and the difference is the whole
	 * behaviour of this method. Action Scheduler treats a non-null $args as an *exact*
	 * match and compares it against the stored payload with
	 * `AND a.args = wp_json_encode( $args )`. An empty array encodes to `[]`, which no
	 * caller here ever schedules, so an `array()` default made every call report "nothing
	 * scheduled" and silently defeated each duplicate guard built on it. Passing null
	 * omits the args clause and matches any payload for the hook, which is what a
	 * "is one of these already queued?" question means.
	 *
	 * @param string     $hook     Action hook name.
	 * @param string     $group_id Action Scheduler group to look in.
	 * @param array|null $args     Exact payload to match, or null to match any payload.
	 * @return bool
	 * @since 1.5.0
	 */
    public function hasScheduledAction( string $hook, string $group_id, ?array $args = null ): bool { //phpcs:ignore
		return as_has_scheduled_action( $hook, $args, $group_id );
	}

	/**
	 * Cancel every pending action in a group.
	 *
	 * Group-wide rather than args-based on purpose. as_unschedule_all_actions() matches
	 * args exactly, and the cart jobs carry a whole payload array, so an exact match is
	 * impractical. Callers that own a group entirely — the abandoned cart schedulers use
	 * one group per cart — can clear it wholesale with no risk of reaching another
	 * caller's work.
	 *
	 * @param string $group_id The Action Scheduler group to clear.
	 * @return void
	 * @since 1.31.1
	 */
    public function cancelByGroup( string $group_id ): void { //phpcs:ignore
		if ( '' === $group_id || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( '', array(), $group_id );
	}

}
