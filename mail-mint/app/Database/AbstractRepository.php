<?php
/**
 * AbstractRepository — Base CRUD repository for all Mail Mint modules.
 *
 * Provides inherited find, create, update, destroy, destroyMany, and list
 * operations so that concrete repositories only declare tableName(), fillable(),
 * and optionally override withStatsQuery().
 *
 * @package Mint\MRM\Database
 * @since   1.19.5
 */

namespace Mint\MRM\Database;

use Mint\MRM\Database\QueryBuilder;

/**
 * Class AbstractRepository
 *
 * @since 1.19.5
 */
abstract class AbstractRepository {

	/**
	 * Return the full table name (without WP prefix).
	 *
	 * Example: 'mint_campaigns'
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	abstract protected function tableName(): string;

	/**
	 * Return the list of columns that are mass-assignable.
	 *
	 * @since 1.19.5
	 *
	 * @return array
	 */
	abstract protected function fillable(): array;

	/**
	 * Derive a short entity name from the table name.
	 *
	 * Strips the 'mint_' prefix so that hook names stay consistent.
	 * Example: 'mint_campaigns' → 'campaigns'
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	public function entityName(): string {
		$table = $this->tableName();
		// Strip 'mint_' prefix if present.
		if ( 0 === strpos( $table, 'mint_' ) ) {
			return substr( $table, 5 );
		}
		return $table;
	}

	/**
	 * Return the prefixed table name for use with QueryBuilder.
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	protected function prefixedTable(): string {
		global $wpdb;
		return $wpdb->prefix . $this->tableName();
	}

	/**
	 * Find a single entity by ID.
	 *
	 * @since 1.19.5
	 *
	 * @param int $id Entity ID.
	 *
	 * @return array|null Row as associative array, or null if not found.
	 */
	public function find( int $id ): ?array {
		$row = QueryBuilder::table( $this->prefixedTable() )
			->where( 'id', '=', $id )
			->first();

		return $row ?: null;
	}

	/**
	 * Create a new entity.
	 *
	 * Filters input to fillable() fields only and auto-sets created_at.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data Column values.
	 *
	 * @return int|\WP_Error Inserted row ID, or WP_Error on SQL failure.
	 */
	public function create( array $data ) {
		$filtered               = $this->filterFillable( $data );
		$filtered['created_at'] = current_time( 'mysql' );

		return QueryBuilder::table( $this->prefixedTable() )
			->insert( $filtered );
	}

	/**
	 * Update an existing entity by ID.
	 *
	 * Filters input to fillable() fields only and auto-sets updated_at.
	 *
	 * @since 1.19.5
	 *
	 * @param int   $id   Entity ID.
	 * @param array $data Column values to update.
	 *
	 * @return int|\WP_Error Affected rows, or WP_Error on SQL failure.
	 */
	public function update( int $id, array $data ) {
		$filtered               = $this->filterFillable( $data );
		$filtered['updated_at'] = current_time( 'mysql' );

		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'id', '=', $id )
			->update( $filtered );
	}

	/**
	 * Delete a single entity by ID.
	 *
	 * @since 1.19.5
	 *
	 * @param int $id Entity ID.
	 *
	 * @return int|\WP_Error Affected rows (0 if not found), or WP_Error on SQL failure.
	 */
	public function destroy( int $id ) {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'id', '=', $id )
			->delete();
	}

	/**
	 * Delete multiple entities by IDs.
	 *
	 * @since 1.19.5
	 *
	 * @param array $ids Array of entity IDs.
	 *
	 * @return int|\WP_Error Affected rows (0 if $ids is empty or none found), or WP_Error on SQL failure.
	 */
	public function destroyMany( array $ids ) {
		if ( empty( $ids ) ) {
			return 0;
		}

		return QueryBuilder::table( $this->prefixedTable() )
			->whereIn( 'id', $ids )
			->delete();
	}

	/**
	 * List entities with pagination, optional filtering, and batch stats.
	 *
	 * Fires `mailmint_repository_list_query` filter before building the query.
	 * Calls withStatsQuery() exactly once with all IDs from the current page.
	 *
	 * Base implementation provides unfiltered pagination only. Concrete
	 * repositories should override to add entity-specific filtering,
	 * searching, and sorting.
	 *
	 * @since 1.19.5
	 *
	 * @param array $params {
	 *     Optional. Query parameters.
	 *
	 *     @type int $page     Page number. Default 1.
	 *     @type int $per_page Items per page. Default 10.
	 * }
	 *
	 * @return array {
	 *     @type array $data        Rows with merged stats.
	 *     @type int   $total       Total matching rows.
	 *     @type int   $page        Current page.
	 *     @type int   $per_page    Items per page.
	 *     @type int   $total_pages Total pages.
	 * }
	 */
	public function list( array $params ): array {
		/**
		 * Filters the list query parameters before building the query.
		 *
		 * @since 1.19.5
		 *
		 * @param array  $params     Query parameters.
		 * @param string $entityName Entity name derived from table.
		 */
		$params = apply_filters( 'mailmint_repository_list_query', $params, $this->entityName() );

		$page     = isset( $params['page'] ) && (int) $params['page'] > 0 ? (int) $params['page'] : 1;
		$per_page = isset( $params['per_page'] ) && (int) $params['per_page'] > 0 ? (int) $params['per_page'] : 10;

		$query = QueryBuilder::table( $this->prefixedTable() );

		$result = $query->paginate( $page, $per_page );

		// Collect IDs from the current page for batch stats (cast to int for type safety).
		$ids = array_map( 'intval', array_column( $result['data'], 'id' ) );

		// Call withStatsQuery exactly once with all page IDs.
		$stats = ! empty( $ids ) ? $this->withStatsQuery( $ids ) : array();

		// Merge stats into rows.
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

		return $result;
	}

	/**
	 * Batch-load stats for a page of entities.
	 *
	 * Default returns empty array. Subclasses override to load related
	 * counts/aggregates in a single query (N+1 killer pattern).
	 *
	 * @since 1.19.5
	 *
	 * @param array $ids Array of entity IDs from the current page.
	 *
	 * @return array Array of stat rows, each with an 'id' key for merging.
	 */
	public function withStatsQuery( array $ids ): array {
		return array();
	}

	/**
	 * Filter input data to only include fillable fields.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data Raw input data.
	 *
	 * @return array Filtered data with only fillable keys.
	 */
	protected function filterFillable( array $data ): array {
		return array_intersect_key( $data, array_flip( $this->fillable() ) );
	}
}
