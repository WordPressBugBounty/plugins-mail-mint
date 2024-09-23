<?php
/**
 * Check if this is a new installation for MRM plugin,
 * or upgrade the existing one or do nothing if versions are up to date
 *
 * @package Mint\MRM\DataBase\
 * @namespace Mint\MRM\DataBase\
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase;

use Mint\Mrm\Internal\Traits\Singleton;
/**
 * Upgrade class
 *
 * Check if this is a new installation for MRM plugin,
 * or upgrade the existing one or do nothing if versions are up to date
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class Upgrade {

	use Singleton;

	/**
	 * Version
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $version = null;


	/**
	 * Check if this is a new installation for MRM plugin,
	 * or upgrade the existing one or do nothing if versions are up to date
	 *
	 * @since 1.0.0
	 */
	public function maybe_upgrade() {
		// Fresh install.
		$this->install();
	}


	/**
	 * Fresh install of MRM
	 *
	 * @since 1.0.0
	 */
	public function install() {
		// Installing schema.
		if ( $this->requires_install() ) {
			$this->flush_versions();

			$this->upgrade_schema();

			if ( defined( 'MRM_DB_VERSION' ) ) {
				update_option( 'mail_mint_db_version', MRM_DB_VERSION, false );
			}
		}

		/**
		 * Fires after MRM is fully installed
		 *
		 * @param int MRM_VERSION new version
		 * @since 1.0.0
		 */
		do_action( 'mailmint_post_install', MRM_VERSION );
	}


	/**
	 * Sets up the tables for MRM
	 *
	 * @since 1.0.0
	 */
	public function upgrade_schema() {
		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		global $wpdb;
		$schema          = $this->get_db_schema();
		$charset_collate = $wpdb->get_charset_collate();
		foreach ( $schema as $table_name => $table_class ) {
			$table_class_name = 'Mint\\MRM\\DataBase\\Tables\\' . $table_class;
			$table            = new $table_class_name();
			$sql              = $table->get_sql() . $charset_collate;
			dbDelta( $sql );
		}
	}


	/**
	 * Get MRM DB schema
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_db_schema() {
		require_once MRM_DIR_PATH . 'app/Database/Model.php';
		return Model::get_tables();
	}


	/**
	 * Return true if MRM needs to be install from scratch
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function requires_install() {
		return is_null( get_option( 'mail_mint_version', null ) );
	}


	/**
	 * Flushed cached version
	 *
	 * @since 1.0.0
	 */
	public function flush_versions() {
		$this->versions = null;
		wp_cache_delete( 'mail_mint_db_version' );
		wp_cache_delete( 'mail_mint_version' );
	}
}
