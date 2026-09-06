<?php
/**
 * Tokenized, public, read-only snapshots of a Site Health report.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Shared Reports class.
 *
 * This is the only part of the plugin that publishes anything to the open
 * internet, so read the threat model before changing it: someone who obtains a
 * share link must learn nothing that helps them attack the site.
 *
 * Two rules follow, and neither is negotiable.
 *
 * REDACT. The payload is BUILT, field by field, by build_payload(). It is not
 * the stored report with dangerous keys filtered out at render time, and it
 * must never become that. The difference matters: with an allowlist, a field
 * added to the scan next year is absent from the public page until somebody
 * decides otherwise. With a denylist, it is published the day it is added and
 * nobody notices. What survives redaction is a score, a band, and one label,
 * status, severity and COUNT per check. No paths, no URLs, no IDs, no titles,
 * no versions, no plugin names.
 *
 * Two checks are dropped entirely rather than counted: exposed-files and
 * xmlrpc-enabled. Publishing those is publishing a vulnerability report about
 * your own server.
 *
 * EXPIRE. Every link has an end date. There is no never option, because a link
 * that lives forever is a link nobody remembers is out there.
 *
 * The payload is also a SNAPSHOT taken when the link is created. Re-scanning
 * the site does not change what a link already sent shows. A document that
 * rewrites itself after you have sent it is not a document.
 */
class Shared_Reports {

	/**
	 * Payload format version. Bump when the public shape changes.
	 *
	 * @var int
	 */
	public const PAYLOAD_VERSION = 1;

	/**
	 * Expiry windows offered, in days.
	 *
	 * @var array<int, int>
	 */
	public const EXPIRY_CHOICES = [ 7, 30, 90 ];

	/**
	 * Expiry used when none is chosen.
	 *
	 * @var int
	 */
	public const DEFAULT_EXPIRY_DAYS = 30;

	/**
	 * Checks that are never included in a public report, at any count.
	 *
	 * @var array<int, string>
	 */
	public const EXCLUDED_CHECKS = [
		'exposed-files',
		'xmlrpc-enabled',
	];

	/**
	 * Requests one IP address may make to the public endpoint per hour.
	 *
	 * @var int
	 */
	public const VIEW_RATE_LIMIT = 30;

	/**
	 * Most links one site keeps at once.
	 *
	 * @var int
	 */
	public const MAX_ACTIVE_LINKS = 25;

	/**
	 * Create a share link from a stored report.
	 *
	 * The raw token is returned once and never stored. Only its SHA-256 hash
	 * goes into the database, exactly as Auth_Manager does for API tokens.
	 *
	 * @param array<string, mixed> $report Stored report.
	 * @param int                  $days   Days until expiry.
	 * @return array{token: string, id: int, url: string, expires_at: string}|\WP_Error
	 */
	public function create( array $report, int $days ): array|\WP_Error {
		global $wpdb;

		if ( ! in_array( $days, self::EXPIRY_CHOICES, true ) ) {
			$days = self::DEFAULT_EXPIRY_DAYS;
		}

		if ( $this->count_active() >= self::MAX_ACTIVE_LINKS ) {
			return new \WP_Error(
				'my_site_hand_too_many_links',
				sprintf(
					/* translators: %d: maximum number of share links allowed at once */
					__( 'You already have %d share links. Revoke one before creating another.', 'my-site-hand' ),
					self::MAX_ACTIVE_LINKS
				),
				[ 'status' => 400 ]
			);
		}

		$payload = $this->build_payload( $report );
		$encoded = wp_json_encode( $payload );

		if ( false === $encoded ) {
			return new \WP_Error(
				'my_site_hand_share_encode_failed',
				__( 'This report could not be prepared for sharing.', 'my-site-hand' ),
				[ 'status' => 500 ]
			);
		}

		$raw_token  = bin2hex( random_bytes( 32 ) );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$this->table(),
			[
				'token_hash' => hash( 'sha256', $raw_token ),
				'payload'    => $encoded,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'expires_at' => $expires_at,
				'view_count' => 0,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( ! $inserted ) {
			return new \WP_Error(
				'my_site_hand_share_insert_failed',
				__( 'The share link could not be saved.', 'my-site-hand' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'id'         => (int) $wpdb->insert_id,
			'token'      => $raw_token,
			'url'        => $this->public_url( $raw_token ),
			'expires_at' => $expires_at,
		];
	}

	/**
	 * Build the redacted public payload.
	 *
	 * Every value here is written out deliberately. Nothing is copied across
	 * from the stored report wholesale, and nothing may be added to this
	 * method without asking what an attacker would do with it.
	 *
	 * @param array<string, mixed> $report Stored report.
	 * @return array<string, mixed>
	 */
	public function build_payload( array $report ): array {
		$scanner = Plugin::get_instance()->get_site_health_scanner();
		$score   = max( 0, min( 100, (int) ( $report['score'] ?? 0 ) ) );
		$band    = $scanner->get_score_band( $score );

		$previous = isset( $report['previous_score'] ) && null !== $report['previous_score']
			? max( 0, min( 100, (int) $report['previous_score'] ) )
			: null;

		$checks = [];

		foreach ( (array) ( $report['checks'] ?? [] ) as $check_id => $entry ) {
			$check_id = (string) $check_id;

			// The two security checks are dropped, not counted. "This site has
			// 3 exposed files" is a finding an attacker would act on.
			if ( in_array( $check_id, self::EXCLUDED_CHECKS, true ) ) {
				continue;
			}

			if ( ! is_array( $entry ) ) {
				continue;
			}

			$severity = (string) ( $entry['severity'] ?? Health_Check_Base::SEVERITY_NOTICE );

			$checks[] = [
				// The check's own translated name. A plugin string, not site
				// data — it says "Broken Links", never which links.
				'label'       => (string) ( $entry['label'] ?? $check_id ),
				'status'      => in_array( (string) ( $entry['status'] ?? '' ), [ 'done', 'skipped', 'error', 'pending', 'running' ], true )
					? (string) $entry['status']
					: 'pending',
				'severity'    => in_array( $severity, Health_Check_Base::SEVERITIES, true )
					? $severity
					: Health_Check_Base::SEVERITY_NOTICE,
				// A count. Never the issues themselves: those carry file
				// names, post IDs, URLs, plugin names and versions.
				'issue_count' => max( 0, (int) ( $entry['issue_count'] ?? 0 ) ),
			];
		}

		return [
			'version'        => self::PAYLOAD_VERSION,
			// Public on every page of the site already, and the recipient has
			// to know which site they are looking at. Disclosed to the admin
			// before they create the link.
			'site_name'      => (string) get_bloginfo( 'name' ),
			'generated_at'   => (int) ( $report['generated_at'] ?? time() ),
			'score'          => $score,
			'band'           => (string) $band['band'],
			'band_label'     => (string) $band['label'],
			'previous_score' => $previous,
			'checks'         => $checks,
		];
	}

	/**
	 * Look up a live link by its raw token.
	 *
	 * Returns null for unknown, expired, and revoked alike. The caller turns
	 * every one of those into the same 404: a different answer for "expired"
	 * than for "never existed" confirms to whoever is probing that they hold a
	 * token that was real once.
	 *
	 * @param string $raw_token Raw token from the URL.
	 * @return array<string, mixed>|null
	 */
	public function find( string $raw_token ): ?array {
		global $wpdb;

		if ( ! $this->is_well_formed( $raw_token ) ) {
			return null;
		}

		$hash = hash( 'sha256', $raw_token );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, token_hash, payload, created_at, expires_at, view_count FROM {$wpdb->prefix}mysitehand_shared_reports WHERE token_hash = %s",
				$hash
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		// The index lookup already matched, so this is belt and braces — but
		// it is the comparison that belongs here, and a later refactor that
		// loosens the query should still land on a constant-time compare.
		if ( ! hash_equals( (string) $row['token_hash'], $hash ) ) {
			return null;
		}

		if ( strtotime( (string) $row['expires_at'] . ' UTC' ) <= time() ) {
			return null;
		}

		$payload = json_decode( (string) $row['payload'], true );

		if ( ! is_array( $payload ) ) {
			return null;
		}

		return [
			'id'         => (int) $row['id'],
			'payload'    => $payload,
			'created_at' => (string) $row['created_at'],
			'expires_at' => (string) $row['expires_at'],
			'view_count' => (int) $row['view_count'],
		];
	}

	/**
	 * Count one view of a link.
	 *
	 * @param int $id Row ID.
	 * @return void
	 */
	public function record_view( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}mysitehand_shared_reports SET view_count = view_count + 1 WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Every link that has not expired, newest first.
	 *
	 * The token hash is deliberately not returned: nothing in the admin needs
	 * it, and a hash on a screen is a hash in a screenshot.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_active(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, created_at, expires_at, view_count FROM {$wpdb->prefix}mysitehand_shared_reports WHERE expires_at > UTC_TIMESTAMP() ORDER BY id DESC",
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map(
			static function ( array $row ): array {
				return [
					'id'         => (int) $row['id'],
					'created_at' => (string) $row['created_at'],
					'expires_at' => (string) $row['expires_at'],
					'view_count' => (int) $row['view_count'],
				];
			},
			$rows
		);
	}

	/**
	 * Revoke a link immediately.
	 *
	 * The row is deleted rather than flagged. There is nothing worth keeping
	 * in it once the link is gone, and a deleted row cannot be un-revoked by
	 * a stray UPDATE.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public function revoke( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );

		return (bool) $deleted;
	}

	/**
	 * Delete expired rows.
	 *
	 * Hooked to the existing daily cleanup cron alongside the audit log and
	 * history pruning — a feature this small does not deserve a cron event of
	 * its own.
	 *
	 * @return int Rows removed.
	 */
	public function purge_expired(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			"DELETE FROM {$wpdb->prefix}mysitehand_shared_reports WHERE expires_at <= UTC_TIMESTAMP()"
		);
	}

	/**
	 * Public URL for a raw token.
	 *
	 * @param string $raw_token Raw token.
	 * @return string
	 */
	public function public_url( string $raw_token ): string {
		return rest_url( 'my-site-hand/v1/health/shared/' . rawurlencode( $raw_token ) );
	}

	/**
	 * Whether a token even looks like one this plugin issued.
	 *
	 * Rejecting the malformed ones before touching the database keeps junk
	 * probes off the query path.
	 *
	 * @param string $raw_token Raw token.
	 * @return bool
	 */
	private function is_well_formed( string $raw_token ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $raw_token );
	}

	/**
	 * Live links currently stored.
	 *
	 * @return int
	 */
	private function count_active(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_shared_reports WHERE expires_at > UTC_TIMESTAMP()"
		);
	}

	/**
	 * Fully qualified table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mysitehand_shared_reports';
	}
}
