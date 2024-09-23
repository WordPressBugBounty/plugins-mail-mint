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
use Mint\MRM\Constants;

/**
 * Mail Mint
 *
 * The GeneralFieldActions class implements the Action interface and is responsible for retrieving
 * all contact primary fields from the mint_contact_primary_fields option.
 *
 * @package Mint\MRM\API\Actions\Admin
 * @since 1.5.0
 */
class GeneralFieldActions implements Action {

    /**
     * Retrieves all general fields.
     *
     * Retrieves all general fields from the primary contact fields.
     *
     * @param array $params Additional parameters.
     * @return array All retrieved general fields.
     * @since 1.5.0
     */
    public function get_all( $params ){
        $fields = get_option( 'mint_contact_primary_fields', Constants::$primary_contact_fields );
        return array_merge( ...array_values( $fields ) );
    }

    /**
     * Retrieves a single field based on the given slug.
     *
     * Retrieves a single field from the primary contact fields based on the provided slug.
     *
     * @param array $params The parameters for retrieving the field.
     *     - 'slug' (string) Optional. The slug of the field to retrieve. Default empty.
     * @return mixed|null The retrieved field, or null if no matching field is found.
     * @since 1.5.0
     */
	public function get_single( $params ) {
        $slug   = isset( $params['slug'] ) ? $params['slug'] : '';
		$fields = get_option( 'mint_contact_primary_fields', Constants::$primary_contact_fields );
        $fields = array_merge(...array_values($fields));
        // Define the filter callback function.
        $filter_callback = function ( $field ) use ( $slug ) {
            return $field['slug'] === $slug;
        };

        // Apply the filter and retrieve the matching field.
        $matching_fields = array_values(array_filter($fields, $filter_callback));

        // Return the first matching field, or null if no matching field is found.
        return isset($matching_fields[0]) ? $matching_fields[0] : array();
	}

    /**
     * Creates or updates a field based on the given parameters.
     *
     * Creates or updates a field in the primary contact fields based on the provided parameters.
     *
     * @param array $params The parameters for creating or updating the field.
     *     - 'slug' (string) Optional. The slug of the field to create or update. Default empty.
     * @return bool Whether the operation to create or update the field was successful.
     * @since 1.5.0
     */
	public function create_or_update( $params ) {
        $slug   = isset( $params['slug'] ) ? $params['slug'] : '';
        unset( $params['_locale'] );
        $fields = get_option( 'mint_contact_primary_fields', Constants::$primary_contact_fields );

        foreach ($fields as $key => &$subArray) {
            foreach ($subArray as $index => &$element) {
                if ($element['slug'] === $slug) {
                    $subArray[$index] = $params;
                    // Terminate both loops when a match is found
                    break 2;
                }
            }
        }
        
        return update_option( 'mint_contact_primary_fields', $fields );
	}
}