<?php
/**
 * Site Health check: WordPress files that should not be publicly readable.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use RuntimeException;

/**
 * Check: Exposed WordPress Files.
 *
 * Asks the site, over HTTP, whether a handful of well-known files answer to an
 * anonymous visitor. Each one hands an attacker something for free: the exact
 * core version, a confirmation that this is WordPress at all, or — worst of the
 * set — a debug log full of absolute paths, queries, and occasionally
 * credentials.
 *
 * NO FALSE ASSURANCE. Plenty of hosts block a site from making HTTP requests to
 * itself. When that happens the check reports itself ERRORED rather than
 * finding nothing, because "we could not look" and "we looked and it is fine"
 * are different answers and only one of them is true.
 *
 * The answer changes about as often as a server config does, so the result is
 * cached for twelve hours and repeat scans cost nothing.
 */
class Check_Exposed_Files extends Health_Check_Base {

	/**
	 * How long a completed probe stays good for.
	 *
	 * @var int
	 */
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Per-request timeout.
	 *
	 * @var int
	 */
	private const TIMEOUT = 5;

	/**
	 * Where to read about closing these files off.
	 *
	 * @var string
	 */
	private const DOCS_URL = 'https://developer.wordpress.org/advanced-administration/security/hardening/';

	/**
	 * Cache manager instance.
	 *
	 * @var Cache_Manager
	 */
	private Cache_Manager $cache;

	/**
	 * Constructor.
	 *
	 * @param Cache_Manager $cache Cache manager, used for the twelve hour result cache.
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
		return 'exposed-files';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Exposed WordPress Files', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Asks your own site whether a few well-known files can be read by anyone. readme.html gives away your exact WordPress version, and a debug log can contain server paths and credentials. Closing them off is a rule in your .htaccess or nginx config — this plugin will not edit server configuration for you, so the fix is a short manual step.', 'my-site-hand' );
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
	 * Single pass — a handful of requests, all made together.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 1;
	}

	/**
	 * Probe every file in one pass.
	 *
	 * @param int $offset Unused; this check always completes in one call.
	 * @throws RuntimeException When the site cannot make HTTP requests to itself.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		$targets = $this->get_targets();
		$cached  = $this->cache->get( $this->cache_key() );

		if ( is_array( $cached ) ) {
			return $this->make_result( true, null, count( $targets ), $this->build_issues( $cached ) );
		}

		$exposed = [];

		foreach ( $targets as $path => $severity ) {
			$status = $this->probe( home_url( $path ) );

			if ( null === $status ) {
				// A loopback failure means we learned nothing. Saying "no
				// problems found" here would be a security claim we cannot
				// back up, so the check reports itself as unable to run.
				throw new RuntimeException(
					esc_html__( 'This site could not make a request to itself, so its files could not be checked. Some hosts block loopback requests.', 'my-site-hand' )
				);
			}

			if ( 200 === $status ) {
				$exposed[] = $path;
			}
		}

		$this->cache->set( $this->cache_key(), $exposed, self::CACHE_TTL );

		return $this->make_result( true, null, count( $targets ), $this->build_issues( $exposed ) );
	}

	/**
	 * Files to probe, mapped to the severity of finding them readable.
	 *
	 * @return array<string, string>
	 */
	private function get_targets(): array {
		return [
			'/readme.html'           => self::SEVERITY_WARNING,
			'/license.txt'           => self::SEVERITY_WARNING,
			'/wp-config-sample.php'  => self::SEVERITY_WARNING,
			'/wp-content/debug.log'  => self::SEVERITY_CRITICAL,
		];
	}

	/**
	 * Human-readable explanation of what each file gives away.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	private function describe( string $path ): string {
		switch ( $path ) {
			case '/readme.html':
				return __( 'Publicly readable — it names your exact WordPress version', 'my-site-hand' );
			case '/license.txt':
				return __( 'Publicly readable — it confirms the site runs WordPress', 'my-site-hand' );
			case '/wp-config-sample.php':
				return __( 'Publicly readable — it confirms a standard, unhardened install', 'my-site-hand' );
			case '/wp-content/debug.log':
				return __( 'Publicly readable — debug logs can contain server paths and credentials', 'my-site-hand' );
			default:
				return __( 'Publicly readable', 'my-site-hand' );
		}
	}

	/**
	 * Turn a list of exposed paths into issues.
	 *
	 * @param array<int, string> $exposed Paths that answered 200.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_issues( array $exposed ): array {
		$targets = $this->get_targets();
		$issues  = [];

		foreach ( $exposed as $path ) {
			$path = (string) $path;

			if ( ! isset( $targets[ $path ] ) ) {
				continue;
			}

			$issues[] = $this->make_issue(
				[
					'title'    => $path,
					'context'  => $this->describe( $path ),
					'severity' => $targets[ $path ],
					'link'     => self::DOCS_URL,
					// A rule in .htaccess or an nginx server block. The plugin
					// does not edit server configuration files, so the only
					// honest offer here is the documentation.
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => self::DOCS_URL ],
					'fixable'  => false,
					'ability'  => null,
				]
			);
		}

		return $issues;
	}

	/**
	 * Ask the site whether one URL answers to an anonymous visitor.
	 *
	 * @param string $url Absolute URL on this site.
	 * @return int|null HTTP status, or null when the request itself failed.
	 */
	private function probe( string $url ): ?int {
		$response = wp_remote_head(
			$url,
			[
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'sslverify'   => false,
				'user-agent'  => $this->user_agent(),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		return (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * User agent identifying the plugin and the site making the request.
	 *
	 * @return string
	 */
	private function user_agent(): string {
		return 'MySiteHand/' . MYSITEHAND_VERSION . '; ' . home_url( '/' );
	}

	/**
	 * Cache key for the probe result.
	 *
	 * Keyed by home URL so a site that moves does not read a stale answer.
	 *
	 * @return string
	 */
	private function cache_key(): string {
		return 'health_exposed_files_' . md5( home_url( '/' ) );
	}
}
