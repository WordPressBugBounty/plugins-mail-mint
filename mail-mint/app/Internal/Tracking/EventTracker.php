<?php

/**
 * This class handles event tracking functionality using OpenPanel analytics.
 * This class implements a singleton pattern to ensure only one instance
 * of the tracker exists throughout the application lifecycle.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2025-05-12 09:30:00
 * @modify date 2024-05-12 11:03:17
 * @package Mint\App\Internal\Tracking
 */

namespace Mint\MRM\Internal\Tracking;

use Mint\MRM\DataBase\Models\CampaignModel;
use MintMail\App\Internal\Automation\HelperFunctions;

/**
 * Class EventTracker
 * 
 * This class implements a singleton pattern to ensure only one instance
 * of the tracker exists throughout the application lifecycle.
 * 
 * @package Mint\MRM\Internal\Tracking
 * @since 1.17.10
 */
class EventTracker{

    /**
     * Holds the singleton instance of this class.
     * 
     * @var EventTracker|null $instance The singleton instance of this class.
     * @since 1.17.10
     */
    private static $instance;

    /**
     * Flag to indicate if tracking is enabled.
     *
     * @var bool $enabled Flag to indicate if tracking is enabled.
     * @since 1.17.10
     */
    private $enabled;

    /**
     * Private constructor to prevent instantiation from outside the class.
     *
     * @since 1.17.10
     */
    private function __construct(){
        $this->enabled = get_option('mail-mint_allow_tracking') === 'yes';

        add_action( 'mailmint_after_accept_consent', array( $this, 'on_plugin_activated' ) );
        add_action( 'mailmint_contact_list_viewed', array( $this, 'on_contact_list_viewed' ) );
        add_action( 'mailmint_campaign_created', array( $this, 'on_campaign_created' ), 10, 2 );
        add_action( 'mailmint_campaign_email_sent', array( $this, 'on_campaign_email_sending_completed' ), 10, 2 );
        add_action( 'mailmint_campaign_analytics', array( $this, 'on_campaign_analytics_viewed' ), 10, 1 );
        add_action( 'mailmint_plugin_deactivated', array( $this, 'on_plugin_deactivated' ) );
        add_action( 'mailmint_wc_abandoned_cart_automation_created', array( $this, 'on_abandoned_cart_automation_created' ) );
        add_action( 'mailmint_automation_log_overall_analytics', array( $this, 'get_automation_overall_analytics' ) );
        add_action( 'mailmint_product_block_automation_email', array( $this, 'on_product_block_automation_email_sent' ) );
        add_action( 'mailmint_after_automation_send_mail', array( $this, 'on_automation_email_sent' ), 10, 3 );
        add_action( 'mailmint_automation_after_added_to_list', array( $this, 'on_automation_after_added_to_list' ) );
    }

    /**
     * Returns the singleton instance of this class.
     *
     * If the instance does not exist, it creates a new instance.
     * 
     * @access public
     *
     * @return EventTracker The singleton instance of this class.
     *
     * @since 1.17.10
     */
    public static function init(): void
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
    }

    /**
     * Registers WordPress hooks for tracking events.
     *
     * This method iterates over an array of events and registers WordPress
     * hooks for each event.
     * 
     * @access private
     *
     * @return void
     *
     * @since 1.17.10
     */
    private function register_hooks(): void
    {
        $events = [
            // 'mailmint_wc_abandoned_cart_lost_automation_created' => ['abandoned_cart_automation_created', 'on_abandoned_cart_automation_created'],
            // 'mailmint_wc_abandoned_cart_recovered_automation_created' => ['abandoned_cart_automation_created', 'on_abandoned_cart_automation_created'],
            // 'mailmint_after_abandoned_cart_recovered' => ['abandoned_cart_recovered', 'on_abandoned_cart_recovered'],
            // 'mailmint_abandoned_cart_get_recoverable_carts' => ['admin_dashboard_visited', 'on_admin_dashboard_visited'],
            // 'mailmint_wc_order_completed_automation_created' => ['automation_created', 'on_order_completed_automation_created'],
            // 'mailmint_wc_all_order_created_automation_created' => ['automation_created', 'on_new_order_placed_automation_created'],
            // 'mailmint_wc_first_order_automation_created' => ['automation_created', 'on_wc_first_order_automation_created'],
            // 'mailmint_automation_log_overall_analytics' => ['admin_dashboard_visited', 'get_automation_overall_analytics'],
            // 'mailmint_product_block_automation_email' => ['product_offer_email_sent', 'on_product_block_automation_email_sent'],
            // 'mailmint_wp_user_registration_automation_created' => ['automation_created', 'on_wp_user_registration_automation_created'],
            // 'mailmint_after_automation_send_mail' => ['email_sent_from_automation', 'on_automation_email_sent'],
            // 'mailmint_wc_order_completed_automation_updated' => ['automation_created', 'on_segment_customer_for_personalized_campaigns'],
            // 'mailmint_wc_all_order_created_automation_updated' => ['automation_created', 'on_segment_customer_for_personalized_campaigns'],
            // 'mailmint_wc_first_order_automation_updated' => ['automation_created', 'on_segment_customer_for_personalized_campaigns'],
            // 'mailmint_wc_order_created_automation_updated' => ['automation_created', 'on_segment_customer_for_personalized_campaigns'],
            // 'mailmint_wc_order_status_changed_automation_updated' => ['automation_created', 'on_segment_customer_for_personalized_campaigns'],
            // 'mailmint_wc_order_failed_automation_updated' => ['automation_created', 'on_segment_customer_for_personalized_campaigns'],
            // 'mailmint_automation_after_added_to_list' => ['contact_added_to_list', 'on_automation_after_added_to_list'],
            // 'mailmint_learndash_complete_lesson_automation_updated' => ['automation_created', 'on_email_automation_for_lesson_completion_engagement'],
            // 'mailmint_tutor_complete_lesson_automation_updated' => ['automation_created', 'on_email_automation_for_lesson_completion_engagement'],
            // Add more here easily
        ];

        foreach ($events as $hook => [$event_name, $callback]) {
            add_action($hook, [$this, $callback], 10, 10);
        }
    }

    /**
     * Sends a plugin activation event to OpenPanel.
     *
     * This method sends a plugin activation event to OpenPanel, including
     * the site URL, plugin slug, and plugin version.
     *
     * @access public
     *
     * @return void
     *
     * @since 1.17.10
     */
    public function on_plugin_activated(): void
    {
        linno_telemetry_track(
            MRM_FILE,
            'plugin_activation',
            array(
                'site_url'        => get_site_url(),
                'plugin_version'  => MRM_VERSION,
            )
        );
    }

    /**
     * Sends a plugin deactivation event to OpenPanel.
     *
     * This method sends a plugin deactivation event to OpenPanel, including
     * the site URL and plugin version.
     *
     * @access public
     *
     * @return void
     *
     * @since 1.17.10
     */
    public function on_plugin_deactivated(): void
    {
        linno_telemetry_track(
            MRM_FILE,
            'plugin_deactivation',
            array(
                'site_url'        => get_site_url(),
                'plugin_version'  => MRM_VERSION,
            )
        );
    }

    /**
     * Sends an event to OpenPanel when a contact list is viewed.
     *
     * This method sends an event to OpenPanel when a contact list is viewed,
     * including the total number of contacts in the list.
     *
     * @access public
     *
     * @param int $total_contacts The total number of contacts in the list.
     *
     * @return void
     *
     * @since 1.17.10
     */
    public function on_contact_list_viewed($total_contacts): void
    {
        if ($total_contacts > 0) {
            $has_added_contacts = get_option('mailmint_contacts_added', false);
            if (!$has_added_contacts) {
                linno_telemetry_track(
                    MRM_FILE,
                    'contacts_added',
                    array(
                        'total_contacts' => $total_contacts,
                        'time'           => current_time('mysql'),
                    )
                );
                update_option('mailmint_contacts_added', true);
            }
        }
    }

    /**
     * Sends an event to OpenPanel when a campaign is created.
     *
     * This method sends an event to OpenPanel when a campaign is created,
     * including the campaign type and trigger name.
     *
     * @access public
     *
     * @param int $campaign_id The ID of the created campaign.
     * @param array $args The arguments passed to the campaign creation.
     * @return void
     *
     * @since 1.17.10
     */
    public function on_campaign_created($campaign_id, $args): void
    {
        linno_telemetry_track(
            MRM_FILE,
            'campaign_created',
            array(
                'campaign_type' => $args['type'],
                'campaign_id'   => $campaign_id,
            )
        );
    }

    /**
     * Sends an event to OpenPanel when a campaign email is sent.
     *
     * This method sends an event to OpenPanel when a campaign email is sent,
     * including the campaign type and trigger name.
     *
     * @access public
     *
     * @param int $campaign_id The ID of the sent campaign.
     * @param int $email_id The ID of the email content.
     * @return void
     *
     * @since 1.17.10
     */
    public function on_campaign_email_sending_completed($campaign_id, $email_id): void
    {
        $type = CampaignModel::get_campaign_type($campaign_id);
        linno_telemetry_track(
            MRM_FILE,
            'campaign_completed',
            array(
                'campaign_type' => $type,
                'campaign_id'   => $campaign_id,
                'email_id'      => $email_id,
            )
        );
    }

    /**
     * Sends an event to OpenPanel when campaign analytics are viewed.
     *
     * This method sends an event to OpenPanel when campaign analytics are viewed,
     * including the campaign type and trigger name.
     *
     * @access public
     *
     * @param int $campaign_id The ID of the viewed campaign.
     * @return void
     *
     * @since 1.17.10
     */
    public function on_campaign_analytics_viewed($campaign_id): void
    {
        $type = CampaignModel::get_campaign_type($campaign_id);
        linno_telemetry_track(
            MRM_FILE,
            'campaign_analytics_viewed',
            array(
                'campaign_type' => $type,
                'campaign_id'   => $campaign_id,
            )
        );
    }

    private function get_days_since_install() {
        // Get the install timestamp from WP options table
        $install_timestamp = get_option('mailmint_install_timestamp');

        // Check if it's set and is a valid integer
        if (empty($install_timestamp) || !is_numeric($install_timestamp)) {
            return null;
        }

        // Cast to integer
        $install_timestamp = (int) $install_timestamp;

        // Check if it's a valid timestamp (not in future or zero)
        if ($install_timestamp <= 0 || $install_timestamp > time()) {
            return null;
        }

        // Calculate the difference in days
        $seconds_since_install = time() - $install_timestamp;
        $days_since_install = floor($seconds_since_install / 86400); // 86400 = seconds in a day

        return $days_since_install >= 0 ? $days_since_install : null;
    }

    // === Event handlers ===

    // public function on_abandoned_cart_recovered(): void
    // {
    //     $this->send_event('abandoned_cart_recovered', [
    //         'journey_type' => 'abandoned_cart_recovery',
    //     ]);
    // }

    // public function on_admin_dashboard_visited($params): void
    // {
    //     if (isset($params['page']) && $params['page'] == 1) {
    //         $days_since_install = $this->get_days_since_install();
    //         $this->send_event('admin_dashboard_visited', [
    //             'page' => 'abandoned_cart',
    //             'journey_type' => 'abandoned_cart_recovery',
    //             'days_since_install' => $days_since_install,
    //         ]);
    //     }
    // }

    public function on_abandoned_cart_automation_created(): void
    {
        linno_telemetry_track(
            MRM_FILE,
            'automation_created',
            array(
                'automation_type' => 'abandoned_cart',
            )
        );
    }

    // public function on_order_completed_automation_created(): void
    // {
    //     $this->send_event('automation_created', [
    //         'trigger_name' => 'wc_order_completed',
    //         'journey_type' => 'post_purchase',
    //     ]);
    // }

    // public function on_new_order_placed_automation_created(): void
    // {
    //     $this->send_event('automation_created', [
    //         'trigger_name' => 'wc_new_order_placed',
    //         'journey_type' => 'post_purchase',
    //     ]);
    // }

    // public function on_wc_first_order_automation_created(): void
    // {
    //     $this->send_event('automation_created', [
    //         'trigger_name' => 'wc_first_order',
    //         'journey_type' => 'post_purchase',
    //     ]);
    // }

    public function get_automation_overall_analytics($params): void
    {
        $has_automation_used = get_option('mailmint_automation_used', false);
        if (!$has_automation_used) {
            linno_telemetry_track(
                MRM_FILE,
                'admin_dashboard_visited',
                array(
                    'page' => 'automation_analytics',
                )
            );
            update_option('mailmint_automation_used', true);
        }
        
    }

    public function on_product_block_automation_email_sent(): void
    {
        linno_telemetry_track(
            MRM_FILE,
            'product_offer_email_sent',
            array(
                'journey_type' => 'post_purchase',
            )
        );
    }

    // public function on_wp_user_registration_automation_created(): void
    // {
    //     $this->send_event('automation_created', [
    //         'trigger_name' => 'wp_user_registration',
    //         'journey_type' => 'onboarding_new_customers',
    //     ]);
    // }

    public function on_automation_email_sent($automation_id, $user_email, $is_sent): void
    {
        if (!$automation_id) {
            return;
        }

        $trigger_name = HelperFunctions::get_automation_trigger_name($automation_id);
        $has_automation_used = get_option('mailmint_automation_used', false);
        if (!$has_automation_used) {
            linno_telemetry_track(
                MRM_FILE,
                'email_sent_from_automation',
                array(
                    'trigger_name' => $trigger_name,
                )
            );
            update_option('mailmint_automation_used', true);
        }
    }

    // public function on_segment_customer_for_personalized_campaigns($automation_id): void
    // {
    //     $steps = HelperFunctions::get_automation_steps_by_id($automation_id);
    //     // Find if a specific key exists in any sub-array.
    //     $matched = array_filter($steps, function ($step) {
    //         return isset($step['key']) && $step['key'] === 'addList';
    //     });

    //     $trigger_name = HelperFunctions::get_automation_trigger_name($automation_id);

    //     if ($matched) {
    //         $this->send_event('automation_created', [
    //             'trigger_name' => $trigger_name,
    //             'journey_type' => 'segment_customer_for_personalized_campaigns',
    //             'has_add_to_list_action' => true,
    //         ]);
    //     }
    // }

    public function on_automation_after_added_to_list(): void
    {
        linno_telemetry_track(
            MRM_FILE,
            'contact_added_to_list',
            array(
                'time' => current_time('mysql'),
            )
        );
    }
}
