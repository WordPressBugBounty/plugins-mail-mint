<?php
/**
 * REST API Product Controller
 *
 * Handles requests to the products endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

/**
 * This is the main class that controls the products feature.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class ProductController extends \WP_REST_Controller {

	/**
	 * The namespace.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Rest base for the current object.
	 *
	 * @var string
	 */
	protected $rest_base;

	/**
	 * Category_List_Rest constructor.
	 */
	public function __construct() {
		$this->namespace = 'mrm/v1';
		$this->rest_base = 'products';
	}

	/**
	 * Check permissions for the read.
	 *
	 * @param /WP_REST_Request $request get data from request.
	 *
	 * @return bool|/WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'read' ) ) {
			return new \WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the category resource.', 'mrm' ), array( 'status' => $this->authorization_status_code() ) );
		}
		return true;
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(

				array(
					'methods'  => \WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_products' ),
				),
				'schema' => null,

			)
		);
	}



	/**
	 * Get all products
	 *
	 * @param mixed $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_products( $request ) {
		$registered = $this->get_collection_params();
		$args       = array();

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'exclude'        => 'post__not_in',
			'include'        => 'post__in',
			'menu_order'     => 'menu_order',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'parent'         => 'post_parent__in',
			'parent_exclude' => 'post_parent__not_in',
			'search'         => 's',
			'slug'           => 'post_name__in',
			'status'         => 'post_status',
		);

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Check for & assign any parameters which require special handling or setting.
		$args['date_query'] = array();

		if ( isset( $registered['before'], $request['before'] ) ) {
			$args['date_query'][] = array(
				'before' => $request['before'],
				'column' => 'post_date',
			);
		}

		if ( isset( $registered['modified_before'], $request['modified_before'] ) ) {
			$args['date_query'][] = array(
				'before' => $request['modified_before'],
				'column' => 'post_modified',
			);
		}

		if ( isset( $registered['after'], $request['after'] ) ) {
			$args['date_query'][] = array(
				'after'  => $request['after'],
				'column' => 'post_date',
			);
		}

		if ( isset( $registered['modified_after'], $request['modified_after'] ) ) {
			$args['date_query'][] = array(
				'after'  => $request['modified_after'],
				'column' => 'post_modified',
			);
		}

		// Ensure our per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $request['per_page'];
		}

		$args              = $this->prepare_tax_query( $args, $request );
		$args['post_type'] = 'product';

		$response = rest_ensure_response( array() );
		return $response;
	}


	/**
	 * Prepares the 'tax_query' for a collection of posts.
	 *
	 * @param array            $args List of arguments.
	 * @param \WP_REST_Request $request Request object used to generate the response.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	private function prepare_tax_query( array $args, \WP_REST_Request $request ) {
		$relation = $request['tax_relation'];

		if ( $relation ) {
			// phpcs:disable
			$args['tax_query'] = array( 'relation' => $relation );
			// phpcs:enable
		}

		$taxonomies = wp_list_filter(
			get_object_taxonomies( $this->post_type, 'objects' ),
			array( 'show_in_rest' => true )
		);

		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

			$tax_include = $request[ $base ];
			$tax_exclude = $request[ $base . '_exclude' ];

			if ( $tax_include ) {
				$terms            = array();
				$include_children = false;
				$operator         = 'IN';

				if ( rest_is_array( $tax_include ) ) {
					$terms = $tax_include;
				} elseif ( rest_is_object( $tax_include ) ) {
					$terms            = empty( $tax_include['terms'] ) ? array() : $tax_include['terms'];
					$include_children = ! empty( $tax_include['include_children'] );

					if ( isset( $tax_include['operator'] ) && 'AND' === $tax_include['operator'] ) {
						$operator = 'AND';
					}
				}

				if ( $terms ) {
					$args['tax_query'][] = array(
						'taxonomy'         => $taxonomy->name,
						'field'            => 'term_id',
						'terms'            => $terms,
						'include_children' => $include_children,
						'operator'         => $operator,
					);
				}
			}

			if ( $tax_exclude ) {
				$terms            = array();
				$include_children = false;

				if ( rest_is_array( $tax_exclude ) ) {
					$terms = $tax_exclude;
				} elseif ( rest_is_object( $tax_exclude ) ) {
					$terms            = empty( $tax_exclude['terms'] ) ? array() : $tax_exclude['terms'];
					$include_children = ! empty( $tax_exclude['include_children'] );
				}

				if ( $terms ) {
					$args['tax_query'][] = array(
						'taxonomy'         => $taxonomy->name,
						'field'            => 'term_id',
						'terms'            => $terms,
						'include_children' => $include_children,
						'operator'         => 'NOT IN',
					);
				}
			}
		}

		return $args;
	}

}
