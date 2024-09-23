<?php
/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-02-01 11:03:17
 * @modify date 2024-02-01 11:03:17
 * @package /app/API/Actions/Admin
 */

use Mint\MRM\API\Actions\Action;
use MRM\Common\MrmCommon;

/**
 * Class TemplateAction
 *
 * Summary: Template Action implementation.
 * Description: Implements the Template Action interface and provides methods to fetch and manipulate templates.
 *
 * @since 1.9.0
 */
class TemplateAction implements Action {

    /**
     * Retrieve templates based on specified parameters.
     *
     * @param array $params An associative array of parameters:
     *                      - 'page'       : The current page for pagination (default: 1).
     *                      - 'per-page'   : The number of results to retrieve per page (default: 10).
     *
     * @return array An array containing information about templates.
     * 
     * @since 1.9.0
     */
    public function retrieve_and_format_templates( $params ) {
        global $wpdb;
        // Extract parameters or use default values.
        $page     = isset( $params['page'] ) ? $params['page'] : 1;
        $per_page = isset( $params['per-page'] ) ? $params['per-page'] : 10;
        $offset   = ( $page - 1 ) * $per_page;

        // Map 'order-by' parameter to actual database fields.
        $order_by_map = array(
            'created_at' => 'ID',
            'title'      => 'post_title',
        );

        // Get 'order-by' and 'order-type' parameters or use default values.
        $order_by   = isset( $params['order-by'] ) && isset( $order_by_map[ $params['order-by'] ] ) ? $order_by_map[ $params['order-by'] ] : 'ID';
        $order_type = isset( $params['order-type'] ) ? strtoupper( $params['order-type'] ) : 'DESC';

        // Get 'search' parameter or use default value.
        $search = isset( $params['search'] ) ? $params['search'] : '';

        // Fetch templates from the database here.
        $templates_data = $wpdb->get_results($wpdb->prepare("SELECT * 
                FROM {$wpdb->prefix}posts 
                LEFT JOIN {$wpdb->prefix}postmeta ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id 
                WHERE {$wpdb->prefix}posts.post_type = 'mint_email_template' 
                AND {$wpdb->prefix}posts.post_status = 'draft' 
                AND (
                    ({$wpdb->prefix}postmeta.meta_key = 'mailmint_wc_email_type' AND {$wpdb->prefix}postmeta.meta_value = %s) 
                    OR 
                    ({$wpdb->prefix}postmeta.meta_key = 'mailmint_wc_email_type' AND {$wpdb->prefix}postmeta.meta_value IS NULL)
                )
                AND {$wpdb->prefix}posts.post_title LIKE %s
                ORDER BY {$wpdb->prefix}posts.{$order_by} {$order_type}
                LIMIT %d OFFSET %d
            ", 'default', '%' . $wpdb->esc_like($search) . '%', $per_page, $offset));

        if ( ! empty( $templates_data ) ) {
            $templates = array();
            foreach ( $templates_data as $template ) {
                if ( ! isset( $template->ID ) ) {
                    continue;
                }

                $templates['templates'][] = array(
                    'id'              => $template->ID,
                    'title'           => $template->post_title,
                    'created_at'      => MrmCommon::date_time_format_with_core( $template->post_date ),
                    'html_content'    => get_post_meta( $template->ID, 'mailmint_email_template_html_content', true ),
                    'json_content'    => get_post_meta( $template->ID, 'mailmint_email_template_json_content', true ),
                    'thumbnail_image' => get_post_meta( $template->ID, 'mailmint_email_template_thumbnail', true ),
                    'wc_email_type'   => get_post_meta($template->ID, 'mailmint_wc_email_type', true),
                );

                $filtered_data = array_filter( $templates['templates'], function( $item ) {
                    return $item['wc_email_type'] === 'default' || $item['wc_email_type'] === '';
                } );

                $templates['templates'] = $filtered_data;
            }

            $search_term = '%' . $wpdb->esc_like($search) . '%'; // Escaping search string

            $templates['total_count'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) 
                FROM {$wpdb->prefix}posts 
                LEFT JOIN {$wpdb->prefix}postmeta ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id 
                WHERE {$wpdb->prefix}posts.post_type = 'mint_email_template' 
                AND {$wpdb->prefix}posts.post_status = 'draft' 
                AND (
                    ({$wpdb->prefix}postmeta.meta_key = 'mailmint_wc_email_type' AND {$wpdb->prefix}postmeta.meta_value = 'default') 
                    OR 
                    ({$wpdb->prefix}postmeta.meta_key = 'mailmint_wc_email_type' AND {$wpdb->prefix}postmeta.meta_value IS NULL)
                )
                AND {$wpdb->prefix}posts.post_title LIKE %s
            ", $search_term));

            $templates['total_pages'] = ( 0 !== $per_page ) ? ceil( $templates['total_count'] / $per_page ) : 0;
            return $templates;
        }

        return array(
            'templates' => array(),
        );
    }

    /**
     * Delete template based on specified parameters.
     *
     * @param array $params An associative array of parameters:
     *                      - 'template_id' : The template id to delete.
     *
     * @return bool True if the template was deleted successfully, false otherwise.
     * 
     * @since 1.9.0
     */
    public function delete_template( $params ) {
        // Extract parameters or use default values.
        $template_ids = isset( $params['template_ids'] ) ? $params['template_ids'] : array();
        
        foreach ($template_ids as $template_id) {
            $thumbnail = get_post_meta( $template_id, 'mailmint_email_template_thumbnail', true );
            $thumbnail = ! empty( $thumbnail[ 'path' ] ) ? $thumbnail[ 'path' ] : null;
    
            if ( $template_id && wp_delete_post( $template_id ) ) {
                if ( ! empty( $thumbnail ) ) {
                    unlink( $thumbnail );
                }
            } else {
                // Return false if any deletion fails.
                return false;
            }
        }
    
        // Return true if all deletions are successful.
        return true;
    }

    /**
     * Update template based on specified parameters.
     *
     * @param array $params An associative array of parameters:
     *                      - 'template_id' : The template id to update.
     *                      - 'title'       : The title of the template.
     *                      - 'html'        : The HTML content of the template.
     *                      - 'json_content': The JSON content of the template.
     *                      - 'thumbnail'   : The thumbnail image of the template.
     *                      - 'editor'      : The editor type of the template.
     *                      - 'wooCommerce_email_type' : The WooCommerce email type of the template.
     *                      - 'wooCommerce_email_enable' : The WooCommerce email enable status of the template.
     *
     * @return bool True if the template was updated successfully, false otherwise.
     * 
     * @since 1.10.5
     */
    public function update_template( $params ) {
        // Extract parameters or use default values.
        $template_id = isset( $params['template_id'] ) ? $params['template_id'] : 0;

        $post_id = wp_update_post(
            array(
                'ID'           => $template_id,
                'post_type'    => 'mint_email_template',
                'post_title'   => sanitize_text_field( $params[ 'title' ] ),
                'post_status'  => 'draft',
                'post_author'  => get_current_user_id(),
            )
        );
        
        $params['wooCommerce_email_type']   = isset( $params['wooCommerce_email_type'] ) ? $params['wooCommerce_email_type'] : 'default';
        $params['wooCommerce_email_enable'] = isset( $params['wooCommerce_email_enable'] ) ? $params['wooCommerce_email_enable'] : false;

        if ( $post_id ) {
			if ( !empty( $params[ 'html' ] ) ) {
				$editor        = isset( $params['editor'] ) ? $params['editor'] : 'advanced-builder';

                if( 'default' === $params['wooCommerce_email_type'] ) {
                    $thumbnail_url = $this->upload_template_thumnail($params[ 'thumbnail' ]);
				    update_post_meta( $post_id, 'mailmint_email_template_thumbnail', $thumbnail_url );
                }
				update_post_meta( $post_id, 'mailmint_email_template_html_content', $params[ 'html' ] );
				update_post_meta( $post_id, 'mailmint_email_template_json_content', $params[ 'json_content' ] );
				update_post_meta( $post_id, 'mailmint_email_editor_type', $editor );
				update_post_meta( $post_id, 'mailmint_wc_email_type', $params['wooCommerce_email_type'] );
                update_post_meta( $post_id, 'mailmint_wc_customize_enable', $params['wooCommerce_email_enable'] );
			}
		}
        return true;
    }

    /**
	 * Save template thumbnail image from image data source
	 *
	 * @param string $thumbnail_data Image data source.
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	private function upload_template_thumnail( $thumbnail_data ) {
		if ( ! empty( $thumbnail_data ) ) {
			$thumbnail_data = explode( ',', $thumbnail_data );
			$thumbnail_data = !empty( $thumbnail_data[1] ) ? base64_decode($thumbnail_data[1]) : '';

			if ( '' === $thumbnail_data ) {
				return;
			}
		}
		else {
			return;
		}

		$template_thumbnail_dir = MRM_UPLOAD_DIR . '/template-thumbnails/campaigns';
		$template_thumbnail_url = MRM_UPLOAD_URL . '/template-thumbnails/campaigns';

		if ( !file_exists( $template_thumbnail_dir ) ) {
			wp_mkdir_p( $template_thumbnail_dir );
		}

		$image_name = rand( time(), time() + time() ) . '.png';
		$image_dir = $template_thumbnail_dir . '/' . $image_name;
		$image_url = $template_thumbnail_url . '/' . $image_name;

		return file_put_contents( $image_dir, $thumbnail_data ) ? array( 'url' => $image_url, 'path' => $image_dir ) : '';
	}

    /**
     * Retrieve WooCommerce email templates based on specified parameters.
     *
     * @param array $params An associative array of parameters:
     *                      - 'type' : The type of WooCommerce email template to retrieve.
     *
     * @return array An array containing information about WooCommerce email templates.
     * 
     * @since 1.10.5
     */
    public function get_woocommerce_email_template( $params ) {
        $type = isset( $params['type'] ) ? $params['type'] : 'default';

        global $wpdb;
        $templates_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}posts LEFT JOIN {$wpdb->prefix}postmeta ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id 
                WHERE {$wpdb->prefix}posts.post_type = 'mint_email_template' 
                AND {$wpdb->prefix}posts.post_status = 'draft' 
                AND {$wpdb->prefix}postmeta.meta_key = 'mailmint_wc_email_type' 
                AND {$wpdb->prefix}postmeta.meta_value = %s
            ", $type ) );
        
        $templates = array();

        if ( ! empty( $templates_data ) ) {
            foreach ( $templates_data as $template ) {
                if ( ! isset( $template->ID ) ) {
                    continue;
                }

                $templates['templates'] = array(
                    'id'                        => $template->ID,
                    'title'                     => $template->post_title,
                    'created_at'                => MrmCommon::date_time_format_with_core( $template->post_date ),
                    'html_content'              => get_post_meta( $template->ID, 'mailmint_email_template_html_content', true ),
                    'json_content'              => get_post_meta( $template->ID, 'mailmint_email_template_json_content', true ),
                    'thumbnail_image'           => get_post_meta( $template->ID, 'mailmint_email_template_thumbnail', true ),
                    'wc_email_type'             => get_post_meta($template->ID, 'mailmint_wc_email_type', true),
                    'wooCommerce_email_enable' => get_post_meta($template->ID, 'mailmint_wc_customize_enable', true),
                );
            }
        }
        return $templates;
    }
        
}