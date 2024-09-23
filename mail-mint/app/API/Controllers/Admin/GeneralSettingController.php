<?php
/**
 * REST API General Setting Controller
 *
 * Handles requests to the general setting endpoint.
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
 * This is the main class that controls the general setting feature. Its responsibilities are:
 *
 * - Create or update general settings
 * - Retrieve general settings from options table
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class GeneralSettingController extends SettingBaseController {

	use Singleton;

	/**
	 * Update General global settings into wp_options table
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return array|WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		if ( is_array( $params ) && ! empty( $params ) ) {
			foreach ( $params as $key => $value ) {
				if ( isset( $value['confirmation_type'] ) && 'redirect' === $value['confirmation_type'] ) {
					if ( isset( $value['url'] ) && ! empty( $value['url'] ) ) {
						if ( filter_var( $value['url'], FILTER_VALIDATE_URL ) === false ) {
							return $this->get_error_response( __( ' URL is not valid', 'mrm' ) );
						}
					}
				}
				update_option( '_mrm_general_' . $key, $value );
			}
			return $this->get_success_response( __( 'General settings have been successfully saved.', 'mrm' ) );
		}
		return $this->get_error_response( __( 'No changes have been made.', 'mrm' ) );
	}

	/**
	 * Get General global settings from wp_option table
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return array|WP_REST_Response
	 * @since 1.0.0
	 */
	public function get( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		$option_keys = apply_filters(
			'mrm_general_settings_option_key',
			array(
				'unsubscriber_settings',
				'preference',
				'user_signup',
				'comment_form_subscription',
				'plugin_data_delete',
				'user_delete',
			)
		);

		$settings = array();
		if ( isset( $params['general_settings_key'] ) ) {
			$key              = $params['general_settings_key'];
			$settings[ $key ] = get_option( '_mrm_general_' . $key, array() );
		} else {
			foreach ( $option_keys as $key ) {				
				$settings[ $key ] = get_option( '_mrm_general_' . $key, array() );
			}
		}
		$settings[ 'footer_watermark' ] = get_option( '_mrm_general_footer_watermark', 'yes' );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : array();
		$settings = $this->assign_tag_tag_list_exist( $settings );
		return $this->get_success_response_data( $settings );
	}

	/**
	 * Checking Assign List
	 *
	 * @param array $settings Get Setting.
	 *
	 * @return array
	 */
	public function assign_tag_tag_list_exist( $settings ) {
		if ( isset( $settings['user_signup']['list_mapping'] ) && is_array( $settings['user_signup']['list_mapping'] ) ) {
			$listis = $settings['user_signup']['list_mapping'];
			foreach ( $listis as $key => $list ) {
				if ( 'administrator' === $list['role'] ) {
					$result = MrmCommon::is_list_exist( $list['list'], 'lists' );
					$settings['user_signup']['list_mapping'][ $key ]['role'] = 'administrator';
					$settings['user_signup']['list_mapping'][ $key ]['list'] = array_values( array_filter( $result ) );
				}if ( 'editor' === $list['role'] ) {
					$result = MrmCommon::is_list_exist( $list['list'], 'lists' );
					$settings['user_signup']['list_mapping'][ $key ]['role'] = 'editor';
					$settings['user_signup']['list_mapping'][ $key ]['list'] = array_values( array_filter( $result ) );
				}if ( 'author' === $list['role'] ) {
					$result = MrmCommon::is_list_exist( $list['list'], 'lists' );
					$settings['user_signup']['list_mapping'][ $key ]['role'] = 'author';
					$settings['user_signup']['list_mapping'][ $key ]['list'] = array_values( array_filter( $result ) );
				}if ( 'contributor' === $list['role'] ) {
					$result = MrmCommon::is_list_exist( $list['list'], 'lists' );
					$settings['user_signup']['list_mapping'][ $key ]['role'] = 'contributor';
					$settings['user_signup']['list_mapping'][ $key ]['list'] = array_values( array_filter( $result ) );
				}if ( 'subscriber' === $list['role'] ) {
					$result = MrmCommon::is_list_exist( $list['list'], 'lists' );
					$settings['user_signup']['list_mapping'][ $key ]['role'] = 'subscriber';
					$settings['user_signup']['list_mapping'][ $key ]['list'] = array_values( array_filter( $result ) );
				}
			}
		}

		if ( isset( $settings['preference']['lists'] ) && is_array( $settings['preference']['lists'] ) ) {
			$preferance_list                 = $settings['preference']['lists'];
			$result                          = MrmCommon::is_list_exist( $preferance_list, 'lists' );
			$settings['preference']['lists'] = array_values( array_filter( $result ) );
		}
		if ( isset( $settings['comment_form_subscription']['lists'] ) && is_array( $settings['comment_form_subscription']['lists'] ) ) {
			$comment_list                                   = $settings['comment_form_subscription']['lists'];
			$comment_list_result                            = MrmCommon::is_list_exist( $comment_list, 'lists' );
			$settings['comment_form_subscription']['lists'] = array_values( array_filter( $comment_list_result ) );
		}
		if ( isset( $settings['comment_form_subscription']['tags'] ) && is_array( $settings['comment_form_subscription']['tags'] ) ) {
			$comment_tag                                   = $settings['comment_form_subscription']['tags'];
			$comment_tag_result                            = MrmCommon::is_list_exist( $comment_tag, 'tags' );
			$settings['comment_form_subscription']['tags'] = array_values( array_filter( $comment_tag_result ) );
		}

		return $settings;
	}
}
