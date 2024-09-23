<?php
/**
 * WPModel class
 *
 * Manages custom wp api related operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */

namespace Mint\MRM\DataBase\Models;

/**
 * WPModel class
 *
 * Manages custom wp api related operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class WPModel {

	/**
	 * Get Data by post name.
	 *
	 * @param string $post_type WP post types.
	 *
	 * @return array|object|\stdClass[]|null
	 *
	 * @since 1.0.0
	 */
	public static function get_data_by_post_type( $post_type ) {
		global $wpdb;

		$query  = 'SELECT `ID` AS `id`, `post_title` AS `title` ';
		$query .= "FROM {$wpdb->posts} ";
		$query .= 'WHERE `post_type` = %s ';
		$query .= 'AND `post_status` = %s';

		return $wpdb->get_results( $wpdb->prepare( $query, $post_type, 'publish' ), ARRAY_A ); //phpcs:ignores
	}
}
