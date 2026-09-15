<?php
/**
 * Automation WPFunnels connector class for MRM Automation connector
 *
 * Class ConnectorWpfnlCore
 *
 * @package MintMail\App\Internal\Automation\Connector
 */

namespace MintMail\App\Internal\Automation\Connector;

use MintMail\App\Internal\Automation\Automation_Connector;
use MintMail\App\Internal\Automation\Connector\trigger\WpfnlCoreTriggers;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * WPFunnels Connector
 *
 * Named *WpfnlCore*, not *WPFunnels*: Pro declares ConnectorWpfunnels in this same
 * namespace and registers it under the same 'wpfunnels' connector key. PHP class names
 * are case-insensitive, so with a `WPFunnels` spelling Pro's key wins and this class is
 * never loaded at all — taking the Funnel Created trigger down with it.
 *
 * Class ConnectorWpfnlCore
 *
 * @package MintMail\App\Internal\Automation\Connector
 */
class ConnectorWpfnlCore extends Automation_Connector {

	use Singleton;

	/**
	 * WPFunnels triggers
	 *
	 * @var $triggers.
	 */
	public $triggers;

	/**
	 * Initialization
	 */
	public function __construct() {
		if ( $this->maybe_connected() ) {
			WpfnlCoreTriggers::get_instance()->init();
		}
	}

	/**
	 * Get connector name
	 *
	 * @return String
	 * @since  1.0.0
	 */
	public function get_name() {
		return 'WPFunnels';
	}

	/**
	 * Check the connector is connected or not
	 *
	 * @return Bool
	 * @since  1.0.0
	 */
	public function maybe_connected() {
		return MrmCommon::is_wpfnl_active();
	}

	/**
	 * Get all triggers
	 */
	public function get_triggers() {
		$this->triggers = $this->get_supported_wpfunnels_triggers();
		return $this->triggers;
	}

	/**
	 * All supported WPFunnels triggers
	 */
	public function get_supported_wpfunnels_triggers() {
		$wpfunnels_triggers = array(
			array(
				'key'   => 'wpfunnels_funnel_created',
				'label' => 'Funnel Created',
			),
		);
		return $wpfunnels_triggers;
	}

}
