<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/DataStores
 */

namespace Mint\MRM\DataStores;

/**
 * [Manage field group data]
 *
 * @desc Manage plugin's assets
 * @package /app/DataStores
 * @since 1.0.0
 */
class FieldGroup {

	/**
	 * Group title
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $title;

	/**
	 * Initialize class functionalities
	 *
	 * @param array $args Custom field data.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $args ) {
		$this->title = isset( $args[ 'title' ] ) ? sanitize_text_field( $args[ 'title' ] ) : null;
	}


	/**
	 * Return title
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_title() {
		return $this->title;
	}

}
