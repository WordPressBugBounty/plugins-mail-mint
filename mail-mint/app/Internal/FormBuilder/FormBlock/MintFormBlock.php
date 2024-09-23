<?php
/**
 * BlockTypesController class.
 *
 * @since 1.0.0
 * @internal
 * @package FormBlock
 */

namespace Mint\App\Internal\FormBuilder;

use Mint\MRM\DataBase\Models\FormModel;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * BlockTypesController class.
 *
 * @since 1.0.0
 * @internal
 */
class MintFormBlock {

	use Singleton;

	/**
	 * Initialize class functionalities
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'form_block_editor_assets' ) );
		add_action( 'wp_ajax_show_form_markup', array( $this, 'show_form_markup' ) );
	}

	/**
	 * Registers block
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_block() {
		register_block_type(
			'mint/mintform',
			array(
				'editor_script'   => 'mintform-block-editor-js',
				'editor_style'    => 'mintform-block-editor-style',
				'style'           => 'mintform-block-frontend-style',
				'render_callback' => array( $this, 'render_block' ),

			)
		);
	}

	/**
	 * Register assets file
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_assets() {
		if ( file_exists( MRM_DIR_PATH . '/app/Internal/FormBuilder/FormBlock/build/dist/mintform.min.asset.php' ) ) {
			$dependency = require_once MRM_DIR_PATH . '/app/Internal/FormBuilder/FormBlock/build/dist/mintform.min.asset.php';

			wp_register_script(
				'mintform-block-editor',
				MRM_DIR_URL . '/app/Internal/FormBuilder/FormBlock/build/dist/mintform.min.js',
				$dependency[ 'dependencies' ],
				MRM_VERSION,
				true
			);

			wp_register_style(
				'mintform-block-editor-style',
				MRM_DIR_URL . '/app/Internal/FormBuilder/FormBlock/build/dist/mintform.css',
				array( 'wp-edit-blocks' ),
				MRM_VERSION
			);
		}
	}

	/**
	 * Enqueue form block editor assets
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function form_block_editor_assets() {
		wp_enqueue_script( 'mintform-block-editor' );
		wp_enqueue_style( 'mintform-block-editor-style' );

		wp_localize_script(
			'mintform-block-editor',
			'getmm_block_object',
			array(
				'siteUrl' => get_site_url(),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'theme'   => MRM_DIR_URL,
			)
		);
	}

	/**
	 * Show form markups
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function show_form_markup() {
		$param = MrmCommon::get_sanitized_get_post();
		$post  = isset( $param['post'] ) ? $param['post'] : array();
		if ( isset( $post['post_id'] ) && !empty( $post['post_id'] ) ) {
			$form_id     = wp_unslash( $post['post_id'] );
			$form_data   = FormModel::get( $form_id );
			$form_status = isset( $form_data['status'] ) ? $form_data['status'] : 0;

			if ( !$form_status ) {
				echo esc_html( 'This form is not active. Please check' );
				wp_die();
			}

			ob_start();

			$output  = $form_data['form_body'];
			$output .= ob_get_clean();
			echo wp_kses( $output, MrmCommon::wp_kseser_for_contact() );
			wp_die();
		}

		echo esc_html( 'Please select a form' );
		wp_die();
	}

	/**
	 * Renders block markups
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string|void
	 * @since 1.0.0
	 */
	public function render_block( $attributes ) {
		$html           = '';
		$form_id        = isset( $attributes['form_id'] ) ? $attributes['form_id'] : 0;
		$get_setting    = FormModel::get_meta( $form_id );
		$form_setting   = isset( $get_setting['meta_fields']['settings'] ) ? $get_setting['meta_fields']['settings'] : '';
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

		$form_data   = FormModel::get( $form_id );
		$form_status = isset( $form_data['status'] ) ? $form_data['status'] : 0;

		if ( empty( $form_data ) ) {
			return __( '<div>Form ID is not valid</div>', 'mrm' ); //phpcs:ignore
		} elseif ( 'draft' === $form_status ) {
			return __( '<div>This form is not active. Please check</div>', 'mrm' ); //phpcs:ignore
		}
		$show = true;

		$show_always = isset( $form_setting->settings->extras->show_always ) ? $form_setting->settings->extras->show_always : true;
		if ( !$show_always ) {
			$cookie      = MrmCommon::get_sanitized_get_post();
			$cookie      = !empty( $cookie[ 'cookie' ] ) ? $cookie[ 'cookie' ] : array();
			$cookie_name = 'mintmail_form_dismissed_' . $form_id;
			$cookies     = isset( $cookie[ $cookie_name ] ) ? wp_unslash( $cookie[ $cookie_name ] ) : '';
			$cookies     = html_entity_decode( $cookies ); //phpcs:ignore PHPCompatibility.ParameterValues.NewHTMLEntitiesFlagsDefault.NotSet
			$cookies     = json_decode( stripslashes( $cookies ) );
			if ( ! empty( $cookies->expire ) ) {
				$expire = $cookies->expire;

				$today = strtotime( 'today UTC' );

				if ( $expire > $today ) {
					$show = false;
				}
			}
		}
		$blocks     = parse_blocks( $form_data[ 'form_body' ] );
		$block_html = '';
		$class      = '';
		foreach ( $blocks as $block ) {
			if ( 'core/columns' === $block['blockName'] ) {
				if ( isset( $block['attrs']['style']['color']['background'] ) ) {
					$class = 'custom-background';
				}
				if ( isset( $block['attrs']['backgroundColor'] ) ) {
					$class = 'custom-background';
				}
			}
			if ( 'core/group' === $block['blockName'] ) {
				if ( isset( $block['attrs']['style']['color']['background'] ) ) {
					$class = 'custom-background';
				}
				if ( isset( $block['attrs']['backgroundColor'] ) ) {
					$class = 'custom-background';
				}
			}
			if ( 'core/cover' === $block['blockName'] ) {
				if ( isset( $block['attrs']['customOverlayColor'] ) ) {
					$class = 'custom-background';
				}
				if ( isset( $block['attrs']['url'] ) ) {
					$class = 'custom-background';
				}if ( isset( $block['attrs']['overlayColor'] ) ) {
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
		$mrm_form_disable   = '';
		if ( 'v3_invisible' === $version && $recaptch_is_enable ) {
			$recapcha_js      = '<script src="https://www.google.com/recaptcha/api.js?render=' . $v3_site_key . '"></script>'; //phpcs:ignore
			$hidden_recaptcha = '<input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response"/>';
		}
		if ( 0 === $form_id ) {
			$html = '<div class="mintmrm">
                        <p>No form added</p>
                    </div>';
		} else {
			if ( $show ) {
				$html .= $recapcha_js;
				$html .= '<div class="mintmrm" id="mintmrm-' . $form_id . '">';
				if ( 'popup' === $form_placement && $is_form_open_button && $form_button_text ) {
					$mrm_form_disable = 'mrm-form-disable';
					$html            .= '<div> <button class="mint-form-button">' . $form_button_text . '</button></div>';
				}
				if ( ( 'popup' === $form_placement || 'flyins' === $form_placement ) && $is_time_delay && $time_delay ) {
					$mrm_form_disable = 'mrm-form-disable';
				}
				$html .= '<div id="mrm-' . $form_placement . '" class="mrm-form-wrapper mrm-' . $form_animation . ' mrm-' . $form_placement . ' ' . $mrm_form_disable . '">';
				$html .= '<div class="mrm-form-wrapper-inner ' . $class . '">';
				if ( 'default' !== $form_placement ) {
					$html .= '<span style="background:' . $form_close_background_color . '" class="mrm-form-close" form-id=' . $form_id . ' >
                        <svg width="10" height="11" fill="none" viewBox="0 0 14 13" xmlns="http://www.w3.org/2000/svg"><path stroke="' . $form_close_button_color . '" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12.5 1l-11 11m0-11l11 11"/></svg>
                    </span>';
				}

				$html .= '
                    <div class="mrm-form-overflow">
                        <form method="post" id="mrm-form">';
				$html .= '<input type="hidden" name="form_id" value="' . $attributes['form_id'] . '" />';
				$html .= $hidden_recaptcha;
				$html .= $block_html;
				$html .= '</form>';
				if ( 'v3_invisible' === $version && $recaptch_is_enable ) {
					$html .= '<script>
                                grecaptcha.ready(function() {
                                    grecaptcha.execute("' . $v3_site_key . '", {action: "homepage"}).then(function(token) {
                                        document.getElementById("g-recaptcha-response").value = token;
                                    });
                                });
                            </script>';
				}
				if ( ( 'popup' === $form_placement || 'flyins' === $form_placement ) && $is_time_delay && $time_delay ) {
					$html .="<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var elements = document.getElementById('mintmrm-$form_id');
                            var disableForm = elements.querySelector('.mrm-form-disable')
                            var timeDelay   =  $time_delay * 1000
                            if( elements){
                                setTimeout(function (){
                                    disableForm.classList.remove('mrm-form-disable')
                                },timeDelay)
                            }
                        });
                    </script>";
				}
				$html .='</div>

                    </div>
                </div>

            </div>';
			}
		}

		return $html;
	}
}
