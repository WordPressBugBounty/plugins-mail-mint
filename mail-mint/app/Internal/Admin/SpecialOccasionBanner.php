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
        $btn_link = 'https://getwpfunnels.com/pricing/?utm_source=plugin&utm_medium=dashboard-banner-mm&utm_campaign=iemail40#mail-mint';
        $coupon_code = 'AIEMAIL40';
        $discount = '40% OFF';

        if (!in_array($screen->id, $promotional_notice_pages)) {
            return;
        }

        if (defined('MAIL_MINT_PRO_VERSION') || ($current_date_time < $this->start_date || $current_date_time > $this->end_date) || 'no' === get_option('_is_show_ai_engine_banner')) {
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
                                    <span class="banner-emoji" aria-hidden="true">🔥</span>

                                    <div class="banner-text">
                                        <span class="banner-headline">
                                            <?php
                                            printf(
                                                /* translators: %s: discount amount, e.g. "40% OFF". */
                                                esc_html__('AI Email Engine Is Here — %s on Mail Mint!', 'mrm'),
                                                '<span class="highlighted-text">' . esc_html($discount) . '</span>'
                                            );
                                            ?>
                                        </span>
                                        <span class="banner-subtext">
                                            <?php esc_html_e('Limited time deal · Use the code at checkout', 'mrm'); ?>
                                        </span>
                                    </div>

                                    <div class="coupon-block">
                                        <span class="coupon-label"><?php esc_html_e('Coupon', 'mrm'); ?></span>

                                        <button type="button" class="coupon-chip" data-coupon="<?php echo esc_attr($coupon_code); ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: coupon code. */ __('Copy coupon code %s', 'mrm'), $coupon_code)); ?>">
                                            <span class="coupon-code"><?php echo esc_html($coupon_code); ?></span>
                                            <span class="coupon-copy-icon" aria-hidden="true">
                                                <svg width="14" height="14" fill="none" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="5" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M9.5 3.5v-1A1.5 1.5 0 008 1H2.5A1.5 1.5 0 001 2.5V8a1.5 1.5 0 001.5 1.5h1" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                                            </span>
                                            <span class="coupon-copied-tip" aria-hidden="true"><?php esc_html_e('Copied!', 'mrm'); ?></span>
                                        </button>
                                    </div>

                                    <span class="banner-divider" aria-hidden="true"></span>

                                    <a href="<?php echo esc_url($btn_link); ?>" class="mintmrm-btn cta-button" role="button" aria-label="<?php esc_attr_e('Claim the discount', 'mrm'); ?>" target="_blank">
                                        <?php esc_html_e('Claim Discount', 'mrm'); ?>
                                        <svg width="11" height="11" fill="none" viewBox="0 0 11 11" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" stroke="currentColor" stroke-width=".2" d="M9.419.1a.88.88 0 01.88.881V9.42a.88.88 0 11-1.761 0V3.11l-6.934 6.933A.88.88 0 01.358 8.796l6.934-6.934H.982A.88.88 0 11.981.1h8.437z"></path></svg>
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

            (function() {
                var COPIED_DURATION = 1800;

                function copyToClipboard(text) {
                    if (navigator.clipboard && window.isSecureContext) {
                        return navigator.clipboard.writeText(text);
                    }

                    // Fallback for non-secure contexts (e.g. plain http admin).
                    return new Promise(function(resolve, reject) {
                        var helper = document.createElement('textarea');
                        helper.value = text;
                        helper.setAttribute('readonly', '');
                        helper.style.position = 'fixed';
                        helper.style.top = '-9999px';
                        document.body.appendChild(helper);
                        helper.select();

                        try {
                            document.execCommand('copy') ? resolve() : reject();
                        } catch (error) {
                            reject(error);
                        } finally {
                            document.body.removeChild(helper);
                        }
                    });
                }

                function bindCouponChip(chip) {
                    var timer = null;

                    chip.addEventListener('click', function() {
                        copyToClipboard(chip.getAttribute('data-coupon')).then(function() {
                            chip.classList.add('is-copied');
                            window.clearTimeout(timer);
                            timer = window.setTimeout(function() {
                                chip.classList.remove('is-copied');
                            }, COPIED_DURATION);
                        }).catch(function() {});
                    });
                }

                document.addEventListener('DOMContentLoaded', function() {
                    var chips = document.querySelectorAll('.mailmint-promotional-notice .coupon-chip');
                    Array.prototype.forEach.call(chips, bindCouponChip);
                });
            })();
        </script>
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
                background-color: #1C1A4A;
                z-index: 1111;
                font-family: "Inter", sans-serif;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner * {
                font-family: "Inter", sans-serif;
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
                row-gap: 12px;
                position: relative;
                text-align: left;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-emoji {
                font-size: 24px;
                line-height: 1;
                flex: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-text {
                display: flex;
                flex-flow: column;
                align-items: flex-start;
                justify-content: center;
                gap: 3px;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-headline {
                font-size: 16px;
                color: #fff;
                font-weight: 600;
                line-height: 1.4;
                letter-spacing: 0;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-headline .highlighted-text {
                color: #02C4FB;
                font-weight: 700;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-subtext {
                font-size: 12px;
                font-weight: 400;
                line-height: 1.4;
                color: rgba(255, 255, 255, .65);
            }

            /* ---coupon chip--- */
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-block {
                display: flex;
                align-items: center;
                gap: 8px;
                flex: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-label {
                font-size: 11px;
                font-weight: 500;
                line-height: 1;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: rgba(255, 255, 255, .65);
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-chip {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                margin: 0;
                padding: 6px 12px;
                background: rgba(87, 59, 255, .25);
                border: 1.5px dashed rgba(87, 59, 255, .8);
                border-radius: 6px;
                cursor: pointer;
                box-shadow: none;
                -webkit-appearance: none;
                appearance: none;
                transition: all .3s ease;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-chip:hover {
                background: rgba(87, 59, 255, .4);
                border-color: #573BFF;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-chip:focus {
                outline: none;
                box-shadow: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-chip.is-copied {
                border-color: #10B981;
                background: rgba(16, 185, 129, .2);
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-code {
                font-size: 15px;
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: 0.12em;
                color: #FFFFFF;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-copy-icon {
                display: flex;
                align-items: center;
                color: rgba(255, 255, 255, .8);
                transition: all .3s ease;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-copy-icon svg {
                display: block;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-chip:hover .coupon-copy-icon {
                color: #FFFFFF;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-chip.is-copied .coupon-copy-icon {
                color: #10B981;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-copied-tip {
                position: absolute;
                left: 50%;
                bottom: calc(100% + 8px);
                transform: translate(-50%, 4px);
                padding: 3px 8px;
                border-radius: 4px;
                background: #10B981;
                color: #FFFFFF;
                font-size: 11px;
                font-weight: 600;
                line-height: 1.4;
                white-space: nowrap;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: all .3s ease;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .coupon-chip.is-copied .coupon-copied-tip {
                opacity: 1;
                visibility: visible;
                transform: translate(-50%, 0);
            }

            /* ---divider--- */
            .mailmint-promotional-notice .gwpf-promotional-banner .banner-divider {
                width: 1px;
                height: 34px;
                flex: none;
                background: rgba(255, 255, 255, .2);
            }

            /* ---cta--- */
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button {
                color: #fff;
                font-size: 13px;
                font-style: normal;
                font-weight: 600;
                line-height: 1;
                letter-spacing: 0;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all .3s ease;
                border: none;
                border-radius: 6px;
                background: #573BFF;
                padding: 10px 18px;
                text-decoration: none;
                flex: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button svg {
                display: block;
                transform: translate(0, 0);
                transition: all .3s ease;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:focus,
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:visited {
                color: #fff !important;
                box-shadow: none;
                outline: none;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:hover {
                color: #fff !important;
                background: #4C25A5;
            }
            .mailmint-promotional-notice .gwpf-promotional-banner .cta-button:hover svg {
                transform: translate(3px, -3px);
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
                    max-height: none;
                }

                .mailmint-promotional-notice .gwpf-promotional-banner .banner-divider {
                    display: none;
                }

                .mailmint-promotional-notice .gwpf-promotional-banner .banner-content {
                    justify-content: flex-start;
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
