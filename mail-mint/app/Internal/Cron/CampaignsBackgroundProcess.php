<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Cron
 */

namespace Mint\MRM\Internal\Cron;

use MailMint\App\Helper;
use Mint\App\Internal\Cron\BackgroundProcessHelper;
use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\Database\Enums\BroadcastStatus;
use Mint\MRM\Database\Enums\CampaignType;
use Mint\MRM\Database\Repositories\CampaignRepository;
use Mint\MRM\Internal\Campaign\EmailPersonalizer;
use Mint\MRM\Internal\Cron\GlobalQueueCoordinator;
use Mint\MRM\Internal\Cron\Traits\ActionSchedulerTrait;
use Mint\MRM\Internal\Cron\Traits\BatchProcessTrait;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Admin\API\Controllers\CampaignController;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Tables\EmailSchema;
use Mint\MRM\Internal\Parser\Parser;
use Mint\MRM\Utilites\Helper\Email;
use MintMailPro\Mint_Pro_Helper;
use MRM\Common\MrmCommon;

/**
 * [Manage plugin's Cron functionalities]
 *
 * @desc Manage plugin's assets
 * @package /app/Internal/Cron
 * @since 1.0.0
 */
class CampaignsBackgroundProcess {

	use Singleton;
	use ActionSchedulerTrait;
	use BatchProcessTrait;

	/**
	 * Initialize constructor-level hooks.
	 *
	 * @return void
	 * @since 1.20.0
	 */
	private function __construct() {
		add_action( 'action_scheduler_completed_action', array( $this, 'delete_completed_mailmint_action' ) );
	}

	/**
	 * Initialize cron functionalities
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		if ( defined( 'MAILMINT_SCHEDULE_EMAILS' ) ) {
			add_action( MAILMINT_SCHEDULE_EMAILS, array( $this, 'process_campaign_emails_scheduling' ) );
		}
		if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) ) {
			add_action( MAILMINT_SEND_SCHEDULED_EMAILS, array( $this, 'send_recipient_emails' ) );
		}
		add_action( GlobalQueueCoordinator::HOOK, array( GlobalQueueCoordinator::get_instance(), 'run' ) );
		add_action( 'init', array( GlobalQueueCoordinator::get_instance(), 'selfHeal' ), 99 );

		add_action( 'mailmint_campaign_emails_scheduling_completed', array( $this, 'delete_actions' ) );
		add_action( 'mailmint_single_email_scheduling_processed', array( $this, 'schedule_async_send_email_action' ), 10, 2 );
		add_action( 'mailmint_batch_email_sent', array( $this, 'delete_actions' ) );
		add_action( 'mailmint_campaign_email_sent', array( $this, 'update_campaign_status' ), 10, 2 );
		add_action( 'mailmint_after_campaign_start', array( $this, 'activate_scheduled_campaign' ), 10, 2 );
	}

	/**
	 * Configure campaign emails and insert
	 * the recipient emails to mint_broadcast_emails table
	 *
	 * @param array $args Arguments for scheduling emails.
	 *
	 * @return void
	 * @since 1.0.0
	 * @since 1.14.6 Update get_campaign_recipients_email function to get_campaign_recipients_email.
	 * @since 1.14.6 Remove merge tags parsing from email headers.
	 */
	public function process_campaign_emails_scheduling( $args ) {
		do_action( 'mailmint_after_campaign_start', $args );

		$campaign_id = ! empty( $args[ 'campaign_id' ] ) ? $args[ 'campaign_id' ] : null;

		// ── PAUSE CHECK: Stop if campaign is paused ──
		$repo     = new CampaignRepository();
		$campaign = $repo->find( (int) $campaign_id );
		if ( ! empty( $campaign['status'] ) && 'paused' === $campaign['status'] ) {
			// Campaign is paused, do not schedule any more emails.
			return;
		}

		$frequency       = Helper::get_email_frequency_setting();
		$frequency_limit = ! empty( $frequency['emails'] ) ? (int) $frequency['emails'] : 25;
		$frequency_time  = ! empty( $frequency['time'] ) ? (int) $frequency['time'] : 5;
		$slot_duration   = (float) ( $frequency_time * 60 ) / max( 1, $frequency_limit );

		global $wpdb;
		$email_broadcast_table = $wpdb->prefix . EmailSchema::$table_name;
		$email_settings        = get_option( '_mrm_email_settings', Email::default_email_settings() );
		$campaign_id           = ! empty( $args[ 'campaign_id' ] ) ? $args[ 'campaign_id' ] : null;
		$campaign_email        = ! empty( $args[ 'email' ] ) ? $args[ 'email' ] : array();
		$campaign_email_id     = ! empty( $campaign_email[ 'id' ] ) ? $campaign_email[ 'id' ] : null;
		$per_batch             = ! empty( $args[ 'per_batch' ] ) ? $args[ 'per_batch' ] : 200;
		$offset                = ! empty( $args[ 'offset' ] ) ? $args[ 'offset' ] : 0;
		$reply_name            = ! empty( $email_settings[ 'reply_name' ] ) ? $email_settings[ 'reply_name' ] : '';
		$reply_email           = ! empty( $email_settings[ 'reply_email' ] ) ? $email_settings[ 'reply_email' ] : '';
		$sender_email          = ! empty( $email_settings[ 'sender_email' ] ) ? $email_settings[ 'sender_email' ] : '';
		$sender_name           = ! empty( $email_settings[ 'sender_name' ] ) ? $email_settings[ 'sender_name' ] : '';
		$recipients_emails     = CampaignModel::get_campaign_recipients_email( $campaign_id, $offset, $per_batch );

		$start_time      = time();
		$processed_count = 0;

		if ( is_array( $recipients_emails ) && ! empty( $recipients_emails ) ) {
			$sender_name  = ! empty( $campaign_email[ 'sender_name' ] ) ? $campaign_email[ 'sender_name' ] : $sender_name;
			$sender_email = ! empty( $campaign_email[ 'sender_email' ] ) ? $campaign_email[ 'sender_email' ] : $sender_email;
			$reply_name   = ! empty( $campaign_email[ 'reply_name' ] ) ? $campaign_email[ 'reply_name' ] : $reply_name;
			$reply_email  = ! empty( $campaign_email[ 'reply_email' ] ) ? $campaign_email[ 'reply_email' ] : $reply_email;
			$headers      = $this->prepare_email_headers( $sender_name, $sender_email, $reply_name, $reply_email );
			$headers_json = wp_json_encode( $headers );
			$now          = current_time( 'mysql' );

			// ── PR-6 FIX: Batch duplicate check — 1 query instead of N ──
			$existing_emails = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT email_address FROM {$email_broadcast_table} WHERE campaign_id = %d AND email_id = %d",
					$campaign_id,
					$campaign_email_id
				)
			);
			$existing_set    = array_flip( $existing_emails );

			// ── PR-5 FIX: Collect rows for batch INSERT ──
			$rows_to_insert = array();

			// The AS job was already scheduled with the email's delay, so by the time
			// this function runs the delay has been consumed. Starting from time() means
			// broadcast rows are due immediately and the coordinator sends them right away.
			$base_unix = time();

			$next_slot_unix = (float) max( $base_unix, ( new CampaignRepository() )->getSchedulingFrontier( $base_unix ) );

			foreach ( $recipients_emails as $email ) {
				if ( BackgroundProcessHelper::memory_exceeded() || BackgroundProcessHelper::time_exceeded( $start_time, 0.5 ) ) {

					// Flush any pending rows before rescheduling.
					if ( ! empty( $rows_to_insert ) ) {
						$this->batch_insert_broadcast_rows( $wpdb, $email_broadcast_table, $rows_to_insert );
						$rows_to_insert = array();
					}

					// Reschedule the task.
					$new_offset = $offset + $processed_count;
					$args       = array(
						array(
							'campaign_id'     => $campaign_id,
							'campaign_status' => 'active',
							'email'           => $campaign_email,
							'offset'          => $new_offset,
							'per_batch'       => $per_batch,
						),
					);
					$group      = 'mailmint-campaign-schedule-' . $campaign_id;
					as_schedule_single_action( time() + 60, MAILMINT_SCHEDULE_EMAILS, $args, $group );
					return;
				}

				if ( isset( $email['id'], $email['email'] ) && $email['id'] && $email['email'] ) {
					// In-memory dedup — O(1) instead of 1 DB query per recipient.
					if ( ! isset( $existing_set[ $email['email'] ] ) ) {
						$next_slot_unix  += $slot_duration;
						$scheduled_at_row = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $next_slot_unix ) );

						$rows_to_insert[] = $wpdb->prepare(
							'(%d, %d, %d, %s, %s, %s, %s, %s, %s, %s)',
							$campaign_id,
							$campaign_email_id,
							$email['id'],
							$email['email'],
							$headers_json,
							'scheduled',
							'campaign',
							MrmCommon::get_rand_email_hash( $email['email'], $campaign_id ),
							$scheduled_at_row,
							$now
						);
						// Mark as existing to prevent duplicates within the same batch.
						$existing_set[ $email['email'] ] = true;
					}
				}
				$processed_count++;
			}

			// Flush remaining rows.
			if ( ! empty( $rows_to_insert ) ) {
				$this->batch_insert_broadcast_rows( $wpdb, $email_broadcast_table, $rows_to_insert );
			}

			$campaign_email[ 'delay_value' ] = '';
			CampaignModel::schedule_campaign_action( $campaign_id, $campaign_email, 'active', '', ( $offset + $per_batch ) );
		} else {
			do_action( 'mailmint_single_email_scheduling_processed', (int) $campaign_id, (int) $campaign_email_id );

			CampaignModel::update_campaign_email_status( $campaign_id, $campaign_email_id, 'scheduled' );
			$email = CampaignModel::get_first_campaign_email( $campaign_id );

			if ( is_array( $email ) ) {
				$custom_date   = CampaignModel::get_campaign_email_meta( $email['id'], 'schedule_date' );
				$schedule_date = $custom_date ?: '';
				CampaignModel::schedule_campaign_action( $campaign_id, $email, 'active', $schedule_date );
			} else {
				do_action( 'mailmint_campaign_emails_scheduling_completed', 'mailmint-campaign-schedule-' . $campaign_id );
			}
		}

		do_action( 'mailmint_email_batch_scheduling_processed', (int) $campaign_id, (int) $campaign_email_id );
	}

	/**
	 * Insert broadcast email rows in chunks using multi-row INSERT.
	 *
	 * Replaces per-recipient $wpdb->insert() with chunked bulk INSERT
	 * for significantly fewer queries (e.g., 200 rows = 4 queries instead of 200).
	 *
	 * @param \wpdb  $wpdb                 WordPress database instance.
	 * @param string $table                The broadcast emails table name.
	 * @param array  $prepared_row_values  Array of $wpdb->prepare()'d value strings.
	 *
	 * @since 1.15.0
	 */
	private function batch_insert_broadcast_rows( $wpdb, $table, $prepared_row_values ) {
		$columns = 'campaign_id, email_id, contact_id, email_address, email_headers, status, email_type, email_hash, scheduled_at, created_at';

		foreach ( array_chunk( $prepared_row_values, 50 ) as $chunk ) {
			$values_sql = implode( ', ', $chunk );
			$wpdb->query( "INSERT INTO {$table} ({$columns}) VALUES {$values_sql}" ); //phpcs:ignore
		}
	}
	/**
	 * Send emails and handle time/memory limits
	 *
	 * @param array $args Arguments.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function send_recipient_emails( $args = array() ) {
		if ( $this->isGlobalQueueCoordinatorEnabled() ) {
			GlobalQueueCoordinator::get_instance()->schedule();
			return;
		}

		$campaign_id            = ! empty( $args['campaign_id'] ) ? $args['campaign_id'] : 0;
		$email_id               = ! empty( $args['email_id'] ) ? $args['email_id'] : 0;
		$batch                  = ! empty( $args['batch'] ) ? $args['batch'] : 1;

		// ── PAUSE CHECK: Stop if campaign is paused ──
		$repo     = new CampaignRepository();
		$campaign = $repo->find( (int) $campaign_id );
		if ( ! empty( $campaign['status'] ) && 'paused' === $campaign['status'] ) {
			// Campaign is paused, do not send any emails.
			return;
		}

		$frequency              = Helper::get_email_frequency_setting();
		$frequency_type         = ! empty( $frequency['type'] ) ? $frequency['type'] : 'Recommended';
		$frequency_time         = ! empty( $frequency['time'] ) ? (int) $frequency['time'] : 5;
		$frequency_time_seconds = $frequency_time * 60; // Convert minutes to seconds.
		$frequency_limit        = ! empty( $frequency['emails'] ) ? $frequency['emails'] : 25;

		/**
		 * Retrieves the batch limit for sending emails from a filter.
		 *
		 * @return int The batch limit for sending emails.
		 * @since 1.5.2
		 */
		$per_batch = apply_filters( 'mailmint_send_email_batch_limit', 20 );
		if ( 'Manual' === $frequency_type ) {
			$per_batch = $frequency_limit;
		}

		if ( ! $campaign_id ) {
			return;
		}

		// Resolve email attributes: read from transient cache or fetch once from DB.
		$cache_key = 'mailmint_email_cache_' . $campaign_id . '_' . $email_id;
		$cached    = get_transient( $cache_key );
		if ( empty( $cached ) || ! is_array( $cached ) ) {
			$cached = $this->buildEmailCache( $campaign_id, $email_id );
			set_transient( $cache_key, $cached, HOUR_IN_SECONDS );
		}

		$personalizer   = new EmailPersonalizer();
		$batches_in_job = 0;

		// Shared state for per-batch processing (reset each batch via claim callback).
		$claimed_ids      = array();
		$sent_email_ids   = array();
		$failed_email_ids = array();
		$contacts_cache   = array();
		$email_body       = '';
		$next_gate_retry_at = 0;

		$result = $this->runBatchLoop(
			array(
				// Fetch the next batch of scheduled broadcast rows.
				'fetch_batch'        => function () use ( $repo, $campaign_id, $email_id, $per_batch, &$next_gate_retry_at ) {
					$next_gate_retry_at = 0;
					return $this->getDispatchableRecipients( $repo, $campaign_id, $email_id, $per_batch, $next_gate_retry_at );
				},

				// Atomically claim broadcast rows: UPDATE status scheduled → sending.
				 'claim_batch'       => function ( $rows ) use ( &$claimed_ids, &$sent_email_ids, &$failed_email_ids, &$contacts_cache, &$email_body, $campaign_id, $email_id, $cached ) {
					 global $wpdb;

					 // Reset per-batch tracking state.
					 $sent_email_ids   = array();
					 $failed_email_ids = array();

					 $ids = array_column( $rows, 'id' );
					 $ids = array_filter( array_map( 'intval', $ids ) );

					if ( empty( $ids ) ) {
						return false;
					}

					 $claimed_ids  = $ids;
					 $table_name   = $wpdb->prefix . EmailSchema::$table_name;
					 $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
					$params       = array_merge(
						array( BroadcastStatus::SENDING, BroadcastStatus::SCHEDULED ),
						$ids
					);

					 $sql = "UPDATE {$table_name} SET status = %s WHERE status = %s AND id IN ({$placeholders})";
					 $wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore

					 // If we could not claim all rows, another worker may have claimed some.
					if ( (int) $wpdb->rows_affected !== count( $ids ) ) {
						return false;
					}

					 // Batch-load contacts for this batch (2 queries instead of 2 per recipient).
					 $contact_ids    = array_filter( array_map( 'intval', array_column( $rows, 'contact_id' ) ) );
					 $contacts_cache = ! empty( $contact_ids ) ? ContactModel::getMany( $contact_ids ) : array();

					 // Fetch email body fresh from DB per batch — not cached in transient
					 // to avoid storing large base64-encoded HTML in wp_options.
					 $email_body = $this->getEmailBody( $campaign_id, $email_id, $cached['editor_type'] );

					 return true;
				 },

				// Process a single recipient: personalize, send, collect result.
				 'process_item'      => function ( $recipient ) use ( $cached, $personalizer, &$sent_email_ids, &$failed_email_ids, &$contacts_cache, &$email_body ) {
					 $recipient_email    = ! empty( $recipient['email_address'] ) ? sanitize_email( $recipient['email_address'] ) : '';
					 $broadcast_email_id = ! empty( $recipient['id'] ) ? (int) $recipient['id'] : 0;
					 $contact_id         = ! empty( $recipient['contact_id'] ) ? (int) $recipient['contact_id'] : 0;
					 $email_hash         = ! empty( $recipient['email_hash'] ) ? $recipient['email_hash'] : '';
					 $email_headers      = ! empty( $recipient['email_headers'] ) ? json_decode( $recipient['email_headers'] ) : array();

					if ( ! is_array( $email_headers ) ) {
						$email_headers = array();
					}

					 // Build headers with unsubscribe via EmailPersonalizer.
					 $preview_text  = '';
					 $email_headers = $personalizer->buildHeaders( $preview_text, $email_hash, $email_headers );

					 // Get contact from batch-loaded cache, fall back to individual load.
					 $contact = isset( $contacts_cache[ $contact_id ] ) ? $contacts_cache[ $contact_id ] : ContactModel::get( $contact_id );
					if ( isset( $contact['meta_fields'] ) && is_array( $contact['meta_fields'] ) ) {
						$contact = array_merge( $contact, $contact['meta_fields'] );
						unset( $contact['meta_fields'] );
					}

					 // Parse merge tags in subject, preview text, and body.
					 $email_subject      = Parser::parse( $cached['email_subject'], $contact );
					 $preview_text       = Parser::parse( $cached['email_preview_text'], $contact );
					 $parsed_email_body  = Parser::parse( $email_body, $contact );
					 $parsed_email_body  = Helper::replace_url( $parsed_email_body, $email_hash );

					 // Update X-PreHeader with the parsed (per-contact) preview text.
					 // Remove the placeholder added by buildHeaders and re-add with parsed value.
					$email_headers = array_filter(
						$email_headers,
						function ( $h ) {
							return 0 !== strpos( $h, 'X-PreHeader: ' );
						}
					);
					 $email_headers[] = 'X-PreHeader: ' . $preview_text;

					 // Apply post-processing via EmailPersonalizer (tracking pixel, preview text, watermark, RTL).
					$parsed_email_body = $personalizer->personalizeBody(
						$parsed_email_body,
						$email_hash,
						$preview_text,
						$cached['editor_type'],
						$cached['watermark']
					);

					 // Apply Pro-specific post-processing (lead magnet tracking).
					 $parsed_email_body = $personalizer->applyProProcessing( $parsed_email_body, $recipient_email );

					 // Send the email.
					 $email_sent = MM()->mailer->send( $recipient_email, $email_subject, $parsed_email_body, $email_headers );

					if ( $email_sent ) {
						$sent_email_ids[] = $broadcast_email_id;
					} else {
						$failed_email_ids[] = $broadcast_email_id;
					}
				 },

				// After each batch: update statuses, reset unclaimed, fire hook.
				 'on_batch_complete' => function () use ( &$sent_email_ids, &$failed_email_ids, &$claimed_ids, &$batches_in_job, $campaign_id ) {
					 // Batch update statuses for processed emails.
					 self::update_scheduled_emails_status( $sent_email_ids, BroadcastStatus::SENT );
					 self::update_scheduled_emails_status( $failed_email_ids, BroadcastStatus::FAILED );

					 // Reset any claimed-but-unprocessed emails back to 'scheduled'.
					 $processed_ids   = array_merge( $sent_email_ids, $failed_email_ids );
					 $unprocessed_ids = array_diff( $claimed_ids, $processed_ids );
					if ( ! empty( $unprocessed_ids ) ) {
						self::update_scheduled_emails_status( array_values( $unprocessed_ids ), BroadcastStatus::SCHEDULED );
					}

					 ++$batches_in_job;

					 do_action( 'mailmint_batch_email_sent', 'mailmint-campaign-email-sending-' . $campaign_id );
				 },

				// All broadcast rows processed — campaign email step complete.
				'on_all_complete'    => function () use ( $repo, $campaign_id, $email_id, $batch, &$batches_in_job, $frequency_time_seconds, &$next_gate_retry_at ) {
					if ( $repo->hasPendingBroadcastRecipientsForCampaignEmail( (int) $campaign_id, (string) $email_id ) ) {
						$delay_seconds = $frequency_time_seconds;

						if ( $next_gate_retry_at > time() ) {
							$delay_seconds = max( 1, $next_gate_retry_at - time() );
						}

						$repo->scheduleDelayedSendAction(
							(int) $campaign_id,
							$email_id,
							(int) $batch + max( 1, $batches_in_job ),
							$delay_seconds
						);
						return;
					}

					do_action( 'mailmint_campaign_email_sent', $campaign_id, $email_id );
				},

				// Schedule a continuation AS job for remaining rows.
				'schedule_next'      => function () use ( $repo, $campaign_id, $email_id, $batch, &$batches_in_job, $frequency_time_seconds ) {
					$repo->scheduleDelayedSendAction(
						(int) $campaign_id,
						$email_id,
						(int) $batch + $batches_in_job,
						$frequency_time_seconds
					);
				},

				'max_wall_time'      => 240,
				'memory_threshold'   => 0.8,
			)
		);
	}

	/**
	 * Update email status in mint_broadcast_emails table
	 *
	 * @param array  $broadcast_email_ids Email ids that were scheduled in mint_broadcast_emails table.
	 * @param string $status Updated status.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function update_scheduled_emails_status( array $broadcast_email_ids, string $status ) {
		if ( ! empty( $broadcast_email_ids ) ) {
			global $wpdb;
			$email_broadcast_table = $wpdb->prefix . EmailSchema::$table_name;

			$query  = "UPDATE {$email_broadcast_table} ";
			$query .= 'SET `status` = %s, ';
			$query .= '`updated_at` = %s ';
			$query .= 'WHERE `id` IN (%1s)';

			$wpdb->query( $wpdb->prepare( $query, $status, current_time( 'mysql', true ), implode( ', ', $broadcast_email_ids ) ) ); //phpcs:ignore
		}
	}

	/**
	 * Get recipient emails from mint_broadcast_emails table batch wise
	 *
	 * @param int $campaign_id Campaign id.
	 * @param int $email_id Email id.
	 * @param int $per_batch Fetch per batch.
	 *
	 * @return array|object|\stdClass[]|null
	 *
	 * @since 1.0.0
	 */
	private function get_recipient_emails( int $campaign_id, int $email_id, int $per_batch = 20 ) {
		global $wpdb;
		$broadcast_email_table = $wpdb->prefix . EmailSchema::$table_name;

		$sql_query  = "SELECT `id`, `campaign_id`, `email_id`, `email_address`, `email_headers`, `contact_id`, `email_hash`, `scheduled_at` FROM {$broadcast_email_table} ";
		$sql_query .= 'WHERE `status` = %s ';
		$sql_query .= 'AND `email_type` = %s ';
		$sql_query .= 'AND `campaign_id` = %s ';
		$sql_query .= 'AND `email_id` = %s ';
		$sql_query .= 'LIMIT %d';
		$sql_query = $wpdb->prepare( $sql_query, 'scheduled', 'campaign', $campaign_id, $email_id, $per_batch ); //phpcs:ignore

		return $wpdb->get_results( $sql_query, ARRAY_A ); //phpcs:ignore
	}

	/**
	 * Filter the next recipient batch through deterministic sequence parent gates.
	 *
	 * Parent failures are terminal for the child row and are marked failed with a
	 * structured event. Pending parents or unmet relative delays remain scheduled.
	 *
	 * @param CampaignRepository $repository     Repository instance.
	 * @param int                $campaign_id    Campaign ID.
	 * @param int                $email_id       Campaign email step ID.
	 * @param int                $per_batch      Maximum rows to inspect.
	 * @param int                $next_retry_at  Earliest retry timestamp, by reference.
	 * @return array<int,array<string,mixed>>
	 * @since 1.20.0
	 */
	private function getDispatchableRecipients( CampaignRepository $repository, int $campaign_id, int $email_id, int $per_batch, int &$next_retry_at ): array {
		$rows = $this->get_recipient_emails( $campaign_id, $email_id, $per_batch );

		if ( empty( $rows ) ) {
			return array();
		}

		$decisions  = $repository->prefetchSequenceParentGateDecisions( $rows, time() );
		$eligible   = array();

		foreach ( $rows as $row ) {
			$candidate_id = ! empty( $row['id'] ) ? (int) $row['id'] : 0;
			$decision     = $candidate_id > 0 ? ( $decisions[ $candidate_id ] ?? array() ) : array();

			if ( empty( $decision ) || ! empty( $decision['eligible'] ) ) {
				$eligible[] = $row;
				continue;
			}

			$retry_at = ! empty( $decision['retry_at'] ) ? (int) $decision['retry_at'] : 0;
			if ( $retry_at > 0 && ( 0 === $next_retry_at || $retry_at < $next_retry_at ) ) {
				$next_retry_at = $retry_at;
			}

			if ( ! empty( $decision['terminal_status'] ) && $candidate_id && $repository->claimGlobalDispatchCandidate( $candidate_id ) ) {
				$repository->updateGlobalDispatchCandidateStatus( $candidate_id, (string) $decision['terminal_status'] );
				$this->logSequenceGateDecision( $row, $decision );
			}
		}

		return $eligible;
	}

	/**
	 * Emit a structured gate event for blocked sequence child rows.
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
	 * Prepares email headers
	 *
	 * @param string $sender_name Sender name.
	 * @param string $sender_email Sender email.
	 * @param string $reply_name Replay name.
	 * @param string $reply_email Replay email.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function prepare_email_headers( $sender_name, $sender_email, $reply_name, $reply_email ) {
		$headers = array(
			'MIME-Version: 1.0',
			'Content-type: text/html;charset=UTF-8',
		);

		$from      = 'From: ' . $sender_name;
		$headers[] = $from . ' <' . $sender_email . '>';
		if ( $reply_name && $reply_email ) {
			$headers[] = 'Reply-To: ' . $reply_name . ' <' . $reply_email . '>';
		} elseif ( $reply_email ) {
			$headers[] = $reply_email;
		}

		return $headers;
	}

	/**
	 * Build the email attribute cache for a campaign email step.
	 *
	 * Fetches email attributes via CampaignRepository, applies Pro
	 * latest-content replacement, plain-text-editor conversion, and
	 * fetches the watermark. The result is stored in a transient
	 * keyed by campaign_id + email_id to avoid redundant DB queries.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id    Campaign email step ID.
	 *
	 * @return array {
	 *     @type string $email_subject      Parsed email subject template.
	 *     @type string $email_preview_text  Preview/preheader text template.
	 *     @type string $email_body          Pre-processed email body HTML.
	 *     @type string $editor_type         Editor type identifier.
	 *     @type string $watermark           Watermark HTML (already filtered).
	 * }
	 *
	 * @since 1.20.0
	 */
	private function buildEmailCache( int $campaign_id, int $email_id ): array {
		$repo             = new CampaignRepository();
		$email_attributes = $repo->getEmailAttributes( $campaign_id, $email_id );

		$email_subject      = ! empty( $email_attributes['email_subject'] ) ? $email_attributes['email_subject'] : '';
		$email_preview_text = ! empty( $email_attributes['email_preview_text'] ) ? $email_attributes['email_preview_text'] : '';
		$editor_type        = ! empty( $email_attributes['editor_type'] ) ? $email_attributes['editor_type'] : 'advanced-builder';

		// Fetch watermark (preserves mail_mint_remove_email_footer_watermark filter).
		$watermark = CampaignEmailBuilderModel::get_email_footer_watermark();

		// NOTE: email_body is intentionally excluded from the cache.
		// It can contain large base64-encoded images (26KB+) which would bloat
		// the wp_options transient and slow down every coordinator run.
		// The body is fetched fresh from DB per send via getEmailBody().
		return array(
			'email_subject'      => $email_subject,
			'email_preview_text' => $email_preview_text,
			'editor_type'        => $editor_type,
			'watermark'          => $watermark,
		);
	}

	/**
	 * Fetch and prepare the email body for a campaign email step.
	 *
	 * Called once per batch — not cached — to avoid storing large HTML bodies
	 * (which may contain base64 images) in the wp_options transient.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param int    $email_id    Campaign email step ID.
	 * @param string $editor_type Editor type from the cache.
	 *
	 * @return string Prepared email body HTML.
	 * @since 1.21.3
	 */
	private function getEmailBody( int $campaign_id, int $email_id, string $editor_type ): string {
		$repo             = new CampaignRepository();
		$email_attributes = $repo->getEmailAttributes( $campaign_id, $email_id );
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
	 * Delete completed actions [from Mail Mint] by action schedulers
	 *
	 * @param string $slug Action group slug.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @since 1.20.0 Delegates to ActionSchedulerTrait::unscheduleGroup().
	 */
	public function delete_actions( string $slug ) {
		$this->unscheduleGroup( $slug, 'complete' );
	}

	/**
	 * Delete completed Mail Mint Action Scheduler records after completion.
	 *
	 * Hooked to 'action_scheduler_completed_action' which fires AFTER mark_complete(),
	 * ensuring the action is fully processed before deletion. This prevents race conditions
	 * where the action might be deleted before error handling can mark it as failed.
	 *
	 * @param int $action_id Action ID.
	 *
	 * @return void
	 * @since 1.20.0
	 */
	public function delete_completed_mailmint_action( int $action_id ): void {
		$store  = \ActionScheduler_Store::instance();
		$action = $store->fetch_action( $action_id );

		if ( ! $action instanceof \ActionScheduler_Action ) {
			return;
		}

		$hooks = apply_filters(
			'mailmint_immediate_cleanup_hooks',
			array(
				MAILMINT_SEND_SCHEDULED_EMAILS,
				MAILMINT_SCHEDULE_EMAILS,
				GlobalQueueCoordinator::HOOK,
			)
		);

		if ( in_array( $action->get_hook(), $hooks, true ) ) {
			$store->delete_action( $action_id );
		}
	}

	/**
	 * Scheduler sending action after scheduling emails
	 *
	 * @param int|string $campaign_id Campaign id.
	 * @param int|string $campaign_email_id Campaign email id.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @since 1.20.0 Delegates to CampaignRepository::scheduleAsyncSendEmailAction().
	 */
	public function schedule_async_send_email_action( $campaign_id, $campaign_email_id ) {
		if ( $this->isGlobalQueueCoordinatorEnabled() ) {
			GlobalQueueCoordinator::get_instance()->schedule();
			return;
		}

		if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) ) {
			$repo = new CampaignRepository();
			$repo->scheduleAsyncSendEmailAction( (int) $campaign_id, (int) $campaign_email_id );
		}
	}

	/**
	 * Update campaign status to archived
	 *
	 * @param int|string $campaign_id Campaign id.
	 * @param int|string $email_id Email id.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @since 1.20.0 Uses CampaignRepository and CampaignType enum.
	 */
	public function update_campaign_status( $campaign_id, $email_id ) {
		$repo     = new CampaignRepository();
		$progress = $repo->getProgress( (int) $campaign_id );
		$campaign = $repo->find( (int) $campaign_id );

		$last_email_id = ! empty( $progress['last_email_id'] ) ? $progress['last_email_id'] : 0;
		$type          = ! empty( $campaign['type'] ) ? $campaign['type'] : '';
		$status        = CampaignType::RECURRING === $type ? 'active' : 'archived';

		if ( (int) $last_email_id === (int) $email_id ) {
			$repo->updateStatus( (int) $campaign_id, $status );
		}
	}

	/**
	 * Activate scheduled campaigns
	 *
	 * @param array $args Arguments.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @since 1.20.0 Uses CampaignRepository::updateStatus().
	 */
	public function activate_scheduled_campaign( $args ) {
		if ( !empty( $args[ 'campaign_id' ] ) && !empty( $args[ 'campaign_status' ] ) && 'schedule' === $args[ 'campaign_status' ] ) {
			$repo = new CampaignRepository();
			$repo->updateStatus( (int) $args[ 'campaign_id' ], 'active' );
		}
	}

	/**
	 * Recover emails stuck in 'sending' status.
	 *
	 * When an Action Scheduler action times out (e.g., after 300 seconds),
	 * emails that were claimed as 'sending' never get updated to 'sent' or 'failed'.
	 * This method resets any 'sending' emails older than 10 minutes back to 'scheduled'
	 * and reschedules the send action so the campaign continues.
	 *
	 * @return void
	 *
	 * @since 1.x.x
	 */
	public function recover_stuck_sending_emails() {
		global $wpdb;
		$broadcast_email_table = $wpdb->prefix . EmailSchema::$table_name;

		// Find distinct campaign/email combos with emails stuck in 'sending' for over 10 minutes.
		$stuck_timeout = gmdate( 'Y-m-d H:i:s', time() - 600 ); // 10 minutes ago.

		$stuck_campaigns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT `campaign_id`, `email_id` FROM {$broadcast_email_table} WHERE `status` = %s AND `email_type` = %s AND `updated_at` < %s",
				'sending',
				'campaign',
				$stuck_timeout
		), ARRAY_A ); //phpcs:ignore

		if ( empty( $stuck_campaigns ) ) {
			return;
		}

		// Reset all stuck 'sending' emails back to 'scheduled'.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$broadcast_email_table} SET `status` = 'scheduled', `updated_at` = %s WHERE `status` = %s AND `email_type` = %s AND `updated_at` < %s",
				current_time( 'mysql', true ),
				'sending',
				'campaign',
				$stuck_timeout
		) ); //phpcs:ignore

		if ( $this->isGlobalQueueCoordinatorEnabled() ) {
			GlobalQueueCoordinator::get_instance()->schedule( time() + 1 );
			return;
		}

		// Reschedule send actions for affected campaigns (if not already scheduled).
		foreach ( $stuck_campaigns as $stuck ) {
			$campaign_id = (int) $stuck['campaign_id'];
			$email_id    = (int) $stuck['email_id'];

			// Only skip scheduling if this specific campaign/email already has a pending/in-progress action.
			$action_args = array(
				'campaign_id' => $campaign_id,
				'email_id'    => $email_id,
			);

			if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) && ! MrmCommon::mailmint_as_has_scheduled_action( MAILMINT_SEND_SCHEDULED_EMAILS, $action_args, array( 'pending', 'in-progress' ) ) ) {
				CampaignModel::schedule_async_send_email_action( $campaign_id, $email_id );
			}
		}
	}

	/**
	 * Check whether the global coordinator rollout flag is enabled.
	 *
	 * @return bool
	 * @since 1.20.0
	 */
	private function isGlobalQueueCoordinatorEnabled(): bool {
		return (bool) apply_filters( 'mailmint_enable_global_queue_coordinator', true );
	}
}
