<?php
/**
 * ImportService — Handles contact import logic for WooCommerce, Mailchimp, and EDD.
 *
 * Mechanically extracted from ContactController to keep the controller thin.
 * Uses ContactRepository for contact creation and existence checks instead of
 * ContactModel::insert() and ContactModel::is_contact_exist().
 *
 * @package Mint\MRM\Internal\Import
 * @since   1.19.5
 */

namespace Mint\MRM\Internal\Import;

use Mint\MRM\Database\Enums\ContactStatus;
use Mint\MRM\Database\Repositories\ContactRepository;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\Admin\API\Controllers\MessageController;
use Mint\MRM\Utilites\Helper\Import;
use MRM\Common\MrmCommon;

/**
 * Class ImportService
 *
 * @since 1.19.5
 */
class ImportService {

	/**
	 * Contact repository instance.
	 *
	 * @var ContactRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.19.5
	 *
	 * @param ContactRepository $repository Contact repository instance.
	 */
	public function __construct( ContactRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Import contacts from WooCommerce customers.
	 *
	 * @since 1.19.5
	 *
	 * @param array $params {
	 *     Import parameters from the REST request.
	 *
	 *     @type array  $map                 Field mappings [{source, target}].
	 *     @type int    $offset              Pagination offset.
	 *     @type array  $status              Contact status array.
	 *     @type string $created_by          Creator user ID.
	 *     @type array  $tags                Tag IDs to assign.
	 *     @type array  $lists               List IDs to assign.
	 *     @type bool   $skip_existing       Whether to skip existing contacts.
	 *     @type bool   $optin_confirmation  Whether to send opt-in to existing contacts.
	 *     @type bool   $automation_control  Whether to disable automation triggers.
	 * }
	 *
	 * @return array {
	 *     @type int $imported          Number of newly imported contacts.
	 *     @type int $total             Total WooCommerce customers in batch.
	 *     @type int $skipped           Number of skipped contacts.
	 *     @type int $existing_contacts Number of existing contacts found.
	 *     @type int $offset            Next offset for pagination.
	 * }
	 *
	 * @throws \Exception If import fails.
	 */
	public function importFromWooCommerce( array $params ) {
		$imported     = 0;
		$skipped      = 0;
		$exists_count = 0;
		$total_count  = 0;

		/**
		 * Get the import batch limit per operation.
		 *
		 * @param int $per_batch The default import batch limit per operation.
		 * @return int The modified import batch limit per operation.
		 *
		 * @since 1.5.0
		 */
		$per_batch = apply_filters( 'mint_import_batch_limit', 500 );

		if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
		}

		if ( isset( $params['map'] ) && empty( $params['map'] ) ) {
			return array( 'error' => __( 'Please map at least one field for importing.', 'mrm' ), 'code' => 400 );
		}
		$mappings = isset( $params['map'] ) ? $params['map'] : array();

		$wc_customers = Import::get_wc_customers( $params['offset'], $per_batch );

		foreach ( $wc_customers as $wc_customer ) {
			if ( isset( $wc_customer ) ) {
				$contact_email = $wc_customer['billing_email'];
			}
			if ( ! is_email( $contact_email ) ) {
				$skipped++;
				continue;
			}

			$status     = isset( $params['status'] ) ? $params['status'][0] : '';
			$status     = ! empty( $status ) ? $status : 'pending';
			$created_by = isset( $params['created_by'] ) ? $params['created_by'] : '';

			$contact_args = array(
				'status'      => $status,
				'source'      => 'WooCommerce',
				'meta_fields' => array(),
				'created_by'  => $created_by,
			);

			foreach ( $mappings as $map ) {
				$target = isset( $map['target'] ) ? $map['target'] : '';
				$source = isset( $map['source'] ) ? $map['source'] : '';

				if ( in_array( $target, array( 'first_name', 'last_name', 'email' ), true ) ) {
					$contact_args[ $target ] = $wc_customer[ $source ];
				} else {
					$contact_args['meta_fields'][ $target ] = isset( $wc_customer[ $source ] ) ? $wc_customer[ $source ] : '';
				}
			}
			if ( ! array_key_exists( 'email', $contact_args ) ) {
				return array( 'error' => __( 'The email field is required.', 'mrm' ), 'code' => 400 );
			}
			$contact_email = trim( $contact_args['email'] );

			$exists = $this->repository->isContactExist( $contact_email );
			if ( ! $exists ) {
				$contact_id = $this->repository->create( $contact_args );
				// The repository stores only contact columns, so meta fields are saved separately.
				ContactModel::update_meta_fields( $contact_id, $contact_args );

				if ( 'pending' === $status ) {
					MessageController::get_instance()->send_double_opt_in( $contact_id );
				}

				if ( isset( $params['tags'] ) ) {
					ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
				}

				if ( isset( $params['lists'] ) ) {
					ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
				}
				$imported++;
			} else {
				if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
					$skipped++;
					$total_count++;
					continue;
				}

				$contact_id     = ContactModel::get_id_by_email( $contact_email );
				$contact_update = ContactModel::update( Import::remove_non_overwritable_fields( $contact_args, $params ), $contact_id );

				if ( 'pending' === $status && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
					MessageController::get_instance()->send_double_opt_in( $contact_id );
				}

				if ( isset( $params['tags'] ) ) {
					ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
				}

				if ( isset( $params['lists'] ) ) {
					ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
				}
				$exists_count++;
			}
			$total_count++;
		}

		if ( $imported > 0 ) {
			do_action( 'mailmint_contacts_imported', $imported, 'WooCommerce' );
		}

		return array(
			'imported'          => $imported,
			'total'             => count( $wc_customers ),
			'skipped'           => $skipped,
			'existing_contacts' => $exists_count,
			'offset'            => $params['offset'] + (int) $per_batch,
		);
	}

	/**
	 * Import contacts from Mailchimp.
	 *
	 * @since 1.19.5
	 *
	 * @param array $params {
	 *     Import parameters from the REST request.
	 *
	 *     @type array  $map                 Field mappings [{source, target}].
	 *     @type int    $offset              Pagination offset.
	 *     @type string $list_id             Mailchimp list/audience ID.
	 *     @type string $key                 Mailchimp API key.
	 *     @type string $status              Contact status.
	 *     @type string $created_by          Creator user ID.
	 *     @type array  $tags                Tag IDs to assign.
	 *     @type array  $lists               List IDs to assign.
	 *     @type bool   $optin_confirmation  Whether to send opt-in to existing contacts.
	 *     @type bool   $automation_control  Whether to disable automation triggers.
	 * }
	 *
	 * @return array Result array or error array with 'error' and 'code' keys.
	 *
	 * @throws \Exception If import fails.
	 */
	public function importFromMailchimp( array $params ) {
		$imported    = 0;
		$skipped     = 0;
		$exists      = 0;
		$total_count = 0;

		if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
		}

		if ( isset( $params['map'] ) && empty( $params['map'] ) ) {
			return array( 'error' => __( 'Please map at least one field for importing.', 'mrm' ), 'code' => 400 );
		}

		$list_id = isset( $params['list_id'] ) ? $params['list_id'] : '';
		if ( empty( $list_id ) ) {
			return array( 'error' => __( 'Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.', 'mrm' ), 'code' => 400 );
		}

		$key = isset( $params['key'] ) ? $params['key'] : '';
		if ( empty( $key ) ) {
			return array( 'error' => __( 'Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.', 'mrm' ), 'code' => 400 );
		}

		$response = Import::get_mailchimp_response( $key, "lists/{$list_id}/members", $params['offset'] );
		$members  = isset( $response['members'] ) ? $response['members'] : array();

		if ( empty( $members ) ) {
			return array(
				'total'             => 0,
				'skipped'           => 0,
				'existing_contacts' => 0,
				'imported'          => 0,
			);
		}

		foreach ( $members as $row ) {
			$status     = isset( $params['status'] ) ? $params['status'] : '';
			$created_by = isset( $params['created_by'] ) ? $params['created_by'] : '';

			$contact_args = array(
				'status'      => $status,
				'source'      => 'Mailchimp',
				'meta_fields' => array(),
				'created_by'  => $created_by,
			);

			foreach ( $params['map'] as $map ) {
				$target = isset( $map['target'] ) ? $map['target'] : '';
				$source = isset( $map['source'] ) ? $map['source'] : '';

				if ( in_array( $target, array( 'email' ), true ) ) {
					$contact_args[ $target ] = $row[ $source ];
				} elseif ( in_array( $target, array( 'first_name', 'last_name' ), true ) ) {
					$contact_args[ $target ] = $row['merge_fields'][ $source ];
				} elseif ( in_array( $source, array( 'addr1', 'addr2', 'city', 'state', 'zip', 'country' ), true ) ) {
					$contact_args['meta_fields'][ $target ] = isset( $row['merge_fields']['ADDRESS'][ $source ] ) ? $row['merge_fields']['ADDRESS'][ $source ] : '';
				} else {
					$contact_args['meta_fields'][ $target ] = $row['merge_fields'][ $source ];
				}
			}

			if ( ! array_key_exists( 'email', $contact_args ) ) {
				return array( 'error' => __( 'The email field is required.', 'mrm' ), 'code' => 400 );
			}
			$contact_email = trim( $contact_args['email'] );
			if ( $contact_email && is_email( $contact_email ) ) {
				$is_exists = $this->repository->isContactExist( $contact_email );
				if ( ! $is_exists ) {
					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = $this->repository->create( $contact_args );
					// The repository stores only contact columns, so meta fields are saved separately.
					ContactModel::update_meta_fields( $contact_id, $contact_args );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$imported++;
				} else {
					if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
						$skipped++;
						$total_count++;
						continue;
					}

					$contact_args   = $this->resolveContactStatus( $contact_args );
					$contact_id     = ContactModel::get_id_by_email( $contact_email );
					$contact_update = ContactModel::update( Import::remove_non_overwritable_fields( $contact_args, $params ), $contact_id );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}

					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$exists++;
				}
			} else {
				$skipped++;
			}
			$total_count++;
		}

		if ( $imported > 0 ) {
			do_action( 'mailmint_contacts_imported', $imported, 'Mailchimp' );
		}

		return array(
			'total'             => $total_count,
			'skipped'           => $skipped,
			'existing_contacts' => $exists,
			'imported'          => $imported,
		);
	}

	/**
	 * Import one batch of MailerLite subscribers.
	 *
	 * MailerLite pages subscribers by cursor, so each batch returns the cursor of the next one
	 * (null once the last batch is read).
	 *
	 * @since 1.32.0
	 *
	 * @param array $params {
	 *     Import parameters from the REST request.
	 *
	 *     @type array  $map                 Field mappings [{source, target, overwrite}].
	 *     @type string $cursor              Cursor of the batch to read; empty for the first batch.
	 *     @type string $group_id            MailerLite group ID, or "all" for every subscriber.
	 *     @type string $key                 MailerLite API key.
	 *     @type string $status              Contact status.
	 *     @type string $created_by          Creator user ID.
	 *     @type array  $tags                Tag IDs to assign.
	 *     @type array  $lists               List IDs to assign.
	 *     @type bool   $skip_existing       Whether to skip existing contacts.
	 *     @type bool   $optin_confirmation  Whether to send opt-in to existing contacts.
	 *     @type bool   $automation_control  Whether to disable automation triggers.
	 * }
	 *
	 * @return array Result array (with the next 'cursor') or error array with 'error' and 'code' keys.
	 *
	 * @throws \Exception If import fails.
	 */
	public function importFromMailerlite( array $params ) {
		$imported    = 0;
		$skipped     = 0;
		$exists      = 0;
		$total_count = 0;

		if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
		}

		if ( empty( $params['map'] ) ) {
			return array( 'error' => __( 'Please map at least one field for importing.', 'mrm' ), 'code' => 400 );
		}

		$group_id = isset( $params['group_id'] ) ? (string) $params['group_id'] : '';
		if ( '' === $group_id ) {
			return array( 'error' => __( 'Please select a MailerLite group to import.', 'mrm' ), 'code' => 400 );
		}

		$key = isset( $params['key'] ) ? $params['key'] : '';
		if ( empty( $key ) ) {
			return array( 'error' => __( 'Your MailerLite API key is invalid.', 'mrm' ), 'code' => 400 );
		}

		$endpoint = 'all' === $group_id ? 'subscribers' : 'groups/' . rawurlencode( $group_id ) . '/subscribers';
		$query    = array( 'limit' => 100 );
		if ( ! empty( $params['cursor'] ) ) {
			$query['cursor'] = $params['cursor'];
		}

		$response = Import::get_mailerlite_response( $key, $endpoint, $query );
		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return array( 'error' => $response['message'], 'code' => 401 );
		}

		$members = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$cursor  = isset( $response['meta']['next_cursor'] ) ? $response['meta']['next_cursor'] : null;

		if ( empty( $members ) ) {
			return array(
				'total'             => 0,
				'skipped'           => 0,
				'existing_contacts' => 0,
				'imported'          => 0,
				'cursor'            => null,
			);
		}

		foreach ( $members as $row ) {
			$status     = isset( $params['status'] ) ? $params['status'] : '';
			$created_by = isset( $params['created_by'] ) ? $params['created_by'] : '';
			$fields     = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();

			$contact_args = array(
				'status'      => $status,
				'source'      => 'MailerLite',
				'meta_fields' => array(),
				'created_by'  => $created_by,
			);

			foreach ( $params['map'] as $map ) {
				$target = isset( $map['target'] ) ? $map['target'] : '';
				$source = isset( $map['source'] ) ? $map['source'] : '';

				if ( 'email' === $source ) {
					$value = isset( $row['email'] ) ? $row['email'] : '';
				} else {
					$value = isset( $fields[ $source ] ) ? (string) $fields[ $source ] : '';
				}

				if ( in_array( $target, array( 'email', 'first_name', 'last_name' ), true ) ) {
					$contact_args[ $target ] = $value;
				} else {
					$contact_args['meta_fields'][ $target ] = $value;
				}
			}

			if ( ! array_key_exists( 'email', $contact_args ) ) {
				return array( 'error' => __( 'The email field is required.', 'mrm' ), 'code' => 400 );
			}
			$contact_email = trim( $contact_args['email'] );
			if ( $contact_email && is_email( $contact_email ) ) {
				$is_exists = $this->repository->isContactExist( $contact_email );
				if ( ! $is_exists ) {
					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = $this->repository->create( $contact_args );
					// The repository stores only contact columns, so meta fields are saved separately.
					ContactModel::update_meta_fields( $contact_id, $contact_args );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$imported++;
				} else {
					if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
						$skipped++;
						$total_count++;
						continue;
					}

					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = ContactModel::get_id_by_email( $contact_email );
					ContactModel::update( Import::remove_non_overwritable_fields( $contact_args, $params ), $contact_id );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}

					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$exists++;
				}
			} else {
				$skipped++;
			}
			$total_count++;
		}

		if ( $imported > 0 ) {
			do_action( 'mailmint_contacts_imported', $imported, 'MailerLite' );
		}

		return array(
			'total'             => $total_count,
			'skipped'           => $skipped,
			'existing_contacts' => $exists,
			'imported'          => $imported,
			'cursor'            => $cursor,
		);
	}

	/**
	 * Import one batch (page) of active Drip subscribers.
	 *
	 * @since 1.32.0
	 *
	 * @param array $params {
	 *     Import parameters from the REST request.
	 *
	 *     @type array  $map                 Field mappings [{source, target, overwrite}].
	 *     @type int    $page                Drip page to read, starting at 1.
	 *     @type string $account_id          Drip account ID.
	 *     @type string $tag                 Drip tag to import, or "all" for every subscriber.
	 *     @type string $token               Drip API token.
	 *     @type string $status              Contact status.
	 *     @type string $created_by          Creator user ID.
	 *     @type array  $tags                Tag IDs to assign.
	 *     @type array  $lists               List IDs to assign.
	 *     @type bool   $skip_existing       Whether to skip existing contacts.
	 *     @type bool   $optin_confirmation  Whether to send opt-in to existing contacts.
	 *     @type bool   $automation_control  Whether to disable automation triggers.
	 * }
	 *
	 * @return array Result array or error array with 'error' and 'code' keys.
	 *
	 * @throws \Exception If import fails.
	 */
	public function importFromDrip( array $params ) {
		$imported    = 0;
		$skipped     = 0;
		$exists      = 0;
		$total_count = 0;

		if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
		}

		if ( empty( $params['map'] ) ) {
			return array( 'error' => __( 'Please map at least one field for importing.', 'mrm' ), 'code' => 400 );
		}

		$account_id = isset( $params['account_id'] ) ? sanitize_text_field( $params['account_id'] ) : '';
		if ( '' === $account_id ) {
			return array( 'error' => __( 'Please select a Drip account to import.', 'mrm' ), 'code' => 400 );
		}

		$token = isset( $params['token'] ) ? sanitize_text_field( $params['token'] ) : '';
		if ( empty( $token ) ) {
			return array( 'error' => __( 'Your Drip API token is invalid.', 'mrm' ), 'code' => 400 );
		}

		$query = array(
			'status'   => 'active',
			'page'     => isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1,
			'per_page' => 100,
		);
		$tag   = isset( $params['tag'] ) ? sanitize_text_field( $params['tag'] ) : '';
		if ( '' !== $tag && 'all' !== $tag ) {
			$query['tags'] = $tag;
		}

		$response = Import::get_drip_response( $token, rawurlencode( $account_id ) . '/subscribers', $query );
		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return array( 'error' => $response['message'], 'code' => 401 );
		}

		$members = isset( $response['subscribers'] ) && is_array( $response['subscribers'] ) ? $response['subscribers'] : array();

		if ( empty( $members ) ) {
			return array(
				'total'             => 0,
				'skipped'           => 0,
				'existing_contacts' => 0,
				'imported'          => 0,
			);
		}

		foreach ( $members as $row ) {
			$status        = isset( $params['status'] ) ? $params['status'] : '';
			$created_by    = isset( $params['created_by'] ) ? $params['created_by'] : '';
			$custom_fields = isset( $row['custom_fields'] ) && is_array( $row['custom_fields'] ) ? $row['custom_fields'] : array();

			$contact_args = array(
				'status'      => $status,
				'source'      => 'Drip',
				'meta_fields' => array(),
				'created_by'  => $created_by,
			);

			foreach ( $params['map'] as $map ) {
				$target = isset( $map['target'] ) ? $map['target'] : '';
				$source = isset( $map['source'] ) ? (string) $map['source'] : '';

				// Custom field columns are sent as "custom_fields.{identifier}".
				if ( 0 === strpos( $source, 'custom_fields.' ) ) {
					$identifier = substr( $source, strlen( 'custom_fields.' ) );
					$value      = isset( $custom_fields[ $identifier ] ) && is_scalar( $custom_fields[ $identifier ] ) ? (string) $custom_fields[ $identifier ] : '';
				} else {
					$value = isset( $row[ $source ] ) && is_scalar( $row[ $source ] ) ? (string) $row[ $source ] : '';
				}

				if ( in_array( $target, array( 'email', 'first_name', 'last_name' ), true ) ) {
					$contact_args[ $target ] = $value;
				} else {
					$contact_args['meta_fields'][ $target ] = $value;
				}
			}

			if ( ! array_key_exists( 'email', $contact_args ) ) {
				return array( 'error' => __( 'The email field is required.', 'mrm' ), 'code' => 400 );
			}
			$contact_email = trim( $contact_args['email'] );
			if ( $contact_email && is_email( $contact_email ) ) {
				$is_exists = $this->repository->isContactExist( $contact_email );
				if ( ! $is_exists ) {
					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = $this->repository->create( $contact_args );
					// The repository stores only contact columns, so meta fields are saved separately.
					ContactModel::update_meta_fields( $contact_id, $contact_args );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$imported++;
				} else {
					if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
						$skipped++;
						$total_count++;
						continue;
					}

					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = ContactModel::get_id_by_email( $contact_email );
					ContactModel::update( Import::remove_non_overwritable_fields( $contact_args, $params ), $contact_id );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}

					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$exists++;
				}
			} else {
				$skipped++;
			}
			$total_count++;
		}

		if ( $imported > 0 ) {
			do_action( 'mailmint_contacts_imported', $imported, 'Drip' );
		}

		return array(
			'total'             => $total_count,
			'skipped'           => $skipped,
			'existing_contacts' => $exists,
			'imported'          => $imported,
		);
	}

	/**
	 * Import one batch of contacts that are active on an ActiveCampaign list.
	 *
	 * ActiveCampaign pages contacts by offset; custom field values are side-loaded with each batch.
	 *
	 * @since 1.32.0
	 *
	 * @param array $params {
	 *     Import parameters from the REST request.
	 *
	 *     @type array  $map                 Field mappings [{source, target, overwrite}].
	 *     @type int    $offset              Offset of the batch to read.
	 *     @type string $list_id             ActiveCampaign list ID.
	 *     @type string $api_url             ActiveCampaign API URL.
	 *     @type string $key                 ActiveCampaign API key.
	 *     @type string $status              Contact status.
	 *     @type string $created_by          Creator user ID.
	 *     @type array  $tags                Tag IDs to assign.
	 *     @type array  $lists               List IDs to assign.
	 *     @type bool   $skip_existing       Whether to skip existing contacts.
	 *     @type bool   $optin_confirmation  Whether to send opt-in to existing contacts.
	 *     @type bool   $automation_control  Whether to disable automation triggers.
	 * }
	 *
	 * @return array Result array or error array with 'error' and 'code' keys.
	 *
	 * @throws \Exception If import fails.
	 */
	public function importFromActiveCampaign( array $params ) {
		$imported    = 0;
		$skipped     = 0;
		$exists      = 0;
		$total_count = 0;

		if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
		}

		if ( empty( $params['map'] ) ) {
			return array( 'error' => __( 'Please map at least one field for importing.', 'mrm' ), 'code' => 400 );
		}

		$list_id = isset( $params['list_id'] ) ? sanitize_text_field( $params['list_id'] ) : '';
		if ( '' === $list_id ) {
			return array( 'error' => __( 'Please select an ActiveCampaign list to import.', 'mrm' ), 'code' => 400 );
		}

		$key = isset( $params['key'] ) ? sanitize_text_field( $params['key'] ) : '';
		if ( empty( $key ) ) {
			return array( 'error' => __( 'Your ActiveCampaign API key is invalid.', 'mrm' ), 'code' => 400 );
		}
		$api_url = isset( $params['api_url'] ) ? esc_url_raw( trim( $params['api_url'] ) ) : '';

		$query = array(
			'listid'  => $list_id,
			'status'  => 1,
			'limit'   => 100,
			'offset'  => isset( $params['offset'] ) ? max( 0, (int) $params['offset'] ) : 0,
			'include' => 'fieldValues',
		);

		$response = Import::get_activecampaign_response( $api_url, $key, 'contacts', $query );
		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return array( 'error' => $response['message'], 'code' => 401 );
		}

		$members = isset( $response['contacts'] ) && is_array( $response['contacts'] ) ? $response['contacts'] : array();

		// Side-loaded custom field values, keyed by contact ID and then field ID.
		$field_values = array();
		$side_loaded  = isset( $response['fieldValues'] ) && is_array( $response['fieldValues'] ) ? $response['fieldValues'] : array();
		foreach ( $side_loaded as $field_value ) {
			if ( isset( $field_value['contact'], $field_value['field'] ) && isset( $field_value['value'] ) && is_scalar( $field_value['value'] ) ) {
				$field_values[ (string) $field_value['contact'] ][ (string) $field_value['field'] ] = (string) $field_value['value'];
			}
		}

		if ( empty( $members ) ) {
			return array(
				'total'             => 0,
				'skipped'           => 0,
				'existing_contacts' => 0,
				'imported'          => 0,
			);
		}

		foreach ( $members as $row ) {
			$status        = isset( $params['status'] ) ? $params['status'] : '';
			$created_by    = isset( $params['created_by'] ) ? $params['created_by'] : '';
			$custom_fields = isset( $row['id'], $field_values[ (string) $row['id'] ] ) ? $field_values[ (string) $row['id'] ] : array();

			$contact_args = array(
				'status'      => $status,
				'source'      => 'ActiveCampaign',
				'meta_fields' => array(),
				'created_by'  => $created_by,
			);

			foreach ( $params['map'] as $map ) {
				$target = isset( $map['target'] ) ? $map['target'] : '';
				$source = isset( $map['source'] ) ? (string) $map['source'] : '';

				// Custom field columns are sent as "field.{field ID}".
				if ( 0 === strpos( $source, 'field.' ) ) {
					$field_id = substr( $source, strlen( 'field.' ) );
					$value    = isset( $custom_fields[ $field_id ] ) ? $custom_fields[ $field_id ] : '';
				} else {
					$value = isset( $row[ $source ] ) && is_scalar( $row[ $source ] ) ? (string) $row[ $source ] : '';
				}

				if ( in_array( $target, array( 'email', 'first_name', 'last_name' ), true ) ) {
					$contact_args[ $target ] = $value;
				} else {
					$contact_args['meta_fields'][ $target ] = $value;
				}
			}

			if ( ! array_key_exists( 'email', $contact_args ) ) {
				return array( 'error' => __( 'The email field is required.', 'mrm' ), 'code' => 400 );
			}
			$contact_email = trim( $contact_args['email'] );
			if ( $contact_email && is_email( $contact_email ) ) {
				$is_exists = $this->repository->isContactExist( $contact_email );
				if ( ! $is_exists ) {
					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = $this->repository->create( $contact_args );
					// The repository stores only contact columns, so meta fields are saved separately.
					ContactModel::update_meta_fields( $contact_id, $contact_args );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$imported++;
				} else {
					if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
						$skipped++;
						$total_count++;
						continue;
					}

					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = ContactModel::get_id_by_email( $contact_email );
					ContactModel::update( Import::remove_non_overwritable_fields( $contact_args, $params ), $contact_id );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}

					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$exists++;
				}
			} else {
				$skipped++;
			}
			$total_count++;
		}

		if ( $imported > 0 ) {
			do_action( 'mailmint_contacts_imported', $imported, 'ActiveCampaign' );
		}

		return array(
			'total'             => $total_count,
			'skipped'           => $skipped,
			'existing_contacts' => $exists,
			'imported'          => $imported,
		);
	}

	/**
	 * Import one batch of active Kit subscribers.
	 *
	 * Kit pages subscribers by cursor, so each batch returns the cursor of the next one
	 * (null once the last batch is read).
	 *
	 * @since 1.32.0
	 *
	 * @param array $params {
	 *     Import parameters from the REST request.
	 *
	 *     @type array  $map                 Field mappings [{source, target, overwrite}].
	 *     @type string $cursor              Cursor of the batch to read; empty for the first batch.
	 *     @type string $tag_id              Kit tag ID, or "all" for every subscriber.
	 *     @type string $key                 Kit v4 API key.
	 *     @type string $status              Contact status.
	 *     @type string $created_by          Creator user ID.
	 *     @type array  $tags                Tag IDs to assign.
	 *     @type array  $lists               List IDs to assign.
	 *     @type bool   $skip_existing       Whether to skip existing contacts.
	 *     @type bool   $optin_confirmation  Whether to send opt-in to existing contacts.
	 *     @type bool   $automation_control  Whether to disable automation triggers.
	 * }
	 *
	 * @return array Result array (with the next 'cursor') or error array with 'error' and 'code' keys.
	 *
	 * @throws \Exception If import fails.
	 */
	public function importFromKit( array $params ) {
		$imported    = 0;
		$skipped     = 0;
		$exists      = 0;
		$total_count = 0;

		if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
		}

		if ( empty( $params['map'] ) ) {
			return array( 'error' => __( 'Please map at least one field for importing.', 'mrm' ), 'code' => 400 );
		}

		$tag_id = isset( $params['tag_id'] ) ? sanitize_text_field( $params['tag_id'] ) : '';
		if ( '' === $tag_id ) {
			return array( 'error' => __( 'Please select a Kit tag to import.', 'mrm' ), 'code' => 400 );
		}

		$key = isset( $params['key'] ) ? sanitize_text_field( $params['key'] ) : '';
		if ( empty( $key ) ) {
			return array( 'error' => __( 'Your Kit API key is invalid.', 'mrm' ), 'code' => 400 );
		}

		$query = array(
			'status'   => 'active',
			'per_page' => 100,
		);
		if ( ! empty( $params['cursor'] ) ) {
			$query['after'] = sanitize_text_field( $params['cursor'] );
		}

		$response = Import::get_kit_response( $key, Import::get_kit_subscribers_endpoint( $tag_id ), $query );
		if ( isset( $response['status'] ) && 401 === $response['status'] ) {
			return array( 'error' => $response['message'], 'code' => 401 );
		}

		$members = isset( $response['subscribers'] ) && is_array( $response['subscribers'] ) ? $response['subscribers'] : array();
		$cursor  = ! empty( $response['pagination']['has_next_page'] ) && ! empty( $response['pagination']['end_cursor'] ) ? $response['pagination']['end_cursor'] : null;

		if ( empty( $members ) ) {
			return array(
				'total'             => 0,
				'skipped'           => 0,
				'existing_contacts' => 0,
				'imported'          => 0,
				'cursor'            => null,
			);
		}

		foreach ( $members as $row ) {
			$status        = isset( $params['status'] ) ? $params['status'] : '';
			$created_by    = isset( $params['created_by'] ) ? $params['created_by'] : '';
			$custom_fields = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();

			$contact_args = array(
				'status'      => $status,
				'source'      => 'Kit',
				'meta_fields' => array(),
				'created_by'  => $created_by,
			);

			foreach ( $params['map'] as $map ) {
				$target = isset( $map['target'] ) ? $map['target'] : '';
				$source = isset( $map['source'] ) ? (string) $map['source'] : '';

				// Custom field columns are sent as "fields.{key}".
				if ( 0 === strpos( $source, 'fields.' ) ) {
					$field_key = substr( $source, strlen( 'fields.' ) );
					$value     = isset( $custom_fields[ $field_key ] ) && is_scalar( $custom_fields[ $field_key ] ) ? (string) $custom_fields[ $field_key ] : '';
				} else {
					$value = isset( $row[ $source ] ) && is_scalar( $row[ $source ] ) ? (string) $row[ $source ] : '';
				}

				if ( in_array( $target, array( 'email', 'first_name', 'last_name' ), true ) ) {
					$contact_args[ $target ] = $value;
				} else {
					$contact_args['meta_fields'][ $target ] = $value;
				}
			}

			if ( ! array_key_exists( 'email', $contact_args ) ) {
				return array( 'error' => __( 'The email field is required.', 'mrm' ), 'code' => 400 );
			}
			$contact_email = trim( $contact_args['email'] );
			if ( $contact_email && is_email( $contact_email ) ) {
				$is_exists = $this->repository->isContactExist( $contact_email );
				if ( ! $is_exists ) {
					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = $this->repository->create( $contact_args );
					// The repository stores only contact columns, so meta fields are saved separately.
					ContactModel::update_meta_fields( $contact_id, $contact_args );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}
					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$imported++;
				} else {
					if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
						$skipped++;
						$total_count++;
						continue;
					}

					$contact_args = $this->resolveContactStatus( $contact_args );
					$contact_id   = ContactModel::get_id_by_email( $contact_email );
					ContactModel::update( Import::remove_non_overwritable_fields( $contact_args, $params ), $contact_id );

					if ( isset( $contact_args['status'] ) && 'pending' === $contact_args['status'] && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
						MessageController::get_instance()->send_double_opt_in( $contact_id );
					}

					if ( isset( $params['tags'] ) ) {
						ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
					}

					if ( isset( $params['lists'] ) ) {
						ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
					}
					$exists++;
				}
			} else {
				$skipped++;
			}
			$total_count++;
		}

		if ( $imported > 0 ) {
			do_action( 'mailmint_contacts_imported', $imported, 'Kit' );
		}

		return array(
			'total'             => $total_count,
			'skipped'           => $skipped,
			'existing_contacts' => $exists,
			'imported'          => $imported,
			'cursor'            => $cursor,
		);
	}

	/**
	 * Import contacts from Easy Digital Downloads customers.
	 *
	 * @since 1.19.5
	 *
	 * @param array $params {
	 *     Import parameters from the REST request.
	 *
	 *     @type array  $map                 Field mappings [{source, target}].
	 *     @type int    $offset              Pagination offset.
	 *     @type array  $status              Contact status array.
	 *     @type string $created_by          Creator user ID.
	 *     @type array  $tags                Tag IDs to assign.
	 *     @type array  $lists               List IDs to assign.
	 *     @type bool   $skip_existing       Whether to skip existing contacts.
	 *     @type bool   $optin_confirmation  Whether to send opt-in to existing contacts.
	 *     @type bool   $automation_control  Whether to disable automation triggers.
	 * }
	 *
	 * @return array {
	 *     @type int $imported          Number of newly imported contacts.
	 *     @type int $total             Total count of processed contacts.
	 *     @type int $skipped           Number of skipped contacts.
	 *     @type int $existing_contacts Number of existing contacts updated.
	 *     @type int $offset            Next offset for pagination.
	 * }
	 *
	 * @throws \Exception If import fails.
	 */
	public function importFromEDD( array $params ) {
		$imported     = 0;
		$skipped      = 0;
		$exists_count = 0;
		$total_count  = 0;

		/**
		 * Get the import batch limit per operation.
		 *
		 * @param int $per_batch The default import batch limit per operation.
		 * @return int The modified import batch limit per operation.
		 *
		 * @since 1.4.9
		 */
		$per_batch = apply_filters( 'mint_import_batch_limit', 30 );

		if ( isset( $params['automation_control'] ) && $params['automation_control'] ) {
			add_filter( 'mint_automation_trigger_control_on_import', '__return_true' );
		}

		if ( isset( $params['map'] ) && empty( $params['map'] ) ) {
			return array( 'error' => __( 'Please map at least one field for importing.', 'mrm' ), 'code' => 400 );
		}
		$mappings = isset( $params['map'] ) ? $params['map'] : array();

		$customers = Import::edd_get_customers( $params['offset'], $per_batch );
		foreach ( $customers as $customer ) {
			if ( isset( $customer ) ) {
				$contact_email = $customer['email'];
			}

			if ( ! is_email( $contact_email ) ) {
				$skipped++;
				continue;
			}

			$status     = isset( $params['status'] ) ? $params['status'][0] : '';
			$status     = ! empty( $status ) ? $status : 'pending';
			$created_by = isset( $params['created_by'] ) ? $params['created_by'] : '';

			$contact_args = array(
				'status'      => $status,
				'source'      => 'Easy Digital Downloads',
				'meta_fields' => array(),
				'created_by'  => $created_by,
			);

			foreach ( $mappings as $map ) {
				$target = isset( $map['target'] ) ? $map['target'] : '';
				$source = strtolower( isset( $map['source'] ) ? $map['source'] : '' );
				if ( in_array( $target, array( 'first_name', 'last_name', 'email' ), true ) ) {
					$contact_args[ $target ] = isset( $customer[ $source ] ) ? $customer[ $source ] : '';
				} else {
					$contact_args['meta_fields'][ $target ] = isset( $customer[ $source ] ) ? $customer[ $source ] : '';
				}
			}
			if ( ! array_key_exists( 'email', $contact_args ) ) {
				return array( 'error' => __( 'The email field is required.', 'mrm' ), 'code' => 400 );
			}
			$contact_email = trim( $contact_args['email'] );

			$exists = $this->repository->isContactExist( $contact_email );

			if ( ! $exists ) {
				$contact_id = $this->repository->create( $contact_args );
				// The repository stores only contact columns, so meta fields are saved separately.
				ContactModel::update_meta_fields( $contact_id, $contact_args );

				if ( 'pending' === $status ) {
					MessageController::get_instance()->send_double_opt_in( $contact_id );
				}

				if ( isset( $params['tags'] ) ) {
					ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
				}

				if ( isset( $params['lists'] ) ) {
					ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
				}
				$imported++;
			} else {
				if ( isset( $params['skip_existing'] ) && $params['skip_existing'] ) {
					$skipped++;
					$total_count++;
					continue;
				}
				$contact_id     = ContactModel::get_id_by_email( $contact_email );
				$contact_update = ContactModel::update( Import::remove_non_overwritable_fields( $contact_args, $params ), $contact_id );

				if ( 'pending' === $status && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
					MessageController::get_instance()->send_double_opt_in( $contact_id );
				}

				if ( isset( $params['tags'] ) ) {
					ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
				}

				if ( isset( $params['lists'] ) ) {
					ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
				}
				$exists_count++;
			}
			$total_count++;
		}

		if ( $imported > 0 ) {
			do_action( 'mailmint_contacts_imported', $imported, 'Easy Digital Downloads' );
		}

		return array(
			'imported'          => $imported,
			'total'             => $total_count,
			'skipped'           => $skipped,
			'existing_contacts' => $exists_count,
			'offset'            => $params['offset'] + (int) $per_batch,
		);
	}

	/**
	 * Resolve contact status based on double opt-in settings.
	 *
	 * Replicates the logic from ContactController::get_contact_status().
	 *
	 * @since 1.19.5
	 *
	 * @param array $params Contact arguments with 'status' key.
	 *
	 * @return array Modified params with resolved status.
	 */
	private function resolveContactStatus( array $params ) {
		$is_enable = MrmCommon::is_double_optin_enable();

		if ( ! $is_enable && empty( $params['status'][0] ) ) {
			$params['status'] = 'subscribed';
		} elseif ( ! is_array( $params['status'] ) ) {
			$params['status'] = isset( $params['status'] ) && ContactStatus::isValid( $params['status'] ) ? $params['status'] : 'pending';
		} else {
			$params['status'] = isset( $params['status'][0] ) && ! empty( $params['status'][0] ) ? $params['status'][0] : 'pending';
		}
		return $params;
	}
}
