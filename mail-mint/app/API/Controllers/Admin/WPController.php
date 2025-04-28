<?php
/**
 * REST API WPPagesRoute Controller
 *
 * Handles requests to the wp page api endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\DataBase\Models\WPModel;
use MRM\Common\MrmCommon;
use Mint\Mrm\Internal\Traits\Singleton;
use WP_Query;
use WP_REST_Request;

/**
 * This is the main class that controls the wp pages feature.
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class WPController {

    use Singleton;

    /**
     * Get all published pages
     *
     * @return \WP_REST_Response|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
     */
    public function get_pages( WP_REST_Request $request ) {
        $params    = MrmCommon::get_api_params_values( $request );
        $term      = isset( $params['term'] ) ? $params['term'] : '';

        $args = array(
            's'              => $term,
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $posts_query = new WP_Query( $args );
        $pages       = array();
        if ( $posts_query->have_posts() ) {
            while ( $posts_query->have_posts() ) {
                $posts_query->the_post();
                $page = array(
                    'value' => get_the_ID(),
                    'label' => get_the_title(),
                );
                array_push( $pages, $page );
            }
            wp_reset_postdata();
        }
        return rest_ensure_response( [ 'data' => $pages, 'status' => 200 ] );
    }

    /**
     * Retrieve post data for the WordPress post search endpoint.
     *
     * This function handles requests to retrieve post data based on search terms for use in the WordPress post search feature.
     *
     * @access public
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return WP_REST_Response Response object containing post data and status.
     * @since 1.5.9
     */
    public function get_posts( WP_REST_Request $request ) {
        $params    = MrmCommon::get_api_params_values( $request );
        $term      = isset( $params['term'] ) ? $params['term'] : '';

        $args = array(
            's'              => $term,
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $posts_query = new WP_Query( $args );
        $posts       = array();
        if ( $posts_query->have_posts() ) {
            while ( $posts_query->have_posts() ) {
                $posts_query->the_post();
                $post = array(
                    'value' => get_the_ID(),
                    'label' => get_the_title(),
                );
                array_push( $posts, $post );
            }
            wp_reset_postdata();
        }
        return rest_ensure_response( [ 'data' => $posts, 'status' => 200 ] );
    }

    /**
     * Retrieve product data for the WooCommerce product search endpoint.
     *
     * This function handles requests to retrieve product data based on search terms for use in the WooCommerce product search feature.
     *
     * @access public
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return WP_REST_Response Response object containing product data and status.
     * @since 1.5.9
     */
    public function get_products( WP_REST_Request $request ) {
        $params    = MrmCommon::get_api_params_values( $request );
        $term      = isset( $params['term'] ) ? $params['term'] : '';
        $products  = array();

        if ( $term && MrmCommon::is_wc_active() ) {
            $data_store = \WC_Data_Store::load( 'product' );
            $ids        = $data_store->search_products( $term, '', true, false, 10 );

            foreach ( $ids as $id ) {
                $product_object = wc_get_product( $id );

                if ( ! wc_products_array_filter_readable( $product_object ) ) {
                    continue;
                }

                $formatted_name = $product_object->get_formatted_name();

                $products[ $product_object->get_id() ] = rawurldecode( wp_strip_all_tags( $formatted_name ) );
            }
        }
        return rest_ensure_response( [ 'data' => $products, 'status' => 200 ] );
    }

    /**
     * Retrieves categories based on search term from the REST API.
     *
     * This function handles a REST API request to retrieve categories. It accepts a search term
     * as a parameter and searches for matching terms in both the 'category' and 'product_cat'
     * taxonomies.
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return WP_REST_Response The REST API response containing matching categories.
     * @since 1.5.11
     */
    public function get_categories( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        $term   = isset( $params['term'] ) ? $params['term'] : '';

        $taxonomies = array('category');

        // Check if WooCommerce is active.
        if ( MrmCommon::is_wc_active() ) {
            $taxonomies[] = 'product_cat';
        }

        $terms = get_terms( array(
            'taxonomy' => $taxonomies,
            'name__like' => $term,
            'hide_empty' => false
        ) );

        $categories = array();
        foreach ($terms as $term) {
            $categories[] = array(
                'label' => $term->name,
                'value' => $term->term_id,
            );
        }
        return rest_ensure_response( [ 'data' => $categories, 'status' => 200 ] );
    }

    /**
     * Retrieves tags based on search term from the REST API.
     *
     * This function handles a REST API request to retrieve tags. It accepts a search term
     * as a parameter and searches for matching terms in both the 'category' and 'product_tag'
     * taxonomies.
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return WP_REST_Response The REST API response containing matching tags.
     * @since 1.5.11
     */
    public function get_tags( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        $term   = isset( $params['term'] ) ? $params['term'] : '';

        $taxonomies = array('post_tag');

        // Check if WooCommerce is active.
        if ( MrmCommon::is_wc_active() ) {
            $taxonomies[] = 'product_tag';
        }

        $terms = get_terms( array(
            'taxonomy' => $taxonomies,
            'name__like' => $term,
            'hide_empty' => false
        ) );

        $tags = array();
        foreach ($terms as $term) {
            $tags[] = array(
                'label' => $term->name,
                'value' => $term->term_id,
            );
        }
        return rest_ensure_response( [ 'data' => $tags, 'status' => 200 ] );
    }

    /**
     * Retrieves admin users based on search term from the REST API.
     *
     * This function handles a REST API request to retrieve admin users. It accepts a search term
     * as a parameter and searches for matching users based on the provided term.
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return WP_REST_Response The REST API response containing matching admin users.
     * @since 1.17.1
     */
    public function get_admins( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        $term   = isset( $params['term'] ) ? $params['term'] : '';
        
        if ( ! $term ) {
            return rest_ensure_response( [ 'admins' => array(),'success' => false ] );
        }

        $args = array(
            'search' => '*' . esc_attr($term) . '*',
            'fields' => array('ID', 'user_email')
        );
    
        $users  = get_users($args);
        $admins = array_map(function($user) {
            return array(
                'label' => $user->user_email,
                'value' => $user->ID
            );
        }, $users);
        return rest_ensure_response( [ 'admins' => $admins, 'success' => true ] );
    }
}
