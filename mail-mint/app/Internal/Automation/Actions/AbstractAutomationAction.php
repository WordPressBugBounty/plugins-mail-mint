<?php
/**
 * Abstract Automation connector class for MRM Autoamtion
 *
 * Class Automation_Connector
 *
 * @package MintMail\App\Internal\Automation
 */

namespace MintMail\App\Internal\Automation\Action;

/**
 * Abstract Automation connector class for MRM Autoamtion
 *
 * Class Automation_Connector
 *
 * @package MintMail\App\Internal\Automation
 */
abstract class AbstractAutomationAction {

	/**
	 * Get action name
	 */
	abstract public function get_name();


	/**
	 * Process.
	 *
	 * @param array $data Get All data.
	 */
	abstract public function process( $data );

}
