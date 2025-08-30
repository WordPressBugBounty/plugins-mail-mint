<?php

/**
 * Helper class for Mail Mint import feature
 *
 * @package Mint\MRM\Utilites\Helper
 * @namespace Mint\MRM\Utilites\Helper
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\Utilites\Helper;

use EDD\Database\Queries\Customer;
use EDD_Customer;
use Exception;
use Mint\MRM\Constants;
use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;

/**
 * Import class
 */
class Import
{

	/**
	 * Creates a CSV file from the uploaded file and returns the import metadata.
	 *
	 * @param array  $file       The uploaded file information.
	 * @param string $delimiter  Optional. The delimiter to use for CSV parsing. Default is ','.
	 *
	 * @return array|string      The import metadata array on success, or an error message on failure.
	 * @since 1.0.0
	 */
	public static function create_csv_from_import($file, $delimiter = ',')
	{
		if (! is_array($file) || ! isset($file['name'], $file['tmp_name'])) {
			return array(
				'import_type' => 'csv',
				'delimiter'   => $delimiter,
			);
		}

		// CSV file import directory.
		if (! file_exists(MRM_IMPORT_DIR . '/')) {
			wp_mkdir_p(MRM_IMPORT_DIR);
		}

		$file_name = isset($file['name']) ? $file['name'] : '';
		$tmp_name  = isset($file['tmp_name']) ? $file['tmp_name'] : '';

		// Move the file to the directory.
		$new_file_name = md5(wp_rand() . time()) . '-' . $file_name;
		$new_file      = MRM_IMPORT_DIR . '/' . $new_file_name;
		$move_new_file = @move_uploaded_file($tmp_name, $new_file); //phpcs:ignore

		return array(
			'import_type'   => 'csv',
			'delimiter'     => $delimiter,
			'file'          => $new_file,
			'new_file_name' => $new_file_name,
		);
	}

	/**
	 * Validate the fields in a row array.
	 *
	 * This function checks if any of the fields in the row array is a valid email.
	 *
	 * @access public
	 *
	 * @param array $row_array The array of rows.
	 * @return bool True if a valid email is found, false otherwise.
	 * @since 1.5.1
	 */
	public static function field_validation($row_array)
	{
		$data = array_slice($row_array, 1);

		foreach ($data as $row) {
			foreach ($row as $index) {
				$index = trim($index);
				if (is_email($index)) {
					// Found a valid email, no need to continue searching.
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Create array by reading the CSV.
	 *
	 * @param string $file file.
	 * @param string $delimiter  The delimiter used in the CSV file.
	 *
	 * @return array
	 * @since 1.0.0
	 * @throws Exception    $e Throws an exception if the action could not be saved.
	 */
	public static function create_array_from_csv($file, $delimiter)
	{
		if (isset($file)) {
			$file = MRM_IMPORT_DIR . '/' . $file;
		}

		// if the file does not exist return error.
		if (! file_exists($file)) {
			throw new Exception(__('The File is not found on this server.', 'mrm'));
		}

		$file = fopen($file, 'r'); //  phpcs:ignore

		while (false !== ($data = fgetcsv($file, 0, $delimiter))) { //  phpcs:ignore
			$array[] = $data;
		}

		fclose($file); //  phpcs:ignore
		return $array;
	}

	/**
	 * Prepare mapping headers from uploaded CSV and custom fields
	 *
	 * @param string $csv_file CSV file path.
	 * @param string $delimiter CSV delimiter.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function prepare_mapping_options_from_csv($csv_file, $delimiter)
	{
		$handle = fopen($csv_file, 'r'); //phpcs:ignore

		/**
		 * Fetching CSV header
		 */
		$headers = false !== $handle ? fgetcsv($handle, 0, $delimiter) : false;

		if (! is_array($headers) && empty($headers)) {
			$headers = array();
		}

		if (isset($headers[0])) {
			$headers[0] = self::remove_utf8_bom($headers[0]);
		}

		// Get contact general fields.
		$contacts_attrs   = self::get_contact_general_fields();
		$contacts_attrs   = apply_filters('mint_contacts_attrs', $contacts_attrs);
		$contacts_attrs[] = array(
			'name' => 'Status',
			'slug' => 'status',
		);

		return array(
			'headers' => $headers,
			'fields'  => $contacts_attrs,
		);
	}

	/**
	 * Retrieves the contact general fields.
	 * Retrieves the contact general fields from the primary contact fields option and prepares them for display.
	 *
	 * @return array An array of contact general fields.
	 * @since 1.5.0
	 */
	public static function get_contact_general_fields()
	{
		$fields = get_option('mint_contact_primary_fields', Constants::$primary_contact_fields);
		$fields = array_merge(...array_values($fields));

		$contact_attrs = array_map(
			function ($field) {
				return array(
					'name' => $field['meta']['label'],
					'slug' => $field['slug'],
				);
			},
			$fields
		);

		$segments = array(
			array(
				'name' => 'Lists',
				'slug' => 'lists',
			),
			array(
				'name' => 'Tags',
				'slug' => 'tags',
			),
		);

		return array_merge($contact_attrs, $segments);
	}

	/**
	 * Remove UTF8_bom
	 *
	 * @param string $string String.
	 *
	 * @return string
	 */
	public static function remove_utf8_bom($string)
	{
		if ('efbbbf' === substr(bin2hex($string), 0, 6)) {
			$string = substr($string, 3);
		}

		return $string;
	}

	/**
	 * Import WC customers information from orders and metadata table
	 *
	 * @param int $offset The starting point of orders to retrieve customers.
	 * @param int $per_batch The number of customers to retrieve per batch.
	 *
	 * @return array
	 * @since 1.16.5 Added the $per_batch parameter.
	 */
	public static function get_wc_customers($offset, $per_batch)
	{
		$all_order_ids = wc_get_orders(
			array(
				'return'       => 'ids',
				'limit'        => 500,
				'offset'       => $offset,
				'type'         => 'shop_order',
				'parent'       => 0,
				'date_created' => '<' . time(),
			)
		);

		$customers = array_map(
			function ($all_order_id) {
				$orders = wc_get_order($all_order_id);

				$order_arr = array(
					'billing_email'       => $orders->get_billing_email(),
					'billing_first_name'  => $orders->get_billing_first_name(),
					'billing_last_name'   => $orders->get_billing_last_name(),
					'customer_id'         => $orders->get_customer_id(),
					'billing_address_1'   => $orders->get_billing_address_1(),
					'billing_address_2'   => $orders->get_billing_address_2(),
					'billing_city'        => $orders->get_billing_city(),
					'billing_country'     => $orders->get_billing_country(),
					'billing_postcode'    => $orders->get_billing_postcode(),
					'billing_state'       => $orders->get_billing_state(),
					'billing_phone'       => $orders->get_billing_phone(),
					'shipping_first_name' => $orders->get_shipping_first_name(),
					'shipping_last_name'  => $orders->get_shipping_last_name(),
					'shipping_address_1'  => $orders->get_shipping_address_1(),
					'shipping_address_2'  => $orders->get_shipping_address_2(),
					'shipping_city'       => $orders->get_shipping_city(),
					'shipping_country'    => $orders->get_shipping_country(),
					'shipping_postcode'   => $orders->get_shipping_postcode(),
					'shipping_state'      => $orders->get_shipping_state(),
					'registered_date'     => (function () use ($orders) {
						$customer = new \WC_Customer($orders->get_customer_id());
						$date_created = $customer->get_date_created();
						return $date_created ? $date_created->date('Y-m-d H:i:s') : null;
					})(),
					'total_spent'         => (function () use ($orders) {
						$customer = new \WC_Customer($orders->get_customer_id());
						return $customer->get_total_spent();
					})(),
					'total_orders'        => (function () use ($orders) {
						$customer = new \WC_Customer($orders->get_customer_id());
						return $customer->get_order_count();
					})(),
				);

				return $order_arr;
			},
			$all_order_ids
		);
		return $customers;
	}


	/**
	 * Import EDD customers information from orders and metadata table
	 *
	 * @param int $offset The starting point of orders to retrieve customers.
	 *
	 * @return array
	 * @since 1.0.0
	 * @since 1.11.0 Bugfix: Offset and limit added to the query.
	 */
	public static function edd_get_customers($offset, $limit)
	{
		if (!class_exists('\Easy_Digital_Downloads')) {
			return false;
		}

		$r = wp_parse_args(
			array(
				'number' => $limit,
				'offset' => $offset,
			)
		);

		$customers      = array();
		$customer_query = new Customer();
		$customer_query = $customer_query->query($r);

		if ($customer_query) {
			foreach ($customer_query as $customer_obj) {
				$customer                      = array();
				$customer['customer_id']       = $customer_obj->id;
				$customer['user_id']           = 0;
				$customer['username']          = '';
				$customer['display_name']      = '';
				$customer['email']             = $customer_obj->email;
				$customer['additional_emails'] = null;
				$customer['date_created']      = $customer_obj->date_created;

				if (! empty($customer_obj->name)) {
					$names                  = explode(' ', $customer_obj->name);
					$customer['first_name'] = $names[0];
					$customer['last_name']  = count($names) > 1 ? implode(' ', array_slice($names, 1)) : '';
				}

				if (! empty($customer_obj->emails) && count($customer_obj->emails) > 1) {
					$additional_emails = $customer_obj->emails;
					if (($key = array_search($customer_obj->email, $additional_emails)) !== false) {
						unset($additional_emails[$key]);
					}
					$customer['additional_emails'] = $additional_emails;
				}

				if (! empty($customer_obj->user_id) && $customer_obj->user_id > 0) {
					$user_data                = get_userdata($customer_obj->user_id);
					$customer['user_id']      = $customer_obj->user_id;
					$customer['username']     = $user_data->user_login;
					$customer['display_name'] = $user_data->display_name;
				}

				$customer['total_purchases'] = $customer_obj->purchase_count;
				$customer['total_spent']     = edd_format_amount($customer_obj->purchase_value, true, '', 'typed');
				$customer['total_downloads'] = edd_count_file_downloads_of_customer($customer_obj->id);

				array_push($customers, $customer);
			}
		}
		return $customers;
	}

	/**
	 * Import fluent booking guest information from booking and metadata table
	 *
	 * @param int $offset The starting point of booking to retrieve guest.
	 *
	 * @return array
	 * @since 1.16.6
	 */
	public static function get_fluent_booking_users($offset, $limit){
		global $wpdb;
		$booking_table      = $wpdb->prefix . 'fcal_bookings';
		$booking_meta_table = $wpdb->prefix . 'fcal_booking_meta';
		
		$query   = $wpdb->prepare( "SELECT b.email, b.first_name, b.last_name, b.phone, b.country, bm.meta_key, bm.value FROM $booking_table b LEFT JOIN $booking_meta_table bm ON b.id = bm.booking_id LIMIT %d OFFSET %d", $limit, $offset);
		$results =  $wpdb->get_results($query, ARRAY_A);

		$formatted_results = array();
		foreach ($results as $row) {
			$email = $row['email'];

			if (!isset($formatted_results[$email])) {
				$formatted_results[$email] = [
					'email'      => $row['email'],
					'first_name' => $row['first_name'],
					'last_name'  => $row['last_name'],
					'phone'      => $row['phone'],
					'country'    => $row['country'],
				];
			}

			if (!empty($row['meta_key'])) {
				$formatted_results[$email][$row['meta_key']] = $row['value'];
			}
		}

		return array_values($formatted_results);
	}
	/**
	 * Creating batch for contact import
	 *
	 * @param string $file Imported CSV file.
	 * @param string $delimiter  The delimiter used in the CSV file.
	 *
	 * @return array
	 * @throws Exception $e Throws an exception if the action could not be saved.
	 * @since 1.0.1
	 */
	public static function csv_batch_creator($file, $delimiter)
	{
		if (isset($file)) {
			$file = MRM_IMPORT_DIR . '/' . $file;
		}

		// if the file does not exist return error.
		if (! file_exists($file)) {
			throw new Exception(__('The File is not found on this server.', 'mrm'));
		}

		$file = fopen($file, 'r'); //  phpcs:ignore

		while ((false !== $data = fgetcsv($file, 0, $delimiter))) { //  phpcs:ignore
			$array[] = $data;
		}

		fclose($file); //  phpcs:ignore

		$arr = array_slice($array, 1);

		$per_batch   = 500;
		$total_batch = ceil(count($arr) / $per_batch);
		$offset      = 0;

		return array(
			'all_data'    => $arr,
			'total_batch' => $total_batch,
			'offset'      => $offset,
			'per_batch'   => $per_batch,
			'delimiter'   => $delimiter,
		);
	}

	/**
	 * Summary: Retrieves MailChimp response.
	 * Description: Retrieves the MailChimp response based on the provided API key, endpoint, and offset.
	 *
	 * @access public
	 *
	 * @param string $api_key The MailChimp API key.
	 * @param string $endpoint The MailChimp API endpoint.
	 * @param int    $offset Optional. The offset value for pagination. Default is 0.
	 *
	 * @return array Returns the MailChimp response or an error array.
	 * @since 1.0.9
	 */
	public static function get_mailchimp_response(string $api_key, string $endpoint, int $offset = 0)
	{
		$key = (preg_match('/[a-zA-Z0-9]{32}-[a-zA-Z0-9]{2,4}$/', $api_key)) ? $api_key : false;
		if (empty($key)) {
			$error = array(
				'status'  => 401,
				'message' => 'Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.',
			);
			return $error;
		}

		$key_array = explode('-', $key);

		if (count($key_array) <= 1) {
			$error = array(
				'status'  => 401,
				'message' => 'Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.',
			);
			return $error;
		}

		$key_server = $key_array[1];

		$url = sprintf('https://user:%s@%s.api.mailchimp.com/3.0/', $key, $key_server);

		$url .= $endpoint . '?' . http_build_query(
			array(
				'count'  => 100,
				'offset' => $offset,
			)
		);

		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-Api-Key'    => $api_key,
			),
		);

		$response = wp_remote_get(
			esc_url_raw($url),
			$args
		);

		if ((!is_wp_error($response)) && (200 === wp_remote_retrieve_response_code($response))) {
			return json_decode(wp_remote_retrieve_body($response), true);
		}

		$error = array(
			'status' => 401,
			'detail' => 'Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.',
		);
		return $error;
	}

	/**
	 * Summary: Formats MailChimp lists.
	 *
	 * Description: Formats the MailChimp lists into a specific format containing name, ID, and member count.
	 *
	 * @access public
	 *
	 * @param array $lists The MailChimp lists array.
	 * @return array Returns the formatted MailChimp lists array.
	 * @since 1.4.9
	 */
	public static function get_format_mailchimp_lists($lists)
	{
		if (empty($lists) || ! is_array($lists)) {
			return array();
		}

		$formatted_lists = array();

		foreach ($lists as $list) {
			$formatted_lists[] = array(
				'name'         => isset($list['name']) ? $list['name'] : '',
				'id'           => isset($list['id']) ? $list['id'] : '',
				'member_count' => isset($list['stats']['member_count']) ? $list['stats']['member_count'] : 0,
			);
		}

		return $formatted_lists;
	}

	/**
	 * Validate the uploaded CSV file.
	 *
	 * This function checks whether the uploaded file is a valid CSV file.
	 *
	 * @access public
	 *
	 * @param array $file The file information array.
	 * @return bool True if the file is a valid CSV, false otherwise.
	 * @since 1.5.1
	 */
	public static function csv_file_upload_validation($file)
	{
		// Get allowed CSV mime types from MrmCommon class.
		$csv_mimes = MrmCommon::csv_mimes();

		// Get file type and temporary name from the file array.
		$file_type     = isset($file['type']) ? $file['type'] : '';
		$file_tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';

		// CSV file upload validation.
		if (
			empty($file) ||
			! is_array($file) ||
			! is_uploaded_file($file_tmp_name) ||
			! in_array($file_type, $csv_mimes, true)
		) {
			return false;
		}
		return true;
	}

	/**
	 * Parse raw data into headers and content using a specified delimiter.
	 *
	 * This function splits the provided raw data into headers and content using the given delimiter.
	 *
	 * @access public
	 *
	 * @param string $raw The raw data to be parsed.
	 * @param string $delimiter The delimiter used to separate values in the raw data.
	 *
	 * @return array An array containing headers as the first element and content as the second element.
	 *
	 * @since 1.5.2
	 */
	public static function parse_raw_data($raw, $delimiter)
	{
		$lines = preg_split("/\r\n|\n|\r/", $raw);

		$parsed_data = array(
			'headers' => array(),
			'content' => array(),
		);

		if (count($lines) > 1) {
			// Trim whitespace from the first line.
			$first_line = trim($lines[0]);

			// Check if the delimiter exists in the first line.
			if (empty($first_line)) {
				return $parsed_data;
			} elseif (!empty($first_line) && strpos($first_line, $delimiter) !== false) {
				$parsed_data['headers'] = explode($delimiter, $first_line);
			} else {
				// Handle case when delimiter doesn't match.
				$parsed_data['headers'] = false;
			}
		}

		$parsed_data['content'] = array_slice($lines, 1);

		return $parsed_data;
	}

	/**
	 * Validate raw data for the presence of valid email addresses.
	 *
	 * This function checks if the provided raw data contains valid email addresses.
	 *
	 * @access public
	 *
	 * @param array  $content   An array containing rows of raw data to be validated.
	 * @param string $delimiter The delimiter used to separate values in the raw data.
	 * @param array  $headers   An array containing column headers.
	 *
	 * @return bool True if valid email addresses are found, false otherwise.
	 *
	 * @since 1.5.2
	 */
	public static function validate_raw_data_format($content, $delimiter, $headers)
	{
		$flag = true;

		foreach ($content as $row) {
			if (!empty($row)) {
				$row = str_getcsv($row, $delimiter, '"');
				if (count($headers) !== count($row)) {
					$flag = false;
					break;
				}
			}
		}
		return $flag;
	}

	/**
	 * Retrieve WordPress Users by Roles with Limit and Offset.
	 *
	 * Retrieves an array of WordPress user objects based on specified roles, with the option to limit the number
	 * of users returned and provide an offset. User metadata is also fetched and included in the user objects.
	 *
	 * @access public
	 *
	 * @param array $roles   An array of roles to filter users by.
	 * @param int   $number  The maximum number of users to retrieve.
	 * @param int   $offset  The offset for pagination.
	 * @return array An array of WordPress user objects with associated metadata.
	 * @since 1.5.4
	 */
	public static function get_wp_users_by_roles_with_limit_offset($roles = array(), $number = 5, $offset = 0)
	{
		if (empty($roles)) {
			return array();
		}

		$users = get_users(
			array(
				'role__in' => $roles,
				'orderby'  => 'ID',
				'order'    => 'ASC',
				'offset'   => $offset,
				'number'   => $number,
			)
		);

		if (empty($users)) {
			return array();
		}

		$formatted_users = array_map(
			function ($user) {
				// Flatten core user data from the "data" property
				$user_data = (array) $user->data;

				// Merge additional properties of WP_User into the flat array
				$additional_data = array(
					'roles'   => $user->roles,
					'allcaps' => $user->allcaps,
					'caps'    => $user->caps,
				);

				// Fetch and merge user meta
				$user_meta = get_user_meta($user->ID);
				foreach ($user_meta as $key => $value) {
					$user_data[$key] = reset($value); // Flatten meta values
				}

				return array_merge($user_data, $additional_data);
			},
			$users
		);

		return $formatted_users;
	}

	/**
	 * Prepare Contact Data for Import.
	 *
	 * This function prepares the contact data and arguments for the import process.
	 * It processes mappings and creates contact arguments including status, source, meta fields, and more.
	 *
	 * @access public
	 *
	 * @param array  $csv_contact The contact data from the CSV.
	 * @param array  $mappings    The mappings for contact data fields.
	 * @param string $import_type The type of import, either 'csv' or 'raw'.
	 * @param string $status      The status of the imported contact.
	 * @param int    $created_by  The ID of the user who created the contact.
	 * @return array Prepared contact arguments for the import process.
	 *
	 * @since 1.5.4
	 * @modified 1.7.1 Add lists and tags on the contact_args
	 */
	public static function prepare_contact_arguments($csv_contact, $mappings, $import_type, $status, $created_by)
	{
		$contact_args = array(
			'status'      => $status,
			'source'      => 'csv' === $import_type ? strtoupper($import_type) : ucfirst($import_type),
			'meta_fields' => array(),
			'created_by'  => $created_by,
		);

		foreach ($mappings as $map) {
			$target = isset($map['target']) ? $map['target'] : '';
			$source = isset($map['source']) ? $map['source'] : '';

			if (in_array($target, array('first_name', 'last_name', 'email'), true)) {
				$contact_args[$target] = $csv_contact[$source];
			} elseif (in_array($target, array('lists', 'tags'), true)) {
				$contact_args['groups'][$target] = $csv_contact[$source];
			} else {
				$contact_args['meta_fields'][$target] = $csv_contact[$source];
			}
		}

		return $contact_args;
	}

	/**
	 * Get WordPress users by LearnDash with limit and offset.
	 *
	 * Description: Retrieves WordPress users associated with LearnDash courses based on the provided parameters.
	 *
	 * @param array $courses An array of selected LearnDash courses.
	 * @param int   $number  The number of users to retrieve (default is 5).
	 * @param int   $offset  The offset for batch processing (default is 0).
	 * @return array An array containing formatted user data and the total number of users.
	 * @access public
	 * @since 1.8.0
	 */
	public static function get_wp_users_by_learndash_with_limit_offset($courses, $number = 5, $offset = 0)
	{
		// Extract course IDs from the provided courses.
		$course_ids = array_column($courses, 'value');

		// If no course IDs are provided, get all LearnDash courses.
		if (!$course_ids) {
			$all_courses = HelperFunctions::get_learndash_courses();
			$course_ids  = array_column($all_courses, 'value');
		}

		$keys = array_map(
			function ($course_id) {
				return 'course_' . $course_id . '_access_from';
			},
			$course_ids
		);

		// Query to get the total number of distinct user IDs.
		global $wpdb;
		// Create placeholders for IN clause
		$placeholders = implode(', ', array_fill(0, count($keys), '%s'));

		// Total query (safe with prepare)
		$total_query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) as total
			FROM {$wpdb->usermeta}
			WHERE meta_key IN ($placeholders)",
			$keys
		);

		$total = $wpdb->get_var($total_query); //phpcs:ignore

		// Final query with LIMIT & OFFSET (safe with prepare)
		$final_query = $wpdb->prepare(
			"SELECT user_id
			FROM {$wpdb->usermeta}
			WHERE meta_key IN ($placeholders)
			GROUP BY user_id
			LIMIT %d OFFSET %d",
			array_merge($keys, array($number, $offset))
		);

		$users = $wpdb->get_results($final_query, ARRAY_A); //phpcs:ignore

		if (empty($users)) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		$user_ids = array();

		foreach ($users as $user) {
			$user_ids[] = $user['user_id'];
		}

		$contacts = get_users(
			array(
				'include' => $user_ids,
			)
		);

		if (empty($contacts)) {
			return array();
		}

		// Format user data, including usermeta information.
		$formatted_users = array_map(
			function ($user) {
				$user->usermeta = array_map(
					function ($user_data) {
						return reset($user_data);
					},
					get_user_meta($user->ID)
				);

				return $user;
			},
			$contacts
		);

		return array(
			'formatted_users' => $formatted_users,
			'total_users'     => $total,
		);
	}

	/**
	 * Get WordPress users by Tutor LMS with limit and offset.
	 *
	 * Description: Retrieves WordPress users associated with Tutor LMS courses based on the provided parameters.
	 *
	 * @param array $courses An array of selected Tutor LMS courses.
	 * @param int   $number  The number of users to retrieve (default is 5).
	 * @param int   $offset  The offset for batch processing (default is 0).
	 * @return array An array containing formatted user data and the total number of users.
	 * @access public
	 * @since 1.8.0
	 */
	public static function get_wp_users_by_tutorlms_with_limit_offset($courses, $number = 5, $offset = 0)
	{
		// Extract course IDs from the provided courses.
		$course_ids = array_column($courses, 'value');

		// If no course IDs are provided, get all LearnDash courses.
		if (!$course_ids) {
			$all_courses = HelperFunctions::get_tutor_lms_courses();
			$course_ids  = array_column($all_courses, 'value');
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'posts';

		$enrollments_query = $wpdb->prepare("SELECT post_author FROM $table_name WHERE post_type = 'tutor_enrolled' AND post_parent IN ('" . implode("', '", $course_ids) . "')"); //phpcs:ignore

		$total_query = $wpdb->prepare("SELECT COUNT( DISTINCT post_author) FROM $table_name WHERE post_type = 'tutor_enrolled' AND post_parent IN ('" . implode("', '", $course_ids) . "')"); //phpcs:ignore

		$total = $wpdb->get_var($total_query); //phpcs:ignore

		$enrollments_query .= $wpdb->prepare(' LIMIT %d OFFSET %d', $number, $offset);

		$enrollments = $wpdb->get_results($enrollments_query); //phpcs:ignore

		if (empty($enrollments)) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		$user_ids = array();

		foreach ($enrollments as $enrollment) {
			$user_ids[] = $enrollment->post_author;
		}

		$contacts = get_users(
			array(
				'include' => $user_ids,
			)
		);

		if (empty($contacts)) {
			return array();
		}

		// Format user data, including usermeta information.
		$formatted_users = array_map(
			function ($user) {
				$user->usermeta = array_map(
					function ($user_data) {
						return reset($user_data);
					},
					get_user_meta($user->ID)
				);

				return $user;
			},
			$contacts
		);

		return array(
			'formatted_users' => $formatted_users,
			'total_users'     => $total,
		);
	}

	/**
	 * Get WordPress users by MemberPress with limit and offset.
	 *
	 * Description: Retrieves WordPress users associated with MemberPress levels based on the provided parameters.
	 *
	 * @param array $levels An array of selected MemberPress levels.
	 * @param int   $number  The number of users to retrieve (default is 5).
	 * @param int   $offset  The offset for batch processing (default is 0).
	 * @return array An array containing formatted user data and the total number of users.
	 * @access public
	 * @since 1.8.0
	 */
	public static function get_wp_users_by_memberpress_with_limit_offset($levels, $number = 5, $offset = 0)
	{
		// Extract level IDs from the provided levels.
		$level_ids = array_column($levels, 'value');

		// If no level IDs are provided, get all membership levels.
		if (!$level_ids) {
			$all_levels = HelperFunctions::get_mp_membership_levels();
			$level_ids  = array_column($all_levels, 'value');
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mepr_members';
		$conditions = array();

		foreach ($level_ids as $membership_id) {
			$conditions[] = $wpdb->prepare('FIND_IN_SET( %d, memberships ) > 0', $membership_id);
		}

		$enrollments_query = $wpdb->prepare("SELECT user_id, memberships FROM $table_name WHERE memberships != '' AND (' . implode(' OR ', $conditions) . ')"); //phpcs:ignore

		$total_query = $wpdb->prepare("SELECT count( user_id ), memberships FROM $table_name WHERE memberships != '' AND (' . implode(' OR ', $conditions) . ')"); //phpcs:ignore

		$total = $wpdb->get_var($total_query); //phpcs:ignore

		$enrollments_query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $number, $offset); //phpcs:ignore

		$enrollments = $wpdb->get_results($enrollments_query, ARRAY_A); //phpcs:ignore

		if (empty($enrollments)) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		$user_ids = array();

		foreach ($enrollments as $enrollment) {
			$user_ids[] = $enrollment['user_id'];
		}

		$contacts = get_users(
			array(
				'include' => $user_ids,
			)
		);

		if (empty($contacts)) {
			return array();
		}

		// Format user data, including usermeta information.
		$formatted_users = array_map(
			function ($user) {
				$user->usermeta = array_map(
					function ($user_data) {
						return reset($user_data);
					},
					get_user_meta($user->ID)
				);

				return $user;
			},
			$contacts
		);

		return array(
			'formatted_users' => $formatted_users,
			'total_users'     => $total,
		);
	}

	/**
	 * Get WordPress users by LifterLMS with limit and offset.
	 *
	 * Description: Retrieves WordPress users associated with LifterLMS courses based on the provided parameters.
	 *
	 * @param array $courses An array of selected LifterLMS courses.
	 * @param int   $number  The number of users to retrieve (default is 5).
	 * @param int   $offset  The offset for batch processing (default is 0).
	 * @return array An array containing formatted user data and the total number of users.
	 * @access public
	 * @since 1.12.0
	 */
	public static function get_wp_users_by_lifterlms_with_limit_offset($courses, $number = 5, $offset = 0)
	{
		// Extract course IDs from the provided courses.
		$course_ids = array_column($courses, 'value');

		// If no course IDs are provided, get all LearnDash courses.
		if (!$course_ids) {
			$all_courses = HelperFunctions::get_lifter_lms_courses();
			$course_ids  = array_column($all_courses, 'value');
		}

		$course_ids = implode(', ', $course_ids);

		global $wpdb;

		$table_name  = $wpdb->prefix . 'lifterlms_user_postmeta';
		$users_table = $wpdb->prefix . 'users';

		$users = $wpdb->get_results("SELECT u.ID as id, (
			SELECT meta_value
			FROM {$table_name}
			WHERE meta_key = '_status'
			AND user_id = id
			AND post_id IN ($course_ids)
			ORDER BY updated_date DESC
			LIMIT $number OFFSET $offset ) AS status
			FROM $users_table as u
			HAVING status IS NOT NULL
			AND status = 'enrolled'", ARRAY_A);

		$total = $wpdb->get_var("SELECT COUNT(*) AS enrolled_count
			FROM (
				SELECT u.ID as id
				FROM $users_table as u
				WHERE EXISTS (
					SELECT 1
					FROM $table_name
					WHERE meta_key = '_status'
					AND user_id = u.ID
					AND post_id IN ($course_ids)
					AND meta_value = 'enrolled'
					ORDER BY updated_date DESC
				)
			) AS enrolled_users");

		if (empty($users)) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		$user_ids = array();

		foreach ($users as $user) {
			$user_ids[] = $user['id'];
		}

		$contacts = get_users(
			array(
				'include' => $user_ids,
			)
		);

		if (empty($contacts)) {
			return array();
		}

		// Format user data, including usermeta information.
		$formatted_users = array_map(
			function ($user) {
				$user->usermeta = array_map(
					function ($user_data) {
						return reset($user_data);
					},
					get_user_meta($user->ID)
				);

				return $user;
			},
			$contacts
		);

		return array(
			'formatted_users' => $formatted_users,
			'total_users'     => $total,
		);
	}

	/**
	 * Retrieves MailPoet contacts with a specified limit and offset.
	 *
	 * @param int $number The number of contacts to retrieve. Default is 5.
	 * @param int $offset The offset for the contacts to retrieve. Default is 0.
	 * @return array An array containing 'formatted_subscribers' and 'total_subscribers'.
	 *               'formatted_subscribers' is an array of subscribers with their custom fields.
	 *               'total_subscribers' is the total number of subscribers.
	 * @since 1.16.9 
	 */
	public static function get_mail_poet_contacts_with_limit_offset( $number = 5, $offset = 0 ){
		global $wpdb;
		$subscribers_table       = $wpdb->prefix . 'mailpoet_subscribers';
		$subscriber_custom_table = $wpdb->prefix . 'mailpoet_subscriber_custom_field';

		$query = $wpdb->prepare("SELECT 
			s.id AS subscriber_id,
			s.wp_user_id,
			s.first_name,
			s.last_name,
			s.email,
			s.status,
			cf.custom_field_id,
			cf.value AS custom_field_value
			FROM {$subscribers_table} s
			LEFT JOIN {$subscriber_custom_table} cf ON s.id = cf.subscriber_id
			LIMIT %d OFFSET %d
		",
			$number,
			$offset
		);

		$subscribers = $wpdb->get_results($query, ARRAY_A);

		if (empty($subscribers)) {
			return array(
					'formatted_subscribers' => array(),
					'total_subscribers'     => 0,
			);
		}

		$total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$subscribers_table}"));

		$formatted_subscribers = array_map(function ($subscriber) {
			$subscriber['cf_'. $subscriber['custom_field_id']] = $subscriber['custom_field_value'];
			unset($subscriber['custom_field_id']);
			unset($subscriber['custom_field_value']);
			return $subscriber;
		}, $subscribers);

		return array(
			'formatted_subscribers' => $formatted_subscribers,
			'total_subscribers'     => $total,
		);
	}
}
