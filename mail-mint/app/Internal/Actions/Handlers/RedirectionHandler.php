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
use Mint\MRM\Internal\Optin\UnsubscribeConfirmation;
use Mint\MRM\Utilites\Helper\Campaign;
use MRM\Common\MrmCommon;

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
                $this->handle_default( $hash, $target_url, $email_id );
                break;
        }
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
    private function handle_default($hash, $target_url, $email_id){
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

        Campaign::track_email_link_click_performance($email_id, $target_url);
        EmailModel::insert_or_update_email_meta('is_click', 1, $email_id);
        EmailModel::insert_or_update_email_meta('user_click_agent', Helper::get_user_agent(), $email_id);
        $is_ip_store = get_option('_mint_compliance');
        $is_ip_store = isset($is_ip_store['anonymize_ip']) ? $is_ip_store['anonymize_ip'] : 'no';
        if ('no' === $is_ip_store) {
            EmailModel::insert_or_update_email_meta('user_click_ip', Helper::get_user_ip(), $email_id);
        }

        do_action('mailmint_after_email_click', $email_id);
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
        $params = $this->filter_params_by_hash( $query_string );
        $url    = '';
        $count  = count($params);
        if (strpos($query_string, 'target=') !== false) {
            for ($i = 1; $i < $count; $i++) {
                if ($i > 1) {
                    $url .= '&';
                }
                $url .= $params[$i];
            }
        }
        return substr($url, 7);
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
        $params = explode('&amp;', $query_string);
        $params = array_filter(
            $params,
            function ($param) {
                return strpos($param, 'hash=') !== 0;
            }
        );
        return $params;
    }
}
