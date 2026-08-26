<?php
/**
 * Site Health check: media that nothing appears to use.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Check: Orphan Media.
 *
 * Unattached attachments that do not appear in any published post's content.
 *
 * This check CANNOT be made exact. Page builders (Elementor, Divi, Beaver
 * Builder) store image references in serialized meta rather than post_content,
 * so images used in those layouts can look abandoned. Every design decision
 * here leans the same way: over-match rather than under-match, stay at notice
 * severity, say so in the description, and never offer a bulk delete.
 *
 * References are collected ONCE per run into an index of filenames and
 * attachment IDs, rather than running two LIKE scans per attachment. On a
 * library of a couple of thousand files that is the difference between a scan
 * that takes seconds and one that takes minutes.
 */
class Check_Orphan_Media extends Health_Check_Base {

	/**
	 * Seconds a single batch may spend before yielding.
	 *
	 * @var int
	 */
	private const BATCH_TIME_BUDGET = 10;

	/**
	 * Seconds the reference index may take to build.
	 *
	 * @var int
	 */
	private const INDEX_TIME_BUDGET = 12;

	/**
	 * Postmeta rows read per keyset page while indexing.
	 *
	 * @var int
	 */
	private const INDEX_PAGE_SIZE = 500;

	/**
	 * Hard ceiling on postmeta rows examined while indexing.
	 *
	 * @var int
	 */
	private const INDEX_MAX_ROWS = 100000;

	/**
	 * File extensions worth recognising inside content.
	 *
	 * @var string
	 */
	private const FILE_EXTENSIONS = 'jpe?g|png|gif|webp|avif|svg|bmp|ico|tiff?|heic|pdf|mp4|m4v|mov|webm|mp3|m4a|wav|ogg|zip|rar|docx?|xlsx?|pptx?|csv|txt';

	/**
	 * Cache manager instance.
	 *
	 * @var Cache_Manager
	 */
	private Cache_Manager $cache;

	/**
	 * Constructor.
	 *
	 * @param Cache_Manager $cache Cache manager, used for the reference index.
	 */
	public function __construct( Cache_Manager $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'orphan-media';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Orphan Media', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Finds unattached files that no published content seems to reference. Images placed with a page builder such as Elementor or Divi can appear here by mistake, so review each one before deleting it.', 'my-site-hand' );
	}

	/**
	 * Default severity for issues from this check.
	 *
	 * Never raise this. The check is inherently imprecise.
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
	 * Examine one batch of unattached attachments.
	 *
	 * @param int $offset Offset to resume from.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		global $wpdb;

		$batch_size = $this->get_batch_size();
		$index      = 0 === $offset ? $this->build_reference_index() : $this->get_reference_index();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.ID, a.post_title, meta.meta_value AS attachment_metadata
				FROM {$wpdb->posts} AS a
				LEFT JOIN {$wpdb->postmeta} AS meta
					ON meta.post_id = a.ID AND meta.meta_key = '_wp_attachment_metadata'
				WHERE a.post_type = 'attachment'
					AND a.post_status != 'trash'
					AND a.post_parent = 0
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

		$reserved = $this->get_reserved_ids( wp_list_pluck( $rows, 'ID' ) );
		$deadline = microtime( true ) + self::BATCH_TIME_BUDGET;

		$issues  = [];
		$scanned = 0;

		foreach ( $rows as $row ) {
			++$scanned;

			$attachment_id = (int) $row['ID'];
			$metadata      = maybe_unserialize( $row['attachment_metadata'] );
			$metadata      = is_array( $metadata ) ? $metadata : [];

			if ( ! isset( $reserved[ $attachment_id ] ) && ! $this->is_referenced( $attachment_id, $metadata, $index ) ) {
				$issues[] = $this->make_issue(
					[
						'title'     => $this->resolve_filename( $attachment_id, $metadata, (string) $row['post_title'] ),
						'context'   => __( 'Not attached to any post', 'my-site-hand' ),
						'link'      => $this->edit_link( $attachment_id ),
						'object_id' => $attachment_id,
						'fix_type'  => 'delete',
						'fix_meta'  => [
							'attachment_id' => $attachment_id,
						],
						// Deleting media is far too destructive to hand to an AI.
						'fixable'   => false,
						'ability'   => null,
					]
				);
			}

			if ( microtime( true ) >= $deadline && $scanned < count( $rows ) ) {
				break;
			}
		}

		// A short batch caused by the time guard is not the end of the check.
		$done = $scanned === count( $rows ) && $scanned < $batch_size;

		return $this->make_result( $done, $offset + $scanned, $scanned, $issues );
	}

	/**
	 * Attachment IDs used in ways that never appear in post content.
	 *
	 * Featured images, the site logo, and the site icon are the three biggest
	 * sources of false positives, and all three are cheap to rule out.
	 *
	 * @param array<int, mixed> $ids Attachment IDs in this batch.
	 * @return array<int, bool> Keyed by attachment ID.
	 */
	private function get_reserved_ids( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );

		if ( empty( $ids ) ) {
			return [];
		}

		$reserved     = [];
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a generated list of %d, one per ID.
		$thumbnails = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
				WHERE meta_key = '_thumbnail_id' AND meta_value IN ( {$placeholders} )",
				...$ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		foreach ( (array) $thumbnails as $thumbnail_id ) {
			$reserved[ (int) $thumbnail_id ] = true;
		}

		$custom_logo = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo > 0 ) {
			$reserved[ $custom_logo ] = true;
		}

		$site_icon = (int) get_option( 'site_icon' );
		if ( $site_icon > 0 ) {
			$reserved[ $site_icon ] = true;
		}

		return $reserved;
	}

	/**
	 * Whether anything on the site appears to reference this attachment.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $metadata      Unserialized attachment metadata.
	 * @param array<string, mixed> $index         Reference index.
	 * @return bool
	 */
	private function is_referenced( int $attachment_id, array $metadata, array $index ): bool {
		if ( isset( $index['ids'][ $attachment_id ] ) ) {
			return true;
		}

		$stem = $this->filename_stem( $attachment_id, $metadata );

		if ( null !== $stem && isset( $index['stems'][ $stem ] ) ) {
			return true;
		}

		// The index could not read every meta row on this site, so fall back to
		// asking the database directly rather than risk telling someone to
		// delete a file they are using.
		if ( ! empty( $index['truncated'] ) ) {
			return $this->is_referenced_in_meta( $attachment_id, $stem );
		}

		return false;
	}

	/**
	 * Direct database fallback used only when the index is incomplete.
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string|null $stem          Filename stem.
	 * @return bool
	 */
	private function is_referenced_in_meta( int $attachment_id, ?string $stem ): bool {
		global $wpdb;

		$patterns = [
			'%' . $wpdb->esc_like( '"id":' . $attachment_id ) . '%',
			'%' . $wpdb->esc_like( '"id":"' . $attachment_id . '"' ) . '%',
		];

		if ( null !== $stem ) {
			$patterns[] = '%' . $wpdb->esc_like( $stem ) . '%';
		}

		$where = implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_value LIKE %s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where is a generated list of "meta_value LIKE %s", one per pattern.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key != '_wp_attachment_metadata'
					AND meta_key != '_wp_attached_file'
					AND ( {$where} )
				LIMIT 1",
				...$patterns
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return null !== $found;
	}

	/**
	 * Build the reference index and cache it for the run.
	 *
	 * @return array<string, mixed>
	 */
	private function build_reference_index(): array {
		global $wpdb;

		$index = [
			'ids'       => [],
			'stems'     => [],
			'truncated' => false,
		];

		$deadline = microtime( true ) + self::INDEX_TIME_BUDGET;

		// Published content, read in pages so a large site cannot exhaust memory.
		$last_id = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_content FROM {$wpdb->posts}
					WHERE ID > %d
						AND post_status = 'publish'
						AND post_type NOT IN ( 'attachment', 'revision' )
						AND post_content != ''
					ORDER BY ID ASC
					LIMIT %d",
					$last_id,
					200
				),
				ARRAY_A
			);

			if ( ! is_array( $rows ) || empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$last_id = (int) $row['ID'];
				$this->harvest( (string) $row['post_content'], $index );
			}

			if ( microtime( true ) >= $deadline ) {
				$index['truncated'] = true;
				break;
			}
		} while ( true );

		// Meta rows that could plausibly hold a media reference.
		$last_meta = 0;
		$examined  = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, meta_value FROM {$wpdb->postmeta}
					WHERE meta_id > %d
						AND meta_key NOT IN ( '_wp_attachment_metadata', '_wp_attached_file', '_edit_lock', '_edit_last' )
						AND ( meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s )
					ORDER BY meta_id ASC
					LIMIT %d",
					$last_meta,
					'%' . $wpdb->esc_like( 'uploads' ) . '%',
					'%' . $wpdb->esc_like( '"id"' ) . '%',
					'%' . $wpdb->esc_like( 'wp-image-' ) . '%',
					self::INDEX_PAGE_SIZE
				),
				ARRAY_A
			);

			if ( ! is_array( $rows ) || empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$last_meta = (int) $row['meta_id'];
				$this->harvest( (string) $row['meta_value'], $index );
			}

			$examined += count( $rows );

			if ( microtime( true ) >= $deadline || $examined >= self::INDEX_MAX_ROWS ) {
				$index['truncated'] = true;
				break;
			}
		} while ( true );

		$this->cache->set( $this->index_key(), $index, Site_Health_Scanner::RUN_STATE_TTL );

		return $index;
	}

	/**
	 * Get the cached reference index, rebuilding it if it has expired.
	 *
	 * @return array<string, mixed>
	 */
	private function get_reference_index(): array {
		$index = $this->cache->get( $this->index_key() );

		if ( is_array( $index ) && isset( $index['ids'], $index['stems'] ) ) {
			return $index;
		}

		return $this->build_reference_index();
	}

	/**
	 * Cache key for the current user's reference index.
	 *
	 * @return string
	 */
	private function index_key(): string {
		return 'health_media_refs_' . get_current_user_id();
	}

	/**
	 * Pull every attachment ID and filename out of a blob of text.
	 *
	 * @param string               $text  Post content or meta value.
	 * @param array<string, mixed> $index Index being built, by reference.
	 * @return void
	 */
	private function harvest( string $text, array &$index ): void {
		if ( '' === $text ) {
			return;
		}

		// Classic editor image classes and block editor / builder JSON.
		if ( preg_match_all( '/wp-image-(\d+)/i', $text, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$index['ids'][ (int) $id ] = true;
			}
		}

		if ( preg_match_all( '/"id"\s*:\s*"?(\d+)"?/i', $text, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$index['ids'][ (int) $id ] = true;
			}
		}

		// Serialized builder data: 'id' => 123 / "id";i:123.
		if ( preg_match_all( '/["\']id["\']\s*(?:=>|;\s*i:)\s*(\d+)/i', $text, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$index['ids'][ (int) $id ] = true;
			}
		}

		if ( preg_match_all( '/\[gallery[^\]]*ids\s*=\s*["\']([\d,\s]+)["\']/i', $text, $matches ) ) {
			foreach ( $matches[1] as $list ) {
				foreach ( preg_split( '/[,\s]+/', $list ) ?: [] as $id ) {
					if ( '' !== $id ) {
						$index['ids'][ (int) $id ] = true;
					}
				}
			}
		}

		// Any filename with a media-ish extension, wherever it appears.
		if ( preg_match_all( '#[^/"\'\s\\\\<>()]+\.(?:' . self::FILE_EXTENSIONS . ')#i', $text, $matches ) ) {
			foreach ( $matches[0] as $filename ) {
				$stem = $this->normalize_stem( $filename );

				if ( null !== $stem ) {
					$index['stems'][ $stem ] = true;
				}
			}
		}
	}

	/**
	 * Reduce a filename to a comparable stem.
	 *
	 * Strips the extension and any WordPress size suffix, so
	 * "hero-banner-300x200.jpg" and "hero-banner.jpg" compare equal.
	 *
	 * @param string $filename File name, with or without a path.
	 * @return string|null
	 */
	private function normalize_stem( string $filename ): ?string {
		$basename = wp_basename( $filename );
		$stem     = pathinfo( $basename, PATHINFO_FILENAME );

		if ( ! is_string( $stem ) || '' === $stem ) {
			return null;
		}

		// Drop a trailing -1024x768 style size suffix.
		$stem = preg_replace( '/-\d+x\d+$/', '', $stem );

		if ( ! is_string( $stem ) || '' === $stem ) {
			return null;
		}

		return strtolower( rawurldecode( $stem ) );
	}

	/**
	 * Filename stem for an attachment, normalized the same way as the index.
	 *
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string, mixed> $metadata      Unserialized attachment metadata.
	 * @return string|null
	 */
	private function filename_stem( int $attachment_id, array $metadata ): ?string {
		$file = ! empty( $metadata['file'] ) ? (string) $metadata['file'] : (string) get_attached_file( $attachment_id );

		if ( '' === $file ) {
			return null;
		}

		return $this->normalize_stem( $file );
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

		$path = get_attached_file( $attachment_id );

		if ( $path && is_string( $path ) ) {
			return wp_basename( $path );
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
