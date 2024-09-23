<?php
/**
 * App Class for Create Instance
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app
 */

namespace Mint\MRM;

use Mint\App\Internal\EmailCustomization\WooCommerce\EmailTrigger;
use Mint\MRM\Admin\API\Server;
use Mint\MRM\DataBase\Migration\DatabaseMigrator;
use Mint\MRM\Internal\Admin\AdminAssets;
use Mint\MRM\Internal\Admin\FrontendAssets;
use Mint\MRM\Internal\Admin\HandleFrontendMenu;
use Mint\MRM\Internal\Admin\Page\PageController;
use Mint\MRM\Internal\Admin\SetupWizard;
use Mint\MRM\Internal\Admin\UserAssignContact;
use Mint\MRM\Internal\Admin\WPUserDelete;
use Mint\MRM\Internal\FormBuilder\FormBuilderHelper;
use Mint\MRM\Internal\Frontend\FrontendAction;
use Mint\MRM\Internal\Frontend\WooCommerceCheckoutContact;
use Mint\MRM\Internal\Optin\OptinConfirmation;
use Mint\MRM\Internal\ShortCode\ShortCode;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Internal\Cron\CampaignsBackgroundProcess;
use Mint\MRM\Internal\Optin\UnsubscribeConfirmation;
use Mint\MRM\Internal\Admin\WooCommerceOrderDetails;
use Mint\MRM\Internal\Templates\TemplateHandler;
use MintMail\App\Internal\Automation;
use MRM\Common\MrmCommon;

/**
 * MRM App class.
 *
 * @since 1.0.0
 */
class App {

	use Singleton;

	/**
	 * Init the plugin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->init_hooks();
		if ( $this->is_request( 'admin' ) ) {
			// Load assets.
			AdminAssets::get_instance();

			new SetupWizard;
		}

		// init form-builder.
		new FormBuilderHelper();

		// init plugin shortcodes.
		ShortCode::get_instance()->init();

		if ( $this->is_request( 'frontend' ) ) {
			// Load assets.
			FrontendAssets::get_instance();
			// Opt-in.
			OptinConfirmation::get_instance();
			// Unsubscription.
			new UnsubscribeConfirmation();

			WooCommerceCheckoutContact::get_instance()->init();
		}

		HandleFrontendMenu::get_instance()->init();

		// User assign contact form user in Sign up and comment.
		UserAssignContact::get_instance();

		CampaignsBackgroundProcess::get_instance()->init();

		WooCommerceOrderDetails::get_instance()->init();

		TemplateHandler::get_instance()->init();

		WPUserDelete::get_instance()->init();

		// Initialize server class.
		$server = new Server();
		$server->init();

		add_action(
			'plugins_loaded',
			function() {
				$automation_manager   = Automation\AutomationManager::get_instance();
				$automation_connector = Automation\Connector::get_instance();
				$automation_action    = Automation\Action\AutomationAction::get_instance();
				new Automation( $automation_manager, $automation_connector, $automation_action );
			}, 200
		);

		if ( $this->is_request( 'admin' ) ) {
			// Initialize Page.
			PageController::get_instance();
		}

		DatabaseMigrator::get_instance()->init();

		new \MailMint\App\Actions\Hooks();

		// If the method returns true, indicating that email customization is active, it creates a new instance of the `EmailTrigger` class.
		if ( MrmCommon::is_email_customization_active() ) {
			new EmailTrigger();
		}
	}


	/**
	 * Hook into actions and filters.
	 *
	 * @since 1.0.0
	 */
	public function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), -1 );
		add_action( 'init', array( $this, 'init_mail_mint' ), 0 );
		add_action( 'in_plugin_update_message-mail-mint/mail-mint.php', array( $this, 'mail_mint_plugin_update_message' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'hide_notices' ) );
		// Localize the plugin.
		add_action( 'init', array( $this, 'localization_setup' ) );
	}

	public function localization_setup() {
		load_plugin_textdomain( 'mrm', false, 'mail-mint' . '/languages/' );

		// Load the React-pages translations.
		if ( is_admin() ) {
			// Load wp-script translation for plugin-name-app
			wp_set_script_translations( 'mail-mint-js', 'mrm', MRM_DIR_URL . 'languages/' );
			wp_set_script_translations( 'mint-mail-automation-editor', 'mrm', MRM_DIR_URL . 'languages/' );
			wp_set_script_translations( 'mail-mint-mjml', 'mrm', MRM_DIR_URL . 'languages/' );
			wp_set_script_translations( 'mail-mint-vendor', 'mrm', MRM_DIR_URL . 'languages/' );
		}
	}

	/**
	 * Handles the logic for hiding specific notices based on query parameters.
	 *
	 * @throws \Exception Throws exception error message.
	 *
	 * @since 1.6.0
	 */
	public function hide_notices() {
		if ( isset( $_GET['mm-hide-notice'] ) && isset( $_GET['mm_notice_nonce'] ) ) { //phpcs:ignore
			if ( ! \wp_verify_nonce( sanitize_key( wp_unslash( $_GET['mm_notice_nonce'] ) ), 'mm_hide_notices_nonce' ) ) { //phpcs:ignore
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'mrm' ) );
			}

			$notice_name = sanitize_text_field( wp_unslash( $_GET['mm-hide-notice'] ) );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You don&#8217;t have permission to do this.', 'mrm' ) );
			}

			update_option( "mail_mint_hide_{$notice_name}_notice", 'yes' );
		}
	}

	/**
	 * Display a warning message in the WordPress admin area when there's a plugin update.
	 *
	 * @access public
	 *
	 * @param array  $data     An array of update data.
	 * @param object $response The update response object.
	 *
	 * @return void
	 * @since 1.5.7
	 */
	public function mail_mint_plugin_update_message( $data, $response ) {
		if ( isset( $data['upgrade_notice'] ) ) {
			$msg = str_replace( array( '<p>', '</p>' ), array( '<div>', '</div>' ), $data['upgrade_notice'] );
			?>
				<hr class="e-major-update-warning__separator" />
				<div class="e-major-update-warning">
					<div class="e-major-update-warning__icon">
						<i class="eicon-info-circle"></i>
					</div>
					<div>
						<div class="e-major-update-warning__title">
							<?php echo esc_html__( 'Heads up, Please backup before upgrade!', 'mrm' ); ?>
						</div>
						<div class="e-major-update-warning__message">
							<?php
								printf( wp_kses_post( wpautop( $msg ) ) );
							?>
						</div>
					</div>
				</div>
			<?php
		}
	}

	/**
	 * When WP has loaded all plugins, trigger the `woocommerce_loaded` hook.
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		do_action( 'mailmint_loaded' );
	}


	/**
	 * Init MailMint when WordPress Initialises.
	 *
	 * @since 1.0.0
	 */
	public function init_mail_mint() {
		register_post_type( 'mint_email_template' );
	}



	/**
	 * Check the type of the request
	 *
	 * @param string $type get request type .
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}
}
