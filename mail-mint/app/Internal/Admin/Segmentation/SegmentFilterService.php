<?php
/**
 * Mail Mint Segment Filter Service
 *
 * @author [MRM Team]
 * @email [support@rextheme.com]
 * @package Mint\MRM\Internal\Admin\Segmentation
 * @since 1.25.0
 */

namespace Mint\MRM\Internal\Admin\Segmentation;

use Mint\MRM\DataBase\Tables\ContactGroupPivotSchema;
use Mint\MRM\DataBase\Tables\ContactMetaSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;

/**
 * Segment Filter Service
 *
 * Evaluates segment filter rules and generates SQL WHERE clauses.
 * Supports 40+ filter operators for Contact Segment, Primary, Custom, and WooCommerce fields.
 *
 * @package Mint\MRM\Internal\Admin\Segmentation
 * @since 1.25.0
 */
class SegmentFilterService {

	/**
	 * Contact primary fields (direct column access)
	 *
	 * @var array
	 * @since 1.25.0
	 */
	private static $contact_primary_field = array(
		'email',
		'first_name',
		'last_name',
		'status',
		'list',
		'tag',
	);

	/**
	 * Build WHERE clause from segment filters using sequential accumulation.
	 *
	 * Traverses every filter in order. Each filter's logical connector joins it
	 * to the already-accumulated expression, not just to the adjacent filter.
	 *
	 * @param array $filters Segment filter array.
	 * @return string SQL WHERE clause.
	 * @since 1.25.0
	 */
	public function buildWhereClause( array $filters ) {
		if ( ! is_array( $filters ) || empty( $filters ) ) {
			return '';
		}

		$first = reset( $filters );

		// Grouped API format: {0: [{name, condition, value}, ...], 1: [...]} — sent by frontend.
		if ( is_array( $first ) && isset( $first[0] ) && is_array( $first[0] ) && isset( $first[0]['name'] ) ) {
			return $this->buildFromGroupedFormat( $filters );
		}

		// UI format: [{field:{value:...}, action_condition:{value:...}, action_values:{...}, condition_logic:'AND'}]
		if ( is_array( $first ) && isset( $first['field'] ) ) {
			return $this->buildFromUiFormat( $filters );
		}

		// Flat prepared format: [{name:..., condition:..., value:[...], condition_logic:'AND'}]
		if ( is_array( $first ) && isset( $first['name'] ) ) {
			return $this->buildFromFlatFormat( $filters );
		}

		return '';
	}

	/**
	 * Build WHERE from grouped API format.
	 *
	 * @param array $groups Grouped filters {0:[...], 1:[...]}.
	 * @return string SQL WHERE clause.
	 * @since 1.25.0
	 */
	private function buildFromGroupedFormat( array $groups ): string {
		$accumulated = '';

		foreach ( $groups as $group_index => $filter_group ) {
			if ( ! is_array( $filter_group ) ) {
				continue;
			}

			$is_first_in_group = true;

			foreach ( $filter_group as $filter ) {
				if ( ! isset( $filter['name'], $filter['condition'], $filter['value'] ) ) {
					continue;
				}

				$filter_name      = sanitize_text_field( $filter['name'] );
				$filter_condition = sanitize_text_field( $filter['condition'] );
				$filter_value     = is_array( $filter['value'] )
					? array_map( 'sanitize_text_field', $filter['value'] )
					: array( sanitize_text_field( $filter['value'] ) );
				$full_filter      = $filter;

				if ( ! method_exists( $this, $filter_condition ) ) {
					$is_first_in_group = false;
					continue;
				}

				if ( 'in_the_between_dates' === $filter_condition ) {
					$end_date = isset( $full_filter['endDate'] ) ? $full_filter['endDate'] : '';
					$sql      = $this->$filter_condition( $filter_name, $filter_value, $end_date );
				} else {
					$sql = $this->$filter_condition( $filter_name, $filter_value, $full_filter );
				}

				if ( '' === $accumulated ) {
					$accumulated = $sql;
				} elseif ( $is_first_in_group ) {
					$accumulated = '(' . $accumulated . ') OR ' . $sql;
				} else {
					$accumulated = '(' . $accumulated . ') AND ' . $sql;
				}

				$is_first_in_group = false;
			}
		}

		return $accumulated;
	}

	/**
	 * Build WHERE from UI format filters.
	 *
	 * @param array $filters UI format filter array.
	 * @return string SQL WHERE clause.
	 * @since 1.25.0
	 */
	private function buildFromUiFormat( array $filters ): string {
		$accumulated = '';

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field']['value'] ) || empty( $filter['action_condition']['value'] ) ) {
				continue;
			}

			$filter_name      = sanitize_text_field( $filter['field']['value'] );
			$filter_condition = sanitize_text_field( $filter['action_condition']['value'] );
			$condition_logic  = isset( $filter['condition_logic'] ) ? $filter['condition_logic'] : 'AND';

			if ( isset( $filter['action_values']['value'] ) && is_array( $filter['action_values']['value'] ) ) {
				$filter_value = array_map(
					function ( $item ) {
						return isset( $item['id'] ) ? $item['id'] : ( isset( $item['value'] ) ? $item['value'] : $item );
					},
					$filter['action_values']['value']
				);
			} else {
				$filter_value = isset( $filter['action_values']['value'] ) ? array( $filter['action_values']['value'] ) : array();
			}
			$filter_value = array_map( 'sanitize_text_field', $filter_value );

			$full_filter = array(
				'name'            => $filter_name,
				'condition'       => $filter_condition,
				'value'           => $filter_value,
				'condition_logic' => $condition_logic,
			);
			foreach ( array( 'endDate', 'operator', 'timeframe', 'timeframe_value', 'timeframe_value_end', 'timeframe_unit', 'count', 'score' ) as $extra_key ) {
				if ( isset( $filter['action_values'][ $extra_key ] ) ) {
					$full_filter[ $extra_key ] = $filter['action_values'][ $extra_key ];
				}
			}

			if ( ! method_exists( $this, $filter_condition ) ) {
				continue;
			}

			if ( 'in_the_between_dates' === $filter_condition ) {
				$end_date = isset( $full_filter['endDate'] ) ? $full_filter['endDate'] : '';
				$sql      = $this->$filter_condition( $filter_name, $filter_value, $end_date );
			} else {
				$sql = $this->$filter_condition( $filter_name, $filter_value, $full_filter );
			}

			if ( '' === $accumulated ) {
				$accumulated = $sql;
			} elseif ( 'OR' === $condition_logic ) {
				$accumulated = '(' . $accumulated . ') OR ' . $sql;
			} else {
				$accumulated = '(' . $accumulated . ') AND ' . $sql;
			}
		}

		return $accumulated;
	}

	/**
	 * Build WHERE from flat prepared format.
	 *
	 * @param array $filters Flat prepared filters.
	 * @return string SQL WHERE clause.
	 * @since 1.25.0
	 */
	private function buildFromFlatFormat( array $filters ): string {
		$accumulated = '';

		foreach ( $filters as $filter ) {
			if ( ! isset( $filter['name'], $filter['condition'], $filter['value'] ) ) {
				continue;
			}

			$filter_name      = sanitize_text_field( $filter['name'] );
			$filter_condition = sanitize_text_field( $filter['condition'] );
			$condition_logic  = isset( $filter['condition_logic'] ) ? $filter['condition_logic'] : 'AND';
			$filter_value     = is_array( $filter['value'] )
				? array_map( 'sanitize_text_field', $filter['value'] )
				: array( sanitize_text_field( $filter['value'] ) );
			$full_filter      = $filter;

			if ( ! method_exists( $this, $filter_condition ) ) {
				continue;
			}

			if ( 'in_the_between_dates' === $filter_condition ) {
				$end_date = isset( $full_filter['endDate'] ) ? $full_filter['endDate'] : '';
				$sql      = $this->$filter_condition( $filter_name, $filter_value, $end_date );
			} else {
				$sql = $this->$filter_condition( $filter_name, $filter_value, $full_filter );
			}

			if ( '' === $accumulated ) {
				$accumulated = $sql;
			} elseif ( 'OR' === $condition_logic ) {
				$accumulated = '(' . $accumulated . ') OR ' . $sql;
			} else {
				$accumulated = '(' . $accumulated . ') AND ' . $sql;
			}
		}

		return $accumulated;
	}

	/**
	 * Parse and validate filter rule
	 *
	 * @param array $rule Filter rule.
	 * @return array|\WP_Error Parsed rule or error.
	 * @since 1.25.0
	 */
	public function parseFilterRule( array $rule ) {
		if ( ! isset( $rule['name'] ) || ! isset( $rule['condition'] ) || ! isset( $rule['value'] ) ) {
			return new \WP_Error(
				'invalid_filter_rule',
				__( 'Filter rule must contain name, condition, and value fields.', 'mrm' )
			);
		}

		return array(
			'name'      => sanitize_text_field( $rule['name'] ),
			'condition' => sanitize_text_field( $rule['condition'] ),
			'value'     => is_array( $rule['value'] ) ? array_map( 'sanitize_text_field', $rule['value'] ) : array( sanitize_text_field( $rule['value'] ) ),
		);
	}

	/**
	 * Format and normalize filter rule
	 *
	 * @param array $rule Filter rule.
	 * @return array Formatted rule.
	 * @since 1.25.0
	 */
	public function formatFilterRule( array $rule ) {
		$formatted = array(
			'name'      => isset( $rule['name'] ) ? sanitize_text_field( $rule['name'] ) : '',
			'condition' => isset( $rule['condition'] ) ? sanitize_text_field( $rule['condition'] ) : '',
			'value'     => isset( $rule['value'] ) ? $rule['value'] : array(),
		);

		if ( ! is_array( $formatted['value'] ) ) {
			$formatted['value'] = array( $formatted['value'] );
		}

		return $formatted;
	}

	/**
	 * Get contacts matching WHERE clause
	 *
	 * @param string $where_clause SQL WHERE clause.
	 * @param array  $params Query parameters (page, per_page, order_by, order_type).
	 * @return array Paginated contact results.
	 * @since 1.25.0
	 */
	public function getContacts( $where_clause, $params = array() ) {
		global $wpdb;
		$contacts_table = $wpdb->prefix . ContactSchema::$table_name;

		$page       = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page   = isset( $params['per_page'] ) ? absint( $params['per_page'] ) : 25;
		$order_by   = isset( $params['order_by'] ) ? sanitize_text_field( $params['order_by'] ) : 'email';
		$order_type = isset( $params['order_type'] ) ? sanitize_text_field( $params['order_type'] ) : 'ASC';
		$count_only = isset( $params['count_only'] ) ? (bool) $params['count_only'] : false;
		$offset     = ( $page - 1 ) * $per_page;

		if ( $count_only ) {
			$query = $wpdb->prepare( 'SELECT COUNT(id) FROM %i AS c1 WHERE ', $contacts_table );
			$query .= $where_clause;
			return $wpdb->get_var( $query );
		}

		$allowed_order_by = array( 'id', 'email', 'first_name', 'last_name', 'status', 'created_at', 'updated_at' );
		$order_by         = in_array( $order_by, $allowed_order_by, true ) ? $order_by : 'email';
		$order_type       = strtoupper( $order_type ) === 'DESC' ? 'DESC' : 'ASC';

		$query  = $wpdb->prepare( 'SELECT * FROM %i AS c1 WHERE ', $contacts_table );
		$query .= $where_clause;
		$query .= $wpdb->prepare( " ORDER BY %i {$order_type}", $order_by );
		$query .= $wpdb->prepare( ' LIMIT %d, %d', $offset, $per_page );

		$count_query  = $wpdb->prepare( 'SELECT COUNT(id) FROM %i AS c1 WHERE ', $contacts_table );
		$count_query .= $where_clause;

		try {
			$data        = $wpdb->get_results( $query, ARRAY_A );
			$total_count = $wpdb->get_var( $count_query );

			if ( null === $data ) {
				return array(
					'data'        => array(),
					'total_count' => 0,
					'total_pages' => 0,
				);
			}

			return array(
				'data'        => $data,
				'total_count' => (int) $total_count,
				'total_pages' => $per_page > 0 ? ceil( $total_count / $per_page ) : 0,
			);
		} catch ( \Exception $e ) {
			return array(
				'data'        => array(),
				'total_count' => 0,
				'total_pages' => 0,
			);
		}
	}

	// ========================================
	// Filter Operator Methods
	// ========================================

	private function are_exactly_in( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = implode( ', ', $filter_value );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( 'c1.%i IN (%s) ', $filter_name, $filter_value );
		}

		return $this->get_meta_join_query( $filter_name, "('$filter_value')", 'IN' );
	}

	private function are_not_in( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = implode( ', ', $filter_value );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( 'c1.%i NOT IN (%s) ', $filter_name, $filter_value );
		}

		return $this->get_meta_join_query( $filter_name, "('$filter_value')", 'NOT IN' );
	}

	private function contains( $filter_name, $filter_value ) {
		global $wpdb;
		$query       = '';
		$filter_size = count( $filter_value );

		foreach ( $filter_value as $value ) {
			if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
				$query .= $wpdb->prepare( "c1.%i LIKE %s ", $filter_name, '%' . $wpdb->esc_like( $value ) . '%' );
				$query .= $filter_size > 1 ? 'OR ' : '';
			} else {
				$query .= $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $value ) . '%' ), 'LIKE' );
				$query .= $filter_size > 1 ? 'OR ' : '';
			}
		}

		return $query;
	}

	private function does_not_contain( $filter_name, $filter_value ) {
		global $wpdb;
		$query       = '';
		$filter_size = count( $filter_value );

		foreach ( $filter_value as $value ) {
			if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
				$query .= $wpdb->prepare( "c1.%i NOT LIKE %s ", $filter_name, '%' . $wpdb->esc_like( $value ) . '%' );
				$query .= $filter_size > 1 ? 'OR ' : '';
			} else {
				$query .= $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $value ) . '%' ), 'NOT LIKE' );
				$query .= $filter_size > 1 ? 'OR ' : '';
			}
		}

		return $query;
	}

	private function starts_with( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = implode( ', ', $filter_value );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( "c1.%i LIKE %s ", $filter_name, $wpdb->esc_like( $filter_value ) . '%' );
		}

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', $wpdb->esc_like( $filter_value ) . '%' ), 'LIKE' );
	}

	private function does_not_start_with( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = implode( ', ', $filter_value );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( "c1.%i NOT LIKE %s ", $filter_name, $wpdb->esc_like( $filter_value ) . '%' );
		}

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', $wpdb->esc_like( $filter_value ) . '%' ), 'NOT LIKE' );
	}

	private function ends_with( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = implode( ', ', $filter_value );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( "c1.%i LIKE %s ", $filter_name, '%' . $wpdb->esc_like( $filter_value ) );
		}

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $filter_value ) ), 'LIKE' );
	}

	private function does_not_end_with( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = implode( ', ', $filter_value );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( "c1.%i NOT LIKE %s ", $filter_name, '%' . $wpdb->esc_like( $filter_value ) );
		}

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $filter_value ) ), 'NOT LIKE' );
	}

	private function have_been_entered( $filter_name ) {
		global $wpdb;

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( "c1.%i <> '' ", $filter_name );
		}

		return $this->get_meta_join_query( $filter_name, "''", '<>' );
	}

	private function have_not_been_entered( $filter_name ) {
		global $wpdb;

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			return $wpdb->prepare( "c1.%i = '' ", $filter_name );
		}

		return $this->get_meta_join_query( $filter_name, "''", '=' );
	}

	private function email_subscription_status_is_equal( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';

		return $wpdb->prepare( "c1.%i = %s ", $filter_name, $filter_value );
	}

	private function email_subscription_status_is_not_equal( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';

		return $wpdb->prepare( "c1.%i <> %s ", $filter_name, $filter_value );
	}

	private function greater_than( $filter_name, $filter_value ) {
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';
		$wc_fields    = array( 'total_order_count', 'total_order_value', 'average_order_value' );

		if ( in_array( $filter_name, $wc_fields, true ) ) {
			return $this->$filter_name( $filter_value, '>' );
		}

		return $this->get_meta_join_query( $filter_name, $filter_value, '>' );
	}

	private function less_than( $filter_name, $filter_value ) {
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';
		$wc_fields    = array( 'total_order_count', 'total_order_value', 'average_order_value' );

		if ( in_array( $filter_name, $wc_fields, true ) ) {
			return $this->$filter_name( $filter_value, '<' );
		}

		return $this->get_meta_join_query( $filter_name, $filter_value, '<' );
	}

	private function equal( $filter_name, $filter_value ) {
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';
		$wc_fields    = array( 'total_order_count', 'total_order_value', 'average_order_value' );

		if ( in_array( $filter_name, $wc_fields, true ) ) {
			return $this->$filter_name( $filter_value, '=' );
		}

		return $this->get_meta_join_query( $filter_name, $filter_value, '=' );
	}

	private function does_not_equal( $filter_name, $filter_value ) {
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';
		$wc_fields    = array( 'total_order_count', 'total_order_value', 'average_order_value' );

		if ( in_array( $filter_name, $wc_fields, true ) ) {
			return $this->$filter_name( $filter_value, '<>' );
		}

		return $this->get_meta_join_query( $filter_name, $filter_value, '<>' );
	}

	private function before( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';

		if ( 'first_order_date' === $filter_name || 'last_order_date' === $filter_name ) {
			return $this->$filter_name( $filter_value, '<' );
		}

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', $filter_value ), '<' );
	}

	private function after( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';

		if ( 'first_order_date' === $filter_name || 'last_order_date' === $filter_name ) {
			return $this->$filter_name( $filter_value, '>' );
		}

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', $filter_value ), '>' );
	}

	private function in_the_date( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';

		if ( 'first_order_date' === $filter_name || 'last_order_date' === $filter_name ) {
			return $this->$filter_name( $filter_value, '=' );
		}

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', $filter_value ), '=' );
	}

	private function in_the_between_dates( $filter_name, $start_date, $end_date ) {
		$start_date = isset( $start_date[0] ) ? $start_date[0] : '';

		if ( 'first_order_date' === $filter_name || 'last_order_date' === $filter_name ) {
			return $this->{$filter_name . '_between'}( $start_date, $end_date );
		}

		return '';
	}

	private function groups_includes_in( $filter_name, $filter_value ) {
		global $wpdb;
		$contact_table       = $wpdb->prefix . ContactSchema::$table_name;
		$contact_group_pivot = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$filter_value        = implode( ', ', array_map( 'absint', $filter_value ) );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			$query  = $wpdb->prepare( 'c1.id IN (SELECT c2.id FROM %i AS c2 ', $contact_table );
			$query .= $wpdb->prepare( 'JOIN %i AS cgp1 ', $contact_group_pivot );
			$query .= 'ON c2.id = cgp1.contact_id ';
			$query .= $wpdb->prepare( 'WHERE cgp1.group_id IN (%1s)) ', $filter_value );

			return $query;
		}

		return $this->get_meta_join_query( $filter_name, "IN ('$filter_value')", '' );
	}

	private function groups_does_not_include_in( $filter_name, $filter_value ) {
		global $wpdb;
		$contact_table       = $wpdb->prefix . ContactSchema::$table_name;
		$contact_group_pivot = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$filter_value        = implode( ', ', array_map( 'absint', $filter_value ) );

		if ( in_array( $filter_name, self::$contact_primary_field, true ) ) {
			$query  = $wpdb->prepare( 'c1.id NOT IN (SELECT c3.id FROM %i AS c3 ', $contact_table );
			$query .= $wpdb->prepare( 'JOIN %i AS cgp2 ', $contact_group_pivot );
			$query .= 'ON c3.id = cgp2.contact_id ';
			$query .= $wpdb->prepare( 'WHERE cgp2.group_id IN (%1s)) ', $filter_value );

			return $query;
		}

		return $this->get_meta_join_query( $filter_name, "NOT IN ('$filter_value')", '' );
	}

	private function custom_field_includes_in( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $filter_value ) . '%' ), 'LIKE' );
	}

	private function custom_field_does_not_include_in( $filter_name, $filter_value ) {
		global $wpdb;
		$filter_value = isset( $filter_value[0] ) ? $filter_value[0] : '';

		return $this->get_meta_join_query( $filter_name, $wpdb->prepare( '%s', '%' . $wpdb->esc_like( $filter_value ) . '%' ), 'NOT LIKE' );
	}

	// ========================================
	// WooCommerce Filter Methods
	// ========================================

	private function total_order_count( $filter_value, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE t2.total_order_count {$operator} %d) ", $filter_value );
	}

	private function total_order_value( $filter_value, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE t2.total_order_value {$operator} %s) ", $filter_value );
	}

	private function average_order_value( $filter_value, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';
		$decimal_precision  = get_option( 'woocommerce_price_num_decimals', 2 );

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE ROUND(t2.aov, %d) {$operator} %s) ", $decimal_precision, round( $filter_value, $decimal_precision ) );
	}

	private function is_exist( $filter_name, $filter_value ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email IN (SELECT DISTINCT t2.email_address FROM {$wc_customers_table} AS t2) " );
	}

	private function is_not_exist( $filter_name, $filter_value ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email NOT IN (SELECT DISTINCT t2.email_address FROM {$wc_customers_table} AS t2) " );
	}

	private function first_order_date( $filter_value, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE DATE(t2.f_order_date) {$operator} %s) ", $filter_value );
	}

	private function last_order_date( $filter_value, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE DATE(t2.l_order_date) {$operator} %s) ", $filter_value );
	}

	private function first_order_date_between( $start_date, $end_date ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE DATE(t2.f_order_date) BETWEEN %s AND %s) ", $start_date, $end_date );
	}

	private function last_order_date_between( $start_date, $end_date ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE DATE(t2.l_order_date) BETWEEN %s AND %s) ", $start_date, $end_date );
	}

	private function wc_select_field_includes_in( $filter_name, $filter_value ) {
		if ( empty( $filter_value ) ) {
			return '';
		}

		return $this->$filter_name( $filter_value, 'JSON_CONTAINS' );
	}

	private function wc_select_field_does_not_include_in( $filter_name, $filter_value ) {
		if ( empty( $filter_value ) ) {
			return '';
		}

		return $this->$filter_name( $filter_value, 'NOT JSON_CONTAINS' );
	}

	private function purchased_products( $values, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		$json_clauses = array();
		foreach ( $values as $value ) {
			$json_clauses[] = $wpdb->prepare( "{$operator}(t2.purchased_products, %s)", wp_json_encode( $value ) );
		}

		$where_clause = implode( ' OR ', $json_clauses );

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE ({$where_clause})) " );
	}

	private function purchased_tags( $values, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		$json_clauses = array();
		foreach ( $values as $value ) {
			$json_clauses[] = $wpdb->prepare( "{$operator}(t2.purchased_products_tags, %s)", wp_json_encode( $value ) );
		}

		$where_clause = implode( ' OR ', $json_clauses );

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE ({$where_clause})) " );
	}

	private function purchased_categories( $values, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		$json_clauses = array();
		foreach ( $values as $value ) {
			$json_clauses[] = $wpdb->prepare( "{$operator}(t2.purchased_products_cats, %s)", wp_json_encode( $value ) );
		}

		$where_clause = implode( ' OR ', $json_clauses );

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE ({$where_clause})) " );
	}

	private function used_coupons( $values, $operator ) {
		global $wpdb;
		$wc_customers_table = $wpdb->prefix . 'mint_wc_customers';

		if ( ! is_array( $values ) ) {
			return '';
		}

		$json_clauses = array();
		foreach ( $values as $value ) {
			if ( ! empty( $value ) ) {
				if ( class_exists( 'WC_Coupon' ) ) {
					$coupon = new \WC_Coupon( $value );
					if ( $coupon && $coupon->get_id() ) {
						$json_clauses[] = $wpdb->prepare( "{$operator}(t2.used_coupons, %s)", wp_json_encode( $coupon->get_code() ) );
					}
				}
			}
		}

		if ( empty( $json_clauses ) ) {
			return '';
		}

		$where_clause = implode( ' OR ', $json_clauses );

		return $wpdb->prepare( "c1.email IN (SELECT t2.email_address FROM {$wc_customers_table} AS t2 WHERE ({$where_clause})) " );
	}

	// ========================================
	// Helper Methods
	// ========================================

	private function get_meta_join_query( $filter_name, $filter_value, $operator ) {
		global $wpdb;
		$contact_table      = $wpdb->prefix . ContactSchema::$table_name;
		$contact_meta_table = $wpdb->prefix . ContactMetaSchema::$table_name;

		$query  = $wpdb->prepare( 'c1.id IN (SELECT c4.id FROM %i AS c4 ', $contact_table );
		$query .= $wpdb->prepare( 'JOIN %i AS cm1 ', $contact_meta_table );
		$query .= 'ON c4.id = cm1.contact_id ';
		$query .= $wpdb->prepare( 'WHERE cm1.meta_key = %s ', $filter_name );
		$query .= "AND cm1.meta_value {$operator} {$filter_value}) ";

		return $query;
	}

	// ========================================
	// Email Engagement Filter Methods
	// ========================================

	private function build_timeframe_sql( array $filter, string $column = 'created_at' ): string {
		global $wpdb;

		$timeframe = isset( $filter['timeframe'] ) ? $filter['timeframe'] : '';

		if ( empty( $timeframe ) || 'all_time' === $timeframe ) {
			return '';
		}

		if ( 'in_the_last' === $timeframe ) {
			$value = intval( isset( $filter['timeframe_value'] ) ? $filter['timeframe_value'] : 0 );
			if ( $value <= 0 ) {
				return '';
			}
			$unit_map = array(
				'days'   => 'DAY',
				'weeks'  => 'WEEK',
				'months' => 'MONTH',
			);
			$raw_unit = isset( $filter['timeframe_unit'] ) ? $filter['timeframe_unit'] : '';
			$sql_unit = isset( $unit_map[ $raw_unit ] ) ? $unit_map[ $raw_unit ] : 'DAY';
			return $wpdb->prepare( " AND {$column} >= DATE_SUB(NOW(), INTERVAL %d {$sql_unit})", $value );
		}

		if ( 'before' === $timeframe ) {
			$date = isset( $filter['timeframe_value'] ) ? $filter['timeframe_value'] : '';
			return empty( $date ) ? '' : $wpdb->prepare( " AND {$column} < %s", $date );
		}

		if ( 'after' === $timeframe ) {
			$date = isset( $filter['timeframe_value'] ) ? $filter['timeframe_value'] : '';
			return empty( $date ) ? '' : $wpdb->prepare( " AND {$column} > %s", $date );
		}

		if ( 'between' === $timeframe ) {
			$start = isset( $filter['timeframe_value'] ) ? $filter['timeframe_value'] : '';
			$end   = isset( $filter['timeframe_value_end'] ) ? $filter['timeframe_value_end'] : '';
			return ( empty( $start ) || empty( $end ) ) ? '' : $wpdb->prepare( " AND {$column} BETWEEN %s AND %s", $start, $end );
		}

		return '';
	}

	private function get_frequency_operator( string $operator ): string {
		$map = array(
			'more_than' => '>',
			'less_than' => '<',
			'at_least'  => '>=',
			'at_most'   => '<=',
			'exactly'   => '=',
			'not_equal' => '!=',
		);
		return isset( $map[ $operator ] ) ? $map[ $operator ] : '>';
	}

	private function opened_campaign( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$campaign_ids = array_filter( array_map( 'intval', $filter_value ), function( $v ) { return $v > 0; } );
		if ( empty( $campaign_ids ) ) {
			return '1=0';
		}

		$operator     = isset( $filter['operator'] ) ? $filter['operator'] : 'any';
		$placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
		$be_table     = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table     = $wpdb->prefix . 'mint_broadcast_email_meta';

		if ( 'none' === $operator ) {
			return $wpdb->prepare(
				"c1.id NOT IN (
					SELECT DISTINCT be.contact_id
					FROM {$be_table} be
					INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
					WHERE be.campaign_id IN ({$placeholders})
					  AND bm.meta_key = 'is_open' AND bm.meta_value = '1'
				)",
				...$campaign_ids
			);
		}

		if ( 'all' === $operator ) {
			$count = count( $campaign_ids );
			return $wpdb->prepare(
				"c1.id IN (
					SELECT be.contact_id
					FROM {$be_table} be
					INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
					WHERE be.campaign_id IN ({$placeholders})
					  AND bm.meta_key = 'is_open' AND bm.meta_value = '1'
					GROUP BY be.contact_id
					HAVING COUNT(DISTINCT be.campaign_id) = %d
				)",
				...array_merge( $campaign_ids, array( $count ) )
			);
		}

		return $wpdb->prepare(
			"c1.id IN (
				SELECT DISTINCT be.contact_id
				FROM {$be_table} be
				INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
				WHERE be.campaign_id IN ({$placeholders})
				  AND bm.meta_key = 'is_open' AND bm.meta_value = '1'
			)",
			...$campaign_ids
		);
	}

	private function not_opened_campaign( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$campaign_ids = array_filter( array_map( 'intval', $filter_value ), function( $v ) { return $v > 0; } );
		if ( empty( $campaign_ids ) ) {
			return '1=0';
		}

		$placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
		$be_table     = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table     = $wpdb->prefix . 'mint_broadcast_email_meta';

		return $wpdb->prepare(
			"c1.id NOT IN (
				SELECT DISTINCT be.contact_id
				FROM {$be_table} be
				INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
				WHERE be.campaign_id IN ({$placeholders})
				  AND bm.meta_key = 'is_open' AND bm.meta_value = '1'
			)",
			...$campaign_ids
		);
	}

	private function email_was_sent( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$campaign_ids = array_filter( array_map( 'intval', $filter_value ), function( $v ) { return $v > 0; } );
		if ( empty( $campaign_ids ) ) {
			return '1=0';
		}

		$operator     = isset( $filter['operator'] ) ? $filter['operator'] : 'any';
		$placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
		$be_table     = $wpdb->prefix . 'mint_broadcast_emails';

		if ( 'none' === $operator ) {
			return $wpdb->prepare(
				"c1.id NOT IN (
					SELECT DISTINCT contact_id FROM {$be_table}
					WHERE campaign_id IN ({$placeholders}) AND status = 'sent'
				)",
				...$campaign_ids
			);
		}

		if ( 'all' === $operator ) {
			$count = count( $campaign_ids );
			return $wpdb->prepare(
				"c1.id IN (
					SELECT contact_id FROM {$be_table}
					WHERE campaign_id IN ({$placeholders}) AND status = 'sent'
					GROUP BY contact_id
					HAVING COUNT(DISTINCT campaign_id) = %d
				)",
				...array_merge( $campaign_ids, array( $count ) )
			);
		}

		return $wpdb->prepare(
			"c1.id IN (
				SELECT DISTINCT contact_id FROM {$be_table}
				WHERE campaign_id IN ({$placeholders}) AND status = 'sent'
			)",
			...$campaign_ids
		);
	}

	private function email_not_sent( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$campaign_ids = array_filter( array_map( 'intval', $filter_value ), function( $v ) { return $v > 0; } );
		if ( empty( $campaign_ids ) ) {
			return '1=0';
		}

		$placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
		$be_table     = $wpdb->prefix . 'mint_broadcast_emails';

		return $wpdb->prepare(
			"c1.id NOT IN (
				SELECT DISTINCT contact_id FROM {$be_table}
				WHERE campaign_id IN ({$placeholders}) AND status = 'sent'
			)",
			...$campaign_ids
		);
	}

	private function email_bounced( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$operator     = isset( $filter['operator'] ) ? $filter['operator'] : 'any';
		$campaign_ids = array_filter( array_map( 'intval', $filter_value ), function( $v ) { return $v > 0; } );
		$be_table     = $wpdb->prefix . 'mint_broadcast_emails';

		$campaign_clause = '';
		$prepare_args    = array();

		if ( ! empty( $campaign_ids ) ) {
			$placeholders    = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
			$campaign_clause = " AND campaign_id IN ({$placeholders})";
			$prepare_args    = $campaign_ids;
		}

		$in_or_not = ( 'none' === $operator ) ? 'NOT IN' : 'IN';
		$raw_sql   = "c1.id {$in_or_not} (
			SELECT DISTINCT contact_id FROM {$be_table}
			WHERE status = 'failed'{$campaign_clause}
		)";

		return ! empty( $prepare_args ) ? $wpdb->prepare( $raw_sql, ...$prepare_args ) : $raw_sql;
	}

	private function clicked_any_link( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$be_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table      = $wpdb->prefix . 'mint_broadcast_email_meta';
		$timeframe_sql = $this->build_timeframe_sql( $filter, 'bm.created_at' );

		return "c1.id IN (
			SELECT DISTINCT be.contact_id
			FROM {$be_table} be
			INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1'{$timeframe_sql}
		)";
	}

	private function not_clicked_any_link( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$be_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table      = $wpdb->prefix . 'mint_broadcast_email_meta';
		$timeframe_sql = $this->build_timeframe_sql( $filter, 'bm.created_at' );

		return "c1.id NOT IN (
			SELECT DISTINCT be.contact_id
			FROM {$be_table} be
			INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1'{$timeframe_sql}
		)";
	}

	private function opened_email( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$operator      = isset( $filter['operator'] ) ? $filter['operator'] : 'any';
		$campaign_ids  = array_filter( array_map( 'intval', $filter_value ), function( $v ) { return $v > 0; } );
		$timeframe_sql = $this->build_timeframe_sql( $filter, 'bm.created_at' );
		$be_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table      = $wpdb->prefix . 'mint_broadcast_email_meta';

		$campaign_clause = '';
		$prepare_args    = array();

		if ( ! empty( $campaign_ids ) ) {
			$placeholders    = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
			$campaign_clause = " AND be.campaign_id IN ({$placeholders})";
			$prepare_args    = $campaign_ids;
		}

		if ( 'none' === $operator ) {
			$raw = "c1.id NOT IN (
				SELECT DISTINCT be.contact_id
				FROM {$be_table} be
				INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
				WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'{$campaign_clause}{$timeframe_sql}
			)";
			return ! empty( $prepare_args ) ? $wpdb->prepare( $raw, ...$prepare_args ) : $raw;
		}

		if ( 'all' === $operator && ! empty( $campaign_ids ) ) {
			$count = count( $campaign_ids );
			return $wpdb->prepare(
				"c1.id IN (
					SELECT be.contact_id
					FROM {$be_table} be
					INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
					WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'{$campaign_clause}{$timeframe_sql}
					GROUP BY be.contact_id
					HAVING COUNT(DISTINCT be.campaign_id) = %d
				)",
				...array_merge( $campaign_ids, array( $count ) )
			);
		}

		$raw = "c1.id IN (
			SELECT DISTINCT be.contact_id
			FROM {$be_table} be
			INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'{$campaign_clause}{$timeframe_sql}
		)";
		return ! empty( $prepare_args ) ? $wpdb->prepare( $raw, ...$prepare_args ) : $raw;
	}

	private function clicked_link( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$operator      = isset( $filter['operator'] ) ? $filter['operator'] : 'any';
		$campaign_ids  = array_filter( array_map( 'intval', $filter_value ), function( $v ) { return $v > 0; } );
		$timeframe_sql = $this->build_timeframe_sql( $filter, 'bm.created_at' );
		$be_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table      = $wpdb->prefix . 'mint_broadcast_email_meta';

		$campaign_clause = '';
		$prepare_args    = array();

		if ( ! empty( $campaign_ids ) ) {
			$placeholders    = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );
			$campaign_clause = " AND be.campaign_id IN ({$placeholders})";
			$prepare_args    = $campaign_ids;
		}

		if ( 'none' === $operator ) {
			$raw = "c1.id NOT IN (
				SELECT DISTINCT be.contact_id
				FROM {$be_table} be
				INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
				WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1'{$campaign_clause}{$timeframe_sql}
			)";
			return ! empty( $prepare_args ) ? $wpdb->prepare( $raw, ...$prepare_args ) : $raw;
		}

		if ( 'all' === $operator && ! empty( $campaign_ids ) ) {
			$count = count( $campaign_ids );
			return $wpdb->prepare(
				"c1.id IN (
					SELECT be.contact_id
					FROM {$be_table} be
					INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
					WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1'{$campaign_clause}{$timeframe_sql}
					GROUP BY be.contact_id
					HAVING COUNT(DISTINCT be.campaign_id) = %d
				)",
				...array_merge( $campaign_ids, array( $count ) )
			);
		}

		$raw = "c1.id IN (
			SELECT DISTINCT be.contact_id
			FROM {$be_table} be
			INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
			WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1'{$campaign_clause}{$timeframe_sql}
		)";
		return ! empty( $prepare_args ) ? $wpdb->prepare( $raw, ...$prepare_args ) : $raw;
	}

	private function total_opens_count( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$be_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table      = $wpdb->prefix . 'mint_broadcast_email_meta';
		$sql_op        = $this->get_frequency_operator( isset( $filter['operator'] ) ? $filter['operator'] : 'more_than' );
		$count         = max( 0, intval( isset( $filter['count'] ) ? $filter['count'] : 0 ) );
		$timeframe_sql = $this->build_timeframe_sql( $filter, 'bm.created_at' );

		return $wpdb->prepare(
			"c1.id IN (
				SELECT be.contact_id
				FROM {$be_table} be
				INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
				WHERE bm.meta_key = 'is_open' AND bm.meta_value = '1'{$timeframe_sql}
				GROUP BY be.contact_id
				HAVING COUNT(bm.id) {$sql_op} %d
			)",
			$count
		);
	}

	private function total_clicks_count( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$be_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$bm_table      = $wpdb->prefix . 'mint_broadcast_email_meta';
		$sql_op        = $this->get_frequency_operator( isset( $filter['operator'] ) ? $filter['operator'] : 'more_than' );
		$count         = max( 0, intval( isset( $filter['count'] ) ? $filter['count'] : 0 ) );
		$timeframe_sql = $this->build_timeframe_sql( $filter, 'bm.created_at' );

		return $wpdb->prepare(
			"c1.id IN (
				SELECT be.contact_id
				FROM {$be_table} be
				INNER JOIN {$bm_table} bm ON be.id = bm.mint_email_id
				WHERE bm.meta_key = 'is_click' AND bm.meta_value = '1'{$timeframe_sql}
				GROUP BY be.contact_id
				HAVING COUNT(bm.id) {$sql_op} %d
			)",
			$count
		);
	}

	private function emails_received_count( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$be_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$sql_op        = $this->get_frequency_operator( isset( $filter['operator'] ) ? $filter['operator'] : 'more_than' );
		$count         = max( 0, intval( isset( $filter['count'] ) ? $filter['count'] : 0 ) );
		$timeframe_sql = $this->build_timeframe_sql( $filter, 'created_at' );

		return $wpdb->prepare(
			"c1.id IN (
				SELECT contact_id FROM {$be_table}
				WHERE status = 'sent'{$timeframe_sql}
				GROUP BY contact_id
				HAVING COUNT(*) {$sql_op} %d
			)",
			$count
		);
	}

	private function engagement_score( string $filter_name, array $filter_value, array $filter = array() ): string {
		global $wpdb;

		$score = min( 100, max( 0, intval( isset( $filter['score'] ) ? $filter['score'] : 0 ) ) );

		$operator_map = array(
			'higher_than' => '>',
			'lower_than'  => '<',
			'equals'      => '=',
			'not_equals'  => '!=',
		);
		$raw_op = isset( $filter['operator'] ) ? $filter['operator'] : '';
		$sql_op = isset( $operator_map[ $raw_op ] ) ? $operator_map[ $raw_op ] : '>';

		return $wpdb->prepare( "c1.scores {$sql_op} %d", $score );
	}
}
