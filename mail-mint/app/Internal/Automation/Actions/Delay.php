<?php
/**
 * Automation action class for MRM Autoamtion
 *
 * Class Delay
 *
 * @package MintMail\App\Internal\Automation\Action
 */

namespace MintMail\App\Internal\Automation\Action;

use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\Action\AbstractAutomationAction;
use MintMail\App\Internal\Automation\HelperFunctions;
use MintMail\App\Internal\Automation\ActionScheduler;

/**
 * Delay
 *
 * Class Delay
 *
 * @package MintMail\App\Internal\Automation\Action
 */
class Delay extends AbstractAutomationAction {

	use Singleton;

	/**
	 * Action scheduler.
	 *
	 * @object $action_scheduler
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
		return __( 'Delay', 'mrm' );
	}

	/**
	 * Process.
	 *
	 * @param array $data Get All data.
	 */
	public function process( $data ) {
		if ( $data ) {
			$step_data = HelperFunctions::get_step_data( $data['automation_id'], $data['step_id'] );
			$next_step = HelperFunctions::get_next_step( $data['automation_id'], $data['step_id'] );
			$maybe_run = true;
			if ( $next_step && isset( $next_step['key'] ) && ( 'sendMail' === $next_step['key'] || 'sequence' === $next_step['key'] ) ) {
				if ( !HelperFunctions::maybe_user( $data['data']['user_email'] ) ) {
					$next_step = HelperFunctions::get_next_step( $data['automation_id'], $next_step['step_id'] );
					$maybe_run = false;
				}
			}
			$payload = array(
				'automation_id' => $data['automation_id'],
				'step_id'       => $data['step_id'],
				'identifier'    => $data['identifier'],
				'status'        => 'hold',
				'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
			);
			HelperFunctions::update_log( $payload );
			if ( $maybe_run ) {
				$delay_settings = isset( $step_data['settings']['delay_settings'] ) ? $step_data['settings']['delay_settings'] : array();
				$time           = $this->calculate_seconds( $delay_settings );
				if ( isset( $data['delay_time'] ) || !empty( $data['delay_time'] ) ) {
					$time = $data['delay_time'];
				}
				if ( !$this->validate_delay( $time ) ) {
					$time = 0;
				}
				$payload = array(
					'automation_id' => $data['automation_id'],
					'step_id'       => $data['step_id'],
					'identifier'    => $data['identifier'],
					'status'        => 'completed',
					'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
				);
				HelperFunctions::update_log( $payload );
				HelperFunctions::update_job( $data['automation_id'], isset( $next_step['step_id'] ) ? $next_step['step_id'] : null, isset( $next_step['step_id'] ) ? 'processing' : 'completed' );
			}

			if ( $next_step ) {
				$next_step['data']       = isset( $data['data'] ) ? $data['data'] : array();
				$next_step['identifier'] = $data['identifier'];
				if ( isset( $data['double_optin'] ) ) {
					$next_step['double_optin'] = $data['double_optin'];
				}
				$scheduler_data = array( $next_step );

				if ( $this->action_scheduler->hasScheduledAction( MINT_PROCESS_AUTOMATION ) ) {
					return;
				}
				if ( $maybe_run ) {
					$this->action_scheduler->schedule( time() + $time, MINT_PROCESS_AUTOMATION, $scheduler_data );
				} else {
					if ( !isset( $data['double_optin'] ) ) {
						do_action(MINT_PROCESS_AUTOMATION, $next_step);
					}
				}
			}
		}
	}

	/**
	 * Calculate delay time and convert it into second
	 *
	 * @param array $data get Data .
	 *
	 * @return int
	 */
	private function calculate_seconds( $data ) {
		$type  = isset( $data['unit'] ) ? strtoupper( $data['unit'] ) : 'MINUTES';
		$delay = isset( $data['delay'] ) ? $data['delay'] : 1;

		switch ( $type ) {
			case 'SECONDS':
				return $delay;
			case 'MINUTES':
				return $delay * MINUTE_IN_SECONDS;
			case 'HOURS':
				return $delay * HOUR_IN_SECONDS;
			case 'DAYS':
				return $delay * DAY_IN_SECONDS;
			case 'WEEKS':
				return $delay * WEEK_IN_SECONDS;
			case 'MONTH':
				return $delay * MONTH_IN_SECONDS;
			case 'YEAR':
				return $delay * YEAR_IN_SECONDS;
			default:
				return 0;
		}
	}


	/**
	 * Validate delay time
	 *
	 * @param array $seconds Set in seconds.
	 *
	 * @return bool
	 */
	private function validate_delay( $seconds ) {
		if ( $seconds <= 0 ) {
			return false;
		}
		if ( $seconds > 2 * YEAR_IN_SECONDS ) {
			return false;
		}
		return true;
	}

}
