<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app
 */

namespace MRM\Common;

use DateTime;
use DateTimeZone;
use MailMint\App\Helper;
use MailMintPro\Mint\Internal\Admin\Segmentation\FilterSegmentContacts;
use Mint\MRM\Constants;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\CustomFieldModel;
use Mint\MRM\DataBase\Tables\ContactSchema;
use Mint\MRM\Utilites\Helper\Import;
use WC_Customer;
use WC_Order;
use WP_REST_Request;

/**
 * [Manage Common mrm function ]
 *
 * @desc Manages Common function in mrm
 * @package /app/Internal/Ajax
 * @since 1.0.0
 */
class MrmCommon {

	/**
	 * Returns alphanumeric hash
	 *
	 * @param string $email get email .
	 * @param mixed  $len  get lengh .
	 *
	 * @return string
	 */
	public static function get_rand_hash( $email, $len = 32 ) {
		return substr( md5( $email ), -$len );
	}


	/**
	 * Returns request query params or body values
	 *
	 * @param  WP_REST_Request $request Get Request type .
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_api_params_values( WP_REST_Request $request ) {
		if ( $request->sanitize_params() ) {
			$query_params   = $request->get_query_params();
			$request_params = $request->get_params();

			return array_replace( $query_params, $request_params );
		}

		return array();
	}

	/**
	 * Prepares and returns request parameters from a WP_REST_Request object.
	 *
	 * This function prepares and returns an array of request parameters from the provided WP_REST_Request object.
	 *
	 * @access public
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 * @return array An array containing the request parameters.
	 * @since 1.5.1
	 */
	public static function prepare_request_params( WP_REST_Request $request ) {
		if ( $request->sanitize_params() ) {
			$params = array();

			$method = $request->get_method();

			if ( 'GET' === $method ) {
				$params = $request->get_params();
			} elseif ( 'POST' === $method ) {
				$body_params = $request->get_body_params();
				$file_params = $request->get_file_params();
				$json_params = $request->get_json_params();

				$params = array_merge(
					$body_params ? $body_params : array(),
					$file_params ? $file_params : array(),
					$json_params ? $json_params : array()
				);
			}

			return $params;
		}

		return array();
	}

	/**
	 * Return created by or author id
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_current_user_id() {
		if ( is_user_logged_in() ) {
			return get_current_user_id();
		}
		return get_current_user_id();
	}


	/**
	 * Get the list of CSV mime types.
	 *
	 * This function returns an array of mime types that are associated with CSV files.
	 *
	 * @access public
	 *
	 * @return array An array of CSV mime types.
	 * @since 1.5.1
	 */
	public static function csv_mimes() {
		/**
		 * Apply a filter to customize the supported CSV mime types.
		 *
		 * This function applies the 'mint_csv_mimes' filter hook, allowing other parts of the code
		 * to modify the array of CSV mime types before it is used to determine valid CSV file types.
		 *
		 * @return array An array containing various CSV mime types that are recognized as valid.
		 * @since 1.5.1
		 */
		return apply_filters(
			'mint_csv_mimes',
			array(
				'text/csv',
				'text/plain',
				'application/csv',
				'text/comma-separated-values',
				'application/excel',
				'application/vnd.ms-excel',
				'application/vnd.msexcel',
				'text/anytext',
				'application/octet-stream',
				'application/txt',
			)
		);
	}


	/**
	 * Create a slug from a string
	 *
	 * @param mixed $str get string .
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function create_slug( $str ) {
		return preg_replace( '/[^A-Za-z0-9-]+/', '-', $str );
	}


	/**
	 * Sanitize global variables
	 *
	 * @param array $data An optional array of data to sanitize.
	 *
	 * @return array An array containing sanitized data from $_GET, $_POST, $_COOKIE, and $_SERVER.
	 * @since 1.0.0
	 * @modified 1.8.2 Use htmlspecialchars function instead of deprecated FILTER_SANITIZE_STRING
	 */
	public static function get_sanitized_get_post( $data = array() ) {
		if ( is_array( $data ) && ! empty( $data ) ) {
			return array_map(
				function( $item ) {
					return is_string( $item ) ? htmlspecialchars( $item, ENT_QUOTES, 'UTF-8' ) : $item;
				},
				$data
			);
		}

		$get    = filter_input_array( INPUT_GET, FILTER_DEFAULT );
		$post   = filter_input_array( INPUT_POST, FILTER_DEFAULT );
		$cookie = filter_input_array( INPUT_COOKIE, FILTER_DEFAULT );
		$server = filter_input_array( INPUT_SERVER, FILTER_DEFAULT );

		return array(
			'get'    => is_array( $get ) ? array_map(
				function( $item ) {
					return is_string( $item ) ? htmlspecialchars( $item, ENT_QUOTES, 'UTF-8' ) : $item;
				},
				$get
			) : array(),
			'post'   => is_array( $post ) ? array_map(
				function( $item ) {
					return is_string( $item ) ? htmlspecialchars( $item, ENT_QUOTES, 'UTF-8' ) : $item;
				},
				$post
			) : array(),
			'cookie' => is_array( $cookie ) ? array_map(
				function( $item ) {
					return is_string( $item ) ? htmlspecialchars( $item, ENT_QUOTES, 'UTF-8' ) : $item;
				},
				$cookie
			) : array(),
			'server' => is_array( $server ) ? array_map(
				function( $item ) {
					return is_string( $item ) ? htmlspecialchars( $item, ENT_QUOTES, 'UTF-8' ) : $item;
				},
				$server
			) : array(),
		);
	}


	/**
	 * Partially hide or mask email address
	 *
	 * @param mixed $email  get email address .
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function obfuscate_email( $email ) {
		$em   = explode( '@', $email );
		$name = implode( '@', array_slice( $em, 0, count( $em ) - 1 ) );
		$len  = floor( strlen( $name ) / 2 );

		return substr( $name, 0, $len ) . str_repeat( '*', $len ) . '@' . end( $em );
	}

	/**
	 * Get the page id by a page slug
	 *
	 * @param mixed $page_slug  Page slug to get id.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_page_id_by_slug( $page_slug ) {
		$page = get_page_by_path( $page_slug );
		if ( $page ) {
			return $page->ID;
		} else {
			return null;
		}
	}


	/**
	 * Get the page id by a page slug
	 *
	 * @param mixed $page_slug  Page slug to get id.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_page_title_by_slug( $page_slug ) {
		$page = get_page_by_path( $page_slug );
		if ( $page ) {
			return $page->post_title;
		} else {
			return null;
		}
	}
	/**
	 * Check List and lage are exist or not
	 *
	 * @param array  $data Get Setting data.
	 * @param string $type Get Group Type.
	 *
	 * @return array
	 */
	public static function is_list_exist( $data, $type ) {
		$all_list = ContactGroupModel::get_all_lists_or_tags( $type );
		$result   = array();
		foreach ( $data as $key => $list ) {
			$result[] = self::search_for_id( $list['id'], $all_list );
		}
		return $result;
	}

	/**
	 * Helper Function for List  Check ID value.
	 *
	 * @param int   $id Get ID form LIST.
	 * @param array $array Get List data.
	 *
	 * @return array|null
	 */
	public static function search_for_id( $id, $array ) {
		foreach ( $array as $key => $val ) {
			if ( $val['id'] === $id ) {
				$data = array(
					'id'    => $val['id'],
					'title' => $val['title'],
				);
				return $data;
			}
		}
		return null;
	}
	/**
	 * Get Mint Created Page
	 *
	 * @param string $slug Set Page Slug.
	 *
	 * @return int
	 */
	public static function get_mint_page_id( $slug ) {
		if ( get_page_by_path( $slug, OBJECT ) ) {
			$page = get_page_by_path( $slug, OBJECT );
			return $page->ID;
		}
		return false;
	}

	/**
	 * Get default Preference Page.
	 *
	 * @return false|string
	 */
	public static function get_default_preference_page_id_title() {
		$settings              = get_option( '_mrm_general_preference' ); //phpcs:ignore
		if ( isset( $settings['preference_page_id'] ) ) {
			$page_id = isset( $settings['preference_page_id'] ) ? $settings['preference_page_id'] : '';
			return get_permalink( $page_id );
		} else {
			$page_id = self::get_mint_page_id( 'preference_page' );
			return get_permalink( $page_id );
		}
	}

	/**
	 * Simple check for validating a URL, it must start with http:// or https://.
	 * and pass FILTER_VALIDATE_URL validation.
	 *
	 * @param  string $url to check.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_valid_url( $url ) {

		// Must start with http:// or https://.
		if ( 0 !== strpos( $url, 'http://' ) && 0 !== strpos( $url, 'https://' ) ) {
			return false;
		}

		// Must pass validation.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Function used to prepare double opt-in default configuration
	 *
	 * @return array
	 * @since 1.0.0
	 * @since 1.10.3 Added editor_type and json_data to the response.
	 */
	public static function double_optin_default_configuration() {
		return array(
			'enable'            => true,
			'email_subject'     => 'Confirm your subscription to {{site_title}}',
			'preview_text'      => 'Confirm your subscription to {{site_title}} for exclusive updates and offers.',
			'email_body'        => '<p>Hello,</p>
			<p>You&#039;ve received this message because you subscribed to {{site_title}}. Please confirm your subscription to receive emails from us:</p>
			<p>Click <a href="{{subscribe_link}}" data-wplink-url-error="true">here</a> to confirm your subscription.</p>
			<p>If you received this email by mistake, simply delete it. You won&#039;t receive any more emails from us unless you confirm your subscription.</p>
			<p>Thank you,</p>
            <p>{{site_title}}</p>',
			'confirmation_type' => 'redirect-page',
			'page_id'           => self::get_page_id_by_slug( 'optin_confirmation' ),
			'page_title'        => self::get_page_title_by_slug( 'optin_confirmation' ),
			'editor_type'       => 'classic-editor',
			'json_data'         => array(),
		);
	}


	/**
	 * Function used to prepare reCAPTCHA default configuration
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function recaptcha_default_configuration() {
		return array(
			'enable'       => false,
			'v3_invisible' => array(
				'site_key'   => '',
				'secret_key' => '',
			),
			'v2_visible'   => array(
				'site_key'   => '',
				'secret_key' => '',
			),
			'api_version'  => 'v2_visible',
		);
	}

	/**
	 * Verify reCaptcha response.
	 *
	 * @param string $token response from the user.
	 *
	 * @return bool
	 * @since 1.1.0
	 */
	public static function get_recaptcha_response( $token ) {
		$verify_url = 'https://www.google.com/recaptcha/api/siteverify';

		$settings = get_option( '_mint_recaptcha_settings' );
		$version  = isset( $settings['api_version'] ) ? $settings['api_version'] : '';
		$version  = isset( $settings['api_version'] ) ? $settings['api_version'] : '';

		$v3_secret_key = isset( $settings['v3_invisible']['secret_key'] ) ? $settings['v3_invisible']['secret_key'] : array();
		$v2_secret_key = isset( $settings['v2_visible']['secret_key'] ) ? $settings['v2_visible']['secret_key'] : array();

		$response = wp_remote_post(
			$verify_url,
			array(
				'method' => 'POST',
				'body'   => array(
					'secret'   => 'v3_invisible' === $version ? $v3_secret_key : $v2_secret_key,
					'response' => $token,
				),
			)
		);

		$is_valid = false;

		if ( ! is_wp_error( $response ) ) {
			$result = json_decode( wp_remote_retrieve_body( $response ) );
			if ( 'v3_invisible' === $version && $result->success ) {
				$score       = $result->score;
				$check_score = apply_filters( 'mail_mint_recaptcha_v3_ref_score', 0.5 );
				$is_valid    = $score >= $check_score;
			} else {
				$is_valid = $result->success;
			}
		}

		return $is_valid;
	}


	/**
	 * Function used to prepare business settings default configuration
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function business_settings_default_configuration() {
		$business_name = get_bloginfo() ;	
		return array(
			'business_name'    => $business_name ? html_entity_decode( $business_name, ENT_QUOTES ) : '',
			'phone'            => '',
			'business_address' => array(
				'address_line_1' => '',
				'postal'         => '',
				'address_line_2' => '',
				'country'        => '',
				'state'          => '',
			),
			'logo_url'         => '',
		);
	}


	/**
	 * Function used to check is double opt-in enable or disable
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function is_double_optin_enable() {
		$default  = self::double_optin_default_configuration();
		$settings = get_option( '_mrm_optin_settings', $default );
		$settings = is_array( $settings ) && ! empty( $settings ) ? $settings : $default;
		return isset( $settings['enable'] ) && ! empty( $settings['enable'] ) ? true : false;
	}


	/**
	 * Function used to prepare server domain link
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_domain_link() {
		$sanitize_server = self::get_sanitized_get_post();
		$sanitize_server = !empty( $sanitize_server[ 'server' ] ) ? $sanitize_server[ 'server' ] : array();
		$server          = !empty( $sanitize_server['SERVER_PROTOCOL'] ) ? $sanitize_server['SERVER_PROTOCOL'] : '';
		$protocol        = strpos( strtolower( $server ), 'https' ) === false ? 'http' : 'https';
		return $protocol . '://' . $sanitize_server['HTTP_HOST'];
	}

	/**
	 * Returns alphanumeric hash for email
	 *
	 * @param string $email email address.
	 * @param mixed  $id  campaign or automation id.
	 *
	 * @return string
	 */
	public static function get_rand_email_hash( $email, $id ) {
		$rand_hash  = substr( md5( openssl_random_pseudo_bytes( $id ) ), -16 );
		$email_hash = substr( md5( $email ), -16 );
		return $rand_hash . $email_hash;
	}

	/**
	 * Gets unique value from a multidimensional array
	 *
	 * @param array  $array Array data.
	 * @param string $key Array key.
	 *
	 * @return array
	 */
	public static function array_multidim_unique( $array, $key ) {
		$i          = 0;
		$temp_array = array();
		$key_array  = array();

		foreach ( $array as $val ) {
			if ( ! in_array( $val[ $key ], $key_array, true ) ) {
				$key_array[ $i ]  = $val[ $key ];
				$temp_array[ $i ] = $val;
			}
			$i ++;
		}

		return $temp_array;
	}


	/**
	 * Get user's IP address
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public static function get_user_ip() {
		$server = self::get_sanitized_get_post();
		$server = !empty( $server[ 'server' ] ) ? $server[ 'server' ] : array();

		if ( isset( $server[ 'HTTP_CLIENT_IP' ] ) && $server[ 'HTTP_CLIENT_IP' ] ) {
			return wp_unslash( $server[ 'HTTP_CLIENT_IP' ] );
		} elseif ( isset( $server[ 'HTTP_X_FORWARDED_FOR' ] ) && $server[ 'HTTP_X_FORWARDED_FOR' ] ) {
			return wp_unslash( $server[ 'HTTP_X_FORWARDED_FOR' ] );
		} elseif ( isset( $server[ 'HTTP_X_FORWARDED' ] ) && $server[ 'HTTP_X_FORWARDED' ] ) {
			return wp_unslash( $server[ 'HTTP_X_FORWARDED' ] );
		} elseif ( isset( $server[ 'HTTP_FORWARDED_FOR' ] ) && $server[ 'HTTP_FORWARDED_FOR' ] ) {
			return wp_unslash( $server[ 'HTTP_FORWARDED_FOR' ] );
		} elseif ( isset( $server[ 'HTTP_FORWARDED' ] ) && $server[ 'HTTP_FORWARDED' ] ) {
			return wp_unslash( $server[ 'HTTP_FORWARDED' ] );
		} elseif ( isset( $server[ 'REMOTE_ADDR' ] ) && $server[ 'REMOTE_ADDR' ] ) {
			return wp_unslash( $server[ 'REMOTE_ADDR' ] );
		}
		return __( 'UNKNOWN', 'mrm' );
	}


	/**
	 * Check if wc is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function is_wc_active() {
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { //phpcs:ignore
			return true;
		} elseif ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				return true;
			}
		}
		return false;
	}


	/**
	 * Check if wc is installed
	 *
	 * @return bool
	 * @since  1.0.0
	 * @since 1.10.7 Added EDD Pro plugin check.
	 */
	public static function is_edd_active() {
		$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
		if (in_array('easy-digital-downloads/easy-digital-downloads.php', $active_plugins) || 
			in_array('easy-digital-downloads-pro/easy-digital-downloads.php', $active_plugins)) { //phpcs:ignore
			return true;
		} elseif (function_exists('is_plugin_active')) {
			if (is_plugin_active('easy-digital-downloads/easy-digital-downloads.php') || 
				is_plugin_active('easy-digital-downloads-pro/easy-digital-downloads.php')) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get woocommerce customer id by Email.
	 *
	 * @param string $email email.
	 * @return int
	 * @since  1.0.0
	 */
	public static function get_customer_id_by_email( $email ) {
		if ( empty( $email ) ) {
			return;
		}

		// Global.
		global $wpdb;

		// Result, select from "wc_customer_lookup".
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT customer_id, date_last_active FROM {$wpdb->prefix}wc_customer_lookup WHERE email = %s", $email ), ARRAY_A ); //phpcs:ignore
		// NOT empty.
		if ( ! empty( $result ) ) {
			return $result;
		}

		return array();
	}

	/**
	 * Summary: Retrieves orders based on customer ID and optional time filter.
	 *
	 * Description: This static method retrieves orders associated with a specific customer ID.
	 * Optionally, a time filter ('lifetime', 'month', or 'year') can be applied to narrow down the orders based on their creation date.
	 *
	 * @access public
	 * @static
	 *
	 * @param string $email  The email address of the customer for which orders are to be retrieved.
	 * @param string $filter The time period filter for retrieving orders ('lifetime', 'month', or 'year'). Default is 'lifetime'.
	 *
	 * @return array Returns an array containing order details for the specified customer ID within the specified time period and with certain order statuses.
	 *
	 * @since 1.0.0
	 * @modified 1.7.2 Use email address instead of customer ID.
	 */
	public static function get_orders_by_customer_email( $email, $filter = 'lifetime' ) {
		if ( empty( $email ) || !self::is_wc_active() ) {
			return array();
		}

		$last_order_date = '';

		// Apply date filter based on $filter parameter.
		if ( 'month' === $filter ) {
			$last_order_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		} elseif ( 'year' === $filter ) {
			$last_order_date = date( 'Y-m-d', strtotime( '-1 year' ) );
		}

		$args = array(
			'customer'   => $email,
			'status'     => array( 'wc-processing', 'wc-completed' ),
			'date_after' => $last_order_date,
			'limit'      => -1,
		);

		$customer_orders = wc_get_orders( $args );
		if ( empty( $customer_orders ) ) {
			return array();
		}

		$formatted_orders = array();

		foreach ( $customer_orders as $order ) {
			$formatted_orders[] = array(
				'total_amount' => $order->get_total(),
			);
		}
		return $formatted_orders;
	}


	/**
	 * Set a cookie - wrapper for setcookie using WP constants.
	 *
	 * @param string  $name Name of the cookie being set.
	 * @param string  $value Value of the cookie.
	 * @param integer $expire Expiry of the cookie.
	 * @param bool    $secure Whether the cookie should be served only over https.
	 * @since 1.0.0
	 */
	public static function set_cookie( $name, $value, $expire = 0, $secure = false ) {
		if ( headers_sent() ) {
			return;
		}
		setcookie( $name, $value, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, $secure ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
	}

	/**
	 * Format date and time based on WP core settings
	 *
	 * @param mixed $date_time mail mint date and time.
	 * @return string
	 * @since 1.0.0
	 */
	public static function date_time_format_with_core( $date_time ) {
		$date_time_at = new \DateTimeImmutable( $date_time, wp_timezone() );
		$date_format  = get_option( 'date_format' );
		$time_format  = get_option( 'time_format' );
		return $date_time_at->format( $date_format . ' ' . $time_format );
	}


	/**
	 * Return customer email address from order
	 *
	 * @param mixed $order_id WooCommerce order id.
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_email_from_wc_order( $order_id ) {
		$is_wc_active = self::is_wc_active();

		if ( $is_wc_active ) {
			$order = wc_get_order( $order_id );
			// Get the customer ID.
			if ( $order ) {
				return $order->get_billing_email();
			}
			return false;
		}
	}


	/**
	 * Business Setting address Formate for email.
	 *
	 * @param array $setting Get business Setting data.
	 * @return string
	 */
	public static function business_address_formate_for_email( $setting ) {
		$address_line_1 = !empty( $setting[ 'business_address' ]['address_line_1'] ) ? $setting[ 'business_address' ]['address_line_1'] : '';
		$address_line_2 = !empty( $setting[ 'business_address' ]['address_line_2'] ) ? ', ' . $setting[ 'business_address' ]['address_line_2'] : '';
		$city           = !empty( $setting[ 'business_address' ]['city'] ) ? ', ' . $setting[ 'business_address' ]['city'] : '';
		$state          = !empty( $setting[ 'business_address' ]['state'] ) ? '<br>' . $setting[ 'business_address' ]['state'] : '';
		$postal         = !empty( $setting[ 'business_address' ]['postal'] ) ? ', ' . $setting[ 'business_address' ]['postal'] : '';
		$country        = !empty( $setting[ 'business_address' ]['country'] ) ? ', ' . $setting[ 'business_address' ]['country'] : '';
		return $address_line_1 . $address_line_2 . $city . $state . $postal . $country;
	}

	/**
	 * Check if Mail Mint Pro is active
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public static function is_mailmint_pro_active() {
		return apply_filters( 'is_mail_mint_pro_active', false );
	}

	/**
	 * Check if Mail Mint Pro license is active
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public static function is_mailmint_pro_license_active() {
		return apply_filters( 'is_mail_mint_pro_license_active', false );
	}

	/**
	 * Format price with WooCommerce currency setting
	 *
	 * @param mixed $price Revenue price to concate with WooCoomerce sysbol.
	 * @return string
	 * @since 1.0.0
	 */
	public static function price_format_with_wc_currency( $price ) {
		$is_acive = self::is_wc_active();
		$format   = '';
		if ( $is_acive ) {
			$symbol             = html_entity_decode( get_woocommerce_currency_symbol() ); //phpcs:ignore
			$currency_pos       = get_option( 'woocommerce_currency_pos', 'left' );
			$minor_unit         = wc_get_price_decimals();
			$decimal_separator  = wc_get_price_decimal_separator();
			$thousand_separator = wc_get_price_thousand_separator();

			$price = number_format( (float) ( $price ), $minor_unit, $decimal_separator, $thousand_separator );

			switch ( $currency_pos ) {
				case 'left_space':
					$format = $symbol . ' ' . $price;
					break;
				case 'left':
					$format = $symbol . $price;
					break;
				case 'right_space':
					$format = $price . ' ' . $symbol;
					break;
				case 'right':
					$format = $price . $symbol;
					break;
			}
		}
		return $format;
	}

	/**
	 * Check email footer watermark settings option.
	 *
	 * @return bool
	 */
	public static function is_footer_watermark() {
		$condition = get_option( '_mrm_general_footer_watermark', 'yes' );

		if ( 'yes' === $condition ) {
			return true;
		}
		return false;
	}


	/**
	 * Return 24 hours list
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_last_day_hours() {
		$current_time = date_i18n( get_option( 'time_format' ) );
		$current_hour = date( 'H', strtotime( $current_time ) );

		// Create an array to store hourly values.
		$hour_array = array();

		// Loop through 24 hours and initialize values to 0.
		$current_hour = intval( $current_hour );

		// Loop through the last 24 hours and create array keys.
		for ( $i = 0; $i < 24; $i++ ) {
			// Calculate the hour for the current iteration.
			$hour = ( $current_hour - $i + 24 ) % 24;

			// Determine whether it's AM or PM.
			$am_pm = ( $hour < 12 ) ? 'AM' : 'PM';

			// Adjust the hour if it's 0 to represent 12 AM.
			$hour = ( $hour == 0 ) ? 12 : $hour;

			// Format the hour with leading zeros if it's a single digit.
			$formatted_hour = sprintf( '%02d', $hour );

			// Combine the hour and AM/PM and add to the array as a key.
			$hour_array[ $formatted_hour . ' ' . $am_pm ] = 0;
		}

		return $hour_array;
	}


	/**
	 * Get action action scheduler group id by group slug
	 *
	 * @param string $slug Actions scheduler group slug.
	 *
	 * @return int|string|null
	 *
	 * @since 1.0.0
	 */
	public static function get_as_group_id( string $slug ) {
		if ( empty( $slug ) ) {
			return 0;
		}
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT `group_id` FROM {$wpdb->actionscheduler_groups} WHERE `slug` = %s", $slug ) ); //phpcs:ignore
	}

	/**
	 * Delete completed actions [from Mail Mint] by action schedulers
	 *
	 * @param string $slug Action scheduler group slug.
	 * @param string $status Action scheduler status.
	 *
	 * @return bool|int|\mysqli_result|resource|null
	 *
	 * @since 1.0.0
	 */
	public static function delete_as_actions( string $slug, $status = '' ) {
		if ( empty( $slug ) ) {
			return false;
		}

		$group_id = self::get_as_group_id( $slug );

		if ( empty( $group_id ) ) {
			return false;
		}

		global $wpdb;

		$query = $wpdb->prepare( "DELETE FROM {$wpdb->actionscheduler_actions} WHERE `group_id` = %d", $group_id );

		if ( $status ) {
			$query .= $wpdb->prepare( ' AND `status` = %s', $status );
		}

		return $wpdb->query( $query ); //phpcs:ignore
	}

	/**
	 * Check if a action already exists
	 *
	 * @param string $hook Hook.
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	public static function mailmint_as_has_scheduled_action( $hook ) {
		global $wpdb;
		$query  = "SELECT `action_id` FROM {$wpdb->actionscheduler_actions} ";
		$query .= 'WHERE `hook` = %s ';
		$query .= 'AND `status` = %s ';
		$query .= 'ORDER BY `action_id` ASC ';
		$query .= 'LIMIT 1';

		return $wpdb->get_var( $wpdb->prepare( $query, $hook, 'pending' ), ARRAY_A ); //phpcs:ignore
	}

	/**
	 * Format campaign date and time based on WP core settings
	 *
	 * @param mixed $date_time campaign array key.
	 * @param mixed $campaign campaign array.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function format_campaign_date_time( $date_time, $campaign ) {
		$timezone    = wp_timezone();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		if ( array_key_exists( $date_time, $campaign ) ) {
			if ( isset( $campaign[ $date_time ] ) ) {
				$time = new \DateTimeImmutable( $campaign[ $date_time ], $timezone );
				return $time->format( $date_format . ' ' . $time_format );
			}
		}
		return '';
	}

	/**
	 * Delete completed Action Scheduler actions by hook and status.
	 *
	 * This function deletes Action Scheduler actions from the database that match the specified hook and status.
	 * It is particularly useful for cleaning up completed actions to manage the size of the database.
	 *
	 * @param string $hook   The hook of the Action Scheduler action.
	 * @param string $status The status of the Action Scheduler action (e.g., 'completed').
	 *
	 * @return false|int|void The number of rows affected or false on error.
	 *
	 * @since 1.5.20
	 */
	public static function delete_completed_action_scheduler( $hook, $status ) {
		global $wpdb;
		$query = $wpdb->prepare( "DELETE FROM {$wpdb->actionscheduler_actions} WHERE `hook` = %s AND `status` = %s", $hook, $status );
		$wpdb->query( $query ); //phpcs:ignore
	}


	/**
	 * Check manager permissions on REST API.
	 *
	 * @param string $permission_label Permission label.
	 *
	 * @return bool
	 * @since  1.0.0
	 */
	public static function rest_check_manager_permissions( $permission_label = 'manage' ) {
		$capability = is_multisite() ? 'delete_sites' : 'manage_options';
		return current_user_can( $capability );
	}

	/**
	 * Add form builder html context for WP_kses
	 *
	 * @return array
	 */
	public static function wp_kseser_for_contact() {
		$allowed_html_post = wp_kses_allowed_html( 'post' );
		$allowed_html      = array(
			'input'  => array(
				'type'        => array(),
				'name'        => array(),
				'id'          => array(),
				'placeholder' => array(),
				'required'    => array(),
				'style'       => array(),
				'pattern'     => array(),
				'value'       => array(),
			),
			'select' => array(
				'name'  => true,
				'id'    => true,
				'style' => true,
				'required' => array()
			),
			'option' => array(
				'value' => true,
			),
			'script' => array(
				'src' => array(),
			),
			'textarea' => array(
				'name'     => array(),
				'id'       => array(),
				'class'    => array(),
				'rows'     => array(),
				'cols'     => array(),
				'required' => array(),
			),
		);

		return array_merge( $allowed_html_post, $allowed_html );
	}

	/**
	 * Filters selected group ids [in different modules]
	 * if they still exist
	 *
	 * @param array  $recipients Recipients array.
	 * @param string $status Campaign status.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public static function filter_recipients( $recipients, $status ) {
		$group_ids   = ContactGroupModel::get_all_group_ids();
		$subscribers = 0;
		if ( !empty( $recipients[ 'lists' ] ) ) {
			foreach ( $recipients[ 'lists' ] as $index => $list ) {
				if ( !empty( $list[ 'id' ] ) && !in_array( $list[ 'id' ], $group_ids, true ) ) {
					unset( $recipients[ 'lists' ][ $index ] );
				}
			}
			$recipients[ 'lists' ] = array_values( $recipients[ 'lists' ] );
		}
		if ( !empty( $recipients[ 'tags' ] ) ) {
			foreach ( $recipients[ 'tags' ] as $index => $tag ) {
				if ( !empty( $tag[ 'id' ] ) && !in_array( $tag[ 'id' ], $group_ids, true ) ) {
					unset( $recipients[ 'tags' ][ $index ] );
				}
			}
			$recipients[ 'tags' ] = array_values( $recipients[ 'tags' ] );
		}
		if ( !empty( $recipients[ 'segments' ] ) ) {
			foreach ( $recipients[ 'segments' ] as $index => $segment ) {
				if ( !empty( $segment[ 'id' ] ) && !in_array( $segment[ 'id' ], $group_ids, true ) ) {
					unset( $recipients[ 'segments' ][ $index ] );
				}
			}
			$recipients[ 'segments' ] = array_values( $recipients[ 'segments' ] );
		}
		if ( !empty( $status ) && 'draft' !== $status ) {
			$list_ids    = array_column( $recipients[ 'lists' ], 'id' );
			$tag_ids     = array_column( $recipients[ 'tags' ], 'id' );
			$segment_ids = array_column( $recipients[ 'segments' ], 'id' );

			if ( ! empty( $segment_ids ) ) {
				$contact_ids = array();

				if ( class_exists( 'MailMintPro\Mint\Internal\Admin\Segmentation\FilterSegmentContacts' ) ) {
					foreach ( $segment_ids as $segment_id ) {
						$segment_data  = FilterSegmentContacts::get_segment( $segment_id );
						$contact_ids[] = !empty( $segment_data[ 'contacts' ][ 'data' ] ) ? array_column( $segment_data[ 'contacts' ][ 'data' ], 'id' ) : array();
					}
				}
				$subscribers = count( array_unique( array_merge( ...array_values( $contact_ids ) ) ) );
			} elseif ( ! empty( $list_ids ) || ! empty( $tag_ids ) ) {
				$subscribers = (int) ContactGroupPivotModel::get_contacts_to_group( array_merge( $list_ids, $tag_ids ), 0, 0, true );
			}
		}

		$recipients['total_recipients'] = $subscribers;
		return $recipients;
	}

	/**
	 * Get WooCommerce customer revenue history
	 *
	 * @param string $email Customer email.
	 * @param array  $contact Mail Mint contact object.
	 *
	 * @return array
	 */
	public static function get_wc_customer_revenue_history( $email, $contact ) {
		if ( empty( $email ) || empty( $contact ) ) {
			return;
		}

		if ( self::is_wc_active() ) {
			$wc_data                    = self::contact_woocommerce_purchase_history( $email );
			$contact [ 'total_orders' ] = ! empty( $wc_data[ 'total_orders' ] ) ? $wc_data[ 'total_orders' ] : 0;
			$contact [ 'total_spent' ]  = ! empty( $wc_data[ 'total_spent' ] ) ? $wc_data[ 'total_spent' ] : 0;

			$divide_by = 0 === $contact [ 'total_orders' ] ? 1 : $contact [ 'total_orders' ];

			$contact['aov']         = self::price_format_with_wc_currency( $contact['total_spent'] / $divide_by );
			$contact['total_spent'] = self::price_format_with_wc_currency( $contact['total_spent'] );
		}

		return $contact;
	}

	/**
	 * Get WooCommerce customer revenue history
	 *
	 * @param string $email Customer email.
	 * @param array  $contact Mail Mint contact object.
	 *
	 * @return array|void
	 */
	public static function get_edd_customer_revenue_history( $email, $contact ) {
		if ( empty( $email ) || empty( $contact ) ) {
			return;
		}

		if ( self::is_edd_active() ) {
			$edd_data                   = self::contact_edd_purchase_history( $email );
			$contact [ 'total_orders' ] = ! empty( $edd_data[ 'total_orders' ] ) ? $edd_data[ 'total_orders' ] : 0;
			$contact [ 'total_spent' ]  = ! empty( $edd_data[ 'total_spent' ] ) ? $edd_data[ 'total_spent' ] : 0;

			$divide_by         = 0 === $contact [ 'total_orders' ] ? 1 : $contact [ 'total_orders' ];
			$contact [ 'aov' ] = number_format( (float) ( $contact [ 'total_spent' ] / $divide_by ), 2, '.', '' );
		}

		return $contact;
	}

	/**
	 * Contact WooCommerce purchase history.
	 *
	 * @param string $email Customer email.
	 *
	 * @return array
	 * @since 1.0.0
	 * @modified 1.7.3 Use email address instead of customer ID to get order details.
	 */
	public static function contact_woocommerce_purchase_history( $email ) {
		$total_spent  = 0;
		$total_orders = 0;

		$order_details = self::get_orders_by_customer_email( $email, 'lifetime' );

		if ( empty( $order_details ) ) {
			return array(
				'total_orders' => $total_orders,
				'total_spent'  => $total_spent,
			);
		}

		foreach ( $order_details as $order ) {
			if ( $order ) {
				$total_spent += $order['total_amount'];
			}
		}

		return array(
			'total_orders' => count( $order_details ),
			'total_spent'  => $total_spent,
		);
	}

	/**
	 * EDD Customer purchase history
	 *
	 * @param string $email Customer email.
	 *
	 * @return array|int[]
	 */
	public static function contact_edd_purchase_history( $email ) {
		if ( !$email ) {
			return array(
				'total_orders' => 0,
				'total_spent'  => 0,
			);
		}

		if ( self::is_edd_active() ) {
			$customer = new \EDD_Customer( $email );
			// Get Currency.
			$edd_currency        = edd_get_currency();
			$edd_currency_symbol = edd_currency_symbol( $edd_currency );

			return array(
				'total_orders' => ! empty( $customer->purchase_count ) ? $customer->purchase_count : 0, // Get the customer's order count.
				'total_spent'  => ! empty( $customer->purchase_value ) ? $customer->purchase_value : 0, // Get the customer's total spent.
				'edd_currency' => html_entity_decode( $edd_currency_symbol ), //phpcs:ignore PHPCompatibility.ParameterValues.NewHTMLEntitiesFlagsDefault.NotSet
			);
		}

		return array(
			'total_orders' => 0,
			'total_spent'  => 0,
			'edd_currency' => '',
		);
	}


	/**
	 * Get formated product name
	 *
	 * @param Object $product WooCommerce product object.
	 * @param mixed  $formatted_attr product data formated array.
	 *
	 * @return String
	 */
	public static function get_formated_product_name( $product, $formatted_attr = array() ) {
		$_product        = wc_get_product( $product );
		$each_child_attr = array();
		$_title          = '';
		if ( $_product ) {
			if ( !$formatted_attr ) {
				if ( 'variable' === $_product->get_type() || 'variation' === $_product->get_type() || 'subscription_variation' === $_product->get_type() || 'variable-subscription' === $_product->get_type() ) {
					$attr_summary = $_product->get_attribute_summary();
					$attr_array   = explode( ',', $attr_summary );

					foreach ( $attr_array as $ata ) {
						$attr              = strpbrk( $ata, ':' );
						$each_child_attr[] = $attr;
					}
				}
			} else {
				foreach ( $formatted_attr as $attr ) {
					$each_child_attr[] = ucfirst( $attr );
				}
			}
			if ( $each_child_attr ) {
				$each_child_attr_two = array();
				foreach ( $each_child_attr as $eca ) {
					$each_child_attr_two[] = str_replace( ': ', ' ', $eca );
				}
				$_title = $_product->get_title() . ' - ';
				$_title = $_title . implode( ', ', $each_child_attr_two );
			} else {
				$_title = $_product->get_title();
			}
		}

		return $_title;
	}

	/**
	 * Generating 1x1 pixel transparent gif image
	 *
	 * @return void
	 */
	public static function generate_gif() {
		nocache_headers();
		if ( !headers_sent() ) {
			header( 'Content-Type: image/gif', true, 301 ); // this causing the error Cannot modify header information - headers already sent by ...
			// Transparent 1x1 GIF as hex format.
		}
		exit( "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x90\x00\x00\xff\x00\x00\x00\x00\x00\x21\xf9\x04\x05\x10\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x04\x01\x00\x3b" );
	}

	/**
	 * Return contact mapping attributes
	 *
	 * @return array
	 * @since 1.0.8
	 */
	public static function import_contacts_map_attrs() {
		/**
		 * Get existing contact fields
		 */
		$contacts_attrs = Import::get_contact_general_fields();
		return apply_filters( 'mint_contacts_attrs', $contacts_attrs );
	}

	/**
	 * Retrieves all post types registered in WordPress, except for "product" and "product_variation" post types.
	 *
	 * @return array An array of objects containing the names and labels of allowed post types.
	 *
	 * @since 1.0.8
	 * @since 1.14.4 Remove default post type checking and retrieve all post types.
	 */
	public static function get_all_post_types() {
		$post_types = get_post_types( array(), 'objects' );

		// Early return if no post types are found.
		if ( empty( $post_types ) ) {
			return array();
		}

		// Use array_map for cleaner array transformation.
		$allowed_types = array_map( function ( $post_type ) {
			return array(
				'value' => $post_type->name,
				'label' => $post_type->label,
			);
		}, array_values($post_types) );

		return $allowed_types;
	}

	/**
	 * Convert a string from snake_case to CamelCase.
	 *
	 * @param string $string The input string in snake_case format.
	 * @return string The input string converted to CamelCase format.
	 * @since 1.1.0
	 */
	public static function convert_to_camelcase( string $string ): string {
		$string = str_replace( '_', ' ', $string ); // replace underscore with space.
		$string = ucwords( $string ); // capitalize first letter of each word.
		$string = str_replace( ' ', '', $string ); // remove spaces.
		return $string;
	}

	/**
	 * Retrieve the MailMint newsletter subscription from order meta data or post meta.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @param string   $key   The meta key to search for.
	 *
	 * @return string The MailMint newsletter subscription value.
	 */
	public static function get_mailmint_newsletter_subscription_hpos( $order, $key ) {
		$mrm_newsletter_subscription = '';
		if ( $order ) {
			$mrm_newsletter_subscription = get_post_meta( $order->get_id(), $key, true );

			if ( !$mrm_newsletter_subscription ) {
				$meta_data = $order->get_meta_data();
				foreach ( $meta_data as $meta ) {
					if ( $meta->key === $key ) {
						$mrm_newsletter_subscription = $meta->value;
						break;
					}
				}
			}
		}
		return $mrm_newsletter_subscription;
	}

	/**
	 * Sets the default preference settings for a preference page.
	 *
	 * @param int $preference_page_id The ID of the preference page.
	 *  @return bool Whether the option update was successful or not.
	 * @since 1.4.3
	 */
	public static function default_preferance_setting( $preference_page_id ) {
		$preferance_setting = array(
			'enable'                => 1,
			'preference'            => 'no-contact-manage',
			'preference_page_id'    => $preference_page_id,
			'lists'                 => array(),
			'primary_fields'        => array(
				'first_name' => 1,
				'last_name'  => 1,
				'status'     => 1,
			),
			'preference_page_title' => 'Mint Mail Preference',
		);

		return update_option( '_mrm_general_preference', $preferance_setting );
	}
	/**
	 * Sets the default unsubscribe settings for a confirmation page.
	 *
	 * @param int $page_id The ID of the confirmation page.
	 * @return bool Whether the option update was successful or not.
	 * @since 1.4.3
	 */
	public static function default_unsubscribe_setting( $page_id ) {
		$confirmation_data = array(
			'confirmation_page_id'    => $page_id,
			'confirmation_page_title' => 'Mint Mail Unsubscribe Confirmation',
		);

		return update_option( '_mrm_general_unsubscriber_settings', $confirmation_data );
	}

	/**
	 * Finds the active SMTP plugin from the list of plugins.
	 *
	 * @return array|null The active plugin array or null if no active plugin is found.
	 * @since 1.4.3
	 */
	public static function find_active_smtp_plugin() {
		$plugins        = Constants::get_smtp_plugin_list();
		$active_plugins = array();
		if ( empty( $plugins ) || !is_array( $plugins ) ) {
			return array(
				'status' => 'failed',
			);
		}
		foreach ( $plugins as $plugin ) {
			if ( isset( $plugin['slug'] ) && is_plugin_active( $plugin['slug'] ) ) {
				$active_plugins[] = $plugin;
			} elseif ( isset( $plugin['class'] ) && class_exists( $plugin['class'] ) ) {
				$active_plugins[] = $plugin;
			} elseif ( isset( $plugin['function'] ) && function_exists( $plugin['function'] ) ) {
				$active_plugins[] = $plugin;
			}
		}
		$get_notice = get_option( 'mint_notice_update', false );
		if ( empty( $active_plugins ) && !$get_notice ) {
			return array(
				'status'  => 'success',
				'message' => __( 'You do not have any SMTP setup on your website.Please note that your website is using the default WP sending service, which is not recommended for sending promotional emails.', 'mrm' ),
			);
		}
		return array(
			'status' => 'failed',
		);
	}

	/**
	 * Summary: Retrieves the contact fields based on the specified type.
	 *
	 * Description: Retrieves the contact fields either for a specific type or all types from the options or default values.
	 *
	 * @access public
	 *
	 * @param string $type Optional. Specifies the type of contact fields to retrieve. Default empty.
	 *                    Accepts a string representing the type of contact fields.
	 *
	 * @return array Returns an array of the contact fields based on the specified type. If no type is provided,
	 *               returns all contact fields.
	 * @since 1.5.0
	 */
	public static function retrieve_contact_fields( $type = '' ) {
		$fields = get_option( 'mint_contact_primary_fields', Constants::$primary_contact_fields );
		return isset( $fields[ $type ] ) ? $fields[ $type ] : $fields;
	}

	/**
	 * Summary: Retrieves the stored contact columns.
	 *
	 * Description: Retrieves the stored contact columns from the options or default values and unserializes them.
	 *
	 * @access public
	 *
	 * @return array Returns an array of the stored contact columns.
	 * @since 1.5.0
	 */
	public static function retrieve_stored_columns() {
		$stored_columns = maybe_unserialize(
			get_option(
				'mrm_contact_columns',
				array(
					array(
						'id'    => 'first_name',
						'value' => 'First Name',
					),
					array(
						'id'    => 'last_name',
						'value' => 'Last Name',
					),
					array(
						'id'    => 'lists',
						'value' => 'Lists',
					),
					array(
						'id'    => 'tags',
						'value' => 'Tags',
					),
					array(
						'id'    => 'statuses',
						'value' => 'Status',
					),
				)
			)
		);
		return $stored_columns;
	}

	/**
	 * Retrieves the general contact fields.
	 *
	 * Retrieves the general contact fields from the options or default values and formats them into an array.
	 *
	 * @access public
	 *
	 * @return array Returns an array of the general contact fields.
	 * @since 1.0.0
	 */
	public static function get_contact_general_fields() {
		$fields                      = self::retrieve_contact_fields();
		$fields                      = array_reduce( $fields, 'array_merge', array() );
		$segment_primary_field_slugs = array(
			'first_name',
			'last_name',
			'email',
			'country',
			'state',
			'city',
			'company',
			'designation',
			'address_line_1',
			'address_line_2',
			'date_of_birth',
			'phone_number',
		);
		$primary_fields              = array();

		$segment_primary_field_slugs = array_flip( $segment_primary_field_slugs );

		foreach ( $fields as $item ) {
			$slug  = !empty( $item['slug'] ) ? $item['slug'] : '';
			$title = !empty( $item['meta']['label'] ) ? $item['meta']['label'] : '';
			if ( isset( $segment_primary_field_slugs[ $slug ] ) ) {
				$primary_fields[ $slug ] = $title;
			}
		}

		return $primary_fields;
	}

	/**
	 * Retrieves custom fields for contacts.
	 *
	 * This function retrieves the custom fields associated with contacts and returns them in an array.
	 *
	 * @access public
	 *
	 * @return array Returns an array containing custom field values and labels for contacts.
	 * @since 1.5.1
	 */
	public static function get_contact_custom_fields() {
		// Get the custom fields associated with contacts.
		$customer_fields = CustomFieldModel::get_custom_fields_to_segment();
		$new_fields      = array();

		// Iterate through each custom field and extract relevant information.
		foreach ( $customer_fields as $customer_field ) {
			$meta  = isset( $customer_field['meta'] ) ? maybe_unserialize( $customer_field['meta'] ) : array();
			$type  = isset( $customer_field['type'] ) ? $customer_field['type'] : '';
			$label = isset( $meta['label'] ) ? $meta['label'] : '';
			$value = isset( $customer_field['value'] ) ? $customer_field['value'] : '';

			// Check if the field type is 'text' or 'textArea'.
			if ( in_array( $type, array( 'text', 'textArea' ), true ) ) {
				$new_fields[ $value ] = $label;
			}
		}
		return $new_fields;
	}

	/**
	 * Summary: Checks if the MailMint Pro version is compatible.
	 *
	 * Description: Checks if the installed MailMint Pro version is compatible with the specified version.
	 *
	 * @access public
	 *
	 * @param string $version The version to compare against.
	 *
	 * @return bool Returns true if the MailMint Pro version is compatible, false otherwise.
	 * @since 1.5.0
	 */
	public static function is_mailmint_pro_version_compatible( $version ) {
		return defined( 'MAIL_MINT_PRO_VERSION' ) && version_compare( MAIL_MINT_PRO_VERSION, $version, '>=' );
	}

	/**
	 * Check if HPOS is enabled.
	 *
	 * @since 1.5.5
	 *
	 * @return bool Whether HPOS is enabled or not.
	 */
	public static function is_hpos_enable() {
		if ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled', true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if the WPFunnels (WP Funnel Builder) plugin is active.
	 *
	 * This function checks whether the WP Funnels plugin is active on the WordPress site.
	 *
	 * @return bool True if the WP Funnels plugin is active, false otherwise.
	 * @since  1.5.11
	 */
	public static function is_wpfnl_active() {
		if ( in_array( 'wpfunnels/wpfnl.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { //phpcs:ignore
			return true;
		} elseif ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'wpfunnels/wpfnl.php' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if Mail Mint is running in Right-to-Left (RTL) mode.
	 *
	 * @return bool True if Mail Mint is set to render emails in RTL mode, false otherwise.
	 * @since 1.5.18
	 */
	public static function mail_mint_is_rtl() {
		/**
		 * Filter to determine if Mail Mint should render emails in RTL mode.
		 *
		 * @param bool $is_rtl Whether to render Mail Mint emails in RTL mode.
		 * @since 1.5.18
		 */
		return apply_filters( 'mail_mint_is_rtl', is_rtl() );
	}

	/**
	 * Retrieve placeholder data for a contact.
	 *
	 * @param array $data The input data.
	 *
	 * @return array The modified data with placeholder values replaced.
	 */
	public static function get_contact_placeholder_data( $data ) {
		$contact_id  = wp_rand( 1, 100 );
		$user_ID     = get_current_user_id();
		$user_info   = get_userdata( $user_ID );
		$contact     = ContactModel::get( $contact_id );
		$email       = isset( $contact['email'] ) ? $contact['email'] : $user_info->user_email;
		$first_name  = isset( $contact['first_name'] ) ? $contact['first_name'] : $user_info->first_name;
		$last_name   = isset( $contact['last_name'] ) ? $contact['last_name'] : $user_info->user_lastname;
		$company     = isset( $contact['meta_fields']['company'] ) ? $contact['meta_fields']['company'] : $user_info->user_url;
		$designation = isset( $contact['meta_fields']['designation'] ) ? $contact['meta_fields']['designation'] : '';
		$address_1   = isset( $contact['meta_fields']['address_line_1'] ) ? $contact['meta_fields']['address_line_1'] : '';
		$address_2   = isset( $contact['meta_fields']['address_line_2'] ) ? $contact['meta_fields']['address_line_2'] : '';
		$hash        = isset( $contact['hash'] ) ? $contact['hash'] : '#';
		$meta_fields = !empty( $contact['meta_fields'] ) ? $contact['meta_fields'] : array();
		$data        = Helper::replace_placeholder_email_body( $data, $first_name, $last_name, $email, $address_1, $address_2, $company, $designation, $meta_fields );
		$data        = Helper::replace_placeholder_business_setting( $data, $hash );

		return $data;
	}

	public static function get_timezone_offset() {
		$timezone = get_option( 'timezone_string' );
		if ( $timezone ) {
			$timezone_object = new DateTimeZone( $timezone );

			return $timezone_object->getOffset( new DateTime( 'now' ) ) / HOUR_IN_SECONDS;
		} else {
			return floatval( get_option( 'gmt_offset', 0 ) );
		}
	}

	/**
	 * Checks if email customization is active.
	 *
	 * This function retrieves the '_mrm_woocommerce_settings' option from the WordPress database
	 * and checks if the 'enable_email_customize' key is set and its value.
	 *
	 * @return bool Returns true if 'enable_email_customize' is set and its value is truthy, false otherwise.
	 * @since 1.10.0
	 */
	public static function is_email_customization_active() {
		$settings = get_option( '_mrm_woocommerce_settings' );
		return isset( $settings['enable_email_customize'] ) ? $settings['enable_email_customize'] : false;
	}

	public static function get_total_batches() {
		$batch_size = 500;
		$contacts   = ContactModel::get_contacts_count();
		$batches    = ceil( $contacts / $batch_size );
		return $batches;
	}

	/**
	 * Get the business full address
	 *
	 * @return string
	 * @since 1.14.0
	 */
	public static function get_business_full_address() {
		$settings = get_option('_mrm_business_basic_info_setting', array(
			'business_name' => '',
			'phone'         => '',
			'business_address' => array(
				'address_line_1' => '',
				'postal'         => '',
				'city'           => '',
				'address_line_2' => '',
				'country'        => '',
				'state'          => '',
			),
			'logo_url'      => '',
		));

		$address = $settings['business_address'];

		$full_address = '';
		if (!empty($address['address_line_1'])) {
			$full_address .= $address['address_line_1'] . ', ';
		}
		if (!empty($address['address_line_2'])) {
			$full_address .= $address['address_line_2'] . ', ';
		}
		if (!empty($address['city'])) {
			$full_address .= $address['city'] . ', ';
		}
		if (!empty($address['state'])) {
			$full_address .= $address['state'] . ', ';
		}
		if (!empty($address['postal'])) {
			$full_address .= $address['postal'] . ', ';
		}
		if (!empty($address['country'])) {
			$full_address .= $address['country'];
		}

		return rtrim($full_address, ', ');
	}

	/**
	 * Get the business name
	 *
	 * @return string
	 * @since 1.14.0
	 */
	public static function get_business_name() {
		$settings = get_option('_mrm_business_basic_info_setting', array(
			'business_name' => '',
			'phone'         => '',
			'business_address' => array(
				'address_line_1' => '',
				'postal'         => '',
				'city'           => '',
				'address_line_2' => '',
				'country'        => '',
				'state'          => '',
			),
			'logo_url'      => '',
		));

		return $settings['business_name'];
	}

	/**
	 * Retrieve the bounce handler configurations for various email services.
	 *
	 * @return array An array containing the bounce handler configurations for various email services.
	 * @since 1.15.0
	 */
	public static function get_bounce_configs(){
        $security_code = get_option('mint_bounce_key');
        if ( !$security_code ) {
            $security_code = 'mint_' . substr( md5( wp_generate_uuid4() ), 0, 14 );
            update_option('mint_bounce_key', $security_code);
        }

        $bounce_settings = array(
            'mailgun' => array(
				'label'       => __('Mailgun', 'mrm'),
                'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/mailgun/handle/' . $security_code),
                'doc_url'     => '',
                'input_title' => __('Mailgun Bounce Handler Webhook URL', 'mrm'),
                'input_info'  => __('Please paste this URL into your Mailgun\'s Webhook settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
            'sendgrid' => array(
				'label'       => __('SendGrid', 'mrm'),
                'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/sendgrid/handle/' . $security_code),
                'doc_url'     => '',
                'input_title' => __('SendGrid Bounce Handler Webhook URL', 'mrm'),
                'input_info'  => __('Please paste this URL into your SendGrid\'s Webhook settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
			'ses' => array(
				'label'       => __('Amazon SES', 'mrm'),
				'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/ses/handle/' . $security_code),
				'doc_url'     => '',
				'input_title' => __('Amazon SES Bounce Handler Webhook URL', 'mrm'),
				'input_info'  => __('Please use this bounce handler url in your Amazon SES + SNS settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
			'postmark' => array(
				'label'       => __('Postmark', 'mrm'),
				'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/postmark/handle/' . $security_code),
				'doc_url'     => '',
				'input_title' => __('Postmark Bounce Handler Webhook URL', 'mrm'),
				'input_info'  => __('Please paste this URL into your Postmark\'s Webhook settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
			'brevo' => array(
				'label'       => __('Brevo (Sendinblue)', 'mrm'),
				'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/brevo/handle/' . $security_code),
				'doc_url'     => '',
				'input_title' => __('Brevo Bounce Handler Webhook URL', 'mrm'),
				'input_info'  => __('Please paste this URL into your Brevo\'s Webhook settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
			'sparkpost' => array(
				'label'       => __('SparkPost', 'mrm'),
				'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/sparkpost/handle/' . $security_code),
				'doc_url'     => '',
				'input_title' => __('SparkPost Bounce Handler Webhook URL', 'mrm'),
				'input_info'  => __('Please paste this URL into your SparkPost\'s Webhook settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
			'pepipost' => array(
				'label'       => __('Pepipost', 'mrm'),
				'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/pepipost/handle/' . $security_code),
				'doc_url'     => '',
				'input_title' => __('Pepipost Bounce Handler Webhook URL', 'mrm'),
				'input_info'  => __('Please paste this URL into your Pepipost\'s Webhook settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
			'mailjet' => array(
				'label'       => __('Mailjet', 'mrm'),
				'webhook_url' => get_rest_url(null, 'mint-mail/v1/bounce_handler/mailjet/handle/' . $security_code),
				'doc_url'     => '',
				'input_title' => __('Mailjet Bounce Handler Webhook URL', 'mrm'),
				'input_info'  => __('Please paste this URL into your Mailjet\'s Webhook settings to enable Bounce Handling with Mail Mint.', 'mrm')
			),
		);

        return apply_filters('mint_bounce_handlers', $bounce_settings, $security_code);
    }
}
