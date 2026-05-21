<?php
/**
 * REST API Email Setting Controller
 *
 * Handles requests to the Email setting endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Internal\Admin\CreateContact;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use Mint\MRM\Utilites\Helper\Email;
use WP_REST_Response;

/**
 * This is the main class that controls the email setting feature. Its responsibilities are:
 *
 * - Create or update email settings
 * - Retrieve email settings from options table
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class EmailSettingController extends SettingBaseController {

	use Singleton;

	/**
	 * Settings object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args;

	/**
	 * Get and send response to create or update a new settings
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		// From name validation.
		$from_name = isset( $params['from_name'] ) ? $params['from_name'] : '';

		if ( strlen( $from_name ) > 150 ) {
			return $this->get_error_response( __( 'From name character limit exceeded 150 characters', 'mrm' ) );
		}

		if ( empty( $from_name ) ) {
			return $this->get_error_response( __( 'From name is required', 'mrm' ) );
		}

		// From email address validation.
		$from_email = isset( $params['from_email'] ) ? $params['from_email'] : '';
		if ( ! is_email( $from_email ) || empty( $from_email ) ) {
			return $this->get_error_response( __( 'Enter a valid email address from where to send email', 'mrm' ) );
		}

		// From name validation.
		$reply_name = isset( $params['reply_name'] ) ? $params['reply_name'] : '';

		if ( strlen( $reply_name ) > 150 ) {
			return $this->get_error_response( __( 'From name character limit exceeded 150 characters', 'mrm' ) );
		}

		if ( empty( $reply_name ) ) {
			return $this->get_error_response( __( 'Reply name is required', 'mrm' ) );
		}

		// Reply to email address validation.
		$reply_email = isset( $params['reply_email'] ) ? $params['reply_email'] : '';
		if ( ! is_email( $reply_email ) || empty( $reply_email ) ) {
			return $this->get_error_response( __( 'Enter a valid email address where to reply email', 'mrm' ) );
		}

        $email_frequency = !empty( $params['email_frequency'] ) ? $params['email_frequency'] : [];
		$email_settings = array(
			'from_name'       => $from_name,
			'from_email'      => $from_email,
			'reply_name'      => $reply_name,
			'reply_email'     => $reply_email,
            'email_frequency' => $email_frequency,
			'bounce_tracking' => !empty( $params['bounce_tracking'] ) ? $params['bounce_tracking'] : array('enable' => false, 'esp'  => array('value' => 'ses', 'label' => 'Amazon SES')),
		);
		// enqueue to wp option table.
		update_option( '_mrm_email_settings', $email_settings );
		return $this->get_success_response( __( 'Email settings have been successfully saved.', 'mrm' ) );
	}


	/**
	 * Function used to handle a single get request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get( WP_REST_Request $request ) {
		// Get default value for email settings.
		$default = Email::default_email_settings();
		$settings = get_option( '_mrm_email_settings', $default );

		// Check if the 'email_frequency' key exists in $settings.
		if (!isset($settings['email_frequency'])) {
			// If it doesn't exist, add the default 'email_frequency' array to $settings.
			$settings = array_merge($settings, ['email_frequency' => $default['email_frequency']]);
		}

		// Check if the 'bounce_tracking' key exists in $settings.
		if (!isset($settings['bounce_tracking'])) {
			// If it doesn't exist, add the default 'bounce_tracking' array to $settings.
			$settings = array_merge($settings, ['bounce_tracking' => $default['bounce_tracking']]);
		}

		return $this->get_success_response_data( $settings );
	}
}
