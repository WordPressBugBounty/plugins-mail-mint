<?php

/**
 * This class handles event tracking functionality using PostHog analytics.
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
use PostHog\PostHog;

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
     * The distinct ID for the current user.
     * 
     * @var string $distinct_id The distinct ID for the current user.
     * @since 1.17.10
     */
    private $distinct_id;

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
        $this->distinct_id = get_option('mailmint_installation_id');
        if (!$this->distinct_id) {
            $this->distinct_id = wp_generate_uuid4();
            update_option('mailmint_installation_id', $this->distinct_id);
        }

        if ($this->enabled) {
            PostHog::init('phc_XTEbQk5eJoGlFdEIhJUh1yTfH9JnwdEkS029kAv0EPN', [
                'host' => 'https://eu.i.posthog.com',
            ]);
        }

        $this->register_hooks();
    }

    /**
     * Gets or creates a unique identifier for the current installation.
     * 
     * This method checks for an existing installation ID in WordPress options.
     * If none exists, it generates a new UUID using wp_generate_uuid4()
     * and stores it in the options table.
     * 
     * @access private
     * 
     * @return string The installation's distinct ID
     * 
     * @since 1.17.10
     */
    private function get_or_create_distinct_id(): string
    {
        $distinct_id = get_option('mailmint_installation_id');
        if (!$distinct_id) {
            $distinct_id = wp_generate_uuid4();
            update_option('mailmint_installation_id', $distinct_id);
        }
        return $distinct_id;
    }

    /**
     * Initializes the PostHog instance.
     * 
     * This method checks if tracking is enabled and initializes the PostHog
     * instance with the appropriate API key and host.
     *
     * @access private
     *
     * @return void
     *
     * @since 1.17.10
     */
    private function initialize_posthog(): void
    {
        if ($this->enabled) {
            PostHog::init('phc_XTEbQk5eJoGlFdEIhJUh1yTfH9JnwdEkS029kAv0EPN', [
                'host' => 'https://eu.i.posthog.com',
            ]);
        }
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
        // Hook name => [PostHog event name, callback method]
        $events = [
            'mailmint_after_accept_consent' => ['plugin_activated', 'on_plugin_activated'],
            'mailmint_contact_list_viewed'  => ['contact_list_viewed', 'on_contact_list_viewed'],
            'mailmint_campaign_created'     => ['campaign_created', 'on_campaign_created'],
            'mailmint_campaign_email_sent'  => ['campaign_email_sent', 'on_campaign_email_sending_completed'],
            'mailmint_campaign_analytics'   => ['campaign_analytics', 'on_campaign_analytics_viewed'],
            // 'mailmint_wc_abandoned_cart_automation_created' => ['abandoned_cart_automation_created', 'on_abandoned_cart_automation_created'],
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
     * Sends a plugin activation event to PostHog.
     *
     * This method sends a plugin activation event to PostHog, including
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
        $this->send_event('plugin_activated', [
            'site_url'       => get_site_url(),
            'plugin_slug'    => 'mail-mint',
            'plugin_version' => MRM_VERSION,
        ]);
    }

    /**
     * Sends an event to PostHog.
     *
     * This method sends an event to PostHog, including the event name,
     * distinct ID, and properties.
     *
     * @access public
     *
     * @param string $event_name The name of the event to send.
     * @param array $properties Additional properties to include with the event.
     * 
     * @return void
     *
     * @since 1.17.10
     */
    private function send_event(string $event, array $properties = []): void
    {
        if (!$this->enabled) return;

        try {
            PostHog::capture([
                'distinctId' => $this->distinct_id,
                'event' => $event,
                'properties' => $properties,
            ]);
        } catch (\Exception $e) {
            error_log('[PostHog] ' . $e->getMessage());
        }
    }

    /**
     * Sends an event to PostHog when a contact list is viewed.
     *
     * This method sends an event to PostHog when a contact list is viewed,
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
                $this->send_event('contacts_added', [
                    'journey_type' => 'sending_or_scheduling_email_campaign',
                    'trigger_name' => 'contact_list',
                ]);
                update_option('mailmint_contacts_added', true);
            }
        }
    }

    /**
     * Sends an event to PostHog when a campaign is created.
     *
     * This method sends an event to PostHog when a campaign is created,
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
        $this->send_event('campaign_created', [
            'journey_type'  => 'sending_or_scheduling_email_campaign',
            'campaign_type' => $args['type'],
            'trigger_name'  => 'campaign_created',
        ]);
    }

    /**
     * Sends an event to PostHog when a campaign email is sent.
     *
     * This method sends an event to PostHog when a campaign email is sent,
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
        $this->send_event('campaign_completed', [
            'journey_type'  =>'sending_or_scheduling_email_campaign',
            'campaign_type' => $type,
            'trigger_name'  => 'campaign_completed',
        ]);
    }

    /**
     * Sends an event to PostHog when campaign analytics are viewed.
     *
     * This method sends an event to PostHog when campaign analytics are viewed,
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
        $this->send_event('campaign_analytics_viewed', [
            'journey_type'  =>'sending_or_scheduling_email_campaign',
            'campaign_type' => $type,
            'trigger_name'  => 'campaign_analytics_viewed',
        ]);
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

    public function on_abandoned_cart_recovered(): void
    {
        $this->send_event('abandoned_cart_recovered', [
            'journey_type' => 'abandoned_cart_recovery',
        ]);
    }

    public function on_admin_dashboard_visited($params): void
    {
        if (isset($params['page']) && $params['page'] == 1) {
            $days_since_install = $this->get_days_since_install();
            $this->send_event('admin_dashboard_visited', [
                'page' => 'abandoned_cart',
                'journey_type' => 'abandoned_cart_recovery',
                'days_since_install' => $days_since_install,
            ]);
        }
    }

    public function on_abandoned_cart_automation_created(): void
    {
        $this->send_event('automation_created', [
            'trigger_name' => 'abandoned_cart',
            'journey_type' => 'abandoned_cart_recovery',
        ]);
    }

    public function on_order_completed_automation_created(): void
    {
        $this->send_event('automation_created', [
            'trigger_name' => 'wc_order_completed',
            'journey_type' => 'post_purchase',
        ]);
    }

    public function on_new_order_placed_automation_created(): void
    {
        $this->send_event('automation_created', [
            'trigger_name' => 'wc_new_order_placed',
            'journey_type' => 'post_purchase',
        ]);
    }

    public function on_wc_first_order_automation_created(): void
    {
        $this->send_event('automation_created', [
            'trigger_name' => 'wc_first_order',
            'journey_type' => 'post_purchase',
        ]);
    }

    public function get_automation_overall_analytics($params): void
    {
        $days_since_install = $this->get_days_since_install();
        $this->send_event('admin_dashboard_visited', [
            'page' => 'automation_analytics',
            'journey_type' => 'post_purchase',
            'days_since_install' => $days_since_install,
        ]);
    }

    public function on_product_block_automation_email_sent(): void
    {
        $this->send_event('product_offer_email_sent', [
            'journey_type' => 'post_purchase'
        ]);
    }

    public function on_wp_user_registration_automation_created(): void
    {
        $this->send_event('automation_created', [
            'trigger_name' => 'wp_user_registration',
            'journey_type' => 'onboarding_new_customers',
        ]);
    }

    public function on_automation_email_sent($automation_id, $user_email, $is_sent): void
    {
        $trigger_name = HelperFunctions::get_automation_trigger_name($automation_id);

        if ('wp_user_registration' === $trigger_name || 'wc_first_order' === $trigger_name) {
            $this->send_event('email_sent_from_automation', [
                'journey_type' => 'onboarding_new_customers',
            ]);
        }
    }

    public function on_segment_customer_for_personalized_campaigns($automation_id): void
    {
        $steps = HelperFunctions::get_automation_steps_by_id($automation_id);
        // Find if a specific key exists in any sub-array.
        $matched = array_filter($steps, function ($step) {
            return isset($step['key']) && $step['key'] === 'addList';
        });

        $trigger_name = HelperFunctions::get_automation_trigger_name($automation_id);

        if ($matched) {
            $this->send_event('automation_created', [
                'trigger_name' => $trigger_name,
                'journey_type' => 'segment_customer_for_personalized_campaigns',
                'has_add_to_list_action' => true,
            ]);
        }
    }

    public function on_automation_after_added_to_list(): void
    {
        $this->send_event('contact_added_to_list', [
            'journey_type' => 'segment_customer_for_personalized_campaigns',
        ]);
    }

    public function on_email_automation_for_lesson_completion_engagement($automation_id): void
    {
        $trigger_name = HelperFunctions::get_automation_trigger_name($automation_id);
        $this->send_event('automation_created', [
            'trigger_name' => $trigger_name,
            'journey_type' => 'email_automation_for_lesson_completion_engagement',
        ]);
    }
}
