<?php
/**
 * Class for automation connector rules
 *
 * Class Connector
 *
 * @package  MintMail\App\Internal\Automation;
 * @version 1.0.0
 */

namespace  MintMail\App\Internal\Automation\Action;

use Mint\Mrm\Internal\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for automation connector rules
 *
 * Class Connector
 *
 * @package  MintMail\App\Internal\Automation;
 * @version 1.0.0
 */
class AutomationAction {

	use Singleton;

	/**
	 * Actions
	 *
	 * @var $actions
	 */
	protected $actions = array();


	/**
	 * Constructor.
	 *
	 * @return void
	 * @since  1.0.0
	 */
	public function __construct() {
		$this->actions = $this->supported_actions();
	}


	/**
	 * Constructor
	 *
	 * @param string $action action.
	 * @param array  $data data.
	 *
	 * @return void
	 * @since  1.0.0
	 */
	public function init( $action = '', $data = array() ) {
		if ( isset( $this->actions[ $action ] ) ) {
			$class_name = 'MintMail\\App\\Internal\\Automation\\Action\\' . ucfirst( $action );
			if ( class_exists( $class_name ) ) {
				$class_name::get_instance()->process( $data );
			}
		}
	}



	/**
	 * Get actions
	 */
	public function get_actions() {
		return $this->actions;
	}


	/**
	 * Supported actions
	 */
	public function supported_actions() {
		$actions = array(
			'delay'    => 'Delay',
			'sendMail' => 'Send mail',
			'addTag'   => 'Add tag',
			'addList'  => 'Add list',
		);
		$actions = apply_filters( 'mint_supported_automation_actions', $actions );
		return $actions;
	}
}
