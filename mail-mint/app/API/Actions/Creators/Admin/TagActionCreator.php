<?php
/**
 * Create class object of TagActions and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

/**
 * Class TagActionCreator
 * 
 * @since 1.0.0 
 */
class TagActionCreator extends ActionCreator {

	/** 
 	 * Returns a new instance of the TagActions class, 
	 * which is used to perform actions on a tag of items.
 	 * 
 	 * @return TagActions A new instance of the TagActions class. 
 	 * @since 1.0.0 
 	 */ 
	public function makeAction() {
		return new TagActions();
	}
}