<?php
/**
 * Storage for Form Template Controller
 *
 * Core base controller for managing and interacting with REST API items.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace MailMint\App\Internal\FormBuilder;

use Mint\Mrm\Internal\Traits\Singleton;
/**
 * This is the Class for Form template
 *
 * @package MailMint\App\Internal\FormBuilder
 */
class Storage {

	use Singleton;

	/**
	 * Set All Template In array.
	 *
	 * @return \string[][]
	 */
	public static function get_form_templates() {
		$image_path = plugins_url( 'img', __FILE__ );
		$forms      = array(
			// ----10% discount on burger (popup)------
			array(
				'id'              => '1',
				'title'           => __( 'Coupon Discount', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/ten-percent-discount.jpg',
				'content'         => '<!-- wp:columns {"verticalAlignment":"center","backgroundColor":"black"} -->
                <div class="wp-block-columns are-vertically-aligned-center has-black-background-color has-background"><!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"id":236,"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image size-full"><img src="' . $image_path . '/content_image/10-percent-off-on-burger.png" alt="" class="wp-image-236"/></figure>
                <!-- /wp:image --></div>
                <!-- /wp:column -->

                <!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":50}},"textColor":"white"} -->
                <h1 class="has-text-align-center has-white-color has-text-color" style="font-size:50px">' . __( '10% Off Your Next', 'mrm' ) . ' <mark style="background-color:rgba(0, 0, 0, 0);color:#d68743" class="has-inline-color">Order</mark></h1>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"textColor":"white","fontSize":"small"} -->
                <p class="has-text-align-center has-white-color has-text-color has-small-font-size" style="font-style:normal;font-weight:400">' . __( 'Subscribe to our valued customer\'s list and claim your coupon to get a 10% discount the next time you dine in', 'mrm' ) . '.</p>
                <!-- /wp:paragraph -->

				<!-- wp:mrmformfield/email-field-block {"inputTextColor":"#9398a5","inputBgColor":"#000000","inputBorderRadius":8,"inputBorderColor":"#232323"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#000000;color:#9398a5;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#232323" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonBgColor":"#d68743","buttonBorderRadius":8,"buttonText":"Subscribe Now","buttonWidth":100,"buttonFontSize":18,"paddingTopBottom":20} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" type="submit" aria-label="Submit" style="background-color:#d68743;color:;border-radius:8px;padding:20px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:18px;border-width:0;border-color:;width:100%">' . __( 'Subscribe Now', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2022-12-22","time":"11:52:52"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""}}}',
			),

			// ----sofa (popup)------
			array(
				'id'              => '2',
				'title'           => __( 'First Order Discount', 'mrm' ),
				'form_position'   => 'Pop-ups',
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/sofa.jpg',
				'content'         => '<!-- wp:columns {"verticalAlignment":"center","style":{"border":{"radius":"16px"}},"backgroundColor":"white"} -->
                <div class="wp-block-columns are-vertically-aligned-center has-white-background-color has-background" style="border-radius:16px"><!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"id":254,"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image size-full"><img src="' . $image_path . '/content_image/sofa.png" alt="" class="wp-image-254"/></figure>
                <!-- /wp:image --></div>
                <!-- /wp:column -->


                <!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":50}},"textColor":"primary"} -->
                <h1 class="has-text-align-center has-primary-color has-text-color" style="font-size:50px">' . __( 'Get 10% OFF', 'mrm' ) . '</h1>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"500"},"color":{"text":"#9398a5"}},"fontSize":"small"} -->
                <p class="has-text-align-center has-text-color has-small-font-size" style="color:#9398a5;font-style:normal;font-weight:500">' . __( 'Sign up for a 10% discount on your first order', 'mrm' ) . '!</p>
                <!-- /wp:paragraph -->

				<!-- wp:mrmformfield/email-field-block {"inputTextColor":"#9398a5","inputBgColor":"#fff","inputBorderRadius":8,"inputBorderColor":"#e9e9e9"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#fff;color:#9398a5;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#e9e9e9" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonTextColor":"#FFFFFF","buttonBgColor":"#70a197","buttonBorderRadius":8,"buttonText":"Subscribe Now","buttonWidth":100,"typography":{"openTypography":true,"weight":600,"family":"Arial","type":"sans-serif"},"buttonFontSize":18,"paddingTopBottom":17} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#70a197;color:#FFFFFF;border-radius:8px;padding:17px 20px;line-height:1;letter-spacing:0;border-style:none;font-weight:600;font-family:Arial;font-size:18px;border-width:0;border-color:;width:100%">' . __( 'Subscribe Now', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2022-12-22","time":"11:11:48"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""}}}',
			),

			// ------yoga (fixed on bottom)------
			array(
				'id'              => '3',
				'title'           => __( 'Ticket Discount - Yoga Summit', 'mrm' ),
				'form_position'   => 'Fixed on bottom',
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'featured_image'  => $image_path . '/yoga.jpg',
				'type'            => 'free',
				'content'         => '<!-- wp:columns {"verticalAlignment":"center","style":{"color":{"background":"#fd7e6d"}}} -->
				<div class="wp-block-columns are-vertically-aligned-center has-background" style="background-color:#fd7e6d"><!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"style":{"typography":{"fontSize":24,"fontStyle":"normal","fontWeight":"700"}},"textColor":"white"} -->
				<h2 class="wp-block-heading has-white-color has-text-color" style="font-size:24px;font-style:normal;font-weight:700">' . __( 'Save 50% on Yoga Summit tickets until December 31', 'mrm' ) . '.</h2>
				<!-- /wp:heading --></div>
				<!-- /wp:column -->
				
				<!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:columns {"verticalAlignment":null} -->
				<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/email-field-block {"rowSpacing":0,"labelSpacing":0} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:0px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:0px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:5px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block --></div>
				<!-- /wp:column -->
				
				<!-- wp:column -->
				<div class="wp-block-column"><!-- wp:mrmformfield/mrm-button-block {"rowSpacing":0,"buttonTextColor":"#FFFFFF","buttonBgColor":"#000000","paddingTopBottom":13} -->
				<div class="mrm-form-group submit" style="margin-bottom:0px;text-align:left"><button class="mrm-submit-button mintmrm-btn" type="submit" aria-label="Submit" style="background-color:#000000;color:#FFFFFF;border-radius:5px;padding:13px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:%">' . __( 'Submit', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px"></div><div class="response"></div></div>
				<!-- /wp:mrmformfield/mrm-button-block --></div>
				<!-- /wp:column --></div>
				<!-- /wp:columns --></div>
				<!-- /wp:column --></div>
				<!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"fixed-on-bottom","form_animation":"fade-in","close_button_color":"#333333","close_background_color":"#fd7e6d"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2022-12-22","time":"11:55:58"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""}}}',
			),

			// ------don't miss out (flyins)------
			array(
				'id'              => '4',
				'title'           => 'Join The Deals\' List',
				'form_position'   => 'Fly-ins',
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/dont-miss-out.jpg',
				'content'         => '<!-- wp:group {"layout":{"type":"constrained","wideSize":"436px"}} -->
                <div class="wp-block-group"><!-- wp:columns -->
                <div class="wp-block-columns"><!-- wp:column {"width":"100%"} -->
                <div class="wp-block-column" style="flex-basis:100%"><!-- wp:group {"layout":{"type":"constrained"}} -->
                <div class="wp-block-group"><!-- wp:group {"layout":{"type":"constrained"}} -->
                <div class="wp-block-group"><!-- wp:columns {"backgroundColor":"white"} -->
                <div class="wp-block-columns has-white-background-color has-background"><!-- wp:column {"verticalAlignment":"top"} -->
                <div class="wp-block-column is-vertically-aligned-top"><!-- wp:image {"align":"center","id":256,"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image aligncenter size-full"><img src="' . $image_path . '/content_image/envelope.png" alt="" class="wp-image-256"/></figure>
                <!-- /wp:image -->

                <!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#2d3149"}},"fontSize":"larger"} -->
                <h2 class="has-text-align-center has-text-color has-larger-font-size" style="color:#2d3149;font-style:normal;font-weight:700">' . __( 'Don\'t Miss Out', 'mrm' ) . '</h2>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":16},"color":{"text":"#9398a5"}}} -->
                <p class="has-text-align-center has-text-color" style="color:#9398a5;font-size:16px">' . __( 'Subscribe to get exclusive deals sent directly to your email', 'mrm' ) . '.</p>
                <!-- /wp:paragraph -->

				<!-- wp:mrmformfield/email-field-block {"inputBorderRadius":8,"inputBorderColor":"#e2e2e2"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#e2e2e2" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonTextColor":"#ffffff","buttonBgColor":"#296763","buttonBorderRadius":8,"buttonText":"Subscribe","buttonAlign":"center","buttonWidth":100,"buttonFontSize":20} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:center"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#296763;color:#ffffff;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:20px;border-width:0;border-color:;width:100%">' . __( 'Subscribe', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns --></div>
                <!-- /wp:group --></div>
                <!-- /wp:group --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns --></div>
                <!-- /wp:group -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"flyins","form_animation":"slide-in-up","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2022-12-22","time":"11:01:18"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""}}}',
			),

			// ------Newsletter (flyins)------
			array(
				'id'              => '5',
				'title'           => __( 'Newsletter Subscription', 'mrm' ),
				'form_position'   => 'Fly-ins',
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/newsletter.jpg',
				'content'         => '<!-- wp:group {"layout":{"type":"constrained","wideSize":"436px"}} -->
                <div class="wp-block-group"><!-- wp:columns -->
                <div class="wp-block-columns"><!-- wp:column {"width":"100%"} -->
                <div class="wp-block-column" style="flex-basis:100%"><!-- wp:group {"layout":{"type":"constrained"}} -->
                <div class="wp-block-group"><!-- wp:group {"layout":{"type":"constrained"}} -->
                <div class="wp-block-group"><!-- wp:columns {"backgroundColor":"white"} -->
                <div class="wp-block-columns has-white-background-color has-background"><!-- wp:column {"verticalAlignment":"top"} -->
                <div class="wp-block-column is-vertically-aligned-top"><!-- wp:image {"align":"center","id":259,"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image aligncenter size-full"><img src="' . $image_path . '/content_image/envelope2.png" alt="" class="wp-image-259"/></figure>
                <!-- /wp:image -->

                <!-- wp:heading {"textAlign":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"700","fontSize":"32px"},"color":{"text":"#2d3149"}}} -->
                <h2 class="has-text-align-center has-text-color" style="color:#2d3149;font-size:32px;font-style:normal;font-weight:700">' . __( 'Subscribe To Our Newsletter', 'mrm' ) . '</h2>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":16},"color":{"text":"#9398a5"}}} -->
                <p class="has-text-align-center has-text-color" style="color:#9398a5;font-size:16px">' . __( 'Join our mailing list to receive the latest news and updates from our team', 'mrm' ) . '.</p>
                <!-- /wp:paragraph -->

				<!-- wp:mrmformfield/email-field-block {"inputBorderRadius":8,"inputBorderColor":"#e2e2e2"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#e2e2e2" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonTextColor":"#ffffff","buttonBgColor":"#6e42d3","buttonBorderRadius":8,"buttonText":"Subscribe","buttonAlign":"center","buttonWidth":100,"typography":{"openTypography":true,"weight":600,"family":"Arial","type":"sans-serif"},"buttonFontSize":20} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:center"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#6e42d3;color:#ffffff;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-weight:600;font-family:Arial;font-size:20px;border-width:0;border-color:;width:100%">' . __( 'Subscribe', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns --></div>
                <!-- /wp:group --></div>
                <!-- /wp:group --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns --></div>
                <!-- /wp:group -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"flyins","form_animation":"slide-in-up","close_button_color":"#ffffff","close_background_color":"#888d93"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2022-12-22","time":"11:39:39"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""}}}',
			),

			// It's pro
			// ------Black Friday Sale (popup)------!

			array(
				'id'              => '6',
				'title'           => __( 'Black Friday Sale', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/black_friday.jpg',
			),

			// ------Dive In (popup)------!
			array(
				'id'              => '7',
				'title'           => __( 'Dive In!', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/dive_in.jpg',
			),

			// ------Scared In (popup)------!
			array(
				'id'              => '8',
				'title'           => __( 'Boo! Don\'t Be Scared!', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/Boo.jpeg',
			),

			// It's free.
			// ------Get Free Shipping Fast (popup)------!

			array(
				'id'              => '9',
				'title'           => __( 'Get Free Shipping Fast', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/shipping.jpeg',
				'content'         => ' <!-- wp:columns {"backgroundColor":"white"} -->
				<div class="wp-block-columns has-white-background-color has-background"><!-- wp:column {"verticalAlignment":"top","width":""} -->
				<div class="wp-block-column is-vertically-aligned-top"><!-- wp:cover {"url":"' . $image_path . '/content_image/Shipping-Fast.png","id":55,"dimRatio":0,"focalPoint":{"x":0.34,"y":0.43},"isDark":false,"style":{"color":{}}} -->
				<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-55" alt="" src="' . $image_path . '/content_image/Shipping-Fast.png" style="object-position:34% 43%" data-object-fit="cover" data-object-position="34% 43%"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
				<p class="has-text-align-center has-large-font-size"></p>
				<!-- /wp:paragraph --></div></div>
				<!-- /wp:cover -->

                <!-- wp:columns -->
                <div class="wp-block-columns"><!-- wp:column -->
                <div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":56,"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#323232"},"spacing":{"margin":{"top":"20px"}}}} -->
                <h1 class="has-text-align-center has-text-color" style="color:#323232;margin-top:20px;font-size:56px;font-style:normal;font-weight:700">' . __( 'Get Free Shipping Fast', 'mrm' ) . '</h1>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"color":{"text":"#9398a5"},"spacing":{"margin":{"top":"10px","right":"0px","bottom":"30px","left":"0px"}},"typography":{"fontStyle":"normal","fontWeight":"400"}},"fontSize":"medium"} -->
                <p class="has-text-align-center has-text-color has-medium-font-size" style="color:#9398a5;margin-top:10px;margin-right:0px;margin-bottom:30px;margin-left:0px;font-style:normal;font-weight:400">' . __( 'And there’s more to that—be the first to hear about', 'mrm' ) . '<br> ' . __( 'exclusive deals and new arrivals', 'mrm' ) . '.</p>

                <!-- /wp:paragraph -->

				<!-- wp:mrmformfield/email-field-block {"emailPlaceholder":"Enter your email","rowSpacing":20,"inputBorderRadius":8,"inputPaddingTop":15,"inputPaddingRight":20,"inputPaddingBottom":15,"inputPaddingLeft":20} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:20px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Enter your email', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:15px;padding-right:20px;padding-bottom:15px;padding-left:20px;border-style:solid;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonTextColor":"#323232","buttonBgColor":"#ffc82c","buttonBorderRadius":8,"buttonText":"Get Free Shipping","buttonWidth":100,"typography":{"openTypography":true,"weight":600},"buttonFontSize":20} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#ffc82c;color:#323232;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-weight:600;font-size:20px;border-width:0;border-color:;width:100%">' . __( 'Get Free Shipping', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":16},"color":{"text":"#333333d1"}}} -->
                <p class="has-text-align-center has-text-color" style="color:#333333d1;font-size:16px">' . __( 'We don’t spam! Read more in our', 'mrm' ) . '<a href="#" style="color:#323232cc;">' . __( 'privacy policy', 'mrm' ) . ' </a></p>
                <!-- /wp:paragraph --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-02-13","time":"11:08:35"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
			),
			// It's free.
			// ------Don’t Miss Out! (popup)------!

			array(
				'id'              => '10',
				'title'           => __( 'Don’t Miss Out!', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/miss_out.jpg',
				'content'         => '<!-- wp:columns {"backgroundColor":"white"} -->
				<div class="wp-block-columns has-white-background-color has-background"><!-- wp:column {"verticalAlignment":"top"} -->
				<div class="wp-block-column is-vertically-aligned-top"><!-- wp:cover {"url":"' . $image_path . '/content_image/Dont-Miss-Out-3.png","id":23,"dimRatio":0,"focalPoint":{"x":0.59,"y":0.52},"style":{"color":{}}} -->
				<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><img class="wp-block-cover__image-background wp-image-23" alt="" src="' . $image_path . '/content_image/Dont-Miss-Out-3.png" style="object-position:59% 52%" data-object-fit="cover" data-object-position="59% 52%"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
				<p class="has-text-align-center has-large-font-size"></p>
				<!-- /wp:paragraph --></div></div>
				<!-- /wp:cover -->

                <!-- wp:columns -->
                <div class="wp-block-columns"><!-- wp:column -->
                <div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","fontSize":56},"color":{"text":"#232b69"},"spacing":{"margin":{"top":"30px","bottom":"0px"}}}} -->
                <h1 class="has-text-align-center has-text-color" style="color:#232b69;margin-top:30px;margin-bottom:0px;font-size:56px;font-style:normal;font-weight:700">' . __( 'Don\'t Miss Out', 'mrm' ) . '</h1>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"color":{"text":"#9398a5"},"spacing":{"margin":{"bottom":"25px","top":"10px"}}},"fontSize":"medium"} -->
                <p class="has-text-align-center has-text-color has-medium-font-size" style="color:#9398a5;margin-top:10px;margin-bottom:25px">' . __( 'Subscribe to our email newsletter today to receive updates on', 'mrm' ) . '<br> ' . __( 'the latest news, tutorials, and amazing offers', 'mrm' ) . ' !</p>
                <!-- /wp:paragraph -->

                <!-- wp:mrmformfield/email-field-block {"inputBorderRadius":8,"inputPaddingTop":12,"inputPaddingBottom":12} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:12px;padding-right:14px;padding-bottom:12px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonBgColor":"#1f115e","buttonBorderRadius":8,"buttonText":"Join The Club","buttonWidth":100,"typography":{"openTypography":true,"weight":600},"buttonFontSize":18} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#1f115e;color:;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-weight:600;font-size:18px;border-width:0;border-color:;width:100%">' . __( 'Join The Club', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":16},"color":{"text":"#9398a5"}}} -->
                <p class="has-text-align-center has-text-color" style="color:#9398a5;font-size:16px">' . __( 'We don’t spam! Read more in our', 'mrm' ) . '<a href="#" style="color:#9398a5;"> ' . __( 'privacy policy', 'mrm' ) . '</a></p>
                <!-- /wp:paragraph --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-02-13","time":"11:08:35"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
			),

			array(
				'id'              => '11',
				'title'           => 'Fashion Store Newsletter',
				'form_position'   => 'Default',
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/fashionable-img.jpeg',
				'content'         => '<!-- wp:columns {"verticalAlignment":"center","backgroundColor":"black","fontSize":"large"} -->
                <div class="wp-block-columns are-vertically-aligned-center has-black-background-color has-background has-large-font-size"><!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"id":187,"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image size-full"><img src="' . $image_path . '/content_image/first-order-image.png" alt="" class="wp-image-187"/></figure>
                <!-- /wp:image --></div>
                <!-- /wp:column -->

                <!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":36,"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"0px"}}},"textColor":"white"} -->
                <h1 class="wp-block-heading has-text-align-center has-white-color has-text-color" style="margin-top:0px;font-size:36px;font-style:normal;font-weight:600">' . __( 'Stay Fashionably Informed', 'mrm' ) . '!</h1>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"400","fontSize":16},"color":{"text":"#848484"},"spacing":{"margin":{"bottom":"25px","right":"30px","left":"30px","top":"16px"}}}} -->
                <p class="has-text-align-center has-text-color" style="color:#848484;margin-top:16px;margin-right:30px;margin-bottom:25px;margin-left:30px;font-size:16px;font-style:normal;font-weight:400">' . __( 'Sign up to receive the latest fashion trends & exclusive offers straight to your inbox', 'mrm' ) . '.</p>
                <!-- /wp:paragraph -->

				<!-- wp:mrmformfield/email-field-block {"rowSpacing":14,"labelTypography":{"openTypography":false},"inputTypography":{"openTypography":false},"inputFontSize":16,"inputTextColor":"#e9e9e9","inputBgColor":"#060513","inputBorderRadius":8,"inputBorderColor":"#e9e9e9"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:14px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#060513;color:#e9e9e9;font-size:16px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#e9e9e9" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonTextColor":"#ffffff","buttonBgColor":"#701c15","buttonBorderRadius":12,"buttonBorderWidth":0,"buttonText":"Subscribe","buttonWidth":100,"typography":{"openTypography":false},"buttonFontSize":18,"paddingTopBottom":20} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#701c15;color:#ffffff;border-radius:12px;padding:20px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:18px;border-width:0;border-color:;width:100%">' . __( 'Subscribe', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"400"},"color":{"text":"#af9997"}},"fontSize":"small"} -->
                <p class="has-text-align-center has-text-color has-small-font-size" style="color:#af9997;font-style:normal;font-weight:400">' . __( 'We don’t spam! Read more in our privacy policy', 'mrm' ) . '</p>
                <!-- /wp:paragraph --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"default","form_animation":"none","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-06-14","time":"11:24:43"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
			),

			array(
				'id'              => '12',
				'title'           => __( 'Basic Newsletter', 'mrm' ),
				'form_position'   => __( 'Default', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/subscribe.png',
				'content'         => '<!-- wp:columns -->
                <div class="wp-block-columns"><!-- wp:column {"backgroundColor":"white"} -->
                <div class="wp-block-column has-white-background-color has-background"><!-- wp:columns {"style":{"border":{"radius":"20px"},"spacing":{"margin":{"top":"100px","bottom":"100px"}}},"backgroundColor":"white"} -->
                <div class="wp-block-columns has-white-background-color has-background" style="border-radius:20px;margin-top:100px;margin-bottom:100px"><!-- wp:column {"verticalAlignment":"center","width":"30%","backgroundColor":"white"} -->
                <div class="wp-block-column is-vertically-aligned-center has-white-background-color has-background" style="flex-basis:30%"><!-- wp:image {"align":"center","id":186,"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image aligncenter size-full"><img src="' . $image_path . '/content_image/computer-image.png" alt="" class="wp-image-186"/></figure>
                <!-- /wp:image --></div>
                <!-- /wp:column -->

                <!-- wp:column -->
                <div class="wp-block-column"><!-- wp:heading {"textAlign":"left","level":1,"style":{"color":{"text":"#333333"},"typography":{"fontSize":36,"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"0px","bottom":"0px","left":"0px","right":"0px"}}}} -->
                <h1 class="wp-block-heading has-text-align-left has-text-color" style="color:#333333;margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;font-size:36px;font-style:normal;font-weight:600">' . __( 'Subscribe', 'mrm' ) . '</h1>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"left","style":{"typography":{"fontStyle":"normal","fontWeight":"400","fontSize":16},"spacing":{"margin":{"bottom":"24px","right":"30px","left":"10px","top":"10px"}}},"textColor":"black"} -->
                <p class="has-text-align-left has-black-color has-text-color" style="margin-top:10px;margin-right:30px;margin-bottom:24px;margin-left:10px;font-size:16px;font-style:normal;font-weight:400">' . __( 'Stay in the loop! Sign up for our newsletter today for getting regular updates', 'mrm' ) . '.</p>
                <!-- /wp:paragraph -->

                <!-- wp:columns -->
				<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/email-field-block {"inputBorderRadius":10,"inputPaddingTop":13,"align":"center"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:10px;padding-top:13px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block --></div>
				<!-- /wp:column -->

                <!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/mrm-button-block {"buttonBgColor":"#5eacf7","buttonBorderRadius":10,"buttonBorderWidth":0,"buttonText":"Sign UP","buttonFontSize":16,"paddingTopBottom":13,"paddingLeftRight":40} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#5eacf7;color:;border-radius:10px;padding:13px 40px;line-height:1;letter-spacing:0;border-style:none;font-size:16px;border-width:0;border-color:;width:%">' . __( 'Sign UP', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"default","form_animation":"fade-in","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-06-16","time":"07:03:03"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
			),

			array(
				'id'              => '13',
				'title'           => __( 'Free Shipping', 'mrm' ),
				'form_position'   => __( 'Default', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/exclusive-savings.png',
				'content'         => '<!-- wp:columns {"verticalAlignment":"center","style":{"color":{"background":"#6a251a"}}} -->
                <div class="wp-block-columns are-vertically-aligned-center has-background" style="background-color:#6a251a"><!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":34,"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"0px"}}},"textColor":"white"} -->
                <h1 class="wp-block-heading has-text-align-center has-white-color has-text-color" style="margin-top:0px;font-size:34px;font-style:normal;font-weight:600">' . __( 'Exclusive Savings', 'mrm' ) . '!</h1>
                <!-- /wp:heading -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"400","fontSize":16},"color":{"text":"#efcec8"},"spacing":{"margin":{"bottom":"25px","right":"30px","left":"30px"}}}} -->
                <p class="has-text-align-center has-text-color" style="color:#efcec8;margin-right:30px;margin-bottom:25px;margin-left:30px;font-size:16px;font-style:normal;font-weight:400">' . __( 'Sign Up For a Special Coupon Code To Get Free Shipping On Your First Order', 'mrm' ) . '</p>
                <!-- /wp:paragraph -->

				<!-- wp:mrmformfield/email-field-block {"rowSpacing":14,"labelTypography":{"openTypography":false},"inputTypography":{"openTypography":false},"inputTextColor":"#e9e9e9","inputBgColor":"#6a251a","inputBorderRadius":6,"inputBorderColor":"#e9e9e9"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:14px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#6a251a;color:#e9e9e9;font-size:14px;border-radius:6px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#e9e9e9" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

                <!-- wp:mrmformfield/mrm-button-block {"buttonTextColor":"#ffffff","buttonBgColor":"#fe533b","buttonBorderRadius":6,"buttonText":"Sign UP","buttonWidth":100,"typography":{"openTypography":false},"buttonFontSize":18,"paddingTopBottom":20} -->
                <div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#fe533b;color:#ffffff;border-radius:6px;padding:20px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:18px;border-width:0;border-color:;width:100%">' . __( 'Sign UP', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
                <!-- /wp:mrmformfield/mrm-button-block -->

                <!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"400","fontSize":15},"color":{"text":"#dbb5ae"}}} -->
                <p class="has-text-align-center has-text-color" style="color:#dbb5ae;font-size:15px;font-style:normal;font-weight:400">' . __( 'We don’t spam! Read more in our privacy policy', 'mrm' ) . '</p>
                <!-- /wp:paragraph --></div>
                <!-- /wp:column -->

                <!-- wp:column {"verticalAlignment":"center"} -->
                <div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"align":"center","id":183,"sizeSlug":"full","linkDestination":"none"} -->
                <figure class="wp-block-image aligncenter size-full"><img src="' . $image_path . '/content_image/etq-image.png" alt="" class="wp-image-183"/></figure>
                <!-- /wp:image --></div>
                <!-- /wp:column --></div>
                <!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"default","form_animation":"fade-in","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-06-16","time":"06:51:53"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
			),
			// phpcs:ignore
			// ------eBook Lead Magnet (fixed-on-bottom)------!
			array(
				'id'              => '14',
				'title'           => __( 'eBook Lead Magnet', 'mrm' ),
				'form_position'   => __( 'Fixed on bottom', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/eBook-lead-magnet.jpg',
			),
			array(
				'id'              => '15',
				'title'           => __( 'Join The Bunch', 'mrm' ),
				'form_position'   => __( 'Fly-ins', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/join-the-bunch.jpg',
			),
			array(
				'id'              => '16',
				'title'           => __( 'Newsletter Subscription 2', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/newsletter-subscription-2.jpg',
			),
			array(
				'id'              => '17',
				'title'           => __( 'Offering Special Deal', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/offering-special-deal.png',
			),
			array(
				'id'              => '18',
				'title'           => __( 'Fashion Update Newsletter', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/fashion-update-newsletter.png',
				'content'         => '<!-- wp:columns {"verticalAlignment":"center","backgroundColor":"white"} -->
									<div class="wp-block-columns are-vertically-aligned-center has-white-background-color has-background"><!-- wp:column {"verticalAlignment":"center"} -->
									<div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"id":137,"scale":"cover","sizeSlug":"full","linkDestination":"none"} -->
									<figure class="wp-block-image size-full"><img src="' . $image_path . '/updated-image.jpg" alt="" class="wp-image-137" style="object-fit:cover"/></figure>
									<!-- /wp:image --></div>
									<!-- /wp:column -->

									<!-- wp:column {"verticalAlignment":"center"} -->
									<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"textAlign":"center","level":1,"style":{"color":{"text":"#333333"},"typography":{"fontStyle":"normal","fontWeight":"600"},"spacing":{"margin":{"top":"0px"}}},"fontSize":"large"} -->
									<h1 class="wp-block-heading has-text-align-center has-text-color has-large-font-size" style="color:#333333;margin-top:0px;font-style:normal;font-weight:600"><img draggable="false" role="img" class="emoji" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f31f.svg" alt="🌟"> Stay in Style! <img draggable="false" role="img" class="emoji" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f31f.svg" alt="🌟"></h1>
									<!-- /wp:heading -->

									<!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"400","fontSize":16},"color":{"text":"#848484"},"spacing":{"margin":{"bottom":"25px","right":"30px","left":"30px"}}}} -->
									<p class="has-text-align-center has-text-color" style="color:#848484;margin-right:30px;margin-bottom:25px;margin-left:30px;font-size:16px;font-style:normal;font-weight:400">' . __( 'Be the First to Know About Exclusive Fashion Deals And The Latest Trend Updates From Fashion Experts', 'mrm' ) . '.</p>
									<!-- /wp:paragraph -->

									<!-- wp:mrmformfield/email-field-block {"emailPlaceholder":"Email Address","inputPaddingTop":15,"inputPaddingBottom":15} -->
									<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email Address', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:5px;padding-top:15px;padding-right:14px;padding-bottom:15px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
									<!-- /wp:mrmformfield/email-field-block -->

									<!-- wp:mrmformfield/mrm-button-block {"buttonBgColor":"#e64d8e","buttonBorderRadius":6,"buttonText":"Count Me In!","buttonWidth":100,"typography":{"openTypography":false},"buttonFontSize":18,"paddingTopBottom":20} -->
									<div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#e64d8e;color:;border-radius:6px;padding:20px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:18px;border-width:0;border-color:;width:100%">' . __( 'Count Me In', 'mrm' ) . '!</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
									<!-- /wp:mrmformfield/mrm-button-block -->

									<!-- wp:paragraph {"align":"center","style":{"typography":{"fontStyle":"normal","fontWeight":"400","fontSize":15},"color":{"text":"#848484"}}} -->
									<p class="has-text-align-center has-text-color" style="color:#848484;font-size:15px;font-style:normal;font-weight:400">' . __( 'Read our privacy policy for more details', 'mrm' ) . '.</p>
									<!-- /wp:paragraph --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-08-31","time":"06:04:49"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true,"allow_automation_multiple":true},"button_render":{"enable":false,"button_text":"Click Here"},"admin_notification":{"enable":false,"admin_email":"dev-email@flywheel.local"}}}',
			),
			array(
				'id'              => '19',
				'title'           => __( 'Tech Newsletter', 'mrm' ),
				'form_position'   => __( 'Fly-ins', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/tech-newsletter.jpg',
				'content'         => '<!-- wp:columns {"style":{"color":{"background":"#4896fa"}}} -->
										<div class="wp-block-columns has-background" style="background-color:#4896fa"><!-- wp:column -->
										<div class="wp-block-column"><!-- wp:image {"align":"center","id":172,"sizeSlug":"full","linkDestination":"none"} -->
										<figure class="wp-block-image aligncenter size-full"><img src="' . $image_path . '/newsletter-image.png" alt="" class="wp-image-172"/></figure>
										<!-- /wp:image -->

										<!-- wp:heading {"textAlign":"center","level":1,"textColor":"white","fontSize":"large"} -->
										<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color has-large-font-size">' . __( '', 'mrm' ) . 'Technically Trendy Insights!</h1>
										<!-- /wp:heading -->

										<!-- wp:group {"layout":{"type":"constrained"}} -->
										<div class="wp-block-group"><!-- wp:paragraph {"align":"center","textColor":"white","fontSize":"small"} -->
										<p class="has-text-align-center has-white-color has-text-color has-small-font-size">' . __( 'Keep in touch with the latest trends in the tech industry! Get the newest updates on ', 'mrm' ) . '<br>' . __( 'web development trends, cutting-edge technologies, and success stories that inspire', 'mrm' ) . '.</p>
										<!-- /wp:paragraph --></div>
										<!-- /wp:group --></div>
										<!-- /wp:column --></div>
										<!-- /wp:columns -->

										<!-- wp:columns {"style":{"color":{"background":"#297de8"}}} -->
										<div class="wp-block-columns has-background" style="background-color:#297de8"><!-- wp:column -->
										<div class="wp-block-column"><!-- wp:mrmformfield/first-name-block {"firstNamePlaceholder":"Your Name","labelSpacing":0} -->
										<div class="mrm-form-group mrm-input-group alignment-left first-name" style="margin-bottom:12px;width:% ;max-width:px "><label for="mrm-first-name" style="color:#363B4E;margin-bottom:0px"></label><div class="input-wrapper"><input type="text" name="first_name" id="mrm-first-name" placeholder="Your Name" style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:5px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8"/></div></div>
										<!-- /wp:mrmformfield/first-name-block -->

										<!-- wp:mrmformfield/email-field-block {"labelSpacing":0} -->
										<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:12px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:0px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:5px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
										<!-- /wp:mrmformfield/email-field-block -->

										<!-- wp:mrmformfield/mrm-button-block {"buttonBgColor":"#fd5f32","buttonText":"Sign Up Now","buttonWidth":100} -->
										<div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#fd5f32;color:;border-radius:5px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:100%">' . __( 'Sign Up Now', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
										<!-- /wp:mrmformfield/mrm-button-block -->

										<!-- wp:paragraph {"align":"center","textColor":"white"} -->
										<p class="has-text-align-center has-white-color has-text-color">' . __( 'We don’t spam! Read more in our privacy policy', 'mrm' ) . '</p>
										<!-- /wp:paragraph --></div>
										<!-- /wp:column --></div>
										<!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"flyins","form_animation":"slide-in-up","close_button_color":"#323232","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-08-31","time":"06:06:00"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true,"allow_automation_multiple":true},"button_render":{"enable":false,"button_text":"Click Here"},"admin_notification":{"enable":false,"admin_email":"dev-email@flywheel.local"}}}',
			),
			array(
				'id'              => '20',
				'title'           => __( 'Contact Us', 'mrm' ),
				'form_position'   => __( 'Fixed on bottom', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'is-pro',
				'featured_image'  => $image_path . '/contact-us.jpg',
			),
			array(
				'id'              => '21',
				'title'           => 'Newsletter Subscription 3',
				'form_position'   => 'Pop-ups',
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/newsletter-subscription-3.jpg',
				'content'         => '<!-- wp:columns {"backgroundColor":"white"} -->
									<div class="wp-block-columns has-white-background-color has-background"><!-- wp:column {"verticalAlignment":"top","width":""} -->
									<div class="wp-block-column is-vertically-aligned-top"><!-- wp:image {"align":"center","id":153,"sizeSlug":"full","linkDestination":"none","style":{"color":{}}} -->
									<figure class="wp-block-image aligncenter size-full"><img src="' . $image_path . '/newsletter-3-image.jpg" alt="" class="wp-image-153"/></figure>
									<!-- /wp:image -->

									<!-- wp:columns -->
									<div class="wp-block-columns"><!-- wp:column -->
									<div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"color":{"text":"#573bff"},"spacing":{"margin":{"top":"20px","bottom":"10px"}}},"fontSize":"large"} -->
									<h1 class="wp-block-heading has-text-align-center has-text-color has-large-font-size" style="color:#573bff;margin-top:20px;margin-bottom:10px;font-style:normal;font-weight:700"><strong>' . __( 'Stay Informed About Our Latest Offers', 'mrm' ) . '!</strong></h1>
									<!-- /wp:heading -->

									<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#9398a5"},"spacing":{"margin":{"bottom":"30px","top":"0px","right":"0px","left":"0px"}},"typography":{"fontSize":16,"fontStyle":"normal","fontWeight":"400"}}} -->
									<p class="has-text-align-center has-text-color" style="color:#9398a5;margin-top:0px;margin-right:0px;margin-bottom:30px;margin-left:0px;font-size:16px;font-style:normal;font-weight:400">' . __( 'Be the first to get the news of our hand-picked deals and exclusive campaigns', 'mrm' ) . '.<br> ' . __( 'Subscribe to our newsletter for instant updates', 'mrm' ) . '.</p>
									<!-- /wp:paragraph -->

									<!-- wp:mrmformfield/email-field-block {"emailLabel":"Email","emailPlaceholder":"Email Address","rowSpacing":20,"inputTextColor":"#a4a4a4","inputBorderRadius":8,"inputPaddingTop":15,"inputPaddingRight":20,"inputPaddingBottom":15,"inputPaddingLeft":20,"inputBorderColor":"#e9e9e9"} -->
									<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:20px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px">' . __( '', 'mrm' ) . 'Email<span class="required-mark">*</span></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email Address', 'mrm' ) . '" required style="background-color:#ffffff;color:#a4a4a4;font-size:14px;border-radius:8px;padding-top:15px;padding-right:20px;padding-bottom:15px;padding-left:20px;border-style:solid;border-width:1px;border-color:#e9e9e9" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
									<!-- /wp:mrmformfield/email-field-block -->

									<!-- wp:mrmformfield/mrm-button-block {"buttonBorderRadius":8,"buttonText":"Subscribe Now","buttonWidth":100} -->
									<div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#573bff;color:;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:100%">' . __( 'Subscribe Now', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
									<!-- /wp:mrmformfield/mrm-button-block --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->

									<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":16},"color":{"text":"#9398a5"}}} -->
									<p class="has-text-align-center has-text-color" style="color:#9398a5;font-size:16px">' . __( 'We don’t spam! Read more in our', 'mrm' ) . ' <a href="#">' . __( 'privacy policy', 'mrm' ) . '</a></p>
									<!-- /wp:paragraph --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-08-31","time":"06:50:05"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true,"allow_automation_multiple":true},"button_render":{"enable":false,"button_text":"Click Here"},"admin_notification":{"enable":false,"admin_email":"dev-email@flywheel.local"}}}',
			),
			array(
				'id'              => '22',
				'title'           => __( 'Halloween Deal', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/halloween-deal.jpg',
				'content'         => '<!-- wp:columns {"style":{"color":{"background":"#08151b"}}} -->
									<div class="wp-block-columns has-background" style="background-color:#08151b"><!-- wp:column {"verticalAlignment":"top","width":""} -->
									<div class="wp-block-column is-vertically-aligned-top"><!-- wp:image {"align":"center","id":135,"sizeSlug":"full","linkDestination":"none","style":{"color":{}}} -->
									<figure class="wp-block-image aligncenter size-full"><img src="' . $image_path . '/halloween-image.jpg" alt="" class="wp-image-135"/></figure>
									<!-- /wp:image -->

									<!-- wp:columns -->
									<div class="wp-block-columns"><!-- wp:column -->
									<div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"20px","bottom":"10px"}}},"textColor":"white","fontSize":"large"} -->
									<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color has-large-font-size" style="margin-top:20px;margin-bottom:10px;font-style:normal;font-weight:700"><img draggable="false" role="img" class="emoji" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f383.svg" alt="🎃"> Join Us To Get Spooky Halloween Treats! <img draggable="false" role="img" class="emoji" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f383.svg" alt="🎃"></h1>
									<!-- /wp:heading -->

									<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#9398a5"},"spacing":{"margin":{"bottom":"30px","top":"0px","right":"0px","left":"0px"}},"typography":{"fontSize":16,"fontStyle":"normal","fontWeight":"400"}}} -->
									<p class="has-text-align-center has-text-color" style="color:#9398a5;margin-top:0px;margin-right:0px;margin-bottom:30px;margin-left:0px;font-size:16px;font-style:normal;font-weight:400">' . __( 'Get ready for a hauntingly good time! Be the first to know about eerie discounts', 'mrm' ) . ', <br>' . __( 'spine-chilling events, and ghostly giveaways', 'mrm' ) . '. <img draggable="false" role="img" class="emoji" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f577.svg" alt="🕷️"><img draggable="false" role="img" class="emoji" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f36c.svg" alt="🍬"><br>Sign up now and get a 31% discount on all Halloween items!</p>
									<!-- /wp:paragraph -->

									<!-- wp:mrmformfield/email-field-block {"emailPlaceholder":"Email Address","rowSpacing":20,"inputTextColor":"#a4a4a4","inputBorderRadius":8,"inputPaddingTop":15,"inputPaddingRight":20,"inputPaddingBottom":15,"inputPaddingLeft":20,"inputBorderColor":"#e9e9e9"} -->
									<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:20px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email Address', 'mrm' ) . '" required style="background-color:#ffffff;color:#a4a4a4;font-size:14px;border-radius:8px;padding-top:15px;padding-right:20px;padding-bottom:15px;padding-left:20px;border-style:solid;border-width:1px;border-color:#e9e9e9" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
									<!-- /wp:mrmformfield/email-field-block -->

									<!-- wp:mrmformfield/mrm-button-block {"buttonBgColor":"#eb5506","buttonBorderRadius":8,"buttonText":"Give Me My Treat","buttonWidth":100} -->
									<div class="mrm-form-group submit" style="margin-bottom:12px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#eb5506;color:;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:100%">' . __( 'Give Me My Treat', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px;"></div><div class="response"></div></div>
									<!-- /wp:mrmformfield/mrm-button-block --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->

									<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":16}},"textColor":"white"} -->
									<p class="has-text-align-center has-white-color has-text-color" style="font-size:16px">' . __( 'We don’t spam! Read more in our privacy policy', 'mrm' ) . '</p>
									<!-- /wp:paragraph --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"flyins","form_animation":"slide-in-up","close_button_color":"#000","close_background_color":"#fff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-08-31","time":"06:38:29"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true,"allow_automation_multiple":true},"button_render":{"enable":false,"button_text":"Click Here"},"admin_notification":{"enable":false,"admin_email":"dev-email@flywheel.local"}}}',
			),
			array(
				'id'              => '23',
				'title'           => __( 'Newsletter Subscription 4', 'mrm' ),
				'form_position'   => __( 'Fixed on bottom', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/newsletter-subscription-4.jpg',
				'content'         => '<!-- wp:columns {"verticalAlignment":"center","style":{"color":{"background":"#6c6db5"}}} -->
				<div class="wp-block-columns are-vertically-aligned-center has-background" style="background-color:#6c6db5"><!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"style":{"typography":{"fontSize":24,"fontStyle":"normal","fontWeight":"700"}},"textColor":"white"} -->
				<h2 class="wp-block-heading has-white-color has-text-color" style="font-size:24px;font-style:normal;font-weight:700"><strong>' . __( 'Embark on Your Path to Mindfulness', 'mrm' ) . '</strong></h2>
				<!-- /wp:heading -->
				
				<!-- wp:paragraph {"textColor":"white","fontSize":"small"} -->
				<p class="has-white-color has-text-color has-small-font-size">' . __( 'Subscribe to Wellness Wisdom Newsletter for fitness tips, nutritional advice, and mental wellness strategies from our experienced coaches', 'mrm' ) . '.</p>
				<!-- /wp:paragraph --></div>
				<!-- /wp:column -->
				
				<!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:columns {"verticalAlignment":null} -->
				<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/email-field-block {"rowSpacing":0,"labelSpacing":0,"inputTextColor":"#2d2c2b","inputPaddingTop":15,"inputPaddingBottom":15,"inputBorderColor":"#7071b8"} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:0px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:0px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="' . __( 'Email', 'mrm' ) . '" required style="background-color:#ffffff;color:#2d2c2b;font-size:14px;border-radius:5px;padding-top:15px;padding-right:14px;padding-bottom:15px;padding-left:14px;border-style:solid;border-width:1px;border-color:#7071b8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block --></div>
				<!-- /wp:column -->
				
				<!-- wp:column -->
				<div class="wp-block-column"><!-- wp:mrmformfield/mrm-button-block {"rowSpacing":0,"buttonTextColor":"#6c6db5","buttonBgColor":"#ffffff","buttonText":"Subscribe Now"} -->
				<div class="mrm-form-group submit" style="margin-bottom:0px;text-align:left"><button class="mrm-submit-button mintmrm-btn" aria-label="Submit" type="submit" style="background-color:#ffffff;color:#6c6db5;border-radius:5px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:%">' . __( 'Subscribe Now', 'mrm' ) . '</button><div id="mint-google-recaptcha" style="padding-top:10px"></div><div class="response"></div></div>
				<!-- /wp:mrmformfield/mrm-button-block --></div>
				<!-- /wp:column --></div>
				<!-- /wp:columns --></div>
				<!-- /wp:column --></div>
				<!-- /wp:columns -->',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"fixed-on-bottom","form_animation":"fade-in","close_button_color":"#ffffff","close_background_color":"#6c6db5"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-08-31","time":"06:42:11"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true,"allow_automation_multiple":true},"button_render":{"enable":false,"button_text":"Click Here"},"admin_notification":{"enable":false,"admin_email":"dev-email@flywheel.local"}}}',
			),
			array(
				'id'              => '24',
				'title'           => __( 'Affiliate Registration', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/affiliate-registration.png',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-02-13","time":"11:08:35"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
				'content'		  => '<!-- wp:columns {"style":{"border":{"radius":"16px"},"spacing":{"padding":{"top":"20px","right":"20px","bottom":"20px","left":"20px"}}},"backgroundColor":"white"} -->
									<div class="wp-block-columns has-white-background-color has-background" style="border-radius:16px;padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><!-- wp:column {"verticalAlignment":"top","width":""} -->
									<div class="wp-block-column is-vertically-aligned-top"><!-- wp:columns -->
									<div class="wp-block-columns"><!-- wp:column -->
									<div class="wp-block-column"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700"},"spacing":{"margin":{"top":"0px","right":"0px","bottom":"0px","left":"0px"},"padding":{"top":"0px","right":"0px","bottom":"10px","left":"0px"}}},"textColor":"black","fontSize":"larger"} -->
									<h1 class="wp-block-heading has-text-align-center has-black-color has-text-color has-larger-font-size" style="margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:10px;padding-left:0px;font-style:normal;font-weight:700">Join Our Affiliate Program</h1>
									<!-- /wp:heading -->

									<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"bottom":"30px","top":"0px","right":"0px","left":"0px"},"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"}},"typography":{"fontStyle":"normal","fontWeight":"400","fontSize":"16px"}},"textColor":"black"} -->
									<p class="has-text-align-center has-black-color has-text-color" style="margin-top:0px;margin-right:0px;margin-bottom:30px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;font-size:16px;font-style:normal;font-weight:400">Promote Our Products and Get Rewarded for Every Sale!</p>
									<!-- /wp:paragraph -->

									<!-- wp:mrmformfield/first-name-block {"firstNameLabel":"First Name","isRequiredName":true,"rowSpacing":8,"inputTextColor":"#000000","inputBorderRadius":8,"inputPaddingTop":15,"inputPaddingRight":20,"inputPaddingBottom":12,"inputPaddingLeft":20} -->
									<div class="mrm-form-group mrm-input-group alignment-left first-name" style="margin-bottom:8px;width:% ;max-width:px "><label for="mrm-first-name" style="color:#363B4E;margin-bottom:7px">First Name<span class="required-mark">*</span></label><div class="input-wrapper"><input type="text" name="first_name" id="mrm-first-name" placeholder="First Name" required style="background-color:#ffffff;color:#000000;font-size:14px;border-radius:8px;padding-top:15px;padding-right:20px;padding-bottom:12px;padding-left:20px;border-style:solid;border-width:1px;border-color:#DFE1E8"/></div></div>
									<!-- /wp:mrmformfield/first-name-block -->

									<!-- wp:mrmformfield/last-name-block {"lastNameLabel":"Last Name","isRequiredLastName":true,"rowSpacing":8,"inputTextColor":"#000000","inputBorderRadius":8,"inputPaddingTop":15,"inputPaddingRight":20,"inputPaddingBottom":15,"inputPaddingLeft":20} -->
									<div class="mrm-form-group mrm-input-group alignment-left last-name" style="margin-bottom:8px;width:% ;max-width:px "><label for="wpfnl-last-name" style="color:#363B4E;margin-bottom:7px">Last Name<span class="required-mark">*</span></label><div class="input-wrapper"><input type="text" name="last_name" id="wpfnl-last-name" placeholder="Last Name" required style="background-color:#ffffff;color:#000000;font-size:14px;border-radius:8px;padding-top:15px;padding-right:20px;padding-bottom:15px;padding-left:20px;border-style:solid;border-width:1px;border-color:#DFE1E8"/></div></div>
									<!-- /wp:mrmformfield/last-name-block -->

									<!-- wp:mrmformfield/mrm-custom-field {"field_name":"Website URL","field_label":"Website URL","customFields":[{"id":"14","title":"My Custom Field 1","slug":"my-custom-field-1","group_id":"1","type":"number","meta":{"placeholder":"My Custom Field","label":"My Custom Field 1"},"created_at":"2024-07-29 09:03:40","updated_at":null},{"id":"15","title":"My Custom Field 2","slug":"my-custom-field-2","group_id":"1","type":"text","meta":{"placeholder":"My Custom Field","label":"My Custom Field 2"},"created_at":"2024-07-29 09:03:57","updated_at":null}],"custom_text_placeholder":"Website URL","field_require":true,"field_slug":"website-url","rowSpacing":8,"inputBorderRadius":8} -->
									<div class="mrm-form-group mrm-input-group alignment-left text" style="margin-bottom:8px;width:% ;max-width:px "><label for="Website URL" style="color:#363B4E;margin-bottom:7px">Website URL<span class="required-mark">*</span></label><div class="input-wrapper"><input type="text" name="website-url" id="website-url" placeholder="Website URL" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8"/></div></div>
									<!-- /wp:mrmformfield/mrm-custom-field -->

									<!-- wp:mrmformfield/email-field-block {"emailLabel":"Email","rowSpacing":8,"inputTextColor":"#000000","inputBorderRadius":8,"inputPaddingTop":15,"inputPaddingRight":20,"inputPaddingBottom":15,"inputPaddingLeft":20,"inputBorderColor":"#e9e9e9"} -->
									<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:8px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px">Email<span class="required-mark">*</span></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="Email" required style="background-color:#ffffff;color:#000000;font-size:14px;border-radius:8px;padding-top:15px;padding-right:20px;padding-bottom:15px;padding-left:20px;border-style:solid;border-width:1px;border-color:#e9e9e9" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
									<!-- /wp:mrmformfield/email-field-block -->

									<!-- wp:mrmformfield/mrm-custom-field {"field_type":"textarea","field_name":"Social Media Links","field_label":"Social Media Links","customFields":[{"id":"14","title":"My Custom Field 1","slug":"my-custom-field-1","group_id":"1","type":"number","meta":{"placeholder":"My Custom Field","label":"My Custom Field 1"},"created_at":"2024-07-29 09:03:40","updated_at":null},{"id":"15","title":"My Custom Field 2","slug":"my-custom-field-2","group_id":"1","type":"text","meta":{"placeholder":"My Custom Field","label":"My Custom Field 2"},"created_at":"2024-07-29 09:03:57","updated_at":null}],"custom_text_placeholder":"Your Social Media Links (Optional)","custom_textarea_placeholder":"Social Media Links (Optional)","field_slug":"social-media-links","rowSpacing":8,"inputBorderRadius":8} -->
									<div class="mrm-form-group mrm-input-group alignment-left textarea" style="margin-bottom:8px;width:% ;max-width:px "><label for="social-media-links" style="color:#363B4E;margin-bottom:7px">Social Media Links</label><div class="input-wrapper"><textarea id="social-media-links" name="social-media-links" placeholder="Social Media Links (Optional)" rows="4" cols="50" style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8"></textarea></div></div>
									<!-- /wp:mrmformfield/mrm-custom-field -->

									<!-- wp:mrmformfield/mrm-custom-field {"field_type":"textarea","field_name":"Payment Method","field_label":"Payment Method","customFields":[{"id":"14","title":"My Custom Field 1","slug":"my-custom-field-1","group_id":"1","type":"number","meta":{"placeholder":"My Custom Field","label":"My Custom Field 1"},"created_at":"2024-07-29 09:03:40","updated_at":null},{"id":"15","title":"My Custom Field 2","slug":"my-custom-field-2","group_id":"1","type":"text","meta":{"placeholder":"My Custom Field","label":"My Custom Field 2"},"created_at":"2024-07-29 09:03:57","updated_at":null}],"custom_text_placeholder":"Your Social Media Links (Optional)","custom_textarea_placeholder":"How Would You Like to Get Paid?","field_require":true,"field_slug":"payment-method","rowSpacing":8,"inputBorderRadius":8} -->
									<div class="mrm-form-group mrm-input-group alignment-left textarea" style="margin-bottom:8px;width:% ;max-width:px "><label for="payment-method" style="color:#363B4E;margin-bottom:7px">Payment Method<span class="required-mark">*</span></label><div class="input-wrapper"><textarea id="payment-method" name="payment-method" placeholder="How Would You Like to Get Paid?" required rows="4" cols="50" style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:8px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8"></textarea></div></div>
									<!-- /wp:mrmformfield/mrm-custom-field -->

									<!-- wp:mrmformfield/mrm-button-block {"rowSpacing":5,"buttonTextColor":"#FFFFFF","buttonBgColor":"#007dff","buttonBorderRadius":8,"buttonText":"Join the Program","buttonWidth":100} -->
									<div class="mrm-form-group submit" style="margin-bottom:5px;text-align:left"><button class="mrm-submit-button mintmrm-btn" type="submit" aria-label="Submit" style="background-color:#007dff;color:#FFFFFF;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:100%">Join the Program</button><div id="mint-google-recaptcha" style="padding-top:10px"></div><div class="response"></div></div>
									<!-- /wp:mrmformfield/mrm-button-block --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->

									<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":16},"spacing":{"margin":{"top":"0px","right":"0px","bottom":"0px","left":"0px"},"padding":{"top":"0px","right":"0px","bottom":"10px","left":"0px"}}},"textColor":"dark-gray"} -->
									<p class="has-text-align-center has-dark-gray-color has-text-color" style="margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:10px;padding-left:0px;font-size:14px">We don’t spam! Read more in our<a style="color:#323232cc;" href="#"> </a>privacy policy</p>
									<!-- /wp:paragraph --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->',
			),
			array(
				'id'              => '25',
				'title'           => __( 'Newsletter Subscription 5', 'mrm' ),
				'form_position'   => __( 'Pop-ups', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":true},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/newsletter-subscription-5.jpg',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"popup","form_animation":"fade-in","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-02-13","time":"11:08:35"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
			),
			array(
				'id'              => '26',
				'title'           => __( 'Contact Form', 'mrm' ),
				'form_position'   => __( 'Fly-ins', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/contact-form.png',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"flyins","form_animation":"fade-in","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-02-13","time":"11:08:35"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
				'content'		  => '<!-- wp:columns {"style":{"color":{"background":"#123238"}}} -->
									<div class="wp-block-columns has-background" style="background-color:#123238"><!-- wp:column {"verticalAlignment":"top"} -->
									<div class="wp-block-column is-vertically-aligned-top"><!-- wp:cover {"url":"'.$image_path . '/form-img-mm-2.png","id":1451,"dimRatio":0,"customOverlayColor":"#a4a39f","isUserOverlayColor":true,"isDark":false,"style":{"color":{}}} -->
									<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim" style="background-color:#a4a39f"></span><img class="wp-block-cover__image-background wp-image-1451" alt="" src="'.$image_path . '/form-img-mm-2.png" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"large"} -->
									<p class="has-text-align-center has-large-font-size"></p>
									<!-- /wp:paragraph --></div></div>
									<!-- /wp:cover -->

									<!-- wp:columns -->
									<div class="wp-block-columns"><!-- wp:column {"style":{"spacing":{"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"}}}} -->
									<div class="wp-block-column" style="padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px"><!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","fontSize":56},"color":{"text":"#4feefa"},"spacing":{"margin":{"top":"0px","bottom":"0px"}}}} -->
									<h1 class="wp-block-heading has-text-align-center has-text-color" style="color:#4feefa;margin-top:0px;margin-bottom:0px;font-size:56px;font-style:normal;font-weight:700">We\'re Here to Help</h1>
									<!-- /wp:heading -->

									<!-- wp:paragraph {"align":"center","style":{"spacing":{"margin":{"bottom":"25px","top":"10px"}}},"textColor":"white","fontSize":"medium"} -->
									<p class="has-text-align-center has-white-color has-text-color has-medium-font-size" style="margin-top:10px;margin-bottom:25px">Reach out to us with your inquiries, feedback, or support requests. Our team is ready to assist you.</p>
									<!-- /wp:paragraph -->

									<!-- wp:mrmformfield/mrm-custom-field {"field_name":"Full Name","customFields":[{"id":"14","title":"My Custom Field 1","slug":"my-custom-field-1","group_id":"1","type":"number","meta":{"placeholder":"My Custom Field","label":"My Custom Field 1"},"created_at":"2024-07-29 03:03:40","updated_at":null},{"id":"15","title":"My Custom Field 2","slug":"my-custom-field-2","group_id":"1","type":"text","meta":{"placeholder":"My Custom Field","label":"My Custom Field 2"},"created_at":"2024-07-29 03:03:57","updated_at":null}],"custom_text_placeholder":"Your Full Name","field_slug":"full-name","rowSpacing":8,"labelColor":"#FFFFFF","inputTextColor":"#FFFFFF","inputBgColor":"#163f47","inputBorderStyle":"none"} -->
									<div class="mrm-form-group mrm-input-group alignment-left text" style="margin-bottom:8px;width:% ;max-width:px "><label for="Full Name" style="color:#FFFFFF;margin-bottom:7px"></label><div class="input-wrapper"><input type="text" name="full-name" id="full-name" placeholder="Your Full Name" style="background-color:#163f47;color:#FFFFFF;font-size:14px;border-radius:5px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:none;border-width:1px;border-color:#DFE1E8"/></div></div>
									<!-- /wp:mrmformfield/mrm-custom-field -->

									<!-- wp:mrmformfield/email-field-block {"emailPlaceholder":"Your Email Address","rowSpacing":8,"inputTextColor":"#FFFFFF","inputBgColor":"#163f47","inputBorderRadius":8,"inputPaddingTop":12,"inputPaddingBottom":12,"inputBorderStyle":"none"} -->
									<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:8px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="Your Email Address" required style="background-color:#163f47;color:#FFFFFF;font-size:14px;border-radius:8px;padding-top:12px;padding-right:14px;padding-bottom:12px;padding-left:14px;border-style:none;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
									<!-- /wp:mrmformfield/email-field-block -->

									<!-- wp:mrmformfield/mrm-custom-field {"field_type":"textarea","field_name":"Message","customFields":[{"id":"14","title":"My Custom Field 1","slug":"my-custom-field-1","group_id":"1","type":"number","meta":{"placeholder":"My Custom Field","label":"My Custom Field 1"},"created_at":"2024-07-29 03:03:40","updated_at":null},{"id":"15","title":"My Custom Field 2","slug":"my-custom-field-2","group_id":"1","type":"text","meta":{"placeholder":"My Custom Field","label":"My Custom Field 2"},"created_at":"2024-07-29 03:03:57","updated_at":null}],"custom_textarea_placeholder":"Your Message To Us","field_slug":"message","rowSpacing":8,"inputTextColor":"#FFFFFF","inputBgColor":"#163f47","inputBorderStyle":"none"} -->
									<div class="mrm-form-group mrm-input-group alignment-left textarea" style="margin-bottom:8px;width:% ;max-width:px "><label for="message" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><textarea id="message" name="message" placeholder="Your Message To Us" rows="4" cols="50" style="background-color:#163f47;color:#FFFFFF;font-size:14px;border-radius:5px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:none;border-width:1px;border-color:#DFE1E8"></textarea></div></div>
									<!-- /wp:mrmformfield/mrm-custom-field -->

									<!-- wp:mrmformfield/mrm-button-block {"rowSpacing":8,"buttonTextColor":"#123238","buttonBgColor":"#e5ff73","buttonBorderRadius":8,"buttonWidth":100,"typography":{"openTypography":true,"weight":600},"buttonFontSize":18} -->
									<div class="mrm-form-group submit" style="margin-bottom:8px;text-align:left"><button class="mrm-submit-button mintmrm-btn" type="submit" aria-label="Submit" style="background-color:#e5ff73;color:#123238;border-radius:8px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-weight:600;font-size:18px;border-width:0;border-color:;width:100%">Submit</button><div id="mint-google-recaptcha" style="padding-top:10px"></div><div class="response"></div></div>
									<!-- /wp:mrmformfield/mrm-button-block --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->',
			),
			array(
				'id'              => '27',
				'title'           => __( 'Early Bird Form', 'mrm' ),
				'form_position'   => __( 'Fixed on top', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/early-bird-form.png',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"fixed-on-top","form_animation":"fade-in","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-02-13","time":"11:08:35"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
				'content'		  => '<!-- wp:columns {"verticalAlignment":"center","backgroundColor":"black"} -->
									<div class="wp-block-columns are-vertically-aligned-center has-black-background-color has-background"><!-- wp:column {"verticalAlignment":"center","width":"15%"} -->
									<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:15%"><!-- wp:image {"id":1453,"width":"83px","height":"auto","sizeSlug":"full","linkDestination":"none","align":"center"} -->
									<figure class="wp-block-image aligncenter size-full is-resized"><img src="'.$image_path . '/guitar_img.png" alt="" class="wp-image-1453" style="width:83px;height:auto"/></figure>
									<!-- /wp:image --></div>
									<!-- /wp:column -->

									<!-- wp:column {"verticalAlignment":"center"} -->
									<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"style":{"typography":{"fontSize":24,"fontStyle":"normal","fontWeight":"700"},"spacing":{"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"},"margin":{"top":"0px","right":"0px","bottom":"0px","left":"0px"}}},"textColor":"white"} -->
									<h2 class="wp-block-heading has-white-color has-text-color" style="margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;font-size:24px;font-style:normal;font-weight:700"><strong>Stay Tuned</strong></h2>
									<!-- /wp:heading -->

									<!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"},"margin":{"top":"10px","right":"0px","bottom":"0px","left":"0px"}}},"textColor":"white","fontSize":"small"} -->
									<p class="has-white-color has-text-color has-small-font-size" style="margin-top:10px;margin-right:0px;margin-bottom:0px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px">Be the first to hear about our upcoming album release!</p>
									<!-- /wp:paragraph --></div>
									<!-- /wp:column -->

									<!-- wp:column {"verticalAlignment":"center"} -->
									<div class="wp-block-column is-vertically-aligned-center"><!-- wp:columns {"verticalAlignment":"center"} -->
									<div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} -->
									<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/email-field-block {"rowSpacing":0,"labelSpacing":0,"inputTextColor":"#2d2c2b","inputPaddingTop":15,"inputPaddingBottom":15,"inputBorderColor":"#7071b8","align":"center"} -->
									<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:0px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:0px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="Email" required style="background-color:#ffffff;color:#2d2c2b;font-size:14px;border-radius:5px;padding-top:15px;padding-right:14px;padding-bottom:15px;padding-left:14px;border-style:solid;border-width:1px;border-color:#7071b8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
									<!-- /wp:mrmformfield/email-field-block --></div>
									<!-- /wp:column -->

									<!-- wp:column {"verticalAlignment":"center"} -->
									<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/mrm-button-block {"rowSpacing":0,"buttonTextColor":"#FFFFFF","buttonBgColor":"#4c527c","buttonText":"Join The List","buttonAlign":"center","paddingTopBottom":18} -->
									<div class="mrm-form-group submit" style="margin-bottom:0px;text-align:center"><button class="mrm-submit-button mintmrm-btn" type="submit" aria-label="Submit" style="background-color:#4c527c;color:#FFFFFF;border-radius:5px;padding:18px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:%">Join The List</button><div id="mint-google-recaptcha" style="padding-top:10px"></div><div class="response"></div></div>
									<!-- /wp:mrmformfield/mrm-button-block --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns --></div>
									<!-- /wp:column --></div>
									<!-- /wp:columns -->',
			),
			array(
				'id'              => '28',
				'title'           => __( 'Offering Special Deal 2', 'mrm' ),
				'form_position'   => __( 'Fixed on top', 'mrm' ),
				'form_appearance' => '{"pages":{"all":false,"selected":[],"homepage":false},"post":{"all":false,"selected":[]},"product":{"all":false,"selected":[]},"categories":[],"tags":[],"category_archives":{"all":false,"selected":[]}}',
				'type'            => 'free',
				'featured_image'  => $image_path . '/offering-special-deal-2.png',
				'settings'        => '{"settings":{"confirmation_type":{"selected_confirmation_type":"same-page","same_page":{"message_to_show":"Form submitted successfully.","after_form_submission":"none"}},"form_layout":{"form_position":"fixed-on-top","form_animation":"fade-in","close_button_color":"#a7a8b3","close_background_color":"#ffffff"},"schedule":{"form_scheduling":false,"submission_start":{"date":"2023-02-13","time":"11:08:35"}},"restriction":{"max_entries":false,"max_number":0,"max_type":""},"extras":{"cookies_timer":7,"show_always":true}}}',
				'content'		  => '<!-- wp:columns {"verticalAlignment":"center","style":{"layout":{"selfStretch":"fixed","flexSize":"99.9%"},"spacing":{"padding":{"top":"30px","right":"40px","bottom":"10px","left":"30px"},"margin":{"top":"0px","bottom":"0px"}}},"backgroundColor":"black"} -->
				<div class="wp-block-columns are-vertically-aligned-center has-black-background-color has-background" style="margin-top:0px;margin-bottom:0px;padding-top:30px;padding-right:40px;padding-bottom:10px;padding-left:30px"><!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"style":{"typography":{"fontSize":24,"fontStyle":"normal","fontWeight":"700"},"spacing":{"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"},"margin":{"top":"0px","right":"0px","bottom":"0px","left":"0px"}}},"textColor":"white"} -->
				<h2 class="wp-block-heading has-white-color has-text-color" style="margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;font-size:24px;font-style:normal;font-weight:700"><strong>Catch the Deal of the Week!</strong></h2>
				<!-- /wp:heading -->

				<!-- wp:spacer {"height":"10px","style":{"spacing":{"margin":{"top":"0px","bottom":"0px"}}}} -->
				<div style="margin-top:0px;margin-bottom:0px;height:10px" aria-hidden="true" class="wp-block-spacer"></div>
				<!-- /wp:spacer --></div>
				<!-- /wp:column -->

				<!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/first-name-block {"firstNamePlaceholder":"Your Name","rowSpacing":0} -->
				<div class="mrm-form-group mrm-input-group alignment-left first-name" style="margin-bottom:0px;width:% ;max-width:px "><label for="mrm-first-name" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="text" name="first_name" id="mrm-first-name" placeholder="Your Name" style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:5px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8"/></div></div>
				<!-- /wp:mrmformfield/first-name-block -->

				<!-- wp:spacer {"height":"10px","style":{"spacing":{"margin":{"top":"0px","bottom":"0px"}}}} -->
				<div style="margin-top:0px;margin-bottom:0px;height:10px" aria-hidden="true" class="wp-block-spacer"></div>
				<!-- /wp:spacer --></div>
				<!-- /wp:column -->

				<!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/email-field-block {"emailPlaceholder":"Your Email","rowSpacing":0} -->
				<div class="mrm-form-group mrm-input-group alignment-left email" style="margin-bottom:0px ;width:100% ;max-width:px "><label for="mrm-email" style="color:#363B4E;margin-bottom:7px"></label><div class="input-wrapper"><input type="email" name="email" id="mrm-email" placeholder="Your Email" required style="background-color:#ffffff;color:#7A8B9A;font-size:14px;border-radius:5px;padding-top:11px;padding-right:14px;padding-bottom:11px;padding-left:14px;border-style:solid;border-width:1px;border-color:#DFE1E8" pattern="[^@\s]+@[^@\s]+\.[^@\s]+"/></div></div>
				<!-- /wp:mrmformfield/email-field-block -->

				<!-- wp:spacer {"height":"10px","style":{"spacing":{"margin":{"top":"0px","bottom":"0px"}}}} -->
				<div style="margin-top:0px;margin-bottom:0px;height:10px" aria-hidden="true" class="wp-block-spacer"></div>
				<!-- /wp:spacer --></div>
				<!-- /wp:column -->

				<!-- wp:column {"verticalAlignment":"center"} -->
				<div class="wp-block-column is-vertically-aligned-center"><!-- wp:mrmformfield/mrm-button-block {"rowSpacing":0,"buttonTextColor":"#000000","buttonBgColor":"#e780a0","buttonText":"Count Me In"} -->
				<div class="mrm-form-group submit" style="margin-bottom:0px;text-align:left"><button class="mrm-submit-button mintmrm-btn" type="submit" aria-label="Submit" style="background-color:#e780a0;color:#000000;border-radius:5px;padding:15px 20px;line-height:1;letter-spacing:0;border-style:none;font-size:15px;border-width:0;border-color:;width:%">Count Me In</button><div id="mint-google-recaptcha" style="padding-top:10px"></div><div class="response"></div></div>
				<!-- /wp:mrmformfield/mrm-button-block --></div>
				<!-- /wp:column --></div>
				<!-- /wp:columns -->

				<!-- wp:columns {"style":{"spacing":{"margin":{"top":"0px","bottom":"0px"},"padding":{"top":"10px","right":"40px","bottom":"20px","left":"40px"}}},"backgroundColor":"black"} -->
				<div class="wp-block-columns has-black-background-color has-background" style="margin-top:0px;margin-bottom:0px;padding-top:10px;padding-right:40px;padding-bottom:20px;padding-left:40px"><!-- wp:column {"width":"66.66%","style":{"spacing":{"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"}}}} -->
				<div class="wp-block-column" style="padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;flex-basis:66.66%"><!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0px","right":"0px","bottom":"0px","left":"0px"},"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"}},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"textColor":"white"} -->
				<p class="has-white-color has-text-color has-link-color" style="margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px"><span style="font-size: 16px">Subscribe today to receive a 20% off on your next orders instantly. Hurry, this offer won\'t last long!</span></p>
				<!-- /wp:paragraph --></div>
				<!-- /wp:column -->

				<!-- wp:column {"width":"33.33%"} -->
				<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:paragraph {"style":{"spacing":{"padding":{"top":"0px","right":"0px","bottom":"0px","left":"0px"},"margin":{"top":"0px","right":"0px","bottom":"0px","left":"0px"}},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"textColor":"white"} -->
				<p class="has-white-color has-text-color has-link-color" style="margin-top:0px;margin-right:0px;margin-bottom:0px;margin-left:0px;padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px"><span style="font-size: 16px">We don’t spam! Read more in our&nbsp;<a href="#" style="color: blue">privacy policy</a></span></p>
				<!-- /wp:paragraph --></div>
				<!-- /wp:column --></div>
				<!-- /wp:columns -->',
			),

		);
		return apply_filters( 'mailmint_form_template', $forms );
	}

	/**
	 * Get Form By id.
	 *
	 * @param string $id Pass Form ID for get Details.
	 * @return array|string[]
	 */
	public static function get_form( $id ) {
		$forms           = self::get_form_templates();
		$get_single_form = array();
		foreach ( $forms as $key => $value ) {
			if ( $value['id'] === $id ) {
				$get_single_form = $value;
			}
		}
		return $get_single_form;
	}

}
