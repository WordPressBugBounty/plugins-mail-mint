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
     * Calculate time remaining until Halloween
     *
     * @return array Time remaining in days, hours, and minutes
     */
    function mint_get_halloween_countdown()
    {
        $end_date  = strtotime('2025-08-25 23:59:59');
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
        if (!in_array($screen->id, $promotional_notice_pages)) {
            return;
        }

        // if (defined('MAIL_MINT_PRO_VERSION') || ($current_date_time < $this->start_date || $current_date_time > $this->end_date) || 'no' === get_option('_is_mint_4thofjuly_25') || MrmCommon::is_wpfnl_active() || 'no' === get_option('_is_wpfnl_4thofjuly_25')) {
        //     return;
        // }

        if (($current_date_time < $this->start_date || $current_date_time > $this->end_date) || 'no' === get_option('_hide_clms_early_access')) {
            return;
        }

        if (($screen->id === 'dashboard' || $screen->id === 'plugins') &&  MrmCommon::is_wpfnl_active()) {
            return;
        }

        // Calculate the time remaining in seconds
        $time_remaining = $this->end_date - $current_date_time;

        // Calculate the countdown
        $countdown = $this->mint_get_halloween_countdown();
?>

        <!-- Name: WordPress Anniversary Notification Banner -->
        <div class="<?php echo esc_attr($this->occasion); ?>-banner notice">
            <div class="mailmint-promotional-banner">
                <div class="mailmint-tb__notification">

                    <div class="banner-overflow">
                        <!-- <section class="mint-notification-counter default-notification" aria-labelledby="mint-halloween-offer-title">
                            <div class="mint-notification-counter__container">
                                <div class="mint-notification-counter__content">

                                    <figure class="mint-notification-counter__figure-logo">
                                        <img src="<//?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/independence-day/occation.webp'); ?>" alt="wpfunnel anniversary offer banner" class="mint-notification-counter__img">
                                    </figure>

                                    <figure class="mint-notification-counter__figure-occasion">
                                        <img src="<//?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/independence-day/parcentage.webp'); ?>" alt="wpfunnel anniversary offer banner" class="mint-notification-counter__img">
                                    </figure>

                                    <figure class="mint-notification-counter__figure-percentage">
                                        <img src="<//?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/independence-day/logo.webp'); ?>" alt="wpfunnel anniversary offer banner" class="mint-notification-counter__img">
                                    </figure>

                                    <div id="mint-halloween-countdown" class="mint-notification-counter__countdown" aria-live="polite">
                                        <h3 class="screen-reader-text"><//?php echo __('Offer Countdown', 'mrm'); ?></h3>
                                        <ul class="mint-notification-counter__list">

                                            <//?php foreach (['days', 'hours', 'mins', 'secs'] as $unit): ?>
                                                <li class="mint-notification-counter__item ">
                                                    <span id="mint-halloween-<//?php echo esc_attr($unit); ?>" class="mint-notification-counter__time">
                                                        <//?php echo esc_html($countdown[$unit]); ?>
                                                    </span>
                                                    <span class="mint-notification-counter__label">
                                                        <//?php echo esc_html($unit); ?>
                                                    </span>
                                                </li>
                                            <//?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <div class="mint-notification-counter__btn-area">
                                        <a href="<//?php echo esc_url('https://getwpfunnels.com/pricing/?utm_source=website&utm_medium=plugin-ban-mm&utm_campaign=4thofjuly#mail-mint'); ?>" target="_blank" class="mint-notification-counter__btn" role="button">

                                            <span class="mint-btn-inner">
                                                <span class="screen-reader-text"><//?php echo __('Click to view Halloween sale products', 'mrm'); ?></span>
                                                <span aria-hidden="true" class="mint-notification-counter__mint-button"> <//?php echo __('Get The Deal Now', 'mrm'); ?></span>
                                            </span>

                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="mint-earlybird-notification__container">
                            </div>
                        </section> -->

                        <!-- Creator lms early bird banner starts -->
                        <section class="mint-lms-earlybird-banner">
                            <div class="mint-lms-earlybird-banner-container">
                                <div class="mailmint-banner__icon">
                                    <img src="<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/creator-lms-logo.webp'); ?>" alt="creator lms logo" class="">
                                </div>

                                <div class="mailmint-banner__content">
                                <p>📢 Introducing <strong>Creator LMS Pro</strong> — a powerful upgrade to Creator LMS with a sleek new UI, Cohorts, Communities, built-in checkout, gamification, AI Assistant, advanced analytics, and more! 🚀 <strong>Get a special discount</strong> as an Early Adopter — available for a limited time only!</p>

                                <div class="mailmint-banner__actions">
                                    <div class="mint-notification-counter__btn-area">
                                    <a href="https://getwpfunnels.com/creatorlms-early-access/?utm_source=plugin-CTA&utm_medium=mm-plugin&utm_campaign=clms-early-bird"
                                        target="_blank"
                                        class="mint-notification-counter__btn btn btn-primary"
                                        role="button">
                                        <span class="mint-btn-inner">
                                        <span class="screen-reader-text">Click to upgrade to Mail Mint Pro</span>
                                        <span aria-hidden="true" class="mint-notification-counter__mint-button">
                                            Sign Up
                                        </span>
                                        </span>
                                    </a>
                                    </div>

                                    <div class="mint-notification-counter__btn-area close-no-thanks">
                                        <a href="#"
                                            class="mint-notification-counter__btn btn btn-secondary"
                                            role="button">
                                            <span class="mint-btn-inner">
                                            <span class="screen-reader-text">Dismiss this offer</span>
                                            <span aria-hidden="true" class="mint-notification-counter__mint-button">
                                                No, Thanks
                                            </span>
                                            </span>
                                        </a>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </section>

                        <!-- Creator lms early bird banner ends -->
                    </div>

                    <button class="close-promotional-banner" type="button" aria-label="close banner">
                        <svg width="12" height="13" fill="none" viewBox="0 0 12 13" xmlns="http://www.w3.org/2000/svg">
                            <path stroke="#7A8B9A" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 1.97L1 11.96m0-9.99l10 9.99" />
                        </svg>
                    </button>


                </div>
            </div>
        </div>
        <script>
            var timeRemaining = <?php echo esc_js($time_remaining); ?>;

            function updateCountdown() {
                var endDate = new Date("2025-08-25 23:59:59").getTime();
                var now = new Date().getTime();
                var timeLeft = endDate - now;

                var days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                var hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

                var daysElement = document.getElementById('mint-halloween-days');
                var hoursElement = document.getElementById('mint-halloween-hours');
                var minsElement = document.getElementById('mint-halloween-mins');
                var secsElement = document.getElementById('mint-halloween-secs');

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
            @font-face {
                font-family: "Circular Std Bold";
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/circularstd-bold.woff2'; ?>) format("woff2"),
                    url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/circularstd-bold.woff'; ?>) format("woff");
                font-weight: normal;
                font-style: normal;
                font-display: swap;
            }

            @font-face {
                font-family: "Circular Std Book";
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/CircularStd-Book.woff2'; ?>) format("woff2"),
                    url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/CircularStd-Book.woff'; ?>) format("woff");
                font-weight: normal;
                font-style: normal;
                font-display: swap;
            }


            @font-face {
                font-family: "Inter";
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Inter-Bold.woff2'; ?>) format("woff2"),
                    url(<?php echo MRM_DIR_URL . 'assets/fonts/Inter-Bold.woff'; ?>) format("woff");
                font-weight: 700;
                font-style: normal;
                font-display: swap;
            }

            @font-face {
                font-family: 'Lexend Deca';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/LexendDeca-SemiBold.woff2'; ?>) format("woff2"),
                    url(<?php echo MRM_DIR_URL . 'assets/fonts/LexendDeca-SemiBold.woff'; ?>) format("woff");
                font-weight: 600;
                font-style: normal;
                font-display: swap;
            }

            @font-face {
                font-family: 'Lexend Deca';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/LexendDeca-Bold.woff2'; ?>) format("woff2"),
                    url(<?php echo MRM_DIR_URL . 'assets/fonts/LexendDeca-Bold.woff'; ?>) format("woff");
                font-weight: 700;
                font-style: normal;
                font-display: swap;
            }

            @font-face {
                font-family: 'Lexend Deca';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/LexendDeca-ExtraBold.woff2'; ?>) format("woff2"),
                    url(<?php echo MRM_DIR_URL . 'assets/fonts/LexendDeca-ExtraBold.woff'; ?>) format("woff");
                font-weight: 800;
                font-style: normal;
                font-display: swap;
            }

            .mailmint-tb__notification,
            .mailmint-tb__notification * {
                box-sizing: border-box;
            }

            /* .mailmint-tb__notification {
                width: calc(100% - 20px);
                margin: 20px 0 20px;
                background-image: url(<//?php echo MRM_DIR_URL . 'admin/assets/images/independence-day/bg.webp'; ?>);
                background-repeat: no-repeat;
                background-size: cover;
                position: relative;
                border: none;
                box-shadow: none;
                display: block;
                max-height: 110px;
            } */

            /* For Creator LMS early bird banner */
            .mailmint-tb__notification {
                width: calc(100% - 20px);
                margin: 20px 0 20px;
                position: relative;
                border: none;
                box-shadow: none;
                display: block;
                max-height: 110px;
                border-radius: 6px;
            }

            .mailmint-tb__notification .banner-overflow {
                overflow: hidden;
                position: relative;
                width: 100%;
                z-index: 1;
            }

            .wp-anniversary-banner.notice {
                border: none;
                padding: 0;
                display: block !important;
                background: transparent;
                margin: 0;
                box-shadow: none !important;
            }

            .mailmint-tb__notification .close-promotional-banner {
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

            .mailmint-tb__notification .close-promotional-banner svg {
                width: 22px;
            }

            .mailmint-tb__notification .close-promotional-banner svg {
                display: block;
                width: 15px;
                height: 15px;
            }

            .mailmint-anniv__container {
                width: 100%;
                margin: 0 auto;
                max-width: 1640px;
                position: relative;
                padding-right: 15px;
                padding-left: 15px;
            }

            .mailmint-anniv__container-area {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .mailmint-anniv__content-area {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: space-evenly;
                max-width: 1310px;
                position: relative;
                padding-right: 15px;
                padding-left: 15px;
                margin: 0 auto;
                z-index: 1;
            }

            .mailmint-anniv__image--left {
                position: absolute;
                left: 140px;
                top: 50%;
                transform: translateY(-50%);
            }

            .mailmint-anniv__image--right {
                position: absolute;
                right: 0;
                top: 60%;
                transform: translateY(-50%);
            }

            .mailmint-anniv__image--group {
                display: flex;
                align-items: center;
                gap: 50px;
            }

            .mailmint-anniv__image--left img {
                width: 100%;
                max-width: 98px;
            }

            .mailmint-anniv__image--eid-mubarak img {
                width: 100%;
                max-width: 125px;
            }

            .mailmint-anniv__image--wpfunnel-logo img {
                width: 100%;
                max-width: 125px;
            }

            .mailmint-anniv__image--four img {
                width: 100%;
                max-width: 224px;
            }

            .mailmint-anniv__lead-text {
                display: flex;
                gap: 11px;
            }

            .mailmint-anniv__lead-text h2 {
                font-size: 42px;
                line-height: 1;
                margin: 0;
                color: #FFFFFF;
                font-weight: 700;
                font-family: 'Lexend Deca';

            }



            .mailmint-anniv__image--right img {
                width: 100%;
                max-width: 152px;
            }

            .mailmint-anniv__image figure {
                margin: 0;
            }

            .mailmint-anniv__text-container {
                position: relative;
                max-width: 330px;
            }

            .mailmint-anniv__campaign-text-images {
                position: absolute;
                top: -10px;
                right: -15px;
                max-width: 100%;
                max-height: 24px;
            }



            .mailmint-anniv__btn-area {
                display: flex;
                align-items: flex-end;
                justify-content: flex-end;
                position: relative;
            }

            .mailmint-anniv__btn-area svg {
                position: absolute;
                width: 70px;
                right: -35px;
            }

            .mailmint-anniv__btn {
                font-family: 'Lexend Deca';
                font-size: 20px;
                font-weight: 700;
                line-height: 1;
                text-align: center;
                border-radius: 13px;
                background: linear-gradient(0deg, #ACE7FF 0%, #FFFFFF 100%);
                ;
                box-shadow: 0px 11px 30px 0px rgba(19, 13, 57, 0.25);
                color: #2D29FF;
                padding: 17px 26px;
                display: inline-block;
                cursor: pointer;
                text-transform: capitalize;
                transition: all 0.5s linear;
                text-decoration: none;
            }

            a.mailmint-anniv__btn:hover {
                box-shadow: none;
            }

            .mailmint-anniv__btn-area a:focus {
                color: #fff;
                box-shadow: none;
                outline: 0px solid transparent;
            }

            .mailmint-anniv__btn:hover {
                background-color: #201cfe;
                color: #6E42D3;
            }

            .wpcartlift-banner-title p {
                margin: 0;
                font-weight: 700;
                max-width: 315px;
                font-size: 24px;
                color: #ffffff;
                line-height: 1.3;
            }

            @media only screen and (min-width: 1921px) {
                .mailmint-anniv__image--left img {
                    max-width: 108px;
                }
            }


            @media only screen and (max-width: 1710px) {

                .mailmint-anniv__image--left {
                    left: 100px;
                }

                .mailmint-anniv__lead-text h2 {
                    font-size: 36px;
                }

                .mailmint-anniv__content-area {
                    justify-content: center;
                }

                .mailmint-anniv__image--group {
                    gap: 30px;
                }

                .mailmint-anniv__content-area {
                    gap: 30px;
                }

                .mailmint-anniv__btn {
                    font-size: 18px;
                }

                .mailmint-anniv__btn-area svg {
                    position: absolute;
                    width: 70px;
                    right: -30px;
                }

            }


            @media only screen and (max-width: 1440px) {

                .mailmint-anniv__image--left {
                    left: 40px;
                }

                .mailmint-anniv__image--left img {
                    width: 90%;
                }

                .mailmint-anniv__image--eid-mubarak img {
                    width: 90%;
                }

                .mailmint-anniv__image--wpfunnel-logo img {
                    width: 90%;
                }

                .mailmint-anniv__image--four img {
                    width: 90%;
                }

                .mailmint-anniv__image--right img {
                    width: 90%;
                }

                .mailmint-anniv__lead-text h2 {
                    font-size: 28px;
                }

                .mailmint-anniv__image--group {
                    gap: 25px;
                }

                .mailmint-anniv__content-area {
                    gap: 30px;
                    justify-content: center;
                }

                .mailmint-anniv__btn {
                    font-size: 16px;
                    font-weight: 400;
                    border-radius: 30px;
                    padding: 12px 16px;
                }

                .mailmint-anniv__btn-area svg {
                    position: absolute;
                    width: 60px;
                    right: -15px;
                    top: -15px;
                }

            }


            @media only screen and (max-width: 1399px) {

                .mailmint-anniv__image--left {
                    left: 20px;
                }

                .mailmint-anniv__image--left img {
                    max-width: 78px;
                    opacity: .35;
                }

                .mailmint-anniv__image--eid-mubarak img {
                    max-width: 100px;
                }

                .mailmint-anniv__image--wpfunnel-logo img {
                    max-width: 108px;
                }

                .mailmint-anniv__image--four img {
                    max-width: 173px;
                }

                .mailmint-anniv__image--right img {
                    max-width: 121.5px;
                }

                .mailmint-anniv__lead-text h2 {
                    font-size: 24px;
                }

                .mailmint-anniv__image--group {
                    gap: 20px;
                }

                .mailmint-anniv__content-area {
                    gap: 35px;
                }

                .mailmint-anniv__btn {
                    font-size: 14px;
                    font-weight: 600;
                    border-radius: 30px;
                    padding: 12px 16px;
                }

                .mailmint-anniv__btn-area svg {
                    width: 45px;
                    right: -13px;
                    top: -21px;
                }

                .mailmint-anniv__image--right {
                    right: -9px;
                    top: 56%;
                }

            }

            @media only screen and (max-width: 1024px) {

                .mailmint-anniv__image--left img {
                    max-width: 76.39px;
                }

                .mailmint-anniv__image--eid-mubarak img {
                    max-width: 90px;
                }

                .mailmint-anniv__image--wpfunnel-logo img {
                    max-width: 100px;
                }

                .mailmint-anniv__image--four img {
                    max-width: 173px;
                }

                .mailmint-anniv__image--right img {
                    max-width: 111.5px;
                }

                .mailmint-anniv__lead-text h2 {
                    font-size: 22px;
                }

                .mailmint-anniv__lead-text svg {
                    width: 25px;
                    margin-top: -10px;
                }


                .mailmint-anniv__content-area {
                    gap: 30px;
                }

                .mailmint-anniv__image--group {
                    gap: 15px;
                }

                .mailmint-anniv__btn {
                    font-size: 12px;
                    line-height: 1.2;
                    padding: 11px 12px;
                    font-weight: 400;
                }

                .mailmint-anniv__btn {
                    box-shadow: none;
                }

                .mailmint-anniv__image--right,
                .mailmint-anniv__image--left {
                    display: none;
                }

                .mailmint-anniv__btn-area svg {
                    width: 40px;
                    right: -15px;
                    top: -23px;
                }


            }

            @media only screen and (max-width: 991px) {

                .mailmint-anniv__container-area {
                    padding: 0 15px;
                }

                .mailmint-anniv__container-area {
                    justify-content: center;
                    gap: 20px;
                }

                .mailmint-tb__notification {
                    max-height: 190px;
                }

                .mailmint-anniv__image--left img {
                    max-width: 76.39px;
                }

                .mailmint-anniv__image--eid-mubarak img {
                    max-width: 92px;
                }

                .mailmint-anniv__image--wpfunnel-logo img {
                    max-width: 90px;
                }

                .mailmint-anniv__image--four img {
                    max-width: 163px;
                }

                .mailmint-anniv__image--right img {
                    max-width: 111.5px;
                }

                .mailmint-anniv__lead-text h2 {
                    font-size: 22px;
                }

                .mailmint-anniv__content-area {
                    gap: 30px;
                }

                .mailmint-anniv__image--group {
                    gap: 15px;
                }

                .mailmint-tb__notification .close-promotional-banner {
                    width: 25px;
                    height: 25px;
                }

                .mailmint-anniv__image--group {
                    gap: 20px;
                }

                .mailmint-anniv__image--left,
                .mailmint-anniv__image--right {
                    display: none;
                }

                .mailmint-anniv__btn {
                    font-size: 12px;
                    line-height: 1;
                    font-weight: 400;
                    padding: 10px 12px;
                    margin-left: 0;
                    box-shadow: none;
                }

                .mailmint-anniv__content-area {
                    display: contents;
                    gap: 25px;
                    text-align: center;
                    align-items: center;
                }

                .mailmint-anniv__lead-text svg {
                    width: 22px;
                    margin-top: -8px;
                }


            }

            @media only screen and (max-width: 767px) {

                .mailmint-anniv__image--right,
                .mailmint-anniv__image--left {
                    display: none;
                }

                .mailmint-anniv__stroke-font {
                    font-size: 16px;
                }

                .mailmint-anniv__content-area {
                    display: contents;
                    gap: 25px;
                    text-align: center;
                    align-items: center;
                }

                .mailmint-anniv__btn-area {
                    justify-content: center;
                    padding-top: 5px;
                }

                .mailmint-anniv__btn {
                    font-size: 12px;
                    padding: 15px 24px;
                }

                .mailmint-anniv__image--group {
                    gap: 10px;
                    padding: 0;
                }
            }


            /* Halloween */

            .mint-notification-counter {
                position: relative;
                background-image: url(<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/independence-day/bg.webp'); ?>);
                background-position: center;
                background-repeat: no-repeat;
                background-size: cover;
                object-fit: cover;
                background-color: #03031E;
                z-index: 1111;
                padding: 0;
            }

            .mint-notification-counter__container {
                position: relative;
                width: 100%;
                max-width: 1280px;
                margin: 0 auto;
                max-height: 100%;
                overflow: hidden;
                padding: 0;
            }

            .mint-notification-counter__content {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .mint-notification-counter__figure-logo {
                max-width: 218px;
                margin: 0;
                line-height: 0;
            }

            .mint-notification-counter__figure-occasion {
                max-width: 219px;
                margin: 0;
                line-height: 0;
            }

            .mint-notification-counter__figure-percentage {
                max-width: 108px;
                margin: 0;
                line-height: 0;
            }

            .mint-notification-counter__img {
                width: 100%;
                max-width: 100%;
            }

            .mint-notification-counter__list {
                display: flex;
                justify-content: center;
                gap: 10px;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .mint-notification-counter__item {
                margin: 0;
                display: flex;
                flex-direction: column;
                width: 56.14px;
                font-family: "Inter";
                font-size: 14px;
                font-style: normal;
                font-weight: 400;
                line-height: normal;
                letter-spacing: 0.56px;
                text-transform: uppercase;
                text-align: center;
                color: #111827;
            }

            .mint-notification-counter__time {
                font-size: 28px;
                font-family: "Inter";
                font-style: normal;
                font-weight: 700;
                line-height: normal;
                color: #fff;
                text-align: center;
                margin-bottom: 7px;
                border-radius: 10px;
                background: linear-gradient(200deg, #02C4FB 13.28%, #573BFF 100.5%);
                box-shadow: 0px 3px 0px 0px #3522A3;
            }

            .mint-notification-counter__btn-area {
                display: flex;
                align-items: flex-end;
                justify-content: flex-end;
            }

            .mint-notification-counter__btn {
                position: relative;
                font-family: "Inter";
                font-weight: 600;
                padding: 16px 30px;
                border-radius: 16px;
                font-size: 20px;
                line-height: normal;
                color: #FFF;
                text-align: center;
                filter: none;
                display: inline-block;
                cursor: pointer;
                text-transform: capitalize;
                background: #573BFF;
                text-decoration: none;
                transition: all 0.3s ease;
                box-shadow: none;
            }

            .mint-notification-counter__btn:hover {
                background-color: #201cfe;
                color: #ffffff;
            }

            .mint-notification-counter__stroke-font {
                font-size: 26px;
                font-family: "Inter";
                font-weight: 700;
            }

            /* Media Queries */
            @media only screen and (max-width: 1710px) {
                .mint-notification-counter__container {
                    max-width: 1200px;
                }

                .mint-notification-counter__figure-occasion {
                    max-width: 190px;
                }

                .mint-notification-counter__figure-logo {
                    max-width: 200px
                }

                .mint-notification-counter__figure-percentage {
                    max-width: 108px;
                }
            }

            @media only screen and (max-width: 1550px) {
                .mint-notification-counter__container {
                    max-width: 1140px;
                }

                .mint-notification-counter__figure-logo {
                    max-width: 190px;
                }

                .mint-notification-counter__figure-occasion {
                    max-width: 180px;
                }

                .mint-notification-counter__figure-percentage {
                    max-width: 100px;
                }

                .mint-notification-counter__btn {
                    padding: 12px 18px;
                    font-size: 17px;
                    border-radius: 12px;
                }
            }

            @media only screen and (max-width: 1440px) {
                .mint-notification-counter__container {
                    max-width: 1080px;
                }

                .mint-notification-counter__figure-logo {
                    max-width: 180px;
                }

                .mint-notification-counter__figure-occasion {
                    max-width: 160px;
                }

                .mint-notification-counter__figure-percentage {
                    max-width: 95px;
                }

                .mint-notification-counter__btn {
                    font-size: 16px;
                    padding: 13px 22px;
                    border-radius: 12px;
                }

                .mint-notification-counter__time {
                    display: flex;
                    width: 48px;
                    height: 40px;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 6px;
                    font-size: 20px;
                }

                .mint-notification-counter__list {
                    gap: 5px;
                }

                .mint-notification-counter__item {
                    width: 52px;
                    font-size: 13px;
                }
            }

            @media only screen and (max-width: 1399px) {
                .mint-notification-counter__container {
                    max-width: 930px;
                }

                .mint-notification-counter__figure-logo {
                    max-width: 170px;
                }

                .mint-notification-counter__figure-occasion {
                    max-width: 140px;
                }

                .mint-notification-counter__figure-percentage {
                    max-width: 80px;
                }

                .mint-notification-counter__btn {
                    font-size: 17px;
                    padding: 12px 20px;
                    border-radius: 10px;
                }

                .mint-notification-counter__time {
                    display: flex;
                    width: 40px;
                    height: 32px;
                    font-size: 20px;
                    border-radius: 8px;
                }

                .mint-notification-counter__list {
                    gap: 5px;
                }

                .mint-notification-counter__item {
                    font-size: 12px;
                    align-items: center;
                    width: 45px;
                }

                /* .mint-notification-counter__btn {
                    font-size: 16px !important;
                    padding: 12px 18px !important;
                    border-radius: 10px !important;
                } */
            }

            @media only screen and (max-width: 1199px) {
                .mint-notification-counter__container {
                    max-width: 860px;
                }

                .mint-notification-counter__stroke-font {
                    font-size: 20px;
                }

                .mint-notification-counter__figure-logo {
                    max-width: 150px;
                }

                .mint-notification-counter__figure-occasion {
                    max-width: 130px;
                }

                .mint-notification-counter__figure-percentage {
                    max-width: 75px;
                }

                .mint-notification-counter__btn {
                    font-size: 16px;
                    padding: 12px 20px;
                }

                .mint-notification-counter__list {
                    gap: 0;
                }

                .mint-notification-counter__time {
                    display: flex;
                    width: 40px;
                    height: 32px;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 6px;
                }

                .mint-notification-counter__time {
                    font-size: 18px;
                }
            }

            @media only screen and (max-width: 1024px) {
                .mint-notification-counter__container {
                    max-width: 730px;
                }

                .mint-notification-counter__figure-logo {
                    max-width: 130px;
                    margin: 0;
                }

                .mint-notification-counter__figure-occasion {
                    max-width: 105px;
                }

                .mint-notification-counter__figure-percentage {
                    max-width: 70px;
                }

                /* .mint-notification-counter__btn {
                    font-size: 16px !important;
                    padding: 10px 14px !important;
                    border-radius: 10px !important;
                } */

                .mint-notification-counter__time {
                    width: 38px;
                    height: 30px;
                }

                .mint-notification-counter__item {
                    font-size: 12px;
                }
            }

            @media only screen and (max-width: 991px) {
                .mint-notification-counter__container {
                    max-width: 690px;
                }

                .mint-notification-counter__stroke-font {
                    font-size: 20px;
                }

                .wpf-notification-counter__figure-logo {
                    max-width: 130px;
                }

                .mint-notification-counter__figure-occasion {
                    max-width: 105px;
                }

                .mint-notification-counter__figure-percentage {
                    max-width: 60px;
                }

                /* .mint-notification-counter__btn {
                    font-size: 14px !important;
                    padding: 10px 12px !important;
                } */

                .mint-notification-counter__time {
                    font-size: 16px;
                }

                .mint-notification-counter__list {
                    gap: 0;
                }

                .mint-notification-counter__time {
                    display: flex;
                    width: 35px;
                    height: 27px;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 6px;
                }

                .mint-notification-counter__item {
                    font-size: 12px;
                    width: 45px;
                }
            }

            /* Creator LMS early bird banner design */
            .mint-lms-earlybird-banner {
                .mint-lms-earlybird-banner-container {
                    display: flex;
                    align-items: center;
                    background: #F7F7FF;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    padding: 14px 18px;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
                    position: relative;
                    font-family: 'Inter', sans-serif;
                    position: relative;

                    &::before {
                        content: "";
                        position: absolute;
                        left: 0px;
                        top: 0;
                        height: 100%;
                        width: 6px;
                        background: #6E42D3;
                        border-radius: 30px;
                    }

                    .mailmint-banner__icon {
                        flex-shrink: 0;
                        margin-right: 20px;

                        img {
                            max-width: 85px;
                            width: 100%;
                        }
                    }

                    .mailmint-banner__content {
                        flex: 1;

                        p {
                            margin: 0;
                            font-size: 14px;
                            color: #374151;
                            font-weight: 500;

                            strong {
                                color: #6E42D3;
                            }
                        }

                        .mailmint-banner__actions {
                            margin-top: 8px;
                            display: flex;
                            flex-wrap: wrap;

                            .mint-notification-counter__btn-area {
                                margin-right: 8px;
                                margin-bottom: 6px;
                            }

                            .btn {
                                font-size: 13px;
                                font-weight: 500;
                                padding: 6px 14px;
                                border-radius: 4px;
                                cursor: pointer;
                                border: none;
                                text-decoration: none;
                                display: inline-block;
                                transition: all 0.2s ease;

                                &:focus {
                                    box-shadow: none;
                                    outline: none;
                                }

                                &.btn-primary {
                                    background: #6E42D3;
                                    color: #fff;

                                    &:hover {
                                        background: darken(#ef4444, 8%);
                                    }
                                }

                                &.btn-secondary {
                                    background: #f0f1f3ff;
                                    color: #374151;

                                    &:hover {
                                        background: darken(#f3f4f6, 5%);
                                    }
                                }
                            }
                        }
                    }

                    .mailmint-banner__close {
                        position: absolute;
                        top: 8px;
                        right: 10px;
                        border: none;
                        background: transparent;
                        font-size: 18px;
                        color: #9ca3af;
                        cursor: pointer;

                        &:hover {
                            color: #4b5563;
                        }
                    }
                }

                /* ================== RESPONSIVENESS ================== */
                @media (max-width: 1550px) {
                    .mint-lms-earlybird-banner-container {
                        padding: 12px 16px;

                        .mailmint-banner__content p {
                            font-size: 13.5px;
                        }
                    }
                }

                @media (max-width: 1399px) {
                    .mint-lms-earlybird-banner-container {
                        .mailmint-banner__content p {
                            font-size: 13px;
                        }

                        .mailmint-banner__actions .btn {
                            font-size: 12.5px;
                            padding: 6px 12px;
                        }
                    }
                }

                @media (max-width: 1199px) {
                    .mint-lms-earlybird-banner-container {
                        .mailmint-banner__content p {
                            font-size: 12px;
                        }

                        .mailmint-banner__icon {
                            margin-right: 15px;
                            margin-bottom: 0;
                        }

                        .mailmint-banner__actions {
                            margin-top: 6px !important;

                            .btn {
                                margin-right: 6px;
                                margin-bottom: 6px;
                                font-size: 12px !important;
                                padding: 5px 14px !important;
                            }
                        }
                    }
                }

                @media (max-width: 991px) {
                    .mint-lms-earlybird-banner-container {
                        padding: 12px 30px;
                        flex-direction: column;
                        text-align: center;

                        .mailmint-banner__content p {
                            font-size: 12px;
                            line-height: 1.4;
                        }

                        .mailmint-banner__icon {
                            img {
                                max-width: 65px;
                                width: 100%;
                            }
                        }

                        .mailmint-banner__actions {
                            margin-top: 10px !important;
                            align-items: center;
                            justify-content: center;

                            .btn {
                                width: 100%;
                                margin-right: 0;
                            }
                        }
                    }
                }
            }


        </style>

<?php
    }
}
