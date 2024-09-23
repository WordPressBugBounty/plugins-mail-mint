<?php
/**
 * Create class object of AnalyticsActions and return object
 *
 * @package Mint\MRM\API\Actions
 */

namespace Mint\MRM\API\Actions;

/**
 * Class AnalyticsActionCreator
 */
class AnalyticsActionCreator extends ActionCreator {

	/**
	 * Create AnalyticsActions instance
	 *
	 * @return AnalyticsActions object
	 * @since 1.0.0
	 */
	public function makeAction() {
		return new AnalyticsActions();
	}
}