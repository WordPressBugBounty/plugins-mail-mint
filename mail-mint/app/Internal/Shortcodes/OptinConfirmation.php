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

/**
 * [Manages plugin's contact form shortcodes]
 *
 * @desc Manages plugin's contact form shortcodes
 * @package /app/Internal/Shortcodes
 * @since 1.0.0
 */
class OptinConfirmation {

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
				'text'  => __( 'Subscription Confirmed. Thank you.', 'mrm' ),
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
		ob_start();?>
		<div class="optin-confirmation-shortcode <?php echo esc_html( $this->attributes['class'] ); ?>">
			<?php echo esc_html( $this->attributes['text'] ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
