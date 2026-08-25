<?php
/**
 * Helpers functions for Contact modules
 *
 * @package /includes/contacts/
 */

use Mint\MRM\Admin\API\Controllers\MessageController;
use Mint\MRM\Admin\API\Controllers\TagController;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataStores\ContactData;
use Mint\MRM\DataStores\ListData;

if ( !function_exists( 'mailmint_resolve_contact_status' ) ) {
	/**
	 * Decide what status an existing contact should end up with.
	 *
	 * ContactModel::update() writes whatever `status` it is handed — the column is not in
	 * its unset() list — so a caller that passes a status unconditionally will overwrite a
	 * contact's subscription state. For an opt-in form that is correct; the submission *is*
	 * the consent. For a passive capture such as an abandoned cart it is not, and it
	 * silently destroys an unsubscribe.
	 *
	 * Withdrawn consent is therefore sticky here. Note this deliberately differs from
	 * FormAction::update_contact_data(), which lifts `unsubscribed` back to subscribed:
	 * that path has an explicit form submission behind it and this one does not.
	 *
	 * @param string $current   The status already stored for the contact.
	 * @param string $requested The status the caller wants to apply.
	 *
	 * @return string The status to write.
	 * @since 1.31.1
	 */
	function mailmint_resolve_contact_status( $current, $requested ) {
		$current   = is_string( $current ) ? $current : '';
		$requested = is_string( $requested ) ? $requested : '';

		// Nothing asked for, or no prior state to protect.
		if ( '' === $requested ) {
			return $current;
		}
		if ( '' === $current ) {
			return $requested;
		}

		// Withdrawn or undeliverable consent is never restored by a passive capture.
		$sticky = array( 'unsubscribed', 'bounced', 'complained' );
		if ( in_array( $current, $sticky, true ) ) {
			return $current;
		}

		// Never walk a confirmed contact back to unconfirmed.
		if ( 'subscribed' === $current && 'pending' === $requested ) {
			return $current;
		}

		/*
		 * 'transactional' is only ever *requested* by a passive capture — an abandoned
		 * checkout, an order — for an address that has not opted in. A contact who is
		 * already in the database has a status that came from somewhere with more
		 * standing (a form, an import, an admin edit, a double opt-in confirmation), so
		 * the capture must not narrow it. Concretely: a subscribed customer who abandons
		 * a cart keeps receiving campaigns, and a pending contact is not moved out of the
		 * double opt-in pipeline behind the site owner's back.
		 */
		if ( 'transactional' === $requested ) {
			return $current;
		}

		return $requested;
	}
}

if ( !function_exists( 'mailmint_create_multiple_contacts' ) ) {
	/**
	 * Create multiple contacts
	 *
	 * @param array $args Array data with multiple contacts information.
	 *
	 * @return bool
	 *
	 * @since 1.0.6
	 */
	function mailmint_create_multiple_contacts( $args = array() ) {
		foreach ( $args as $arg ) {
			if ( !empty( $arg[ 'email' ] ) ) {
				try {
					$contact_id = ContactModel::is_contact_exist( $arg[ 'email' ] );
					if ( !$contact_id ) {
						$contact    = new ContactData( $arg[ 'email' ], $arg );
						$contact_id = ContactModel::insert( $contact );

						if ( $contact_id && function_exists( 'mailmint_add_contact_to_groups' ) ) {
							if ( !empty( $arg[ 'lists' ] ) ) {
								mailmint_add_contact_to_groups( 'lists', $arg[ 'lists' ], $contact_id );
							}
							if ( !empty( $arg[ 'tags' ] ) ) {
								mailmint_add_contact_to_groups( 'tags', $arg[ 'tags' ], $contact_id );
							}
						}
					} else {
						$response = ContactModel::update( $arg, $contact_id );
						if ( $response && function_exists( 'mailmint_add_contact_to_groups' ) ) {
							if ( ! empty( $args[ 'lists' ] ) ) {
								mailmint_add_contact_to_groups( 'lists', $args[ 'lists' ], $contact_id );
							}
							if ( ! empty( $args[ 'tags' ] ) ) {
								mailmint_add_contact_to_groups( 'tags', $args[ 'tags' ], $contact_id );
							}
						}
					}
				} catch ( Exception $e ) {
					return false;
				}
			}
		}
		return true;
	}
}

if ( !function_exists( 'mailmint_create_single_contact' ) ) {
	/**
	 * Create single contact
	 *
	 * @param array $args Array data with single contact information.
	 *
	 * @return bool|int
	 *
	 * @since 1.0.6
	 */
	function mailmint_create_single_contact( $args = array() ) {
		/*
		 * Validate, don't just sanitize. sanitize_email() happily returns a half-typed
		 * address, and the abandoned cart path captures checkout fields as the customer
		 * types — so without this a contact gets created for "jo@gm" and the resulting
		 * hard bounce damages sending reputation for every campaign on the store, not
		 * just for cart recovery. This is the backstop for every caller, since an email
		 * can also reach a cart row via WooCommerceUserLogin.
		 */
		if ( empty( $args['email'] ) || !is_email( $args['email'] ) ) {
			return false;
		}

		if ( !empty( $args[ 'email' ] ) ) {
			try {
				$requested_status   = isset( $args['status'] ) ? $args['status'] : '';
				$args['status']     = $requested_status;
				$contact_id = ContactModel::is_contact_exist( $args[ 'email' ] );
				if ( !$contact_id ) {
					$contact    = new ContactData( $args[ 'email' ], $args );
					$contact_id = ContactModel::insert( $contact );
					if ( 'pending' === $args['status'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
					if ( $contact_id && function_exists( 'mailmint_add_contact_to_groups' ) ) {
						if ( !empty( $args[ 'lists' ] ) ) {
							mailmint_add_contact_to_groups( 'lists', $args[ 'lists' ], $contact_id );
						}
						if ( !empty( $args[ 'tags' ] ) ) {
							mailmint_add_contact_to_groups( 'tags', $args[ 'tags' ], $contact_id );
						}
					}
					return $contact_id;
				} else {
					/*
					 * Resolve the status against what the contact already has before
					 * updating. ContactModel::update() does not unset 'status', so passing
					 * $args through unchanged would overwrite an unsubscribe — and
					 * ContactModel also stamps status_changed meta, making the overwrite
					 * look like a legitimate opt-in in the contact's history.
					 */
					$existing            = ContactModel::get( $contact_id );
					$existing_status     = isset( $existing['status'] ) ? $existing['status'] : '';
					$resolved_status     = mailmint_resolve_contact_status( $existing_status, $requested_status );
					$args['status']      = $resolved_status;

					$response = ContactModel::update( $args, $contact_id );

					// Only re-send confirmation when this update actually left them pending.
					if ( 'pending' === $resolved_status ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
					if ( $response && function_exists( 'mailmint_add_contact_to_groups' ) ) {
						if ( !empty( $args[ 'lists' ] ) ) {
							mailmint_add_contact_to_groups( 'lists', $args[ 'lists' ], $contact_id );
						}
						if ( !empty( $args[ 'tags' ] ) ) {
							mailmint_add_contact_to_groups( 'tags', $args[ 'tags' ], $contact_id );
						}
						return $contact_id;
					}
					return false;
				}
			} catch ( Exception $e ) {
				return false;
			}
		}
		return false;
	}
}

if ( !function_exists( 'mailmint_create_multiple_contact_groups' ) ) {
	/**
	 * Create multiple contact groups - lists/tags
	 *
	 * @param string $type Group type [lists,tags].
	 * @param array  $args Array data with multiple contact groups information.
	 *
	 * @return bool
	 *
	 * @since 1.0.6
	 */
	function mailmint_create_multiple_contact_groups( $type, $args = array() ) {
		if ( ( 'lists' === $type || 'tags' === $type ) && !empty( $args ) ) {
			foreach ( $args as $arg ) {
				try {
					if ( !empty( $arg[ 'title' ] ) ) {
						$group_id = ContactGroupModel::is_group_exists( $arg[ 'title' ], $type );
						$group    = new ListData( $arg );
						if ( !$group_id ) {
							ContactGroupModel::insert( $group, $type );
						} else {
							ContactGroupModel::update( $group, $group_id, $type );
						}
					}
				} catch ( Exception $e ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}
}

if ( !function_exists( 'mailmint_create_single_contact_group' ) ) {
	/**
	 * Create single contact
	 *
	 * @param string $type Group type [lists,tags].
	 * @param array  $args Array data with single contact group information.
	 *
	 * @return bool|int
	 *
	 * @since 1.0.6
	 */
	function mailmint_create_single_contact_group( $type, $args = array() ) {
		if ( ( 'lists' === $type || 'tags' === $type ) && !empty( $args[ 'title' ] ) ) {
			try {
				$group_id = ContactGroupModel::is_group_exists( $args[ 'title' ], $type );
				$group    = new ListData( $args );
				if ( !$group_id ) {
					return ContactGroupModel::insert( $group, $type );
				} else {
					$response = ContactGroupModel::update( $group, $group_id, $type );
					return $response ? $group_id : false;
				}
			} catch ( Exception $e ) {
				return false;
			}
		}
		return false;
	}
}

if ( !function_exists( 'mailmint_add_contact_to_groups' ) ) {
	/**
	 * Assign group ids  [lists/tags] to a specific contact
	 *
	 * @param string     $type Group type [lists/tags].
	 * @param array      $group_ids Group ids [lists/tags].
	 * @param string|int $contact_id Contact id.
	 *
	 * @return void
	 */
	function mailmint_add_contact_to_groups( $type, $group_ids, $contact_id ) {
		if ( !empty( $group_ids ) ) {
			$groups = array();
			foreach ( $group_ids as $group_id ) {
				$groups[] = array( 'id' => $group_id );
			}
			if ( !empty( $groups ) ) {
				if ( 'lists' === $type ) {
					ContactGroupModel::set_lists_to_contact( $groups, $contact_id );
				} elseif ( 'tags' === $type ) {
					ContactGroupModel::set_tags_to_contact( $groups, $contact_id );
				}
			}
		}
	}
}
