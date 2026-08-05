<?php
/**
 * REST API Application Password Controller
 *
 * Lets the current user create, list, and revoke WordPress Application Passwords
 * directly from the MCP settings screen, so they never have to leave for the
 * profile page. Every credential created here is name-prefixed with "Mail Mint"
 * so this feature only ever manages its own passwords.
 *
 * GET    /mrm/v1/settings/mcp/app-passwords            — availability status + Mail Mint passwords.
 * POST   /mrm/v1/settings/mcp/app-passwords            — create a new password (plaintext returned ONCE).
 * DELETE /mrm/v1/settings/mcp/app-passwords/{uuid}     — revoke a password by uuid.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

defined( 'ABSPATH' ) || exit;

use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;
use WP_Application_Passwords;
use WP_REST_Request;

/**
 * Application Password controller for the MCP "Connect a client" flow.
 *
 * @since 1.25.0
 */
class ApplicationPasswordController extends SettingBaseController {

	use Singleton;

	/**
	 * Prefix applied to every application password created through this feature.
	 * Used both when naming new passwords and when filtering the list so we never
	 * touch application passwords the user created elsewhere.
	 */
	const NAME_PREFIX = 'Mail Mint';

	/**
	 * GET /mrm/v1/settings/mcp/app-passwords
	 *
	 * Returns the availability status and the list of Mail Mint application
	 * passwords for the current user. Never returns a plaintext value.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get( WP_REST_Request $request ) {
		return $this->get_success_response_data(
			array(
				'status'    => $this->app_passwords_status(),
				'passwords' => $this->get_mcp_passwords(),
			)
		);
	}

	/**
	 * POST /mrm/v1/settings/mcp/app-passwords
	 *
	 * Creates a new application password for the current user. The plaintext value
	 * is returned exactly once here — WordPress core stores only a hash, so it can
	 * never be re-exposed by the list endpoint. It is never written to any option,
	 * user meta, or log.
	 *
	 * Accepts: { name?: string }
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_or_update( WP_REST_Request $request ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return $this->get_error_response( __( 'Application Passwords are not available on this WordPress version.', 'mail-mint' ) );
		}

		$status = $this->app_passwords_status();
		if ( ! $status['available'] ) {
			return $this->get_error_response( $status['message'] );
		}

		$params    = MrmCommon::get_api_params_values( $request );
		$raw_name  = isset( $params['name'] ) && is_string( $params['name'] ) ? sanitize_text_field( trim( $params['name'] ) ) : '';
		$user_id   = get_current_user_id();
		$app_name  = '' !== $raw_name ? self::NAME_PREFIX . ': ' . $raw_name : self::NAME_PREFIX;

		// Avoid duplicate names — append a counter if one already exists.
		$existing = WP_Application_Passwords::get_user_application_passwords( $user_id );
		$names    = array_column( $existing, 'name' );
		if ( in_array( $app_name, $names, true ) ) {
			$i = 2;
			while ( in_array( $app_name . ' ' . $i, $names, true ) ) {
				++$i;
			}
			$app_name = $app_name . ' ' . $i;
		}

		$result = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => $app_name ) );

		if ( is_wp_error( $result ) ) {
			return $this->get_error_response( $result->get_error_message() );
		}

		// $result[0] is the one-time plaintext; $result[1] holds the stored item (uuid, name, ...).
		return $this->get_success_response_data(
			array(
				'password' => $result[0],
				'uuid'     => isset( $result[1]['uuid'] ) ? $result[1]['uuid'] : '',
				'name'     => $app_name,
				'username' => wp_get_current_user()->user_login,
				'message'  => __( 'Application password generated. Save it now — it will not be shown in full again.', 'mail-mint' ),
			)
		);
	}

	/**
	 * DELETE /mrm/v1/settings/mcp/app-passwords/{uuid}
	 *
	 * Revokes an application password owned by the current user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function revoke( WP_REST_Request $request ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return $this->get_error_response( __( 'Application Passwords are not available on this WordPress version.', 'mail-mint' ) );
		}

		$uuid = (string) $request->get_param( 'uuid' );
		if ( '' === $uuid ) {
			return $this->get_error_response( __( 'Missing application password identifier.', 'mail-mint' ) );
		}

		$deleted = WP_Application_Passwords::delete_application_password( get_current_user_id(), $uuid );
		if ( is_wp_error( $deleted ) ) {
			return $this->get_error_response( $deleted->get_error_message() );
		}

		return $this->get_success_response( __( 'Application password revoked.', 'mail-mint' ) );
	}

	/**
	 * Report whether WordPress Application Passwords are available, and why not if not.
	 *
	 * Distinguishes the HTTPS/local-env requirement (wp_is_application_passwords_supported())
	 * from a filter-based override (typical of security plugins hooking
	 * wp_is_application_passwords_available).
	 *
	 * @return array{available: bool, reason: string, message: string, local_http: bool}
	 */
	private function app_passwords_status() {
		if ( function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available() ) {
			return array(
				'available'  => true,
				'reason'     => 'available',
				'message'    => '',
				'local_http' => false,
			);
		}

		if ( function_exists( 'wp_is_application_passwords_supported' ) && ! wp_is_application_passwords_supported() ) {
			return array(
				'available'  => false,
				'reason'     => 'unsupported',
				'message'    => __( 'Application Passwords require HTTPS or WP_ENVIRONMENT_TYPE set to "local".', 'mail-mint' ),
				'local_http' => $this->likely_local_http(),
			);
		}

		return array(
			'available'  => false,
			'reason'     => 'filtered',
			'message'    => __( 'Application Passwords have been disabled on this site, likely by a security plugin. Check your security plugin settings (e.g. Solid Security, Wordfence, All In One WP Security) and re-enable Application Passwords to continue.', 'mail-mint' ),
			'local_http' => false,
		);
	}

	/**
	 * Return the current user's application passwords whose name begins with the Mail Mint prefix.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_mcp_passwords() {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return array();
		}

		$all    = WP_Application_Passwords::get_user_application_passwords( get_current_user_id() );
		$format = $this->datetime_format();
		$mine   = array();

		foreach ( $all as $item ) {
			$name = isset( $item['name'] ) ? (string) $item['name'] : '';
			if ( 0 !== strpos( $name, self::NAME_PREFIX ) ) {
				continue;
			}
			$mine[] = array(
				'uuid'      => isset( $item['uuid'] ) ? (string) $item['uuid'] : '',
				'name'      => $name,
				'created'   => ! empty( $item['created'] ) ? wp_date( $format, (int) $item['created'] ) : __( 'Unknown', 'mail-mint' ),
				'last_used' => ! empty( $item['last_used'] ) ? wp_date( $format, (int) $item['last_used'] ) : __( 'Never', 'mail-mint' ),
			);
		}

		return array_values( $mine );
	}

	/**
	 * Build a combined date/time format from WordPress settings, with a safe fallback.
	 *
	 * @return string
	 */
	private function datetime_format() {
		$date_format = (string) get_option( 'date_format' );
		$time_format = (string) get_option( 'time_format' );
		if ( '' === $date_format || '' === $time_format ) {
			return 'Y-m-d H:i';
		}
		return $date_format . ' ' . $time_format;
	}

	/**
	 * Best-effort guess that the site is served on a local hostname over plain HTTP,
	 * so the UI can suggest defining WP_ENVIRONMENT_TYPE as "local".
	 *
	 * @return bool
	 */
	private function likely_local_http() {
		if ( is_ssl() ) {
			return false;
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $host ) {
			return false;
		}
		if ( 'localhost' === $host || '127.0.0.1' === $host || '::1' === $host ) {
			return true;
		}
		return (bool) preg_match( '/\.(local|test|localhost|dev)$/', $host );
	}
}
