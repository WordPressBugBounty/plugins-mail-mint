<?php
/**
 * REST API Campaign Email Controller
 *
 * Handles requests to the campaign email endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\Internal\Admin\EmailTemplates\DefaultEmailTemplates;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Utilites\Helper\Email;
use WP_REST_Request;
use MRM\Common\MrmCommon;
use MailMint\App\Helper;
use Mint\MRM\Internal\Parser\Parser;
use WP_Query;
use Mint\MRM\DataBase\Models\ContactModel;

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/**
 * This is the main class that controls the campaign email feature. Its responsibilities are:
 *
 * - Create new campaign email
 * - Delete single or multiple campaign email
 * - Retrieve single or multiple campaign email
 * - Send test email from campaign
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class CampaignEmailController extends AdminBaseController {

	use Singleton;


	/**
	 * Campaign object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args = array();


	/**
	 * Create or update email templates for each campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		$email_id    = isset( $params['email_id'] ) ? $params['email_id'] : 0;
		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : 0;
		$email_index = isset( $params['email_index'] ) ? $params['email_index'] : 0;

		if ( empty( $email_id ) ) {
			$email = CampaignModel::get_email_by_index($campaign_id, $email_index);
			$email_id = $email->id;
		}

		$email_builder_data = CampaignEmailBuilderModel::is_new_email_template( $email_id );

		$editor_type     = isset( $params[ 'editor_type' ] ) ? $params[ 'editor_type' ] : 'advanced-builder';
		$advance_content = isset( $params[ 'email_body' ] ) ? $params[ 'email_body' ] : '';
		$classic_content = isset( $params[ 'json_data' ]['content'] ) ? $params[ 'json_data' ]['content'] : '';
		$json_data       = isset( $params[ 'json_data' ] ) ? $params[ 'json_data' ] : [];

		$update_data = array(
			'editor_type' => $editor_type,
            'email_body' => ($editor_type === 'advanced-builder') ? $advance_content : $classic_content,
            'json_data' => maybe_serialize($json_data),
		);

		if ($email_builder_data) {
            CampaignEmailBuilderModel::update($email_id, $update_data);
        } else {
            CampaignEmailBuilderModel::insert(array_merge(['email_id' => $email_id], $update_data));
        }

		return rest_ensure_response( array(
			'success' => true,
			'campaign_id' => $params[ 'campaign_id' ],
			'message' => __( 'Email builder content has been saved successfully.', 'mrm' )
		) );
	}

	/**
	 * TODO: use this function to get single email
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return void
	 */
	public function delete_single( WP_REST_Request $request ) {
		// TODO: Implement delete_single() method.
	}


	/**
	 * TODO: use this function to get single email
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return void
	 */
	public function delete_all( WP_REST_Request $request ) {
		// TODO: Implement delete_all() method.
	}


	/**
	 * Create a new email for existing campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_new_campaign_email( WP_REST_Request $request ) {
		// Receive params from POST API request and prepare email data.
		$params      = MrmCommon::get_api_params_values( $request );
		$email_data  = isset( $params['email_data'] ) ? $params['email_data'] : array();
		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : array();
		$json_data   = isset( $params['json_data'] ) ? maybe_unserialize( $params['json_data'] ) : array();
		$editor      = isset( $json_data['editor'] ) ? $json_data['editor'] : 'advanced-builder';
		$email_id    = isset( $params['email_id'] ) ? $params['email_id']: array();
		// Insert email data on campaign emails and email builder table.
		CampaignEmailBuilderModel::insert(
			array(
				'email_id'   => $email_id,
				'email_body' => $email_data,
				'json_data'   => maybe_serialize( $json_data ),
				'editor_type' => $editor
			)
		);

		$response['campaign_id'] = $campaign_id;
		return rest_ensure_response( $response );
	}


	/**
	 * Function use to get single campaign email
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function get_single( WP_REST_Request $request ) {
		$params   = MrmCommon::get_api_params_values( $request );

		if ( !empty( $params[ 'email_id' ] ) ) {
			$email_id = $params[ 'email_id' ];
		}
		else {
			$email    = CampaignModel::get_email_by_index( $params['campaign_id'], $params['email_index'] );
			$email_id = $email->id;
		}
		$response = array(
			'success' => true,
			'message' => '',
		);

		if ( ! $email_id ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => 'No email data found!',
			) );
		}
		$email_builder_data     = CampaignEmailBuilderModel::get( $email_id );
		$response['email_data'] = $email_builder_data;
		return rest_ensure_response( $response );
	}


	/**
	 * TODO: use this function to get multiple email
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return void
	 */
	public function get_all( WP_REST_Request $request ) {
		// TODO: Implement get_all() method.
	}


	/**
	 * We followed three steps to save a new email for a campaign.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_Error|\WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function create_email( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$email_index = isset( $params['email_index'] ) ? $params['email_index'] : null;
		$response    = array(
			'success' => true,
			'message' => '',
		);

		if ( is_null( $email_index ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'There is something wrong. Email index of this campaign not found. Try again.',
				)
			);
		}

		// Step #1.
		if ( isset( $params['campaign_data']['status'] ) && empty( $params['campaign_data']['status'] ) ) {
			$params['campaign_data']['status'] = 'draft';
		}

		$campaign    = CampaignModel::insert( $params['campaign_data'] );
		$campaign_id = $campaign['id'];

		// Insert campaign recipients information.
		$recipients = isset( $params['campaign_data']['recipients'] ) ? maybe_serialize( $params['campaign_data']['recipients'] ) : '';
		CampaignModel::insert_campaign_recipients( $recipients, $campaign_id );

		$params['campaign_data'][ $email_index ]['campaign_id'] = $campaign_id;
		$emails = isset( $params['campaign_data']['emails'] ) ? $params['campaign_data']['emails'] : '';
		// Step #2.
		foreach ( $emails as $index => $email ) {
			$email_id = CampaignModel::insert_campaign_emails( $email, $campaign_id, $index );
			if ( $index == $email_index ) { //phpcs:ignore
				// Step #3.
				CampaignEmailBuilderModel::insert(
					array(
						'email_id'   => $email_id,
						'email_body' => html_entity_decode( $params['email_body'] ),
						'json_data'  => maybe_serialize( $params['json_data'] ),
					)
				);
			}
		}
		$response['message']     = __( 'Data successfully saved', 'mrm' );
		$response['campaign_id'] = $campaign_id;
		return rest_ensure_response( $response );
	}

	/**
	 * Get and send response to create or update a campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 * @since 1.11.1 Add Mailer class to send email.
	 */
	public function send_test_email( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		// Check for required fields.
		$subject = ! empty( $params[ 'json_data' ][ 'subject' ] ) ? $params[ 'json_data' ][ 'subject' ] : '';
		if ( empty( $subject ) ){
			return array(
				'status'  => 'error',
				'message' => __( 'Please enter a subject for the test email.', 'mrm' )
			);
		}

		$receivers = ! empty( $params[ 'json_data' ][ 'to' ] ) ? $params[ 'json_data' ][ 'to' ] : '';
		if ( empty( $receivers ) ){
			return array(
				'status'  => 'error',
				'message' => __('At least one email address is required.', 'mrm' )
			);
		}
		$receivers  = explode( ',', $receivers );

		$header_data = array(
			'reply_name'  => ! empty( $params[ 'json_data' ][ 'reply_name' ] ) ? $params[ 'json_data' ][ 'reply_name' ] : '',
			'reply_email' => ! empty( $params[ 'json_data' ][ 'reply' ] ) ? $params[ 'json_data' ][ 'reply' ] : '',
			'from_email'  => ! empty( $params[ 'json_data' ][ 'from' ] ) ? $params[ 'json_data' ][ 'from' ] : '',
			'from_name'   => ! empty( $params[ 'json_data' ][ 'sender_name' ] ) ? $params[ 'json_data' ][ 'sender_name' ] : '',
		);

		$content    = ! empty( $params[ 'json_data' ][ 'content' ] ) ? html_entity_decode( $params[ 'json_data' ][ 'content' ] ) : '';
		$headers    = Email::get_mail_header( $header_data, '' );
		$preview    = ! empty( $params[ 'json_data' ][ 'email_preview_text' ] ) ? $params[ 'json_data' ][ 'email_preview_text' ] : '';

		$editor_type = !empty( $params[ 'json_data' ][ 'editor_type' ] ) ? $params[ 'json_data' ][ 'editor_type' ] : 'advanced-builder';
		
		$post_id     = isset($params['data']['post_id']) ? $params['data']['post_id'] : '';
		$order_id	 = isset($params['data']['order_id']) ? $params['data']['order_id'] : '';
		$abandoned_id = isset($params['data']['abandoned_id']) ? $params['data']['abandoned_id'] : '';
		$payment_id   = isset($params['data']['payment_id']) ? $params['data']['payment_id'] : '';

		// Check for '{{post.' in the content and replace merge tags.
		if ( false !== strpos( $content, '{{post.' ) ) {
			$latest_post = $this->get_latest_post();
			if ( isset( $latest_post->ID ) ) {
				$content = Helper::replace_email_body_post_merge_tags( $content, $latest_post->ID );
			}
		}

		// Add preheader to headers.
		$headers[] = 'X-PreHeader: '. $preview;

		if ( 'advanced-builder' === $editor_type ) {
			$content = str_replace('</html>', CampaignEmailBuilderModel::get_email_footer_watermark() . '</html>', $content);
		} else {
			$content = $content . CampaignEmailBuilderModel::get_email_footer_watermark();
		}

		$content = Helper::modify_email_for_rtl( $content );

		$response = array(
			'status'  => 'success',
			'message' => __('A test email has been sent successfully.', 'mrm'),
		);

		$false_emails = array();
		foreach( $receivers as $receiver ) {
			$receiver = trim( $receiver );
			if ( ! is_email( $receiver ) ) {
				$false_emails[] = $receiver;
			}

			$contact = ContactModel::get_contact_by_email($receiver);
			$contact = ContactModel::get($contact['id']);

			if (isset($contact['meta_fields']) && is_array($contact['meta_fields'])) {
				$contact = array_merge($contact, $contact['meta_fields']);
				unset($contact['meta_fields']);
			}

			// Reset subject, content, and preview for each receiver
			$parsed_subject = Parser::parse($subject, $contact, $post_id, $order_id, array('abandoned_id' => $abandoned_id, 'edd_payment_id' => $payment_id));
			$parsed_content = Parser::parse($content, $contact, $post_id, $order_id, array('abandoned_id' => $abandoned_id, 'edd_payment_id' => $payment_id));
			$parsed_preview = Parser::parse($preview, $contact, $post_id, $order_id, array('abandoned_id' => $abandoned_id, 'edd_payment_id' => $payment_id));
			$final_content = Email::inject_preview_text_on_email_body($parsed_preview, $parsed_content);

			MM()->mailer->send($receiver, $parsed_subject, $final_content, $headers);
		}

		if ( count( $false_emails ) ){
			$response = array(
				'status'  => 'error',
				'message' => __( 'A test email has been sent successfully but some emails are wrong - ', 'mrm' ) . implode( ", ", $false_emails )
			);
		}

		return $response;
	}

	/**
	 * Upload Media
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function upload_media( WP_REST_Request $request ) {
		$params   = $request->get_file_params();
		$movefile = wp_handle_upload( $params['image'], array( 'test_form' => false ) );
		return $movefile;
	}


	/**
	 * Get email template data from email builder
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_email_builder_data( WP_REST_Request $request ) {
		// Receive params from POST API request and prepare email data.
		$params      = MrmCommon::get_api_params_values( $request );
		$email_id    = isset( $params['email_id'] ) ? $params['email_id'] : array();
		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : array();

		$email    = CampaignModel::get_campaign_email_to_builder( $campaign_id, $email_id );
		$response = array(
			'success' => true,
			'message' => '',
		);
		if ( ! $email ) {
			$response = array(
				'success' => false,
				'message' => 'No email data found!',
			);
			return rest_ensure_response( $response );
		}
		$email_builder_data     = CampaignEmailBuilderModel::get( $email->id );
		$response['email_data'] = $email_builder_data;
		return rest_ensure_response( $response );
	}

	/**
	 * Save email as template
	 *
	 * @param WP_REST_Request $request WP_REST_Request
	 *
	 * @return array|\WP_Error
	 * @since 1.0.0
	 */
	public function save_campaign_email_template( WP_REST_Request $request ) {
		// Receive params from POST API request and prepare email data.
		$params = MrmCommon::get_api_params_values( $request );

		if ( empty( $params[ 'title' ] ) ) {
			return $this->get_error_response( __('Please set a template title.', 'mrm'), 201 );
		}
		if ( empty( $params[ 'json_content' ] ) ) {
			return $this->get_error_response( __('Please set content to set as template.', 'mrm'), 201 );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'mint_email_template',
				'post_title'   => sanitize_text_field( $params[ 'title' ] ),
				'post_status'  => 'draft',
				'post_author'  => isset( $params[ 'post_author' ] ) ? $params[ 'post_author' ] : 0,
			)
		);

		$params['wooCommerce_email_type']   = isset( $params['wooCommerce_email_type'] ) ? $params['wooCommerce_email_type'] : 'default';
		$params['wooCommerce_email_enable'] = isset( $params['wooCommerce_email_enable'] ) ? $params['wooCommerce_email_enable'] : false;

		if ( $post_id ) {
			if ( !empty( $params[ 'html' ] ) ) {
				$editor        = isset( $params['editor'] ) ? $params['editor'] : 'advanced-builder';
				$thumbnail_url = $this->upload_template_thumnail($params[ 'thumbnail' ]);
				update_post_meta( $post_id, 'mailmint_email_template_thumbnail', $thumbnail_url );
				update_post_meta( $post_id, 'mailmint_email_template_html_content', $params[ 'html' ] );
				update_post_meta( $post_id, 'mailmint_email_template_json_content', $params[ 'json_content' ] );
				update_post_meta( $post_id, 'mailmint_email_editor_type', $editor );
				update_post_meta( $post_id, 'mailmint_wc_email_type', $params[ 'wooCommerce_email_type' ] );
				update_post_meta( $post_id, 'mailmint_wc_customize_enable', $params['wooCommerce_email_enable'] );
			}

			$data = array(
				'id' 	          => $post_id,
				'title'           => $params[ 'title' ],
				'created_at'      => current_time('mysql'),
				'html_content'    => $params[ 'html' ],
				'json_content'    => $params[ 'json_content' ],
				'thumbnail_image' => $thumbnail_url,
			);
			return $this->get_success_response( __('Email template has been saved successfully.', 'mrm'), 200, $data );
		}
		return $this->get_error_response( __('Template could not be saved.', 'mrm'), 401 );
	}

	/**
	 * Get save email templates
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 *
	 * @return array|\WP_Error
	 * @since 1.0.0
	 */
	public function get_email_templates( WP_REST_Request $request ) {
		// Receive params from POST API request and prepare email data.
		$params   = MrmCommon::get_api_params_values( $request );
		$perBatch = ! empty( $params[ 'per_batch' ] ) ? (int) $params[ 'per_batch' ] : '';
		$offset   = ! empty( $params[ 'offset' ] ) ? (int) $params[ 'offset' ] : '';

		if ( ! empty( $params[ 'user_id' ] ) ) {
			$templates_data = get_posts(
				array(
					'post_type'      => 'mint_email_template',
					'post_status'    => 'draft',
					'post_author'    => (int) $params[ 'user_id' ],
					'posts_per_page' => $perBatch,
					'offset'         => $offset,
					'orderby'        => 'ID',
					'order'          => 'DESC',
				)
			);

			if ( ! empty( $templates_data ) ) {
				$templates = array();
				foreach ( $templates_data as $template ) {
					if ( ! isset( $template->ID ) ) {
						continue;
					}

					$templates['templates'][] = array(
						'id'              => $template->ID,
						'title'           => $template->post_title,
						'created_at'      => MrmCommon::date_time_format_with_core( $template->post_date ),
						'html_content'    => get_post_meta( $template->ID, 'mailmint_email_template_html_content', true ),
						'json_content'    => get_post_meta( $template->ID, 'mailmint_email_template_json_content', true ),
						'thumbnail_image' => get_post_meta( $template->ID, 'mailmint_email_template_thumbnail', true ),
					);
				}

				$templates[ 'total_templates' ] = wp_count_posts( 'mint_email_template' )->draft;

				return $this->get_success_response( 'Templates fetched successfully.', 200, $templates );
			}

			return $this->get_success_response( 'No saved templates found.', 200 );
		}

		return $this->get_error_response( 'Invalid user ID.', 402 );
	}

	/**
	 * Save template thumbnail image from image data source
	 *
	 * @param string $thumbnail_data Image data source.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	private function upload_template_thumnail( $thumbnail_data ) {
		if ( ! empty( $thumbnail_data ) ) {
			$thumbnail_data = explode( ',', $thumbnail_data );
			$thumbnail_data = !empty( $thumbnail_data[1] ) ? base64_decode($thumbnail_data[1]) : '';

			if ( '' === $thumbnail_data ) {
				return;
			}
		}
		else {
			return;
		}

		$template_thumbnail_dir = MRM_UPLOAD_DIR . '/template-thumbnails/campaigns';
		$template_thumbnail_url = MRM_UPLOAD_URL . '/template-thumbnails/campaigns';

		if ( !file_exists( $template_thumbnail_dir ) ) {
			wp_mkdir_p( $template_thumbnail_dir );
		}

		$image_name = rand( time(), time() + time() ) . '.png';
		$image_dir = $template_thumbnail_dir . '/' . $image_name;
		$image_url = $template_thumbnail_url . '/' . $image_name;

		return file_put_contents( $image_dir, $thumbnail_data ) ? array( 'url' => $image_url, 'path' => $image_dir ) : '';
	}

	/**
	 * Delete email template
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 *
	 * @return array|\WP_Error
	 * @since 1.0.0
	 */
	public function delete_template( WP_REST_Request $request ) {
		// Receive params from POST API request and prepare email data.
		$params      = MrmCommon::get_api_params_values( $request );
		$user_id     = ! empty( $params[ 'user_id' ] ) ? $params[ 'user_id' ] : null;
		$template_id = ! empty( $params[ 'template_id' ] ) ? $params[ 'template_id' ] : null;
		$thumbnail   = get_post_meta( $template_id, 'mailmint_email_template_thumbnail', true );
		$thumbnail   = ! empty( $thumbnail[ 'path' ] ) ? $thumbnail[ 'path' ] : null;

		if ( $user_id && $template_id && wp_delete_post( $template_id ) ) {
			if ( ! empty( $thumbnail ) ) {
				unlink( $thumbnail );
			}

			return $this->get_success_response( __( 'Template has been successfully deleted.', 'mrm' ), 200 );
		}

		return $this->get_error_response( __( 'Template could be deleted.', 'mrm' ), 402 );
	}

	/**
	 * Retrieve default email templates
	 *
	 * @param WP_REST_Request $request WP Rest Request.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function get_default_email_templates( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$limit  = isset($params['limit']) ? intval($params['limit']) : 10;
		$offset = isset($params['offset']) ? intval($params['offset']) : 0;

		// Slice the array to retrieve the templates for the current page.
		$templates = DefaultEmailTemplates::get_default_templates();
        $templates = array_slice($templates, $offset, $limit);
		return $this->get_success_response( __('Template fetched successfully.', 'mrm'), 200, $templates );
	}

	/**
	 * Retrieves the latest published post.
	 *
	 * This function queries the database for the most recent post of type 'post'
	 * that has a status of 'publish'. It returns the latest post object if found,
	 * otherwise it returns null.
	 *
	 * @return WP_Post|null The latest post object if found, otherwise null.
	 * @since 1.13.0
	 */
	private function get_latest_post() {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$query = new WP_Query( $args );
		if ($query->have_posts()) {
			return $query->posts[0];
		}
		return null;
	}
}
