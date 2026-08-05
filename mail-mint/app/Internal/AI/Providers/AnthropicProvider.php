<?php
/**
 * AnthropicProvider — Claude via the Anthropic Messages API.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Providers;

defined( 'ABSPATH' ) || exit;

class AnthropicProvider extends AbstractProvider {

    private const API_URL     = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function slug(): string {
        return 'anthropic';
    }

    public function chat( string $system, array $messages, array $tools ) {
        $body = [
            'model'      => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'thinking'   => [ 'type' => 'adaptive' ],
            // Stable system prompt first with a cache breakpoint — history grows
            // after it, so repeated loop steps read the prefix from cache.
            'system'     => [
                [
                    'type'          => 'text',
                    'text'          => $system,
                    'cache_control' => [ 'type' => 'ephemeral' ],
                ],
            ],
            'messages'   => $this->buildMessages( $messages ),
        ];

        if ( ! empty( $tools ) ) {
            $body['tools'] = array_map(
                static function ( $tool ) {
                    return [
                        // Tool names must match ^[a-zA-Z0-9_-]{1,64}$ — no slashes.
                        'name'         => self::wireName( $tool['name'] ),
                        'description'  => $tool['description'],
                        'input_schema' => $tool['input_schema'],
                    ];
                },
                $tools
            );
        }

        $response = $this->postJson(
            self::API_URL,
            [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => self::API_VERSION,
            ],
            $body
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $text       = '';
        $tool_calls = [];
        foreach ( (array) ( $response['content'] ?? [] ) as $block ) {
            if ( 'text' === ( $block['type'] ?? '' ) ) {
                $text .= $block['text'] ?? '';
            } elseif ( 'tool_use' === ( $block['type'] ?? '' ) ) {
                $tool_calls[] = [
                    'id'        => $block['id'] ?? uniqid( 'toolu_' ),
                    'name'      => self::abilityName( (string) ( $block['name'] ?? '' ) ),
                    'arguments' => is_array( $block['input'] ?? null ) ? $block['input'] : [],
                ];
            }
        }

        $stop_map = [ 'tool_use' => 'tool_use', 'max_tokens' => 'max_tokens' ];

        return [
            'text'        => $text,
            'tool_calls'  => $tool_calls,
            'stop_reason' => $stop_map[ $response['stop_reason'] ?? '' ] ?? 'end_turn',
            // Full native content (incl. thinking blocks) — must be replayed
            // verbatim on the next request of a tool-use loop.
            'raw'         => $response['content'] ?? [],
            'usage'       => [
                'input_tokens'  => (int) ( $response['usage']['input_tokens'] ?? 0 ),
                'output_tokens' => (int) ( $response['usage']['output_tokens'] ?? 0 ),
            ],
        ];
    }

    public function validateKey() {
        $result = $this->postJson(
            self::API_URL,
            [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => self::API_VERSION,
            ],
            [
                'model'      => $this->model,
                'max_tokens' => 1,
                'messages'   => [ [ 'role' => 'user', 'content' => 'ping' ] ],
            ]
        );
        return is_wp_error( $result ) ? $result : true;
    }

    // -----------------------------------------------------------------------
    // History conversion
    // -----------------------------------------------------------------------

    private function buildMessages( array $messages ): array {
        $out = [];

        foreach ( $messages as $message ) {
            $role    = $message['role'] ?? '';
            $content = self::content( $message );

            if ( 'user' === $role ) {
                $out[] = [ 'role' => 'user', 'content' => (string) ( $content['text'] ?? '' ) ];
                continue;
            }

            if ( 'assistant' === $role ) {
                $raw = $this->rawFor( $message );
                if ( is_array( $raw ) && ! empty( $raw ) ) {
                    $out[] = [ 'role' => 'assistant', 'content' => $raw ];
                    continue;
                }
                // Reconstructed fallback (history produced by another provider).
                $blocks = [];
                if ( '' !== (string) ( $content['text'] ?? '' ) ) {
                    $blocks[] = [ 'type' => 'text', 'text' => (string) $content['text'] ];
                }
                foreach ( (array) ( $content['tool_calls'] ?? [] ) as $call ) {
                    $blocks[] = [
                        'type'  => 'tool_use',
                        'id'    => $call['id'],
                        'name'  => self::wireName( $call['name'] ),
                        'input' => is_array( $call['arguments'] ?? null ) ? $call['arguments'] : new \stdClass(),
                    ];
                }
                if ( ! empty( $blocks ) ) {
                    $out[] = [ 'role' => 'assistant', 'content' => $blocks ];
                }
                continue;
            }

            if ( 'tool' === $role ) {
                $result_block = [
                    'type'        => 'tool_result',
                    'tool_use_id' => (string) ( $content['tool_call_id'] ?? '' ),
                    'content'     => (string) ( $content['content'] ?? '' ),
                ];
                if ( ! empty( $content['is_error'] ) ) {
                    $result_block['is_error'] = true;
                }
                // Consecutive tool results merge into one user turn.
                $last = count( $out ) - 1;
                if ( $last >= 0 && 'user' === $out[ $last ]['role'] && is_array( $out[ $last ]['content'] )
                    && 'tool_result' === ( $out[ $last ]['content'][0]['type'] ?? '' ) ) {
                    $out[ $last ]['content'][] = $result_block;
                } else {
                    $out[] = [ 'role' => 'user', 'content' => [ $result_block ] ];
                }
            }
        }

        return $out;
    }

    /**
     * Anthropic tool names must match ^[a-zA-Z0-9_-]{1,64}$ — slashes in
     * ability names ('mail-mint/list-contacts') are not allowed on the wire.
     */
    private static function wireName( string $ability_name ): string {
        return str_replace( '/', '__', $ability_name );
    }

    private static function abilityName( string $wire_name ): string {
        return str_replace( '__', '/', $wire_name );
    }
}
