<?php
/**
 * Template
 *
 * This class is responsible for handling email templates in the application.
 * It is part of the Mail Mint package and is maintained by the WPFunnels Team.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2022-10-07 09:30:00
 * @modify date 2022-10-07 11:03:17
 * @package Mint\App\Database\Repositories\Email
 */

namespace Mint\App\Database\Repositories\Email;

/**
 * Template Class
 *
 * This class is responsible for handling email templates in the application.
 * It provides methods to retrieve a custom WooCommerce email template from the database.
 * The class is part of a larger system and is used in the context of handling email templates.
 *
 * @since 1.10.0
 */
class Template {

	/**
	 * Constructor for the Template class.
	 *
	 * This is the constructor method of the Template class. It's responsible for initializing the Template object.
	 * Currently, it does not perform any operations.
	 *
	 * @since 1.10.0
	 */
	public function __construct() {
		// Constructor.
	}

	/**
	 * Retrieves a custom WooCommerce email template.
	 *
	 * This method queries the database for a post with a specific meta key and value,
	 * which represent the type of the WooCommerce email. If such a post is found,
	 * it retrieves the HTML content of the email template from the post's meta data.
	 *
	 * @param string $type The type of the WooCommerce email to retrieve the template for.
	 * @return array An associative array containing the HTML content of the email template,
	 *               or an empty array if no matching post was found.
	 * @since 1.10.0
	 */
	public function get_custom_wc_email_template( $type ) {
		global $wpdb;
		$post      = $wpdb->prefix . 'posts';
		$post_meta = $wpdb->prefix . 'postmeta';

		$email = array();

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT posts.ID as post_id FROM {$post} as posts 
            LEFT JOIN {$post_meta} as postmeta ON (posts.ID = postmeta.post_id) 
            WHERE postmeta.meta_key = 'mailmint_wc_email_type' AND postmeta.meta_value = '%s'
            ORDER BY posts.ID DESC
            LIMIT 1",
				sanitize_text_field( $type )
			),
			ARRAY_A
		);

		if ( !empty( $result['post_id'] ) ) {
			$postmeta                  = get_post_meta( intval( $result['post_id'] ) );
			$email['template']         = ( !empty( $postmeta['mailmint_email_template_html_content'][0] ) ) ? $postmeta['mailmint_email_template_html_content'][0] : '';
			$email['customize_enable'] = isset( $postmeta['mailmint_wc_customize_enable'] ) ? $postmeta['mailmint_wc_customize_enable'][0] : false;
		}

		return $email;
	}
}
