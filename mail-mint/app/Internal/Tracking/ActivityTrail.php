<?php
/**
 * Product activity trail.
 *
 * Records every Mail Mint feature interaction on the site:
 *   - every admin screen opened (route pattern, sent from the React app), and
 *   - every Mail Mint REST action (all create/update/delete/send/test calls in
 *     Free and Pro, plus any request that fails), named automatically from the
 *     route pattern, e.g. `POST /campaigns/:campaign_id/status-update` becomes
 *     `campaign_status_update`, and a failure becomes `campaign_status_update_failed`.
 *
 * Delivery to PostHog, one event per entry with its original timestamp and the
 * same distinct id the Linno Telemetry SDK uses:
 *   - opted-in sites: sent daily, so retained and churned users can be compared.
 *   - every site: whatever is unsent is sent when the plugin is deactivated,
 *     followed by a `deactivation_context` summary.
 *
 * Payload is anonymous: route patterns (never ids), feature names, HTTP status
 * and error codes. No request bodies, messages, emails or contact data.
 *
 * @package Mint\MRM\Internal\Tracking
 * @since 1.32.0
 */

namespace Mint\MRM\Internal\Tracking;

use LinnoSDK\Telemetry\Client;
use LinnoSDK\Telemetry\Helpers\Utils;
use Mint\MRM\Utilities\Helper\PermissionManager;
use PostHog\PostHog;

/**
 * Class ActivityTrail
 *
 * @since 1.32.0
 */
class ActivityTrail {

	/**
	 * Option that stores the trail (not autoloaded).
	 */
	const OPTION = 'mailmint_activity_trail';

	/**
	 * Maximum unsent entries kept (a busy day on an opted-in site).
	 */
	const MAX_UNSENT = 200;

	/**
	 * Already-sent entries kept for the deactivation summary.
	 */
	const KEEP_SENT = 30;

	/**
	 * Entries older than this are dropped (30 days).
	 */
	const MAX_AGE = 2592000;

	/**
	 * Repeats of the same activity within this window collapse into one entry
	 * (autosave, polling, reopening a screen).
	 */
	const COLLAPSE_WINDOW = 600;

	/**
	 * WP-Cron hook for the daily flush on opted-in sites.
	 */
	const FLUSH_HOOK = 'mailmint_activity_trail_flush';

	/**
	 * PostHog ingestion host (same project as the Linno SDK client).
	 */
	const POSTHOG_HOST = 'https://eu.i.posthog.com';

	/**
	 * PostHog event name prefix for trail events.
	 */
	const EVENT_PREFIX = 'mailmint_activity/';

	/**
	 * Admin REST namespaces owned by Mail Mint Free and Pro. The public
	 * `mint-mail/v1` namespace (visitor form submits, unsubscribes) is left out:
	 * it is subscriber traffic, not product usage.
	 */
	const NAMESPACES = array( 'mrm/v1', 'mrm-pro/v1', 'mail-mint/v1' );

	/**
	 * Routes (without namespace) that are never recorded.
	 */
	const IGNORED_ROUTES = array( '/activity-trail/screen' );

	/**
	 * First route segment → feature area.
	 */
	const AREA_ALIASES = array(
		'campaigns'      => 'campaign',
		'contacts'       => 'contact',
		'lists'          => 'list',
		'tags'           => 'tag',
		'segments'       => 'segment',
		'forms'          => 'form',
		'webhooks'       => 'webhook',
		'lead-magnets'   => 'lead_magnet',
		'link-triggers'  => 'link_trigger',
		'products'       => 'product',
		'posts'          => 'post',
		'roles'          => 'role',
		'columns'        => 'contact_columns',
		'reports'        => 'dashboard',
		'email-history'  => 'email_history',
		'abandoned-cart' => 'abandoned_cart',
		'split-test'     => 'split_test',
		'custom-access'  => 'custom_access',
		'data-cleanup'   => 'data_cleanup',
	);

	/**
	 * Explicit names for actions the naming rules can't infer
	 * (actions exposed as GET, or clearer names for key signals).
	 * Keys are "<GET|WRITE|DELETE> <normalized route>".
	 */
	const OVERRIDES = array(
		'GET /campaigns/duplicate/:campaign_id'                                    => 'campaign_duplicated',
		'GET /forms/:id/duplicate'                                                  => 'form_duplicated',
		'GET /forms/:id/export'                                                     => 'form_exported',
		'GET /forms/import-form-template/:form_id'                                  => 'form_template_imported',
		'GET /automation/:id/duplicate-automation'                                  => 'automation_duplicated',
		'GET /automation/:id/export-automation'                                     => 'automation_exported',
		'GET /automation/get-single-automation-recipe/:id'                          => 'automation_recipe_selected',
		'GET /abandoned-cart/manually-run-automation/:abandoned_id/:automation_id' => 'abandoned_cart_automation_run_manually',
		'WRITE /campaign/sendTest'                                                  => 'test_email_sent',
		'WRITE /campaign/mediaUpload'                                               => 'media_uploaded',
		'WRITE /campaigns/hide-smtp-notice'                                         => 'smtp_notice_dismissed',
		'WRITE /reports/hide-checklist'                                             => 'onboarding_checklist_dismissed',
		'WRITE /ai/conversations/:conversation_id/step-stream'                      => 'ai_conversations_step',
		'WRITE /forms/update-status/:form_id'                                       => 'form_status_update',
		'WRITE /forms/preview-editor/:form_id'                                      => 'form_preview',
		'WRITE /campaign/analytics/:campaign_id/resend-to-unopened/:email_id'       => 'campaign_resent_to_unopened',
		'WRITE /reports/dashboard/sync'                                             => 'dashboard_sync',
		'WRITE /integration'                                                        => 'integration_connected',
	);

	/**
	 * AI assistant settings option (see AISettings::OPTION_KEY).
	 */
	const AI_SETTINGS_OPTION = '_mrm_ai_settings';

	/**
	 * MCP server on/off option (default 'yes').
	 */
	const MCP_ENABLED_OPTION = '_mrm_mcp_enabled';

	/**
	 * Last MCP client name seen on `initialize` (tools/call requests don't carry it).
	 */
	const MCP_CLIENT_OPTION = 'mailmint_mcp_last_client';

	/**
	 * MCP methods recorded; list/ping calls are client housekeeping and skipped.
	 */
	const MCP_METHODS = array(
		'initialize'     => 'mcp_client_connected',
		'tools/call'     => 'mcp_tool_called',
		'prompts/get'    => 'mcp_prompt_used',
		'resources/read' => 'mcp_resource_read',
	);

	/**
	 * Tool-name keywords → feature area, most specific first.
	 */
	const TOOL_AREAS = array(
		'custom-field' => 'custom_field',
		'automation'   => 'automation',
		'campaign'     => 'campaign',
		'segment'      => 'segment',
		'contact'      => 'contact',
		'template'     => 'template',
		'form'         => 'form',
		'email'        => 'email',
		'list'         => 'list',
		'tag'          => 'tag',
		'report'       => 'report',
		'stat'         => 'report',
		'context'      => 'context',
	);

	/**
	 * Singleton instance.
	 *
	 * @var ActivityTrail|null
	 */
	private static $instance = null;

	/**
	 * Linno Telemetry client (for the consent state).
	 *
	 * @var Client|null
	 */
	private $client;

	/**
	 * Matched route pattern per in-flight REST request, keyed by object id.
	 *
	 * @var array
	 */
	private $matched_routes = array();

	/**
	 * Register hooks once.
	 *
	 * @param Client|null $client Linno Telemetry client.
	 * @return void
	 */
	public static function init( $client = null ) {
		if ( null === self::$instance ) {
			self::$instance         = new self();
			self::$instance->client = $client;
			self::$instance->register_hooks();
		}
	}

	/**
	 * The running instance, or null before init().
	 *
	 * @return ActivityTrail|null
	 */
	public static function get_instance() {
		return self::$instance;
	}

	/**
	 * Wire recorders, the screen-view endpoint, the daily flush and the
	 * deactivation sender.
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Every Mail Mint REST action (Free + Pro).
		add_filter( 'rest_dispatch_request', array( $this, 'remember_route' ), 10, 4 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'record_rest_action' ), 10, 3 );

		// Work that finishes outside the admin request.
		add_action(
			'mailmint_campaign_email_sent',
			function () {
				$this->record( 'campaign_sending_completed' );
			}
		);
		add_action(
			'mailmint_contacts_imported',
			function ( $count = 0, $source = '' ) {
				$this->record(
					'contact_import_completed',
					array(
						'import_source' => sanitize_key( (string) $source ),
						'count'         => (int) $count,
					)
				);
			},
			10,
			2
		);

		// AI assistant: provider connection lifecycle and copilot tool calls.
		add_action( 'add_option_' . self::AI_SETTINGS_OPTION, array( $this, 'on_ai_settings_added' ), 10, 2 );
		add_action( 'update_option_' . self::AI_SETTINGS_OPTION, array( $this, 'on_ai_settings_updated' ), 10, 2 );
		add_action(
			'mailmint_ai_tool_executed',
			function ( $tool_name ) {
				$this->record_ai_tool( (string) $tool_name, true );
			}
		);
		add_action(
			'mailmint_ai_tool_failed',
			function ( $tool_name, $error_code = '' ) {
				$this->record_ai_tool( (string) $tool_name, false, (string) $error_code );
			},
			10,
			2
		);

		// MCP server on/off. MCP requests are recorded by MintMcpObservabilityHandler.
		add_action( 'add_option_' . self::MCP_ENABLED_OPTION, array( $this, 'on_mcp_enabled_added' ), 10, 2 );
		add_action( 'update_option_' . self::MCP_ENABLED_OPTION, array( $this, 'on_mcp_enabled_updated' ), 10, 2 );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		add_action( self::FLUSH_HOOK, array( $this, 'flush' ) );
		add_action( 'init', array( $this, 'maybe_schedule_flush' ) );

		add_action( 'mailmint_plugin_deactivated', array( $this, 'send_on_deactivation' ) );
	}

	/**
	 * Register the screen-view endpoint used by the admin SPA.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'mrm/v1',
			'/activity-trail/screen',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_screen' ),
				'permission_callback' => array( $this, 'can_record' ),
				'args'                => array(
					'screen' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Any user who can use Mail Mint may be recorded.
	 *
	 * @return bool
	 */
	public function can_record() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		foreach ( PermissionManager::plugin_permissions() as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * REST callback: record a screen (route pattern such as `/campaign/:type/edit/:id`).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function record_screen( \WP_REST_Request $request ) {
		// Route patterns only: letters, digits, slashes, colons, dashes, underscores.
		$screen = substr( preg_replace( '/[^a-zA-Z0-9\/:_-]/', '', (string) $request->get_param( 'screen' ) ), 0, 100 );

		if ( '' !== $screen ) {
			$this->record( 'screen_viewed', array( 'screen' => $screen ) );
		}

		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Filter `rest_dispatch_request`: remember which route pattern matched.
	 *
	 * @param mixed            $result  Dispatch result (returned unchanged).
	 * @param \WP_REST_Request $request Request.
	 * @param string           $route   Matched route pattern.
	 * @param array            $handler Route handler.
	 * @return mixed
	 */
	public function remember_route( $result, $request, $route, $handler ) {
		if ( $request instanceof \WP_REST_Request && is_string( $route ) ) {
			$this->matched_routes[ spl_object_id( $request ) ] = $route;
		}

		return $result;
	}

	/**
	 * Filter `rest_request_after_callbacks`: record the Mail Mint action and its outcome.
	 *
	 * Writes (POST/PUT/PATCH/DELETE) are always recorded; reads (GET) only when
	 * they fail or are listed in OVERRIDES as actions.
	 *
	 * @param mixed            $response Response (returned unchanged).
	 * @param array            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public function record_rest_action( $response, $handler, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$key = spl_object_id( $request );
		if ( ! isset( $this->matched_routes[ $key ] ) ) {
			return $response;
		}
		$pattern = $this->matched_routes[ $key ];
		unset( $this->matched_routes[ $key ] );

		try {
			$path = $this->strip_namespace( $pattern );
			if ( null === $path || in_array( $path, self::IGNORED_ROUTES, true ) || ! $this->can_record() ) {
				return $response;
			}

			$method = strtoupper( $request->get_method() );
			$class  = 'GET' === $method || 'DELETE' === $method ? $method : 'WRITE';
			$route  = $this->normalize_route( $path );

			list( $ok, $status, $error_code ) = $this->outcome( $response );

			$name = self::OVERRIDES[ $class . ' ' . $route ] ?? null;
			if ( null === $name ) {
				if ( 'GET' === $class && $ok ) {
					return $response; // Plain reads are covered by screen views.
				}
				$name = $this->derive_name( $route, $class );
			}

			$properties = array(
				'area'  => $this->area_of( $route ),
				'route' => $class . ' ' . $route,
			);

			if ( 'ai' === $properties['area'] || 'settings_ai' === substr( $name, 0, 11 ) ) {
				$properties['ai_provider'] = $this->active_ai_provider();
			}

			if ( ! $ok ) {
				$name                     .= '_failed';
				$properties['http_status'] = $status;
				if ( '' !== $error_code ) {
					$properties['error_code'] = $error_code;
				}
			}

			$this->record( $name, $properties );
		} catch ( \Throwable $e ) {
			// Telemetry must never break a request.
			return $response;
		}

		return $response;
	}

	/**
	 * Record one MCP request. Called by MintMcpObservabilityHandler for every
	 * `mcp.request` metric the MCP adapter emits.
	 *
	 * Events:
	 *   mcp_client_connected  (initialize)     client_name, transport
	 *   mcp_tool_called       (tools/call)     tool, tool_area, client_name
	 *   mcp_prompt_used       (prompts/get)    prompt, client_name
	 *   mcp_resource_read     (resources/read) client_name
	 * Each gets `_failed` plus error_code / error_category on failure.
	 *
	 * @param array $tags Adapter tags: method, status, params, tool_name, error_code, ...
	 * @return void
	 */
	public function record_mcp_request( array $tags ) {
		try {
			$method = (string) ( $tags['method'] ?? '' );
			if ( ! isset( self::MCP_METHODS[ $method ] ) || ! $this->can_record() ) {
				return;
			}

			$name   = self::MCP_METHODS[ $method ];
			$params = is_array( $tags['params'] ?? null ) ? $tags['params'] : array();

			if ( 'initialize' === $method ) {
				$client = $this->clean_label( $params['client_name'] ?? '' );
				if ( '' !== $client ) {
					update_option( self::MCP_CLIENT_OPTION, $client, false );
				}
			}

			$properties = array(
				'client_name' => '' !== ( $client ?? '' ) ? $client : (string) get_option( self::MCP_CLIENT_OPTION, 'unknown' ),
				'transport'   => $this->clean_label( $tags['transport'] ?? '' ),
			);

			if ( 'tools/call' === $method ) {
				$tool                    = (string) ( $tags['tool_name'] ?? $params['name'] ?? '' );
				$properties['tool']      = $this->short_tool_name( $tool );
				$properties['tool_area'] = $this->tool_area( $tool );
				if ( isset( $params['arguments_count'] ) ) {
					$properties['arguments_count'] = (int) $params['arguments_count'];
				}
			} elseif ( 'prompts/get' === $method ) {
				$properties['prompt'] = $this->short_tool_name( (string) ( $params['name'] ?? '' ) );
			}

			if ( 'error' === ( $tags['status'] ?? '' ) ) {
				$name .= '_failed';
				if ( isset( $tags['error_code'] ) ) {
					$properties['error_code'] = (string) $tags['error_code'];
				}
				if ( isset( $tags['error_category'] ) ) {
					$properties['error_category'] = $this->clean_label( $tags['error_category'] );
				}
			}

			$this->record( $name, $properties );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * Record one tool call made by the in-app AI copilot (ToolGateway).
	 *
	 * @param string $tool_name  Ability name, e.g. `mail-mint/create-campaign`.
	 * @param bool   $ok         Whether the tool succeeded.
	 * @param string $error_code WP_Error code on failure.
	 * @return void
	 */
	public function record_ai_tool( $tool_name, $ok, $error_code = '' ) {
		if ( ! $this->can_record() ) {
			return;
		}

		$properties = array(
			'tool'        => $this->short_tool_name( $tool_name ),
			'tool_area'   => $this->tool_area( $tool_name ),
			'ai_provider' => $this->active_ai_provider(),
		);

		if ( ! $ok && '' !== $error_code ) {
			$properties['error_code'] = sanitize_key( $error_code );
		}

		$this->record( $ok ? 'ai_tool_called' : 'ai_tool_called_failed', $properties );
	}

	/**
	 * `add_option__mrm_ai_settings`: first AI settings save.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function on_ai_settings_added( $option, $value ) {
		$this->on_ai_settings_updated( array(), $value );
	}

	/**
	 * `update_option__mrm_ai_settings`: turn the settings diff into events.
	 *
	 * Events: ai_provider_connected / ai_provider_disconnected (provider, model),
	 * ai_provider_switched (provider), ai_model_changed (provider, model),
	 * ai_assistant_enabled / ai_assistant_disabled. API keys are never read.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $new_value New settings.
	 * @return void
	 */
	public function on_ai_settings_updated( $old_value, $new_value ) {
		try {
			$old = is_array( $old_value ) ? $old_value : array();
			$new = is_array( $new_value ) ? $new_value : array();

			$old_providers = $this->connected_ai_providers( $old );
			$new_providers = $this->connected_ai_providers( $new );

			foreach ( array_diff_key( $new_providers, $old_providers ) as $provider => $model ) {
				$this->record( 'ai_provider_connected', array( 'provider' => $provider, 'model' => $model ) );
			}
			foreach ( array_diff_key( $old_providers, $new_providers ) as $provider => $model ) {
				$this->record( 'ai_provider_disconnected', array( 'provider' => $provider, 'model' => $model ) );
			}
			foreach ( array_intersect_key( $new_providers, $old_providers ) as $provider => $model ) {
				if ( $model !== $old_providers[ $provider ] ) {
					$this->record( 'ai_model_changed', array( 'provider' => $provider, 'model' => $model ) );
				}
			}

			$old_active = (string) ( $old['active_provider'] ?? '' );
			$new_active = (string) ( $new['active_provider'] ?? '' );
			if ( '' !== $new_active && $new_active !== $old_active && isset( $old_providers[ $new_active ] ) ) {
				$this->record( 'ai_provider_switched', array( 'provider' => $new_active ) );
			}

			$old_enabled = ! empty( $old['enabled'] );
			$new_enabled = ! empty( $new['enabled'] );
			if ( $old_enabled !== $new_enabled ) {
				$this->record( $new_enabled ? 'ai_assistant_enabled' : 'ai_assistant_disabled', array( 'provider' => $new_active ) );
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	/**
	 * `add_option__mrm_mcp_enabled`.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function on_mcp_enabled_added( $option, $value ) {
		$this->on_mcp_enabled_updated( 'yes', $value );
	}

	/**
	 * `update_option__mrm_mcp_enabled`: MCP server switched on or off.
	 *
	 * @param mixed $old_value Previous value ('yes' / 'no').
	 * @param mixed $new_value New value.
	 * @return void
	 */
	public function on_mcp_enabled_updated( $old_value, $new_value ) {
		$was = 'yes' === $old_value;
		$now = 'yes' === $new_value;

		if ( $was !== $now ) {
			$this->record( $now ? 'mcp_server_enabled' : 'mcp_server_disabled' );
		}
	}

	/**
	 * Connected AI providers and their models, from an AI settings array.
	 *
	 * @param array $settings AI settings.
	 * @return array provider => model
	 */
	private function connected_ai_providers( array $settings ) {
		$connected = array();

		foreach ( (array) ( $settings['providers'] ?? array() ) as $provider => $config ) {
			if ( is_array( $config ) && ( ! empty( $config['key'] ) || ! empty( $config['connected'] ) ) ) {
				$connected[ sanitize_key( (string) $provider ) ] = $this->clean_label( $config['model'] ?? '' );
			}
		}

		return $connected;
	}

	/**
	 * Active AI provider slug, or '' when none.
	 *
	 * @return string
	 */
	private function active_ai_provider() {
		$settings = get_option( self::AI_SETTINGS_OPTION, array() );

		return is_array( $settings ) ? sanitize_key( (string) ( $settings['active_provider'] ?? '' ) ) : '';
	}

	/**
	 * Same short name for copilot and MCP tools:
	 * `mail-mint/create-campaign` (ability) and `mail-mint-create-campaign`
	 * (MCP flattens `/` to `-`) both become `create_campaign`.
	 *
	 * @param string $tool_name Ability, MCP tool or prompt name.
	 * @return string
	 */
	private function short_tool_name( $tool_name ) {
		$short = strtolower( substr( (string) strrchr( '/' . $tool_name, '/' ), 1 ) );
		$short = preg_replace( '/^(mail-mint-pro-|mail-mint-)/', '', $short );

		return sanitize_key( str_replace( '-', '_', $short ) );
	}

	/**
	 * Feature area of a tool, from keywords in its name.
	 *
	 * @param string $tool_name Ability name.
	 * @return string
	 */
	private function tool_area( $tool_name ) {
		$short = str_replace( '_', '-', $this->short_tool_name( $tool_name ) );

		foreach ( self::TOOL_AREAS as $keyword => $area ) {
			if ( false !== strpos( $short, $keyword ) ) {
				return $area;
			}
		}

		return 'other';
	}

	/**
	 * Short, safe label (client names, model ids, categories).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function clean_label( $value ) {
		return is_scalar( $value ) ? substr( preg_replace( '/[^a-zA-Z0-9 ._:\/-]/', '', (string) $value ), 0, 60 ) : '';
	}

	/**
	 * Return the route without its namespace, or null if it isn't a Mail Mint route.
	 *
	 * @param string $pattern Full route pattern, e.g. `/mrm/v1/campaigns`.
	 * @return string|null
	 */
	private function strip_namespace( $pattern ) {
		foreach ( self::NAMESPACES as $namespace ) {
			$prefix = '/' . $namespace;
			if ( 0 === strpos( $pattern, $prefix . '/' ) ) {
				return substr( $pattern, strlen( $prefix ) );
			}
		}

		return null;
	}

	/**
	 * Turn a route regex into a readable pattern:
	 * `/campaigns/(?P<campaign_id>[\d]+)/delete` → `/campaigns/:campaign_id/delete`,
	 * and optional groups `(?:...)?` are dropped.
	 *
	 * @param string $path Route regex without namespace.
	 * @return string
	 */
	public function normalize_route( $path ) {
		$route = preg_replace( '/\(\?P<([a-zA-Z0-9_]+)>[^)]*\)/', ':$1', $path );

		do {
			$previous = $route;
			$route    = preg_replace( '/\(\?:[^()]*\)\?/', '', $route );
		} while ( $route !== $previous );

		return rtrim( $route, '/' );
	}

	/**
	 * Name an action from its route.
	 *
	 * Examples:
	 *   WRITE /campaigns                         → campaign_created
	 *   WRITE /campaigns/:campaign_id            → campaign_updated
	 *   WRITE /lists/:list_id/delete             → list_deleted
	 *   WRITE /campaigns/:campaign_id/status-update → campaign_status_update
	 *   WRITE /contacts/import/csv               → contact_import_csv
	 *   WRITE /settings/email                    → settings_email_saved
	 *   GET   /campaign/analytics/:id/opened/:e  → campaign_analytics_opened (failures only)
	 *
	 * @param string $route Normalized route.
	 * @param string $class GET, WRITE or DELETE.
	 * @return string
	 */
	public function derive_name( $route, $class ) {
		$segments = array_values( array_filter( explode( '/', $route ), 'strlen' ) );
		$literals = array_values(
			array_filter(
				$segments,
				function ( $segment ) {
					return ':' !== $segment[0];
				}
			)
		);

		if ( empty( $literals ) ) {
			return 'unknown';
		}

		$area          = $this->area_of( $route );
		$rest          = array_map( array( $this, 'to_snake' ), array_slice( $literals, 1 ) );
		$ends_in_param = ':' === substr( end( $segments ), 0, 1 );
		$verb          = '';

		if ( ! empty( $rest ) && 'delete' === end( $rest ) ) {
			array_pop( $rest );
			$verb = 'deleted';
		} elseif ( 'DELETE' === $class ) {
			$verb = 'deleted';
		} elseif ( 'WRITE' === $class && $ends_in_param ) {
			$verb = 'updated';
		} elseif ( 'WRITE' === $class && empty( $rest ) ) {
			$verb = 'created';
		} elseif ( 'WRITE' === $class && 'settings' === $area && 1 === count( $rest ) ) {
			$verb = 'saved';
		}

		$name = implode( '_', array_filter( array_merge( array( $area ), $rest, array( $verb ) ), 'strlen' ) );

		return trim( preg_replace( '/_+/', '_', preg_replace( '/[^a-z0-9_]/', '_', strtolower( $name ) ) ), '_' );
	}

	/**
	 * Feature area of a normalized route (its first literal segment).
	 *
	 * @param string $route Normalized route.
	 * @return string
	 */
	private function area_of( $route ) {
		$segments = array_values( array_filter( explode( '/', $route ), 'strlen' ) );
		$first    = $segments[0] ?? '';

		return self::AREA_ALIASES[ $first ] ?? $this->to_snake( $first );
	}

	/**
	 * `sendTest` / `status-update` → `send_test` / `status_update`.
	 *
	 * @param string $segment Route segment.
	 * @return string
	 */
	private function to_snake( $segment ) {
		return strtolower( str_replace( '-', '_', preg_replace( '/(?<!^)[A-Z]/', '_$0', $segment ) ) );
	}

	/**
	 * Work out whether a REST response succeeded.
	 *
	 * Mail Mint signals failure either with an HTTP status >= 400 or with
	 * HTTP 200 and `success: false` in the body.
	 *
	 * @param mixed $response Response.
	 * @return array [ bool $ok, int $status, string $error_code ]
	 */
	private function outcome( $response ) {
		if ( is_wp_error( $response ) ) {
			$data   = $response->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;

			return array( false, $status, sanitize_key( (string) $response->get_error_code() ) );
		}

		if ( $response instanceof \WP_HTTP_Response ) {
			$status = (int) $response->get_status();
			$data   = $response->get_data();
			$failed = is_array( $data ) && array_key_exists( 'success', $data ) && false === $data['success'];
			$code   = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? sanitize_key( $data['code'] ) : '';

			return array( $status < 400 && ! $failed, $status, $code );
		}

		return array( true, 200, '' );
	}

	/**
	 * Append an entry to the trail.
	 *
	 * Repeating the last unsent activity with the same properties within
	 * COLLAPSE_WINDOW updates that entry and bumps its count instead.
	 *
	 * @param string $activity   Activity name.
	 * @param array  $properties Extra scalar properties.
	 * @return void
	 */
	public function record( $activity, array $properties = array() ) {
		$trail = $this->get_trail();
		$now   = time();
		$index = count( $trail ) - 1;

		if ( $index >= 0 ) {
			$last = $trail[ $index ];
			if ( empty( $last['s'] ) && $last['a'] === $activity && ( $last['p'] ?? array() ) === $properties
				&& $now - (int) $last['t'] <= self::COLLAPSE_WINDOW ) {
				$trail[ $index ]['t'] = $now;
				$trail[ $index ]['n'] = (int) ( $last['n'] ?? 1 ) + 1;
				$this->save_trail( $trail );
				return;
			}
		}

		$trail[] = array(
			'a' => $activity,
			'p' => $properties,
			't' => $now,
		);

		$this->save_trail( $trail );
	}

	/**
	 * Load the trail, dropping expired or malformed entries.
	 *
	 * @return array
	 */
	public function get_trail() {
		$trail = get_option( self::OPTION, array() );

		if ( ! is_array( $trail ) ) {
			return array();
		}

		$cutoff = time() - self::MAX_AGE;

		return array_values(
			array_filter(
				$trail,
				function ( $entry ) use ( $cutoff ) {
					return is_array( $entry ) && isset( $entry['a'], $entry['t'] ) && $entry['t'] >= $cutoff;
				}
			)
		);
	}

	/**
	 * Persist the trail, keeping the newest MAX_UNSENT unsent and KEEP_SENT sent entries.
	 *
	 * @param array $trail Trail entries.
	 * @return void
	 */
	private function save_trail( array $trail ) {
		$kept   = array();
		$sent   = 0;
		$unsent = 0;

		for ( $i = count( $trail ) - 1; $i >= 0; $i-- ) {
			if ( ! empty( $trail[ $i ]['s'] ) ) {
				if ( $sent++ < self::KEEP_SENT ) {
					$kept[] = $trail[ $i ];
				}
			} elseif ( $unsent++ < self::MAX_UNSENT ) {
				$kept[] = $trail[ $i ];
			}
		}

		$kept = array_reverse( $kept );

		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $kept, '', false );
			return;
		}

		update_option( self::OPTION, $kept, false );
	}

	/**
	 * Whether the site owner opted in to usage tracking.
	 *
	 * @return bool
	 */
	private function is_opted_in() {
		return $this->client && method_exists( $this->client, 'get_optin_state' )
			&& 'yes' === $this->client->get_optin_state();
	}

	/**
	 * Keep the daily flush scheduled only while the site is opted in.
	 *
	 * @return void
	 */
	public function maybe_schedule_flush() {
		$next = wp_next_scheduled( self::FLUSH_HOOK );

		if ( $this->is_opted_in() ) {
			if ( ! $next ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::FLUSH_HOOK );
			}
		} elseif ( $next ) {
			wp_unschedule_event( $next, self::FLUSH_HOOK );
		}
	}

	/**
	 * Daily cron (opted-in sites only): send unsent entries and mark them sent.
	 *
	 * @return void
	 */
	public function flush() {
		if ( ! $this->is_opted_in() ) {
			return;
		}

		$unsent = array_values(
			array_filter(
				$this->get_trail(),
				function ( $entry ) {
					return empty( $entry['s'] );
				}
			)
		);

		if ( empty( $unsent ) ) {
			return;
		}

		$last_sent_at = max( array_column( $unsent, 't' ) );

		if ( ! $this->send( $unsent ) ) {
			return;
		}

		// Re-read so entries recorded while sending are kept unsent.
		$trail = $this->get_trail();
		foreach ( $trail as $index => $entry ) {
			if ( empty( $entry['s'] ) && (int) $entry['t'] <= $last_sent_at ) {
				$trail[ $index ]['s'] = true;
			}
		}
		$this->save_trail( $trail );
	}

	/**
	 * On deactivation (every site): send unsent entries plus a summary event.
	 *
	 * @return void
	 */
	public function send_on_deactivation() {
		wp_clear_scheduled_hook( self::FLUSH_HOOK );

		// Always send the context, even with an empty trail: deactivation_source
		// explains the SDK's `reason: none` deactivations on sites with no activity.
		$trail  = $this->get_trail();
		$unsent = array_values(
			array_filter(
				$trail,
				function ( $entry ) {
					return empty( $entry['s'] );
				}
			)
		);

		if ( $this->send( $unsent, $this->build_context( $trail ) ) ) {
			delete_option( self::OPTION );
		}
	}

	/**
	 * Summary of the whole trail for the `deactivation_context` event.
	 *
	 * @param array $trail All entries (sent and unsent).
	 * @return array
	 */
	private function build_context( array $trail ) {
		$now          = time();
		$last         = empty( $trail ) ? null : end( $trail );
		$last_feature = '';
		$last_screen  = '';
		$last_failure = '';
		$failures     = 0;

		foreach ( $trail as $entry ) {
			if ( 'screen_viewed' === $entry['a'] ) {
				$last_screen = $entry['p']['screen'] ?? '';
				continue;
			}
			$last_feature = $entry['a'];
			if ( '_failed' === substr( $entry['a'], -7 ) ) {
				$last_failure = $entry['a'];
				++$failures;
			}
		}

		$installed = (int) get_option( 'mailmint_install_timestamp', 0 );

		return array(
			'deactivation_source'         => $this->deactivation_source(),
			'modal_reason_submitted'      => 'yes' === get_transient( 'mail-mint_deactivation_event_sent' ),
			'last_activity'               => $last ? $this->label( $last ) : '',
			'last_feature'                => $last_feature,
			'last_screen'                 => $last_screen,
			'last_failure'                => $last_failure,
			'failures_in_trail'           => $failures,
			'minutes_since_last_activity' => $last ? (int) floor( ( $now - (int) $last['t'] ) / MINUTE_IN_SECONDS ) : null,
			'days_since_install'          => $installed ? (int) floor( ( $now - $installed ) / DAY_IN_SECONDS ) : null,
			'trail_length'                => count( $trail ),
			'recent_activities'           => array_map( array( $this, 'label' ), array_slice( $trail, -15 ) ),
			'opted_in'                    => $this->is_opted_in(),
		);
	}

	/**
	 * How the plugin is being deactivated.
	 *
	 * The Linno SDK's deactivation modal only opens for the single "Deactivate"
	 * link on plugins.php; every other route makes the SDK send `reason: none`.
	 * This tells those routes apart. Runs on `mailmint_plugin_deactivated`,
	 * which fires before the SDK clears its "modal submitted" transient.
	 *
	 * @return string wp_cli | rest_api | xmlrpc | cron | recovery_mode | network_admin |
	 *                bulk_action | modal_submitted | link_modal_skipped | ajax | programmatic
	 */
	private function deactivation_source() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only classification; core verified the request.
		$action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
		$network = ! empty( $_REQUEST['networkwide'] );
		// phpcs:enable

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp_cli';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest_api';
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return 'xmlrpc';
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'cron';
		}
		if ( function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode() ) {
			return 'recovery_mode';
		}
		if ( $network || ( function_exists( 'is_network_admin' ) && is_network_admin() ) ) {
			return 'network_admin';
		}
		if ( 'deactivate-selected' === $action || 'deactivate-selected' === $action2 ) {
			return 'bulk_action';
		}
		if ( 'deactivate' === $action ) {
			return 'yes' === get_transient( 'mail-mint_deactivation_event_sent' ) ? 'modal_submitted' : 'link_modal_skipped';
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return 'ajax';
		}

		return 'programmatic';
	}

	/**
	 * Short label for an entry, e.g. `screen:/campaigns` or `campaign_created`.
	 *
	 * @param array $entry Trail entry.
	 * @return string
	 */
	private function label( $entry ) {
		return 'screen_viewed' === $entry['a'] ? 'screen:' . ( $entry['p']['screen'] ?? '' ) : $entry['a'];
	}

	/**
	 * Send entries (and optionally a deactivation summary) to PostHog.
	 *
	 * Screens go out as PostHog `$screen` events (`$screen_name` = `mail-mint<route>`)
	 * so they appear as screens in Paths; everything else as `mailmint_activity/<name>`.
	 *
	 * @param array      $entries Entries to send, oldest first.
	 * @param array|null $context Deactivation summary, or null.
	 * @return bool True when PostHog accepted the batch.
	 */
	private function send( array $entries, $context = null ) {
		if ( ( empty( $entries ) && null === $context ) || ! defined( 'MRM_POSTHOG_API_KEY' )
			|| ! class_exists( PostHog::class ) || ! class_exists( Utils::class ) ) {
			return false;
		}

		$now         = time();
		$distinct_id = Utils::getUniqueSiteId();
		$base        = array(
			'plugin_name'    => 'Mail Mint',
			'plugin_version' => defined( 'MRM_VERSION' ) ? MRM_VERSION : '',
			'is_pro_active'  => defined( 'MAIL_MINT_PRO_VERSION' ),
			'source'         => null === $context ? 'activity_trail_daily' : 'activity_trail_deactivation',
		);

		try {
			PostHog::init( MRM_POSTHOG_API_KEY, array( 'host' => self::POSTHOG_HOST ) );

			foreach ( $entries as $entry ) {
				$properties = array_merge(
					$base,
					is_array( $entry['p'] ?? null ) ? $entry['p'] : array(),
					array( 'repeat_count' => (int) ( $entry['n'] ?? 1 ) )
				);

				if ( null !== $context ) {
					$properties['seconds_before_deactivation'] = max( 0, $now - (int) $entry['t'] );
				}

				if ( 'screen_viewed' === $entry['a'] ) {
					$event                      = '$screen';
					$properties['$screen_name'] = 'mail-mint' . ( $entry['p']['screen'] ?? '' );
				} else {
					$event = self::EVENT_PREFIX . $entry['a'];
				}

				PostHog::capture(
					array(
						'distinctId' => $distinct_id,
						'event'      => $event,
						'properties' => $properties,
						'timestamp'  => (int) $entry['t'],
					)
				);
			}

			if ( null !== $context ) {
				PostHog::capture(
					array(
						'distinctId' => $distinct_id,
						'event'      => self::EVENT_PREFIX . 'deactivation_context',
						'properties' => array_merge( $base, $context ),
						'timestamp'  => $now,
					)
				);
			}

			// flush() returns the last HttpResponse (always truthy), so check the status code.
			$response = PostHog::flush();

			return is_object( $response ) && method_exists( $response, 'getResponseCode' )
				? 200 === (int) $response->getResponseCode()
				: (bool) $response;
		} catch ( \Throwable $e ) {
			// Never block the request or deactivation on telemetry.
			return false;
		}
	}
}
