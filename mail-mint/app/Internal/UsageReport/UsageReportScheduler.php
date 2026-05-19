<?php
/**
 * UsageReportScheduler
 *
 * Manages the WP-Cron event that sends the weekly / monthly usage-digest email.
 * Hook registration is handled here so the main plugin file only needs to call
 * UsageReportScheduler::init().
 *
 * @author   MRM Team
 * @category Internal
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Internal\UsageReport;

/**
 * Class UsageReportScheduler
 *
 * @package Mint\MRM\Internal\UsageReport
 */
class UsageReportScheduler {

	const CRON_HOOK  = 'mint_send_usage_report';
	const OPTION_KEY = '_mint_usage_report_settings';

	// ──────────────────────────────────────────────────────────────────────
	// Bootstrap
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Register hooks. Call once from the main plugin bootstrap.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_digest' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );

		// Auto-schedule for new installs and plugin updates.
		add_action( 'mailmint_newly_installed', array( __CLASS__, 'schedule_on_install' ) );
		add_action( 'mailmint_updated',         array( __CLASS__, 'maybe_reschedule_on_update' ) );
	}

	/**
	 * Add custom WP-Cron intervals.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_intervals( array $schedules ) {
		if ( ! isset( $schedules['mint_weekly'] ) ) {
			$schedules['mint_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly (Mail Mint)', 'mrm' ),
			);
		}

		if ( ! isset( $schedules['mint_monthly'] ) ) {
			$schedules['mint_monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly (Mail Mint)', 'mrm' ),
			);
		}

		return $schedules;
	}

	// ──────────────────────────────────────────────────────────────────────
	// Auto-scheduling on install / update
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Save default settings and schedule the digest on a fresh install.
	 * Hooked to `mailmint_newly_installed`.
	 *
	 * @return void
	 */
	public static function schedule_on_install() {
		if ( get_option( self::OPTION_KEY ) ) {
			return;
		}

		$settings = self::default_settings();
		update_option( self::OPTION_KEY, $settings );
		self::reschedule( $settings );
	}

	/**
	 * Ensure the digest is scheduled after a plugin update.
	 * Hooked to `mailmint_updated`.
	 *
	 * - Existing users who never had this feature yet get default settings
	 *   (enabled, weekly) and are scheduled automatically.
	 * - Users who already have settings: cron is silently re-queued if it was
	 *   lost (e.g. after a manual deactivation/reactivation) while enabled=true.
	 *
	 * @return void
	 */
	public static function maybe_reschedule_on_update() {
		$stored = get_option( self::OPTION_KEY, false );

		if ( false === $stored ) {
			// Feature is brand-new to this site — apply defaults and schedule.
			$settings = self::default_settings();
			update_option( self::OPTION_KEY, $settings );
			self::reschedule( $settings );
			return;
		}

		// Settings already exist — respect them, but re-queue if the cron was lost.
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::default_settings() );
		if ( ! empty( $settings['enabled'] ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			self::reschedule( $settings );
		}
	}

	// ──────────────────────────────────────────────────────────────────────
	// Scheduling
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * (Re)schedule the digest cron event to match saved settings.
	 * Called by UsageReportSettingController after every successful save.
	 *
	 * @param array $settings Sanitised settings array.
	 * @return void
	 */
	public static function reschedule( array $settings ) {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$recurrence = ( 'monthly' === $settings['frequency'] ) ? 'mint_monthly' : 'mint_weekly';
		$first_run  = self::calculate_first_run( $settings );

		wp_schedule_event( $first_run, $recurrence, self::CRON_HOOK );
	}

	/**
	 * Calculate the Unix timestamp for the very first run based on schedule settings.
	 *
	 * @param array $settings Settings array.
	 * @return int Unix timestamp.
	 */
	private static function calculate_first_run( array $settings ) {
		$tz  = \wp_timezone();
		$now = new \DateTime( 'now', $tz );

		if ( 'monthly' === $settings['frequency'] ) {
			$target_day = isset( $settings['month_day'] ) ? (int) $settings['month_day'] : 1;
			$target_day = max( 1, min( 31, $target_day ) );

			$next = clone $now;
			$next->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'm' ), $target_day );
			$next->setTime( 9, 0, 0 );
			if ( $next <= $now ) {
				$next->modify( '+1 month' );
				$next->setDate( (int) $next->format( 'Y' ), (int) $next->format( 'm' ), $target_day );
				$next->setTime( 9, 0, 0 );
			}
		} else {
			$target_day = isset( $settings['week_day'] ) ? ucfirst( strtolower( $settings['week_day'] ) ) : 'Monday';
			$next       = new \DateTime( 'next ' . $target_day, $tz );
			$next->setTime( 9, 0, 0 );

			$today_candidate = clone $now;
			$today_candidate->setTime( 9, 0, 0 );
			if ( strtolower( $now->format( 'l' ) ) === strtolower( $target_day ) && $today_candidate > $now ) {
				$next = $today_candidate;
			}
		}

		return $next->getTimestamp();
	}

	// ──────────────────────────────────────────────────────────────────────
	// Sending
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Send an immediate one-off digest to one or more addresses (used for test sends).
	 *
	 * @param string[] $recipients One or more validated recipient email addresses.
	 * @param array    $settings   Settings array to use when building the email.
	 * @return bool True if wp_mail accepted the message for every recipient.
	 */
	public static function send_test( array $recipients, array $settings ) {
		$is_monthly   = 'monthly' === ( $settings['frequency'] ?? 'weekly' );
		$period_label = $is_monthly ? __( 'This Month', 'mrm' ) : __( 'This Week', 'mrm' );

		$tz        = \wp_timezone();
		$now       = new \DateTime( 'now', $tz );
		$days      = $is_monthly ? 30 : 7;
		$date_from = clone $now;
		$date_from->modify( "-{$days} days" );
		$date_to   = clone $now;
		$since     = $date_from->format( 'Y-m-d H:i:s' );

		$stats        = self::collect_stats( $since, $days );
		$period_range = $date_from->format( 'F j, Y' ) . ' - ' . $date_to->format( 'F j, Y' );
		$freq_label   = $is_monthly ? __( 'Monthly', 'mrm' ) : __( 'Weekly', 'mrm' );
		$custom       = ! empty( $settings['subject'] ) ? trim( $settings['subject'] ) : '';
		$subject      = $custom
			? '[Test] ' . str_replace( array( '{frequency}', '{period}' ), array( $freq_label, $period_range ), $custom )
			: sprintf(
				/* translators: %s: period label */
				__( '[Test] [Mail Mint] Your %s Usage Report', 'mrm' ),
				$period_label
			);
		$body    = self::build_email_body( $stats, $settings, true, $date_from, $date_to );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $recipients, $subject, $body, $headers );
	}

	/**
	 * Build and send the digest email. Fired by WP-Cron.
	 *
	 * @return void
	 */
	public static function send_digest() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args( $stored, self::default_settings() );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$is_monthly = 'monthly' === $settings['frequency'];

		$tz        = \wp_timezone();
		$now       = new \DateTime( 'now', $tz );
		$date_to   = clone $now;
		$days      = $is_monthly ? 30 : 7;
		$date_from = clone $now;
		$date_from->modify( "-{$days} days" );

		$since        = $date_from->format( 'Y-m-d H:i:s' );
		$stats        = self::collect_stats( $since, $days );
		$period_label = $is_monthly ? __( 'This Month', 'mrm' ) : __( 'This Week', 'mrm' );
		$period_range = $date_from->format( 'F j, Y' ) . ' - ' . $date_to->format( 'F j, Y' );
		$freq_label   = $is_monthly ? __( 'Monthly', 'mrm' ) : __( 'Weekly', 'mrm' );
		$custom       = ! empty( $settings['subject'] ) ? trim( $settings['subject'] ) : '';
		$subject      = $custom
			? str_replace( array( '{frequency}', '{period}' ), array( $freq_label, $period_range ), $custom )
			: sprintf(
				/* translators: %s: period label e.g. "This Week" */
				__( '[Mail Mint] Your %s Usage Report', 'mrm' ),
				$period_label
			);
		$body       = self::build_email_body( $stats, $settings, false, $date_from, $date_to );
		$recipients = self::resolve_recipients( $settings['recipients'] );
		$headers    = array( 'Content-Type: text/html; charset=UTF-8' );

		foreach ( $recipients as $to ) {
			wp_mail( $to, $subject, $body, $headers );
		}
	}

	// ──────────────────────────────────────────────────────────────────────
	// Data collection
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Return an array of validated recipient email addresses.
	 *
	 * @param string $raw Comma-separated email string from settings.
	 * @return string[]
	 */
	private static function resolve_recipients( $raw ) {
		if ( empty( trim( (string) $raw ) ) ) {
			return array( get_option( 'admin_email' ) );
		}

		$addresses = array_map( 'trim', explode( ',', $raw ) );
		$valid     = array_filter( $addresses, 'is_email' );

		return empty( $valid ) ? array( get_option( 'admin_email' ) ) : array_values( $valid );
	}

	/**
	 * Collect performance statistics from the database.
	 *
	 * @param string $since MySQL datetime string for the start of the current period.
	 * @param int    $days  Length of the period in days (used to compute the previous period).
	 * @return array Associative array of stats.
	 */
	private static function collect_stats( string $since, int $days = 7 ) {
		global $wpdb;

		// Previous-period boundary (same number of days immediately before $since).
		$since_prev_dt = new \DateTime( $since );
		$since_prev_dt->modify( "-{$days} days" );
		$since_previous = $since_prev_dt->format( 'Y-m-d H:i:s' );

		// ── Email Engagement ───────────────────────────────────────────────
		$emails_sent = 0;
		$open_rate   = 0.0;
		$click_rate  = 0.0;

		$email_meta_table = $wpdb->prefix . 'mint_email_meta';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$email_meta_table}'" ) ) {
			$emails_sent = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$email_meta_table}` WHERE created_at >= %s",
					$since
				)
			);
			$total_open  = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$email_meta_table}` WHERE is_open = 1 AND created_at >= %s",
					$since
				)
			);
			$total_click = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$email_meta_table}` WHERE is_click = 1 AND created_at >= %s",
					$since
				)
			);

			if ( $emails_sent > 0 ) {
				$open_rate  = round( ( $total_open / $emails_sent ) * 100, 1 );
				$click_rate = round( ( $total_click / $emails_sent ) * 100, 1 );
			}
		}

		// ── Audience ───────────────────────────────────────────────────────
		$new_contacts        = 0;
		$unsubscribes_bounces = 0;
		$total_contacts      = 0;

		$contacts_table = $wpdb->prefix . 'mint_contacts';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$contacts_table}'" ) ) {
			$new_contacts = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$contacts_table}` WHERE created_at >= %s AND status = 'subscribed'",
					$since
				)
			);
			$unsubscribes_bounces = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$contacts_table}` WHERE updated_at >= %s AND status IN ('unsubscribed','bounced')",
					$since
				)
			);
			$total_contacts = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM `{$contacts_table}` WHERE status = 'subscribed'"
			);
		}

		// ── Revenue (WooCommerce attributed) ──────────────────────────────
		$revenue_current  = 0.0;
		$revenue_previous = 0.0;

		if ( class_exists( 'WooCommerce' ) ) {
			$orders_table = $wpdb->prefix . 'posts';
			$meta_table   = $wpdb->prefix . 'postmeta';

			$revenue_current = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))), 0)
					FROM `{$orders_table}` p
					INNER JOIN `{$meta_table}` pm_total
						ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
					INNER JOIN `{$meta_table}` pm_mint
						ON p.ID = pm_mint.post_id AND pm_mint.meta_key = '_mint_revenue_attributed' AND pm_mint.meta_value = '1'
					WHERE p.post_type = 'shop_order'
					AND p.post_status IN ('wc-completed','wc-processing')
					AND p.post_date >= %s",
					$since
				)
			);

			$revenue_previous = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(CAST(pm_total.meta_value AS DECIMAL(10,2))), 0)
					FROM `{$orders_table}` p
					INNER JOIN `{$meta_table}` pm_total
						ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
					INNER JOIN `{$meta_table}` pm_mint
						ON p.ID = pm_mint.post_id AND pm_mint.meta_key = '_mint_revenue_attributed' AND pm_mint.meta_value = '1'
					WHERE p.post_type = 'shop_order'
					AND p.post_status IN ('wc-completed','wc-processing')
					AND p.post_date >= %s AND p.post_date < %s",
					$since_previous,
					$since
				)
			);
		}

		return compact(
			'emails_sent',
			'open_rate',
			'click_rate',
			'new_contacts',
			'unsubscribes_bounces',
			'total_contacts',
			'revenue_current',
			'revenue_previous'
		);
	}

	// ──────────────────────────────────────────────────────────────────────
	// Email builder
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Build the HTML body for the digest email.
	 *
	 * @param array          $stats    Stats from collect_stats().
	 * @param array          $settings Settings array.
	 * @param bool           $is_test  Whether this is a test send (adds banner).
	 * @param \DateTime|null $date_from Start of the report period.
	 * @param \DateTime|null $date_to   End of the report period.
	 * @return string HTML string.
	 */
	private static function build_email_body(
		array $stats,
		array $settings,
		bool $is_test = false,
		?\DateTime $date_from = null,
		?\DateTime $date_to = null
	) {
		$font       = "'Helvetica Neue', Helvetica, Arial, sans-serif";
		$primary    = '#573BFF';
		$dark       = '#1A1A2E';
		$body_text  = '#4A4A5A';
		$muted      = '#8A92A6';
		$stat_label = '#6B7280';
		$outer_bg   = '#EDECEA';
		$white      = '#FFFFFF';

		$site_name  = get_bloginfo( 'name' );
		$admin_url  = admin_url( 'admin.php?page=mrm-admin' );
		$admin_user = get_userdata( get_current_user_id() );
		$admin_name = ( $admin_user && $admin_user->display_name ) ? $admin_user->display_name : $site_name;

		$is_monthly = 'monthly' === $settings['frequency'];
		$title      = $is_monthly ? __( 'Monthly Summary', 'mrm' ) : __( 'Weekly Summary', 'mrm' );
		$freq_word  = $is_monthly ? __( 'monthly', 'mrm' ) : __( 'weekly', 'mrm' );

		$tz = \wp_timezone();
		if ( null === $date_from ) {
			$days      = $is_monthly ? 30 : 7;
			$date_from = new \DateTime( 'now', $tz );
			$date_from->modify( "-{$days} days" );
		}
		if ( null === $date_to ) {
			$date_to = new \DateTime( 'now', $tz );
		}

		$from_year = $date_from->format( 'Y' );
		$to_year   = $date_to->format( 'Y' );
		$date_long = ( $from_year === $to_year )
			? $date_from->format( 'M j' ) . ' – ' . $date_to->format( 'M j, Y' )
			: $date_from->format( 'M j, Y' ) . ' – ' . $date_to->format( 'M j, Y' );
		$date_short = $date_from->format( 'M. j' ) . ' – ' . $date_to->format( 'M. j' );

		// ── Revenue change label (WooCommerce only) ──────────────────────
		$revenue_fmt  = '';
		$revenue_note = '';
		if ( class_exists( 'WooCommerce' ) ) {
			$revenue_fmt = '$' . number_format( $stats['revenue_current'], 2 );
			if ( $stats['revenue_previous'] > 0 ) {
				$pct          = round( ( $stats['revenue_current'] - $stats['revenue_previous'] ) / $stats['revenue_previous'] * 100 );
				$arrow        = $pct >= 0 ? '&#8593;' : '&#8595;';
				$revenue_note = esc_html__( 'From email-attributed orders', 'mrm' ) . ' &middot; ' . $arrow . ' ' . abs( $pct ) . '% ' . esc_html__( 'vs last', 'mrm' ) . ' ' . esc_html( $freq_word );
			} else {
				$revenue_note = esc_html__( 'From email-attributed orders', 'mrm' );
			}
		}

		// ── Preheader ────────────────────────────────────────────────────
		$html  = '<!doctype html><html><head>';
		$html .= '<meta charset="UTF-8">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		$html .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
		$html .= '<title>' . esc_html( $site_name ) . ' &ndash; ' . esc_html( $title ) . '</title>';
		$html .= '<style type="text/css">';
		$html .= 'a{word-wrap:break-word;}table{border-collapse:collapse;}';
		$html .= 'h1,h2,h3,h4,h5,h6{display:block;margin:0;padding:0;}';
		$html .= 'img,a img{border:0;height:auto;outline:none;text-decoration:none;}';
		$html .= 'body,#bodyTable,#bodyCell{height:100%;margin:0;padding:0;width:100%;}';
		$html .= '#outlook a{padding:0;}';
		$html .= 'table{mso-table-lspace:0pt;mso-table-rspace:0pt;}';
		$html .= '.ReadMsgBody{width:100%;}.ExternalClass{width:100%;}';
		$html .= 'p,a,li,td,blockquote{mso-line-height-rule:exactly;}';
		$html .= 'a[href^="tel"],a[href^="sms"]{color:inherit;cursor:default;text-decoration:none;}';
		$html .= 'p,a,li,td,body,table,blockquote{-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;}';
		$html .= '.ExternalClass,.ExternalClass p,.ExternalClass td,.ExternalClass span,.ExternalClass font{line-height:100%;}';
		$html .= 'a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important;font-size:inherit!important;font-family:inherit!important;font-weight:inherit!important;line-height:inherit!important;}';
		$html .= '#bodyCell{padding-right:10px!important;padding-bottom:20px!important;padding-left:10px!important;}';
		$html .= '.emailContainer{max-width:600px;}';
		$html .= '.footerContent a{color:' . $primary . ';font-weight:500;text-decoration:none;}';
		$html .= '.button a{text-decoration:none;}';
		$html .= '@media screen and (min-width:768px){.emailContainer{width:600px!important;}}';
		$html .= '@media only screen and (max-width:480px){';
		$html .= 'body{width:100%!important;min-width:100%!important;}';
		$html .= '.contentContainer{padding-right:20px!important;padding-left:20px!important;}';
		$html .= 'h1{font-size:30px!important;line-height:36px!important;}';
		$html .= '.footerContent p{border-bottom:1px solid #DEDDDC;font-size:12px!important;padding-bottom:16px!important;}';
		$html .= '.mobileHide{display:none;visibility:hidden;}}';
		$html .= '</style></head>';
		$html .= '<body bgcolor="' . $outer_bg . '">';
		$html .= '<span style="color:' . $outer_bg . ';display:none;font-size:0px;height:0px;visibility:hidden;width:0px;">';
		$html .= esc_html__( 'We ran the numbers. Here are the latest stats...', 'mrm' );
		$html .= '</span>';
		$html .= '<center>';

		// ── Outer table ──────────────────────────────────────────────────
		$html .= '<table align="center" bgcolor="' . $outer_bg . '" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable">';
		$html .= '<tr><td align="center" style="padding-right:10px;padding-top:10px;padding-left:10px;" valign="top" id="bodyCell">';
		$html .= '<table align="center" border="0" cellpadding="0" cellspacing="0" style="max-width:600px;" width="100%" class="emailContainer">';
		$html .= '<tr><td align="center" valign="top">';

		// ── Body card ────────────────────────────────────────────────────
		$html .= '<table align="center" bgcolor="' . $white . '" border="0" cellpadding="0" cellspacing="0" style="background-color:' . $white . ';" width="100%">';
		$html .= '<tr><td align="center" class="contentContainer" style="padding-right:40px;padding-bottom:36px;padding-left:40px;" valign="top">';
		$html .= '<table border="0" cellpadding="0" cellspacing="0" width="100%">';

		// ── Brand logo ───────────────────────────────────────────────────
		$logo_url = defined( 'MRM_DIR_URL' ) ? MRM_DIR_URL . 'admin/assets/images/mail-mint-logo.svg' : '';
		$html    .= '<tr><td align="center" valign="top" style="padding-top:28px;padding-bottom:10px;">';
		if ( $logo_url ) {
			$html .= '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Mail Mint', 'mrm' ) . '" width="111" height="34" style="display:block;border:0;outline:none;text-decoration:none;max-width:111px;" />';
		} else {
			$html .= '<p style="color:' . $muted . ';font-family:' . $font . ';font-size:11px;font-weight:600;letter-spacing:2.5px;margin:0;text-align:center;text-transform:uppercase;">' . esc_html__( 'Mail Mint', 'mrm' ) . '</p>';
		}
		$html .= '</td></tr>';

		// ── Title ────────────────────────────────────────────────────────
		$html .= '<tr><td style="padding-bottom:6px;" valign="top">';
		$html .= '<h1 style="color:' . $dark . ';font-family:' . $font . ';font-size:40px;font-weight:800;line-height:48px;letter-spacing:-1px;margin:0;padding:0;text-align:center;">';
		$html .= esc_html( $title );
		$html .= '</h1></td></tr>';

		// ── Date range ───────────────────────────────────────────────────
		$html .= '<tr><td style="padding-bottom:14px;" valign="top">';
		$html .= '<p style="color:' . $muted . ';font-family:' . $font . ';font-size:15px;font-weight:400;line-height:22px;padding:0;margin:0;text-align:center;">';
		$html .= esc_html( $date_long );
		$html .= '</p></td></tr>';

		// ── Subtitle ─────────────────────────────────────────────────────
		$html .= '<tr><td style="padding-bottom:30px;" valign="top">';
		$html .= '<table align="center" border="0" cellpadding="0" cellspacing="0" style="max-width:400px;" width="100%">';
		$html .= '<tr><td>';
		$html .= '<p style="color:' . $body_text . ';font-family:' . $font . ';font-size:15px;font-weight:400;line-height:24px;padding:0;margin:0;text-align:center;">';
		/* translators: 1: bold admin name, 2: "weekly" or "monthly" */
		$html .= sprintf(
			esc_html__( 'Check out %1$s\'s %2$s performance summary and see how you compared to the previous %2$s.', 'mrm' ),
			'<strong>' . esc_html( $admin_name ) . '</strong>',
			esc_html( $freq_word )
		);
		$html .= '</p></td></tr></table></td></tr>';

		// ════════════════════════════════════════════════════════════════
		// SECTION: Email Engagement
		// ════════════════════════════════════════════════════════════════
		$html .= '<tr><td valign="top" style="padding-bottom:8px;">';
		$html .= '<h2 style="color:' . $dark . ';font-family:' . $font . ';font-size:22px;font-weight:700;line-height:28px;margin:0;padding:0;text-align:center;">';
		$html .= esc_html__( 'Email Engagement', 'mrm' );
		$html .= '</h2></td></tr>';

		$html .= '<tr><td valign="top" style="padding-bottom:22px;">';
		$html .= '<p style="color:' . $muted . ';font-family:' . $font . ';font-size:14px;font-weight:400;line-height:18px;padding:0;margin:0;text-align:center;">';
		$html .= esc_html( $date_short );
		$html .= '</p></td></tr>';

		$html .= '<tr><td align="center" valign="top" style="font-size:0;padding-bottom:24px;">';
		$html .= self::stat_block( number_format( $stats['emails_sent'] ), __( 'Emails Sent', 'mrm' ), $dark, $stat_label, $font );
		$html .= self::stat_block( $stats['open_rate'] . '%', __( 'Open Rate', 'mrm' ), $dark, $stat_label, $font );
		$html .= self::stat_block( $stats['click_rate'] . '%', __( 'Click Rate', 'mrm' ), $dark, $stat_label, $font );
		$html .= '</td></tr>';

		$html .= '<tr><td align="center" valign="top" style="padding-bottom:32px;">';
		$html .= self::cta_button( $admin_url, __( 'View Reporting Dashboard', 'mrm' ) );
		$html .= '</td></tr>';

		// ════════════════════════════════════════════════════════════════
		// SECTION: Audience
		// ════════════════════════════════════════════════════════════════
		$html .= '<tr><td valign="top" style="padding-bottom:8px;">';
		$html .= '<h2 style="color:' . $dark . ';font-family:' . $font . ';font-size:22px;font-weight:700;line-height:28px;margin:0;padding:0;text-align:center;">';
		$html .= esc_html__( 'Audience', 'mrm' );
		$html .= '</h2></td></tr>';

		$html .= '<tr><td valign="top" style="padding-bottom:22px;">';
		$html .= '<p style="color:' . $muted . ';font-family:' . $font . ';font-size:14px;font-weight:400;line-height:18px;padding:0;margin:0;text-align:center;">';
		$html .= esc_html( $date_short );
		$html .= '</p></td></tr>';

		$new_label = ( $stats['new_contacts'] > 0 ? '+' : '' ) . number_format( $stats['new_contacts'] );

		$html .= '<tr><td align="center" valign="top" style="font-size:0;padding-bottom:32px;">';
		$html .= self::stat_block( $new_label, __( 'New Contacts', 'mrm' ), $dark, $stat_label, $font );
		$html .= self::stat_block( number_format( $stats['unsubscribes_bounces'] ), __( 'Unsubscribes &amp; Bounces', 'mrm' ), $dark, $stat_label, $font );
		$html .= self::stat_block( number_format( $stats['total_contacts'] ), __( 'Total Audience', 'mrm' ), $dark, $stat_label, $font );
		$html .= '</td></tr>';

		// ════════════════════════════════════════════════════════════════
		// SECTION: Revenue Attributed (WooCommerce only)
		// ════════════════════════════════════════════════════════════════
		if ( class_exists( 'WooCommerce' ) ) {
			$html .= '<tr><td valign="top" style="padding-bottom:8px;">';
			$html .= '<h2 style="color:' . $dark . ';font-family:' . $font . ';font-size:22px;font-weight:700;line-height:28px;margin:0;padding:0;text-align:center;">';
			$html .= esc_html__( 'Revenue Attributed', 'mrm' );
			$html .= '</h2></td></tr>';

			$html .= '<tr><td valign="top" style="padding-bottom:22px;">';
			$html .= '<p style="color:' . $muted . ';font-family:' . $font . ';font-size:14px;font-weight:400;line-height:18px;padding:0;margin:0;text-align:center;">';
			$html .= esc_html( $date_short );
			$html .= '</p></td></tr>';

			$html .= '<tr><td align="center" valign="top" style="padding-bottom:6px;">';
			$html .= '<p style="color:' . $dark . ';font-family:' . $font . ';font-size:26px;font-weight:700;line-height:32px;margin:0;text-align:center;">';
			$html .= esc_html( $revenue_fmt );
			$html .= '</p></td></tr>';

			$html .= '<tr><td align="center" valign="top" style="padding-bottom:32px;">';
			$html .= '<p style="color:' . $stat_label . ';font-family:' . $font . ';font-size:14px;font-weight:400;line-height:20px;margin:0;text-align:center;">';
			$html .= $revenue_note;
			$html .= '</p></td></tr>';
		}

		// ════════════════════════════════════════════════════════════════
		// SECTION: Boost Your Stats
		// ════════════════════════════════════════════════════════════════
		$html .= '<tr><td valign="top" style="padding-bottom:10px;">';
		$html .= '<h2 style="color:' . $dark . ';font-family:' . $font . ';font-size:22px;font-weight:700;line-height:28px;margin:0;padding:0;text-align:center;">';
		$html .= esc_html__( 'Boost Your Stats', 'mrm' );
		$html .= '</h2></td></tr>';

		$html .= '<tr><td valign="top" style="padding-bottom:22px;">';
		$html .= '<p style="color:' . $body_text . ';font-family:' . $font . ';font-size:14px;font-weight:400;line-height:23px;padding:0;margin:0;text-align:center;">';
		$html .= sprintf(
			'%s <a href="%s" style="color:%s;font-weight:500;text-decoration:none;">%s</a> %s %s %s %s. %s',
			esc_html__( 'Visit our', 'mrm' ),
			esc_url( 'https://getwpfunnels.com/mail-mint-docs/' ),
			$primary,
			esc_html__( 'Guides &amp; Tutorials', 'mrm' ),
			esc_html__( 'to learn how to', 'mrm' ),
			esc_html__( 'grow your audience', 'mrm' ),
			esc_html__( 'and', 'mrm' ),
			esc_html__( 'maximize subscriber engagement', 'mrm' ),
			esc_html__( 'Then check out your Dashboard to get a more complete view of your account health.', 'mrm' )
		);
		$html .= '</p></td></tr>';

		$html .= '<tr><td align="center" valign="top" style="padding-bottom:4px;">';
		$html .= self::cta_button( $admin_url, __( 'Log in to Mail Mint', 'mrm' ) );
		$html .= '</td></tr>';

		// ── Close body table ─────────────────────────────────────────────
		$html .= '</table>';
		$html .= '</td></tr></table>';

		// ── Footer ───────────────────────────────────────────────────────
		$html .= '<table border="0" cellpadding="0" cellspacing="0" width="100%">';
		$html .= '<tr><td align="center" valign="top" class="footerContent" ';
		$html .= 'style="color:' . $muted . ';font-family:' . $font . ';font-size:12px;font-weight:400;line-height:22px;padding-top:28px;padding-bottom:40px;text-align:center;">';
		$html .= '<p style="color:' . $muted . ';font-family:' . $font . ';font-size:12px;margin:0 0 8px 0;text-align:center;">';
		/* translators: 1: site name, 2: date range */
		$html .= sprintf(
			esc_html__( 'This performance report email was sent from your site %1$s for period %2$s.', 'mrm' ),
			'<a href="' . esc_url( home_url() ) . '" style="color:' . $muted . ';font-weight:500;text-decoration:underline;">' . esc_html( $site_name ) . '</a>',
			esc_html( $date_long )
		);
		$html .= '</p>';
		$html .= '<a href="' . esc_url( admin_url( 'admin.php?page=mrm-admin#/settings/usage-report' ) ) . '" ';
		$html .= 'style="color:' . $primary . ';font-weight:500;text-decoration:none;display:inline-block;margin-top:4px;">';
		$html .= esc_html__( 'Turn off Notification', 'mrm' );
		$html .= '</a>';
		$html .= '</td></tr></table>';

		// ── Close outer tables ───────────────────────────────────────────
		$html .= '</td></tr></table>';
		$html .= '</td></tr></table>';
		$html .= '</center>';
		$html .= '</body></html>';

		return $html;
	}

	// ──────────────────────────────────────────────────────────────────────
	// Email component helpers
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Render an inline-block stat unit: large value + small label below.
	 *
	 * @param string $value      Stat value to display.
	 * @param string $label      Label below the value (may contain HTML entities).
	 * @param string $value_color Hex colour for the value.
	 * @param string $label_color Hex colour for the label.
	 * @param string $font       Font-family string.
	 * @return string HTML string.
	 */
	private static function stat_block( string $value, string $label, string $value_color, string $label_color, string $font ) {
		return '<div style="display:inline-block;max-width:160px;vertical-align:top;width:100%;">'
			. '<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">'
			. '<tr><td align="center" style="padding-bottom:5px;">'
			. '<p style="color:' . $value_color . ';font-family:' . $font . ';font-size:22px;font-weight:700;line-height:28px;margin:0;text-align:center;">'
			. esc_html( $value )
			. '</p></td></tr>'
			. '<tr><td align="center">'
			. '<p style="color:' . $label_color . ';font-family:' . $font . ';font-size:14px;line-height:20px;margin:0;text-align:center;">'
			. $label
			. '</p></td></tr>'
			. '</table></div>';
	}

	/**
	 * Render a full-width pill CTA button.
	 *
	 * @param string $url   Button destination URL.
	 * @param string $label Button text.
	 * @return string HTML anchor string.
	 */
	private static function cta_button( string $url, string $label ) {
		return '<table align="center" border="0" cellspacing="0" cellpadding="0">'
			. '<tr><td align="center">'
			. '<a href="' . esc_url( $url ) . '" style="background-color:#573BFF;border:1px solid #573BFF;border-radius:40px;color:#ffffff;display:inline-block;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:15px;font-weight:500;line-height:20px;padding:11px 28px;text-align:center;text-decoration:none;-webkit-text-size-adjust:none;">'
			. esc_html( $label )
			. '</a>'
			. '</td></tr></table>';
	}

	// ──────────────────────────────────────────────────────────────────────
	// Defaults
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	private static function default_settings() {
		return array(
			'enabled'    => true,
			'frequency'  => 'weekly',
			'week_day'   => 'monday',
			'month_day'  => 1,
			'subject'    => '{frequency} insights from Mail Mint - {period}',
			'recipients' => '',
		);
	}
}
