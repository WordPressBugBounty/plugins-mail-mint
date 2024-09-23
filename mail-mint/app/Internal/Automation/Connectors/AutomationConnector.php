<?php
/**
 * Class for automation connector rules
 *
 * Class Connector
 *
 * @package  MintMail\App\Internal\Automation;
 * @version 1.0.0
 */

namespace MintMail\App\Internal\Automation;

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
class Connector {

	use Singleton;

	/**
	 * Connectors
	 *
	 * @var $connectors
	 */
	protected $connectors;

	/**
	 * Connector instance
	 *
	 * @var $connector_instance
	 */
	protected $connector_instance;

	/**
	 * Connector name
	 *
	 * @var $connector_name
	 */
	protected $connector_name;


	/**
	 * Connector trigger
	 *
	 * @var $triggers
	 */
	protected $triggers = array();


	/**
	 * Constructor
	 *
	 * @return void
	 * @since  1.0.0
	 */
	public function __construct() {
		$connectors = $this->get_connectors();
		foreach ( $connectors as $connector ) {
			$connector_class = 'MintMail\\App\\Internal\\Automation\\Connector\\' . $connector['class_name'];
			if ( class_exists( $connector_class ) ) {
				$connector_class::get_instance();
				$connector_name                    = $connector_class::get_instance()->get_name();
				$triggers                          = $connector_class::get_instance()->get_triggers();
				$this->triggers[ $connector_name ] = $triggers;
			}
		}
	}


	/**
	 * Save supported connector name
	 *
	 * @return Array
	 * @since  1.0.0
	 */
	private function get_connectors() {
		$default_connectors = array(
			'wordpress' => array(
				'class_name' => 'ConnectorWordPress',
			),
			'mintform'  => array(
				'class_name' => 'ConnectorMintForm',
			),
		);
		return apply_filters( 'mrm_automation_connectors', $default_connectors );
	}



	/**
	 * Get triggers
	 */
	public function get_triggers() {
		return $this->triggers;
	}
}
