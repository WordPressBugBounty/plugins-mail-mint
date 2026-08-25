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
		'1.16.0' => array(
			'mm_update_1160_migrate_form_submissions_tables',
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
		add_action( 'mail_mint_sync_wc_customers', array( $this, 'sync_wc_customers_batch' ), 10, 1 );
		add_action( 'mailmint_dedupe_broadcast_email_meta', array( $this, 'mm_update_1163_dedupe_broadcast_email_meta' ) );
		add_action( 'init', array( $this, 'maybe_schedule_broadcast_email_meta_dedupe' ), 20 );
		add_action( self::CART_INDEX_HOOK, array( $this, 'build_abandoned_cart_indexes' ) );
		add_action( 'init', array( $this, 'maybe_schedule_abandoned_cart_indexes' ), 20 );
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
			'1.16.0' => array(
				'mm_update_1160_migrate_form_submissions_tables',
			),
			'1.16.1' => array(
				'add_unique_key_to_automation_log',
			),
			'1.16.2' => array(
				'add_unique_key_to_contact_meta',
			),
			'1.17.0' => array(
				'mm_update_1170_create_ai_tables',
				'add_position_to_custom_fields',
			),
			'1.16.4' => array(
				'maybe_create_unsubscribe_survey_page',
			),
			'1.18.0' => array(
				'mm_update_1180_create_abandoned_cart_tables',
			),
		);

		$this->current_db_version = get_option( 'mail_mint_db_version', null );
		if ( ! is_null( $this->current_db_version ) ) {
			$this->upgrade_database_tables();
			$this->maybe_create_email_templates_table();
			$this->maybe_create_abandoned_cart_tables();
			// Must follow the table check above — it has nothing to alter until the
			// tables exist.
			$this->maybe_upgrade_abandoned_cart_columns();
		}
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
	 * Ensure the mint_email_templates table exists for existing installs.
	 *
	 * The table is created on fresh installs via Upgrade::upgrade_schema(), but for
	 * users who upgraded past 1.15.2 without triggering the async migration (e.g.
	 * the admin notice was suppressed for MRM >= 1.17.7), the table may be missing.
	 * This check runs on every init and is a no-op once the table exists.
	 *
	 * @return void
	 * @since 1.15.2
	 */
	private function maybe_create_email_templates_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'mint_email_templates';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$schema = new \Mint\MRM\DataBase\Tables\EmailTemplatesSchema();
			$schema->get_sql();
		}
	}


	/**
	 * Ensure the abandoned cart tables exist.
	 *
	 * upgrade_database_tables() bumps the stored DB version whether or not its callbacks
	 * succeeded, so a single failed dbDelta would otherwise leave a site permanently
	 * without the cart tables while the tracking hooks keep firing. This guard is a no-op
	 * once both tables exist, short-circuited by an option so the common path costs nothing.
	 *
	 * Also covers sites that had Pro (which used to own these tables), removed it, and
	 * never ran a Free migration that created them.
	 *
	 * @return void
	 * @since 1.31.0
	 */
	private function maybe_create_abandoned_cart_tables() {
		if ( 'yes' === get_option( '_mrm_abandoned_cart_tables_ready' ) ) {
			return;
		}

		global $wpdb;

		$carts = $wpdb->prefix . \Mint\MRM\DataBase\Tables\AbandonedCartSchema::$table_name;
		$meta  = $wpdb->prefix . \Mint\MRM\DataBase\Tables\AbandonedCartSchema::$meta_table_name;

		$carts_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $carts ) ) === $carts;
		$meta_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $meta ) ) === $meta;

		if ( $carts_exists && $meta_exists ) {
			update_option( '_mrm_abandoned_cart_tables_ready', 'yes', false );
			return;
		}

		( new \Mint\MRM\DataBase\Tables\AbandonedCartSchema() )->get_sql();

		$carts_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $carts ) ) === $carts;
		$meta_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $meta ) ) === $meta;

		if ( $carts_exists && $meta_exists ) {
			update_option( '_mrm_abandoned_cart_tables_ready', 'yes', false );
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

	/**
	 * Add a UNIQUE KEY on (email, step_id, identifier) to the automation log table.
	 *
	 * Guards against duplicate rows created by the previous check-then-insert race
	 * condition in update_log(). The ALTER is skipped if the key already exists so
	 * the migration is safe to run multiple times.
	 *
	 * @return void
	 * @since 1.16.1
	 */
	private function add_unique_key_to_automation_log() {
		global $wpdb;
		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\AutomationLogSchema::$table_name;

		$key_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM information_schema.statistics
				 WHERE table_schema = %s
				   AND table_name   = %s
				   AND index_name   = 'unique_email_step_identifier'",
				DB_NAME,
				$table
			)
		);

		if ( ! $key_exists ) {
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY `unique_email_step_identifier` (`email`, `step_id`, `identifier`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Add a UNIQUE KEY on (contact_id, meta_key) to the contact meta table.
	 *
	 * Required so that INSERT ... ON DUPLICATE KEY UPDATE can be used in
	 * update_meta_fields() to collapse N·F queries down to N queries.
	 * The ALTER is skipped if the key already exists so the migration is
	 * safe to run multiple times.
	 *
	 * @return void
	 * @since 1.16.2
	 */
	/**
	 * Create the AI copilot conversation tables (v1.17.0).
	 *
	 * Uses the same schema classes as fresh installs, so dbDelta stays the
	 * single source of truth for the table structure.
	 *
	 * @return void
	 * @since 1.17.0
	 */
	private function mm_update_1170_create_ai_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		dbDelta( ( new \Mint\MRM\DataBase\Tables\AIConversationSchema() )->get_sql() . $charset_collate );
		dbDelta( ( new \Mint\MRM\DataBase\Tables\AIMessageSchema() )->get_sql() . $charset_collate );
		update_option( '_mrm_ai_tables_ready', 'yes', false );
	}

	/**
	 * Create the abandoned cart tracking tables (v1.18.0).
	 *
	 * Cart tracking moved from Mail Mint Pro into Free in 1.31.0. Existing installs never
	 * run Upgrade::upgrade_schema(), so this is the only path that reaches them. The schema
	 * uses CREATE TABLE IF NOT EXISTS, making this a clean no-op on sites where Pro already
	 * created the tables — their rows are left untouched.
	 *
	 * @return void
	 * @since 1.31.0
	 */
	private function mm_update_1180_create_abandoned_cart_tables() {
		( new \Mint\MRM\DataBase\Tables\AbandonedCartSchema() )->get_sql();
	}

	private function add_unique_key_to_contact_meta() {
		global $wpdb;
		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\ContactMetaSchema::$table_name;

		$key_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM information_schema.statistics
				 WHERE table_schema = %s
				   AND table_name   = %s
				   AND index_name   = 'unique_contact_meta'",
				DB_NAME,
				$table
			)
		);

		if ( ! $key_exists ) {
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY `unique_contact_meta` (`contact_id`, `meta_key`(191))" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Add the position column to the custom fields table.
	 *
	 * Backs application-side field ordering.
	 *
	 * @return void
	 * @since 1.17.0
	 */
	private function add_position_to_custom_fields() {
		global $wpdb;
		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\CustomFieldSchema::$table_name;

		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM information_schema.columns
				 WHERE table_schema = %s
				   AND table_name   = %s
				   AND column_name  = 'position'",
				DB_NAME,
				$table
			)
		);

		if ( ! $column_exists ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN `position` INT UNSIGNED NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Create the unsubscribe survey page for existing installs (upgrade migration).
	 *
	 * wp_insert_post() computes the post GUID via get_permalink(), which dereferences
	 * the global $wp_rewrite. This migration can run synchronously at plugin-include
	 * time (App::init() is invoked directly from the plugin bootstrap), which is before
	 * WordPress instantiates $wp_rewrite in wp-settings.php. Calling wp_insert_post()
	 * then fatals with "Call to a member function get_page_permastruct() on null".
	 *
	 * So when the rewrite subsystem is not ready yet, defer the actual creation to the
	 * 'init' hook, where $wp_rewrite is guaranteed to exist. The DB version is still
	 * bumped by the surrounding migration loop, and the page is created later in the
	 * same request.
	 *
	 * Safe to run multiple times — guarded by get_page_by_path() check.
	 *
	 * @return void
	 * @since 1.16.4
	 */
	private function maybe_create_unsubscribe_survey_page() {
		if ( empty( $GLOBALS['wp_rewrite'] ) ) {
			add_action( 'init', array( $this, 'create_unsubscribe_survey_page' ), 20 );
			return;
		}

		$this->create_unsubscribe_survey_page();
	}

	/**
	 * Insert the unsubscribe survey page.
	 *
	 * Safe to run multiple times — guarded by get_page_by_path() check. Public so it
	 * can be deferred to the 'init' hook by maybe_create_unsubscribe_survey_page() when
	 * the rewrite subsystem is not yet available during early bootstrap.
	 *
	 * @return void
	 * @since 1.16.4
	 */
	public function create_unsubscribe_survey_page() {
		if ( get_page_by_path( 'unsubscribe_survey', OBJECT, 'page' ) ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_name'    => 'unsubscribe_survey',
				'post_title'   => _x( 'Mint Mail Unsubscribe Survey', 'Page title', 'mrm' ),
				'post_content' => '<!-- wp:shortcode -->[unsubscribe_survey]<!-- /wp:shortcode -->',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_wp_page_template', 'template-unsubscribe-page.php' );
		}
	}

	/**
	 * Helper-index name used while de-duplicating the broadcast email meta table.
	 *
	 * @var string
	 */
	const EMAIL_META_DEDUPE_INDEX = 'tmp_email_meta_dedupe';

	/**
	 * Action Scheduler hook that processes one de-dupe batch of the email meta table.
	 *
	 * @var string
	 */
	const EMAIL_META_DEDUPE_HOOK = 'mailmint_dedupe_broadcast_email_meta';

	/**
	 * Check whether a named index exists on a table.
	 *
	 * @param string $table      Fully-prefixed table name.
	 * @param string $index_name Index name to look for.
	 * @return bool
	 * @since 1.16.3
	 */
	private function index_exists( $table, $index_name ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM information_schema.statistics
				 WHERE table_schema = %s
				   AND table_name   = %s
				   AND index_name   = %s",
				DB_NAME,
				$table,
				$index_name
			)
		);
	}

	/**
	 * Option flag set once the abandoned carts table has the recovery columns.
	 *
	 * @var string
	 */
	const CART_COLUMNS_DONE = 'mailmint_abandoned_cart_columns_ready';

	/**
	 * How many times the synchronous column upgrade may be attempted, and where the count
	 * is kept.
	 *
	 * The upgrade runs on every request until it succeeds, which is the right shape for a
	 * transient failure and the wrong one for a permanent failure. A site whose DB user
	 * lacks ALTER, or whose carts table is on a read-only replica, would otherwise re-run
	 * an information_schema scan plus up to four failing ALTER statements on every
	 * front-end page load, forever. A budget turns that into a bounded cost, and the
	 * deferred index job keeps a slow-path retry alive afterwards so a site that fixes its
	 * grants still converges without a manual nudge.
	 *
	 * @var string
	 */
	const CART_COLUMNS_ATTEMPTS = 'mailmint_abandoned_cart_columns_attempts';

	/**
	 * Synchronous attempts allowed before the work is left to the deferred job.
	 *
	 * @var int
	 */
	const CART_COLUMNS_MAX_ATTEMPTS = 5;

	/**
	 * Bring the abandoned carts table's columns up to date.
	 *
	 * Deliberately NOT a version-gated migration callback, for two independent reasons.
	 *
	 * AbandonedCartSchema uses CREATE TABLE IF NOT EXISTS, so dbDelta never alters a table
	 * that already exists — and every site that had Mail Mint Pro up to 1.30.1 already has
	 * these tables in their older shape. Those sites can arrive at any stored DB version,
	 * so no single version gate reaches all of them.
	 *
	 * The abandoned cart feature also bumped MRM_DB_VERSION to 1.18.0 before release, so
	 * machines that ran the feature branch already have 1.18.0 recorded and would skip a
	 * callback registered under it. Self-healing covers them without inventing a version
	 * number for work that ships in the same release.
	 *
	 * Columns only, and synchronously: they are instant DDL, and CartModel::has_column()
	 * drops recovery_source from writes until they exist, so deferring them would silently
	 * lose attribution on the first orders after an upgrade. Index creation is the part
	 * that can be slow, and that stays in the deferred job — which also means the widening
	 * here always runs first, so an index is never built against the narrow column and
	 * then rebuilt.
	 *
	 * Synchronous, but not unconditionally so: see CART_COLUMNS_ATTEMPTS for why the
	 * per-request retry is capped and where the work goes once the cap is reached.
	 *
	 * @return void
	 * @since 1.31.1
	 */
	private function maybe_upgrade_abandoned_cart_columns() {
		if ( 'yes' === get_option( self::CART_COLUMNS_DONE ) ) {
			return;
		}

		if ( (int) get_option( self::CART_COLUMNS_ATTEMPTS, 0 ) >= self::CART_COLUMNS_MAX_ATTEMPTS ) {
			return;
		}

		$this->upgrade_abandoned_cart_columns();
	}

	/**
	 * Add the recovery columns and widen the narrow ones, recording the attempt.
	 *
	 * Split out from the per-request guard so the deferred index job can retry the work on
	 * its own schedule after the synchronous budget is spent — a background retry costs a
	 * store nothing, where a per-request one costs it every page view. Called directly, it
	 * ignores that budget; the budget belongs to the caller that runs per request.
	 *
	 * The attempt is counted before the DDL runs, not after, so an ALTER that takes the
	 * whole request down with it still consumes budget rather than looping.
	 *
	 * @return bool True once the columns are present.
	 * @since 1.31.1
	 */
	private function upgrade_abandoned_cart_columns() {
		global $wpdb;

		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\AbandonedCartSchema::$table_name;

		// Nothing to upgrade on a site that never had cart tracking. Leave the flag unset,
		// and spend no budget, so the check runs again once the tables appear.
		if ( ! $this->table_exists( $table ) ) {
			return false;
		}

		update_option( self::CART_COLUMNS_ATTEMPTS, (int) get_option( self::CART_COLUMNS_ATTEMPTS, 0 ) + 1, false );

		$columns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT column_name, character_maximum_length
				 FROM information_schema.columns
				 WHERE table_schema = %s
				   AND table_name   = %s",
				DB_NAME,
				$table
			),
			ARRAY_A
		);

		$existing = array();
		foreach ( (array) $columns as $column ) {
			$name              = isset( $column['column_name'] ) ? $column['column_name'] : ( isset( $column['COLUMN_NAME'] ) ? $column['COLUMN_NAME'] : '' );
			$length            = isset( $column['character_maximum_length'] ) ? $column['character_maximum_length'] : ( isset( $column['CHARACTER_MAXIMUM_LENGTH'] ) ? $column['CHARACTER_MAXIMUM_LENGTH'] : null );
			$existing[ $name ] = $length;
		}

		// How a cart came to be recovered: link, organic, fast_purchase or manual.
		// Without it, a recovery driven by the email and a coincidental purchase are
		// indistinguishable and the recovery rate cannot be defended to a store owner.
		if ( ! array_key_exists( 'recovery_source', $existing ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN `recovery_source` VARCHAR(20) NULL DEFAULT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Attribution without a timestamp is half a feature: reporting needs to know when
		// the recovery happened, not just that it did.
		if ( ! array_key_exists( 'recovered_at', $existing ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN `recovered_at` TIMESTAMP NULL DEFAULT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// token and session_key were sized to exactly the values they hold, leaving no
		// headroom — and MySQL truncates rather than erroring outside strict mode. Staying
		// inside 0-255 keeps the one-byte length prefix, so this is an INSTANT operation.
		if ( array_key_exists( 'token', $existing ) && (int) $existing['token'] < 64 ) {
			$wpdb->query( "ALTER TABLE {$table} MODIFY `token` VARCHAR(64)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( array_key_exists( 'session_key', $existing ) && (int) $existing['session_key'] < 64 ) {
			$wpdb->query( "ALTER TABLE {$table} MODIFY `session_key` VARCHAR(64)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Confirm rather than assume: an ALTER can fail on a locked or corrupt table, and
		// flagging done regardless would leave the column permanently missing.
		$has_source = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM information_schema.columns
				 WHERE table_schema = %s
				   AND table_name   = %s
				   AND column_name  = 'recovery_source'",
				DB_NAME,
				$table
			)
		);

		if ( ! $has_source ) {
			return false;
		}

		update_option( self::CART_COLUMNS_DONE, 'yes', false );
		delete_option( self::CART_COLUMNS_ATTEMPTS );

		return true;
	}

	/**
	 * Hook and option names for the deferred abandoned cart index build.
	 *
	 * @var string
	 */
	const CART_INDEX_HOOK = 'mailmint_build_abandoned_cart_indexes';
	const CART_INDEX_DONE = 'mailmint_abandoned_cart_indexed';

	/**
	 * Attempt budget for the deferred index build.
	 *
	 * The job used to flag itself done whether or not the ALTERs landed, which traded a
	 * runaway queue for an index that silently never existed. It now retries, so it needs
	 * its own ceiling: without one, an ALTER that kills the request every time would have
	 * the scheduler enqueue a fresh action roughly once a minute forever. Bounded retries
	 * give a genuinely slow table room to finish while keeping the queue finite.
	 *
	 * @var string
	 */
	const CART_INDEX_ATTEMPTS = 'mailmint_abandoned_cart_index_attempts';

	/**
	 * Deferred index-build attempts allowed before the job stops being re-enqueued.
	 *
	 * @var int
	 */
	const CART_INDEX_MAX_ATTEMPTS = 5;

	/**
	 * Enqueue the background index build for the abandoned carts table when needed.
	 *
	 * Same shape and rationale as maybe_schedule_broadcast_email_meta_dedupe(): hooked on
	 * `init` after Action Scheduler is up rather than the synchronous upgrade path, and
	 * self-healing rather than one-shot — upgrade_database_tables() bumps the stored
	 * version whether or not its callbacks succeeded, so a one-shot enqueue tied to the
	 * bump can be lost if the upgrade request dies. Re-checks each request until the
	 * indexes exist, then costs nothing behind an autoloaded option flag.
	 *
	 * @return void
	 * @since 1.31.1
	 */
	public function maybe_schedule_abandoned_cart_indexes() {
		if ( get_option( self::CART_INDEX_DONE ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\AbandonedCartSchema::$table_name;

		// No cart tables on this site — nothing to index, and nothing to keep checking.
		if ( ! $this->table_exists( $table ) ) {
			update_option( self::CART_INDEX_DONE, 'yes' );
			return;
		}

		if ( $this->index_exists( $table, 'idx_ac_session_status' ) ) {
			update_option( self::CART_INDEX_DONE, 'yes' );
			return;
		}

		// Retries are bounded, so a build that can never succeed cannot keep enqueueing.
		if ( (int) get_option( self::CART_INDEX_ATTEMPTS, 0 ) >= self::CART_INDEX_MAX_ATTEMPTS ) {
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' )
			&& false === as_next_scheduled_action( self::CART_INDEX_HOOK ) ) {
			as_schedule_single_action( time() + 60, self::CART_INDEX_HOOK, array(), 'mail-mint-db-updates' );
		}
	}

	/**
	 * Build the abandoned cart lookup indexes.
	 *
	 * Every hot query in CartModel filters on a key plus a status —
	 * get_cart_details_by_key() and get_cart_details_by_key_and_status() are called on
	 * every add-to-cart, coupon change and order transition — while the shipped schema
	 * indexes `email` alone. These are the composites those queries actually want.
	 *
	 * All non-unique, deliberately. A UNIQUE key on session_key would be wrong rather
	 * than merely strict: WC_Session_Handler keeps the session customer ID equal to the
	 * WordPress user ID for logged-in shoppers, so uniqueness would permit each
	 * registered customer exactly one cart row for the life of the store and silently
	 * fail every insert after the first. The real invariant is "at most one *open* row
	 * per session", which MySQL cannot express and CartModel::insert_or_update() already
	 * enforces in application code. token stays non-unique too, since rows created by
	 * older Pro versions can carry an empty string and MySQL treats repeated '' as
	 * duplicate under UNIQUE.
	 *
	 * @return void
	 * @since 1.31.1
	 */
	public function build_abandoned_cart_indexes() {
		global $wpdb;

		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\AbandonedCartSchema::$table_name;

		if ( ! $this->table_exists( $table ) ) {
			update_option( self::CART_INDEX_DONE, 'yes' );
			return;
		}

		update_option( self::CART_INDEX_ATTEMPTS, (int) get_option( self::CART_INDEX_ATTEMPTS, 0 ) + 1, false );

		/*
		 * Background retry for the column work. The synchronous path in
		 * maybe_upgrade_abandoned_cart_columns() gives up after a few requests so it cannot
		 * tax every page load; this is where a site that has since been granted ALTER gets
		 * to converge. It also preserves the ordering the prefixes below depend on.
		 */
		if ( 'yes' !== get_option( self::CART_COLUMNS_DONE ) ) {
			$this->upgrade_abandoned_cart_columns();
		}

		$lengths = $this->cart_column_lengths( $table );

		/*
		 * Prefix lengths are clamped to what the column can actually offer rather than
		 * hardcoded. Pro shipped `token` and `session_key` as VARCHAR(32); asking for a
		 * 64-byte prefix of a 32-byte column is MySQL error 1089, which would take the
		 * whole ALTER down. The widening above normally runs first and makes the point
		 * moot, but "normally" is not a guarantee to build an index on — and a narrower
		 * prefix still indexes correctly, where a failed statement indexes nothing.
		 */
		$indexes = array(
			'idx_ac_session_status' => array(
				array( 'session_key', 64 ),
				array( 'status', 20 ),
			),
			'idx_ac_email_status'   => array(
				// 150 rather than the 191 that WordPress uses for single-column keys: the
				// 767-byte InnoDB limit that 191 is derived from applies to the whole
				// composite, and 191 + 20 characters of utf8mb4 is 844 bytes. Pre-5.7
				// installs — COMPACT row format, no innodb_large_prefix — reject that
				// outright. 150 characters of an email address is far past the point where
				// any real address stops being distinguishable.
				array( 'email', 150 ),
				array( 'status', 20 ),
			),
			'idx_ac_status_created' => array(
				array( 'status', 20 ),
				array( 'created_at', 0 ),
			),
			'idx_ac_token'          => array(
				array( 'token', 64 ),
			),
		);

		$built = 0;

		foreach ( $indexes as $name => $parts ) {
			if ( $this->index_exists( $table, $name ) ) {
				++$built;
				continue;
			}

			$columns = array();
			foreach ( $parts as $part ) {
				list( $column, $prefix ) = $part;

				// A column the table does not have cannot be indexed, and guessing past it
				// would build a differently-shaped index under the expected name.
				if ( ! array_key_exists( $column, $lengths ) ) {
					$columns = array();
					break;
				}

				$available = (int) $lengths[ $column ];
				$prefix    = $available > 0 ? min( (int) $prefix, $available ) : 0;
				$columns[] = $prefix > 0 ? "`{$column}`({$prefix})" : "`{$column}`";
			}

			if ( empty( $columns ) ) {
				continue;
			}

			$definition = '(' . implode( ', ', $columns ) . ')';
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX `{$name}` {$definition}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( $this->index_exists( $table, $name ) ) {
				++$built;
			}
		}

		// Only flag done once every index is really there. Flagging unconditionally is what
		// let a failed ALTER leave the hot cart queries on a table scan with nothing to
		// signal it; the attempt budget, not an optimistic flag, is what bounds the retries.
		if ( count( $indexes ) === $built ) {
			update_option( self::CART_INDEX_DONE, 'yes' );
			delete_option( self::CART_INDEX_ATTEMPTS );
		}
	}

	/**
	 * Character lengths of the abandoned carts table's columns, keyed by column name.
	 *
	 * Non-string columns report null in information_schema and are normalised to 0, which
	 * reads naturally as "no prefix is applicable here".
	 *
	 * @param string $table Fully-prefixed table name.
	 * @return array<string,int>
	 * @since 1.31.1
	 */
	private function cart_column_lengths( $table ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT column_name, character_maximum_length
				 FROM information_schema.columns
				 WHERE table_schema = %s
				   AND table_name   = %s",
				DB_NAME,
				$table
			),
			ARRAY_A
		);

		$lengths = array();

		foreach ( (array) $rows as $row ) {
			// MySQL 8 lower-cases these labels, MySQL 5.7 upper-cases them.
			$name = isset( $row['column_name'] ) ? $row['column_name'] : ( isset( $row['COLUMN_NAME'] ) ? $row['COLUMN_NAME'] : '' );

			if ( '' === $name ) {
				continue;
			}

			$length = isset( $row['character_maximum_length'] ) ? $row['character_maximum_length'] : ( isset( $row['CHARACTER_MAXIMUM_LENGTH'] ) ? $row['CHARACTER_MAXIMUM_LENGTH'] : null );

			$lengths[ $name ] = null === $length ? 0 : (int) $length;
		}

		return $lengths;
	}

	/**
	 * Whether a table exists in the current database.
	 *
	 * @param string $table Fully-prefixed table name.
	 * @return bool
	 * @since 1.31.1
	 */
	private function table_exists( $table ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Option flag set once the broadcast email meta table is de-duplicated and keyed.
	 *
	 * Autoloaded so the per-request check in maybe_schedule_broadcast_email_meta_dedupe()
	 * costs nothing once migration is complete.
	 *
	 * @var string
	 */
	const EMAIL_META_DEDUPE_DONE = 'mailmint_email_meta_deduped';

	/**
	 * Enqueue the background de-duplication of the broadcast email meta table when needed.
	 *
	 * Hooked on `init` (priority 20, after Action Scheduler is initialised) rather than the
	 * synchronous upgrade_database_tables() path, which runs at plugin-include time before
	 * AS is ready. Self-healing and idempotent: a one-shot enqueue tied to the version bump
	 * could be missed if the upgrade request dies, so this re-checks every request until the
	 * unique key exists, guarded by an autoloaded option flag so it is free afterwards. The
	 * heavy dedupe + ALTER work happens in mm_update_1163_dedupe_broadcast_email_meta(), so a
	 * large meta table (100k–200k+ rows) can never block a page load or hit the PHP
	 * execution-time limit on upgrade.
	 *
	 * @return void
	 * @since 1.16.3
	 */
	public function maybe_schedule_broadcast_email_meta_dedupe() {
		if ( get_option( self::EMAIL_META_DEDUPE_DONE ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\EmailMetaSchema::$table_name;

		// Already migrated (fresh installs get the key from the schema) — record and stop checking.
		if ( $this->index_exists( $table, 'unique_email_meta' ) ) {
			update_option( self::EMAIL_META_DEDUPE_DONE, 'yes' );
			return;
		}

		// Enqueue the first dedupe batch; the handler reschedules itself until done.
		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' )
			&& false === as_next_scheduled_action( self::EMAIL_META_DEDUPE_HOOK ) ) {
			as_schedule_single_action( time() + 60, self::EMAIL_META_DEDUPE_HOOK, array(), 'mail-mint-db-updates' );
		}
	}

	/**
	 * Process one batch of the broadcast email meta de-duplication, then add the unique key.
	 *
	 * Background Action Scheduler handler for self::EMAIL_META_DEDUPE_HOOK. Each run:
	 *  1. Returns early if the unique key already exists (idempotent / safe to re-run).
	 *  2. Adds a temporary helper index on (mint_email_id, meta_key) so the duplicate
	 *     lookup is indexed instead of an O(n^2) self-join.
	 *  3. Deletes up to BATCH duplicate rows (every row except the lowest id per
	 *     (mint_email_id, meta_key)) and reschedules itself while duplicates remain.
	 *  4. Once clean, adds UNIQUE KEY `unique_email_meta` and drops the helper index. If
	 *     a duplicate slipped in between the empty check and the ALTER (tracking writes
	 *     continue during migration), the ALTER fails and another batch is rescheduled.
	 *
	 * @return void
	 * @since 1.16.3
	 */
	public function mm_update_1163_dedupe_broadcast_email_meta() {
		global $wpdb;
		$table = $wpdb->prefix . \Mint\MRM\DataBase\Tables\EmailMetaSchema::$table_name;
		$batch = 5000;

		// Idempotent: unique key already present means the migration is complete.
		if ( $this->index_exists( $table, 'unique_email_meta' ) ) {
			if ( $this->index_exists( $table, self::EMAIL_META_DEDUPE_INDEX ) ) {
				$wpdb->query( "ALTER TABLE {$table} DROP INDEX `" . self::EMAIL_META_DEDUPE_INDEX . "`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			update_option( self::EMAIL_META_DEDUPE_DONE, 'yes' );
			return;
		}

		// Helper index so the duplicate lookup is indexed (avoids an O(n^2) self-join).
		if ( ! $this->index_exists( $table, self::EMAIL_META_DEDUPE_INDEX ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX `" . self::EMAIL_META_DEDUPE_INDEX . "` (`mint_email_id`, `meta_key`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Collect a batch of duplicate row ids (all but the lowest id per (mint_email_id, meta_key)).
		$duplicate_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT t1.id FROM {$table} t1
				 INNER JOIN {$table} t2
				   ON t1.mint_email_id = t2.mint_email_id
				  AND t1.meta_key = t2.meta_key
				  AND t1.id > t2.id
				 LIMIT %d",
				$batch
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $duplicate_ids ) ) {
			$ids_in = implode( ',', array_map( 'absint', $duplicate_ids ) );
			$wpdb->query( "DELETE FROM {$table} WHERE id IN ({$ids_in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// More may remain — reschedule the next batch.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + 30, self::EMAIL_META_DEDUPE_HOOK, array(), 'mail-mint-db-updates' );
			}
			return;
		}

		// No duplicates left — enforce uniqueness.
		$added = $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY `unique_email_meta` (`mint_email_id`, `meta_key`)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( false === $added ) {
			// A duplicate likely slipped in via a tracking write during the gap — try again.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + 30, self::EMAIL_META_DEDUPE_HOOK, array(), 'mail-mint-db-updates' );
			}
			return;
		}

		// Unique key now supersedes the helper index — drop it and mark the migration done.
		if ( $this->index_exists( $table, self::EMAIL_META_DEDUPE_INDEX ) ) {
			$wpdb->query( "ALTER TABLE {$table} DROP INDEX `" . self::EMAIL_META_DEDUPE_INDEX . "`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		update_option( self::EMAIL_META_DEDUPE_DONE, 'yes' );
	}

	/**
	 * Create form submissions and form entry details tables.
	 *
	 * Runs via the Action Scheduler for existing users upgrading to 1.16.0.
	 * Fresh installs get these tables through Upgrade::upgrade_schema() instead.
	 *
	 * @param array $args An associative array containing the version for processing.
	 *
	 * @return void
	 *
	 * @since 1.16.0
	 */
	public function mm_update_1160_migrate_form_submissions_tables( $args = array() ) {
		$version = ! empty( $args['version'] ) ? $args['version'] : '1.16.0';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		$submissions_table = $wpdb->prefix . 'mint_form_submissions';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$submissions_table'" ) !== $submissions_table ) {
			$sql[] = "CREATE TABLE {$submissions_table} (
				id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				form_id         BIGINT UNSIGNED NOT NULL,
				user_id         BIGINT UNSIGNED DEFAULT NULL,
				contact_id      BIGINT UNSIGNED DEFAULT NULL,
				source_url      TEXT DEFAULT NULL,
				status          VARCHAR(45) DEFAULT 'unread',
				browser         VARCHAR(45) DEFAULT NULL,
				device          VARCHAR(45) DEFAULT NULL,
				ip              VARCHAR(45) DEFAULT NULL,
				city            VARCHAR(100) DEFAULT NULL,
				country         VARCHAR(100) DEFAULT NULL,
				utm_source      VARCHAR(191) DEFAULT NULL,
				utm_medium      VARCHAR(191) DEFAULT NULL,
				utm_campaign    VARCHAR(191) DEFAULT NULL,
				utm_term        VARCHAR(191) DEFAULT NULL,
				utm_content     VARCHAR(191) DEFAULT NULL,
				created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
				updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				INDEX idx_form_id    (form_id),
				INDEX idx_user_id    (user_id),
				INDEX idx_contact_id (contact_id),
				INDEX idx_status     (status),
				INDEX idx_created_at (created_at)
			) $charset_collate;";
		}

		$entry_details_table = $wpdb->prefix . 'mint_form_entry_details';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$entry_details_table'" ) !== $entry_details_table ) {
			$sql[] = "CREATE TABLE {$entry_details_table} (
				id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				submission_id   BIGINT UNSIGNED NOT NULL,
				field_name      VARCHAR(255) NOT NULL,
				field_type      VARCHAR(50) DEFAULT NULL,
				field_value     LONGTEXT DEFAULT NULL,
				PRIMARY KEY (id),
				INDEX idx_submission_id    (submission_id),
				INDEX idx_field_name       (field_name),
				INDEX idx_submission_field (submission_id, field_name)
			) $charset_collate;";
		}

		if ( ! empty( $sql ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			foreach ( $sql as $query ) {
				dbDelta( $query );
			}
		}

		update_option( 'mail_mint_db_version', $version, false );
	}

	/**
	 * Sync WooCommerce customer data into the mint_wc_customers table in batches.
	 *
	 * Triggered on demand via the REST API. Creates the table if missing, truncates
	 * on the first batch (offset 0), then processes orders in batches of 200 and
	 * schedules the next batch via Action Scheduler until all orders are processed.
	 *
	 * @param array $args Associative array with 'offset' key for batch pagination.
	 *
	 * @return void
	 *
	 * @since 1.14.0
	 */
	public function sync_wc_customers_batch( $args = array() ) {
		if ( ! MrmCommon::is_wc_active() ) {
			return;
		}

		global $wpdb;
		$new_table_name = $wpdb->prefix . 'mint_wc_customers';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$new_table_name'" ) != $new_table_name ) {
			$charset_collate        = $wpdb->get_charset_collate();
			$create_table_query     = "
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
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $create_table_query );
		}

		$batch_size     = 200;
		$offset         = ! empty( $args['offset'] ) ? (int) $args['offset'] : 0;
		$valid_statuses = array( 'wc-completed', 'wc-processing', 'wc-on-hold' );

		if ( 0 === $offset ) {
			$wpdb->query( "TRUNCATE TABLE $new_table_name" ); //phpcs:ignore
		}

		$orders = wc_get_orders(
			array(
				'limit'  => $batch_size,
				'offset' => $offset,
				'status' => $valid_statuses,
			)
		);

		if ( ! empty( $orders ) ) {
			foreach ( $orders as $order ) {
				if ( $order instanceof \WC_Order_Refund ) {
					continue;
				}

				$customer      = Helper::getDbCustomerFromOrder( $order );
				$email_address = $customer->email;
				$order_date    = $order->get_date_created()->format( 'Y-m-d H:i:s' );
				$total_value   = $order->get_total();
				$items         = $order->get_items();

				$existing_data = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM $new_table_name WHERE email_address = %s", $email_address ), //phpcs:ignore
					ARRAY_A
				);

				if ( $existing_data ) {
					$existing_data['l_order_date'] = max( $existing_data['l_order_date'], $order_date );
					$existing_data['f_order_date'] = min( $existing_data['f_order_date'], $order_date );

					if ( $order->get_parent_id() == 0 ) {
						$existing_data['total_order_count'] += 1;
					}

					$existing_data['total_order_value'] += $total_value;

					$existing_products = json_decode( $existing_data['purchased_products'], true );
					$existing_cats     = json_decode( $existing_data['purchased_products_cats'], true );
					$existing_tags     = json_decode( $existing_data['purchased_products_tags'], true );
					$existing_coupons  = json_decode( $existing_data['used_coupons'], true );

					foreach ( $items as $item ) {
						$product = $item->get_product();
						if ( $product ) {
							$existing_products[] = $product->get_id();
							$product_cats        = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
							$product_tags        = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );
							$existing_cats       = array_merge( $existing_cats, $product_cats );
							$existing_tags       = array_merge( $existing_tags, $product_tags );
						}
					}

					$existing_coupons = array_merge( $existing_coupons, $order->get_coupon_codes() );

					$existing_data['purchased_products']      = array_values( array_unique( $existing_products ) );
					$existing_data['purchased_products_cats'] = array_values( array_unique( $existing_cats ) );
					$existing_data['purchased_products_tags'] = array_values( array_unique( $existing_tags ) );
					$existing_data['used_coupons']            = array_values( array_unique( $existing_coupons ) );

					if ( $existing_data['total_order_count'] > 0 ) {
						$existing_data['aov'] = number_format( (float) ( $existing_data['total_order_value'] / $existing_data['total_order_count'] ), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
					} else {
						$existing_data['aov'] = number_format( 0.0, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
					}

					if ( $existing_data['total_order_count'] > 0 ) {
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
					$purchased_products      = array();
					$purchased_products_cats = array();
					$purchased_products_tags = array();

					foreach ( $items as $item ) {
						$product = $item->get_product();
						if ( $product ) {
							$purchased_products[]    = $product->get_id();
							$product_cats            = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
							$product_tags            = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );
							$purchased_products_cats = array_merge( $purchased_products_cats, $product_cats );
							$purchased_products_tags = array_merge( $purchased_products_tags, $product_tags );
						}
					}

					$used_coupons = $order->get_coupon_codes();
					$aov          = ( $order->get_parent_id() == 0 )
						? number_format( (float) $total_value, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() )
						: number_format( 0.0, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );

					if ( $order->get_parent_id() == 0 ) {
						$wpdb->insert(
							$new_table_name,
							array(
								'email_address'           => $email_address,
								'l_order_date'            => $order_date,
								'f_order_date'            => $order_date,
								'total_order_count'       => 1,
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

			// Only schedule the next batch if we got a full batch — a partial result means this was the last one.
			if ( count( $orders ) >= $batch_size ) {
				$next_offset = $offset + $batch_size;
				as_schedule_single_action(
					time() + 60,
					'mail_mint_sync_wc_customers',
					array( 'offset' => $next_offset ),
					'mail-mint-wc-sync'
				);
			} else {
				self::cleanup_sync_wc_customers_actions();
			}
		} else {
			self::cleanup_sync_wc_customers_actions();
		}
	}

	/**
	 * Delete all pending and completed Action Scheduler entries for the WC customer sync hook.
	 *
	 * @return void
	 * @since 1.14.0
	 */
	private static function cleanup_sync_wc_customers_actions() {
		$store    = \ActionScheduler::store();
		$statuses = array(
			\ActionScheduler_Store::STATUS_PENDING,
			\ActionScheduler_Store::STATUS_COMPLETE,
			\ActionScheduler_Store::STATUS_FAILED,
			\ActionScheduler_Store::STATUS_CANCELED,
		);

		foreach ( $statuses as $status ) {
			$action_ids = $store->query_actions(
				array(
					'hook'     => 'mail_mint_sync_wc_customers',
					'group'    => 'mail-mint-wc-sync',
					'status'   => $status,
					'per_page' => -1,
				)
			);
			foreach ( $action_ids as $action_id ) {
				$store->delete_action( $action_id );
			}
		}
	}

}
