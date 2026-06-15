<?php
/**
 * Unsubscribe survey shortcode.
 *
 * @author   MRM Team
 * @category Internal
 * @package  MRM
 * @since    1.20.0
 */

namespace Mint\MRM\Internal\ShortCode;

use Mint\MRM\Internal\Optin\UnsubscribeReasons;
use MRM\Common\MrmCommon;

/**
 * Renders the post-unsubscribe feedback survey card.
 *
 * Usage: [unsubscribe_survey]
 *
 * @since 1.20.0
 */
class UnsubscribeSurvey {

	/**
	 * Shortcode attributes.
	 *
	 * @var array
	 * @since 1.20.0
	 */
	protected $attributes = array();

	/**
	 * Constructor.
	 *
	 * @param array $attributes Shortcode attributes.
	 * @since 1.20.0
	 */
	public function __construct( $attributes = array() ) {
		$this->attributes = $this->parse_attributes( $attributes );
	}

	/**
	 * Parse and apply defaults for shortcode attributes.
	 *
	 * @param array $attributes Raw attributes.
	 * @return array
	 * @since 1.20.0
	 */
	protected function parse_attributes( $attributes ) {
		return shortcode_atts(
			array(
				'title'       => __( 'Tell us why you unsubscribed', 'mrm' ),
				'subtitle'    => __( 'Your feedback helps us improve. This is optional.', 'mrm' ),
				'button_text' => __( 'Submit Feedback', 'mrm' ),
				'skip_text'   => __( 'Skip', 'mrm' ),
				'class'       => '',
			),
			$attributes
		);
	}

	/**
	 * Returns the shortcode HTML output.
	 *
	 * @return string
	 * @since 1.20.0
	 */
	public function get_content() {
		$get          = MrmCommon::get_sanitized_get_post();
		$get          = isset( $get['get'] ) ? $get['get'] : array();
		$hash         = isset( $get['hash'] ) ? sanitize_text_field( $get['hash'] ) : '';
		// Build the skip / after-unsubscribe redirect URL.
		$settings     = get_option( '_mrm_general_unsubscriber_settings', array() );
		$allow_text   = 'yes' === ( $settings['unsubscribe_survey_allow_other_text'] ?? 'no' );
		$allow_resub  = 'yes' === ( $settings['unsubscribe_allow_resubscription'] ?? 'yes' );
		$skip_url     = $this->get_after_unsubscribe_url( $settings );

		// Resubscribe URL.
		$resub_url = add_query_arg(
			array(
				'mrm'   => 1,
				'route' => 'resubscribe',
				'hash'  => $hash,
			),
			home_url()
		);

		$api_url     = esc_url( rest_url( 'mint-mail/v1/unsubscribe-survey' ) );
		$reasons     = UnsubscribeReasons::get_reasons();

		ob_start();
		?>
		<section class="mintmrm-default-pages mintmrm-survey-page <?php echo esc_attr( $this->attributes['class'] ); ?>">
			<div class="mintmrm-card-wrapper">
				<div class="mintmrm-card" id="mint-survey-card">

					<div class="mintmrm-card-header">
						<svg fill="none" width="259" height="259" viewBox="0 0 259 259" xmlns="http://www.w3.org/2000/svg"><circle cx="129.5" cy="129.5" r="129.5" fill="#F6F8FA"/><path fill="#FDC142" d="M191.813 95.5v51.75a27.334 27.334 0 01-27.312 27.313h-74.75a27.339 27.339 0 01-27.313-27.313V95.5c-.019-.73.02-1.46.115-2.185a27.319 27.319 0 0127.198-25.127h74.75a27.318 27.318 0 0127.197 25.127c.096.724.134 1.455.115 2.185z"/><path fill="#FFE578" d="M191.698 93.315l-51.29 28.52a27.303 27.303 0 01-26.565 0l-51.29-28.52A27.319 27.319 0 0189.75 68.188h74.75a27.319 27.319 0 0127.198 25.127z"/></svg>
					</div>

					<div class="mintmrm-card-body">
						<h1 class="mintmrm-card-title"><?php echo esc_html( $this->attributes['title'] ); ?></h1>
						<p class="mintmrm-card-subtitle"><?php echo esc_html( $this->attributes['subtitle'] ); ?></p>

						<form id="mint-survey-form" class="mint-survey-form">
							<div class="input-custom-wrapper mint-survey-reasons">
								<?php foreach ( $reasons as $value => $label ) : ?>
									<span class="mintmrm-radiobtn">
										<input
											type="radio"
											id="mint-reason-<?php echo esc_attr( $value ); ?>"
											name="mint_reason"
											value="<?php echo esc_attr( $value ); ?>"
										/>
										<label for="mint-reason-<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></label>
									</span>
								<?php endforeach; ?>
							</div>

							<?php if ( $allow_text ) : ?>
							<div class="mint-survey-other-wrap" id="mint-survey-other-wrap" style="display:none;">
								<textarea
									id="mint-survey-other-text"
									name="mint_reason_text"
									class="mint-survey-textarea"
									placeholder="<?php esc_attr_e( 'Tell us more (optional)&hellip;', 'mrm' ); ?>"
									maxlength="500"
									rows="4"
								></textarea>
							</div>
							<?php endif; ?>

							<div id="mint-survey-message" class="mintmrm-alert" style="display:none;"></div>

							<div class="mintmrm-card-buttons mint-survey-buttons">
								<button
									type="submit"
									class="mintmrm-card-button"
									id="mint-survey-submit"
								><?php echo esc_html( $this->attributes['button_text'] ); ?></button>

								<a
									href="<?php echo esc_url( $skip_url ); ?>"
									class="mintmrm-card-button mintmrm-card-button-outline"
								><?php echo esc_html( $this->attributes['skip_text'] ); ?></a>
							</div>
						</form>

						<?php if ( $allow_resub && $hash ) : ?>
						<p class="mint-survey-resub">
							<?php esc_html_e( 'Changed your mind?', 'mrm' ); ?>
							<a href="<?php echo esc_url( $resub_url ); ?>" class="mint-survey-resub-link">
								<?php esc_html_e( 'Resubscribe', 'mrm' ); ?>
							</a>
						</p>
						<?php endif; ?>
					</div>

				</div>
			</div>
		</section>

		<script>
		(function () {
			var form       = document.getElementById( 'mint-survey-form' );
			var otherWrap  = document.getElementById( 'mint-survey-other-wrap' );
			var msgBox     = document.getElementById( 'mint-survey-message' );
			var submitBtn  = document.getElementById( 'mint-survey-submit' );

			<?php if ( $allow_text ) : ?>
			// Show/hide free-text area when "other" is selected.
			document.querySelectorAll( 'input[name="mint_reason"]' ).forEach( function ( radio ) {
				radio.addEventListener( 'change', function () {
					if ( otherWrap ) {
						otherWrap.style.display = this.value === 'other' ? 'block' : 'none';
					}
				} );
			} );
			<?php endif; ?>

			if ( form ) {
				form.addEventListener( 'submit', function ( e ) {
					e.preventDefault();

					var selected = document.querySelector( 'input[name="mint_reason"]:checked' );
					if ( ! selected ) {
						showMessage( '<?php echo esc_js( __( 'Please select a reason.', 'mrm' ) ); ?>', false );
						return;
					}

					submitBtn.disabled = true;

					var body = {
						hash        : <?php echo wp_json_encode( $hash ); ?>,
						reason      : selected.value,
						reason_text : otherWrap ? ( document.getElementById( 'mint-survey-other-text' ) ? document.getElementById( 'mint-survey-other-text' ).value : '' ) : ''
					};

					fetch( <?php echo wp_json_encode( $api_url ); ?>, {
						method  : 'POST',
						headers : { 'Content-Type': 'application/json' },
						body    : JSON.stringify( body )
					} )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data && data.success ) {
							showMessage( '<?php echo esc_js( __( 'Thank you for your feedback!', 'mrm' ) ); ?>', true );
							form.style.display = 'none';
							setTimeout( function () {
								window.location.href = data.redirect_url || <?php echo wp_json_encode( $skip_url ); ?>;
							}, 2000 );
						} else {
							submitBtn.disabled = false;
							showMessage( ( data && data.message ) ? data.message : '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'mrm' ) ); ?>', false );
						}
					} )
					.catch( function () {
						submitBtn.disabled = false;
						showMessage( '<?php echo esc_js( __( 'Something went wrong. Please try again.', 'mrm' ) ); ?>', false );
					} );
				} );
			}

			function showMessage( text, success ) {
				if ( ! msgBox ) return;
				msgBox.textContent    = text;
				msgBox.className      = 'mintmrm-alert ' + ( success ? 'mintmrm-success' : 'mintmrm-error' );
				msgBox.style.display  = 'block';
			}
		}());
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Resolves the URL to redirect to after the survey (skip / on success).
	 *
	 * Falls back to home_url() if no page is configured.
	 *
	 * @param array $settings Value of _mrm_general_unsubscriber_settings option.
	 * @return string
	 * @since 1.20.0
	 */
	private function get_after_unsubscribe_url( array $settings ): string {
		$type = isset( $settings['confirmation_type'] ) ? $settings['confirmation_type'] : 'redirect-page';

		if ( 'redirect-page' === $type && ! empty( $settings['page_id'] ) ) {
			$url = get_permalink( $settings['page_id'] );
			if ( $url ) {
				return $url;
			}
		}

		if ( 'redirect' === $type && ! empty( $settings['url'] ) ) {
			return esc_url( $settings['url'] );
		}

		return home_url();
	}
}
