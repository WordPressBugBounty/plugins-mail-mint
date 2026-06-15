<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin
 */

namespace Mint\MRM\Internal\Admin;

use MailMintPro\App\Utilities\Helper\Integration;
use MailMintPro\Mint\Internal\AbandonedCart\Helper\Common;
use Mint\MRM\Internal\Constants;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Utilites\Helper\Email;
use Mint\MRM\Utilities\Helper\PermissionManager;
use Mint\MRM\Utilities\Helper\TranslationString\TransStrings;
use Mint\MRM\Internal\Optin\UnsubscribeReasons;
use Mint\Utilities\CustomFonts;
use MintMail\App\Internal\Automation\HelperFunctions;
use MintMailPro\Mint_Pro_Helper;
use MRM\Common\MrmCommon;

/**
 * [Manage plugin's admin assets]
 *
 * @desc Manage plugin's admin assets
 * @package /app/Internal/Admin
 * @since 1.0.0
 */
class AdminAssets {

    use Singleton;

    /**
     * Initialize the plugin functionalities
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_all_scripts' ] );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }


    /**
     * Register all scripts and styles.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register_all_scripts() {
        $this->register_styles( $this->get_styles() );
        $this->register_scripts( $this->get_scripts() );
    }


    /**
     * Add a button in a core button that is disabled by default
     *
     * @param array $buttons First-row list of TinyMCE buttons.
     * @return mixed
     * @since 1.0.0
     */
    public function my_mce_buttons_2( array $buttons ) {
        $buttons[] = 'wdm_mce_button';

        return $buttons;
    }


    /**
     * Get all styles.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function get_styles() {

        $styles = array(
            'mint-mail-css' => [
                'src'     => MRM_DIR_URL . 'assets/admin/dist/main.css',
                'version' => MRM_VERSION,
                'deps'    => [],
            ],
            'mint-mail-automation-editor-css' => [
                'src'     => MRM_DIR_URL . 'assets/admin/dist/automation_editor.css',
                'version' => MRM_VERSION,
                'deps'    => [],
            ],
        );

        if ( MINT_DEV_MODE ) {
            $styles['mint-mail-automation-editor-css'] = [
                'src'     => MRM_DIR_URL . 'assets/admin/dist/automation_editor.css',
                'version' => MRM_VERSION,
                'deps'    => [],
            ];
        }

        return $styles;
    }

    /**
     * Get all scripts.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function get_scripts() {
        $dependency         = require_once MRM_DIR_PATH . '/assets/admin/dist/main/index.min.asset.php';
        $editor_dependency  = require_once MRM_DIR_PATH . '/assets/admin/dist/automation_editor/index.min.asset.php';

        $scripts = [
            'mail-mint-automation-editor' => [
                'src'     => MRM_DIR_URL . 'assets/admin/dist/automation_editor/index.min.js',
                'version'   => MRM_VERSION,
                'deps'      => $editor_dependency['dependencies'],
                'in_footer' => true,
            ],
            'mail-mint-js' => [
                'src'     => MRM_DIR_URL . 'assets/admin/dist/main/index.min.js',
                'version'   => MRM_VERSION,
                'deps'      => $dependency['dependencies'],
                'in_footer' => true,
            ],
        ];

        if ( MINT_DEV_MODE ) {
            $scripts['mint-mail-automation-editor'] = [
                'src'       => MRM_DIR_URL . 'assets/admin/dist/vendors-src_components_Editor_Editor_tsx.min.js',
                'version'   => MRM_VERSION,
                'deps'      => [],
                'in_footer' => true,
            ];

        }

        return $scripts;
    }


    /**
     * Register styles.
     *
     * @param $styles
     *
     * @since 1.0.0
     */
    public function register_styles( $styles ) {
        foreach ( $styles as $handle => $style ) {
            wp_register_style( $handle, $style['src'], $style['deps'], $style['version'] );
        }
    }

    /**
     * Register scripts.
     *
     * @param $scripts
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register_scripts( $scripts ) {
        foreach ( $scripts as $handle => $script ) {
            wp_register_script( $handle, $script['src'], $script['deps'], $script['version'], $script['in_footer'] );
        }
    }


    /**
     * Enqueue admin styles and scripts.
     *
     * @param $hook
     * @return bool
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook) {
        wp_enqueue_script(
			MRM_PLUGIN_NAME,
			MRM_DIR_URL . 'assets/admin/js/admin.js',
			array( 'jquery' ),
			MRM_VERSION,
			true
		);

        wp_localize_script(
			MRM_PLUGIN_NAME,
			'mrm_admin_ajax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'promotional_banner_nonce' ),
			)
		);

        if ( ! $this->maybe_mrm_page( $hook ) ) {
            return false;
        }

	    $default         = array(
		    'business_name' => '',
		    'phone'         => '',
		    'business_address' => array(
                'address_line_1' => '',
                'postal'         => '',
                'city'           => '',
                'address_line_2' => '',
                'country'        => '',
                'state'          => '',
            ),
		    'logo_url'      => '',
	    );

        $recaptcha_default = MrmCommon::recaptcha_default_configuration();
        $wc_active         = MrmCommon::is_wc_active();

        $custom_fonts = new CustomFonts();
        $custom_fonts->enqueue_style();

        // Broadcasts.
        wp_tinymce_inline_scripts();
        wp_enqueue_editor();
        if ( 'toplevel_page_mrm-admin' === $hook ) {

            

            // Enqueue wp media.
            wp_enqueue_media();

            wp_enqueue_style( 'mint-mail-css' );
            wp_enqueue_style( 'mint-mail-email-editor' );
            wp_enqueue_script( 'mail-mint-js' );

            // Admin Name & Email Finding Starts
            $admin_email = get_option('admin_email');
            $admin_user = get_user_by('email', $admin_email);
            $admin_name = $admin_user ? $admin_user->display_name : '';
            
            // Get current user email.
            $current_user       = wp_get_current_user();
            $current_user_email = $current_user->user_email;

	        wp_localize_script(
		        'mail-mint-js',
		        'MRM_Vars',
		        array(
			        'ajaxurl'                        => admin_url( 'admin-ajax.php' ),
			        'api_base_url'                   => get_rest_url(),
			        'nonce'                          => wp_create_nonce( 'wp_rest' ),
			        'current_userID'                 => get_current_user_id(),
			        'editor_data_source'             => $this->get_editor_source(),
			        'admin_url'                      => get_admin_url(),
			        'email_settings'                 => get_option( '_mrm_email_settings', Email::default_email_settings() ),
			        'is_wc_active'                   => $wc_active,
			        'start_of_week'                  => get_option( 'start_of_week', 1 ),
			        'unsubscribe_url'                => home_url(),
			        'preference_url'                 => home_url(),
			        'business_basic_settings'        => get_option( '_mrm_business_basic_info_setting', $default ),
			        'business_social_settings'       => get_option( '_mrm_business_social_info_setting', array( 'socialMedia' => array() ) ),
			        'date_format'                    => get_option( 'date_format', 'F j, Y' ),
			        'time_format'                    => get_option( 'time_format', 'g:i a' ),
			        'local_time'                     => date_i18n( 'Y-m-d H:i:s' ),
			        'timezone'                       => MrmCommon::get_site_timezone_info(),
			        'site_url'                       => site_url(),
			        'currency_format'                => $wc_active ? html_entity_decode( get_woocommerce_currency_symbol() ) : '',
			        'is_mailmint_pro_active'         => MrmCommon::is_mailmint_pro_active(),
			        'is_mailmint_pro_license_active' => MrmCommon::is_mailmint_pro_license_active(),
			        'is_edd_active'                  => HelperFunctions::is_edd_active(),
                    'contacts_map_attrs'             => MrmCommon::import_contacts_map_attrs(),
                    'open_ai_key'                    => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.15.2') ? Integration::get_open_ai_secret_key() : array(),
                    'recaptcha_settings'             => get_option( '_mint_recaptcha_settings', $recaptcha_default ),
                    'exist_contact_field'            => Constants::get_exsiting_fields_array(),
                    'contact_general_fields'         => MrmCommon::get_contact_general_fields(),
                    'cart_settings'                  => $wc_active && MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.5.0' ) ? Common::get_abandoned_cart_settings() : array(),
                    'images_url'                     => plugins_url( 'Email-Templates/images', __FILE__ ) . '/',
                    'mint_page'                      => 'campaign',
                    'is_learndash_active'            => HelperFunctions::is_learndash_lms_active(),
                    'is_tutor_active'                => HelperFunctions::is_tutor_active(),
                    'is_memberpress_active'          => HelperFunctions::is_memberpress_active(),
                    'mint_trans'                     => TransStrings::getStrings(),
                    'total_batches'                  => MrmCommon::get_total_batches(),
                    'wp_uuid4'                       => wp_generate_uuid4(),
                    'is_lifterlms_active'            => HelperFunctions::is_lifter_lms_active(),
                    'admin_img_url'                  => MRM_DIR_URL.'admin/assets/images',
                    'admin_name'                     => $admin_name,
                    'admin_email'                    => $admin_email,
                    'address'                        => MrmCommon::get_business_full_address(),
                    'current_user_email'             => $current_user_email,
                    'current_user_name'              => $current_user->display_name,
                    'current_user_first_name'        => $current_user->first_name,
                    'bounce_configs'                 => MrmCommon::get_bounce_configs(),
                    'is_wcs_active'                  => HelperFunctions::is_woocommerce_subscription_active(),
                    'is_wcm_active'                  => HelperFunctions::is_woocommerce_membership_active(),
                    'permissions'                    => PermissionManager::get_readable_permissions(),
                    'is_wcw_active'                  => HelperFunctions::is_woocommerce_wishlist_active(),
                    'is_fluent_booking_active'       => HelperFunctions::is_fluent_booking_active(),
                    'is_mailpoet_active'             => HelperFunctions::is_mailpoet_active(),
                    'is_wc_st_active'                => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.18.6') ? Mint_Pro_Helper::is_wc_shipment_tracking_active() : false,
                    'is_wc_ads_active'               => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.18.6') ? Mint_Pro_Helper::is_wc_advanced_shipment_tracking_active() : false,
                    'is_zerobounce_connected'        => MrmCommon::is_mailmint_pro_active() && method_exists('MailMintPro\App\Utilities\Helper\Integration', 'is_zero_bounce_connected') ? Integration::is_zero_bounce_connected() : false,
                    'is_wpf_active'                  => MrmCommon::is_wpfnl_active(),
                    'smtp_notice'                    => MrmCommon::find_active_smtp_plugin(),
                    'mint_mail_home_url'             => home_url(),
                    'unsubscribe_reasons'            => UnsubscribeReasons::get_reasons(),
		        )
	        );
        }

        if ( 'mail-mint_page_mint-mail-automation-editor' === $hook ) {
            wp_enqueue_style( 'mint-mail-automation-editor-css' );
            
            // Enqueue wp media.
            wp_enqueue_media();

            wp_enqueue_script( 'mail-mint-vendor' );
            wp_enqueue_script( 'mail-mint-mjml' );
            wp_enqueue_script( 'mail-mint-automation-editor' );

            // Get current user email.
            $current_user       = wp_get_current_user();
            $current_user_email = $current_user->user_email;

	        wp_localize_script(
		        'mail-mint-automation-editor',
		        'MRM_Vars',
		        array(
			        'ajaxurl'                        => admin_url( 'admin-ajax.php' ),
			        'api_base_url'                   => get_rest_url(),
			        'nonce'                          => wp_create_nonce( 'wp_rest' ),
			        'current_userID'                 => get_current_user_id(),
			        'editor_data_source'             => $this->get_editor_source(),
			        'admin_url'                      => get_admin_url(),
			        'email_settings'                 => get_option( '_mrm_email_settings', Email::default_email_settings() ),
			        'is_wc_active'                   => HelperFunctions::is_wc_active(),
			        'business_basic_settings'        => get_option( '_mrm_business_basic_info_setting', $default ),
			        'business_social_settings'       => get_option( '_mrm_business_social_info_setting', array( 'socialMedia' => array() ) ),
			        'date_format'                    => get_option( 'date_format', 'F j, Y' ),
			        'time_format'                    => get_option( 'time_format', 'H:i' ),
			        'gmt_offset'                     => get_option('gmt_offset'),
			        'admin_email'                    => get_bloginfo( 'admin_email' ),
			        'sequences'                      => HelperFunctions::get_sequences(),
			        'is_mailmint_pro_active'         => MrmCommon::is_mailmint_pro_active(),
			        'is_mailmint_pro_license_active' => MrmCommon::is_mailmint_pro_license_active(),
			        'is_edd_active'                  => HelperFunctions::is_edd_active(),
			        'is_tutor_active'                => HelperFunctions::is_tutor_active(),
			        'is_gform_active'                => HelperFunctions::is_gform_active(),
			        'get_gforms'                     => HelperFunctions::get_gform_forms(),
                    'is_jetform_active'              => HelperFunctions::is_jetform_active(),
			        'get_mailmint_forms'             => HelperFunctions::get_mailmint_forms(),
                    'open_ai_key'                    => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.15.2') ? Integration::get_open_ai_secret_key() : array(),
                    'is_fluentform_active'           => HelperFunctions::is_fluentform_active(),
                    'contact_general_fields'         => MrmCommon::get_contact_general_fields(),
                    'cart_settings'                  => $wc_active && MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.5.0' ) ? Common::get_abandoned_cart_settings() : array(),
                    'images_url'                     => plugins_url( 'Email-Templates/images', __FILE__ ) . '/',
                    'mint_page'                      => 'automation',
                    'is_contact_form_active'         => HelperFunctions::is_contact_form_7_active(),
                    'is_learndash_active'            => HelperFunctions::is_learndash_lms_active(),
                    'is_memberpress_active'          => HelperFunctions::is_memberpress_active(),
                    'mint_trans'                     => TransStrings::getStrings(),
                    'wc_order_statuses'              => HelperFunctions::get_woocommerce_order_statuses(),
                    'twilio_settings'                => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.9.3' ) ? Common::get_twilio_settings() : array(),
                    'local_time'                     => date_i18n( 'Y-m-d H:i:s' ),
                    'timezone'                       => MrmCommon::get_site_timezone_info(),
                    'date_fields'                    => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.12.0' ) ? Common::get_date_fields() : array(),
                    'is_lifterlms_active'            => HelperFunctions::is_lifter_lms_active(),
                    'is_bricks_active'               => HelperFunctions::is_bricks_active(),
                    'is_wcs_active'                  => HelperFunctions::is_woocommerce_subscription_active(),
                    'wcs_order_statuses'             => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.15.0' ) ? Mint_Pro_Helper::get_wcs_order_statuses() : false,
                    'is_wcm_active'                  => HelperFunctions::is_woocommerce_membership_active(),
                    'wcm_plans'                      => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.15.0' ) ? Mint_Pro_Helper::get_wcm_plans() : false,
                    'wcm_plan_statuses'              => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible( '1.15.0' ) ? Mint_Pro_Helper::get_wcm_plan_statuses() : false,
                    'is_wcw_active'                  => HelperFunctions::is_woocommerce_wishlist_active(),
                    'contacts_map_attrs'             => MrmCommon::import_contacts_map_attrs(),
                    'address'                        => MrmCommon::get_business_full_address(),
                    'current_user_email'             => $current_user_email,
                    'condition_fields'               => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.16.1') ? Mint_Pro_Helper::get_automation_condition_fields() : false,
                    'is_wp_form_active'              => HelperFunctions::is_wp_form_active(),
                    'text_fields'                    => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.17.2') ? Mint_Pro_Helper::get_text_type_fields() : false,
                    'is_fluent_booking_active'       => HelperFunctions::is_fluent_booking_active(),
                    'is_wc_st_active'                => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.18.6') ? Mint_Pro_Helper::is_wc_shipment_tracking_active() : false,
                    'is_wc_ads_active'               => MrmCommon::is_mailmint_pro_active() && MrmCommon::is_mailmint_pro_version_compatible('1.18.6') ? Mint_Pro_Helper::is_wc_advanced_shipment_tracking_active() : false,
                    'is_wpf_active'                  => MrmCommon::is_wpfnl_active(),
                    'is_elementor_pro_active'        => HelperFunctions::is_elementor_pro_active(),
		        )
	        );
        }

    }



    /**
     * Check if the current page is CRM page or not
     *
     * @param string $hook Hook suffix of current admin page.
     *
     * @return bool
     * @since 1.0.0
     */
    private function maybe_mrm_page( string $hook ) {
        return 'toplevel_page_mrm-admin' === $hook || 'mail-mint_page_mint-mail-automation-editor' === $hook;
    }



    /**
     * Get editor source data
     *
     * @return array
     * @since 1.0.0
     */
    private function get_editor_source() {
        // get product categories for email builder.
        $wc_categories = $this->get_formatted_wc_categories();
        $wp_categories = $this->get_formatted_wp_post_categories();

        return apply_filters(
            'plugin_hook_name',
            array(
                'product_categories' => $wc_categories,
                'post_categories'    => $wp_categories,
                'placeholder_image'  => MRM_DIR_URL . 'admin/assets/images/mint-placeholder.png'
            )
        );
    }



    /**
     * Get the WooCommerce product categories
     *
     * @return array
     * @since 1.0.0
     */
    private function get_formatted_wc_categories() {
        $taxonomy     = 'product_cat';
        $orderby      = 'name';
        $show_count   = 0;
        $pad_counts   = 0;
        $hierarchical = 1;
        $title        = '';
        $empty        = 0;

        $args               = array(
            'taxonomy'     => $taxonomy,
            'orderby'      => $orderby,
            'show_count'   => $show_count,
            'pad_counts'   => $pad_counts,
            'hierarchical' => $hierarchical,
            'title_li'     => $title,
            'hide_empty'   => $empty,
        );
        $product_categories = get_categories( $args );
        $wc_categories      = array();
        foreach ( $product_categories as $product_cat ) {
            $wc_categories[] = array(
                'value' => $product_cat->term_id,
                'label' => $product_cat->name,
            );
        }

        return $wc_categories;
    }


    /**
     * Get the WordPress post categories
     *
     * @return array
     * @since 1.0.0
     */
    private function get_formatted_wp_post_categories() {
        $taxonomy     = 'category';
        $orderby      = 'name';
        $show_count   = 0;
        $pad_counts   = 0;
        $hierarchical = 1;
        $title        = '';
        $empty        = 0;

        $args               = array(
            'taxonomy'     => $taxonomy,
            'orderby'      => $orderby,
            'show_count'   => $show_count,
            'pad_counts'   => $pad_counts,
            'hierarchical' => $hierarchical,
            'title_li'     => $title,
            'hide_empty'   => $empty,
        );
        $post_categories = get_categories( $args );
        $categories      = array();
        foreach ( $post_categories as $post_cat ) {
            $categories[] = array(
                'value' => $post_cat->term_id,
                'label' => $post_cat->name,
            );
        }
        return $categories;
    }
}