<?php
/**
 * WordPressAIProvider — wraps the WordPress AI Services API.
 *
 * This provider delegates to whichever AI service the site owner has
 * configured via the WordPress AI Services plugin (wordpress.org/plugins/ai-services)
 * or a hosting-level WordPress AI Client. No API key is stored in Mail Mint;
 * the underlying service handles credentials.
 *
 * Because the AI Services plugin does not guarantee tool-use support across
 * all configured backends, this provider operates in text-generation mode.
 * The agent loop receives text-only responses (no tool_calls) which means
 * it can answer questions from context but cannot execute write operations.
 * For the full AI Copilot (create campaigns, build automations), use a direct
 * API key via Anthropic, OpenAI, or Gemini.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Providers;

defined( 'ABSPATH' ) || exit;

class WordPressAIProvider implements ProviderInterface {

    protected string $model;

    public function __construct( string $api_key = '', string $model = '' ) {
        $this->model = '' !== $model ? $model : 'auto';
    }

    public function slug(): string {
        return 'wordpress_ai';
    }

    /**
     * Check whether a WordPress AI service is available and configured.
     *
     * Prefers the native WordPress AI Client (wp_ai_client_prompt(), core since
     * WP 7.0) when present, falling back to the third-party AI Services plugin
     * (ai_services()/wp_ai_services()) for older sites.
     *
     * @return true|\WP_Error
     */
    public function validateKey() {
        if ( self::isNativeAvailable() ) {
            if ( ! self::isNativeConfigured() ) {
                return new \WP_Error(
                    'wp_ai_no_service',
                    __(
                        'WordPress AI is available but no provider is configured under it. Add one under Settings → Connectors (or your hosting panel).',
                        'mrm'
                    )
                );
            }

            return true;
        }

        if ( ! self::isAvailable() ) {
            return new \WP_Error(
                'wp_ai_unavailable',
                __(
                    'WordPress AI is not available on this site. Install the AI Services plugin (wordpress.org/plugins/ai-services) or use hosting that provides the WordPress AI Client API.',
                    'mrm'
                )
            );
        }

        $services = self::getAvailableServices();
        if ( empty( $services ) ) {
            return new \WP_Error(
                'wp_ai_no_service',
                __(
                    'WordPress AI is installed but no AI provider is configured under it. Add one under Settings → AI Services (or your hosting panel).',
                    'mrm'
                )
            );
        }

        return true;
    }

    /**
     * Run one text-generation turn via the WordPress AI Services API.
     *
     * Tool use is not attempted — the response will always have an empty
     * tool_calls array. See class docblock for rationale.
     *
     * @param string $system   System prompt text.
     * @param array  $messages Normalized message list.
     * @param array  $tools    Tool definitions (accepted but not forwarded).
     * @return array|\WP_Error Normalized response.
     */
    public function chat( string $system, array $messages, array $tools ) {
        if ( self::isNativeAvailable() ) {
            try {
                return $this->doNativeChat( $system, $messages );
            } catch ( \Throwable $e ) {
                return new \WP_Error( 'wp_ai_error', $e->getMessage() );
            }
        }

        if ( ! self::isAvailable() ) {
            return new \WP_Error( 'wp_ai_unavailable', __( 'WordPress AI is not available.', 'mrm' ) );
        }

        try {
            return $this->doChat( $system, $messages );
        } catch ( \Throwable $e ) {
            return new \WP_Error( 'wp_ai_error', $e->getMessage() );
        }
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Run one text-generation turn via the native WordPress AI Client
     * (wp_ai_client_prompt(), core since WP 7.0).
     *
     * @throws \Throwable
     * @return array|\WP_Error
     */
    private function doNativeChat( string $system, array $messages ) {
        $prompt = wp_ai_client_prompt( self::flattenMessagesToPrompt( $messages ) );

        if ( '' !== $system ) {
            $prompt->using_system_instruction( $system );
        }

        if ( ! $prompt->is_supported_for_text_generation() ) {
            return new \WP_Error( 'wp_ai_no_service', __( 'WordPress AI is installed but no provider is configured for text generation.', 'mrm' ) );
        }

        $result = $prompt->generate_text();
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( '' === trim( (string) $result ) ) {
            return new \WP_Error( 'wp_ai_empty_response', __( 'WordPress AI returned no response.', 'mrm' ) );
        }

        return [
            'text'        => $result,
            'tool_calls'  => [],
            'stop_reason' => 'end_turn',
            'raw'         => null,
            'usage'       => [ 'input_tokens' => 0, 'output_tokens' => 0 ],
        ];
    }

    /**
     * Flatten normalized messages into role-prefixed prompt text.
     *
     * @param array $messages Normalized message list.
     * @return string
     */
    private static function flattenMessagesToPrompt( array $messages ): string {
        $lines = [];
        foreach ( $messages as $msg ) {
            $role    = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? [];
            if ( is_array( $content ) ) {
                $text = $content['text'] ?? wp_json_encode( $content );
            } else {
                $text = (string) $content;
            }
            $label   = 'assistant' === $role ? 'Assistant' : 'User';
            $lines[] = $label . ': ' . $text;
        }
        return implode( "\n\n", $lines );
    }

    /**
     * @throws \Throwable
     * @return array|\WP_Error
     */
    private function doChat( string $system, array $messages ) {
        $fn  = self::servicesFunctionName();
        $api = $fn();

        $service = method_exists( $api, 'get_available_service' )
            ? $api->get_available_service( [ 'feature' => 'text-generation' ] )
            : null;

        if ( null === $service || is_wp_error( $service ) ) {
            return new \WP_Error( 'wp_ai_no_service', __( 'No WordPress AI service is available.', 'mrm' ) );
        }

        $model_args = [ 'feature' => 'text-generation' ];
        if ( 'auto' !== $this->model && '' !== $this->model ) {
            $model_args['model'] = $this->model;
        }

        $model_obj = method_exists( $service, 'get_model_object' )
            ? $service->get_model_object( $model_args )
            : null;

        if ( null === $model_obj || is_wp_error( $model_obj ) ) {
            return new \WP_Error( 'wp_ai_model_error', __( 'Could not load a WordPress AI model.', 'mrm' ) );
        }

        // Flatten messages into a prompt string with role prefixes.
        $history_lines = [];
        if ( '' !== $system ) {
            $history_lines[] = 'System: ' . $system;
        }
        $history_lines[] = self::flattenMessagesToPrompt( $messages );

        $prompt = implode( "\n\n", $history_lines );

        $options    = [];
        $candidates = method_exists( $model_obj, 'generate_text' )
            ? $model_obj->generate_text( $prompt, $options )
            : null;

        if ( null === $candidates || is_wp_error( $candidates ) ) {
            $msg = is_wp_error( $candidates ) ? $candidates->get_error_message() : __( 'WordPress AI returned no response.', 'mrm' );
            return new \WP_Error( 'wp_ai_empty_response', $msg );
        }

        // Extract text from the candidates object (various AI Services versions).
        $response_text = '';
        if ( function_exists( 'ai_candidates_text' ) ) {
            $response_text = ai_candidates_text( $candidates );
        } elseif ( is_string( $candidates ) ) {
            $response_text = $candidates;
        } elseif ( method_exists( $candidates, 'get_candidates' ) ) {
            $first = $candidates->get_candidates()[0] ?? null;
            if ( $first && method_exists( $first, 'get_content' ) ) {
                $parts = $first->get_content()->get_parts();
                foreach ( $parts as $part ) {
                    if ( method_exists( $part, 'get_text' ) ) {
                        $response_text .= $part->get_text();
                    }
                }
            }
        }

        return [
            'text'        => $response_text,
            'tool_calls'  => [],
            'stop_reason' => 'end_turn',
            'raw'         => null,
            'usage'       => [ 'input_tokens' => 0, 'output_tokens' => 0 ],
        ];
    }

    // -----------------------------------------------------------------------
    // Static availability checks
    // -----------------------------------------------------------------------

    /**
     * Whether the native WordPress AI Client (core since WP 7.0, also backed by
     * the wordpress.org "AI" companion plugin) is present on this site.
     */
    public static function isNativeAvailable(): bool {
        return function_exists( 'wp_ai_client_prompt' );
    }

    /**
     * Whether the native WordPress AI Client has a connector configured that
     * actually supports text generation. Mirrors the check the "AI" companion
     * plugin and other integrations (e.g. FluentCRM) use before generating.
     */
    public static function isNativeConfigured(): bool {
        if ( ! self::isNativeAvailable() ) {
            return false;
        }
        try {
            return wp_ai_client_prompt( 'Test' )->is_supported_for_text_generation();
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    public static function isAvailable(): bool {
        return '' !== self::servicesFunctionName();
    }

    public static function getAvailableServices(): array {
        if ( ! self::isAvailable() ) {
            return [];
        }
        try {
            $fn  = self::servicesFunctionName();
            $api = $fn();
            if ( method_exists( $api, 'get_available_services' ) ) {
                $result = $api->get_available_services();
                return is_array( $result ) ? $result : [];
            }
        } catch ( \Throwable $e ) {
            // Swallow — not available.
        }
        return [];
    }

    private static function servicesFunctionName(): string {
        if ( function_exists( 'ai_services' ) ) {
            return 'ai_services';
        }
        if ( function_exists( 'wp_ai_services' ) ) {
            return 'wp_ai_services';
        }
        return '';
    }
}
