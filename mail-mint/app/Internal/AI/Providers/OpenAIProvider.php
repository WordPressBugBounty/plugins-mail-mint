<?php
/**
 * OpenAIProvider — GPT via the OpenAI Chat Completions API.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Providers;

defined( 'ABSPATH' ) || exit;

class OpenAIProvider extends AbstractProvider {

    private const API_BASE = 'https://api.openai.com/v1';

    public function slug(): string {
        return 'openai';
    }

    public function chat( string $system, array $messages, array $tools ) {
        $body = [
            'model'                 => $this->model,
            'max_completion_tokens' => self::MAX_TOKENS,
            'messages'              => array_merge(
                [ [ 'role' => 'system', 'content' => $system ] ],
                $this->buildMessages( $messages )
            ),
        ];

        if ( ! empty( $tools ) ) {
            $body['tools'] = array_map(
                static function ( $tool ) {
                    return [
                        'type'     => 'function',
                        'function' => [
                            'name'        => self::wireName( $tool['name'] ),
                            'description' => $tool['description'],
                            'parameters'  => $tool['input_schema'],
                        ],
                    ];
                },
                $tools
            );
            $body['tool_choice'] = 'auto';
        }

        $response = $this->postJson(
            self::API_BASE . '/chat/completions',
            [ 'Authorization' => 'Bearer ' . $this->api_key ],
            $body
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $choice  = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $tool_calls = [];
        foreach ( (array) ( $message['tool_calls'] ?? [] ) as $call ) {
            $arguments = json_decode( (string) ( $call['function']['arguments'] ?? '{}' ), true );
            $tool_calls[] = [
                'id'        => $call['id'] ?? uniqid( 'call_' ),
                'name'      => self::abilityName( (string) ( $call['function']['name'] ?? '' ) ),
                'arguments' => is_array( $arguments ) ? $arguments : [],
            ];
        }

        $finish   = $choice['finish_reason'] ?? 'stop';
        $stop_map = [ 'tool_calls' => 'tool_use', 'length' => 'max_tokens' ];

        return [
            'text'        => (string) ( $message['content'] ?? '' ),
            'tool_calls'  => $tool_calls,
            'stop_reason' => $stop_map[ $finish ] ?? 'end_turn',
            'raw'         => $message,
            'usage'       => [
                'input_tokens'  => (int) ( $response['usage']['prompt_tokens'] ?? 0 ),
                'output_tokens' => (int) ( $response['usage']['completion_tokens'] ?? 0 ),
            ],
        ];
    }

    public function validateKey() {
        $result = $this->getJson( self::API_BASE . '/models', [ 'Authorization' => 'Bearer ' . $this->api_key ] );
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
                    $out[] = array_merge( [ 'role' => 'assistant' ], $raw );
                    continue;
                }
                $assistant = [ 'role' => 'assistant', 'content' => (string) ( $content['text'] ?? '' ) ];
                $calls     = [];
                foreach ( (array) ( $content['tool_calls'] ?? [] ) as $call ) {
                    $calls[] = [
                        'id'       => $call['id'],
                        'type'     => 'function',
                        'function' => [
                            'name'      => self::wireName( $call['name'] ),
                            'arguments' => wp_json_encode( is_array( $call['arguments'] ?? null ) ? $call['arguments'] : [] ),
                        ],
                    ];
                }
                if ( ! empty( $calls ) ) {
                    $assistant['tool_calls'] = $calls;
                }
                $out[] = $assistant;
                continue;
            }

            if ( 'tool' === $role ) {
                $out[] = [
                    'role'         => 'tool',
                    'tool_call_id' => (string) ( $content['tool_call_id'] ?? '' ),
                    'content'      => (string) ( $content['content'] ?? '' ),
                ];
            }
        }

        return $out;
    }

    /**
     * OpenAI function names must match ^[a-zA-Z0-9_-]+$ — slashes in ability
     * names ('mail-mint/list-contacts') are not allowed.
     */
    private static function wireName( string $ability_name ): string {
        return str_replace( '/', '__', $ability_name );
    }

    private static function abilityName( string $wire_name ): string {
        return str_replace( '__', '/', $wire_name );
    }
}
