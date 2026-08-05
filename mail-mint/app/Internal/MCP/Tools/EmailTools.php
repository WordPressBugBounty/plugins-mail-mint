<?php
/**
 * MCP Email Tools
 *
 * Provides 2 email-sending tools:
 *   - send-test-email  (renders and sends a test, does not create a campaign)
 *   - send-email-to-contact (one-off email to a contact)
 *
 * Both route through the Mail Mint mailer pipeline (MM()->mailer->send) so the
 * configured ESP/SMTP connection, unsubscribe headers, and open/click tracking
 * are honoured — instead of bypassing them via raw wp_mail().
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\Database\Repositories\BroadcastRepository;
use Mint\MRM\Database\Repositories\CampaignRepository;
use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use Mint\MRM\Internal\Campaign\EmailPersonalizer;
use Mint\MRM\Utilites\Helper\Email;
use Mint\MRM\API\Actions\ComplianceAction;
use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;
use MailMint\App\Helper;
use MRM\Common\MrmCommon;

class EmailTools {

    public static function definitions(): array {
        return [

            'mail-mint/send-test-email' => [
                'label'       => __( 'Send Test Email', 'mail-mint' ),
                'description' => 'Render and send a test email to one or more addresses through the configured Mail Mint mailer. Subject is prefixed with "TEST:". Does not create a campaign record and does not inject unsubscribe/tracking (it is a test).',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'to', 'subject', 'body' ],
                    'properties' => [
                        'to'           => [ 'type' => 'array', 'items' => [ 'type' => 'string', 'format' => 'email' ], 'description' => 'Recipient email addresses.' ],
                        'subject'      => [ 'type' => 'string' ],
                        'body'         => [ 'type' => 'string', 'description' => 'HTML email body.' ],
                        'from_name'    => [ 'type' => 'string' ],
                        'from_email'   => [ 'type' => 'string', 'format' => 'email' ],
                        'preview_text' => [ 'type' => 'string' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'sendTestEmail' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns_send' ),
                'annotations'         => [],
            ],

            'mail-mint/send-email-to-contact' => [
                'label'       => __( 'Send Email to Contact', 'mail-mint' ),
                'description' => 'Send a one-off or campaign-resend email to a single Mail Mint contact through the configured mailer. Identify the contact by contact_id (integer) or email address — at least one is required. CAMPAIGN RESEND (preferred): pass campaign_email_id (get-campaign include_emails=true to find it) — subject and body are fetched automatically and the send is linked to the campaign in reporting. CAMPAIGN RESEND (fallback): if you already fetched the body yourself, pass campaign_id so the send is still linked to the campaign — subject and body are required. CUSTOM ONE-OFF: omit both campaign_email_id and campaign_id and supply subject + body directly. Unsubscribe headers are always added for non-transactional sends; open/click tracking follows your compliance settings and can be forced off per-send.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'contact_id' ],
                    'properties' => [
                        'contact_id'         => [ 'type' => 'integer', 'description' => 'Numeric contact ID.' ],
                        'email'              => [ 'type' => 'string', 'format' => 'email', 'description' => 'Contact email address (alternative to contact_id).' ],
                        'campaign_email_id'  => [ 'type' => 'integer', 'description' => 'ID from mint_campaign_emails (get-campaign include_emails=true). When provided, subject and body are loaded automatically from the campaign; you may still pass subject/body to override. Preferred for campaign resends.' ],
                        'campaign_id'        => [ 'type' => 'integer', 'description' => 'Campaign ID. Use when you have already fetched the body yourself and campaign_email_id is unavailable. Ensures the broadcast log is linked to the campaign; subject and body are still required.' ],
                        'subject'            => [ 'type' => 'string', 'description' => 'Required when campaign_email_id is not given.' ],
                        'body'               => [ 'type' => 'string', 'description' => 'HTML email body. Required when campaign_email_id is not given. Supports merge tags (e.g. {{contact.first_name}}).' ],
                        'from_name'          => [ 'type' => 'string' ],
                        'from_email'         => [ 'type' => 'string', 'format' => 'email' ],
                        'is_transactional'   => [ 'type' => 'boolean', 'default' => false, 'description' => 'Skip unsubscribe checks and omit the unsubscribe header (use only for genuinely transactional mail).' ],
                        'click_tracker'      => [ 'type' => 'boolean', 'default' => true, 'description' => 'Rewrite links for click tracking. Defaults to your compliance setting; set false to force off.' ],
                        'open_tracker'       => [ 'type' => 'boolean', 'default' => true, 'description' => 'Inject the open-tracking pixel. Defaults to your compliance setting; set false to force off.' ],
                        'idempotency_key'    => [ 'type' => 'string', 'description' => 'Optional. Strongly recommended: a repeated call with the same key returns the prior result instead of sending the email twice.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'sendEmailToContact' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns_send' ),
                'annotations'         => [],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function sendTestEmail( array $params ): array|\WP_Error {
        $to      = array_filter( array_map( 'sanitize_email', (array) ( $params['to'] ?? [] ) ) );
        $subject = sanitize_text_field( $params['subject'] ?? '' );
        $body    = wp_kses_post( $params['body'] ?? '' );

        if ( empty( $to ) ) {
            return MCPHelper::error( 'missing_param', 'At least one valid recipient email is required.' );
        }
        if ( ! $subject ) {
            return MCPHelper::error( 'missing_param', 'subject is required.' );
        }
        if ( ! $body ) {
            return MCPHelper::error( 'missing_param', 'body is required.' );
        }

        $sender       = self::resolveSender( $params );
        $preview_text = sanitize_text_field( $params['preview_text'] ?? '' );
        $watermark    = CampaignEmailBuilderModel::get_email_footer_watermark();
        $personalizer = new EmailPersonalizer();

        $test_subject = 'TEST: ' . $subject;
        $sent_count   = 0;
        $errors       = [];

        foreach ( $to as $email ) {
            // A test send is not tied to a contact/campaign, so we use a throwaway hash
            // purely so the open-tracking pixel and preview injection render correctly.
            $hash         = MrmCommon::get_rand_email_hash( $email, 1 );
            $headers      = self::baseHeaders( $sender );
            $rendered     = $personalizer->personalizeBody( $body, $hash, $preview_text, 'advanced-builder', $watermark );

            $ok = MM()->mailer->send( $email, $test_subject, $rendered, $headers );
            if ( $ok ) {
                $sent_count++;
            } else {
                $errors[] = $email;
            }
        }

        return [
            'result'     => $sent_count > 0 ? 'sent' : 'failed',
            'recipients' => count( $to ),
            'sent_count' => $sent_count,
            'failed'     => $errors,
        ];
    }

    public static function sendEmailToContact( array $params ): array|\WP_Error {
        $contact_id        = (int) ( $params['contact_id'] ?? 0 );
        $email_param       = sanitize_email( $params['email'] ?? '' );
        $campaign_email_id = (int) ( $params['campaign_email_id'] ?? 0 );
        $campaign_id       = (int) ( $params['campaign_id'] ?? 0 );
        // Email body must NOT go through wp_kses_post — that strips email-critical HTML
        // (MSO conditionals, bgcolor/valign/cellpadding attributes, mso-* CSS, etc.).
        $subject = sanitize_text_field( $params['subject'] ?? '' );
        $body    = wp_unslash( $params['body'] ?? '' );

        if ( ! $contact_id && ! $email_param ) {
            return MCPHelper::error( 'missing_param', 'Provide contact_id or email.' );
        }

        // When a campaign email step ID is given, auto-fill subject + body from the DB
        // so the resend uses the exact same content and is linked to the campaign.
        $log_email_id   = 0;
        $log_campaign_id = 0;
        if ( $campaign_email_id ) {
            global $wpdb;
            $email_step = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT campaign_id, email_subject FROM ' . $wpdb->prefix . 'mint_campaign_emails WHERE id = %d',
                    $campaign_email_id
                ),
                ARRAY_A
            );
            if ( ! $email_step ) {
                return MCPHelper::error( 'not_found', sprintf( 'Campaign email step #%d not found.', $campaign_email_id ) );
            }
            if ( '' === $subject ) {
                $subject = sanitize_text_field( $email_step['email_subject'] ?? '' );
            }
            if ( '' === $body ) {
                $builder = CampaignEmailBuilderModel::get_email_body( $campaign_email_id );
                $body    = $builder['email_body'] ?? '';
            }
            $log_email_id    = $campaign_email_id;
            $log_campaign_id = (int) ( $email_step['campaign_id'] ?? 0 );
        } elseif ( $campaign_id ) {
            // AI fetched the body manually but passed campaign_id — link the broadcast
            // log to the campaign by reusing (or creating) a campaign email step record.
            global $wpdb;
            $email_step = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT id, email_subject FROM ' . $wpdb->prefix . 'mint_campaign_emails WHERE campaign_id = %d ORDER BY email_index ASC, id ASC LIMIT 1',
                    $campaign_id
                ),
                ARRAY_A
            );
            if ( $email_step ) {
                $log_email_id    = (int) $email_step['id'];
                $log_campaign_id = $campaign_id;
                if ( '' === $subject ) {
                    $subject = sanitize_text_field( $email_step['email_subject'] ?? '' );
                }
                if ( '' === $body ) {
                    $builder = CampaignEmailBuilderModel::get_email_body( $log_email_id );
                    $body    = $builder['email_body'] ?? '';
                }
            } else {
                // No email step exists yet — create a minimal one so email_id is never null.
                $new_email_id = ( new CampaignRepository() )->insertCampaignEmail(
                    $campaign_id,
                    [ 'email_subject' => $subject, 'status' => 'sent', 'email_index' => 0 ]
                );
                if ( $new_email_id ) {
                    CampaignEmailBuilderModel::insert(
                        [ 'email_id' => $new_email_id, 'editor_type' => 'classic-editor', 'email_body' => $body ]
                    );
                    $log_email_id    = $new_email_id;
                    $log_campaign_id = $campaign_id;
                }
            }
        }

        if ( ! $subject ) {
            return MCPHelper::error( 'missing_param', 'subject is required (or provide campaign_email_id to auto-fill from the campaign).' );
        }
        if ( ! $body ) {
            return MCPHelper::error( 'missing_param', 'body is required (or provide campaign_email_id to auto-fill from the campaign).' );
        }

        // Idempotency: never send the same email twice on a retry with the same key.
        $idem = MCPHelper::idempotentHit( 'mail-mint/send-email-to-contact', $params );
        if ( null !== $idem ) {
            return $idem;
        }

        if ( ! $contact_id ) {
            $contact_id = (int) ContactModel::get_id_by_email( $email_param );
        }

        $contact = $contact_id ? ContactModel::get( $contact_id ) : null;
        if ( ! $contact ) {
            return MCPHelper::error( 'not_found', 'Contact not found.' );
        }

        $contact_arr   = is_object( $contact ) ? (array) $contact : $contact;
        $contact_email = $contact_arr['email'] ?? '';
        if ( ! $contact_email ) {
            return MCPHelper::error( 'no_email', 'Contact has no email address.' );
        }

        // Skip non-subscribed contacts unless this is explicitly transactional.
        $is_transactional = ! empty( $params['is_transactional'] );
        if ( ! $is_transactional ) {
            $status = $contact_arr['status'] ?? '';
            if ( in_array( $status, [ 'unsubscribed', 'complained', 'bounced' ], true ) ) {
                return MCPHelper::error( 'not_subscribed', 'Contact is "' . $status . '". Use is_transactional=true to override.' );
            }
        }

        $sender       = self::resolveSender( $params );
        $watermark    = CampaignEmailBuilderModel::get_email_footer_watermark();
        $personalizer = new EmailPersonalizer();
        $hash         = MrmCommon::get_rand_email_hash( $contact_email, $contact_id );

        // Resolve tracking modes: the per-send flags can only turn tracking OFF,
        // they cannot override a compliance setting that already disables it.
        $click_mode = ComplianceAction::get_click_tracking_mode();
        $open_mode  = ComplianceAction::get_open_tracking_mode();
        $want_click = ! isset( $params['click_tracker'] ) || ! empty( $params['click_tracker'] );
        $want_open  = ! isset( $params['open_tracker'] ) || ! empty( $params['open_tracker'] );
        if ( ! $want_click ) {
            $click_mode = 'no';
        }
        if ( ! $want_open ) {
            $open_mode = 'no';
        }

        // Merge-tag parsing so {{contact.*}} tags resolve for this recipient.
        $parsed_subject = self::parseForContact( $subject, $contact );
        $parsed_body    = self::parseForContact( $body, $contact );

        // Click tracking: rewrite links before personalisation (mirrors SendMail::process).
        if ( 'no' !== $click_mode ) {
            $parsed_body = Helper::replace_url( $parsed_body, $hash, $click_mode );
        }

        // Headers: add unsubscribe header for non-transactional sends.
        $headers = self::baseHeaders( $sender );
        if ( ! $is_transactional ) {
            $headers = $personalizer->buildHeaders( '', $hash, $headers );
        }

        // Body post-processing: open pixel (gated by $open_mode), preview, watermark, RTL, Pro.
        $rendered = self::personalizeWithOpenMode( $personalizer, $parsed_body, $hash, $watermark, $open_mode );
        $rendered = $personalizer->applyProProcessing( $rendered, $contact_email );

        $sent = MM()->mailer->send( $contact_email, $parsed_subject, $rendered, $headers );

        // Standalone send (no campaign or email step context): anchor the broadcast log
        // to a permanent "MCP Direct Sends" campaign so email_id is never null.
        if ( ! $log_email_id ) {
            $direct_campaign_id = self::getOrCreateDirectSendsCampaign();
            if ( $direct_campaign_id ) {
                $new_email_id = ( new CampaignRepository() )->insertCampaignEmail(
                    $direct_campaign_id,
                    [
                        'email_subject' => $parsed_subject,
                        'sender_name'   => $sender['name'],
                        'sender_email'  => $sender['email'],
                        'status'        => $sent ? 'sent' : 'failed',
                        'email_index'   => 0,
                    ]
                );
                if ( $new_email_id ) {
                    CampaignEmailBuilderModel::insert(
                        [ 'email_id' => $new_email_id, 'editor_type' => 'classic-editor', 'email_body' => $body ]
                    );
                    $log_email_id = $new_email_id;
                    // $log_campaign_id stays 0: email_type remains 'regular' in the broadcast log.
                }
            }
        }

        // Record a broadcast row so the send appears in reporting and so open/click
        // events (which carry the hash) can resolve back to it.
        self::logBroadcast( $contact_id, $contact_email, $hash, (bool) $sent, $log_email_id, $log_campaign_id );

        if ( ! $sent ) {
            return MCPHelper::error( 'send_failed', 'The mailer returned an error. Check your email/SMTP configuration.' );
        }

        do_action( 'mailmint_one_off_email_sent', $contact_id, $parsed_subject );

        $result = [
            'result'     => 'sent',
            'contact_id' => $contact_id,
            'to'         => $contact_email,
            'tracking'   => [ 'open' => $open_mode, 'click' => $click_mode ],
        ];
        MCPHelper::idempotentStore( 'mail-mint/send-email-to-contact', $params, $result );
        return $result;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve sender name/email from params, falling back to Mail Mint settings.
     */
    private static function resolveSender( array $params ): array {
        $settings   = get_option( '_mrm_email_settings', [] );
        $from_name  = sanitize_text_field( $params['from_name'] ?? ( $settings['from_name'] ?? get_bloginfo( 'name' ) ) );
        $from_email = sanitize_email( $params['from_email'] ?? ( $settings['from_email'] ?? get_option( 'admin_email' ) ) );
        return [ 'name' => $from_name, 'email' => $from_email ];
    }

    /**
     * Build the base header array (Content-Type + From).
     */
    private static function baseHeaders( array $sender ): array {
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender['name'] . ' <' . $sender['email'] . '>',
        ];
    }

    /**
     * Apply preview + watermark + RTL, and the open-tracking pixel only when $open_mode
     * is not 'no'.
     *
     * EmailPersonalizer::personalizeBody reads the global compliance mode internally and
     * has no per-call override, so when a caller forces open_tracker=false we replicate
     * its non-pixel steps here (preview/watermark/RTL) and skip the pixel injection. When
     * the pixel IS wanted we delegate to personalizeBody so we stay in lockstep with it.
     */
    private static function personalizeWithOpenMode( EmailPersonalizer $personalizer, string $body, string $hash, string $watermark, string $open_mode ): string {
        if ( 'no' !== $open_mode ) {
            return $personalizer->personalizeBody( $body, $hash, '', 'advanced-builder', $watermark );
        }

        // Pixel suppressed: mirror the remaining personalizeBody steps without it.
        $body = Email::inject_preview_text_on_email_body( '', $body );
        if ( '' !== $watermark ) {
            $body = str_replace( '</html>', $watermark . '</html>', $body );
        }
        return Helper::modify_email_for_rtl( $body );
    }

    /**
     * Parse merge tags for a contact. Defensive: never let a parser error block the send.
     */
    private static function parseForContact( string $content, $contact ): string {
        try {
            if ( class_exists( '\Mint\MRM\Internal\Parser\Parser' ) ) {
                $parsed = \Mint\MRM\Internal\Parser\Parser::parse( $content, $contact, 0, 0, [] );
                if ( is_string( $parsed ) && '' !== $parsed ) {
                    return $parsed;
                }
            }
        } catch ( \Throwable $e ) {
            // fall through to the raw content
        }
        return $content;
    }

    /**
     * Return the ID of the permanent "MCP Direct Sends" campaign, creating it once if needed.
     *
     * Standalone MCP sends must have a valid email_id FK in mint_broadcast_emails, but
     * mint_campaign_emails.campaign_id is NOT NULL. This campaign acts as a silent container
     * (status=archived, never shown in campaign lists) so every standalone send gets a proper
     * email step record without polluting real campaigns.
     */
    private static function getOrCreateDirectSendsCampaign(): int {
        $option_key  = '_mrm_mcp_direct_sends_campaign_id';
        $campaign_id = (int) get_option( $option_key, 0 );

        if ( $campaign_id ) {
            global $wpdb;
            $exists = $wpdb->get_var(
                $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'mint_campaigns WHERE id = %d', $campaign_id )
            );
            if ( $exists ) {
                return (int) $exists;
            }
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'mint_campaigns',
            [
                'title'      => 'MCP Direct Sends',
                'type'       => 'regular',
                'status'     => 'archived',
                'created_by' => get_current_user_id() ?: 1,
                'created_at' => current_time( 'mysql' ),
            ]
        );
        if ( ! $inserted ) {
            return 0;
        }

        $campaign_id = (int) $wpdb->insert_id;
        update_option( $option_key, $campaign_id, false );
        return $campaign_id;
    }

    /**
     * Insert a row in mint_broadcast_emails so the one-off send is auditable and trackable.
     *
     * When $log_email_id and $log_campaign_id are provided the row is linked to the campaign
     * email step, which makes the send visible on the contact profile page and in campaign
     * analytics. email_type is set to 'campaign' in that case; otherwise 'regular'.
     */
    private static function logBroadcast( int $contact_id, string $email, string $hash, bool $sent, int $log_email_id = 0, int $log_campaign_id = 0 ): void {
        try {
            $data = [
                'email_type'    => $log_campaign_id ? 'campaign' : 'regular',
                'email_address' => $email,
                'contact_id'    => $contact_id,
                'email_hash'    => $hash,
                'status'        => $sent ? 'sent' : 'failed',
            ];
            if ( $log_email_id ) {
                $data['email_id'] = $log_email_id;
            }
            if ( $log_campaign_id ) {
                $data['campaign_id'] = $log_campaign_id;
            }
            ( new BroadcastRepository() )->create( $data );
        } catch ( \Throwable $e ) {
            // Logging must never break the send result.
        }
    }
}
