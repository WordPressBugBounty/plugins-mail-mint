<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/DataStores
 */

namespace Mint\MRM\DataStores;

/**
 * [Manage message data]
 *
 * @desc Manage plugin's assets
 * @package /app/DataStores
 * @since 1.0.0
 */
class MessageData {

	/**
	 * Email address
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $email_address;

	/**
	 * Email subject
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $email_subject;

	/**
	 * Email body
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $email_body;


	/**
	 * Message type
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $type;


	/**
	 * Contact ID
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $contact_id;


	/**
	 * Sender ID
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $sender_id;

	/**
	 * Initialize class functionalities
	 *
	 * @param array $args Custom field data.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $args ) {
		$this->email_address = isset( $args[ 'email_address' ] ) ? $args[ 'email_address' ] : '';
		$this->email_subject = isset( $args[ 'email_subject' ] ) ? $args[ 'email_subject' ] : '';
		$this->email_body    = isset( $args[ 'email_body' ] ) ? $args[ 'email_body' ] : '';
	}

	/**
	 * Return message receiver email address
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_receiver_email() {
		return $this->email_address;
	}

	/**
	 * Return email subject
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_email_subject() {
		return $this->email_subject;
	}

	/**
	 * Return email body
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_email_body() {
		return $this->email_body;
	}

	/**
	 * Return message type
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_message_type() {
		return $this->type;
	}

	/**
	 * Return receiver ID
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function get_receiver_id() {
		return $this->contact_id;
	}

	/**
	 * Return sender ID
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function get_sender_id() {
		return $this->sender_id;
	}
}
