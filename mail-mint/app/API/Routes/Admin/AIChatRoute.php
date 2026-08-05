<?php
/**
 * AI Chat Route
 *
 * Registers the Mint AI copilot conversation endpoints under /mrm/v1/ai.
 *
 * @package Mint\MRM\Admin\API\Routes
 */

namespace Mint\MRM\Admin\API\Routes;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Admin\API\Controllers\AIChatController;
use Mint\MRM\Admin\API\Controllers\AIGenerateController;
use Mint\MRM\Utilities\Helper\PermissionManager;

class AIChatRoute {

    protected $namespace = 'mrm/v1';
    protected $rest_base = 'ai';

    public function register_routes(): void {
        $controller = AIChatController::get_instance();
        $permission = PermissionManager::current_user_can( 'mint_view_dashboard' );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/conversations',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $controller, 'create_conversation' ],
                    'permission_callback' => $permission,
                ],
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $controller, 'list_conversations' ],
                    'permission_callback' => $permission,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/conversations/(?P<conversation_id>[\d]+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $controller, 'get_conversation' ],
                    'permission_callback' => $permission,
                ],
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $controller, 'rename_conversation' ],
                    'permission_callback' => $permission,
                ],
                [
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => [ $controller, 'delete_conversation' ],
                    'permission_callback' => $permission,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/conversations/(?P<conversation_id>[\d]+)/messages',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $controller, 'append_message' ],
                    'permission_callback' => $permission,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/conversations/(?P<conversation_id>[\d]+)/step',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $controller, 'step' ],
                    'permission_callback' => $permission,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/campaign-preview/(?P<campaign_id>[\d]+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $controller, 'campaign_preview' ],
                    'permission_callback' => PermissionManager::current_user_can( 'mint_read_campaigns' ),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/form-preview/(?P<form_id>[\d]+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $controller, 'form_preview' ],
                    'permission_callback' => PermissionManager::current_user_can( 'mint_read_forms' ),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/automation-preview/(?P<automation_id>[\d]+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $controller, 'automation_preview' ],
                    'permission_callback' => PermissionManager::current_user_can( 'mint_read_automations' ),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/conversations/(?P<conversation_id>[\d]+)/confirm',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $controller, 'confirm' ],
                    'permission_callback' => $permission,
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/generate',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ AIGenerateController::get_instance(), 'generate' ],
                    'permission_callback' => $permission,
                ],
            ]
        );
    }
}
