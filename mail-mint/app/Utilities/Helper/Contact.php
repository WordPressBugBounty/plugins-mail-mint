<?php
/**
 * Contact data helper.
 *
 * @package Mint\MRM\Utilites\Helper
 * @namespace Mint\MRM\Utilites\Helper
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\Utilites\Helper;

use Mint\MRM\Constants;
use Mint\MRM\DataBase\Tables\ContactMetaSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;

/**
 * ContactData class
 *
 * Contact data helper class.
 *
 * @package Mint\MRM\Utilites\Helper
 * @namespace Mint\MRM\Utilites\Helper
 *
 * @version 1.0.0
 */
class Contact {

	/**
	 * Get contact meta value from contact meta table
	 *
	 * @param int    $contact_id contact id.
	 * @param string $meta_key meta key.
	 * @return string|null
	 * @since 1.0.0
	 */
	public static function get_meta( $contact_id, $meta_key ) {
		global $wpdb;

		$meta_table_name = $wpdb->prefix . ContactMetaSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( "SELECT `meta_value` FROM {$meta_table_name} WHERE `contact_id` = %d AND `meta_key` = %s", array( $contact_id, $meta_key ) );

		return $wpdb->get_var( $sql ); // db call ok. ; no-cache ok.
	}

	/**
	 * Get contact info from contact table
	 *
	 * @param int    $contact_id contact id.
	 * @param string $key meta key.
	 * @return string|null
	 * @since 1.0.0
	 */
	public static function get_info( $contact_id, $key ) {
		global $wpdb;

		$table_name = $wpdb->prefix . ContactSchema::$table_name;

		$sql = "SELECT `{$key}` FROM {$table_name} WHERE `id` = %d";
		$sql = $wpdb->prepare( $sql, $contact_id );

		return $wpdb->get_var( $sql ); // db call ok. ; no-cache ok.
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Get the avatar URL for a contact.
	 *
	 * This function retrieves the avatar URL for a contact.
	 *
	 * @access public
	 * @param array $contact An associative array containing contact information.
	 * @return string The URL of the contact's avatar.
	 *
	 * @since 1.5.18
	 * @since 1.18.3 Add Gravatar compliance settings to create avatar URL.
	 */
	public static function get_avatar_url( $contact ) {
		// Check if the contact array is valid and contains the necessary keys.
		if (isset($contact['meta_fields']['avatar_url']) && $contact['meta_fields']['avatar_url']) {
			$upload_dir = wp_upload_dir();
			$url        = $upload_dir['baseurl'];
			if (false !== strpos($contact['meta_fields']['avatar_url'], $url)) {
				return $contact['meta_fields']['avatar_url'];
			}
		}

		// If the avatar URL is not set or invalid, proceed to generate the Gravatar URL.
		$compliance        = get_option('_mint_compliance');
		$enable_gravatar   = isset( $compliance['enable_gravatar'] ) && $compliance['enable_gravatar'] == 'yes' ? true : false;
		$gravatar_fallback = isset( $compliance['gravatar_fallback'] ) && $compliance['gravatar_fallback'] == 'yes' ? true : false;
		$email             = isset($contact['email']) ? $contact['email'] : '';

		// If Gravatar is not enabled, return the default avatar URL.
		if ( !$enable_gravatar ) {
			return apply_filters('mail_mint_default_avatar', MRM_PLUGIN_URL . 'admin/assets/images/avatar.png', $email);
		}

		$hash       = md5( strtolower( trim( $email ) ) );
		$first_name = isset( $contact['first_name'] ) ? $contact['first_name'] : '';
		$last_name  = isset( $contact['last_name'] ) ? $contact['last_name'] : '';
		$full_name  = trim( $first_name . ' ' . $last_name );
		/**
		 * Gravatar URL by Email.
		 *
		 * @return string $gravatar url of the gravatar image.
		 */

		$fall_back = '';
		if ( $gravatar_fallback && !empty( $full_name ) ) {
			$fall_back = '&d=https%3A%2F%2Fui-avatars.com%2Fapi%2F' . urlencode( $full_name ) . '/128';
		}

		/**
		 * Apply a filter for generating the avatar URL.
		 *
		 * This function allows other developers or plugins to modify the generated avatar URL by hooking into the 'mail_mint_get_avatar' filter.
		 *
		 * @since 1.5.18
		 *
		 * @param string $avatar_url The default avatar URL to be filtered.
		 * @param string $email The email address associated with the avatar.
		 */
		return apply_filters(
			'mail_mint_get_avatar',
			"https://www.gravatar.com/avatar/{$hash}?s=128" . $fall_back,
			$email
		);
	}

	/**
	 * Retrieves the primary contact fields from the WordPress options or returns the default fields.
	 *
	 * @return array An array of primary contact fields.
	 * @since 1.5.0
	 */
	public static function get_contact_primary_fields() {
		return get_option( 'mint_contact_primary_fields', Constants::$primary_contact_fields );
	}
}
