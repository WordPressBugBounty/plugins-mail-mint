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
	 * Preview of opt-in form
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_content() {
		$param          = MrmCommon::get_sanitized_get_post();
		$get            = !empty( $param['get'] ) ? $param['get'] : array();
		$param          = isset( $get['mint-form-setting'] ) ? wp_unslash( $get['mint-form-setting'] ) : '';
		$replace_param  = str_replace( '\\', '', $param );
		$form_setting   = json_decode( $replace_param );
		$form_placement = ! empty( $form_setting->settings->form_layout->form_position ) ? $form_setting->settings->form_layout->form_position : '';
		$form_animation = '';
		if ( 'default' !== $form_placement ) {
			$form_animation = ! empty( $form_setting->settings->form_layout->form_animation ) ? $form_setting->settings->form_layout->form_animation : '';
		}

		$form_close_button_color     = ! empty( $form_setting->settings->form_layout->close_button_color ) ? $form_setting->settings->form_layout->close_button_color : '#fff';
		$form_close_background_color = ! empty( $form_setting->settings->form_layout->close_background_color ) ? $form_setting->settings->form_layout->close_background_color : '#000';
		$output                      = '';
		ob_start();
		?>

		<div class="mintmrm mintmrm-form-preview">
            <div id="mrm-<?php echo esc_attr( $form_placement ); ?>" class="mrm-form-wrapper mrm-<?php echo esc_attr( $form_animation ); echo isset( $this->attributes[ 'class' ] ) ? esc_attr( $this->attributes[ 'class' ] ) : ''; echo ' mrm-' . esc_attr( $form_placement ); // phpcs:ignore. ?>">
				<div class="mrm-form-wrapper-inner custom-background">

					<?php
					if ( 'default' !== $form_placement ) {
						?>
						<span style="background: <?php echo esc_attr( $form_close_background_color ); ?> " class="mrm-form-close">
								<svg width="10" height="11" fill="none" viewBox="0 0 14 13" xmlns="http://www.w3.org/2000/svg"><path stroke="<?php echo esc_attr( $form_close_button_color ); ?>" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.5 1l-11 11m0-11l11 11"/></svg>
							</span>
						<?php
					}
					?>

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
