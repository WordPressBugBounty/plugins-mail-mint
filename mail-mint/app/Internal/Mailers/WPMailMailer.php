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
		// is_plugin_active() is not loaded on REST or WP-Cron requests before WP 6.8.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

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

		// Capture the reason for this send only; the global $phpmailer->ErrorInfo can be left over
		// from an earlier send. ErrorInfo adds the SMTP code and server reply, but PHPMailer appends
		// the last SMTP error to every message, so it is only used when this send failed at the
		// SMTP stage — otherwise an earlier connection error would be attached to an unrelated failure.
		$failure_reason = '';
		$capture_error  = function ( $error ) use ( &$failure_reason ) {
			global $phpmailer;
			if ( ! is_wp_error( $error ) ) {
				return;
			}
			$failure_reason = $error->get_error_message();
			$error_info     = is_object( $phpmailer ) ? (string) $phpmailer->ErrorInfo : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( 0 === strpos( $failure_reason, 'SMTP Error' ) && false !== strpos( $error_info, $failure_reason ) ) {
				// PHPMailer joins the server detail with no separator ("...serverSMTP server error:").
				$failure_reason = preg_replace( '/\.?\s*SMTP server error:/', '. SMTP server error:', $error_info );
			}
		};
		add_action( 'wp_mail_failed', $capture_error );

		try {
			$send_mail = wp_mail( $message->to, $message->subject, $message->body, $message->headers, $message->attachments );
		} finally {
			remove_action( 'wp_mail_failed', $capture_error );
		}

		if ( ! $send_mail ) {
			$message = '' !== $failure_reason
				? wp_strip_all_tags( $failure_reason )
				: __( 'WP Mail Error: Unknown. wp_mail() returned false without an error, so another plugin may have blocked the email.', 'mrm' );

			return $this->do_response( 'error', $message );
		}

		return $this->do_response( 'success' );
	}

}
