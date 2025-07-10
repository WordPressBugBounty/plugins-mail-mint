<?php
/**
 * Create Contact to Mail Mint and Appsero
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-06-12 11:03:17
 * @modify date 2024-06-12 11:03:17
 * @package Mint\MRM\Internal\Admin
 */
namespace Mint\MRM\Internal\Admin;

/**
 * Create Contact to Mail Mint and Appsero.
 * 
 * @since 1.13.0
 */
class CreateContact {
    
    /**
     * Webhook URL
     * 
     * @var array
     * @since 1.13.0
     */
    protected $web_hook_url = array(
        'https://useraccount.getwpfunnels.com/?mailmint=1&route=webhook&topic=contact&hash=706ab83f-8acb-4899-9ef9-1b95e4d2c1d5',
    );

    /**
     * Contact email address.
     * 
     * @var string
     * @since 1.13.0
     */
    protected $email = '';

    /**
     * Contact name.
     * 
     * @var string
     * @since 1.13.0
     */
    protected $name = '';

    /**
     * Appsero URL
     * 
     * @var string
     * @since 1.13.0
     */
    protected $appsero_url = 'https://api.appsero.com/';

    /**
     * Appsero API Key
     * 
     * @var string
     * @since 1.13.0
     */
    protected $appsero_api_key = '9d981a5e-81ce-4a15-a61e-7f9d912625c0';

    /**
     * Plugin Name
     * 
     * @var string
     * @since 1.13.0
     */
    protected $plugin_name = 'Mail Mint';

    /**
     * Plugin Slug
     * 
     * @var string
     * @since 1.13.0
     */
    protected $plugin_slug = 'mail-mint';

    /**
     * Plugin File
     * 
     * @var string
     * @since 1.13.0
     */
    protected $plugin_file = __FILE__;

    /**
     * Source
     * 
     * @var string
     * @since 1.13.0
     */
    protected $source = 'setup-wizard';

    /**
     * Constructor of the class CreateContact.
     * 
     * @param string $email
     * @param string $name
     * @since 1.13.0
     */
    public function __construct( $email, $name ){
        $this->email = $email;
        $this->name  = $name;
        if( 'setup-wizard' == $this->source ){
            add_filter( $this->plugin_slug.'_tracker_data',array( $this, 'modify_contact_data' ), PHP_INT_MAX );
        }
    }

    /**
     * Create contact to Mail Mint via webhook
     * 
     * @return array
     * @since 1.13.0
     */
    public function create_contact_via_webhook(){
        if( !$this->email ){
            return array( 'success' => false );
        }

        $response = array( 'success' => false );

        $json_body_data = json_encode( array( 
            'email'      => $this->email,
            'first_name' => $this->name,
            )
        );

        try{
            if( !empty( $this->web_hook_url ) ){
                foreach( $this->web_hook_url as $url ){
                    $response = wp_remote_request( $url, array(
                        'method'    => 'POST',
                        'headers'   => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => $json_body_data
                    ));
                }
            }
        }catch(\Exception $e){
            $response = array( 'success' => false );
        }
        
        return $response;
    }

    /**
     * Send contact to Appsero
     * 
     * @return void
     * @since 1.13.0
     */
    public function send_contact_to_appsero(){
        // Enable tracking FIRST
        update_option($this->plugin_slug . '_allow_tracking', 'yes');
        update_option($this->plugin_slug . '_tracking_notice', ' hide');

        // Force EventTracker to reinitialize with new tracking settings
        if (class_exists('\Mint\MRM\Internal\Tracking\EventTracker')) {
            // Reset the singleton instance so it reinitializes with new settings
            $reflection = new \ReflectionClass('\Mint\MRM\Internal\Tracking\EventTracker');
            $instance = $reflection->getProperty('instance');
            $instance->setAccessible(true);
            $instance->setValue(null, null);

            // Now initialize with the new tracking enabled setting
            \Mint\MRM\Internal\Tracking\EventTracker::init();
        }

        $client = new \Appsero\Client($this->appsero_api_key, $this->plugin_name, $this->plugin_file);
        $client->insights()->send_tracking_data(true);

        do_action('mailmint_after_accept_consent');
    }

    /**
     * Modify contact data before sending to appsero
     * 
     * @param array $data
     * @return array
     * @since 1.13.0
     */
    public function modify_contact_data( $data ){
        $data['admin_email'] = $this->email;
        $data['first_name']  = $this->name;
        return $data;
    }
}