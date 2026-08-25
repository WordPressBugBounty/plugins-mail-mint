<?php
/**
 * Class AbandonedCartRunScheduler
 *
 * Handles the WooCommerce "ABANDONED_CART_SCHEDULER" hook.
 * This class is responsible for handling mint_handle_abandoned_cart_user_status.
 *
 * @package Mint\MRM\Internal\AbandonedCart\Scheduler
 * @since 1.5.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Scheduler;

use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartModel;

/**
 * Class AbandonedCartRunScheduler
 *
 * Represents a scheduler for running abandoned cart tasks.
 */
class AbandonedCartRunScheduler {

	/**
	 * AbandonedCartRunScheduler constructor.
	 *
	 * Initializes a new instance of the AbandonedCartRunScheduler class.
	 *
	 * @since 1.5.0
	 */
	public function __construct() {
		add_action( ABANDONED_CART_SCHEDULER, array( $this, 'mint_handle_abandoned_cart_user_status' ), 10 );
		add_action( CART_CREATION_SCHEDULER, array( $this, 'update_abandoned_cart_status_to_lost' ), 10 );
	}


	/**
	 * Handle the abandoned cart user status and perform necessary actions.
	 *
	 * @param array $data The data containing the user's ID, email, and cookie.
	 * @return bool|int|void Returns nothing.
	 * @since 1.5.0
	 */
	public function mint_handle_abandoned_cart_user_status( $data ) {
		if ( empty( $data ) || !is_array( $data ) ) {
			return;
		}
		// Extract the abandoned cart ID and session key from the data array.
		$id          = !empty( $data['id'] ) ? $data['id'] : 0;
		$session_key = !empty( $data['session_key'] ) ? $data['session_key'] : 0;

		// Get the cart details based on the ID and status.
		$cart_details = CartModel::get_cart_details_by_key( 'session_key', $session_key, 'pending' );

		// If the cart details are empty, return.
		if ( empty( $cart_details ) ) {
			return;
		}

		// Extract the user email from the cart_details array.
		$email = !empty( $cart_details['email'] ) ? $cart_details['email'] : '';

		// If the email is empty, delete the corresponding user model and return.
		if ( !$email ) {
			return CartModel::delete( $id );
		}

		// If the cart status and ID are set, perform the necessary actions.
		if ( isset( $cart_details['status'], $cart_details['id'] ) ) {
			/**
			 * Fires before the Cart has Abandoned before wait period.
			 *
			 * @since 1.5.0
			 *
			 * @param string  $id Abandoned ID.
			 */
			do_action( 'mailmint_before_cart_abandoned', $id );

			// Update the status of the cart to 'abandoned'.
			$updated_status = CartModel::update_status( 'status', 'abandoned', array( 'id' => $id ) );

			// If the status update fails, return.
			if ( !$updated_status ) {
				return;
			}

			// Create a mint user after the cart is abandoned.
			$this->create_mint_user_after_abandoned( $cart_details );

			/**
			 * Fires after the Cart has Abandoned after wait period.
			 *
			 * @since 1.5.0
			 *
			 * @param string  $id Abandoned ID.
			 */
			do_action( 'mailmint_after_cart_abandoned', $id );
		}
	}

	/**
	 * Create a Mint user after the cart is abandoned.
	 *
	 * @param array $cart_details The details of the abandoned cart.
	 * @return void Returns nothing.
	 * @since 1.5.0
	 */
	public function create_mint_user_after_abandoned( $cart_details ) {
		// If the cart details are empty, return.
		if ( empty( $cart_details ) || empty( $cart_details['email'] ) ) {
			return;
		}

		$checkout_data = isset( $cart_details['checkout_data'] ) ? maybe_unserialize( $cart_details['checkout_data'] ) : array();

		// Get the abandoned cart settings.
		$setting = CartCommon::get_abandoned_cart_settings();

		// Extract the lists and tags from the settings.
		$lists = !empty( $setting['lists'] ) ? array_column( $setting['lists'], 'id' ) : array();
		$tags  = !empty( $setting['tags'] ) ? array_column( $setting['tags'], 'id' ) : array();

		/*
		 * Use the "Status for New Contacts" cart setting rather than the site's double
		 * opt-in setting.
		 *
		 * This address was typed into a checkout field, not a signup form, so there is no
		 * marketing consent to infer from it — but there is also no confirmation flow that
		 * would ever move it out of 'pending', which is how cart recovery used to go
		 * silent on double opt-in sites. The default, 'transactional', keeps the contact
		 * out of every campaign audience while still letting a Send Email step marked
		 * transactional reach them.
		 *
		 * This only applies to contacts this capture creates: mailmint_create_single_contact()
		 * routes an existing contact through mailmint_resolve_contact_status(), which
		 * refuses to narrow an already-known status to 'transactional'.
		 */
		$status = CartCommon::get_new_contact_status();

		// Prepare the contact arguments for creating a Mint user.
		$contact_args = array(
			'email'      => !empty( $cart_details['email'] ) ? $cart_details['email'] : '', // required.
			'status'     => $status,      // transactional/subscribed.
			'lists'      => $lists,       // list IDs as an array.
			'tags'       => $tags,
			'first_name' => isset($checkout_data['fields']['billing_first_name']) ? $checkout_data['fields']['billing_first_name'] : (isset($checkout_data['fields']['billing-first_name']) ? $checkout_data['fields']['billing-first_name'] : ''),
			'last_name'  => isset($checkout_data['fields']['billing_last_name']) ? $checkout_data['fields']['billing_last_name'] : (isset($checkout_data['fields']['billing-last_name']) ? $checkout_data['fields']['billing-last_name'] : ''),
			'source'     => 'WC Cart Abandoned',
		);

		// Create a single contact using the contact arguments.
		mailmint_create_single_contact( $contact_args );
	}

	/**
	 * Summary: Handles the cart creation time for abandoned carts.
	 *
	 * Description: Updates the status of the abandoned cart with the specified ID to 'lost' in the Model.
	 *
	 * @access public
	 *
	 * @param array $payload An array containing the payload data, including the abandoned cart ID.
	 *
	 * @return void
	 * @since 1.5.0
	 */
	public function update_abandoned_cart_status_to_lost( $payload ) {
		// Extract the abandoned cart ID and session key from the data array.
		$id          = !empty( $payload['id'] ) ? $payload['id'] : 0;
		$cart_detail = CartModel::get_cart_details_by_key_and_status( 'id', $id, array( 'pending', 'abandoned' ) );
		if ( empty( $cart_detail ) ) {
			return;
		}
		/**
		 * Execute the 'mailmint_before_abandoned_cart_lost' action hook.
		 *
		 * This function triggers the 'mailmint_before_abandoned_cart_lost' action, allowing other
		 * functions or plugins to perform additional tasks when an abandoned cart is considered lost.
		 *
		 * @param mixed $payload Optional. Additional data or parameters passed to the hooked functions. Default is null.
		 *                       Developers can use this parameter to pass custom data to the hooked functions.
		 * @return void
		 * @since 1.5.0
		 */
		do_action( 'mailmint_before_abandoned_cart_lost', $payload );
		CartModel::update_status( 'status', 'lost', array( 'id' => $id ) );

		/**
		 * Execute the 'mailmint_after_abandoned_cart_lost' action hook.
		 *
		 * This function triggers the 'mailmint_after_abandoned_cart_lost' action, allowing other
		 * functions or plugins to perform additional tasks when an abandoned cart is considered lost.
		 *
		 * @param mixed $payload Optional. Additional data or parameters passed to the hooked functions. Default is null.
		 *                       Developers can use this parameter to pass custom data to the hooked functions.
		 * @return void
		 * @since 1.5.0
		 */
		do_action( 'mailmint_after_abandoned_cart_lost', $payload );
	}
}


