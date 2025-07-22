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
		);
		$settings = get_option( '_mint_compliance', $default );
		return $settings;
	}
}
