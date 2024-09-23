<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2023-06-09 11:03:17
 * @modify date 2023-06-09 11:03:17
 * @package /app/API/Actions/Admin
 */

use Mint\MRM\API\Actions\Action;
use Mint\MRM\DataBase\Models\FormModel;
use Mint\MRM\DataBase\Models\NoteModel;
use Mint\MRM\DataBase\Models\EmailModel;
use MRM\Common\MrmCommon;

/**
 * Class ContactImportAction
 *
 * Summary: Contact Import Action implementation.
 * Description: Implements the Contact Import Action interface and provides methods to fetch MailChimp lists and contact attributes,
 * and retrieve MailChimp member headers.
 *
 * @since 1.4.9
 */
class ContactProfileAction implements Action {

    /**
     * Retrieve form submissions for a specific contact based on specified parameters.
     *
     * @param array $params An associative array of parameters:
     *                      - 'page'       : The current page for pagination (default: 1).
     *                      - 'per-page'   : The number of results to retrieve per page (default: 10).
     *                      - 'contact_id' : The ID of the contact to fetch form submissions for.
     *
     * @return array An array containing information about form submissions for the contact.
     * 
     * @since 1.8.0
     */
    public function fetch_form_submission_for_contact( $params ) {
        // Extract parameters or use default values.
        $page       = isset( $params['page'] ) ? $params['page'] : 1;
		$per_page   = isset( $params['per-page'] ) ? $params['per-page'] : 10;
		$offset     = ( $page - 1 ) * $per_page;
		$contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';

        // Use FormModel to get form submissions associated with the contact profile.
        return FormModel::get_forms_to_contact_profile( $offset, $per_page, $contact_id );
    }

    /**
     * Delete a form association from a contact profile based on the contact meta ID.
     *
     * @param array $params An associative array of parameters:
     *                      - 'contact_meta_id' : The ID of the contact meta entry associated with the form.
     *
     * @return array An array containing the status and message of the deletion operation.
     * @since 1.8.0
     */
    public function delete_contact_profile_form( $params ) {
        $contact_meta_id = isset( $params['contact_meta_id'] ) ? $params['contact_meta_id'] : '';
		$success = FormModel::destroy_form_from_contact_profile( $contact_meta_id );

		if ( $success ) {
			$response['status']  = 'success';
        	$response['message'] = __( 'Form has been deleted successfully.', 'mrm' );
		}

		return $response;
	}

    /**
     * Summary: Creates or updates a note associated with a contact.
     *
     * Description: This function is responsible for creating or updating a note related to a contact.
     *
     * @access public
     * 
     * @param array $params An array containing parameters for creating or updating a note.
     * 
     * @return array Returns an array indicating the status of the operation and a corresponding message.
     * @since 1.7.0
     */
    public function create_or_update_note( $params) {
        $contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
		$note_id    = isset( $params['note_id'] ) ? $params['note_id'] : '';
        $note       = isset( $params['note'] ) ? $params['note'] : array();

		// Note description validation.
		$description = isset( $note['description'] ) ? sanitize_text_field( $note['description'] ) : '';
		if ( empty( $description ) ) {
			return array(
                'status'  => 'failed',
                'message' => __( 'Note description is required.', 'mrm' ),
            );
		}

        // Note object create and insert or update to database.
        if ( $note_id ) {
            $success = NoteModel::update( $note, $contact_id, $note_id );
        } else {
            $success = NoteModel::insert( $note, $contact_id );
        }

		if ( $success ) {
            return array(
                'status'  => 'success',
                'message' => __( 'Note has been saved successfully.', 'mrm' ),
            );
        }
        return array(
            'status'  => 'failed',
            'message' => __( 'Note has not been saved.', 'mrm' ),
        );
	}

    /**
     * Summary: Retrieves notes associated with a specific contact.
     *
     * Description: This function retrieves notes linked to a specified contact ID using the NoteModel class. 
     * It takes a parameter array containing the contact ID and utilizes the NoteModel::get_notes_to_contact method to fetch the relevant notes.
     *
     * @access public
     *
     * @param array $params An array containing parameters for retrieving notes.
     *
     * @return array Returns an array of notes associated with the specified contact.
     *
     * @since 1.7.0
     */
    public function get_notes_to_contact( $params ) {
		$contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
		$notes = NoteModel::get_notes_to_contact( $contact_id );
		$notes = array_map(
			function( $note ) {
				if ( isset( $note['created_by'] ) && ! empty( $note['created_by'] ) ) {
					$user_meta          = get_userdata( $note['created_by'] );
					$note['created_by'] = $user_meta->data->user_login;
				}
				return $note;
			},
			$notes
		);
		return $notes;
	}

    /**
     * Summary: Deletes a note associated with a contact profile.
     *
     * Description: This function deletes a note identified by the provided note ID using the NoteModel class.
     *
     * @access public
     *
     * @param array $params An array containing parameters for deleting a note.
     *
     * @return array Returns a response array with information about the status of the deletion operation.
     *
     * @since 1.7.0
     */
    public function delete_contact_profile_note( $params ) {
        $note_id = isset( $params['note_id'] ) ? $params['note_id'] : '';
        $success = NoteModel::destroy( $params['note_id'] );

		if ( $success ) {
			$response['status']  = 'success';
        	$response['message'] = __( 'Note has been deleted successfully.', 'mrm' );
		}

		return $response;
	}

    /**
     * Fetch emails for a specific contact.
     *
     * Retrieves a list of emails sent to a particular contact, including regular emails,
     * campaign emails, and automation emails.
     *
     * @param array $params An array of parameters for fetching emails.
     *                      - 'page' (int)      : The current page number. Default is 1.
     *                      - 'per-page' (int)  : Number of emails per page. Default is 10.
     *                      - 'contact_id' (int): The ID of the contact for whom emails are fetched.
     *
     * @return array An associative array containing:
     *               - 'emails'      (array) : An array of emails sent to the contact.
     *               - 'total_pages' (int)   : The total number of pages based on the pagination.
     *               - 'total_count' (int)   : The total count of emails sent to the contact.
     * 
     * @since 1.7.0
     */
    public function fetch_emails_for_contact( $params ) {
        // Extract parameters or use default values.
        $page       = isset( $params['page'] ) ? $params['page'] : 1;
		$per_page   = isset( $params['per-page'] ) ? $params['per-page'] : 10;
		$offset     = ( $page - 1 ) * $per_page;
		$contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';

        // Get broadcast email IDs related to the contact.
		$email_ids = EmailModel::get_broadcast_email_ids_to_contact( $contact_id, $offset, $per_page );
		$count    = EmailModel::total_broadcast_email_ids_to_contact( $contact_id ); // db call ok. ; no-cache ok.

        // Fetch different types of emails.
		$regular_emails    = EmailModel::get_regular_emails_to_contact_details( $contact_id, $email_ids );
		$campaign_emails   = EmailModel::get_emails_to_contact_details( $contact_id, $email_ids );
		$automation_emails = EmailModel::get_automation_emails_to_contact_details( $contact_id, $email_ids );
        $sequence_emails   = EmailModel::get_automation_sequence_emails_to_contact_details( $contact_id, $email_ids );

        // Combine and format the emails.
		$emails = array_merge( $sequence_emails, $automation_emails, $campaign_emails, $regular_emails );
        $emails = EmailModel::get_broadcast_emails_open_time( $contact_id, $emails );
        $emails = EmailModel::get_broadcast_emails_click_time( $contact_id, $emails );
		$emails = array_map(
			function( $email ) {
				if ( isset( $email['broadcast_email_created_at'] ) ) {
					$email['created_time'] = $email['broadcast_email_created_at'];
					$email['sent_at']      = MrmCommon::date_time_format_with_core( $email['broadcast_email_created_at'] ); //phpcs:disable
				}
				return $email;
			},
			$emails
		);
        // Sort emails by creation time in descending order.
		usort($emails, function ($a, $b) {
			$timeA = strtotime($a['broadcast_email_created_at']);
			$timeB = strtotime($b['broadcast_email_created_at']);
		
			return $timeB - $timeA;
		});

		$total_pages = ( 0 !== $per_page ) ? ceil( $count / $per_page ) : 0;
        return array(
			'emails'      => $emails,
			'total_pages' => $total_pages,
			'total_count' => $count,
		);
    }

    /**
     * Summary: Deletes a email associated with a contact profile.
     *
     * Description: This function deletes a email identified by the provided note ID using the EmailModel class.
     *
     * @access public
     *
     * @param array $params An array containing parameters for deleting a email.
     *
     * @return array Returns a response array with information about the status of the deletion operation.
     *
     * @since 1.7.0
     */
    public function delete_contact_profile_email( $params ) {
        $email_id   = isset( $params['email_id'] ) ? $params['email_id'] : '';
        $contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
        $success    = EmailModel::delete_broadcast_email_by_contact_id( $email_id, $contact_id );

		if ( $success ) {
			$response['status']  = 'success';
        	$response['message'] = __( 'Email has been deleted successfully.', 'mrm' );
		}

		return $response;
	}

    /**
     * Summary: Deletes a email associated with a contact profile.
     *
     * Description: This function deletes a email identified by the provided note ID using the EmailModel class.
     *
     * @access public
     *
     * @param array $params An array containing parameters for deleting a email.
     *
     * @return array Returns a response array with information about the status of the deletion operation.
     *
     * @since 1.7.0
     */
    public function delete_contact_profile_emails( $params ) {
        $email_ids  = isset( $params['email_ids'] ) ? $params['email_ids'] : '';
        $contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
        $success    = EmailModel::delete_multiple_broadcast_email_by_contact_id( $email_ids, $contact_id );

		if ( $success ) {
			$response['status']  = 'success';
        	$response['message'] = __( 'Emails has been deleted successfully.', 'mrm' );
		}

		return $response;
	}
}