<?php
/**
 * Handles the public campaign archive "view in browser" page.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.24.0
 */

namespace Mint\MRM\Internal\Optin;

use Mint\MRM\Database\Repositories\CampaignRepository;
use Mint\MRM\Database\Enums\CampaignStatus;
use Mint\MRM\Internal\Parser\Parser;
use MRM\Common\MrmCommon;

/**
 * CampaignArchiveView class.
 *
 * Renders an archive-enabled campaign email as a full HTML page so that
 * visitors browsing a `[mailmint_archive]` list can read past emails. Unlike
 * the per-recipient EmailPreview route, this is keyed by a campaign-level
 * archive hash and renders the email generically — no contact context, so
 * merge tags resolve to their defaults.
 *
 * URL format: /?mrm=1&route=campaign_archive&hash={archive_hash}
 *
 * @since 1.24.0
 */
class CampaignArchiveView {

	/**
	 * Constructor.
	 *
	 * @since 1.24.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'handle_archive_view' ), 9999 );
	}

	/**
	 * Detect and handle the public campaign archive route.
	 *
	 * Bails early when the request is not an archive route. Renders the email
	 * as a full HTML page and exits on success.
	 *
	 * @since 1.24.0
	 */
	public function handle_archive_view() {
		$get = MrmCommon::get_sanitized_get_post();
		$get = isset( $get['get'] ) ? $get['get'] : array();

		if ( ! isset( $get['mrm'] ) || ! isset( $get['route'] ) || 'campaign_archive' !== $get['route'] ) {
			return;
		}

		$hash = isset( $get['hash'] ) ? sanitize_text_field( $get['hash'] ) : '';

		if ( empty( $hash ) ) {
			wp_die( esc_html__( 'Invalid archive link.', 'mrm' ) );
		}

		$repository = new CampaignRepository();
		$campaign   = $repository->findByArchiveHash( $hash );

		// Only sent regular campaigns explicitly enabled for the archive are viewable.
		if (
			empty( $campaign ) ||
			CampaignStatus::ARCHIVED !== ( $campaign['status'] ?? '' ) ||
			'1' !== $repository->getMeta( (int) $campaign['id'], 'show_in_archive' )
		) {
			wp_die( esc_html__( 'This archived email is no longer available.', 'mrm' ) );
		}

		// A regular campaign has a single email step — the first one (email_index 0).
		$emails = $repository->getCampaignEmails( (int) $campaign['id'] );
		$email  = ! empty( $emails ) ? reset( $emails ) : array();

		$email_body    = $email['body_data'] ?? '';
		$email_subject = $email['email_subject'] ?? '';

		if ( empty( $email_body ) ) {
			wp_die( esc_html__( 'This archived email is no longer available.', 'mrm' ) );
		}

		// Render generically: no recipient context, so merge tags resolve to defaults.
		$email_body    = Parser::parse( $email_body, array() );
		$email_subject = Parser::parse( $email_subject, array() );

		// The visitor is already viewing in browser — neutralise the tag.
		$email_body = str_replace( '{{link.view_in_browser}}', '#', $email_body );

		$share_visibility = $repository->getMeta( (int) $campaign['id'], 'share_visibility' );
		$archive_url      = $repository->getArchiveUrl( (int) $campaign['id'] );

		$data = array(
			'email_body'       => $email_body,
			'email_subject'    => $email_subject,
			'show_share_bar'   => 'private' !== $share_visibility,
			'archive_url'      => $archive_url,
		);

		/**
		 * Filter the data passed to the public campaign archive view template.
		 *
		 * @since 1.24.0
		 *
		 * @param array $data     Template data (email_body, email_subject).
		 * @param array $campaign Campaign row.
		 */
		$data = apply_filters( 'mail_mint_campaign_archive_view_data', $data, $campaign );

		$this->render( $data );
		exit;
	}

	/**
	 * Include the browser view template.
	 *
	 * @param array $data Template data.
	 * @since 1.24.0
	 */
	private function render( array $data ) {
		include MRM_DIR_PATH . 'app/Views/email_browser_view.php';
	}
}
