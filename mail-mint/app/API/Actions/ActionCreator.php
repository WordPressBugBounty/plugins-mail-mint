<?php
/**
 * An Interface that is responsible for make an action object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

/**
 * Class ActionCreator
 */
abstract class ActionCreator {

	/**
	 * Create action instance and return object
	 *
	 * @return Action Action object
	 * @since 1.0.0
	 */
	abstract public function makeAction(); //phpcs:ignore
}
