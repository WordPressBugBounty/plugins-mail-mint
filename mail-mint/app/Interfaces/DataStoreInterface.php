<?php
/**
 * Interface to store all the data
 *
 * @package Mint\MRM\DataStores
 * @namespace Mint\MRM\DataStores
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 */

namespace Mint\MRM\DataStores;

interface DataStoreInterface {

	/**
	 * Abstract method to create or update datas
	 *
	 * @param mixed $data data object.
	 * @since 1.0.0
	 */
	public function create_or_update( &$data );

	/**
	 * Abstract method to read data
	 *
	 * @since 1.0.0
	 */
	public function read();

	/**
	 * Abstract method to delete data
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function delete();

	/**
	 * Abstract method to read meta data
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function read_meta();

	/**
	 * Abstract method to update meta data
	 *
	 * @since 1.0.0
	 */
	public function update_meta();

	/**
	 * Abstract method to delete meta data
	 *
	 * @since 1.0.0
	 */
	public function delete_meta();

}
