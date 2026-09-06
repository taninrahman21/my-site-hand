<?php
/**
 * Site Health check: xmlrpc.php reachable.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

use RuntimeException;

/**
 * Check: XML-RPC Enabled.
 *
 * A NOTICE, deliberately, and it will never be anything higher.
 *
 * XML-RPC is a real brute-force amplification vector: system.multicall lets an
 * attacker try hundreds of passwords in a single request. It is also how the
 * WordPress mobile apps, Jetpack, and several publishing tools talk to a site.
 * Telling someone to switch it off is good advice for one of those sites and
 * actively harmful for the other. A scanner that breaks a user's mobile app
 * because it sounded more secure has not helped anybody.
 *
 * So: notice severity, an honest description of the tradeoff, and no report at
 * all when Jetpack is active, because Jetpack cannot work without it.
 */
class Check_Xmlrpc extends Health_Check_Base {

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
	 * Where to read about turning XML-RPC off, and what breaks when you do.
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
		return 'xmlrpc-enabled';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'XML-RPC Enabled', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'XML-RPC is the old remote interface at xmlrpc.php. It lets an attacker try many passwords in a single request, which is why hardening guides suggest closing it. It is also what the WordPress mobile apps, Jetpack and some publishing tools use to reach your site — so this is only worth turning off if you do not use any of them. That is why it is a notice and not a warning: on plenty of perfectly healthy sites the right answer is to leave it alone.', 'my-site-hand' );
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
	 * Single pass — one request.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 1;
	}

	/**
	 * Skip entirely on sites that need XML-RPC.
	 *
	 * Jetpack communicates with WordPress.com over XML-RPC. Recommending that
	 * a Jetpack site disable it would be recommending that the site break.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		if ( class_exists( 'Jetpack' ) || defined( 'JETPACK__VERSION' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Probe xmlrpc.php in one pass.
	 *
	 * @param int $offset Unused; this check always completes in one call.
	 * @throws RuntimeException When the site cannot make HTTP requests to itself.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		/**
		 * Core's own switch. When something has already turned XML-RPC off
		 * there is nothing to report, and saying so would be noise.
		 *
		 * @param bool $enabled Whether XML-RPC methods requiring authentication are enabled.
		 */
		if ( ! apply_filters( 'xmlrpc_enabled', true ) ) {
			return $this->make_result( true, null, 1, [] );
		}

		$cached = $this->cache->get( $this->cache_key() );

		if ( is_array( $cached ) ) {
			return $this->make_result( true, null, 1, $this->build_issues( ! empty( $cached['reachable'] ) ) );
		}

		$reachable = $this->probe();

		if ( null === $reachable ) {
			// Same rule as the exposed files check: a loopback failure means we
			// learned nothing, and reporting nothing would read as an all-clear.
			throw new RuntimeException(
				esc_html__( 'This site could not make a request to itself, so xmlrpc.php could not be checked. Some hosts block loopback requests.', 'my-site-hand' )
			);
		}

		$this->cache->set( $this->cache_key(), [ 'reachable' => $reachable ], self::CACHE_TTL );

		return $this->make_result( true, null, 1, $this->build_issues( $reachable ) );
	}

	/**
	 * Build the single issue, when there is one.
	 *
	 * @param bool $reachable Whether xmlrpc.php answered.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_issues( bool $reachable ): array {
		if ( ! $reachable ) {
			return [];
		}

		return [
			$this->make_issue(
				[
					'title'    => __( 'xmlrpc.php is reachable', 'my-site-hand' ),
					'context'  => __( 'Fine if you use the WordPress mobile app, Jetpack or a publishing tool — worth closing if you do not', 'my-site-hand' ),
					'severity' => self::SEVERITY_NOTICE,
					'link'     => self::DOCS_URL,
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => self::DOCS_URL ],
					'fixable'  => false,
					'ability'  => null,
				]
			),
		];
	}

	/**
	 * Ask the site whether xmlrpc.php answers.
	 *
	 * WordPress replies to a GET on xmlrpc.php with 405 and a fixed line of
	 * text, so a 405 is the signal that the endpoint is alive. A 403 or 404
	 * means something in front of it is already blocking the file.
	 *
	 * @return bool|null True when reachable, false when blocked, null when the request failed.
	 */
	private function probe(): ?bool {
		$response = wp_remote_get(
			site_url( '/xmlrpc.php' ),
			[
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'sslverify'   => false,
				'user-agent'  => 'MySiteHand/' . MYSITEHAND_VERSION . '; ' . home_url( '/' ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 405 === $status ) {
			return true;
		}

		if ( 200 === $status ) {
			return str_contains( wp_remote_retrieve_body( $response ), 'XML-RPC server accepts POST requests only' );
		}

		return false;
	}

	/**
	 * Cache key for the probe result.
	 *
	 * @return string
	 */
	private function cache_key(): string {
		return 'health_xmlrpc_' . md5( site_url( '/' ) );
	}
}
