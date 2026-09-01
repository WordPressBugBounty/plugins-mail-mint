<?php
/**
 * Frontend REST controller for unsubscribe survey submission.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.20.0
 */

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\EmailModel;
use Mint\MRM\Internal\Optin\UnsubscribeReasons;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles POST mint-mail/v1/unsubscribe-survey.
 *
 * Validates the submitted reason, persists it in both:
 *  - wp_mint_contact_meta        (for contact profile display)
 *  - wp_mint_broadcast_email_meta (for campaign analytics)
 *
 * @since 1.20.0
 */
class UnsubscribeSurveyController {

	/**
	 * Handle survey form submission.
	 *
	 * @param WP_REST_Request $request Incoming REST request.
	 * @return WP_REST_Response
	 * @since 1.20.0
	 */
	public function submit( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		// Validate hash.
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

		// Resolve contact through the shared validated helper.
		// SECURITY: before 1.31.2 the token was md5( email ), so an unsubscribe reason
		// could be attributed to any contact by anyone who knew their address.
		$contact_id = MrmCommon::resolve_contact_id_by_link_hash( $hash );

		if ( ! $contact_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Contact not found.', 'mrm' ),
				),
				404
			);
		}

		// Only store reason for contacts that are actually unsubscribed.
		$contact = ContactModel::get( $contact_id );
		$status  = $contact['status'] ?? '';

		if ( ! empty( $contact ) && 'subscribed' === $status ) {
			// Contact resubscribed (e.g. via the "Changed your mind?" link) since landing on this page.
			return new WP_REST_Response(
				array(
					'success'            => false,
					'already_subscribed' => true,
					'message'            => __( "You're already resubscribed - no need to tell us why you left!", 'mrm' ),
				),
				409
			);
		}

		if ( empty( $contact ) || 'unsubscribed' !== $status ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'This action is not allowed.', 'mrm' ),
				),
				403
			);
		}

		// Validate reason.
		$reason = isset( $params['reason'] ) ? sanitize_key( $params['reason'] ) : '';
		if ( ! UnsubscribeReasons::is_valid_reason( $reason ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid reason selected.', 'mrm' ),
				),
				400
			);
		}

		// Sanitize optional free-text (only stored when reason='other' and setting allows it).
		$unsubscriber_settings = get_option( '_mrm_general_unsubscriber_settings', array() );
		$allow_other_text      = 'yes' === ( $unsubscriber_settings['unsubscribe_survey_allow_other_text'] ?? 'no' );
		$reason_text       = '';
		if ( 'other' === $reason && $allow_other_text && ! empty( $params['reason_text'] ) ) {
			$reason_text = mb_substr( trim( wp_strip_all_tags( $params['reason_text'] ) ), 0, 500 );
		}

		// --- Dual-write ---

		// 1. Contact meta (profile page).
		$meta_fields = array( 'unsubscribe_reason' => $reason );
		if ( '' !== $reason_text ) {
			$meta_fields['unsubscribe_reason_text'] = $reason_text;
		}
		ContactModel::update_meta_fields( $contact_id, array( 'meta_fields' => $meta_fields ) );

		// 2. Broadcast email meta (campaign analytics).
		$broadcast_email_id = EmailModel::get_broadcast_email_by_hash( $hash );
		if ( $broadcast_email_id ) {
			EmailModel::insert_or_update_email_meta( 'unsubscribe_reason', $reason, $broadcast_email_id );
		}

		// Determine redirect URL.
		$settings     = get_option( '_mrm_general_unsubscriber_settings', array() );
		$redirect_url = $this->get_after_unsubscribe_url( $settings );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'redirect_url' => $redirect_url,
			),
			200
		);
	}

	/**
	 * Resolves the after-unsubscribe redirect URL from plugin settings.
	 *
	 * @param array $settings Value of _mrm_general_unsubscriber_settings.
	 * @return string
	 * @since 1.20.0
	 */
	private function get_after_unsubscribe_url( array $settings ): string {
		$type = isset( $settings['confirmation_type'] ) ? $settings['confirmation_type'] : 'redirect-page';

		if ( 'redirect-page' === $type && ! empty( $settings['page_id'] ) ) {
			$url = get_permalink( $settings['page_id'] );
			if ( $url ) {
				return $url;
			}
		}

		if ( 'redirect' === $type && ! empty( $settings['url'] ) ) {
			return esc_url( $settings['url'] );
		}

		return home_url();
	}
}
