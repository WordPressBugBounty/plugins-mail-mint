<?php
/**
 * REST API WCSetting Controller
 *
 * Handles requests to the WooCommerce settings endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Tables\ContactGroupSchema;
use MRM\Common\MrmCommon;
use WP_REST_Request;

/**
 * This is the main class that controls the WooCommerce setting feature. Its responsibilities are:
 *
 * - Create or update WooCommerce settings
 * - Retrieve WooCommerce settings from options table
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class WCSettingController extends SettingBaseController {

	/**
	 * Update WooCommerce global settings into wp_option table
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response|WP_Error
	 * @since 1.0.0
	 * @since 1.20.0 - Add 'enable_email_customize' to the default settings.
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$params = is_array( $params ) && ! empty( $params ) ? $params : array(
			'enable'                 => true,
			'checkbox_label'         => 'I would like to receive exclusive emails with discounts and product information.',
			'lists'                  => array(),
			'tags'                   => array(),
			'enable_email_customize' => false,
		);

		if ( update_option( '_mrm_woocommerce_settings', $params ) ) {
			return $this->get_success_response( __( 'WooCommerce settings have been successfully saved.', 'mrm' ) );
		}
		return $this->get_error_response( __( 'No changes have been made.', 'mrm' ) );
	}

	/**
	 * Get WooCommerce global settings from wp_option table
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 * @since 1.20.0 - Add 'enable_email_customize' to the default settings.
	 */
	public function get( WP_REST_Request $request ) {
		$default  = array(
			'enable'                 => true,
			'checkbox_label' 		 => 'I would like to receive exclusive emails with discounts and product information.',
			'lists'          		 => array(),
			'tags'           		 => array(),
			'enable_email_customize' => false,
		);
		$settings = get_option( '_mrm_woocommerce_settings', $default );
		$settings = $this->validate_groups( $settings );
		update_option( '_mrm_woocommerce_settings', $settings );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : $default;
		return $this->get_success_response_data( $settings );
	}

	/**
	 * Validate selected tags/lists in the WooCommerce setting in MRM are still exists or not
	 *
	 * @param array $settings MRM WooCommerce Settings.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private function validate_groups( $settings ) {
		if ( isset( $settings[ 'lists' ] ) ) {
			foreach ( $settings[ 'lists' ] as $key => $list ) {
				if ( isset( $list[ 'id' ] ) && ! $this->is_group_exist( (int) $list[ 'id' ], 'lists' ) ) {
					unset( $settings[ 'lists' ][ $key ] );
					$settings[ 'lists' ] = array_values( $settings[ 'lists' ] );
				}
			}
		}
		if ( isset( $settings[ 'tags' ] ) ) {
			foreach ( $settings[ 'tags' ] as $key => $list ) {
				if ( isset( $list[ 'id' ] ) && ! $this->is_group_exist( (int) $list[ 'id' ], 'tags' ) ) {
					unset( $settings[ 'tags' ][ $key ] );
					$settings[ 'tags' ] = array_values( $settings[ 'tags' ] );
				}
			}
		}
		return $settings;
	}

	/**
	 * Check existing tag, list or segment on database
	 *
	 * @param mixed  $id Group id.
	 * @param string $type Group type.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_group_exist( $id, $type ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		$select_query = $wpdb->prepare( 'SELECT `id` FROM %1s WHERE `id` = %d AND type = %s', $group_table, (int) $id, $type ); //phpcs:ignore
		$group_id = $wpdb->get_var( $select_query ); //phpcs:ignore
		return $group_id && (int) $group_id === (int) $id;
	}
}
