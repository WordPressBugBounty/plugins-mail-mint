<?php
/**
 * EmailTrigger
 *
 * This class or function is responsible for triggering emails in the application.
 * It is part of the Mail Mint package and is maintained by the WPFunnels Team.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2022-10-07 09:30:00
 * @modify date 2022-10-07 11:03:17
 * @package /app/API/Actions/Admin
 */

namespace Mint\App\Internal\EmailCustomization\WooCommerce;

use Mint\App\Database\Repositories\Email\Template;
use Mint\App\Internal\EmailCustomization\Render\EmailRender;

class EmailTrigger {

	/**
	 * Holds the ID of the email.
	 *
	 * @var string $email_id
	 *
	 * @since 1.10.0
	 */
	private $email_id;

	/**
	 * Holds the email template.
	 *
	 * @var string $template
	 *
	 * @since 1.10.0
	 */
	private $template;

	/**
	 * Holds the object.
	 *
	 * @var string $object
	 *
	 * @since 1.10.0
	 */
	private $object;

	/**
	 * Holds the object type.
	 *
	 * @var string $object_type
	 *
	 * @since 1.10.0
	 */
	private $object_type;

	/**
	 * Holds the email repository.
	 *
	 * @var object $email_repository
	 *
	 * @since 1.10.0
	 */
	private $email_repository;

	public function __construct() {
		$this->email_repository = new Template();
		add_action( 'woocommerce_email', array( $this, 'register_email_hooks' ), PHP_INT_MAX );
		add_filter( 'woocommerce_mail_content', array( $this, 'set_email_content' ), PHP_INT_MAX );
	}

	/**
	 * Registers email hooks for WooCommerce emails.
	 *
	 * This method takes an email object as a parameter. If the email object contains an array of emails,
	 * it loops through each email and adds a filter for the recipient of the email. The filter triggers
	 * the 'set_email_template_for_recipient' method of this class.
	 *
	 * @param object $email_object The email object containing the emails to register hooks for.
	 * @since 1.10.0
	 */
	public function register_email_hooks( $email_object ) {
		if ( is_array( $email_object->emails ) ) {
			foreach ( $email_object->emails as $email ) {
				add_filter( 'woocommerce_email_recipient_' . $email->id, array( $this, 'set_email_template_for_recipient' ), 20, 3 );
				add_filter( 'woocommerce_email_subject_' . $email->id, array( $this, 'set_email_subject' ) );
			}
		}
		add_filter( 'woocommerce_email_recipient_customer_partially_refunded_order', array( $this, 'set_email_template_for_recipient' ), 20, 3 );
	}

	/**
	 * Sets the email template for a recipient.
	 *
	 * This method retrieves a custom WooCommerce email template based on the email ID.
	 * If a template is found, it sets the email ID, template, object, and object type properties of the class.
	 *
	 * @param string $recipient The recipient of the email.
	 * @param object $object The object related to the email, can be a \WP_User or an order.
	 * @param object $email The email object.
	 * @return string The recipient of the email.
	 *
	 * @since 1.10.0
	 */
	public function set_email_template_for_recipient( $recipient, $object, $email ) {
		$email_template = $this->email_repository->get_custom_wc_email_template( $email->id );

		if ( !empty( $email_template ) ) {
			$this->email_id    = $email->id;
			$this->template    = $email_template;
			$this->object      = $object;
			$this->object_type = ( $object instanceof \WP_User ) ? 'user' : 'order';
		}

		return $recipient;
	}

	/**
	 * Sets the email subject.
	 *
	 * This method checks the type of the object associated with the email.
	 * If the object is an instance of \WC_Order, it sets the 'order_object' and 'user_object' in the email data.
	 *
	 * @param string $subject The original subject of the email.
	 * @return string The original subject of the email.
	 *
	 * @since 1.10.0
	 */
	public function set_email_subject( $subject ) {
		$email_object_data = array();

		if ( $this->object instanceof \WC_Order ) {
			$email_object_data = array(
				'order_object' => $this->object,
				'user_object'  => $this->object->get_user(),
			);
		}

		if ( $this->object instanceof \WP_User ) {
			$email_object_data = array(
				'user_object' => $this->object,
			);
		}

		return $subject;
	}

	/**
	 * Sets the content of the email.
	 *
	 * This method checks if the template property of the class is set and not empty.
	 * If it is, it replaces the input message with the content of the template.
	 *
	 * @param string $message The original message of the email.
	 * @return string The new message of the email. If the template was set and not empty,
	 *                it's the content of the template. Otherwise, it's the original message.
	 * @since 1.10.0
	 * @since 1.11.1 Disable WP HTML Mail template.
	 */
	public function set_email_content( $message ) {
		if ( !empty( $this->template['template'] ) && $this->template['customize_enable'] ) {
			// Disable WP HTML Mail template.
			if( is_plugin_active( 'wp-html-mail/wp-html-mail.php' ) ) {
				add_filter( 'haet_mail_use_template', function( $use_template, $mail ) {
					return false;
				}, PHP_INT_MAX, 2 );
			}

			$email_render = new EmailRender(
				array(
					'object_type' => $this->object_type,
					'object'      => $this->object,
					'render_type' => 'wc_action',
					'template'    => $this->template['template'],
				)
			);

			$rendered = $email_render->render();
			if ( !empty( $rendered ) ) {
				$message = $rendered;
			}
		}
		return $message;
	}
}
