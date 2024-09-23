<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/API/Routes/Admin
 */

namespace Mint\MRM\Admin\API\Routes;

/**
 * Manages Backend API modules
 *
 * @since 1.0.0
 */
abstract class AdminRoute {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $namespace = 'mrm/v1';
}