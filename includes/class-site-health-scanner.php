<?php
/**
 * Site Health scan orchestrator.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use Throwable;
use WP_Error;

/**
 * Site Health Scanner class.
 *
 * Owns the check registry, drives resumable checks one batch at a time, keeps
 * partial results alive between the separate HTTP requests that make up a
 * single scan, scores the result, and stores the finished report plus a
 * trimmed history row.
 *
 * This inspects the USER'S SITE. It is not the AI Audit Log.
 */
class Site_Health_Scanner {

	/**
	 * Option holding the most recent finished report.
	 *
	 * @var string
	 */
	public const REPORT_OPTION = 'mysitehand_last_scan';

	/**
	 * Stored report format version. Bump when the shape changes.
	 *
	 * @var int
	 */
	public const REPORT_VERSION = 1;

	/**
	 * Lifetime of partial run state.
	 *
	 * @var int
	 */
	public const RUN_STATE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Maximum issue arrays stored per check in the report.
	 *
	 * Quick Fix renders from this data, so it needs enough rows to work
	 * through — but a site with thousands of issues must not produce a
	 * multi-megabyte option.
	 *
	 * @var int
	 */
	public const MAX_STORED_ISSUES = 50;

	/**
	 * History rows retained per site.
	 *
	 * @var int
	 */
	public const HISTORY_LIMIT = 52;

	/**
	 * User meta holding the lifetime Quick Fix count.
	 *
	 * @var string
	 */
	public const FIX_COUNT_META = 'mysitehand_fix_count';

	/**
	 * Score deducted per issue, by issue severity.
	 *
	 * @var array<string, int>
	 */
	public const ISSUE_WEIGHTS = [
		Health_Check_Base::SEVERITY_CRITICAL => 10,
		Health_Check_Base::SEVERITY_WARNING  => 3,
		Health_Check_Base::SEVERITY_NOTICE   => 1,
	];

	/**
	 * Maximum a single check may deduct, by check severity.
	 *
	 * Without a cap the score stops discriminating: 500 missing alt tags and
	 * 600 missing alt tags both floor the score at zero, every large site sees
	 * the same number, and clearing a category produces no visible reward.
	 *
	 * Retuned in 1.2.0, when the scan went from six checks to twelve. The old
	 * caps (25 / 15 / 10) were sized so that six checks summed to exactly 100.
	 * Left alone, twelve checks summed to 175 and an ordinary site tripping
	 * seven or eight categories landed in the critical band — a report that
	 * tells every single user their site is broken is not a measurement, it is
	 * noise, and users uninstall it. These caps sum to 105 across the twelve
	 * shipped checks, so the top of the range is still reachable only by a site
	 * that is genuinely failing everywhere.
	 *
	 * @var array<string, int>
	 */
	public const CHECK_CAPS = [
		Health_Check_Base::SEVERITY_CRITICAL => 20,
		Health_Check_Base::SEVERITY_WARNING  => 8,
		Health_Check_Base::SEVERITY_NOTICE   => 5,
	];

	/**
	 * Cache manager instance.
	 *
	 * @var Cache_Manager
	 */
	private Cache_Manager $cache;

	/**
	 * Memoized check instances, keyed by id.
	 *
	 * @var array<string, Health_Check_Base>|null
	 */
	private ?array $checks = null;

	/**
	 * Constructor.
	 *
	 * @param Cache_Manager $cache Cache manager used for run state.
	 */
	public function __construct( Cache_Manager $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Get every registered check, in display order, keyed by id.
	 *
	 * Checks are contributed through the 'my_site_hand_health_checks' filter so
	 * new checks can be added without editing this class.
	 *
	 * @return array<string, Health_Check_Base>
	 */
	public function get_checks(): array {
		if ( null !== $this->checks ) {
			return $this->checks;
		}

		/**
		 * Filter the Site Health checks that make up a scan.
		 *
		 * @param array<int|string, Health_Check_Base> $checks Check instances, in display order.
		 */
		$registered = apply_filters( 'my_site_hand_health_checks', [] );

		$checks = [];

		foreach ( (array) $registered as $check ) {
			if ( ! $check instanceof Health_Check_Base ) {
				continue;
			}

			$id = $check->get_id();

			if ( '' === $id || isset( $checks[ $id ] ) ) {
				continue;
			}

			$checks[ $id ] = $check;
		}

		$this->checks = $checks;

		return $this->checks;
	}

	/**
	 * Get a single check by id.
	 *
	 * @param string $id Check id.
	 * @return Health_Check_Base|null
	 */
	public function get_check( string $id ): ?Health_Check_Base {
		$checks = $this->get_checks();

		return $checks[ $id ] ?? null;
	}

	/**
	 * Run one batch of one check and merge the result into run state.
	 *
	 * A check that throws is caught and marked errored: one broken check must
	 * never abort the whole scan.
	 *
	 * @param string $id     Check id.
	 * @param int    $offset Offset to resume from.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run_check( string $id, int $offset = 0 ): array|WP_Error {
		$check = $this->get_check( $id );

		if ( null === $check ) {
			return new WP_Error(
				'my_site_hand_unknown_check',
				__( 'Unknown health check.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		$offset = max( 0, $offset );
		$state  = $this->get_run_state();

		// A new batch means the run is no longer finished.
		unset( $state['finalized_at'] );

		// Starting a check from the top resets its accumulator.
		if ( 0 === $offset || ! isset( $state['checks'][ $id ] ) ) {
			$state['checks'][ $id ] = $this->blank_check_state();
		}

		if ( ! $check->is_applicable() ) {
			$state['checks'][ $id ]['status'] = 'skipped';
			$this->save_run_state( $state );

			return $this->batch_response( $id, $state, true, null, 0, [] );
		}

		try {
			$result = $check->run( $offset );
		} catch ( Throwable $e ) {
			$state['checks'][ $id ]['status'] = 'error';
			$state['checks'][ $id ]['error']  = $e->getMessage();
			$this->save_run_state( $state );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'My Site Hand health check "%s" failed: %s', $id, $e->getMessage() ) );
			}

			return $this->batch_response( $id, $state, true, null, 0, [] );
		}

		$done          = ! empty( $result['done'] );
		$next_offset   = isset( $result['next_offset'] ) ? (int) $result['next_offset'] : null;
		$batch_scanned = isset( $result['scanned'] ) ? (int) $result['scanned'] : 0;
		$batch_issues  = isset( $result['issues'] ) && is_array( $result['issues'] ) ? array_values( $result['issues'] ) : [];

		// Accumulate — never replace.
		$state['checks'][ $id ]['status']   = $done ? 'done' : 'running';
		$state['checks'][ $id ]['scanned'] += $batch_scanned;
		$state['checks'][ $id ]['issues']   = array_merge( $state['checks'][ $id ]['issues'], $batch_issues );

		$this->save_run_state( $state );

		return $this->batch_response( $id, $state, $done, $done ? null : $next_offset, $batch_scanned, $batch_issues );
	}

	/**
	 * Get the current partial run state, initializing it when absent.
	 *
	 * @return array<string, mixed>
	 */
	public function get_run_state(): array {
		$state = $this->cache->get( $this->run_state_key() );

		if ( ! is_array( $state ) || ! isset( $state['checks'] ) || ! is_array( $state['checks'] ) ) {
			$state = [
				'started_at' => time(),
				'started_ms' => (int) round( microtime( true ) * 1000 ),
				'checks'     => [],
			];

			foreach ( array_keys( $this->get_checks() ) as $id ) {
				$state['checks'][ $id ] = $this->blank_check_state();
			}
		}

		return $state;
	}

	/**
	 * Discard partial run state.
	 *
	 * @return void
	 */
	public function clear_run_state(): void {
		$this->cache->delete( $this->run_state_key() );
	}

	/**
	 * Score the run, store the report, append history, and prune.
	 *
	 * Finalizing twice returns the same report rather than writing a second
	 * history row, so a retried request after a network blip is harmless.
	 *
	 * @return array<string, mixed>
	 */
	public function finalize(): array {
		$state = $this->get_run_state();

		if ( ! empty( $state['finalized_at'] ) ) {
			$stored = $this->get_latest_report();
			if ( null !== $stored ) {
				return $stored;
			}
		}

		$results  = $state['checks'];
		$score    = $this->calculate_score( $results );
		$band     = $this->get_score_band( $score );
		$previous = $this->get_previous_score();

		$started_ms  = isset( $state['started_ms'] )
			? (int) $state['started_ms']
			: (int) ( ( $state['started_at'] ?? time() ) * 1000 );
		$duration_ms = max( 0, (int) round( microtime( true ) * 1000 ) - $started_ms );

		$report = [
			'version'        => self::REPORT_VERSION,
			'generated_at'   => time(),
			'score'          => $score,
			'band'           => $band['band'],
			'previous_score' => $previous,
			'duration_ms'    => $duration_ms,
			'checks'         => [],
		];

		$summary = [];

		foreach ( $results as $id => $result ) {
			$check    = $this->get_check( (string) $id );
			$severity = $this->check_severity( (string) $id, $result );
			$issues   = isset( $result['issues'] ) && is_array( $result['issues'] ) ? $result['issues'] : [];
			$status   = (string) ( $result['status'] ?? 'pending' );
			$counted  = $this->counts_toward_score( $status );

			$sorted = $this->sort_by_severity( $issues );
			$stored = array_slice( $sorted, 0, self::MAX_STORED_ISSUES );

			$report['checks'][ $id ] = [
				'label'       => null !== $check ? $check->get_label() : (string) $id,
				'status'      => $status,
				'severity'    => $severity,
				'scanned'     => isset( $result['scanned'] ) ? (int) $result['scanned'] : 0,
				'issue_count' => count( $issues ),
				'deduction'   => $counted ? $this->calculate_deduction( $severity, $issues ) : 0,
				'truncated'   => count( $sorted ) > count( $stored ),
				'issues'      => $stored,
			];

			$summary[ $id ] = [
				'status' => $status,
				'issues' => count( $issues ),
			];
		}

		update_option( self::REPORT_OPTION, $report, false );

		$this->insert_history_row( $score, $summary );
		$this->prune_history();

		$state['finalized_at'] = time();
		$this->save_run_state( $state );

		return $report;
	}

	/**
	 * Get the most recent stored report.
	 *
	 * Returns null for an unrecognized format version so a future shape change
	 * degrades to "no report yet" instead of crashing on stale data.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_latest_report(): ?array {
		$report = get_option( self::REPORT_OPTION, null );

		if ( ! is_array( $report ) || ! isset( $report['version'] ) ) {
			return null;
		}

		if ( (int) $report['version'] !== self::REPORT_VERSION ) {
			return null;
		}

		return $report;
	}

	/**
	 * Calculate the overall score from run-state-shaped check results.
	 *
	 * @param array<string, array<string, mixed>> $check_results Keyed by check id.
	 * @return int Score between 0 and 100.
	 */
	public function calculate_score( array $check_results ): int {
		$deducted = 0;

		foreach ( $check_results as $id => $result ) {
			if ( ! $this->counts_toward_score( (string) ( $result['status'] ?? 'pending' ) ) ) {
				continue;
			}

			$issues = isset( $result['issues'] ) && is_array( $result['issues'] ) ? $result['issues'] : [];

			$deducted += $this->calculate_deduction( $this->check_severity( (string) $id, $result ), $issues );
		}

		return max( 0, 100 - $deducted );
	}

	/**
	 * Calculate one check's capped deduction.
	 *
	 * @param string                           $check_severity Severity of the check itself.
	 * @param array<int, array<string, mixed>> $issues         Issues found by the check.
	 * @return int
	 */
	public function calculate_deduction( string $check_severity, array $issues ): int {
		$raw = 0;

		foreach ( $issues as $issue ) {
			$severity = $issue['severity'] ?? $check_severity;
			$raw     += self::ISSUE_WEIGHTS[ $severity ] ?? self::ISSUE_WEIGHTS[ Health_Check_Base::SEVERITY_NOTICE ];
		}

		$cap = self::CHECK_CAPS[ $check_severity ] ?? self::CHECK_CAPS[ Health_Check_Base::SEVERITY_NOTICE ];

		return min( $raw, $cap );
	}

	/**
	 * Map a score to its band and translated label.
	 *
	 * @param int $score Score between 0 and 100.
	 * @return array{band: string, label: string}
	 */
	public function get_score_band( int $score ): array {
		if ( $score >= 80 ) {
			return [
				'band'  => 'good',
				'label' => __( 'Good', 'my-site-hand' ),
			];
		}

		if ( $score >= 50 ) {
			return [
				'band'  => 'warning',
				'label' => __( 'Needs Attention', 'my-site-hand' ),
			];
		}

		return [
			'band'  => 'critical',
			'label' => __( 'Critical', 'my-site-hand' ),
		];
	}

	/**
	 * Record one Quick Fix repair.
	 *
	 * Counted twice on purpose: in run state for the current session's UI, and
	 * in user meta so the total survives the fifteen minute run-state window.
	 * The review prompt depends on the lifetime figure being trustworthy.
	 *
	 * @return int Repairs made in the current session.
	 */
	public function record_fix(): int {
		$state          = $this->get_run_state();
		$session        = (int) ( $state['fixes'] ?? 0 ) + 1;
		$state['fixes'] = $session;

		$this->save_run_state( $state );

		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::FIX_COUNT_META, (int) get_user_meta( $user_id, self::FIX_COUNT_META, true ) + 1 );
		}

		return $session;
	}

	/**
	 * Total repairs this user has made with Quick Fix, all time.
	 *
	 * @param int $user_id User ID, or 0 for the current user.
	 * @return int
	 */
	public function get_fix_count( int $user_id = 0 ): int {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return 0;
		}

		return (int) get_user_meta( $user_id, self::FIX_COUNT_META, true );
	}

	/**
	 * Get recent history rows, newest first.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_history( int $limit = 8 ): array {
		global $wpdb;

		$limit = max( 1, min( self::HISTORY_LIMIT, $limit ) );

		// The table name is interpolated straight from $wpdb->prefix rather than
		// via a local, so static analysis can see it never touches user input.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, scanned_at, score, summary FROM {$wpdb->prefix}mysitehand_health_history ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! is_array( $rows ) ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$row['id']      = (int) $row['id'];
			$row['score']   = (int) $row['score'];
			$decoded        = json_decode( (string) $row['summary'], true );
			$row['summary'] = is_array( $decoded ) ? $decoded : [];
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Trim history to the retention cap.
	 *
	 * Hooked to the existing daily cleanup cron alongside the audit log cleanup.
	 *
	 * @return int Rows removed.
	 */
	public function prune_history(): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cutoff = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}mysitehand_health_history ORDER BY id DESC LIMIT 1 OFFSET %d", self::HISTORY_LIMIT - 1 )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( null === $cutoff ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$removed = (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}mysitehand_health_history WHERE id < %d", (int) $cutoff )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $removed;
	}

	/**
	 * Blank per-check accumulator.
	 *
	 * @return array<string, mixed>
	 */
	private function blank_check_state(): array {
		return [
			'status'  => 'pending',
			'scanned' => 0,
			'issues'  => [],
			'error'   => null,
		];
	}

	/**
	 * Build the response returned to the caller after a batch.
	 *
	 * @param string               $id            Check id.
	 * @param array<string, mixed> $state         Current run state.
	 * @param bool                 $done          Whether the check finished.
	 * @param int|null             $next_offset   Offset to resume from.
	 * @param int                  $batch_scanned Items examined in this batch.
	 * @param array<int, mixed>    $batch_issues  Issues found in this batch.
	 * @return array<string, mixed>
	 */
	private function batch_response( string $id, array $state, bool $done, ?int $next_offset, int $batch_scanned, array $batch_issues ): array {
		$check_state = $state['checks'][ $id ];

		return [
			'check'         => $id,
			'status'        => $check_state['status'],
			'done'          => $done,
			'next_offset'   => $done ? null : $next_offset,
			'scanned'       => (int) $check_state['scanned'],
			'batch_scanned' => $batch_scanned,
			'issues_found'  => count( $check_state['issues'] ),
			'batch_issues'  => $batch_issues,
			'error'         => $check_state['error'],
		];
	}

	/**
	 * Persist partial run state.
	 *
	 * @param array<string, mixed> $state Run state.
	 * @return void
	 */
	private function save_run_state( array $state ): void {
		$this->cache->set( $this->run_state_key(), $state, self::RUN_STATE_TTL );
	}

	/**
	 * Cache key for the current user's run state.
	 *
	 * @return string
	 */
	private function run_state_key(): string {
		return 'health_run_' . get_current_user_id();
	}

	/**
	 * Whether a check status should participate in scoring.
	 *
	 * Skipped, errored, and unfinished checks deduct nothing — and must not be
	 * presented as passing either.
	 *
	 * @param string $status Check status.
	 * @return bool
	 */
	private function counts_toward_score( string $status ): bool {
		return in_array( $status, [ 'running', 'done' ], true );
	}

	/**
	 * Resolve the severity of a check, preferring stored data over the instance.
	 *
	 * @param string               $id     Check id.
	 * @param array<string, mixed> $result Stored check result.
	 * @return string
	 */
	private function check_severity( string $id, array $result ): string {
		if ( isset( $result['severity'] ) && in_array( $result['severity'], Health_Check_Base::SEVERITIES, true ) ) {
			return (string) $result['severity'];
		}

		$check = $this->get_check( $id );

		return null !== $check ? $check->get_severity() : Health_Check_Base::SEVERITY_NOTICE;
	}

	/**
	 * Sort issues most severe first, preserving relative order within a severity.
	 *
	 * @param array<int, array<string, mixed>> $issues Issues.
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_by_severity( array $issues ): array {
		$rank = [
			Health_Check_Base::SEVERITY_CRITICAL => 0,
			Health_Check_Base::SEVERITY_WARNING  => 1,
			Health_Check_Base::SEVERITY_NOTICE   => 2,
		];

		$indexed = [];
		foreach ( array_values( $issues ) as $position => $issue ) {
			$indexed[] = [
				'rank'     => $rank[ $issue['severity'] ?? '' ] ?? 2,
				'position' => $position,
				'issue'    => $issue,
			];
		}

		usort(
			$indexed,
			static function ( array $a, array $b ): int {
				if ( $a['rank'] === $b['rank'] ) {
					return $a['position'] <=> $b['position'];
				}
				return $a['rank'] <=> $b['rank'];
			}
		);

		return array_column( $indexed, 'issue' );
	}

	/**
	 * Score of the most recent history row, before this run is appended.
	 *
	 * @return int|null
	 */
	private function get_previous_score(): ?int {
		$history = $this->get_history( 1 );

		return isset( $history[0]['score'] ) ? (int) $history[0]['score'] : null;
	}

	/**
	 * Append a history row.
	 *
	 * Only per-check counts are stored — this table is append-only and would
	 * grow without bound if it held issue detail.
	 *
	 * @param int                  $score   Overall score.
	 * @param array<string, mixed> $summary Per-check counts.
	 * @return void
	 */
	private function insert_history_row( int $score, array $summary ): void {
		global $wpdb;

		$encoded = wp_json_encode( $summary );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$wpdb->prefix . 'mysitehand_health_history',
			[
				'scanned_at' => current_time( 'mysql' ),
				'score'      => max( 0, min( 100, $score ) ),
				'summary'    => false !== $encoded ? $encoded : '{}',
			],
			[ '%s', '%d', '%s' ]
		);
	}
}
