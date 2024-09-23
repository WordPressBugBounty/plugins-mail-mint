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
 * [Manage segment data]
 *
 * @desc Manage plugin's assets
 * @package /app/DataStores
 * @since 1.0.0
 */
class SegmentData {

	/**
	 * Segment title
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $title;


	/**
	 * Segment description and filters
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $data;

	/**
	 * Segment slug
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $slug;

	/**
	 * Initialize class functionalities
	 *
	 * @param array $args Custom field data.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $args ) {
		$this->title = isset( $args[ 'title' ] ) ? $args[ 'title' ] : null;
		$this->slug  = isset( $args[ 'slug' ] ) ? $args[ 'slug' ] : null;
		$this->data  = isset( $args[ 'data' ] ) ? $args[ 'data' ] : null;
	}


	/**
	 * Return segment title
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_title() {
		return $this->title;
	}


	/**
	 * Return segment slug
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_slug() {
		return $this->slug;
	}


	/**
	 * Return segment data after serialization
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_data() {
		if ( ! is_serialized( $this->data ) ) {
			return maybe_serialize( $this->data );
		}
	}
}
