<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Frontend
 */

namespace Mint\MRM\Internal\Admin;

use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * [Manages plugin's frontend assets]
 *
 * @desc Manages plugin's frontend assets
 * @package /app/Internal/Frontend
 * @since 1.0.0
 */
class FrontendAssets {

	use Singleton;

	/**
	 * Initializes class functionalities
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}


	/**
	 * Load plugin main js file
	 *
	 * @param string $hook Hook suffix of current admin page.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts( $hook ) {
		$recaptcha_default = MrmCommon::recaptcha_default_configuration();
		wp_enqueue_script(
			MRM_PLUGIN_NAME,
			MRM_DIR_URL . 'assets/frontend/js/frontend.js',
			array( 'jquery' ),
			MRM_VERSION,
			true
		);
		wp_localize_script(
			MRM_PLUGIN_NAME,
			'MRM_Frontend_Vars',
			array(
				'ajaxurl'            => admin_url( 'admin-ajax.php' ),
				// This is a nonce created for front-end form submissions.
				// This is used in the following file: /assets/frontend/js/frontend.js.
				'nonce'              => wp_create_nonce( 'wp_rest' ),
				'rest_api_url'       => get_rest_url(),
				'form_cookies_time'  => apply_filters( 'mailmint_set_form_cookies_time', $this->set_dissmiss_time() ),
				'recaptcha_settings' => get_option( '_mint_recaptcha_settings', $recaptcha_default ),

			)
		);
	}

	/**
	 * Gets form dismissal time from wp_options table
	 *
	 * @return false|mixed|void
	 * @since 1.0.0
	 */
	public function set_dissmiss_time() {
		return get_option( '_mailmint_form_dismissed', 7 );
	}


	/**
	 * Load plugin main css file
	 *
	 * @param string $hook Hook suffix of current admin page.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles( $hook ) {
		wp_enqueue_style(
			MRM_PLUGIN_NAME . '-select2',
			MRM_DIR_URL . 'assets/frontend/css/frontend.css',
			array(),
			MRM_VERSION
		);
	}


	/**
	 * Get assets URL
	 *
	 * @param string $file File name.
	 * @param string $ext File extension.
	 * @param string $type File type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_url( $file, $ext, $type = 'dist' ) {
		$suffix = '';
		// Potentially enqueue minified JavaScript.
		if ( 'js' === $ext ) {
			$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
			$suffix       = self::should_use_minified_file( $script_debug ) ? '' : '.min';
		}

		return plugins_url( self::get_path( $ext, $type ) . $file . $suffix . '.' . $ext, MRM_FILE );
	}


	/**
	 * Get the Asset path
	 *
	 * @param string $ext File extension.
	 * @param string $type File type.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function get_path( $ext, $type = 'dist' ) {
		if ( 'external' === $type ) {
			return ( 'css' === $ext ) ? MRM_ADMIN_EXTERNAL_CSS_FOLDER : MRM_ADMIN_EXTERNAL_JS_FOLDER;
		}

		return ( 'css' === $ext ) ? MRM_ADMIN_DIST_CSS_FOLDER : MRM_ADMIN_DIST_JS_FOLDER;
	}


	/**
	 * Determine if minified file is served
	 *
	 * @param bool $script_debug Constant variable SCRIPT_DEBUG.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function should_use_minified_file( $script_debug ) {
		return ! $script_debug;
	}


	/**
	 * Check if the current page is CRM page or not
	 *
	 * @param string $hook Hook suffix of current admin page.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function maybe_mrm_page( $hook ) {
		return 'toplevel_page_mrm-admin' === $hook;
	}
}
