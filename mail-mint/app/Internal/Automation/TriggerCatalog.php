<?php
/**
 * Canonical catalogue of every automation trigger the visual builder offers.
 *
 * DO NOT EDIT — generated from the builder step registry by
 * `npm run generate:trigger-catalog` (tools/trigger-catalog/generate.js).
 * Edit the trigger under src/automation/integrations/core/triggers/ and
 * regenerate; TriggerCatalogDriftTest fails if this file falls behind.
 *
 * Availability is NOT baked in here — this is the static shape of each
 * trigger. Ask TriggerAvailability whether a given site can use one.
 *
 * @package MintMail\App\Internal\Automation
 */

namespace MintMail\App\Internal\Automation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Static trigger catalogue.
 *
 * @package MintMail\App\Internal\Automation
 */
class TriggerCatalog {

	/**
	 * Category key => human label, mirroring the builder sidebar.
	 *
	 * @var array<string,string>
	 */
	const CATEGORIES = array(
		'mailmint' => 'Mail Mint',
		'mint-wordpress' => 'WordPress',
		'wpfunnels' => 'WPFunnels',
		'mint-woocommerce' => 'WooCommerce',
		'mint-woocommerce-subscription' => 'WooCommerce Subscriptions',
		'mint-woocommerce-membership' => 'WooCommerce Memberships',
		'mint-woocommerce-wishlist' => 'WooCommerce Wishlists',
		'edd' => 'Easy Digital Downloads',
		'mint-tutor-lms' => 'Tutor LMS',
		'mint-gravity-form' => 'Gravity Forms',
		'mint-jet-form' => 'JetFormBuilder',
		'mint-fluent-form' => 'Fluent Forms',
		'mint-fluent-booking' => 'Fluent Booking',
		'mint-contact-form' => 'Contact Form 7',
		'mint-bricks-form' => 'Bricks',
		'mint-learndash' => 'LearnDash',
		'mint-memberpress' => 'MemberPress',
		'mint-wp-forms' => 'WPForms',
		'mint-lifterlms' => 'LifterLMS',
		'mint-elementor-form' => 'Elementor Forms',
		'logical' => 'Condition',
		'mint-twilio' => 'Twilio',
		'mint-send-data' => 'Send Data',
	);

	/**
	 * Every trigger the builder registers, keyed by canonical trigger_name.
	 *
	 * @var array<string,array>
	 */
	const TRIGGERS = array(
		'edd_complete_purchase' => array(
			'label'        => 'Complete purchase',
			'description'  => 'Runs when a customer completes a purchase.',
			'category'     => 'edd',
			'package'      => 'pro',
			'requires'     => array(
				'edd',
			),
		),
		'edd_recurring_update_subscription' => array(
			'label'        => 'Update Subscription',
			'description'  => '',
			'category'     => 'edd',
			'package'      => 'pro',
			'requires'     => array(
				'edd',
			),
		),
		'edd_update_payment_status' => array(
			'label'        => 'Update payment status',
			'description'  => 'Runs when the payment status of an order changes.',
			'category'     => 'edd',
			'package'      => 'pro',
			'requires'     => array(
				'edd',
			),
		),
		'mint_add_to_segment' => array(
			'label'        => 'Added To Segment',
			'description'  => 'Automation will trigger when a contact is added to a specific segment.',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_anniversary_reminder' => array(
			'label'        => 'Anniversary',
			'description'  => 'Celebrates recurring milestones by engaging contacts on key dates.',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_clicks_a_link' => array(
			'label'        => 'Contact Clicks a Link in Email',
			'description'  => 'Run automation when a contact clicks a link in your email',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_create_contact' => array(
			'label'        => 'Contact Created',
			'description'  => 'Triggers automation when a contact is created or imported.',
			'category'     => 'mailmint',
			'package'      => 'free',
			'requires'     => array(),
		),
		'mint_form_submission' => array(
			'label'        => 'Form Submitted',
			'description'  => 'Automation will run when a contact submits a form.',
			'category'     => 'mailmint',
			'package'      => 'free',
			'requires'     => array(),
		),
		'mint_inactive_subscriber' => array(
			'label'        => 'Subscriber Win Back',
			'description'  => 'Reaches out automatically when a subscriber stops engaging',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_list_applied' => array(
			'label'        => 'Added To List',
			'description'  => 'Starts the automation when a a list applied to a contact.',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_list_removed' => array(
			'label'        => 'Removed From List',
			'description'  => 'Automation will run when a contact is removed from a selected list.',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_opens_an_email' => array(
			'label'        => 'Contact Opens an Email',
			'description'  => 'Run automation when a contact opens one of your emails',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_tag_applied' => array(
			'label'        => 'Tag Assigned',
			'description'  => 'Starts the automation when a a list applied to a contact.',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_tag_removed' => array(
			'label'        => 'Tag Removed',
			'description'  => 'Automation will run when a contact is removed from a selected list.',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'mint_webhook_received' => array(
			'label'        => 'Webhook Received',
			'description'  => 'Trigger automation when Mail Mint receives an incoming webhook.',
			'category'     => 'mailmint',
			'package'      => 'pro',
			'requires'     => array(),
		),
		'brf_pro_form_submit' => array(
			'label'        => 'After Pro Form Submission',
			'description'  => 'Triggered when a Bricksforge Pro Form is submitted.',
			'category'     => 'mint-bricks-form',
			'package'      => 'pro',
			'requires'     => array(
				'bricksforge',
			),
		),
		'bricks_form_submit' => array(
			'label'        => 'After Form Submission',
			'description'  => 'Triggered when a Bricks form is submitted.',
			'category'     => 'mint-bricks-form',
			'package'      => 'pro',
			'requires'     => array(
				'bricks',
			),
		),
		'wpcf7_submit' => array(
			'label'        => 'Form Submitted',
			'description'  => 'Automation will run when a new form has been submitted',
			'category'     => 'mint-contact-form',
			'package'      => 'pro',
			'requires'     => array(
				'contact_form_7',
			),
		),
		'elementor_form_submit' => array(
			'label'        => 'Form Submitted',
			'description'  => 'Starts the automation when an Elementor Pro form has been submitted',
			'category'     => 'mint-elementor-form',
			'package'      => 'pro',
			'requires'     => array(
				'elementor_pro',
			),
		),
		'fluentbooking_cancelled' => array(
			'label'        => 'Booking Cancelled',
			'description'  => 'This automation will be triggered when a booking is cancelled.',
			'category'     => 'mint-fluent-booking',
			'package'      => 'pro',
			'requires'     => array(
				'fluent_booking',
			),
		),
		'fluentbooking_completed' => array(
			'label'        => 'Booking Completed',
			'description'  => 'This automation will be triggered when a booking has been marked as completed (manually or automatically).',
			'category'     => 'mint-fluent-booking',
			'package'      => 'pro',
			'requires'     => array(
				'fluent_booking',
			),
		),
		'fluentbooking_new_booking' => array(
			'label'        => 'New Booking',
			'description'  => 'This automation will be triggered when a new booking has been confirmed.',
			'category'     => 'mint-fluent-booking',
			'package'      => 'pro',
			'requires'     => array(
				'fluent_booking',
			),
		),
		'fluentbooking_rescheduled' => array(
			'label'        => 'Booking Rescheduled',
			'description'  => 'This automation will be triggered when a booking has been rescheduled.',
			'category'     => 'mint-fluent-booking',
			'package'      => 'pro',
			'requires'     => array(
				'fluent_booking',
			),
		),
		'fluentform_submission_inserted' => array(
			'label'        => 'Form Submitted',
			'description'  => 'Starts the automation when a new form has been submitted',
			'category'     => 'mint-fluent-form',
			'package'      => 'pro',
			'requires'     => array(
				'fluent_forms',
			),
		),
		'gform_after_email' => array(
			'label'        => 'Confirmation Email Sent',
			'description'  => 'Starts when a confirmation email is successfully sent after form submission.',
			'category'     => 'mint-gravity-form',
			'package'      => 'pro',
			'requires'     => array(
				'gravity_forms',
			),
		),
		'gform_after_submission' => array(
			'label'        => 'Form Submitted',
			'description'  => 'Runs when a contact submits a Gravity Form.',
			'category'     => 'mint-gravity-form',
			'package'      => 'pro',
			'requires'     => array(
				'gravity_forms',
			),
		),
		'gform_send_email_failed' => array(
			'label'        => 'Confirmation Email Failed',
			'description'  => 'Runs when a confirmation email fails to deliver after form submission.',
			'category'     => 'mint-gravity-form',
			'package'      => 'pro',
			'requires'     => array(
				'gravity_forms',
			),
		),
		'jetform_after_submit' => array(
			'label'        => 'Form Submitted',
			'description'  => 'Starts the automation when a new form has been submitted',
			'category'     => 'mint-jet-form',
			'package'      => 'pro',
			'requires'     => array(
				'jetformbuilder',
			),
		),
		'jetform_before_submit' => array(
			'label'        => 'Form Abandoned',
			'description'  => 'Starts the automation when a new form has been abandoned',
			'category'     => 'mint-jet-form',
			'package'      => 'pro',
			'requires'     => array(
				'jetformbuilder',
			),
			'broken'       => 'no dispatcher',
		),
		'learndash_complete_course' => array(
			'label'        => 'Completes a Course',
			'description'  => 'This automation will start when a student completes a course.',
			'category'     => 'mint-learndash',
			'package'      => 'pro',
			'requires'     => array(
				'learndash',
			),
		),
		'learndash_complete_lesson' => array(
			'label'        => 'Completes a Lesson',
			'description'  => 'This automation will start a student completes a lesson',
			'category'     => 'mint-learndash',
			'package'      => 'pro',
			'requires'     => array(
				'learndash',
			),
		),
		'learndash_complete_topic' => array(
			'label'        => 'Completes a Topic',
			'description'  => 'This automation will start when a student completes a topic.',
			'category'     => 'mint-learndash',
			'package'      => 'pro',
			'requires'     => array(
				'learndash',
			),
		),
		'learndash_completes_quiz' => array(
			'label'        => 'Completes a Quiz',
			'description'  => 'This automation will start when a student completes a quiz.',
			'category'     => 'mint-learndash',
			'package'      => 'pro',
			'requires'     => array(
				'learndash',
			),
		),
		'learndash_enrolled_course' => array(
			'label'        => 'Enrolls in a Course',
			'description'  => 'This automation will start when a student is enrolled in a course.',
			'category'     => 'mint-learndash',
			'package'      => 'pro',
			'requires'     => array(
				'learndash',
			),
		),
		'learndash_enrolls_groups' => array(
			'label'        => 'Enrolls in a Group',
			'description'  => 'This automation will start when a user is enrolled in a group.',
			'category'     => 'mint-learndash',
			'package'      => 'pro',
			'requires'     => array(
				'learndash',
			),
		),
		'lifterlms_enrolled_course' => array(
			'label'        => 'Enrolls in a Course',
			'description'  => 'This automation will start when a student is enrolled in a course.',
			'category'     => 'mint-lifterlms',
			'package'      => 'pro',
			'requires'     => array(
				'lifterlms',
			),
		),
		'lifterlms_enrolled_membership' => array(
			'label'        => 'Enrolls in a Membership',
			'description'  => 'This automation will start when a student has been enrolled in a membership level.',
			'category'     => 'mint-lifterlms',
			'package'      => 'pro',
			'requires'     => array(
				'lifterlms',
			),
		),
		'memberpress_member_added' => array(
			'label'        => 'Added to a Membership Level',
			'description'  => 'This automation will start when a membership level get activated for a member.',
			'category'     => 'mint-memberpress',
			'package'      => 'pro',
			'requires'     => array(
				'memberpress',
			),
		),
		'memberpress_subscription_expired' => array(
			'label'        => 'Subscription Expired',
			'description'  => 'This automation will start when a subscription has been expired.',
			'category'     => 'mint-memberpress',
			'package'      => 'pro',
			'requires'     => array(
				'memberpress',
			),
		),
		'tutor_after_approved_instructor' => array(
			'label'        => 'Instructor approved',
			'description'  => 'Fires when an instructor account gets approved.',
			'category'     => 'mint-tutor-lms',
			'package'      => 'pro',
			'requires'     => array(
				'tutor_lms',
			),
		),
		'tutor_after_enrolled' => array(
			'label'        => 'After course enrollment',
			'description'  => 'Starts when a student enrolls in a course.',
			'category'     => 'mint-tutor-lms',
			'package'      => 'pro',
			'requires'     => array(
				'tutor_lms',
			),
		),
		'tutor_after_student_signup' => array(
			'label'        => 'Student registration',
			'description'  => 'Runs when a new student registers on your site.',
			'category'     => 'mint-tutor-lms',
			'package'      => 'pro',
			'requires'     => array(
				'tutor_lms',
			),
		),
		'tutor_complete_course' => array(
			'label'        => 'Completes a Course',
			'description'  => 'Starts when a student finishes a course.',
			'category'     => 'mint-tutor-lms',
			'package'      => 'pro',
			'requires'     => array(
				'tutor_lms',
			),
		),
		'tutor_complete_lesson' => array(
			'label'        => 'Completes a Lesson',
			'description'  => 'This automation will start a student completes a lesson',
			'category'     => 'mint-tutor-lms',
			'package'      => 'pro',
			'requires'     => array(
				'tutor_lms',
			),
		),
		'tutor_delete_enrollment' => array(
			'label'        => 'Enrollment removed',
			'description'  => '',
			'category'     => 'mint-tutor-lms',
			'package'      => 'pro',
			'requires'     => array(
				'tutor_lms',
			),
		),
		'wc_abandoned_cart' => array(
			'label'        => 'Abandoned Cart',
			'description'  => 'Runs when a customer leaves your store without completing their purchase.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
				'cart_tracking',
			),
		),
		'wc_abandoned_cart_lost' => array(
			'label'        => 'Cart Lost',
			'description'  => 'Runs when the recovery period ends and the abandoned cart is no longer recoverable.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
				'cart_tracking',
			),
		),
		'wc_abandoned_cart_recovered' => array(
			'label'        => 'Cart Recovered',
			'description'  => 'Starts when a customer comes back and completes their abandoned cart purchase.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
				'cart_tracking',
			),
		),
		'wc_all_order_created' => array(
			'label'        => 'New Order Placed',
			'description'  => 'Starts when any new order is placed in your store.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_customer_winback' => array(
			'label'        => 'Customer Win Back',
			'description'  => 'Bring customers back to your store by promoting your new products or offers',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_first_order' => array(
			'label'        => 'First Order In Store',
			'description'  => 'Runs when a customer places their very first order in your store.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_order_completed' => array(
			'label'        => 'Order Completed',
			'description'  => 'Runs when an order is marked as completed.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_order_created' => array(
			'label'        => 'Product Ordered',
			'description'  => 'Runs when a specific product is ordered by a customer.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_order_failed' => array(
			'label'        => 'Order Failed',
			'description'  => '',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_order_status_changed' => array(
			'label'        => 'Target Order Status',
			'description'  => 'Runs when an order reaches a specific status you define.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_price_dropped' => array(
			'label'        => 'Price Drop / On Sale',
			'description'  => 'Starts when the price drops or a product goes on sale.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wc_review_received' => array(
			'label'        => 'Review Received',
			'description'  => 'Runs when a customer leaves a review on any product.',
			'category'     => 'mint-woocommerce',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce',
			),
		),
		'wcm_membership_created' => array(
			'label'        => 'Membership Created',
			'description'  => 'This automation will start when a membership level get activated for a member.',
			'category'     => 'mint-woocommerce-membership',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_memberships',
			),
		),
		'wcm_membership_status_changed' => array(
			'label'        => 'Membership Status Changed',
			'description'  => 'This automation will trigger when the status of a subscription changes.',
			'category'     => 'mint-woocommerce-membership',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_memberships',
			),
		),
		'wcs_subscription_before_end' => array(
			'label'        => 'Subscription Before End',
			'description'  => 'Fires a set time before a subscription is about to expire.',
			'category'     => 'mint-woocommerce-subscription',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_subscriptions',
			),
		),
		'wcs_subscription_before_renewal' => array(
			'label'        => 'Subscription Before Renewal',
			'description'  => 'Runs a set time before a subscription is due for renewal.',
			'category'     => 'mint-woocommerce-subscription',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_subscriptions',
			),
		),
		'wcs_subscription_created' => array(
			'label'        => 'Subscription Created',
			'description'  => 'Runs when a new subscription is created for a customer.',
			'category'     => 'mint-woocommerce-subscription',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_subscriptions',
			),
		),
		'wcs_subscription_renewal_payment_failed' => array(
			'label'        => 'Subscription Renewal Payment Failed',
			'description'  => 'Starts when a renewal payment fails for a subscription.',
			'category'     => 'mint-woocommerce-subscription',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_subscriptions',
			),
		),
		'wcs_subscription_status_changed' => array(
			'label'        => 'Subscription Status Changed',
			'description'  => 'Runs when the status of a customer\'s subscription changes.',
			'category'     => 'mint-woocommerce-subscription',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_subscriptions',
			),
		),
		'wcs_subscription_trial_end' => array(
			'label'        => 'Subscription Trial End',
			'description'  => 'Starts when a customer\'s subscription trial period comes to an end.',
			'category'     => 'mint-woocommerce-subscription',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_subscriptions',
			),
		),
		'wcw_user_adds_product' => array(
			'label'        => 'User Adds Product To Wishlist',
			'description'  => 'Automation runs after a user adds product to wishlist.',
			'category'     => 'mint-woocommerce-wishlist',
			'package'      => 'pro',
			'requires'     => array(
				'woocommerce_wishlists',
			),
		),
		'wp_post_publish' => array(
			'label'        => 'Post Publish',
			'description'  => 'Runs when a new blog post goes live on your site.',
			'category'     => 'mint-wordpress',
			'package'      => 'free',
			'requires'     => array(),
		),
		'wp_user_login' => array(
			'label'        => 'User Login',
			'description'  => 'Starts when a user logs into your WordPress site.',
			'category'     => 'mint-wordpress',
			'package'      => 'free',
			'requires'     => array(),
		),
		'wp_user_registration' => array(
			'label'        => 'New User Registration',
			'description'  => 'Runs when a new user signs up on your WordPress site.',
			'category'     => 'mint-wordpress',
			'package'      => 'free',
			'requires'     => array(),
		),
		'wpforms_submission_inserted' => array(
			'label'        => 'Form Submitted',
			'description'  => 'Automation will run when a new form has been submitted',
			'category'     => 'mint-wp-forms',
			'package'      => 'pro',
			'requires'     => array(
				'wpforms',
			),
		),
		'funnel_downsell_accepted' => array(
			'label'        => 'Downsell Accepted',
			'description'  => 'Runs when a contact accepts a downsell offer.',
			'category'     => 'wpfunnels',
			'package'      => 'free',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_downsell_accepted',
		),
		'funnel_downsell_rejected' => array(
			'label'        => 'Downsell Rejected',
			'description'  => 'Starts when a contact declines a downsell offer.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_downsell_rejected',
		),
		'funnel_downsell_trigger' => array(
			'label'        => 'Downsell Trigger',
			'description'  => 'Runs when a contact reaches a downsell step in the funnel.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_downsell_trigger',
		),
		'funnel_optin_submitted' => array(
			'label'        => 'Optin Submitted',
			'description'  => 'Triggers when a contact submits an optin form in your funnel.',
			'category'     => 'wpfunnels',
			'package'      => 'free',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_optin_submit',
		),
		'funnel_order_bump_accepted' => array(
			'label'        => 'Order Bump Accepted',
			'description'  => 'Runs when a contact accepts an order bump offer.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_orderbump_accepted',
		),
		'funnel_order_bump_action' => array(
			'label'        => 'Order Bump Action',
			'description'  => 'Runs when a contact interacts with an order bump offer.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_orderbump_action',
		),
		'funnel_order_bump_rejected' => array(
			'label'        => 'Order Bump Rejected',
			'description'  => 'Starts when a contact declines an order bump offer.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_orderbump_rejected',
		),
		'funnel_upsell_accepted' => array(
			'label'        => 'Upsell Accepted',
			'description'  => 'Runs when a contact accepts an upsell offer.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_upsell_accepted',
		),
		'funnel_upsell_rejected' => array(
			'label'        => 'Upsell Rejected',
			'description'  => 'Starts when a contact declines an upsell offer.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_upsell_rejected',
		),
		'funnel_upsell_trigger' => array(
			'label'        => 'Upsell Trigger',
			'description'  => 'Runs when a contact reaches an upsell step in the funnel',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_upsell_trigger',
		),
		'wpf_order_placed' => array(
			'label'        => 'Checkout Order Accepted',
			'description'  => 'Starts when a contact completes a checkout order.',
			'category'     => 'wpfunnels',
			'package'      => 'pro',
			'requires'     => array(
				'wpfunnels',
			),
		),
		'wpfunnels_cta_triggered' => array(
			'label'        => 'CTA Triggered',
			'description'  => 'Runs when a contact clicks a call-to-action in your funnel.',
			'category'     => 'wpfunnels',
			'package'      => 'free',
			'requires'     => array(
				'wpfunnels',
			),
			'broken'       => 'wpf_cta_trigger',
		),
		'wpfunnels_funnel_created' => array(
			'label'        => 'Funnel Created',
			'description'  => 'Starts when a new funnel is created in WPFunnels.',
			'category'     => 'wpfunnels',
			'package'      => 'free',
			'requires'     => array(
				'wpfunnels',
			),
		),
	);

	/**
	 * All catalogued triggers.
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		return self::TRIGGERS;
	}

	/**
	 * A single trigger definition.
	 *
	 * @param string $key Canonical trigger_name.
	 * @return array|null Null when the key is not a catalogued trigger.
	 */
	public static function get( $key ) {
		return isset( self::TRIGGERS[ $key ] ) ? self::TRIGGERS[ $key ] : null;
	}

	/**
	 * Human label for a builder category.
	 *
	 * @param string $category Category key.
	 * @return string Falls back to the raw key for unknown categories.
	 */
	public static function category_label( $category ) {
		return isset( self::CATEGORIES[ $category ] ) ? self::CATEGORIES[ $category ] : $category;
	}
}
