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
     * Default chatStream(): providers that either have no streaming transport
     * of their own, or whose wire format this codebase isn't confident enough
     * to hand-parse correctly, degrade to the ordinary blocking chat() call
     * and deliver the whole reply as a single delta. This keeps every
     * provider usable behind the SSE route from day one — a provider only
     * needs to override this method once its real streaming is verified.
     *
     * @param string   $system   System prompt.
     * @param array    $messages Normalized message list.
     * @param array    $tools    Normalized tool defs.
     * @param callable $on_delta function( string $text_chunk ): void.
     * @return array|\WP_Error Normalized response, same shape as chat().
     */
    public function chatStream( string $system, array $messages, array $tools, callable $on_delta ) {
        $response = $this->chat( $system, $messages, $tools );
        if ( ! is_wp_error( $response ) && '' !== (string) ( $response['text'] ?? '' ) ) {
            $on_delta( (string) $response['text'] );
        }
        return $response;
    }

    /**
     * POST a request whose body arrives over the wire in chunks, invoking
     * $on_chunk with each raw fragment AS IT ARRIVES rather than only once
     * the full body is buffered.
     *
     * Note: wp_remote_post() always waits for and returns the complete
     * response body — there is no public WP_Http option for a per-chunk
     * callback. The only way to observe bytes as they land is to reach into
     * the underlying cURL handle directly, which WP exposes via the
     * `http_api_curl` action (fired with the live handle, by reference,
     * immediately before curl_exec()). We attach a CURLOPT_WRITEFUNCTION
     * there for exactly this one request and detach in `finally` —
     * `http_api_curl` is a GLOBAL WP hook, so leaving a closure attached
     * would keep intercepting (and silently swallowing the body of) every
     * unrelated HTTP request the process makes afterward.
     *
     * The same curl handle also gets a CURLOPT_XFERINFOFUNCTION, which — unlike
     * WRITEFUNCTION — fires periodically even before any bytes have arrived
     * (while curl is still waiting on the socket). That is the only point
     * available to (a) notice a client that closed the panel while the
     * provider is still thinking, aborting the transfer instead of running it
     * to completion for nobody, and (b) emit the `: keepalive` SSE comment
     * roughly every 15s of silence so intermediary proxies don't drop an
     * idle connection during a slow time-to-first-byte.
     *
     * @param string   $url      Request URL.
     * @param array    $headers  Extra request headers (Content-Type is added automatically).
     * @param array    $body     Request body; JSON-encoded before sending.
     * @param callable $on_chunk function( string $raw_bytes ): void.
     * @return array{code:int, body:string}|\WP_Error {code, body} for ANY
     *         HTTP response (2xx or not) so callers can run the response body
     *         through the same status-code error mapping decodeResponse()
     *         uses; WP_Error only for a network failure wp_remote_post()
     *         itself caught (DNS, connect refused, timeout, ...).
     */
    protected function postJsonStreamed( string $url, array $headers, array $body, callable $on_chunk ) {
        if ( ! function_exists( 'curl_init' ) ) {
            return new \WP_Error( 'ai_no_curl', 'Streaming requires the PHP cURL extension.' );
        }

        $raw            = '';
        $last_heartbeat = microtime( true );

        $hook = static function ( $handle ) use ( $on_chunk, &$raw, &$last_heartbeat ) {
            // Raw curl_setopt() is unavoidable here — this is the one place in
            // the codebase that needs a per-chunk write callback, which no
            // wp_remote_*() wrapper exposes; see the method docblock above.
            curl_setopt( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
                $handle,
                CURLOPT_WRITEFUNCTION,
                static function ( $curl_handle, $data ) use ( $on_chunk, &$raw, &$last_heartbeat ) {
                    $raw           .= $data;
                    $last_heartbeat = microtime( true );
                    $on_chunk( $data );
                    if ( connection_aborted() ) {
                        // Returning fewer bytes than were handed in is cURL's
                        // documented signal to abort the transfer immediately.
                        return 0;
                    }
                    return strlen( $data );
                }
            );

            // NOPROGRESS defaults to true (progress callback disabled) — must
            // be turned off explicitly for XFERINFOFUNCTION to fire at all.
            curl_setopt( $handle, CURLOPT_NOPROGRESS, false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
            curl_setopt( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
                $handle,
                CURLOPT_XFERINFOFUNCTION,
                static function ( $curl_handle, $expected_down, $downloaded, $expected_up, $uploaded ) use ( &$last_heartbeat ) {
                    if ( connection_aborted() ) {
                        return 1; // Non-zero also aborts the transfer for this callback.
                    }
                    if ( microtime( true ) - $last_heartbeat >= 15 ) {
                        echo ": keepalive\n\n";
                        flush();
                        $last_heartbeat = microtime( true );
                    }
                    return 0;
                }
            );

            // A streamed reply legitimately runs the whole HTTP_TIMEOUT; make
            // sure cURL's own timeout matches instead of a shorter default.
            curl_setopt( $handle, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
        };

        add_action( 'http_api_curl', $hook );
        try {
            $response = wp_remote_post(
                $url,
                [
                    'timeout' => self::HTTP_TIMEOUT,
                    'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
                    'body'    => wp_json_encode( $body ),
                ]
            );
        } finally {
            remove_action( 'http_api_curl', $hook );
        }

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'ai_network_error', sprintf( 'Could not reach %s: %s', $this->slug(), $response->get_error_message() ) );
        }

        return [
            'code' => (int) wp_remote_retrieve_response_code( $response ),
            'body' => $raw,
        ];
    }

    /**
     * Drain an SSE byte fragment into the raw `data: ...` payload strings it
     * contains, carrying any incomplete trailing line over in $buffer for the
     * next call. Shared by every provider whose stream uses plain
     * `data: <json>` framing (Gemini, OpenAI) — event-typed SSE (Anthropic)
     * needs its own parser and does not use this helper.
     *
     * @param string $buffer Carried across calls; mutated in place.
     * @param string $chunk  Newly arrived raw bytes.
     * @return string[] Trimmed payload strings (the part after `data:`), in
     *                   order; blank lines and non-"data:" lines are dropped.
     */
    protected static function extractSseDataLines( string &$buffer, string $chunk ): array {
        $buffer .= $chunk;
        $payloads = [];
        $pos      = strpos( $buffer, "\n" );
        while ( false !== $pos ) {
            $line   = rtrim( substr( $buffer, 0, $pos ), "\r" );
            $buffer = substr( $buffer, $pos + 1 );
            $pos    = strpos( $buffer, "\n" );
            if ( '' === $line || 0 !== strpos( $line, 'data:' ) ) {
                // Blank frame separators, and any other SSE field (e.g. a
                // provider that also sends "event:" lines) carry nothing this
                // parser's callers act on.
                continue;
            }
            $payloads[] = trim( substr( $line, 5 ) );
        }
        return $payloads;
    }

    /**
     * @param array|\WP_Error $response wp_remote_* result.
     * @return array|\WP_Error
     */
    private function decodeResponse( $response ) {
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'ai_network_error', sprintf( 'Could not reach %s: %s', $this->slug(), $response->get_error_message() ) );
        }

        return $this->decodeStatusAndBody(
            (int) wp_remote_retrieve_response_code( $response ),
            (string) wp_remote_retrieve_body( $response )
        );
    }

    /**
     * The status-code/body error mapping decodeResponse() applies, factored
     * out so postJsonStreamed() callers — whose body never passes through
     * wp_remote_retrieve_body() because the WRITEFUNCTION override diverts
     * it — can run the same mapping over the bytes they accumulated
     * themselves on a non-2xx response.
     *
     * @return array|\WP_Error
     */
    protected function decodeStatusAndBody( int $code, string $raw_body ) {
        $body = json_decode( $raw_body, true );

        if ( $code >= 200 && $code < 300 ) {
            return is_array( $body ) ? $body : [];
        }

        $detail = '';
        if ( is_array( $body ) ) {
            $detail = $body['error']['message'] ?? ( $body['message'] ?? ( $body['error'] ?? '' ) );
            $detail = is_string( $detail ) ? $detail : wp_json_encode( $detail );
        }

        // OpenAI (and others) reuse HTTP 429 for two very different cases:
        // transient rate-limiting and a hard quota/billing stop. Only the
        // former should get the generic "try again shortly" treatment — a
        // quota error needs to surface its real, actionable message.
        $error_type = is_array( $body ) ? ( $body['error']['type'] ?? ( $body['error']['code'] ?? '' ) ) : '';
        $is_quota_error = 429 === $code && in_array( $error_type, [ 'insufficient_quota', 'billing_not_active' ], true );

        if ( $is_quota_error ) {
            return new \WP_Error( 'ai_quota_exceeded', $detail ?: 'The provider account has no quota remaining.', [ 'status' => $code ] );
        }

        $map = [
            401 => [ 'ai_auth_error', 'The API key was rejected. Reconnect with a valid key.' ],
            403 => [ 'ai_auth_error', 'The API key lacks permission for this request.' ],
            404 => [ 'ai_model_error', 'The configured model was not found.' ],
            429 => [ 'ai_rate_limited', 'The provider rate-limited the request. Try again shortly.' ],
            529 => [ 'ai_overloaded', 'The provider is temporarily overloaded. Try again shortly.' ],
        ];
        [ $err_code, $err_message ] = $map[ $code ] ?? [ 'ai_provider_error', sprintf( 'Provider returned HTTP %d.', $code ) ];

        // Rate-limit/overload bodies are long, link-filled walls of
        // provider-specific text (quota metric names, billing URLs) — not
        // useful to surface verbatim. Keep the friendly message and, when the
        // provider tells us how long to wait, fold just that number in.
        if ( in_array( $code, [ 429, 529 ], true ) ) {
            $retry_after = self::extractRetrySeconds( $body );
            if ( $retry_after ) {
                $err_message = rtrim( $err_message, '.' ) . sprintf( '. Retry in %ds.', $retry_after );
            }
            return new \WP_Error( $err_code, $err_message, [ 'status' => $code, 'retry_after' => $retry_after ] );
        }

        return new \WP_Error( $err_code, trim( $err_message . ( $detail ? ' (' . $detail . ')' : '' ) ), [ 'status' => $code ] );
    }

    /**
     * Pull a retry delay in whole seconds out of a provider's error body, if
     * it says one. Gemini reports this as an `error.details[]` entry shaped
     * `{ "@type": "...RetryInfo", "retryDelay": "30s" }`; other providers
     * carry nothing of the kind, so this simply returns null for them.
     */
    private static function extractRetrySeconds( $body ): ?int {
        $details = is_array( $body ) ? ( $body['error']['details'] ?? null ) : null;
        if ( ! is_array( $details ) ) {
            return null;
        }
        foreach ( $details as $entry ) {
            if ( is_array( $entry ) && isset( $entry['retryDelay'] ) && is_string( $entry['retryDelay'] )
                && preg_match( '/^(\d+(?:\.\d+)?)s$/', $entry['retryDelay'], $matches ) ) {
                return (int) ceil( (float) $matches[1] );
            }
        }
        return null;
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
