<?php
/**
 * REST API Custom Field Controller
 *
 * Handles requests to the custom-fields endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.24.5
 */

namespace Mint\MRM\Admin\API\Controllers;

use WP_REST_Request;
use Exception;
use Mint\MRM\Constants;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\CustomFieldModel;
use Mint\MRM\DataStores\CustomFieldData;
use MRM\Common\MrmCommon;

/**
 * This is the main class that controls the custom fields feature. Its responsibilities are:
 *
 * - Create or update a custom field
 * - Delete single or multiple custom fields
 * - Retrieve single or multiple custom fields
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class CustomFieldController extends AdminBaseController {

	/**
	 * Field object arguments
	 *
	 * @var object
	 * @since 1.24.5
	 */
	public $args;


	/**
	 * Get and send response to create or update a custom field
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_Error|WP_REST_Response
	 * @since 1.24.5
	 */
	public function create_or_update( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		// Filed title validation.
		$title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : null;

		if ( empty( $title ) && ! isset( $params['field_id'] ) ) {
			return $this->get_error_response( __( 'Field name is mandatory.', 'mrm' ), 200 );
		}

		$slug = isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : sanitize_title( $title );

		// On create, auto-suffix a colliding slug (-2, -3, ...) instead of rejecting it.
		if ( ! isset( $params['field_id'] ) ) {
			$slug = CustomFieldModel::generate_unique_slug( $slug );
		}

		$primary_fields = Constants::$primary_fields;
		$exist          = CustomFieldModel::is_field_exist( $slug );
		$title_exist    = CustomFieldModel::is_field_exist( $title );

		if ( in_array( $slug, $primary_fields, true ) ) {
			return $this->get_error_response( __( 'Field is already available, Can not Save.', 'mrm' ), 200 );
		}

		// Checking if title exists and it's not an update operation.
		if ( $title_exist && ! isset( $params['field_id'] ) ) {
			return $this->get_error_response( __( 'Field is already available, Can not Save.', 'mrm' ), 200 );
		}

		// Checking if title exists and it's an update operation.
		if ( $title_exist && isset( $params['field_id'] ) ) {
			$get_id_by_title = CustomFieldModel::get_id_by_slug( $title );
			if ( $get_id_by_title !== $params['field_id'] ) {
				return $this->get_error_response( __( 'Field is already available.', 'mrm' ), 200 );
			}
		}

		// Checking if slug exists and it's not an update operation.
		if ( $exist && ! isset( $params['field_id'] ) ) {
			return $this->get_error_response( __( 'Same slug has been assigned to another Field, Can not Save.', 'mrm' ), 200 );
		}

		// Checking if slug exists and it's not update operation.
		if ( $exist && isset( $params['field_id'] ) ) {
			$get_id_by_slug = CustomFieldModel::get_id_by_slug( $slug );
			if ( $get_id_by_slug !== $params['field_id'] ) {
				return $this->get_error_response( __( 'Same slug has been assigned to another Field, Can not Save.', 'mrm' ), 200 );
			}
		}

		// Field type validation.
		$type = isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : null;

		if ( empty( $type ) ) {
			return $this->get_error_response( __( 'Type is mandatory, Can not Save.', 'mrm' ), 202 );
		}

		if ( ! empty( $params['meta'] ) && isset( $params['meta']['options'] ) ) {
			$options = $params['meta']['options'];
			$options = array_filter( $options );
		}

		$placeholder = isset( $params['meta']['placeholder'] ) ? sanitize_text_field( $params['meta']['placeholder'] ) : '';
		$label       = isset( $params['meta']['label'] ) ? sanitize_text_field( $params['meta']['label'] ) : $title;

		$meta = array();
		if ( ! empty( $options ) ) {
			$options         = array_filter(
				$options,
				function( $value ) {
					if ( is_array( $value ) ) {
						return false;
					}
					return trim( $value ) !== '';
				}
			);
			$meta['options'] = array_values( $options );
		}
		if ( ! empty( $placeholder ) ) {
			$meta['placeholder'] = $placeholder;
		}
		if ( ! empty( $label ) ) {
			$meta['label'] = $label;
		}

		$this->args = array(
			'title' => $title,
			'slug'  => $slug,
			'type'  => $type,
			'meta'  => $meta,
		);

		if ( ! isset( $params['field_id'] ) ) {
			$this->args['position'] = CustomFieldModel::get_next_position();
		}

		$field = new CustomFieldData( $this->args );

		// Field object create and insert or update to database.
		try {
			if ( isset( $params['field_id'] ) ) {
				$field_id = isset( $params['field_id'] ) ? $params['field_id'] : '';

				$success = CustomFieldModel::update( $field, $field_id );
			} else {
				$success = CustomFieldModel::insert( $field );
			}

			if ( $success ) {
				return $this->get_success_response( __( 'Field has been saved successfully', 'mrm' ), 201 );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Custom Field is not valid', 'mrm' ), 400 );
		}
	}


	/**
	 * Request for deleting a single field
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_Error|WP_REST_Response
	 * @since 1.24.5
	 */
	public function delete_single( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$field_id = isset( $params['field_id'] ) ? $params['field_id'] : '';

		$field   = CustomFieldModel::get( $field_id );
		$success = CustomFieldModel::destroy( $field_id );
		if ( $success ) {
			if ( ! empty( $field->slug ) ) {
				ContactModel::delete_meta_by_key( $field->slug );
			}
			return $this->get_success_response( __( 'Field has been deleted successfully', 'mrm' ), 200 );
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Delete multiple custom fields and their associated contact meta values.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_Error|WP_REST_Response
	 * @since 1.24.5
	 */
	public function delete_all( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$field_ids = isset( $params['field_ids'] ) ? $params['field_ids'] : array();

		if ( empty( $field_ids ) || ! is_array( $field_ids ) ) {
			return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
		}

		foreach ( $field_ids as $field_id ) {
			$field = CustomFieldModel::get( $field_id );
			if ( CustomFieldModel::destroy( $field_id ) && ! empty( $field->slug ) ) {
				ContactModel::delete_meta_by_key( $field->slug );
			}
		}

		return $this->get_success_response( __( 'Fields have been deleted successfully', 'mrm' ), 200 );
	}


	/**
	 * Persist a new field order.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_Error|WP_REST_Response
	 * @since 1.24.5
	 */
	public function reorder( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$field_ids = isset( $params['field_ids'] ) ? $params['field_ids'] : array();

		if ( empty( $field_ids ) || ! is_array( $field_ids ) ) {
			return $this->get_error_response( __( 'Failed to reorder fields', 'mrm' ), 400 );
		}

		foreach ( array_values( $field_ids ) as $position => $field_id ) {
			CustomFieldModel::update_position( $field_id, $position );
		}

		return $this->get_success_response( __( 'Fields have been reordered successfully', 'mrm' ), 200 );
	}


	/**
	 * Get all fields request
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_Error|WP_REST_Response
	 * @since 1.24.5
	 */
	public function get_all( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$fields = CustomFieldModel::get_all();

		$fields['data'] = array_map(
			function( $field ) {
				if ( ! empty( $field ) && $field['meta'] ) {
					$field['meta'] = maybe_unserialize( $field['meta'] );
				}
				return $field;
			},
			$fields['data']
		);

		if ( isset( $fields ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $fields );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function use to get single field
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_Error|WP_REST_Response
	 * @since 1.24.5
	 */
	public function get_single( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$field = CustomFieldModel::get( $params['field_id'] );

		if ( ! empty( $field ) && $field->meta ) {
			$field->meta = maybe_unserialize( $field->meta );
		}

		if ( isset( $field ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $field );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}
}
