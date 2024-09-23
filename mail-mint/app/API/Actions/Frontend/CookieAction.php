<?php
/**
 * Cookie Controller's actions
 *
 * Handles requests to the Frontend endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\API\Actions;

use Mint\MRM\API\Actions\Action;
use Mint\MRM\DataBase\Models\FormModel;

/**
 * This is the class that controls the Cookie action. Responsibilities are:
 * Set cookie for each form
 */
class CookieAction implements Action {

	/**
	 * Set Cookies for Individual form.
	 *
	 * @param array $params Parameter.
	 * @return array
	 * @since 1.0.0
	 */
	public function set_cookie_for_form( $params ) {
        $form_id     = !empty( $params['form_id'] ) && !is_bool( $params['form_id'] ) ? wp_unslash( $params['form_id'] ) : null; //phpcs:ignore
		$cookie_name = $form_id && intval( $form_id ) ? 'mintmail_form_dismissed_' . intval( $form_id ) : null;
		$cookies     = $cookie_name && !empty( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : '';
		if ( '' === $cookies && $cookie_name ) {
			$get_setting  = FormModel::get_meta( $form_id );
			$form_setting = isset( $get_setting[ 'meta_fields' ][ 'settings' ] ) ? $get_setting[ 'meta_fields' ][ 'settings' ] : wp_json_encode( '' );
			$form_setting = $form_setting ? json_decode( $form_setting ) : '';
			$cookie_time  = !empty( $form_setting->settings->extras->cookies_timer ) ? $form_setting->settings->extras->cookies_timer : 7;
			$cookies_data = array(
				'show'   => true,
				'expire' => time() +60 *60 *24 *$cookie_time,
			);
            @setcookie( $cookie_name, wp_json_encode( $cookies_data ), time() +60 *60 *24 *$cookie_time, '/' ); // phpcs:ignore
			return array(
				'status'  => 'success',
				'message' => __( 'Form cookie set successfully', 'mrm' ),
			);
		}
		return array(
			'status'  => 'failed',
			'message' => __( 'Something went wrong. Please try again', 'mrm' ),
		);
	}
}
