<?php
/**
 * WordPress triggers
 *
 * @package MintMail\App\Internal\Automation\Connector\trigger
 */

namespace MintMail\App\Internal\Automation\Connector\trigger;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\HelperFunctions;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;

/**
 * WordPress triggers
 *
 * @package MintMail\App\Internal\Automation\Connector
 */
class MintFormTriggers {

	use Singleton;


	/**
	 * Connector name
	 *
	 * @var $connector_name
	 */
	public $connector_name = 'MintForm';


	/**
	 * Initialization of WordPress hooks
	 */
	public function init() {
		add_action( 'mailmint_after_form_submit', array( $this, 'mrm_after_form_submission' ), 10, 3 );
	}


	/**
	 * Validate trigger settings
	 *
	 * @param array $step_data Get Step Data.
	 * @param array $data Get all Data.
	 * @return bool
	 */
	public function validate_settings( $step_data, $data ) {
		$step_data    = HelperFunctions::get_step_data( $step_data['automation_id'], $step_data['step_id'] );
		$trigger_name = isset( $data['trigger_name'] ) ? $data['trigger_name'] : '';

		if ( 'mint_form_submission' === $trigger_name && !empty( $step_data['settings']['mailmint_form_settings']['form_id'] ) ) {
			$automation_id = isset( $step_data['automation_id'] )? $step_data['automation_id'] : '';
			$user_email    = isset( $data['data']['user_email'] )? $data['data']['user_email'] : '';
			if ( HelperFunctions::if_already_in_automation( $user_email, $automation_id ) ){
				$allow_entry = isset( $step_data['settings']['mailmint_form_settings']['allow_entry'] )? $step_data['settings']['mailmint_form_settings']['allow_entry'] : false;
				if ( ! $allow_entry ) {
					return false;
				}
			}

			$form_id          = $step_data['settings']['mailmint_form_settings']['form_id'];
			$settings_form_id = is_array($form_id) && isset($form_id['value']) ? (int)$form_id['value'] : (int)$form_id;
			return $data['data']['form_id'] == $settings_form_id; //phpcs:ignore
		}
		

		return true;
	}


	/**
	 * Mail Mint form submission
	 *
	 * @param int    $form_id Submitted Form ID.
	 * @param int    $contact_id Get Contact ID.
	 * @param object $contact Get contact Array.
	 */
	public function mrm_after_form_submission( $form_id, $contact_id, $contact ) {
		$data = array(
			'connector_name' => $this->connector_name,
			'trigger_name'   => 'mint_form_submission',
			'data'           => array(
				'user_email' => $contact->get_email(),
				'first_name' => $contact->get_first_name(),
				'last_name'  => $contact->get_last_name(),
				'form_id'    => $form_id,
			),
		);
		do_action( MINT_TRIGGER_AUTOMATION, $data );
	}
}

