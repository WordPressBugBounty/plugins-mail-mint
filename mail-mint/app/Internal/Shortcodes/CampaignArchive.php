<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/Internal/Shortcodes
 * @since 1.24.0
 */

namespace Mint\MRM\Internal\ShortCode;

use Mint\MRM\Database\Repositories\CampaignRepository;

/**
 * Renders a public list of past (sent) campaigns flagged for the archive.
 *
 * Usage: [mailmint_archive limit="50" subject_contains="news" start_date="2026-01-01" end_date="2026-06-30" in_the_last_days="30"]
 * All attributes are optional; a bare [mailmint_archive] lists the most recent
 * archive-enabled campaigns up to the default limit.
 *
 * @package /app/Internal/Shortcodes
 * @since 1.24.0
 */
class CampaignArchive {

	/**
	 * Shortcode attributes.
	 *
	 * @var array
	 * @since 1.24.0
	 */
	protected $attributes = array();

	/**
	 * Initializes class functionalities.
	 *
	 * @param array $attributes Shortcode attributes.
	 * @since 1.24.0
	 */
	public function __construct( $attributes = array() ) {
		$this->attributes = $this->parse_attributes( $attributes );
	}

	/**
	 * Get shortcode attributes.
	 *
	 * @return array
	 * @since 1.24.0
	 */
	public function get_attributes() {
		return $this->attributes;
	}

	/**
	 * Parses and normalises shortcode attributes.
	 *
	 * @param array $attributes Shortcode attributes.
	 *
	 * @return array
	 * @since 1.24.0
	 */
	protected function parse_attributes( $attributes ) {
		return shortcode_atts(
			array(
				'limit'            => 100,
				'subject_contains' => '',
				'start_date'       => '',
				'end_date'         => '',
				'in_the_last_days' => 0,
			),
			$attributes
		);
	}

	/**
	 * Render the archive list markup.
	 *
	 * @return string
	 * @since 1.24.0
	 */
	public function get_content() {
		$repository = new CampaignRepository();
		$campaigns  = $repository->getArchivedPublicCampaigns( $this->get_attributes() );

		if ( empty( $campaigns ) ) {
			/**
			 * Filter the message shown when the archive has no campaigns.
			 *
			 * @since 1.24.0
			 * @param string $message Default empty-state message.
			 */
			return apply_filters(
				'mail_mint_archive_no_campaigns',
				esc_html__( 'Oops! There are no emails to display.', 'mrm' )
			);
		}

		$date_format = get_option( 'date_format' );
		$html        = '';

		/**
		 * Filter the optional archive list title.
		 *
		 * @since 1.24.0
		 * @param string $title Default empty title.
		 */
		$title = apply_filters( 'mail_mint_archive_title', '' );
		if ( ! empty( $title ) && is_scalar( $title ) ) {
			$html .= '<h3 class="mailmint_archive_title">' . esc_html( (string) $title ) . '</h3>';
		}

		$html .= '<ul class="mailmint_archive">';
		foreach ( $campaigns as $campaign ) {
			$url     = $repository->getArchiveUrl( (int) $campaign['id'] );
			$subject = ! empty( $campaign['email_subject'] ) ? $campaign['email_subject'] : ( $campaign['title'] ?? '' );
			$date    = ! empty( $campaign['updated_at'] ) ? date_i18n( $date_format, strtotime( $campaign['updated_at'] ) ) : '';

			$subject_html = $url
				? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $subject ) . '</a>'
				: esc_html( $subject );

			$html .= '<li>' .
				'<span class="mailmint_archive_date" style="margin-right:8px;">' . esc_html( $date ) . '</span>' .
				'<span class="mailmint_archive_subject">' . $subject_html . '</span>' .
				'</li>';
		}
		$html .= '</ul>';

		return $html;
	}
}
