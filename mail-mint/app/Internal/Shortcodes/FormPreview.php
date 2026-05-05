<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Shortcodes
 */

namespace Mint\MRM\Internal\ShortCode;

use Mint\MRM\DataBase\Models\FormModel;
use MRM\Common\MrmCommon;

/**
 * [Manages plugin's contact form shortcodes]
 *
 * @desc Manages plugin's contact form shortcodes
 * @package /app/Internal/Shortcodes
 * @since 1.0.0
 */
class FormPreview {

	/**
	 * Shortcode attributes
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $attributes = array();


	/**
	 * Initializes class functionalities
	 *
	 * @param array $attributes Shortcode attributes.
	 * @since 1.0.0
	 */
	public function __construct( $attributes = array() ) {
		$this->attributes = $this->parse_attributes( $attributes );
	}


	/**
	 * Get shortcode attributes.
	 *
	 * @return array
	 * @since  1.0.0
	 */
	public function get_attributes() {
		return $this->attributes;
	}


	/**
	 * Parses shortcode attributes
	 *
	 * @param array $attributes Shortcode attributes.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function parse_attributes( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$attributes
		);

		return $attributes;
	}


	/**
	 * Get wrapper classes
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function get_wrapper_classes() {
		return array();
	}


	/**
	 * Preview of opt-in form.
	 *
	 * Reads the unsaved form state stored by preview_editor API endpoint from a
	 * transient (keyed by form_id). Falls back to the saved database record when
	 * the transient has expired or a form_id is not present.
	 *
	 * The output wraps the form in a realistic simulated page so that positioned
	 * placements (popup, fly-in, fixed bar) render correctly in the iframe — the
	 * same approach MailPoet uses by rendering actual post content as the backdrop.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_content() {
		$param   = MrmCommon::get_sanitized_get_post();
		$get     = ! empty( $param['get'] ) ? $param['get'] : array();
		$form_id = isset( $get['mint_preview_form_id'] ) ? absint( $get['mint_preview_form_id'] ) : 0;

		// Legacy template-gallery path: settings passed via URL param, blocks read from localStorage.
		if ( ! $form_id && isset( $_GET['mint-form-setting'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $this->get_legacy_template_preview();
		}

		// Try to load unsaved preview data from transient.
		$preview_data = $form_id ? get_transient( 'mint_form_preview_' . $form_id ) : false;

		if ( is_array( $preview_data ) ) {
			$form_body    = isset( $preview_data['form_body'] ) ? $preview_data['form_body'] : '';
			$meta_fields  = isset( $preview_data['meta_fields'] ) ? $preview_data['meta_fields'] : array();
			$form_setting = isset( $meta_fields['settings'] ) ? $meta_fields['settings'] : '{}';
			$form_setting = is_string( $form_setting ) ? json_decode( htmlspecialchars_decode( $form_setting ) ) : $form_setting;
		} elseif ( $form_id ) {
			// Transient expired — fall back to saved DB record.
			$form_data    = FormModel::get( $form_id );
			$form_body    = isset( $form_data['form_body'] ) ? $form_data['form_body'] : '';
			$get_setting  = FormModel::get_meta( $form_id );
			$form_setting = isset( $get_setting['meta_fields']['settings'] ) ? $get_setting['meta_fields']['settings'] : '{}';
			$form_setting = json_decode( $form_setting );
		} else {
			return '<div style="padding:40px;text-align:center;color:#6b7280;">' . esc_html__( 'Preview is not available. Please try again.', 'mrm' ) . '</div>';
		}

		$form_placement = ! empty( $form_setting->settings->form_layout->form_position ) ? $form_setting->settings->form_layout->form_position : 'default';
		$form_animation = '';
		if ( 'default' !== $form_placement ) {
			$form_animation = ! empty( $form_setting->settings->form_layout->form_animation ) ? $form_setting->settings->form_layout->form_animation : '';
		}

		$form_close_button_color     = ! empty( $form_setting->settings->form_layout->close_button_color ) ? $form_setting->settings->form_layout->close_button_color : '#fff';
		$form_close_background_color = ! empty( $form_setting->settings->form_layout->close_background_color ) ? $form_setting->settings->form_layout->close_background_color : '#000';
		$exit_intent_enabled         = ! empty( $form_setting->settings->exit_intent->enable ) ? $form_setting->settings->exit_intent->enable : false;
		$custom_css                  = ! empty( $form_setting->settings->custom_css ) ? $form_setting->settings->custom_css : '';

		// Parse and render Gutenberg blocks from form_body.
		$blocks     = parse_blocks( $form_body );
		$block_html = '';
		$class      = '';
		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'], array( 'core/columns', 'core/group' ), true ) ) {
				if ( isset( $block['attrs']['style']['color']['background'] ) || isset( $block['attrs']['backgroundColor'] ) ) {
					$class = 'custom-background';
				}
			}
			if ( 'core/cover' === $block['blockName'] ) {
				if ( isset( $block['attrs']['customOverlayColor'] ) || isset( $block['attrs']['url'] ) || isset( $block['attrs']['overlayColor'] ) ) {
					$class = 'custom-background';
				}
			}
			$block_html .= render_block( $block );
		}

		// Fetch a real post to use as the page backdrop, matching MailPoet's approach.
		$backdrop_post    = '';
		$backdrop_title   = esc_html__( 'Sample page to preview your form', 'mrm' );
		$backdrop_posts   = get_posts( array( 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		if ( ! empty( $backdrop_posts ) ) {
			$backdrop_title = esc_html( get_the_title( $backdrop_posts[0] ) );
			$post_content   = apply_filters( 'the_content', $backdrop_posts[0]->post_content );
			$backdrop_post  = wp_kses_post( $post_content );
		}

		$is_default = ( 'default' === $form_placement );

		$output = '';
		ob_start();
		?>
		<style>
			/* Reset the page so the preview fills the iframe cleanly */
			html, body {
				margin: 0;
				padding: 0;
			}
			/* Simulated page backdrop */
			.mrm-preview-page {
				min-height: 100vh;
				background: #fff;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				color: #333;
				line-height: 1.7;
			}
			.mrm-preview-page-inner {
				max-width: 860px;
				margin: 0 auto;
				padding: 40px 24px 80px;
			}
			.mrm-preview-page-title {
				font-size: 28px;
				font-weight: 700;
				margin: 0 0 20px;
				color: #111;
			}
			.mrm-preview-page-body p {
				margin: 0 0 16px;
				color: #555;
				font-size: 15px;
			}
			<?php if ( ! empty( $custom_css ) ) : ?>
				<?php echo wp_strip_all_tags( $custom_css ); ?>
			<?php endif; ?>
		</style>

		<div class="mrm-preview-page">
			<div class="mrm-preview-page-inner">
				<h1 class="mrm-preview-page-title"><?php echo $backdrop_title; // phpcs:ignore ?></h1>

				<?php if ( $is_default ) : ?>
					<?php /* Inline forms render in content flow */ ?>
					<div class="mintmrm" data-is-preview="1">
						<div id="mrm-<?php echo esc_attr( $form_placement ); ?>" class="mrm-form-wrapper mrm-<?php echo esc_attr( $form_placement ); ?>" data-form-id="preview">
							<div class="mrm-form-wrapper-inner <?php echo esc_attr( $class ); ?>">
								<div class="mrm-form-overflow">
									<form class="mrm-form" method="post" id="mrm-form">
										<?php echo wp_kses( $block_html, MrmCommon::wp_kseser_for_contact() ); ?>
									</form>
								</div>
							</div>
						</div>
					</div>
					<div class="mrm-preview-page-body">
						<?php if ( $backdrop_post ) : ?>
							<?php echo $backdrop_post; // phpcs:ignore ?>
						<?php else : ?>
							<p><?php esc_html_e( 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!', 'mrm' ); ?></p>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<?php /* Popup / Fly-in / Fixed — render page content first, form overlays on top */ ?>
					<div class="mrm-preview-page-body">
						<?php if ( $backdrop_post ) : ?>
							<?php echo $backdrop_post; // phpcs:ignore ?>
						<?php else : ?>
							<p><?php esc_html_e( 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!', 'mrm' ); ?></p>
						<?php endif; ?>
					</div>

					<div class="mintmrm" data-is-preview="1">
						<div id="mrm-<?php echo esc_attr( $form_placement ); ?>" class="mrm-form-wrapper mrm-<?php echo esc_attr( $form_animation ); echo ' mrm-' . esc_attr( $form_placement ); // phpcs:ignore. ?>" data-exit-intent="<?php echo ( 'popup' === $form_placement && $exit_intent_enabled ) ? esc_attr( 'true' ) : esc_attr( 'false' ); ?>" data-form-id="preview">
							<div class="mrm-form-wrapper-inner <?php echo esc_attr( $class ); ?>">
								<span style="background: <?php echo esc_attr( $form_close_background_color ); ?>" class="mrm-form-close">
									<svg width="10" height="11" fill="none" viewBox="0 0 14 13" xmlns="http://www.w3.org/2000/svg"><path stroke="<?php echo esc_attr( $form_close_button_color ); ?>" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.5 1l-11 11m0-11l11 11"/></svg>
								</span>
								<div class="mrm-form-overflow">
									<form class="mrm-form" method="post" id="mrm-form">
										<?php echo wp_kses( $block_html, MrmCommon::wp_kseser_for_contact() ); ?>
									</form>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php

		$output .= ob_get_clean();
		return $output;
	}

	/**
	 * Template-gallery preview: settings come from the mint-form-setting URL param,
	 * blocks are read from localStorage on the client (same-origin iframe).
	 *
	 * @return string
	 */
	private function get_legacy_template_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_param    = isset( $_GET['mint-form-setting'] ) ? wp_unslash( $_GET['mint-form-setting'] ) : '';
		$replace_param = str_replace( '\\', '', $raw_param );
		$form_setting  = json_decode( urldecode( $replace_param ) );

		$form_placement = ! empty( $form_setting->settings->form_layout->form_position ) ? $form_setting->settings->form_layout->form_position : 'default';
		$form_animation = '';
		if ( 'default' !== $form_placement ) {
			$form_animation = ! empty( $form_setting->settings->form_layout->form_animation ) ? $form_setting->settings->form_layout->form_animation : '';
		}

		$form_close_button_color     = ! empty( $form_setting->settings->form_layout->close_button_color ) ? $form_setting->settings->form_layout->close_button_color : '#fff';
		$form_close_background_color = ! empty( $form_setting->settings->form_layout->close_background_color ) ? $form_setting->settings->form_layout->close_background_color : '#000';
		$custom_css                  = ! empty( $form_setting->settings->custom_css ) ? $form_setting->settings->custom_css : '';

		$output = '';
		ob_start();
		?>
		<style>
			html, body { margin: 0; padding: 0; }
			.mintmrm-form-preview { padding: 20px; }
			<?php if ( ! empty( $custom_css ) ) : ?>
				<?php echo wp_strip_all_tags( $custom_css ); ?>
			<?php endif; ?>
		</style>
		<div class="mintmrm mintmrm-form-preview">
			<div id="mrm-<?php echo esc_attr( $form_placement ); ?>" class="mrm-form-wrapper mrm-<?php echo esc_attr( $form_animation ); echo ' mrm-' . esc_attr( $form_placement ); // phpcs:ignore ?>">
				<div class="mrm-form-wrapper-inner custom-background">
					<?php if ( 'default' !== $form_placement ) : ?>
						<span style="background: <?php echo esc_attr( $form_close_background_color ); ?>" class="mrm-form-close">
							<svg width="10" height="11" fill="none" viewBox="0 0 14 13" xmlns="http://www.w3.org/2000/svg"><path stroke="<?php echo esc_attr( $form_close_button_color ); ?>" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.5 1l-11 11m0-11l11 11"/></svg>
						</span>
					<?php endif; ?>
					<div class="mrm-form-overflow">
						<form class="mrm-form" method="post" id="mrm-form">
							<script>document.write(localStorage.getItem('getmrmblocks'));</script>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
		$output .= ob_get_clean();
		return $output;
	}
}
