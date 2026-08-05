<?php
/**
 * MCP Custom Field Tools
 *
 * Custom field CRUD (create/update/delete/reorder/get) moved from Pro to Free
 * on 2026-07-28 (app/API/Controllers/Admin/CustomFieldController.php). This
 * tool follows the same move: it replaces Pro's mail-mint/manage-custom-field
 * ability, which is now removed from mail-mint-pro.
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Constants;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\CustomFieldModel;
use Mint\MRM\DataStores\CustomFieldData;
use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;

class CustomFieldTools {

    // Matches the <option value="..."> set in AddCustomFieldModal.jsx — NOT
    // the slug-cased names ("dropdown", "radio-button", ...) used elsewhere
    // in the codebase's docs; those are never written to the DB.
    private const FIELD_TYPES = [ 'text', 'textArea', 'number', 'selectField', 'radioField', 'checkboxField', 'date', 'date_time' ];

    private const OPTIONS_TYPES = [ 'selectField', 'radioField', 'checkboxField' ];

    public static function definitions(): array {
        return [
            'mail-mint/manage-custom-field' => [
                'label'       => __( 'Manage Custom Field', 'mail-mint' ),
                'description' => 'Create, update, or delete a custom contact field definition. Field values are then read/written per contact via the contact tools using the field slug. meta.options is required for selectField, radioField, and checkboxField types.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'action' ],
                    'properties' => [
                        'action'   => [ 'type' => 'string', 'enum' => [ 'create', 'update', 'delete' ] ],
                        'field_id' => [ 'type' => 'integer', 'description' => 'Required for update and delete.' ],
                        'title'    => [ 'type' => 'string', 'description' => 'Required for create.' ],
                        'slug'     => [ 'type' => 'string', 'description' => 'Optional; generated from title when omitted.' ],
                        'type'     => [ 'type' => 'string', 'enum' => self::FIELD_TYPES ],
                        'meta'     => [
                            'type'       => 'object',
                            'properties' => [
                                'options'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                'placeholder' => [ 'type' => 'string' ],
                                'label'       => [ 'type' => 'string' ],
                            ],
                        ],
                        'confirm' => [ 'type' => 'boolean', 'description' => 'Hard-stop safety gate: must be true to perform this destructive action (delete). Omit or false to get a confirmation-required preview instead.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'manageCustomField' ],
                'permission_callback' => PermissionManager::current_user_can_any( [ 'mint_manage_settings', 'mint_manage_forms' ] ),
                'annotations'         => [ 'destructive' ],
            ],
        ];
    }

    public static function manageCustomField( array $params ): array|\WP_Error {
        $action   = sanitize_key( $params['action'] ?? '' );
        $field_id = (int) ( $params['field_id'] ?? 0 );

        if ( 'delete' === $action ) {
            if ( ! $field_id ) {
                return MCPHelper::error( 'missing_param', 'field_id is required for delete.' );
            }
            $field = CustomFieldModel::get( $field_id );
            if ( ! $field ) {
                return MCPHelper::error( 'not_found', 'Custom field not found.' );
            }
            CustomFieldModel::destroy( $field_id );
            if ( ! empty( $field->slug ) ) {
                ContactModel::delete_meta_by_key( $field->slug );
            }
            do_action( 'mailmint_custom_field_deleted', $field_id );
            return [ 'result' => 'deleted', 'field_id' => $field_id ];
        }

        if ( ! in_array( $action, [ 'create', 'update' ], true ) ) {
            return MCPHelper::error( 'invalid_action', 'action must be create, update, or delete.' );
        }

        $title = sanitize_text_field( $params['title'] ?? '' );
        $type  = sanitize_text_field( $params['type'] ?? '' );
        $slug  = sanitize_title( $params['slug'] ?? $title );

        if ( 'create' === $action && ( '' === $title || '' === $type ) ) {
            return MCPHelper::error( 'missing_param', 'title and type are required for create.' );
        }
        if ( 'update' === $action && ! $field_id ) {
            return MCPHelper::error( 'missing_param', 'field_id is required for update.' );
        }
        if ( '' !== $type && ! in_array( $type, self::FIELD_TYPES, true ) ) {
            return MCPHelper::error( 'invalid_state', 'Unknown field type: ' . $type );
        }
        if ( in_array( $type, self::OPTIONS_TYPES, true ) && empty( $params['meta']['options'] ) ) {
            return MCPHelper::error( 'missing_param', 'meta.options is required for ' . $type . ' fields.' );
        }

        if ( in_array( $slug, Constants::$primary_fields, true ) ) {
            return MCPHelper::error( 'invalid_state', 'Slug collides with a primary contact field.' );
        }

        $exists_id = CustomFieldModel::is_field_exist( $slug )
            ? (int) CustomFieldModel::get_id_by_slug( $slug )
            : 0;
        if ( 'create' === $action && $exists_id ) {
            return MCPHelper::error( 'already_exists', sprintf( 'Slug "%s" is already used by field #%d.', $slug, $exists_id ) );
        }
        if ( 'update' === $action && $exists_id && $exists_id !== $field_id ) {
            return MCPHelper::error( 'already_exists', sprintf( 'Slug "%s" is already used by field #%d.', $slug, $exists_id ) );
        }

        $meta = [ 'label' => sanitize_text_field( $params['meta']['label'] ?? $title ) ];
        if ( ! empty( $params['meta']['options'] ) && is_array( $params['meta']['options'] ) ) {
            $meta['options'] = array_values( array_filter( array_map( 'sanitize_text_field', $params['meta']['options'] ) ) );
        }
        if ( ! empty( $params['meta']['placeholder'] ) ) {
            $meta['placeholder'] = sanitize_text_field( $params['meta']['placeholder'] );
        }

        $args = [
            'title' => $title,
            'slug'  => $slug,
            'type'  => $type,
            'meta'  => $meta,
        ];

        if ( 'update' === $action ) {
            if ( ! CustomFieldModel::get( $field_id ) ) {
                return MCPHelper::error( 'not_found', 'Custom field not found.' );
            }
            CustomFieldModel::update( new CustomFieldData( $args ), $field_id );
            do_action( 'mailmint_custom_field_updated', $field_id );
            return [ 'result' => 'updated', 'field_id' => $field_id, 'slug' => $slug ];
        }

        $args['position'] = CustomFieldModel::get_next_position();
        $new_id           = CustomFieldModel::insert( new CustomFieldData( $args ) );
        if ( ! $new_id ) {
            return MCPHelper::error( 'insert_failed', 'Failed to create the custom field.' );
        }
        do_action( 'mailmint_custom_field_added', (int) $new_id );
        return [ 'result' => 'created', 'field_id' => (int) $new_id, 'slug' => $slug ];
    }
}
