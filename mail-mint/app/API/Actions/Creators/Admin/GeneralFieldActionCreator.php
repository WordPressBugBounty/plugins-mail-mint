<?php
/**
 * Create class object of GeneralFieldActions and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

use GeneralFieldActions;

/**
 * Class GeneralFieldActionCreator
 */
class GeneralFieldActionCreator extends ActionCreator {

	/** 
 	 * Returns a new instance of the GeneralFieldActions class, 
	 * which is used to perform actions on a contact general fields of items.
 	 * 
 	 * @return GeneralFieldActions A new instance of the GeneralFieldActions class. 
 	 * @since 1.5.0 
 	 */ 
	public function makeAction() {
		return new GeneralFieldActions();
	}
}