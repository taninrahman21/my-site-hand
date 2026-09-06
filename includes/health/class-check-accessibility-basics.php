<?php
/**
 * Site Health check: structural accessibility problems in post content.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use DOMDocument;
use DOMElement;

/**
 * Check: Accessibility Basics.
 *
 * Four structural problems that can be found by reading markup: links a screen
 * reader announces as nothing, links whose text is "click here", more than one
 * <h1>, and headings that skip a level.
 *
 * IT DOES NOT CHECK COLOUR CONTRAST, which is the single most common
 * accessibility failure on the web. Contrast depends on the stylesheet, the
 * theme, custom properties and whatever the browser resolves at paint time —
 * none of which exists on the server. Claiming to audit accessibility while
 * quietly skipping the biggest category would leave someone believing their
 * site is fine when it is not, so the description says plainly that this covers
 * structural basics only.
 *
 * One issue per post, with the specifics in the context line.
 */
class Check_Accessibility_Basics extends Health_Check_Base {

	/**
	 * Link text that tells a screen reader user nothing.
	 *
	 * @var array<int, string>
	 */
	private const VAGUE_LINK_TEXT = [
		'click here',
		'read more',
		'here',
		'link',
	];

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'accessibility-basics';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Accessibility Basics', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Looks for four structural problems in your content: links that announce nothing to a screen reader, links that only say "click here", more than one main heading, and headings that skip a level. This is a structural check, not a full accessibility audit — it deliberately does not test colour contrast, which is the most common failure of all and cannot be measured on the server. Passing this check does not mean your site is accessible.', 'my-site-hand' );
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
	 * Half what the other content checks use. This one parses the document AND
	 * walks every link and heading in it, so on a site of long, link-heavy posts
	 * a batch of fifty was measured at seven seconds — fine here, uncomfortably
	 * close to the limit on slow shared hosting.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 25;
	}

	/**
	 * Parsing post content needs DOMDocument.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return class_exists( 'DOMDocument' );
	}

	/**
	 * Examine one batch of published posts and pages.
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
				"SELECT p.ID, p.post_title, p.post_content
				FROM {$wpdb->posts} AS p
				WHERE p.post_type IN ( 'post', 'page' )
					AND p.post_status = 'publish'
				ORDER BY p.ID ASC
				LIMIT %d OFFSET %d",
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
			$post_id  = (int) $row['ID'];
			$problems = $this->inspect( (string) $row['post_content'] );

			if ( empty( $problems ) ) {
				continue;
			}

			$issues[] = $this->make_issue(
				[
					'title'     => sprintf(
						/* translators: 1: post title, 2: number of accessibility problems found in it */
						_n(
							'%1$s (%2$d accessibility problem)',
							'%1$s (%2$d accessibility problems)',
							count( $problems ),
							'my-site-hand'
						),
						$this->post_title( $post_id, (string) $row['post_title'] ),
						count( $problems )
					),
					'context'   => implode( __( ' · ', 'my-site-hand' ), $problems ),
					'link'      => $this->edit_link( $post_id ),
					'object_id' => $post_id,
					// Rewriting link text or restructuring someone's headings
					// is an editorial decision, not a mechanical repair.
					'fix_type'  => null,
					'fix_meta'  => [
						'post_id'       => $post_id,
						'problem_count' => count( $problems ),
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
	 * Describe every structural problem found in one post.
	 *
	 * @param string $content Raw post content.
	 * @return array<int, string> Translated summaries, empty when the post is clean.
	 */
	private function inspect( string $content ): array {
		$dom = $this->load_dom( $content );

		if ( null === $dom ) {
			return [];
		}

		$problems = [];

		$empty_links = 0;
		$vague_links = 0;

		foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
			if ( ! $anchor instanceof DOMElement ) {
				continue;
			}

			// An anchor with no href is a target, not a link.
			if ( '' === trim( $anchor->getAttribute( 'href' ) ) ) {
				continue;
			}

			$text = $this->link_text( $anchor );

			if ( '' === $text ) {
				if ( '' === trim( $anchor->getAttribute( 'aria-label' ) )
					&& '' === trim( $anchor->getAttribute( 'title' ) )
					&& '' === trim( $anchor->getAttribute( 'aria-labelledby' ) ) ) {
					++$empty_links;
				}

				continue;
			}

			if ( in_array( strtolower( trim( $text, " \t\n\r\0\x0B.!?:;," ) ), self::VAGUE_LINK_TEXT, true ) ) {
				++$vague_links;
			}
		}

		if ( $empty_links > 0 ) {
			$problems[] = sprintf(
				/* translators: %d: number of links with no readable text */
				_n(
					'%d link a screen reader announces as nothing',
					'%d links a screen reader announces as nothing',
					$empty_links,
					'my-site-hand'
				),
				$empty_links
			);
		}

		if ( $vague_links > 0 ) {
			$problems[] = sprintf(
				/* translators: %d: number of links whose text says nothing about the destination */
				_n(
					'%d link says only "click here" or similar',
					'%d links say only "click here" or similar',
					$vague_links,
					'my-site-hand'
				),
				$vague_links
			);
		}

		$headings = $this->heading_levels( $dom );
		$h1_count = count(
			array_filter(
				$headings,
				static fn( int $level ): bool => 1 === $level
			)
		);

		if ( $h1_count > 1 ) {
			$problems[] = sprintf(
				/* translators: %d: number of top-level headings in the content */
				__( '%d main headings — a page should have one', 'my-site-hand' ),
				$h1_count
			);
		}

		$skipped = $this->count_skipped_levels( $headings );

		if ( $skipped > 0 ) {
			$problems[] = sprintf(
				/* translators: %d: number of places where a heading level was skipped */
				_n(
					'%d heading skips a level',
					'%d headings skip a level',
					$skipped,
					'my-site-hand'
				),
				$skipped
			);
		}

		return $problems;
	}

	/**
	 * Readable text of a link, counting an image's alt text as text.
	 *
	 * @param DOMElement $anchor Anchor element.
	 * @return string
	 */
	private function link_text( DOMElement $anchor ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property name.
		$text = trim( (string) $anchor->textContent );

		if ( '' !== $text ) {
			return $text;
		}

		foreach ( $anchor->getElementsByTagName( 'img' ) as $img ) {
			if ( $img instanceof DOMElement && '' !== trim( $img->getAttribute( 'alt' ) ) ) {
				return trim( $img->getAttribute( 'alt' ) );
			}
		}

		return '';
	}

	/**
	 * Heading levels in document order.
	 *
	 * @param DOMDocument $dom Parsed content.
	 * @return array<int, int>
	 */
	private function heading_levels( DOMDocument $dom ): array {
		$levels = [];

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );

		if ( false === $nodes ) {
			return [];
		}

		foreach ( $nodes as $node ) {
			if ( $node instanceof DOMElement ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property name.
				$levels[] = (int) substr( $node->nodeName, 1 );
			}
		}

		return $levels;
	}

	/**
	 * Count places where the heading level jumps by more than one.
	 *
	 * Going back UP is always fine — h4 followed by h2 closes a section. Only
	 * going down two or more levels at once leaves a gap.
	 *
	 * @param array<int, int> $levels Heading levels in document order.
	 * @return int
	 */
	private function count_skipped_levels( array $levels ): int {
		$skipped  = 0;
		$previous = null;

		foreach ( $levels as $level ) {
			if ( null !== $previous && $level > $previous + 1 ) {
				++$skipped;
			}

			$previous = $level;
		}

		return $skipped;
	}

	/**
	 * Parse post content into a document, swallowing malformed-HTML warnings.
	 *
	 * @param string $content Raw post content.
	 * @return DOMDocument|null Null when there is nothing parseable.
	 */
	private function load_dom( string $content ): ?DOMDocument {
		if ( ! class_exists( 'DOMDocument' ) || '' === trim( $content ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );

		$dom    = new DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $content );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $loaded ? $dom : null;
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
