<?php
/**
 * Mail Mint
 *
 * @author [WPFunnels Team]
 * @email [support@getwpfunnels.com]
 * @create date 2024-02-01 11:03:17
 * @modify date 2024-02-01 11:03:17
 * @package /app/API/Controllers
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\API\Actions\TemplateActionCreator;
use MRM\Common\MrmCommon;
use WP_REST_Request;
/**
 * [Handle template related API callbacks]
 *
 * @desc Handle template related API callbacks
 * @package /app/API/Controllers
 * @since 1.9.0
 */
class TemplateController extends AdminBaseController {

    /**
     * The TemplateActionCreator instance used to create TemplateAction objects.
     *
     * @var TemplateActionCreator
     * @access protected
     */
    protected $creator;

    /**
     * The TemplateAction instance used for performing template actions.
     *
     * @var TemplateAction
     * @access protected
     */
    protected $action;

    /**
     * TemplateController constructor.
     *
     * This constructor initializes the TemplateController and TemplateAction objects,
     * making them accessible within the class for further use.
     *
     * @access public
     */
    public function __construct() {
        $this->creator = new TemplateActionCreator();
        $this->action  = $this->creator->makeAction();
    }


    /**
     * Handles GET requests for templates.
     *
     * This method retrieves templates based on the parameters provided in the request.
     * It uses the `retrieve_and_format_templates` method of the `TemplateAction` object to retrieve and format the templates.
     * The results are then returned in a response with a status of 'success' and a message indicating that the templates have been retrieved successfully.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     *
     * @since @since 1.9.0
     */
    public function get_templates( WP_REST_Request $request ) {
        // Handle GET requests here.
		$params   = MrmCommon::get_api_params_values( $request );
        $response = $this->action->retrieve_and_format_templates( $params );

        return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => __( 'Templates has been retrieved successfully.', 'mrm' ),
				'results' => $response,
			)
		);
    }

    /**
     * Handles POST requests for deleting templates.
     *
     * This method creates a template based on the parameters provided in the request.
     * It uses the `create_template` method of the `TemplateAction` object to create the template.
     * The results are then returned in a response with a status of 'success' and a message indicating that the template has been created successfully.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     *
     * @since @since 1.9.0
     */
    public function delete_template( WP_REST_Request $request ) {
        // Handle DELETE requests here.
        $params   = MrmCommon::get_api_params_values( $request );
        $response = $this->action->delete_template( $params );

        return rest_ensure_response(
            array(
                'status'  => 'success',
                'message' => __( 'Template has been deleted successfully.', 'mrm' ),
            )
        );
    }

    public function update_template( WP_REST_Request $request ) {
        // Handle POST requests here.
        $params   = MrmCommon::get_api_params_values( $request );
        $response = $this->action->update_template( $params );

        return rest_ensure_response(
            array(
                'status'  => 'success',
                'message' => __( 'Template has been updated successfully.', 'mrm' ),
            )
        );
    }

    /**
     * Handles GET requests for retrieving WooCommerce email template.
     *
     * This method retrieves WooCommerce email template based on the parameters provided in the request.
     * It uses the `get_woocommerce_email_template` method of the `TemplateAction` object to retrieve the WooCommerce email template.
     * The results are then returned in a response with a status of 'success' and a message indicating that the WooCommerce email template has been retrieved successfully.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     *
     * @since @since 1.10.5
     */
    public function get_woocommerce_email_template( WP_REST_Request $request ) {
        // Handle GET requests here.
        $params   = MrmCommon::get_api_params_values( $request );
        $response = $this->action->get_woocommerce_email_template( $params );

        return rest_ensure_response(
            array(
                'status'  => 'success',
                'message' => __( 'WooCommerce email template has been retrieved successfully.', 'mrm' ),
                'results' => $response,
            )
        );
    }
}