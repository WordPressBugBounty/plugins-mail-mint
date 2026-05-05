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
		$this->invalidateCache( "contact_group_{$this->type}_list" );
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
		$this->invalidateCache( "contact_group_{$this->type}_list" );
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
		$this->invalidateCache( "contact_group_{$this->type}_list" );
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
		$this->invalidateCache( "contact_group_{$this->type}_list" );
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
		$order_by = isset( $params['order_by'] ) ? $params['order_by'] : 'id';
		$order    = isset( $params['order'] ) ? strtoupper( $params['order'] ) : 'DESC';

		/**
		 * Filters the list query parameters before building the query.
		 *
		 * @since 1.19.5
		 *
		 * @param array  $params     Query parameters.
		 * @param string $entityName Entity name derived from table.
		 */
		$params = apply_filters( 'mailmint_repository_list_query', $params, $this->entityName() );

		$query = QueryBuilder::table( $this->prefixedTable() )
			->where( 'type', '=', $this->type );

		if ( ! empty( $search ) ) {
			$query->where( 'title', 'LIKE', '%' . $search . '%' );
		}

		$query->orderBy( $order_by, $order );

		$result = $query->paginate( $page, $per_page );

		// Batch-load total_contacts for all IDs on this page.
		$ids   = array_map( 'intval', array_column( $result['data'], 'id' ) );
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
				} else {
					$row['total_contacts'] = 0;
				}
			}
			unset( $row );
		} else {
			foreach ( $result['data'] as &$row ) {
				$row['total_contacts'] = 0;
			}
			unset( $row );
		}

		return $result;
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
		$pivot_table = $wpdb->prefix . 'mint_contact_group_relationship';

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
		QueryBuilder::table( $wpdb->prefix . 'mint_contact_group_relationship' )
			->whereIn( 'group_id', $group_ids )
			->delete();
	}
}
