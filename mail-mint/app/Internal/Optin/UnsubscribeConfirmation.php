<?php
/**
 * Handles unsubscription process
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Internal\Optin;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\EmailModel;
use MRM\Common\MrmCommon;

/**
 * UnsubscribeConfirmation class.
 *
 * @since 1.1.0
 */
class UnsubscribeConfirmation {

	/**
	 * OptinConfirmation constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'unsubscribe_confirmation_page' ), 9999 );
		add_action( 'init', array( $this, 'unsubscribe_confirmation' ), 9999 );
	}

	/**
	 * Summary: Unsubscribe confirmation page handler.
	 *
	 * Description: This method handles the logic for the unsubscribe confirmation page.
	 * It checks the GET parameters to determine if it's a valid unsubscribe route.
	 *
	 * @access public
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @modified 1.7.1 Apply DRY principle by creating two functions.
	 */
	public function unsubscribe_confirmation_page() {
		// Get sanitized GET parameters.
		$get = MrmCommon::get_sanitized_get_post();
		$get = isset( $get[ 'get' ] ) ? $get[ 'get' ] : array();

		// Check if the route is 'unsubscribe'.
		if ( !isset( $get['mrm'] ) || !isset( $get['route'] ) || 'unsubscribe' !== $get['route'] ) {
			return;
		}

		// Retrieve the hash from the URL.
		$hash = isset( $get[ 'hash' ] ) ? $get[ 'hash' ] : '';

		// Process the unsubscribe confirmation.
		$this->process_unsubscribe( $hash );
	}

	/**
	 * Process the unsubscribe confirmation.
	 *
	 * This method processes the unsubscribe confirmation based on the configuration settings.
	 *
	 * @access public
	 *
	 * @param string $hash The unsubscribe confirmation hash.
	 *
	 * @return void
	 *
	 * @since 1.14.0
	 */
	public function process_unsubscribe( $hash ){
		// Get the contact ID associated with the hash.
		$contact_hash = EmailModel::get_contact_id_by_hash( $hash );

		if (empty($contact_hash)) {
			$contact = ContactModel::get_by_hash($hash);
			$contact_hash = $contact;
		}

		$contact_id = isset($contact_hash['contact_id']) ? $contact_hash['contact_id'] : $contact_hash['id'];

		// Get compliance and unsubscribe settings.
		$compliance = get_option('_mint_compliance');
		$one_click  = isset($compliance['one_click_unsubscribe']) ? $compliance['one_click_unsubscribe'] : 'no';
		$settings   = get_option('_mrm_general_unsubscriber_settings');

		// Process redirection or one-click confirmation based on configuration and contact status.
		if ('no' === $one_click) {
			$this->process_redirect_confirmation($hash, $settings);
		} elseif ('yes' === $one_click) {
			$this->process_one_click_confirmation($hash, $settings, $contact_id);
		}
	}

	/**
	 * Summary: Process redirection-based unsubscribe confirmation.
	 *
	 * Description: This method processes the unsubscribe confirmation using a redirection-based approach.
	 *
	 * @access public
	 *
	 * @param string $hash     The unsubscribe confirmation hash.
	 * @param array  $settings Unsubscribe confirmation settings.
	 *
	 * @return void
	 *
	 * @since 1.7.1
	 */
	public function process_redirect_confirmation( $hash, $settings ) {
		$default_page_id = MrmCommon::get_page_id_by_slug( 'unsubscribe_confirmation' );
		$page_id         = isset( $settings[ 'confirmation_page_id' ] ) ? $settings[ 'confirmation_page_id' ] : $default_page_id;
		$redirect_url    = $page_id ? get_permalink( $page_id ) : '';

		// Perform a WordPress redirect to the confirmation page.
		exit( wp_redirect( esc_url( add_query_arg( 'hash', $hash, $redirect_url ) ) ) ); //phpcs:ignore
	}

	/**
	 * Summary: Process one-click confirmation with redirection.
	 *
	 * Description: This method processes the one-click unsubscribe confirmation with redirection.
	 *
	 * @access public
	 *
	 * @param string $hash        The unsubscribe confirmation hash.
	 * @param array  $settings    Unsubscribe confirmation settings.
	 * @param int    $contact_id  The ID of the contact associated with the confirmation.
	 *
	 * @return void
	 *
	 * @since 1.7.1
	 */
	public function process_one_click_confirmation( $hash, $settings, $contact_id ) {
		$confirmation_type  = isset( $settings[ 'confirmation_type' ] ) ? $settings[ 'confirmation_type' ] : 'redirect-page';
		$broadcast_email_id = EmailModel::get_broadcast_email_by_hash( $hash );

		if ( 'redirect' === $confirmation_type || 'redirect-page' === $confirmation_type ) {

			// update the contact's subscription status.
			ContactModel::update_subscription_status( $contact_id, 'unsubscribed' );
			EmailModel::insert_or_update_email_meta( 'is_unsubscribe', 1, $broadcast_email_id );

			// if redirecting to a page, get the page's URL.
			if ( 'redirect-page' === $confirmation_type ) {
				$page_id      = isset( $settings[ 'page_id' ] ) ? $settings[ 'page_id' ] : '';
				$redirect_url = $page_id ? get_permalink( $page_id ) : home_url();
			}

			// if no page URL is available, get the URL from settings.
			if ( empty( $redirect_url ) ) {
				$redirect_url = isset( $settings[ 'url' ] ) ? esc_url( $settings[ 'url' ] ) : false;
			}

			// check if the URL is valid before redirecting.
			if ( $redirect_url && MrmCommon::is_valid_url( $redirect_url ) ) {
				exit( wp_redirect( $redirect_url ) ); //phpcs:ignore
			}
		}
	}

	/**
	 * Summary: Unsubscribe confirmation handler.
	 *
	 * Description: This method serves as the handler for the unsubscribe confirmation.
	 *
	 * @access public
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @modified 1.7.1 Remove duplicate code.
	 */
	public function unsubscribe_confirmation() {
		// get sanitized GET request data.
		$get = MrmCommon::get_sanitized_get_post();
		$get = isset( $get[ 'get' ] ) ? $get[ 'get' ] : array();

		// check if the correct route is accessed.
		if ( isset( $get[ 'mrm' ] ) && isset( $get[ 'route' ] ) && 'unsubscribe-confirmation' === $get[ 'route' ] ) {
			// get the contact's unique hash.
			$hash = isset( $get[ 'hash' ] ) ? $get[ 'hash' ] : '';

			// get the contact's ID by their unique hash.
			$contact_id = EmailModel::get_contact_id_by_hash( $hash );
			$contact_id = isset( $contact_id[ 'contact_id' ] ) ? $contact_id[ 'contact_id' ] : false;

			if ( empty( $contact_id ) ) {
				$contact    = ContactModel::get_by_hash( $hash );
				$contact_id = isset( $contact[ 'id' ] ) ? $contact[ 'id' ] : false;
			}

			// get the contact's information.
			$contact = ContactModel::get( $contact_id );
			// get the plugin's unsubscribe settings.
			$unsubscribe_settings = get_option( '_mrm_general_unsubscriber_settings' );
			$this->process_one_click_confirmation( $hash, $unsubscribe_settings, $contact_id );
		}
	}
}
