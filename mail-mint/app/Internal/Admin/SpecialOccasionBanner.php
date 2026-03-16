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
     * Calculate time remaining until blackfriday
     *
     * @return array Time remaining in days, hours, and minutes
     */
    function mint_get_promotional_countdown()
    {
        $end_date  = strtotime('2025-10-20 23:59:59');
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
        $btn_link = 'https://getwpfunnels.com/pricing/?utm_source=plugin&utm_medium=dashboard-banner-mm&utm_campaign=eidoffer2026';

        if (!in_array($screen->id, $promotional_notice_pages)) {
            return;
        }

        if (defined('MAIL_MINT_PRO_VERSION') || ($current_date_time < $this->start_date || $current_date_time > $this->end_date) || 'no' === get_option('_is_show_eid_ul_fitr_26_banner') || MrmCommon::is_wpfnl_active()) {
            return;
        }

        // Calculate the time remaining in seconds
        $time_remaining = $this->end_date - $current_date_time;

        // Calculate the countdown
        $countdown = $this->mint_get_promotional_countdown();
        ?>

        <div class="gwpf-promotional-notice mailmint-promotional-notice <?php echo esc_attr($this->occasion); ?>-banner notice">
            <div class="gwpf-tb__notification">
                <div class="banner-overflow">
                    <section class="gwpf-promotional-banner" aria-labelledby="wpf-blackfriday-offer">
                        <div class="gwpf-container">
                            <div class="promotional-banner">
                                <div class="banner-content">
                                    <span class="blackfriday-logo">
                                        <img src="" alt="">
                                    </span>

                                    <div class="banner-text">
                                        🔥 Eid ul-Fitr Special: Claim A Huge <span class="highlighted-text">40% OFF</span> On Mail Mint! - Limited Time Deal!
                                    </div>

                                    <a href="<?php echo esc_url($btn_link); ?>" class="cta-button" role="button" aria-label="get special discount " target="_blank">
                                        <?php
                                            echo __('Get 40% OFF', 'getwpfunnels');
                                        ?>
                                        <svg width="11" height="11" fill="none" viewBox="0 0 11 11" xmlns="http://www.w3.org/2000/svg"><path fill="#3C1F7D" stroke="#3C1F7D" stroke-width=".2" d="M9.419.1a.88.88 0 01.88.881V9.42a.88.88 0 11-1.761 0V3.11l-6.934 6.933A.88.88 0 01.358 8.796l6.934-6.934H.982A.88.88 0 11.981.1h8.437z"></path></svg>
                                    </a>

                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <button class="close-promotional-banner" type="button" aria-label="close banner">
                    <svg width="12" height="13" fill="none" viewBox="0 0 12 13" xmlns="http://www.w3.org/2000/svg"><path stroke="#7A8B9A" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 1.97L1 11.96m0-9.99l10 9.99"/></svg>
                </button>
            </div>
        </div>

        <script>
            var timeRemaining = <?php echo esc_js($time_remaining); ?>;

            function updateCountdown() {
                var endDate = new Date("2025-12-11 23:59:59").getTime();
                var now = new Date().getTime();
                var timeLeft = endDate - now;

                var days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                var hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                var daysElement = document.getElementById('mint-blackfriday-days');
                var hoursElement = document.getElementById('mint-blackfriday-hours');
                var minsElement = document.getElementById('mint-blackfriday-mins');
                var secsElement = document.getElementById('mint-blackfriday-secs');

                if (daysElement) {
                    daysElement.innerHTML = days;
                }

                if (hoursElement) {
                    hoursElement.innerHTML = hours;
                }

                if (minsElement) {
                    minsElement.innerHTML = minutes;
                }
                if (secsElement) {
                    secsElement.innerHTML = seconds;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                updateCountdown();
                setInterval(updateCountdown, 1000); // Update every minute
            });
        </script>
    <?php
    }

    /**
     * Adds internal CSS styles for the special occasion banners.
     */
    public function add_styles()
    {
    ?>
        <style id="mailmint-promotional-banner-style">

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
                max-height: 110px;
                border-radius: 6px;
            }
            .mailmint-promotional-notice .toplevel_page_mrm-admin .gwpf-tb__notification {
                width: calc(100% - 20px);
            }

            .mailmint-promotional-notice .gwpf-tb__notification .banner-overflow {
                position: relative;
                width: 100%;
                z-index: 1;
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
                background-color: #150A53;
                /* background: linear-gradient(96deg, #573BFF 23.39%, #3216DA 49.94%); */
                z-index: 1111;
                /* padding: 6px 0; */
            }
            .gwpf-promotional-banner .banner-content .blackfriday-logo svg {
                display: block;
                position: relative;
                top: 5px;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .promotional-banner {
                color: white;
                padding: 12px 20px;
                text-align: center;
                font-size: 14px;
                line-height: 1.4;
                position: relative;
                display: flex;
                flex-flow: column;
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
                gap: 20px;
                row-gap: 8px;
                position: relative;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-text {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 6px;
                row-gap: 0;
                justify-content: center;
                font-size: 16px;
                color: #fff;
                font-weight: 400;
                line-height: 1.4;
                text-transform: capitalize;
                letter-spacing: 0;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-text svg {
                display: block;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-text .highlighted-text {
                color: #02C4FB;
                font-weight: 700;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button {
                color: #3c1f7d;
                font-size: 14px;
                font-style: normal;
                font-weight: 700;
                line-height: 1;
                letter-spacing: -0.084px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all .3s ease;
                border-radius: 100px;
                background: #fff;
                box-shadow: 0 4px 10px 0 rgba(110, 66, 211, .3);
                padding: 10px 15px;
                text-decoration: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button svg {
                transform: translate(0, 0);
                transition: all .3s ease;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:focus, 
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:visited {
                color: #3c1f7d !important;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:hover {
                color: #3c1f7d !important;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:hover svg {
                transform: translate(3px, -4px);
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

            @media only screen and (max-width: 991px) {
                .mailmint-promotional-notice .promotional-banner {
                    padding: 16px 20px;
                }

                .mailmint-promotional-notice .gwpf-tb__notification {
                    margin: 60px 0 20px;
                }

                .mailmint-promotional-notice .gwpf-tb__notification .close-promotional-banner {
                    width: 25px;
                    height: 25px;
                }
            }

            @media only screen and (max-width: 767px) {
                .mailmint-promotional-notice .wpvr-promotional-banner {
                    padding-top: 20px;
                    padding-bottom: 30px;
                    max-height: none;
                }

                .mailmint-promotional-notice .wpvr-promotional-banner {
                    max-height: none;
                }
            }

            @media only screen and (max-width: 575px) {
                .mailmint-promotional-notice .promotional-banner {
                    padding: 16px 55px;
                    font-size: 13px;
                }
            }
        </style>

<?php
    }
}
