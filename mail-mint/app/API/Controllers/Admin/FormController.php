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
use Mint\MRM\DataBase\Models\FormSubmissionModel;
use Mint\MRM\DataStores\FormData;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

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
				if ( ! isset( $params['form_id'] ) ) {
					do_action( 'mailmint_first_form_created', $success );
				}
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
	 * Duplicates a form based on the provided form ID and optional override parameters.
	 *
	 * @param WP_REST_Request $request The REST API request object containing form data.
	 *
	 * @return WP_REST_Response JSON response indicating success or failure.
	 * @since 1.16.2
	 */
	public function duplicate_form(WP_REST_Request $request) {
		// Retrieve parameters from the request.
		$params = MrmCommon::get_api_params_values($request);
		$form_id = $params['id'] ?? 0;

		// Fetch the original form using the provided form ID.
		$original_form = FormModel::get($form_id);
		if (empty($original_form)) {
			return $this->get_error_response(__('Failed to retrieve form data.', 'mrm'), 400);
		}

		// Prepare duplicate form data.
		$duplicate_form_data = [
			'title'         => $original_form['title'] . ' [Duplicate]',
			'form_body'     => $original_form['form_body'] ?? '',
			'form_position' => $original_form['form_position'] ?? [],
			'status'        => 'draft',
			'group_ids'     => $original_form['group_ids'] ?? [],
			'meta_fields'   => $original_form['meta_fields'] ?? [],
		];

		$form_obj = new FormData( $duplicate_form_data );

		// Insert the duplicate form into the database.
		$duplicate_form_id = FormModel::insert($form_obj, 'forms');

		if ($duplicate_form_id) {
			return $this->get_success_response(
				__('Form has been duplicated successfully.', 'mrm'),
				201,
				$duplicate_form_id
			);
		}

		// Return error response if form duplication failed.
		return $this->get_error_response(__('Failed to duplicate form data.', 'mrm'), 400);
	}

	/**
	 * Import form template
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return array|\WP_Error
	 */
	public function import_form_template(WP_REST_Request $request)
	{
		$params = MrmCommon::get_api_params_values($request);
		if (isset($params['form_id']) && !empty($params['form_id'])) {
			$form_id = $params['form_id'];
			$get_single_form = Storage::get_form($form_id);
			if (!empty($get_single_form)) {
				return $this->get_success_response(__('Query Successful.', 'mrm'), 200, $get_single_form);
			}
		}
		return $this->get_error_response(__('Failed to get data.', 'mrm'), 400);
	}

	/**
	 * Return paginated entries (submissions + field values) for a specific form.
	 *
	 * GET /mrm/v1/forms/{form_id}/entries?page=1&per-page=25
	 *
	 * Response shape:
	 * {
	 *   code: 200,
	 *   message: "...",
	 *   data: {
	 *     data: [ { id, form_id, contact_id, source_url, status, browser, device,
	 *               ip, created_at, fields: { field_name: field_value } }, ... ],
	 *     count: <int>,
	 *     total_pages: <int>
	 *   }
	 * }
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 * @since 1.16.0
	 */
	public function get_form_entries( WP_REST_Request $request ) {
		$params     = MrmCommon::get_api_params_values( $request );
		$form_id    = isset( $params['form_id'] ) ? absint( $params['form_id'] ) : 0;
		$page       = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page   = isset( $params['per-page'] ) ? absint( $params['per-page'] ) : 25;
		$order_by   = isset( $params['order-by'] ) ? sanitize_text_field( $params['order-by'] ) : 'created_at';
		$order_type = isset( $params['order-type'] ) ? sanitize_text_field( $params['order-type'] ) : 'DESC';
		$search      = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
		$read_status = isset( $params['read_status'] ) ? sanitize_text_field( $params['read_status'] ) : '';

		if ( ! $form_id ) {
			return $this->get_error_response( __( 'Invalid form ID', 'mrm' ), 400 );
		}

		$entries = FormSubmissionModel::get_form_entries( $form_id, $page, $per_page, $order_by, $order_type, $search, $read_status );

		// Fetch the form body and extract ordered field columns from the block markup.
		$form_body_rows = FormModel::get_form_body( $form_id );
		$form_body      = ! empty( $form_body_rows[0]['form_body'] ) ? $form_body_rows[0]['form_body'] : '';
		$entries['columns'] = self::parse_form_columns_from_body( $form_body );

		return $this->get_success_response( __( 'Query Successful', 'mrm' ), 200, $entries );
	}

	/**
	 * Parse a form's block markup and return an ordered list of field column definitions.
	 *
	 * Each entry is [ 'label' => <human label>, 'slug' => <field_name key used in submissions> ].
	 *
	 * Supported block types:
	 *   - wp:mrmformfield/mrm-custom-field  → "field_name" / "field_slug"
	 *   - wp:mrmformfield/first-name-block  → firstNamePlaceholder / first_name
	 *   - wp:mrmformfield/last-name-block   → lastNamePlaceholder  / last_name
	 *   - wp:mrmformfield/email-field-block → "Email"              / email
	 *   - wp:mrmformfield/phone-block       → "Phone"              / phone
	 *
	 * @param string $form_body Raw block-markup content from wp_mint_forms.form_body.
	 *
	 * @return array[]  Array of { label: string, slug: string } maps.
	 * @since 1.16.0
	 */
	private static function parse_form_columns_from_body( $form_body ) {
		$columns = array();
		$seen    = array();

		if ( empty( $form_body ) ) {
			return $columns;
		}

		// parse_blocks() correctly handles arbitrarily nested JSON attributes,
		// unlike a hand-rolled regex which breaks on deeply nested objects.
		$blocks = parse_blocks( $form_body );

		foreach ( self::flatten_blocks( $blocks ) as $block ) {
			$name = isset( $block['blockName'] ) ? $block['blockName'] : '';
			if ( 0 !== strpos( $name, 'mrmformfield/' ) ) {
				continue;
			}

			$block_type = substr( $name, strlen( 'mrmformfield/' ) );
			$attrs      = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

			$label = '';
			$slug  = '';

			switch ( $block_type ) {
				case 'mrm-custom-field':
					$label = ! empty( $attrs['field_name'] ) ? sanitize_text_field( $attrs['field_name'] ) : '';
					$slug  = ! empty( $attrs['field_slug'] ) ? sanitize_key( $attrs['field_slug'] ) : '';
					break;

				case 'first-name-block':
					$label = ! empty( $attrs['firstNamePlaceholder'] ) ? sanitize_text_field( $attrs['firstNamePlaceholder'] ) : __( 'First Name', 'mrm' );
					$slug  = 'first_name';
					break;

				case 'last-name-block':
					$label = ! empty( $attrs['lastNamePlaceholder'] ) ? sanitize_text_field( $attrs['lastNamePlaceholder'] ) : __( 'Last Name', 'mrm' );
					$slug  = 'last_name';
					break;

				case 'email-field-block':
					$label = __( 'Email', 'mrm' );
					$slug  = 'email';
					break;

				case 'phone-block':
					$label = __( 'Phone', 'mrm' );
					$slug  = 'phone';
					break;

				default:
					// Ignore layout/button/recaptcha blocks.
					continue 2;
			}

			if ( $slug && ! isset( $seen[ $slug ] ) ) {
				$columns[]     = array(
					'label' => $label,
					'slug'  => $slug,
				);
				$seen[ $slug ] = true;
			}
		}

		return $columns;
	}

	/**
	 * Recursively flatten a nested parse_blocks() result into a single list.
	 *
	 * @param array $blocks Block list from parse_blocks().
	 * @return array
	 * @since 1.16.0
	 */
	private static function flatten_blocks( array $blocks ) {
		$flat = array();
		foreach ( $blocks as $block ) {
			$flat[] = $block;
			if ( ! empty( $block['innerBlocks'] ) ) {
				$flat = array_merge( $flat, self::flatten_blocks( $block['innerBlocks'] ) );
			}
		}
		return $flat;
	}

	/**
	 * Retrieve a single form entry with its field values and adjacent navigation IDs.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 * @since 1.16.0
	 */
	public function get_form_entry( WP_REST_Request $request ) {
		$params   = MrmCommon::get_api_params_values( $request );
		$form_id  = isset( $params['form_id'] ) ? absint( $params['form_id'] ) : 0;
		$entry_id = isset( $params['entry_id'] ) ? absint( $params['entry_id'] ) : 0;

		if ( ! $form_id || ! $entry_id ) {
			return $this->get_error_response( __( 'Invalid form or entry ID', 'mrm' ), 400 );
		}

		$entry = FormSubmissionModel::get_single_entry( $form_id, $entry_id );

		if ( false === $entry ) {
			return $this->get_error_response( __( 'Entry not found', 'mrm' ), 404 );
		}

		// Attach column definitions so the frontend can render field labels.
		$form_body_rows   = FormModel::get_form_body( $form_id );
		$form_body        = ! empty( $form_body_rows[0]['form_body'] ) ? $form_body_rows[0]['form_body'] : '';
		$entry['columns'] = self::parse_form_columns_from_body( $form_body );

		// Attach WP display name for the submitting user when available.
		$user_id = ! empty( $entry['submission']['user_id'] ) ? (int) $entry['submission']['user_id'] : 0;
		if ( $user_id ) {
			$wp_user = get_userdata( $user_id );
			$entry['submission']['user_display_name'] = $wp_user ? $wp_user->display_name : '';
		}

		return $this->get_success_response( __( 'Query Successful', 'mrm' ), 200, $entry );
	}

	/**
	 * Get form list by search
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return array
	 * @since 1.16.6
	 */
	public function get_form_list_by_search( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$search = isset( $params['search'] ) ? $params['search'] : '';
	
		$formatted_forms=[];
		$forms = FormModel::get_all('id', 'ASC', 'all', 0, PHP_INT_MAX, $search);

		if ( is_array($forms['data']) && !empty($forms['data']) ) {
			foreach ($forms['data'] as $post) {
				$formatted_forms[] = array(
					'value'  => $post['id'],
					'label'  => $post['title'],
				);
			}
		}
		$response['success'] = true;
		$response['forms']   = $formatted_forms;
		return $response;
	}

	/**
	 * Mark a form entry as read.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.16.0
	 */
	public function mark_form_entry_read( WP_REST_Request $request ) {
		$entry_id = (int) $request->get_param( 'entry_id' );

		$result = FormSubmissionModel::mark_as_read( $entry_id );

		if ( false === $result ) {
			return $this->get_error_response( __( 'Failed to mark entry as read.', 'mrm' ), 400 );
		}

		return $this->get_success_response( __( 'Entry marked as read.', 'mrm' ), 200 );
	}

	/**
	 * Update the status of a single form entry.
	 *
	 * Expected JSON body: { "status": "read"|"unread"|"trashed" }
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.16.0
	 */
	public function update_form_entry_status( WP_REST_Request $request ) {
		$entry_id = (int) $request->get_param( 'entry_id' );
		$params   = MrmCommon::get_api_params_values( $request );
		$status   = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '';

		if ( ! $entry_id ) {
			return $this->get_error_response( __( 'Invalid entry ID.', 'mrm' ), 400 );
		}

		$allowed = array( 'read', 'unread', 'trashed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return $this->get_error_response( __( 'Invalid status value.', 'mrm' ), 400 );
		}

		$result = FormSubmissionModel::update_status( $entry_id, $status );

		if ( false === $result ) {
			return $this->get_error_response( __( 'Failed to update entry status.', 'mrm' ), 500 );
		}

		return $this->get_success_response( __( 'Entry status updated.', 'mrm' ), 200 );
	}

	/**
	 * Delete one or more form entries.
	 *
	 * Expected JSON body: { "ids": [1, 2, 3] }
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 * @since 1.16.0
	 */
	public function delete_form_entries( WP_REST_Request $request ) {
		$params  = MrmCommon::get_api_params_values( $request );
		$form_id = isset( $params['form_id'] ) ? absint( $params['form_id'] ) : 0;
		$ids     = isset( $params['ids'] ) ? (array) $params['ids'] : array();

		if ( ! $form_id ) {
			return $this->get_error_response( __( 'Invalid form ID', 'mrm' ), 400 );
		}

		if ( empty( $ids ) ) {
			return $this->get_error_response( __( 'No entry IDs provided.', 'mrm' ), 400 );
		}

		$result = FormSubmissionModel::delete_entries( $ids );

		if ( ! $result ) {
			return $this->get_error_response( __( 'Failed to delete entries.', 'mrm' ), 500 );
		}

		return $this->get_success_response( __( 'Entries deleted successfully.', 'mrm' ), 200 );
	}
}
