<?php
/**
 * Mail Mint
 *
 * @package /app/Internal/Admin
 */

namespace Mint\MRM\Internal\Admin;

use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use MintMail\App\Internal\Automation\AutomationModel;
use MRM\Common\MrmCommon;

/**
 * Small boxes on the WordPress dashboard that point the user to the next
 * useful thing in Mail Mint: send a draft campaign, import contacts, turn on
 * a draft automation, set up abandoned cart recovery.
 *
 * Each box is its own dashboard widget so users can move, collapse or hide
 * it with Screen Options. "Remind me later" hides a box for the current user
 * for SNOOZE_DAYS days.
 *
 * @package /app/Internal/Admin
 * @since 1.32.0
 */
class DashboardNudges {

	/**
	 * User meta holding snoozed boxes: nudge key => timestamp it was snoozed.
	 *
	 * @since 1.32.0
	 */
	const SNOOZE_META = 'mailmint_dashboard_nudges_snoozed';

	/**
	 * Days a snoozed box stays hidden.
	 *
	 * @since 1.32.0
	 */
	const SNOOZE_DAYS = 14;

	/**
	 * Prefix of every widget id.
	 *
	 * @since 1.32.0
	 */
	const WIDGET_PREFIX = 'mailmint_nudge_';

	/**
	 * Triggers that make an automation an abandoned cart automation.
	 *
	 * @since 1.32.0
	 */
	const CART_TRIGGERS = array( 'wc_abandoned_cart', 'wc_abandoned_cart_lost', 'wc_abandoned_cart_recovered' );

	/**
	 * Nudges resolved for this request, keyed by nudge key.
	 *
	 * @var array|null
	 */
	private $nudges = null;

	/**
	 * Whether the shared styles were printed already.
	 *
	 * @var bool
	 */
	private $styles_printed = false;

	/**
	 * Hook in.
	 *
	 * @since 1.32.0
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'admin_post_mailmint_snooze_dashboard_nudge', array( $this, 'handle_snooze' ) );
	}

	/**
	 * Register one dashboard widget per nudge that applies.
	 *
	 * @since 1.32.0
	 */
	public function add_dashboard_widgets() {
		foreach ( $this->get_nudges() as $key => $nudge ) {
			wp_add_dashboard_widget(
				self::WIDGET_PREFIX . $key,
				$nudge['box_title'],
				array( $this, 'render_widget' ),
				null,
				array( 'key' => $key )
			);
		}
	}

	/**
	 * Nudges to show the current user, keyed by nudge key.
	 *
	 * @return array
	 * @since 1.32.0
	 */
	public function get_nudges() {
		if ( null !== $this->nudges ) {
			return $this->nudges;
		}

		$this->nudges = array();

		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->nudges;
		}

		// The setup box comes first; don't pile more boxes on top of it.
		if ( class_exists( SetupWizard::class ) && SetupWizard::is_unfinished() ) {
			return $this->nudges;
		}

		$candidates = array(
			'contacts'       => array( $this, 'contacts_nudge' ),
			'campaign'       => array( $this, 'campaign_nudge' ),
			'automation'     => array( $this, 'automation_nudge' ),
			'abandoned_cart' => array( $this, 'abandoned_cart_nudge' ),
		);

		foreach ( $candidates as $key => $callback ) {
			if ( $this->is_snoozed( $key ) ) {
				continue;
			}
			$nudge = call_user_func( $callback );
			if ( $nudge ) {
				$this->nudges[ $key ] = $nudge;
			}
		}

		/**
		 * Filter the Mail Mint nudges shown on the WordPress dashboard.
		 *
		 * @param array $nudges Nudge key => { box_title, title, text, button, url }.
		 * @since 1.32.0
		 */
		$this->nudges = (array) apply_filters( 'mailmint_dashboard_nudges', $this->nudges );

		return $this->nudges;
	}

	/**
	 * No contacts yet: import some.
	 *
	 * @return array|null
	 * @since 1.32.0
	 */
	private function contacts_nudge() {
		if ( ContactModel::get_contacts_count() > 0 ) {
			return null;
		}

		return array(
			'box_title' => __( 'Mail Mint: Add Your Contacts', 'mrm' ),
			'title'     => __( 'Your audience is empty', 'mrm' ),
			'text'      => __( 'Import contacts from a CSV file, WordPress users, WooCommerce customers or another email tool, and you are ready to send your first email.', 'mrm' ),
			'button'    => __( 'Import contacts', 'mrm' ),
			'url'       => self::admin_route( '/contacts/import' ),
		);
	}

	/**
	 * A campaign is still a draft: finish and send it.
	 *
	 * @return array|null
	 * @since 1.32.0
	 */
	private function campaign_nudge() {
		$drafts = CampaignModel::get_draft_campaigns_summary();
		if ( empty( $drafts['count'] ) ) {
			return null;
		}

		$box_title = __( 'Mail Mint: Draft Campaigns', 'mrm' );

		if ( 1 === $drafts['count'] && ! empty( $drafts['latest'] ) ) {
			$latest = $drafts['latest'];
			$name   = '' !== trim( (string) $latest['title'] ) ? $latest['title'] : __( 'Untitled campaign', 'mrm' );
			return array(
				'box_title' => $box_title,
				// translators: %s: campaign title.
				'title'     => sprintf( __( '"%s" is ready when you are', 'mrm' ), $name ),
				'text'      => __( 'Your campaign is saved as a draft and no one has received it yet. Finish it and send it now or schedule it for later.', 'mrm' ),
				'button'    => __( 'Finish campaign', 'mrm' ),
				'url'       => self::campaign_edit_url( $latest ),
			);
		}

		return array(
			'box_title' => $box_title,
			// translators: %d: number of draft campaigns.
			'title'     => sprintf( _n( 'You have %d campaign in draft', 'You have %d campaigns in draft', $drafts['count'], 'mrm' ), $drafts['count'] ),
			'text'      => __( 'None of your contacts have received them yet. Finish them and send them now or schedule them for later.', 'mrm' ),
			'button'    => __( 'Review drafts', 'mrm' ),
			'url'       => self::admin_route( '/campaigns' ),
		);
	}

	/**
	 * An automation is still a draft: turn it on.
	 *
	 * @return array|null
	 * @since 1.32.0
	 */
	private function automation_nudge() {
		if ( ! class_exists( AutomationModel::class ) ) {
			return null;
		}

		$drafts = AutomationModel::get_draft_automations_summary();
		if ( empty( $drafts['count'] ) ) {
			return null;
		}

		$box_title = __( 'Mail Mint: Draft Automations', 'mrm' );

		if ( 1 === $drafts['count'] && ! empty( $drafts['latest'] ) ) {
			$latest = $drafts['latest'];
			$name   = '' !== trim( (string) $latest['name'] ) ? $latest['name'] : __( 'Untitled automation', 'mrm' );
			return array(
				'box_title' => $box_title,
				// translators: %s: automation name.
				'title'     => sprintf( __( '"%s" is not running yet', 'mrm' ), $name ),
				'text'      => __( 'This automation is still a draft, so it won\'t send anything. Review the steps and activate it to start working for you.', 'mrm' ),
				'button'    => __( 'Activate automation', 'mrm' ),
				'url'       => admin_url( 'admin.php?page=mint-mail-automation-editor#/id=' . absint( $latest['id'] ) ),
			);
		}

		return array(
			'box_title' => $box_title,
			// translators: %d: number of draft automations.
			'title'     => sprintf( _n( '%d automation is not running yet', '%d automations are not running yet', $drafts['count'], 'mrm' ), $drafts['count'] ),
			'text'      => __( 'Draft automations won\'t send anything. Review them and activate the ones you want to start working for you.', 'mrm' ),
			'button'    => __( 'Review automations', 'mrm' ),
			'url'       => self::admin_route( '/automations/' ),
		);
	}

	/**
	 * WooCommerce store on Pro: turn on cart tracking, then build a recovery
	 * automation for it.
	 *
	 * @return array|null
	 * @since 1.32.0
	 */
	private function abandoned_cart_nudge() {
		if ( ! MrmCommon::is_wc_active() || ! MrmCommon::is_mailmint_pro_active() || ! class_exists( CartCommon::class ) ) {
			return null;
		}

		$box_title = __( 'Mail Mint: Abandoned Cart Recovery', 'mrm' );
		$settings  = CartCommon::get_abandoned_cart_settings();

		if ( empty( $settings['enable'] ) ) {
			return array(
				'box_title' => $box_title,
				'title'     => __( 'Win back customers who leave without buying', 'mrm' ),
				'text'      => __( 'Turn on abandoned cart tracking, then create an automation that emails shoppers a reminder to complete their order.', 'mrm' ),
				'button'    => __( 'Enable abandoned cart', 'mrm' ),
				'url'       => self::admin_route( '/settings/abandoned-cart' ),
			);
		}

		if ( ! class_exists( AutomationModel::class ) || AutomationModel::count_by_triggers( self::CART_TRIGGERS ) > 0 ) {
			return null;
		}

		return array(
			'box_title' => $box_title,
			'title'     => __( 'Carts are tracked, but no one is reminded', 'mrm' ),
			'text'      => __( 'Abandoned cart tracking is on, but no automation uses it yet. Create a recovery automation so shoppers get a reminder to complete their order.', 'mrm' ),
			'button'    => __( 'Create recovery automation', 'mrm' ),
			'url'       => self::admin_route( '/automations/recipe' ),
		);
	}

	/**
	 * Render one nudge box.
	 *
	 * @param mixed $post   Unused, always empty on the dashboard.
	 * @param array $widget Widget definition; args.key names the nudge.
	 * @since 1.32.0
	 */
	public function render_widget( $post, $widget ) {
		$key    = isset( $widget['args']['key'] ) ? $widget['args']['key'] : '';
		$nudges = $this->get_nudges();
		if ( empty( $nudges[ $key ] ) ) {
			return;
		}

		$nudge      = $nudges[ $key ];
		$snooze_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'mailmint_snooze_dashboard_nudge',
					'nudge'  => $key,
				),
				admin_url( 'admin-post.php' )
			),
			'mailmint_snooze_dashboard_nudge_' . $key
		);

		$this->print_styles();
		?>
		<div class="mmw-nudge">
			<div class="mmw-nudge__icon"><?php echo self::icon_svg( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></div>
			<div class="mmw-nudge__main">
				<p class="mmw-nudge__title"><?php echo esc_html( $nudge['title'] ); ?></p>
				<p class="mmw-nudge__text"><?php echo esc_html( $nudge['text'] ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( $nudge['url'] ); ?>"><?php echo esc_html( $nudge['button'] ); ?></a>
			</div>
		</div>
		<p class="mmw-nudge__foot">
			<a class="mmw-nudge__snooze" href="<?php echo esc_url( $snooze_url ); ?>"><?php esc_html_e( 'Remind me later', 'mrm' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Handle "Remind me later".
	 *
	 * @since 1.32.0
	 */
	public function handle_snooze() {
		$key = isset( $_GET['nudge'] ) ? sanitize_key( wp_unslash( $_GET['nudge'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'mrm' ) );
		}
		check_admin_referer( 'mailmint_snooze_dashboard_nudge_' . $key );

		if ( '' !== $key ) {
			$snoozed         = (array) get_user_meta( get_current_user_id(), self::SNOOZE_META, true );
			$snoozed[ $key ] = time();
			update_user_meta( get_current_user_id(), self::SNOOZE_META, $snoozed );
		}

		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * Whether the current user snoozed a nudge less than SNOOZE_DAYS ago.
	 *
	 * @param string $key Nudge key.
	 * @return bool
	 * @since 1.32.0
	 */
	private function is_snoozed( $key ) {
		$snoozed = get_user_meta( get_current_user_id(), self::SNOOZE_META, true );
		if ( ! is_array( $snoozed ) || empty( $snoozed[ $key ] ) ) {
			return false;
		}
		return ( time() - (int) $snoozed[ $key ] ) < self::SNOOZE_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * URL of a route inside the Mail Mint admin app.
	 *
	 * @param string $route Hash route, e.g. /contacts/import.
	 * @return string
	 * @since 1.32.0
	 */
	private static function admin_route( $route ) {
		return admin_url( 'admin.php?page=mrm-admin#' . $route );
	}

	/**
	 * Editor URL of a draft campaign, matching the campaign list's routing.
	 *
	 * @param array $campaign Row with id and type.
	 * @return string
	 * @since 1.32.0
	 */
	private static function campaign_edit_url( array $campaign ) {
		$id = absint( $campaign['id'] );
		switch ( $campaign['type'] ) {
			case 'split':
				return self::admin_route( '/campaign/split-testing/edit/' . $id );
			case 'sequence':
				return self::admin_route( '/campaign/sequence-campaign/edit/' . $id );
			case 'recurring':
				return self::admin_route( '/campaign/recurring/edit/' . $id );
			default:
				return self::admin_route( '/campaign/regular/edit/' . $id );
		}
	}

	/**
	 * Print the styles shared by every nudge box, once.
	 *
	 * @since 1.32.0
	 */
	private function print_styles() {
		if ( $this->styles_printed ) {
			return;
		}
		$this->styles_printed = true;
		?>
		<style>
			[id^="mailmint_nudge_"] .inside{padding:0;margin:0}
			.mmw-nudge{display:flex;gap:12px;padding:12px 12px 14px}
			.mmw-nudge__icon{flex:0 0 36px;width:36px;height:36px;border-radius:8px;background:#efebff;display:flex;align-items:center;justify-content:center}
			.mmw-nudge__main{flex:1;min-width:0}
			.mmw-nudge__title{margin:0 0 4px;font-size:14px;font-weight:600;color:#1d2327}
			.mmw-nudge__text{margin:0 0 12px;color:#50575e}
			.mmw-nudge .button-primary{background:#573bff;border-color:#573bff}
			.mmw-nudge .button-primary:hover,.mmw-nudge .button-primary:focus{background:#4c25a5;border-color:#4c25a5}
			.mmw-nudge__foot{display:flex;justify-content:flex-end;margin:0;padding:8px 12px;border-top:1px solid #f0f0f1;font-size:12px}
			.mmw-nudge__foot .mmw-nudge__snooze{color:#8c8f94}
		</style>
		<?php
	}

	/**
	 * Small line icon per nudge.
	 *
	 * @param string $key Nudge key.
	 * @return string SVG markup.
	 * @since 1.32.0
	 */
	private static function icon_svg( $key ) {
		$paths = array(
			'contacts'       => '<circle cx="9" cy="8" r="3"/><path d="M3.5 18a5.5 5.5 0 0 1 11 0"/><path d="M17 8v6M14 11h6"/>',
			'campaign'       => '<path d="m21 3-9.5 9.5"/><path d="M21 3 15 21l-3.5-8.5L3 9l18-6Z"/>',
			'automation'     => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
			'abandoned_cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H6"/>',
		);
		$path  = isset( $paths[ $key ] ) ? $paths[ $key ] : $paths['campaign'];
		return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#573bff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
	}
}
