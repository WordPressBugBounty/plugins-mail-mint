<?php
/**
 * AI Setting Route
 *
 * Registers GET and POST endpoints at /mrm/v1/settings/ai.
 *
 * @package Mint\MRM\Admin\API\Routes
 */

namespace Mint\MRM\Admin\API\Routes;

defined( 'ABSPATH' ) || exit;

use Mint\MRM\Admin\API\Controllers\AISettingController;
use Mint\MRM\Utilities\Helper\PermissionManager;

class AISettingRoute {

    protected $namespace = 'mrm/v1';
    protected $rest_base = 'settings';

    public function register_routes(): void {
        $controller = AISettingController::get_instance();

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/ai',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $controller, 'get' ],
                    'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
                ],
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $controller, 'create_or_update' ],
                    'permission_callback' => PermissionManager::current_user_can( 'mint_manage_settings' ),
                ],
            ]
        );
    }
}
