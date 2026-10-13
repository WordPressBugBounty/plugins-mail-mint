<?php
/**
 * OAuthTokens — authorization codes, access tokens, and refresh tokens.
 *
 * Each approved connection is a "grant" stored in the user's meta, in the same way
 * WordPress core stores Application Passwords. Only SHA-256 hashes of the tokens are
 * stored. Tokens carry the user ID and grant ID in clear text, so looking one up never
 * needs a table scan:
 *
 *   access:  mma_{user_id}_{grant_id}_{secret}
 *   refresh: mmr_{user_id}_{grant_id}_{secret}
 *
 * Authorization codes are single-use and live in a 10-minute transient.
 *
 * @package Mint\MRM\Internal\MCP\OAuth
 * @since 1.32.0
 */

namespace Mint\MRM\Internal\MCP\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Issues, validates, refreshes, and revokes tokens.
 *
 * @since 1.32.0
 */
class OAuthTokens {

	const META_KEY        = '_mint_mcp_oauth_grants';
	const USED_META_KEY   = '_mint_mcp_oauth_last_used';
	const CODE_PREFIX     = 'mint_mcp_code_';
	const CODE_TTL        = 600;
	const MAX_GRANTS      = 25;
	const LAST_USED_EVERY = 60;
	const TOKEN_PATTERN   = '/^(mma|mmr)_(\d+)_([a-f0-9]{16})_([a-f0-9]{48})$/';

	/**
	 * Access token lifetime in seconds.
	 *
	 * @return int
	 */
	private static function access_ttl() {
		return max( 300, (int) apply_filters( 'mail_mint/mcp_oauth_access_token_ttl', HOUR_IN_SECONDS ) );
	}

	/**
	 * Refresh token lifetime in seconds. Each refresh renews it, so a connection that is
	 * in use stays connected; one left idle this long must be connected again.
	 *
	 * @return int
	 */
	private static function refresh_ttl() {
		return max( DAY_IN_SECONDS, (int) apply_filters( 'mail_mint/mcp_oauth_refresh_token_ttl', 90 * DAY_IN_SECONDS ) );
	}

	/**
	 * Create a single-use authorization code.
	 *
	 * @param array $data { client_id, redirect_uri, code_challenge, user_id, resource }.
	 * @return string The code.
	 */
	public static function create_code( $data ) {
		$code            = bin2hex( random_bytes( 32 ) );
		$data['expires'] = time() + self::CODE_TTL;
		set_transient( self::CODE_PREFIX . hash( 'sha256', $code ), $data, self::CODE_TTL );
		return $code;
	}

	/**
	 * Fetch and delete an authorization code. A code can only be redeemed once.
	 *
	 * @param string $code Code.
	 * @return array|null
	 */
	public static function consume_code( $code ) {
		if ( ! is_string( $code ) || ! preg_match( '/^[a-f0-9]{64}$/', $code ) ) {
			return null;
		}
		$key  = self::CODE_PREFIX . hash( 'sha256', $code );
		$data = get_transient( $key );

		// Only the request that actually deletes the code may redeem it, so two parallel
		// redemptions cannot both succeed.
		if ( false === $data || ! delete_transient( $key ) ) {
			return null;
		}

		if ( ! is_array( $data ) || empty( $data['expires'] ) || (int) $data['expires'] < time() ) {
			return null;
		}
		return $data;
	}

	/**
	 * Create a new grant for a user and return its tokens.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $client_id Client ID.
	 * @param array  $client    Client from OAuthClients::get().
	 * @param string $redirect_uri Redirect URI the user approved.
	 * @return array Token response.
	 */
	public static function create_grant( $user_id, $client_id, $client, $redirect_uri ) {
		$grants   = self::get_grants( $user_id );
		$grant_id = bin2hex( random_bytes( 8 ) );
		$now      = time();

		$grants[ $grant_id ] = array(
			'client_id'     => $client_id,
			'client_name'   => $client['name'],
			'redirect_host' => self::describe_redirect( $redirect_uri ),
			'created'       => $now,
		);

		$tokens = self::rotate( $grants[ $grant_id ], $user_id, $grant_id );
		self::save_grants( $user_id, self::prune( $grants ) );

		return $tokens;
	}

	/**
	 * Exchange a refresh token for a new access + refresh token pair.
	 *
	 * @param string $refresh_token Refresh token.
	 * @param string $client_id     Client ID that must own the grant.
	 * @return array|null Token response, or null when the refresh token is not valid.
	 */
	public static function refresh( $refresh_token, $client_id ) {
		$parsed = self::parse( $refresh_token, 'mmr' );
		if ( null === $parsed ) {
			return null;
		}
		list( $user_id, $grant_id, $secret ) = $parsed;

		$grants = self::get_grants( $user_id );
		if ( ! isset( $grants[ $grant_id ] ) ) {
			return null;
		}
		$grant = $grants[ $grant_id ];

		if ( empty( $grant['refresh_hash'] ) || ! hash_equals( $grant['refresh_hash'], hash( 'sha256', $secret ) ) ) {
			return null;
		}
		if ( (int) $grant['refresh_expires'] < time() || ! hash_equals( (string) $grant['client_id'], (string) $client_id ) ) {
			return null;
		}
		if ( ! get_userdata( $user_id ) ) {
			return null;
		}

		$tokens              = self::rotate( $grant, $user_id, $grant_id );
		$grants[ $grant_id ] = $grant;
		self::save_grants( $user_id, $grants );

		return $tokens;
	}

	/**
	 * Validate an access token.
	 *
	 * @param string $token Access token.
	 * @return int User ID, or 0 when the token is not valid.
	 */
	public static function validate_access_token( $token ) {
		$parsed = self::parse( $token, 'mma' );
		if ( null === $parsed ) {
			return 0;
		}
		list( $user_id, $grant_id, $secret ) = $parsed;

		$grants = self::get_grants( $user_id );
		if ( ! isset( $grants[ $grant_id ] ) ) {
			return 0;
		}
		$grant = $grants[ $grant_id ];

		if ( empty( $grant['access_hash'] ) || ! hash_equals( $grant['access_hash'], hash( 'sha256', $secret ) ) ) {
			return 0;
		}
		if ( (int) $grant['access_expires'] < time() ) {
			return 0;
		}
		if ( ! get_userdata( $user_id ) ) {
			return 0;
		}

		// Kept in its own meta key: writing the grants array back here could overwrite a
		// refresh that rotated the tokens in a parallel request.
		$used = self::get_last_used( $user_id );
		if ( time() - ( isset( $used[ $grant_id ] ) ? (int) $used[ $grant_id ] : 0 ) > self::LAST_USED_EVERY ) {
			$used[ $grant_id ] = time();
			update_user_meta( $user_id, self::USED_META_KEY, array_intersect_key( $used, $grants ) );
		}

		return $user_id;
	}

	/**
	 * Revoke the grant an access or refresh token belongs to (RFC 7009).
	 *
	 * @param string $token     Access or refresh token.
	 * @param string $client_id Client ID that must own the grant.
	 * @return void
	 */
	public static function revoke_token( $token, $client_id ) {
		$type   = is_string( $token ) && 0 === strpos( $token, 'mmr_' ) ? 'mmr' : 'mma';
		$parsed = self::parse( $token, $type );
		if ( null === $parsed ) {
			return;
		}
		list( $user_id, $grant_id, $secret ) = $parsed;

		$grants = self::get_grants( $user_id );
		if ( ! isset( $grants[ $grant_id ] ) || ! hash_equals( (string) $grants[ $grant_id ]['client_id'], (string) $client_id ) ) {
			return;
		}
		$hash_key = 'mmr' === $type ? 'refresh_hash' : 'access_hash';
		if ( empty( $grants[ $grant_id ][ $hash_key ] ) || ! hash_equals( $grants[ $grant_id ][ $hash_key ], hash( 'sha256', $secret ) ) ) {
			return;
		}

		unset( $grants[ $grant_id ] );
		self::save_grants( $user_id, $grants );
	}

	/**
	 * Revoke a grant by ID (from the settings screen).
	 *
	 * @param int    $user_id  User ID.
	 * @param string $grant_id Grant ID.
	 * @return bool Whether a grant was removed.
	 */
	public static function revoke_grant( $user_id, $grant_id ) {
		$grants = self::get_grants( $user_id );
		if ( ! isset( $grants[ $grant_id ] ) ) {
			return false;
		}
		unset( $grants[ $grant_id ] );
		self::save_grants( $user_id, $grants );
		return true;
	}

	/**
	 * Active connections for a user, newest first, for the settings screen. Never includes hashes.
	 *
	 * @param int $user_id User ID.
	 * @return array[]
	 */
	public static function list_connections( $user_id ) {
		$list = array();
		$used = self::get_last_used( $user_id );
		foreach ( self::prune( self::get_grants( $user_id ) ) as $grant_id => $grant ) {
			$list[] = array(
				'id'            => (string) $grant_id,
				'client_name'   => (string) $grant['client_name'],
				'redirect_host' => (string) $grant['redirect_host'],
				'created'       => (int) $grant['created'],
				'last_used'     => isset( $used[ $grant_id ] ) ? (int) $used[ $grant_id ] : 0,
			);
		}
		usort(
			$list,
			function ( $a, $b ) {
				return $b['created'] - $a['created'];
			}
		);
		return $list;
	}

	/**
	 * Issue a fresh token pair into a grant (by reference) and return the token response.
	 *
	 * @param array  $grant    Grant, updated in place.
	 * @param int    $user_id  User ID.
	 * @param string $grant_id Grant ID.
	 * @return array
	 */
	private static function rotate( &$grant, $user_id, $grant_id ) {
		$access_secret  = bin2hex( random_bytes( 24 ) );
		$refresh_secret = bin2hex( random_bytes( 24 ) );
		$now            = time();

		$grant['access_hash']     = hash( 'sha256', $access_secret );
		$grant['access_expires']  = $now + self::access_ttl();
		$grant['refresh_hash']    = hash( 'sha256', $refresh_secret );
		$grant['refresh_expires'] = $now + self::refresh_ttl();

		return array(
			'access_token'  => 'mma_' . $user_id . '_' . $grant_id . '_' . $access_secret,
			'token_type'    => 'Bearer',
			'expires_in'    => self::access_ttl(),
			'refresh_token' => 'mmr_' . $user_id . '_' . $grant_id . '_' . $refresh_secret,
			'scope'         => OAuthServer::SCOPE,
		);
	}

	/**
	 * Split a token into user ID, grant ID, and secret.
	 *
	 * @param string $token Token.
	 * @param string $type  Expected prefix: 'mma' or 'mmr'.
	 * @return array|null [ user_id, grant_id, secret ]
	 */
	private static function parse( $token, $type ) {
		if ( ! is_string( $token ) || ! preg_match( self::TOKEN_PATTERN, $token, $m ) || $m[1] !== $type ) {
			return null;
		}
		$user_id = (int) $m[2];
		return $user_id > 0 ? array( $user_id, $m[3], $m[4] ) : null;
	}

	/**
	 * Drop expired grants, then keep only the newest MAX_GRANTS.
	 *
	 * @param array $grants Grants keyed by ID.
	 * @return array
	 */
	private static function prune( $grants ) {
		$now    = time();
		$grants = array_filter(
			$grants,
			function ( $grant ) use ( $now ) {
				return is_array( $grant ) && isset( $grant['refresh_expires'] ) && (int) $grant['refresh_expires'] >= $now;
			}
		);

		if ( count( $grants ) > self::MAX_GRANTS ) {
			uasort(
				$grants,
				function ( $a, $b ) {
					return (int) $b['created'] - (int) $a['created'];
				}
			);
			$grants = array_slice( $grants, 0, self::MAX_GRANTS, true );
		}

		return $grants;
	}

	/**
	 * Short, human-readable form of a redirect URI for the settings list.
	 *
	 * @param string $uri Redirect URI.
	 * @return string
	 */
	public static function describe_redirect( $uri ) {
		$parts  = wp_parse_url( $uri );
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

		if ( 'https' === $scheme || 'http' === $scheme ) {
			return OAuthClients::is_loopback_host( $host ) ? __( 'an app on this computer', 'mail-mint' ) : $host;
		}
		return $scheme . '://' . $host;
	}

	/**
	 * Read a user's grants.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private static function get_grants( $user_id ) {
		$grants = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $grants ) ? $grants : array();
	}

	/**
	 * Read a user's last-used timestamps, keyed by grant ID.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private static function get_last_used( $user_id ) {
		$used = get_user_meta( $user_id, self::USED_META_KEY, true );
		return is_array( $used ) ? $used : array();
	}

	/**
	 * Save a user's grants.
	 *
	 * @param int   $user_id User ID.
	 * @param array $grants  Grants.
	 * @return void
	 */
	private static function save_grants( $user_id, $grants ) {
		if ( empty( $grants ) ) {
			delete_user_meta( $user_id, self::META_KEY );
			delete_user_meta( $user_id, self::USED_META_KEY );
			return;
		}
		update_user_meta( $user_id, self::META_KEY, $grants );
	}
}
