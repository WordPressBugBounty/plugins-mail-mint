<?php
/**
 * CampaignEmailBuilderModel class.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataBase\Models;

use Mint\MRM\DataBase\Tables\CampaignEmailBuilderSchema;
use Mint\MRM\DataBase\Tables\CampaignSchema;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * CampaignEmailBuilderModel class
 *
 * Manage Campaign email builder related database operations.
 *
 * @package Mint\MRM\DataBase\Models
 * @namespace Mint\MRM\DataBase\Models
 *
 * @version 1.0.0
 */
class CampaignEmailBuilderModel {

	use Singleton;

	/**
	 * Check is it a new email template or not
	 *
	 * @param int $email_id email_id.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function is_new_email_template( $email_id ) {
		global $wpdb;
		$email_builder_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare( "SELECT * FROM $email_builder_table WHERE email_id = %d", array( $email_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_row( $query ); // db call ok. ; no-cache ok.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		if ( $results ) {
			return true;
		}
		return false;
	}

	/**
	 * Run SQL query to insert campaign email builder information into database
	 *
	 * @param mixed $args insert arguments.
	 *
	 * @return int|bool
	 * @since 1.0.0
	 */
	public static function insert( $args ) {
		global $wpdb;
		$email_builder_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		$args['created_at']  = current_time( 'mysql' );
		$inserted            = $wpdb->insert( $email_builder_table, $args ); // db call ok.
		if ( $inserted ) {
			return $wpdb->insert_id;
		}
		return false;
	}

	/**
	 * Run SQL query to update campaign email builder information into database.
	 *
	 * @param int   $email_id email id.
	 * @param array $args update arguments.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function update( $email_id, $args ) {
		global $wpdb;
		$email_builder_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		$args['updated_at']  = current_time( 'mysql' );
		$wpdb->update(
			$email_builder_table,
			$args,
			array(
				'email_id' => $email_id,
			)
		); // db call ok. ; no-cache ok.
	}


	/**
	 * Get single email template.
	 *
	 * @param int $id email id.
	 * @return array|bool|object|void|null
	 *
	 * @since 1.0.0
	 */
	public static function get( $id ) {
		global $wpdb;
		$email_builder_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = $wpdb->prepare( "SELECT id, email_id, editor_type, json_data, email_body FROM $email_builder_table WHERE email_id=%s", $id );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$email = $wpdb->get_row( $select_query, ARRAY_A ); // db call ok. ; no-cache ok.

		if ( $email ) {
			$email[ 'json_data' ] = isset( $email[ 'json_data' ] ) ? maybe_unserialize( $email[ 'json_data' ] ) : '';
			return $email;
		}
		return null;
	}


	/**
	 * Get email body by id.
	 *
	 * @param int $id email id.
	 * @return array|bool|object|void|null
	 *
	 * @since 1.0.0
	 */
	public static function get_email_body( $id ) {
		global $wpdb;
		$email_builder_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;

		$select_query = $wpdb->prepare( "SELECT `email_body`, `editor_type` FROM {$email_builder_table} WHERE email_id=%s", $id ); //phpcs:ignore

		$email = $wpdb->get_row( $select_query, ARRAY_A ); //phpcs:ignore

		$email[ 'email_body' ] = !empty( $email[ 'email_body' ] ) ? maybe_unserialize( $email[ 'email_body' ] ) : '';

		return $email;
	}

	/**
	 * Prepare email body with Mail Mint watermark
	 *
	 * @return mixed|string
	 *
	 * @since 1.0.0
	 */
	public static function get_email_footer_watermark() {
		if ( apply_filters( 'mail_mint_remove_email_footer_watermark', true ) ) {
			return '<div style="">
                    <!--[if mso | IE]><table align="center" border="0" cellpadding="0" cellspacing="0" class="" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-height-rule:exactly;"><![endif]-->
                    <div style="margin:0px auto;max-width:600px;">
                      <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;">
                        <tbody>
                          <tr>
                            <td style="direction:ltr;font-size:0px;padding:0 0 5px 0;text-align:center;">
                              <!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="" style="vertical-align:top;width:600px;" ><![endif]-->
                              <div class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;">
                                <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%">
                                  <tbody>
                                    <tr>
                                      <td align="center" style="font-size:0px;padding:10px 25px;word-break:break-word;">
                                        <div style="font-family:Ubuntu, Helvetica, Arial, sans-serif;font-size:13px;line-height:1;text-align:center;color:#000000;">Powered by <a href="https://getwpfunnels.com/email-marketing-automation-mail-mint/?utm_source=emailfooter&utm_medium=freeusers&utm_campaign=poweredbymailmint" style="text-decoration: underline; color: black;">Mail Mint</a></div>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                              <!--[if mso | IE]></td></tr></table><![endif]-->
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <!--[if mso | IE]></td></tr></table><![endif]-->
                  </div>';
		}

		return '';
	}

	/**
	 * Get email builder ids for a given campaign id.
	 *
	 * @param mixed $campaign_id Campaign ID for which to fetch email builder IDs.
	 *
	 * @return string Comma separated list of email builder IDs.
	 * @since 1.2.2
	 */
	public static function get_email_ids_by_campaign_id( $campaign_id ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;

		$ids = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $campaign_emails_table WHERE campaign_id = %d", $campaign_id ), ARRAY_A ); //phpcs:ignore.
		$ids = implode( ',', array_column( $ids, 'id' ) );
		return $ids;
	}

	/**
	 * Get email step ids by campaign ids.
	 *
	 * @param mixed $campaign_ids Array of campaign ids.
	 *
	 * @return string Comma-separated string of email step ids.
	 * @since 1.2.2
	 */
	public static function get_step_ids_by_campaign_ids( $campaign_ids ) {
		global $wpdb;
		$campaign_emails_table = $wpdb->prefix . CampaignSchema::$campaign_emails_table;
		$ids                   = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $campaign_emails_table WHERE campaign_id IN(%1s)", $campaign_ids ), ARRAY_A ); //phpcs:ignore.
		$ids                   = implode( ',', array_column( $ids, 'id' ) );
		return $ids;
	}

	/**
	 * Deletes all child rows in the campaign email builder table based on a list of email IDs.
	 *
	 * @param mixed $email_ids Comma-separated list of email IDs.
	 *
	 * @return void
	 * @since 1.2.2
	 */
	public static function delete_all_child_row_by_email_ids( $email_ids ) {
		global $wpdb;
		$builder_table = $wpdb->prefix . CampaignEmailBuilderSchema::$table_name;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %1s WHERE email_id IN(%1s)', $builder_table, $email_ids ) ); //  phpcs:ignore.
	}
}
