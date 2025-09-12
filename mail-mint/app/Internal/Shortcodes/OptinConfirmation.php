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
