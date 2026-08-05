<?php
/**
 * SingleShotAIService — one-shot, non-conversational AI generation.
 *
 * Point-of-use helpers (inline subject-line writer, analytics summary,
 * contact-profile summary) need a single "prompt in → text out" call, NOT the
 * multi-turn AgentLoop with its conversation store and confirmation flow. This
 * service reuses the SAME provider adapters and encrypted keys as the copilot
 * (via AIInit) but performs exactly one model round-trip with no tools and no
 * persistence — cheap enough to run inline behind a field-level button.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI;

defined( 'ABSPATH' ) || exit;

class SingleShotAIService {

    /**
     * Run one model turn: a system prompt plus a single user message, with no
     * tools, no history, and no persistence.
     *
     * @param string $system    System/instruction prompt.
     * @param string $user_text User content to act on.
     * @return string|\WP_Error Generated text, or an error.
     */
    public static function generate( string $system, string $user_text ) {
        if ( '' === trim( $user_text ) ) {
            return new \WP_Error( 'ai_empty_prompt', __( 'There is nothing for the AI to work with.', 'mrm' ) );
        }

        $provider = AIInit::activeProvider();
        if ( is_wp_error( $provider ) ) {
            return $provider;
        }

        $response = $provider->chat(
            $system,
            [
                [
                    'role'    => 'user',
                    'content' => [ 'text' => $user_text ],
                ],
            ],
            []
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $text = trim( (string) ( $response['text'] ?? '' ) );
        if ( '' === $text ) {
            return new \WP_Error( 'ai_empty_response', __( 'The AI returned no content. Please try again.', 'mrm' ) );
        }

        /**
         * Fires after a single-shot generation completes.
         *
         * @param string $system    The system prompt used.
         * @param string $user_text The user content acted on.
         * @param string $text      The generated text.
         * @param string $provider  Active provider slug.
         */
        do_action( 'mailmint_ai_generated', $system, $user_text, $text, $provider->slug() );

        return $text;
    }
}
