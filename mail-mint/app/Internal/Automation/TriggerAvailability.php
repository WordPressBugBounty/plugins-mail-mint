<?php
/**
 * Resolves whether a catalogued automation trigger can actually be used on
 * THIS site, and explains why when it cannot.
 *
 * TriggerCatalog answers "does this trigger exist?" — a fact about Mail Mint.
 * This class answers "can this site use it right now?" — a fact about the
 * site. Keeping them apart is the whole point: the AI assistant used to read
 * a single list that conflated the two, so a Pro trigger on a Free site was
 * indistinguishable from a trigger that does not exist, and the assistant
 * told users Mail Mint had no Abandoned Cart trigger. Callers must be able to
 * say "that exists, here is what it needs" instead.
 *
 * @package MintMail\App\Internal\Automation
 */

namespace MintMail\App\Internal\Automation;

use MRM\Common\MrmCommon;
use Mint\MRM\Internal\AbandonedCart\Helper\CartCommon;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Runtime availability for automation triggers.
 *
 * @package MintMail\App\Internal\Automation
 */
class TriggerAvailability {

	/**
	 * Where to send a user who needs to upgrade.
	 */
	const UPGRADE_URL = 'https://getwpfunnels.com/pricing/';

	/**
	 * Requirement token => how to describe and check it.
	 *
	 * `check` names a static method on this class rather than a closure so the
	 * table stays a constant and remains cheap to reason about in tests.
	 *
	 * @var array<string,array{label:string,remedy:string,check:string}>
	 */
	const REQUIREMENTS = array(
		'woocommerce'               => array(
			'label'  => 'WooCommerce',
			'remedy' => 'Install and activate WooCommerce.',
			'check'  => 'is_wc_active',
		),
		'woocommerce_subscriptions' => array(
			'label'  => 'WooCommerce Subscriptions',
			'remedy' => 'Install and activate the WooCommerce Subscriptions extension.',
			'check'  => 'is_wcs_active',
		),
		'woocommerce_memberships'   => array(
			'label'  => 'WooCommerce Memberships',
			'remedy' => 'Install and activate the WooCommerce Memberships extension.',
			'check'  => 'is_wcm_active',
		),
		'woocommerce_wishlists'     => array(
			'label'  => 'WooCommerce Wishlists',
			'remedy' => 'Install and activate a supported WooCommerce wishlist plugin.',
			'check'  => 'is_wcw_active',
		),
		'edd'                       => array(
			'label'  => 'Easy Digital Downloads',
			'remedy' => 'Install and activate Easy Digital Downloads.',
			'check'  => 'is_edd_active',
		),
		'tutor_lms'                 => array(
			'label'  => 'Tutor LMS',
			'remedy' => 'Install and activate Tutor LMS.',
			'check'  => 'is_tutor_active',
		),
		'learndash'                 => array(
			'label'  => 'LearnDash',
			'remedy' => 'Install and activate LearnDash.',
			'check'  => 'is_learndash_active',
		),
		'lifterlms'                 => array(
			'label'  => 'LifterLMS',
			'remedy' => 'Install and activate LifterLMS.',
			'check'  => 'is_lifterlms_active',
		),
		'memberpress'               => array(
			'label'  => 'MemberPress',
			'remedy' => 'Install and activate MemberPress.',
			'check'  => 'is_memberpress_active',
		),
		'gravity_forms'             => array(
			'label'  => 'Gravity Forms',
			'remedy' => 'Install and activate Gravity Forms.',
			'check'  => 'is_gform_active',
		),
		'jetformbuilder'            => array(
			'label'  => 'JetFormBuilder',
			'remedy' => 'Install and activate JetFormBuilder.',
			'check'  => 'is_jetform_active',
		),
		'fluent_forms'              => array(
			'label'  => 'Fluent Forms',
			'remedy' => 'Install and activate Fluent Forms.',
			'check'  => 'is_fluentform_active',
		),
		'fluent_booking'            => array(
			'label'  => 'FluentBooking',
			'remedy' => 'Install and activate FluentBooking.',
			'check'  => 'is_fluent_booking_active',
		),
		'contact_form_7'            => array(
			'label'  => 'Contact Form 7',
			'remedy' => 'Install and activate Contact Form 7.',
			'check'  => 'is_contact_form_7_active',
		),
		'wpforms'                   => array(
			'label'  => 'WPForms',
			'remedy' => 'Install and activate WPForms.',
			'check'  => 'is_wpforms_active',
		),
		'bricks'                    => array(
			'label'  => 'Bricks',
			'remedy' => 'Activate the Bricks theme.',
			'check'  => 'is_bricks_active',
		),
		'bricksforge'               => array(
			'label'  => 'Bricksforge',
			'remedy' => 'Install and activate Bricksforge.',
			'check'  => 'is_bricksforge_active',
		),
		'elementor_pro'             => array(
			'label'  => 'Elementor Pro',
			'remedy' => 'Install and activate Elementor Pro.',
			'check'  => 'is_elementor_pro_active',
		),
		'wpfunnels'                 => array(
			'label'  => 'WPFunnels',
			'remedy' => 'Install and activate WPFunnels.',
			'check'  => 'is_wpf_active',
		),
		'cart_tracking'             => array(
			'label'  => 'Abandoned cart tracking',
			'remedy' => 'Turn on cart tracking in Mail Mint → Settings → Abandoned Cart.',
			'check'  => 'is_cart_tracking_enabled',
		),
	);

	/**
	 * Availability of every catalogued trigger.
	 *
	 * @return array<string,array> Trigger key => catalogue entry merged with its
	 *                             availability verdict.
	 */
	public static function all() {
		$resolved = array();
		foreach ( TriggerCatalog::all() as $key => $definition ) {
			$resolved[ $key ] = array_merge(
				$definition,
				array( 'availability' => self::resolve( $key, $definition ) )
			);
		}
		return $resolved;
	}

	/**
	 * Availability of one trigger.
	 *
	 * @param string $key Canonical trigger_name.
	 * @return array{status:string,available:bool,reason:string,missing:array,remedies:array,upgrade_url?:string}|null
	 *         Null when the key is not a catalogued trigger at all.
	 */
	public static function for_trigger( $key ) {
		$definition = TriggerCatalog::get( $key );
		if ( null === $definition ) {
			return null;
		}
		return self::resolve( $key, $definition );
	}

	/**
	 * Whether a trigger is usable right now.
	 *
	 * @param string $key Canonical trigger_name.
	 * @return bool
	 */
	public static function is_available( $key ) {
		$availability = self::for_trigger( $key );
		return is_array( $availability ) && $availability['available'];
	}

	/**
	 * Works out the verdict for one catalogue entry.
	 *
	 * Requirements are reported in full rather than short-circuiting on the
	 * first failure: "needs Pro" alone is misleading advice for a Free site
	 * that also has no WooCommerce installed.
	 *
	 * @param string $key        Canonical trigger_name.
	 * @param array  $definition Catalogue entry.
	 * @return array
	 */
	private static function resolve( $key, array $definition ) {
		$missing  = array();
		$remedies = array();

		if ( 'pro' === $definition['package'] ) {
			if ( ! MrmCommon::is_mailmint_pro_active() ) {
				$missing[]  = 'Mail Mint Pro';
				$remedies[] = 'Install and activate Mail Mint Pro.';
			} elseif ( ! MrmCommon::is_mailmint_pro_license_active() ) {
				$missing[]  = 'an active Mail Mint Pro license';
				$remedies[] = 'Activate your Mail Mint Pro license.';
			}
		}

		$needs_pro = ! empty( $missing );

		foreach ( $definition['requires'] as $token ) {
			if ( ! isset( self::REQUIREMENTS[ $token ] ) ) {
				continue;
			}
			$requirement = self::REQUIREMENTS[ $token ];
			$check       = array( self::class, $requirement['check'] );
			if ( is_callable( $check ) && ! call_user_func( $check ) ) {
				$missing[]  = $requirement['label'];
				$remedies[] = $requirement['remedy'];
			}
		}

		if ( empty( $missing ) ) {
			// A trigger nothing dispatches is worse than an unavailable one: it
			// builds cleanly and then never fires. Surface it as its own status
			// so callers warn instead of recommending it.
			if ( ! empty( $definition['broken'] ) ) {
				return array(
					'status'    => 'not_dispatched',
					'available' => false,
					'reason'    => sprintf(
						'The "%s" trigger appears in the automation builder but no Mail Mint code fires it, so an automation using it would never run. This is a known bug, not a setup problem.',
						$definition['label']
					),
					'missing'   => array(),
					'remedies'  => array( 'Pick a different trigger and report the broken one to Mail Mint support.' ),
				);
			}

			return array(
				'status'    => 'available',
				'available' => true,
				'reason'    => '',
				'missing'   => array(),
				'remedies'  => array(),
			);
		}

		$verdict = array(
			'status'    => $needs_pro ? 'requires_upgrade' : 'requires_setup',
			'available' => false,
			'reason'    => sprintf(
				'The "%1$s" trigger exists in Mail Mint but is not usable on this site yet — it needs: %2$s.',
				$definition['label'],
				implode( ', ', $missing )
			),
			'missing'   => $missing,
			'remedies'  => $remedies,
		);

		if ( $needs_pro ) {
			$verdict['upgrade_url'] = self::UPGRADE_URL;
		}

		return $verdict;
	}

	/* --------------------------------------------------------------------- *
	 * Requirement checks
	 *
	 * Thin wrappers over the same helpers AdminAssets uses to build the
	 * `MRM_Vars.is_*_active` flags the builder gates on, so PHP and the builder
	 * cannot disagree about what is installed.
	 * --------------------------------------------------------------------- */

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_wc_active() {
		return (bool) HelperFunctions::is_wc_active();
	}

	/**
	 * Whether WooCommerce Subscriptions is active.
	 *
	 * @return bool
	 */
	public static function is_wcs_active() {
		return (bool) HelperFunctions::is_woocommerce_subscription_active();
	}

	/**
	 * Whether WooCommerce Memberships is active.
	 *
	 * @return bool
	 */
	public static function is_wcm_active() {
		return (bool) HelperFunctions::is_woocommerce_membership_active();
	}

	/**
	 * Whether a supported WooCommerce wishlist plugin is active.
	 *
	 * @return bool
	 */
	public static function is_wcw_active() {
		return (bool) HelperFunctions::is_woocommerce_wishlist_active();
	}

	/**
	 * Whether Easy Digital Downloads is active.
	 *
	 * @return bool
	 */
	public static function is_edd_active() {
		return (bool) HelperFunctions::is_edd_active();
	}

	/**
	 * Whether Tutor LMS is active.
	 *
	 * @return bool
	 */
	public static function is_tutor_active() {
		return (bool) HelperFunctions::is_tutor_active();
	}

	/**
	 * Whether LearnDash is active.
	 *
	 * @return bool
	 */
	public static function is_learndash_active() {
		return (bool) HelperFunctions::is_learndash_lms_active();
	}

	/**
	 * Whether LifterLMS is active.
	 *
	 * @return bool
	 */
	public static function is_lifterlms_active() {
		return (bool) HelperFunctions::is_lifter_lms_active();
	}

	/**
	 * Whether MemberPress is active.
	 *
	 * @return bool
	 */
	public static function is_memberpress_active() {
		return (bool) HelperFunctions::is_memberpress_active();
	}

	/**
	 * Whether Gravity Forms is active.
	 *
	 * @return bool
	 */
	public static function is_gform_active() {
		return (bool) HelperFunctions::is_gform_active();
	}

	/**
	 * Whether JetFormBuilder is active.
	 *
	 * @return bool
	 */
	public static function is_jetform_active() {
		return (bool) HelperFunctions::is_jetform_active();
	}

	/**
	 * Whether Fluent Forms is active.
	 *
	 * @return bool
	 */
	public static function is_fluentform_active() {
		return (bool) HelperFunctions::is_fluentform_active();
	}

	/**
	 * Whether FluentBooking is active.
	 *
	 * @return bool
	 */
	public static function is_fluent_booking_active() {
		return (bool) HelperFunctions::is_fluent_booking_active();
	}

	/**
	 * Whether Contact Form 7 is active.
	 *
	 * @return bool
	 */
	public static function is_contact_form_7_active() {
		return (bool) HelperFunctions::is_contact_form_7_active();
	}

	/**
	 * Whether WPForms is active.
	 *
	 * @return bool
	 */
	public static function is_wpforms_active() {
		return (bool) HelperFunctions::is_wp_form_active();
	}

	/**
	 * Whether the Bricks theme is active.
	 *
	 * @return bool
	 */
	public static function is_bricks_active() {
		return (bool) HelperFunctions::is_bricks_active();
	}

	/**
	 * Whether Bricksforge is active.
	 *
	 * @return bool
	 */
	public static function is_bricksforge_active() {
		return (bool) HelperFunctions::is_bricksforge_active();
	}

	/**
	 * Whether Elementor Pro is active.
	 *
	 * @return bool
	 */
	public static function is_elementor_pro_active() {
		return (bool) HelperFunctions::is_elementor_pro_active();
	}

	/**
	 * Whether WPFunnels is active.
	 *
	 * @return bool
	 */
	public static function is_wpf_active() {
		return (bool) MrmCommon::is_wpfnl_active();
	}

	/**
	 * Whether abandoned cart tracking is switched on.
	 *
	 * The builder hides the cart triggers unless tracking is enabled, because a
	 * cart automation on a site that records no carts never fires.
	 *
	 * @return bool
	 */
	public static function is_cart_tracking_enabled() {
		if ( ! self::is_wc_active() || ! class_exists( CartCommon::class ) ) {
			return false;
		}
		$settings = CartCommon::get_abandoned_cart_settings();
		return is_array( $settings ) && ! empty( $settings['enable'] );
	}
}
