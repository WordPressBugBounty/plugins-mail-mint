<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Frontend
 */

namespace Mint\MRM\Internal\Admin;

use Mint\Mrm\Internal\Traits\Singleton;

/**
 * Manages front-end nav menu
 *
 * @package /app/Internal/Frontend
 * @since 1.0.0
 */
class HandleFrontendMenu {

	use Singleton;

	/**
	 * Initialize actions
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		add_filter( 'wp_page_menu_args', array( $this, 'exclude_pages_from_menu_items' ) );
		add_filter( 'wp_get_nav_menu_items', array( $this, 'exclude_pages_from_menu_items' ) );
	}

	/**
	 * Remove mrm pages from nav menu items
	 *
	 * @param object $items Menu items.
	 *
	 * @return object
	 */
	public function exclude_pages_from_menu_items( $items ) {
		$exclude_pages_ids = $this->get_mrm_page_ids();

		if ( ! empty( $exclude_pages_ids ) ) {
			foreach ( $items as $key => $item ) {
				if ( isset( $item->object_id ) && in_array( $item->object_id, $exclude_pages_ids, true ) ) {
					unset( $items[ $key ] );
				}
			}
		}
		return $items;
	}

	/**
	 * Get mrm default page ids by post name
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private function get_mrm_page_ids() {
		global $wpdb;

		$post_table = $wpdb->prefix . 'posts';

		$sql = 'SELECT `id` FROM %1s WHERE `post_type` = %s AND `post_name` IN (%s, %s, %s)';

		return $wpdb->get_col( $wpdb->prepare( $sql, $post_table, 'page', 'optin_confirmation', 'preference_page', 'unsubscribe_confirmation' ) ); //phpcs:ignore
	}

}
