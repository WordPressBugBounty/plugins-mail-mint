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
     * Summary: Fetches MailerLite groups and contact attributes.
     *
     * Description: Verifies the MailerLite API key, then returns the account's groups and the contact fields to map to.
     *
     * @access public
     *
     * @param string $key The MailerLite API key.
     * @return array Returns MailerLite groups, headers and contact attributes, or an error array with 'status' and 'message'.
     * @since 1.32.0
     */
    public function fetch_mailerlite_groups_and_contact_attributes( $key ){
        $groups = Import::get_mailerlite_groups( $key );

        if( isset( $groups['status'] ) && 401 === $groups['status'] ){
            return $groups;
        }

        // The total of every subscriber, for importing without picking a group.
        $response = Import::get_mailerlite_response( $key, 'subscribers', array( 'limit' => 0 ) );
        $total    = isset( $response['total'] ) ? (int) $response['total'] : 0;

        $contacts_attrs = Import::get_contact_general_fields();
        $contacts_attrs = apply_filters( 'mint_contacts_attrs', $contacts_attrs );

        return array(
            'mailerlite_groups' => $groups,
            'total_subscribers' => $total,
            'headers'           => array(),
            'fields'            => $contacts_attrs,
        );
    }

    /**
     * Summary: Retrieves MailerLite subscriber headers.
     * Description: Returns the subscriber columns (email plus every MailerLite field) and the batch plan for a group.
     *
     * @access public
     *
     * @param array $params The parameters including group ID ("all" for every subscriber), API key and member count.
     *
     * @return array Returns the total, batch count, batch size, headers and header labels, or an error array.
     * @since 1.32.0
     */
    public function get_mailerlite_member_headers( $params ){
        $group_id = isset( $params['group_id'] ) ? (string) $params['group_id'] : '';
        if( '' === $group_id ){
            return array();
        }

        $key          = isset( $params['key'] ) ? $params['key'] : '';
        $member_count = isset( $params['member_count'] ) ? (int) $params['member_count'] : 0;
        $batch_size   = 100;

        $response = Import::get_mailerlite_response( $key, 'fields', array( 'limit' => 100 ) );

        if( isset( $response['status'] ) && 401 === $response['status'] ){
            return $response;
        }

        $fields = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();

        $headers       = array( 'email' );
        $header_labels = array( 'email' => __( 'Email', 'mrm' ) );

        foreach ( $fields as $field ) {
            if ( empty( $field['key'] ) ) {
                continue;
            }
            $headers[]                      = $field['key'];
            $header_labels[ $field['key'] ] = isset( $field['name'] ) ? $field['name'] : $field['key'];
        }

        // MailerLite's "name" field holds the first name.
        if ( isset( $header_labels['name'] ) ) {
            $header_labels['name'] = __( 'First Name', 'mrm' );
        }

        return array (
            'total'        => $member_count,
            'total_batch'  => ceil( $member_count / $batch_size ),
            'per_batch'    => $batch_size,
            'headers'      => $headers,
            'headerLabels' => $header_labels,
        );
	}

    /**
     * Summary: Fetches Drip accounts, tags and contact attributes.
     *
     * Description: Verifies the Drip API token, then returns its accounts, the chosen account's tags,
     * its active subscriber total and the contact fields to map to.
     *
     * @access public
     *
     * @param string $token      The Drip API token.
     * @param string $account_id Optional. The account to read; the first account when empty or unknown.
     * @return array Returns Drip accounts, tags, totals and contact attributes, or an error array with 'status' and 'message'.
     * @since 1.32.0
     */
    public function fetch_drip_accounts_and_contact_attributes( $token, $account_id = '' ){
        $accounts = Import::get_drip_accounts( $token );

        if( isset( $accounts['status'] ) && 401 === $accounts['status'] ){
            return $accounts;
        }

        if( empty( $accounts ) ){
            return array(
                'status'  => 401,
                'message' => __( 'No Drip account was found for this API token.', 'mrm' ),
            );
        }

        $account_ids = array_column( $accounts, 'id' );
        if( ! in_array( (string) $account_id, $account_ids, true ) ){
            $account_id = $account_ids[0];
        }

        $response = Import::get_drip_response( $token, rawurlencode( $account_id ) . '/tags' );
        if( isset( $response['status'] ) && 401 === $response['status'] ){
            return $response;
        }
        $tags = isset( $response['tags'] ) && is_array( $response['tags'] ) ? array_values( array_filter( $response['tags'], 'is_string' ) ) : array();

        // The total of every active subscriber, for importing without picking a tag.
        $total = Import::get_drip_subscriber_total( $token, $account_id );
        if( is_array( $total ) ){
            return $total;
        }

        $contacts_attrs = Import::get_contact_general_fields();
        $contacts_attrs = apply_filters( 'mint_contacts_attrs', $contacts_attrs );

        return array(
            'drip_accounts'     => $accounts,
            'account_id'        => $account_id,
            'drip_tags'         => $tags,
            'total_subscribers' => $total,
            'headers'           => array(),
            'fields'            => $contacts_attrs,
        );
    }

    /**
     * Summary: Retrieves Drip subscriber headers.
     * Description: Returns the subscriber columns (standard Drip fields plus every custom field) and the batch plan
     * for an account, optionally narrowed to one tag.
     *
     * @access public
     *
     * @param array $params The parameters including API token, account ID and tag ("all" for every subscriber).
     *
     * @return array Returns the total, batch count, batch size, headers and header labels, or an error array.
     * @since 1.32.0
     */
    public function get_drip_member_headers( $params ){
        $token      = isset( $params['token'] ) ? $params['token'] : '';
        $account_id = isset( $params['account_id'] ) ? (string) $params['account_id'] : '';
        $tag        = isset( $params['tag'] ) && 'all' !== $params['tag'] ? (string) $params['tag'] : '';
        $batch_size = 100;

        if( '' === $account_id ){
            return array(
                'status'  => 401,
                'message' => __( 'Please select a Drip account to import.', 'mrm' ),
            );
        }

        $total = Import::get_drip_subscriber_total( $token, $account_id, $tag );
        if( is_array( $total ) ){
            return $total;
        }

        if( 0 === $total ){
            return array(
                'status'  => 401,
                'message' => '' === $tag
                    ? __( 'This Drip account has no active subscribers to import.', 'mrm' )
                    : __( 'No active subscribers have this tag.', 'mrm' ),
            );
        }

        $headers       = array( 'email', 'first_name', 'last_name', 'address1', 'address2', 'city', 'state', 'zip', 'country', 'phone' );
        $header_labels = array(
            'email'      => __( 'Email', 'mrm' ),
            'first_name' => __( 'First Name', 'mrm' ),
            'last_name'  => __( 'Last Name', 'mrm' ),
            'address1'   => __( 'Address Line 1', 'mrm' ),
            'address2'   => __( 'Address Line 2', 'mrm' ),
            'city'       => __( 'City', 'mrm' ),
            'state'      => __( 'State', 'mrm' ),
            'zip'        => __( 'Postal Code', 'mrm' ),
            'country'    => __( 'Country', 'mrm' ),
            'phone'      => __( 'Phone', 'mrm' ),
        );

        $response = Import::get_drip_response( $token, rawurlencode( $account_id ) . '/custom_field_identifiers' );
        if( isset( $response['status'] ) && 401 === $response['status'] ){
            return $response;
        }

        $identifiers = isset( $response['custom_field_identifiers'] ) && is_array( $response['custom_field_identifiers'] ) ? $response['custom_field_identifiers'] : array();
        foreach ( $identifiers as $identifier ) {
            if ( ! is_string( $identifier ) || '' === $identifier ) {
                continue;
            }
            // Custom fields live under "custom_fields" in Drip, so they get a prefix to never clash with the standard ones.
            $headers[]                                      = 'custom_fields.' . $identifier;
            $header_labels[ 'custom_fields.' . $identifier ] = $identifier;
        }

        return array (
            'total'        => $total,
            'total_batch'  => ceil( $total / $batch_size ),
            'per_batch'    => $batch_size,
            'headers'      => $headers,
            'headerLabels' => $header_labels,
        );
	}

    /**
     * Summary: Fetches ActiveCampaign lists and contact attributes.
     *
     * Description: Verifies the ActiveCampaign API URL and key, then returns the account's lists and the contact fields to map to.
     *
     * @access public
     *
     * @param string $api_url The ActiveCampaign API URL.
     * @param string $key     The ActiveCampaign API key.
     * @return array Returns ActiveCampaign lists and contact attributes, or an error array with 'status' and 'message'.
     * @since 1.32.0
     */
    public function fetch_activecampaign_lists_and_contact_attributes( $api_url, $key ){
        $lists = Import::get_activecampaign_lists( $api_url, $key );

        if( isset( $lists['status'] ) && 401 === $lists['status'] ){
            return $lists;
        }

        $contacts_attrs = Import::get_contact_general_fields();
        $contacts_attrs = apply_filters( 'mint_contacts_attrs', $contacts_attrs );

        return array(
            'activecampaign_lists' => $lists,
            'headers'              => array(),
            'fields'               => $contacts_attrs,
        );
    }

    /**
     * Summary: Retrieves ActiveCampaign contact headers.
     * Description: Returns the contact columns (standard fields plus every custom field) and the batch plan for a list.
     *
     * @access public
     *
     * @param array $params The parameters including API URL, API key and list ID.
     *
     * @return array Returns the total, batch count, batch size, headers and header labels, or an error array.
     * @since 1.32.0
     */
    public function get_activecampaign_member_headers( $params ){
        $api_url    = isset( $params['api_url'] ) ? $params['api_url'] : '';
        $key        = isset( $params['key'] ) ? $params['key'] : '';
        $list_id    = isset( $params['list_id'] ) ? (string) $params['list_id'] : '';
        $batch_size = 100;

        if( '' === $list_id ){
            return array(
                'status'  => 401,
                'message' => __( 'Please select an ActiveCampaign list to import.', 'mrm' ),
            );
        }

        $total = Import::get_activecampaign_contact_total( $api_url, $key, $list_id );
        if( is_array( $total ) ){
            return $total;
        }

        if( 0 === $total ){
            return array(
                'status'  => 401,
                'message' => __( 'This list has no active contacts to import.', 'mrm' ),
            );
        }

        $headers       = array( 'email', 'firstName', 'lastName', 'phone' );
        $header_labels = array(
            'email'     => __( 'Email', 'mrm' ),
            'firstName' => __( 'First Name', 'mrm' ),
            'lastName'  => __( 'Last Name', 'mrm' ),
            'phone'     => __( 'Phone', 'mrm' ),
        );

        $offset = 0;
        do {
            $response = Import::get_activecampaign_response( $api_url, $key, 'fields', array( 'limit' => 100, 'offset' => $offset ) );
            if( isset( $response['status'] ) && 401 === $response['status'] ){
                return $response;
            }

            $fields = isset( $response['fields'] ) && is_array( $response['fields'] ) ? $response['fields'] : array();
            foreach ( $fields as $field ) {
                if ( empty( $field['id'] ) ) {
                    continue;
                }
                // Custom field values are matched by field ID, so the column key carries it.
                $headers[]                                = 'field.' . $field['id'];
                $header_labels[ 'field.' . $field['id'] ] = isset( $field['title'] ) ? $field['title'] : (string) $field['id'];
            }

            $fields_total = isset( $response['meta']['total'] ) ? (int) $response['meta']['total'] : 0;
            $offset      += 100;
        } while ( $offset < $fields_total && ! empty( $fields ) );

        return array (
            'total'        => $total,
            'total_batch'  => ceil( $total / $batch_size ),
            'per_batch'    => $batch_size,
            'headers'      => $headers,
            'headerLabels' => $header_labels,
        );
	}

    /**
     * Summary: Fetches Kit tags and contact attributes.
     *
     * Description: Verifies the Kit v4 API key, then returns its tags, the active subscriber total and the contact fields to map to.
     *
     * @access public
     *
     * @param string $key The Kit v4 API key.
     * @return array Returns Kit tags, the total and contact attributes, or an error array with 'status' and 'message'.
     * @since 1.32.0
     */
    public function fetch_kit_tags_and_contact_attributes( $key ){
        $tags = Import::get_kit_tags( $key );

        if( isset( $tags['status'] ) && 401 === $tags['status'] ){
            return $tags;
        }

        // The total of every active subscriber, for importing without picking a tag.
        $total = Import::get_kit_subscriber_total( $key, 'all' );
        if( is_array( $total ) ){
            return $total;
        }

        $contacts_attrs = Import::get_contact_general_fields();
        $contacts_attrs = apply_filters( 'mint_contacts_attrs', $contacts_attrs );

        return array(
            'kit_tags'          => $tags,
            'total_subscribers' => $total,
            'headers'           => array(),
            'fields'            => $contacts_attrs,
        );
    }

    /**
     * Summary: Retrieves Kit subscriber headers.
     * Description: Returns the subscriber columns (email, first name and every custom field) and the batch plan
     * for every subscriber or one tag.
     *
     * @access public
     *
     * @param array $params The parameters including API key and tag ID ("all" for every subscriber).
     *
     * @return array Returns the total, batch count, batch size, headers and header labels, or an error array.
     * @since 1.32.0
     */
    public function get_kit_member_headers( $params ){
        $key        = isset( $params['key'] ) ? $params['key'] : '';
        $tag_id     = isset( $params['tag_id'] ) ? (string) $params['tag_id'] : '';
        $batch_size = 100;

        if( '' === $tag_id ){
            return array(
                'status'  => 401,
                'message' => __( 'Please select a Kit tag to import.', 'mrm' ),
            );
        }

        $total = Import::get_kit_subscriber_total( $key, $tag_id );
        if( is_array( $total ) ){
            return $total;
        }

        if( 0 === $total ){
            return array(
                'status'  => 401,
                'message' => 'all' === $tag_id
                    ? __( 'This Kit account has no active subscribers to import.', 'mrm' )
                    : __( 'No active subscribers have this tag.', 'mrm' ),
            );
        }

        $headers       = array( 'email_address', 'first_name' );
        $header_labels = array(
            'email_address' => __( 'Email', 'mrm' ),
            'first_name'    => __( 'First Name', 'mrm' ),
        );

        $after = '';
        do {
            $query = array( 'per_page' => 1000 );
            if ( '' !== $after ) {
                $query['after'] = $after;
            }

            $response = Import::get_kit_response( $key, 'custom_fields', $query );
            if( isset( $response['status'] ) && 401 === $response['status'] ){
                return $response;
            }

            $fields = isset( $response['custom_fields'] ) && is_array( $response['custom_fields'] ) ? $response['custom_fields'] : array();
            foreach ( $fields as $field ) {
                if ( empty( $field['key'] ) ) {
                    continue;
                }
                // Custom field values sit under "fields" on each subscriber, so the column key carries the prefix.
                $headers[]                                  = 'fields.' . $field['key'];
                $header_labels[ 'fields.' . $field['key'] ] = isset( $field['label'] ) ? $field['label'] : $field['key'];
            }

            $has_next = ! empty( $response['pagination']['has_next_page'] ) && ! empty( $response['pagination']['end_cursor'] );
            $after    = $has_next ? (string) $response['pagination']['end_cursor'] : '';
        } while ( $has_next && ! empty( $fields ) );

        return array (
            'total'        => $total,
            'total_batch'  => ceil( $total / $batch_size ),
            'per_batch'    => $batch_size,
            'headers'      => $headers,
            'headerLabels' => $header_labels,
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
        if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('Please upload a file first.', 'mrm')
            );
        }

        // Secure file validation
        $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        $allowed_exts = array( 'csv' );

        if ( empty( $filetype['ext'] ) || ! in_array( $filetype['ext'], $allowed_exts, true ) ) {
            return array(
                'status'  => 'failed',
                'message' => __('Invalid file type. Only CSV files are allowed.', 'mrm')
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

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, $import_type );
        }

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

		$contact_args = $this->remove_non_overwritable_fields( $contact_args, $params );

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
     * Drop mapped fields that should not overwrite an existing contact's data.
     *
     * A mapping opts out with `'overwrite' => false`; mappings without the flag
     * keep overwriting, so older import screens behave as before.
     *
     * @access private
     *
     * @param array $contact_args Prepared contact arguments.
     * @param array $params       Import request parameters containing the field map.
     * @return array Contact arguments without the non-overwritable fields.
     *
     * @since 1.31.5
     */
    private function remove_non_overwritable_fields( $contact_args, $params ) {
        return Import::remove_non_overwritable_fields( $contact_args, $params );
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

        $wp_roles    = wp_roles();
        $count_users = count_users();
        $avail_roles = isset( $count_users['avail_roles'] ) ? $count_users['avail_roles'] : array();

        foreach ( $wp_roles->get_names() as $key => $value ) {
            $formatted_roles[] = array(
                'role'       => $key,
                'name'       => $value,
                'user_count' => isset( $avail_roles[ $key ] ) ? (int) $avail_roles[ $key ] : 0,
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

        $wp_users = Import::get_wp_users_by_roles_with_limit_offset($params['roles'], $per_batch, $params['offset']);

        if (!is_array($wp_users) && empty($wp_users)) {
            return array(
                'status'  => 'failed',
                'message' => __('No WordPress user available to import.', 'mrm')
            );
        }

        foreach ($wp_users as $wp_user) {
            if (isset($wp_user['user_email']) && ! is_email($wp_user['user_email'])) {
                $skipped++;
                continue;
            }
            $result = $this->process_individual_wordpress_user_to_insert($wp_user, $params, $mappings, 'WordPress');

            $skipped  += $result['skipped'];
            $exists   += $result['exists'];
            $imported += $result['imported'];
        }
        // Prepare data for success response.
        $result = array(
            'imported'          => $imported,
            'total'             => count($wp_users),
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'offset'            => $params['offset'] + (int) $per_batch,
        );

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'WordPress' );
        }

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
     * @param string $source The contact source.
     *
     * @return array The result of the import process for this user, including skipped, existing, and imported counts.
     * @since 1.5.7
     * @since 1.8.0 Add contact source as param
     * @since 1.16.5 Add $mapping as param
     */
    public function process_individual_wordpress_user_to_insert($wp_user, $params, $mappings, $source){
        $skipped  = 0;
        $exists   = 0;
        $imported = 0;

        $contact_args = array(
            'wp_user_id'  => isset($wp_user['ID']) ? $wp_user['ID'] : null,
            'status'      => isset($params['status']) ? $params['status'] : 'pending',
            'source'      => $source,
            'meta_fields' => array(),
            'created_by'  => get_current_user_id(),
        );

        foreach ( $mappings as $map ) {
            $target = isset($map['target']) ? $map['target'] : '';
            $source = isset($map['source']) ? $map['source'] : '';

            if (in_array($target, array('first_name', 'last_name', 'email'), true)) {
                $contact_args[$target] = isset($wp_user[$source]) ? $wp_user[$source] : '';
            } elseif (in_array($target, array('lists', 'tags'), true)) {
                $contact_args['groups'][$target] = isset($wp_user[$source]) ? $wp_user[$source] : '';
            } else {
                $contact_args['meta_fields'][$target] = isset($wp_user[$source]) ? $wp_user[$source] : '';
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
         * Filter the LearnDash student columns offered on the Map Columns step.
         *
         * @param array $headers Keys of the formatted user data (user fields and user meta).
         * @return array
         *
         * @since 1.31.5
         */
        $headers = apply_filters( 'mint_learndash_user_import_headers', $this->get_lms_user_import_headers() );

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
                            'headers'          => $headers,
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
        $mappings = isset( $params['map'] ) && is_array( $params['map'] ) ? $params['map'] : array();

        foreach ( $wp_users as $wp_user ) {
            $result = $this->insert_lms_user( $wp_user, $params, $mappings, 'LearnDash' );

            if ( ! is_array( $result ) ) {
                return array(
                    'status'  => 'failed',
                    'message' => __( 'The email field is required.', 'mrm' ),
                );
            }

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

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'LearnDash' );
        }

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }

    /**
     * Default user columns offered on the Map Columns step for LMS and membership imports.
     *
     * They are keys of the formatted user data (see format_contact_data_from_wp_user()).
     *
     * @access private
     *
     * @return array
     * @since 1.31.5
     */
    private function get_lms_user_import_headers() {
        return array(
            'user_email',
            'first_name',
            'last_name',
            'user_login',
            'nickname',
            'user_registered',
            'id',
        );
    }

    /**
     * Insert or update one LMS student or membership member as a contact.
     *
     * The new import screen sends a field map, which is applied like a WordPress
     * user import (with per-field Overwrite); the legacy screens send none and keep
     * the fixed email/name import.
     *
     * @access private
     *
     * @param array  $wp_user  Formatted user data.
     * @param array  $params   Import request parameters.
     * @param array  $mappings Field map from the request, or an empty array.
     * @param string $source   Contact source label.
     * @return array|string Import counts, or 'failed' when no column maps to the email.
     * @since 1.31.5
     */
    private function insert_lms_user( $wp_user, $params, $mappings, $source ) {
        if ( empty( $mappings ) ) {
            return $this->process_individual_user_to_insert( $wp_user, $params, $source );
        }

        $wp_user['ID'] = isset( $wp_user['id'] ) ? $wp_user['id'] : null;
        return $this->process_individual_wordpress_user_to_insert( $wp_user, $params, $mappings, $source );
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
         * Filter the Tutor LMS student columns offered on the Map Columns step.
         *
         * @param array $headers Keys of the formatted user data (user fields and user meta).
         * @return array
         *
         * @since 1.31.5
         */
        $headers = apply_filters( 'mint_tutorlms_user_import_headers', $this->get_lms_user_import_headers() );

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
                            'headers'          => $headers,
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
        $mappings = isset( $params['map'] ) && is_array( $params['map'] ) ? $params['map'] : array();

        foreach ( $wp_users as $wp_user ) {
            $result = $this->insert_lms_user( $wp_user, $params, $mappings, 'Tutor LMS' );

            if ( ! is_array( $result ) ) {
                return array(
                    'status'  => 'failed',
                    'message' => __( 'The email field is required.', 'mrm' ),
                );
            }

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

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'Tutor LMS' );
        }

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
         * Filter the MemberPress member columns offered on the Map Columns step.
         *
         * @param array $headers Keys of the formatted user data (user fields and user meta).
         * @return array
         *
         * @since 1.31.5
         */
        $headers = apply_filters( 'mint_memberpress_user_import_headers', $this->get_lms_user_import_headers() );

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
                            'headers'         => $headers,
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
        $mappings = isset( $params['map'] ) && is_array( $params['map'] ) ? $params['map'] : array();

        foreach ( $wp_users as $wp_user ) {
            $result = $this->insert_lms_user( $wp_user, $params, $mappings, 'MemberPress' );

            if ( ! is_array( $result ) ) {
                return array(
                    'status'  => 'failed',
                    'message' => __( 'The email field is required.', 'mrm' ),
                );
            }

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

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'MemberPress' );
        }

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
         * Filter the LifterLMS student columns offered on the Map Columns step.
         *
         * @param array $headers Keys of the formatted user data (user fields and user meta).
         * @return array
         *
         * @since 1.31.5
         */
        $headers = apply_filters( 'mint_lifterlms_user_import_headers', $this->get_lms_user_import_headers() );

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
                            'headers'          => $headers,
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
        $mappings = isset( $params['map'] ) && is_array( $params['map'] ) ? $params['map'] : array();

        foreach ( $wp_users as $wp_user ) {
            $result = $this->insert_lms_user( $wp_user, $params, $mappings, 'LifterLMS' );

            if ( ! is_array( $result ) ) {
                return array(
                    'status'  => 'failed',
                    'message' => __( 'The email field is required.', 'mrm' ),
                );
            }

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

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'LifterLMS' );
        }

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Contact(s) has been successfully imported.', 'mrm')
        );
    }

    /**
     * Retrieve contacts associated with BuddyPress/BuddyBoss.
     *
     * Description: Retrieves contacts associated with the selected BuddyPress/BuddyBoss groups
     * or member types based on the provided parameters.
     *
     * @param array $params The parameters for retrieving contacts.
     * @return array An array containing the status, data, and message of the retrieval operation.
     * @access public
     * @since 1.20.0
     */
    public function retrieve_contacts_associated_with_buddypress( $params ) {
        $import_type = isset( $params['importType'] ) ? $params['importType'] : 'member_group';
        $selection   = isset( $params['selection'] ) ? $params['selection'] : array();

        $wp_users    = Import::get_wp_users_by_buddypress_with_limit_offset( $import_type, $selection, 5 );
        $contacts    = array_map( array( $this, 'format_contact_data_from_wp_user' ), $wp_users['formatted_users'] );
        $total_users = (int) $wp_users['total_users'];

        /**
         * Filter the BuddyPress member columns offered on the Map Columns step.
         *
         * @param array $headers Keys of the formatted user data (user fields and user meta).
         * @return array
         *
         * @since 1.31.5
         */
        $headers = apply_filters( 'mint_buddypress_user_import_headers', $this->get_lms_user_import_headers() );

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         *
         * @since 1.20.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        return array(
            'status'  => 'success',
            'data'    => array(
                            'contacts'    => $contacts,
                            'total_batch' => ceil( $total_users / (int) $per_batch ),
                            'total'       => $total_users,
                            'importType'  => $import_type,
                            'selection'   => $selection,
                            'headers'     => $headers,
                        ),
            'message' => __( 'Data retrieval and processing completed successfully.', 'mrm' ),
        );
    }

    /**
     * Perform BuddyPress/BuddyBoss user import.
     *
     * Description: Imports BuddyPress/BuddyBoss users based on the provided parameters.
     *
     * @param array $params The parameters for performing the import.
     * @return array An array containing the status, data, and message of the import operation.
     * @access public
     * @since 1.20.0
     */
    public function perform_buddypress_user_import( $params ) {
        $skipped  = 0;
        $exists   = 0;
        $imported = 0;

        if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
            add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
        }

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         *
         * @since 1.20.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        $import_type = isset( $params['importType'] ) ? $params['importType'] : 'member_group';
        $selection   = isset( $params['selection'] ) ? $params['selection'] : array();

        $wp_users = Import::get_wp_users_by_buddypress_with_limit_offset( $import_type, $selection, $per_batch, $params['offset'] );

        if ( ! is_array( $wp_users['formatted_users'] ) && empty( $wp_users['formatted_users'] ) ) {
            return array(
                'status'  => 'failed',
                'message' => __( 'No BuddyPress user available to import.', 'mrm' ),
            );
        }

        $wp_users = array_map( array( $this, 'format_contact_data_from_wp_user' ), $wp_users['formatted_users'] );
        $mappings = isset( $params['map'] ) && is_array( $params['map'] ) ? $params['map'] : array();

        foreach ( $wp_users as $wp_user ) {
            $result = $this->insert_lms_user( $wp_user, $params, $mappings, 'BuddyPress' );

            if ( ! is_array( $result ) ) {
                return array(
                    'status'  => 'failed',
                    'message' => __( 'The email field is required.', 'mrm' ),
                );
            }

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

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'BuddyPress' );
        }

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __( 'Contact(s) has been successfully imported.', 'mrm' ),
        );
    }

    /**
     * Perform Fluent Booking Import.
     * 
     * Description: This function handles the import of Fluent Booking users based on the provided parameters.
     *
     * @param array $params The import parameters.
     * @return void
     * 
     * @since 1.16.4
     */
    public function perform_fluent_booking_import( $params ){
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
        $per_batch = apply_filters( 'mint_import_batch_limit', 100 );

        $users = Import::get_fluent_booking_users($params['offset'], $per_batch);

        if (!is_array($users) && empty($users)) {
            return array(
                'status'  => 'failed',
                'message' => __('No user available to import.', 'mrm')
            );
        }

        foreach ($users as $user) {
            if (isset($user['email']) && ! is_email($user['email'])) {
                $skipped++;
                continue;
            }
            $result = $this->process_individual_wordpress_user_to_insert($user, $params, $mappings, 'Fluent Booking');

            $skipped  += $result['skipped'];
            $exists   += $result['exists'];
            $imported += $result['imported'];
        }
        // Prepare data for success response.
        $result = array(
            'imported'          => $imported,
            'total'             => count($users),
            'skipped'           => $skipped,
            'existing_contacts' => $exists,
            'offset'            => $params['offset'] + (int) $per_batch,
        );

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'Fluent Booking' );
        }

        return array(
            'status'  => 'success',
            'data'    => $result,
            'message' => __('Import contact has been successful.', 'mrm')
        );
    }

    /**
     * Perform MailPoet user import.
     * This function imports users from MailPoet into the system. It handles mapping fields,
     * checking for existing contacts, and updating or inserting new contacts as necessary.
     * 
     * @param array $params Parameters for the import process.
     * @return array Associative array containing the result of the import process.
     * 
     * @since 1.18.9
     */
    public function perform_mail_poet_user_import($params) {
        $skipped        = 0;
        $exists_counts  = 0;
        $imported       = 0;

        if (isset($params['automation_control']) &&  $params['automation_control']) {
            add_filter('mint_automation_trigger_control_on_import', '__return_true');
        }
        $mappings = $params['map'] ?? [];
        if (empty($mappings)) {
            return [
                'status'  => 'failed',
                'message' => __('Please map at least one field for importing.', 'mrm')
            ];
        }

        $per_batch = apply_filters('mint_import_batch_limit', 200);
        
        $wp_users_subscriber   = Import::get_mail_poet_contacts_with_limit_offset($per_batch, $params['offset']);
        $mail_poet_subscribers = $wp_users_subscriber['formatted_subscribers'] ?? [];
        if (empty($mail_poet_subscribers)) {
            return [
                'status'  => 'failed',
                'message' => __('No MailPoet user available to import.', 'mrm')
            ];
        }

        foreach ($mail_poet_subscribers as $wp_user) {
            if (isset($wp_user['email']) && !is_email($wp_user['email'])) {
                $skipped++;
                continue;
            }

            $contact_args = [
                'status'      => $params['status'] ?? 'pending',
                'source'      => 'MailPoet',
                'meta_fields' => [],
                'created_by'  => $params['created_by'] ?? '',
            ];

            foreach ($mappings as $map) {
                $target = $map['target'] ?? '';
                $source = $map['source'] ?? '';

                if (in_array($target, ['first_name', 'last_name', 'email', 'status'], true)) {
                    if ($target === 'status') {
                        $status_map = [
                            'subscribed'   => 'subscribed',
                            'unsubscribed' => 'unsubscribed',
                            'inactive'     => 'pending',
                            'unconfirmed'  => 'pending',
                            'bounced'      => 'bounced',
                        ];
                        $contact_args[$target] = $status_map[$wp_user[$source]] ?? 'pending';
                    } else {
                        $contact_args[$target] = $wp_user[$source] ?? '';
                    }
                } else {
                    $contact_args['meta_fields'][$target] = $wp_user[$source] ?? '';
                }
            }

            if (empty($contact_args['email'])) {
                return [
                    'status'  => 'failed',
                    'message' => __('The email field is required.', 'mrm')
                ];
            }

            $contact_email = trim($contact_args['email']);
            $exists = ContactModel::is_contact_exist($contact_email);
            if (!$exists) {
                $contact = new ContactData($contact_email, $contact_args);
                $contact_id = ContactModel::insert($contact);

                if ($contact_args['status'] === 'pending') {
                    MessageController::get_instance()->send_double_opt_in($contact_id);
                }

                if (!empty($params['tags'])) {
                    ContactGroupModel::set_tags_to_contact($params['tags'], $contact_id);
                }

                if (!empty($params['lists'])) {
                    ContactGroupModel::set_lists_to_contact($params['lists'], $contact_id);
                }

                $imported++;
            } else {
                if (!empty($params['skip_existing'])) {
                    $skipped++;
                    continue;
                }
                $contact_id = ContactModel::get_id_by_email($contact_email);
                ContactModel::update(Import::remove_non_overwritable_fields($contact_args, $params), $contact_id);
                if ($contact_args['status'] === 'pending' && !empty($params['optin_confirmation'])) {
                    MessageController::get_instance()->send_double_opt_in($contact_id);
                }

                if (!empty($params['tags'])) {
                    ContactGroupModel::set_tags_to_contact($params['tags'], $contact_id);
                }

                if (!empty($params['lists'])) {
                    ContactGroupModel::set_lists_to_contact($params['lists'], $contact_id);
                }

                $exists_counts++;
            }
        }

        if ( $imported > 0 ) {
            do_action( 'mailmint_contacts_imported', $imported, 'MailPoet' );
        }

        return [
            'code'    => 200,
            'status'  => 'success',
            'data'    => [
                'imported'          => $imported,
                'total'             => count($mail_poet_subscribers),
                'skipped'           => $skipped,
                'existing_contacts' => $exists_counts,
                'offset'            => $params['offset'] + $per_batch,
            ],
            'message' => __('Contact(s) import has been successful.', 'mrm')
        ];
    }
}