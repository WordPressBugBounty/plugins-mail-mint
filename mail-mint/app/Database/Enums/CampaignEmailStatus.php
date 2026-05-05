<?php
/**
 * Campaign email status enum.
 *
 * @package Mint\MRM\Database\Enums
 */

namespace Mint\MRM\Database\Enums;

/**
 * Canonical campaign email scheduling status values.
 */
final class CampaignEmailStatus {

	const DRAFT      = 'draft';
	const SCHEDULING = 'scheduling';
	const SCHEDULED  = 'scheduled';
	const SENT       = 'sent';

	const ALL = [
		self::DRAFT,
		self::SCHEDULING,
		self::SCHEDULED,
		self::SENT,
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
