<?php
/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2025-01-07 15:33:17
 * @modify date 2025-01-07 15:33:17
 * @package MintMailPro\App\Admin\API\Actions\Connector;
 */
namespace Mint\MRM\Admin\API\Controllers;

/**
 * Abstract base class for connector handlers.
 *
 * This class provides the structure for connector handler classes that process
 * integration-specific API requests. All derived classes must implement the `get` method.
 *
 * @since 1.17.4
 */
abstract class BaseConnectorHandler {

    /**
     * Handles the "get" action for a connector.
     *
     * This method must be implemented by any class extending `BaseConnectorHandler`.
     * It defines how to process "get" requests for a specific integration.
     *
     * @param mixed $request The request data or parameters.
     *
     * @return mixed The response for the "get" action.
     * @since 1.17.4
     */
    abstract public function get($request);

  
}
