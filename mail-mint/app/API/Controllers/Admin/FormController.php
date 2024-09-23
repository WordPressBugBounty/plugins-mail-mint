<?php
/**
 * REST API Form Controller
 *
 * Handles requests to the forms endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Exception;
use MailMint\App\Internal\FormBuilder\Storage;
use Mint\MRM\DataBase\Models\FormModel;
use Mint\MRM\DataStores\FormData;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_Query;
use WP_REST_Request;
use MRM\Common\MrmCommon;

/**
 * This is the main class that controls the forms feature. Its responsibilities are:
 *
 * - Create or update a form
 * - Delete single or multiple forms
 * - Retrieve single or multiple forms
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class FormController extends AdminBaseController {

	/**
	 * Form object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args;

	/**
	 * Remote API url for form templates
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public static $form_templates_remote_api_url = 'https://d-aardvark-fufe.instawp.xyz/wp-json/mha/v1/forms';


	/**
	 * Function used to handle create  or update requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_RESPONSE
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {

		// Get values from the API request.
		$params = MrmCommon::get_api_params_values( $request );
		// Form title validation.
		$title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : null;
		if ( empty( $title ) ) {
			return $this->get_error_response( __( 'Form name is mandatory', 'mrm' ), 200 );
		}
		if( isset($params['status']) && 'draft'  !== $params['status']){
			$group = isset( $params['group_ids'] ) ? $params['group_ids'] : array();
            if( empty( $group['lists'] ) && empty( $group['tags']) ){
                return $this->get_error_response( __( 'Please select a list or tag', 'mrm' ), 200 );
            }
		}
		if ( strlen( $title ) > 150 ) {
			return $this->get_error_response( __( 'Form title character limit exceeded 150 characters', 'mrm' ), 200 );
		}
		// Form object create and insert or update to database.
		$this->args                            = array(
			'title'         => $title,
			'form_body'     => isset( $params['form_body'] ) ? htmlspecialchars_decode( $params['form_body'] ) : '',
			'form_position' => isset( $params['form_position'] ) ? serialize($params['form_position']) : [],
			'status'        => isset( $params['status'] ) ? $params['status'] : '',
			'group_ids'     => isset( $params['group_ids'] ) ? $params['group_ids'] : array(),
			'meta_fields'   => isset( $params['meta_fields'] ) ? $params['meta_fields'] : array(),
		);
		$this->args['meta_fields']['settings'] = htmlspecialchars_decode( $this->args['meta_fields']['settings'] );
        try {
			$form = new FormData( $this->args );
			if ( isset( $params['form_id'] ) ) {
				$success = FormModel::update( $form, $params['form_id'], 'forms' );
			} else {
				$success = FormModel::insert( $form, 'forms' );
			}

			if ( $success ) {
				return $this->get_success_response( __( 'Form has been saved successfully', 'mrm' ), 201, $success );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 200 );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Form is not valid', 'mrm' ), 200 );
		}
	}



	/**
	 * Function used to handle paginated get and search requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$page     = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page = isset( $params['per-page'] ) ? absint( $params['per-page'] ) : 25;
		$offset   = ( $page - 1 ) * $per_page;

		$order_by   = isset( $params['order-by'] ) ? strtolower( $params['order-by'] ) : 'created_at';
		$order_type = isset( $params['order-type'] ) ? strtolower( $params['order-type'] ) : 'desc';
		$status     = isset( $params['status'] ) ? $params['status'] : 'all';

		// Form Search keyword.
		$search = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';

		$forms = FormModel::get_all( $order_by, $order_type, $status, $offset, $per_page, $search );
		// Prepare human_time_diff for every form.
		if ( isset( $forms['data'] ) ) {
			$forms['data'] = array_map(
				function( $form ) {
					if ( isset( $form['created_at'] ) ) {
						$form['created_ago'] = human_time_diff( strtotime( $form['created_at'] ), current_time( 'timestamp' ) );
					}
					$form['group_ids'] = isset( $form['group_ids'] ) ? maybe_unserialize( $form['group_ids'] ) : array();
					return $form;
				},
				$forms['data']
			);
		}

		if ( isset( $forms ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $forms );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function used to handle paginated get all forms only title and id
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all_id_title( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$forms = FormModel::get_all_id_title();

		$form_data = array();
		$list_none = array(
			'value' => 0,
			'label' => 'None',
		);
		array_push( $form_data, $list_none );

		foreach ( $forms['data'] as $form ) {
			$forms_ob = array(
				'value' => $form['id'],
				'label' => $form['title'],
			);
			array_push( $form_data, $forms_ob );
		}

		if ( isset( $forms ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $form_data );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Retrieve a single form's data.
	 * 
	 * @access public
	 *
	 * @param WP_REST_Request $request The REST request object.
	 *
	 * @return WP_REST_Response The REST response containing the form data or an error message.
	 * @since 1.5.6
	 */
	public function get_single( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$form_id = isset( $params['form_id'] ) ? $params['form_id'] : 0;
		$form    = FormModel::get( $form_id );

		if ( empty( $form ) ) {
			return $this->get_error_response(__('Failed to retrieve form data.', 'mrm'), 400);
		}

		$form['group_ids']     = isset( $form['group_ids'] ) ? maybe_unserialize( $form['group_ids'] ) : array();
		$form['form_position'] = isset( $form['form_position'] ) ? maybe_unserialize( $form['form_position'] ) : '';

		return $this->get_success_response( __( 'Form data has been retrieved successfully.', 'mrm' ), 200, $form );
	}


	/**
	 * Function used to handle delete single form requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['form_id'] ) ) {
			$success = FormModel::destroy( $params['form_id'] );
			if ( $success ) {
				return $this->get_success_response( __( 'Form has been deleted successfully', 'mrm' ), 200 );
			}
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Function used to handle delete requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['form_ids'] ) ) {
			$success = FormModel::destroy_all( $params['form_ids'] );
			if ( $success ) {
				return $this->get_success_response( __( 'Forms has been deleted successfully', 'mrm' ), 200 );
			}
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Function used to handle update status requests
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_RESPONSE
	 * @since 1.0.0
	 */
	public function form_status_update( WP_REST_Request $request ) {

		// Get values from the API request.
		$params = MrmCommon::get_api_params_values( $request );
		// Form object create and insert or update to database.
		$status  = isset( $params['status'] ) ? $params['status'] : 'draft';
		$form_id = isset( $params['form_id'] ) ? $params['form_id'] : 0;
		$success = FormModel::form_status_update( $status, $form_id);

		if ( $success ) {
			return $this->get_success_response( __( 'Form status has been updated successfully.', 'mrm' ), 201, $success );
		}
		return $this->get_error_response( __( 'Form status has not been updated.', 'mrm' ), 200 );
	}



	/**
	 * Function used to get settings of a single form
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_form_settings( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$form = FormModel::get_form_settings( $params['form_id'] );

		if ( isset( $form ) ) {
			return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $form );
		}
		return $this->get_error_response( __( 'Failed to get data.', 'mrm' ), 400 );
	}


	/**
	 * Function used to get title status and group form a single form
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_title_group( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$form = FormModel::get_title_group( $params['form_id'] );

		$form[0]['group_ids'] = isset( $form[0]['group_ids'] ) ? maybe_unserialize( $form[0]['group_ids'] ) : array();
		$form[0]['form_position'] = isset( $form[0]['form_position'] ) ? maybe_unserialize( $form[0]['form_position'] ) : array();
		if ( isset( $form ) ) {
			return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $form );
		}
		return $this->get_error_response( __( 'Failed to get data.', 'mrm' ), 400 );
	}

	/**
	 * Function used to get body of a single form
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_form_body( WP_REST_Request $request ) {

		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$form = FormModel::get_form_body( $params['form_id'] );

		if ( isset( $form ) ) {
			return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $form );
		}
		return $this->get_error_response( __( 'Failed to get data.', 'mrm' ), 400 );
	}



	/**
	 * Function used to get all form templates
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_form_templates( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$limit  = isset($params['per-page']) ? intval($params['per-page']) : 10;
		$offset = isset($params['page']) ? intval($params['page']) : 0;

        $forms = Storage::get_form_templates();
		$forms = array_slice($forms, $offset, $limit);
        $data  = is_array( $forms ) && !empty( $forms ) ? [
            'forms' => $forms
        ] : [];
        return rest_ensure_response( $data );
	}

	/**
	 * Import form template
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return array|\WP_Error
	 */
	public function import_form_template( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
        if( isset($params['form_id']) && !empty($params['form_id']) ){
            $form_id = $params['form_id'];
	        $get_single_form = Storage::get_form($form_id);
            if ( !empty( $get_single_form ) ) {
                return $this->get_success_response( __( 'Query Successful.', 'mrm' ), 200, $get_single_form );
            }
        }
        return $this->get_error_response( __( 'Failed to get data.', 'mrm' ), 400 );
	}

}
