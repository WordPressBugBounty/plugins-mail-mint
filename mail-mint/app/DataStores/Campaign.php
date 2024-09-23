<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/DataStores
 */

namespace Mint\MRM\DataStores;

use Mint\MRM\DataBase\Tables\ContactGroupPivotSchema;
use Mint\MRM\DataBase\Tables\ContactSchema;

/**
 * [Manage storing data into database]
 *
 * @desc Manage plugin's assets
 * @package /app/DataStores
 * @since 1.0.0
 */
class Campaign {

	/**
	 * MRM campaign ID
	 *
	 * @var int|string $campaign_id
	 * @since 1.0.0
	 */
	protected $campaign_id;

	/**
	 * Initialize class functionalities
	 *
	 * @param int|string $campaign_id Campaign ID.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $campaign_id ) {
		$this->campaign_id = $campaign_id;
	}


	/**
	 * Get the campaign by campaign id
	 *
	 * @param string $status Status of the Campaign.
	 *
	 * @return array|object|\stdClass|void|null
	 * @since 1.0.0
	 */
	private function get_the_campaign( $status = 'processing' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mint_campaigns';
		$sql        = $wpdb->prepare( 'SELECT * FROM %s WHERE id=%s and status = %s', $table_name, $this->campaign_id, $status );

		return $wpdb->get_row( $sql ); //phpcs:ignore
	}


	/**
	 * Triggers campaign email
	 *
	 * @param int|string $per_batch Number of data to fetch from database.
	 *
	 * @return array|false|object|\stdClass|void
	 * @since 1.0.0
	 */
	public function trigger_email( $per_batch = 500 ) {
		$campaign = $this->get_the_campaign();
		if ( ! $campaign ) {
			return false;
		}
		$settings   = $campaign->settings;
		$offset     = $campaign->total_recipients ? $campaign->total_recipients : 0;
		$contacts   = $this->get_contacts( $settings, $per_batch, $offset );
		$result     = $this->prepare_campaign_email_lists( $contacts, $settings );
		$should_run = ! ! $result;

		while ( $should_run && ! mrm_memory_exceed() ) {
			$should_run = ! ! $result;
			if ( $should_run ) {
				$campaign = $this->get_the_campaign();
				$settings = $campaign->settings;
				$contacts = $this->get_contacts( $settings, $per_batch, $campaign->total_recipients );
				$result   = $this->prepare_campaign_email_lists( $contacts, $settings );
			}
		}

		// all email is ready to be sent.
		if ( ! $result ) {
			mrm_update_campaign(
				array(
					'id'     => $this->campaign_id,
					'status' => 'processed',
				)
			);
		}

		return $campaign;
	}


	/**
	 * Prepare lists of campaign email
	 *
	 * @param object|array $contacts MRM Contact object.
	 * @param object|array $settings Settings.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private function prepare_campaign_email_lists( $contacts, $settings ) {
		if ( ! $contacts ) {
			return false;
		}
		global $wpdb;
		$table        = $wpdb->prefix . 'mint_campaign_emails';
		$time         = current_time( 'mysql' );
		$inserted_ids = array();
		foreach ( $contacts as $contact ) {
			$campaign_email = array(
				'campaign_id' => $this->campaign_id,
				'contact_id'  => $contact->id,
				'status'      => 'scheduled',
				'created_at'  => $time,
				'updated_at'  => $time,
			);

			$inserted_id    = $wpdb->insert( //phpcs:ignore
				$table,
				$campaign_email
			);
			$inserted_ids[] = $inserted_id;
		}

		// update recipient count.
		mrm_update_campaign(
			array(
				'id'               => $this->campaign_id,
				'total_recipients' => count( $inserted_ids ),
			)
		);

		return $inserted_ids;
	}


	/**
	 * Gets MRM Contact object
	 *
	 * @param object|array $settings Settings.
	 * @param int|string   $per_batch Number of data to fetch from database.
	 * @param int|string   $offset Number of data to ignore from database.
	 *
	 * @return array|false|object|\stdClass[]
	 */
	private function get_contacts( $settings, $per_batch, $offset = 0 ) {
		if ( ! $settings ) {
			return false;
		}

		$settings = unserialize( $settings ); //phpcs:ignore

		if ( ! $settings ) {
			return false;
		}

		global $wpdb;
		$table             = $wpdb->prefix . ContactSchema::$table_name;
		$group_pivot_table = $wpdb->prefix . ContactGroupPivotSchema::$table_name;

		$where_group        = array();
		$skip_where_query   = false;
		$where_query        = " WHERE status = 'subscribed'";
		$include_conditions = $settings[ 'contact' ];
		$sql                = "SELECT distinct(c.id) FROM {$table} as c INNER JOIN $group_pivot_table as p ON c.id=p.contact_id ";
		foreach ( $include_conditions as $condition ) {
			$list_id = is_array( $condition[ 'lists' ] ) ? 'all' : $condition[ 'lists' ];
			$tag_id  = is_array( $condition[ 'tags' ] ) ? 'all' : $condition[ 'tags' ];

			if ( ! $list_id || ! $tag_id ) {
				continue;
			}

			if ( 'all' === $list_id && 'all' === $tag_id ) {
				$skip_where_query = true;
				$sql              = 'SELECT DISTINCT (id) FROM %s';
			} elseif ( 'all' === $list_id ) {
				$where_group[] = $tag_id;
			} elseif ( 'all' === $tag_id ) {
				$where_group[] = $list_id;
			} else {
				$where_group[] = $list_id;
				$where_group[] = $tag_id;
			}
		}

		if ( ! $skip_where_query ) {
			$where_query = ' AND p.group_id IN (' . implode( ', ', $where_group ) . ')';
		}

		$results = $wpdb->get_results( $wpdb->prepare( $sql . "{$where_query} LIMIT %d OFFSET %d", $table, (int) $per_batch, (int) $offset ) ); //phpcs:ignore
		if ( $results ) {
			return $results;
		}

		return false;
	}
}
