<?php
/**
 * Automation manager
 *
 * @package MintMail\App\Internal\Automation
 */

namespace MintMail\App\Internal\Automation;

use Mint\MRM\DataBase\Tables\AutomationLogSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\Action\AutomationAction;
/**
 * Automation manager
 *
 * @package MintMail\App\Internal\Automation
 */
class AutomationManager {
	use Singleton;
	/**
	 * Initialization
	 */
	public function __construct() {
		add_action( MINT_TRIGGER_AUTOMATION, array( $this, 'trigger_automation' ), 10 );
		add_action( MINT_PROCESS_AUTOMATION, array( $this, 'process_automation_data' ), 10 );
		add_action( MINT_AUTOMATION_AFTER_DOUBLE_OPTIN, array( $this, 'process_automation_after_double_optin' ), 10 );
		add_action( 'mailmint_after_confirm_double_optin', array( $this, 'mintmail_contact_status_change' ), 10, 1 );
		add_action( 'mailmint_after_email_open', array( $this, 'process_after_email_open' ), 10, 1 );
		add_action( 'mailmint_after_email_click', array( $this, 'process_after_email_click' ), 10, 1 );
	}


	/**
	 * Trigger automation
	 *
	 * @param array $data data.
	 */
	public function trigger_automation( $data ) {



		if ( isset( $data['trigger_name'], $data['connector_name'] ) ) {
			if ( isset( $data['manually_run_automation'] ) && $data['manually_run_automation'] && !empty( $data['data']['manual_automation_id'] ) ) {
				$automations = HelperFunctions::get_specific_automation_by_trigger( $data['trigger_name'], $data['data']['manual_automation_id'] );
			} else {
				$automations = HelperFunctions::get_all_automations_by_tigger( $data['trigger_name'] );
			}

			if ( is_array( $automations ) && !empty( $automations ) ) {
				foreach ( $automations as $automation ) {
					if ( isset( $automation['id'] ) ) {
						$maybe_run = true;

						if ( isset( $data['trigger_name'] ) && 'wc_abandoned_cart' === $data['trigger_name'] ) {
							// Trigger the 'mint_abandoned_cart_updated_after_automation_trigger' filter and pass the $data and $automation parameters.
							$maybe_run = apply_filters( 'mint_abandoned_cart_updated_after_automation_trigger', $data, $automation );
						}

						if ( !$maybe_run ) {
							continue;
						}

						$step_data = HelperFunctions::get_next_step( $automation['id'] );

						if ( isset( $data['trigger_name'] ) && 'wpcf7_submit' === $data['trigger_name'] ) {
							/**
							 * Filters the data mapped from Contact Form 7 fields before processing.
							 *
							 * This filter allows modification of the data array before it is processed for a specific step in an automation.
							 *
							 * @param array $data      The data array mapped from Contact Form 7 fields.
							 * @param array $step_data Additional data related to the step or automation.
							 *
							 * @return array The filtered data array.
							 * @since 1.5.19
							 */
							$data = apply_filters( 'mint_contact_form_7_fields_map', $data, $step_data );
						}

						if ( isset( $data['trigger_name'] ) && 'bricks_form_submit' === $data['trigger_name'] ) {
							/**
							 * Filters the data mapped from Bricks Form fields before processing.
							 *
							 * This filter allows modification of the data array before it is processed for a specific step in an automation.
							 *
							 * @param array $data      The data array mapped from Bricks Form fields.
							 * @param array $step_data Additional data related to the step or automation.
							 *
							 * @return array The filtered data array.
							 * @since 1.14.0
							 */
							$data = apply_filters( 'mint_bricks_form_fields_map', $data, $step_data );
						}

						if (isset($data['trigger_name']) && 'fluentform_submission_inserted' === $data['trigger_name']) {
							/**
							 * Filters the data mapped from Fluent Form fields before processing.
							 *
							 * This filter allows modification of the data array before it is processed for a specific step in an automation.
							 *
							 * @param array $data      The data array mapped from Fluent Form fields.
							 * @param array $step_data Additional data related to the step or automation.
							 *
							 * @return array The filtered data array.
							 * @since 1.14.0
							 */
							$data = apply_filters('mint_fluent_form_fields_map', $data, $step_data);
						}


						if (isset($data['trigger_name']) && 'wpforms_submission_inserted' === $data['trigger_name']) {
							/**
							 * Filters the data mapped from WPForms fields before processing.
							 *
							 * This filter allows modification of the data array before it is processed for a specific step in an automation.
							 *
							 * @param array $data      The data array mapped from WPForms fields.
							 * @param array $step_data Additional data related to the step or automation.
							 *
							 * @return array The filtered data array.
							 * @since 1.16.2
							 */
							$data = apply_filters('mint_wpforms_form_fields_map', $data, $step_data);

						}

						if (isset($data['trigger_name']) && 'gform_after_submission' === $data['trigger_name']) {
							/**
							 * Filters the data mapped from Fluent Form fields before processing.
							 *
							 * This filter allows modification of the data array before it is processed for a specific step in an automation.
							 *
							 * @param array $data      The data array mapped from Fluent Form fields.
							 * @param array $step_data Additional data related to the step or automation.
							 *
							 * @return array The filtered data array.
							 * @since 1.14.0
							 */
							$data = apply_filters('mint_gravity_form_fields_map', $data, $step_data);
						}

						if (isset($data['trigger_name']) && 'jetform_after_submit' === $data['trigger_name']) {
							/**
							 * Filters the data mapped from JetFormBuilder fields before processing.
							 *
							 * This filter allows modification of the data array before it is processed for a specific step in an automation.
							 *
							 * @param array $data      The data array mapped from JetFormBuilder fields.
							 * @param array $step_data Additional data related to the step or automation.
							 *
							 * @return array The filtered data array.
							 * @since 1.14.0
							 */
							$data = apply_filters('mint_jet_form_builder_fields_map', $data, $step_data);
						}

						if ( isset( $data['trigger_name'] ) && 'wc_price_dropped' === $data['trigger_name'] ) {
							/**
							 * Apply filters for actions after a WooCommerce price drop event.
							 *
							 * This function applies the 'mint_after_wc_price_drop_event' filter to the provided data,
							 * allowing other functions or plugins to modify the data after a WooCommerce price drop event.
							 *
							 * @param mixed $data      The data related to the WooCommerce price drop event.
							 * @param array $step_data Additional data related to the specific step of the event.
							 *
							 * @return mixed The filtered data after applying modifications.
							 * @since 1.8.1
							 */
							$data = apply_filters( 'mint_after_wc_price_drop_event', $data, $step_data );
						}



						if ( is_array( $step_data ) ) {
							if ( isset( $step_data['step_type'], $step_data['step_id'] ) && 'trigger' === $step_data['step_type'] ) {
								$maybe_validate_trigger_settings = true;

								$class_name                      = "MintMail\\App\\Internal\\Automation\\Connector\\trigger\\" . $data['connector_name'] . 'Triggers';

								if ( class_exists( $class_name ) ) {
									$maybe_validate_trigger_settings = $class_name::get_instance()->validate_settings( $step_data, $data );
								}
								if ( !$maybe_validate_trigger_settings ) {
									continue;
								}
								$identifier = uniqid();
								$log_data   = array(
									'status'     => 'processing',
									'email'      => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
									'identifier' => $identifier,
								);
								HelperFunctions::update_automation_steps_status( $automation['id'], $log_data );
								$payload = array(
									'automation_id' => $automation['id'],
									'step_id'       => $step_data['step_id'],
									'status'        => 'completed',
									'email'         => !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '',
									'identifier'    => $identifier,
								);
								HelperFunctions::update_log( $payload );
								$step_data = HelperFunctions::get_next_step( $automation['id'], $step_data['step_id'] );

								if ( $step_data && is_array( $step_data ) ) {
									$_data = array(
										'step_id'       => $step_data['step_id'],
										'automation_id' => $automation['id'],
										'identifier'    => $identifier,
										'data'          => $data['data'],
									);

									$key = $step_data['key'];

									if ( 'wc_price_dropped' === $data['trigger_name'] && isset( $data['recipients'] ) && !empty( $data['recipients'] ) ) {
										// Maximum execution time for the script.
										$max_execution_time = 30;
										// Start time for the script.
										$start_time = time();

										foreach ( $data['recipients'] as $recipient ) {
											$_data['data']['user_email'] = $recipient['email'];
											$this->action_process( $_data, $key );

											// Check the elapsed time and introduce a delay if needed.
											$elapsed_time = time() - $start_time;
											if ( $elapsed_time > $max_execution_time ) {
												// Introduce a delay (e.g., 1 second) to avoid script timeout.
												sleep( 1 );
												// Reset start time for the next iteration.
												$start_time = time();
											}
										}
									}

									if ( 'wc_price_dropped' !== $data['trigger_name'] ) {
										$this->action_process( $_data, $key );
									}
								}
							}
						}
					}
				}
			}
		}
	}


	/**
	 * Process automation after double opt-in
	 *
	 * @param array $data Data.
	 * @return void
	 * @since 1.0.0
	 */
	public function process_automation_after_double_optin( $data ) {
		if ( $data && is_array( $data ) && isset( $data['automation_id'], $data['step_id'] ) ) {
			$is_active = HelperFunctions::step_exist_with_active_automation( $data['automation_id'], $data['step_id'] );
			if ( !$is_active ) {
				return;
			}
			$prev_step = HelperFunctions::get_prev_step( $data['automation_id'], $data['step_id'] );

			if ( $prev_step && isset( $prev_step['key'] ) && ( 'stopAutomation' === $prev_step['key'] ) ) {
				global $wpdb;
				$log_table = $wpdb->prefix . AutomationLogSchema::$table_name;

				$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$log_table} WHERE identifier = %s AND step_id = %s AND status = %s", $data['identifier'], $prev_step['step_id'], 'completed' ) );

				if ( $id ) {
					return;
				}
			}

			if ( $prev_step && isset( $prev_step['key'] ) && ( 'delay' === $prev_step['key'] || 'specificTimeDelay' === $prev_step['key'] ) ) {
				$data['step_id'] = $prev_step['step_id'];
				$data['key']     = $prev_step['key'];
			}

			$step_data = array(
				'step_id'       => $data['step_id'],
				'automation_id' => $data['automation_id'],
				'identifier'    => $data['identifier'],
				'data'          => $data['data'],
				'double_optin'  => 'yes',
			);
			if ( isset( $data['delay_time'] ) || !empty( $data['delay_time'] ) ) {
				$step_data['delay_time'] = $data['delay_time'];
			}
			$key = $data['key'];
			$this->action_process( $step_data, $key );
		}
	}


	/**
	 * Process automation data;
	 *
	 * @param array $data Get All data .
	 */
	public function process_automation_data( $data ) {
		/**
		 * Applies filters before processing an automation step.
		 *
		 * This function applies filters to the provided data before processing it
		 * in an automation step. The filters are hooked to the 'mailmint_before_automation_step_process' action.
		 *
		 * @param mixed $data The data to be processed in the automation step.
		 * @return mixed The filtered data after applying the filters.
		 */
		$data = apply_filters( 'mailmint_before_automation_step_process', $data );
		if ( $data && is_array( $data ) && isset( $data['automation_id'], $data['step_id'] ) ) {
			$is_active = HelperFunctions::step_exist_with_active_automation( $data['automation_id'], $data['step_id'] );
			if ( !$is_active ) {
				return;
			}
			$step_data = array(
				'step_id'       => $data['step_id'],
				'automation_id' => $data['automation_id'],
				'identifier'    => $data['identifier'],
				'data'          => $data['data'],
			);
			$key       = $data['key'];
			if ( isset( $data['double_optin'] ) ) {
				$step_data['double_optin'] = $data['double_optin'];
			}
			if ( isset( $data['abandoned_id'] ) ) {
				$step_data['abandoned_id'] = $data['abandoned_id'];
			}
			$this->action_process( $step_data, $key );
		}
	}


	/**
	 * Create action instance and process data.
	 *
	 * @param array $data Get All data .
	 * @param array $key Get All data .
	 */
	private function action_process( $data, $key ) {
		if ( $data && $key ) {
			AutomationAction::get_instance()->init( $key, $data );
		}
	}

	/**
	 * Perform after contact status has been changed
	 *
	 * @param array $contact Contact data.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function mintmail_contact_status_change( $contact ) {
		$contact_email = isset( $contact['email'] ) ? $contact['email'] : '';
		$status        = isset( $contact['status'] ) ? $contact['status'] : 'pending';
		if ( $contact_email && 'pending' === $status ) {
			$status         = array(
				'processing',
				'hold',
			);
			$automation_log = HelperFunctions::get_automaiton_log_data_by_email( $contact_email, $status );
			if ( is_array( $automation_log ) && !empty( $automation_log ) ) {
				$delay_time = 0;
				foreach ( $automation_log as $log ) {
					if ( !empty( $log['automation_id'] ) && !empty( $log['step_id'] ) ) {
						$step_id       = $log['step_id'];
						$automation_id = $log['automation_id'];
						$identifier    = $log['identifier'];
						$step_data     = HelperFunctions::get_step_data( $automation_id, $step_id );
						if ( isset( $step_data['key'] ) && ( 'sendMail' === $step_data['key'] || 'sequence' === $step_data['key'] ) ) {
							$data = array(
								'automation_id' => $automation_id,
								'step_id'       => $step_id,
								'identifier'    => $identifier,
								'key'           => $step_data['key'],
								'data'          => array(
									'user_email' => $contact_email,
								),

							);
							$prev_step = HelperFunctions::get_prev_step( $data['automation_id'], $data['step_id'] );

							if ( $prev_step && isset( $prev_step['key'] ) && ( 'delay' === $prev_step['key'] ) ) {
								$step_data      = HelperFunctions::get_step_data( $prev_step['automation_id'], $prev_step['step_id'] );
								$delay_settings = isset( $step_data['settings']['delay_settings'] ) ? $step_data['settings']['delay_settings'] : array();
								$time           = $this->calculate_seconds( $delay_settings );
								if ( !$this->validate_delay( $time ) ) {
									$time = 0;
								}
								$delay_time         = $delay_time + $time;
								$data['delay_time'] = $delay_time;
							}
							do_action( MINT_AUTOMATION_AFTER_DOUBLE_OPTIN, $data );
						}
					}
				}
			}
		}
	}

	/**
	 * Processes the action hook 'mailmint_after_email_open'
	 * by retrieving automation log data and triggering automation after email open.
	 *
	 * @param int $email_id The ID of the email that was opened.
	 *
	 * @return void
	 * @since 1.2.6
	 */
	public function process_after_email_open( $email_id ) {
		$email_address = HelperFunctions::get_email_address_by_email_id( $email_id );

		$status = array(
			'processing',
			'hold',
		);

		$automation_log = HelperFunctions::get_automaiton_log_data_by_email( $email_address, $status );

		if ( is_array( $automation_log ) && !empty( $automation_log ) ) {
			foreach ( $automation_log as $log ) {
				if ( !empty( $log['automation_id'] ) && !empty( $log['step_id'] ) ) {
					$step_id       = $log['step_id'];
					$automation_id = $log['automation_id'];
					$identifier    = $log['identifier'];
					$step_data     = HelperFunctions::get_step_data( $automation_id, $step_id );
					if ( isset( $step_data['key'] ) && ( 'condition' === $step_data['key'] ) ) {
						$data = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_id,
							'identifier'    => $identifier,
							'key'           => $step_data['key'],
							'data'          => array(
								'user_email' => $email_address,
							),

						);

						$this->process_automation_after_email_open( $data );
					}
				}
			}
		}
		// Unset the variable to free up memory.
		unset( $automation_log );
	}

	/**
	 * Process automation step after email is opened.
	 *
	 * @param array $data An array of data related to the automation step.
	 *
	 * @return void
	 * @since 1.2.6
	 */
	public function process_automation_after_email_open( $data ) {
		if ( $data && is_array( $data ) ) {
			$step_data = array(
				'step_id'       => $data['step_id'],
				'automation_id' => $data['automation_id'],
				'identifier'    => $data['identifier'],
				'data'          => $data['data'],
				'email_opened'  => 'yes',
			);
			$key       = $data['key'];
			$this->action_process( $step_data, $key );
		}
		do_action( MINT_AUTOMATION_AFTER_EMAIL_OPEN, $data );
	}

	/**
	 * Process automation step after email is clicked.
	 *
	 * @param array $data An array of data related to the automation step.
	 *
	 * @return void
	 * @since 1.2.6
	 */
	public function process_automation_after_email_click( $data ) {
		if ( $data && is_array( $data ) && isset( $data['automation_id'], $data['step_id'] ) ) {
			$is_active = HelperFunctions::step_exist_with_active_automation( $data['automation_id'], $data['step_id'] );
			if ( !$is_active ) {
				return;
			}
			$step_data = array(
				'step_id'       => $data['step_id'],
				'automation_id' => $data['automation_id'],
				'identifier'    => $data['identifier'],
				'data'          => $data['data'],
				'email_clicked' => 'yes',
			);
			$key       = $data['key'];
			$this->action_process( $step_data, $key );
		}

		do_action( MINT_AUTOMATION_AFTER_EMAIL_CLICK, $data );
	}

	/**
	 * Processes the action hook 'mailmint_after_email_click'
	 * by retrieving automation log data and triggering automation after email click.
	 *
	 * @param int $email_id The ID of the email that was clicked.
	 *
	 * @return void
	 * @since 1.2.6
	 */
	public function process_after_email_click( $email_id ) {
		$email_address = HelperFunctions::get_email_address_by_email_id( $email_id );

		$status = array(
			'processing',
			'hold',
		);

		$automation_log = HelperFunctions::get_automaiton_log_data_by_email( $email_address, $status );

		if ( is_array( $automation_log ) && !empty( $automation_log ) ) {
			foreach ( $automation_log as $log ) {
				if ( !empty( $log['automation_id'] ) && !empty( $log['step_id'] ) ) {
					$step_id       = $log['step_id'];
					$automation_id = $log['automation_id'];
					$identifier    = $log['identifier'];
					$step_data     = HelperFunctions::get_step_data( $automation_id, $step_id );
					if ( isset( $step_data['key'] ) && ( 'condition' === $step_data['key'] ) ) {
						$data = array(
							'automation_id' => $automation_id,
							'step_id'       => $step_id,
							'identifier'    => $identifier,
							'key'           => $step_data['key'],
							'data'          => array(
								'user_email' => $email_address,
							),

						);

						$this->process_automation_after_email_click( $data );
					}
				}
			}
		}
		// Unset the variable to free up memory.
		unset( $automation_log );
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
			case 'MINUTES':
				return $delay * MINUTE_IN_SECONDS;
			case 'HOURS':
				return $delay * HOUR_IN_SECONDS;
			case 'DAYS':
				return $delay * DAY_IN_SECONDS;
			case 'WEEKS':
				return $delay * WEEK_IN_SECONDS;
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
