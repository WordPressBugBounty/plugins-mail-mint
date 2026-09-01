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

use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\CustomFieldModel;
use MRM\Common\MrmCommon;

/**
 * [Manages plugin's contact form shortcodes]
 *
 * @desc Manages plugin's contact form shortcodes
 * @package /app/Internal/Shortcodes
 * @since 1.0.0
 */
class PreferencePage {

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
				'class' => '',
				'title' => 'Preference',
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
		$get_no_contact_manage = '';
		$get_assign_list       = '';
		$get_all_manage        = '';
		$hash                  = '';
		$param                 = MrmCommon::get_sanitized_get_post();
		$get                   = isset( $param['get'] ) ? $param['get'] : array();
        $settings              = get_option( '_mrm_general_preference' ); //phpcs:ignore
		if ( !empty( $settings['enable'] ) ) {
            $hash       = isset( $get['hash'] ) ? $get['hash'] : ''; //phpcs:ignore
			$preference = isset( $settings['preference'] ) ? $settings['preference'] : ''; //phpcs:ignore

			switch ( $preference ) {
				case 'no-contact-manage':
					$get_no_contact_manage = $this->no_contact_manage( $settings, $hash );
					break;
				case 'contact-manage-following':
					$get_assign_list = $this->contact_manage_following( $settings, $hash );
					break;
				case 'contact-manage':
					$get_all_manage = $this->contact_manage( $settings, $hash );
					break;
				default:
					$get_no_contact_manage = $this->no_contact_manage( $settings, $hash );
			}
		}
		ob_start();
		?>
		<section class="mintmrm-default-pages mintmrm-preference-page <?php echo esc_html( $this->attributes['class'] ); ?>">
			<div class="mintmrm-card-wrapper">
				<h2 class="mintmrm-card-title"><?php echo esc_html( $this->attributes['title'] ); ?></h2>
				<div class="mintmrm-card">
					<div class="response"></div>
					<form method="post" id="mrm-preference-form" class="mintmrm-preference-form">
						<input type="hidden" name="contact_hash" value="<?php echo esc_attr( $hash ); ?>">
						<?php
							echo wp_kses( $get_no_contact_manage, $this->allowed_wp_kses() );
							echo wp_kses( $get_assign_list, $this->allowed_wp_kses() );
							echo wp_kses( $get_all_manage, $this->allowed_wp_kses() );
						?>
					</form>
				</div>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
	/**
	 * Contact can not  manage list
	 *
	 * @param array  $settings get Preferance setting .
	 * @param string $hash get hash form contact .
	 *
	 * @return string
	 */
	public function no_contact_manage( $settings, $hash ) {
		$html   = '';
		$fields = isset( $settings['primary_fields'] ) ? $settings['primary_fields'] : array();
		if ( ! empty( $fields ) ) {
			$is_true = false;
			foreach ( $fields as $field ) {
				if ( !empty( $field ) ) {
					$is_true = true;
				}
			}
			$html  = '';
			$html .= $this->form_group_primary_filed( $fields, $hash, $html );
			$html .= $this->render_custom_fields( $hash );
			if ( $is_true ) {
				$html .= '
				<div class="mrm-form-group mintmrm-submit">
					<button type="submit" class="mrm-pref-submit-button mintmrm-card-button">'
						. __( 'Submit', 'mrm' ) . 
						'<span class="mintmrm-loader"></span>
					</button>
				</div>';
			}
		}
		return $html;
	}

	/**
	 * Contact can manage specific lists
	 *
	 * @param array  $settings get Preferance setting.
	 * @param string $hash get hash form contact.
	 *
	 * @return string|void
	 */
	public function contact_manage_following( $settings, $hash ) {
		$primary_fields = isset( $settings['primary_fields'] ) ? $settings['primary_fields'] : array();
		$fields         = isset( $settings['lists'] ) ? $settings['lists'] : array();
		if ( ! empty( $fields ) ) {
			$html      = '';
			$html_list = '';
			$html     .= $this->form_group_primary_filed( $primary_fields, $hash, $html );
			$html     .= $this->render_custom_fields( $hash );

			$html .= $this->contact_assign_list( $fields, $hash, $html_list );
			$html .= '
				<div class="mrm-form-group mintmrm-submit">
					<button type="submit" class="mrm-pref-submit-button mintmrm-card-button">'
					. __( 'Submit', 'mrm' ) .  
						'<span class="mintmrm-loader"></span>
					</button>
				</div>
			</div>';
			return $html;
		}
	}

	/**
	 * Contact can manage all Lists
	 *
	 * @param array  $settings get Preferance setting.
	 * @param string $hash get hash form contact.
	 *
	 * @return string|void
	 */
	public function contact_manage( $settings, $hash ) {
		$primary_fields = isset( $settings['primary_fields'] ) ? $settings['primary_fields'] : array();
		$html           = '';
		$html_list      = '';
		$html          .= $this->form_group_primary_filed( $primary_fields, $hash, $html );
		$html          .= $this->render_custom_fields( $hash );

		$html .= $this->contact_all_list( $hash, $html_list );
		$html .= '
            <div class="mrm-form-group mintmrm-submit">
                <button type="submit" class="mrm-pref-submit-button mintmrm-card-button">'
				. __( 'Submit', 'mrm' ) .  
                    '<span class="mintmrm-loader">
                </button>
            </div>
        </div>';

		return $html;
	}
	/**
	 * Render Primery Fields
	 *
	 * @param array  $fields Get all fields form Setting.
	 * @param string $hash get hash form Contact.
	 * @param mixed  $html render Html in Preference page.
	 *
	 * @return mixed|string
	 */
	public function form_group_primary_filed( $fields, $hash, $html ) {
		$contact_id = MrmCommon::resolve_contact_id_by_link_hash( $hash );
		if ( ! $contact_id ) {
			return $html;
		}
		$get_user_info = ContactModel::get( $contact_id );
		$first_name    = ! empty( $get_user_info[ 'first_name' ] ) ? $get_user_info[ 'first_name' ] : '';
		$last_name     = ! empty( $get_user_info[ 'last_name' ] ) ? $get_user_info[ 'last_name' ] : '';
		$status        = ! empty( $get_user_info[ 'status' ] ) ? $get_user_info[ 'status' ] : '';
		$status_array  = $this->contact_status();

		foreach ( $fields as $key => $field ) {
			if ( $field ) {
				if ( 'first_name' === $key ) {
					// SECURITY: contact names are attacker-controllable through the
					// preference endpoint and opt-in forms, so they must be escaped
					// before being interpolated into an attribute. wp_kses() on the
					// caller strips event handlers, but that is a second line of
					// defence, not this one.
					$html .= '<div class="mrm-form-group mintmrm-first-name">
								<label class="mrm-block-label" for="">'. __( 'First Name', 'mrm' ) . '</label>
								<input placeholder="'. __( 'Enter first name', 'mrm' ) . '" type="text" name="first_name" value="' . esc_attr( $first_name ) . '">
							  </div>';
				}

				if ( 'last_name' === $key ) {
					$html .= '<div class="mrm-form-group mintmrm-last-name">
								<label class="mrm-block-label" for="">'. __( 'Last Name', 'mrm' ) . '</label>
								<input placeholder="'. __( 'Enter last name', 'mrm' ) . '" type="text" name="last_name" value="' . esc_attr( $last_name ) . '">
							  </div>';
				}
				if ( 'status' === $key ) {
					$html .= '<div class="mrm-form-group mintmrm-status">
					<label class="mrm-block-label" for="">'. __( 'Status', 'mrm' ) . '</label>
					<div class="input-custom-wrapper">';
					foreach ( $status_array as $value ) {
						$checked = $status === $value['value'] ? 'checked' : '';
						$html   .= '<span class="mintmrm-radiobtn">
										<input id="status-' . $value['value'] . '" type="radio" name="status" value="' . $value['value'] . '" ' . $checked . '>
										<label for="status-' . $value['value'] . '">' . $value['name'] . '</label>
									</span>';
					}
					$html .= '</div></div>';
				}
			}
		}
		return $html;
	}

	/**
	 * Resolve a contact ID from a preference-link hash.
	 *
	 * @param string $hash Contact hash.
	 * @return int|false
	 * @since 1.24.5
	 */
	private function get_contact_id_by_hash( $hash ) {
		return MrmCommon::resolve_contact_id_by_link_hash( $hash );
	}

	/**
	 * Render custom field inputs, pre-filled with the contact's current values.
	 *
	 * @param string $hash Contact hash.
	 * @return string
	 * @since 1.24.5
	 */
	public function render_custom_fields( $hash ) {
		$contact_id = $this->get_contact_id_by_hash( $hash );
		if ( ! $contact_id ) {
			return '';
		}

		$fields = CustomFieldModel::get_all( 0, 200 );
		$fields = isset( $fields['data'] ) ? $fields['data'] : array();
		if ( empty( $fields ) ) {
			return '';
		}

		$meta_values = ContactModel::get_meta( $contact_id );
		$meta_values = isset( $meta_values['meta_fields'] ) ? $meta_values['meta_fields'] : array();

		$html = '';
		foreach ( $fields as $field ) {
			$meta  = ! empty( $field['meta'] ) ? maybe_unserialize( $field['meta'] ) : array();
			$label = isset( $meta['label'] ) ? $meta['label'] : $field['title'];
			$slug  = $field['slug'];
			$value = isset( $meta_values[ $slug ] ) ? $meta_values[ $slug ] : '';

			$html .= $this->render_custom_field_input( $slug, $field['type'], $label, $value, $meta );
		}

		return $html;
	}

	/**
	 * Render a single custom field's input, matching its stored field type.
	 *
	 * @param string $slug  Field slug (used as the input name).
	 * @param string $type  Field type.
	 * @param string $label Field label.
	 * @param mixed  $value Current value for this contact.
	 * @param array  $meta  Field meta (options, placeholder, etc.).
	 * @return string
	 * @since 1.24.5
	 */
	private function render_custom_field_input( $slug, $type, $label, $value, $meta ) {
		$name    = esc_attr( $slug );
		$options = ! empty( $meta['options'] ) && is_array( $meta['options'] ) ? $meta['options'] : array();

		switch ( $type ) {
			case 'textArea':
				return '<div class="mrm-form-group mintmrm-custom-field">
							<label class="mrm-block-label" for="">' . esc_html( $label ) . '</label>
							<textarea name="' . $name . '">' . esc_textarea( is_string( $value ) ? $value : '' ) . '</textarea>
						</div>';

			case 'selectField':
				$html = '<div class="mrm-form-group mintmrm-custom-field">
							<label class="mrm-block-label" for="">' . esc_html( $label ) . '</label>
							<select name="' . $name . '">';
				foreach ( $options as $option ) {
					$selected = ( (string) $value === (string) $option ) ? 'selected' : '';
					$html    .= '<option value="' . esc_attr( $option ) . '" ' . $selected . '>' . esc_html( $option ) . '</option>';
				}
				$html .= '</select></div>';
				return $html;

			case 'radioField':
				$html = '<div class="mrm-form-group mintmrm-custom-field">
							<label class="mrm-block-label" for="">' . esc_html( $label ) . '</label>
							<div class="input-custom-wrapper">';
				foreach ( $options as $option ) {
					$checked = ( (string) $value === (string) $option ) ? 'checked' : '';
					$html   .= '<span class="mintmrm-radiobtn">
									<input id="' . $name . '-' . esc_attr( $option ) . '" type="radio" name="' . $name . '" value="' . esc_attr( $option ) . '" ' . $checked . '>
									<label for="' . $name . '-' . esc_attr( $option ) . '">' . esc_html( $option ) . '</label>
								</span>';
				}
				$html .= '</div></div>';
				return $html;

			case 'checkboxField':
				$selected_values = is_array( $value ) ? $value : array();
				$html            = '<div class="mrm-form-group mintmrm-custom-field">
							<label class="mrm-block-label" for="">' . esc_html( $label ) . '</label>
							<div class="input-custom-wrapper">';
				foreach ( $options as $option ) {
					$checked = in_array( $option, $selected_values, true ) ? 'checked' : '';
					$html   .= '<span class="mintmrm-checkbox">
									<input id="' . $name . '-' . esc_attr( $option ) . '" type="checkbox" name="' . $name . '[]" value="' . esc_attr( $option ) . '" ' . $checked . '>
									<label for="' . $name . '-' . esc_attr( $option ) . '">' . esc_html( $option ) . '</label>
								</span>';
				}
				$html .= '</div></div>';
				return $html;

			case 'date':
				return '<div class="mrm-form-group mintmrm-custom-field">
							<label class="mrm-block-label" for="">' . esc_html( $label ) . '</label>
							<input type="date" name="' . $name . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '">
						</div>';

			case 'number':
				return '<div class="mrm-form-group mintmrm-custom-field">
							<label class="mrm-block-label" for="">' . esc_html( $label ) . '</label>
							<input type="number" name="' . $name . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '">
						</div>';

			default:
				return '<div class="mrm-form-group mintmrm-custom-field">
							<label class="mrm-block-label" for="">' . esc_html( $label ) . '</label>
							<input type="text" name="' . $name . '" value="' . esc_attr( is_scalar( $value ) ? $value : '' ) . '">
						</div>';
		}
	}

	/**
	 * Contact Status
	 *
	 * @return \string[][]
	 */
	public function contact_status() {
		$status_array = array(
			array(
				'value' => 'pending',
				'name'  => __( 'Pending',  ),
			),
			array(
				'value' => 'subscribed',
				'name'  => __( 'Subscribe', 'mrm' ),
			),
			array(
				'value' => 'unsubscribed',
				'name'  => __( 'Unsubscribe', 'mrm' ),
			),
		);

		return $status_array;
	}

	/**
	 * Render list
	 *
	 * @param array  $fields Get all fields form Setting.
	 * @param string $hash get hash form Contact.
	 * @param mixed  $html render Html in Preference page.
	 *
	 * @return mixed|string
	 */
	public function contact_assign_list( $fields, $hash, $html ) {
		$get_assign_list = $this->get_contact_assign_lists( $hash );
		$is_at_least_one_list_checked = false;
		$is_all_lists_checked = true;
		if ( ! empty( $fields ) ) {
			$html .= '<div class="mrm-form-group"><label class="mrm-block-label">'. __( 'Subscribed Lists', 'mrm' ) . '</label>';
			$html .= '<div class="form-group tag-lists-dropdown">
						<button type="button" class="drop-down-button mintmrm-dropdown-button" id="mintmrm-dropdown-button">';
						foreach ($fields as $field) {
							if ($this->is_checked_list($field['id'], $get_assign_list)) {
								$is_at_least_one_list_checked = true;
								$html .= '<span class="single-list mintmrm-tag-list">' . esc_html($field['title']) . '
										<span class="close-list" title="Delete">
											&#10005;
										</span>
									</span>';
							}else{
								$is_all_lists_checked = false;
							}
						}
						if (!$is_at_least_one_list_checked) {
							$html .= '<span>' . esc_html__('No lists selected', 'mrm') . '</span>';
						}
						$html .= '</button>
						<div class="add-contact mintmrm-dropdown" id="mintmrm-dropdown">
							<div class="searchbar mintmrm-dropdown-list">
								<span class="pos-relative">
									<svg width="15" height="16" fill="none" viewBox="0 0 15 16" xmlns="http://www.w3.org/2000/svg">
										<path fill="#C5C7D3" fill-rule="evenodd" d="M6.75 2.423c-2.9 0-5.25 2.28-5.25 5.091 0 2.812 2.35 5.091 5.25 5.091S12 10.325 12 7.515c0-2.812-2.35-5.092-5.25-5.092zM0 7.514C0 3.9 3.022.97 6.75.97S13.5 3.9 13.5 7.515c0 3.615-3.022 6.546-6.75 6.546S0 11.13 0 7.514z" clip-rule="evenodd"></path>
										<path fill="#C5C7D3" fill-rule="evenodd" d="M10.72 11.363a.767.767 0 011.06 0l3 2.91a.712.712 0 010 1.028.767.767 0 01-1.06 0l-3-2.91a.712.712 0 010-1.028z" clip-rule="evenodd"></path>
									</svg>
									<input type="search" name="column-search" id="mintmrm-search-input placeholder="Search or create" value="">
								</span>
							</div>
							<div class="list-title mintmrm-dropdown-list">CHOOSE LIST</div>
							<div class="option-section">
								<div class="single-column mintmrm-dropdown-list">
									<div class="mintmrm-checkbox">
										<input type="checkbox" name="all-items" id="all-items-create" ' . ( $is_all_lists_checked ? 'checked' : '' ) . '>
										<label for="all-items-create" class="mrm-custom-select-label">Select All Items</label>
									</div>
								</div>';
			foreach ( $fields as $field ) {
				$checked = $this->is_checked_list( $field['id'], $get_assign_list ) ? 'checked' : '';
				$html   .= '<div class="single-column mintmrm-dropdown-list' . ( $checked ? ' mrm-custom-select-single-column-selected' : '' ) . '">
								<div class="mintmrm-checkbox">
									<input type="checkbox" name="' . esc_attr( $field['id'] ) . '" id="create' . esc_attr( $field['id'] ) . '" data-custom-id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['title'] ) . '" ' . $checked . '>
									<label for="create' . esc_attr( $field['id'] ) . '" class="mrm-custom-select-label">' . esc_html( $field['title'] ) . '</label>
								</div>
							</div>';
			}
			$html .= '       </div>
						</div>
					</div></div>';
		}
		return $html;
	}
	/**
	 * Render All list
	 *
	 * @param string $hash get hash form Contact.
	 * @param mixed  $html render Html in Preference page.
	 *
	 * @return mixed|string
	 */
	public function contact_all_list( $hash, $html ) {
		$get_all_list    = ContactGroupModel::get_all_to_custom_select( 'lists' );
		$fields          = isset( $get_all_list['data'] ) ? $get_all_list['data'] : array();
		$get_assign_list = $this->get_contact_assign_lists( $hash );
		$is_at_least_one_list_checked = false;
		$is_all_lists_checked = true;
		if ( ! empty( $fields ) ) {
			$html .= '<div class="mrm-form-group"><label class="mrm-block-label">'. __( 'Subscribed Lists', 'mrm' ) . '</label>';
			$html .= '<div class="form-group tag-lists-dropdown">
						<button type="button" class="drop-down-button mintmrm-dropdown-button" id="mintmrm-dropdown-button">';
						foreach ($fields as $field) {
							if ($this->is_checked_list($field['id'], $get_assign_list)) {
								$is_at_least_one_list_checked = true;
								$html .= '<span class="single-list mintmrm-tag-list">' . esc_html($field['title']) . '
										<span class="close-list" title="Delete">
											&#10005;
										</span>
									</span>';
							}else{
								$is_all_lists_checked = false;
							}
						}
						if (!$is_at_least_one_list_checked) {
							$html .= '<span>' . esc_html__('No lists selected', 'mrm') . '</span>';
						}
						$html .= '</button>
						<div class="add-contact mintmrm-dropdown" id="mintmrm-dropdown">
							<div class="searchbar mintmrm-dropdown-list">
								<span class="pos-relative">
									<svg width="15" height="16" fill="none" viewBox="0 0 15 16" xmlns="http://www.w3.org/2000/svg">
										<path fill="#C5C7D3" fill-rule="evenodd" d="M6.75 2.423c-2.9 0-5.25 2.28-5.25 5.091 0 2.812 2.35 5.091 5.25 5.091S12 10.325 12 7.515c0-2.812-2.35-5.092-5.25-5.092zM0 7.514C0 3.9 3.022.97 6.75.97S13.5 3.9 13.5 7.515c0 3.615-3.022 6.546-6.75 6.546S0 11.13 0 7.514z" clip-rule="evenodd"></path>
										<path fill="#C5C7D3" fill-rule="evenodd" d="M10.72 11.363a.767.767 0 011.06 0l3 2.91a.712.712 0 010 1.028.767.767 0 01-1.06 0l-3-2.91a.712.712 0 010-1.028z" clip-rule="evenodd"></path>
									</svg>
									<input type="search" name="column-search" id="mintmrm-search-input placeholder="Search or create" value="">
								</span>
							</div>
							<div class="list-title mintmrm-dropdown-list">CHOOSE LIST</div>
							<div class="option-section">
								<div class="single-column mintmrm-dropdown-list">
									<div class="mintmrm-checkbox">
										<input type="checkbox" name="all-items" id="all-items-create" ' . ( $is_all_lists_checked ? 'checked' : '' ) . '>
										<label for="all-items-create" class="mrm-custom-select-label">Select All Items</label>
									</div>
								</div>';
			foreach ( $fields as $field ) {
				$checked = $this->is_checked_list( $field['id'], $get_assign_list ) ? 'checked' : '';
				$html   .= '<div class="single-column mintmrm-dropdown-list' . ( $checked ? ' mrm-custom-select-single-column-selected' : '' ) . '">
								<div class="mintmrm-checkbox">
									<input type="checkbox" name="' . esc_attr( $field['id'] ) . '" id="create' . esc_attr( $field['id'] ) . '" data-custom-id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $field['title'] ) . '" ' . $checked . '>
									<label for="create' . esc_attr( $field['id'] ) . '" class="mrm-custom-select-label">' . esc_html( $field['title'] ) . '</label>
								</div>
							</div>';
			}
			$html .= '       </div>
						</div>
					</div></div>';
		}
		return $html;
	}

	/**
	 * Get List which is assign a contact
	 *
	 * @param string $hash Get Contact hash value.
	 *
	 * @return array
	 */
	public function get_contact_assign_lists( $hash ) {
		$contact_id = MrmCommon::resolve_contact_id_by_link_hash( $hash );
		if ( ! $contact_id ) {
			return array();
		}
		$contact      = ContactModel::get( $contact_id );
		$contact_list = ContactGroupModel::get_lists_to_contact( $contact );
		$ids          = array();

		if ( ! empty( $contact_list[ 'lists' ] ) ) {
			foreach ( $contact_list[ 'lists' ] as $list ) {
				$ids[] = $list->id;
			}
		}

		return array_unique( $ids );
	}

	/**
	 * Checked LIst already assign
	 *
	 * @param int   $id Finding id.
	 * @param array $data Find id from this array.
	 *
	 * @return bool
	 */
	public function is_checked_list( $id, $data ) {
		if ( in_array( $id, $data, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Allowed WP Kses Array.
	 *
	 * @return \array[][]
	 */
	public function allowed_wp_kses() {
		return array(
			'input'  => array(
				'type'    => array(),
				'name'    => array(),
				'value'   => array(),
				'hidden'  => array(),
				'id'      => array(),
				'checked' => array(),
			),
			'form'   => array(
				'method' => array(),
				'id'     => array(),
				'class'  => array(),
			),
			'div'    => array(
				'class' => array(),
			),
			'button' => array(
				'type'  => array(),
				'class' => array(),
			),
			'label'  => array(
				'for'   => array(),
				'class' => array(),
			),
			'select' => array(
				'name' => array(),
				'id'   => array(),
			),
			'option' => array(
				'value'    => array(),
				'selected' => true,
			),
			'span'   => array(
				'class' => array(),
			),
			'textarea' => array(
				'name' => array(),
			),
		);
	}
}