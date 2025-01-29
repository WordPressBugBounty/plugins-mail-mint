<?php
/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2025-01-13 15:33:17
 * @modify date 2025-01-13 15:33:17
 */
namespace Mint\MRM\Admin\API\Controllers\Connector;
use MintMail\App\Internal\Automation\HelperFunctions;
use  Mint\MRM\Admin\API\Controllers\BaseConnectorHandler;

class LearnDashConnectorHandler extends BaseConnectorHandler {

    /**
     * Retrieves a list of LearnDash courses.
     *
     * This method fetches all published LearnDash courses, optionally filtered by a search term.
     * It returns an array of courses with their IDs and titles.
     *
     * @param array $params[] 
     * @return array[]   
     * @since 1.17.4
     */
    public function get($params) {
        $search = isset($params['search']) ? $params['search'] : '';
		if ( HelperFunctions::is_learndash_lms_active() ) {
			$posts = get_posts(
				array(
					'post_type'   => 'sfwd-courses',
					'numberposts' => -1,
					'orderby'     => 'created_at',
					'order'       => 'DESC',
					'post_status' => 'publish',
                    's'           => $search,
				)
			);

            $formatted_courses=[];
			if ( is_array( $posts ) ) {
				foreach ( $posts as $post ) {
					$formatted_courses[] = array(
						'value'   => $post->ID,
						'label'   => $post->post_title,
					);
				}
			}
            return [
                "success" => true,
                "courses" => $formatted_courses
            ];  
		}
    }
}