<?php
/**
 * DatabaseMigrator class.
 *
 * @package Mint\MRM\DataBase\Migration
 * @namespace Mint\MRM\DataBase\Migration
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 */

namespace Mint\MRM\DataBase\Migration;

use Mint\MRM\DataBase\Tables\AutomationJobSchema;
use Mint\MRM\DataBase\Tables\CampaignEmailBuilderSchema;
use Mint\MRM\DataBase\Tables\EmailSchema;
use Mint\MRM\DataBase\Tables\FormSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * DatabaseMigrator class
 *
 * Manages database migrations.
 *
 * @package Mint\MRM\DataBase\Migration
 * @namespace Mint\MRM\DataBase\Migration
 *
 * @version 1.0.0
 */
class DatabaseMigrator {

	use Singleton;

	/**
	 * Existing database version
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private $current_db_version;

	/**
	 * New database version
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 */
	private $update_db_versions;


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
	 * Initialize class functionalities
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'mail_mint_run_update_callback', array( $this, 'run_update_callback' ), 10, 2 );
		add_action( 'init', array( $this, 'install_actions' ) );

		$this->update_db_versions = array(
			'1.0.2' => array(
				'add_editor_type_column_in_builder_table',
			),
			'1.0.3' => array(
				'update_form_group_id_field_structure',
			),
			'1.0.4' => array(
				'update_form_position_field_structure',
			),
			'1.0.5' => array(
				'update_form_position_field_structure',
				'update_form_group_id_field_structure',
			),
		);
	}


	/**
	 * Performs installation actions based on user input.
	 * Checks if the 'do_update_mm_database' parameter is present in the query string.
	 * If found, triggers the database update process using the 'update' method.
	 *
	 * @since 1.6.0
	 */
	public function install_actions() {
		if ( !empty( $_GET['do_update_mm_database'] ) ) { //phpcs:ignore
			self::update();
		}
	}


	/**
	 * Queue all the DB updates for processing
	 *
	 * @since 1.6.0
	 */
	public static function update() {
		$current_db_version = get_option( 'mail_mint_db_version' );
		$loop               = 0;

		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					if ( false === as_has_scheduled_action( 'mail_mint_run_update_callback' ) ) {
						as_schedule_single_action(
							time() + 60,
							'mail_mint_run_update_callback',
							array(
								'update_callback' => $update_callback,
								'args'            => array(
									'offset'  => 0,
									'version' => $version,
								),
							),
							'mail-mint-db-updates'
						);
					}

					$loop++;
				}
			}
		}
	}


	/**
	 * Retrieves an array of database update callbacks.
	 *
	 * @return array The array of database update callbacks.
	 * @since 1.6.0
	 */
	public static function get_db_update_callbacks() {
		return self::$db_updates;
	}



	/**
	 * Is DB updated needed?
	 *
	 * @return bool
	 * @since 1.6.0
	 */
	public static function needs_db_update() {
		$current_db_version = get_option( 'mail_mint_db_version', null );
		$updates            = self::get_db_update_callbacks();
		$update_versions    = array_keys( $updates );
		usort( $update_versions, 'version_compare' );
		return ! is_null( $current_db_version ) && version_compare( $current_db_version, end( $update_versions ), '<' );
	}


	/**
	 * Run a specified update callback method if it exists in the current class.
	 *
	 * This function checks if a given update callback method exists in the current class
	 * and, if found, invokes the method with the provided arguments.
	 *
	 * @param string $update_callback The name of the update callback method.
	 * @param mixed  $args            Arguments to be passed to the update callback method.
	 *
	 * @return void
	 * @since 1.6.0
	 */
	public function run_update_callback( $update_callback, $args ) {
		if ( method_exists( $this, $update_callback ) ) {
			$this->{$update_callback}( $args );
		}
	}


	/**
	 * MailMint function to alter the broadcast emails table.
	 *
	 * This function is designed to alter the `wp_mint_broadcast_emails` table by updating specific columns
	 * for a batch of records. It updates columns like `email_subject`, `email_preview_text`, `email_body`,
	 * `sender_email`, and `sender_name` to NULL in a batch-wise manner. After each batch, it schedules the next
	 * batch for processing until all records are processed. Once all records are updated, it deletes the specified
	 * columns from the table.
	 *
	 * @param array $args An associative array containing the batch and offset for processing.
	 *
	 * @return void
	 *
	 * @since 1.6.0
	 */
	public function mm_update_160_migrate_broadcast_table( $args = array() ) {
		$offset  = ! empty( $args[ 'offset' ] ) ? $args[ 'offset' ] : 0;
		$version = $args[ 'version' ];
		$limit   = 1000;

		global $wpdb;
		$scheduled_emails_table = $wpdb->prefix . EmailSchema::$table_name;
		$alter_query            = "ALTER TABLE $scheduled_emails_table 
							DROP COLUMN email_subject,
							DROP COLUMN email_preview_text,
							DROP COLUMN email_body,
							DROP COLUMN sender_email,
							DROP COLUMN sender_name
							";
		$wpdb->query($alter_query); //phpcs:ignore
		/**
		 * Update database to latest version
		 */
		update_option( 'mail_mint_db_version', $version, false );
	}


	/**
	 * Upgrade all required database
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function upgrade_database_tables() {
		$update_versions = $this->get_db_update_versions();
		foreach ( $update_versions as $version => $callbacks ) {
			if ( version_compare( $this->current_db_version, $version, '<' ) ) {
				foreach ( $callbacks as $callback ) {
					if ( method_exists( $this, $callback ) ) {
						$this->$callback();
					}
				}
			}
		}
		$this->update_db_version();
	}

	/**
	 * Get update database versions
	 *
	 * @return mixed|void
	 *
	 * @since 1.0.0
	 */
	public function get_db_update_versions() {
		return apply_filters( 'mailmint_update_db_versions', $this->update_db_versions );
	}

	/**
	 * Update database version
	 *
	 * @return void
	 */
	private function update_db_version() {
		update_option(
			'mail_mint_db_version',
			apply_filters( 'mail_mint_db_version', MRM_DB_VERSION ),
			false
		);
	}

	/**
	 * Upgrade broadcast_emails table
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function add_editor_type_column_in_builder_table() {
		global $wpdb;
		$email_builder_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		$query  = "ALTER TABLE {$email_builder_table} ";
		$query .= 'ADD `editor_type` VARCHAR(50) NOT NULL ';
		$query .= "DEFAULT 'advanced-builder' COMMENT 'advanced-builder, classic-editor' ";
		$query .= 'AFTER `email_id`';

		$wpdb->query( $query ); //phpcs:ignore
	}


	/**
	 * Update the structure of the form_group_id field to use LONGTEXT data type.
	 *
	 * @since 1.5.2
	 */
	private function update_form_group_id_field_structure() {
		global $wpdb;
		$form_builder_table = $wpdb->prefix . FormSchema::$table_name;

		// Modify the column data type.
		$query  = "ALTER TABLE {$form_builder_table} ";
		$query .= 'MODIFY group_ids LONGTEXT;';

		// Execute the SQL query.
        $wpdb->query( $query ); //phpcs:ignore
	}

	/**
	 * Update the structure of the form_position field to use LONGTEXT data type.
	 *
	 * @since 1.5.5
	 */
	private function update_form_position_field_structure() {
		global $wpdb;
		$form_builder_table = $wpdb->prefix . FormSchema::$table_name;

		// Modify the column data type.
		$query  = "ALTER TABLE {$form_builder_table} ";
		$query .= 'MODIFY form_position	 LONGTEXT;';

		// Execute the SQL query.
        $wpdb->query( $query ); //phpcs:ignore
	}

}
