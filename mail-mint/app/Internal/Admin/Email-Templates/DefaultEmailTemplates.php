<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Admin/EmailTemplates
 */

namespace Mint\MRM\Internal\Admin\EmailTemplates;

use MRM\Common\MrmCommon;

/**
 * Helper class for email templates
 *
 * @package /app/Internal/Admin/EmailTemplates
 * @since 1.0.0
 */
class DefaultEmailTemplates {

	/**
	 * Get default email templates
	 *
	 * @return array[]
	 *
	 * @since 1.0.0
	 */
	public static function get_default_templates() {
		$image_path = plugins_url( 'images', __FILE__ ) . '/';
		$pinterest  = 'pinterest.png';
		$instagram  = 'instagram.png';
		$facebook   = 'facebook.png';
		$twitter    = 'twitter.png';
    $address    = MrmCommon::get_business_full_address() ? MrmCommon::get_business_full_address() : '{{business.address}}';
    $busi_name  = MrmCommon::get_business_name() ? MrmCommon::get_business_name() : '{{business.name}}';

		return apply_filters(
			'mail_mint_email_templates',
			array(

                array(
                    'id'              => 1,
                    'is_pro'          => false,
                    'emailCategories' => ['Selling Products'],
                    'industry'        => ['Fashion & Jewelry'],
                    'title'           => 'Product Suggestion',
                    'json_content'    => [
                        'subject' => 'Welcome to MINT CRM email',
                        'subTitle' => 'Nice to meet you!',
                        'content' => [
                            'type' => 'page',
                            'data' => [
                                'value' => [
                                    'breakpoint' => '480px',
                                    'headAttributes' => '',
                                    'font-size' => '14px',
                                    'line-height' => '1.7',
                                    'headStyles' => [
                                    ],
                                    'fonts' => [
                                    ],
                                    'responsive' => true,
                                    'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
                                    'text-color' => '#000000',
                                ],
                            ],
                            'attributes' => [
                                'background-color' => '#ececec',
                                'width' => '600px',
                                'css-class' => 'mjml-body',
                            ],
                            'children' => [
                                0 => [
                                    'type' => 'advanced_wrapper',
                                    'data' => [
                                        'value' => [
                                        ],
                                    ],
                                    'attributes' => [
                                        'background-color' => '#F4F5FB',
                                        'padding' => '24px 24px 40px 24px',
                                        'border' => 'none',
                                        'direction' => 'ltr',
                                        'text-align' => 'center',
                                    ],
                                    'children' => [
                                        0 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 20px 0px',
                                                'src' => $image_path . 'your-logo.png',
                                                'width' => '100%',
                                                'href' => '#',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        1 => [
                                            'type' => 'advanced_hero',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#74CAE3',
                                                'background-position' => 'center center',
                                                'mode' => 'fluid-height',
                                                'padding' => '48px 20px 48px 20px',
                                                'vertical-align' => 'top',
                                                'background-url' => '',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => 'See Something you like?',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'padding' => '0px 0px 20px 0px',
                                                        'align' => 'center',
                                                        'color' => '#0E1D3F',
                                                        'font-size' => '36px',
                                                        'line-height' => '1',
                                                        'font-weight' => '700',
                                                        'font-family' => 'Lato',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                                1 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => "Hi there, we noticed you were browsing our site but haven't checked out yet.<div><br><div><span style=\"word-spacing: normal;\">Feel free to contact us if you have any questions about our products</span></div></div>",
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'background-color' => '#414141',
                                                        'color' => '#0E1D3F',
                                                        'font-weight' => '400',
                                                        'border-radius' => '3px',
                                                        'padding' => '0px 20px 0px 20px',
                                                        'inner-padding' => '10px 25px 10px 25px',
                                                        'line-height' => '1.75',
                                                        'target' => '_blank',
                                                        'vertical-align' => 'middle',
                                                        'border' => 'none',
                                                        'text-align' => 'center',
                                                        'href' => '#',
                                                        'font-size' => '16px',
                                                        'font-family' => 'Lato',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                                2 => [
                                                    'type' => 'button',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => 'Shop Now',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'background-color' => '#0E1D3F',
                                                        'color' => '#ffffff',
                                                        'font-size' => '15px',
                                                        'font-weight' => '700',
                                                        'border-radius' => '100px',
                                                        'padding' => '25px 0px 0px 0px',
                                                        'inner-padding' => '17px 35px 17px 35px',
                                                        'line-height' => '1',
                                                        'target' => '_blank',
                                                        'vertical-align' => 'middle',
                                                        'border' => 'none',
                                                        'text-align' => 'center',
                                                        'href' => '#',
                                                        'font-family' => 'Lato',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                            ],
                                        ],
                                        2 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '20px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        3 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'padding' => '20px 20px 20px 20px',
                                                'background-repeat' => 'repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => '50%',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_image',
                                                            'data' => [
                                                                'value' => [
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'height' => 'auto',
                                                                'padding' => '0px 0px 0px 0px',
                                                                'src' => $image_path . 'eugen-left.png',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                1 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => '50%',
                                                        'padding' => '40px 0px 40px 20px',
                                                        'vertical-align' => 'middle',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Multicolored Shawl',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0 0px 0',
                                                                'align' => 'left',
                                                                'font-family' => 'Lato',
                                                                'font-size' => '22px',
                                                                'font-weight' => '700',
                                                                'line-height' => '1.45',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#0E1D3F',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => '$99.00',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '6px 0 0 0',
                                                                'align' => 'left',
                                                                'font-family' => 'Lato',
                                                                'font-size' => '34px',
                                                                'font-weight' => '800',
                                                                'line-height' => '1',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#0E1D3F',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        2 => [
                                                            'type' => 'advanced_button',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Shop Now',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'left',
                                                                'font-family' => 'Lato',
                                                                'background-color' => '#0E1D3F',
                                                                'color' => '#ffffff',
                                                                'font-weight' => '700',
                                                                'font-style' => 'normal',
                                                                'border-radius' => '100px',
                                                                'padding' => '18px 0px 0 0',
                                                                'inner-padding' => '12px 25px 12px 25px',
                                                                'font-size' => '14px',
                                                                'line-height' => '1.05',
                                                                'target' => '_blank',
                                                                'vertical-align' => 'middle',
                                                                'border' => 'none',
                                                                'text-align' => 'center',
                                                                'letter-spacing' => 'normal',
                                                                'href' => '#',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        4 => [
                                            'type' => 'advanced_social',
                                            'data' => [
                                                'value' => [
                                                    'elements' => [
                                                        0 => [
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $pinterest,
                                                            'content' => '',
                                                        ],
                                                        1 => [
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $facebook,
                                                            'content' => '',
                                                        ],
                                                        2 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $instagram,
                                                            'content' => '',
                                                        ],
                                                        3 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $twitter,
                                                            'content' => '',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'color' => '#333333',
                                                'mode' => 'horizontal',
                                                'font-size' => '13px',
                                                'font-weight' => 'normal',
                                                'border-radius' => '3px',
                                                'padding' => '36px 25px 36px 25px',
                                                'inner-padding' => '4px 5px 4px 5px',
                                                'line-height' => '22px',
                                                'text-padding' => '4px 4px 4px 0px',
                                                'icon-padding' => '0px',
                                                'icon-size' => '40px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        5 => [
                                            'type' => 'advanced_divider',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'border-width' => '1px',
                                                'border-style' => 'solid',
                                                'border-color' => '#E2E3EC',
                                                'padding' => '0px 0px 0px 0px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        6 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp; |&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '36px 25px 12px 25px',
                                                'align' => 'center',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                                'line-height' => '1.47',
                                                'font-size' => '15px',
                                                'font-family' => 'Lato',
                                                'font-weight' => '400',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        7 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => '© '.date("Y") . ', ' . $busi_name .', '. $address,
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '10px 35px 10px 35px',
                                                'align' => 'center',
                                                'font-family' => 'Lato',
                                                'font-size' => '14px',
                                                'font-weight' => '400',
                                                'line-height' => '1.7',
                                                'letter-spacing' => 'normal',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/product-suggestion.png',
                ),
				array(
					'id'              => 2,
					'is_pro'          => true,
                    'emailCategories' => ['Selling Products'],
                    'industry'        => ['Business & Finance'],
					'title'           => 'Product Offer',
					'json_content'    => [],
					'html_content'    => '',
					'thumbnail_image' => $image_path . '/thumbnails/product-offer.png',
				),

                array(
                    'id'              => 3,
                    'is_pro'          => false,
                    'emailCategories' => ['Educate & Inform'],
                    'industry'        => ['E-commerce & Retail'],
                    'title'           => 'Order Received',
                    'json_content'    => [
                        'subject' => 'Welcome to MINT CRM email',
                        'subTitle' => 'Nice to meet you!',
                        'content' => [
                            'type' => 'page',
                            'data' => [
                                'value' => [
                                    'breakpoint' => '480px',
                                    'headAttributes' => '',
                                    'font-size' => '14px',
                                    'line-height' => '1.7',
                                    'headStyles' => [
                                    ],
                                    'fonts' => [
                                    ],
                                    'responsive' => true,
                                    'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
                                    'text-color' => '#000000',
                                ],
                            ],
                            'attributes' => [
                                'background-color' => '#ececec',
                                'width' => '600px',
                                'css-class' => 'mjml-body',
                            ],
                            'children' => [
                                0 => [
                                    'type' => 'advanced_wrapper',
                                    'data' => [
                                        'value' => [
                                        ],
                                    ],
                                    'attributes' => [
                                        'background-color' => '#F4F5FB',
                                        'padding' => '24px 24px 40px 24px',
                                        'border' => 'none',
                                        'direction' => 'ltr',
                                        'text-align' => 'center',
                                    ],
                                    'children' => [
                                        0 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 20px 0px',
                                                'src' => $image_path . 'your-logo.png',
                                                'width' => '100%',
                                                'href' => '#',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        1 => [
                                            'type' => 'advanced_hero',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#74CAE3',
                                                'background-position' => 'center center',
                                                'mode' => 'fluid-height',
                                                'padding' => '80px 30px 80px 30px',
                                                'vertical-align' => 'top',
                                                'background-url' => $image_path . 'thank-you-bg.png',
                                                'background-width' => '',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => 'That was Mail Mint',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'padding' => '0px 0px 10px 0px',
                                                        'align' => 'center',
                                                        'color' => '#ffff',
                                                        'font-size' => '40px',
                                                        'line-height' => '1.2',
                                                        'font-weight' => '800',
                                                        'font-family' => 'Lato',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                                1 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => 'Thanks for using Speed Checkout to place your order with Coffee House on January 14, 2024!',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'background-color' => '#414141',
                                                        'color' => '#FFFFFF',
                                                        'font-weight' => '400',
                                                        'border-radius' => '3px',
                                                        'padding' => '0px 0px 0px 0px',
                                                        'inner-padding' => '10px 25px 10px 25px',
                                                        'line-height' => '1.6',
                                                        'target' => '_blank',
                                                        'vertical-align' => 'middle',
                                                        'border' => 'none',
                                                        'text-align' => 'center',
                                                        'href' => '#',
                                                        'font-size' => '16px',
                                                        'font-family' => 'Lato',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                            ],
                                        ],
                                        2 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '20px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        3 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'padding' => '40px 30px 40px 30px',
                                                'background-repeat' => 'repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => '100%',
                                                        'padding' => '0px 0px 0px 0px',
                                                        'vertical-align' => 'middle',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Hi Jhon Doe,&nbsp;👋',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0 0px 0',
                                                                'align' => 'left',
                                                                'font-family' => 'Lato',
                                                                'font-size' => '24px',
                                                                'font-weight' => '700',
                                                                'line-height' => '1.45',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#0E1D3F',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Thanks for using Speed. You’ll get an order confirmation from Coffee House shortly with your full receipt.',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '6px 0px 0 0',
                                                                'align' => 'left',
                                                                'font-family' => 'Lato',
                                                                'font-size' => '16px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.6',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#878792',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        2 => [
                                                            'type' => 'advanced_button',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'View your order on speed.co',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'font-family' => 'Lato',
                                                                'background-color' => '#612EAB',
                                                                'color' => '#ffffff',
                                                                'font-weight' => '700',
                                                                'font-style' => 'normal',
                                                                'border-radius' => '100px',
                                                                'padding' => '15px 0px 0 0',
                                                                'inner-padding' => '17px 35px 17px 35px',
                                                                'font-size' => '14px',
                                                                'line-height' => '1.05',
                                                                'target' => '_blank',
                                                                'vertical-align' => 'middle',
                                                                'border' => 'none',
                                                                'text-align' => 'center',
                                                                'letter-spacing' => 'normal',
                                                                'href' => '#',
                                                                'container-background-color' => '',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        4 => [
                                            'type' => 'advanced_social',
                                            'data' => [
                                                'value' => [
                                                    'elements' => [
                                                        0 => [
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $pinterest,
                                                            'content' => '',
                                                        ],
                                                        1 => [
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $facebook,
                                                            'content' => '',
                                                        ],
                                                        2 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $instagram,
                                                            'content' => '',
                                                        ],
                                                        3 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $twitter,
                                                            'content' => '',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'color' => '#333333',
                                                'mode' => 'horizontal',
                                                'font-size' => '13px',
                                                'font-weight' => 'normal',
                                                'border-radius' => '3px',
                                                'padding' => '36px 25px 36px 25px',
                                                'inner-padding' => '4px 5px 4px 5px',
                                                'line-height' => '22px',
                                                'text-padding' => '4px 4px 4px 0px',
                                                'icon-padding' => '0px',
                                                'icon-size' => '40px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        5 => [
                                            'type' => 'advanced_divider',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'border-width' => '1px',
                                                'border-style' => 'solid',
                                                'border-color' => '#E2E3EC',
                                                'padding' => '0px 0px 0px 0px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        6 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '36px 25px 12px 25px',
                                                'align' => 'center',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                                'line-height' => '1.47',
                                                'font-size' => '15px',
                                                'font-family' => 'Lato',
                                                'font-weight' => '400',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        7 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                  'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '10px 35px 10px 35px',
                                                'align' => 'center',
                                                'font-family' => 'Lato',
                                                'font-size' => '14px',
                                                'font-weight' => '400',
                                                'line-height' => '1.7',
                                                'letter-spacing' => 'normal',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/order-received.jpg',
                ),
				array(
					'id'              => 4,
					'is_pro'          => true,
                    'emailCategories' => ['Announcement'],
                    'industry'        => ['Business & Finance'],
					'title'           => 'Coming Soon!',
					'json_content'    => [],
					'html_content'    => '',
					'thumbnail_image' => $image_path . '/thumbnails/coming-soon.jpg',
				),
				array(
					'id'              => 5,
					'is_pro'          => true,
                    'emailCategories' => ['Welcome'],
                    'industry'        => ['Business & Finance'],
					'title'           => 'Welcome Email-Skin Care!',
					'json_content'    => [],
					'html_content'    => '',
					'thumbnail_image' => $image_path . '/thumbnails/congratulate.png',
				),
				array(
					'id'              => 6,
					'is_pro'          => false,
                    'emailCategories' => ['Welcome'],
                    'industry'        => ['Business & Finance'],
					'title'           => 'Welcome Email',
					'json_content'    => [
						'subject' => 'Welcome to MINT CRM email',
						'subTitle' => 'Nice to meet you!',
						'content' => [
							'type' => 'page',
							'data' => [
								'value' => [
									'breakpoint' => '480px',
									'headAttributes' => '',
									'font-size' => '14px',
									'line-height' => '1.7',
									'headStyles' => [
									],
									'fonts' => [
									],
									'responsive' => true,
									'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
									'text-color' => '#000000',
								],
							],
							'attributes' => [
								'background-color' => '#ececec',
								'width' => '600px',
								'css-class' => 'mjml-body',
							],
							'children' => [
								0 => [
									'type' => 'advanced_wrapper',
									'data' => [
										'value' => [
										],
									],
									'attributes' => [
										'background-color' => '#F4F5FB',
										'padding' => '24px 24px 40px 24px',
										'border' => 'none',
										'direction' => 'ltr',
										'text-align' => 'center',
									],
									'children' => [
										0 => [
											'type' => 'advanced_image',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'align' => 'center',
												'height' => 'auto',
												'padding' => '0px 0px 20px 0px',
												'src' => $image_path . 'your-logo.png',
												'width' => '100%',
												'href' => '#',
											],
											'children' => [
											],
										],
										1 => [
											'type' => 'advanced_hero',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'background-color' => '#fff',
												'background-position' => 'center center',
												'mode' => 'fluid-height',
												'padding' => '40px 30px 45px 30px',
												'vertical-align' => 'top',
												'background-url' => '',
											],
											'children' => [
												0 => [
													'type' => 'text',
													'data' => [
														'value' => [
															'content' => 'Welcome to Boom',
														],
													],
													'attributes' => [
														'padding' => '0px 0px 10px 0px',
														'align' => 'center',
														'color' => '#0E1D3F',
														'font-size' => '30px',
														'line-height' => '1.2',
														'font-weight' => '700',
														'font-family' => 'Lato',
													],
													'children' => [
													],
												],
												1 => [
													'type' => 'text',
													'data' => [
														'value' => [
															'content' => 'Here are the details for your new Boom workspace, along with some tips to get started.',
														],
													],
													'attributes' => [
														'align' => 'center',
														'background-color' => '#414141',
														'color' => '#878792',
														'font-weight' => '400',
														'border-radius' => '3px',
														'padding' => '0px 0px 0px 0px',
														'inner-padding' => '10px 25px 10px 25px',
														'line-height' => '1.75',
														'target' => '_blank',
														'vertical-align' => 'middle',
														'border' => 'none',
														'text-align' => 'center',
														'href' => '#',
														'font-size' => '16px',
														'font-family' => 'Lato',
													],
													'children' => [
													],
												],
												2 => [
													'type' => 'advanced_image',
													'data' => [
														'value' => [
														],
													],
													'attributes' => [
														'align' => 'center',
														'height' => 'auto',
														'padding' => '32px 0px 0px 0px',
														'src' => $image_path . 'welcome-boom.png',
													],
													'children' => [
													],
												],
											],
										],
										2 => [
											'type' => 'advanced_spacer',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'height' => '20px',
												'padding' => '   ',
											],
											'children' => [
											],
										],
										3 => [
											'type' => 'advanced_section',
											'data' => [
												'value' => [
													'noWrap' => false,
												],
											],
											'attributes' => [
												'background-color' => '#ffffff',
												'padding' => '40px 40px 20px 40px',
												'background-repeat' => 'repeat',
												'background-size' => 'auto',
												'background-position' => 'top center',
												'border' => 'none',
												'direction' => 'ltr',
												'text-align' => 'center',
											],
											'children' => [
												0 => [
													'type' => 'advanced_column',
													'data' => [
														'value' => [
														],
													],
													'attributes' => [
														'background-color' => '#ffffff',
														'padding' => '0px 0px 0px 0px',
														'border' => 'none',
														'vertical-align' => 'top',
													],
													'children' => [
														0 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => 'Tips for Getting Started',
																],
															],
															'attributes' => [
																'padding' => '10px 0px 10px 0px',
																'align' => 'center',
																'font-family' => 'Arial',
																'font-size' => '26px',
																'font-weight' => '700',
																'line-height' => '1',
																'letter-spacing' => 'normal',
																'color' => '#000000',
															],
															'children' => [
															],
														],
													],
												],
											],
										],
										4 => [
											'type' => 'advanced_section',
											'data' => [
												'value' => [
													'noWrap' => false,
												],
											],
											'attributes' => [
												'background-color' => '#ffffff',
												'padding' => '0px 20px 40px 20px',
												'background-repeat' => 'repeat',
												'background-size' => 'auto',
												'background-position' => 'top center',
												'border' => '',
												'direction' => 'ltr',
												'text-align' => 'center',
											],
											'children' => [
												0 => [
													'type' => 'advanced_column',
													'attributes' => [
														'width' => '20%',
														'padding' => '0px 0px 0px 0px',
														'vertical-align' => 'middle',
													],
													'data' => [
														'value' => [
														],
													],
													'children' => [
														0 => [
															'type' => 'advanced_image',
															'data' => [
																'value' => [
																],
															],
															'attributes' => [
																'align' => 'center',
																'height' => 'auto',
																'padding' => '0px 0px 0px 0px',
																'src' => $image_path . 'invite.png',
																'width' => '70px',
															],
															'children' => [
															],
														],
													],
												],
												1 => [
													'type' => 'advanced_column',
													'attributes' => [
														'width' => '80%',
														'padding' => '10px 0px 0px 0px',
														'vertical-align' => 'middle',
													],
													'data' => [
														'value' => [
														],
													],
													'children' => [
														0 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => 'Invite teammates',
																],
															],
															'attributes' => [
																'padding' => '0px 25px 5px 25px',
																'align' => 'left',
																'font-family' => 'Lato',
																'font-size' => '18px',
																'font-weight' => '700',
																'line-height' => '1.2',
																'letter-spacing' => 'normal',
																'color' => '#000000',
															],
															'children' => [
															],
														],
														1 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => 'Boom is made for teams. <a href="#" target="_blank" style="text-decoration: underline;"><font color="#0064ff">Invite people</font></a> to work and communicate effortlessly.',
																],
															],
															'attributes' => [
																'padding' => '0px 25px 10px 25px',
																'align' => 'left',
																'font-family' => 'Arial',
																'font-size' => '16px',
																'font-weight' => '400',
																'line-height' => '1.6',
																'letter-spacing' => 'normal',
																'color' => '#878792',
															],
															'children' => [
															],
														],
													],
												],
											],
										],
										5 => [
											'type' => 'advanced_divider',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'align' => 'center',
												'border-width' => '1px',
												'border-style' => 'solid',
												'border-color' => '#C9CCCF',
												'padding' => '1px 40px 1px 40px',
												'container-background-color' => '#ffffff',
											],
											'children' => [
											],
										],
										6 => [
											'type' => 'advanced_divider',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'align' => 'center',
												'border-width' => '1px',
												'border-style' => 'solid',
												'border-color' => 'EBEBEB',
												'padding' => '0px 40px 0px 40px',
												'container-background-color' => '#fff',
											],
											'children' => [
											],
										],
										7 => [
											'type' => 'advanced_section',
											'data' => [
												'value' => [
													'noWrap' => false,
												],
											],
											'attributes' => [
												'background-color' => '#ffffff',
												'padding' => '40px 20px 40px 20px',
												'background-repeat' => 'repeat',
												'background-size' => 'auto',
												'background-position' => 'top center',
												'border' => '',
												'direction' => 'ltr',
												'text-align' => 'center',
											],
											'children' => [
												0 => [
													'type' => 'advanced_column',
													'attributes' => [
														'width' => '20%',
														'padding' => '0px 0px 0px 0px',
														'vertical-align' => 'middle',
													],
													'data' => [
														'value' => [
														],
													],
													'children' => [
														0 => [
															'type' => 'advanced_image',
															'data' => [
																'value' => [
																],
															],
															'attributes' => [
																'align' => 'center',
																'height' => 'auto',
																'padding' => '0px 0px 0px 0px',
																'src' => $image_path . 'corona.png',
																'width' => '70px',
															],
															'children' => [
															],
														],
													],
												],
												1 => [
													'type' => 'advanced_column',
													'attributes' => [
														'width' => '80%',
														'padding' => '10px 0px 0px 0px',
														'vertical-align' => 'middle',
													],
													'data' => [
														'value' => [
														],
													],
													'children' => [
														0 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => 'Create Channels',
																],
															],
															'attributes' => [
																'padding' => '0px 25px 5px 25px',
																'align' => 'left',
																'font-family' => 'Lato',
																'font-size' => '18px',
																'font-weight' => '700',
																'line-height' => '1.2',
																'letter-spacing' => 'normal',
																'color' => '#000000',
															],
															'children' => [
															],
														],
														1 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => '<a href="#" target="_blank" style="color: inherit; text-decoration: underline;"><font color="#0064ff">Keep work in channels</font>.</a> space for everything related to the project or team.',
																],
															],
															'attributes' => [
																'padding' => '0px 25px 10px 25px',
																'align' => 'left',
																'font-family' => 'Arial',
																'font-size' => '16px',
																'font-weight' => '400',
																'line-height' => '1.6',
																'letter-spacing' => 'normal',
																'color' => '#878792',
															],
															'children' => [
															],
														],
													],
												],
											],
										],
										8 => [
											'type' => 'advanced_divider',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'align' => 'center',
												'border-width' => '1px',
												'border-style' => 'solid',
												'border-color' => '#EBEBEB',
												'padding' => '1px 40px 1px 40px',
												'container-background-color' => '#ffffff',
											],
											'children' => [
											],
										],
										9 => [
											'type' => 'advanced_divider',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'align' => 'center',
												'border-width' => '1px',
												'border-style' => 'solid',
												'border-color' => 'EBEBEB',
												'padding' => '1px 40px 0px 40px',
												'container-background-color' => '#fff',
											],
											'children' => [
											],
										],
										10 => [
											'type' => 'advanced_section',
											'data' => [
												'value' => [
													'noWrap' => false,
												],
											],
											'attributes' => [
												'background-color' => '#ffffff',
												'padding' => '40px 20px 50px 20px',
												'background-repeat' => 'repeat',
												'background-size' => 'auto',
												'background-position' => 'top center',
												'border' => '',
												'direction' => 'ltr',
												'text-align' => 'center',
											],
											'children' => [
												0 => [
													'type' => 'advanced_column',
													'attributes' => [
														'width' => '20%',
														'padding' => '0px 0px 0px 0px',
														'vertical-align' => 'middle',
													],
													'data' => [
														'value' => [
														],
													],
													'children' => [
														0 => [
															'type' => 'advanced_image',
															'data' => [
																'value' => [
																],
															],
															'attributes' => [
																'align' => 'center',
																'height' => 'auto',
																'padding' => '0px 0px 0px 0px',
																'src' => $image_path . 'download.png',
																'width' => '70px',
															],
															'children' => [
															],
														],
													],
												],
												1 => [
													'type' => 'advanced_column',
													'attributes' => [
														'width' => '80%',
														'padding' => '10px 0px 0px 0px',
														'vertical-align' => 'middle',
													],
													'data' => [
														'value' => [
														],
													],
													'children' => [
														0 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => 'Download Boom',
																],
															],
															'attributes' => [
																'padding' => '0px 25px 5px 25px',
																'align' => 'left',
																'font-family' => 'Lato',
																'font-size' => '18px',
																'font-weight' => '700',
																'line-height' => '1.2',
																'letter-spacing' => 'normal',
																'color' => '#000000',
															],
															'children' => [
															],
														],
														1 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => 'For the best experience with Boom, <a href="#" target="_blank" style="text-decoration: underline;"><font color="#0064ff">download our apps</font></a> for desktops.',
																],
															],
															'attributes' => [
																'padding' => '0px 25px 10px 25px',
																'align' => 'left',
																'font-family' => 'Arial',
																'font-size' => '16px',
																'font-weight' => '400',
																'line-height' => '1.7',
																'letter-spacing' => 'normal',
																'color' => '#878792',
															],
															'children' => [
															],
														],
													],
												],
											],
										],
										11 => [
											'type' => 'advanced_section',
											'data' => [
												'value' => [
													'noWrap' => false,
												],
											],
											'attributes' => [
												'background-color' => '#ffffff',
												'padding' => '0px 0px 20px 0px',
												'background-repeat' => 'repeat',
												'background-size' => 'auto',
												'background-position' => 'top center',
												'border' => 'none',
												'direction' => 'ltr',
												'text-align' => 'center',
											],
											'children' => [
												0 => [
													'type' => 'advanced_column',
													'attributes' => [
														'width' => [
															0 => '25%',
															1 => '25%',
															2 => '25%',
															3 => '25%',
														],
														'padding' => '0px 0px 0px 0px',
													],
													'data' => [
														'value' => [
														],
													],
													'children' => [
														0 => [
															'type' => 'advanced_button',
															'data' => [
																'value' => [
																	'content' => 'See More Tips',
																],
															],
															'attributes' => [
																'align' => 'center',
																'font-family' => 'Lato',
																'background-color' => '#0064FF',
																'color' => '#ffffff',
																'font-weight' => '600',
																'font-style' => 'normal',
																'border-radius' => '100px',
																'padding' => '0px 0px 40px 0px',
																'inner-padding' => '17px 30px 17px 30px',
																'font-size' => '15px',
																'line-height' => '1.2',
																'target' => '_blank',
																'vertical-align' => 'middle',
																'border' => 'none',
																'text-align' => 'center',
																'letter-spacing' => 'normal',
																'href' => '#',
															],
															'children' => [
															],
														],
														1 => [
															'type' => 'advanced_text',
															'data' => [
																'value' => [
																	'content' => 'Have questions or need help? Drop us a note at <a href="#" target="_blank" style="text-decoration: underline;"><font color="#0064ff">boom@gmail.com.</font></a> We’re glad you’re here!',
																],
															],
															'attributes' => [
																'padding' => '0px 30px 10px 30px',
																'align' => 'center',
																'font-family' => 'Arial',
																'font-size' => '16px',
																'font-weight' => '400',
																'line-height' => '1.7',
																'letter-spacing' => 'normal',
																'color' => '#878792',
															],
															'children' => [
															],
														],
													],
												],
											],
										],
										12 => [
											'type' => 'advanced_social',
											'data' => [
												'value' => [
													'elements' => [
														0 => [
															'href' => '#',
															'target' => '_blank',
															'src' => $image_path . $pinterest,
															'content' => '',
														],
														1 => [
															'href' => '#',
															'target' => '_blank',
															'src' => $image_path . $facebook,
															'content' => '',
														],
														2 => [
															'href' => '',
															'target' => '_blank',
															'src' => $image_path . $instagram,
															'content' => '',
														],
														3 => [
															'href' => '',
															'target' => '_blank',
															'src' => $image_path . $twitter,
															'content' => '',
														],
													],
												],
											],
											'attributes' => [
												'align' => 'center',
												'color' => '#333333',
												'mode' => 'horizontal',
												'font-size' => '13px',
												'font-weight' => 'normal',
												'border-radius' => '3px',
												'padding' => '36px 25px 36px 25px',
												'inner-padding' => '4px 5px 4px 5px',
												'line-height' => '22px',
												'text-padding' => '4px 4px 4px 0px',
												'icon-padding' => '0px',
												'icon-size' => '40px',
											],
											'children' => [
											],
										],
										13 => [
											'type' => 'advanced_divider',
											'data' => [
												'value' => [
												],
											],
											'attributes' => [
												'align' => 'center',
												'border-width' => '1px',
												'border-style' => 'solid',
												'border-color' => '#E2E3EC',
												'padding' => '0px 0px 0px 0px',
											],
											'children' => [
											],
										],
										14 => [
											'type' => 'advanced_text',
											'data' => [
												'value' => [
													'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp; |&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
												],
											],
											'attributes' => [
												'padding' => '36px 25px 12px 25px',
												'align' => 'center',
												'color' => 'rgba(135, 135, 146, 1)',
												'line-height' => '1.47',
												'font-size' => '15px',
												'font-family' => 'Lato',
												'font-weight' => '400',
											],
											'children' => [
											],
										],
										15 => [
											'type' => 'advanced_text',
											'data' => [
												'value' => [
                          'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
												],
											],
											'attributes' => [
												'padding' => '10px 35px 10px 35px',
												'align' => 'center',
												'font-family' => 'Lato',
												'font-size' => '14px',
												'font-weight' => '400',
												'line-height' => '1.7',
												'letter-spacing' => 'normal',
												'color' => 'rgba(135, 135, 146, 1)',
											],
											'children' => [
											],
										],
									],
								],
							],
						],
					],
					'html_content'    => '',
					'thumbnail_image' => $image_path . '/thumbnails/welcome-email.png',
				),
				array(
					'id'              => 7,
					'is_pro'          => false,
                    'emailCategories' => ['Announcement'],
                    'industry'        => ['E-commerce & Retail'],
					'title'           => 'Giveaway!',
					'json_content'    => array (
            'subject' => 'Welcome to Mail Mint email marketing and automation',
            'subTitle' => 'Nice to meet you!',
            'content' => 
            array (
              'type' => 'page',
              'data' => 
              array (
                'value' => 
                array (
                  'breakpoint' => '480px',
                  'headAttributes' => '',
                  'font-size' => '14px',
                  'font-weight' => '400',
                  'line-height' => '1.7',
                  'headStyles' => 
                  array (
                  ),
                  'fonts' => 
                  array (
                  ),
                  'responsive' => true,
                  'font-family' => 'Arial',
                  'text-color' => '#000000',
                ),
              ),
              'attributes' => 
              array (
                'background-color' => '#efeeea',
                'width' => '600px',
              ),
              'children' => 
              array (
                0 => 
                array (
                  'type' => 'advanced_image',
                  'data' => 
                  array (
                    'value' => 
                    array (
                    ),
                  ),
                  'attributes' => 
                  array (
                    'align' => 'center',
                    'height' => 'auto',
                    'padding' => '42px 0px 0px 0px',
                    'src' => $image_path . 'logo-with-color.png',
                    'container-background-color' => '#F4F5FB',
                    'width' => '100%',
                  ),
                  'children' => 
                  array (
                  ),
                ),
                1 => 
                array (
                  'type' => 'advanced_wrapper',
                  'data' => 
                  array (
                    'value' => 
                    array (
                    ),
                  ),
                  'attributes' => 
                  array (
                    'background-color' => '#F4F5FB',
                    'padding' => '30px 24px 30px 24px',
                    'border' => 'none',
                    'direction' => 'ltr',
                    'text-align' => 'center',
                  ),
                  'children' => 
                  array (
                    0 => 
                    array (
                      'type' => 'advanced_hero',
                      'data' => 
                      array (
                        'value' => 
                        array (
                        ),
                      ),
                      'attributes' => 
                      array (
                        'background-color' => '#ffffff',
                        'background-position' => 'center center',
                        'mode' => 'fixed-height',
                        'padding' => '60px 0px 0px 0px',
                        'vertical-align' => 'top',
                        'background-url' => $image_path . 'giveaway-bg.jpg',
                        'height' => '900px',
                      ),
                      'children' => 
                      array (
                        0 => 
                        array (
                          'type' => 'advanced_text',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => '<div style="text-align: center;"><span style="word-spacing: normal;">Giveaway!</span></div>',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '10px 0px 10px 0px',
                            'align' => 'center',
                            'font-family' => 'Arial',
                            'font-size' => '50px',
                            'line-height' => '53px',
                            'color' => '#182DAA',
                            'font-weight' => 'bold',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        1 => 
                        array (
                          'type' => 'advanced_text',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => 'Exciting giveaway alert! ',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '15px 0px 15px 0px',
                            'align' => 'center',
                            'font-family' => 'Arial',
                            'font-size' => '15px',
                            'line-height' => '1',
                            'font-weight' => '600',
                            'color' => '#E65D2E',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          'type' => 'advanced_text',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => 'Get ready for a chance to win our exclusive products. Stay tuned for our email with all the details.',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '10px 25px 10px 25px',
                            'align' => 'center',
                            'font-family' => 'Arial',
                            'font-size' => '16px',
                            'line-height' => '26px',
                            'font-weight' => '500',
                            'color' => '#878792',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          'type' => 'advanced_text',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => 'Don\'t Miss Out!',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '15px 0px 15px 0px',
                            'align' => 'center',
                            'font-family' => 'Arial',
                            'font-size' => '22px',
                            'line-height' => '26px',
                            'font-weight' => '600',
                            'color' => '#E65D2E',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        4 => 
                        array (
                          'type' => 'advanced_button',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => 'Enter to win',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'font-family' => 'Arial',
                            'background-color' => '#182DAA',
                            'color' => '#ffffff',
                            'font-weight' => '600',
                            'font-style' => 'normal',
                            'border-radius' => '20px',
                            'padding' => '20px 25px 10px 25px',
                            'inner-padding' => '17px 30px 17px 30px',
                            'font-size' => '15px',
                            'line-height' => '1.2',
                            'target' => '_blank',
                            'vertical-align' => 'middle',
                            'border' => 'none',
                            'text-align' => 'center',
                            'letter-spacing' => 'normal',
                            'href' => '#',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                2 => 
                array (
                  'type' => 'advanced_social',
                  'data' => 
                  array (
                    'value' => 
                    array (
                      'elements' => 
                      array (
                        0 => 
                        array (
                          'href' => '#',
                          'target' => '_blank',
                          'src' => $image_path . 'pinterest.png',
                          'content' => '',
                        ),
                        1 => 
                        array (
                          'href' => '#',
                          'target' => '_blank',
                          'src' => $image_path . 'facebook.png',
                          'content' => '',
                        ),
                        2 => 
                        array (
                          'href' => '#',
                          'target' => '_blank',
                          'src' => $image_path . 'instagram.png',
                          'content' => '',
                        ),
                        3 => 
                        array (
                          'href' => '#',
                          'target' => '_blank',
                          'src' => $image_path . 'twitter.png',
                          'content' => '',
                        ),
                      ),
                    ),
                  ),
                  'attributes' => 
                  array (
                    'align' => 'center',
                    'color' => '#333333',
                    'mode' => 'horizontal',
                    'font-size' => '13px',
                    'font-weight' => 'normal',
                    'font-style' => 'normal',
                    'font-family' => 'Arial',
                    'border-radius' => '3px',
                    'padding' => '0px 20px 0px 0px',
                    'inner-padding' => '0px 20px 0px 0px',
                    'line-height' => '1.6',
                    'text-padding' => '4px 4px 4px 0px',
                    'icon-padding' => '0px',
                    'icon-size' => '40px',
                    'container-background-color' => '#F4F5FB',
                  ),
                  'children' => 
                  array (
                  ),
                ),
                3 => 
                array (
                  'type' => 'advanced_divider',
                  'data' => 
                  array (
                    'value' => 
                    array (
                    ),
                  ),
                  'attributes' => 
                  array (
                    'align' => 'center',
                    'border-width' => '1px',
                    'border-style' => 'solid',
                    'border-color' => '#E2E3EC',
                    'padding' => '30px 24px 30px 24px',
                    'container-background-color' => '#F4F5FB',
                  ),
                  'children' => 
                  array (
                  ),
                ),
                4 => 
                array (
                  'type' => 'advanced_text',
                  'data' => 
                  array (
                    'value' => 
                    array (
                      'content' => 'No longer want to be Mail Mint friends?',
                    ),
                  ),
                  'attributes' => 
                  array (
                    'padding' => '0px 0px 0px 0px',
                    'align' => 'center',
                    'container-background-color' => '#F4F5FB',
                    'font-family' => 'Arial',
                    'font-size' => '15px',
                    'line-height' => '22px',
                    'color' => '#878792',
                  ),
                  'children' => 
                  array (
                  ),
                ),
                5 => 
                array (
                  'type' => 'advanced_text',
                  'data' => 
                  array (
                    'value' => 
                    array (
                      'content' => '<font color="#878792"><a href="[object Object]" tabindex="-1"><font color="#878792">Email Preference</font></a> .&nbsp;<a href="[object Object]" tabindex="-1"><font color="#878792">Unsubscribe</font></a></font>',
                    ),
                  ),
                  'attributes' => 
                  array (
                    'padding' => '10px 25px 10px 25px',
                    'align' => 'center',
                    'container-background-color' => '#F4F5FB',
                    'font-family' => 'Arial',
                    'font-size' => '15px',
                    'line-height' => '22px',
                    'color' => '#878792',
                  ),
                  'children' => 
                  array (
                  ),
                ),
                6 => 
                array (
                  'type' => 'advanced_text',
                  'data' => 
                  array (
                    'value' => 
                    array (
                      'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                    ),
                  ),
                  'attributes' => 
                  array (
                    'padding' => '10px 5px 20px 5px',
                    'align' => 'center',
                    'container-background-color' => '#F4F5FB',
                    'font-family' => 'Arial',
                    'font-size' => '15px',
                    'line-height' => '22px',
                    'color' => '#878792',
                  ),
                  'children' => 
                  array (
                  ),
                ),
              ),
            ),
          ),
					'html_content'    => '',
					'thumbnail_image' => $image_path . '/thumbnails/giveaway.jpg',
				),
				array(
					'id'              => 8,
					'is_pro'          => false,
                    'emailCategories' => ['Deals & Offers'],
                    'industry'        => ['Others'],
					'title'           => 'Black Friday Deal',
					'json_content'    => [
                        'subject' => 'Welcome to MINT CRM email',
                        'subTitle' => 'Nice to meet you!',
                        'content' => [
                            'type' => 'page',
                            'data' => [
                                'value' => [
                                    'breakpoint' => '480px',
                                    'headAttributes' => '',
                                    'font-size' => '14px',
                                    'line-height' => '1.7',
                                    'headStyles' => [
                                    ],
                                    'fonts' => [
                                    ],
                                    'responsive' => true,
                                    'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
                                    'text-color' => '#000000',
                                ],
                            ],
                            'attributes' => [
                                'background-color' => '#ececec',
                                'width' => '600px',
                                'css-class' => 'mjml-body',
                            ],
                            'children' => [
                                0 => [
                                    'type' => 'advanced_wrapper',
                                    'data' => [
                                        'value' => [
                                        ],
                                    ],
                                    'attributes' => [
                                        'background-color' => '#F5F6FB',
                                        'padding' => '24px 24px 40px 24px',
                                        'border' => 'none',
                                        'direction' => 'ltr',
                                        'text-align' => 'center',
                                    ],
                                    'children' => [
                                        0 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 24px 0px',
                                                'src' => $image_path .'50-your-logo.png',
                                                'width' => '100%',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        1 => [
                                            'type' => 'advanced_hero',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'background-position' => 'top center',
                                                'mode' => 'fluid-height',
                                                'padding' => '40px 0px 310px 0px',
                                                'vertical-align' => 'top',
                                                'background-url' => $image_path .'50-hero-bg.png',
                                                'background-width' => 'cover',
                                                'background-height' => 'cover',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_image',
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'height' => 'auto',
                                                        'padding' => '0px 0px 24px 0px',
                                                        'src' => $image_path .'black-friday-image.png',
                                                        'width' => '230px',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                                1 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => '<font color="#f8e71c">50% Off</font> Everything',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'padding' => '10px 0px 10px 0px',
                                                        'align' => 'center',
                                                        'color' => '#FFFFFF',
                                                        'font-size' => '44px',
                                                        'line-height' => '1.1',
                                                        'font-family' => 'Arial',
                                                        'font-weight' => '600',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                            ],
                                        ],
                                        2 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '24px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        3 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'padding' => '40px 20px 40px 20px',
                                                'background-repeat' => 'repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => [
                                                            0 => '25%',
                                                            1 => '25%',
                                                            2 => '25%',
                                                            3 => '25%',
                                                        ],
                                                        'padding' => '0px 0px 0px 0px',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Black Friday Special Deal Is Here!',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 24px 0px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '30px',
                                                                'font-weight' => '600',
                                                                'line-height' => '1.3',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#2B2D38',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'It\'s that time of year again - Black Friday! We\'re excited to announce our amazing deals that you won\'t want to miss out on.&nbsp;<div><br><div>Starting this Friday, November 26th, we\'re offering discounts of up to 50% off on select products. From electronics to clothing, there\'s something for everyone on your list. Plus, we\'re offering free shipping on all orders over $50!&nbsp;</div><div><br></div><div>&nbsp;Hurry, these deals won\'t last long. Don\'t wait until it\'s too late to get your holiday shopping done.</div></div>',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 35px 0px',
                                                                'align' => 'left',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '16px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.7',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#878792',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        2 => [
                                                            'type' => 'advanced_button',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'GET THE DEAL NOW',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'background-color' => '#2F3033',
                                                                'color' => '#ffffff',
                                                                'font-weight' => 'normal',
                                                                'font-style' => 'normal',
                                                                'border-radius' => '100px',
                                                                'padding' => '0px 0px 0px 0px',
                                                                'inner-padding' => '16px 30px 16px 30px',
                                                                'font-size' => '15px',
                                                                'line-height' => '1.2',
                                                                'target' => '_blank',
                                                                'vertical-align' => 'middle',
                                                                'border' => 'none',
                                                                'text-align' => 'center',
                                                                'letter-spacing' => 'normal',
                                                                'href' => '#',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        4 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        5 => [
                                            'type' => 'advanced_social',
                                            'data' => [
                                                'value' => [
                                                    'elements' => [
                                                        0 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $pinterest,
                                                            'content' => '',
                                                        ],
                                                        1 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $facebook,
                                                            'content' => '',
                                                        ],
                                                        2 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $instagram,
                                                            'content' => '',
                                                        ],
                                                        3 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $twitter,
                                                            'content' => '',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'color' => '#333333',
                                                'mode' => 'horizontal',
                                                'font-size' => '13px',
                                                'font-weight' => 'normal',
                                                'font-style' => 'normal',
                                                'font-family' => 'Arial',
                                                'border-radius' => '',
                                                'padding' => '0px 25px 0px 25px',
                                                'inner-padding' => '4px 5px 4px 5px',
                                                'line-height' => '1.6',
                                                'text-padding' => '4px 4px 4px 0px',
                                                'icon-padding' => '0px',
                                                'icon-size' => '40px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        6 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        7 => [
                                            'type' => 'advanced_divider',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'border-width' => '1px',
                                                'border-style' => 'solid',
                                                'border-color' => '#E2E3EC',
                                                'padding' => '0px 0px 0px 0px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        8 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp; |&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '30px 0px 24px 0px',
                                                'align' => 'center',
                                                'color' => '#878792',
                                                'line-height' => '1.6',
                                                'font-size' => '15px',
                                                'font-family' => 'Arial',
                                                'font-weight' => '400',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        9 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                  'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '0px 35px 0px 35px',
                                                'align' => 'center',
                                                'font-family' => 'Arial',
                                                'font-size' => '14px',
                                                'font-weight' => '400',
                                                'line-height' => '1.7',
                                                'letter-spacing' => 'normal',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
					'html_content'    => '',
					'thumbnail_image' => $image_path . '/thumbnails/black-friday.jpg',
				),
                array(
                    'id'              => 9,
                    'is_pro'          => true,
                    'emailCategories' => ['Selling Services'],
                    'industry'        => ['Health & Wellness'],
                    'title'           => 'Fitness Gym Membership',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/marketing-email.jpg',
                ),

                array(
                    'id'              => 10,
                    'is_pro'          => false,
                    'emailCategories' => ['Selling Products'],
                    'industry'        => ['E-commerce & Retail'],
                    'title'           => 'Online Store Last Minute Shopping',
                    'json_content'    => [
                        'subject' => 'Welcome to MINT CRM email',
                        'subTitle' => 'Nice to meet you!',
                        'content' => [
                            'type' => 'page',
                            'data' => [
                                'value' => [
                                    'breakpoint' => '480px',
                                    'headAttributes' => '',
                                    'font-size' => '14px',
                                    'line-height' => '1.7',
                                    'headStyles' => [
                                    ],
                                    'fonts' => [
                                    ],
                                    'responsive' => true,
                                    'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
                                    'text-color' => '#000000',
                                ],
                            ],
                            'attributes' => [
                                'background-color' => '#ececec',
                                'width' => '600px',
                                'css-class' => 'mjml-body',
                            ],
                            'children' => [
                                0 => [
                                    'type' => 'advanced_wrapper',
                                    'data' => [
                                        'value' => [
                                        ],
                                    ],
                                    'attributes' => [
                                        'background-color' => '#F5F6FB',
                                        'padding' => '24px 24px 40px 24px',
                                        'border' => 'none',
                                        'direction' => 'ltr',
                                        'text-align' => 'center',
                                    ],
                                    'children' => [
                                        0 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 20px 0px',
                                                'src' =>  $image_path .'left-logo.png',
                                                'width' => '100%',
                                                'href' => '#',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        1 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 0px 0px',
                                                'src' => $image_path .'last-minute-hero-image.png',
                                                'container-background-color' => '#DBD5D2',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        2 => [
                                            'type' => 'advanced_hero',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'background-position' => 'center center',
                                                'mode' => 'fluid-height',
                                                'padding' => '40px 30px 50px 30px',
                                                'vertical-align' => 'top',
                                                'background-url' => '',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => 'Last Minute Shopping Again?',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'padding' => '0px 0px 16px 0px',
                                                        'align' => 'center',
                                                        'color' => '#2B2D38',
                                                        'font-size' => '38px',
                                                        'line-height' => '1.22',
                                                        'font-weight' => '700',
                                                        'font-family' => 'Arial',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                                1 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => "No Worries! We have got you covered with our ladies' jeans, pants,\nand tops - the perfect gift for the holiday season that they'll surely\nlove!&nbsp;<div><br><div>&nbsp;Order by 10/16 to ensure delivery in time for the holidays.\n\f</div></div>",
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'background-color' => '#414141',
                                                        'color' => '#878792',
                                                        'font-weight' => '400',
                                                        'border-radius' => '3px',
                                                        'padding' => '0px 0px 0px 0px',
                                                        'inner-padding' => '10px 25px 10px 25px',
                                                        'line-height' => '1.62',
                                                        'target' => '_blank',
                                                        'vertical-align' => 'middle',
                                                        'border' => 'none',
                                                        'text-align' => 'center',
                                                        'href' => '#',
                                                        'font-size' => '16px',
                                                        'font-family' => 'Arial',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                                2 => [
                                                    'type' => 'advanced_button',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => 'SHOP NOW',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'font-family' => 'Arial',
                                                        'background-color' => 'rgba(87, 59, 255, 1)',
                                                        'color' => '#ffffff',
                                                        'font-weight' => 'normal',
                                                        'font-style' => 'normal',
                                                        'border-radius' => '100px',
                                                        'padding' => '30px 25px 0px 25px',
                                                        'inner-padding' => '17px 30px 17px 30px',
                                                        'font-size' => '16px',
                                                        'line-height' => '1.2',
                                                        'target' => '_blank',
                                                        'vertical-align' => 'middle',
                                                        'border' => 'none',
                                                        'text-align' => 'center',
                                                        'letter-spacing' => 'normal',
                                                        'href' => '',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                            ],
                                        ],
                                        3 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '24px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        4 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '',
                                                'padding' => '95px 20px 95px 20px',
                                                'background-repeat' => 'no-repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                                'background-url' => $image_path .'happy-women.png',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => [
                                                            0 => '25%',
                                                            1 => '25%',
                                                            2 => '25%',
                                                            3 => '25%',
                                                        ],
                                                        'padding' => '0px 0px 0px 0px',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'No supply chain issues with a Dame gift card',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 0px 0px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '42px',
                                                                'font-weight' => '700',
                                                                'line-height' => '1.22',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#ffffff',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_button',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'GET YOUR GIFT CARD',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'background-color' => '#ffffff',
                                                                'color' => '#573BFF',
                                                                'font-weight' => '600',
                                                                'font-style' => 'normal',
                                                                'border-radius' => '100px',
                                                                'padding' => '40px 0px 0px 0px',
                                                                'inner-padding' => '17px 30px 17px 30px',
                                                                'font-size' => '16px',
                                                                'line-height' => '1.2',
                                                                'target' => '_blank',
                                                                'vertical-align' => 'middle',
                                                                'border' => 'none',
                                                                'text-align' => 'center',
                                                                'letter-spacing' => '1px',
                                                                'href' => '',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        5 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '24px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        6 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#2B2D38',
                                                'padding' => '30px 0px 30px 0px',
                                                'background-repeat' => 'repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => [
                                                            0 => '25%',
                                                            1 => '25%',
                                                            2 => '25%',
                                                            3 => '25%',
                                                        ],
                                                        'padding' => '10px 0px 10px 0px',
                                                        'vertical-align' => 'middle',
                                                        'border' => '',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_image',
                                                            'data' => [
                                                                'value' => [
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'height' => 'auto',
                                                                'padding' => '0px 0px 0px 0px',
                                                                'src' => $image_path .'fast.png',
                                                                'width' => '40px',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Express Shipping Available',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '10px 45px 10px 45px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '16px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.5',
                                                                'letter-spacing' => 'normal',
                                                                'color' => 'rgba(255, 255, 255, 0.6);',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                                1 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => [
                                                            0 => '25%',
                                                            1 => '25%',
                                                            2 => '25%',
                                                            3 => '25%',
                                                        ],
                                                        'padding' => '10px 0px 10px 0px',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_image',
                                                            'data' => [
                                                                'value' => [
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'height' => 'auto',
                                                                'padding' => '0px 0px 0px 0px',
                                                                'src' => $image_path .'customer-service.png',
                                                                'width' => '40px',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Questions? <a href="" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1"><font color="#ffffff" style="">Contact us</font></a> or visit our <a href="" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1"><font color="#ffffff" style="">FAQ page</font></a>',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '10px 45px 10px 45px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '16px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.5',
                                                                'letter-spacing' => 'normal',
                                                                'color' => 'rgba(255, 255, 255, 0.6);',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        7 => [
                                            'type' => 'advanced_social',
                                            'data' => [
                                                'value' => [
                                                    'elements' => [
                                                        0 => [
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $pinterest,
                                                            'content' => '',
                                                        ],
                                                        1 => [
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $facebook,
                                                            'content' => '',
                                                        ],
                                                        2 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $instagram,
                                                            'content' => '',
                                                        ],
                                                        3 => [
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src' => $image_path . $twitter,
                                                            'content' => '',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'color' => '#333333',
                                                'mode' => 'horizontal',
                                                'font-size' => '13px',
                                                'font-weight' => 'normal',
                                                'border-radius' => '3px',
                                                'padding' => '36px 25px 36px 25px',
                                                'inner-padding' => '4px 5px 4px 5px',
                                                'line-height' => '22px',
                                                'text-padding' => '4px 4px 4px 0px',
                                                'icon-padding' => '0px',
                                                'icon-size' => '40px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        8 => [
                                            'type' => 'advanced_divider',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'border-width' => '1px',
                                                'border-style' => 'solid',
                                                'border-color' => '#E2E3EC',
                                                'padding' => '0px 0px 0px 0px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        9 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp; |&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '36px 0px 12px 0px',
                                                'align' => 'center',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                                'line-height' => '1.6',
                                                'font-size' => '15px',
                                                'font-family' => 'Arial',
                                                'font-weight' => '400',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        10 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                  'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '10px 0px 10px 0px',
                                                'align' => 'center',
                                                'font-family' => 'Arial',
                                                'font-size' => '14px',
                                                'font-weight' => '400',
                                                'line-height' => '1.62',
                                                'letter-spacing' => 'normal',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/last-minute.jpg',
                ),

                array(
                    'id'              => 11,
                    'is_pro'          => true,
                    'emailCategories' => ['Follow Up'],
                    'industry'        => ['Fashion & Jewelry'],
                    'title'           => 'Post Purchase Follow Up',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/recent-purchase.jpg',
                ),

                array(
                    'id'              => 12,
                    'is_pro'          => true,
                    'emailCategories' => ['Selling Products'],
                    'industry'        => ['Food & Travel'],
                    'title'           => 'Juice Store',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/abandoned-cart-2.jpg',
                ),
                array(
                    'id'              => 13,
                    'is_pro'          => false,
                    'emailCategories' => ['Educate & Inform'],
                    'industry'        => ['Business & Finance'],
                    'title'           => 'Upgrade Notice',
                    'json_content'    => [
                        'subject' => 'Welcome to MINT CRM email',
                        'subTitle' => 'Nice to meet you!',
                        'content' => [
                            'type' => 'page',
                            'data' => [
                                'value' => [
                                    'breakpoint' => '480px',
                                    'headAttributes' => '',
                                    'font-size' => '14px',
                                    'line-height' => '1.7',
                                    'headStyles' => [
                                    ],
                                    'fonts' => [
                                    ],
                                    'responsive' => true,
                                    'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
                                    'text-color' => '#000000',
                                ],
                            ],
                            'attributes' => [
                                'background-color' => '#ececec',
                                'width' => '600px',
                                'css-class' => 'mjml-body',
                            ],
                            'children' => [
                                0 => [
                                    'type' => 'advanced_wrapper',
                                    'data' => [
                                        'value' => [
                                        ],
                                    ],
                                    'attributes' => [
                                        'background-color' => '#F5F6FB',
                                        'padding' => '24px 24px 40px 24px',
                                        'border' => 'none',
                                        'direction' => 'ltr',
                                        'text-align' => 'center',
                                    ],
                                    'children' => [
                                        0 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 24px 0px',
                                                'src' => $image_path . 'left-logo.png',
                                                'width' => '100%',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        1 => [
                                            'type' => 'advanced_hero',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#C6E5FC',
                                                'background-position' => 'top center',
                                                'mode' => 'fluid-height',
                                                'padding' => '44px 20px 95px 20px',
                                                'vertical-align' => 'top',
                                                'background-url' => '',
                                                'background-width' => 'cover',
                                                'background-height' => 'cover',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_image',
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'height' => 'auto',
                                                        'padding' => '0px 0px 0px 0px',
                                                        'src' => $image_path . 'attention-hero-image.png',
                                                        'width' => '457px',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                            ],
                                        ],
                                        2 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'padding' => '40px 20px 40px 20px',
                                                'background-repeat' => 'repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => [
                                                            0 => '25%',
                                                            1 => '25%',
                                                            2 => '25%',
                                                            3 => '25%',
                                                        ],
                                                        'padding' => '0px 0px 0px 0px',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Attention! This announcement is specifically for you.&nbsp;<br>',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 14px 0px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '32px',
                                                                'font-weight' => '700',
                                                                'line-height' => '1.22',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#0E1D3F',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Get ready to tune in and discover the exciting 10 major upgrades we\'re introducing with the latest release of Mail Mint.',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 25px 0px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '16px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.62',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#878792',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        2 => [
                                                            'type' => 'advanced_button',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'LET ME KNOW ABOUT THE UPGRADES',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'background-color' => '#573BFF',
                                                                'color' => '#ffffff',
                                                                'font-weight' => 'normal',
                                                                'font-style' => 'normal',
                                                                'border-radius' => '100px',
                                                                'padding' => '0px 0px 0px 0px',
                                                                'inner-padding' => '16px 30px 16px 30px',
                                                                'font-size' => '15px',
                                                                'line-height' => '1.5',
                                                                'target' => '_blank',
                                                                'vertical-align' => 'middle',
                                                                'border' => 'none',
                                                                'text-align' => 'center',
                                                                'letter-spacing' => '0.6px',
                                                                'href' => '#',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        3 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        4 => [
                                            'type' => 'advanced_social',
                                            'data' => [
                                                'value' => [
                                                    'elements' => array(
                                                        0 => array(
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $pinterest,
                                                            'content' => '',
                                                        ),
                                                        1 => array(
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $facebook,
                                                            'content' => '',
                                                        ),
                                                        2 => array(
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $instagram,
                                                            'content' => '',
                                                        ),
                                                        3 => array(
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $twitter,
                                                            'content' => '',
                                                        ),
                                                    ),
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'color' => '#333333',
                                                'mode' => 'horizontal',
                                                'font-size' => '13px',
                                                'font-weight' => 'normal',
                                                'font-style' => 'normal',
                                                'font-family' => 'Arial',
                                                'border-radius' => '',
                                                'padding' => '0px 25px 0px 25px',
                                                'inner-padding' => '4px 5px 4px 5px',
                                                'line-height' => '1.6',
                                                'text-padding' => '4px 4px 4px 0px',
                                                'icon-padding' => '0px',
                                                'icon-size' => '40px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        5 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        6 => [
                                            'type' => 'advanced_divider',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'border-width' => '1px',
                                                'border-style' => 'solid',
                                                'border-color' => '#E2E3EC',
                                                'padding' => '0px 0px 0px 0px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        7 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp; |&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '30px 0px 24px 0px',
                                                'align' => 'center',
                                                'color' => '#878792',
                                                'line-height' => '1.6',
                                                'font-size' => '15px',
                                                'font-family' => 'Arial',
                                                'font-weight' => '400',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        8 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                  'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '0px 35px 0px 35px',
                                                'align' => 'center',
                                                'font-family' => 'Arial',
                                                'font-size' => '14px',
                                                'font-weight' => '400',
                                                'line-height' => '1.7',
                                                'letter-spacing' => 'normal',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/upgrade-notice.jpg',
                ),
                array(
                    'id'              => 14,
                    'is_pro'          => false,
                    'emailCategories' => ['Educate & Inform'],
                    'industry'        => ['Business & Finance'],
                    'title'           => 'Subscription Cancellation Notice',
                    'json_content'    => [
                        'subject' => 'Welcome to MINT CRM email',
                        'subTitle' => 'Nice to meet you!',
                        'content' => [
                            'type' => 'page',
                            'data' => [
                                'value' => [
                                    'breakpoint' => '480px',
                                    'headAttributes' => '',
                                    'font-size' => '14px',
                                    'line-height' => '1.7',
                                    'headStyles' => [
                                    ],
                                    'fonts' => [
                                    ],
                                    'responsive' => true,
                                    'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
                                    'text-color' => '#000000',
                                ],
                            ],
                            'attributes' => [
                                'background-color' => '#ececec',
                                'width' => '600px',
                                'css-class' => 'mjml-body',
                            ],
                            'children' => [
                                0 => [
                                    'type' => 'advanced_wrapper',
                                    'data' => [
                                        'value' => [
                                        ],
                                    ],
                                    'attributes' => [
                                        'background-color' => '#F5F6FB',
                                        'padding' => '24px 24px 40px 24px',
                                        'border' => 'none',
                                        'direction' => 'ltr',
                                        'text-align' => 'center',
                                    ],
                                    'children' => [
                                        0 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 24px 0px',
                                                'src' => $image_path . 'left-logo.png',
                                                'width' => '100%',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        1 => [
                                            'type' => 'advanced_hero',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#C6E5FC',
                                                'background-position' => 'top center',
                                                'mode' => 'fluid-height',
                                                'padding' => '50px 20px 50px 20px',
                                                'vertical-align' => 'top',
                                                'background-url' => '',
                                                'background-width' => 'cover',
                                                'background-height' => 'cover',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_image',
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'align' => 'center',
                                                        'height' => 'auto',
                                                        'padding' => '0px 0px 25px 0px',
                                                        'src' => $image_path . 'sorry-hero-img.png',
                                                        'width' => '285px',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                                1 => [
                                                    'type' => 'text',
                                                    'data' => [
                                                        'value' => [
                                                            'content' => 'Sorry To See You Go!',
                                                        ],
                                                    ],
                                                    'attributes' => [
                                                        'padding' => '0px 0px 0px 0px',
                                                        'align' => 'center',
                                                        'color' => '#0E1D3F',
                                                        'font-size' => '32px',
                                                        'line-height' => '1.1',
                                                        'font-family' => 'Arial',
                                                        'font-weight' => '700',
                                                    ],
                                                    'children' => [
                                                    ],
                                                ],
                                            ],
                                        ],
                                        2 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '24px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        3 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'padding' => '40px 20px 40px 20px',
                                                'background-repeat' => 'repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => [
                                                            0 => '25%',
                                                            1 => '25%',
                                                            2 => '25%',
                                                            3 => '25%',
                                                        ],
                                                        'padding' => '0px 0px 0px 0px',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Hi Jhon Doe,',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 16px 0px',
                                                                'align' => 'left',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '24px',
                                                                'font-weight' => '700',
                                                                'line-height' => '1.3',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#0E1D3F',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'We wanted to inform you that your Mail Mint subscription has been canceled. We hope to see you return soon and take advantage of our wide selection of templates. If you ever wish to resume your subscription you can resubscribe it at any time.<br>',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 30px 0px',
                                                                'align' => 'left',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '16px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.7',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#878792',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        2 => [
                                                            'type' => 'advanced_button',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'RENEW MY SUBSCRIPTION',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'background-color' => '#573BFF',
                                                                'color' => '#ffffff',
                                                                'font-weight' => 'normal',
                                                                'font-style' => 'normal',
                                                                'border-radius' => '100px',
                                                                'padding' => '0px 0px 17px 0px',
                                                                'inner-padding' => '16px 30px 16px 30px',
                                                                'font-size' => '15px',
                                                                'line-height' => '1.5',
                                                                'target' => '_blank',
                                                                'vertical-align' => 'middle',
                                                                'border' => 'none',
                                                                'text-align' => 'center',
                                                                'letter-spacing' => 'normal',
                                                                'href' => '#',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        3 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Questions? <font color="#573bff">Contact Us</font> anytime<br>',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 0px 0px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '15px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.5',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#878792',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        4 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        5 => [
                                            'type' => 'advanced_social',
                                            'data' => [
                                                'value' => [
                                                    'elements' => array(
                                                        0 => array(
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $pinterest,
                                                            'content' => '',
                                                        ),
                                                        1 => array(
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $facebook,
                                                            'content' => '',
                                                        ),
                                                        2 => array(
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $instagram,
                                                            'content' => '',
                                                        ),
                                                        3 => array(
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $twitter,
                                                            'content' => '',
                                                        ),
                                                    ),
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'color' => '#333333',
                                                'mode' => 'horizontal',
                                                'font-size' => '13px',
                                                'font-weight' => 'normal',
                                                'font-style' => 'normal',
                                                'font-family' => 'Arial',
                                                'border-radius' => '',
                                                'padding' => '0px 25px 0px 25px',
                                                'inner-padding' => '4px 5px 4px 5px',
                                                'line-height' => '1.6',
                                                'text-padding' => '4px 4px 4px 0px',
                                                'icon-padding' => '0px',
                                                'icon-size' => '40px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        6 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        7 => [
                                            'type' => 'advanced_divider',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'border-width' => '1px',
                                                'border-style' => 'solid',
                                                'border-color' => '#E2E3EC',
                                                'padding' => '0px 0px 0px 0px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        8 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp; |&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '30px 0px 24px 0px',
                                                'align' => 'center',
                                                'color' => '#878792',
                                                'line-height' => '1.6',
                                                'font-size' => '15px',
                                                'font-family' => 'Arial',
                                                'font-weight' => '400',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        9 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                  'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '0px 35px 0px 35px',
                                                'align' => 'center',
                                                'font-family' => 'Arial',
                                                'font-size' => '14px',
                                                'font-weight' => '400',
                                                'line-height' => '1.7',
                                                'letter-spacing' => 'normal',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/subscription-cancellation-notice.jpg',
                ),
                array(
                    'id'              => 15,
                    'is_pro'          => false,
                    'emailCategories' => ['Deals & Offers'],
                    'industry'        => ['Others'],
                    'title'           => 'Limited Time Deal',
                    'json_content'    => [
                        'subject' => 'Welcome to MINT CRM email',
                        'subTitle' => 'Nice to meet you!',
                        'content' => [
                            'type' => 'page',
                            'data' => [
                                'value' => [
                                    'breakpoint' => '480px',
                                    'headAttributes' => '',
                                    'font-size' => '14px',
                                    'line-height' => '1.7',
                                    'headStyles' => [
                                    ],
                                    'fonts' => [
                                    ],
                                    'responsive' => true,
                                    'font-family' => 'lucida Grande,Verdana,Microsoft YaHei',
                                    'text-color' => '#000000',
                                ],
                            ],
                            'attributes' => [
                                'background-color' => '#ececec',
                                'width' => '600px',
                                'css-class' => 'mjml-body',
                            ],
                            'children' => [
                                0 => [
                                    'type' => 'advanced_wrapper',
                                    'data' => [
                                        'value' => [
                                        ],
                                    ],
                                    'attributes' => [
                                        'background-color' => '#F5F6FB',
                                        'padding' => '24px 24px 40px 24px',
                                        'border' => 'none',
                                        'direction' => 'ltr',
                                        'text-align' => 'center',
                                    ],
                                    'children' => [
                                        0 => [
                                            'type' => 'advanced_image',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'height' => 'auto',
                                                'padding' => '0px 0px 24px 0px',
                                                'src' => $image_path . 'left-logo.png',
                                                'width' => '100%',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        1 => [
                                            'type' => 'advanced_section',
                                            'data' => [
                                                'value' => [
                                                    'noWrap' => false,
                                                ],
                                            ],
                                            'attributes' => [
                                                'background-color' => '#ffffff',
                                                'padding' => '40px 20px 40px 20px',
                                                'background-repeat' => 'repeat',
                                                'background-size' => 'auto',
                                                'background-position' => 'top center',
                                                'border' => 'none',
                                                'direction' => 'ltr',
                                                'text-align' => 'center',
                                            ],
                                            'children' => [
                                                0 => [
                                                    'type' => 'advanced_column',
                                                    'attributes' => [
                                                        'width' => [
                                                            0 => '25%',
                                                            1 => '25%',
                                                            2 => '25%',
                                                            3 => '25%',
                                                        ],
                                                        'padding' => '0px 0px 0px 0px',
                                                    ],
                                                    'data' => [
                                                        'value' => [
                                                        ],
                                                    ],
                                                    'children' => [
                                                        0 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => '48 HOURS ONLY',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 14px 0px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '18px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.62',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#573BFF',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        1 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Limited Time Deal',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 30px 0px',
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '34px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.22',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#0E1D3F',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        2 => [
                                                            'type' => 'advanced_image',
                                                            'data' => [
                                                                'value' => [
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'height' => 'auto',
                                                                'padding' => '0px 0px 30px 0px',
                                                                'src' => $image_path . 'prime-hero-img.png',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        3 => [
                                                            'type' => 'advanced_text',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'Here\'s an exclusive limited-time deal that you don\'t want to miss!&nbsp;<div>For a limited period only, take advantage of our products at a huge 50% discount!&nbsp;</div><div>Seize the opportunity, this deal won\'t be there forever.</div>',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'padding' => '0px 0px 30px 0px',
                                                                'align' => 'left',
                                                                'font-family' => 'Arial',
                                                                'font-size' => '16px',
                                                                'font-weight' => '400',
                                                                'line-height' => '1.62',
                                                                'letter-spacing' => 'normal',
                                                                'color' => '#878792',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                        4 => [
                                                            'type' => 'advanced_button',
                                                            'data' => [
                                                                'value' => [
                                                                    'content' => 'CHECK OUT THE DEAL',
                                                                ],
                                                            ],
                                                            'attributes' => [
                                                                'align' => 'center',
                                                                'font-family' => 'Arial',
                                                                'background-color' => '#573BFF',
                                                                'color' => '#ffffff',
                                                                'font-weight' => 'normal',
                                                                'font-style' => 'normal',
                                                                'border-radius' => '100px',
                                                                'padding' => '0px 0px 0px 0px',
                                                                'inner-padding' => '16px 30px 16px 30px',
                                                                'font-size' => '15px',
                                                                'line-height' => '1.5',
                                                                'target' => '_blank',
                                                                'vertical-align' => 'middle',
                                                                'border' => 'none',
                                                                'text-align' => 'center',
                                                                'letter-spacing' => '0.6px',
                                                                'href' => '#',
                                                            ],
                                                            'children' => [
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        2 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        3 => [
                                            'type' => 'advanced_social',
                                            'data' => [
                                                'value' => [
                                                    'elements' => array(
                                                        0 => array(
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $pinterest,
                                                            'content' => '',
                                                        ),
                                                        1 => array(
                                                            'href' => '#',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $facebook,
                                                            'content' => '',
                                                        ),
                                                        2 => array(
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $instagram,
                                                            'content' => '',
                                                        ),
                                                        3 => array(
                                                            'href' => '',
                                                            'target' => '_blank',
                                                            'src'  => $image_path . $twitter,
                                                            'content' => '',
                                                        ),
                                                    ),
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'color' => '#333333',
                                                'mode' => 'horizontal',
                                                'font-size' => '13px',
                                                'font-weight' => 'normal',
                                                'font-style' => 'normal',
                                                'font-family' => 'Arial',
                                                'border-radius' => '',
                                                'padding' => '0px 25px 0px 25px',
                                                'inner-padding' => '4px 5px 4px 5px',
                                                'line-height' => '1.6',
                                                'text-padding' => '4px 4px 4px 0px',
                                                'icon-padding' => '0px',
                                                'icon-size' => '40px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        4 => [
                                            'type' => 'advanced_spacer',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'height' => '30px',
                                                'padding' => '   ',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        5 => [
                                            'type' => 'advanced_divider',
                                            'data' => [
                                                'value' => [
                                                ],
                                            ],
                                            'attributes' => [
                                                'align' => 'center',
                                                'border-width' => '1px',
                                                'border-style' => 'solid',
                                                'border-color' => '#E2E3EC',
                                                'padding' => '0px 0px 0px 0px',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        6 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                    'content' => 'No longer want to be Mail Mint friends?<br>&nbsp;<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Email Preference</a>&nbsp; |&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Unsubscribe</a><b><br></b>',
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '30px 0px 24px 0px',
                                                'align' => 'center',
                                                'color' => '#878792',
                                                'line-height' => '1.6',
                                                'font-size' => '15px',
                                                'font-family' => 'Arial',
                                                'font-weight' => '400',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                        7 => [
                                            'type' => 'advanced_text',
                                            'data' => [
                                                'value' => [
                                                  'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                                ],
                                            ],
                                            'attributes' => [
                                                'padding' => '0px 35px 0px 35px',
                                                'align' => 'center',
                                                'font-family' => 'Arial',
                                                'font-size' => '14px',
                                                'font-weight' => '400',
                                                'line-height' => '1.7',
                                                'letter-spacing' => 'normal',
                                                'color' => 'rgba(135, 135, 146, 1)',
                                            ],
                                            'children' => [
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/limited-time-deal.jpg',
                ),
                array(
                    'id'              => 16,
                    'is_pro'          => true,
                    'emailCategories' => ['Events'],
                    'industry'        => ['Education & Non Profit'],
                    'title'           => 'Event Registration',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/event-registration.jpg',
                ),
                array(
                    'id'              => 17,
                    'is_pro'          => true,
                    'emailCategories' => ['Selling Services'],
                    'industry'        => ['Food & Travel'],
                    'title'           => 'Hotel Booking',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/hotel-booking.jpg',
                ),
                array(
                    'id'              => 18,
                    'is_pro'          => true,
                    'emailCategories' => ['Abandoned Cart Recovery'],
                    'industry'        => ['E-commerce & Retail'],
                    'title'           => 'Abandoned Cart Recovery 1',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/reserve-cart.jpg',
                ),
                array(
                    'id'              => 19,
                    'is_pro'          => true,
                    'emailCategories' => ['Abandoned Cart Recovery'],
                    'industry'        => ['E-commerce & Retail'],
                    'title'           => 'Abandoned Cart Recovery 2',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/purchase-today.jpg',
                ),
                array(
                    'id'              => 20,
                    'is_pro'          => true,
                    'emailCategories' => ['Deals & Offers'],
                    'industry'        => ['Others'],
                    'title'           => 'Happy Halloween!',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/halloween-thumb.jpg',
                ),
                array(
                    'id'              => 21,
                    'is_pro'          => false,
                    'emailCategories' => ['Welcome'],
                    'industry'        => ['Food & Travel'],
                    'title'           => 'Restaurant Welcome Email',
                    'json_content'    => array (
                        'subject' => 'Welcome to Mail Mint email marketing and automation',
                        'subTitle' => 'Nice to meet you!',
                        'content' => 
                        array (
                          'type' => 'page',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'breakpoint' => '480px',
                              'headAttributes' => '',
                              'font-size' => '14px',
                              'font-weight' => '400',
                              'line-height' => '1.7',
                              'headStyles' => 
                              array (
                              ),
                              'fonts' => 
                              array (
                              ),
                              'responsive' => true,
                              'font-family' => 'Arial',
                              'text-color' => '#000000',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'background-color' => '#efeeea',
                            'width' => '600px',
                          ),
                          'children' => 
                          array (
                            0 => 
                            array (
                              'type' => 'advanced_wrapper',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'background-color' => '#F5F6FB',
                                'padding' => '17px 19px 17px 19px',
                                'border' => 'none',
                                'direction' => 'ltr',
                                'text-align' => 'center',
                              ),
                              'children' => 
                              array (
                                0 => 
                                array (
                                  'type' => 'advanced_image',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'height' => 'auto',
                                    'padding' => '0px 0px 27px 0px',
                                    "src" => $image_path . 'your-logo.png',
                                    'width' => '100%',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                1 => 
                                array (
                                  'type' => 'advanced_image',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'height' => 'auto',
                                    'padding' => '0px 0px 0px 0px',
                                    "src" => $image_path . 'restaurant-welcome-email/hero-image.png',
                                    'width' => '',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                2 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'Welcome to Harmonious Palate
                      ',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '40px 0px 10px 0px',
                                    'align' => 'center',
                                    'container-background-color' => '#0B0F12',
                                    'color' => '#FFFFFF',
                                    'font-size' => '32px',
                                    'line-height' => '1.12',
                                    'font-weight' => '700',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                3 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'In our Asian Fusion Kitchen, tradition meets innovation in an exquisite dance of flavors. We are thrilled to have you as our esteemed guest!
                      ',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '0px 35px 40px 35px',
                                    'align' => 'center',
                                    'font-size' => '16px',
                                    'line-height' => '1.5',
                                    'font-weight' => '400',
                                    'color' => '#ABABAB',
                                    'container-background-color' => '#0B0F12',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                4 => 
                                array (
                                  'type' => 'advanced_spacer',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'height' => '20px',
                                    'padding' => '0px 0px 0px 0px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                5 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '',
                                    'padding' => '0px 0px 0px 0px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => '2px solid #0B0F12',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => 
                                        array (
                                          0 => '25%',
                                          1 => '25%',
                                          2 => '25%',
                                          3 => '25%',
                                        ),
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '3 Things To Know About Us',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '22px 0px 22px 0px',
                                            'align' => 'center',
                                            'color' => '#FFFFFF',
                                            'container-background-color' => '#E8563C',
                                            'font-size' => '26px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_divider',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'border-width' => '2px',
                                            'border-style' => 'solid',
                                            'border-color' => '#0B0F12',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '40px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        3 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '1. Our Mission',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 0px 12px 0px',
                                            'align' => 'center',
                                            'color' => '#000000',
                                            'container-background-color' => '',
                                            'font-size' => '24px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        4 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Our foremost mission is to take you to the heart of Asia with each exquisite dish. We aim to create an ambiance of warmth, sophistication, and unity, where you can explore the diverse flavors of Asia within our walls.',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 25px 0px 25px',
                                            'align' => 'center',
                                            'color' => '#737373',
                                            'container-background-color' => '',
                                            'font-size' => '16px',
                                            'line-height' => '1.5',
                                            'font-weight' => '400',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        5 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '40px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        6 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '0px 25px 0px 25px',
                                            "src" => $image_path . 'restaurant-welcome-email/our-mission.png', 
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        7 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '40px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        8 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '2. How Our Menu is Designed',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 0px 12px 0px',
                                            'align' => 'center',
                                            'color' => '#000000',
                                            'container-background-color' => '',
                                            'font-size' => '24px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        9 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Our menu is a harmonious fusion of classic Asian recipes and contemporary twists, meticulously crafted by our skilled culinary team. From fresh, locally sourced produce to rare, imported spices, we want to give you the best.',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 25px 0px 25px',
                                            'align' => 'center',
                                            'color' => '#737373',
                                            'container-background-color' => '',
                                            'font-size' => '16px',
                                            'line-height' => '1.5',
                                            'font-weight' => '400',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        10 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '40px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        11 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '0px 25px 0px 25px',
                                            "src" => $image_path . 'restaurant-welcome-email/menu.png',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        12 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '40px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        13 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '3.&nbsp;What We Want to Be',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 0px 12px 0px',
                                            'align' => 'center',
                                            'color' => '#000000',
                                            'container-background-color' => '',
                                            'font-size' => '24px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        14 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'We want to be your go-to destination for moments of celebration, tranquility, and joy. We strive to be a part of your cherished memories, whether it\'s a romantic dinner for two or a lively family gathering.
                      ',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 25px 0px 25px',
                                            'align' => 'center',
                                            'color' => '#737373',
                                            'container-background-color' => '',
                                            'font-size' => '16px',
                                            'line-height' => '1.5',
                                            'font-weight' => '400',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        15 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '40px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                6 => 
                                array (
                                  'type' => 'advanced_spacer',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'height' => '20px',
                                    'padding' => '0px 0px 0px 0px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                7 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#0B0F12',
                                    'padding' => '40px 0px 40px 0px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => '2px solid #E8563C',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => 
                                        array (
                                          0 => '25%',
                                          1 => '25%',
                                          2 => '25%',
                                          3 => '25%',
                                        ),
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'What Others Say About Us
                      ',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 25px 50px 25px',
                                            'align' => 'center',
                                            'color' => '#FFFFFF',
                                            'font-size' => '24px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '0px 0px 0px 0px',
                                            "src" => $image_path . 'restaurant-welcome-email/stars.png',
                                            'width' => '150%',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Harmonious Palate is truly a culinary gem! I recently dined there with my family, and we were blown away by the exquisite flavors and warm hospitality. The menu is a work of art, and every dish we tried was amazing!',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '22px 20px 0px 20px',
                                            'align' => 'center',
                                            'color' => '#ABABAB',
                                            'font-size' => '16px',
                                            'line-height' => '1.56',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        3 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'John Doe',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '30px 0px 0px 0px',
                                            'align' => 'center',
                                            'color' => '#FFFFFF',
                                            'font-size' => '16px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                8 => 
                                array (
                                  'type' => 'advanced_spacer',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'height' => '40px',
                                    'padding' => '0px 0px 0px 0px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                9 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'A Few Client Favorites
                      ',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '10px 25px 10px 25px',
                                    'align' => 'center',
                                    'font-weight' => '700',
                                    'font-size' => '24px',
                                    'line-height' => '1',
                                    'color' => '#0B0F12',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                10 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '',
                                    'padding' => '30px 0px 0px 0px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => 'none',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '50%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '10px 0px 10px 0px',
                                            "src" => $image_path . 'restaurant-welcome-email/japanese-sushi.png',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '50%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Japanese Sushi',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '40px 0px 10px 25px',
                                            'align' => 'left',
                                            'font-size' => '18px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                            'color' => '#0B0F12',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Indulge in the exquisite artistry of our Japanese Sushi, a symphony of fresh, velvety fish and perfectly seasoned rice.',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 10px 10px 25px',
                                            'align' => 'left',
                                            'font-size' => '16px',
                                            'line-height' => '1.56',
                                            'font-weight' => '400',
                                            'color' => '#737373',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '<a href="#" target="_blank" style="color: inherit; text-decoration: underline;">Get a special discount</a>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 0px 10px 25px',
                                            'align' => 'left',
                                            'font-size' => '15px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                            'color' => '#E8563C',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                11 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '',
                                    'padding' => '30px 0px 0px 0px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => 'none',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '50%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '10px 0px 10px 0px',
                                            "src" => $image_path . 'restaurant-welcome-email/kun-pau-chicken.png', 
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '50%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Kung Pao Chicken',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '40px 0px 10px 25px',
                                            'align' => 'left',
                                            'font-size' => '18px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                            'color' => '#0B0F12',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Savor the fiery flavors of our Kung Pao Chicken, a tantalizing fusion of tender, succulent chicken, roasted peanuts, and vibrant, crisp vegetables.',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 10px 10px 25px',
                                            'align' => 'left',
                                            'font-size' => '16px',
                                            'line-height' => '1.56',
                                            'font-weight' => '400',
                                            'color' => '#737373',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '<a href="#" target="_blank" style="color: inherit; text-decoration: underline;">Get a special discount</a>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 0px 10px 25px',
                                            'align' => 'left',
                                            'font-size' => '15px',
                                            'line-height' => '1',
                                            'font-weight' => '700',
                                            'color' => '#E8563C',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                12 => 
                                array (
                                  'type' => 'advanced_spacer',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'height' => '40px',
                                    'padding' => '0px 0px 0px 0px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                13 => 
                                array (
                                  'type' => 'advanced_hero',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#0B0F12',
                                    'background-position' => 'center center',
                                    'mode' => 'fluid-height',
                                    'padding' => '40px 40px 0px 40px',
                                    'vertical-align' => 'top',
                                    'background-url' => '',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_text',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => 'Follow @harmonykitchen on Instagram',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'padding' => '17px 0px 17px 0px',
                                        'align' => 'center',
                                        'container-background-color' => '#E8563C',
                                        'font-size' => '18px',
                                        'line-height' => '0.8',
                                        'font-weight' => '700',
                                        'color' => '#FFFFFF',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                  ),
                                ),
                                14 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#0B0F12',
                                    'padding' => '0px 0px 40px 0px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => 'none',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => 
                                        array (
                                          0 => '25%',
                                          1 => '25%',
                                          2 => '25%',
                                          3 => '25%',
                                        ),
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '30px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_social',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'elements' => 
                                              array (
                                                0 => 
                                                array (
                                                  'href' => '#',
                                                  'target' => '_blank',
                                                  "src" => $image_path . 'restaurant-welcome-email/pinterest.png',
                                                  'content' => '',
                                                ),
                                                1 => 
                                                array (
                                                  'href' => '#',
                                                  'target' => '_blank',
                                                  "src" => $image_path . 'restaurant-welcome-email/facebook.png',
                                                  'content' => '',
                                                ),
                                                2 => 
                                                array (
                                                  'href' => '#',
                                                  'target' => '_blank',
                                                  "src" => $image_path . 'restaurant-welcome-email/instagram.png',
                                                  'content' => '',
                                                ),
                                                3 => 
                                                array (
                                                  'href' => '#',
                                                  'target' => '_blank',
                                                  "src" => $image_path . 'restaurant-welcome-email/twitter.png',
                                                  'content' => '',
                                                ),
                                              ),
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'color' => '#333333',
                                            'mode' => 'horizontal',
                                            'font-size' => '13px',
                                            'font-weight' => 'normal',
                                            'font-style' => 'normal',
                                            'font-family' => 'Arial',
                                            'border-radius' => '3px',
                                            'padding' => '0px 0px 0px 0px',
                                            'inner-padding' => '0px 20px 0px 0px',
                                            'line-height' => '1.6',
                                            'text-padding' => '4px 4px 4px 0px',
                                            'icon-padding' => '0px',
                                            'icon-size' => '40px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '30px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        3 => 
                                        array (
                                          'type' => 'advanced_divider',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'border-width' => '1px',
                                            'border-style' => 'solid',
                                            'border-color' => '#242729',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        4 => 
                                        array (
                                          'type' => 'advanced_spacer',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'height' => '30px',
                                            'padding' => '0px 0px 0px 0px',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        5 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'No longer want to be Mail Mint friends?',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 5px 10px 5px',
                                            'align' => 'center',
                                            'font-size' => '15px',
                                            'line-height' => '1.4',
                                            'font-weight' => '400',
                                            'color' => '#888888',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        6 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;">Email Preference</a> .&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;">Unsubscribe</a>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 5px 10px 5px',
                                            'align' => 'center',
                                            'font-size' => '15px',
                                            'line-height' => '1.4',
                                            'font-weight' => '400',
                                            'color' => '#888888',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        7 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '30px 5px 10px 5px',
                                            'align' => 'center',
                                            'font-size' => '15px',
                                            'line-height' => '1.4',
                                            'font-weight' => '400',
                                            'color' => '#888888',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/restaurent-welcome-email.jpg',
                ),
                array(
                    'id'              => 22,
                    'is_pro'          => true,
                    'emailCategories' => ['Events'],
                    'industry'        => ['Education & Non Profit', 'Business & Finance'],
                    'title'           => 'Event Invitation',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/event-invitation.jpg',
                ),
                array(
                    'id'              => 23,
                    'is_pro'          => true,
                    'emailCategories' => ['Deals & Offers'],
                    'industry'        => ['E-commerce & Retail', 'Others'],
                    'title'           => 'Christmas Exclusive Offer',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/christmas-exclusive-offer.jpg',
                ),
                array(
                    'id'              => 24,
                    'is_pro'          => true,
                    'emailCategories' => ['Follow Up'],
                    'industry'        => ['E-commerce & Retail'],
                    'title'           => 'Shipping Update',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/shipping-update.jpg',
                ),
                array(
                    'id'              => 25,
                    'is_pro'          => true,
                    'emailCategories' => ['Welcome'],
                    'industry'        => ['Health & Wellness'],
                    'title'           => 'Welcome To Gym Email',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/welcome-to-gym.jpg',
                ),
                array(
                    'id'              => 26,
                    'is_pro'          => true,
                    'emailCategories' => ['Deals & Offers'],
                    'industry'        => ['Business & Finance', 'E-commerce & Retail', 'Others'],
                    'title'           => 'Cyber Monday - Extended Sale',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/extend-sale.jpg',
                ),
                array(
                    'id'              => 27,
                    'is_pro'          => true,
                    'emailCategories' => ['Deals & Offers'],
                    'industry'        => ['Business & Finance', 'Others'],
                    'title'           => 'Anniversary Greetings',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/anniversary-greetings.jpg',
                ),
                array(
                    'id'              => 28,
                    'is_pro'          => false,
                    'emailCategories' => ['Educate & Inform'],
                    'industry'        => ['Education & Non Profit', 'Business & Finance', 'Other'],
                    'title'           => 'Newsletter Update',
                    'json_content'    => array (
                        'subTitle' => 'Nice to meet you!',
                        'content' => 
                        array (
                          'type' => 'page',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'breakpoint' => '480px',
                              'headAttributes' => '',
                              'font-size' => '14px',
                              'font-weight' => '400',
                              'line-height' => '1.7',
                              'headStyles' => 
                              array (
                              ),
                              'fonts' => 
                              array (
                              ),
                              'responsive' => true,
                              'font-family' => 'Arial',
                              'text-color' => '#000000',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'background-color' => '#efeeea',
                            'width' => '600px',
                          ),
                          'children' => 
                          array (
                            0 => 
                            array (
                              'type' => 'advanced_image',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'height' => 'auto',
                                'padding' => '20px 0px 20px 0px',
                                'src' => $image_path . 'your-logo.png',
                                'width' => '100%',
                                'container-background-color' => '#fff',
                                'alt' => 'Logo',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            1 => 
                            array (
                              'type' => 'advanced_wrapper',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'background-color' => '#0F0740',
                                'padding' => '40px 24px 40px 24px',
                                'border' => 'none',
                                'direction' => 'ltr',
                                'text-align' => 'center',
                              ),
                              'children' => 
                              array (
                                0 => 
                                array (
                                  'type' => 'advanced_hero',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#261B5C',
                                    'background-position' => 'center center',
                                    'mode' => 'fixed-height',
                                    'padding' => '0px 0px 0px 0px',
                                    'vertical-align' => 'top',
                                    'background-url' => '',
                                    'border-radius' => '10px',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'text',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => 'Mail Mint Monthly',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'padding' => '40px 0px 0px 0px',
                                        'align' => 'center',
                                        'color' => '#ffff',
                                        'font-size' => '30px',
                                        'line-height' => '1',
                                        'font-family' => 'Arial',
                                        'font-weight' => '500',
                                        'font-style' => 'normal',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'text',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => 'Greetings from Mail Mint!&nbsp;<div>&nbsp;Check out our latest newsletter for handpicked guides, articles, and product highlights. Elevate your team collaboration game with these valuable insights.  Happy reading! 📚✨</div>',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'align' => 'center',
                                        'background-color' => '#414141',
                                        'color' => '#fff',
                                        'font-weight' => 'normal',
                                        'border-radius' => '3px',
                                        'padding' => '16px 20px 40px 20px',
                                        'inner-padding' => '10px 25px 10px 25px',
                                        'line-height' => '1.55',
                                        'target' => '_blank',
                                        'vertical-align' => 'middle',
                                        'border' => 'none',
                                        'text-align' => 'center',
                                        'href' => '#',
                                        'font-size' => '18px',
                                        'font-style' => 'normal',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            2 => 
                            array (
                              'type' => 'advanced_wrapper',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'background-color' => '#ffffff',
                                'padding' => '0px 0px 0px 0px',
                                'border' => 'none',
                                'direction' => 'ltr',
                                'text-align' => 'center',
                              ),
                              'children' => 
                              array (
                                0 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#ffffff',
                                    'padding' => '44px 20px 0px 20px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => 'none',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '33%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '0px 0px 10px 0px',
                                            'src' => $image_path . 'newsletter-update/post-image-1.png',
                                            'border-radius' => '10px',
                                            'width' => '229px',
                                            'alt' => 'Complete Guide',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '67%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Complete Guide: Boost Your ROI Using Targeted Email Campaigns',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 0px 0px 26px',
                                            'align' => 'left',
                                            'color' => '#0B0F12',
                                            'font-weight' => '700',
                                            'font-size' => '18px',
                                            'line-height' => '1.44',
                                            'font-style' => 'normal',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Email marketing can often get<div>challenging. Sometimes, even if you&nbsp;<div>make great offers . .</div></div>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '14px 0px 0px 26px',
                                            'align' => 'left',
                                            'line-height' => '1.56',
                                            'font-size' => '16px',
                                            'font-weight' => '400',
                                            'font-style' => 'normal',
                                            'color' => '#737373',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_button',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Read More',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'left',
                                            'font-family' => 'Arial',
                                            'background-color' => '#573BFF',
                                            'color' => '#ffffff',
                                            'font-weight' => 'normal',
                                            'font-style' => 'normal',
                                            'border-radius' => '100px',
                                            'padding' => '20px 0px 0px 26px',
                                            'inner-padding' => '10px 25px 10px 25px',
                                            'font-size' => '13px',
                                            'line-height' => '1.15',
                                            'target' => '_blank',
                                            'vertical-align' => 'middle',
                                            'border' => 'none',
                                            'text-align' => 'center',
                                            'letter-spacing' => 'normal',
                                            'href' => '#',
                                            'width' => '',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                1 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#ffffff',
                                    'padding' => '44px 20px 0px 20px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => 'none',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '67%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Product Update: The Abandoned&nbsp;<div>Cart Recovery Feature in Mail&nbsp;</div><div>Mint is here!</div>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 46px 0px 0px',
                                            'align' => 'left',
                                            'font-size' => '18px',
                                            'font-weight' => '700',
                                            'line-height' => '1.44',
                                            'color' => '#0B0F12',
                                            'font-style' => 'normal',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Did you know that about 60% of&nbsp;<div>businesses lose potential sales due to&nbsp;</div><div>cart abandonment?</div>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '14px 46px 10px 00px',
                                            'align' => 'left',
                                            'color' => '#737373',
                                            'font-size' => '16px',
                                            'line-height' => '1.56',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_button',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Read More',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'left',
                                            'font-family' => 'Arial',
                                            'background-color' => '#573BFF',
                                            'color' => '#ffffff',
                                            'font-weight' => 'normal',
                                            'font-style' => 'normal',
                                            'border-radius' => '100px',
                                            'padding' => '20px 0px 0px 00px',
                                            'inner-padding' => '10px 25px 10px 25px',
                                            'font-size' => '13px',
                                            'line-height' => '1.15',
                                            'target' => '_blank',
                                            'vertical-align' => 'middle',
                                            'border' => 'none',
                                            'text-align' => 'center',
                                            'letter-spacing' => 'normal',
                                            'href' => '#',
                                            'width' => '',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '33%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '10px 26px 0px 0px',
                                            'src' => $image_path . 'newsletter-update/post-image-2.png',
                                            'border-radius' => '10px',
                                            'width' => '229px',
                                            'alt' => 'Product Update',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                2 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#ffffff',
                                    'padding' => '44px 20px 0px 20px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => 'none',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '33%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '0px 0px 10px 0px',
                                            'src' => $image_path . 'newsletter-update/post-image-3.png',
                                            'border-radius' => '10px',
                                            'width' => '229px',
                                            'alt' => 'Email Marketing',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '67%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Level up: 10+ SaaS Email Marketing Strategies For Effective Business Growth',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 0px 0px 26px',
                                            'align' => 'left',
                                            'color' => '#0B0F12',
                                            'font-weight' => '700',
                                            'font-size' => '18px',
                                            'line-height' => '1.44',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Running a SaaS business is quite different from a traditional online<div>business.<br></div>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '14px 0px 0px 26px',
                                            'align' => 'left',
                                            'line-height' => '1.56',
                                            'font-size' => '16px',
                                            'font-weight' => '400',
                                            'color' => '#737373',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_button',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Read More',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'left',
                                            'font-family' => 'Arial',
                                            'background-color' => '#573BFF',
                                            'color' => '#ffffff',
                                            'font-weight' => 'normal',
                                            'font-style' => 'normal',
                                            'border-radius' => '100px',
                                            'padding' => '20px 0px 0px 26px',
                                            'inner-padding' => '10px 25px 10px 25px',
                                            'font-size' => '13px',
                                            'line-height' => '1.15',
                                            'target' => '_blank',
                                            'vertical-align' => 'middle',
                                            'border' => 'none',
                                            'text-align' => 'center',
                                            'letter-spacing' => 'normal',
                                            'href' => '#',
                                            'width' => '',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                3 => 
                                array (
                                  'type' => 'advanced_section',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'noWrap' => false,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#ffffff',
                                    'padding' => '44px 20px 0px 20px',
                                    'background-repeat' => 'repeat',
                                    'background-size' => 'auto',
                                    'background-position' => 'top center',
                                    'border' => 'none',
                                    'direction' => 'ltr',
                                    'text-align' => 'center',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '67%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'New articles on Click Rate vs Click Through Rate – Differences &amp; Associated Action Items<br>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '0px 10px 0px 0px',
                                            'align' => 'left',
                                            'font-size' => '18px',
                                            'font-weight' => '700',
                                            'line-height' => '1.44',
                                            'color' => '#0B0F12',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        1 => 
                                        array (
                                          'type' => 'advanced_text',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Tracking the performance of your email campaigns is essential for optimizing your marketing efforts.<br>',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'padding' => '14px 10px 0px 0px',
                                            'align' => 'left',
                                            'color' => '#737373',
                                            'font-size' => '16px',
                                            'line-height' => '1.56',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                        2 => 
                                        array (
                                          'type' => 'advanced_button',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                              'content' => 'Read More',
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'left',
                                            'font-family' => 'Arial',
                                            'background-color' => '#573BFF',
                                            'color' => '#ffffff',
                                            'font-weight' => 'normal',
                                            'font-style' => 'normal',
                                            'border-radius' => '100px',
                                            'padding' => '20px 10px 41px 0px',
                                            'inner-padding' => '10px 25px 10px 25px',
                                            'font-size' => '13px',
                                            'line-height' => '1.15',
                                            'target' => '_blank',
                                            'vertical-align' => 'middle',
                                            'border' => 'none',
                                            'text-align' => 'center',
                                            'letter-spacing' => 'normal',
                                            'href' => '#',
                                            'width' => '',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'advanced_column',
                                      'attributes' => 
                                      array (
                                        'width' => '33%',
                                        'padding' => '0px 0px 0px 0px',
                                      ),
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                        ),
                                      ),
                                      'children' => 
                                      array (
                                        0 => 
                                        array (
                                          'type' => 'advanced_image',
                                          'data' => 
                                          array (
                                            'value' => 
                                            array (
                                            ),
                                          ),
                                          'attributes' => 
                                          array (
                                            'align' => 'center',
                                            'height' => 'auto',
                                            'padding' => '10px 26px 10px 0px',
                                            'src' => $image_path . 'newsletter-update/post-image-4.png',
                                            'border-radius' => '10px',
                                            'width' => '229px',
                                            'alt' => 'News articles compare',
                                          ),
                                          'children' => 
                                          array (
                                          ),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            3 => 
                            array (
                              'attributes' => 
                              array (
                                'padding' => '0px 0px 0px 0px',
                              ),
                            ),
                            4 => 
                            array (
                              'type' => 'advanced_wrapper',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'background-color' => '#0f0940',
                                'padding' => '40px 16px 30px 16px',
                                'border' => 'none',
                                'direction' => 'ltr',
                                'text-align' => 'center',
                              ),
                              'children' => 
                              array (
                                0 => 
                                array (
                                  'type' => 'advanced_hero',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#ffffff',
                                    'background-position' => 'center center',
                                    'mode' => 'fluid-height',
                                    'padding' => '40px 0px 40px 0px',
                                    'vertical-align' => 'top',
                                    'background-url' => $image_path . 'newsletter-update/blog-bg.png'
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'text',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => 'Want to more articles like this?',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'padding' => '10px 25px 10px 25px',
                                        'align' => 'center',
                                        'color' => '#FFFFFF',
                                        'font-size' => '36px',
                                        'line-height' => '1.2',
                                        'font-weight' => '700',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'advanced_button',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => 'Visit Our Blog',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'align' => 'center',
                                        'font-family' => 'Arial',
                                        'background-color' => '#FFFFFF',
                                        'color' => '#573BFF',
                                        'font-weight' => '700',
                                        'font-style' => 'normal',
                                        'border-radius' => '10px',
                                        'padding' => '10px 25px 10px 25px',
                                        'inner-padding' => '17px 61px 17px 61px',
                                        'font-size' => '18px',
                                        'line-height' => '1.2',
                                        'target' => '_blank',
                                        'vertical-align' => 'middle',
                                        'border' => 'none',
                                        'text-align' => 'center',
                                        'letter-spacing' => 'normal',
                                        'href' => '#',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            5 => 
                            array (
                              'type' => 'advanced_wrapper',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'background-color' => '#0F0740',
                                'padding' => '0px 0px 0px 0px',
                                'border' => 'none',
                                'direction' => 'ltr',
                                'text-align' => 'center',
                              ),
                              'children' => 
                              array (
                                0 => 
                                array (
                                  'type' => 'advanced_social',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'elements' => 
                                      array (
                                        0 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'newsletter-update/pinterest.png',
                                          'content' => '',
                                        ),
                                        1 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'newsletter-update/facebook.png',
                                          'content' => '',
                                        ),
                                        2 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'newsletter-update/instagram.png',
                                          'content' => '',
                                        ),
                                        3 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'newsletter-update/twiter.png',
                                          'content' => '',
                                        ),
                                      ),
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'color' => '#333333',
                                    'mode' => 'horizontal',
                                    'font-size' => '13px',
                                    'font-weight' => 'normal',
                                    'font-style' => 'normal',
                                    'font-family' => 'Arial',
                                    'border-radius' => '3px',
                                    'padding' => '0px 0px 0 0px',
                                    'inner-padding' => '0px 0px 0px 20px',
                                    'line-height' => '1.6',
                                    'text-padding' => '0px 0px 0px 0px',
                                    'icon-padding' => '0px',
                                    'icon-size' => '40px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                1 => 
                                array (
                                  'type' => 'advanced_divider',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'border-width' => '2px',
                                    'border-style' => 'solid',
                                    'border-color' => '#2D2368',
                                    'padding' => '30px 24px 40px 24px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                2 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'No longer want to be Mail Mint friends?',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '0px 0px 0px 0px',
                                    'align' => 'center',
                                    'color' => '#928AC1',
                                    'font-size' => '15px',
                                    'line-height' => '1.46',
                                    'font-weight' => '400',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                3 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => '<a href="{{link.preference}}">Email Preference</a>&nbsp; .&nbsp; <a href="{{link.unsubscribe}}">Unsubscribe</a>',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '8px 0px 0px 0px',
                                    'align' => 'center',
                                    'color' => '#928AC1',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                4 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '24px 0px 40px 0px',
                                    'align' => 'center',
                                    'color' => '#928AC1',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/newsletter-update.jpg',
                ),
                array(
                    'id'              => 29,
                    'is_pro'          => true,
                    'emailCategories' => ['Abandoned Cart Recovery'],
                    'industry'        => ['Fashion & Jewelry','E-commerce & Retail'],
                    'title'           => 'Abandoned Cart Reminder',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/abandoned-cart-reminder.jpg',
                ),
                array(
                    'id'              => 30,
                    'is_pro'          => false,
                    'emailCategories' => ['Deals & Offers'],
                    'industry'        => ['Others'],
                    'title'           => 'Birthday Greetings',
                    'json_content'    => array (
                        'subject' => 'Welcome to Mail Mint email marketing and automation',
                        'subTitle' => 'Nice to meet you!',
                        'content' => 
                        array (
                          'type' => 'page',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'breakpoint' => '480px',
                              'headAttributes' => '',
                              'font-size' => '14px',
                              'font-weight' => '400',
                              'line-height' => '1.7',
                              'headStyles' => 
                              array (
                              ),
                              'fonts' => 
                              array (
                              ),
                              'responsive' => true,
                              'font-family' => 'Arial',
                              'text-color' => '#000000',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'background-color' => '#efeeea',
                            'width' => '600px',
                          ),
                          'children' => 
                          array (
                            0 => 
                            array (
                              'type' => 'advanced_image',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'height' => 'auto',
                                'padding' => '16px 0px 16px 0px',
                                'src' => $image_path . 'your-logo.png',
                                'container-background-color' => '#fff',
                                'width' => '142px',
                                'alt' => 'logo',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            1 => 
                            array (
                              'type' => 'advanced_wrapper',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'background-color' => '#43B4AF',
                                'padding' => '40px 22px 0px 22px',
                                'border' => 'none',
                                'direction' => 'ltr',
                                'text-align' => 'center',
                              ),
                              'children' => 
                              array (
                                0 => 
                                array (
                                  'type' => 'advanced_hero',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'background-color' => '#ffffff',
                                    'background-position' => 'center center',
                                    'mode' => 'fluid-height',
                                    'padding' => '0px 0px 100px 0px',
                                    'vertical-align' => 'top',
                                    'background-url' => $image_path . 'birthday-greetings/bg.png',
                                    'background-width' => '556px',
                                    'background-height' => 'auto',
                                    'width' => '',
                                  ),
                                  'children' => 
                                  array (
                                    0 => 
                                    array (
                                      'type' => 'text',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => '🎉 Happy Birthday!',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'padding' => '80px 0px 10px 0px',
                                        'align' => 'center',
                                        'color' => '#2B2D38',
                                        'font-size' => '34px',
                                        'line-height' => '1.29',
                                        'font-weight' => '700',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                    1 => 
                                    array (
                                      'type' => 'text',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => '🎂 Your Special Gift Inside! 🎁',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'align' => 'center',
                                        'background-color' => '#414141',
                                        'color' => '#2B2D38',
                                        'font-weight' => '500',
                                        'border-radius' => '3px',
                                        'padding' => '0px 0px 0px 0px',
                                        'inner-padding' => '10px 25px 10px 25px',
                                        'line-height' => '1.29',
                                        'target' => '_blank',
                                        'vertical-align' => 'middle',
                                        'border' => 'none',
                                        'text-align' => 'center',
                                        'href' => '#',
                                        'font-size' => '34px',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                    2 => 
                                    array (
                                      'type' => 'advanced_text',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => 'May your birthday be filled with warmth, laughter, and the company of those who hold a special place in your heart. Here\'s to the amazing person you are and the wonderful moments that lie ahead!',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'padding' => '24px 20px 0px 20px',
                                        'align' => 'center',
                                        'color' => '#737373',
                                        'font-size' => '18px',
                                        'line-height' => '1.56',
                                        'font-style' => 'normal',
                                        'font-weight' => '400',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                    3 => 
                                    array (
                                      'type' => 'advanced_button',
                                      'data' => 
                                      array (
                                        'value' => 
                                        array (
                                          'content' => '🎁  Get Your Gift',
                                        ),
                                      ),
                                      'attributes' => 
                                      array (
                                        'align' => 'center',
                                        'font-family' => 'Arial',
                                        'background-color' => '#43B4AF',
                                        'color' => '#161616',
                                        'font-weight' => 'normal',
                                        'font-style' => 'normal',
                                        'border-radius' => '30px',
                                        'padding' => '40px 0px 160px 0px',
                                        'inner-padding' => '17px 32px 17px 32px',
                                        'font-size' => '18px',
                                        'line-height' => '0.83',
                                        'target' => '_blank',
                                        'vertical-align' => 'middle',
                                        'border' => 'none',
                                        'text-align' => 'center',
                                        'letter-spacing' => 'normal',
                                        'href' => '#',
                                        'width' => '205px',
                                      ),
                                      'children' => 
                                      array (
                                      ),
                                    ),
                                  ),
                                ),
                                1 => 
                                array (
                                  'type' => 'advanced_social',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'elements' => 
                                      array (
                                        0 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'birthday-greetings/pinterest.png',
                                          'content' => '',
                                        ),
                                        1 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'birthday-greetings/facebook.png',
                                          'content' => '',
                                        ),
                                        2 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'birthday-greetings/instagram.png',
                                          'content' => '',
                                        ),
                                        3 => 
                                        array (
                                          'href' => '#',
                                          'target' => '_blank',
                                          'src' => $image_path . 'birthday-greetings/twiter.png',
                                          'content' => '',
                                        ),
                                      ),
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'color' => '#333333',
                                    'mode' => 'horizontal',
                                    'font-size' => '13px',
                                    'font-weight' => 'normal',
                                    'font-style' => 'normal',
                                    'font-family' => 'Arial',
                                    'border-radius' => '',
                                    'padding' => '30px 0px 0px 0px',
                                    'inner-padding' => '0px 0px 0px 20px',
                                    'line-height' => '1.6',
                                    'text-padding' => '4px 4px 4px 0px',
                                    'icon-padding' => '0px',
                                    'icon-size' => '40px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                2 => 
                                array (
                                  'type' => 'advanced_divider',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'border-width' => '1px',
                                    'border-style' => 'solid',
                                    'border-color' => '#67C6C2',
                                    'padding' => '30px 22px 0px 26px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                3 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'No longer want to be Mail Mint friends?',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '40px 0px 10px 0px',
                                    'align' => 'center',
                                    'color' => '#D1F7F5',
                                    'font-size' => '15px',
                                    'line-height' => '1.46',
                                    'font-family' => 'Arial',
                                    'font-style' => 'normal',
                                    'font-weight' => '400',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                4 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => '<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;">Email Preference</a>&nbsp; .&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;">Unsubscribe</a>',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '8px 0px 0px 0px',
                                    'align' => 'center',
                                    'color' => '#D1F7F5',
                                    'font-size' => '15px',
                                    'line-height' => '1.46',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                5 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '24px 0px 31px 0px',
                                    'align' => 'center',
                                    'color' => '#D1F7F5',
                                    'font-size' => '15px',
                                    'line-height' => '1.57',
                                    'font-weight' => '400',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/birthday-greetings.jpg',
                ),
                array(
                    'id'              => 31,
                    'is_pro'          => true,
                    'emailCategories' => ['Announcement'],
                    'industry'        => ['Business & Finance','E-commerce & Retail', 'Others'],
                    'title'           => 'Referral Program Invitation',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/referral-program.jpg',
                ),
                array(
                    'id'              => 32,
                    'is_pro'          => true,
                    'emailCategories' => ['Educate & Inform'],
                    'industry'        => ['Business & Finance','E-commerce & Retail', 'Others'],
                    'title'           => 'Apology Email Template',
                    'json_content'    => [],
                    'html_content'    => '',
                    'thumbnail_image' => $image_path . '/thumbnails/apology-email-template.jpg',
                ),
              array(
                  'id'              => 33,
                  'is_pro'          => false,
                  'emailCategories' => ['Review & Feedback'],
                  'industry'        => ['Business & Finance','E-commerce & Retail', 'Others', 'Education & Non Profit'],
                  'title'           => 'Feedback Needed',
                  'json_content'    => array (
                    'content' => 
                    array (
                      'type' => 'page',
                      'data' => 
                      array (
                        'value' => 
                        array (
                          'breakpoint' => '480px',
                          'headAttributes' => '',
                          'font-size' => '14px',
                          'font-weight' => '400',
                          'line-height' => '1.7',
                          'headStyles' => 
                          array (
                          ),
                          'fonts' => 
                          array (
                          ),
                          'responsive' => true,
                          'font-family' => 'Arial',
                          'text-color' => '#000000',
                        ),
                      ),
                      'attributes' => 
                      array (
                        'background-color' => '#efeeea',
                        'width' => '600px',
                      ),
                      'children' => 
                      array (
                        0 => 
                        array (
                          'type' => 'advanced_image',
                          'data' => 
                          array (
                            'value' => 
                            array (
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'height' => 'auto',
                            'padding' => '16px 0px 16px 0px',
                            'src' => $image_path . 'your-logo.png',
                            'width' => '100%',
                            'container-background-color' => '#fff',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        1 => 
                        array (
                          'type' => 'advanced_wrapper',
                          'data' => 
                          array (
                            'value' => 
                            array (
                            ),
                          ),
                          'attributes' => 
                          array (
                            'background-color' => '#6557F5',
                            'padding' => '24px 24px 0px 24px',
                            'border' => 'none',
                            'direction' => 'ltr',
                            'text-align' => 'center',
                          ),
                          'children' => 
                          array (
                            0 => 
                            array (
                              'type' => 'advanced_hero',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'background-color' => '#ffffff',
                                'background-position' => 'center center',
                                'mode' => 'fluid-height',
                                'padding' => '0px 0px 0px 0px',
                                'vertical-align' => 'top',
                                'background-url' => '',
                              ),
                              'children' => 
                              array (
                                0 => 
                                array (
                                  'type' => 'text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'Elevate Your Experience - Your Feedback Needed!',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '40px 0px 0px 0px',
                                    'align' => 'center',
                                    'color' => '#1F1F2D',
                                    'font-size' => '30px',
                                    'line-height' => '1.33',
                                    'font-weight' => '800',
                                    'font-style' => 'normal',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                1 => 
                                array (
                                  'type' => 'advanced_image',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'height' => 'auto',
                                    'padding' => '65px 0px 0px 0px',
                                    'src' => $image_path . 'feedback-needed/hero-img.png',
                                    'width' => '258px',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                2 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'Dear John Doe,',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '49px 0px 0px 40px',
                                    'align' => 'left',
                                    'font-style' => 'normal',
                                    'font-size' => '16px',
                                    'line-height' => '1',
                                    'font-weight' => '400',
                                    'color' => '#0B1B1B',
                                    'font-family' => 'Arial',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                3 => 
                                array (
                                  'type' => 'advanced_text',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'I hope this email finds you well. Your satisfaction is our top priority. We greatly appreciate your trust in our services and would love to hear about your experience.  <div><br><div>Take a moment for our survey, and get a 30% discount on 
                  your next renewal.&nbsp;</div><div><br></div><div>We would like to take this opportunity to sincerely thank you for choosing Mail Mint. Your support means a lot to us, and we are committed to ensuring your satisfaction.</div><div><br></div><div>Please take a moment to share your thoughts by clicking on the following link to access our feedback form</div></div>',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'padding' => '23px 02px 0px 40px',
                                    'align' => 'left',
                                    'font-size' => '16px',
                                    'line-height' => '1.75',
                                    'font-weight' => '400',
                                    'font-style' => 'normal',
                                    'color' => '#0B1B1B',
                                    'font-family' => 'Arial',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                                4 => 
                                array (
                                  'type' => 'advanced_button',
                                  'data' => 
                                  array (
                                    'value' => 
                                    array (
                                      'content' => 'Share Your Insights',
                                    ),
                                  ),
                                  'attributes' => 
                                  array (
                                    'align' => 'center',
                                    'font-family' => 'Arial',
                                    'background-color' => '#6557F5',
                                    'color' => '#ffffff',
                                    'font-weight' => '700',
                                    'font-style' => 'normal',
                                    'border-radius' => '10px',
                                    'padding' => '40px 0px 40px 0px',
                                    'inner-padding' => '15px 30px 15px 30px',
                                    'font-size' => '18px',
                                    'line-height' => '0.83',
                                    'target' => '_blank',
                                    'vertical-align' => 'middle',
                                    'border' => 'none',
                                    'text-align' => 'center',
                                    'letter-spacing' => 'normal',
                                    'href' => '#',
                                    'width' => '',
                                  ),
                                  'children' => 
                                  array (
                                  ),
                                ),
                              ),
                            ),
                            1 => 
                            array (
                              'type' => 'advanced_social',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'elements' => 
                                  array (
                                    0 => 
                                    array (
                                      'href' => '#',
                                      'target' => '_blank',
                                      'src' => $image_path . 'feedback-needed/pinterest.png',
                                      'content' => '',
                                    ),
                                    1 => 
                                    array (
                                      'href' => '#',
                                      'target' => '_blank',
                                      'src' => $image_path . 'feedback-needed/facebook.png',
                                      'content' => '',
                                    ),
                                    2 => 
                                    array (
                                      'href' => '#',
                                      'target' => '_blank',
                                      'src' => $image_path . 'feedback-needed/instagram.png',
                                      'content' => '',
                                    ),
                                    3 => 
                                    array (
                                      'href' => '#',
                                      'target' => '_blank',
                                      'src' => $image_path . 'feedback-needed/twiter.png',
                                      'content' => '',
                                    ),
                                  ),
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'color' => '',
                                'mode' => 'horizontal',
                                'font-size' => '',
                                'font-weight' => 'normal',
                                'font-style' => 'normal',
                                'font-family' => 'Arial',
                                'border-radius' => '',
                                'padding' => '30px 0px 0px 0px',
                                'inner-padding' => '0px 0px 0px 20px',
                                'line-height' => '',
                                'text-padding' => '4px 4px 4px 0px',
                                'icon-padding' => '0px',
                                'icon-size' => '40px',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              'type' => 'advanced_divider',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'border-width' => '1px',
                                'border-style' => 'solid',
                                'border-color' => '#8D82FF',
                                'padding' => '30px 24px 0px 24px',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              'type' => 'advanced_text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'No longer want to be Mail Mint friends?',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '40px 0px 0px 0px',
                                'align' => 'center',
                                'color' => 'rgba(255, 255, 255, 0.60)',
                                'font-size' => '15px',
                                'font-weight' => '400',
                                'font-style' => 'normal',
                                'line-height' => '1.46',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            4 => 
                            array (
                              'type' => 'advanced_text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => '<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;">Email Preference</a>&nbsp; .&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;">Unsubscribe</a>',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '8px 0px 0px 0px',
                                'align' => 'center',
                                'color' => 'rgba(255, 255, 255, 0.60)',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            5 => 
                            array (
                              'type' => 'advanced_text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '24px 0px 30px 0px',
                                'align' => 'center',
                                'color' => 'rgba(255, 255, 255, 0.60)',
                                'font-size' => '14px',
                                'font-family' => 'Arial',
                                'line-height' => '1.57',
                                'font-style' => 'normal',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                    'subTitle' => 'Nice to meet you!',
                  ),
                  'html_content'    => '',
                  'thumbnail_image' => $image_path . '/thumbnails/feedback-needed.jpg',
              ),
              array(
                'id'              => 34,
                'is_pro'          => true,
                'emailCategories' => ['Educate & Inform'],
                'industry'        => ['Business & Finance', 'Others'],
                'title'           => 'Behind The Scenes Peek',
                'json_content'    => [],
                'html_content'    => '',
                'thumbnail_image' => $image_path . '/thumbnails/behind-the-scenes-peek.jpg',
              ),
              array(
                'id'              => 35,
                'is_pro'          => false,
                'emailCategories' => ['Review & Feedback'],
                'industry'        => ['Business & Finance', 'E-commerce & Retail', 'Others'],
                'title'           => 'Survey Invitation',
                'json_content'    => array (
                  'subject' => 'Welcome to Mail Mint email marketing and automation',
                  'subTitle' => 'Nice to meet you!',
                  'content' => 
                  array (
                    'type' => 'page',
                    'data' => 
                    array (
                      'value' => 
                      array (
                        'breakpoint' => '480px',
                        'headAttributes' => '',
                        'font-size' => '14px',
                        'font-weight' => '400',
                        'line-height' => '1.7',
                        'headStyles' => 
                        array (
                        ),
                        'fonts' => 
                        array (
                        ),
                        'responsive' => true,
                        'font-family' => 'Arial',
                        'text-color' => '#000000',
                      ),
                    ),
                    'attributes' => 
                    array (
                      'background-color' => '#efeeea',
                      'width' => '600px',
                    ),
                    'children' => 
                    array (
                      0 => 
                      array (
                        'type' => 'advanced_image',
                        'data' => 
                        array (
                          'value' => 
                          array (
                          ),
                        ),
                        'attributes' => 
                        array (
                          'align' => 'center',
                          'height' => 'auto',
                          'padding' => '16px 0px 16px 0px',
                          'src' => $image_path . 'your-logo.png',
                          'width' => '142px',
                          'container-background-color' => '#ffff',
                        ),
                        'children' => 
                        array (
                        ),
                      ),
                      1 => 
                      array (
                        'type' => 'advanced_wrapper',
                        'data' => 
                        array (
                          'value' => 
                          array (
                          ),
                        ),
                        'attributes' => 
                        array (
                          'background-color' => '#F5F5F5',
                          'padding' => '22px 24px 0px 24px',
                          'border' => 'none',
                          'direction' => 'ltr',
                          'text-align' => 'center',
                        ),
                        'children' => 
                        array (
                          0 => 
                          array (
                            'type' => 'advanced_hero',
                            'data' => 
                            array (
                              'value' => 
                              array (
                              ),
                            ),
                            'attributes' => 
                            array (
                              'background-color' => '#ffffff',
                              'background-position' => 'center center',
                              'mode' => 'fluid-height',
                              'padding' => '0px 0px 0px 0px',
                              'vertical-align' => 'top',
                              'background-url' => '',
                              'border-radius' => '10px',
                            ),
                            'children' => 
                            array (
                              0 => 
                              array (
                                'type' => 'advanced_image',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'align' => 'center',
                                  'height' => 'auto',
                                  'padding' => '51px 0px 0px 0px',
                                  'src' => $image_path . 'survey-invitation/hero-img.png',
                                  'width' => '250px',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              1 => 
                              array (
                                'type' => 'text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => 'What can we do for you?',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '10px 20px 0px 20px',
                                  'align' => 'center',
                                  'color' => '#22252A',
                                  'font-size' => '36px',
                                  'line-height' => '1.16',
                                  'font-weight' => '800',
                                  'font-family' => 'Arial',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              2 => 
                              array (
                                'type' => 'text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => 'Thank you for choosing Mail Mint! We\'re excited to have you on board and are eager to enhance your experience. Your valuable insights can help us tailor our app to meet your specific needs.',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'align' => 'left',
                                  'background-color' => '#414141',
                                  'color' => '#6C6C6C',
                                  'font-weight' => '400',
                                  'border-radius' => '3px',
                                  'padding' => '15px 20px 0px 40px',
                                  'inner-padding' => '10px 25px 10px 25px',
                                  'line-height' => '1.56',
                                  'target' => '_blank',
                                  'vertical-align' => 'middle',
                                  'border' => 'none',
                                  'text-align' => 'center',
                                  'href' => '#',
                                  'font-size' => '16px',
                                  'font-family' => 'Arial',
                                  'font-style' => 'normal',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              3 => 
                              array (
                                'type' => 'advanced_text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => '<ul><li>Take Our Quick 3-5 Minute Survey 📊<br></li></ul>',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '40px 25px 0px 20px',
                                  'align' => 'left',
                                  'font-weight' => '800',
                                  'font-size' => '18px',
                                  'line-height' => '1.11',
                                  'font-family' => 'Arial',
                                  'color' => '#42445D',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              4 => 
                              array (
                                'type' => 'advanced_text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => 'To understand your usage and goals better, please take a few minutes to complete our brief survey',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '0px 20px 0px 60px',
                                  'align' => 'left',
                                  'font-size' => '16px',
                                  'font-family' => 'Arial',
                                  'line-height' => '1.56',
                                  'font-weight' => '400',
                                  'color' => '#707070',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              5 => 
                              array (
                                'type' => 'advanced_text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => '<ul><li>Exclusive Invite: Join a Direct Chat 🚀<br></li></ul>',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '25px 25px 0px 20px',
                                  'align' => 'left',
                                  'font-weight' => '800',
                                  'font-size' => '18px',
                                  'line-height' => '1.11',
                                  'font-family' => 'Arial',
                                  'color' => '#42445D',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              6 => 
                              array (
                                'type' => 'advanced_text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => 'Want to dive deeper into your needs? Sign up for a direct chat at the end of the survey. We have limited slots, so the sooner you share your thoughts, the better.',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '0px 20px 0px 60px',
                                  'align' => 'left',
                                  'font-size' => '16px',
                                  'font-family' => 'Arial',
                                  'line-height' => '1.56',
                                  'font-weight' => '400',
                                  'color' => '#707070',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              7 => 
                              array (
                                'type' => 'advanced_text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => '<ul><li>Bonus: $75 Gift Voucher 🎁<br></li></ul>',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '25px 25px 0px 20px',
                                  'align' => 'left',
                                  'font-weight' => '800',
                                  'font-size' => '18px',
                                  'line-height' => '1.11',
                                  'font-family' => 'Arial',
                                  'color' => '#42445D',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              8 => 
                              array (
                                'type' => 'advanced_text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => 'As a token of our appreciation, participants in the direct chat will receive a $75 voucher gift after the meeting.
                ',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '0px 20px 0px 60px',
                                  'align' => 'left',
                                  'font-size' => '16px',
                                  'font-family' => 'Arial',
                                  'line-height' => '1.56',
                                  'font-weight' => '400',
                                  'color' => '#707070',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              9 => 
                              array (
                                'type' => 'advanced_button',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => 'start the survey',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'align' => 'center',
                                  'font-family' => 'Arial',
                                  'background-color' => '#0064FF',
                                  'color' => '#ffffff',
                                  'font-weight' => '600',
                                  'font-style' => 'normal',
                                  'border-radius' => '12px',
                                  'padding' => '50px 0px 0px 0px',
                                  'inner-padding' => '17px 30px 17px 30px',
                                  'font-size' => '16px',
                                  'line-height' => '0.93',
                                  'target' => '_blank',
                                  'vertical-align' => 'middle',
                                  'border' => 'none',
                                  'text-align' => 'center',
                                  'letter-spacing' => 'normal',
                                  'href' => '#',
                                  'width' => '193px',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                              10 => 
                              array (
                                'type' => 'advanced_text',
                                'data' => 
                                array (
                                  'value' => 
                                  array (
                                    'content' => 'You Can Always Visit Our<b>&nbsp;<font color="#4a90e2"><a href="#" target="_blank" style="color: inherit; text-decoration: underline;" tabindex="-1">Help Center </a></font></b>For Tips And Resources<div>Or Contact&nbsp;Customer Happiness With questions.</div>',
                                  ),
                                ),
                                'attributes' => 
                                array (
                                  'padding' => '24px 30px 36px 30px',
                                  'align' => 'center',
                                  'color' => '#707070',
                                  'font-size' => '15px',
                                  'font-weight' => '400',
                                  'font-family' => 'Arial',
                                  'line-height' => '1.53',
                                ),
                                'children' => 
                                array (
                                ),
                              ),
                            ),
                          ),
                          1 => 
                          array (
                            'type' => 'advanced_social',
                            'data' => 
                            array (
                              'value' => 
                              array (
                                'elements' => 
                                array (
                                  0 => 
                                  array (
                                    'href' => '#',
                                    'target' => '_blank',
                                    'src' => $image_path . 'survey-invitation/pinterest.png',
                                    'content' => '',
                                  ),
                                  1 => 
                                  array (
                                    'href' => '#',
                                    'target' => '_blank',
                                    'src' => $image_path . 'survey-invitation/facebook.png',
                                    'content' => '',
                                  ),
                                  2 => 
                                  array (
                                    'href' => '#',
                                    'target' => '_blank',
                                    'src' => $image_path . 'survey-invitation/instagram.png',
                                    'content' => '',
                                  ),
                                  3 => 
                                  array (
                                    'href' => '#',
                                    'target' => '_blank',
                                    'src' => $image_path . 'survey-invitation/twiter.png',
                                    'content' => '',
                                  ),
                                ),
                              ),
                            ),
                            'attributes' => 
                            array (
                              'align' => 'center',
                              'color' => '#333333',
                              'mode' => 'horizontal',
                              'font-size' => '13px',
                              'font-weight' => 'normal',
                              'font-style' => 'normal',
                              'font-family' => 'Arial',
                              'border-radius' => '3px',
                              'padding' => '30px 0px 0px 0px',
                              'inner-padding' => '0px 0px 0px 20px',
                              'line-height' => '1.6',
                              'text-padding' => '4px 4px 4px 0px',
                              'icon-padding' => '0px',
                              'icon-size' => '40px',
                            ),
                            'children' => 
                            array (
                            ),
                          ),
                          2 => 
                          array (
                            'type' => 'advanced_divider',
                            'data' => 
                            array (
                              'value' => 
                              array (
                              ),
                            ),
                            'attributes' => 
                            array (
                              'align' => 'center',
                              'border-width' => '1px',
                              'border-style' => 'solid',
                              'border-color' => '#D9D9D9',
                              'padding' => '30px 24px 0px 24px',
                            ),
                            'children' => 
                            array (
                            ),
                          ),
                          3 => 
                          array (
                            'type' => 'advanced_text',
                            'data' => 
                            array (
                              'value' => 
                              array (
                                'content' => 'No longer want to be Mail Mint friends?',
                              ),
                            ),
                            'attributes' => 
                            array (
                              'padding' => '30px 0px 0px 0px',
                              'align' => 'center',
                              'color' => '#969696',
                              'font-size' => '15px',
                              'line-height' => '1',
                              'font-weight' => '400',
                            ),
                            'children' => 
                            array (
                            ),
                          ),
                          4 => 
                          array (
                            'type' => 'advanced_text',
                            'data' => 
                            array (
                              'value' => 
                              array (
                                'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                              ),
                            ),
                            'attributes' => 
                            array (
                              'padding' => '12px 0px 0px 0px',
                              'align' => 'center',
                              'color' => '#969696',
                              'font-weight' => '400',
                              'font-size' => '14px',
                              'line-height' => '1.42',
                            ),
                            'children' => 
                            array (
                            ),
                          ),
                          5 => 
                          array (
                            'type' => 'advanced_text',
                            'data' => 
                            array (
                              'value' => 
                              array (
                                'content' => '<a href="#" target="_blank" style="text-decoration: underline; color:#4a90e2;" tabindex="-1"><font color="#4a90e2">Update Preference</font></a> . <a href="#" target="_blank" style="text-decoration: underline; color:#4a90e2;" tabindex="-1"><font color="#4a90e2">Unsubscribe</font></a>',
                              ),
                            ),
                            'attributes' => 
                            array (
                              'padding' => '12px 0px 32px 0px',
                              'align' => 'center',
                              'font-size' => '13px',
                              'font-family' => 'Arial',
                              'line-height' => '1.69',
                              'color' => '#0064FF',
                            ),
                            'children' => 
                            array (
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
                'html_content'    => '',
                'thumbnail_image' => $image_path . '/thumbnails/survey-invitation.jpg',
              ),
            array(
              'id'              => 36,
              'is_pro'          => true,
              'emailCategories' => ['Announcement'],
              'industry'        => ['E-commerce & Retail'],
              'title'           => 'Product Launch Announcement',
              'json_content'    => [],
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/product-launch-announcement.jpg',
            ),
            array(
              'id'              => 37,
              'is_pro'          => true,
              'emailCategories' => ['Educate & Inform'],
              'industry'        => ['E-commerce & Retail', 'Business & Finance', 'Health & Wellness'],
              'title'           => 'Customer Success Story',
              'json_content'    => [],
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/customer-success-story.jpg',
            ),
            array(
              'id'              => 38,
              'is_pro'          => true,
              'emailCategories' => ['Selling Services'],
              'industry'        => ['Business & Finance', 'Education & Non Profit', 'Others'],
              'title'           => 'Educational Content',
              'json_content'    => [],
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/educational-content.jpg',
            ),
            array(
              'id'              => 39,
              'is_pro'          => true,
              'emailCategories' => ['Events'],
              'industry'        => ['Business & Finance', 'Education & Non Profit', 'Others'],
              'title'           => 'Campaign Alert',
              'json_content'    => [],
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/campaign-alert.jpg',
            ),
            array(
              'id'              => 40,
              'is_pro'          => true,
              'emailCategories' => ['Selling Products'],
              'industry'        => ['E-commerce & Retail'],
              'title'           => 'Seasonal Greetings',
              'json_content'    => [],
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/seasonal-greetings.jpg',
            ),
            array(
              'id'              => 41,
              'is_pro'          => true,
              'emailCategories' => ['Selling Products'],
              'industry'        => ['E-commerce & Retail', 'Health & Wellness'],
              'title'           => 'Personalized Recommendation',
              'json_content'    => [],
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/personalized-recommendations.jpg',
            ),
            array(
              'id'              => 42,
              'is_pro'          => false,
              'emailCategories' => ['Deals & Offers'],
              'industry'        => ['E-commerce & Retail', 'Others'],
              'title'           => 'Easter Bunny, Easter Eggs',
              'json_content'    => array (
                'subject' => 'Welcome to Mail Mint email marketing and automation',
                'subTitle' => 'Nice to meet you!',
                'content' => 
                array (
                  'type' => 'page',
                  'data' => 
                  array (
                    'value' => 
                    array (
                      'breakpoint' => '480px',
                      'headAttributes' => '',
                      'font-size' => '14px',
                      'font-weight' => '400',
                      'line-height' => '1.7',
                      'headStyles' => 
                      array (
                      ),
                      'fonts' => 
                      array (
                      ),
                      'responsive' => true,
                      'font-family' => 'Arial',
                      'text-color' => '#000000',
                    ),
                  ),
                  'attributes' => 
                  array (
                    'background-color' => '#efeeea',
                    'width' => '600px',
                  ),
                  'children' => 
                  array (
                    0 => 
                    array (
                      'type' => 'advanced_image',
                      'data' => 
                      array (
                        'value' => 
                        array (
                        ),
                      ),
                      'attributes' => 
                      array (
                        'align' => 'center',
                        'height' => 'auto',
                        'padding' => '20px 0px 20px 0px',
                        'src' => $image_path . 'your-logo.png',
                        'width' => '100%',
                        'container-background-color' => '#FFFFFF',
                        'alt' => 'Logo',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      'type' => 'advanced_wrapper',
                      'data' => 
                      array (
                        'value' => 
                        array (
                        ),
                      ),
                      'attributes' => 
                      array (
                        'background-color' => '#80DB8E',
                        'padding' => '24px 24px 24px 24px',
                        'border' => 'none',
                        'direction' => 'ltr',
                        'text-align' => 'center',
                      ),
                      'children' => 
                      array (
                        0 => 
                        array (
                          'type' => 'advanced_hero',
                          'data' => 
                          array (
                            'value' => 
                            array (
                            ),
                          ),
                          'attributes' => 
                          array (
                            'background-color' => '#FBE089',
                            'background-position' => 'center center',
                            'mode' => 'fluid-height',
                            'padding' => '40px 0px 0px 0px',
                            'vertical-align' => 'top',
                            'background-url' => '',
                          ),
                          'children' => 
                          array (
                            0 => 
                            array (
                              'type' => 'text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'Easter Hunt Is&nbsp;<div>Ending Soon!</div>',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '10px 10px 10px 10px',
                                'align' => 'center',
                                'color' => '#000000',
                                'font-size' => '40px',
                                'line-height' => '46px',
                                'font-weight' => '800',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            1 => 
                            array (
                              'type' => 'button',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'Code: Easter60',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'background-color' => '#80DB8E',
                                'color' => '#273528',
                                'font-size' => '15px',
                                'font-weight' => '800',
                                'border-radius' => '6px',
                                'padding' => '10px 0px 10px 0px',
                                'inner-padding' => '10px 12px 10px 12px',
                                'line-height' => '14px',
                                'target' => '_blank',
                                'vertical-align' => 'middle',
                                'border' => 'none',
                                'text-align' => 'center',
                                'href' => '#',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              'type' => 'advanced_text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'Celebrate Easter with exclusive discounts!&nbsp;<div>Limited-time offers on our spring collections.</div>',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '10px 10px 10px 10px',
                                'align' => 'center',
                                'font-size' => '18px',
                                'line-height' => '28px',
                                'color' => '#273528',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              'type' => 'advanced_button',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'Shop Now',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'font-family' => 'Arial',
                                'background-color' => '#414141',
                                'color' => '#ffffff',
                                'font-weight' => '600',
                                'font-style' => 'normal',
                                'border-radius' => '6px',
                                'padding' => '10px 0px 10px 0px',
                                'inner-padding' => '18px 45px 18px 45px',
                                'font-size' => '16px',
                                'line-height' => '15px',
                                'target' => '_blank',
                                'vertical-align' => 'middle',
                                'border' => 'none',
                                'text-align' => 'center',
                                'letter-spacing' => 'normal',
                                'href' => '#',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          'type' => 'advanced_image',
                          'data' => 
                          array (
                            'value' => 
                            array (
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'height' => 'auto',
                            'padding' => '0px 0px 0px 0px',
                            'src' => $image_path . 'order-img.png',
                            'alt' => 'Order Image',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                      ),
                    ),
                    2 => 
                    array (
                      'type' => 'advanced_social',
                      'data' => 
                      array (
                        'value' => 
                        array (
                          'elements' => 
                          array (
                            0 => 
                            array (
                              'href' => '#',
                              'target' => '_blank',
                              'src' => $image_path . $pinterest,
                              'content' => '',
                            ),
                            1 => 
                            array (
                              'href' => '#',
                              'target' => '_blank',
                              'src' => $image_path . $facebook,
                              'content' => '',
                            ),
                            2 => 
                            array (
                              'href' => '#',
                              'target' => '_blank',
                              'src' => $image_path . $instagram,
                              'content' => '',
                            ),
                            3 => 
                            array (
                              'href' => '#',
                              'target' => '_blank',
                              'src' => $image_path . $twitter,
                              'content' => '',
                            ),
                          ),
                        ),
                      ),
                      'attributes' => 
                      array (
                        'align' => 'center',
                        'color' => '#333333',
                        'mode' => 'horizontal',
                        'font-size' => '13px',
                        'font-weight' => 'normal',
                        'font-style' => 'normal',
                        'font-family' => 'Arial',
                        'border-radius' => '3px',
                        'padding' => '30px 0px 30px 0px',
                        'inner-padding' => '0px 20px 0px 0px',
                        'line-height' => '1.6',
                        'text-padding' => '4px 4px 4px 0px',
                        'icon-padding' => '0px',
                        'icon-size' => '40px',
                        'container-background-color' => '#FFFFFF',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    3 => 
                    array (
                      'type' => 'advanced_divider',
                      'data' => 
                      array (
                        'value' => 
                        array (
                        ),
                      ),
                      'attributes' => 
                      array (
                        'align' => 'center',
                        'border-width' => '1px',
                        'border-style' => 'solid',
                        'border-color' => '#EDECE9',
                        'padding' => '0px 24px 40px 24px',
                        'container-background-color' => '#FFFFFF',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    4 => 
                    array (
                      'type' => 'advanced_text',
                      'data' => 
                      array (
                        'value' => 
                        array (
                          'content' => 'No longer want to be Mail Mint friends?',
                        ),
                      ),
                      'attributes' => 
                      array (
                        'padding' => '0px 10px 8px 10px',
                        'align' => 'center',
                        'font-size' => '15px',
                        'line-height' => '22px',
                        'color' => '#8F8F8F',
                        'container-background-color' => '#FFFFFF',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    5 => 
                    array (
                      'type' => 'advanced_text',
                      'data' => 
                      array (
                        'value' => 
                        array (
                          'content' => '<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;">Email Preference</a>&nbsp;.&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;">Unsubscribe</a>',
                        ),
                      ),
                      'attributes' => 
                      array (
                        'padding' => '0px 10px 24px 10px',
                        'align' => 'center',
                        'font-size' => '15px',
                        'line-height' => '22px',
                        'color' => '#8F8F8F',
                        'container-background-color' => '#FFFFFF',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    6 => 
                    array (
                      'type' => 'advanced_text',
                      'data' => 
                      array (
                        'value' => 
                        array (
                          'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                        ),
                      ),
                      'attributes' => 
                      array (
                        'padding' => '0px 10px 30px 10px',
                        'align' => 'center',
                        'font-size' => '15px',
                        'line-height' => '22px',
                        'color' => '#8F8F8F',
                        'container-background-color' => '#FFFFFF',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                  ),
                ),
              ),
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/easter-bunny-easter-eggs.jpg',
            ),
            array(
              'id'              => 43,
              'is_pro'          => true,
              'emailCategories' => ['Re-Engagement'],
              'industry'        => ['E-commerce & Retail'],
              'title'           => 'Re-engagement Campaign',
              'json_content'    => [],
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/re-engagement-campaign.jpg',
            ),
            array(
              'id'              => 44,
              'is_pro'          => false,
              'emailCategories' => ['Welcome'],
              'industry'        => ['Others'],
              'title'           => 'Confirm your subscription',
              'json_content'    => array (
                'subTitle' => 'Nice to meet you!',
                'content' => 
                array (
                  'type' => 'page',
                  'data' => 
                  array (
                    'value' => 
                    array (
                      'breakpoint' => '480px',
                      'headAttributes' => '',
                      'font-size' => '14px',
                      'font-weight' => '400',
                      'line-height' => '1.7',
                      'headStyles' => 
                      array (
                      ),
                      'fonts' => 
                      array (
                      ),
                      'responsive' => true,
                      'font-family' => 'Arial',
                      'text-color' => '#000000',
                    ),
                  ),
                  'attributes' => 
                  array (
                    'background-color' => '#efeeea',
                    'width' => '600px',
                  ),
                  'children' => 
                  array (
                    0 => 
                    array (
                      'type' => 'advanced_image',
                      'data' => 
                      array (
                        'value' => 
                        array (
                        ),
                      ),
                      'attributes' => 
                      array (
                        'align' => 'center',
                        'height' => 'auto',
                        'padding' => '16px 0px 16px 0px',
                        'src' => $image_path . 'your-logo.png',
                        'width' => '100%',
                        'container-background-color' => '#fff',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    1 => 
                    array (
                      'type' => 'advanced_wrapper',
                      'data' => 
                      array (
                        'value' => 
                        array (
                        ),
                      ),
                      'attributes' => 
                      array (
                        'background-color' => '#9259F3',
                        'padding' => '24px 24px 0px 24px',
                        'border' => 'none',
                        'direction' => 'ltr',
                        'text-align' => 'center',
                      ),
                      'children' => 
                      array (
                        0 => 
                        array (
                          'type' => 'advanced_hero',
                          'data' => 
                          array (
                            'value' => 
                            array (
                            ),
                          ),
                          'attributes' => 
                          array (
                            'background-color' => '#ffffff',
                            'background-position' => 'center center',
                            'mode' => 'fluid-height',
                            'padding' => '0px 0px 0px 0px',
                            'vertical-align' => 'top',
                            'background-url' => '',
                          ),
                          'children' => 
                          array (
                            0 => 
                            array (
                              'type' => 'advanced_image',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'height' => 'auto',
                                'padding' => '39px 0px 0px 0px',
                                'src' => $image_path . 'confirmation-email-hero-img.png',
                                'width' => '258px',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            1 => 
                            array (
                              'type' => 'text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => '<font color="#0b1b1b">Confirm your subscription</font>',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '26px 0px 0px 0px',
                                'align' => 'center',
                                'color' => '#1F1F2D',
                                'font-size' => '30px',
                                'line-height' => '1.33',
                                'font-weight' => '800',
                                'font-style' => 'normal',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            2 => 
                            array (
                              'type' => 'advanced_text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'Dear {{contact.firstName}},',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '50px 0px 0px 40px',
                                'align' => 'left',
                                'font-style' => 'normal',
                                'font-size' => '16px',
                                'line-height' => '1',
                                'font-weight' => '400',
                                'color' => '#0B1B1B',
                                'font-family' => 'Arial',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            3 => 
                            array (
                              'type' => 'advanced_text',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'You\'ve received this message because you subscribed to Mail Mint. Please confirm your subscription to receive emails from us
              If you received this email by mistake, simply delete it. You won\'t receive any more emails from us unless you confirm your subscription.<br>',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '20px 02px 0px 40px',
                                'align' => 'left',
                                'font-size' => '16px',
                                'line-height' => '1.75',
                                'font-weight' => '400',
                                'font-style' => 'normal',
                                'color' => '#0B1B1B',
                                'font-family' => 'Arial',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                            4 => 
                            array (
                              'type' => 'advanced_button',
                              'data' => 
                              array (
                                'value' => 
                                array (
                                  'content' => 'Confirm my subscription',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'align' => 'center',
                                'font-family' => 'Arial',
                                'background-color' => '#9259F3',
                                'color' => '#ffffff',
                                'font-weight' => '700',
                                'font-style' => 'normal',
                                'border-radius' => '10px',
                                'padding' => '40px 10px 40px 10px',
                                'inner-padding' => '15px 30px 15px 30px',
                                'font-size' => '18px',
                                'line-height' => '1.2',
                                'target' => '_blank',
                                'vertical-align' => 'middle',
                                'border' => 'none',
                                'text-align' => 'center',
                                'letter-spacing' => 'normal',
                                'href' => '{{link.subscribe}}',
                                'width' => '',
                              ),
                              'children' => 
                              array (
                              ),
                            ),
                          ),
                        ),
                        1 => 
                        array (
                          'type' => 'advanced_social',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'elements' => 
                              array (
                                0 => 
                                array (
                                  'href' => '#',
                                  'target' => '_blank',
                                  'src' => $image_path . $pinterest,
                                  'content' => '',
                                ),
                                1 => 
                                array (
                                  'href' => '#',
                                  'target' => '_blank',
                                  'src' => $image_path . $facebook,
                                  'content' => '',
                                ),
                                2 => 
                                array (
                                  'href' => '#',
                                  'target' => '_blank',
                                  'src' => $image_path . $instagram,
                                  'content' => '',
                                ),
                                3 => 
                                array (
                                  'href' => '#',
                                  'target' => '_blank',
                                  'src' => $image_path . $twitter,
                                  'content' => '',
                                ),
                              ),
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'color' => '',
                            'mode' => 'horizontal',
                            'font-size' => '',
                            'font-weight' => 'normal',
                            'font-style' => 'normal',
                            'font-family' => 'Arial',
                            'border-radius' => '',
                            'padding' => '30px 0px 0px 0px',
                            'inner-padding' => '0px 0px 0px 20px',
                            'line-height' => '',
                            'text-padding' => '4px 4px 4px 0px',
                            'icon-padding' => '0px',
                            'icon-size' => '40px',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        2 => 
                        array (
                          'type' => 'advanced_divider',
                          'data' => 
                          array (
                            'value' => 
                            array (
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'border-width' => '1px',
                            'border-style' => 'solid',
                            'border-color' => '#A776FB',
                            'padding' => '30px 24px 0px 24px',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          'type' => 'advanced_text',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => 'No longer want to be Mail Mint friends?',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '40px 0px 0px 0px',
                            'align' => 'center',
                            'color' => 'rgba(255, 255, 255, 0.60)',
                            'font-size' => '15px',
                            'font-weight' => '400',
                            'font-style' => 'normal',
                            'line-height' => '1.46',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        4 => 
                        array (
                          'type' => 'advanced_text',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => '<a href="{{link.preference}}" target="_blank" style="color: inherit; text-decoration: underline;">Email Preference</a>&nbsp; .&nbsp;&nbsp;<a href="{{link.unsubscribe}}" target="_blank" style="color: inherit; text-decoration: underline;">Unsubscribe</a>',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '8px 0px 0px 0px',
                            'align' => 'center',
                            'color' => 'rgba(255, 255, 255, 0.60)',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        5 => 
                        array (
                          'type' => 'advanced_text',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => '© ' . date("Y") . ', ' . $busi_name . ', ' . $address,
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '24px 0px 30px 0px',
                            'align' => 'center',
                            'color' => 'rgba(255, 255, 255, 0.60)',
                            'font-size' => '14px',
                            'font-family' => 'Arial',
                            'line-height' => '1.57',
                            'font-style' => 'normal',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              'html_content'    => '',
              'thumbnail_image' => $image_path . '/thumbnails/opt-in.jpg',
          ),
			)
		);
	}
}
