<?php
/**
 * Create class object of FormAction and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

/**
 * Class FormActionCreator
 */
class FormActionCreator extends ActionCreator {

	/**
	 * Create FormAction instance
	 *
	 * @return FormAction FormAction object
	 * @since 1.0.0
	 */
	public function makeAction() {
		return new FormAction();
	}
}
