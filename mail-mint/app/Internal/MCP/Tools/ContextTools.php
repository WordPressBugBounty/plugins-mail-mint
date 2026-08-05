<?php
/**
 * MCP Context Tool — mail-mint/get-crm-context
 *
 * Returns a discovery surface (user, site, stats, tags, lists, custom fields,
 * default sender, enums, and usage guidelines) for AI agents.
 *
 * Cached 60 s per user via a transient so repeated calls are cheap.
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\DataBase\Models\CustomFieldModel;
use Mint\MRM\Utilities\Helper\PermissionManager;

class ContextTools {

	/**
	 * Tool definition for AbilitiesRegistrar.
	 */
	public static function definition(): array {
		return array(
			'label'               => __( 'Get CRM Context', 'mrm' ),
			'description'         => 'Returns identity, permissions, stats, top tags/lists, custom fields schema, enums, and default sender. Use this as the starting point for every session. Cached 60 s.',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => function () {
				return PermissionManager::current_user_can( 'mint_view_dashboard' )()
					|| PermissionManager::current_user_can( 'mint_read_contacts' )();
			},
			'annotations'         => array( 'readonly' ),
		);
	}

	/**
	 * Tool definition for mail-mint/check-setup-status ability.
	 *
	 * @return array
	 */
	public static function setupDefinition(): array {
		return array(
			'label'               => __( 'Check Site Setup & Deliverability Status', 'mrm' ),
			'description'         => 'Audits full site email marketing setup: SMTP plugin, sender email domain, sender name, business physical address (CAN-SPAM/GDPR), lists, contacts, opt-in forms, unsubscribe settings, and WooCommerce integration. Use when user asks about setup, onboarding, deliverability, or getting started.',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => new \stdClass(),
			),
			'execute_callback'    => array( self::class, 'getSetupStatus' ),
			'permission_callback' => function () {
				return PermissionManager::current_user_can( 'mint_view_dashboard' )()
					|| PermissionManager::current_user_can( 'mint_manage_settings' )();
			},
			'annotations'         => array( 'readonly' ),
		);
	}

	/**
	 * Tool definition for mail-mint/update-email-settings ability.
	 *
	 * @return array
	 */
	public static function updateEmailSettingsDefinition(): array {
		return array(
			'label'               => __( 'Update Email Settings', 'mrm' ),
			'description'         => 'Updates Mail Mint sender email settings (from_name, from_email, reply_name, reply_email). Use when the user asks AI to set or update their sender email address or sender name.',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'from_name'   => array(
						'type'        => 'string',
						'description' => 'Sender name (e.g., My Company).',
					),
					'from_email'  => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => 'Sender email address (e.g., info@mydomain.com).',
					),
					'reply_name'  => array(
						'type'        => 'string',
						'description' => 'Optional reply-to name.',
					),
					'reply_email' => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => 'Optional reply-to email address.',
					),
				),
			),
			'execute_callback'    => array( self::class, 'updateEmailSettings' ),
			'permission_callback' => function () {
				return PermissionManager::current_user_can( 'mint_manage_settings' )();
			},
			'annotations'         => array(),
		);
	}

	/**
	 * Tool definition for mail-mint/update-business-settings ability.
	 *
	 * @return array
	 */
	public static function updateBusinessSettingsDefinition(): array {
		return array(
			'label'               => __( 'Update Business Basic Settings', 'mrm' ),
			'description'         => 'Updates Mail Mint business basic settings (business_name, phone, address, city, state, zip, country). Use when the user asks AI to set or update their business address or business name for anti-spam compliance.',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'business_name' => array(
						'type'        => 'string',
						'description' => 'Business or brand name.',
					),
					'phone'         => array(
						'type'        => 'string',
						'description' => 'Business phone number.',
					),
					'address'       => array(
						'type'        => 'string',
						'description' => 'Street address line.',
					),
					'city'          => array(
						'type'        => 'string',
						'description' => 'City name.',
					),
					'state'         => array(
						'type'        => 'string',
						'description' => 'State or region.',
					),
					'zip'           => array(
						'type'        => 'string',
						'description' => 'ZIP or postal code.',
					),
					'country'       => array(
						'type'        => 'string',
						'description' => 'Country name.',
					),
				),
			),
			'execute_callback'    => array( self::class, 'updateBusinessSettings' ),
			'permission_callback' => function () {
				return PermissionManager::current_user_can( 'mint_manage_settings' )();
			},
			'annotations'         => array(),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param array $params (unused — no input)
	 * @return array|false|\WP_Error
	 */
	public static function execute( array $params = array() ) {
		$user_id = get_current_user_id();
		$version = (int) get_option( 'mail_mint_mcp_context_version', 0 );
		// Cache key includes the shared version so any invalidateCache() call auto-busts it.
		$cache_key = 'mail_mint_mcp_ctx_' . $user_id . '_v' . $version;

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$result = self::build( $user_id );
		set_transient( $cache_key, $result, 60 );

		return $result;
	}

	/**
	 * Delete the cached context for all users.
	 * Called by cache-invalidation hooks in MCPInit whenever tags, lists, or custom fields change.
	 * Uses a shared version key so a single increment invalidates all per-user transients without
	 * a full wp_options table scan.
	 */
	public static function invalidateCache(): void {
		// Bump the shared version key; per-user caches check this and rebuild on the next call.
		$version = (int) get_option( 'mail_mint_mcp_context_version', 0 );
		update_option( 'mail_mint_mcp_context_version', $version + 1, false );
	}

	// -----------------------------------------------------------------------
	// Builder
	// -----------------------------------------------------------------------

	private static function build( int $user_id ): array {
		$wp_user = get_userdata( $user_id );
		$perms   = self::buildPermissions();

		return array(
			'you'                  => array(
				'wp_user_id'  => $user_id,
				'name'        => $wp_user ? $wp_user->display_name : '',
				'email'       => $wp_user ? $wp_user->user_email : '',
				'is_admin'    => current_user_can( 'manage_options' ),
				'permissions' => $perms,
			),
			'site'                 => array(
				'name'     => get_bloginfo( 'name' ),
				'url'      => home_url(),
				'timezone' => wp_timezone_string(),
				'locale'   => get_locale(),
			),
			'stats'                => self::buildStats(),
			'top_tags'             => self::buildTopGroups( 'tags' ),
			'top_lists'            => self::buildTopGroups( 'lists' ),
			'custom_fields_schema' => self::buildCustomFieldsSchema(),
			'default_sender'       => self::buildDefaultSender(),
			'setup_readiness'      => array(
				'status'  => self::getSetupStatus()['overall_status'],
				'message' => 'Call mail-mint/check-setup-status for complete audit details (SMTP, sender domain, business physical address, contacts, forms, deliverability).',
			),
			'segments'             => self::buildSegmentsHint(),
			'enums'                => array(
				'contact_statuses'  => array( 'pending', 'subscribed', 'unsubscribed', 'complained', 'bounced', 'inactive' ),
				'campaign_types'    => array( 'regular', 'sequence', 'recurring', 'automation' ),
				'campaign_statuses' => array( 'draft', 'active', 'schedule', 'archived', 'processing', 'processed' ),
				'contact_types'     => array( 'lead', 'customer' ),
				'note_types'        => array( 'note', 'call', 'email', 'meeting' ),
				'sort_directions'   => array( 'ASC', 'DESC' ),
			),
			'guidelines'           => self::buildGuidelines(),
		);
	}

	private static function buildPermissions(): array {
		$caps = array(
			'read_contacts'      => 'mint_read_contacts',
			'manage_contacts'    => 'mint_manage_contacts',
			'delete_contacts'    => 'mint_manage_contacts_delete',
			'read_campaigns'     => 'mint_read_campaigns',
			'manage_campaigns'   => 'mint_manage_campaigns',
			'send_campaigns'     => 'mint_manage_campaigns_send',
			'delete_campaigns'   => 'mint_manage_campaigns_delete',
			'read_automations'   => 'mint_read_automations',
			'manage_automations' => 'mint_manage_automations',
			'manage_segments'    => 'mint_manage_contact_cats',
			'delete_segments'    => 'mint_manage_contact_cats_delete',
			'manage_settings'    => 'mint_manage_settings',
		);

		$out = array();
		foreach ( $caps as $label => $cap ) {
			$cb            = PermissionManager::current_user_can( $cap );
			$out[ $label ] = is_callable( $cb ) ? (bool) $cb() : false;
		}
		return $out;
	}

	private static function buildStats(): array {
		global $wpdb;

		$contacts_table  = $wpdb->prefix . 'mint_contacts';
		$campaigns_table = $wpdb->prefix . 'mint_campaigns';
		$groups_table    = $wpdb->prefix . 'mint_contact_groups';

		// One grouped pass for status counts instead of a scan per status.
		$status_counts = array();
		foreach ( (array) $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$contacts_table} GROUP BY status", ARRAY_A ) as $r ) {
			$status_counts[ (string) $r['status'] ] = (int) $r['c'];
		}
		// One grouped pass for stage (contact_type) counts.
		$stage_counts = array();
		foreach ( (array) $wpdb->get_results( "SELECT stage, COUNT(*) AS c FROM {$contacts_table} GROUP BY stage", ARRAY_A ) as $r ) {
			$stage_counts[ (string) $r['stage'] ] = (int) $r['c'];
		}
		// One grouped pass for group (tag/list) counts.
		$group_counts = array();
		foreach ( (array) $wpdb->get_results( "SELECT type, COUNT(*) AS c FROM {$groups_table} GROUP BY type", ARRAY_A ) as $r ) {
			$group_counts[ (string) $r['type'] ] = (int) $r['c'];
		}

		$total_contacts = array_sum( $status_counts );
		$subscribed     = $status_counts['subscribed'] ?? 0;
		$unsubscribed   = $status_counts['unsubscribed'] ?? 0;
		$pending        = $status_counts['pending'] ?? 0;
		// contact_type breakdown — DB column is 'stage'.
		$leads           = $stage_counts['lead'] ?? 0;
		$customers       = $stage_counts['customer'] ?? 0;
		$total_campaigns = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$campaigns_table}" );
		$total_tags      = $group_counts['tags'] ?? 0;
		$total_lists     = $group_counts['lists'] ?? 0;

		$total_automations = 0;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}mint_automations'" ) ) {
			$total_automations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mint_automations" );
		}

		return compact(
			'total_contacts',
			'subscribed',
			'unsubscribed',
			'pending',
			'leads',
			'customers',
			'total_campaigns',
			'total_automations',
			'total_tags',
			'total_lists'
		);
	}

	private static function buildTopGroups( string $type ): array {
		global $wpdb;

		$groups_table = $wpdb->prefix . 'mint_contact_groups';
		// Actual pivot table is mint_contact_group_relationship with column group_id.
		$pivot_table = $wpdb->prefix . 'mint_contact_group_relationship';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.id, g.title, COUNT(p.contact_id) AS count
                 FROM {$groups_table} g
                 LEFT JOIN {$pivot_table} p ON p.group_id = g.id
                 WHERE g.type = %s
                 GROUP BY g.id
                 ORDER BY count DESC
                 LIMIT 20",
				$type
			),
			ARRAY_A
		);

		return array_map(
			function ( $r ) {
				return array(
					'id'    => (int) $r['id'],
					'title' => $r['title'],
					'count' => (int) $r['count'],
				);
			},
			$rows ?: array()
		);
	}

	private static function buildCustomFieldsSchema(): array {
		$fields = CustomFieldModel::get_all( 0, 200 );
		if ( empty( $fields['data'] ) ) {
			return array();
		}
		return array_map(
			function ( $f ) {
				if ( is_object( $f ) ) {
					  $f = (array) $f;
				}
				return array(
					'slug'    => $f['slug'] ?? '',
					'label'   => $f['title'] ?? $f['label'] ?? '',
					'type'    => $f['type'] ?? '',
					'options' => $f['meta'] ?? array(),
				);
			},
			$fields['data']
		);
	}

	private static function buildDefaultSender(): array {
		$settings = get_option( '_mrm_email_settings', array() );
		return array(
			'from_name'  => $settings['from_name'] ?? get_bloginfo( 'name' ),
			'from_email' => $settings['from_email'] ?? get_option( 'admin_email' ),
		);
	}

	/**
	 * Segment awareness. This context intentionally does NOT count segments in
	 * stats (segments are a separate, Pro-registered tool). Without this hint an
	 * agent grounds on stats, sees no segment data, and wrongly reports "no
	 * segments". We detect the tool by ability existence (never a Pro class
	 * reference), so this stays boundary-safe on Free-only sites.
	 *
	 * @return string
	 */
	private static function buildSegmentsHint(): string {
		$has_segments_tool = function_exists( 'wp_get_ability' ) && wp_get_ability( 'mail-mint/list-segments' );

		return $has_segments_tool
			? 'Saved/advanced segments are NOT counted in the stats above. Call mail-mint/list-segments to enumerate them — never conclude there are no segments from this context. Note: resolve-segments and apply-segments-to-contacts operate on tags and lists, not saved segments.'
			: 'This site has no saved-segments feature. Here "segments" means tags and lists — browse them with list-tags / list-lists.';
	}

	private static function buildGuidelines(): array {
		return array(
			'always_start_with'  => 'Call mail-mint/get-crm-context first to discover IDs, tag names, and enums.',
			'ids_vs_slugs'       => 'Most write tools accept tag/list IDs (integers). Use top_tags/top_lists to resolve common names; for any tag/list not in that top-20 list, call mail-mint/resolve-segments with the names, or browse with mail-mint/list-tags / mail-mint/list-lists.',
			'segments'           => 'Saved/advanced segments are listed by mail-mint/list-segments (Pro). Despite their names, resolve-segments and apply-segments-to-contacts operate on tags and lists, NOT saved segments. To audit or list segments, call list-segments — never conclude there are none from this context, which does not count them.',
			'upsert_contacts'    => 'Use mail-mint/upsert-contact with if_exists=merge to safely update existing contacts.',
			'bulk_operations'    => 'For bulk updates use mail-mint/apply-segments-to-contacts with dry_run=true first to preview.',
			'campaign_workflow'  => 'Full flow: upsert-campaign (shell + recipients) → compose-campaign-email (subject + structured content; generates the email design) → send-test-email to preview → change-campaign-status to schedule/activate. Check results later with get-campaign-analytics.',
			'email_composition'  => 'compose-campaign-email takes structured content (hero, paragraphs, bullets, buttons, images) and a style preset (clean/dark/warm, colors overridable) — never raw builder JSON. Discover designs with list-email-templates. Link every button and inline link to site.url above (or a path under it) unless the user gave you a destination; placeholder domains like example.com are rewritten to the site URL, losing the path.',
			'destructive_tools'  => 'Tools annotated destructive require explicit confirmation from the user before calling.',
			'pagination'         => 'Default per_page is 10. Max is 200. Always check total_pages and paginate if needed.',
			'custom_fields'      => 'Use the custom_fields_schema from context to discover valid custom field slugs.',
			'check_setup_status' => 'Call mail-mint/check-setup-status when user asks about setup readiness, onboarding, SMTP, or deliverability.',
		);
	}

	/**
	 * Run full site setup audit for email marketing readiness.
	 *
	 * @param array $params (unused).
	 * @return array
	 */
	public static function getSetupStatus( array $params = array() ): array {
		$smtp             = self::checkSmtpSetup();
		$sender_email     = self::checkSenderEmail();
		$sender_name      = self::checkSenderName();
		$business_address = self::checkBusinessAddress();
		$audience_forms   = self::checkAudienceAndForms();
		$unsubscribe_page = self::checkUnsubscribePage();
		$woocommerce      = self::checkWooCommerceSetup();

		$items = array_merge(
			array(
				'smtp'             => $smtp,
				'sender_email'     => $sender_email,
				'sender_name'      => $sender_name,
				'business_address' => $business_address,
			),
			$audience_forms['items'],
			array(
				'unsubscribe_page' => $unsubscribe_page,
			)
		);

		if ( ! empty( $woocommerce ) ) {
			$items['woocommerce'] = $woocommerce;
		}

		$fails    = 0;
		$warnings = 0;
		$passes   = 0;

		foreach ( $items as $item ) {
			$status = $item['status'] ?? 'pass';
			if ( 'fail' === $status ) {
				$fails++;
			} elseif ( 'warning' === $status ) {
				$warnings++;
			} else {
				$passes++;
			}
		}

		$overall_status = 'READY';
		if ( $fails > 0 ) {
			$overall_status = 'NOT_READY';
		} elseif ( $warnings > 0 ) {
			$overall_status = 'NEEDS_ATTENTION';
		}

		return array(
			'overall_status' => $overall_status,
			'summary'        => array(
				'total_checks' => count( $items ),
				'passed'       => $passes,
				'warnings'     => $warnings,
				'failed'       => $fails,
			),
			'checks'         => $items,
		);
	}

	/**
	 * Check SMTP and mailer setup.
	 *
	 * @return array
	 */
	private static function checkSmtpSetup(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$smtp_plugins = array(
			'wp-mail-smtp/wp_mail_smtp.php'       => 'WP Mail SMTP',
			'fluent-smtp/fluent-smtp.php'         => 'FluentSMTP',
			'post-smtp/post-smtp.php'             => 'Post SMTP',
			'easy-wp-smtp/easy-wp-smtp.php'       => 'Easy WP SMTP',
			'smtp-mailer/main.php'                => 'SMTP Mailer',
			'wp-offload-ses/wp-offload-ses.php'   => 'WP Offload SES',
			'gmail-smtp/main.php'                 => 'Gmail SMTP',
			'sendinblue-subscribe/sendinblue.php' => 'Brevo (Sendinblue)',
			'mailgun/mailgun.php'                 => 'Mailgun',
		);

		$detected_plugin = null;
		foreach ( $smtp_plugins as $plugin_path => $name ) {
			if ( is_plugin_active( $plugin_path ) ) {
				$detected_plugin = $name;
				break;
			}
		}

		$mail_mint_mailer  = get_option( 'mrm_email_service_settings', array() );
		$mailer_slug       = ! empty( $mail_mint_mailer['mailer'] ) ? $mail_mint_mailer['mailer'] : 'wpmail';
		$has_custom_mailer = 'wpmail' !== $mailer_slug;

		$has_smtp_constants = ( defined( 'SMTP_HOST' ) && SMTP_HOST )
			|| ( defined( 'WPMS_ON' ) && WPMS_ON )
			|| defined( 'POST_SMTP_AUTH' );

		$has_smtp_classes = class_exists( 'WPMailSMTP\Core' )
			|| function_exists( 'wp_mail_smtp' )
			|| defined( 'FLUENTMAIL' )
			|| class_exists( 'FluentSmtp\App\App' )
			|| class_exists( 'Postman' )
			|| defined( 'POST_SMTP_VER' )
			|| class_exists( 'EasyWPSMTP' )
			|| defined( 'EASY_WP_SMTP_VER' );

		if ( $detected_plugin ) {
			return array(
				'name'           => __( 'SMTP / Mailer Configuration', 'mrm' ),
				'status'         => 'pass',
				// translators: %s: SMTP plugin name.
				'details'        => sprintf( __( 'SMTP plugin detected (%s). Mail delivery is configured.', 'mrm' ), $detected_plugin ),
				'recommendation' => null,
			);
		}

		if ( $has_custom_mailer ) {
			return array(
				'name'           => __( 'SMTP / Mailer Configuration', 'mrm' ),
				'status'         => 'pass',
				// translators: %s: Mailer name.
				'details'        => sprintf( __( 'Mail Mint mailer configured (%s).', 'mrm' ), ucfirst( $mailer_slug ) ),
				'recommendation' => null,
			);
		}

		if ( $has_smtp_constants || $has_smtp_classes ) {
			return array(
				'name'           => __( 'SMTP / Mailer Configuration', 'mrm' ),
				'status'         => 'pass',
				'details'        => __( 'Custom SMTP configuration detected in WordPress.', 'mrm' ),
				'recommendation' => null,
			);
		}

		return array(
			'name'           => __( 'SMTP / Mailer Configuration', 'mrm' ),
			'status'         => 'fail',
			'details'        => __( 'No SMTP plugin or custom mailer detected. Emails are using default WordPress wp_mail() / PHP mail(), which frequently fails or gets flagged as SPAM.', 'mrm' ),
			'recommendation' => __( 'Install and configure an SMTP plugin to ensure reliable email deliverability.', 'mrm' ),
		);
	}

	/**
	 * Check sender email address and domain.
	 *
	 * @return array
	 */
	private static function checkSenderEmail(): array {
		$email_settings = get_option( '_mrm_email_settings', array() );
		$from_email     = ! empty( $email_settings['from_email'] ) ? trim( $email_settings['from_email'] ) : get_option( 'admin_email' );

		if ( empty( $from_email ) || ! is_email( $from_email ) ) {
			return array(
				'name'           => __( 'Sender Email Address', 'mrm' ),
				'status'         => 'fail',
				'details'        => __( 'No sender email address configured.', 'mrm' ),
				'recommendation' => __( 'Go to Mail Mint > Settings > Email Settings and set a valid From Email address.', 'mrm' ),
			);
		}

		$free_domains = array(
			'gmail.com',
			'yahoo.com',
			'hotmail.com',
			'outlook.com',
			'icloud.com',
			'aol.com',
			'gmx.com',
			'mail.com',
			'protonmail.com',
			'yandex.com',
			'live.com',
		);

		$parts  = explode( '@', strtolower( $from_email ) );
		$domain = end( $parts );

		if ( in_array( $domain, $free_domains, true ) ) {
			return array(
				'name'           => __( 'Sender Email Address', 'mrm' ),
				'status'         => 'warning',
				'from_email'     => $from_email,
				// translators: 1: Sender email address, 2: Domain name.
				'details'        => sprintf( __( 'Sender email (%1$s) uses a free webmail domain (@%2$s).', 'mrm' ), $from_email, $domain ),
				'recommendation' => __( 'Free webmail domains fail DMARC/SPF authentication when sending bulk emails. Major mailbox providers (Gmail, Yahoo, Outlook) reject these emails or send them to SPAM. Use an email address from your custom domain (e.g. info@yourdomain.com).', 'mrm' ),
			);
		}

		return array(
			'name'           => __( 'Sender Email Address', 'mrm' ),
			'status'         => 'pass',
			'from_email'     => $from_email,
			// translators: %s: Sender email address.
			'details'        => sprintf( __( 'Sender email is configured with custom domain (%s).', 'mrm' ), $from_email ),
			'recommendation' => null,
		);
	}

	/**
	 * Check sender name setup.
	 *
	 * @return array
	 */
	private static function checkSenderName(): array {
		$email_settings = get_option( '_mrm_email_settings', array() );
		$from_name      = ! empty( $email_settings['from_name'] ) ? trim( $email_settings['from_name'] ) : '';

		if ( empty( $from_name ) || 'WordPress' === $from_name ) {
			return array(
				'name'           => __( 'Sender Name', 'mrm' ),
				'status'         => 'warning',
				'details'        => __( 'Sender name is empty or using default "WordPress".', 'mrm' ),
				'recommendation' => __( 'Set a recognizable brand or sender name in Mail Mint Email Settings to improve open rates.', 'mrm' ),
			);
		}

		return array(
			'name'           => __( 'Sender Name', 'mrm' ),
			'status'         => 'pass',
			'from_name'      => $from_name,
			// translators: %s: Sender name.
			'details'        => sprintf( __( 'Sender name is configured ("%s").', 'mrm' ), $from_name ),
			'recommendation' => null,
		);
	}

	/**
	 * Check business physical address setup.
	 *
	 * @return array
	 */
	private static function checkBusinessAddress(): array {
		$basic_settings = get_option( '_mrm_business_basic_info_setting', array() );
		$business_name  = ! empty( $basic_settings['business_name'] ) ? trim( $basic_settings['business_name'] ) : '';
		$address        = ! empty( $basic_settings['business_address'] ) && is_array( $basic_settings['business_address'] ) ? $basic_settings['business_address'] : array();

		$has_name   = ! empty( $business_name );
		$has_street = ! empty( $address['address'] ) || ! empty( $address['address_line_1'] ) || ! empty( $address['street'] );
		$has_city   = ! empty( $address['city'] );

		if ( ! $has_name || ! $has_street || ! $has_city ) {
			return array(
				'name'           => __( 'Business Physical Address', 'mrm' ),
				'status'         => 'fail',
				'details'        => __( 'Business physical address is missing or incomplete in Mail Mint Settings.', 'mrm' ),
				'recommendation' => __( 'CAN-SPAM and GDPR laws require a physical mailing address in every commercial email footer. Missing addresses significantly increase spam flag risks. Complete your physical address in Mail Mint > Settings > Business Info.', 'mrm' ),
			);
		}

		return array(
			'name'           => __( 'Business Physical Address', 'mrm' ),
			'status'         => 'pass',
			// translators: %s: Business name.
			'details'        => sprintf( __( 'Business physical address configured for %s.', 'mrm' ), $business_name ),
			'recommendation' => null,
		);
	}

	/**
	 * Check audience contacts, lists, and forms setup.
	 *
	 * @return array
	 */
	private static function checkAudienceAndForms(): array {
		global $wpdb;

		$contacts_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mint_contacts" );
		$lists_count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mint_contact_groups WHERE type = %s", 'lists' ) );
		$forms_count    = 0;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}mint_forms'" ) ) {
			$forms_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mint_forms" );
		}

		$items = array();

		if ( 0 === $contacts_count ) {
			$items['contacts'] = array(
				'name'           => __( 'Subscribers / Contacts', 'mrm' ),
				'status'         => 'warning',
				'details'        => __( 'No contacts found in Mail Mint.', 'mrm' ),
				'recommendation' => __( 'Import existing contacts CSV or publish an opt-in form to start building your audience.', 'mrm' ),
			);
		} else {
			$items['contacts'] = array(
				'name'           => __( 'Subscribers / Contacts', 'mrm' ),
				'status'         => 'pass',
				// translators: %d: Number of contacts.
				'details'        => sprintf( __( '%d subscriber contact(s) in your database.', 'mrm' ), $contacts_count ),
				'recommendation' => null,
			);
		}

		if ( 0 === $lists_count ) {
			$items['lists'] = array(
				'name'           => __( 'Contact Lists', 'mrm' ),
				'status'         => 'warning',
				'details'        => __( 'No contact lists created to organize subscribers.', 'mrm' ),
				'recommendation' => __( 'Create at least one list (e.g. "Newsletter" or "Main List") to group contacts before creating email campaigns.', 'mrm' ),
			);
		} else {
			$items['lists'] = array(
				'name'           => __( 'Contact Lists', 'mrm' ),
				'status'         => 'pass',
				// translators: %d: Number of contact lists.
				'details'        => sprintf( __( '%d list(s) configured.', 'mrm' ), $lists_count ),
				'recommendation' => null,
			);
		}

		if ( 0 === $forms_count ) {
			$items['forms'] = array(
				'name'           => __( 'Opt-in Forms', 'mrm' ),
				'status'         => 'warning',
				'details'        => __( 'No opt-in forms created.', 'mrm' ),
				'recommendation' => __( 'Create an opt-in form under Mail Mint > Forms to capture leads directly from your site.', 'mrm' ),
			);
		} else {
			$items['forms'] = array(
				'name'           => __( 'Opt-in Forms', 'mrm' ),
				'status'         => 'pass',
				// translators: %d: Number of opt-in forms.
				'details'        => sprintf( __( '%d opt-in form(s) created.', 'mrm' ), $forms_count ),
				'recommendation' => null,
			);
		}

		return array(
			'contacts_count' => $contacts_count,
			'lists_count'    => $lists_count,
			'forms_count'    => $forms_count,
			'items'          => $items,
		);
	}

	/**
	 * Check unsubscribe / preference page setup.
	 *
	 * @return array
	 */
	private static function checkUnsubscribePage(): array {
		$pref_option = get_option( '_mrm_general_preference', array() );
		$page_id     = ! empty( $pref_option['preference_page'] ) ? (int) $pref_option['preference_page'] : 0;

		if ( $page_id > 0 && get_post( $page_id ) ) {
			return array(
				'name'           => __( 'Unsubscribe / Preference Page', 'mrm' ),
				'status'         => 'pass',
				'details'        => __( 'Unsubscribe / Email Preference page is set up.', 'mrm' ),
				'recommendation' => null,
			);
		}

		return array(
			'name'           => __( 'Unsubscribe / Preference Page', 'mrm' ),
			'status'         => 'warning',
			'details'        => __( 'Unsubscribe / Preference page uses default unassigned page.', 'mrm' ),
			'recommendation' => __( 'Ensure your Unsubscribe & Preference page is assigned in Mail Mint > Settings > General.', 'mrm' ),
		);
	}

	/**
	 * Check WooCommerce integration setup.
	 *
	 * @return array|null
	 */
	private static function checkWooCommerceSetup(): ?array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) && ! class_exists( 'WooCommerce' ) ) {
			return null;
		}

		$wc_settings = get_option( '_mrm_wc_settings', array() );
		$is_enabled  = ! empty( $wc_settings['enable'] ) || ! empty( $wc_settings['customer_sync'] );

		if ( $is_enabled ) {
			return array(
				'name'           => __( 'WooCommerce Integration', 'mrm' ),
				'status'         => 'pass',
				'details'        => __( 'WooCommerce integration is active and customer sync is enabled.', 'mrm' ),
				'recommendation' => null,
			);
		}

		return array(
			'name'           => __( 'WooCommerce Integration', 'mrm' ),
			'status'         => 'warning',
			'details'        => __( 'WooCommerce is installed but customer sync / checkout opt-in is not fully configured in Mail Mint.', 'mrm' ),
			'recommendation' => __( 'Configure WooCommerce integration in Mail Mint > Settings > WooCommerce to sync customer orders and abandoned carts.', 'mrm' ),
		);
	}

	/**
	 * Update email settings.
	 *
	 * @param array $params Email settings parameters.
	 * @return array
	 */
	public static function updateEmailSettings( array $params ): array {
		$existing    = get_option( '_mrm_email_settings', array() );
		$from_name   = ! empty( $params['from_name'] ) ? sanitize_text_field( $params['from_name'] ) : ( $existing['from_name'] ?? '' );
		$from_email  = ! empty( $params['from_email'] ) ? sanitize_email( $params['from_email'] ) : ( $existing['from_email'] ?? '' );
		$reply_name  = ! empty( $params['reply_name'] ) ? sanitize_text_field( $params['reply_name'] ) : ( $existing['reply_name'] ?? $from_name );
		$reply_email = ! empty( $params['reply_email'] ) ? sanitize_email( $params['reply_email'] ) : ( $existing['reply_email'] ?? $from_email );

		if ( empty( $from_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'From name is required.', 'mrm' ),
			);
		}

		if ( empty( $from_email ) || ! is_email( $from_email ) ) {
			return array(
				'success' => false,
				'message' => __( 'A valid From Email address is required.', 'mrm' ),
			);
		}

		$new_settings = array_merge(
			$existing,
			array(
				'from_name'   => $from_name,
				'from_email'  => $from_email,
				'reply_name'  => $reply_name,
				'reply_email' => $reply_email,
			)
		);

		update_option( '_mrm_email_settings', $new_settings );
		self::invalidateCache();

		return array(
			'success'          => true,
			'message'          => __( 'Email settings updated successfully.', 'mrm' ),
			'updated_settings' => array(
				'from_name'  => $from_name,
				'from_email' => $from_email,
			),
			'setup_audit'      => self::getSetupStatus(),
		);
	}

	/**
	 * Update business basic info settings.
	 *
	 * @param array $params Business settings parameters.
	 * @return array
	 */
	public static function updateBusinessSettings( array $params ): array {
		$existing = get_option( '_mrm_business_basic_info_setting', array() );

		$business_name = ! empty( $params['business_name'] ) ? sanitize_text_field( $params['business_name'] ) : ( $existing['business_name'] ?? '' );
		$phone         = ! empty( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : ( $existing['phone'] ?? '' );

		$addr_existing = ! empty( $existing['business_address'] ) && is_array( $existing['business_address'] ) ? $existing['business_address'] : array();

		$address_line = ! empty( $params['address'] ) ? sanitize_text_field( $params['address'] ) : ( $addr_existing['address'] ?? $addr_existing['address_line_1'] ?? '' );
		$city         = ! empty( $params['city'] ) ? sanitize_text_field( $params['city'] ) : ( $addr_existing['city'] ?? '' );
		$state        = ! empty( $params['state'] ) ? sanitize_text_field( $params['state'] ) : ( $addr_existing['state'] ?? '' );
		$zip          = ! empty( $params['zip'] ) ? sanitize_text_field( $params['zip'] ) : ( $addr_existing['zip'] ?? '' );
		$country      = ! empty( $params['country'] ) ? sanitize_text_field( $params['country'] ) : ( $addr_existing['country'] ?? '' );

		$new_address = array(
			'address' => $address_line,
			'city'    => $city,
			'state'   => $state,
			'zip'     => $zip,
			'country' => $country,
		);

		$new_options = array(
			'business_name'    => $business_name,
			'phone'            => $phone,
			'business_address' => $new_address,
			'logo_url'         => $existing['logo_url'] ?? '',
		);

		update_option( '_mrm_business_basic_info_setting', $new_options );
		self::invalidateCache();

		return array(
			'success'          => true,
			'message'          => __( 'Business info settings updated successfully.', 'mrm' ),
			'updated_settings' => array(
				'business_name'    => $business_name,
				'business_address' => $new_address,
			),
			'setup_audit'      => self::getSetupStatus(),
		);
	}
}
