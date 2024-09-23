<?php
/**
 * Abstract Automation connector class for MRM Autoamtion
 *
 * Class Automation_Connector
 *
 * @package MintMail\App\Internal\Automation
 */

namespace MintMail\App\Internal\Automation;

/**
 * Abstract Automation connector class for MRM Autoamtion
 *
 * Class Automation_Connector
 *
 * @package MintMail\App\Internal\Automation
 */
abstract class Automation_Connector {

	/**
	 * Get connector name
	 */
	abstract public function get_name();

	/**
	 *  Check is connected or not
	 */
	abstract public function maybe_connected();

}
