<?php
/**
 * SchemaTranslator — adapts ability JSON Schemas per provider.
 *
 * Anthropic and OpenAI accept the ability schemas as-is. Gemini's
 * functionDeclarations accept only a restricted OpenAPI-style subset, so
 * unsupported keywords are stripped recursively.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI\Providers;

defined( 'ABSPATH' ) || exit;

class SchemaTranslator {

    /**
     * Keys Gemini accepts inside a schema node.
     */
    private const GEMINI_ALLOWED = [ 'type', 'description', 'properties', 'required', 'items', 'enum', 'nullable', 'format' ];

    private const GEMINI_ALLOWED_FORMATS = [ 'enum', 'date-time' ];

    /**
     * Sanitize a JSON Schema for Gemini functionDeclarations.parameters.
     */
    public static function forGemini( array $schema ): array {
        $clean = [];
        foreach ( $schema as $key => $value ) {
            if ( ! in_array( $key, self::GEMINI_ALLOWED, true ) ) {
                continue;
            }
            if ( 'format' === $key && ! in_array( $value, self::GEMINI_ALLOWED_FORMATS, true ) ) {
                continue;
            }
            if ( 'properties' === $key && is_array( $value ) ) {
                $props = [];
                foreach ( $value as $prop => $prop_schema ) {
                    $props[ $prop ] = is_array( $prop_schema ) ? self::forGemini( $prop_schema ) : $prop_schema;
                }
                // Gemini rejects empty properties objects on type:object nodes.
                if ( ! empty( $props ) ) {
                    $clean['properties'] = $props;
                }
                continue;
            }
            if ( 'items' === $key && is_array( $value ) ) {
                $clean['items'] = self::forGemini( $value );
                continue;
            }
            $clean[ $key ] = $value;
        }

        // An object schema with no properties confuses Gemini — degrade gracefully.
        if ( ( $clean['type'] ?? '' ) === 'object' && empty( $clean['properties'] ) ) {
            $clean['properties'] = [ '_' => [ 'type' => 'string', 'description' => 'Unused placeholder.' ] ];
        }

        return $clean;
    }
}
