<?php
/**
 * Class CartModel
 *
 * Data access layer for the abandoned cart tables.
 *
 * Moved from Mail Mint Pro in 1.31.0 when cart tracking became a Free feature.
 *
 * @package Mint\MRM\Internal\AbandonedCart\Helper
 * @since 1.31.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Helper;

use Mint\MRM\DataBase\Tables\AbandonedCartSchema;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Tables\AutomationLogSchema;
use Mint\MRM\DataBase\Tables\AutomationSchema;
use Mint\MRM\Internal\AbandonedCart\Scheduler\AbandonedCartScheduler;
use MRM\Common\MrmCommon;

/**
 * Class CartModel
 *
 * Data access layer for the abandoned cart tables.
 */
class CartModel {
	/**
	 * Get the fully qualified abandoned carts table name.
	 *
	 * @return string The fully qualified abandoned carts table name.
	 * @since 1.5.0
	 */
	protected static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . AbandonedCartSchema::$table_name;
	}

	/**
	 * Summary: Retrieves the meta table name.
	 *
	 * Description: Retrieves the table name for the meta data associated with the AbandonedCart class.
	 *
	 * @access protected
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 * @return string The meta table name.
	 * @since 1.5.0
	 */
	protected static function get_meta_table_name() {
		global $wpdb;
		return $wpdb->prefix . AbandonedCartSchema::$meta_table_name;
	}

	/**
	 * Retrieve cart details from the database based on a specific key-value pair and status.
	 *
	 * This function queries the database table for cart details that match the given key-value pair and status.
	 *
	 * @param string $key     The key to search for in the database table.
	 * @param string $value   The value to match against the specified key.
	 * @param string $status  The status of the cart (default: 'pending').
	 *
	 * @return array|false     An associative array of cart details if found, or false if no matching cart is found.
	 * @since 1.5.0
	 */
	public static function get_cart_details_by_key( $key, $value, $status = 'pending' ) {
		global $wpdb;
		$table  = $wpdb->prefix . AbandonedCartSchema::$table_name;
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$key} = %s AND status = %s", array( $value, $status ) ), ARRAY_A ); //phpcs:ignore
		if ( is_array( $result ) && !empty( $result ) ) {
			return $result;
		}
		return false;
	}

	/**
	 * Insert or update abandoned cart data.
	 *
	 * This function inserts or updates the abandoned cart data based on the provided $data array.
	 *
	 * @param array $data The abandoned cart data.
	 *
	 * @return bool
	 * @since 1.5.0
	 */
	public static function insert_or_update( $data ) {
		// Never let a missing table surface a wpdb error on the storefront.
		if ( ! self::tables_exist() ) {
			return false;
		}

		// Set initial values.
		$email         = isset( $data['email'] ) ? $data['email'] : '';
		$settings      = CartCommon::get_abandoned_cart_settings();
		$disable_roles = isset($settings['disable_roles']) ? $settings['disable_roles'] : array();

		// Check if the user role is in the disabled roles list.
		if (! empty($disable_roles)) {
			$user = get_user_by('email', $email);
			if ($user) {
				$user_roles = $user->roles;

				foreach ($user_roles as $role) {
					if (in_array($role, $disable_roles, true)) {
						return false;
					}
				}
			}
		}

		// The visitor opted out of cart tracking via the GDPR consent notice.
		if ( isset( $_COOKIE['mint_ab_cart_skip_track'] ) && 'yes' === $_COOKIE['mint_ab_cart_skip_track'] ) {
			return false;
		}

		// The email (or its domain) is on the blacklist — Pro feature, no-ops in Free.
		if ( CartCommon::is_email_blacklisted( $email ) ) {
			return false;
		}

		$data = CartCommon::get_abandoned_cart_totals( $data );

		/*
		 * Resolve the existing row by session first. A guest row starts with email=''
		 * and only gains a real email once checkout fields are captured or the visitor
		 * logs in — looking up by email alone would miss it (or, worse, collide with
		 * another guest who also hasn't entered an email yet) until then.
		 */
		$session_key = isset( $data['session_key'] ) ? $data['session_key'] : '';
		$cart_detail = $session_key
			? self::get_cart_details_by_key_and_status( 'session_key', $session_key, array( 'pending' ) )
			: false;

		if ( ! $cart_detail && $email ) {
			$cart_detail = self::get_cart_details_by_key_and_status( 'email', $email, array( 'pending' ) );
		}

		if ( isset( $cart_detail['id'] ) ) {
			// Update existing cart.
			$data['status']     = $cart_detail['status'];
			$data['updated_at'] = current_time( 'mysql' );
			return self::update( $data, $cart_detail['id'] );
		} else {
			/*
			 * The customer bought recently, so hold off on tracking them again.
			 *
			 * Checked on insert only. Applying it to an update would freeze an already
			 * tracked cart's contents mid-session; declining to create the row is what the
			 * setting actually promises — no new cart, and therefore no email chain — and
			 * it keeps the reports free of carts that were never going to be chased.
			 *
			 * Guests are skipped because there is no address to match an order against, so
			 * the common storefront path costs nothing.
			 */
			if ( $email && CartCommon::is_within_cool_off_period( $email ) ) {
				/**
				 * Fires when cart tracking is skipped for a customer inside the cool-off period.
				 *
				 * @param string $email The customer email that was skipped.
				 * @param string $reason Why tracking was skipped.
				 *
				 * @since 1.31.1
				 */
				do_action( 'mint_abandoned_cart_capture_skipped', $email, 'cool_off' );
				return false;
			}

			// Insert new cart.
			$session_key           = WC()->session->get_customer_id();
			$data['token']         = CartCommon::create_abandoned_cart_token( 32 );
			$data['session_key']   = $session_key;
			$data['created_at']    = current_time( 'mysql' );
			$data['checkout_data'] = maybe_serialize( CartCommon::prepare_checkout_data( $data ) );
			$abandoned_cart_id     = self::insert( $data );

			self::schedule_cart_jobs( $abandoned_cart_id, $email, $session_key );

			return $abandoned_cart_id;
		}
	}

	/**
	 * Queue the two status-transition jobs for a newly tracked cart.
	 *
	 * One marks the cart abandoned once the wait period elapses, the other marks it lost
	 * once the exclude period does.
	 *
	 * The payload shape here is part of the contract with Action Scheduler: at the point
	 * cart tracking moved from Pro to Free, live sites held jobs queued by Pro against
	 * these same hooks. Free's handlers must keep reading the same keys.
	 *
	 * @param int    $cart_id     Abandoned cart ID.
	 * @param string $email       Cart email address.
	 * @param string $session_key WooCommerce session customer ID.
	 *
	 * @return void
	 * @since 1.31.0
	 */
	public static function schedule_cart_jobs( $cart_id, $email, $session_key ) {
		if ( empty( $cart_id ) ) {
			return;
		}

		CartCommon::enqueue_user_after_add_to_cart(
			array(
				'data' => array(
					'id'          => $cart_id,
					'email'       => $email,
					'session_key' => $session_key,
				),
			),
			$cart_id
		);

		CartCommon::enqueue_cart_creation_time(
			array(
				'data' => array(
					'id'          => $cart_id,
					'email'       => $email,
					'session_key' => $session_key,
					'created_at'  => current_time( 'mysql' ),
				),
			),
			$cart_id
		);
	}

	/**
	 * Whether the abandoned cart tables are present.
	 *
	 * The storefront capture hooks fire on every add-to-cart, so a site whose migration
	 * failed must degrade quietly instead of emitting a wpdb error on a customer-facing
	 * page. DatabaseMigrator::maybe_create_abandoned_cart_tables() repairs the tables;
	 * this only decides whether it is safe to write right now.
	 *
	 * @return bool True when both tables exist.
	 * @since 1.31.0
	 */
	protected static function tables_exist() {
		static $exists = null;

		if ( null !== $exists ) {
			return $exists;
		}

		if ( 'yes' === get_option( '_mrm_abandoned_cart_tables_ready' ) ) {
			$exists = true;
			return $exists;
		}

		global $wpdb;
		$table  = self::get_table_name();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

		return $exists;
	}

	/**
	 * Insert data into the database table.
	 *
	 * @param array $data  The data to be inserted into the table.
	 *
	 * @return false|int   False on failure to insert, or the number of rows affected on success.
	 * @since 1.5.0
	 */
	public static function insert( $data ) {
		if ( empty( $data ) || !is_array( $data ) ) {
			return false;
		}
		global $wpdb;
        $inserted =  $wpdb->insert( self::get_table_name(), $data ); //phpcs:ignore
		if ( $inserted ) {
			return $wpdb->insert_id;
		}
		return $inserted;
	}

	/**
	 * Update data in the database table.
	 *
	 * @param array $data  The data to be updated in the table.
	 * @param int   $id    The ID of the row to be updated.
	 *
	 * @return false|int   False on failure to update, or the number of rows affected on success.
	 * @since 1.5.0
	 */
	public static function update( $data, $id ) {
		global $wpdb;
		return $wpdb->update( //phpcs:ignore
			self::get_table_name(),
			$data,
			array(
				'ID' => $id,
			)
		);
	}
	/**
	 * Update the status of a record in the database table.
	 *
	 * @param string $column    The column name to update.
	 * @param mixed  $value     The new value for the column.
	 * @param array  $condition An array defining the conditions for updating the record.
	 *                          Example: ['id' => 123, 'status' => 'pending'].
	 * @return int|false The number of rows updated, or false on failure.
	 */
	public static function update_status( $column, $value, $condition ) {
		if ( !$column || !$value || !$condition || !is_array( $condition ) ) {
			return false;
		}

		return self::update_columns( array( $column => $value ), $condition );
	}

	/**
	 * Update an arbitrary set of columns under an arbitrary condition.
	 *
	 * The single write path for conditional updates, so a recovery can set status,
	 * recovery_source and recovered_at in one statement while keeping the read-status
	 * condition that makes concurrent writers safe. update_status() delegates here.
	 *
	 * Columns the current schema does not have are dropped rather than failing the
	 * write: upgrade_database_tables() bumps the stored DB version whether or not its
	 * callbacks succeeded, so a half-applied migration must not turn every recovery into
	 * a wpdb error on the storefront.
	 *
	 * @param array $data      Column => value pairs to write.
	 * @param array $condition Column => value pairs forming the WHERE clause.
	 *
	 * @return int|false Rows updated, or false when there is nothing safe to write.
	 * @since 1.31.1
	 */
	public static function update_columns( $data, $condition ) {
		if ( empty( $data ) || !is_array( $data ) || empty( $condition ) || !is_array( $condition ) ) {
			return false;
		}

		foreach ( array_keys( $data ) as $column ) {
			if ( ! self::has_column( $column ) ) {
				unset( $data[ $column ] );
			}
		}

		if ( empty( $data ) ) {
			return false;
		}

		global $wpdb;
		return $wpdb->update( self::get_table_name(), $data, $condition ); //phpcs:ignore
	}

	/**
	 * Whether the carts table currently has the given column.
	 *
	 * Memoised per request, the same shape as tables_exist(), so a hot write path pays
	 * one query at most. Guards writers against a migration that has not landed yet.
	 *
	 * @param string $column Column name.
	 *
	 * @return bool
	 * @since 1.31.1
	 */
	public static function has_column( $column ) {
		static $columns = null;

		if ( null === $columns ) {
			global $wpdb;
			$columns = array();

			if ( self::tables_exist() ) {
				$found = $wpdb->get_col( "SHOW COLUMNS FROM " . self::get_table_name() ); //phpcs:ignore
				if ( is_array( $found ) ) {
					$columns = $found;
				}
			}
		}

		return in_array( $column, $columns, true );
	}

	/**
	 * Close every other open cart belonging to the same customer.
	 *
	 * Called when one cart closes, because a customer who has bought should not keep
	 * receiving reminders about their other carts — the recovery handlers only ever
	 * resolve a single row, so without this the siblings carry on emailing.
	 *
	 * Two details that look like oversights and are not:
	 *
	 * It writes `lost` directly and fires no action. Routing through
	 * AbandonedCartRunScheduler::update_abandoned_cart_status_to_lost() would fire
	 * mailmint_after_abandoned_cart_lost, which Pro turns into a wc_abandoned_cart_lost
	 * automation trigger — a "we lost your cart" email to somebody who just bought.
	 *
	 * The `email != ''` clause is load-bearing. Guest carts are deliberately created with
	 * an empty email, so without it every anonymous cart is a sibling of every other one
	 * and a single guest purchase would close the entire guest population's carts.
	 *
	 * @param string $email        Cart email, may be empty for a guest.
	 * @param int    $user_id      WordPress user ID, 0 for a guest.
	 * @param int    $keep_cart_id The cart to leave alone.
	 *
	 * @return array IDs of the carts that were closed.
	 * @since 1.31.1
	 */
	public static function close_open_siblings( $email, $user_id, $keep_cart_id ) {
		if ( ! self::tables_exist() ) {
			return array();
		}

		$email        = is_string( $email ) ? trim( $email ) : '';
		$user_id      = (int) $user_id;
		$keep_cart_id = (int) $keep_cart_id;

		// With neither identifier there is no way to tell siblings from strangers.
		if ( '' === $email && $user_id <= 0 ) {
			return array();
		}

		global $wpdb;
		$table = self::get_table_name();

		$ids = $wpdb->get_col( //phpcs:ignore
			$wpdb->prepare(
				"SELECT id FROM {$table}
				 WHERE id != %d
				   AND status IN ( 'pending', 'abandoned' )
				   AND ( ( email = %s AND email != '' ) OR ( user_id = %d AND user_id > 0 ) )", //phpcs:ignore
				$keep_cart_id,
				$email,
				$user_id
			)
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$ids = array_map( 'absint', $ids );

		foreach ( $ids as $id ) {
			self::update_columns( array( 'status' => 'lost' ), array( 'id' => $id ) );
			self::cancel_cart_jobs( $id );
		}

		return $ids;
	}

	/**
	 * Find a tracked cart belonging to the customer who placed an order.
	 *
	 * Extracted so the new-order handler and the order-status watcher resolve the same
	 * cart for the same order instead of each having their own idea. Matches the
	 * registered customer first, because a logged-in shopper may check out with a billing
	 * address that differs from their account email, then falls back to the billing email
	 * for guests.
	 *
	 * @param \WC_Order $order    The order.
	 * @param array     $statuses Cart statuses to consider.
	 *
	 * @return array|false The cart row, or false when the customer has none tracked.
	 * @since 1.31.1
	 */
	public static function find_open_cart_for_order( $order, $statuses = array( 'pending', 'abandoned' ) ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		$user_id = (int) $order->get_customer_id();

		if ( $user_id ) {
			$cart = self::get_cart_details_by_key_and_status( 'user_id', $user_id, $statuses );

			if ( ! empty( $cart['id'] ) ) {
				return $cart;
			}
		}

		$email = $order->get_billing_email();

		if ( empty( $email ) ) {
			return false;
		}

		return self::get_cart_details_by_key_and_status( 'email', $email, $statuses );
	}

	/**
	 * Cancel the queued status-transition jobs for a cart.
	 *
	 * Both jobs are scheduled into a group unique to the cart
	 * (MINT_ABANDONED_CART_GROUP . '_' . $cart_id), which holds nothing else — so
	 * clearing the whole group is exact and cannot reach another cart's work.
	 *
	 * @param int $cart_id Abandoned cart ID.
	 *
	 * @return void
	 * @since 1.31.1
	 */
	public static function cancel_cart_jobs( $cart_id ) {
		$cart_id = (int) $cart_id;

		if ( $cart_id <= 0 ) {
			return;
		}

		$scheduler = new AbandonedCartScheduler();
		$scheduler->cancelByGroup( MINT_ABANDONED_CART_GROUP . '_' . $cart_id );
	}


	/**
	 * Delete a record from the database table by ID.
	 *
	 * @param int $id The ID of the record to be deleted.
	 * @return bool|int False if the ID is empty or the deletion fails. Otherwise, the number of rows deleted.
	 * @since 1.5.0
	 */
	public static function delete( $id ) {
		if ( empty( $id ) ) {
			return false;
		}

		global $wpdb;
		return $wpdb->delete( self::get_table_name(), array( 'ID' => $id ) ); //phpcs:ignore
	}

	/**
	 * Summary: Inserts a meta data for a cart.
	 *
	 * Description: Inserts a meta data with the specified key and value for the cart with the given ID.
	 *
	 * @access public
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 * @param int    $cart_id The ID of the cart.
	 * @param string $key     The meta key.
	 * @param mixed  $value   The meta value.
	 *
	 * @return false|int The number of rows affected if the meta data is inserted successfully, false otherwise.
	 * @since 1.5.0
	 */
	public static function insert_meta( $cart_id, $key, $value ) {
		global $wpdb;

		$data = array(
			'meta_key'          => $key, //phpcs:ignore
			'meta_value'        => $value, //phpcs:ignore
			'abandoned_cart_id' => $cart_id,
		);

		return $wpdb->insert( self::get_meta_table_name(), $data ); //phpcs:ignore
	}

	/**
	 * Summary: Updates a meta data for a cart.
	 *
	 * Description: Updates the meta data with the specified key and value for the cart meta with the given ID.
	 *
	 * @access public
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 * @param int    $meta_id The ID of the meta data.
	 * @param string $key     The meta key.
	 * @param mixed  $value   The meta value.
	 *
	 * @return false|int The number of rows affected if the meta data is updated successfully, false otherwise.
	 * @since 1.5.0
	 */
	public static function update_meta( $meta_id, $key, $value ) {
		// Check if $meta_id, $key, and $value are valid.
		if ( empty( $meta_id ) || empty( $key ) ) {
			return false;
		}

		global $wpdb;
		return $wpdb->update( //phpcs:ignore
			self::get_meta_table_name(),
			array( 'meta_value' => $value ), //phpcs:ignore
			array(
				'ID'       => $meta_id,
				'meta_key' => $key, //phpcs:ignore
			)
		);
	}

	/**
	 * Retrieves all abandoned data based on status.
	 *
	 * @param string $status     The comma-separated status string.
	 * @param int    $offset     The offset for pagination.
	 * @param int    $limit      The limit for pagination.
	 * @param string $search     The search term.
	 * @param string $order_by   The column to order by.
	 * @param string $order_type The order type (ASC or DESC).
	 *
	 * @return array The formatted abandoned data.
	 *
	 * @since 1.5.0
	 */
	public static function get_all_abandoned_data_by_status( $status = 'pending,abandoned', $offset = 0, $limit = 10, $search = '', $order_by = 'email', $order_type = 'DESC' ) {
		global $wpdb;
		$abandoned_cart      = $wpdb->prefix . 'mint_abandoned_carts';
		$abandoned_cart_meta = $wpdb->prefix . 'mint_abandoned_carts_meta';
		$search_terms        = null;
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "AND $abandoned_cart.email LIKE '%%$search%%'";
		}
		$status_formatted = CartCommon::format_status( $status );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
		if ( 'total' === $order_by ) {
			$order_by = $wpdb->prepare( 'CAST(%1$s.%2$s AS DECIMAL(10,2))', $abandoned_cart, $order_by );
		} else {
			$order_by = $wpdb->prepare( '%1$s.%2$s', $abandoned_cart, $order_by );
		}
		$query = $wpdb->prepare(
			"SELECT $abandoned_cart.*, GROUP_CONCAT($abandoned_cart_meta.meta_key) AS meta_key, GROUP_CONCAT($abandoned_cart_meta.meta_value) AS meta_value
                FROM $abandoned_cart
                LEFT JOIN $abandoned_cart_meta ON $abandoned_cart.id = $abandoned_cart_meta.abandoned_cart_id
                WHERE $abandoned_cart.status in ( $status_formatted ) $search_terms
                 GROUP BY $abandoned_cart.id
                ORDER BY $order_by $order_type
                LIMIT %d, %d",
			array( $offset, $limit )
		);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
        $results            = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
		$get_formatted_data = self::formatted_abandoned_data( $results );
		return $get_formatted_data;
	}

	/**
	 * Formats the retrieved abandoned data.
	 *
	 * @param array $results The retrieved abandoned data.
	 *
	 * @return array The formatted abandoned data.
	 *
	 * @since 1.5.0
	 */
	public static function formatted_abandoned_data( $results ) {
		if ( !is_array( $results ) ) {
			return array();
		}
		$final_results = array();
		foreach ( $results as $row ) {
			$id = $row['id'];
			// If the id is not already present in the final results array, create a new entry.
			if ( !isset( $final_results[ $id ] ) ) {
				$final_results[ $id ] = array(
					'id'            => $id,
					'email'         => $row['email'],
					'user_id'       => $row['user_id'],
					'automation_id' => $row['automation_id'],
					'status'        => $row['status'],
					'items'         => maybe_unserialize( $row['items'] ), // No need to serialize here.
					'provider'      => $row['provider'],
					'total'         => $row['total'],
					'token'         => $row['token'],
					'session_key'   => $row['session_key'],
					'checkout_data' => maybe_unserialize( $row['checkout_data'] ), // No need to serialize here.
					'created_at'    => !empty( $row['created_at'] ) ? gmdate( 'F d, Y h:i a', strtotime( $row['created_at'] ) ) : '',
					'updated_at'    => !empty( $row['updated_at'] ) ? gmdate( 'F d, Y h:i a', strtotime( $row['updated_at'] ) ) : '',
					'meta'          => array(),
				);
			}

			// Add the meta key-value pair to the corresponding entry in the final results array.
			if ( !empty( $row['meta_key'] ) && !empty( $row['meta_value'] ) ) {
				$meta_key   = explode( ',', $row['meta_key'] );
				$meta_value = explode( ',', $row['meta_value'] );
				$keys       = array_map( 'trim', $meta_key );
				$values     = array_map( 'trim', $meta_value );
				$result     = array();
				foreach ( $keys as $index => $key ) {
					$result[ $key ] = $values[ $index ];
				}
				$final_results[ $id ]['meta'] = $result;
			}
		}
		return array_values( $final_results );
	}


	/**
	 * Formats the retrieved abandoned cart data.
	 *
	 * @param array $get_recoverable The retrieved abandoned cart data.
	 * @param bool  $with_media      Whether product ids and thumbnails should be resolved.
	 *
	 * @return array The formatted abandoned cart data.
	 *
	 * @since 1.5.0
	 */
	public static function get_formatted_result( $get_recoverable, $with_media = false ) {
		$result = array();
		if ( !is_array( $get_recoverable ) ) {
			return $result;
		}
		foreach ( $get_recoverable as $item ) {
			$result[] = array(
				'id'                        => ! is_null( $item['id'] ) ? $item['id'] : 0,
				'automation_id'             => ! is_null( $item['automation_id'] ) ? $item['automation_id'] : 0,
				'manual_run_automation_ids' => !empty( $item['meta']['manual_run_automation_ids'] ) ? maybe_unserialize( $item['meta']['manual_run_automation_ids'] ) : array(),
				'email'                     => ! is_null( $item['email'] ) ? $item['email'] : '',
				'preview'                   => CartCommon::get_preview_data( $item, $item['checkout_data'], $with_media ),
				'created_at'                => $item['created_at'],
				'status'                    => ! is_null( $item['status'] ) ? $item['status'] : '',
				'items'                     => CartCommon::get_items( $item['items'] ),
				'total'                     => ! is_null( $item['total'] ) ? $item['total'] : 0,
				'currency'                  => ! empty( $item['currency'] ) ? CartCommon::get_currency_by_code( $item['currency'] ) : CartCommon::get_currency(),
				'order'                     => CartCommon::get_order_id( $item ),
				'user_id'                   => ! empty( $item['user_id'] ) ? $item['user_id'] : 0,
				'mint_contact_id'           => ContactModel::get_id_by_email( ! empty( $item['email'] ) ? $item['email'] : '' ),
				'checkout_data'             => isset( $item['checkout_data'] ) ? $item['checkout_data'] : '',
			);
		}
		return $result;
	}
	/**
	 * Retrieves all abandoned cart data based on status and parameters.
	 *
	 * @param array  $params The parameters for retrieving abandoned cart data.
	 * @param string $status The status of abandoned carts to retrieve.
	 *
	 * @return array The retrieved abandoned cart data.
	 *
	 * @since 1.5.0
	 */
	public static function get_all_abandoned_cart( $params, $status ) {
		$page     = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page = isset( $params['per-page'] ) ? absint( $params['per-page'] ) : 25;
		$offset   = ( $page - 1 ) * $per_page;

		$order_by   = isset( $params['order-by'] ) ? strtolower( $params['order-by'] ) : 'email';
		$order_type = isset( $params['order-type'] ) ? strtolower( $params['order-type'] ) : 'asc';

		// valid order by fields and types.
		$allowed_order_by_fields = array( 'email', 'created_at', 'total' );
		$allowed_order_by_types  = array( 'asc', 'desc' );

		// validate order by fields or use default otherwise.
		$order_by   = in_array( $order_by, $allowed_order_by_fields, true ) ? $order_by : 'email';
		$order_type = in_array( $order_type, $allowed_order_by_types, true ) ? $order_type : 'asc';

		// Form Search keyword.
		$search         = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$get_cart_data  = self::get_all_abandoned_data_by_status( $status, $offset, $per_page, $search, $order_by, $order_type );
		$abandoned_data = self::get_formatted_result( $get_cart_data );
		$total          = CartCommon::get_count_abandoned_cart_data();
		$result         = array(
			'total'          => $total,
			'current_page'   => $page,
			'abandoned_data' => $abandoned_data,
		);
		return $result;
	}

	/**
	 * Retrieves a single abandoned cart, formatted exactly like a list row.
	 *
	 * The cart details page needs the same payload the list endpoints hand to the
	 * table row (email, status, items, order, currency and the checkout preview),
	 * so the row is pushed through the same two formatters instead of shaping a
	 * second, drift-prone response.
	 *
	 * @param int $abandoned_id The ID of the abandoned cart.
	 *
	 * @return array The formatted cart data, or an empty array when the cart is gone.
	 *
	 * @since 1.31.0
	 */
	public static function get_single_abandoned_cart( $abandoned_id ) {
		$abandoned_id = absint( $abandoned_id );
		if ( ! $abandoned_id ) {
			return array();
		}

		global $wpdb;
		$abandoned_cart      = $wpdb->prefix . 'mint_abandoned_carts';
		$abandoned_cart_meta = $wpdb->prefix . 'mint_abandoned_carts_meta';

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT $abandoned_cart.*, GROUP_CONCAT($abandoned_cart_meta.meta_key) AS meta_key, GROUP_CONCAT($abandoned_cart_meta.meta_value) AS meta_value
                FROM $abandoned_cart
                LEFT JOIN $abandoned_cart_meta ON $abandoned_cart.id = $abandoned_cart_meta.abandoned_cart_id
                WHERE $abandoned_cart.id = %d
                GROUP BY $abandoned_cart.id",
			array( $abandoned_id )
		);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore

		if ( empty( $results ) ) {
			return array();
		}

		$rows      = self::formatted_abandoned_data( $results );
		$formatted = self::get_formatted_result( $rows, true );

		if ( empty( $formatted[0] ) ) {
			return array();
		}

		$cart            = $formatted[0];
		$checkout_data   = isset( $rows[0]['checkout_data'] ) ? $rows[0]['checkout_data'] : array();
		$cart['address'] = CartCommon::get_structured_address( $checkout_data );

		// The details page links back to where the customer left off.
		$cart['checkout_url'] = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '';
		$cart['emails']       = self::get_cart_recovery_emails( $cart['automation_id'], $cart['email'] );

		return $cart;
	}

	/**
	 * Retrieves the recovery emails queued or sent for one cart.
	 *
	 * Only the automation stored on the cart is read, which is the same automation the
	 * cart's automation view reports on; emails from a manually re-run automation are
	 * not folded in. Sending these emails is a Mail Mint Pro feature, so a site without
	 * Pro has no cart automation and this returns nothing.
	 *
	 * @param int    $automation_id The automation that picked the cart up.
	 * @param string $email         The cart's email address.
	 * @param int    $limit         Maximum log rows to inspect before filtering.
	 *
	 * @return array The formatted email rows.
	 * @since 1.31.0
	 */
	public static function get_cart_recovery_emails( $automation_id, $email, $limit = 100 ) {
		$automation_id = absint( $automation_id );
		if ( ! $automation_id || empty( $email ) ) {
			return array();
		}

		$log_rows = self::get_automation_log_by_id_and_email( $automation_id, $email, 0, $limit );

		return CartCommon::formatted_recovery_emails( $log_rows );
	}


	/**
	 * Retrieves the count of abandoned cart data for different statuses.
	 *
	 * @return array An array containing the counts for different abandoned cart statuses.
	 * @since 1.5.0
	 */
	public static function get_abandoned_cart_status_count() {
		global $wpdb;
		$abandoned_cart = $wpdb->prefix . 'mint_abandoned_carts';
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$get_total = $wpdb->get_row( //phpcs:ignore
			$wpdb->prepare(
				"SELECT COUNT(CASE WHEN status = %s THEN 1 END) AS abandoned,
            COUNT(CASE WHEN status = %s THEN 1 END) AS pending,
            COUNT(CASE WHEN status = %s THEN 1 END) AS recovered,
            COUNT(CASE WHEN status = %s THEN 1 END) AS lost
            FROM $abandoned_cart",
				'abandoned',
				'pending',
				'recovered',
				'lost'
			),
			ARRAY_A
		);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( !empty( $get_total ) ) {
			return $get_total;
		}
		return array();
	}

	/**
	 * Delete multiple Abandoned
	 *
	 * @param array $abandoned_ids abandoned id.
	 *
	 * @return bool
	 * @since 1.5.0
	 */
	public static function destroy_all( $abandoned_ids ) {
		global $wpdb;
		$abandoned_cart      = $wpdb->prefix . 'mint_abandoned_carts';
		$abandoned_cart_meta = $wpdb->prefix . 'mint_abandoned_carts_meta';
		if ( is_array( $abandoned_ids ) && count( $abandoned_ids ) > 0 ) {
			$placeholders = implode( ', ', array_fill( 0, count( $abandoned_ids ), '%d' ) );
			$query                = $wpdb->prepare( "DELETE FROM $abandoned_cart WHERE id IN($placeholders)", $abandoned_ids ); //phpcs:ignore
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$delete_abandoned_ids = $wpdb->query( $query ); //phpcs:ignore
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $delete_abandoned_ids ) {
				$query = $wpdb->prepare( "DELETE FROM $abandoned_cart_meta WHERE abandoned_cart_id IN($placeholders)", $abandoned_ids ); //phpcs:ignore
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $query ); //phpcs:ignore
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			return $delete_abandoned_ids;
		}
		return false;
	}


	/**
	 * Summary: Retrieves cart details based on key, value, and statuses.
	 *
	 * Description: Retrieves the cart details from the database based on the specified key, value, and statuses.
	 *
	 * @access public
	 *
	 * @param string $key         The key to match in the query.
	 * @param mixed  $value       The value to match against the provided key.
	 * @param array  $statuses    Optional. An array of statuses to match.
	 *
	 * @return array|false Returns an associative array containing the cart details if found, or false if not found.
	 * @since 1.5.0
	 */
	public static function get_cart_details_by_key_and_status( $key, $value, $statuses = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . AbandonedCartSchema::$table_name;

		$status_conditions = empty( $statuses ) ? '1=1' : implode( ' OR ', array_fill( 0, count( $statuses ), 'status = %s' ) );
		$status_values     = array_merge( array( $value ), $statuses );

		$query = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$key} = %s AND ({$status_conditions})", //phpcs:ignore
			$status_values
		);

		$result = $wpdb->get_row( $query, ARRAY_A ); //phpcs:ignore

		return $result ? $result : false;
	}

	/**
	 * Summary: Updates the meta data for a cart.
	 *
	 * Description: Updates the meta data with the specified key and value for the cart with the given ID.
	 *
	 * @access public
	 *
	 * @param int    $cart_id    The ID of the cart.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 *
	 * @return bool|int The meta ID if the meta data is updated successfully, false otherwise.
	 * @since 1.5.0
	 */
	public static function update_cart_meta( $cart_id, $meta_key, $meta_value ) {
		$meta_id = self::is_cart_meta_exist( $cart_id, $meta_key );
		if ( ! $meta_id ) {
			return self::insert_meta( $cart_id, $meta_key, $meta_value );
		}

		return self::update_meta( $meta_id, $meta_key, $meta_value );
	}

	/**
	 * Summary: Checks if a cart meta exists.
	 *
	 * Description: Checks if a cart meta with the specified meta key exists for the given cart ID.
	 *
	 * @access public
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 * @param int    $cart_id  The ID of the cart.
	 * @param string $meta_key The meta key.
	 *
	 * @return bool|int The meta ID if the cart meta exists, false otherwise.
	 * @since 1.5.0
	 */
	public static function is_cart_meta_exist( $cart_id, $meta_key ) {
		global $wpdb;
		$table   = $wpdb->prefix . AbandonedCartSchema::$meta_table_name;
		$meta_id = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %1s WHERE meta_key = %s AND abandoned_cart_id = %d', $table, $meta_key, $cart_id ) ); //phpcs:ignore
		return $meta_id ? $meta_id[0] : false;
	}

	/**
	 * Summary: Get cart meta data.
	 *
	 * Description: Checks if a cart meta with the specified meta key exists for the given cart ID.
	 *
	 * @access public
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 * @param int    $cart_id  The ID of the cart.
	 * @param string $meta_key The meta key.
	 *
	 * @return array The meta ID if the cart meta exists, false otherwise.
	 * @since 1.5.5
	 */
	public static function get_cart_meta( $cart_id, $meta_key ) {
		global $wpdb;
		$table     = $wpdb->prefix . AbandonedCartSchema::$meta_table_name;
		$meta_data = $wpdb->get_row( $wpdb->prepare( 'SELECT meta_value FROM %1s WHERE meta_key = %s AND abandoned_cart_id = %d', $table, $meta_key, $cart_id ),ARRAY_A ); //phpcs:ignore
		if ( !empty( $meta_data['meta_value'] ) ) {
			return maybe_unserialize( $meta_data['meta_value'] );
		}
		return array();
	}

	/**
	 * Summary: Retrieves abandoned cart details by token and status.
	 *
	 * Description: Retrieves the abandoned cart details from the database based on the specified token and status.
	 *
	 * @access public
	 *
	 * @global wpdb $wpdb The WordPress database object.
	 *
	 * @param string $token  The token of the abandoned cart.
	 * @param string $status The status of the abandoned cart.
	 *
	 * @return array An array containing the abandoned cart details.
	 * @since 1.5.0
	 */
	public static function get_abandoned_cart_detail_by_token_and_status( $token, $status ) {
		global $wpdb;

		// Define table names.
		$abandoned_carts_table = $wpdb->prefix . AbandonedCartSchema::$table_name;

		$abandoned_carts_meta_table = $wpdb->prefix . AbandonedCartSchema::$meta_table_name;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Prepare and execute the query.
		$query = $wpdb->prepare(
			"
							SELECT carts.*, meta.meta_key, meta.meta_value
							FROM {$abandoned_carts_table} AS carts
							LEFT JOIN {$abandoned_carts_meta_table} AS meta ON carts.id = meta.abandoned_cart_id
							WHERE carts.token = %s AND carts.status = %s 
						",
			$token,
			$status
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
	}

	/**
	 * Retrieves automation logs by automation ID and email with pagination.
	 *
	 * @param int    $automation_id The ID of the automation.
	 * @param string $email         The email to match.
	 * @param int    $offset        The offset for pagination.
	 * @param int    $limit         The number of logs to retrieve.
	 * @return array The automation log data matching the criteria.
	 * @since 1.5.0
	 */
	public static function get_automation_log_by_id_and_email( $automation_id, $email, $offset = 0, $limit = 10 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . AutomationLogSchema::$table_name;
		$query      = $wpdb->prepare(
			'SELECT * FROM %1s WHERE automation_id = %d AND email = %s ORDER BY id DESC LIMIT %d offset %d', //phpcs:ignore
			array( $table_name, $automation_id, $email, $limit, $offset )
		);
		$result     = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore

		if ( !empty( $result ) ) {
			return $result;
		}
		return array();
	}

	/**
	 * Retrieves the total count of automation logs by automation ID and email.
	 *
	 * @param int    $automation_id The ID of the automation.
	 * @param string $email         The email to match.
	 * @return int The total count of automation logs matching the criteria.
	 * @since 1.5.0
	 */
	public static function get_automation_log_count( $automation_id, $email ) {
		global $wpdb;
		$table_name = $wpdb->prefix . AutomationLogSchema::$table_name;

		$count_query = $wpdb->prepare(
			'SELECT COUNT(*) AS total FROM %1s WHERE automation_id = %d AND email = %s', //phpcs:ignore
			array( $table_name, $automation_id, $email )
		);
		$total_count = $wpdb->get_var( $count_query ); //phpcs:ignore

		return $total_count;
	}

	/**
	 * Retrieves all abandoned automations based on the provided parameters with pagination.
	 *
	 * @param array $params The parameters for retrieving abandoned automations.
	 * @return array The abandoned automations data including pagination information.
	 * @since 1.5.0
	 */
	public static function get_all_abandoned_automations( $params ) {
		$page          = !empty( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page      = !empty( $params['per-page'] ) ? absint( $params['per-page'] ) : 10;
		$automation_id = !empty( $params['automation_id'] ) ? $params['automation_id'] : 0;
		$email         = !empty( $params['email'] ) ? $params['email'] : '';
		$offset        = ( $page - 1 ) * $per_page;

		if ( !$automation_id || !$email ) {
			return array();
		}

		$log_data   = self::get_automation_log_by_id_and_email( $automation_id, $email, $offset, $per_page );
		$total_logs = self::get_automation_log_count( $automation_id, $email );

		return array(
			'current_page' => $page,
			'per_page'     => $per_page,
			'total'        => $total_logs,
			'data'         => CartCommon::formatted_automation_log( $log_data ),
		);
	}

	/**
	 * Retrieves the abandoned cart data by ID.
	 *
	 * @param int $abandoned_id The ID of the abandoned cart.
	 * @return array|false The abandoned cart data if found, or false if not found.
	 * @since 1.5.0
	 */
	public static function get_cart_by_id( $abandoned_id ) {
		if ( !$abandoned_id ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . AbandonedCartSchema::$table_name;

		/*
		 * user_id, session_key and created_at are selected for CartGate: deciding whether
		 * a cart may still be emailed needs the customer identity to look for an order,
		 * and created_at to scope that search. Every consumer reads named keys, so widening
		 * the projection cannot break one.
		 */
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT id,email,user_id,session_key,automation_id,status,created_at FROM {$table} WHERE id = %d", array( $abandoned_id, ) ), ARRAY_A ); //phpcs:ignore
		if ( is_array( $result ) && !empty( $result ) ) {
			return $result;
		}
		return false;
	}

	/**
	 * Retrieves the abandoned cart report data.
	 *
	 * @param string $filter The filter to be applied (e.g., 'all', 'today', 'last_7_days').
	 * @return array The abandoned cart report data.
	 * @since 1.5.0
	 */
	public static function get_abandoned_cart_report( $filter = 'all' ) {
		$abandoned_table = self::get_table_name();

		$cart_total                      = self::get_cart_total_report( $abandoned_table, $filter );
		$cart_total['potential_revenue'] = isset( $cart_total['potential_revenue'] ) ? MrmCommon::price_format_with_wc_currency( $cart_total['potential_revenue'] ) : MrmCommon::price_format_with_wc_currency( 0 );
		$cart_total['recovered_revenue'] = isset( $cart_total['recovered_revenue'] ) ? MrmCommon::price_format_with_wc_currency( $cart_total['recovered_revenue'] ) : MrmCommon::price_format_with_wc_currency( 0 );

		$revenue_bar_chart  = self::get_revenue_for_bar_chart( $filter );
		$recovery_rate      = self::get_recovery_rate( $filter );
		$revenue_line_chart = self::get_revenue_for_line_chart( $filter );

		return array(
			'cart_total'         => $cart_total,
			'currency'           => CartCommon::get_currency(),
			'revenue_bar_chart'  => $revenue_bar_chart,
			'recovery_rate'      => $recovery_rate,
			'revenue_line_chart' => $revenue_line_chart,
		);
	}
	/**
	 * Retrieves the revenue recovery rate.
	 *
	 * @param string $filter The filter to be applied (e.g., 'all', 'today', 'last_7_days').
	 * @return float The revenue recovery rate as a percentage.
	 * @since 1.5.0
	 */
	public static function get_recovery_rate( $filter ) {
		$abandoned_table = self::get_table_name();
		$get_total       = self::get_cart_total_report( $abandoned_table, $filter );

		$abandoned = isset( $get_total['abandoned'] ) ? intval( $get_total['abandoned'] ) : 0;
		$pending   = isset( $get_total['pending'] ) ? intval( $get_total['pending'] ) : 0;
		$recovered = isset( $get_total['recovered'] ) ? intval( $get_total['recovered'] ) : 0;
		$lost      = isset( $get_total['lost'] ) ? intval( $get_total['lost'] ) : 0;

		$total_abandoned = intval( $abandoned + $pending + $lost );

		$recovery_rate = 0;

		if ( 0 === $recovered ) {
			return 0;
		}

		$total_abandoned += $recovered;
		$recovery_rate    = number_format( ( $recovered / $total_abandoned ) * 100, 2 );
		return $recovery_rate;
	}
	/**
	 * Retrieves revenue data for the bar chart based on the provided filter.
	 *
	 * @param string $filter The filter to be applied (e.g., 'all', 'today', 'last_7_days').
	 * @return array The revenue data for the bar chart.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_bar_chart( $filter ) {
		$get_revenue  = 'get_revenue_for_bar_chart_' . strtolower( $filter );
		$revenue_data = self::$get_revenue();
		$max_revenue  = array_reduce(
			$revenue_data,
			function ( $carry, $revenues ) {
				$potential_revenue = isset( $revenues['potential_revenue'] ) ? $revenues['potential_revenue'] : 0;
				$recovered_revenue = isset( $revenues['recovered_revenue'] ) ? $revenues['recovered_revenue'] : 0;
				return max( $carry, $potential_revenue, $recovered_revenue );
			},
			PHP_INT_MIN
		);

		return array(
			'data'        => $revenue_data,
			'max_revenue' => $max_revenue,
		);
	}
	/**
	 * Get revenue data for the line chart based on the provided filter.
	 *
	 * @param string $filter The filter to be applied ('all', 'monthly', 'weekly', or 'yearly').
	 * @return array An array containing abandoned, recovered, and lost revenue data for the line chart.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_line_chart( $filter ) {
		$get_revenue  = 'get_revenue_for_line_chart_' . strtolower( $filter );
		$revenue_data = self::$get_revenue();
		$abandoned    = !empty( $revenue_data['abandoned'] ) ? $revenue_data['abandoned'] : array();
		$recovered    = !empty( $revenue_data['recovered'] ) ? $revenue_data['recovered'] : array();
		$lost         = !empty( $revenue_data['lost'] ) ? $revenue_data['lost'] : array();
		$max_value    = max(
			max( array_values( $abandoned ) ),
			max( array_values( $recovered ) ),
			max( array_values( $lost ) )
		);
		return array(
			'labels'      => array_keys( $abandoned ),
			'max_revenue' => $max_value,

			'abandoned'   => array(
				'values' => array_values( $abandoned ),
			),
			'recovered'   => array(
				'values' => array_values( $recovered ),
			),
			'lost'        => array(
				'values' => array_values( $lost ),
			),
		);
	}

	/**
	 * Retrieves the cart total report based on the provided filter.
	 *
	 * @param string $table_name The name of the table to query.
	 * @param string $filter The filter to be applied (e.g., 'all', 'today', 'last_7_days').
	 * @return array The cart total report data.
	 * @since 1.5.0
	 */
	public static function get_cart_total_report( $table_name, $filter ) {
		global $wpdb;
		$condition = CartCommon::get_where_query( $filter );
        $get_total = $wpdb->get_row(  //phpcs:ignore
			$wpdb->prepare(
				'SELECT
            COUNT(CASE WHEN status = %s THEN 1 END) AS abandoned,
            COUNT(CASE WHEN status = %s THEN 1 END) AS pending,
            COUNT(CASE WHEN status = %s THEN 1 END) AS recovered,
            COUNT(CASE WHEN status = %s THEN 1 END) AS lost,
            SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS potential_revenue,
            SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered_revenue
        FROM %1s WHERE %1s', array( 'abandoned', 'pending', 'recovered', 'lost', 'abandoned', 'recovered', $table_name, $condition )), ARRAY_A);  //phpcs:ignore

		if ( !empty( $get_total ) ) {
			return $get_total;
		}
		return array();
	}
	/**
	 * Retrieves revenue data for the bar chart for all months.
	 *
	 * @return array The revenue data for the bar chart with months labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_line_chart_all() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();

		$query  = "SELECT DATE_FORMAT(created_at, '%b') AS label";
		$query .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS abandoned';
		$query .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered';
		$query .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS lost';
		$query .= ' FROM %1s ';
		$query .= 'WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 5 YEAR) AND DATE_ADD(NOW(), INTERVAL 1 YEAR) ';
		$query .= "GROUP BY DATE_FORMAT(created_at, '%b') ";
		$query .= "ORDER BY DATE_FORMAT(created_at, '%b') ASC";
        $result = $wpdb->get_results( $wpdb->prepare( $query, array(  'abandoned', 'recovered','lost' ,$abandoned_cat_table) ), ARRAY_A ); //phpcs:ignore
		$months = CartCommon::get_months_array();
		return CartCommon::get_formated_line_chart( $result, $months );
	}

	/**
	 * Retrieves revenue data for the bar chart for the current week.
	 *
	 * @return array The revenue data for the bar chart with daily labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_line_chart_weekly() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();
		$week_start_end      = get_weekstartend( current_time( 'mysql' ), get_option( 'start_of_week', 1 ) );
		if ( ! empty( $week_start_end[ 'start' ] ) && ! empty( $week_start_end[ 'end' ] ) ) {
			$start_of_week = date_i18n( 'Y-m-d', $week_start_end['start'] );
			$end_of_week   = date_i18n( 'Y-m-d', $week_start_end['end'] );

			$query     = "SELECT DATE_FORMAT(created_at, '%b %e') AS label";
            $query .= $wpdb->prepare( ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS abandoned', array( 'abandoned' ) ); //phpcs:ignore
            $query .= $wpdb->prepare( ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered', array( 'recovered' ) ); //phpcs:ignore
            $query .= $wpdb->prepare( ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS lost', array( 'lost' ) ); //phpcs:ignore
            $query .= $wpdb->prepare( " FROM %1s ", array( $abandoned_cat_table ) ); //phpcs:ignore
			$query    .= "WHERE DATE_FORMAT(created_at, '%Y-%m-%d') >= ";
            $query .= $wpdb->prepare( '%s ', array( $start_of_week ) ); //phpcs:ignore
			$query    .= "AND DATE_FORMAT(created_at, '%Y-%m-%d') <= ";
            $query .= $wpdb->prepare( '%s ', array( $end_of_week ) ); //phpcs:ignore
			$query    .= "GROUP BY DATE_FORMAT(created_at, '%b %e') ";
			$query    .= "ORDER BY DATE_FORMAT(created_at, '%c %e') ASC";
            $result         = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
			$week_days = CartCommon::get_week_days( $start_of_week );
			return CartCommon::get_formated_line_chart( $result, $week_days );
		}
		return array();
	}
	/**
	 * Retrieves revenue data for the bar chart for the current month.
	 *
	 * @return array The revenue data for the bar chart with daily labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_line_chart_monthly() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();

		$query        = "SELECT DATE_FORMAT(created_at,  '%b %e') AS label";
		$query       .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS abandoned';
		$query       .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered';
		$query       .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS lost ';
		$query       .= ' FROM %1s ';
		$query       .= 'WHERE (EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now())) ';
		$query       .= "GROUP BY DATE_FORMAT(created_at,  '%b %e') ";
		$query       .= "ORDER BY DATE_FORMAT(created_at,  '%b %e') ASC";
        $result = $wpdb->get_results( $wpdb->prepare( $query, array(  'abandoned', 'recovered', 'lost' ,$abandoned_cat_table) ), ARRAY_A ); //phpcs:ignore
		$monthly_days = CartCommon::get_monthly_days();
		return CartCommon::get_formated_line_chart( $result, $monthly_days );
	}
	/**
	 * Retrieves revenue data for the bar chart for the current year.
	 *
	 * @return array The revenue data for the bar chart with monthly labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_line_chart_yearly() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();

		$query  = "SELECT DATE_FORMAT(created_at, '%b') AS label";
		$query .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS abandoned';
		$query .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered';
		$query .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS lost ';
		$query .= ' FROM %1s ';
		$query .= 'WHERE YEAR(created_at) = YEAR(now()) ';
		$query .= "GROUP BY DATE_FORMAT(created_at, '%b') ";
		$query .= "ORDER BY DATE_FORMAT(created_at, '%b') ASC";
        $result = $wpdb->get_results( $wpdb->prepare( $query, array(  'abandoned', 'recovered', 'lost' ,$abandoned_cat_table) ), ARRAY_A ); //phpcs:ignore
		$months = CartCommon::get_months_array();
		return CartCommon::get_formated_line_chart( $result, $months );
	}

	/**
	 * Retrieves revenue data for the bar chart for all months.
	 *
	 * @return array The revenue data for the bar chart with months labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_bar_chart_all() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();

		$query          = "SELECT DATE_FORMAT(created_at, '%b') AS label";
		$query         .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS potential_revenue';
		$query         .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered_revenue';
		$query         .= ' FROM %1s ';
		$query         .= 'WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 5 YEAR) AND DATE_ADD(NOW(), INTERVAL 1 YEAR) ';
		$query         .= "GROUP BY DATE_FORMAT(created_at, '%b') ";
		$query         .= "ORDER BY DATE_FORMAT(created_at, '%b') ASC";
        $result = $wpdb->get_results( $wpdb->prepare( $query, array(  'abandoned', 'recovered' ,$abandoned_cat_table) ), ARRAY_A ); //phpcs:ignore
		$formatted_data = CartCommon::format_data_with_label( $result );
		$months         = CartCommon::get_months_multi_dimensional_array();
		return array_merge( $months, $formatted_data );
	}

	/**
	 * Retrieves revenue data for the bar chart for the current week.
	 *
	 * @return array The revenue data for the bar chart with daily labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_bar_chart_weekly() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();
		$week_start_end      = get_weekstartend( current_time( 'mysql' ), get_option( 'start_of_week', 1 ) );
		if ( ! empty( $week_start_end[ 'start' ] ) && ! empty( $week_start_end[ 'end' ] ) ) {
			$start_of_week = date_i18n( 'Y-m-d', $week_start_end['start'] );
			$end_of_week   = date_i18n( 'Y-m-d', $week_start_end['end'] );

			$query          = "SELECT DATE_FORMAT(created_at, '%b %e') AS label";
            $query .= $wpdb->prepare( ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS potential_revenue', array( 'abandoned' ) ); //phpcs:ignore
            $query .= $wpdb->prepare( ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered_revenue', array( 'recovered' ) ); //phpcs:ignore
            $query .= $wpdb->prepare( " FROM %1s ", array( $abandoned_cat_table ) ); //phpcs:ignore
			$query         .= "WHERE DATE_FORMAT(created_at, '%Y-%m-%d') >= ";
            $query .= $wpdb->prepare( '%s ', array( $start_of_week ) ); //phpcs:ignore
			$query         .= "AND DATE_FORMAT(created_at, '%Y-%m-%d') <= ";
            $query .= $wpdb->prepare( '%s ', array( $end_of_week ) ); //phpcs:ignore
			$query         .= "GROUP BY DATE_FORMAT(created_at, '%b %e') ";
			$query         .= "ORDER BY DATE_FORMAT(created_at, '%c %e') ASC";
			$result         = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore
			$formatted_data = CartCommon::format_data_with_label( $result );
			$week_days      = CartCommon::get_week_days( $start_of_week );

			return array_merge( $week_days, $formatted_data );
		}
		return array();
	}
	/**
	 * Retrieves revenue data for the bar chart for the current month.
	 *
	 * @return array The revenue data for the bar chart with daily labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_bar_chart_monthly() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();

		$query          = "SELECT DATE_FORMAT(created_at,  '%b %e') AS label";
		$query         .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS potential_revenue';
		$query         .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered_revenue';
		$query         .= ' FROM %1s ';
		$query         .= 'WHERE (EXTRACT(YEAR_MONTH FROM created_at) = EXTRACT(YEAR_MONTH FROM now())) ';
		$query         .= "GROUP BY DATE_FORMAT(created_at,  '%b %e') ";
		$query         .= "ORDER BY DATE_FORMAT(created_at,  '%b %e') ASC";
        $result = $wpdb->get_results( $wpdb->prepare( $query, array(  'abandoned', 'recovered' ,$abandoned_cat_table) ), ARRAY_A ); //phpcs:ignore
		$formatted_data = CartCommon::format_data_with_label( $result );
		$monthly_days   = CartCommon::get_monthly_days();
		return array_merge( $monthly_days, $formatted_data );
	}
	/**
	 * Retrieves revenue data for the bar chart for the current year.
	 *
	 * @return array The revenue data for the bar chart with monthly labels.
	 * @since 1.5.0
	 */
	public static function get_revenue_for_bar_chart_yearly() {
		global $wpdb;
		$abandoned_cat_table = self::get_table_name();

		$query          = "SELECT DATE_FORMAT(created_at, '%b') AS label";
		$query         .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS potential_revenue';
		$query         .= ', SUM(CASE WHEN status = %s THEN total ELSE 0 END) AS recovered_revenue';
		$query         .= ' FROM %1s ';
		$query         .= 'WHERE YEAR(created_at) = YEAR(now()) ';
		$query         .= "GROUP BY DATE_FORMAT(created_at, '%b') ";
		$query         .= "ORDER BY DATE_FORMAT(created_at, '%b') ASC";
        $result = $wpdb->get_results( $wpdb->prepare( $query, array(  'abandoned', 'recovered' ,$abandoned_cat_table) ), ARRAY_A ); //phpcs:ignore
		$formatted_data = CartCommon::format_data_with_label( $result );
		$months         = CartCommon::get_months_multi_dimensional_array();
		return array_merge( $months, $formatted_data );
	}

	/**
	 * Get automation data based on the provided automation ID.
	 *
	 * @param int $automation_id The ID of the automation to retrieve data for.
	 * @return array|false An array containing automation data or false if no data is found.
	 * @since 1.5.0
	 */
	public static function get_automation_data( $automation_id ) {
		if ( !$automation_id ) {
			return false;
		}
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;
        $results          = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $automation_table WHERE id = %d and status = %s", array($automation_id, 'active' )), ARRAY_A ); //phpcs:ignore
		if ( !empty( $results ) ) {
			return $results;
		}
		return array();
	}
	/**
	 * Retrieves all active abandoned cart automation records with a specific trigger name.
	 *
	 * This function queries the database to fetch active abandoned cart automation records
	 * that match the provided trigger name.
	 *
	 * @return array Associative array containing the id and name of matching automation records.
	 * @since 1.5.5
	 */
	public static function get_all_abandoned_cart_automation() {
		global $wpdb;
		$automation_table = $wpdb->prefix . AutomationSchema::$table_name;
        $results          = $wpdb->get_results( $wpdb->prepare( "SELECT id as automation_id, name as automation_name  FROM $automation_table WHERE trigger_name = %s and status = %s ORDER BY id DESC", array('wc_abandoned_cart', 'active' )), ARRAY_A ); //phpcs:ignore
		if ( !empty( $results ) ) {
			return $results;
		}
		return array();
	}

	/**
	 * Checks if an abandoned cart with the specified ID exists.
	 *
	 * This function queries the database to check whether an abandoned cart record
	 * with the given ID exists in the database.
	 *
	 * @param int $id The ID of the abandoned cart to check.
	 * @return bool True if an abandoned cart with the specified ID exists, false otherwise.
	 * @since 1.5.5
	 */
	public static function is_abandoned_cart_exist( $id ) {
		global $wpdb;
		$table  = $wpdb->prefix . AbandonedCartSchema::$table_name;
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", array( $id ) ), ARRAY_A ); //phpcs:ignore
		if ( is_array( $result ) && !empty( $result ) ) {
			return true;
		}
		return false;
	}
	/**
	 * Runs the abandoned cart automation manually for a specific abandoned cart ID.
	 *
	 * This function triggers the abandoned cart automation process for a given abandoned cart ID.
	 * It checks if the abandoned cart exists, and if so, it fires the 'mailmint_after_cart_abandoned'
	 * action hook.
	 *
	 * @param int $abandoned_id The ID of the abandoned cart to run automation for.
	 * @param int $automation_id The ID of the triggered automation to run automation for.
	 * @return bool True if the abandoned cart exists and automation was triggered, false otherwise.
	 * @since 1.5.5
	 */
	public static function run_automation_manually( $abandoned_id, $automation_id ) {
		if ( !$abandoned_id || !$automation_id ) {
			return false;
		}
		$is_cart_exist = self::is_abandoned_cart_exist( $abandoned_id );
		if ( $is_cart_exist ) {
			/**
			 * Executes the 'mailmint_manually_run_abandoned_data' action hook, triggering manual processing
			 * of abandoned data for a specific abandoned item and automation.
			 *
			 * This action is typically used to manually initiate the processing of abandoned data, which might
			 * involve sending notifications, updating records, or performing other tasks related to abandoned items.
			 *
			 * @since 1.0.0
			 *
			 * @param int $abandoned_id The ID of the abandoned item to be processed.
			 * @param int $automation_id The ID of the automation associated with the abandoned item.
			 */
			do_action( 'mailmint_manually_run_abandoned_data', $abandoned_id, $automation_id );
			return true;
		}
		return false;
	}

	/**
	 * Retrieves the abandoned cart details by ID.
	 *
	 * This function queries the database to fetch the details of an abandoned cart record
	 * with the specified ID.
	 *
	 * @param int    $abandoned_id The ID of the abandoned cart to retrieve details for.
	 * @param string $status       The status of the abandoned cart to retrieve.
	 * @return array|false An array containing the abandoned cart details if found, or false if not found.
	 * @since 1.5.5
	 */
	public static function get_cart_details_by_id($abandoned_id, $status = ''){
		global $wpdb;
		$table  = $wpdb->prefix . AbandonedCartSchema::$table_name;

		// Base query and parameters.
		$query = "SELECT * FROM {$table} WHERE id = %d";
		$params = [$abandoned_id];

		// Add status condition if status is not empty.
		if ( !empty( $status ) ) {
			$query .= " AND status = %s";
			$params[] = $status;
		}

		// Prepare and execute the query.
		$result = $wpdb->get_row( $wpdb->prepare( $query, $params ), ARRAY_A ); //phpcs:ignore
		if (is_array($result) && !empty($result)) {
			return $result;
		}
		return array();
	}
}
