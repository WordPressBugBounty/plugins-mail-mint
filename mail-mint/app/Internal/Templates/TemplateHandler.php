<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Templates
 */

namespace Mint\MRM\Internal\Templates;

use Mint\Mrm\Internal\Traits\Singleton;

/**
 * Handle the templates for email and forms
 *
 * @package /app/Internal/Templates
 * @since 1.0.0
 */
class TemplateHandler {

	use Singleton;

	/**
	 * Init function
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'after_setup_theme', array( $this, 'register_mrm_template_after_page_setup' ) );
		add_filter( 'template_include', array( $this, 'mrm_template_select' ), 99 );
	}

	/**
	 * After theme setup this function register Mail mint Template.
	 *
	 * @return void
	 * @since 1.5.14
	 */
	public function register_mrm_template_after_page_setup() {
		add_filter( 'theme_page_templates', array( $this, 'register_mrm_templates' ), 10, 3 );
	}

	/**
	 * Page template array
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function mrm_page_template_array() {
		$templates = array();

		$templates[ 'template-preference-page.php' ]  = 'Preference Page';
		$templates[ 'template-subscribe-page.php' ]   = 'Subscribe Page';
		$templates[ 'template-unsubscribe-page.php' ] = 'Unsubscribe Page';

		return $templates;
	}

	/**
	 * Register templates on theme with existing page templates
	 *
	 * @param mixed $page_templates page templates.
	 * @param mixed $theme theme.
	 * @param mixed $post post.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function register_mrm_templates( $page_templates, $theme, $post ) {
		$all_templates = $this->mrm_page_template_array();

		foreach ( $all_templates as $key => $template ) {
			$is_template_exist = locate_template(
				array( 'mrm-templates/' . $key )
			);

			if ( empty( $is_template_exist ) ) {
				$page_templates[ $key ] = $template;
			}
		}

		return $page_templates;
	}

	/**
	 * Page Template Select
	 *
	 * @param mixed $template template.
	 *
	 * @since 1.0.0
	 */
	public function mrm_template_select( $template ) {
		global $post, $wp_query, $wpdb;

		if ( isset( $post->ID ) ) {
			$page_template_slug = get_page_template_slug( $post->ID );
			$all_templates      = $this->mrm_page_template_array();

			if ( isset( $all_templates[ $page_template_slug ] ) ) {
				$template = locate_template(
					array( 'mrm-templates/' . $page_template_slug )
				);

				if ( empty( $template ) ) {
					$template = MRM_DIR_PATH . '/mrm-templates/' . $page_template_slug;
				}
			}
		}

		return $template;
	}
}
