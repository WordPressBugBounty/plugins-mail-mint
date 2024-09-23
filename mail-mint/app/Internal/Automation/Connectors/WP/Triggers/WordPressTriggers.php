<?php
/**
 * WordPress triggers
 *
 * @package MintMail\App\Internal\Automation\Connector\trigger;
 */

namespace MintMail\App\Internal\Automation\Connector\trigger;

use Mint\Mrm\Internal\Traits\Singleton;
/**
 * WordPress triggers
 *
 * @package MintMail\App\Internal\Automation\Connector
 */
class WordpressTriggers {

	use Singleton;


	/**
	 * Connector name
	 *
	 * @var $connector_name
	 */
	public $connector_name = 'WordPress';


	/**
	 * Initialization of WordPress hooks
	 */
	public function init() {
		add_action( 'user_register', array( $this, 'mint_wp_user_created' ), 10 );
		add_action( 'wp_login', array( $this, 'mint_wp_login' ), 10, 2 );
	}

	/**
	 * Validate trigger settings
	 *
	 * @param array $step_data Get Step Data.
	 * @param array $data Get all data.
	 * @return bool
	 */
	public function validate_settings( $step_data, $data ) {
		return true;
	}


	/**
	 * WP user created
	 *
	 * @param string $user_id Register User ID.
	 */
	public function mint_wp_user_created( $user_id ) {
		$user_data = get_userdata( $user_id );
		$data      = array(
			'connector_name' => $this->connector_name,
			'trigger_name'   => 'wp_user_registration',
			'data'           => array(
				'user_id'    => isset( $user_data->data->ID ) ? $user_data->data->ID : '',
				'user_email' => isset( $user_data->data->ID ) ? $user_data->data->user_email : '',
				'first_name' => isset( $user_data->first_name ) ? $user_data->first_name : '',
				'last_name'  => isset( $user_data->last_name ) ? $user_data->last_name : '',
			),
		);

		do_action( MINT_TRIGGER_AUTOMATION, $data );
	}
	/**
	 * WP user login.
	 *
	 * @param string $username Username.
	 * @param Object $user User data.
	 */
	public function mint_wp_login( $username, $user ) {
		$data = array(
			'connector_name' => $this->connector_name,
			'trigger_name'   => 'wp_user_login',
			'data'           => array(
				'user_id'    => isset( $user->data->ID ) ? $user->data->ID : '',
				'user_email' => isset( $user->data->ID ) ? $user->data->user_email : '',
				'first_name' => isset( $user->first_name ) ? $user->first_name : '',
				'last_name'  => isset( $user->last_name ) ? $user->last_name : '',
			),
		);
		do_action( MINT_TRIGGER_AUTOMATION, $data );
	}
}

