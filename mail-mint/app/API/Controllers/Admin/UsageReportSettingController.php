<?php
/**
 * REST API Usage Report Setting Controller
 *
 * Handles GET and POST requests for the /settings/usage-report endpoint.
 * Stores preferences in wp_options and reschedules the WP-Cron digest event
 * whenever settings are saved.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Internal\UsageReport\UsageReportScheduler;
use WP_REST_Request;

/**
 * Controls the usage-report digest settings.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class UsageReportSettingController extends SettingBaseController {

	use Singleton;

	/**
	 * wp_options key for this setting.
	 *
	 * @var string
	 */
	const OPTION_KEY = '_mint_usage_report_settings';

	/**
	 * Default values returned when the option has never been saved.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'enabled'    => true,
			'frequency'  => 'weekly',
			'week_day'   => 'monday',
			'month_day'  => 1,
			'subject'    => '{frequency} insights from Mail Mint - {period}',
			'recipients' => '',
		);
	}

	/**
	 * Return the current usage-report settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get( WP_REST_Request $request ) {
		$stored   = get_option( self::OPTION_KEY, array() );
		$settings = wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			$this->defaults()
		);

		return $this->get_success_response_data( array( 'settings' => $settings ) );
	}

	/**
	 * Save usage-report settings and reschedule the cron event.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) || empty( $params ) ) {
			return $this->get_error_response(
				__( 'No settings data was provided.', 'mrm' )
			);
		}

		$allowed_keys = array_keys( $this->defaults() );
		$sanitized    = array();

		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}

			switch ( $key ) {
				case 'enabled':
					$sanitized[ $key ] = (bool) $params[ $key ];
					break;

				case 'frequency':
					$sanitized[ $key ] = in_array( $params[ $key ], array( 'weekly', 'monthly' ), true )
						? $params[ $key ]
						: 'weekly';
					break;

				case 'week_day':
					$valid_days        = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
					$sanitized[ $key ] = in_array( strtolower( (string) $params[ $key ] ), $valid_days, true )
						? strtolower( (string) $params[ $key ] )
						: 'monday';
					break;

				case 'month_day':
					$day               = (int) $params[ $key ];
					$sanitized[ $key ] = ( $day >= 1 && $day <= 31 ) ? $day : 1;
					break;

				case 'subject':
					$sanitized[ $key ] = sanitize_text_field( $params[ $key ] );
					break;

				case 'recipients':
					$sanitized[ $key ] = sanitize_text_field( $params[ $key ] );
					break;
			}
		}

		$previous    = get_option( self::OPTION_KEY, array() );
		$was_enabled = ! empty( $previous['enabled'] );
		$merged      = wp_parse_args( $sanitized, $this->defaults() );

		// Record enable timestamp and first-send flag whenever digest is (re-)enabled.
		if ( ! empty( $merged['enabled'] ) && ! $was_enabled ) {
			update_option( 'usage_digest_enabled_at', time() );
			update_option( 'usage_digest_is_first_send', true );
		}

		update_option( self::OPTION_KEY, $merged );

		// Reschedule the cron event to reflect the new schedule.
		UsageReportScheduler::reschedule( $merged );

		return $this->get_success_response(
			__( 'Usage report settings have been saved successfully.', 'mrm' )
		);
	}

	/**
	 * Send an immediate test digest to the supplied email address.
	 *
	 * @param WP_REST_Request $request Request object.
	 *   Body: { test_email: string, settings?: object }
	 * @return \WP_REST_Response
	 */
	public function send_test( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$raw    = isset( $params['test_email'] ) ? (string) $params['test_email'] : '';

		$addresses = array_map( 'trim', explode( ',', $raw ) );
		$valid     = array_values( array_filter( $addresses, 'is_email' ) );

		if ( empty( $valid ) ) {
			return $this->get_error_response(
				__( 'Please provide at least one valid email address.', 'mrm' )
			);
		}

		// Use the settings from the request body when provided (allows previewing
		// unsaved changes), otherwise fall back to the stored settings.
		$settings = isset( $params['settings'] ) && is_array( $params['settings'] )
			? wp_parse_args( $params['settings'], $this->defaults() )
			: wp_parse_args( get_option( self::OPTION_KEY, array() ), $this->defaults() );

		$sent = UsageReportScheduler::send_test( $valid, $settings );

		if ( $sent ) {
			return $this->get_success_response(
				/* translators: %s: comma-separated recipient email addresses */
				sprintf( __( 'Test report sent to %s.', 'mrm' ), implode( ', ', $valid ) )
			);
		}

		return $this->get_error_response(
			__( 'Failed to send test report. Please check your email configuration.', 'mrm' )
		);
	}
}
