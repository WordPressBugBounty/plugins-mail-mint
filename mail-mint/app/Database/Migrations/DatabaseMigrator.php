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

use MailMint\App\Helper;
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
		'1.14.0' => array(
			'mm_update_1140_migrate_woocommerce_order_custom_table',
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
	 * @since 1.14.0 Added less than or equal to check for current DB version.
	 */
	public static function update() {
		$current_db_version = get_option( 'mail_mint_db_version' );
		$loop               = 0;

		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<=' ) ) {
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
	 * @since 1.14.0 Added less than or equal to check for current DB version.
	 */
	public static function needs_db_update() {
		$current_db_version = get_option( 'mail_mint_db_version', null );
		$updates            = self::get_db_update_callbacks();
		$update_versions    = array_keys( $updates );
		usort( $update_versions, 'version_compare' );
		return ! is_null( $current_db_version ) && version_compare( $current_db_version, end( $update_versions ), '<=' );
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
	 * Migrate WooCommerce order data to a custom table.
	 *
	 * This function is designed to migrate WooCommerce order data to a custom table named `wp_mint_wc_customers`.
	 * It retrieves a batch of WooCommerce orders and processes them to calculate the Last Order Date, First Order Date,
	 * Total Order Count, Total Order Value, Average Order Value, Purchased Products, Purchased Product Categories,
	 * Purchased Product Tags, and Used Coupons. It then inserts or updates the data into the custom table.
	 *
	 * @param array $args An associative array containing the batch and offset for processing.
	 *
	 * @return void
	 *
	 * @since 1.14.0
	 */
	public function mm_update_1140_migrate_woocommerce_order_custom_table( $args = array() ) {
		$version = $args[ 'version' ];

		// Array of columns to drop.
		$columns_to_drop = array(
			'email_subject',
			'email_preview_text',
			'email_body',
			'sender_email',
			'sender_name'
		);

		// Function to check if a column exists
		function column_exists( $table_name, $column_name ) {
			global $wpdb;
			$query = $wpdb->prepare(
				"SELECT COUNT(*) 
				 FROM information_schema.columns 
				 WHERE table_schema = %s 
				 AND table_name = %s 
				 AND column_name = %s",
				DB_NAME, $table_name, $column_name
			);
			return $wpdb->get_var( $query ) > 0;
		}

		global $wpdb;
		$scheduled_emails_table = $wpdb->prefix . EmailSchema::$table_name;

		// Iterate over each column and drop if exists.
		foreach ( $columns_to_drop as $column ) {
			if ( column_exists( $scheduled_emails_table, $column ) ) {
				$alter_query = "ALTER TABLE $scheduled_emails_table DROP COLUMN $column";
				$wpdb->query( $alter_query ); //phpcs:ignore
			}
		}

		// Check if WooCommerce is active.
		if ( MrmCommon::is_wc_active() ) {
			// Create custom WooCommerce orders table if it doesn't exist.
			$new_table_name = $wpdb->prefix . 'mint_wc_customers';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$new_table_name'" ) != $new_table_name ) {
				$charset_collate = $wpdb->get_charset_collate();
				$create_table_query = "
					CREATE TABLE $new_table_name (
						id int(12) unsigned AUTO_INCREMENT PRIMARY KEY,
						email_address varchar(255),
						l_order_date datetime,
						f_order_date datetime,
						total_order_count int(7),
						total_order_value double,
						aov double,
						purchased_products longtext NULL,
						purchased_products_cats longtext NULL,
						purchased_products_tags longtext NULL,
						used_coupons longtext NULL,
						INDEX (email_address),
						INDEX (l_order_date),
						INDEX (f_order_date),
						INDEX (total_order_count),
						INDEX (total_order_value) 
					) $charset_collate;";
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
				dbDelta( $create_table_query );
			}

			// Batch processing setup.
			$batch_size = 100;
			$offset = ! empty( $args['offset'] ) ? $args['offset'] : 0;
			$valid_statuses = array('wc-completed', 'wc-processing', 'wc-on-hold');
		
			// Retrieve a batch of WooCommerce orders.
			$orders = wc_get_orders( array(
				'limit'  => $batch_size,
				'offset' => $offset,
				'status' => $valid_statuses
			) );

			if ( ! empty( $orders ) ) {
				foreach ( $orders as $order ) {
					// Skip refund orders.
					if ( $order instanceof \WC_Order_Refund ) {
						continue;
					}
				
					$customer = Helper::getDbCustomerFromOrder($order);

					$email_address = $customer->email;
					$order_date = $order->get_date_created()->format('Y-m-d H:i:s');
					$total_value = $order->get_total();
					$items = $order->get_items();
				
					// Retrieve existing data for the email
					$existing_data = $wpdb->get_row(
						$wpdb->prepare("SELECT * FROM $new_table_name WHERE email_address = %s", $email_address),
						ARRAY_A
					);
				
					// Initialize or update customer data
					if ( $existing_data ) {
						// Update existing data
						$existing_data['l_order_date'] = max( $existing_data['l_order_date'], $order_date );
						$existing_data['f_order_date'] = min( $existing_data['f_order_date'], $order_date );
						
						// Only count parent orders for `total_order_count`
						if ( $order->get_parent_id() == 0 ) {
							$existing_data['total_order_count'] += 1;
						}
				
						// Always include the order value, whether it's parent or child
						$existing_data['total_order_value'] += $total_value;
				
						$existing_products = json_decode( $existing_data['purchased_products'], true );
						$existing_cats     = json_decode( $existing_data['purchased_products_cats'], true );
						$existing_tags     = json_decode( $existing_data['purchased_products_tags'], true );
						$existing_coupons  = json_decode( $existing_data['used_coupons'], true );
				
						foreach ( $items as $item ) {
							$product = $item->get_product();
							if ( $product ) {
								$existing_products[] = $product->get_id();
								$product_cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
								$product_tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );
								$existing_cats = array_merge( $existing_cats, $product_cats );
								$existing_tags = array_merge( $existing_tags, $product_tags );
							}
						}
				
						$existing_coupons = array_merge( $existing_coupons, $order->get_coupon_codes() );
				
						// Remove duplicates
						$existing_data['purchased_products']      = array_values(array_unique($existing_products));
						$existing_data['purchased_products_cats'] = array_values(array_unique($existing_cats));
						$existing_data['purchased_products_tags'] = array_values(array_unique($existing_tags));
						$existing_data['used_coupons']            = array_values(array_unique($existing_coupons));
				
						// Calculate AOV (Average Order Value)
						if ( $existing_data['total_order_count'] > 0 ) {
							$existing_data['aov'] = number_format((float) ($existing_data['total_order_value'] / $existing_data['total_order_count']), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
						} else {
							$existing_data['aov'] = number_format((float) (0), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
						}

						// Update the custom table
						if ($existing_data['total_order_count'] > 0) {
							$wpdb->update(
								$new_table_name,
								array(
									'l_order_date'            => $existing_data['l_order_date'],
									'f_order_date'            => $existing_data['f_order_date'],
									'total_order_count'       => $existing_data['total_order_count'],
									'total_order_value'       => $existing_data['total_order_value'],
									'aov'                     => $existing_data['aov'],
									'purchased_products'      => wp_json_encode( $existing_data['purchased_products'] ),
									'purchased_products_cats' => wp_json_encode( $existing_data['purchased_products_cats'] ),
									'purchased_products_tags' => wp_json_encode( $existing_data['purchased_products_tags'] ),
									'used_coupons'            => wp_json_encode( $existing_data['used_coupons'] ),
								),
								array( 'email_address' => $email_address )
							);
						}
					} else {
						// Initialize new data
						$purchased_products = array();
						$purchased_products_cats = array();
						$purchased_products_tags = array();
						foreach ( $items as $item ) {
							$product = $item->get_product();
							if ( $product ) {
								$purchased_products[] = $product->get_id();
								$product_cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
								$product_tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );
								$purchased_products_cats = array_merge( $purchased_products_cats, $product_cats );
								$purchased_products_tags = array_merge( $purchased_products_tags, $product_tags );
							}
						}
				
						// Collecting used coupons
						$used_coupons = $order->get_coupon_codes();
				
						// Calculate AOV (Average Order Value)
						$aov = ($order->get_parent_id() == 0) ? number_format((float) ($total_value), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator()) : number_format((float) (0), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());

						// Insert new data into the custom table
						if ($order->get_parent_id() == 0) {
							$wpdb->insert(
								$new_table_name,
								array(
									'email_address'           => $email_address,
									'l_order_date'            => $order_date,
									'f_order_date'            => $order_date,
									'total_order_count'       => ($order->get_parent_id() == 0) ? 1 : 0,  // Only count parent orders for total_order_count
									'total_order_value'       => $total_value,
									'aov'                     => $aov,
									'purchased_products'      => wp_json_encode( array_values( array_unique( $purchased_products ) ) ),
									'purchased_products_cats' => wp_json_encode( array_values( array_unique( $purchased_products_cats ) ) ),
									'purchased_products_tags' => wp_json_encode( array_values( array_unique( $purchased_products_tags ) ) ),
									'used_coupons'            => wp_json_encode( array_values( array_unique( $used_coupons ) ) ),
								)
							);
						}
					}
				}
				
		
				// Schedule the next batch if there are more orders to process
				$next_offset = $offset + $batch_size;
				as_schedule_single_action( time() + 120, 'mail_mint_run_update_callback', array(
					'update_callback' => 'mm_update_1140_migrate_woocommerce_order_custom_table',
					'args'            => array(
						'offset'  => $next_offset,
						'version' => $version,
					),
				), 'mail-mint-db-updates' );
			} else {
				// Update database to latest version if all orders are processed
				update_option( 'mail_mint_db_version', $version, false );
				update_option( 'mail_mint_db_1140_version_updated', 'yes' );
			}
		} else {
			// WooCommerce not active, skip WooCommerce related processing
			update_option( 'mail_mint_db_version', $version, false );
			update_option( 'mail_mint_db_1140_version_updated', 'yes' );
		}
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
