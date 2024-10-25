<?php
/**
 * Prepare email.
 *
 * @package Mint\MRM\Utilites\Helper
 * @namespace Mint\MRM\Utilites\Helper
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\Utilites\Helper;

use MailMint\App\Helper;
use Mint\MRM\DataBase\Models\ContactModel;

/**
 * Email class
 *
 * Prepare email.
 *
 * @package Mint\MRM\Utilites\Helper
 * @namespace Mint\MRM\Utilites\Helper
 *
 * @version 1.0.0
 */
class Email {

	/**
	 * Get email headers for Mail Mint emails.
	 *
	 * This function constructs the email headers based on the provided data and
	 * unsubscribe link. It includes MIME version, content type, 'From' and 'Reply-To'
	 * addresses, and 'List-Unsubscribe' header if enabled by the filter.
	 *
	 * @param array  $data              An associative array containing email data, such as 'from_name',
	 *                                  'from_email', 'reply_name', 'reply_email'.
	 * @param string $unsubscribe_link  The unsubscribe link to be included in the 'List-Unsubscribe' header.
	 *
	 * @return array An array of email headers.
	 *
	 * @since 1.0.0
	 * @modified 1.8.2 Add List-Unsubscribe on the header.
	 */
	public static function get_mail_header( $data, $unsubscribe_link ) {
		$headers = array(
			'MIME-Version: 1.0',
			'Content-type: text/html;charset=UTF-8',
		);

		if ( $data['from_name'] && $data['from_email'] ) {
			$headers[] = 'From: ' . $data['from_name'] . ' <' . $data['from_email'] . '>';
		} elseif ( $data['from_email'] ) {
			$headers[] = $data['from_email'];
		}

		if ( $data['reply_name'] && $data['reply_email'] ) {
			$headers[] = 'Reply-To: ' . $data['reply_name'] . ' <' . $data['reply_email'] . '>';
		} elseif ( $data['reply_email'] ) {
			$headers[] = $data['reply_email'];
		}

		/**
		 * Filters whether to enable the 'List-Unsubscribe' header in Mail Mint emails.
		 *
		 * This filter allows customization of whether to include the 'List-Unsubscribe' header
		 * in the email headers. By default, it returns true, enabling the header.
		 *
		 * @param bool   $enable_header  Whether to enable the 'List-Unsubscribe' header. Default is true.
		 * @param array  $data           An associative array containing email data, such as 'from_name',
		 *                               'from_email', 'reply_name', 'reply_email'.
		 *
		 * @return bool Whether to enable the 'List-Unsubscribe' header.
		 *
		 * @since 1.8.2
		 */
		if ( apply_filters( 'mail_mint_enable_unsubscribe_header', true, $data ) ) {
			$headers[] = 'List-Unsubscribe: <mailto:' . $data['from_email'] . '?subject=Unsubscribe>, <' . $unsubscribe_link . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}

		/**
		 * Filters the headers to be included in Mail Mint emails.
		 *
		 * This filter allows customization of the email headers before sending the email.
		 * It receives an array of headers, the email data, and allows modification.
		 *
		 * @param array $headers An array of email headers.
		 * @param array $data    An associative array containing email data, such as 'from_name',
		 *                       'from_email', 'reply_name', 'reply_email'.
		 *
		 * @return array Modified array of email headers.
		 *
		 * @since 1.8.2
		 */
		return apply_filters( 'mail_mint_email_headers', $headers, $data );
	}

	/**
	 * Return default email settings or header information
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function default_email_settings() {
		// Get site title and admin email from native WP.
		$name        = get_bloginfo( 'name' );
		$admin_email = get_bloginfo( 'admin_email' );

		// Return default value for email settings.
		return array(
			'from_name'       => $name,
			'from_email'      => $admin_email,
			'reply_name'      => $name,
			'reply_email'     => $admin_email,
			'email_frequency' => array(
				'type'   => 'Recommended',
				'time'   => 5,
				'emails' => 25,
				'host'   => '',
			),
            'bounce_tracking' => array(
                'enable' => false,
                'esp'  => array(
                    'value' => 'mailgun',
                    'label' => 'Mailgun',
                ),
            ),
		);
	}


	/**
	 * Replace custom placeholders from email subject
	 *
	 * @param string $data String value of email subject/preview/body text.
	 * @param int    $contact_id MRM contact id.
	 * @param string $hash Contact email and cmapign Id .
	 *
	 * @return array|string
	 */
	public static function update_dynamic_placeholders( string $data, int $contact_id, string $hash ) {
		$contact     = ContactModel::get( $contact_id );
		$email       = isset( $contact['email'] ) ? $contact['email'] : '';
		$first_name  = isset( $contact['first_name'] ) ? $contact['first_name'] : '';
		$last_name   = isset( $contact['last_name'] ) ? $contact['last_name'] : '';
		$company     = isset( $contact['meta_fields']['company'] ) ? $contact['meta_fields']['company'] : '';
		$designation = isset( $contact['meta_fields']['designation'] ) ? $contact['meta_fields']['designation'] : '';
		$city        = isset( $contact['meta_fields']['city'] ) ? $contact['meta_fields']['city'] : '';
		$state       = isset( $contact['meta_fields']['state'] ) ? $contact['meta_fields']['state'] : '';
		$country     = isset( $contact['meta_fields']['country'] ) ? $contact['meta_fields']['country'] : '';
		$address_1   = isset( $contact['meta_fields']['address_line_1'] ) ? $contact['meta_fields']['address_line_1'] : '';
		$address_2   = isset( $contact['meta_fields']['address_line_2'] ) ? $contact['meta_fields']['address_line_2'] : '';
		$meta_fields = !empty( $contact['meta_fields'] ) ? $contact['meta_fields'] : array();
		$data        = Helper::replace_placeholder_email_subject_preview( $data, $first_name, $last_name, $email, $city, $state, $country, $company, $designation, $meta_fields );
		$data        = Helper::replace_placeholder_email_body( $data, $first_name, $last_name, $email, $address_1, $address_2, $company, $designation, $meta_fields );
		$data        = Helper::replace_placeholder_business_setting( $data, $hash );
		return $data;
	}

	/**
	 * Prepare email template information
	 *
	 * @param string $email_body email body.
	 * @param string $domain_link domain link.
	 * @param string $hash contact hash key.
	 * @param string $preview_text email preview text.
	 *
	 * @since 1.0.0
	 */
	public static function get_mail_template( $email_body = '', $domain_link = '', $hash = '', $preview_text = '' ) {
		$is_watermark = apply_filters( 'mail_mint_remove_email_footer_watermark', true );

		$watermark_text = '';

		if ( $is_watermark ) {
			$watermark_text = 'Powered by <a href="https://getwpfunnels.com/email-marketing-automation-mail-mint/?utm_source=emailfooter&utm_medium=freeusers&utm_campaign=poweredbymailmint" style="text-decoration: underline; color: black;">Mail Mint</a>';
		}

		return '
    <!doctype html>
    <html>
      <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>Simple Transactional Email</title>
        <style>
          /* -------------------------------------
              GLOBAL RESETS
          ------------------------------------- */

          /*All the styling goes here*/

          img {
            border: none;
            -ms-interpolation-mode: bicubic;
            max-width: 100%;
          }

          body {
            background-color: #f6f6f6;
            font-family: sans-serif;
            -webkit-font-smoothing: antialiased;
            font-size: 14px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
          }

          table {
            border-collapse: separate;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            width: 100%; }
            table td {
              font-family: sans-serif;
              font-size: 14px;
              vertical-align: top;
          }

          /* -------------------------------------
              BODY & CONTAINER
          ------------------------------------- */

          .body {
            background-color: #f6f6f6;
            width: 100%;
          }

          /* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
          .container {
            display: block;
            margin: 0 auto !important;
            /* makes it centered */
            max-width: 580px;
            padding: 10px;
            width: 580px;
          }

          /* This should also be a block element, so that it will fill 100% of the .container */
          .content {
            box-sizing: border-box;
            display: block;
            margin: 0 auto;
            max-width: 580px;
            padding: 10px;
          }

          /* -------------------------------------
              HEADER, FOOTER, MAIN
          ------------------------------------- */
          .main {
            background: #ffffff;
            border-radius: 3px;
            width: 100%;
          }

          .wrapper {
            box-sizing: border-box;
            padding: 20px;
          }

          .content-block {
            padding-bottom: 10px;
            padding-top: 10px;
          }

          .footer {
            clear: both;
            margin-top: 10px;
            text-align: center;
            width: 100%;
          }
            .footer td,
            .footer p,
            .footer span,
            .footer a {
              color: #999999;
              font-size: 12px;
              text-align: center;
          }

          /* -------------------------------------
              TYPOGRAPHY
          ------------------------------------- */
          h1,
          h2,
          h3,
          h4 {
            color: #000000;
            font-family: sans-serif;
            font-weight: 400;
            line-height: 1.4;
            margin: 0;
            margin-bottom: 30px;
          }

          h1 {
            font-size: 35px;
            font-weight: 300;
            text-align: center;
            text-transform: capitalize;
          }

          p,
          ul,
          ol {
            font-family: sans-serif;
            font-size: 14px;
            font-weight: normal;
            margin: 0;
            margin-bottom: 15px;
          }
            p li,
            ul li,
            ol li {
              list-style-position: inside;
              margin-left: 5px;
          }

          a {
            color: #3498db;
            text-decoration: underline;
          }

          /* -------------------------------------
              BUTTONS
          ------------------------------------- */
          .btn {
            box-sizing: border-box;
            width: 100%; }
            .btn > tbody > tr > td {
              padding-bottom: 15px; }
            .btn table {
              width: auto;
          }
            .btn table td {
              background-color: #ffffff;
              border-radius: 5px;
              text-align: center;
          }
            .btn a {
              background-color: #ffffff;
              border: solid 1px #3498db;
              border-radius: 5px;
              box-sizing: border-box;
              color: #3498db;
              cursor: pointer;
              display: inline-block;
              font-size: 14px;
              font-weight: bold;
              margin: 0;
              padding: 12px 25px;
              text-decoration: none;
              text-transform: capitalize;
          }

          .btn-primary table td {
            background-color: #3498db;
          }

          .btn-primary a {
            background-color: #3498db;
            border-color: #3498db;
            color: #ffffff;
          }

          /* -------------------------------------
              OTHER STYLES THAT MIGHT BE USEFUL
          ------------------------------------- */
          .last {
            margin-bottom: 0;
          }

          .first {
            margin-top: 0;
          }

          .align-center {
            text-align: center;
          }

          .align-right {
            text-align: right;
          }

          .align-left {
            text-align: left;
          }

          .clear {
            clear: both;
          }

          .mt0 {
            margin-top: 0;
          }

          .mb0 {
            margin-bottom: 0;
          }

          .preheader {
            color: transparent;
            display: none;
            height: 0;
            max-height: 0;
            max-width: 0;
            opacity: 0;
            overflow: hidden;
            mso-hide: all;
            visibility: hidden;
            width: 0;
          }

          .powered-by a {
            text-decoration: none;
          }

          hr {
            border: 0;
            border-bottom: 1px solid #f6f6f6;
            margin: 20px 0;
          }

            /* -------------------------------------
                RESPONSIVE AND MOBILE FRIENDLY STYLES
            ------------------------------------- */
            @media only screen and (max-width: 620px) {
                table.body h1 {
                    font-size: 28px !important;
                    margin-bottom: 10px !important;
                }
                table.body p,
                table.body ul,
                table.body ol,
                table.body td,
                table.body span,
                table.body a {
                    font-size: 16px !important;
                }
                table.body .wrapper,
                table.body .article {
                    padding: 10px !important;
                }
                table.body .content {
                    padding: 0 !important;
                }
                table.body .container {
                    padding: 0 !important;
                    width: 100% !important;
                }
                table.body .main {
                    border-left-width: 0 !important;
                    border-radius: 0 !important;
                    border-right-width: 0 !important;
                }
                table.body .btn table {
                    width: 100% !important;
                }
                table.body .btn a {
                    width: 100% !important;
                }
                table.body .img-responsive {
                    height: auto !important;
                    max-width: 100% !important;
                    width: auto !important;
                }
            }

          /* -------------------------------------
              PRESERVE THESE STYLES IN THE HEAD
          ------------------------------------- */
          @media all {
            .ExternalClass {
              width: 100%;
            }
            .ExternalClass,
            .ExternalClass p,
            .ExternalClass span,
            .ExternalClass font,
            .ExternalClass td,
            .ExternalClass div {
              line-height: 100%;
            }
            .apple-link a {
              color: inherit !important;
              font-family: inherit !important;
              font-size: inherit !important;
              font-weight: inherit !important;
              line-height: inherit !important;
              text-decoration: none !important;
            }
            #MessageViewBody a {
              color: inherit;
              text-decoration: none;
              font-size: inherit;
              font-family: inherit;
              font-weight: inherit;
              line-height: inherit;
            }
            .btn-primary table td:hover {
              background-color: #34495e !important;
            }
            .btn-primary a:hover {
              background-color: #34495e !important;
              border-color: #34495e !important;
            }
          }

        </style>
      </head>
      <body>
      <div class="preheader" style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . $preview_text . '</div>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body">
          <tr>
            <td>&nbsp;</td>
            <td class="container">
              <div class="content">

                <!-- START CENTERED WHITE CONTAINER -->
                <table role="presentation" class="main">

                  <!-- START MAIN CONTENT AREA -->
                  <tr>
                    <td class="wrapper">
                      <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                          <td>'
												. $email_body .
											'</td>
                        </tr>
                      </table>
                    </td>
                  </tr>

                <!-- END MAIN CONTENT AREA -->

                </table>
                <!-- END CENTERED WHITE CONTAINER -->

                <!-- START FOOTER -->
                    <div class="footer">
                      <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                          <td class="content-block">
                            ' . $watermark_text . '
                          </td>
                        </tr>
                      </table>
                    </div>
                <!-- END FOOTER -->

              </div>
            </td>
            <td>&nbsp;</td>
          </tr>
        </table>
      </body>
    </html>
    ';
	}

	/**
	 * Injects preview text into an email body.
	 *
	 * This function adds the provided preview text as a hidden preheader div
	 * at the beginning of the email body.
	 *
	 * @param string $preview_text The preview text to be injected.
	 * @param string $email_body   The original email body.
	 *
	 * @return string The modified email body with the injected preview text.
	 *
	 * @since 1.5.3
	 */
	public static function inject_preview_text_on_email_body( $preview_text, $email_body ) {
		$pre_header = '<div class="preheader" style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . $preview_text . '</div>';
		if ( strpos( $email_body, '</body>' ) ) {
			$pattern     = '/<body(.*?)>(.*?)<\/body>/is';
			$replacement = '<body$1>' . $pre_header . '$2</body>';
			$email_body  = preg_replace( $pattern, $replacement, $email_body );
		} else {
			$email_body = $pre_header . ' ' . $email_body;
		}

		return $email_body;
	}

	/**
	 * Injects a tracking image into an email body for open tracking.
	 *
	 * @access public
	 *
	 * This function generates a tracking URL based on a random hash and injects it as an invisible tracking image
	 * into the provided email body. The tracking image is used to track email opens when the recipient loads the image.
	 *
	 * @param string $rand_hash A random hash used for tracking.
	 * @param string $email_body The email body to inject the tracking image into.
	 * @return string The email body with the tracking image injected.
	 * @since 1.5.14
	 */
	public static function inject_tracking_image_on_email_body( $rand_hash, $email_body ) {
		// Get the site's domain URL.
		$domain_link = get_site_url();

		// Generate the tracking URL with the random hash.
		$track_url = add_query_arg(
			array(
				'mint'  => 1,
				'route' => 'open',
				'hash'  => $rand_hash,
			),
			$domain_link
		);
		// Create an invisible tracking image and inject it into the email body.
		$image_url  = "<img width='1' height='1' src= $track_url />";
		$email_body = $email_body . $image_url;
		return $email_body;
	}

    /**
     * Check if the email has already been sent to the contact.
     * 
     * @param int $automation_id The ID of the automation.
     * @param string $step_id The ID of the step.
     * @param string $email_id The ID of the email.
     * @param int $contact_id The ID of the contact.
     * @param string $status The status of the email.
     * 
     * @return bool True if the email has already been sent, false otherwise.
     * @since 1.14.2
     */
    public static function is_email_already_sent( $automation_id, $step_id, $email_id, $contact_id, $status ){
        global $wpdb;
        $broadcast_table = $wpdb->prefix . 'mint_broadcast_emails';
        // Query the database to check if the email has already been sent.
        $sent_email = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $broadcast_table WHERE automation_id = %d AND step_id = %s AND email_id = %s AND contact_id = %d AND status = %s",
                $automation_id,
                $step_id,
                $email_id,
                $contact_id,
                $status
            )
        );
        return !is_null($sent_email);
    }
}
