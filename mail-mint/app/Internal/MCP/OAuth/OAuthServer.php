<?php
/**
 * OAuthServer — one-click OAuth 2.1 connect for the Mail Mint MCP endpoint.
 *
 * Lets an MCP client (Claude, Claude Code, Cursor, VS Code, Codex, ...) connect with only
 * the MCP URL: the client gets a 401 challenge, discovers the metadata below, registers
 * itself (RFC 7591), sends the user through the WordPress login + consent screen, and then
 * calls the MCP endpoint with a Bearer token.
 *
 * Responsibilities:
 *   - Serve discovery metadata (RFC 9728 protected resource, RFC 8414 authorization server).
 *   - Add the WWW-Authenticate challenge to 401 responses from the MCP route.
 *   - Authenticate Bearer tokens, for the MCP route only.
 *   - Wire the authorize screen and the REST endpoints.
 *
 * Application Password (Basic auth) access keeps working unchanged next to this.
 *
 * @package Mint\MRM\Internal\MCP\OAuth
 * @since 1.32.0
 */

namespace Mint\MRM\Internal\MCP\OAuth;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\MCP\MCPInit;

/**
 * Bootstraps the OAuth layer.
 *
 * @since 1.32.0
 */
class OAuthServer {

	/**
	 * Single scope granted to every token. The token acts as the user, and every MCP tool
	 * still runs its own capability check, so finer scopes would add no protection.
	 */
	const SCOPE = 'mcp';

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * User ID authenticated by a Bearer token in this request, or 0.
	 *
	 * @var int
	 */
	private static $bearer_user = 0;

	/**
	 * Whether the outer REST dispatch was already checked.
	 *
	 * @var bool
	 */
	private static $dispatch_checked = false;

	/**
	 * Register hooks. Called at plugin load: `determine_current_user` runs before `init`,
	 * so the Bearer filter has to be in place before that.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_filter( 'determine_current_user', array( __CLASS__, 'authenticate_bearer' ), 25 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'restrict_bearer_to_mcp' ), 0, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'add_challenge_header' ), 10, 3 );
		add_filter( 'rest_exposed_cors_headers', array( __CLASS__, 'expose_cors_headers' ) );
		add_filter( 'do_parse_request', array( __CLASS__, 'maybe_serve_well_known' ), 1, 1 );
		add_action( 'rest_api_init', array( OAuthRoutes::class, 'register_routes' ) );
		add_action( 'init', array( OAuthAuthorize::class, 'maybe_handle' ), 1 );
		add_action( 'login_form_' . OAuthAuthorize::ACTION, array( OAuthAuthorize::class, 'handle' ) );
		add_action( 'admin_init', array( OAuthAuthorize::class, 'resume_pending' ), 1 );
		add_action( 'template_redirect', array( OAuthAuthorize::class, 'resume_pending' ), 1 );
	}

	/**
	 * Whether the OAuth connect flow is available on this site.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! self::is_mcp_option_on() || ! function_exists( 'wp_register_ability' ) ) {
			return false;
		}

		/**
		 * Filter whether the one-click OAuth connect flow is available.
		 * Application Password access is not affected by this filter.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'mail_mint/mcp_oauth_enabled', true );
	}

	/**
	 * Whether the MCP server option is on.
	 *
	 * @return bool
	 */
	private static function is_mcp_option_on() {
		return 'yes' === get_option( '_mrm_mcp_enabled', 'yes' );
	}

	/**
	 * Authorization server issuer identifier (the site home URL).
	 *
	 * @return string
	 */
	public static function issuer() {
		return untrailingslashit( home_url() );
	}

	/**
	 * The protected resource identifier — the MCP endpoint URL.
	 *
	 * @return string
	 */
	public static function resource() {
		return MCPInit::getEndpointUrl();
	}

	/**
	 * Whether a `resource` value sent by a client points at the MCP endpoint.
	 *
	 * @param string $resource Resource indicator (RFC 8707).
	 * @return bool
	 */
	public static function resource_matches( $resource ) {
		return untrailingslashit( (string) $resource ) === untrailingslashit( self::resource() );
	}

	/**
	 * URL of the browser authorize screen.
	 *
	 * A front-end URL, not wp-login.php (see OAuthAuthorize for why). Works with plain permalinks.
	 *
	 * @return string
	 */
	public static function authorize_url() {
		return add_query_arg( OAuthAuthorize::QUERY_VAR, 'authorize', home_url( '/' ) );
	}

	/**
	 * The real wp-login.php URL. Built from site_url() rather than wp_login_url(): plugins
	 * such as EDD point the login_url filter at a front-end page that may drop redirect_to,
	 * while hide-login plugins filter site_url() too.
	 *
	 * @return string
	 */
	public static function login_page_url() {
		return site_url( 'wp-login.php', 'login' );
	}

	/**
	 * Authorization server metadata (RFC 8414).
	 *
	 * @return array
	 */
	public static function authorization_server_metadata() {
		return array(
			'issuer'                                     => self::issuer(),
			'authorization_endpoint'                     => self::authorize_url(),
			'token_endpoint'                             => rest_url( OAuthRoutes::REST_NAMESPACE . '/mcp/oauth/token' ),
			'registration_endpoint'                      => rest_url( OAuthRoutes::REST_NAMESPACE . '/mcp/oauth/register' ),
			'revocation_endpoint'                        => rest_url( OAuthRoutes::REST_NAMESPACE . '/mcp/oauth/revoke' ),
			'response_types_supported'                   => array( 'code' ),
			'response_modes_supported'                   => array( 'query' ),
			'grant_types_supported'                      => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'           => array( 'S256' ),
			'token_endpoint_auth_methods_supported'      => array( 'none', 'client_secret_post' ),
			'revocation_endpoint_auth_methods_supported' => array( 'none', 'client_secret_post' ),
			'scopes_supported'                           => array( self::SCOPE ),
			'authorization_response_iss_parameter_supported' => true,
			'client_id_metadata_document_supported'      => false,
		);
	}

	/**
	 * Protected resource metadata (RFC 9728).
	 *
	 * @return array
	 */
	public static function protected_resource_metadata() {
		return array(
			'resource'                 => self::resource(),
			'authorization_servers'    => array( self::issuer() ),
			'scopes_supported'         => array( self::SCOPE ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => sprintf(
				/* translators: %s: site name */
				__( 'Mail Mint on %s', 'mail-mint' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			),
		);
	}

	/**
	 * URL of the protected resource metadata, sent in the WWW-Authenticate challenge.
	 *
	 * Uses the REST copy rather than the root /.well-known path because the REST API is
	 * reachable on every host, while /.well-known is sometimes blocked by the web server.
	 *
	 * @return string
	 */
	public static function protected_resource_metadata_url() {
		return rest_url( OAuthRoutes::REST_NAMESPACE . '/mcp/oauth/protected-resource' );
	}

	/**
	 * Serve the /.well-known discovery documents.
	 *
	 * Handles RFC 8414 path insertion (/.well-known/oauth-authorization-server/{path}) and
	 * the OpenID-style path append ({path}/.well-known/openid-configuration) that MCP clients
	 * try in turn.
	 *
	 * @param bool $parse Whether WordPress should parse the request.
	 * @return bool
	 */
	public static function maybe_serve_well_known( $parse ) {
		if ( ! $parse || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $parse;
		}

		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( false === strpos( $path, '/.well-known/' ) ) {
			return $parse;
		}

		if ( ! self::is_enabled() ) {
			return $parse;
		}

		$kind = self::match_well_known(
			rawurldecode( $path ),
			(string) wp_parse_url( self::issuer(), PHP_URL_PATH ),
			(string) wp_parse_url( self::resource(), PHP_URL_PATH ),
			true
		);
		if ( null === $kind ) {
			return $parse;
		}

		$document = 'resource' === $kind
			? self::protected_resource_metadata()
			: self::authorization_server_metadata();

		self::send_cors_headers();

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'OPTIONS' === $method ) {
			status_header( 204 );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=300' );
		echo wp_json_encode( $document );
		exit;
	}

	/**
	 * Decide which discovery document, if any, a /.well-known path asks this plugin for.
	 *
	 * Only answers for this plugin's own issuer and resource, so another plugin on the same
	 * site (WPFunnels runs its own OAuth server at a different issuer path) can serve its
	 * documents side by side. Accepts the RFC 8414 / RFC 9728 path-inserted form
	 * (/.well-known/{type}{path}) and the OpenID path-appended form ({path}/.well-known/{type}).
	 *
	 * @param string $path          Decoded request path.
	 * @param string $issuer_path   Path component of the issuer ('' at the site root).
	 * @param string $resource_path Path component of the MCP endpoint.
	 * @param bool   $root_resource Also answer a bare /.well-known/oauth-protected-resource.
	 * @return string|null 'server', 'resource', or null when the path is not ours.
	 */
	public static function match_well_known( $path, $issuer_path, $resource_path, $root_resource ) {
		if ( ! preg_match( '#^(.*?)/\.well-known/(oauth-authorization-server|openid-configuration|oauth-protected-resource)(/.*)?$#', (string) $path, $m ) ) {
			return null;
		}
		$prefix        = rtrim( $m[1], '/' );
		$suffix        = isset( $m[3] ) ? rtrim( $m[3], '/' ) : '';
		$issuer_path   = rtrim( (string) $issuer_path, '/' );
		$resource_path = rtrim( (string) $resource_path, '/' );

		if ( 'oauth-protected-resource' === $m[2] ) {
			if ( '' === $prefix && ( $suffix === $resource_path || ( $root_resource && '' === $suffix ) ) ) {
				return 'resource';
			}
			return null;
		}

		if ( ( '' === $prefix && $suffix === $issuer_path ) || ( '' === $suffix && $prefix === $issuer_path ) ) {
			return 'server';
		}
		return null;
	}

	/**
	 * Send permissive CORS headers for the discovery documents (they hold no secrets).
	 *
	 * @return void
	 */
	private static function send_cors_headers() {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, MCP-Protocol-Version' );
	}

	/**
	 * `determine_current_user` filter: log the request in as the token's user.
	 *
	 * Only acts on requests to the MCP route that carry `Authorization: Bearer`, so a token
	 * can never be used against the rest of the REST API or wp-admin. Basic auth
	 * (Application Passwords) and cookies are left to WordPress core.
	 *
	 * @param int|false $user_id User ID found by earlier filters.
	 * @return int|false
	 */
	public static function authenticate_bearer( $user_id ) {
		if ( ! empty( $user_id ) || ! self::is_mcp_option_on() ) {
			return $user_id;
		}

		$token = self::get_bearer_token();
		if ( '' === $token || ! self::is_mcp_request() ) {
			return $user_id;
		}

		$token_user = OAuthTokens::validate_access_token( $token );
		if ( $token_user > 0 ) {
			self::$bearer_user = $token_user;
			return $token_user;
		}
		return $user_id;
	}

	/**
	 * `rest_post_dispatch` filter: add the OAuth challenge to 401s from the MCP route.
	 *
	 * @param \WP_HTTP_Response $response Response.
	 * @param \WP_REST_Server   $server   Server.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_HTTP_Response
	 */
	public static function add_challenge_header( $response, $server, $request ) {
		if ( ! ( $response instanceof \WP_HTTP_Response ) || 401 !== (int) $response->get_status() ) {
			return $response;
		}
		if ( ! ( $request instanceof \WP_REST_Request ) || ! self::is_mcp_route( $request->get_route() ) ) {
			return $response;
		}
		if ( ! self::is_enabled() ) {
			return $response;
		}

		$challenge = sprintf(
			'Bearer realm="mail-mint", resource_metadata="%s", scope="%s"',
			self::protected_resource_metadata_url(),
			self::SCOPE
		);
		if ( '' !== self::get_bearer_token() ) {
			$challenge .= ', error="invalid_token", error_description="The access token is invalid or expired"';
		}

		$response->header( 'WWW-Authenticate', $challenge );
		return $response;
	}

	/**
	 * Let browser-based MCP clients read the challenge header.
	 *
	 * @param string[] $headers Exposed headers.
	 * @return string[]
	 */
	public static function expose_cors_headers( $headers ) {
		$headers   = (array) $headers;
		$headers[] = 'WWW-Authenticate';
		return array_values( array_unique( $headers ) );
	}

	/**
	 * The MCP REST route, e.g. "/mrm/mcp".
	 *
	 * @return string
	 */
	private static function mcp_route() {
		$namespace = apply_filters( 'mail_mint/mcp_server_namespace', 'mrm' );
		$route     = apply_filters( 'mail_mint/mcp_server_route', 'mcp' );
		return '/' . trim( (string) $namespace, '/' ) . '/' . trim( (string) $route, '/' );
	}

	/**
	 * Whether a REST route is the MCP route.
	 *
	 * @param string $route Route.
	 * @return bool
	 */
	private static function is_mcp_route( $route ) {
		return untrailingslashit( '/' . ltrim( (string) $route, '/' ) ) === self::mcp_route();
	}

	/**
	 * Whether the current HTTP request targets the MCP route.
	 *
	 * Runs before the REST server parses the request, so it reads the raw request URI.
	 *
	 * @return bool
	 */
	private static function is_mcp_request() {
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// WordPress reads rest_route from POST before the URL, so a body value could swap the route.
		if ( isset( $_POST['rest_route'] ) ) {
			return false;
		}
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		$path = untrailingslashit( rawurldecode( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) ) );
		$home = untrailingslashit( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ) );

		// Plain permalinks: /?rest_route=/mrm/mcp or /index.php?rest_route=/mrm/mcp.
		if ( isset( $_GET['rest_route'] ) ) {
			if ( ! is_string( $_GET['rest_route'] ) || ! self::is_mcp_route( wp_unslash( $_GET['rest_route'] ) ) ) {
				return false;
			}
			return in_array( $path, array( $home, $home . '/index.php' ), true );
		}
		// phpcs:enable

		// Pretty permalinks: the path must be exactly the MCP endpoint. A suffix match would let
		// PATH_INFO URLs such as /wp-admin/admin-ajax.php/wp-json/mrm/mcp through.
		$rest = '/' . trim( rest_get_url_prefix(), '/' ) . self::mcp_route();
		return in_array( $path, array( $home . $rest, $home . '/index.php' . $rest ), true );
	}

	/**
	 * `rest_pre_dispatch` filter: second line of defence. When the user was authenticated
	 * by a Bearer token, refuse every route except the MCP route.
	 *
	 * @param mixed            $result  Pre-dispatch result.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function restrict_bearer_to_mcp( $result, $server, $request ) {
		// Only the outer HTTP request is checked; the first dispatch is always that one, and later
		// rest_do_request() calls made by a tool while it runs are internal.
		if ( ! self::$bearer_user || self::$dispatch_checked || ! ( $request instanceof \WP_REST_Request ) ) {
			return $result;
		}
		self::$dispatch_checked = true;
		if ( self::is_mcp_route( $request->get_route() ) && get_current_user_id() === self::$bearer_user ) {
			return $result;
		}
		return new \WP_Error( 'mint_mcp_oauth_scope', __( 'This access token only works with the Mail Mint MCP endpoint.', 'mail-mint' ), array( 'status' => 401 ) );
	}

	/**
	 * Read a Bearer token from the Authorization header.
	 *
	 * @return string Empty string when there is none.
	 */
	public static function get_bearer_token() {
		$header = '';
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		} elseif ( function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $name => $value ) {
				if ( 'authorization' === strtolower( (string) $name ) ) {
					$header = (string) $value;
					break;
				}
			}
		}
		// phpcs:enable

		if ( ! is_string( $header ) || ! preg_match( '/^\s*Bearer\s+([A-Za-z0-9._~+\/=-]+)\s*$/i', $header, $matches ) ) {
			return '';
		}
		return $matches[1];
	}
}
