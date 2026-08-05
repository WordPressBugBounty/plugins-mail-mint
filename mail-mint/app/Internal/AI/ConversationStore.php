<?php
/**
 * ConversationStore — persistence for AI copilot conversations and messages.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\DataBase\Tables\AIConversationSchema;
use Mint\MRM\DataBase\Tables\AIMessageSchema;

class ConversationStore {

    /**
     * Idempotently create the AI tables if missing (covers the window before
     * the async migration runs, and self-heals multisite subsites).
     */
    public static function ensureTables(): void {
        if ( 'yes' === get_option( '_mrm_ai_tables_ready' ) ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . AIConversationSchema::$table_name;
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            dbDelta( ( new AIConversationSchema() )->get_sql() . $charset );
            dbDelta( ( new AIMessageSchema() )->get_sql() . $charset );
        }
        update_option( '_mrm_ai_tables_ready', 'yes', false );
    }

    public static function createConversation( int $user_id, string $provider, string $context_type, int $context_id, string $title ): int {
        self::ensureTables();
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . AIConversationSchema::$table_name,
            [
                'user_id'      => $user_id,
                'title'        => mb_substr( $title, 0, 250 ),
                'provider'     => $provider,
                'context_type' => $context_type,
                'context_id'   => $context_id ?: null,
                'status'       => 'idle',
                'created_at'   => current_time( 'mysql' ),
                'updated_at'   => current_time( 'mysql' ),
            ]
        );
        return (int) $wpdb->insert_id;
    }

    public static function getConversation( int $conversation_id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . AIConversationSchema::$table_name;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $conversation_id ), ARRAY_A );
        if ( ! $row ) {
            return null;
        }
        $row['pending'] = ! empty( $row['pending'] ) ? json_decode( $row['pending'], true ) : null;
        return $row;
    }

    public static function listConversations( int $user_id, string $context_type = '', int $context_id = 0, int $limit = 50, int $offset = 0 ): array {
        self::ensureTables();
        global $wpdb;
        $table = $wpdb->prefix . AIConversationSchema::$table_name;

        $where = 'user_id = %d';
        $args  = [ $user_id ];
        if ( '' !== $context_type ) {
            $where .= ' AND context_type = %s';
            $args[] = $context_type;
        }
        if ( $context_id > 0 ) {
            $where .= ' AND context_id = %d';
            $args[] = $context_id;
        }

        $limit  = max( 1, $limit );
        $offset = max( 0, $offset );
        $args[] = $limit;
        $args[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT id, title, provider, context_type, context_id, status, created_at, updated_at FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $args ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : [];
    }

    public static function updateConversation( int $conversation_id, array $fields ): void {
        global $wpdb;
        if ( array_key_exists( 'pending', $fields ) && null !== $fields['pending'] ) {
            $fields['pending'] = wp_json_encode( $fields['pending'] );
        }
        $fields['updated_at'] = current_time( 'mysql' );
        $wpdb->update( $wpdb->prefix . AIConversationSchema::$table_name, $fields, [ 'id' => $conversation_id ] );
    }

    /**
     * Delete a conversation and its messages (no FK cascade defined).
     */
    public static function deleteConversation( int $conversation_id ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . AIMessageSchema::$table_name, [ 'conversation_id' => $conversation_id ] );
        $wpdb->delete( $wpdb->prefix . AIConversationSchema::$table_name, [ 'id' => $conversation_id ] );
    }

    public static function appendMessage( int $conversation_id, string $role, array $content, array $meta = [] ): int {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . AIMessageSchema::$table_name,
            [
                'conversation_id' => $conversation_id,
                'role'            => $role,
                'content'         => wp_json_encode( $content ),
                'meta'            => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
                'created_at'      => current_time( 'mysql' ),
            ]
        );
        return (int) $wpdb->insert_id;
    }

    /**
     * All messages of a conversation in order. Content/meta stay JSON strings;
     * provider adapters decode lazily.
     */
    public static function getMessages( int $conversation_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . AIMessageSchema::$table_name;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare( "SELECT id, role, content, meta, created_at FROM {$table} WHERE conversation_id = %d ORDER BY id ASC", $conversation_id ),
            ARRAY_A
        );
        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Number of assistant messages since the most recent user message —
     * the loop-iteration counter for the current turn.
     */
    public static function assistantStepsThisTurn( array $messages ): int {
        $steps = 0;
        for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
            if ( 'user' === $messages[ $i ]['role'] ) {
                break;
            }
            if ( 'assistant' === $messages[ $i ]['role'] ) {
                $steps++;
            }
        }
        return $steps;
    }
}
