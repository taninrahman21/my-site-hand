<?php
/**
 * SEO module — analysis and optimization abilities.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Module SEO class.
 *
 * Provides SEO analysis, meta management, and site-wide audit abilities.
 * Detects Yoast SEO and RankMath and uses their data when available.
 */
class Module_Seo extends Module_Base {

	/**
	 * {@inheritdoc}
	 */
	public function get_module_name(): string {
		return 'seo';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ability_names(): array {
		return [
			'my-site-hand/analyze-seo',
			'my-site-hand/set-meta-description',
			'my-site-hand/set-focus-keyword',
			'my-site-hand/bulk-seo-audit',
			'my-site-hand/get-sitemap-urls',
			'my-site-hand/check-broken-links',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_abilities(): void {
		$this->register_analyze_seo();
		$this->register_set_meta_description();
		$this->register_set_focus_keyword();
		$this->register_bulk_seo_audit();
		$this->register_get_sitemap_urls();
		$this->register_check_broken_links();
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_analyze-seo
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_analyze-seo ability.
	 *
	 * @return void
	 */
	private function register_analyze_seo(): void {
		$this->register(
			'my-site-hand/analyze-seo',
			[
				'label'            => __( 'Analyze SEO', 'my-site-hand' ),
				'description'      => __( 'Analyze the SEO health of a post — keyword density, heading structure, link ratios, meta description, and more.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'keyword' => [ 'type' => 'string', 'description' => 'Focus keyword to analyze' ],
					],
				],
				'execute_callback' => [ $this, 'execute_analyze_seo' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_analyze-seo.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_analyze_seo( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] );
		$keyword = sanitize_text_field( $input['keyword'] ?? '' );

		$cache_key = "seo_analysis_{$post_id}_" . md5( $keyword );
		$cached    = get_transient( 'MYSITEHAND_' . $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		$content       = $post->post_content;
		$plain_content = wp_strip_all_tags( $content );
		$word_count    = str_word_count( $plain_content );
		$reading_time  = max( 1, (int) round( $word_count / 200 ) );

		// Detect SEO plugin.
		$seo_plugin = $this->detect_seo_plugin();

		// Get meta description.
		$meta_description = $this->get_meta_description( $post_id, $seo_plugin );
		$meta_desc_length = strlen( $meta_description );

		// Get title.
		$title        = $post->post_title;
		$title_length = strlen( $title );

		// Keyword analysis.
		$keyword_density         = 0.0;
		$keyword_in_title        = false;
		$keyword_in_meta         = false;
		$keyword_in_first_para   = false;

		if ( ! empty( $keyword ) ) {
			$keyword_lower           = strtolower( $keyword );
			$content_lower           = strtolower( $plain_content );
			$keyword_count           = substr_count( $content_lower, $keyword_lower );
			$keyword_density         = $word_count > 0 ? round( ( $keyword_count / $word_count ) * 100, 2 ) : 0.0;
			$keyword_in_title        = str_contains( strtolower( $title ), $keyword_lower );
			$keyword_in_meta         = str_contains( strtolower( $meta_description ), $keyword_lower );

			// First paragraph keyword check.
			$paragraphs = explode( "\n\n", $plain_content );
			$first_para = strtolower( $paragraphs[0] ?? '' );
			$keyword_in_first_para = str_contains( $first_para, $keyword_lower );
		}

		// Heading structure analysis.
		$headings = [];
		foreach ( [ 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ] as $tag ) {
			$count = preg_match_all( "/<{$tag}[^>]*>/i", $content );
			if ( $count > 0 ) {
				$headings[ $tag ] = $count;
			}
		}

		// Link analysis.
		preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $link_matches );
		$site_url      = get_site_url();
		$internal_links = 0;
		$external_links = 0;
		foreach ( $link_matches[1] ?? [] as $href ) {
			if ( str_starts_with( $href, $site_url ) || str_starts_with( $href, '/' ) ) {
				++$internal_links;
			} elseif ( str_starts_with( $href, 'http' ) ) {
				++$external_links;
			}
		}

		// Image analysis.
		preg_match_all( '/<img[^>]+>/i', $content, $img_matches );
		$images_total      = count( $img_matches[0] );
		$images_missing_alt = 0;
		foreach ( $img_matches[0] as $img_tag ) {
			if ( ! preg_match( '/alt=["\'][^"\']+["\']/', $img_tag ) ||
				preg_match( '/alt=["\']["\']/', $img_tag ) ) {
				++$images_missing_alt;
			}
		}

		// Flesch-Kincaid approximation (simplified).
		$sentences       = preg_split( '/[.!?]+/', $plain_content, -1, PREG_SPLIT_NO_EMPTY );
		$sentence_count  = max( 1, count( $sentences ) );
		$syllable_count  = $this->count_syllables( $plain_content );
		$fk_grade        = $word_count > 0
			? round( 0.39 * ( $word_count / $sentence_count ) + 11.8 * ( $syllable_count / max( 1, $word_count ) ) - 15.59, 1 )
			: 0.0;

		// Meta description scoring.
		$meta_desc_status = 'missing';
		if ( $meta_desc_length > 0 ) {
			$meta_desc_status = ( $meta_desc_length >= 120 && $meta_desc_length <= 160 ) ? 'good' : ( $meta_desc_length < 120 ? 'too_short' : 'too_long' );
		}

		// Title length scoring.
		$title_status = ( $title_length >= 30 && $title_length <= 60 ) ? 'good' : ( $title_length < 30 ? 'too_short' : 'too_long' );

		// Issues and suggestions.
		$issues      = [];
		$suggestions = [];

		if ( 'missing' === $meta_desc_status ) {
			/* translators: No meta description error */
			$issues[]      = __( 'No meta description set.', 'my-site-hand' );
			$suggestions[] = __( 'Add a 120-160 character meta description.', 'my-site-hand' );
		} elseif ( 'too_short' === $meta_desc_status ) {
			/* translators: Meta description too short error */
			$issues[]      = __( 'Meta description is too short.', 'my-site-hand' );
			$suggestions[] = __( 'Expand meta description to 120-160 characters.', 'my-site-hand' );
		} elseif ( 'too_long' === $meta_desc_status ) {
			/* translators: Meta description too long error */
			$issues[]      = __( 'Meta description is too long and may be truncated in SERPs.', 'my-site-hand' );
		}

		if ( ! empty( $keyword ) && ! $keyword_in_meta ) {
			/* translators: Focus keyword missing from meta description error */
			$issues[]      = __( 'Focus keyword missing from meta description.', 'my-site-hand' );
			/* translators: Suggestion to add focus keyword to meta description */
			$suggestions[] = __( 'Add focus keyword to meta description.', 'my-site-hand' );
		}

		if ( $images_missing_alt > 0 ) {
			/* translators: %d: number of images missing alt text */
			$issues[]      = sprintf( _n( '%d image is missing alt text.', '%d images are missing alt text.', $images_missing_alt, 'my-site-hand' ), $images_missing_alt );
			/* translators: Suggestion to add alt text to images */
			$suggestions[] = __( 'Add descriptive alt text to all images.', 'my-site-hand' );
		}

		if ( empty( $headings['H2'] ) && $word_count > 300 ) {
			/* translators: No H2 headings error */
			$issues[]      = __( 'No H2 headings found in a long article.', 'my-site-hand' );
			/* translators: Suggestion to add H2 headings */
			$suggestions[] = __( 'Add H2 subheadings to organize content.', 'my-site-hand' );
		}

		if ( $word_count < 300 ) {
			/* translators: Content too short error */
			$issues[]      = __( 'Content is too short (under 300 words).', 'my-site-hand' );
			/* translators: Suggestion to expand content */
			$suggestions[] = __( 'Expand content to at least 300 words.', 'my-site-hand' );
		}

		// Build Yoast score if available.
		$yoast_score = null;
		if ( 'yoast' === $seo_plugin ) {
			$yoast_score = get_post_meta( $post_id, '_yoast_wpseo_linkdex', true );
		}

		$analysis = [
			'word_count'                  => $word_count,
			'reading_time_minutes'        => $reading_time,
			'flesch_kincaid_grade'        => $fk_grade,
			'keyword_density'             => $keyword ? $keyword_density . '%' : null,
			'keyword_in_title'            => $keyword ? $keyword_in_title : null,
			'keyword_in_meta_description' => $keyword ? $keyword_in_meta : null,
			'keyword_in_first_paragraph'  => $keyword ? $keyword_in_first_para : null,
			'meta_description'            => $meta_description,
			'meta_description_length'     => $meta_desc_length,
			'meta_description_status'     => $meta_desc_status,
			'title_length'                => $title_length,
			'title_length_status'         => $title_status,
			'heading_structure'           => $headings,
			'internal_links'              => $internal_links,
			'external_links'              => $external_links,
			'images_total'                => $images_total,
			'images_missing_alt'          => $images_missing_alt,
			'url_slug'                    => $post->post_name,
			'canonical_url'               => get_permalink( $post_id ),
			'issues'                      => $issues,
			'suggestions'                 => $suggestions,
			'seo_plugin'                  => $seo_plugin,
			'yoast_score'                 => $yoast_score,
		];

		set_transient( 'MYSITEHAND_' . $cache_key, $analysis, HOUR_IN_SECONDS );

		return $analysis;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_set-meta-description
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_set-meta-description ability.
	 *
	 * @return void
	 */
	private function register_set_meta_description(): void {
		$this->register(
			'my-site-hand/set-meta-description',
			[
				'label'               => __( 'Set Meta Description', 'my-site-hand' ),
				'description'         => __( 'Set the SEO meta description for a post. Works with Yoast, RankMath, or raw meta.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'meta_description' ],
					'properties' => [
						'post_id'          => [ 'type' => 'integer' ],
						'meta_description' => [ 'type' => 'string', 'description' => 'Meta description (max 160 chars)' ],
					],
				],
				'permission_callback' => static function ( int $user_id, array $input ) {
					$post_id = absint( $input['post_id'] ?? 0 );
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_post', $post_id );
					}
					return current_user_can( 'edit_post', $post_id );
				},
				'execute_callback'    => [ $this, 'execute_set_meta_description' ],
				'annotations'         => [
					'meta' => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_set-meta-description.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_set_meta_description( array $input ): array|\WP_Error {
		$post_id     = absint( $input['post_id'] );
		$description = substr( sanitize_text_field( $input['meta_description'] ), 0, 160 );

		if ( ! get_post( $post_id ) ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		$seo_plugin = $this->detect_seo_plugin();
		$plugin_used = 'raw';

		if ( 'yoast' === $seo_plugin ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
			$plugin_used = 'yoast';
		} elseif ( 'rankmath' === $seo_plugin ) {
			update_post_meta( $post_id, 'rank_math_description', $description );
			$plugin_used = 'rankmath';
		} else {
			update_post_meta( $post_id, '_mysitehand_meta_description', $description );
		}

		// Clear SEO analysis cache.
		delete_transient( 'mysitehand_seo_analysis_' . $post_id . '_' );

		return [
			'updated'          => true,
			'plugin'           => $plugin_used,
			'meta_description' => $description,
			'length'           => strlen( $description ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_set-focus-keyword
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_set-focus-keyword ability.
	 *
	 * @return void
	 */
	private function register_set_focus_keyword(): void {
		$this->register(
			'my-site-hand/set-focus-keyword',
			[
				'label'               => __( 'Set Focus Keyword', 'my-site-hand' ),
				'description'         => __( 'Set the SEO focus keyword for a post. Works with Yoast or RankMath.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'keyword' ],
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'keyword' => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function ( int $user_id, array $input ) {
					$post_id = absint( $input['post_id'] ?? 0 );
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_post', $post_id );
					}
					return current_user_can( 'edit_post', $post_id );
				},
				'execute_callback'    => [ $this, 'execute_set_focus_keyword' ],
				'annotations'         => [
					'meta' => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_set-focus-keyword.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_set_focus_keyword( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] );
		$keyword = sanitize_text_field( $input['keyword'] );

		if ( ! get_post( $post_id ) ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		$seo_plugin = $this->detect_seo_plugin();
		$plugin_used = 'raw';

		if ( 'yoast' === $seo_plugin ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
			$plugin_used = 'yoast';
		} elseif ( 'rankmath' === $seo_plugin ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $keyword );
			$plugin_used = 'rankmath';
		} else {
			update_post_meta( $post_id, '_mysitehand_focus_keyword', $keyword );
		}

		return [
			'updated' => true,
			'plugin'  => $plugin_used,
			'keyword' => $keyword,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_bulk-seo-audit
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_bulk-seo-audit ability.
	 *
	 * @return void
	 */
	private function register_bulk_seo_audit(): void {
		$this->register(
			'my-site-hand/bulk-seo-audit',
			[
				'label'            => __( 'Bulk SEO Audit', 'my-site-hand' ),
				'description'      => __( 'Run SEO audit across multiple posts and return sorted results (worst first).', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string', 'default' => 'post' ],
						'limit'     => [ 'type' => 'integer', 'default' => 50, 'maximum' => 200 ],
						'status'    => [ 'type' => 'string', 'default' => 'publish' ],
					],
				],
				'execute_callback' => [ $this, 'execute_bulk_seo_audit' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_bulk-seo-audit.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_bulk_seo_audit( array $input ): array {
		$post_type = sanitize_key( $input['post_type'] ?? 'post' );
		$limit     = min( absint( $input['limit'] ?? 50 ), 200 );
		$status    = sanitize_text_field( $input['status'] ?? 'publish' );

		$posts = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] );

		$results = [];
		$summary = [ 'good' => 0, 'ok' => 0, 'poor' => 0 ];

		foreach ( $posts as $post_id ) {
			$analysis    = $this->execute_analyze_seo( [ 'post_id' => $post_id ] );
			$issue_count = is_array( $analysis ) ? count( $analysis['issues'] ?? [] ) : 99;

			$score = 'good';
			if ( $issue_count >= 3 ) {
				$score = 'poor';
			} elseif ( $issue_count >= 1 ) {
				$score = 'ok';
			}

			++$summary[ $score ];

			$results[] = [
				'post_id'     => $post_id,
				'title'       => get_the_title( $post_id ),
				'url'         => get_permalink( $post_id ),
				'score'       => $score,
				'issue_count' => $issue_count,
				'issues'      => is_array( $analysis ) ? ( $analysis['issues'] ?? [] ) : [ 'Analysis failed' ],
				'word_count'  => is_array( $analysis ) ? ( $analysis['word_count'] ?? 0 ) : 0,
			];
		}

		// Sort by issue count descending (worst first).
		usort( $results, static fn( $a, $b ) => $b['issue_count'] <=> $a['issue_count'] );

		return [
			'posts'   => $results,
			'summary' => $summary,
			'total'   => count( $results ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-sitemap-urls
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-sitemap-urls ability.
	 *
	 * @return void
	 */
	private function register_get_sitemap_urls(): void {
		$this->register(
			'my-site-hand/get-sitemap-urls',
			[
				'label'            => __( 'Get Sitemap URLs', 'my-site-hand' ),
				'description'      => __( 'Get sitemap URLs for this WordPress site (Yoast, RankMath, or WordPress core).', 'my-site-hand' ),
				'execute_callback' => [ $this, 'execute_get_sitemap_urls' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-sitemap-urls.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_get_sitemap_urls( array $input ): array {
		$seo_plugin = $this->detect_seo_plugin();
		$site_url   = get_site_url();
		$sitemaps   = [];

		if ( 'yoast' === $seo_plugin ) {
			$sitemaps[] = $site_url . '/sitemap_index.xml';
			$sitemaps[] = $site_url . '/post-sitemap.xml';
			$sitemaps[] = $site_url . '/page-sitemap.xml';
		} elseif ( 'rankmath' === $seo_plugin ) {
			$sitemaps[] = $site_url . '/sitemap_index.xml';
		} else {
			// WordPress core sitemaps (WP 5.5+).
			$sitemaps[] = $site_url . '/wp-sitemap.xml';
			$sitemaps[] = $site_url . '/wp-sitemap-posts-post-1.xml';
			$sitemaps[] = $site_url . '/wp-sitemap-posts-page-1.xml';
		}

		return [
			'sitemaps'   => $sitemaps,
			'seo_plugin' => $seo_plugin,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_check-broken-links
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_check-broken-links ability.
	 *
	 * @return void
	 */
	private function register_check_broken_links(): void {
		$this->register(
			'my-site-hand/check-broken-links',
			[
				'label'            => __( 'Check Broken Links', 'my-site-hand' ),
				'description'      => __( 'Check all outgoing links in a post for broken URLs (returns HTTP status for each).', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
					],
				],
				'execute_callback' => [ $this, 'execute_check_broken_links' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_check-broken-links.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_check_broken_links( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		preg_match_all( '/href=["\']([^"\'#]+)["\']/', $post->post_content, $matches );
		$urls = array_unique( $matches[1] ?? [] );

		$ok     = [];
		$broken = [];

		foreach ( $urls as $url ) {
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				continue;
			}

			$response = wp_remote_head( esc_url_raw( $url ), [
				'timeout'    => 10,
				'user-agent' => 'WP my-site-hand Link Checker/1.0',
				'sslverify'  => false,
			] );

			if ( is_wp_error( $response ) ) {
				$broken[] = [ 'url' => $url, 'status' => 0, 'error' => $response->get_error_message() ];
				continue;
			}

			$status = wp_remote_retrieve_response_code( $response );

			if ( $status >= 400 || 0 === $status ) {
				$broken[] = [ 'url' => $url, 'status' => $status ];
			} else {
				$ok[] = [ 'url' => $url, 'status' => $status ];
			}
		}

		return [
			'total'  => count( $urls ),
			'broken' => $broken,
			'ok'     => $ok,
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Detect which SEO plugin is active.
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
	 * Get the meta description for a post from the active SEO plugin.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $seo_plugin Active SEO plugin name.
	 * @return string Meta description string.
	 */
	private function get_meta_description( int $post_id, string $seo_plugin ): string {
		if ( 'yoast' === $seo_plugin ) {
			return (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		}
		if ( 'rankmath' === $seo_plugin ) {
			return (string) get_post_meta( $post_id, 'rank_math_description', true );
		}
		return (string) get_post_meta( $post_id, '_mysitehand_meta_description', true );
	}

	/**
	 * Count approximate syllables in text for Flesch-Kincaid calculation.
	 *
	 * @param string $text Input text.
	 * @return int Approximate syllable count.
	 */
	private function count_syllables( string $text ): int {
		$words    = str_word_count( strtolower( $text ), 1 );
		$syllables = 0;

		foreach ( $words as $word ) {
			$word_syllables = preg_match_all( '/[aeiou]+/i', $word );
			$syllables += max( 1, (int) $word_syllables );
		}

		return $syllables;
	}
}




