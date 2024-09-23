<?php
 /** 
  * Mail Mint 
  * 
  * @author [MRM Team] 
  * @email [support@getwpfunnels.com] 
  * @package /app/API/Actions/Admin 
  */ 

namespace Mint\MRM\API\Actions;

use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataStores\ListData;
use MRM\Common\MrmCommon;

/**
 * ListActions class contains methods to handle the creation and retrieval of contact groups (lists) from the database.
 * It implements the Action interface, which defines the methods that need to be implemented.
 * 
 * @package /app/API/Actions/Admin
 * @since 1.0.0 
 */
class ListActions implements Action {

	/**
	 * An associative array that stores the response of the API requests made in this class.
	 * @var array
	 */
	private $response = array();

    /**
	 * Creates or updates a list in the database with the provided data.
	 *
	 * @param array $params An array of input parameters for the list, including title and data.
	 *
	 * @return array An array containing a status and message indicating success or failure of the database operation.
	 * @since 1.0.0
	 */
	public function create_or_update( $params ) {
        
        $this->response = array(
			'status'  => 'failed',
			'message' => __( 'Something went wrong. Please try again!', 'mrm' ),
		);

		// List title validation.
		$title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : null;
		if ( empty( $title ) ) {
			$this->response['status'] = 'failed';
            $this->response['message'] = __( 'List name is required', 'mrm' );
			return $this->response;
		}

		if ( strlen( $title ) > 60 ) {
            $this->response['status'] = 'failed';
            $this->response['message'] = __( 'Name must be no longer than 60 characters', 'mrm' );
			return $this->response;
		}

		// List object create and insert or update to database.
		$args = array(
			'title' => $title,
			'data'  => isset( $params['data'] ) ? $params['data'] : '',
		);

		$list = new ListData( $args );

		$success = isset( $params['list_id'] )
                ? ContactGroupModel::update( $list, $params['list_id'], 'lists' )
                : ContactGroupModel::insert( $list, 'lists' );

        if ( $success ) {
            $this->response['status']  = 'success';
			$this->response['data']    = $success;
            $this->response['message'] = __( 'List has been saved successfully', 'mrm' );
            return $this->response;
        }
        return $this->response;
	}

	/**
	 * Retrieves a list of contact groups from the database based on the provided parameters.
	 *
	 * @param array $params An array of input parameters, including page, per-page, order-by, order-type, and search.
	 * @return array An array containing a status, message, and data array with contact group information.
	 * @since 1.0.0
	 */
	public function get_all( $params ) {

		$page     = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page = isset( $params['per-page'] ) ? absint( $params['per-page'] ) : 0;
		$offset   = ( $page - 1 ) * $per_page;

		$order_by   = isset( $params['order-by'] ) ? strtolower( $params['order-by'] ) : 'id';
		$order_type = isset( $params['order-type'] ) ? strtolower( $params['order-type'] ) : 'desc';

		// List Search keyword.
		$search = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';

		$groups = ContactGroupModel::get_all( 'lists', $offset, $per_page, $search, $order_by, $order_type );

		// Prepare created at as WP admin general settings date format.
		if ( isset( $groups['data'] ) ) {
			$groups['data'] = array_map(
				function( $group ) {
					if ( isset( $group['created_at'] ) ) {
						$group['created_at'] = MrmCommon::date_time_format_with_core( $group['created_at'] );
					}
					return $group;
				},
				$groups['data']
			);
		}
		
		// Count contacts groups.
		$groups['count_groups'] = array(
			'lists'    => ContactGroupModel::get_groups_count( 'lists' ),
			'tags'     => ContactGroupModel::get_groups_count( 'tags' ),
			'contacts' => ContactModel::get_contacts_count(),
			'segments' => ContactGroupModel::get_groups_count( 'segments' ),
		);

		$this->response['status']  = 'success';
        $this->response['message'] = __( 'Query Successfull', 'mrm' );
		$this->response['data']    = $groups;
        return $this->response;
	}

	/**
	 * Retrieves a single contact group by ID.
	 *
	 * @param array $params Parameters for retrieving a single contact group.
	 * @return array The retrieved contact group.
	 * @since 1.0.0
	 */
	public function get_single( $params ) {
		$group = ContactGroupModel::get( $params['list_id'] );

		$this->response['status']  = 'success';
        $this->response['message'] = __( 'Query Successfull', 'mrm' );
		$this->response['data']    = $group;
		return $this->response;
	}

	/**
	 * Deletes all contact groups specified in the parameter list_ids.
	 *
	 * @param array $params An array containing the list_ids of the contact groups to be deleted.
	 * @return array An array containing the status and message of the operation.
	 * @since 1.0.0
	 */
	public function delete_all( $params ) {
		$success = ContactGroupModel::destroy_all( $params['list_ids'] );

		if ( $success ) {
			$this->response['status']  = 'success';
        	$this->response['message'] = __( 'Lists has been deleted successfully', 'mrm' );
		}

		return $this->response;
	}

	/**
	 * Deletes a single contact group.
	 *
	 * @param array $params An array containing the ID of the contact group to delete.
	 * @return array An array with the response message indicating whether the operation 
	 * @since 1.0.0
	 */
	public function delete_single( $params ) {
		$success = ContactGroupModel::destroy( $params['list_id'] );

		if ( $success ) {
			$this->response['status']  = 'success';
        	$this->response['message'] = __( 'Lists has been deleted successfully', 'mrm' );
		}

		return $this->response;
	}

	/**
	 * Get lists [ids & titles] for custom dropdown
	 *
	 * @return array
	 */
	public function get_lists_for_dropdown() {
		$groups = ContactGroupModel::get_all_to_custom_select( 'lists' );

		if ( is_array( $groups ) && ! is_wp_error( $groups ) ) {
			return [
				'status'  => 'success',
				'message' => __( 'All lists have been fetched successfully!', 'mrm' ),
				'data'    => $groups
			];
		}

		return [
			'status'  => 'failed',
			'message' => __( 'Error while fetching the lists', 'mrm' ),
			'data'    => []
		];
	}
}