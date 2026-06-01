<?php
/**
 * Audit logger for all ability executions.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Audit Logger class.
 *
 * Logs every ability execution to the custom mysitehand_audit_log table,
 * with full input/output capture, IP address, timing, and token info.
 */
class Audit_Logger {

	/**
	 * Log an ability execution.
	 *
	 * @param array<string, mixed> $data {
	 *   @type int    $token_id      Token DB ID.
	 *   @type int    $user_id       User ID.
	 *   @type string $ability_name  Name of the ability executed.
	 *   @type array  $input         Input parameters.
	 *   @type string $result_status 'success' | 'error' | 'rate_limited'.
	 *   @type string $result_summary Short summary of result or error message.
	 *   @type int    $duration_ms   Execution time in milliseconds.
	 * }
	 * @return int|false Insert ID or false on failure.
	 */
	public function log( array $data ): int|false {
		global $wpdb;

		$log_level = get_option( 'mysitehand_log_level', 'all' );

		// Skip logging if level is 'none'.
		if ( 'none' === $log_level ) {
			return false;
		}

		// Skip non-errors if level is 'errors-only'.
		if ( 'errors-only' === $log_level && ( $data['result_status'] ?? 'success' ) === 'success' ) {
			return false;
		}

		$input_json = wp_json_encode( $data['input'] ?? [] );
		if ( false === $input_json ) {
			$input_json = '{}';
		}

		$result_summary = isset( $data['result_summary'] )
			? substr( (string) $data['result_summary'], 0, 500 )
			: null;

		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
			: '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'mysitehand_audit_log',
			[
				'token_id'      => isset( $data['token_id'] ) ? (int) $data['token_id'] : null,
				'user_id'       => isset( $data['user_id'] ) ? (int) $data['user_id'] : null,
				'ability_name'  => sanitize_text_field( $data['ability_name'] ?? '' ),
				'input_json'    => $input_json,
				'result_status' => sanitize_text_field( $data['result_status'] ?? 'success' ),
				'result_summary' => $result_summary,
				'ip_address'    => $ip_address,
				'user_agent'    => $user_agent,
				'duration_ms'   => isset( $data['duration_ms'] ) ? (int) $data['duration_ms'] : null,
				'executed_at'   => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get audit log entries with optional filters.
	 *
	 * @param array<string, mixed> $filters {
	 *   @type int    $token_id     Filter by token ID.
	 *   @type string $ability_name Filter by ability name.
	 *   @type string $status       Filter by result status.
	 *   @type string $date_from    ISO 8601 start date.
	 *   @type string $date_to      ISO 8601 end date.
	 *   @type int    $per_page     Results per page (default 20).
	 *   @type int    $page         Page number (default 1).
	 * }
	 * @return array{logs: array, total: int, pages: int}
	 */
	public function get_logs( array $filters = [] ): array {
		global $wpdb;

		$per_page = (int) ( $filters['per_page'] ?? 20 );
		$page     = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = [];
		$values = [];

		if ( ! empty( $filters['token_id'] ) ) {
			$where[]  = 'token_id = %d';
			$values[] = (int) $filters['token_id'];
		}

		if ( ! empty( $filters['ability_name'] ) ) {
			$where[]  = 'ability_name = %s';
			$values[] = sanitize_text_field( $filters['ability_name'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'result_status = %s';
			$values[] = sanitize_text_field( $filters['status'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'executed_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'executed_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[]  = '(ability_name LIKE %s OR result_summary LIKE %s OR ip_address LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( $where ) {
			$query = "SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log $where_sql";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $wpdb->prepare( $query, ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log" );
		}

		// Fetch rows.
		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$query = "SELECT * FROM {$wpdb->prefix}mysitehand_audit_log $where_sql ORDER BY executed_at DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$logs = $wpdb->get_results( $wpdb->prepare( $query, ...$query_values ), ARRAY_A );

		return [
			'logs'  => $logs ?: [],
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		];
	}

	/**
	 * Delete audit log entries older than the configured retention period.
	 *
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_logs(): int {
		global $wpdb;

		$days = (int) get_option( 'mysitehand_log_retention_days', 30 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}mysitehand_audit_log WHERE executed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/**
	 * Get aggregated statistics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_stats(): array {
		global $wpdb;

		$stats = wp_cache_get( 'audit_stats', 'my-site-hand' );
		if ( false !== $stats ) {
			return $stats;
		}

		// Calls today.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$calls_today = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log WHERE DATE(executed_at) = CURDATE()"
		);

		// Calls this week.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$calls_week = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);

		// Calls yesterday.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$calls_yesterday = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log WHERE DATE(executed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
		);

		// Calls this month.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$calls_month = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		// Errors in last 24h.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$errors_24h = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log WHERE result_status = 'error' AND executed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		// Top 5 abilities.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$top_abilities = $wpdb->get_results(
			"SELECT ability_name, COUNT(*) as count FROM {$wpdb->prefix}mysitehand_audit_log WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY ability_name ORDER BY count DESC LIMIT 5",
			ARRAY_A
		);

		// Error rate.
		$total_month  = max( 1, $calls_month );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$errors_month = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log WHERE result_status = 'error' AND executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);
		$error_rate = round( ( $errors_month / $total_month ) * 100, 2 );

		// Average duration.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$avg_duration = (float) $wpdb->get_var(
			"SELECT AVG(duration_ms) FROM {$wpdb->prefix}mysitehand_audit_log WHERE executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND duration_ms IS NOT NULL"
		);

		$stats = [
			'calls_today'     => $calls_today,
			'calls_yesterday' => $calls_yesterday,
			'calls_week'      => $calls_week,
			'calls_month'     => $calls_month,
			'errors_24h'      => $errors_24h,
			'top_abilities'   => $top_abilities ?: [],
			'error_rate'      => $error_rate,
			'avg_duration'    => round( $avg_duration, 2 ),
		];

		wp_cache_set( 'audit_stats', $stats, 'my-site-hand', 300 ); // Cache for 5 minutes.

		return $stats;
	}

	/**
	 * Delete all audit logs.
	 *
	 * @return int Number deleted.
	 */
	public function delete_all_logs(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( "DELETE FROM {$wpdb->prefix}mysitehand_audit_log" );
	}

	/**
	 * Get the client's real IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For).
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}




