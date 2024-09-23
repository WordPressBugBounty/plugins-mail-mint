<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/API/Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

use WP_REST_Request;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Utilites\Helper\AnimatedGif;
use MRM\Common\MRM_Common;
use MRM\Common\MrmCommon;
use WP_Query;
use WP_REST_Controller;

/**
 * Manages Analytics API callbacks
 *
 * @package /app/API/Controllers
 * @since 1.0.0
 */
class EmailBuilderController extends WP_REST_Controller {

	use Singleton;

	/**
	 * Track email opening for individual contact
	 * 
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return void
	 * @since 1.0.0
	 */
	public function create_countdown_timer( WP_REST_Request $request ) {

        $params      = MRM_Common::get_api_params_values( $request );

		 // change this to your local country time zone
        
        $time = isset( $params['dt'] ) ? $params['dt'] : "";
        $zone = isset( $params['tz'] ) ? $params['tz'] : "";

        $future_date = new \DateTime(date('r',strtotime($time)), new \DateTimeZone($zone));
        $future_date->setTimezone(new \DateTimeZone($zone));
        $time_now = time();
        $now = new \DateTime(date('r', $time_now));

        $f = "%a:%H:%I:%S";
        $t = "second";

        $frames = array();
        $delays = array();

        $cache_file = "cache/".preg_replace("/[^a-z,A-Z,0-9,_]/", "_", $time).".gif";

        $image = imagecreatefrompng(MRM_DIR_PATH . 'admin/assets/images/timer.png'); // change background 
        $delay = 100;
        $font = array(
            'size'=>65, // font size
            'angle'=>0,
            'x-offset'=>30, // offset on x asis
            'y-offset'=>80, // offset on y asis
            'file'=>MRM_DIR_PATH . 'app/Utilities/Helper/fonts/Arial.ttf', // change font (example: handsean.ttf)
            'color'=>imagecolorallocate($image, 255, 255, 255),
        );

        for($i = 0; $i <= 120; $i++){
            $interval = date_diff($future_date, $now);
            if($future_date < $now){

                $image = imagecreatefrompng(MRM_DIR_PATH . 'admin/assets/images/timer.png'); // change background 
                $text = $interval->format('00:00:00:00');
                imagettftext ($image , $font['size'] , $font['angle'] , $font['x-offset'] , $font['y-offset'] , $font['color'] , $font['file'], $text );
                ob_start();
                imagegif($image);
                $frames[]=ob_get_contents();
                $delays[]=$delay;
                $loops = 1;
                ob_end_clean();
                break;
            } else {

                $image = imagecreatefrompng(MRM_DIR_PATH . 'admin/assets/images/timer.png'); // change background 
                $text = $interval->format($f);

                if(preg_match('/^[0-9]\:/', $text)){
                    $text = '0'.$text;
                }
                imagettftext($image , $font['size'] , $font['angle'] , $font['x-offset'] , $font['y-offset'] , $font['color'] , $font['file'], $text );
                ob_start();
                imagegif($image);
                $frames[]=ob_get_contents();
                $delays[]=$delay;
                $loops = 0;
                ob_end_clean();
            }
            $now->modify('+1 '.$t);
        }

        $gif = new AnimatedGif($frames,$delays,$loops);

        $email_builder_dir = MRM_UPLOAD_DIR . 'email-builder';
        $email_builder_url = MRM_UPLOAD_URL . 'email-builder';

		if ( !file_exists( $email_builder_dir ) ) {
			wp_mkdir_p( $email_builder_dir );
		}

        $image_name = rand( time(), time() + time() ) . '.png';
        $image_dir = $email_builder_dir . '/' . $image_name;
		$image_url = $email_builder_url . '/' . $image_name;
        //if($is_cache){
            file_put_contents($image_dir, $gif->get_animation());
        //}
        $response            = array();
        $response['success'] = true;
        $response['gif_url'] = $image_url;
        
        header( 'Expires: '.gmdate('D, d M Y H:i:s T', strtotime($time)) ); //expire this image
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Cache-Control: post-check=0, pre-check=0', false );
        header( 'Pragma: no-cache' );
		return rest_ensure_response( $response );
    }

    /**
     * Get latest published post by category ID
     * 
     * @param WP_REST_Request $request WP Rest Request object.
     * 
     * @return WP_REST_Response
     * @since 1.0.0
     */
    public function get_latest_published_post_by_category( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );
        $category_id = isset( $params['category_id'] ) ? $params['category_id'] : '';
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'category__in' => array($category_id)
        );
    
        $latest_post = new WP_Query($args);
        $posts = array();
        if ($latest_post->have_posts()) {
            while ($latest_post->have_posts()) {
                $latest_post->the_post();
                $post = array(
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'permalink' => get_the_permalink(),
                    'thumbnail' => empty( get_the_post_thumbnail_url() ) ?  MRM_DIR_URL . 'admin/assets/images/mint-placeholder.png' : get_the_post_thumbnail_url()
                );
                array_push($posts, $post);
            }
            wp_reset_postdata();
        } else {
            return array();
        }
		return rest_ensure_response( $posts );
    }

    /**
	 * User accessability check for REST API
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function rest_permissions_check() {
		return true;
	}

}