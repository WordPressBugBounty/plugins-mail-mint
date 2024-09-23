<?php
/**
 * Create class object of ContactImportAction and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

use ContactImportAction;

/**
 * Class ContactImportActionCreator
 *
 * Summary: Contact Import Action Creator.
 * Description: Extends the ActionCreator class to create instances of the ContactImportAction class for performing actions on contact import from MailChimp.
 *
 * @since 1.4.9
 */
class ContactImportActionCreator extends ActionCreator {

	/** 
 	 * Returns a new instance of the ContactImportAction class, 
	 * which is used to perform actions on contact import from MailChimp.
 	 * 
 	 * @return ContactImportAction A new instance of the ContactImportAction class. 
 	 * @since 1.4.9 
 	 */ 
	public function makeAction() {
		return new ContactImportAction();
	}
}