<?php
/**
 * REST API Segment Controller (Free Base)
 *
 * Handles field types retrieval and contact preview filtering.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since 1.25.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Constants;
use Mint\MRM\DataBase\Models\CustomFieldModel;
use Mint\MRM\DataBase\Models\ContactModel;
use Mint\MRM\DataBase\Models\ContactGroupModel;
use Mint\MRM\Internal\Admin\Segmentation\SegmentFilterService;
use MRM\Common\MrmCommon;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Segment Controller (Free Base)
 *
 * Provides available field types for filtering and evaluates advanced contact filters.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since 1.25.0
 */
class SegmentController extends AdminBaseController {

	/**
	 * Get specific segment contacts (preview with filters)
	 *
	 * Uses SegmentFilterService to build WHERE clause and retrieve contacts.
	 *
	 * @param WP_REST_Request $request WP_REST_Request.
	 * @return WP_REST_Response|array
	 * @since 1.25.0
	 */
	public function get_preview_contacts( WP_REST_Request $request ) {
		$params          = MrmCommon::get_api_params_values( $request );
		$segment_filters = isset( $params['filters'] ) ? $params['filters'] : array();

		$page       = isset( $params['page'] ) ? $params['page'] : 1;
		$per_page   = isset( $params['per-page'] ) ? $params['per-page'] : 10;
		$count_only = isset( $params['count-only'] ) ? $params['count-only'] : false;
		$order_by   = isset( $params['order-by'] ) ? strtolower( $params['order-by'] ) : 'email';
		$order_type = isset( $params['order-type'] ) ? strtolower( $params['order-type'] ) : 'ASC';

		$filter_service = new SegmentFilterService();
		$where_query    = $segment_filters ? $filter_service->buildWhereClause( $segment_filters ) : false;

		if ( ! $where_query ) {
			return $this->get_error_response( __( 'No contacts matched.', 'mrm' ), 201 );
		}

		$query_params = array(
			'page'       => $page,
			'per_page'   => $per_page,
			'order_by'   => $order_by,
			'order_type' => $order_type,
			'count_only' => $count_only,
		);

		$segment_contacts = $filter_service->getContacts( $where_query, $query_params );

		if ( ! $count_only && is_array( $segment_contacts ) && ! empty( $segment_contacts['data'] ) ) {
			foreach ( $segment_contacts['data'] as $key => $contact ) {
				$contact_id                       = isset( $contact['id'] ) ? $contact['id'] : 0;
				$meta_fields                      = ContactModel::get_meta( $contact_id );
				$contact                          = array_merge( $contact, $meta_fields );
				$contact                          = ContactGroupModel::get_tags_to_contact( $contact );
				$contact                          = ContactGroupModel::get_lists_to_contact( $contact );
				$segment_contacts['data'][ $key ] = $contact;
			}
		}

		if ( ! empty( $segment_contacts ) ) {
			return $this->get_success_response( __( 'Successfully fetched segment contacts!', 'mrm' ), 200, $segment_contacts );
		}

		return $this->get_error_response( __( 'No contacts matched.', 'mrm' ), 201 );
	}

	/**
	 * Retrieve a list of available field types, including default, primary, custom, and engagement fields.
	 *
	 * @return WP_REST_Response
	 * @since 1.25.0
	 */
	public function retrieve_available_field_types() {
		$field_types = $this->get_default_field_types();
		$field_types = $this->add_primary_fields( $field_types );
		$field_types = $this->add_custom_fields( $field_types );
		$field_types = $this->add_email_engagement_fields( $field_types );

		/**
		 * Filter available segment field types.
		 * Allows Pro and integrations to append additional field categories (e.g. WooCommerce).
		 *
		 * @param array $field_types Field types array.
		 * @since 1.25.0
		 */
		$field_types = apply_filters( 'mrm_available_segment_field_types', $field_types );

		$response['status']  = 'success';
		$response['message'] = __( 'Field types have been retrieved successfully.', 'mrm' );
		$response['data']    = $field_types;

		return new WP_REST_Response( $response );
	}

	/**
	 * Get default static field types.
	 *
	 * @return array
	 * @since 1.25.0
	 */
	public function get_default_field_types() {
		return array(
			'Contact Segment' => Constants::$static_primary_field_types,
		);
	}

	/**
	 * Add primary fields to field types array.
	 *
	 * @param array $field_types Field types array.
	 * @return array
	 * @since 1.25.0
	 */
	public function add_primary_fields( $field_types ) {
		$fields = get_option( 'mint_contact_primary_fields', Constants::$primary_contact_fields );
		$fields = array_merge( ...array_values( $fields ) );

		$primary_fields = array();
		foreach ( $fields as $item ) {
			$slug  = isset( $item['slug'] ) ? $item['slug'] : '';
			$label = isset( $item['meta']['label'] ) ? $item['meta']['label'] : '';
			$type  = isset( $item['segment'] ) ? $item['segment'] : 'address';

			foreach ( Constants::$segment_primary_field_slugs as $group => $slugs ) {
				if ( in_array( $slug, $slugs, true ) ) {
					$primary_fields[ $group ][] = array(
						'label' => $label,
						'type'  => $type,
						'value' => $slug,
					);
					break;
				}
			}
		}

		return array_merge( $primary_fields, $field_types );
	}

	/**
	 * Add custom fields to field types array.
	 *
	 * @param array $field_types Field types array.
	 * @return array
	 * @since 1.25.0
	 */
	public function add_custom_fields( $field_types ) {
		$customer_fields = CustomFieldModel::get_custom_fields_to_segment();

		if ( empty( $customer_fields ) ) {
			return $field_types;
		}

		$customer_fields = array_map(
			function ( $customer_field ) {
				$meta                      = maybe_unserialize( $customer_field['meta'] );
				$customer_field['label']   = isset( $meta['label'] ) ? $meta['label'] : '';
				$customer_field['options'] = isset( $meta['options'] ) ? $meta['options'] : '';
				unset( $customer_field['meta'] );
				return $customer_field;
			},
			$customer_fields
		);

		$field_types['Custom'] = $customer_fields;

		return $field_types;
	}

	/**
	 * Add email engagement fields to field types array.
	 *
	 * @param array $field_types Field types array.
	 * @return array
	 * @since 1.25.0
	 */
	public function add_email_engagement_fields( array $field_types ): array {
		return array_merge(
			$field_types,
			array(
				'Email Engagement' => array(
					array(
						'label' => __( 'Opened Email', 'mrm' ),
						'type'  => 'email_engagement',
						'value' => 'opened_email',
					),
					array(
						'label' => __( 'Clicked Link', 'mrm' ),
						'type'  => 'email_engagement',
						'value' => 'clicked_link',
					),
					array(
						'label' => __( 'Email Was Sent', 'mrm' ),
						'type'  => 'email_engagement',
						'value' => 'email_was_sent',
					),
					array(
						'label' => __( 'Total Opens Count', 'mrm' ),
						'type'  => 'email_engagement',
						'value' => 'total_opens_count',
					),
					array(
						'label' => __( 'Total Clicks Count', 'mrm' ),
						'type'  => 'email_engagement',
						'value' => 'total_clicks_count',
					),
					array(
						'label' => __( 'Emails Received', 'mrm' ),
						'type'  => 'email_engagement',
						'value' => 'emails_received_count',
					),
					array(
						'label' => __( 'Email Bounced', 'mrm' ),
						'type'  => 'email_engagement',
						'value' => 'email_bounced',
					),
				),
			)
		);
	}
}
