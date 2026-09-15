<?php
/**
 * ProviderInterface — contract every AI provider adapter implements.
 *
 * The agent loop speaks one NORMALIZED dialect; adapters translate to and
 * from each provider's wire format.
 *
 * Normalized message (stored in mint_ai_messages.content as JSON):
 *   role 'user'      : { text: string }
 *   role 'assistant' : { text: string, tool_calls: [ {id, name, arguments:object} ] }
 *   role 'tool'      : { tool_call_id: string, name: string, content: string, is_error: bool }
 *
 * Assistant messages may carry provider-native blocks in meta['raw'] (e.g.
 * Anthropic thinking blocks) which adapters replay verbatim when rebuilding
 * history for the SAME provider — required for tool-use loops with thinking.
 *
 * Normalized response from chat():
 *   {
 *     text:        string,
 *     tool_calls:  [ {id, name, arguments:object} ],
 *     stop_reason: 'end_turn'|'tool_use'|'max_tokens',
 *     raw:         mixed,   // provider-native assistant payload for history replay
 *     usage:       { input_tokens:int, output_tokens:int }
 *   }
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Providers;

defined( 'ABSPATH' ) || exit;

interface ProviderInterface {

    /**
     * Run one model turn.
     *
     * @param string $system   System prompt.
     * @param array  $messages Normalized message list (each: role, content, meta).
     * @param array  $tools    Normalized tool defs: [ {name, description, input_schema} ].
     * @return array|\WP_Error Normalized response.
     */
    public function chat( string $system, array $messages, array $tools );

    /**
     * Run one model turn, delivering assistant TEXT to $on_delta as it
     * becomes available instead of only after the full reply is ready.
     *
     * $on_delta may be called any number of times (zero, once, or many) with
     * successive chunks of assistant-visible text — never tool arguments or
     * other provider-internal payloads. It is called synchronously, on the
     * same thread, before this method returns.
     *
     * The return value is the SAME normalized shape as chat() — {text,
     * tool_calls, stop_reason, raw, usage} — with `text` holding the full
     * accumulated reply. A provider that has no real streaming transport (or
     * one this codebase isn't confident it can parse correctly) may simply
     * run chat() and hand the whole reply to $on_delta once; see
     * AbstractProvider::chatStream() for that default.
     *
     * @param string   $system   System prompt.
     * @param array    $messages Normalized message list (each: role, content, meta).
     * @param array    $tools    Normalized tool defs: [ {name, description, input_schema} ].
     * @param callable $on_delta function( string $text_chunk ): void.
     * @return array|\WP_Error Normalized response, same shape as chat().
     */
    public function chatStream( string $system, array $messages, array $tools, callable $on_delta );

    /**
     * Cheap live credential check.
     *
     * @return true|\WP_Error
     */
    public function validateKey();

    /**
     * Provider slug ('anthropic' | 'openai' | 'gemini').
     */
    public function slug(): string;
}
