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
use Mint\MRM\API\Actions\ContactImportActionCreator;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class ContactImportController
 *
 * Summary: Contact Import Controller.
 * Description: Extends the AdminBaseController class and handles actions related to contact import from MailChimp.
 *
 * @since 1.4.9
 */
class ContactImportController extends AdminBaseController {

    /**
	 * The ContactImportActionCreator instance used to create ContactImportAction objects.
	 *
	 * @var ContactImportActionCreator
	 * @access protected
	 * @since 1.5.1
	 */
	protected $creator;

	/**
	 * The ContactImportAction instance used for performing contact import actions.
	 *
	 * @var ContactImportAction
	 * @access protected
	 * @since 1.5.0
	 */
	protected $action;

	/**
	 * ContactImportController constructor.
	 *
	 * This constructor initializes the ContactImportActionCreator and ContactImportAction objects,
	 * making them accessible within the class for further use.
	 *
	 * @access public
	 * @since 1.5.0
	 */
	public function __construct() {
		$this->creator = new ContactImportActionCreator();
		$this->action  = $this->creator->makeAction();
	}

    /**
	 * Summary: Retrieves attributes for MailChimp lists.
     * 
     * Description: Retrieves attributes for MailChimp lists based on the provided API key.
     * 
     * @access public
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
     * 
	 * @return WP_REST_Response Returns a REST response containing the status, data, and message.
	 * @since 1.4.9
	 */
	public function get_mailchimp_lists_attributes( WP_REST_Request $request ) {
		// Get API key from the request object.
        $key = isset( MrmCommon::get_api_params_values( $request )['key'] ) ? MrmCommon::get_api_params_values( $request )['key'] : '';

        $response = $this->action->fetch_mailchimp_lists_and_contact_attributes( $key );

        if( isset($response['status']) && 401 == $response['status'] ){
            return new WP_REST_Response([
				'status' => 'failed',
				'message' => __($response['message'], 'mrm'),
			]);
        }
        
        return new WP_REST_Response([
			'status' => 'success',
			'data' => $response,
			'message' => __('MailChimp lists have been retrieved successfully.', 'mrm'),
		]); 
	}

    /**
     * Summary: Handles MailChimp member headers.
     * Description: Handles the request for MailChimp member headers based on the provided API parameters.
     *
     * @access public
     * 
     * @param WP_REST_Request $request The REST request object.
     * 
     * @return array Returns the MailChimp member headers.
     * @since 1.4.9
     */
	public function handle_mailchimp_member_headers( WP_REST_Request $request ) {
		// Get parameters.
        $params  = MrmCommon::get_api_params_values( $request );
        $params  = filter_var_array( $params );

        $response = $this->action->get_mailchimp_member_headers( $params );

        if( empty( $response ) ) {
            return new WP_REST_Response([
                'status'  => 'failed',
                'message' => __('MailChimp headers have not been retrieved successfully.', 'mrm'),
            ]);
        }

        return new WP_REST_Response([
			'status'  => 'success',
			'data'    => $response,
			'message' => __('MailChimp headers have been retrieved successfully.', 'mrm'),
		]); 
	}

    /**
     * Summary: Retrieves the total number of EDD contacts.
     * Description: Retrieves the total number of contacts from Easy Digital Downloads.
     * 
     * @access public
     *
     * @return WP_REST_Response Returns a REST response containing the total batch count of contacts.
     * @since 1.4.9
     */
    public function get_edd_contacts_total() {
        if ( !class_exists( '\Easy_Digital_Downloads' ) ) {
			return false;
		}
        $total = edd_count_customers();

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.4.9
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 30 );

        return $this->get_success_response( __( 'Total orders has been retrieved successfully.', 'mrm' ), 200, array(
            'total_batch' => ceil( $total / (int) $per_batch )
        ) );
    }

    /**
     * Validates and imports contact attributes using WP_REST_Request.
     *
     * This function validates and imports contact attributes based on the provided WP_REST_Request object.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The WP_REST_Request object.
     * @return WP_REST_Response The WP_REST_Response containing the import process response.
     * @since 1.5.1
     */
	public function validate_and_import_contact_attributes( WP_REST_Request $request ) {
        // Define the required parameters for the import process.
        $required_params = array('delimiter');

        // Check if all required parameters are present in the request.
        foreach ( $required_params as $param ) {
            if ( !$request->has_param($param) ) {
                return $this->get_error_response( __( "Required parameter '$param' is missing.", 'mrm' ), 400 );
            }
        }

		// Get values from the API request.
		$params   = MrmCommon::prepare_request_params( $request );
		$response = $this->action->process_contact_attribute_import( $params );
        // Return a WP_REST_Response containing the import process response.
        return new WP_REST_Response( $response );
	}

    /**
     * Import raw contact attributes data via REST endpoint.
     *
     * This function handles the import of raw contact attributes data through a custom REST endpoint.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the import process response.
     * @since 1.5.2
     */
	public function import_contacts_raw_get_attrs( WP_REST_Request $request ) {
        // Define the required parameters for the import process.
        $required_params = array('delimiter', 'raw');

        // Check if all required parameters are present in the request.
        foreach ( $required_params as $param ) {
            if ( !$request->has_param($param) ) {
                return $this->get_error_response( __( "Required parameter '$param' is missing.", 'mrm' ), 400 );
            }
        }

        // Get values from the API request.
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->process_imported_raw_data( $params );
        // Return a WP_REST_Response containing the import process response.
        return new WP_REST_Response( $response );
	}

    /**
     * Retrieve and Format Native WordPress Roles.
     *
     * This function retrieves native WordPress user roles and returns them in a formatted response.
     *
     * @return WP_REST_Response Formatted response containing WordPress roles' data.
     *
     * @since 1.5.4
     */
    public function retrieve_and_format_native_wp_roles() {
        $response = $this->action->get_formatted_wp_roles();
        // Return a WP_REST_Response containing the import process response.
        return new WP_REST_Response( $response );
    }

    /**
     * Import contacts with associated native WordPress user roles.
     *
     * Imports contacts along with their corresponding native WordPress user roles
     * based on the provided mapping of roles.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The response containing the import process result.
     * @since 1.5.4
     */
    public function retrieve_wordpress_user_map_fields( WP_REST_Request $request ) {
        // Define the required parameters for the import process.
        $required_params = array('roles');

        // Check if all required parameters are present in the request.
        foreach ( $required_params as $param ) {
            if ( !$request->has_param($param) ) {
                return $this->get_error_response( __( "Required parameter '$param' is missing.", 'mrm' ), 400 );
            }
        }
        // Get values from the API request.
        $params = MrmCommon::prepare_request_params( $request );
        $response = $this->action->retrieve_contacts_associated_with_native_wp_roles( $params );
        // Return a WP_REST_Response containing the import process response.
        return new WP_REST_Response( $response );
    }

        /**
     * Import Contacts from CSV using REST API.
     *
     * This function imports contacts from a CSV file using the REST API. It prepares the request parameters,
     * calls the appropriate action to perform the contact import, and returns the response as a REST API response.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The REST API response containing the import process result.
     *
     * @since 1.5.4
     */
	public function import_contacts_from_csv( WP_REST_Request $request ) {
		// Get values from the API request.
		$params = MrmCommon::prepare_request_params( $request );

        // Perform the contact import from CSV.
        $response = $this->action->perform_contact_import( $params, 'csv' );

        // Return the import process result as a REST API response.
		return new WP_REST_Response( $response );
    }

    /**
     * Import Contacts from Raw using REST API.
     *
     * This function imports contacts from a raw data using the REST API. It prepares the request parameters,
     * calls the appropriate action to perform the contact import, and returns the response as a REST API response.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The REST API response containing the import process result.
     *
     * @since 1.5.5
     */
	public function import_contacts_from_raw( WP_REST_Request $request ) {
		// Get values from the API request.
		$params = MrmCommon::prepare_request_params( $request );

        // Perform the contact import from CSV.
        $response = $this->action->perform_contact_import( $params, 'raw' );

        // Return the import process result as a REST API response.
		return new WP_REST_Response( $response );
    }

    /**
     * Insert Native WordPress Contacts via REST API.
     * 
     * This function processes a REST API request to insert native 
     * WordPress contacts based on the provided parameters.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return WP_REST_Response The REST API response containing the result of the import process.
     * @since 1.5.7
     */
    public function insert_native_wp_contacts( WP_REST_Request $request ) {
        // Define the required parameters for the import process.
        $required_params = array('roles');

        // Check if all required parameters are present in the request.
        foreach ( $required_params as $param ) {
            if ( !$request->has_param($param) ) {
                return $this->get_error_response( __( "Required parameter '$param' is missing.", 'mrm' ), 400 );
            }
        }
        // Get values from API.
        $params = MrmCommon::prepare_request_params( $request );

        // Perform the contact import from CSV.
        $response = $this->action->perform_wordpress_user_import( $params );

        // Return the import process result as a REST API response.
		return new WP_REST_Response( $response );
    }

    /**
     * Map contacts with LearnDash.
     *
     * Description: Handles the mapping of contacts with LearnDash based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the mapping.
     * @access public
     * @since 1.8.0
     */
    public function map_contacts_with_learndash( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->retrieve_contacts_associated_with_learndash( $params );
        return new WP_REST_Response( $response );
    }

    /**
     * Insert LearnDash Contacts.
     *
     * Description: Handles the insertion of contacts from LearnDash based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the insertion.
     * @access public
     * @since 1.8.0
     */
    public function insert_learndash_contacts( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->perform_learndash_user_import( $params );
		return new WP_REST_Response( $response );
    }

    /**
     * Map contacts with Tutor LMS.
     *
     * Description: Handles the mapping of contacts with Tutor LMS based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the mapping.
     * @access public
     * @since 1.8.0
     */
    public function map_contacts_with_tutorlms( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->retrieve_contacts_associated_with_tutorlms( $params );
        return new WP_REST_Response( $response );
    }

    /**
     * Insert Tutor LMS Contacts.
     *
     * Description: Handles the insertion of contacts from Tutor LMS based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the insertion.
     * @access public
     * @since 1.8.0
     */
    public function insert_tutorlms_contacts( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->perform_tutorlms_user_import( $params );
		return new WP_REST_Response( $response );
    }

    /**
     * Map contacts with MemberPress.
     *
     * Description: Handles the mapping of contacts with MemberPress based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the mapping.
     * @access public
     * @since 1.8.0
     */
    public function map_contacts_with_memberpress( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->retrieve_contacts_associated_with_memberpress( $params );
		return new WP_REST_Response( $response );
    }

    /**
     * Insert MemberPress Contacts.
     *
     * Description: Handles the insertion of contacts from MemberPress based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the insertion.
     * @access public
     * @since 1.8.0
     */
    public function insert_memberpress_contacts( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->perform_memberpress_user_import( $params );
		return new WP_REST_Response( $response );
    }

    /**
     * Map contacts with LifterLMS.
     *
     * Description: Handles the mapping of contacts with LifterLMS based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the mapping.
     * @access public
     * @since 1.12.0
     */
    public function map_contacts_with_lifterlms( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->retrieve_contacts_associated_with_lifterlms( $params );
        return new WP_REST_Response( $response );
    }

    /**
     * Insert LifterLMS Contacts.
     *
     * Description: Handles the insertion of contacts from LifterLMS based on the provided request parameters.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response The REST response containing the result of the insertion.
     * @access public
     * @since 1.12.0
     */
    public function insert_lifterlms_contacts( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->perform_lifterlms_user_import( $params );
		return new WP_REST_Response( $response );
    }

    /**
     * Retrieve MailPoet contact fields.
     *
     * This function retrieves the contact fields from the MailPoet subscribers table,
     * including both required fields and custom fields.
     *
     * @return array Success response containing the total number of batches and the headers.
     *
     * @since 1.16.9
     */
    public function get_mail_poet_contact_fields(){
        global $wpdb;

        $subscribers_table   = $wpdb->prefix . 'mailpoet_subscribers';
        $custom_fields_table = $wpdb->prefix . 'mailpoet_custom_fields';

        $total_subscribers  = $wpdb->get_var("SELECT COUNT(*) FROM $subscribers_table");

        $required_fields = array(
            'wp_user_id' => 'WP User ID',
            'first_name' => 'First Name',
            'last_name'  => 'Last Name',
            'email'      => 'Email',
            'status'     => 'Status',
        );

        $custom_fields = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM $custom_fields_table"), ARRAY_A);
        foreach ($custom_fields as $custom_field) {
            $required_fields['cf_' . $custom_field['id']] = $custom_field['name'];
        }

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.4.9
         */
        $per_batch = apply_filters('mint_import_batch_limit', 200);
        return $this->get_success_response(__('MailPoet Contacts has been retrieved successfully.', 'mrm'), 200, array(
            'total_batch' => ceil($total_subscribers  / (int) $per_batch),
            'headers'     => $required_fields,
        ));
    }

    /**
     * Inserts MailPoet contacts.
     *
     * This method handles the insertion of MailPoet contacts by preparing the request parameters
     * and performing the MailPoet user import action.
     *
     * @param WP_REST_Request $request The request object containing the parameters for the import.
     * @return WP_REST_Response The response object containing the result of the import action.
        * @since 1.18.9
     */
    public function insert_mail_poet_contacts( WP_REST_Request $request ) {
        $params   = MrmCommon::prepare_request_params( $request );
        $response = $this->action->perform_mail_poet_user_import( $params );
		return new WP_REST_Response( $response );
    }

    /**
     * Retrieve FluentBooking customers.
     *
     * This function retrieves the total number of bookings and the unique meta keys from the booking meta table.
     * It excludes certain fields from the result and returns the total number of batches required for import
     * along with the headers of the booking table and meta keys.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @return array The response containing the total number of batches and headers.
     *
     * @since 1.16.6
     */
    public function get_fluentbooking_customers(){
        global $wpdb;

        $booking_table   = $wpdb->prefix . 'fcal_bookings';
        $total_bookings  = $wpdb->get_var("SELECT COUNT(*) FROM $booking_table");
        $booking_columns = array(
            'email',
            'first_name',
            'last_name',
            'phone',
            'country',
        );

        $booking_meta_table = $wpdb->prefix . 'fcal_booking_meta';
        $meta_keys          = $wpdb->get_col("SELECT DISTINCT meta_key FROM $booking_meta_table ORDER BY meta_key ASC");
        $all_fields         = array_merge($booking_columns, $meta_keys);

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.4.9
         */
        $per_batch = apply_filters('mint_import_batch_limit', 100);
        return $this->get_success_response(__('Total guest has been retrieved successfully.', 'mrm'), 200, array(
            'total_batch' => ceil($total_bookings  / (int) $per_batch),
            'headers'     => array_values(array_unique($all_fields)),
        ));
    }

    /**
     * Import contacts from fluentBooking guests
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     *
     * @return WP_REST_Response
     * @since 1.16.6
     */
    public function import_contacts_fluentbooking(WP_REST_Request $request){
        $params   = MrmCommon::prepare_request_params($request);
        $response = $this->action->perform_fluent_booking_import($params);
        return new WP_REST_Response($response);
    }
}