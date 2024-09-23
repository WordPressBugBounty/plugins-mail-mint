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
use Mint\MRM\Utilites\Helper\Import;
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

	/**
	 * Contact object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $contact_args;

	/**
	 * Create a new contact or update a existing contact
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );
		// Email address validation.
		if ( isset( $params['email'] ) ) {
			$email = sanitize_text_field( $params['email'] );
			if ( empty( $email ) ) {
				return $this->get_error_response( __( 'Email address is mandatory', 'mrm' ), 200 );
			}

			if ( ! is_email( $email ) ) {
				return $this->get_error_response( __( 'Enter a valid email address', 'mrm' ), 200 );
			}

            $settings = get_option( '_mint_integration_settings', array(
                'zero_bounce' => array(
                    'api_key' => '',
                    'email_address' => '',
                    'is_integrated' => false,
                ),
            ) );
    
            $zero_bounce   = isset( $settings['zero_bounce'] ) ? $settings['zero_bounce'] : array();
            $api_key       = isset( $zero_bounce['api_key'] ) ? $zero_bounce['api_key'] : '';
            $is_integrated = isset( $zero_bounce['is_integrated'] ) ? $zero_bounce['is_integrated'] : false;
    
            if( $is_integrated ) {
                $response = Integration::handle_zero_bounce_request( $api_key, $email );

                if( 200 === $response['response'] && ( isset( $response['body']['status'] ) && 'invalid' === $response['body']['status'] ) ){
                    return $this->get_error_response( __( 'The email address does not exist. Please check the spelling and try again.', 'mrm' ), 200 );
                }
            }

			$exist = ContactModel::is_contact_exist( $email );
			if ( $exist && ! isset( $params['contact_id'] ) ) {
				return $this->get_error_response( __( 'Email address already assigned to another contact.', 'mrm' ), 200 );
			}
		}

		// Contact object create and insert or update to database.
		try {
			if ( isset( $params['contact_id'] ) ) {
				$contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
				// Existing contact email address check.
				$contact_email     = ContactModel::get_email_by_id( $contact_id );
				$is_contact_exists = ContactModel::is_contact_exist( $contact_email );
				$is_email_exists   = ContactModel::is_contact_exist_by_id( $contact_email, $contact_id );

				if ( $is_contact_exists && ! $is_email_exists ) {
					return $this->get_error_response( __( 'Email address already assigned to another contact.', 'mrm' ), 200 );
				}
				
				$contact_id = ContactModel::update( $params, $contact_id );
			} else {
				$params     = $this->get_contact_status( $params );
				$contact    = new ContactData( $email, $params );
				$contact_id = ContactModel::insert( $contact );
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
				return $this->get_success_response( __( 'Contact has been saved successfully', 'mrm' ), 201 );
			}
			return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
		} catch ( Exception $e ) {
				return $this->get_error_response( __( 'Contact is not valid', 'mrm' ), 400 );
		}
	}


	/**
	 * Return a contact details
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {
		// Get values from API.
		$params     = MrmCommon::get_api_params_values( $request );
		$contact_id = isset( $params['contact_id'] ) ? $params['contact_id'] : '';
		$contact    = ContactModel::get( $contact_id );

		// Get and merge tags and lists.
		if ( $contact ) {
			$contact               = ContactGroupModel::get_tags_to_contact( $contact );
			$contact               = ContactGroupModel::get_lists_to_contact( $contact );
		}
		if ( $contact && isset( $contact['email'] ) ) {
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
				$contact ['added_by_login'] = esc_html(  $user_meta->data->user_login ); //phpcs:ignore
			} elseif ( !empty( $contact[ 'source' ] ) ) {
				$temp_src = $contact[ 'source'];
				$parts = explode("-", $temp_src);
				if ( "Form" === $parts[0] ){
					$form_id = $parts[1];
					$get_form = FormModel::get($form_id);
					$contact ['added_by_login'] = isset( $get_form['title'] ) ? $get_form['title'] : $form_id;
				} else {
					$contact ['added_by_login'] = esc_html( $contact[ 'source' ] );
				}
			} elseif ( ContactModel::is_contact_meta_exist( $contact_id, '_wc_customer_id' ) ) {
				$contact ['added_by_login'] = esc_html__( 'WooCommerce Checkout', 'mrm' );
			} else {
				$contact ['added_by_login'] = esc_html__( 'External Source', 'mrm' );
			}
			$contact['meta_fields']['avatar_url'] = Contact::get_avatar_url( $contact );
            $contact['general_fields'] = Contact::get_contact_primary_fields();
			$is_wc_active = MrmCommon::is_wc_active();
			if ( $is_wc_active ){
                /**
                 * Summary: Applies filters to enhance the contact profile statistics.
                 *
                 * Description: This line of code applies filters to the 'mail_mint_contact_profile_stats' hook, enhancing the contact profile statistics. 
                 * It takes the existing contact array, usually containing customer summary information, and passes it through the specified filter hook.
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
	 * Return Contacts for list view
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$page     = isset( $params['page'] ) ? $params['page'] : 1;
		$per_page = isset( $params['per-page'] ) ? $params['per-page'] : 10;
		$offset   = ( $page - 1 ) * $per_page;

		// Contact Search keyword.
		$search = isset( $params['search'] ) ? $params['search'] : '';

		$contacts = ContactModel::get_all( $offset, $per_page, $search );

		// Merge tags and lists to contacts.
		$contacts['data'] = array_map(
			function( $contact ) {
				$contact = ContactGroupModel::get_tags_to_contact( $contact );
				$contact = ContactGroupModel::get_lists_to_contact( $contact );
				return $contact;
			},
			$contacts['data']
		);

		// Count contacts groups.
		$contacts['count_groups'] = array(
			'lists'    => ContactGroupModel::get_groups_count( 'lists' ),
			'tags'     => ContactGroupModel::get_groups_count( 'tags' ),
			'segments' => ContactGroupModel::get_groups_count( 'segments' ),
			'contacts' => absint( $contacts['total_count'] ),
		);
        $total_contact = ContactModel::get_contact_total();

		$subscriber_count  = !empty($total_contact['subscribed']) ? $total_contact['subscribed'] : 0 ;
		$unsubcriber_count = !empty($total_contact['unsubscribed']) ? $total_contact['unsubscribed'] : 0;
		$pending_count     = !empty($total_contact['pending']) ? $total_contact['pending'] : 0 ;
	    $total_status      = $subscriber_count + $unsubcriber_count + $pending_count;

		// Count contacts based on status.
		$contacts['count_status'] = array(
			'subscribed'   => $subscriber_count,
			'unsubscribed' => $unsubcriber_count,
			'pending'      => $pending_count,
			'total_status' => $total_status
		);

		// Prepare last activity for every single contact.
		if ( isset( $contacts['data'] ) ) {
			$contacts['data'] = array_map(
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
				$contacts['data']
			);
		}

		$contacts['current_page'] = (int) $page;
		if ( isset( $contacts ) ) {
			return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $contacts );
		}
		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Delete a contact
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$success = ContactModel::destroy( $params['contact_id'] );

		if ( $success ) {
			return $this->get_success_response( __( 'Contact has been deleted successfully', 'mrm' ), 200 );
		}
		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Delete multiple contacts
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		// Get values from API.
		$params = MrmCommon::get_api_params_values( $request );

		$success = ContactModel::destroy_all( $params['contact_ids'] );

		if ( $success ) {
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
        $imported     = 0;
        $skipped      = 0;
        $exists_count = 0;
        $total_count  = 0;

        // Get values from API.
        $params = MrmCommon::get_api_params_values( $request );

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.5.0
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 500 );

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
            add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
        }

        try {
            if ( isset( $params['map'] ) && empty( $params['map'] ) ) {
                return $this->get_success_response( __( 'Please map at least one field for importing.', 'mrm' ), 400 );
            }
            $mappings = isset( $params['map'] ) ? $params['map'] : array();

            $wc_customers = Import::get_wc_customers($params['offset']);
            foreach ( $wc_customers as $wc_customer ) {
                if ( isset( $wc_customer ) ) {
                    $contact_email = $wc_customer['Email'];
                }
                if ( !is_email( $contact_email ) ){
                    $skipped++;
                    continue;
                }

                $status     = isset( $params['status'] ) ? $params['status'][0] : '';
                $status     = !empty ( $status ) ? $status : 'pending';
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
                    return $this->get_success_response( __( 'The email field is required.', 'mrm' ), 400 );
                }
                $contact_email = trim( $contact_args['email'] );

                $exists = ContactModel::is_contact_exist( $contact_email );
                if ( ! $exists ) {
                    $contact      = new ContactData( $contact_email, $contact_args );
                    $contact_id = ContactModel::insert( $contact );

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
					if (isset( $params['skip_existing'] ) && $params['skip_existing']){
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
                $total_count ++;
            }
            /**
             * Prepare data for success response.
             */
            $result = array(
                'imported'          => $imported,
                'total'             => count( $wc_customers ),
                'skipped'           => $skipped,
                'existing_contacts' => $exists_count,
                'offset'            => $params['offset'] + (int) $per_batch,
            );

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
        $total_orders = wc_get_orders(
            array(
				'return'       => 'ids',
				'type'         => 'shop_order',
                'limit'        => -1,
				'parent'       => 0,
				'date_created' => '<' . time(),
			)
        );
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
            'total_batch' => ceil( count( $total_orders ) / (int) $per_batch ),
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
        $imported    = 0;
        $skipped     = 0;
        $exists      = 0;
        $total_count = 0;

        // Get values from API.
        $params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
            add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
        }

        try {
            if ( isset( $params['map'] ) && empty( $params['map'] ) ) {
                return new WP_REST_Response( array(
                    'status'  => 'failed',
                    'message' => __('Please map at least one field for importing.', 'mrm')
                ) );
            }

            $list_id = isset( $params['list_id'] ) ? $params[ 'list_id' ] : '';
            // Check if list ID is empty.
            if( empty( $list_id ) ){
                return new WP_REST_Response( array(
                    'status'  => 'failed',
                    'message' => __('Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.', 'mrm')
                ) );
            }

            $key = isset( $params['key'] ) ? $params['key'] : '';
            // Check if list ID is empty.
            if( empty( $key ) ){
                return new WP_REST_Response( array(
                    'status'  => 'failed',
                    'message' => __('Your API key may be invalid, or you\'ve attempted to access the wrong datacenter.', 'mrm')
                ) );
            }

            $response = Import::get_mailchimp_response( $key, "lists/{$list_id}/members", $params['offset'] );
            $members  = isset( $response['members'] ) ? $response['members'] : array();

            if( empty($members) ){
                $result = array(
                    'total'             => 0,
                    'skipped'           => 0,
                    'existing_contacts' => 0,
                    'imported'          => 0,
                );

                return new WP_REST_Response( array(
                    'status'  => 'success',
                    'message' => __('Import contact from mailchimp has been successful.', 'mrm')
                ) );
    
            }

			foreach ($members as $row){
				$status       = isset( $params['status'] ) ? $params['status'] : '';
                $created_by   = isset( $params['created_by'] ) ? $params['created_by'] : '';

				$contact_args = array(
                    'status'      => $status,
                    'source'      => 'Mailchimp',
                    'meta_fields' => array(),
                    'created_by'  => $created_by,
                );

				foreach ( $params['map'] as $map ){
					$target = isset( $map['target'] ) ? $map['target'] : '';
                	$source = isset( $map['source'] ) ? $map['source'] : '';

					if ( in_array( $target, array( 'email' ), true ) ) {
                        $contact_args[ $target ] = $row[ $source ];
                    } else if ( in_array( $target, array( 'first_name', 'last_name' ), true ) )  {
                        $contact_args[ $target ] = $row[ 'merge_fields' ][ $source ];
                    } else if ( in_array( $source, array( 'addr1', 'addr2', 'city', 'state', 'zip', 'country' ), true ) )  {
						$contact_args[ 'meta_fields' ][ $target ] = isset( $row[ 'merge_fields' ][ 'ADDRESS' ][ $source ] ) ? $row[ 'merge_fields' ][ 'ADDRESS' ][ $source ] : '';
					} else {
						$contact_args[ 'meta_fields' ][ $target ] = $row[ 'merge_fields' ][ $source ];
					}
				}

                if ( ! array_key_exists( 'email', $contact_args ) ) {
                    return new WP_REST_Response( array(
                        'status'  => 'failed',
                        'message' => __('The email field is required.', 'mrm')
                    ) );
                }
                $contact_email = trim( $contact_args['email'] );
                if ( $contact_email && is_email( $contact_email ) ) {
                    $is_exists = ContactModel::is_contact_exist( $contact_email );
                    if ( ! $is_exists ) {
                        $contact_args = $this->get_contact_status( $contact_args );
                        $contact      = new ContactData( $contact_email, $contact_args );
                        $contact_id   = ContactModel::insert( $contact );

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
                        $contact_args   = $this->get_contact_status( $contact_args );
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

            $result = array(
                'total'             => $total_count,
                'skipped'           => $skipped,
                'existing_contacts' => $exists,
                'imported'          => $imported,
            );

            return new WP_REST_Response( array(
                'status'  => 'success',
                'data'    => $result,
                'message' => __('Import contact from mailchimp has been successful.', 'mrm')
            ) );
        } catch ( Exception $e ) {
            return new WP_REST_Response( array(
                'status'  => 'failed',
                'message' => __('Import contact from mailchimp has not been successful.', 'mrm')
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
        $imported     = 0;
        $skipped      = 0;
        $exists_count = 0;
        $total_count  = 0;

        // Get values from API.
        $params = MrmCommon::get_api_params_values( $request );

        /**
         * Get the import batch limit per operation.
         *
         * @param int $per_batch The default import batch limit per operation.
         * @return int The modified import batch limit per operation.
         * 
         * @since 1.4.9
         */
        $per_batch = apply_filters( 'mint_import_batch_limit', 30 );

        if ( isset( $params['automation_control'] ) &&  $params['automation_control'] ){
            add_filter( 'mint_automation_trigger_control_on_import', '__return_true');
        }

        try {
            if ( isset( $params['map'] ) && empty( $params['map'] ) ) {
                return $this->get_success_response( __( 'Please map at least one field for importing.', 'mrm' ), 400 );
            }
            $mappings = isset( $params['map'] ) ? $params['map'] : array();

            $customers = Import::edd_get_customers( $params['offset'], $per_batch );
            foreach ( $customers as $customer ) {
                if ( isset( $customer ) ) {
                    $contact_email = $customer['email'];
                }

                if ( !is_email( $contact_email ) ){
                    $skipped++;
                    continue;
                }

                $status     = isset( $params['status'] ) ? $params['status'][0] : '';
                $status     = !empty ( $status ) ? $status : 'pending';
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
                    return $this->get_success_response( __( 'The email field is required.', 'mrm' ), 400 );
                }
                $contact_email = trim( $contact_args['email'] );

                $exists = ContactModel::is_contact_exist( $contact_email );

                if ( ! $exists ) {
                    $contact      = new ContactData( $contact_email, $contact_args );
                    $contact_id = ContactModel::insert( $contact );

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
					if (isset( $params['skip_existing'] ) && $params['skip_existing']){
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
                    $exists_count ++;
                }
                $total_count ++;
            }

            /**
             * Prepare data for success response.
             */
            $result = array(
                'imported'          => $imported,
                'total'             => $total_count,
                'skipped'           => $skipped,
                'existing_contacts' => $exists_count,
                'offset'            => $params['offset'] + (int) $per_batch,
            );

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

        $subscriber_count  = !empty($total_contact['subscribed']) ? $total_contact['subscribed'] : 0 ;
        $unsubcriber_count = !empty($total_contact['unsubscribed']) ? $total_contact['unsubscribed'] : 0;
        $pending_count     = !empty($total_contact['pending']) ? $total_contact['pending'] : 0 ;
	    $total_status      = $subscriber_count + $unsubcriber_count + $pending_count;

		// Count contacts based on status.
		$contacts['count_status'] = array(
			'subscribed'   => $subscriber_count,
			'unsubscribed' => $unsubcriber_count,
			'pending'      => $pending_count,
			'total_status' => $total_status
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
     * Get total Contact
     *
     * @param mixed $request POST request after form submission on frontend.
     * @return mixed
     * @since 1.0.0
     */
    public function get_total_count( $request ) {
        return ContactModel::get_instance()->get_total_count( $request );
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
}
