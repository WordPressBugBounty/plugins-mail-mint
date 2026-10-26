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
use MintMail\App\Internal\Automation\HelperFunctions;

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
		$class_name = 'MintMail\\App\\Internal\\Automation\\Action\\' . ucfirst( $action );
		if ( isset( $this->actions[ $action ] ) && class_exists( $class_name ) ) {
			$class_name::get_instance()->process( $data );
			return;
		}

		$this->skip_unavailable_action( $data );
	}

	/**
	 * Fail an action step that has no handler and move on to the next step.
	 *
	 * The step was saved while its handler was available, e.g. by a newer Mail Mint Pro
	 * or with an integration plugin that is now inactive. Each action moves the contact
	 * on itself, so without this the contact would stay on the step for good.
	 *
	 * @param array $data Step data.
	 *
	 * @return void
	 * @since 1.31.7
	 */
	private function skip_unavailable_action( $data ) {
		if ( ! isset( $data['automation_id'], $data['step_id'] ) ) {
			return;
		}

		$automation_id = $data['automation_id'];
		$step_id       = $data['step_id'];
		$step_data     = HelperFunctions::get_step_data( $automation_id, $step_id );

		// Triggers and other non-action steps are not run through here.
		if ( empty( $step_data['step_type'] ) || 'action' !== $step_data['step_type'] ) {
			return;
		}

		$log_payload = array(
			'automation_id' => $automation_id,
			'step_id'       => $step_id,
			'status'        => 'fail',
			'identifier'    => isset( $data['identifier'] ) ? $data['identifier'] : '',
			'email'         => isset( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
		);
		HelperFunctions::update_log( $log_payload );

		if ( ! isset( $data['double_optin'] ) ) {
			$next_step = HelperFunctions::get_next_step( $automation_id, $step_id );
			HelperFunctions::update_job( $automation_id, isset( $next_step['step_id'] ) ? $next_step['step_id'] : null, isset( $next_step['step_id'] ) ? 'processing' : 'completed' );
			if ( $next_step ) {
				$next_step['data']       = $data['data'];
				$next_step['identifier'] = $data['identifier'];
				do_action( MINT_PROCESS_AUTOMATION, $next_step );
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
