<?php
/**
 * Mail Mint 
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2025-01-07 15:33:17
 * @modify date 2025-01-07 15:33:17
 * @package MailMintPro\Mint\Admin\API\Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

use MRM\Common\MrmCommon;
use WP_REST_Controller;
use WP_REST_Request;

/**
 * Manages the connector API endpoints callback functions.
 *
 * Summary: This class is responsible for handling API endpoints callback functions for the connector.
 * Description: Extends the WP_REST_Controller class and provides the callback functions for the connector API endpoints.
 *
 * @since 1.17.4
 */
class ConnectorController extends WP_REST_Controller {

	/**
	 * Handles API requests for integrations and actions.
	 *
	 * This function processes a REST API request, determines the correct handler class
	 * based on the `integration` parameter, and calls the specified action method.
	 *
	 * @param WP_REST_Request $request The API request containing parameters.
	 *
	 * @return WP_REST_Response|WP_Error The response from the handler or an error if the handler or method doesn't exist.
	 * @since 1.17.4
	 */
	public function handle_request( WP_REST_Request $request ) {
		$params      = MrmCommon::get_api_params_values( $request );
		$integration = isset( $params['integration'] ) ? $params['integration'] : '';
		$action      = isset( $params['action'] ) ? $params['action'] : 'get';

		$base_namespace = 'Mint\\MRM\\Admin\\API\\Controllers\\Connector\\';
		$handler_class  = $base_namespace . str_replace(' ', '', ucwords(str_replace('-', ' ', $integration))) . 'ConnectorHandler';

		if (class_exists($handler_class)) {
			$handler = new $handler_class();
			if (method_exists($handler, $action)) {
				$results = $handler->$action($params);
				return rest_ensure_response($results);
			}
		}
	}
}
