<?php
/**
 * Interface for Schemas
 *
 * @package Mint\MRM\DataStores
 * @namespace Mint\MRM\DataStores
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\Interfaces;

interface Schema {
	/**
	 * Abstract function to get SQL for the database
	 *
	 * @since 1.0.0
	 */
	public function get_sql();

}
