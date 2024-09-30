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
          array(
            'id'              => 45,
            'is_pro'          => false,
            'emailCategories' => ['Re-Engagement'],
            'industry'        => ['Others'],
            'title'           => 'Join Us on Social Media',
            'json_content'    =>  array (
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
                        'padding' => '17px 0px 17px 0px',
                        'src' => 'data:image/png;base64,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',
                        'width' => '100%',
                        'container-background-color' => '#FFFFFF',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    1 => 
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
                        'padding' => '0px 24px 40px 24px',
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
                              'content' => 'Are we friends on social media?',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '0px 0px 0px 0px',
                            'align' => 'center',
                            'color' => '#000000',
                            'font-size' => '38px',
                            'line-height' => '48px',
                            'font-weight' => '800',
                            'font-family' => 'Arial',
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
                            'padding' => '20px 0px 0px 0px',
                            'src' => $image_path . 'join-social-media/social-media-add-hero-post.jpeg',
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
                              'content' => 'Stay connected and never miss an update! Follow us on social media to get the latest tips, exclusive content, and behind-the-scenes looks at what we’re creating. Let’s build a community where we can share ideas and inspire each other.',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'background-color' => '#414141',
                            'color' => '#2B2D38',
                            'font-weight' => 'normal',
                            'border-radius' => '3px',
                            'padding' => '14px 0px 30px 14px',
                            'inner-padding' => '10px 25px 10px 25px',
                            'line-height' => '30px',
                            'target' => '_blank',
                            'vertical-align' => 'middle',
                            'border' => 'none',
                            'text-align' => 'center',
                            'href' => '#',
                            'font-size' => '18px',
                            'font-family' => 'Arial',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        3 => 
                        array (
                          'type' => 'button',
                          'data' => 
                          array (
                            'value' => 
                            array (
                              'content' => 'Connect With Us',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'background-color' => '#2B2D38',
                            'color' => '#ffffff',
                            'font-size' => '13px',
                            'font-weight' => 'normal',
                            'border-radius' => '30px',
                            'padding' => '0px 0px 0px 0px',
                            'inner-padding' => '16px 30px 16px 30px',
                            'line-height' => '120%',
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
                      ),
                    ),
                    2 => 
                    array (
                      'type' => 'advanced_text',
                      'data' => 
                      array (
                        'value' => 
                        array (
                          'content' => '<div style="text-align: center;"><span style="word-spacing: normal;"><font color="#1a30eb">Connect with us</font></span></div>',
                        ),
                      ),
                      'attributes' => 
                      array (
                        'padding' => '20px 5px 10px 5px',
                        'align' => 'left',
                        'container-background-color' => '#FFFFFF',
                        'font-size' => '25px',
                        'font-weight' => 'bold',
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
                          'content' => '<div style="text-align: center;"><span style="word-spacing: normal;"><font color="#9b9b9b">We promise to share only cool stuff! 🤞🏻</font></span></div>',
                        ),
                      ),
                      'attributes' => 
                      array (
                        'padding' => '0px 5px 20px 5px',
                        'align' => 'left',
                        'container-background-color' => '#FFFFFF',
                        'font-size' => '20px',
                        'font-weight' => 'bold',
                      ),
                      'children' => 
                      array (
                      ),
                    ),
                    4 => 
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
                        'padding' => '30px 0px 30px 0px',
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
                                'padding' => '10px 0px 0px 10px',
                                'src' => $image_path . 'join-social-media/tech-post.jpeg',
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
                                  'content' => '<font color="#4a90e2">&gt;</font> Get to know the latest tech news on Twitter
              <div><br></div><div><span style="color: #1a30eb;">&gt;</span> Shop directly on our Instagram page<br></div><div><br></div><div><span style="color: #1a30eb;">&gt; </span>Stay tuned about our deals and promotions on Facebook<br></div>',
                                ),
                              ),
                              'attributes' => 
                              array (
                                'padding' => '10px 25px 10px 25px',
                                'align' => 'left',
                                'font-size' => '18px',
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
                        'padding' => '20px 0px 30px 0px',
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
                              'content' => '<font color="#9b9b9b">Search for our profile</font>',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '10px 25px 10px 25px',
                            'align' => 'center',
                            'color' => '#000000',
                            'font-size' => '26px',
                            'line-height' => '45px',
                            'font-weight' => 'bold',
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
                              'content' => '@TopTech',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'background-color' => '#414141',
                            'color' => '#000000',
                            'font-weight' => 'bold',
                            'border-radius' => '3px',
                            'padding' => '10px 25px 10px 25px',
                            'inner-padding' => '10px 25px 10px 25px',
                            'line-height' => '1.5',
                            'target' => '_blank',
                            'vertical-align' => 'middle',
                            'border' => 'none',
                            'text-align' => 'center',
                            'href' => '#',
                            'font-size' => '20px',
                          ),
                          'children' => 
                          array (
                          ),
                        ),
                        2 => 
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
                            'padding' => '0px 0px 0px 10px',
                            'src' => $image_path . 'join-social-media/heart-sign.gif',
                            'width' => '100%',
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
                              'content' => 'Connect With Us',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'align' => 'center',
                            'font-family' => 'Arial',
                            'background-color' => '#2B2D38',
                            'color' => '#ffffff',
                            'font-weight' => 'normal',
                            'font-style' => 'normal',
                            'border-radius' => '30px',
                            'padding' => '10px 5px 10px 5px',
                            'inner-padding' => '16px 30px 16px 30px',
                            'font-size' => '13px',
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
                    6 => 
                    array (
                      'type' => 'advanced_footer',
                      'data' => 
                      array (
                        'value' => 
                        array (
                        ),
                      ),
                      'attributes' => 
                      array (
                        'background-color' => '#F1F1F1',
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
                                  'src' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAMAAAC7IEhfAAAAclBMVEUAAAA7WZg7Wpg6WZg8WJdAU586Wpg6Wpc7WZg8WZk6WZg7WZg6WZg7WZY5WJc6Wpc7WJg7WZk7W5Y6WpU7WZj////m6vKdrMvO1eW2wdhUbaWEl7+El75sgrKpt9KRosV4jbhTbqXy9fiQocVsgrFHY58FMJF5AAAAFHRSTlMAv++AIBCfQJBQ39+wcHBg0M9wMJn76TsAAAEYSURBVDjLjdTZbsMgEEDRYcziPU2LCa3rNOny/79YnESaDAaZ+2gdgQEB8JR51SLUV42CfEagpcQhw5rWRnUpKoWliMrNcGjTVdxVNlu94yhNrrFRH6G/7ewSmVr8tPZOX8wD8vWeAopgp27uwNx1IhhN3jJ4TkFU25X4Fbl5npd4N3sO3QrPNgoDtAl4snEGTBkc+aH8ej+tOe/9F4Oa/+LnRHHYgcjBC4OYh4tlcXhx7oa+nXPXGJatGkGXwSPUZVBAUwYrUKVHCKIEdgAwlkAdoMICKOmu5iHd2HYPorxDswcHeDTmIX9Uno/Hu9DPs3tREEmKu7LnTKvMe8vDATbJKjGchFRyYKNizRjvbeiPGEzb1wZY/0BgYWSlhMOpAAAAAElFTkSuQmCC',
                                  'content' => '',
                                ),
                                1 => 
                                array (
                                  'href' => '#',
                                  'target' => '_blank',
                                  'src' => 'data:image/png;base64,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',
                                  'content' => '',
                                ),
                                2 => 
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
                            'padding' => '15px 0px 15px 0px',
                            'inner-padding' => '0px 20px 0px 0px',
                            'line-height' => '1.6',
                            'text-padding' => '4px 4px 4px 0px',
                            'icon-padding' => '0px',
                            'icon-size' => '25px',
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
                            'border-width' => '1px',
                            'border-style' => 'solid',
                            'border-color' => '#D3CFD8',
                            'padding' => '0px 24px 15px 24px',
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
                              'content' => '{{business.name}}',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '10px 25px 10px 25px',
                            'align' => 'center',
                            'font-size' => '16px',
                            'line-height' => '15px',
                            'font-weight' => '500',
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
                              'content' => '{{business.address_line_1}}<br><div>{{business.country}}<br></div>',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '12px 0px 12px 0px',
                            'align' => 'center',
                            'font-size' => '14px',
                            'line-height' => '20px',
                            'color' => '#908A99',
                            'font-family' => 'Arial',
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
                              'content' => '<a href="{{link.preference}}" target="_blank" style="text-decoration: underline;"><font color="#0064ff">Update Preference</font></a> . <a href="{{link.unsubscribe}}" target="_blank" style="text-decoration: underline;"><font color="#0064ff">Unsubscribe</font></a>',
                            ),
                          ),
                          'attributes' => 
                          array (
                            'padding' => '10px 25px 10px 25px',
                            'align' => 'center',
                            'font-size' => '10px',
                            'font-family' => 'Arial',
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
            'thumbnail_image' => $image_path . '/thumbnails/join-us-on-social-media.png',
          ),
          array(
            'id'              => 46,
            'is_pro'          => true,
            'emailCategories' => ['Abandoned Cart Recovery'],
            'industry'        => ['E-commerce & Retail'],
            'title'           => 'Abandoned Cart Recovery 3',
            'json_content'    => [],
            'html_content'    => '',
            'thumbnail_image' => $image_path . '/thumbnails/abandoned-cart-4.png',
          ),
          array(
            'id'              => 47,
            'is_pro'          => true,
            'emailCategories' => ['Download Emails'],
            'industry'        => ['Others'],
            'title'           => 'Download Emails 1',
            'json_content'    => [],
            'html_content'    => '',
            'thumbnail_image' => $image_path . '/thumbnails/download-emails-1.png',
          ),
          array(
            'id'              => 48,
            'is_pro'          => false,
            'emailCategories' => ['Download Emails'],
            'industry'        => ['Others'],
            'title'           => 'Download Emails 2',
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
                      'padding' => '0px 0px 40px 0px',
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
                          'padding' => '0px 0px 0px 0px',
                          'src' => $image_path . 'recurring-revenue.webp',
                          'alt' => 'Default Image',
                          'container-background-color' => '#01516e',
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
                            'content' => 'Recurring revenue: The secret to scaling your agency',
                          ),
                        ),
                        'attributes' => 
                        array (
                          'padding' => '20px 10px 20px 10px',
                          'align' => 'center',
                          'color' => '#000000',
                          'font-size' => '34px',
                          'line-height' => '1.2',
                          'font-weight' => '800',
                          'font-family' => 'Arial',
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
                            'content' => '<div style="text-align: center;"><span style="word-spacing: normal;"><i>Focus on the projects you want instead of the projects you need</i></span></div>',
                          ),
                        ),
                        'attributes' => 
                        array (
                          'padding' => '10px 10px 10px 10px',
                          'align' => 'left',
                          'font-size' => '14px',
                        ),
                        'children' => 
                        array (
                        ),
                      ),
                      3 => 
                      array (
                        'type' => 'text',
                        'data' => 
                        array (
                          'value' => 
                          array (
                            'content' => 'By offering up services that your clients continue to pay for over time, you\'re creating an income stream that is stable, predictable, and super reliable. Recurring revenue will allow your agency to maintain a healthy cash flow and work in a more stress-free environment. In four chapters, we’ll cover four different ways your agency can start earning recurring revenue and actionable steps to start implementing those strategies.',
                          ),
                        ),
                        'attributes' => 
                        array (
                          'align' => 'center',
                          'background-color' => '#414141',
                          'color' => '#2B2D38',
                          'font-weight' => 'normal',
                          'border-radius' => '3px',
                          'padding' => '14px 10px 30px 10px',
                          'inner-padding' => '10px 25px 10px 25px',
                          'line-height' => '1.5',
                          'target' => '_blank',
                          'vertical-align' => 'middle',
                          'border' => 'none',
                          'text-align' => 'center',
                          'href' => '#',
                          'font-size' => '16px',
                          'font-family' => 'Arial',
                        ),
                        'children' => 
                        array (
                        ),
                      ),
                      4 => 
                      array (
                        'type' => 'button',
                        'data' => 
                        array (
                          'value' => 
                          array (
                            'content' => 'Download It Now',
                          ),
                        ),
                        'attributes' => 
                        array (
                          'align' => 'center',
                          'background-color' => '#01516e',
                          'color' => '#ffffff',
                          'font-size' => '14px',
                          'font-weight' => 'normal',
                          'border-radius' => '14px',
                          'padding' => '0px 0px 0px 0px',
                          'inner-padding' => '16px 30px 16px 30px',
                          'line-height' => '120%',
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
                    ),
                  ),
                  1 => 
                  array (
                    'type' => 'advanced_footer',
                    'data' => 
                    array (
                      'value' => 
                      array (
                      ),
                    ),
                    'attributes' => 
                    array (
                      'background-color' => '#000000',
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
                                'src' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACgAAAAoCAMAAAC7IEhfAAAAclBMVEUAAAA7WZg7Wpg6WZg8WJdAU586Wpg6Wpc7WZg8WZk6WZg7WZg6WZg7WZY5WJc6Wpc7WJg7WZk7W5Y6WpU7WZj////m6vKdrMvO1eW2wdhUbaWEl7+El75sgrKpt9KRosV4jbhTbqXy9fiQocVsgrFHY58FMJF5AAAAFHRSTlMAv++AIBCfQJBQ39+wcHBg0M9wMJn76TsAAAEYSURBVDjLjdTZbsMgEEDRYcziPU2LCa3rNOny/79YnESaDAaZ+2gdgQEB8JR51SLUV42CfEagpcQhw5rWRnUpKoWliMrNcGjTVdxVNlu94yhNrrFRH6G/7ewSmVr8tPZOX8wD8vWeAopgp27uwNx1IhhN3jJ4TkFU25X4Fbl5npd4N3sO3QrPNgoDtAl4snEGTBkc+aH8ej+tOe/9F4Oa/+LnRHHYgcjBC4OYh4tlcXhx7oa+nXPXGJatGkGXwSPUZVBAUwYrUKVHCKIEdgAwlkAdoMICKOmu5iHd2HYPorxDswcHeDTmIX9Uno/Hu9DPs3tREEmKu7LnTKvMe8vDATbJKjGchFRyYKNizRjvbeiPGEzb1wZY/0BgYWSlhMOpAAAAAElFTkSuQmCC',
                                'content' => '',
                              ),
                              1 => 
                              array (
                                'href' => '#',
                                'target' => '_blank',
                                'src' => 'data:image/png;base64,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',
                                'content' => '',
                              ),
                              2 => 
                              array (
                                'href' => '#',
                                'target' => '_blank',
                                'src' => 'data:image/png;base64,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',
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
                          'padding' => '15px 0px 15px 0px',
                          'inner-padding' => '0px 20px 0px 0px',
                          'line-height' => '1.6',
                          'text-padding' => '4px 4px 4px 0px',
                          'icon-padding' => '0px',
                          'icon-size' => '25px',
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
                          'border-width' => '1px',
                          'border-style' => 'solid',
                          'border-color' => '#D3CFD8',
                          'padding' => '0px 24px 15px 24px',
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
                            'content' => '<font color="#ffffff">{{business.name}}</font>',
                          ),
                        ),
                        'attributes' => 
                        array (
                          'padding' => '10px 25px 10px 25px',
                          'align' => 'center',
                          'font-size' => '16px',
                          'line-height' => '15px',
                          'font-weight' => '500',
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
                            'content' => '<font color="#ffffff">© 2024&nbsp;{{business.name}} {{business.address}}</font>',
                          ),
                        ),
                        'attributes' => 
                        array (
                          'padding' => '12px 0px 12px 0px',
                          'align' => 'center',
                          'font-size' => '14px',
                          'line-height' => '20px',
                          'color' => '#908A99',
                          'font-family' => 'Arial',
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
                            'content' => '<a href="{{link.preference}}" target="_blank" style="text-decoration: underline;"><font color="#0064ff">Update Preference</font></a> . <a href="{{link.unsubscribe}}" target="_blank" style="text-decoration: underline;"><font color="#0064ff">Unsubscribe</font></a>',
                          ),
                        ),
                        'attributes' => 
                        array (
                          'padding' => '10px 25px 20px 25px',
                          'align' => 'center',
                          'font-size' => '10px',
                          'font-family' => 'Arial',
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
            'thumbnail_image' => $image_path . '/thumbnails/download-emails-2.png',
          ),
          array(
            'id'              => 49,
            'is_pro'          => true,
            'emailCategories' => ['Welcome'],
            'industry'        => ['Others'],
            'title'           => 'Podcast Welcome',
            'json_content'    => [],
            'html_content'    => '',
            'thumbnail_image' => $image_path . '/thumbnails/podcast-welcome.png',
          ),
          array(
            'id'              => 50,
            'is_pro'          => true,
            'emailCategories' => ['Deals & Offers'],
            'industry'        => ['Fashion & Jewelry'],
            'title'           => 'Summer Collection Offers',
            'json_content'    => [],
            'html_content'    => '',
            'thumbnail_image' => $image_path . '/thumbnails/summer-collection-deals.png',
          ),
          array(
            'id'              => 51,
            'is_pro'          => true,
            'emailCategories' => ['Deals & Offers'],
            'industry'        => ['Fashion & Jewelry'],
            'title'           => 'E-Commerce Newsletter',
            'json_content'    => [],
            'html_content'    => '',
            'thumbnail_image' => $image_path . '/thumbnails/ecommerce-newsletter.png',
          ),
			)
		);
	}
}
