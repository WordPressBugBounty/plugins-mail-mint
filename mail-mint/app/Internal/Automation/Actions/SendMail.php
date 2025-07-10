<?php
/**
 * Automation action class for MRM Autoamtion
 *
 * Class SendMail
 *
 * @package MintMail\App\Internal\Automation\Action
 */

namespace MintMail\App\Internal\Automation\Action;

use MailMint\App\Helper;
use MailMintPro\Internal\EmailCustomization\Render\EmailRender;
use Mint\MRM\Internal\Parser\Parser;
use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\EmailModel;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Utilites\Helper\Email;
use MintMail\App\Internal\Automation\Action\AbstractAutomationAction;
use MintMail\App\Internal\Automation\HelperFunctions;
use MintMail\App\Internal\Automation\ActionScheduler;
use MintMailPro\Mint_Pro_Helper;
use MRM\Common\MrmCommon;

/**
 * SendMail action
 *
 * Class SendMail
 *
 * @package MintMail\App\Internal\Automation\Action
 */
class SendMail extends AbstractAutomationAction {

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
	 * Get connector name
	 *
	 * @return String
	 * @since  1.0.0
	 */
	public function get_name() {
		return __( 'SendMail', 'mrm' );
	}

	/**
	 * Process.
	 *
	 * @param array $data Get All data.
	 */
	public function process( $data ) {
		if ( $data ) {
			// Get email settings from the options table.
			$global_email_settings = get_option( '_mrm_email_settings', Email::default_email_settings() );

			// Get business settings default configuration.
			$default_business_settings = MrmCommon::business_settings_default_configuration();
			$business_settings         = get_option( '_mrm_business_basic_info_setting', $default_business_settings );
			$business_settings         = is_array( $business_settings ) && ! empty( $business_settings ) ? $business_settings : $default_business_settings;
			// Prepare replay to information.
			$reply_name      = isset( $global_email_settings['reply_name'] ) ? $global_email_settings['reply_name'] : '';
			$reply_email     = isset( $global_email_settings['reply_email'] ) ? $global_email_settings['reply_email'] : '';
			$user_email      = !empty( $data['data']['user_email'] ) ? $data['data']['user_email'] : '';
			$contact_id      = HelperFunctions::get_contact_id_by_broadcast_table( $user_email );
			$wp_user_id      = isset( $data['data']['user_id'] ) ? $data['data']['user_id'] : '';
			$post_id         = isset( $data['data']['post_id'] ) ? $data['data']['post_id'] : '';
			$order_id	     = isset( $data['data']['order_id'] ) ? $data['data']['order_id'] : '';
			$abandoned_id    = isset( $data['data']['abandoned_id'] ) ? $data['data']['abandoned_id'] : '';
			$payment_id      = isset( $data['data']['payment_id'] ) ? $data['data']['payment_id'] : '';
			$subscription_id = isset( $data['data']['subscription_id'] ) ? $data['data']['subscription_id'] : '';
			$product_id	  	 = isset( $data['data']['product_id'] ) ? $data['data']['product_id'] : '';
			$user_membership_id = isset($data['data']['user_membership_id']) ? $data['data']['user_membership_id'] : '';
			$wishlist_id 	 = isset($data['data']['wishlist_id']) ? $data['data']['wishlist_id'] : '';
			$ld_course_id	 = isset( $data['data']['ld_course_id'] ) ? $data['data']['ld_course_id'] : '';
			$ld_quiz_id		 = isset( $data['data']['ld_quiz_id'] ) ? $data['data']['ld_quiz_id'] : '';
			$ld_lesson_id	 = isset( $data['data']['ld_lesson_id'] ) ? $data['data']['ld_lesson_id'] : '';
			$ld_group_id	 = isset( $data['data']['ld_group_id'] ) ? $data['data']['ld_group_id'] : '';
			$ld_topic_id	 = isset( $data['data']['ld_topic_id'] ) ? $data['data']['ld_topic_id'] : '';
			$booking_id 	 = isset($data['data']['booking_id']) ? $data['data']['booking_id'] : '';

			$step_data = HelperFunctions::get_step_data(  $data['automation_id'], $data['step_id'] );

			$log_payload = array(
				'automation_id' => $data['automation_id'],
				'step_id'       => $data['step_id'],
				'status'        => 'hold',
				'identifier'    => $data['identifier'],
				'email'         => $user_email,
			);
			HelperFunctions::update_log( $log_payload );
			/**
			 * Fires before sending an email as part of an automation process.
			 *
			 * @since 1.0.0
			 *
			 * @param string $automation_id The ID of the automation.
			 * @param string $data The step data.
			 */
			do_action( 'mailmint_before_automation_send_mail', $data['automation_id'], $data['data']['user_email'] );
			do_action( 'mint_before_automation_send_mail', $data['automation_id'], $data['data'] );

			$transactional_email = isset($step_data['settings']['message_data']['make_transactional']) ? $step_data['settings']['message_data']['make_transactional']: false;
			if (!empty($step_data['settings']['message_data']) &&
				( $transactional_email || HelperFunctions::maybe_user($data['data']['user_email']))) {
	
				// Check if the email body contains the product block.
				$serialized_body = maybe_serialize($step_data['settings']['message_data']['json_body']);
				if (strpos($serialized_body, 'product_block') !== false) {
					do_action('mailmint_product_block_automation_email');
				}

				$headers = array(
					'MIME-Version: 1.0',
					'Content-type: text/html;charset=UTF-8',
				);
				$rand_hash   = MrmCommon::get_rand_email_hash( $data['data']['user_email'], $data['automation_id'] );
				$sender_name = isset( $step_data['settings']['message_data']['sender_name'] ) ? $step_data['settings']['message_data']['sender_name'] : '';
				$from        = 'From: ' . $sender_name;
				$headers[]   = $from . ' <' . $step_data['settings']['message_data']['sender_email'] . '>';
				$reply_name  = isset( $step_data['settings']['message_data']['reply_name'] ) ? $step_data['settings']['message_data']['reply_name'] : '';
				$reply_email = isset( $step_data['settings']['message_data']['reply_email'] ) ? $step_data['settings']['message_data']['reply_email'] : '';
				$preview     = isset( $step_data['settings']['message_data']['email_preview_text'] ) ? $step_data['settings']['message_data']['email_preview_text'] : '';
				if ( $reply_name && $reply_email ) {
					$headers[] = 'Reply-To: ' . $reply_name . ' <' . $reply_email . '>';
				} elseif ( $reply_email ) {
					$headers[] = $reply_email;
				}

				// Get contact and merge meta fields with contact fields.
				$contact = ContactModel::get( $contact_id );
				if (isset($contact['meta_fields']) && is_array($contact['meta_fields'])) {
					$contact = array_merge($contact, $contact['meta_fields']);
					unset($contact['meta_fields']);
				}

				if( empty( $contact ) && $transactional_email ) {
					$contact = array(
						'first_name' => isset( $data['data']['first_name'] ) ? $data['data']['first_name'] : '',
						'last_name'  => isset( $data['data']['last_name'] ) ? $data['data']['last_name'] : '',
						'email'      => $user_email,
					);

				}

				$preview = Parser::parse( 
					$preview, 
					$contact, 
					$post_id, 
					$order_id, 
					array( 
						'abandoned_id'       => $abandoned_id, 
						'edd_payment_id'     => $payment_id, 
						'subscription_id'    => $subscription_id, 
						'product_id'         => $product_id, 
						'user_membership_id' => $user_membership_id, 
						'wishlist_id'        => $wishlist_id, 
						'ld_course_id'       => $ld_course_id,
						'ld_quiz_id'         => $ld_quiz_id,
						'ld_lesson_id'       => $ld_lesson_id,
						'ld_group_id'        => $ld_group_id,
						'ld_topic_id'        => $ld_topic_id,
						'booking_id' 		 => $booking_id,
						'wp_user_id'         => $wp_user_id,
					)
				);
				$preview   = Helper::replace_dynamic_coupon( $preview, $user_email );
				$headers[] = 'X-PreHeader: ' . $preview;

				$unsubscribe_url = Helper::get_unsubscribed_url( $rand_hash );

				/** This filter is documented in app/Utilities/Helper/Email.php */
				if ( apply_filters( 'mail_mint_enable_unsubscribe_header', true, $headers ) ) {
					$headers[] = 'List-Unsubscribe: <' . $unsubscribe_url . '>';
					$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
				}

				$email_data = array(
					'receiver_email' => $user_email,
					'subject'        => !empty( $step_data['settings']['message_data']['subject'] ) ? $step_data['settings']['message_data']['subject'] : 'Welcome to Mint email',
					'body'           => !empty( $step_data['settings']['message_data']['body'] ) ? $step_data['settings']['message_data']['body'] : '',
					'header'         => $headers,
					'editor_type'    => !empty( $step_data['settings']['message_data']['json_body']['editor'] ) ? $step_data['settings']['message_data']['json_body']['editor'] : 'advanced-builder',
				);

				if ('plain-text-editor' === $email_data['editor_type']) {
					$email_data['body'] = nl2br(html_entity_decode($email_data['body']));
				}

				$email_data['subject'] = Parser::parse( 
					$email_data['subject'], 
					$contact, 
					$post_id, 
					$order_id, 
					array(
						'abandoned_id'       => $abandoned_id, 
						'edd_payment_id'     => $payment_id, 
						'subscription_id'    => $subscription_id, 
						'product_id'         => $product_id, 
						'user_membership_id' => $user_membership_id, 
						'wishlist_id'        => $wishlist_id, 
						'ld_course_id'       => $ld_course_id,
						'ld_quiz_id'         => $ld_quiz_id,
						'ld_lesson_id'       => $ld_lesson_id,
						'ld_group_id'        => $ld_group_id,
						'ld_topic_id'        => $ld_topic_id,
						'booking_id'         => $booking_id,
						'wp_user_id'         => $wp_user_id,
					) 
				);
				$email_data['subject'] = Helper::replace_dynamic_coupon( $email_data['subject'], $email_data['receiver_email'] );
				$email_data['body']    = Helper::replace_url( $email_data['body'], $rand_hash );
				$email_data['body']    = Parser::parse( 
					$email_data['body'],
					$contact,
					$post_id,
					$order_id,
					array(
						'abandoned_id'       => $abandoned_id, 
						'edd_payment_id'     => $payment_id, 
						'subscription_id'    => $subscription_id, 
						'product_id'         => $product_id, 
						'user_membership_id' => $user_membership_id, 
						'wishlist_id'        => $wishlist_id, 
						'ld_course_id'       => $ld_course_id,
						'ld_quiz_id'         => $ld_quiz_id,
						'ld_lesson_id'       => $ld_lesson_id,
						'ld_group_id'        => $ld_group_id,
						'ld_topic_id'        => $ld_topic_id,
						'booking_id'         => $booking_id,
						'wp_user_id'         => $wp_user_id,
					)
				);
				$email_data['body']    = Helper::replace_dynamic_coupon( $email_data['body'], $email_data['receiver_email'] );

				/**
				 * Summary: Applies the 'mint_replace_abandoned_carts_placeholder' filter to replace abandoned carts placeholders in the email body.
				 *
				 * Description: This code section applies the 'mint_replace_abandoned_carts_placeholder' filter hook to the email body.
				 * The filter allows for customization of the replacement of abandoned carts placeholders with actual values in the email body.
				 *
				 * @param string $email_data['body'] The email body to be filtered.
				 * @param array  $data            An array containing data to be passed to the filter hook.
				 *
				 * @return string The filtered email body with replaced abandoned carts placeholders.
				 * @since 1.5.0
				 */
				$email_data['body'] = apply_filters('mint_automation_email_body', $email_data['body'], $data);
				$email_data['body'] = Email::inject_preview_text_on_email_body($preview, $email_data['body']);

				// Call EmailRender class to dynamically render the custom blocks.
				if ( $order_id && class_exists('MailMintPro\Internal\EmailCustomization\Render\EmailRender') ) {
					$order = wc_get_order($order_id);
					$email_render = new EmailRender(
						array(
							'object_type' => 'order',
							'object'      => $order,
							'render_type' => 'wc_action',
							'template'    => $email_data['body'],
						)
					);

					$email_data['body'] = $email_render->render();
				}

				// Process URL for lead-magnet tracking if MailMint Pro is active.
				if (MrmCommon::is_mailmint_pro_active()) {
					$email_data['body'] = Mint_Pro_Helper::replace_automatic_latest_content($email_data['body'], get_post_type($post_id));
					if (MrmCommon::is_mailmint_pro_version_compatible('1.15.1')) {
						$email_data['body'] = Mint_Pro_Helper::process_lead_magnet_tracking($email_data['body'], $user_email);
					}
				}

				$is_sent = $this->send_message( $email_data, $rand_hash );
				do_action('mailmint_after_automation_send_mail', $data['automation_id'], $data['data']['user_email'], $is_sent);
				$payload = array(
					'automation_id' => $data['automation_id'],
					'step_id'       => $data['step_id'],
					'email_id'      => $data['step_id'],
					'email_type'    => 'automation',
					'email_address' => $user_email,
					'contact_id'    => $contact_id,
					'email_hash'    => $rand_hash,
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				);
				if ( $is_sent ) {
					$payload['status'] = 'sent';
					$log_payload       = array(
						'automation_id' => $data['automation_id'],
						'step_id'       => $data['step_id'],
						'status'        => 'completed',
						'identifier'    => $data['identifier'],
						'email'         => $user_email,
					);
					HelperFunctions::update_log( $log_payload );
				} else {
					$payload['status'] = 'failed';
					$log_payload       = array(
						'automation_id' => $data['automation_id'],
						'step_id'       => $data['step_id'],
						'identifier'    => $data['identifier'],
						'status'        => 'fail',
						'email'         => $user_email,
					);
					HelperFunctions::update_log( $log_payload );
				}
				EmailModel::insert( $payload );
			}

			if ( !isset( $data['double_optin'] ) ) {
				$next_step = HelperFunctions::get_next_step( $data['automation_id'], $data['step_id'] );

				HelperFunctions::update_job( $data['automation_id'], isset( $next_step['step_id'] ) ? $next_step['step_id'] : null, isset( $next_step['step_id'] ) ? 'processing' : 'completed' );
				if ( $next_step ) {
					$next_step['data']       = $data['data'];
					$next_step['identifier'] = $data['identifier'];
					do_action(MINT_PROCESS_AUTOMATION, $next_step);
				}
			}
		}
	}


	/**
	 * Send a message to contact
	 *
	 * @param mixed $data Single message object.
	 * 
	 * @return bool
	 * @since 1.0.0
	 * @since 1.14.1 Add $rand_hash parameter to the function.
	 */
	public function send_message( $data, $rand_hash ) {
		$to      = isset( $data['receiver_email'] ) ? $data['receiver_email'] : '';
		$subject = isset( $data['subject'] ) ? $data['subject'] : 'Mail from Mint Email';
		$body    = isset( $data['body'] ) ? html_entity_decode( $data['body'] ) : ''; //phpcs:ignore


		$body = Email::inject_tracking_image_on_email_body($rand_hash, $body);

		if ( 'advanced-builder' === $data['editor_type'] ) {
			$body = str_replace( '</html>', CampaignEmailBuilderModel::get_email_footer_watermark() . '</html>', $body );
		} else {
			$body = $body . CampaignEmailBuilderModel::get_email_footer_watermark();
		}

		$headers = isset( $data['header'] ) ? $data['header'] : '';
		$body    = Helper::modify_email_for_rtl( $body );
		if ( $to ) {
			return MM()->mailer->send( $to, $subject, $body, $headers );
		}
		return false;
	}
}
