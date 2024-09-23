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

use Mint\Mrm\Internal\Traits\Singleton;

/**
 * [Manages plugin's shortcodes]
 *
 * @desc Manages plugin's shortcodes
 * @package /app/Internal/Shortcodes
 * @since 1.0.0
 */
class ShortCode {

	use Singleton;

	/**
	 * Initializes class functionalities and register shortcodes
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$shortcodes = array(
			'mintmrm'                  => __CLASS__ . '::render_contact_form',
			'mintmail_preview_page'    => __CLASS__ . '::render_form_preview',
			'optin_confirmation'       => __CLASS__ . '::render_optin_confirmation_page',
			'preference_page'          => __CLASS__ . '::render_preference_page',
			'unsubscribe_confirmation' => __CLASS__ . '::render_unsubscribe_confirmation_page',
		);

		foreach ( $shortcodes as $shortcode => $function ) {
			add_shortcode( $shortcode, $function );
		}
	}


	/**
	 * Renders Shortcode
	 *
	 * @param array|object $atts Form attributes.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function render_contact_form( $atts ) {
		$shortcode = new ContactForm( (array) $atts );

		return $shortcode->get_content();
	}


	/**
	 * Render optin confirmation page shortcode
	 *
	 * @param array|object $atts Page attributes.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function render_optin_confirmation_page( $atts ) {
		$shortcode = new OptinConfirmation( (array) $atts );
		return $shortcode->get_content();
	}

	/**
	 * Render Preference  Page page shortcode.
	 *
	 * @param array|object $atts Page attributes.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function render_preference_page( $atts ) {
		$shortcode = new PreferencePage( (array) $atts );
		return $shortcode->get_content();
	}
	/**
	 * Render optin confirmation page shortcode
	 *
	 * @param array|object $atts Page attributes.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function render_unsubscribe_confirmation_page( $atts ) {
		$shortcode = new UnsubscribeConfirmation( (array) $atts );
		return $shortcode->get_content();
	}
	/**
	 * Render optin confirmation page shortcode
	 *
	 * @param array|object $atts Page attributes.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function render_form_preview( $atts ) {
		$shortcode = new FormPreview( (array) $atts );
		return $shortcode->get_content();
	}
}
