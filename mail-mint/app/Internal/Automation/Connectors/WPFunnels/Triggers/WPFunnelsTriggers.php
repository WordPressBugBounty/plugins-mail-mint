<?php
/**
 * WPFunnels triggers
 *
 * @package MintMail\App\Internal\Automation\Connector\trigger
 */

namespace MintMail\App\Internal\Automation\Connector\trigger;

use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\HelperFunctions;

/**
 * WPFunnels triggers
 *
 * @package MintMail\App\Internal\Automation\Connector
 */
class WPFunnelsTriggers {

	use Singleton;

	/**
	 * Connector name
	 *
	 * @var $connector_name
	 */
	public $connector_name = 'WPFunnels';

	/**
	 * Initialization of WordPress hooks
	 */
	public function init() {
		add_action( 'wpfunnels_after_funnel_creation', array( $this, 'mint_wpfnl_funnel_created' ), 10, 1 );
	}

	/**
	 * Validate trigger settings
	 *
	 * @param array $step_data Get Step Data.
	 * @param array $data Get all data.
	 * @return bool
	 */
	public function validate_settings( $step_data, $data ) {
		if ( ! isset( $step_data['automation_id'], $step_data['step_id'] ) ) {
			return false;
		}

		$automation_data = HelperFunctions::get_step_data( $step_data['automation_id'], $step_data['step_id'] );
		if ( empty( $automation_data ) ) {
			return false;
		}

		$trigger_name = isset( $data['trigger_name'] ) ? $data['trigger_name'] : '';
		$email        = isset( $data['data']['user_email'] ) ? $data['data']['user_email'] : '';

		if ( 'wpfunnels_funnel_created' !== $trigger_name ) {
			return true;
		}

		$entry_rule = isset( $automation_data['settings']['wpfunnels_funnel_created_settings']['entry'] )
			? $automation_data['settings']['wpfunnels_funnel_created_settings']['entry']
			: 'only_once';

		if ( 'only_once' === $entry_rule ) {
			if ( HelperFunctions::if_already_in_automation( $email, $step_data['automation_id'] ) ) {
				return false;
			}
		} elseif ( 'only_after_exit' === $entry_rule ) {
			if ( HelperFunctions::if_already_in_automation( $email, $step_data['automation_id'] ) ) {
				if ( ! HelperFunctions::if_contact_has_exited_automation( $email, $step_data['automation_id'] ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * WPFunnels funnel created
	 *
	 * @param int $funnel_id Funnel ID.
	 */
	public function mint_wpfnl_funnel_created( $funnel_id ) {
		$funnel_id = absint( $funnel_id );
		if ( ! $funnel_id ) {
			return;
		}

		$funnel_post = get_post( $funnel_id );
		if ( empty( $funnel_post ) ) {
			return;
		}

		$user_id   = isset( $funnel_post->post_author ) ? (int) $funnel_post->post_author : 0;
		$user_data = $user_id ? get_userdata( $user_id ) : null;
		$email     = $user_data && isset( $user_data->user_email ) ? $user_data->user_email : '';

		$data = array(
			'connector_name' => $this->connector_name,
			'trigger_name'   => 'wpfunnels_funnel_created',
			'data'           => array(
				'user_id'      => $user_id,
				'user_email'   => $email,
				'funnel_id'    => $funnel_id,
				'funnel_title' => get_the_title( $funnel_id ),
				'funnel_type'  => get_post_meta( $funnel_id, '_wpfnl_funnel_type', true ),
			),
		);

		do_action( MINT_TRIGGER_AUTOMATION, $data );
	}
}
