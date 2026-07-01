<?php
/**
 * BroadcastRepository — SOLID repository for broadcast emails.
 *
 * Replaces legacy EmailModel static methods for broadcast email CRUD,
 * batch insert, meta operations, analytics aggregations, contact-scoped
 * queries, and stuck-email recovery with a clean, testable repository
 * extending AbstractRepository.
 *
 * @package Mint\MRM\Database\Repositories
 * @since   1.20.0
 */

namespace Mint\MRM\Database\Repositories;

use Mint\MRM\Database\AbstractRepository;
use Mint\MRM\Database\QueryBuilder;
use Mint\MRM\Database\Enums\BroadcastStatus;

/**
 * Class BroadcastRepository
 *
 * @since 1.20.0
 */
class BroadcastRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.20.0
	 */
	protected function tableName(): string {
		return 'mint_broadcast_emails';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.20.0
	 */
	protected function fillable(): array {
		return array(
			'campaign_id',
			'automation_id',
			'step_id',
			'email_id',
			'contact_id',
			'email_type',
			'email_address',
			'email_hash',
			'email_headers',
			'status',
			'scheduled_at',
		);
	}

	/**
	 * Insert broadcast recipient rows in 500-row chunks.
	 *
	 * Sets `created_at` on each row before insertion and delegates
	 * to QueryBuilder::batchInsert() for chunked bulk inserts.
	 *
	 * @since 1.20.0
	 *
	 * @param array $rows Array of associative arrays with broadcast email data.
	 * @return int|\WP_Error Total rows inserted, or WP_Error on failure.
	 */
	public function createBroadcastRecipients( array $rows ) {
		if ( empty( $rows ) ) {
			return 0;
		}

		$now = current_time( 'mysql' );

		foreach ( $rows as &$row ) {
			$row['created_at'] = $now;
		}
		unset( $row );

		return QueryBuilder::table( $this->prefixedTable() )->batchInsert( $rows, 500 );
	}

	/**
	 * Get the fully-prefixed broadcast email meta table name.
	 *
	 * @since 1.20.0
	 *
	 * @return string
	 */
	private function metaTable(): string {
		global $wpdb;
		return $wpdb->prefix . 'mint_broadcast_email_meta';
	}

	/**
	 * Insert a broadcast email meta row.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $email_id Broadcast email ID.
	 * @param string $key      Meta key.
	 * @param mixed  $value    Meta value.
	 * @return int Inserted row ID.
	 */
	public function insertMeta( int $email_id, string $key, $value ): int {
		return QueryBuilder::table( $this->metaTable() )
			->insert(
				array(
					'mint_email_id' => $email_id,
					'meta_key'      => $key,
					'meta_value'    => $value,
				)
			);
	}

	/**
	 * Update a broadcast email meta value.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $email_id Broadcast email ID.
	 * @param string $key      Meta key.
	 * @param mixed  $value    New meta value.
	 * @return int|false Affected rows, or false on error.
	 */
	public function updateMeta( int $email_id, string $key, $value ) {
		$result = QueryBuilder::table( $this->metaTable() )
			->where( 'mint_email_id', '=', $email_id )
			->where( 'meta_key', '=', $key )
			->update(
				array(
					'meta_value' => $value,
				)
			);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return $result;
	}

	/**
	 * Get a broadcast email meta value.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $email_id Broadcast email ID.
	 * @param string $key      Meta key.
	 * @return string|false Meta value, or false if not found.
	 */
	public function getMeta( int $email_id, string $key ) {
		$row = QueryBuilder::table( $this->metaTable() )
			->where( 'mint_email_id', '=', $email_id )
			->where( 'meta_key', '=', $key )
			->first();

		if ( empty( $row ) || ! isset( $row['meta_value'] ) ) {
			return false;
		}

		return $row['meta_value'];
	}

	/**
	 * Insert or update a broadcast email meta value (upsert).
	 *
	 * @since 1.20.0
	 *
	 * @param int    $email_id Broadcast email ID.
	 * @param string $key      Meta key.
	 * @param mixed  $value    Meta value.
	 * @return int|false Inserted ID or affected rows, or false on error.
	 */
	public function insertOrUpdateMeta( int $email_id, string $key, $value ) {
		$existing = $this->getMeta( $email_id, $key );

		if ( false === $existing ) {
			return $this->insertMeta( $email_id, $key, $value );
		}

		return $this->updateMeta( $email_id, $key, $value );
	}

	/**
	 * Count broadcast emails matching a given status for a specific email step.
	 *
	 * Replaces legacy `EmailModel::count_delivered_status()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $email_id Broadcast email step ID (the `email_id` column).
	 * @param string $status   Status to count (e.g. BroadcastStatus::SENT).
	 * @return int Count of matching rows.
	 */
	public function countDeliveredStatus( int $email_id, string $status ): int {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'email_id', '=', $email_id )
			->where( 'status', '=', $status )
			->count();
	}

	/**
	 * Count broadcast emails matching a given status for a campaign.
	 *
	 * Replaces legacy `EmailModel::count_delivered_status_on_campaign()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int        $campaign_id Campaign ID.
	 * @param string     $status      Status to count (e.g. BroadcastStatus::SENT).
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime] to restrict by created_at.
	 * @param string     $search      Optional email address search filter.
	 * @param int        $email_id    Optional occurrence (campaign email) ID. When > 0, narrows the
	 *                                count to a single recurring run; 0 keeps the campaign-wide scope.
	 * @return int Count of matching rows.
	 */
	public function countDeliveredStatusOnCampaign( int $campaign_id, string $status, ?array $date_range = null, string $search = '', int $email_id = 0 ): int {
		$qb = QueryBuilder::table( $this->prefixedTable() )
			->where( 'campaign_id', '=', $campaign_id )
			->where( 'status', '=', $status );

		if ( $email_id > 0 ) {
			$qb = $qb->where( 'email_id', '=', $email_id );
		}

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$qb = $qb->where( 'created_at', '>=', $date_range['start'] )
					->where( 'created_at', '<=', $date_range['end'] );
		}

		if ( '' !== $search ) {
			$qb = $qb->where( 'email_address', 'LIKE', '%' . $search . '%' );
		}

		return $qb->count();
	}

	/**
	 * Count opened emails for a campaign via LEFT JOIN to broadcast email meta.
	 *
	 * Replaces legacy `EmailModel::calculate_open_rate_on_campaign()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int        $campaign_id Campaign ID.
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime] to restrict by meta created_at.
	 * @param string     $search      Optional email address search filter.
	 * @param int        $email_id    Optional occurrence (campaign email) ID. When > 0, narrows the
	 *                                count to a single recurring run; 0 keeps the campaign-wide scope.
	 * @return int Count of broadcast emails with `is_open = 1` meta.
	 */
	public function calculateOpenRateOnCampaign( int $campaign_id, ?array $date_range = null, string $search = '', int $email_id = 0 ): int {
		global $wpdb;

		$broadcast = $this->prefixedTable();
		$qb        = QueryBuilder::table( $broadcast )
			->leftJoin( $this->metaTable() . ' AS meta', 'meta.mint_email_id', '=', $broadcast . '.id' )
			->where( $broadcast . '.campaign_id', '=', $campaign_id )
			->where( 'meta.meta_key', '=', 'is_open' )
			->where( 'meta.meta_value', '=', '1' );

		if ( $email_id > 0 ) {
			$qb = $qb->where( $broadcast . '.email_id', '=', $email_id );
		}

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$qb = $qb->where( 'meta.created_at', '>=', $date_range['start'] )
					->where( 'meta.created_at', '<=', $date_range['end'] );
		}

		if ( '' !== $search ) {
			$qb = $qb->where( $broadcast . '.email_address', 'LIKE', '%' . $search . '%' );
		}

		$tracked = $qb->count();

		// Anon pixel counts are not timestamped so only include them on all-time queries.
		// Skip anon counts when searching, as they are not tied to an email address.
		// Anon counts are campaign-level (no email_id dimension), so they are also
		// skipped for a per-occurrence (recurring run) scope to avoid double-attributing
		// a campaign-wide tally to one run.
		if ( empty( $date_range ) && '' === $search && 0 === $email_id ) {
			$campaign_meta_table = $wpdb->prefix . 'mint_campaigns_meta';
			$anon                = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$campaign_meta_table} WHERE campaign_id = %d AND meta_key = '_anon_open_count'", $campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tracked            += $anon;
		}

		return $tracked;
	}

	/**
	 * Count clicked emails for a campaign via LEFT JOIN to broadcast email meta.
	 *
	 * Replaces legacy `EmailModel::calculate_click_rate_on_campaign()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int        $campaign_id Campaign ID.
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime] to restrict by meta created_at.
	 * @param string     $search      Optional email address search filter.
	 * @param int        $email_id    Optional occurrence (campaign email) ID. When > 0, narrows the
	 *                                count to a single recurring run; 0 keeps the campaign-wide scope.
	 * @return int Count of broadcast emails with `is_click = 1` meta.
	 */
	public function calculateClickRateOnCampaign( int $campaign_id, ?array $date_range = null, string $search = '', int $email_id = 0 ): int {
		global $wpdb;

		$broadcast = $this->prefixedTable();
		$qb        = QueryBuilder::table( $broadcast )
			->leftJoin( $this->metaTable() . ' AS meta', 'meta.mint_email_id', '=', $broadcast . '.id' )
			->where( $broadcast . '.campaign_id', '=', $campaign_id )
			->where( 'meta.meta_key', '=', 'is_click' )
			->where( 'meta.meta_value', '=', '1' );

		if ( $email_id > 0 ) {
			$qb = $qb->where( $broadcast . '.email_id', '=', $email_id );
		}

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$qb = $qb->where( 'meta.created_at', '>=', $date_range['start'] )
					->where( 'meta.created_at', '<=', $date_range['end'] );
		}

		if ( '' !== $search ) {
			$qb = $qb->where( $broadcast . '.email_address', 'LIKE', '%' . $search . '%' );
		}

		$tracked = $qb->count();

		// Anon click counts are not timestamped so only include them on all-time queries.
		// Skip anon counts when searching, as they are not tied to an email address.
		// Anon counts are campaign-level (no email_id dimension), so they are also
		// skipped for a per-occurrence (recurring run) scope to avoid double-attributing
		// a campaign-wide tally to one run.
		if ( empty( $date_range ) && '' === $search && 0 === $email_id ) {
			$campaign_meta_table = $wpdb->prefix . 'mint_campaigns_meta';
			$anon                = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$campaign_meta_table} WHERE campaign_id = %d AND meta_key = '_anon_click_count'", $campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$tracked            += $anon;
		}

		return $tracked;
	}

	/**
	 * Count unsubscribes for a campaign via LEFT JOIN to broadcast email meta.
	 *
	 * Replaces legacy `EmailModel::count_unsubscribe_on_campaign()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int        $campaign_id Campaign ID.
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime] to restrict by meta created_at.
	 * @param string     $search      Optional email address search filter.
	 * @param int        $email_id    Optional occurrence (campaign email) ID. When > 0, narrows the
	 *                                count to a single recurring run; 0 keeps the campaign-wide scope.
	 * @return int Count of broadcast emails with `is_unsubscribe = 1` meta.
	 */
	public function countUnsubscribeOnCampaign( int $campaign_id, ?array $date_range = null, string $search = '', int $email_id = 0 ): int {
		$broadcast = $this->prefixedTable();

		$qb = QueryBuilder::table( $broadcast )
			->leftJoin( $this->metaTable() . ' AS meta', 'meta.mint_email_id', '=', $broadcast . '.id' )
			->where( $broadcast . '.campaign_id', '=', $campaign_id )
			->where( 'meta.meta_key', '=', 'is_unsubscribe' )
			->where( 'meta.meta_value', '=', '1' );

		if ( $email_id > 0 ) {
			$qb = $qb->where( $broadcast . '.email_id', '=', $email_id );
		}

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$qb = $qb->where( 'meta.created_at', '>=', $date_range['start'] )
					->where( 'meta.created_at', '<=', $date_range['end'] );
		}

		if ( '' !== $search ) {
			$qb = $qb->where( $broadcast . '.email_address', 'LIKE', '%' . $search . '%' );
		}

		return $qb->count();
	}

	/**
	 * Get unsubscribe reason counts for a campaign, keyed by reason slug.
	 *
	 * Queries broadcast_email_meta for meta_key='unsubscribe_reason' joined to
	 * broadcast emails belonging to the given campaign.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<string,int> Map of reason slug → count. Empty array when no reasons recorded.
	 */
	public function getUnsubscribeReasonCountsForCampaign( int $campaign_id ): array {
		global $wpdb;
		$broadcast = $this->prefixedTable();
		$meta      = $this->metaTable();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( // db call ok. ; no-cache ok.
			$wpdb->prepare(
				"SELECT meta.meta_value AS reason, COUNT(*) AS total
				FROM {$broadcast} AS be
				INNER JOIN {$meta} AS meta ON meta.mint_email_id = be.id AND meta.meta_key = 'unsubscribe_reason'
				WHERE be.campaign_id = %d
				GROUP BY meta.meta_value",
				$campaign_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $rows ) ) {
			return array();
		}

		$result = array();
		foreach ( $rows as $row ) {
			$result[ $row['reason'] ] = (int) $row['total'];
		}
		return $result;
	}

	/**
	 * Count contacts who left written feedback (unsubscribe_reason_text) for a campaign.
	 *
	 * @since 1.26.0
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int Number of broadcast emails that have an unsubscribe_reason_text meta entry.
	 */
	public function countUnsubscribeReasonTextOnCampaign( int $campaign_id ): int {
		global $wpdb;
		$broadcast = $this->prefixedTable();
		$meta      = $this->metaTable();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( // db call ok. ; no-cache ok.
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$broadcast} AS be
				INNER JOIN {$meta} AS meta ON meta.mint_email_id = be.id AND meta.meta_key = 'unsubscribe_reason_text'
				WHERE be.campaign_id = %d",
				$campaign_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return $count;
	}

	/**
	 * Get broadcast emails sent to a specific contact with pagination.
	 *
	 * Replaces legacy `EmailModel::get_broadcast_email_ids_to_contact()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int $contact_id Contact ID.
	 * @param int $offset     Pagination offset. Default 0.
	 * @param int $limit      Maximum rows to return. Default 10.
	 * @return array Array of broadcast email rows.
	 */
	public function getBroadcastEmailsToContact( int $contact_id, int $offset = 0, int $limit = 10 ): array {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'contact_id', '=', $contact_id )
			->orderBy( 'id', 'DESC' )
			->limit( $limit )
			->offset( $offset )
			->get();
	}

	/**
	 * Get the total count of broadcast emails sent to a specific contact.
	 *
	 * Replaces legacy `EmailModel::total_broadcast_email_ids_to_contact()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int $contact_id Contact ID.
	 * @return int Total count of broadcast emails for the contact.
	 */
	public function getTotalBroadcastEmailsToContact( int $contact_id ): int {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'contact_id', '=', $contact_id )
			->count();
	}

	/**
	 * Delete a single broadcast email and its meta for a specific contact.
	 *
	 * Replaces legacy `EmailModel::delete_broadcast_email_by_contact_id()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int $email_id   Broadcast email ID.
	 * @param int $contact_id Contact ID.
	 * @return int Affected rows from the broadcast email deletion.
	 */
	public function deleteBroadcastEmailByContact( int $email_id, int $contact_id ): int {
		// Delete associated meta first.
		QueryBuilder::table( $this->metaTable() )
			->where( 'mint_email_id', '=', $email_id )
			->delete();

		// Delete the broadcast email row scoped to the contact.
		$result = QueryBuilder::table( $this->prefixedTable() )
			->where( 'id', '=', $email_id )
			->where( 'contact_id', '=', $contact_id )
			->delete();

		return is_wp_error( $result ) ? 0 : (int) $result;
	}

	/**
	 * Delete multiple broadcast emails and their meta for a specific contact.
	 *
	 * Replaces legacy `EmailModel::delete_multiple_broadcast_email_by_contact_id()`.
	 *
	 * @since 1.20.0
	 *
	 * @param array $email_ids  Array of broadcast email IDs.
	 * @param int   $contact_id Contact ID.
	 * @return int Affected rows from the broadcast email deletion.
	 */
	public function deleteMultipleBroadcastEmailsByContact( array $email_ids, int $contact_id ): int {
		if ( empty( $email_ids ) ) {
			return 0;
		}

		$email_ids = array_map( 'intval', $email_ids );

		foreach ( $email_ids as $email_id ) {
			QueryBuilder::table( $this->metaTable() )
				->where( 'mint_email_id', '=', $email_id )
				->delete();
		}

		$result = QueryBuilder::table( $this->prefixedTable() )
			->whereIn( 'id', $email_ids )
			->delete();

		return is_wp_error( $result ) ? 0 : (int) $result;
	}

	/**
	 * Get the total number of distinct recipients for a recurring campaign.
	 *
	 * Counts DISTINCT contact_id values in the broadcast table for the given
	 * campaign, so contacts receiving multiple emails are counted once.
	 *
	 * Replaces legacy `EmailModel::recurring_campaign_total_recipients()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id The recurring campaign ID.
	 * @return int The count of distinct recipients.
	 */
	public function getRecurringCampaignTotalRecipients( int $campaign_id ): int {
		$row = QueryBuilder::table( $this->prefixedTable() )
			->select( 'COUNT(DISTINCT contact_id) as cnt' )
			->where( 'campaign_id', '=', $campaign_id )
			->first();

		return $row ? (int) $row['cnt'] : 0;
	}

	/**
	 * Count broadcast rows queued for a single recurring occurrence (email_id).
	 *
	 * Returns the number of broadcast rows for one occurrence's email step. This
	 * is the correct denominator for that occurrence's delivered/open/click
	 * percentages, because it shares the same per-occurrence scope as the
	 * delivered count — unlike the campaign-wide DISTINCT-contact reach, which
	 * accumulates across runs and would push a multi-run rate above 100%.
	 *
	 * @since 1.24.0
	 *
	 * @param int|string $email_id The campaign email (occurrence) ID.
	 * @return int The count of broadcast rows for that occurrence.
	 */
	public function getRecurringOccurrenceRecipients( $email_id ): int {
		if ( empty( $email_id ) ) {
			return 0;
		}

		$row = QueryBuilder::table( $this->prefixedTable() )
			->select( 'COUNT(*) as cnt' )
			->where( 'email_id', '=', $email_id )
			->first();

		return $row ? (int) $row['cnt'] : 0;
	}

	/**
	 * Per-occurrence run history for a recurring campaign (real runs only).
	 *
	 * Groups broadcast rows by `email_id` (one occurrence = one cloned email
	 * step) and returns ONLY discrete occurrences — those whose cloned step
	 * carries the `recurring_run` meta (INNER JOIN). Pre-upgrade and clone-failed
	 * sends share the untouched template `email_id` and carry no such meta, so
	 * they are excluded here and collapsed separately by getRecurringLegacyBucket()
	 * into the single bottom-pinned "Earlier runs" entry.
	 *
	 * The row's identity (subject), its occurrence meta (sequence, scheduled
	 * time) and enough send progress to derive a status are all resolved in this
	 * one query — the caller no longer issues per-row meta/subject lookups. Rows
	 * are ordered by the occurrence's scheduled time (the same field the list
	 * displays), newest first, so visible order matches visible dates. The
	 * per-run open/click/bounce aggregates are deliberately omitted; those are
	 * shown in the analytics report, not this list.
	 *
	 * @since 1.24.0
	 * @since 1.24.0 Excludes the legacy bucket; folds in subject + occurrence meta.
	 *
	 * @param int $campaign_id The recurring campaign ID.
	 * @param int $limit       Max rows to return. 0 (default) returns all rows.
	 * @param int $offset      Row offset for pagination. Ignored when $limit is 0.
	 * @return array List of per-email_id occurrence rows, newest first.
	 */
	public function getRecurringRunHistory( int $campaign_id, int $limit = 0, int $offset = 0 ): array {
		global $wpdb;

		$broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
		$emails_table    = $wpdb->prefix . 'mint_campaign_emails';
		$meta_table      = $wpdb->prefix . 'mint_campaign_emails_meta';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// One row per real occurrence. The INNER JOIN on `recurring_run` meta
		// keeps legacy/clone-failed sends (no meta) out of this list. Subject and
		// occurrence meta are MAX()-aggregated so the query stays one-row-per
		// email_id and is safe under ONLY_FULL_GROUP_BY. Ordering uses the
		// occurrence's stamped scheduled time (falling back to the earliest
		// broadcast time) so the sort matches the date shown in the row. A LIMIT
		// is appended only when supplied, so the caller can page without loading
		// every run.
		$select = "SELECT
				b.email_id AS email_id,
				MIN( b.scheduled_at ) AS scheduled_at,
				MAX( b.updated_at ) AS sent_at,
				COUNT( DISTINCT b.id ) AS recipients,
				COUNT( DISTINCT CASE WHEN b.status = %s THEN b.id END ) AS delivered,
				MAX( ce.email_subject ) AS subject,
				MAX( seq.meta_value ) AS run_sequence,
				MAX( sched.meta_value ) AS run_scheduled_at
			FROM {$broadcast_table} AS b
			INNER JOIN {$meta_table} AS run ON run.campaign_emails_id = b.email_id AND run.meta_key = 'recurring_run'
			LEFT JOIN {$emails_table} AS ce ON ce.id = b.email_id
			LEFT JOIN {$meta_table} AS seq ON seq.campaign_emails_id = b.email_id AND seq.meta_key = 'run_sequence'
			LEFT JOIN {$meta_table} AS sched ON sched.campaign_emails_id = b.email_id AND sched.meta_key = 'run_scheduled_at'
			WHERE b.campaign_id = %d
			GROUP BY b.email_id
			ORDER BY COALESCE( MAX( sched.meta_value ), MIN( b.scheduled_at ) ) DESC";

		if ( $limit > 0 ) {
			$sql = $wpdb->prepare(
				$select . ' LIMIT %d, %d',
				BroadcastStatus::SENT,
				$campaign_id,
				$offset,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				$select,
				BroadcastStatus::SENT,
				$campaign_id
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count of real recurring occurrences (distinct meta-stamped email_id groups).
	 *
	 * Mirrors the INNER JOIN filter in getRecurringRunHistory() so the paginated
	 * total counts only discrete runs. The legacy bucket is a single extra entry
	 * the caller appends separately; it is NOT counted here.
	 *
	 * @since 1.24.0
	 * @since 1.24.0 Counts real occurrences only (excludes the legacy bucket).
	 *
	 * @param int $campaign_id The recurring campaign ID.
	 * @return int Number of distinct real occurrences.
	 */
	public function getRecurringRunHistoryCount( int $campaign_id ): int {
		global $wpdb;

		$broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
		$meta_table      = $wpdb->prefix . 'mint_campaign_emails_meta';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT b.email_id )
				FROM {$broadcast_table} AS b
				INNER JOIN {$meta_table} AS run ON run.campaign_emails_id = b.email_id AND run.meta_key = 'recurring_run'
				WHERE b.campaign_id = %d",
				$campaign_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * The single "Earlier runs" (legacy) bucket for a recurring campaign.
	 *
	 * Collapses EVERY send that is not a discrete occurrence — pre-upgrade sends
	 * and any clone-failed run, all of which share the untouched template
	 * `email_id` and carry no `recurring_run` meta — into one aggregate row, so
	 * the UI's "combines every send … into one row" promise holds no matter how
	 * many such email_ids exist. The anti-join (`run.campaign_emails_id IS NULL`)
	 * selects exactly the rows excluded by getRecurringRunHistory().
	 *
	 * `email_id` is the lowest legacy email_id and is used purely as the report
	 * scope when the user opens the bucket's analytics. For a recurring campaign
	 * this is the one template step, so the report covers the whole bucket; the
	 * MIN keeps it deterministic in the rare multi-legacy-id case.
	 *
	 * @since 1.24.0
	 *
	 * @param int $campaign_id The recurring campaign ID.
	 * @return array|null { email_id, scheduled_at, sent_at, recipients, delivered },
	 *                     or null when the campaign has no legacy sends.
	 */
	public function getRecurringLegacyBucket( int $campaign_id ) {
		global $wpdb;

		$broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
		$meta_table      = $wpdb->prefix . 'mint_campaign_emails_meta';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					MIN( b.email_id ) AS email_id,
					MIN( b.scheduled_at ) AS scheduled_at,
					MAX( b.updated_at ) AS sent_at,
					COUNT( DISTINCT b.id ) AS recipients,
					COUNT( DISTINCT CASE WHEN b.status = %s THEN b.id END ) AS delivered
				FROM {$broadcast_table} AS b
				LEFT JOIN {$meta_table} AS run ON run.campaign_emails_id = b.email_id AND run.meta_key = 'recurring_run'
				WHERE b.campaign_id = %d AND run.campaign_emails_id IS NULL",
				BroadcastStatus::SENT,
				$campaign_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// The aggregate always returns one row; it is "no legacy" when the
		// anti-join matched nothing (null email_id / zero recipients).
		if ( empty( $row ) || empty( $row['email_id'] ) || (int) $row['recipients'] <= 0 ) {
			return null;
		}

		return $row;
	}

	/**
	 * Campaign-level rollup across all recurring occurrences.
	 *
	 * Sums delivered/opens/clicks/bounced/unsubscribes across every occurrence
	 * (every email_id) of the campaign. `recipients` is the SUM of per-occurrence
	 * sends (NOT a DISTINCT contact count), so campaign KPI rates divide opens by
	 * the same scope they accumulated in. The single legacy occurrence contributes
	 * its blended numbers as one bucket, keeping the total correct.
	 *
	 * @since 1.24.0
	 *
	 * @param int $campaign_id The recurring campaign ID.
	 * @return array { recipients, delivered, bounced, opens, clicks, unsubscribes }
	 */
	public function getRecurringCampaignRollup( int $campaign_id ): array {
		global $wpdb;

		$broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
		$meta_table      = $wpdb->prefix . 'mint_broadcast_email_meta';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT
				COUNT( DISTINCT b.id ) AS recipients,
				COUNT( DISTINCT CASE WHEN b.status = %s THEN b.id END ) AS delivered,
				COUNT( DISTINCT CASE WHEN b.status = %s THEN b.id END ) AS bounced,
				COUNT( DISTINCT CASE WHEN m_open.meta_value = '1' THEN b.id END ) AS opens,
				COUNT( DISTINCT CASE WHEN m_click.meta_value = '1' THEN b.id END ) AS clicks,
				COUNT( DISTINCT CASE WHEN m_unsub.meta_value = '1' THEN b.id END ) AS unsubscribes
			FROM {$broadcast_table} AS b
			LEFT JOIN {$meta_table} AS m_open
				ON b.id = m_open.mint_email_id AND m_open.meta_key = 'is_open'
			LEFT JOIN {$meta_table} AS m_click
				ON b.id = m_click.mint_email_id AND m_click.meta_key = 'is_click'
			LEFT JOIN {$meta_table} AS m_unsub
				ON b.id = m_unsub.mint_email_id AND m_unsub.meta_key = 'is_unsubscribe'
			WHERE b.campaign_id = %d",
			BroadcastStatus::SENT,
			BroadcastStatus::FAILED,
			$campaign_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'recipients'   => isset( $row['recipients'] ) ? (int) $row['recipients'] : 0,
			'delivered'    => isset( $row['delivered'] ) ? (int) $row['delivered'] : 0,
			'bounced'      => isset( $row['bounced'] ) ? (int) $row['bounced'] : 0,
			'opens'        => isset( $row['opens'] ) ? (int) $row['opens'] : 0,
			'clicks'       => isset( $row['clicks'] ) ? (int) $row['clicks'] : 0,
			'unsubscribes' => isset( $row['unsubscribes'] ) ? (int) $row['unsubscribes'] : 0,
		);
	}

	/**
	 * Count broadcast emails with a specific status for an automation sequence campaign.
	 *
	 * Counts rows in the broadcast table whose `email_id` matches any campaign
	 * email step belonging to the given campaign, filtered by the given status.
	 *
	 * Replaces legacy `EmailModel::count_delivered_status_on_automation_sequence()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id The campaign ID.
	 * @param string $status      The broadcast status to count.
	 * @return int Count of matching broadcast emails.
	 */
	public function countDeliveredStatusOnAutomationSequence( int $campaign_id, string $status ): int {
		global $wpdb;

		$broadcast_table       = $this->prefixedTable();
		$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';

		$query = $wpdb->prepare(
			"SELECT COUNT(id) FROM {$broadcast_table} WHERE email_id IN (SELECT id FROM {$campaign_emails_table} WHERE campaign_id = %d) AND status = %s",
			$campaign_id,
			$status
		);

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count broadcast emails with a specific metric for an automation sequence campaign.
	 *
	 * Counts rows in the broadcast email meta table matching the given metric type
	 * (e.g. 'is_open', 'is_click') for broadcast emails belonging to the campaign's
	 * email steps.
	 *
	 * Replaces legacy `EmailModel::count_email_metrics_on_automation_sequence()`.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $campaign_id The campaign ID.
	 * @param string $metric_type The meta key to count (e.g. 'is_open', 'is_click').
	 * @return int Count of matching meta rows.
	 */
	public function countEmailMetricsOnAutomationSequence( int $campaign_id, string $metric_type ): int {
		global $wpdb;

		$broadcast_table       = $this->prefixedTable();
		$broadcast_meta_table  = $this->metaTable();
		$campaign_emails_table = $wpdb->prefix . 'mint_campaign_emails';

		$query = $wpdb->prepare(
			"SELECT COUNT(mint_email_id) FROM {$broadcast_meta_table} WHERE meta_key = %s AND meta_value = %d AND mint_email_id IN ( SELECT id FROM {$broadcast_table} WHERE email_id IN ( SELECT id FROM {$campaign_emails_table} WHERE campaign_id = %d ) )",
			$metric_type,
			1,
			$campaign_id
		);

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get order totals from broadcast emails grouped by time period.
	 *
	 * Returns revenue data grouped by the specified time filter (weekly, monthly,
	 * yearly, or auto-detected via 'all'). Queries WooCommerce `wc_order_stats`
	 * for order IDs tracked in broadcast email meta.
	 *
	 * Replaces legacy `EmailModel::get_order_total_from_email()`.
	 *
	 * @since 1.20.0
	 *
	 * @param string $filter     Time filter: 'weekly', 'monthly', 'all', or default (yearly).
	 * @param string $email_type Email type to filter by (e.g. 'campaign', 'automation').
	 * @return array Associative array of label => order_total pairs.
	 */
	public function getOrderTotalFromEmail( string $filter, string $email_type ): array {
		$revenue_arr = array();

		if ( 'weekly' === $filter ) {
			$order_ids = $this->getOrderIdsForPeriod( $email_type, 'week' );
			if ( ! empty( $order_ids ) ) {
				$revenue_arr = $this->getOrderTotalForWeek( $order_ids );
			}
		} elseif ( 'all' === $filter ) {
			$revenue_arr = $this->getOrderTotalForAll( $email_type );
		} elseif ( 'monthly' === $filter ) {
			$order_ids = $this->getOrderIdsForPeriod( $email_type, 'month' );
			if ( ! empty( $order_ids ) ) {
				$revenue_arr = $this->getOrderTotalForMonth( $order_ids );
			}
		} else {
			$order_ids = $this->getOrderIdsForPeriod( $email_type, 'year' );
			if ( ! empty( $order_ids ) ) {
				$revenue_arr = $this->getOrderTotalForYear( $order_ids );
			}
		}

		return $revenue_arr;
	}

	/**
	 * Get total revenue from WooCommerce orders by order IDs.
	 *
	 * Sums `total_sales` from `wc_order_stats` for the given order IDs
	 * (including child/parent orders) with qualifying statuses.
	 *
	 * Replaces legacy `EmailModel::get_total_revenue_from_email()`.
	 *
	 * @since 1.20.0
	 *
	 * @param array $order_ids Array of WooCommerce order IDs.
	 * @return float Total revenue amount.
	 */
	public function getTotalRevenueFromEmail( array $order_ids ): float {
		if ( empty( $order_ids ) || ! function_exists( 'wc_get_order' ) ) {
			return 0.0;
		}

		$paid_statuses = array( 'processing', 'completed', 'on-hold', 'wc-wpfnl-main-order' );
		$total         = 0.0;

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( absint( $order_id ) );
			if ( $order && \in_array( $order->get_status(), $paid_statuses, true ) ) {
				$total += (float) $order->get_total();
			}
		}

		return $total;
	}

	/**
	 * Get order IDs from broadcast email meta for a given time period.
	 *
	 * @since 1.20.0
	 *
	 * @param string $email_type Email type filter (e.g. 'campaign', 'automation').
	 * @param string $period     Time period: 'week', 'month', or 'year'.
	 * @return array Array of order IDs.
	 */
	private function getOrderIdsForPeriod( string $email_type, string $period ): array {
		global $wpdb;

		$broadcast_table      = $this->prefixedTable();
		$broadcast_meta_table = $this->metaTable();

		$interval_map = array(
			'week'  => '7 DAY',
			'month' => '30 DAY',
			'year'  => '1 YEAR',
		);

		$interval = isset( $interval_map[ $period ] ) ? $interval_map[ $period ] : '1 YEAR';

		$query  = "SELECT meta_value FROM {$broadcast_meta_table} ";
		$query .= "WHERE meta_key = 'order_id' ";
		$query .= $wpdb->prepare(
			"AND mint_email_id IN (SELECT id FROM {$broadcast_table} WHERE email_type = %s) ",
			$email_type
		);
		$query .= "AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL {$interval}) AND NOW() ";

		return $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get order IDs for the 'all' filter with auto-detected time range.
	 *
	 * @since 1.20.0
	 *
	 * @param string $email_type Email type filter.
	 * @param string $first_date First contact creation date.
	 * @return array Array of order IDs.
	 */
	private function getOrderIdsForAllPeriod( string $email_type, string $first_date ): array {
		global $wpdb;

		$broadcast_table      = $this->prefixedTable();
		$broadcast_meta_table = $this->metaTable();

		$prev_date = new \DateTime( $first_date );
		$curt_date = new \DateTime();
		$interval  = $prev_date->diff( $curt_date );
		$days      = $interval->days;

		if ( $days <= 7 ) {
			return $this->getOrderIdsForPeriod( $email_type, 'week' );
		} elseif ( $days <= 31 ) {
			return $this->getOrderIdsForPeriod( $email_type, 'month' );
		} elseif ( $days <= 365 ) {
			return $this->getOrderIdsForPeriod( $email_type, 'year' );
		}

		$years    = $days <= 1460 ? 4 : 5;
		$end_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$years} years", strtotime( $first_date ) ) );

		$query  = "SELECT meta_value FROM {$broadcast_meta_table} ";
		$query .= "WHERE meta_key = 'order_id' ";
		$query .= $wpdb->prepare(
			"AND mint_email_id IN (SELECT id FROM {$broadcast_table} WHERE email_type = %s) ",
			$email_type
		);
		$query .= $wpdb->prepare( 'AND created_at BETWEEN %s AND %s ', $first_date, $end_date );

		return $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get order totals for the 'all' filter with auto-detected time grouping.
	 *
	 * @since 1.20.0
	 *
	 * @param string $email_type Email type filter.
	 * @return array Associative array of label => order_total pairs.
	 */
	private function getOrderTotalForAll( string $email_type ): array {
		global $wpdb;

		$contact_table = $wpdb->prefix . 'mint_contacts';
		$first_row     = $wpdb->get_row(
			$wpdb->prepare( 'SELECT created_at FROM %1s LIMIT 1', $contact_table ),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$first_date = isset( $first_row['created_at'] ) ? $first_row['created_at'] : '';
		$prev_date  = new \DateTime( $first_date );
		$curt_date  = new \DateTime();
		$interval   = $prev_date->diff( $curt_date );
		$days       = $interval->days;

		$order_ids = $this->getOrderIdsForAllPeriod( $email_type, $first_date );
		if ( empty( $order_ids ) ) {
			return array();
		}

		if ( $days <= 7 ) {
			return $this->getOrderTotalForWeek( $order_ids );
		} elseif ( $days <= 31 ) {
			return $this->getOrderTotalForMonth( $order_ids );
		} elseif ( $days <= 365 ) {
			return $this->getOrderTotalForYear( $order_ids );
		} elseif ( $days <= 1460 ) {
			return $this->getOrderTotalForQuarterly( $order_ids, $first_date );
		}

		return $this->getOrderTotalForAllYearly( $order_ids, $first_date );
	}

	/**
	 * Get order totals grouped by day for the last 7 days.
	 *
	 * @since 1.20.0
	 *
	 * @param array $order_ids Array of WooCommerce order IDs.
	 * @return array Associative array of 'Mon D' => order_total.
	 */
	private function getOrderTotalForWeek( array $order_ids ): array {
		global $wpdb;

		$order_ids   = array_map( 'absint', $order_ids );
		$id_list     = implode( ',', $order_ids );
		$stats_table = $wpdb->prefix . 'wc_order_stats';

		$query  = "SELECT DATE_FORMAT(date_created, '%b %e') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$stats_table} ";
		$query .= "WHERE (order_id IN ({$id_list}) OR parent_id IN ({$id_list})) ";
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= "GROUP BY DATE_FORMAT(date_created, '%b %e') ";
		$query .= "ORDER BY DATE_FORMAT(date_created, '%c %e') ASC";

		$result = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = array_column( $result, 'order_total', 'label' );

		$week_days = array();
		for ( $i = 6; $i >= 0; $i-- ) {
			$label               = gmdate( 'M j', strtotime( "-{$i} day" ) );
			$week_days[ $label ] = 0;
		}

		return array_merge( $week_days, $result );
	}

	/**
	 * Get order totals grouped by day for the last 30 days.
	 *
	 * @since 1.20.0
	 *
	 * @param array $order_ids Array of WooCommerce order IDs.
	 * @return array Associative array of 'Mon D' => order_total.
	 */
	private function getOrderTotalForMonth( array $order_ids ): array {
		global $wpdb;

		$order_ids   = array_map( 'absint', $order_ids );
		$id_list     = implode( ',', $order_ids );
		$stats_table = $wpdb->prefix . 'wc_order_stats';

		$query  = "SELECT DATE_FORMAT(date_created, '%b %e') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$stats_table} ";
		$query .= "WHERE (order_id IN ({$id_list}) OR parent_id IN ({$id_list})) ";
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= "GROUP BY DATE_FORMAT(date_created, '%b %e') ";
		$query .= "ORDER BY DATE_FORMAT(date_created, '%b %e') ASC";

		$result = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = array_column( $result, 'order_total', 'label' );

		$monthly_days = array();
		for ( $i = 29; $i >= 0; $i-- ) {
			$label                  = gmdate( 'M j', strtotime( "-{$i} day" ) );
			$monthly_days[ $label ] = 0;
		}

		return array_merge( $monthly_days, $result );
	}

	/**
	 * Get order totals grouped by month for the last year.
	 *
	 * @since 1.20.0
	 *
	 * @param array $order_ids Array of WooCommerce order IDs.
	 * @return array Associative array of 'Mon' => order_total.
	 */
	private function getOrderTotalForYear( array $order_ids ): array {
		global $wpdb;

		$order_ids   = array_map( 'absint', $order_ids );
		$id_list     = implode( ',', $order_ids );
		$stats_table = $wpdb->prefix . 'wc_order_stats';

		$query  = "SELECT DATE_FORMAT(date_created, '%b') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$stats_table} ";
		$query .= "WHERE (order_id IN ({$id_list}) OR parent_id IN ({$id_list})) ";
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= "GROUP BY DATE_FORMAT(date_created, '%b') ";
		$query .= "ORDER BY DATE_FORMAT(date_created, '%b') ASC";

		$result = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = array_column( $result, 'order_total', 'label' );

		$months = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$label            = gmdate( 'M', strtotime( "-{$i} month" ) );
			$months[ $label ] = 0;
		}

		return array_merge( $months, $result );
	}

	/**
	 * Get order totals grouped by quarter.
	 *
	 * @since 1.20.0
	 *
	 * @param array  $order_ids  Array of WooCommerce order IDs.
	 * @param string $first_date First contact creation date.
	 * @return array Associative array of 'YYYY QN' => order_total.
	 */
	private function getOrderTotalForQuarterly( array $order_ids, string $first_date ): array {
		global $wpdb;

		$order_ids   = array_map( 'absint', $order_ids );
		$id_list     = implode( ',', $order_ids );
		$stats_table = $wpdb->prefix . 'wc_order_stats';

		$query  = "SELECT CONCAT(YEAR(date_created), ' Q', QUARTER(date_created)) AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$stats_table} ";
		$query .= "WHERE (order_id IN ({$id_list}) OR parent_id IN ({$id_list})) ";
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= 'AND date_created >= DATE_SUB(NOW(), INTERVAL 5 YEAR) ';
		$query .= "GROUP BY CONCAT(YEAR(date_created), ' Q', QUARTER(date_created)) ";
		$query .= "ORDER BY CONCAT(YEAR(date_created), ' Q', QUARTER(date_created)) ASC";

		$result = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = array_column( $result, 'order_total', 'label' );

		$quarters     = array();
		$current_year = (int) gmdate( 'Y', strtotime( $first_date ) );
		$count        = 0;

		for ( $year = $current_year; $year < $current_year + 4; $year++ ) {
			for ( $quarter = 1; $quarter <= 4; $quarter++ ) {
				$count++;
				if ( $count > 20 ) {
					break 2;
				}
				$label              = $year . ' Q' . $quarter;
				$quarters[ $label ] = isset( $result[ $label ] ) ? $result[ $label ] : 0;
			}
		}

		return array_merge( $quarters, $result );
	}

	/**
	 * Get order totals grouped by year.
	 *
	 * @since 1.20.0
	 *
	 * @param array  $order_ids  Array of WooCommerce order IDs.
	 * @param string $first_date First contact creation date.
	 * @return array Associative array of 'YYYY' => order_total.
	 */
	private function getOrderTotalForAllYearly( array $order_ids, string $first_date ): array {
		global $wpdb;

		$order_ids   = array_map( 'absint', $order_ids );
		$id_list     = implode( ',', $order_ids );
		$stats_table = $wpdb->prefix . 'wc_order_stats';

		$query  = "SELECT DATE_FORMAT(date_created, '%Y') AS label";
		$query .= ', SUM(total_sales) as order_total ';
		$query .= "FROM {$stats_table} ";
		$query .= "WHERE (order_id IN ({$id_list}) OR parent_id IN ({$id_list})) ";
		$query .= "AND status IN ('wc-processing', 'wc-completed', 'wc-wpfnl-main-order') ";
		$query .= 'AND date_created BETWEEN DATE_SUB(NOW(), INTERVAL 5 YEAR) AND DATE_ADD(NOW(), INTERVAL 1 YEAR) ';
		$query .= 'GROUP BY YEAR(date_created) ';
		$query .= 'ORDER BY YEAR(date_created) ASC';

		$result = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = array_column( $result, 'order_total', 'label' );

		$start_year = (int) gmdate( 'Y', strtotime( $first_date ) );
		$last_year  = $start_year + 5;

		$years = array();
		for ( $year = $start_year; $year < $last_year; $year++ ) {
			$years[ $year ] = isset( $result[ $year ] ) ? $result[ $year ] : 0;
		}

		return $years;
	}

	/**
	 * Find broadcast emails stuck in 'sending' status older than the given timeout.
	 *
	 * Used by the recovery cron to detect emails that were claimed for sending
	 * but never completed (e.g., after an Action Scheduler timeout).
	 *
	 * @param int $timeout_minutes Minutes since updated_at to consider an email stuck.
	 *
	 * @return array Stuck broadcast email rows.
	 *
	 * @since 1.20.0
	 */
	public function getStuckSendingEmails( int $timeout_minutes ): array {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $timeout_minutes * 60 ) );

		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'status', '=', BroadcastStatus::SENDING )
			->where( 'updated_at', '<', $cutoff )
			->get();
	}

	/**
	 * Get broadcast email counts grouped by status for a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 *
	 * @return array Associative array keyed by status with count values.
	 *               e.g. ['scheduled' => 5000, 'sending' => 1000, 'sent' => 3000, 'failed' => 0]
	 *
	 * @since 1.20.0
	 */
	public function getStatusCounts( int $campaign_id ): array {
		$rows = QueryBuilder::table( $this->prefixedTable() )
			->select( 'status', 'COUNT(id) AS cnt' )
			->where( 'campaign_id', '=', $campaign_id )
			->groupBy( 'status' )
			->get();

		$counts = array(
			'scheduled' => 0,
			'sending'   => 0,
			'sent'      => 0,
			'failed'    => 0,
		);

		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['cnt'];
			}
		}

		return $counts;
	}

	/**
	 * Count all broadcast email rows for a campaign without status filtering.
	 *
	 * Replaces legacy `EmailModel::count_broadcast_email_ids_to_campaign($campaign_id)`
	 * when called without a status parameter.
	 *
	 * @since 1.20.0
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int Total count of broadcast email rows for the campaign.
	 */
	/**
	 * Count broadcast emails that have NOT been opened for a campaign.
	 *
	 * A recipient is "not opened" when their broadcast email row exists (status = 'sent')
	 * but there is no corresponding `is_open = 1` entry in the meta table.
	 *
	 * @since 1.23.5
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $search      Optional email address search filter.
	 * @param array|null $date_range Optional ['start' => datetime, 'end' => datetime] to restrict by scheduled_at.
	 * @param int    $email_id    Optional occurrence (campaign email) ID. When > 0, narrows the
	 *                            count to a single recurring run; 0 keeps the campaign-wide scope.
	 * @return int Count of broadcast emails with no open meta entry.
	 */
	public function countNotOpenedOnCampaign( int $campaign_id, string $search = '', ?array $date_range = null, int $email_id = 0 ): int {
		global $wpdb;

		$broadcast = $this->prefixedTable();
		$meta      = $this->metaTable();

		$query = "SELECT COUNT(DISTINCT {$broadcast}.id)
			FROM {$broadcast}
			WHERE {$broadcast}.campaign_id = %d
			  AND {$broadcast}.status IN ('sent', 'scheduled')
			  AND NOT EXISTS (
			      SELECT 1 FROM {$meta}
			      WHERE {$meta}.mint_email_id = {$broadcast}.id
			        AND {$meta}.meta_key = 'is_open'
			        AND {$meta}.meta_value = '1'
			  )";

		$values = array( $campaign_id );

		if ( $email_id > 0 ) {
			$query   .= " AND {$broadcast}.email_id = %d";
			$values[] = $email_id;
		}

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$query   .= " AND {$broadcast}.scheduled_at >= %s AND {$broadcast}.scheduled_at <= %s";
			$values[] = $date_range['start'];
			$values[] = $date_range['end'];
		}

		if ( '' !== $search ) {
			$query   .= ' AND ' . $broadcast . '.email_address LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $query, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function countBroadcastEmailsByCampaign( int $campaign_id, string $search = '', ?array $date_range = null, int $email_id = 0 ): int {
		$table = $this->prefixedTable();
		$qb    = QueryBuilder::table( $table )
			->where( 'campaign_id', '=', $campaign_id );

		if ( $email_id > 0 ) {
			$qb = $qb->where( 'email_id', '=', $email_id );
		}

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$qb = $qb->where( 'scheduled_at', '>=', $date_range['start'] )
					->where( 'scheduled_at', '<=', $date_range['end'] );
		}

		if ( '' !== $search ) {
			$qb = $qb->where( 'email_address', 'LIKE', '%' . $search . '%' );
		}

		return $qb->count();
	}

	/**
	 * Retrieve all five contact-overview email stats in a single query.
	 *
	 * Collapses the five separate COUNT/MAX calls previously used by
	 * ContactProfileAction::get_contact_overview() into one round-trip:
	 * delivered, bounced, opened, clicked, and last-opened timestamp.
	 *
	 * @since 1.20.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $filter     Time filter: 'lifetime', 'month', or 'year'. Default 'lifetime'.
	 * @return array{delivered: int, bounced: int, opened: int, clicked: int, last_opened: string|null}
	 */
	public function getContactEmailStats( int $contact_id, string $filter = 'lifetime' ): array {
		global $wpdb;

		$email_table = $this->prefixedTable();
		$meta_table  = $this->metaTable();
		$where_clause = '';

		if ( 'month' === $filter ) {
			$last_month   = date( 'Y-m-d', strtotime( '-30 days' ) );
			$where_clause = $wpdb->prepare( ' AND be.created_at >= %s', $last_month );
		} elseif ( 'year' === $filter ) {
			$last_year    = date( 'Y-m-d', strtotime( '-1 year' ) );
			$where_clause = $wpdb->prepare( ' AND be.created_at >= %s', $last_year );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN be.status = 'sent'                          THEN 1 ELSE 0 END) AS delivered,
					SUM(CASE WHEN be.status = 'failed'                        THEN 1 ELSE 0 END) AS bounced,
					SUM(CASE WHEN bm_open.mint_email_id  IS NOT NULL          THEN 1 ELSE 0 END) AS opened,
					SUM(CASE WHEN bm_click.mint_email_id IS NOT NULL          THEN 1 ELSE 0 END) AS clicked,
					MAX(COALESCE(bm_open.updated_at, bm_open.created_at))                        AS last_opened
				FROM {$email_table} be
				LEFT JOIN {$meta_table} bm_open
					ON bm_open.mint_email_id = be.id AND bm_open.meta_key = 'is_open'  AND bm_open.meta_value = '1'
				LEFT JOIN {$meta_table} bm_click
					ON bm_click.mint_email_id = be.id AND bm_click.meta_key = 'is_click' AND bm_click.meta_value = '1'
				WHERE be.contact_id = %d {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id
			),
			ARRAY_A
		);

		return array(
			'delivered'   => isset( $row['delivered'] )   ? (int) $row['delivered']   : 0,
			'bounced'     => isset( $row['bounced'] )     ? (int) $row['bounced']     : 0,
			'opened'      => isset( $row['opened'] )      ? (int) $row['opened']      : 0,
			'clicked'     => isset( $row['clicked'] )     ? (int) $row['clicked']     : 0,
			'last_opened' => ! empty( $row['last_opened'] ) ? $row['last_opened']     : null,
		);
	}

	/**
	 * Count broadcast emails by contact, status, and time filter.
	 *
	 * Replaces legacy EmailModel::count_delivered_status_single_contact().
	 *
	 * @since 1.20.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $status     Email delivery status ('sent', 'failed').
	 * @param string $filter     Time filter: 'lifetime', 'month', or 'year'. Default 'lifetime'.
	 * @return int Count of matching broadcast email rows.
	 */
	public function countDeliveredStatusForContact( int $contact_id, string $status, string $filter = 'lifetime' ): int {
		global $wpdb;

		$table        = $this->prefixedTable();
		$where_clause = '';

		if ( 'month' === $filter ) {
			$last_month   = date( 'Y-m-d', strtotime( '-30 days' ) );
			$where_clause = $wpdb->prepare( ' AND created_at >= %s', $last_month );
		} elseif ( 'year' === $filter ) {
			$last_year    = date( 'Y-m-d', strtotime( '-1 year' ) );
			$where_clause = $wpdb->prepare( ' AND created_at >= %s', $last_year );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(status) FROM {$table} WHERE contact_id = %d AND status = %s {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id,
				$status
			)
		);
	}

	/**
	 * Count broadcast emails with a specific meta key for a contact and time filter.
	 *
	 * Replaces legacy EmailModel::count_email_open_click_on_contact().
	 *
	 * @since 1.20.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $meta_key   Meta key ('is_open', 'is_click').
	 * @param string $filter     Time filter: 'lifetime', 'month', or 'year'. Default 'lifetime'.
	 * @return int Count of matching rows.
	 */
	public function countEmailMetricForContact( int $contact_id, string $meta_key, string $filter = 'lifetime' ): int {
		global $wpdb;

		$email_table  = $this->prefixedTable();
		$meta_table   = $this->metaTable();
		$where_clause = '';

		if ( 'month' === $filter ) {
			$last_month   = date( 'Y-m-d', strtotime( '-30 days' ) );
			$where_clause = $wpdb->prepare( ' AND mail_meta.created_at >= %s', $last_month );
		} elseif ( 'year' === $filter ) {
			$last_year    = date( 'Y-m-d', strtotime( '-1 year' ) );
			$where_clause = $wpdb->prepare( ' AND mail_meta.created_at >= %s', $last_year );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(mail.id) FROM {$email_table} AS mail
				INNER JOIN {$meta_table} AS mail_meta ON mail.id = mail_meta.mint_email_id
				WHERE mail.contact_id = %d AND mail_meta.meta_key = %s {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id,
				$meta_key
			)
		);
	}

	/**
	 * Get the most recent meta timestamp for a contact by meta key.
	 *
	 * Replaces legacy EmailModel::last_opened_email_single_contact()
	 * and EmailModel::last_clicked_email_single_contact().
	 *
	 * @since 1.20.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $meta_key   Meta key ('is_open', 'is_click').
	 * @return string|null Datetime string or null if no match.
	 */
	public function getLastEmailMetaTimestampForContact( int $contact_id, string $meta_key ): ?string {
		global $wpdb;

		$email_table = $this->prefixedTable();
		$meta_table  = $this->metaTable();

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT mail_meta.created_at, mail_meta.updated_at
				FROM {$email_table} AS mail
				INNER JOIN {$meta_table} AS mail_meta ON mail.id = mail_meta.mint_email_id
				WHERE mail.contact_id = %d AND mail_meta.meta_key = %s
				ORDER BY mail_meta.id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id,
				$meta_key
			),
			ARRAY_A
		);

		if ( empty( $result ) ) {
			return null;
		}

		return ! empty( $result['updated_at'] ) ? $result['updated_at'] : $result['created_at'];
	}

	/**
	 * Get the most recent meta timestamp for a given meta_key across all emails in a campaign.
	 *
	 * @since 1.24.0
	 *
	 * @param int        $campaign_id Campaign ID.
	 * @param string     $meta_key    Meta key to query (e.g. 'is_open', 'is_click').
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime].
	 * @param int        $email_id    Optional occurrence (campaign email) ID. When > 0, narrows the
	 *                                lookup to a single recurring run; 0 keeps the campaign-wide scope.
	 * @return string|null Datetime string or null if no match.
	 */
	public function getLastMetaTimestampOnCampaign( int $campaign_id, string $meta_key, ?array $date_range = null, int $email_id = 0 ): ?string {
		global $wpdb;

		$broadcast  = $this->prefixedTable();
		$meta_table = $this->metaTable();

		// Narrow to a single recurring occurrence when an email_id is given.
		$occurrence_clause = $email_id > 0 ? ' AND be.email_id = %d' : '';

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$args = array( $meta_key, $campaign_id );
			if ( $email_id > 0 ) {
				$args[] = $email_id;
			}
			$args[] = $date_range['start'];
			$args[] = $date_range['end'];

			$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT MAX(COALESCE(meta.updated_at, meta.created_at))
					FROM {$broadcast} be
					LEFT JOIN {$meta_table} meta
						ON meta.mint_email_id = be.id AND meta.meta_key = %s AND meta.meta_value = '1'
					WHERE be.campaign_id = %d{$occurrence_clause} AND meta.created_at BETWEEN %s AND %s",
					$args
				)
			);
		} else {
			$args = array( $meta_key, $campaign_id );
			if ( $email_id > 0 ) {
				$args[] = $email_id;
			}

			$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT MAX(COALESCE(meta.updated_at, meta.created_at))
					FROM {$broadcast} be
					LEFT JOIN {$meta_table} meta
						ON meta.mint_email_id = be.id AND meta.meta_key = %s AND meta.meta_value = '1'
					WHERE be.campaign_id = %d{$occurrence_clause}",
					$args
				)
			);
		}

		return ! empty( $result ) ? $result : null;
	}

	/**
	 * Get the most recent broadcast email creation timestamp for a contact.
	 *
	 * Replaces legacy EmailModel::last_email_sent_single_contact().
	 *
	 * @since 1.20.0
	 *
	 * @param int $contact_id Contact ID.
	 * @return string|null Datetime string or null if no match.
	 */
	public function getLastEmailSentTimestampForContact( int $contact_id ): ?string {
		global $wpdb;

		$table = $this->prefixedTable();

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_at FROM {$table}
				WHERE contact_id = %d
				ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$contact_id
			)
		);
	}

	/**
	 * Re-queue sent broadcast rows that were never opened back to 'scheduled'.
	 *
	 * Finds every sent broadcast row for the given campaign + email that has no
	 * corresponding is_open meta entry, then bulk-updates their status to
	 * 'scheduled' and sets a new scheduled_at so the send worker picks them up.
	 *
	 * @param int    $campaign_id  Campaign ID.
	 * @param int    $email_id     Campaign-email ID (mint_campaign_emails.id).
	 * @param string $scheduled_at UTC datetime string ('Y-m-d H:i:s') for the resend.
	 * @return int Number of rows re-queued.
	 *
	 * @since 1.24.0
	 */
	public function rescheduleUnopenedBroadcasts( int $campaign_id, int $email_id, string $scheduled_at ): int {
		global $wpdb;

		$broadcast = $this->prefixedTable();
		$meta      = $this->metaTable();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$broadcast}
				SET status       = 'scheduled',
				    scheduled_at = %s,
				    updated_at   = %s
				WHERE campaign_id = %d
				  AND email_id    = %d
				  AND status      = 'sent'
				  AND NOT EXISTS (
				      SELECT 1 FROM {$meta}
				      WHERE {$meta}.mint_email_id = {$broadcast}.id
				        AND {$meta}.meta_key      = 'is_open'
				        AND {$meta}.meta_value    = '1'
				  )",
				$scheduled_at,
				current_time( 'mysql' ),
				$campaign_id,
				$email_id
			)
		);
		// phpcs:enable

		return is_int( $updated ) ? $updated : 0;
	}

}
