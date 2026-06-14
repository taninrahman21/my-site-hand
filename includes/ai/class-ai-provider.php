<?php
/**
 * AI provider gateway — talks to OpenAI and Gemini.
 *
 * @package MySiteHand
 */

namespace MySiteHand\AI;

defined('ABSPATH') || exit;

/**
 * AI_Provider class.
 *
 * Handles all communication with external AI APIs (OpenAI and Google Gemini).
 * Reads provider configuration from WordPress options, builds provider-specific
 * request payloads, and normalizes responses into a common shape understood by
 * the Chat_Controller. API keys are stored encrypted and never logged.
 */
class AI_Provider
{

	/**
	 * Selected provider slug ('openai' or 'gemini').
	 *
	 * @var string
	 */
	private string $provider;

	/**
	 * Decrypted API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Selected model identifier.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Map of provider-safe tool names to their real ability names.
	 *
	 * Ability names contain characters (e.g. "/") that OpenAI and Gemini reject
	 * in function names, so they are sanitized before sending and translated
	 * back when the model selects one.
	 *
	 * @var array<string, string>
	 */
	private array $tool_name_map = [];

	/**
	 * My Site Hand proxy endpoint URL.
	 *
	 * @var string
	 */
	public const PROXY_URL = 'https://msh-proxy.builtbytanin.workers.dev';

	/**
	 * Shared secret that authenticates requests to the proxy.
	 *
	 * @var string
	 */
	public const PROXY_SECRET = 'msh_sk_builtbytanin_2026';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 30;

	/**
	 * System prompt sent with every request.
	 *
	 * @var string
	 */
	private const SYSTEM_PROMPT = "You are an AI assistant built into the My Site Hand WordPress plugin. You help users manage their WordPress website using natural language.\n\nYou have a large set of tools (functions) that can READ and WRITE data on this WordPress site — including creating, updating, and deleting posts, pages, media, users, SEO metadata, and WooCommerce data. The exact tools available to you are provided with every request; treat that tool list as the single source of truth for what you can do.\n\nWhen the user asks you to do something, find the most appropriate tool and call it. Do NOT claim you are unable to perform an action that one of your tools supports — if a relevant tool exists, use it instead of telling the user to do it themselves. For example, if the user asks to create a post and a create-post tool exists, call it.\n\nAfter a tool runs, respond to the user in a friendly, clear, and concise way — summarize what was done or what was found.\n\nRules:\n- Prefer taking action with a tool over describing how the user could do it manually.\n- Only ask a clarifying question when a REQUIRED parameter is genuinely missing and cannot be reasonably inferred; otherwise proceed with sensible defaults.\n- Never invent tool results. Only report what the tools actually return.\n- Keep responses short and helpful. No unnecessary preamble.\n- If a tool returns an error, explain it clearly and suggest what to try next.";

	/**
	 * Whether the model is set to "auto" (pick the best available).
	 *
	 * @var bool
	 */
	private bool $auto_mode = false;

	/**
	 * Per-provider model priority, best first. In auto mode the provider tries
	 * these in order and falls back to the next when one is unavailable.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const MODEL_PRIORITY = [
		'openai' => ['gpt-4o', 'gpt-4.1', 'gpt-4o-mini', 'gpt-4.1-mini'],
		'gemini' => ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash'],
	];

	/**
	 * Constructor — loads provider settings from options.
	 */
	public function __construct()
	{
		$this->provider = (string) get_option('mysitehand_ai_provider', '');
		$this->api_key = mysitehand_decrypt_api_key((string) get_option('mysitehand_ai_api_key', ''));
		$this->model = (string) get_option('mysitehand_ai_model', '');
		$this->auto_mode = ('auto' === $this->model || '' === $this->model);
	}

	/**
	 * Send a chat completion request to the configured provider.
	 *
	 * @param array<int, array<string, mixed>> $messages Conversation messages, each
	 *                                                    ['role' => 'user'|'assistant'|'tool', 'content' => string, ...].
	 * @param array<int, array<string, mixed>> $tools    Tool schemas from
	 *                                                    Abilities_Registry::get_all_as_mcp_tool_schemas().
	 * @return array{type: string, content?: string, tool_calls?: array<int, array{id: string, name: string, args: array<string, mixed>}>}
	 *               Either ['type' => 'text', 'content' => string] or
	 *               ['type' => 'tool_call', 'tool_calls' => [['id', 'name', 'args'], ...]].
	 *
	 * @throws \RuntimeException When the request cannot be completed.
	 */
	public function call(array $messages, array $tools): array
	{
		if (empty($messages)) {
			throw new \RuntimeException(esc_html__('No messages were provided to the AI provider.', 'my-site-hand'));
		}

		// Use proxy when user has no own key set.
		if (self::is_using_proxy()) {
			return $this->call_via_proxy($messages, $tools);
		}

		// User has their own key — call provider directly.
		if ('' === $this->api_key || '' === $this->model || '' === $this->provider) {
			throw new \RuntimeException(
				esc_html__('The AI provider is not fully configured.', 'my-site-hand')
			);
		}

		return match ( $this->provider ) {
			'openai' => $this->call_with_fallback( 'openai', $messages, $tools ),
			'gemini' => $this->call_with_fallback( 'gemini', $messages, $tools ),
			default  => throw new \RuntimeException(
				sprintf(
					/* translators: %s: provider slug */
					esc_html__( 'Unknown AI provider "%s".', 'my-site-hand' ),
					esc_html( $this->provider )
				)
			),
		};
	}

	/**
	 * Resolve the ordered list of model candidates to try.
	 *
	 * In auto mode this is the priority list with the last known-working model
	 * moved to the front. Otherwise it is just the single configured model.
	 *
	 * @return array<int, string>
	 */
	private function get_model_candidates(): array
	{
		if (!$this->auto_mode) {
			return [$this->model];
		}

		$priority = self::MODEL_PRIORITY[$this->provider] ?? [];

		$remembered = (string) get_transient('mysitehand_ai_auto_model_' . $this->provider);
		if ('' !== $remembered && in_array($remembered, $priority, true)) {
			// Put the remembered model first.
			$priority = array_values(array_unique(array_merge([$remembered], $priority)));
		}

		return !empty($priority) ? $priority : [''];
	}

	/**
	 * Cache the model that successfully handled a request (auto mode).
	 *
	 * @param string $model Model identifier.
	 * @return void
	 */
	private function remember_working_model(string $model): void
	{
		if ('' === $model) {
			return;
		}
		set_transient('mysitehand_ai_auto_model_' . $this->provider, $model, DAY_IN_SECONDS);
	}

	/**
	 * Heuristic: does this error message indicate an authentication failure?
	 *
	 * Auth failures affect every model, so auto mode must not retry on them.
	 *
	 * @param string $message Error message.
	 * @return bool
	 */
	private function is_auth_error(string $message): bool
	{
		$needles = ['api key', 'api_key', 'unauthorized', 'unauthenticated', 'invalid authentication', 'incorrect api', '401'];
		$lower = strtolower($message);
		foreach ($needles as $needle) {
			if (str_contains($lower, $needle)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Try each model candidate in order, falling back on non-auth errors.
	 *
	 * In auto mode `get_model_candidates()` returns several models ranked best-first.
	 * This method iterates through them and returns the first successful response.
	 * On auth errors it throws immediately — retrying won't help when the key is wrong.
	 * On success it caches the working model via `remember_working_model()`.
	 *
	 * @param string                           $provider  'openai' or 'gemini'.
	 * @param array<int, array<string, mixed>> $messages  Conversation messages.
	 * @param array<int, array<string, mixed>> $tools     MCP tool schemas.
	 * @return array{type: string, content?: string, tool_calls?: array<int, array<string, mixed>>}
	 *
	 * @throws \RuntimeException When all candidates fail or an auth error is detected.
	 */
	private function call_with_fallback( string $provider, array $messages, array $tools ): array {
		$candidates = $this->get_model_candidates();
		$last_error = null;

		foreach ( $candidates as $model ) {
			try {
				$result = 'openai' === $provider
					? $this->call_openai( $messages, $tools, $model )
					: $this->call_gemini( $messages, $tools, $model );

				// Cache the model that worked so next request starts here.
				$this->remember_working_model( $model );

				return $result;
			} catch ( \RuntimeException $e ) {
				// Auth errors affect every model — no point retrying.
				if ( $this->is_auth_error( $e->getMessage() ) ) {
					throw $e;
				}
				$last_error = $e;
			}
		}

		throw $last_error ?? new \RuntimeException(
			__( 'All AI models are currently unavailable. Please try again later.', 'my-site-hand' )
		);
	}

	// -------------------------------------------------------------------------
	// OpenAI
	// -------------------------------------------------------------------------

	/**
	 * Perform an OpenAI chat completion request.
	 *
	 * @param array<int, array<string, mixed>> $messages Conversation messages.
	 * @param array<int, array<string, mixed>> $tools    MCP tool schemas.
	 * @return array{type: string, content?: string, tool_name?: string, tool_args?: array<string, mixed>}
	 *
	 * @throws \RuntimeException On transport or API error.
	 */
	private function call_openai(array $messages, array $tools, string $model): array
	{
		$payload = [
			'model' => $model,
			'messages' => $this->build_openai_messages($messages),
		];

		$mapped_tools = $this->map_tools_openai($tools);
		if (!empty($mapped_tools)) {
			$payload['tools'] = $mapped_tools;
			$payload['tool_choice'] = 'auto';
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => self::TIMEOUT,
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				],
				'body' => wp_json_encode($payload),
			]
		);

		$body = $this->parse_response($response);

		$message = $body['choices'][0]['message'] ?? [];

		// One or more tool calls requested?
		if (!empty($message['tool_calls']) && is_array($message['tool_calls'])) {
			$tool_calls = [];

			foreach ($message['tool_calls'] as $i => $call) {
				if (empty($call['function']['name'])) {
					continue;
				}

				$args = [];
				if (isset($call['function']['arguments']) && is_string($call['function']['arguments'])) {
					$decoded = json_decode($call['function']['arguments'], true);
					$args = is_array($decoded) ? $decoded : [];
				}

				$tool_calls[] = [
					'id' => (string) ($call['id'] ?? ('call_' . $i)),
					'name' => $this->real_tool_name((string) $call['function']['name']),
					'args' => $args,
				];
			}

			if (!empty($tool_calls)) {
				return [
					'type' => 'tool_call',
					'tool_calls' => $tool_calls,
				];
			}
		}

		return [
			'type' => 'text',
			'content' => (string) ($message['content'] ?? ''),
		];
	}

	/**
	 * Build the OpenAI messages array, prepending the system prompt.
	 *
	 * @param array<int, array<string, mixed>> $messages Internal conversation messages.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_openai_messages(array $messages): array
	{
		$out = [
			[
				'role' => 'system',
				'content' => self::SYSTEM_PROMPT,
			],
		];

		foreach ($messages as $msg) {
			$role = (string) ($msg['role'] ?? 'user');

			// Assistant turn that requested one or more tool calls.
			if ('assistant' === $role && !empty($msg['tool_calls'])) {
				$calls = [];
				foreach ($msg['tool_calls'] as $call) {
					$calls[] = [
						'id' => (string) ($call['id'] ?? ''),
						'type' => 'function',
						'function' => [
							'name' => $this->safe_tool_name((string) ($call['name'] ?? '')),
							'arguments' => wp_json_encode($call['args'] ?? []),
						],
					];
				}
				$out[] = [
					'role' => 'assistant',
					'content' => null,
					'tool_calls' => $calls,
				];
				continue;
			}

			// Native tool result turn (from the agentic loop).
			if ('tool' === $role && isset($msg['tool_call_id'])) {
				$out[] = [
					'role' => 'tool',
					'tool_call_id' => (string) $msg['tool_call_id'],
					'content' => (string) ($msg['content'] ?? ''),
				];
				continue;
			}

			$content = (string) ($msg['content'] ?? '');

			// Legacy / cross-turn tool rows loaded from the DB: fold into context.
			if ('tool' === $role) {
				$tool_name = (string) ($msg['tool_name'] ?? 'tool');
				$out[] = [
					'role' => 'assistant',
					'content' => sprintf('[Tool %1$s result] %2$s', $tool_name, $content),
				];
				continue;
			}

			$out[] = [
				'role' => in_array($role, ['user', 'assistant'], true) ? $role : 'user',
				'content' => $content,
			];
		}

		return $out;
	}

	/**
	 * Map MCP tool schemas to the OpenAI tools format.
	 *
	 * @param array<int, array<string, mixed>> $tools MCP tool schemas.
	 * @return array<int, array<string, mixed>>
	 */
	private function map_tools_openai(array $tools): array
	{
		$mapped = [];

		foreach ($tools as $tool) {
			if (empty($tool['name'])) {
				continue;
			}

			$mapped[] = [
				'type' => 'function',
				'function' => [
					'name' => $this->safe_tool_name((string) $tool['name']),
					'description' => (string) ($tool['description'] ?? ''),
					'parameters' => $tool['inputSchema'] ?? ['type' => 'object', 'properties' => new \stdClass()],
				],
			];
		}

		return $mapped;
	}

	// -------------------------------------------------------------------------
	// Gemini
	// -------------------------------------------------------------------------

	/**
	 * Perform a Google Gemini generateContent request.
	 *
	 * @param array<int, array<string, mixed>> $messages Conversation messages.
	 * @param array<int, array<string, mixed>> $tools    MCP tool schemas.
	 * @return array{type: string, content?: string, tool_name?: string, tool_args?: array<string, mixed>}
	 *
	 * @throws \RuntimeException On transport or API error.
	 */
	private function call_gemini(array $messages, array $tools, string $model): array
	{
		$payload = [
			'system_instruction' => [
				'parts' => [
					['text' => self::SYSTEM_PROMPT],
				],
			],
			'contents' => $this->build_gemini_contents($messages),
		];

		$mapped_tools = $this->map_tools_gemini($tools);
		if (!empty($mapped_tools)) {
			$payload['tools'] = $mapped_tools;
			$payload['tool_config'] = [
				'function_calling_config' => ['mode' => 'AUTO'],
			];
		}

		$endpoint = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode($model),
			rawurlencode($this->api_key)
		);

		$response = wp_remote_post(
			$endpoint,
			[
				'timeout' => self::TIMEOUT,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode($payload),
			]
		);

		$body = $this->parse_response($response);

		$parts = $body['candidates'][0]['content']['parts'] ?? [];

		// Collect any functionCall parts (Gemini may emit several).
		$tool_calls = [];
		foreach ($parts as $i => $part) {
			if (!empty($part['functionCall']['name'])) {
				$fn = $part['functionCall'];
				$tool_calls[] = [
					'id' => 'call_' . $i,
					'name' => $this->real_tool_name((string) $fn['name']),
					'args' => isset($fn['args']) && is_array($fn['args']) ? $fn['args'] : [],
				];
			}
		}

		if (!empty($tool_calls)) {
			return [
				'type' => 'tool_call',
				'tool_calls' => $tool_calls,
			];
		}

		// Otherwise concatenate text parts.
		$text = '';
		foreach ($parts as $part) {
			if (isset($part['text'])) {
				$text .= (string) $part['text'];
			}
		}

		return [
			'type' => 'text',
			'content' => $text,
		];
	}

	/**
	 * Send a chat request through the built-in My Site Hand proxy.
	 *
	 * The proxy holds the Gemini API key. This plugin never touches the key.
	 * The proxy URL and secret are hard-coded as private class constants.
	 *
	 * @param array<int, array<string, mixed>> $messages Internal conversation messages.
	 * @param array<int, array<string, mixed>> $tools    MCP tool schemas.
	 * @return array{type: string, content?: string, tool_name?: string, tool_args?: array<string, mixed>}
	 *
	 * @throws \RuntimeException On network error, proxy error, or daily limit (code 429).
	 */
	private function call_via_proxy( array $messages, array $tools ): array {
		$payload = [
			'system'   => self::SYSTEM_PROMPT,
			'messages' => $this->build_gemini_contents( $messages ),
		];

		$mapped_tools = $this->map_tools_gemini( $tools );
		if ( ! empty( $mapped_tools ) ) {
			$payload['tools'] = $mapped_tools;
		}

		$response = wp_remote_post(
			self::PROXY_URL . '/v1/chat',
			[
				'timeout' => self::TIMEOUT,
				'headers' => [
					'Content-Type' => 'application/json',
					'X-MSH-Secret' => self::PROXY_SECRET,
					'X-MSH-Site'   => home_url(),
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error detail */
					esc_html__( 'Could not reach the AI service: %s', 'my-site-hand' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( 429 === $code ) {
			$decoded = json_decode( $body, true );
			$message = is_array( $decoded ) && ! empty( $decoded['message'] )
				? (string) $decoded['message']
				: esc_html__( 'You have reached the free daily limit. Add your own API key in Settings → AI Assistant for unlimited messages.', 'my-site-hand' );
			throw new \RuntimeException( esc_html( $message ), 429 );
		}

		if ( 200 !== $code ) {
			$decoded = json_decode( $body, true );
			$message = is_array( $decoded ) && ! empty( $decoded['message'] )
				? (string) $decoded['message']
				: $this->extract_error_message( $body );
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: HTTP status, 2: error detail */
					esc_html__( 'AI service error %1$d: %2$s', 'my-site-hand' ),
					(int) $code,
					esc_html( $message )
				)
			);
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException(
				esc_html__( 'AI service returned an unreadable response.', 'my-site-hand' )
			);
		}

		$type = (string) ( $decoded['type'] ?? '' );

		if ( 'tool_call' === $type ) {
			// Normalize to the multi-tool format expected by Chat_Controller.
			// real_tool_name() converts the provider-safe name (e.g. my_site_hand_list_users)
			// back to the real ability name (e.g. my-site-hand/list-users) the registry expects.
			$safe_name = (string) ( $decoded['tool_name'] ?? '' );
			$tool_args = isset( $decoded['tool_args'] ) && is_array( $decoded['tool_args'] )
				? $decoded['tool_args']
				: [];

			return [
				'type'       => 'tool_call',
				'tool_calls' => [
					[
						'id'   => 'proxy_call_0',
						'name' => $this->real_tool_name( $safe_name ),
						'args' => $tool_args,
					],
				],
			];
		}

		return [
			'type'    => 'text',
			'content' => (string) ( $decoded['content'] ?? '' ),
		];
	}

	/**
	 * Build the Gemini contents array.
	 *
	 * @param array<int, array<string, mixed>> $messages Internal conversation messages.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_gemini_contents(array $messages): array
	{
		$contents = [];

		foreach ($messages as $msg) {
			$role = (string) ($msg['role'] ?? 'user');

			// Assistant turn that requested one or more tool calls.
			if ('assistant' === $role && !empty($msg['tool_calls'])) {
				$parts = [];
				foreach ($msg['tool_calls'] as $call) {
					$parts[] = [
						'functionCall' => [
							'name' => $this->safe_tool_name((string) ($call['name'] ?? '')),
							'args' => (object) ($call['args'] ?? []),
						],
					];
				}
				$contents[] = [
					'role' => 'model',
					'parts' => $parts,
				];
				continue;
			}

			// Native tool result turn — Gemini expects a functionResponse in a user turn.
			if ('tool' === $role && isset($msg['tool_call_id'])) {
				$contents[] = [
					'role' => 'user',
					'parts' => [
						[
							'functionResponse' => [
								'name' => $this->safe_tool_name((string) ($msg['tool_name'] ?? '')),
								'response' => ['result' => (string) ($msg['content'] ?? '')],
							],
						],
					],
				];
				continue;
			}

			$content = (string) ($msg['content'] ?? '');

			// Legacy / cross-turn tool rows loaded from the DB: fold into context.
			if ('tool' === $role) {
				$tool_name = (string) ($msg['tool_name'] ?? 'tool');
				$contents[] = [
					'role' => 'model',
					'parts' => [
						['text' => sprintf('[Tool %1$s result] %2$s', $tool_name, $content)],
					],
				];
				continue;
			}

			// Gemini uses 'model' for assistant turns and 'user' for the user.
			$gemini_role = ('assistant' === $role) ? 'model' : 'user';

			$contents[] = [
				'role' => $gemini_role,
				'parts' => [
					['text' => $content],
				],
			];
		}

		return $contents;
	}

	/**
	 * Map MCP tool schemas to the Gemini tools format.
	 *
	 * @param array<int, array<string, mixed>> $tools MCP tool schemas.
	 * @return array<int, array<string, mixed>>
	 */
	private function map_tools_gemini(array $tools): array
	{
		$declarations = [];

		foreach ($tools as $tool) {
			if (empty($tool['name'])) {
				continue;
			}

			$declarations[] = [
				'name' => $this->safe_tool_name((string) $tool['name']),
				'description' => (string) ($tool['description'] ?? ''),
				'parameters' => $this->sanitize_gemini_schema($tool['inputSchema'] ?? []),
			];
		}

		if (empty($declarations)) {
			return [];
		}

		return [
			['function_declarations' => $declarations],
		];
	}

	/**
	 * Normalize an MCP input schema for Gemini's parameter requirements.
	 *
	 * Gemini rejects an empty object for `properties`; it expects either an
	 * object with properties or no parameters block at all.
	 *
	 * @param array<string, mixed> $schema MCP inputSchema.
	 * @return array<string, mixed>
	 */
	private function sanitize_gemini_schema(array $schema): array
	{
		$properties = $schema['properties'] ?? null;

		// An empty stdClass / empty array means "no parameters".
		$has_properties = !empty($properties) && (is_array($properties) ? count($properties) > 0 : (array) $properties);

		if (!$has_properties) {
			return [
				'type' => 'object',
				'properties' => new \stdClass(),
			];
		}

		$out = [
			'type' => 'object',
			'properties' => $properties,
		];

		if (!empty($schema['required'])) {
			$out['required'] = array_values((array) $schema['required']);
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Shared helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert an ability name into a provider-safe function name.
	 *
	 * OpenAI and Gemini only permit [a-zA-Z0-9_.:-] (and must start with a
	 * letter/underscore). Ability names such as "my-site-hand/create-post"
	 * contain "/", so disallowed characters are replaced with "_". The mapping
	 * back to the real ability name is recorded for later translation.
	 *
	 * @param string $name Real ability name.
	 * @return string Provider-safe function name.
	 */
	private function safe_tool_name(string $name): string
	{
		$safe = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $name);
		$safe = (string) $safe;

		// Must start with a letter or underscore.
		if ('' !== $safe && !preg_match('/^[a-zA-Z_]/', $safe)) {
			$safe = '_' . $safe;
		}

		// Enforce the 128-character maximum.
		if (strlen($safe) > 128) {
			$safe = substr($safe, 0, 128);
		}

		$this->tool_name_map[$safe] = $name;

		return $safe;
	}

	/**
	 * Translate a provider-safe function name back to the real ability name.
	 *
	 * @param string $safe Provider-safe function name returned by the model.
	 * @return string Real ability name (or the input unchanged if unknown).
	 */
	private function real_tool_name(string $safe): string
	{
		return $this->tool_name_map[$safe] ?? $safe;
	}

	/**
	 * Validate the HTTP transport response and decode the JSON body.
	 *
	 * @param array|\WP_Error $response Result of wp_remote_post().
	 * @return array<string, mixed> Decoded response body.
	 *
	 * @throws \RuntimeException On transport error or non-200 status.
	 */
	private function parse_response($response): array
	{
		if (is_wp_error($response)) {
			throw new \RuntimeException(esc_html($response->get_error_message()));
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);

		if (200 !== $code) {
			$message = $this->extract_error_message($body);
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: HTTP status code, 2: error detail */
					esc_html__('AI provider returned HTTP %1$d: %2$s', 'my-site-hand'),
					(int) $code,
					esc_html($message)
				)
			);
		}

		$decoded = json_decode($body, true);

		if (!is_array($decoded)) {
			throw new \RuntimeException(esc_html__('The AI provider returned an unreadable response.', 'my-site-hand'));
		}

		return $decoded;
	}

	/**
	 * Extract a human-readable error message from a provider error body.
	 *
	 * @param string $body Raw response body.
	 * @return string
	 */
	private function extract_error_message(string $body): string
	{
		$decoded = json_decode($body, true);

		if (is_array($decoded)) {
			if (isset($decoded['error']['message'])) {
				return (string) $decoded['error']['message'];
			}
			if (isset($decoded['error']) && is_string($decoded['error'])) {
				return $decoded['error'];
			}
		}

		// Truncate raw body so we never dump huge payloads.
		return '' !== $body ? substr($body, 0, 300) : __('Unknown error.', 'my-site-hand');
	}

	/**
	 * Test the configured provider, key, and model with a minimal request.
	 *
	 * @return bool|\WP_Error True on success, WP_Error with a message on failure.
	 */
	public function test_connection(): bool|\WP_Error
	{
		// When using proxy — test by hitting the usage endpoint.
		if (self::is_using_proxy()) {
			$response = wp_remote_get(
				self::PROXY_URL . '/v1/usage',
				[
					'timeout' => 10,
					'headers' => [
						'X-MSH-Secret' => self::PROXY_SECRET,
						'X-MSH-Site' => home_url(),
					],
				]
			);

			if (is_wp_error($response)) {
				return new \WP_Error(
					'proxy_unreachable',
					$response->get_error_message()
				);
			}

			if (200 === (int) wp_remote_retrieve_response_code($response)) {
				return true;
			}

			return new \WP_Error(
				'proxy_error',
				__('Could not connect to the AI service. Please try again.', 'my-site-hand')
			);
		}

		if ('' === $this->provider || '' === $this->api_key) {
			return new \WP_Error(
				'not_configured',
				__('Please choose a provider and enter an API key before testing.', 'my-site-hand')
			);
		}

		try {
			$result = $this->call([['role' => 'user', 'content' => 'Say hi']], []);
		} catch (\Throwable $e) {
			return new \WP_Error('connection_failed', $e->getMessage());
		}

		if ('text' === ($result['type'] ?? '') || 'tool_call' === ($result['type'] ?? '')) {
			return true;
		}

		return new \WP_Error('connection_failed', __('The AI provider did not return a valid response.', 'my-site-hand'));
	}

	/**
	 * Get the hardcoded list of supported models, keyed by provider.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function get_available_models(): array
	{
		return [
			'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini'],
			'gemini' => ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash'],
		];
	}

	/**
	 * Whether the AI provider is fully configured.
	 *
	 * The model may be left empty or set to "auto" — the provider resolves the
	 * best available model at call time — so only the provider and API key are
	 * strictly required here.
	 *
	 * @return bool True if provider and api_key are set.
	 */
	public static function is_using_proxy(): bool
	{
		$encrypted = (string) get_option('mysitehand_ai_api_key', '');
		if ('' === $encrypted) {
			return true;
		}
		$decrypted = mysitehand_decrypt_api_key($encrypted);
		return '' === $decrypted;
	}

	/**
	 * Whether the AI is available at all (proxy always makes it available).
	 *
	 * @return bool Always true because proxy is always configured.
	 */
	public static function is_configured(): bool
	{
		// Proxy is always available — no user setup needed.
		// If user has own key fully set, also configured.
		$provider = (string) get_option('mysitehand_ai_provider', '');
		$api_key = (string) get_option('mysitehand_ai_api_key', '');
		$model = (string) get_option('mysitehand_ai_model', '');

		if ('' !== $provider && '' !== $api_key && '' !== $model) {
			$decrypted = mysitehand_decrypt_api_key($api_key);
			if ('' !== $decrypted) {
				return true; // User has own key.
			}
		}

		return true; // Proxy is always available.
	}
}
