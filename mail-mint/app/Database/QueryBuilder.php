<?php
/**
 * Fluent query builder wrapping $wpdb with auto-prepare.
 *
 * @package Mint\MRM\Database
 */

namespace Mint\MRM\Database;

use InvalidArgumentException;

/**
 * QueryBuilder provides a fluent interface for building and executing
 * database queries via $wpdb with automatic prepare() on all user values.
 *
 * Usage:
 *   $rows = QueryBuilder::table('mint_contacts')
 *       ->select('id', 'email')
 *       ->where('status', '=', 'subscribed')
 *       ->orderBy('id', 'DESC')
 *       ->limit(10)
 *       ->get();
 *
 * @since 1.19.5
 */
class QueryBuilder {

	/**
	 * Allowed comparison operators for WHERE clauses.
	 *
	 * @var string[]
	 */
	private const ALLOWED_OPERATORS = array(
		'=', '!=', '<>', '<', '>', '<=', '>=',
		'LIKE', 'NOT LIKE', 'IS', 'IS NOT',
	);

	/**
	 * Target table name.
	 *
	 * @var string
	 */
	private $table = '';

	/**
	 * SELECT columns.
	 *
	 * @var string[]
	 */
	private $columns = array( '*' );

	/**
	 * WHERE clauses.
	 *
	 * @var array
	 */
	private $wheres = array();

	/**
	 * WHERE IN clauses.
	 *
	 * @var array
	 */
	private $where_ins = array();

	/**
	 * JOIN clauses.
	 *
	 * @var array
	 */
	private $joins = array();

	/**
	 * ORDER BY column.
	 *
	 * @var string|null
	 */
	private $order_by = null;

	/**
	 * ORDER BY direction.
	 *
	 * @var string
	 */
	private $order_dir = 'DESC';

	/**
	 * GROUP BY column.
	 *
	 * @var string|null
	 */
	private $group_by = null;

	/**
	 * LIMIT value.
	 *
	 * @var int|null
	 */
	private $limit_val = null;

	/**
	 * OFFSET value.
	 *
	 * @var int|null
	 */
	private $offset_val = null;

	/**
	 * Collected bindings for $wpdb->prepare().
	 *
	 * @var array
	 */
	private $bindings = array();

	/**
	 * Create a new QueryBuilder instance scoped to the given table.
	 *
	 * @param string $table Table name.
	 * @return self
	 * @throws InvalidArgumentException If table name is empty.
	 */
	public static function table( string $table ): self {
		if ( '' === trim( $table ) ) {
			throw new InvalidArgumentException( 'Table name cannot be empty.' );
		}

		$instance        = new self();
		$instance->table = $table;
		return $instance;
	}

	/**
	 * Set the columns to select.
	 *
	 * @param string ...$columns Column names.
	 * @return $this
	 */
	public function select( string ...$columns ): self {
		if ( ! empty( $columns ) ) {
			$this->columns = array_map( array( $this, 'sanitize_select_column' ), $columns );
		}
		return $this;
	}

	/**
	 * Add a WHERE clause.
	 *
	 * @param string $column   Column name.
	 * @param string $operator Comparison operator.
	 * @param mixed  $value    Value to compare.
	 * @return $this
	 */
	public function where( string $column, string $operator, $value ): self {
		$operator = strtoupper( trim( $operator ) );

		if ( ! in_array( $operator, self::ALLOWED_OPERATORS, true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid SQL operator: %s', $operator )
			);
		}

		$this->wheres[] = array(
			'column'   => $this->sanitize_identifier( $column ),
			'operator' => $operator,
			'value'    => $value,
		);
		return $this;
	}

	/**
	 * Add a WHERE IN clause.
	 *
	 * Skips the clause if the values array is empty.
	 *
	 * @param string $column Column name.
	 * @param array  $values Values for the IN clause.
	 * @return $this
	 */
	public function whereIn( string $column, array $values ): self {
		if ( empty( $values ) ) {
			return $this;
		}
		$this->where_ins[] = array(
			'column' => $this->sanitize_identifier( $column ),
			'values' => $values,
		);
		return $this;
	}

	/**
	 * Add a LEFT JOIN clause.
	 *
	 * @param string $table    Table to join.
	 * @param string $left_col Left column.
	 * @param string $op       Operator.
	 * @param string $right_col Right column.
	 * @return $this
	 */
	public function leftJoin( string $table, string $left_col, string $op, string $right_col ): self {
		$this->joins[] = array(
			'type'      => 'LEFT',
			'table'     => $table,
			'left_col'  => $this->sanitize_identifier( $left_col ),
			'op'        => $op,
			'right_col' => $this->sanitize_identifier( $right_col ),
		);
		return $this;
	}

	/**
	 * Add an INNER JOIN clause.
	 *
	 * @since 1.20.0
	 *
	 * @param string $table    Table to join.
	 * @param string $left_col Left column.
	 * @param string $op       Operator.
	 * @param string $right_col Right column.
	 * @return $this
	 */
	public function innerJoin( string $table, string $left_col, string $op, string $right_col ): self {
		$this->joins[] = array(
			'type'      => 'INNER',
			'table'     => $table,
			'left_col'  => $this->sanitize_identifier( $left_col ),
			'op'        => $op,
			'right_col' => $this->sanitize_identifier( $right_col ),
		);
		return $this;
	}

	/**
	 * Set ORDER BY clause.
	 *
	 * @param string $col Column name.
	 * @param string $dir Direction (ASC or DESC).
	 * @return $this
	 */
	public function orderBy( string $col, string $dir = 'DESC' ): self {
		$this->order_by  = $this->sanitize_identifier( $col );
		$this->order_dir = strtoupper( $dir ) === 'ASC' ? 'ASC' : 'DESC';
		return $this;
	}

	/**
	 * Set GROUP BY clause.
	 *
	 * @param string $col Column name.
	 * @return $this
	 */
	public function groupBy( string $col ): self {
		$this->group_by = $this->sanitize_identifier( $col );
		return $this;
	}

	/**
	 * Set LIMIT.
	 *
	 * @param int $n Limit value.
	 * @return $this
	 */
	public function limit( int $n ): self {
		$this->limit_val = $n;
		return $this;
	}

	/**
	 * Set OFFSET.
	 *
	 * @param int $n Offset value.
	 * @return $this
	 */
	public function offset( int $n ): self {
		$this->offset_val = $n;
		return $this;
	}

	/**
	 * Execute a SELECT query and return all matching rows.
	 *
	 * @return array
	 */
	public function get(): array {
		global $wpdb;

		$sql = $this->build_select_sql();

		if ( ! empty( $this->bindings ) ) {
			$sql = $wpdb->prepare( $sql, $this->bindings ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Execute a SELECT query and return the first row.
	 *
	 * @return array|null
	 */
	public function first(): ?array {
		$this->limit_val = 1;
		$rows            = $this->get();
		return ! empty( $rows ) ? $rows[0] : null;
	}

	/**
	 * Execute a COUNT query and return the total.
	 *
	 * @return int
	 */
	public function count(): int {
		global $wpdb;

		$saved_columns = $this->columns;
		$saved_limit   = $this->limit_val;
		$saved_offset  = $this->offset_val;

		$this->columns    = array( 'COUNT(*) as cnt' );
		$this->limit_val  = null;
		$this->offset_val = null;

		$sql = $this->build_select_sql();

		$this->columns    = $saved_columns;
		$this->limit_val  = $saved_limit;
		$this->offset_val = $saved_offset;

		if ( ! empty( $this->bindings ) ) {
			$sql = $wpdb->prepare( $sql, $this->bindings ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $row ? (int) $row['cnt'] : 0;
	}

	/**
	 * Execute a paginated SELECT query.
	 *
	 * Returns an array with keys: data, total, page, per_page, total_pages.
	 *
	 * @param int $page    Current page (1-based).
	 * @param int $per_page Items per page.
	 * @return array
	 */
	public function paginate( int $page, int $per_page ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );

		// Clone bindings before count query consumes them.
		$saved_bindings  = $this->bindings;
		$saved_limit     = $this->limit_val;
		$saved_offset    = $this->offset_val;

		$total = $this->count();

		// Restore state for the data query.
		$this->bindings   = $saved_bindings;
		$this->limit_val  = $per_page;
		$this->offset_val = ( $page - 1 ) * $per_page;

		$data = $this->get();

		// Restore original limit/offset.
		$this->limit_val  = $saved_limit;
		$this->offset_val = $saved_offset;

		return array(
			'data'        => $data,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Insert a single row and return the insert ID.
	 *
	 * @param array $data Column => value pairs.
	 *
	 * @return int|\WP_Error Insert ID, or WP_Error on SQL failure.
	 */
	public function insert( array $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return 0;
		}

		$columns      = array_map( array( $this, 'sanitize_identifier' ), array_keys( $data ) );
		$placeholders = array_map( array( $this, 'get_placeholder' ), array_values( $data ) );
		$values       = array_values( $data );

		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$this->table,
			implode( ', ', $columns ),
			implode( ', ', $placeholders )
		);

		$sql    = $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			return new \WP_Error(
				'db_insert_failed',
				$wpdb->last_error ?: __( 'Database insert query failed.', 'mrm' )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert multiple rows in chunks.
	 *
	 * @param array $rows       Array of associative arrays.
	 * @param int   $chunk_size Rows per chunk to avoid packet limits.
	 *
	 * @return int|\WP_Error Total rows inserted, or WP_Error on SQL failure
	 *                       (error data includes 'inserted_before_failure' count).
	 */
	public function batchInsert( array $rows, int $chunk_size = 500 ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return 0;
		}

		$total_inserted = 0;
		$columns        = array_map( array( $this, 'sanitize_identifier' ), array_keys( $rows[0] ) );
		$raw_columns    = array_keys( $rows[0] );
		$chunks         = array_chunk( $rows, $chunk_size );

		foreach ( $chunks as $chunk ) {
			$value_strings = array();
			$flat_values   = array();

			foreach ( $chunk as $row ) {
				$placeholders = array();
				foreach ( $raw_columns as $col ) {
					$val            = isset( $row[ $col ] ) ? $row[ $col ] : null;
					$placeholders[] = $this->get_placeholder( $val );
					$flat_values[]  = $val;
				}
				$value_strings[] = '(' . implode( ', ', $placeholders ) . ')';
			}

			$sql = sprintf(
				'INSERT INTO %s (%s) VALUES %s',
				$this->table,
				implode( ', ', $columns ),
				implode( ', ', $value_strings )
			);

			$sql    = $wpdb->prepare( $sql, $flat_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( false === $result ) {
				return new \WP_Error(
					'db_batch_insert_failed',
					$wpdb->last_error ?: __( 'Database batch insert query failed.', 'mrm' ),
					array( 'inserted_before_failure' => $total_inserted )
				);
			}

			$total_inserted += $result;
		}

		return $total_inserted;
	}

	/**
	 * Execute an UPDATE query and return affected rows.
	 *
	 * @param array $data Column => value pairs to update.
	 *
	 * @return int|\WP_Error Affected rows, or WP_Error on SQL failure.
	 */
	public function update( array $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return 0;
		}

		$set_parts   = array();
		$set_values  = array();

		foreach ( $data as $col => $val ) {
			$set_parts[]  = $this->sanitize_identifier( $col ) . ' = ' . $this->get_placeholder( $val );
			$set_values[] = $val;
		}

		$sql = sprintf(
			'UPDATE %s SET %s',
			$this->table,
			implode( ', ', $set_parts )
		);

		$sql .= $this->build_where_clause();

		// Merge SET values before WHERE bindings.
		$all_values = array_merge( $set_values, $this->bindings );

		if ( ! empty( $all_values ) ) {
			$sql = $wpdb->prepare( $sql, $all_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			return new \WP_Error(
				'db_update_failed',
				$wpdb->last_error ?: __( 'Database update query failed.', 'mrm' )
			);
		}

		return (int) $result;
	}

	/**
	 * Execute a DELETE query and return affected rows.
	 *
	 * @return int|\WP_Error Affected rows, or WP_Error on SQL failure.
	 */
	public function delete() {
		global $wpdb;

		$sql = sprintf( 'DELETE FROM %s', $this->table );
		$sql .= $this->build_where_clause();

		if ( ! empty( $this->bindings ) ) {
			$sql = $wpdb->prepare( $sql, $this->bindings ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $result ) {
			return new \WP_Error(
				'db_delete_failed',
				$wpdb->last_error ?: __( 'Database delete query failed.', 'mrm' )
			);
		}

		return (int) $result;
	}

	/**
	 * Build the full SELECT SQL string (unprepared).
	 *
	 * @return string
	 */
	private function build_select_sql(): string {
		$sql = sprintf(
			'SELECT %s FROM %s',
			implode( ', ', $this->columns ),
			$this->table
		);

		foreach ( $this->joins as $join ) {
			$sql .= sprintf(
				' %s JOIN %s ON %s %s %s',
				$join['type'],
				$join['table'],
				$join['left_col'],
				$join['op'],
				$join['right_col']
			);
		}

		$sql .= $this->build_where_clause();

		if ( null !== $this->group_by ) {
			$sql .= ' GROUP BY ' . $this->group_by;
		}

		if ( null !== $this->order_by ) {
			$sql .= ' ORDER BY ' . $this->order_by . ' ' . $this->order_dir;
		}

		if ( null !== $this->limit_val ) {
			$sql .= ' LIMIT ' . (int) $this->limit_val;
		}

		if ( null !== $this->offset_val ) {
			$sql .= ' OFFSET ' . (int) $this->offset_val;
		}

		return $sql;
	}

	/**
	 * Build the WHERE clause portion of a SQL string.
	 *
	 * @return string
	 */
	private function build_where_clause(): string {
		$parts = array();
		$this->bindings = array();

		foreach ( $this->wheres as $where ) {
			$parts[]          = sprintf(
				'%s %s %s',
				$where['column'],
				$where['operator'],
				$this->get_placeholder_for_binding()
			);
			$this->bindings[] = $where['value'];
		}

		foreach ( $this->where_ins as $in ) {
			$placeholders = implode(
				', ',
				array_fill( 0, count( $in['values'] ), '%s' )
			);
			$parts[] = sprintf( '%s IN (%s)', $in['column'], $placeholders );
			foreach ( $in['values'] as $val ) {
				$this->bindings[] = $val;
			}
		}

		if ( empty( $parts ) ) {
			return '';
		}

		return ' WHERE ' . implode( ' AND ', $parts );
	}

	/**
	 *
	 * @since 1.19.5
	 *
	 * @param string $identifier Raw identifier.
	 * @return string Backtick-wrapped identifier.
	 * @throws InvalidArgumentException If identifier contains invalid characters.
	 */
	private function sanitize_identifier( string $identifier ): string {
		// Handle dot-notation (table.column) — split, validate each part, wrap individually.
		if ( false !== strpos( $identifier, '.' ) ) {
			$parts = explode( '.', $identifier );
			if ( count( $parts ) !== 2 ) {
				throw new InvalidArgumentException(
					sprintf( 'Invalid SQL identifier: %s', $identifier )
				);
			}
			return $this->sanitize_identifier( $parts[0] ) . '.' . $this->sanitize_identifier( $parts[1] );
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $identifier ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid SQL identifier: %s', $identifier )
			);
		}
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * @since 1.19.5
	 *
	 * @param string $column Column name or expression.
	 * @return string Sanitized column.
	 */
	private function sanitize_select_column( string $column ): string {
		// Pass through wildcards and expressions (always internal/hardcoded).
		if ( false !== strpos( $column, '*' ) || false !== strpos( $column, '(' ) ) {
			return $column;
		}

		// Handle "column AS alias" or "column as alias" syntax.
		if ( preg_match( '/^(.+)\s+[Aa][Ss]\s+(\w+)$/', $column, $matches ) ) {
			return $this->sanitize_select_column( trim( $matches[1] ) ) . ' as ' . $this->sanitize_identifier( $matches[2] );
		}

		return $this->sanitize_identifier( $column );
	}

	/**
	 * Get the appropriate prepare placeholder for a value.
	 *
	 * @param mixed $value The value to determine placeholder for.
	 * @return string %d for integers, %f for floats, %s for everything else.
	 */
	private function get_placeholder( $value ): string {
		if ( is_int( $value ) ) {
			return '%d';
		}
		if ( is_float( $value ) ) {
			return '%f';
		}
		return '%s';
	}

	/**
	 * Return a generic string placeholder for WHERE bindings.
	 *
	 * We use %s universally in WHERE clauses since $wpdb->prepare()
	 * handles type coercion and escaping for all types via %s.
	 *
	 * @return string
	 */
	private function get_placeholder_for_binding(): string {
		return '%s';
	}

	/**
	 * Execute a callable inside a database transaction.
	 *
	 * Calls START TRANSACTION before the callback and COMMIT after.
	 * If the callback throws an exception or returns a WP_Error,
	 * ROLLBACK is issued and the exception is re-thrown (or WP_Error returned).
	 *
	 * Usage:
	 *   $result = QueryBuilder::transaction( function () use ( $ids, $data ) {
	 *       QueryBuilder::table('pivot')->whereIn('group_id', $ids)->delete();
	 *       return QueryBuilder::table('groups')->whereIn('id', $ids)->delete();
	 *   });
	 *
	 * @since 1.19.5
	 *
	 * @param callable $callback The work to execute inside the transaction.
	 *
	 * @return mixed Whatever the callback returns.
	 *
	 * @throws \Exception Re-throws any exception from the callback after rollback.
	 */
	public static function transaction( callable $callback ) {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			$result = $callback();

			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}

			$wpdb->query( 'COMMIT' );
			return $result;
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}
}
