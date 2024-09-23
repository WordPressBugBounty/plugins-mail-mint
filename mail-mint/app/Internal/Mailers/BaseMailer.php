<?php
/**
 * The BaseMailer class provides a foundational structure
 * for implementing email sending functionality within WordPress,
 * offering methods for request handling, error logging, batch sending,
 * and integration with email service providers
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2024-03-14 09:30:00
 * @modify date 2024-03-14 11:03:17
 * @package Mint\App\Internal\Mailers
 */

namespace Mint\App\Internal\Mailers;

use Mint\App\Classes\Message;
use WP_Error;

/**
 * class BaseMailer
 *
 * @since 1.9.5
 */
class BaseMailer {

	/**
	 * Mailer name
	 *
	 * @var string $name
	 *
	 * @since 1.9.5
	 */
	public $name;

	/**
	 * Mailer Slug
	 *
	 * @var string $slug
	 *
	 * @since 1.9.5
	 */
	public $slug;

	/**
	 * Mailer Version
	 *
	 * @var string $version
	 *
	 * @since 1.9.5
	 */
	public $version = '1.0';

	/**
	 * Request headers
	 *
	 * @var array $headers
	 *
	 * @since 1.9.5
	 */
	public $headers = array();

	/**
	 * Request body
	 *
	 * @var array $body
	 *
	 * @since 1.9.5
	 */
	public $body = array();

	/**
	 * Added Logger Context
	 *
	 * @var array $logger_context
	 *
	 * @since 1.9.5
	 */
	public $logger_context = array(
		'source' => 'mail_mint_email_sending',
	);

	/**
	 * Flag to determine whether this mailer support batch sending or not
	 *
	 * @var boolean $support_batch_sending
	 *
	 * @since 1.9.5
	 */
	public $support_batch_sending = false;

	/**
	 * Stores batch sending mode
	 *
	 * @var string $batch_sending_mode
	 *
	 * @since 1.9.5
	 */
	public $batch_sending_mode = '';

	/**
	 * Batch limit
	 *
	 * @var boolean $batch_limit
	 *
	 * @since 1.9.5
	 */
	public $batch_limit = 0;

	/**
	 * Current batch size
	 *
	 * @var boolean $current_batch_size
	 *
	 * @since 1.9.5
	 */
	public $current_batch_size = 0;

	/**
	 * Batch data
	 *
	 * @var array $batch_data
	 *
	 * @since 1.9.5
	 */
	public $batch_data = array();

	/**
	 * Links
	 *
	 * @var array $links
	 *
	 * @since 1.9.5
	 */
	public $links = array();

	/**
	 * Account URL
	 *
	 * @var string $account_url
	 *
	 * @since 1.9.5
	 */
	protected $account_url = '';

	/**
	 * BaseMailer constructor.
	 *
	 * @since 1.9.5
	 */
	public function __construct() {
	}

	/**
	 * Get Mailer Name
	 *
	 * @return string
	 *
	 * @since 1.9.5
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Placeholder function for sending an email message.
	 *
	 * This function returns a WP_Error indicating that the send method is not implemented.
	 *
	 * @param Message $message The message object to be sent.
	 * @return WP_Error Error object indicating that the send method is not implemented.
	 *
	 * @since 1.9.5
	 */
	public function send( Message $message ) {
		return new WP_Error( 'mail_mint_email_sending_failed', 'Send Method Not Implemented' );
	}

	/**
	 * Method will be called before email send
	 *
	 * @since 1.9.5
	 */
	public function pre_send( Message $message ) {
	}

	/**
	 * Method will be called after email send
	 *
	 * @since 1.9.5
	 */
	public function post_send( Message $message ) {
	}

	/**
	 * Generates a response based on the status and message.
	 *
	 * If the status is 'success', it returns true. Otherwise, it returns a WP_Error object.
	 *
	 * @param string $status The status of the response, default is 'success'.
	 * @param string $message The message associated with the response, default is an empty string.
	 * @return WP_Error|bool WP_Error object if status is not 'success', otherwise true.
	 *
	 * @since 1.9.5
	 */
	public function do_response( $status = 'success', $message = '' ) {
		if ( 'success' !== $status ) {
			return new WP_Error( 'mail_mint_email_sending_failed', $message );
		}

		return true;
	}

	/**
	 * Sets a header with the given name and value after sanitizing them.
	 *
	 * @param string $name The name of the header.
	 * @param string $value The value of the header.
	 * @return void
	 *
	 * @since 1.9.5
	 */
	public function set_header( $name, $value ) {
		$name = sanitize_text_field( $name );

		$this->headers[ $name ] = sanitize_text_field( $value );
	}

	/**
	 * Sets the subject of the email.
	 *
	 * @param string $subject The subject of the email.
	 * @return void
	 *
	 * @since 1.9.5
	 */
	public function set_subject( $subject ) {
		$this->set_body_param(
			array(
				'subject' => $subject,
			)
		);
	}

	/**
	 * Set the request params, that goes to the body of the HTTP request.
	 *
	 * @param array $param Key=>value of what should be sent to a 3rd party mailing API.
	 * @return void
	 *
	 * @since 1.9.5
	 */
	public function set_body_param( $param ) {
		$this->body = array_merge_recursive( $this->body, $param );
	}

	/**
	 * Retrieves the default parameters for the mailer.
	 *
	 * @return array The default parameters.
	 *
	 * @since 1.9.5
	 */
	public function get_default_params() {
		return apply_filters(
			'mail_mint_mailer_default_params',
			array(
				'timeout'     => 15,
				'httpversion' => '1.1',
				'blocking'    => true,
			)
		);
	}

	/**
	 * Retrieves the body of the email.
	 *
	 * @return string The email body.
	 *
	 * @since 1.9.5
	 */
	public function get_body() {
		return apply_filters( 'mail_mint_mailer_get_body', $this->body, $this );
	}

	/**
	 * Get the email headers.
	 *
	 * @return array
	 *
	 * @since 1.9.5
	 */
	public function get_headers() {
		return apply_filters( 'mail_mint_mailer_get_headers', $this->headers, $this );
	}

	/**
	 * Retrieves the variable name as a string.
	 *
	 * @param string $variable_name The name of the variable.
	 * @return string The variable name.
	 *
	 * @since 1.9.5
	 */
	public function get_variable_string( $variable_name = '' ) {
		return $variable_name;
	}

	/**
	 * Resets the data of the mailer instance.
	 *
	 * @since 1.9.5
	 */
	public function reset_mailer_data() {
		$this->body    = array();
		$this->headers = array();
	}

	/**
	 * Check if the batch limit has been reached or not.
	 *
	 * @return boolean
	 *
	 * @since 1.9.5
	 */
	public function is_batch_limit_reached() {
		return $this->current_batch_size >= $this->batch_limit;
	}

	/**
	 * Converts tags to mailer tags.
	 *
	 * @param string $string The string to convert.
	 * @return string The converted string.
	 *
	 * @since 1.9.5
	 */
	public function convert_tags_to_mailer_tags( $string = '' ) {
		return $string;
	}

	/**
	 * Send batch email
	 *
	 * @since 1.9.5
	 */
	public function send_batch() {
		$response = $this->send_email();
		return $response;
	}

	/**
	 * Clear mailer data
	 *
	 * @since 1.9.5
	 */
	public function clear_email_data() {
		// Clear mailer specific data.
	}

	/**
	 * Handle throttling
	 *
	 * @return void
	 *
	 * @since 1.9.5
	 */
	public function handle_throttling() {
		// Add ESP specific throttling logic here.
		// Should be override in the ESP mailer class.
	}

	/**
	 * Get account URL
	 *
	 * @return string
	 *
	 * @since 1.9.5
	 */
	public function get_account_url() {
		return $this->account_url;
	}
}
