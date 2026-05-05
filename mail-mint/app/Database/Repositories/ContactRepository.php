<?php
/**
 * ContactRepository — SOLID repository for the mint_contacts table.
 *
 * Replaces ContactModel static CRUD methods with an instance-based repository
 * that extends AbstractRepository and uses QueryBuilder for all DB access.
 *
 * Does NOT use CacheableTrait — contacts are write-heavy (form submissions,
 * automation triggers, imports) so cache invalidation cost exceeds benefit.
 *
 * @package Mint\MRM\Database\Repositories
 * @since   1.19.5
 */

namespace Mint\MRM\Database\Repositories;

use Mint\MRM\Database\AbstractRepository;
use Mint\MRM\Database\QueryBuilder;
use Mint\MRM\DataBase\Tables\ContactGroupSchema;
use Mint\MRM\DataBase\Tables\ContactGroupPivotSchema;
use Mint\MRM\DataBase\Tables\ContactMetaSchema;
use Mint\MRM\DataBase\Tables\ContactNoteSchema;
use MRM\Common\MrmCommon;
use Mint\MRM\DataBase\Models\ContactModel;

/**
 * Class ContactRepository
 *
 * @since 1.19.5
 */
class ContactRepository extends AbstractRepository {

	/**
	 * Return the table name (without WP prefix).
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	protected function tableName(): string {
		return 'mint_contacts';
	}

	/**
	 * Return the list of mass-assignable columns.
	 *
	 * Matches the columns written by the legacy ContactModel::insert().
	 *
	 * @since 1.19.5
	 *
	 * @return array
	 */
	protected function fillable(): array {
		return array( 'email', 'first_name', 'last_name', 'status', 'stage', 'scores', 'source', 'hash', 'last_activity', 'created_by', 'wp_user_id' );
	}

	/**
	 * Return the columns used for LIKE search in list().
	 *
	 * Matches the search columns in the legacy ContactModel::get_all() WHERE clause.
	 *
	 * @since 1.19.5
	 *
	 * @return array
	 */
	protected function searchable(): array {
		return array( 'email', 'first_name', 'last_name', 'source', 'status', 'stage' );
	}

	/**
	 * Create a new contact.
	 *
	 * Auto-generates the `hash` column via MrmCommon::get_rand_hash() if not
	 * provided, matching the legacy ContactModel::insert() behaviour.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data Column values.
	 *
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public function create( array $data ): int {
		if ( empty( $data['hash'] ) && ! empty( $data['email'] ) ) {
			$data['hash'] = MrmCommon::get_rand_hash( $data['email'] );
		}

		return parent::create( $data );
	}

	/**
	 * Check whether a contact with the given email already exists.
	 *
	 * Replaces ContactModel::is_contact_exist().
	 *
	 * @since 1.19.5
	 *
	 * @param string $email Email address to check.
	 *
	 * @return int|null Contact ID if exists, null otherwise.
	 */
	public function isContactExist( string $email ) {
		$row = QueryBuilder::table( $this->prefixedTable() )
			->select( 'id' )
			->where( 'email', '=', $email )
			->first();

		return $row ? (int) $row['id'] : null;
	}

	/**
	 * Check whether a contact with the given email belongs to the given contact ID.
	 *
	 * Used during update to allow the same email if it belongs to the contact
	 * being updated. Replaces ContactModel::is_contact_exist_by_id().
	 *
	 * @since 1.19.5
	 *
	 * @param string $email      Email address to check.
	 * @param int    $contact_id Contact ID to match against.
	 *
	 * @return bool True if the email belongs to this contact ID.
	 */
	public function isContactExistById( string $email, int $contact_id ): bool {
		$row = QueryBuilder::table( $this->prefixedTable() )
			->select( 'id' )
			->where( 'email', '=', $email )
			->where( 'id', '=', $contact_id )
			->first();

		return ! empty( $row );
	}

	/**
	 * Delete a single contact and clean up related tables.
	 *
	 * Replicates the cleanup sequence from the legacy ContactModel::destroy():
	 * meta → notes → group pivot → form entries → contact row.
	 *
	 * @since 1.19.5
	 *
	 * @param int $id Contact ID.
	 *
	 * @return int Number of affected rows (0 if not found).
	 */
	public function destroy( int $id ): int {
		global $wpdb;

		$meta_table  = $wpdb->prefix . ContactMetaSchema::$table_name;
		$note_table  = $wpdb->prefix . ContactNoteSchema::$table_name;
		$pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		// Clean up related tables before deleting the contact row.
		QueryBuilder::table( $meta_table )
			->where( 'contact_id', '=', $id )
			->delete();

		QueryBuilder::table( $note_table )
			->where( 'contact_id', '=', $id )
			->delete();

		QueryBuilder::table( $pivot_table )
			->where( 'contact_id', '=', $id )
			->delete();

		ContactModel::remove_form_entries_for_deleted_contact( $id );

		return parent::destroy( $id );
	}

	/**
	 * Delete multiple contacts and clean up related tables for all IDs.
	 *
	 * Batched version of destroy() — same cleanup but using whereIn for efficiency.
	 * Replicates the legacy ContactModel::destroy_all() behaviour.
	 *
	 * @since 1.19.5
	 *
	 * @param array $ids Array of contact IDs.
	 *
	 * @return int Number of affected rows, or 0 if $ids is empty.
	 */
	public function destroyMany( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;

		$meta_table  = $wpdb->prefix . ContactMetaSchema::$table_name;
		$note_table  = $wpdb->prefix . ContactNoteSchema::$table_name;
		$pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		// Clean up form entries per contact (no batch API available).
		foreach ( $ids as $id ) {
			ContactModel::remove_form_entries_for_deleted_contact( $id );
		}

		// Batch-clean related tables.
		QueryBuilder::table( $meta_table )
			->whereIn( 'contact_id', $ids )
			->delete();

		QueryBuilder::table( $note_table )
			->whereIn( 'contact_id', $ids )
			->delete();

		QueryBuilder::table( $pivot_table )
			->whereIn( 'contact_id', $ids )
			->delete();

		return parent::destroyMany( $ids );
	}

	/**
	 * Batch-load tags and lists for a page of contacts (N+1 elimination).
	 *
	 * Replaces the per-row calls to ContactGroupModel::get_tags_to_contact()
	 * and ContactGroupModel::get_lists_to_contact() with a single JOIN query.
	 *
	 * Query count is constant (1 query) regardless of page size.
	 *
	 * @since 1.19.5
	 *
	 * @param array $ids Array of contact IDs from the current page.
	 *
	 * @return array Keyed by contact_id, each entry containing 'tags' and 'lists'.
	 */
	public function withStatsQuery( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;

		$pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		// Single JOIN query: pivot → groups, filtered by contact IDs.
		$rows = QueryBuilder::table( $pivot_table )
			->select(
				$pivot_table . '.contact_id',
				$group_table . '.id',
				$group_table . '.title',
				$group_table . '.type'
			)
			->leftJoin(
				$group_table,
				$pivot_table . '.group_id',
				'=',
				$group_table . '.id'
			)
			->whereIn( $pivot_table . '.contact_id', $ids )
			->get();

		// Group results by contact_id → tags[] / lists[].
		$stats = array();
		foreach ( $ids as $id ) {
			$stats[ $id ] = array(
				'tags'  => array(),
				'lists' => array(),
			);
		}

		foreach ( $rows as $row ) {
			$contact_id = (int) $row['contact_id'];
			$type       = isset( $row['type'] ) ? $row['type'] : '';
			$group_id   = (int) $row['id'];
			$group      = array(
				'id'    => $group_id,
				'title' => isset( $row['title'] ) ? $row['title'] : '',
			);

			if ( 'tags' === $type && isset( $stats[ $contact_id ] ) ) {
				// Deduplicate: skip if this group id is already present (guards against duplicate pivot rows).
				$existing_ids = array_column( $stats[ $contact_id ]['tags'], 'id' );
				if ( ! in_array( $group_id, $existing_ids, true ) ) {
					$stats[ $contact_id ]['tags'][] = $group;
				}
			} elseif ( 'lists' === $type && isset( $stats[ $contact_id ] ) ) {
				// Deduplicate: skip if this group id is already present (guards against duplicate pivot rows).
				$existing_ids = array_column( $stats[ $contact_id ]['lists'], 'id' );
				if ( ! in_array( $group_id, $existing_ids, true ) ) {
					$stats[ $contact_id ]['lists'][] = $group;
				}
			}
		}

		return $stats;
	}

	/**
	 * List contacts with search, ordering, and batch tag/list loading.
	 *
	 * Overrides AbstractRepository::list() to add:
	 * - Hyphenated param mapping (per-page → per_page, order-by → order_by)
	 * - LIKE search across all searchable() columns
	 * - order_by / order support
	 * - withStatsQuery() called exactly once for N+1 elimination
	 *
	 * @since 1.19.5
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array {
	 *     @type array $data        Rows with merged tags and lists.
	 *     @type int   $total       Total matching rows.
	 *     @type int   $page        Current page.
	 *     @type int   $per_page    Items per page.
	 *     @type int   $total_pages Total pages.
	 * }
	 */
	public function list( array $params ): array {
		// Map hyphenated params to underscored equivalents.
		$param_map = array(
			'per-page' => 'per_page',
			'order-by' => 'order_by',
		);
		foreach ( $param_map as $from => $to ) {
			if ( isset( $params[ $from ] ) && ! isset( $params[ $to ] ) ) {
				$params[ $to ] = $params[ $from ];
			}
		}

		$page     = isset( $params['page'] ) && (int) $params['page'] > 0 ? (int) $params['page'] : 1;
		$per_page = isset( $params['per_page'] ) && (int) $params['per_page'] > 0 ? (int) $params['per_page'] : 10;
		$search   = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$order_by = isset( $params['order_by'] ) ? sanitize_text_field( $params['order_by'] ) : 'id';
		$order    = isset( $params['order'] ) && in_array( strtoupper( $params['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $params['order'] ) : 'DESC';

		/**
		 * Filters the list query parameters before building the query.
		 *
		 * @since 1.19.5
		 *
		 * @param array  $params Query parameters.
		 * @param string $entity Entity name ('contacts').
		 */
		$params = apply_filters( 'mailmint_repository_list_query', $params, $this->entityName() );

		global $wpdb;

		$table = $this->prefixedTable();

		// Build the WHERE clause for search.
		$where_sql = '';
		$bindings  = array();

		if ( ! empty( $search ) ) {
			$like_value  = '%' . $wpdb->esc_like( $search ) . '%';
			$searchable  = $this->searchable();
			$like_parts  = array();

			foreach ( $searchable as $column ) {
				$like_parts[] = "`{$column}` LIKE %s";
				$bindings[]   = $like_value;
			}

			// Also search concatenated first + last name (legacy behaviour).
			$like_parts[] = "CONCAT(`first_name`, ' ', `last_name`) LIKE %s";
			$bindings[]   = $like_value;

			$where_sql = 'WHERE (' . implode( ' OR ', $like_parts ) . ')';
		}

		// Whitelist order_by column against table columns to prevent injection.
		$allowed_order_columns = array_merge( array( 'id', 'created_at', 'updated_at' ), $this->fillable(), $this->searchable() );
		$allowed_order_columns = array_unique( $allowed_order_columns );
		if ( ! in_array( $order_by, $allowed_order_columns, true ) ) {
			$order_by = 'id';
		}

		// Count total matching rows.
		$count_sql = "SELECT COUNT(id) FROM {$table} {$where_sql}";
		if ( ! empty( $bindings ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $bindings ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Fetch paginated data.
		$offset   = ( $page - 1 ) * $per_page;
		$data_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY `{$order_by}` {$order} LIMIT %d, %d";

		$data_bindings   = array_merge( $bindings, array( $offset, $per_page ) );
		$data_sql        = $wpdb->prepare( $data_sql, $data_bindings ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$data            = $wpdb->get_results( $data_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Collect IDs for batch stats.
		$ids = array_map( 'intval', array_column( $data, 'id' ) );

		// Call withStatsQuery exactly once with all page IDs.
		$stats = ! empty( $ids ) ? $this->withStatsQuery( $ids ) : array();

		// Merge tags and lists into each contact row.
		foreach ( $data as &$row ) {
			$row_id = (int) $row['id'];
			if ( isset( $stats[ $row_id ] ) ) {
				$row['tags']  = $stats[ $row_id ]['tags'];
				$row['lists'] = $stats[ $row_id ]['lists'];
			} else {
				$row['tags']  = array();
				$row['lists'] = array();
			}
		}
		unset( $row );

		return array(
			'data'        => $data,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}
}
