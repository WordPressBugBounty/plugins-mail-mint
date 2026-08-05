<?php
/**
 * EmailComposer — builds a campaign email from a constrained content structure.
 *
 * Produces BOTH stored representations of a campaign email in one pass:
 *   - the email-builder JSON tree (same node shapes as the shipped default
 *     templates, so the result opens cleanly in the drag-and-drop editor), and
 *   - a compiled, email-safe HTML document (tables, inline styles) used for
 *     sending and previews — the builder normally compiles MJML client-side,
 *     which a server-side tool cannot do.
 *
 * Callers (the compose-campaign-email MCP tool, and later the AI copilot)
 * never supply raw builder JSON — only the content structure validated here.
 *
 * @package Mint\MRM\Internal\MCP\Helpers
 */

namespace Mint\MRM\Internal\MCP\Helpers;

use MRM\Common\MrmCommon;

defined( 'ABSPATH' ) || exit;

class EmailComposer {

    /**
     * Style presets. Every key can be overridden individually via the
     * style input object.
     */
    private const PRESETS = [
        'clean' => [
            'page_background'    => '#F4F5F7',
            'content_background' => '#FFFFFF',
            'text_color'         => '#1F2937',
            'muted_color'        => '#6B7280',
            'accent_color'       => '#573BFF',
            'button_text_color'  => '#FFFFFF',
            'hero_background'    => '#EEEBFF',
            'hero_text_color'    => '#1F2937',
            'font_family'        => 'Arial, Helvetica, sans-serif',
        ],
        'dark'  => [
            'page_background'    => '#111827',
            'content_background' => '#1F2937',
            'text_color'         => '#F9FAFB',
            'muted_color'        => '#9CA3AF',
            'accent_color'       => '#8B5CF6',
            'button_text_color'  => '#FFFFFF',
            'hero_background'    => '#0B1120',
            'hero_text_color'    => '#F9FAFB',
            'font_family'        => 'Arial, Helvetica, sans-serif',
        ],
        'warm'  => [
            'page_background'    => '#FBF6EE',
            'content_background' => '#FFFFFF',
            'text_color'         => '#3D2C1E',
            'muted_color'        => '#8A7460',
            'accent_color'       => '#C96F2B',
            'button_text_color'  => '#FFFFFF',
            'hero_background'    => '#F3E5CF',
            'hero_text_color'    => '#3D2C1E',
            'font_family'        => 'Georgia, "Times New Roman", serif',
        ],
    ];

    private const SECTION_TYPES = [ 'heading', 'paragraph', 'bullets', 'button', 'image', 'divider', 'spacer' ];

    /**
     * Hosts a model reaches for when it does not know the real site address.
     * Any link pointing at one of these is rewritten to the site's own URL —
     * a campaign that ships with example.com CTAs sends every recipient off
     * the site. Compared case-insensitively, after stripping a "www." prefix.
     */
    private const PLACEHOLDER_HOSTS = [
        'example.com',
        'example.org',
        'example.net',
        'example.edu',
        'yourdomain.com',
        'your-domain.com',
        'yourwebsite.com',
        'your-website.com',
        'yoursite.com',
        'your-site.com',
        'yourstore.com',
        'your-store.com',
        'yourbrand.com',
        'yourcompany.com',
        'mysite.com',
        'mywebsite.com',
        'mystore.com',
        'mybrand.com',
        'domain.com',
        'website.com',
        'site.com',
        'store.com',
        'shop.com',
        'company.com',
        'brand.com',
        'acme.com',
        'placeholder.com',
        'sample.com',
        'demo.com',
        'test.com',
    ];

    /**
     * Substrings that mark an invented host even when the exact domain is not
     * in PLACEHOLDER_HOSTS (e.g. "yourdomain.co.uk", "example-shop.com").
     */
    private const PLACEHOLDER_HOST_FRAGMENTS = [
        'example',
        'yourdomain',
        'your-domain',
        'yoursite',
        'your-site',
        'yourstore',
        'your-store',
        'yourbrand',
        'yourcompany',
        'placeholder',
        'lorem',
    ];

    /**
     * TLDs reserved by RFC 2606/6761 for documentation and testing — never a
     * reachable destination for a real recipient.
     */
    private const PLACEHOLDER_TLDS = [ '.example', '.invalid', '.test', '.localhost' ];

    /**
     * Inline tags a section text value may contain. Merge tags like
     * {{contact.first_name}} are plain text and pass through untouched.
     */
    private const ALLOWED_INLINE_HTML = [
        'a'      => [ 'href' => true, 'target' => true ],
        'strong' => [],
        'b'      => [],
        'em'     => [],
        'i'      => [],
        'u'      => [],
        'span'   => [],
        'br'     => [],
    ];

    /**
     * Validate + normalise content/style input and build both representations.
     *
     * @param array $content Content structure: logo_url, hero{heading,subheading}, sections[], footer_text.
     * @param array $style   Style object: preset + per-key overrides.
     * @return array{json:array,html:string}|\WP_Error
     */
    public static function compose( array $content, array $style = [] ) {
        $palette = self::resolvePalette( $style );

        $normalized = self::normalizeContent( $content );
        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }

        if ( $normalized['hero'] ) {
            $normalized['hero']['image_url'] = self::resolveFallbackImageUrl( $normalized['hero']['image_url'], $palette );
        }

        foreach ( $normalized['sections'] as &$section ) {
            if ( 'image' === $section['type'] && '' === $section['src'] ) {
                $section['src'] = self::resolveFallbackImageUrl( '', $palette );
            }
        }
        unset( $section );

        return [
            'json' => self::buildJsonTree( $normalized, $palette ),
            'html' => self::buildHtml( $normalized, $palette ),
        ];
    }

    /**
     * Expose preset names + section types for tool schemas / discovery.
     */
    public static function capabilities(): array {
        return [
            'style_presets' => array_keys( self::PRESETS ),
            'section_types' => self::SECTION_TYPES,
            'style_keys'    => array_keys( self::PRESETS['clean'] ),
        ];
    }

    // -----------------------------------------------------------------------
    // Validation / normalisation
    // -----------------------------------------------------------------------

    private static function resolvePalette( array $style ): array {
        $preset  = isset( $style['preset'] ) && isset( self::PRESETS[ $style['preset'] ] )
            ? $style['preset']
            : 'clean';
        $palette = self::PRESETS[ $preset ];

        foreach ( $palette as $key => $default ) {
            if ( empty( $style[ $key ] ) || ! is_string( $style[ $key ] ) ) {
                continue;
            }
            if ( 'font_family' === $key ) {
                $palette[ $key ] = sanitize_text_field( $style[ $key ] );
                continue;
            }
            $color = sanitize_hex_color( $style[ $key ] );
            if ( $color ) {
                $palette[ $key ] = $color;
            }
        }

        return $palette;
    }

    /**
     * @return array|\WP_Error
     */
    private static function normalizeContent( array $content ) {
        $include_footer = ! isset( $content['include_footer'] ) || (bool) $content['include_footer'];

        $out = [
            // A logo on an invented host renders as a broken image and, unlike
            // hero/section images, has no generated fallback — drop it instead.
            'logo_url'        => self::resolveImageUrl( (string) ( $content['logo_url'] ?? '' ) ),
            'hero'            => null,
            'sections'        => [],
            'footer_text'     => isset( $content['footer_text'] ) ? self::cleanText( (string) $content['footer_text'] ) : '',
            'include_footer'  => $include_footer,
            'business_name'   => MrmCommon::get_business_name() ?: '{{business.name}}',
            'business_address'=> MrmCommon::get_business_full_address() ?: '{{business.address}}',
        ];

        if ( ! empty( $content['hero'] ) && is_array( $content['hero'] ) ) {
            $heading = self::cleanText( (string) ( $content['hero']['heading'] ?? '' ) );
            if ( '' === $heading ) {
                return MCPHelper::error( 'invalid_content', 'hero.heading must not be empty when hero is provided.' );
            }
            $out['hero'] = [
                'heading'    => $heading,
                'subheading' => self::cleanText( (string) ( $content['hero']['subheading'] ?? '' ) ),
                'image_url'  => self::resolveImageUrl( (string) ( $content['hero']['image_url'] ?? '' ) ),
            ];
        }

        $sections = isset( $content['sections'] ) && is_array( $content['sections'] ) ? $content['sections'] : [];
        if ( empty( $sections ) && empty( $out['hero'] ) ) {
            return MCPHelper::error( 'invalid_content', 'Provide a hero and/or at least one section.' );
        }
        if ( count( $sections ) > 30 ) {
            return MCPHelper::error( 'invalid_content', 'Too many sections (max 30).' );
        }

        foreach ( $sections as $i => $section ) {
            $normalized = self::normalizeSection( is_array( $section ) ? $section : [], sprintf( 'sections[%d]', $i ) );
            if ( is_wp_error( $normalized ) ) {
                return $normalized;
            }
            $out['sections'][] = $normalized;
        }

        return $out;
    }

    /**
     * Validate and sanitize ONE section of the content structure.
     *
     * @param array  $section Raw section as supplied by a caller/model.
     * @param string $label   How to refer to this section in error messages.
     * @return array|\WP_Error
     */
    private static function normalizeSection( array $section, string $label ) {
        if ( empty( $section['type'] ) || ! in_array( $section['type'], self::SECTION_TYPES, true ) ) {
            return MCPHelper::error( 'invalid_content', sprintf( '%s.type must be one of: %s', $label, implode( ', ', self::SECTION_TYPES ) ) );
        }

        switch ( $section['type'] ) {
            case 'heading':
            case 'paragraph':
                $text = self::cleanText( (string) ( $section['text'] ?? '' ) );
                if ( '' === $text ) {
                    return MCPHelper::error( 'invalid_content', sprintf( '%s.text must not be empty.', $label ) );
                }
                return [ 'type' => $section['type'], 'text' => $text ];

            case 'bullets':
                $items = array_values( array_filter( array_map(
                    static function ( $item ) {
                        return self::cleanText( (string) $item );
                    },
                    is_array( $section['items'] ?? null ) ? $section['items'] : []
                ) ) );
                if ( empty( $items ) ) {
                    return MCPHelper::error( 'invalid_content', sprintf( '%s.items must contain at least one non-empty string.', $label ) );
                }
                return [ 'type' => 'bullets', 'items' => $items ];

            case 'button':
                $button_label = self::cleanText( (string) ( $section['label'] ?? '' ), false );
                // Placeholder domains and site-relative paths are repointed at
                // this site before anything is stored — see resolveLinkUrl().
                $url          = self::resolveLinkUrl( (string) ( $section['url'] ?? '' ) );
                if ( '' === $button_label || '' === $url ) {
                    return MCPHelper::error( 'invalid_content', sprintf( '%s button requires label and a valid url.', $label ) );
                }
                return [ 'type' => 'button', 'label' => $button_label, 'url' => $url ];

            case 'image':
                return [
                    'type' => 'image',
                    // May be empty — resolved to a real URL (AI-supplied or generated
                    // fallback) in compose(), same as hero.image_url. Never left broken.
                    // A placeholder host is emptied here so the fallback takes over.
                    'src'  => self::resolveImageUrl( (string) ( $section['src'] ?? '' ) ),
                    'alt'  => sanitize_text_field( (string) ( $section['alt'] ?? '' ) ),
                ];

            case 'divider':
                return [ 'type' => 'divider' ];

            case 'spacer':
            default:
                return [ 'type' => 'spacer', 'height' => min( 120, max( 4, (int) ( $section['height'] ?? 24 ) ) ) ];
        }
    }

    /**
     * Build ONE builder node from a section, for callers that patch an
     * existing template instead of composing a whole email (the AI email
     * editor inserting a block into the email already on screen).
     *
     * @param array $section Section structure ({type, ...}).
     * @param array $palette Palette to style the node with — pass the one
     *                       inferred from the template being edited so the new
     *                       block matches its surroundings.
     * @return array|\WP_Error Builder node.
     */
    public static function composeSection( array $section, array $palette = [] ) {
        $normalized = self::normalizeSection( $section, 'section' );
        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }

        $palette = array_merge( self::PRESETS['clean'], array_intersect_key( $palette, self::PRESETS['clean'] ) );

        if ( 'image' === $normalized['type'] && '' === $normalized['src'] ) {
            $normalized['src'] = self::resolveFallbackImageUrl( '', $palette );
        }

        return self::sectionToNode( $normalized, $palette );
    }

    /**
     * The default palette, as a starting point for callers that infer a
     * palette from an existing template.
     *
     * @param string $preset Preset name; unknown names fall back to 'clean'.
     * @return array
     */
    public static function defaultPalette( string $preset = 'clean' ): array {
        return isset( self::PRESETS[ $preset ] ) ? self::PRESETS[ $preset ] : self::PRESETS['clean'];
    }

    /**
     * Sanitize a text value, optionally keeping a small inline-HTML whitelist.
     */
    private static function cleanText( string $text, bool $allow_inline = true ): string {
        $text = trim( $text );
        if ( '' === $text ) {
            return '';
        }
        if ( ! $allow_inline ) {
            return sanitize_text_field( $text );
        }
        return self::resolveInlineLinks( wp_kses( $text, self::ALLOWED_INLINE_HTML ) );
    }

    // -----------------------------------------------------------------------
    // Link resolution
    // -----------------------------------------------------------------------

    /**
     * Point a caller-supplied link at something that actually works for a
     * recipient.
     *
     * A model composing an email has no reliable knowledge of the site's URL
     * structure, so it falls back to inventing one — "https://example.com/shop"
     * or a bare "/pricing". Both ship broken: the first sends recipients to a
     * domain the site owner does not control, the second has no base to resolve
     * against once the mail leaves the site. This normalises them:
     *
     *   - merge tags ({{link.unsubscribe}}) and non-HTTP schemes (mailto:, tel:)
     *     pass through untouched,
     *   - relative paths become absolute against home_url(),
     *   - placeholder hosts are swapped for this site's host, keeping the
     *     invented path only when it resolves to real content here,
     *   - everything else (a real external URL, or one the user supplied) is
     *     left alone.
     *
     * @param string $url Raw URL as supplied by a caller/model.
     * @return string Sendable URL, or '' when the input was empty.
     */
    public static function resolveLinkUrl( string $url ): string {
        $url = trim( $url );
        if ( '' === $url ) {
            return '';
        }

        // Merge tags are resolved at send time; esc_url_raw would strip the braces.
        if ( false !== strpos( $url, '{{' ) ) {
            return $url;
        }

        // Bare anchors have no meaning in an email client.
        if ( 0 === strpos( $url, '#' ) ) {
            return home_url( '/' );
        }

        $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

        // mailto:, tel:, sms: and friends are used as given.
        if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return esc_url_raw( $url );
        }

        // Protocol-relative ("//host/path") — give it a scheme so it parses.
        if ( '' === $scheme && 0 === strpos( $url, '//' ) ) {
            $url    = ( is_ssl() ? 'https:' : 'http:' ) . $url;
            $scheme = is_ssl() ? 'https' : 'http';
        }

        // No scheme at all: treat it as a path on this site.
        if ( '' === $scheme ) {
            return esc_url_raw( home_url( '/' . ltrim( $url, '/' ) ) );
        }

        $host = self::normalizeHost( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' === $host || ! self::isPlaceholderHost( $host ) ) {
            return esc_url_raw( $url );
        }

        $path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
        if ( '' === $path ) {
            return home_url( '/' );
        }

        // The path was invented alongside the host, so keep it only when it
        // happens to match real content here; otherwise the home page is the
        // safer destination than a guaranteed 404.
        $candidate = home_url( '/' . $path . '/' );
        if ( url_to_postid( $candidate ) > 0 ) {
            return esc_url_raw( $candidate );
        }

        return home_url( '/' );
    }

    /**
     * Sanitize an image URL, rejecting placeholder hosts outright.
     *
     * @param string $url Raw image URL as supplied by a caller/model.
     * @return string The URL, or '' when it is empty or points at a stand-in domain.
     */
    public static function resolveImageUrl( string $url ): string {
        $url = trim( $url );
        if ( '' === $url ) {
            return '';
        }
        $host = self::normalizeHost( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        if ( '' !== $host && self::isPlaceholderHost( $host ) ) {
            return '';
        }
        return esc_url_raw( $url );
    }

    /**
     * Lower-case a host and drop a leading "www." so comparisons are stable.
     */
    private static function normalizeHost( string $host ): string {
        $host = strtolower( trim( $host ) );
        if ( 0 === strpos( $host, 'www.' ) ) {
            $host = substr( $host, 4 );
        }
        return $host;
    }

    /**
     * Whether a host is an invented stand-in rather than a real destination.
     * The site's own host is never a placeholder, even if it happens to look
     * like one (a local example.com dev install, say).
     */
    private static function isPlaceholderHost( string $host ): bool {
        if ( $host === self::normalizeHost( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
            return false;
        }

        if ( in_array( $host, self::PLACEHOLDER_HOSTS, true ) ) {
            return true;
        }

        // A host with no dot ("yoursite", "localhost") cannot be reached from a
        // recipient's inbox.
        if ( false === strpos( $host, '.' ) ) {
            return true;
        }

        foreach ( self::PLACEHOLDER_TLDS as $tld ) {
            if ( substr( $host, -strlen( $tld ) ) === $tld ) {
                return true;
            }
        }

        foreach ( self::PLACEHOLDER_HOST_FRAGMENTS as $fragment ) {
            if ( false !== strpos( $host, $fragment ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run every href in a sanitized inline-HTML fragment through
     * resolveLinkUrl(), so links written inside paragraph text get the same
     * treatment as a button's url.
     *
     * Operates on wp_kses output, where attributes are always rebuilt as
     * href="value" — a plain scan is enough, no pattern matching needed.
     *
     * @param string $html Already-sanitized HTML fragment.
     * @return string Same fragment with every href resolved.
     */
    public static function resolveInlineLinks( string $html ): string {
        if ( false === strpos( $html, 'href="' ) ) {
            return $html;
        }

        $needle = 'href="';
        $offset = 0;

        while ( false !== ( $start = strpos( $html, $needle, $offset ) ) ) {
            $value_start = $start + strlen( $needle );
            $value_end   = strpos( $html, '"', $value_start );
            if ( false === $value_end ) {
                break;
            }

            $original = substr( $html, $value_start, $value_end - $value_start );
            $resolved = self::resolveLinkUrl( html_entity_decode( $original, ENT_QUOTES, 'UTF-8' ) );
            if ( '' === $resolved ) {
                $offset = $value_end + 1;
                continue;
            }

            $resolved = esc_attr( $resolved );
            if ( $resolved === $original ) {
                $offset = $value_end + 1;
                continue;
            }

            $html   = substr_replace( $html, $resolved, $value_start, $value_end - $value_start );
            $offset = $value_start + strlen( $resolved ) + 1;
        }

        return $html;
    }

    // -----------------------------------------------------------------------
    // Builder JSON tree (easy-email node shapes from the default templates)
    // -----------------------------------------------------------------------

    private static function buildJsonTree( array $content, array $p ): array {
        $children = [];

        if ( $content['logo_url'] ) {
            $children[] = self::nodeImage( $content['logo_url'], '', '0px 0px 24px 0px', '180px' );
        }

        if ( $content['hero'] ) {
            $children[] = self::nodeHero( $content['hero'], $p );
        }

        $section_children = [];
        foreach ( $content['sections'] as $section ) {
            $section_children[] = self::sectionToNode( $section, $p );
        }

        if ( $content['include_footer'] ) {
            foreach ( self::buildFooterNodes( $content, $p ) as $node ) {
                $section_children[] = $node;
            }
        }

        if ( ! empty( $section_children ) ) {
            $children[] = [
                'type'       => 'advanced_section',
                'data'       => [ 'value' => [ 'noWrap' => false ] ],
                'attributes' => [
                    'background-color'    => $p['content_background'],
                    'padding'             => '24px 24px 24px 24px',
                    'background-repeat'   => 'repeat',
                    'background-size'     => 'auto',
                    'background-position' => 'top center',
                    'border'              => 'none',
                    'direction'           => 'ltr',
                    'text-align'          => 'center',
                ],
                'children'   => [
                    [
                        'type'       => 'advanced_column',
                        'attributes' => [ 'width' => '100%' ],
                        'data'       => [ 'value' => [] ],
                        'children'   => $section_children,
                    ],
                ],
            ];
        }

        if ( $content['footer_text'] ) {
            $children[] = self::nodeText( $content['footer_text'], [
                'padding'    => '24px 24px 0px 24px',
                'align'      => 'center',
                'color'      => $p['muted_color'],
                'font-size'  => '12px',
                'line-height'=> '1.6',
            ], $p );
        }

        return [
            'type'       => 'page',
            'data'       => [
                'value' => [
                    'breakpoint'     => '480px',
                    'headAttributes' => '',
                    'font-size'      => '14px',
                    'line-height'    => '1.7',
                    'headStyles'     => [],
                    'fonts'          => [],
                    'responsive'     => true,
                    'font-family'    => $p['font_family'],
                    'text-color'     => $p['text_color'],
                ],
            ],
            'attributes' => [
                'background-color' => $p['page_background'],
                'width'            => '600px',
                'css-class'        => 'mjml-body',
            ],
            'children'   => [
                [
                    'type'       => 'advanced_wrapper',
                    'data'       => [ 'value' => [] ],
                    'attributes' => [
                        'background-color' => $p['page_background'],
                        'padding'          => '24px 24px 40px 24px',
                        'border'           => 'none',
                        'direction'        => 'ltr',
                        'text-align'       => 'center',
                    ],
                    'children'   => $children,
                ],
            ],
        ];
    }

    private static function sectionToNode( array $section, array $p ): array {
        switch ( $section['type'] ) {
            case 'heading':
                return self::nodeText( $section['text'], [
                    'padding'     => '8px 0px 8px 0px',
                    'align'       => 'left',
                    'color'       => $p['text_color'],
                    'font-size'   => '24px',
                    'line-height' => '1.3',
                    'font-weight' => '700',
                ], $p );

            case 'paragraph':
                return self::nodeText( $section['text'], [
                    'padding'     => '8px 0px 8px 0px',
                    'align'       => 'left',
                    'color'       => $p['text_color'],
                    'font-size'   => '15px',
                    'line-height' => '1.7',
                ], $p );

            case 'bullets':
                $html = '<ul style="margin:0;padding-left:20px;">';
                foreach ( $section['items'] as $item ) {
                    $html .= '<li style="margin:0 0 8px 0;">' . $item . '</li>';
                }
                $html .= '</ul>';
                return self::nodeText( $html, [
                    'padding'     => '8px 0px 8px 0px',
                    'align'       => 'left',
                    'color'       => $p['text_color'],
                    'font-size'   => '15px',
                    'line-height' => '1.7',
                ], $p );

            case 'button':
                return [
                    'type'       => 'advanced_button',
                    'data'       => [ 'value' => [ 'content' => $section['label'] ] ],
                    'attributes' => [
                        'align'            => 'center',
                        'font-family'      => $p['font_family'],
                        'background-color' => $p['accent_color'],
                        'color'            => $p['button_text_color'],
                        'font-weight'      => '600',
                        'font-style'       => 'normal',
                        'border-radius'    => '6px',
                        'padding'          => '16px 0px 16px 0px',
                        'inner-padding'    => '14px 32px 14px 32px',
                        'font-size'        => '15px',
                        'line-height'      => '1.2',
                        'target'           => '_blank',
                        'vertical-align'   => 'middle',
                        'border'           => 'none',
                        'text-align'       => 'center',
                        'letter-spacing'   => 'normal',
                        'href'             => $section['url'],
                    ],
                    'children'   => [],
                ];

            case 'image':
                return self::nodeImage( $section['src'], $section['alt'], '12px 0px 12px 0px', '100%' );

            case 'divider':
                return [
                    'type'       => 'advanced_divider',
                    'data'       => [ 'value' => [] ],
                    'attributes' => [
                        'align'        => 'center',
                        'border-width' => '1px',
                        'border-style' => 'solid',
                        'border-color' => $p['muted_color'],
                        'padding'      => '12px 0px 12px 0px',
                    ],
                    'children'   => [],
                ];

            case 'spacer':
            default:
                return [
                    'type'       => 'advanced_spacer',
                    'data'       => [ 'value' => [] ],
                    'attributes' => [ 'height' => $section['height'] . 'px', 'padding' => '0px 0px 0px 0px' ],
                    'children'   => [],
                ];
        }
    }

    private static function nodeHero( array $hero, array $p ): array {
        $children = [
            self::nodeText( $hero['heading'], [
                'padding'     => '0px 0px 12px 0px',
                'align'       => 'center',
                'color'       => $p['hero_text_color'],
                'font-size'   => '32px',
                'line-height' => '1.2',
                'font-weight' => '700',
            ], $p, 'text' ),
        ];
        if ( $hero['subheading'] ) {
            $children[] = self::nodeText( $hero['subheading'], [
                'padding'     => '0px 0px 0px 0px',
                'align'       => 'center',
                'color'       => $p['hero_text_color'],
                'font-size'   => '16px',
                'line-height' => '1.5',
            ], $p, 'text' );
        }

        return [
            'type'       => 'advanced_hero',
            'data'       => [ 'value' => [] ],
            'attributes' => [
                'background-color'    => $p['hero_background'],
                'background-position' => 'center center',
                'mode'                => 'fluid-height',
                'padding'             => '40px 24px 40px 24px',
                'vertical-align'      => 'top',
                'background-url'      => $hero['image_url'] ?? '',
            ],
            'children'   => $children,
        ];
    }

    /**
     * Text node. Hero children use the plain 'text' type (as in the default
     * templates); body content uses 'advanced_text'.
     */
    private static function nodeText( string $content, array $attributes, array $p, string $type = 'advanced_text' ): array {
        $attributes['font-family'] = $p['font_family'];
        return [
            'type'       => $type,
            'data'       => [ 'value' => [ 'content' => $content ] ],
            'attributes' => $attributes,
            'children'   => [],
        ];
    }

    private static function nodeImage( string $src, string $alt, string $padding, string $width ): array {
        return [
            'type'       => 'advanced_image',
            'data'       => [ 'value' => [] ],
            'attributes' => [
                'align'   => 'center',
                'height'  => 'auto',
                'padding' => $padding,
                'src'     => $src,
                'alt'     => $alt,
                'width'   => $width,
            ],
            'children'   => [],
        ];
    }

    // -----------------------------------------------------------------------
    // Email-safe HTML document
    // -----------------------------------------------------------------------

    private static function buildHtml( array $content, array $p ): string {
        $font = esc_attr( $p['font_family'] );
        $rows = '';

        if ( $content['logo_url'] ) {
            $rows .= sprintf(
                '<tr><td align="center" style="padding:0 0 24px 0;"><img src="%s" alt="" width="180" style="display:block;max-width:180px;height:auto;border:0;" /></td></tr>',
                esc_url( $content['logo_url'] )
            );
        }

        if ( $content['hero'] ) {
            $sub = '';
            if ( $content['hero']['subheading'] ) {
                $sub = sprintf(
                    '<p style="margin:12px 0 0 0;font-family:%s;font-size:16px;line-height:1.5;color:%s;">%s</p>',
                    $font,
                    esc_attr( $p['hero_text_color'] ),
                    $content['hero']['subheading']
                );
            }
            $hero_style = sprintf( 'background-color:%s;padding:40px 24px;border-radius:8px 8px 0 0;', esc_attr( $p['hero_background'] ) );
            if ( ! empty( $content['hero']['image_url'] ) ) {
                $hero_style .= sprintf(
                    'background-image:url(%s);background-size:cover;background-position:center center;background-repeat:no-repeat;',
                    esc_url( $content['hero']['image_url'] )
                );
            }
            $rows .= sprintf(
                '<tr><td align="center" style="%s">'
                . '<h1 style="margin:0;font-family:%s;font-size:32px;line-height:1.2;font-weight:700;color:%s;">%s</h1>%s'
                . '</td></tr>',
                $hero_style,
                $font,
                esc_attr( $p['hero_text_color'] ),
                $content['hero']['heading'],
                $sub
            );
        }

        $body_rows = '';
        foreach ( $content['sections'] as $section ) {
            $body_rows .= self::sectionToHtml( $section, $p, $font );
        }
        if ( $content['include_footer'] ) {
            $body_rows .= self::buildFooterHtml( $content, $p, $font );
        }
        if ( '' !== $body_rows ) {
            $radius = $content['hero'] ? '0 0 8px 8px' : '8px';
            $rows  .= sprintf(
                '<tr><td style="background-color:%s;padding:24px;border-radius:%s;">'
                . '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0">%s</table>'
                . '</td></tr>',
                esc_attr( $p['content_background'] ),
                $radius,
                $body_rows
            );
        }

        if ( $content['footer_text'] ) {
            $rows .= sprintf(
                '<tr><td align="center" style="padding:24px 24px 0 24px;font-family:%s;font-size:12px;line-height:1.6;color:%s;">%s</td></tr>',
                $font,
                esc_attr( $p['muted_color'] ),
                $content['footer_text']
            );
        }

        return sprintf(
            '<!doctype html><html xmlns="http://www.w3.org/1999/xhtml"><head>'
            . '<meta charset="utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<meta http-equiv="X-UA-Compatible" content="IE=edge" /><title></title></head>'
            . '<body style="margin:0;padding:0;background-color:%1$s;">'
            . '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="background-color:%1$s;">'
            . '<tr><td align="center" style="padding:24px 12px 40px 12px;">'
            // Cap width on a wrapping div (not the table): a table sizes to its
            // content and often won't shrink below it even with max-width, which
            // makes the layout overflow narrow viewports. A max-width div with a
            // width:100%% table shrinks reliably — the same structure MJML emits.
            . '<div style="margin:0 auto;max-width:600px;">'
            . '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="width:100%%;">%2$s</table>'
            . '</div>'
            . '</td></tr></table></body></html>',
            esc_attr( $p['page_background'] ),
            $rows
        );
    }

    private static function sectionToHtml( array $section, array $p, string $font ): string {
        switch ( $section['type'] ) {
            case 'heading':
                return sprintf(
                    '<tr><td style="padding:8px 0;font-family:%s;font-size:24px;line-height:1.3;font-weight:700;color:%s;">%s</td></tr>',
                    $font,
                    esc_attr( $p['text_color'] ),
                    $section['text']
                );

            case 'paragraph':
                return sprintf(
                    '<tr><td style="padding:8px 0;font-family:%s;font-size:15px;line-height:1.7;color:%s;">%s</td></tr>',
                    $font,
                    esc_attr( $p['text_color'] ),
                    $section['text']
                );

            case 'bullets':
                $items = '';
                foreach ( $section['items'] as $item ) {
                    $items .= sprintf( '<li style="margin:0 0 8px 0;">%s</li>', $item );
                }
                return sprintf(
                    '<tr><td style="padding:8px 0;font-family:%s;font-size:15px;line-height:1.7;color:%s;"><ul style="margin:0;padding-left:20px;">%s</ul></td></tr>',
                    $font,
                    esc_attr( $p['text_color'] ),
                    $items
                );

            case 'button':
                return sprintf(
                    '<tr><td align="center" style="padding:16px 0;">'
                    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                    . '<td align="center" style="background-color:%s;border-radius:6px;">'
                    . '<a href="%s" target="_blank" style="display:inline-block;padding:14px 32px;font-family:%s;font-size:15px;font-weight:600;line-height:1.2;color:%s;text-decoration:none;">%s</a>'
                    . '</td></tr></table></td></tr>',
                    esc_attr( $p['accent_color'] ),
                    esc_url( $section['url'] ),
                    $font,
                    esc_attr( $p['button_text_color'] ),
                    $section['label']
                );

            case 'image':
                return sprintf(
                    '<tr><td align="center" style="padding:12px 0;"><img src="%s" alt="%s" width="552" style="display:block;width:100%%;max-width:552px;height:auto;border:0;border-radius:4px;" /></td></tr>',
                    esc_url( $section['src'] ),
                    esc_attr( $section['alt'] )
                );

            case 'divider':
                return sprintf(
                    '<tr><td style="padding:12px 0;"><hr style="margin:0;border:none;border-top:1px solid %s;" /></td></tr>',
                    esc_attr( $p['muted_color'] )
                );

            case 'spacer':
            default:
                return sprintf( '<tr><td style="height:%dpx;line-height:%dpx;font-size:0;">&nbsp;</td></tr>', $section['height'], $section['height'] );
        }
    }

    // -----------------------------------------------------------------------
    // Standard Mail Mint footer (unsubscribe + copyright)
    // -----------------------------------------------------------------------

    /**
     * Build the three JSON nodes that make up the standard footer:
     * divider → unsubscribe/preference links → copyright line.
     * These are appended to the content column so they open correctly in
     * the drag-and-drop builder.
     */
    private static function buildFooterNodes( array $content, array $p ): array {
        $year    = (int) date( 'Y' );
        $name    = esc_html( $content['business_name'] );
        $address = esc_html( $content['business_address'] );

        $copyright = $address
            ? sprintf( '&copy; %d, %s, %s', $year, $name, $address )
            : sprintf( '&copy; %d, %s', $year, $name );

        return [
            [
                'type'       => 'advanced_divider',
                'data'       => [ 'value' => [] ],
                'attributes' => [
                    'align'        => 'center',
                    'border-width' => '1px',
                    'border-style' => 'solid',
                    'border-color' => $p['muted_color'],
                    'padding'      => '24px 0px 0px 0px',
                ],
                'children'   => [],
            ],
            self::nodeText(
                'No longer want to receive these emails?&nbsp; '
                . '<a href="{{link.preference}}" target="_blank" style="color:inherit;text-decoration:underline;">Email Preference</a>'
                . '&nbsp;|&nbsp;'
                . '<a href="{{link.unsubscribe}}" target="_blank" style="color:inherit;text-decoration:underline;">Unsubscribe</a>',
                [
                    'padding'     => '12px 0px 4px 0px',
                    'align'       => 'center',
                    'color'       => $p['muted_color'],
                    'font-size'   => '13px',
                    'line-height' => '1.5',
                ],
                $p
            ),
            self::nodeText(
                $copyright,
                [
                    'padding'     => '4px 0px 12px 0px',
                    'align'       => 'center',
                    'color'       => $p['muted_color'],
                    'font-size'   => '12px',
                    'line-height' => '1.5',
                ],
                $p
            ),
        ];
    }

    /**
     * Build the HTML rows for the standard footer — appended inside the
     * content <td> so they sit within the same white card as the body copy.
     */
    private static function buildFooterHtml( array $content, array $p, string $font ): string {
        $year    = (int) date( 'Y' );
        $name    = esc_html( $content['business_name'] );
        $address = esc_html( $content['business_address'] );

        $copyright = $address
            ? sprintf( '&copy; %d, %s, %s', $year, $name, $address )
            : sprintf( '&copy; %d, %s', $year, $name );

        $muted = esc_attr( $p['muted_color'] );

        return sprintf(
            '<tr><td style="padding:24px 0 0 0;"><hr style="margin:0;border:none;border-top:1px solid %s;" /></td></tr>',
            $muted
        )
        . sprintf(
            '<tr><td align="center" style="padding:12px 0 4px 0;font-family:%s;font-size:13px;line-height:1.5;color:%s;">'
            . 'No longer want to receive these emails?&nbsp; '
            . '<a href="{{link.preference}}" target="_blank" style="color:inherit;text-decoration:underline;">Email Preference</a>'
            . '&nbsp;|&nbsp;'
            . '<a href="{{link.unsubscribe}}" target="_blank" style="color:inherit;text-decoration:underline;">Unsubscribe</a>'
            . '</td></tr>',
            $font,
            $muted
        )
        . sprintf(
            '<tr><td align="center" style="padding:4px 0 12px 0;font-family:%s;font-size:12px;line-height:1.5;color:%s;">%s</td></tr>',
            $font,
            $muted,
            $copyright
        );
    }

    // -----------------------------------------------------------------------
    // Fallback images — real URL if given, otherwise an auto-generated
    // gradient so a hero or image section is never left broken or missing.
    // -----------------------------------------------------------------------

    /**
     * Resolve a hero background or section image: use the supplied URL only
     * if it actually resolves to a real image — the AI is asked never to
     * invent a URL, but it sometimes does anyway (e.g. placeholder domains
     * like "your-store.com"), so a live check is the only way to be sure.
     * Otherwise generate a gradient from the business logo's dominant color
     * (falling back to the resolved style palette if there is no logo or
     * extraction fails).
     */
    private static function resolveFallbackImageUrl( string $ai_supplied_url, array $p ): string {
        if ( '' !== $ai_supplied_url && self::isRealImageUrl( $ai_supplied_url ) ) {
            return $ai_supplied_url;
        }

        $brand_color = self::getBrandLogoColor();
        if ( $brand_color ) {
            $generated = self::generateHeroGradient( $brand_color, $p['accent_color'] );
            if ( $generated ) {
                return $generated;
            }
        }

        return (string) self::generateHeroGradient( $p['hero_background'], $p['accent_color'] );
    }

    /**
     * Verify a URL actually resolves to a loadable image before trusting it
     * in an email. Uses wp_safe_remote_* so hallucinated/attacker-influenced
     * URLs can't be used to probe internal network addresses.
     */
    private static function isRealImageUrl( string $url ): bool {
        if ( ! preg_match( '#^https?://#i', $url ) ) {
            return false;
        }

        // An invented host is never worth a round-trip — and some of them do
        // answer 200 with an image, which would ship a stranger's asset.
        if ( self::isPlaceholderHost( self::normalizeHost( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) ) {
            return false;
        }

        $args     = [ 'timeout' => 4, 'redirection' => 3 ];
        $response = wp_safe_remote_head( $url, $args );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            // Some servers reject HEAD requests — retry with a small ranged GET.
            $response = wp_safe_remote_get( $url, $args + [ 'limit_response_size' => 1024 ] );
            if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                return false;
            }
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        return is_string( $content_type ) && 0 === strpos( $content_type, 'image/' );
    }

    /**
     * Sample the dominant color of the configured business logo, caching the
     * result for a day since the logo rarely changes. Returns null when
     * there's no logo, or extraction is unavailable/fails.
     */
    private static function getBrandLogoColor(): ?string {
        $business = get_option( '_mrm_business_basic_info_setting', [] );
        $logo_url = is_array( $business ) ? (string) ( $business['logo_url'] ?? '' ) : '';
        if ( '' === $logo_url ) {
            return null;
        }

        $cache_key = 'mint_ai_logo_color_' . md5( $logo_url );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return '' === $cached ? null : $cached;
        }

        $color = self::extractDominantColor( $logo_url );
        set_transient( $cache_key, $color ?? '', DAY_IN_SECONDS );

        return $color;
    }

    /**
     * Downsample a remote image and average its non-near-white, non-transparent
     * pixels to approximate the brand's dominant color.
     */
    private static function extractDominantColor( string $url ): ?string {
        if ( ! function_exists( 'imagecreatefromstring' ) ) {
            return null;
        }

        $response = wp_safe_remote_get( $url, [ 'timeout' => 5 ] );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( '' === $body || strlen( $body ) > 5 * MB_IN_BYTES ) {
            return null;
        }

        $image = @imagecreatefromstring( $body );
        if ( ! $image ) {
            return null;
        }

        $size   = 8;
        $sample = imagecreatetruecolor( $size, $size );
        imagealphablending( $sample, false );
        imagesavealpha( $sample, true );
        imagecopyresampled( $sample, $image, 0, 0, 0, 0, $size, $size, imagesx( $image ), imagesy( $image ) );
        imagedestroy( $image );

        $total_r = 0;
        $total_g = 0;
        $total_b = 0;
        $count   = 0;

        for ( $x = 0; $x < $size; $x++ ) {
            for ( $y = 0; $y < $size; $y++ ) {
                $rgba = imagecolorsforindex( $sample, imagecolorat( $sample, $x, $y ) );
                if ( $rgba['alpha'] > 100 ) {
                    continue; // Skip transparent background pixels.
                }
                if ( $rgba['red'] > 245 && $rgba['green'] > 245 && $rgba['blue'] > 245 ) {
                    continue; // Skip near-white canvas pixels.
                }
                $total_r += $rgba['red'];
                $total_g += $rgba['green'];
                $total_b += $rgba['blue'];
                ++$count;
            }
        }
        imagedestroy( $sample );

        if ( 0 === $count ) {
            return null;
        }

        return sprintf( '#%02x%02x%02x', (int) ( $total_r / $count ), (int) ( $total_g / $count ), (int) ( $total_b / $count ) );
    }

    /**
     * Render (or reuse a cached) diagonal-gradient PNG between two colors as
     * the hero background, stored under the uploads dir.
     */
    private static function generateHeroGradient( string $color_from, string $color_to ): ?string {
        if ( ! function_exists( 'imagecreatetruecolor' ) ) {
            return null;
        }

        $from = self::hexToRgb( $color_from );
        $to   = self::hexToRgb( $color_to );
        if ( ! $from || ! $to ) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) ) {
            return null;
        }

        $dir      = trailingslashit( $upload_dir['basedir'] ) . 'mail-mint/ai-hero';
        $filename = 'hero-' . md5( $color_from . $color_to ) . '.png';
        $path     = trailingslashit( $dir ) . $filename;
        $url      = trailingslashit( $upload_dir['baseurl'] ) . 'mail-mint/ai-hero/' . $filename;

        if ( file_exists( $path ) ) {
            return $url;
        }

        if ( ! wp_mkdir_p( $dir ) ) {
            return null;
        }

        $width  = 1200;
        $height = 420;
        $image  = imagecreatetruecolor( $width, $height );
        if ( ! $image ) {
            return null;
        }

        for ( $x = 0; $x < $width; $x++ ) {
            $ratio = $x / $width;
            $r     = (int) ( $from[0] + ( $to[0] - $from[0] ) * $ratio );
            $g     = (int) ( $from[1] + ( $to[1] - $from[1] ) * $ratio );
            $b     = (int) ( $from[2] + ( $to[2] - $from[2] ) * $ratio );
            $color = imagecolorallocate( $image, $r, $g, $b );
            imageline( $image, $x, 0, $x, $height, $color );
        }

        $saved = imagepng( $image, $path );
        imagedestroy( $image );

        return $saved ? $url : null;
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    private static function hexToRgb( string $hex ): ?array {
        $hex = ltrim( $hex, '#' );
        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return null;
        }
        return [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
    }
}
