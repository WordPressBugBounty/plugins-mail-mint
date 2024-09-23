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

use Mint\MRM\Admin\API\Controllers\MessageController;
use Mint\MRM\Admin\API\Controllers\TagController;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataStores\ContactData;
use Mint\Mrm\Internal\Traits\Singleton;
use MRM\Common\MrmCommon;

/**
 * [Manages assigning wp user in mrm contact]
 *
 * @desc Manages assigning wp user in mrm contact
 * @package /app/Internal/Frontend
 * @since 1.0.0
 */
class UserAssignContact {

	use Singleton;

	/**
	 * Initializes class functionalities
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'user_register', array( $this, 'assign_signup_user_in_contact' ), 10, 2 );
		add_action( 'register_form', array( $this, 'mint_signup_form_permission_field_set' ) );
		add_action( 'comment_post', array( $this, 'assign_comment_post_user_in_contact' ), 10, 3 );
		add_action( 'comment_form_field_comment', array( $this, 'mint_set_comment_permission_input_field_login' ) );
		add_action( 'comment_form_after_fields', array( $this, 'mint_set_comment_permission_input_field_logout' ) );
	}

	/**
	 * Assign User in Mint mail contact
	 *
	 * @param int|string   $user_id WP user id.
	 * @param array|object $user_data User's data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function assign_signup_user_in_contact( $user_id, $user_data ) {
		$param = MrmCommon::get_sanitized_get_post();
		$post  = isset( $param['post'] ) ? $param['post'] : array();
        if ( isset( $post['mintmail_subscribe_on_register'] ) && $post['mintmail_subscribe_on_register'] ) { //phpcs:ignore
			$get_options = get_option( '_mrm_general_user_signup' );
			if ( isset( $get_options['enable'] ) && $get_options['enable'] ) {
				if ( isset( $get_options['list_mapping'] ) && is_array( $get_options['list_mapping'] ) ) {
					foreach ( $get_options['list_mapping'] as $lists ) {
						$user_role  = $this->user_has_role( $user_id, $lists['role'] );
						$user_email = isset( $user_data['user_email'] ) ? sanitize_email( $user_data['user_email'] ) : '';
						$first_name = isset( $user_data['first_name'] ) ? sanitize_text_field( $user_data['first_name'] ) : '';
						$last_name  = isset( $user_data['last_name'] ) ? sanitize_text_field( $user_data['last_name'] ) : '';

						$exist_email = ContactModel::is_contact_exist( $user_email );
						if ( $user_role && !$exist_email ) {
							$contact    = new ContactData(
								$user_email,
								array(
									'first_name' => $first_name,
									'last_name'  => $last_name,
									'status'     => MrmCommon::is_double_optin_enable() ? 'pending' : 'subscribed',
									'source'     => 'Signup',
									'wp_user_id' => $user_id,
								)
							);
							$contact_id = ContactModel::insert( $contact );
							if ( $contact_id ) {
								$is_ip_store = get_option( '_mint_compliance' );
								$is_ip_store = isset( $is_ip_store['anonymize_ip'] ) ? $is_ip_store['anonymize_ip'] : 'no';
								$meta_fields = array();
								if ( 'no' === $is_ip_store ) {
                                    $meta_fields['meta_fields']['_ip_address'] = MrmCommon::get_user_ip(); //phpcs:ignore
								}
								ContactModel::update_meta_fields( $contact_id, $meta_fields );

								$get_double_optin = get_option( '_mrm_optin_settings' );
								$is_double_optin  = isset( $get_double_optin['enable'] ) ? $get_double_optin['enable'] : true;
								if ( $is_double_optin ) {
									MessageController::get_instance()->send_double_opt_in( $contact_id );
								}
								$list_data = isset( $lists['list'] ) ? $lists['list'] : array();
								$ids       = array();
								foreach ( $list_data as $id ) {
									$ids[] = $id;
								}
								ContactGroupModel::set_tags_to_contact( $ids, $contact_id );
								ContactGroupModel::set_lists_to_contact( $ids, $contact_id );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Assign Commer User to Mint Email
	 *
	 * @param int|string   $comment_id User's comment id.
	 * @param bool         $comment_approved If comment is approved.
	 * @param array|object $commentdata Comment data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function assign_comment_post_user_in_contact( $comment_id, $comment_approved, $commentdata ) {
		$param = MrmCommon::get_sanitized_get_post();
		$post  = isset( $param['post'] ) ? $param['post'] : array();
		if ( isset( $post['mintmail_subscribe_on_comment'] ) && $post['mintmail_subscribe_on_comment'] ) {
			$get_options = get_option( '_mrm_general_comment_form_subscription' );
			if ( isset( $get_options[ 'enable' ] ) && $get_options[ 'enable' ] ) {
				$user_email = isset( $commentdata[ 'comment_author_email' ] ) ? $commentdata[ 'comment_author_email' ] : '';
				$user_data  = get_user_by( 'email', $user_email );
				$first_name = $user_data && isset( $user_data->first_name ) ? sanitize_text_field( $user_data->first_name ) : '';
				$last_name  = $user_data && isset( $user_data->last_name ) ? sanitize_text_field( $user_data->last_name ) : '';
				$source     = '';

				if ( isset( $commentdata[ 'comment_type' ] ) ) {
					if ( 'comment' === $commentdata[ 'comment_type' ] ) {
						$source = esc_html__( 'Post Comment', 'mrm' );
					} elseif ( 'review' === $commentdata[ 'comment_type' ] ) {
						$source = esc_html__( 'Product Review', 'mrm' );
					}
				}

				if ( '' !== $user_email ) {
					update_comment_meta( $comment_id, 'mint_subscribe_permission', sanitize_text_field( $post['mintmail_subscribe_on_comment'] ) );
					$exist_email = ContactModel::is_contact_exist( $user_email );
					if ( ! $exist_email ) {
						$contact    = new ContactData(
							$user_email,
							array(
								'first_name' => $first_name,
								'last_name'  => $last_name,
								'source'     => $source,
								'status'     => MrmCommon::is_double_optin_enable() ? 'pending' : 'subscribed',
							)
						);
						$contact_id = ContactModel::insert( $contact );
						if ( $contact_id ) {
							$is_ip_store = get_option( '_mint_compliance' );
							$is_ip_store = isset( $is_ip_store['anonymize_ip'] ) ? $is_ip_store['anonymize_ip'] : 'no';
							$meta_fields = array();
							if ( 'no' === $is_ip_store ) {
                                $meta_fields['meta_fields']['_ip_address'] = MrmCommon::get_user_ip(); //phpcs:ignore
							}
							ContactModel::update_meta_fields( $contact_id, $meta_fields );
							$get_double_optin = get_option( '_mrm_optin_settings' );
							$is_double_optin  = isset( $get_double_optin[ 'enable' ] ) ? $get_double_optin[ 'enable' ] : true;
							if ( $is_double_optin ) {
								MessageController::get_instance()->send_double_opt_in( $contact_id );
							}
							$group_tag  = isset( $get_options[ 'tags' ] ) ? $get_options[ 'tags' ] : array();
							$group_list = isset( $get_options[ 'lists' ] ) ? $get_options[ 'lists' ] : array();
							$group_data = array_merge( $group_tag, $group_list );
							$ids        = array();
							foreach ( $group_data as $id ) {
								$ids[] = $id;
							}
							ContactGroupModel::set_tags_to_contact( $ids, $contact_id );
							ContactGroupModel::set_lists_to_contact( $ids, $contact_id );
						}
					}
				}
			}
		}
	}

	/**
	 * Herlper function for user role
	 *
	 * @param string|int $user_id WP user id.
	 * @param string     $role_name User role name.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function user_has_role( $user_id, $role_name ) {
		$user_meta  = get_userdata( $user_id );
		$user_roles = $user_meta->roles;

		return in_array( $role_name, $user_roles, true );
	}

	/**
	 * Set Comment persmissin from user
	 *
	 * @param array $field Get Default Field.
	 * @return mixed|string
	 * @since 1.0.0
	 */
	public function mint_set_comment_permission_input_field_login( $field ) {
		$get_options = get_option( '_mrm_general_comment_form_subscription' );
		if ( isset( $get_options[ 'enable' ] ) && $get_options[ 'enable' ] ) {
			$label = isset( $get_options[ 'checkboxLabel' ] ) ? $get_options[ 'checkboxLabel' ] : 'Yes, add me to your Mintmail list';
			if ( is_user_logged_in() ) {
				$field .= '<p class="comment-form-mint-mail">
                              <label for="mint_mail_subscribe_on_comment">
                                <input type="checkbox" id="mint_mail_subscribe_on_comment" value="1" name="mintmail_subscribe_on_comment">' . $label . '
                              </label>
                            </p>';
			}
		}

		return $field;
	}

	/**
	 * Set Comment persmissin from user Get Default Field.
	 *
	 * @since 1.0.0
	 */
	public function mint_set_comment_permission_input_field_logout() {
		$options = get_option( '_mrm_general_comment_form_subscription' );

		if ( isset( $options['enable'] ) && $options['enable'] ) {
			$label = isset( $options['checkboxLabel'] ) ? $options['checkboxLabel'] : __( 'Add to Mintmail list', 'mrm' );
			?>
			<p class="comment-form-mint-mail">
				<label for="mint_mail_subscribe_on_comment">
					<input type="checkbox" id="mint_mail_subscribe_on_comment" value="1" name="mintmail_subscribe_on_comment"> <?php echo esc_html( $label ); ?>
				</label>
			</p>
			<?php
		}
	}

	/**
	 * Set signup form Permission.
	 *
	 * @return void
	 */
	public function mint_signup_form_permission_field_set() {
		$get_options = get_option( '_mrm_general_user_signup' );
		if ( isset( $get_options['enable'] ) && $get_options['enable'] ) {
			$label = isset( $get_options[ 'checkboxLabel' ] ) ? $get_options[ 'checkboxLabel' ] : 'Add Mintmail list';
			?>
			<p class="registration-form-mintmail"><label for="mintmail_subscribe_on_register"><input type="checkbox" id="mintmail_subscribe_on_register" value="1" name="mintmail_subscribe_on_register"/><?php echo esc_html( $label ); ?></label></p>
			<?php
		}
	}
}
