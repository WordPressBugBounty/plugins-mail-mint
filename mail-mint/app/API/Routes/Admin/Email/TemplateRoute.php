<?php
/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-02-01 11:03:17
 * @modify date 2024-02-01 11:03:17
 * @package /app/API/Routes
 */

namespace Mint\MRM\Admin\API\Routes;

use WP_REST_Server;
use Mint\MRM\Admin\API\Controllers\TemplateController;

/**
 * [Handle template related API callbacks]
 *
 * @desc Handle template related API callbacks
 * @package /app/API/Routes
 * @since 1.9.0
 */
class TemplateRoute extends AdminRoute {

    /**
     * Route base.
     *
     * @var string
     * @since 1.9.0
     */
    protected $rest_base = 'email/templates';

    /**
     * TemplateController class object
     *
     * @var TemplateController
     */
    protected $controller;

    /**
     * Initialize responsible controller for this route
     */
    public function __construct() {
        $this->controller = new TemplateController();
    }

    /**
     * Register API endpoints routes for email related templates
     *
     * @return void
     * @since 1.9.0
     */
    public function register_routes() {

        /**
         * Email templates retrieve endpoints
         *
         * @return void
         * @since 1.9.0
         */
        register_rest_route( $this->namespace, $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this->controller, 'get_templates' ),
                'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
            ),
        ) );

        /**
         * Email template delete endpoint
         *
         * @return void
         * @since 1.9.0
         */
        register_rest_route( $this->namespace, $this->rest_base . '/delete', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this->controller, 'delete_template' ),
                'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
            ),
        ) );

        /**
         * Email template update endpoint
         *
         * @return void
         * @since 1.9.0
         */
        register_rest_route( $this->namespace, $this->rest_base . '/(?P<template_id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this->controller, 'update_template' ),
                'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
            ),
        ) );

        /**
         * Email template retrieve endpoint
         *
         * @return void
         * @since 1.10.5
         */
        register_rest_route( $this->namespace, $this->rest_base . '/wc', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this->controller, 'get_woocommerce_email_template' ),
                'permission_callback' => array( $this->controller, 'rest_permissions_check' ),
            ),
        ) );
    }
}