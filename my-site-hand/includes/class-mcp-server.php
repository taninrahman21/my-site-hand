<?php
/**
 * MCP Server — implements the Model Context Protocol over HTTP.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * MCP Server class.
 *
 * Implements the full MCP JSON-RPC 2.0 protocol over HTTP Streamable transport.
 * Handles initialize, tools/list, tools/call, and ping methods.
 * Enforces authentication, rate limiting, and audit logging on every tool call.
 */
class MCP_Server {

	/**
	 * MCP protocol version.
	 *
	 * @var string
	 */
	private const PROTOCOL_VERSION = '2024-11-05';

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
	 * Constructor.
	 *
	 * @param Abilities_Registry $registry     Abilities registry.
	 * @param Auth_Manager       $auth         Authentication manager.
	 * @param Rate_Limiter       $rate_limiter Rate limiter.
	 * @param Audit_Logger       $audit        Audit logger.
	 */
	public function __construct(
		Abilities_Registry $registry,
		Auth_Manager $auth,
		Rate_Limiter $rate_limiter,
		Audit_Logger $audit
	) {
		$this->registry     = $registry;
		$this->auth         = $auth;
		$this->rate_limiter = $rate_limiter;
		$this->audit        = $audit;
	}

	/**
	 * Register the MCP REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// This endpoint is intentionally public (permission_callback: __return_true)
		// because authentication is handled at the application layer via MCP bearer tokens,
		// validated inside handle_post() and handle_get() through the AuthManager class.
		// WordPress session/cookie authentication is not applicable for machine-to-machine
		// MCP client connections. Unauthenticated requests are rejected inside the handlers.
		register_rest_route(
			'my-site-hand/v1',
			'/mcp/streamable',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'handle_post' ],
					'permission_callback' => '__return_true', // Auth handled inside.
				],
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'handle_get' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Handle POST MCP JSON-RPC requests.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_post( \WP_REST_Request $request ): \WP_REST_Response {
		// Authenticate.
		$token = $this->auth->authenticate_request();
		if ( is_wp_error( $token ) ) {
			return $this->error_response( null, -32000, $token->get_error_message(), 401 );
		}

		$body = $request->get_body();
		if ( empty( $body ) ) {
			return $this->error_response( null, -32700, __( 'Parse error: empty request body.', 'my-site-hand' ), 400 );
		}

		$payload = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return $this->error_response( null, -32700, __( 'Parse error: invalid JSON.', 'my-site-hand' ), 400 );
		}

		// Check if batch request.
		if ( isset( $payload[0] ) && is_array( $payload[0] ) ) {
			return $this->handle_batch( $payload, $token );
		}

		return $this->handle_single( $payload, $token );
	}

	/**
	 * Handle GET requests — returns server info (SSE upgrade placeholder).
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_get( \WP_REST_Request $request ): \WP_REST_Response {
		// Authenticate.
		$token = $this->auth->authenticate_request();
		if ( is_wp_error( $token ) ) {
			return new \WP_REST_Response(
				[ 'error' => $token->get_error_message() ],
				401
			);
		}

		$server_info = $this->get_server_info();

		return new \WP_REST_Response(
			[
				'status'     => 'ready',
				'serverInfo' => $server_info,
				'endpoint'   => rest_url( 'my-site-hand/v1/mcp/streamable' ),
			],
			200
		);
	}

	/**
	 * Handle a single JSON-RPC request object.
	 *
	 * @param array<string, mixed>  $payload JSON-RPC request.
	 * @param array<string, mixed>  $token   Validated token record.
	 * @return \WP_REST_Response
	 */
	private function handle_single( array $payload, array $token ): \WP_REST_Response {
		$id     = $payload['id'] ?? null;
		$method = $payload['method'] ?? '';
		$params = $payload['params'] ?? [];

		// JSON-RPC version check.
		if ( ( $payload['jsonrpc'] ?? '' ) !== '2.0' ) {
			return $this->error_response( $id, -32600, __( 'Invalid Request: jsonrpc must be "2.0".', 'my-site-hand' ) );
		}

		return match ( $method ) {
			'initialize'   => $this->handle_initialize( $id, $params ),
			'tools/list'   => $this->handle_tools_list( $id, $token ),
			'tools/call'   => $this->handle_tools_call( $id, $params, $token ),
			'ping'         => $this->handle_ping( $id ),
			default        => $this->error_response( $id, -32601, sprintf(
				/* translators: %s: method name */
				__( 'Method not found: %s', 'my-site-hand' ),
				sanitize_text_field( $method )
			) ),
		};
	}

	/**
	 * Handle a batch of JSON-RPC requests.
	 *
	 * @param array<int, array>    $batch Batch of request objects.
	 * @param array<string, mixed> $token Validated token.
	 * @return \WP_REST_Response
	 */
	private function handle_batch( array $batch, array $token ): \WP_REST_Response {
		$responses = [];
		foreach ( $batch as $request ) {
			if ( ! is_array( $request ) ) {
				$responses[] = [ 'jsonrpc' => '2.0', 'id' => null, 'error' => [ 'code' => -32600, 'message' => 'Invalid Request' ] ];
				continue;
			}
			$response      = $this->handle_single( $request, $token );
			$responses[]   = $response->get_data();
		}
		return new \WP_REST_Response( $responses, 200 );
	}

	/**
	 * Handle initialize method.
	 *
	 * @param mixed                $id     JSON-RPC request ID.
	 * @param array<string, mixed> $params Request params.
	 * @return \WP_REST_Response
	 */
	private function handle_initialize( mixed $id, array $params ): \WP_REST_Response {
		$server_info = $this->get_server_info();

		return $this->success_response(
			$id,
			[
				'protocolVersion' => self::PROTOCOL_VERSION,
				'capabilities'    => [
					'tools' => [
						'listChanged' => false,
					],
				],
				'serverInfo'      => $server_info,
			]
		);
	}

	/**
	 * Handle tools/list method.
	 *
	 * @param mixed                $id    JSON-RPC request ID.
	 * @param array<string, mixed> $token Token record.
	 * @return \WP_REST_Response
	 */
	private function handle_tools_list( mixed $id, array $token ): \WP_REST_Response {
		$schemas = $this->registry->get_all_as_mcp_tool_schemas();

		// If token has ability restrictions, filter the list.
		if ( ! empty( $token['abilities'] ) ) {
			$allowed = $token['abilities'];
			$schemas = array_values( array_filter(
				$schemas,
				static fn( array $schema ) => in_array( $schema['name'], $allowed, true )
			) );
		}

		// Sanitize tool names for MCP spec compliance (no slashes allowed).
		$schemas = array_map( function ( array $schema ): array {
			$schema['name'] = self::to_mcp_tool_name( $schema['name'] );
			return $schema;
		}, $schemas );

		return $this->success_response( $id, [ 'tools' => $schemas ] );
	}

	/**
	 * Handle tools/call method.
	 *
	 * @param mixed                $id     JSON-RPC request ID.
	 * @param array<string, mixed> $params Request params containing name and arguments.
	 * @param array<string, mixed> $token  Token record.
	 * @return \WP_REST_Response
	 */
	private function handle_tools_call( mixed $id, array $params, array $token ): \WP_REST_Response {
		$mcp_name  = sanitize_text_field( $params['name'] ?? '' );
		$tool_name = self::from_mcp_tool_name( $mcp_name );
		$arguments = $params['arguments'] ?? [];

		if ( empty( $tool_name ) ) {
			return $this->error_response( $id, -32602, __( 'Invalid params: tool name is required.', 'my-site-hand' ) );
		}

		$token_id = (int) $token['id'];

		// Check token ability restrictions.
		if ( ! empty( $token['abilities'] ) && ! in_array( $tool_name, $token['abilities'], true ) ) {
			$this->audit->log( [
				'token_id'      => $token_id,
				'user_id'       => $token['user_id'],
				'ability_name'  => $tool_name,
				'input'         => $arguments,
				'result_status' => 'error',
				'result_summary' => 'Token not authorized for this ability.',
			] );

			return $this->error_response( $id, -32000, __( 'Token not authorized for this ability.', 'my-site-hand' ), 403 );
		}

		// Rate limit check.
		$rate_check = $this->rate_limiter->check( $token_id );
		if ( is_wp_error( $rate_check ) ) {
			$this->audit->log( [
				'token_id'      => $token_id,
				'user_id'       => $token['user_id'],
				'ability_name'  => $tool_name,
				'input'         => $arguments,
				'result_status' => 'rate_limited',
				'result_summary' => $rate_check->get_error_message(),
			] );

			return $this->error_response( $id, -32029, $rate_check->get_error_message(), 429 );
		}

		// Increment rate limiter.
		$this->rate_limiter->increment( $token_id );

		// Execute the ability.
		$start_time = microtime( true );
		$user_id    = (int) $token['user_id'];
		$result     = $this->registry->execute( $tool_name, is_array( $arguments ) ? $arguments : [], $user_id );
		$duration   = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		// Log the execution.
		if ( is_wp_error( $result ) ) {
			$this->audit->log( [
				'token_id'      => $token_id,
				'user_id'       => $user_id,
				'ability_name'  => $tool_name,
				'input'         => $arguments,
				'result_status' => 'error',
				'result_summary' => $result->get_error_message(),
				'duration_ms'   => $duration,
			] );

			return $this->tool_error_response( $id, $result->get_error_message() );
		}

		$result_summary = is_string( $result ) ? $result : wp_json_encode( $result );
		$this->audit->log( [
			'token_id'      => $token_id,
			'user_id'       => $user_id,
			'ability_name'  => $tool_name,
			'input'         => $arguments,
			'result_status' => 'success',
			'result_summary' => is_string( $result_summary ) ? substr( $result_summary, 0, 500 ) : '',
			'duration_ms'   => $duration,
		] );

		// Format result as MCP tool result.
		$text = is_string( $result ) ? $result : wp_json_encode( $result, JSON_PRETTY_PRINT );

		return $this->success_response(
			$id,
			[
				'content' => [
					[
						'type' => 'text',
						'text' => $text ?: '',
					],
				],
				'isError' => false,
			]
		);
	}

	/**
	 * Handle ping method.
	 *
	 * @param mixed $id JSON-RPC request ID.
	 * @return \WP_REST_Response
	 */
	private function handle_ping( mixed $id ): \WP_REST_Response {
		return $this->success_response( $id, new \stdClass() );
	}

	/**
	 * Get server info array for MCP initialize response.
	 *
	 * @return array<string, string>
	 */
	private function get_server_info(): array {
		$info = [
			'name'    => get_option( 'mysitehand_display_name', 'WP my-site-hand' ),
			'version' => MYSITEHAND_VERSION,
		];

		return apply_filters( 'my_site_hand_server_info', $info );
	}

	/**
	 * Build a successful JSON-RPC response.
	 *
	 * @param mixed $id     JSON-RPC request ID.
	 * @param mixed $result Result payload.
	 * @return \WP_REST_Response
	 */
	private function success_response( mixed $id, mixed $result ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => $result,
			],
			200
		);
	}

	/**
	 * Build an error JSON-RPC response.
	 *
	 * @param mixed  $id      JSON-RPC request ID.
	 * @param int    $code    JSON-RPC error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_REST_Response
	 */
	private function error_response( mixed $id, int $code, string $message, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => [
					'code'    => $code,
					'message' => $message,
				],
			],
			$status
		);
	}

	/**
	 * Build a tool-level error response (isError: true in content).
	 *
	 * @param mixed  $id      JSON-RPC request ID.
	 * @param string $message Error message.
	 * @return \WP_REST_Response
	 */
	private function tool_error_response( mixed $id, string $message ): \WP_REST_Response {
		return $this->success_response(
			$id,
			[
				'content' => [
					[
						'type' => 'text',
						'text' => $message,
					],
				],
				'isError' => true,
			]
		);
	}

	/**
	 * Convert an internal ability name to an MCP-safe tool name.
	 *
	 * MCP spec requires tool names to match ^[a-zA-Z0-9_-]{1,64}$.
	 * Internal names like "my-site-hand/list-posts" become "mysitehand_list-posts".
	 *
	 * @param string $name Internal ability name.
	 * @return string MCP-compliant tool name.
	 */
	private static function to_mcp_tool_name( string $name ): string {
		return str_replace( '/', '_', $name );
	}

	/**
	 * Convert an MCP tool name back to the internal ability name.
	 *
	 * Reverses to_mcp_tool_name() by replacing the first underscore with a slash.
	 * e.g. "mysitehand_list-posts" becomes "my-site-hand/list-posts".
	 *
	 * @param string $name MCP tool name from client request.
	 * @return string Internal ability name.
	 */
	private static function from_mcp_tool_name( string $name ): string {
		$pos = strpos( $name, '_' );
		if ( false !== $pos ) {
			return substr_replace( $name, '/', $pos, 1 );
		}
		return $name;
	}
}




