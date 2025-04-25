<?php
/**
 * Automation WordPress connector class for MRM Autoamtion connector
 *
 * Class Connector_Wordpress
 *
 * @package MintMail\App\Internal\Automation\Connector
 */

namespace MintMail\App\Internal\Automation\Connector;

use Automattic\Jetpack\Import\Endpoints\Post;
use MintMail\App\Internal\Automation\Automation_Connector;
use MintMail\App\Internal\Automation\Connector\trigger\MintFormTriggers;
use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\Connector\trigger\PostPublishedTriggers;
use MintMail\App\Internal\Automation\Connector\trigger\WordpressTriggers;
use WPDesk\ShopMagic\Workflow\Event\Builtin\Post\PostPublished;

/**
 * WordPress Connector
 *
 * Class Connector_Wordpress
 *
 * @package MintMail\App\Internal\Automation\Connector
 */
class ConnectorWordPress extends Automation_Connector {

	use Singleton;

	/**
	 * Wp triggers
	 *
	 * @var $triggers.
	 */
	public $triggers;

	/**
	 * Initialization
	 */
	public function __construct() {
		if ( $this->maybe_connected() ) {
			WordpressTriggers::get_instance()->init();
			PostPublishedTriggers::get_instance()->init();
		}
	}

	/**
	 * Get connector name
	 *
	 * @return String
	 * @since  1.0.0
	 */
	public function get_name() {
		return 'WordPress';
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
		$this->triggers = $this->get_supported_wp_triggers();
		return $this->triggers;
	}


	/**
	 * All supported wp triggers
	 */
	public function get_supported_wp_triggers() {
		$wp_triggers = array(
			array(
				'key'   => 'wp_user_registration',
				'label' => 'WP user registration',
			),
			array(
				'key'   => 'wp_user_login',
				'label' => 'WP user login',
			),
		);
		return $wp_triggers;
	}

}
