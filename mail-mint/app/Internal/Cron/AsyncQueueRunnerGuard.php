<?php
/**
 * Keeps the Action Scheduler async queue runner alive on LiteSpeed.
 *
 * @package Mint\MRM\Internal\Cron
 * @since   1.31.5
 */

namespace Mint\MRM\Internal\Cron;

use Mint\Mrm\Internal\Traits\Singleton;

/**
 * Class AsyncQueueRunnerGuard
 *
 * Action Scheduler starts its async queue runner with a non-blocking loopback
 * request to admin-ajax.php, so the caller disconnects right away. LiteSpeed
 * (without the `noabort` env flag) kills the PHP worker once the client is gone,
 * which leaves automation steps half-run and later marked "in-progress for at
 * least 300 seconds ... failed" — typically the first email after a Delay step.
 *
 * WordPress core's wp-cron.php avoids this by calling ignore_user_abort() and
 * litespeed_finish_request(). This class does the same, scoped to the Action
 * Scheduler async runner request on LiteSpeed only.
 *
 * @since 1.31.5
 */
class AsyncQueueRunnerGuard {

	use Singleton;

	/**
	 * The admin-ajax action Action Scheduler uses for its async queue runner.
	 *
	 * @since 1.31.5
	 */
	const AJAX_ACTION = 'as_async_request_queue_runner';

	/**
	 * Whether the connection was already detached in this request.
	 *
	 * @var bool
	 * @since 1.31.5
	 */
	private $detached = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 * @since 1.31.5
	 */
	public function init() {
		// Priority 0 so the worker is detached before any action in the batch runs.
		add_action( 'action_scheduler_before_process_queue', array( $this, 'maybe_detach' ), 0 );
	}

	/**
	 * Detach the async runner from the client connection on LiteSpeed.
	 *
	 * Fires inside Action Scheduler's queue runner, after the async request's
	 * nonce has been verified.
	 *
	 * @return void
	 * @since 1.31.5
	 */
	public function maybe_detach() {
		if ( $this->detached || ! $this->is_async_queue_runner_request() ) {
			return;
		}

		// Only LiteSpeed's PHP SAPI defines this function; other servers are untouched.
		if ( ! function_exists( 'litespeed_finish_request' ) ) {
			return;
		}

		/**
		 * Filters whether Mail Mint detaches the Action Scheduler async queue runner
		 * from the client connection on LiteSpeed servers.
		 *
		 * @param bool $detach Whether to detach. Default true.
		 * @since 1.31.5
		 */
		if ( ! apply_filters( 'mailmint_detach_async_queue_runner', true ) ) {
			return;
		}

		$this->detached = true;

		ignore_user_abort( true );
		litespeed_finish_request();
	}

	/**
	 * Whether the current request is Action Scheduler's async queue runner.
	 *
	 * @return bool
	 * @since 1.31.5
	 */
	private function is_async_queue_runner_request() {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Action Scheduler verified its nonce before this hook fires; this only reads the action name.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return self::AJAX_ACTION === $action;
	}
}
