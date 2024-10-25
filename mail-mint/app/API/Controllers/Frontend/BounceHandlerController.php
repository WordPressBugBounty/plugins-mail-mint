<?php

/**
 * Class BounceHandlerController
 * 
 * This class is responsible for handling email bounces from various providers.
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-10-11 11:03:17
 * @modify date 2024-10-11 11:03:17
 * @package Mint\MRM\Frontend\API\Controllers
 */

namespace Mint\MRM\Frontend\API\Controllers;

use Mint\MRM\Utilities\Integrations\EmailBounce;
use MRM\Common\MrmCommon;

/**
 * Class BounceHandlerController
 *
 * Handles email bounce events from various providers such as Mailgun and SendGrid.
 * This controller processes the bounce data and records the event.
 *
 * @package Mint\MRM\Frontend\API\Controllers
 * @since 1.15.0
 */
class BounceHandlerController extends FrontendBaseController{

    /**
     * List of valid email service providers.
     *
     * @var array
     * @since 1.15.0
     */
    private $valid_services = array('mailgun', 'sendgrid', 'ses', 'postmark', 'brevo', 'sparkpost', 'pepipost', 'mailjet');

    /**
     * Handles the bounce event from the email provider.
     *
     * @param \WP_REST_Request $request The request object containing the bounce data.
     * @return array The response indicating the result of the bounce handling.
     * @since 1.15.0
     */
    public function handle_bounce( $request ){
        $params = MrmCommon::get_api_params_values($request);
        $params = filter_var_array($params);

        // Get the provider and token from the request.
        $provider   = isset($params['provider']) ? $params['provider'] : '';
        $token      = isset($params['token']) ? $params['token'] : '';

        if ( !in_array( $provider, $this->valid_services ) ) {
            // This is a custom bounce handler.
            return apply_filters('mint_handle_bounce_' . $provider, [
                'success' => 0,
                'message' => '',
                'service' => $provider,
                'result'  => '',
                'time'    => time()
            ], $request, $token);
        }

        if ( $token != $this->get_security_code()) {
            return $this->get_error();
        }

        $result = (new EmailBounce())->handle($provider, $request);

        return [
            'success' => 1,
            'message' => 'recorded',
            'service' => $provider,
            'result'  => $result,
            'time'    => time()
        ];
    }

    /**
     * Retrieves the security code for validating the request.
     *
     * @return string The security code.
     * @since 1.15.0
     */
    private function get_security_code(){
        $code = get_option('mint_bounce_key');

        if ( !$code ) {
            $code = 'mint_' . substr(md5(wp_generate_uuid4()), 0, 14);
            update_option('mint_bounce_key', $code);
        }

        return $code;
    }
    /**
     * Returns an error response for invalid data or security code.
     *
     * @return array The error response.
     * @since 1.15.0
     */
    private function get_error(){
        return array(
            'status'  => false,
            'message' => __('Invalid Data or Security Code.', 'mrm')
        );
    }
}