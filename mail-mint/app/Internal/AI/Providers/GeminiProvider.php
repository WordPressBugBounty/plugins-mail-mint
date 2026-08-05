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

        $response = $this->postJson(
            self::API_BASE . '/models/' . rawurlencode( $this->model ) . ':generateContent',
            [ 'x-goog-api-key' => $this->api_key ],
            $body
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
                    $out[] = [ 'role' => 'model', 'parts' => $raw['parts'] ];
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
                            'args' => is_array( $call['arguments'] ?? null ) ? $call['arguments'] : new \stdClass(),
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
}
