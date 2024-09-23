<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin/WP-User
 */

namespace Mint\MRM\Internal\Admin;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * Manages actions after wp user delete
 *
 * @package /app/Internal/Admin/WP-User
 * @since 1.0.0
 */
class WPUserDelete {

	use Singleton;

	/**
	 * Initialize class functionalities
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'deleted_user', array( $this, 'remove_from_mailmint_users' ) );
	}

	/**
	 * Remove Mail Mint user
	 *
	 * @param string|int $user_id WP User ID.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function remove_from_mailmint_users( $user_id ) {
        $is_user_delete                = get_option( '_mint_compliance');
        $is_user_delete                = isset( $is_user_delete['user_info_delete'] ) ? $is_user_delete['user_info_delete'] : 'no';
		if ( 'yes' === $is_user_delete ) {
			$mailmint_user_id = ContactModel::get_user_id_by_wp_user_id( $user_id );

			if ( $mailmint_user_id ) {
				ContactModel::destroy( $mailmint_user_id );
			}
		}
	}
}