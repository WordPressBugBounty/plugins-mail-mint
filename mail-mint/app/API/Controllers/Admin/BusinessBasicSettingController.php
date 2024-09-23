<?php
/**
 * REST API Business Basic Setting Controller
 *
 * Handles requests to the business setting endpoint.
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
 * This is the main class that controls the business basic setting feature. Its responsibilities are:
 *
 * - Create or update business basic settings
 * - Retrieve business basic settings from options table
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class BusinessBasicSettingController extends SettingBaseController {

	use Singleton;

	/**
	 * Setiings object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args;

	/**
	 * Business settings key for options table
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $option_key = '_mrm_business_basic_info_setting';

	/**
	 * Get and send response to create a new settings
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		if ( is_array( $params ) && ! empty( $params ) ) {
			$business_name = isset( $params['business_name'] ) ? sanitize_text_field( $params['business_name'] ) : '';
			$phone         = isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '';
			$address       = !empty( $params['business_address'] ) ?  $params['business_address']  : [];
			$logo_url      = isset( $params['logo_url'] ) ? sanitize_text_field( $params['logo_url'] ) : '';
			if ( ctype_punct( $business_name ) ) {
				return $this->get_error_response( __( 'Business name should not contain only special characters.', 'mrm' ) );
			}

			foreach ( $address as $field ) {
				if ( ctype_punct( $field ) ) {
					return $this->get_error_response( __( 'Address info should not contain only special characters.', 'mrm' ) );
				}
			}

			if ( !empty($phone) && ! $this->phone_number_validation( $phone ) ) {
				return $this->get_error_response( __( 'Please provide a valid phone number.', 'mrm' ) );
			}
			$image_mime = array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'gif'          => 'image/gif',
				'png'          => 'image/png',
				'bmp'          => 'image/bmp',
				'tiff|tif'     => 'image/tiff',
				'webp'         => 'image/webp',
				'ico'          => 'image/x-icon',
				'heic'         => 'image/heic',
			);
			$logo_mimes = wp_check_filetype( $logo_url );

			if ( !empty( $logo_mimes['type'] ) && ! in_array( $logo_mimes['type'], $image_mime, true ) ) {
				return $this->get_error_response( sprintf( __( 'Image type %s is not supported', 'mrm' ), $logo_mimes['ext'] ) );
			}
			$business_options = array(
				'business_name'    => $business_name,
				'phone'            => $phone,
				'business_address' => $address,
				'logo_url'         => $logo_url,
			);
			update_option( $this->option_key, $business_options );
			return $this->get_success_response( __( 'Basic settings has been successfully saved.', 'mrm' ) );
		}

		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function used to handle a single get request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get( WP_REST_Request $request ) {
        $business_name = get_bloginfo() ;		
		$default  = array(
			'business_name'    => $business_name ? html_entity_decode( $business_name, ENT_QUOTES ) : '',
			'phone'            => '',
			'business_address' => '',
			'logo_url'         => '',
		);
		$settings = get_option( $this->option_key, $default );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : $default;

		$settings['business_name'] = isset( $settings['business_name'] ) ? html_entity_decode( $settings['business_name'], ENT_QUOTES ) : '';

		if ( isset( $settings['business_address'] ) ){
			$settings['business_address'] = maybe_unserialize( $settings['business_address'] );
		}
		return $this->get_success_response_data( $settings );
	}

	/**
	 * Function used to validate a phone number
	 *
	 * @param mixed $number phone number to validate.
	 * @return bool
	 * @since 1.0.0
	 */
	public function phone_number_validation( $number ) {
		$phone_number_validation_regex = '/^\\+?\\d{1,4}?[-.\\s]?\\(?\\d{1,3}?\\)?[-.\\s]?\\d{1,4}[-.\\s]?\\d{1,4}[-.\\s]?\\d{1,9}$/';
		return preg_match( $phone_number_validation_regex, $number );
	}
}
