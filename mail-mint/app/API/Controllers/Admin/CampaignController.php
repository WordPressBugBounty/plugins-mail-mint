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

use WP_Error;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use MRM\Common\MrmCommon;
use Mint\MRM\Utilites\Helper\Campaign;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\DataBase\Tables\EmailSchema;
use Mint\MRM\Database\Enums\CampaignType;
use Mint\MRM\Database\AbstractRepository;
use Mint\MRM\Database\Enums\CampaignStatus;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\EmailMetaSchema;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\MRM\Database\Repositories\CampaignRepository;
use MintMail\App\Internal\Automation\AutomationLogModel;
use Mint\MRM\API\Controllers\Traits\CrudControllerTrait;
use Mint\MRM\DataBase\Models\CampaignModel as ModelsCampaign;

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
	use CrudControllerTrait;

	/**
	 * Returns the repository instance for campaign CRUD operations.
	 *
	 * @since 1.20.0
	 *
	 * @return CampaignRepository
	 */
	protected function repository(): AbstractRepository {
		return new CampaignRepository();
	}

	/**
	 * Returns the primary key parameter name for campaign requests.
	 *
	 * @since 1.20.0
	 *
	 * @return string
	 */
	protected function idKey(): string {
		return 'campaign_id';
	}

	/**
	 * Validate campaign data before create/update.
	 *
	 * @since 1.20.0
	 *
	 * @param array $data Request data.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	protected function validate( array $data ) {
		if ( isset( $data['title'] ) && strlen( $data['title'] ) > 150 ) {
			return $this->get_error_response( __( 'Campaign title character limit exceeded 150 characters', 'mrm' ), 200 );
		}

		$emails = isset( $data['emails'] ) ? $data['emails'] : array();

		if ( isset( $data['status'] ) ) {
			foreach ( $emails as $index => $email ) {
				$sender_email       = isset( $email['sender_email'] ) ? $email['sender_email'] : '';
				$email_subject      = isset( $email['email_subject'] ) ? $email['email_subject'] : '';
				$email_preview_text = isset( $email['email_preview_text'] ) ? $email['email_preview_text'] : '';

				if ( empty( $sender_email ) ) {
					/* translators: %d email index */
					return $this->get_error_response( sprintf( __( 'Sender email is missing on email %d', 'mrm' ), ( $index + 1 ) ), 200 );
				}
				if ( ! is_email( $sender_email ) ) {
					/* translators: %d email index */
					return $this->get_error_response( sprintf( __( 'Sender Email Address is not valid on email %d.', 'mrm' ), ( $index + 1 ) ), 203 );
				}

				if ( strlen( $email_subject ) > 190 ) {
					/* translators: %d email index */
					return $this->get_error_response( sprintf( __( 'Email subject character limit exceeded 190 characters on email %d.', 'mrm' ), ( $index + 1 ) ), 200 );
				}

				if ( strlen( $email_preview_text ) > 190 ) {
					/* translators: %d email index */
					return $this->get_error_response( sprintf( __( 'Email preview text character limit exceeded 190 characters on email %d.', 'mrm' ), ( $index + 1 ) ), 200 );
				}
			}
		}

		if ( isset( $data['type'] ) && ! CampaignType::isValid( $data['type'] ) ) {
			return $this->get_error_response( __( 'Invalid campaign type', 'mrm' ), 400 );
		}

		if ( isset( $data['status'] ) && ! CampaignStatus::isValid( $data['status'] ) ) {
			return $this->get_error_response( __( 'Invalid campaign status', 'mrm' ), 400 );
		}

		return true;
	}

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


	// =========================================================================
	// CRUD Operations 
	// =========================================================================

	/**
	 * Create or update a campaign.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @since 1.0.0
	 * @since 1.20.0 Refactored to use CampaignRepository via CrudControllerTrait.
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['title'] ) && empty( $params['title'] ) ) {
			$params['title'] = 'Untitled';
		}

		$validation = $this->validate( $params );
		if ( true !== $validation ) {
			return $validation;
		}

		try {
			if ( isset( $params['campaign_id'] ) ) {
				$campaign_id = $params['campaign_id'];
				$this->handle_campaign_update( $campaign_id, $params );
			} else {
				$campaign_id = $this->handle_campaign_create( $params );
			}

			if ( ! $this->campaign_data ) {
				return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
			}

			return $this->build_save_response( $campaign_id );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Failed to save campaign', 'mrm' ), 400 );
		}
	}

	/**
	 * Toggle whether a campaign appears in the public archive.
	 *
	 * Lightweight endpoint used from the sent-campaign view so existing
	 * campaigns can be opted in/out of the archive without re-saving the whole
	 * campaign (which would bump updated_at and re-process email steps).
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @since 1.24.0
	 */
	public function update_archive_visibility( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = ! empty( $params['campaign_id'] ) ? (int) $params['campaign_id'] : 0;

		if ( ! $campaign_id || ! $this->repository()->find( $campaign_id ) ) {
			return $this->get_error_response( __( 'Campaign not found.', 'mrm' ), 404 );
		}

		$enabled = ! empty( $params['show_in_archive'] );
		$this->save_archive_setting( $campaign_id, array( 'show_in_archive' => $enabled ? 1 : 0 ) );

		return $this->get_success_response(
			$enabled
				? __( 'Campaign added to the archive.', 'mrm' )
				: __( 'Campaign removed from the archive.', 'mrm' ),
			200,
			array(
				'show_in_archive' => $enabled,
				'archive_url'     => $this->repository()->getArchiveUrl( $campaign_id ),
			)
		);
	}

	/**
	 * Update the share-bar visibility for an archived campaign.
	 *
	 * Controls whether the social share toolbar is rendered on the public
	 * archive page. Does not affect URL access — that is gated by show_in_archive.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @since 1.25.0
	 */
	public function update_share_visibility( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = ! empty( $params['campaign_id'] ) ? (int) $params['campaign_id'] : 0;

		if ( ! $campaign_id || ! $this->repository()->find( $campaign_id ) ) {
			return $this->get_error_response( __( 'Campaign not found.', 'mrm' ), 404 );
		}

		$visibility = isset( $params['share_visibility'] ) && 'private' === $params['share_visibility']
			? 'private'
			: 'public';

		$this->repository()->insertOrUpdateMeta( $campaign_id, 'share_visibility', $visibility );

		if ( 'public' === $visibility && ! $this->repository()->getMeta( $campaign_id, 'archive_hash' ) ) {
			$this->repository()->insertOrUpdateMeta( $campaign_id, 'archive_hash', wp_generate_password( 24, false ) );
		}

		return $this->get_success_response(
			__( 'Share visibility updated.', 'mrm' ),
			200,
			array(
				'share_visibility' => $visibility,
				'archive_url'      => $this->repository()->getArchiveUrl( $campaign_id ),
			)
		);
	}

	/**
	 * Handle campaign update path.
	 *
	 * @since 1.20.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 * @param array      $params      Request parameters.
	 */
	private function handle_campaign_update( $campaign_id, array $params ) {
		
		if ( CampaignStatus::SCHEDULE === ( $params['status'] ?? '' ) && CampaignType::RECURRING === ( $params['type'] ?? '' ) ) {
			if ( ! empty( $params['scheduled_at'] ) ) {
				$params['scheduled_at'] = gmdate( 'Y-m-d 00:00:01', strtotime( $params['scheduled_at'] ) );
			}
		}

		$this->repository()->update( $campaign_id, $params );
		$this->campaign_data = $this->repository()->find( $campaign_id );

		if ( ! $this->campaign_data ) {
			return;
		}

		$this->save_campaign_meta( $campaign_id, $params );
		$this->save_campaign_emails( $campaign_id, $params, true );
	}

	/**
	 * Handle campaign create path.
	 *
	 * @since 1.20.0
	 *
	 * @param array $params Request parameters.
	 *
	 * @return int|string Campaign ID.
	 */
	private function handle_campaign_create( array $params ) {
		$campaign_id         = $this->repository()->create( $params );
		$this->campaign_data = $campaign_id ? $this->repository()->find( $campaign_id ) : null;
		$campaign_id         = isset( $this->campaign_data['id'] ) ? $this->campaign_data['id'] : '';

		if ( ! $campaign_id ) {
			return '';
		}

		$recipients = isset( $params['recipients'] ) ? maybe_serialize( $this->sanitize_recipient_ids( $params['recipients'] ) ) : '';
		$this->repository()->insertOrUpdateMeta( $campaign_id, 'recipients', $recipients );
		$this->save_utm_params( $campaign_id, $params );
		$this->save_archive_setting( $campaign_id, $params );
		$this->save_share_visibility_setting( $campaign_id, $params );
		$this->save_campaign_emails( $campaign_id, $params, false );

		return $campaign_id;
	}

	/**
	 * Save campaign meta (recipients, total_recipients, recurring_properties).
	 *
	 * Only called during update — create path handles recipients inline.
	 *
	 * @since 1.20.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 * @param array      $params      Request parameters.
	 */
	private function save_campaign_meta( $campaign_id, array $params ) {
		$recipients       = isset( $params['recipients'] ) ? maybe_serialize( $this->sanitize_recipient_ids( $params['recipients'] ) ) : '';
		$total_recipients = isset( $params['totalRecipients'] ) ? $params['totalRecipients'] : '';
		$status           = $this->campaign_data['status'] ?? '';
		$type             = $this->campaign_data['type'] ?? '';

		$this->repository()->insertOrUpdateMeta( $campaign_id, 'recipients', $recipients );
		$this->save_utm_params( $campaign_id, $params );
		$this->save_archive_setting( $campaign_id, $params );
		$this->save_share_visibility_setting( $campaign_id, $params );

		if ( CampaignStatus::ACTIVE === $status || CampaignStatus::SCHEDULE === $status ) {
			$this->repository()->insertOrUpdateMeta( $campaign_id, 'total_recipients', $total_recipients );
		}

		if ( ( CampaignStatus::ACTIVE === $status || CampaignStatus::SCHEDULE === $status ) && CampaignType::RECURRING === $type ) {
			$recurring_properties = isset( $params['recurringData'] ) ? maybe_serialize( $params['recurringData'] ) : '';
			$this->repository()->insertOrUpdateMeta( $campaign_id, 'recurring_properties', $recurring_properties );
		}
	}

	/**
	 * Enforce integer ids on campaign recipients before persistence.
	 *
	 * Recipient ids (lists, tags, segments and their exclude counterparts) are always
	 * group/segment integer ids. Casting them to int here — and dropping entries whose id
	 * is non-numeric — prevents a stored payload such as "1) UNION SELECT ..." from later
	 * reaching a query as a raw string. This is defense-in-depth; queries that consume these
	 * ids must still bind them as integers.
	 *
	 * @param mixed $recipients Recipients structure from the request.
	 *
	 * @return mixed Sanitized recipients (unchanged when not an array).
	 *
	 * @since 1.24.2
	 */
	private function sanitize_recipient_ids( $recipients ) {
		if ( ! is_array( $recipients ) ) {
			return $recipients;
		}

		$group_keys = array( 'lists', 'tags', 'segments', 'exclude_lists', 'exclude_tags' );

		foreach ( $group_keys as $key ) {
			if ( empty( $recipients[ $key ] ) || ! is_array( $recipients[ $key ] ) ) {
				continue;
			}

			foreach ( $recipients[ $key ] as $index => $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['id'] ) ) {
					continue;
				}

				if ( is_numeric( $entry['id'] ) ) {
					$recipients[ $key ][ $index ]['id'] = (int) $entry['id'];
				} else {
					unset( $recipients[ $key ][ $index ] );
				}
			}

			$recipients[ $key ] = array_values( $recipients[ $key ] );
		}

		return $recipients;
	}

	/**
	 * Sanitize and persist UTM params from request into campaign meta.
	 *
	 * @since 1.21.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 * @param array      $params      Request parameters.
	 */
	private function save_utm_params( $campaign_id, array $params ) {
		if ( ! isset( $params['utm'] ) || ! is_array( $params['utm'] ) ) {
			return;
		}
		$utm = array(
			'status'   => intval( $params['utm']['status'] ?? 0 ),
			'source'   => sanitize_text_field( $params['utm']['source'] ?? '' ),
			'medium'   => sanitize_text_field( $params['utm']['medium'] ?? '' ),
			'campaign' => sanitize_text_field( $params['utm']['campaign'] ?? '' ),
			'term'     => sanitize_text_field( $params['utm']['term'] ?? '' ),
			'content'  => sanitize_text_field( $params['utm']['content'] ?? '' ),
		);
		$this->repository()->insertOrUpdateMeta( $campaign_id, 'utm_params', wp_json_encode( $utm ) );
	}

	/**
	 * Persist the "show in archive" setting and ensure a public archive hash exists.
	 *
	 * When a campaign is flagged to appear in the public archive, a stable random
	 * hash is generated once and reused as the slug for its public "view in
	 * browser" URL. The hash is never regenerated, so existing shared links keep
	 * working even if the toggle is turned off and on again.
	 *
	 * @since 1.24.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 * @param array      $params      Request parameters.
	 */
	private function save_archive_setting( $campaign_id, array $params ) {
		if ( ! isset( $params['show_in_archive'] ) ) {
			return;
		}

		$enabled = ! empty( $params['show_in_archive'] ) ? '1' : '0';
		$this->repository()->insertOrUpdateMeta( $campaign_id, 'show_in_archive', $enabled );

		// Generate a stable public hash once, on first enable.
		if ( '1' === $enabled && ! $this->repository()->getMeta( (int) $campaign_id, 'archive_hash' ) ) {
			$this->repository()->insertOrUpdateMeta( $campaign_id, 'archive_hash', wp_generate_password( 24, false ) );
		}
	}

	/**
	 * Persist the social sharing visibility setting independently of archive.
	 *
	 * Defaults to 'private' when the meta row does not yet exist.
	 *
	 * @since 1.24.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 * @param array      $params      Request parameters.
	 */
	private function save_share_visibility_setting( $campaign_id, array $params ) {
		if ( ! isset( $params['share_visibility'] ) ) {
			return;
		}
		$visibility = 'public' === $params['share_visibility'] ? 'public' : 'private';
		$this->repository()->insertOrUpdateMeta( $campaign_id, 'share_visibility', $visibility );
	}

	/**
	 * Save campaign email steps (insert or update).
	 *
	 * Computes send_time per email, sets scheduling status based on campaign
	 * status, strips non-DB fields, and persists via repository.
	 *
	 * @since 1.20.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 * @param array      $params      Request parameters.
	 * @param bool       $is_update   True for update path, false for create path.
	 */
	private function save_campaign_emails( $campaign_id, array $params, bool $is_update ) {
		$emails = isset( $params['emails'] ) ? $params['emails'] : array();
		$status = $this->campaign_data['status'] ?? '';

		// Initialize send_time for all emails.
		$emails = array_map(
			function ( $email ) {
				$email['send_time'] = 0;
				return $email;
			},
			$emails
		);

		$last_email_id = 0;

		foreach ( $emails as $index => $email ) {
			// Compute cumulative send_time with delay offsets.
			$emails = $this->compute_email_send_time( $emails, $index );
			$email  = $emails[ $index ];

			// Set email scheduling status based on campaign status.
			$email = $this->apply_email_scheduling_status( $email, $status, $is_update );

			// Strip non-DB fields and persist.
			$last_email_id = $this->persist_campaign_email( $campaign_id, $email, $index, $is_update );

			// Handle custom date scheduling meta.
			$this->save_email_schedule_meta( $last_email_id, $email );
		}

		// Store last_email_id in the response data structure.
		if ( $is_update ) {
			$this->campaign_data['last_email_id'] = $last_email_id;
		} else {
			$this->campaign_data['emails']['last_email_id'] = $last_email_id;
		}
	}

	/**
	 * Compute cumulative send_time for an email at a given index.
	 *
	 * @since 1.20.0
	 *
	 * @param array $emails All emails array (modified by reference via return).
	 * @param int   $index  Current email index.
	 *
	 * @return array Updated emails array with send_time set.
	 */
	private function compute_email_send_time( array $emails, int $index ): array {
		$delay = isset( $emails[ $index ]['delay'] ) ? $emails[ $index ]['delay'] : 0;

		if ( 0 === $index ) {
			$emails[ $index ]['send_time'] = microtime( true );
		} else {
			$prev_send_time                = $emails[ $index - 1 ]['send_time'];
			$emails[ $index ]['send_time'] = $delay + $prev_send_time;
		}

		return $emails;
	}

	/**
	 * Apply scheduling status to an email based on campaign status.
	 *
	 * @since 1.20.0
	 *
	 * @param array  $email     Email data.
	 * @param string $status    Campaign status.
	 * @param bool   $is_update Whether this is an update (checks SCHEDULE too) or create (ACTIVE only).
	 *
	 * @return array Email data with status and scheduled_at set.
	 */
	private function apply_email_scheduling_status( array $email, string $status, bool $is_update ): array {
		$should_schedule = $is_update
			? ( CampaignStatus::ACTIVE === $status || CampaignStatus::SCHEDULE === $status )
			: ( CampaignStatus::ACTIVE === $status );

		if ( $should_schedule ) {
			$email['scheduled_at'] = current_time( 'mysql' );
			$email['status']       = 'scheduling';
		}

		if ( CampaignStatus::DRAFT === $status ) {
			$email['scheduled_at'] = null;
			$email['status']       = CampaignStatus::DRAFT;
		}

		return $email;
	}

	/**
	 * Strip non-DB fields and persist a campaign email (insert or update).
	 *
	 * @since 1.20.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 * @param array      $email       Email data.
	 * @param int        $index       Email index in the sequence.
	 * @param bool       $is_update   True for update path, false for create path.
	 *
	 * @return int Last email ID (inserted or updated).
	 */
	private function persist_campaign_email( $campaign_id, array $email, int $index, bool $is_update ): int {
		$email_data = $email;

		// Strip fields that don't belong in the DB.
		unset( $email_data['email_body'], $email_data['email_json'], $email_data['delay_option'], $email_data['scheduleDate'] );

		if ( ! $is_update ) {
			// Create path strips additional frontend-only fields.
			unset( $email_data['toError'], $email_data['senderEmailError'], $email_data['email_address'], $email_data['contact_id'], $email_data['email_hash'] );
		}
		$email_data['email_index'] = $index;

		if ( $is_update ) {
			$email_id = isset( $email_data['id'] ) ? (int) $email_data['id'] : 0;
			if ( $email_id ) {
				// updateCampaignEmail returns the affected-row count, or false on DB error.
				// A return of 0 means the row matched but no values changed (e.g. status is
				// already correct) — the row still exists, so we must NOT fall through to insert.
				// Only a real DB failure (false) should trigger the insert fallback.
				$updated = $this->repository()->updateCampaignEmail( $email_id, (int) $campaign_id, $email_data );
				if ( false !== $updated ) {
					return $email_id;
				}
			}
		}

		return $this->repository()->insertCampaignEmail( (int) $campaign_id, $email_data );
	}

	/**
	 * Save schedule_date meta for emails with customDate delay option.
	 *
	 * @since 1.20.0
	 *
	 * @param int   $email_id Email ID.
	 * @param array $email    Email data (with delay_option and scheduleDate).
	 */
	private function save_email_schedule_meta( int $email_id, array $email ) {
		$delay_option = isset( $email['delay_option'] ) ? $email['delay_option'] : '';
		if ( 'customDate' === $delay_option ) {
			$schedule_date = isset( $email['scheduleDate'] ) ? $email['scheduleDate'] : '';
			$this->repository()->insertOrUpdateCampaignEmailMeta( $email_id, 'schedule_date', $schedule_date );
		}
	}

	/**
	 * Build the response after a successful campaign save.
	 *
	 * Handles scheduling delegation and hook firing based on campaign
	 * status and type.
	 *
	 * @since 1.20.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 *
	 * @return WP_REST_Response
	 */
	private function build_save_response( $campaign_id ) {
		$data['campaign'] = $this->campaign_data;
		$status           = $data['campaign']['status'] ?? '';
		$type             = $data['campaign']['type'] ?? '';

		// Automation campaigns skip scheduling — they are triggered per-contact.
		if ( ! isset( $data['campaign']['status'], $data['campaign']['type'] ) || CampaignType::AUTOMATION === $type ) {
			return $this->get_success_response( __( 'Campaign has been saved successfully', 'mrm' ), 201, $data );
		}

		$first_email = $this->get_first_scheduling_email( $campaign_id );

		if ( CampaignStatus::ACTIVE === $status ) {
			$this->repository()->scheduleCampaignAction( $campaign_id, $first_email, CampaignStatus::ACTIVE );
			return $this->get_success_response( __( 'Campaign has been started successfully.', 'mrm' ), 201, $data );
		}

		if ( CampaignStatus::SCHEDULE === $status && CampaignType::RECURRING !== $type ) {
			if ( ! empty( $data['campaign']['scheduled_at'] ) ) {
				$this->repository()->scheduleCampaignAction( $campaign_id, $first_email, CampaignStatus::SCHEDULE, $data['campaign']['scheduled_at'] );
			}
			return $this->get_success_response( __( 'Campaign has been scheduled successfully.', 'mrm' ), 201, $data );
		}

		if ( CampaignStatus::SCHEDULE === $status && CampaignType::RECURRING === $type ) {
			if ( ! empty( $data['campaign']['scheduled_at'] ) ) {
				/**
				 * Fires when processing a recurring campaign in Mail Mint.
				 *
				 * @param array $campaign_data Data related to the recurring campaign.
				 *
				 * @hook mailmint_process_recurring_campaign
				 * @since 1.6.0
				 */
				do_action( 'mailmint_process_recurring_campaign', $data['campaign'] );
			}
			return $this->get_success_response( __( 'Campaign has been scheduled successfully.', 'mrm' ), 201, $data );
		}

		return $this->get_success_response( __( 'Campaign has been saved successfully', 'mrm' ), 201, $data );
	}

	/**
	 * Get the first campaign email with 'scheduling' status, stripped to lightweight fields.
	 *
	 * Used by the Action Scheduler to avoid exceeding the 8000 character JSON limit.
	 *
	 * @since 1.20.0
	 *
	 * @param int|string $campaign_id Campaign ID.
	 *
	 * @return array|null Lightweight email array or null.
	 */
	private function get_first_scheduling_email( $campaign_id ) {
		$campaign_emails = $this->repository()->getCampaignEmails( $campaign_id );
		$first_email     = current(
			array_filter(
				$campaign_emails,
				function ( $email ) {
					return isset( $email['status'] ) && 'scheduling' === $email['status'];
				}
			)
		) ?: null;

		if ( $first_email ) {
			$first_email = array_intersect_key(
				$first_email,
				array_flip( array( 'id', 'email_subject', 'email_preview_text', 'sender_email', 'sender_name', 'reply_email', 'reply_name', 'delay_count', 'delay_value' ) )
			);
		}

		return $first_email;
	}



	/**
	 * Delete a single campaign with cascade deletion, action unscheduling,
	 * and pre-processed email cleanup.
	 *
	 * @since 1.20.0
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response|array|WP_Error
	 */
	public function delete_single( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : '';

		$success = $this->repository()->destroy( $campaign_id );

		if ( $success ) {
			$this->repository()->unscheduleCampaignActions( $campaign_id );

			/**
			 * Fires after a campaign has been deleted.
			 *
			 * @since 1.20.0
			 *
			 * @param int|string $campaign_id The deleted campaign ID.
			 */
			do_action( 'mailmint_campaigns_deleted', $campaign_id );

			return $this->get_success_response( __( 'Campaign has been deleted successfully', 'mrm' ), 200 );
		}

		return $this->get_error_response( __( 'Failed to Delete', 'mrm' ), 400 );
	}


	/**
	 * Request for deleting a email from a campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array|WP_Error
	 * @since 1.0.0
	 */
	public function delete_campaign_email( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$campaign_id = isset( $params['campaign_id'] ) ? (int) $params['campaign_id'] : 0;
		$email_id    = isset( $params['email_id'] ) ? (int) $params['email_id'] : 0;

		$success = $this->repository()->deleteCampaignEmail( $campaign_id, $email_id );
		if ( $success ) {
			return $this->get_success_response( __( 'Campaign email has been deleted successfully', 'mrm' ), 200 );
		}
		return $this->get_error_response( __( 'Failed to Delete', 'mrm' ), 400 );
	}

	/**
	 * Request for deleting multiple campaigns.
	 *
	 * Uses CampaignRepository for cascade deletion and unscheduling.
	 * Fires mailmint_campaigns_deleted hook per deleted campaign.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @since 1.20.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$campaign_ids = isset( $params['campaign_ids'] ) ? $params['campaign_ids'] : array();

		if ( empty( $campaign_ids ) ) {
			return $this->get_error_response( __( 'No campaigns selected', 'mrm' ), 400 );
		}

		// Unschedule actions for each campaign before deletion.
		foreach ( $campaign_ids as $campaign_id ) {
			$this->repository()->unscheduleCampaignActions( $campaign_id );
		}

		$success = $this->repository()->destroyMany( $campaign_ids );

		if ( $success && ! is_wp_error( $success ) ) {
			// Fire delete hook per campaign for Pro plugin compatibility.
			foreach ( $campaign_ids as $campaign_id ) {
				/**
				 * Fires after a campaign has been deleted.
				 *
				 * @since 1.20.0
				 *
				 * @param int|string $campaign_id The deleted campaign ID.
				 */
				do_action( 'mailmint_campaigns_deleted', $campaign_id );
			}

			return $this->get_success_response( __( 'Campaign has been deleted successfully', 'mrm' ), 200 );
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}

	/**
	 * Get all campaigns with stats enrichment.
	 *
	 * @since 1.20.0
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all( WP_REST_Request $request ) {
		global $wpdb;

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['per-page'] ) ) {
			$params['per_page'] = $params['per-page'];
		}
		if ( isset( $params['order-by'] ) ) {
			$params['order_by'] = $params['order-by'];
		}
		if ( isset( $params['order-type'] ) ) {
			$params['order'] = $params['order-type'];
		}

		$result = $this->repository()->list( $params );
		$campaigns = array(
			'campaigns'    => $result['data'],
			'count'        => $result['total'],
			'total_pages'  => $result['total_pages'],
			'current_page' => $result['page'],
			'groups'       => $result['groups'],
		);

		if ( isset( $campaigns['campaigns'] ) && ! empty( $campaigns['campaigns'] ) ) {
			$id_groups = $this->categorize_campaign_ids( $campaigns['campaigns'] );
			$stats     = $this->batch_load_campaign_stats( $wpdb, $id_groups );

			$campaigns['campaigns'] = array_map(
				function( $campaign ) use ( $stats ) {
					return $this->enrich_campaign_with_stats( $campaign, $stats );
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
	 * Function use to get campaigns by name search
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array|WP_REST_Response|WP_Error
	 * @since 1.18.0
	 */
	public function get_campaign_by_name(WP_REST_Request $request) {
		global $wpdb;
		$params = MrmCommon::get_api_params_values($request);
		$term   = isset($params['term']) ? $params['term'] : '';
		$table  = $wpdb->prefix . CampaignSchema::$campaign_table;

		// Prepare the search string with wildcards for a LIKE query.
		$search = '%' . $wpdb->esc_like($term) . '%';

		// Query to fetch id as value and name as label.
		$query = $wpdb->prepare("SELECT id AS value, title AS label
			FROM {$table}
			WHERE title LIKE %s
		", $search);

		// Execute the query and return the results
		$campaigns = $wpdb->get_results($query, ARRAY_A);
		$response['success']     = true;
		$response['campaigns'] = $campaigns;
		return rest_ensure_response( $response );
	}

	/**
	 * Get campaigns for segment builder — lightweight, paginated, grouped by type.
	 * Returns only id, title, type. Supports search and scroll pagination.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.19.6
	 */
	public function get_campaigns_for_segment( WP_REST_Request $request ) {
		global $wpdb;

		$params   = MrmCommon::get_api_params_values( $request );
		$page     = max( 1, intval( isset( $params['page'] ) ? $params['page'] : 1 ) );
		$per_page = max( 1, intval( isset( $params['per_page'] ) ? $params['per_page'] : 10 ) );
		$search   = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$offset   = ( $page - 1 ) * $per_page;

		$table = $wpdb->prefix . CampaignSchema::$campaign_table;

		$where = '';
		if ( ! empty( $search ) ) {
			$where = $wpdb->prepare( 'WHERE title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		// Fetch only id, title, type — no stats, no meta
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, type FROM {$table} {$where} ORDER BY type ASC, id DESC LIMIT %d, %d",
				$offset,
				$per_page
			),
			ARRAY_A
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );

		// Group by type
		$type_labels = array(
			'regular'   => __( 'Regular Campaign', 'mrm' ),
			'recurring' => __( 'Recurring Campaign', 'mrm' ),
			'sequence'  => __( 'Campaign Sequence', 'mrm' ),
			'automation' => __( 'Automation Sequence', 'mrm' ),
		);

		$grouped = array();
		foreach ( $results as $row ) {
			$type  = isset( $row['type'] ) ? $row['type'] : 'regular';
			$label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : ucfirst( $type );
			if ( ! isset( $grouped[ $type ] ) ) {
				$grouped[ $type ] = array(
					'label'   => $label,
					'options' => array(),
				);
			}
			$grouped[ $type ]['options'][] = array(
				'value' => (int) $row['id'],
				'label' => $row['title'],
			);
		}

		$response = array(
			'success'    => true,
			'groups'     => array_values( $grouped ),
			'total'      => $total,
			'page'       => $page,
			'per_page'   => $per_page,
			'has_more'   => ( $offset + $per_page ) < $total,
		);

		return rest_ensure_response( $response );
	}
	
	
	/**
	 * Function use to get single campaign
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return array|WP_Error
	 * @since 1.0.0
	 * @since 1.20.0 Overridden to use CampaignRepository instead of CampaignModel.
	 */
	public function get_single( WP_REST_Request $request ) {
		// Get values from REST API JSON.
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = ! empty( $params['campaign_id'] ) ? (int) $params['campaign_id'] : 0;
		$campaign    = $this->repository()->find( $campaign_id );

		if ( empty( $campaign ) ) {
			return $this->get_error_response( 'Failed to retrieve the campaign.', 400 );
		}

		$campaign['emails'] = $this->repository()->getCampaignEmails( $campaign_id );
		$campaign           = $this->enrich_single_campaign_stats( $campaign_id, $campaign );
		$campaign           = $this->format_campaign_schedule( $campaign );
		$campaign           = $this->load_and_filter_recipients( $campaign_id, $campaign );
		$campaign           = $this->load_recurring_data( $campaign_id, $campaign );
		$campaign           = $this->load_utm_params( $campaign_id, $campaign );
		$campaign           = $this->load_archive_setting( $campaign_id, $campaign );

		$created_by_id                  = ! empty( $campaign['created_by'] ) ? (int) $campaign['created_by'] : 0;
		$created_by_user                = $created_by_id ? get_userdata( $created_by_id ) : false;
		$campaign['created_by_name']    = $created_by_user ? $created_by_user->display_name : '';
		$campaign['created_by_email']   = $created_by_user ? $created_by_user->user_email : '';

		return $this->get_success_response( 'Campaign has been retrieved successfully.', 200, $campaign );
	}

	/**
	 * Enrich a single campaign with broadcast/email stats when not in draft.
	 *
	 * @since 1.20.0
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $campaign    Campaign data.
	 *
	 * @return array Campaign data with stats fields added.
	 */
	private function enrich_single_campaign_stats( int $campaign_id, array $campaign ): array {
		if ( empty( $campaign['status'] ) || CampaignStatus::DRAFT === $campaign['status'] ) {
			return $campaign;
		}

		$broadcast_repo = new \Mint\MRM\Database\Repositories\BroadcastRepository();

		if ( CampaignType::AUTOMATION === $campaign['type'] ) {
			$campaign = $this->load_automation_sequence_stats( $broadcast_repo, $campaign_id, $campaign );
		} else {
			$campaign = $this->load_broadcast_campaign_stats( $broadcast_repo, $campaign_id, $campaign );
		}

		$campaign['total_recipients'] = $this->repository()->getMeta( $campaign_id, 'total_recipients' );
		$campaign['unsubscribed_rate'] = $this->calculate_unsubscribe_rate( $campaign );

		// Recurring campaigns also expose a cross-occurrence rollup with rates that
		// divide by SUM(per-run delivered) — the same scope the opens/clicks
		// accumulate in — so campaign-level KPIs can't exceed 100% the way the
		// opens / DISTINCT-recipients math does. The legacy occurrence contributes
		// its blended numbers as one bucket, keeping the totals correct.
		if ( CampaignType::RECURRING === $campaign['type'] ) {
			$rollup = $broadcast_repo->getRecurringCampaignRollup( $campaign_id );

			$rollup['open_rate']  = $rollup['delivered'] > 0 ? round( ( $rollup['opens'] / $rollup['delivered'] ) * 100, 1 ) : 0;
			$rollup['click_rate'] = $rollup['delivered'] > 0 ? round( ( $rollup['clicks'] / $rollup['delivered'] ) * 100, 1 ) : 0;

			$campaign['recurring_rollup'] = $rollup;
		}

		return $campaign;
	}

	/**
	 * Load stats for automation-type campaigns via the automation sequence join path.
	 *
	 * @since 1.20.0
	 *
	 * @param \Mint\MRM\Database\Repositories\BroadcastRepository $broadcast_repo Broadcast repository.
	 * @param int                                                  $campaign_id    Campaign ID.
	 * @param array                                                $campaign       Campaign data.
	 *
	 * @return array Campaign data with automation stats.
	 */
	private function load_automation_sequence_stats( $broadcast_repo, int $campaign_id, array $campaign ): array {
		$total_delivered = $broadcast_repo->countDeliveredStatusOnAutomationSequence( $campaign_id, 'sent' );

		if ( 0 === (int) $total_delivered ) {
			$campaign['total_open']        = 0;
			$campaign['total_click']       = 0;
			$campaign['total_unsubscribe'] = 0;
		} else {
			$campaign['total_open']        = $broadcast_repo->countEmailMetricsOnAutomationSequence( $campaign_id, 'is_open' );
			$campaign['total_click']       = $broadcast_repo->countEmailMetricsOnAutomationSequence( $campaign_id, 'is_click' );
			$campaign['total_unsubscribe'] = $broadcast_repo->countEmailMetricsOnAutomationSequence( $campaign_id, 'is_unsubscribe' );
		}

		$campaign['total_bounced'] = $broadcast_repo->countDeliveredStatusOnAutomationSequence( $campaign_id, 'failed' );

		return $campaign;
	}

	/**
	 * Load stats for regular/sequence/recurring campaigns via the broadcast table.
	 *
	 * @since 1.20.0
	 *
	 * @param \Mint\MRM\Database\Repositories\BroadcastRepository $broadcast_repo Broadcast repository.
	 * @param int                                                  $campaign_id    Campaign ID.
	 * @param array                                                $campaign       Campaign data.
	 *
	 * @return array Campaign data with broadcast stats.
	 */
	private function load_broadcast_campaign_stats( $broadcast_repo, int $campaign_id, array $campaign ): array {
		$total_delivered = $broadcast_repo->countDeliveredStatusOnCampaign( $campaign_id, 'sent' );

		if ( 0 === (int) $total_delivered ) {
			$campaign['total_open']        = 0;
			$campaign['total_click']       = 0;
			$campaign['total_unsubscribe'] = 0;
		} else {
			$campaign['total_open']        = $broadcast_repo->calculateOpenRateOnCampaign( $campaign_id );
			$campaign['total_click']       = $broadcast_repo->calculateClickRateOnCampaign( $campaign_id );
			$campaign['total_unsubscribe'] = $broadcast_repo->countUnsubscribeOnCampaign( $campaign_id );
		}

		$campaign['total_bounced'] = $broadcast_repo->countDeliveredStatusOnCampaign( $campaign_id, 'failed' );

		return $campaign;
	}

	/**
	 * Calculate unsubscribe rate as a percentage of total recipients.
	 *
	 * @since 1.20.0
	 *
	 * @param array $campaign Campaign data with total_unsubscribe and total_recipients.
	 *
	 * @return float Unsubscribe rate percentage.
	 */
	private function calculate_unsubscribe_rate( array $campaign ): float {
		if ( empty( $campaign['total_recipients'] ) || 0 === (int) $campaign['total_recipients'] ) {
			return 0;
		}
		return ( (int) $campaign['total_unsubscribe'] / (int) $campaign['total_recipients'] ) * 100;
	}

	/**
	 * Format the scheduled_at field for display using WP date/time settings.
	 *
	 * @since 1.20.0
	 *
	 * @param array $campaign Campaign data.
	 *
	 * @return array Campaign data with formatted scheduled_at.
	 */
	private function format_campaign_schedule( array $campaign ): array {
		$time                     = new \DateTimeImmutable( isset( $campaign['scheduled_at'] ) ? $campaign['scheduled_at'] : '', wp_timezone() );
		$date_format              = get_option( 'date_format' );
		$time_format              = get_option( 'time_format' );
		$campaign['scheduled_at'] = sprintf( esc_html__( 'Schedule at %s', 'mrm' ), $time->format( $date_format . ' ' . $time_format ) );

		return $campaign;
	}

	/**
	 * Load, deserialize, filter, and persist campaign recipients meta.
	 *
	 * Verifies that saved recipient group IDs still exist and prunes stale ones.
	 *
	 * @since 1.20.0
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $campaign    Campaign data.
	 *
	 * @return array Campaign data with meta.recipients populated.
	 */
	private function load_and_filter_recipients( int $campaign_id, array $campaign ): array {
		$raw_recipients                 = $this->repository()->getMeta( $campaign_id, 'recipients' );
		$campaign['meta']['recipients'] = is_array( $raw_recipients ) ? $raw_recipients : maybe_unserialize( maybe_unserialize( $raw_recipients ) );

		if ( ! is_array( $campaign['meta']['recipients'] ) ) {
			$campaign['meta']['recipients'] = array( 'lists' => array(), 'tags' => array(), 'segments' => array() );
		}

		$campaign['meta']['recipients'] = MrmCommon::filter_recipients( $campaign['meta']['recipients'], $campaign['status'] );

		return $campaign;
	}

	/**
	 * Load recurring campaign properties and override scheduled_at with a human-readable sentence.
	 *
	 * No-op for non-recurring campaigns.
	 *
	 * @since 1.20.0
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $campaign    Campaign data.
	 *
	 * @return array Campaign data with recurringData and updated scheduled_at for recurring types.
	 */
	private function load_recurring_data( int $campaign_id, array $campaign ): array {
		if ( CampaignType::RECURRING !== $campaign['type'] ) {
			return $campaign;
		}

		$recurring_properties = $this->repository()->getMeta( $campaign_id, 'recurring_properties' );
		$recurring_properties = ! empty( $recurring_properties ) ? maybe_unserialize( $recurring_properties ) : array();

		$campaign['scheduled_at']  = Campaign::prepare_recurring_schedule_sentence( $recurring_properties );
		$campaign['recurringData'] = $recurring_properties;

		return $campaign;
	}

	/**
	 * Load UTM params from campaign meta into the campaign array.
	 *
	 * @since 1.21.0
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $campaign    Campaign data.
	 *
	 * @return array Campaign data with meta.utm populated.
	 */
	private function load_utm_params( int $campaign_id, array $campaign ): array {
		$raw                       = $this->repository()->getMeta( $campaign_id, 'utm_params' );
		$campaign['meta']['utm']   = $raw ? json_decode( $raw, true ) : array();
		return $campaign;
	}

	/**
	 * Load the "show in archive" setting from campaign meta into the campaign array.
	 *
	 * @since 1.24.0
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $campaign    Campaign data.
	 *
	 * @return array Campaign data with meta.show_in_archive populated as a boolean.
	 */
	private function load_archive_setting( int $campaign_id, array $campaign ): array {
		$campaign['meta']['show_in_archive']  = '1' === $this->repository()->getMeta( $campaign_id, 'show_in_archive' );
		$campaign['meta']['archive_url']      = $this->repository()->getArchiveUrl( $campaign_id );
		$share_visibility                     = $this->repository()->getMeta( $campaign_id, 'share_visibility' );
		$campaign['meta']['share_visibility'] = $share_visibility ?: 'private';
		return $campaign;
	}

	// =========================================================================
	// Legacy / Action Scheduler Operations
	// =========================================================================

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

		// Cancel any queued Action Scheduler jobs whenever a campaign leaves an
		// active/scheduled state. For recurring campaigns this also clears the
		// recurring schedule group, so "End campaign" (archived) and Pause
		// actually stop future runs. unschedule_campaign_actions() is idempotent.
		$unschedule_statuses = array(
			CampaignStatus::SUSPENDED,
			CampaignStatus::DRAFT,
			CampaignStatus::ARCHIVED,
			CampaignStatus::PAUSED,
		);
		if ( in_array( $status, $unschedule_statuses, true ) ) {
			ModelsCampaign::unschedule_campaign_actions( $campaign_id );
		}

		if ( $update ) {
			return $this->get_success_response( __( 'Campaign status has been updated successfully', 'mrm' ), 201 );
		}
		return $this->get_error_response( __( 'Failed to update campaign status', 'mrm' ), 400 );
	}

	/**
	 * Get the resolved next run time for a recurring campaign.
	 *
	 * Replaces the client-side next-run derivation with the authoritative time
	 * from the Action Scheduler recurring group.
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 *
	 * @return array|WP_REST_Response|\WP_Error
	 * @since 1.24.0
	 */
	public function get_recurring_next_run( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = ! empty( $params['campaign_id'] ) ? (int) $params['campaign_id'] : 0;

		$next_ts = $this->repository()->getRecurringNextRunTimestamp( $campaign_id );

		$data = array(
			'has_next_run' => null !== $next_ts,
			'next_run_ts'  => $next_ts,
			'next_run_gmt' => $next_ts ? gmdate( 'Y-m-d H:i:s', $next_ts ) : null,
			'timezone'     => wp_timezone_string(),
		);

		return $this->get_success_response( __( 'Next run resolved.', 'mrm' ), 200, $data );
	}

	/**
	 * Get the per-occurrence run history for a recurring campaign.
	 *
	 * Returns one entry per occurrence (cloned email step) with its own
	 * delivered/open/click stats and server-computed rates. Pre-upgrade rows that
	 * carry no occurrence meta are folded into a single "legacy" entry.
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 *
	 * @return array|WP_REST_Response|\WP_Error
	 * @since 1.24.0
	 */
	public function get_recurring_runs( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = ! empty( $params['campaign_id'] ) ? (int) $params['campaign_id'] : 0;
		$page        = max( 1, intval( isset( $params['page'] ) ? $params['page'] : 1 ) );
		$per_page    = max( 1, intval( isset( $params['per_page'] ) ? $params['per_page'] : 5 ) );
		$offset      = ( $page - 1 ) * $per_page;

		$broadcast_repo = new \Mint\MRM\Database\Repositories\BroadcastRepository();

		// Real (meta-stamped) occurrences are paged on their own. The legacy bucket
		// is a SINGLE extra entry that always renders last, so it is counted as one
		// row on top of the real total and never paged through inline — otherwise a
		// "before per-run reporting" aggregate could sort into the middle of the
		// list by date and read as if it were a recent run.
		$legacy      = $broadcast_repo->getRecurringLegacyBucket( $campaign_id );
		$has_legacy  = ! empty( $legacy );
		$total_real  = $broadcast_repo->getRecurringRunHistoryCount( $campaign_id );
		$total_runs  = $total_real + ( $has_legacy ? 1 : 0 );
		$total_pages = (int) ceil( $total_runs / max( 1, $per_page ) );

		$rows = $broadcast_repo->getRecurringRunHistory( $campaign_id, $per_page, $offset );

		$runs = array();
		foreach ( $rows as $row ) {
			$runs[] = $this->format_recurring_run( $row, false );
		}

		// Append the legacy bucket only on the last page, so it is pinned to the
		// very bottom of the full list. The slot is already accounted for in
		// $total_runs, so the last page always has room for it within $per_page.
		if ( $has_legacy && $page >= max( 1, $total_pages ) ) {
			$runs[] = $this->format_recurring_run( $legacy, true );
		}

		return $this->get_success_response(
			__( 'Recurring run history retrieved.', 'mrm' ),
			200,
			array(
				'runs'        => $runs,
				'total_runs'  => $total_runs,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => $total_pages,
				'has_more'    => $page < $total_pages,
			)
		);
	}

	/**
	 * Shape one recurring run-history row for the API response.
	 *
	 * Both real occurrences and the collapsed legacy ("Earlier runs") bucket
	 * arrive as an aggregate row carrying recipients/delivered counts. The
	 * email-step status is NOT a reliable source — Pro stamps each cloned run as
	 * SCHEDULED and never flips it — so the run's real status is derived from its
	 * broadcast progress here. Subject and occurrence meta come pre-joined on the
	 * row (no per-row lookups). Per-run open/click stats live in the analytics
	 * report, not this list.
	 *
	 * @since 1.24.0
	 *
	 * @param array $row       Aggregate row from BroadcastRepository.
	 * @param bool  $is_legacy Whether this row is the collapsed legacy bucket.
	 * @return array Run entry for the response.
	 */
	private function format_recurring_run( array $row, bool $is_legacy ): array {
		$email_id   = isset( $row['email_id'] ) ? (int) $row['email_id'] : 0;
		$delivered  = isset( $row['delivered'] ) ? (int) $row['delivered'] : 0;
		$recipients = isset( $row['recipients'] ) ? (int) $row['recipients'] : 0;

		if ( $recipients <= 0 ) {
			$status = 'scheduled';
		} elseif ( $delivered >= $recipients ) {
			$status = 'sent';
		} elseif ( $delivered > 0 ) {
			$status = 'sending';
		} else {
			$status = 'scheduled';
		}

		$run_scheduled = isset( $row['run_scheduled_at'] ) ? $row['run_scheduled_at'] : '';

		return array(
			'run_id'       => $email_id,
			'sequence'     => $is_legacy ? 0 : ( isset( $row['run_sequence'] ) ? (int) $row['run_sequence'] : 0 ),
			'is_legacy'    => $is_legacy,
			'subject'      => isset( $row['subject'] ) ? (string) $row['subject'] : '',
			'status'       => $status,
			'scheduled_at' => ! empty( $run_scheduled ) ? $run_scheduled : ( isset( $row['scheduled_at'] ) ? $row['scheduled_at'] : null ),
			'sent_at'      => isset( $row['sent_at'] ) ? $row['sent_at'] : null,
		);
	}

	/**
	 * Get subscriber count by list/tag ids
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 *
	 * @return array
	 */
	/**
	 * Get subscriber count by list/tag/segment ids.
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 *
	 * @return array
	 * @since 1.0.0
	 * @since 1.20.0 Updated to use CampaignRepository::getMeta() and SegmentRepository + SegmentFilterService.
	 */
	public function get_subscribers( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$subscribers = 0;
		$segment_ids = '';

		// Parse exclude lists/tags from query params.
		$exclude_list_ids = ! empty( $params['exclude_lists'] ) ? array_filter( array_map( 'intval', explode( ', ', $params['exclude_lists'] ) ) ) : [];
		$exclude_tag_ids  = ! empty( $params['exclude_tags'] ) ? array_filter( array_map( 'intval', explode( ', ', $params['exclude_tags'] ) ) ) : [];

		// Resolve the excluded contacts to a concrete ID set ONCE. Exclusion is a set
		// difference (audience MINUS these contacts), never a flat count subtraction:
		// subtracting a standalone group size double-removes contacts the audience
		// (e.g. a segment) already excludes, and over-removes when the groups don't
		// overlap. We push these IDs into each audience query as a NOT IN filter so the
		// count reflects only the contacts actually in the audience but not excluded.
		$excluded_contact_ids = array();
		if ( ! empty( $exclude_list_ids ) || ! empty( $exclude_tag_ids ) ) {
			$excluded_rows = ContactGroupPivotModel::get_contacts_to_group( array_merge( $exclude_list_ids, $exclude_tag_ids ), 0, 0, false );
			if ( is_array( $excluded_rows ) ) {
				$excluded_contact_ids = array_filter( array_map( static function ( $row ) {
					return (int) ( is_object( $row ) ? $row->contact_id : $row['contact_id'] );
				}, $excluded_rows ) );
			}
		}

		// When 'all' is passed, return total subscribed contacts count without filtering by list/tag.
		if ( 'all' === ( $params['lists'] ?? '' ) && empty( $params['tags'] ) && empty( $params['segment'] ) ) {
			global $wpdb;
			$contact_table = esc_sql( $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactSchema::$table_name );

			$query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$contact_table}` WHERE status = %s", 'subscribed' ); //phpcs:ignore

			// Exclude contacts who belong to excluded lists/tags (set difference).
			if ( ! empty( $excluded_contact_ids ) ) {
				$query .= ' AND id NOT IN (' . implode( ', ', $excluded_contact_ids ) . ')';
			}

			$subscribers = (int) $wpdb->get_var( $query ); //phpcs:ignore

			return $this->get_success_response( __( 'Subscriber list count successfully fetched.', 'mrm' ), 200, $subscribers );
		}

		if ( ! empty( $params[ 'lists' ] ) || ! empty( $params[ 'tags' ] ) || ! empty( $params[ 'segment' ] ) ) {
			$list_ids    = ! empty( $params[ 'lists' ] ) ? explode( ', ', $params[ 'lists' ] ) : [];
			$tag_ids     = ! empty( $params[ 'tags' ] ) ? explode( ', ', $params[ 'tags' ] ) : [];
			$segment_ids = ! empty( $params[ 'segment' ] ) ? explode( ', ', $params[ 'segment' ] ) : [];
		} elseif ( ! empty( $params[ 'campaign_id' ] ) ) {
			$campaign_id = $params[ 'campaign_id' ];

			$recipients = $this->repository()->getMeta( $campaign_id, 'recipients' );
			$recipients = maybe_unserialize( $recipients );

			$list_ids = array_column( $recipients[ 'lists' ], 'id' );
			$tag_ids  = array_column( $recipients[ 'tags' ], 'id' );

			$segment_ids = array_column( $recipients[ 'segments' ], 'id' );
			$segment_ids = ! empty( $segment_ids ) ? $segment_ids : [];
		}

		if ( ! empty( $segment_ids ) ) {
			// Use SOLID pattern: SegmentRepository + SegmentFilterService (replaces deprecated FilterSegmentContacts).
			if ( class_exists( 'MailMintPro\Mint\Database\Repositories\SegmentRepository' )
				&& class_exists( 'MailMintPro\Mint\Internal\Admin\Segmentation\SegmentFilterService' ) ) {
				$segment_repo   = new \MailMintPro\Mint\Database\Repositories\SegmentRepository();
				$filter_service = new \MailMintPro\Mint\Internal\Admin\Segmentation\SegmentFilterService();

				foreach ( $segment_ids as $segment_id ) {
					$segment = $segment_repo->findWithFilters( (int) $segment_id );

					if ( $segment && ! empty( $segment['filters'] ) ) {
						$where_clause = $filter_service->buildWhereClause( $segment['filters'] );

						if ( $where_clause ) {
							$subscribed_where = '(' . $where_clause . ") AND c1.status = 'subscribed'";

							// Exclude contacts in excluded lists/tags (set difference, not subtraction).
							if ( ! empty( $excluded_contact_ids ) ) {
								$subscribed_where .= ' AND c1.id NOT IN (' . implode( ', ', $excluded_contact_ids ) . ')';
							}

							$count        = $filter_service->getContacts( $subscribed_where, array( 'count_only' => true ) );
							$subscribers += (int) $count;
						}
					}
				}
			}
		} elseif ( ! empty( $list_ids ) || ! empty( $tag_ids ) ) {
			global $wpdb;
			$contact_table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactSchema::$table_name;
			$pivot_table   = $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactGroupPivotSchema::$table_name;
			$group_ids     = implode( ', ', array_map( 'intval', array_merge( $list_ids, $tag_ids ) ) );

			$query = "SELECT COUNT(DISTINCT cgp.contact_id) FROM `{$pivot_table}` AS cgp JOIN `{$contact_table}` AS c ON cgp.contact_id = c.id AND c.status = 'subscribed' WHERE cgp.group_id IN ({$group_ids})";

			// Exclude contacts in excluded lists/tags (set difference, not subtraction).
			if ( ! empty( $excluded_contact_ids ) ) {
				$query .= ' AND cgp.contact_id NOT IN (' . implode( ', ', $excluded_contact_ids ) . ')';
			}

			$subscribers = (int) $wpdb->get_var( $query ); //phpcs:ignore
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
    public function hide_smtp_notice( WP_REST_Request $request ){
        $params      = MrmCommon::get_api_params_values( $request );
        if( !empty( $params['remove_smtp_notice'] ) ){
            $notice = $params['remove_smtp_notice'];
            update_option('mint_notice_update',$notice);
            return $this->get_success_response( __( 'Notice updated successfully', 'mrm' ), 201 );
        }
        return $this->get_error_response( __( 'Failed to update notice status', 'mrm' ), 400 );
    }

	/**
	 * Retrieve URLs from a campaign email.
	 *
	 * This function handles a REST API request to get all URLs from the email body of a specified campaign.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * 
	 * @return WP_REST_Response The response object containing the success status and the list of URLs.
	 * @since 1.17.2
	 */
	public function get_urls_from_campaign( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : '';

		$urls = ModelsCampaign::get_urls_from_campaign_email( $campaign_id );
		$response['success'] = true;
		$response['urls']    = $urls;
		return rest_ensure_response($response);
	}

	/**
	 * Get progress data for a campaign.
	 *
	 * This function retrieves the progress of a campaign, including total recipients, sent count, failed count,
	 * scheduled count, and calculates the current phase and percentage of completion.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.18.10
	 */
	public function get_progress( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = isset( $params['campaign_id'] ) ? intval( $params['campaign_id'] ) : 0;

		if ( empty( $campaign_id ) ) {
			return rest_ensure_response([
				'success' => false,
				'message' => __( 'Campaign ID is required.', 'mrm' ),
				'data'    => [],
			]);
		}

		// Cache total recipients (doesn't change per poll).
		$cache_key        = "mm_campaign_total_recipients_{$campaign_id}";
		$total_recipients = get_transient( $cache_key );
		if ( false === $total_recipients ) {
			$total_recipients = (int) $this->repository()->getMeta( $campaign_id, 'total_recipients' );
			set_transient( $cache_key, $total_recipients, HOUR_IN_SECONDS );
		}

		// Dynamic counts via BroadcastRepository — single query for all statuses.
		$broadcast_repo = new \Mint\MRM\Database\Repositories\BroadcastRepository();
		$counts         = $broadcast_repo->getStatusCounts( $campaign_id );

		$total_broadcast   = $counts['scheduled'] + $counts['sending'] + $counts['sent'] + $counts['failed'];
		$processed_count   = $counts['sent'] + $counts['failed'];
		$in_progress_count = $counts['sending'];

		// Phase detection — never let the displayed count go backward.
		$phase_label    = '';
		$complete_count = 0;
		$percentage     = 0;

		$campaign = $this->repository()->find( $campaign_id );
		$status   = ! empty( $campaign['status'] ) ? $campaign['status'] : '';

		if ( $processed_count >= $total_recipients || CampaignStatus::ARCHIVED === $status ) {
			// Phase 3 — Completed.
			$phase_label     = __( 'Emails sent successfully', 'mrm' );
			$complete_count  = $total_recipients;
			$percentage      = 100;

		} elseif ( $processed_count > 0 || $in_progress_count > 0 ) {
			// Phase 2 — Sending (emails claimed, in-flight, or already sent).
			// Include 'sending' rows so the count never drops when a batch is mid-flight.
			$phase_label     = __( 'Emails are being sent', 'mrm' );
			$complete_count  = $processed_count + $in_progress_count;
			$percentage      = $total_recipients > 0 ? round( ( $complete_count / $total_recipients ) * 100 ) : 0;

		} elseif ( $total_broadcast > 0 ) {
			// Phase 1 — Scheduling (broadcast rows being inserted, nothing claimed yet).
			$phase_label     = __( 'Processing emails for sending', 'mrm' );
			$complete_count  = $total_broadcast;
			// Cap at 50% — scheduling is only half the work, sending is the other half.
			$raw_pct         = $total_recipients > 0 ? ( $total_broadcast / $total_recipients ) * 50 : 0;
			$percentage      = min( round( $raw_pct ), 50 );

		} else {
			// Phase 0 — Queued (nothing scheduled yet).
			$phase_label     = __( 'Preparing campaign', 'mrm' );
			$complete_count  = 0;
			$percentage      = 0;
		}

		$progressData = [
			'label'      => $phase_label,
			'percentage' => $percentage,
			'scheduled'  => $counts['scheduled'] + $counts['sending'],
			'sent'       => $counts['sent'],
			'total'      => $total_recipients,
		];

		return rest_ensure_response([
			'success' => true,
			'message' => __( 'Campaign progress fetched successfully.', 'mrm' ),
			'data'    => $progressData,
		]);
	}

	/**
	 * Categorize campaign IDs by type and status for batch loading.
	 *
	 * @param array $campaigns Array of campaign data arrays.
	 *
	 * @return array {
	 *     @type int[] $all       All campaign IDs.
	 *     @type int[] $broadcast Non-draft regular/sequence/recurring campaign IDs.
	 *     @type int[] $recurring Recurring-only campaign IDs (subset of broadcast).
	 * }
	 *
	 * @since 1.15.0
	 */
	private function categorize_campaign_ids( array $campaigns ) {
		$groups = array(
			'all'       => array(),
			'broadcast' => array(),
			'recurring' => array(),
		);

		foreach ( $campaigns as $campaign ) {
			if ( ! isset( $campaign['id'] ) ) {
				continue;
			}
			$cid     = (int) $campaign['id'];
			$ctype   = isset( $campaign['type'] ) ? $campaign['type'] : CampaignType::REGULAR;
			$cstatus = isset( $campaign['status'] ) ? $campaign['status'] : CampaignStatus::DRAFT;

			$groups['all'][] = $cid;

			if ( CampaignStatus::DRAFT !== $cstatus && CampaignType::AUTOMATION !== $ctype ) {
				$groups['broadcast'][] = $cid;
				if ( CampaignType::RECURRING === $ctype ) {
					$groups['recurring'][] = $cid;
				}
			}
		}

		return $groups;
	}

	/**
	 * Batch-load campaign statistics for all campaign IDs in a single pass.
	 *
	 * Replaces per-campaign N+1 queries with grouped batch queries.
	 * Returns lookup maps keyed by campaign_id.
	 *
	 * @param \wpdb $wpdb      WordPress database instance.
	 * @param array $id_groups Output from categorize_campaign_ids().
	 *
	 * @return array 
	 *
	 * @since 1.15.0
	 */
	private function batch_load_campaign_stats( $wpdb, array $id_groups ) {
		$stats = array(
			'meta'        => array(),
			'status'      => array(),
			'email_count' => array(),
			'open'        => array(),
			'click'       => array(),
			'unsub'       => array(),
			'recurring'   => array(),
		);

		// Batch meta — total_recipients for all campaigns.
		if ( ! empty( $id_groups['all'] ) ) {
			$meta_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;
			$ph         = implode( ', ', array_fill( 0, count( $id_groups['all'] ), '%d' ) );
			$rows       = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT campaign_id, meta_value FROM {$meta_table} WHERE campaign_id IN ({$ph}) AND meta_key = %s",
					array_merge( $id_groups['all'], array( 'total_recipients' ) )
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$stats['meta'][ (int) $row['campaign_id'] ] = $row['meta_value'] ?: 0;
			}
		}

		// All broadcast-specific queries (sent/failed, email count, opens, clicks, unsubs).
		if ( ! empty( $id_groups['broadcast'] ) ) {
			$broadcast_table       = $wpdb->prefix . EmailSchema::$table_name;
			$broadcast_meta_table  = $wpdb->prefix . EmailMetaSchema::$table_name;
			$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
			$ph                    = implode( ', ', array_fill( 0, count( $id_groups['broadcast'] ), '%d' ) );

			// Status counts (sent + failed).
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT campaign_id, status, COUNT(id) as total FROM {$broadcast_table} WHERE campaign_id IN ({$ph}) AND status IN ('sent', 'failed') GROUP BY campaign_id, status",
					$id_groups['broadcast']
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$cid = (int) $row['campaign_id'];
				if ( ! isset( $stats['status'][ $cid ] ) ) {
					$stats['status'][ $cid ] = array( 'sent' => 0, 'failed' => 0 );
				}
				$stats['status'][ $cid ][ $row['status'] ] = (int) $row['total'];
			}

			// Email step count.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT campaign_id, COUNT(id) as cnt FROM {$campaign_emails_table} WHERE campaign_id IN ({$ph}) GROUP BY campaign_id",
					$id_groups['broadcast']
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$stats['email_count'][ (int) $row['campaign_id'] ] = (int) $row['cnt'];
			}

			// Open counts (JOIN instead of subquery).
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT be.campaign_id, COUNT(bem.mint_email_id) as total FROM {$broadcast_meta_table} bem JOIN {$broadcast_table} be ON be.id = bem.mint_email_id WHERE bem.meta_key = 'is_open' AND bem.meta_value = 1 AND be.campaign_id IN ({$ph}) GROUP BY be.campaign_id",
					$id_groups['broadcast']
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$stats['open'][ (int) $row['campaign_id'] ] = (int) $row['total'];
			}

			// Click counts.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT be.campaign_id, COUNT(bem.mint_email_id) as total FROM {$broadcast_meta_table} bem JOIN {$broadcast_table} be ON be.id = bem.mint_email_id WHERE bem.meta_key = 'is_click' AND bem.meta_value = 1 AND be.campaign_id IN ({$ph}) GROUP BY be.campaign_id",
					$id_groups['broadcast']
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$stats['click'][ (int) $row['campaign_id'] ] = (int) $row['total'];
			}

			// Anonymous open/click counts from mint_campaigns_meta (no contact reference).
			$campaign_meta_table = $wpdb->prefix . CampaignSchema::$campaign_meta_table;
			$anon_rows           = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT campaign_id, meta_key, meta_value FROM {$campaign_meta_table} WHERE meta_key IN ('_anon_open_count', '_anon_click_count') AND campaign_id IN ({$ph})",
					$id_groups['broadcast']
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $anon_rows as $row ) {
				$cid = (int) $row['campaign_id'];
				if ( '_anon_open_count' === $row['meta_key'] ) {
					$stats['open'][ $cid ] = ( isset( $stats['open'][ $cid ] ) ? $stats['open'][ $cid ] : 0 ) + (int) $row['meta_value'];
				} elseif ( '_anon_click_count' === $row['meta_key'] ) {
					$stats['click'][ $cid ] = ( isset( $stats['click'][ $cid ] ) ? $stats['click'][ $cid ] : 0 ) + (int) $row['meta_value'];
				}
			}

			// Unsubscribe counts.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT be.campaign_id, COUNT(bem.mint_email_id) as total FROM {$broadcast_meta_table} bem JOIN {$broadcast_table} be ON be.id = bem.mint_email_id WHERE bem.meta_key = 'is_unsubscribe' AND bem.meta_value = 1 AND be.campaign_id IN ({$ph}) GROUP BY be.campaign_id",
					$id_groups['broadcast']
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$stats['unsub'][ (int) $row['campaign_id'] ] = (int) $row['total'];
			}

			// Recurring-specific — total recipients from broadcast table.
			if ( ! empty( $id_groups['recurring'] ) ) {
				$rc_ph = implode( ', ', array_fill( 0, count( $id_groups['recurring'] ), '%d' ) );
				$rows  = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT campaign_id, COUNT(campaign_id) as total_recipients FROM {$broadcast_table} WHERE campaign_id IN ({$rc_ph}) GROUP BY campaign_id",
						$id_groups['recurring']
					),
					ARRAY_A
				);
				foreach ( $rows as $row ) {
					$stats['recurring'][ (int) $row['campaign_id'] ] = (int) $row['total_recipients'];
				}
			}
		}

		return $stats;
	}

	/**
	 * Enrich a single campaign array with pre-loaded batch stats.
	 *
	 * @param array $campaign The campaign data array.
	 * @param array $stats    Lookup maps from batch_load_campaign_stats().
	 *
	 * @return array The enriched campaign data.
	 *
	 * @since 1.15.0
	 */
	/**
	 * Return all lists with their subscribed contact count.
	 *
	 * Endpoint: GET mrm/v1/campaigns/recipient-lists
	 *
	 * @since 1.20.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_recipient_lists_for_campaign( WP_REST_Request $request ): WP_REST_Response {
		$search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$items  = $this->query_recipient_group_type( 'lists', $search );

		return $this->legacy_success_response(
			__( 'Recipient lists fetched successfully.', 'mrm' ),
			array( 'data' => $items )
		);
	}

	/**
	 * Return all tags with their subscribed contact count.
	 *
	 * Endpoint: GET mrm/v1/campaigns/recipient-tags
	 *
	 * @since 1.20.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_recipient_tags_for_campaign( WP_REST_Request $request ): WP_REST_Response {
		$search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$items  = $this->query_recipient_group_type( 'tags', $search );

		return $this->legacy_success_response(
			__( 'Recipient tags fetched successfully.', 'mrm' ),
			array( 'data' => $items )
		);
	}

	/**
	 * Return all segments with their dynamically-computed subscribed contact count.
	 *
	 * Endpoint: GET mrm/v1/campaigns/recipient-segments
	 *
	 * Segments are dynamic filter-based groups (Pro feature). Their contact count is computed
	 * at query-time via SegmentRepository + SegmentFilterService (Pro classes), guarded by
	 * class_exists(). When Pro is inactive, total_subscribed_contacts is 0 for every segment.
	 *
	 * @since 1.20.0
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_recipient_segments_for_campaign( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$search       = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$groups_table = $wpdb->prefix . 'mint_contact_groups';

		if ( ! empty( $search ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$groups_table} WHERE type = %s AND title LIKE %s ORDER BY title ASC",
					'segments',
					'%' . $wpdb->esc_like( $search ) . '%'
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$groups_table} WHERE type = %s ORDER BY title ASC",
					'segments'
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$rows = $rows ?? array();

		$has_pro = class_exists( 'MailMintPro\Mint\Database\Repositories\SegmentRepository' )
			&& class_exists( 'MailMintPro\Mint\Internal\Admin\Segmentation\SegmentFilterService' );

		$items = array();
		foreach ( $rows as $row ) {
			$count = 0;

			if ( $has_pro ) {
				$segment_repo   = new \MailMintPro\Mint\Database\Repositories\SegmentRepository();
				$filter_service = new \MailMintPro\Mint\Internal\Admin\Segmentation\SegmentFilterService();
				$segment        = $segment_repo->findWithFilters( (int) $row['id'] );

				if ( $segment && ! empty( $segment['filters'] ) ) {
					$where_clause = $filter_service->buildWhereClause( $segment['filters'] );
					if ( $where_clause ) {
						$count = (int) $filter_service->getContacts(
							$where_clause . " AND c1.status = 'subscribed'",
							array( 'count_only' => true )
						);
					}
				}
			}

			$items[] = array(
				'id'                        => (int) $row['id'],
				'title'                     => $row['title'],
				'total_subscribed_contacts' => $count,
			);
		}

		return $this->legacy_success_response(
			__( 'Recipient segments fetched successfully.', 'mrm' ),
			array( 'data' => $items )
		);
	}

	/**
	 * Query lists or tags with subscribed contact counts, optionally filtered by title.
	 *
	 * @since 1.20.0
	 *
	 * @param string $type   'lists' or 'tags'.
	 * @param string $search Optional title search string.
	 *
	 * @return array Array of { id, title, total_subscribed_contacts }.
	 */
	private function query_recipient_group_type( string $type, string $search = '' ): array {
		global $wpdb;

		$groups_table   = $wpdb->prefix . 'mint_contact_groups';
		$pivot_table    = $wpdb->prefix . 'mint_contact_group_relationship';
		$contacts_table = $wpdb->prefix . 'mint_contacts';

		if ( ! empty( $search ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.id, g.title,
					COALESCE(COUNT(DISTINCT CASE WHEN c.status = %s THEN r.contact_id END), 0) AS total_subscribed_contacts
					FROM {$groups_table} AS g
					LEFT JOIN {$pivot_table} AS r ON r.group_id = g.id
					LEFT JOIN {$contacts_table} AS c ON c.id = r.contact_id
					WHERE g.type = %s AND g.title LIKE %s
					GROUP BY g.id, g.title
					ORDER BY g.title ASC",
					'subscribed',
					$type,
					'%' . $wpdb->esc_like( $search ) . '%'
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT g.id, g.title,
					COALESCE(COUNT(DISTINCT CASE WHEN c.status = %s THEN r.contact_id END), 0) AS total_subscribed_contacts
					FROM {$groups_table} AS g
					LEFT JOIN {$pivot_table} AS r ON r.group_id = g.id
					LEFT JOIN {$contacts_table} AS c ON c.id = r.contact_id
					WHERE g.type = %s
					GROUP BY g.id, g.title
					ORDER BY g.title ASC",
					'subscribed',
					$type
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return array_map(
			function ( $row ) {
				return array(
					'id'                        => (int) $row['id'],
					'title'                     => $row['title'],
					'total_subscribed_contacts' => (int) $row['total_subscribed_contacts'],
				);
			},
			$rows ?? array()
		);
	}

	private function enrich_campaign_with_stats( array $campaign, array $stats ) {
		if ( ! isset( $campaign['id'] ) ) {
			return $campaign;
		}

		$cid     = (int) $campaign['id'];
		$cstatus = isset( $campaign['status'] ) ? $campaign['status'] : CampaignStatus::DRAFT;
		$ctype   = isset( $campaign['type'] ) ? $campaign['type'] : CampaignType::REGULAR;

		if ( CampaignStatus::DRAFT !== $cstatus && in_array( $ctype, array( CampaignType::REGULAR, CampaignType::SEQUENCE, CampaignType::RECURRING ), true ) ) {
			$campaign['total_recipients'] = isset( $stats['meta'][ $cid ] ) ? $stats['meta'][ $cid ] : 0;

			if ( CampaignType::RECURRING === $ctype && isset( $stats['recurring'][ $cid ] ) ) {
				$campaign['total_recipients'] = $stats['recurring'][ $cid ];
			}

			$total_delivered = isset( $stats['status'][ $cid ]['sent'] ) ? $stats['status'][ $cid ]['sent'] : 0;
			$total_bounced   = isset( $stats['status'][ $cid ]['failed'] ) ? $stats['status'][ $cid ]['failed'] : 0;

			if ( 0 === (int) $total_delivered ) {
				$campaign['open_rate']   = 0.00;
				$campaign['click_rate']  = 0.00;
				$campaign['unsubscribe'] = 0;
			} else {
				$total_recipients = (int) $campaign['total_recipients'];
				$email_count      = isset( $stats['email_count'][ $cid ] ) ? $stats['email_count'][ $cid ] : 1;
				$total_opened     = isset( $stats['open'][ $cid ] ) ? $stats['open'][ $cid ] : 0;
				$total_clicked    = isset( $stats['click'][ $cid ] ) ? $stats['click'][ $cid ] : 0;

				$divisor = ( $total_recipients * $email_count ) - $total_bounced;
				$divisor = 0 === $divisor ? 1 : $divisor;

				$campaign['open_rate']   = number_format( (float) ( $total_opened / $divisor ) * 100, 2, '.', '' );
				$campaign['click_rate']  = number_format( (float) ( $total_clicked / $divisor ) * 100, 2, '.', '' );
				$campaign['unsubscribe'] = isset( $stats['unsub'][ $cid ] ) ? $stats['unsub'][ $cid ] : 0;
			}
		} elseif ( CampaignStatus::DRAFT !== $cstatus && CampaignType::AUTOMATION === $ctype ) {
			$campaign['automation_stats'] = AutomationLogModel::prepare_automation_statistics_for_campaign( $cid );
		} else {
			$campaign['total_recipients'] = isset( $stats['meta'][ $cid ] ) ? $stats['meta'][ $cid ] : 0;
		}

		$campaign['scheduled_at'] = MrmCommon::format_campaign_date_time( 'scheduled_at', $campaign );
		$campaign['updated_at']   = MrmCommon::format_campaign_date_time( 'updated_at', $campaign );

		// Recurring campaigns show a schedule description, next-run time, and broadcast
		// count in their dedicated list columns instead of recipient/open/click stats.
		if ( CampaignType::RECURRING === $ctype ) {
			$campaign = $this->enrich_recurring_list_meta( $cid, $campaign );
		}

		return $campaign;
	}

	/**
	 * Attach recurring-specific list fields used by the recurring campaign list columns.
	 *
	 * Adds:
	 *  - recurring_description: human-readable cadence (e.g. "Broadcasts daily at 00:15").
	 *  - next_schedule:         site-local timestamp of the next scheduled run, or '' if none.
	 *  - broadcasts_count:      number of occurrences already sent.
	 *
	 * Only invoked for recurring campaigns, so other campaign type lists are unaffected.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $campaign    Campaign data being enriched.
	 * @return array Campaign data with recurring list fields.
	 * @since 1.24.0
	 */
	private function enrich_recurring_list_meta( int $campaign_id, array $campaign ): array {
		$recurring_properties = $this->repository()->getMeta( $campaign_id, 'recurring_properties' );
		$recurring_properties = ! empty( $recurring_properties ) ? maybe_unserialize( $recurring_properties ) : array();

		$campaign['recurring_description'] = Campaign::prepare_recurring_broadcast_sentence( $recurring_properties );
		$campaign['broadcasts_count']      = $this->repository()->getRecurringRunCount( $campaign_id );

		$next_ts                   = $this->repository()->getRecurringNextRunTimestamp( $campaign_id );
		$campaign['next_schedule'] = $next_ts ? wp_date( 'Y-m-d H:i:s', $next_ts ) : '';

		return $campaign;
	}

	/**
	 * Get URLs from a campaign email for the Campaign Actions feature.
	 *
	 * Returns all URLs found in the campaign email body by default.
	 * Pro overrides the 'mint_campaign_clicked_links' filter to return only
	 * URLs that contacts actually clicked.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.24.0
	 */
	public function get_clicked_links( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = isset( $params['campaign_id'] ) ? intval( $params['campaign_id'] ) : 0;

		if ( empty( $campaign_id ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => __( 'Invalid campaign ID.', 'mrm' ) ) );
		}

		$links = ModelsCampaign::get_urls_from_campaign_email( $campaign_id );

		/**
		 * Filters the list of URLs shown in the Campaign Actions "clicked links" selector.
		 *
		 * By default returns all URLs found in the email body. Pro overrides this to
		 * return only URLs that contacts actually clicked.
		 *
		 * @param array $links       Array of link objects: [ [ 'value' => url, 'label' => url ], … ].
		 * @param int   $campaign_id Campaign ID.
		 * @since 1.24.0
		 */
		$links = apply_filters( 'mint_campaign_clicked_links', $links, $campaign_id );

		return rest_ensure_response( array( 'success' => true, 'links' => array_values( $links ) ) );
	}

	/**
	 * Apply campaign actions (add/remove tags or lists) to contacts filtered by email interaction.
	 *
	 * The actual processing is delegated to Pro via the 'mint_campaign_do_actions' filter.
	 * Returns an error without Pro.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.24.0
	 */
	public function do_campaign_actions( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$campaign_id = isset( $params['campaign_id'] ) ? intval( $params['campaign_id'] ) : 0;

		if ( empty( $campaign_id ) ) {
			return rest_ensure_response( array( 'success' => false, 'message' => __( 'Invalid campaign ID.', 'mrm' ) ) );
		}

		if ( ! MrmCommon::is_mailmint_pro_active() ) {
			return rest_ensure_response( array(
				'success' => false,
				'code'    => 'pro_required',
				'message' => __( 'Campaign Actions requires Mail Mint Pro.', 'mrm' ),
			) );
		}

		/**
		 * Filters the result of applying campaign actions (add/remove tags or lists to filtered contacts).
		 *
		 * Pro hooks into this filter to perform tag/list operations and returns pagination/status data.
		 *
		 * @param array|null $result      Null by default; Pro returns status array.
		 * @param int        $campaign_id Campaign ID.
		 * @param array      $params      Request params: action_type, tags, lists, filter_type, page.
		 * @since 1.24.0
		 */
		$result = apply_filters( 'mint_campaign_do_actions', null, $campaign_id, $params );

		if ( null === $result ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'Campaign Actions could not be processed.', 'mrm' ),
			) );
		}

		return rest_ensure_response( array_merge( array( 'success' => true ), $result ) );
	}
}