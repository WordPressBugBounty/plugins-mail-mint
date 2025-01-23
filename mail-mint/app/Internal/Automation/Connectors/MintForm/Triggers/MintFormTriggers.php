<?php
/**
 * WordPress triggers
 *
 * @package MintMail\App\Internal\Automation\Connector\trigger
 */

namespace MintMail\App\Internal\Automation\Connector\trigger;

use Mint\MRM\DataBase\Models\ContactModel;
use Mint\Mrm\Internal\Traits\Singleton;
use MintMail\App\Internal\Automation\HelperFunctions;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;

/**
 * WordPress triggers
 *
 * @package MintMail\App\Internal\Automation\Connector
 */
class MintFormTriggers {

	use Singleton;


	/**
	 * Connector name
	 *
	 * @var $connector_name
	 */
	public $connector_name = 'MintForm';


	/**
	 * Initialization of WordPress hooks
	 */
	public function init() {
		add_action( 'mailmint_after_form_submit', array( $this, 'mrm_after_form_submission' ), 10, 3 );
		add_action( 'mailmint_tag_applied', array( $this, 'mrm_after_tag_applied' ), 10, 2 );
		add_action( 'mailmint_list_applied', array( $this, 'mrm_after_list_applied' ), 10, 2 );
		add_action( 'mint_list_removed', array( $this, 'mrm_after_contact_removed_from_list' ), 10, 2 );
		add_action( 'mint_tag_removed', array( $this, 'mrm_after_contact_removed_from_tag' ), 10, 2 );
	}


	/**
	 * Validate trigger settings
	 *
	 * @param array $step_data Get Step Data.
	 * @param array $data Get all Data.
	 * @return bool
	 */
	public function validate_settings( $step_data, $data ) {
		$step_data    = HelperFunctions::get_step_data( $step_data['automation_id'], $step_data['step_id'] );
		$trigger_name = isset( $data['trigger_name'] ) ? $data['trigger_name'] : '';
		if ( 'mint_tag_applied' === $trigger_name || 'mint_list_applied' === $trigger_name ) {
			$group_name = '';
			if ( 'mint_tag_applied' === $trigger_name ) {
				$group_name = 'tag_applied';
			}
			if ( 'mint_list_applied' === $trigger_name ) {
				$group_name = 'list_applied';
			}
			if ( isset( $step_data['settings'] ) ) {
				if ( isset( $step_data['settings'][ $group_name ]['type'] ) && 'any' === $step_data['settings'][ $group_name ]['type'] ) {
					return true;
				} elseif ( isset( $step_data['settings'][ $group_name ]['type'] ) && 'selected' === $step_data['settings'][ $group_name ]['type'] ) {
					$new_groups    = isset( $data['data']['new_groups'] ) ? $data['data']['new_groups'] : array();
					$new_group_ids = array_column( $new_groups, 'id' );
					$new_group_ids = empty( $new_group_ids ) ? $new_groups : $new_group_ids;

					$old_group_ids = array_column( $data['data']['groups'], 'id' );
					$old_group_ids = empty( $old_group_ids ) ? $data['data']['groups'] : $old_group_ids;

					if ( isset( $step_data['settings'][ $group_name ]['group'] ) && is_array( $step_data['settings'][ $group_name ]['group'] ) ) {
						foreach ( $step_data['settings'][ $group_name ]['group'] as $group ) {
							$key = array_search( $group['id'], $old_group_ids ); //phpcs:ignore

							// Check if $group['id'] also exists in $filtered_key.
							$filtered_key = array_search( $group['id'], $new_group_ids ); //phpcs:ignore

							if ( false !== $key && false !== $filtered_key ) {
								// The ID exists, return true immediately.
								return true;
							}
						}

						// If none of the IDs were found, return false after the loop.
						return false;
					}
				}
				return false;
			}
		}

		// Check if the trigger is mint_list_removed.
		if ( 'mint_list_removed' === $trigger_name ) {
			if ( isset( $step_data['settings'] ) ) {
				if ( isset( $step_data['settings']['list_removed']['type'] ) && 'any' === $step_data['settings']['list_removed']['type'] ) {
					return true;
				} elseif ( isset( $step_data['settings']['list_removed']['type'] ) && 'selected' === $step_data['settings']['list_removed']['type'] ) {
					$removed_groups = isset( $data['data']['groups'] ) ? $data['data']['groups'] : array();
					$setting_groups = isset( $step_data['settings']['list_removed']['group'] ) ? $step_data['settings']['list_removed']['group'] : array();

					if ( is_array( $setting_groups ) && !empty( $setting_groups ) ) {
						foreach ( $setting_groups as $group ) {
							$key = array_search( $group['id'], $removed_groups ); //phpcs:ignore
							if ( false !== $key ) {
								// The ID exists, return true immediately.
								return true;
							}
						}
						// If none of the IDs were found, return false after the loop.
						return false;
					}
				}
				return false;
			}
		}

		// Check if the trigger is mint_tag_removed.
		if ( 'mint_tag_removed' === $trigger_name ) {
			if ( isset( $step_data['settings'] ) ) {
				if ( isset( $step_data['settings']['tag_removed']['type'] ) && 'any' === $step_data['settings']['tag_removed']['type'] ) {
					return true;
				} elseif ( isset( $step_data['settings']['tag_removed']['type'] ) && 'selected' === $step_data['settings']['tag_removed']['type'] ) {
					$removed_groups = isset( $data['data']['groups'] ) ? $data['data']['groups'] : array();
					$setting_groups = isset( $step_data['settings']['tag_removed']['group'] ) ? $step_data['settings']['tag_removed']['group'] : array();

					if ( is_array( $setting_groups ) && !empty( $setting_groups ) ) {
						foreach ( $setting_groups as $group ) {
							$key = array_search( $group['id'], $removed_groups ); //phpcs:ignore
							if ( false !== $key ) {
								// The ID exists, return true immediately.
								return true;
							}
						}

						// If none of the IDs were found, return false after the loop.
						return false;
					}
				}
				return false;
			}
		}

		if ( 'mint_form_submission' === $trigger_name && !empty( $step_data['settings']['mailmint_form_settings']['form_id'] ) ) {
			
			$form_id          = $step_data['settings']['mailmint_form_settings']['form_id'];
			$settings_form_id = is_array($form_id) && isset($form_id['value']) ? $form_id['value'] : $form_id;
			return $data['data']['form_id'] === $settings_form_id; //phpcs:ignore
		}
		

		return true;
	}


	/**
	 * Mail Mint form submission
	 *
	 * @param int    $form_id Submitted Form ID.
	 * @param int    $contact_id Get Contact ID.
	 * @param object $contact Get contact Array.
	 */
	public function mrm_after_form_submission( $form_id, $contact_id, $contact ) {
		$data = array(
			'connector_name' => $this->connector_name,
			'trigger_name'   => 'mint_form_submission',
			'data'           => array(
				'user_email' => $contact->get_email(),
				'first_name' => $contact->get_first_name(),
				'last_name'  => $contact->get_last_name(),
				'form_id'    => $form_id,
			),
		);
		do_action( MINT_TRIGGER_AUTOMATION, $data );
	}


	/**
	 * Mint list applied
	 *
	 * @param array $lists Get lists array.
	 * @param int   $contact_ids Get Contact IDs.
	 */
	public function mrm_after_list_applied( $lists, $contact_ids ) {
		$contacts = is_array( $contact_ids ) ? $contact_ids : array( $contact_ids );
		foreach ( $contacts as $contact_id ) {
			$contact   = ContactModel::get( $contact_id );
			$new_lists = array_filter(
				$lists,
				function ( $list ) {
					return !isset( $list['created_at'] );
				}
			);

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'mint_list_applied',
				'data'           => array(
					'user_email' => isset( $contact['email'] ) ? $contact['email'] : '',
					'first_name' => isset( $contact['first_name'] ) ? $contact['first_name'] : '',
					'last_name'  => isset( $contact['last_name'] ) ? $contact['last_name'] : '',
					'groups'     => $lists,
					'new_groups' => $new_lists,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}

	/**
	 * Mint tag applied
	 *
	 * @param array $tags Get tags Array.
	 * @param int   $contact_ids Get Contact IDs.
	 */
	public function mrm_after_tag_applied( $tags, $contact_ids ) {
		$contacts = is_array( $contact_ids ) ? $contact_ids : array( $contact_ids );
		foreach ( $contacts as $contact_id ) {
			$contact  = ContactModel::get( $contact_id );
			$new_tags = array_filter(
				$tags,
				function ( $tag ) {
					return !isset( $tag['created_at'] );
				}
			);

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'mint_tag_applied',
				'data'           => array(
					'user_email' => isset( $contact['email'] ) ? $contact['email'] : '',
					'first_name' => isset( $contact['first_name'] ) ? $contact['first_name'] : '',
					'last_name'  => isset( $contact['last_name'] ) ? $contact['last_name'] : '',
					'groups'     => $tags,
					'new_groups' => $new_tags,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}

	/**
	 * Executes automation triggers after contacts are removed from one or more mailing lists.
	 *
	 * This method is triggered after one or more contacts are removed from one or more mailing lists.
	 *
	 * @param array $groups       An array of mailing list IDs from which the contacts were removed.
	 * @param array $contact_ids  An array containing the IDs of contacts that were removed from the mailing lists.
	 * @since 1.9.4
	 */
	public function mrm_after_contact_removed_from_list( $groups, $contact_ids ) {
		foreach ( $contact_ids as $contact_id ) {
			$contact  = ContactModel::get( $contact_id );
			$is_exist = ContactGroupPivotModel::is_group_exist_to_contact( $contact_id, $groups );

			if ( !$is_exist ) {
				continue;
			}

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'mint_list_removed',
				'data'           => array(
					'user_email' => isset( $contact['email'] ) ? $contact['email'] : '',
					'first_name' => isset( $contact['first_name'] ) ? $contact['first_name'] : '',
					'last_name'  => isset( $contact['last_name'] ) ? $contact['last_name'] : '',
					'groups'     => $groups,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}

	/**
	 * Executes automation triggers after contacts are removed from one or more mailing tags.
	 *
	 * This method is triggered after one or more contacts are removed from one or more mailing tags.
	 *
	 * @param array $groups       An array of mailing list IDs from which the contacts were removed.
	 * @param array $contact_ids  An array containing the IDs of contacts that were removed from the mailing tags.
	 * @since 1.9.4
	 */
	public function mrm_after_contact_removed_from_tag( $groups, $contact_ids ) {
		foreach ( $contact_ids as $contact_id ) {
			$contact  = ContactModel::get( $contact_id );
			$is_exist = ContactGroupPivotModel::is_group_exist_to_contact( $contact_id, $groups );

			if ( !$is_exist ) {
				continue;
			}

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'mint_tag_removed',
				'data'           => array(
					'user_email' => isset( $contact['email'] ) ? $contact['email'] : '',
					'first_name' => isset( $contact['first_name'] ) ? $contact['first_name'] : '',
					'last_name'  => isset( $contact['last_name'] ) ? $contact['last_name'] : '',
					'groups'     => $groups,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}
}

