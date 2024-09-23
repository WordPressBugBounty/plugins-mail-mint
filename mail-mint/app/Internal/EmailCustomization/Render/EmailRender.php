<?php

/**
 * This class is responsible for rendering email templates with dynamic content.
 *
 * @author WPFunnels Team
 * @email support@getwpfunnels.com
 * @create date 2024-03-07 09:30:00
 * @modify date 2024-03-07 11:03:17
 * @package Mint\App\Internal\EmailCustomization\Render
 */

namespace Mint\App\Internal\EmailCustomization\Render;

use DOMDocument;
use DOMXPath;
use Mint\App\Internal\EmailCustomization\Shortcode\WCShortcode;

/**
 * Class EmailRender
 *
 * This class is responsible for rendering email templates with dynamic content.
 *
 * @since 1.10.0
 */
class EmailRender {

	/**
	 * Holds the email structure.
	 *
	 * @var string $email_structure
	 *
	 * @since 1.10.0
	 */
	private $email_structure;

	/**
	 * Holds the order object.
	 *
	 * @var object $order_object
	 *
	 * @since 1.10.0
	 */
	private $order_object;

	/**
	 * Holds the user object.
	 *
	 * @var object $user_object
	 *
	 * @since 1.10.0
	 */
	private $user_object;

	/**
	 * Holds the render type.
	 *
	 * @var string $render_type
	 *
	 * @since 1.10.0
	 */
	private $render_type;

	/**
	 * Holds the WCShortcode.
	 *
	 * @var object $wc_shortcode
	 *
	 * @since 1.10.0
	 */
	private $wc_shortcode;

	/**
	 * Holds the BlockRender.
	 *
	 * @var object $block_render
	 *
	 * @since 1.10.0
	 */
	private $block_render;

	/**
	 * Constructor for the EmailRender class.
	 *
	 * This is the constructor method of the EmailRender class. It's responsible for initializing the EmailRender object.
	 * It sets the email structure, the render type, the order object, and the user object.
	 *
	 * @param array $data An associative array containing the data to initialize the EmailRender object with.
	 * @since 1.10.0
	 */
	public function __construct( $data ) {
		// Initialize properties.
		$this->user_object     = null;
		$this->email_structure = !empty( $data['template'] ) ? $data['template'] : '';
		$this->render_type     = sanitize_text_field( $data['render_type'] );
		$this->wc_shortcode    = new WCShortcode();
		$this->block_render    = new BlockRender();

		// Set order and user objects based on object type.
		if ( 'order' === $data['object_type'] && $data['object'] instanceof \WC_Order ) {
			$this->order_object = $data['object'];
			$this->user_object  = $this->order_object->get_user();
		}

		if ( 'user' === $data['object_type'] && $data['object'] instanceof \WP_User ) {
			$this->user_object = $data['object'];
		}
	}

	/**
	 * Renders the email template.
	 *
	 * This method renders the email template with dynamic content.
	 * It replaces the shortcodes in the email template with the corresponding dynamic content.
	 *
	 * @return string|bool The rendered email template, or false if the email structure is empty.
	 * @since 1.10.0
	 */
	public function render() {
		// Check if the email structure/template is empty.
		if ( empty( $this->email_structure ) ) {
			return false;
		}

		// Set data for WooCommerce shortcodes.
		$this->wc_shortcode->set_data(
			array(
				'user_object'        => $this->user_object,
				'order_object'       => ( !empty( $this->order_object ) ) ? $this->order_object : null,
				'password_reset_key' => true,
			)
		);

		 // Render the email content and store the output
		$output = $this->content_render( $this->email_structure );

		return $output;
	}

	/**
	 * Renders the content of the email.
	 *
	 * This method renders the content of the email with dynamic content.
	 * It replaces the shortcodes in the email content with the corresponding dynamic content.
	 *
	 * @param string $content The content of the email.
	 * @return string The rendered content of the email.
	 * @since 1.10.0
	 */
	private function content_render( $content ) {
		// Return the original content if DOMDocument class is not available.
		if ( !class_exists( 'DOMDocument' ) ) {
			return $content;
		}

		libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$dom->loadHTML( $content );
		libxml_clear_errors();

		// Query for order detail blocks within the email content.
		$xpath        = new DOMXPath( $dom );
		$order_blocks = $xpath->query( '//tbody[contains(@class, "mint-order-details-grid-block")]' );

		if ( $order_blocks->length > 0 ) {
			$content = $this->block_render->render_order_details( $content, $this->order_object, $order_blocks, $dom );
		}

		// Check for downloadable products and render downloadable blocks if they exist.
		if ( $this->order_has_downloadable_products( $this->order_object ) ) {
			$downloadable_blocks = $xpath->query('//tbody[contains(@class, "mint-downloadable-order-details-block")]');
			if ( $downloadable_blocks->length > 0 ) {
				$content = $this->block_render->render_order_downloadable_products( $content, $this->order_object, $downloadable_blocks, $dom );
			}
		} else {
			// Remove divs with the class 'downloadable-block-wrapper' if no downloadable products
			$downloadable_block_wrappers = $xpath->query('//div[contains(@class, "downloadable-block-wrapper")]');

			// Remove each found div from the document
			foreach ($downloadable_block_wrappers as $node) {
				$node->parentNode->removeChild($node);
			}

			// Save the modified content
			$content = $dom->saveHTML();
		}

		$billing_blocks = $xpath->query( '//div[contains(@class, "mint-billing-address-block")]' );
		if ( $billing_blocks->length > 0 ) {
			$content = $this->block_render->render_order_billing_address( $content, $this->order_object, $billing_blocks, $dom );
		}

		$shipping_blocks = $xpath->query( '//div[contains(@class, "mint-shipping-address-block")]' );
		if ( $shipping_blocks->length > 0 ) {
			$content = $this->block_render->render_order_shipping_address( $content, $this->order_object, $shipping_blocks, $dom );
		}

		// Replace WooCommerce shortcodes in the content.
		$output = $this->wc_shortcode->replace( $content );

		return $output;
	}

	/**
	 * Checks if the WooCommerce order contains downloadable products.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return bool True if the order contains downloadable products, false otherwise.
	 * @since 1.14.0
	 */
	private function order_has_downloadable_products( $order ) {
		// Get the items in the order.
		$items = $order->get_items();

		// Iterate through the items and check if any are downloadable.
		foreach ($items as $item) {
			$product = $item->get_product();
			if ($product && $product->is_downloadable()) {
				return true;
			}
		}

		// No downloadable products found.
		return false;
	}
}
