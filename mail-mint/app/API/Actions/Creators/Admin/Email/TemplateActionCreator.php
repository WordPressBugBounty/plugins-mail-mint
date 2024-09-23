<?php
/**
 * Create class object of TemplateAction and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

use TemplateAction;

/**
 * Class TemplateActionCreator
 *
 * Summary: Template Action Creator.
 * Description: Extends the ActionCreator class to create instances of the TemplateAction class for performing actions on templates.
 *
 * @since 1.9.0
 */
class TemplateActionCreator extends ActionCreator {

    /** 
     * Returns a new instance of the TemplateAction class, 
     * which is used to perform actions on templates.
     * 
     * @return TemplateAction A new instance of the TemplateAction class. 
     * @since 1.9.0 
     */ 
    public function makeAction() {
        return new TemplateAction();
    }
}