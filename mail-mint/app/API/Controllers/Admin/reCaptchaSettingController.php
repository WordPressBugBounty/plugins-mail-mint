<?php
/**
 * REST API reCAPTCHA Setting Controller
 *
 * Handles requests to the reCAPTCHA setting endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;
use WP_REST_Request;

/**
 * This is the main class that controls the reCAPTCHA setting feature. Its responsibilities are:
 *
 * - Create or update reCAPTCHA settings
 * - Retrieve reCAPTCHA settings from options table
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class reCaptchaSettingController extends SettingBaseController {

	use Singleton;

	/**
	 * Setiings object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args;

	/**
	 * Optin setiings key
	 *
	 * @var object
	 * @since 1.0.0
	 */
	private $option_key = '_mint_recaptcha_settings';

	/**
	 * Get and send response to create a new settings
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

        if ( is_array( $params ) && ! empty( $params ) ) {
            $captcha_setting = isset( $params['reCaptchaSetting'] ) ? $params['reCaptchaSetting'] : array();
            $enable = !empty($captcha_setting['enable']) ? $captcha_setting['enable'] : false;
            $api_version = isset( $captcha_setting['api_version'] ) ? $captcha_setting['api_version'] : array();
            
            $v3_site_key   = isset( $captcha_setting['v3_invisible']['site_key'] ) ? $captcha_setting['v3_invisible']['site_key']  : '';
            $v3_secret_key = isset( $captcha_setting['v3_invisible']['secret_key'] ) ? $captcha_setting['v3_invisible']['secret_key']  : '';
            $v2_site_key   = isset( $captcha_setting['v2_visible']['site_key'] ) ? $captcha_setting['v2_visible']['site_key']  : '';
            $v2_secret_key = isset( $captcha_setting['v2_visible']['secret_key'] ) ? $captcha_setting['v2_visible']['secret_key']  : '';

            // Email body and confirmation message validation.
			if ( 'v3_invisible' == $api_version && '' === $v3_site_key  && $enable ) {
				return $this->get_error_response( __( 'reCAPTCHA site key is empty', 'mrm' ) );
			}

            if ( 'v3_invisible' == $api_version && '' === $v3_secret_key  && $enable ) {
				return $this->get_error_response( __( 'reCAPTCHA secret key is empty', 'mrm' ) );
			}

            if ( 'v2_visible' == $api_version && '' === $v2_site_key  && $enable ) {
				return $this->get_error_response( __( 'reCAPTCHA site key is empty', 'mrm' ) );
			}

            if ( 'v2_visible' == $api_version && '' === $v2_secret_key  && $enable ) {
				return $this->get_error_response( __( 'reCAPTCHA secret key is empty', 'mrm' ) );
			}
            if( $enable && 'v3_invisible' == $api_version && ctype_space($v3_site_key) ){
                return $this->get_error_response( __( 'Whitespace is not allowed in the site key for reCAPTCHA v3.', 'mrm' ) );
            }
            if( $enable && 'v3_invisible' == $api_version && ctype_space($v3_secret_key)){
                return $this->get_error_response( __( 'Whitespace is not allowed in the secret key for reCAPTCHA v3', 'mrm' ) );
            }
            if( $enable && 'v2_visible' == $api_version && ctype_space($v2_site_key) ){
                return $this->get_error_response( __( 'Whitespace is not allowed in the site key for reCAPTCHA v2.', 'mrm' ) );
            }
            if( $enable && 'v2_visible' == $api_version && ctype_space($v2_secret_key)){
                return $this->get_error_response( __( 'Whitespace is not allowed in the secret key for reCAPTCHA v2', 'mrm' ) );
            }

            update_option( $this->option_key,$captcha_setting);
            return $this->get_success_response( __( 'reCAPTCHA settings have been successfully saved.', 'mrm' ) );
        }
	}


	/**
	 * Function used to handle a single get request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get( WP_REST_Request $request ) {
		$default  = MrmCommon::recaptcha_default_configuration();
		$settings = get_option( $this->option_key, $default );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : $default;

		return $this->get_success_response_data( $settings );
	}
}
