<?php
/**
 * AIInit — entry point for the Mint AI module.
 *
 * Provides the provider factory used by settings validation and the agent
 * loop. The AI surface is FREE: any provider works with the user's own key.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Internal\AI\Providers\AnthropicProvider;
use Mint\MRM\Internal\AI\Providers\GeminiProvider;
use Mint\MRM\Internal\AI\Providers\OpenAIProvider;
use Mint\MRM\Internal\AI\Providers\ProviderInterface;
use Mint\MRM\Internal\AI\Providers\WordPressAIProvider;
use Mint\MRM\Internal\AI\Settings\AISettings;

class AIInit {

    /**
     * Build a provider adapter from explicit credentials (used by the
     * settings validate-and-save flow before anything is stored).
     *
     * For wordpress_ai, api_key is ignored.
     */
    public static function makeProvider( string $provider, string $api_key, string $model = '' ): ?ProviderInterface {
        $model = '' !== $model ? $model : ( AISettings::DEFAULT_MODELS[ $provider ] ?? '' );
        switch ( $provider ) {
            case 'anthropic':
                return new AnthropicProvider( $api_key, $model );
            case 'openai':
                return new OpenAIProvider( $api_key, $model );
            case 'gemini':
                return new GeminiProvider( $api_key, $model );
            case 'wordpress_ai':
                return new WordPressAIProvider( '', $model );
        }
        return null;
    }

    /**
     * Build the adapter for the currently connected/active provider.
     *
     * @return ProviderInterface|\WP_Error
     */
    public static function activeProvider() {
        if ( ! AISettings::isEnabled() ) {
            return new \WP_Error( 'ai_disabled', 'The AI Assistant is turned off. Enable it under Mail Mint → Settings → AI.' );
        }
        $provider = AISettings::getActiveProvider();
        if ( '' === $provider ) {
            return new \WP_Error( 'ai_not_connected', 'No AI provider is connected. Connect one under Mail Mint → Settings → AI.' );
        }
        $adapter = self::makeProvider( $provider, AISettings::getApiKey( $provider ), AISettings::getModel( $provider ) );
        return $adapter ?: new \WP_Error( 'ai_not_connected', 'Unknown AI provider configured.' );
    }
}
