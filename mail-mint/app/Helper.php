<?php
/**
 * Mail Mint Helper
 *
 * Helper class for the site's plugins.
 *
 * @author   Mail Mint Team
 * @category Action
 * @package  MRM
 * @since    1.0.0
 */

namespace MailMint\App;

use DOMDocument;
use Mint\MRM\DataBase\Models\CampaignModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\Utilites\Helper\Email;
use MRM\Common\MrmCommon;

/**
 * Class Helper
 */
class Helper {

	/**
	 * Replace email's url with hash value
	 *
	 * @param string $string String to match.
	 * @param string $email_hash Email address hash to generate URL.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function replace_url( $string, $email_hash ) {
		preg_match_all( '/<a[^>]+(href\=["|\'](http.*?)["|\'])/m', $string, $urls );
		$replaces = $urls[1];
		$urls     = $urls[2];
		foreach ( $urls as $index => $url ) {
			$hash = '';
			// Extract hash from URL.
			$url_parts = wp_parse_url( $url );
			if ( isset( $url_parts['fragment'] ) ) {
				$hash = '#' . $url_parts['fragment'];
			}
			// Remove hash from URL fragment.
			unset( $url_parts['fragment'] );

			$scheme = isset( $url_parts['scheme'] ) ? $url_parts['scheme'] : '';
			$host   = isset( $url_parts['host'] ) ? $url_parts['host'] : '';
			$path   = isset( $url_parts['path'] ) ? $url_parts['path'] : '';

			// Reconstruct the URL.
			$reconstructed_url = $scheme . '://' . $host . $path;
			if ( !empty( $url_parts['query'] ) ) {
				$reconstructed_url .= '?' . $url_parts['query'];
			}
			$generated_url = add_query_arg(
				array(
					'action' => 'mint_action',
					'target' => $reconstructed_url,
					'hash'   => $email_hash,
				),
				site_url()
			);

			// Append the extracted hash to the 'hash' query parameter.
			if ( !empty( $hash ) ) {
				$generated_url .= $hash;
			}

			$campaign_url = 'href="' . $generated_url . '"';
			$string       = str_replace( $replaces[ $index ], $campaign_url, $string );
		}

		return $string;
	}


	/**
	 * Generate hash value
	 *
	 * @return string
	 */
	public static function generate_hash() {
		return wp_generate_uuid4();
	}


	/**
	 * Get user agent
	 */
	public static function get_user_agent() {
		// Senitize GLOBAL Variable request.
		$sanitize_server = MrmCommon::get_sanitized_get_post();
		$sanitize_server = !empty( $sanitize_server[ 'server' ] ) ? $sanitize_server[ 'server' ] : array();

		$ua      = isset( $sanitize_server['HTTP_USER_AGENT'] ) ? strtolower( $sanitize_server['HTTP_USER_AGENT'] ) : '';
		$is_mob  = is_numeric( strpos( $ua, 'mobile' ) );
		$is_tab  = is_numeric( strpos( $ua, 'tablet' ) );
		$is_desk = !$is_mob && !$is_tab;

		if ( $is_mob ) {
			return 'mobile';
		} elseif ( $is_tab ) {
			return 'tab';
		} elseif ( $is_desk ) {
			return 'desktop';
		} else {
			return 'unidentified';
		}
	}


	/**
	 * Get user ip
	 *
	 * @return mixed|string
	 */
	public static function get_user_ip() {
		// Senitize GLOBAL Variable request.
		$sanitize_server = MrmCommon::get_sanitized_get_post();
		$sanitize_server = !empty( $sanitize_server[ 'server' ] ) ? $sanitize_server[ 'server' ] : array();
		$ip              = '';

		if ( !empty( $sanitize_server['HTTP_CLIENT_IP'] ) ) {
			$ip = $sanitize_server['HTTP_CLIENT_IP'];
		} elseif ( !empty( $sanitize_server['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $sanitize_server['HTTP_X_FORWARDED_FOR'];
		} elseif ( !empty( $sanitize_server['REMOTE_ADDR'] ) ) {
			$ip = $sanitize_server['REMOTE_ADDR'];
		}
		return $ip;
	}

	/**
	 * Replace Placeholder
	 *
	 * @param array  $business_settings Business setting for set email footer.
	 * @param string $data email data.
	 * @param string $email_data email data.
	 * @param string $hash Automation and contact email hash.
	 * @return array|string|string[]
	 */
	public static function replace_placeholder( $business_settings, $data, $email_data, $hash ) {
		$exist_email     = ContactModel::is_contact_exist( $email_data );
		$preference_url  = '#';
		$unsubscribe_url = '#';
		$email           = '';
		$first_name      = '';
		$last_name       = '';
		$city            = '';
		$state           = '';
		$country         = '';
		$company         = '';
		$designation     = '';
		$address_1       = '';
		$address_2       = '';
		$meta_fields     = array();
		if ( $exist_email ) {
			$contact_data    = ContactModel::get_contact_by_email( $email_data );
			$contact_id      = $contact_data['id'];
			$contact         = ContactModel::get( $contact_id );
			$email           = isset( $contact['email'] ) ? $contact['email'] : '';
			$first_name      = isset( $contact['first_name'] ) ? $contact['first_name'] : '';
			$last_name       = isset( $contact['last_name'] ) ? $contact['last_name'] : '';
			$city            = isset( $contact['meta_fields']['city'] ) ? $contact['meta_fields']['city'] : '';
			$state           = isset( $contact['meta_fields']['state'] ) ? $contact['meta_fields']['state'] : '';
			$country         = isset( $contact['meta_fields']['country'] ) ? $contact['meta_fields']['country'] : '';
			$company         = isset( $contact['meta_fields']['company'] ) ? $contact['meta_fields']['company'] : '';
			$designation     = isset( $contact['meta_fields']['designation'] ) ? $contact['meta_fields']['designation'] : '';
			$address_1       = isset( $contact['meta_fields']['address_line_1'] ) ? $contact['meta_fields']['address_line_1'] : '';
			$address_2       = isset( $contact['meta_fields']['address_line_2'] ) ? $contact['meta_fields']['address_line_2'] : '';
			$contact_hash    = isset( $contact['hash'] ) ? $contact['hash'] : '#';
			$unsubscribe_url = self::get_unsubscribed_url( $hash );
			$preference_url  = self::get_preference_url( $contact_hash );
			$meta_fields     = !empty( $contact['meta_fields'] ) ? $contact['meta_fields'] : array();
		}
		$data = self::helper_for_replace_business_setting_data( $data, $business_settings, $unsubscribe_url, $preference_url );
		$data = self::replace_placeholder_email_subject_preview( $data, $first_name, $last_name, $email, $city, $state, $country, $company, $designation, $meta_fields );
		$data = self::replace_placeholder_email_body( $data, $first_name, $last_name, $email, $address_1, $address_2, $company, $designation, $meta_fields );
		return $data;
	}

	/**
	 * Replace dynamic coupon placeholders with actual coupon codes.
	 *
	 * Description: Replaces dynamic coupon placeholders in the provided data with actual coupon codes.
	 *
	 * @param string $data  The data containing dynamic coupon placeholders.
	 * @param string $email The email associated with the coupon.
	 * @return string The data with replaced dynamic coupon codes.
	 * @access public
	 * @since 1.8.0
	 */
	public static function replace_dynamic_coupon( $data, $email ) {
		$pattern = '/{{mint_wc_dynamic_coupon\s+id=([^\s}]+)}}/';

		// Perform the search for all occurrences.
		preg_match_all( $pattern, $data, $matches );

		// Check if any matches are found.
		if ( !empty( $matches[0] ) ) {
			$count = count( $matches[0] );
			// Loop through each match.
			for ( $i = 0; $i < $count; $i++ ) {
				$full_match   = $matches[0][ $i ];
				$dynamic_part = $matches[1][ $i ];

				global $wpdb;

				$post_table      = $wpdb->prefix . 'posts';
				$post_meta_table = $wpdb->prefix . 'postmeta';

				$query = $wpdb->prepare(
					"SELECT p.post_title
					FROM $post_table AS p
					JOIN $post_meta_table AS pm1 ON p.ID = pm1.post_id
					JOIN $post_meta_table AS pm2 ON p.ID = pm2.post_id
					WHERE ( pm1.meta_key = %s AND pm1.meta_value = %s ) AND ( pm2.meta_key = %s AND pm2.meta_value = %s ) ORDER BY p.id DESC LIMIT 1",
					'mailmint_step_id',
					$dynamic_part,
					'mailmint_customer_email',
					$email
				);

				$result = $wpdb->get_results( $query, ARRAY_A );
				$code   = isset( $result[0]['post_title'] ) ? $result[0]['post_title'] : '';
				$data   = str_replace( '{{mint_wc_dynamic_coupon id=' . $dynamic_part . '}}', $code, $data );
			}
		}
		return $data;
	}

	/**
	 * Replace business settings placeholder
	 *
	 * @param array $business_settings Business setting for set email footer.
	 * @param array $data email data.
	 * @return array|string|string[]
	 */
	public static function replace_business_settings_placeholder( $business_settings, $data ) {
		$preference_url  = '#';
		$unsubscribe_url = '#';
		$business_name   = !empty( $business_settings[ 'business_name' ] ) ? $business_settings[ 'business_name' ] : '';
		$phone           = !empty( $business_settings[ 'phone' ] ) ? $business_settings[ 'phone' ] : '';
		$image           = self::replace_image_tag_with_placeholder( $business_settings );
		$address         = MrmCommon::business_address_formate_for_email( $business_settings );
		$data            = str_replace( '{{link.unsubscribe}}', $unsubscribe_url, $data );
		$data            = str_replace( '{{link.preference}}', $preference_url, $data );
		$data            = str_replace( '{{business.name}}', $business_name, $data );
		$data            = str_replace( '{{business.address}}', $address, $data );
		$data            = str_replace( '{{business.logo_url}}', $image, $data );
		$data            = str_replace( '{{business.phone}}', $phone, $data );
		$data            = str_replace( '{{site.url}}', site_url(), $data );
		$data            = str_replace( '{{site.title}}', get_bloginfo(), $data );
		return $data;
	}

	/**
	 * Replace image placeholder
	 *
	 * @param array $data get data.
	 * @return mixed
	 */
	public static function replace_image_tag_with_placeholder( $data ) {
		$image = '';
		if ( isset( $data[ 'logo_url' ] ) && !empty( $data[ 'logo_url' ] ) ) {
			$image = '<img style="width:120px; height:120px; margin:auto" src="' . $data[ 'logo_url' ] . '">';
		}
		return $image;
	}

	/**
	 * Replace Email Subject and Preview.
	 *
	 * @param string $data Get all Email Data.
	 * @param string $first_name Recipient First Name.
	 * @param string $last_name  Recipient Fast Name.
	 * @param string $email Recipient email Name.
	 * @param string $city Recipient City Name.
	 * @param string $state Recipient State Name.
	 * @param string $country Recipient Country Name.
	 * @param string $company Recipient company Name.
	 * @param string $designation Recipient Description Name.
	 * @param array  $custom_fields Contact custom fields.
	 * @return array|string|string[]
	 */
	public static function replace_placeholder_email_subject_preview( $data, $first_name, $last_name, $email, $city, $state, $country, $company, $designation, $custom_fields ) {
		$data = self::replace_pipe_data( 'first_name', $data, $first_name );
		$data = str_replace( '{{first_name}}', $first_name, $data );

		$data = self::replace_pipe_data( 'last_name', $data, $last_name );
		$data = str_replace( '{{last_name}}', $last_name, $data );

		$data = self::replace_pipe_data( 'email', $data, $email );
		$data = str_replace( '{{email}}', $email, $data );

		$data = self::replace_pipe_data( 'city', $data, $city );
		$data = str_replace( '{{city}}', $city, $data );

		$data = self::replace_pipe_data( 'state', $data, $state );
		$data = str_replace( '{{state}}', $state, $data );

		$data = self::replace_pipe_data( 'country', $data, $country );
		$data = str_replace( '{{country}}', $country, $data );

		$data = self::replace_pipe_data( 'company', $data, $company );
		$data = str_replace( '{{company}}', $company, $data );

		$data = self::replace_pipe_data( 'designation', $data, $designation );
		$data = str_replace( '{{designation}}', $designation, $data );

		$data = self::replace_custom_fields( $data, $custom_fields );

		return $data;
	}

	/**
	 * Replace all Placeholder.
	 *
	 * @param string $placeholder Get Placeholder.
	 * @param string $data Get All email body.
	 * @param string $name Get Name value eatch Data.
	 * @return array|string|string[]
	 */
	public static function replace_pipe_data( $placeholder, $data, $name ) {
		preg_match( '/{{' . $placeholder . '\|(.*?)}}/', $data, $_matches );
		$pipe_match = isset( $_matches[1] ) ? $_matches[1] : '';
		$value      = '{{' . $placeholder . '|' . $pipe_match . '}}';
		if ( '' === $name ) {
			$data = str_replace( $value, 'fallback' === $pipe_match ? '' : $pipe_match, $data );
		} else {
			$data = str_replace( $value, $name, $data );
		}
		return $data;
	}

	/**
	 * Return placeholder pipe text
	 *
	 * @param string $placeholder Get Placeholder.
	 * @param string $data Get All email body.
	 * @param string $name Get Name value eatch Data.
	 * @return array|string|string[]
	 */
	public static function get_pipe_text( $placeholder, $data, $name ) {
		preg_match( '/{{' . $placeholder . '\|(.*?)}}/', $data, $_matches );
		$pipe_match = isset( $_matches[1] ) ? $_matches[1] : '';
		return $pipe_match;
	}

	/**
	 * Replace Email Subject and Preview.
	 *
	 * @param array  $data Get all Email Data.
	 * @param string $first_name Recipient First Name.
	 * @param string $last_name  Recipient Fast Name.
	 * @param string $email Recipient email Name.
	 * @param string $address_1 Recipient Country Name.
	 * @param string $address_2 Recipient company Name.
	 * @param string $company Recipient State Name.
	 * @param string $designation Recipient Description Name.
	 * @param array  $custom_fields Contact custom fields.
	 *
	 * @return array|string|string[]
	 */
	public static function replace_placeholder_email_body( $data, $first_name, $last_name, $email, $address_1, $address_2, $company, $designation, $custom_fields ) {
		$data = self::replace_pipe_data( 'contact.email', $data, $email );
		$data = str_replace( '{{contact.email}}', $email, $data );

		$data = self::replace_pipe_data( 'contact.firstName', $data, $first_name );
		$data = str_replace( '{{contact.firstName}}', $first_name, $data );

		$data = self::replace_pipe_data( 'contact.lastName', $data, $last_name );
		$data = str_replace( '{{contact.lastName}}', $last_name, $data );

		$data = self::replace_pipe_data( 'contact.companyName', $data, $company );
		$data = str_replace( '{{contact.companyName}}', $company, $data );

		$data = self::replace_pipe_data( 'contact.designation', $data, $designation );
		$data = str_replace( '{{contact.designation}}', $designation, $data );

		$data = self::replace_pipe_data( 'contact.address_1', $data, $address_1 );
		$data = str_replace( '{{contact.address_1}}', $address_1, $data );

		$data = self::replace_pipe_data( 'contact.address_2', $data, $address_2 );
		$data = str_replace( '{{contact.address_2}}', $address_2, $data );

		$data = self::replace_custom_fields( $data, $custom_fields );

		return $data;
	}

	/**
	 * Replaces custom fields in the given data.
	 *
	 * This function replaces custom fields in the provided data by their corresponding values.
	 *
	 * @access public
	 *
	 * @param string $data         The data to replace custom fields in.
	 * @param array  $custom_fields An array containing custom field values.
	 * @return string The data with custom fields replaced by their values.
	 * @since 1.5.1
	 */
	public static function replace_custom_fields( $data, $custom_fields ) {
		$fields = CampaignModel::get_all_customfield();
		foreach ( $fields as $key => $field ) {
			if ( isset( $custom_fields[ $field ] ) ) {
				$data = self::replace_pipe_data( 'custom.' . $field . '', $data, $custom_fields[ $field ] );
				$data = str_replace( '{{custom.' . $field . '}}', $custom_fields[ $field ], $data );
			} else {
				$data = self::replace_pipe_data( 'custom.' . $field . '', $data, '' );
				$data = str_replace( '{{custom.' . $field . '}}', '', $data );
			}
		}
		return $data;
	}

	/**
	 * Replace placeholder for Business Setting.
	 *
	 * @param string $data Get all Email Data.
	 * @param string $hash Customer email Hash.
	 * @return mixed
	 */
	public static function replace_placeholder_business_setting( $data, $hash ) {
		$default_business_settings = MrmCommon::business_settings_default_configuration();
		$business_settings         = get_option( '_mrm_business_basic_info_setting', $default_business_settings );
		$business_settings         = is_array( $business_settings ) && ! empty( $business_settings ) ? $business_settings : $default_business_settings;
		// Prepare unsubscribe and preference url for individual contact.

		$unsubscribe_url = self::get_unsubscribed_url( $hash );
		$preference_url  = self::get_preference_url( $hash );
		$data            = self::helper_for_replace_business_setting_data( $data, $business_settings, $unsubscribe_url, $preference_url );

		return $data;
	}


	/**
	 * Helper for replace business Setting Data.
	 *
	 * @param string $data Get all Email Data.
	 * @param array  $business_settings Get all Setting Data.
	 * @param string $unsubscribe_url Get all Setting Data.
	 * @param string $preference_url Get all Setting Data.
	 * @return array|string|string[]
	 */
	public static function helper_for_replace_business_setting_data( $data, $business_settings, $unsubscribe_url, $preference_url ) {

		// Replace for classic editor.
		$classice_preferance_url = '<a href ="' . $preference_url . '">Manage your preference</a>';
		$classic_unsubscribe_url = '<a href ="' . $unsubscribe_url . '">Unsubscribe</a>';

		$image   = self::replace_image_tag_with_placeholder( $business_settings );
		$address = MrmCommon::business_address_formate_for_email( $business_settings );

		$unsubscribe_text     = self::get_pipe_text( 'link.unsubscribe_html', $data, $unsubscribe_url );
		$unsubscribe_url_html = '<a href ="' . $unsubscribe_url . '">' . $unsubscribe_text . '</a>';

		$data = self::replace_pipe_data( 'link.unsubscribe_html', $data, $unsubscribe_url_html );
		$data = str_replace( '{{link.unsubscribe_html|' . $unsubscribe_text . '}}', $unsubscribe_url_html, $data );
		$data = str_replace( '{{link.unsubscribe_html}}', $classic_unsubscribe_url, $data );

		$preference_text     = self::get_pipe_text( 'link.preference_html', $data, $preference_url );
		$preference_url_html = '<a href ="' . $preference_url . '">' . $preference_text . '</a>';

		$data = self::replace_pipe_data( 'link.preference_html', $data, $preference_url_html );
		$data = str_replace( '{{link.preference_html|' . $preference_text . '}}', $preference_url_html, $data );
		$data = str_replace( '{{link.preference_html}}', $classice_preferance_url, $data );

		$data = self::replace_pipe_data( 'link.unsubscribe', $data, $unsubscribe_url );
		$data = str_replace( '{{link.unsubscribe}}', $unsubscribe_url, $data );

		$data = self::replace_pipe_data( 'link.preference', $data, $preference_url );
		$data = str_replace( '{{link.preference}}', $preference_url, $data );
		$data = self::replace_pipe_data( 'business.name', $data, $business_settings[ 'business_name' ] );
		if ( $business_settings[ 'business_name' ] ) {
			$data = str_replace( '{{business.name}}', $business_settings[ 'business_name' ], $data );
		} else {
			$data = str_replace( '<div><br></div><div style="text-align: center;"><span style="font-size: 12px;">{{business.name}}</span></div>', $business_settings[ 'business_name' ], $data );
		}

		$data = self::replace_pipe_data( 'business.address', $data, $address );
		if ( $address ) {
			$data = str_replace( '{{business.address}}', $address, $data );
		} else {
			$data = str_replace( '<div><br></div><div style="text-align: center;"><span style="font-size: 10px; font-weight: 400;">{{business.address}}</span></div>', '', $data );
		}

		$data = self::replace_pipe_data( 'business.logo_url', $data, $image );
		if ( $image ) {
			$data = str_replace( '{{business.logo_url}}', $image, $data );
		} else {
			$data = str_replace( '<div><br></div><div style="text-align: center;">{{business.logo_url}}</div>', '', $data );
		}

		$data = self::replace_pipe_data( 'business.phone', $data, $business_settings[ 'phone' ] );
		if ( $business_settings[ 'phone' ] ) {
			$data = str_replace( '{{business.phone}}', $business_settings[ 'phone' ], $data );
		} else {
			$data = str_replace( '<div><br></div><div style="text-align: center;"><span style="font-size: 10px; font-weight: 400;">{{business.phone}}</span></div>', '', $data );
		}

		$data = self::replace_pipe_data( 'site.url', $data, site_url() );
		$data = str_replace( '{{site.url}}', site_url(), $data );

		$data = self::replace_pipe_data( 'site.title', $data, get_bloginfo() );
		$data = str_replace( '{{site.title}}', get_bloginfo(), $data );

		$data = self::replace_pipe_data( 'url.home', $data, home_url() );
		$data = str_replace( '{{url.home}}', home_url(), $data );

		$shop_url = MrmCommon::is_wc_active() ? wc_get_page_permalink( 'shop' ) : home_url();
		$data     = self::replace_pipe_data( 'url.shop', $data, $shop_url ? $shop_url : home_url() );
		$data     = str_replace( '{{url.shop}}', $shop_url ? $shop_url : home_url(), $data );

		$account_url = MrmCommon::is_wc_active() ? wc_get_page_permalink( 'myaccount' ) : home_url();
		$data        = self::replace_pipe_data( 'url.my_account', $data, $account_url ? $account_url : home_url() );
		$data        = str_replace( '{{url.my_account}}', $account_url ? $account_url : home_url(), $data );

		$checkout_url = MrmCommon::is_wc_active() ? wc_get_checkout_url() : home_url();
		$data         = self::replace_pipe_data( 'url.checkout', $data, $checkout_url ? $checkout_url : home_url() );
		$data         = str_replace( '{{url.checkout}}', $checkout_url ? $checkout_url : home_url(), $data );

		return $data;
	}

	/**
	 * Get Preferance Url
	 *
	 * @param string $hash get String.
	 * @return string
	 */
	public static function get_preference_url( $hash ) {
		return add_query_arg(
			array(
				'mrm'   => '1',
				'route' => 'mrm-preference',
				'hash'  => $hash,
			),
			MrmCommon::get_default_preference_page_id_title()
		);
	}
	/**
	 * Get Unsubscribe Url
	 *
	 * @param string $hash get String.
	 * @return string
	 */
	public static function get_unsubscribed_url( $hash ) {
		return add_query_arg(
			array(
				'mrm'   => '1',
				'route' => 'unsubscribe',
				'hash'  => $hash,
			),
			site_url()
		);
	}
	/**
	 * Retrieves the email frequency setting.
	 *
	 * @return array The email frequency setting.
	 * @since 1.5.13
	 */
	public static function get_email_frequency_setting() {
		$get_frequency = get_option( '_mrm_email_settings', Email::default_email_settings() );
		return is_array( $get_frequency ) && !empty( $get_frequency['email_frequency'] ) ? $get_frequency['email_frequency'] : array();
	}

	/**
	 * Prepare email headers based on sender and reply information.
	 *
	 * @param string $sender_name   Sender's name.
	 * @param string $sender_email  Sender's email address.
	 * @param string $reply_name    Reply-to name.
	 * @param string $reply_email   Reply-to email address.
	 *
	 * @return array Email headers.
	 * @since 1.6.0
	 */
	public static function prepare_email_headers( $sender_name, $sender_email, $reply_name, $reply_email ) {
		$headers = array(
			'MIME-Version: 1.0',
			'Content-type: text/html;charset=UTF-8',
		);

		$from      = 'From: ' . $sender_name;
		$headers[] = $from . ' <' . $sender_email . '>';
		if ( $reply_name && $reply_email ) {
			$headers[] = 'Reply-To: ' . $reply_name . ' <' . $reply_email . '>';
		} elseif ( $reply_email ) {
			$headers[] = $reply_email;
		}

		return $headers;
	}

	/**
	 * Get the ordinal suffix for a given number (e.g., 1st, 2nd, 3rd, 4th).
	 *
	 * @param int $number The number for which to determine the ordinal suffix.
	 *
	 * @return string The number with its ordinal suffix.
	 * @since 1.7.0
	 */
	public static function get_ordinal_suffix( $number ) {
		if ( $number % 100 >= 11 && $number % 100 <= 13 ) {
			$suffix = 'th';
		} else {
			switch ( $number % 10 ) {
				case 1:
					$suffix = 'st';
					break;
				case 2:
					$suffix = 'nd';
					break;
				case 3:
					$suffix = 'rd';
					break;
				default:
					$suffix = 'th';
					break;
			}
		}

		return $number . $suffix;
	}

	/**
	 * Make the email body RTL (Right-to-Left) supported if Mail Mint is configured for RTL mode.
	 *
	 * @param string $email_body The original email body content.
	 *
	 * @return string The modified email body content with RTL support, or the original content if RTL mode is not enabled.
	 * @since 1.5.18
	 */
	public static function modify_email_for_rtl( $email_body ) {
		// Check if Mail Mint is configured for RTL mode.
		if (!MrmCommon::mail_mint_is_rtl()) {
			return $email_body;
		}

		// Ensure UTF-8 encoding
		$email_body = mb_convert_encoding($email_body, 'HTML-ENTITIES', 'UTF-8');

		// Create a new DOMDocument with UTF-8 support.
		$dom = new DOMDocument();

		// Suppress warnings and load the HTML.
		libxml_use_internal_errors(true);
		$dom->loadHTML('<!DOCTYPE html><html><body>' . $email_body . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		// Get the <body> element.
		$body = $dom->getElementsByTagName('body')->item(0);

		if ($body) {
			// Add the dir="rtl" attribute to the <body> tag.
			$body->setAttribute('dir', 'rtl');

			// Get modified HTML.
			$modified_html = $dom->saveHTML($body);

			// Convert direction: ltr to direction: rtl.
			$modified_html = preg_replace('/direction:\s*ltr/i', 'direction: rtl', $modified_html);

			return $modified_html;
		}

		return $email_body; // Return original if body is not found.
	}

	/**
	 * Replace post merge tags in the email content.
	 *
	 * @param string $data   The email content.
	 * @param int    $post_id The ID of the post.
	 *
	 * @return string The email content with the post merge tags replaced.
	 * @since 1.13.0
	 */
	public static function replace_email_body_post_merge_tags( $data, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $data;
		}

		$tags = array(
			'{{post.title}}'   => $post->post_title,
			'{{post.author}}'  => get_the_author_meta( 'display_name', $post->post_author ),
			'{{post.date}}'    => MrmCommon::date_time_format_with_core( $post->post_modified ),
			'{{post.excerpt}}' => get_the_excerpt( $post ),
		);

		foreach ( $tags as $tag => $value ) {
			$data = str_replace( $tag, $value, $data );
		}

		// Replace the post image placeholder.
		$post_thumbnail      = '';
		$post_thumbnail_link = '';
		$post_thumbnail_url  = '';
		$post_link           = get_permalink( $post_id );
		if ( ( function_exists( 'has_post_thumbnail' ) ) && ( has_post_thumbnail( $post_id ) ) ) {
			$post_image_size = get_option( 'mint_post_image_size', 'thumbnail' );

			switch ( $post_image_size ) {
				case 'full':
					$post_thumbnail = get_the_post_thumbnail( $post_id, 'full' );
					break;
				case 'medium':
					$post_thumbnail = get_the_post_thumbnail( $post_id, 'medium' );
					break;
				case 'thumbnail':
				default:
					$post_thumbnail = get_the_post_thumbnail( $post_id, 'thumbnail' );
					break;
			}
		}

		if ( '' !== $post_thumbnail ) {
			$post_thumbnail_link = "<a href='" . $post_link . "' target='_blank'>" . $post_thumbnail . "</a>";
		}

		$post_thumbnail_id = get_post_thumbnail_id( $post_id );

		if ( ! empty( $post_thumbnail_id ) ) {
			$post_thumbnail_url = wp_get_attachment_url( $post_thumbnail_id );
		}

		$data = str_replace( '{{post.image}}', $post_thumbnail_link, $data );
		$data = str_replace( '{{post.image_url}}', $post_thumbnail_url, $data );

		// Replace the post description placeholder.
		$post_description_length = 50;
		$post_description        = $post->post_content;
		$post_description        = strip_tags( self::strip_shortcodes( $post_description ) );
		$words                   = explode( ' ', $post_description, $post_description_length + 1 );
		if ( count( $words ) > $post_description_length ) {
			array_pop( $words );
			array_push( $words, '...' );
			$post_description = implode( ' ', $words );
		}
		$data = str_replace( '{{post.description}}', $post_description, $data );
		$data = str_replace( '{{post.link}}', $post_link, $data );

		if ( '' != $post_link ) {
			$post_link_with_title = "<a href='" . $post_link . "' target='_blank'>" . $post->post_title . '</a>';
			$data                 = str_replace( '{{post.link_with_title}}', $post_link_with_title, $data );
		}

		// Replace the post full content placeholder.
		$post_full = $post->post_content;
		$post_full = wpautop( $post_full );
		$data      = str_replace( '{{post.full_content}}', $post_full, $data );

		// Replace the post categories placeholder.
		if ( false !== strpos( $data, '{{post.cats}}' ) ) {
			$taxonomies = get_object_taxonomies( $post );
			$post_cats  = array();

			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $taxonomy ) {
					$taxonomy_object = get_taxonomy( $taxonomy );
					// Check if taxonomy is hierarchical e.g. have parent-child relationship like categories.
					if ( $taxonomy_object->hierarchical ) {
						$post_terms = get_the_terms( $post, $taxonomy );
						if ( ! empty( $post_terms ) ) {
							foreach ( $post_terms as $term ) {
								$term_name   = $term->name;
								$post_cats[] = $term_name;
							}
						}
					}
				}
			}

			$data = str_replace( '{{post.cats}}', implode( ', ', $post_cats ), $data );
		}

		return $data;
	}

	/**
	 * Strip shortcodes from the content.
	 *
	 * @param string $content The content from which to strip shortcodes.
	 *
	 * @return string The content with shortcodes stripped.
	 * @since 1.13.0
	 */
	public static function strip_shortcodes( $content ) {
		$content = preg_replace('/\[[^\[\]]*\]/', '', $content);
		return $content;
	}

	public static function getDbCustomerFromOrder($order){
		global $wpdb;
        if ($customerUserId = $order->get_customer_id()) {
			$customer = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d", $customerUserId)
			);
            if ($customer) {
                return $customer;
            }
        }

        $customerId = false;
        
		$lookup = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_order_product_lookup WHERE order_id = %d", $order->get_id())
		);

        if ($lookup) {
            $customerId = $lookup->customer_id;
        } else {
            if (class_exists('\Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore')) {
                if (!is_a($order, '\Automattic\WooCommerce\Admin\Overrides\Order')) {
                    $order = new \Automattic\WooCommerce\Admin\Overrides\Order($order);
                }

                $customerId = \Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore::get_or_create_customer_from_order($order);
            }
        }

        if (!$customerId) {
			$lookup = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d", $order->get_id())
			);
            if ($lookup) {
                $customerId = $lookup->customer_id;
            }
        }

        if (!$customerId) {
            $customerEmail = $order->get_billing_email();
            if ($customerEmail) {
                return $wpdb->get_row(
					$wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_customer_lookup WHERE email = %s", $customerEmail)
				);
            } else {
                return false;
            }
        }

        return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d", $customerId)
		);
    }
}
