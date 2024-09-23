<?php
/**
 * Automation class
 *
 * MRM Autoamtion
 *
 * @package Mint\MRM
 *
 * @since 1.0.0
 */

namespace MintMail\App\Internal;

use MintMail\App\Internal\Automation\AutomationManager;
use MintMail\App\Internal\Automation\Connector;
use MintMail\App\Internal\Automation\Action\AutomationAction;

/**
 * Automation class
 *
 * MRM Autoamtion
 *
 * @package Mint\MRM
 *
 * @since 1.0.0
 */
class Automation {

	/**
	 * Automation hook instance
	 *
	 * @var $hook_instance
	 *
	 * @since 1.0.0
	 */
	public $hook_instance;

	/**
	 * Automation manager instance
	 *
	 * @var $manager
	 *
	 * @since 1.0.0
	 */
	public $manager;

	/**
	 * Connector manager instance
	 *
	 * @var $connector_instance
	 *
	 * @since 1.0.0
	 */
	public $connector_instance;


	/**
	 * Actions
	 *
	 * @var $actions
	 *
	 * @since 1.0.0
	 */
	public $actions;

	/**
	 * Triggers
	 *
	 * @var $triggers
	 *
	 * @since 1.0.0
	 */
	public $triggers;


	/**
	 * Class Initialization
	 *
	 * @param AutomationManager $automation_manager Instance of AutomationManager class.
	 * @param Connector         $connector Instance of Connector class.
	 * @param AutomationAction  $automation_action Instance of AutomationAction class.
	 */
	public function __construct( AutomationManager $automation_manager, Connector $connector, AutomationAction $automation_action ) {
		$this->manager            = $automation_manager;
		$this->connector_instance = $connector;
		$this->triggers           = $connector->get_triggers();
		$this->actions            = $automation_action->get_actions();
	}
}
