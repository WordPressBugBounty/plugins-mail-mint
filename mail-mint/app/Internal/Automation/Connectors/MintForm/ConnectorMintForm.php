<?php
/**
 * Automation WordPress connector class for MRM Autoamtion connector
 *
 * Class ConnectorMintForm
 *
 * @package MintMail\App\Internal\Automation\Connector
 */

namespace MintMail\App\Internal\Automation\Connector;

use MintMail\App\Internal\Automation\Automation_Connector;
use MintMail\App\Internal\Automation\Connector\trigger\MintFormTriggers;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * WordPress Connector
 *
 * Class ConnectorMintForm
 *
 * @package MintMail\App\Internal\Automation\Connector
 */
class ConnectorMintForm extends Automation_Connector {

	use Singleton;

	/**
	 * MM triggers
	 *
	 * @var $triggers.
	 */
	public $triggers;

	/**
	 * Initialization
	 */
	public function __construct() {
		if ( $this->maybe_connected() ) {
			MintFormTriggers::get_instance()->init();
		}
	}

	/**
	 * Get connector name
	 *
	 * @return String
	 * @since  1.0.0
	 */
	public function get_name() {
		return 'MintForm';
	}


	/**
	 * Check the connector is connected or not
	 *
	 * @return Bool
	 * @since  1.0.0
	 */
	public function maybe_connected() {
		return true;
	}


	/**
	 * Get all triggers
	 */
	public function get_triggers() {
		$this->triggers = $this->get_supported_mm_triggers();
		return $this->triggers;
	}


	/**
	 * All supported wp triggers
	 */
	public function get_supported_mm_triggers() {
		$mm_triggers = array(
			array(
				'key'   => 'mint_form_submission',
				'label' => 'Mint form submission',
			),
			array(
				'key'   => 'mint_list_applied',
				'label' => 'List applied',
			),
		);
		return $mm_triggers;
	}

}
