<?php
/**
 * Rate limiter for API tokens.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Rate Limiter class.
 *
 * Implements per-token hourly and daily rate limiting stored in a custom DB table.
 * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic increments.
 */
class Rate_Limiter {

	/**
	 * Check if a token is within rate limits.
	 *
	 * @param int $token_id Token DB ID.
	 * @return true|\WP_Error True if OK, WP_Error with 429 status if rate limited.
	 */
	public function check( int $token_id ): true|\WP_Error {
		global $wpdb;

		$hourly_limit = (int) apply_filters(
			'my_site_hand_hourly_limit',
			(int) get_option( 'mysitehand_hourly_limit', 200 ),
			$token_id
		);

		$daily_limit = (int) apply_filters(
			'my_site_hand_daily_limit',
			(int) get_option( 'mysitehand_daily_limit', 2000 ),
			$token_id
		);

		$hourly_key = gmdate( 'Y-m-d-H' );
		$daily_key  = gmdate( 'Y-m-d' );

		// Get current usage.
		$usage = $this->get_usage( $token_id );

		// Check hourly limit.
		if ( $usage['hourly']['used'] >= $hourly_limit ) {
			$seconds_until_reset = 3600 - ( time() % 3600 );
			return new \WP_Error(
				'rate_limited',
				sprintf(
					/* translators: %d: hourly limit */
					__( 'Hourly rate limit of %d requests exceeded.', 'my-site-hand' ),
					$hourly_limit
				),
				[
					'status'      => 429,
					'retry_after' => $seconds_until_reset,
				]
			);
		}

		// Check daily limit.
		if ( $usage['daily']['used'] >= $daily_limit ) {
			$seconds_until_reset = strtotime( 'tomorrow' ) - time();
			return new \WP_Error(
				'rate_limited',
				sprintf(
					/* translators: %d: daily limit */
					__( 'Daily rate limit of %d requests exceeded.', 'my-site-hand' ),
					$daily_limit
				),
				[
					'status'      => 429,
					'retry_after' => $seconds_until_reset,
				]
			);
		}

		return true;
	}

	/**
	 * Increment usage counters for the current time window.
	 *
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic increment.
	 *
	 * @param int $token_id Token DB ID.
	 * @return void
	 */
	public function increment( int $token_id ): void {
		global $wpdb;

		$hourly_key = gmdate( 'Y-m-d-H' );
		$daily_key  = gmdate( 'Y-m-d' );
		$now        = current_time( 'mysql' );

		// Increment hourly window.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}mysitehand_rate_limits (token_id, window_key, request_count, window_start)
				VALUES (%d, %s, 1, %s)
				ON DUPLICATE KEY UPDATE request_count = request_count + 1",
				$token_id,
				$hourly_key,
				$now
			)
		);

		// Increment daily window.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}mysitehand_rate_limits (token_id, window_key, request_count, window_start)
				VALUES (%d, %s, 1, %s)
				ON DUPLICATE KEY UPDATE request_count = request_count + 1",
				$token_id,
				$daily_key,
				$now
			)
		);
	}

	/**
	 * Get current usage stats for a token.
	 *
	 * @param int $token_id Token DB ID.
	 * @return array{hourly: array{used: int, limit: int}, daily: array{used: int, limit: int}}
	 */
	public function get_usage( int $token_id ): array {
		global $wpdb;

		$hourly_limit = (int) apply_filters(
			'my_site_hand_hourly_limit',
			(int) get_option( 'mysitehand_hourly_limit', 200 ),
			$token_id
		);

		$daily_limit = (int) apply_filters(
			'my_site_hand_daily_limit',
			(int) get_option( 'mysitehand_daily_limit', 2000 ),
			$token_id
		);

		$hourly_key = gmdate( 'Y-m-d-H' );
		$daily_key  = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$hourly_used = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT request_count FROM {$wpdb->prefix}mysitehand_rate_limits WHERE token_id = %d AND window_key = %s",
				$token_id,
				$hourly_key
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$daily_used = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT request_count FROM {$wpdb->prefix}mysitehand_rate_limits WHERE token_id = %d AND window_key = %s",
				$token_id,
				$daily_key
			)
		);

		return [
			'hourly' => [
				'used'  => $hourly_used,
				'limit' => $hourly_limit,
			],
			'daily'  => [
				'used'  => $daily_used,
				'limit' => $daily_limit,
			],
		];
	}

	/**
	 * Reset all rate limit counters for a token.
	 *
	 * @param int $token_id Token DB ID.
	 * @return void
	 */
	public function reset( int $token_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'mysitehand_rate_limits',
			[ 'token_id' => $token_id ],
			[ '%d' ]
		);
	}

	/**
	 * Clean up old rate limit records.
	 *
	 * Removes records older than 2 days.
	 *
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_records(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			"DELETE FROM {$wpdb->prefix}mysitehand_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 DAY)"
		);
	}
}




