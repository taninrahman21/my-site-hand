<?php
/**
 * Site Health check: posts and pages without an SEO meta description.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Check: Missing Meta Description.
 *
 * Supports Yoast SEO and RankMath, querying only the meta key belonging to
 * whichever is active. When neither is installed the check reports itself
 * inapplicable: a site without an SEO plugin has no meta descriptions by
 * design, so reporting zero problems would be misleading and reporting every
 * post as a problem would be worse.
 */
class Check_Missing_Meta_Description extends Health_Check_Base {

	/**
	 * Yoast SEO meta description key.
	 *
	 * @var string
	 */
	private const YOAST_KEY = '_yoast_wpseo_metadesc';

	/**
	 * RankMath meta description key.
	 *
	 * @var string
	 */
	private const RANKMATH_KEY = 'rank_math_description';

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'missing-meta-description';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Missing Meta Description', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Finds published posts and pages with no SEO meta description, so search engines invent their own snippet.', 'my-site-hand' );
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
	 * Only applicable when an SEO plugin is actually managing descriptions.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return 'none' !== $this->detect_seo_plugin();
	}

	/**
	 * Examine one batch of published posts and pages.
	 *
	 * @param int $offset Offset to resume from.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		global $wpdb;

		$meta_key = $this->get_meta_key();

		if ( null === $meta_key ) {
			return $this->make_result( true, null, 0, [] );
		}

		$batch_size = $this->get_batch_size();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_type
				FROM {$wpdb->posts} AS p
				LEFT JOIN {$wpdb->postmeta} AS m
					ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type IN ( 'post', 'page' )
					AND p.post_status = 'publish'
					AND ( m.meta_value IS NULL OR TRIM( m.meta_value ) = '' )
				ORDER BY p.ID ASC
				LIMIT %d OFFSET %d",
				$meta_key,
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
			$title   = (string) $row['post_title'];

			$issues[] = $this->make_issue(
				[
					'title'     => '' !== trim( $title ) ? $title : sprintf(
						/* translators: %d: post ID */
						__( 'Untitled (#%d)', 'my-site-hand' ),
						$post_id
					),
					'context'   => $this->post_type_label( (string) $row['post_type'] ),
					'link'      => $this->edit_link( $post_id ),
					'object_id' => $post_id,
					'fix_type'  => 'inline_text',
					'fix_meta'  => [
						'post_id'  => $post_id,
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Payload for the repair ability, not a query argument.
						'meta_key' => $meta_key,
					],
					'fixable'   => true,
					'ability'   => 'my-site-hand/set-meta-description',
				]
			);
		}

		$scanned = count( $rows );
		$done    = $scanned < $batch_size;

		return $this->make_result( $done, $offset + $scanned, $scanned, $issues );
	}

	/**
	 * Meta key used by the active SEO plugin.
	 *
	 * @return string|null Null when no supported SEO plugin is active.
	 */
	public function get_meta_key(): ?string {
		switch ( $this->detect_seo_plugin() ) {
			case 'yoast':
				return self::YOAST_KEY;
			case 'rankmath':
				return self::RANKMATH_KEY;
			default:
				return null;
		}
	}

	/**
	 * Detect which SEO plugin is active.
	 *
	 * Mirrors Module_Seo::detect_seo_plugin() so both agree on what is active.
	 *
	 * @return string 'yoast' | 'rankmath' | 'none'
	 */
	private function detect_seo_plugin(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}

		return 'none';
	}

	/**
	 * Human-readable singular label for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return string|null
	 */
	private function post_type_label( string $post_type ): ?string {
		$object = get_post_type_object( $post_type );

		if ( null === $object ) {
			return null;
		}

		return (string) $object->labels->singular_name;
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
