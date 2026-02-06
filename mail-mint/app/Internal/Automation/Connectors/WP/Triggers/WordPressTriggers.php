<?php
/**
 * WordPress triggers
 *
 * @package MintMail\App\Internal\Automation\Connector\trigger;
 */

namespace MintMail\App\Internal\Automation\Connector\trigger;

use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\HelperFunctions;
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
		if (!isset($step_data['automation_id'], $step_data['step_id'])) {
			return false;
		}

		$automation_data = HelperFunctions::get_step_data( $step_data['automation_id'], $step_data['step_id'] );
		if (empty($automation_data)) {
			return false;
		}

		$trigger_name = isset($data['trigger_name']) ? $data['trigger_name'] : '';
		$email        = isset($data['data']['user_email']) ? $data['data']['user_email'] : '';
		
		// Determine the settings path based on trigger type
		if ('wp_user_registration' === $trigger_name) {
			$entry_rule = isset($automation_data['settings']['wp_user_registration_settings']['entry']) ? $automation_data['settings']['wp_user_registration_settings']['entry'] : 'only_once';
		} elseif ('wp_user_login' === $trigger_name) {
			$entry_rule = isset($automation_data['settings']['wp_user_login_settings']['entry']) ? $automation_data['settings']['wp_user_login_settings']['entry'] : 'only_once';
		} else {
			return true;
		}
		
		if ('only_once' === $entry_rule) {
			if (HelperFunctions::if_already_in_automation( $email, $step_data['automation_id'] )) {
				return false;
			}
		} elseif ('only_after_exit' === $entry_rule) {
			if (HelperFunctions::if_already_in_automation($email, $step_data['automation_id'])) {
				if (!HelperFunctions::if_contact_has_exited_automation($email, $step_data['automation_id'])) {
					return false;
				}
			}
		}

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

