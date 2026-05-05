<?php
/**
 * Campaign type enum.
 *
 * @package Mint\MRM\Database\Enums
 */

namespace Mint\MRM\Database\Enums;

/**
 * Canonical campaign type values with helper methods for broadcast
 * and Pro gating checks.
 */
final class CampaignType {

	const REGULAR    = 'regular';
	const SEQUENCE   = 'sequence';
	const RECURRING  = 'recurring';
	const AUTOMATION = 'automation';

	const ALL = [
		self::REGULAR,
		self::SEQUENCE,
		self::RECURRING,
		self::AUTOMATION,
	];

	/**
	 * Types that use the broadcast email pipeline.
	 *
	 * @var string[]
	 */
	private static $broadcastTypes = [
		self::REGULAR,
		self::RECURRING,
	];

	/**
	 * Types that require the Pro plugin.
	 *
	 * @var string[]
	 */
	private static $proTypes = [
		self::SEQUENCE,
		self::RECURRING,
		self::AUTOMATION,
	];

	/**
	 * Runtime-registered types (Pro extensibility).
	 *
	 * Each entry is keyed by type string with its config array as value.
	 *
	 * @var array<string, array>
	 */
	private static $registered = [];

	/**
	 * Check whether a type string is valid.
	 *
	 * @param string $type Type to check.
	 * @return bool
	 */
	public static function isValid( string $type ): bool {
		return in_array( $type, self::ALL, true )
			|| array_key_exists( $type, self::$registered );
	}

	/**
	 * Return every valid type value.
	 *
	 * @return string[]
	 */
	public static function all(): array {
        return array_merge( self::ALL, array_keys( self::$registered ) );
    }

    /**
     * Register an additional type at runtime.
     *
     * Duplicate types are silently ignored.
     *
     * @param string $type   New type to register.
     * @param array  $config Configuration for the type (e.g. usesBroadcast, requiresPro).
     */
    public static function register( string $type, array $config = [] ): void {
        if ( ! in_array( $type, self::ALL, true ) && ! array_key_exists( $type, self::$registered ) ) {
            self::$registered[ $type ] = $config;
        }
    }

    /**
     * Check whether a campaign type uses the broadcast email pipeline.
     *
     * @param string $type Campaign type.
     * @return bool
     */
    public static function usesBroadcast( string $type ): bool {
        if ( in_array( $type, self::$broadcastTypes, true ) ) {
            return true;
        }

        if ( array_key_exists( $type, self::$registered ) ) {
            return ! empty( self::$registered[ $type ]['usesBroadcast'] );
        }

        return false;
    }

    /**
     * Check whether a campaign type requires the Pro plugin.
     *
     * @param string $type Campaign type.
     * @return bool
     */
    public static function requiresPro( string $type ): bool {
        if ( in_array( $type, self::$proTypes, true ) ) {
            return true;
        }

        if ( array_key_exists( $type, self::$registered ) ) {
            return ! empty( self::$registered[ $type ]['requiresPro'] );
        }

        return false;
    }
}
