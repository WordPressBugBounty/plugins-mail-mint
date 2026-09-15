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
        $response = $this->postJson(
            self::API_BASE . '/chat/completions',
            [ 'Authorization' => 'Bearer ' . $this->api_key ],
            $this->buildRequestBody( $system, $messages, $tools )
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

    /**
     * Real streaming via the Chat Completions `stream: true` SSE wire
     * format: a sequence of `data: {...}` frames, each carrying a
     * `choices[0].delta` with an INCREMENTAL text fragment (`delta.content`)
     * and/or incremental tool-call fragments (`delta.tool_calls[].function.
     * arguments` is a partial JSON string that must be concatenated per
     * `index`, since a single tool call's arguments are split across many
     * frames), terminated by a literal `data: [DONE]` frame. With
     * `stream_options.include_usage` the final accounting also arrives, in a
     * frame whose `choices` array is empty and whose top-level `usage` is set.
     */
    public function chatStream( string $system, array $messages, array $tools, callable $on_delta ) {
        if ( ! function_exists( 'curl_init' ) ) {
            return parent::chatStream( $system, $messages, $tools, $on_delta );
        }

        $body                    = $this->buildRequestBody( $system, $messages, $tools );
        $body['stream']          = true;
        $body['stream_options']  = [ 'include_usage' => true ];

        $sse_buffer = '';
        $content    = '';
        $calls      = []; // index => [ id, name, arguments ] — arguments accumulate as a JSON string.
        $finish     = 'stop';
        $usage      = [ 'input_tokens' => 0, 'output_tokens' => 0 ];

        $on_chunk = function ( string $data ) use ( &$sse_buffer, &$content, &$calls, &$finish, &$usage, $on_delta ) {
            foreach ( self::extractSseDataLines( $sse_buffer, $data ) as $payload ) {
                if ( '' === $payload || '[DONE]' === $payload ) {
                    continue;
                }
                $decoded = json_decode( $payload, true );
                if ( ! is_array( $decoded ) ) {
                    continue; // Malformed/split frame — skip, keep the stream alive.
                }

                $choice = $decoded['choices'][0] ?? null;
                if ( is_array( $choice ) ) {
                    $delta = (array) ( $choice['delta'] ?? [] );
                    if ( isset( $delta['content'] ) && '' !== (string) $delta['content'] ) {
                        $content .= (string) $delta['content'];
                        $on_delta( (string) $delta['content'] );
                    }
                    foreach ( (array) ( $delta['tool_calls'] ?? [] ) as $tc ) {
                        $index = (int) ( $tc['index'] ?? 0 );
                        if ( ! isset( $calls[ $index ] ) ) {
                            $calls[ $index ] = [
                                'id'        => '',
                                'name'      => '',
                                'arguments' => '',
                            ];
                        }
                        if ( ! empty( $tc['id'] ) ) {
                            $calls[ $index ]['id'] = (string) $tc['id'];
                        }
                        if ( isset( $tc['function']['name'] ) ) {
                            $calls[ $index ]['name'] .= (string) $tc['function']['name'];
                        }
                        if ( isset( $tc['function']['arguments'] ) ) {
                            $calls[ $index ]['arguments'] .= (string) $tc['function']['arguments'];
                        }
                    }
                    if ( ! empty( $choice['finish_reason'] ) ) {
                        $finish = (string) $choice['finish_reason'];
                    }
                }
                if ( isset( $decoded['usage'] ) && is_array( $decoded['usage'] ) ) {
                    $usage = [
                        'input_tokens'  => (int) ( $decoded['usage']['prompt_tokens'] ?? 0 ),
                        'output_tokens' => (int) ( $decoded['usage']['completion_tokens'] ?? 0 ),
                    ];
                }
            }
        };

        $result = $this->postJsonStreamed( self::API_BASE . '/chat/completions', [ 'Authorization' => 'Bearer ' . $this->api_key ], $body, $on_chunk );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( $result['code'] < 200 || $result['code'] >= 300 ) {
            return $this->decodeStatusAndBody( $result['code'], $result['body'] );
        }

        $tool_calls = [];
        foreach ( $calls as $tc ) {
            $arguments    = json_decode( '' !== $tc['arguments'] ? $tc['arguments'] : '{}', true );
            $tool_calls[] = [
                'id'        => '' !== $tc['id'] ? $tc['id'] : uniqid( 'call_' ),
                'name'      => self::abilityName( $tc['name'] ),
                'arguments' => is_array( $arguments ) ? $arguments : [],
            ];
        }

        $stop_map    = [ 'tool_calls' => 'tool_use', 'length' => 'max_tokens' ];
        $stop_reason = $stop_map[ $finish ] ?? 'end_turn';

        // Reassemble the same OpenAI "message" object shape chat() stores in
        // `raw` (role/content/tool_calls, with wire-format name + JSON-string
        // arguments) so buildMessages() replays a streamed turn exactly like
        // a non-streamed one on the next request.
        $raw_message = [
            'role'    => 'assistant',
            'content' => '' !== $content ? $content : null,
        ];
        if ( ! empty( $calls ) ) {
            $raw_message['tool_calls'] = array_values(
                array_map(
                    static function ( $tc ) {
                        return [
                            'id'       => '' !== $tc['id'] ? $tc['id'] : uniqid( 'call_' ),
                            'type'     => 'function',
                            'function' => [
                                'name'      => $tc['name'],
                                'arguments' => '' !== $tc['arguments'] ? $tc['arguments'] : '{}',
                            ],
                        ];
                    },
                    $calls
                )
            );
        }

        return [
            'text'        => $content,
            'tool_calls'  => $tool_calls,
            'stop_reason' => $stop_reason,
            'raw'         => $raw_message,
            'usage'       => $usage,
        ];
    }

    /**
     * Shared request body for both chat() and chatStream() — streaming just
     * adds `stream`/`stream_options` on top of this.
     */
    private function buildRequestBody( string $system, array $messages, array $tools ): array {
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

        return $body;
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
