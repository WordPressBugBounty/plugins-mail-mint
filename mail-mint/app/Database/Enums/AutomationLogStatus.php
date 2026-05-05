<?php
/**
 * Automation log status enum.
 *
 * @package Mint\MRM\Database\Enums
 */

namespace Mint\MRM\Database\Enums;

/**
 * Canonical automation log step-tracking status values.
 */
final class AutomationLogStatus {

	const PROCESSING = 'processing';
	const COMPLETED  = 'completed';
	const HOLD       = 'hold';
	const EXITED     = 'exited';
	const PENDING    = 'pending';
	const FAIL       = 'fail';

	const ALL = [
		self::PROCESSING,
		self::COMPLETED,
		self::HOLD,
		self::EXITED,
		self::PENDING,
		self::FAIL,
	];

	/**
	 * Runtime-registered values (Pro extensibility).
	 *
	 * @var string[]
	 */
	private static $registered = [];

	/**
	 * Check whether a status string is valid.
	 *
	 * @param string $status Status to check.
	 * @return bool
	 */
	public static function isValid( string $status ): bool {
		return in_array( $status, array_merge( self::ALL, self::$registered ), true );
	}

	/**
	 * Return every valid status value.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array_merge( self::ALL, self::$registered );
	}

	/**
	 * Register an additional status value at runtime.
	 *
	 * Duplicate values are silently ignored.
	 *
	 * @param string $status New status to register.
	 */
	public static function register( string $status ): void {
		if ( ! in_array( $status, self::$registered, true ) ) {
			self::$registered[] = $status;
		}
	}
}
