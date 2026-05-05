<?php
/**
 * REST API Tag Controller
 *
 * Handles requests to the tags endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\API\Controllers\Traits\CrudControllerTrait;
use Mint\MRM\Database\Repositories\ContactGroupRepository;
use Mint\MRM\DataBase\Models\ContactModel;
use MRM\Common\MrmCommon;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * TagController — manages tag CRUD via CrudControllerTrait.
 *
 * Uses legacy_success_response() / legacy_error_response() from the trait
 * to preserve the { status, message } envelope the frontend expects.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since   1.0.0
 */
class TagController extends AdminBaseController {

	use CrudControllerTrait;

	/**
	 * Return the repository instance for tags.
	 *
	 * @since 1.19.5
	 *
	 * @return ContactGroupRepository
	 */
	protected function repository(): ContactGroupRepository {
		return new ContactGroupRepository( 'tags' );
	}

	/**
	 * Return the request parameter key used for the tag ID.
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	protected function idKey(): string {
		return 'tag_id';
	}

	/**
	 * Validate tag data before create/update.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data Request data.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	protected function validate( array $data ) {
		$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';

		if ( empty( $title ) ) {
			return new WP_Error(
				'missing_title',
				__( 'Tag name is required', 'mrm' )
			);
		}

		if ( strlen( $title ) > 60 ) {
			return new WP_Error(
				'title_too_long',
				__( 'Name must be no longer than 60 characters', 'mrm' )
			);
		}

		return true;
	}

	/**
	 * Override get_all to match legacy response envelope.
	 *
	 * Legacy shape: { status, message, data: { data, total_pages, total_count, count_groups } }
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_all( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();

		// Map legacy hyphenated query param names to repository param names.
		$repo_params = array(
			'page'     => isset( $params['page'] ) ? absint( $params['page'] ) : 1,
			'per_page' => isset( $params['per-page'] ) ? absint( $params['per-page'] ) : 10,
			'order_by' => isset( $params['order-by'] ) ? $params['order-by'] : 'id',
			'order'    => isset( $params['order-type'] ) ? $params['order-type'] : 'DESC',
			'search'   => isset( $params['search'] ) ? $params['search'] : '',
		);

		$repo   = $this->repository();
		$result = $repo->list( $repo_params );

		// Format created_at to match legacy date formatting.
		if ( ! empty( $result['data'] ) ) {
			$result['data'] = array_map(
				function ( $row ) {
					if ( isset( $row['created_at'] ) ) {
						$row['created_at'] = MrmCommon::date_time_format_with_core( $row['created_at'] );
					}
					return $row;
				},
				$result['data']
			);
		}

		// Build count_groups to match legacy response.
		$count_groups = array(
			'lists'    => $repo->countByType( 'lists' ),
			'tags'     => $repo->countByType( 'tags' ),
			'contacts' => ContactModel::get_contacts_count(),
			'segments' => $repo->countByType( 'segments' ),
		);

		return $this->legacy_success_response(
			__( 'Query Successfull', 'mrm' ),
			array(
				'data' => array(
					'data'         => $result['data'],
					'total_pages'  => $result['total_pages'],
					'total_count'  => $result['total'],
					'count_groups' => $count_groups,
				),
			)
		);
	}

	/**
	 * Override create_or_update to match legacy response envelope.
	 *
	 * Legacy shape on create: { status: 'success', data: $new_id, message }
	 * Legacy shape on update: { status: 'success', message }
	 * Legacy shape on error:  { status: 'failed', message }
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		$validation = $this->validate( $data );
		if ( is_wp_error( $validation ) ) {
			return $this->legacy_error_response( $validation->get_error_message() );
		}

		$id_key = $this->idKey();
		$id     = $request->get_param( $id_key );
		$repo   = $this->repository();

		if ( $id ) {
			$id       = (int) $id;
			$affected = $repo->update( $id, $data );

			if ( is_wp_error( $affected ) ) {
				return $this->legacy_error_response(
					__( 'Failed to save tag.', 'mrm' ),
					500
				);
			}

			/**
			 * Fires after a contact group is saved (created or updated).
			 *
			 * @since 1.19.5
			 *
			 * @param int   $id   Entity ID.
			 * @param array $data Saved data.
			 */
			do_action( "mailmint_{$this->entityName()}_saved", $id, $data );

			return $this->legacy_success_response(
				__( 'Tag has been saved successfully', 'mrm' )
			);
		}

		$new_id = $repo->create( $data );

		if ( is_wp_error( $new_id ) ) {
			return $this->legacy_error_response(
				__( 'Failed to save tag.', 'mrm' ),
				500
			);
		}

		if ( ! $new_id ) {
			return $this->legacy_error_response(
				__( 'Failed to save tag.', 'mrm' ),
				500
			);
		}

		/** This action is documented above. */
		do_action( "mailmint_{$this->entityName()}_saved", $new_id, $data );

		return $this->legacy_success_response(
			__( 'Tag has been saved successfully', 'mrm' ),
			array( 'data' => $new_id )
		);
	}

	/**
	 * Override delete_single to match legacy response envelope.
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_single( WP_REST_Request $request ) {
		$id = (int) $request->get_param( $this->idKey() );

		if ( ! $id ) {
			return $this->legacy_error_response(
				__( 'Something went wrong. Please try again!', 'mrm' )
			);
		}

		$deleted = $this->repository()->destroy( $id );

		if ( is_wp_error( $deleted ) ) {
			return $this->legacy_error_response(
				__( 'Failed to delete tag.', 'mrm' ),
				500
			);
		}

		if ( ! $deleted ) {
			return $this->legacy_error_response(
				__( 'Tag not found.', 'mrm' ),
				404
			);
		}

		/**
		 * Fires after a contact group is deleted.
		 *
		 * @since 1.19.5
		 *
		 * @param int $id Deleted entity ID.
		 */
		do_action( "mailmint_{$this->entityName()}_deleted", $id );

		return $this->legacy_success_response(
			__( 'Tag has been deleted successfully', 'mrm' )
		);
	}

	/**
	 * Override delete_all to match legacy response envelope.
	 *
	 * Legacy expects 'tag_ids' param in the request body.
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_all( WP_REST_Request $request ) {
		$ids = $request->get_param( 'tag_ids' );
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return $this->legacy_error_response(
				__( 'Something went wrong. Please try again!', 'mrm' )
			);
		}

		$ids = array_map( 'intval', $ids );

		$deleted = $this->repository()->destroyMany( $ids );

		if ( is_wp_error( $deleted ) ) {
			return $this->legacy_error_response(
				__( 'Failed to delete tags.', 'mrm' ),
				500
			);
		}

		$entity_name = $this->entityName();
		foreach ( $ids as $id ) {
			/** This action is documented in delete_single(). */
			do_action( "mailmint_{$entity_name}_deleted", $id );
		}

		return $this->legacy_success_response(
			__( 'Tags has been deleted successfully', 'mrm' )
		);
	}

	/**
	 * Non-CRUD: dropdown data for tag selectors.
	 *
	 * Preserved from legacy — not part of CrudControllerTrait.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_tags_for_dropdown() {
		try {
			$groups = $this->repository()->allForDropdown();

			return $this->legacy_success_response(
				__( 'All tags have been fetched successfully!', 'mrm' ),
				array( 'data' => array( 'data' => $groups ) )
			);
		} catch ( \Exception $e ) {
			return $this->legacy_error_response(
				__( 'Error while fetching the tags', 'mrm' )
			);
		}
	}
}
