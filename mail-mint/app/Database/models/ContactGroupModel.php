<?php
/**
 * Manage Contact Groups Module related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use Mint\MRM\Admin\API\Controllers\ContactPivotController;
use Mint\MRM\DataBase\Tables\ContactGroupPivotSchema;
use Mint\MRM\DataBase\Tables\ContactGroupSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use PHPUnit\Exception;

/**
 * ContactGroupModel class
 *
 * Manage Contact Groups Module related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class ContactGroupModel {

	/**
	 * Insert group information to database
	 *
	 * @param object $group Tag or List or Segment object.
	 * @param string $type group type.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function insert( $group, $type ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			$wpdb->insert(
				$group_table,
				array(
					'title'      => $group->get_title(),
					'type'       => $type,
					'data'       => $group->get_data(),
					'created_at' => current_time( 'mysql' ),
				)
			); // db call ok.
			return $wpdb->insert_id;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Update group information to database
	 *
	 * @param object $group         Tag or List or Segment object.
	 * @param int    $id            Tag or List or Segment id.
	 * @param string $type          Tag or List or Segment type.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function update( $group, $id, $type ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			$wpdb->update(
				$group_table,
				array(
					'title'      => $group->get_title(),
					'type'       => $type,
					'data'       => $group->get_data(),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $id )
			); // db call ok. ; no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Run SQL query to get groups from database
	 *
	 * @param string $type     Tag or List or Segment type.
	 * @param int    $offset   offset.
	 * @param int    $limit    limit.
	 * @param string $search   search.
	 * @param string $order_by sorting order.
	 * @param string $order_type order type.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all( $type, $offset = 0, $limit = 20, $search = '', $order_by = 'title', $order_type = 'ASC' ) {
		global $wpdb;
		$group_table  = $wpdb->prefix . ContactGroupSchema::$table_name;
		$pivot_table  = $wpdb->prefix . ContactGroupPivotSchema::$table_name;
		$search_terms = null;
		// Search groups by title.
		if ( ! empty( $search ) ) {
			$search       = $wpdb->esc_like( $search );
			$search_terms = "AND title LIKE '%%$search%%'";
		}

		// Return groups for list view.
		try {
			$query  = 'SELECT COUNT( DISTINCT contact_id,group_id) as total_contacts, g.id, g.title, g.data, g.created_at ';
			$query .= $wpdb->prepare( 'from %1s as p right join %1s as g ', $pivot_table, $group_table ); //phpcs:ignore
			$query .= 'on p.group_id = g.id ';
			$query .= $wpdb->prepare( 'where type = %s ', $type );
			$query .= "{$search_terms} ";
			$query .= 'group by g.id, g.title, g.data, g.created_at ';
			$query .= $wpdb->prepare( 'order by %s %s ', $order_by, $order_type ); //phpcs:ignore
			if ( 0 !== $limit ) {
				$query .= $wpdb->prepare( 'limit %d, %d', $offset, $limit );
			}
			$query_results = $wpdb->get_results( $query, ARRAY_A ); //phpcs:ignore

			$query  = 'SELECT COUNT(*) as total FROM ( ';
			$query .= 'SELECT count(group_id) as total_contacts, g.id, g.title, g.data, g.created_at ';
			$query .= $wpdb->prepare( 'from %1s as p right join %1s as g ', $pivot_table, $group_table ); //phpcs:ignore
			$query .= 'on p.group_id = g.id ';
			$query .= $wpdb->prepare( 'where type = %s ', $type );
			$query .= "{$search_terms} ";
			$query .= 'group by g.id, g.title, g.data, g.created_at ';
			$query .= ') as table1 ';

			$count_result = $wpdb->get_var( $query ); //phpcs:ignore
			$count        = (int) $count_result;
			$total_pages  = 0;
			if ( 0 !== $limit ) {
				$total_pages = ceil( $count / $limit );
			}

			return array(
				'data'        => $query_results,
				'total_pages' => $total_pages,
				'total_count' => $count,
			);
		} catch ( \Exception $e ) {
			return null;
		}
	}


	/**
	 * Run SQL query to get groups from database
	 *
	 * @param string $type     Tag or List or Segment type.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all_to_custom_select( $type ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT `id`, `title` FROM %1s WHERE type = %s ORDER BY `title` ASC", array( $group_table, $type ) ), ARRAY_A ); //phpcs:ignore
		return array(
			'data' => $results,
		);
	}


	/**
	 * Delete a group from the database
	 *
	 * @param mixed $id group id (tag_id, list_id, segment_id).
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy( $id ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			self::delete_contact_group_relation( array( $id ) );
			$wpdb->delete( $group_table, array( 'id' => $id ), array( '%d' ) ); // db call ok. ; no-cache ok.
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Delete multiple groups from the database
	 *
	 * @param array $ids multiple group ids (tag_id, list_id, segment_id).
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function destroy_all( $ids ) {
		global $wpdb;

		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			if ( is_array( $ids ) ) {
				self::delete_contact_group_relation( $ids );
				$ids   = implode( ',', array_map( 'intval', $ids ) );
				$query = $wpdb->prepare( 'DELETE FROM %1s WHERE `id` IN(%1s)', $group_table, $ids ); //phpcs:ignore
				$wpdb->query( $query ); //phpcs:ignore
			}
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Returns a single group data
	 *
	 * @param int $id Tag, List or Segment ID.
	 *
	 * @return array|bool
	 * @since 1.0.0
	 */
	public static function get( $id ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			$select_query = $wpdb->prepare( "SELECT * FROM %1s WHERE id = %d", array( $group_table, $id ) ); //phpcs:ignore
			return $wpdb->get_row( $select_query, ARRAY_A ); //phpcs:ignore
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Run SQL Query to get groups related to a contact
	 *
	 * @param mixed $group_ids group ids.
	 * @param mixed $type group type.
	 *
	 * @return array|bool
	 * @since 1.0.0
	 */
	public static function get_groups_to_contact( $group_ids, $type ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			$groups       = implode( ',', array_map( 'intval', $group_ids ) );
			$select_query = $wpdb->prepare( "SELECT * FROM %1s WHERE id IN ({$groups}) AND type = %s", array( $table_name, $type ) ); //phpcs:ignore
			return $wpdb->get_results( $select_query ); //phpcs:ignore
		} catch ( \Exception $e ) {
			return false;
		}
	}


	/**
	 * Check existing tag, list or segment on database by id
	 *
	 * @param int $id   group id.
	 *
	 * @return bool
	 * @since 1.0.4
	 */
	public static function is_group_exist_by_id( $id ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %1s WHERE id= %d", array( $group_table, $id ) ) ); //phpcs:ignore
		if ( $result ) {
			return true;
		}
		return false;
	}


	/**
	 * Return contact groups count data
	 *
	 * @param mixed $type group type.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_groups_count( $type ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(`id`) FROM %1s WHERE type = %s', array( $group_table, $type ) ) ) ); //phpcs:ignore
	}

	/**
	 * Run SQL Query to get groups related to a contact
	 *
	 * @param mixed $type group type.
	 *
	 * @return array|bool
	 * @since 1.0.0
	 */
	public static function get_all_lists_or_tags( $type ) {
		global $wpdb;
		$table_name = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			return $wpdb->get_results( $wpdb->prepare( "SELECT `id`, `title`  FROM %1s WHERE  type = %s", array( $table_name, $type ) ),ARRAY_A ); // phpcs:ignore
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get all group ids [list/tag/segments]
	 *
	 * @return array|object|\stdClass[]|null
	 *
	 * @since 1.0.0
	 */
	public static function get_all_group_ids() {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;
		return $wpdb->get_col( "SELECT `id` FROM {$group_table}" ); //phpcs:ignore
	}

	/**
	 * Delete contact group relations
	 *
	 * @param array $group_ids List/Tag ids.
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 */
	public static function delete_contact_group_relation( $group_ids ) {
		global $wpdb;
		$group_pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		if ( is_array( $group_ids ) && !empty( $group_ids ) ) {
			try {
				$group_ids = implode( ', ', array_map( 'intval', $group_ids ) );
				$query = $wpdb->prepare( 'DELETE FROM %1s WHERE `group_id` IN(%1s)', $group_pivot_table, $group_ids ); //phpcs:ignore
				return $wpdb->query( $query ); //phpcs:ignore
			} catch ( \Exception $e ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if a group already exists [by slug]
	 *
	 * @param mixed $slug group slug.
	 * @param mixed $type group type.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_group_exists( $slug, $type ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		try {
			return $wpdb->get_var( $wpdb->prepare( 'SELECT `id` FROM %1s WHERE `title` = %s AND `type` = %s', array( $group_table, $slug, $type ) ) ); //phpcs:ignore
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Add lists to new contact
	 *
	 * @param array     $lists List of lists.
	 * @param int|array $contact_id Contact ID.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function set_lists_to_contact( $lists, $contact_id ) {
		$pivot_ids = array_map(
			function( $list ) use ( $contact_id ) {
				return array(
					'group_id'   => isset( $list[ 'id' ] ) ? $list[ 'id' ] : $list,
					'contact_id' => $contact_id,
				);
			},
			$lists
		);

		$response        = ContactPivotController::set_groups_to_contact( $pivot_ids );
		$trigger_control = apply_filters( 'mint_automation_trigger_control_on_import', false );

		if ( ! $trigger_control ) {
			do_action( 'mailmint_list_applied', $lists, $contact_id );
		}

		return $response;
	}

	/**
	 * Add lists to multiple contacts
	 *
	 * @param array $lists List of lists.
	 * @param mixed $contact_ids Contact IDs.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function set_lists_to_multiple_contacts( $lists, $contact_ids ) {
		$res             = array_map(
			function ( $list ) use ( $contact_ids ) {
				$pivot_ids = array_map(
					function ( $contact_id ) use ( $list ) {
						return array(
							'group_id'   => $list['id'],
							'contact_id' => $contact_id,
						);
					},
					$contact_ids
				);

				( ContactPivotController::set_groups_to_contact( $pivot_ids ) );
			},
			$lists
		);
		$trigger_control = apply_filters( 'mint_automation_trigger_control_on_import', false );

		if ( ! $trigger_control ) {
			do_action( 'mailmint_list_applied', $lists, $contact_ids );
		}
		return $res;
	}

	/**
	 * Return lists which are assigned to a contact
	 *
	 * @param mixed $contact Single contact object.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_lists_to_contact( $contact ) {
		$contact_id = 0;
		if ( ! empty( $contact[ 'contact_id' ] ) ) {
			$contact_id = $contact[ 'contact_id' ];
		} elseif ( ! empty( $contact[ 'id' ] ) ) {
			$contact_id = $contact[ 'id' ];
		}
		$contact['lists'] = array();
		$results          = $contact_id ? ContactPivotController::get_instance()->get_groups_to_contact( $contact_id ) : array();

		if ( ! empty( $results ) ) {
			$list_ids = array_map(
				function( $list_id ) {
					return $list_id['group_id'];
				},
				$results
			);

			$contact['lists'] = !empty( $list_ids ) ? self::get_groups_to_contact( $list_ids, 'lists' ) : array();
		}

		return $contact;
	}

	/**
	 * Add tags to new contact
	 *
	 * @param array     $tags List of tags.
	 * @param int|array $contact_id Contact Id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function set_tags_to_contact( $tags, $contact_id ) {
		$pivot_ids       = array_map(
			function ( $tag ) use ( $contact_id ) {
				return array(
					'group_id'   => isset( $tag['id'] ) ? $tag['id'] : $tag,
					'contact_id' => $contact_id,
				);
			},
			$tags
		);
		$response        = ContactPivotController::set_groups_to_contact( $pivot_ids );
		$trigger_control = apply_filters( 'mint_automation_trigger_control_on_import', false );

		if ( ! $trigger_control ) {
			do_action( 'mailmint_tag_applied', $tags, $contact_id );
		}
		return $response;
	}

	/**
	 * Add tags to multiple contacts
	 *
	 * @param array $tags List of tags.
	 * @param mixed $contact_ids Contact Id.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function set_tags_to_multiple_contacts( $tags, $contact_ids ) {
		$res             = array_map(
			function ( $tag ) use ( $contact_ids ) {
				$pivot_ids = array_map(
					function ( $contact_id ) use ( $tag ) {
						return array(
							'group_id'   => $tag['id'],
							'contact_id' => $contact_id,
						);
					},
					$contact_ids
				);

				$response = ContactPivotController::set_groups_to_contact( $pivot_ids );
			},
			$tags
		);
		$trigger_control = apply_filters( 'mint_automation_trigger_control_on_import', false );

		if ( ! $trigger_control ) {
			do_action( 'mailmint_tag_applied', $tags, $contact_ids );
		}
		return $res;
	}

	/**
	 * Return tags which are assigned to a contact
	 *
	 * @param mixed $contact Single contact object.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_tags_to_contact( $contact ) {
		$contact_id      = isset( $contact['contact_id'] ) ? $contact['contact_id'] : $contact['id'];
		$contact['tags'] = array();
		$results         = ContactPivotController::get_instance()->get_groups_to_contact( $contact_id );

		if ( ! empty( $results ) ) {
			$tag_ids = array_map(
				function( $tag_id ) {
					return $tag_id['group_id'];
				},
				$results
			);

			$contact['tags'] = self::get_groups_to_contact( $tag_ids, 'tags' );
		}

		return $contact;
	}

	/**
	 * Summary: Retrieves contact groups for contact export.
	 * Description: Retrieves contact groups of the specified type (segments or any other) for contact export.
	 *
	 * @access public
	 *
	 * @param string $type The type of contact groups to retrieve. Default is 'segments'.
	 * @return array Returns an array containing contact groups with 'id' and 'title' fields.
	 *
	 * @since 1.5.0
	 */
	public static function get_groups_to_contact_export( $type = 'segments' ) {
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		return $wpdb->get_results( $wpdb->prepare( "SELECT id, title FROM {$group_table} WHERE type = %s", array( $type ) ), ARRAY_A ); // phpcs:ignore.
	}

	/**
	 * Summary: Get or insert a contact group by title.
	 *
	 * Description: This method checks if a contact group with the specified title already exists in the database.
	 * If it exists, the method returns the existing ID and title.
	 * If it doesn't exist, a new contact group is inserted into the database, and the method returns the newly inserted ID and title.
	 *
	 * @access public
	 *
	 * @param string $title The title of the contact group.
	 * @param string $type  The type of the contact group.
	 *
	 * @return array Returns an array containing the contact group ID and title.
	 *
	 * @since 1.7.1
	 */
	public static function get_or_insert_contact_group_by_title( $title, $type ) {
		if ( empty( $title ) ) {
			return array();
		}
		global $wpdb;
		$group_table = $wpdb->prefix . ContactGroupSchema::$table_name;

		// Check if the title exists in the database.
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, title FROM {$group_table} WHERE title = %s", $title ), ARRAY_A ); // phpcs:ignore.

		if ( $existing ) {
			// If title exists, return ID and title.
			return $existing;
		} else {
			// If title doesn't exist, insert into the database.
			$wpdb->insert( // phpcs:ignore.
				$group_table,
				array(
					'title'      => $title,
					'type'       => $type,
					'data'       => null,
					'created_at' => current_time( 'mysql' ),
				)
			);

			// Get the newly inserted post ID.
			$new_post_id = $wpdb->insert_id;

			// Return the new ID and title.
			return array(
				'id'    => $new_post_id,
				'title' => $title,
			);
		}
	}
}
