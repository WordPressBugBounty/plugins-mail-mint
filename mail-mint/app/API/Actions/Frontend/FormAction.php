<?php
/**
 * Form Controller's actions
 *
 * Handles requests to the Frontend endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\API\Actions;

use MailMintPro\App\Utilities\Helper\Integration;
use Mint\MRM\Internal\Constants;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use MRM\Common\MrmCommon;
use Mint\MRM\DataStores\ContactData;
use Mint\MRM\DataBase\Models\FormModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\Admin\API\Controllers\MessageController;

/**
 * This is the class that controls the Form action. Responsibilities are:
 * Handle form submission
 */
class FormAction implements Action {

	/**
	 * Handle form submission
	 *
	 * @param array $params Parameter.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function handle_form_submission( $params ) {
		$response      = array(
			'status'  => 'failed',
			'message' => 'Form is not valid',
		);
		$get_post_data = isset( $params['post_data'] ) ? wp_unslash( $params['post_data'] ) : '';
		parse_str( $get_post_data, $post_data );

		$form_data                = array();
		$form_data['meta_fields'] = array();
		if ( $post_data ) {
			foreach ( $post_data as $key => $value ) {
				if ( 'email' === $key ) {
					$form_data['email'] = sanitize_email( $value );
				} elseif ( 'last_name' === $key ) {
					$form_data['last_name'] = sanitize_text_field( $value );
				} elseif ( 'first_name' === $key ) {
					$form_data['first_name'] = sanitize_text_field( $value );
				} elseif ( 'form_id' === $key ) {
					$form_data['form_id'] = intval( $value );
				} else {
					$form_data['meta_fields'][ $key ] = $value;
				}
			}
		}
		$form_id = isset( $form_data['form_id'] ) ? $form_data['form_id'] : 0;

		if ( !$form_id ) {
			return $response;
		}
		/**
		 * Recaptch Add.
		 */
		$recaptcha_settings = get_option( '_mint_recaptcha_settings' );
		$recaptch_is_enable = isset( $recaptcha_settings['enable'] ) ? $recaptcha_settings['enable'] : false;
		if ( $recaptch_is_enable ) {
			if ( isset( $post_data['g-recaptcha-response'] ) && empty( $post_data['g-recaptcha-response'] ) ) {
				$response = array(
					'status'  => 'failed',
					'message' => 'reCAPTCHA is required.',
				);
				return $response;
			} else {
				$token              = isset( $post_data['g-recaptcha-response'] ) ? $post_data['g-recaptcha-response'] : '';
				$recapthca_response = MrmCommon::get_recaptcha_response( $token );
				if ( !$recapthca_response ) {
					$response = array(
						'status'  => 'failed',
						'message' => 'ReCaptcha is not valid',
					);
					return $response;
				}
			}
		}

		$form_email = isset( $form_data['email'] ) ? $form_data['email'] : '';
		if ( ! $form_email ) {
			$response['status']  = 'failed';
			$response['message'] = __( "<p style='color: #F9B333'> Email Field Not found.</p>", 'mrm' );
			return $response;
		}

		$settings = get_option( '_mint_integration_settings', array(
            'zero_bounce' => array(
                'api_key' => '',
                'email_address' => '',
                'is_integrated' => false,
            ),
        ) );

        $zero_bounce   = isset( $settings['zero_bounce'] ) ? $settings['zero_bounce'] : array();
		$api_key       = isset( $zero_bounce['api_key'] ) ? $zero_bounce['api_key'] : '';
		$is_integrated = isset( $zero_bounce['is_integrated'] ) ? $zero_bounce['is_integrated'] : false;

		if( $is_integrated ) {
			$response = Integration::handle_zero_bounce_request( $api_key, $form_email );

			if( isset($response['body']['status'] ) && 'invalid' === $response['body']['status'] ){
				$response = array(
					'status'  => 'failed',
					'message' => __( 'The email address does not exist. Please check the spelling and try again.', 'mrm' ),
				);
				return $response;
			}
		}

		$parms = array(
			'first_name' => isset( $form_data['first_name'] ) ? $form_data['first_name'] : '',
			'last_name'  => isset( $form_data['last_name'] ) ? $form_data['last_name'] : '',
			'source'     => 'Form-' . $form_id,
			'status'     => MrmCommon::is_double_optin_enable() ? 'pending' : 'subscribed',
		);

		$get_group_id = FormModel::get( $form_id );
		$group_ids    = isset( $get_group_id['group_ids'] ) ? unserialize( $get_group_id['group_ids'] ) : array(); //phpcs:ignore
		$group_tag    = isset( $group_ids['tags'] ) ? $group_ids['tags'] : array();
		$group_list   = isset( $group_ids['lists'] ) ? $group_ids['lists'] : array();
		$group_data   = array_merge( $group_tag, $group_list );
		$ids          = array();
		foreach ( $group_data as $id ) {
			$ids[] = $id['id'];
		}

		/**
		 * After form submit
		 * get status and check this will done after submit form
		 */
		$get_setting               = FormModel::get_meta( $form_id );
		$form_setting              = isset( $get_setting['meta_fields']['settings'] ) ? $get_setting['meta_fields']['settings'] : array();
		$form_setting              = $form_setting && is_string( $form_setting ) ? json_decode( $form_setting ) : array();
		$show_always               = isset( $form_setting->settings->extras->show_always ) ? $form_setting->settings->extras->show_always : true;
		$allow_multiple            = isset( $form_setting->settings->extras->allow_automation_multiple ) ? $form_setting->settings->extras->allow_automation_multiple : true;
		$enable_admin_notification = isset( $form_setting->settings->admin_notification->enable ) ? $form_setting->settings->admin_notification->enable : false;
		if ( $enable_admin_notification ) {
			$admin_email      = isset( $form_setting->settings->admin_notification->admin_email ) ? $form_setting->settings->admin_notification->admin_email : get_option( 'admin_email' );
			$admin_subject    = isset( $form_setting->settings->admin_notification->admin_subject ) ? $form_setting->settings->admin_notification->admin_subject : '';
			$admin_email_body = isset( $form_setting->settings->admin_notification->admin_email_body ) ? $form_setting->settings->admin_notification->admin_email_body : '';
			if ( '' !== $admin_email ) {
				$this->send_admin_notification( $admin_email, $form_data, $admin_subject, $admin_email_body );
			}
		}
		if ( !$show_always ) {
			$cookie_time  = !empty( $form_setting->settings->extras->cookies_timer ) ? $form_setting->settings->extras->cookies_timer : 7;
			$time         = time() +60 *60 *24 *$cookie_time;
			$cookie_name  = 'mintmail_form_dismissed_' . $form_id;
			$cookie_value = (object) array(
				'show'   => 1,
				'expire' => $time,
			);
			if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
				setcookie( $cookie_name, wp_json_encode( $cookie_value ), $time, '/' );
			}
		}

		$confirmation_type = isset( $form_setting->settings->confirmation_type ) ? $form_setting->settings->confirmation_type : array();
		if ( ! empty( $confirmation_type->same_page ) ) {
			$same_page                         = $confirmation_type->same_page;
			$response['confirmation_type']     = 'same_page';
			$response['after_form_submission'] = $same_page->after_form_submission;
			$response['message']               = $same_page->message_to_show;
		}
		if ( ! empty( $confirmation_type->to_a_page ) ) {
			$to_a_page = $confirmation_type->to_a_page;
			$page_id   = false;
			if ( property_exists( $to_a_page->page, 'value' ) ) {
				$page_id = $to_a_page->page->value;
			}
			$response['confirmation_type'] = 'to_a_page';
			$response['redirect_page']     = get_permalink( $page_id );
			$response['message']           = $to_a_page->redirection_message;
		}
		if ( ! empty( $confirmation_type->to_a_custom_url ) ) {
			$to_a_custom_url               = $confirmation_type->to_a_custom_url;
			$response['confirmation_type'] = 'to_a_custom_url';
			$response['custom_url']        = $to_a_custom_url->custom_url;
			$response['message']           = $to_a_custom_url->custom_redirection_message;
		}
		if ( ! empty( $confirmation_type->download ) ) {
			$download = $confirmation_type->download;

			$file_url = wp_get_attachment_url($download->attachment_id);

			$response['confirmation_type']     = 'download';
			$response['file_url']              = $file_url;
			$response['file_name']             = $download->attachment_name;
			$response['message']               = $download->success_message;
			$response['after_form_submission'] = $download->after_form_submission;
		}
		$contact     = new ContactData( $form_email, $parms );
		$exist_email = ContactModel::is_contact_exist( $form_email );
		if ( $exist_email ) {
			$this->process_form_submission_for_existing_contact( $contact, $form_email, $form_id, $form_data, $ids, $allow_multiple );
			$response['status'] = 'success';

			/**
			 * Filter Hook: mailmint_after_form_submit_response
			 *
			 * Allows developers to modify the response after a form submission in MailMint.
			 *
			 * This filter hook provides the ability to customize or manipulate the response data
			 * received after a form submission in MailMint before it's processed further or displayed.
			 *
			 * @since 1.5.17
			 *
			 * @param mixed  $response The response data after form submission in MailMint.
			 * @param int    $form_id  The ID of the form submitted.
			 * @param object $contact  The contact object or data associated with the form submission.
			 *
			 * @return mixed The modified response data after applying filters.
			 */
			return apply_filters( 'mailmint_after_form_submit_response', $response, $form_id, $contact );
		}
		do_action( 'mailmint_before_form_submit', $form_id, $contact );
		$contact_id = ContactModel::insert( $contact );

		if ( $contact_id ) {
			/**
			 * Send Double Optin Email
			 */
			MessageController::get_instance()->send_double_opt_in( $contact_id );

			$entries       = FormModel::get_form_meta_value_with_key( $form_id, 'entries' );
			$entries_count = isset( $entries['meta_fields']['entries'] ) ? $entries['meta_fields']['entries'] : 0;

			$args['meta_fields'] = array(
				'entries' => $entries_count + 1,
			);
			FormModel::update_meta_fields( $form_id, $args );

			/**
			 * Assign Tag and List for contact
			 */

			ContactGroupModel::set_tags_to_contact( $ids, $contact_id );
			ContactGroupModel::set_lists_to_contact( $ids, $contact_id );
			$meta_fields['meta_fields'] = isset( $form_data['meta_fields'] ) ? $form_data['meta_fields'] : array();
			$is_ip_store                = get_option( '_mint_compliance' );
			$is_ip_store                = isset( $is_ip_store['anonymize_ip'] ) ? $is_ip_store['anonymize_ip'] : 'no';
			if ( 'no' === $is_ip_store ) {
				$meta_fields['meta_fields']['_ip_address'] = MrmCommon::get_user_ip(); //phpcs:ignore
			}
			$meta_fields['meta_fields']['_form_id'] = $form_id;
			ContactModel::update_meta_fields( $contact_id, $meta_fields );

			$response['status'] = 'success';

			/**
			 * Fires after a form submission in the MailMint plugin.
			 *
			 * @param int    $form_id    The ID of the submitted form.
			 * @param int    $contact_id The ID of the contact associated with the form submission.
			 * @param object $contact    The contact object containing information about the submitted form.
			 *
			 * @since 1.5.0
			 */
			do_action( 'mailmint_after_form_submit', $form_id, $contact_id, $contact );
			/**
			 * This filter was documented in the upper section where it was first initialized
			 */
			return apply_filters( 'mailmint_after_form_submit_response', $response, $form_id, $contact );
		}

		/**
		 * This filter was documented in the upper section where it was first initialized
		 */
		return apply_filters( 'mailmint_after_form_submit_response', $response, $form_id, $contact );
	}

	/**
	 * Process form submission.
	 *
	 * @param string $contact The contact from the submitted form.
	 * @param string $form_email The email from the submitted form.
	 * @param int    $form_id The ID of the submitted form.
	 * @param array  $form_data The data submitted in the form.
	 * @param array  $ids The array of IDs associated with the contact.
	 * @param array  $allow_multiple The array of IDs associated with the contact.
	 * @return void
	 * @since 1.5.0
	 */
	public function process_form_submission_for_existing_contact( $contact, $form_email, $form_id, $form_data, $ids, $allow_multiple ) {
		$contact_id = ContactModel::get_id_by_email( $form_email );
		if ( $allow_multiple ) {
			/**
			 * Fires after a form submission in the MailMint plugin.
			 *
			 * @param int    $form_id    The ID of the submitted form.
			 * @param int    $contact_id The ID of the contact associated with the form submission.
			 * @param object $contact    The contact object containing information about the submitted form.
			 *
			 * @since 1.5.0
			 */
			do_action( 'mailmint_after_form_submit', $form_id, $contact_id, $contact );
		}
		$contact_data = ContactModel::get( $contact_id );

		if ( $contact_data ) {
			$this->update_contact_data( $contact_data, $contact_id, $form_data, $form_id, $ids );
		}
	}

	/**
	 * Update contact data.
	 *
	 * @param array $contact_data The ID of the contact to update.
	 * @param int   $contact_id The ID of the contact to update.
	 * @param array $form_data The data submitted in the form.
	 * @param int   $form_id The ID of the submitted form.
	 * @param array $ids The array of IDs associated with the contact.
	 * @return void
	 * @since 1.5.0
	 */
	public function update_contact_data( $contact_data, $contact_id, $form_data, $form_id, $ids ) {
		$first_name = !empty( $contact_data['first_name'] ) ? $contact_data['first_name'] : '';
		$last_name  = !empty( $contact_data['last_name'] ) ? $contact_data['last_name'] : '';
		$status     = !empty( $contact_data['status'] ) ? $contact_data['status'] : '';
		if ( 'unsubscribed' === $status ) {
			$status = MrmCommon::is_double_optin_enable() ? 'pending' : 'subscribed';
			/**
			 * Send Double Optin Email
			 */
			MessageController::get_instance()->send_double_opt_in( $contact_id );
		}
		$meta_fields         = !empty( $contact_data['meta_fields'] ) ? $contact_data['meta_fields'] : array();
		$args['first_name']  = !empty( $form_data['first_name'] ) ? $form_data['first_name'] : $first_name;
		$args['last_name']   = !empty( $form_data['last_name'] ) ? $form_data['last_name'] : $last_name;
		$args['status']      = $status;
		$args['meta_fields'] = !empty( $form_data['meta_fields'] ) ? $form_data['meta_fields'] : $meta_fields;

		ContactModel::update( $args, $contact_id );
		ContactModel::insert_form_submission( $contact_id, $form_id );
		ContactModel::update_meta_fields( $contact_id, $args );
		ContactGroupModel::set_tags_to_contact( $ids, $contact_id );
		ContactGroupModel::set_lists_to_contact( $ids, $contact_id );
	}


	/**
	 * Sends an admin notification.
	 *
	 * @param string $admin_email Admin email address.
	 * @param array  $form_data   Form data.
	 * @param string $admin_subject   Admin Subject.
	 * @param string $admin_email_body   Form data.
	 *
	 * @return bool True if the email was sent successfully, false otherwise.
	 *
	 * @since 1.5.3
	 */
	public function send_admin_notification( $admin_email, $form_data, $admin_subject, $admin_email_body ) {
		if ( empty( $admin_email ) || empty( $form_data ) ) {
			return false;
		}
		$subject    = $this->get_email_subject( $form_data, $admin_subject );
		$email      = trim( $admin_email );
		$email_body = $this->compose_email_body( $form_data, $email, $admin_email_body );
		$headers    = $this->get_email_headers( $email );

		if ( $email ) {
			return MM()->mailer->send( $email, $subject, $email_body, $headers );
		}
	}

	/**
	 * Gets the email subject.
	 *
	 * @param array  $form_data Form data.
	 * @param string $admin_subject Admin subject.
	 *
	 * @return string Email subject.
	 *
	 * @since 1.5.3
	 */
	public function get_email_subject( $form_data, $admin_subject ) {
		return $admin_subject;
	}

	/**
	 * Composes the email body.
	 *
	 * @param array  $form_data Form data.
	 * @param string $email     Admin email address.
	 * @param string $admin_email_body     Admin email address.
	 *
	 * @return string Composed email body.
	 *
	 * @since 1.5.3
	 */
	public function compose_email_body( $form_data, $email, $admin_email_body ) {
		$info  = $this->get_content_info( $form_data, $admin_email_body );
		$info .= $this->get_signature_info( $email );
		return $info;
	}

	/**
	 * Get content information for an email.
	 *
	 * @param array  $form_data        The form data.
	 * @param string $admin_email_body The email body template.
	 *
	 * @return string The content information for the email.
	 * @since 1.5.5
	 */
	public function get_content_info( $form_data, $admin_email_body ) {
		$data  = '<br>' . $this->get_basic_info( $form_data );
		$data .= $this->get_meta_info( $form_data ) . '<br>';
		$info  = str_replace( '[all-fields]', $data, $admin_email_body );
		return '<br>' . $info . '<br>';
	}

	/**
	 * Gets basic information from form data.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return string Basic information.
	 *
	 * @since 1.5.3
	 */
	public function get_basic_info( $form_data ) {
		$info = '';

		if ( !empty( $form_data['first_name'] ) ) {
			$info .= "First Name : {$form_data['first_name']}<br>";
		}
		if ( !empty( $form_data['last_name'] ) ) {
			$info .= "Last Name : {$form_data['last_name']}<br>";
		}
		if ( !empty( $form_data['email'] ) ) {
			$info .= "Email : {$form_data['email']}<br><br>";
		}

		return $info;
	}

	/**
	 * Gets meta information from form data.
	 *
	 * @param array $form_data Form data.
	 *
	 * @return string Meta information.
	 *
	 * @since 1.5.3
	 */
	public function get_meta_info( $form_data ) {
		$meta_fields       = !empty( $form_data['meta_fields'] ) ? $form_data['meta_fields'] : array();
		$existing_data     = Constants::get_exsiting_fields_array();
		$associative_array = array_column( $existing_data, 'label', 'value' );
		$info              = '';

		if ( is_array( $meta_fields ) && is_array( $associative_array ) ) {
			foreach ( $meta_fields as $key => $field ) {
				$label = $associative_array[ $key ] ?? str_replace( array( '_', '-' ), ' ', $key );
				$info .= "$label : {$field}<br>";
			}
		}

		return $info;
	}

	/**
	 * Gets signature information for the email.
	 *
	 * @param string $email Admin email address.
	 *
	 * @return string Signature information.
	 *
	 * @since 1.5.3
	 */
	public function get_signature_info( $email ) {
		$current_date = date( 'd M Y' );  //phpcs:ignore
		$current_time = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'h:i A' ); //phpcs:ignore
		$powered_by   = '';
		if ( apply_filters( 'mail_mint_remove_email_footer_watermark', true ) ) {
			$powered_by = __( 'Powered by : Mail Mint', 'mrm' );
		}
		$info = "<br>----<br><br>Date : $current_date <br>Time : $current_time <br>$powered_by <br>";
		return $info;
	}

	/**
	 * Gets email headers.
	 *
	 * @param string $email Admin email address.
	 *
	 * @return array Email headers.
	 *
	 * @since 1.5.3
	 */
	public function get_email_headers( $email ) {
		return array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $email,
			'Reply-To: ' . $email,
		);
	}
}
