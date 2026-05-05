<?php
/**
 * Form status enum.
 *
 * @package Mint\MRM\Database\Enums
 */

namespace Mint\MRM\Database\Enums;

/**
 * Canonical form publishing status values.
 */
final class FormStatus {

	const DRAFT     = 'draft';
	const PUBLISHED = 'published';

	const ALL = [
		self::DRAFT,
		self::PUBLISHED,
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
