<?php
/**
 * Handles double opt-in subscription process
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Internal\Optin;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\NoteModel;
use MRM\Common\MrmCommon;

/**
 * OptinConfirmation class.
 *
 * @since 1.1.0
 */
class OptinConfirmation {

	/**
	 * Class instance.
	 *
	 * @var OptinConfirmation instance
	 */
	protected static $instance = null;

	/**
	 * Get class instance.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * OptinConfirmation constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'double_optin_confirmation' ), 9999 );
	}


	/**
	 * Double optin confirmation by the contact
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function double_optin_confirmation() {
		$get = MrmCommon::get_sanitized_get_post();
		$get = isset( $get['get'] ) ? $get['get'] : array();

		if ( isset( $get['mrm'] ) && isset( $get['route'] ) && 'confirmation' === $get['route'] ) {
			$default           = MrmCommon::double_optin_default_configuration();
			$settings          = get_option( '_mrm_optin_settings', $default );
			$confirmation_type = isset( $settings['confirmation_type'] ) ? $settings['confirmation_type'] : '';
			// Get contact information by unique hash.
			$hash       = isset( $get['hash'] ) ? $get['hash'] : '';
			$contact    = ContactModel::get_by_hash( $hash );
			$contact_id = isset( $contact['id'] ) ? $contact['id'] : '';
			if ( $hash === $contact['hash'] ) {
				$args = array(
					'contact_id' => $contact_id,
					'status'     => 'subscribed',
				);

				// Update contact status from pending to subscribed.
				ContactModel::update( $args, $contact_id );
				do_action( 'mailmint_after_confirm_double_optin', $contact );
				// Create a note for the contact subscription.
				$note = array(
					'title'       => __( 'Subscriber double opt-in confirmed', 'mrm' ),
					'description' => __( 'Subscriber confirmed double opt-in', 'mrm' ),
					'type'        => 'note',
					'is_public'   => 1,
					'status'      => 1,
					'created_by'  => get_current_user_id(),
				);

				NoteModel::insert( $note, $contact_id );
			}

			// Reddirect to an URL or page based on confirmation type.
			if ( 'redirect' === $confirmation_type ) {
				$url = isset( $settings['url'] ) ? $settings['url'] : '';
				wp_redirect( $url ); //phpcs:ignore
				exit;
			} else {
				$page_id = isset( $settings['page_id'] ) ? $settings['page_id'] : '';
				wp_safe_redirect( get_permalink( $page_id ) );
			}
			die();
		}
	}
}
