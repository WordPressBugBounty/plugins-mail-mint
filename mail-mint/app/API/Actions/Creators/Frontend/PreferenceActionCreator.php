<?php
/**
 * Create class object of FormAction and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

/**
 * Class PreferenceActionCreator
 */
class PreferenceActionCreator extends ActionCreator {

	/**
	 * Create PreferenceAction instance
	 *
	 * @return PreferenceAction PreferenceAction object
	 * @since 1.0.0
	 */
	public function makeAction() {
		return new PreferenceAction();
	}
}
