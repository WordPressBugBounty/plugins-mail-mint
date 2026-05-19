<?php
/**
 * REST API Data Cleanup Controller
 *
 * Handles all /mrm/v1/data-cleanup/* endpoints for the Data Cleanup feature.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace Mint\MRM\Admin\API\Controllers;

use Mint\MRM\Internal\DataCleanup\DataCleanupScheduler;
use Mint\MRM\Internal\DataCleanup\JobStateManager;
use MRM\Common\MrmCommon;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DataCleanupController
 *
 * Provides REST endpoints for previewing, starting, monitoring, and cancelling
 * data cleanup jobs. All endpoints require manage_options capability.
 *
 * @package Mint\MRM\Admin\API\Controllers
 * @since   1.0.0
 */
class DataCleanupController extends SettingBaseController {

	/**
	 * JobStateManager instance.
	 *
	 * @since 1.0.0
	 * @var JobStateManager
	 */
	private $state_manager;

	/**
	 * DataCleanupScheduler instance.
	 *
	 * @since 1.0.0
	 * @var DataCleanupScheduler
	 */
	private $scheduler;

	/**
	 * Constructor. Initialises dependencies.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->state_manager = new JobStateManager();
		$this->scheduler     = new DataCleanupScheduler();
	}

	/**
	 * Required by SettingBaseController — not used in this controller.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_or_update( WP_REST_Request $request ) {
		return $this->get_error_response( __( 'Method not allowed.', 'mrm' ) );
	}

	/**
	 * Required by SettingBaseController — not used in this controller.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get( WP_REST_Request $request ) {
		return $this->get_error_response( __( 'Method not allowed.', 'mrm' ) );
	}

	/**
	 * Returns the category-to-table configuration map.
	 *
	 * Each entry defines:
	 *  - label:        Human-readable category name.
	 *  - table:        Primary table name without prefix.
	 *  - alias:        Short alias used in single-table queries.
	 *  - filter:       WHERE clause fragment for single-table queries (alias-prefixed, %s = ref_date).
	 *  - date_col:     Column used for MIN/MAX date range queries (unqualified).
	 *
	 * @since 1.0.0
	 * @return array Category configuration keyed by category slug.
	 */
	private function get_category_config(): array {
		return array(
			'broadcast_emails'  => array(
				'label'    => 'Email Send Logs',
				'table'    => 'mint_broadcast_emails',
				'alias'    => 'be',
				'filter'   => "be.status = 'sent' AND be.scheduled_at < %s",
				'date_col' => 'scheduled_at',
			),
			'email_engagement'  => array(
				'label'    => 'Email Engagement Logs',
				'table'    => 'mint_broadcast_email_meta',
				'alias'    => 'bem',
				'filter'   => 'bem.created_at < %s',
				'date_col' => 'created_at',
				// count_filter uses a subquery to match only meta rows linked to eligible sent emails.
				'count_filter' => "bem.created_at < %s AND bem.mint_email_id IN (SELECT id FROM `{prefix}mint_broadcast_emails` WHERE status = 'sent' AND scheduled_at < %s)",
			),
			'automation_log'    => array(
				'label'    => 'Automation Execution Logs',
				'table'    => 'mint_automation_log',
				'alias'    => 'al',
				'filter'   => "al.status IN ('completed', 'exited') AND al.created_at < %s",
				'date_col' => 'created_at',
			),
			'automation_jobs'   => array(
				'label'    => 'Automation Job Queue',
				'table'    => 'mint_automation_jobs',
				'alias'    => 'aj',
				'filter'   => "aj.status = 'completed' AND aj.created_at < %s",
				'date_col' => 'created_at',
			),
			'form_submissions'  => array(
				'label'    => 'Form Submission Logs',
				'table'    => 'mint_form_submissions',
				'alias'    => 'fs',
				'filter'   => 'fs.created_at < %s',
				'date_col' => 'created_at',
			),
			'abandoned_carts'   => array(
				'label'    => 'Abandoned Cart Logs',
				'table'    => 'mint_abandoned_carts',
				'alias'    => 'ac',
				'filter'   => "ac.status IN ('recovered', 'lost') AND ac.created_at < %s",
				'date_col' => 'created_at',
			),
			'download_tracking' => array(
				'label'    => 'Download Tracking Tokens',
				'table'    => 'mint_lead_magnet_download_tracking',
				'alias'    => 'lmdt',
				'filter'   => '(lmdt.is_used = 1 OR lmdt.expires_at < NOW()) AND lmdt.created_at < %s',
				'date_col' => 'created_at',
			),
		);
	}

	/**
	 * Validates and sanitises common preview/start request parameters.
	 *
	 * Returns a WP_Error-style array on failure or an array with 'categories'
	 * and 'retention_days' keys on success.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The incoming request.
	 * @return array|\WP_REST_Response Validated params array or error response.
	 */
	private function validate_request_params( WP_REST_Request $request ) {
		$params         = MrmCommon::get_api_params_values( $request );
		$retention_days = isset( $params['retention_days'] ) ? (int) $params['retention_days'] : 0;
		$categories     = isset( $params['categories'] ) ? $params['categories'] : array();

		if ( $retention_days < 1 ) {
			return $this->get_error_response( __( 'Retention period must be at least 1 day.', 'mrm' ) );
		}

		if ( empty( $categories ) || ! is_array( $categories ) ) {
			return $this->get_error_response( __( 'Please select at least one category.', 'mrm' ) );
		}

		$valid_categories = array_keys( $this->get_category_config() );
		foreach ( $categories as $cat ) {
			if ( ! in_array( $cat, $valid_categories, true ) ) {
				/* translators: %s: invalid category key */
				return $this->get_error_response( sprintf( __( 'Invalid category: %s', 'mrm' ), sanitize_key( $cat ) ) );
			}
		}

		return array(
			'categories'     => $categories,
			'retention_days' => $retention_days,
		);
	}

	/**
	 * Computes the reference cutoff date string for a given retention period.
	 *
	 * Uses current_time( 'mysql' ) so the cutoff is in WordPress local time,
	 * consistent with ExportJob and DeleteJob which also use local time.
	 *
	 * @since 1.0.0
	 * @param int $retention_days Number of days to retain.
	 * @return string Date string in 'Y-m-d H:i:s' format.
	 */
	private function compute_ref_date( int $retention_days ): string {
		return date( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . " -{$retention_days} days" ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
	}

	/**
	 * Counts eligible rows for a single category.
	 *
	 * @since 1.0.0
	 * @param string $category Category slug.
	 * @param string $ref_date Reference cutoff date string.
	 * @return int Row count.
	 */
	private function count_rows_for_category( string $category, string $ref_date ): int {
		global $wpdb;

		$config = $this->get_category_config();
		if ( ! isset( $config[ $category ] ) ) {
			return 0;
		}

		$cfg    = $config[ $category ];
		$table  = $wpdb->prefix . $cfg['table'];
		$alias  = $cfg['alias'];

		// Use count_filter (with subquery) when available, otherwise fall back to filter.
		$raw_filter = isset( $cfg['count_filter'] ) ? $cfg['count_filter'] : $cfg['filter'];
		$filter     = str_replace( '{prefix}', $wpdb->prefix, $raw_filter );

		// Count the number of %s placeholders to build the args array.
		$placeholder_count = substr_count( $filter, '%s' );
		$prepare_args      = array_fill( 0, $placeholder_count, $ref_date );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM `{$table}` AS `{$alias}` WHERE {$filter}",
				...$prepare_args
			)
		);

		return (int) $count;
	}

	/**
	 * Runs aggregate stats (COUNT, MIN date, MAX date) for a single category.
	 *
	 * @since 1.0.0
	 * @param string $category Category slug.
	 * @param array  $cfg      Category config entry.
	 * @param string $ref_date Reference cutoff date string.
	 * @return array Associative array with row_count, oldest_date, newest_date.
	 */
	private function get_aggregate_stats( string $category, array $cfg, string $ref_date ): array {
		global $wpdb;

		$table    = $wpdb->prefix . $cfg['table'];
		$alias    = $cfg['alias'];
		$date_col = $cfg['date_col'];

		// Use count_filter (with subquery) when available, otherwise fall back to filter.
		$raw_filter = isset( $cfg['count_filter'] ) ? $cfg['count_filter'] : $cfg['filter'];
		$filter     = str_replace( '{prefix}', $wpdb->prefix, $raw_filter );

		// Count the number of %s placeholders to build the args array.
		$placeholder_count = substr_count( $filter, '%s' );
		$prepare_args      = array_fill( 0, $placeholder_count, $ref_date );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) AS row_count, MIN(`{$alias}`.`{$date_col}`) AS oldest_date, MAX(`{$alias}`.`{$date_col}`) AS newest_date FROM `{$table}` AS `{$alias}` WHERE {$filter}",
				...$prepare_args
			),
			ARRAY_A
		);

		return array(
			'row_count'   => isset( $row['row_count'] ) ? (int) $row['row_count'] : 0,
			'oldest_date' => isset( $row['oldest_date'] ) ? $row['oldest_date'] : null,
			'newest_date' => isset( $row['newest_date'] ) ? $row['newest_date'] : null,
		);
	}

	/**
	 * Fetches up to 10 affected entity names (campaign/automation titles) for a category.
	 *
	 * @since 1.0.0
	 * @param string $category Category slug.
	 * @param array  $cfg      Category config entry.
	 * @param string $ref_date Reference cutoff date string.
	 * @return array Array of entity title strings.
	 */
	private function get_affected_entities( string $category, array $cfg, string $ref_date ): array {
		global $wpdb;

		$p = $wpdb->prefix;

		switch ( $category ) {
			case 'broadcast_emails':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT c.title FROM `{$p}mint_campaigns` c INNER JOIN `{$p}mint_broadcast_emails` be ON be.campaign_id = c.id WHERE {$cfg['filter']} LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					)
				);

			case 'email_engagement':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT c.title FROM `{$p}mint_campaigns` c INNER JOIN `{$p}mint_broadcast_emails` be ON be.campaign_id = c.id INNER JOIN `{$p}mint_broadcast_email_meta` bem ON bem.mint_email_id = be.id WHERE {$cfg['filter']} LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					)
				);

			case 'automation_log':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT a.name FROM `{$p}mint_automations` a INNER JOIN `{$p}mint_automation_log` al ON al.automation_id = a.id WHERE {$cfg['filter']} LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					)
				);

			case 'automation_jobs':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT a.name FROM `{$p}mint_automations` a INNER JOIN `{$p}mint_automation_jobs` aj ON aj.automation_id = a.id WHERE {$cfg['filter']} LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					)
				);

			case 'form_submissions':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT f.title FROM `{$p}mint_forms` f INNER JOIN `{$p}mint_form_submissions` fs ON fs.form_id = f.id WHERE {$cfg['filter']} LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					)
				);

			case 'abandoned_carts':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT a.name FROM `{$p}mint_automations` a INNER JOIN `{$p}mint_abandoned_carts` ac ON ac.automation_id = a.id WHERE {$cfg['filter']} LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					)
				);

			case 'download_tracking':
			default:
				return array();
		}
	}

	/**
	 * Fetches up to 5 human-readable sample rows for a category.
	 *
	 * @since 1.0.0
	 * @param string $category Category slug.
	 * @param array  $cfg      Category config entry.
	 * @param string $ref_date Reference cutoff date string.
	 * @return array Array of row associative arrays.
	 */
	private function get_sample_rows( string $category, array $cfg, string $ref_date ): array {
		global $wpdb;

		$p = $wpdb->prefix;

		switch ( $category ) {
			case 'broadcast_emails':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT con.email AS email_address, con.first_name, con.last_name, camp.title AS campaign_title, be.status, be.scheduled_at AS sent_date FROM `{$p}mint_broadcast_emails` be LEFT JOIN `{$p}mint_contacts` con ON be.contact_id = con.id LEFT JOIN `{$p}mint_campaigns` camp ON be.campaign_id = camp.id WHERE {$cfg['filter']} LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					),
					ARRAY_A
				);

			case 'email_engagement':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT con.email AS email_address, camp.title AS campaign_title, bem.created_at FROM `{$p}mint_broadcast_email_meta` bem INNER JOIN `{$p}mint_broadcast_emails` be ON bem.mint_email_id = be.id LEFT JOIN `{$p}mint_contacts` con ON be.contact_id = con.id LEFT JOIN `{$p}mint_campaigns` camp ON be.campaign_id = camp.id WHERE {$cfg['filter']} LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					),
					ARRAY_A
				);

			case 'automation_log':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT al.email, a.name AS automation_name, al.step_id, al.status, al.count AS execution_count, al.created_at FROM `{$p}mint_automation_log` al LEFT JOIN `{$p}mint_automations` a ON al.automation_id = a.id WHERE {$cfg['filter']} LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					),
					ARRAY_A
				);

			case 'automation_jobs':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT a.name AS automation_name, aj.next_step_id, aj.status, aj.created_at FROM `{$p}mint_automation_jobs` aj LEFT JOIN `{$p}mint_automations` a ON aj.automation_id = a.id WHERE {$cfg['filter']} LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					),
					ARRAY_A
				);

			case 'form_submissions':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT f.title AS form_title, con.email AS contact_email, fs.status, fs.created_at FROM `{$p}mint_form_submissions` fs LEFT JOIN `{$p}mint_forms` f ON fs.form_id = f.id LEFT JOIN `{$p}mint_contacts` con ON fs.contact_id = con.id WHERE {$cfg['filter']} LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					),
					ARRAY_A
				);

			case 'abandoned_carts':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ac.email, a.name AS automation_name, ac.status, ac.total AS cart_total, ac.created_at FROM `{$p}mint_abandoned_carts` ac LEFT JOIN `{$p}mint_automations` a ON ac.automation_id = a.id WHERE {$cfg['filter']} LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					),
					ARRAY_A
				);

			case 'download_tracking':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT con.email, lmdt.is_used, lmdt.expires_at, lmdt.created_at FROM `{$p}mint_lead_magnet_download_tracking` lmdt LEFT JOIN `{$p}mint_contacts` con ON lmdt.contact_id = con.id WHERE {$cfg['filter']} LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$ref_date
					),
					ARRAY_A
				);

			default:
				return array();
		}
	}

	/**
	 * Handles GET /mrm/v1/data-cleanup/preview
	 *
	 * Returns row counts, date ranges, affected entity names, and sample rows
	 * for each selected category.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return \WP_REST_Response
	 */
	public function preview( WP_REST_Request $request ) {
		$validated = $this->validate_request_params( $request );

		// If validate_request_params returned a response object, it's an error.
		if ( $validated instanceof \WP_REST_Response ) {
			return $validated;
		}

		$categories     = $validated['categories'];
		$retention_days = $validated['retention_days'];
		$ref_date       = $this->compute_ref_date( $retention_days );
		$config         = $this->get_category_config();
		$result         = array();

		foreach ( $categories as $category ) {
			$cfg   = $config[ $category ];
			$stats = $this->get_aggregate_stats( $category, $cfg, $ref_date );

			$result[ $category ] = array(
				'label'             => $cfg['label'],
				'row_count'         => $stats['row_count'],
				'date_range'        => array(
					'oldest_date' => $stats['oldest_date'],
					'newest_date' => $stats['newest_date'],
				),
				'affected_entities' => $this->get_affected_entities( $category, $cfg, $ref_date ),
				'sample_rows'       => $stats['row_count'] > 0 ? $this->get_sample_rows( $category, $cfg, $ref_date ) : array(),
			);
		}

		return $this->get_success_response_data( array( 'data' => $result ) );
	}

	/**
	 * Handles POST /mrm/v1/data-cleanup/start
	 *
	 * Validates input, enforces mutual exclusion, computes total rows,
	 * sets initial job state, and dispatches the appropriate background job.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return \WP_REST_Response
	 */
	public function start( WP_REST_Request $request ) {
		$params = MrmCommon::get_api_params_values( $request );
		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';

		// Validate action.
		if ( ! in_array( $action, array( 'export_then_delete', 'delete_only' ), true ) ) {
			return $this->get_error_response( __( 'Invalid action. Must be export_then_delete or delete_only.', 'mrm' ) );
		}

		$validated = $this->validate_request_params( $request );

		if ( $validated instanceof \WP_REST_Response ) {
			return $validated;
		}

		$categories     = $validated['categories'];
		$retention_days = $validated['retention_days'];

		// Mutual exclusion checks.
		$state          = $this->state_manager->get_state();
		$current_status = $state['status'];

		if ( 'exporting' === $current_status ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'An export job is already in progress.', 'mrm' ),
				),
				409
			);
		}

		if ( 'deleting' === $current_status ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'A delete job is already in progress.', 'mrm' ),
				),
				409
			);
		}

		if ( 'export_ready' === $current_status && 'export_then_delete' === $action ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'An export has already completed. Proceed to delete or cancel.', 'mrm' ),
				),
				409
			);
		}

		// Compute total rows across all selected categories.
		$ref_date   = $this->compute_ref_date( $retention_days );
		$total_rows = 0;

		foreach ( $categories as $category ) {
			$total_rows += $this->count_rows_for_category( $category, $ref_date );
		}

		// Set initial job state.
		$initial_status = ( 'export_then_delete' === $action ) ? 'exporting' : 'deleting';

		$this->state_manager->set_state(
			array(
				'status'          => $initial_status,
				'action'          => $action,
				'categories'      => $categories,
				'retention_days'  => $retention_days,
				'total_rows'      => $total_rows,
				'rows_exported'   => 0,
				'rows_deleted'    => 0,
				'export_progress' => 0,
				'delete_progress' => 0,
				'export_file'     => null,
				'export_file_url' => null,
				'started_at'      => current_time( 'mysql' ),
				'error'           => null,
			)
		);

		// Dispatch the appropriate job.
		$job_args = array(
			'categories'     => $categories,
			'retention_days' => $retention_days,
			'action'         => $action,
		);

		if ( 'export_then_delete' === $action ) {
			$this->scheduler->dispatch_export( $job_args );
			$message = __( 'Export job started successfully.', 'mrm' );
		} else {
			$this->scheduler->dispatch_delete( $job_args );
			$message = __( 'Delete job started successfully.', 'mrm' );
		}

		return $this->get_success_response_data(
			array(
				'success' => true,
				'message' => $message,
				'data'    => array(
					'status'     => $initial_status,
					'total_rows' => $total_rows,
				),
			)
		);
	}

	/**
	 * Handles GET /mrm/v1/data-cleanup/status
	 *
	 * Returns the current job state. Performs staleness detection and
	 * clears the export file URL if the file no longer exists on disk.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return \WP_REST_Response
	 */
	public function status( WP_REST_Request $request ) {
		// Check and handle staleness (auto-transitions to failed if timed out).
		$this->state_manager->check_and_handle_staleness();

		$state = $this->state_manager->get_state();

		// If export file URL is set but file no longer exists on disk, clear it.
		if ( ! empty( $state['export_file'] ) && ! empty( $state['export_file_url'] ) ) {
			if ( ! file_exists( $state['export_file'] ) ) {
				$this->state_manager->clear_export_file();
				$state['export_file']     = null;
				$state['export_file_url'] = null;
			}
		}

		return $this->get_success_response_data( array( 'data' => $state ) );
	}

	/**
	 * Handles POST /mrm/v1/data-cleanup/cancel
	 *
	 * Cancels all pending Action Scheduler jobs, deletes any partial export file,
	 * and resets the job state to idle.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The incoming REST request.
	 * @return \WP_REST_Response
	 */
	public function cancel( WP_REST_Request $request ) {
		// Cancel all pending Action Scheduler jobs.
		$this->scheduler->cancel_all_jobs();

		// Delete partial export file if it exists.
		$state = $this->state_manager->get_state();

		if ( ! empty( $state['export_file'] ) && file_exists( $state['export_file'] ) ) {
			wp_delete_file( $state['export_file'] );
		}

		// Reset state to idle.
		$this->state_manager->reset_to_idle();

		return $this->get_success_response( __( 'Job cancelled successfully.', 'mrm' ) );
	}
}
