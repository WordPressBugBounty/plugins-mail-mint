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
	 * Summary: Retrieves a MailerLite API response.
	 * Description: Calls the MailerLite API (connect.mailerlite.com) with the given API token, endpoint and query.
	 *
	 * @access public
	 *
	 * @param string $api_key  The MailerLite API token.
	 * @param string $endpoint The MailerLite API endpoint, e.g. "groups".
	 * @param array  $query    Optional. Query arguments such as limit, page or cursor.
	 *
	 * @return array Returns the decoded MailerLite response or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_mailerlite_response( string $api_key, string $endpoint, array $query = array() ) {
		$api_key = trim( $api_key );
		if ( empty( $api_key ) ) {
			return array(
				'status'  => 401,
				'message' => __( 'Please enter your MailerLite API key.', 'mrm' ),
			);
		}

		$url = 'https://connect.mailerlite.com/api/' . $endpoint;
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query );
		}

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 401,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) ) {
			return $body;
		}

		if ( 401 === $code ) {
			$message = __( 'Your MailerLite API key is invalid.', 'mrm' );
		} else {
			$message = isset( $body['message'] ) ? $body['message'] : __( 'Unable to connect to MailerLite.', 'mrm' );
		}

		return array(
			'status'  => 401,
			'message' => $message,
		);
	}

	/**
	 * Summary: Retrieves every MailerLite group.
	 * Description: Walks the page-based groups endpoint and formats each group with name, ID and active subscriber count.
	 *
	 * @access public
	 *
	 * @param string $api_key The MailerLite API token.
	 * @return array Returns the formatted groups, or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_mailerlite_groups( string $api_key ) {
		$groups = array();
		$page   = 1;

		do {
			$response = self::get_mailerlite_response(
				$api_key,
				'groups',
				array(
					'limit' => 100,
					'page'  => $page,
				)
			);

			if ( isset( $response['status'] ) && 401 === $response['status'] ) {
				return $response;
			}

			$data = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
			foreach ( $data as $group ) {
				$groups[] = array(
					'name'         => isset( $group['name'] ) ? $group['name'] : '',
					'id'           => isset( $group['id'] ) ? (string) $group['id'] : '',
					'member_count' => isset( $group['active_count'] ) ? (int) $group['active_count'] : 0,
				);
			}

			$last_page = isset( $response['meta']['last_page'] ) ? (int) $response['meta']['last_page'] : $page;
			$page++;
		} while ( $page <= $last_page && ! empty( $data ) );

		return $groups;
	}

	/**
	 * Summary: Retrieves a Drip API response.
	 * Description: Calls the Drip REST API v2 (api.getdrip.com) with the given API token, endpoint and query.
	 * Drip authenticates with HTTP Basic: the token is the username and the password is blank.
	 *
	 * @access public
	 *
	 * @param string $api_token The Drip API token.
	 * @param string $endpoint  The Drip API endpoint, e.g. "accounts" or "{account_id}/subscribers".
	 * @param array  $query     Optional. Query arguments such as page, per_page or tags.
	 *
	 * @return array Returns the decoded Drip response or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_drip_response( string $api_token, string $endpoint, array $query = array() ) {
		$api_token = trim( $api_token );
		if ( empty( $api_token ) ) {
			return array(
				'status'  => 401,
				'message' => __( 'Please enter your Drip API token.', 'mrm' ),
			);
		}

		$url = 'https://api.getdrip.com/v2/' . $endpoint;
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query );
		}

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_token . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Accept'        => 'application/json',
					// Drip rejects requests without a User-Agent.
					'User-Agent'    => 'Mail Mint (' . home_url() . ')',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 401,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) ) {
			return $body;
		}

		if ( 401 === $code ) {
			$message = __( 'Your Drip API token is invalid.', 'mrm' );
		} elseif ( 429 === $code ) {
			$message = __( 'Drip\'s hourly API limit has been reached. Please try again later.', 'mrm' );
		} else {
			$message = isset( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : __( 'Unable to connect to Drip.', 'mrm' );
		}

		return array(
			'status'  => 401,
			'message' => $message,
		);
	}

	/**
	 * Summary: Retrieves every Drip account the API token can access.
	 *
	 * @access public
	 *
	 * @param string $api_token The Drip API token.
	 * @return array Returns the accounts as [{id, name}], or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_drip_accounts( string $api_token ) {
		$response = self::get_drip_response( $api_token, 'accounts' );

		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return $response;
		}

		$accounts = array();
		$data     = isset( $response['accounts'] ) && is_array( $response['accounts'] ) ? $response['accounts'] : array();
		foreach ( $data as $account ) {
			if ( empty( $account['id'] ) ) {
				continue;
			}
			$accounts[] = array(
				'id'   => (string) $account['id'],
				'name' => isset( $account['name'] ) ? $account['name'] : (string) $account['id'],
			);
		}

		return $accounts;
	}

	/**
	 * Summary: Counts the active subscribers of a Drip account, optionally with one tag.
	 *
	 * @access public
	 *
	 * @param string $api_token  The Drip API token.
	 * @param string $account_id The Drip account ID.
	 * @param string $tag        Optional. Only count subscribers with this tag.
	 *
	 * @return array|int Returns the subscriber count, or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_drip_subscriber_total( string $api_token, string $account_id, string $tag = '' ) {
		$query = array(
			'status'   => 'active',
			'per_page' => 1,
		);
		if ( '' !== $tag ) {
			$query['tags'] = $tag;
		}

		$response = self::get_drip_response( $api_token, rawurlencode( $account_id ) . '/subscribers', $query );

		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return $response;
		}

		return isset( $response['meta']['total_count'] ) ? (int) $response['meta']['total_count'] : 0;
	}

	/**
	 * Summary: Normalizes an ActiveCampaign API URL.
	 * Description: Trims it and drops a trailing slash or "/api/3", so both the bare account URL and
	 * the full API URL work. Only https URLs are accepted.
	 *
	 * @access public
	 *
	 * @param string $api_url The API URL from ActiveCampaign → Settings → Developer.
	 * @return string Returns the base URL, or an empty string when it is not a valid https URL.
	 * @since 1.32.0
	 */
	public static function normalize_activecampaign_url( string $api_url ) {
		$api_url = untrailingslashit( trim( $api_url ) );
		$api_url = preg_replace( '#/api/3$#i', '', $api_url );
		$api_url = untrailingslashit( $api_url );

		if ( 'https' !== wp_parse_url( $api_url, PHP_URL_SCHEME ) || ! wp_http_validate_url( $api_url ) ) {
			return '';
		}

		return $api_url;
	}

	/**
	 * Summary: Retrieves an ActiveCampaign API response.
	 * Description: Calls the ActiveCampaign API v3 of the given account with the given key, endpoint and query.
	 *
	 * @access public
	 *
	 * @param string $api_url  The ActiveCampaign API URL, e.g. https://account.api-us1.com.
	 * @param string $api_key  The ActiveCampaign API key.
	 * @param string $endpoint The API endpoint, e.g. "lists" or "contacts".
	 * @param array  $query    Optional. Query arguments such as limit, offset or listid.
	 *
	 * @return array Returns the decoded ActiveCampaign response or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_activecampaign_response( string $api_url, string $api_key, string $endpoint, array $query = array() ) {
		$api_url = self::normalize_activecampaign_url( $api_url );
		if ( '' === $api_url ) {
			return array(
				'status'  => 401,
				'message' => __( 'Please enter a valid ActiveCampaign API URL (https://…).', 'mrm' ),
			);
		}

		$api_key = trim( $api_key );
		if ( empty( $api_key ) ) {
			return array(
				'status'  => 401,
				'message' => __( 'Please enter your ActiveCampaign API key.', 'mrm' ),
			);
		}

		$url = $api_url . '/api/3/' . $endpoint;
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query );
		}

		// The URL comes from the user, so the safe variant blocks local and private hosts.
		$response = wp_safe_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Api-Token' => $api_key,
					'Accept'    => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 401,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) ) {
			return $body;
		}

		if ( 401 === $code || 403 === $code ) {
			$message = __( 'Your ActiveCampaign API URL or key is invalid.', 'mrm' );
		} elseif ( 429 === $code ) {
			$message = __( 'ActiveCampaign\'s API limit has been reached. Please try again in a moment.', 'mrm' );
		} elseif ( 404 === $code ) {
			$message = __( 'No ActiveCampaign API was found at this URL.', 'mrm' );
		} else {
			$message = isset( $body['message'] ) ? $body['message'] : __( 'Unable to connect to ActiveCampaign.', 'mrm' );
		}

		return array(
			'status'  => 401,
			'message' => $message,
		);
	}

	/**
	 * Summary: Retrieves every ActiveCampaign list.
	 * Description: Walks the offset-based lists endpoint and formats each list with name and ID.
	 *
	 * @access public
	 *
	 * @param string $api_url The ActiveCampaign API URL.
	 * @param string $api_key The ActiveCampaign API key.
	 * @return array Returns the lists as [{id, name}], or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_activecampaign_lists( string $api_url, string $api_key ) {
		$lists  = array();
		$offset = 0;

		do {
			$response = self::get_activecampaign_response(
				$api_url,
				$api_key,
				'lists',
				array(
					'limit'  => 100,
					'offset' => $offset,
				)
			);

			if ( isset( $response['status'] ) && 401 === $response['status'] ) {
				return $response;
			}

			$data = isset( $response['lists'] ) && is_array( $response['lists'] ) ? $response['lists'] : array();
			foreach ( $data as $list ) {
				if ( empty( $list['id'] ) ) {
					continue;
				}
				$lists[] = array(
					'id'   => (string) $list['id'],
					'name' => isset( $list['name'] ) ? $list['name'] : (string) $list['id'],
				);
			}

			$total   = isset( $response['meta']['total'] ) ? (int) $response['meta']['total'] : 0;
			$offset += 100;
		} while ( $offset < $total && ! empty( $data ) );

		return $lists;
	}

	/**
	 * Summary: Counts the contacts that are active on an ActiveCampaign list.
	 *
	 * @access public
	 *
	 * @param string $api_url The ActiveCampaign API URL.
	 * @param string $api_key The ActiveCampaign API key.
	 * @param string $list_id The ActiveCampaign list ID.
	 *
	 * @return array|int Returns the contact count, or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_activecampaign_contact_total( string $api_url, string $api_key, string $list_id ) {
		$response = self::get_activecampaign_response(
			$api_url,
			$api_key,
			'contacts',
			array(
				'listid' => $list_id,
				'status' => 1,
				'limit'  => 1,
			)
		);

		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return $response;
		}

		return isset( $response['meta']['total'] ) ? (int) $response['meta']['total'] : 0;
	}

	/**
	 * Summary: Retrieves a Kit API response.
	 * Description: Calls the Kit API v4 (api.kit.com) with the given v4 API key, endpoint and query.
	 *
	 * @access public
	 *
	 * @param string $api_key  The Kit v4 API key.
	 * @param string $endpoint The Kit API endpoint, e.g. "tags" or "subscribers".
	 * @param array  $query    Optional. Query arguments such as per_page, after or status.
	 *
	 * @return array Returns the decoded Kit response or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_kit_response( string $api_key, string $endpoint, array $query = array() ) {
		$api_key = trim( $api_key );
		if ( empty( $api_key ) ) {
			return array(
				'status'  => 401,
				'message' => __( 'Please enter your Kit API key.', 'mrm' ),
			);
		}

		$url = 'https://api.kit.com/v4/' . $endpoint;
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query );
		}

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 30,
				'headers' => array(
					'X-Kit-Api-Key' => $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 401,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $body ) ) {
			return $body;
		}

		if ( 401 === $code ) {
			$message = __( 'Your Kit API key is invalid. Please use a v4 API key.', 'mrm' );
		} elseif ( 429 === $code ) {
			$message = __( 'Kit\'s API limit has been reached. Please try again shortly.', 'mrm' );
		} else {
			$message = isset( $body['errors'][0] ) && is_string( $body['errors'][0] ) ? $body['errors'][0] : __( 'Unable to connect to Kit.', 'mrm' );
		}

		return array(
			'status'  => 401,
			'message' => $message,
		);
	}

	/**
	 * Summary: Retrieves every Kit tag.
	 * Description: Walks the cursor-based tags endpoint and formats each tag with name and ID.
	 *
	 * @access public
	 *
	 * @param string $api_key The Kit v4 API key.
	 * @return array Returns the tags as [{id, name}], or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_kit_tags( string $api_key ) {
		$tags  = array();
		$after = '';

		do {
			$query = array( 'per_page' => 1000 );
			if ( '' !== $after ) {
				$query['after'] = $after;
			}

			$response = self::get_kit_response( $api_key, 'tags', $query );
			if ( isset( $response['status'] ) && 401 === $response['status'] ) {
				return $response;
			}

			$data = isset( $response['tags'] ) && is_array( $response['tags'] ) ? $response['tags'] : array();
			foreach ( $data as $tag ) {
				if ( empty( $tag['id'] ) ) {
					continue;
				}
				$tags[] = array(
					'id'   => (string) $tag['id'],
					'name' => isset( $tag['name'] ) ? $tag['name'] : (string) $tag['id'],
				);
			}

			$has_next = ! empty( $response['pagination']['has_next_page'] ) && ! empty( $response['pagination']['end_cursor'] );
			$after    = $has_next ? (string) $response['pagination']['end_cursor'] : '';
		} while ( $has_next && ! empty( $data ) );

		return $tags;
	}

	/**
	 * Summary: Builds the Kit subscribers endpoint for "all" or one tag.
	 *
	 * @access public
	 *
	 * @param string $tag_id The Kit tag ID, or "all" (or empty) for every subscriber.
	 * @return string Returns the endpoint path.
	 * @since 1.32.0
	 */
	public static function get_kit_subscribers_endpoint( string $tag_id ) {
		return ( '' === $tag_id || 'all' === $tag_id ) ? 'subscribers' : 'tags/' . rawurlencode( $tag_id ) . '/subscribers';
	}

	/**
	 * Summary: Counts the active Kit subscribers, optionally with one tag.
	 *
	 * @access public
	 *
	 * @param string $api_key The Kit v4 API key.
	 * @param string $tag_id  The Kit tag ID, or "all" for every subscriber.
	 *
	 * @return array|int Returns the subscriber count, or an error array with 'status' and 'message'.
	 * @since 1.32.0
	 */
	public static function get_kit_subscriber_total( string $api_key, string $tag_id ) {
		$response = self::get_kit_response(
			$api_key,
			self::get_kit_subscribers_endpoint( $tag_id ),
			array(
				'status'              => 'active',
				'per_page'            => 1,
				'include_total_count' => 'true',
			)
		);

		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return $response;
		}

		return isset( $response['pagination']['total_count'] ) ? (int) $response['pagination']['total_count'] : 0;
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
	 * Drop mapped fields that should not overwrite an existing contact's data.
	 *
	 * A mapping opts out with `'overwrite' => false`; mappings without the flag
	 * keep overwriting, so older import screens behave as before.
	 *
	 * @access public
	 *
	 * @param array $contact_args Prepared contact arguments.
	 * @param array $params       Import request parameters containing the field map.
	 * @return array Contact arguments without the non-overwritable fields.
	 *
	 * @since 1.31.5
	 */
	public static function remove_non_overwritable_fields( $contact_args, $params ) {
		$mappings = isset( $params['map'] ) && is_array( $params['map'] ) ? $params['map'] : array();

		foreach ( $mappings as $map ) {
			if ( ! isset( $map['overwrite'] ) || rest_sanitize_boolean( $map['overwrite'] ) ) {
				continue;
			}

			$target = isset( $map['target'] ) ? $map['target'] : '';

			if ( in_array( $target, array( 'email', 'lists', 'tags' ), true ) ) {
				continue;
			}

			// Status is a top-level contact field when mapped (MailPoet import).
			if ( in_array( $target, array( 'first_name', 'last_name', 'status' ), true ) ) {
				unset( $contact_args[ $target ] );
			} else {
				unset( $contact_args['meta_fields'][ $target ] );
			}
		}

		return $contact_args;
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

		// If no course IDs are provided, get all Tutor LMS courses.
		if (!$course_ids) {
			$all_courses = HelperFunctions::get_tutor_lms_courses();
			$course_ids  = array_column($all_courses, 'value');
		}

		// Sanitize course IDs to ensure they are integers
		$course_ids = array_map('intval', $course_ids);

		if (empty($course_ids)) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'posts';

		// Create placeholders for IN clause
		$placeholders = implode(', ', array_fill(0, count($course_ids), '%d'));

		// Prepare safe query with placeholders
		$enrollments_query = $wpdb->prepare(
			"SELECT DISTINCT post_author FROM $table_name WHERE post_type = 'tutor_enrolled' AND post_parent IN ($placeholders) LIMIT %d OFFSET %d",
			array_merge($course_ids, array($number, $offset))
		);

		// Prepare safe total query with placeholders
		$total_query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT post_author) FROM $table_name WHERE post_type = 'tutor_enrolled' AND post_parent IN ($placeholders)",
			$course_ids
		);

		$total = $wpdb->get_var($total_query); //phpcs:ignore

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

		// Sanitize course IDs to ensure they are integers
		$course_ids = array_map('intval', $course_ids);
		
		if (empty($course_ids)) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		global $wpdb;

		$table_name  = $wpdb->prefix . 'lifterlms_user_postmeta';
		$users_table = $wpdb->prefix . 'users';
		
		// Create placeholders for the IN clause
		$placeholders = implode(', ', array_fill(0, count($course_ids), '%d'));
		
		// Prepare the query with placeholders
		$query = $wpdb->prepare(
			"SELECT u.ID as id, (
			SELECT meta_value
			FROM {$table_name}
			WHERE meta_key = '_status'
			AND user_id = id
			AND post_id IN ($placeholders)
			ORDER BY updated_date DESC
			LIMIT %d OFFSET %d ) AS status
			FROM $users_table as u
			HAVING status IS NOT NULL
			AND status = 'enrolled'",
			array_merge($course_ids, array($number, $offset))
		);
		
		$users = $wpdb->get_results($query, ARRAY_A);
		
		// Prepare the count query with placeholders
		$total_query = $wpdb->prepare(
			"SELECT COUNT(*) AS enrolled_count
			FROM (
				SELECT u.ID as id
				FROM $users_table as u
				WHERE EXISTS (
					SELECT 1
					FROM $table_name
					WHERE meta_key = '_status'
					AND user_id = u.ID
					AND post_id IN ($placeholders)
					AND meta_value = 'enrolled'
					ORDER BY updated_date DESC
				)
			) AS enrolled_users",
			$course_ids
		);
		
		$total = $wpdb->get_var($total_query);

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
	 * Get WordPress users by BuddyPress/BuddyBoss with limit and offset.
	 *
	 * Retrieves WordPress users associated with the selected BuddyPress/BuddyBoss groups or
	 * member types based on the provided parameters.
	 *
	 * @param string $import_type The import dimension, either 'member_group' or 'member_type'.
	 * @param array  $selection   An array of selected groups or member types ({ value, label }).
	 * @param int    $number      The number of users to retrieve (default is 5).
	 * @param int    $offset      The offset for batch processing (default is 0).
	 * @return array An array containing formatted user data and the total number of users.
	 * @access public
	 * @since 1.32.0
	 */
	public static function get_wp_users_by_buddypress_with_limit_offset( $import_type, $selection, $number = 5, $offset = 0 ) {
		if ( ! HelperFunctions::is_buddypress_active() ) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		if ( 'member_type' === $import_type ) {
			$result = self::get_buddypress_user_ids_by_member_types( $selection, $number, $offset );
		} else {
			$result = self::get_buddypress_user_ids_by_groups( $selection, $number, $offset );
		}

		$user_ids = $result['user_ids'];
		$total    = $result['total'];

		if ( empty( $user_ids ) ) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		$contacts = get_users( array( 'include' => $user_ids ) );

		if ( empty( $contacts ) ) {
			return array(
				'formatted_users' => array(),
				'total_users'     => 0,
			);
		}

		// Format user data, including usermeta information.
		$formatted_users = array_map(
			function ( $user ) {
				$user->usermeta = array_map(
					function ( $user_data ) {
						return reset( $user_data );
					},
					get_user_meta( $user->ID )
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
	 * Get paginated BuddyPress/BuddyBoss user IDs belonging to the selected groups.
	 *
	 * @param array $groups An array of selected groups ({ value, label }). Empty means all groups.
	 * @param int   $number The number of user IDs to retrieve.
	 * @param int   $offset The offset for batch processing.
	 * @return array An array containing 'user_ids' and 'total'.
	 * @access private
	 * @since 1.32.0
	 */
	private static function get_buddypress_user_ids_by_groups( $groups, $number, $offset ) {
		global $wpdb;

		$group_ids  = array_map( 'intval', array_column( $groups, 'value' ) );
		$table_name = $wpdb->prefix . 'bp_groups_members';

		if ( ! empty( $group_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $group_ids ), '%d' ) );
			$total        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT user_id ) FROM {$table_name} WHERE is_confirmed = 1 AND group_id IN ($placeholders)", $group_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$user_ids     = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$table_name} WHERE is_confirmed = 1 AND group_id IN ($placeholders) ORDER BY user_id ASC LIMIT %d OFFSET %d", array_merge( $group_ids, array( $number, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		} else {
			$total    = (int) $wpdb->get_var( "SELECT COUNT( DISTINCT user_id ) FROM {$table_name} WHERE is_confirmed = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$table_name} WHERE is_confirmed = 1 ORDER BY user_id ASC LIMIT %d OFFSET %d", $number, $offset ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return array(
			'user_ids' => array_map( 'intval', (array) $user_ids ),
			'total'    => $total,
		);
	}

	/**
	 * Get paginated BuddyPress/BuddyBoss user IDs belonging to the selected member types.
	 *
	 * @param array $member_types An array of selected member types ({ value, label }). Empty means all types.
	 * @param int   $number       The number of user IDs to retrieve.
	 * @param int   $offset       The offset for batch processing.
	 * @return array An array containing 'user_ids' and 'total'.
	 * @access private
	 * @since 1.32.0
	 */
	private static function get_buddypress_user_ids_by_member_types( $member_types, $number, $offset ) {
		$type_slugs = array_column( $member_types, 'value' );

		if ( empty( $type_slugs ) ) {
			$type_slugs = array_keys( (array) bp_get_member_types() );
		}

		if ( empty( $type_slugs ) || ! function_exists( 'bp_get_term_by' ) || ! function_exists( 'bp_get_objects_in_term' ) ) {
			return array(
				'user_ids' => array(),
				'total'    => 0,
			);
		}

		$term_ids = array();
		foreach ( $type_slugs as $slug ) {
			$term = bp_get_term_by( 'slug', $slug, bp_get_member_type_tax_name() );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = $term->term_id;
			}
		}

		if ( empty( $term_ids ) ) {
			return array(
				'user_ids' => array(),
				'total'    => 0,
			);
		}

		$user_ids = bp_get_objects_in_term( $term_ids, bp_get_member_type_tax_name() );
		if ( is_wp_error( $user_ids ) || empty( $user_ids ) ) {
			return array(
				'user_ids' => array(),
				'total'    => 0,
			);
		}

		$user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );

		return array(
			'user_ids' => array_slice( $user_ids, $offset, $number ),
			'total'    => count( $user_ids ),
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
