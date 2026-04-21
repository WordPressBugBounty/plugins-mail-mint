<?php
/**
 * Creates default mailing lists on plugin activation.
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/Internal/Admin
 * @since 1.22.0
 */

namespace Mint\MRM\Internal\Admin;

use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataStores\ListData;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * DefaultListsCreator class
 *
 * Creates default mailing lists the first time the plugin is installed.
 * A flag option prevents duplicate creation on subsequent activations.
 *
 * @package /app/Internal/Admin
 * @since 1.22.0
 */
class DefaultListsCreator {

	use Singleton;

	/**
	 * WordPress option key used as a one-time creation flag.
	 *
	 * @var string
	 * @since 1.22.0
	 */
	const FLAG_OPTION = 'mint_default_lists_created';

	/**
	 * Register hooks.
	 *
	 * @since 1.22.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_create_default_lists' ) );
	}

	/**
	 * Create default lists once on a fresh install.
	 *
	 * Runs on admin_init but only proceeds when the transient set by
	 * MrmActivator::activate() during a new installation is present.
	 * This prevents list creation for existing users upgrading to this version.
	 *
	 * @return void
	 * @since 1.22.0
	 */
	public function maybe_create_default_lists() {
		if ( get_option( self::FLAG_OPTION ) ) {
			return;
		}

		if ( ! get_transient( 'mailmint_fresh_install_pending' ) ) {
			update_option( self::FLAG_OPTION, 'yes', false );
			return;
		}

		$this->create_default_lists();
		delete_transient( 'mailmint_fresh_install_pending' );
		update_option( self::FLAG_OPTION, 'yes', false );
	}

	/**
	 * Insert the default mailing lists into the database.
	 *
	 * Always creates "Newsletter mailing list" and "WordPress Users".
	 * Creates "WooCommerce Customers" only when WooCommerce is active.
	 *
	 * @return void
	 * @since 1.22.0
	 */
	private function create_default_lists() {
		$lists = array(
			array(
				'title' => __( 'Newsletter mailing list', 'mrm' ),
				'data'  => __( 'This list is automatically created when you install Mail Mint.', 'mrm' ),
			),
			array(
				'title' => __( 'WordPress Users', 'mrm' ),
				'data'  => __( 'This list contains all of your WordPress users.', 'mrm' ),
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$lists[] = array(
				'title' => __( 'WooCommerce Customers', 'mrm' ),
				'data'  => __( 'This list contains all of your WooCommerce customers.', 'mrm' ),
			);
		}

		foreach ( $lists as $list_args ) {
			$list = new ListData( $list_args );
			ContactGroupModel::insert( $list, 'lists' );
		}
	}
}
