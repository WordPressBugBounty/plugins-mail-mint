<?php
/**
 * Create class object of FormAction and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

/**
 * Class CookieActionCreator
 */
class CookieActionCreator extends ActionCreator {

	/**
	 * Create CookieAction instance
	 *
	 * @return CookieAction CookieAction object
	 * @since 1.0.0
	 */
	public function makeAction() {
		return new CookieAction();
	}
}
