<?php
/**
 * REST API Message Controller
 *
 * Handles requests to the messages endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\EmailModel;
use Mint\MRM\DataStores\MessageData;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Utilites\Helper\Email;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use MailMint\App\Helper;
use Mint\MRM\Internal\Parser\Parser;
use Mint\MRM\API\Actions\ComplianceAction;

/**
 * This is the main class that controls the messages feature. Its responsibilities are:
 *
 * - Create or update a message
 * - Delete single or multiple messages
 * - Retrieve single or multiple messages
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class MessageController extends AdminBaseController {


	use Singleton;

	/**
	 * API values after sanitization
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $args = array();


	/**
	 * API values after sanitization
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $meta_args = array();


	/**
	 * Send an email to contact
	 * Stores email information to database
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return bool|WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		// Get values from API.
		$params     = MrmCommon::get_api_params_values( $request );

		// Get sender id and contact id from request.
		$sender_id     = isset( $params['sender_id'] ) ? sanitize_text_field( $params['sender_id'] ) : null;
		$contact_id    = isset( $params['contact_id'] ) ? sanitize_text_field( $params['contact_id'] ) : null;
		$email_address = isset( $params['email_address'] ) ? sanitize_text_field( $params['email_address'] ) : null;
		$email_body    = isset( $params['email_body'] ) ? html_entity_decode( $params['email_body'] ) : null;
		$email_hash    = MrmCommon::get_rand_email_hash( $email_address, $contact_id );

		// Prepare email information array.
		$this->args = array(
			'email_address' => $email_address,
			'email_subject' => isset( $params['email_subject'] ) ? sanitize_text_field( $params['email_subject'] ) : null,
			'email_body'    => $email_body,
			'contact_id'	=> $contact_id,
			'email_hash'	=> $email_hash,
			'email_type'    => 'regular',
		);

		// Email address valiation.
		if ( empty( $this->args['email_address'] ) ) {
			return $this->get_error_response( __( 'Email address is mandatory', 'mrm' ), 200 );
		}

		// Email subject validation.
		if ( empty( $this->args['email_subject'] ) ) {
			return $this->get_error_response( __( 'Email subject is mandatory', 'mrm' ), 200 );
		}

		// Email body validation.
		if ( empty( $this->args['email_body'] ) ) {
			return $this->get_error_response( __( 'Email body is mandatory', 'mrm' ), 200 );
		}

		$last_email_id = CampaignModel::insert_campaign_emails( $this->args, 0, 0 );

		$last_email_builder_id = CampaignEmailBuilderModel::insert(
			array(
				'email_id'   => $last_email_id,
				'email_body' => $this->args['email_body'],
				'editor_type' => 'classic-editor'
			)
		);

		// Prepare message data.
		$message = new MessageData( $this->args );
		$this->args['email_id'] = $last_email_id;
		// Insert email and email meta inforamtion.
		$inserted_email_id = EmailModel::insert( $this->args );

		$this->meta_args = array(
			'mint_email_id' => $inserted_email_id,
			'meta_key' 		=> 'sender_id',
			'meta_value'    => $sender_id,
			'created_at'    => current_time( 'mysql' )
		);
		EmailModel::insert_broadcast_email_meta( $this->meta_args );

		// Sent email to contact
		$sent = $this->send_message( $message, $contact_id, $email_hash );
		if ( $sent ) {
			EmailModel::update( $inserted_email_id, 'status', 'sent' );
			return $this->get_success_response( __( 'Email has been sent successfully', 'mrm' ), 201 );
		}

		EmailModel::update( $inserted_email_id, 'status', 'failed' );
		return $this->get_error_response( __( 'Email not sent', 'mrm' ), 200 );
	}

	/**
	 * Send a message to contact
	 *
	 * @param mixed $message Single message object.
	 * @return bool
	 * @since 1.0.0
	 */
	public function send_message( $message, $contact_id, $email_hash ) {
		$contact = ContactModel::get( $contact_id );
		$hash    = isset( $contact['hash'] ) ? $contact['hash'] : '';

		$to      = $message->get_receiver_email();
		$subject = $message->get_email_subject();

		$sanitize_server = MrmCommon::get_sanitized_get_post();
		$sanitize_server = !empty( $sanitize_server[ 'server' ] ) ? $sanitize_server[ 'server' ] : array();
		$server          = !empty( $sanitize_server['SERVER_PROTOCOL'] ) ? $sanitize_server['SERVER_PROTOCOL'] : '';
		$protocol        = strpos( strtolower( $server ), 'https' ) === false ? 'http' : 'https';
		$domain_link     = $protocol . '://' . $sanitize_server['HTTP_HOST'];

        $get_preference_url = MrmCommon::get_default_preference_page_id_title();
		$body = $message->get_email_body();

        $preference_link = add_query_arg(
            array(
                'mrm'   => 1,
                'route' => 'mrm-preference',
                'hash'  => $hash,
            ),
            $get_preference_url
        );
        $unsubscribe_link = site_url( '?mrm=1&route=unsubscribe&hash=' . $email_hash );

        $body = str_replace( '{{preference_link}}', $preference_link, $body );
        $body = str_replace( '{{unsubscribe_link}}', $unsubscribe_link, $body );
        $body = Email::get_mail_template( $body, $domain_link, $hash );
		$open_tracking_mode = ComplianceAction::get_open_tracking_mode();
		if ( 'no' !== $open_tracking_mode ) {
			$body = Email::inject_tracking_image_on_email_body( $email_hash, $body, $open_tracking_mode );
		}
		$body = Helper::modify_email_for_rtl( $body );

		$email_settings = get_option( '_mrm_email_settings', Email::default_email_settings() );
		$header_data    = array(
			'reply_name'  => ! empty( $email_settings['reply_name'] ) ? $email_settings['reply_name'] : '',
			'reply_email' => ! empty( $email_settings['reply_email'] ) ? $email_settings['reply_email'] : '',
			'from_email'  => ! empty( $email_settings['from_email'] ) ? $email_settings['from_email'] : '',
			'from_name'   => ! empty( $email_settings['from_name'] ) ? $email_settings['from_name'] : '',
		);

		$headers = Email::get_mail_header( $header_data, $unsubscribe_link );
		return MM()->mailer->send( $to, $subject, $body, $headers );
	}

	/**
	 * Send double optin email
	 *
	 * @param mixed $contact_id Contact Id to get contact information.
	 *
	 * @return array|bool|\WP_Error
	 * @since 1.0.0
	 */
	public function send_double_opt_in( $contact_id ) {
		$contact = ContactModel::get( $contact_id );

		// Contact status check and validation.
		$status = isset( $contact['status'] ) ? $contact['status'] : '';
		if ( 'subscribed' === $status ) {
			return $this->get_error_response( __( 'Contact Already Subscribed', 'mrm' ), 400 );
		}

		if ( 'unsubscribed' === $status ) {
			return $this->get_error_response( __( 'Unsubscribed contacts will not receive the double optin email.', 'mrm' ), 400 );
		}

        $get_preference_url = MrmCommon::get_default_preference_page_id_title();

		// Get double opt-in settings.
		$default  = MrmCommon::double_optin_default_configuration();
		$settings = get_option( '_mrm_optin_settings', $default );
		$editor   = isset( $settings['editor_type'] ) ? $settings['editor_type'] : 'classic-editor';
		$enable   = isset( $settings['enable'] ) ? $settings['enable'] : '';
		if ( ! $enable ) {
			return false;
		}
		if ( $enable ) {
			$to   = isset( $contact['email'] ) ? $contact['email'] : '';
			$hash = isset( $contact['hash'] ) ? $contact['hash'] : '';

			$subscribe_url = site_url( '?mrm=1&route=confirmation&hash=' . $hash );
			$preference_link = add_query_arg(
                array(
                    'mrm'   => 1,
                    'route' => 'mrm-preference',
                    'hash'  => $hash,
                ),
                $get_preference_url
            );
			$unsubscribe_link = site_url( '?mrm=1&route=unsubscribe&hash=' . $hash );

			// Prepare email subject.
			$site_title    = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$subject       = isset( $settings['email_subject'] ) ? $settings['email_subject'] : '';
			$subject       = str_replace( '{{site_title}}', $site_title, $subject );

			$preview = isset( $settings['preview_text'] ) ? $settings['preview_text'] : '';
			$preview = str_replace( '{{site_title}}', $site_title, $preview );

			// Prepare email body.
			$email_body = isset( $settings['email_body'] ) ? $settings['email_body'] : '';
			if ('plain-text-editor' === $editor) {
				$email_body = nl2br(html_entity_decode($email_body));
			}
			$email_body = str_replace( '{{subscribe_link}}', $subscribe_url, $email_body );
			$email_body = str_replace( '{{link.subscribe}}', $subscribe_url, $email_body );

			$subscribe_text     = Helper::get_pipe_text( 'link.subscribe_html', $email_body, $subscribe_url );
			$subscribe_url_html = '<a href ="' . $subscribe_url . '">' . $subscribe_text . '</a>';

			$email_body = Helper::replace_pipe_data( 'link.subscribe_html', $email_body, $subscribe_url_html );
			$email_body = str_replace( '{{link.subscribe_html|' . $subscribe_text . '}}', $subscribe_url_html, $email_body );

			$email_body = str_replace( '#subscribe_link#', $subscribe_url, $email_body );
			$email_body = str_replace( '{{site_title}}', $site_title, $email_body );
            $email_body = str_replace( '{{preference_link}}', $preference_link, $email_body );
            $email_body = str_replace( '{{unsubscribe_link}}', $unsubscribe_link, $email_body );


			$sanitize_server = MrmCommon::get_sanitized_get_post();
			$sanitize_server = !empty( $sanitize_server[ 'server' ] ) ? $sanitize_server[ 'server' ] : array();
			$server          = !empty( $sanitize_server['SERVER_PROTOCOL'] ) ? $sanitize_server['SERVER_PROTOCOL'] : '';
			$protocol        = strpos( strtolower( $server ), 'https' ) === false ? 'http' : 'https';
			$domain_link     = !empty( $sanitize_server['HTTP_HOST'] ) ? $protocol . '://' . $sanitize_server['HTTP_HOST'] : '';

			if( 'classic-editor'  === $editor ){
				$body = Email::get_mail_template( $email_body, $domain_link, $hash, $preview );
				$body = preg_replace_callback(
					'/<img\s[^>]*?src\s*=\s*([\'"])(.*?)\1[^>]*?>/i',
					function ($matches) {
						// $matches[2] is the value of the src attribute
						if (strpos($matches[2], '../') === 0) {
							return str_replace('../', site_url().'/', $matches[0]);
						} else {
							return $matches[0];
						}
					},
					$body
				);
			} else {
				$body = Email::inject_preview_text_on_email_body( $preview, $email_body );
				$body = str_replace( '</html>', CampaignEmailBuilderModel::get_email_footer_watermark() . '</html>', $body );
			}

			if (isset($contact['meta_fields']) && is_array($contact['meta_fields'])) {
				$contact = array_merge($contact, $contact['meta_fields']);
				unset($contact['meta_fields']);
			}

			$body = Parser::parse($body, $contact);
			$body = Helper::modify_email_for_rtl( $body );

			$email_settings = get_option( '_mrm_email_settings', Email::default_email_settings() );
			$header_data    = array(
				'reply_name'  => ! empty( $email_settings['reply_name'] ) ? $email_settings['reply_name'] : '',
				'reply_email' => ! empty( $email_settings['reply_email'] ) ? $email_settings['reply_email'] : '',
				'from_email'  => ! empty( $email_settings['from_email'] ) ? $email_settings['from_email'] : '',
				'from_name'   => ! empty( $email_settings['from_name'] ) ? $email_settings['from_name'] : '',
			);

			$headers   = Email::get_mail_header( $header_data, $unsubscribe_link );
			$headers[] = 'X-PreHeader: ' . $preview;

			return MM()->mailer->send( $to, $subject, $body, $headers );
		}
	}


	/**
	 * Prepare total email delivery and percentage reports for campaign emails
	 * 
	 * @param mixed $email_id Email ID
	 * @param mixed $total_recipients Total number of recipients
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_delivered_reports( $email_id, $total_recipients ) {
		$total_delivered = EmailModel::count_delivered_status( $email_id, 'sent' );
		$divide_by       = 0 === (int) $total_recipients ? 1 : $total_recipients;

		$delivered_percentage = number_format( (float) ( $total_delivered / $divide_by) * 100, 2, '.', '' );
		return array(
			'total_delivered'      => $total_delivered,
			'delivered_percentage' => $delivered_percentage
		);
	}


	/**
	 * Prepare total email open and percentage reports for campaign emails
	 * 
	 * @param mixed $email_id Email ID
	 * @param mixed $total_bounced Total number of bounced emails
	 * @param mixed $total_delivered Total number of delivered emails
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_open_rate_reports( $email_id, $total_bounced, $total_delivered ) {
		// If no emails have been delivered yet, skip calculation entirely.
		if ( (int) $total_delivered === 0 ) {
			return array(
				'total_opened'    => 0,
				'open_percentage' => number_format( 0, 2, '.', '' ),
			);
		}
		$total_opened = EmailModel::count_email_open( $email_id );

		// Prefer delivered count; fallback to recipients if not yet updated
		$divide_by = (int) $total_delivered;

		// Prevent division by zero
		if ( $divide_by <= 0 ) {
			$divide_by = 1;
		}

		// Calculate percentage
		$open_percentage = ( $total_opened / $divide_by ) * 100;

		// Cap at 100% to avoid over-reporting
        $open_percentage = min( $open_percentage, 100.00 );

		// Always format to two decimal places
		$open_percentage = number_format( $open_percentage, 2, '.', '' );

		return array(
			'total_opened'    => (int) $total_opened,
			'open_percentage' => $open_percentage,
		);
	}


	/**
	 * Prepare unsubscribe reports for campaign emails
	 * 
	 * @param mixed $email_id Email ID
	 * @param mixed $total_bounced Total number of bounced emails
	 * @param mixed $total_delivered Total number of delivered emails
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_unsubscribe_reports( $email_id, $total_bounced, $total_delivered ) {
		// If no emails have been delivered yet, skip calculation entirely.
		if ( (int) $total_delivered === 0 ) {
			return array(
				'total_unsubscribe'      => 0,
				'unsubscribe_percentage' => number_format( 0, 2, '.', '' ),
			);
		}

		$total_unsubscribe = EmailModel::count_unsubscribe( $email_id );
		$divide_by         = (int) $total_delivered;

		// Prevent division by zero
		if ( $divide_by <= 0 ) {
			$divide_by = 1;
		}
		
		// Calculate percentage
		$unsubscribe_percentage = ( $total_unsubscribe / $divide_by ) * 100;

		// Cap percentage to 100% max to handle edge cases
		$unsubscribe_percentage = min( $unsubscribe_percentage, 100.00 );

		return array(
			'total_unsubscribe'      => $total_unsubscribe,
			'unsubscribe_percentage' => number_format( $unsubscribe_percentage, 2, '.', '' ),
		);
	}


	/**
	 * Prepare last day reports for a specific email.
	 *
	 * Retrieves data for the last day, including the number of email opens,
	 * email clicks, and unsubscribes per hour. Calculates maximum values and step size
	 * for chart visualization.
	 *
	 * @param int $email_id The ID of the email for which to retrieve last day reports.
	 * @param int $total_delivered The total number of delivered emails for the specified email.
	 * @return array An associative array containing data for last day reports:
	 * 
	 * @since 1.0.0
	 * @since 1.9.0 Prepare unsubscribe data for the last 24 hours.
	 */
	public static function prepare_last_day_reports( $email_id, $total_delivered ) {
		// If no emails have been delivered, return zero data to avoid confusion.
		if ( (int) $total_delivered === 0 ) {
			return array(
				'open' 	=> array(
					'labels' => array( '12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM' ),
					'values' => array_fill( 0, 24, 0 ),
				),
				'click'	=> array(
					'labels' => array( '12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM' ),
					'values' => array_fill( 0, 24, 0 ),
				),
				'unsubscribe' => array(
					'labels' => array( '12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM', '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM', '8 PM', '9 PM', '10 PM', '11 PM' ),
					'values' => array_fill( 0, 24, 0 ),
				),
				'max'       => 0,
				'step_size' => 0,
			);
		}
		$open_labels        = array();
		$open_values        = array();
		$click_labels       = array();
		$click_values       = array();
		$unsubscribe_labels = array();
		$unsubscribe_values = array();

		// Prepare last day email open data.
		$opened = self::prepare_last_day_email_open( $email_id );
		if ( ! empty( $opened ) ) {
			$open_labels = array_keys( $opened );
			$open_values = array_values( $opened );
		}
		
		// Prepare last day email click data.
		$clicked = self::prepare_last_day_email_click( $email_id );
		if ( ! empty( $clicked ) ) {
			$click_labels = array_keys( $clicked );
			$click_values = array_values( $clicked );
		}

		// Prepare last day unsubscribe data.
		$unsubscribe = self::prepare_last_day_unsubscribe( $email_id );
		if ( ! empty( $unsubscribe ) ) {
			$unsubscribe_labels = array_keys( $unsubscribe );
			$unsubscribe_values = array_values( $unsubscribe );
		}

		// Calculate maximum value.
		$opened_max      = ! empty( $opened ) ? max( $opened ) : 0;
		$clicked_max     = ! empty( $clicked ) ? max( $clicked ) : 0;
		$unsubscribe_max = ! empty( $unsubscribe ) ? max( $unsubscribe ) : 0;

		$max = max( array( $opened_max, $clicked_max, $unsubscribe_max ) );

		return array(
			'open' 	=> array(
				'labels' => $open_labels,
				'values' => $open_values,
			),
			'click'	=> array(
				'labels' => $click_labels,
				'values' => $click_values,
			),
			'unsubscribe' => array(
				'labels' => $unsubscribe_labels,
				'values' => $unsubscribe_values,
			),
			'max'       => (int)$max,
			'step_size' => ceil( (int)$max / 10 ),
		);
	}

	/**
	 * Prepare last twnety four hours email open rate
	 * 
	 * @param mixed $email_id Email ID
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_last_day_email_open( $email_id ) {
		$hours      = MrmCommon::get_last_day_hours();
		$open_array = EmailModel::count_per_hour_total_email_open( $email_id );
		return array_reverse( array_merge( $hours, $open_array ) );
	}


	/**
	 * Prepare last twnety four hours email click rate
	 * 
	 * @param mixed $email_id Email ID
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_last_day_email_click( $email_id ) {
		$hours       = MrmCommon::get_last_day_hours();
		$click_array = EmailModel::count_per_hour_total_link_click( $email_id );
		return array_reverse( array_merge( $hours, $click_array ) );
	}

	/**
	 * Prepare last day unsubscribe reports for a specific email.
	 *
	 * Retrieves the total number of unsubscribes per hour for the last day.
	 *
	 * @param int $email_id The ID of the email for which to retrieve unsubscribe reports.
	 * @return array An array containing hourly unsubscribe counts for the last day.
	 *               The array is formatted as ['hour' => 'count'].
	 * @since 1.9.0
	 */
	public static function prepare_last_day_unsubscribe( $email_id ) {
		$hours       = MrmCommon::get_last_day_hours();
		$unsubscribe = EmailModel::count_per_hour_total_unsubscribe( $email_id );
		return array_reverse( array_merge( $hours, $unsubscribe ) );
	}

	/**
	 * Prepare "engagement since send" reports for a campaign email.
	 *
	 * Builds three ranges measured from the moment the email was sent:
	 *  - 24h / 48h: cumulative opens, clicks and unsubscribes bucketed by hour.
	 *  - overall:   opens, clicks and unsubscribes per day since send (not cumulative).
	 *
	 * @param int $email_id        Mint email step id.
	 * @param int $total_delivered Total delivered emails for this step.
	 * @return array Map of range key => series payload consumed by the engagement chart.
	 * @since 1.23.4
	 */
	public static function prepare_engagement_since_send( $email_id, $total_delivered ) {
		// Nothing delivered yet, or no send time recorded — return zeroed ranges so the UI can show its empty state.
		$send_time = (int) $total_delivered > 0 ? EmailModel::get_campaign_email_send_time( $email_id ) : null;
		if ( empty( $send_time ) ) {
			return array(
				'24h'     => self::build_hourly_engagement_range( 24, array(), array(), array(), array( 0, 3, 6, 9, 12, 15, 18, 21, 24 ) ),
				'48h'     => self::build_hourly_engagement_range( 48, array(), array(), array(), array( 0, 6, 12, 18, 24, 36, 48 ) ),
				'overall' => self::build_daily_engagement_range( null, array(), array(), array() ),
			);
		}

		// Hourly buckets across the full 48h window; the 24h range reuses the first 24 hours.
		$open_hourly        = EmailModel::count_engagement_by_hour_offset( $email_id, 'is_open', $send_time, 48 );
		$click_hourly       = EmailModel::count_engagement_by_hour_offset( $email_id, 'is_click', $send_time, 48 );
		$unsubscribe_hourly = EmailModel::count_engagement_by_hour_offset( $email_id, 'is_unsubscribe', $send_time, 48 );

		// Per-day buckets since send.
		$open_daily        = EmailModel::count_engagement_by_day_offset( $email_id, 'is_open', $send_time );
		$click_daily       = EmailModel::count_engagement_by_day_offset( $email_id, 'is_click', $send_time );
		$unsubscribe_daily = EmailModel::count_engagement_by_day_offset( $email_id, 'is_unsubscribe', $send_time );

		return array(
			'24h'     => self::build_hourly_engagement_range( 24, $open_hourly, $click_hourly, $unsubscribe_hourly, array( 0, 3, 6, 9, 12, 15, 18, 21, 24 ) ),
			'48h'     => self::build_hourly_engagement_range( 48, $open_hourly, $click_hourly, $unsubscribe_hourly, array( 0, 6, 12, 18, 24, 36, 48 ) ),
			'overall' => self::build_daily_engagement_range( $send_time, $open_daily, $click_daily, $unsubscribe_daily ),
		);
	}

	/**
	 * Build a per-hour engagement range from per-hour buckets.
	 *
	 * Each point reflects the events that occurred within that hour (not a running total).
	 *
	 * @param int   $hours        Number of hours to render (inclusive of hour 0 = send).
	 * @param array $open         Map of hour offset => open count.
	 * @param array $click        Map of hour offset => click count.
	 * @param array $unsubscribe  Map of hour offset => unsubscribe count.
	 * @param array $tick_hours   Hour offsets that should be labelled on the X axis.
	 * @return array Series payload (labels, ticks, open, click, unsubscribe, max, step_size, has_data).
	 * @since 1.23.4
	 */
	private static function build_hourly_engagement_range( $hours, $open, $click, $unsubscribe, $tick_hours ) {
		$labels       = array();
		$ticks        = array();
		$open_series  = array();
		$click_series = array();
		$unsub_series = array();
		$max          = 0;

		for ( $hour = 0; $hour <= $hours; $hour++ ) {
			$label    = 0 === $hour ? __( 'Sent', 'mrm' ) : $hour . 'h';
			$labels[] = $label;
			if ( in_array( $hour, $tick_hours, true ) ) {
				$ticks[] = $label;
			}
			$open_value     = isset( $open[ $hour ] ) ? (int) $open[ $hour ] : 0;
			$click_value    = isset( $click[ $hour ] ) ? (int) $click[ $hour ] : 0;
			$unsub_value    = isset( $unsubscribe[ $hour ] ) ? (int) $unsubscribe[ $hour ] : 0;
			$open_series[]  = $open_value;
			$click_series[] = $click_value;
			$unsub_series[] = $unsub_value;
			$max            = max( $max, $open_value, $click_value, $unsub_value );
		}

		return array(
			'labels'      => $labels,
			'ticks'       => $ticks,
			'open'        => $open_series,
			'click'       => $click_series,
			'unsubscribe' => $unsub_series,
			'max'         => (int) $max,
			'step_size'   => $max > 0 ? (int) ceil( $max / 5 ) : 0,
			'has_data'    => $max > 0,
		);
	}

	/**
	 * Build a per-calendar-day engagement range from the send date until today.
	 *
	 * Points are calendar dates (not rolling 24-hour windows from send), matching the
	 * DATEDIFF buckets returned by EmailModel::count_engagement_by_day_offset. Today
	 * gets its own point as soon as the date rolls over, even if fewer than 24 hours
	 * have elapsed since send.
	 *
	 * @param string|null $send_time   MySQL datetime of the send moment, or null when nothing was sent.
	 * @param array       $open        Map of day offset => open count.
	 * @param array       $click       Map of day offset => click count.
	 * @param array       $unsubscribe Map of day offset => unsubscribe count.
	 * @return array Series payload (labels, ticks, open, click, unsubscribe, max, step_size, has_data).
	 * @since 1.23.4
	 */
	private static function build_daily_engagement_range( $send_time, $open, $click, $unsubscribe ) {
		// Without a send time there is a single zeroed point so the empty state can still render an axis.
		if ( empty( $send_time ) ) {
			return array(
				'labels'      => array( __( 'Sent', 'mrm' ) ),
				'ticks'       => array(),
				'open'        => array( 0 ),
				'click'       => array( 0 ),
				'unsubscribe' => array( 0 ),
				'max'         => 0,
				'step_size'   => 0,
				'has_data'    => false,
			);
		}

		// Anchor to calendar dates (midnight) so offsets match the DATEDIFF buckets and avoid DST drift.
		$send_day  = strtotime( gmdate( 'Y-m-d', strtotime( $send_time ) ) );
		$today     = strtotime( gmdate( 'Y-m-d', strtotime( current_time( 'mysql' ) ) ) );
		$days      = (int) round( ( $today - $send_day ) / DAY_IN_SECONDS );
		$days      = max( 0, min( $days, 365 ) );

		$labels       = array();
		$open_series  = array();
		$click_series = array();
		$unsub_series = array();
		$max          = 0;

		for ( $day = 0; $day <= $days; $day++ ) {
			$labels[]      = date_i18n( 'M j', strtotime( "+{$day} day", $send_day ) );
			$open_value    = isset( $open[ $day ] ) ? (int) $open[ $day ] : 0;
			$click_value   = isset( $click[ $day ] ) ? (int) $click[ $day ] : 0;
			$unsub_value   = isset( $unsubscribe[ $day ] ) ? (int) $unsubscribe[ $day ] : 0;
			$open_series[]  = $open_value;
			$click_series[] = $click_value;
			$unsub_series[] = $unsub_value;
			$max            = max( $max, $open_value, $click_value, $unsub_value );
		}

		// Show every label for short spans; let the chart auto-skip (empty ticks) for longer spans.
		$ticks = count( $labels ) <= 8 ? $labels : array();

		return array(
			'labels'      => $labels,
			'ticks'       => $ticks,
			'open'        => $open_series,
			'click'       => $click_series,
			'unsubscribe' => $unsub_series,
			'max'         => (int) $max,
			'step_size'   => $max > 0 ? (int) ceil( $max / 5 ) : 0,
			'has_data'    => $max > 0,
		);
	}

	/**
	 * Prepare total number of open and click based on user agent or devices
	 * 
	 * @param mixed $email_id Email ID
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_device_reports( $email_id ) {
		$total_open  = EmailModel::count_email_open( $email_id );
		$total_click = EmailModel::count_email_click( $email_id );

		$open_devices  = EmailModel::count_total_email_open_on_device( $email_id, $total_open );
		$click_devices = EmailModel::count_total_email_click_on_device( $email_id, $total_click );
		return array(
			'devices' => array(
				'open' 	=> $open_devices,
				'click'	=> $click_devices
			)
		);
	}


	/**
	 * Prepare total email click and percentage reports for campaign emails
	 * 
	 * @param mixed $email_id Email ID
	 * @param mixed $total_recipients Total number of bounded emails
	 * @param mixed $total_delivered Total number of delivered emails
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_click_rate_reports( $email_id, $total_bounced, $total_delivered ) {
		// If no emails have been delivered yet, skip calculation entirely.
		if ( (int) $total_delivered === 0 ) {
			return array(
				'total_click'      => 0,
				'click_percentage' => number_format( 0, 2, '.', '' ),
			);
		}
		$total_click = EmailModel::count_email_click( $email_id );

		// Divide by total delivered (not minus bounced)
		$divide_by = (int) $total_delivered;
		if ( $divide_by <= 0 ) {
			$divide_by = 1;
		}
		
		$click_percentage = ( $total_click / $divide_by ) * 100;

		// Clamp value between 0 and 100, and format
		$click_percentage = min( max( $click_percentage, 0 ), 100.00 );

		return array(
			'total_click'      => (int) $total_click,
			'click_percentage' => number_format( $click_percentage, 2, '.', '' ),
		);
	}

	/**
	 * Prepares Click-to-Open Rate (CTOR) reports based on the total number of clicks and opens.
	 *
	 * Calculates the Click-to-Open Rate (CTOR) by dividing the total number of clicks by the total number of opens,
	 * and then multiplying the result by 100 to get a percentage.
	 *
	 * @param array $clicked An associative array containing click-related data, with 'total_click' as the key.
	 * @param array $opened  An associative array containing open-related data, with 'total_opened' as the key.
	 *
	 * @return array An associative array containing the Click-to-Open Rate (CTOR).
	 *               - 'ctor' (float): The calculated Click-to-Open Rate as a percentage.
	 *
	 * @since 1.9.0
	 */
	public static function prepare_click_to_open_rate_reports( $clicked, $opened ) {
		$total_click = isset( $clicked['total_click'] ) ? $clicked['total_click'] : '';
		$total_open  = isset( $opened['total_opened'] ) ? $opened['total_opened'] : '';

		$divide_by = 0 === (int) $total_open ? 1 : $total_open;
		$ctor      = number_format( (float)( $total_click / $divide_by ) * 100, 2, '.', '' );

		return array(
			'ctor' => $ctor,
		);
	}

	/**
	 * Prepare total email bounced and percentage reports for campaign emails
	 * 
	 * @param mixed $email_id Email ID
	 * @param mixed $total_recipients Total number of recipients
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_bounced_reports( $email_id, $total_recipients ) {
		$total_bounced = EmailModel::count_delivered_status( $email_id, 'failed' );
		$divide_by     = 0 === (int) $total_recipients ? 1 : $total_recipients;

		$bounced_percentage = number_format( (float)( $total_bounced / $divide_by ) * 100, 2, '.', '' );
		return array(
			'total_bounced' => $total_bounced,
			'bounced_percentage' => $bounced_percentage
		);
	}


	/**
	 * Prepare total orders and revenue for a campaign email
	 * 
	 * @param mixed $email_id Campaign email id.
	 * 
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_order_reports( $email_id, $type ) {
		$total_orders  = EmailModel::count_total_orders_to_campaign_email( $email_id, $type );
		$total_revenue = EmailModel::count_total_revenue_to_campaign_email( $email_id, $type );
		$total_revenue = MrmCommon::price_format_with_wc_currency( $total_revenue );

		return array(
			'total_orders'  => $total_orders,
			'total_revenue'	=> $total_revenue
		);
	}
}