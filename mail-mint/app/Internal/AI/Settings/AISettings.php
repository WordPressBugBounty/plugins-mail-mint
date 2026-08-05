<?php
/**
 * AISettings — storage for AI provider connections.
 *
 * API keys are encrypted at rest (AES-256-CBC, key derived from the site's
 * auth salts) and are never returned to the frontend after save — only a
 * masked tail for display.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Settings;

defined( 'ABSPATH' ) || exit;

class AISettings {

    public const OPTION_KEY = '_mrm_ai_settings';

    public const PROVIDERS = [ 'anthropic', 'openai', 'gemini', 'wordpress_ai' ];

    /** Providers that need an API key. wordpress_ai uses the site-level WP AI Services. */
    public const REQUIRES_KEY = [ 'anthropic', 'openai', 'gemini' ];

    public const DEFAULT_MODELS = [
        'anthropic'     => 'claude-opus-4-8',
        'openai'        => 'gpt-4o',
        'gemini'        => 'gemini-2.0-flash',
        'wordpress_ai'  => 'auto',
    ];

    /**
     * Selectable models per provider, shown in the settings UI dropdown.
     * The first item is the default.
     */
    public const MODEL_LISTS = [
        'anthropic' => [
            [ 'id' => 'claude-opus-4-8',          'label' => 'Claude Opus 4.8 (Default)' ],
            [ 'id' => 'claude-sonnet-4-6',         'label' => 'Claude Sonnet 4.6' ],
            [ 'id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5' ],
            [ 'id' => 'claude-fable-5',            'label' => 'Claude Fable 5' ],
            [ 'id' => 'claude-opus-4-6',           'label' => 'Claude Opus 4.6' ],
        ],
        'openai' => [
            [ 'id' => 'gpt-4o',       'label' => 'GPT-4o (Default)' ],
            [ 'id' => 'gpt-4o-mini',  'label' => 'GPT-4o Mini' ],
            [ 'id' => 'gpt-4.1',      'label' => 'GPT-4.1' ],
            [ 'id' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 Mini' ],
            [ 'id' => 'gpt-4.1-nano', 'label' => 'GPT-4.1 Nano' ],
            [ 'id' => 'o4-mini',      'label' => 'o4 Mini' ],
            [ 'id' => 'o3',           'label' => 'o3' ],
        ],
        'gemini' => [
            [ 'id' => 'gemini-2.0-flash',      'label' => 'Gemini 2.0 Flash (Default)' ],
            [ 'id' => 'gemini-2.5-flash',      'label' => 'Gemini 2.5 Flash' ],
            [ 'id' => 'gemini-2.5-pro',        'label' => 'Gemini 2.5 Pro' ],
            [ 'id' => 'gemini-2.5-flash-lite', 'label' => 'Gemini 2.5 Flash Lite' ],
            [ 'id' => 'gemini-2.0-flash-lite', 'label' => 'Gemini 2.0 Flash Lite' ],
        ],
        'wordpress_ai' => [
            [ 'id' => 'auto', 'label' => 'WordPress Default Model' ],
        ],
    ];

    /**
     * Raw settings array (keys stay encrypted).
     */
    public static function all(): array {
        $settings = get_option( self::OPTION_KEY, [] );
        return is_array( $settings ) ? $settings : [];
    }

    public static function getActiveProvider(): string {
        $settings = self::all();
        $active   = $settings['active_provider'] ?? '';
        return in_array( $active, self::PROVIDERS, true ) && self::isConnected( $active ) ? $active : '';
    }

    /**
     * Master on/off switch for the AI Assistant. Disabled by default — the
     * admin opts in explicitly. Toggling off leaves provider connections
     * untouched so they survive a later re-enable.
     */
    public static function isEnabled(): bool {
        $settings = self::all();
        return ! empty( $settings['enabled'] );
    }

    public static function setEnabled( bool $enabled ): void {
        $settings            = self::all();
        $settings['enabled'] = $enabled;
        update_option( self::OPTION_KEY, $settings, false );
    }

    public static function isConnected( string $provider ): bool {
        $settings = self::all();
        if ( in_array( $provider, self::REQUIRES_KEY, true ) ) {
            return ! empty( $settings['providers'][ $provider ]['key'] );
        }
        // wordpress_ai is "connected" when explicitly activated (no key required).
        return ! empty( $settings['providers'][ $provider ]['connected'] );
    }

    public static function getModel( string $provider ): string {
        $settings = self::all();
        $model    = $settings['providers'][ $provider ]['model'] ?? '';
        return is_string( $model ) && '' !== $model ? $model : ( self::DEFAULT_MODELS[ $provider ] ?? '' );
    }

    /**
     * Decrypted API key for a provider, or '' when not connected / no key needed.
     */
    public static function getApiKey( string $provider ): string {
        if ( ! in_array( $provider, self::REQUIRES_KEY, true ) ) {
            return '';
        }
        $settings = self::all();
        $stored   = $settings['providers'][ $provider ]['key'] ?? '';
        return is_string( $stored ) && '' !== $stored ? self::decrypt( $stored ) : '';
    }

    /**
     * Custom instructions text to inject into every system prompt.
     */
    public static function getCustomInstructions(): string {
        $settings = self::all();
        $text     = $settings['custom_instructions'] ?? '';
        return is_string( $text ) ? $text : '';
    }

    public static function saveCustomInstructions( string $text ): void {
        $settings                      = self::all();
        $settings['custom_instructions'] = sanitize_textarea_field( $text );
        update_option( self::OPTION_KEY, $settings, false );
    }

    /**
     * Store a provider connection (key encrypted) and mark it active.
     * For wordpress_ai, api_key is ignored — only validates availability.
     *
     * @return true|\WP_Error
     */
    public static function connect( string $provider, string $api_key, string $model = '' ) {
        if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
            return new \WP_Error( 'invalid_provider', 'Unknown provider.' );
        }

        $settings = self::all();

        if ( ! in_array( $provider, self::REQUIRES_KEY, true ) ) {
            // wordpress_ai — no key storage.
            $settings['providers'][ $provider ] = [
                'connected' => true,
                'model'     => '' !== $model ? sanitize_text_field( $model ) : self::DEFAULT_MODELS[ $provider ],
            ];
            $settings['active_provider'] = $provider;
            // Connecting a provider opts the admin into the feature — enable it
            // so the settings toggle and dashboard reflect the connection.
            $settings['enabled'] = true;
            update_option( self::OPTION_KEY, $settings, false );
            return true;
        }

        $encrypted = self::encrypt( $api_key );
        if ( '' === $encrypted ) {
            return new \WP_Error( 'encryption_failed', 'Could not encrypt the API key (openssl unavailable).' );
        }

        $settings['providers'][ $provider ] = [
            'key'   => $encrypted,
            'model' => '' !== $model ? sanitize_text_field( $model ) : self::DEFAULT_MODELS[ $provider ],
        ];
        $settings['active_provider'] = $provider;
        // Connecting a provider opts the admin into the feature — enable it
        // so the settings toggle and dashboard reflect the connection.
        $settings['enabled'] = true;
        update_option( self::OPTION_KEY, $settings, false );
        return true;
    }

    public static function disconnect( string $provider ): void {
        $settings = self::all();
        unset( $settings['providers'][ $provider ] );
        if ( ( $settings['active_provider'] ?? '' ) === $provider ) {
            $settings['active_provider'] = '';
            foreach ( self::PROVIDERS as $candidate ) {
                if ( self::isConnected( $candidate ) ) {
                    $settings['active_provider'] = $candidate;
                    break;
                }
            }
        }
        update_option( self::OPTION_KEY, $settings, false );
    }

    public static function setActiveProvider( string $provider ): bool {
        if ( ! self::isConnected( $provider ) ) {
            return false;
        }
        $settings                    = self::all();
        $settings['active_provider'] = $provider;
        $settings['enabled']         = true;
        update_option( self::OPTION_KEY, $settings, false );
        return true;
    }

    /**
     * Update the stored model for a connected provider without re-entering the key.
     */
    public static function updateModel( string $provider, string $model ): bool {
        if ( ! self::isConnected( $provider ) ) {
            return false;
        }
        $settings = self::all();
        $settings['providers'][ $provider ]['model'] = sanitize_text_field( $model );
        update_option( self::OPTION_KEY, $settings, false );
        return true;
    }

    /**
     * Frontend-safe view: connection status + masked key tail per provider.
     */
    public static function publicState(): array {
        $providers = [];
        foreach ( self::PROVIDERS as $provider ) {
            $key       = self::getApiKey( $provider );
            $connected = self::isConnected( $provider );
            $providers[ $provider ] = [
                'connected'    => $connected,
                'masked_key'   => ( '' !== $key ) ? '••••' . substr( $key, -4 ) : '',
                'model'        => self::getModel( $provider ),
                'model_list'   => self::MODEL_LISTS[ $provider ] ?? [],
                'requires_key' => in_array( $provider, self::REQUIRES_KEY, true ),
            ];
        }
        return [
            'enabled'             => self::isEnabled(),
            'active_provider'     => self::getActiveProvider(),
            'providers'           => $providers,
            'custom_instructions' => self::getCustomInstructions(),
        ];
    }

    // -----------------------------------------------------------------------
    // Crypto
    // -----------------------------------------------------------------------

    private static function encryptionKey(): string {
        $salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' );
        if ( '' === $salt ) {
            $salt = wp_salt( 'auth' );
        }
        return hash( 'sha256', 'mail-mint-ai|' . $salt, true );
    }

    private static function encrypt( string $plaintext ): string {
        if ( '' === $plaintext || ! function_exists( 'openssl_encrypt' ) ) {
            return '';
        }
        $iv     = random_bytes( 16 );
        $cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', self::encryptionKey(), OPENSSL_RAW_DATA, $iv );
        return false === $cipher ? '' : base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    private static function decrypt( string $stored ): string {
        if ( '' === $stored || ! function_exists( 'openssl_decrypt' ) ) {
            return '';
        }
        $raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        if ( false === $raw || strlen( $raw ) <= 16 ) {
            return '';
        }
        $plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::encryptionKey(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
        return false === $plain ? '' : $plain;
    }
}
