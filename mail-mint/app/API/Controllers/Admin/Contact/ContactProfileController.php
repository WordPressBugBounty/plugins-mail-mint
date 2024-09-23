<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2023-06-09 11:03:17
 * @modify date 2023-06-09 11:03:17
 * @package /app/API/Controllers/Admin/Contact
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Admin\API\Controllers\AdminBaseController;
use Mint\MRM\API\Actions\ContactProfileActionCreator;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class ContactProfileController
 *
 * Summary: Contact Profile Controller.
 * Description: Extends the AdminBaseController class.
 *
 * @since 1.8.0
 */
class ContactProfileController extends AdminBaseController {

    /**
	 * The ContactProfileActionCreator instance used to create ContactIAction objects.
	 *
	 * @var ContactProfileActionCreator
	 * @access protected
	 * @since 1.5.1
	 */
	protected $creator;

	/**
	 * The ContactProfileAction instance used for performing contact profile actions.
	 *
	 * @var ContactProfileAction
	 * @access protected
	 * @since 1.5.0
	 */
	protected $action;

	/**
	 * ContactProfileController constructor.
	 *
	 * This constructor initializes the ContactProfileController and ContactProfileAction objects,
	 * making them accessible within the class for further use.
	 *
	 * @access public
	 * @since 1.5.0
	 */
	public function __construct() {
		$this->creator = new ContactProfileActionCreator();
		$this->action  = $this->creator->makeAction();
	}

    /**
	 * Retrieve form submissions for a specific contact using the provided REST request.
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters.
	 *
	 * @return WP_REST_Response The REST response object with the result of the query.
	 * 
	 * @since 1.8.0
	 */
	public function get_contact_forms( WP_REST_Request $request ) {
		// Get API key from the request object.
        $params   = MrmCommon::prepare_request_params( $request );
		$response = $this->action->fetch_form_submission_for_contact( $params );

        return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $response );
	}

	/**
	 * Delete a form association from a contact profile using the provided REST request.
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters.
	 *
	 * @return WP_REST_Response The REST response object with the result of the deletion operation.
	 * 
	 * @since 1.8.0
	 */
	public function delete_contact_forms( WP_REST_Request $request ) {
		// Get values from API.
		$params   = MrmCommon::get_api_params_values( $request );
		$response = $this->action->delete_contact_profile_form( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Retrieve emails for a specific contact using the REST API.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return WP_REST_Response A REST API response containing the retrieved emails.
	 * @since 1.7.0
	 */
	public function get_contact_emails( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::prepare_request_params( $request );
		$emails = $this->action->fetch_emails_for_contact( $params );
		
		return new WP_REST_Response( array(
			'data'    => $emails,
			'status'  => 'success',
			'message' => __( 'Emails has been retrieved successfully.', 'mrm' ),
		) );
	}

	/**
	 * Create or Update Note for a Contact.
	 *
	 * @param WP_REST_Request $request The REST request object containing the note details.
	 *
	 * @return WP_REST_Response The REST response with the result of the note creation or update.
	 * @since 1.7.0
	 */
	public function create_or_update_note( WP_REST_Request $request ) {
		// Get API key from the request object.
        $params   = MrmCommon::get_api_params_values( $request );
		$response = $this->action->create_or_update_note( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Get Notes for a Contact.
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters for fetching notes.
	 *
	 * @return WP_REST_Response The REST response with the retrieved notes, status, and total count.
	 * @since 1.7.0
	 */
	public function get_notes_to_contact( WP_REST_Request $request ) {
		// Get API key from the request object.
        $params = MrmCommon::prepare_request_params( $request );
		$notes  = $this->action->get_notes_to_contact( $params );
		// Return the result array.
		return new WP_REST_Response( array(
			'notes'  => $notes,
			'status' => 'success',
			'total_count' => __( 'Notes has been retrieved successfully.', 'mrm' ),
		) );
	}

	/**
	 * Delete a Contact Note.
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters for deleting a note.
	 *
	 * @return WP_REST_Response The REST response with the result of the deletion operation.
	 * @since 1.7.0
	 */
	public function delete_contact_note( WP_REST_Request $request ) {
		$params   = MrmCommon::get_api_params_values( $request );
		$response = $this->action->delete_contact_profile_note( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Delete a Contact Email.
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters for deleting a email.
	 *
	 * @return WP_REST_Response The REST response with the result of the deletion operation.
	 * @since 1.7.0
	 */
	public function delete_contact_email( WP_REST_Request $request ) {
		$params   = MrmCommon::get_api_params_values( $request );
		$response = $this->action->delete_contact_profile_email( $params );
		return new WP_REST_Response( $response );
	}

	/**
	 * Delete a Contact Emails.
	 *
	 * @param WP_REST_Request $request The REST request object containing parameters for deleting emails.
	 *
	 * @return WP_REST_Response The REST response with the result of the deletion operation.
	 * @since 1.7.0
	 */
	public function delete_contact_emails( WP_REST_Request $request ) {
		$params   = MrmCommon::get_api_params_values( $request );
		$response = $this->action->delete_contact_profile_emails( $params );
		return new WP_REST_Response( $response );
	}
}