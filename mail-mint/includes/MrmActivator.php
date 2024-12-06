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
			'mail_mint_db_version',
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
		$db_version = get_option( 'current_db_version' );

		foreach ( self::$db_version as $version => $callbacks ) {
			if ( version_compare( $db_version, $version, '<' ) ) {
				foreach ( $callbacks as $callback ) {
					as_enqueue_async_action(
						'mailmint_run_update_callback',
						array(
							'update_callback' => $callback,
						),
						'mailmint_db_update'
					);
				}
			}
		}
	}


	/**
	 * TODO
	 *
	 * @since 1.0.0
	 */
	public static function mailmint_run_update_callback() {
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
			}
		}
	}
}
