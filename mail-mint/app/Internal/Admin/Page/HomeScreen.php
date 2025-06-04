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

namespace Mint\MRM\Internal\Admin\Page;

use Mint\MRM\Internal\Admin\Notices\DBUpgradeNotice;
use Mint\MRM\Internal\Admin\SpecialOccasionBanner;
use Mint\Mrm\Internal\Traits\Singleton;
use Mint\MRM\Utilities\Helper\PermissionManager;
use MRM\Common\MrmCommon;

/**
 * [Register plugin menus]
 *
 * @desc Register plugin menus
 * @package /app/Internal/Admin
 * @since 1.0.0
 */
class HomeScreen {

	use Singleton;

	const MENU_SLUG = 'mrm-admin';


	/**
	 * [Initialize class functionalities]
	 *
	 * @desc Initialize class functionalities
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_head', function() {
			remove_submenu_page( self::MENU_SLUG, 'mint-mail-automation-editor' );
		} );
        // add_action('activated_plugin', array($this, 'mint_admin_redirects'));

        add_filter( 'plugin_action_links_' . MAILMINT_BASE_NAME, [ $this, 'plugin_action_links' ] );
        add_action( 'admin_head', array( $this,'hide_update_noticee_to_mailmint' ), 1 );
		add_action("wp_ajax_mint_delete_promotional_banner", array( $this, 'mint_delete_promotional_banner' ));
    }



	/**
	 * [Register menus]
	 *
	 * @desc Register menus
	 * @since 1.0.0
	 */
	public function register_page() {
		$permissions = PermissionManager::current_user_permissions();

		if (!$permissions) {
			return;
		}

		$dashboard_permission = 'mint_view_dashboard';
		$is_admin = false;
		if (in_array('administrator', $permissions)) {
			$dashboard_permission = 'manage_options';
			$is_admin = true;
		}

		add_menu_page(
			__( 'Mail Mint', 'mrm' ),
			__( 'Mail Mint', 'mrm' ),
			$dashboard_permission,
			self::MENU_SLUG,
			array( $this, 'load_wrapper' ),
			$this->get_menu_icon(),
			2
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'mrm' ),
			__( 'Dashboard', 'mrm' ),
			$dashboard_permission,
			self::MENU_SLUG,
			array( $this, 'load_wrapper' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Contacts', 'mrm' ),
			__( 'Contacts', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_read_contacts',
			'mrm-admin#/contacts/',
			array( $this, 'load_wrapper' )
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Campaigns', 'mrm' ),
			__( 'Campaigns', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_read_campaigns',
			'mrm-admin#/campaigns/regular',
			array($this, 'load_wrapper')
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Automations', 'mrm' ),
			__( 'Automations', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_read_automations',
			'mrm-admin#/automations/',
			array($this, 'load_wrapper_automation_editor'),
		);
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Automation', 'mrm' ),
			__( 'Automation', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_read_automations',
			'mint-mail-automation-editor',
			array($this, 'load_wrapper_automation_editor'),
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Forms', 'mrm' ),
			__( 'Forms', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_read_forms',
			'mrm-admin#/forms/',
			array(
				$this,
				'load_wrapper',
			)
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Email Templates', 'mrm' ),
			__( 'Email Templates', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_manage_email_templates',
			'mrm-admin#/email-templates/',
			array(
				$this,
				'load_wrapper',
			)
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tools', 'mrm' ),
			__( 'Tools', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_read_tools',
			'mrm-admin#/tools/link-triggers/',
			array(
				$this,
				'load_wrapper',
			)
		);

		if( MrmCommon::is_wc_active() ){
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Abandoned Cart', 'mrm' ),
				__( 'Abandoned Cart', 'mrm' ),
				($is_admin) ? $dashboard_permission : 'mint_manage_settings',
				'mrm-admin#/abandoned-cart/',
				array(
					$this,
					'load_wrapper',
				)
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Integrations', 'mrm' ),
			__( 'Integrations', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_manage_integrations',
			'mrm-admin#/integrations/',
			array(
				$this,
				'load_wrapper',
			)
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'mrm' ),
			__( 'Settings', 'mrm' ),
			($is_admin) ? $dashboard_permission : 'mint_manage_settings',
			'mrm-admin#/settings/business-info/',
			array(
				$this,
				'load_wrapper',
			)
		);
	}

	public function mint_delete_promotional_banner( $payload ){
		check_ajax_referer( 'promotional_banner_nonce', 'nonce' );
		update_option('_is_mint_adha_25', 'no' );
		return [
            'success' => true,
        ];
    }

	/**
     * Redirects the user to the Mail Mint setup wizard or the Mail Mint admin page based on the presence of a transient.
     *
	 * @param string $plugin The path of the plugin being activated or deactivated.
	 * @return void
	 *
     * @since 1.0.0
	 */
	public function mint_admin_redirects( $plugin) {
		if ( sanitize_text_field( $plugin ) === plugin_basename( 'mail-mint/mail-mint.php' ) ) {
			$url = admin_url('/admin.php?page=mrm-admin#/setup-wizard/welcome');
			$url = esc_url($url, FILTER_SANITIZE_URL);
			if ( 'yes' === get_transient('mailmint_show_setup_wizard') ) {
				delete_transient('mailmint_show_setup_wizard');
				// wp_redirect($url);
    			// exit;
			}else{
				// wp_redirect(admin_url('/admin.php?page=mrm-admin'));
    			// exit;
			}
		}
	}


	public function load_wrapper_automation_editor() {
        ?>
        <div class="mrm-app-wrapper" style="display: block; ">
            <div id="mrm-app-editor" class="mintmrm"></div>
        </div>
        <?php
    }


	/**
	 * [Loads plugin default wrapper]
	 *
	 * @desc Loads plugin default wrapper
	 * @return void
	 * @since 1.0.0
	 */
	public function load_wrapper() {
		?>
		<div class="crm-app-wrapper" style="display: block; ">
			<div id="mint-mail-app"></div>
			<div id="crm-portal"></div>
		</div>
		<?php
	}

	/**
	 * Gets the SVG icon for menu
	 *
	 * @desc Gets the SVG icon for menu
	 * @return string
	 * @since 1.0.0
	 */
	private function get_menu_icon() {
		return 'data:image/svg+xml;base64,' . base64_encode(        //phpcs:ignore
			'<?xml version="1.0" encoding="utf-8"?>
			<!-- Generator: Adobe Illustrator 27.1.1, SVG Export Plug-In . SVG Version: 6.00 Build 0)  -->
			<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
				viewBox="0 0 22 18" style="enable-background:new 0 0 22 18;" xml:space="preserve">
			<style type="text/css">
				.st0{fill:#AFA9FF;}
				.st1{fill:#FF810D;}
				.st2{fill:#FFFFFF;}
			</style>
			<path class="st0" d="M19.2,6.7l-0.7,9.7l1-1.4l1.1-11.3l-10.2,6.8L8.1,8.1L1.5,2.5l8.4,9.8c0.3,0.3,0.7,0.4,1.1,0.1L19.2,6.7z"/>
			<path class="st1" d="M16.7,3.1c-4.3,0.9-8,1-9.3,0.9l1.1,1.2c5-0.3,7.4-1,8-1.4C16.9,3.5,16.8,3.2,16.7,3.1z"/>
			<path class="st2" d="M15.1,5.3c-2.7,0.8-5,1-5.8,1L10.1,7c3.1-0.4,4.6-1,4.9-1.3C15.3,5.5,15.2,5.3,15.1,5.3z"/>
			<path class="st2" d="M21.1,2.8c-0.1,0-0.3,0.1-0.4,0.1L20.6,3l-10,6.8L6,4.8L5.2,3.9L4.3,3c1.9,0.2,9.7-0.1,14-1.1
				c2-0.5,1.6-1.4,1.4-1.3C10.4,1.9,2.3,1.1,2.3,1.1C1.9,1,1.6,1.2,1.4,1.5s-0.1,0.7,0.1,1L2,3l2.3,2.3l5.7,6c0.3,0.3,0.7,0.3,1.1,0.1
				L20,5.3c-0.5,4.5-1.1,9-1.4,10.1c-2,0.4-13.7,0.5-15.3,0.1c-0.5-0.8-1.1-4.7-1.5-8.8C1.7,6.1,1.1,5.7,0.5,6c0,0-0.4,0.2-0.4,0.8
				c1,9.5,2,10,2.3,10.2c0.6,0.3,4.6,0.5,8.7,0.5s8.1-0.2,8.7-0.7c0.2-0.2,0.9-0.8,2.1-12.6V4.1C22,3.2,21.7,2.8,21.1,2.8z"/>
			</svg>'
		);
	}


    /**
     * Plugin action links
     *
     * Adds action link to plugin list window
     *
     *
     * @param $links
     * @return mixed
     *
     * @since 1.0.0
     */
    public function plugin_action_links($links) {
        // settings link.
        $settings_link = sprintf(
            '<a href="%1$s">%2$s</a>',
            admin_url('admin.php?page=mrm-admin#/settings/business-info'),
            esc_html__('Settings', 'mrm')
        );
        array_unshift($links, $settings_link);

		// Documentation link.
        if (!defined('MAILMINT_PRO')) {
            $doc_link = sprintf(
                '<a href="%1$s" target="_blank" style="color: #7742e6;font-weight: bold;">%2$s</a>',
                'https://getwpfunnels.com/email-marketing-automation-mail-mint/?utm_source=mm-plugins-page-CTA&utm_medium=wp-plugins-page+&utm_campaign=mm-plugins-page-pro-CTA&utm_id=mm-plugins-page-pro-CTA#price',
                esc_html__('Upgrade to Pro', 'mrm')
            );
            $links[] = $doc_link;
        }

		// Dashboard link
		$dashboard_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url('admin.php?page=mrm-admin'),
			esc_html__('Dashboard', 'mrm')
		);
		array_unshift($links, $dashboard_link);

        return $links;
    }


    /**
     * Hide Admin notice for Mailmint.
     *
     * @return void
     */
    public function hide_update_noticee_to_mailmint()
    {
        global $current_screen;
		if ( current_user_can( 'manage_options' ) ) {
			new DBUpgradeNotice();
		}
		new SpecialOccasionBanner('wp-anniversary', '2025-06-04 20:00:00', '2025-06-15 23:59:59');
        if( 'toplevel_page_mrm-admin' === $current_screen->base){
            remove_all_actions( 'admin_notices' );
			if ( current_user_can( 'manage_options' ) ) {
				new DBUpgradeNotice();
			}
			new SpecialOccasionBanner('wp-anniversary', '2025-06-04 20:00:00', '2025-06-15 23:59:59');
        }
        if( 'mail-mint_page_mint-mail-automation-editor' === $current_screen->base){
            remove_all_actions( 'admin_notices' );
        }
    }
}
