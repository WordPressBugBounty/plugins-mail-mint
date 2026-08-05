<?php
/**
 * ContactSummaryContext — assembles the 360° contact context for the AI
 * contact-summary point-of-use helper.
 *
 * The conversational copilot and external MCP clients read a contact through
 * the `mail-mint/get-contact` tool, which returns profile, tags, lists, notes
 * and custom fields but NO engagement or commerce history. A useful contact
 * summary needs more: how the contact engages with email and what they have
 * bought. This builder composes that richer view from existing, Free data
 * sources so the single-shot summariser has something worth summarising.
 *
 * Commerce data is intentionally pulled through a filter rather than a direct
 * dependency: purchase history is a Free feature on the roadmap, and Pro
 * already ships WooCommerce/EDD history. Either can inject a provider block via
 * `mint_mail/ai/contact_summary_commerce` without this class knowing about
 * them — keeping Free standalone-safe (no bare Pro references).
 *
 * @package Mint\MRM\Internal\AI\Context
 * @since   1.20.0
 */

namespace Mint\MRM\Internal\AI\Context;

use Mint\MRM\Internal\MCP\Tools\ContactTools;

defined( 'ABSPATH' ) || exit;

class ContactSummaryContext {

	/**
	 * How many of the most recent emails to include verbatim in the context.
	 * Kept small so the JSON stays within a sensible token budget; aggregate
	 * counts still reflect the contact's full history.
	 */
	const RECENT_EMAILS = 20;

	/**
	 * Build the contact-summary context.
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $locale     Site locale the summary will be written in.
	 * @return array|\WP_Error Context array, or WP_Error if the contact is missing.
	 */
	public static function build( $contact_id, $locale = 'en_US' ) {
		$contact_id = (int) $contact_id;

		$profile = ContactTools::getContact(
			array(
				'contact_id'            => $contact_id,
				'include_notes'         => true,
				'include_custom_fields' => true,
			)
		);
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		$email    = $profile['email'] ?? '';
		$emails   = self::email_engagement( $contact_id );
		$commerce = self::commerce_history( $contact_id, $email );

		return array(
			'locale'           => $locale,
			'contact'          => array(
				'id'            => (int) ( $profile['id'] ?? $contact_id ),
				'name'          => trim( ( $profile['first_name'] ?? '' ) . ' ' . ( $profile['last_name'] ?? '' ) ),
				'email'         => $email,
				'status'        => $profile['status'] ?? '',
				'contact_type'  => $profile['contact_type'] ?? '',
				'source'        => $profile['source'] ?? '',
				'created_at'    => $profile['created_at'] ?? '',
				'tags'          => $profile['tags'] ?? array(),
				'lists'         => $profile['lists'] ?? array(),
				'notes'         => $profile['notes'] ?? array(),
				'custom_fields' => $profile['custom_fields'] ?? array(),
			),
			'emails'           => $emails,
			'purchase_history' => $commerce,
			'counts'           => array(
				'emails'             => (int) ( $emails['total'] ?? 0 ),
				'commerce_providers' => count( $commerce ),
			),
		);
	}

	/**
	 * Aggregate the contact's email engagement (send/open/click) from the
	 * broadcast log, reusing the same composition the profile "Emails" tab uses.
	 *
	 * @param int $contact_id Contact ID.
	 * @return array Engagement summary with recent items.
	 */
	private static function email_engagement( $contact_id ) {
		$empty = array(
			'total'        => 0,
			'recent_count' => 0,
			'opened'       => 0,
			'clicked'      => 0,
			'recent'       => array(),
		);

		// ContactProfileAction lives in the global namespace and composes
		// regular/campaign/automation/sequence emails plus open & click times.
		if ( ! class_exists( 'ContactProfileAction' ) ) {
			return $empty;
		}

		$action = new \ContactProfileAction();
		$result = $action->fetch_emails_for_contact(
			array(
				'contact_id' => $contact_id,
				'page'       => 1,
				'per-page'   => self::RECENT_EMAILS,
			)
		);

		$items = ( is_array( $result ) && ! empty( $result['emails'] ) && is_array( $result['emails'] ) )
			? $result['emails']
			: array();
		$total = ( is_array( $result ) && isset( $result['total_count'] ) )
			? (int) $result['total_count']
			: count( $items );

		$opened  = 0;
		$clicked = 0;
		$recent  = array();

		foreach ( $items as $item ) {
			$is_open  = ! empty( $item['email_opened_time'] );
			$is_click = ! empty( $item['email_clicked_time'] );

			if ( $is_open ) {
				++$opened;
			}
			if ( $is_click ) {
				++$clicked;
			}

			$recent[] = array(
				'subject' => $item['email_subject'] ?? '',
				'status'  => $item['status'] ?? '',
				'sent_at' => $item['sent_at'] ?? ( $item['broadcast_email_created_at'] ?? '' ),
				'opened'  => $is_open,
				'clicked' => $is_click,
			);
		}

		return array(
			'total'        => $total,
			'recent_count' => count( $recent ),
			'opened'       => $opened,
			'clicked'      => $clicked,
			'recent'       => $recent,
		);
	}

	/**
	 * Collect the contact's purchase/commerce history from any registered
	 * provider (Free purchase history, or Pro WooCommerce/EDD).
	 *
	 * @param int    $contact_id    Contact ID.
	 * @param string $contact_email Contact email address.
	 * @return array List of provider blocks; empty when no provider is hooked.
	 */
	private static function commerce_history( $contact_id, $contact_email ) {
		/**
		 * Inject a contact's purchase history into the AI contact summary.
		 *
		 * Handlers append one block per commerce provider, e.g.:
		 *   array(
		 *     'title'    => 'WooCommerce',
		 *     'total'    => 249.00,
		 *     'currency' => 'USD',
		 *     'orders'   => array(
		 *       array( 'id' => 1042, 'total' => 49.00, 'date' => '2026-05-01', 'status' => 'completed', 'items' => array( 'Pro Plan' ) ),
		 *     ),
		 *   )
		 *
		 * @since 1.20.0
		 *
		 * @param array  $providers     Provider blocks (default empty).
		 * @param int    $contact_id    Contact ID.
		 * @param string $contact_email Contact email address.
		 */
		$providers = apply_filters( 'mint_mail/ai/contact_summary_commerce', array(), $contact_id, $contact_email );

		return is_array( $providers ) ? array_values( $providers ) : array();
	}
}
