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
 * Version:           1.21.5
 * Author:            WPFunnels Team
 * Author URI:        https://getwpfunnels.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mrm
 * Domain Path:       /languages
 */

use LinnoSDK\Telemetry\Client;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'MRM_VERSION', '1.21.5' );
define( 'MAILMINT', 'mailmint' );
define( 'MRM_DB_VERSION', '1.16.0' );
define( 'MINT_DEV_MODE', false );
define( 'MRM_PLUGIN_NAME', 'mrm' );
define( 'MRM_FILE', __FILE__ );
define( 'MRM_FILE_DIR', __DIR__ );
define( 'MRM_PLUGIN_URL', plugin_dir_url(__FILE__) );
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

if ( ! defined( 'MRM_POSTHOG_API_KEY' ) ) {
	define( 'MRM_POSTHOG_API_KEY', 'phc_rw2FnQu3QoGOkJs4r0uLGH7WQf8PTM1TscVUheNKB4U' );
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

if ( ! function_exists( 'init_mail_mint_telemetry' ) ) {
	/**
	 * Initialize the Linno Telemetry SDK client and register event triggers.
	 *
	 * Registers declarative hook-to-event mappings for onboarding completion,
	 * AHA moment (first campaign sent), and recurring feature usage events.
	 * Plugin activation and deactivation events are handled natively by the SDK.
	 *
	 * @return void
	 * @since 1.21.0
	 */
	function init_mail_mint_telemetry() {
		$client = new Client(
			array(
				'pluginFile' => __FILE__,
				'slug'       => 'mail-mint',
				'pluginName' => 'Mail Mint',
				'version'    => MRM_VERSION,
				'driver'     => 'posthog',
				'driver_config'  => array(
                    'host'    => 'https://eu.i.posthog.com',
                    'api_key' => MRM_POSTHOG_API_KEY,
                ),
				'review_prompt' => array(
					'webhook'              => 'https://getwpfunnels.com/wp-json/mrm/v1/form/44/webhook/receive?token=b553ed8f2d6f9ff1612b4fe79c19ff9ee8a30c170eb547f1aba9376eb7ac8039',
					'min_feedback_length'  => 50,
					'snooze_schedule'      => array( 7, 30, 90 ),
					'nps_question'         => 'How likely are you to recommend Mail Mint to your friends or colleagues?',
					'low_score_threshold'  => 7,
					'review_url'           => 'https://wordpress.org/support/plugin/mail-mint/reviews/#new-post',
					'support_url'          => 'https://getwpfunnels.com/support/',
					'privacy_url'          => 'https://getwpfunnels.com/privacy-policy/',
					'installed_option_key' => 'mailmint_install_timestamp',
					'position'             => 'bottom-right',
					'allowed_screens'      => array(
						'toplevel_page_mrm-admin',
						'mail-mint_page_mrm-admin',
					),
				),
			)
		);

		$client->define_triggers(
			array(
				'onboarding' => array(
					'hook'     => 'mailmint_setup_wizard_complete',
					'callback' => function ( $goal ) {
						return array( 'goal' => sanitize_text_field( $goal ) );
					},
				),
				'aha' => array(
					'first_campaign_sent'       => array(
						'hook' => 'mailmint_campaign_email_sent',
					),
					'first_campaign_created'    => array(
						'hook'     => 'mailmint_campaign_created',
						'callback' => function ( $campaign_id ) {
							return array( 'campaign_id' => (int) $campaign_id );
						},
					),
					'contacts_imported'         => array(
						'hook'     => 'mailmint_contacts_imported',
						'callback' => function ( $count, $source ) {
							return array(
								'count'  => (int) $count,
								'source' => sanitize_text_field( $source ),
							);
						},
					),
					'first_form_created'        => array(
						'hook'     => 'mailmint_first_form_created',
						'callback' => function ( $form_id ) {
							return array( 'form_id' => (int) $form_id );
						},
					),
					'first_automation_created'  => array(
						'hook'     => 'mailmint_automation_created',
						'callback' => function ( $automation_id, $trigger_name ) {
							return array(
								'automation_id' => (int) $automation_id,
								'trigger_type'  => sanitize_text_field( $trigger_name ),
							);
						},
					),
					'wpfunnels_connected'       => array(
						'hook' => 'mailmint_wpfunnels_connected',
					),
					'woocommerce_connected'     => array(
						'hook' => 'mailmint_woocommerce_connected',
					),
				),
				'feature_used' => array(
					// Fires once per campaign completion (not per recipient) — safe frequency.
					'campaign_sent'            => array(
						'hook'     => 'mailmint_campaign_email_sent',
						'callback' => function ( $campaign_id ) {
							return array( 'campaign_id' => (int) $campaign_id );
						},
					),
					// Fires per visitor opt-in form submission.
					'form_submission_received' => array(
						'hook'     => 'mailmint_after_form_submit',
						'callback' => function ( $form_id ) {
							return array( 'form_id' => (int) $form_id );
						},
					),
					// Fires while user import contacts
					'contacts_imported' => array(
						'hook'     => 'mailmint_contacts_imported',
						'callback' => function ( $count, $source ) {
							return array( 'source' => $source, 'count' => (int) $count );
						},
					),
					// Fires while user import added or updated
					'contacts_added' => array(
						'hook'     => 'mailmint_contacts_saved',
						'callback' => function ( $contact_id, $params ) {
							return array( 'contact_id' => $contact_id );
						},
					),
					// Fires each time an automation is triggered.
					'automation_triggered' => array(
						'hook'     => MINT_TRIGGER_AUTOMATION,
						'callback' => function ( $data ) {
							return array(
								'trigger_name'   => isset( $data['trigger_name'] ) ? $data['trigger_name'] : '',
								'connector_name' => isset( $data['connector_name'] ) ? $data['connector_name'] : '',
							);
						},
					),
					// Fires when a WooCommerce cart is marked as abandoned.
					'cart_abandoned' => array(
						'hook'     => 'mailmint_after_cart_abandoned',
						'callback' => function ( $abandoned_id ) {
							return array( 'abandoned_id' => (int) $abandoned_id );
						},
					),
				),
			)
		);

		/**
		 * Sync consent state independently when user accepts tracking consent.
		 *
		 * Registered as a separate callback from the setup trigger per FR-007.
		 * Calls set_optin_state('yes') to write consent to both the global
		 * linno_telemetry_allow_tracking and plugin-specific mail-mint_allow_tracking options.
		 *
		 * @since 1.21.0
		 */
		add_action(
			'mailmint_after_accept_consent',
			function () use ( $client ) {
				$client->set_optin_state( 'yes' );
			}
		);

		/**
		 * Update tracking consent from the General Settings page.
		 *
		 * Fired by TrackingSettingController::create_or_update() with either 'yes'
		 * or 'no', allowing the SDK to write to both linno_telemetry_allow_tracking
		 * and mail-mint_allow_tracking in a single call.
		 *
		 * @since 1.0.0
		 */
		add_action(
			'mailmint_tracking_consent_changed',
			function ( $state ) use ( $client ) {
				$client->set_optin_state( $state );
			}
		);

		// Replace generic SDK deactivation reasons with mail mint specific ones.
        add_filter( 'mail-mint_telemetry_deactivation_reasons', function( $reasons ) {
			return [
				[
					'id'   => 'smtp-email-sending-issue',
					'text' => __( 'Emails are not sending', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="#3B86FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 6l-10 7L2 6" stroke="#3B86FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
				],
				[
					'id'   => 'too-complex-to-use',
					'text' => __( 'Too complex to get started', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="3" stroke="#3B86FF" stroke-width="2"/><path d="M9 9h6M9 12h4" stroke="#3B86FF" stroke-width="2" stroke-linecap="round"/></svg>',
				],
				[
					'id'   => 'fatal-error-or-conflict',
					'text' => __( 'Caused a fatal error or conflict with another plugin', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="#3B86FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
				],
				[
					'id'   => 'missing-integration',
					'text' => __( 'Missing a integration I need', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#3B86FF" stroke-width="2"/><path d="M12 8v4M12 16h.01" stroke="#3B86FF" stroke-width="2" stroke-linecap="round"/></svg>',
				],
				[
					'id'   => 'switching-to-another-plugin',
					'text' => __( 'Switching to another plugin', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M12 5l7 7-7 7" stroke="#3B86FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
				],
				[
					'id'   => 'no-longer-needed',
					'text' => __( 'No longer need email marketing', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="#3B86FF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
				],
				[
					'id'   => 'temporary',
					'text' => __( 'Temporarily deactivating', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#3B86FF" stroke-width="2"/><path d="M12 6v6l4 2" stroke="#3B86FF" stroke-width="2" stroke-linecap="round"/></svg>',
				],
				[
					'id'   => 'missing-feature',
					'text' => __( 'Missing a feature I need', 'mrm' ),
					'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#3B86FF" stroke-width="2"/><path d="M9 9a3 3 0 1 1 4 2.83V13M12 17h.01" stroke="#3B86FF" stroke-width="2" stroke-linecap="round"/></svg>',
				],
			];
		} );
	}
}
init_mail_mint_telemetry();

/**
 * Fire one-time integration-connected hooks on plugins_loaded so the Linno
 * SDK can track when WooCommerce or WPFunnels becomes active alongside Mail Mint.
 */
add_action( 'plugins_loaded', function () {
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
		&& ! get_option( 'mail_mint_woocommerce_connected_tracked' ) ) {
		do_action( 'mailmint_woocommerce_connected' );
		update_option( 'mail_mint_woocommerce_connected_tracked', 'yes' );
	}

	if ( in_array( 'wpfunnels/wpfnl.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true )
		&& ! get_option( 'mail_mint_wpfunnels_connected_tracked' ) ) {
		do_action( 'mailmint_wpfunnels_connected' );
		update_option( 'mail_mint_wpfunnels_connected_tracked', 'yes' );
	}
}, 20 );


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



