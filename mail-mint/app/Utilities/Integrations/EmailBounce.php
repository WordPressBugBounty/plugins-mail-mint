<?php

/**
 * Class EmailBounce
 * 
 * This class Handles email bounce events from various email service providers.
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-10-11 11:03:17
 * @modify date 2024-10-11 11:03:17
 * @package Mint\MRM\Utilities\Integrations
 */

namespace Mint\MRM\Utilities\Integrations;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\Utilities\Arr;
use MRM\Common\MrmCommon;

/**
 * Class EmailBounce
 *
 * Handles email bounce events from various email service providers.
 * This class processes the bounce data and updates the contact status accordingly.
 *
 * @package Mint\MRM\Utilities\Integrations
 * @since 1.15.0
 */
class EmailBounce{

    /**
     * Handles the bounce event from the specified email provider.
     *
     * @param string $provider The email service provider (e.g., 'mailgun', 'sendgrid').
     * @param \WP_REST_Request $request The request object containing the bounce data.
     * 
     * @return mixed The result of the bounce handling, or null if the provider method does not exist.
     * @since 1.15.0
     */
    public function handle( $provider, $request ){
        $method = 'handle_' . strtolower($provider);

        if (method_exists($this, $method)) {
            return $this->{$method}($request);
        }

        return null;
    }

    /**
     * Handles the bounce event from Mailgun.
     *
     * @param \WP_REST_Request $request The request object containing the bounce data.
     *
     * @return bool|mixed False if the event data is invalid or the event is not handled, otherwise the result of recording the unsubscribe.
     * @since 1.15.0
     */
    private function handle_mailgun( $request ){
        $params     = MrmCommon::get_api_params_values($request);
        $event_data = isset($params['event-data']) ? $params['event-data'] : array();
        if ( !$event_data ) {
            return false;
        }

        $event        = Arr::get($event_data, 'event');
        $catch_events = array('failed', 'unsubscribed', 'complained');

        if (!in_array($event, $catch_events)) {
            return false;
        }

        $recipient_email = Arr::get($event_data, 'recipient');
        if ( !$recipient_email ) {
            return false;
        }

        $new_status = 'bounced';
        if ( 'complained' === $event ) {
            $new_status = 'complained';
        } else if ( 'unsubscribed' === $event ) {
            $new_status = 'unsubscribed';
        }

        $unsubscribe_data = array(
            'email'    => $recipient_email,
            'reason'   => $new_status . __(' was set by mailgun webhook api with event name: ', 'mrm') . $event . __(' at ', 'mrm') . current_time('mysql'),
            'status'   => $new_status,
            'provider' => 'Mailgun'
        );

        return ContactModel::record_unsubscribe($unsubscribe_data);
    }

    /**
     * Handles SendGrid events and records unsubscribe data if necessary.
     *
     * This function processes an array of SendGrid events. If an event is of type 'dropped', 'bounce', 'spamreport', or 'unsubscribe',
     * it records the unsubscribe data including the email, reason, and status.
     *
     * @param \WP_REST_Request $request The request object containing the bounce data.
     * @return mixed Returns the result of ContactModel::record_unsubscribe if unsubscribe data exists, otherwise returns false.
     * @since 1.15.0
     */
    private function handle_sendgrid($request){
        $params = MrmCommon::get_api_params_values($request);
        $events = isset($params['events']) ? $params['events'] : array();

        if (!$events || !count($events)) {
            return false;
        }

        $unsubscribe_data = array();
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $event_name = Arr::get($event, 'event');
            if (in_array($event_name, ['dropped', 'bounce', 'spamreport', 'unsubscribe'])) {
                $new_status = 'complained';
                if ( 'bounce' === $event_name ) {
                    $new_status = 'bounced';
                } else if ( 'unsubscribe' === $event_name ) {
                    $new_status = 'unsubscribed';
                }
                $unsubscribe_data = [
                    'email'  => Arr::get($event, 'email'),
                    'reason' => $new_status . __(' status was set from SendGrid Webhook API. Reason: ', 'mrm') . Arr::get($event, 'reason') . __('. Recorded at: ', 'mrm') . current_time('mysql'),
                    'status' => $new_status
                ];
            }
        }

        if ($unsubscribe_data) {
            return ContactModel::record_unsubscribe($unsubscribe_data);
        }

        return false;
    }

    /**
     * Handle Amazon SES notifications.
     *
     * This function processes incoming Amazon SES notifications, including bounce and complaint notifications.
     * It extracts the relevant information from the notification and records unsubscribe actions for the affected email addresses.
     *
     * @param WP_REST_Request $request The incoming request object.
     * @return bool True if the notification was handled successfully, false otherwise.
     * 
     * @since 1.15.0
     */
    private function handle_ses( $request ){
        $post_data = file_get_contents('php://input');

        if ( is_wp_error($post_data ) ) {
            return false;
        }

        $post_data         = json_decode($post_data, true);
        $notification_type = Arr::get( $post_data, 'notificationType' );

        if ( !$notification_type ) {
            $notification_type = Arr::get( $post_data, 'Type' );
        }

        if ('SubscriptionConfirmation' === $notification_type ) {
            wp_remote_get( $post_data['SubscribeURL'] );
            wp_send_json([
                'status'  => 200,
                'message' => __('success', 'mrm')
            ], 200);
        }

        if ( empty( $post_data['notificationType'] ) && !empty( $post_data['Message'] ) ) {
            $post_data         = json_decode( $post_data['Message'], true );
            $notification_type = Arr::get( $post_data, 'notificationType', $notification_type );
        }

        if ( 'Bounce' === $notification_type ) {
            $bounce = Arr::get( $post_data, 'bounce', [] );

            $bounce_type = Arr::get($bounce, 'bounceType');
            if ( 'Undetermined' === $bounce_type || 'Permanent' === $bounce_type ) {
                foreach ( $bounce['bouncedRecipients'] as $bounced_recipient ) {
                    $unsubscribe_data = array(
                        'email'  => $this->extract_valid_email($bounced_recipient['emailAddress']),
                        'reason' => Arr::get($bounced_recipient, 'diagnosticCode'),
                        'status' => 'bounced',
                    );

                    ContactModel::record_unsubscribe($unsubscribe_data);
                }
            } else {
                foreach ($bounce['bouncedRecipients'] as $bounced_recipient) {
                    $unsubscribe_data = array(
                        'email'  => $this->extract_valid_email($bounced_recipient['emailAddress']),
                        'reason' => Arr::get($bounced_recipient, 'diagnosticCode'),
                        'status' => 'soft bounced'
                    );

                    ContactModel::record_unsubscribe($unsubscribe_data);
                }
            }
        } else if ( 'Complaint' === $notification_type ) {
            $complaint = Arr::get( $post_data, 'complaint', [] );

            foreach ($complaint['complainedRecipients'] as $complained_recipient) {
                $reason = Arr::get($complained_recipient, 'diagnosticCode');
                if (!$reason) {
                    $reason = 'SES complained received as: ' . Arr::get($complaint, 'complaintFeedbackType');
                }
                $unsubscribe_data = array(
                    'email'  => $this->extract_valid_email(Arr::get($complained_recipient, 'emailAddress')),
                    'reason' => $reason,
                    'status' => 'complained',
                );

                ContactModel::record_unsubscribe($unsubscribe_data);
            }
        }

        return true;
    }

    /**
     * Handles Postmark events and records unsubscribe data if necessary.
     *
     * This function processes a Postmark event and records the unsubscribe data if the event is a bounce or spam complaint.
     *
     * @param \WP_REST_Request $request The request object containing the bounce data.
     * @return bool|mixed False if the event data is invalid or the event is not handled, otherwise the result of recording the unsubscribe.
     * @since 1.15.0
     */
    private function handle_postmark( $request ){
        $params = MrmCommon::get_api_params_values($request);

        $unsubscribe_data = array();

        $event_name = Arr::get( $params, 'RecordType' );

        if ( in_array( $event_name, ['Bounce', 'SpamComplaint'] ) ) {
            $new_status = 'bounced';
            if ( 'SpamComplaint' === $event_name ) {
                $new_status = 'complained';
            }

            $reason = $new_status . __(' status was set from Postmark Webhook API. Reason: ', 'mrm') . $event_name . __('. Recorded at: ', 'mrm') . current_time('mysql');

            if ( $source_response = Arr::get( $params, 'Description' ) ) {
                $reason = $source_response;
            }

            $email = Arr::get( $params, 'Email' );
            if ( $email ) {
                $unsubscribe_data = array(
                    'email'  => $email,
                    'reason' => $reason,
                    'status' => $new_status,
                );
            }
        }

        if ($unsubscribe_data) {
            return ContactModel::record_unsubscribe( $unsubscribe_data );
        }

        return false;
    }

    /**
     * Handles Brevo events and records unsubscribe data if necessary.
     *
     * This function processes a Brevo event and records the unsubscribe data if the event is a bounce, spam, or unsubscribe.
     *
     * @param \WP_REST_Request $request The request object containing the bounce data.
     * @return bool|mixed False if the event data is invalid or the event is not handled, otherwise the result of recording the unsubscribe.
     * @since 1.15.0
     */
    private function handle_brevo( $request ){
        $params = MrmCommon::get_api_params_values($request);

        $unsubscribe_data = array();
        $catch_events     = array('soft_bounce', 'hard_bounce', 'spam', 'unsubscribed');

        $event = Arr::get( $params, 'event' );

        if ( !in_array( $event, $catch_events ) ) {
            return false;
        }

        $recipient_email = Arr::get($params, 'email');
        if ( !$recipient_email ) {
            return false;
        }

        $new_status = 'bounced';
        if ( 'spam' === $event ) {
            $new_status = 'complained';
        } else if ( 'unsubscribed' === $event ) {
            $new_status = 'unsubscribed';
        }

        $unsubscribe_data = array(
            'email'    => $recipient_email,
            'reason'   => $new_status . __(' was set by brevo webhook api with event name: ', 'mrm') . $event . __(' at ', 'mrm') . current_time('mysql'),
            'status'   => $new_status,
            'provider' => 'Brevo'
        );

        return ContactModel::record_unsubscribe($unsubscribe_data);
    }

    /**
     * Handles Sparkpost events and records unsubscribe data if necessary.
     *
     * This function processes a Sparkpost event and records the unsubscribe data if the event is a bounce, spam complaint, or link unsubscribe.
     *
     * @param \WP_REST_Request $request The request object containing the bounce data.
     * @return bool|mixed False if the event data is invalid or the event is not handled, otherwise the result of recording the unsubscribe.
     * @since 1.15.0
     */
    private function handle_sparkpost( $request ){
        $params = MrmCommon::get_api_params_values($request);
        $msys   = isset($params[0]['msys']) ? $params[0]['msys'] : array();

        if (!$msys || !is_array($msys)) {
            return false;
        }

        $unsubscribe_data = array();
        $event = Arr::get($msys, 'message_event');

        if (!$event || !is_array($event)) {
            return false;
        }

        $event_name = Arr::get($event, 'type');
        if (in_array($event_name, ['bounce', 'spam_complaint', 'link_unsubscribe'])) {
            $new_status = 'bounced';
            if ($event_name == 'spam_complaint' || $event_name == 'link_unsubscribe') {
                $new_status = 'complained';
            }
            $reason = $new_status . __(' status was set from Sparkpost Webhook API. Reason: ', 'mrm') . $event_name . __('. Recorded at: ', 'mrm') . current_time('mysql');

            if ($source_response = Arr::get($event, 'raw_reason')) {
                $reason = $source_response;
            }

            $email = Arr::get($event, 'rcpt_to');
            if ($email) {
                $unsubscribe_data = [
                    'email'  => $email,
                    'reason' => $reason,
                    'status' => $new_status
                ];
            }
        }

        if ($unsubscribe_data) {
            return ContactModel::record_unsubscribe($unsubscribe_data);
        }
    }

    /**
     * Handles Pepipost events and records unsubscribe data if necessary.
     *
     * This function processes a Pepipost event and records the unsubscribe data if the event is a bounce, invalid, spam, or unsubscribe.
     *
     * @param \WP_REST_Request $request The request object containing the bounce data.
     * @return bool|mixed False if the event data is invalid or the event is not handled, otherwise the result of recording the unsubscribe.
     * @since 1.15.0
     */
    private function handle_pepipost($request){
        $params = MrmCommon::get_api_params_values($request);
        $events = isset($params['events']) ? $params['events'] : array();

        if (!$events || !count($events)) {
            return false;
        }

        $unsubscribe_data = [];
        foreach ($events as $event) {
            if ($unsubscribe_data || !is_array($event)) {
                continue;
            }
            $event_name = Arr::get($event, 'EVENT');
            if (in_array($event_name, ['bounced', 'invalid', 'spam', 'unsubscribed'])) {
                $new_status = 'bounced';
                if ($event_name == 'unsubscribed' || $event_name == 'spam') {
                    $new_status = 'complained';
                }
                $reason = $new_status . __(' status was set from SendGrid Webhook API. Reason: ', 'mrm') . Arr::get($event, 'BOUNCE_TYPE') . __('. Recorded at: ', 'mrm') . current_time('mysql');

                if ($source_response = Arr::get($event, 'RESPONSE')) {
                    $reason = $source_response;
                }

                $email = Arr::get($event, 'EMAIL');
                if ($email) {
                    $unsubscribe_data = [
                        'email'  => $email,
                        'reason' => $reason,
                        'status' => $new_status
                    ];
                }
            }
        }

        if ($unsubscribe_data) {
            return ContactModel::record_unsubscribe($unsubscribe_data);
        }

        return false;
    }

    /**
     * Extract and validate an email address from a string.
     *
     * This function extracts an email address from a string that may contain additional text,
     * such as a name and angle brackets. It then validates the extracted email address.
     *
     * @param string $from_email The input string containing the email address.
     * @return string|false The extracted and validated email address, or false if the email is invalid.
     * 
     * @since 1.15.0
     */
    private function extract_valid_email($from_email){
        $bracket_pos = strpos($from_email, '<');
        if (false !== $bracket_pos) {
            $from_email = substr($from_email, $bracket_pos + 1);
            $from_email = str_replace('>', '', $from_email);
            $from_email = trim($from_email);
        }

        if (is_email($from_email)) {
            return $from_email;
        }
        return false;
    }
}
