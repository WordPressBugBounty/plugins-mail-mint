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
				$contact_update = ContactModel::update( $contact_args, $contact_id );

				if ( 'pending' === $status && isset( $params['optin_confirmation'] ) && $params['optin_confirmation'] ) {
					MessageController::get_instance()->send_double_opt_in( $contact_id );
				}

				if ( isset( $params['tags'] ) ) {
					ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
				}

				if ( isset( $params['lists'] ) ) {
					ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
				}
				$skipped++;
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
					$contact_args   = $this->resolveContactStatus( $contact_args );
					$contact_id     = ContactModel::get_id_by_email( $contact_email );
					$contact_update = ContactModel::update( $contact_args, $contact_id );

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
				$contact_update = ContactModel::update( $contact_args, $contact_id );

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
			$params['status'] = isset( $params['status'] ) && in_array( $params['status'], array( 'subscribed', 'unsubscribed', 'pending' ), true ) ? $params['status'] : 'pending';
		} else {
			$params['status'] = isset( $params['status'][0] ) && ! empty( $params['status'][0] ) ? $params['status'][0] : 'pending';
		}
		return $params;
	}
}
