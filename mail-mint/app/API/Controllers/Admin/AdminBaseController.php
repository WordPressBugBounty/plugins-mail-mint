<?php
/**
 * REST API Base Controller
 *
 * Core base controller for managing and interacting with REST API items.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use MRM\Common\MrmCommon;
use Mint\MRM\API\Controllers\BaseController;

/**
 * This is the core class that defines abstract function for child controllers
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
abstract class AdminBaseController extends BaseController {

	/**
	 * User accessibility check for REST API
	 *
	 * @return \WP_Error|bool
	 * @since 1.0.0
	 */
    public function rest_permissions_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not authorized to perform this action.', 'mrm' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}
}
