<?php
/**
 * AbstractProvider — shared HTTP plumbing for AI provider adapters.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Providers;

defined( 'ABSPATH' ) || exit;

abstract class AbstractProvider implements ProviderInterface {

    /**
     * Per-step output ceiling. Deliberately below the SDK-default 16K: each
     * agent-loop step runs inside one synchronous REST request, and shared
     * hosting PHP timeouts are the binding constraint, not output length.
     */
    protected const MAX_TOKENS = 8192;

    protected const HTTP_TIMEOUT = 90;

    protected string $api_key;
    protected string $model;

    public function __construct( string $api_key, string $model ) {
        $this->api_key = $api_key;
        $this->model   = $model;
    }

    /**
     * POST JSON and decode the response, mapping HTTP failures to WP_Error.
     *
     * @return array|\WP_Error Decoded JSON body.
     */
    protected function postJson( string $url, array $headers, array $body ) {
        $response = wp_remote_post(
            $url,
            [
                'timeout' => self::HTTP_TIMEOUT,
                'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
                'body'    => wp_json_encode( $body ),
            ]
        );
        return $this->decodeResponse( $response );
    }

    /**
     * GET and decode, same error mapping.
     *
     * @return array|\WP_Error
     */
    protected function getJson( string $url, array $headers ) {
        $response = wp_remote_get(
            $url,
            [
                'timeout' => 30,
                'headers' => $headers,
            ]
        );
        return $this->decodeResponse( $response );
    }

    /**
     * @param array|\WP_Error $response wp_remote_* result.
     * @return array|\WP_Error
     */
    private function decodeResponse( $response ) {
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'ai_network_error', sprintf( 'Could not reach %s: %s', $this->slug(), $response->get_error_message() ) );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 200 && $code < 300 ) {
            return is_array( $body ) ? $body : [];
        }

        $detail = '';
        if ( is_array( $body ) ) {
            $detail = $body['error']['message'] ?? ( $body['message'] ?? ( $body['error'] ?? '' ) );
            $detail = is_string( $detail ) ? $detail : wp_json_encode( $detail );
        }

        $map = [
            401 => [ 'ai_auth_error', 'The API key was rejected. Reconnect with a valid key.' ],
            403 => [ 'ai_auth_error', 'The API key lacks permission for this request.' ],
            404 => [ 'ai_model_error', 'The configured model was not found.' ],
            429 => [ 'ai_rate_limited', 'The provider rate-limited the request. Try again shortly.' ],
            529 => [ 'ai_overloaded', 'The provider is temporarily overloaded. Try again shortly.' ],
        ];
        [ $err_code, $err_message ] = $map[ $code ] ?? [ 'ai_provider_error', sprintf( 'Provider returned HTTP %d.', $code ) ];

        return new \WP_Error( $err_code, trim( $err_message . ( $detail ? ' (' . $detail . ')' : '' ) ), [ 'status' => $code ] );
    }

    /**
     * Decode a message row's JSON content into an array.
     */
    protected static function content( array $message ): array {
        $content = $message['content'] ?? [];
        if ( is_string( $content ) ) {
            $content = json_decode( $content, true );
        }
        return is_array( $content ) ? $content : [];
    }

    /**
     * Provider-native raw payload stored on an assistant message, if it was
     * produced by THIS provider (raw blocks are not portable across providers).
     */
    protected function rawFor( array $message ) {
        $meta = $message['meta'] ?? [];
        if ( is_string( $meta ) ) {
            $meta = json_decode( $meta, true );
        }
        if ( is_array( $meta ) && ( $meta['provider'] ?? '' ) === $this->slug() && isset( $meta['raw'] ) ) {
            return $meta['raw'];
        }
        return null;
    }
}
