<?php
/**
 * REST API Onboarding Controller
 *
 * Handles requests to the onboarding endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.16.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Internal\Admin\CreateContact;
use Mint\MRM\Internal\Admin\SendingHealth;
use Mint\MRM\Internal\Admin\SetupWizard;
use Mint\MRM\DataBase\Models\DashboardModel;
use Mint\MRM\Internal\AI\Settings\AISettings;
use Mint\MRM\Internal\Constants;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles the onboarding send-test-email endpoint.
 *
 * Validates request parameters, sends a test email to the current admin user
 * via wp_mail(), and returns a success or warning response.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class OnboardingController extends SettingBaseController {

	use Singleton;

	/**
	 * Send a test email.
	 *
	 * Validates from_name, from_email, subject, and body fields.
	 * Sends an HTML email via wp_mail() to to_email when given and valid,
	 * otherwise to the logged-in admin.
	 *
	 * @param WP_REST_Request $request Request object with from_name, from_email, subject, body and optional to_email.
	 *
	 * @return WP_REST_Response|array Success or warning response.
	 */
	public function send_test_email( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$from_name  = isset( $params['from_name'] ) ? sanitize_text_field( $params['from_name'] ) : '';
		$from_email = isset( $params['from_email'] ) ? sanitize_email( $params['from_email'] ) : '';
		$subject    = isset( $params['subject'] ) ? sanitize_text_field( $params['subject'] ) : '';
		$body       = isset( $params['body'] ) ? wp_kses_post( $params['body'] ) : '';

		if ( empty( $from_name ) ) {
			return $this->get_error_response( esc_html__( 'From name is required.', 'mrm' ) );
		}

		if ( strlen( $from_name ) > 150 ) {
			return $this->get_error_response( esc_html__( 'From name must be 150 characters or fewer.', 'mrm' ) );
		}

		if ( ! is_email( $from_email ) || empty( $from_email ) ) {
			return $this->get_error_response( esc_html__( 'A valid from email address is required.', 'mrm' ) );
		}

		if ( empty( $subject ) ) {
			return $this->get_error_response( esc_html__( 'Subject is required.', 'mrm' ) );
		}

		if ( strlen( $subject ) > 200 ) {
			return $this->get_error_response( esc_html__( 'Subject must be 200 characters or fewer.', 'mrm' ) );
		}

		if ( empty( $body ) ) {
			return $this->get_error_response( esc_html__( 'Email body is required.', 'mrm' ) );
		}

		$to_email = isset( $params['to_email'] ) ? sanitize_email( $params['to_email'] ) : '';
		if ( empty( $to_email ) || ! is_email( $to_email ) ) {
			$to_email = wp_get_current_user()->user_email;
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		);

		$sent = wp_mail( $to_email, $subject, $body, $headers );

		if ( $sent ) {
			return $this->get_success_response( esc_html__( 'Test email sent successfully.', 'mrm' ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'warning',
				'message' => esc_html__( 'Email could not be sent. Consider installing an SMTP plugin.', 'mrm' ),
			)
		);
	}

	/**
	 * Accept consent and forward admin contact to webhook/Appsero.
	 *
	 * @param WP_REST_Request $request Request payload with consent boolean.
	 *
	 * @return WP_REST_Response Consent processing response.
	 */
	public function accept_consent( WP_REST_Request $request ) {
		$params  = $request->get_json_params();
		$consent = ! empty( $params['consent'] );

		if ( ! $consent ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'status'  => 'success',
					'message' => esc_html__( 'Consent was not accepted. Contact was not sent.', 'mrm' ),
				)
			);
		}

		$current_user = wp_get_current_user();
		$email        = sanitize_email( $current_user->user_email ?? '' );
		$name         = sanitize_text_field( $current_user->first_name ?: $current_user->display_name );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return $this->get_error_response( esc_html__( 'A valid admin email is required to submit consent.', 'mrm' ) );
		}

		$instance = new CreateContact( $email, $name );
		$response = $instance->create_contact_via_webhook();
		$instance->send_contact_to_appsero();

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'success',
				'message' => esc_html__( 'Consent accepted and contact has been sent successfully.', 'mrm' ),
				'results' => $response,
			)
		);
	}

	/**
	 * Mark the setup wizard as complete and fire telemetry hooks.
	 *
	 * Called from SuccessStep after the user gives consent (acceptWelcomeConsent
	 * is awaited first on the frontend). Accepts wizard activity data so AHA
	 * hooks can be re-fired here — after opt-in state is already set — ensuring
	 * the Linno SDK tracks them correctly.
	 *
	 * @param WP_REST_Request $request Request payload with goal, form_id, campaign_id,
	 *                                 automation_id, contacts_imported, contacts_source.
	 *
	 * @return WP_REST_Response
	 */
	public function complete_wizard( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$goal   = isset( $params['goal'] ) ? sanitize_text_field( $params['goal'] ) : '';

		// Re-fire AHA telemetry hooks with the IDs created during the wizard.
		// These fire after acceptWelcomeConsent has set the opt-in state, so
		// the Linno SDK will track them regardless of when the original hooks
		// fired during the wizard flow.
		if ( ! empty( $params['form_id'] ) ) {
			do_action( 'mailmint_first_form_created', (int) $params['form_id'] );
		}

		if ( ! empty( $params['campaign_id'] ) ) {
			do_action( 'mailmint_campaign_created', (int) $params['campaign_id'] );
		}

		if ( ! empty( $params['automation_id'] ) ) {
			do_action( 'mailmint_automation_created', (int) $params['automation_id'], '' );
		}

		if ( ! empty( $params['contacts_imported'] ) ) {
			$contacts_source = isset( $params['contacts_source'] ) ? sanitize_text_field( $params['contacts_source'] ) : '';
			do_action( 'mailmint_contacts_imported', 1, $contacts_source );
		}

		// Setup state collected by the merged journey (business info, email
		// sending and compliance steps). Booleans only — no personal data.
		$setup = array(
			'smtp_configured'   => ! empty( $params['smtp_configured'] ),
			'test_email_sent'   => ! empty( $params['test_email_sent'] ),
			'double_optin'      => ! empty( $params['double_optin'] ),
			'business_info'     => ! empty( $params['business_info'] ),
			'sender_configured' => ! empty( $params['sender_configured'] ),
			'ai_connected'      => ! empty( $params['ai_connected'] ),
		);

		/**
		 * Fires when the setup wizard is completed.
		 *
		 * @param string $goal  Goal chosen in the wizard (forms, newsletter, automations), or 'setup' for the merged journey.
		 * @param array  $setup Setup state flags collected by the wizard.
		 */
		do_action( 'mailmint_setup_wizard_complete', $goal, $setup );

		update_option( 'mailmint_setup_wizard_status', 'completed', false );

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'success',
				'message' => esc_html__( 'Wizard completed.', 'mrm' ),
			)
		);
	}

	/**
	 * Save whether the setup wizard should still be offered.
	 *
	 * - dismissed: the user exited the wizard; stop opening it automatically,
	 *   keep the dashboard card.
	 * - hidden:    the user hid the dashboard card too.
	 * - pending:   open it again on the next Mail Mint page visit.
	 *
	 * "completed" is only set by complete_wizard().
	 *
	 * @param WP_REST_Request $request Request with status.
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function update_wizard_status( WP_REST_Request $request ) {
		$status = sanitize_key( $request->get_param( 'status' ) );
		if ( ! in_array( $status, array( 'pending', 'dismissed', 'hidden' ), true ) ) {
			return $this->get_error_response( esc_html__( 'Invalid setup wizard status.', 'mrm' ) );
		}

		update_option( 'mailmint_setup_wizard_status', $status, false );

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'success',
				'value'   => $status,
			)
		);
	}

	/**
	 * Setup status, what's left of setup, and open issues that can stop
	 * emails from being sent or delivered (dismissed ones left out).
	 *
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function get_setup_issues() {
		$status = SetupWizard::get_status();
		return rest_ensure_response(
			array(
				'success'  => true,
				'status'   => $status,
				'progress' => SetupWizard::is_unfinished() ? SetupWizard::get_progress() : array(),
				// Sites from before the wizard status existed aren't nagged.
				'issues'   => '' === $status ? array() : SetupWizard::get_issues(),
				'checks'   => '' === $status ? array() : array_map(
					function ( $check ) {
						$check['dismissed'] = SetupWizard::is_dismissed( $check );
						return $check;
					},
					SetupWizard::get_checks()
				),
			)
		);
	}

	/**
	 * Getting Started page data: whether it still applies, and the
	 * first-steps checklist.
	 *
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function get_getting_started() {
		return rest_ensure_response(
			array(
				'success'    => true,
				'active'     => SetupWizard::is_getting_started_active(),
				'onboarding' => DashboardModel::get_onboarding_stats(),
			)
		);
	}

	/**
	 * Hide the Getting Started page and its menu item for good.
	 *
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function hide_getting_started() {
		SetupWizard::retire_getting_started();
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Stop reminding about the given issues.
	 *
	 * @param WP_REST_Request $request Request with keys (string[]).
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function dismiss_setup_issues( WP_REST_Request $request ) {
		$keys = $request->get_param( 'keys' );
		SetupWizard::dismiss_issues( is_array( $keys ) ? $keys : array() );
		return $this->get_setup_issues();
	}

	/**
	 * Record whether a test email sent without an SMTP service arrived.
	 *
	 * @param WP_REST_Request $request Request with confirmed (bool).
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function confirm_host_mail( WP_REST_Request $request ) {
		$confirmed = rest_sanitize_boolean( $request->get_param( 'confirmed' ) );
		update_option( SetupWizard::HOST_MAIL_CONFIRMED_OPTION, $confirmed ? 'yes' : 'no', false );
		return $this->get_setup_issues();
	}

	/**
	 * Return everything the setup wizard needs in one request, so its steps can
	 * render filled in instead of each one loading on its own.
	 *
	 * Business name falls back to the site title (done by the business
	 * controller) and the logo falls back to the theme's custom logo, then the
	 * site icon.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.32.0
	 */
	public function get_setup_data( WP_REST_Request $request ) {
		$business = $this->get_controller_data( BusinessBasicSettingController::get_instance(), $request );
		if ( empty( $business['logo_url'] ) ) {
			$business['logo_url'] = $this->get_site_logo_url();
		}

		$ai = array();
		if ( class_exists( AISettings::class ) ) {
			$ai = AISettings::publicState();
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'business'   => $business,
				'email'      => $this->get_controller_data( EmailSettingController::get_instance(), $request ),
				'health'     => SendingHealth::get_report(),
				'host_mail'  => get_option( SetupWizard::HOST_MAIL_CONFIRMED_OPTION, '' ),
				'optin'      => $this->get_controller_data( OptinSettingController::get_instance(), $request ),
				'compliance' => $this->get_controller_data( ComplianceSettingController::get_instance(), $request ),
				'ai'         => $ai,
				'countries'  => Constants::get_country_name(),
				'states'     => Constants::get_country_state(),
			)
		);
	}

	/**
	 * Call a settings controller's get() and return its data without the
	 * `success` flag.
	 *
	 * @param SettingBaseController $controller Settings controller.
	 * @param WP_REST_Request       $request    Request object.
	 * @return array
	 * @since 1.32.0
	 */
	private function get_controller_data( $controller, WP_REST_Request $request ) {
		$response = $controller->get( $request );
		$data     = $response instanceof WP_REST_Response ? $response->get_data() : $response;
		$data     = is_array( $data ) ? $data : array();
		unset( $data['success'] );
		return $data;
	}

	/**
	 * Site logo URL: the theme's custom logo, else the site icon.
	 *
	 * @return string
	 * @since 1.32.0
	 */
	private function get_site_logo_url() {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		$icon = get_site_icon_url( 512 );
		return $icon ? $icon : '';
	}

	/**
	 * Not used — required by SettingBaseController.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	public function create_or_update( WP_REST_Request $request ) {
		// Not applicable for onboarding controller.
	}

	/**
	 * Not used — required by SettingBaseController.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return void
	 */
	public function get( WP_REST_Request $request ) {
		// Not applicable for onboarding controller.
	}
}
