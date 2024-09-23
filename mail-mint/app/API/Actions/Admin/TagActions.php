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
use Mint\MRM\DataStores\TagData;
use MRM\Common\MrmCommon;

/**
 * TagActions class contains methods to handle the creation and retrieval of contact groups (tags) from the database.
 * It implements the Action interface, which defines the methods that need to be implemented.
 * 
 * @package /app/API/Actions/Admin
 * @since 1.0.0 
 */
class TagActions implements Action {

	/**
	 * An associative array that stores the response of the API requests made in this class.
	 * @var array
	 */
	private $response = array();

    /**
	 * Creates or updates a tag in the database with the provided data.
	 *
	 * @param array $params An array of input parameters for the tag, including title and data.
	 *
	 * @return array An array containing a status and message indicating success or failure of the database operation.
	 * @since 1.0.0
	 */
	public function create_or_update( $params ) {
        
        $this->response = array(
			'status'  => 'failed',
			'message' => __( 'Something went wrong. Please try again!', 'mrm' ),
		);

		// Tag title validation.
		$title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : null;
		if ( empty( $title ) ) {
			$this->response['status'] = 'failed';
            $this->response['message'] = __( 'Tag name is required', 'mrm' );
			return $this->response;
		}

		if ( strlen( $title ) > 60 ) {
            $this->response['status'] = 'failed';
            $this->response['message'] = __( 'Name must be no longer than 60 characters', 'mrm' );
			return $this->response;
		}

		// Tag object create and insert or update to database.
		$args = array(
			'title' => $title,
			'data'  => isset( $params['data'] ) ? $params['data'] : '',
		);

		$tag = new TagData( $args );

		$success = isset( $params['tag_id'] )
                ? ContactGroupModel::update( $tag, $params['tag_id'], 'tags' )
                : ContactGroupModel::insert( $tag, 'tags' );

        if ( $success ) {
            $this->response['status']  = 'success';
			$this->response['data']    = $success;
            $this->response['message'] = __( 'Tag has been saved successfully', 'mrm' );
            return $this->response;
        }
        return $this->response;
	}

    /**
	 * Retrieves a tag of contact groups from the database based on the provided parameters.
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

		// Tag Search keyword.
		$search = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';

		$groups = ContactGroupModel::get_all( 'tags', $offset, $per_page, $search, $order_by, $order_type );

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
	 * Deletes all contact groups specified in the parameter tag_ids.
	 *
	 * @param array $params An array containing the tag_ids of the contact groups to be deleted.
	 * @return array An array containing the status and message of the operation.
	 * @since 1.0.0
	 */
	public function delete_all( $params ) {
		$success = ContactGroupModel::destroy_all( $params['tag_ids'] );

		if ( $success ) {
			$this->response['status']  = 'success';
        	$this->response['message'] = __( 'Tags has been deleted successfully', 'mrm' );
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
		$success = ContactGroupModel::destroy( $params['tag_id'] );

		if ( $success ) {
			$this->response['status']  = 'success';
        	$this->response['message'] = __( 'Tag has been deleted successfully', 'mrm' );
		}

		return $this->response;
	}

    /**
	 * Retrieves all tags from the ContactGroupModel and formats them for use in a dropdown.
	 *
	 * @return array An array containing the status of the operation, a message, and an array of tags formatted for use in a dropdown.
	 */
	public function get_tags_for_dropdown() {
		$groups = ContactGroupModel::get_all_to_custom_select( 'tags' );

		if ( is_array( $groups ) && ! is_wp_error( $groups ) ) {
			return [
				'status'  => 'success',
				'message' => __( 'All tags have been fetched successfully!', 'mrm' ),
				'data'    => $groups
			];
		}

		return [
			'status'  => 'failed',
			'message' => __( 'Error while fetching the tags', 'mrm' ),
			'data'    => []
		];
	}

}