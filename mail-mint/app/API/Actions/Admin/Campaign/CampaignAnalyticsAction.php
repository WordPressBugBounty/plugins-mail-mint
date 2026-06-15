<?php
/**
 * Mail Mint
 *
 * Campaign analytics action for the Free plugin.
 *
 * Provides the regular-campaign analytics overview that ships with Mail Mint Free.
 * Pro-only campaign types (sequence, automation) and the activity/click-performance
 * drill-down tabs remain part of Mail Mint Pro. This class references only classes
 * that ship with Free, so it cannot fatal regardless of the installed Pro version.
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /app/API/Actions/Admin/Campaign
 * @since 1.23.3
 */

namespace Mint\MRM\API\Actions;

use Mint\MRM\Database\Repositories\CampaignRepository;
use Mint\MRM\Database\Repositories\BroadcastRepository;
use Mint\MRM\Admin\API\Controllers\MessageController;
use MRM\Common\MrmCommon;

/**
 * CampaignAnalyticsAction class.
 *
 * Handles the campaign analytics header and overview metrics for regular campaigns
 * on Mail Mint Free.
 *
 * @since 1.23.3
 */
class CampaignAnalyticsAction implements Action {

	/**
	 * Campaign repository instance.
	 *
	 * @var CampaignRepository|null
	 * @since 1.23.3
	 */
	private $campaign_repository;

	/**
	 * Broadcast repository instance.
	 *
	 * @var BroadcastRepository|null
	 * @since 1.23.3
	 */
	private $broadcast_repository;

	/**
	 * Returns the CampaignRepository, creating it on first call.
	 *
	 * @return CampaignRepository|null
	 * @since 1.23.3
	 */
	private function get_campaign_repository() {
		if ( null === $this->campaign_repository && class_exists( CampaignRepository::class ) ) {
			$this->campaign_repository = new CampaignRepository();
		}
		return $this->campaign_repository;
	}

	/**
	 * Returns the BroadcastRepository, creating it on first call.
	 *
	 * @return BroadcastRepository|null
	 * @since 1.23.3
	 */
	private function get_broadcast_repository() {
		if ( null === $this->broadcast_repository && class_exists( BroadcastRepository::class ) ) {
			$this->broadcast_repository = new BroadcastRepository();
		}
		return $this->broadcast_repository;
	}

	/**
	 * Format and retrieve campaign analytics header.
	 *
	 * Returns the campaign record along with its email steps, mirroring the response
	 * shape the analytics dashboard expects.
	 *
	 * @param array $params An array of parameters for retrieving analytics.
	 * @return array The formatted campaign analytics data.
	 *
	 * @since 1.23.3
	 */
	public function format_and_retrieve_campaign_analytics( $params ) {
		$campaign_repository = $this->get_campaign_repository();
		if ( null === $campaign_repository ) {
			return array();
		}

		$campaign_id = isset( $params['campaign_id'] ) ? $params['campaign_id'] : '';
		$campaign    = $campaign_repository->find( $campaign_id );
		$campaign    = \is_array( $campaign ) ? $campaign : array();

		// Attach campaign email steps to preserve the response shape expected by the dashboard.
		$campaign['emails'] = $campaign_repository->getCampaignEmails( $campaign_id );

		$campaign['scheduled_at'] = MrmCommon::format_campaign_date_time( 'scheduled_at', $campaign );
		$campaign['updated_at']   = MrmCommon::format_campaign_date_time( 'updated_at', $campaign );

		return $campaign;
	}

	/**
	 * Formats and retrieves an overview of campaign metrics.
	 *
	 * Calculates total recipients, delivered and bounced emails, open/click rate,
	 * click-to-open rate (CTOR), last 24 hours performance, total unsubscribes,
	 * order reports and a campaign summary.
	 *
	 * @param array $params An associative array containing 'campaign_id' and 'email_id'.
	 * @return array An associative array containing the analytics keys.
	 *
	 * @since 1.23.3
	 */
	public function format_and_retrieve_campaign_overview( $params ) {
		$campaign_repository = $this->get_campaign_repository();
		if ( null === $campaign_repository ) {
			return array(
				'recipients' => 0,
				'metrics'    => array(),
				'engagement' => array(),
				'summery'    => array(),
			);
		}

		$campaign_id      = isset( $params['campaign_id'] ) ? $params['campaign_id'] : '';
		$campaign         = $campaign_repository->find( $campaign_id );
		$type             = isset( $campaign['type'] ) ? $campaign['type'] : '';
		$email_id         = isset( $params['email_id'] ) ? $params['email_id'] : '';
		$total_recipients = $campaign_repository->getMeta( $campaign_id, 'total_recipients' ) ?: 0;

		// Adjust total recipients for recurring campaigns (Pro-only type, handled defensively via Free repo).
		if ( 'recurring' === $type ) {
			$broadcast_repository = $this->get_broadcast_repository();
			if ( null !== $broadcast_repository ) {
				$total_recipients = $broadcast_repository->getRecurringCampaignTotalRecipients( $campaign_id );
			}
		}

		// Resolve date range from filter params (null = all time, no restriction).
		$date_range = MessageController::resolve_date_range(
			isset( $params['filter'] ) ? $params['filter'] : '',
			isset( $params['start_date'] ) ? $params['start_date'] : '',
			isset( $params['end_date'] ) ? $params['end_date'] : ''
		);

		// Calculate delivered and bounced emails (always unfiltered — fixed at send time).
		$total_delivered = MessageController::prepare_delivered_reports( $email_id, $total_recipients );
		$total_bounced   = MessageController::prepare_bounced_reports( $email_id, $total_recipients );
		$bounced         = isset( $total_bounced['total_bounced'] ) ? $total_bounced['total_bounced'] : '';
		$delivered       = isset( $total_delivered['total_delivered'] ) ? $total_delivered['total_delivered'] : '';

		// Calculate click and open rate (filtered by date range when active).
		$open_rate  = MessageController::prepare_open_rate_reports( $email_id, $bounced, $delivered, $date_range );
		$click_rate = MessageController::prepare_click_rate_reports( $email_id, $bounced, $delivered, $date_range );
		$ctor       = MessageController::prepare_click_to_open_rate_reports( $click_rate, $open_rate );

		// Calculate engagement chart data (mode depends on active date filter).
		$engagement = MessageController::prepare_engagement_since_send(
			$email_id,
			$delivered,
			isset( $params['filter'] ) ? $params['filter'] : '',
			isset( $params['start_date'] ) ? $params['start_date'] : '',
			isset( $params['end_date'] ) ? $params['end_date'] : ''
		);

		// Calculate total unsubscribe (filtered by date range when active).
		$unsubscribe = MessageController::prepare_unsubscribe_reports( $email_id, $bounced, $delivered, $date_range );

		// Calculate order reports (filtered by date range when active).
		$orders = MessageController::prepare_order_reports( $email_id, 'campaign', $date_range );

		// Merge into the final metrics array.
		$metrics = array_merge( $total_delivered, $total_bounced, $open_rate, $click_rate, $unsubscribe, $orders, $ctor );

		// Prepare campaign summary (filtered by the same date range as the metric cards).
		$summery = $this->prepare_campaign_summery( $campaign_id, $date_range );

		return array(
			'recipients' => $total_recipients,
			'metrics'    => $metrics,
			'engagement' => $engagement,
			'summery'    => $summery,
		);
	}

	/**
	 * Prepare a summary of campaign metrics.
	 *
	 * Ported into Free so the overview does not depend on any Pro helper class.
	 *
	 * @param int        $campaign_id The ID of the campaign for which to prepare the summary.
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime] to restrict counts.
	 * @return array An associative array containing various campaign metrics.
	 *
	 * @since 1.23.3
	 */
	private function prepare_campaign_summery( $campaign_id, ?array $date_range = null ) {
		$broadcast_repo = $this->get_broadcast_repository();
		if ( null === $broadcast_repo ) {
			return array();
		}

		$total_sent    = $this->calculate_total_email_sent( $campaign_id, $date_range );
		$total_success = $broadcast_repo->countDeliveredStatusOnCampaign( $campaign_id, 'sent', $date_range );
		$success_rate  = $this->calculate_email_delivery_success_rate( $total_sent, $total_success );
		$total_bounced = $broadcast_repo->countDeliveredStatusOnCampaign( $campaign_id, 'failed', $date_range );
		$unsubscribe   = $broadcast_repo->countUnsubscribeOnCampaign( $campaign_id, $date_range );
		$total_open    = $broadcast_repo->calculateOpenRateOnCampaign( $campaign_id, $date_range );
		$total_click   = $broadcast_repo->calculateClickRateOnCampaign( $campaign_id, $date_range );
		$ctor          = $this->calculate_click_to_open_rate( $total_open, $total_click );
		$revenue       = $this->calculate_campaign_total_revenue( $campaign_id, $date_range );
		$last_opened   = $broadcast_repo->getLastMetaTimestampOnCampaign( $campaign_id, 'is_open', $date_range );
		$last_clicked  = $broadcast_repo->getLastMetaTimestampOnCampaign( $campaign_id, 'is_click', $date_range );

		return array(
			'total_sent'    => $total_sent,
			'total_success' => $total_success,
			'success_rate'  => $success_rate,
			'total_bounced' => $total_bounced,
			'unsubscribe'   => $unsubscribe,
			'total_open'    => $total_open,
			'total_click'   => $total_click,
			'ctor'          => $ctor,
			'revenue'       => MrmCommon::price_format_with_wc_currency( $revenue ),
			'last_opened'   => $last_opened,
			'last_clicked'  => $last_clicked,
		);
	}

	/**
	 * Calculate the total number of emails sent for a specific campaign.
	 *
	 * @param int        $campaign_id The campaign ID.
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime] to restrict by created_at.
	 * @return int The total number of emails sent.
	 *
	 * @since 1.23.3
	 */
	private function calculate_total_email_sent( $campaign_id, ?array $date_range = null ): int {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mint_broadcast_emails';

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE campaign_id = %d AND created_at BETWEEN %s AND %s", $campaign_id, $date_range['start'], $date_range['end'] ) ); //phpcs:ignore
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$table_name} WHERE campaign_id = %d", $campaign_id ) ); //phpcs:ignore
	}

	/**
	 * Calculate the success rate of email delivery as a percentage.
	 *
	 * @param int $total_sent The total number of emails sent.
	 * @param int $total_success The number of emails successfully delivered.
	 * @return string The success rate formatted to two decimal places.
	 *
	 * @since 1.23.3
	 */
	private function calculate_email_delivery_success_rate( $total_sent, $total_success ) {
		if ( $total_sent > 0 ) {
			return number_format( ( $total_success / $total_sent ) * 100, 2, '.', '' );
		}
		return number_format( 0, 2, '.', '' );
	}

	/**
	 * Calculate the click-to-open rate as a percentage.
	 *
	 * @param int $open The total number of emails opened.
	 * @param int $click The number of opened emails that were clicked.
	 * @return string The click-to-open rate formatted to two decimal places.
	 *
	 * @since 1.23.3
	 */
	private function calculate_click_to_open_rate( $open, $click ) {
		if ( $open > 0 ) {
			return number_format( ( $click / $open ) * 100, 2, '.', '' );
		}
		return number_format( 0, 2, '.', '' );
	}

	/**
	 * Calculate the total revenue generated by a specific campaign.
	 *
	 * @param int        $campaign_id The campaign ID.
	 * @param array|null $date_range  Optional ['start' => datetime, 'end' => datetime] to restrict by meta created_at.
	 * @return string|int The total revenue, or 0 when none.
	 *
	 * @since 1.23.3
	 */
	private function calculate_campaign_total_revenue( $campaign_id, ?array $date_range = null ) {
		$broadcast_repo = $this->get_broadcast_repository();
		if ( null === $broadcast_repo ) {
			return 0;
		}
		global $wpdb;
		$email_table      = $wpdb->prefix . 'mint_broadcast_emails';
		$email_meta_table = $wpdb->prefix . 'mint_broadcast_email_meta';

		if ( ! empty( $date_range['start'] ) && ! empty( $date_range['end'] ) ) {
			$order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$email_meta_table} WHERE meta_key = 'order_id' AND created_at BETWEEN %s AND %s AND mint_email_id IN (SELECT id FROM {$email_table} WHERE campaign_id = %d)", $date_range['start'], $date_range['end'], $campaign_id ) ); //phpcs:ignore
		} else {
			$order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$email_meta_table} WHERE meta_key = 'order_id' AND mint_email_id IN (SELECT id FROM {$email_table} WHERE campaign_id = %d)", $campaign_id ) ); //phpcs:ignore
		}

		if ( empty( $order_ids ) ) {
			return 0;
		}

		return $broadcast_repo->getTotalRevenueFromEmail( $order_ids );
	}
}
