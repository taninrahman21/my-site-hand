<?php
/**
 * Site Health check: links that no longer resolve.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use DOMDocument;

/**
 * Check: Broken Links.
 *
 * The only check that makes outbound HTTP requests, and therefore the only one
 * that can hang. Everything here is bounded: the 50 most recent published posts
 * and pages, a capped list of unique URLs, ten URLs per batch, a five second
 * per-request timeout, a per-batch wall-clock guard, and a twelve hour cache
 * per URL so repeat scans barely touch the network.
 *
 * ACCURACY OVER COMPLETENESS. Plenty of perfectly healthy sites answer 403 or
 * 405 to a non-browser client. Reporting one of those as broken would destroy
 * trust in every other check, so only 404 and 410 count as broken, anything
 * ambiguous is retried once, and anything still ambiguous is not reported.
 */
class Check_Broken_Links extends Health_Check_Base {

	/**
	 * Posts and pages scanned for links.
	 *
	 * @var int
	 */
	private const POST_LIMIT = 50;

	/**
	 * Default cap on unique URLs probed per scan.
	 *
	 * Ten URLs per batch means this is also the request count: keeping it near
	 * one hundred is what keeps a first scan close to the promised half minute.
	 *
	 * @var int
	 */
	private const DEFAULT_URL_LIMIT = 100;

	/**
	 * Seconds a single batch may spend on the network before yielding.
	 *
	 * Ten URLs each timing out at five seconds would be fifty seconds, and
	 * shared hosting kills PHP at thirty.
	 *
	 * @var int
	 */
	private const BATCH_TIME_BUDGET = 12;

	/**
	 * Per-URL result cache lifetime.
	 *
	 * @var int
	 */
	private const URL_CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Status codes that mean a link is definitively gone.
	 *
	 * @var array<int, int>
	 */
	private const BROKEN_CODES = [ 404, 410 ];

	/**
	 * Status codes that mean "ask again, differently" rather than "broken".
	 *
	 * @var array<int, int>
	 */
	private const AMBIGUOUS_CODES = [ 403, 405, 429 ];

	/**
	 * Cache manager instance.
	 *
	 * @var Cache_Manager
	 */
	private Cache_Manager $cache;

	/**
	 * Constructor.
	 *
	 * @param Cache_Manager $cache Cache manager, used for the URL list and per-URL results.
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
		return 'broken-links';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Broken Links', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Checks the links in your 50 most recent posts and pages and reports the ones that are gone.', 'my-site-hand' );
	}

	/**
	 * Default severity for issues from this check.
	 *
	 * @return string
	 */
	public function get_severity(): string {
		return self::SEVERITY_CRITICAL;
	}

	/**
	 * URLs probed per batch.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 10;
	}

	/**
	 * Link extraction needs DOMDocument.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return class_exists( 'DOMDocument' );
	}

	/**
	 * Probe one batch of URLs.
	 *
	 * @param int $offset Offset into the URL list.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		$urls = 0 === $offset ? $this->build_url_list() : $this->get_url_list();

		$total = count( $urls );

		if ( 0 === $total || $offset >= $total ) {
			return $this->make_result( true, null, 0, [] );
		}

		$batch    = array_slice( $urls, $offset, $this->get_batch_size() );
		$deadline = microtime( true ) + self::BATCH_TIME_BUDGET;
		$issues   = [];
		$scanned  = 0;

		foreach ( $batch as $entry ) {
			$result = $this->check_url( $entry['url'] );
			++$scanned;

			if ( $result['broken'] ) {
				$issues[] = $this->make_issue(
					[
						'title'     => sprintf(
							/* translators: 1: URL, 2: HTTP status code */
							__( '%1$s (HTTP %2$d)', 'my-site-hand' ),
							$entry['url'],
							$result['code']
						),
						'context'   => '' !== $entry['post_title'] ? $entry['post_title'] : null,
						'link'      => $this->edit_link( (int) $entry['post_id'] ),
						'object_id' => (int) $entry['post_id'],
						'fix_type'  => 'inline_url',
						'fix_meta'  => [
							'post_id' => (int) $entry['post_id'],
							'old_url' => $entry['url'],
						],
						'fixable'   => true,
						'ability'   => 'my-site-hand/update-post',
					]
				);
			}

			// Yield rather than risk the request being killed mid-flight. At
			// least one URL always completes, so progress is guaranteed.
			if ( microtime( true ) >= $deadline && $scanned < count( $batch ) ) {
				break;
			}
		}

		$next = $offset + $scanned;
		$done = $next >= $total;

		return $this->make_result( $done, $next, $scanned, $issues );
	}

	/**
	 * Total unique URLs queued for the current run.
	 *
	 * @return int
	 */
	public function get_url_count(): int {
		return count( $this->get_url_list() );
	}

	/**
	 * Build the URL list for a run and cache it.
	 *
	 * Rebuilt only when a run starts at offset zero: re-parsing fifty posts on
	 * every batch would cost more than the HTTP requests do.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_url_list(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content
				FROM {$wpdb->posts}
				WHERE post_status = 'publish'
					AND post_type IN ( 'post', 'page' )
				ORDER BY post_date DESC
				LIMIT %d",
				self::POST_LIMIT
			),
			ARRAY_A
		);

		$internal = [];
		$external = [];
		$seen     = [];
		$home     = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( (array) $posts as $post ) {
			if ( empty( $post['post_content'] ) ) {
				continue;
			}

			foreach ( $this->extract_hrefs( (string) $post['post_content'] ) as $href ) {
				$url = $this->normalize_url( $href );

				if ( null === $url || isset( $seen[ $url ] ) ) {
					continue;
				}

				$seen[ $url ] = true;

				$entry = [
					'url'        => $url,
					'post_id'    => (int) $post['ID'],
					'post_title' => (string) $post['post_title'],
				];

				if ( wp_parse_url( $url, PHP_URL_HOST ) === $home ) {
					$internal[] = $entry;
				} else {
					$external[] = $entry;
				}
			}
		}

		// Internal links first: they are fast, and they are the ones the user
		// can actually fix.
		$urls = array_merge( $internal, $external );

		/**
		 * Filter the maximum number of unique URLs probed in one scan.
		 *
		 * @param int $limit URL cap.
		 */
		$limit = (int) apply_filters( 'my_site_hand_health_broken_links_max_urls', self::DEFAULT_URL_LIMIT );
		$urls  = array_slice( $urls, 0, max( 1, $limit ) );

		$this->cache->set( $this->url_list_key(), $urls, Site_Health_Scanner::RUN_STATE_TTL );

		return $urls;
	}

	/**
	 * Get the cached URL list, rebuilding it if the cache has expired.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_url_list(): array {
		$urls = $this->cache->get( $this->url_list_key() );

		if ( is_array( $urls ) ) {
			return $urls;
		}

		return $this->build_url_list();
	}

	/**
	 * Cache key for the current user's URL list.
	 *
	 * @return string
	 */
	private function url_list_key(): string {
		return 'health_links_' . get_current_user_id();
	}

	/**
	 * Extract every href from a block of HTML.
	 *
	 * DOMDocument, not a regular expression: post content is full of edge cases
	 * a pattern gets wrong.
	 *
	 * @param string $content Post content.
	 * @return array<int, string>
	 */
	private function extract_hrefs( string $content ): array {
		if ( ! class_exists( 'DOMDocument' ) || '' === trim( $content ) ) {
			return [];
		}

		$previous = libxml_use_internal_errors( true );

		$dom    = new DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $content );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return [];
		}

		$hrefs = [];

		foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
			$href = $anchor->getAttribute( 'href' );

			if ( '' !== $href ) {
				$hrefs[] = $href;
			}
		}

		return $hrefs;
	}

	/**
	 * Turn an href into an absolute http(s) URL, or null if it is not probeable.
	 *
	 * @param string $href Raw href attribute.
	 * @return string|null
	 */
	private function normalize_url( string $href ): ?string {
		$href = trim( $href );

		if ( '' === $href || str_starts_with( $href, '#' ) ) {
			return null;
		}

		// Anything that is not a web request: mail clients, phones, scripts,
		// inline data.
		foreach ( [ 'mailto:', 'tel:', 'sms:', 'javascript:', 'data:', 'file:' ] as $scheme ) {
			if ( stripos( $href, $scheme ) === 0 ) {
				return null;
			}
		}

		// Protocol-relative.
		if ( str_starts_with( $href, '//' ) ) {
			$href = ( is_ssl() ? 'https:' : 'http:' ) . $href;
		}

		// Root-relative.
		if ( str_starts_with( $href, '/' ) ) {
			$href = untrailingslashit( home_url() ) . $href;
		}

		$parts = wp_parse_url( $href );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return null;
		}

		// Drop the fragment: it never reaches the server.
		$without_fragment = strtok( $href, '#' );

		return is_string( $without_fragment ) && '' !== $without_fragment ? $without_fragment : null;
	}

	/**
	 * Get a URL's status, from cache when possible.
	 *
	 * @param string $url Absolute URL.
	 * @return array{code: int, broken: bool}
	 */
	private function check_url( string $url ): array {
		$key    = 'health_link_' . md5( $url );
		$cached = $this->cache->get( $key );

		if ( is_array( $cached ) && isset( $cached['broken'], $cached['code'] ) ) {
			return [
				'code'   => (int) $cached['code'],
				'broken' => (bool) $cached['broken'],
			];
		}

		$result = $this->probe( $url );

		$this->cache->set( $key, $result, self::URL_CACHE_TTL );

		return $result;
	}

	/**
	 * Probe a URL over the network.
	 *
	 * @param string $url Absolute URL.
	 * @return array{code: int, broken: bool}
	 */
	private function probe( string $url ): array {
		$args = [
			'timeout'     => 5,
			'redirection' => 3,
			'sslverify'   => true,
			'user-agent'  => $this->user_agent(),
		];

		$response = wp_remote_head( $url, $args );

		// A transport error is not evidence the link is broken.
		if ( is_wp_error( $response ) ) {
			return [
				'code'   => 0,
				'broken' => false,
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $code, self::BROKEN_CODES, true ) ) {
			return [
				'code'   => $code,
				'broken' => true,
			];
		}

		$needs_retry = in_array( $code, self::AMBIGUOUS_CODES, true ) || $code >= 500 || 0 === $code;

		if ( ! $needs_retry ) {
			return [
				'code'   => $code,
				'broken' => false,
			];
		}

		// Many hosts refuse HEAD but answer GET. Ask for the first bytes only.
		$args['headers'] = [ 'Range' => 'bytes=0-1023' ];
		$retry           = wp_remote_get( $url, $args );

		if ( is_wp_error( $retry ) ) {
			return [
				'code'   => $code,
				'broken' => false,
			];
		}

		$retry_code = (int) wp_remote_retrieve_response_code( $retry );

		return [
			'code'   => $retry_code,
			'broken' => in_array( $retry_code, self::BROKEN_CODES, true ),
		];
	}

	/**
	 * Descriptive user agent identifying the plugin and the site.
	 *
	 * @return string
	 */
	private function user_agent(): string {
		return sprintf(
			'MySiteHand/%s (+%s) WordPress link checker',
			defined( 'MYSITEHAND_VERSION' ) ? MYSITEHAND_VERSION : '1.0.0',
			home_url( '/' )
		);
	}

	/**
	 * Admin URL for the linking post's edit screen.
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
