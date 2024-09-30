<?php
/**
 * Recipe Controller For Automation
 *
 * Handles requests to the Automation Recipe.
 *
 * @author   MRM Team
 * @category API
 * @package  MRM
 * @since    1.0.0
 */

namespace MintMail\App\Internal\Automation\Recipe;

use MintMail\App\Internal\Automation\HelperFunctions;
use MRM\Common\MrmCommon;

/**
 * This is the main class that controls the automation Recipe. Its responsibilities are:
 *
 * - Add all recipe for
 *
 * @package MintMail\App\Internal\Automation\Recipe
 */
class AutomationRecipe {

	/**
	 * Get all recipe.
	 *
	 * @return mixed|null
	 */
	public static function get_all_recipe() {
		$recipe = array(
			array(
				'id'                    => 1,
				'isPro'                 => false,
				'type'                  => 'mailmint',
				'automationTitle'       => 'Opt-in Welcome Email',
				'automationDescription' => 'When a person submits a form, assign a tag and follow up with a welcome email.',
				'icon'                  => '<svg width="26" height="26" fill="none" viewBox="0 0 26 26" xmlns="http://www.w3.org/2000/svg"><mask id="a" style="mask-type:luminance" width="26" height="26" x="0" y="0" maskUnits="userSpaceOnUse"><path fill="#fff" d="M0 0h26v26H0V0z"/></mask><g stroke="#573BFF" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="2" mask="url(#a)"><path d="M9.14 24.984H6.806a3.047 3.047 0 01-3.047-3.047V4.063a3.047 3.047 0 013.047-3.046H19.29a3.047 3.047 0 013.047 3.046v6.043M7.816 7.11h10.461M7.816 11.172h6.404m-6.404 4.062h4.22"/><path d="M16.047 15.893a2.945 2.945 0 115.89 0 2.945 2.945 0 01-5.89 0z"/><path d="M18.992 18.869c2.94 0 5.394 2.086 5.97 4.863v-.014c.134.646-.353 1.266-1.01 1.266h-9.919a1.037 1.037 0 01-1.01-1.253c.576-2.776 3.03-4.862 5.97-4.862z"/></g></svg>',
				'automation_data'       => '{"id":"89","name":"Opt-in Welcome Email","author":"1","trigger_name":"mint_form_submission","status":"draft","created_at":"2023-02-23 02:56:28","updated_at":"2023-02-23 02:56:28","steps":[{"id":"239","automation_id":"89","step_id":"58ikk","key":"mint_form_submission","type":"trigger","settings":[],"next_step_id":"60fxff","created_at":"2023-02-23 02:56:28","updated_at":"2023-02-23 02:56:28","enterance":0,"completed":0,"exited":0},{"id":"240","automation_id":"89","step_id":"60fxff","key":"addTag","type":"action","settings":{"tag_settings":{"tags":[]}},"next_step_id":"wnfg5","created_at":"2023-02-23 02:56:28","updated_at":"2023-02-23 02:56:28","enterance":0,"completed":0,"exited":0},{"id":"241","automation_id":"89","step_id":"wnfg5","key":"delay","type":"action","settings":{"delay_settings":{"delay":1,"unit":"minutes"}},"next_step_id":"6ira7","created_at":"2023-02-23 02:56:28","updated_at":"2023-02-23 02:56:28","enterance":0,"completed":0,"exited":0},{"id":"242","automation_id":"89","step_id":"6ira7","key":"sendMail","type":"action","settings":{"message_data":{"subject":"Welcome email from mint","sender_email":"dev-email@flywheel.local","sender_name":"mail-mint","reply_name":"mail-mint","reply_email":"dev-email@flywheel.local","email_preview_text":"","body":"","json_body":""}},"next_step_id":"a:0:{}","created_at":"2023-02-23 02:56:28","updated_at":"2023-02-23 02:56:28","enterance":0,"completed":0,"exited":0}]}',
				'tags'					=> ['Mail Mint', 'Lead Form'],
				'category'              => ['mail_mint', 'lead_gen'],
			),
			array(
				'id'                    => 2,
				'isPro'                 => false,
				'type'                  => 'mailmint',
				'automationTitle'       => 'New User Welcome Email',
				'automationDescription' => 'When a new user registers on your site, add the user to a list and follow up with a welcome email.',
				'icon'                  => '<svg width="25" height="26" fill="none" viewBox="0 0 25 26" xmlns="http://www.w3.org/2000/svg"><path fill="#573BFF" d="M17.332 11.172c0-.561-.455-1.016-1.016-1.016H5.856a1.016 1.016 0 100 2.031h10.46c.561 0 1.016-.454 1.016-1.015zM5.855 14.219a1.016 1.016 0 100 2.031h6.354a1.016 1.016 0 000-2.031H5.855z"/><path fill="#573BFF" d="M8.24 23.969H4.844a2.034 2.034 0 01-2.032-2.032V4.063c0-1.12.912-2.03 2.032-2.03H17.33c1.12 0 2.032.91 2.032 2.03v6.247a1.016 1.016 0 102.03 0V4.063A4.067 4.067 0 0017.33 0H4.844A4.067 4.067 0 00.78 4.063v17.875A4.067 4.067 0 004.844 26H8.24a1.016 1.016 0 100-2.031z"/><path fill="#573BFF" d="M23.194 14.705a3.05 3.05 0 00-4.309 0l-5.576 5.563a1.016 1.016 0 00-.254.424l-1.214 3.997a1.016 1.016 0 001.243 1.274l4.099-1.135c.169-.047.322-.136.446-.26l5.565-5.554a3.05 3.05 0 000-4.309zm-6.81 8.237l-2.063.57.604-1.986 3.762-3.754 1.436 1.437-3.74 3.733zm5.374-5.365l-.197.196-1.436-1.436.196-.195a1.017 1.017 0 011.437 1.435zM16.316 6.094H5.856a1.016 1.016 0 100 2.031h10.46a1.016 1.016 0 000-2.031z"/></svg>',
				'automation_data'       => '{"id":"90","name":"New User Welcome Email","author":"1","trigger_name":"wp_user_registration","status":"draft","created_at":"2023-02-23 02:58:37","updated_at":"2023-02-23 02:58:43","steps":[{"id":"243","automation_id":"90","step_id":"p7upq","key":"wp_user_registration","type":"trigger","settings":[],"next_step_id":"2roiq","created_at":"2023-02-23 02:58:37","updated_at":"2023-02-23 02:58:43","enterance":0,"completed":0,"exited":0},{"id":"244","automation_id":"90","step_id":"2roiq","key":"addList","type":"action","settings":{"list_settings":{"lists":[]}},"next_step_id":"rb4p5","created_at":"2023-02-23 02:58:37","updated_at":"2023-02-23 02:58:43","enterance":0,"completed":0,"exited":0},{"id":"245","automation_id":"90","step_id":"rb4p5","key":"delay","type":"action","settings":{"delay_settings":{"delay":1,"unit":"minutes"}},"next_step_id":"xmgeb","created_at":"2023-02-23 02:58:37","updated_at":"2023-02-23 02:58:43","enterance":0,"completed":0,"exited":0},{"id":"246","automation_id":"90","step_id":"xmgeb","key":"sendMail","type":"action","settings":{"message_data":{"subject":"Welcome email from mint","sender_email":"dev-email@flywheel.local","sender_name":"mail-mint","reply_name":"mail-mint","reply_email":"dev-email@flywheel.local","email_preview_text":"","body":"","json_body":""}},"next_step_id":"a:0:{}","created_at":"2023-02-23 02:58:37","updated_at":"2023-02-23 02:58:43","enterance":0,"completed":0,"exited":0}]}',
				'tags'					=> ['WordPress'],
				'category'				=> ['wordpress'],
			),
			array(
				'id'                    => 3,
				'isPro'                 => false,
				'type'                  => 'mailmint',
				'automationTitle'       => 'User Login Notification',
				'automationDescription' => 'Send a notification email to the user\'s email when logging in.',
				'icon'                  => '<svg width="26" height="26" fill="none" viewBox="0 0 26 26"><g fill="#573BFF" clip-path="url(#clip0_5499_2255)"><path d="M6.678 23.969H3.082c-.434 0-.695-.242-.812-.387a1.061 1.061 0 01-.216-.89c1.055-5.065 5.526-8.765 10.687-8.885a6.946 6.946 0 00.52 0c1.262.029 2.495.266 3.668.707a1.016 1.016 0 10.714-1.902 13.169 13.169 0 00-.507-.178 6.9 6.9 0 002.77-5.528A6.914 6.914 0 0013 0a6.914 6.914 0 00-6.906 6.906 6.9 6.9 0 002.775 5.531 13.2 13.2 0 00-4.257 2.34 13.25 13.25 0 00-4.546 7.502c-.191.917.038 1.86.628 2.585A3.066 3.066 0 003.082 26h3.596a1.016 1.016 0 100-2.031zM8.125 6.906A4.88 4.88 0 0113 2.031a4.88 4.88 0 014.875 4.875 4.881 4.881 0 01-4.638 4.87 13.39 13.39 0 00-.475 0 4.881 4.881 0 01-4.637-4.87z"/><path d="M25.223 17.5a2.737 2.737 0 00-2.498-1.605h-3.174c-1.142 0-2.14.673-2.54 1.716-.054.14-.119.314-.187.518h-6.65c-.274 0-.537.111-.728.308L7.7 20.234a1.016 1.016 0 00.004 1.42l1.778 1.808c.19.194.452.304.724.304h3.3a1.016 1.016 0 000-2.032h-2.874l-.784-.797.755-.777h6.982c.468 0 .875-.32.986-.774.082-.335.186-.658.335-1.046.098-.256.345-.414.647-.414H22.723c.287 0 .534.155.645.403.273.61.598 1.554.6 2.589.003 1.042-.322 2.008-.595 2.635a.694.694 0 01-.638.416H19.527a.723.723 0 01-.654-.45 6.752 6.752 0 01-.315-1.005 1.016 1.016 0 00-1.976.472c.118.493.253.92.413 1.307A2.74 2.74 0 0019.525 26H22.737a2.723 2.723 0 002.498-1.635c.35-.805.768-2.057.765-3.452-.003-1.391-.425-2.624-.777-3.413z"/><path d="M21.887 21.938a1.016 1.016 0 100-2.032 1.016 1.016 0 000 2.032z"/></g><defs><clipPath id="clip0_5499_2255"><path fill="#fff" d="M0 0h26v26H0z"/></clipPath></defs></svg>',
				'automation_data'       => '{"id":"91","name":"User Login Notification","author":"1","trigger_name":"wp_user_login","status":"draft","created_at":"2023-02-23 02:59:30","updated_at":"2023-02-23 02:59:30","steps":[{"id":"247","automation_id":"91","step_id":"3mrulg","key":"wp_user_login","type":"trigger","settings":[],"next_step_id":"yg52f","created_at":"2023-02-23 02:59:31","updated_at":"2023-02-23 02:59:31","enterance":0,"completed":0,"exited":0},{"id":"248","automation_id":"91","step_id":"yg52f","key":"delay","type":"action","settings":{"delay_settings":{"delay":1,"unit":"minutes"}},"next_step_id":"1xz6m","created_at":"2023-02-23 02:59:31","updated_at":"2023-02-23 02:59:31","enterance":0,"completed":0,"exited":0},{"id":"249","automation_id":"91","step_id":"1xz6m","key":"sendMail","type":"action","settings":{"message_data":{"subject":"Welcome email from mint","sender_email":"dev-email@flywheel.local","sender_name":"mail-mint","reply_name":"mail-mint","reply_email":"dev-email@flywheel.local","email_preview_text":"","body":"","json_body":""}},"next_step_id":"51b5m","created_at":"2023-02-23 02:59:31","updated_at":"2023-02-23 02:59:31","enterance":0,"completed":0,"exited":0}]}',
				'tags'					=> ['WordPress'],
				'category'				=> ['wordpress'],
			),
		);
		$recipe = array_merge( $recipe, self::mint_woocommerce_recipe() );
		$recipe = array_merge( $recipe, self::mint_edd_recipe() );
		$recipe = array_merge( $recipe, self::mint_abandoned_cart_recipe() );
		$recipe = array_merge( $recipe, self::mint_pro_recipe() );
		return apply_filters( 'mailmint_all_automation_recipe', $recipe );
	}

	/**
	 * Woocommerce Trigger Recipe.
	 *
	 * @return array
	 */
	public static function mint_woocommerce_recipe() {
		$wc_recipe =
			array(
				array(
					'id'                    => 4,
					'isPro'                 => true,
					'type'                  => 'wc',
					'automationTitle'       => 'First Order Engagement (Woo)',
					'automationDescription' => 'Welcome your new customers on their first order in the store, and follow-up with potential offers.',
					'icon'                  => '<svg width="30" height="26" fill="none" viewBox="0 0 30 26"><path fill="#573BFF" d="M28.983 7.896h-3.074L19.215.336a.937.937 0 00-1.438 0 1.255 1.255 0 000 1.624l5.256 5.936h-4.699V4.94c0-.634-.455-1.148-1.017-1.148h-4.634c-.561 0-1.017.514-1.017 1.148v2.955H6.968l5.256-5.936a1.255 1.255 0 000-1.624.937.937 0 00-1.438 0l-6.694 7.56H1.017C.455 7.896 0 8.41 0 9.044c0 .634.455 1.148 1.017 1.148H2.13v11.183C2.13 23.925 3.967 26 6.226 26h17.549c2.258 0 4.095-2.075 4.095-4.625V10.192h1.113c.562 0 1.017-.514 1.017-1.148 0-.634-.455-1.148-1.017-1.148zM13.7 14.134V6.09h2.6v8.045c0 .634.455 1.148 1.017 1.148h1.248L15 19.173l-3.565-3.89h1.248c.562 0 1.017-.515 1.017-1.149zm12.137 7.241c0 1.284-.925 2.33-2.062 2.33H6.225c-1.136 0-2.061-1.046-2.061-2.33V10.192h7.502v2.794H8.92c-.415 0-.788.284-.943.718-.156.434-.062.93.236 1.256l6.08 6.635a.956.956 0 00.707.323c.255 0 .51-.107.707-.323l6.08-6.635c.298-.326.392-.822.236-1.256-.155-.434-.528-.718-.942-.718h-2.747v-2.794h7.503v11.183z"/></svg>',
					'tags'					=> ['WooCommerce', 'Orders'],
					'category'				=> ['woocommerce'],
				),
				array(
					'id'                    => 5,
					'isPro'                 => true,
					'type'                  => 'wc',
					'automationTitle'       => 'New Order Follow-up Emails (Woo)',
					'automationDescription' => 'When a new order is placed, add the customer to specific lists and initiate a promotion email sequence.',
					'icon'                  => '<svg width="26" height="26" fill="none" viewBox="0 0 26 26"><g fill="#573BFF" clip-path="url(#clip0_5499_2322)"><path d="M12.083 16.586a.824.824 0 001.639-.169l-.38-3.675a.824.824 0 00-1.638.17l.38 3.674zM17.235 17.321a.825.825 0 00.904-.735l.38-3.675a.823.823 0 10-1.639-.17l-.38 3.676a.824.824 0 00.735.904zM10.858 20.9a2.552 2.552 0 00-2.55 2.55 2.553 2.553 0 002.55 2.55 2.553 2.553 0 002.55-2.55 2.552 2.552 0 00-2.55-2.55zm0 3.453a.903.903 0 010-1.805.903.903 0 010 1.805zM19.362 20.9a2.552 2.552 0 00-2.55 2.55 2.553 2.553 0 002.55 2.55 2.553 2.553 0 002.55-2.55 2.552 2.552 0 00-2.55-2.55zm0 3.453a.904.904 0 010-1.805.903.903 0 010 1.805z"/><path d="M24.29 9.26a.824.824 0 00-.652-.32H7.22l-.69-2.651a.824.824 0 00-.797-.617H2.36a.824.824 0 000 1.648h2.739l.683 2.627a.826.826 0 00.013.049l2.542 9.775a.824.824 0 00.797.617h11.956a.824.824 0 00.797-.617l2.549-9.8a.824.824 0 00-.146-.711zm-3.838 9.48H9.77l-2.12-8.153h14.922l-2.12 8.154zM11.706 4.312h4.824l-1.26 1.26a.824.824 0 001.165 1.165L19.1 4.072a.824.824 0 000-1.164L16.433.24a.824.824 0 00-1.164 1.165l1.259 1.26h-4.822a.824.824 0 000 1.646z"/></g><defs><clipPath id="clip0_5499_2322"><path fill="#fff" d="M0 0h26v26H0z"/></clipPath></defs></svg>',
					'tags'					=> ['WooCommerce', 'Orders'],
					'category'				=> ['woocommerce'],
				),
				array(
					'id'                    => 6,
					'isPro'                 => true,
					'type'                  => 'wc',
					'automationTitle'       => 'Completed Order Follow-up (Woo)',
					'automationDescription' => 'Once a buyer completes an order, send an engaging email with a pleasant \'Thank You\' and more product suggestions.',
					'icon'                  => '<svg width="28" height="28" fill="none" viewBox="0 0 28 28"><path fill="#573BFF" stroke="#573BFF" stroke-width=".3" d="M13.709 25.444l-.376.187L2.3 20.728V7.85l10.785 4.794a.651.651 0 00.528 0L24.4 7.85v5.5a.65.65 0 001.3 0v-6.5a.65.65 0 00-.386-.594l-11.7-5.2a.652.652 0 00-.528 0l-11.7 5.2A.65.65 0 001 6.851v14.3a.65.65 0 00.386.593l11.7 5.2a.65.65 0 00.554-.012l.65-.325a.65.65 0 10-.582-1.163h0zm-.36-14.104L8.751 9.295l10.062-4.472c.012-.005.021-.013.032-.019l4.603 2.046-10.099 4.488zm0-8.978l3.9 1.734-10.013 4.45a.64.64 0 00-.075.043l-3.91-1.738 10.098-4.489z"/><path fill="#573BFF" stroke="#573BFF" stroke-width=".3" d="M20.5 14a6.5 6.5 0 106.5 6.5 6.507 6.507 0 00-6.5-6.5zm0 11.7a5.2 5.2 0 110-10.399 5.2 5.2 0 010 10.399z"/><path fill="#573BFF" stroke="#573BFF" stroke-width=".3" d="M22.64 18.741l-2.79 2.79-1.165-1.165a.65.65 0 00-.461-.196c-.086 0-.25.05-.25.05l-.213.14s-.11.134-.142.213a.65.65 0 00.147.712l1.625 1.625a.648.648 0 00.919 0l3.25-3.25a.65.65 0 00-.92-.919z"/></svg>',
					'tags'					=> ['WooCommerce', 'Orders'],
					'category'				=> ['woocommerce'],
				),
				array(
					'id'                    => 8,
					'isPro'                 => true,
					'type'                  => 'wc',
					'automationTitle'       => 'Recover Failed Orders (Woo)',
					'automationDescription' => 'When an order is failed, send an email to the customer to try to complete the order again.',
					'icon'                  => '<svg width="26" height="26" fill="none" viewBox="0 0 26 26"><path fill="#573BFF" d="M10.693 24.363a1.6 1.6 0 01-.279.904 1.662 1.662 0 01-.747.602 1.71 1.71 0 01-1.821-.346 1.616 1.616 0 01-.46-.832 1.59 1.59 0 01.09-.94c.125-.298.338-.553.611-.733.274-.18.596-.278.926-.28h.014c.442 0 .865.172 1.178.476.313.305.488.718.488 1.149zm9-1.624h-.013a1.696 1.696 0 00-1.18.48 1.61 1.61 0 00-.487 1.154c0 .431.178.845.491 1.15.314.306.74.477 1.182.477.444 0 .869-.171 1.182-.477a1.61 1.61 0 00.492-1.15 1.61 1.61 0 00-.487-1.153 1.696 1.696 0 00-1.18-.481zM25.939 8.11l-1.352 8.011a4.202 4.202 0 01-.46 1.683c-.269.524-.646.989-1.107 1.365a4.436 4.436 0 01-1.574.824 4.509 4.509 0 01-1.78.146H8.646a3.739 3.739 0 01-2.399-.878 3.565 3.565 0 01-1.23-2.19L3 3.343a1.62 1.62 0 00-.558-.994 1.7 1.7 0 00-1.087-.4H1c-.265 0-.52-.103-.707-.285A.962.962 0 010 .974C0 .717.105.469.293.286.48.103.735 0 1 0h.355a3.74 3.74 0 012.397.878 3.565 3.565 0 011.23 2.19l.123.83h17.228c.537 0 1.068.115 1.554.337.487.221.917.545 1.261.947.345.401.594.872.73 1.378.138.506.159 1.036.063 1.55h-.002zm-2.327-1.68a1.66 1.66 0 00-.573-.43 1.7 1.7 0 00-.706-.154H5.39l1.6 10.95c.058.387.257.74.561.996a1.7 1.7 0 001.093.398h11.021c2.134 0 2.648-.78 2.95-2.4l1.353-8.013a1.588 1.588 0 00-.357-1.346zm-5.946 4.613h-5.333c-.265 0-.52.103-.707.286a.962.962 0 00-.293.689c0 .258.105.506.293.69.188.182.442.284.707.284h5.333c.265 0 .52-.102.707-.285a.962.962 0 00.293-.689.962.962 0 00-.293-.69 1.014 1.014 0 00-.707-.285z"/></svg>',
					'tags'					=> ['WooCommerce', 'Orders'],
					'category'				=> ['woocommerce'],
				),
			);
		if ( HelperFunctions::is_wc_active() ) {
			return apply_filters( 'mintmail_automation_wc_recipe', $wc_recipe );
		}
		return array();
	}

	/**
	 * Edd Recipe.
	 *
	 * @return array
	 */
	public static function mint_edd_recipe() {
		$edd_recipe =
			array(
				array(
					'id'                    => 9,
					'isPro'                 => true,
					'type'                  => 'edd',
					'automationTitle'       => 'Completed Order Follow-up (EDD)',
					'automationDescription' => 'Once a buyer completes an order, send an engaging email with a pleasant \'Thank You\' and more product suggestions.',
					'icon'                  => '<svg width="26" height="26" fill="none" viewBox="0 0 26 26"><path fill="#573BFF" d="M10.693 24.363a1.6 1.6 0 01-.279.904 1.662 1.662 0 01-.747.602 1.71 1.71 0 01-1.821-.346 1.616 1.616 0 01-.46-.832 1.59 1.59 0 01.09-.94c.125-.298.338-.553.611-.733.274-.18.596-.278.926-.28h.014c.442 0 .865.172 1.178.476.313.305.488.718.488 1.149zm9-1.624h-.013a1.696 1.696 0 00-1.18.48 1.61 1.61 0 00-.487 1.154c0 .431.178.845.491 1.15.314.306.74.477 1.182.477.444 0 .869-.171 1.182-.477a1.61 1.61 0 00.492-1.15 1.61 1.61 0 00-.487-1.153 1.696 1.696 0 00-1.18-.481zM25.939 8.11l-1.352 8.011a4.202 4.202 0 01-.46 1.683c-.269.524-.646.989-1.107 1.365a4.436 4.436 0 01-1.574.824 4.509 4.509 0 01-1.78.146H8.646a3.739 3.739 0 01-2.399-.878 3.565 3.565 0 01-1.23-2.19L3 3.343a1.62 1.62 0 00-.558-.994 1.7 1.7 0 00-1.087-.4H1c-.265 0-.52-.103-.707-.285A.962.962 0 010 .974C0 .717.105.469.293.286.48.103.735 0 1 0h.355a3.74 3.74 0 012.397.878 3.565 3.565 0 011.23 2.19l.123.83h17.228c.537 0 1.068.115 1.554.337.487.221.917.545 1.261.947.345.401.594.872.73 1.378.138.506.158 1.036.061 1.55zm-2.327-1.68a1.66 1.66 0 00-.573-.43 1.7 1.7 0 00-.706-.154H5.39l1.6 10.95c.058.387.257.74.561.996a1.7 1.7 0 001.093.398h11.021c2.134 0 2.648-.78 2.95-2.4l1.353-8.013a1.588 1.588 0 00-.357-1.346zM16.26 9.598l-2.848 2.775-1.067-1.04a1.014 1.014 0 00-.703-.273 1.013 1.013 0 00-.698.285.96.96 0 00-.013 1.366l1.777 1.732a1.003 1.003 0 00.707.286 1.022 1.022 0 00.708-.286l3.552-3.463a.962.962 0 00.293-.689.962.962 0 00-.293-.69 1.014 1.014 0 00-.708-.285c-.265 0-.52.103-.707.286v-.004z"/></svg>',
					'tags'					=> ['EDD', 'Orders'],
					'category'				=> ['edd'],
				),
				array(
					'id'                    => 10,
					'isPro'                 => true,
					'type'                  => 'edd',
					'automationTitle'       => 'New Customer Onboarding Emails (EDD)',
					'automationDescription' => 'When a new order is placed, add the customer to specific lists and initiate a promotion email sequence.',
					'icon'                  => '<svg width="30" height="26" fill="none" viewBox="0 0 30 26"><path fill="#573BFF" d="M28.786 16.714a.929.929 0 00-.928.929v6.5h-26V13h10.214a.929.929 0 000-1.857H1.857V7.428h10.215a.929.929 0 000-1.857H1.857A1.858 1.858 0 000 7.428v16.715C0 25.168.832 26 1.857 26h26a1.858 1.858 0 001.858-1.857v-6.5a.929.929 0 00-.929-.929z"/><path fill="#573BFF" d="M8.35 16.714H4.636a.929.929 0 000 1.858H8.35a.929.929 0 000-1.858zM29.146 2.86l-6.5-2.786a.948.948 0 00-.733 0l-6.5 2.786a.93.93 0 00-.561.854v3.714c0 5.11 1.888 8.096 6.966 11.02a.932.932 0 00.925 0c5.077-2.917 6.966-5.903 6.966-11.02V3.714a.93.93 0 00-.563-.854zm-1.294 4.568c0 4.288-1.42 6.649-5.572 9.138-4.152-2.495-5.571-4.855-5.571-9.138V4.327l5.571-2.389 5.572 2.389v3.101z"/><path fill="#573BFF" d="M25.643 5.774a.935.935 0 00-1.305.144l-2.92 3.652-1.155-1.728a.93.93 0 00-1.287-.258.93.93 0 00-.258 1.287l1.857 2.786c.165.247.437.4.734.414h.039c.28 0 .547-.126.726-.349l3.714-4.643a.93.93 0 00-.145-1.305z"/></svg>',
					'tags'					=> ['EDD', 'Orders'],
					'category'				=> ['edd'],
				),

			);
		if ( HelperFunctions::is_edd_active() ) {
			return apply_filters( 'mintmail_automation_edd_recipe', $edd_recipe );
		}
		return array();
	}


	/**
	 * Edd Recipe.
	 *
	 * @return array
	 */
	public static function mint_abandoned_cart_recipe() {
		$cart_recipe =
			array(
				array(
					'id'                    => 11,
					'isPro'                 => true,
					'type'                  => 'abandoned_cart',
					'automationTitle'       => __( 'Abandoned Cart Recovery Emails', 'mrm' ),
					'automationDescription' => __( 'When a user abandons their cart, send recovery emails to guide them to purchase their desired items with attractive Offer.', 'mrm' ),
					'icon'                  => '<svg width="26" height="26" viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_8040_2689)"><path fill-rule="evenodd" clip-rule="evenodd" d="M18.353 4.58817H21.7941C22.2165 4.58817 22.5589 4.93054 22.5589 5.35289V7.7426L25.687 10.0887C25.8768 10.2279 26 10.4525 26 10.7058V24.4706C26 24.8929 25.6577 25.2353 25.2353 25.2353H0.764715C0.342367 25.2352 0 24.8929 0 24.4705V10.7058C0 10.4525 0.123195 10.2279 0.313016 10.0887L3.44119 7.7426V5.35289C3.44119 4.93054 3.78356 4.58817 4.20591 4.58817H7.6471L12.5412 0.917602C12.8131 0.713664 13.1869 0.713664 13.4589 0.917602L18.353 4.58817ZM4.97057 13.6554L9.1 17.5882H17.2715L21.032 13.8277C21.0302 13.8069 21.0294 13.7858 21.0294 13.7646V6.1176H4.97057V13.6554ZM15.8039 4.58817L13 2.48522L10.1961 4.58817H15.8039ZM22.5588 12.3008L24.0711 10.7886L22.5588 9.65436V12.3008ZM3.44119 12.1988V9.65436L1.94655 10.7754L3.44119 12.1988ZM17.32 19.1176H8.79409C8.75789 19.1176 8.72193 19.115 8.68649 19.11C8.65069 19.1382 8.6157 19.1657 8.57157 19.2004C8.32188 19.3968 8.04304 19.6156 7.74191 19.8512C6.88157 20.5245 6.01519 21.1979 5.19782 21.8266C5.12205 21.8849 5.12205 21.8849 5.04618 21.9432C4.05564 22.704 3.30449 23.2779 2.72609 23.7058H23.0552L17.32 19.1176ZM1.52943 12.4901V22.6769C1.5535 22.6594 6.4612 18.8924 7.45271 18.1314L1.52943 12.4901ZM24.4706 22.8795V12.552L18.7331 18.2895L24.4706 22.8795ZM12.6177 12.6832L15.5181 9.78269C15.8168 9.48404 16.301 9.48404 16.5996 9.78269C16.8982 10.0813 16.8982 10.5655 16.5996 10.8641L13.1584 14.3053C12.8597 14.604 12.3755 14.604 12.0769 14.3053L10.1652 12.3936C9.86654 12.0949 9.86654 11.6107 10.1652 11.3121C10.4638 11.0135 10.948 11.0135 11.2466 11.3121L12.6177 12.6832Z" fill="#573BFF"/></g><defs><clipPath id="clip0_8040_2689"><rect width="26" height="26" fill="white"/></clipPath></defs></svg>',
					'tags'					=> ['WooCommerce', 'Abandoned Cart'],
					'category'				=> ['wooCommerce', 'abandoned_cart'],
				),
				array(
					'id'                    => 12,
					'isPro'                 => true,
					'type'                  => 'abandoned_cart',
					'automationTitle'       => __( 'Successful Cart Recovery', 'mrm' ),
					'automationDescription' => __( 'When a user completes their order via the cart recovery emails, send them a thank you email and confirm that their cart has been successfully recovered.', 'mrm' ),
					'icon'                  => '<svg width="28" height="27" viewBox="0 0 28 27" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M26.9205 9.35099H23.189L17.7661 0.933258C17.4359 0.420706 16.7654 0.281054 16.2729 0.624241C15.7776 0.965942 15.644 1.65677 15.9743 2.16932L20.6017 9.35099H7.3956L12.0231 2.16932C12.3533 1.65677 12.2198 0.965942 11.7244 0.624241C11.2291 0.28254 10.5614 0.420706 10.2312 0.933258L4.80836 9.35099H1.07682C0.482415 9.35099 0 9.85017 0 10.4652C0 11.0803 0.482415 11.5795 1.07682 11.5795H1.62959L3.8665 23.1512C4.23406 25.0544 5.86221 26.436 7.73875 26.436H20.26C22.1365 26.436 23.7633 25.0544 24.1323 23.1512L26.3692 11.5795H26.9219C27.5163 11.5795 27.9988 11.0803 27.9988 10.4652C27.9988 9.85017 27.5149 9.35099 26.9205 9.35099ZM22.0188 22.7145C21.8508 23.5791 21.1114 24.2076 20.2586 24.2076H7.73875C6.8859 24.2076 6.14649 23.5791 5.9785 22.7145L3.8263 11.5795H24.171L22.0188 22.7145Z" fill="#573BFF"/><path fill-rule="evenodd" clip-rule="evenodd" d="M17.7625 14.2348C17.4458 13.9217 16.9323 13.9217 16.6156 14.2348L12.7408 18.0648L11.3845 16.724C11.0678 16.411 10.5543 16.411 10.2376 16.724C9.92081 17.0371 9.92081 17.5446 10.2376 17.8577L12.1674 19.7652C12.4841 20.0783 12.9976 20.0783 13.3143 19.7652C13.3251 19.7546 13.3354 19.7437 13.3455 19.7327L13.3464 19.7336L17.7625 15.3684C18.0792 15.0554 18.0792 14.5478 17.7625 14.2348Z" fill="#573BFF" stroke="#573BFF" stroke-width="0.4"/></svg>',
					'tags'					=> ['WooCommerce', 'Abandoned Cart'],
					'category'				=> ['wooCommerce', 'abandoned_cart'],
				),

			);
		if ( HelperFunctions::is_wc_active() ) {
			return apply_filters( 'mintmail_automation_abandoned_cart_recipe', $cart_recipe );
		}
		return array();
	}


	public static function mint_pro_recipe(){
		if(MrmCommon::is_mailmint_pro_active()){
			$mint_recipe =
			array(
				array(
					'id'                    => 13,
					'isPro'                 => true,
					'type'                  => 'mailmint',
					'automationTitle'       => 'Automate Birthday Wishes',
					'automationDescription' => "Celebrate your customers' birthdays with a special email, delivered right on time to make their day even more memorable.",
					'icon'                  => '<svg width="22" height="22" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_9905_2788)"><path fill="#573BFF" stroke="#fff" stroke-width=".1" d="M18.283 18.334v.05h.884a.783.783 0 110 1.567H.833a.783.783 0 010-1.567h.884v-6.717A4.122 4.122 0 015.833 7.55h3.384V5l-.03-.013a2.033 2.033 0 01-1.22-1.856A6.027 6.027 0 019.422.307h0a.783.783 0 011.156 0h0a6.028 6.028 0 011.455 2.824 2.034 2.034 0 01-1.22 1.856l-.03.013v2.55h3.384a4.122 4.122 0 014.116 4.117v6.667zm-1.622-4.967l.056.006v-1.706a2.55 2.55 0 00-2.55-2.55H5.833a2.55 2.55 0 00-2.55 2.55v1.706l.055-.005a1.89 1.89 0 00.938-.35c.112-.083.2-.174.261-.262a.471.471 0 00.096-.255.783.783 0 111.567 0c0 .079.042.173.11.267.07.095.17.195.3.286.26.182.634.33 1.098.33.456 0 .83-.14 1.091-.318.131-.09.234-.188.305-.285a.5.5 0 00.113-.28.783.783 0 111.566 0c0 .079.042.173.11.267.07.095.171.195.3.286.26.182.634.33 1.099.33.455 0 .83-.14 1.09-.318a1.27 1.27 0 00.306-.285.5.5 0 00.112-.28.783.783 0 111.567 0c0 .073.035.158.094.242.06.086.147.177.258.262.222.17.543.32.942.362zm-13.33 1.566l-.048.002v3.448h13.434v-3.449l-.048-.002a3.414 3.414 0 01-2.053-.803l-.032-.027-.033.027a3.54 3.54 0 01-4.52.012L10 14.115l-.032.026a3.54 3.54 0 01-4.52-.012l-.032-.027-.032.027a3.414 3.414 0 01-2.053.804z"></path></g><defs><clipPath id="clip0_9905_2788"><path fill="#fff" d="M0 0h20v20H0z"></path></clipPath></defs></svg>',
					'tags'					=> ["Mail Mint"],
					'category'				=> ['mail_mint'],
				),

			);
			if ( true ) {
				return apply_filters( 'mintmail_automation_mailmint_pro_recipe', $mint_recipe );
			}
		}
		
		return array();
	}




	/**
	 * Get single recipe by ID.
	 *
	 * @param string $id Automation recipe ID.
	 * @return array|mixed
	 */
	public static function get_single_recipe( $id ) {
		$recipe            = self::get_all_recipe();
		$get_single_recipe = array();

		foreach ( $recipe as $key => $value ) {
			if ( intval( $id ) === $value['id'] ) {
				$get_single_recipe = $value;
			}
		}
		return $get_single_recipe;
	}


}
