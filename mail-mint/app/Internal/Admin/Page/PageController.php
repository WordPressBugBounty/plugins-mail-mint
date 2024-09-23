<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin
 */

namespace Mint\MRM\Internal\Admin\Page;

use Mint\Mrm\Internal\Traits\Singleton;

/**
 * [Manage pages of the plugin]
 *
 * @desc Manage pages of the plugin
 * @package /app/Internal/Admin
 * @since 1.0.0
 */
class PageController {

	use Singleton;

	/**
	 * [Initialize class functionalities]
	 *
	 * @desc Initialize class functionalities
	 * @since 1.0.0
	 */
	public function __construct() {
		// Init home screen.
		HomeScreen::get_instance();

		add_filter( 'display_post_states', array( $this, 'add_display_post_states' ), 10, 2 );

	}

	/**
	 * Set State in Page
	 *
	 * @param array  $post_states Get post State data
	 * @param object $post Get WP POst obejct
	 *
	 * @return mixed
	 */
	public function add_display_post_states( $post_states, $post ) {
		$state = __( 'Mint Mail Page', 'mrm' );
		if ( 'optin_confirmation' === get_post_field( 'post_name', $post->ID ) ) {
			$post_states[ 'mint_mrm_optin' ] = $state;
		}
		if ( 'preference_page' === get_post_field( 'post_name', $post->ID ) ) {
			$post_states[ 'mint_mrm_preference' ] = $state;
		}
		if ( 'unsubscribe_confirmation' === get_post_field( 'post_name', $post->ID ) ) {
			$post_states[ 'mint_mrm_unsubscribe_confirmation' ] = $state;
		}

		return $post_states;
	}
}
