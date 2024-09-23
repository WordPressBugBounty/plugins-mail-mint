<?php
/**
 * REST API Field Group Controller
 *
 * Handles requests to the field groups endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use Exception;
use MRM\Common\MrmCommon;
use MRM\Data\FieldGroup;
use MRM\Models\FieldGroup as ModelsFieldGroup;

/**
 * This is the main class that controls the field groups feature. Its responsibilities are:
 *
 * - Create or update a field group
 * - Delete single or multiple field groups
 * - Retrieve single or multiple field groups
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class FieldGroupController extends AdminBaseController {

	use Singleton;

	/**
	 * Get and send response to create or update a field group
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		// Group title validation.
		$title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : null;

		if ( empty( $title ) ) {
			return $this->get_error_response( __( 'Title is mandatory', 'mrm' ), 400 );
		}

		// Field group object create and insert or update to database.
		try {
			$field_group = new FieldGroup( $params );

			if ( isset( $params['group_id'] ) ) {
				$success = ModelsFieldGroup::update( $field_group, $params['group_id'] );
			} else {
				$success = ModelsFieldGroup::insert( $field_group );
			}

			if ( $success ) {
				return $this->get_success_response( __( 'Field group has been saved successfully', 'mrm' ), 201 );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		}
	}


	/**
	 * Request for deleting a single field group
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$success = ModelsFieldGroup::destroy( $params['group_id'] );
		if ( $success ) {
			return $this->get_success_response( __( 'Field group has been deleted successfully', 'mrm' ), 200 );
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * TODO: complete this function in order to delete multilple request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return void
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
	}


	/**
	 * Get all group fields request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$groups = ModelsFieldGroup::get_all();

		if ( isset( $groups ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $groups );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function use to get single field group
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$group = ModelsFieldGroup::get( $params['group_id'] );

		if ( isset( $group ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $group );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}

}
