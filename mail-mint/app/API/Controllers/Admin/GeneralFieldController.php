<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2023-06-09 11:03:17
 * @modify date 2023-06-09 11:03:17
 * @package /app/API/Controllers/Admin
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Admin\API\Controllers\AdminBaseController;
use Mint\MRM\API\Actions\GeneralFieldActionCreator;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Mail Mint
 *
 * The GeneralFieldController class is responsible for handling REST API requests related to general fields.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since 1.5.0
 */
class GeneralFieldController extends AdminBaseController {

	/**
	 * An associative array that stores the response of the API requests made in this class.
     * 
	 * @var array
     * @since 1.5.0
	 */
	private $response = array();

    /**
	 * Retrieves all general fields based on the provided API parameters.
	 *
	 * Retrieves all general fields using the API parameters received in the WP_REST_Request object.
	 *
	 * @param WP_REST_Request $request The REST request object containing the API parameters.
	 * @return WP_REST_Response The response containing the retrieved fields.
	 *     - 'status' (string) The status of the operation. Possible values: 'success'.
	 *     - 'message' (string) The success message.
	 *     - 'data' (mixed) The retrieved field data.
	 * @since 1.5.0
	 */
	public function get_all( WP_REST_Request $request ) {

		// Get values from API.
		$params  = MrmCommon::get_api_params_values( $request );
		$params  = filter_var_array( $params );
		$creator = new GeneralFieldActionCreator();
		$action  = $creator->makeAction();
		$fields  = $action->get_all( $params );
		
		// Set response status to 'success', assign success message, and data before returning WP_REST_Response instance.
		$this->response['status']  = 'success';
        $this->response['message'] = __( 'General fields retrieved successfully', 'mrm' );
		$this->response['data']    = $fields;
		return new WP_REST_Response( $this->response );
	}

	/**
	 * Retrieves a single field based on the given API parameters.
	 *
	 * Retrieves a single field using the API parameters received in the WP_REST_Request object.
	 *
	 * @param WP_REST_Request $request The REST request object containing the API parameters.
	 * @return WP_REST_Response The response containing the retrieved field or an error message.
	 *     - 'status' (string) The status of the operation. Possible values: 'success' or 'failed'.
	 *     - 'data' (mixed) The retrieved field data.
	 *     - 'message' (string) The success or error message.
	 * @since 1.5.0
	 */
	public function get_single( WP_REST_Request $request ) {
		// Get values from API.
		$params  = MrmCommon::get_api_params_values( $request );
		$params  = filter_var_array( $params );
		$creator = new GeneralFieldActionCreator();
		$action  = $creator->makeAction();
		$field   = $action->get_single( $params );

		if ( empty( $field ) ) {
			return new WP_REST_Response([
				'status' => 'failed',
				'message' => __('General field not found.', 'mrm'),
			]);
		}
	
		return new WP_REST_Response([
			'status' => 'success',
			'data' => $field,
			'message' => __('General field retrieved successfully.', 'mrm'),
		]);
	}

	/**
	 * Creates or updates a general field based on the given API parameters.
	 *
	 * Creates or updates a general field using the API parameters received in the WP_REST_Request object.
	 *
	 * @param WP_REST_Request $request The REST request object containing the API parameters.
	 * @return WP_REST_Response The response indicating the success or failure of the operation.
	 *     - 'status' (string) The status of the operation. Possible values: 'success' or 'failed'.
	 *     - 'message' (string) The success or error message.
	 * @since 1.5.0
	 */
	public function create_or_update( WP_REST_Request $request ) {

		// Get values from the API request.
		$params  = MrmCommon::get_api_params_values( $request );
		$params  = filter_var_array( $params );

		$title = isset( $params['title'] ) ? $params['title'] : '';
		$label = isset( $params['meta']['label'] ) ? $params['meta']['label'] : '';

		if( empty( $title ) || empty( $label ) ){
			return new WP_REST_Response([
				'status' => 'failed',
				'message' => __('Field Title or Label is empty. No updates applied.', 'mrm'),
			]);
		}

		$creator = new GeneralFieldActionCreator();
		$action  = $creator->makeAction();
		$result  = $action->create_or_update( $params );

		if ( $result ) {
			return new WP_REST_Response([
				'status' => 'success',
				'message' => __('General field has been saved successfully.', 'mrm'),
			]);
		}

		return new WP_REST_Response([
			'status' => 'failed',
			'message' => __('No changes made to the '. $title .' field. No updates applied.', 'mrm'),
		]);
	}
}