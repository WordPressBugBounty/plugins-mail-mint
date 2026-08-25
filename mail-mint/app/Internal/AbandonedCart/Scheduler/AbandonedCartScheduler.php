<?php
/**
 * Class AbandonedCartScheduler
 *
 * Represents the AbandonedCart class responsible for initializing and managing CartScheduler related to abandoned carts.
 *
 * @package Mint\MRM\Internal\AbandonedCart
 * @since 1.5.0
 */

namespace Mint\MRM\Internal\AbandonedCart\Scheduler;

use  Mint\MRM\Scheduler\AbstractActionScheduler;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action scheduler
 *
 * @package MintMail\App\Internal\Automation;
 */
class AbandonedCartScheduler extends AbstractActionScheduler {
	/**
	 * Private constant representing the group ID for the abandoned cart functionality.
	 *
	 * This constant is assigned the value of the `MINT_ABANDONED_CART_GROUP` constant,
	 * which represents the group name for the abandoned cart functionality in the Mint system.
	 * It is used internally within the class or scope to reference the abandoned cart group ID.
	 *
	 * @since 1.5.0
	 */
	private const GROUPID = MINT_ABANDONED_CART_GROUP;



}
