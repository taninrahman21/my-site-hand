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

		$options = [
			'abilities'  => array_map( 'sanitize_text_field', (array) $request->get_param( 'abilities' ) ),
			'expires_at' => $request->get_param( 'expires_at' ),
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




