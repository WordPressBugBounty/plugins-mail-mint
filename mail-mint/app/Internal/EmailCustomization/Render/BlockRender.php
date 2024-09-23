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

use DateInterval;

/**
 * Class BlockRender
 *
 * This class is responsible for rendering email templates with dynamic content.
 *
 * @since 1.10.0
 */
class BlockRender {

	public function __construct() {
		// Constructor.
	}

	/**
	 * Renders the order details block in the email template.
	 *
	 * This method is responsible for rendering the order details block in the email template.
	 * It retrieves the order details from the order object and updates the content of the block
	 * with the actual order details.
	 *
	 * @param string $content The content of the email template.
	 * @param object $order The order object.
	 * @param array  $order_blocks The order blocks in the email template.
	 * @param object $dom The DOMDocument object.
	 * @return string The updated content of the email template.
	 * @since 1.10.0
	 */
	public function render_order_details( $content, $order, $order_blocks, $dom ) {
		$output               = '';
		$first_child_content  = '';
		$second_child_content = '';
		$third_child_content  = '';
		$fourth_child_content = '';
		$fifth_child_content  = '';

		// Iterate through cart blocks to adjust the layout.
		foreach ( $order_blocks as $order_block ) {
			$first_child  = $order_block->firstChild; //phpcs:ignore
			$second_child = $order_block->childNodes->item( 1 );
			$third_child  = $order_block->childNodes->item( 2 );
			$fourth_child = $order_block->childNodes->item( 3 );
			$fifth_child  = $order_block->childNodes->item( 4 );
			$sixth_child  = $order_block->childNodes->item( 5 );

			if ( null !== $first_child ) {
				$first_child_content = $dom->saveHTML( $first_child );
			}

			if ( null !== $second_child ) {
				$second_child_content = $dom->saveHTML( $second_child );
			}

			if ( null !== $third_child ) {
				$third_child_content = $dom->saveHTML( $third_child );
			}

			if ( null !== $fourth_child ) {
				$fourth_child_content = $dom->saveHTML( $fourth_child );
			}

			if ( null !== $fifth_child ) {
				$fifth_child_content = $dom->saveHTML( $fifth_child );
			}

			if ( null !== $sixth_child ) {
				$sixth_child_content = $dom->saveHTML( $sixth_child );
			}
		}

		if ( !empty( $order ) && $order instanceof \WC_Order ) {
			$order_items = $order->get_items();
			foreach ( $order_items as $item_id => $item ) {
				$product = $item->get_product();
				$doc     = new \DOMDocument();
				$doc->loadHTML( $first_child_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
                $doc->substituteEntities = false; // phpcs:ignore
				$elements                = $doc->getElementsByTagName( 'td' );

				// Update the content for product title, price, and image.
				foreach ( $elements as $element ) {
					$has_class = $element->hasAttribute( 'class' );
					$class     = $has_class ? $element->getAttribute( 'class' ) : '';

					if ( $has_class && strpos( $class, 'mint-order-details-item-name' ) !== false ) {
						// Find the text node within the td element and replace its value.
						$element->textContent = htmlspecialchars_decode($product->get_name(), ENT_QUOTES | ENT_HTML5);
					} elseif ( $has_class && strpos( $class, 'mint-order-details-item-quantity' ) !== false ) {
						// Replace the text.
                        $element->nodeValue = $item->get_quantity(); // phpcs:ignore
					} elseif ( $has_class && strpos( $class, 'mint-order-details-item-price' ) !== false ) {
						// Replace the product price.
                        $element->nodeValue = wp_strip_all_tags(wc_price($item->get_total())); // phpcs:ignore
					}
				}

				$current_content = $doc->saveHTML();
				$output         .= $current_content;
			}

			$second_child_content = $this->get_content_node_element( $second_child_content, 'mint-order-details-subtotal', wc_price( $order->get_subtotal() ) );
			$output              .= $second_child_content;

			if ( !empty( $order->get_shipping_total() ) ) {
				$third_child_content = $this->get_content_node_element( $third_child_content, 'mint-order-details-shipping', wc_price( $order->get_shipping_total() ) );
				$output             .= $third_child_content;
			}

			if ( wc_tax_enabled() && $order->get_total_tax() > 0 ) {
				$fourth_child_content = $this->get_content_node_element( $fourth_child_content, 'mint-order-details-tax', wc_price( $order->get_total_tax() ) );
				$output              .= $fourth_child_content;
			}

			$fifth_child_content = $this->get_content_node_element( $fifth_child_content, 'mint-order-details-payment-method', ( $order instanceof \WC_Order ) ? esc_html( $order->get_payment_method_title() ) : 'Paypal' );
			$output             .= $fifth_child_content;

			$sixth_child_content = $this->get_content_node_element( $sixth_child_content, 'mint-order-details-total', wc_price( $order->get_total() ) );
			$output             .= $sixth_child_content;

			foreach ( $order_blocks as $index => $order_block ) {
				// Remove the original content
				while ( $order_block->firstChild ) {
					$order_block->removeChild( $order_block->firstChild );
				}

				// Create a new DOMDocument for the updated content
				$new_doc = new \DOMDocument();
				@$new_doc->loadHTML( $output );

				// Import the nodes of the updated content to the original DOMDocument
				foreach ( $new_doc->getElementsByTagName( 'body' )->item( 0 )->childNodes as $node ) {
					$order_block->appendChild( $dom->importNode( $node, true ) );
				}
			}
		}

		// Clean up and return the updated email body.
		$content = $dom->saveHTML();
		$content = str_replace( '%7B%7B', '{{', $content );
		$content = str_replace( '%7D%7D', '}}', $content );
		return $content;
	}

	/**
	 * Retrieves the content node element.
	 *
	 * This method retrieves the content node element from the email template and updates its value.
	 *
	 * @param string $element The content of the email template.
	 * @param string $class_name The class name of the content node element.
	 * @param string $new_value The new value for the content node element.
	 * @return string The updated content of the email template.
	 * @since 1.10.0
	 */
	private function get_content_node_element( $element, $class_name, $new_value ) {
		$doc = new \DOMDocument();
		@$doc->loadHTML( $element );
		$finder = new \DomXPath( $doc );
		$node   = $finder->query( "//*[contains(@class, '$class_name')]" )->item( 0 );

		if ( $node ) {
			$node->nodeValue = wp_strip_all_tags( $new_value );
		}

		// Save the changes back to the HTML.
		return $doc->saveHTML();
	}

	/**
	 * Renders the billing address block in the email template.
	 *
	 * This method is responsible for rendering the billing address block in the email template.
	 * It retrieves the billing address from the order object and updates the content of the block
	 * with the actual billing address.
	 *
	 * @param string $content The content of the email template.
	 * @param object $order The order object.
	 * @param array  $billing_blocks The billing address blocks in the email template.
	 * @param object $dom The DOMDocument object.
	 * @return string The updated content of the email template.
	 * @since 1.10.0
	 */
	public function render_order_billing_address( $content, $order, $billing_blocks, $dom ) {
		// Load the HTML content into a DOMDocument.
		$dom = new \DOMDocument();
		@$dom->loadHTML( $content );

		// Create a DOMXPath object to query the DOMDocument.
		$xpath = new \DOMXPath( $dom );

		// Find elements with the class 'mint-billing-address-first-name'.
		$elements = $dom->getElementsByTagName( 'span' );
		// Loop through each found element and replace its value.
		foreach ( $elements as $element ) {
			if ( $element->getAttribute( 'class' ) === 'mint-billing-address-first-name' ) {
				// Replace the innerHTML of the span with the billing address.
				$element->nodeValue = '';
				$addressLines       = explode( '<br/>', $order->get_formatted_billing_address() );
				foreach ( $addressLines as $line ) {
					$element->appendChild( $dom->createTextNode( $line ) );
					$element->appendChild( $dom->createElement( 'br' ) );
				}
			}
		}

		// Get the updated HTML content.
		$content = $dom->saveHTML();
		$content = str_replace( '%7B%7B', '{{', $content );
		$content = str_replace( '%7D%7D', '}}', $content );
		return $content;
	}

	/**
	 * Renders the shipping address block in the email template.
	 *
	 * This method is responsible for rendering the shipping address block in the email template.
	 * It retrieves the shipping address from the order object and updates the content of the block
	 * with the actual shipping address.
	 *
	 * @param string $content The content of the email template.
	 * @param object $order The order object.
	 * @param array  $shipping_blocks The shipping address blocks in the email template.
	 * @param object $dom The DOMDocument object.
	 * @return string The updated content of the email template.
	 * @since 1.10.0
	 */
	public function render_order_shipping_address( $content, $order, $shipping_blocks, $dom ) {
		// Load the HTML content into a DOMDocument.
		$dom = new \DOMDocument();
		@$dom->loadHTML( $content );

		// Create a DOMXPath object to query the DOMDocument.
		$xpath = new \DOMXPath( $dom );

		// Find elements with the class 'mint-billing-address-first-name'.
		$elements = $dom->getElementsByTagName( 'span' );
		// Loop through each found element and replace its value.
		foreach ( $elements as $element ) {
			if ( $element->getAttribute( 'class' ) === 'mint-shipping-address-first-name' ) {
				// Replace the innerHTML of the span with the billing address.
				$element->nodeValue = '';
				$addressLines       = explode( '<br/>', $order->get_formatted_shipping_address() );
				foreach ( $addressLines as $line ) {
					$element->appendChild( $dom->createTextNode( $line ) );
					$element->appendChild( $dom->createElement( 'br' ) );
				}
			}
		}

		// Get the updated HTML content.
		$content = $dom->saveHTML();
		$content = str_replace( '%7B%7B', '{{', $content );
		$content = str_replace( '%7D%7D', '}}', $content );
		return $content;
	}

	/**
	 * Render downloadable products details in the email content.
	 *
	 * This function processes the email content to replace placeholders with actual 
	 * downloadable product details such as product name, expiry date, and download link.
	 *
	 * @param string   $content The original HTML content.
	 * @param WC_Order $order   The WooCommerce order object.
	 * @param DOMNodeList $blocks The list of DOM elements to be processed.
	 * @param DOMDocument $dom The DOMDocument instance.
	 *
	 * @return string The modified HTML content with downloadable product details.
	 * @since 1.14.0
	 */
	public function render_order_downloadable_products( $content, $order, $blocks, $dom ) {
    	$first_child_content = '';
	
		// Iterate through downloadable blocks to adjust the layout.
		foreach ($blocks as $block) {
			$first_child = $block->firstChild;
	
			if (null !== $first_child) {
				$first_child_content = $dom->saveHTML($first_child);
			}
		}

		$output = '';

		if (!empty($order) && $order instanceof \WC_Order) {
			$order_items = $order->get_items();

			foreach ($order_items as $item_id => $item) {
				// Get the product object from the item.
				$product = $item->get_product();
				if ( $product && $product->is_downloadable() ) {
					// Get the downloadable files.
					$downloads = $product->get_downloads();
					if ( empty( $downloads ) ) {
						continue;
					}

					// Calculate expiry date for each download.
					$download_expiry_days = $product->get_download_expiry();

					foreach ($downloads as $download_id => $download) {
						$doc = new \DOMDocument();
						$doc->loadHTML($first_child_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
						$doc->substituteEntities = false; // phpcs:ignore
						$elements = $doc->getElementsByTagName('td');

						// Update the content for product name, expiry date, and download link.
						foreach ( $elements as $element ) {
							$has_class = $element->hasAttribute( 'class' );
							$class     = $has_class ? $element->getAttribute( 'class' ) : '';
		
							if ( $has_class && strpos( $class, 'mint-downloadable-item-name' ) !== false ) {
								// Find the 'a' tag within the 'td' element.
								$a_tag = $element->getElementsByTagName('a')->item(0);
        
								if ($a_tag) {
									// Set the new href value.
									$product_name = $product->get_name();
									$new_href     = $product->get_permalink();
									$a_tag->setAttribute('href', $new_href);
									
									// Optionally, set the visible text of the link to the product name.
									$a_tag->textContent = htmlspecialchars_decode($product_name, ENT_QUOTES | ENT_HTML5);
								}
							}

							if ( $has_class && strpos( $class, 'mint-downloadable-item-expires' ) !== false ) {								
								if ( $download_expiry_days > 0 ) {
									$expiry_date = date_i18n( get_option( 'date_format' ), strtotime( "+" . $download_expiry_days . " days" ) );
								} else {
									$expiry_date = 'Never';
								}
								$element->nodeValue = $expiry_date; // phpcs:ignore
							}

							if ( $has_class && strpos( $class, 'mint-downloadable-item-file' ) !== false ) {
								// Find the 'a' tag within the 'td' element.
								$a_tag = $element->getElementsByTagName('a')->item(0);
        
								if ($a_tag) {
									// Set the new href value.
									$a_tag->setAttribute('href', $download['file']);
									
									// Optionally, set the visible text of the link to the product name.
									$a_tag->nodeValue = $download['name'];
								}
							}
						}

						$current_content = $doc->saveHTML();
						$output .= $current_content;
					}
				}
			}

			foreach ($blocks as $index => $block) {
				// Remove the original content
				while ($block->firstChild) {
					$block->removeChild($block->firstChild);
				}

				// Create a new DOMDocument for the updated content
				$new_doc = new \DOMDocument();
				@$new_doc->loadHTML($output);

				// Import the nodes of the updated content to the original DOMDocument
				foreach ($new_doc->getElementsByTagName('body')->item(0)->childNodes as $node) {
					$block->appendChild($dom->importNode($node, true));
				}
			}
		}

		// Save the modified content
		$content = $dom->saveHTML();
		$content = str_replace( '%7B%7B', '{{', $content );
		$content = str_replace( '%7D%7D', '}}', $content );
		return $content;
	}
}
