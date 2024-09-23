<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/API/Routes
 */

namespace Mint\MRM\Frontend\API\Routes;

/**
 * Manages Frontend module
 *
 * @package /app/API/Routes
 * @since 1.0.0
 */
abstract class FrontendRoute {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $namespace = 'mint-mail/v1';
}
