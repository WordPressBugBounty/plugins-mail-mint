<?php
/**
 * GeminiProvider — Gemini via the Google Generative Language API.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Providers;

defined( 'ABSPATH' ) || exit;

class GeminiProvider extends AbstractProvider {

    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function slug(): string {
        return 'gemini';
    }

    public function chat( string $system, array $messages, array $tools ) {
        $response = $this->postJson(
            self::API_BASE . '/models/' . rawurlencode( $this->model ) . ':generateContent',
            [ 'x-goog-api-key' => $this->api_key ],
            $this->buildRequestBody( $system, $messages, $tools )
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $candidate = $response['candidates'][0] ?? [];
        $parts     = (array) ( $candidate['content']['parts'] ?? [] );

        $text       = '';
        $tool_calls = [];
        foreach ( $parts as $part ) {
            if ( isset( $part['text'] ) ) {
                $text .= $part['text'];
            }
            if ( isset( $part['functionCall'] ) ) {
                // Gemini has no call ids — mint stable ones for the loop.
                $tool_calls[] = [
                    'id'        => uniqid( 'gcall_' ),
                    'name'      => self::abilityName( (string) ( $part['functionCall']['name'] ?? '' ) ),
                    'arguments' => is_array( $part['functionCall']['args'] ?? null ) ? $part['functionCall']['args'] : [],
                ];
            }
        }

        $finish      = (string) ( $candidate['finishReason'] ?? 'STOP' );
        $stop_reason = 'end_turn';
        if ( ! empty( $tool_calls ) ) {
            $stop_reason = 'tool_use';
        } elseif ( 'MAX_TOKENS' === $finish ) {
            $stop_reason = 'max_tokens';
        }

        return [
            'text'        => $text,
            'tool_calls'  => $tool_calls,
            'stop_reason' => $stop_reason,
            'raw'         => $candidate['content'] ?? [],
            'usage'       => [
                'input_tokens'  => (int) ( $response['usageMetadata']['promptTokenCount'] ?? 0 ),
                'output_tokens' => (int) ( $response['usageMetadata']['candidatesTokenCount'] ?? 0 ),
            ],
        ];
    }

    /**
     * Real streaming: Gemini's `:streamGenerateContent?alt=sse` emits a
     * sequence of `data: {...}` SSE frames, each a PARTIAL GenerateContentResponse
     * — i.e. `parts[].text` on each frame is already an incremental fragment,
     * not the cumulative text so far, so every text part can be handed to
     * $on_delta as-is the moment it arrives. functionCall parts can also
     * appear inside the stream (typically on the final frame); those are
     * accumulated but never sent to $on_delta, which carries assistant text only.
     */
    public function chatStream( string $system, array $messages, array $tools, callable $on_delta ) {
        if ( ! function_exists( 'curl_init' ) ) {
            // No cURL — postJsonStreamed() has no way to observe partial
            // bytes, so fall back to one full-text delta rather than fail.
            return parent::chatStream( $system, $messages, $tools, $on_delta );
        }

        $url  = self::API_BASE . '/models/' . rawurlencode( $this->model ) . ':streamGenerateContent?alt=sse';
        $body = $this->buildRequestBody( $system, $messages, $tools );

        $raw_parts  = [];
        $sse_buffer = '';
        $text       = '';
        $tool_calls = [];
        $finish     = 'STOP';
        $usage      = [ 'input_tokens' => 0, 'output_tokens' => 0 ];

        $on_chunk = function ( string $data ) use ( &$sse_buffer, &$text, &$tool_calls, &$raw_parts, &$finish, &$usage, $on_delta ) {
            foreach ( self::extractSseDataLines( $sse_buffer, $data ) as $payload ) {
                if ( '' === $payload ) {
                    continue;
                }
                $decoded = json_decode( $payload, true );
                if ( ! is_array( $decoded ) ) {
                    // A frame split oddly across two WRITEFUNCTION calls, or a
                    // provider-side hiccup — drop it and keep the stream alive
                    // rather than failing an otherwise healthy turn.
                    continue;
                }

                $candidate = $decoded['candidates'][0] ?? [];
                foreach ( (array) ( $candidate['content']['parts'] ?? [] ) as $part ) {
                    if ( isset( $part['text'] ) && '' !== $part['text'] ) {
                        $text .= $part['text'];
                        $on_delta( (string) $part['text'] );
                    }
                    if ( isset( $part['functionCall'] ) ) {
                        $tool_calls[] = [
                            'id'        => uniqid( 'gcall_' ),
                            'name'      => self::abilityName( (string) ( $part['functionCall']['name'] ?? '' ) ),
                            'arguments' => is_array( $part['functionCall']['args'] ?? null ) ? $part['functionCall']['args'] : [],
                        ];
                        $raw_parts[]  = $part;
                    }
                }
                if ( isset( $candidate['finishReason'] ) ) {
                    $finish = (string) $candidate['finishReason'];
                }
                if ( isset( $decoded['usageMetadata'] ) ) {
                    // Gemini reports cumulative usage on later frames — last one wins.
                    $usage = [
                        'input_tokens'  => (int) ( $decoded['usageMetadata']['promptTokenCount'] ?? $usage['input_tokens'] ),
                        'output_tokens' => (int) ( $decoded['usageMetadata']['candidatesTokenCount'] ?? $usage['output_tokens'] ),
                    ];
                }
            }
        };

        $result = $this->postJsonStreamed( $url, [ 'x-goog-api-key' => $this->api_key ], $body, $on_chunk );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        if ( $result['code'] < 200 || $result['code'] >= 300 ) {
            // Error responses are not streamed — $result['body'] is the plain
            // JSON error object, so the normal status/body mapping applies.
            return $this->decodeStatusAndBody( $result['code'], $result['body'] );
        }

        $stop_reason = 'end_turn';
        if ( ! empty( $tool_calls ) ) {
            $stop_reason = 'tool_use';
        } elseif ( 'MAX_TOKENS' === $finish ) {
            $stop_reason = 'max_tokens';
        }

        // Reassemble the SAME { role: 'model', parts: [...] } shape chat()
        // stores in `raw` (one merged text part, then each function call) so
        // buildContents() replays a streamed turn exactly like a non-streamed
        // one on the next request — see rawFor()/buildContents() above.
        $parts = [];
        if ( '' !== $text ) {
            $parts[] = [ 'text' => $text ];
        }
        if ( ! empty( $raw_parts ) ) {
            foreach ( $raw_parts as $raw_part ) {
                $parts[] = $raw_part;
            }
        } else {
            foreach ( $tool_calls as $call ) {
                $parts[] = [
                    'functionCall' => [
                        'name' => self::wireName( $call['name'] ),
                        'args' => self::toArgsObject( $call['arguments'] ),
                    ],
                ];
            }
        }

        return [
            'text'        => $text,
            'tool_calls'  => $tool_calls,
            'stop_reason' => $stop_reason,
            'raw'         => [
                'role'  => 'model',
                'parts' => $parts,
            ],
            'usage'       => $usage,
        ];
    }

    /**
     * Shared request body for both chat() and chatStream() — the streaming
     * endpoint takes the exact same payload shape, just posted to a
     * different path with `?alt=sse`.
     */
    private function buildRequestBody( string $system, array $messages, array $tools ): array {
        $body = [
            'systemInstruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
            'contents'          => $this->buildContents( $messages ),
            'generationConfig'  => [ 'maxOutputTokens' => self::MAX_TOKENS ],
        ];

        if ( ! empty( $tools ) ) {
            $body['tools'] = [
                [
                    'functionDeclarations' => array_map(
                        static function ( $tool ) {
                            return [
                                'name'        => self::wireName( $tool['name'] ),
                                'description' => $tool['description'],
                                'parameters'  => SchemaTranslator::forGemini( $tool['input_schema'] ),
                            ];
                        },
                        $tools
                    ),
                ],
            ];
        }

        return $body;
    }

    public function validateKey() {
        $result = $this->getJson(
            self::API_BASE . '/models?pageSize=1',
            [ 'x-goog-api-key' => $this->api_key ]
        );
        return is_wp_error( $result ) ? $result : true;
    }

    // -----------------------------------------------------------------------
    // History conversion
    // -----------------------------------------------------------------------

    private function buildContents( array $messages ): array {
        $out = [];

        foreach ( $messages as $message ) {
            $role    = $message['role'] ?? '';
            $content = self::content( $message );

            if ( 'user' === $role ) {
                $out[] = [ 'role' => 'user', 'parts' => [ [ 'text' => (string) ( $content['text'] ?? '' ) ] ] ];
                continue;
            }

            if ( 'assistant' === $role ) {
                $raw = $this->rawFor( $message );
                if ( is_array( $raw ) && ! empty( $raw['parts'] ) ) {
                    $out[] = [ 'role' => 'model', 'parts' => self::normalizeParts( $raw['parts'] ) ];
                    continue;
                }
                $parts = [];
                if ( '' !== (string) ( $content['text'] ?? '' ) ) {
                    $parts[] = [ 'text' => (string) $content['text'] ];
                }
                foreach ( (array) ( $content['tool_calls'] ?? [] ) as $call ) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => self::wireName( $call['name'] ),
                            'args' => self::toArgsObject( $call['arguments'] ?? null ),
                        ],
                    ];
                }
                if ( ! empty( $parts ) ) {
                    $out[] = [ 'role' => 'model', 'parts' => $parts ];
                }
                continue;
            }

            if ( 'tool' === $role ) {
                $decoded = json_decode( (string) ( $content['content'] ?? '' ), true );
                $part    = [
                    'functionResponse' => [
                        'name'     => self::wireName( (string) ( $content['name'] ?? '' ) ),
                        'response' => [ 'result' => null !== $decoded ? $decoded : (string) ( $content['content'] ?? '' ) ],
                    ],
                ];
                // Consecutive function responses merge into one user turn.
                $last = count( $out ) - 1;
                if ( $last >= 0 && 'user' === $out[ $last ]['role'] && isset( $out[ $last ]['parts'][0]['functionResponse'] ) ) {
                    $out[ $last ]['parts'][] = $part;
                } else {
                    $out[] = [ 'role' => 'user', 'parts' => [ $part ] ];
                }
            }
        }

        // Gemini refuses any request whose contents end on a model turn
        // ("Requests ending with a model turn are not supported."), unlike
        // Anthropic and OpenAI which happily continue from one. Drop the
        // trailing model turns so a history that ends that way still asks a
        // valid question instead of failing the whole turn.
        while ( ! empty( $out ) && 'model' === $out[ count( $out ) - 1 ]['role'] ) {
            array_pop( $out );
        }

        return $out;
    }

    /**
     * Gemini function names must match ^[a-zA-Z_][a-zA-Z0-9_.-]* — no slashes.
     */
    private static function wireName( string $ability_name ): string {
        return str_replace( '/', '__', $ability_name );
    }

    private static function abilityName( string $wire_name ): string {
        return str_replace( '__', '/', $wire_name );
    }

    /**
     * Gemini's proto rejects `functionCall.args: []` — an empty PHP array
     * round-tripped through json_decode( ..., true ) is indistinguishable
     * from an empty JSON object, so force it to a Struct on the way out.
     */
    private static function toArgsObject( $args ) {
        return ( is_array( $args ) && ! empty( $args ) ) ? $args : new \stdClass();
    }

    /**
     * Self-heals previously stored `raw` parts whose functionCall.args
     * decoded to an empty array before being replayed to Gemini.
     */
    private static function normalizeParts( array $parts ): array {
        foreach ( $parts as &$part ) {
            if ( isset( $part['functionCall'] ) && is_array( $part['functionCall'] ) ) {
                $part['functionCall']['args'] = self::toArgsObject( $part['functionCall']['args'] ?? null );
            }
        }
        return $parts;
    }
}
