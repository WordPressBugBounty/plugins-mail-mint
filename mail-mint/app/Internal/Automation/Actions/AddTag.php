<?php
/**
 * Automation action class for MRM Autoamtion
 *
 * Class AddTag
 *
 * @package MintMail\App\Internal\Automation\Action
 */

namespace MintMail\App\Internal\Automation\Action;

use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\Action\AbstractAutomationAction;
use MintMail\App\Internal\Automation\HelperFunctions;
use MintMail\App\Internal\Automation\ActionScheduler;
use Mint\MRM\DataStores\ContactData;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use MRM\Common\MrmCommon;
use Mint\MRM\Admin\API\Controllers\MessageController;
/**
 * AddTag action
 *
 * Class AddTag
 *
 * @package MintMail\App\Internal\Automation\Action
 */
class AddTag extends AbstractAutomationAction {

	use Singleton;
	/**
	 * Action scheduler.
	 *
	 * @var object $action_scheduler
	 */
	private $action_scheduler;

	/**
	 * Initialization
	 */
	public function __construct() {
		$this->action_scheduler = new ActionScheduler();
	}


	/**
	 * Get action name
	 *
	 * @return String
	 * @since  1.0.0
	 */
	public function get_name() {
		return __( 'Add tag', 'mrm' );
	}


	/**
	 * Process.
	 *
	 * @param array $data Get All data.
	 */
	public function process( $data ) {
		if ( $data ) {
			$email         = isset( $data['data']['user_email'] ) ? $data['data']['user_email'] : '';
			$automation_id = isset( $data['automation_id'] ) ? $data['automation_id'] : '';
			$step_id       = isset( $data['step_id'] ) ? $data['step_id'] : '';
			$log_payload   = array(
				'automation_id' => $data['automation_id'],
				'step_id'       => $data['step_id'],
				'status'        => 'hold',
				'identifier'    => $data['identifier'],
				'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
			);
			HelperFunctions::update_log( $log_payload );
			if ( $email && $automation_id && $step_id ) {
				$contact_id = ContactModel::get_id_by_email( $email );
				$step_data  = HelperFunctions::get_step_data( $data['automation_id'], $data['step_id'] );
				if ( $contact_id ) {
					if ( isset( $step_data['settings']['tag_settings']['tags'] ) ) {
						$_items = $this->get_items( $step_data['settings']['tag_settings']['tags'] );
						ContactGroupModel::set_tags_to_contact( $_items, $contact_id );
						$payload = array(
							'automation_id' => $data['automation_id'],
							'step_id'       => $step_id,
							'status'        => 'completed',
							'identifier'    => $data['identifier'],
							'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
						);
						HelperFunctions::update_log( $payload );
					} else {
						$payload = array(
							'automation_id' => $data['automation_id'],
							'step_id'       => $step_id,
							'status'        => 'fail',
							'identifier'    => $data['identifier'],
							'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
						);
						HelperFunctions::update_log( $payload );
					}
				} else {
					$user_data   = array(
						'first_name'  => isset( $data['data']['first_name'] ) ? $data['data']['first_name'] : '',
						'last_name'   => isset( $data['data']['last_name'] ) ? $data['data']['last_name'] : '',
						'meta_fields' => array(
							'phone_number' => isset( $data['data']['phone_number'] ) ? $data['data']['phone_number'] : '',
						),
						'status'      => MrmCommon::is_double_optin_enable() ? 'pending' : 'subscribed',
						'source'      => 'Automation',
					);
					$contact     = new ContactData( $email, $user_data );
					$exist_email = ContactModel::is_contact_exist( $email );
					if ( !$exist_email ) {
						$contact_id = ContactModel::insert( $contact );
						if ( isset( $user_data['status'] ) && 'pending' === $user_data['status'] ) {
							MessageController::get_instance()->send_double_opt_in( $contact_id );
						}
						if ( isset( $step_data['settings']['tag_settings']['tags'] ) ) {
							$_items = $this->get_items( $step_data['settings']['tag_settings']['tags'] );
							ContactGroupModel::set_tags_to_contact( $_items, $contact_id );
							$payload = array(
								'automation_id' => $data['automation_id'],
								'step_id'       => $step_id,
								'status'        => 'completed',
								'identifier'    => $data['identifier'],
								'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
							);
							HelperFunctions::update_log( $payload );
						}
					} else {
						$payload = array(
							'automation_id' => $data['automation_id'],
							'step_id'       => $step_id,
							'status'        => 'fail',
							'identifier'    => $data['identifier'],
							'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
						);
						HelperFunctions::update_log( $payload );
					}
				}
			}

			$next_step = HelperFunctions::get_next_step( $data['automation_id'], $data['step_id'] );
			HelperFunctions::update_job( $data['automation_id'], isset( $next_step['step_id'] ) ? $next_step['step_id'] : null, isset( $next_step['step_id'] ) ? 'processing' : 'completed' );

			$prev_step = HelperFunctions::get_prev_step( $data['automation_id'], $data['step_id'] );
			if ( $next_step ) {
				$next_step['data']       = $data['data'];
				$next_step['identifier'] = $data['identifier'];
				$scheduler_data          = array( $next_step );
				if ( $this->action_scheduler->hasScheduledAction( MINT_PROCESS_AUTOMATION ) ) {
					return;
				}
				$this->action_scheduler->enqueue( MINT_PROCESS_AUTOMATION, $scheduler_data );
			}
		}
	}

	/**
	 * Get tags for assign contact.
	 *
	 * @param array $settings settings.
	 */
	public function get_items( $settings ) {
		if ( is_array( $settings ) ) {
			$formatted_items = array();
			foreach ( $settings as $item ) {
				$formatted_items[] = array(
					'id' => $item['id'],
				);
			}
			return $formatted_items;
		}
	}

}
