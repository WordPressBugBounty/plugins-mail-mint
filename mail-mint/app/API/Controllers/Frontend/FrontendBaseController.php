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

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\API\Controllers\BaseController;

/**
 * This is the core class that defines abstract function for child controllers
 */
abstract class FrontendBaseController extends BaseController {

	/**
	 * Default permission_callback
	 *
	 * @return bool
	 */
	public function rest_permissions_check() {
		return true;
	}
}
