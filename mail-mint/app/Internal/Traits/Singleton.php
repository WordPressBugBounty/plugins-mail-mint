<?php
/**
 * Mail Mint
 *
 * @author [MRM Team]
 * @email [support@getwpfunnels.com]
 * @create date 2022-08-09 11:03:17
 * @modify date 2022-08-09 11:03:17
 * @package /app/Internal/Traits
 */

namespace Mint\Mrm\Internal\Traits;

/**
 * Trait to follow Singleton design pattern
 *
 * @package /app/Internal/Traits
 * @since 1.0.0
 */
trait Singleton {

	/**
	 * Singleton Instance
	 *
	 * @var Singleton
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Private Constructor
	 *
	 * We can't use the constructor to create an instance of the class
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function __construct() {
		// Don't do anything, we don't want to be initialized.
	}

	/**
	 * Get the singleton instance
	 *
	 * @return Singleton
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_attr__( 'Cloning is forbidden.', 'mrm' ), '2.1' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_attr__( 'Unserializing instances of this class is forbidden.', 'mrm' ), '2.1' );
	}

	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @param mixed $key Key name.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function __get( $key ) {
		if ( in_array( $key, array( 'payment_gateways', 'shipping', 'mailer', 'checkout' ), true ) ) {
			return $this->$key();
		}
	}
}
