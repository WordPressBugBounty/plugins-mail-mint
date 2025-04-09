<?php
/**
 * REST API Automation Controller
 *
 * Handles requests to the Automation endpoint.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use MintMail\App\Internal\Automation\AutomationModel;
use MintMail\App\Internal\Automation\AutomationStepModel;
use MintMail\App\Internal\Automation\Recipe\AutomationRecipe;
use WP_REST_Request;
use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;
use WP_REST_Response;

/**
 * This is the main class that controls the automation feature. Its responsibilities are:
 *
 * - Create or update automation
 * - Delete single or multiple Automation
 * - Retrieve single or multiple automations
 *
 * @package Mint\MRM\Admin\API\Controllers
 */
class AutomationController extends AdminBaseController {

	/**
	 * Automation object arguments
	 *
	 * @var object
	 * @since 1.0.0
	 */
	public $args = array();


	/**
	 * Automation array from API response
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $automation_data;


	/**
	 * Get and send response to create or update a automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return array|\WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function create_or_update( WP_REST_Request $request ) {
		$params           = MrmCommon::get_api_params_values( $request );
		$get_at_most_date = isset( $params['atMostDate'] ) ? $params['atMostDate'] : '';
		$stat             = !empty( $params['showAnalyticsStat'] ) ? $params['showAnalyticsStat'] : false;
		unset( $params['atMostDate'] );
		unset( $params['showAnalyticsStat'] );
		$automation_id = AutomationModel::get_instance()->create_or_update( $params );
		if ( $automation_id ) {
			HelperFunctions::update_automation_meta( $automation_id, 'source', 'mint' );
			HelperFunctions::update_automation_meta( $automation_id, 'enable_stats', $stat );

			HelperFunctions::update_automation_meta( $automation_id, '_at_most_date', maybe_serialize( $get_at_most_date ) );

			$data = array(
				'automation_id' => $automation_id,
			);
			return $this->get_success_response( __( 'Automation has been saved successfully', 'mrm' ), 201, $data );
		}
		return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
	}

	/**
	 * Update automation status.
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return array|\WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function status_update( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		$automation_id = isset( $params['id'] ) ? $params[ 'id' ] : 0;

		$status = isset( $params['status'] ) ? strtolower( $params['status'] ) : '';

		if ( $automation_id ) {
			HelperFunctions::update_status( $automation_id, $status );
			$data = array(
				'automation_id' => $automation_id,
			);
			return $this->get_success_response( __( 'Automation status been saved successfully', 'mrm' ), 201, $data );
		}
		return $this->get_error_response( __( 'Failed to save', 'mrm' ), 400 );
	}


	/**
	 * Request for deleting a single automation by ID
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_single( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['id'] ) ) {
			$success = AutomationModel::destroy( $params['id'] );
			if ( $success ) {
				return $this->get_success_response( __( 'Automation has been deleted successfully', 'mrm' ), 200 );
			}
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Request for deleting multiple automations by ID
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 *
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_all( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		if ( isset( $params['automation_ids'] ) ) {
			$success = AutomationModel::destroy_all( $params['automation_ids'] );
			if ( $success ) {
				return $this->get_success_response( __( 'Automations has been deleted successfully', 'mrm' ), 200 );
			}
		}

		return $this->get_error_response( __( 'Failed to delete', 'mrm' ), 400 );
	}


	/**
	 * Get all automations
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_all( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );

		$page     = isset( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page = isset( $params['per-page'] ) ? absint( $params['per-page'] ) : 25;
		$offset   = ( $page - 1 ) * $per_page;

		$status = isset( $params['status'] ) ? strtolower( $params['status'] ) : 'all';

		$order_by   = isset( $params['order-by'] ) ? strtolower( $params['order-by'] ) : 'created_at';
		$order_type = isset( $params['order-type'] ) ? strtolower( $params['order-type'] ) : 'desc';

		// Automation Search keyword.
		$search = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';

		$automations = AutomationModel::get_all( $order_by, $order_type, $offset, $per_page, $search, $status );
		// Prepare human_time_diff for every Automation.
		if ( isset( $automations['data'] ) ) {
			$automations['data'] = array_map(
				function( $automation ) {
					if ( is_array( $automation ) && !empty( $automation ) ) {
						$created_at    = isset( $automation['created_at'] ) ? $automation['created_at'] : '';
						$automation_id = isset( $automation['id'] ) ? $automation['id'] : '';

						$automation['created_ago'] = human_time_diff( strtotime( $created_at ), current_time( 'timestamp' ) ); //phpcs:disable
						$automation['enterance']   = HelperFunctions::count_total_enterance( $automation_id );
						$automation['completed']   = HelperFunctions::count_completed_automation( $automation_id );
						$automation['processing']  = $automation['enterance'] - $automation['completed'];
					}
					return $automation;
				},
				$automations['data']
			);
		}

		if ( isset( $automations ) ) {
			return $this->get_success_response( __( 'Query Successfully', 'mrm' ), 200, $automations );
		}

		return $this->get_error_response( __( 'Failed to get data', 'mrm' ), 400 );
	}


	/**
	 * Function use to get single automation
	 *
	 * @param WP_REST_Request $request Request object used to generate the response.
	 * @return WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_single( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		if( !empty( $params['id'] ) ){
			$automations        = AutomationModel::get_single( $params['id'] );
            $automation_meta    = HelperFunctions::get_automation_meta( $params['id'], '_at_most_date' );
            $get_stat_meta    = HelperFunctions::get_automation_meta( $params['id'], 'enable_stats' );
			// Prepare human_time_diff for every Automation.
			if ( isset( $automations['data'] ) ) {
                if( isset( $automation_meta[0] ) && isset( $automation_meta[0]['meta_key'] ) && '_at_most_date' === $automation_meta[0]['meta_key'] && !empty($automation_meta[0]['meta_value'] )){
                    $value = maybe_unserialize( $automation_meta[0]['meta_value'] );
                    $automations['data'][0]['atMostDate'] = $value;
                } if( isset( $get_stat_meta[0]['meta_key'] ) && 'enable_stats' === $get_stat_meta[0]['meta_key'] && !empty($get_stat_meta[0]['meta_value'] )){
                    $value =  $get_stat_meta[0]['meta_value'];
                    $automations['data'][0]['showAnalyticsStat'] = $value;
                }
				$automations['data'] = array_map(
					function( $automation ) {
						if ( isset( $automation['created_at'] ) ) {
							$automation['created_ago'] = human_time_diff( strtotime( $automation['created_at'] ), current_time( 'timestamp' ) ); //phpcs:disable
						}
						return $automation;
					},
					$automations['data']
				);
			}
			if ( isset( $automations ) ) {
				return $this->get_success_response( __( 'Query Successful', 'mrm' ), 200, $automations );
			}
		}
		return $this->get_error_response( 'Failed to Get Data', 400 );
	}

    /**
     * Export Automation
     *
     * @param WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function export_automation( WP_REST_Request $request )
    {
        $params = MrmCommon::get_api_params_values( $request );
        if( !empty( $params['id'] ) ){
            $automations = AutomationModel::get_single( $params['id'] );
            if ( isset( $automations ) ) {
                $response = array(
                    'data' => !empty( $automations['data'][0]) ? $automations['data'][0] : [],
                    'status' => 'success',
                    'message' => 'Export Successfully'
                );
                return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $response );
            }
        }
        return $this->get_error_response( 'Failed to Get Data', 400 );
    }

    /**
     * Duplicate an automation with its steps.
     *
     * @param WP_REST_Request $request The request object.
     * 
     * @return WP_REST_Response Returns a success or error response object.
     * @since 1.2.6
     */
    public function duplicate_automation( WP_REST_Request $request )
    {
        $params = MrmCommon::get_api_params_values( $request );

        if (empty($params['id'])) {
            return $this->get_error_response('Failed to Get Data', 400);
        }

		// Get the original automation.
		$original_automation = AutomationModel::get_single( $params['id'] );
		if ( empty( $original_automation ) ) {
			return false;
		}

        $automations   = HelperFunctions::clone_automation( $original_automation, $params['id'] );
        $automation_id = isset($automations['id']) ? $automations['id'] : '';

        if ( !$automation_id ) {
            return $this->get_error_response(__('Failed to duplicate', 'mrm'), 400);
        }

        $step_data = [];

        foreach ($automations['steps'] as $key => $step) {
            $step_data = HelperFunctions::generate_individual_step_data($automations['steps'], $step_data, $key, $step, $automation_id);
        }

        HelperFunctions::create_duplicate_automation_steps($step_data, $automation_id);

        $data = [
            'automation_id' => $automation_id,
        ];

        return $this->get_success_response(__('Automation has been duplicated successfully', 'mrm'), 200, $data);
    }

    /**
     * Get All recipe.
     *
     * @param WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function get_all_recipe( WP_REST_Request $request )
    {
        $recipe = AutomationRecipe::get_all_recipe();
        if ( !empty( $recipe ) ) {
            $recipe_data = [];
            foreach($recipe as $value){
                if( !empty($value) ){
                    $recipe_data[] = $value;
                }
            }
            $response = array(
                'data' => $recipe_data,
                'status' => 'success',
                'message' => 'Recipe get Successfully'
            );
            return $this->get_success_response( __( 'Query Successfull', 'mrm' ), 200, $response );
        }
        return $this->get_error_response( 'Failed to Get Data', 400 );
    }

    /**
     * Get Single Recipe.
     *
     * @param WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function get_single_recipe( WP_REST_Request $request )
    {
        $params = MrmCommon::get_api_params_values( $request );
        if( !empty( $params['id'] ) ){
            $single_recipe = AutomationRecipe::get_single_recipe( $params['id'] );
            $single_recipe['automation_data']  = !empty($single_recipe['automation_data']) ? $single_recipe['automation_data'] : [];
            $single_recipe['automation_data'] = json_decode( $single_recipe['automation_data'] ,true);
            if ( !empty( $single_recipe ) ) {
                $automations = !empty($single_recipe['automation_data']) ? $single_recipe['automation_data'] : []  ;
                $automations['name'] = !empty($single_recipe['automationTitle']) ? $single_recipe['automationTitle'] : 'Untitled';
                $automations['status'] = 'draft';
                unset($automations['id']);
                $automation_id = AutomationModel::get_instance()->create( $automations );
                if ( $automation_id ) {
                    if ( !empty( $automations['steps'] ) && is_array( $automations['steps'] ) ) {
                        $steps            = $automations['steps'];
                        $step_data = array();
                        foreach ( $steps as $key =>$step ) {
                            $random             = substr(md5(mt_rand()), 0, 5);
                            $next_step_random   = substr(md5(mt_rand()), 0, 5);
                            if ( isset( $step['step_id'] ) ) {
                                $step_data[$key] = array(
                                    'automation_id' => $automation_id,
                                    'key'           => $step['key'],
                                    'type'          => $step['type'],
                                    'settings'      => isset( $step['settings'] ) ? $step['settings'] : array(),
                                );
                                if(isset($steps[$key+1])){
                                    $step_data[$key]['next_step_id'] = $next_step_random;
                                }else{
                                    $step_data[$key]['next_step_id'] = [];
                                }
                                if($key == 0){
                                    $step_data[$key]['step_id'] = $random;
                                }else{
                                    $step_data[$key]['step_id'] = $step_data[$key-1]['next_step_id'];
                                }

                            }
                        }
                        foreach ($step_data as $key => $dup_step ){
                            if ( isset( $dup_step['step_id'] ) ) {
                                $duplicate_step = array(
                                    'automation_id' => $automation_id,
                                    'step_id'       => $dup_step['step_id'],
                                    'key'           => $dup_step['key'],
                                    'type'          => $dup_step['type'],
                                    'settings'      => isset( $dup_step['settings'] ) ? $dup_step['settings'] : array(),
                                    'next_step_id'  => isset( $dup_step['next_step_id'] ) ? $dup_step['next_step_id'] : '',
                                );
                                AutomationStepModel::get_instance()->create_or_update( $duplicate_step );
                            }
                        }

                    }
                    HelperFunctions::update_automation_meta( $automation_id, 'source', 'mint' );
                    $data = array(
                        'automation_id' => $automation_id,
                        'steps'         => HelperFunctions::get_all_step_by_automation_id( $automation_id ),
                    );
                    return $this->get_success_response( __( 'Automation has been duplicate successfully', 'mrm' ), 200, $data );
                }
            }
        }
        return $this->get_error_response( 'Failed to Get Data', 400 );
    }

    /**
     * Import automation data from a JSON string.
     *
     * This function takes a JSON string representing an automation and imports it into the system.
     *
     * @param WP_REST_Request $request
     * @return array|WP_REST_Response|\WP_Error|\WP_HTTP_Response|\WP_REST_Response
     */
    public function import_automation( WP_REST_Request $request )
    {
        // Get the original automation.
        $params = MrmCommon::prepare_request_params( $request );
        if( empty($params['file']) || empty($params['file']['tmp_name']) || empty($params['file']['type'] || 'application/json' !== $params['file']['type']) ){
            return new WP_REST_Response(
                array(
                    'status'  => 'failed',
                    'message' => __( 'Failed to import automation.File does not exist.', 'mailmint-pro' ),
                )
            );
        }
        $file_name = $params['file']['name'];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        if ( 'json' !== strtolower($file_extension) ) {
            return new WP_REST_Response(
                array(
                    'status'  => 'failed',
                    'message' => __( 'Failed to import automation.File format is not valid.', 'mailmint-pro' ),
                )
            );
        }
        $temp_file_name = $params['file']['tmp_name'];
        // Read the JSON content from the temporary file
        $json_data = file_get_contents($temp_file_name);

        $original_automation = json_decode($json_data, true);
        if ( empty( $original_automation ) ) {
            return new WP_REST_Response(
                array(
                    'status'  => 'failed',
                    'message' => __( 'Failed to import automation. JSON is not valid.', 'mailmint-pro' ),
                )
            );
        }

        $automations = $original_automation;
        if(!isset($original_automation['name']) || !isset($original_automation['trigger_name']) || empty($original_automation['steps'])){
            return new WP_REST_Response(
                array(
                    'status'  => 'failed',
                    'message' => __( 'Failed to import automation. JSON is not valid.', 'mailmint-pro' ),
                )
            );
        }
        $automations['name'] = !empty($original_automation['name']) ? $original_automation['name'] : 'Untitled';
        $automations['status'] = 'draft';
        unset($automations['id']);
        $automation_id = AutomationModel::get_instance()->create( $automations );

        $step_data = [];
        foreach ($automations['steps'] as $key => $step) {
            $step_data = HelperFunctions::generate_individual_step_data($automations['steps'], $step_data, $key, $step, $automation_id);
        }

        HelperFunctions::create_duplicate_automation_steps($step_data, $automation_id);
        HelperFunctions::update_automation_meta( $automation_id, 'source', 'mint' );
        $data = [
            'automation_id' => $automation_id,
        ];
        return new WP_REST_Response(
            array(
                'status'  => 'success',
                'data'    => $data,
                'message' => __( 'Automation has been imported successfully', 'mailmint-pro' ),
            )
        );
    }

    public function search_automation( WP_REST_Request $request ){
        $params      = MrmCommon::get_api_params_values( $request );
        $response    = array();
        $automations = AutomationModel::search_automation( $params );

        $response['success']     = true;
		$response['automations'] = $automations;
		return rest_ensure_response( $response );
    }
}
