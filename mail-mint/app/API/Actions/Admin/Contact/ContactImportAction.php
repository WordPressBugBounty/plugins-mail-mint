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

use MailMintPro\App\Utilities\Helper\Integration;
use Mint\MRM\Admin\API\Controllers\MessageController;
use Mint\MRM\API\Actions\Action;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataStores\ContactData;
use Mint\MRM\Utilites\Helper\Import;

/**
 * Class ContactImportAction
 *
 * Summary: Contact Import Action implementation.
 * Description: Implements the Contact Import Action interface and provides methods to fetch MailChimp lists and contact attributes,
 * and retrieve MailChimp member headers.
 *
 * @since 1.4.9
 */
class ContactImportAction implements Action {

    /**
     * Summary: Fetches MailChimp lists and contact attributes.
     *
     * Description: Fetches MailChimp lists and contact attributes based on the provided API key.
     *
     * @access public
     * 
     * @param string $key The MailChimp API key.
     * @return array Returns an array containing MailChimp lists, headers, and contact attributes.
     * @since 1.4.9
     */
    public function fetch_mailchimp_lists_and_contact_attributes( $key ){
        $response = Import::get_mailchimp_response( $key, 'lists' );

        if( isset( $response['status'] ) && 401 === $response['status'] ){
            return $response;
        }

        $lists = isset ( $response['lists'] ) ? $response['lists'] : array();

        $lists_arr = Import::get_format_mailchimp_lists( $lists );

        $contacts_attrs = Import::get_contact_general_fields();
        $contacts_attrs = apply_filters( 'mint_contacts_attrs', $contacts_attrs );

        return array(
            'mailchimp_lists' => $lists_arr,
            'headers'         => array(),
            'fields'          => $contacts_attrs,
        );
    }

    /**
     * Summary: Retrieves MailChimp member headers.
     * Description: Retrieves the headers for MailChimp members based on the provided parameters.
     * 
     * @access public
     *
     * @param array $params The parameters including list ID, API key, member count, etc.
     * 
     * @return array Returns an array containing the total member count, total batch count, batch size, and headers.
     * @since 1.4.9
     */
	public function get_mailchimp_member_headers( $params ){
        // Check if list ID is empty.
        $list_id = isset( $params['list_id'] ) ? $params[ 'list_id' ] : '';
        if( empty( $list_id ) ){
            return array();
        }

        $key          = isset( $params['key'] ) ? $params['key'] : '';
        $member_count = isset( $params['member_count'] ) ? (int) $params['member_count'] : 0;
        $batch_size   = 100;

        // Get merge fields from MailChimp API.
        $response = Import::get_mailchimp_response( $key, "lists/{$list_id}/merge-fields" );

        // Check if API response has authentication error.
        if( isset( $response['status'] ) && 401 === $response['status'] ){
            return $response;
        }

        $merge_fields = isset( $response['merge_fields'] ) ? $response['merge_fields'] : array();

        $headers = array(
            'email_address',
            'addr1',
            'addr2',
            'city',
            'state',
            'zip',
            'country',
        );

        // Extract tags from merge fields and remove empty tags.
        $tags    = array_column( $merge_fields, 'tag' );
        $tags    = array_filter( $tags, 'strlen' );
        $headers = array_merge( $headers, $tags );

        return array (
            'total'       => $member_count,
            'total_batch' => ceil( $member_count / $batch_size ),
            'per_batch'   => $batch_size,
            'headers'     => $headers
        );
	}

    /**
     * Process contact attribute import using provided parameters.
     *
     * This function processes the contact attribute import based on the provided parameters.
     * 
     * @access public
     *
     * @param array $params The parameters containing CSV file and delimiter information.
     * @return array The array containing the import process result.
     * @since 1.5.1
     */
    public function process_contact_attribute_import( $params ) {
        // Check if the CSV file is provided in the parameters.
        $file = isset( $params['csv'] ) ? $params['csv'] : '';
		
        // Validate the uploaded CSV file.
        $response = Import::csv_file_upload_validation( $file );

        if( !$response ){
            return array(
                'status'  => 'failed',
                'message' => __('Please upload a valid CSV first.', 'mrm')
            );
        }

        $delimiter = isset( $params['delimiter'] ) && 'comma' === $params['delimiter'] ? ',' : ';';

        // Create a CSV from the imported file using the specified delimiter.
        $import_res = Import::create_csv_from_import( $file, $delimiter );
        if ( ! is_array( $import_res ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('Unable to upload CSV file.', 'mrm')
            );
        }

        // Get the imported file location and new file name.
        $file_location = isset( $import_res['file'] ) ? $import_res['file'] : '';
        $new_file_name = isset( $import_res['new_file_name']  ) ? $import_res['new_file_name'] : '';

        $options = Import::prepare_mapping_options_from_csv( $file_location, $delimiter );

        if ( isset( $options['headers'] ) && empty( $options['headers'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('Please upload a properly formatted CSV file.', 'mrm')
            );
        }

        $row_array       = Import::create_array_from_csv( $new_file_name, $delimiter );
        $fieldValidation = Import::field_validation( $row_array );

        if ( ! $fieldValidation ) {
            return array(
                'status'  => 'failed',
                'message' => __('Please upload a valid CSV first.', 'mrm')
            );
        }
        // Create batches for CSV processing.
        $batch_array = Import::csv_batch_creator( $new_file_name, $delimiter );

        wp_delete_file( $new_file_name );

        // Prepare the result with headers, fields, and batch information.
        $result = array(
            'headers' => $options['headers'],
            'fields'  => isset( $options['fields'] ) ? $options['fields'] : '',
            'file'    => $new_file_name,
        );

        // Merge the result with batch information and return the response.
        $result = array_merge( $result, $batch_array );
        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('File has been uploaded successfully.', 'mrm')
        );
    }

    /**
     * Process raw contact attributes data for import.
     *
     * This function processes the raw contact attributes data for import, including parsing,
     * validation, and preparing data for the import operation.
     * 
     * @access public
     *
     * @param array $params The parameters containing raw data and delimiter.
     *
     * @return array The response indicating the success or failure of the processing,
     * along with relevant data and messages.
     * @since 1.5.2
     */
    public function process_imported_raw_data( $params ) {
        $raw = isset( $params['raw'] ) ? $params['raw'] : array();
        if ( empty( $raw ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('Please enter data into the textarea for processing.', 'mrm')
            );
        }

        $delimiter = isset( $params['delimiter'] ) && 'comma' === $params['delimiter'] ? ',' : ';';

        // Parse the raw data into headers and content.
        $parsed_array = Import::parse_raw_data($raw, $delimiter);

        $headers = isset( $parsed_array['headers'] ) ? $parsed_array['headers'] : array();
        $content = isset( $parsed_array['content'] ) ? $parsed_array['content'] : array();

        if( !is_array( $headers ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('The data provided does not match the selected delimiter format.', 'mrm')
            );
        }

        // Validate the raw data fields against the selected delimiter format.
        $flag = Import::validate_raw_data_format( $content, $delimiter, $headers );
        if ( ! $flag ){
            return array(
                'status'  => 'failed',
                'message' => __('The data provided does not match the selected delimiter format.', 'mrm')
            );
        }
        
        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.1
         */
        $per_batch   = apply_filters( 'mint_import_batch_limit', 500 );
        $total_batch = ceil( count( $content ) / $per_batch );
        $offset      = 0;

        $batch_array = array(
            'total_batch' => $total_batch,
            'offset'      => $offset,
            'per_batch'   => $per_batch,
        );

        // Get existing contact fields.
        $contacts_attrs = Import::get_contact_general_fields();
        $contacts_attrs = apply_filters( 'mint_contacts_attrs', $contacts_attrs );

        // Prepare the result array.
        $result = array(
            'raw'       => $content,
            'headers'   => $headers,
            'fields'    => $contacts_attrs,
            'delimiter' => $delimiter
        );

        $result = array_merge($result, $batch_array);

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Data has been successfully uploaded and processed.', 'mrm')
        );
	}

    /**
     * Imports contacts based on the provided data and mapping.
     *
     * This function processes the import of contacts based on the provided data and mapping.
     * It handles both CSV and raw data imports.
     * 
     * @access public
     *
     * @param array  $params      The parameters containing import data and configuration.
     * @param string $import_type The type of import, either 'csv' or 'raw'.
     * @return array An array containing import statistics and status.
     *
     * @since 1.5.4
     */
    public function perform_contact_import( $params, $import_type ){
        $skipped     = 0;
        $exists      = 0;
        $total_count = 0;
        $imported    = 0;

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
		}

        $mappings = isset( $params['map'] ) ? $params['map'] : array();

        if ( empty( $mappings ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('Please map at least one field for importing.', 'mrm')
            );
        }

        $headers     = isset($params['headers']) ? $params['headers'] : [];
        $delimiter   = isset($params['delimiter']) ? $params['delimiter'] : ',';
        $data        = $import_type === 'csv' ? $params['data'] : $params['raw'];
        $total_count = count($data);

        foreach ($data as $row) {
            $result = $this->process_individual_contact_row_for_import($row, $headers, $mappings, $params, $delimiter, $import_type);

            if( !is_array( $result ) ) {
                return array(
                    'status'  => $result,
                    'message' => __('The email field is required.', 'mrm')
                );
            }

            $skipped += $result['skipped'];
            $exists += $result['exists'];
            $imported += $result['imported'];
        }

        $result = array(
            'total'             => $total_count,
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'imported'          => $imported,
        );

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }

    /**
     * Process Individual Contact Row for Import.
     *
     * This private function handles the processing of an individual contact row during the import.
     * 
     * @access private
     *
     * @param array  $row_array   The data of the current row to be processed.
     * @param array  $header      The header fields for mapping.
     * @param array  $mappings    The mappings for contact data fields.
     * @param array  $params      Additional parameters for the import process.
     * @param string $delimiter   The delimiter used in the import data.
     * @param string $import_type The type of import, either 'csv' or 'raw'.
     * @return array An array containing import statistics and status.
     *
     * @since 1.5.4
     */
    private function process_individual_contact_row_for_import($row_array, $header, $mappings, $params, $delimiter, $import_type) {
        $skipped  = 0;
        $exists   = 0;
        $imported = 0;

        if( 'raw' === $import_type ) {
            $row_array = explode( $delimiter, (string)$row_array ); 
        }

        if ( count( $header ) !== count( $row_array ) ) {
            $skipped++;
            return compact('skipped', 'exists', 'imported');
        }
    
        $csv_contact = array_combine($header, $row_array);

        $status      = isset($params['status']) ? $params['status'] : '';
        $created_by  = isset($params['created_by']) ? $params['created_by'] : '';
    
        $contact_args = Import::prepare_contact_arguments($csv_contact, $mappings, $import_type, $status, $created_by);

        if ( ! array_key_exists( 'email', $contact_args ) ) {
            return 'failed';
        }
    
        $contact_email = trim($contact_args['email']);

        $settings = get_option( '_mint_integration_settings', array(
            'zero_bounce' => array(
                'api_key' => '',
                'email_address' => '',
                'is_integrated' => false,
            ),
        ) );

        $zero_bounce   = isset( $settings['zero_bounce'] ) ? $settings['zero_bounce'] : array();
		$api_key       = isset( $zero_bounce['api_key'] ) ? $zero_bounce['api_key'] : '';
		$is_integrated = isset( $zero_bounce['is_integrated'] ) ? $zero_bounce['is_integrated'] : false;
        $response      = array();

		if( $is_integrated ) {
            $response = Integration::handle_zero_bounce_request( $api_key, $contact_email );

            if ( $contact_email && is_email( $contact_email ) && isset ( $response['body']['status'] ) && 'invalid' !== $response['body']['status'] ) {
                return $this->process_valid_contact($contact_args, $params, $contact_email, $imported, $exists, $skipped);
            } else {
                return $this->process_invalid_contact( $skipped, $imported, $exists );
            }
		}

        if ( $contact_email && is_email( $contact_email ) ) {
            return $this->process_valid_contact($contact_args, $params, $contact_email, $imported, $exists, $skipped);
        } else {
            return $this->process_invalid_contact( $skipped, $imported, $exists );
        }
    
        return compact('skipped', 'exists', 'imported');
    }

    /**
     * Process Valid Contact.
     *
     * This function handles the processing of a valid contact. It checks if the contact already exists
     * and either inserts a new contact or updates an existing one based on the email.
     * 
     * @access public
     *
     * @param array   $contact_args   Prepared arguments for the contact.
     * @param array   $params         Additional parameters from the import process.
     * @param string  $contact_email  The email of the contact being processed.
     * @param int     $imported       Total number of successfully imported contacts.
     * @param int     $exists         Total number of existing contacts.
     * @param int     $skipped        Total number of skipped contacts.
     * @return string|array Result of processing valid contact or specific message if failed.
     *
     * @since 1.5.4
     */
    private function process_valid_contact($contact_args, $params, $contact_email, $imported, $exists, $skipped) {
		$is_exists = ContactModel::is_contact_exist($contact_email);
		
		if (!$is_exists) {
			return $this->insert_new_contact_and_handle_operations($contact_args, $params, $imported, $skipped, $exists);
		} else {
			return $this->update_or_skip_existing_contact($contact_args, $params, $contact_email, $exists, $skipped, $imported);
		}
	}

    /**
     * Handle Invalid Contact.
     *
     * This function handles an invalid contact during the import process. It increments the skipped count
     * and returns the updated counts.
     * 
     * @access private
     *
     * @param int $skipped The current count of skipped contacts.
     * @param int     $imported       Total number of successfully imported contacts.
     * @param int     $exists         Total number of existing contacts.
     * @return array Updated counts of skipped, exists, and imported contacts.
     *
     * @since 1.5.4
     */
    private function process_invalid_contact( $skipped, $imported, $exists ) {
		$skipped++;
		return compact('skipped', 'exists', 'imported');
	}

    /**
     * Insert New Contact and Handle Post-Insert Operations.
     *
     * This function inserts a new contact into the database based on the provided contact arguments,
     * and performs additional post-insert operations.
     * 
     * @access private
     *
     * @param array $contact_args Arguments for the new contact to be inserted.
     * @param array $params Additional parameters for the import process.
     * @param int $imported The current count of imported contacts.
     * @param int $skipped The current count of skipped contacts.
     * @param int $exists The current count of existing contacts.
     * @return array Updated counts of skipped, exists, and imported contacts.
     *
     * @since 1.5.4
     * @modified 1.7.1 Add get_or_insert_contact_group_by_title function to map list and  tag
     */
    private function insert_new_contact_and_handle_operations($contact_args, $params, $imported, $skipped, $exists) {
		$contact = new ContactData($contact_args['email'], $contact_args);
		$contact_id = ContactModel::insert($contact);

        $mapping_lists = isset( $contact_args['groups']['lists'] ) ? $contact_args['groups']['lists'] : '';
        $mapping_lists = preg_split('/,\s*|\s*,\s*/', $mapping_lists);

        foreach( $mapping_lists as $mapping_list ) {
            $list =  ContactGroupModel::get_or_insert_contact_group_by_title( $mapping_list, 'lists' );
            // Push the data into the 'lists' array.
            if ( !empty( $list ) ) {
                $params['lists'][] = $list;
            }
        }

        $mapping_tags = isset( $contact_args['groups']['tags'] ) ? $contact_args['groups']['tags'] : '';
        $mapping_tags = preg_split('/,\s*|\s*,\s*/', $mapping_tags);

        foreach( $mapping_tags as $mapping_tag ) {
            $tag = ContactGroupModel::get_or_insert_contact_group_by_title( $mapping_tag, 'tags' );
            // Push the data into the 'tags' array.
            if ( !empty( $tag ) ) {
                $params['tags'][] = $tag;
            }
        }

		if (isset($contact_args['status']) && 'pending' === $contact_args['status']) {
			MessageController::get_instance()->send_double_opt_in($contact_id);
		}
		
		$this->set_tags_and_lists_for_contact($params, $contact_id);
		$imported++;
        return compact('skipped', 'exists', 'imported');
	}

    /**
     * Set Tags and Lists for the Contact.
     *
     * This function associates tags and lists with a specific contact based on the provided parameters.
     * 
     * @access private
     *
     * @param array $params Additional parameters for the import process.
     * @param int $contact_id The ID of the contact to set tags and lists for.
     *
     * @since 1.5.4
     */
    private function set_tags_and_lists_for_contact($params, $contact_id) {
		if (isset($params['tags'])) {
			ContactGroupModel::set_tags_to_contact($params['tags'], $contact_id);
		}
	
		if (isset($params['lists'])) {
			ContactGroupModel::set_lists_to_contact($params['lists'], $contact_id);
		}
	}

    /**
     * Update Existing Contact or Skip based on Parameters.
     *
     * This function updates an existing contact's information or skips it based on the provided parameters.
     * If 'skip_existing' is set to true in the parameters, the contact will be skipped.
     * 
     * @access private
     *
     * @param array $contact_args Contact arguments containing updated information.
     * @param array $params Additional parameters for the update process.
     * @param string $contact_email The email of the existing contact.
     * @param int $exists The count of existing contacts processed.
     * @param int $skipped The count of skipped contacts.
     * @param int $imported The count of successfully imported contacts.
     *
     * @return array The counts of skipped, existing, and imported contacts.
     *
     * @since 1.5.4
     * @modified 1.7.1 Add get_or_insert_contact_group_by_title function to map list and  tag
     */
    private function update_or_skip_existing_contact($contact_args, $params, $contact_email, $exists, $skipped, $imported) {
		if (isset($params['skip_existing']) && $params['skip_existing']) {
            $skipped++;
			return compact('skipped', 'exists', 'imported');
		}

        $mapping_lists = isset( $contact_args['groups']['lists'] ) ? $contact_args['groups']['lists'] : '';
        $mapping_lists = preg_split('/,\s*|\s*,\s*/', $mapping_lists);

        foreach( $mapping_lists as $mapping_list ) {
            $list =  ContactGroupModel::get_or_insert_contact_group_by_title( $mapping_list, 'lists' );
            // Push the data into the 'lists' array.
            if ( !empty( $list ) ) {
                $params['lists'][] = $list;
            }
        }
        
        $mapping_tags = isset( $contact_args['groups']['tags'] ) ? $contact_args['groups']['tags'] : '';
        $mapping_tags = preg_split('/,\s*|\s*,\s*/', $mapping_tags);

        foreach( $mapping_tags as $mapping_tag ) {
            $tag = ContactGroupModel::get_or_insert_contact_group_by_title( $mapping_tag, 'tags' );
            if ( !empty( $tag ) ) {
                // Push the data into the 'tags' array.
                $params['tags'][] = $tag;
            }
        }

		$contact_id = ContactModel::get_id_by_email($contact_email);
		ContactModel::update($contact_args, $contact_id);
	
		if (isset($contact_args['status']) && 'pending' === $contact_args['status'] && isset($params['optin_confirmation']) && $params['optin_confirmation']) {
			MessageController::get_instance()->send_double_opt_in($contact_id);
		}
	
		$this->set_tags_and_lists_for_contact($params, $contact_id);
		$exists++;
        return compact('skipped', 'exists', 'imported');
	}

    /**
     * Get WordPress Roles with Formatting.
     *
     * This function retrieves the WordPress user roles and formats them into an array.
     * 
     * @access public
     *
     * @return array Formatted response containing roles' data.
     *
     * @since 1.5.4
     */
	public function get_formatted_wp_roles() {
		// Get and formatting editable roles.
		$formatted_roles = array();
		
        $wp_roles = wp_roles();

        foreach ( $wp_roles->get_names() as $key => $value ) {
            $formatted_roles[] = array(
                'role' => $key,
                'name' => $value,
            );
        }

        return array(
            'status'  => 'success',
            'data'    => $formatted_roles,
            'message' => __('Roles have been successfully retrieved.', 'mrm')
        );
	}

    /**
     * Retrieve Contacts Associated with Native WordPress User Roles.
     *
     * Retrieve contacts and associates them with specific native WordPress user roles
     * based on the provided roles mapping. Returns information about the import process.
     * 
     * @access public
     *
     * @param array $params Parameters for the import process including roles mapping.
     * @return array Associative array containing import process information.
     * @since 1.5.4
     */
    public function retrieve_contacts_associated_with_native_wp_roles( $params ) {
        
        if ( empty( $params['roles'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('Please specify the roles attribute to continue.', 'mrm')
            );
        }

        $count_users = count_users();
        $total_users = 0;

        if( in_array( 'all_roles', $params['roles'] ) ) {
            $total_users = isset( $count_users['total_users'] ) ? $count_users['total_users'] : 0;
        }else{
            $avail_roles    = isset( $count_users['avail_roles'] ) ? $count_users['avail_roles'] : array();
            $filtered_roles = array_intersect_key($avail_roles, array_flip($params['roles']));
            $total_users    = array_sum($filtered_roles);
        }

        $headers = apply_filters( 'mint_wordpress_user_import_headers', array(
            'ID',
            'user_email',
            'user_registered',
            'user_login',
            'nickname',
            'first_name',
            'last_name',
        ) );

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        return array(
            'status'  => 'success',
            'data'    => array(
                'headers'     => $headers,
                'total_batch' => ceil( $total_users / (int) $per_batch ),
                'total'       => $total_users,
                'roles'       => $params['roles']
            ),
            'message' => __('Data retrieval and processing completed successfully.', 'mrm')
        );
    }

    /**
     * Format Contact Data from WordPress User Object.
     *
     * Formats contact data from a WordPress user object, including user-related information
     * and user metadata. Returns the formatted contact data.
     * 
     * @access public
     *
     * @param WP_User $wp_user The WordPress user object.
     * @return array Formatted contact data including user-related information and metadata.
     * @since 1.5.4
     */
    public function format_contact_data_from_wp_user( $wp_user ) {
        $user_data = $wp_user->data;

        if ( empty( (array) $user_data) ) {
            return array();
        }
    
        $user_metadata = $user_data->usermeta;
    
        $contact = [
            'user_email'      => $user_data->user_email,
            'id'              => $user_data->ID,
            'user_registered' => $user_data->user_registered,
            'user_pass'       => $user_data->user_pass,
            'user_login'      => $user_data->user_login,
        ];
    
        return array_merge($contact, $user_metadata);
    }

    /**
     * Perform WordPress User Import.
     *
     * This function handles the import of WordPress users based on the provided parameters.
     * 
     * @access public
     *
     * @param array $params The import parameters.
     *
     * @return array The result of the import process including the number of imported, skipped, and existing contacts.
     * @since 1.16.4
     */
    public function perform_wordpress_user_import( $params ){
        $skipped     = 0;
        $exists      = 0;
        $imported    = 0;

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
		}

        $mappings = isset($params['map']) ? $params['map'] : array();

        if (empty($mappings)) {
            return array(
                'status'  => 'failed',
                'message' => __('Please map at least one field for importing.', 'mrm')
            );
        }

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         *
         * @since 1.5.6
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );
    
        try {
            $wp_users = Import::get_wp_users_by_roles_with_limit_offset_in_a_flat_array( $params['roles'], $per_batch, $params['offset'] );

            if ( ! is_array( $wp_users ) || empty( $wp_users ) ) {
                return array(
                    'status'  => 'failed',
                    'message' => __( 'No WordPress user available to import.', 'mrm' )
                );
            }
            foreach ( $wp_users as $wp_user ) {

                if ( isset( $wp_user['user_email'] ) && ! is_email( $wp_user['user_email'] ) ) {
                    $skipped++;
                    continue;
                }
    
                $contact_args = array(
                    'status'      => isset( $params['status'] ) ? $params['status'] : 'pending',
                    'source'      => 'WordPress',
                    'meta_fields' => array(),
                    'created_by'  => isset( $params['created_by'] ) ? $params['created_by'] : '',
                ); 
                foreach ( $params['map'] ?? [] as $map ) {
                    $target = $map['target'] ?? '';
                    $source = $map['source'] ?? '';
                    if ( in_array( $target, array( 'first_name', 'last_name', 'email' ), true ) ) {
                        $contact_args[ $target ] = $wp_user[ $source ] ?? '';
                    } else {
                        $contact_args['meta_fields'][ $target ] = $wp_user[ $source ] ?? '';
                    }
                }
                if ( empty( $contact_args['email'] ) ) {
                    return array(
                        'status'  => 'failed',
                        'message' => __( 'The email field is required.', 'mrm' )
                    );
                }
    
                $contact_email = trim( $contact_args['email'] );
                $exists = ContactModel::is_contact_exist( $contact_email );
    
                if ( ! $exists ) {
                    $contact      = new ContactData( $contact_email, $contact_args );
                    $contact_id = ContactModel::insert( $contact );
    
                    if ( 'pending' === $contact_args['status'] ) {
                        MessageController::get_instance()->send_double_opt_in( $contact_id );
                    }
    
                    if ( isset( $params['tags'] ) ) {
                        ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
                    }
    
                    if ( isset( $params['lists'] ) ) {
                        ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
                    }
                    $imported++;
                } else {
                    if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
                        $skipped++;
                        $total_count++;
                        continue;
                    }
    
                    $contact_id     = ContactModel::get_id_by_email( $contact_email );
                    $contact_update = ContactModel::update( $contact_args, $contact_id );
    
                    if ( 'pending' === $contact_args['status'] && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
                        MessageController::get_instance()->send_double_opt_in( $contact_id );
                    }
    
                    if ( isset( $params['tags'] ) ) {
                        ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
                    }
    
                    if ( isset( $params['lists'] ) ) {
                        ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
                    }
                    $skipped++;
                }
                $total_count++;
            }
    
            $result = array(
                'imported'          => $imported,
                'total'             => count( $wp_users ),
                'skipped'           => $skipped,
                'existing_contacts' => $exists_count,
                'offset'            => $params['offset'] + (int) $per_batch,
            );
    
            return array(
                'status'  => 'success',
                'data'    => $result,
                'message' => __( 'Import contact has been successful.', 'mrm' )
            );
        } catch ( Exception $e ) {
            return array(
                'status'  => 'failed',
                'message' => __( 'Import has not been successful.', 'mrm' )
            );
        }

        foreach ( $wp_users as $wp_user ) {
            if ( isset( $wp_user['user_email'] ) && ! is_email( $wp_user['user_email'] ) ) {
                $skipped++;
                continue;
            }
            $result = $this->process_individual_wordpress_user_to_insert($wp_user, $params, $mappings);

            $skipped  += $result['skipped'];
            $exists   += $result['exists'];
            $imported += $result['imported'];
        }
        // Prepare data for success response.
        $result = array(
            'imported'          => $imported,
            'total'             => count( $wp_users ),
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'offset'            => $params['offset'] + (int) $per_batch,
        );

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }
    

    /**
     * Process an individual WordPress user for insertion into contacts.
     * 
     * @access public
     * 
     * @param array $wp_user The WordPress user data.
     * @param array $params  The import parameters.
     * @param string $source The contact source.
     *
     * @return array The result of the import process for this user, including skipped, existing, and imported counts.
     * @since 1.5.7
     * @modified 1.8.0 Add contact source as param
     */
    public function process_individual_user_to_insert($wp_user, $params, $source){
        $skipped  = 0;
        $exists   = 0;
        $imported = 0;

        $contact_args = array(
            'email'      => isset($wp_user['user_email']) ? $wp_user['user_email'] : '',
            'first_name' => isset($wp_user['first_name']) ? $wp_user['first_name'] : '',
            'last_name'  => isset($wp_user['last_name']) ? $wp_user['last_name'] : '',
            'status'     => isset($params['status']) ? $params['status'] : 'pending',
            'source'     => $source,
            'created_by' => isset($params['created_by']) ? $params['created_by'] : '',
            'wp_user_id' => isset($wp_user['id']) ? $wp_user['id'] : 0,
        );

        if (! array_key_exists('email', $contact_args)) {
            return 'failed';
        }

        $contact_email = trim($contact_args['email']);

        if ($contact_email && is_email($contact_email)) {
            return $this->process_valid_contact($contact_args, $params, $contact_email, $imported, $exists, $skipped);
        } else {
            return $this->process_invalid_contact($skipped, $imported, $exists);
        }

        return compact('skipped', 'exists', 'imported');
    }

    /**
     * Process an individual WordPress user for insertion into contacts.
     * 
     * @access public
     * 
     * @param array $wp_user The WordPress user data.
     * @param array $params  The import parameters.
     * @param array $mappings The contact mappings.
     *
     * @return array The result of the import process for this user, including skipped, existing, and imported counts.
     * @since 1.5.7
     * @since 1.8.0 Add contact source as param
     * @since 1.16.5 Add $mapping as param
     */
    public function process_individual_wordpress_user_to_insert($wp_user, $params, $mappings) {
        $skipped  = 0;
        $exists   = 0;
        $imported = 0;

        $contact_args = array(
            'status'      => isset($params['status']) ? $params['status'] : 'pending',
            'source'      => 'WordPress',
            'meta_fields' => array(),
            'created_by'  => get_current_user_id(),
        );

        foreach ( $mappings as $map ) {
            $target = isset($map['target']) ? $map['target'] : '';
            $source = isset($map['source']) ? $map['source'] : '';

            if (in_array($target, array('first_name', 'last_name', 'email'), true)) {
                $contact_args[$target] = $wp_user[$source];
            } elseif (in_array($target, array('lists', 'tags'), true)) {
                $contact_args['groups'][$target] = $wp_user[$source];
            } else {
                $contact_args['meta_fields'][$target] = $wp_user[$source];
            }
        }

        if ( ! array_key_exists( 'email', $contact_args ) ) {
            return 'failed';
        }
    
        $contact_email = trim($contact_args['email']);

        if ($contact_email && is_email($contact_email)) {
            return $this->process_valid_contact($contact_args, $params, $contact_email, $imported, $exists, $skipped);
        } else {
            return $this->process_invalid_contact( $skipped, $imported, $exists );
        }
    
        return compact('skipped', 'exists', 'imported');
    }

    /**
     * Retrieve contacts associated with LearnDash.
     *
     * Description: Retrieves contacts associated with LearnDash based on the provided parameters.
     *
     * @param array $params The parameters for retrieving contacts.
     * @return array An array containing the status, data, and message of the retrieval operation.
     * @access public
     * @since 1.8.0
     */
    public function retrieve_contacts_associated_with_learndash( $params ) {
        $wp_users    = Import::get_wp_users_by_learndash_with_limit_offset( $params['selectedCourses'], 5 );
        $contacts    = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);
        $total_users = (int) $wp_users['total_users'];

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        return array(
            'status'  => 'success',
            'data'    => array(
                            'contacts'         => $contacts,
                            'total_batch'      => ceil( $total_users / (int) $per_batch ),
                            'total'            => $total_users,
                            'isImportByCourse' => $params['isImportByCourse'],
                            'selectedCourses'  => $params['selectedCourses'],
                        ),
            'message' => __('Data retrieval and processing completed successfully.', 'mrm')
        );
    }

    /**
     * Perform LearnDash user import.
     *
     * Description: Imports LearnDash users based on the provided parameters.
     *
     * @param array $params The parameters for performing LearnDash user import.
     * @return array An array containing the status, data, and message of the import operation.
     * @access public
     * @since 1.8.0
     */
    public function perform_learndash_user_import( $params ){
        $skipped     = 0;
        $exists      = 0;
        $imported    = 0;

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
		}

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.6
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        $wp_users = Import::get_wp_users_by_learndash_with_limit_offset($params['selectedCourses'], $per_batch, $params['offset']);

        if ( !is_array( $wp_users['formatted_users'] ) && empty( $wp_users['formatted_users'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('No LearnDash user available to import.', 'mrm')
            );
        }

        $wp_users = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);

        foreach ( $wp_users as $wp_user ) {
            $result = $this->process_individual_user_to_insert($wp_user, $params, 'LearnDash');

            $skipped += $result['skipped'];
            $exists += $result['exists'];
            $imported += $result['imported'];
        }
        // Prepare data for success response.
        $result = array(
            'imported'          => $imported,
            'total'             => count( $wp_users ),
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'offset'            => $params['offset'] + (int) $per_batch,
        );

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }

    /**
     * Retrieve contacts associated with Tutor LMS.
     *
     * Description: Retrieves contacts associated with LearnDash based on the provided parameters.
     *
     * @param array $params The parameters for retrieving contacts.
     * @return array An array containing the status, data, and message of the retrieval operation.
     * @access public
     * @since 1.8.0
     */
    public function retrieve_contacts_associated_with_tutorlms( $params ) {
        $wp_users    = Import::get_wp_users_by_tutorlms_with_limit_offset( $params['selectedCourses'], 5 );
        $contacts    = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);
        $total_users = (int) $wp_users['total_users'];

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        return array(
            'status'  => 'success',
            'data'    => array(
                            'contacts'         => $contacts,
                            'total_batch'      => ceil( $total_users / (int) $per_batch ),
                            'total'            => $total_users,
                            'isImportByCourse' => $params['isImportByCourse'],
                            'selectedCourses'  => $params['selectedCourses'],
                        ),
            'message' => __('Data retrieval and processing completed successfully.', 'mrm')
        );
    }

    /**
     * Perform Tutor LMS user import.
     *
     * Description: Imports Tutor LMS users based on the provided parameters.
     *
     * @param array $params The parameters for performing Tutor LMS user import.
     * @return array An array containing the status, data, and message of the import operation.
     * @access public
     * @since 1.8.0
     */
    public function perform_tutorlms_user_import( $params ){
        $skipped     = 0;
        $exists      = 0;
        $imported    = 0;

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
		}

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.6
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        $wp_users = Import::get_wp_users_by_tutorlms_with_limit_offset($params['selectedCourses'], $per_batch, $params['offset']);

        if ( !is_array( $wp_users['formatted_users'] ) && empty( $wp_users['formatted_users'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('No Tutor LMS user available to import.', 'mrm')
            );
        }

        $wp_users = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);

        foreach ( $wp_users as $wp_user ) {
            $result = $this->process_individual_user_to_insert($wp_user, $params, 'Tutor LMS');

            $skipped += $result['skipped'];
            $exists += $result['exists'];
            $imported += $result['imported'];
        }
        // Prepare data for success response.
        $result = array(
            'imported'          => $imported,
            'total'             => count( $wp_users ),
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'offset'            => $params['offset'] + (int) $per_batch,
        );

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }

    /**
     * Retrieve contacts associated with MemberPress.
     *
     * Description: Retrieves contacts associated with MemberPress based on the provided parameters.
     *
     * @param array $params The parameters for retrieving contacts.
     * @return array An array containing the status, data, and message of the retrieval operation.
     * @access public
     * @since 1.8.0
     */
    public function retrieve_contacts_associated_with_memberpress( $params ) {
        $wp_users    = Import::get_wp_users_by_memberpress_with_limit_offset( $params['selectedLevels'], 5 );
        $contacts    = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);
        $total_users = (int) $wp_users['total_users'];

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        return array(
            'status'  => 'success',
            'data'    => array(
                            'contacts'        => $contacts,
                            'total_batch'     => ceil( $total_users / (int) $per_batch ),
                            'total'           => $total_users,
                            'isImportByLevel' => $params['isImportByLevel'],
                            'selectedLevels'  => $params['selectedLevels'],
                        ),
            'message' => __('Data retrieval and processing completed successfully.', 'mrm')
        );
    }

    /**
     * Perform MemberPress user import.
     *
     * Description: Imports MemberPress users based on the provided parameters.
     *
     * @param array $params The parameters for performing MemberPress user import.
     * @return array An array containing the status, data, and message of the import operation.
     * @access public
     * @since 1.8.0
     */
    public function perform_memberpress_user_import( $params ){
        $skipped     = 0;
        $exists      = 0;
        $imported    = 0;

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
		}

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.6
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        $wp_users = Import::get_wp_users_by_memberpress_with_limit_offset($params['selectedLevels'], $per_batch, $params['offset']);

        if ( !is_array( $wp_users['formatted_users'] ) && empty( $wp_users['formatted_users'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('No MemberPress user available to import.', 'mrm')
            );
        }

        $wp_users = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);

        foreach ( $wp_users as $wp_user ) {
            $result = $this->process_individual_user_to_insert($wp_user, $params, 'MemberPress');

            $skipped += $result['skipped'];
            $exists += $result['exists'];
            $imported += $result['imported'];
        }
        // Prepare data for success response.
        $result = array(
            'imported'          => $imported,
            'total'             => count( $wp_users ),
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'offset'            => $params['offset'] + (int) $per_batch,
        );

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }

    /**
     * Retrieve contacts associated with LifterLMS.
     *
     * Description: Retrieves contacts associated with LifterLMS based on the provided parameters.
     *
     * @param array $params The parameters for retrieving contacts.
     * @return array An array containing the status, data, and message of the retrieval operation.
     * @access public
     * @since 1.12.0
     */
    public function retrieve_contacts_associated_with_lifterlms( $params ) {
        $wp_users    = Import::get_wp_users_by_lifterlms_with_limit_offset( $params['selectedCourses'], 5 );
        $contacts    = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);
        $total_users = (int) $wp_users['total_users'];

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        return array(
            'status'  => 'success',
            'data'    => array(
                            'contacts'         => $contacts,
                            'total_batch'      => ceil( $total_users / (int) $per_batch ),
                            'total'            => $total_users,
                            'isImportType'     => $params['isImportType'],
                            'selectedCourses'  => $params['selectedCourses'],
                        ),
            'message' => __('Data retrieval and processing completed successfully.', 'mrm')
        );
    }

    /**
     * Perform LifterLMS user import.
     *
     * Description: Imports LifterLMS users based on the provided parameters.
     *
     * @param array $params The parameters for performing LifterLMS user import.
     * @return array An array containing the status, data, and message of the import operation.
     * @access public
     * @since 1.12.0
     */
    public function perform_lifterlms_user_import( $params ){
        $skipped     = 0;
        $exists      = 0;
        $imported    = 0;

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
		}

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.6
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        $wp_users = Import::get_wp_users_by_lifterlms_with_limit_offset($params['selectedCourses'], $per_batch, $params['offset']);

        if ( !is_array( $wp_users['formatted_users'] ) && empty( $wp_users['formatted_users'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('No LifterLMS user available to import.', 'mrm')
            );
        }

        $wp_users = array_map(array($this, 'format_contact_data_from_wp_user'), $wp_users['formatted_users']);

        foreach ( $wp_users as $wp_user ) {
            $result = $this->process_individual_user_to_insert($wp_user, $params, 'LifterLMS');

            $skipped += $result['skipped'];
            $exists += $result['exists'];
            $imported += $result['imported'];
        }
        // Prepare data for success response.
        $result = array(
            'imported'          => $imported,
            'total'             => count( $wp_users ),
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'offset'            => $params['offset'] + (int) $per_batch,
        );

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }
}