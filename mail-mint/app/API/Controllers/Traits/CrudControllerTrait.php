<?php
/**
 * CrudControllerTrait — Standard REST CRUD endpoint methods for Mail Mint controllers.
 *
 * Provides create_or_update, get_all, get_single, delete_single, and delete_all
 * so that concrete controllers only declare repository(), idKey(), and validate().
 *
 * @package Mint\MRM\API\Controllers\Traits
 * @since   1.19.5
 */

namespace Mint\MRM\API\Controllers\Traits;

use Mint\MRM\Database\AbstractRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Trait CrudControllerTrait
 *
 * @since 1.19.5
 */
trait CrudControllerTrait {

	/**
	 * Return the repository instance for this controller.
	 *
	 * @since 1.19.5
	 *
	 * @return AbstractRepository
	 */
	abstract protected function repository(): AbstractRepository;

	/**
	 * Return the request parameter key used for the entity ID.
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	abstract protected function idKey(): string;

	/**
	 * Validate request data before create or update.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data Request data.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	abstract protected function validate( array $data );

	/**
	 * Derive entity name from the repository's table name.
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	protected function entityName(): string {
		return $this->repository()->entityName();
	}

	/**
	 * Build a success response.
	 *
	 * Concrete controllers can override to match legacy BaseController format.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data   Response data.
	 * @param int   $status HTTP status code.
	 *
	 * @return WP_REST_Response
	 */
	protected function success_response( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Build an error response.
	 *
	 * Concrete controllers can override to match legacy BaseController format.
	 *
	 * @since 1.19.5
	 *
	 * @param string $code    Machine-readable error code (snake_case).
	 * @param string $message Translated human-readable message.
	 * @param int    $status  HTTP status code.
	 *
	 * @return WP_Error
	 */
	protected function error_response( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Build a legacy-envelope success response.
	 *
	 * @since 1.19.5
	 *
	 * @param string $message Translated success message.
	 * @param array  $extra   Optional additional keys merged into the envelope (e.g. 'data', 'id').
	 * @param int    $status  HTTP status code. Default 200.
	 *
	 * @return WP_REST_Response
	 */
	protected function legacy_success_response( string $message, array $extra = array(), int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array_merge(
				array(
					'status'  => 'success',
					'message' => $message,
				),
				$extra
			),
			$status
		);
	}

	/**
	 * Build a legacy-envelope error response.
	 *
	 * @since 1.19.5
	 *
	 * @param string $message Translated error message.
	 * @param int    $status  HTTP status code. Default 400.
	 *
	 * @return WP_REST_Response
	 */
	protected function legacy_error_response( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'status'  => 'failed',
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * @since 1.19.5
	 *
	 * @param string   $code     Machine-readable error code (snake_case).
	 * @param string   $message  Generic translated message for production.
	 * @param WP_Error $db_error The WP_Error from the repository/QueryBuilder layer.
	 * @param int      $status   HTTP status code. Default 500.
	 *
	 * @return WP_Error
	 */
	protected function db_error_response( string $code, string $message, WP_Error $db_error, int $status = 500 ): WP_Error {
		$error_message = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
			? $db_error->get_error_message()
			: $message;

		return new WP_Error( $code, $error_message, array( 'status' => $status ) );
	}

	/**
	 * Sanitize input data before persistence.
	 * 
	 * @since 1.19.5
	 *
	 * @param array $data Raw validated data.
	 *
	 * @return array Sanitized data.
	 */
	protected function sanitize( array $data ): array {
		return array_map( function ( $value ) {
			if ( is_string( $value ) ) {
				return sanitize_text_field( $value );
			}
			if ( is_array( $value ) ) {
				return $this->sanitize( $value );
			}
			return $value;
		}, $data );
	}

	/**
	 * Create or update an entity.
	 *
	 * If the request contains an ID, updates the existing entity.
	 * Otherwise, creates a new one. Calls validate() before saving.
	 *
	 * Fires: do_action("mailmint_{entity}_saved", $id, $data)
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_body_params();
		}

		$validation = $this->validate( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$data = $this->sanitize( $data );

		$id_key = $this->idKey();
		$id     = $request->get_param( $id_key );

		if ( $id ) {
			$id     = (int) $id;
			$entity = $this->repository()->find( $id );

			if ( null === $entity ) {
				return $this->error_response(
					'not_found',
					__( 'Entity not found.', 'mrm' ),
					404
				);
			}

			$affected = $this->repository()->update( $id, $data );

			if ( is_wp_error( $affected ) ) {
				return $this->db_error_response(
					'update_failed',
					__( 'Failed to update entity.', 'mrm' ),
					$affected
				);
			}

			/**
			 * Fires after an entity is successfully saved (updated).
			 *
			 * @since 1.19.5
			 *
			 * @param int   $id   Entity ID.
			 * @param array $data Saved data.
			 */
			do_action( "mailmint_{$this->entityName()}_saved", $id, $data );

			return $this->success_response(
				array(
					'success' => true,
					'id'      => $id,
				),
				200
			);
		}

		$new_id = $this->repository()->create( $data );

		if ( is_wp_error( $new_id ) ) {
			return $this->db_error_response(
				'create_failed',
				__( 'Failed to create entity.', 'mrm' ),
				$new_id
			);
		}

		if ( ! $new_id ) {
			return $this->error_response(
				'create_failed',
				__( 'Failed to create entity.', 'mrm' ),
				500
			);
		}

		/** This action is documented above. */
		do_action( "mailmint_{$this->entityName()}_saved", $new_id, $data );

		return $this->success_response(
			array(
				'success' => true,
				'id'      => $new_id,
			),
			201
		);
	}

	/**
	 * Get all entities with pagination.
	 *
	 * Delegates to repository->list() which fires the
	 * mailmint_repository_list_query filter internally.
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_all( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		$result = $this->repository()->list( $params );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Get a single entity by ID.
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_single( WP_REST_Request $request ) {
		$id_key = $this->idKey();
		$id     = $request->get_param( $id_key );

		if ( ! $id ) {
			return $this->error_response(
				'missing_param',
				sprintf(
					/* translators: %s: parameter name */
					__( 'Missing required parameter: %s', 'mrm' ),
					$id_key
				),
				400
			);
		}

		$entity = $this->repository()->find( (int) $id );

		if ( null === $entity ) {
			return $this->error_response(
				'not_found',
				__( 'Entity not found.', 'mrm' ),
				404
			);
		}

		return $this->success_response( $entity, 200 );
	}

	/**
	 * Delete a single entity by ID.
	 *
	 * Fires: do_action("mailmint_{entity}_deleted", $id)
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_single( WP_REST_Request $request ) {
		$id_key = $this->idKey();
		$id     = $request->get_param( $id_key );

		if ( ! $id ) {
			return $this->error_response(
				'missing_param',
				sprintf(
					/* translators: %s: parameter name */
					__( 'Missing required parameter: %s', 'mrm' ),
					$id_key
				),
				400
			);
		}

		$id     = (int) $id;
		$deleted = $this->repository()->destroy( $id );

		if ( is_wp_error( $deleted ) ) {
			return $this->db_error_response(
				'delete_failed',
				__( 'Failed to delete entity.', 'mrm' ),
				$deleted
			);
		}

		if ( ! $deleted ) {
			return $this->error_response(
				'not_found',
				__( 'Entity not found.', 'mrm' ),
				404
			);
		}

		/**
		 * Fires after an entity is successfully deleted.
		 *
		 * @since 1.19.5
		 *
		 * @param int $id Deleted entity ID.
		 */
		do_action( "mailmint_{$this->entityName()}_deleted", $id );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Delete multiple entities by IDs.
	 *
	 * Expects an 'ids' parameter in the request body.
	 * Fires: do_action("mailmint_{entity}_deleted", $id) per deleted ID.
	 *
	 * @since 1.19.5
	 *
	 * @param WP_REST_Request $request Full request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_all( WP_REST_Request $request ) {
		$ids = $request->get_param( 'ids' );

		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return $this->error_response(
				'missing_param',
				__( 'Missing required parameter: ids', 'mrm' ),
				400
			);
		}

		$ids = array_map( 'intval', $ids );

		$deleted = $this->repository()->destroyMany( $ids );

		if ( is_wp_error( $deleted ) ) {
			return $this->db_error_response(
				'delete_failed',
				__( 'Failed to delete entities.', 'mrm' ),
				$deleted
			);
		}

		$entity_name = $this->entityName();
		foreach ( $ids as $id ) {
			/** This action is documented in delete_single(). */
			do_action( "mailmint_{$entity_name}_deleted", $id );
		}

		return new WP_REST_Response( null, 204 );
	}
}
