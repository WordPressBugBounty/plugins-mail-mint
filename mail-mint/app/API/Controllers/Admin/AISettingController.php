<?php
/**
 * REST API AI Setting Controller
 *
 * GET  /mrm/v1/settings/ai           — connection state per provider (keys never returned).
 * POST /mrm/v1/settings/ai           — connect / switch / disconnect / test / save-instructions / update-model.
 *
 * POST shapes:
 *   { provider, api_key, model? }                      connect: validate key live, store encrypted, set active
 *   { provider: 'wordpress_ai' }                       connect WP AI (no key needed, validates availability)
 *   { provider, set_active: true }                     switch active (provider must already be connected)
 *   { provider, disconnect: true }                     remove stored key
 *   { provider, test_connection: true }                re-validate stored connection
 *   { provider, update_model: '<model-id>' }           change model without re-entering key
 *   { save_instructions: true, instructions: string }  persist custom system prompt
 *
 * @package Mint\MRM\Admin\API\Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

defined( 'ABSPATH' ) || exit;

use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Internal\AI\AIInit;
use Mint\MRM\Internal\AI\Settings\AISettings;
use MRM\Common\MrmCommon;
use WP_REST_Request;

class AISettingController extends SettingBaseController {

    use Singleton;

    /**
     * GET /mrm/v1/settings/ai
     *
     * @param WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get( WP_REST_Request $request ) {
        return $this->get_success_response_data( AISettings::publicState() );
    }

    /**
     * POST /mrm/v1/settings/ai
     *
     * @param WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function create_or_update( WP_REST_Request $request ) {
        $params = MrmCommon::get_api_params_values( $request );

        // ---- Master enable / disable -------------------------------------
        if ( isset( $params['set_enabled'] ) ) {
            AISettings::setEnabled( 'yes' === $params['set_enabled'] || true === $params['set_enabled'] );
            return $this->get_success_response_data(
                AISettings::publicState() + [
                    'message' => AISettings::isEnabled()
                        ? __( 'AI Assistant enabled.', 'mrm' )
                        : __( 'AI Assistant disabled.', 'mrm' ),
                ]
            );
        }

        // ---- Custom instructions ----------------------------------------
        if ( ! empty( $params['save_instructions'] ) ) {
            $instructions = (string) ( $params['instructions'] ?? '' );
            AISettings::saveCustomInstructions( $instructions );
            return $this->get_success_response_data(
                AISettings::publicState() + [ 'message' => __( 'Custom instructions saved.', 'mrm' ) ]
            );
        }

        $provider = sanitize_key( $params['provider'] ?? '' );
        if ( ! in_array( $provider, AISettings::PROVIDERS, true ) ) {
            return $this->get_error_response(
                /* translators: %s: comma-separated valid provider slugs */
                sprintf( __( 'provider must be one of: %s.', 'mrm' ), implode( ', ', AISettings::PROVIDERS ) )
            );
        }

        // ---- Disconnect --------------------------------------------------
        if ( ! empty( $params['disconnect'] ) ) {
            AISettings::disconnect( $provider );
            return $this->get_success_response_data(
                AISettings::publicState() + [ 'message' => __( 'Provider disconnected.', 'mrm' ) ]
            );
        }

        // ---- Switch active -----------------------------------------------
        if ( ! empty( $params['set_active'] ) ) {
            if ( ! AISettings::setActiveProvider( $provider ) ) {
                return $this->get_error_response( __( 'Connect this provider before making it active.', 'mrm' ) );
            }
            return $this->get_success_response_data( AISettings::publicState() );
        }

        // ---- Update model (no key re-entry) ------------------------------
        if ( isset( $params['update_model'] ) ) {
            $model = sanitize_text_field( $params['update_model'] );
            if ( ! AISettings::updateModel( $provider, $model ) ) {
                return $this->get_error_response( __( 'Connect this provider before changing its model.', 'mrm' ) );
            }
            return $this->get_success_response_data(
                AISettings::publicState() + [ 'message' => __( 'Model updated.', 'mrm' ) ]
            );
        }

        // ---- Test stored connection ---------------------------------------
        if ( ! empty( $params['test_connection'] ) ) {
            if ( ! AISettings::isConnected( $provider ) ) {
                return $this->get_error_response( __( 'Provider is not connected.', 'mrm' ) );
            }
            $adapter = AIInit::makeProvider( $provider, AISettings::getApiKey( $provider ), AISettings::getModel( $provider ) );
            if ( ! $adapter ) {
                return $this->get_error_response( __( 'Unknown provider.', 'mrm' ) );
            }
            $valid = $adapter->validateKey();
            if ( is_wp_error( $valid ) ) {
                return $this->get_error_response(
                    sprintf(
                        /* translators: %s: provider error detail */
                        __( 'Connection test failed: %s', 'mrm' ),
                        $valid->get_error_message()
                    )
                );
            }
            return $this->get_success_response_data(
                AISettings::publicState() + [ 'message' => __( 'Connection is working.', 'mrm' ) ]
            );
        }

        // ---- Connect -----------------------------------------------------
        $api_key = trim( (string) ( $params['api_key'] ?? '' ) );
        $model   = sanitize_text_field( $params['model'] ?? '' );

        // wordpress_ai doesn't need an API key.
        if ( ! in_array( $provider, AISettings::REQUIRES_KEY, true ) ) {
            $adapter = AIInit::makeProvider( $provider, '', $model );
            $valid   = $adapter ? $adapter->validateKey() : new \WP_Error( 'invalid_provider', 'Unknown provider.' );
            if ( is_wp_error( $valid ) ) {
                return $this->get_error_response(
                    sprintf(
                        /* translators: %s: provider error detail */
                        __( 'Could not connect: %s', 'mrm' ),
                        $valid->get_error_message()
                    )
                );
            }
            $stored = AISettings::connect( $provider, '', $model );
            if ( is_wp_error( $stored ) ) {
                return $this->get_error_response( $stored->get_error_message() );
            }
            do_action( 'mailmint_ai_provider_connected', $provider );
            return $this->get_success_response_data(
                AISettings::publicState() + [ 'message' => __( 'WordPress AI connected successfully.', 'mrm' ) ]
            );
        }

        if ( '' === $api_key ) {
            return $this->get_error_response( __( 'api_key is required to connect this provider.', 'mrm' ) );
        }

        // Validate the key with a live ping BEFORE storing anything.
        $adapter = AIInit::makeProvider( $provider, $api_key, $model );
        $valid   = $adapter ? $adapter->validateKey() : new \WP_Error( 'invalid_provider', 'Unknown provider.' );
        if ( is_wp_error( $valid ) ) {
            return $this->get_error_response(
                sprintf(
                    /* translators: %s: provider error detail */
                    __( 'Could not connect: %s', 'mrm' ),
                    $valid->get_error_message()
                )
            );
        }

        $stored = AISettings::connect( $provider, $api_key, $model );
        if ( is_wp_error( $stored ) ) {
            return $this->get_error_response( $stored->get_error_message() );
        }

        do_action( 'mailmint_ai_provider_connected', $provider );

        return $this->get_success_response_data(
            AISettings::publicState() + [ 'message' => __( 'AI connected successfully.', 'mrm' ) ]
        );
    }
}
