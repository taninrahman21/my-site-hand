<?php
/**
 * AI Chat REST controller.
 *
 * @package MySiteHand
 */

namespace MySiteHand\AI;

defined( 'ABSPATH' ) || exit;

use MySiteHand\Abilities_Registry;
use MySiteHand\Audit_Logger;

/**
 * Chat_Controller class.
 *
 * Registers the REST endpoints that power the native AI Assistant chat UI and
 * orchestrates the full request/response cycle: it loads conversation history,
 * asks the configured AI provider to choose a tool, executes that tool through
 * the Abilities_Registry, and returns a human-friendly reply.
 */
class Chat_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'my-site-hand/v1';

	/**
	 * Maximum number of messages kept/loaded per session.
	 *
	 * @var int
	 */
	private const HISTORY_LIMIT = 50;

	/**
	 * Maximum number of tool-calling round-trips per user message.
	 *
	 * Caps the agentic loop so a misbehaving model cannot call tools forever.
	 *
	 * @var int
	 */
	private const MAX_TOOL_STEPS = 8;

	/**
	 * Free daily message limit enforced by the proxy.
	 *
	 * @var int
	 */
	private const FREE_DAILY_LIMIT = 10;

	/**
	 * Abilities registry.
	 *
	 * @var Abilities_Registry
	 */
	private Abilities_Registry $registry;

	/**
	 * Audit logger.
	 *
	 * @var Audit_Logger
	 */
	private Audit_Logger $audit;

	/**
	 * Constructor.
	 *
	 * @param Abilities_Registry $registry Abilities registry.
	 * @param Audit_Logger       $audit    Audit logger.
	 */
	public function __construct( Abilities_Registry $registry, Audit_Logger $audit ) {
		$this->registry = $registry;
		$this->audit    = $audit;
	}

	/**
	 * Register the chat REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/chat/send',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_send' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/chat/clear',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_clear' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/chat/history',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_history' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/chat/sessions',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_list_sessions' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/chat/test-connection',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_test_connection' ],
				'permission_callback' => [ $this, 'require_manage_options' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/chat/usage',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_usage' ],
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Endpoint handlers
	// -------------------------------------------------------------------------

	/**
	 * POST /chat/send — main chat endpoint.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_send( \WP_REST_Request $request ): \WP_REST_Response {
		$message    = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$session_id = $this->sanitize_session_id( (string) $request->get_param( 'session_id' ) );

		if ( '' === trim( $message ) ) {
			return new \WP_REST_Response( [ 'message' => __( 'Message cannot be empty.', 'my-site-hand' ) ], 400 );
		}

		if ( '' === $session_id ) {
			return new \WP_REST_Response( [ 'message' => __( 'A valid session ID is required.', 'my-site-hand' ) ], 400 );
		}

		$user_id = get_current_user_id();

		// Load prior history (folded) and append the new user message.
		$conversation = $this->get_history( $session_id );
		$this->insert_message( $session_id, $user_id, 'user', $message );
		$conversation[] = [ 'role' => 'user', 'content' => $message ];

		// Upsert thread record.
		global $wpdb;
		$title = wp_trim_words( $message, 5, '...' );
		$time  = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}mysitehand_chat_threads (session_id, user_id, title, created_at, updated_at) VALUES (%s, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE updated_at = %s",
				$session_id,
				$user_id,
				$title,
				$time,
				$time,
				$time
			)
		);

		$tools    = $this->registry->get_all_as_mcp_tool_schemas();
		$provider = new AI_Provider();

		$this->debug_log( sprintf( 'send: %d tools available, provider=%s', count( $tools ), (string) get_option( 'mysitehand_ai_provider', '' ) ) );

		$tools_used   = [];
		$reply        = '';
		$last_summary = '';
		$last_error   = false;

		// Agentic loop: keep letting the model call tools until it produces a
		// final text reply (or we hit the safety cap), the way an MCP client does.
		for ( $step = 0; $step < self::MAX_TOOL_STEPS; $step++ ) {
			try {
				$response = $provider->call( $conversation, $tools );
			} catch ( \Throwable $e ) {
				$is_limit = 429 === (int) $e->getCode();
				return new \WP_REST_Response(
					[
						'error'       => $is_limit ? 'daily_limit_reached' : 'provider_error',
						'message'     => $e->getMessage(),
						'upgrade_url' => admin_url( 'admin.php?page=my-site-hand-settings&tab=ai-assistant' ),
					],
					$is_limit ? 429 : 400
				);
			}

			$this->debug_log(
				sprintf(
					'step %d: response type=%s%s',
					$step,
					(string) ( $response['type'] ?? 'unknown' ),
					isset( $response['tool_calls'] )
						? ' tools=' . implode( ',', array_map( static fn( $c ) => (string) ( $c['name'] ?? '' ), $response['tool_calls'] ) )
						: ''
				)
			);

			// Final text answer — we're done.
			if ( 'text' === ( $response['type'] ?? '' ) ) {
				$reply = (string) ( $response['content'] ?? '' );
				break;
			}

			if ( 'tool_call' !== ( $response['type'] ?? '' ) || empty( $response['tool_calls'] ) ) {
				break;
			}

			// Record the assistant's tool-call turn so the model keeps context.
			$conversation[] = [
				'role'       => 'assistant',
				'tool_calls' => $response['tool_calls'],
			];

			// Execute each requested tool and feed the results back.
			foreach ( $response['tool_calls'] as $call ) {
				$tool_name = (string) ( $call['name'] ?? '' );
				$tool_args = isset( $call['args'] ) && is_array( $call['args'] ) ? $call['args'] : [];
				$call_id   = (string) ( $call['id'] ?? $tool_name );

				$started = microtime( true );
				$result  = $this->registry->execute( $tool_name, $tool_args, $user_id );
				$elapsed = (int) round( ( microtime( true ) - $started ) * 1000 );

				$is_error       = is_wp_error( $result );
				$result_summary = $this->summarize_result( $result );
				$status         = $is_error ? 'error' : 'success';

				$last_summary = $result_summary;
				$last_error   = $is_error;
				$tools_used[] = [ 'name' => $tool_name, 'status' => $status ];

				// Persist + feed back the tool result.
				$this->insert_message( $session_id, $user_id, 'tool', $result_summary, $tool_name, $status );
				$conversation[] = [
					'role'         => 'tool',
					'tool_call_id' => $call_id,
					'tool_name'    => $tool_name,
					'content'      => $result_summary,
				];

				$this->audit->log(
					[
						'user_id'        => $user_id,
						'ability_name'   => $tool_name,
						'input'          => $tool_args,
						'result_status'  => $status,
						'result_summary' => $result_summary,
						'duration_ms'    => $elapsed,
					]
				);
			}
		}

		// If the model never produced a closing message, summarize the last result.
		if ( '' === trim( $reply ) ) {
			if ( '' !== $last_summary ) {
				$reply = $last_error
					? sprintf(
						/* translators: %s: error message */
						__( 'The tool returned an error: %s', 'my-site-hand' ),
						$last_summary
					)
					: $last_summary;
			} else {
				$reply = __( 'I could not generate a response. Please try rephrasing your request.', 'my-site-hand' );
			}
		}

		// Persist the assistant reply.
		$this->insert_message( $session_id, $user_id, 'assistant', $reply );

		$first_tool = $tools_used[0] ?? null;

		return new \WP_REST_Response(
			[
				'reply'       => $reply,
				'tools_used'  => $tools_used,
				'tool_used'   => $first_tool ? $first_tool['name'] : null,
				'tool_status' => $first_tool ? $first_tool['status'] : null,
				'session_id'  => $session_id,
			],
			200
		);
	}

	/**
	 * GET /chat/history — recent messages for a session.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_history( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id = $this->sanitize_session_id( (string) $request->get_param( 'session_id' ) );

		if ( '' === $session_id ) {
			return new \WP_REST_Response( [], 200 );
		}

		return new \WP_REST_Response( $this->get_history( $session_id ), 200 );
	}

	/**
	 * POST /chat/clear — delete all messages for a session.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_clear( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id = $this->sanitize_session_id( (string) $request->get_param( 'session_id' ) );

		if ( '' === $session_id ) {
			return new \WP_REST_Response( [ 'message' => __( 'A valid session ID is required.', 'my-site-hand' ) ], 400 );
		}

		$this->delete_session( $session_id );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'mysitehand_chat_threads', [ 'session_id' => $session_id ] );

		return new \WP_REST_Response( [ 'cleared' => true ], 200 );
	}

	/**
	 * GET /chat/sessions — list recent conversations.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_list_sessions( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, title, updated_at FROM {$wpdb->prefix}mysitehand_chat_threads WHERE user_id = %d ORDER BY updated_at DESC LIMIT 50",
				$user_id
			),
			ARRAY_A
		);

		return new \WP_REST_Response( $rows ?: [], 200 );
	}

	/**
	 * POST /chat/test-connection — verify provider/key/model.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_test_connection( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = new AI_Provider();
		$result   = $provider->test_connection();

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => $result->get_error_message(),
				],
				200
			);
		}

		return new \WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Return today's usage. Fetches from proxy when proxy is active.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_usage(): \WP_REST_Response {
		if ( ! AI_Provider::is_using_proxy() ) {
			return new \WP_REST_Response(
				[
					'using_proxy' => false,
					'limit'       => null,
					'used'        => null,
					'remaining'   => null,
				],
				200
			);
		}

		$response = wp_remote_get(
			\MySiteHand\AI\AI_Provider::PROXY_URL . '/v1/usage',
			[
				'timeout' => 8,
				'headers' => [
					'X-MSH-Secret' => \MySiteHand\AI\AI_Provider::PROXY_SECRET,
					'X-MSH-Site'   => home_url(),
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_REST_Response(
				[
					'using_proxy' => true,
					'limit'       => self::FREE_DAILY_LIMIT,
					'used'        => null,
					'remaining'   => null,
				],
				200
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return new \WP_REST_Response(
			[
				'using_proxy' => true,
				'limit'       => (int) ( $data['limit'] ?? self::FREE_DAILY_LIMIT ),
				'used'        => (int) ( $data['used'] ?? 0 ),
				'remaining'   => (int) ( $data['remaining'] ?? self::FREE_DAILY_LIMIT ),
				'resets_at'   => (string) ( $data['resets_at'] ?? 'midnight UTC' ),
			],
			200
		);
	}

	// -------------------------------------------------------------------------
	// Database helpers
	// -------------------------------------------------------------------------

	/**
	 * Load the most recent messages for a session, oldest first.
	 *
	 * @param string $session_id Sanitized session ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_history( string $session_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'mysitehand_chat_sessions';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, tool_name, tool_status FROM {$wpdb->prefix}mysitehand_chat_sessions WHERE session_id = %s ORDER BY id DESC LIMIT %d",
				$session_id,
				self::HISTORY_LIMIT
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [];
		}

		// Re-order oldest-first for chronological display / replay.
		$rows = array_reverse( $rows );

		$messages = [];
		foreach ( $rows as $row ) {
			$message = [
				'role'    => (string) $row['role'],
				'content' => (string) $row['content'],
			];

			if ( 'tool' === $row['role'] ) {
				$message['tool_name']   = (string) ( $row['tool_name'] ?? '' );
				$message['tool_status'] = (string) ( $row['tool_status'] ?? '' );
			}

			$messages[] = $message;
		}

		return $messages;
	}

	/**
	 * Insert one chat message row.
	 *
	 * @param string      $session_id  Sanitized session ID.
	 * @param int         $user_id     User ID.
	 * @param string      $role        'user' | 'assistant' | 'tool'.
	 * @param string      $content     Message content.
	 * @param string|null $tool_name   Tool name (for tool rows).
	 * @param string|null $tool_status Tool status (for tool rows).
	 * @return void
	 */
	private function insert_message(
		string $session_id,
		int $user_id,
		string $role,
		string $content,
		?string $tool_name = null,
		?string $tool_status = null
	): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$wpdb->prefix . 'mysitehand_chat_sessions',
			[
				'session_id'  => $session_id,
				'user_id'     => $user_id,
				'role'        => $role,
				'content'     => $content,
				'tool_name'   => $tool_name,
				'tool_status' => $tool_status,
				'created_at'  => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Delete all messages for a session.
	 *
	 * @param string $session_id Sanitized session ID.
	 * @return void
	 */
	private function delete_session( string $session_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'mysitehand_chat_sessions',
			[ 'session_id' => $session_id ],
			[ '%s' ]
		);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert a tool execution result into a short string summary.
	 *
	 * @param mixed $result Result returned by Abilities_Registry::execute().
	 * @return string
	 */
	private function summarize_result( mixed $result ): string {
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		if ( is_scalar( $result ) ) {
			return (string) $result;
		}

		if ( null === $result ) {
			return __( 'Done.', 'my-site-hand' );
		}

		// Arrays / objects: JSON-encode, truncated for sanity.
		$encoded = wp_json_encode( $result );
		if ( false === $encoded ) {
			return __( 'Completed, but the result could not be summarized.', 'my-site-hand' );
		}

		if ( strlen( $encoded ) > 2000 ) {
			$encoded = substr( $encoded, 0, 2000 ) . '…';
		}

		return $encoded;
	}

	/**
	 * Write a diagnostic line to the PHP error log when WP_DEBUG is on.
	 *
	 * Never logs message content or the API key — only structural info that
	 * helps diagnose tool-calling behavior.
	 *
	 * @param string $message Diagnostic message.
	 * @return void
	 */
	private function debug_log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[My Site Hand AI] ' . $message );
		}
	}

	/**
	 * Sanitize a browser-supplied session ID.
	 *
	 * @param string $session_id Raw session ID.
	 * @return string Sanitized key (max 64 chars), or empty string.
	 */
	private function sanitize_session_id( string $session_id ): string {
		// sanitize_key lowercases and strips to [a-z0-9_-]; UUIDs survive this.
		return substr( sanitize_key( $session_id ), 0, 64 );
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Require manage_options capability for all chat endpoints.
	 *
	 * @return bool
	 */
	public function require_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}
}
