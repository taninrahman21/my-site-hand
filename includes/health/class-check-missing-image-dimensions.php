<?php
/**
 * Site Health check: images with no width and height attributes.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use DOMDocument;
use DOMElement;

/**
 * Check: Images Missing Dimensions.
 *
 * An <img> with neither a width nor a height attribute has no reserved space in
 * the layout, so the page reflows the moment it loads. That is cumulative
 * layout shift, one of the Core Web Vitals Google measures.
 *
 * ONE ISSUE PER POST, never one per image. A post with twenty undimensioned
 * images is a single editing job, and twenty rows carrying the same post title
 * would drown out every other finding in the report.
 */
class Check_Missing_Image_Dimensions extends Health_Check_Base {

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'missing-image-dimensions';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Images Missing Dimensions', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Finds images with no width or height, which makes the page jump around while it loads. Open the post, select each image and re-insert it from the media library — WordPress writes the dimensions back in for you. There is no safe automatic repair for this: rewriting the markup of a stored post is not something a scanner should do on your behalf.', 'my-site-hand' );
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
		return 50;
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
					AND p.post_content LIKE %s
				ORDER BY p.ID ASC
				LIMIT %d OFFSET %d",
				'%' . $wpdb->esc_like( '<img' ) . '%',
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
			$post_id = (int) $row['ID'];
			$count   = $this->count_undimensioned( (string) $row['post_content'] );

			if ( 0 === $count ) {
				continue;
			}

			$issues[] = $this->make_issue(
				[
					'title'     => sprintf(
						/* translators: 1: post title, 2: number of images with no width or height */
						_n(
							'%1$s (%2$d image with no dimensions)',
							'%1$s (%2$d images with no dimensions)',
							$count,
							'my-site-hand'
						),
						$this->post_title( $post_id, (string) $row['post_title'] ),
						$count
					),
					'context'   => __( 'Causes layout shift while the page loads', 'my-site-hand' ),
					'link'      => $this->edit_link( $post_id ),
					'object_id' => $post_id,
					// Rewriting arbitrary markup inside someone's post is not a
					// repair worth automating. The editor does it safely; a
					// pattern replacement over stored content does not.
					'fix_type'  => null,
					'fix_meta'  => [
						'post_id'     => $post_id,
						'image_count' => $count,
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
	 * Count images in one post that declare neither a width nor a height.
	 *
	 * DOMDocument, never a regular expression: post content is full of
	 * malformed markup and a pattern that survives all of it does not exist.
	 *
	 * @param string $content Raw post content.
	 * @return int
	 */
	private function count_undimensioned( string $content ): int {
		$dom = $this->load_dom( $content );

		if ( null === $dom ) {
			return 0;
		}

		$count = 0;

		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			if ( ! $img instanceof DOMElement ) {
				continue;
			}

			if ( '' !== trim( $img->getAttribute( 'width' ) ) || '' !== trim( $img->getAttribute( 'height' ) ) ) {
				continue;
			}

			// An inline aspect-ratio reserves the same space a width and height
			// pair would, so the layout does not shift.
			if ( $this->has_aspect_ratio( $img ) ) {
				continue;
			}

			// Inside a <picture>, the chosen <source> supplies the geometry.
			if ( $this->picture_source_has_dimensions( $img ) ) {
				continue;
			}

			++$count;
		}

		return $count;
	}

	/**
	 * Whether an element carries an inline aspect-ratio hint.
	 *
	 * @param DOMElement $element Element to inspect.
	 * @return bool
	 */
	private function has_aspect_ratio( DOMElement $element ): bool {
		$style = strtolower( $element->getAttribute( 'style' ) );

		return '' !== $style && str_contains( $style, 'aspect-ratio' );
	}

	/**
	 * Whether the image sits in a <picture> whose sources carry dimensions.
	 *
	 * @param DOMElement $img Image element.
	 * @return bool
	 */
	private function picture_source_has_dimensions( DOMElement $img ): bool {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property names.
		$parent = $img->parentNode;

		while ( $parent instanceof DOMElement ) {
			if ( 'picture' === strtolower( $parent->nodeName ) ) {
				foreach ( $parent->getElementsByTagName( 'source' ) as $source ) {
					if ( ! $source instanceof DOMElement ) {
						continue;
					}

					if ( '' !== trim( $source->getAttribute( 'width' ) ) || '' !== trim( $source->getAttribute( 'height' ) ) ) {
						return true;
					}
				}

				return false;
			}

			$parent = $parent->parentNode;
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return false;
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
