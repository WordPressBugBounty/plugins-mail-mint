<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @package /includes/
 */

namespace Mint\MRM\Includes;

/**
 * Responsible MintMail CLI
 *
 * @package /includes/
 * @since 1.0.0
 */
class MintMailCLI {

	/**
	 * Remove data (tables and option values) added by Mint Mail
	 *
	 * @return void
	 */
	public function remove_all() {
		DeletePluginData::delete_all_db_tables();
		DeletePluginData::delete_all_option_values();
		DeletePluginData::delete_all_saved_templates();
	}

	/**
	 * Remove tables added by Mint Mail
	 *
	 * @return void
	 */
	public function remove_tables() {
		DeletePluginData::delete_all_db_tables();
	}

	/**
	 * Remove options in wp_option table added by Mint Mail
	 *
	 * @return void
	 */
	public function remove_options() {
		DeletePluginData::delete_all_option_values();
	}

	/**
	 * Remove saved email templates added by Mint Mail
	 *
	 * @return void
	 */
	public function remove_templates() {
		DeletePluginData::delete_all_saved_templates();
	}
}
