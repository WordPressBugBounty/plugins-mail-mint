<?php
/**
 * OAuthRoutes — REST endpoints for the MCP OAuth flow.
 *
 * Public (called by MCP clients before the user is known):
 *   GET  /mrm/v1/mcp/oauth/protected-resource      — RFC 9728 metadata.
 *   GET  /mrm/v1/mcp/oauth/authorization-server    — RFC 8414 metadata (copy of the /.well-known document).
 *   POST /mrm/v1/mcp/oauth/register                — RFC 7591 dynamic client registration.
 *   POST /mrm/v1/mcp/oauth/token                   — authorization_code and refresh_token grants.
 *   POST /mrm/v1/mcp/oauth/revoke                  — RFC 7009 token revocation.
 *
 * Settings screen (current user's own connections):
 *   GET    /mrm/v1/settings/mcp/connections
 *   DELETE /mrm/v1/settings/mcp/connections/{id}
 *
 * @package Mint\MRM\Internal\MCP\OAuth
 * @since 1.32.0
 */

namespace Mint\MRM\Internal\MCP\OAuth;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Utilities\Helper\PermissionManager;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers and handles the OAuth REST routes.
 *
 * @since 1.32.0
 */
class OAuthRoutes {

	const REST_NAMESPACE = 'mrm/v1';

	/**
	 * Register routes. The OAuth routes exist only while the flow is enabled; the
	 * connections routes always exist so users can still revoke old connections.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( OAuthServer::is_enabled() ) {
			register_rest_route(
				self::REST_NAMESPACE,
				'/mcp/oauth/protected-resource',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'protected_resource' ),
					'permission_callback' => '__return_true',
				)
			);
			register_rest_route(
				self::REST_NAMESPACE,
				'/mcp/oauth/authorization-server',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'authorization_server' ),
					'permission_callback' => '__return_true',
				)
			);
			register_rest_route(
				self::REST_NAMESPACE,
				'/mcp/oauth/register',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'register_client' ),
					'permission_callback' => '__return_true',
				)
			);
			register_rest_route(
				self::REST_NAMESPACE,
				'/mcp/oauth/token',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'token' ),
					'permission_callback' => '__return_true',
				)
			);
			register_rest_route(
				self::REST_NAMESPACE,
				'/mcp/oauth/revoke',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'revoke' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings/mcp/connections',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_connections' ),
				'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings/mcp/connections/(?P<id>[a-f0-9]{16})',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_connection' ),
				'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
			)
		);
	}

	/**
	 * GET protected resource metadata.
	 *
	 * @return WP_REST_Response
	 */
	public static function protected_resource() {
		return self::public_json( OAuthServer::protected_resource_metadata() );
	}

	/**
	 * GET authorization server metadata.
	 *
	 * @return WP_REST_Response
	 */
	public static function authorization_server() {
		return self::public_json( OAuthServer::authorization_server_metadata() );
	}

	/**
	 * POST dynamic client registration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function register_client( WP_REST_Request $request ) {
		$metadata = $request->get_json_params();
		if ( ! is_array( $metadata ) ) {
			$metadata = $request->get_body_params();
		}

		$result = OAuthClients::register( $metadata );
		if ( is_wp_error( $result ) ) {
			return self::oauth_error( $result->get_error_code(), $result->get_error_message(), 400 );
		}

		$response = new WP_REST_Response( $result, 201 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * POST token endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function token( WP_REST_Request $request ) {
		$grant_type = (string) $request->get_param( 'grant_type' );
		$client_id  = (string) $request->get_param( 'client_id' );

		if ( '' === $client_id ) {
			return self::oauth_error( 'invalid_request', __( 'client_id is required.', 'mail-mint' ), 400 );
		}
		$client = OAuthClients::authenticate( $client_id, (string) $request->get_param( 'client_secret' ) );
		if ( null === $client ) {
			return self::oauth_error( 'invalid_client', __( 'Client authentication failed. Remove the connector and add it again.', 'mail-mint' ), 401 );
		}

		$resource = (string) $request->get_param( 'resource' );
		if ( '' !== $resource && ! OAuthServer::resource_matches( $resource ) ) {
			return self::oauth_error( 'invalid_target', __( 'The resource is not this site\'s MCP endpoint.', 'mail-mint' ), 400 );
		}

		if ( 'authorization_code' === $grant_type ) {
			return self::exchange_code( $request, $client_id, $client );
		}

		if ( 'refresh_token' === $grant_type ) {
			$tokens = OAuthTokens::refresh( (string) $request->get_param( 'refresh_token' ), $client_id );
			if ( null === $tokens ) {
				return self::oauth_error( 'invalid_grant', __( 'The refresh token is invalid, expired, or revoked.', 'mail-mint' ), 400 );
			}
			return self::token_response( $tokens );
		}

		return self::oauth_error( 'unsupported_grant_type', __( 'Only authorization_code and refresh_token are supported.', 'mail-mint' ), 400 );
	}

	/**
	 * Redeem an authorization code (PKCE S256 required).
	 *
	 * @param WP_REST_Request $request   Request.
	 * @param string          $client_id Client ID.
	 * @param array           $client    Client.
	 * @return WP_REST_Response
	 */
	private static function exchange_code( WP_REST_Request $request, $client_id, $client ) {
		$data = OAuthTokens::consume_code( (string) $request->get_param( 'code' ) );
		if ( null === $data || ! hash_equals( (string) $data['client_id'], $client_id ) ) {
			return self::oauth_error( 'invalid_grant', __( 'The authorization code is invalid, expired, or already used.', 'mail-mint' ), 400 );
		}

		$redirect_uri = (string) $request->get_param( 'redirect_uri' );
		if ( '' !== $redirect_uri && $redirect_uri !== $data['redirect_uri'] ) {
			return self::oauth_error( 'invalid_grant', __( 'redirect_uri does not match the authorization request.', 'mail-mint' ), 400 );
		}

		$verifier = (string) $request->get_param( 'code_verifier' );
		if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $verifier ) ) {
			return self::oauth_error( 'invalid_grant', __( 'A valid code_verifier is required.', 'mail-mint' ), 400 );
		}
		$challenge = OAuthClients::base64url( hash( 'sha256', $verifier, true ) );
		if ( ! hash_equals( (string) $data['code_challenge'], $challenge ) ) {
			return self::oauth_error( 'invalid_grant', __( 'PKCE verification failed.', 'mail-mint' ), 400 );
		}

		$user_id = (int) $data['user_id'];
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return self::oauth_error( 'invalid_grant', __( 'The user who approved this connection no longer exists.', 'mail-mint' ), 400 );
		}

		return self::token_response( OAuthTokens::create_grant( $user_id, $client_id, $client, (string) $data['redirect_uri'] ) );
	}

	/**
	 * POST token revocation. Always answers 200, as RFC 7009 requires.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function revoke( WP_REST_Request $request ) {
		$client_id = (string) $request->get_param( 'client_id' );
		$client    = OAuthClients::authenticate( $client_id, (string) $request->get_param( 'client_secret' ) );
		if ( null === $client ) {
			return self::oauth_error( 'invalid_client', __( 'Client authentication failed.', 'mail-mint' ), 401 );
		}

		OAuthTokens::revoke_token( (string) $request->get_param( 'token' ), $client_id );

		$response = new WP_REST_Response( null, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * GET the current user's connections.
	 *
	 * @return WP_REST_Response
	 */
	public static function list_connections() {
		$format      = self::datetime_format();
		$connections = array();

		foreach ( OAuthTokens::list_connections( get_current_user_id() ) as $item ) {
			$connections[] = array(
				'id'            => $item['id'],
				'client_name'   => $item['client_name'],
				'redirect_host' => $item['redirect_host'],
				'created'       => wp_date( $format, $item['created'] ),
				'last_used'     => $item['last_used'] ? wp_date( $format, $item['last_used'] ) : __( 'Never', 'mail-mint' ),
			);
		}

		return new WP_REST_Response(
			array(
				'success'       => true,
				'oauth_enabled' => OAuthServer::is_enabled(),
				'connections'   => $connections,
			),
			200
		);
	}

	/**
	 * DELETE one of the current user's connections.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function delete_connection( WP_REST_Request $request ) {
		$removed = OAuthTokens::revoke_grant( get_current_user_id(), (string) $request->get_param( 'id' ) );

		return new WP_REST_Response(
			array(
				'success' => $removed,
				'message' => $removed
					? __( 'Connection removed. That app can no longer access Mail Mint.', 'mail-mint' )
					: __( 'Connection not found.', 'mail-mint' ),
			),
			$removed ? 200 : 404
		);
	}

	/**
	 * JSON response for public metadata, readable cross-origin.
	 *
	 * @param array $data Data.
	 * @return WP_REST_Response
	 */
	private static function public_json( $data ) {
		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'Access-Control-Allow-Origin', '*' );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * Token success response.
	 *
	 * @param array $tokens Tokens.
	 * @return WP_REST_Response
	 */
	private static function token_response( $tokens ) {
		$response = new WP_REST_Response( $tokens, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	/**
	 * OAuth error response (RFC 6749 section 5.2).
	 *
	 * @param string $code        OAuth error code.
	 * @param string $description Human-readable description.
	 * @param int    $status      HTTP status.
	 * @return WP_REST_Response
	 */
	private static function oauth_error( $code, $description, $status ) {
		$response = new WP_REST_Response(
			array(
				'error'             => $code,
				'error_description' => $description,
			),
			$status
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Combined date/time format from WordPress settings.
	 *
	 * @return string
	 */
	private static function datetime_format() {
		$date_format = (string) get_option( 'date_format' );
		$time_format = (string) get_option( 'time_format' );
		if ( '' === $date_format || '' === $time_format ) {
			return 'Y-m-d H:i';
		}
		return $date_format . ' ' . $time_format;
	}
}
