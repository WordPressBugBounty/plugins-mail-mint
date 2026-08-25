<?php
/**
 * Class CartGroupJanitor
 *
 * Reclaims the Action Scheduler group rows left behind by cart tracking.
 *
 * @package Mint\MRM\Internal\AbandonedCart\Scheduler
 * @since 1.31.1
 */

namespace Mint\MRM\Internal\AbandonedCart\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartGroupJanitor
 *
 * Cart tracking schedules its two status-transition jobs into a group of their own per
 * cart — `mint_abandoned_cart_<id>` — so a settled cart's jobs can be cancelled exactly,
 * without matching on a payload that cannot be reconstructed. That is the right trade for
 * cancellation, but it means every tracked cart mints a row in
 * `wp_actionscheduler_groups`, and Action Scheduler has no collector for that table: its
 * retention pass prunes actions and logs and leaves groups alone. The rows are small, but
 * nothing ever removes them, so a busy store accretes one per cart indefinitely.
 *
 * This sweeps the ones that can no longer refer to anything. It is deliberately a
 * collector rather than a redesign of the group scheme — the per-cart group is load
 * bearing for both the duplicate guard and the cancel path.
 */
class CartGroupJanitor {

	/**
	 * Action Scheduler hook for the sweep.
	 *
	 * @var string
	 * @since 1.31.1
	 */
	const HOOK = 'mailmint_purge_abandoned_cart_groups';

	/**
	 * Action Scheduler group the sweep itself runs in.
	 *
	 * Its own group, not a cart's: putting maintenance in `mint_abandoned_cart_<id>` would
	 * put it in reach of CartModel::cancel_cart_jobs().
	 *
	 * @var string
	 * @since 1.31.1
	 */
	const GROUP = 'mail-mint-cart-maintenance';

	/**
	 * Group rows to reclaim per run.
	 *
	 * Sized so one daily pass outruns any realistic rate of cart creation, which is what
	 * lets this stay a single recurring action with no batch chaining to get wrong.
	 *
	 * @var int
	 * @since 1.31.1
	 */
	const BATCH = 2000;

	/**
	 * CartGroupJanitor constructor.
	 *
	 * @since 1.31.1
	 */
	public function __construct() {
		add_action( self::HOOK, array( $this, 'purge_orphan_groups' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
	}

	/**
	 * Ensure the recurring sweep is scheduled.
	 *
	 * @return void
	 * @since 1.31.1
	 */
	public function maybe_schedule() {
		/*
		 * Storefront requests are the bulk of a shop's traffic and there is no reason to
		 * spend a queue lookup on each one. Admin and cron hits are frequent enough to get
		 * the schedule established, and to restore it if it is ever lost.
		 */
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		/*
		 * Exact args match on purpose. The sweep is scheduled with an empty payload, so
		 * `array()` is the precise thing to look for here — unlike a "has anything been
		 * queued for this hook?" question, where an empty array would match nothing.
		 */
		if ( false !== as_next_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
			return;
		}

		as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), self::GROUP );
	}

	/**
	 * Delete a batch of per-cart group rows that no action refers to any more.
	 *
	 * Orphan-only, and that is what makes it safe: a group is taken solely once Action
	 * Scheduler's own retention pass has removed the last action pointing at it, so this
	 * can never orphan a queued job or a still-readable history entry. Action Scheduler
	 * re-creates a group row on demand, so reclaiming one early would cost a row, not
	 * correctness.
	 *
	 * @return void
	 * @since 1.31.1
	 */
	public function purge_orphan_groups() {
		global $wpdb;

		$groups  = $wpdb->prefix . 'actionscheduler_groups';
		$actions = $wpdb->prefix . 'actionscheduler_actions';

		if ( ! $this->table_exists( $groups ) || ! $this->table_exists( $actions ) ) {
			return;
		}

		// esc_like() matters here: the slug separator is an underscore, which LIKE would
		// otherwise read as a single-character wildcard.
		$prefix = $wpdb->esc_like( MINT_ABANDONED_CART_GROUP . '_' ) . '%';

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT g.group_id
				 FROM {$groups} g
				 WHERE g.slug LIKE %s
				   AND NOT EXISTS (
				       SELECT 1 FROM {$actions} a WHERE a.group_id = g.group_id
				   )
				 LIMIT %d",
				$prefix,
				self::BATCH
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $ids ) ) {
			return;
		}

		$ids          = array_map( 'absint', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$groups} WHERE group_id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Whether a table exists in the current database.
	 *
	 * @param string $table Fully-prefixed table name.
	 * @return bool
	 * @since 1.31.1
	 */
	private function table_exists( $table ) {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
