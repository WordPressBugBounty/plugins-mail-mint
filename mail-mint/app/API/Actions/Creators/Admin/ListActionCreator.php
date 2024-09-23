<?php
/**
 * Create class object of ListActions and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

/**
 * Class ListActionCreator
 */
class ListActionCreator extends ActionCreator {

	/** 
 	 * Returns a new instance of the ListActions class, 
	 * which is used to perform actions on a list of items.
 	 * 
 	 * @return ListActions A new instance of the ListActions class. 
 	 * @since 1.0.0 
 	 */ 
	public function makeAction() {
		return new ListActions();
	}
}