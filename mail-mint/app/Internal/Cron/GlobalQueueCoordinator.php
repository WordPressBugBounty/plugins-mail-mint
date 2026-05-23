<?php
/**
 * Global queue coordinator for campaign dispatch.
 *
 * Applies one shared sending budget across globally due campaign recipients.
 * This class is intentionally introduced before runtime hook activation so the
 * coordinator logic can be developed and verified in isolation.
 *
 * @package Mint\MRM\Internal\Cron
 * @since   1.20.0
 */

namespace Mint\MRM\Internal\Cron;

use MailMint\App\Helper;
use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\Database\Enums\BroadcastStatus;
use Mint\MRM\Database\Repositories\CampaignRepository;
use Mint\MRM\Internal\Campaign\EmailPersonalizer;
use Mint\MRM\Internal\Parser\Parser;
use Mint\MRM\Utilites\Helper\Email;
use Mint\Mrm\Internal\Traits\Singleton;
use MintMailPro\Mint_Pro_Helper;
use MRM\Common\MrmCommon;

/**
 * Class GlobalQueueCoordinator
 *
 * @since 1.20.0
 */
class GlobalQueueCoordinator {

	use Singleton;

	/**
	 * Action Scheduler hook used for future coordinator wiring.
	 *
	 * @since 1.20.0
	 */
	public const HOOK = 'mailmint_global_queue_coordinator';

	/**
	 * Action Scheduler group for coordinator actions.
	 *
	 * @since 1.20.0
	 */
	public const GROUP = 'mailmint-global-queue';

	/**
	 * Transient key that stores the current budget window state.
	 *
	 * @since 1.20.0
	 */
	private const BUDGET_TRANSIENT = 'mailmint_global_queue_budget_state';

	/**
	 * Immediate continuation delay when work remains and budget is not exhausted.
	 *
	 * @since 1.20.0
	 */
	private const CONTINUATION_DELAY = 5;

	/**
	 * Check whether the global coordinator rollout flag is enabled.
	 *
	 * @return bool
	 * @since 1.20.0
	 */
	public function isEnabled(): bool {
		return (bool) apply_filters( 'mailmint_enable_global_queue_coordinator', true );
	}

	/**
	 * Self-healing orphan recovery — runs on init at priority 99.
	 *
	 * Checks if there are scheduled broadcast rows with no active coordinator
	 * job to process them (orphaned by a failed/stuck transition or code swap).
	 * If found, schedules a fresh coordinator job immediately.
	 *
	 * After the first clean run (no orphans found), stores a transient so the
	 * broadcast row query is skipped on subsequent requests until the transient
	 * expires — keeping the per-request cost to a single get_transient() call.
	 *
	 * @since 1.21.3
	 * @return void
	 */
	public function selfHeal(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		// Skip if we recently confirmed no orphans exist — unless a coordinator
		// action has since failed (e.g. PHP timeout), in which case the transient
		// is stale and we must restart regardless.
		if ( get_transient( 'mailmint_coordinator_healthy' ) ) {
			global $wpdb;
			$failed_coordinator = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ag.action_id
					FROM {$wpdb->prefix}actionscheduler_actions ag
					INNER JOIN {$wpdb->prefix}actionscheduler_groups ag_grp ON ag.group_id = ag_grp.group_id
					WHERE ag.hook = %s AND ag_grp.slug = %s AND ag.status = 'failed'
					LIMIT 1",
					self::HOOK,
					self::GROUP
				)
			);
			if ( ! $failed_coordinator ) {
				return;
			}
			delete_transient( 'mailmint_coordinator_healthy' );
		}

		global $wpdb;

		// Check our own broadcast table for any active or pending campaign emails.
		// 'sending' = claimed mid-batch, 'scheduled' = waiting to be picked up.
		$active_work = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$wpdb->prefix}mint_broadcast_emails
				WHERE status IN (%s, %s)
				AND campaign_id > 0",
				'scheduled',
				'sending'
			)
		);

		if ( ! $active_work ) {
			// Nothing to process — cache healthy state for 60s.
			set_transient( 'mailmint_coordinator_healthy', '1', 60 );
			return;
		}

		// There is work to do — check if a coordinator job is already on it.
		$active_coordinator = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ag.action_id
				FROM {$wpdb->prefix}actionscheduler_actions ag
				INNER JOIN {$wpdb->prefix}actionscheduler_groups ag_grp ON ag.group_id = ag_grp.group_id
				WHERE ag.hook = %s AND ag_grp.slug = %s AND ag.status IN ('pending', 'in-progress')
				LIMIT 1",
				self::HOOK,
				self::GROUP
			)
		);

		if ( $active_coordinator ) {
			set_transient( 'mailmint_coordinator_healthy', '1', 60 );
			return;
		}

		// Orphaned work — clear failed/stuck coordinator actions and reschedule.
		$coordinator_group_id = MrmCommon::get_as_group_id( self::GROUP );
		if ( $coordinator_group_id ) {
			$wpdb->query( // phpcs:ignore
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}actionscheduler_actions
					WHERE group_id = %d AND status IN ('failed', 'in-progress')",
					$coordinator_group_id
				)
			);
		}

		$this->schedule();
	}

	/**
	 * Schedule a coordinator action if one is not already pending.
	 *
	 * @param int $timestamp Optional scheduled timestamp. Immediate when <= current time.
	 * @return bool True when scheduled or already pending.
	 * @since 1.20.0
	 */
	public function schedule( int $timestamp = 0 ): bool {
		if ( ! $this->isEnabled() || ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		// Check for a PENDING or IN-PROGRESS action only — as_has_scheduled_action()
		// matches all statuses including 'failed', which must not block re-scheduling.
		global $wpdb;
		$active_action = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ag.action_id FROM {$wpdb->prefix}actionscheduler_actions ag
				INNER JOIN {$wpdb->prefix}actionscheduler_groups ag_grp ON ag.group_id = ag_grp.group_id
				WHERE ag.hook = %s AND ag_grp.slug = %s AND ag.status IN ('pending', 'in-progress')
				LIMIT 1",
				self::HOOK,
				self::GROUP
			)
		);

		if ( $active_action ) {
			return true;
		}

		if ( $timestamp > time() && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $timestamp, self::HOOK, array(), self::GROUP );
			return true;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), self::GROUP );
			return true;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 1, self::HOOK, array(), self::GROUP );
			return true;
		}

		return false;
	}

	/**
	 * Execute one coordinator run.
	 *
	 * Reads the shared frequency budget, processes globally due campaign
	 * recipients in deterministic order, and schedules continuation only when
	 * additional due work remains.
	 *
	 * @return array{
	 *     sent_count:int,
	 *     remaining_budget:int,
	 *     due_candidates:int,
	 *     continuation_scheduled:bool,
	 *     budget_exhausted:bool,
	 *     window_start:int,
	 *     window_seconds:int
	 * }
	 * @since 1.20.0
	 */
	public function run(): array {
		if ( ! $this->isEnabled() ) {
			return array(
				'sent_count'             => 0,
				'remaining_budget'       => 0,
				'due_candidates'         => 0,
				'continuation_scheduled' => false,
				'budget_exhausted'       => false,
				'window_start'           => 0,
				'window_seconds'         => 0,
			);
		}

		$repository = new CampaignRepository();
		$config     = $this->getFrequencyConfig();
		$now        = time();
		$state      = $this->getBudgetState( $config, $now );
		$remaining  = max( 0, $state['emails'] - $state['sent_count'] );

		if ( $remaining <= 0 ) {
			$due_candidates = $repository->countGlobalDueDispatchCandidates( $now );
			$scheduled      = $this->scheduleContinuationIfNeeded( $repository, $config, $now, $due_candidates, true );

			return array(
				'sent_count'             => 0,
				'remaining_budget'       => 0,
				'due_candidates'         => $due_candidates,
				'continuation_scheduled' => $scheduled,
				'budget_exhausted'       => true,
				'window_start'           => $state['window_start'],
				'window_seconds'         => $config['window_seconds'],
			);
		}

		$candidates      = $repository->getGlobalDueDispatchCandidates( $remaining, $now );
		$gate_decisions  = $repository->prefetchSequenceParentGateDecisions( $candidates, $now );
		$sent_count      = 0;
		$next_retry_at   = 0;

		$contact_ids    = array_filter( array_map( 'intval', array_column( $candidates, 'contact_id' ) ) );
		$contacts_cache = ! empty( $contact_ids ) ? ContactModel::getMany( $contact_ids ) : array();

		$email_cache = array();
		foreach ( $candidates as $candidate ) {
			$c_id = ! empty( $candidate['campaign_id'] ) ? (int) $candidate['campaign_id'] : 0;
			$e_id = ! empty( $candidate['email_id'] ) ? (int) $candidate['email_id'] : 0;
			if ( ! $c_id || ! $e_id ) {
				continue;
			}
			$cache_key = $c_id . ':' . $e_id;
			if ( isset( $email_cache[ $cache_key ] ) ) {
				continue;
			}
			$transient_key = 'mailmint_email_cache_' . $c_id . '_' . $e_id;
			$cached        = get_transient( $transient_key );
			if ( empty( $cached ) || ! is_array( $cached ) ) {
				$cached = $this->buildEmailCache( $c_id, $e_id );
				set_transient( $transient_key, $cached, HOUR_IN_SECONDS );
			}
			$email_cache[ $cache_key ] = $cached;
		}

		foreach ( $candidates as $candidate ) {
			$candidate_id = ! empty( $candidate['id'] ) ? (int) $candidate['id'] : 0;
			$decision     = $candidate_id > 0 ? ( $gate_decisions[ $candidate_id ] ?? array() ) : array();
			$eligible     = ! empty( $decision ) ? (bool) $decision['eligible'] : true;
			$eligible     = apply_filters( 'mailmint_global_queue_candidate_eligible', $eligible, $candidate, $now );

			if ( ! $eligible ) {
				$retry_at = ! empty( $decision['retry_at'] ) ? (int) $decision['retry_at'] : 0;

				if ( $retry_at > 0 && ( 0 === $next_retry_at || $retry_at < $next_retry_at ) ) {
					$next_retry_at = $retry_at;
				}

				if ( ! empty( $decision['terminal_status'] ) && $candidate_id && $repository->claimGlobalDispatchCandidate( $candidate_id ) ) {
					$repository->updateGlobalDispatchCandidateStatus( $candidate_id, (string) $decision['terminal_status'] );
					$this->logSequenceGateDecision( $candidate, $decision );
					$this->maybeMarkCampaignEmailComplete( $repository, $candidate );
				}

				continue;
			}

			if ( ! $candidate_id || ! $repository->claimGlobalDispatchCandidate( $candidate_id ) ) {
				continue;
			}

			$status = $this->dispatchCandidate( $candidate, $contacts_cache, $email_cache ) ? BroadcastStatus::SENT : BroadcastStatus::FAILED;
			$repository->updateGlobalDispatchCandidateStatus( $candidate_id, $status );

			if ( BroadcastStatus::SENT === $status ) {
				++$sent_count;
			}

			$this->maybeMarkCampaignEmailComplete( $repository, $candidate );

			if ( $sent_count >= $remaining ) {
				break;
			}
		}

		if ( $sent_count > 0 ) {
			$this->persistBudgetState( $state['window_start'], $state['sent_count'] + $sent_count, $config['window_seconds'], $now );
		}

		$due_candidates = $repository->countGlobalDueDispatchCandidates( $now );
		$scheduled      = $this->scheduleContinuationIfNeeded( $repository, $config, $now, $due_candidates, $sent_count >= $remaining, 0 === $sent_count ? $next_retry_at : 0 );

		$result = array(
			'sent_count'             => $sent_count,
			'remaining_budget'       => max( 0, $remaining - $sent_count ),
			'due_candidates'         => $due_candidates,
			'continuation_scheduled' => $scheduled,
			'budget_exhausted'       => $sent_count >= $remaining,
			'window_start'           => $state['window_start'],
			'window_seconds'         => $config['window_seconds'],
		);

		/**
		 * Fires after the coordinator completes a run with dispatch metrics.
		 *
		 * @since 1.21.3
		 *
		 * @param array $result Coordinator run result with sent_count, budget, etc.
		 */
		do_action( 'mailmint_global_queue_coordinator_run_complete', $result );

		return $result;
	}

	/**
	 * Load the current global frequency configuration.
	 *
	 * @return array{emails:int, minutes:int, window_seconds:int}
	 * @since 1.20.0
	 */
	public function getFrequencyConfig(): array {
		$frequency = Helper::get_email_frequency_setting();

		$emails         = ! empty( $frequency['emails'] ) ? (int) $frequency['emails'] : 25;
		$minutes        = ! empty( $frequency['time'] ) ? (int) $frequency['time'] : 5;
		$window_seconds = max( 60, $minutes * MINUTE_IN_SECONDS );

		return array(
			'emails'         => max( 1, $emails ),
			'minutes'        => max( 1, $minutes ),
			'window_seconds' => $window_seconds,
		);
	}

	/**
	 * Dispatch a single globally selected candidate.
	 *
	 * Reuses the campaign email personalization/mailer path so send behavior
	 * stays aligned with the existing campaign sender.
	 *
	 * @param array $candidate Globally due broadcast row.
	 *
	 * @return bool True when the mailer reports success.
	 * @since 1.20.0
	 */
	private function dispatchCandidate( array $candidate, array $contacts_cache = array(), array $email_cache = array() ): bool {
		$campaign_id      = ! empty( $candidate['campaign_id'] ) ? (int) $candidate['campaign_id'] : 0;
		$email_id         = ! empty( $candidate['email_id'] ) ? (int) $candidate['email_id'] : 0;
		$recipient_email  = ! empty( $candidate['email_address'] ) ? sanitize_email( $candidate['email_address'] ) : '';
		$contact_id       = ! empty( $candidate['contact_id'] ) ? (int) $candidate['contact_id'] : 0;
		$email_hash       = ! empty( $candidate['email_hash'] ) ? $candidate['email_hash'] : '';
		$existing_headers = ! empty( $candidate['email_headers'] ) ? json_decode( $candidate['email_headers'], true ) : array();

		if ( ! $campaign_id || ! $email_id || ! $contact_id || '' === $recipient_email ) {
			return false;
		}

		if ( ! is_array( $existing_headers ) ) {
			$existing_headers = array();
		}

		// Use pre-built email cache from run(); fall back to individual build if missing.
		$cache_key = $campaign_id . ':' . $email_id;
		$cached    = $email_cache[ $cache_key ] ?? $this->buildEmailCache( $campaign_id, $email_id );
		if ( empty( $cached ) ) {
			return false;
		}

		// Fetch email body fresh from DB — not stored in cache to avoid bloating
		// the wp_options transient with large base64-encoded HTML bodies.
		$email_body_raw = $this->getEmailBody( $campaign_id, $email_id, $cached['editor_type'] ?? 'advanced-builder' );

		// Use batch-loaded contact cache from run(); fall back to individual load if missing.
		$contact = $contacts_cache[ $contact_id ] ?? ContactModel::get( $contact_id );
		if ( empty( $contact ) || ! is_array( $contact ) ) {
			return false;
		}

		if ( isset( $contact['meta_fields'] ) && is_array( $contact['meta_fields'] ) ) {
			$contact = array_merge( $contact, $contact['meta_fields'] );
			unset( $contact['meta_fields'] );
		}

		$personalizer = new EmailPersonalizer();
		$preview_text = '';
		$headers      = $personalizer->buildHeaders( $preview_text, $email_hash, $existing_headers );

		$email_subject = Parser::parse( $cached['email_subject'], $contact );
		$preview_text  = Parser::parse( $cached['email_preview_text'], $contact );
		$email_body    = Parser::parse( $email_body_raw, $contact );
		$email_body    = Helper::replace_url( $email_body, $email_hash );

		$headers = array_filter(
			$headers,
			function ( $header ) {
				return 0 !== strpos( $header, 'X-PreHeader: ' );
			}
		);
		$headers[] = 'X-PreHeader: ' . $preview_text;

		$email_body = $personalizer->personalizeBody(
			$email_body,
			$email_hash,
			$preview_text,
			$cached['editor_type'],
			$cached['watermark']
		);
		$email_body = $personalizer->applyProProcessing( $email_body, $recipient_email );

		return (bool) MM()->mailer->send( $recipient_email, $email_subject, $email_body, $headers );
	}

	/**
	 * Build cached email attributes for a campaign email step.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id    Campaign email step ID.
	 *
	 * @return array<string,string>
	 * @since 1.20.0
	 */
	private function buildEmailCache( int $campaign_id, int $email_id ): array {
		$repository       = new CampaignRepository();
		$email_attributes = $repository->getEmailAttributes( $campaign_id, $email_id );

		$email_subject      = ! empty( $email_attributes['email_subject'] ) ? $email_attributes['email_subject'] : '';
		$email_preview_text = ! empty( $email_attributes['email_preview_text'] ) ? $email_attributes['email_preview_text'] : '';
		$editor_type        = ! empty( $email_attributes['editor_type'] ) ? $email_attributes['editor_type'] : 'advanced-builder';

		// NOTE: email_body is intentionally excluded from the cache.
		// It can contain large base64-encoded images (26KB+) which would bloat
		// the wp_options transient and slow down every coordinator run.
		// The body is fetched fresh from DB per dispatch via getEmailBody().
		return array(
			'email_subject'      => $email_subject,
			'email_preview_text' => $email_preview_text,
			'editor_type'        => $editor_type,
			'watermark'          => CampaignEmailBuilderModel::get_email_footer_watermark(),
		);
	}

	/**
	 * Fetch and prepare the email body for a campaign email step.
	 *
	 * Not cached — avoids storing large HTML bodies (which may contain base64
	 * images) in the wp_options transient. Called once per dispatchCandidate.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param int    $email_id    Campaign email step ID.
	 * @param string $editor_type Editor type from the cache.
	 *
	 * @return string Prepared email body HTML.
	 * @since 1.21.3
	 */
	private function getEmailBody( int $campaign_id, int $email_id, string $editor_type ): string {
		$repository       = new CampaignRepository();
		$email_attributes = $repository->getEmailAttributes( $campaign_id, $email_id );
		$email_body       = ! empty( $email_attributes['email_body'] ) ? $email_attributes['email_body'] : '';

		if ( MrmCommon::is_mailmint_pro_active() ) {
			$email_body = Mint_Pro_Helper::replace_automatic_latest_content( $email_body );
		}

		if ( 'plain-text-editor' === $editor_type ) {
			$email_body = nl2br( html_entity_decode( $email_body ) );
		}

		return $email_body;
	}

	/**
	 * Mark a campaign email step complete when no pending broadcast rows remain.
	 *
	 * @param CampaignRepository $repository Repository instance.
	 * @param array              $candidate  Candidate row.
	 *
	 * @return void
	 * @since 1.20.0
	 */
	private function maybeMarkCampaignEmailComplete( CampaignRepository $repository, array $candidate ): void {
		$campaign_id = ! empty( $candidate['campaign_id'] ) ? (int) $candidate['campaign_id'] : 0;
		$email_id    = ! empty( $candidate['email_id'] ) ? (int) $candidate['email_id'] : 0;

		if ( ! $campaign_id || ! $email_id ) {
			return;
		}

		if ( ! $repository->hasPendingBroadcastRecipientsForCampaignEmail( $campaign_id, (string) $email_id ) ) {
			do_action( 'mailmint_campaign_email_sent', $campaign_id, $email_id );
		}
	}

	/**
	 * Return the persisted budget state for the current window.
	 *
	 * @param array $config Frequency config.
	 * @param int   $now    Current Unix time.
	 *
	 * @return array{window_start:int, sent_count:int, emails:int}
	 * @since 1.20.0
	 */
	private function getBudgetState( array $config, int $now ): array {
		$window_start = $this->getWindowStart( $now, $config['window_seconds'] );
		$state        = get_transient( self::BUDGET_TRANSIENT );

		if ( ! is_array( $state ) || empty( $state['window_start'] ) || (int) $state['window_start'] !== $window_start ) {
			return array(
				'window_start' => $window_start,
				'sent_count'   => 0,
				'emails'       => $config['emails'],
			);
		}

		return array(
			'window_start' => $window_start,
			'sent_count'   => ! empty( $state['sent_count'] ) ? (int) $state['sent_count'] : 0,
			'emails'       => $config['emails'],
		);
	}

	/**
	 * Persist the budget state until the next frequency window starts.
	 *
	 * @param int $window_start   Current window start.
	 * @param int $sent_count     Sent count inside the current window.
	 * @param int $window_seconds Window size in seconds.
	 * @param int $now            Current Unix time.
	 *
	 * @return void
	 * @since 1.20.0
	 */
	private function persistBudgetState( int $window_start, int $sent_count, int $window_seconds, int $now ): void {
		$ttl = max( 1, ( $window_start + $window_seconds ) - $now + 5 );

		set_transient(
			self::BUDGET_TRANSIENT,
			array(
				'window_start' => $window_start,
				'sent_count'   => max( 0, $sent_count ),
			),
			$ttl
		);
	}

	/**
	 * Schedule the next coordinator run only when due work remains.
	 *
	 * @param CampaignRepository $repository       Repository instance.
	 * @param array              $config           Frequency config.
	 * @param int                $now              Current Unix time.
	 * @param int                $due_candidates   Count of currently due candidates.
	 * @param bool               $budget_exhausted Whether the current window budget is exhausted.
	 *
	 * @return bool True when a continuation action was scheduled or already exists.
	 * @since 1.20.0
	 */
	private function scheduleContinuationIfNeeded( CampaignRepository $repository, array $config, int $now, int $due_candidates, bool $budget_exhausted, int $retry_at_hint = 0 ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}

		// Check for a PENDING action (not completed/failed).
		// as_has_scheduled_action() includes all statuses, so we need to verify.
		global $wpdb;
		$pending_action = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ag.action_id FROM {$wpdb->prefix}actionscheduler_actions ag
				INNER JOIN {$wpdb->prefix}actionscheduler_groups ag_grp ON ag.group_id = ag_grp.group_id
				WHERE ag.hook = %s AND ag_grp.slug = %s AND ag.status = %s
				LIMIT 1",
				self::HOOK,
				self::GROUP,
				'pending'
			)
		);

		if ( $pending_action ) {
			return true;
		}

		if ( $due_candidates <= 0 ) {
			// No rows are due right now. Check if any rows are scheduled in the future
			// (rate-spread scenario). If so, wake up exactly when the first one becomes due.
			$earliest = $repository->getEarliestPendingBroadcastScheduledAt( $now );
			if ( $earliest <= 0 ) {
				return false;
			}
			as_schedule_single_action( $earliest + 1, self::HOOK, array(), self::GROUP );
			return true;
		}

		$timestamp = $budget_exhausted
			? max( $now + 1, $this->getWindowStart( $now, $config['window_seconds'] ) + $config['window_seconds'] )
			: $now + self::CONTINUATION_DELAY;

		if ( $retry_at_hint > $now && ! $budget_exhausted ) {
			$timestamp = max( $now + 1, $retry_at_hint );
		}

		as_schedule_single_action( $timestamp, self::HOOK, array(), self::GROUP );

		return true;
	}

	/**
	 * Emit a structured gate event for sequence child rows.
	 *
	 * @param array $candidate Candidate row.
	 * @param array $decision  Gate decision.
	 * @return void
	 * @since 1.20.0
	 */
	private function logSequenceGateDecision( array $candidate, array $decision ): void {
		do_action(
			'mailmint_sequence_parent_gate_event',
			array(
				'broadcast_id' => ! empty( $candidate['id'] ) ? (int) $candidate['id'] : 0,
				'campaign_id'  => ! empty( $candidate['campaign_id'] ) ? (int) $candidate['campaign_id'] : 0,
				'email_id'     => ! empty( $candidate['email_id'] ) ? (int) $candidate['email_id'] : 0,
				'contact_id'   => ! empty( $candidate['contact_id'] ) ? (int) $candidate['contact_id'] : 0,
				'reason'       => $decision['reason'] ?? '',
				'status'       => $decision['terminal_status'] ?? '',
			)
		);
	}

	/**
	 * Compute the start timestamp of the current frequency window.
	 *
	 * @param int $now            Current Unix time.
	 * @param int $window_seconds Window size in seconds.
	 *
	 * @return int
	 * @since 1.20.0
	 */
	private function getWindowStart( int $now, int $window_seconds ): int {
		return (int) ( floor( $now / $window_seconds ) * $window_seconds );
	}
}
