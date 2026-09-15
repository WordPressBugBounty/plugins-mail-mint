<?php
/**
 * ContactGroupRepository — SOLID repository for lists and tags.
 *
 * Shared repository for both lists and tags, differentiated by a $type
 * constructor argument. Replaces legacy ContactGroupModel static methods
 * for list/tag CRUD operations.
 *
 * @package Mint\MRM\Database\Repositories
 * @since   1.19.5
 */

namespace Mint\MRM\Database\Repositories;

use Mint\MRM\Database\AbstractRepository;
use Mint\MRM\Database\QueryBuilder;
use Mint\MRM\Database\Traits\CacheableTrait;

/**
 * Class ContactGroupRepository
 *
 * @since 1.19.5
 */
class ContactGroupRepository extends AbstractRepository {

	use CacheableTrait;

	/**
	 * Sort key that orders rows by how many contacts they hold.
	 *
	 * Not a column on the groups table — it is derived, so it takes the
	 * dedicated query path in listOrderedByContactCount().
	 *
	 * @since 1.31.2
	 *
	 * @var string
	 */
	public const ORDER_BY_CONTACT_COUNT = 'total_contacts';

	/**
	 * Sort keys the list endpoint accepts.
	 *
	 * Anything else falls back to 'id'. Without this an unknown key reaches
	 * MySQL as a bare column name and the query fails, blanking the table.
	 *
	 * @since 1.31.2
	 *
	 * @var string[]
	 */
	private const SORTABLE = array( 'id', 'title', 'type', 'created_at', 'updated_at', self::ORDER_BY_CONTACT_COUNT );

	/**
	 * Contact <-> group pivot table, without the site prefix.
	 *
	 * @since 1.31.2
	 *
	 * @var string
	 */
	protected const PIVOT_TABLE = 'mint_contact_group_relationship';

	/**
	 * Group type: 'lists' or 'tags'.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Constructor.
	 *
	 * @since 1.19.5
	 *
	 * @param string $type Group type — 'lists' (default) or 'tags'.
	 */
	public function __construct( string $type = 'lists' ) {
		$this->type = $type;
		$this->enableCache( 300 );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tableName(): string {
		return 'mint_contact_groups';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function fillable(): array {
		return array( 'title', 'type', 'data' );
	}

	/**
	 * Override: force $this->type into the type column on insert.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data Column values.
	 *
	 * @return int Inserted row ID.
	 */
	public function create( array $data ): int {
		$data['type'] = $this->type;
		$id           = parent::create( $data );
		$this->invalidateListCaches();
		return $id;
	}

	/**
	 * Override: force $this->type into the type column on update.
	 *
	 * @since 1.19.5
	 *
	 * @param int   $id   Entity ID.
	 * @param array $data Column values.
	 *
	 * @return int Affected rows.
	 */
	public function update( int $id, array $data ): int {
		$data['type'] = $this->type;
		$result       = parent::update( $id, $data );
		$this->invalidateListCaches();
		return $result;
	}

	/**
	 * Override: delete pivot rows and the group inside a transaction.
	 *
	 * @since 1.19.5
	 *
	 * @param int $id Entity ID.
	 *
	 * @return int Affected rows.
	 */
	public function destroy( int $id ): int {
		$result = QueryBuilder::transaction( function () use ( $id ) {
			$this->deleteRelationships( array( $id ) );
			return parent::destroy( $id );
		} );
		$this->invalidateListCaches();
		return $result;
	}

	/**
	 * Override: delete pivot rows and groups inside a transaction.
	 *
	 * @since 1.19.5
	 *
	 * @param array $ids Entity IDs.
	 *
	 * @return int Affected rows.
	 */
	public function destroyMany( array $ids ): int {
		if ( empty( $ids ) ) {
			return 0;
		}
		$result = QueryBuilder::transaction( function () use ( $ids ) {
			$this->deleteRelationships( $ids );
			return parent::destroyMany( $ids );
		} );
		$this->invalidateListCaches();
		return $result;
	}

	/**
	 * Override: scope all list queries to $this->type.
	 *
	 * Builds the query directly with type scoping, calls withStatsQuery()
	 * to batch-load total_contacts, and merges stats into each row.
	 *
	 * @since 1.19.5
	 *
	 * @param array $params Query parameters.
	 *
	 * @return array Paginated result with merged stats.
	 */
	public function list( array $params ): array {
		$page     = isset( $params['page'] ) && (int) $params['page'] > 0 ? (int) $params['page'] : 1;
		$per_page = isset( $params['per_page'] ) && (int) $params['per_page'] > 0 ? (int) $params['per_page'] : 10;
		$search   = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$order_by = isset( $params['order_by'] ) ? (string) $params['order_by'] : 'id';
		$order    = isset( $params['order'] ) ? strtoupper( $params['order'] ) : 'DESC';

		if ( ! in_array( $order_by, self::SORTABLE, true ) ) {
			$order_by = 'id';
		}

		/**
		 * Filters the list query parameters before building the query.
		 *
		 * @since 1.19.5
		 *
		 * @param array  $params     Query parameters.
		 * @param string $entityName Entity name derived from table.
		 */
		$params = apply_filters( 'mailmint_repository_list_query', $params, $this->entityName() );

		if ( self::ORDER_BY_CONTACT_COUNT === $order_by ) {
			return $this->listOrderedByContactCount(
				array(
					'page'     => $page,
					'per_page' => $per_page,
					'search'   => $search,
					'order'    => $order,
				)
			);
		}

		$query = $this->baseListQuery( $search )->orderBy( $order_by, $order );

		$result = $query->paginate( $page, $per_page );

		// Batch-load total_contacts for all IDs on this page.
		$ids   = array_map( 'intval', array_column( $result['data'], 'id' ) );
		$stats = ! empty( $ids ) ? $this->withStatsQuery( $ids ) : array();

		$result['data'] = $this->mergeStats( $result['data'], $stats );

		return $result;
	}

	/**
	 * List a page of rows ordered by how many contacts each one holds.
	 *
	 * Lists and tags keep their membership in the pivot table, so the count is
	 * computed inside the same query as a correlated subquery and sorted on.
	 * Doing it in SQL rather than post-sorting the page is what makes the
	 * ordering span the whole result set instead of just the rows on screen.
	 *
	 * Pro overrides this for segments, whose counts come from filter rules
	 * rather than the pivot table.
	 *
	 * @since 1.31.2
	 *
	 * @param array $args Normalised query args: page, per_page, search, order.
	 *
	 * @return array Paginated result in the same shape QueryBuilder::paginate() returns.
	 */
	protected function listOrderedByContactCount( array $args ): array {
		global $wpdb;

		$table = $this->prefixedTable();
		$pivot = $wpdb->prefix . self::PIVOT_TABLE;

		$count_column = "( SELECT COUNT(DISTINCT rel.contact_id) FROM {$pivot} AS rel WHERE rel.group_id = {$table}.id ) as total_contacts";

		$rows = $this->baseListQuery( $args['search'] )
			->select( '*', $count_column )
			->orderBy( self::ORDER_BY_CONTACT_COUNT, $args['order'] )
			// Counts tie constantly (every empty list holds zero), so without a
			// stable tiebreaker rows drift between pages.
			->thenOrderBy( 'id', 'DESC' )
			->limit( $args['per_page'] )
			->offset( ( $args['page'] - 1 ) * $args['per_page'] )
			->get();

		foreach ( $rows as &$row ) {
			$row['total_contacts'] = isset( $row['total_contacts'] ) ? (int) $row['total_contacts'] : 0;
		}
		unset( $row );

		$total = $this->baseListQuery( $args['search'] )->count();

		return array(
			'data'        => $rows,
			'total'       => $total,
			'page'        => $args['page'],
			'per_page'    => $args['per_page'],
			'total_pages' => (int) ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Build the shared SELECT scaffold for a list query: type scope + search.
	 *
	 * @since 1.31.2
	 *
	 * @param string $search Optional title search term.
	 *
	 * @return QueryBuilder
	 */
	protected function baseListQuery( string $search = '' ): QueryBuilder {
		$query = QueryBuilder::table( $this->prefixedTable() )
			->where( 'type', '=', $this->type );

		if ( '' !== $search ) {
			$query->where( 'title', 'LIKE', '%' . $search . '%' );
		}

		return $query;
	}

	/**
	 * Merge batch-loaded stats into a page of rows, defaulting missing counts to 0.
	 *
	 * @since 1.31.2
	 *
	 * @param array $rows  Rows from the list query.
	 * @param array $stats Stat rows keyed by an 'id' member.
	 *
	 * @return array Rows with stats merged in.
	 */
	private function mergeStats( array $rows, array $stats ): array {
		$stats_by_id = array();
		foreach ( $stats as $stat ) {
			if ( isset( $stat['id'] ) ) {
				$stats_by_id[ $stat['id'] ] = $stat;
			}
		}

		foreach ( $rows as &$row ) {
			if ( isset( $row['id'], $stats_by_id[ $row['id'] ] ) ) {
				$row = array_merge( $row, $stats_by_id[ $row['id'] ] );
			}
			// COUNT() comes back from $wpdb as a string; cast so both this path
			// and listOrderedByContactCount() hand the API the same type.
			$row['total_contacts'] = isset( $row['total_contacts'] ) ? (int) $row['total_contacts'] : 0;
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Batch-load total_contacts for a page of group IDs.
	 *
	 * Single query regardless of page size — N+1 killer.
	 *
	 * @since 1.19.5
	 *
	 * @param int[] $ids Group IDs from the current page.
	 *
	 * @return array Array of [{id, total_contacts}, ...]
	 */
	public function withStatsQuery( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;
		$pivot_table = $wpdb->prefix . self::PIVOT_TABLE;

		return QueryBuilder::table( $pivot_table )
			->select( 'group_id as id', 'COALESCE(COUNT(DISTINCT contact_id), 0) as total_contacts' )
			->whereIn( 'group_id', $ids )
			->groupBy( 'group_id' )
			->get();
	}

	/**
	 * Get all groups of this type for dropdown (id + title only).
	 *
	 * @since 1.19.5
	 *
	 * @return array Array of [{id, title}, ...]
	 */
	public function allForDropdown(): array {
		return QueryBuilder::table( $this->prefixedTable() )
			->select( 'id', 'title' )
			->where( 'type', '=', $this->type )
			->orderBy( 'title', 'ASC' )
			->get();
	}

	/**
	 * Count all groups of a given type.
	 *
	 * @since 1.19.5
	 *
	 * @param string $type Group type.
	 *
	 * @return int
	 */
	public function countByType( string $type ): int {
		return QueryBuilder::table( $this->prefixedTable() )
			->where( 'type', '=', $type )
			->count();
	}

	/**
	 * Drop the caches that a write to this group type invalidates.
	 *
	 * Subclasses override it to add their own derived caches — Pro's segment
	 * repository also caches per-segment contact counts.
	 *
	 * @since 1.31.2
	 *
	 * @return void
	 */
	protected function invalidateListCaches(): void {
		$this->invalidateCache( "contact_group_{$this->type}_list" );
	}

	/**
	 * Delete pivot rows from mint_contact_group_relationship.
	 *
	 * @since 1.19.5
	 *
	 * @param int[] $group_ids Group IDs to clean up.
	 *
	 * @return void
	 */
	private function deleteRelationships( array $group_ids ): void {
		if ( empty( $group_ids ) ) {
			return;
		}
		global $wpdb;
		QueryBuilder::table( $wpdb->prefix . self::PIVOT_TABLE )
			->whereIn( 'group_id', $group_ids )
			->delete();
	}
}
