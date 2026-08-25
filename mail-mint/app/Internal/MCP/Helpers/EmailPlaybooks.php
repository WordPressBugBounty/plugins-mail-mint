<?php
/**
 * Industry-standard email content for automation steps.
 *
 * An automation whose sendMail steps have no body is worse than no automation:
 * it looks finished in the builder, activates cleanly, and mails nothing. That
 * is what happened when upsert-automation accepted a sendMail step with an
 * empty `message_data`. This class is the safety net — whenever a caller does
 * not supply copy, it produces a real, send-ready email appropriate to the
 * trigger and the email's position in the sequence.
 *
 * Playbook copy is intentionally generic about the business but specific about
 * the JOB of each email (first cart reminder ≠ last-chance nudge). Callers mark
 * playbook-sourced emails as needing review so the user is told to personalise
 * them rather than discovering filler in a live send.
 *
 * @package Mint\MRM\Internal\MCP\Helpers
 */

namespace Mint\MRM\Internal\MCP\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Trigger-aware default email content in EmailComposer's content shape.
 */
class EmailPlaybooks {

	/**
	 * Trigger key => playbook family.
	 *
	 * Grouped by the merge tags each trigger family actually exposes (see
	 * src/components/MargeTag/constant.jsx). Composing a cart email for an
	 * order trigger would emit {{cart.*}} tags that never resolve, so the
	 * mapping is explicit rather than inferred from the trigger name.
	 *
	 * @var array<string,string>
	 */
	const FAMILIES = array(
		// Cart recovery — the only family with {{cart.*}} tags.
		'wc_abandoned_cart'                       => 'abandoned_cart',
		'wc_abandoned_cart_lost'                  => 'cart_lost',
		'wc_abandoned_cart_recovered'             => 'cart_recovered',

		// Purchase / order lifecycle — {{order.*}} and {{wc.*}} tags.
		'wc_all_order_created'                    => 'post_purchase',
		'wc_order_created'                        => 'post_purchase',
		'wc_order_completed'                      => 'post_purchase',
		'wc_first_order'                          => 'first_order',
		'wc_order_failed'                         => 'order_failed',
		'wc_order_status_changed'                 => 'post_purchase',
		'wc_review_received'                      => 'post_purchase',
		'wc_price_dropped'                        => 'price_drop',
		'wc_customer_winback'                     => 'winback',
		'edd_complete_purchase'                   => 'post_purchase',

		// Subscriptions / memberships.
		'wcs_subscription_created'                => 'subscription_welcome',
		'wcs_subscription_trial_end'              => 'trial_ending',
		'wcs_subscription_before_renewal'         => 'renewal_reminder',
		'wcs_subscription_before_end'             => 'renewal_reminder',
		'wcs_subscription_renewal_payment_failed' => 'payment_failed',
		'wcm_membership_created'                  => 'subscription_welcome',

		// Onboarding / list growth.
		'wp_user_registration'                    => 'welcome',
		'mint_create_contact'                     => 'welcome',
		'mint_form_submission'                    => 'welcome',
		'mint_list_applied'                       => 'welcome',
		'mint_tag_applied'                        => 'welcome',
		'mint_add_to_segment'                     => 'welcome',
		'funnel_optin_submitted'                  => 'welcome',

		// Re-engagement.
		'mint_inactive_subscriber'                => 'winback',

		// Learning.
		'learndash_enrolled_course'               => 'course_welcome',
		'lifterlms_enrolled_course'               => 'course_welcome',
		'tutor_after_enrolled'                    => 'course_welcome',
		'learndash_complete_course'               => 'course_complete',
		'tutor_complete_course'                   => 'course_complete',

		// Scheduling.
		'fluentbooking_new_booking'               => 'booking_confirmed',

		// Milestones.
		'mint_anniversary_reminder'               => 'anniversary',
	);

	/**
	 * Default content for a sendMail step.
	 *
	 * @param string $trigger_key Canonical trigger_name the automation runs on.
	 * @param int    $position    1-based index of this email among the automation's
	 *                            sendMail steps. Later emails escalate in urgency.
	 * @param int    $total       How many sendMail steps the automation has, so a
	 *                            final email reads as a final email.
	 * @return array{content:array,subject:string,preview_text:string,playbook:string}
	 */
	public static function for_step( string $trigger_key, int $position = 1, int $total = 1 ): array {
		$family = self::FAMILIES[ $trigger_key ] ?? 'generic';
		$plan   = self::plan( $family, $position, $total );

		return array(
			'subject'      => $plan['subject'],
			'preview_text' => $plan['preview_text'],
			'content'      => $plan['content'],
			'playbook'     => $family . ':' . $position,
		);
	}

	/**
	 * Whether a trigger has purpose-built copy rather than the generic skeleton.
	 *
	 * @param string $trigger_key Canonical trigger_name.
	 * @return bool
	 */
	public static function has_playbook( string $trigger_key ): bool {
		return isset( self::FAMILIES[ $trigger_key ] );
	}

	/**
	 * Builds the email plan for a family and sequence position.
	 *
	 * @param string $family   Playbook family.
	 * @param int    $position 1-based position among sendMail steps.
	 * @param int    $total    Total sendMail steps.
	 * @return array{subject:string,preview_text:string,content:array}
	 */
	private static function plan( string $family, int $position, int $total ): array {
		$is_last = $position >= $total && $total > 1;

		switch ( $family ) {
			case 'abandoned_cart':
				return self::cart_recovery( $position, $is_last );

			case 'cart_lost':
				return self::build(
					'We saved your favourites, {{contact.first_name}}',
					'Your cart expired — but these are still available',
					'Still thinking it over?',
					'Your cart has expired, but everything in it is still waiting for you.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, the items you were looking at are still in stock. Here is what caught your eye:' ),
						array( 'type' => 'paragraph', 'text' => '{{cart.items}}' ),
						array( 'type' => 'button', 'label' => 'Rebuild my cart', 'url' => '{{cart.recovery_url}}' ),
						array( 'type' => 'paragraph', 'text' => 'If you have moved on, no problem at all — we will stop reminding you about this one.' ),
					)
				);

			case 'cart_recovered':
				return self::build(
					'Thank you for your order, {{contact.first_name}}',
					'We are getting your order ready',
					'Thank you for your order',
					'We are preparing everything now and will let you know the moment it ships.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, thanks for completing your purchase. Here is what you ordered:' ),
						array( 'type' => 'paragraph', 'text' => '{{cart.items}}' ),
						array( 'type' => 'paragraph', 'text' => 'Order total: {{cart.currency}}{{cart.total}}' ),
						array( 'type' => 'paragraph', 'text' => 'If anything looks wrong, just reply to this email and we will sort it out.' ),
					)
				);

			case 'welcome':
				return self::welcome( $position, $is_last );

			case 'first_order':
				return self::build(
					'Thanks for your first order, {{contact.first_name}}',
					'A little something about what happens next',
					'Welcome aboard, {{contact.first_name}}',
					'Thank you for your first order with us.',
					array(
						array( 'type' => 'paragraph', 'text' => 'We are thrilled you chose us. Your order is being prepared and you will get a shipping update as soon as it is on the way.' ),
						array( 'type' => 'heading', 'text' => 'While you wait' ),
						array(
							'type'  => 'bullets',
							'items' => array(
								'Reply to this email with any question — a real person reads it',
								'Keep an eye out for your shipping confirmation',
								'Follow us for early access to new arrivals',
							),
						),
					)
				);

			case 'post_purchase':
				return self::build(
					'Your order is confirmed, {{contact.first_name}}',
					'Here is everything you need to know',
					'Order confirmed',
					'Thanks for your purchase — here are the details.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we have received your order and are getting it ready.' ),
						array( 'type' => 'paragraph', 'text' => 'We will email you again the moment it ships. If you need to change anything, reply to this email as soon as you can.' ),
					)
				);

			case 'order_failed':
				return self::build(
					'There was a problem with your payment',
					'Your order did not go through — here is how to fix it',
					'We could not process your payment',
					'Your order is on hold until the payment goes through.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, your recent payment did not complete, so we have not been able to process your order.' ),
						array( 'type' => 'paragraph', 'text' => 'This is usually a temporary card issue and takes a moment to fix. Your items are still reserved for now.' ),
						array( 'type' => 'button', 'label' => 'Complete my payment', 'url' => '{{url.checkout}}' ),
					)
				);

			case 'price_drop':
				return self::build(
					'Good news — the price just dropped',
					'Something on your radar is now cheaper',
					'The price just dropped',
					'An item you were interested in is now available for less.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, something you were looking at has come down in price. Prices can change again, so it is worth a look now.' ),
						array( 'type' => 'button', 'label' => 'See the new price', 'url' => '{{url.shop}}' ),
					)
				);

			case 'winback':
				return self::winback( $position, $is_last );

			case 'subscription_welcome':
				return self::build(
					'Your subscription is active, {{contact.first_name}}',
					'Here is how to get the most out of it',
					'You are all set',
					'Your subscription is active and ready to use.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, thanks for subscribing. Everything is active on your account from today.' ),
						array( 'type' => 'heading', 'text' => 'Getting started' ),
						array(
							'type'  => 'bullets',
							'items' => array(
								'Log in and explore what is included',
								'Set your preferences so you only hear what matters',
								'Reply to this email if you get stuck — we are here to help',
							),
						),
						array( 'type' => 'button', 'label' => 'Go to my account', 'url' => '{{url.my_account}}' ),
					)
				);

			case 'trial_ending':
				return self::build(
					'Your trial ends soon, {{contact.first_name}}',
					'A quick heads-up before your trial finishes',
					'Your trial is nearly over',
					'Here is what happens next — and how to keep going.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, your trial is coming to an end shortly. If you would like to keep your access, no action is needed — your subscription will continue automatically.' ),
						array( 'type' => 'paragraph', 'text' => 'If now is not the right time, you can change or cancel any time from your account.' ),
						array( 'type' => 'button', 'label' => 'Manage my subscription', 'url' => '{{url.my_account}}' ),
					)
				);

			case 'renewal_reminder':
				return self::build(
					'Your subscription renews soon',
					'A heads-up so nothing catches you by surprise',
					'Your renewal is coming up',
					'We wanted to give you plenty of notice.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, your subscription is due to renew shortly. You do not need to do anything — we will take care of it.' ),
						array( 'type' => 'paragraph', 'text' => 'If you would like to make a change first, you can update or cancel from your account at any time.' ),
						array( 'type' => 'button', 'label' => 'Manage my subscription', 'url' => '{{url.my_account}}' ),
					)
				);

			case 'payment_failed':
				return self::build(
					'We could not process your renewal',
					'A quick fix keeps your access running',
					'Your payment did not go through',
					'Update your details to keep your subscription active.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we tried to renew your subscription but the payment did not complete.' ),
						array( 'type' => 'paragraph', 'text' => 'This is nearly always a card expiry or a temporary bank block. Updating your payment details takes a minute and keeps everything running.' ),
						array( 'type' => 'button', 'label' => 'Update payment details', 'url' => '{{url.my_account}}' ),
					)
				);

			case 'course_welcome':
				return self::build(
					'Welcome to the course, {{contact.first_name}}',
					'Here is how to get started',
					'You are enrolled',
					'Everything is ready for your first lesson.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, welcome aboard. Your course is unlocked and you can start whenever you are ready.' ),
						array( 'type' => 'heading', 'text' => 'Tips for finishing' ),
						array(
							'type'  => 'bullets',
							'items' => array(
								'Block out a regular slot — consistency beats long sessions',
								'Start with lesson one even if you know the basics',
								'Reply here if you have a question at any point',
							),
						),
						array( 'type' => 'button', 'label' => 'Start the course', 'url' => '{{site.url}}' ),
					)
				);

			case 'course_complete':
				return self::build(
					'Congratulations, {{contact.first_name}}',
					'You finished the course — here is what is next',
					'You did it',
					'Congratulations on completing the course.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, finishing takes real commitment — well done.' ),
						array( 'type' => 'paragraph', 'text' => 'If you found it useful, we would love a short review. And if you are ready for the next step, there is more waiting for you.' ),
						array( 'type' => 'button', 'label' => 'See what is next', 'url' => '{{site.url}}' ),
					)
				);

			case 'booking_confirmed':
				return self::build(
					'Your booking is confirmed',
					'All the details for your upcoming session',
					'You are booked in',
					'Here are the details of your booking.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, your booking is confirmed. We are looking forward to it.' ),
						array( 'type' => 'paragraph', 'text' => 'If you need to reschedule, just reply to this email and we will find another time.' ),
					)
				);

			case 'anniversary':
				return self::build(
					'Happy anniversary, {{contact.first_name}}',
					'A thank you for being with us',
					'It has been a year',
					'Thank you for being part of what we do.',
					array(
						array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, today marks another year with us — thank you for sticking around.' ),
						array( 'type' => 'paragraph', 'text' => 'As a small thank you, here is something for your next visit.' ),
						array( 'type' => 'button', 'label' => 'Claim my thank you', 'url' => '{{site.url}}' ),
					)
				);

			case 'generic':
			default:
				return self::generic( $position, $is_last );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Multi-touch sequences
	 *
	 * Position drives the job of the email, following the standard shape these
	 * flows take: remind, then add a reason, then close with urgency. Copy
	 * never invents a discount amount — the user must choose that themselves.
	 * --------------------------------------------------------------------- */

	/**
	 * Abandoned cart recovery, escalating by position.
	 *
	 * @param int  $position 1-based email index.
	 * @param bool $is_last  Whether this is the final email in the sequence.
	 * @return array
	 */
	private static function cart_recovery( int $position, bool $is_last ): array {
		if ( 1 === $position ) {
			return self::build(
				'You left something behind, {{contact.first_name}}',
				'Your cart is saved — pick up where you left off',
				'Still interested?',
				'We saved your cart so you can finish whenever you are ready.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, you left a few things in your cart. We have kept them for you:' ),
					array( 'type' => 'paragraph', 'text' => '{{cart.items}}' ),
					array( 'type' => 'paragraph', 'text' => 'Total: {{cart.currency}}{{cart.total}}' ),
					array( 'type' => 'button', 'label' => 'Return to my cart', 'url' => '{{cart.recovery_url}}' ),
					array( 'type' => 'paragraph', 'text' => 'Checkout takes less than a minute.' ),
				)
			);
		}

		if ( ! $is_last ) {
			return self::build(
				'Any questions about your order?',
				'We are here if something is holding you back',
				'Can we help?',
				'Sometimes a small question is all that stands in the way.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, your cart is still saved. If something stopped you from checking out, we would genuinely like to help.' ),
					array( 'type' => 'paragraph', 'text' => '{{cart.items}}' ),
					array( 'type' => 'heading', 'text' => 'Common questions' ),
					array(
						'type'  => 'bullets',
						'items' => array(
							'Delivery times and shipping costs',
							'Returns and exchanges',
							'Payment options',
						),
					),
					array( 'type' => 'button', 'label' => 'Complete my order', 'url' => '{{cart.recovery_url}}' ),
					array( 'type' => 'paragraph', 'text' => 'Just reply to this email and a real person will get back to you.' ),
				)
			);
		}

		return self::build(
			'Last chance — your cart expires soon',
			'We cannot hold these items much longer',
			'Your cart is about to expire',
			'This is the last reminder we will send about this cart.',
			array(
				array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we are about to release the items in your cart back into stock.' ),
				array( 'type' => 'paragraph', 'text' => '{{cart.items}}' ),
				array( 'type' => 'button', 'label' => 'Checkout now', 'url' => '{{cart.recovery_url}}' ),
				array( 'type' => 'paragraph', 'text' => 'This is the last email we will send about this cart — thanks for reading.' ),
			)
		);
	}

	/**
	 * Welcome / onboarding sequence.
	 *
	 * @param int  $position 1-based email index.
	 * @param bool $is_last  Whether this is the final email in the sequence.
	 * @return array
	 */
	private static function welcome( int $position, bool $is_last ): array {
		if ( 1 === $position ) {
			return self::build(
				'Welcome, {{contact.first_name}}',
				'Thanks for joining — here is what to expect',
				'Welcome aboard',
				'We are glad you are here.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, thanks for signing up. Here is what you can expect from us.' ),
					array(
						'type'  => 'bullets',
						'items' => array(
							'Practical tips you can actually use',
							'Early access to anything new',
							'No spam, and one-click unsubscribe any time',
						),
					),
					array( 'type' => 'button', 'label' => 'Take a look around', 'url' => '{{site.url}}' ),
					array( 'type' => 'paragraph', 'text' => 'If you ever have a question, just reply — we read every email.' ),
				)
			);
		}

		if ( ! $is_last ) {
			return self::build(
				'Getting the most out of this',
				'A few things worth knowing early',
				'Where to start',
				'A short guide to getting value quickly.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, now that you have settled in, here are the things people find most useful in their first week.' ),
					array(
						'type'  => 'bullets',
						'items' => array(
							'Start with the one thing you came here to solve',
							'Set your preferences so what you get stays relevant',
							'Ask us anything — replying is the fastest route',
						),
					),
					array( 'type' => 'button', 'label' => 'Get started', 'url' => '{{site.url}}' ),
				)
			);
		}

		return self::build(
			'Anything we can help with, {{contact.first_name}}?',
			'One question before we leave you to it',
			'How is it going?',
			'We would love to know how you are getting on.',
			array(
				array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, you have been with us a little while now. Is there anything you were hoping for that you have not found yet?' ),
				array( 'type' => 'paragraph', 'text' => 'Hit reply and tell us — it genuinely shapes what we build next.' ),
			)
		);
	}

	/**
	 * Re-engagement / win-back sequence.
	 *
	 * @param int  $position 1-based email index.
	 * @param bool $is_last  Whether this is the final email in the sequence.
	 * @return array
	 */
	private static function winback( int $position, bool $is_last ): array {
		if ( 1 === $position ) {
			return self::build(
				'We miss you, {{contact.first_name}}',
				'It has been a while — here is what you missed',
				'It has been a while',
				'A lot has changed since you last stopped by.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we noticed it has been a while. We have been busy since you were last here.' ),
					array( 'type' => 'button', 'label' => 'See what is new', 'url' => '{{site.url}}' ),
				)
			);
		}

		if ( ! $is_last ) {
			return self::build(
				'Something to bring you back',
				'A reason to take another look',
				'Worth another look?',
				'Here is what people come back for.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we would love to have you back. Here is what is worth your time right now.' ),
					array( 'type' => 'button', 'label' => 'Take a look', 'url' => '{{site.url}}' ),
					array( 'type' => 'paragraph', 'text' => 'Replace this with a specific offer or your best content — a concrete reason works far better than a general invitation.' ),
				)
			);
		}

		return self::build(
			'Should we stop emailing you?',
			'No hard feelings either way',
			'Still want to hear from us?',
			'We would rather ask than keep filling your inbox.',
			array(
				array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we have not heard from you in a while, so we want to check: would you still like these emails?' ),
				array( 'type' => 'paragraph', 'text' => 'If yes, wonderful — click below and nothing changes. If not, you can unsubscribe at the bottom and we will leave you be.' ),
				array( 'type' => 'button', 'label' => 'Keep me subscribed', 'url' => '{{site.url}}' ),
			)
		);
	}

	/**
	 * Fallback for triggers without purpose-built copy.
	 *
	 * @param int  $position 1-based email index.
	 * @param bool $is_last  Whether this is the final email in the sequence.
	 * @return array
	 */
	private static function generic( int $position, bool $is_last ): array {
		if ( 1 === $position ) {
			return self::build(
				'A quick note, {{contact.first_name}}',
				'Something we thought you should see',
				'Hello, {{contact.first_name}}',
				'Thanks for being here.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we are getting in touch because of something you did on our site.' ),
					array( 'type' => 'paragraph', 'text' => 'Replace this paragraph with the reason for this email and what you would like the reader to do next.' ),
					array( 'type' => 'button', 'label' => 'Take a look', 'url' => '{{site.url}}' ),
				)
			);
		}

		if ( ! $is_last ) {
			return self::build(
				'Following up, {{contact.first_name}}',
				'A short follow-up to our last email',
				'Following up',
				'Just in case our last email got buried.',
				array(
					array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, we wanted to follow up in case our last message came at a busy moment.' ),
					array( 'type' => 'paragraph', 'text' => 'Replace this with the single most useful thing you can offer at this point in the sequence.' ),
					array( 'type' => 'button', 'label' => 'Take a look', 'url' => '{{site.url}}' ),
				)
			);
		}

		return self::build(
			'One last thing, {{contact.first_name}}',
			'The final email in this sequence',
			'One last thing',
			'We will leave it here after this.',
			array(
				array( 'type' => 'paragraph', 'text' => 'Hi {{contact.first_name}}, this is the last email in this sequence.' ),
				array( 'type' => 'paragraph', 'text' => 'Replace this with a clear final call to action, and make it easy to say no.' ),
				array( 'type' => 'button', 'label' => 'Take a look', 'url' => '{{site.url}}' ),
			)
		);
	}

	/**
	 * Assembles a plan in EmailComposer's content shape.
	 *
	 * Button URLs are written as {{site.url}} or a trigger-specific merge tag so
	 * nothing ever points at an invented domain — EmailComposer rejects those,
	 * and a recovery link is the one thing a cart email cannot fake.
	 *
	 * @param string $subject      Email subject.
	 * @param string $preview_text Inbox preview line.
	 * @param string $heading      Hero heading.
	 * @param string $subheading   Hero subheading.
	 * @param array  $sections     EmailComposer sections.
	 * @return array{subject:string,preview_text:string,content:array}
	 */
	private static function build( string $subject, string $preview_text, string $heading, string $subheading, array $sections ): array {
		return array(
			'subject'      => $subject,
			'preview_text' => $preview_text,
			'content'      => array(
				'hero'           => array(
					'heading'    => $heading,
					'subheading' => $subheading,
				),
				'sections'       => $sections,
				'include_footer' => true,
			),
		);
	}
}
