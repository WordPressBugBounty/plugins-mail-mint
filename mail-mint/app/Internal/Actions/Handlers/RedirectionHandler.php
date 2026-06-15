<?php

/**
 * This class is responsible for redirection 
 * based on various routes provided through data input.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2024-08-07 09:30:00
 * @modify date 2024-08-07 11:03:17
 * @package Mint\App\Internal\Actions\Handlers
 */

namespace Mint\App\Internal\Actions\Handlers;

use MailMint\App\Helper;
use MailMintPro\Internal\LeadMagnet\LeadMagnetDownloader;
use Mint\MRM\DataBase\Models\EmailModel;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\Internal\Optin\UnsubscribeConfirmation;
use Mint\MRM\Utilites\Helper\Campaign;
use MRM\Common\MrmCommon;
use Mint\MRM\API\Actions\ComplianceAction;

/**
 * Class RedirectionHandler
 *
 * This class handles redirection based on various routes provided through data input.
 * It processes unsubscribe confirmations, preference page redirection, lead magnet downloads, 
 * and link triggers. It also manages tracking of email link clicks and handling cookies 
 * for WooCommerce links.
 *
 * @package Mint\App\Internal\Actions\Handlers
 * @since 1.14.0
 */
class RedirectionHandler {

    /**
     * Redirects based on the route provided in the data.
     *
     * @param array $data The data array containing information about the route and hash.
     * @param array $server The server array containing information like query string.
     * 
     * @return void
     * @since 1.14.0
     */
    public function redirect( $data, $server ){
        nocache_headers();

        $target_url = ! empty( $data['target'] ) ? $data['target'] : '#';

        if ( !empty( $server['QUERY_STRING'] ) ) {
			$target_url = $this->get_target_url( $server['QUERY_STRING'] );
		}

        $hash     = $data['hash'] ?? '';
		$route    = $data['route'] ?? '';
		$email_id = EmailModel::get_broadcast_email_by_hash( $hash );

        switch ($route) {
            case 'unsubscribe':
                $this->handle_unsubscribe( $hash );
                break;
            case 'mrm-preference':
                $this->handle_preference( $hash, $email_id );
                break;
            case 'lead-magnet':
                $this->handle_lead_magnet( $data );
                break;
            case 'link-trigger':
                $this->handle_link_triggers( $data, $hash, $email_id );
            default:
                if ( ! $this->is_safe_redirect_url( $target_url ) ) {
                    exit( wp_redirect( home_url() ) );
                }
                // Verify HMAC signature when present (old inbox links without mts are allowed through).
                $signature = isset( $data['mts'] ) ? $data['mts'] : '';
                if ( ! \MailMint\App\Helper::verify_tracking_signature( $hash, $target_url, $signature ) ) {
                    exit( wp_redirect( $target_url, 302 ) );
                }
                // tmode is baked into the URL at send time — pass it through so old emails
                // are not retroactively affected by later compliance setting changes.
                $tmode = isset( $data['tmode'] ) && in_array( $data['tmode'], array( 'yes', 'anonymous' ), true )
                    ? $data['tmode']
                    : null;
                $this->handle_default( $hash, $target_url, $email_id, $tmode );
                break;
        }
    }

    /**
     * Validates that a redirect target URL is safe (http/https only, has a valid host).
     *
     * Prevents open-redirect abuse where an attacker supplies a javascript:,
     * data:, or other dangerous URI as the target parameter.
     *
     * @param string $url The URL to validate.
     * @return bool True when the URL is safe to redirect to.
     * @since 1.14.0
     */
    private function is_safe_redirect_url( $url ) {
        if ( empty( $url ) || '#' === $url ) {
            return false;
        }
        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['host'] ) ) {
            return false;
        }
        $allowed_schemes = array( 'http', 'https' );
        return isset( $parsed['scheme'] ) && in_array( strtolower( $parsed['scheme'] ), $allowed_schemes, true );
    }

    /**
     * Redirects based on the route provided in the data.
     *
     * @param array $data The data array containing information about the route and hash.
     * @param array $server The server array containing information like query string.
     * 
     * @return void
     * @since 1.14.0
     */
    private function handle_unsubscribe( $hash ){
        $unsubscribe = new UnsubscribeConfirmation();
        $unsubscribe->process_unsubscribe( $hash );
    }

    /**
     * Handles redirection to the preference page based on the given hash and email ID.
     *
     * @param string $hash The hash used to identify the email or user.
     * @param int $email_id The ID of the email associated with the preference update.
     * 
     * @return void
     * @since 1.14.0
     */
    private function handle_preference( $hash, $email_id ){
        $preference_url = Helper::get_preference_url( $hash );
        EmailModel::insert_or_update_email_meta( 'is_preference', 1, $email_id );
        exit( wp_redirect( $preference_url ) );
    }

    /**
     * Handles the lead magnet download process.
     *
     * @param array $data The data array containing information for the lead magnet download.
     * 
     * @return void
     * @since 1.14.0
     */
    private function handle_lead_magnet( $data ){
        if ( !MrmCommon::is_mailmint_pro_active() ) {
            exit( wp_redirect( site_url() ) );
        } else {
            new LeadMagnetDownloader( $data );
        }
    }

    /**
     * Handles the default redirection process, including tracking clicks and setting cookies.
     *
     * @param string $hash The hash used to identify the email or user.
     * @param string $target_url The URL to which the user should be redirected.
     * @param int $email_id The ID of the email associated with the click.
     * 
     * @return void
     * @since 1.14.0
     */
    private function handle_default($hash, $target_url, $email_id, $tmode = null){
        // Use send-time mode baked in the URL; old links without tmode were sent
        // before this feature existed (no compliance setting in place) — treat as
        // 'yes' (full tracking) regardless of what the current global setting is.
        $click_tracking_mode = ( null !== $tmode ) ? $tmode : 'yes';

        // Rate-limit: allow at most one tracked click per email per URL per minute
        // to guard against bot crawlers inflating click counts.
        $dedup_key = 'mint_click_' . (int) $email_id . '_' . substr( md5( $target_url ), 0, 8 );
        $already_counted = get_transient( $dedup_key );
        if ( ! $already_counted ) {
            set_transient( $dedup_key, 1, MINUTE_IN_SECONDS );
        }

        // Resolve campaign_id once — used for both anonymous tracking and UTM appending.
        $campaign_id = (int) EmailModel::get_campaign_id_by_email_id( $email_id );

        if ( 'anonymous' === $click_tracking_mode ) {
            /*
             * In anonymous mode we increment an aggregate counter on mint_campaigns_meta
             * (keyed by campaign_id) instead of writing to mint_broadcast_email_meta.
             * mint_broadcast_email_meta.mint_email_id is a FK to mint_broadcast_emails
             * which carries contact_id — any row there lets a JOIN identify the contact.
             * mint_campaigns_meta only references campaign_id, so no contact is traceable.
             */
            if ( ! $already_counted && $campaign_id ) {
                $current = (int) CampaignModel::get_campaign_meta_value( $campaign_id, '_anon_click_count' );
                CampaignModel::insert_or_update_campaign_meta( $campaign_id, '_anon_click_count', $current + 1 );
            }

            /**
             * Fires when a tracked link is clicked in anonymous mode.
             *
             * @param int    $email_id   The ID of the email.
             * @param string $target_url The destination URL.
             * @since 1.14.0
             */
            do_action( 'mailmint_after_email_click_anonymous', $email_id, $target_url );
        } elseif ( 'yes' === $click_tracking_mode ) {
            // WooCommerce active check.
            $is_wc_active = MrmCommon::is_wc_active();

            if ($is_wc_active) {
                // Set cookie to track product buying from the link.
                $cookie = MrmCommon::get_sanitized_get_post();
                $cookie = !empty($cookie['cookie']) ? $cookie['cookie'] : array();
                if (isset($cookie['mail_mint_link_trigger'])) {
                    setcookie('mail_mint_link_trigger', '', time() - 3600);
                    unset($cookie['mail_mint_link_trigger']);
                }
                MrmCommon::set_cookie('mail_mint_link_trigger', $hash, time() + HOUR_IN_SECONDS);
            }

            $compliance          = get_option('_mint_compliance', array());
            $should_anonymize_ip = ! empty($compliance['anonymize_ip']) && 'yes' === $compliance['anonymize_ip'];

            if ( ! $already_counted ) {
                Campaign::track_email_link_click_performance($email_id, $target_url);
                EmailModel::bulk_insert_or_update_email_meta(
                    array(
                        'is_click'        => 1,
                        'user_click_agent' => Helper::get_user_agent(),
                        'user_click_ip'    => $should_anonymize_ip ? Helper::get_anonymized_ip() : Helper::get_user_ip(),
                    ),
                    $email_id
                );
            }

            do_action('mailmint_after_email_click', $email_id, $target_url);
        }

        // Append UTM params from campaign config — evaluated at click-time so already-inboxed
        // emails are never affected (their URLs are unchanged; only the final destination changes).
        if ( $campaign_id ) {
            $utm_params = Campaign::get_utm_params( $campaign_id );
            if ( ! empty( $utm_params ) ) {
                $target_url = add_query_arg( $utm_params, $target_url );
            }
        }

        wp_redirect($target_url, 307);
        exit;
    }

    /**
     * Handles link triggers based on the provided data and hash.
     *
     * @param array $data The data array containing information for the link trigger.
     * @param string $hash The hash used to identify the email or user.
     * @param int $email_id The email_id used to identify sending email ID.
     * 
     * @return void
     * @since 1.14.0
     */
    private function handle_link_triggers( $data, $hash, $email_id ){
        if( ! MrmCommon::is_mailmint_pro_active() ) {
            exit( wp_redirect( site_url() ) );
        } else {
            $contact_hash = EmailModel::get_contact_id_by_hash( $hash );
            $contact_id   = isset( $contact_hash['contact_id'] ) ? $contact_hash['contact_id'] : false;
            MM()->link_trigger_handler->handle_click( $data, $contact_id, $email_id );
        }
    }

    /**
     * Generates the target URL using parameters from the query string.
     *
     * @param string $query_string The query string from the server request.
     * 
     * @return string The generated target URL.
     * @since 1.2.7
     */
    public function get_target_url( $query_string ){
        if ( empty( $query_string ) ) {
            return '';
        }

        $query_string = str_replace( '&amp;', '&', $query_string );
        $params       = explode( '&', $query_string );
        $target_index = null;

        foreach ( $params as $index => $param ) {
            if ( strpos( $param, 'target=' ) === 0 ) {
                $target_index = $index;
                break;
            }
        }

        if ( null === $target_index ) {
            return '';
        }

        $target = substr( $params[ $target_index ], 7 );
        $target = rawurldecode( $target );

        // These are Mail Mint's own tracking params — strip them so they are never
        // forwarded to the destination URL as query params.
        $mint_params  = array( 'hash=', 'mts=', 'tmode=', 'action=', 'mint=' );
        $extra_params = array();
        $count        = count( $params );
        for ( $i = $target_index + 1; $i < $count; $i++ ) {
            if ( empty( $params[ $i ] ) ) {
                continue;
            }
            $is_mint_param = false;
            foreach ( $mint_params as $mint_param ) {
                if ( strpos( $params[ $i ], $mint_param ) === 0 ) {
                    $is_mint_param = true;
                    break;
                }
            }
            if ( $is_mint_param ) {
                continue;
            }
            $extra_params[] = $params[ $i ];
        }

        if ( ! empty( $extra_params ) ) {
            $glue      = ( false === strpos( $target, '?' ) ) ? '?' : '&';
            $last_char = substr( $target, -1 );
            if ( '?' === $last_char || '&' === $last_char ) {
                $glue = '';
            }
            $target .= $glue . implode( '&', $extra_params );
        }

        return $target;
    }

    /**
     * Filters out the hash from the query string parameters.
     *
     * @param string $query_string The query string from the server request.
     * 
     * @return string[] The filtered parameters.
     * @since 1.2.7
     */
    public function filter_params_by_hash( $query_string ){
        if (!$query_string) {
            return array();
        }
        $query_string = str_replace( '&amp;', '&', $query_string );
        $params = explode('&', $query_string);
        $params = array_filter(
            $params,
            function ($param) {
                return strpos($param, 'hash=') !== 0;
            }
        );
        return $params;
    }
}
