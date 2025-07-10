<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://rextheme.com/
 * @since      1.0.0
 *
 * @package    Mrm
 * @subpackage Mrm/includes
 */

use MailMintPro\Mint\Internal\LinkTrigger\LinkTriggerHandler;
use Mint\App\Classes\Mailer;
use Mint\App\Classes\WPRemoteRequestHandler;
use Mint\App\Internal\Actions\Handlers\RedirectionHandler;
use Mint\MRM\App;
use Mint\MRM\Internal\Constants;
use Mint\MRM\Internal\Tracking\EventTracker;
use MRM\Common\MrmCommon;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Mrm
 * @subpackage Mrm/includes
 * @author     RexTheme <support@getwpfunnels.com>
 */
class MailMint {

	/**
	 * The single instance of the class.
	 *
	 * @var WooCommerce
	 * @since 2.1
	 */
	protected static $instance = null;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;


	/**
	 * Plugin constants variables
	 *
	 * @var Constants
	 * @since 1.0.0
	 */
	protected $constants;


	/**
	 * Main app variable.
	 *
	 * @var App
	 * @since 1.0.0
	 */
	protected $app;

	/**
	 * Mailer object
	 *
	 * @var object|Mailer
	 * @since 1.10.0
	 */
	public $mailer;

	/**
	 * WPRemoteRequestHandler object
	 * 
	 * @var object|WPRemoteRequestHandler
	 * @since 1.12.0
	 */
	public $wp_remote_request_handler = null;

	/**
	 * RedirectionHandler object
	 * 
	 * @var object|RedirectionHandler
	 * @since 1.14.0
	 */
	public $redirection_handler;

	/**
	 * LinkTriggerHandler object
	 * 
	 * @var object|LinkTriggerHandler
	 * @since 1.14.0
	 */
	public $link_trigger_handler;

	/**
	 * EventTracker object
	 * 
	 * @var object|EventTracker
	 * @since 1.17.10
	 */
	public $event_tracker;

	/**
	 * Main MRM Instance.
	 *
	 * Ensures only one instance of MRM is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @return MRM - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		self::$instance->mailer                    = new Mailer();
		self::$instance->wp_remote_request_handler = new WPRemoteRequestHandler();
		self::$instance->redirection_handler       = new RedirectionHandler();
		self::$instance->event_tracker             = EventTracker::init();
		if( MrmCommon::is_mailmint_pro_active() && class_exists('MailMintPro\Mint\Internal\LinkTrigger\LinkTriggerHandler' ) ) {
			self::$instance->link_trigger_handler = new LinkTriggerHandler();
		}

		return self::$instance;
	}


	/**
	 * Cloning is forbidden.
	 *
	 * @since 2.1
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'mrm' ), '2.1' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 2.1
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'mrm' ), '2.1' );
	}

	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @param mixed $key Key name.
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( in_array( $key, array( 'payment_gateways', 'shipping', 'mailer', 'checkout' ), true ) ) {
			return $this->$key();
		}
	}


	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'MRM_VERSION' ) ) {
			$this->version = MRM_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'mrm';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_constants();

		App::get_instance()->init();
	}


	/**
	 * Defined constants.
	 *
	 * @since 1.0.0
	 */
	private function define_constants() {
		$this->constants = Constants::get_instance();
		$this->constants->define_constants();
	}



	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Mrm_Loader. Orchestrates the hooks of the plugin.
	 * - Mrmi18n. Defines internationalization functionality.
	 * - Mrm_Admin. Defines all hooks for the admin area.
	 * - Mrm_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		// Initialize Action Scheduler. It needs to be called early because it hooks into `plugins_loaded`.
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/woocommerce/action-scheduler/action-scheduler.php';

		/**
		 * The class responsible for auto loading all files of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Mrmi18n.php';

		// Require MailMint Contacts file.
		if ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Contacts/MailMintContacts.php' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Contacts/MailMintContacts.php';
		}
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Mrmi18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new Mrmi18n();

		add_action( 'init', array( $plugin_i18n, 'load_plugin_textdomain' ) );
	}



	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Mrm_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
