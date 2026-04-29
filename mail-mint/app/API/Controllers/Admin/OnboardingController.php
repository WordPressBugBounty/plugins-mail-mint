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
	 * Send a test email to the current admin user.
	 *
	 * Validates from_name, from_email, subject, and body fields.
	 * Sends an HTML email via wp_mail() to the logged-in admin.
	 *
	 * @param WP_REST_Request $request Request object with from_name, from_email, subject, body.
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

		$current_user = wp_get_current_user();
		$to_email     = $current_user->user_email;

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

		do_action( 'mailmint_setup_wizard_complete', $goal );

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'success',
				'message' => esc_html__( 'Wizard completed.', 'mrm' ),
			)
		);
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
