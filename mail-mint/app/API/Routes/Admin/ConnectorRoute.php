<?php

/**
 * Mail Mint Pro
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2025-01-07 15:33:17
 * @modify date 2025-01-07 15:33:17
 * @package MailMintPro\Mint\Admin\API\Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use  Mint\MRM\Admin\API\Controllers\ConnectorController;
use WP_REST_Server;

/**
 * Class ConnectorRoute
 *
 * Summary: This class is responsible for handling API endpoints for the connector.
 * Description: Extends the AdminRoute class and initializes the ConnectorController class.
 *
 * @since 1.17.4
 */
class ConnectorRoute extends AdminRoute{

    /**
     * Route base.
     *
     * @var string The base route for the connector.
     * @since 1.17.4
     */
    protected $rest_base = 'connector';

    /**
     * ConnectorController class object.
     *
     * @var object An instance of the ConnectorController class.
     * @since 1.17.4
     */
    protected $controller;

    /**
     * Initialize responsible controller for this route.
     *
     * @since 1.17.4
     */
    public function __construct(){
        $this->controller = new ConnectorController();
    }

    /**
     * Register API endpoints routes to handle requests.
     *
     * @return void
     * @since 1.17.4
     */
    public function register_routes(){
        register_rest_route(
            $this->namespace,
            $this->rest_base . '/(?P<integration>[a-zA-Z0-9-_]+)/(?P<action>[a-zA-Z0-9-_]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this->controller, 'handle_request' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );
    }
}
