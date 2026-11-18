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
     * Calculate time remaining until the Black Friday sale ends
     *
     * @return array Time remaining in days, hours, minutes and seconds, zero-padded.
     */
    function mint_get_promotional_countdown()
    {
        $diff = max(0, $this->end_date - current_time('timestamp'));

        return array(
            'days'  => sprintf('%02d', floor($diff / (60 * 60 * 24))),
            'hours' => sprintf('%02d', floor(($diff % (60 * 60 * 24)) / (60 * 60))),
            'mins'  => sprintf('%02d', floor(($diff % (60 * 60)) / 60)),
            'secs'  => sprintf('%02d', $diff % 60),
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
        $btn_link = 'https://getwpfunnels.com/pricing/?utm_source=plugin&utm_medium=dashboard-banner-mm&utm_campaign=bfcm2026';

        if (!in_array($screen->id, $promotional_notice_pages)) {
            return;
        }

        if (defined('MAIL_MINT_PRO_VERSION') || ($current_date_time < $this->start_date || $current_date_time > $this->end_date) || 'no' === get_option('_is_show_black_friday_26_banner')) {
            return;
        }

        // WPFunnels free shows its own banner on the shared screens, but never on Mail Mint's page.
        if ('toplevel_page_mrm-admin' !== $screen->id && MrmCommon::is_wpfnl_active() && !defined('WPFNL_PRO_VERSION')) {
            return;
        }

        $countdown = $this->mint_get_promotional_countdown();
        $countdown_labels = array(
            'days'  => __('Day', 'mrm'),
            'hours' => __('Hr', 'mrm'),
            'mins'  => __('Min', 'mrm'),
            'secs'  => __('Sec', 'mrm'),
        );
        ?>

        <div class="gwpf-promotional-notice mailmint-promotional-notice <?php echo esc_attr($this->occasion); ?>-banner notice wp-exclude-emoji">
            <section class="mint-bf-banner" aria-label="<?php esc_attr_e('Mail Mint Black Friday offer', 'mrm'); ?>">
                <div class="mint-bf-banner__content">
                    <figure class="mint-bf-banner__logo">
                        <img src="<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/promotional/black-friday.webp'); ?>" alt="<?php esc_attr_e('Black Friday', 'mrm'); ?>" width="87" height="56">
                    </figure>

                    <div class="mint-bf-banner__text">
                        <span class="mint-bf-banner__title"><?php esc_html_e('Biggest Sale of The Year', 'mrm'); ?></span>
                        <span class="mint-bf-banner__offer"><?php esc_html_e('GET 40% OFF!', 'mrm'); ?></span>
                    </div>

                    <div class="mint-bf-banner__timer" id="mint-bf-countdown" data-remaining="<?php echo esc_attr(max(0, $this->end_date - $current_date_time)); ?>">
                        <span class="screen-reader-text"><?php esc_html_e('Offer countdown', 'mrm'); ?></span>
                        <?php foreach ($countdown_labels as $unit => $label) : ?>
                            <div class="mint-bf-banner__timer-box">
                                <span class="mint-bf-banner__timer-num" data-unit="<?php echo esc_attr($unit); ?>"><?php echo esc_html($countdown[$unit]); ?></span>
                                <span class="mint-bf-banner__timer-unit"><?php echo esc_html($label); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <a href="<?php echo esc_url($btn_link); ?>" class="mint-bf-banner__cta" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Claim Your Deal', 'mrm'); ?>
                        <svg width="10" height="10" fill="none" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M1 9L9 1m0 0H2.5M9 1v6.5"/></svg>
                    </a>
                </div>

                <button class="close-promotional-banner" type="button" aria-label="<?php esc_attr_e('Close banner', 'mrm'); ?>">
                    <svg width="9" height="9" fill="none" viewBox="0 0 8 8" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-width="1.2" d="M7.5.5l-7 7m0-7l7 7"/></svg>
                </button>
            </section>
        </div>

        <script>
            (function() {
                var timer = document.getElementById('mint-bf-countdown');
                if (!timer) {
                    return;
                }

                var remaining = parseInt(timer.getAttribute('data-remaining'), 10) || 0;
                var endAt = Date.now() + remaining * 1000;
                var nodes = {};
                timer.querySelectorAll('[data-unit]').forEach(function(node) {
                    nodes[node.getAttribute('data-unit')] = node;
                });

                function pad(n) {
                    return n < 10 ? '0' + n : String(n);
                }

                function tick() {
                    var diff = Math.max(0, Math.floor((endAt - Date.now()) / 1000));
                    nodes.days.textContent  = pad(Math.floor(diff / 86400));
                    nodes.hours.textContent = pad(Math.floor((diff % 86400) / 3600));
                    nodes.mins.textContent  = pad(Math.floor((diff % 3600) / 60));
                    nodes.secs.textContent  = pad(diff % 60);

                    if (diff <= 0) {
                        clearInterval(interval);
                        var notice = timer.closest('.gwpf-promotional-notice');
                        if (notice) {
                            notice.style.display = 'none';
                        }
                    }
                }

                var interval = setInterval(tick, 1000);
            })();
        </script>
    <?php
    }

    /**
     * Adds internal CSS styles for the special occasion banners.
     */
    public function add_styles()
    {
        $font_url = MRM_DIR_URL . 'admin/assets/fonts/campaign-font/';
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
            @font-face {
                font-family: 'Mint Promo Inter';
                src: url(<?php echo esc_url($font_url . 'Inter-Regular.woff2'); ?>) format('woff2');
                font-weight: 400;
                font-style: normal;
                font-display: swap;
            }

            @font-face {
                font-family: 'Mint Promo Inter';
                src: url(<?php echo esc_url($font_url . 'Inter-SemiBold.woff2'); ?>) format('woff2');
                font-weight: 600;
                font-style: normal;
                font-display: swap;
            }

            @font-face {
                font-family: 'Mint Promo Inter';
                src: url(<?php echo esc_url($font_url . 'Inter-Bold.woff2'); ?>) format('woff2');
                font-weight: 700 800;
                font-style: normal;
                font-display: swap;
            }

            /* Never promote inside the setup wizard. */
            html.mrm-setup-wizard .mailmint-promotional-notice {
                display: none !important;
            }

            .gwpf-promotional-notice.notice {
                border: none;
                padding: 0;
                display: block;
                background: transparent;
                margin: 20px 0;
                box-shadow: none !important;
            }

            .mailmint-promotional-notice .mint-bf-banner,
            .mailmint-promotional-notice .mint-bf-banner * {
                box-sizing: border-box;
            }

            /* Measured from the 1920px design: 70px bar, content block centered. */
            .mailmint-promotional-notice .mint-bf-banner {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 70px;
                padding: 7px 64px;
                background: #040317;
                color: #fff;
                font-family: 'Mint Promo Inter', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            }

            .mailmint-promotional-notice .mint-bf-banner__content {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .mailmint-promotional-notice .mint-bf-banner__logo {
                flex-shrink: 0;
                width: 87px;
                height: 56px;
                margin: 0 30px 0 0;
                padding: 0;
            }

            .mailmint-promotional-notice .mint-bf-banner__logo img {
                display: block;
                width: 87px;
                height: 56px;
                max-width: none;
            }

            .mailmint-promotional-notice .mint-bf-banner__text {
                display: flex;
                flex-direction: column;
                gap: 4px;
                margin-right: 47px;
                white-space: nowrap;
            }

            .mailmint-promotional-notice .mint-bf-banner__title {
                font-size: 16px;
                font-weight: 400;
                line-height: 1.2;
                letter-spacing: -0.2px;
                color: #fff;
            }

            .mailmint-promotional-notice .mint-bf-banner__offer {
                font-size: 24px;
                font-weight: 800;
                line-height: 1.2;
                color: #D86F2E;
            }

            .mailmint-promotional-notice .mint-bf-banner__timer {
                display: flex;
                align-items: center;
                gap: 2px;
                margin-right: 81px;
            }

            .mailmint-promotional-notice .mint-bf-banner__timer-box {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 7px;
                width: 54px;
                height: 50px;
                background: #0B0A12;
                border: 1px solid #2E2A6E;
                border-radius: 4px;
            }

            .mailmint-promotional-notice .mint-bf-banner__timer-num {
                font-size: 20px;
                font-weight: 700;
                line-height: 20px;
                color: #fff;
                font-variant-numeric: tabular-nums;
            }

            .mailmint-promotional-notice .mint-bf-banner__timer-unit {
                font-size: 12px;
                font-weight: 400;
                line-height: 12px;
                text-transform: uppercase;
                color: #fff;
            }

            .mailmint-promotional-notice .mint-bf-banner__cta {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                height: 38px;
                padding: 0 19px;
                font-size: 15px;
                font-weight: 600;
                line-height: 1;
                letter-spacing: -0.15px;
                color: #000;
                text-decoration: none;
                white-space: nowrap;
                background: #ffffff;
                border-radius: 4px;
                box-shadow: none;
                transition: background-color .2s ease, transform .15s ease;
            }

            .mailmint-promotional-notice .mint-bf-banner__cta:hover,
            .mailmint-promotional-notice .mint-bf-banner__cta:focus {
                color: #000;
                background: #f0f0f0;
                box-shadow: none;
                transform: translateY(-1px);
            }

            .mailmint-promotional-notice .mint-bf-banner__cta:visited {
                color: #000;
            }

            .mailmint-promotional-notice .close-promotional-banner {
                position: absolute;
                top: 50%;
                right: 33px;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 30px;
                height: 30px;
                margin-top: -15px;
                padding: 0;
                color: #7e7e7e;
                background: transparent;
                border: none;
                border-radius: 50%;
                cursor: pointer;
                transition: color .2s ease, background .2s ease;
            }

            .mailmint-promotional-notice .close-promotional-banner:hover,
            .mailmint-promotional-notice .close-promotional-banner:focus {
                color: #fff;
                background: rgba(255, 255, 255, .15);
                outline: none;
            }

            @media only screen and (max-width: 1280px) {
                .mailmint-promotional-notice .mint-bf-banner {
                    padding: 7px 56px 7px 24px;
                }

                .mailmint-promotional-notice .mint-bf-banner__text {
                    margin-right: 28px;
                }

                .mailmint-promotional-notice .mint-bf-banner__timer {
                    margin-right: 40px;
                }
            }

            @media only screen and (max-width: 1080px) {
                .mailmint-promotional-notice .mint-bf-banner {
                    padding: 14px 48px 14px 20px;
                }

                .mailmint-promotional-notice .mint-bf-banner__content {
                    flex-wrap: wrap;
                    gap: 12px 20px;
                }

                .mailmint-promotional-notice .mint-bf-banner__logo,
                .mailmint-promotional-notice .mint-bf-banner__text,
                .mailmint-promotional-notice .mint-bf-banner__timer {
                    margin-right: 0;
                }

                .mailmint-promotional-notice .close-promotional-banner {
                    top: 10px;
                    right: 8px;
                    margin-top: 0;
                }
            }
        </style>

<?php
    }
}
