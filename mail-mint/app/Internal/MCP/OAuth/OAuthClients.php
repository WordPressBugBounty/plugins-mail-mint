<?php
/**
 * OAuthClients — stateless dynamic client registration (RFC 7591).
 *
 * Registration is open to anyone (MCP clients register themselves before the user signs
 * in), so nothing is written to the database. The client_id is the client's metadata
 * signed with an HMAC of the site's auth salt; decoding and checking the signature gives
 * the metadata back. A client_secret, when one is issued, is derived from the client_id.
 *
 * @package Mint\MRM\Internal\MCP\OAuth
 * @since 1.32.0
 */

namespace Mint\MRM\Internal\MCP\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and verifies OAuth clients.
 *
 * @since 1.32.0
 */
class OAuthClients {

	const ID_PREFIX         = 'mmc_';
	const MAX_REDIRECT_URIS = 10;
	const MAX_URI_LENGTH    = 2000;
	const MAX_NAME_LENGTH   = 100;

	/**
	 * Register a client from RFC 7591 metadata.
	 *
	 * @param array $metadata Client metadata from the request body.
	 * @return array|\WP_Error Registration response, or an error whose code is the OAuth error.
	 */
	public static function register( $metadata ) {
		$metadata = is_array( $metadata ) ? $metadata : array();

		$uris = isset( $metadata['redirect_uris'] ) ? $metadata['redirect_uris'] : null;
		if ( ! is_array( $uris ) || empty( $uris ) ) {
			return new \WP_Error( 'invalid_redirect_uri', __( 'redirect_uris must be a non-empty array.', 'mail-mint' ) );
		}
		if ( count( $uris ) > self::MAX_REDIRECT_URIS ) {
			return new \WP_Error( 'invalid_redirect_uri', __( 'Too many redirect_uris.', 'mail-mint' ) );
		}
		$uris = array_values( array_unique( $uris, SORT_REGULAR ) );
		foreach ( $uris as $uri ) {
			if ( ! self::is_allowed_redirect_uri( $uri ) ) {
				return new \WP_Error( 'invalid_redirect_uri', __( 'A redirect URI must use https, a loopback http address, or an app URL scheme.', 'mail-mint' ) );
			}
		}

		$name = isset( $metadata['client_name'] ) && is_string( $metadata['client_name'] ) ? sanitize_text_field( $metadata['client_name'] ) : '';
		$name = '' !== $name ? $name : __( 'MCP client', 'mail-mint' );
		if ( function_exists( 'mb_substr' ) ) {
			$name = mb_substr( $name, 0, self::MAX_NAME_LENGTH );
		} else {
			$name = substr( $name, 0, self::MAX_NAME_LENGTH );
		}

		if ( isset( $metadata['grant_types'] ) && is_array( $metadata['grant_types'] ) && ! in_array( 'authorization_code', $metadata['grant_types'], true ) ) {
			return new \WP_Error( 'invalid_client_metadata', __( 'Only the authorization_code grant is supported.', 'mail-mint' ) );
		}

		// Public client unless it asks for a secret. Any secret-based method is served as client_secret_post.
		$requested    = isset( $metadata['token_endpoint_auth_method'] ) ? (string) $metadata['token_endpoint_auth_method'] : 'none';
		$confidential = 'none' !== $requested;
		$issued_at    = time();

		$payload = wp_json_encode(
			array(
				'v' => 1,
				'n' => $name,
				'r' => $uris,
				'c' => $confidential ? 1 : 0,
				't' => $issued_at,
			)
		);
		if ( false === $payload ) {
			return new \WP_Error( 'invalid_client_metadata', __( 'Client metadata could not be encoded.', 'mail-mint' ) );
		}

		$client_id = self::ID_PREFIX . self::base64url( $payload ) . '.' . self::base64url( self::sign( $payload ) );

		$response = array(
			'client_id'                  => $client_id,
			'client_id_issued_at'        => $issued_at,
			'client_name'                => $name,
			'redirect_uris'              => $uris,
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => $confidential ? 'client_secret_post' : 'none',
			'scope'                      => OAuthServer::SCOPE,
		);

		if ( $confidential ) {
			$response['client_secret']            = self::secret_for( $client_id );
			$response['client_secret_expires_at'] = 0;
		}

		return $response;
	}

	/**
	 * Decode and verify a client_id.
	 *
	 * @param string $client_id Client ID.
	 * @return array|null { name: string, redirect_uris: string[], confidential: bool } or null when invalid.
	 */
	public static function get( $client_id ) {
		if ( ! is_string( $client_id ) || 0 !== strpos( $client_id, self::ID_PREFIX ) || strlen( $client_id ) > 30000 ) {
			return null;
		}

		$parts = explode( '.', substr( $client_id, strlen( self::ID_PREFIX ) ) );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		$payload   = self::base64url_decode( $parts[0] );
		$signature = self::base64url_decode( $parts[1] );
		if ( false === $payload || false === $signature || ! hash_equals( self::sign( $payload ), $signature ) ) {
			return null;
		}

		$data = json_decode( $payload, true );
		if ( ! is_array( $data ) || empty( $data['r'] ) || ! is_array( $data['r'] ) ) {
			return null;
		}

		return array(
			'name'          => isset( $data['n'] ) ? (string) $data['n'] : '',
			'redirect_uris' => array_values( array_filter( $data['r'], 'is_string' ) ),
			'confidential'  => ! empty( $data['c'] ),
		);
	}

	/**
	 * Check client authentication at the token and revocation endpoints.
	 *
	 * @param string $client_id     Client ID.
	 * @param string $client_secret Secret from the request body (may be empty).
	 * @return array|null The client, or null when authentication fails.
	 */
	public static function authenticate( $client_id, $client_secret ) {
		$client = self::get( $client_id );
		if ( null === $client ) {
			return null;
		}
		if ( $client['confidential'] && ! hash_equals( self::secret_for( $client_id ), (string) $client_secret ) ) {
			return null;
		}
		return $client;
	}

	/**
	 * Whether a redirect URI is acceptable at all.
	 *
	 * Allows https, http on a loopback host (CLI tools such as Claude Code), and private-use
	 * app schemes (RFC 8252) such as cursor:// or vscode://. Rejects schemes that can run
	 * code or read local data.
	 *
	 * @param mixed $uri Redirect URI.
	 * @return bool
	 */
	public static function is_allowed_redirect_uri( $uri ) {
		if ( ! is_string( $uri ) || '' === $uri || strlen( $uri ) > self::MAX_URI_LENGTH ) {
			return false;
		}
		if ( preg_match( '/[\s<>"\'\\\\`\x00-\x1f\x7f]/', $uri ) ) {
			return false;
		}

		$parts = wp_parse_url( $uri );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}

		$scheme = strtolower( $parts['scheme'] );
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

		if ( 'https' === $scheme ) {
			return '' !== $host;
		}
		if ( 'http' === $scheme ) {
			return self::is_loopback_host( $host );
		}

		$blocked = array( 'javascript', 'data', 'file', 'vbscript', 'about', 'blob', 'filesystem', 'ftp', 'ws', 'wss', 'mailto', 'tel', 'sms' );
		if ( in_array( $scheme, $blocked, true ) ) {
			return false;
		}

		return (bool) preg_match( '/^[a-z][a-z0-9+.\-]*$/', $scheme );
	}

	/**
	 * Whether a redirect URI sent to /authorize or /token matches one the client registered.
	 *
	 * Exact match, except that loopback http URIs may use any port (RFC 8252 section 7.3).
	 *
	 * @param array  $client Client from get().
	 * @param string $uri    Redirect URI from the request.
	 * @return bool
	 */
	public static function redirect_uri_matches( $client, $uri ) {
		if ( ! is_string( $uri ) || ! self::is_allowed_redirect_uri( $uri ) ) {
			return false;
		}

		foreach ( $client['redirect_uris'] as $registered ) {
			if ( $registered === $uri ) {
				return true;
			}
			if ( self::loopback_without_port( $registered ) !== null && self::loopback_without_port( $registered ) === self::loopback_without_port( $uri ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Normalise a loopback http URI by dropping the port.
	 *
	 * @param string $uri URI.
	 * @return string|null Null when the URI is not loopback http.
	 */
	private static function loopback_without_port( $uri ) {
		$parts = wp_parse_url( $uri );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || 'http' !== strtolower( $parts['scheme'] ) ) {
			return null;
		}
		$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		if ( ! self::is_loopback_host( $host ) ) {
			return null;
		}
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		return 'http://' . $host . $path . $query;
	}

	/**
	 * Whether a host is a loopback address.
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	public static function is_loopback_host( $host ) {
		return in_array( strtolower( (string) $host ), array( 'localhost', '127.0.0.1', '[::1]', '::1' ), true );
	}

	/**
	 * Client secret for a confidential client.
	 *
	 * @param string $client_id Client ID.
	 * @return string
	 */
	private static function secret_for( $client_id ) {
		return self::base64url( hash_hmac( 'sha256', 'secret|' . $client_id, self::key(), true ) );
	}

	/**
	 * HMAC signature of a client payload.
	 *
	 * @param string $payload Payload.
	 * @return string Raw binary signature.
	 */
	private static function sign( $payload ) {
		return hash_hmac( 'sha256', 'client|' . $payload, self::key(), true );
	}

	/**
	 * Signing key, derived from the site's auth salt.
	 *
	 * @return string
	 */
	private static function key() {
		return wp_salt( 'auth' ) . '|mail-mint-mcp-oauth';
	}

	/**
	 * Base64url encode without padding.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	public static function base64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $data Data.
	 * @return string|false
	 */
	private static function base64url_decode( $data ) {
		if ( ! is_string( $data ) || ! preg_match( '/^[A-Za-z0-9_-]*$/', $data ) ) {
			return false;
		}
		return base64_decode( strtr( $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
}
