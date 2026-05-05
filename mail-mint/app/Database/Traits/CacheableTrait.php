<?php
/**
 * CacheableTrait — opt-in WP Transient caching for repositories.
 *
 * @package Mint\MRM\Database\Traits
 */

namespace Mint\MRM\Database\Traits;

/**
 * Provides opt-in caching via WordPress Transients.
 *
 * Caching is disabled by default. Call enableCache() to activate.
 * All transient keys are prefixed with 'mailmint_' to avoid collisions.
 */
trait CacheableTrait {

	/**
	 * Whether caching is currently active.
	 *
	 * @var bool
	 */
	private $cacheEnabled = false;

	/**
	 * Cache time-to-live in seconds.
	 *
	 * @var int
	 */
	private $cacheTtl = 300;

	/**
	 * Enable caching for this instance.
	 *
	 * @param int $ttl Time-to-live in seconds. Default 300 (5 minutes).
	 * @return $this
	 */
	public function enableCache( int $ttl = 300 ) {
		$this->cacheEnabled = true;
		$this->cacheTtl     = $ttl;
		return $this;
	}

	/**
	 * Return cached data for the given key, or execute the callback and cache the result.
	 *
	 * When caching is disabled, the callback is executed directly every time.
	 *
	 * @param string   $key      Cache key (will be prefixed with 'mailmint_').
	 * @param callable $callback Callback that produces the value to cache.
	 * @return mixed
	 */
	protected function cached( string $key, callable $callback ) {
		if ( ! $this->cacheEnabled ) {
			return $callback();
		}

		$transient_key = 'mailmint_' . $key;
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = $callback();
		set_transient( $transient_key, $value, $this->cacheTtl );

		return $value;
	}

	/**
	 * Invalidate a cached transient by key.
	 *
	 * @param string $pattern Cache key to invalidate (will be prefixed with 'mailmint_').
	 * @return void
	 */
	public function invalidateCache( string $pattern ) {
		delete_transient( 'mailmint_' . $pattern );
	}
}
