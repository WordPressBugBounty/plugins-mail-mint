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

		$start_time = time();

		if ( is_array( $recipients_emails ) && ! empty( $recipients_emails ) ) {
			$sender_name   = ! empty( $campaign_email[ 'sender_name' ] ) ? $campaign_email[ 'sender_name' ] : $sender_name;
			$sender_email  = ! empty( $campaign_email[ 'sender_email' ] ) ? $campaign_email[ 'sender_email' ] : $sender_email;
			$reply_name    = ! empty( $campaign_email[ 'reply_name' ] ) ? $campaign_email[ 'reply_name' ] : $reply_name;
			$reply_email   = ! empty( $campaign_email[ 'reply_email' ] ) ? $campaign_email[ 'reply_email' ] : $reply_email;
			$headers       = $this->prepare_email_headers( $sender_name, $sender_email, $reply_name, $reply_email );

			foreach ( $recipients_emails as $email ) {
				if ( BackgroundProcessHelper::memory_exceeded() || BackgroundProcessHelper::time_exceeded( $start_time, 0.6 ) ) {
					// Reschedule the task if memory or time limit is exceeded.
					$args = array(
						array(
							'campaign_id'     => $campaign_id,
							'campaign_status' => 'active',
							'email'           => $campaign_email,
							'offset'          => $offset,
							'per_batch'       => $per_batch,
						),
					);
					$group = 'mailmint-campaign-schedule-' . $campaign_id;
					as_schedule_single_action( time() + 120, MAILMINT_SCHEDULE_EMAILS, $args, $group );
					return false;
				}

				// Check if email id and email address is set.
				if ( isset( $email[ 'id' ], $email[ 'email' ] ) && $email[ 'id' ] && $email[ 'email' ] ) {
					$email_hash = MrmCommon::get_rand_email_hash( $email[ 'email' ], $campaign_id );

					$wpdb->insert( //phpcs:ignore
						$email_broadcast_table,
						array(
							'campaign_id'   => $campaign_id,
							'email_id'      => $campaign_email_id,
							'contact_id'    => $email['id'],
							'email_address' => $email['email'],
							'email_headers' => wp_json_encode($headers),
							'status'        => 'scheduled',
							'email_type'    => 'campaign',
							'email_hash'    => $email_hash,
							'scheduled_at'  => current_time('mysql'),
							'created_at'    => current_time('mysql'),
						)
					);

					usleep(5000); // 5 milliseconds sleep
				}
			}

			$campaign_email[ 'delay_value' ] = '';
			CampaignModel::schedule_campaign_action( $campaign_id, $campaign_email, 'active', '', ( $offset + $per_batch ) );
		} else {
			do_action( 'mailmint_single_email_scheduling_processed', (int) $campaign_id, (int) $campaign_email_id );

			CampaignModel::update_campaign_email_status( $campaign_id, $campaign_email_id, 'scheduled' );
			$email = CampaignModel::get_first_campaign_email( $campaign_id );

			$custom_date   = CampaignModel::get_campaign_email_meta( $email['id'], 'schedule_date' );
			$schedule_date = '';
			if( $custom_date ){
				$schedule_date = $custom_date;
			}
			if ( is_array( $email ) ) {
				CampaignModel::schedule_campaign_action( $campaign_id, $email, 'active', $schedule_date );
			} else {
				do_action( 'mailmint_campaign_emails_scheduling_completed', 'mailmint-campaign-schedule-' . $campaign_id );
			}
		}

		do_action( 'mailmint_email_batch_scheduling_processed', (int) $campaign_id, (int) $campaign_email_id );
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
		$campaign_id     = ! empty( $args[ 'campaign_id' ] ) ? $args[ 'campaign_id' ] : 0;
		$email_id        = ! empty( $args[ 'email_id' ] ) ? $args[ 'email_id' ] : 0;
		$batch           = ! empty( $args[ 'batch' ] ) ? $args[ 'batch' ] : 1;
		$frequency       = Helper::get_email_frequency_setting();
		$frequency_type  = !empty( $frequency['type'] ) ? $frequency['type'] : 'Recommended';
		$frequency_time  = !empty( $frequency['time'] ) ? $frequency['time'] : 5;
		$frequency_limit = !empty( $frequency['emails'] ) ? $frequency['emails'] : 25;
		/**
		 * Retrieves the batch limit for sending emails from a filter.on.
		 *
		 * This function retrieves the batch limit for sending emails from the
		 * 'mailmint_send_email_batch_limit' filter. If the filter is not applied or
		 * returns an invalid value, the default value of 20 is used.
		 *
		 * @return int The batch limit for sending emails.
		 *
		 * @since 1.5.2
		 */
		$per_batch = apply_filters( 'mailmint_send_email_batch_limit', 20 );
		if ( 'Manual' === $frequency_type ) {
			$per_batch = $frequency_limit;
		}
		$recipient_emails     = $campaign_id ? $this->get_recipient_emails( $campaign_id, $email_id, $per_batch ) : array();
		$email_attributes     = CampaignModel::get_campaign_email_attributes_to_sent( $campaign_id, $email_id );
		$global_email_subject = ! empty( $email_attributes['email_subject'] ) ? $email_attributes['email_subject'] : '';
		$global_preview_text  = ! empty( $email_attributes['email_preview_text'] ) ? $email_attributes['email_preview_text'] : '';
		$global_email_body    = ! empty( $email_attributes[ 'email_body' ] ) ? $email_attributes[ 'email_body' ] : '';
		$editor_type          = ! empty($email_attributes['editor_type']) ? $email_attributes['editor_type'] : 'advanced-builder';

		if( MrmCommon::is_mailmint_pro_active() ){
			$global_email_body = Mint_Pro_Helper::replace_automatic_latest_content( $global_email_body );
		}

		if( 'plain-text-editor' === $editor_type ){
			$global_email_body = nl2br( html_entity_decode( $global_email_body ) );
		}

		if ( is_array( $recipient_emails ) && ! empty( $recipient_emails ) ) {
			$sent_email_ids   = array();
			$failed_email_ids = array();
			$start_time       = time();

			foreach ( $recipient_emails as $recipient ) {
				$recipient_email    = ! empty( $recipient[ 'email_address' ] ) ? sanitize_email( $recipient[ 'email_address' ] ) : '';
				$broadcast_email_id = ! empty( $recipient[ 'id' ] ) ? (int) $recipient[ 'id' ] : '';
				$contact_id         = ! empty( $recipient[ 'contact_id' ] ) ? (int) $recipient[ 'contact_id' ] : '';
				$email_hash         = ! empty( $recipient[ 'email_hash' ] ) ? $recipient[ 'email_hash' ] : '';
				$email_headers      = ! empty( $recipient[ 'email_headers' ] ) ? json_decode( $recipient[ 'email_headers' ] ) : '';
				$unsubscribe_url    = Helper::get_unsubscribed_url( $email_hash );

				/** This filter is documented in app/Utilities/Helper/Email.php */
				if ( apply_filters( 'mail_mint_enable_unsubscribe_header', true, $email_headers ) ) {
					$email_headers[] = 'List-Unsubscribe: <' . $unsubscribe_url . '>';
					$email_headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
				}

				// Get contact and merge meta fields with contact fields.
				$contact = ContactModel::get( $contact_id );
				if (isset($contact['meta_fields']) && is_array($contact['meta_fields'])) {
					$contact = array_merge($contact, $contact['meta_fields']);
					unset($contact['meta_fields']);
				}

				// Parse email subject, preview text and email body.
				$email_subject   = Parser::parse( $global_email_subject, $contact );
				$preview_text    = Parser::parse( $global_preview_text, $contact );
				$email_headers[] = 'X-PreHeader: ' . $preview_text;
				$email_body_html = Parser::parse( $global_email_body, $contact );
				$email_body_html = Helper::replace_url( $email_body_html, $email_hash );
				$email_body_html = Email::inject_tracking_image_on_email_body($email_hash, $email_body_html);

				// Add preview text on the email body.
				$email_body_html = Email::inject_preview_text_on_email_body( $preview_text, $email_body_html );

				if ( 'advanced-builder' === $editor_type ) {
					$email_body_html = str_replace( '</html>', CampaignEmailBuilderModel::get_email_footer_watermark() . '</html>', $email_body_html );
				} else {
					$email_body_html = $email_body_html . CampaignEmailBuilderModel::get_email_footer_watermark();
				}

				$email_body_html = Helper::modify_email_for_rtl( $email_body_html );

				if ( MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.15.1') ) {
					$email_body_html = Mint_Pro_Helper::process_lead_magnet_tracking( $email_body_html, $recipient_email );
				}	

				if ( BackgroundProcessHelper::memory_exceeded() || BackgroundProcessHelper::time_exceeded($start_time, 0.6) ) {
					break;
				}

				$email_sent = MM()->mailer->send( $recipient_email, $email_subject, $email_body_html, $email_headers );

				if ( $email_sent ) {
					$sent_email_ids[] = $broadcast_email_id;
				} else {
					$failed_email_ids[] = $broadcast_email_id;
				}
			}

			self::update_scheduled_emails_status( $sent_email_ids, 'sent' );
			self::update_scheduled_emails_status( $failed_email_ids, 'failed' );

			CampaignModel::schedule_single_send_email_action_delay( (int) $campaign_id, $email_id, (int) $batch + 1, $frequency_time );
			do_action( 'mailmint_batch_email_sent', 'mailmint-campaign-email-sending-' . $campaign_id );
		} else {
			do_action( 'mailmint_campaign_email_sent', $campaign_id, $email_id );
		}
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

		$sql_query  = "SELECT `id`, `email_id`, `email_address`, `email_headers`, `contact_id`, `email_hash` FROM {$broadcast_email_table} ";
		$sql_query .= 'WHERE `status` = %s ';
		$sql_query .= 'AND `email_type` = %s ';
		$sql_query .= 'AND `campaign_id` = %s ';
		$sql_query .= 'AND `email_id` = %s ';
		$sql_query .= 'LIMIT %d';
		$sql_query = $wpdb->prepare( $sql_query, 'scheduled', 'campaign', $campaign_id, $email_id, $per_batch ); //phpcs:ignore

		return $wpdb->get_results( $sql_query, ARRAY_A ); //phpcs:ignore
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
	 * Delete completed actions [from Mail Mint] by action schedulers
	 *
	 * @param string $slug Action group slug.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function delete_actions( string $slug ) {
		MrmCommon::delete_as_actions( $slug, 'complete' );
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
	 */
	public function schedule_async_send_email_action( $campaign_id, $campaign_email_id ) {
		if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) && !MrmCommon::mailmint_as_has_scheduled_action( MAILMINT_SEND_SCHEDULED_EMAILS ) ) {
			CampaignModel::schedule_async_send_email_action( $campaign_id, $campaign_email_id );
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
	 */
	public function update_campaign_status( $campaign_id, $email_id ) {
		$last_email_id = CampaignModel::get_last_campaign_email_id( $campaign_id );
		$type          = CampaignModel::get_campaign_type( $campaign_id );
		$status        = 'recurring' === $type ? 'active' : 'archived';
		if ( (int) $last_email_id === (int) $email_id ) {
			CampaignModel::update_campaign_status( $campaign_id, $status );
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
	 */
	public function activate_scheduled_campaign( $args ) {
		if ( !empty( $args[ 'campaign_id' ] ) && !empty( $args[ 'campaign_status' ] ) && 'schedule' === $args[ 'campaign_status' ] ) {
			CampaignModel::update_campaign_status( $args[ 'campaign_id' ], 'active' );
		}
	}
}
