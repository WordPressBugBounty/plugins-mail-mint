<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin/Setup-Wizard
 */

namespace Mint\MRM\Internal\Admin;

/**
 * Manages Setup Wizard
 *
 * @package /app/Internal/Admin/Setup-Wizard
 * @since 1.0.0
 * @since 1.13.0 Added redirect_to_setup_wizard method
 */
class SetupWizard {

	public function __construct() {
        $this->init();
    }

	/**
	 * Initialize the class
	 * 
	 * @since 1.13.0
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'redirect_to_setup_wizard' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_filter( 'get_user_option_meta-box-order_dashboard', array( $this, 'place_dashboard_widget_first' ) );
		add_action( 'admin_post_mailmint_dismiss_setup_issues', array( $this, 'handle_dismiss_issues' ) );
	}

	/**
	 * Option holding the wizard status: pending | dismissed | hidden | completed.
	 * Missing on sites installed before it existed.
	 *
	 * @since 1.32.0
	 */
	const STATUS_OPTION = 'mailmint_setup_wizard_status';

	/**
	 * Current wizard status.
	 *
	 * @return string
	 * @since 1.32.0
	 */
	public static function get_status() {
		return (string) get_option( self::STATUS_OPTION, '' );
	}

	/**
	 * Whether setup was started on this site but not finished or hidden.
	 *
	 * @return bool
	 * @since 1.32.0
	 */
	public static function is_unfinished() {
		return in_array( self::get_status(), array( 'pending', 'dismissed' ), true );
	}

	/**
	 * Short progress list for the setup reminder: business details, sender
	 * and email delivery, each done or not.
	 *
	 * @return array[] List of { key, label, done }.
	 * @since 1.32.0
	 */
	public static function get_progress() {
		$labels = array(
			'business' => __( 'Business details', 'mrm' ),
			'sender'   => __( 'Sender', 'mrm' ),
			'delivery' => __( 'Email delivery', 'mrm' ),
		);
		$out    = array();
		foreach ( self::get_checks() as $check ) {
			if ( isset( $labels[ $check['key'] ] ) ) {
				$out[] = array(
					'key'   => $check['key'],
					'label' => $labels[ $check['key'] ],
					'done'  => $check['done'],
				);
			}
		}
		return $out;
	}

	/**
	 * Option: issue keys the user chose to stop being reminded about.
	 *
	 * @since 1.32.0
	 */
	const ISSUES_DISMISSED_OPTION = 'mailmint_setup_issues_dismissed';

	/**
	 * Option: "yes" once the user confirmed a test email sent without an SMTP
	 * service arrived, i.e. the host's own mail service works for them.
	 *
	 * @since 1.32.0
	 */
	const HOST_MAIL_CONFIRMED_OPTION = 'mailmint_host_mail_confirmed';

	/**
	 * Free webmail domains. Their DMARC policies reject or spam-folder mail
	 * sent in their name from other servers.
	 *
	 * @since 1.32.0
	 */
	const FREE_MAIL_DOMAINS = array(
		'gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'hotmail.com', 'outlook.com',
		'live.com', 'msn.com', 'aol.com', 'icloud.com', 'me.com', 'mac.com', 'protonmail.com',
		'proton.me', 'gmx.com', 'gmx.net', 'mail.com', 'yandex.com', 'zoho.com',
	);

	/**
	 * Mail can be delivered: an SMTP plugin or mailer is set up, or the user
	 * confirmed a test email sent through the host arrived.
	 *
	 * @return bool
	 * @since 1.32.0
	 */
	public static function delivery_ok() {
		$smtp = SendingHealth::check_smtp();
		if ( isset( $smtp['status'] ) && 'pass' === $smtp['status'] ) {
			return true;
		}
		return 'yes' === get_option( self::HOST_MAIL_CONFIRMED_OPTION, '' );
	}

	/**
	 * What Mail Mint needs to deliver email, as a checklist: business name
	 * and postal address, a sender on the site's own domain, and SMTP (or a
	 * confirmed host mail test). Stuck background jobs are added only while
	 * they fail. Titles say what to do when open and what's in place when done.
	 *
	 * dismiss_key includes the sender domain, so dismissing a warning for one
	 * address doesn't hide a new one for another.
	 *
	 * @return array[] List of { key, dismiss_key, done, severity, title, summary, detail, action_label, action_url, help_url }.
	 * @since 1.32.0
	 */
	public static function get_checks() {
		$app_url = admin_url( 'admin.php?page=mrm-admin#' );
		$doc_url = 'https://getwpfunnels.com/docs/troubleshooting-for-mail-mint/email-deliverability-best-practices-for-mail-mint/';
		$checks  = array();

		$check = function ( $data ) {
			return array_merge(
				array(
					'dismiss_key'  => $data['key'],
					'done'         => false,
					'severity'     => 'warning',
					'summary'      => '',
					'detail'       => '',
					'action_label' => '',
					'action_url'   => '',
					'help_url'     => '',
				),
				$data
			);
		};

		// Business name and postal address.
		$business = get_option( '_mrm_business_basic_info_setting', array() );
		$business = is_array( $business ) ? $business : array();
		$address  = isset( $business['business_address'] ) ? maybe_unserialize( $business['business_address'] ) : array();
		$address  = is_array( $address ) ? $address : array();
		$filled   = function ( $value ) {
			return '' !== trim( (string) $value );
		};
		$has_name    = $filled( $business['business_name'] ?? '' );
		$has_address = $filled( $address['address_line_1'] ?? '' ) && $filled( $address['city'] ?? '' ) && $filled( $address['country'] ?? '' );
		$done        = $has_name && $has_address;

		$checks[] = $check(
			array(
				'key'          => 'business',
				'done'         => $done,
				'title'        => $done ? __( 'Business details added', 'mrm' ) : ( $has_name ? __( 'Add your business address', 'mrm' ) : __( 'Add your business details', 'mrm' ) ),
				'summary'      => __( 'Anti-spam laws require it in every email.', 'mrm' ),
				'detail'       => __( 'Anti-spam laws (CAN-SPAM, GDPR) require a business name and postal address in every marketing email. Without them, emails are more likely to be marked as spam.', 'mrm' ),
				'action_label' => __( 'Add address', 'mrm' ),
				'action_url'   => $app_url . '/settings/business-info',
			)
		);

		// Sender address.
		$email  = get_option( '_mrm_email_settings', array() );
		$from   = is_array( $email ) && ! empty( $email['from_email'] ) ? strtolower( trim( $email['from_email'] ) ) : '';
		$domain = $from && is_email( $from ) ? substr( strrchr( $from, '@' ), 1 ) : '';
		$host   = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		$sender = array(
			'key'          => 'sender',
			'action_label' => __( 'Fix sender', 'mrm' ),
			'action_url'   => $app_url . '/settings/email-settings',
		);

		if ( ! $domain ) {
			$sender += array(
				'title'   => __( 'Set your from email', 'mrm' ),
				'summary' => __( 'Emails go out with WordPress defaults.', 'mrm' ),
				'detail'  => __( 'Without a from name and email of your own, emails go out with WordPress defaults that subscribers won’t recognise.', 'mrm' ),
			);
		} elseif ( in_array( $domain, self::FREE_MAIL_DOMAINS, true ) ) {
			$sender += array(
				'dismiss_key' => 'sender_free:' . $domain,
				'title'       => __( 'Use a sender on your own domain', 'mrm' ),
				// translators: %s: email domain.
				'summary'     => sprintf( __( '@%s mail sent from your site often bounces.', 'mrm' ), $domain ),
				// translators: %s: email domain.
				'detail'      => sprintf( __( '@%s only lets its own servers send in its name, so emails from your site are often rejected or put in spam. Use an address on your own domain.', 'mrm' ), $domain ),
				'help_url'    => $doc_url,
			);
		} elseif ( $host && $domain !== $host && substr( $host, -strlen( '.' . $domain ) ) !== '.' . $domain ) {
			$sender += array(
				'dismiss_key' => 'sender_domain:' . $domain,
				'title'       => __( 'Check your sender domain', 'mrm' ),
				// translators: %s: email domain.
				'summary'     => sprintf( __( 'Mail for @%s may be marked as spam.', 'mrm' ), $domain ),
				// translators: 1: site domain, 2: email domain.
				'detail'      => sprintf( __( 'Your site is on %1$s but emails come from @%2$s. Make sure your mail service is allowed to send for @%2$s, or emails may land in spam.', 'mrm' ), $host, $domain ),
				'help_url'    => $doc_url,
			);
		} else {
			$sender += array(
				'done'  => true,
				'title' => __( 'Sender address set', 'mrm' ),
			);
		}
		$checks[] = $check( $sender );

		// Delivery.
		$smtp      = SendingHealth::check_smtp();
		$smtp_ok   = isset( $smtp['status'] ) && 'pass' === $smtp['status'];
		$host_ok   = 'yes' === get_option( self::HOST_MAIL_CONFIRMED_OPTION, '' );
		$checks[]  = $check(
			array(
				'key'          => 'delivery',
				'done'         => $smtp_ok || $host_ok,
				'title'        => $smtp_ok ? __( 'SMTP service connected', 'mrm' ) : ( $host_ok ? __( 'Host mail confirmed', 'mrm' ) : __( 'Connect an SMTP service', 'mrm' ) ),
				'summary'      => __( 'Host mail often lands in spam.', 'mrm' ),
				'detail'       => __( 'WordPress sends through your host’s mail, which usually isn’t authenticated, so emails can land in spam or never arrive. Connect SMTP, or send a test to confirm your host delivers.', 'mrm' ),
				'action_label' => __( 'Set up SMTP', 'mrm' ),
				'action_url'   => $app_url . '/settings/email-settings',
				'help_url'     => $doc_url,
			)
		);

		// Background jobs: only listed while they fail.
		$jobs = SendingHealth::check_background_jobs();
		if ( isset( $jobs['status'] ) && 'pass' !== $jobs['status'] ) {
			$checks[] = $check(
				array(
					'key'      => 'background_jobs',
					'severity' => 'fail' === $jobs['status'] ? 'error' : 'warning',
					'title'    => __( 'Fix background sending', 'mrm' ),
					'summary'  => __( 'Scheduled emails may not go out.', 'mrm' ),
					'detail'   => trim( ( $jobs['details'] ?? '' ) . ' ' . ( $jobs['recommendation'] ?? '' ) ),
				)
			);
		}

		return $checks;
	}

	/**
	 * Whether the user hid this open check.
	 *
	 * @param array $check Check from get_checks().
	 * @return bool
	 * @since 1.32.0
	 */
	public static function is_dismissed( array $check ) {
		return in_array( $check['dismiss_key'], (array) get_option( self::ISSUES_DISMISSED_OPTION, array() ), true );
	}

	/**
	 * Open checks as issues (key = dismiss_key), without dismissed ones
	 * unless asked.
	 *
	 * @param bool $include_dismissed Also return dismissed issues.
	 * @return array[] List of { key, severity, title, summary, detail, action_label, action_url, help_url }.
	 * @since 1.32.0
	 */
	public static function get_issues( $include_dismissed = false ) {
		$issues = array();
		foreach ( self::get_checks() as $check ) {
			if ( $check['done'] || ( ! $include_dismissed && self::is_dismissed( $check ) ) ) {
				continue;
			}
			$issue        = $check;
			$issue['key'] = $check['dismiss_key'];
			unset( $issue['dismiss_key'], $issue['done'] );
			$issues[] = $issue;
		}
		return $issues;
	}

	/**
	 * Option: "yes" once the Getting Started page is retired (everything
	 * done or hidden, or the user hid the page). Never shown again after.
	 *
	 * @since 1.32.0
	 */
	const GETTING_STARTED_RETIRED_OPTION = 'mailmint_getting_started_retired';

	/**
	 * Whether the Getting Started page (and its menu item) applies.
	 *
	 * Only for admins on sites installed with the setup wizard (old sites have
	 * no wizard status), and only while something on it is still open:
	 * the wizard, an undismissed setup check, or the first-steps checklist.
	 * When nothing is left, the page retires for good.
	 *
	 * @return bool
	 * @since 1.32.0
	 */
	public static function is_getting_started_active() {
		static $active = null;
		if ( null !== $active ) {
			return $active;
		}

		if ( ! current_user_can( 'manage_options' ) || '' === self::get_status()
			|| 'yes' === get_option( self::GETTING_STARTED_RETIRED_OPTION, '' ) ) {
			$active = false;
			return $active;
		}

		$active = self::is_unfinished();

		if ( ! $active ) {
			foreach ( self::get_checks() as $check ) {
				if ( ! $check['done'] && ! self::is_dismissed( $check ) ) {
					$active = true;
					break;
				}
			}
		}

		if ( ! $active ) {
			$onboarding = \Mint\MRM\DataBase\Models\DashboardModel::get_onboarding_stats();
			$active     = ! empty( $onboarding['show'] ) && (int) $onboarding['completed'] < (int) $onboarding['total'];
		}

		if ( ! $active ) {
			self::retire_getting_started();
		}

		return $active;
	}

	/**
	 * Retire the Getting Started page for good.
	 *
	 * @since 1.32.0
	 */
	public static function retire_getting_started() {
		update_option( self::GETTING_STARTED_RETIRED_OPTION, 'yes', false );
	}

	/**
	 * Stop reminding about these issues.
	 *
	 * @param string[] $keys Issue keys.
	 * @since 1.32.0
	 */
	public static function dismiss_issues( array $keys ) {
		$dismissed = (array) get_option( self::ISSUES_DISMISSED_OPTION, array() );
		$dismissed = array_values( array_unique( array_merge( $dismissed, array_map( 'sanitize_text_field', $keys ) ) ) );
		update_option( self::ISSUES_DISMISSED_OPTION, $dismissed, false );
	}

	/**
	 * What the WordPress dashboard box should show: "setup" while the wizard
	 * is unfinished, "issues" when something still blocks sending, or "" for
	 * nothing.
	 *
	 * @return string
	 * @since 1.32.0
	 */
	private static function dashboard_widget_mode() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		if ( self::is_unfinished() ) {
			return 'setup';
		}
		// Sites from before the wizard status existed keep a quiet dashboard.
		if ( '' === self::get_status() ) {
			return '';
		}
		return self::get_issues() ? 'issues' : '';
	}

	/**
	 * Add the "Mail Mint" box to the top of the WordPress dashboard.
	 *
	 * @since 1.32.0
	 */
	public function add_dashboard_widget() {
		$mode = self::dashboard_widget_mode();
		if ( ! $mode ) {
			return;
		}

		$title = 'setup' === $mode ? __( 'Mail Mint Setup', 'mrm' ) : __( 'Mail Mint Email Delivery', 'mrm' );
		wp_add_dashboard_widget( 'mailmint_setup', $title, array( $this, 'render_dashboard_widget' ) );

		// Move it to the top of the first column, like WooCommerce Setup.
		global $wp_meta_boxes;
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['mailmint_setup'] ) ) {
			$core   = $wp_meta_boxes['dashboard']['normal']['core'];
			$widget = array( 'mailmint_setup' => $core['mailmint_setup'] );
			unset( $core['mailmint_setup'] );
			$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $widget, $core ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Users with a saved dashboard layout get new boxes appended to the end of
	 * a column. Put the box first in the left column instead, unless the user
	 * has already placed it somewhere themselves.
	 *
	 * @param array|false $order Saved box order per column (comma-separated ids).
	 * @return array|false
	 * @since 1.32.0
	 */
	public function place_dashboard_widget_first( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}

		foreach ( $order as $ids ) {
			if ( in_array( 'mailmint_setup', explode( ',', (string) $ids ), true ) ) {
				return $order;
			}
		}

		$order['normal'] = empty( $order['normal'] ) ? 'mailmint_setup' : 'mailmint_setup,' . $order['normal'];
		return $order;
	}

	/**
	 * Handle "Dismiss these warnings" from the WordPress dashboard box.
	 *
	 * @since 1.32.0
	 */
	public function handle_dismiss_issues() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'mrm' ) );
		}
		check_admin_referer( 'mailmint_dismiss_setup_issues' );

		self::dismiss_issues( wp_list_pluck( self::get_issues(), 'key' ) );
		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * Render the WordPress dashboard box, WooCommerce Setup style: progress
	 * pill, the one thing to do next, a single button.
	 *
	 * @since 1.32.0
	 */
	public function render_dashboard_widget() {
		$mode   = self::dashboard_widget_mode();
		$checks = self::get_checks();
		$total  = count( $checks );
		$done   = count( array_filter( wp_list_pluck( $checks, 'done' ) ) );
		$open   = array_values(
			array_filter(
				$checks,
				function ( $c ) {
					return ! $c['done'] && ! self::is_dismissed( $c );
				}
			)
		);

		if ( 'setup' === $mode ) {
			$title  = __( 'Finish setting up Mail Mint', 'mrm' );
			$text   = __( 'Add your business details and sender so your emails can be sent.', 'mrm' );
			$button = __( 'Continue setup', 'mrm' );
			$url    = admin_url( 'admin.php?page=mrm-admin#/setup-wizard' );
		} else {
			$next   = $open[0];
			$title  = $next['title'];
			$text   = $next['summary'];
			$button = $next['action_label'] ? $next['action_label'] : __( 'Open Mail Mint', 'mrm' );
			$url    = $next['action_url'] ? $next['action_url'] : admin_url( 'admin.php?page=mrm-admin' );
		}

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=mailmint_dismiss_setup_issues' ), 'mailmint_dismiss_setup_issues' );
		$more        = 'issues' === $mode ? count( $open ) - 1 : 0;

		// Progress ring in the pill: circumference of r=6 is ~37.7.
		$circumference = 37.7;
		$offset        = $circumference * ( 1 - ( $total ? $done / $total : 0 ) );
		?>
		<style>
			#mailmint_setup .inside{padding:0;margin:0}
			#mailmint_setup .mmw-wpb{display:flex;gap:16px;padding:12px 12px 14px}
			#mailmint_setup .mmw-wpb__main{flex:1;min-width:0}
			#mailmint_setup .mmw-wpb__pill{display:inline-flex;align-items:center;gap:6px;margin:0 0 12px;padding:3px 10px 3px 6px;border:1px solid #c3c4c7;border-radius:999px;font-size:12px;color:#50575e}
			#mailmint_setup .mmw-wpb__title{margin:0 0 4px;font-size:14px;font-weight:600;color:#1d2327}
			#mailmint_setup .mmw-wpb__text{margin:0 0 14px;color:#50575e}
			#mailmint_setup .mmw-wpb__art{flex:0 0 96px;align-self:center}
			#mailmint_setup .mmw-wpb .button-primary{background:#573bff;border-color:#573bff}
			#mailmint_setup .mmw-wpb .button-primary:hover,#mailmint_setup .mmw-wpb .button-primary:focus{background:#4c25a5;border-color:#4c25a5}
			#mailmint_setup .mmw-wpb__foot{display:flex;justify-content:space-between;gap:12px;margin:0;padding:8px 12px;border-top:1px solid #f0f0f1;font-size:12px}
			#mailmint_setup .mmw-wpb__foot .mmw-wpb__dismiss{color:#8c8f94}
		</style>
		<div class="mmw-wpb">
			<div class="mmw-wpb__main">
				<p class="mmw-wpb__pill">
					<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
						<circle cx="8" cy="8" r="6" fill="none" stroke="#dcdcde" stroke-width="2"/>
						<circle cx="8" cy="8" r="6" fill="none" stroke="#573bff" stroke-width="2" stroke-linecap="round"
							stroke-dasharray="<?php echo esc_attr( $circumference ); ?>" stroke-dashoffset="<?php echo esc_attr( $offset ); ?>" transform="rotate(-90 8 8)"/>
					</svg>
					<?php
					// translators: 1: checks done, 2: total checks.
					echo esc_html( sprintf( __( '%1$d of %2$d done', 'mrm' ), $done, $total ) );
					?>
				</p>
				<p class="mmw-wpb__title"><?php echo esc_html( $title ); ?></p>
				<p class="mmw-wpb__text"><?php echo esc_html( $text ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button ); ?></a>
			</div>
			<div class="mmw-wpb__art"><?php echo self::illustration_svg( 'setup' !== $mode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></div>
		</div>
		<p class="mmw-wpb__foot">
			<?php if ( $more > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mrm-admin' ) ); ?>">
					<?php
					// translators: %d: number of other open checks.
					echo esc_html( sprintf( _n( '%d more to check in Mail Mint', '%d more to check in Mail Mint', $more, 'mrm' ), $more ) );
					?>
				</a>
			<?php else : ?>
				<span></span>
			<?php endif; ?>
			<?php if ( 'issues' === $mode ) : ?>
				<a class="mmw-wpb__dismiss" href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'mrm' ); ?></a>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Small flat illustration: a letter coming out of an envelope, with a
	 * warning badge when something needs attention.
	 *
	 * @param bool $warning Show the warning badge.
	 * @return string SVG markup.
	 * @since 1.32.0
	 */
	public static function illustration_svg( $warning = true ) {
		$badge = $warning
			? '<circle cx="76" cy="60" r="11" fill="#f79009"/><path d="M76 54v7M76 65v.5" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>'
			: '<circle cx="76" cy="60" r="11" fill="#573bff"/><path d="m71 60 3.5 3.5L81 57" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>';
		return '<svg width="96" height="80" viewBox="0 0 96 80" fill="none" aria-hidden="true">'
			. '<circle cx="24" cy="24" r="16" fill="#efebff"/>'
			. '<rect x="18" y="30" width="60" height="40" rx="5" fill="#e4defe"/>'
			. '<rect x="28" y="10" width="40" height="46" rx="3" fill="#fff" stroke="#d9d2ff"/>'
			. '<rect x="34" y="18" width="20" height="3" rx="1.5" fill="#c9c1ff"/>'
			. '<rect x="34" y="25" width="28" height="3" rx="1.5" fill="#e4e7ec"/>'
			. '<rect x="34" y="31" width="24" height="3" rx="1.5" fill="#e4e7ec"/>'
			. '<rect x="34" y="39" width="14" height="6" rx="2" fill="#573bff"/>'
			. '<path d="M18 36 48 54l30-18v29a5 5 0 0 1-5 5H23a5 5 0 0 1-5-5V36Z" fill="#d9d2ff"/>'
			. $badge
			. '</svg>';
	}

	/**
	 * Redirect to setup wizard
	 * 
	 * @since 1.13.0
	 */
	public function redirect_to_setup_wizard() {
		// Setup wizard redirect.
        if (get_transient('mailmint_show_setup_wizard')) {
            $do_redirect = true;

            // On these pages, or during these events, postpone the redirect.
            if (wp_doing_ajax() || is_network_admin() || !current_user_can('manage_options')) {
                $do_redirect = false;
            }

            if ( $do_redirect ) {
				update_option('mail_mint_hide_database_update_notice', 'yes');
                delete_transient('mailmint_show_setup_wizard');
                $url = admin_url('admin.php?page=mrm-admin#/setup-wizard');
                wp_safe_redirect(  wp_sanitize_redirect( esc_url_raw( $url ) ) );
                exit;
            }
        }
	}
}