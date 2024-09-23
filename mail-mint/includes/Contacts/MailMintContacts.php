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
		if ( !empty( $args[ 'email' ] ) ) {
			try {
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
					$response = ContactModel::update( $args, $contact_id );
					if ( 'pending' === $args['status'] ) {
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
