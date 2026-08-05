<?php
/**
 * EmailTemplateEditor — surgical edits to an email-builder template tree.
 *
 * The AI panel in the email builder must be able to change ONE thing — a
 * headline, a CTA label, one added paragraph — without touching the rest of
 * the email. Re-composing the whole template from a content structure can
 * never do that: it rewrites every block and throws away anything the user
 * built by hand.
 *
 * So this class works the other way round:
 *   outline()   flattens the live template into a short, addressable list of
 *               editable blocks the model can reason about;
 *   applyOps()  applies the model's edit operations back onto the SAME tree,
 *               leaving every untouched node byte-identical.
 *
 * Block ids survive inserts and deletes: every node is tagged with its
 * original path before any operation runs, so a later op still resolves to the
 * block the model meant when it wrote the plan.
 *
 * @package Mint\MRM\Internal\AI
 */

namespace Mint\MRM\Internal\AI;

use Mint\MRM\Internal\MCP\Helpers\EmailComposer;

defined( 'ABSPATH' ) || exit;

class EmailTemplateEditor {

    /**
     * Transient key holding a node's stable id while ops are applied. Stripped
     * before the patched tree is returned.
     */
    private const ID_KEY = '__mm_ai_id';

    /**
     * Node types that only hold other nodes — outlined as structure, never as
     * an editable block.
     */
    private const CONTAINER_TYPES = [
        'page',
        'wrapper',
        'advanced_wrapper',
        'section',
        'advanced_section',
        'column',
        'advanced_column',
        'group',
        'advanced_group',
    ];

    /**
     * Longest block text handed to the model. Long rich-text blocks are
     * truncated for the outline only — the stored node is never touched unless
     * an op targets it.
     */
    private const OUTLINE_TEXT_LIMIT = 600;

    /**
     * Cap on outlined blocks, so a huge template cannot blow the context
     * window. Beyond this the assistant still edits what it can see.
     */
    private const OUTLINE_BLOCK_LIMIT = 150;

    /**
     * Edit operations, in the vocabulary the model is given.
     */
    private const OPS = [ 'update_text', 'update_button', 'update_image', 'replace', 'delete', 'insert_before', 'insert_after' ];

    /**
     * Flatten a template into the editable blocks the model may address.
     *
     * @param array $template Builder node tree (the editor's `content` value).
     * @return array [ {id, type, text?, url?, src?, alt?} ]
     */
    public static function outline( array $template ): array {
        $blocks = [];
        self::collect( $template, '', $blocks );
        return array_slice( $blocks, 0, self::OUTLINE_BLOCK_LIMIT );
    }

    /**
     * Apply a model-produced edit plan to a template.
     *
     * Unknown ops, ids that no longer resolve, and sections that fail
     * validation are skipped and reported — a partially applicable plan still
     * lands the parts that are valid rather than failing the whole request.
     *
     * @param array $template Builder node tree.
     * @param array $ops      Edit operations.
     * @return array{template:array,applied:int,skipped:array}
     */
    public static function applyOps( array $template, array $ops ): array {
        $palette = self::inferPalette( $template );
        $tagged  = self::tag( $template, '' );
        $applied = 0;
        $skipped = [];

        foreach ( $ops as $op ) {
            if ( ! is_array( $op ) ) {
                continue;
            }

            $name = isset( $op['op'] ) ? (string) $op['op'] : '';
            $id   = isset( $op['id'] ) ? (string) $op['id'] : '';

            if ( ! in_array( $name, self::OPS, true ) ) {
                $skipped[] = sprintf( 'Unknown operation "%s".', $name );
                continue;
            }
            if ( '' === $id ) {
                $skipped[] = sprintf( 'Operation "%s" is missing a block id.', $name );
                continue;
            }

            // Structural ops need the replacement/inserted node built up front,
            // so an invalid section is reported instead of corrupting the tree.
            $node = null;
            if ( in_array( $name, [ 'replace', 'insert_before', 'insert_after' ], true ) ) {
                $section = isset( $op['section'] ) && is_array( $op['section'] ) ? $op['section'] : [];
                $node    = EmailComposer::composeSection( $section, $palette );
                if ( is_wp_error( $node ) ) {
                    $skipped[] = $node->get_error_message();
                    continue;
                }
            }

            $hit    = false;
            $tagged = self::mutate( $tagged, $id, $op, $node, $hit );
            if ( $hit ) {
                ++$applied;
            } else {
                $skipped[] = sprintf( 'Block "%s" was not found.', $id );
            }
        }

        return [
            'template' => self::untag( $tagged ),
            'applied'  => $applied,
            'skipped'  => $skipped,
        ];
    }

    /**
     * Derive a palette from the template being edited, so an inserted block
     * looks like it belongs there instead of like a pasted default.
     *
     * @param array $template Builder node tree.
     * @return array Palette in EmailComposer's shape.
     */
    public static function inferPalette( array $template ): array {
        $palette = EmailComposer::defaultPalette();

        $page_value = isset( $template['data']['value'] ) && is_array( $template['data']['value'] ) ? $template['data']['value'] : [];
        if ( ! empty( $page_value['font-family'] ) ) {
            $palette['font_family'] = sanitize_text_field( (string) $page_value['font-family'] );
        }
        $palette['text_color']      = self::color( $page_value['text-color'] ?? '', $palette['text_color'] );
        $palette['page_background'] = self::color( $template['attributes']['background-color'] ?? '', $palette['page_background'] );

        // First occurrence of each styled node type wins — the email's own
        // accent is far more useful than a preset guess.
        $found = [];
        self::collectStyles( $template, $found );

        if ( isset( $found['button'] ) ) {
            $palette['accent_color']      = self::color( $found['button']['background-color'] ?? '', $palette['accent_color'] );
            $palette['button_text_color'] = self::color( $found['button']['color'] ?? '', $palette['button_text_color'] );
        }
        if ( isset( $found['section'] ) ) {
            $palette['content_background'] = self::color( $found['section']['background-color'] ?? '', $palette['content_background'] );
        }
        if ( isset( $found['hero'] ) ) {
            $palette['hero_background'] = self::color( $found['hero']['background-color'] ?? '', $palette['hero_background'] );
        }
        if ( isset( $found['divider'] ) ) {
            $palette['muted_color'] = self::color( $found['divider']['border-color'] ?? '', $palette['muted_color'] );
        }

        return $palette;
    }

    /**
     * The op vocabulary, for the system prompt that teaches it.
     *
     * @return string[]
     */
    public static function operations(): array {
        return self::OPS;
    }

    // -----------------------------------------------------------------------
    // Outline
    // -----------------------------------------------------------------------

    /**
     * Walk the tree, appending every editable block to $blocks in reading order.
     *
     * @param array  $node   Current node.
     * @param string $path   Path id of the current node ('' for the root).
     * @param array  $blocks Accumulator, by reference.
     * @return void
     */
    private static function collect( array $node, string $path, array &$blocks ) {
        $type = isset( $node['type'] ) ? (string) $node['type'] : '';

        if ( '' !== $path && ! in_array( $type, self::CONTAINER_TYPES, true ) ) {
            $block = self::describe( $node, $path, $type );
            if ( $block ) {
                $blocks[] = $block;
            }
        }

        $children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [];
        foreach ( $children as $index => $child ) {
            if ( is_array( $child ) ) {
                self::collect( $child, '' === $path ? (string) $index : $path . '.' . $index, $blocks );
            }
        }
    }

    /**
     * Describe one node for the outline, or null when it carries nothing the
     * model can meaningfully edit.
     *
     * @param array  $node Node.
     * @param string $path Path id.
     * @param string $type Node type.
     * @return array|null
     */
    private static function describe( array $node, string $path, string $type ) {
        $content    = isset( $node['data']['value']['content'] ) ? (string) $node['data']['value']['content'] : '';
        $attributes = isset( $node['attributes'] ) && is_array( $node['attributes'] ) ? $node['attributes'] : [];

        if ( self::isType( $type, 'button' ) ) {
            return [
                'id'    => $path,
                'type'  => 'button',
                'label' => self::shorten( $content ),
                'url'   => (string) ( $attributes['href'] ?? '' ),
            ];
        }

        if ( self::isType( $type, 'image' ) ) {
            return [
                'id'   => $path,
                'type' => 'image',
                'src'  => (string) ( $attributes['src'] ?? '' ),
                'alt'  => (string) ( $attributes['alt'] ?? '' ),
            ];
        }

        if ( self::isType( $type, 'hero' ) ) {
            return [
                'id'    => $path,
                'type'  => 'hero',
                'image' => (string) ( $attributes['background-url'] ?? '' ),
                'note'  => 'Hero banner. Its heading and subheading are separate text blocks listed below it.',
            ];
        }

        if ( self::isType( $type, 'divider' ) || self::isType( $type, 'spacer' ) ) {
            return [
                'id'   => $path,
                'type' => self::isType( $type, 'divider' ) ? 'divider' : 'spacer',
            ];
        }

        // Everything else that carries text: advanced_text, text, and any
        // custom block storing its copy the same way.
        if ( '' !== trim( $content ) ) {
            return [
                'id'   => $path,
                'type' => 'text',
                'text' => self::shorten( $content ),
            ];
        }

        return null;
    }

    /**
     * Whether a node type is a variant of a base type ('advanced_button' and
     * 'button' are both buttons).
     *
     * @param string $type Node type.
     * @param string $base Base type.
     * @return bool
     */
    private static function isType( string $type, string $base ): bool {
        return $type === $base || $type === 'advanced_' . $base;
    }

    /**
     * Truncate outline text; the underlying node keeps its full content.
     *
     * @param string $text Text.
     * @return string
     */
    private static function shorten( string $text ): string {
        $text = trim( $text );
        return strlen( $text ) > self::OUTLINE_TEXT_LIMIT
            ? substr( $text, 0, self::OUTLINE_TEXT_LIMIT ) . '…'
            : $text;
    }

    // -----------------------------------------------------------------------
    // Applying operations
    // -----------------------------------------------------------------------

    /**
     * Tag every node with its path so ids stay valid after inserts/deletes.
     *
     * @param array  $node Node.
     * @param string $path Path id.
     * @return array Tagged node.
     */
    private static function tag( array $node, string $path ): array {
        if ( '' !== $path ) {
            $node[ self::ID_KEY ] = $path;
        }
        if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
            foreach ( $node['children'] as $index => $child ) {
                if ( is_array( $child ) ) {
                    $node['children'][ $index ] = self::tag( $child, '' === $path ? (string) $index : $path . '.' . $index );
                }
            }
        }
        return $node;
    }

    /**
     * Remove the transient id key from every node.
     *
     * @param array $node Node.
     * @return array Clean node.
     */
    private static function untag( array $node ): array {
        unset( $node[ self::ID_KEY ] );
        if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
            foreach ( $node['children'] as $index => $child ) {
                if ( is_array( $child ) ) {
                    $node['children'][ $index ] = self::untag( $child );
                }
            }
        }
        return $node;
    }

    /**
     * Apply one operation to the first node whose tagged id matches.
     *
     * @param array      $node Node being walked.
     * @param string     $id   Target block id.
     * @param array      $op   Operation.
     * @param array|null $new  Pre-built node for structural ops.
     * @param bool       $hit  Set to true once the target is found.
     * @return array Node with the operation applied to its subtree.
     */
    private static function mutate( array $node, string $id, array $op, $new, bool &$hit ): array {
        if ( ! isset( $node['children'] ) || ! is_array( $node['children'] ) ) {
            return $node;
        }

        $children = [];
        foreach ( $node['children'] as $child ) {
            if ( ! is_array( $child ) ) {
                $children[] = $child;
                continue;
            }

            if ( $hit || ! isset( $child[ self::ID_KEY ] ) || $child[ self::ID_KEY ] !== $id ) {
                $children[] = self::mutate( $child, $id, $op, $new, $hit );
                continue;
            }

            $hit = true;
            switch ( $op['op'] ) {
                case 'delete':
                    break;

                case 'replace':
                    $children[] = $new;
                    break;

                case 'insert_before':
                    $children[] = $new;
                    $children[] = $child;
                    break;

                case 'insert_after':
                    $children[] = $child;
                    $children[] = $new;
                    break;

                default:
                    $children[] = self::updateNode( $child, $op );
                    break;
            }
        }

        $node['children'] = array_values( $children );
        return $node;
    }

    /**
     * Apply an in-place update to a single node, touching only the keys the
     * operation actually supplies.
     *
     * @param array $node Node.
     * @param array $op   Operation.
     * @return array Updated node.
     */
    private static function updateNode( array $node, array $op ): array {
        $type = isset( $node['type'] ) ? (string) $node['type'] : '';

        switch ( $op['op'] ) {
            case 'update_text':
                if ( isset( $op['text'] ) ) {
                    // wp_kses_post, not the composer's narrow inline whitelist:
                    // this text may be replacing rich content a user built by
                    // hand, and stripping their markup would be a silent edit
                    // nobody asked for.
                    $node['data']['value']['content'] = EmailComposer::resolveInlineLinks( wp_kses_post( (string) $op['text'] ) );
                }
                break;

            case 'update_button':
                if ( isset( $op['label'] ) ) {
                    $node['data']['value']['content'] = sanitize_text_field( (string) $op['label'] );
                }
                if ( ! empty( $op['url'] ) ) {
                    // Placeholder domains and relative paths get repointed at
                    // this site, same as a composed button.
                    $node['attributes']['href'] = EmailComposer::resolveLinkUrl( (string) $op['url'] );
                }
                break;

            case 'update_image':
                // A hero carries its picture as a background, every other
                // image node as a src.
                // An invented host resolves to '' — keep whatever picture the
                // node already has rather than swapping in a broken image.
                $src = EmailComposer::resolveImageUrl( (string) ( $op['src'] ?? '' ) );
                if ( '' !== $src ) {
                    if ( self::isType( $type, 'hero' ) ) {
                        $node['attributes']['background-url'] = $src;
                    } else {
                        $node['attributes']['src'] = $src;
                    }
                }
                if ( isset( $op['alt'] ) ) {
                    $node['attributes']['alt'] = sanitize_text_field( (string) $op['alt'] );
                }
                break;
        }

        return $node;
    }

    // -----------------------------------------------------------------------
    // Palette inference
    // -----------------------------------------------------------------------

    /**
     * Record the attributes of the first button/section/hero/divider found.
     *
     * @param array $node  Node.
     * @param array $found Accumulator, by reference.
     * @return void
     */
    private static function collectStyles( array $node, array &$found ) {
        $type       = isset( $node['type'] ) ? (string) $node['type'] : '';
        $attributes = isset( $node['attributes'] ) && is_array( $node['attributes'] ) ? $node['attributes'] : [];

        foreach ( [ 'button', 'section', 'hero', 'divider' ] as $base ) {
            if ( ! isset( $found[ $base ] ) && self::isType( $type, $base ) ) {
                $found[ $base ] = $attributes;
            }
        }

        $children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [];
        foreach ( $children as $child ) {
            if ( is_array( $child ) ) {
                self::collectStyles( $child, $found );
            }
        }
    }

    /**
     * A valid hex colour from a node attribute, or the fallback.
     *
     * @param mixed  $value    Attribute value.
     * @param string $fallback Palette default.
     * @return string
     */
    private static function color( $value, string $fallback ): string {
        $color = is_string( $value ) ? sanitize_hex_color( trim( $value ) ) : null;
        return $color ? $color : $fallback;
    }
}
