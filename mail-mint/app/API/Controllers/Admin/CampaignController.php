<?php
/**
 * REST API Campaign Controller
 *
 * Handles requests to the campaign endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\MRM\DataBase\Models\EmailModel;
use MailMintPro\Mint\Internal\Admin\Segmentation\FilterSegmentContacts;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use Exception;
use MRM\Common\MrmCommon;
use Mint\MRM\DataBase\Models\CampaignModel as ModelsCampaign;
use MintMail\App\Internal\Automation\AutomationLogModel;
use WP_REST_Response;
use Mint\MRM\Utilites\Helper\Campaign;

/**
 * This is the main class that controls the campaign feature. Its responsibilities are:
 *
 * - Create or update a custom field
 * - Delete single or multiple campaign
 * - Retrieve single or multiple campaign
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class CampaignController extends AdminBaseController {

	use Singleton;


	/**
	 * Campaign object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args = array();


	/**
	 * Campaign array from API response
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $campaign_data;


	/**
	 * Get and send response to create or update a campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );
		// Assign Untitled as value if title is empty.
		if ( isset( $params['title'] ) && empty( $params['title'] ) ) {
			$params['title'] = 'Untitled';
		}
		if ( strlen( $params['title'] ) > 150 ) {
			return $this->get_error_response( __( 'Campaign title character limit exceeded 150 characters', 'mrm' ), 200 );
		}

		$emails = isset( $params['emails'] ) ? $params['emails'] : array();

		// Email subject validation.
		if ( isset( $params['status'] ) ) {
			foreach ( $emails as $index => $email ) {

				$sender_email       = isset( $email['sender_email'] ) ? $email['sender_email'] : '';
				$email_subject      = isset( $email['email_subject'] ) ? $email['email_subject'] : '';
				$email_preview_text = isset( $email['email_preview_text'] ) ? $email['email_preview_text'] : '';

				if ( isset( $sender_email ) && empty( $sender_email ) ) {
					/* translators: %d email index */
					return $this->get_error_response( sprintf( __( 'Sender email is missing on email %d', 'mrm' ), ( $index + 1 ) ), 200 );
				}
				if ( ! is_email( $sender_email ) ) {
					/* translators: %d email index */
					return $this->get_error_response( sprintf( __( 'Sender Email Address is not valid on email %d.', 'mrm' ), ( $index + 1 ) ), 203 );
				}

				if ( strlen( $email_subject ) > 190 ) {
					return $this->get_error_response( sprintf( __( 'Email subject character limit exceeded 190 characters on email %d.', 'mrm' ), ( $index + 1 ) ), 200 );
				}

				if ( strlen( $email_preview_text ) > 190 ) {
					return $this->get_error_response( sprintf( __( 'Email preview text character limit exceeded 190 characters on email %d.', 'mrm' ), ( $index + 1 ) ), 200 );
				}
			}
		}

		try {
			// Update a campaign if campaign_id present on API request.
			if ( isset( $params['campaign_id'] ) ) {

				$campaign_id = $params['campaign_id'];
				$this->campaign_data = ModelsCampaign::update( $params, $campaign_id );

				if ( $this->campaign_data ) {
					// Update campaign recipients into meta table.
					$recipients       = isset( $params['recipients'] ) ? maybe_serialize( $params['recipients'] ) : '';
					$total_recipients = isset( $params['totalRecipients'] ) ? $params['totalRecipients'] : '';

					ModelsCampaign::insert_or_update_campaign_meta( $campaign_id, 'recipients', $recipients );
					if ( isset( $this->campaign_data['status'] ) && 'active' === $this->campaign_data['status'] || 'schedule' === $this->campaign_data['status'] ) {
						ModelsCampaign::insert_or_update_campaign_meta( $campaign_id, 'total_recipients', $total_recipients );
					}

					if ( isset( $this->campaign_data['status'] ) && ( 'active' === $this->campaign_data['status'] || 'schedule' === $this->campaign_data['status'] ) && 'recurring' === $this->campaign_data['type']  ) {
						$recurring_properties = isset( $params['recurringData'] ) ? maybe_serialize( $params['recurringData'] ) : '';
						ModelsCampaign::insert_or_update_campaign_meta( $campaign_id, 'recurring_properties', $recurring_properties );
					}

					// Update emails list.
					$emails = isset( $params['emails'] ) ? $params['emails'] : array();

					// set send_time key for all email of campaign.
					$emails = array_map(
						function( $email ) {
							$email['send_time'] = 0;
							return $email;
						},
						$emails
					);

					foreach ( $emails as $index => $email ) {
						// counting the sending time for each email.
						$delay = isset( $email['delay'] ) ? $email['delay'] : 0;

						if ( 0 === $index ) {
							$email['send_time']            = microtime( true );
							$emails[ $index ]['send_time'] = $email['send_time'];
						} else {
							$prev_send_time                = $emails[ $index - 1 ]['send_time'];
							$email['send_time']            = $delay + $prev_send_time;
							$emails[ $index ]['send_time'] = $email['send_time'];
						}

						$data['campaign'] = $this->campaign_data;

						if ( isset( $data['campaign']['status'] ) && 'active' === $data['campaign']['status'] || 'schedule' === $data['campaign']['status'] ) {
							$email['scheduled_at'] = current_time( 'mysql' );
							$email['status']       = 'scheduling';
						}

						if ( isset( $data['campaign']['status'] ) && 'draft' === $data['campaign']['status'] ) {
							$email['scheduled_at'] = null;
							$email['status']       = 'draft';
						}

						$last_email_id = ModelsCampaign::update_campaign_emails( $email, $campaign_id, $index );
					}
					$this->campaign_data['last_email_id'] = $last_email_id;
				}
			} else {
				// Insert campaign information.
				$this->campaign_data = ModelsCampaign::insert( $params );
				$campaign_id         = isset( $this->campaign_data['id'] ) ? $this->campaign_data['id'] : '';
				if ( $campaign_id ) {
					// Insert campaign recipients information.
					$recipients = isset( $params['recipients'] ) ? maybe_serialize( $params['recipients'] ) : '';
					ModelsCampaign::insert_or_update_campaign_meta( $campaign_id, 'recipients', $recipients );

					// Insert campaign emails information.
					$emails = isset( $params['emails'] ) ? $params['emails'] : array();

					// set send_time key for all email of campaign.
					$emails = array_map(
						function( $email ) {
							$email['send_time'] = 0;
							return $email;
						},
						$emails
					);

					foreach ( $emails as $index => $email ) {
						// counting the sending time for each email.
						$delay = isset( $email['delay'] ) ? $email['delay'] : 0;

						if ( 0 === $index ) {
							$email['send_time']            = microtime( true );
							$emails[ $index ]['send_time'] = microtime( true );
						} else {
							$prev_send_time                = $emails[ $index - 1 ]['send_time'];
							$email['send_time']            = $delay + $prev_send_time;
							$emails[ $index ]['send_time'] = $email['send_time'];
						}

						$data['campaign'] = $this->campaign_data;

						if ( isset( $data['campaign']['status'] ) && 'active' === $data['campaign']['status'] ) {
							$email['scheduled_at'] = current_time( 'mysql' );
							$email['status']       = 'scheduling';
						}

						if ( isset( $data['campaign']['status'] ) && 'draft' === $data['campaign']['status'] ) {
							$email['scheduled_at'] = null;
							$email['status']       = 'draft';
						}
						$last_email_id = ModelsCampaign::insert_campaign_emails( $email, $campaign_id, $index );
						$this->campaign_data['emails']['last_email_id'] = $last_email_id;
					}
				}
			}

			// Send renponses back to the frontend.
			if ( $this->campaign_data ) {
				$data['campaign'] = $this->campaign_data;
				if ( isset( $data['campaign']['status'], $data['campaign']['type'] ) && 'automation' !== $data['campaign']['type'] ) {
					$first_email = ModelsCampaign::get_first_campaign_email( $campaign_id );

					if ( 'active' === $data['campaign']['status'] ) {
						ModelsCampaign::schedule_campaign_action( $campaign_id, $first_email, 'active' );
						return $this->get_success_response( __( 'Campaign has been started successfully.', 'mrm' ), 201, $data );
					}

					if ( 'schedule' === $data['campaign']['status']  && 'recurring' !== $data['campaign']['type'] ) {
						if ( !empty( $data[ 'campaign' ][ 'scheduled_at' ] ) ) {
							ModelsCampaign::schedule_campaign_action( $campaign_id, $first_email, 'schedule', $data[ 'campaign' ][ 'scheduled_at' ] );
						}
						return $this->get_success_response( __( 'Campaign has been scheduled successfully.', 'mrm' ), 201, $data );
					}

					if ( 'schedule' === $data['campaign']['status'] && 'recurring' === $data['campaign']['type'] ) {
						if ( !empty( $data[ 'campaign' ][ 'scheduled_at' ] ) ) {
							/**
							 * Fires when processing a recurring campaign in Mail Mint.
							 *
							 * This action is triggered when a recurring campaign is being processed in the MailMint plugin.
							 *
							 * @param array $campaign_data Data related to the recurring campaign.
							 *                             - 'campaign' (int) The ID of the recurring campaign being processed.
							 *
							 * @hook mailmint_process_recurring_campaign
							 * 
							 * @since 1.6.0
							 */
							do_action( 'mailmint_process_recurring_campaign', $data['campaign'] );
						}
						return $this->get_success_response( __( 'Campaign has been scheduled successfully.', 'mrm' ), 201, $data );
					}

				}
				return $this->get_success_response( __( 'Campaign has been saved successfully', 'mrm' ), 201, $data );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Failed to save campaign', 'mrm' ), 400 );
		}
	}

	/**
	 * Get and send response to send campaign email
	 *
	 * @param int   $campaign_id Campaign ID to get contacts email.
	 * @param mixed $params Campaign parameters.
	 * @return void
	 * @since 1.0.0
	 */
	public static function send_campaign_email( $campaign_id, $params ) {
		$campaign = ModelsCampaign::get( $campaign_id );

		$meta = maybe_unserialize( $campaign->meta );

		$tags  = $meta['tags'];
		$lists = $meta['lists'];

		$groups = array_merge( $tags, $lists );

		$count     = ContactGroupPivotModel::get_contacts_count_to_campaign( $groups );
		$per_batch = 30;

		$total_batch = ceil( $count / $per_batch );

		for ( $i = 1; $i <= $total_batch; $i++ ) {
			$contacts = ContactGroupPivotModel::get_contacts_to_campaign( $groups, $i + $per_batch, $per_batch );
			$messages = array_map(
				function( $contact ) use ( $campaign ) {
					return array(
						'email_address' => $contact->email,
						'email_subject' => $campaign->email_subject,
						'email_body'    => $campaign->email_body,
						'contact_id'    => $contact->id,
						'sender_email'  => $campaign->sender_email,
						'sender_name'   => $campaign->sender_name,
						'campaign_id'   => $campaign->id,
					);
				},
				$contacts
			);

			do_action( 'mailmint_send_campaign_email', $messages );
		}
	}


	/**
	 * Request for deleting a single campaign to Campaign Model by Campaign ID
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {

		// Get values from API.
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : '';
		$success     = ModelsCampaign::destroy( $campaign_id );

		ModelsCampaign::unschedule_campaign_actions( $campaign_id );

		if ( $success ) {
			return $this->get_success_response( __( 'Campaign has been deleted successfully', 'mrm' ), 200 );
		}
		return $this->get_error_response( __( 'Failed to Delete', 'mrm' ), 400 );
	}


	/**
	 * Request for deleting a email from a campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function delete_campaign_email( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : '';
		$email_id    = isset( $params['email_id'] ) ? $params['email_id'] : '';

		$success = ModelsCampaign::remove_email_from_campaign( $campaign_id, $email_id );
		if ( $success ) {
			return $this->get_success_response( __( 'Campaign email has been deleted successfully', 'mrm' ), 200 );
		}
		return $this->get_error_response( __( 'Failed to Delete', 'mrm' ), 400 );
	}


	/**
	 * Request for deleting multiple campaigns to Campaign Model by Campaign ID
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$campaign_ids = isset( $params['campaign_ids'] ) ? $params['campaign_ids'] : array();

		foreach ( $campaign_ids as $campaign_id ) {
			ModelsCampaign::unschedule_campaign_actions( $campaign_id );
		}

		$success = ModelsCampaign::destroy_all( $campaign_ids );

		if ( $success ) {
			return $this->get_success_response( __( 'Campaign has been deleted successfully', 'mrm' ), 200 );
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Get all campaign request to Campaign Model
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {
		global $wpdb;
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );
		
		$page     = isset( $params['page'] ) ? $params['page'] : 1;
		$per_page = isset( $params['per-page'] ) ? $params['per-page'] : 10;
		$offset   = ( $page - 1 ) * $per_page;

		$order_by   = isset( $params['order-by'] ) ? strtolower( $params['order-by'] ) : 'id';
		$order_type = isset( $params['order-type'] ) ? strtolower( $params['order-type'] ) : 'desc';

		// valid order by fields and types.
		$allowed_order_by_fields = array( 'title', 'created_at' );
		$allowed_order_by_types  = array( 'asc', 'desc' );

		// validate order by fields or use default otherwise.
		$order_by   = in_array( $order_by, $allowed_order_by_fields, true ) ? $order_by : 'id';
		$order_type = in_array( $order_type, $allowed_order_by_types, true ) ? $order_type : 'desc';

		// Contact Search keyword.
		$search = isset( $params['search'] ) ? $params['search'] : '';

		// Contact filter keyword.
		$filter     = isset( $params['filter'] ) ? $params['filter'] : '';
		$filterType = isset( $params['type'] ) ? $params['type'] : '';
		$status     = isset( $params['status'] ) ? $params['status'] : '';

		$campaigns = ModelsCampaign::get_all( $wpdb, $offset, $per_page, $search, $order_by, $order_type, $filter, $filterType, $status );
		$campaigns['current_page'] = (int) $page;

		// Prepare human_time_diff for every campaign.
		if ( isset( $campaigns['campaigns'] ) ) {
			$campaigns['campaigns'] = array_map(
				function( $campaign ) {
					if ( isset( $campaign['id'] ) ) {
						$campaign_status = isset( $campaign['status'] ) ? $campaign['status'] : 'draft';
						$campaign_type   = isset( $campaign['type'] ) ? $campaign['type'] : 'regular';
						if( 'draft' !== $campaign_status && ( 'regular' === $campaign_type ||  'sequence' === $campaign_type || 'recurring' === $campaign_type ) ){

							$campaign['total_recipients'] = ModelsCampaign::get_campaign_meta_value( $campaign['id'], 'total_recipients' );
		
							if( 'recurring' === $campaign_type ) {
								$campaign['total_recipients'] = EmailModel::recurring_campaign_total_recipients( $campaign['id'] );
							}
							$total_bounced                = EmailModel::count_delivered_status_on_campaign( $campaign['id'], 'failed' );
							$campaign['open_rate']        = ModelsCampaign::prepare_campaign_open_rate( $campaign['id'], $campaign['total_recipients'], $total_bounced );
							$campaign['click_rate']       = ModelsCampaign::prepare_campaign_click_rate( $campaign['id'], $campaign['total_recipients'], $total_bounced );
							$campaign['unsubscribe']      = EmailModel::count_unsubscribe_on_campaign( $campaign['id'] );
						} elseif( 'draft' !== $campaign_status && 'automation' === $campaign_type ){
							$campaign['automation_stats'] = AutomationLogModel::prepare_automation_statistics_for_campaign( $campaign['id'] );
						} else {
                        	$campaign['total_recipients'] = ModelsCampaign::get_campaign_meta_value( $campaign['id'], 'total_recipients' );
						}
						$campaign['scheduled_at']     = MrmCommon::format_campaign_date_time( 'scheduled_at', $campaign );
						$campaign['updated_at']       = MrmCommon::format_campaign_date_time( 'updated_at', $campaign );
					}
					return $campaign;
				},
				$campaigns['campaigns']
			);
		}
		if ( isset( $campaigns ) ) {
			return $this->get_success_response_data( $campaigns );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function use to get single campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {
		
		// Get values from REST API JSON.
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = !empty( $params['campaign_id'] ) ? $params['campaign_id'] : '';
		$campaign    = ModelsCampaign::get( $campaign_id );

		if ( !empty( $campaign[ 'status' ] ) && 'draft' !== $campaign[ 'status' ] ) {
			$campaign[ 'total_open' ]        = EmailModel::calculate_open_rate_on_campaign( $campaign_id );
			$campaign[ 'total_click' ]       = EmailModel::calculate_click_rate_on_campaign( $campaign_id );
			$campaign[ 'total_bounced' ]     = EmailModel::calculate_bounched_on_campaign( $campaign_id );
			$campaign[ 'total_unsubscribe' ] = EmailModel::count_unsubscribe_on_campaign( $campaign_id );
			$campaign['total_recipients']    = ModelsCampaign::get_campaign_meta_value( $campaign_id, 'total_recipients' );

			$unsubscribed_rate = 0;
			if ( !empty( $campaign['total_recipients'] ) && 0 !== (int) $campaign['total_recipients'] ){
				$unsubscribed_rate = ( $campaign['total_unsubscribe'] / $campaign['total_recipients'] ) * 100;
			}

			$campaign['unsubscribed_rate'] = $unsubscribed_rate;
		}

		// Now we can use our timestamp with get_date_from_gmt().
		$time                  = new \DateTimeImmutable( isset( $campaign['scheduled_at'] ) ? $campaign['scheduled_at'] : "", wp_timezone() );
		$date_format           = get_option( 'date_format' );
		$time_format           = get_option( 'time_format' );
		$campaign['scheduled_at'] = sprintf( esc_html__( 'Schedule at %s', 'mrm' ), $time->format($date_format . ' ' . $time_format) );
		
		// Prepare campaign data for response.
		$campaign['meta']['recipients'] = !empty( $campaign['meta_value'] ) ? maybe_unserialize( $campaign['meta_value'] ) : [];
		unset( $campaign['meta_key'] );
		unset( $campaign['meta_value'] );
		// Verify saved campaigns selected group ids if they still exist.
		$campaign['meta']['recipients'] = ModelsCampaign::get_campaign_meta_value($campaign_id, 'recipients');
		$campaign['meta']['recipients'] = maybe_unserialize($campaign['meta']['recipients']);
		$campaign['meta']['recipients'] = MrmCommon::filter_recipients( $campaign['meta']['recipients'], $campaign[ 'status' ] );
		ModelsCampaign::update_campaign_recipients( maybe_serialize($campaign['meta']['recipients']), $campaign_id );

		if( 'recurring' === $campaign['type'] ) {
			$recurring_properties      = ModelsCampaign::get_campaign_meta_value( $campaign_id, 'recurring_properties' );
			$recurring_properties      = !empty( $recurring_properties ) ? maybe_unserialize( $recurring_properties ) : [];

			// Construct the final scheduled_at.
			$campaign['scheduled_at']  = Campaign::prepare_recurring_schedule_sentence( $recurring_properties );
			$campaign['recurringData'] = $recurring_properties;
		}
		if ( isset( $campaign ) ) {
			return $this->get_success_response( 'Campaign has been retrieved successfully.', 200, $campaign );
		}
		return $this->get_error_response( 'Failed to retrieve the campaign.', 400 );
	}

	/**
	 * Function use to get duplicate campaign
	 * 
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * 
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function rest_campaign_duplicate_callback( WP_REST_Request $request ) {
		// get sanitized GET request data.
		$get 		 = MrmCommon::get_api_params_values( $request );
		$campaign_id = !empty( $get['campaign_id'] ) ? $get['campaign_id'] : '';
		$campaign    = ModelsCampaign::get_campaign_to_duplicate( $campaign_id );

		$campaign['meta']['recipients'] = maybe_unserialize( $campaign['meta_value'] );
		return $this->get_success_response( 'Query Successful', 200, $campaign );
	}

	/**
	 * Update a campaign's status
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array|WP_REST_Response|\WP_Error
	 * @since 1.0.0
	 */
	public function status_update( WP_REST_Request $request ) {
		// Get params from status update API request.
		$params = MrmCommon::get_api_params_values( $request );
		$status      = isset( $params['status'] ) ? $params['status'] : '';
		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : '';

		$update = ModelsCampaign::update_campaign_status( $campaign_id, $status );

		if ( 'suspended' === $status ) {
			ModelsCampaign::unschedule_campaign_actions( $campaign_id );
		}

		if ( $update ) {
			return $this->get_success_response( __( 'Campaign status has been updated successfully', 'mrm' ), 201 );
		}
		return $this->get_error_response( __( 'Failed to update campaign status', 'mrm' ), 400 );
	}

	/**
	 * Get subscriber count by list/tag ids
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 *
	 * @return array
	 */
	public function get_subscribers( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$subscribers = 0;
		$segment_ids = '';

		if ( ! empty( $params[ 'lists' ] ) || ! empty( $params[ 'tags' ] ) || ! empty( $params[ 'segment' ] ) ) {
			$list_ids    = ! empty( $params[ 'lists' ] ) ? explode( ', ', $params[ 'lists' ] ) : [];
			$tag_ids     = ! empty( $params[ 'tags' ] ) ? explode( ', ', $params[ 'tags' ] ) : [];
			$segment_ids = ! empty( $params[ 'segment' ] ) ? explode( ', ', $params[ 'segment' ] ) : [];
		} elseif ( ! empty( $params[ 'campaign_id' ] ) ) {
			$campaign_id = $params[ 'campaign_id' ];

			$recipients = ModelsCampaign::get_campaign_meta_value( $campaign_id, 'recipients' );
			$recipients = maybe_unserialize( $recipients );

			$list_ids = array_column( $recipients[ 'lists' ], 'id' );
			$tag_ids  = array_column( $recipients[ 'tags' ], 'id' );

			$segment_ids = array_column( $recipients[ 'segments' ], 'id' );
			$segment_ids = ! empty( $segment_ids ) ? $segment_ids : [];
		}

		if ( ! empty( $segment_ids ) ) {
			$contact_ids = [];

			if ( class_exists( 'MailMintPro\Mint\Internal\Admin\Segmentation\FilterSegmentContacts' ) ) {
				foreach ( $segment_ids as $segment_id ) {
					$segment_data  = FilterSegmentContacts::get_segment( $segment_id );
					$contact_ids[] = !empty( $segment_data[ 'contacts' ][ 'data' ] ) ? array_column(
						array_filter($segment_data['contacts']['data'], function ($contact) {
							return $contact['status'] === 'subscribed';
						}),
						'id'
					) : [];
				}
			}
			$subscribers = sizeof( array_unique( array_merge( ...array_values( $contact_ids ) ) ) );
		} elseif ( ! empty( $list_ids ) || ! empty( $tag_ids ) ) {
			$subscribers = (int) ContactGroupPivotModel::get_contacts_to_group( array_merge( $list_ids, $tag_ids ), 0, 0, true );
		}

		return $this->get_success_response( __( 'Subscriber list count successfully fetched.', 'mrm' ), 200, $subscribers );
	}

    /**
     * Get subscriber count by list/tag ids
     *
     * @param WP_REST_Request $request WP_REST_Request.
     * @return array|\WP_Error|\WP_HTTP_Response|WP_REST_Response
     * @since 1.4.3
     */
    public function hide_smtp_notice( WP_REST_Request $request )
    {
        $params      = MrmCommon::get_api_params_values( $request );
        if( !empty( $params['remove_smtp_notice'] ) ){
            $notice = $params['remove_smtp_notice'];
            update_option('mint_notice_update',$notice);
            return $this->get_success_response( __( 'Notice updated successfully', 'mrm' ), 201 );
        }
        return $this->get_error_response( __( 'Failed to update notice status', 'mrm' ), 400 );
    }
}