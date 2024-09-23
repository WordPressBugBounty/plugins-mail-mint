<?php
/**
 * Manages the selection and instantiation of the appropriate mailer for sending emails.
 * This class determines the current mailer based on settings
 * and instantiates the corresponding mailer class.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2024-03-14 09:30:00
 * @modify date 2024-03-14 11:03:17
 * @package Mint\App\Classes
 */

namespace Mint\App\Classes;

/**
 * Class Mailer
 *
 * Handles the selection and instantiation of the appropriate mailer class based on settings.
 *
 * @package Mint\App\Classes
 * @since 1.10.0
 */
class Mailer {

    /**
     * The instance of the current mailer for sending emails.
     *
     * @var object $mailer The current mailer object.
     * @since 1.10.0
     */
    public $mailer;

    /**
     * Constructor method.
     * Initializes the mailer by setting the appropriate mailer instance.
     * 
     * @since 1.10.0
     */
	public function __construct() {
		$this->set_mailer();
	}

    /**
     * Sets the mailer object based on the current mailer class.
     *
     * This function dynamically instantiates a mailer object of the appropriate class based on the current
     * mailer class retrieved by the 'get_current_mailer_class()' method.
     *
     * @return void
     * @since 1.10.0
     */
    public function set_mailer() {
        $mailer_class = $this->get_current_mailer_class();
        $mailer_class = '\Mint\\App\\Internal\\Mailers\\' . $mailer_class;
        $mailer_obj   = new $mailer_class();
        $this->mailer = $mailer_obj;
    }

    /**
     * Retrieves the current mailer class based on the mailer slug.
     *
     * This function gets the current mailer slug using the 'get_current_mailer_slug()' method and
     * generates the corresponding mailer class name.
     *
     * @return string The current mailer class name.
     * @since 1.10.0
     */
    public function get_current_mailer_class() {
        $malier_slug          = $this->get_current_mailer_slug();
        $current_mailer_class = ucfirst( $malier_slug ) . 'Mailer';

        // If the mailer class doesn't exist, fallback to WP Mail.
        if ( ! class_exists( $current_mailer_class ) ) {
            $current_mailer_class = 'WpmailMailer';
        }
        return $current_mailer_class;
    }

    /**
     * Retrieves the current mailer slug from the email service settings.
     *
     * This function fetches the current mailer slug from the 'mrm_email_service_settings' option,
     * which stores the email service settings.
     *
     * @return string The current mailer slug.
     * @since 1.10.0
     */
    public function get_current_mailer_slug() {
        $mailer_settings     = get_option( 'mrm_email_service_settings', '');

        // Return the mailer slug if it's not empty; otherwise, default to 'wpmail'.
        return ! empty( $mailer_settings['mailer'] ) ? $mailer_settings['mailer'] : 'wpmail';
    }

    public function send( $email, $subject, $content, $headers ) {
        $message = new Message();

        $message->to      = $email;
        $message->subject = $subject;
        $message->body    = $content;
        $message->headers = $headers;

        $send_response = $this->mailer->send( $message );
        return ! is_wp_error( $send_response ) ? true : false;
    }
}
