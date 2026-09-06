<?php
/**
 * Site Health check: published content below a word threshold.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Check: Thin Content.
 *
 * A NOTICE, and it stays a notice. Short pages are frequently correct: contact
 * pages, thank-you pages, landing pages, and anything whose job is one link and
 * one sentence. This check counts words; it cannot read, and it does not know
 * which of your pages are supposed to be short.
 *
 * Everything whose word count is meaningless is excluded before counting:
 * attachments, revisions, non-public post types, post types with no editor, and
 * any post that is mostly a shortcode or a block rendering something from
 * elsewhere. A gallery page with eleven words is not thin content, it is a
 * gallery.
 */
class Check_Thin_Content extends Health_Check_Base {

	/**
	 * Default word count below which content is reported.
	 *
	 * @var int
	 */
	private const DEFAULT_THRESHOLD = 300;

	/**
	 * Block names that render content this check cannot see.
	 *
	 * A post built out of these has no meaningful word count.
	 *
	 * @var array<int, string>
	 */
	private const DYNAMIC_BLOCKS = [
		'core/embed',
		'core/gallery',
		'core/shortcode',
		'core/html',
		'core/query',
		'core/post-content',
		'core/template-part',
		'core/latest-posts',
		'core/rss',
		'core/file',
		'core/video',
		'core/audio',
		'core/button',
		'core/buttons',
	];

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'thin-content';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Thin Content', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Lists published posts and pages with very few words, which search engines tend to rank poorly. Read this one with judgement: a contact page, a thank-you page or a landing page is supposed to be short, and there is nothing wrong with it. Pages built mostly from a shortcode, a gallery or an embed are left out entirely, because counting their words tells you nothing.', 'my-site-hand' );
	}

	/**
	 * Default severity for issues from this check.
	 *
	 * Never raise this. See the class docblock.
	 *
	 * @return string
	 */
	public function get_severity(): string {
		return self::SEVERITY_NOTICE;
	}

	/**
	 * Items examined per batch.
	 *
	 * Fifty rather than a hundred: parse_blocks() over long posts is the cost
	 * here, and a batch has to stay well clear of the PHP time limit on the
	 * slowest host this plugin runs on, not just on a fast one.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 50;
	}

	/**
	 * Only applicable when there is a post type worth counting words in.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return ! empty( $this->get_post_types() );
	}

	/**
	 * Examine one batch of published content.
	 *
	 * @param int $offset Offset to resume from.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		global $wpdb;

		$post_types = $this->get_post_types();

		if ( empty( $post_types ) ) {
			return $this->make_result( true, null, 0, [] );
		}

		$batch_size = $this->get_batch_size();
		$threshold  = $this->get_threshold();

		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$arguments    = array_merge( $post_types, [ $batch_size, $offset ] );

		// $placeholders is a generated list of %s and never user input; every value
		// still travels as a prepare() argument. The placeholder sniff counts only
		// the tokens it can see in the literal, so it cannot count these.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type, p.post_content
				FROM {$wpdb->posts} AS p
				WHERE p.post_type IN ( {$placeholders} )
					AND p.post_status = 'publish'
				ORDER BY p.ID ASC
				LIMIT %d OFFSET %d",
				$arguments
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return $this->make_result( true, null, 0, [] );
		}

		$issues = [];

		foreach ( $rows as $row ) {
			$post_id = (int) $row['ID'];
			$content = (string) $row['post_content'];

			if ( $this->is_dynamic( $content ) ) {
				continue;
			}

			$words = $this->count_words( $content );

			if ( $words >= $threshold ) {
				continue;
			}

			$issues[] = $this->make_issue(
				[
					'title'     => sprintf(
						/* translators: 1: post title, 2: number of words in the post */
						_n(
							'%1$s (%2$d word)',
							'%1$s (%2$d words)',
							$words,
							'my-site-hand'
						),
						$this->post_title( $post_id, (string) $row['post_title'] ),
						$words
					),
					'context'   => sprintf(
						/* translators: %d: the word count threshold */
						__( 'Under %d words — fine if the page is meant to be short', 'my-site-hand' ),
						$threshold
					),
					'link'      => $this->edit_link( $post_id ),
					'object_id' => $post_id,
					// Writing the missing words is the fix. There is nothing to
					// automate here.
					'fix_type'  => null,
					'fix_meta'  => [
						'post_id'    => $post_id,
						'word_count' => $words,
					],
					'fixable'   => true,
					'ability'   => 'my-site-hand/update-post',
				]
			);
		}

		$scanned = count( $rows );
		$done    = $scanned < $batch_size;

		return $this->make_result( $done, $offset + $scanned, $scanned, $issues );
	}

	/**
	 * Word count threshold.
	 *
	 * @return int
	 */
	private function get_threshold(): int {
		/**
		 * Filter the word count below which published content is reported.
		 *
		 * @param int $threshold Word count.
		 */
		$threshold = (int) apply_filters( 'my_site_hand_health_thin_content_threshold', self::DEFAULT_THRESHOLD );

		return max( 1, $threshold );
	}

	/**
	 * Post types worth counting words in.
	 *
	 * Public types only, and only those with an editor. Attachments and
	 * revisions are never included: an attachment has no body to count, and a
	 * revision is not something a visitor ever reads.
	 *
	 * @return array<int, string>
	 */
	private function get_post_types(): array {
		$types = get_post_types( [ 'public' => true ], 'names' );
		$types = is_array( $types ) ? array_values( $types ) : [];

		$excluded = [ 'attachment', 'revision', 'nav_menu_item' ];

		$types = array_values(
			array_filter(
				$types,
				static function ( string $type ) use ( $excluded ): bool {
					if ( in_array( $type, $excluded, true ) ) {
						return false;
					}

					// A post type with no editor has no long-form body to
					// measure, so a low word count means nothing.
					return post_type_supports( $type, 'editor' );
				}
			)
		);

		/**
		 * Filter the post types the thin content check examines.
		 *
		 * @param array<int, string> $types Post type names.
		 */
		$types = (array) apply_filters( 'my_site_hand_health_thin_content_post_types', $types );

		return array_values( array_filter( array_map( 'strval', $types ) ) );
	}

	/**
	 * Count words in post content.
	 *
	 * @param string $content Raw post content.
	 * @return int
	 */
	private function count_words( string $content ): int {
		$text = strip_shortcodes( $content );
		$text = wp_strip_all_tags( $text, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );

		if ( '' === $text ) {
			return 0;
		}

		// str_word_count() only understands single-byte letters, so it
		// undercounts badly in any language that is not English. Splitting on
		// whitespace is cruder but treats every language the same.
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $words ) ? count( $words ) : 0;
	}

	/**
	 * Whether a post is mostly something this check cannot read.
	 *
	 * A page whose body is a form, a gallery, an embed or a shortcode has no
	 * word count worth reporting, so it is left out rather than flagged.
	 *
	 * @param string $content Raw post content.
	 * @return bool
	 */
	private function is_dynamic( string $content ): bool {
		if ( '' === trim( $content ) ) {
			return false;
		}

		if ( $this->has_any_shortcode( $content ) ) {
			return true;
		}

		if ( ! function_exists( 'parse_blocks' ) || ! str_contains( $content, '<!-- wp:' ) ) {
			return false;
		}

		foreach ( parse_blocks( $content ) as $block ) {
			if ( $this->block_is_dynamic( $block ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the content uses any registered shortcode.
	 *
	 * @param string $content Raw post content.
	 * @return bool
	 */
	private function has_any_shortcode( string $content ): bool {
		if ( ! str_contains( $content, '[' ) ) {
			return false;
		}

		global $shortcode_tags;

		if ( ! is_array( $shortcode_tags ) || empty( $shortcode_tags ) ) {
			return false;
		}

		foreach ( array_keys( $shortcode_tags ) as $tag ) {
			if ( has_shortcode( $content, (string) $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a parsed block (or one of its children) renders external content.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return bool
	 */
	private function block_is_dynamic( array $block ): bool {
		$name = (string) ( $block['blockName'] ?? '' );

		if ( '' !== $name ) {
			if ( in_array( $name, self::DYNAMIC_BLOCKS, true ) ) {
				return true;
			}

			// Legacy embeds, and every third-party form builder, register
			// under their own namespace.
			if ( str_starts_with( $name, 'core-embed/' ) ) {
				return true;
			}

			if ( str_contains( $name, 'form' ) || str_contains( $name, 'gallery' ) || str_contains( $name, 'slider' ) ) {
				return true;
			}
		}

		foreach ( (array) ( $block['innerBlocks'] ?? [] ) as $inner ) {
			if ( is_array( $inner ) && $this->block_is_dynamic( $inner ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Display title for a post, falling back to its ID.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Stored post title.
	 * @return string
	 */
	private function post_title( int $post_id, string $title ): string {
		if ( '' !== trim( $title ) ) {
			return $title;
		}

		return sprintf(
			/* translators: %d: post ID */
			__( 'Untitled (#%d)', 'my-site-hand' ),
			$post_id
		);
	}

	/**
	 * Admin URL for the post edit screen.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function edit_link( int $post_id ): string {
		$link = get_edit_post_link( $post_id, 'raw' );

		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}

		return admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	}
}
