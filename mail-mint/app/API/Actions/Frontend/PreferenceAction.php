<?php
/**
 * Preference Controller's actions
 *
 * Handles requests to the Frontend endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\API\Actions;

use Mint\MRM\API\Actions\Action;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\MRM\DataBase\Models\EmailModel;


/**
 * This is the class that controls the Preference action. Responsibilities are:
 * Update preference
 */
class PreferenceAction implements Action {

	/**
	 * Preference update from email
	 *
	 * @param array $params Parameter.
	 * @return array
	 * @since 1.0.0
	 */
	public function update_preference( $params ) {
		$response = array(
			'status'  => 'failed',
			'message' => 'Something went wrong. Please try again!',
		);
		if ( empty( $params['post_data'] ) ) {
			return $response;
		}
        $get_param_data = wp_unslash( $params['post_data'] ); //phpcs:ignore
		parse_str( $get_param_data, $post_data );

		$pref_data = array();

		if ( !empty( $post_data ) ) {
			foreach ( $post_data as $key => $value ) {
				switch ( $key ) {
					case 'last_name':
					case 'first_name':
					case 'status':
						$pref_data[ $key ] = sanitize_text_field( $value );
						break;
				}
			}
		}

		$list_ids = isset( $post_data['mrm_list'] ) ? $post_data['mrm_list'] : array();

		$contact_id = 0;
		if ( ! empty( $post_data[ 'contact_hash' ] ) ) {
			$contact_id = EmailModel::get_contact_id_by_hash( $post_data[ 'contact_hash' ] );
			if ( ! empty( $contact_id[ 'contact_id' ] ) ) {
				$contact_id = $contact_id[ 'contact_id' ];
			} else {
				$contact_id = ContactModel::get_by_hash( $post_data[ 'contact_hash' ] );
				if ( ! empty( $contact_id[ 'id' ] ) ) {
					$contact_id = $contact_id[ 'id' ];
				}
			}
		}

		if ( $contact_id ) {
			$contact   = ContactModel::get( $contact_id );
			$assign_id = array();
			if ( $contact ) {
				$assign_list = ContactGroupModel::get_lists_to_contact( $contact );
				if ( ! empty( $assign_list[ 'lists' ] ) ) {
					foreach ( $assign_list[ 'lists' ] as $list ) {
						$assign_id[] = $list->id;
					}
				}
			}

			$settings = get_option( '_mrm_general_preference', array() );

			if ( !empty( $settings[ 'preference' ] ) ) {
				if ( 'contact-manage-following' === $settings[ 'preference' ] ) {
					$settings_lists_ids = isset( $settings[ 'lists' ] ) ? array_column( $settings[ 'lists' ], 'id' ) : array();

					$_removed_ids = array();
					foreach ( $settings_lists_ids as $_id ) {
						if ( ! in_array( $_id, $list_ids, true ) ) {
							$_removed_ids[] = $_id;
						}
					}

					if ( ! empty( $_removed_ids ) ) {
						ContactGroupPivotModel::delete_groups_to_contact( $contact_id, $_removed_ids );
					}
				} elseif ( 'contact-manage' === $settings[ 'preference' ] ) {
					ContactGroupPivotModel::delete_groups_to_contact( $contact_id, $assign_id );
				}
			}

			$update_contact = ContactModel::update( $pref_data, $contact_id );
			if ( $update_contact ) {
				$pivot_res = empty( $list_ids ) || ContactGroupModel::set_tags_to_contact( $list_ids, $contact_id );
				if ( $pivot_res ) {
					$response = array(
						'status'  => 'success',
						'message' => __( 'Preference Has Been Updated Successfully.', 'mrm' ),
					);
				}
			}
		}
		return $response;
	}
}
