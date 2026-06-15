<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Frontend
 */

namespace Mint\MRM\Internal\Admin;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\DataBase\Models\FormModel;
use MRM\Common\MrmCommon;

/**
 * [Manages plugin's frontend assets]
 *
 * @desc Manages plugin's frontend assets
 * @package /app/Internal/Frontend
 * @since 1.0.0
 */
class FrontendAssets {

	use Singleton;

	/**
	 * Initializes class functionalities
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}


	/**
	 * Load plugin main js file
	 *
	 * @param string $hook Hook suffix of current admin page.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! $this->current_page_has_form() ) {
			return;
		}

		$recaptcha_default    = MrmCommon::recaptcha_default_configuration();
		$recaptcha_raw        = get_option( '_mint_recaptcha_settings', $recaptcha_default );
		$recaptcha_public     = array(
			'enable'       => isset( $recaptcha_raw['enable'] ) ? $recaptcha_raw['enable'] : false,
			'api_version'  => isset( $recaptcha_raw['api_version'] ) ? $recaptcha_raw['api_version'] : 'v2_visible',
			'v2_visible'   => array(
				'site_key' => isset( $recaptcha_raw['v2_visible']['site_key'] ) ? $recaptcha_raw['v2_visible']['site_key'] : '',
			),
			'v3_invisible' => array(
				'site_key' => isset( $recaptcha_raw['v3_invisible']['site_key'] ) ? $recaptcha_raw['v3_invisible']['site_key'] : '',
			),
		);
		wp_enqueue_script(
			MRM_PLUGIN_NAME,
			MRM_DIR_URL . 'assets/frontend/js/frontend.js',
			array( 'jquery' ),
			MRM_VERSION,
			true
		);
		wp_localize_script(
			MRM_PLUGIN_NAME,
			'MRM_Frontend_Vars',
			array(
				'ajaxurl'            => admin_url( 'admin-ajax.php' ),
				// This is a nonce created for front-end form submissions.
				// This is used in the following file: /assets/frontend/js/frontend.js.
				'nonce'              => wp_create_nonce( 'wp_rest' ),
				'rest_api_url'       => get_rest_url(),
				'form_cookies_time'  => apply_filters( 'mailmint_set_form_cookies_time', $this->set_dissmiss_time() ),
				'recaptcha_settings' => $recaptcha_public,

			)
		);
	}

	/**
	 * Gets form dismissal time from wp_options table
	 *
	 * @return false|mixed|void
	 * @since 1.0.0
	 */
	public function set_dissmiss_time() {
		return get_option( '_mailmint_form_dismissed', 7 );
	}


	/**
	 * Load plugin main css file
	 *
	 * @param string $hook Hook suffix of current admin page.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles( $hook ) {
		if ( ! $this->current_page_has_form() ) {
			return;
		}

		wp_enqueue_style(
			MRM_PLUGIN_NAME . '-select2',
			MRM_DIR_URL . 'assets/frontend/css/frontend.css',
			array(),
			MRM_VERSION
		);
	}


	/**
	 * Returns true if the current page will render any Mail Mint form — whether
	 * placed via shortcode, Gutenberg block, or the global form-position settings.
	 * Result is cached in a static variable so the DB query runs at most once per request.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function current_page_has_form() {
		static $result = null;
		if ( null !== $result ) {
			return $result;
		}

		global $post;

		// Preview pages always need form assets so popup/fly-in JS initialises correctly.
		if ( is_a( $post, 'WP_Post' ) && 'mint_preview_page' === $post->post_type ) {
			return $result = true;
		}

		// 1. Shortcodes or block embedded directly in this page's content.
		if ( is_a( $post, 'WP_Post' ) ) {
			$content = $post->post_content;
			if (
				has_shortcode( $content, 'mintmrm' )
				|| has_shortcode( $content, 'optin_confirmation' )
				|| has_shortcode( $content, 'preference_page' )
				|| has_shortcode( $content, 'unsubscribe_confirmation' )
				|| has_shortcode( $content, 'unsubscribe_survey' )
				|| has_block( 'mint/mintform', $post )
			) {
				return $result = true;
			}
		}

		// 2. Globally-placed forms — only relevant on page types the form builder supports.
		if (
			! is_singular( array( 'post', 'page', 'product' ) )
			&& ! is_home()
			&& ! is_front_page()
			&& ! is_archive()
		) {
			return $result = false;
		}

		$forms = FormModel::get_all_form_position();
		if ( empty( $forms['data'] ) ) {
			return $result = false;
		}

		foreach ( $forms['data'] as $data ) {
			if ( empty( $data['form_position'] ) ) {
				continue;
			}

			$placement = json_decode( maybe_unserialize( $data['form_position'] ) );
			if ( ! $placement ) {
				continue;
			}

			// Front page.
			if ( is_front_page() && ! empty( $placement->pages->homepage ) ) {
				return $result = true;
			}

			// Home / blog listing.
			if ( is_home() ) {
				if ( ! empty( $placement->pages->all ) ) {
					return $result = true;
				}
				$selected = ! empty( $placement->pages->selected ) ? array_column( $placement->pages->selected, 'value' ) : array();
				if ( in_array( (string) get_queried_object_id(), $selected ) ) { // phpcs:ignore WordPress.PHP.StrictInArray
					return $result = true;
				}
			}

			// Archive pages.
			if ( is_archive() && $post ) {
				if ( $this->placement_targets_category_archive( $placement, $post->ID )
					|| $this->placement_targets_tag_archive( $placement, $post->ID ) ) {
					return $result = true;
				}
			}

			// Singular pages, posts, products.
			if ( is_singular() && $post ) {
				if ( is_page() && $this->placement_targets_post( $placement, 'pages', $post->ID ) ) {
					return $result = true;
				}
				if ( is_singular( 'post' ) && (
					$this->placement_targets_post( $placement, 'post', $post->ID )
					|| $this->placement_targets_category( $placement, $post->ID )
					|| $this->placement_targets_tag( $placement, $post->ID )
				) ) {
					return $result = true;
				}
				if ( is_singular( 'product' ) && (
					$this->placement_targets_post( $placement, 'product', $post->ID )
					|| $this->placement_targets_category( $placement, $post->ID )
					|| $this->placement_targets_tag( $placement, $post->ID )
				) ) {
					return $result = true;
				}
			}
		}

		return $result = false;
	}


	/**
	 * Whether a form placement targets a specific post/page/product type and ID.
	 *
	 * @param object $placement Form position object.
	 * @param string $key       Property key: 'pages', 'post', or 'product'.
	 * @param int    $post_id   Current post ID.
	 *
	 * @return bool
	 */
	private function placement_targets_post( $placement, $key, $post_id ) {
		if ( ! isset( $placement->$key ) ) {
			return false;
		}
		if ( ! empty( $placement->$key->all ) ) {
			return true;
		}
		$selected = ! empty( $placement->$key->selected ) ? array_column( $placement->$key->selected, 'value' ) : array();
		return in_array( $post_id, $selected ); // phpcs:ignore WordPress.PHP.StrictInArray
	}


	/**
	 * Whether a form placement targets the post's category or product category.
	 *
	 * @param object $placement Form position object.
	 * @param int    $post_id   Current post ID.
	 *
	 * @return bool
	 */
	private function placement_targets_category( $placement, $post_id ) {
		if ( empty( $placement->categories ) ) {
			return false;
		}
		$cats = array_column( $placement->categories, 'value' );
		return has_category( $cats, $post_id ) || has_term( $cats, 'product_cat', $post_id );
	}


	/**
	 * Whether a form placement targets the post's tag or product tag.
	 *
	 * @param object $placement Form position object.
	 * @param int    $post_id   Current post ID.
	 *
	 * @return bool
	 */
	private function placement_targets_tag( $placement, $post_id ) {
		if ( empty( $placement->tags ) ) {
			return false;
		}
		$tags = array_column( $placement->tags, 'value' );
		return has_tag( $tags, $post_id ) || has_term( $tags, 'product_tag', $post_id );
	}


	/**
	 * Whether a form placement targets the current category archive.
	 *
	 * @param object $placement Form position object.
	 * @param int    $post_id   Current post ID.
	 *
	 * @return bool
	 */
	private function placement_targets_category_archive( $placement, $post_id ) {
		if ( ! isset( $placement->category_archives ) ) {
			return false;
		}
		if ( ! empty( $placement->category_archives->all ) ) {
			return true;
		}
		$selected = ! empty( $placement->category_archives->selected ) ? array_column( $placement->category_archives->selected, 'value' ) : array();
		return ! empty( $selected ) && ( has_category( $selected, $post_id ) || has_term( $selected, 'product_cat', $post_id ) );
	}


	/**
	 * Whether a form placement targets the current tag archive.
	 *
	 * @param object $placement Form position object.
	 * @param int    $post_id   Current post ID.
	 *
	 * @return bool
	 */
	private function placement_targets_tag_archive( $placement, $post_id ) {
		if ( ! isset( $placement->tag_archives ) ) {
			return false;
		}
		if ( ! empty( $placement->tag_archives->all ) ) {
			return true;
		}
		$selected = ! empty( $placement->tag_archives->selected ) ? array_column( $placement->tag_archives->selected, 'value' ) : array();
		return ! empty( $selected ) && ( has_tag( $selected, $post_id ) || has_term( $selected, 'product_tag', $post_id ) );
	}


	/**
	 * Get assets URL
	 *
	 * @param string $file File name.
	 * @param string $ext File extension.
	 * @param string $type File type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_url( $file, $ext, $type = 'dist' ) {
		$suffix = '';
		// Potentially enqueue minified JavaScript.
		if ( 'js' === $ext ) {
			$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
			$suffix       = self::should_use_minified_file( $script_debug ) ? '' : '.min';
		}

		return plugins_url( self::get_path( $ext, $type ) . $file . $suffix . '.' . $ext, MRM_FILE );
	}


	/**
	 * Get the Asset path
	 *
	 * @param string $ext File extension.
	 * @param string $type File type.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function get_path( $ext, $type = 'dist' ) {
		if ( 'external' === $type ) {
			return ( 'css' === $ext ) ? MRM_ADMIN_EXTERNAL_CSS_FOLDER : MRM_ADMIN_EXTERNAL_JS_FOLDER;
		}

		return ( 'css' === $ext ) ? MRM_ADMIN_DIST_CSS_FOLDER : MRM_ADMIN_DIST_JS_FOLDER;
	}


	/**
	 * Determine if minified file is served
	 *
	 * @param bool $script_debug Constant variable SCRIPT_DEBUG.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function should_use_minified_file( $script_debug ) {
		return ! $script_debug;
	}


	/**
	 * Check if the current page is CRM page or not
	 *
	 * @param string $hook Hook suffix of current admin page.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function maybe_mrm_page( $hook ) {
		return 'toplevel_page_mrm-admin' === $hook;
	}
}
