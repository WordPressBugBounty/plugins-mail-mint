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

use MailMint\App\Helper;

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
				'title'    => __( 'Thank You', 'mrm' ),
				'subtitle' => __( 'Your subscription is now successfully confirmed.', 'mrm' ),
				'class'    => '',
				'svg'	   => '<svg fill="none" width="259" height="259" viewBox="0 0 259 259" xmlns="http://www.w3.org/2000/svg"><circle cx="129.5" cy="129.5" r="129.5" fill="#F6F8FA"/><path fill="#FF9D00" d="M196.014 121.415v63.34c0 7.871-6.388 14.258-14.259 14.258H77.258c-7.87 0-14.258-6.387-14.258-14.258v-63.34l56.467-56.25c5.552-5.553 14.554-5.553 20.134 0l56.413 56.25z"/><path fill="#F4EEE6" d="M88.418 76.889h82.342c7.87 0 14.258 6.388 14.258 14.258v82.342c0 7.87-6.388 14.258-14.258 14.258H88.418c-7.87 0-14.258-6.388-14.258-14.258v-82.37c0-7.87 6.388-14.23 14.258-14.23z"/><path fill="#FFBE1D" d="M196.014 121.415v63.34c0 7.87-6.388 14.258-14.259 14.258H77.258c-7.87 0-14.258-6.388-14.258-14.258v-63.151l66.413 66.412 66.601-66.601z"/><path fill="#FFE000" d="M191.891 194.809a14.349 14.349 0 01-8.598 4.151l-106.518.053a14.333 14.333 0 01-9.623-4.204l52.289-52.289c5.553-5.553 14.582-5.553 20.161 0l52.289 52.289z"/><path fill="#00BF00" d="M129.519 131.362c13.115 0 23.746-10.632 23.746-23.746 0-13.115-10.631-23.746-23.746-23.746-13.114 0-23.746 10.631-23.746 23.746 0 13.114 10.632 23.746 23.746 23.746z"/><path fill="#fff" d="M143.129 97.455c-.916-.916-2.426-.916-3.369 0l-15.256 15.255-5.309-5.309a2.326 2.326 0 00-3.262 0l-.054.053c-.943.944-.943 2.453 0 3.37l6.927 6.927c.917.916 2.426.916 3.369 0l16.954-16.954a2.328 2.328 0 000-3.342z"/></svg>'
			),
			$attributes
		);

		// The svg attribute is user-controllable via the shortcode, so restrict it to plain SVG markup.
		$attributes['svg'] = self::sanitize_svg( $attributes['svg'] );

		return $attributes;
	}


	/**
	 * Sanitize SVG markup against a strict allowlist of SVG elements and
	 * presentational attributes (no scripts, event handlers, links or styles).
	 *
	 * @param mixed $svg SVG markup.
	 *
	 * @return string
	 * @since 1.31.10
	 */
	public static function sanitize_svg( $svg ) {
		if ( ! is_string( $svg ) || '' === $svg ) {
			return '';
		}

		$common = array(
			'id'                => true,
			'class'             => true,
			'fill'              => true,
			'fill-rule'         => true,
			'fill-opacity'      => true,
			'clip-rule'         => true,
			'clip-path'         => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-opacity'    => true,
			'stroke-dasharray'  => true,
			'stroke-miterlimit' => true,
			'stroke-dashoffset' => true,
			'opacity'           => true,
			'transform'         => true,
		);

		$shape = array_merge(
			$common,
			array(
				'd'      => true,
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'rx'     => true,
				'ry'     => true,
				'x'      => true,
				'y'      => true,
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'width'  => true,
				'height' => true,
				'points' => true,
			)
		);

		$text = array_merge(
			$common,
			array(
				'x'           => true,
				'y'           => true,
				'dx'          => true,
				'dy'          => true,
				'text-anchor' => true,
				'font-size'   => true,
				'font-family' => true,
				'font-weight' => true,
			)
		);

		$gradient = array(
			'id'                => true,
			'x1'                => true,
			'y1'                => true,
			'x2'                => true,
			'y2'                => true,
			'cx'                => true,
			'cy'                => true,
			'r'                 => true,
			'fx'                => true,
			'fy'                => true,
			'gradientunits'     => true,
			'gradienttransform' => true,
		);

		$allowed = array(
			'svg'            => array_merge(
				$common,
				array(
					'xmlns'               => true,
					'width'               => true,
					'height'              => true,
					'viewbox'             => true,
					'preserveaspectratio' => true,
					'role'                => true,
					'aria-hidden'         => true,
					'aria-label'          => true,
					'focusable'           => true,
				)
			),
			'g'              => $common,
			'path'           => $shape,
			'circle'         => $shape,
			'ellipse'        => $shape,
			'rect'           => $shape,
			'line'           => $shape,
			'polyline'       => $shape,
			'polygon'        => $shape,
			'text'           => $text,
			'tspan'          => $text,
			'title'          => array(),
			'desc'           => array(),
			'defs'           => array(),
			'lineargradient' => $gradient,
			'radialgradient' => $gradient,
			'stop'           => array(
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
			),
			'clippath'       => array(
				'id'            => true,
				'clippathunits' => true,
			),
		);

		$svg = trim( force_balance_tags( wp_kses( $svg, $allowed ) ) );

		// Only accept a single, complete <svg> element so stray tags (e.g. a bare <title>) can't break the page.
		if ( ! preg_match( '/^<svg\b/i', $svg ) || ! preg_match( '/<\/svg>$/i', $svg ) ) {
			return '';
		}

		return $svg;
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
		ob_start();
		Helper::get_template(
			'template-subscribe-content.php',
			array(
				'attributes' => $this->get_attributes(),
				'classes'    => $this->get_wrapper_classes(),
			)
		);
		return ob_get_clean();
	}
}
