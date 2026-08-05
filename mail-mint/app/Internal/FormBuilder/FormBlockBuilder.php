<?php
/**
 * Form Block Builder
 *
 * Generates Mail Mint form block markup (the `form_body` column) from a simple,
 * structured field specification. This is the safe path for programmatic form
 * creation (e.g. the MCP create-form / update-form tools): callers describe the
 * fields they want, and this class emits the exact block-comment + inline-style
 * markup the visual form builder produces, keeping the JSON attributes and the
 * rendered HTML in sync. Callers never author raw markup.
 *
 * The styling is intentionally fixed to a clean default (mirroring the shipped
 * templates in Storage.php); colours and layout can be refined afterwards in the
 * visual builder.
 *
 * @package Mint\MRM\Internal\FormBuilder
 * @since 1.24.0
 */

namespace Mint\MRM\Internal\FormBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Builds form_body block markup from a field spec.
 *
 * @since 1.24.0
 */
class FormBlockBuilder {

	/**
	 * Shared inline style for text/email/textarea inputs.
	 */
	const INPUT_STYLE = 'background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8';

	/**
	 * Shared inline style for field labels.
	 */
	const LABEL_STYLE = 'color:#363B4E;margin-bottom:7px';

	/**
	 * Field types this builder can render.
	 */
	const SUPPORTED_TYPES = array( 'email', 'first_name', 'last_name', 'text', 'textarea' );

	/**
	 * Build form_body markup and a normalised field manifest from a spec.
	 *
	 * @param array $spec {
	 *     @type array  $fields      List of field specs: each [ 'type', 'label', 'placeholder', 'required', 'slug' ].
	 *     @type string $button_text Submit button label.
	 *     @type string $heading     Optional heading rendered above the fields.
	 *     @type string $description Optional paragraph rendered below the heading.
	 * }
	 *
	 * @return array{form_body:string,fields:array} Markup plus the resolved field manifest.
	 * @since 1.24.0
	 */
	public static function build( array $spec ): array {
		$fields = self::normalizeFields( isset( $spec['fields'] ) && is_array( $spec['fields'] ) ? $spec['fields'] : array() );

		$blocks = array();

		$heading = isset( $spec['heading'] ) ? trim( (string) $spec['heading'] ) : '';
		if ( '' !== $heading ) {
			$blocks[] = self::renderHeading( $heading );
		}

		$description = isset( $spec['description'] ) ? trim( (string) $spec['description'] ) : '';
		if ( '' !== $description ) {
			$blocks[] = self::renderParagraph( $description );
		}

		foreach ( $fields as $field ) {
			$blocks[] = self::renderField( $field );
		}

		$button_text = isset( $spec['button_text'] ) && '' !== trim( (string) $spec['button_text'] )
			? trim( (string) $spec['button_text'] )
			: __( 'Subscribe', 'mrm' );
		$blocks[] = self::renderButton( $button_text );

		$inner = implode( "\n\n", $blocks );

		return array(
			'form_body' => self::wrap( $inner ),
			'fields'    => $fields,
		);
	}

	/**
	 * Normalise and validate the incoming field list.
	 *
	 * Guarantees an email field exists (Mail Mint forms require it), forces it to
	 * be required, defaults labels/placeholders, and derives unique slugs for
	 * custom text/textarea fields.
	 *
	 * @param array $raw Raw field specs from the caller.
	 * @return array Normalised field specs.
	 * @since 1.24.0
	 */
	private static function normalizeFields( array $raw ): array {
		$defaults = array(
			'email'      => __( 'Email', 'mrm' ),
			'first_name' => __( 'First Name', 'mrm' ),
			'last_name'  => __( 'Last Name', 'mrm' ),
			'text'       => __( 'Text', 'mrm' ),
			'textarea'   => __( 'Message', 'mrm' ),
		);

		$fields   = array();
		$used     = array();
		$has_email = false;

		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = isset( $item['type'] ) ? sanitize_key( $item['type'] ) : '';
			if ( ! in_array( $type, self::SUPPORTED_TYPES, true ) ) {
				continue;
			}

			$label       = isset( $item['label'] ) && '' !== trim( (string) $item['label'] ) ? trim( (string) $item['label'] ) : $defaults[ $type ];
			$placeholder = isset( $item['placeholder'] ) && '' !== trim( (string) $item['placeholder'] ) ? trim( (string) $item['placeholder'] ) : $label;
			$required    = ! empty( $item['required'] );

			$slug = '';
			if ( 'email' === $type ) {
				$slug     = 'email';
				$required = true; // Email is always required.
				$has_email = true;
			} elseif ( 'first_name' === $type ) {
				$slug = 'first_name';
			} elseif ( 'last_name' === $type ) {
				$slug = 'last_name';
			} else {
				$base = isset( $item['slug'] ) && '' !== trim( (string) $item['slug'] ) ? sanitize_title( $item['slug'] ) : sanitize_title( $label );
				if ( '' === $base ) {
					$base = $type;
				}
				$slug    = $base;
				$counter = 2;
				while ( isset( $used[ $slug ] ) ) {
					$slug = $base . '-' . $counter;
					++$counter;
				}
			}

			// Reserved-slug collisions for fixed fields: skip duplicates.
			if ( isset( $used[ $slug ] ) ) {
				continue;
			}
			$used[ $slug ] = true;

			$fields[] = array(
				'type'        => $type,
				'label'       => $label,
				'placeholder' => $placeholder,
				'required'    => $required,
				'slug'        => $slug,
			);
		}

		// Always guarantee an email field — prepend it when missing.
		if ( ! $has_email ) {
			array_unshift(
				$fields,
				array(
					'type'        => 'email',
					'label'       => $defaults['email'],
					'placeholder' => $defaults['email'],
					'required'    => true,
					'slug'        => 'email',
				)
			);
		}

		return $fields;
	}

	/**
	 * Render a single field block.
	 *
	 * @param array $field Normalised field spec.
	 * @return string
	 * @since 1.24.0
	 */
	private static function renderField( array $field ): string {
		switch ( $field['type'] ) {
			case 'email':
				return self::renderEmail( $field );
			case 'first_name':
			case 'last_name':
				return self::renderName( $field );
			case 'textarea':
				return self::renderTextarea( $field );
			case 'text':
			default:
				return self::renderText( $field );
		}
	}

	/**
	 * Email field block (always required).
	 *
	 * @param array $field Field spec.
	 * @return string
	 */
	private static function renderEmail( array $field ): string {
		$attrs = self::encodeAttrs(
			array(
				'emailLabel'       => $field['label'],
				'rowSpacing'       => 8,
				'inputBorderRadius' => 8,
			)
		);
		$label = esc_html( $field['label'] ) . self::requiredMark( true );
		$ph    = esc_attr( $field['placeholder'] );

		$html = '<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:8px ;width:100% ;max-width:px ">'
			. '<label for="mrm-email" style="' . self::LABEL_STYLE . '">' . $label . '</label>'
			. '<div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . $ph . '" required style="' . self::INPUT_STYLE . '" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>';

		return self::wrapBlock( 'mrmformfield/email-field-block', $attrs, $html );
	}

	/**
	 * First-name / last-name field block.
	 *
	 * @param array $field Field spec.
	 * @return string
	 */
	private static function renderName( array $field ): string {
		$is_first = 'first_name' === $field['type'];
		$attrs    = self::encodeAttrs(
			$is_first
				? array(
					'firstNameLabel'    => $field['label'],
					'isRequiredName'    => (bool) $field['required'],
					'rowSpacing'        => 8,
					'inputBorderRadius' => 8,
				)
				: array(
					'lastNameLabel'      => $field['label'],
					'isRequiredLastName' => (bool) $field['required'],
					'rowSpacing'         => 8,
					'inputBorderRadius'  => 8,
				)
		);

		$name      = $is_first ? 'first_name' : 'last_name';
		$id        = $is_first ? 'mrm-first-name' : 'wpfnl-last-name';
		$css_class = $is_first ? 'first-name' : 'last-name';
		$block     = $is_first ? 'mrmformfield/first-name-block' : 'mrmformfield/last-name-block';

		$label    = esc_html( $field['label'] ) . self::requiredMark( $field['required'] );
		$ph       = esc_attr( $field['placeholder'] );
		$req_attr = $field['required'] ? ' required' : '';

		$html = '<div class="mrm-form-group mrm-input-group alignment-left ' . $css_class . '" style="margin-bottom:8px;width:% ;max-width:px ">'
			. '<label for="' . esc_attr( $id ) . '" style="' . self::LABEL_STYLE . '">' . $label . '</label>'
			. '<div class="input-wrapper"><input type="text" name="' . $name . '" id="' . esc_attr( $id ) . '" placeholder="' . $ph . '"' . $req_attr . ' style="' . self::INPUT_STYLE . '"/></div></div>';

		return self::wrapBlock( $block, $attrs, $html );
	}

	/**
	 * Custom single-line text field block.
	 *
	 * @param array $field Field spec.
	 * @return string
	 */
	private static function renderText( array $field ): string {
		$attrs = self::encodeAttrs(
			array(
				'field_name'           => $field['label'],
				'field_label'          => $field['label'],
				'custom_text_placeholder' => $field['placeholder'],
				'field_require'        => (bool) $field['required'],
				'field_slug'           => $field['slug'],
				'rowSpacing'           => 8,
				'inputBorderRadius'    => 8,
			)
		);

		$slug     = esc_attr( $field['slug'] );
		$label    = esc_html( $field['label'] ) . self::requiredMark( $field['required'] );
		$ph       = esc_attr( $field['placeholder'] );
		$req_attr = $field['required'] ? ' required' : '';

		$html = '<div class="mrm-form-group mrm-input-group alignment-left text" style="margin-bottom:8px;width:% ;max-width:px ">'
			. '<label for="' . $slug . '" style="' . self::LABEL_STYLE . '">' . $label . '</label>'
			. '<div class="input-wrapper"><input type="text" name="' . $slug . '" id="' . $slug . '" placeholder="' . $ph . '"' . $req_attr . ' style="' . self::INPUT_STYLE . '"/></div></div>';

		return self::wrapBlock( 'mrmformfield/mrm-custom-field', $attrs, $html );
	}

	/**
	 * Custom multi-line textarea field block.
	 *
	 * @param array $field Field spec.
	 * @return string
	 */
	private static function renderTextarea( array $field ): string {
		$attrs = self::encodeAttrs(
			array(
				'field_type'                => 'textarea',
				'field_name'                => $field['label'],
				'field_label'               => $field['label'],
				'custom_textarea_placeholder' => $field['placeholder'],
				'field_require'             => (bool) $field['required'],
				'field_slug'                => $field['slug'],
				'rowSpacing'                => 8,
				'inputBorderRadius'         => 8,
			)
		);

		$slug     = esc_attr( $field['slug'] );
		$label    = esc_html( $field['label'] ) . self::requiredMark( $field['required'] );
		$ph       = esc_attr( $field['placeholder'] );
		$req_attr = $field['required'] ? ' required' : '';

		$html = '<div class="mrm-form-group mrm-input-group alignment-left textarea" style="margin-bottom:8px;width:% ;max-width:px ">'
			. '<label for="' . $slug . '" style="' . self::LABEL_STYLE . '">' . $label . '</label>'
			. '<div class="input-wrapper"><textarea id="' . $slug . '" name="' . $slug . '" placeholder="' . $ph . '"' . $req_attr . ' rows="4" cols="50" style="' . self::INPUT_STYLE . '"></textarea></div></div>';

		return self::wrapBlock( 'mrmformfield/mrm-custom-field', $attrs, $html );
	}

	/**
	 * Submit button block.
	 *
	 * @param string $text Button label.
	 * @return string
	 */
	private static function renderButton( string $text ): string {
		$attrs = self::encodeAttrs(
			array(
				'rowSpacing'        => 5,
				'buttonTextColor'   => '#FFFFFF',
				'buttonBgColor'     => '#007dff',
				'buttonBorderRadius' => 8,
				'buttonText'        => $text,
				'buttonWidth'       => 100,
			)
		);

		$label = esc_html( $text );
		$html  = '<div class="mrm-form-group submit" style="margin-bottom:5px;text-align:left">'
			. '<button class="mrm-submit-button mintmrm-btn" type="submit" aria-label="Submit" style="background-color:#007dff;color:#FFFFFF;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:100%">' . $label . '</button>'
			. '<div id="mint-google-recaptcha" style="padding-top:10px"></div><div class="response"></div></div>';

		return self::wrapBlock( 'mrmformfield/mrm-button-block', $attrs, $html );
	}

	/**
	 * Heading block.
	 *
	 * @param string $text Heading text.
	 * @return string
	 */
	private static function renderHeading( string $text ): string {
		$attrs = self::encodeAttrs(
			array(
				'textAlign' => 'center',
				'level'     => 2,
			)
		);
		$html = '<h2 class="wp-block-heading has-text-align-center">' . esc_html( $text ) . '</h2>';
		return self::wrapBlock( 'heading', $attrs, $html );
	}

	/**
	 * Paragraph block.
	 *
	 * @param string $text Paragraph text.
	 * @return string
	 */
	private static function renderParagraph( string $text ): string {
		$attrs = self::encodeAttrs( array( 'align' => 'center' ) );
		$html  = '<p class="has-text-align-center">' . esc_html( $text ) . '</p>';
		return self::wrapBlock( 'paragraph', $attrs, $html );
	}

	/**
	 * Wrap the inner blocks in a single-column container matching the shipped
	 * template layout (white card with padding and rounded corners).
	 *
	 * @param string $inner Concatenated child block markup.
	 * @return string
	 */
	private static function wrap( string $inner ): string {
		$columns_attrs = self::encodeAttrs(
			array(
				'style'           => array(
					'border'  => array( 'radius' => '16px' ),
					'spacing' => array(
						'padding' => array(
							'top'    => '20px',
							'right'  => '20px',
							'bottom' => '20px',
							'left'   => '20px',
						),
					),
				),
				'backgroundColor' => 'white',
			)
		);
		$column_attrs = self::encodeAttrs( array( 'verticalAlignment' => 'top' ) );

		return '<!-- wp:columns ' . $columns_attrs . " -->\n"
			. '<div class="wp-block-columns has-white-background-color has-background" style="border-radius:16px;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px">'
			. '<!-- wp:column ' . $column_attrs . " -->\n"
			. '<div class="wp-block-column is-vertically-aligned-top">' . $inner . '</div>' . "\n"
			. "<!-- /wp:column --></div>\n"
			. '<!-- /wp:columns -->';
	}

	/**
	 * Wrap an HTML payload in a Gutenberg block comment pair.
	 *
	 * @param string $block_name Fully-qualified block name (e.g. mrmformfield/email-field-block).
	 * @param string $attrs      Pre-encoded JSON attribute string ('' for none).
	 * @param string $html       Rendered inner HTML.
	 * @return string
	 */
	private static function wrapBlock( string $block_name, string $attrs, string $html ): string {
		$open = '' !== $attrs ? '<!-- wp:' . $block_name . ' ' . $attrs . ' -->' : '<!-- wp:' . $block_name . ' -->';
		return $open . "\n" . $html . "\n" . '<!-- /wp:' . $block_name . ' -->';
	}

	/**
	 * Encode block attributes to the JSON form used in block comments.
	 *
	 * @param array $attrs Attribute map.
	 * @return string JSON string, or '' when there are no attributes.
	 */
	private static function encodeAttrs( array $attrs ): string {
		if ( empty( $attrs ) ) {
			return '';
		}
		return wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Required-field asterisk markup.
	 *
	 * @param bool $required Whether the field is required.
	 * @return string
	 */
	private static function requiredMark( bool $required ): string {
		return $required ? '<span class="required-mark">*</span>' : '';
	}
}
