<?php
/**
 * Transient-based caching layer.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Cache Manager class.
 *
 * Provides a thin wrapper around WordPress transients with
 * consistent key prefixing and in-request cache for repeated lookups.
 */
class Cache_Manager {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private const KEY_PREFIX = 'MYSITEHAND_';

	/**
	 * In-request cache to avoid repeated get_transient() calls.
	 *
	 * @var array<string, mixed>
	 */
	private array $runtime_cache = [];

	/**
	 * Get a cached value.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return mixed Cached value or false if not found.
	 */
	public function get( string $key ): mixed {
		$full_key = self::KEY_PREFIX . $key;

		// Check runtime cache first.
		if ( array_key_exists( $full_key, $this->runtime_cache ) ) {
			return $this->runtime_cache[ $full_key ];
		}

		$value = get_transient( $full_key );

		if ( false !== $value ) {
			$this->runtime_cache[ $full_key ] = $value;
		}

		return $value;
	}

	/**
	 * Set a cached value.
	 *
	 * @param string $key     Cache key (without prefix).
	 * @param mixed  $value   Value to cache.
	 * @param int    $ttl     Expiry in seconds (0 = use global config).
	 * @return bool True on success.
	 */
	public function set( string $key, mixed $value, int $ttl = 0 ): bool {
		$full_key = self::KEY_PREFIX . $key;

		if ( 0 === $ttl ) {
			$ttl = (int) get_option( 'mysitehand_cache_ttl', 3600 );
		}

		$this->runtime_cache[ $full_key ] = $value;

		return set_transient( $full_key, $value, $ttl );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return bool True on success.
	 */
	public function delete( string $key ): bool {
		$full_key = self::KEY_PREFIX . $key;
		unset( $this->runtime_cache[ $full_key ] );
		return delete_transient( $full_key );
	}

	/**
	 * Get a cached value or compute and cache it.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Function to compute value if not cached.
	 * @param int      $ttl      Expiry in seconds.
	 * @return mixed
	 */
	public function remember( string $key, callable $callback, int $ttl = 0 ): mixed {
		$cached = $this->get( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = $callback();
		$this->set( $key, $value, $ttl );

		return $value;
	}

	/**
	 * Clear all my-site-hand transients.
	 *
	 * @return int Number of transients deleted.
	 */
	public function clear_all(): int {
		global $wpdb;

		// Clear runtime cache.
		$this->runtime_cache = [];

		// Delete all transients with our prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::KEY_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::KEY_PREFIX ) . '%'
			)
		);

		return $count;
	}

	/**
	 * Check if a cache key exists.
	 *
	 * @param string $key Cache key (without prefix).
	 * @return bool
	 */
	public function has( string $key ): bool {
		return false !== $this->get( $key );
	}
}




