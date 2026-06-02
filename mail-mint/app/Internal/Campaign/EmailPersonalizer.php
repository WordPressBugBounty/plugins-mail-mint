<?php
/**
 * Stateless email post-processing service.
 *
 * Handles the shared email post-processing steps (tracking pixel injection,
 * preview text injection, watermark insertion, RTL modification) common to
 * both the campaign send loop and the automation send path.
 *
 * Zero DB queries in personalizeBody(). All inputs via parameters.
 * No constructor dependencies, no internal mutable state.
 *
 * @package Mint\MRM\Internal\Campaign
 * @since 1.20.0
 */

namespace Mint\MRM\Internal\Campaign;

use MailMint\App\Helper;
use Mint\MRM\Utilites\Helper\Email;
use MintMailPro\Mint_Pro_Helper;
use MRM\Common\MrmCommon;
use Mint\MRM\API\Actions\ComplianceAction;

/**
 * Class EmailPersonalizer
 *
 * @since 1.20.0
 */
class EmailPersonalizer {

	/**
	 * Apply post-processing to email body in fixed order:
	 * (a) tracking pixel → (b) preview text → (c) watermark → (d) RTL.
	 *
	 * Performs zero database queries. All data is passed as parameters.
	 *
	 * @param string $body         The email body HTML.
	 * @param string $email_hash   Unique hash for tracking pixel.
	 * @param string $preview_text Preview/preheader text.
	 * @param string $editor_type  Editor type: 'advanced-builder', 'plain-text-editor', etc.
	 * @param string $watermark    Watermark HTML string (already filtered by caller). Empty to skip.
	 *
	 * @return string Fully processed email body.
	 * @since 1.20.0
	 */
	public function personalizeBody( string $body, string $email_hash, string $preview_text, string $editor_type, string $watermark ): string {
		// (a) Inject tracking pixel — bake mode into URL so it's frozen to send-time consent.
		$open_tracking_mode = ComplianceAction::get_open_tracking_mode();
		if ( 'no' !== $open_tracking_mode ) {
			$body = Email::inject_tracking_image_on_email_body( $email_hash, $body, $open_tracking_mode );
		}

		// (b) Inject preview text.
		$body = Email::inject_preview_text_on_email_body( $preview_text, $body );

		// (c) Insert watermark based on editor type (skip if empty).
		if ( '' !== $watermark ) {
			if ( 'advanced-builder' === $editor_type ) {
				$body = str_replace( '</html>', $watermark . '</html>', $body );
			} else {
				$body = $body . $watermark;
			}
		}

		// (d) Apply RTL modification.
		$body = Helper::modify_email_for_rtl( $body );

		return $body;
	}

	/**
	 * Build complete email headers array.
	 *
	 * Merges caller-provided base headers with X-PreHeader and conditionally
	 * adds List-Unsubscribe + List-Unsubscribe-Post based on the
	 * mail_mint_enable_unsubscribe_header filter.
	 *
	 * @param string $preview_text     Preview/preheader text.
	 * @param string $email_hash       Unique hash for unsubscribe URL generation.
	 * @param array  $existing_headers Existing headers array from the caller.
	 *
	 * @return array Complete headers array.
	 * @since 1.20.0
	 */
	public function buildHeaders( string $preview_text, string $email_hash, array $existing_headers ): array {
		$headers   = $existing_headers;
		$headers[] = 'X-PreHeader: ' . $preview_text;

		/** This filter is documented in app/Utilities/Helper/Email.php */
		if ( apply_filters( 'mail_mint_enable_unsubscribe_header', true, $headers ) ) {
			$unsubscribe_url = Helper::get_unsubscribed_url( $email_hash );
			$headers[]       = 'List-Unsubscribe: <' . $unsubscribe_url . '>';
			$headers[]       = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		return $headers;
	}

	/**
	 * Apply Pro-specific post-processing (lead magnet tracking).
	 *
	 * Guarded by is_mailmint_pro_active() and version compatibility check.
	 * Returns body unchanged when Pro is inactive or incompatible.
	 *
	 * @param string $body            The email body HTML.
	 * @param string $recipient_email The recipient's email address.
	 *
	 * @return string Processed email body (unchanged if Pro inactive).
	 * @since 1.20.0
	 */
	public function applyProProcessing( string $body, string $recipient_email ): string {
		if ( MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.15.1' ) ) {
			$body = Mint_Pro_Helper::process_lead_magnet_tracking( $body, $recipient_email );
		}

		return $body;
	}
}
