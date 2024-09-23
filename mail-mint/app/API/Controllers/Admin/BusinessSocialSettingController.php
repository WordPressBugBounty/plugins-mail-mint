<?php
/**
 * REST API Business Social Setting Controller
 *
 * Handles requests to the business social setting endpoint.
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
 * This is the main class that controls the business social setting feature. Its responsibilities are:
 *
 * - Create or update business social settings
 * - Retrieve business social settings from options table
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class BusinessSocialSettingController extends SettingBaseController {

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
	protected $option_key = '_mrm_business_social_info_setting';

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
			$social        = isset( $params['socialMedia'] ) ? $params['socialMedia'] : array();

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

			foreach ( $social as $social_file ) {
				if ( isset( $social_file['icon'] ) && ! empty( $social_file['icon'] ) ) {
					$_mimes = wp_check_filetype( $social_file['icon'] );
					if ( isset( $_mimes['type'] ) ) {
						if ( ! in_array( $_mimes['type'], $image_mime, true ) ) {
							/* translators: %s mimes type */
							return $this->get_error_response( sprintf( __( 'Social media image type %s is not supported', 'mrm' ), $_mimes['ext'] ) );
						}
					}
				}
				if ( ! empty( $social_file['url'] ) ) {
					$regex = '%^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu';
					if ( !preg_match( $regex, $social_file['url'] ) ) {
						return $this->get_error_response( sprintf( __( 'Please provide valid URL(s).', 'mrm' ) ) );
					}
				}
			}
			$business_options = array(
				'socialMedia'   => $social,
			);
			update_option( $this->option_key, $business_options );
			return $this->get_success_response( __( 'Social Media settings has been successfully saved.', 'mrm' ) );
		}

		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function used to handle a single get request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get( WP_REST_Request $request ) {
		$default  = array(
			'socialMedia'   => array(),
		);
		$settings = get_option( $this->option_key, $default );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : $default;
		return $this->get_success_response_data( $settings );
	}
}