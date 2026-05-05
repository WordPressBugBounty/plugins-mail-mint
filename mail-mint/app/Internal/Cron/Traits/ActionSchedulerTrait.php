<?php
/**
 * Reusable Action Scheduler behavioral patterns.
 *
 * Provides common AS operations (schedule, unschedule, dedup, group naming)
 * that can be consumed by any background process class — campaigns,
 * automations, or future modules.
 *
 * @package Mint\MRM\Internal\Cron\Traits
 * @since 1.20.0
 */

namespace Mint\MRM\Internal\Cron\Traits;

use MRM\Common\MrmCommon;

/**
 * Trait ActionSchedulerTrait
 *
 * @since 1.20.0
 */
trait ActionSchedulerTrait {

	/**
	 * Schedule an async (delay=0) or delayed single action with dedup guard.
	 *
	 * Uses as_enqueue_async_action() when delay is 0, as_schedule_single_action()
	 * when delay > 0. Checks as_has_scheduled_action() to prevent duplicates.
	 *
	 * @param string $hook          The hook name to schedule.
	 * @param array  $args          Arguments to pass to the hook callback.
	 * @param string $group         Action Scheduler group slug.
	 * @param int    $delay_seconds Delay in seconds. 0 = async (immediate). Default 0.
	 *
	 * @return bool True if a new action was scheduled, false otherwise.
	 *
	 * @since 1.20.0
	 */
	protected function scheduleAction( string $hook, array $args, string $group, int $delay_seconds = 0 ): bool {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		if ( as_has_scheduled_action( $hook, $args, $group ) ) {
			return false;
		}

		if ( $delay_seconds > 0 ) {
			if ( ! function_exists( 'as_schedule_single_action' ) ) {
				return false;
			}
			as_schedule_single_action( time() + $delay_seconds, $hook, $args, $group );
		} else {
			as_enqueue_async_action( $hook, $args, $group );
		}

		return true;
	}

	/**
	 * Remove actions for a given group.
	 *
	 * Delegates to MrmCommon::delete_as_actions(). When $status is empty,
	 * all actions in the group are removed. When $status is provided
	 * (e.g. 'complete'), only actions with that status are removed.
	 *
	 * @param string $group  The Action Scheduler group slug.
	 * @param string $status Optional. Action status to filter by. Default empty (all).
	 *
	 * @since 1.20.0
	 */
	protected function unscheduleGroup( string $group, string $status = '' ): void {
		MrmCommon::delete_as_actions( $group, $status );
	}

	/**
	 * Check whether a scheduled action already exists.
	 *
	 * Guards against undefined hook constants before calling
	 * as_has_scheduled_action().
	 *
	 * @param string $hook  The hook name (typically a constant name value).
	 * @param array  $args  Arguments to match.
	 * @param string $group Action Scheduler group slug.
	 *
	 * @return bool True if a matching scheduled action exists, false otherwise.
	 *
	 * @since 1.20.0
	 */
	protected function hasScheduledAction( string $hook, array $args, string $group ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		return (bool) as_has_scheduled_action( $hook, $args, $group );
	}

	/**
	 * Build a standardized Action Scheduler group slug.
	 *
	 * Returns the format: mailmint-{prefix}-{entity_id}
	 *
	 * @param string $prefix    Group prefix (e.g. 'campaign-email-sending').
	 * @param int    $entity_id Entity identifier (e.g. campaign ID).
	 *
	 * @return string The formatted group slug.
	 *
	 * @since 1.20.0
	 */
	protected function buildGroupSlug( string $prefix, int $entity_id ): string {
		return 'mailmint-' . $prefix . '-' . $entity_id;
	}
}
