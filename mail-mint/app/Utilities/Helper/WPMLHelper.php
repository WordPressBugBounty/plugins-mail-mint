<?php
/**
 * WPML compatibility helper.
 *
 * Resolves WooCommerce product/term IDs to their default-language canonical ID
 * and bridges plugin strings into WPML String Translation. Every method is a
 * pure no-op when WPML is not active, so behaviour on non-WPML sites is
 * unchanged.
 *
 * @package Mint\MRM\Utilities\Helper
 * @since 1.24.5
 */

namespace Mint\MRM\Utilities\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPML compatibility helper.
 *
 * @since 1.24.5
 */
class WPMLHelper {

	/**
	 * Default string-translation domain used when registering plugin strings with WPML.
	 *
	 * @var string
	 * @since 1.24.5
	 */
	const STRING_DOMAIN = 'mail-mint';

	/**
	 * Whether WPML (or a compatible plugin exposing its hooks) is active.
	 *
	 * @return bool
	 * @since 1.24.5
	 */
	public static function is_active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || has_filter( 'wpml_object_id' );
	}

	/**
	 * Resolve a post ID (product or product variation) to its default-language ID.
	 *
	 * When WPML is inactive the ID is returned unchanged. When the post type is
	 * not provided it is auto-detected, so callers can pass raw order-item IDs
	 * without knowing whether they are simple products or variations.
	 *
	 * @param int|string  $post_id   The post/product ID derived from WooCommerce.
	 * @param string|null $post_type Optional post type ('product' or 'product_variation').
	 * @return int
	 * @since 1.24.5
	 */
	public static function default_lang_post_id( $post_id, $post_type = null ) {
		$post_id = (int) $post_id;

		if ( ! $post_id || ! self::is_active() ) {
			return $post_id;
		}

		if ( empty( $post_type ) ) {
			$post_type = get_post_type( $post_id );
			if ( empty( $post_type ) ) {
				$post_type = 'product';
			}
		}

		return self::resolve( $post_id, $post_type );
	}

	/**
	 * Resolve a taxonomy term ID to its default-language ID.
	 *
	 * @param int|string $term_id  The term ID (e.g. product_cat / product_tag).
	 * @param string     $taxonomy The taxonomy the term belongs to.
	 * @return int
	 * @since 1.24.5
	 */
	public static function default_lang_term_id( $term_id, $taxonomy ) {
		$term_id = (int) $term_id;

		if ( ! $term_id || ! self::is_active() ) {
			return $term_id;
		}

		return self::resolve( $term_id, $taxonomy );
	}

	/**
	 * Resolve a list of post/product IDs to their default-language IDs.
	 *
	 * @param array       $ids       Post/product IDs.
	 * @param string|null $post_type Optional shared post type for every ID.
	 * @return int[] De-duplicated list of default-language IDs.
	 * @since 1.24.5
	 */
	public static function default_lang_post_ids( array $ids, $post_type = null ) {
		if ( empty( $ids ) || ! self::is_active() ) {
			return array_values( array_unique( array_map( 'intval', $ids ) ) );
		}

		$resolved = array();
		foreach ( $ids as $id ) {
			$resolved[] = self::default_lang_post_id( $id, $post_type );
		}

		return array_values( array_unique( $resolved ) );
	}

	/**
	 * Resolve a list of term IDs to their default-language IDs.
	 *
	 * @param array  $ids      Term IDs.
	 * @param string $taxonomy The taxonomy the terms belong to.
	 * @return int[] De-duplicated list of default-language IDs.
	 * @since 1.24.5
	 */
	public static function default_lang_term_ids( array $ids, $taxonomy ) {
		if ( empty( $ids ) || ! self::is_active() ) {
			return array_values( array_unique( array_map( 'intval', $ids ) ) );
		}

		$resolved = array();
		foreach ( $ids as $id ) {
			$resolved[] = self::default_lang_term_id( $id, $taxonomy );
		}

		return array_values( array_unique( $resolved ) );
	}

	/**
	 * Register a plugin string with WPML String Translation so it can be translated.
	 *
	 * No-op when WPML is inactive.
	 *
	 * @param string $name   Stable, unique string name.
	 * @param string $value  The default-language string value.
	 * @param string $domain Optional string-translation domain.
	 * @return void
	 * @since 1.24.5
	 */
	public static function register_string( $name, $value, $domain = self::STRING_DOMAIN ) {
		if ( '' === (string) $value || ! self::is_active() ) {
			return;
		}

		/**
		 * WPML: register a single string for translation.
		 *
		 * @since 1.24.5
		 */
		do_action( 'wpml_register_single_string', $domain, $name, (string) $value );
	}

	/**
	 * Return the translated value of a previously registered string for the current language.
	 *
	 * Returns the original value unchanged when WPML is inactive.
	 *
	 * @param string $value  The default-language string value.
	 * @param string $name   The string name used at registration.
	 * @param string $domain Optional string-translation domain.
	 * @return string
	 * @since 1.24.5
	 */
	public static function translate_string( $value, $name, $domain = self::STRING_DOMAIN ) {
		if ( ! self::is_active() ) {
			return (string) $value;
		}

		/**
		 * WPML: translate a single registered string into the current language.
		 *
		 * @since 1.24.5
		 */
		return (string) apply_filters( 'wpml_translate_single_string', (string) $value, $domain, $name );
	}

	/**
	 * Ask WPML for the element ID in the site's default language.
	 *
	 * @param int    $element_id   The element (post or term) ID.
	 * @param string $element_type The WPML element type (post type or taxonomy).
	 * @return int Default-language ID, or the original ID when no translation exists.
	 * @since 1.24.5
	 */
	private static function resolve( $element_id, $element_type ) {
		$default_language = apply_filters( 'wpml_default_language', null );

		$resolved = apply_filters( 'wpml_object_id', $element_id, $element_type, true, $default_language );

		return $resolved ? (int) $resolved : (int) $element_id;
	}
}
