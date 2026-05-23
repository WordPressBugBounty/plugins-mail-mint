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
