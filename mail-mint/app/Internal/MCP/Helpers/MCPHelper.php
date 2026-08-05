<?php
/**
 * Shared MCP helpers — formatters, pagination, error factory, query builder.
 *
 * @package Mint\MRM\Internal\MCP\Helpers
 */

namespace Mint\MRM\Internal\MCP\Helpers;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\DataBase\Models\ContactModel;
use MRM\Common\MrmCommon;

class MCPHelper {

    /**
     * Build standard pagination params from raw input.
     *
     * @param array $params
     * @return array{page:int,per_page:int,offset:int}
     */
    public static function paginationFromInput( array $params ): array {
        $page     = max( 1, (int) ( $params['page'] ?? 1 ) );
        $per_page = min( 200, max( 1, (int) ( $params['per_page'] ?? 10 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        return compact( 'page', 'per_page', 'offset' );
    }

    /**
     * Consistent WP_Error factory for MCP tool failures.
     *
     * @param string $code
     * @param string $message
     * @param array  $data
     * @return \WP_Error
     */
    public static function error( string $code, string $message, array $data = [] ): \WP_Error {
        return new \WP_Error( 'mcp_' . $code, $message, $data );
    }

    /**
     * Format a single contact for MCP output.
     *
     * @param object|array $contact
     * @param array        $options  Keys: include_notes, include_email_history, include_custom_fields
     * @return array
     */
    public static function formatContactForMCP( $contact, array $options = [] ): array {
        if ( is_object( $contact ) ) {
            $contact = (array) $contact;
        }

        $base = [
            'id'           => (int) ( $contact['id'] ?? 0 ),
            'email'        => $contact['email'] ?? '',
            'first_name'   => $contact['first_name'] ?? '',
            'last_name'    => $contact['last_name'] ?? '',
            'status'       => $contact['status'] ?? '',
            // DB column is 'stage'; exposed as 'contact_type' to match the input schema.
            'contact_type' => $contact['contact_type'] ?? $contact['stage'] ?? '',
            'source'       => $contact['source'] ?? '',
            'created_at'   => $contact['created_at'] ?? '',
            'updated_at'   => $contact['updated_at'] ?? '',
            'tags'         => self::extractGroups( $contact, 'tags' ),
            'lists'        => self::extractGroups( $contact, 'lists' ),
        ];

        if ( ! empty( $options['include_custom_fields'] ) ) {
            if ( array_key_exists( 'prefetched_meta', $options ) ) {
                // Caller already batch-fetched meta for this contact (avoids N+1 in lists).
                $meta_result = $options['prefetched_meta'];
            } else {
                // get_meta returns ['meta_fields' => ['key' => 'value', ...]]
                $meta_result = ContactModel::get_meta( $base['id'] );
            }
            $base['custom_fields'] = is_array( $meta_result ) ? ( $meta_result['meta_fields'] ?? [] ) : [];
        }

        if ( ! empty( $options['include_notes'] ) ) {
            $base['notes'] = self::formatNotes( $base['id'] );
        }

        return $base;
    }

    /**
     * Format a paginated contact list result into a standard MCP response shape.
     *
     * @param array $result  Expected keys: data (array of contacts), total, current_page, per_page, last_page
     * @param bool  $includeCustomFields
     * @return array
     */
    public static function formatContactList( array $result, bool $includeCustomFields = false ): array {
        $rows = $result['data'] ?? [];

        // Batch-fetch custom-field meta for the whole page in ONE query (avoids N+1).
        $meta_map = [];
        if ( $includeCustomFields && ! empty( $rows ) ) {
            $ids = array_filter( array_map(
                static function ( $c ) {
                    $c = is_object( $c ) ? (array) $c : $c;
                    return (int) ( $c['id'] ?? 0 );
                },
                $rows
            ) );
            if ( ! empty( $ids ) ) {
                $meta_map = ContactModel::get_meta_for_contacts( $ids );
            }
        }

        $contacts = array_map(
            function ( $c ) use ( $includeCustomFields, $meta_map ) {
                $c_arr   = is_object( $c ) ? (array) $c : $c;
                $cid     = (int) ( $c_arr['id'] ?? 0 );
                $options = [ 'include_custom_fields' => $includeCustomFields ];
                if ( $includeCustomFields ) {
                    $options['prefetched_meta'] = $meta_map[ $cid ] ?? [ 'meta_fields' => [] ];
                }
                return self::formatContactForMCP( $c, $options );
            },
            $rows
        );

        return [
            'contacts'    => $contacts,
            'total'       => (int) ( $result['total'] ?? count( $contacts ) ),
            'page'        => (int) ( $result['current_page'] ?? 1 ),
            'per_page'    => (int) ( $result['per_page'] ?? count( $contacts ) ),
            'total_pages' => (int) ( $result['last_page'] ?? 1 ),
        ];
    }

    /**
     * Validate and normalise the universal contact filter object.
     *
     * @param array $params
     * @return true|\WP_Error
     */
    public static function validateUniversalFilter( array $params ) {
        if ( isset( $params['contact_ids'] ) && isset( $params['filter'] ) ) {
            return self::error( 'invalid_filter', 'Provide either contact_ids or filter, not both.' );
        }
        if ( isset( $params['contact_ids'] ) ) {
            if ( ! is_array( $params['contact_ids'] ) ) {
                return self::error( 'invalid_filter', 'contact_ids must be an array.' );
            }
        }
        return true;
    }

    /**
     * Idempotency guard for write tools.
     *
     * If $params contains a non-empty 'idempotency_key', returns the previously stored
     * result for that key (per tool, per user) when one exists — so a retried call does
     * NOT perform the action twice (the root cause of the duplicate-create class of bugs).
     * Returns null when there is no prior result and the caller should proceed.
     *
     * @param string $tool   Tool name (namespacing the key).
     * @param array  $params Tool params (may contain 'idempotency_key').
     * @return array|null Cached result to short-circuit with, or null to proceed.
     */
    public static function idempotentHit( string $tool, array $params ) {
        $key = isset( $params['idempotency_key'] ) ? sanitize_text_field( (string) $params['idempotency_key'] ) : '';
        if ( '' === $key ) {
            return null;
        }
        $cached = get_transient( self::idempotencyTransientKey( $tool, $key ) );
        return is_array( $cached ) ? $cached : null;
    }

    /**
     * Store a write tool's result against its idempotency_key (no-op without a key).
     *
     * @param string $tool   Tool name.
     * @param array  $params Tool params (may contain 'idempotency_key').
     * @param array  $result The result to remember.
     * @param int    $ttl    Seconds to retain (default 24h).
     */
    public static function idempotentStore( string $tool, array $params, array $result, int $ttl = DAY_IN_SECONDS ): void {
        $key = isset( $params['idempotency_key'] ) ? sanitize_text_field( (string) $params['idempotency_key'] ) : '';
        if ( '' === $key ) {
            return;
        }
        set_transient( self::idempotencyTransientKey( $tool, $key ), $result, $ttl );
    }

    /**
     * Build the per-tool, per-user transient key for an idempotency key.
     */
    private static function idempotencyTransientKey( string $tool, string $key ): string {
        $user = (int) get_current_user_id();
        return 'mm_mcp_idem_' . md5( $tool . '|' . $user . '|' . $key );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private static function extractGroups( array $contact, string $type ): array {
        $key  = $type; // 'tags' or 'lists'
        $data = $contact[ $key ] ?? [];

        if ( is_string( $data ) ) {
            $data = json_decode( $data, true ) ?? [];
        }

        return array_map(
            function ( $g ) {
                if ( is_object( $g ) ) {
                    $g = (array) $g;
                }
                return [
                    'id'    => (int) ( $g['id'] ?? 0 ),
                    'title' => $g['title'] ?? $g['name'] ?? '',
                    'slug'  => $g['slug'] ?? '',
                ];
            },
            (array) $data
        );
    }

    private static function formatNotes( int $contact_id ): array {
        try {
            $notes = \Mint\MRM\DataBase\Models\NoteModel::get_notes_to_contact( $contact_id );
            if ( empty( $notes ) || ! is_array( $notes ) ) {
                return [];
            }
            return array_map( function ( $n ) {
                if ( is_object( $n ) ) {
                    $n = (array) $n;
                }
                return [
                    'id'          => (int) ( $n['id'] ?? 0 ),
                    'type'        => $n['type'] ?? 'note',
                    'description' => $n['description'] ?? '',
                    'created_by'  => (int) ( $n['created_by'] ?? 0 ),
                    'created_at'  => $n['created_at'] ?? '',
                ];
            }, $notes );
        } catch ( \Throwable $e ) {
            return [];
        }
    }
}
