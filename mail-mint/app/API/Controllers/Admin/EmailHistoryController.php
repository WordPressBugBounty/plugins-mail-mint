<?php
/**
 * REST API Email History Controller
 *
 * Handles requests to the email history endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.20.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\MRM\DataBase\Tables\AutomationStepSchema;
use Mint\MRM\DataBase\Tables\CampaignEmailBuilderSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;
use Mint\MRM\DataBase\Tables\EmailMetaSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;
use Mint\MRM\DataBase\Tables\ContactMetaSchema;
use MailMint\App\Helper;
use Mint\MRM\DataBase\Models\CampaignEmailBuilderModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\Internal\Campaign\EmailPersonalizer;
use Mint\MRM\Internal\Parser\Parser;
use MintMailPro\Mint_Pro_Helper;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Email History controller - lists all broadcast emails with related data.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since   1.20.0
 */
class EmailHistoryController extends AdminBaseController {

	/**
	 * Retrieve a paginated, searchable, filterable list of broadcast emails.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.20.0
	 */
	public function get_all( WP_REST_Request $request ) {
		global $wpdb;

		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = max( 1, (int) $request->get_param( 'per-page' ) ?: 10 );
		$search   = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
		$status   = sanitize_text_field( $request->get_param( 'status' ) ?: '' );
		$type     = sanitize_text_field( $request->get_param( 'type' ) ?: '' );
		$offset   = ( $page - 1 ) * $per_page;

		$broadcast_table  = $wpdb->prefix . EmailSchema::$table_name;
		$contact_table    = $wpdb->prefix . ContactSchema::$table_name;
		$contact_meta     = $wpdb->prefix . ContactMetaSchema::$table_name;
		$campaign_table   = $wpdb->prefix . CampaignSchema::$campaign_table;
		$campaign_emails  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$automation_steps = $wpdb->prefix . AutomationStepSchema::$table_name;

		$where_clauses = array( '1=1' );
		$params        = array();

		if ( ! empty( $search ) ) {
			$where_clauses[] = '( be.email_address LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR ce.email_subject LIKE %s )';
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		if ( ! empty( $status ) ) {
			$where_clauses[] = 'be.status = %s';
			$params[]        = $status;
		}

		if ( ! empty( $type ) ) {
			$where_clauses[] = 'be.email_type = %s';
			$params[]        = $type;
		}

		$where = implode( ' AND ', $where_clauses );

		// Count query.
		$count_sql = "
			SELECT COUNT(*)
			FROM {$broadcast_table} be
			LEFT JOIN {$contact_table} c ON c.id = be.contact_id
			LEFT JOIN {$campaign_emails} ce ON ce.id = be.email_id
			WHERE {$where}
		"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Data query — join meta for avatar_url.
		$data_sql = "
			SELECT
				be.id,
				be.contact_id,
				be.email_address,
				be.email_type,
				be.status,
				be.campaign_id,
				be.automation_id,
				be.step_id,
				be.email_id,
				be.scheduled_at,
				be.created_at,
				c.first_name,
				c.last_name,
				c.status AS contact_status,
				ce.email_subject AS campaign_subject,
				camp.title AS campaign_title,
				cm.meta_value AS avatar_url
			FROM {$broadcast_table} be
			LEFT JOIN {$contact_table} c ON c.id = be.contact_id
			LEFT JOIN {$campaign_emails} ce ON ce.id = be.email_id
			LEFT JOIN {$campaign_table} camp ON camp.id = be.campaign_id
			LEFT JOIN {$contact_meta} cm ON cm.contact_id = be.contact_id AND cm.meta_key = 'avatar_url'
			WHERE {$where}
			ORDER BY be.created_at DESC
			LIMIT %d OFFSET %d
		"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $params ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$count     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
			$data_rows = $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, array( $per_page, $offset ) ) ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
			$count     = (int) $wpdb->get_var( $count_sql );
			$data_rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		}

		$total_pages = $per_page > 0 ? (int) ceil( $count / $per_page ) : 0;
		$items       = array();

		foreach ( $data_rows as $row ) {
			$subject = $row->campaign_subject;

			// Automation emails: fetch subject from step settings JSON.
			if ( 'automation' === $row->email_type && ! empty( $row->step_id ) && empty( $subject ) ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$step = $wpdb->get_row(
					$wpdb->prepare( "SELECT settings FROM {$automation_steps} WHERE step_id = %s LIMIT 1", $row->step_id )
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $step && ! empty( $step->settings ) ) {
					$settings = maybe_unserialize( $step->settings );
					if ( is_string( $settings ) ) {
						$settings = json_decode( $settings, true );
					}
					$subject = isset( $settings['message_data']['subject'] ) ? $settings['message_data']['subject'] : '';
				}
			}

			// Regular/profile emails: fetch subject from campaign_emails via email_id.
			if ( empty( $subject ) && ! empty( $row->email_id ) ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ce_row = $wpdb->get_row(
					$wpdb->prepare( "SELECT email_subject FROM {$campaign_emails} WHERE id = %d LIMIT 1", $row->email_id )
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $ce_row ) {
					$subject = $ce_row->email_subject;
				}
			}

			// Resolve automation name when not available via campaign join.
			$source_name = $row->campaign_title;
			if ( 'automation' === $row->email_type && ! empty( $row->automation_id ) && empty( $source_name ) ) {
				$automation_table = $wpdb->prefix . 'mint_automations';
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$auto = $wpdb->get_row(
					$wpdb->prepare( "SELECT name FROM {$automation_table} WHERE id = %d LIMIT 1", $row->automation_id )
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $auto ) {
					$source_name = $auto->name;
				}
			}

			$avatar_url = ! empty( $row->avatar_url )
				? $row->avatar_url
				: 'https://www.gravatar.com/avatar/' . md5( strtolower( trim( $row->email_address ) ) ) . '?d=mp&s=40';

			$items[] = array(
				'id'             => (int) $row->id,
				'contact_id'     => $row->contact_id ? (int) $row->contact_id : null,
				'email_address'  => $row->email_address,
				'first_name'     => $row->first_name ?: '',
				'last_name'      => $row->last_name ?: '',
				'avatar_url'     => $avatar_url,
				'subject'        => $subject ?: __( '(No Subject)', 'mrm' ),
				'source'         => ( empty( $row->email_type ) || 'regular' === $row->email_type ) ? __( 'Admin', 'mrm' ) : ( $source_name ?: '' ),
				'type'           => ( empty( $row->email_type ) || 'regular' === $row->email_type ) ? 'Direct Message' : $row->email_type,
				'status'         => $row->status,
				'contact_status' => $row->contact_status ?: '',
				'sending_time'   => $row->created_at,
				'campaign_id'    => $row->campaign_id ? (int) $row->campaign_id : null,
				'automation_id'  => $row->automation_id ? (int) $row->automation_id : null,
			);
		}

		return $this->get_success_response(
			__( 'Email history retrieved successfully.', 'mrm' ),
			200,
			array(
				'data'        => $items,
				'count'       => $count,
				'total_pages' => $total_pages,
				'per_page'    => $per_page,
				'page'        => $page,
			)
		);
	}


	/**
	 * Delete a single broadcast email record.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.20.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		global $wpdb;

		$id    = (int) $request->get_param( 'id' );
		$table = $wpdb->prefix . EmailSchema::$table_name;

		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( false === $deleted ) {
			return $this->get_error_response( __( 'Failed to delete email record.', 'mrm' ), 500 );
		}

		return $this->get_success_response( __( 'Email record deleted successfully.', 'mrm' ), 200 );
	}


	/**
	 * Retrieve a single broadcast email record with its full body.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.20.0
	 */
	public function get_single( WP_REST_Request $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$broadcast_table  = $wpdb->prefix . EmailSchema::$table_name;
		$campaign_emails  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder    = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		$campaign_table   = $wpdb->prefix . CampaignSchema::$campaign_table;
		$contact_table    = $wpdb->prefix . ContactSchema::$table_name;
		$automation_steps = $wpdb->prefix . AutomationStepSchema::$table_name;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT be.id, be.email_address, be.email_type, be.status, be.email_id,
					be.contact_id, be.campaign_id, be.automation_id, be.step_id, be.created_at,
					c.first_name, c.last_name,
					ce.email_subject AS campaign_subject,
					eb.email_body,
					camp.title AS campaign_title
				FROM {$broadcast_table} be
				LEFT JOIN {$contact_table} c ON c.id = be.contact_id
				LEFT JOIN {$campaign_emails} ce ON ce.id = be.email_id
				LEFT JOIN {$email_builder} eb ON eb.email_id = ce.id
				LEFT JOIN {$campaign_table} camp ON camp.id = be.campaign_id
				WHERE be.id = %d
				LIMIT 1",
				$id
			)
		);
		// phpcs:enable

		if ( ! $row ) {
			return $this->get_error_response( __( 'Email record not found.', 'mrm' ), 404 );
		}

		$subject = $row->campaign_subject;

		if ( 'automation' === $row->email_type && ! empty( $row->step_id ) && empty( $subject ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$step = $wpdb->get_row(
				$wpdb->prepare( "SELECT settings FROM {$automation_steps} WHERE step_id = %s LIMIT 1", $row->step_id )
			);
			// phpcs:enable
			if ( $step && ! empty( $step->settings ) ) {
				$settings = maybe_unserialize( $step->settings );
				if ( is_string( $settings ) ) {
					$settings = json_decode( $settings, true );
				}
				$subject = isset( $settings['message_data']['subject'] ) ? $settings['message_data']['subject'] : '';
			}
		}

		$meta_table = $wpdb->prefix . EmailMetaSchema::$table_name;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$open_at    = $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$meta_table} WHERE mint_email_id = %d AND meta_key = 'is_open' AND meta_value = 1 ORDER BY created_at ASC LIMIT 1", $id ) );
		$clicked_at = $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$meta_table} WHERE mint_email_id = %d AND meta_key = 'is_click' AND meta_value = 1 ORDER BY created_at ASC LIMIT 1", $id ) );
		// phpcs:enable

		$email_settings = get_option( '_mrm_email_settings', array() );
		$from_email     = ! empty( $email_settings['from_email'] ) ? sanitize_email( $email_settings['from_email'] ) : get_option( 'admin_email' );

		return $this->get_success_response(
			__( 'Email record retrieved successfully.', 'mrm' ),
			200,
			array(
				'id'            => (int) $row->id,
				'email_address' => $row->email_address,
				'first_name'    => $row->first_name ?: '',
				'last_name'     => $row->last_name ?: '',
				'subject'       => $subject ?: __( '(No Subject)', 'mrm' ),
				'body'          => $row->email_body ?: '',
				'status'        => $row->status,
				'sending_time'  => $row->created_at,
				'sender_email'  => $from_email,
				'open_at'       => $open_at,
				'clicked_at'    => $clicked_at,
				'type'          => $row->email_type,
			)
		);
	}


	/**
	 * Resend a broadcast email to the original recipient.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.20.0
	 */
	public function resend( WP_REST_Request $request ) {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$broadcast_table  = $wpdb->prefix . EmailSchema::$table_name;
		$campaign_emails  = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$email_builder    = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		$automation_steps = $wpdb->prefix . AutomationStepSchema::$table_name;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT be.*, ce.email_subject AS campaign_subject, ce.email_preview_text AS campaign_preview_text, eb.email_body, eb.editor_type
				FROM {$broadcast_table} be
				LEFT JOIN {$campaign_emails} ce ON ce.id = be.email_id
				LEFT JOIN {$email_builder} eb ON eb.email_id = ce.id
				WHERE be.id = %d
				LIMIT 1",
				$id
			)
		);
		// phpcs:enable

		if ( ! $row ) {
			return $this->get_error_response( __( 'Email record not found.', 'mrm' ), 404 );
		}

		$subject      = $row->campaign_subject;
		$body         = $row->email_body;
		$preview_text = ! empty( $row->campaign_preview_text ) ? $row->campaign_preview_text : '';
		$editor_type  = ! empty( $row->editor_type ) ? $row->editor_type : 'advanced-builder';

		if ( 'automation' === $row->email_type && ! empty( $row->step_id ) && ( empty( $subject ) || empty( $body ) ) ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$step = $wpdb->get_row(
				$wpdb->prepare( "SELECT settings FROM {$automation_steps} WHERE step_id = %s LIMIT 1", $row->step_id )
			);
			// phpcs:enable
			if ( $step && ! empty( $step->settings ) ) {
				$settings = maybe_unserialize( $step->settings );
				if ( is_string( $settings ) ) {
					$settings = json_decode( $settings, true );
				}
				if ( empty( $subject ) ) {
					$subject = isset( $settings['message_data']['subject'] ) ? $settings['message_data']['subject'] : '';
				}
				if ( empty( $body ) ) {
					$body = isset( $settings['message_data']['email_body'] ) ? $settings['message_data']['email_body'] : '';
				}
				if ( empty( $preview_text ) ) {
					$preview_text = isset( $settings['message_data']['email_preview_text'] ) ? $settings['message_data']['email_preview_text'] : '';
				}
				if ( 'advanced-builder' === $editor_type && ! empty( $settings['message_data']['editor_type'] ) ) {
					$editor_type = $settings['message_data']['editor_type'];
				}
			}
		}

		if ( empty( $body ) ) {
			return $this->get_error_response( __( 'Email body not found. Cannot resend.', 'mrm' ), 422 );
		}

		// Fetch contact and flatten meta_fields — required for custom-field merge tags.
		$contact = ! empty( $row->contact_id ) ? ContactModel::get( (int) $row->contact_id ) : null;
		if ( $contact && isset( $contact['meta_fields'] ) && is_array( $contact['meta_fields'] ) ) {
			$contact = array_merge( $contact, $contact['meta_fields'] );
			unset( $contact['meta_fields'] );
		}

		// Apply Pro latest-content replacement before merge tag parsing.
		if ( MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.15.1' ) ) {
			$body = Mint_Pro_Helper::replace_automatic_latest_content( $body );
		}

		// Convert plain-text-editor line breaks to HTML.
		if ( 'plain-text-editor' === $editor_type ) {
			$body = nl2br( html_entity_decode( $body ) );
		}

		// Parse merge tags in subject, preview text, and body.
		if ( $contact ) {
			$subject      = Parser::parse( $subject, $contact );
			$preview_text = Parser::parse( $preview_text, $contact );
			$body         = Parser::parse( $body, $contact );
		}

		// Generate a new hash for this resend (tracking pixel, click tracking, unsubscribe).
		$new_hash = MrmCommon::get_rand_email_hash( $row->email_address, (int) $row->campaign_id );

		// Wrap all links with click-tracking URLs.
		$body = Helper::replace_url( $body, $new_hash );

		// Restore original From/Reply-To headers; fall back to email settings.
		$email_headers = ! empty( $row->email_headers ) ? json_decode( $row->email_headers, true ) : array();
		if ( ! is_array( $email_headers ) ) {
			$email_headers = array();
		}
		if ( empty( $email_headers ) ) {
			$email_settings = get_option( '_mrm_email_settings', array() );
			$from_name      = ! empty( $email_settings['from_name'] ) ? sanitize_text_field( $email_settings['from_name'] ) : get_bloginfo( 'name' );
			$from_email     = ! empty( $email_settings['from_email'] ) ? sanitize_email( $email_settings['from_email'] ) : get_option( 'admin_email' );
			$email_headers  = array(
				'MIME-Version: 1.0',
				'Content-type: text/html;charset=UTF-8',
				'From: ' . $from_name . ' <' . $from_email . '>',
			);
		}

		// Add X-PreHeader and List-Unsubscribe headers.
		$personalizer  = new EmailPersonalizer();
		$email_headers = $personalizer->buildHeaders( $preview_text, $new_hash, $email_headers );

		// Inject tracking pixel, preview text, watermark, and RTL adjustments.
		$watermark = CampaignEmailBuilderModel::get_email_footer_watermark();
		$body      = $personalizer->personalizeBody( $body, $new_hash, $preview_text, $editor_type, $watermark );

		// Apply Pro lead-magnet tracking.
		$body = $personalizer->applyProProcessing( $body, $row->email_address );

		// Send via the plugin mailer (same path as campaign/automation sends).
		$sent = MM()->mailer->send( $row->email_address, $subject ?: __( '(No Subject)', 'mrm' ), $body, $email_headers );

		if ( ! $sent ) {
			return $this->get_error_response( __( 'Failed to resend email.', 'mrm' ), 500 );
		}

		// Log the resend as a new broadcast record.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$broadcast_table,
			array(
				'contact_id'    => $row->contact_id,
				'email_address' => $row->email_address,
				'email_type'    => $row->email_type,
				'status'        => 'sent',
				'campaign_id'   => $row->campaign_id,
				'automation_id' => $row->automation_id,
				'step_id'       => $row->step_id,
				'email_id'      => $row->email_id,
				'email_hash'    => $new_hash,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return $this->get_success_response( __( 'Email resent successfully.', 'mrm' ), 200 );
	}


	/**
	 * Delete multiple broadcast email records.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 * @since 1.20.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		global $wpdb;

		$params = MrmCommon::get_api_params_values( $request );
		$ids    = isset( $params['ids'] ) ? array_map( 'intval', (array) $params['ids'] ) : array();

		if ( empty( $ids ) ) {
			return $this->get_error_response( __( 'No IDs provided.', 'mrm' ), 400 );
		}

		$table       = $wpdb->prefix . EmailSchema::$table_name;
		$placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholder})", $ids ) );

		return $this->get_success_response( __( 'Email records deleted successfully.', 'mrm' ), 200 );
	}
}
