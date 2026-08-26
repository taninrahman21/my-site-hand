<?php
/**
 * Site Health check: oversized media files.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Check: Large Media.
 *
 * Finds attachments larger than a threshold so the user can see how much space
 * is recoverable. This is the reference implementation every other check
 * follows — batching, issue construction, and query style all start here.
 */
class Check_Large_Media extends Health_Check_Base {

	/**
	 * Default size threshold in bytes.
	 *
	 * @var int
	 */
	private const DEFAULT_THRESHOLD = MB_IN_BYTES;

	/**
	 * Total bytes of oversized media found so far, across batches.
	 *
	 * Resets when a run restarts at offset zero.
	 *
	 * @var int
	 */
	private int $total_bytes = 0;

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'large-media';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Large Media', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Finds oversized images and files that slow your pages down and fill your storage.', 'my-site-hand' );
	}

	/**
	 * Default severity for issues from this check.
	 *
	 * @return string
	 */
	public function get_severity(): string {
		return self::SEVERITY_NOTICE;
	}

	/**
	 * Items examined per batch.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 100;
	}

	/**
	 * Total size of every oversized file found so far.
	 *
	 * Accumulates across the batches of a single run so the UI can report
	 * reclaimable space rather than a bare count.
	 *
	 * @return int Bytes.
	 */
	public function get_total_bytes(): int {
		return $this->total_bytes;
	}

	/**
	 * Examine one batch of attachments.
	 *
	 * @param int $offset Attachment offset to resume from.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		global $wpdb;

		if ( 0 === $offset ) {
			$this->total_bytes = 0;
		}

		$batch_size = $this->get_batch_size();
		$threshold  = $this->get_threshold();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.ID, a.post_parent, meta.meta_value AS attachment_metadata, parent.post_title AS parent_title
				FROM {$wpdb->posts} AS a
				LEFT JOIN {$wpdb->postmeta} AS meta
					ON meta.post_id = a.ID AND meta.meta_key = '_wp_attachment_metadata'
				LEFT JOIN {$wpdb->posts} AS parent
					ON parent.ID = a.post_parent
				WHERE a.post_type = 'attachment'
					AND a.post_status != 'trash'
				ORDER BY a.ID ASC
				LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return $this->make_result( true, null, 0, [] );
		}

		$this->prime_meta_cache( $rows );

		$issues = [];

		foreach ( $rows as $row ) {
			$attachment_id = (int) $row['ID'];
			$metadata      = maybe_unserialize( $row['attachment_metadata'] );
			$metadata      = is_array( $metadata ) ? $metadata : [];

			$bytes = $this->resolve_filesize( $attachment_id, $metadata );

			if ( null === $bytes || $bytes <= $threshold ) {
				continue;
			}

			$this->total_bytes += $bytes;

			$issues[] = $this->make_issue(
				[
					'title'     => sprintf(
						/* translators: 1: file name, 2: formatted file size */
						__( '%1$s (%2$s)', 'my-site-hand' ),
						$this->resolve_filename( $attachment_id, $metadata ),
						size_format( $bytes, 1 )
					),
					'context'   => $this->resolve_context( $row ),
					'link'      => $this->edit_link( $attachment_id ),
					'object_id' => $attachment_id,
					// There is no safe in-place fix for an oversized file: it
					// has to be re-exported or compressed outside WordPress.
					'fix_type'  => null,
					'fix_meta'  => [
						'attachment_id' => $attachment_id,
						'bytes'         => $bytes,
					],
					'fixable'   => false,
					'ability'   => null,
				]
			);
		}

		$scanned = count( $rows );
		$done    = $scanned < $batch_size;

		return $this->make_result( $done, $offset + $scanned, $scanned, $issues );
	}

	/**
	 * Size threshold in bytes.
	 *
	 * @return int
	 */
	private function get_threshold(): int {
		/**
		 * Filter the size above which an attachment is reported as large.
		 *
		 * @param int $threshold Threshold in bytes.
		 */
		$threshold = (int) apply_filters( 'my_site_hand_health_large_media_threshold', self::DEFAULT_THRESHOLD );

		return max( 1, $threshold );
	}

	/**
	 * Resolve an attachment's size in bytes.
	 *
	 * Reads the size recorded in attachment metadata. filesize() is only used
	 * when that is absent — hitting the filesystem once per attachment would
	 * make this check crawl on a large media library.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $metadata      Unserialized attachment metadata.
	 * @return int|null Bytes, or null when the size cannot be determined.
	 */
	private function resolve_filesize( int $attachment_id, array $metadata ): ?int {
		if ( ! empty( $metadata['filesize'] ) ) {
			return (int) $metadata['filesize'];
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! is_string( $path ) || ! file_exists( $path ) ) {
			return null;
		}

		$bytes = @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Guarded above; a race or unreadable file must not warn.

		return false === $bytes ? null : (int) $bytes;
	}

	/**
	 * Resolve a display filename for an attachment.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $metadata      Unserialized attachment metadata.
	 * @return string
	 */
	private function resolve_filename( int $attachment_id, array $metadata ): string {
		if ( ! empty( $metadata['file'] ) ) {
			return wp_basename( (string) $metadata['file'] );
		}

		$path = get_attached_file( $attachment_id );

		if ( $path && is_string( $path ) ) {
			return wp_basename( $path );
		}

		$title = get_the_title( $attachment_id );

		return '' !== $title ? $title : sprintf(
			/* translators: %d: attachment ID */
			__( 'Attachment #%d', 'my-site-hand' ),
			$attachment_id
		);
	}

	/**
	 * Describe where the attachment is used, when it is attached to a post.
	 *
	 * @param array<string, mixed> $row Query row.
	 * @return string|null
	 */
	private function resolve_context( array $row ): ?string {
		if ( empty( $row['post_parent'] ) || empty( $row['parent_title'] ) ) {
			return null;
		}

		return sprintf(
			/* translators: %s: post title the attachment is attached to */
			__( 'Used in: %s', 'my-site-hand' ),
			(string) $row['parent_title']
		);
	}

	/**
	 * Admin URL for the attachment edit screen.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function edit_link( int $attachment_id ): string {
		$link = get_edit_post_link( $attachment_id, 'raw' );

		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}

		return admin_url( 'post.php?post=' . $attachment_id . '&action=edit' );
	}

	/**
	 * Warm the postmeta cache for rows whose size or filename needs a lookup.
	 *
	 * One query for the batch beats one query per attachment.
	 *
	 * @param array<int, array<string, mixed>> $rows Query rows.
	 * @return void
	 */
	private function prime_meta_cache( array $rows ): void {
		$needs_lookup = [];

		foreach ( $rows as $row ) {
			$metadata = maybe_unserialize( $row['attachment_metadata'] );

			if ( ! is_array( $metadata ) || empty( $metadata['filesize'] ) || empty( $metadata['file'] ) ) {
				$needs_lookup[] = (int) $row['ID'];
			}
		}

		if ( ! empty( $needs_lookup ) ) {
			update_meta_cache( 'post', $needs_lookup );
		}
	}
}
