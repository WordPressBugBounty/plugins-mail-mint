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
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$params = is_array( $params ) && ! empty( $params ) ? $params : array(
			'enable'                      => true,
			'checkbox_label'              => 'I would like to receive exclusive emails with discounts and product information.',
			'lists'                       => array(),
			'tags'                        => array(),
			'auto_coupon_cleanup_enabled' => false,
			'auto_coupon_cleanup_number'  => 7,
			'auto_coupon_cleanup_unit'    => 'day',
		);

		if ( is_array( $params ) && ! empty( $params ) ) {
			// Validate auto_coupon_cleanup_number: must be a positive integer (>= 1).
			if ( isset( $params['auto_coupon_cleanup_number'] ) ) {
				$cleanup_number = filter_var( $params['auto_coupon_cleanup_number'], FILTER_VALIDATE_INT );
				if ( false === $cleanup_number || $cleanup_number < 1 ) {
					return new \WP_REST_Response(
						array( 'message' => __( 'auto_coupon_cleanup_number must be a positive integer (>= 1).', 'mrm' ) ),
						400
					);
				}
			}

			// Validate auto_coupon_cleanup_unit: must be one of day, week, month.
			if ( isset( $params['auto_coupon_cleanup_unit'] ) ) {
				$allowed_units = array( 'day', 'week', 'month' );
				if ( ! in_array( $params['auto_coupon_cleanup_unit'], $allowed_units, true ) ) {
					return new \WP_REST_Response(
						array( 'message' => __( 'auto_coupon_cleanup_unit must be one of: day, week, month.', 'mrm' ) ),
						400
					);
				}
			}

			if ( update_option( '_mrm_woocommerce_settings', $params ) ) {
				/**
				 * Fires after auto-coupon cleanup settings are successfully saved.
				 *
				 * @param array $params The full settings array that was persisted.
				 * @since 1.19.5
				 */
				do_action( 'mailmint_auto_coupon_cleanup_settings_saved', $params );
				return $this->get_success_response( __( 'WooCommerce settings have been successfully saved.', 'mrm' ) );
			}
			return $this->get_error_response( __( 'No changes have been made.', 'mrm' ) );
		}
	}

	/**
	 * Get WooCommerce global settings from wp_option table
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get( WP_REST_Request $request ) {
		$default  = array(
			'enable'                        => true,
			'checkbox_label' 		        => 'I would like to receive exclusive emails with discounts and product information.',
			'lists'          		        => array(),
			'tags'           		        => array(),
			'auto_coupon_cleanup_enabled'   => false,
			'auto_coupon_cleanup_number'    => 7,
			'auto_coupon_cleanup_unit'      => 'day',
		);
		$settings = get_option( '_mrm_woocommerce_settings', $default );
		$settings = $this->validate_groups( $settings );
		update_option( '_mrm_woocommerce_settings', $settings );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : $default;
		return $this->get_success_response_data( $settings );
	}

	/**
	 * Trigger a background sync of WooCommerce order data into the mint_wc_customers table.
	 *
	 * Cancels any pending sync, then schedules the first batch. The batch processor
	 * (DatabaseMigrator::sync_wc_customers_batch) handles subsequent batches automatically
	 * via Action Scheduler until all orders are processed.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 * @since 1.14.0
	 */
	public function sync_customers( WP_REST_Request $request ) {
		if ( ! MrmCommon::is_wc_active() ) {
			return $this->get_error_response( __( 'WooCommerce is not active.', 'mrm' ) );
		}

		as_unschedule_all_actions( 'mail_mint_sync_wc_customers', array(), 'mail-mint-wc-sync' );

		as_schedule_single_action(
			time() + 5,
			'mail_mint_sync_wc_customers',
			array( 'offset' => 0 ),
			'mail-mint-wc-sync'
		);

		return $this->get_success_response( __( 'WooCommerce customer sync has been started. It will complete in the background.', 'mrm' ) );
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
