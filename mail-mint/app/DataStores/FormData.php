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
 * [Manage form data]
 *
 * @desc Manage plugin's assets
 * @package /app/DataStores
 * @since 1.0.0
 */
class FormData {

	/**
	 * Form title
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $title;

	/**
	 * Form body
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $form_body;


	/**
	 * Form position
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $form_position;

	/**
	 * Form status
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $status;

	/**
	 * Form group_ids
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $group_ids;

	/**
	 * Form template id
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $template_id;

	/**
	 * Form creator id
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $created_by;


	/**
	 * Form Meta Fileds
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $meta_fields;

	/**
	 * Initialize class functionalities
	 *
	 * @param array $args Custom field data.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $args ) {
		$this->title         = isset( $args[ 'title' ] ) ? $args[ 'title' ] : null;
		$this->form_body     = isset( $args[ 'form_body' ] ) ? $args[ 'form_body' ] : null;
		$this->form_position = isset( $args[ 'form_position' ] ) ? $args[ 'form_position' ] : null;
		$this->status        = isset( $args[ 'status' ] ) ? $args[ 'status' ] : 'draft';
		$this->template_id   = isset( $args[ 'template_id' ] ) ? $args[ 'template_id' ] : null;
		$this->created_by    = isset( $args[ 'created_by' ] ) ? $args[ 'created_by' ] : null;
		$this->group_ids     = isset( $args[ 'group_ids' ] ) ? $args[ 'group_ids' ] : array();
		$this->meta_fields   = isset( $args[ 'meta_fields' ] ) ? $args[ 'meta_fields' ] : array();
	}


	/**
	 * Getter Function title
	 *
	 * @return string title of the list
	 * @since 1.0.0
	 */
	public function get_title() {
		return $this->title;
	}


	/**
	 * Return form body
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_form_body() {
		if ( ! is_serialized( $this->form_body ) ) {
			return maybe_serialize( $this->form_body );
		}
	}

	/**
	 * Return form position
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_form_position() {
		return $this->form_position;
	}

	/**
	 * Return form status
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Return form group ids
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_group_ids() {
		if ( ! is_serialized( $this->group_ids ) ) {
			return maybe_serialize( $this->group_ids );
		}
	}


	/**
	 * Return creator ID
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_created_by() {
		return $this->created_by;
	}

	/**
	 * Return template id
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_template_id() {
		return $this->template_id;
	}

	/**
	 * Return Meta Fields
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_meta_fields() {
		return $this->meta_fields;
	}

}
