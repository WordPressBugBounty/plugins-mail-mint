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
        // Define the table name.
        $table_name = $wpdb->prefix . 'mint_email_templates';
        // Extract parameters or use default values.
        $page     = isset( $params['page'] ) ? $params['page'] : 1;
        $per_page = isset( $params['per-page'] ) ? $params['per-page'] : 10;
        $offset   = ( $page - 1 ) * $per_page;

        // Map 'order-by' parameter to actual database fields.
        $order_by_map = array(
            'created_at' => 'ID',
            'title'      => 'title',
        );

        // Get 'order-by' and 'order-type' parameters or use default values.
        $order_by   = isset( $params['order-by'] ) && isset( $order_by_map[ $params['order-by'] ] ) ? $order_by_map[ $params['order-by'] ] : 'ID';
        $order_type = isset( $params['order-type'] ) ? strtoupper( $params['order-type'] ) : 'DESC';

        // Get 'search' parameter or use default value.
        $search = isset( $params['search'] ) ? $params['search'] : '';

        // Define the query.
        $query = "
            SELECT id, title, thumbnail, thumbnail_data, json_content, editor_type, email_type, customizable, author_id, status, newsletter_type, newsletter_id, created_at, updated_at
            FROM $table_name
            WHERE (email_type = %s OR email_type IS NULL OR email_type = '')
            AND title LIKE %s
            ORDER BY $order_by $order_type
            LIMIT %d OFFSET %d
        ";

        // Prepare the query with pagination and search parameters.
        $query = $wpdb->prepare($query, 'default', '%' . $wpdb->esc_like($search) . '%', $per_page, $offset);
        // Execute the query.
        $results = $wpdb->get_results($query, ARRAY_A);

        // Unserialize specific fields in the results
        foreach ($results as &$result) {
            if (isset($result['thumbnail'])) {
                $result['thumbnail'] = maybe_unserialize($result['thumbnail']);
            }
            if (isset($result['thumbnail_data'])) {
                $result['thumbnail_data'] = maybe_unserialize($result['thumbnail_data']);
            }
            if (isset($result['json_content'])) {
                $result['json_content'] = maybe_unserialize($result['json_content']);
            }
        }

        // Define the count query.
        $count_query = "
            SELECT COUNT(*)
            FROM $table_name
            WHERE (email_type = %s OR email_type IS NULL OR email_type = '')
            AND title LIKE %s
        ";

        // Prepare the count query with the search parameter
        $count_query = $wpdb->prepare($count_query, 'default', '%' . $wpdb->esc_like($search) . '%');

        // Execute the count query
        $total_count = (int) $wpdb->get_var($count_query);

        // Calculate total pages
        $total_pages = (0 !== $per_page) ? ceil($total_count / $per_page) : 0;
        return array(
            'templates'   => $results,
            'total_count' => $total_count,
            'total_pages' => $total_pages,
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
            $thumbnail = $this->get_template_thumbnail( $template_id );
            $path      = ! empty( $thumbnail['thumbnail'][ 'path' ] ) ? $thumbnail['thumbnail'][ 'path' ] : null;
    
            if ( $template_id && $this->delete_template_by_id( $template_id ) ) {
                if ( ! empty( $path ) ) {
                    unlink( $path );
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
     * Delete template based on specified parameters.
     *
     * @param int $template_id The template id to delete.
     *
     * @return bool True if the template was deleted successfully, false otherwise.
     * 
     * @since 1.9.0
     */
    public function delete_template_by_id( $template_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mint_email_templates';
        $result = $wpdb->delete( $table_name, array( 'id' => $template_id ) );
        return $result !== false;
    }

    /**
     * Retrieve template thumbnail based on specified parameters.
     *
     * @param array $params An associative array of parameters:
     *                      - 'template_id' : The template id to retrieve thumbnail.
     *
     * @return array An array containing information about template thumbnail.
     * 
     * @since 1.9.0
     */
    public function get_template_thumbnail( $template_id ) {
        global $wpdb;
        $thumbnail = $wpdb->get_row( $wpdb->prepare( "SELECT thumbnail, thumbnail_data FROM {$wpdb->prefix}mint_email_templates WHERE id = %d", $template_id ), ARRAY_A );
        if ( ! empty( $thumbnail ) ) {
            $thumbnail['thumbnail'] = maybe_unserialize( $thumbnail['thumbnail'] );
            $thumbnail['thumbnail_data'] = maybe_unserialize( $thumbnail['thumbnail_data'] );
        }
        return $thumbnail;
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
        global $wpdb;

        // Define the table name
        $table_name = $wpdb->prefix . 'mint_email_templates';
        // Extract parameters or use default values.
        $template_id     = isset( $params['template_id'] ) ? $params['template_id'] : 0;
        $title           = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $html_content    = isset($params['html']) ? $params['html'] : '';
        $json_content    = isset($params['json_content']) ? $params['json_content'] : '';
        $editor_type     = isset($params['editor']) ? $params['editor'] : 'advanced-builder';
        $thumbnail       = isset($params['thumbnail']) ? $this->upload_template_thumnail('advanced-builder' === $editor_type ? $params['thumbnail'] : '') : '';
        $thumbnail_data  = isset($params['thumbnail']) ? $params['thumbnail'] : '';
        $email_type      = isset($params['wooCommerce_email_type']) ? $params['wooCommerce_email_type'] : 'default';
        $customizable    = isset($params['wooCommerce_email_enable']) ? (int) $params['wooCommerce_email_enable'] : 0;
        $status          = isset($params['status']) ? $params['status'] : 'draft';
        $newsletter_type = isset($params['newsletter_type']) ? $params['newsletter_type'] : NULL;
        $newsletter_id   = isset($params['newsletter_id']) ? (int) $params['newsletter_id'] : NULL;
        // Update the template in the database
        $result = $wpdb->update(
                $table_name,
                array(
                    'title' => $title,
                    'html_content' => $html_content,
                    'json_content' => maybe_serialize($json_content),
                    'editor_type' => $editor_type,
                    'thumbnail' => maybe_serialize($thumbnail),
                    'thumbnail_data' => maybe_serialize($thumbnail_data),
                    'email_type' => $email_type,
                    'customizable' => $customizable,
                    'status' => $status,
                    'newsletter_type' => $newsletter_type,
                    'newsletter_id' => $newsletter_id,
                    'updated_at' => current_time('mysql', 1)
                ),
                array('id' => $template_id),
                array(
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%d',
                    '%s'
                ),
                array('%d')
            );

        return $result !== false;
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
		}else {
			return;
		}

		$template_thumbnail_dir = MRM_UPLOAD_DIR . 'template-thumbnails/campaigns';
		$template_thumbnail_url = MRM_UPLOAD_URL . 'template-thumbnails/campaigns';

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
        global $wpdb;
        // Define the table name.
        $table_name = $wpdb->prefix . 'mint_email_templates';
        $type       = isset( $params['type'] ) ? $params['type'] : 'default';

        // Define the query.
        $query = "
            SELECT id, title, thumbnail, thumbnail_data, json_content, editor_type, email_type, customizable, author_id, status, newsletter_type, newsletter_id, created_at, updated_at
            FROM $table_name
            WHERE email_type = %s
            ORDER BY ID DESC LIMIT 1
        ";

        // Prepare the query with pagination and search parameters.
        $query = $wpdb->prepare($query, $type);
        // Execute the query.
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $templates = array();

        if ( ! empty( $results ) ) {
            foreach ( $results as $result ) {
                if ( ! isset( $result['id'] ) ) {
                    continue;
                }

                $templates['templates'] = array(
                    'id'                       => $result['id'],
                    'title'                    => $result['title'],
                    'json_content'             => maybe_unserialize($result['json_content']),
                    'wc_email_type'            => $result['email_type'],
                    'wooCommerce_email_enable' => (int) $result['customizable'],
                    'thumbnail_image'          => maybe_unserialize($result['thumbnail']),
                );
            }
        }

        return $templates;
    }

    /**
     * Retrieve template value based on specified parameters.
     *
     * @param int $id The template id to retrieve value.
     * @param string $key The key to retrieve value.
     *
     * @return string The value of the template.
     * 
     * @since 1.15.3
     */
    public function retrieve_template_value_by_key( $id, $key ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mint_email_templates';
        $result = $wpdb->get_var( $wpdb->prepare( "SELECT $key FROM $table_name WHERE id = %d", $id ) );
        return $result;
    }   
}