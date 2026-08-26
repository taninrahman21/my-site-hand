<?php
/**
 * REST API controller — registers all my-site-hand/v1 endpoints.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Api;

defined( 'ABSPATH' ) || exit;

use MySiteHand\Abilities_Registry;
use MySiteHand\Auth_Manager;
use MySiteHand\Rate_Limiter;
use MySiteHand\Audit_Logger;
use MySiteHand\Cache_Manager;

/**
 * REST Controller class.
 *
 * Registers WP REST API endpoints for the my-site-hand admin API.
 * All endpoints are under the my-site-hand/v1 namespace.
 */
class Rest_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'my-site-hand/v1';

	/**
	 * Abilities registry.
	 *
	 * @var Abilities_Registry
	 */
	private Abilities_Registry $registry;

	/**
	 * Auth manager.
	 *
	 * @var Auth_Manager
	 */
	private Auth_Manager $auth;

	/**
	 * Rate limiter.
	 *
	 * @var Rate_Limiter
	 */
	private Rate_Limiter $rate_limiter;

	/**
	 * Audit logger.
	 *
	 * @var Audit_Logger
	 */
	private Audit_Logger $audit;

	/**
	 * Cache manager.
	 *
	 * @var Cache_Manager
	 */
	private Cache_Manager $cache;

	/**
	 * Constructor.
	 *
	 * @param Abilities_Registry $registry     Abilities registry.
	 * @param Auth_Manager       $auth         Auth manager.
	 * @param Rate_Limiter       $rate_limiter Rate limiter.
	 * @param Audit_Logger       $audit        Audit logger.
	 * @param Cache_Manager      $cache        Cache manager.
	 */
	public function __construct(
		Abilities_Registry $registry,
		Auth_Manager $auth,
		Rate_Limiter $rate_limiter,
		Audit_Logger $audit,
		Cache_Manager $cache
	) {
		$this->registry     = $registry;
		$this->auth         = $auth;
		$this->rate_limiter = $rate_limiter;
		$this->audit        = $audit;
		$this->cache        = $cache;
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /status — public, no auth.
		register_rest_route(
			self::NAMESPACE,
			'/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => '__return_true',
			]
		);

		// GET /abilities — auth required.
		register_rest_route(
			self::NAMESPACE,
			'/abilities',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_abilities' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		// GET /stats — manage_options required.
		register_rest_route(
			self::NAMESPACE,
			'/stats',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_stats' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		// GET /tokens — list current user's tokens.
		register_rest_route(
			self::NAMESPACE,
			'/tokens',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'list_tokens' ],
					'permission_callback' => [ $this, 'require_manage_options' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_token' ],
					'permission_callback' => [ $this, 'require_manage_options' ],
					'args'                => [
						'label'      => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'abilities'  => [
							'default'  => [],
							'type'     => 'array',
						],
						'expires_at' => [
							'default' => null,
						],
						'allowed_ips' => [
							'default' => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);

		// DELETE /tokens/{id}.
		register_rest_route(
			self::NAMESPACE,
			'/tokens/(?P<id>\d+)',
			[
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'revoke_token' ],
					'permission_callback' => [ $this, 'require_manage_options' ],
					'args'                => [
						'id' => [
							'type'     => 'integer',
							'required' => true,
						],
					],
				],
			]
		);

		// GET /audit-log.
		register_rest_route(
			self::NAMESPACE,
			'/audit-log',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_audit_log' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		// GET /audit-log/export — CSV download.
		register_rest_route(
			self::NAMESPACE,
			'/audit-log/export',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'export_audit_log' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		// POST /cache/clear.
		register_rest_route(
			self::NAMESPACE,
			'/cache/clear',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'clear_cache' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		$this->register_health_routes();
	}

	// -------------------------------------------------------------------------
	// Site Health
	//
	// These are admin-only browser endpoints for the Site Health scan. They are
	// NOT part of the token-authenticated MCP surface and deliberately do not
	// go through Auth_Manager: they use the standard wp_rest nonce and the
	// manage_options capability, like any other admin screen.
	// -------------------------------------------------------------------------

	/**
	 * Register the Site Health routes.
	 *
	 * @return void
	 */
	private function register_health_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/health/checks',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_health_checks' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/health/run-check',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'run_health_check' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
				'args'                => [
					'check'  => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value ) && '' !== trim( $value );
						},
					],
					'offset' => [
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value >= 0;
						},
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/health/finalize',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'finalize_health_scan' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/health/fix',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'fix_health_issue' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
				'args'                => [
					'check'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'fix_type' => [
						'required'          => true,
						'type'              => 'string',
						'enum'              => \MySiteHand\Health_Check_Base::FIX_TYPES,
						'sanitize_callback' => 'sanitize_key',
					],
					'fix_meta' => [
						'required' => true,
						'type'     => 'object',
					],
					'value'    => [
						'required' => false,
						'type'     => 'string',
						'default'  => '',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/health/latest',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_latest_health_report' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/health/history',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_health_history' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
				'args'                => [
					'limit' => [
						'required'          => false,
						'type'              => 'integer',
						'default'           => 8,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 52;
						},
					],
				],
			]
		);
	}

	/**
	 * Get the Site Health scanner.
	 *
	 * @return \MySiteHand\Site_Health_Scanner
	 */
	private function health(): \MySiteHand\Site_Health_Scanner {
		return \MySiteHand\Plugin::get_instance()->get_site_health_scanner();
	}

	/**
	 * GET /health/checks — available checks plus any run already in progress.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_health_checks( \WP_REST_Request $request ): \WP_REST_Response {
		$scanner = $this->health();
		$checks  = [];

		foreach ( $scanner->get_checks() as $id => $check ) {
			$checks[] = [
				'id'          => $id,
				'label'       => $check->get_label(),
				'description' => $check->get_description(),
				'severity'    => $check->get_severity(),
				'applicable'  => $check->is_applicable(),
			];
		}

		// The browser is stateless by design: a reload mid-scan resumes from
		// whatever the server already has.
		$state    = $scanner->get_run_state();
		$progress = [];

		foreach ( $state['checks'] as $id => $entry ) {
			$progress[ $id ] = [
				'status'      => $entry['status'] ?? 'pending',
				'scanned'     => (int) ( $entry['scanned'] ?? 0 ),
				'issue_count' => isset( $entry['issues'] ) ? count( (array) $entry['issues'] ) : 0,
			];
		}

		return new \WP_REST_Response(
			[
				'checks'    => $checks,
				'run_state' => [
					'started_at' => (int) ( $state['started_at'] ?? 0 ),
					'finalized'  => ! empty( $state['finalized_at'] ),
					'checks'     => $progress,
				],
			],
			200
		);
	}

	/**
	 * POST /health/run-check — run one bounded batch of one check.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_health_check( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->health()->run_check(
			(string) $request->get_param( 'check' ),
			(int) $request->get_param( 'offset' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /health/finalize — score the run and store the report.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function finalize_health_scan( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( $this->health()->finalize(), 200 );
	}

	/**
	 * GET /health/latest — the most recent stored report.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_latest_health_report( \WP_REST_Request $request ): \WP_REST_Response {
		$report = $this->health()->get_latest_report();

		return new \WP_REST_Response(
			[
				'report' => $report,
				'has_report' => null !== $report,
			],
			200
		);
	}

	/**
	 * Meta keys Quick Fix may write.
	 *
	 * Exactly the keys the checks emit, and nothing else. Accepting an
	 * arbitrary meta_key from the client would be an arbitrary-meta-write hole.
	 *
	 * @var array<int, string>
	 */
	private const FIXABLE_META_KEYS = [
		'_wp_attachment_image_alt',
		'_yoast_wpseo_metadesc',
		'rank_math_description',
	];

	/**
	 * POST /health/fix — repair a single issue in place.
	 *
	 * manage_options gets you to this endpoint; it does not get you past the
	 * per-object capability check below.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function fix_health_issue( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$check    = (string) $request->get_param( 'check' );
		$fix_type = (string) $request->get_param( 'fix_type' );
		$meta     = (array) $request->get_param( 'fix_meta' );
		$value    = (string) $request->get_param( 'value' );

		switch ( $fix_type ) {
			case 'inline_text':
				$result = $this->fix_inline_text( $meta, $value );
				break;

			case 'inline_url':
				$result = $this->fix_inline_url( $meta, $value );
				break;

			case 'delete':
				$result = $this->fix_delete( $meta );
				break;

			default:
				return new \WP_Error(
					'my_site_hand_fix_unsupported',
					__( 'That issue cannot be repaired from here.', 'my-site-hand' ),
					[ 'status' => 400 ]
				);
		}

		if ( is_wp_error( $result ) ) {
			$this->log_fix( $check, $fix_type, $meta, 'error', $result->get_error_message() );
			return $result;
		}

		$this->log_fix( $check, $fix_type, $meta, 'success', $result['summary'] );

		$fixes = \MySiteHand\Plugin::get_instance()->get_site_health_scanner()->record_fix();

		return new \WP_REST_Response(
			[
				'success' => true,
				'value'   => $result['value'],
				'fixes'   => $fixes,
			],
			200
		);
	}

	/**
	 * Write a post meta value from an allowlisted key.
	 *
	 * @param array<string, mixed> $meta  Fix metadata.
	 * @param string               $value New value.
	 * @return array{value: string, summary: string}|\WP_Error
	 */
	private function fix_inline_text( array $meta, string $value ): array|\WP_Error {
		$object_id = (int) ( $meta['attachment_id'] ?? $meta['post_id'] ?? 0 );
		$meta_key  = isset( $meta['meta_key'] ) ? (string) $meta['meta_key'] : '';

		if ( ! in_array( $meta_key, self::FIXABLE_META_KEYS, true ) ) {
			return new \WP_Error(
				'my_site_hand_fix_meta_key',
				__( 'That field cannot be edited from here.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		$error = $this->guard_object( $object_id, 'edit_post' );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$clean = sanitize_text_field( $value );

		if ( mb_strlen( $clean ) > 1000 ) {
			$clean = mb_substr( $clean, 0, 1000 );
		}

		if ( '' === trim( $clean ) ) {
			return new \WP_Error(
				'my_site_hand_fix_empty',
				__( 'Enter some text before saving.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		update_post_meta( $object_id, $meta_key, $clean );

		return [
			'value'   => $clean,
			'summary' => sprintf( 'Set %s on #%d', $meta_key, $object_id ),
		];
	}

	/**
	 * Replace a URL inside a post's link attributes.
	 *
	 * DOMDocument is used to confirm the URL really is a link target, then the
	 * replacement is applied to the href attribute itself. Re-serializing the
	 * whole document through DOMDocument would rewrite block editor markup and
	 * risk corrupting content that has nothing to do with the fix, so the
	 * write stays surgical: only href="old" becomes href="new", and prose that
	 * merely mentions the URL is untouched.
	 *
	 * @param array<string, mixed> $meta  Fix metadata.
	 * @param string               $value New URL.
	 * @return array{value: string, summary: string}|\WP_Error
	 */
	private function fix_inline_url( array $meta, string $value ): array|\WP_Error {
		$post_id = (int) ( $meta['post_id'] ?? 0 );
		$old_url = isset( $meta['old_url'] ) ? (string) $meta['old_url'] : '';

		$error = $this->guard_object( $post_id, 'edit_post' );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$new_url = $this->validate_link_url( $value );

		if ( null === $new_url ) {
			return new \WP_Error(
				'my_site_hand_fix_url',
				__( 'That does not look like a valid URL.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error(
				'my_site_hand_fix_missing',
				__( 'That post no longer exists.', 'my-site-hand' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->content_links_to( $post->post_content, $old_url ) ) {
			return new \WP_Error(
				'my_site_hand_fix_not_found',
				__( 'That link is no longer in this post. Scan again to refresh the report.', 'my-site-hand' ),
				[ 'status' => 409 ]
			);
		}

		$updated = $this->replace_href( $post->post_content, $old_url, $new_url );

		if ( $updated === $post->post_content ) {
			return new \WP_Error(
				'my_site_hand_fix_not_found',
				__( 'That link is no longer in this post. Scan again to refresh the report.', 'my-site-hand' ),
				[ 'status' => 409 ]
			);
		}

		$result = wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $updated,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'value'   => $new_url,
			'summary' => sprintf( 'Replaced a link in #%d', $post_id ),
		];
	}

	/**
	 * Validate a replacement link URL.
	 *
	 * Deliberately NOT wp_http_validate_url(): that function is built to vet
	 * URLs the server is about to fetch, so it resolves DNS and rejects
	 * private network hosts. Here the URL is only being written into post
	 * content, so a temporary DNS failure — or a perfectly legitimate intranet
	 * address — must not block the repair. What does matter is the scheme:
	 * javascript: and data: never reach the content.
	 *
	 * @param string $value Raw user input.
	 * @return string|null Safe URL, or null when it is not usable as a link.
	 */
	private function validate_link_url( string $value ): ?string {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		$parts = wp_parse_url( $value );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			return null;
		}

		$safe = esc_url_raw( $value, [ 'http', 'https' ] );

		return '' !== $safe ? $safe : null;
	}

	/**
	 * Whether a URL appears as an actual link target in some HTML.
	 *
	 * @param string $content HTML.
	 * @param string $url     URL to look for.
	 * @return bool
	 */
	private function content_links_to( string $content, string $url ): bool {
		if ( '' === $url || ! class_exists( 'DOMDocument' ) ) {
			return false;
		}

		$previous = libxml_use_internal_errors( true );

		$dom    = new \DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $content );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return false;
		}

		foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
			if ( $anchor->getAttribute( 'href' ) === $url ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Swap a URL inside href attributes only.
	 *
	 * @param string $content HTML.
	 * @param string $old_url URL to replace.
	 * @param string $new_url Replacement URL.
	 * @return string
	 */
	private function replace_href( string $content, string $old_url, string $new_url ): string {
		// The stored content may hold the URL raw or entity-encoded.
		$variants = array_unique( [ $old_url, esc_attr( $old_url ), htmlspecialchars( $old_url, ENT_QUOTES ) ] );

		foreach ( $variants as $variant ) {
			$pattern = '/(href\s*=\s*(["\']))' . preg_quote( $variant, '/' ) . '(\2)/i';
			$result  = preg_replace( $pattern, '${1}' . str_replace( '$', '\$', esc_url_raw( $new_url ) ) . '${3}', $content );

			if ( is_string( $result ) && $result !== $content ) {
				return $result;
			}
		}

		return $content;
	}

	/**
	 * Move an attachment to the trash.
	 *
	 * wp_trash_post(), not wp_delete_attachment( $id, false ). That looks like
	 * the safe call but is not: it only trashes when the MEDIA_TRASH constant
	 * is defined, and WordPress leaves it undefined by default — so on most
	 * sites it deletes the file permanently. This check reports false
	 * positives by design, so an unrecoverable delete is not acceptable.
	 *
	 * @param array<string, mixed> $meta Fix metadata.
	 * @return array{value: string, summary: string}|\WP_Error
	 */
	private function fix_delete( array $meta ): array|\WP_Error {
		$attachment_id = (int) ( $meta['attachment_id'] ?? 0 );

		$error = $this->guard_object( $attachment_id, 'delete_post' );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$trashed = wp_trash_post( $attachment_id );

		if ( ! $trashed ) {
			return new \WP_Error(
				'my_site_hand_fix_delete',
				__( 'That file could not be moved to the trash.', 'my-site-hand' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'value'   => '',
			'summary' => sprintf( 'Moved attachment #%d to the trash', $attachment_id ),
		];
	}

	/**
	 * Check the object exists and the current user may act on it.
	 *
	 * @param int    $object_id  Post or attachment ID.
	 * @param string $capability Capability to require.
	 * @return \WP_Error|null
	 */
	private function guard_object( int $object_id, string $capability ): ?\WP_Error {
		if ( $object_id <= 0 || null === get_post( $object_id ) ) {
			return new \WP_Error(
				'my_site_hand_fix_missing',
				__( 'That item no longer exists.', 'my-site-hand' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! current_user_can( $capability, $object_id ) ) {
			return new \WP_Error(
				'my_site_hand_fix_forbidden',
				__( 'You are not allowed to change that item.', 'my-site-hand' ),
				[ 'status' => 403 ]
			);
		}

		return null;
	}

	/**
	 * Record a repair in the same history as AI actions.
	 *
	 * @param string               $check    Check id.
	 * @param string               $fix_type Fix type.
	 * @param array<string, mixed> $meta     Fix metadata.
	 * @param string               $status   'success' or 'error'.
	 * @param string               $summary  Human-readable result.
	 * @return void
	 */
	private function log_fix( string $check, string $fix_type, array $meta, string $status, string $summary ): void {
		$this->audit->log(
			[
				'token_id'       => null,
				'user_id'        => get_current_user_id(),
				'ability_name'   => 'my-site-hand/quick-fix',
				'input'          => [
					'check'    => $check,
					'fix_type' => $fix_type,
					'fix_meta' => $meta,
				],
				'result_status'  => $status,
				'result_summary' => $summary,
				'duration_ms'    => 0,
			]
		);
	}

	/**
	 * GET /health/history — recent scores for the trend display.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_health_history( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(
			[ 'history' => $this->health()->get_history( (int) $request->get_param( 'limit' ) ) ],
			200
		);
	}

	// -------------------------------------------------------------------------
	// Endpoint handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /status — public server status.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
		$enabled = (bool) get_option( 'mysitehand_enabled', true );

		return new \WP_REST_Response(
			[
				'status'       => $enabled ? 'active' : 'disabled',
				'version'      => MYSITEHAND_VERSION,
				'mcp_endpoint' => rest_url( 'my-site-hand/v1/mcp/streamable' ),
				'timestamp'    => current_time( 'c' ),
			],
			200
		);
	}

	/**
	 * GET /abilities — list all registered abilities.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_abilities( \WP_REST_Request $request ): \WP_REST_Response {
		$abilities = $this->registry->get_all();
		$formatted = [];

		foreach ( $abilities as $name => $ability ) {
			$formatted[] = [
				'name'        => $name,
				'description' => $ability['description'],
				'annotations' => $ability['annotations'],
				'mcp_public'  => ! empty( $ability['annotations']['meta']['mcp']['public'] ),
			];
		}

		return new \WP_REST_Response(
			[
				'abilities' => $formatted,
				'count'     => count( $formatted ),
			],
			200
		);
	}

	/**
	 * GET /stats — dashboard statistics.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
		$audit_stats = $this->audit->get_stats();
		$token_count = count( $this->auth->list_tokens( 0 ) );
		$ability_count = count( $this->registry->get_all() );

		return new \WP_REST_Response(
			[
				'calls_today'    => $audit_stats['calls_today'],
				'active_tokens'  => $token_count,
				'abilities'      => $ability_count,
				'errors_24h'     => $audit_stats['errors_24h'],
				'top_abilities'  => $audit_stats['top_abilities'],
				'avg_duration'   => $audit_stats['avg_duration'],
				'error_rate'     => $audit_stats['error_rate'],
			],
			200
		);
	}

	/**
	 * GET /tokens — list tokens for current user.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function list_tokens( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = get_current_user_id();
		$all     = current_user_can( 'manage_options' );
		$tokens  = $this->auth->list_tokens( $all ? 0 : $user_id );

		return new \WP_REST_Response( [ 'tokens' => $tokens ], 200 );
	}

	/**
	 * POST /tokens — generate a new token.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function create_token( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify nonce for admin requests.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Nonce verification failed.', 'my-site-hand' ) ], 403 );
		}

		$user_id = get_current_user_id();
		$label   = sanitize_text_field( $request->get_param( 'label' ) );

		if ( empty( $label ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Label is required.', 'my-site-hand' ) ], 400 );
		}

		$abilities = array_map( 'sanitize_text_field', (array) $request->get_param( 'abilities' ) );
		if ( ! in_array( '*', $abilities, true ) && empty( $abilities ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Limited access tokens must select at least one ability.', 'my-site-hand' ) ], 400 );
		}

		$allowed_ips_raw = $request->get_param( 'allowed_ips' );
		if ( ! empty( $allowed_ips_raw ) ) {
			$ips = array_filter( array_map( 'trim', explode( ',', $allowed_ips_raw ) ) );
			foreach ( $ips as $ip ) {
				// Basic regex validation for IPv4/IPv6/CIDR
				if ( ! preg_match( '/^[a-fA-F0-9\.:]+(\/\d{1,2})?$/', $ip ) ) {
					/* translators: %s: the invalid IP address or CIDR range */
					return new \WP_REST_Response( [ 'message' => sprintf( __( 'Invalid IP or CIDR format: %s', 'my-site-hand' ), esc_html( $ip ) ) ], 400 );
				}
			}
		}

		$options = [
			'abilities'   => $abilities,
			'expires_at'  => $request->get_param( 'expires_at' ),
			'allowed_ips' => $allowed_ips_raw,
		];

		$result = $this->auth->generate_token( $user_id, $label, $options );

		return new \WP_REST_Response(
			[
				'token'    => $result['token'],
				'token_id' => $result['token_id'],
				'message'  => __( 'Save this token — it will not be shown again.', 'my-site-hand' ),
			],
			201
		);
	}

	/**
	 * DELETE /tokens/{id} — revoke a token.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function revoke_token( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Nonce verification failed.', 'my-site-hand' ) ], 403 );
		}

		$token_id        = absint( $request->get_param( 'id' ) );
		$requesting_user = get_current_user_id();

		$revoked = $this->auth->revoke_token( $token_id, $requesting_user );

		if ( ! $revoked ) {
			return new \WP_REST_Response( [ 'message' => __( 'Failed to revoke token.', 'my-site-hand' ) ], 400 );
		}

		return new \WP_REST_Response( [ 'revoked' => true, 'token_id' => $token_id ], 200 );
	}

	/**
	 * GET /audit-log — paginated audit log.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_audit_log( \WP_REST_Request $request ): \WP_REST_Response {
		$filters = [
			'per_page'     => absint( $request->get_param( 'per_page' ) ?: 20 ),
			'page'         => absint( $request->get_param( 'page' ) ?: 1 ),
			'token_id'     => $request->get_param( 'token_id' ),
			'ability_name' => $request->get_param( 'ability_name' ),
			'status'       => $request->get_param( 'status' ),
			'date_from'    => $request->get_param( 'date_from' ),
			'date_to'      => $request->get_param( 'date_to' ),
			'search'       => $request->get_param( 'search' ),
		];

		// Remove empty filters.
		$filters = array_filter( $filters, static fn( $v ) => null !== $v && '' !== $v );

		$result = $this->audit->get_logs( $filters );

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /audit-log/export — CSV export.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return void
	 */
	public function export_audit_log( \WP_REST_Request $request ): void {
		$nonce = $request->get_param( 'nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'my_site_hand_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'my-site-hand' ) );
		}

		$logs   = $this->audit->get_logs( [ 'per_page' => 5000, 'page' => 1 ] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$output = fopen( 'php://output', 'w' );

		if ( ! $output ) {
			wp_die( esc_html__( 'Could not open output stream.', 'my-site-hand' ) );
		}

		// Headers for CSV download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="my-site-hand-audit-log-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// CSV header row.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fputcsv( $output, [ 'ID', 'Token ID', 'User ID', 'Ability', 'Status', 'Duration (ms)', 'IP Address', 'Executed At', 'Summary' ] );

		foreach ( $logs['logs'] as $log ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
			fputcsv( $output, [
				$log['id'],
				$log['token_id'],
				$log['user_id'],
				$log['ability_name'],
				$log['result_status'],
				$log['duration_ms'],
				$log['ip_address'],
				$log['executed_at'],
				$log['result_summary'],
			] );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * POST /cache/clear — clear all my-site-hand caches.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function clear_cache( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify nonce.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Nonce verification failed.', 'my-site-hand' ) ], 403 );
		}

		$count = $this->cache->clear_all();

		return new \WP_REST_Response(
			[
				'cleared' => true,
				'count'   => $count,
				'message' => sprintf(
					/* translators: %d: number of transients cleared */
					__( 'Cleared %d cached items.', 'my-site-hand' ),
					$count
				),
			],
			200
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Require manage_options capability.
	 *
	 * @return bool
	 */
	public function require_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}
}




