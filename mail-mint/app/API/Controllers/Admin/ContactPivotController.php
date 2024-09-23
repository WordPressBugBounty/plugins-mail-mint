<?php
/**
 * Handles requests to the contact groups.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;
use WP_REST_Request;

/**
 * This is the main class that controls the contact groups feature.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class ContactPivotController {

	use Singleton;


	/**
	 * Preapre API values and send to pivot model to delete data
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function delete_groups( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );
		$groups = isset( $params['groups'] ) ? $params['groups'] : null;

		return ContactGroupPivotModel::delete_groups_to_contact( $params['contact_id'], $groups );
	}


	/**
	 * Return group ids related to a contact
	 *
	 * @param mixed $contact_id Contact id to get groups information.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_groups_to_contact( $contact_id ) {
		$results = ContactGroupPivotModel::get_groups_to_contact( $contact_id );

		return json_decode( wp_json_encode( $results ), true );
	}


	/**
	 * Set Contact and groups many to many relation
	 *
	 * @param array $pivot_ids List of groups ids.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function set_groups_to_contact( $pivot_ids ) {
		return ContactGroupPivotModel::add_groups_to_contact( $pivot_ids );
	}



	/**
	 * Get all contacts related to a tag or list or segments
	 *
	 * @param mixed $group_id Group id to get list of contacts.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_contacts_to_group( $group_id ) {
		$results     = ContactGroupPivotModel::get_contacts_to_group( $group_id );
		$contact_ids = json_decode( wp_json_encode( $results ), true );
		$contacts    = array_map(
			function( $contact_id ) {
				return $contact_id['contact_id'];
			},
			$contact_ids
		);
		return $contacts;
	}

}
