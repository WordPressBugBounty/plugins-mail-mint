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

        if (defined('MAIL_MINT_PRO_VERSION') || ($current_date_time < $this->start_date || $current_date_time > $this->end_date) || 'no' === get_option('_is_mint_eid_promotion') || MrmCommon::is_wpfnl_active() || 'no' === get_option('_is_wpfnl_eid_promotion')) {
            return;
        }

        // Calculate the time remaining in seconds
        $time_remaining = $this->end_date - $current_date_time;

?>



        <!-- Name: WordPress Anniversary Notification Banner -->
        <div class="<?php echo esc_attr($this->occasion); ?>-banner notice">
            <div class="mailmint-promotional-banner">
                <div class="mailmint-tb__notification">

                    <div class="banner-overflow">
                        <div class="mailmint-anniv__container-area">

                            <div class="mailmint-anniv__image mailmint-anniv__image--left">


                                <figure>
                                    <img src="<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/banner-image/eid-ul-adha-moon.webp'); ?>"
                                        alt="Eid Ul Adha Moon" />
                                </figure>
                            </div>

                            <div class="mailmint-anniv__content-area">


                                <div class="mailmint-anniv__image--group">

                                    <div class='mailmint-anniv__image mailmint-anniv__image--eid-mubarak'>
                                        <figure>
                                            <img src="<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/banner-image/eid-utl-adha-text.webp'); ?>"
                                                alt="Eid Ul Adha Moon" />
                                        </figure>
                                    </div>

                                    <div class='mailmint-anniv__image mailmint-anniv__image--wpfunnel-logo'>
                                        <figure>
                                            <img src="<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/banner-image/eid-ul-adha-mailMint.webp'); ?>"
                                                alt="Mail Mint Logo" />
                                        </figure>
                                    </div>

                                    <div class="mailmint-anniv__image mailmint-anniv__image--four">
                                        <figure>
                                            <img src="<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/banner-image/eid-ul-adha-tweenty.webp'); ?>"
                                                alt="Eid Ul Adha Discount" />
                                        </figure>
                                    </div>



                                    <div class="mailmint-anniv__text-divider">

                                        <div class="mailmint-anniv__lead-text">
                                            <span>
                                                <svg width="33" height="30" fill="none" viewBox="0 0 33 30"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path fill="#FFFFFF" stroke="#FFFFFF"
                                                        d="M28.584 25.483a257.608 257.608 0 00-.525-1.495c-.28-.795-.569-1.614-.769-2.199a1.432 1.432 0 01-.084-.552c.014-.211.106-.57.487-.726a.828.828 0 01.416-.064.754.754 0 01.38.161c.139.11.248.274.309.366l.02.032.003.004c.127.191.203.355.265.49l.04.09.572 1.176a185.411 185.411 0 011.49 3.11c.193.412.306.86.404 1.245l.027.106h0c.077.301.093.67-.128.977-.224.313-.587.415-.925.429h0a54.91 54.91 0 01-3.43.022h-.001l-.166-.003c-1.395-.027-2.84-.055-4.268-.29h-.003c-.312-.053-.574-.138-.78-.299a1.212 1.212 0 01-.371-.523l-.01-.024-.008-.024a.692.692 0 01.175-.694c.137-.136.31-.205.428-.243.248-.08.538-.105.687-.117a5.511 5.511 0 011.039 0c.766.051 1.528.104 2.297.157l.16.01c-5.037-2.4-9.838-5.23-14.007-9.083C7.962 13.508 4.206 9.005 1.53 3.652h0l-.002-.004-.02-.04c-.183-.377-.397-.817-.517-1.283A2.45 2.45 0 00.985 2.3c-.025-.088-.08-.28-.068-.479.016-.273.144-.526.401-.728l.027-.02.029-.018a.729.729 0 01.792.026c.18.117.325.3.442.47.17.24.35.506.507.787l.001.002c2.4 4.35 5.404 8.244 8.893 11.79l-.343.338.343-.338c4.39 4.463 9.63 7.735 15.16 10.655.463.242.93.466 1.415.697z" />
                                                </svg>
                                            </span>

                                            <h2 class="mailmint-wp-anniversary__title-end">
                                                <?php echo __("Ends <br> Soon", 'wpfnl') ?>
                                            </h2>

                                        </div>

                                    </div>

                                </div>

                                <!-- .mailmint-anniv__image end -->
                                <div class="mailmint-anniv__btn-area">

                                    <a href="https://getwpfunnels.com/email-marketing-automation-mail-mint/?utm_source=mm-plugin&utm_medium=banner-cta&utm_campaign=eid2024#price" role="button" class="mailmint-anniv__btn"
                                        target="_self">
                                        <?php echo __('Get It Now', 'mrm') ?>
                                    </a>
                                    <svg width="70" height="63" viewBox="0 0 81 91" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M4.60726 7.08212C5.63427 5.17372 6.26181 3.5466 6.19789 1.74546C5.24817 1.083 4.10149 0.76304 2.94599 0.838064C2.93589 2.70538 2.37727 4.52856 1.33954 6.08101C2.82572 5.97643 3.71203 6.14281 4.60726 7.08212Z"
                                            fill="white" />
                                        <path
                                            d="M67.7906 55.9479C66.0001 55.8367 64.2446 54.9871 61.2859 51.7876C60.0803 53.3512 59.4088 55.2606 59.3701 57.2345C61.4304 59.134 63.9435 60.4731 66.6691 61.124C66.4742 59.3228 66.8676 57.5069 67.7906 55.9479Z"
                                            fill="white" />
                                        <path
                                            d="M60.7242 14.3636C58.8533 15.8746 56.7019 17.0004 54.3939 17.6763C54.6919 18.9167 55.3888 20.025 56.3776 20.8311C58.7543 20.1875 61.0193 19.1862 63.0949 17.8615C61.3805 16.7802 60.6869 15.9535 60.7242 14.3636Z"
                                            fill="white" />
                                        <path
                                            d="M79.4201 90.0494C79.4696 88.9021 79.7633 87.7785 80.2816 86.7538C78.014 86.8946 75.7377 86.7304 73.5138 86.2657C72.7494 87.1074 72.329 88.2054 72.3358 89.3423C74.6605 89.8601 77.0389 90.0975 79.4201 90.0494Z"
                                            fill="white" />
                                    </svg>


                                </div>

                            </div>

                            <div class="mailmint-anniv__image mailmint-anniv__image--right">
                                <figure>
                                    <img src="<?php echo esc_url(MRM_DIR_URL . 'admin/assets/images/banner-image/eid-ul-adha-right.webp'); ?>"
                                        alt="Eid-ul-adha-mosque" />
                                </figure>
                            </div>

                        </div>

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

            // Update the countdown every second
            // setInterval(function() {
            //     var countdownElement    = document.getElementById('mailmint_countdown');
            //     var daysElement         = document.getElementById('mailmint_days');
            //     var hoursElement        = document.getElementById('mailmint_hours');
            //     var minutesElement      = document.getElementById('mailmint_minutes');

            //     // Decrease the remaining time
            //     timeRemaining--;

            //     // Calculate new days, hours, and minutes
            //     var days = Math.floor(timeRemaining / (60 * 60 * 24));
            //     var hours = Math.floor((timeRemaining % (60 * 60 * 24)) / (60 * 60));
            //     var minutes = Math.floor((timeRemaining % (60 * 60)) / 60);


            //     // Format values with leading zeros
            //     days = (days < 10) ? '0' + days : days;
            //     hours = (hours < 10) ? '0' + hours : hours;
            //     minutes = (minutes < 10) ? '0' + minutes : minutes;

            //     // Update the HTML
            //     daysElement.textContent = days;
            //     hoursElement.textContent = hours;
            //     minutesElement.textContent = minutes;

            //     // Check if the countdown has ended
            //     if (timeRemaining <= 0) {
            //         countdownElement.innerHTML = 'Campaign Ended';
            //     }
            // }, 1000); // Update every second
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

            @font-face {
                font-family: 'Syncopate';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Syncopate-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Abril Fatface';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/AbrilFatface-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Alegreya';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Alegreya-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Alegreya Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/AlegreyaSans-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Anton';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Anton-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Arimo';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Arimo-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Arvo';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Arvo-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Catamaran';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Catamaran-Thin.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Della Respira';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/DellaRespira-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'DM Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/DMSans-9ptRegular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Gilda Display';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/GildaDisplay-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Lato';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Lato-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Lora';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Lora-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Marcellus';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Marcellus-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Merriweather';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Merriweather-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Merriweather Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/MerriweatherSans-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Montserrat';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Montserrat.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Nanum Gothic Coding';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/NanumGothicCoding.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Open Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/OpenSans-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Neuton';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Neuton-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Noticia Text';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/NoticiaText-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Noto Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/NotoSans-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Noto Sans Georgian';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/NotoSansGeorgian-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Playfair Display';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/PlayfairDisplay-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Recursive Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/RecursiveSans-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Roboto';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Roboto-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Source Code Roman';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/SourceCodeRoman.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Source Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/SourceSans.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Space Mono';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/SpaceMono-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Tiro Bangla';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/TiroBangla-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Work Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/WorkSans-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Raleway';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Raleway.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Poppins';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Poppins-Regular.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Josefin Sans';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/JosefinSans-VariableFont_wght.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Quicksand';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/Quicksand-VariableFont_wght.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Jeanne Moderno';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/jeanne-moderno.woff2'; ?>) format("woff2");
            }

            @font-face {
                font-family: 'Jeanne Moderno';
                src: url(<?php echo MRM_DIR_URL . 'admin/assets/fonts/jeanne-moderno.woff'; ?>) format("woff");
            }


            .mailmint-tb__notification,
            .mailmint-tb__notification * {
                box-sizing: border-box;
            }

            .mailmint-tb__notification {
                width: calc(100% - 20px);
                margin: 20px 0 20px;
                background-image: url(<?php echo MRM_DIR_URL . 'admin/assets/images/banner-image/notification-br-bg.webp'; ?>);
                background-repeat: no-repeat;
                background-size: cover;
                position: relative;
                border: none;
                box-shadow: none;
                display: block;
                max-height: 110px;
            }

            .mailmint-tb__notification .banner-overflow {
                overflow: hidden;
                position: relative;
                width: 100%;
            }

            .wp-anniversary-banner.notice {
                border: none;
                padding: 0;
                display: block;
                background: transparent;
                margin: 0;
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

                .mailmint-tb__notification {
                    max-height: 99px;
                }

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

                .mailmint-tb__notification {
                    max-height: 79px;
                }

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
                .mailmint-tb__notification {
                    max-height: 75px;
                }

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

            @media only screen and (max-width: 768px) {

                .mailmint-tb__notification {
                    margin: 60px 0 20px;
                }

                .mailmint-anniv__container-area {
                    padding: 0 15px;
                }

                .mailmint-anniv__container-area {
                    justify-content: center;
                    gap: 20px;
                }

                .mailmint-tb__notification {
                    max-height: 64px;
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
                .wpvr-promotional-banner {
                    padding-top: 20px;
                    padding-bottom: 30px;
                    max-height: none;
                }

                .wpvr-promotional-banner {
                    max-height: none;
                }

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
        </style>

<?php
    }
}
