<?php
/**
 * Fired during plugin activation
 *
 * @link       http://rextheme.com/
 * @since      1.0.0
 *
 * @package    Mrm
 * @subpackage Mrm/includes
 */

use Mint\MRM\Utilities\Helper\PermissionManager;
use MRM\Common\MrmCommon;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mrm
 * @subpackage Mrm/includes
 * @author     RexTheme <support@getwpfunnels.com>
 */
class MrmActivator {

	/**
	 * Mail Mint DB version
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public static $db_version = array(
		'1.0.0' => array(
			'mailmint_update_100_db_version',
		),
	);


	/**
	 * Run WP init hooks while activator class executes
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_mint_version' ), 5 );
		add_action( 'init', array( __CLASS__, 'manual_database_update' ), 20 );
		add_action( 'mailmint_run_update_callback', array( __CLASS__, 'run_update_callback' ) );
		add_action( 'mailmint_updated', array( __CLASS__, 'handle_dispatch_transition' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'handle_upgrader_transition' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'maybe_run_post_upgrade_transition' ), 6 );
	}


	/**
	 * Check MailMint version and define if any upgrade is required
	 *
	 * @since 1.0.0
	 */
	public static function check_mint_version() {
		$mint_version    = get_option( 'mail_mint_version' );
		$requires_update = version_compare( $mint_version, MRM_VERSION, '<' );

		if ( $requires_update ) {
			self::activate();

			do_action( 'mailmint_updated' );

			/**
			 * If no MailMint version is found, we consider this as a newly installed plugin
			 */
			if ( !$mint_version ) {
				do_action( 'mailmint_newly_installed' );
			}
		}
	}


	/**
	 * Run manual update of MailMint
	 *
	 * @since 1.0.0
	 */
	public static function manual_database_update() {
		self::update();
	}


	/**
	 * Run manual database update
	 *
	 * @since 1.0.0
	 */
	public static function update() {
		$db_version = get_option( 'mail_mint_db_version' );

		foreach ( self::$db_version as $version => $callbacks ) {
			if ( version_compare( $db_version, $version, '<' ) ) {
				foreach ( $callbacks as $callback ) {
					$args = array( 'update_callback' => $callback );
					if ( ! as_has_scheduled_action( 'mailmint_run_update_callback', $args, 'mailmint_db_update' ) ) {
						as_enqueue_async_action(
							'mailmint_run_update_callback',
							$args,
							'mailmint_db_update'
						);
					}
				}
			}
		}
	}


	/**
	 * Execute a queued DB update callback dispatched by Action Scheduler.
	 *
	 * @param string $update_callback Callable name to invoke.
	 * @since 1.0.0
	 */
	public static function run_update_callback( $update_callback ) {
		if ( is_callable( $update_callback ) ) {
			call_user_func( $update_callback );
		}
	}



	/**
	 * Activate the plugin for a specific blog in a multisite network.
	 *
	 * Switches to the given blog, runs the full activation routine, then
	 * restores the current blog. Safe to call during network activation or
	 * from the wp_initialize_site hook when a new subsite is created.
	 *
	 * @param int $blog_id The blog to activate for.
	 * @since 1.22.0
	 */
	public static function activate_for_blog( $blog_id ) {
		switch_to_blog( $blog_id );
		self::activate();
		restore_current_blog();
	}


	/**
	 * Process all activation tasks
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once MRM_DIR_PATH . 'app/Database/Upgrade.php';

		set_transient( 'mailmint_installing', 'yes', MINUTE_IN_SECONDS * 10 );

		if ( self::is_new_install() ) {
			$upgrade = \Mint\MRM\DataBase\Upgrade::get_instance();

			$upgrade->maybe_upgrade();

			self::set_activation_transient();
			self::create_pages();
			PermissionManager::assign_capabilities_to_admin();
			set_transient( 'mailmint_fresh_install_pending', 'yes', HOUR_IN_SECONDS );
		}
		self::create_files();
		self::update_mint_version();

		delete_transient( 'mailmint_installing' );

		/**
		 * Store the timestamp when MailMint is installed
		 *
		 * @since 1.0.0
		 */
		add_option( 'mailmint_install_timestamp', time() );

		/**
		 * Run after MailMint is installed or updated
		 *
		 * @since 1.0.0
		 */
		do_action( 'mailmint_installed' );
	}


	/**
	 * Set transient to show setup wizard if user installs this plugin for the first time
	 *
	 * @since 1.0.0
	 * @since 1.13.0 Added new install check.
	 */
	public static function set_activation_transient() {
		if ( self::is_new_install() ) {
			set_transient( 'mailmint_show_setup_wizard', 'yes', 30 );
		}
	}


	/**
	 * Check if new install or not
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_new_install() {
		return is_null( get_option( 'mail_mint_version', null ) );
	}


	/**
	 * Update MailMint versions
	 *
	 * @since 1.0.0
	 */
	public static function update_mint_version() {
		if ( defined( 'MRM_VERSION' ) ) {
			update_option( 'mail_mint_version', MRM_VERSION, false );
		}
	}


	/**
	 * Create file/directories
	 *
	 * @since 1.0.0
	 */
	private static function create_files() {

		// Install files and folders for uploading files.
		$upload_dir = wp_get_upload_dir();

		$files = array(
			array(
				'base'    => $upload_dir['basedir'] . '/mail-mint',
				'file'    => 'index.html',
				'content' => '',
			),
			array(
				'base'    => $upload_dir['basedir'] . '/mail-mint/import',
				'file'    => 'index.html',
				'content' => '',
			),
		);

		foreach ( $files as $file ) {
			if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
				$file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen
				if ( $file_handle ) {
					fwrite( $file_handle, $file['content'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
					fclose( $file_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
				}
			}
		}
	}




	/**
	 * Create pages that the plugin relies on, storing page IDs in variables.
	 *
	 * @since 1.0.0
	 */
	public static function create_pages() {
		$pages = apply_filters(
			'mint_mail_create_pages',
			array(
				'optin_confirmation'       => array(
					'post_name'    => _x( 'optin_confirmation', 'Page slug', 'mrm' ),
					'post_title'   => _x( 'Mint Mail Opt-in Confirmation', 'Page title', 'mrm' ),
					'post_content' => '<!-- wp:shortcode -->[optin_confirmation]<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				'preference_page'          => array(
					'post_name'    => _x( 'preference_page', 'Page slug', 'mrm' ),
					'post_title'   => _x( 'Mint Mail Preference', 'Page title', 'mrm' ),
					'post_content' => '<!-- wp:shortcode -->[preference_page]<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				'unsubscribe_confirmation' => array(
					'post_name'    => _x( 'unsubscribe_confirmation', 'Page slug', 'mrm' ),
					'post_title'   => _x( 'Mint Mail Unsubscribe Confirmation', 'Page title', 'mrm' ),
					'post_content' => '<!-- wp:shortcode -->[unsubscribe_confirmation]<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				'unsubscribe_survey'      => array(
					'post_name'    => _x( 'unsubscribe_survey', 'Page slug', 'mrm' ),
					'post_title'   => _x( 'Mint Mail Unsubscribe Survey', 'Page title', 'mrm' ),
					'post_content' => '<!-- wp:shortcode -->[unsubscribe_survey]<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
			)
		);

		foreach ( $pages as $key => $page ) {
			// Insert the post into the database.
			if ( ! get_page_by_path( $page['post_name'], OBJECT, 'page' ) ) { // Check If Page Not Exits.
				$post_id = wp_insert_post( $page );

				if ( 'optin_confirmation' === get_post_field( 'post_name', $post_id ) ) {
					update_post_meta( $post_id, '_wp_page_template', 'template-subscribe-page.php' );
				}
				if ( 'preference_page' === get_post_field( 'post_name', $post_id ) ) {
					update_post_meta( $post_id, '_wp_page_template', 'template-preference-page.php' );
					MrmCommon::default_preferance_setting( $post_id );
				}
				if ( 'unsubscribe_confirmation' === get_post_field( 'post_name', $post_id ) ) {
					update_post_meta( $post_id, '_wp_page_template', 'template-unsubscribe-page.php' );
					MrmCommon::default_unsubscribe_setting( $post_id );
				}
				if ( 'unsubscribe_survey' === get_post_field( 'post_name', $post_id ) ) {
					update_post_meta( $post_id, '_wp_page_template', 'template-unsubscribe-page.php' );
				}
			}
		}
	}

	/**
	 * Set a transient flag when Mail Mint files are replaced via zip upload.
	 *
	 * Fires during `upgrader_process_complete` — at this point the new files are
	 * on disk but the old plugin code is still in memory for this request.
	 * So we only set a transient here; the actual transition runs on the next
	 * request via `maybe_run_post_upgrade_transition()` when everything is loaded.
	 *
	 * @since 1.21.3
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Upgrade options including type and plugins list.
	 * @return void
	 */
	public static function handle_upgrader_transition( $upgrader, $options ) {
		if (
			'plugin' !== ( $options['type'] ?? '' ) ||
			'install' === ( $options['action'] ?? '' )
		) {
			return;
		}

		$plugins = $options['plugins'] ?? array();

		if ( ! in_array( MAILMINT_BASE_NAME, $plugins, true ) ) {
			return;
		}

		set_transient( 'mailmint_dispatch_transition_pending', '1', HOUR_IN_SECONDS );
	}

	/**
	 * Run the dispatch transition on the first request after a zip-based file replacement.
	 *
	 * Checks for the transient flag set by `handle_upgrader_transition()`. Runs at
	 * `init` priority 6 — after `check_mint_version` (priority 5) so it doesn't
	 * double-fire when both a version bump and a zip upload happen simultaneously,
	 * but before anything else tries to schedule AS jobs.
	 *
	 * @since 1.21.3
	 * @return void
	 */
	public static function maybe_run_post_upgrade_transition() {
		if ( ! get_transient( 'mailmint_dispatch_transition_pending' ) ) {
			return;
		}

		delete_transient( 'mailmint_dispatch_transition_pending' );
		self::handle_dispatch_transition();
	}

	/**
	 * Handle dispatch system transition on plugin update.
	 *
	 * Fires on `mailmint_updated` whenever the plugin version increments.
	 * Ensures a smooth handoff from the legacy per-campaign AS job dispatch
	 * (mailmint_send_scheduled_emails) to the GlobalQueueCoordinator:
	 *
	 * 1. Cancel all pending/in-progress legacy send jobs across all campaign groups.
	 * 2. Clear any failed or in-progress coordinator actions left from a bad transition.
	 * 3. Schedule a fresh coordinator job so active campaigns resume immediately.
	 *
	 * Safe to run on every update — all operations are idempotent.
	 *
	 * @since 1.21.3
	 * @return void
	 */
	public static function handle_dispatch_transition() {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		global $wpdb;

		// Step 1: Cancel all pending/in-progress legacy per-campaign send jobs.
		$legacy_hook = defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) ? MAILMINT_SEND_SCHEDULED_EMAILS : 'mailmint_send_scheduled_emails';
		$wpdb->query( // phpcs:ignore
			$wpdb->prepare(
				"DELETE a FROM {$wpdb->prefix}actionscheduler_actions a
				WHERE a.hook = %s
				AND a.status IN ('pending', 'in-progress')",
				$legacy_hook
			)
		);

		// Step 2: Clear failed/in-progress coordinator actions from a bad transition.
		$coordinator_group_id = MrmCommon::get_as_group_id( \Mint\MRM\Internal\Cron\GlobalQueueCoordinator::GROUP );
		if ( $coordinator_group_id ) {
			$wpdb->query( // phpcs:ignore
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}actionscheduler_actions
					WHERE group_id = %d
					AND status IN ('failed', 'in-progress')",
					$coordinator_group_id
				)
			);
		}

		// Step 3: Clear the healthy transient so selfHeal re-evaluates immediately.
		delete_transient( 'mailmint_coordinator_healthy' );

		// Step 4: Schedule a fresh coordinator job to resume active campaigns.
		\Mint\MRM\Internal\Cron\GlobalQueueCoordinator::get_instance()->schedule();
	}
}


/**
 * Mark the database as compatible with version 1.0.0.
 *
 * Called asynchronously via Action Scheduler when mail_mint_db_version
 * is below '1.0.0'. In practice this only fires on sites that somehow
 * never had the option set (e.g. a very old pre-1.0.0 install).
 *
 * @since 1.0.0
 */
function mailmint_update_100_db_version() {
	update_option( 'mail_mint_db_version', '1.0.0', false );
}
