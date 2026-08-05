<?php
/**
 * MCP Form Tools
 *
 * Provides form-related tools:
 *   - list-forms, get-form, list-form-submissions (read)
 *   - create-form, update-form (write)
 *
 * Callers never author raw block markup. create-form/update-form accept a simple
 * structured field spec and FormBlockBuilder generates the form_body markup
 * server-side, keeping the JSON block attributes and rendered HTML in sync.
 * Fine-grained styling and layout stay in the visual form builder.
 *
 * @package Mint\MRM\Internal\MCP\Tools
 */

namespace Mint\MRM\Internal\MCP\Tools;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\FormBuilder\FormBlockBuilder;
use Mint\MRM\DataBase\Models\FormModel;
use Mint\MRM\DataBase\Models\FormSubmissionModel;
use Mint\MRM\Database\Repositories\ContactGroupRepository;
use Mint\MRM\DataStores\FormData;
use Mint\MRM\Internal\MCP\Helpers\MCPHelper;
use Mint\MRM\Utilities\Helper\PermissionManager;

class FormTools {

    public static function definitions(): array {
        return [

            'mail-mint/list-forms' => [
                'label'       => __( 'List Forms', 'mail-mint' ),
                'description' => 'List opt-in forms with status and the tags/lists they assign to subscribers. Supports search, status filtering, and pagination.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'    => [ 'type' => 'string' ],
                        'status'    => [ 'type' => 'string', 'enum' => [ 'all', 'published', 'draft' ], 'default' => 'all' ],
                        'sort_by'   => [ 'type' => 'string', 'enum' => [ 'id', 'title', 'created_at', 'status' ], 'default' => 'id' ],
                        'sort_type' => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
                        'page'      => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page'  => [ 'type' => 'integer', 'default' => 10 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listForms' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_forms' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/get-form' => [
                'label'       => __( 'Get Form', 'mail-mint' ),
                'description' => 'Get one form: status, assigned tags/lists, display settings, and a manifest of its input fields (parsed from the form markup). Raw form markup is not returned.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'form_id' ],
                    'properties' => [
                        'form_id' => [ 'type' => 'integer' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'getForm' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_forms' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/list-form-submissions' => [
                'label'       => __( 'List Form Submissions', 'mail-mint' ),
                'description' => 'List submissions (entries) of a form, including every submitted field value, UTM attribution, and read status.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'form_id' ],
                    'properties' => [
                        'form_id'   => [ 'type' => 'integer' ],
                        'search'    => [ 'type' => 'string', 'description' => 'Matches submitted field values.' ],
                        'status'    => [ 'type' => 'string', 'enum' => [ 'read', 'unread', 'trashed' ], 'description' => 'Filter by read status.' ],
                        'page'      => [ 'type' => 'integer', 'default' => 1 ],
                        'per_page'  => [ 'type' => 'integer', 'default' => 20 ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'listFormSubmissions' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_read_forms' ),
                'annotations'         => [ 'readonly' ],
            ],

            'mail-mint/create-form' => [
                'label'       => __( 'Create Form', 'mail-mint' ),
                'description' => 'Create an opt-in form from a simple field spec. An email field is always included. Provide list_ids/tag_ids to assign subscribers. Block markup is generated server-side; refine styling later in the visual form builder. Publishing requires at least one list or tag.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'title' ],
                    'properties' => [
                        'title'  => [ 'type' => 'string', 'description' => 'Form name (max 150 characters).' ],
                        'fields' => [
                            'type'        => 'array',
                            'description' => 'Fields in display order. An email field is auto-added when omitted. first_name/last_name capture contact name; text/textarea capture custom values keyed by slug.',
                            'items'       => [
                                'type'       => 'object',
                                'required'   => [ 'type' ],
                                'properties' => [
                                    'type'        => [ 'type' => 'string', 'enum' => [ 'email', 'first_name', 'last_name', 'text', 'textarea' ] ],
                                    'label'       => [ 'type' => 'string' ],
                                    'placeholder' => [ 'type' => 'string' ],
                                    'required'    => [ 'type' => 'boolean', 'default' => false, 'description' => 'Email is always required regardless of this flag.' ],
                                    'slug'        => [ 'type' => 'string', 'description' => 'Submission key for text/textarea fields; derived from label when omitted.' ],
                                ],
                            ],
                        ],
                        'button_text'     => [ 'type' => 'string', 'default' => 'Subscribe' ],
                        'heading'         => [ 'type' => 'string', 'description' => 'Optional heading shown above the fields.' ],
                        'description'     => [ 'type' => 'string', 'description' => 'Optional paragraph shown below the heading.' ],
                        'status'          => [ 'type' => 'string', 'enum' => [ 'draft', 'published' ], 'default' => 'draft' ],
                        'list_ids'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Contact list IDs assigned on submit.' ],
                        'tag_ids'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Tag IDs assigned on submit.' ],
                        'success_message' => [ 'type' => 'string', 'description' => 'Message shown after a successful submission.' ],
                        'idempotency_key' => [ 'type' => 'string', 'description' => 'Pass a stable key to make retries safe (no duplicate forms).' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'createForm' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_forms' ),
                'annotations'         => [],
            ],

            'mail-mint/update-form' => [
                'label'       => __( 'Update Form', 'mail-mint' ),
                'description' => 'Update an existing form. Title, status, and assigned lists/tags update in place. Providing "fields" regenerates the form body (replacing all fields); omit it to leave the existing layout untouched. Publishing requires at least one list or tag.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'form_id' ],
                    'properties' => [
                        'form_id'         => [ 'type' => 'integer' ],
                        'title'           => [ 'type' => 'string', 'description' => 'New form name (max 150 characters).' ],
                        'status'          => [ 'type' => 'string', 'enum' => [ 'draft', 'published' ] ],
                        'fields'          => [
                            'type'        => 'array',
                            'description' => 'When provided, replaces the entire form body. Same shape as create-form fields. Omit to keep the current layout.',
                            'items'       => [
                                'type'       => 'object',
                                'required'   => [ 'type' ],
                                'properties' => [
                                    'type'        => [ 'type' => 'string', 'enum' => [ 'email', 'first_name', 'last_name', 'text', 'textarea' ] ],
                                    'label'       => [ 'type' => 'string' ],
                                    'placeholder' => [ 'type' => 'string' ],
                                    'required'    => [ 'type' => 'boolean', 'default' => false ],
                                    'slug'        => [ 'type' => 'string' ],
                                ],
                            ],
                        ],
                        'button_text'     => [ 'type' => 'string', 'description' => 'Used only when regenerating the body via "fields".' ],
                        'heading'         => [ 'type' => 'string', 'description' => 'Used only when regenerating the body via "fields".' ],
                        'description'     => [ 'type' => 'string', 'description' => 'Used only when regenerating the body via "fields".' ],
                        'list_ids'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'When provided, replaces the assigned lists.' ],
                        'tag_ids'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'When provided, replaces the assigned tags.' ],
                        'success_message' => [ 'type' => 'string', 'description' => 'When provided, updates the post-submission message.' ],
                    ],
                ],
                'execute_callback'    => [ self::class, 'updateForm' ],
                'permission_callback' => PermissionManager::current_user_can( 'mint_manage_forms' ),
                'annotations'         => [],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Handlers
    // -----------------------------------------------------------------------

    public static function listForms( array $params ): array|\WP_Error {
        $paging  = MCPHelper::paginationFromInput( $params );
        $search  = sanitize_text_field( $params['search'] ?? '' );
        $status  = sanitize_key( $params['status'] ?? 'all' );
        $sort_by = sanitize_key( $params['sort_by'] ?? 'id' );
        $sort    = strtoupper( $params['sort_type'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

        // FormModel::get_all expects status as array-ish filter; 'all' means no filter.
        $status_filter = in_array( $status, [ 'published', 'draft' ], true ) ? [ $status ] : [];

        $response = FormModel::get_all( $sort_by, $sort, $status_filter, $paging['offset'], $paging['per_page'], $search );

        $rows  = is_array( $response ) ? ( $response['data'] ?? [] ) : [];
        $total = is_array( $response ) ? (int) ( $response['count'] ?? count( $rows ) ) : 0;

        return [
            'forms'       => array_map( [ self::class, 'formatForm' ], $rows ),
            'total'       => $total,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => is_array( $response ) ? (int) ( $response['total_pages'] ?? 1 ) : 1,
        ];
    }

    public static function getForm( array $params ): array|\WP_Error {
        $form_id = (int) ( $params['form_id'] ?? 0 );
        if ( ! $form_id ) {
            return MCPHelper::error( 'missing_param', 'form_id is required.' );
        }

        $form = FormModel::get( $form_id );
        if ( empty( $form ) ) {
            return MCPHelper::error( 'not_found', 'Form not found.' );
        }
        $form = is_object( $form ) ? (array) $form : $form;

        $formatted           = self::formatForm( $form );
        $formatted['fields'] = self::extractFieldManifest( (string) ( $form['form_body'] ?? '' ) );

        $position = maybe_unserialize( $form['form_position'] ?? '' );
        if ( is_array( $position ) ) {
            $formatted['display_settings'] = $position;
        }

        return $formatted;
    }

    public static function listFormSubmissions( array $params ): array|\WP_Error {
        $form_id = (int) ( $params['form_id'] ?? 0 );
        if ( ! $form_id ) {
            return MCPHelper::error( 'missing_param', 'form_id is required.' );
        }
        if ( ! FormModel::is_form_exist( $form_id ) ) {
            return MCPHelper::error( 'not_found', 'Form not found.' );
        }

        $paging = MCPHelper::paginationFromInput( $params );
        $search = sanitize_text_field( $params['search'] ?? '' );
        $status = sanitize_key( $params['status'] ?? '' );

        $response = FormSubmissionModel::get_form_entries(
            $form_id,
            $paging['page'],
            $paging['per_page'],
            'created_at',
            'DESC',
            $search,
            in_array( $status, [ 'read', 'unread', 'trashed' ], true ) ? $status : ''
        );

        $rows = is_array( $response ) ? ( $response['data'] ?? [] ) : [];

        $submissions = array_map(
            static function ( $row ) {
                $row        = is_object( $row ) ? (array) $row : $row;
                $submission = isset( $row['fields'] ) ? $row : [ 'fields' => [] ] + $row;
                return [
                    'id'         => (int) ( $row['id'] ?? 0 ),
                    'contact_id' => isset( $row['contact_id'] ) ? (int) $row['contact_id'] : null,
                    'status'     => $row['status'] ?? '',
                    'source_url' => $row['source_url'] ?? '',
                    'fields'     => is_array( $submission['fields'] ) ? $submission['fields'] : [],
                    'utm'        => array_filter( [
                        'source'   => $row['utm_source'] ?? null,
                        'medium'   => $row['utm_medium'] ?? null,
                        'campaign' => $row['utm_campaign'] ?? null,
                        'term'     => $row['utm_term'] ?? null,
                        'content'  => $row['utm_content'] ?? null,
                    ] ),
                    'created_at' => $row['created_at'] ?? '',
                ];
            },
            $rows
        );

        return [
            'submissions' => $submissions,
            'total'       => is_array( $response ) ? (int) ( $response['count'] ?? count( $submissions ) ) : 0,
            'page'        => $paging['page'],
            'per_page'    => $paging['per_page'],
            'total_pages' => is_array( $response ) ? (int) ( $response['total_pages'] ?? 1 ) : 1,
        ];
    }

    public static function createForm( array $params ): array|\WP_Error {
        if ( ! PermissionManager::current_user_can( 'mint_manage_forms' )() ) {
            return MCPHelper::error( 'permission_denied', 'You do not have permission to create forms.' );
        }

        $cached = MCPHelper::idempotentHit( 'mail-mint/create-form', $params );
        if ( null !== $cached ) {
            return $cached;
        }

        $title = sanitize_text_field( $params['title'] ?? '' );
        if ( '' === $title ) {
            return MCPHelper::error( 'missing_param', 'title is required.' );
        }
        if ( mb_strlen( $title ) > 150 ) {
            return MCPHelper::error( 'invalid_param', 'title may not exceed 150 characters.' );
        }

        $status = in_array( $params['status'] ?? 'draft', [ 'draft', 'published' ], true ) ? $params['status'] : 'draft';
        $groups = self::buildGroupIds( $params['list_ids'] ?? [], $params['tag_ids'] ?? [] );

        if ( 'published' === $status && empty( $groups['lists'] ) && empty( $groups['tags'] ) ) {
            return MCPHelper::error( 'invalid_param', 'Publishing a form requires at least one list or tag.' );
        }

        $built = FormBlockBuilder::build( [
            'fields'      => $params['fields'] ?? [],
            'button_text' => $params['button_text'] ?? '',
            'heading'     => $params['heading'] ?? '',
            'description' => $params['description'] ?? '',
        ] );

        $message = isset( $params['success_message'] ) && '' !== trim( (string) $params['success_message'] )
            ? sanitize_text_field( $params['success_message'] )
            : __( 'Form submitted successfully.', 'mrm' );

        $form = new FormData( [
            'title'         => $title,
            'form_body'     => $built['form_body'],
            // Mirror FormController: form_position is stored serialized.
            'form_position' => serialize( self::defaultFormPosition() ),
            'status'        => $status,
            'group_ids'     => $groups,
            'meta_fields'   => [ 'settings' => self::buildSettings( $message ) ],
        ] );

        $form_id = FormModel::insert( $form );
        if ( ! $form_id ) {
            return MCPHelper::error( 'insert_failed', 'Failed to create form.' );
        }

        do_action( 'mailmint_first_form_created', $form_id );

        $result = [
            'result'  => 'created',
            'form_id' => (int) $form_id,
            'status'  => $status,
            'fields'  => self::manifestFromFields( $built['fields'] ),
        ];

        MCPHelper::idempotentStore( 'mail-mint/create-form', $params, $result );

        return $result;
    }

    public static function updateForm( array $params ): array|\WP_Error {
        if ( ! PermissionManager::current_user_can( 'mint_manage_forms' )() ) {
            return MCPHelper::error( 'permission_denied', 'You do not have permission to update forms.' );
        }

        $form_id = (int) ( $params['form_id'] ?? 0 );
        if ( ! $form_id ) {
            return MCPHelper::error( 'missing_param', 'form_id is required.' );
        }

        $existing = FormModel::get( $form_id );
        if ( empty( $existing ) ) {
            return MCPHelper::error( 'not_found', 'Form not found.' );
        }

        // Title — keep existing unless overridden.
        $title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : ( $existing['title'] ?? '' );
        if ( '' === $title ) {
            return MCPHelper::error( 'invalid_param', 'title may not be empty.' );
        }
        if ( mb_strlen( $title ) > 150 ) {
            return MCPHelper::error( 'invalid_param', 'title may not exceed 150 characters.' );
        }

        // Status — keep existing unless a valid override is supplied.
        $status = isset( $params['status'] ) && in_array( $params['status'], [ 'draft', 'published' ], true )
            ? $params['status']
            : ( $existing['status'] ?? 'draft' );

        // Groups — rebuild only when list_ids/tag_ids supplied, else preserve.
        $override_groups = isset( $params['list_ids'] ) || isset( $params['tag_ids'] );
        if ( $override_groups ) {
            $groups       = self::buildGroupIds( $params['list_ids'] ?? [], $params['tag_ids'] ?? [] );
            $has_segments = ! empty( $groups['lists'] ) || ! empty( $groups['tags'] );
        } else {
            // Preserve the stored JSON string as-is (FormData re-emits valid JSON).
            $groups       = $existing['group_ids'] ?? '';
            $decoded      = is_string( $groups ) ? json_decode( $groups, true ) : ( is_array( $groups ) ? $groups : [] );
            $has_segments = is_array( $decoded ) && ( ! empty( $decoded['lists'] ) || ! empty( $decoded['tags'] ) );
        }

        if ( 'published' === $status && ! $has_segments ) {
            return MCPHelper::error( 'invalid_param', 'Publishing a form requires at least one list or tag.' );
        }

        // Body — regenerate only when fields supplied, else preserve.
        $regenerated   = false;
        $field_manifest = [];
        if ( isset( $params['fields'] ) && is_array( $params['fields'] ) && ! empty( $params['fields'] ) ) {
            $built          = FormBlockBuilder::build( [
                'fields'      => $params['fields'],
                'button_text' => $params['button_text'] ?? '',
                'heading'     => $params['heading'] ?? '',
                'description' => $params['description'] ?? '',
            ] );
            $form_body      = $built['form_body'];
            $field_manifest = self::manifestFromFields( $built['fields'] );
            $regenerated    = true;
        } else {
            $form_body = $existing['form_body'] ?? '';
        }

        // Settings/meta — only touch when a new success message is supplied.
        // FormModel::get() nests the settings JSON under meta_fields['settings'].
        $meta_fields = [];
        if ( isset( $params['success_message'] ) && '' !== trim( (string) $params['success_message'] ) ) {
            $current_settings = $existing['meta_fields']['settings'] ?? '';
            $meta_fields      = [ 'settings' => self::withSuccessMessage( $current_settings, sanitize_text_field( $params['success_message'] ) ) ];
        }

        $form = new FormData( [
            'title'         => $title,
            'form_body'     => $form_body,
            // Stored value is already serialized; pass through unchanged.
            'form_position' => $existing['form_position'] ?? '',
            'status'        => $status,
            'group_ids'     => $groups,
            'meta_fields'   => $meta_fields,
        ] );

        $success = FormModel::update( $form, $form_id );
        if ( ! $success ) {
            return MCPHelper::error( 'update_failed', 'Failed to update form.' );
        }

        $result = [
            'result'           => 'updated',
            'form_id'          => $form_id,
            'status'           => $status,
            'body_regenerated' => $regenerated,
        ];
        if ( $regenerated ) {
            $result['fields'] = $field_manifest;
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve list/tag IDs into the {tags,lists} structure stored in group_ids,
     * attaching titles via the repository (no raw $wpdb in the tool layer).
     * Unknown IDs are silently dropped.
     */
    private static function buildGroupIds( $list_ids, $tag_ids ): array {
        return [
            'tags'  => self::resolveGroups( 'tags', $tag_ids ),
            'lists' => self::resolveGroups( 'lists', $list_ids ),
        ];
    }

    /**
     * Map a set of group IDs of one type to [{id,title}], dropping unknown IDs.
     */
    private static function resolveGroups( string $type, $ids ): array {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
        if ( empty( $ids ) ) {
            return [];
        }

        $repo  = new ContactGroupRepository( $type );
        $title_map = [];
        foreach ( $repo->allForDropdown() as $row ) {
            $row = is_object( $row ) ? (array) $row : $row;
            $title_map[ (int) ( $row['id'] ?? 0 ) ] = $row['title'] ?? '';
        }

        $resolved = [];
        foreach ( $ids as $id ) {
            if ( isset( $title_map[ $id ] ) ) {
                $resolved[] = [ 'id' => $id, 'title' => $title_map[ $id ] ];
            }
        }
        return $resolved;
    }

    /**
     * Default form display rules for a programmatically created form.
     */
    private static function defaultFormPosition(): array {
        return [
            'pages'             => [ 'all' => false, 'selected' => [], 'homepage' => false ],
            'post'              => [ 'all' => false, 'selected' => [] ],
            'product'           => [ 'all' => false, 'selected' => [] ],
            'categories'        => [],
            'tags'              => [],
            'category_archives' => [ 'all' => false, 'selected' => [] ],
        ];
    }

    /**
     * Build the default form settings JSON (stored in the form meta 'settings' key).
     */
    private static function buildSettings( string $message ): string {
        $now  = current_time( 'mysql' );
        $date = substr( $now, 0, 10 );
        $time = substr( $now, 11, 8 );

        return wp_json_encode( [
            'settings' => [
                'confirmation_type' => [
                    'selected_confirmation_type' => 'same-page',
                    'same_page'                  => [
                        'message_to_show'       => $message,
                        'after_form_submission' => 'none',
                    ],
                ],
                'form_layout'       => [
                    'form_position'          => 'default',
                    'form_animation'         => 'none',
                    'close_button_color'     => '#000',
                    'close_background_color' => '#fff',
                ],
                'schedule'          => [
                    'form_scheduling'  => false,
                    'submission_start' => [ 'date' => $date, 'time' => $time ],
                ],
                'restriction'       => [ 'max_entries' => false, 'max_number' => 0, 'max_type' => '' ],
                'extras'            => [ 'cookies_timer' => 7, 'show_always' => true ],
            ],
        ] );
    }

    /**
     * Return the existing settings JSON with only its success message replaced,
     * falling back to a fresh default when the stored value is missing/invalid.
     */
    private static function withSuccessMessage( $existing_settings, string $message ): string {
        $decoded = is_string( $existing_settings ) ? json_decode( $existing_settings, true ) : null;
        if ( ! is_array( $decoded ) || ! isset( $decoded['settings'] ) ) {
            return self::buildSettings( $message );
        }
        $decoded['settings']['confirmation_type']['same_page']['message_to_show'] = $message;
        return wp_json_encode( $decoded );
    }

    /**
     * Reduce builder field specs to a compact manifest for the tool response.
     */
    private static function manifestFromFields( array $fields ): array {
        return array_map(
            static function ( $f ) {
                return [
                    'type'     => $f['type'],
                    'slug'     => $f['slug'],
                    'label'    => $f['label'],
                    'required' => (bool) $f['required'],
                ];
            },
            $fields
        );
    }

    private static function formatForm( $form ): array {
        $form = is_object( $form ) ? (array) $form : $form;

        $groups = json_decode( (string) ( $form['group_ids'] ?? '' ), true );
        if ( ! is_array( $groups ) ) {
            $groups = maybe_unserialize( $form['group_ids'] ?? '' );
        }

        return [
            'id'         => (int) ( $form['id'] ?? 0 ),
            'title'      => $form['title'] ?? '',
            'status'     => $form['status'] ?? '',
            'groups'     => is_array( $groups ) ? $groups : [],
            'created_at' => $form['created_at'] ?? '',
            'updated_at' => $form['updated_at'] ?? '',
        ];
    }

    /**
     * Parse the form's block markup into a compact field manifest.
     */
    private static function extractFieldManifest( string $form_body ): array {
        if ( '' === $form_body || ! function_exists( 'parse_blocks' ) ) {
            return [];
        }

        $fields = [];
        $walk   = static function ( array $blocks ) use ( &$walk, &$fields ): void {
            foreach ( $blocks as $block ) {
                $name = (string) ( $block['blockName'] ?? '' );
                if ( str_starts_with( $name, 'mrmformfield/' ) ) {
                    $attrs    = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
                    $fields[] = array_filter( [
                        'block' => substr( $name, strlen( 'mrmformfield/' ) ),
                        'name'  => $attrs['field_name'] ?? ( $attrs['inputLabel'] ?? null ),
                        'slug'  => $attrs['field_slug'] ?? null,
                    ] );
                }
                if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                    $walk( $block['innerBlocks'] );
                }
            }
        };
        $walk( parse_blocks( $form_body ) );

        return $fields;
    }
}
