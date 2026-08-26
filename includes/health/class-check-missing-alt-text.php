<?php
/**
 * Site Health check: images without alternative text.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Check: Missing Alt Text.
 *
 * Finds image attachments with no alternative text. A LEFT JOIN on postmeta is
 * used deliberately: a meta_query with NOT EXISTS is substantially slower on a
 * large media library.
 */
class Check_Missing_Alt_Text extends Health_Check_Base {

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'missing-alt-text';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Missing Alt Text', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Finds images with no alternative text, which screen readers and search engines both rely on.', 'my-site-hand' );
	}

	/**
	 * Default severity for issues from this check.
	 *
	 * @return string
	 */
	public function get_severity(): string {
		return self::SEVERITY_WARNING;
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
	 * Examine one batch of images.
	 *
	 * @param int $offset Offset to resume from.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		global $wpdb;

		$batch_size = $this->get_batch_size();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.ID, a.post_parent, a.post_title, parent.post_title AS parent_title, meta.meta_value AS attachment_metadata
				FROM {$wpdb->posts} AS a
				LEFT JOIN {$wpdb->postmeta} AS alt
					ON alt.post_id = a.ID AND alt.meta_key = '_wp_attachment_image_alt'
				LEFT JOIN {$wpdb->postmeta} AS meta
					ON meta.post_id = a.ID AND meta.meta_key = '_wp_attachment_metadata'
				LEFT JOIN {$wpdb->posts} AS parent
					ON parent.ID = a.post_parent
				WHERE a.post_type = 'attachment'
					AND a.post_status != 'trash'
					AND a.post_mime_type LIKE %s
					AND ( alt.meta_value IS NULL OR TRIM( alt.meta_value ) = '' )
				ORDER BY a.ID ASC
				LIMIT %d OFFSET %d",
				$wpdb->esc_like( 'image/' ) . '%',
				$batch_size,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return $this->make_result( true, null, 0, [] );
		}

		$issues = [];

		foreach ( $rows as $row ) {
			$attachment_id = (int) $row['ID'];
			$metadata      = maybe_unserialize( $row['attachment_metadata'] );
			$metadata      = is_array( $metadata ) ? $metadata : [];

			$issues[] = $this->make_issue(
				[
					'title'     => $this->resolve_filename( $attachment_id, $metadata, (string) $row['post_title'] ),
					'context'   => $this->resolve_context( $row ),
					'link'      => $this->edit_link( $attachment_id ),
					'object_id' => $attachment_id,
					'fix_type'  => 'inline_text',
					'fix_meta'  => [
						'attachment_id' => $attachment_id,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Payload for the repair ability, not a query argument.
						'meta_key'      => '_wp_attachment_image_alt',
					],
					'fixable'   => true,
					'ability'   => 'my-site-hand/update-media-alt-text',
				]
			);
		}

		$scanned = count( $rows );
		$done    = $scanned < $batch_size;

		return $this->make_result( $done, $offset + $scanned, $scanned, $issues );
	}

	/**
	 * Resolve a display filename for an attachment.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $metadata      Unserialized attachment metadata.
	 * @param string               $fallback      Attachment post title.
	 * @return string
	 */
	private function resolve_filename( int $attachment_id, array $metadata, string $fallback ): string {
		if ( ! empty( $metadata['file'] ) ) {
			return wp_basename( (string) $metadata['file'] );
		}

		if ( '' !== $fallback ) {
			return $fallback;
		}

		return sprintf(
			/* translators: %d: attachment ID */
			__( 'Attachment #%d', 'my-site-hand' ),
			$attachment_id
		);
	}

	/**
	 * Describe where the image is used, when it is attached to a post.
	 *
	 * @param array<string, mixed> $row Query row.
	 * @return string|null
	 */
	private function resolve_context( array $row ): ?string {
		if ( empty( $row['post_parent'] ) || empty( $row['parent_title'] ) ) {
			return null;
		}

		return sprintf(
			/* translators: %s: post title the image is attached to */
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
}
