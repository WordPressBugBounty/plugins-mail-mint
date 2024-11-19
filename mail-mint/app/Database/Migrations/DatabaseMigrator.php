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
		'1.15.2' => array(
			'mm_update_1152_migrate_templates_table',
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
			$batch_size = 200;
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
				as_schedule_single_action( time() + 60, 'mail_mint_run_update_callback', array(
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
	 * Migrate templates table to a new table.
	 *
	 * This function is designed to migrate the templates table to a new table named `wp_mint_templates`.
	 * It retrieves a batch of templates and processes them to calculate the Last Modified Date, Created Date,
	 * Total Template Count, and Total Template Categories. It then inserts or updates the data into the new table.
	 *
	 * @param array $args An associative array containing the batch and offset for processing.
	 *
	 * @return void
	 *
	 * @since 1.15.2
	 */
	public function mm_update_1152_migrate_templates_table( $args = array() ) {
		$version = $args[ 'version' ];
		global $wpdb;

		// Define the table name.
		$table = $wpdb->prefix . 'mint_email_templates';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			$charset_collate = $wpdb->get_charset_collate();
			$create_table_query = "
				CREATE TABLE IF NOT EXISTS {$table} (
					`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
					`title` VARCHAR(250) NOT NULL,
					`thumbnail` longtext NULL,
					`thumbnail_data` longtext NULL,
					`html_content` longtext NULL,
					`json_content` longtext NULL,
					`editor_type` VARCHAR(50) NOT NULL,
					`email_type` VARCHAR(50) NOT NULL,
					`customizable` TINYINT(1) NOT NULL DEFAULT 0,
					`author_id` BIGINT UNSIGNED NOT NULL,
					`status` VARCHAR(50) NOT NULL DEFAULT 'draft',
					`newsletter_type` VARCHAR(50) NULL,
					`newsletter_id` BIGINT UNSIGNED NULL,
					`created_at` TIMESTAMP NULL,
					`updated_at` TIMESTAMP NULL,
					INDEX `template_id_index` (`id` ASC)
				) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $create_table_query );
		}

		// Define the SQL query to get the posts and meta data.
		$sql = "
			SELECT p.ID, p.post_title, p.post_date, p.post_modified, pm.meta_key, pm.meta_value
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'mint_email_template'
			ORDER BY p.ID
		";

		// Execute the query and get the results.
		$results = $wpdb->get_results($sql, ARRAY_A);

		// Initialize an array to hold the combined results.
		$combined_results = array();

		// Process the results to combine meta_key and meta_value.
		foreach ($results as $row) {
			$post_id = $row['ID'];

			// Initialize the post array if it doesn't exist.
			if (!isset($combined_results[$post_id])) {
				$combined_results[$post_id] = array(
					'title'           => $row['post_title'],
					'thumbnail'       => '',
					'thumbnail_data'  => '',
					'html_content'    => '',
					'json_content'    => '',
					'editor_type'     => '',
					'email_type'      => '',
					'customizable'    => 0,
					'author_id'       => get_post_field('post_author', $post_id),
					'status'          => 'draft',
					'newsletter_type' => '',
					'newsletter_id'   => null,
					'created_at'      => $row['post_date'],
					'updated_at'      => $row['post_modified'],
				);
			}

			// Add the meta_key and meta_value to the post array.
			switch ($row['meta_key']) {
				case 'mailmint_email_template_thumbnail':
					$combined_results[$post_id]['thumbnail'] = $row['meta_value'];
					break;
				case 'mailmint_email_template_html_content':
					$combined_results[$post_id]['html_content'] = $row['meta_value'];
					break;
				case 'mailmint_email_template_json_content':
					$combined_results[$post_id]['json_content'] = $row['meta_value'];
					break;
				case 'mailmint_email_editor_type':
					$combined_results[$post_id]['editor_type'] = $row['meta_value'];
					break;
				case 'mailmint_wc_email_type':
					$combined_results[$post_id]['email_type'] = $row['meta_value'];
					break;
				case 'mailmint_wc_customize_enable':
					$combined_results[$post_id]['customizable'] = $row['meta_value'] ? 1 : 0;
					break;
				case 'mailmint_email_template_thumbnail_data':
					$combined_results[$post_id]['thumbnail_data'] = $row['meta_value'];
					break;
				case 'mailmint_newsletter_type':
					$combined_results[$post_id]['newsletter_type'] = $row['meta_value'];
					break;
				case 'mailmint_newsletter_id':
					$combined_results[$post_id]['newsletter_id'] = $row['meta_value'];
					break;
			}
		}

		// Insert the combined results into the new table
		foreach ($combined_results as $data) {
			$wpdb->insert($table, $data);
		}

		update_option( 'mail_mint_db_version', $version, false );
		update_option( 'mail_mint_db_1152_version_updated', 'yes' );
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
