<?php

namespace Mint\MRM\Internal\Admin;

use MRM\Common\MrmCommon;

/**
 * SpecialOccasionBanner Class
 *
 * This class is responsible for displaying a special occasion banner in the WordPress admin.
 *
 * @package YourVendor\SpecialOccasionPlugin
 */
class SpecialOccasionBanner
{

    /**
     * The occasion identifier.
     *
     * @var string
     */
    private $occasion;

    /**
     * The start date and time for displaying the banner.
     *
     * @var int
     */
    private $start_date;

    /**
     * The end date and time for displaying the banner.
     *
     * @var int
     */
    private $end_date;

    /**
     * Constructor method for SpecialOccasionBanner class.
     *
     * @param string $occasion   The occasion identifier.
     * @param string $start_date The start date and time for displaying the banner.
     * @param string $end_date   The end date and time for displaying the banner.
     */
    public function __construct($occasion, $start_date, $end_date)
    {
        $this->occasion = $occasion;
        $this->start_date = strtotime($start_date);
        $this->end_date = strtotime($end_date);

        // Hook into the admin_notices action to display the banner
        add_action('admin_notices', array($this, 'display_banner'));

        // Add styles
        add_action('admin_head', array($this, 'add_styles'));
    }

    /**
     * Calculate time remaining until the Halloween sale ends
     *
     * @return array Time remaining in days, hours, and minutes
     */
    function mint_get_promotional_countdown()
    {
        $end_date  = strtotime('2026-10-30 23:59:59');
        $now       = current_time('timestamp');
        $diff      = $end_date - $now;

        return array(
            'days' => floor($diff / (60 * 60 * 24)),
            'hours' => floor(($diff % (60 * 60 * 24)) / (60 * 60)),
            'mins' => floor(($diff % (60 * 60)) / 60),
            'secs' => $diff % 60,
        );
    }


    /**
     * Displays the special occasion banner if the current date and time are within the specified range.
     */
    public function display_banner()
    {
        $screen = get_current_screen();
        $promotional_notice_pages = ['dashboard', 'plugins', 'toplevel_page_mrm-admin'];
        $current_date_time = current_time('timestamp');
        $btn_link = 'https://getwpfunnels.com/pricing/?utm_source=plugin&utm_medium=dashboard-banner-mm&utm_campaign=halloween26#mail-mint';
        $discount = '30% OFF';

        if (!in_array($screen->id, $promotional_notice_pages)) {
            return;
        }

        if (defined('MAIL_MINT_PRO_VERSION') || ($current_date_time < $this->start_date || $current_date_time > $this->end_date) || 'no' === get_option('_is_show_halloween_26_banner') || MrmCommon::is_wpfnl_active()) {
            return;
        }
        ?>

        <div class="gwpf-promotional-notice mailmint-promotional-notice <?php echo esc_attr($this->occasion); ?>-banner notice wp-exclude-emoji">
            <div class="gwpf-tb__notification">
                <div class="banner-overflow">
                    <section class="gwpf-promotional-banner" aria-label="<?php esc_attr_e('Halloween sale', 'mrm'); ?>">
                        <div class="gwpf-container">
                            <div class="promotional-banner">
                                <div class="banner-content">
                                    <p class="banner-text banner-text--occasion">
                                        <span class="banner-emoji" aria-hidden="true">👻 </span>
                                        <span>
                                            <?php
                                            printf(
                                                /* translators: %s: "Halloween" highlighted. */
                                                esc_html__('Spooktacular %s Sale!', 'mrm'),
                                                '<span class="highlighted-text">' . esc_html__('Halloween', 'mrm') . '</span>'
                                            );
                                            ?>
                                        </span>
                                        <span class="banner-emoji" aria-hidden="true">🎃</span>
                                    </p>

                                    <p class="banner-text banner-text--offer">
                                        <?php
                                        printf(
                                            /* translators: 1: discount amount, e.g. "30% OFF". 2: product name. */
                                            esc_html__('Flat %1$s On %2$s', 'mrm'),
                                            '<span class="discount-text">' . esc_html($discount) . '</span>',
                                            '<strong>' . esc_html__('Mail Mint!', 'mrm') . '</strong>'
                                        );
                                        ?>
                                    </p>

                                    <a href="<?php echo esc_url($btn_link); ?>" class="cta-button" target="_blank" rel="noopener noreferrer">
                                        <?php
                                        /* translators: %s: discount amount, e.g. "30% OFF". */
                                        printf(esc_html__('Get %s', 'mrm'), esc_html($discount));
                                        ?>
                                        <svg width="12" height="12" fill="none" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="currentColor" stroke="currentColor" stroke-width=".5" d="M11 1.781v8.438a.781.781 0 11-1.563 0V3.667l-7.103 7.104a.781.781 0 01-1.105-1.105l7.104-7.104H1.78A.781.781 0 111.78 1h8.438a.781.781 0 01.78.781z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <button class="close-promotional-banner" type="button" aria-label="<?php esc_attr_e('Close banner', 'mrm'); ?>">
                    <svg width="12" height="13" fill="none" viewBox="0 0 12 13" xmlns="http://www.w3.org/2000/svg"><path stroke="#7A8B9A" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 1.97L1 11.96m0-9.99l10 9.99"/></svg>
                </button>
            </div>
        </div>
    <?php
    }

    /**
     * Adds internal CSS styles for the special occasion banners.
     */
    public function add_styles()
    {
    ?>
        <script id="mailmint-promotional-banner-wizard-sync">
            /**
             * The setup wizard is a hash route, so it cannot be detected server-side.
             * Sync the flag class as early as possible (admin_head) so the banner never
             * flashes before Hooks::remove_jetpack_note_from_mail_mint() runs in the footer.
             */
            (function() {
                function mintSyncPromoWizardClass() {
                    document.documentElement.classList.toggle(
                        'mrm-setup-wizard',
                        window.location.href.indexOf('setup-wizard') !== -1
                    );
                }

                mintSyncPromoWizardClass();
                window.addEventListener('hashchange', mintSyncPromoWizardClass);
            })();
        </script>

        <style id="mailmint-promotional-banner-style">

            /* Never promote inside the setup wizard. */
            html.mrm-setup-wizard .mailmint-promotional-notice {
                display: none !important;
            }

            .mailmint-promotional-notice .gwpf-tb__notification,
            .mailmint-promotional-notice .gwpf-tb__notification * {
                box-sizing: border-box;
            }

            .mailmint-promotional-notice .gwpf-tb__notification {
                width: 100%;
                margin: 20px 0 20px;
                position: relative;
                border: none;
                box-shadow: none;
                display: block;
                border-radius: 6px;
            }

            .mailmint-promotional-notice .gwpf-tb__notification .banner-overflow {
                position: relative;
                width: 100%;
                z-index: 1;
                overflow: hidden;
                border-radius: 6px;
            }

            .gwpf-promotional-notice.notice {
                border: none;
                padding: 0;
                display: block;
                background: transparent;
                margin: 0;
                box-shadow: none !important;
            }

            .mailmint-promotional-notice .gwpf-tb__notification .close-promotional-banner {
                position: absolute;
                top: -10px;
                right: -9px;
                background: #fff;
                border: none;
                padding: 0;
                border-radius: 50%;
                cursor: pointer;
                z-index: 9;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .mailmint-promotional-notice .gwpf-tb__notification .close-promotional-banner svg {
                width: 22px;
                display: block;
            }

            /* ---banner style start--- */
            .mailmint-promotional-notice .gwpf-promotional-banner {
                position: relative;
                background-color: #130566;
                background-image:
                    linear-gradient(90deg, #0A6BB5 0, rgba(19, 5, 102, 0) 140px),
                    linear-gradient(270deg, #6D9357 0, rgba(19, 5, 102, 0) 320px);
                z-index: 1111;
                font-family: "Inter", sans-serif;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner * {
                font-family: "Inter", sans-serif;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .promotional-banner {
                color: #fff;
                padding: 15px 20px;
                min-height: 60px;
                text-align: center;
                font-size: 16px;
                line-height: 1.4;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-content {
                max-width: 1200px;
                margin: 0 auto;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-wrap: wrap;
                column-gap: 24px;
                row-gap: 8px;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-text {
                display: inline-flex;
                align-items: center;
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
                margin: 0;
                padding: 0;
                font-size: 16px;
                font-weight: 500;
                line-height: 1.4;
                letter-spacing: 0;
                color: #fff;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-text--offer {
                display: inline-block;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-emoji {
                font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
                font-size: 18px;
                line-height: 1;
                flex: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .highlighted-text {
                color: #02C4FB;
                font-size: 18px;
                font-weight: 800;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .discount-text {
                color: #24EC2C;
                font-size: 18px;
                font-weight: 800;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-text strong {
                color: #fff;
                font-weight: 700;
            }

            /* ---cta--- */
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button {
                margin-left: 46px;
                padding: 0 0 3px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: #02C4FB;
                font-size: 15px;
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: 0;
                text-decoration: none;
                border-bottom: 2px solid #02C4FB;
                background: transparent;
                box-shadow: none;
                flex: none;
                transition: all .3s ease;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button svg {
                display: block;
                transition: transform .3s ease;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:hover,
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:focus,
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:visited {
                color: #02C4FB;
                box-shadow: none;
                outline: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:hover svg {
                transform: translate(3px, -3px);
            }

            @media only screen and (max-width: 1199px) {
                .mailmint-promotional-notice .gwpf-promotional-banner .cta-button {
                    margin-left: 0;
                }
            }

            @media only screen and (max-width: 991px) {
                .mailmint-promotional-notice .gwpf-tb__notification {
                    margin: 60px 0 20px;
                }

                .mailmint-promotional-notice .gwpf-tb__notification .close-promotional-banner {
                    width: 25px;
                    height: 25px;
                }
            }

            @media only screen and (max-width: 575px) {
                .mailmint-promotional-notice .gwpf-promotional-banner .promotional-banner {
                    padding: 16px 40px;
                }

                .mailmint-promotional-notice .gwpf-promotional-banner .banner-text {
                    font-size: 14px;
                }
            }
        </style>

<?php
    }
}
