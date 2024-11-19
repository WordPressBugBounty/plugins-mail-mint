<?php

namespace Mint\MRM\Internal\Admin\Notices;

use Mint\MRM\DataBase\Migration\DatabaseMigrator;


class DBUpgradeNotice {

	/**
	 * DB updates callbacks that will be run per version
	 *
	 * @var \string[][]
	 */
	public static $db_updates = array(
		'1.6.0' => array(
			'mm_update_160_migrate_broadcast_table',
		),
	);


	/**
	 * Initializes actions and hooks needed migration notices on admin panel.
	 *
	 * @since 1.6.0
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'database_update_notice' ) );
	}



	/**
	 * Show user a notice with a button if he wants to update the database
	 *
	 * @since 1.6.0
	 * @since 1.14.0 Added check for database update notice.
	 */
	public function database_update_notice() {
		if ( !self::should_show_notice() ) {
			return;
		}

		/**
		 * Check if there are any migration required.
		 */
		if ( DatabaseMigrator::needs_db_update() && 
			('no' === get_option( 'mail_mint_db_1140_version_updated', 'no' )
			|| 'no' === get_option( 'mail_mint_db_1152_version_updated', 'no' ) ) ) {
			/**
			 * Checks if a scheduled queue is running or if the user has initiated the process.
			 * If a queue is running, indicates that the database migration action is in progress.
			 * Otherwise, displays the database update notice to the user.
			 */
			if ( as_has_scheduled_action( 'mail_mint_run_update_callback' ) || ! empty( $_GET['do_update_mm_database'] )) {
				include dirname( __FILE__ ) . '/views/html-notice-db-updating.php';
			} else {
				include dirname( __FILE__ ) . '/views/html-notice-db-update.php';
			}
		} else {
			include dirname( __FILE__ ) . '/views/html-notice-db-updated.php';
		}
	}


	/**
	 * Determines whether the database update notice should be displayed.
	 *
	 * @return mixed|void
	 *
	 * @since 1.6.0
	 * @since 1.14.0 Added check for new notice.
	 */
	public static function should_show_notice() {
		$previous_notice = get_option( 'mail_mint_hide_database_update_notice', 'no' );
		$new_notice      = get_option( 'mail_mint_hide_wc_database_update_notice', 'no' );
		$another_notice  = get_option( 'mail_mint_hide_template_table_update_notice', 'no' );

		if ( 'yes' === $previous_notice && 'no' === $new_notice && 'no' === $another_notice ) {
			return true;
		} else if ( 'no' === $previous_notice && 'no' === $new_notice && 'no' === $another_notice ) {
			return true;
		} else if ( 'yes' === $previous_notice && 'yes' === $new_notice && 'no' === $another_notice ) {
			return true;
		} else if ( 'yes' === $previous_notice && 'yes' === $new_notice && 'yes' === $another_notice ) {
			return false;
		} else {
			return false;
		}
	}

}
