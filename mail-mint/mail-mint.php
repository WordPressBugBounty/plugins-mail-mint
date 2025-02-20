<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://getwpfunnels.com/email-marketing-automation-mail-mint/
 * @since             1.0.0
 * @package           MintEmail
 *
 * @wordpress-plugin
 * Plugin Name:       Email Marketing Automation - Mail Mint
 * Plugin URI:        https://getwpfunnels.com/email-marketing-automation-mail-mint/
 * Description:       Effortless 📧 email marketing automation tool to collect & manage leads, run email campaigns, and initiate basic email automation.
 * Version:           1.17.1
 * Author:            WPFunnels Team
 * Author URI:        https://getwpfunnels.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mrm
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'MRM_VERSION', '1.17.1' );
define( 'MAILMINT', 'mailmint' );
define( 'MRM_DB_VERSION', '1.15.3' );
define( 'MINT_DEV_MODE', false );
define( 'MRM_PLUGIN_NAME', 'mrm' );
define( 'MRM_FILE', __FILE__ );
define( 'MRM_FILE_DIR', __DIR__ );
define( 'MRM_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MAILMINT_BASE_NAME', plugin_basename( MRM_FILE ) );
define( 'MRM_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/mailmint/' );
define( 'MRM_UPLOAD_URL', WP_CONTENT_URL . '/uploads/mailmint/' );
define( 'MRM_IMPORT_DIR', WP_CONTENT_DIR . '/uploads/mailmint/import' );
define( 'MRM_DIR_URL', plugins_url( '/', __FILE__ ) );
define( 'MRM_ADMIN_EXTERNAL_JS_FOLDER', 'assets/admin/js/' );
define( 'MRM_ADMIN_EXTERNAL_CSS_FOLDER', 'assets/admin/css/' );
define( 'MRM_ADMIN_DIST_JS_FOLDER', 'assets/admin/dist/' );
define( 'MRM_ADMIN_DIST_CSS_FOLDER', 'assets/admin/dist/css/' );
if ( !defined( 'MAILMINT_ACTIVATE_SCHEDULE_CAMPAIGN' ) ) {
	define( 'MAILMINT_ACTIVATE_SCHEDULE_CAMPAIGN', 'mailmint_activate_schedule_campaign' );
}

if ( !defined( 'MAILMINT_SCHEDULE_EMAILS' ) ) {
	define( 'MAILMINT_SCHEDULE_EMAILS', 'mailmint_schedule_emails' );
}

if ( !defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) ) {
	define( 'MAILMINT_SEND_SCHEDULED_EMAILS', 'mailmint_send_scheduled_emails' );
}

// Automation trigger actions.
if ( !defined( 'MINT_TRIGGER_AUTOMATION' ) ) {
	define( 'MINT_TRIGGER_AUTOMATION', 'mint_trigger_automation' );
}

if ( !defined( 'MINT_PROCESS_AUTOMATION' ) ) {
	define( 'MINT_PROCESS_AUTOMATION', 'mint_process_automation_data' );
}

if ( !defined( 'MINT_PROCESS_SEQUENCE' ) ) {
	define( 'MINT_PROCESS_SEQUENCE', 'mint_process_sequence' );
}

if ( !defined( 'MINT_AUTOMATION_GROUP' ) ) {
	define( 'MINT_AUTOMATION_GROUP', 'mint_automation' );
}

if ( !defined( 'MINT_AUTOMATION_AFTER_DOUBLE_OPTIN' ) ) {
	define( 'MINT_AUTOMATION_AFTER_DOUBLE_OPTIN', 'mint_automation_after_double_optin' );
}

if ( !defined( 'MINT_AUTOMATION_AFTER_EMAIL_OPEN' ) ) {
	define( 'MINT_AUTOMATION_AFTER_EMAIL_OPEN', 'mint_automation_after_email_open' );
}

if ( !defined( 'MINT_AUTOMATION_AFTER_EMAIL_CLICK' ) ) {
	define( 'MINT_AUTOMATION_AFTER_EMAIL_CLICK', 'mint_automation_after_email_click' );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/MrmActivator.php
 */
function activate_mrm() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/MrmActivator.php';
	MrmActivator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/MrmDeactivator.php
 */
function deactivate_mrm() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/MrmDeactivator.php';
	MrmDeactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_mrm' );
register_deactivation_hook( __FILE__, 'deactivate_mrm' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/MailMint.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 1.0.0
 * @since 1.10.0 Use MM instead of run_mrm for better coding experiences.
 */
function MM() {
	return MailMint::instance();
}
MM();

if ( ! function_exists( 'appsero_init_tracker_mail_mint' ) ) {
	/**
	 * Initialize the plugin tracker
	 *
	 * @since  1.0.0
	 */
	function appsero_init_tracker_mail_mint() {
		if ( ! class_exists( 'Appsero\Client' ) ) {
			require_once __DIR__ . '/vendor/appsero/src/Client.php';
		}
		$client = new Appsero\Client( '9d981a5e-81ce-4a15-a61e-7f9d912625c0', 'Mail Mint', __FILE__ );
		// Active insights.
		$client->insights()->init();
	}
}
appsero_init_tracker_mail_mint();


if ( ! function_exists( 'mmempty' ) ) {
	/**
	 * Determine if a value is empty
	 *
	 * @param string $name Name of the prop .
	 * @param null   $array array .
	 * @return bool True if empty otherwise false.
	 *
	 * @since 1.0.0
	 */
	function mmempty( $name, $array = null ) {
		if ( is_array( $name ) ) {
			return empty( $name );
		}

		if ( ! $array ) {
            $array = filter_input_array( INPUT_POST, FILTER_DEFAULT ); //phpcs:ignore
		}

		$val = mmarval( $array, $name );

		return empty( $val );
	}
}




if ( ! function_exists( 'mmarval' ) ) {

	/**
	 * Get an specific property of an array
	 *
	 * @param array  $array Array of which the property value should be retrieved.
	 * @param string $prop Name of the property to be retrieved.
	 * @param null   $default Default value if no value is found with that name .
	 * @return mixed|string|null
	 *
	 * @since 1.0.0
	 */
	function mmarval( $array, $prop, $default = null ) {
		if ( ! is_array( $array ) && ! ( is_object( $array ) && $array instanceof ArrayAccess ) ) {
			return $default;
		}

		if ( isset( $array[ $prop ] ) ) {
			$value = $array[ $prop ];
		} else {
			$value = '';
		}
		return empty( $value ) && null !== $default ? $default : $value;
	}
}

/**
 * Register WP CLI Commands
 *
 * @since 1.0.0
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mailmint', 'Mint\MRM\Includes\MintMailCLI' );
}



