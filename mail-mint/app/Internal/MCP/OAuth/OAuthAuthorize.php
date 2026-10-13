<?php
/**
 * OAuthAuthorize — the browser "Allow access?" screen.
 *
 * Served on the front end at /?mint_mcp_oauth=authorize, on `init`, before the theme or
 * any template_redirect handler runs. It is deliberately not on wp-login.php: some hosts
 * (TasteWP, for example) and security plugins send every wp-login.php request from a
 * logged-in user to wp-admin, so the screen would never appear. Signing in still uses the
 * normal WordPress login, including 2FA and login-URL plugins. The page sends
 * X-Frame-Options, so it cannot be framed for clickjacking.
 *
 * Errors about the client or its redirect URI are shown on this screen and never
 * redirected, so a forged request cannot bounce the user to an unregistered URL.
 *
 * @package Mint\MRM\Internal\MCP\OAuth
 * @since 1.32.0
 */

namespace Mint\MRM\Internal\MCP\OAuth;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the authorization endpoint.
 *
 * @since 1.32.0
 */
class OAuthAuthorize {

	const ACTION       = 'mint_mcp_authorize';
	const QUERY_VAR    = 'mint_mcp_oauth';
	const NONCE_ACTION = 'mint_mcp_authorize';
	const MAX_PARAM    = 2000;

	const PENDING_COOKIE = 'mint_mcp_oauth_pending';
	const PENDING_PREFIX = 'mint_mcp_pending_';
	const PENDING_TTL    = 900;

	/**
	 * Request parameters carried through login and the consent form.
	 *
	 * @var string[]
	 */
	private static $params = array( 'response_type', 'client_id', 'redirect_uri', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource' );

	/**
	 * `init` handler: serve the screen when the request asks for it.
	 *
	 * @return void
	 */
	public static function maybe_handle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; handle() verifies the nonce before acting.
		if ( isset( $_GET[ self::QUERY_VAR ] ) && 'authorize' === $_GET[ self::QUERY_VAR ] ) {
			self::handle();
		}
	}

	/**
	 * Serve the authorize screen. Always exits.
	 *
	 * Also hooked to login_form_mint_mcp_authorize so the older wp-login.php URL keeps working.
	 *
	 * @return void
	 */
	public static function handle() {
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( "Content-Security-Policy: frame-ancestors 'self'" );
			header( 'Referrer-Policy: no-referrer' );
		}

		if ( ! OAuthServer::is_enabled() ) {
			self::render_error( __( 'Connecting AI apps is turned off on this site. Turn on MCP in Mail Mint → Settings → MCP, then try again.', 'mail-mint' ) );
		}

		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
		$req     = self::read_params( $is_post );

		// 1. Client and redirect URI. Problems here are shown, never redirected.
		$client = OAuthClients::get( $req['client_id'] );
		if ( null === $client ) {
			self::render_error( __( 'This app is not registered with this site, or its registration is no longer valid. Remove the connector from your AI app and add it again.', 'mail-mint' ) );
		}
		if ( '' === $req['redirect_uri'] && 1 === count( $client['redirect_uris'] ) ) {
			$req['redirect_uri'] = $client['redirect_uris'][0];
		}
		if ( ! OAuthClients::redirect_uri_matches( $client, $req['redirect_uri'] ) ) {
			self::render_error( __( 'The app sent a redirect address it did not register. For your safety the connection was stopped.', 'mail-mint' ) );
		}

		// 2. Request shape. Also shown rather than redirected: registration is open, so bouncing
		// a malformed request straight to its redirect URI would make this an open redirect.
		/* translators: %s: name of the unsupported OAuth parameter */
		$app_error = __( 'Your AI app sent a request this site does not support (%s). Update the app, or use the application password setup in Mail Mint → Settings → MCP.', 'mail-mint' );
		if ( 'code' !== $req['response_type'] ) {
			self::render_error( sprintf( $app_error, 'response_type' ) );
		}
		if ( 'S256' !== $req['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $req['code_challenge'] ) ) {
			self::render_error( sprintf( $app_error, 'PKCE S256' ) );
		}
		if ( '' !== $req['resource'] && ! OAuthServer::resource_matches( $req['resource'] ) ) {
			self::render_error( sprintf( $app_error, 'resource' ) );
		}

		// 3. Sign in.
		if ( ! is_user_logged_in() ) {
			self::remember_pending( self::self_url( $req ) );
			wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( self::self_url( $req ) ), OAuthServer::login_page_url() ) );
			exit;
		}
		self::clear_pending();
		if ( ! self::current_user_can_connect() ) {
			self::render_error(
				__( 'Your account does not have permission to use Mail Mint with AI apps. Ask a site administrator for access, or sign in with another account.', 'mail-mint' ),
				true,
				$req
			);
		}

		// 4. Decision.
		if ( $is_post ) {
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
				self::render_error( __( 'This page expired. Go back to your AI app and start the connection again.', 'mail-mint' ) );
			}

			$decision = isset( $_POST['mint_mcp_decision'] ) ? sanitize_key( wp_unslash( $_POST['mint_mcp_decision'] ) ) : '';
			if ( 'allow' !== $decision ) {
				self::redirect_error( $req, 'access_denied', 'The user denied the request.' );
			}

			$code = OAuthTokens::create_code(
				array(
					'client_id'      => $req['client_id'],
					'redirect_uri'   => $req['redirect_uri'],
					'code_challenge' => $req['code_challenge'],
					'user_id'        => get_current_user_id(),
					'resource'       => $req['resource'],
				)
			);

			$query = array( 'code' => $code );
			if ( '' !== $req['state'] ) {
				$query['state'] = $req['state'];
			}
			$query['iss'] = OAuthServer::issuer();

			self::redirect_to_client( $req['redirect_uri'], $query );
		}

		self::render_consent( $req, $client );
	}

	/**
	 * Remember the authorize request across sign-in.
	 *
	 * The login redirect normally carries it in redirect_to, but some hosts and plugins send
	 * every login to a fixed page (TasteWP, for example, always lands on plugins.php). The
	 * request is stored server side and only a random ID goes in the cookie.
	 *
	 * @param string $url Authorize URL to come back to.
	 * @return void
	 */
	private static function remember_pending( $url ) {
		if ( headers_sent() ) {
			return;
		}
		$id = bin2hex( random_bytes( 16 ) );
		set_transient( self::PENDING_PREFIX . $id, $url, self::PENDING_TTL );
		setcookie( self::PENDING_COOKIE, $id, time() + self::PENDING_TTL, '/', COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Forget the remembered authorize request.
	 *
	 * @return void
	 */
	private static function clear_pending() {
		if ( empty( $_COOKIE[ self::PENDING_COOKIE ] ) ) {
			return;
		}
		$id = sanitize_key( wp_unslash( $_COOKIE[ self::PENDING_COOKIE ] ) );
		delete_transient( self::PENDING_PREFIX . $id );
		unset( $_COOKIE[ self::PENDING_COOKIE ] );
		if ( ! headers_sent() ) {
			setcookie( self::PENDING_COOKIE, '', time() - YEAR_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	/**
	 * `admin_init` / `template_redirect`: after a sign-in that lost redirect_to, send the
	 * user back to the Allow screen. Runs on plain GET page loads only.
	 *
	 * @return void
	 */
	public static function resume_pending() {
		if ( empty( $_COOKIE[ self::PENDING_COOKIE ] ) || ! is_user_logged_in() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method ) {
			return;
		}

		$id  = sanitize_key( wp_unslash( $_COOKIE[ self::PENDING_COOKIE ] ) );
		$url = get_transient( self::PENDING_PREFIX . $id );
		self::clear_pending();

		// Only ever our own authorize screen.
		if ( ! is_string( $url ) || 0 !== strpos( $url, OAuthServer::authorize_url() . '&' ) ) {
			return;
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Read the OAuth parameters from the query string (GET) or the consent form (POST).
	 *
	 * Values are opaque to us (state in particular must come back byte for byte), so they
	 * are not run through sanitize_text_field(), which would strip percent-encoded octets.
	 * They are length-limited, rejected if they contain control characters, and escaped on output.
	 *
	 * @param bool $is_post Whether to read the POST body.
	 * @return array
	 */
	private static function read_params( $is_post ) {
		// phpcs:disable WordPress.Security.NonceVerification -- nonce is verified in handle() before acting on a POST.
		$source = $is_post ? $_POST : $_GET;
		// phpcs:enable
		$req = array();
		foreach ( self::$params as $key ) {
			$value = isset( $source[ $key ] ) && is_string( $source[ $key ] ) ? wp_unslash( $source[ $key ] ) : '';
			if ( strlen( $value ) > ( 'client_id' === $key ? 30000 : self::MAX_PARAM ) || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) {
				$value = '';
			}
			$req[ $key ] = $value;
		}
		return $req;
	}

	/**
	 * Whether the signed-in user passes the same capability check as the MCP transport.
	 *
	 * @return bool
	 */
	private static function current_user_can_connect() {
		/** This filter is documented in app/Internal/MCP/MCPInit.php */
		$capability = apply_filters( 'mail_mint/mcp_transport_capability', 'mint_view_dashboard', new \WP_REST_Request( 'POST', '/mrm/mcp' ) );
		return current_user_can( $capability );
	}

	/**
	 * URL of this screen carrying the current request, used as the login redirect target.
	 *
	 * @param array $req Request params.
	 * @return string
	 */
	private static function self_url( $req ) {
		return OAuthServer::authorize_url() . '&' . http_build_query( array_filter( $req, 'strlen' ), '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Redirect back to the client's registered redirect URI.
	 *
	 * @param string $redirect_uri Validated redirect URI.
	 * @param array  $query        Query parameters to append.
	 * @return void
	 */
	private static function redirect_to_client( $redirect_uri, $query ) {
		$separator = false === strpos( $redirect_uri, '?' ) ? '?' : '&';
		// wp_redirect() rather than wp_safe_redirect(): the target is the client's registered,
		// already validated redirect URI on another host (or an app scheme).
		wp_redirect( $redirect_uri . $separator . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ), 302, 'Mail Mint' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Send an OAuth error back to the client.
	 *
	 * @param array  $req         Request params (redirect URI already validated).
	 * @param string $error       OAuth error code.
	 * @param string $description Description.
	 * @return void
	 */
	private static function redirect_error( $req, $error, $description ) {
		$query = array(
			'error'             => $error,
			'error_description' => $description,
		);
		if ( '' !== $req['state'] ) {
			$query['state'] = $req['state'];
		}
		$query['iss'] = OAuthServer::issuer();
		self::redirect_to_client( $req['redirect_uri'], $query );
	}

	/**
	 * Render the consent screen and exit.
	 *
	 * @param array $req    Request params.
	 * @param array $client Client.
	 * @return void
	 */
	private static function render_consent( $req, $client ) {
		$user        = wp_get_current_user();
		$site_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$client_name = '' !== $client['name'] ? $client['name'] : __( 'An AI app', 'mail-mint' );
		$destination = OAuthTokens::describe_redirect( $req['redirect_uri'] );

		self::page_header();
		?>
		<form name="mint-mcp-authorize" id="loginform" class="mint-mcp-consent" method="post" action="<?php echo esc_url( OAuthServer::authorize_url() ); ?>">
			<h1 class="mint-mcp-title">
				<?php
				/* translators: %s: app name, e.g. "Claude" */
				echo esc_html( sprintf( __( 'Connect %s to Mail Mint', 'mail-mint' ), $client_name ) );
				?>
			</h1>

			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: app name, 2: site name, 3: user display name */
						__( '<strong>%1$s</strong> wants to use Mail Mint on <strong>%2$s</strong> as <strong>%3$s</strong>.', 'mail-mint' ),
						esc_html( $client_name ),
						esc_html( $site_name ),
						esc_html( $user->display_name )
					),
					array( 'strong' => array() )
				);
				?>
			</p>

			<p class="mint-mcp-label"><?php esc_html_e( 'It will be able to:', 'mail-mint' ); ?></p>
			<ul class="mint-mcp-list">
				<li><?php esc_html_e( 'View and manage contacts, lists, tags, segments, and custom fields', 'mail-mint' ); ?></li>
				<li><?php esc_html_e( 'Create campaigns, automations, forms, and email templates', 'mail-mint' ); ?></li>
				<li><?php esc_html_e( 'Send emails and view reports', 'mail-mint' ); ?></li>
			</ul>
			<p class="mint-mcp-note">
				<?php esc_html_e( 'It can only do what your account is allowed to do in Mail Mint. You can disconnect it at any time from Mail Mint → Settings → MCP.', 'mail-mint' ); ?>
			</p>

			<p class="mint-mcp-destination">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: where the browser goes next, e.g. "claude.ai" */
						__( 'After you allow, you will be sent to <strong>%s</strong>. Only continue if you started this connection yourself.', 'mail-mint' ),
						esc_html( $destination )
					),
					array( 'strong' => array() )
				);
				?>
			</p>

			<?php foreach ( self::$params as $key ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $req[ $key ] ); ?>" />
			<?php endforeach; ?>
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>

			<p class="submit mint-mcp-actions">
				<button type="submit" name="mint_mcp_decision" value="deny" class="button button-large"><?php esc_html_e( 'Cancel', 'mail-mint' ); ?></button>
				<button type="submit" name="mint_mcp_decision" value="allow" class="button button-primary button-large"><?php esc_html_e( 'Allow', 'mail-mint' ); ?></button>
			</p>
		</form>

		<p id="nav">
			<?php
			/* translators: %s: user login */
			echo esc_html( sprintf( __( 'Signed in as %s.', 'mail-mint' ), $user->user_login ) );
			?>
			<a href="<?php echo esc_url( wp_logout_url( self::self_url( $req ) ) ); ?>"><?php esc_html_e( 'Use another account', 'mail-mint' ); ?></a>
		</p>
		<?php
		self::page_footer();
		exit;
	}

	/**
	 * Render an error screen and exit.
	 *
	 * @param string     $message         Message.
	 * @param bool       $offer_switch    Whether to offer signing in as another user.
	 * @param array|null $req             Request params, needed when $offer_switch is true.
	 * @return void
	 */
	private static function render_error( $message, $offer_switch = false, $req = null ) {
		self::page_header( $message );
		if ( $offer_switch && is_array( $req ) ) {
			?>
			<p id="nav">
				<a href="<?php echo esc_url( wp_logout_url( self::self_url( $req ) ) ); ?>"><?php esc_html_e( 'Sign in with another account', 'mail-mint' ); ?></a>
			</p>
			<?php
		}
		self::page_footer();
		exit;
	}

	/**
	 * Open the page, styled like the WordPress login screen.
	 *
	 * @param string $error Error message to show, if any.
	 * @return void
	 */
	private static function page_header( $error = '' ) {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		}
		$title = __( 'Connect to Mail Mint', 'mail-mint' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $title . ' &lsaquo; ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></title>
		<?php
		wp_print_styles( array( 'login' ) );
		self::print_styles();
		?>
</head>
<body class="login wp-core-ui">
<div id="login">
	<h1><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a></h1>
		<?php if ( '' !== $error ) : ?>
	<div id="login_error" class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Close the page.
	 *
	 * @return void
	 */
	private static function page_footer() {
		?>
</div>
</body>
</html>
		<?php
	}

	/**
	 * Minimal styles for the consent screen, on top of the wp-login.php stylesheet.
	 *
	 * @return void
	 */
	private static function print_styles() {
		?>
		<style>
			.mint-mcp-consent .mint-mcp-title { font-size: 18px; font-weight: 600; line-height: 1.4; margin: 0 0 12px; }
			.mint-mcp-consent p { margin: 0 0 12px; font-size: 14px; line-height: 1.5; }
			.mint-mcp-consent .mint-mcp-label { margin-bottom: 4px; font-weight: 600; }
			.mint-mcp-consent .mint-mcp-list { margin: 0 0 12px 18px; list-style: disc; }
			.mint-mcp-consent .mint-mcp-list li { font-size: 13px; line-height: 1.5; margin-bottom: 2px; }
			.mint-mcp-consent .mint-mcp-note,
			.mint-mcp-consent .mint-mcp-destination { font-size: 13px; color: #50575e; }
			.mint-mcp-consent .mint-mcp-actions { display: flex; justify-content: flex-end; gap: 8px; margin: 16px 0 0; }
			.mint-mcp-consent .mint-mcp-actions .button { float: none; }
		</style>
		<?php
	}
}
