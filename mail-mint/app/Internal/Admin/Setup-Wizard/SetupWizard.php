<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin/Setup-Wizard
 */

namespace Mint\MRM\Internal\Admin;

/**
 * Manages Setup Wizard
 *
 * @package /app/Internal/Admin/Setup-Wizard
 * @since 1.0.0
 * @since 1.13.0 Added redirect_to_setup_wizard method
 */
class SetupWizard {

	public function __construct() {
        $this->init();
    }

	/**
	 * Initialize the class
	 * 
	 * @since 1.13.0
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'redirect_to_setup_wizard' ) );
	}

	/**
	 * Redirect to setup wizard
	 * 
	 * @since 1.13.0
	 */
	public function redirect_to_setup_wizard() {
		// Setup wizard redirect.
        if (get_transient('mailmint_show_setup_wizard')) {
            $do_redirect = true;

            // On these pages, or during these events, postpone the redirect.
            if (wp_doing_ajax() || is_network_admin() || !current_user_can('manage_options')) {
                $do_redirect = false;
            }

            if ( $do_redirect ) {
				update_option('mail_mint_hide_database_update_notice', 'yes');
                delete_transient('mailmint_show_setup_wizard');
                $url = admin_url('admin.php?page=mrm-admin#/setup-wizard');
                wp_safe_redirect(  wp_sanitize_redirect( esc_url_raw( $url ) ) );
                exit;
            }
        }
	}
}