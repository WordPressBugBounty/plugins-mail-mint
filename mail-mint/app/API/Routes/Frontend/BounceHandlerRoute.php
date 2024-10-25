<?php

/**
 * Class BounceHandlerRoute
 * 
 * This class is responsible for registering REST API routes for handling email bounces from various providers.
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-10-11 11:03:17
 * @modify date 2024-10-11 11:03:17
 * @package Mint\MRM\Frontend\API\Routes
 */
namespace Mint\MRM\Frontend\API\Routes;

use Mint\MRM\Frontend\API\Controllers\BounceHandlerController;
use WP_REST_Server;

/**
 * Class BounceHandlerRoute
 *
 * Registers REST API routes for handling email bounces from various providers.
 *
 * @package Mint\MRM\Frontend\API\Routes
 */
class BounceHandlerRoute extends FrontendRoute{

    /**
     * The base path for the REST API endpoint.
     *
     * @var string
     * @since 1.15.0
     */
    protected $rest_base = 'bounce_handler';

    /**
     * The controller instance to handle the requests.
     *
     * @var BounceHandlerController
     * @since 1.15.0
     */
    protected $controller;

    /**
     * BounceHandlerRoute constructor.
     *
     * Initializes the controller.
     * @since 1.15.0
     */
    public function __construct(){
        $this->controller = new BounceHandlerController();
    }

    /**
     * Registers the REST API routes.
     *
     * @return void
     * @since 1.15.0
     */
    public function register_routes(){
        register_rest_route(
            $this->namespace,
            $this->rest_base . '/(?P<provider>[a-zA-Z0-9_-]+)/handle/(?P<token>[a-zA-Z0-9_]+)',
            array(
                'methods'             => WP_REST_Server::ALLMETHODS,
                'callback'            => array( $this->controller, 'handle_bounce' ),
                'permission_callback' => array( $this->controller, 'rest_permissions_check')
            )
        );
    }
}