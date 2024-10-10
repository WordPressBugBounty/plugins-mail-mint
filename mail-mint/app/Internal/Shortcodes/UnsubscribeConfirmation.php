<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Shortcodes
 */

namespace Mint\MRM\Internal\ShortCode;

use MRM\Common\MrmCommon;

/**
 * [Manages plugin's contact form shortcodes]
 *
 * @desc Manages plugin's contact form shortcodes
 * @package /app/Internal/Shortcodes
 * @since 1.0.0
 */
class UnsubscribeConfirmation {

	/**
	 * Shortcode attributes
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $attributes = array();


	/**
	 * Initializes class functionalities
	 *
	 * @param array $attributes Shortcode attributes.
	 * @since 1.0.0
	 */
	public function __construct( $attributes = array() ) {
		$this->attributes = $this->parse_attributes( $attributes );
	}


	/**
	 * Get shortcode attributes.
	 *
	 * @return array
	 * @since  1.0.0
	 */
	public function get_attributes() {
		return $this->attributes;
	}


	/**
	 * Parses shortcode attributes
	 *
	 * @param array $attributes Shortcode attributes.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function parse_attributes( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'title' => __( 'Do you want to unsubscribe?', 'mrm' ),
				'subtitle' => __( 'If you unsubscribe, you will no longer receive our weekly newsletter.', 'mrm' ),
				'button_text' => __( 'Unsubscribe', 'mrm' ),
				'cancel_text' => __( 'Cancel', 'mrm' ),
				'class' => '',
			),
			$attributes
		);

		return $attributes;
	}


	/**
	 * Get wrapper classes
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function get_wrapper_classes() {
		return array();
	}


	/**
	 * Content of opt-in form
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_content() {
		$hash      = MrmCommon::get_sanitized_get_post();
		$hash      = !empty( $hash[ 'get' ][ 'hash' ] ) ? $hash[ 'get' ][ 'hash' ] : '';
		$optin_url = add_query_arg(
			array(
				'mrm'   => 1,
				'route' => 'unsubscribe-confirmation',
				'hash'  => $hash,
			),
			home_url()
		);
		ob_start();
		?>
		<section class="mintmrm-default-pages <?php echo esc_html( $this->attributes['class'] ); ?>">
			<div class="mintmrm-card-wrapper">
				<div class="mintmrm-card">
					<div class="mintmrm-card-header">
						<svg fill="none" width="259" height="259"  viewBox="0 0 259 259" xmlns="http://www.w3.org/2000/svg"><circle cx="129.5" cy="129.5" r="129.5" fill="#F6F8FA"/><path fill="#FDC142" d="M191.813 95.5v51.75a27.334 27.334 0 01-27.312 27.313h-74.75a27.339 27.339 0 01-27.313-27.313V95.5c-.019-.73.02-1.46.115-2.185a27.319 27.319 0 0127.198-25.127h74.75a27.318 27.318 0 0127.197 25.127c.096.724.134 1.455.115 2.185z"/><path fill="#F73529" d="M170.25 191.812c15.084 0 27.312-12.228 27.312-27.312 0-15.084-12.228-27.312-27.312-27.312-15.084 0-27.312 12.228-27.312 27.312 0 15.084 12.228 27.312 27.312 27.312z"/><path fill="#fff" d="M178.875 168.812h-17.25a4.31 4.31 0 110-8.624h17.25a4.31 4.31 0 110 8.624z"/><path fill="#FFE578" d="M191.698 93.315l-51.29 28.52a27.303 27.303 0 01-26.565 0l-51.29-28.52A27.319 27.319 0 0189.75 68.188h74.75a27.319 27.319 0 0127.198 25.127z"/></svg>
					</div>
					<div class="mintmrm-card-body">
						<h1 class="mintmrm-card-title"><?php echo esc_html( $this->attributes['title'] ); ?></h1>
						<p class="mintmrm-card-subtitle"><?php echo esc_html( $this->attributes['subtitle'] ); ?></p>
						<div class="mintmrm-card-buttons">
							<a href="<?php echo esc_url( $optin_url ); ?>" class="mintmrm-card-button" role="button"><?php echo esc_html( $this->attributes['button_text'] ); ?></a>
							<a href="<?php echo esc_url( $optin_url ); ?>" class="mintmrm-card-button mintmrm-card-button-outline" id="mrm-unsubscribe-cancel" role="button"><?php echo esc_html( $this->attributes['cancel_text'] ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
