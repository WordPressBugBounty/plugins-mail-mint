<?php
/**
 * Represents a mailer for sending emails using WordPress's wp_mail function.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2024-03-14 09:30:00
 * @modify date 2024-03-14 11:03:17
 * @package Mint\App\Internal\Mailers
 */

namespace Mint\App\Internal\Mailers;

use Mint\App\Classes\Message;

/**
 * Class WpmailMailer
 *
 * Represents a mailer for sending emails using WordPress's wp_mail function.
 *
 * @package Mint\App\Internal\Mailers
 * @since 1.9.5
 */
class WpmailMailer extends BaseMailer {

	/**
	 * Mailer name
	 *
	 * @var string $name
	 *
	 * @since 1.9.5
	 */
	public $name = 'WP Mail';

	/**
	 * Mailer Slug
	 *
	 * @var string $slug
	 *
	 * @since 1.9.5
	 */
	public $slug = 'wpmail';

	/**
	 * WpmailMailer constructor.
	 *
	 * @since 1.9.5
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Sends an email using WordPress's wp_mail function.
	 *
	 * @param Message $message The email message object containing details like 'to', 'subject', 'body', 'headers', and 'attachments'.
	 * @return mixed True on success, WP_Error object on failure.
	 *
	 * @since 1.9.5
	 * @since 1.11.1 Added filter to modify the email message before sending.
	 * @since 1.13.4 Added check for receiving email address.
	 */
	public function send( Message $message ) {
		// Add filter to modify the email message before sending.
		if( is_plugin_active( 'wp-html-mail/wp-html-mail.php' ) ) {
			add_filter( 'wp_mail', function( $args ) use ( $message ) {
				// Check if the email is being sent to the receiving email address.
				if( $args['to'] == $message->to ){
					$args['message'] = $message->body;
				}
				return $args;
			}, PHP_INT_MAX );
		}

		$send_mail = wp_mail( $message->to, $message->subject, $message->body, $message->headers, $message->attachments );

		if ( ! $send_mail ) {
			global $phpmailer;

			if ( is_object( $phpmailer ) && $phpmailer->ErrorInfo ) {
				$message = wp_strip_all_tags( $phpmailer->ErrorInfo );
			} else {
				$message = __( 'WP Mail Error: Unknown', 'mrm' );
			}

			return $this->do_response( 'error', $message );
		}

		return $this->do_response( 'success' );
	}

}
