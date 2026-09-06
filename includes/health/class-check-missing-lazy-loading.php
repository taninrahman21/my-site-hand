<?php
/**
 * Site Health check: images opted out of lazy loading.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use DOMDocument;
use DOMElement;

/**
 * Check: Images Missing Lazy Loading.
 *
 * WordPress has added loading="lazy" to content images by itself since 5.5, so
 * on a healthy site this check finds almost nothing. That is the expected
 * result, not a broken check — what it is actually looking for is the small
 * number of images something has deliberately opted OUT of lazy loading.
 *
 * The first image in a post is never reported. Above-the-fold imagery SHOULD
 * load eagerly, and telling someone to lazy-load their hero image would be
 * advice that makes their site measurably slower.
 */
class Check_Missing_Lazy_Loading extends Health_Check_Base {

	/**
	 * Class names commonly used to opt an image out of lazy loading.
	 *
	 * @var array<int, string>
	 */
	private const OPT_OUT_CLASSES = [
		'skip-lazy',
		'no-lazy',
		'nolazy',
		'disable-lazyload',
	];

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'missing-lazy-loading';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Images Missing Lazy Loading', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'WordPress lazy-loads content images by itself, so this check usually finds nothing — that is a good result, not a broken check. It reports the images further down a post that something has deliberately opted out, which makes visitors download them before they are ever seen. The first image in a post is never reported, because that one should load straight away.', 'my-site-hand' );
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
		return 50;
	}

	/**
	 * Only applicable while WordPress is lazy loading at all.
	 *
	 * If the site has switched the feature off through the
	 * 'wp_lazy_loading_enabled' filter, every image on the site is eager by a
	 * deliberate decision someone already made. Listing them back would be
	 * hundreds of rows telling the user something they know.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return false;
		}

		if ( ! function_exists( 'wp_lazy_loading_enabled' ) ) {
			return false;
		}

		return (bool) wp_lazy_loading_enabled( 'img', 'the_content' );
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
			$count   = $this->count_eager( (string) $row['post_content'] );

			if ( 0 === $count ) {
				continue;
			}

			$issues[] = $this->make_issue(
				[
					'title'     => sprintf(
						/* translators: 1: post title, 2: number of images that will not lazy load */
						_n(
							'%1$s (%2$d image loads eagerly)',
							'%1$s (%2$d images load eagerly)',
							$count,
							'my-site-hand'
						),
						$this->post_title( $post_id, (string) $row['post_title'] ),
						$count
					),
					'context'   => __( 'Downloaded before the visitor scrolls to it', 'my-site-hand' ),
					'link'      => $this->edit_link( $post_id ),
					'object_id' => $post_id,
					// Removing an opt-out somebody added on purpose is not a
					// decision to make automatically.
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
	 * Count images past the first that will not lazy load.
	 *
	 * @param string $content Raw post content.
	 * @return int
	 */
	private function count_eager( string $content ): int {
		$dom = $this->load_dom( $content );

		if ( null === $dom ) {
			return 0;
		}

		$count    = 0;
		$position = 0;

		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			if ( ! $img instanceof DOMElement ) {
				continue;
			}

			++$position;

			// The first image is very often the one above the fold. Eager is
			// the right answer there, so it is never reported.
			if ( 1 === $position ) {
				continue;
			}

			if ( $this->is_eager( $img ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Whether one image is opted out of lazy loading.
	 *
	 * An image with no loading attribute at all is NOT reported: WordPress
	 * adds one when the content is rendered.
	 *
	 * @param DOMElement $img Image element.
	 * @return bool
	 */
	private function is_eager( DOMElement $img ): bool {
		$loading = strtolower( trim( $img->getAttribute( 'loading' ) ) );

		if ( 'eager' === $loading ) {
			return true;
		}

		$classes = strtolower( $img->getAttribute( 'class' ) );

		if ( '' === $classes ) {
			return false;
		}

		$classes = preg_split( '/\s+/', $classes );

		if ( ! is_array( $classes ) ) {
			return false;
		}

		foreach ( self::OPT_OUT_CLASSES as $opt_out ) {
			if ( in_array( $opt_out, $classes, true ) ) {
				return true;
			}
		}

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
