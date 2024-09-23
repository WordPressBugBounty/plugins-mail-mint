<?php

namespace Mint\MRM\Internal\Admin;

abstract class Notices {


//	/**
//	 * Stores notices.
//	 *
//	 * @var array
//	 */
//	private static $notices = array();
//
//
//	public static $admin_notices = array(
//		'database_update' => 'database_update'
//	);
//
//
//	public function __construct() {
//		self::$notices = get_option( 'woocommerce_admin_notices', array() );
//	}
//
//
//	/**
//	 * Get notices
//	 *
//	 * @return array
//	 * @since 1.6.0
//	 */
//	public static function get_notices() {
//		return self::$notices;
//	}
//
//
//	public static function hide_notices() {
//		if ( isset( $_GET['mm-hide-notice'] ) && isset( $_GET['mm_notice_nonce'] ) ) { // WPCS: input var ok, CSRF ok.
//			if ( ! \wp_verify_nonce( sanitize_key( wp_unslash( $_GET['mm_notice_nonce'] ) ), 'mm_hide_notices_nonce' ) ) { // WPCS: input var ok, CSRF ok.
//				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'mrm' ) );
//			}
//
//			$notice_name = sanitize_text_field( wp_unslash( $_GET['mm-hide-notice'] ) );
//
//			if ( ! current_user_can( 'manage_options' ) ) {
//				wp_die( esc_html__( 'You don&#8217;t have permission to do this.', 'mrm' ) );
//			}
//
////			self::hide_notice( $notice_name );
//		}
//	}

}
