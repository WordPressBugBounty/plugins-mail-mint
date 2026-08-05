<?php
/**
 * MCP Campaign Tools
 *
 * Provides campaign-related tools:
 *   - list-campaigns, get-campaign, get-campaign-analytics (read)
 *   - upsert-campaign, compose-campaign-email, change-campaign-status (write)
 *
 * Uses CampaignRepository (v1.20.0+) instead of the deprecated CampaignModel.
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Database\Repositories\BroadcastRepository;
use Mint\MRM\Database\Repositories\CampaignRepository;
use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\Internal\MCP\Helpers\EmailComposer;
use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;

class CampaignTools {

    public static function definitions(): array {
        return [

            'mail-mint/list-campaigns' => [
                'label'       => __( 'List Campaigns', 'mail-mint' ),
                'description' => 'List email campaigns. Supports search, status filtering, type, sorting, and pagination. Only one status value at a time is supported. Set include_stats=true to add sent/open/click counts per campaign.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'        => [ 'type' => 'string' ],
                        'status'        => [ 'type' => 'string', 'description' => 'Single status: draft, active, schedule, archived, processing, processed.' ],
                        'type'          => [ 'type' => 'string', 'enum' => [ 'regular', 'sequence', 'recurring', 'automation' ] ],
                        'sort_by'       => [ 'type' => 'string', 'default' => 'id' ],
                        'sort_type'     => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
                        'page'          => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page'      => [ 'type' => 'integer', 'default' => 10 ],
                        'include_stats' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Adds stats per campaign: total_sent, total_delivered, total_bounced, opens, clicks, unsubscribes.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listCampaigns' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/get-campaign' => [
                'label'       => __( 'Get Campaign', 'mail-mint' ),
                'description' => 'Get campaign details including email steps. Set include_stats=true for send/open/click counts; use get-campaign-analytics for full analytics with rates and timeline.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'campaign_id' ],
                    'properties' => [
                        'campaign_id'    => [ 'type' => 'integer' ],
                        'include_emails' => [ 'type' => 'boolean', 'default' => false ],
                        'include_stats'  => [ 'type' => 'boolean', 'default' => false ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getCampaign' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/get-campaign-analytics' => [
                'label'       => __( 'Get Campaign Analytics', 'mail-mint' ),
                'description' => 'Full analytics for one campaign: delivery status breakdown, opens, clicks, unsubscribes, bounces, computed rates, and an optional per-day open/click timeline.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'campaign_id' ],
                    'properties' => [
                        'campaign_id'      => [ 'type' => 'integer' ],
                        'include_timeline' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Adds per-day open and click counts.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getCampaignAnalytics' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/compose-campaign-email' => [
                'label'       => __( 'Compose Campaign Email', 'mail-mint' ),
                'description' => 'Create or replace the email content of a draft campaign step from a structured content object (hero, paragraphs, bullets, buttons, images). Generates both the drag-and-drop builder JSON and the sendable HTML — never pass raw builder JSON. Sections render in the order given. Text values may include merge tags like {{contact.first_name}} and basic inline HTML (a, strong, em, br). A standard footer with Email Preference and Unsubscribe links plus a copyright line is appended automatically unless content.include_footer is set to false. Typical flow: upsert-campaign → compose-campaign-email → send-test-email → change-campaign-status.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'campaign_id', 'subject', 'content' ],
                    'properties' => [
                        'campaign_id'  => [ 'type' => 'integer' ],
                        'email_index'  => [ 'type' => 'integer', 'default' => 0, 'description' => 'Which email step of the campaign to write (0-based). Step is created if it does not exist.' ],
                        'subject'      => [ 'type' => 'string' ],
                        'preview_text' => [ 'type' => 'string' ],
                        'sender_name'  => [ 'type' => 'string', 'description' => 'Optional. Falls back to Mail Mint email settings at send time.' ],
                        'sender_email' => [ 'type' => 'string' ],
                        'reply_name'   => [ 'type' => 'string' ],
                        'reply_email'  => [ 'type' => 'string' ],
                        'content'      => [
                            'type'       => 'object',
                            'properties' => [
                                'logo_url' => [ 'type' => 'string' ],
                                'hero'     => [
                                    'type'       => 'object',
                                    'required'   => [ 'heading' ],
                                    'properties' => [
                                        'heading'    => [ 'type' => 'string' ],
                                        'subheading' => [ 'type' => 'string' ],
                                        'image_url'  => [ 'type' => 'string', 'description' => 'Optional. Only pass a real, known image URL (e.g. a media library attachment or a URL the user gave you) — never invent one. If omitted, Mail Mint automatically fills the hero background with a brand-colored gradient instead of leaving it blank or risking a broken image.' ],
                                    ],
                                ],
                                'sections' => [
                                    'type'  => 'array',
                                    'description' => 'Ordered content blocks. Each item: {type: heading|paragraph|bullets|button|image|divider|spacer, ...}. heading/paragraph need text; bullets needs items[]; button needs label+url (a real URL on this site or one the user gave — never a placeholder domain); image src is optional (+alt) — only pass a real, known URL, never invent one, omit it and Mail Mint fills a generated placeholder; spacer takes height (px).',
                                    'items' => [
                                        'type'       => 'object',
                                        'required'   => [ 'type' ],
                                        'properties' => [
                                            'type'   => [ 'type' => 'string', 'enum' => [ 'heading', 'paragraph', 'bullets', 'button', 'image', 'divider', 'spacer' ] ],
                                            'text'   => [ 'type' => 'string' ],
                                            'items'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                            'label'  => [ 'type' => 'string' ],
                                            'url'    => [ 'type' => 'string', 'description' => 'Button destination. Use the site URL from get-crm-context (site.url), a path under it, or a URL the user gave you — never a placeholder like example.com or yourdomain.com. Placeholder domains and relative paths are rewritten to this site automatically, so an invented link silently loses its path.' ],
                                            'src'    => [ 'type' => 'string', 'description' => 'Optional. Only a real, known image URL — never invented. Omit if you do not have one.' ],
                                            'alt'    => [ 'type' => 'string' ],
                                            'height' => [ 'type' => 'integer' ],
                                        ],
                                    ],
                                ],
                                'footer_text'    => [ 'type' => 'string', 'description' => 'Optional note rendered below the main content box in small muted text (e.g. "You received this because you subscribed on our site.").' ],
                                'include_footer' => [ 'type' => 'boolean', 'default' => true, 'description' => 'When true (default), a standard Mail Mint footer is appended inside the content box: a divider, Email Preference and Unsubscribe links, and a copyright line with the business name and address from Mail Mint settings.' ],
                            ],
                        ],
                        'style' => [
                            'type'        => 'object',
                            'description' => 'Optional. preset: clean (default) | dark | warm. Any palette key can be overridden with a hex color: page_background, content_background, text_color, muted_color, accent_color, button_text_color, hero_background, hero_text_color; plus font_family.',
                            'properties'  => [
                                'preset'             => [ 'type' => 'string', 'enum' => [ 'clean', 'dark', 'warm' ] ],
                                'page_background'    => [ 'type' => 'string' ],
                                'content_background' => [ 'type' => 'string' ],
                                'text_color'         => [ 'type' => 'string' ],
                                'muted_color'        => [ 'type' => 'string' ],
                                'accent_color'       => [ 'type' => 'string' ],
                                'button_text_color'  => [ 'type' => 'string' ],
                                'hero_background'    => [ 'type' => 'string' ],
                                'hero_text_color'    => [ 'type' => 'string' ],
                                'font_family'        => [ 'type' => 'string' ],
                            ],
                        ],
                        'idempotency_key' => [ 'type' => 'string', 'description' => 'Optional. Pass a stable key to make retries safe.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'composeCampaignEmail' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns' ),
                'annotations'         => [],
            ],

            'mail-mint/upsert-campaign' => [
                'label'       => __( 'Upsert Campaign', 'mail-mint' ),
                'description' => 'Create or update a draft campaign shell (title, type, recipients, UTM params, archive settings). Provide campaign_id to update. Returns estimated_recipients. Write the actual email content with compose-campaign-email afterwards.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'title' ],
                    'properties' => [
                        'campaign_id' => [ 'type' => 'integer' ],
                        'title'       => [ 'type' => 'string' ],
                        'type'        => [ 'type' => 'string', 'enum' => [ 'regular', 'sequence', 'recurring' ], 'default' => 'regular' ],
                        'status'      => [ 'type' => 'string', 'enum' => [ 'draft' ], 'default' => 'draft', 'description' => 'Only draft campaigns can be created or updated via MCP. Use change-campaign-status to activate or schedule.' ],
                        'recipients'  => [
                            'type'       => 'object',
                            'description' => 'Target audience. Each ID references a list or tag in Mail Mint.',
                            'properties' => [
                                'tags'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                                'lists'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                                'segments'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Pro only. Dynamic segment IDs.' ],
                                'exclude_lists' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'List IDs whose contacts should be excluded from the send.' ],
                                'exclude_tags'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Tag IDs whose contacts should be excluded from the send.' ],
                            ],
                        ],
                        'utm_params' => [
                            'type'        => 'object',
                            'description' => 'UTM tracking parameters appended to all links in the campaign. Set status=1 to enable.',
                            'properties'  => [
                                'status'   => [ 'type' => 'integer', 'enum' => [ 0, 1 ], 'default' => 0, 'description' => '1 to enable UTM tracking, 0 to disable.' ],
                                'source'   => [ 'type' => 'string', 'description' => 'utm_source value, e.g. "mail-mint".' ],
                                'medium'   => [ 'type' => 'string', 'description' => 'utm_medium value, e.g. "email".' ],
                                'campaign' => [ 'type' => 'string', 'description' => 'utm_campaign value, e.g. "welcome".' ],
                                'term'     => [ 'type' => 'string' ],
                                'content'  => [ 'type' => 'string' ],
                            ],
                        ],
                        'show_in_archive'  => [ 'type' => 'boolean', 'default' => false, 'description' => 'Whether to display this campaign in the public email archive.' ],
                        'share_visibility' => [ 'type' => 'string', 'enum' => [ 'public', 'private' ], 'default' => 'private', 'description' => 'Controls the social share toolbar on the public archive page.' ],
                        'idempotency_key' => [ 'type' => 'string', 'description' => 'Optional. Pass a stable key to make retries safe — a repeated call with the same key returns the original result instead of creating a duplicate.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'upsertCampaign' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns' ),
                'annotations'         => [],
            ],

            'mail-mint/change-campaign-status' => [
                'label'       => __( 'Change Campaign Status', 'mail-mint' ),
                'description' => 'Transition a campaign state: schedule (sets status=schedule and queues the send job), unschedule (back to draft), pause (sets status=archived), resume (sets status=active; if the campaign was scheduled, cancels the future job and triggers sending immediately), duplicate, or delete.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'campaign_id', 'action' ],
                    'properties' => [
                        'campaign_id'  => [ 'type' => 'integer' ],
                        'action'       => [ 'type' => 'string', 'enum' => [ 'schedule', 'unschedule', 'pause', 'resume', 'duplicate', 'delete' ], 'description' => 'pause sets status=archived; resume sets status=active.' ],
                        'scheduled_at' => [ 'type' => 'string', 'description' => 'ISO 8601 datetime. Required for schedule action.' ],
                        'confirm'      => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this action (schedule/send/delete are irreversible). Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'changeCampaignStatus' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_campaigns_send' ),
                'annotations'         => [ 'destructive' ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function listCampaigns( array $params ): array|\WP_Error {
        $paging  = MCPHelper::paginationFromInput( $params );
        $search  = sanitize_text_field( $params['search'] ?? '' );
        $sort_by = sanitize_key( $params['sort_by'] ?? 'id' );
        $sort    = strtoupper( $params['sort_type'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $status  = sanitize_text_field( $params['status'] ?? '' );
        $type    = sanitize_text_field( $params['type'] ?? '' );

        $response = ( new CampaignRepository() )->list( [
            'search'   => $search,
            'status'   => $status,
            'type'     => $type,
            'order_by' => $sort_by,
            'order'    => $sort,
            'page'     => $paging['page'],
            'per_page' => $paging['per_page'],
        ] );

        // CampaignRepository::list() returns ['data' => [...], 'total' => N, 'total_pages' => N]
        $rows      = is_array( $response ) ? ( $response['data'] ?? [] ) : [];
        $campaigns = array_map( [ self::class, 'formatCampaign' ], $rows );
        $total     = is_array( $response ) ? (int) ( $response['total'] ?? 0 ) : 0;

        if ( ! empty( $params['include_stats'] ) && ! empty( $campaigns ) ) {
            $stats_by_id = self::statsByCampaignId( array_column( $campaigns, 'id' ) );
            foreach ( $campaigns as &$campaign ) {
                $campaign['stats'] = $stats_by_id[ $campaign['id'] ] ?? self::emptyStats();
            }
            unset( $campaign );
        }

        return [
            'campaigns'   => $campaigns,
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => is_array( $response ) ? (int) ( $response['total_pages'] ?? 1 ) : 1,
        ];
    }

    public static function getCampaign( array $params ): array|\WP_Error {
        $campaign_id = (int) ( $params['campaign_id'] ?? 0 );
        if ( ! $campaign_id ) {
            return MCPHelper::error( 'missing_param', 'campaign_id is required.' );
        }

        $repo     = new CampaignRepository();
        $campaign = $repo->find( $campaign_id );
        if ( ! $campaign ) {
            return MCPHelper::error( 'not_found', 'Campaign not found.' );
        }

        $formatted = self::formatCampaign( $campaign );

        if ( ! empty( $params['include_emails'] ) ) {
            $formatted['emails'] = $repo->getCampaignEmails( $campaign_id );
        }

        if ( ! empty( $params['include_stats'] ) ) {
            $stats_by_id        = self::statsByCampaignId( [ $campaign_id ] );
            $formatted['stats'] = $stats_by_id[ $campaign_id ] ?? self::emptyStats();
        }

        return $formatted;
    }

    public static function getCampaignAnalytics( array $params ): array|\WP_Error {
        $campaign_id = (int) ( $params['campaign_id'] ?? 0 );
        if ( ! $campaign_id ) {
            return MCPHelper::error( 'missing_param', 'campaign_id is required.' );
        }

        $repo     = new CampaignRepository();
        $campaign = $repo->find( $campaign_id );
        if ( ! $campaign ) {
            return MCPHelper::error( 'not_found', 'Campaign not found.' );
        }

        $stats_by_id = self::statsByCampaignId( [ $campaign_id ] );
        $stats       = $stats_by_id[ $campaign_id ] ?? self::emptyStats();
        $delivered   = max( 0, (int) $stats['total_delivered'] );

        $rate = static function ( int $count ) use ( $delivered ): float {
            return $delivered > 0 ? round( ( $count / $delivered ) * 100, 1 ) : 0.0;
        };

        $result = [
            'campaign'     => self::formatCampaign( $campaign ),
            'totals'       => $stats,
            'queue_status' => ( new BroadcastRepository() )->getStatusCounts( $campaign_id ),
            'rates'        => [
                'open_rate_pct'        => $rate( (int) $stats['opens'] ),
                'click_rate_pct'       => $rate( (int) $stats['clicks'] ),
                'unsubscribe_rate_pct' => $rate( (int) $stats['unsubscribes'] ),
                'bounce_rate_pct'      => $stats['total_sent'] > 0
                    ? round( ( (int) $stats['total_bounced'] / (int) $stats['total_sent'] ) * 100, 1 )
                    : 0.0,
            ],
        ];

        if ( ! empty( $params['include_timeline'] ) ) {
            $result['timeline'] = self::engagementTimeline( $campaign_id );
        }

        return $result;
    }

    public static function composeCampaignEmail( array $params ): array|\WP_Error {
        $campaign_id = (int) ( $params['campaign_id'] ?? 0 );
        $email_index = max( 0, (int) ( $params['email_index'] ?? 0 ) );
        $subject     = sanitize_text_field( $params['subject'] ?? '' );

        if ( ! $campaign_id ) {
            return MCPHelper::error( 'missing_param', 'campaign_id is required.' );
        }
        if ( '' === $subject ) {
            return MCPHelper::error( 'missing_param', 'subject is required.' );
        }
        if ( ! is_array( $params['content'] ?? null ) ) {
            return MCPHelper::error( 'missing_param', 'content object is required.' );
        }

        $repo     = new CampaignRepository();
        $campaign = $repo->find( $campaign_id );
        if ( ! $campaign ) {
            return MCPHelper::error( 'not_found', 'Campaign not found.' );
        }
        $campaign_status = is_object( $campaign ) ? ( $campaign->status ?? '' ) : ( $campaign['status'] ?? '' );
        if ( 'draft' !== $campaign_status ) {
            return MCPHelper::error( 'invalid_state', 'Only draft campaigns can be composed via MCP. Unschedule the campaign first.' );
        }

        $idem = MCPHelper::idempotentHit( 'mail-mint/compose-campaign-email', $params );
        if ( null !== $idem ) {
            return $idem;
        }

        $composed = EmailComposer::compose(
            (array) $params['content'],
            is_array( $params['style'] ?? null ) ? $params['style'] : []
        );
        if ( is_wp_error( $composed ) ) {
            return $composed;
        }

        $preview_text = sanitize_text_field( $params['preview_text'] ?? '' );
        $step_data    = [
            'email_index'        => $email_index,
            'email_subject'      => $subject,
            'email_preview_text' => $preview_text,
            'status'             => 'draft',
            // Delay fields default to zero (no inter-email delay for a single broadcast step).
            // The controller computes send_time as microtime(true) for the first step; match that.
            'delay'              => 0,
            'delay_count'        => 0,
            'delay_value'        => '',
            'send_time'          => intval( microtime( true ) ),
        ];
        foreach ( [ 'sender_name', 'sender_email', 'reply_name', 'reply_email' ] as $field ) {
            if ( ! empty( $params[ $field ] ) ) {
                $step_data[ $field ] = 'sender_name' === $field || 'reply_name' === $field
                    ? sanitize_text_field( $params[ $field ] )
                    : sanitize_email( $params[ $field ] );
            }
        }

        // Find or create the campaign email step at this index.
        $existing = CampaignModel::get_email_by_index( $campaign_id, $email_index );
        if ( $existing && ! empty( $existing->id ) ) {
            $email_id = (int) $existing->id;
            $repo->updateCampaignEmail( $email_id, $campaign_id, $step_data );
            $created_step = false;
        } else {
            $email_id = (int) $repo->insertCampaignEmail( $campaign_id, $step_data );
            if ( ! $email_id ) {
                return MCPHelper::error( 'insert_failed', 'Failed to create the campaign email step.' );
            }
            $created_step = true;
        }

        // Write both representations to the email builder table.
        $builder_data = [
            'editor_type' => 'advanced-builder',
            'email_body'  => $composed['html'],
            'json_data'   => maybe_serialize( [
                'subject'  => $subject,
                'subTitle' => $preview_text,
                'content'  => $composed['json'],
            ] ),
        ];
        if ( CampaignEmailBuilderModel::is_new_email_template( $email_id ) ) {
            CampaignEmailBuilderModel::update( $email_id, $builder_data );
        } else {
            $inserted = CampaignEmailBuilderModel::insert( array_merge( [ 'email_id' => $email_id ], $builder_data ) );
            if ( false === $inserted ) {
                return MCPHelper::error( 'insert_failed', 'Failed to save the composed email content.' );
            }
        }

        // Compact structural snapshot so the agent can self-verify the composed
        // email (subject set, has a CTA, sensible length) without pulling the
        // full HTML into context. The rendered email is viewable via the
        // campaign preview and send-test-email.
        $sections = isset( $params['content']['sections'] ) && is_array( $params['content']['sections'] )
            ? $params['content']['sections']
            : [];
        $has_cta  = false;
        foreach ( $sections as $section ) {
            if ( is_array( $section ) && 'button' === ( $section['type'] ?? '' ) ) {
                $has_cta = true;
                break;
            }
        }

        $result = [
            'result'        => $created_step ? 'composed' : 'recomposed',
            'campaign_id'   => $campaign_id,
            'email_id'      => $email_id,
            'email_index'   => $email_index,
            'subject'       => $subject,
            'preview_text'  => $preview_text,
            'section_count' => count( $sections ),
            'has_cta'       => $has_cta,
            'word_count'    => str_word_count( wp_strip_all_tags( (string) $composed['html'] ) ),
            'html_length'   => strlen( $composed['html'] ),
            'next_steps'    => 'Self-check the snapshot (subject, has_cta, word_count), then send-test-email to preview the render and schedule via change-campaign-status. The email stays editable in the drag-and-drop builder.',
        ];
        MCPHelper::idempotentStore( 'mail-mint/compose-campaign-email', $params, $result );
        return $result;
    }

    public static function upsertCampaign( array $params ): array|\WP_Error {
        $campaign_id = (int) ( $params['campaign_id'] ?? 0 );
        $title       = sanitize_text_field( $params['title'] ?? '' );

        if ( ! $title ) {
            return MCPHelper::error( 'missing_param', 'title is required.' );
        }

        // Only fields in CampaignRepository::fillable() are persisted.
        $data = [
            'title'  => $title,
            'type'   => sanitize_text_field( $params['type'] ?? 'regular' ),
            'status' => sanitize_text_field( $params['status'] ?? 'draft' ),
        ];

        $repo = new CampaignRepository();

        if ( $campaign_id ) {
            $repo->update( $campaign_id, $data );
            self::saveCampaignMeta( $repo, $campaign_id, $params );

            return [ 'result' => 'updated', 'campaign_id' => $campaign_id ];
        }

        // created_by must be set so the campaign row is not anonymous.
        $data['created_by'] = get_current_user_id();

        // Idempotency: a retried create with the same key returns the original result.
        $idem = MCPHelper::idempotentHit( 'mail-mint/upsert-campaign', $params );
        if ( null !== $idem ) {
            return $idem;
        }

        // Prevent duplicate creation: if a draft with the same title already exists, return it.
        $existing = $repo->list( [ 'search' => $title, 'status' => 'draft', 'per_page' => 5, 'page' => 1 ] );
        foreach ( $existing['data'] ?? [] as $row ) {
            if ( isset( $row['title'] ) && $row['title'] === $title ) {
                $existing_id = (int) $row['id'];
                self::saveCampaignMeta( $repo, $existing_id, $params );
                $estimated = self::estimateRecipients( $params['recipients'] ?? [] );
                return [
                    'result'               => 'created',
                    'campaign_id'          => $existing_id,
                    'estimated_recipients' => $estimated,
                ];
            }
        }

        $new_id = $repo->create( $data );
        if ( is_wp_error( $new_id ) || ! $new_id ) {
            return MCPHelper::error( 'insert_failed', 'Failed to create campaign.' );
        }

        self::saveCampaignMeta( $repo, (int) $new_id, $params );

        $estimated = self::estimateRecipients( $params['recipients'] ?? [] );

        do_action( 'mailmint_campaign_created', (int) $new_id, $data );

        $result = [
            'result'               => 'created',
            'campaign_id'          => (int) $new_id,
            'estimated_recipients' => $estimated,
        ];
        MCPHelper::idempotentStore( 'mail-mint/upsert-campaign', $params, $result );
        return $result;
    }

    /**
     * Persist campaign meta (recipients, utm_params, show_in_archive, share_visibility).
     *
     * Recipients are normalised from the MCP input format (integer ID arrays) into
     * the five-key serialized PHP structure the system stores and reads:
     *   {lists: [{id,title}], tags: [{id,title}], segments: [{id,title}],
     *    exclude_lists: [{id,title}], exclude_tags: [{id,title}]}
     *
     * This matches what CampaignController::handle_campaign_create() produces and
     * what CampaignRepository::getRecipients() and get_subscribers() expect.
     *
     * @param CampaignRepository $repo        Repository instance.
     * @param int                $campaign_id Campaign ID.
     * @param array              $params      MCP input params.
     */
    private static function saveCampaignMeta( CampaignRepository $repo, int $campaign_id, array $params ): void {
        // Recipients — resolve integer IDs to [{id, title}] objects then PHP-serialize.
        if ( isset( $params['recipients'] ) && is_array( $params['recipients'] ) ) {
            $recipients_input = $params['recipients'];

            // Collect every unique ID that needs a title lookup.
            $all_ids = array_unique( array_filter( array_map( 'intval', array_merge(
                (array) ( $recipients_input['lists'] ?? [] ),
                (array) ( $recipients_input['tags'] ?? [] ),
                (array) ( $recipients_input['segments'] ?? [] ),
                (array) ( $recipients_input['exclude_lists'] ?? [] ),
                (array) ( $recipients_input['exclude_tags'] ?? [] )
            ) ) ) );

            $titles_by_id = self::fetchGroupTitles( $all_ids );

            $resolve = static function ( array $ids ) use ( $titles_by_id ): array {
                $out = [];
                foreach ( $ids as $id ) {
                    $id = (int) $id;
                    if ( $id > 0 ) {
                        $out[] = [
                            'id'    => $id,
                            'title' => $titles_by_id[ $id ] ?? '',
                        ];
                    }
                }
                return $out;
            };

            $recipients = [
                'lists'         => $resolve( (array) ( $recipients_input['lists'] ?? [] ) ),
                'tags'          => $resolve( (array) ( $recipients_input['tags'] ?? [] ) ),
                'segments'      => $resolve( (array) ( $recipients_input['segments'] ?? [] ) ),
                'exclude_lists' => $resolve( (array) ( $recipients_input['exclude_lists'] ?? [] ) ),
                'exclude_tags'  => $resolve( (array) ( $recipients_input['exclude_tags'] ?? [] ) ),
            ];
            $repo->insertOrUpdateMeta( $campaign_id, 'recipients', maybe_serialize( $recipients ) );
        }

        // UTM tracking parameters — always persist so the meta row exists.
        // When the caller passes utm_params, merge with defaults; otherwise store the default structure.
        $utm_input = isset( $params['utm_params'] ) && is_array( $params['utm_params'] ) ? $params['utm_params'] : [];
        $utm       = [
            'status'   => intval( $utm_input['status'] ?? 0 ),
            'source'   => sanitize_text_field( $utm_input['source'] ?? '' ),
            'medium'   => sanitize_text_field( $utm_input['medium'] ?? '' ),
            'campaign' => sanitize_text_field( $utm_input['campaign'] ?? '' ),
            'term'     => sanitize_text_field( $utm_input['term'] ?? '' ),
            'content'  => sanitize_text_field( $utm_input['content'] ?? '' ),
        ];
        $repo->insertOrUpdateMeta( $campaign_id, 'utm_params', wp_json_encode( $utm ) );

        // Public archive visibility — always persist; defaults to hidden ('0').
        $enabled = isset( $params['show_in_archive'] ) && ! empty( $params['show_in_archive'] ) ? '1' : '0';
        $repo->insertOrUpdateMeta( $campaign_id, 'show_in_archive', $enabled );
        // Generate a stable public hash once, on first enable.
        if ( '1' === $enabled && ! $repo->getMeta( $campaign_id, 'archive_hash' ) ) {
            $repo->insertOrUpdateMeta( $campaign_id, 'archive_hash', wp_generate_password( 24, false ) );
        }

        // Social share bar visibility — always persist so the meta row exists.
        // Defaults to 'private' when not supplied, matching the controller behaviour.
        $visibility = 'public' === ( $params['share_visibility'] ?? '' ) ? 'public' : 'private';
        $repo->insertOrUpdateMeta( $campaign_id, 'share_visibility', $visibility );
    }

    /**
     * Count the distinct subscribed contacts targeted by a campaign's stored recipients meta.
     *
     * Reads the already-normalised [{id,title}] recipients from the meta row so the
     * count is consistent with what the sending pipeline will actually process.
     *
     * @param CampaignRepository $repo        Repository instance.
     * @param int                $campaign_id Campaign ID.
     *
     * @return int Contact count (0 if no recipients or DB error).
     */
    private static function countStoredRecipients( CampaignRepository $repo, int $campaign_id ): int {
        global $wpdb;

        $raw        = $repo->getMeta( $campaign_id, 'recipients' );
        $recipients = $raw ? maybe_unserialize( $raw ) : [];

        if ( ! is_array( $recipients ) ) {
            return 0;
        }

        $group_ids = array_merge(
            array_column( (array) ( $recipients['lists'] ?? [] ), 'id' ),
            array_column( (array) ( $recipients['tags'] ?? [] ), 'id' )
        );
        $group_ids = array_values( array_filter( array_map( 'intval', $group_ids ) ) );

        if ( empty( $group_ids ) ) {
            return 0;
        }

        $pivot        = $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactGroupPivotSchema::$table_name;
        $placeholders = implode( ', ', array_fill( 0, count( $group_ids ), '%d' ) );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT contact_id) FROM {$pivot} WHERE group_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                ...$group_ids
            )
        );
    }

    /**
     * Fetch id→title pairs from mint_contact_groups for the given IDs.
     *
     * Returns an empty array if the ID list is empty, avoiding a malformed query.
     *
     * @param int[] $ids Group IDs (lists, tags, segments, or exclusion groups).
     *
     * @return array<int,string> Map of id => title.
     */
    private static function fetchGroupTitles( array $ids ): array {
        if ( empty( $ids ) ) {
            return [];
        }

        global $wpdb;
        $table        = $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactGroupSchema::$table_name;
        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title FROM {$table} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                ...$ids
            ),
            ARRAY_A
        );

        $map = [];
        foreach ( (array) $rows as $row ) {
            $map[ (int) $row['id'] ] = (string) $row['title'];
        }
        return $map;
    }

    public static function changeCampaignStatus( array $params ): array|\WP_Error {
        $campaign_id = (int) ( $params['campaign_id'] ?? 0 );
        $action      = $params['action'] ?? '';

        if ( ! $campaign_id ) {
            return MCPHelper::error( 'missing_param', 'campaign_id is required.' );
        }

        $repo     = new CampaignRepository();
        $campaign = $repo->find( $campaign_id );
        if ( ! $campaign ) {
            return MCPHelper::error( 'not_found', 'Campaign not found.' );
        }

        switch ( $action ) {
            case 'schedule':
                $scheduled_at = sanitize_text_field( $params['scheduled_at'] ?? '' );
                if ( ! $scheduled_at ) {
                    return MCPHelper::error( 'missing_param', 'scheduled_at is required for schedule action.' );
                }

                // Update the campaign row to schedule status.
                $repo->update( $campaign_id, [ 'status' => 'schedule', 'scheduled_at' => $scheduled_at ] );

                // Transition all email steps to 'scheduling' and stamp scheduled_at so the
                // sending pipeline can claim them. This mirrors what CampaignController::
                // apply_email_scheduling_status() does for the update path.
                $email_steps = $repo->getCampaignEmails( $campaign_id );
                foreach ( $email_steps as $step ) {
                    $repo->updateCampaignEmail( (int) $step['id'], $campaign_id, [
                        'status'       => 'scheduling',
                        'scheduled_at' => current_time( 'mysql' ),
                    ] );
                }

                // Store total_recipients so the UI shows a number instead of PHP false.
                // The controller stores this when the frontend submits the form; replicate it here.
                $total_recipients = self::countStoredRecipients( $repo, $campaign_id );
                $repo->insertOrUpdateMeta( $campaign_id, 'total_recipients', $total_recipients );

                // Enqueue the Action Scheduler job. Mirrors CampaignController::build_save_response().
                // Cancel any existing AS job first so rescheduling (including "run now") always
                // creates a fresh job. Without this, as_has_scheduled_action() inside
                // scheduleCampaignAction() finds the old pending job and returns early.
                if ( ! empty( $email_steps ) ) {
                    $first_email = array_intersect_key( (array) $email_steps[0], array_flip( [
                        'id', 'email_subject', 'email_preview_text', 'sender_email', 'sender_name',
                        'reply_email', 'reply_name', 'delay_count', 'delay_value',
                    ] ) );
                    $repo->unscheduleCampaignActions( $campaign_id );
                    $repo->scheduleCampaignAction( $campaign_id, $first_email, 'schedule', $scheduled_at );
                }
                break;

            case 'unschedule':
                $repo->update( $campaign_id, [ 'status' => 'draft' ] );
                break;

            case 'pause':
                // 'paused' is not a valid DB enum value; archive the campaign to halt sending.
                $repo->update( $campaign_id, [ 'status' => 'archived' ] );
                break;

            case 'resume':
                // Only draft/archived/schedule campaigns can be moved back to active.
                $current_status = $campaign['status'] ?? '';
                if ( ! in_array( $current_status, [ 'draft', 'archived', 'schedule' ], true ) ) {
                    return MCPHelper::error( 'invalid_state', "Cannot resume a campaign with status '{$current_status}'." );
                }

                if ( 'schedule' === $current_status ) {
                    // Campaign was waiting for a future scheduled time. Cancel that job and
                    // fire immediately: re-stamp email steps as 'scheduling', cancel existing
                    // AS job, then enqueue an async (immediate) AS action. The AS handler's
                    // activate_scheduled_campaign() will flip the campaign status to 'active'.
                    $email_steps = $repo->getCampaignEmails( $campaign_id );
                    foreach ( $email_steps as $step ) {
                        $repo->updateCampaignEmail( (int) $step['id'], $campaign_id, [
                            'status'       => 'scheduling',
                            'scheduled_at' => current_time( 'mysql' ),
                        ] );
                    }
                    if ( ! empty( $email_steps ) ) {
                        $first_email = array_intersect_key( (array) $email_steps[0], array_flip( [
                            'id', 'email_subject', 'email_preview_text', 'sender_email', 'sender_name',
                            'reply_email', 'reply_name', 'delay_count', 'delay_value',
                        ] ) );
                        $repo->unscheduleCampaignActions( $campaign_id );
                        // No $schedule arg → scheduleCampaignAction calls as_enqueue_async_action (immediate).
                        $repo->scheduleCampaignAction( $campaign_id, $first_email, 'schedule' );
                    } else {
                        $repo->update( $campaign_id, [ 'status' => 'active' ] );
                    }
                } else {
                    $repo->update( $campaign_id, [ 'status' => 'active' ] );
                }
                break;

            case 'duplicate':
                $dup_data = $repo->getCampaignToDuplicate( $campaign_id );
                if ( empty( $dup_data ) ) {
                    return MCPHelper::error( 'not_found', 'Could not retrieve campaign for duplication.' );
                }
                $new_id = $repo->create( [
                    'title'  => ( $dup_data['title'] ?? 'Copy' ) . ' (Copy)',
                    'type'   => $dup_data['type'] ?? 'regular',
                    'status' => 'draft',
                ] );
                if ( is_wp_error( $new_id ) || ! $new_id ) {
                    return MCPHelper::error( 'insert_failed', 'Failed to duplicate campaign.' );
                }
                $new_id = (int) $new_id;
                // Copy recipients meta.
                $recipients_meta = $repo->getMeta( $campaign_id, 'recipients' );
                if ( $recipients_meta ) {
                    $repo->insertOrUpdateMeta( $new_id, 'recipients', $recipients_meta );
                }
                // Copy email steps (subjects, bodies, delays) so the duplicate is complete.
                $email_steps = $dup_data['emails'] ?? [];
                if ( ! empty( $email_steps ) ) {
                    global $wpdb;
                    $builder_table = $wpdb->prefix . 'mint_campaign_email_builder';
                    foreach ( $email_steps as $step ) {
                        $step_data = array_intersect_key( (array) $step, array_flip( [
                            'delay', 'delay_count', 'delay_value', 'send_time',
                            'sender_email', 'sender_name', 'reply_email', 'reply_name',
                            'email_index', 'email_subject', 'email_preview_text',
                            'template_id', 'status',
                        ] ) );
                        $new_email_id = $repo->insertCampaignEmail( $new_id, $step_data );
                        if ( $new_email_id && ! empty( $step['email_body'] ) ) {
                            $wpdb->insert(
                                $builder_table,
                                [
                                    'email_id'   => $new_email_id,
                                    'json_data'  => $step['json_data'] ?? '',
                                    'email_body' => $step['email_body'],
                                    'created_at' => current_time( 'mysql' ),
                                    'updated_at' => current_time( 'mysql' ),
                                ],
                                [ '%d', '%s', '%s', '%s', '%s' ]
                            );
                        }
                    }
                }
                return [ 'result' => 'duplicated', 'campaign_id' => $new_id, 'emails_copied' => count( $email_steps ) ];

            case 'delete':
                if ( ! PermissionManager::current_user_can( 'mint_manage_campaigns_delete' )() ) {
                    return MCPHelper::error( 'permission_denied', 'You do not have permission to delete campaigns.' );
                }
                $repo->destroy( $campaign_id );
                return [ 'result' => 'deleted', 'campaign_id' => $campaign_id ];

            default:
                return MCPHelper::error( 'invalid_action', 'Unknown action: ' . $action );
        }

        return [ 'result' => $action, 'campaign_id' => $campaign_id ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Fetch send/open/click stats for a set of campaigns, keyed by campaign ID.
     *
     * Wraps CampaignRepository::withStatsQuery() and renames its rate-named
     * count columns (open_rate is a COUNT in that query) to honest names.
     */
    private static function statsByCampaignId( array $campaign_ids ): array {
        $rows = ( new CampaignRepository() )->withStatsQuery( array_map( 'intval', $campaign_ids ) );
        $out  = [];
        foreach ( $rows as $row ) {
            $out[ (int) $row['id'] ] = [
                'total_sent'      => (int) $row['total_sent'],
                'total_delivered' => (int) $row['total_delivered'],
                'total_bounced'   => (int) $row['total_bounced'],
                'opens'           => (int) $row['open_rate'],
                'clicks'          => (int) $row['click_rate'],
                'unsubscribes'    => (int) $row['unsubscribe_count'],
            ];
        }
        return $out;
    }

    private static function emptyStats(): array {
        return [
            'total_sent'      => 0,
            'total_delivered' => 0,
            'total_bounced'   => 0,
            'opens'           => 0,
            'clicks'          => 0,
            'unsubscribes'    => 0,
        ];
    }

    /**
     * Per-day first-open and first-click counts for a campaign, derived from
     * broadcast email meta row timestamps.
     */
    private static function engagementTimeline( int $campaign_id ): array {
        global $wpdb;
        $broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
        $meta_table      = $wpdb->prefix . 'mint_broadcast_email_meta';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(m.created_at) AS day, m.meta_key, COUNT(*) AS cnt
                FROM {$meta_table} AS m
                INNER JOIN {$broadcast_table} AS b ON b.id = m.mint_email_id
                WHERE b.campaign_id = %d
                  AND m.meta_key IN ('is_open', 'is_click')
                  AND m.meta_value = '1'
                GROUP BY DATE(m.created_at), m.meta_key
                ORDER BY day ASC",
                $campaign_id
            ),
            ARRAY_A
        );

        $timeline = [];
        foreach ( (array) $rows as $row ) {
            $day = (string) $row['day'];
            if ( ! isset( $timeline[ $day ] ) ) {
                $timeline[ $day ] = [ 'date' => $day, 'opens' => 0, 'clicks' => 0 ];
            }
            if ( 'is_open' === $row['meta_key'] ) {
                $timeline[ $day ]['opens'] = (int) $row['cnt'];
            } else {
                $timeline[ $day ]['clicks'] = (int) $row['cnt'];
            }
        }

        return array_values( $timeline );
    }

    private static function formatCampaign( $campaign ): array {
        if ( is_object( $campaign ) ) {
            $campaign = (array) $campaign;
        }
        return [
            'id'          => (int) ( $campaign['id'] ?? 0 ),
            'title'       => $campaign['title'] ?? '',
            'type'        => $campaign['type'] ?? '',
            'status'      => $campaign['status'] ?? '',
            'scheduled_at'=> $campaign['scheduled_at'] ?? null,
            'created_at'  => $campaign['created_at'] ?? '',
            'updated_at'  => $campaign['updated_at'] ?? '',
        ];
    }

    private static function estimateRecipients( array $recipients ): int {
        global $wpdb;
        if ( empty( $recipients ) ) {
            return 0;
        }

        // Schema constant resolves to mint_contact_group_relationship — the
        // literal 'mint_contact_group_pivot' used previously does not exist.
        $pivot = $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactGroupPivotSchema::$table_name;
        $ids   = array_unique( array_merge(
            array_map( 'intval', (array) ( $recipients['tags'] ?? [] ) ),
            array_map( 'intval', (array) ( $recipients['lists'] ?? [] ) )
        ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT contact_id) FROM {$pivot} WHERE group_id IN ({$placeholders})",
                ...$ids
            )
        );
    }
}
