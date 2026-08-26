<?php
/**
 * Abstract base class for Site Health checks.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;

/**
 * Health Check Base abstract class.
 *
 * Every Site Health check extends this class. Checks are RESUMABLE by design:
 * the scan runs from the browser, one check at a time, in separate HTTP
 * requests. A single check therefore receives an offset, processes a bounded
 * batch, and reports whether more work remains. A check must never assume it
 * can walk an entire site inside one request.
 *
 * Note: this is the site-inspection layer. It is unrelated to the AI Audit Log,
 * which records what an AI assistant did.
 */
abstract class Health_Check_Base {

	/**
	 * Severity: blocking or damaging problem.
	 *
	 * @var string
	 */
	public const SEVERITY_CRITICAL = 'critical';

	/**
	 * Severity: should be fixed, not urgent.
	 *
	 * @var string
	 */
	public const SEVERITY_WARNING = 'warning';

	/**
	 * Severity: informational housekeeping.
	 *
	 * @var string
	 */
	public const SEVERITY_NOTICE = 'notice';

	/**
	 * Every severity the scoring layer understands.
	 *
	 * @var array<int, string>
	 */
	public const SEVERITIES = [
		self::SEVERITY_CRITICAL,
		self::SEVERITY_WARNING,
		self::SEVERITY_NOTICE,
	];

	/**
	 * Repair strategies Quick Fix knows how to perform.
	 *
	 * @var array<int, string>
	 */
	public const FIX_TYPES = [
		'inline_text',
		'inline_url',
		'delete',
		'external_link',
	];

	/**
	 * Stable machine id for this check.
	 *
	 * Used as an array key in run state and stored reports, and sent by the
	 * browser in REST requests. Never change it once shipped.
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * Human-readable, translated check name.
	 *
	 * @return string
	 */
	abstract public function get_label(): string;

	/**
	 * One-line translated description of what the check looks for.
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Default severity for issues this check reports.
	 *
	 * @return string One of the SEVERITY_* constants.
	 */
	abstract public function get_severity(): string;

	/**
	 * Process one bounded batch, starting at $offset.
	 *
	 * Implementations MUST return exactly:
	 *
	 *     [
	 *         'done'        => (bool)  No more work for this check.
	 *         'next_offset' => (?int)  Null when done.
	 *         'scanned'     => (int)   Items examined in THIS batch.
	 *         'issues'      => (array) Issues found in THIS batch.
	 *     ]
	 *
	 * @param int $offset Item offset to resume from.
	 * @return array<string, mixed>
	 */
	abstract public function run( int $offset = 0 ): array;

	/**
	 * Whether this check can produce a meaningful result on this site.
	 *
	 * A check that cannot (for example, an SEO check with no SEO plugin
	 * installed) must report itself inapplicable rather than reporting zero
	 * problems, which would be misleading.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return true;
	}

	/**
	 * Number of items to examine per batch.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 100;
	}

	/**
	 * Build a single issue array in the canonical shape.
	 *
	 * The fix_type and fix_meta keys are consumed by Quick Fix, and fixable and
	 * ability by the AI repair panel. All four are populated at scan time
	 * because adding them later would mean migrating every stored report.
	 *
	 * @param array<string, mixed> $args {
	 *     Issue data.
	 *
	 *     @type string      $title     Required. The specific problem, e.g. 'hero-banner.jpg (4.2 MB)'.
	 *     @type string|null $context   Where it was found, e.g. 'Used in: About Us'.
	 *     @type string      $severity  Defaults to the check severity.
	 *     @type string|null $link      Admin URL to inspect the item.
	 *     @type int|null    $object_id Related object ID.
	 *     @type string|null $fix_type  One of FIX_TYPES, or null when not repairable in place.
	 *     @type array       $fix_meta  Data Quick Fix needs to perform the repair.
	 *     @type bool        $fixable   Whether an AI ability could repair this.
	 *     @type string|null $ability   Which ability, e.g. 'my-site-hand/update-post'.
	 * }
	 * @throws InvalidArgumentException When 'title' is missing.
	 * @return array<string, mixed>
	 */
	protected function make_issue( array $args ): array {
		if ( ! isset( $args['title'] ) || '' === trim( (string) $args['title'] ) ) {
			throw new InvalidArgumentException( 'A health issue requires a non-empty "title".' );
		}

		$severity = $args['severity'] ?? $this->get_severity();
		if ( ! in_array( $severity, self::SEVERITIES, true ) ) {
			$severity = $this->get_severity();
		}

		$fix_type = $args['fix_type'] ?? null;
		if ( null !== $fix_type && ! in_array( $fix_type, self::FIX_TYPES, true ) ) {
			$fix_type = null;
		}

		return [
			'title'     => (string) $args['title'],
			'context'   => isset( $args['context'] ) && '' !== $args['context'] ? (string) $args['context'] : null,
			'severity'  => $severity,
			'link'      => isset( $args['link'] ) && '' !== $args['link'] ? (string) $args['link'] : null,
			'object_id' => isset( $args['object_id'] ) ? (int) $args['object_id'] : null,
			'fix_type'  => $fix_type,
			'fix_meta'  => isset( $args['fix_meta'] ) ? (array) $args['fix_meta'] : [],
			'fixable'   => ! empty( $args['fixable'] ),
			'ability'   => isset( $args['ability'] ) && '' !== $args['ability'] ? (string) $args['ability'] : null,
		];
	}

	/**
	 * Build the canonical run() return value.
	 *
	 * Every check funnels its result through here so the shape cannot drift.
	 *
	 * @param bool                             $done        No more work for this check.
	 * @param int|null                         $next_offset Offset to resume from, null when done.
	 * @param int                              $scanned     Items examined in this batch.
	 * @param array<int, array<string, mixed>> $issues Issues found in this batch.
	 * @return array<string, mixed>
	 */
	protected function make_result( bool $done, ?int $next_offset, int $scanned, array $issues = [] ): array {
		return [
			'done'        => $done,
			'next_offset' => $done ? null : $next_offset,
			'scanned'     => $scanned,
			'issues'      => array_values( $issues ),
		];
	}
}
