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
 * [Manage tag data]
 *
 * @desc Manage plugin's assets
 * @package /app/DataStores
 * @since 1.0.0
 */
class WordkflowData {

	/**
	 * Workflow Title
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $title;


	/**
	 * Workflow data
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $workflow_data;

	/**
	 * Global State Data
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $global_state;


	/**
	 * Contact status
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private $status;

	/**
	 * Workflow data
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $last_step_id;

	/**
	 * Initialize class functionalities
	 *
	 * @param array $args Custom field data.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $args ) {
		$this->title         = $args[ 'title' ];
		$this->workflow_data = $args[ 'workflow_data' ];
		$this->global_state  = $args[ 'global_state' ];
		$this->status        = $args[ 'status' ];
		$this->last_step_id  = $args[ 'last_step_id' ];
	}

	/**
	 * Return workflow title
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_title() {
		return $this->title;
	}


	/**
	 * Return workflow data
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_workflow_data() {
		return $this->workflow_data;
	}

	/**
	 * Return golbal state data
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_global_state() {
		return $this->global_state;
	}


	/**
	 * Return note status
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Return last step id
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_last_step_id() {
		return $this->last_step_id;
	}
}
