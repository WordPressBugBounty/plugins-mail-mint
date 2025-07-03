<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/FomrBuilder
 */

namespace Mint\MRM\Internal\FormBuilder;

use MailMint\App\Internal\FormBuilder\Storage;
use Mint\App\Internal\FormBuilder\MintFormBlock;
use Mint\MRM\DataBase\Models\FormModel;
use Mint\MRM\Internal\ShortCode\ContactForm;
use MRM\Common\MrmCommon;


/**
 * MRM Form Builder Helper class.
 *
 * @since 1.0.0
 * @internal
 */
class FormBuilderHelper {
	/**
	 * Call construct
	 * Call All class for form builder
	 *
	 * @since 5.0.0
	 * @internal
	 */
	public function __construct() {
		$form_block = MintFormBlock::get_instance();
		$form_block->init();

		Storage::get_instance();
		add_action( 'admin_enqueue_scripts', array( $this, 'mrm_block_editor_init' ) );
		if ( !is_admin() ) {
			add_filter( 'the_content', array( $this, 'mrm_render_form_all_sites' ) );
			add_action( 'wp_footer', array( $this, 'maybe_render_forms_in_footer' ), 100 );
		}
		add_action( 'init', array( $this, 'mint_form_builder_preview' ) );
	}

	/**
	 * Maybe render forms in the website footer based on page conditions.
	 *
	 * This function checks if the current page is the home page or front page and, if so, it retrieves and renders forms based on their positioning settings.
	 * Forms are rendered and displayed in the footer if the conditions are met.
	 *
	 * @since 1.5.11
	 */
	public function maybe_render_forms_in_footer() {
		// Check if the current page is the home page or front page.
		if ( is_home() || is_front_page() || is_archive() ) {
			global $post;
			$form_display = '';
			$forms        = FormModel::get_all_form_position();
			$contact_form = new ContactForm();
			foreach ( $forms['data'] as $data ) {
				$form_data      = isset( $data['form_position'] ) ? maybe_unserialize( $data['form_position'] ) : array();
				$form_placement = json_decode( $form_data );

				// Check if the current page is the front page and the form should be displayed there.
				if ( is_front_page() && $this->should_display_form_on_front_page( $form_placement ) ) {
					$form_display .= $contact_form->render_content( $data['id'] );
				}

				// Special case for when the page is not the front page but is the home page.
				if ( ( !is_front_page() && is_home() ) && $this->should_display_form_on_home( $form_placement ) ) {
					$form_display .= $contact_form->render_content( $data['id'] );
				}

				if ( is_category() || is_tax( 'product_cat' ) ) {
					if ( $post && $this->should_display_form_on_category_archive( $form_placement, $post->ID ) ) {
						$form_display .= $contact_form->render_content( $data['id'] );
					}
				}

				if ( is_tag() || is_tax( 'product_cat' ) ) {
					if ( $post && $this->should_display_form_on_tag_archive( $form_placement, $post->ID ) ) {
						$form_display .= $contact_form->render_content( $data['id'] );
					}
				}
			}
			echo $form_display; // phpcs:ignore
		}
	}

	/**
	 * Run For builder in specified page
	 *
	 * @param string $hook get Cusrrent page .
	 * @return void
	 */
	public function mrm_block_editor_init( $hook ) {
		global $current_screen;

		if ( 'toplevel_page_mrm-admin' !== $hook ) {
			return;
		}

		$script_handle     = 'mrm-form-builder-scripts';
		$script_path       = 'build/index.js';
		$script_asset_path = dirname( __FILE__ ) . '/build/index.asset.php';

		$script_asset = file_exists( $script_asset_path ) ? require $script_asset_path : array('dependencies' => array() ); //phpcs:ignore
		$script_url   = plugins_url( $script_path, __FILE__ );
		$version      = isset( $script_asset['version'] ) ? $script_asset['version'] : '';
		wp_enqueue_script( $script_handle, $script_url, $script_asset['dependencies'], $version ); //phpcs:ignore

		$settings = $this->get_block_editor_settings();
		wp_add_inline_script( $script_handle, 'window.getmrmsetting = ' . wp_json_encode( $settings ) . ';' );
		wp_add_inline_script( $script_handle, 'window.mint_general_fields = ' . wp_json_encode( MrmCommon::get_contact_general_fields() ) . ';' );
		wp_localize_script(
			$script_handle,
			'MRM_Vars_Form',
			array(
				'admin_email'                    => get_option( 'admin_email' ),
				'is_mailmint_pro_license_active' => MrmCommon::is_mailmint_pro_license_active(),
			)
		);
		wp_add_inline_script(
			'wp-blocks',
			'wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
		);

		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_style( 'wp-format-library' );

		wp_enqueue_style('mrm-form-builder-styles', //phpcs:ignore
			plugins_url( 'build/index.css', __FILE__ ), //phpcs:ignore
			array( 'wp-edit-blocks' ) //phpcs:ignore
		);
	}

	/**
	 * Get Block editor Setting
	 */
	public function get_block_editor_settings() {
		$theme_color = $this->get_palette_theme_color();

		$allowed_blocks_for_editor = array(
			'core/paragraph',
			'core/heading',
			'core/image',
			'core/media-text',
			'core/columns',
			'core/html',
			'core/spacer',
			'core/subhead',
			'core/group',
			'core/column',
			'core/cover',
			'mrmformfield/email-field-block',
			'mrmformfield/first-name-block',
			'mrmformfield/last-name-block',
			'mrmformfield/mrm-button-block',
			'mrmformfield/country-block',
			'mrmformfield/privacy-policy-block',
		);

		$allowed_blocks = apply_filters( 'mailmint_add_form_builder_blocks_support', $allowed_blocks_for_editor );

		$settings      = array(
			'disableCustomColors'         => get_theme_support( 'disable-custom-colors' ),
			'disableCustomFontSizes'      => get_theme_support( 'disable-custom-font-sizes' ),
			'allowedBlockTypes'           => $allowed_blocks,
			'isRTL'                       => is_rtl(),
			'__experimentalBlockPatterns' => array(),
			'__experimentalFeatures'      => array(
				'appearanceTools' => true,
				'border'          => array(
					'color'  => false,
					'radius' => true,
					'style'  => true,
					'width'  => false,
				),
				'color'           => array(
					'background'       => true,
					'customDuotone'    => false,
					'defaultGradients' => false,
					'defaultPalette'   => false,
					'duotone'          => array(),
					'gradients'        => array(),
					'link'             => false,
					'palette'          => array(
						'theme' => $theme_color['colors'],
					),
					'text'             => true,
				),
				'spacing'         => array(
					'blockGap' => null,
					'margin'   => true,
				),
				'typography'      => array(
					'dropCap'        => false,
					'fontStyle'      => true,
					'fontWeight'     => true,
					'letterSpacing'  => true,
					'textDecoration' => true,
					'textTransform'  => true,
					'fontSize'       => true,
				),
			),
			'disableCustomGradients'      => true,
			'enableCustomLineHeight'      => get_theme_support( 'custom-line-height' ),
			'enableCustomSpacing'         => get_theme_support( 'custom-spacing' ),
			'enableCustomUnits'           => false,
			'keepCaretInsideBlock'        => true,
		);
		$color_palette = current( (array) get_theme_support( 'editor-color-palette' ) );
		if ( false !== $color_palette ) {
			$settings['colors'] = $color_palette;
		} else {
			$settings['colors'] = array();
		}

		if ( $theme_color['font_sizes'] ) {
			$settings['fontSizes'] = $theme_color['font_sizes'];
		} else {
			$settings['fontSizes'] = array();
		}

		return $settings;
	}

	/**
	 * Get Block editor Setting
	 */
	public static function get_palette_theme_color() {
		static $color;
		if ( ! $color ) {
			list($color_palette) = get_theme_support( 'editor-color-palette' );

			if ( empty( $color_palette ) || ! is_array( $color_palette ) || count( $color_palette ) < 2 ) {
				$color_palette = array(
					array(
						'name'  => __( 'Black', 'mrm' ),
						'slug'  => 'black',
						'color' => '#000000',
					),
					array(
						'name'  => __( 'Cyan bluish gray', 'mrm' ),
						'slug'  => 'cyan-bluish-gray',
						'color' => '#abb8c3',
					),
					array(
						'name'  => __( 'White', 'mrm' ),
						'slug'  => 'white',
						'color' => '#ffffff',
					),
					array(
						'name'  => __( 'Pale pink', 'mrm' ),
						'slug'  => 'pale-pink',
						'color' => '#f78da7',
					),
					array(
						'name'  => __( 'Luminous vivid orange', 'mrm' ),
						'slug'  => 'luminous-vivid-orange',
						'color' => '#ff6900',
					),
					array(
						'name'  => __( 'Luminous vivid amber', 'mrm' ),
						'slug'  => 'luminous-vivid-amber',
						'color' => '#fcb900',
					),
					array(
						'name'  => __( 'Light green cyan', 'mrm' ),
						'slug'  => 'light-green-cyan',
						'color' => '#7bdcb5',
					),
					array(
						'name'  => __( 'Vivid green cyan', 'mrm' ),
						'slug'  => 'vivid-green-cyan',
						'color' => '#00d084',
					),
					array(
						'name'  => __( 'Pale cyan blue', 'mrm' ),
						'slug'  => 'pale-cyan-blue',
						'color' => '#8ed1fc',
					),
					array(
						'name'  => __( 'Vivid cyan blue', 'mrm' ),
						'slug'  => 'vivid-cyan-blue',
						'color' => '#0693e3',
					),
					array(
						'name'  => __( 'Vivid purple', 'mrm' ),
						'slug'  => 'vivid-purple',
						'color' => '#9b51e0',
					),
				);
			}

			list($font_sizes) = (array) get_theme_support( 'editor-font-sizes' );

			if ( empty( $font_sizes ) ) {
				$font_sizes = array(
					array(
						'name'      => __( 'Small', 'mrm' ),
						'shortName' => 'S',
						'size'      => 14,
						'slug'      => 'small',
					),
					array(
						'name'      => __( 'Medium', 'mrm' ),
						'shortName' => 'M',
						'size'      => 18,
						'slug'      => 'medium',
					),
					array(
						'name'      => __( 'Large', 'mrm' ),
						'shortName' => 'L',
						'size'      => 24,
						'slug'      => 'large',
					),
					array(
						'name'      => __( 'Larger', 'mrm' ),
						'shortName' => 'XL',
						'size'      => 32,
						'slug'      => 'larger',
					),
				);
			}

			$color = apply_filters(
				'mrm_theme_plate_color',
				array(
					'colors'     => (array) $color_palette,
					'font_sizes' => (array) $font_sizes,
				)
			);
		}

		return $color;
	}


	/**
	 * Placement the Form in content.
	 *
	 * @param string $content All content in the page.
	 * @return string
	 */
	public function mrm_render_form_all_sites( $content ) {
		global $post;
		$form_display = '';
		$forms        = FormModel::get_all_form_position();
		$contact_form = new ContactForm();
		foreach ( $forms['data'] as $data ) {
			if ( !empty( $data['form_position'] ) ) {
				$form_data      = isset( $data['form_position'] ) ? maybe_unserialize( $data['form_position'] ) : array();
				$form_placement = json_decode( $form_data );
				if ( is_page() && $this->should_display_form_on_post( $form_placement, 'pages', $post->ID ) ) {
					$form_display .= $contact_form->render_content( $data['id'] );
				}

				if ( is_singular( array( 'post' ) ) ) {
					if ( $this->should_display_form_on_post( $form_placement, 'post', $post->ID )
						|| $this->should_display_form_on_category( $form_placement, $post->ID )
						|| $this->should_display_form_on_tag( $form_placement, $post->ID )
					) {
						$form_display .= $contact_form->render_content( $data['id'] );
					}
				}

				if ( is_singular( array( 'product' ) ) ) {
					if ( $this->should_display_form_on_post( $form_placement, 'product', $post->ID )
						|| $this->should_display_form_on_category( $form_placement, $post->ID )
						|| $this->should_display_form_on_tag( $form_placement, $post->ID )
					) {
						$form_display .= $contact_form->render_content( $data['id'] );
					}
				}
			}
		}
		$content .= $form_display;
		return $content;
	}

	/**
	 * Form Preview in Admin section.
	 *
	 * @return void
	 */
	public function mint_form_builder_preview() {
		$get = MrmCommon::get_sanitized_get_post();
		if ( isset( $get[ 'get' ]['mint_preview_page'], $get[ 'get' ]['form_preview'] ) && 'mint_form_preview' === $get[ 'get' ]['mint_preview_page'] && $get[ 'get' ]['form_preview'] ) {
			$this->register_form_page_preview_post_type();
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

	/**
	 * Register Post type for Mint Form Preview
	 *
	 * @return void
	 */
	public function register_form_page_preview_post_type() {
		$post_type = 'mint_preview_page';
		$args      = array(
			'labels'              => array(
				'name'          => __( 'Mint mail Page ', 'mrm' ),
				'singular_name' => __( 'Mint mail Page', 'mrm' ),
			),
			'public'              => true,
			'has_archive'         => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'rewrite'             => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
		);
		register_post_type( $post_type, $args );

		$pages = get_posts(
			array(
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post_type'      => 'mint_preview_page',
			)
		);
		if ( empty( $pages ) ) {
			wp_insert_post(
				array(
					'post_status'  => 'publish',
					'post_type'    => 'mint_preview_page',
					'post_author'  => 1,
					'post_content' => '[mintmail_preview_page]',
					'post_title'   => __( 'Mintmail Preview Page', 'mrm' ),
					'post_name'    => 'mint_form_preview',
				)
			);
		}
	}

	/**
	 * Determine whether to display a form on the homepage.
	 *
	 * This function checks the setup configuration to decide whether a form should be displayed on the homepage.
	 *
	 * @access public
	 *
	 * @param stdClass $setup The setup configuration object containing page settings.
	 *
	 * @return bool True if the form should be displayed on the homepage, false otherwise.
	 * @since 1.5.11
	 */
	public function should_display_form_on_home( $setup ) {
		if ( isset( $setup->pages->all ) && $setup->pages->all ) {
			return true;
		}

		$selected_pages = isset( $setup->pages->selected ) ? $setup->pages->selected : array();
		$selected_pages = array_column( $selected_pages, 'value' );

		if ( in_array( (string) get_queried_object_id(), $selected_pages ) ) { // phpcs:ignore
			return true;
		}

		return false;
	}

	/**
	 * Determine whether to display a form on the front page.
	 *
	 * This function checks the setup configuration to decide whether a form should be displayed on the front page.
	 *
	 * @access public
	 *
	 * @param stdClass $setup The setup configuration object containing page settings.
	 *
	 * @return bool True if the form should be displayed on the front page, false otherwise.
	 * @since 1.5.11
	 */
	public function should_display_form_on_front_page( $setup ) {
		if ( isset( $setup->pages->homepage ) && $setup->pages->homepage ) {
			return true;
		}
		return false;
	}

	/**
	 * Determine whether to display a form on a specific post.
	 *
	 * This function checks the setup configuration to decide whether a form should be displayed on a specific post.
	 *
	 * @access public
	 *
	 * @param stdClass $setup     The setup configuration object containing post settings.
	 * @param string   $posts_key The key within the setup configuration object that corresponds to the posts section.
	 * @param int|null $post_id   The ID of the post being checked. If null, the current post ID is used.
	 *
	 * @return bool True if the form should be displayed on the specified post, false otherwise.
	 * @since 1.5.11
	 */
	public function should_display_form_on_post( $setup, $posts_key, $post_id = null ) {
		if ( !isset( $setup->$posts_key ) ) {
			return false;
		}

		if ( isset( $setup->$posts_key->all ) && $setup->$posts_key->all ) {
			return true;
		}
		$selected_pages = isset( $setup->$posts_key->selected ) ? $setup->$posts_key->selected : array();
		$selected_pages = array_column( $selected_pages, 'value' );

		if ( in_array( $post_id, $selected_pages ) ) { // phpcs:ignore
			return true;
		}

		return false;
	}

	/**
	 * Determine if a form should be displayed on a post or product based on assigned categories.
	 *
	 * This function checks if the current post or product belongs to any of the specified categories in the setup.
	 *
	 * @access public
	 *
	 * @param object $setup   The setup object containing category settings.
	 * @param int    $post_id (Optional) The ID of the post or product. Defaults to null (current post/product).
	 *
	 * @return bool True if the form should be displayed on the post or product, false otherwise.
	 * @since 1.5.11
	 */
	public function should_display_form_on_category( $setup, $post_id = null ) {
		// Check if 'categories' key exists.
		if ( !isset( $setup->categories ) || empty( $setup->categories ) ) {
			return false;
		}

		$categories = array_column( $setup->categories, 'value' );

		// Check if there is at least one post category that matches the provided categories.
		if ( has_category( $categories, $post_id ) ) {
			return true;
		}

		// Check if there is at least one product category that matches the provided categories.
		if ( has_term( $categories, 'product_cat', $post_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a form should be displayed on a post or product based on assigned tags.
	 *
	 * This function checks if the current post or product belongs to any of the specified tags in the setup.
	 *
	 * @access public
	 *
	 * @param object $setup   The setup object containing category settings.
	 * @param int    $post_id (Optional) The ID of the post or product. Defaults to null (current post/product).
	 *
	 * @return bool True if the form should be displayed on the post or product, false otherwise.
	 * @since 1.5.11
	 */
	public function should_display_form_on_tag( $setup, $post_id = null ) {
		// Check if 'tags' key exists.
		if ( !isset( $setup->tags ) || empty( $setup->tags ) ) {
			return false;
		}

		$tags = array_column( $setup->tags, 'value' );

		// Check if there is at least one post category that matches the provided tags.
		if ( has_tag( $tags, $post_id ) ) {
			return true;
		}

		// Check if there is at least one product category that matches the provided tags.
		if ( has_term( $tags, 'product_tag', $post_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determines whether a form should be displayed on a category archive page based on setup.
	 *
	 * This function checks if a form should be displayed on a category archive page based on the
	 * configured setup. It considers 'all' setting and selected categories to make the decision.
	 *
	 * @access public
	 *
	 * @param stdClass $setup   The configuration setup object containing category archive settings.
	 * @param int      $post_id The ID of the post being checked for category matches.
	 *
	 * @return bool True if the form should be displayed, false otherwise.
	 * @since 1.5.11
	 */
	public function should_display_form_on_category_archive( $setup, $post_id = null ) {
		// Check if 'category_archives' key exists.
		if ( !isset( $setup->category_archives ) ) {
			return false;
		}

		// Check if 'all' is set to '1'.
		if ( isset( $setup->category_archives->all ) && $setup->category_archives->all ) {
			return true;
		}

		// Get selected categories or use an empty array if not set.
		$selected_categories = isset( $setup->category_archives->selected ) ? $setup->category_archives->selected : array();

		// Check if there are selected categories.
		if ( empty( $selected_categories ) ) {
			return false;
		}

		$selected_categories = array_column( $selected_categories, 'value' );

		// Check if there is at least one post category or product category that matches the selected categories.
		return has_category( $selected_categories, $post_id ) || has_term( $selected_categories, 'product_cat', $post_id );
	}

	/**
	 * Determines whether a form should be displayed on a category archive page based on setup.
	 *
	 * This function checks if a form should be displayed on a category archive page based on the
	 * configured setup. It considers 'all' setting and selected categories to make the decision.
	 *
	 * @access public
	 *
	 * @param stdClass $setup   The configuration setup object containing category archive settings.
	 * @param int      $post_id The ID of the post being checked for category matches.
	 *
	 * @return bool True if the form should be displayed, false otherwise.
	 * @since 1.5.11
	 */
	public function should_display_form_on_tag_archive( $setup, $post_id = null ) {
		// Check if 'tag_archives' key exists.
		if ( !isset( $setup->tag_archives ) ) {
			return false;
		}

		// Check if 'all' is set to '1'.
		if ( isset( $setup->tag_archives->all ) && $setup->tag_archives->all ) {
			return true;
		}

		// Get selected categories or use an empty array if not set.
		$selected_tags = isset( $setup->tag_archives->selected ) ? $setup->tag_archives->selected : array();

		// Check if there are selected categories.
		if ( empty( $selected_tags ) ) {
			return false;
		}

		$selected_tags = array_column( $selected_tags, 'value' );

		// Check if there is at least one post category or product category that matches the selected categories.
		return has_tag( $selected_tags, $post_id ) || has_term( $selected_tags, 'product_tag', $post_id );
	}
}
