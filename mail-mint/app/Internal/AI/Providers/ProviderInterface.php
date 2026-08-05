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
