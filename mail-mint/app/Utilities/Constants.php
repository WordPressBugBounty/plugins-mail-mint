<?php
/**
 * Shared global constants for use in other classes
 *
 * @package Mint\MRM
 * @namespace Mint\MRM
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM;

use Mint\Mrm\Internal\Traits\Singleton;

/**
 * Constants class
 *
 * Shared global constants for use in other classes.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class Constants {

	use Singleton;

	/**
	 * Contact attrs available for mapping
	 *
	 * @var array $contacts_attrs
	 */
	public static $contacts_attrs = array(
		array(
			'name' => 'Email',
			'slug' => 'email',
		),
		array(
			'name' => 'First Name',
			'slug' => 'first_name',
		),
		array(
			'name' => 'Last Name',
			'slug' => 'last_name',
		),
		array(
			'name' => 'Date of Birth',
			'slug' => 'date_of_birth',
		),
		array(
			'name' => 'Company Name',
			'slug' => 'company',
		),
		array(
			'name' => 'Address Line 1',
			'slug' => 'address_line_1',
		),
		array(
			'name' => 'Address Line 2',
			'slug' => 'address_line_2',
		),
		array(
			'name' => 'Postal Code/ Zip',
			'slug' => 'postal',
		),
		array(
			'name' => 'City',
			'slug' => 'city',
		),
		array(
			'name' => 'State',
			'slug' => 'state',
		),
		array(
			'name' => 'Country',
			'slug' => 'country',
		),
		array(
			'name' => 'Phone',
			'slug' => 'phone_number',
		),
		array(
			'name' => 'Gender',
			'slug' => 'gender',
		),
	);

	/**
	 * Primary contact fields
	 *
	 * @var array $primary_contact_fields
	 * @since 1.5.0
	 */
	public static $primary_contact_fields = array(
		'basic'   => array(
			array(
				'title'     => 'Email',
				'slug'      => 'email',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Email',
				),
				'edit_mode' => false,
				'group_id'  => 2,
				'segment'   => 'email',
			),
			array(
				'title'     => 'First name',
				'slug'      => 'first_name',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'First name',
				),
				'edit_mode' => false,
				'group_id'  => 2,
				'segment'   => 'name',
			),
			array(
				'title'     => 'Last name',
				'slug'      => 'last_name',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Last name',
				),
				'edit_mode' => false,
				'group_id'  => 2,
				'segment'   => 'name',
			),
			array(
				'title'     => 'Phone number',
				'slug'      => 'phone_number',
				'type'      => 'number',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Phone number',
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
			array(
				'title'     => 'Date of Birth',
				'slug'      => 'date_of_birth',
				'type'      => 'date',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Date of Birth',
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
			array(
				'title'     => 'Gender',
				'slug'      => 'gender',
				'type'      => 'selectField',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Gender',
					'options'     => array( 'Male', 'Female', 'Others' ),
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
		),
		'address' => array(
			array(
				'title'     => 'Address Line 1',
				'slug'      => 'address_line_1',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Address Line 1',
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
			array(
				'title'     => 'Address Line 2',
				'slug'      => 'address_line_2',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Address Line 2',
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
			array(
				'title'     => 'City',
				'slug'      => 'city',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'City',
				),
				'edit_mode' => true,
				'group_id'  => 2,
				'segment'   => 'address',
			),
			array(
				'title'     => 'Postal / Zip',
				'slug'      => 'postal',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Postal / Zip',
				),
				'edit_mode' => true,
				'group_id'  => 2,
				'segment'   => 'address',
			),
			array(
				'title'     => 'Country',
				'slug'      => 'country',
				'type'      => 'selectField',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Country',
					'options'     => array(),
				),
				'edit_mode' => true,
				'group_id'  => 2,
				'segment'   => 'address',
			),
			array(
				'title'     => 'State / Province',
				'slug'      => 'state',
				'type'      => 'selectField',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'State / Province',
					'options'     => array(),
				),
				'edit_mode' => true,
				'group_id'  => 2,
				'segment'   => 'address',
			),
		),
		'other'   => array(
			array(
				'title'     => 'Company',
				'slug'      => 'company',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Company',
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
			array(
				'title'     => 'Designation',
				'slug'      => 'designation',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Designation',
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
			array(
				'title'     => 'Timezone',
				'slug'      => 'timezone',
				'type'      => 'text',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Timezone',
					'options'     => array(),
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
			array(
				'title'     => 'Last Update',
				'slug'      => 'last_update',
				'type'      => 'date',
				'meta'      => array(
					'placeholder' => '',
					'label'       => 'Last Update',
				),
				'edit_mode' => true,
				'group_id'  => 2,
			),
		),
	);

	/**
	 * Fields slugs array for custom fields validation
	 *
	 * @var array $primary_fields
	 */
	public static $primary_fields = array(
		'first-name',
		'last-name',
		'email',
		'postal-code',
		'last-activity',
		'date-of-birth',
		'company',
		'address-line-1',
		'address-line-2',
		'city',
		'state',
		'country',
		'phone-number',
		'gender',
		'timezone',
		'designation',
	);

	/**
	 * Array of primary field slugs for segmentation.
	 *
	 * These slugs represent the primary fields used for segmentation. They are used to match against the 'slug' key in the $field array
	 * to prepare the $primary_field_types array.
	 *
	 * @var array
	 * @since 1.5.0
	 */
	public static $segment_primary_field_slugs = array(
		'Contacts' => array(
			'first_name',
			'last_name',
			'email',
		),
		'Address'  => array(
			'postal',
			'city',
			'state',
			'country',
		),
	);

	/**
	 * Primary field types for segmentation
	 *
	 * @var array $static_primary_field_types
	 */
	public static $static_primary_field_types = array(
		array(
			'label' => 'Status',
			'type'  => 'status',
			'value' => 'status',
		),
		array(
			'label' => 'List',
			'type'  => 'list',
			'value' => 'list',
		),
		array(
			'label' => 'Tag',
			'type'  => 'tag',
			'value' => 'tag',
		),
	);

	/**
	 * Get Popular plugin list
	 *
	 * @var array
	 * @since 1.4.3
	 */
	public static function get_smtp_plugin_list() {
		$plugins = array(

			/**
			 * Url: https://wordpress.org/plugins/easy-wp-smtp/
			 */
			array(
				'name'  => 'WP Mail SMTP',
				'slug'  => 'wp-mail-smtp/wp_mail_smtp.php',
				'class' => 'wp_mail_smtp',
			),
			array(
				'name'  => 'WP Mail SMTP',
				'slug'  => 'wp-mail-smtp-pro/wp_mail_smtp.php',
				'class' => 'wp_mail_smtp',
			),
			/**
			 * Url: https://wordpress.org/plugins/easy-wp-smtp/
			 */
			array(
				'name'  => 'Easy WP SMTP',
				'slug'  => 'easy-wp-smtp/easy-wp-smtp.php',
				'class' => 'EasyWPSMTP',
			),

			/**
			 * Closed.
			 *
			 * Url: https://wordpress.org/plugins/postman-smtp/
			 */
			array(
				'name'     => 'Postman SMTP',
				'slug'     => 'postman-smtp/postman-smtp.php',
				'function' => 'postman_start',
			),

			/**
			 * Url: https://wordpress.org/plugins/post-smtp/
			 */
			array(
				'name'     => 'Post SMTP',
				'slug'     => 'post-smtp/postman-smtp.php',
				'function' => 'post_smtp_start',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-mail-bank/
			 */
			array(
				'name'     => 'Mail Bank',
				'slug'     => 'wp-mail-bank/wp-mail-bank.php',
				'function' => 'mail_bank',
			),

			/**
			 * Url: https://wordpress.org/plugins/smtp-mailer/
			 */
			array(
				'name'  => 'SMTP Mailer',
				'slug'  => 'smtp-mailer/main.php',
				'class' => 'SMTP_MAILER',
			),

			/**
			 * Url: https://wordpress.org/plugins/gmail-smtp/
			 */
			array(
				'name'  => 'Gmail SMTP',
				'slug'  => 'gmail-smtp/main.php',
				'class' => 'GMAIL_SMTP',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-email-smtp/
			 */
			array(
				'name'  => 'WP Email SMTP',
				'class' => 'WP_Email_Smtp',
			),

			/**
			 * Url: https://wordpress.org/plugins/smtp-mail/
			 */
			array(
				'name'     => 'SMTP Mail',
				'slug'     => 'smtp-mail/index.php',
				'function' => 'smtpmail_include',
			),

			/**
			 * Url: https://wordpress.org/plugins/bws-smtp/
			 */
			array(
				'name'     => 'SMTP by BestWebSoft',
				'slug'     => 'bws-smtp/bws-smtp.php',
				'function' => 'bwssmtp_init',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-sendgrid-smtp/
			 */
			array(
				'name'  => 'WP SendGrid SMTP',
				'slug'  => 'wp-sendgrid-smtp/wp-sendgrid-smtp.php',
				'class' => 'WPSendGrid_SMTP',
			),

			/**
			 * Url: https://wordpress.org/plugins/sar-friendly-smtp/
			 */
			array(
				'name'     => 'SAR Friendly SMTP',
				'slug'     => 'sar-friendly-smtp/sar-friendly-smtp.php',
				'function' => 'sar_friendly_smtp',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-gmail-smtp/
			 */
			array(
				'name'  => 'WP Gmail SMTP',
				'slug'  => 'wp-gmail-smtp/wp-gmail-smtp.php',
				'class' => 'WPGmail_SMTP',
			),

			/**
			 * Url: https://wordpress.org/plugins/cimy-swift-smtp/
			 */
			array(
				'name'     => 'Cimy Swift SMTP',
				'slug'     => 'cimy-swift-smtp/cimy_swift_smtp.php',
				'function' => 'st_smtp_check_config',
			),

			/**
			 * Closed.
			 *
			 * Url: https://wordpress.org/plugins/wp-easy-smtp/
			 */
			array(
				'name'  => 'WP Easy SMTP',
				'slug'  => 'wp-easy-smtp/wp-easy-smtp.php',
				'class' => 'WP_Easy_SMTP',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-mailgun-smtp/
			 */
			array(
				'name'  => 'WP Mailgun SMTP',
				'slug'  => 'wp-mailgun-smtp/wp-mailgun-smtp.php',
				'class' => 'WPMailgun_SMTP',
			),

			/**
			 * Url: https://wordpress.org/plugins/my-smtp-wp/
			 */
			array(
				'name'     => 'MY SMTP WP',
				'slug'     => 'my-smtp-wp/my-smtp-wp.php',
				'function' => 'my_smtp_wp',
			),

			/**
			 * Closed.
			 *
			 * Url: https://wordpress.org/plugins/wp-mail-booster/
			 */
			array(
				'name'     => 'WP Mail Booster',
				'slug'     => 'wp-mail-booster/wp-mail-booster.php',
				'function' => 'mail_booster',
			),

			/**
			 * Url: https://wordpress.org/plugins/sendgrid-email-delivery-simplified/
			 */
			array(
				'name'  => 'SendGrid',
				'slug'  => 'sendgrid-email-delivery-simplified/wpsendgrid.php',
				'class' => 'Sendgrid_Settings',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-mail-smtp-mailer/
			 */
			array(
				'name'     => 'WP Mail Smtp Mailer',
				'slug'     => 'wp-mail-smtp-mailer/wp-mail-smtp-mailer.php',
				'function' => 'WPMS_php_mailer',
			),

			/**
			 * Closed.
			 *
			 * Url: https://wordpress.org/plugins/wp-amazon-ses-smtp/
			 */
			array(
				'name'  => 'WP Amazon SES SMTP',
				'slug'  => 'wp-amazon-ses-smtp/wp-amazon-ses.php',
				'class' => 'WPAmazonSES_SMTP',
			),

			/**
			 * Url: https://wordpress.org/plugins/postmark-approved-wordpress-plugin/
			 */
			array(
				'name'  => 'Postmark (Official)',
				'slug'  => 'postmark-approved-wordpress-plugin/postmark.php',
				'class' => 'Postmark_Mail',
			),

			/**
			 * Url: https://wordpress.org/plugins/mailgun/
			 */
			array(
				'name'  => 'Mailgun',
				'slug'  => 'mailgun/mailgun.php',
				'class' => 'Mailgun',
			),

			/**
			 * Url: https://wordpress.org/plugins/sparkpost/
			 */
			array(
				'name'  => 'SparkPost',
				'slug'  => 'sparkpost/wordpress-sparkpost.php',
				'class' => 'WPSparkPost\SparkPost',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-yahoo-smtp/
			 */
			array(
				'name'  => 'WP Yahoo SMTP',
				'slug'  => 'wp-yahoo-smtp/wp-yahoo-smtp.php',
				'class' => 'WPYahoo_SMTP',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-ses/
			 */
			array(
				'name'     => 'WP Offload SES Lite',
				'slug'     => 'wp-ses/wp-ses.php',
				'function' => 'wp_offload_ses_lite_init',
			),

			/**
			 * Url: https://deliciousbrains.com/wp-offload-ses/
			 */
			array(
				'name' => 'WP Offload SES',
				'slug' => 'wp-offload-ses/wp-offload-ses.php',
			),

			/**
			 * Url: https://wordpress.org/plugins/turbosmtp/
			 */
			array(
				'name'     => 'turboSMTP',
				'slug'     => 'turbosmtp/turbo-smtp-plugin.php',
				'function' => 'TSPHPMailer',
			),

			/**
			 * Url: https://wordpress.org/plugins/wp-smtp/
			 */
			array(
				'name'  => 'WP SMTP',
				'slug'  => 'wp-smtp/wp-smtp.php',
				'class' => 'WP_SMTP',
			),

			/**
			 * This plugin can be used along with our plugin if disable next option
			 * WooCommerce -> Settings -> Sendinblue -> Email Options -> Enable Sendinblue to send WooCommerce emails.
			 *
			 * Url: https://wordpress.org/plugins/woocommerce-sendinblue-newsletter-subscription
			 */
			array(
				'name'    => 'Sendinblue - WooCommerce Email Marketing',
				'slug'    => 'woocommerce-sendinblue-newsletter-subscription/woocommerce-sendinblue.php',
				'class'   => 'WC_Sendinblue_Integration',
				'test'    => 'test_wc_sendinblue_integration',
				'message' => esc_html__( 'Or disable the Sendinblue email sending setting in WooCommerce > Settings > Sendinblue (tab) > Email Options (tab) > Enable Sendinblue to send WooCommerce emails.', 'mrm' ),
			),

			/**
			 * Url: https://wordpress.org/plugins/disable-emails/
			 */
			array(
				'name'  => 'Disable Emails',
				'slug'  => 'disable-emails/disable-emails.php',
				'class' => '\webaware\disable_emails\Plugin',
			),

			/**
			 * Url: https://wordpress.org/plugins/fluent-smtp/
			 */
			array(
				'name'     => 'FluentSMTP',
				'slug'     => 'fluent-smtp/fluent-smtp.php',
				'function' => 'fluentSmtpInit',
			),

			/**
			 * This plugin can be used along with our plugin if enable next option
			 * Settings > Email template > Sender (tab) -> Do not change email sender by default.
			 *
			 * Url: https://wordpress.org/plugins/wp-html-mail/
			 */
			array(
				'name'     => 'WP HTML Mail - Email Template Designer',
				'slug'     => 'wp-html-mail/wp-html-mail.php',
				'function' => 'Haet_Mail',
				'test'     => 'test_wp_html_mail_integration',
				'message'  => esc_html__( 'Or enable "Do not change email sender by default" setting in Settings > Email template > Sender (tab).', 'mrm' ),
			),

			/**
			 * This plugin can be used along with our plugin if "SMTP" module is deactivated.
			 *
			 * Url: https://wordpress.org/plugins/branda-white-labeling/
			 */
			array(
				'name'     => 'Branda',
				'slug'     => 'branda-white-labeling/ultimate-branding.php',
				'function' => 'set_ultimate_branding',
				'test'     => 'test_branda_integration',
				'message'  => esc_html__( 'Or deactivate "SMTP" module in Branda > Emails > SMTP.', 'mrm' ),
			),

			/**
			 * Url: https://wordpress.org/plugins/zoho-mail/
			 */
			array(
				'name'     => 'Zoho Mail for WordPress',
				'slug'     => 'zoho-mail/zohoMail.php',
				'function' => 'zmail_send_mail_callback',
			),
		);

		return $plugins;
	}
}
