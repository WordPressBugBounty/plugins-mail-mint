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
class ContactForm {

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
				'id'           => 0,
				'class'        => '',
				'button_class' => '',
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
	 * Content of opt-in form
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_content() {
		$form_id = isset( $this->attributes[ 'id' ] ) ? $this->attributes[ 'id' ] : 0;
		if ( 0 === (int) $form_id ) {
			return __( '<div>No form added</div>', 'mrm' ); //phpcs:ignore
		}

		// Render form shortcode output.
		return $this->render_content( $form_id );
	}


	/**
	 * Render form shortcode output
	 *
	 * @param int $form_id Form id.
	 *
	 * @return string|void
	 * @since 1.0.0
	 */
	public function render_content( $form_id ) {
		$form_data   = FormModel::get( $form_id );
		$form_status = isset( $form_data[ 'status' ] ) ? $form_data[ 'status' ] : 0;
		if ( empty( $form_data ) ) {
			return __( '<div>Form ID is not valid</div>', 'mrm' ); //phpcs:ignore
		} elseif ( 'draft' === $form_status ) {
			return __( '<div>This form is not active. Please check</div>', 'mrm' ); //phpcs:ignore
		}
		$get_setting    = FormModel::get_meta( $form_id );
		$form_setting   = isset( $get_setting[ 'meta_fields' ][ 'settings' ] ) ? $get_setting[ 'meta_fields' ][ 'settings' ] : (object) array();
		$form_setting   = json_decode( $form_setting );
		$form_placement = ! empty( $form_setting->settings->form_layout->form_position ) ? $form_setting->settings->form_layout->form_position : '';
		$form_animation = '';
		if ( 'default' !== $form_placement ) {
			$form_animation = ! empty( $form_setting->settings->form_layout->form_animation ) ? $form_setting->settings->form_layout->form_animation : '';
		}

		$form_close_button_color     = ! empty( $form_setting->settings->form_layout->close_button_color ) ? $form_setting->settings->form_layout->close_button_color : '#fff';
		$form_close_background_color = ! empty( $form_setting->settings->form_layout->close_background_color ) ? $form_setting->settings->form_layout->close_background_color : '#000';

		$is_form_open_button = ! empty( $form_setting->settings->button_render->enable ) ? $form_setting->settings->button_render->enable : false;
		$form_button_text    = ! empty( $form_setting->settings->button_render->button_text ) ? $form_setting->settings->button_render->button_text : 'Click Here';

		$is_time_delay = ! empty( $form_setting->settings->time_delay->enable ) ? $form_setting->settings->time_delay->enable : false;
		$time_delay    = ! empty( $form_setting->settings->time_delay->time ) ? $form_setting->settings->time_delay->time : 0;

		$blocks = parse_blocks( $form_data[ 'form_body' ] );
		$output = '';
		$show   = true;

		$show_always = isset( $form_setting->settings->extras->show_always ) ? $form_setting->settings->extras->show_always : true;
		if ( !$show_always ) {
			$cookie_name = 'mintmail_form_dismissed_' . $form_id;
			$cookie      = MrmCommon::get_sanitized_get_post();
			$cookie      = ! empty( $cookie[ 'cookie' ] ) ? $cookie[ 'cookie' ] : array();
			$cookies     = isset( $cookie[ $cookie_name ] ) ? wp_unslash( $cookie[ $cookie_name ] ) : '';
			$cookies     = html_entity_decode( $cookies ); //phpcs:ignore PHPCompatibility.ParameterValues.NewHTMLEntitiesFlagsDefault.NotSet
			$cookies     = json_decode( stripslashes( $cookies ) );
			if ( ! empty( $cookies->expire ) ) {
				$expire = $cookies->expire;

				$today = strtotime( 'today UTC' );

				if ( $today < $expire ) {
					$show = false;
				}
			}
		}
		$block_html = '';
		$class      = '';
		foreach ( $blocks as $block ) {
			if ( 'core/columns' === $block[ 'blockName' ] ) {
				if ( isset( $block[ 'attrs' ][ 'style' ][ 'color' ][ 'background' ] ) ) {
					$class = 'custom-background';
				}
				if ( isset( $block[ 'attrs' ][ 'backgroundColor' ] ) ) {
					$class = 'custom-background';
				}
			}
			if ( 'core/group' === $block[ 'blockName' ] ) {
				if ( isset( $block[ 'attrs' ][ 'style' ][ 'color' ][ 'background' ] ) ) {
					$class = 'custom-background';
				}
				if ( isset( $block[ 'attrs' ][ 'backgroundColor' ] ) ) {
					$class = 'custom-background';
				}
			}
			if ( 'core/cover' === $block[ 'blockName' ] ) {
				if ( isset( $block[ 'attrs' ][ 'customOverlayColor' ] ) ) {
					$class = 'custom-background';
				}
				if ( isset( $block[ 'attrs' ][ 'url' ] ) ) {
					$class = 'custom-background';
				}
				if ( isset( $block[ 'attrs' ][ 'overlayColor' ] ) ) {
					$class = 'custom-background';
				}
			}

			$block_html .= render_block( $block );
		}
		$settings           = get_option( '_mint_recaptcha_settings' );
		$recaptch_is_enable = isset( $settings['enable'] ) ? $settings['enable'] : false;
		$version            = isset( $settings['api_version'] ) ? $settings['api_version'] : '';
		$v3_site_key        = isset( $settings['v3_invisible']['site_key'] ) ? $settings['v3_invisible']['site_key'] : array();
		$recapcha_js        = '';
		$hidden_recaptcha   = '';
		if ( 'v3_invisible' === $version && $recaptch_is_enable ) {
			$recapcha_js      = ' <script src="https://www.google.com/recaptcha/api.js?render=' . $v3_site_key . '"></script>'; //phpcs:ignore
			$hidden_recaptcha = '<input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response"/>';
		}
		$mrm_form_disable = '';
		if ( $show ) {
			ob_start();
			// phpcs:disable Generic.PHP.DisallowShortOpenTag.PossibleFound
			?>
			<?php echo $recapcha_js; //phpcs:ignore ?>
			<div class="mintmrm" id="mintmrm-<?php echo $form_id; //phpcs:ignore ?>">
				<?php
				if ( 'popup' === $form_placement && $is_form_open_button && $form_button_text ) {
					$mrm_form_disable = 'mrm-form-disable'
					?>
					<div>
						<button class="mint-form-button <?php echo isset( $this->attributes[ 'button_class' ] ) ? esc_attr( $this->attributes[ 'button_class' ] ) : ''; ?>" type="submit"><?php echo esc_html( $form_button_text ); ?></button>
					</div>
					<?php
				}
				if ( ('popup' === $form_placement || 'flyins' === $form_placement) && $is_time_delay && $time_delay ) {//phpcs:ignore
					$mrm_form_disable = 'mrm-form-disable';
				}
				?>
                <div id="mrm-<?php echo esc_attr( $form_placement ); ?>" class="mrm-form-wrapper mrm-<?php echo esc_attr( $form_animation ); echo isset( $this->attributes[ 'class' ] ) ? esc_attr( $this->attributes[ 'class' ] ) : ''; echo ' mrm-' . esc_attr( $form_placement ); // phpcs:ignore. ?> <?php echo $mrm_form_disable  ?>">
					<div class="mrm-form-wrapper-inner <?php echo esc_attr( $class ); ?> ">

						<?php
						if ( 'default' !== $form_placement ) {
							?>
							<span style="background: <?php echo esc_attr( $form_close_background_color ); ?> " class="mrm-form-close" form-id=<?php echo esc_attr( $form_id ); ?> >
								<svg width="10" height="11" fill="none" viewBox="0 0 14 13" xmlns="http://www.w3.org/2000/svg"><path stroke="<?php echo esc_attr( $form_close_button_color ); ?>" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.5 1l-11 11m0-11l11 11"/></svg>
							</span>
							<?php
						}
						?>

						<div class="mrm-form-overflow">
							<form class="mrm-form" method="post" id="mrm-form">
								<input type="hidden" name="form_id" value="<?php echo isset( $form_data[ 'id' ] ) ? esc_attr( $form_data[ 'id' ] ) : 0; ?>"/>
								<?php echo wp_kses( $hidden_recaptcha, MrmCommon::wp_kseser_for_contact() ); ?>
								<?php echo wp_kses( $block_html, MrmCommon::wp_kseser_for_contact() ); ?>
							</form>
							<?php
							if ( $recaptch_is_enable && 'v3_invisible' === $version ) {
								?>
								<script>
									grecaptcha.ready(function() {
										grecaptcha.execute('<?php echo $v3_site_key; //phpcs:ignore ?>', {action: 'homepage'}).then(function(token) {
											document.getElementById("g-recaptcha-response").value = token;
										});
									});
								</script>
								<?php
							}
							?>

						</div>

					</div>
				</div>
				<?php //phpcs:ignore
				if ( ( 'popup' === $form_placement || 'flyins' === $form_placement ) && $is_time_delay && $time_delay ) {
					?>
					<script>
						document.addEventListener('DOMContentLoaded', function() {
							var elements = document.getElementById('mintmrm-<?php echo $form_id; //phpcs:ignore ?>');
							var disableForm = elements.querySelector('.mrm-form-disable')
							var timeDelay = <?php echo $time_delay; //phpcs:ignore ?> * 1000
							if( elements){
								setTimeout(function (){
									disableForm.classList.remove('mrm-form-disable')
								},timeDelay)
							}
						});
					</script>

					<?php
				}
				?>
			</div>
			<?php

			$output .= ob_get_clean();

			return $output;
		}
	}

}
