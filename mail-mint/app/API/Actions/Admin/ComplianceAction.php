<?php
/**
 * Compliance Setting Controller's actions
 *
 * Handles requests to the Compliance endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\API\Actions;

/**
 * This is the class that controls the compliance setting action. Its responsibilities are:
 *
 */
class ComplianceAction {

	/**
	 * Get compliance settings
	 *
	 * @return array $settings
	 * @since 1.0.0
	 */
	public function get_compliance() {
		$default  = array(
			'anonymize_ip'          => 'no',
			'user_id_delete'        => 'no',
			'one_click_unsubscribe' => 'no',
			'enable_gravatar'       => 'no',
			'gravatar_fallback'     => 'no',
			'personal_data_export'  => 'yes',
			'personal_data_erase'   => 'yes',
			'email_open_tracking'   => 'yes',
			'email_click_tracking'  => 'yes',
		);
		$settings = get_option( '_mint_compliance', [] );
		return wp_parse_args( $settings, $default );
	}

	/**
	 * Returns the open tracking mode: 'yes', 'anonymous', or 'no'.
	 *
	 * @return string
	 * @since 1.14.1
	 */
	public static function get_open_tracking_mode() {
		$compliance = get_option( '_mint_compliance', array() );
		$mode       = isset( $compliance['email_open_tracking'] ) ? $compliance['email_open_tracking'] : 'yes';
		return in_array( $mode, array( 'yes', 'anonymous', 'no' ), true ) ? $mode : 'yes';
	}

	/**
	 * Returns the click tracking mode: 'yes', 'anonymous', or 'no'.
	 *
	 * @return string
	 * @since 1.14.1
	 */
	public static function get_click_tracking_mode() {
		$compliance = get_option( '_mint_compliance', array() );
		$mode       = isset( $compliance['email_click_tracking'] ) ? $compliance['email_click_tracking'] : 'yes';
		return in_array( $mode, array( 'yes', 'anonymous', 'no' ), true ) ? $mode : 'yes';
	}
}
