<?php
/**
 * REST API Contact Controller
 *
 * Handles requests to the contacts endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\API\Controllers\Traits\CrudControllerTrait;
use Mint\MRM\Database\Repositories\ContactRepository;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_REST_Request;
use Exception;
use MailMintPro\App\Utilities\Helper\Integration;
use Mint\MRM\DataStores\ContactData;
use MRM\Common\MrmCommon;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\DataBase\Models\ContactGroupPivotModel;
use Mint\MRM\DataBase\Models\CustomFieldModel;
use Mint\MRM\DataBase\Models\FormModel;
use Mint\MRM\Utilites\Helper\Contact;
use Mint\MRM\Internal\Optin\UnsubscribeReasons;
use Mint\MRM\Utilites\Helper\Import;
use Mint\MRM\Internal\Import\ImportService;
use WP_REST_Response;

/**
 * This is the main class that controls the contacts feature. Its responsibilities are:
 *
 * - Create or update a contact
 * - Delete single or multiple contacts
 * - Retrieve single or multiple contacts
 * - Assign or removes tags and lists from the contact
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class ContactController extends AdminBaseController {

	use Singleton;
	use CrudControllerTrait;

	/**
	 * Contact object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $contact_args;

	/**
	 * Return the repository instance for this controller.
	 *
	 * @since 1.19.5
	 *
	 * @return \Mint\MRM\Database\AbstractRepository
	 */
	protected function repository(): \Mint\MRM\Database\AbstractRepository {
		return new ContactRepository();
	}

	/**
	 * Return the request parameter key used for the entity ID.
	 *
	 * @since 1.19.5
	 *
	 * @return string
	 */
	protected function idKey(): string {
		return 'contact_id';
	}

	/**
	 * Validate request data before create or update.
	 *
	 * Enforces email required, format, ZeroBounce integration, and uniqueness checks.
	 * Extracted from the legacy create_or_update() validation logic.
	 *
	 * @since 1.19.5
	 *
	 * @param array $data Request data.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	protected function validate( array $data ) {
		if ( isset( $data['email'] ) ) {
			$email = sanitize_text_field( $data['email'] );
			if ( empty( $email ) ) {
				return new \WP_Error( 200, __( 'Email address is mandatory', 'mrm' ), array( 'status' => 200 ) );
			}

			if ( ! is_email( $email ) ) {
				return new \WP_Error( 200, __( 'Enter a valid email address', 'mrm' ), array( 'status' => 200 ) );
			}

			// ZeroBounce integration check.
			$settings = get_option( '_mint_integration_settings', array(
				'zero_bounce' => array(
					'api_key'       => '',
					'email_address' => '',
					'is_integrated' => false,
				),
			) );

			$zero_bounce   = isset( $settings['zero_bounce'] ) ? $settings['zero_bounce'] : array();
			$api_key       = isset( $zero_bounce['api_key'] ) ? $zero_bounce['api_key'] : '';
			$is_integrated = isset( $zero_bounce['is_integrated'] ) ? $zero_bounce['is_integrated'] : false;

			if ( $is_integrated ) {
				$response = Integration::handle_zero_bounce_request( $api_key, $email );

				if ( 200 === $response['response'] && ( isset( $response['body']['status'] ) && 'invalid' === $response['body']['status'] ) ) {
					return new \WP_Error( 200, __( 'The email address does not exist. Please check the spelling and try again.', 'mrm' ), array( 'status' => 200 ) );
				}
			}

			// Email uniqueness check.
			$contact_id = isset( $data['contact_id'] ) ? $data['contact_id'] : '';
			if ( $contact_id ) {
				// Update: allow same email for same contact.
				$current_email = ContactModel::get_contact_email_by_id( $contact_id );
				$exist         = ContactModel::is_contact_exist( $email );
				if ( $exist && $current_email !== $email ) {
					return new \WP_Error( 200, __( 'Email address already assigned to another contact.', 'mrm' ), array( 'status' => 200 ) );
				}
			} else {
				// Create: email must not exist.
				$exist = ContactModel::is_contact_exist( $email );
				if ( $exist ) {
					return new \WP_Error( 200, __( 'Email address already assigned to another contact.', 'mrm' ), array( 'status' => 200 ) );
				}
			}
		}

		return true;
	}

	/**
	 * Create a new contact or update an existing contact.
	 *
	 * Overrides CrudControllerTrait::create_or_update() to preserve the legacy
	 * response envelope and post-processing (meta fields, tag/list assignment,
	 * double opt-in, hooks).
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response|\WP_Error
	 * @since 1.0.0
	 * @since 1.19.5 Migrated to use ContactRepository via CrudControllerTrait.
	 */
	public function create_or_update( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		// Validate via extracted validate() method.
		$validation = $this->validate( $params );
		if ( is_wp_error( $validation ) ) {
			return $this->get_error_response( $validation->get_error_message(), 200 );
		}

		// Contact object create and insert or update to database.
		try {
			if ( isset( $params['contact_id'] ) ) {
				$contact_id = (int) $params['contact_id'];

				$this->repository()->update( $contact_id, $params );

				// Update meta fields.
				ContactModel::update_meta_fields( $contact_id, $params );
			} else {
				$params     = $this->get_contact_status( $params );
				$contact_id = $this->repository()->create( $params );

				// Update meta fields.
				ContactModel::update_meta_fields( $contact_id, $params );

				if ( isset( $params['status'] ) && 'pending' === $params['status'] ) {
					MessageController::get_instance()->send_double_opt_in( $contact_id );
				}
			}

			if ( isset( $params['tags'] ) ) {
				ContactGroupModel::set_tags_to_contact( $params['tags'], $contact_id );
			}

			if ( isset( $params['lists'] ) ) {
				ContactGroupModel::set_lists_to_contact( $params['lists'], $contact_id );
			}

			if ( $contact_id ) {
				/**
				 * Fires after a contact is successfully saved (created or updated).
				 *
				 * @since 1.19.5
				 *
				 * @param int   $contact_id Contact ID.
				 * @param array $params     Saved data.
				 */
				do_action( 'mailmint_contacts_saved', $contact_id, $params );

				return $this->get_success_response( __( 'Contact has been saved successfully', 'mrm' ), 201 );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		} catch ( Exception $e ) {
			return $this->get_error_response( __( 'Contact is not valid', 'mrm' ), 400 );
		}
	}


	/**
	 * Return a contact details.
	 *
	 * Overrides CrudControllerTrait::get_single() to preserve the legacy response
	 * shape with meta, tags, lists, added_by_login, and WooCommerce stats.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 * @since 1.19.5 Migrated to use ContactRepository via CrudControllerTrait.
	 */
	public function get_single( WP_REST_Request $request ) {
		// Get values from API.
		$params     = MrmCommon::get_api_params_values( $request );
		$contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
		$contact    = $this->repository()->find( (int) $contact_id );

		// Get and merge tags and lists.
		if ( $contact ) {
			$contact = ContactGroupModel::get_tags_to_contact( $contact );
			$contact = ContactGroupModel::get_lists_to_contact( $contact );
		}
		if ( $contact && isset( $contact['email'] ) ) {
			// Load meta fields.
			$meta = ContactModel::get_meta( $contact_id );
			if ( ! empty( $meta['meta_fields'] ) ) {
				$contact['meta_fields'] = $meta['meta_fields'];
			}

			if ( isset( $contact['created_at'] ) ) {
				$contact['created_at'] = MrmCommon::date_time_format_with_core( $contact['created_at'] );
			}

			if ( isset( $contact['updated_at'] ) ) {
				$updated_at            = new \DateTimeImmutable( $contact['updated_at'], wp_timezone() );
				$date_format           = get_option( 'date_format' );
				$time_format           = get_option( 'time_format' );
				$contact['updated_at'] = $updated_at->format( $date_format . ' ' . $time_format );
			}

			if ( isset( $contact['created_by'] ) && ! empty( $contact['created_by'] ) ) {
				$user_meta = get_userdata( $contact['created_by'] );
			}

			if ( ! empty( $user_meta->data->user_login ) ) {
				$contact['added_by_login'] = esc_html( $user_meta->data->user_login );
			} elseif ( ! empty( $contact['source'] ) ) {
				$temp_src = $contact['source'];
				$parts    = explode( '-', $temp_src );
				if ( 'Form' === $parts[0] ) {
					$form_id                   = $parts[1];
					$get_form                  = FormModel::get( $form_id );
					$contact['added_by_login'] = isset( $get_form['title'] ) ? $get_form['title'] : $form_id;
				} else {
					$contact['added_by_login'] = esc_html( $contact['source'] );
				}
			} elseif ( ContactModel::is_contact_meta_exist( $contact_id, '_wc_customer_id' ) ) {
				$contact['added_by_login'] = esc_html__( 'WooCommerce Checkout', 'mrm' );
			} else {
				$contact['added_by_login'] = esc_html__( 'External Source', 'mrm' );
			}
			$contact['meta_fields']['avatar_url'] = Contact::get_avatar_url( $contact );
			$contact['general_fields']            = Contact::get_contact_primary_fields();

			// Append unsubscribe reason fields for display on the contact profile.
			$unsub_reason                      = ContactModel::get_meta_value_by_contact_id( $contact_id, 'unsubscribe_reason' );
			$contact['unsubscribe_reason']       = $unsub_reason ? $unsub_reason : '';
			$contact['unsubscribe_reason_label'] = $unsub_reason ? UnsubscribeReasons::get_label( $unsub_reason ) : '';
			$contact['unsubscribe_reason_text']  = ContactModel::get_meta_value_by_contact_id( $contact_id, 'unsubscribe_reason_text' ) ?: '';
			$is_wc_active                         = MrmCommon::is_wc_active();
			if ( $is_wc_active ) {
				/**
				 * Applies filters to enhance the contact profile statistics.
				 *
				 * @see 'mail_mint_contact_profile_stats' hook for customizing contact profile statistics.
				 *
				 * @since 1.7.0
				 */
				$contact['customer_summery'] = apply_filters( 'mail_mint_contact_profile_stats', $contact );
			}

			return $this->get_success_response( 'Contact has been retrieved successfully.', 200, $contact );
		}
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}

	/**
	 * Return Contacts for list view.
	 *
	 * Overrides CrudControllerTrait::get_all() to preserve the legacy response
	 * envelope with count_groups, count_status, and date formatting.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 * @since 1.19.5 Migrated to use ContactRepository via CrudControllerTrait.
	 */
	public function get_all( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		// Map hyphenated query params to underscored for repository.
		$page = isset( $params['page'] ) ? (int) $params['page'] : 1;
		if ( isset( $params['per-page'] ) ) {
			$params['per_page'] = $params['per-page'];
		}
		if ( isset( $params['order-by'] ) ) {
			$params['order_by'] = $params['order-by'];
		}

		// Delegate to repository list() which handles search, ordering, and withStatsQuery.
		$result = $this->repository()->list( $params );

		$search = isset( $params['search'] ) ? $params['search'] : '';

		// Track contact list view only on first page load (to avoid multiple events).
		if ( 1 === $page && empty( $search ) ) {
			do_action( 'mailmint_contact_list_viewed', $result['total'] );
		}

		// Format dates for each contact.
		if ( isset( $result['data'] ) ) {
			$result['data'] = array_map(
				function( $contact ) {
					if ( isset( $contact['created_at'] ) ) {
						$contact['created_at'] = MrmCommon::date_time_format_with_core( $contact['created_at'] );
					}

					if ( isset( $contact['updated_at'] ) ) {
						$time                  = new \DateTimeImmutable( $contact['updated_at'], wp_timezone() );
						$date_format           = get_option( 'date_format' );
						$time_format           = get_option( 'time_format' );
						$contact['updated_at'] = $time->format( $date_format . ' ' . $time_format );
					}
					return $contact;
				},
				$result['data']
			);
		}

		// Count contacts groups.
		$contacts_data['data']        = $result['data'];
		$contacts_data['total_pages'] = $result['total_pages'];
		$contacts_data['total_count'] = $result['total'];

		$contacts_data['count_groups'] = array(
			'lists'    => ContactGroupModel::get_groups_count( 'lists' ),
			'tags'     => ContactGroupModel::get_groups_count( 'tags' ),
			'segments' => ContactGroupModel::get_groups_count( 'segments' ),
			'contacts' => absint( $result['total'] ),
		);

		$total_contact = ContactModel::get_contact_total();

		$subscriber_count  = ! empty( $total_contact['subscribed'] ) ? $total_contact['subscribed'] : 0;
		$unsubcriber_count = ! empty( $total_contact['unsubscribed'] ) ? $total_contact['unsubscribed'] : 0;
		$pending_count     = ! empty( $total_contact['pending'] ) ? $total_contact['pending'] : 0;
		$bounced_count     = ! empty( $total_contact['bounced'] ) ? $total_contact['bounced'] : 0;
		$complained_count  = ! empty( $total_contact['complained'] ) ? $total_contact['complained'] : 0;
		$inactive_count    = ! empty( $total_contact['inactive'] ) ? $total_contact['inactive'] : 0;
		$total_status      = $subscriber_count + $unsubcriber_count + $pending_count + $bounced_count + $complained_count + $inactive_count;

		// Count contacts based on status.
		$contacts_data['count_status'] = array(
			'subscribed'   => $subscriber_count,
			'unsubscribed' => $unsubcriber_count,
			'pending'      => $pending_count,
			'bounced'      => $bounced_count,
			'complained'   => $complained_count,
			'inactive'     => $inactive_count,
			'total_status' => $total_status,
		);

		$contacts_data['current_page'] = $page;

		return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $contacts_data );
	}


	/**
	 * Delete a contact.
	 *
	 * Overrides CrudControllerTrait::delete_single() to use repository destroy
	 * (which handles cleanup) and preserve the legacy response envelope.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 * @since 1.19.5 Migrated to use ContactRepository via CrudControllerTrait.
	 */
	public function delete_single( WP_REST_Request $request ) {
		// Get values from API.
		$params     = MrmCommon::get_api_params_values( $request );
		$contact_id = isset( $params['contact_id'] ) ? (int) $params['contact_id'] : 0;

		$success = $this->repository()->destroy( $contact_id );

		if ( $success ) {
			/**
			 * Fires after a contact is successfully deleted.
			 *
			 * @since 1.19.5
			 *
			 * @param int $contact_id Deleted contact ID.
			 */
			do_action( 'mailmint_contacts_deleted', $contact_id );

			return $this->get_success_response( __( 'Contact has been deleted successfully', 'mrm' ), 200 );
		}
		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Delete multiple contacts.
	 *
	 * Overrides CrudControllerTrait::delete_all() to read `contact_ids` from
	 * request body, use repository destroyMany, and fire hook per ID.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 * @since 1.19.5 Migrated to use ContactRepository via CrudControllerTrait.
	 */
	public function delete_all( WP_REST_Request $request ) {
		// Get values from API.
		$params      = MrmCommon::get_api_params_values( $request );
		$contact_ids = isset( $params['contact_ids'] ) ? array_map( 'intval', $params['contact_ids'] ) : array();

		$success = $this->repository()->destroyMany( $contact_ids );

		if ( $success ) {
			foreach ( $contact_ids as $id ) {
				/** This action is documented in delete_single(). */
				do_action( 'mailmint_contacts_deleted', $id );
			}

			return $this->get_success_response( __( 'Contacts has been deleted successfully', 'mrm' ), 200 );
		}
		return $this->get_error_response( __( 'Failed to Delete', 'mrm' ), 400 );
	}


	/**
	 * Remove tags, lists, and segments from a contact
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_groups( WP_REST_Request $request ) {
		$success = ContactPivotController::get_instance()->delete_groups( $request );

		if ( $success ) {
			return $this->get_success_response( __( 'Removed Successfully', 'mrm' ), 200 );
		}
		return $this->get_error_response( __( 'Failed to Remove', 'mrm' ), 400 );
	}


	/**
	 * Set tags, lists, and segments to a contact
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function set_groups( WP_REST_Request $request ) {
		// Get values from API.
		$params  = MrmCommon::get_api_params_values( $request );
		$is_tag  = false;
		$is_list = false;

		if ( isset( $params['tags'], $params['contact_id'] ) ) {
			if ( empty( $params['tags'] ) ) {
				return $this->get_error_response( __( 'Please select an item first', 'mrm' ), 400 );
			}
			$success = ContactGroupModel::set_tags_to_contact( $params['tags'], $params['contact_id'] );
			$is_tag  = true;
		}

		if ( isset( $params['lists'], $params['contact_id'] ) ) {
			if ( empty( $params['lists'] ) ) {
				return $this->get_error_response( __( 'Please select an item first', 'mrm' ), 400 );
			}
			$success = ContactGroupModel::set_lists_to_contact( $params['lists'], $params['contact_id'] );
			$is_list = true;
		}

		if ( $success && $is_list && $is_tag ) {
			return $this->get_success_response( __( 'Tag and List added Successfully', 'mrm' ), 201 );
		} elseif ( $success && $is_tag ) {
			return $this->get_success_response( __( 'Tag added Successfully', 'mrm' ), 201 );
		} elseif ( $success && $is_list ) {
			return $this->get_success_response( __( 'List added Successfully', 'mrm' ), 201 );
		}
		return $this->get_error_response( __( 'Failed to add', 'mrm' ), 400 );
	}

	/**
	 * Set tags, lists to multiple contacts
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function set_groups_to_multiple( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$is_tag  = false;
		$is_list = false;

		if ( isset( $params['tags'] ) && isset( $params['contact_ids'] ) ) {
			$success = ContactGroupModel::set_tags_to_multiple_contacts( $params['tags'], $params['contact_ids'] );
			$is_tag  = true;
		}

		if ( isset( $params['lists'] ) && isset( $params['contact_ids'] ) ) {
			$success = ContactGroupModel::set_lists_to_multiple_contacts( $params['lists'], $params['contact_ids'] );
			$is_list = true;
		}

		if ( $success && $is_list && $is_tag ) {
			return $this->get_success_response( __( 'Tag and List has been added successfully.', 'mrm' ), 201 );
		} elseif ( $success && $is_tag ) {
			return $this->get_success_response( __( 'Tag has been added successfully.', 'mrm' ), 201 );
		} elseif ( $success && $is_list ) {
			return $this->get_success_response( __( 'List has been added successfully.', 'mrm' ), 201 );
		}
		return $this->get_error_response( __( 'Please select at least one item to proceed.', 'mrm' ), 200 );
	}

    /**
     * Removes tags or lists from multiple contacts.
     *
     * This function removes tags or lists from multiple contacts based on the provided API parameters.
     *
     * @param WP_REST_Request $request The REST request object containing the API parameters.
     * @return WP_REST_Response Returns a REST response indicating the result of the operation.
     * @access public
     * @since 1.5.1
     */
    public function remove_groups_from_multiple_contacts( WP_REST_Request $request ) {
        $required_params = array('contact_ids');

        foreach ( $required_params as $param ) {
            if ( !$request->has_param($param) ) {
                return $this->get_error_response( __( "Required parameter '$param' is missing.", 'mrm' ), 400 );
            }
        }
        // Get values from API parameters.
		$params = MrmCommon::get_api_params_values( $request );

        // Check if tags and contact IDs are provided, or lists and contact IDs are provided.
        $has_tags  = isset( $params['tags'] ) && isset( $params['contact_ids'] );
        $has_lists = isset( $params['lists'] ) && isset( $params['contact_ids'] );

        // If either tags or lists are provided, proceed.
        if ( $has_tags || $has_lists ) {
            $groups = $has_tags ? $params['tags'] : $params['lists'];
            $result = ContactGroupPivotModel::remove_groups_from_contacts( $groups, $params['contact_ids'] );
    
            // If removal was successful, return appropriate success response.
            if ( $result ) {
                if ( $has_tags && $has_lists ) {
                    return $this->get_success_response( __( 'Tag and List have been removed successfully.', 'mrm' ), 201 );
                } elseif ( $has_tags ) {
                    return $this->get_success_response( __( 'Tag has been removed successfully', 'mrm' ), 201 );
                } elseif ( $has_lists ) {
                    return $this->get_success_response( __( 'List has been removed successfully', 'mrm' ), 201 );
                }
            }
        }
    
        return $this->get_error_response( __( 'Please select at least one item to proceed.', 'mrm' ), 200 );
    }

    /**
     * Import contacts from woocommerce customers
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     *
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function import_contacts_native_wc( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );

        try {
            $service = new ImportService( $this->repository() );
            $result  = $service->importFromWooCommerce( $params );

            if ( isset( $result['error'] ) ) {
                return $this->get_success_response( $result['error'], $result['code'] );
            }

            return $this->get_success_response( __( 'Import has been successful', 'mrm' ), 200, $result );
        } catch ( Exception $e ) {
            return $this->get_success_response( __( 'Import has not been successful', 'mrm' ), 400 );
        }
    }
    /**
     * Summary: Retrieves native WooCommerce customers.
     * Description: Retrieves the native WooCommerce customers by retrieving the total number of orders.
     *
     * @access public
     * 
     * @return WP_REST_Response Returns a REST response containing the total batch count of orders.
     * @since 1.4.9
     */
    public function get_native_wc_customers() {
        $order_query  = new \WC_Order_Query( array(
            'limit'  => 1,
            'return' => 'ids',
            'paginate' => true,
        ) );
        $total_orders = $order_query->get_orders()->total;

        $headers = apply_filters( 'mint_woocommerce_customer_import_headers', array(
            'billing_email',
            'billing_first_name',
            'billing_last_name',
            'customer_id',
            'total_spent',
            'total_orders',
            'registered_date',
            'billing_address_1',
            'billing_address_2',
            'billing_city',
            'billing_postcode',
            'billing_state',
            'billing_country',
            'billing_phone',
            'shipping_first_name',
            'shipping_last_name',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_city',
            'shipping_postcode',
            'shipping_state',
            'shipping_country',
        ) );

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.4.9
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        return $this->get_success_response( __( 'Total orders has been retrieved successfully.', 'mrm' ), 200, array(
            'total_batch' => ceil( $total_orders / (int) $per_batch ),
            'headers'     => $headers,
        ) );
    }

    /**
     * Prepare contact object from the uploaded CSV
     * Inseret contcts data into database
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     * @throws Exception    $e Throws an exception if the action could not be saved.
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function import_contacts_mailchimp( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );

        try {
            $service = new ImportService( $this->repository() );
            $result  = $service->importFromMailchimp( $params );

            if ( isset( $result['error'] ) ) {
                return new WP_REST_Response( array(
                    'status'  => 'failed',
                    'message' => $result['error'],
                ) );
            }

            return new WP_REST_Response( array(
                'status'  => 'success',
                'data'    => $result,
                'message' => __( 'Import contact from mailchimp has been successful.', 'mrm' ),
            ) );
        } catch ( Exception $e ) {
            return new WP_REST_Response( array(
                'status'  => 'failed',
                'message' => __( 'Import contact from mailchimp has not been successful.', 'mrm' ),
            ) );
        }
    }

	/**
     * Import contacts from woocommerce customers
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     *
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function import_contacts_native_edd( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );

        try {
            $service = new ImportService( $this->repository() );
            $result  = $service->importFromEDD( $params );

            if ( isset( $result['error'] ) ) {
                return $this->get_success_response( $result['error'], $result['code'] );
            }

            return $this->get_success_response( __( 'Import has been successful', 'mrm' ), 200, $result );
        } catch ( Exception $e ) {
            return $this->get_success_response( __( 'Import has not been successful', 'mrm' ), 400 );
        }
    }


    /**
     * Send double opt-in email for pending status
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     *
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function send_double_opt_in( WP_REST_Request $request ) {
        // Get values from API.
        $params     = MrmCommon::get_api_params_values( $request );
        $contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
        $success    = MessageController::get_instance()->send_double_opt_in( $contact_id );
        if ( $success ) {
            return $this->get_success_response( 'Double Optin email has been sent', 200 );
        } else {
            return $this->get_success_response( __( 'Double opt-in subscription process is disable', 'mrm' ), 400 );
        }
        return $this->get_error_response( 'Failed to send double optin email', 400 );
    }

    /**
     * Return Filtered Contacts for list view
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_filtered_contacts( WP_REST_Request $request ) {
        // Get values from API.
        $params   = MrmCommon::get_api_params_values( $request );
        $page     = isset( $params['page'] ) ? $params['page'] : 1;
        $per_page = isset( $params['per-page'] ) ? $params['per-page'] : 25;
        $offset   = ( $page - 1 ) * $per_page;
        // Contact Search keyword.
        $search = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
        $tags_ids   = isset( $params['tags_ids'] ) ? $params['tags_ids'] : array();
        $lists_ids  = isset( $params['lists_ids'] ) ? $params['lists_ids'] : array();
        $status_arr = isset( $params['status'] ) ? $params['status'] : array();

        $contacts = ContactModel::get_filtered_contacts( $status_arr, $tags_ids, $lists_ids, $per_page, $offset, $search );

        $total_contact = ContactModel::get_contact_total();

        $subscriber_count  = ! empty( $total_contact['subscribed'] ) ? $total_contact['subscribed'] : 0;
        $unsubcriber_count = ! empty( $total_contact['unsubscribed'] ) ? $total_contact['unsubscribed'] : 0;
        $pending_count     = ! empty( $total_contact['pending'] ) ? $total_contact['pending'] : 0;
        $bounced_count     = ! empty( $total_contact['bounced'] ) ? $total_contact['bounced'] : 0;
        $complained_count  = ! empty( $total_contact['complained'] ) ? $total_contact['complained'] : 0;
        $inactive_count    = ! empty( $total_contact['inactive'] ) ? $total_contact['inactive'] : 0;
        $total_status      = $subscriber_count + $unsubcriber_count + $pending_count + $bounced_count + $complained_count + $inactive_count;

		// Count contacts based on status.
		$contacts['count_status'] = array(
			'subscribed'   => $subscriber_count,
			'unsubscribed' => $unsubcriber_count,
			'pending'      => $pending_count,
			'bounced'      => $bounced_count,
			'complained'   => $complained_count,
			'inactive'     => $inactive_count,
			'total_status' => $total_status,
		);

		// Count contacts groups.
		$contacts['count_groups'] = array(
			'lists'    => ContactGroupModel::get_groups_count( 'lists' ),
			'tags'     => ContactGroupModel::get_groups_count( 'tags' ),
			'segments' => ContactGroupModel::get_groups_count( 'segments' ),
			'contacts' => absint( $total_status ),
		);

        if ( isset( $contacts['data'] ) ) {
            $contacts['data'] = array_map(
                function( $contact ) {
                    $contact = ContactGroupModel::get_tags_to_contact( $contact );
                    $contact = ContactGroupModel::get_lists_to_contact( $contact );
                    return $contact;
                },
                $contacts['data']
            );
        }

        if ( isset( $contacts ) ) {
            return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $contacts );
        }
        return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
    }

    /**
     * Retrieves the columns for the contact details page.
     * 
     * Retrieves the columns to be displayed on the contact details page, including basic fields,
     * other fields, custom fields, and default columns. The retrieved columns are merged with
     * stored columns retrieved from the database.
     *
     * @return WP_REST_Response The response containing the columns data.
     * @since 1.0.0
     */
    public function get_columns() {
        $basic_fields   = MrmCommon::retrieve_contact_fields( 'basic' );
        $other_fields   = MrmCommon::retrieve_contact_fields( 'other' );
        $custom_fields  = CustomFieldModel::get_all();
        $stored_columns = MrmCommon::retrieve_stored_columns();

        $list_columns = array_merge(
            $this->get_merged_columns($basic_fields, $other_fields),
            $this->get_custom_fields($custom_fields),
            $this->get_default_columns()
        );

        $columns_data = array(
            'list_columns' => $list_columns,
            'stored_columns' => $stored_columns
        );
    
        return $this->get_success_response(__('Query Successful', 'mrm'), 200, $columns_data);
    }
    
    /**
     * Retrieves the merged columns from the basic fields and other fields.
     * 
     * Merges the basic fields and other fields and maps them to the required format
     * for the merged columns. Each field is represented as an array with 'id' and 'value' keys.
     * 
     * @param mixed $basic_fields The basic fields.
     * @param mixed $other_fields The other fields.
     * 
     * @return array The merged columns.
     * @since 1.5.0
     */
    private function get_merged_columns($basic_fields, $other_fields) {
        $fields = array_merge($basic_fields, $other_fields);
    
        return array_map(function ($field) {
            return array(
                'id'    => isset( $field['slug'] ) ? $field['slug'] : '',
                'value' => isset( $field['meta']['label'] ) ? $field['meta']['label'] : ''
            );
        }, $fields);
    }
    
    /**
     * Retrieves the custom fields data in the required format.
     * 
     * Retrieves the custom fields data and maps them to the required format
     * for the custom fields. Each field is represented as an array with 'id' and 'value' keys.
     * 
     * @param mixed $custom_fields The custom fields data.
     * 
     * @return array The custom fields in the required format.
     * @since 1.5.0
     */
    private function get_custom_fields($custom_fields) {
        $custom_fields_data = isset($custom_fields['data']) ? $custom_fields['data'] : array();
    
        return array_map(function ($custom_field) {
            $meta = maybe_unserialize($custom_field['meta']);
            return array(
                'id'    => isset( $custom_field['slug'] ) ? $custom_field['slug'] : '',
                'value' => isset($meta['label']) ? $meta['label'] : '',
            );
        }, $custom_fields_data);
    }
    
    /**
     * Retrieves the default columns for contact details.
     * 
     * Retrieves an array of default columns for contact details. Each column is represented
     * as an array with 'id' and 'value' keys.
     * 
     * @return array The default columns for contact details.
     * @since 1.5.0
     */
    private function get_default_columns() {
        return array(
            array(
                'id' => 'lists',
                'value' => 'Lists',
            ),
            array(
                'id' => 'tags',
                'value' => 'Tags',
            ),
            array(
                'id' => 'statuses',
                'value' => 'Status',
            ),
            array(
                'id' => 'addresses',
                'value' => 'Address',
            ),
            array(
                'id' => 'sources',
                'value' => 'Source',
            ),
        );
    }

    /**
     * Save column hide/show information on wp_options table
     *
     * @param WP_REST_Request $request Request object used to generate the response.
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function save_contact_columns( WP_REST_Request $request ) {
        $params          = MrmCommon::get_api_params_values( $request );
        $contact_columns = isset( $params['contact_columns'] ) ? $params['contact_columns'] : array();
        $success         = update_option( 'mrm_contact_columns', maybe_serialize( $contact_columns ) );
        if ( $success ) {
            return $this->get_success_response( __( 'Columns has been saved successfully', 'mrm' ), 201, $contact_columns );
        }
        return $this->get_error_response( __( 'Failed to save columns', 'mrm' ), 400 );
    }


    /**
     * Return stored column information from wp_options table
     *
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_stored_columns() {
        $contact_columns = get_option( 'mrm_contact_columns' );
        $columns         = maybe_unserialize( $contact_columns );

        if ( false === $columns ) {
            $columns = array();
        }
        return $this->get_success_response( __( 'Query successfully', 'mrm' ), 200, $columns );
    }

    /**
     * Return contact status based on double opt-in settings
     *
     * @return array
     * @since 1.0.0
     */
    public function get_contact_status( $params ) {
        $is_enable = MrmCommon::is_double_optin_enable();

        if( ! $is_enable &&  empty( $params[ 'status' ][ 0 ] ) ) {
            $params['status'] = 'subscribed';
        } elseif( !is_array( $params['status'] ) ) {
            $params['status'] = isset( $params[ 'status' ] ) && in_array( $params[ 'status' ], array( 'subscribed', 'unsubscribed', 'pending' ), true ) ? $params[ 'status' ] : 'pending';
        } else {
            $params['status'] = isset( $params[ 'status' ][ 0 ] ) && ! empty( $params[ 'status' ][ 0 ] ) ? $params[ 'status' ][ 0 ] : 'pending';
        }
        return $params;
    }

    /**
     * Sends a double opt-in message to multiple contacts.
     *
     * This function sends a double opt-in message to the selected contacts based on the provided contact IDs.
     *
     * @access public
     * 
     * @param WP_REST_Request $request The REST request object containing the API parameters.
     * @return WP_REST_Response Returns a REST response indicating the success or failure of the operation.
     * @since 1.5.1
     */
    public function send_double_optin_to_multiple_contacts( WP_REST_Request $request ) {
        $required_params = array('contact_ids');

        foreach ( $required_params as $param ) {
            if ( !$request->has_param($param) ) {
                return $this->get_error_response( __( "Required parameter '$param' is missing.", 'mrm' ), 400 );
            }
        }
        // Get API parameters from the request object.
		$params = MrmCommon::get_api_params_values( $request );
        $params = filter_var_array( $params );

        $contact_ids = isset( $params['contact_ids'] ) ? $params['contact_ids'] : array();

        // Check if contact IDs are empty.
        if ( empty( $contact_ids ) ) {
            return $this->get_error_response( __( 'Please select an item first.', 'mrm' ), 400 );
        }

        // Iterate through each contact ID and send a double optin message.
        foreach ($contact_ids as $contact_id) {
            MessageController::get_instance()->send_double_opt_in( $contact_id );
        }
        return $this->get_success_response( __( 'Double optin have been successfully dispatched to the chosen contacts.', 'mrm' ), 201 );
    }

    /**
     * Changes the status of multiple contacts.
     *
     * This function changes the status of the specified contacts to the provided status.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The REST request object containing the API parameters.
     * @return WP_REST_Response Returns a REST response indicating the success or failure of the operation.
     * @since 1.5.1
     */
    public function change_status_to_multiple_contacts( WP_REST_Request $request ) {
        // Check if all required parameters are present in the request.
        $required_params = array('contact_ids');
        foreach ( $required_params as $param ) {
            if ( !$request->has_param($param) ) {
                return $this->get_error_response( __( "Required parameter '$param' is missing.", 'mrm' ), 400 );
            }
        }
        // Get API parameters from the request object.
		$params = MrmCommon::get_api_params_values( $request );
        $params = filter_var_array( $params );

        // Extract contact IDs and status from the filtered parameters.
        $contact_ids = isset( $params['contact_ids'] ) ? $params['contact_ids'] : array();
        $status      = isset( $params['status'] ) ? $params['status'] : 'pending';

        // Check if contact IDs are empty.
        if ( empty( $contact_ids ) ) {
            return $this->get_error_response( __( 'Please select an item first.', 'mrm' ), 400 );
        }

        $response = ContactModel::update_contact_status( $contact_ids, $status );

        if( $response ) {
            return $this->get_success_response( __( 'Status have been successfully changed to the chosen contacts.', 'mrm' ), 201 );
        }
        return $this->get_error_response( __( 'Please select an item first.', 'mrm' ), 400 );
    }

    /**
     * Retrieve the counts of different contact groups and contacts.
     * 
     * @access public
     *
     * This function calculates and returns the counts of various contact groups and contacts, including segments, tags,
     * contacts, and lists. These counts are used to provide statistics about the contacts in the system.
     *
     * @return WP_REST_Response A REST API response containing the counts of contact groups and contacts.
     * @since 1.5.14
     */
    public function get_contact_groups_count() {
        $count_groups = array(
			'segments' => absint( isset( $segments['total_count'] ) ? $segments['total_count'] : '' ),
			'tags'     => ContactGroupModel::get_groups_count( 'tags' ),
			'contacts' => ContactModel::get_contacts_count(),
			'lists'    => ContactGroupModel::get_groups_count( 'lists' ),
		);

        return $this->get_success_response( __( 'Query Successful', 'mailmint' ), 200, $count_groups );
    }

    /**
     * Update the avatar of a contact.
     *
     * This function updates the avatar (avatar_url) of a specific contact based on the provided contact ID.
     * 
     * @access public
     *
     * @param WP_REST_Request $request The REST request object.
     *
     * @return WP_REST_Response A REST API response indicating the success or failure of the avatar update.
     *
     * @since 1.5.18
     */
    public function update_contact_avatar( WP_REST_Request $request ) {
        // Get values from API.
        $params     = MrmCommon::get_api_params_values( $request );
        $avatar_url = isset( $params['avatar_url'] ) ? $params['avatar_url'] : '';
        $contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';

        $args['meta_fields'] = array(
            'avatar_url' => $avatar_url,
        );
        ContactModel::update_meta_fields( $contact_id, $args );
        return $this->get_success_response( __( 'Contact avatar has been uploaded successfully.', 'mrm' ), 200 );
    }

    /**
     * Update the status of a contact.
     *
     * This function updates the status of a specific contact based on the provided contact ID and status.
     * @access public
     *
     * @param WP_REST_Request $request The REST request object.
     *
     * @return WP_REST_Response A REST API response indicating the success or failure of the status update.
     *
     * @since 1.18.4
     */
    public function update_status( WP_REST_Request $request ){
        // Get values from API.
        $params = MrmCommon::get_api_params_values( $request );

        $contact_id = isset($params['contact_id']) ? absint($params['contact_id']) : 0;
        $status     = isset($params['status']) ? sanitize_text_field($params['status']) : '';

        // Validate contact ID
        if (empty($contact_id)) {
            return $this->get_error_response(__('Contact ID is required.', 'mrm'), 400);
        }

        // Validate status
        $allowed_statuses = array('pending', 'subscribed', 'unsubscribed', 'complained', 'bounced', 'inactive');
        if (! in_array($status, $allowed_statuses, true)) {
            return $this->get_error_response(__('Invalid status provided.', 'mrm'), 400);
        }

        // Check if contact exists
        $contact = ContactModel::get($contact_id);
        if (! $contact) {
            return $this->get_error_response(__('Contact not found.', 'mrm'), 404);
        }

        // Update the contact status
        $update_data = array(
            'status' => $status
        );

        try {
            $updated = ContactModel::update($update_data, $contact_id);

            if ($updated) {
                return $this->get_success_response(
                    __('Contact status updated successfully.', 'mrm'),
                    201,
                    array(
                        'contact_id' => $contact_id,
                        'status'     => $status
                    )
                );
            } else {
                return $this->get_error_response(__('Failed to update contact status.', 'mrm'), 500);
            }
        } catch (Exception $e) {
            return $this->get_error_response(__('An error occurred while updating contact status.', 'mrm'), 500);
        }
    }
}
