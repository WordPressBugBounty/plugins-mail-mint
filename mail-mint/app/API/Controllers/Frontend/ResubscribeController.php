<?php
/**
 * Frontend REST controller for AJAX-based resubscribe.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.24.5
 */

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\EmailModel;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles POST mint-mail/v1/resubscribe.
 *
 * Restores an unsubscribed contact to subscribed status in place, so the
 * unsubscribe survey page can react without a full page navigation. Mirrors
 * the logic in UnsubscribeConfirmation::resubscribe_confirmation_page(),
 * which remains as the non-JS fallback for the same link.
 *
 * @since 1.24.5
 */
class ResubscribeController {

	/**
	 * Handle resubscribe request.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 * @since 1.24.5
	 */
	public function resubscribe( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$hash = isset( $params['hash'] ) ? sanitize_text_field( $params['hash'] ) : '';
		if ( empty( $hash ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid request.', 'mrm' ),
				),
				400
			);
		}

		$unsubscriber_settings = get_option( '_mrm_general_unsubscriber_settings', array() );
		if ( 'no' === ( $unsubscriber_settings['unsubscribe_allow_resubscription'] ?? 'yes' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'This action is not allowed.', 'mrm' ),
				),
				403
			);
		}

		$contact_hash = EmailModel::get_contact_id_by_hash( $hash );
		if ( empty( $contact_hash ) ) {
			$contact_hash = ContactModel::get_by_hash( $hash );
		}
		$contact_id = isset( $contact_hash['contact_id'] ) ? (int) $contact_hash['contact_id'] : ( isset( $contact_hash['id'] ) ? (int) $contact_hash['id'] : 0 );

		if ( ! $contact_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Contact not found.', 'mrm' ),
				),
				404
			);
		}

		$contact = ContactModel::get( $contact_id );
		if ( empty( $contact ) || 'unsubscribed' !== ( $contact['status'] ?? '' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'This action is not allowed.', 'mrm' ),
				),
				403
			);
		}

		ContactModel::update_subscription_status( $contact_id, 'subscribed' );
		ContactModel::delete_contact_meta_by_key( $contact_id, 'unsubscribe_reason' );
		ContactModel::delete_contact_meta_by_key( $contact_id, 'unsubscribe_reason_text' );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'redirect_url' => esc_url( add_query_arg( 'resubscribed', '1', home_url() ) ),
			),
			200
		);
	}
}
