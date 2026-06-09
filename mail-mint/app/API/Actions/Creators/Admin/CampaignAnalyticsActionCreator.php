<?php
/**
 * Create class object of CampaignAnalyticsAction and return object
 *
 * @package Mint\MRM\API\Actions
 * @since 1.23.3
 */

namespace Mint\MRM\API\Actions;

/**
 * Class CampaignAnalyticsActionCreator
 *
 * @since 1.23.3
 */
class CampaignAnalyticsActionCreator extends ActionCreator {

	/**
	 * Create a CampaignAnalyticsAction instance.
	 *
	 * @return CampaignAnalyticsAction
	 * @since 1.23.3
	 */
	public function makeAction() {
		return new CampaignAnalyticsAction();
	}
}
