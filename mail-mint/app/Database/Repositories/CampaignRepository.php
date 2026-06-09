<?php
/**
 * CampaignRepository — SOLID repository for the campaign module.
 *
 * Replaces legacy CampaignModel static methods for campaign CRUD, meta
 * operations, email step management, recipient fetching, scheduling
 * delegation, and analytics queries with a clean, testable repository
 * extending AbstractRepository.
 *
 * @package Mint\MRM\Database\Repositories
 * @since   1.20.0
 */

namespace Mint\MRM\Database\Repositories;

use Mint\MRM\Database\AbstractRepository;
use Mint\MRM\Database\QueryBuilder;
use Mint\MRM\Database\Enums\CampaignType;
use Mint\MRM\Database\Enums\CampaignStatus;
use Mint\MRM\Database\Enums\CampaignEmailStatus;
use Mint\MRM\Database\Enums\BroadcastStatus;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Tables\EmailSchema;
use Mint\MRM\Utilites\Helper\Campaign;
use MailMint\App\Helper;
use MailMintPro\Mint\Database\Repositories\SegmentRepository;
use MailMintPro\Mint\Internal\Admin\Segmentation\SegmentFilterService;
use MRM\Common\MrmCommon;

/**
 * Class CampaignRepository
 *
 * @since 1.20.0
 */
class CampaignRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.20.0
	 */
	protected function tableName(): string {
		return 'mint_campaigns';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.20.0
	 */
	protected function fillable(): array {
		return array( 'title', 'status', 'type', 'scheduled_at', 'created_by' );
	}

	/**
	 * Whitelist of columns allowed for ORDER BY.
	 *
	 * @since 1.20.0
	 *
	 * @var string[]
	 */
	private const ALLOWED_ORDER_BY = array( 'id', 'title', 'created_at', 'status', 'type' );

	/**
	 * List campaigns with search, type/status filtering, sorting, and batch stats.
	 *
	 * Overrides AbstractRepository::list() to add campaign-specific filtering,
	 * withStatsQuery() stats merge, and type-grouped counts.
	 *
	 * @since 1.20.0
	 *
	 * @param array $params {
	 *     Optional. Query parameters.
	 *
	 *     @type string $search   Title LIKE match.
	 *     @type string $type     Exact match against CampaignType values, or 'all'.
	 *     @type string $status   Exact match against CampaignStatus values, or 'all'.
	 *     @type string $order_by Column from whitelist. Default 'id'.
	 *     @type string $order    'ASC' or 'DESC'. Default 'DESC'.
	 *     @type int    $page     Page number. Default 1.
	 *     @type int    $per_page Items per page. Default 10.
	 * }
	 *
	 * @return array {
	 *     @type array $data        Rows with merged stats.
	 *     @type int   $total       Total matching rows.
	 *     @type int   $page        Current page.
	 *     @type int   $per_page    Items per page.
	 *     @type int   $total_pages Total pages.
	 *     @type array $groups      Campaign counts grouped by type.
	 * }
	 */
	public function list( array $params ): array {
		// Extract and sanitize parameters.
		$page     = isset( $params['page'] ) && (int) $params['page'] > 0 ? (int) $params['page'] : 1;
		$per_page = isset( $params['per_page'] ) && (int) $params['per_page'] > 0 ? (int) $params['per_page'] : 10;
		$search   = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$type     = isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : '';
		$status   = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '';
		$order_by = isset( $params['order_by'] ) ? $params['order_by'] : 'id';
		$order    = isset( $params['order'] ) ? strtoupper( $params['order'] ) : 'DESC';

		// Validate order_by against whitelist, default to 'id'.
		if ( ! in_array( $order_by, self::ALLOWED_ORDER_BY, true ) ) {
			$order_by = 'id';
		}

		// Validate order direction.
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		/**
		 * Filters the list query parameters before building the query.
		 *
		 * @since 1.20.0
		 *
		 * @param array  $params     Query parameters.
		 * @param string $entityName Entity name ('campaigns').
		 */
		$params = apply_filters( 'mailmint_repository_list_query', $params, $this->entityName() );

		$query = QueryBuilder::table( $this->prefixedTable() );

		if ( ! empty( $search ) ) {
			global $wpdb;
			$query->where( 'title', 'LIKE', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		if ( ! empty( $type ) && 'all' !== $type ) {
			$query->where( 'type', '=', $type );
		}

		if ( ! empty( $status ) && 'all' !== $status ) {
			$query->where( 'status', '=', $status );
		}

		$query->orderBy( $order_by, $order );

		$result = $query->paginate( $page, $per_page );

		$ids   = array_map( 'intval', array_column( $result['data'], 'id' ) );
		$stats = ! empty( $ids ) ? $this->withStatsQuery( $ids ) : array();

		if ( ! empty( $stats ) ) {
			$stats_by_id = array();
			foreach ( $stats as $stat ) {
				if ( isset( $stat['id'] ) ) {
					$stats_by_id[ $stat['id'] ] = $stat;
				}
			}
			foreach ( $result['data'] as &$row ) {
				if ( isset( $row['id'], $stats_by_id[ $row['id'] ] ) ) {
					$row = array_merge( $row, $stats_by_id[ $row['id'] ] );
				}
			}
			unset( $row );
		}
		$result['groups'] = $this->getCampaignGroups();

		return $result;
	}

	/**
	 * Single aggregation query returning per-campaign stats.
	 *
	 * Uses raw SQL with GROUP BY + CASE on mint_broadcast_emails LEFT JOIN
	 * mint_broadcast_email_meta. Returns total_sent, total_delivered,
	 * total_bounced, open_rate, click_rate, unsubscribe_count per campaign.
	 *
	 * This is the documented exception for queries too complex for
	 * QueryBuilder's fluent API (same pattern as ContactGroupRepository).
	 *
	 * @since 1.20.0
	 *
	 * @param int[] $ids Campaign IDs from the current page.
	 *
	 * @return array Array of associative arrays with keys: id, total_sent,
	 *               total_delivered, total_bounced, open_rate, click_rate,
	 *               unsubscribe_count.
	 */
	public function withStatsQuery( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;

		$broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
		$meta_table      = $wpdb->prefix . 'mint_broadcast_email_meta';

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$prepare_args = array_merge(
			array( BroadcastStatus::SENT, BroadcastStatus::FAILED ),
			array_map( 'intval', $ids )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT
				b.campaign_id AS id,
				COUNT( DISTINCT b.id ) AS total_sent,
				COUNT( DISTINCT CASE WHEN b.status = %s THEN b.id END ) AS total_delivered,
				COUNT( DISTINCT CASE WHEN b.status = %s THEN b.id END ) AS total_bounced,
				COUNT( DISTINCT CASE WHEN m_open.meta_value = '1' THEN b.id END ) AS open_rate,
				COUNT( DISTINCT CASE WHEN m_click.meta_value = '1' THEN b.id END ) AS click_rate,
				COUNT( DISTINCT CASE WHEN m_unsub.meta_value = '1' THEN b.id END ) AS unsubscribe_count
			FROM {$broadcast_table} AS b
			LEFT JOIN {$meta_table} AS m_open
				ON b.id = m_open.mint_email_id AND m_open.meta_key = 'is_open'
			LEFT JOIN {$meta_table} AS m_click
				ON b.id = m_click.mint_email_id AND m_click.meta_key = 'is_click'
			LEFT JOIN {$meta_table} AS m_unsub
				ON b.id = m_unsub.mint_email_id AND m_unsub.meta_key = 'is_unsubscribe'
			WHERE b.campaign_id IN ({$placeholders})
			GROUP BY b.campaign_id",
			$prepare_args
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $results ) ) {
			return array();
		}

		// Fetch anonymous open/click counts from mint_campaigns_meta and merge in.
		$campaign_meta_table = $wpdb->prefix . 'mint_campaigns_meta';
		$anon_rows           = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT campaign_id, meta_key, meta_value FROM {$campaign_meta_table} WHERE meta_key IN ('_anon_open_count', '_anon_click_count') AND campaign_id IN ({$placeholders})",
				array_map( 'intval', $ids )
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$anon_open  = array();
		$anon_click = array();
		foreach ( $anon_rows as $row ) {
			$cid = (int) $row['campaign_id'];
			if ( '_anon_open_count' === $row['meta_key'] ) {
				$anon_open[ $cid ] = (int) $row['meta_value'];
			} elseif ( '_anon_click_count' === $row['meta_key'] ) {
				$anon_click[ $cid ] = (int) $row['meta_value'];
			}
		}

		// Cast numeric fields to int and add anonymous counts.
		return array_map(
			function ( $row ) use ( $anon_open, $anon_click ) {
				$cid = (int) $row['id'];
				return array(
					'id'                => $cid,
					'total_sent'        => (int) $row['total_sent'],
					'total_delivered'   => (int) $row['total_delivered'],
					'total_bounced'     => (int) $row['total_bounced'],
					'open_rate'         => (int) $row['open_rate'] + ( isset( $anon_open[ $cid ] ) ? $anon_open[ $cid ] : 0 ),
					'click_rate'        => (int) $row['click_rate'] + ( isset( $anon_click[ $cid ] ) ? $anon_click[ $cid ] : 0 ),
					'unsubscribe_count' => (int) $row['unsubscribe_count'],
				);
			},
			$results
		);
	}

	/**
	 * Insert a campaign meta row.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $key         Meta key.
	 * @param mixed  $value       Meta value.
	 *
	 * @return int Inserted row ID.
	 */
	public function insertMeta( int $campaign_id, string $key, $value ): int {
		global $wpdb;

		return QueryBuilder::table( $wpdb->prefix . 'mint_campaigns_meta' )
			->insert(
				array(
					'campaign_id' => $campaign_id,
					'meta_key'    => $key,
					'meta_value'  => $value,
					'created_at'  => current_time( 'mysql' ),
				)
			);
	}

	/**
	 * Update a campaign meta value.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $key         Meta key.
	 * @param mixed  $value       Meta value.
	 *
	 * @return int|false Number of rows updated, or false on failure.
	 */
	public function updateMeta( int $campaign_id, string $key, $value ) {
		global $wpdb;

		$result = QueryBuilder::table( $wpdb->prefix . 'mint_campaigns_meta' )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'meta_key', '=', $key )
			->update(
				array(
					'meta_value' => $value,
					'updated_at' => current_time( 'mysql' ),
				)
			);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return $result;
	}

	/**
	 * Retrieve a campaign meta value.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $key         Meta key.
	 *
	 * @return string|false Meta value, or false if not found.
	 */
	public function getMeta( int $campaign_id, string $key ) {
		global $wpdb;

		$row = QueryBuilder::table( $wpdb->prefix . 'mint_campaigns_meta' )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'meta_key', '=', $key )
			->first();

		if ( empty( $row ) || ! isset( $row['meta_value'] ) ) {
			return false;
		}

		return $row['meta_value'];
	}

	/**
	 * Insert or update a campaign meta value (upsert).
	 *
	 * If the meta key already exists for the campaign, updates the value.
	 * Otherwise, inserts a new meta row.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $key         Meta key.
	 * @param mixed  $value       Meta value.
	 *
	 * @return int|false Inserted row ID on insert, rows affected on update, or false on failure.
	 */
	public function insertOrUpdateMeta( int $campaign_id, string $key, $value ) {
		$existing = $this->getMeta( $campaign_id, $key );

		if ( false === $existing ) {
			return $this->insertMeta( $campaign_id, $key, $value );
		}

		return $this->updateMeta( $campaign_id, $key, $value );
	}

	/**
	 * Delete a campaign meta row by campaign ID and meta key.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $key         Meta key to delete.
	 *
	 * @return int Number of rows deleted.
	 */
	public function deleteMeta( int $campaign_id, string $key ): int {
		global $wpdb;

		return QueryBuilder::table( $wpdb->prefix . 'mint_campaigns_meta' )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'meta_key', '=', $key )
			->delete();
	}

	/**
	 * Insert a campaign email step into mint_campaign_emails.
	 *
	 * @since 1.20.0
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $data        Email step data (email_index, email_subject, sender_email, etc.).
	 *
	 * @return int Inserted row ID.
	 */
	public function insertCampaignEmail( int $campaign_id, array $data ): int {
		global $wpdb;

		$data['campaign_id'] = $campaign_id;
		$data['created_at']  = current_time( 'mysql' );

		return QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails' )
			->insert( $data );
	}

	/**
	 * Update a campaign email step by email ID and campaign ID.
	 *
	 * @since 1.20.0
	 *
	 * @param int   $email_id    Campaign email step ID.
	 * @param int   $campaign_id Campaign ID.
	 * @param array $data        Column => value pairs to update.
	 *
	 * @return int|false Number of rows updated, or false on failure.
	 */
	public function updateCampaignEmail( int $email_id, int $campaign_id, array $data ) {
		global $wpdb;

		return QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails' )
			->where( 'id', '=', $email_id )
			->where( 'campaign_id', '=', $campaign_id )
			->update( $data );
	}

	/**
	 * Get all campaign email steps for a campaign, ordered by email_index ASC.
	 *
	 * LEFT JOINs the email builder table to include json_data and email body.
	 * Post-processes each row to unserialize json_data and attach schedule_date meta.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return array List of campaign email step rows with builder data.
	 */
	public function getCampaignEmails( int $campaign_id ): array {
		global $wpdb;

		$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';
		$email_builder_table   = $wpdb->prefix . 'mint_campaign_email_builder';

		$emails = QueryBuilder::table( $campaign_emails_table . ' AS CET' )
			->select(
				'CET.id', 'CET.delay', 'CET.delay_count', 'CET.delay_value',
				'CET.send_time', 'CET.sender_email', 'CET.sender_name',
				'CET.reply_email', 'CET.reply_name',
				'CET.email_index', 'CET.email_subject', 'CET.email_preview_text',
				'CET.template_id', 'CET.status', 'CET.scheduled_at',
				'EBT.json_data', 'EBT.email_body AS body_data'
			)
			->leftJoin( $email_builder_table . ' AS EBT', 'CET.id', '=', 'EBT.email_id' )
			->where( 'CET.campaign_id', '=', $campaign_id )
			->orderBy( 'CET.email_index', 'ASC' )
			->get();

		if ( empty( $emails ) ) {
			return array();
		}

		return array_map(
			function ( $email ) {
				$email_json          = isset( $email['json_data'] ) ? $email['json_data'] : '';
				$email['email_json'] = maybe_unserialize( $email_json );

				$custom_date = $this->getCampaignEmailMeta( (int) $email['id'], 'schedule_date' );
				if ( $custom_date ) {
					$email['scheduleDate'] = $custom_date;
					$email['delay_option'] = 'customDate';
				} else {
					$email['delay_option'] = 'timePeriod';
				}

				return $email;
			},
			$emails
		);
	}

	/**
	 * Get a single campaign email step by campaign ID and email ID.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id    Campaign email step ID.
	 *
	 * @return array|null Campaign email row or null if not found.
	 */
	public function getCampaignEmailById( int $campaign_id, int $email_id ): ?array {
		global $wpdb;

		return QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails' )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'id', '=', $email_id )
			->first();
	}

	/**
	 * Delete a campaign email step by campaign ID and email ID.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id    Campaign email step ID.
	 *
	 * @return int Number of rows deleted.
	 */
	public function deleteCampaignEmail( int $campaign_id, int $email_id ): int {
		global $wpdb;

		$result = QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails' )
			->where( 'id', '=', $email_id )
			->where( 'campaign_id', '=', $campaign_id )
			->delete();

		return is_wp_error( $result ) ? 0 : $result;
	}

	/**
	 * Get a meta value for a campaign email step.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $email_id Campaign email step ID.
	 * @param string $key      Meta key.
	 *
	 * @return string|false Meta value, or false if not found.
	 */
	public function getCampaignEmailMeta( int $email_id, string $key ) {
		global $wpdb;

		$row = QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails_meta' )
			->where( 'campaign_emails_id', '=', $email_id )
			->where( 'meta_key', '=', $key )
			->first();

		if ( empty( $row ) || ! isset( $row['meta_value'] ) ) {
			return false;
		}

		return $row['meta_value'];
	}

	/**
	 * Insert or update a campaign email meta value (upsert).
	 *
	 * If the meta key already exists for the email, updates the value.
	 * Otherwise, inserts a new meta row.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $email_id Campaign email step ID.
	 * @param string $key      Meta key.
	 * @param mixed  $value    Meta value.
	 *
	 * @return int|false Inserted row ID on insert, rows affected on update, or false on failure.
	 */
	public function insertOrUpdateCampaignEmailMeta( int $email_id, string $key, $value ) {
		global $wpdb;

		$existing = $this->getCampaignEmailMeta( $email_id, $key );

		if ( false === $existing ) {
			return QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails_meta' )
				->insert(
					array(
						'campaign_emails_id' => $email_id,
						'meta_key'           => $key,
						'meta_value'         => $value,
					)
				);
		}

		return QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails_meta' )
			->where( 'campaign_emails_id', '=', $email_id )
			->where( 'meta_key', '=', $key )
			->update(
				array(
					'meta_value' => $value,
				)
			);
	}

	/**
	 * Update campaign status with Enum validation.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $status      New status value.
	 * @return int|\WP_Error Affected rows, or WP_Error if status is invalid.
	 */
	public function updateStatus( int $campaign_id, string $status ) {
		if ( ! CampaignStatus::isValid( $status ) ) {
			return new \WP_Error(
				'invalid_campaign_status',
				/* translators: %s: The invalid status string. */
				sprintf( __( 'Invalid campaign status: %s', 'mrm' ), $status )
			);
		}

		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'id', '=', $campaign_id )
			->update(
				array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql' ),
				)
			);
	}

	/**
	 * Update campaign email step status with Enum validation.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param int    $email_id    Campaign email step ID.
	 * @param string $status      New status value.
	 * @return int|\WP_Error Affected rows, or WP_Error if status is invalid.
	 */
	public function updateEmailStatus( int $campaign_id, int $email_id, string $status ) {
		if ( ! CampaignEmailStatus::isValid( $status ) ) {
			return new \WP_Error(
				'invalid_campaign_email_status',
				/* translators: %s: The invalid status string. */
				sprintf( __( 'Invalid campaign email status: %s', 'mrm' ), $status )
			);
		}

		global $wpdb;

		return QueryBuilder::table( $wpdb->prefix . 'mint_campaign_emails' )
			->where( 'id', '=', $email_id )
			->where( 'campaign_id', '=', $campaign_id )
			->update(
				array(
					'status' => $status,
				)
			);
	}

	/**
	 * Schedule a campaign email processing action via Action Scheduler.
	 *
	 * Uses as_enqueue_async_action() for immediate sends (no delay, no schedule).
	 * Uses as_schedule_single_action() for delayed or scheduled sends.
	 * Guards with as_has_scheduled_action() to prevent duplicate scheduling.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param array  $email       Campaign email step data (must include delay_count, delay_value when delayed).
	 * @param string $status      Campaign status string.
	 * @param string $schedule    Optional scheduled datetime string.
	 * @param int    $offset      Pagination offset for recipient batching.
	 * @param int    $per_batch   Batch size for recipient fetching.
	 *
	 * @return void
	 */
	public function scheduleCampaignAction( int $campaign_id, array $email, string $status, string $schedule = '', int $offset = 0, int $per_batch = 200 ): void {
		if ( ! $campaign_id || empty( $email ) ) {
			return;
		}

		$args  = array(
			array(
				'campaign_id'     => $campaign_id,
				'campaign_status' => $status,
				'email'           => $email,
				'offset'          => $offset,
				'per_batch'       => $per_batch,
			),
		);
		$group = 'mailmint-campaign-schedule-' . $campaign_id;

		if ( ! defined( 'MAILMINT_SCHEDULE_EMAILS' ) || as_has_scheduled_action( MAILMINT_SCHEDULE_EMAILS, $args, $group ) ) {
			return;
		}

		if ( empty( $email['delay_count'] ) && empty( $schedule ) ) {
			as_enqueue_async_action( MAILMINT_SCHEDULE_EMAILS, $args, $group );
		} else {
			if ( ! empty( $schedule ) ) {
				$current_date   = new \DateTime( 'now', wp_timezone() );
				$scheduled_date = date_create( gmdate( 'Y-m-d H:i:s', strtotime( $schedule ) ), wp_timezone() );
				$date_diff      = date_diff( $scheduled_date, $current_date );
				$date_diff      = '+' . $date_diff->y . 'year' . $date_diff->m . 'month' . $date_diff->d . 'day' . $date_diff->h . 'hour' . $date_diff->i . 'minute' . ( $date_diff->s + 1 ) . 'second';
				$scheduled_at   = strtotime( $date_diff );
			} else {
				$scheduled_at = strtotime( '+' . $email['delay_count'] . ' ' . str_replace( 's', '', $email['delay_value'] ) );
			}
			as_schedule_single_action( $scheduled_at, MAILMINT_SCHEDULE_EMAILS, $args, $group );
		}
	}

	/**
	 * Enqueue an async send-email action via Action Scheduler.
	 *
	 * Delegates to as_enqueue_async_action() using the MAILMINT_SEND_SCHEDULED_EMAILS
	 * constant. Guards with as_has_scheduled_action() to prevent duplicate scheduling.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id    Campaign email step ID.
	 * @param int $batch       Batch number. Default 1.
	 *
	 * @return void
	 */
	public function scheduleAsyncSendEmailAction( int $campaign_id, int $email_id, int $batch = 1 ): void {
		if ( ! $campaign_id ) {
			return;
		}

		$args  = array(
			array(
				'campaign_id' => $campaign_id,
				'email_id'    => $email_id,
				'batch'       => $batch,
			),
		);
		$group = 'mailmint-campaign-email-sending-' . $campaign_id;

		if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) && ! as_has_scheduled_action( MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group ) ) {
			$previous_email = $this->getPreviousCampaignEmail( $campaign_id, $email_id );
			$compat_delay   = 0;

			if ( ! empty( $previous_email ) ) {
				$heuristic_enabled = (bool) apply_filters(
					'mailmint_enable_sequence_child_heuristic_delay',
					false,
					$campaign_id,
					$email_id,
					$previous_email
				);

				if ( $heuristic_enabled ) {
					// Temporary backward-compatibility fallback only.
					// Deterministic parent gating is the correctness path; this 120-second
					// kickoff delay only survives behind an explicit compatibility filter.
					$compat_delay = (int) apply_filters(
						'mailmint_sequence_child_kickoff_delay_seconds',
						120,
						$campaign_id,
						$email_id,
						$batch,
						$previous_email
					);
				}
			}

			if ( ! empty( $previous_email ) && $compat_delay > 0 ) {
				$this->scheduleDelayedSendAction( $campaign_id, $email_id, $batch, $compat_delay );
			} else {
				as_enqueue_async_action( MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group );
			}
		}
	}

	/**
	 * Check if an email ID has a previous step in the same campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id Email ID to check.
	 *
	 * @return array|null Previous email details, or null if this is the first step.
	 *
	 * @since 1.20.0
	 */
	private function getPreviousCampaignEmail( int $campaign_id, int $email_id ): ?array {
		if ( ! $campaign_id || ! $email_id ) {
			return null;
		}

		$previous = QueryBuilder::table( $this->getCampaignEmailsTable() )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'id', '<', $email_id )
			->orderBy( 'id', 'DESC' )
			->limit( 1 )
			->first();

		return $previous ?: null;
	}

	/**
	 * Remove all scheduled actions for a campaign.
	 *
	 * Deletes Action Scheduler entries for the campaign's schedule group,
	 * send-email group, and recurring group via MrmCommon::delete_as_actions().
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return void
	 */
	public function unscheduleCampaignActions( int $campaign_id ): void {
		$campaign_group            = 'mailmint-campaign-schedule-' . $campaign_id;
		$campaign_send_email_group = 'mailmint-campaign-email-sending-' . $campaign_id;
		$recurring_campaign_group  = 'mailmint-recurring-campaign-schedule-' . $campaign_id;

		MrmCommon::delete_as_actions( $campaign_group );
		MrmCommon::delete_as_actions( $campaign_send_email_group );
		MrmCommon::delete_as_actions( $recurring_campaign_group );
	}

	/**
	 * Fetch campaign recipients based on lists/tags/segments stored in campaign meta.
	 *
	 * Reads the 'recipients' meta value, which contains lists, tags, and segments arrays.
	 * Delegates to SegmentRepository + SegmentFilterService for segments (Pro, with class_exists() guard),
	 * falls back to ContactGroupPivotModel for lists/tags.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $offset      Pagination offset.
	 * @param int $per_batch   Batch size.
	 *
	 * @return array Array of contact rows [{id, email, ...}, ...].
	 */
	public function getRecipients( int $campaign_id, int $offset = 0, int $per_batch = 200 ): array {
		$all_recipients = $this->getMeta( $campaign_id, 'recipients' );
		$all_recipients = maybe_unserialize( $all_recipients );
		$contacts       = array();

		if ( ! empty( $all_recipients['segments'] ) ) {
			$segment_id = isset( $all_recipients['segments'][0]['id'] ) ? $all_recipients['segments'][0]['id'] : 0;

			if ( class_exists( SegmentRepository::class ) && class_exists( SegmentFilterService::class ) ) {
				$segment_repo = new SegmentRepository();
				$segment      = $segment_repo->findWithFilters( $segment_id );

				if ( $segment && ! empty( $segment['filters'] ) ) {
					$filter_service = new SegmentFilterService();
					$where_clause   = $filter_service->buildWhereClause( $segment['filters'] );

					if ( $where_clause ) {
						$page         = $per_batch > 0 ? (int) floor( $offset / $per_batch ) + 1 : 1;
						$segment_data = $filter_service->getContacts(
							$where_clause,
							array(
								'page'     => $page,
								'per_page' => $per_batch,
							)
						);

						if ( ! empty( $segment_data['data'] ) ) {
							foreach ( $segment_data['data'] as $contact ) {
								if ( 'subscribed' === $contact['status'] ) {
									$contacts[ $contact['id'] ] = $contact;
								}
							}
						}
					}
				}
			}

			return array_values( $contacts );
		}

		$group_ids = array_merge(
			$all_recipients['lists'] ?? array(),
			$all_recipients['tags'] ?? array()
		);

		if ( ! empty( $group_ids ) ) {
			$recipients_ids = ContactGroupPivotModel::get_contacts_to_group( array_column( $group_ids, 'id' ), $offset, $per_batch );
			$recipients_ids = array_column( $recipients_ids, 'contact_id' );
			$contacts       = ContactModel::get_single_email( $recipients_ids );
		}

		return $contacts;
	}

	/**
	 * Get campaign data for duplication — campaign row + meta + email steps with builder data.
	 *
	 * Replicates the legacy CampaignModel::get_campaign_to_duplicate() data shape:
	 * campaign columns + meta columns (flattened via LEFT JOIN) + 'emails' key with builder data.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return array Campaign row with meta columns and 'emails' key containing email steps with builder data.
	 */
	public function getCampaignToDuplicate( int $campaign_id ): array {
		global $wpdb;

		$campaign_table      = $wpdb->prefix . 'mint_campaigns';
		$campaign_meta_table = $wpdb->prefix . 'mint_campaigns_meta';

		$campaign = QueryBuilder::table( $campaign_table . ' AS CT' )
			->leftJoin( $campaign_meta_table . ' AS CMT', 'CT.id', '=', 'CMT.campaign_id' )
			->where( 'CT.id', '=', $campaign_id )
			->first();

		if ( empty( $campaign ) ) {
			return array();
		}

		$campaign['id']     = $campaign_id;
		$campaign['emails'] = $this->getCampaignEmailsToDuplicate( $campaign_id );

		return $campaign;
	}

	/**
	 * Get campaign email steps with builder data for duplication.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return array Email step rows with json_data and email_body from builder table.
	 */
	private function getCampaignEmailsToDuplicate( int $campaign_id ): array {
		global $wpdb;

		$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';
		$email_builder_table   = $wpdb->prefix . 'mint_campaign_email_builder';

		return QueryBuilder::table( $campaign_emails_table . ' AS CET' )
			->select(
				'CET.id', 'CET.delay', 'CET.delay_count', 'CET.delay_value',
				'CET.send_time', 'CET.sender_email', 'CET.sender_name',
				'CET.reply_email', 'CET.reply_name',
				'CET.email_index', 'CET.email_subject', 'CET.email_preview_text',
				'CET.template_id', 'CET.status', 'CET.scheduled_at',
				'EBT.json_data', 'EBT.email_body'
			)
			->leftJoin( $email_builder_table . ' AS EBT', 'CET.id', '=', 'EBT.email_id' )
			->where( 'CET.campaign_id', '=', $campaign_id )
			->get();
	}

	/**
	 * Return all campaigns whose scheduled_at time has arrived.
	 *
	 * Fetches campaigns with status = CampaignStatus::SCHEDULE and
	 * scheduled_at <= current MySQL time.
	 *
	 * @since 1.20.0
	 *
	 * @return array Campaign rows ready to be processed.
	 */
	public function getScheduledCampaigns(): array {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'status', '=', CampaignStatus::SCHEDULE )
			->where( 'scheduled_at', '<=', current_time( 'mysql' ) )
			->get();
	}

	/**
	 * Get campaign sending progress — first email with scheduling status and last email ID.
	 *
	 * Returns the first campaign email step still in SCHEDULING status (ordered by id ASC)
	 * and the last campaign email step ID (ordered by id DESC). Used by the background
	 * process and pause/resume controller to determine campaign progress.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return array {
	 *     @type array|null $first_scheduling_email First email step with SCHEDULING status, or null.
	 *     @type int|null   $last_email_id          Last email step ID for the campaign, or null.
	 * }
	 */
	public function getProgress( int $campaign_id ): array {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';

		$first_scheduling_email = QueryBuilder::table( $campaign_emails_table )
			->select(
				'id',
				'email_subject',
				'email_preview_text',
				'sender_email',
				'sender_name',
				'reply_email',
				'reply_name',
				'delay_count',
				'delay_value'
			)
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'status', '=', CampaignEmailStatus::SCHEDULING )
			->orderBy( 'id', 'ASC' )
			->first();

		$last_email_row = QueryBuilder::table( $campaign_emails_table )
			->select( 'id' )
			->where( 'campaign_id', '=', $campaign_id )
			->orderBy( 'id', 'DESC' )
			->first();

		return array(
			'first_scheduling_email' => $first_scheduling_email ?: null,
			'last_email_id'          => $last_email_row ? (int) $last_email_row['id'] : null,
		);
	}

	/**
	 * Extract URLs from a campaign's email body content.
	 *
	 * JOINs mint_campaign_emails with mint_campaign_email_builder to retrieve
	 * email body HTML, then extracts all anchor href URLs.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return array Array of associative arrays with 'value' and 'label' keys for each unique URL.
	 */
	public function getUrlsFromCampaign( int $campaign_id ): array {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';
		$email_builder_table   = $wpdb->prefix . 'mint_campaign_email_builder';

		$emails = QueryBuilder::table( $campaign_emails_table . ' AS e' )
			->select( 'b.email_body' )
			->innerJoin( $email_builder_table . ' AS b', 'e.id', '=', 'b.email_id' )
			->where( 'e.campaign_id', '=', $campaign_id )
			->get();

		$urls = array();
		if ( ! empty( $emails ) ) {
			foreach ( $emails as $email ) {
				$urls = array_merge( $urls, Campaign::extract_urls_from_html( $email['email_body'] ) );
			}
		}

		$urls = array_unique( $urls );

		return array_map(
			function ( $url ) {
				return array(
					'value' => $url,
					'label' => $url,
				);
			},
			$urls
		);
	}

	/**
	 * Return campaign counts grouped by type.
	 *
	 * @since 1.20.0
	 *
	 * @return array Associative array of type => count.
	 */
	private function getCampaignGroups(): array {
		$rows   = QueryBuilder::table( $this->prefixedTable() )
			->select( 'type', 'COUNT(*) as count' )
			->groupBy( 'type' )
			->get();
		$groups = array();
		foreach ( $rows as $row ) {
			$groups[ $row['type'] ] = (int) $row['count'];
		}
		return $groups;
	}

	/**
	 * Return the total count of all campaigns.
	 *
	 * @since 1.20.0
	 *
	 * @return int Total campaign count.
	 */
	public function getCampaignCount(): int {
		return QueryBuilder::table( $this->prefixedTable() )->count();
	}

	/**
	 * Return the count of campaigns matching the given type.
	 *
	 * @since 1.20.0
	 *
	 * @param string $type Campaign type (CampaignType constant).
	 * @return int Campaign count for the given type.
	 */
	public function getCampaignCountByType( string $type ): int {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'type', '=', $type )
			->count();
	}

	/**
	 * Return the count of campaigns matching the given status.
	 *
	 * @since 1.20.0
	 *
	 * @param string $status Campaign status (CampaignStatus constant).
	 * @return int Campaign count for the given status.
	 */
	public function getCampaignCountByStatus( string $status ): int {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'status', '=', $status )
			->count();
	}

	/**
	 * Replace merge tag placeholders in email content with actual contact data.
	 *
	 * Handles first_name, last_name, email, company, designation, city, state,
	 * country, address lines, subscribe link, business settings, and custom fields.
	 * Falls back to WP user data when the contact is not found in MRM.
	 *
	 * Preserves the `mint_test_email_preview` filter hook for extensibility.
	 *
	 * @param string $data       Email subject/preview/body text with placeholders.
	 * @param int    $contact_id MRM contact ID (or WP user ID as fallback).
	 *
	 * @return array|string The content with placeholders replaced.
	 *
	 * @since 1.20.0
	 */
	public function replaceTestMailDynamicPlaceholders( string $data, int $contact_id ) {
		$contact = ContactModel::get( $contact_id );

		if ( empty( $contact ) ) {
			$user_info  = get_userdata( $contact_id );
			$first_name = $user_info->first_name;
			$last_name  = $user_info->last_name;
		} else {
			$first_name = isset( $contact['first_name'] ) ? $contact['first_name'] : '';
			$last_name  = isset( $contact['last_name'] ) ? $contact['last_name'] : '';
		}

		$email       = isset( $contact['email'] ) ? $contact['email'] : '';
		$company     = isset( $contact['meta_fields']['company'] ) ? $contact['meta_fields']['company'] : '';
		$designation = isset( $contact['meta_fields']['designation'] ) ? $contact['meta_fields']['designation'] : '';
		$city        = isset( $contact['meta_fields']['city'] ) ? $contact['meta_fields']['city'] : '';
		$state       = isset( $contact['meta_fields']['state'] ) ? $contact['meta_fields']['state'] : '';
		$country     = isset( $contact['meta_fields']['country'] ) ? $contact['meta_fields']['country'] : '';
		$address_1   = isset( $contact['meta_fields']['address_line_1'] ) ? $contact['meta_fields']['address_line_1'] : '';
		$address_2   = isset( $contact['meta_fields']['address_line_2'] ) ? $contact['meta_fields']['address_line_2'] : '';
		$hash        = isset( $contact['hash'] ) ? $contact['hash'] : '#';
		$meta_fields = ! empty( $contact['meta_fields'] ) ? $contact['meta_fields'] : array();

		$data = Helper::replace_placeholder_email_subject_preview( $data, $first_name, $last_name, $email, $city, $state, $country, $company, $designation, $meta_fields );
		$data = Helper::replace_placeholder_email_body( $data, $first_name, $last_name, $email, $address_1, $address_2, $company, $designation, $meta_fields );
		$data = Helper::replace_placeholder_business_setting( $data, $hash );

		// Replace subscribe link placeholders.
		$subscribe_url = site_url( '?mrm=1&route=confirmation&hash=' . $hash );

		$data = str_replace( '{{subscribe_link}}', $subscribe_url, $data );
		$data = str_replace( '{{link.subscribe}}', $subscribe_url, $data );

		$subscribe_text     = Helper::get_pipe_text( 'link.subscribe_html', $data, $subscribe_url );
		$subscribe_url_html = '<a href ="' . $subscribe_url . '">' . $subscribe_text . '</a>';

		$data = Helper::replace_pipe_data( 'link.subscribe_html', $data, $subscribe_url_html );
		$data = str_replace( '{{link.subscribe_html|' . $subscribe_text . '}}', $subscribe_url_html, $data );

		$data = str_replace( '#subscribe_link#', $subscribe_url, $data );

		/**
		 * Applies the 'mint_test_email_preview' filter to the provided data.
		 *
		 * @param string $data The data to be filtered.
		 *
		 * @return mixed The filtered data.
		 * @since 1.5.0
		 */
		$data = apply_filters( 'mint_test_email_preview', $data );

		return $data;
	}

	/**
	 * Return custom field slugs where type is 'text' or 'textArea'.
	 *
	 * @return array List of custom field slug strings.
	 *
	 * @since 1.20.0
	 */
	public function getAllCustomFields(): array {
		$results = QueryBuilder::table( 'mint_custom_fields' )
			->select( 'slug' )
			->whereIn( 'type', array( 'text', 'textArea' ) )
			->get();

		return array_map(
			function ( $item ) {
				return $item['slug'];
			},
			$results
		);
	}

	/**
	 * Delete a campaign with cascade cleanup inside a transaction.
	 *
	 * Sequence: broadcast email meta → broadcast emails → campaign meta →
	 *           email builder rows → campaign email steps → campaign row.
	 *
	 * @since 1.20.0
	 *
	 * @param int $id Campaign ID.
	 *
	 * @return int|\WP_Error Affected rows, or WP_Error on failure.
	 */
	public function destroy( int $id ) {
		return QueryBuilder::transaction(
			function () use ( $id ) {
				global $wpdb;

				$broadcast_table       = $wpdb->prefix . 'mint_broadcast_emails';
				$broadcast_meta_table  = $wpdb->prefix . 'mint_broadcast_email_meta';
				$campaign_meta_table   = $wpdb->prefix . 'mint_campaigns_meta';
				$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';
				$email_builder_table   = $wpdb->prefix . 'mint_campaign_email_builder';

				$broadcast_ids = QueryBuilder::table( $broadcast_table )
				->select( 'id' )
				->where( 'campaign_id', '=', $id )
				->get();
				$broadcast_ids = array_map( 'intval', array_column( $broadcast_ids, 'id' ) );

				if ( ! empty( $broadcast_ids ) ) {
					  QueryBuilder::table( $broadcast_meta_table )
					 ->whereIn( 'mint_email_id', $broadcast_ids )
					 ->delete();
				}

				QueryBuilder::table( $broadcast_table )
				->where( 'campaign_id', '=', $id )
				->delete();

				QueryBuilder::table( $campaign_meta_table )
				->where( 'campaign_id', '=', $id )
				->delete();

				$email_step_ids = QueryBuilder::table( $campaign_emails_table )
				->select( 'id' )
				->where( 'campaign_id', '=', $id )
				->get();
				$email_step_ids = array_map( 'intval', array_column( $email_step_ids, 'id' ) );

				if ( ! empty( $email_step_ids ) ) {
					QueryBuilder::table( $email_builder_table )
					   ->whereIn( 'email_id', $email_step_ids )
					   ->delete();
				}

				QueryBuilder::table( $campaign_emails_table )
				->where( 'campaign_id', '=', $id )
				->delete();

				return parent::destroy( $id );
			}
		);
	}

	/**
	 * Delete multiple campaigns with cascade cleanup for all IDs.
	 *
	 * Wraps the entire cascade in a single transaction for atomicity.
	 * Sequence: broadcast email meta → broadcast emails → campaign meta →
	 *           email builder rows → campaign email steps → campaign rows.
	 *
	 * @since 1.20.0
	 *
	 * @param array $ids Array of campaign IDs.
	 *
	 * @return int|\WP_Error Affected rows (0 if $ids is empty), or WP_Error on SQL failure.
	 */
	public function destroyMany( array $ids ) {
		if ( empty( $ids ) ) {
			return 0;
		}

		$ids = array_map( 'intval', $ids );

		return QueryBuilder::transaction(
			function () use ( $ids ) {
				global $wpdb;

				$broadcast_table       = $wpdb->prefix . 'mint_broadcast_emails';
				$broadcast_meta_table  = $wpdb->prefix . 'mint_broadcast_email_meta';
				$campaign_meta_table   = $wpdb->prefix . 'mint_campaigns_meta';
				$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';
				$email_builder_table   = $wpdb->prefix . 'mint_campaign_email_builder';

				$broadcast_ids = QueryBuilder::table( $broadcast_table )
				->select( 'id' )
				->whereIn( 'campaign_id', $ids )
				->get();
				$broadcast_ids = array_map( 'intval', array_column( $broadcast_ids, 'id' ) );

				if ( ! empty( $broadcast_ids ) ) {
					  QueryBuilder::table( $broadcast_meta_table )
					 ->whereIn( 'mint_email_id', $broadcast_ids )
					 ->delete();
				}

				QueryBuilder::table( $broadcast_table )
				->whereIn( 'campaign_id', $ids )
				->delete();

				QueryBuilder::table( $campaign_meta_table )
				->whereIn( 'campaign_id', $ids )
				->delete();

				$email_step_ids = QueryBuilder::table( $campaign_emails_table )
				->select( 'id' )
				->whereIn( 'campaign_id', $ids )
				->get();
				$email_step_ids = array_map( 'intval', array_column( $email_step_ids, 'id' ) );

				if ( ! empty( $email_step_ids ) ) {
					QueryBuilder::table( $email_builder_table )
					   ->whereIn( 'email_id', $email_step_ids )
					   ->delete();
				}

				QueryBuilder::table( $campaign_emails_table )
				->whereIn( 'campaign_id', $ids )
				->delete();

				return parent::destroyMany( $ids );
			}
		);
	}

	/**
	 * Fetch email attributes joined with the email builder table.
	 *
	 * Replaces CampaignModel::get_campaign_email_attributes_to_sent().
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id    Campaign email step ID.
	 *
	 * @return array Associative array with campaign_email_id, campaign_id,
	 *               email_subject, email_preview_text, email_builder_id,
	 *               editor_type, email_body. Empty array if not found.
	 *
	 * @since 1.20.0
	 */
	public function getEmailAttributes( int $campaign_id, int $email_id ): array {
		global $wpdb;

		$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';
		$email_builder_table   = $wpdb->prefix . 'mint_campaign_email_builder';

		$row = QueryBuilder::table( $campaign_emails_table . ' AS ce' )
			->select(
				'ce.id AS campaign_email_id', 'ce.campaign_id', 'ce.email_subject', 'ce.email_preview_text',
				'ceb.id AS email_builder_id', 'ceb.editor_type', 'ceb.email_body'
			)
			->innerJoin( $email_builder_table . ' AS ceb', 'ce.id', '=', 'ceb.email_id' )
			->where( 'ce.campaign_id', '=', $campaign_id )
			->where( 'ceb.email_id', '=', $email_id )
			->first();

		return $row ?: array();
	}

	/**
	 * Fetch globally due campaign broadcast recipients in deterministic order.
	 *
	 * Used by the global queue coordinator to process due campaign rows with
	 * a shared sending budget.
	 *
	 * @param int $limit    Maximum number of rows to fetch.
	 * @param int $due_unix Fetch rows with scheduled_at <= this Unix time.
	 *
	 * @return array<int,array<string,mixed>>
	 * @since 1.20.0
	 */
	public function getGlobalDueDispatchCandidates( int $limit, int $due_unix ): array {
		global $wpdb;

		$limit           = max( 1, $limit );
		$due_local       = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $due_unix ) );
		$broadcast_table = $wpdb->prefix . EmailSchema::$table_name;
		$campaigns_table = $this->prefixedTable();

		$select_columns = array(
			'b.id', 'b.campaign_id', 'b.automation_id', 'b.step_id', 'b.email_id',
			'b.contact_id', 'b.email_address', 'b.email_headers', 'b.email_hash',
			'b.email_type', 'b.scheduled_at', 'c.type AS campaign_type',
		);

		// Check the transition flag. When 1, legacy UTC rows may still exist
		// alongside new local-time rows — use the two-query path to handle both.
		// When 0 (or absent), all rows use local time — use the fast single-query path.
		if ( $this->isTimezoneTransitionActive() ) {
			return $this->getGlobalDueDispatchCandidatesTransition( $limit, $due_unix, $due_local, $broadcast_table, $campaigns_table, $select_columns );
		}

		// Normal path: all scheduled_at values are in local time (post-1.21.3).
		$rows = QueryBuilder::table( $broadcast_table . ' AS b' )
			->select( ...$select_columns )
			->leftJoin( $campaigns_table . ' AS c', 'c.id', '=', 'b.campaign_id' )
			->where( 'b.status', '=', BroadcastStatus::SCHEDULED )
			->where( 'b.campaign_id', '>', 0 )
			->whereIn( 'b.email_type', array( 'campaign', 'regular' ) )
			->where( 'b.scheduled_at', '<=', $due_local )
			->where( 'c.status', '!=', CampaignStatus::PAUSED )
			->where( 'c.status', '!=', CampaignStatus::SUSPENDED )
			->orderBy( 'b.scheduled_at', 'ASC' )
			->limit( $limit )
			->get();

		return $this->sortGlobalDispatchCandidates( is_array( $rows ) ? $rows : array(), $limit );
	}

	/**
	 * Check whether the legacy→new timezone transition is still active.
	 *
	 * Returns true when the wp_options flag `mailmint_tz_transition` is 1,
	 * meaning legacy UTC-timezone broadcast rows may still exist alongside
	 * new local-time rows.
	 *
	 * The flag is set to 1 automatically on plugin update when pending legacy
	 * AS jobs exist. It is cleared to 0 when no more UTC-range scheduled rows
	 * remain in wp_mint_broadcast_emails.
	 *
	 * @since 1.21.3
	 * @return bool
	 */
	private function isTimezoneTransitionActive(): bool {
		$flag = get_option( 'mailmint_tz_transition', null );

		// Flag not set yet — auto-detect on first call.
		if ( null === $flag ) {
			return $this->detectAndSetTimezoneTransitionFlag();
		}

		return '1' === (string) $flag;
	}

	/**
	 * Auto-detect whether a timezone transition is needed and set the flag.
	 *
	 * Checks for pending/in-progress mailmint_send_scheduled_emails AS jobs
	 * (legacy dispatch jobs that write UTC scheduled_at). If any exist, sets
	 * the flag to 1. Otherwise sets it to 0.
	 *
	 * Also clears the flag to 0 when no more UTC-range scheduled broadcast
	 * rows exist (transition complete).
	 *
	 * @since 1.21.3
	 * @return bool True if transition is active.
	 */
	private function detectAndSetTimezoneTransitionFlag(): bool {
		global $wpdb;

		$legacy_hook = defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) ? MAILMINT_SEND_SCHEDULED_EMAILS : 'mailmint_send_scheduled_emails';

		// Check for pending legacy AS jobs — these indicate a mid-transition state.
		$legacy_pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT action_id FROM {$wpdb->prefix}actionscheduler_actions
				WHERE hook = %s AND status IN ('pending', 'in-progress')
				LIMIT 1",
				$legacy_hook
			)
		);

		if ( $legacy_pending ) {
			update_option( 'mailmint_tz_transition', '1', false );
			return true;
		}

		// No legacy jobs — check if any UTC-range scheduled rows still exist.
		// UTC rows have scheduled_at < current local time minus the UTC offset.
		$utc_now   = gmdate( 'Y-m-d H:i:s' );
		$utc_rows  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$wpdb->prefix}mint_broadcast_emails
				WHERE status = 'scheduled' AND campaign_id > 0
				AND scheduled_at <= %s",
				$utc_now
			)
		);

		if ( (int) $utc_rows > 0 ) {
			update_option( 'mailmint_tz_transition', '1', false );
			return true;
		}

		// No legacy jobs, no UTC rows — transition complete.
		update_option( 'mailmint_tz_transition', '0', false );
		return false;
	}

	/**
	 * Transition-mode candidate fetch: handles mixed UTC + local-time rows.
	 *
	 * Runs two queries — one for local-time rows (new code) and one for UTC
	 * rows (legacy code) — then merges and deduplicates. Once all legacy rows
	 * are dispatched, `isTimezoneTransitionActive()` returns false and this
	 * method is no longer called.
	 *
	 * @since 1.21.3
	 */
	private function getGlobalDueDispatchCandidatesTransition( int $limit, int $due_unix, string $due_local, string $broadcast_table, string $campaigns_table, array $select_columns ): array {
		$due_utc = gmdate( 'Y-m-d H:i:s', $due_unix );

		// Query 1: local-time rows (new scheduling code, >= 1.21.3).
		$local_rows = array();
		if ( $due_local !== $due_utc ) {
			$local_rows = QueryBuilder::table( $broadcast_table . ' AS b' )
				->select( ...$select_columns )
				->leftJoin( $campaigns_table . ' AS c', 'c.id', '=', 'b.campaign_id' )
				->where( 'b.status', '=', BroadcastStatus::SCHEDULED )
				->where( 'b.campaign_id', '>', 0 )
				->whereIn( 'b.email_type', array( 'campaign', 'regular' ) )
				->where( 'b.scheduled_at', '>', $due_utc )
				->where( 'b.scheduled_at', '<=', $due_local )
				->orderBy( 'b.scheduled_at', 'ASC' )
				->limit( $limit )
				->get();
			$local_rows = is_array( $local_rows ) ? $local_rows : array();
		}

		// Query 2: UTC rows (legacy scheduling code, < 1.21.3).
		$utc_rows = QueryBuilder::table( $broadcast_table . ' AS b' )
			->select( ...$select_columns )
			->leftJoin( $campaigns_table . ' AS c', 'c.id', '=', 'b.campaign_id' )
			->where( 'b.status', '=', BroadcastStatus::SCHEDULED )
			->where( 'b.campaign_id', '>', 0 )
			->whereIn( 'b.email_type', array( 'campaign', 'regular' ) )
			->where( 'b.scheduled_at', '<=', $due_utc )
			->orderBy( 'b.scheduled_at', 'ASC' )
			->limit( $limit )
			->get();
		$utc_rows = is_array( $utc_rows ) ? $utc_rows : array();

		// Merge and deduplicate by broadcast row ID.
		$rows = array_merge( $local_rows, $utc_rows );
		$seen = array();
		$rows = array_filter(
			$rows,
			function ( $row ) use ( &$seen ) {
				$id = (int) ( $row['id'] ?? 0 );
				if ( isset( $seen[ $id ] ) ) {
					return false;
				}
				$seen[ $id ] = true;
				return true;
			}
		);

		// Check if transition is now complete — clear the flag if no UTC rows remain.
		if ( empty( $utc_rows ) && empty( $local_rows ) ) {
			update_option( 'mailmint_tz_transition', '0', false );
		} elseif ( empty( $utc_rows ) ) {
			// No more UTC rows — transition complete, switch to normal path next run.
			update_option( 'mailmint_tz_transition', '0', false );
		}

		return $this->sortGlobalDispatchCandidates( array_values( $rows ), $limit );
	}

	/**
	 * Count globally due campaign broadcast recipients.
	 *
	 * @param int $due_unix Count rows with scheduled_at <= this Unix time.
	 *
	 * @return int
	 * @since 1.20.0
	 */
	public function countGlobalDueDispatchCandidates( int $due_unix ): int {
		global $wpdb;

		$due_local       = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $due_unix ) );
		$broadcast_table = $wpdb->prefix . EmailSchema::$table_name;
		$campaigns_table = $this->prefixedTable();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(b.id)
				 FROM {$broadcast_table} AS b
				 INNER JOIN {$campaigns_table} AS c ON c.id = b.campaign_id
				 WHERE b.status = 'scheduled'
				   AND b.campaign_id > 0
				   AND b.email_type IN ('campaign', 'regular', 'automation')
				   AND b.scheduled_at <= %s
				   AND c.status NOT IN ('paused', 'suspended')",
				$due_local
			)
		);
	}

	/**
	 * Return the previous campaign email step ID for each requested campaign step.
	 *
	 * The returned map is keyed as `campaign_id:email_id` and contains the
	 * immediate predecessor step ID or `0` when the requested step is the first
	 * step in the campaign sequence.
	 *
	 * @param array<int,array<string,int>> $campaign_email_pairs Campaign/email pairs.
	 * @return array<string,int>
	 * @since 1.20.0
	 */
	public function getPreviousCampaignEmailMap( array $campaign_email_pairs ): array {
		$pairs = array();

		foreach ( $campaign_email_pairs as $pair ) {
			$campaign_id = ! empty( $pair['campaign_id'] ) ? (int) $pair['campaign_id'] : 0;
			$email_id    = ! empty( $pair['email_id'] ) ? (int) $pair['email_id'] : 0;

			if ( $campaign_id < 1 || $email_id < 1 ) {
				continue;
			}

			$pairs[ $campaign_id . ':' . $email_id ] = array(
				'campaign_id' => $campaign_id,
				'email_id'    => $email_id,
			);
		}

		if ( empty( $pairs ) ) {
			return array();
		}

		$campaign_ids = array_values( array_unique( array_map( 'intval', array_column( $pairs, 'campaign_id' ) ) ) );
		$rows         = QueryBuilder::table( $this->getCampaignEmailsTable() )
			->select( 'id', 'campaign_id' )
			->whereIn( 'campaign_id', $campaign_ids )
			->get();

		$steps_by_campaign = array();
		foreach ( $rows as $row ) {
			$campaign_id = ! empty( $row['campaign_id'] ) ? (int) $row['campaign_id'] : 0;
			$step_id     = ! empty( $row['id'] ) ? (int) $row['id'] : 0;

			if ( $campaign_id < 1 || $step_id < 1 ) {
				continue;
			}

			$steps_by_campaign[ $campaign_id ][] = $step_id;
		}

		$previous_map = array();
		foreach ( $steps_by_campaign as $campaign_id => $step_ids ) {
			sort( $step_ids, SORT_NUMERIC );
			$previous_id = 0;

			foreach ( $step_ids as $step_id ) {
				$previous_map[ $campaign_id . ':' . $step_id ] = $previous_id;
				$previous_id                                   = $step_id;
			}
		}

		$result = array();
		foreach ( $pairs as $key => $pair ) {
			$result[ $key ] = isset( $previous_map[ $key ] ) ? (int) $previous_map[ $key ] : 0;
		}

		return $result;
	}

	/**
	 * Check whether a sequence candidate's parent step has already been sent.
	 *
	 * First-step emails return true because no parent gate applies.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $email_id    Current campaign email step ID.
	 * @param int $contact_id  Contact ID.
	 * @return bool
	 * @since 1.20.0
	 */
	public function hasSequenceParentBeenSent( int $campaign_id, int $email_id, int $contact_id ): bool {
		if ( $campaign_id < 1 || $email_id < 1 || $contact_id < 1 ) {
			return false;
		}

		$parent_map       = $this->getPreviousCampaignEmailMap(
			array(
				array(
					'campaign_id' => $campaign_id,
					'email_id'    => $email_id,
				),
			)
		);
		$parent_email_id  = ! empty( $parent_map[ $campaign_id . ':' . $email_id ] ) ? (int) $parent_map[ $campaign_id . ':' . $email_id ] : 0;

		if ( $parent_email_id < 1 ) {
			return true;
		}

		return QueryBuilder::table( $this->getBroadcastEmailsTable() )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'email_id', '=', $parent_email_id )
			->where( 'contact_id', '=', $contact_id )
			->where( 'status', '=', BroadcastStatus::SENT )
->whereIn( 'email_type', array( 'campaign', 'regular' ) )
			->count() > 0;
	}

	/**
	 * Prefetch deterministic parent-gate decisions for candidate rows.
	 *
	 * Sequence child rows are eligible only when the same contact's parent row
	 * is sent and the child's configured delay has elapsed from that parent sent
	 * timestamp. Parent failures default to terminal child failure.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidate rows.
	 * @param int                            $now        Current Unix timestamp.
	 * @return array<int,array<string,mixed>>
	 * @since 1.20.0
	 */
	public function prefetchSequenceParentGateDecisions( array $candidates, int $now ): array {
		$decisions            = array();
		$sequence_candidates  = array();
		$campaign_email_pairs = array();
		$campaign_ids         = array();

		foreach ( $candidates as $candidate ) {
			$candidate_id = ! empty( $candidate['id'] ) ? (int) $candidate['id'] : 0;
			if ( $candidate_id < 1 ) {
				continue;
			}

			$campaign_id = ! empty( $candidate['campaign_id'] ) ? (int) $candidate['campaign_id'] : 0;
			$email_id    = ! empty( $candidate['email_id'] ) ? (int) $candidate['email_id'] : 0;
			$contact_id  = ! empty( $candidate['contact_id'] ) ? (int) $candidate['contact_id'] : 0;

			$decisions[ $candidate_id ] = array(
				'eligible'        => true,
				'terminal_status' => '',
				'reason'          => '',
				'retry_at'        => 0,
			);

			if ( $campaign_id > 0 ) {
				$campaign_ids[] = $campaign_id;
			}

			if ( $campaign_id < 1 || $email_id < 1 || $contact_id < 1 ) {
				$decisions[ $candidate_id ]['eligible'] = false;
				$decisions[ $candidate_id ]['reason']   = 'invalid_candidate';
				continue;
			}

			$sequence_candidates[]  = array(
				'candidate_id'   => $candidate_id,
				'campaign_id'    => $campaign_id,
				'email_id'       => $email_id,
				'contact_id'     => $contact_id,
				'scheduled_at'   => $candidate['scheduled_at'] ?? '',
				'campaign_type'  => $candidate['campaign_type'] ?? '',
			);
			$campaign_email_pairs[] = array(
				'campaign_id' => $campaign_id,
				'email_id'    => $email_id,
			);
		}

		if ( empty( $sequence_candidates ) ) {
			return $decisions;
		}

		if ( ! $this->isSequenceParentGateEnabled() ) {
			return $decisions;
		}

		$campaign_types   = $this->getCampaignTypeMap( $campaign_ids );
		$filtered         = array();
		$filtered_pairs   = array();

		foreach ( $sequence_candidates as $candidate ) {
			$campaign_type = $candidate['campaign_type'] ?: ( $campaign_types[ $candidate['campaign_id'] ] ?? '' );

			if ( CampaignType::SEQUENCE !== $campaign_type ) {
				continue;
			}

			$filtered[]       = $candidate;
			$filtered_pairs[] = array(
				'campaign_id' => $candidate['campaign_id'],
				'email_id'    => $candidate['email_id'],
			);
		}

		if ( empty( $filtered ) ) {
			return $decisions;
		}

		$parent_map       = $this->getPreviousCampaignEmailMap( $filtered_pairs );
		$delay_map        = $this->getCampaignEmailDelaySecondsMap( $filtered_pairs );
		$parent_email_ids = array();
		$sequence_ids     = array();
		$contact_ids      = array();

		foreach ( $filtered as $candidate ) {
			$parent_email_id = ! empty( $parent_map[ $candidate['campaign_id'] . ':' . $candidate['email_id'] ] ) ? (int) $parent_map[ $candidate['campaign_id'] . ':' . $candidate['email_id'] ] : 0;

			if ( $parent_email_id < 1 ) {
				continue;
			}

			$parent_email_ids[] = $parent_email_id;
			$sequence_ids[]     = $candidate['campaign_id'];
			$contact_ids[]      = $candidate['contact_id'];
		}

		$parent_email_ids = array_values( array_unique( array_map( 'intval', $parent_email_ids ) ) );
		$sequence_ids     = array_values( array_unique( array_map( 'intval', $sequence_ids ) ) );
		$contact_ids      = array_values( array_unique( array_map( 'intval', $contact_ids ) ) );

		$parent_lookup = array();
		if ( ! empty( $parent_email_ids ) && ! empty( $sequence_ids ) && ! empty( $contact_ids ) ) {
			$parent_rows = QueryBuilder::table( $this->getBroadcastEmailsTable() )
				->select( 'campaign_id', 'email_id', 'contact_id', 'status', 'updated_at' )
				->whereIn( 'campaign_id', $sequence_ids )
				->whereIn( 'email_id', $parent_email_ids )
				->whereIn( 'contact_id', $contact_ids )
				->whereIn( 'email_type', array( 'campaign', 'regular' ) )
				->get();

			foreach ( $parent_rows as $row ) {
				$key = (int) $row['campaign_id'] . ':' . (int) $row['email_id'] . ':' . (int) $row['contact_id'];
				$parent_lookup[ $key ] = $row;
			}
		}

		foreach ( $filtered as $candidate ) {
			$candidate_id    = $candidate['candidate_id'];
			$parent_email_id = ! empty( $parent_map[ $candidate['campaign_id'] . ':' . $candidate['email_id'] ] ) ? (int) $parent_map[ $candidate['campaign_id'] . ':' . $candidate['email_id'] ] : 0;

			if ( $parent_email_id < 1 ) {
				continue;
			}

			$lookup_key = $candidate['campaign_id'] . ':' . $parent_email_id . ':' . $candidate['contact_id'];
			if ( empty( $parent_lookup[ $lookup_key ] ) ) {
				$decisions[ $candidate_id ]['eligible'] = false;
				$decisions[ $candidate_id ]['reason']   = 'parent_pending';
				$decisions[ $candidate_id ]['retry_at'] = $now + MINUTE_IN_SECONDS;
				continue;
			}

			$parent_row    = $parent_lookup[ $lookup_key ];
			$parent_status = $parent_row['status'] ?? '';

			if ( BroadcastStatus::FAILED === $parent_status ) {
				$decisions[ $candidate_id ]['eligible']        = false;
				$decisions[ $candidate_id ]['terminal_status'] = BroadcastStatus::FAILED;
				$decisions[ $candidate_id ]['reason']          = 'parent_failed';
				continue;
			}

			if ( BroadcastStatus::SENT !== $parent_status ) {
				$decisions[ $candidate_id ]['eligible'] = false;
				$decisions[ $candidate_id ]['reason']   = 'parent_pending';
				$decisions[ $candidate_id ]['retry_at'] = $now + MINUTE_IN_SECONDS;
				continue;
			}

			$parent_sent_unix = $this->mysqlTimestampToUnix( (string) ( $parent_row['updated_at'] ?? '' ) );
			$candidate_due    = $this->mysqlTimestampToUnix( (string) $candidate['scheduled_at'] );
			$delay_seconds    = $delay_map[ $candidate['campaign_id'] . ':' . $candidate['email_id'] ] ?? 0;
			$eligible_at      = max( $candidate_due, $parent_sent_unix + $delay_seconds );

			if ( $eligible_at > $now ) {
				$decisions[ $candidate_id ]['eligible'] = false;
				$decisions[ $candidate_id ]['reason']   = 'delay_wait';
				$decisions[ $candidate_id ]['retry_at'] = $eligible_at;
			}
		}

		return $decisions;
	}

	/**
	 * Prefetch sequence parent gate status for a batch of global candidates.
	 *
	 * Returns a map keyed by broadcast row ID. Non-sequence rows, invalid rows,
	 * and first-step sequence rows default to true so callers can use the map as
	 * a direct eligibility cache.
	 *
	 * @param array<int,array<string,mixed>> $candidates Global queue candidates.
	 * @return array<int,bool>
	 * @since 1.20.0
	 */
	public function prefetchSequenceParentStatusMap( array $candidates ): array {
		$decisions  = $this->prefetchSequenceParentGateDecisions( $candidates, time() );
		$status_map = array();

		foreach ( $decisions as $candidate_id => $decision ) {
			$status_map[ $candidate_id ] = ! empty( $decision['eligible'] );
		}

		return $status_map;
	}

	/**
	 * Atomically claim a globally selected broadcast recipient.
	 *
	 * @param int $broadcast_id Broadcast row ID.
	 *
	 * @return bool True when the row was claimed successfully.
	 * @since 1.20.0
	 */
	public function claimGlobalDispatchCandidate( int $broadcast_id ): bool {
		$updated = QueryBuilder::table( $this->getBroadcastEmailsTable() )
			->where( 'id', '=', $broadcast_id )
			->where( 'status', '=', BroadcastStatus::SCHEDULED )
			->update(
				array(
					'status'     => BroadcastStatus::SENDING,
					'updated_at' => current_time( 'mysql', true ),
				)
			);

		if ( is_wp_error( $updated ) ) {
			return false;
		}

		return 1 === (int) $updated;
	}

	/**
	 * Update the terminal status of a globally dispatched broadcast recipient.
	 *
	 * @param int    $broadcast_id Broadcast row ID.
	 * @param string $status       Terminal broadcast status.
	 *
	 * @return int|\WP_Error
	 * @since 1.20.0
	 */
	public function updateGlobalDispatchCandidateStatus( int $broadcast_id, string $status ) {
		if ( ! BroadcastStatus::isValid( $status ) ) {
			return new \WP_Error(
				'invalid_broadcast_status',
				sprintf( __( 'Invalid broadcast status: %s', 'mrm' ), $status )
			);
		}

		return QueryBuilder::table( $this->getBroadcastEmailsTable() )
			->where( 'id', '=', $broadcast_id )
			->where( 'status', '=', BroadcastStatus::SENDING )
			->update(
				array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql', true ),
				)
			);
	}

	/**
	 * Check whether pending broadcast rows remain for a specific campaign step.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $email_id    Campaign email step ID.
	 *
	 * @return bool
	 * @since 1.20.0
	 */
	public function hasPendingBroadcastRecipientsForCampaignEmail( int $campaign_id, string $email_id ): bool {
		return QueryBuilder::table( $this->getBroadcastEmailsTable() )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'email_id', '=', $email_id )
			->whereIn( 'email_type', array( 'campaign', 'regular', 'automation' ) )
			->whereIn( 'status', array( BroadcastStatus::SCHEDULED, BroadcastStatus::SENDING ) )
			->count() > 0;
	}

	/**
	 * Return the prefixed campaign step table name.
	 *
	 * @return string
	 * @since 1.20.0
	 */
	private function getCampaignEmailsTable(): string {
		global $wpdb;

		return $wpdb->prefix . 'mint_campaign_emails';
	}

	/**
	 * Return the prefixed broadcast email table name.
	 *
	 * @return string
	 * @since 1.20.0
	 */
	private function getBroadcastEmailsTable(): string {
		global $wpdb;

		return $wpdb->prefix . EmailSchema::$table_name;
	}

	/**
	 * Check whether deterministic sequence parent gating is enabled.
	 *
	 * @return bool
	 * @since 1.20.0
	 */
	private function isSequenceParentGateEnabled(): bool {
		return (bool) apply_filters( 'mailmint_enable_sequence_parent_gate', false );
	}

	/**
	 * Return a map of campaign IDs to campaign type values.
	 *
	 * @param array<int> $campaign_ids Campaign IDs.
	 * @return array<int,string>
	 * @since 1.20.0
	 */
	private function getCampaignTypeMap( array $campaign_ids ): array {
		$campaign_ids = array_values( array_unique( array_filter( array_map( 'intval', $campaign_ids ) ) ) );

		if ( empty( $campaign_ids ) ) {
			return array();
		}

		$rows = QueryBuilder::table( $this->prefixedTable() )
			->select( 'id', 'type' )
			->whereIn( 'id', $campaign_ids )
			->get();

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row['id'] ] = (string) $row['type'];
		}

		return $map;
	}

	/**
	 * Return delay seconds keyed by `campaign_id:email_id`.
	 *
	 * @param array<int,array<string,int>> $campaign_email_pairs Campaign/email pairs.
	 * @return array<string,int>
	 * @since 1.20.0
	 */
	private function getCampaignEmailDelaySecondsMap( array $campaign_email_pairs ): array {
		$pairs = array();

		foreach ( $campaign_email_pairs as $pair ) {
			$campaign_id = ! empty( $pair['campaign_id'] ) ? (int) $pair['campaign_id'] : 0;
			$email_id    = ! empty( $pair['email_id'] ) ? (int) $pair['email_id'] : 0;

			if ( $campaign_id < 1 || $email_id < 1 ) {
				continue;
			}

			$pairs[ $campaign_id . ':' . $email_id ] = true;
		}

		if ( empty( $pairs ) ) {
			return array();
		}

		$campaign_ids = array_values(
			array_unique(
				array_map(
					'intval',
					array_map(
						function ( string $key ): int {
							$parts = explode( ':', $key );
							return (int) $parts[0];
						},
						array_keys( $pairs )
					)
				)
			)
		);

		$rows = QueryBuilder::table( $this->getCampaignEmailsTable() )
			->select( 'id', 'campaign_id', 'delay_count', 'delay_value' )
			->whereIn( 'campaign_id', $campaign_ids )
			->get();

		$delay_map = array();
		foreach ( $rows as $row ) {
			$key = (int) $row['campaign_id'] . ':' . (int) $row['id'];

			if ( ! isset( $pairs[ $key ] ) ) {
				continue;
			}

			$delay_map[ $key ] = $this->calculateDelaySeconds( (int) ( $row['delay_count'] ?? 0 ), (string) ( $row['delay_value'] ?? '' ) );
		}

		return $delay_map;
	}

	/**
	 * Convert a stored delay pair into seconds.
	 *
	 * @param int    $delay_count Delay count.
	 * @param string $delay_value Delay unit.
	 * @return int
	 * @since 1.20.0
	 */
	private function calculateDelaySeconds( int $delay_count, string $delay_value ): int {
		if ( $delay_count < 1 || '' === trim( $delay_value ) ) {
			return 0;
		}

		$normalized = strtolower( trim( $delay_value ) );
		$base_time  = new \DateTimeImmutable( '@0' );
		$target     = $base_time->modify( '+' . $delay_count . ' ' . rtrim( $normalized, 's' ) );

		return $target instanceof \DateTimeImmutable ? max( 0, $target->getTimestamp() ) : 0;
	}

	/**
	 * Normalize a MySQL datetime string into a Unix timestamp.
	 *
	 * @param string $value MySQL datetime string.
	 * @return int
	 * @since 1.20.0
	 */
	private function mysqlTimestampToUnix( string $value ): int {
		if ( '' === trim( $value ) ) {
			return 0;
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) );

		if ( $date instanceof \DateTimeImmutable ) {
			return $date->getTimestamp();
		}

		$timestamp = strtotime( $value );

		return false !== $timestamp ? (int) $timestamp : 0;
	}

	/**
	 * Apply deterministic tie-break ordering for globally due candidates.
	 *
	 * QueryBuilder currently supports a single ORDER BY, so campaign/email/id
	 * tie-breaks are normalized in PHP before returning the final batch.
	 *
	 * @param array<int,array<string,mixed>> $rows  Candidate rows.
	 * @param int                            $limit Maximum rows to return.
	 * @return array<int,array<string,mixed>>
	 * @since 1.20.0
	 */
	private function sortGlobalDispatchCandidates( array $rows, int $limit ): array {
		// Step 1: Sort all rows by scheduled_at ASC (oldest due first), then by
		// campaign_id ASC and id ASC as stable tiebreakers within the same timestamp.
		usort(
			$rows,
			function ( array $left, array $right ): int {
				$scheduled_compare = strcmp( (string) ( $left['scheduled_at'] ?? '' ), (string) ( $right['scheduled_at'] ?? '' ) );

				if ( 0 !== $scheduled_compare ) {
					return $scheduled_compare;
				}

				$campaign_compare = (int) ( $left['campaign_id'] ?? 0 ) <=> (int) ( $right['campaign_id'] ?? 0 );
				if ( 0 !== $campaign_compare ) {
					return $campaign_compare;
				}

				$email_compare = (int) ( $left['email_id'] ?? 0 ) <=> (int) ( $right['email_id'] ?? 0 );
				if ( 0 !== $email_compare ) {
					return $email_compare;
				}

				return (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 );
			}
		);

		// Step 2: Round-robin interleave by campaign_id+email_id so the budget is
		// distributed fairly across all active campaign email steps rather than one
		// campaign or email step consuming all slots.
		$groups = array();
		foreach ( $rows as $row ) {
			$campaign_id = (int) ( $row['campaign_id'] ?? 0 );
			$email_id    = (int) ( $row['email_id'] ?? 0 );
			$group_key   = $campaign_id . ':' . $email_id;
			$groups[ $group_key ][] = $row;
		}

		$result   = array();
		$pointers = array_fill_keys( array_keys( $groups ), 0 );

		while ( count( $result ) < $limit ) {
			$added_this_round = 0;

			foreach ( $groups as $campaign_id => $campaign_rows ) {
				if ( count( $result ) >= $limit ) {
					break;
				}

				$pointer = $pointers[ $campaign_id ];
				if ( $pointer >= count( $campaign_rows ) ) {
					continue;
				}

				$result[]                  = $campaign_rows[ $pointer ];
				$pointers[ $campaign_id ]  = $pointer + 1;
				++$added_this_round;
			}

			// All groups exhausted before reaching $limit — stop.
			if ( 0 === $added_this_round ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Get the latest scheduled slot timestamp from pending email rows.
	 *
	 * Looks up the frontier among queued/sending rows at or after the provided
	 * base UNIX timestamp. If no row exists in that window, returns $from_unix.
	 *
	 * @param int $from_unix Base UNIX timestamp for the lookup window.
	 *
	 * @return int Latest pending slot UNIX timestamp, or $from_unix when none exists.
	 * @since 1.20.0
	 */
	public function getSchedulingFrontier( int $from_unix ): int {
		global $wpdb;

		$from_mysql      = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $from_unix ) );
		$broadcast_table = $wpdb->prefix . EmailSchema::$table_name;
		$campaigns_table = $this->prefixedTable();

		// Exclude rows from paused/suspended campaigns so a paused campaign's
		// future-scheduled rows do not push new campaign rows far into the future.
		// Rows with campaign_id = 0 (e.g. automation previews) are kept via the
		// LEFT JOIN NULL check.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MAX(b.scheduled_at) AS frontier
				 FROM {$broadcast_table} AS b
				 LEFT JOIN {$campaigns_table} AS c ON c.id = b.campaign_id
				 WHERE b.status IN ('scheduled', 'sending')
				   AND b.scheduled_at >= %s
				   AND (b.campaign_id = 0 OR c.id IS NULL OR c.status NOT IN ('paused', 'suspended'))",
				$from_mysql
			),
			ARRAY_A
		);

		// Convert local-time string back to UTC Unix — UNIX_TIMESTAMP() is avoided
		// because MySQL interprets the local-time value as server time (UTC), producing
		// a timestamp offset by the site's UTC offset on non-UTC WordPress installs.
		// The offset is derived from the already-computed $from_mysql / $from_unix pair
		// so no additional WordPress functions are needed.
		$frontier_local = $row ? (string) ( $row['frontier'] ?? '' ) : '';
		if ( empty( $frontier_local ) ) {
			return $from_unix;
		}

		$offset_seconds = $from_unix - (int) strtotime( $from_mysql );
		$frontier_unix  = (int) strtotime( $frontier_local ) + $offset_seconds;

		return $frontier_unix > 0 ? $frontier_unix : $from_unix;
	}

	/**
	 * Schedule a delayed send-email Action Scheduler action.
	 *
	 * Wraps as_schedule_single_action() with standard group naming
	 * and dedup guard. Only stores lightweight identifiers in the AS args;
	 * email content is cached via transients by the caller.
	 *
	 * Replaces CampaignModel::schedule_single_send_email_action_delay().
	 *
	 * @param int $campaign_id   Campaign ID.
	 * @param int $email_id      Campaign email step ID.
	 * @param int $batch         Batch number for the next job.
	 * @param int $delay_seconds Delay in seconds before the action fires.
	 *
	 * @since 1.20.0
	 */
	public function scheduleDelayedSendAction( int $campaign_id, int $email_id, int $batch, int $delay_seconds ): void {
		if ( ! $campaign_id ) {
			return;
		}

		$args  = array(
			array(
				'campaign_id' => $campaign_id,
				'email_id'    => $email_id,
				'batch'       => $batch,
			),
		);
		$group = 'mailmint-campaign-email-sending-' . $campaign_id;

		// Check if ANY action exists for this campaign/email (regardless of batch number).
		// We only check campaign_id and email_id to avoid race conditions where the batch
		// number changes between checks, which would cause the campaign to get stuck.
		$check_args = array(
			array(
				'campaign_id' => $campaign_id,
				'email_id'    => $email_id,
			),
		);

		if ( defined( 'MAILMINT_SEND_SCHEDULED_EMAILS' ) && ! $this->hasScheduledSendAction( $check_args, $group ) ) {
			as_schedule_single_action( time() + $delay_seconds, MAILMINT_SEND_SCHEDULED_EMAILS, $args, $group );
		}
	}

	/**
	 * Check if a send action is already scheduled for the given campaign/email.
	 *
	 * This method checks if ANY action exists with matching campaign_id and email_id,
	 * ignoring the batch parameter to prevent race conditions where batch numbers
	 * change between checks.
	 *
	 * @param array  $args  Arguments to check (should contain campaign_id and email_id).
	 * @param string $group Action Scheduler group name.
	 *
	 * @return bool True if an action is already scheduled, false otherwise.
	 * @since 1.20.0
	 */
	private function hasScheduledSendAction( array $args, string $group ): bool {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return false;
		}

		// Get all pending/in-progress actions for this group.
		$actions = as_get_scheduled_actions(
			array(
				'hook'   => MAILMINT_SEND_SCHEDULED_EMAILS,
				'group'  => $group,
				'status' => array( 'pending', 'in-progress' ),
			),
			'ids'
		);

		if ( empty( $actions ) ) {
			return false;
		}

		// Extract the campaign_id and email_id we're looking for.
		$target_campaign_id = ! empty( $args[0]['campaign_id'] ) ? (int) $args[0]['campaign_id'] : 0;
		$target_email_id    = ! empty( $args[0]['email_id'] ) ? (int) $args[0]['email_id'] : 0;

		// Check if any action matches our campaign_id and email_id (ignoring batch).
		foreach ( $actions as $action_id ) {
			$action = \ActionScheduler::store()->fetch_action( $action_id );
			if ( ! $action ) {
				continue;
			}

			$action_args        = $action->get_args();
			$action_campaign_id = ! empty( $action_args[0]['campaign_id'] ) ? (int) $action_args[0]['campaign_id'] : 0;
			$action_email_id    = ! empty( $action_args[0]['email_id'] ) ? (int) $action_args[0]['email_id'] : 0;

			if ( $action_campaign_id === $target_campaign_id && $action_email_id === $target_email_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return the earliest scheduled_at Unix timestamp of pending broadcast rows
	 * that are not yet due (scheduled_at > $after_unix).
	 *
	 * Used by the global coordinator to schedule continuation when no rows are
	 * currently due but future spread slots exist.
	 *
	 * @param int $after_unix Unix timestamp lower bound (exclusive).
	 *
	 * @return int Unix timestamp of the earliest future pending row, or 0 if none.
	 * @since 1.20.0
	 */
	public function getEarliestPendingBroadcastScheduledAt( int $after_unix ): int {
		global $wpdb;

		$after_mysql     = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $after_unix ) );
		$broadcast_table = $wpdb->prefix . EmailSchema::$table_name;
		$campaigns_table = $this->prefixedTable();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MIN(b.scheduled_at) AS earliest
				 FROM {$broadcast_table} AS b
				 INNER JOIN {$campaigns_table} AS c ON c.id = b.campaign_id
				 WHERE b.status = 'scheduled'
				   AND b.campaign_id > 0
				   AND b.email_type IN ('campaign', 'regular')
				   AND b.scheduled_at > %s
				   AND c.status NOT IN ('paused', 'suspended')",
				$after_mysql
			),
			ARRAY_A
		);

		$earliest_local = ! empty( $row['earliest'] ) ? (string) $row['earliest'] : '';
		if ( empty( $earliest_local ) ) {
			return 0;
		}

		// Derive the UTC offset from the already-computed $after_mysql / $after_unix pair
		// to avoid UNIX_TIMESTAMP() which treats local-time strings as MySQL server time (UTC).
		$offset_seconds = $after_unix - (int) strtotime( $after_mysql );
		return (int) strtotime( $earliest_local ) + $offset_seconds;
	}
}

