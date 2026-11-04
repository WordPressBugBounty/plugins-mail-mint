<?php
/**
 * Checks for setup problems that stop Mail Mint emails from being delivered.
 *
 * @package Mint\MRM\Internal\Admin
 * @since 1.31.8
 */

namespace Mint\MRM\Internal\Admin;

use Mint\MRM\DataBase\Tables\EmailMetaSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;

/**
 * Class SendingHealth
 *
 * Each check returns an array with `name`, `status` ('pass', 'warning' or 'fail'),
 * `details` and `recommendation` (null when there is nothing to do).
 *
 * @package Mint\MRM\Internal\Admin
 * @since 1.31.8
 */
class SendingHealth {

	/**
	 * Transient that caches the campaign-screen notice.
	 *
	 * @var string
	 * @since 1.31.8
	 */
	const NOTICE_TRANSIENT = 'mailmint_sending_health_notice';

	/**
	 * Pending Action Scheduler actions overdue by this many minutes trigger a warning.
	 *
	 * @var int
	 * @since 1.31.8
	 */
	const OVERDUE_WARNING_MINUTES = 15;

	/**
	 * Pending Action Scheduler actions overdue by this many minutes mean sending has stopped.
	 *
	 * @var int
	 * @since 1.31.8
	 */
	const OVERDUE_FAIL_MINUTES = 60;

	/**
	 * Number of days looked back for failed sends.
	 *
	 * @var int
	 * @since 1.31.8
	 */
	const FAILURE_WINDOW_DAYS = 7;

	/**
	 * Campaign rows scheduled this many days ago are still checked for recent failures.
	 *
	 * A campaign scheduled earlier than this that is still failing today is not counted.
	 *
	 * @var int
	 * @since 1.31.8
	 */
	const CAMPAIGN_LOOKBACK_DAYS = 30;

	/**
	 * How many of the newest automation rows (per status) the failure check scans.
	 *
	 * @var int
	 * @since 1.31.8
	 */
	const FAILURE_SCAN_ROWS = 50000;

	/**
	 * Failures needed (with nothing sent) before the failure check reports "fail".
	 *
	 * @var int
	 * @since 1.31.8
	 */
	const FAILURE_ALERT_MIN = 5;

	/**
	 * Option holding a counter that is part of the notice cache key.
	 *
	 * @var string
	 * @since 1.31.8
	 */
	const NOTICE_GENERATION_OPTION = 'mailmint_sending_health_notice_gen';

	/**
	 * Action Scheduler hooks of Mail Mint (Free and Pro) whose tasks lead to email being sent.
	 *
	 * @var string[]
	 * @since 1.31.8
	 */
	const SENDING_HOOKS = array(
		'mailmint_global_queue_coordinator',
		'mailmint_schedule_emails',
		'mailmint_send_scheduled_emails',
		'mailmint_recurring_schedule',
		'mint_process_automation_data',
		'mailmint_process_post_published_scheduler',
		'mailmint_process_price_dropped_scheduler',
		'mailmint_run_link_trigger_async',
		'mailmint_split_test_winner_check',
		'mint_process_sequence',
		'mint_abandoned_cart_scheduler',
		'mailmint_process_contact_anniversary_daily',
		'mailmint_process_contact_anniversary_daily_once',
		'mailmint_process_customer_win_back_daily',
		'mailmint_process_customer_win_back_daily_once',
		'mailmint_process_inactive_subscriber_daily',
		'mailmint_process_inactive_subscriber_daily_once',
		'mailmint_process_wcs_renewal_daily',
		'mailmint_process_wcs_renewal_daily_once',
		'mailmint_process_wcs_end_daily',
		'mailmint_process_wcs_end_daily_once',
	);

	/**
	 * Free webmail domains that fail DMARC/SPF when used as the From address of bulk mail.
	 *
	 * @var string[]
	 * @since 1.31.8
	 */
	const FREE_MAIL_DOMAINS = array(
		'gmail.com',
		'googlemail.com',
		'yahoo.com',
		'ymail.com',
		'hotmail.com',
		'outlook.com',
		'live.com',
		'msn.com',
		'icloud.com',
		'me.com',
		'aol.com',
		'gmx.com',
		'mail.com',
		'protonmail.com',
		'proton.me',
		'yandex.com',
		'zoho.com',
	);

	/**
	 * Run every sending check.
	 *
	 * @return array{status: string, checks: array}
	 * @since 1.31.8
	 */
	public static function get_report() {
		$checks = array(
			'smtp'            => self::check_smtp(),
			'sender_email'    => self::check_sender_email(),
			'background_jobs' => self::check_background_jobs(),
			'recent_failures' => self::check_recent_failures(),
		);

		return array(
			'status' => self::worst_status( $checks ),
			'checks' => $checks,
		);
	}

	/**
	 * Check that mail goes through an SMTP plugin or mailer instead of PHP mail().
	 *
	 * @return array
	 * @since 1.31.8
	 */
	public static function check_smtp() {
		$name           = __( 'SMTP / Mailer Configuration', 'mrm' );
		$smtp_plugin    = self::detect_smtp_plugin();
		$mailer_options = get_option( 'mrm_email_service_settings', array() );
		$mailer_slug    = ! empty( $mailer_options['mailer'] ) ? $mailer_options['mailer'] : 'wpmail';

		if ( $smtp_plugin ) {
			return array(
				'name'           => $name,
				'status'         => 'pass',
				// translators: %s: SMTP plugin name.
				'details'        => sprintf( __( 'SMTP plugin detected (%s). Mail delivery is configured.', 'mrm' ), $smtp_plugin ),
				'recommendation' => null,
				'source'         => 'plugin',
				'plugin'         => $smtp_plugin,
			);
		}

		if ( 'wpmail' !== $mailer_slug ) {
			return array(
				'name'           => $name,
				'status'         => 'pass',
				// translators: %s: Mailer name.
				'details'        => sprintf( __( 'Mail Mint mailer configured (%s).', 'mrm' ), ucfirst( $mailer_slug ) ),
				'recommendation' => null,
				'source'         => 'mailer',
				'plugin'         => '',
			);
		}

		$has_smtp_constants = ( defined( 'SMTP_HOST' ) && SMTP_HOST )
			|| ( defined( 'WPMS_ON' ) && WPMS_ON )
			|| defined( 'POST_SMTP_AUTH' );

		$has_smtp_classes = class_exists( 'WPMailSMTP\Core' )
			|| function_exists( 'wp_mail_smtp' )
			|| defined( 'FLUENTMAIL' )
			|| class_exists( 'FluentSmtp\App\App' )
			|| class_exists( 'Postman' )
			|| defined( 'POST_SMTP_VER' )
			|| class_exists( 'EasyWPSMTP' )
			|| defined( 'EASY_WP_SMTP_VER' );

		if ( $has_smtp_constants || $has_smtp_classes ) {
			return array(
				'name'           => $name,
				'status'         => 'pass',
				'details'        => __( 'Custom SMTP configuration detected in WordPress.', 'mrm' ),
				'recommendation' => null,
				'source'         => 'constants',
				'plugin'         => '',
			);
		}

		return array(
			'name'           => $name,
			'status'         => 'fail',
			'details'        => __( 'No SMTP plugin or custom mailer detected. Emails are using default WordPress wp_mail() / PHP mail(), which frequently fails or gets flagged as SPAM.', 'mrm' ),
			'recommendation' => __( 'Install and configure an SMTP plugin to ensure reliable email deliverability.', 'mrm' ),
			'source'         => 'none',
			'plugin'         => '',
		);
	}

	/**
	 * Check the From address used for Mail Mint emails.
	 *
	 * @return array
	 * @since 1.31.8
	 */
	public static function check_sender_email() {
		$name           = __( 'Sender Email Address', 'mrm' );
		$email_settings = get_option( '_mrm_email_settings', array() );
		$configured     = ! empty( $email_settings['from_email'] ) ? trim( $email_settings['from_email'] ) : '';
		$from_email     = '' !== $configured ? $configured : get_option( 'admin_email' );

		if ( empty( $from_email ) ) {
			return array(
				'name'           => $name,
				'status'         => 'fail',
				'details'        => __( 'No sender email address configured.', 'mrm' ),
				'recommendation' => __( 'Go to Mail Mint > Settings > Email Settings and set a valid From Email address.', 'mrm' ),
			);
		}

		if ( ! is_email( $from_email ) ) {
			return array(
				'name'           => $name,
				'status'         => 'fail',
				// translators: %s: Sender email address.
				'details'        => sprintf( __( 'The sender email address (%s) is not valid.', 'mrm' ), $from_email ),
				'recommendation' => __( 'Go to Mail Mint > Settings > Email Settings and set a valid From Email address.', 'mrm' ),
			);
		}

		$parts  = explode( '@', strtolower( $from_email ) );
		$domain = end( $parts );

		if ( in_array( $domain, self::FREE_MAIL_DOMAINS, true ) ) {
			return array(
				'name'           => $name,
				'status'         => 'warning',
				'from_email'     => $from_email,
				// translators: 1: Sender email address, 2: Domain name.
				'details'        => sprintf( __( 'Sender email (%1$s) uses a free webmail domain (@%2$s).', 'mrm' ), $from_email, $domain ),
				'recommendation' => __( 'Free webmail domains fail DMARC/SPF authentication when sending bulk emails. Major mailbox providers (Gmail, Yahoo, Outlook) reject these emails or send them to SPAM. Use an email address from your custom domain (e.g. info@yourdomain.com).', 'mrm' ),
			);
		}

		return array(
			'name'           => $name,
			'status'         => 'pass',
			'from_email'     => $from_email,
			'details'        => '' !== $configured
				// translators: %s: Sender email address.
				? sprintf( __( 'Sender email is configured with custom domain (%s).', 'mrm' ), $from_email )
				// translators: %s: WordPress admin email address.
				: sprintf( __( 'No From Email is set, so the WordPress admin email (%s) is used. It uses a custom domain.', 'mrm' ), $from_email ),
			'recommendation' => null,
		);
	}

	/**
	 * Check that WP-Cron / Action Scheduler is running the queued sending jobs.
	 *
	 * Campaign and automation emails are sent by Action Scheduler, which is driven by
	 * WP-Cron. If it stops, emails stay "scheduled" without any error.
	 *
	 * @return array
	 * @since 1.31.8
	 */
	public static function check_background_jobs() {
		global $wpdb;

		$name          = __( 'Background Sending', 'mrm' );
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$table         = $wpdb->prefix . 'actionscheduler_actions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return array(
				'name'            => $name,
				'status'          => 'warning',
				'details'         => __( 'The Action Scheduler tables were not found, so queued sending could not be checked.', 'mrm' ),
				'recommendation'  => null,
				'overdue_count'   => null,
				'overdue_minutes' => null,
			);
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::OVERDUE_WARNING_MINUTES * MINUTE_IN_SECONDS );
		// Only Mail Mint tasks that lead to email being sent (Free and Pro), so other plugins'
		// overdue actions don't raise a Mail Mint warning. An exact hook list matches the
		// (hook, status, scheduled_date_gmt) index; a LIKE prefix made MySQL scan every pending
		// action of every plugin. Zero dates come from legacy rows and are not "overdue".
		/**
		 * Filters the Action Scheduler hooks counted as Mail Mint sending tasks.
		 *
		 * @param string[] $hooks Hook names.
		 * @since 1.31.8
		 */
		$hooks = array_values( array_filter( (array) apply_filters( 'mailmint_sending_health_hooks', self::SENDING_HOOKS ), 'is_string' ) );
		if ( empty( $hooks ) ) {
			$hooks = self::SENDING_HOOKS;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $hooks ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row           = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, MIN(scheduled_date_gmt) AS oldest FROM {$table}
				WHERE hook IN ( {$placeholders} )
				AND status = 'pending'
				AND scheduled_date_gmt > '2000-01-01 00:00:00'
				AND scheduled_date_gmt < %s",
				array_merge( array_values( $hooks ), array( $cutoff ) )
			)
		);
		$overdue_count = $row ? (int) $row->total : 0;
		$oldest        = ( $row && $row->oldest ) ? $row->oldest : '';
		// phpcs:enable

		$overdue_minutes = '' !== $oldest ? (int) floor( ( time() - strtotime( $oldest . ' UTC' ) ) / MINUTE_IN_SECONDS ) : 0;

		if ( $overdue_count > 0 ) {
			$cron_hint = $cron_disabled
				? __( 'DISABLE_WP_CRON is set, so a server cron job must call wp-cron.php every minute. Check that the cron job exists and runs.', 'mrm' )
				: __( 'WP-Cron only runs when the site gets visits, and it can be blocked by the host or by failing loopback requests. Set up a server cron job that calls wp-cron.php every minute.', 'mrm' );

			return array(
				'name'            => $name,
				'status'          => $overdue_minutes >= self::OVERDUE_FAIL_MINUTES ? 'fail' : 'warning',
				'details'         => sprintf(
					/* translators: 1: number of overdue background tasks, 2: age of the oldest one, e.g. "2 hours". */
					_n(
						'%1$d Mail Mint sending task is overdue (the oldest by %2$s). Scheduled campaigns and automations are not being sent on time.',
						'%1$d Mail Mint sending tasks are overdue (the oldest by %2$s). Scheduled campaigns and automations are not being sent on time.',
						$overdue_count,
						'mrm'
					),
					$overdue_count,
					human_time_diff( time() - $overdue_minutes * MINUTE_IN_SECONDS )
				),
				'recommendation'  => $cron_hint,
				'overdue_count'   => $overdue_count,
				'overdue_minutes' => $overdue_minutes,
			);
		}

		return array(
			'name'            => $name,
			'status'          => 'pass',
			'details'         => $cron_disabled
				? __( 'Background tasks are running on time. WP-Cron is disabled, so this relies on a server cron job calling wp-cron.php.', 'mrm' )
				: __( 'Background tasks are running on time.', 'mrm' ),
			'recommendation'  => null,
			'overdue_count'   => 0,
			'overdue_minutes' => 0,
		);
	}

	/**
	 * Check for Mail Mint emails that failed to send recently.
	 *
	 * @param bool $with_reason Also look up the most common failure reason (the slowest query).
	 * @return array
	 * @since 1.31.8
	 */
	public static function check_recent_failures( $with_reason = true ) {
		global $wpdb;

		$name       = __( 'Recent Sending', 'mrm' );
		$table      = $wpdb->prefix . EmailSchema::$table_name;
		$meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		// created_at is in site time; updated_at is UTC for campaign rows but site time for
		// automation rows. Accept a row if either is recent (on far-east timezones this can
		// include rows up to a few hours older than the window).
		$local_cutoff = wp_date( 'Y-m-d H:i:s', time() - self::FAILURE_WINDOW_DAYS * DAY_IN_SECONDS );
		$utc_cutoff   = gmdate( 'Y-m-d H:i:s', time() - self::FAILURE_WINDOW_DAYS * DAY_IN_SECONDS );
		$recent       = $wpdb->prepare( '( created_at >= %s OR updated_at >= %s )', $local_cutoff, $utc_cutoff );

		// The table can hold millions of rows and only (status, scheduled_at) is indexed, so every
		// query below reads a bounded slice of that index:
		// - campaign rows have scheduled_at; take those scheduled in the last CAMPAIGN_LOOKBACK_DAYS
		// (slow campaigns keep sending long after they were scheduled), then filter by date;
		// - automation rows have no scheduled_at and are inserted when sent, so within the index
		// they are ordered by id; take the newest FAILURE_SCAN_ROWS, then filter by date.
		$campaign_from = gmdate( 'Y-m-d H:i:s', time() - self::CAMPAIGN_LOOKBACK_DAYS * DAY_IN_SECONDS );
		$campaign_rows = $wpdb->prepare( 'status = %s AND scheduled_at >= %s', 'failed', $campaign_from );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- %i needs WP 6.2; the plugin supports 5.8.
		// One row over the cap tells "exactly FAILURE_SCAN_ROWS" apart from "more".
		$auto_rows = $wpdb->prepare( "SELECT id, created_at, updated_at FROM {$table} WHERE status = %s AND scheduled_at IS NULL ORDER BY id DESC LIMIT %d", 'failed', self::FAILURE_SCAN_ROWS + 1 );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// Both halves are capped at FAILURE_SCAN_ROWS, so a huge failure backlog can't make this slow.
		$campaign_failed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ( SELECT 1 FROM {$table} WHERE {$campaign_rows} AND {$recent} LIMIT " . ( self::FAILURE_SCAN_ROWS + 1 ) . ' ) c' );
		$auto_failed     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ( {$auto_rows} ) a WHERE {$recent}" );
		// A half over the cap may have more rows; report the count as a lower bound.
		$capped = $campaign_failed > self::FAILURE_SCAN_ROWS || $auto_failed > self::FAILURE_SCAN_ROWS;
		$failed = min( $campaign_failed, self::FAILURE_SCAN_ROWS ) + min( $auto_failed, self::FAILURE_SCAN_ROWS );

		if ( 0 === $failed ) {
			return array(
				'name'           => $name,
				'status'         => 'pass',
				'details'        => sprintf(
					/* translators: %d: number of days. */
					__( 'No failed emails in the last %d days.', 'mrm' ),
					self::FAILURE_WINDOW_DAYS
				),
				'recommendation' => null,
				'failed_count'   => 0,
				'failed_capped'  => false,
				'any_sent'       => null,
			);
		}

		// Was anything sent successfully in the window? Newest-first, stop at the first hit.
		$any_sent = (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$table} WHERE status = 'sent' AND scheduled_at >= %s AND {$recent} ORDER BY scheduled_at DESC LIMIT 1", $campaign_from )
		);
		if ( ! $any_sent ) {
			$latest_auto_sent = $wpdb->get_row( "SELECT created_at, updated_at FROM {$table} WHERE status = 'sent' AND scheduled_at IS NULL ORDER BY id DESC LIMIT 1" );
			$any_sent         = $latest_auto_sent && ( $latest_auto_sent->created_at >= $local_cutoff || $latest_auto_sent->updated_at >= $utc_cutoff );
		}

		$top_reason = ! $with_reason ? '' : $wpdb->get_var(
			"SELECT m.meta_value FROM {$meta_table} m
			INNER JOIN (
				( SELECT id FROM {$table} WHERE {$campaign_rows} AND {$recent} ORDER BY scheduled_at DESC LIMIT " . (int) self::FAILURE_SCAN_ROWS . " )
				UNION ALL
				( SELECT a.id FROM ( {$auto_rows} ) a WHERE {$recent} )
			) f ON f.id = m.mint_email_id
			WHERE m.meta_key = 'failure_reason'
			GROUP BY m.meta_value
			ORDER BY COUNT(*) DESC
			LIMIT 1"
		);
		// phpcs:enable

		$details = $capped
			? sprintf(
				/* translators: 1: number of failed emails, 2: number of days. */
				__( 'More than %1$s emails failed in the last %2$d days.', 'mrm' ),
				number_format_i18n( $failed ),
				self::FAILURE_WINDOW_DAYS
			)
			: sprintf(
				/* translators: 1: number of failed emails, 2: number of days. */
				_n( '%1$d email failed in the last %2$d days.', '%1$d emails failed in the last %2$d days.', $failed, 'mrm' ),
				$failed,
				self::FAILURE_WINDOW_DAYS
			);
		if ( ! $any_sent ) {
			$details .= ' ' . __( 'No emails were sent successfully in that time.', 'mrm' );
		}
		if ( $top_reason ) {
			$top_reason = trim( $top_reason );
			$details   .= ' ' . sprintf(
				/* translators: %s: error message returned by the mailer. */
				__( 'Most common error: %s', 'mrm' ),
				preg_match( '/[.!?]$/', $top_reason ) ? $top_reason : $top_reason . '.'
			);
		}

		// A single bounce-like failure shouldn't raise a critical alert; "fail" needs several failures and no successes.
		$status = ( ! $any_sent && $failed >= self::FAILURE_ALERT_MIN ) ? 'fail' : 'warning';

		return array(
			'name'           => $name,
			'status'         => $status,
			'details'        => $details,
			'recommendation' => __( 'Open Emails > Email History and filter by Failed to see the reason for each email.', 'mrm' ),
			'failed_count'   => $failed,
			'failed_capped'  => $capped,
			'any_sent'       => (bool) $any_sent,
		);
	}

	/**
	 * Anonymous mail-health values for the deactivation telemetry event.
	 *
	 * Counts, booleans, statuses, the WordPress version and the SMTP plugin name only:
	 * never the From address, recipient addresses or mailer error text.
	 *
	 * @return array
	 * @since 1.31.8
	 */
	public static function get_telemetry() {
		$smtp       = self::check_smtp();
		$sender     = self::check_sender_email();
		$background = self::check_background_jobs();
		// The failure reason is text and not sent, so skip its (slow) lookup.
		$failures = self::check_recent_failures( false );

		return array(
			'wp_version'             => get_bloginfo( 'version' ),
			'sending_health'         => self::worst_status( array( $smtp, $sender, $background, $failures ) ),
			'smtp_configured'        => 'pass' === $smtp['status'],
			'smtp_source'            => $smtp['source'],
			'smtp_plugin'            => $smtp['plugin'],
			'from_email_valid'       => 'fail' !== $sender['status'],
			'from_domain_freemail'   => 'warning' === $sender['status'],
			'cron_disabled'          => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'as_past_due_count'      => $background['overdue_count'],
			'as_past_due_minutes'    => $background['overdue_minutes'],
			'failed_sends_7d'        => $failures['failed_count'],
			'failed_sends_7d_capped' => $failures['failed_capped'],
			'any_sent_7d'            => $failures['any_sent'],
		);
	}

	/**
	 * The most important problem to show on campaign screens, cached briefly.
	 *
	 * Stopped background sending and all-failing sends are shown even after the SMTP
	 * notice was dismissed, and can't be dismissed for good (`dismissible` false).
	 * The SMTP warning is hidden once the user has dismissed it.
	 *
	 * @return array{status: string, message: string, dismissible: bool}|false Notice for the campaign screens, or false for none.
	 * @since 1.31.8
	 */
	public static function get_campaign_notice() {
		// Per locale, since the message is translated; the generation lets clear_notice_cache() drop every locale's copy.
		$key    = self::NOTICE_TRANSIENT . '_' . (int) get_option( self::NOTICE_GENERATION_OPTION, 0 ) . '_' . get_user_locale();
		$notice = get_transient( $key );
		if ( false === $notice ) {
			$notice = self::build_campaign_notice();
			// Store '' rather than false so "no notice" is cached too. A critical problem is
			// re-checked sooner, because loading the page may itself have started the stalled queue.
			$critical = $notice && empty( $notice['dismissible'] );
			set_transient( $key, $notice ? $notice : '', $critical ? MINUTE_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
		}

		return $notice ? $notice : false;
	}

	/**
	 * Drop the cached campaign notice for every locale.
	 *
	 * @return void
	 * @since 1.31.8
	 */
	public static function clear_notice_cache() {
		update_option( self::NOTICE_GENERATION_OPTION, (int) get_option( self::NOTICE_GENERATION_OPTION, 0 ) + 1, false );
	}

	/**
	 * Pick the notice for get_campaign_notice().
	 *
	 * @return array|false
	 * @since 1.31.8
	 */
	private static function build_campaign_notice() {
		$candidates = array( self::check_background_jobs(), self::check_recent_failures() );
		foreach ( $candidates as $check ) {
			if ( 'fail' === $check['status'] ) {
				return array(
					'status'      => 'success',
					'message'     => trim( $check['details'] . ' ' . $check['recommendation'] ),
					'dismissible' => false,
				);
			}
		}

		$smtp = self::check_smtp();
		if ( 'pass' !== $smtp['status'] && ! get_option( 'mint_notice_update', false ) ) {
			return array(
				'status'      => 'success',
				'message'     => __( 'Your emails are sent with the default WordPress mailer (PHP mail()), which often fails or lands in spam. Install an SMTP plugin for reliable delivery.', 'mrm' ),
				'dismissible' => true,
			);
		}

		return false;
	}

	/**
	 * Name of the first active SMTP/mailer plugin, or an empty string.
	 *
	 * Deliberately not Constants::get_smtp_plugin_list(): that list is of plugins that
	 * conflict with mail delivery (it includes "Disable Emails"), not of working mailers.
	 *
	 * @return string
	 * @since 1.31.8
	 */
	private static function detect_smtp_plugin() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$smtp_plugins = array(
			'wp-mail-smtp/wp_mail_smtp.php'       => 'WP Mail SMTP',
			'wp-mail-smtp-pro/wp_mail_smtp.php'   => 'WP Mail SMTP Pro',
			'fluent-smtp/fluent-smtp.php'         => 'FluentSMTP',
			'post-smtp/postman-smtp.php'          => 'Post SMTP',
			'easy-wp-smtp/easy-wp-smtp.php'       => 'Easy WP SMTP',
			'smtp-mailer/main.php'                => 'SMTP Mailer',
			'wp-offload-ses/wp-offload-ses.php'   => 'WP Offload SES',
			'gmail-smtp/main.php'                 => 'Gmail SMTP',
			'sendinblue-subscribe/sendinblue.php' => 'Brevo (Sendinblue)',
			'mailgun/mailgun.php'                 => 'Mailgun',
		);

		foreach ( $smtp_plugins as $plugin_path => $name ) {
			if ( is_plugin_active( $plugin_path ) ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Worst status across checks.
	 *
	 * @param array $checks Check results.
	 * @return string
	 * @since 1.31.8
	 */
	private static function worst_status( $checks ) {
		$statuses = wp_list_pluck( $checks, 'status' );
		if ( in_array( 'fail', $statuses, true ) ) {
			return 'fail';
		}
		return in_array( 'warning', $statuses, true ) ? 'warning' : 'pass';
	}
}
