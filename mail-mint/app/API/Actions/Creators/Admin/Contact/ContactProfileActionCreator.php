<?php
/**
 * Create class object of ContactProfileAction and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

use ContactProfileAction;

/**
 * Class ContactProfileActionCreator
 *
 * Summary: Contact Profile Action Creator.
 * Description: Extends the ActionCreator class to create instances of the ContactProfileAction class for performing actions on contact profile.
 *
 * @since 1.4.9
 */
class ContactProfileActionCreator extends ActionCreator {

	/** 
 	 * Returns a new instance of the ContactProfileAction class, 
	 * which is used to perform actions on contact profile.
 	 * 
 	 * @return ContactProfileAction A new instance of the ContactProfileAction class. 
 	 * @since 1.4.9 
 	 */ 
	public function makeAction() {
		return new ContactProfileAction();
	}
}