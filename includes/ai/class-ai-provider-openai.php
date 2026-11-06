<?php
/**
 * OpenAI provider.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * AI Provider OpenAI class.
 *
 * Talks to Chat Completions with wp_remote_post() and normalizes the response
 * to the same shape the Anthropic provider returns.
 */
class AI_Provider_OpenAI extends AI_Provider_Base {

	/**
	 * Chat Completions endpoint.
	 *
	 * @var string
	 */
	private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Models endpoint, used to test a key for free.
	 *
	 * @var string
	 */
	private const MODELS_ENDPOINT = 'https://api.openai.com/v1/models';

	/**
	 * USD per million tokens, [ input, output ].
	 *
	 * NEEDS MANUAL REVIEW when OpenAI changes prices or retires a model.
	 * Last checked 2026-10-11.
	 *
	 * @var array<string, array{0: float, 1: float}>
	 */
	public const PRICING = [
		'gpt-4o-mini'  => [ 0.15, 0.60 ],
		'gpt-4.1-mini' => [ 0.40, 1.60 ],
	];

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'openai';
	}

	/**
	 * Provider name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'OpenAI', 'my-site-hand' );
	}

	/**
	 * Selectable models, cheapest first.
	 *
	 * @return array<string, string>
	 */
	public function get_models(): array {
		return [
			'gpt-4o-mini'  => __( 'GPT-4o mini (recommended, lowest cost)', 'my-site-hand' ),
			'gpt-4.1-mini' => __( 'GPT-4.1 mini (higher quality, about 2.5x the cost)', 'my-site-hand' ),
		];
	}

	/**
	 * Run one completion.
	 *
	 * @param array<string, mixed> $args See AI_Provider_Base::complete().
	 * @return array{text: string, input_tokens: int, output_tokens: int, model: string}|\WP_Error
	 */
	public function complete( array $args ): array|\WP_Error {
		$api_key  = (string) ( $args['api_key'] ?? '' );
		$model    = (string) ( $args['model'] ?? $this->get_default_model() );
		$messages = [];

		// Chat Completions carries the system prompt as the first message.
		if ( ! empty( $args['system'] ) ) {
			$messages[] = [
				'role'    => 'system',
				'content' => (string) $args['system'],
			];
		}

		foreach ( (array) ( $args['messages'] ?? [] ) as $message ) {
			$messages[] = $message;
		}

		$body = [
			'model'                 => $model,
			'messages'              => $messages,
			'max_completion_tokens' => max( 1, (int) ( $args['max_tokens'] ?? 1024 ) ),
		];

		if ( isset( $args['temperature'] ) ) {
			$body['temperature'] = (float) $args['temperature'];
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout' => self::TIMEOUT,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $this->network_error();
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 ) {
			return $this->http_error( $status, $this->vendor_message( $raw ), $api_key );
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || ! isset( $data['choices'][0]['message'] ) ) {
			return $this->bad_response_error();
		}

		$message = $data['choices'][0]['message'];

		if ( ! empty( $message['refusal'] ) ) {
			return new \WP_Error(
				'ai_refused',
				__( 'The model declined to write this batch. Try again, or fill these items in by hand.', 'my-site-hand' ),
				[ 'status' => 422 ]
			);
		}

		return [
			'text'          => is_string( $message['content'] ?? null ) ? $message['content'] : '',
			'input_tokens'  => (int) ( $data['usage']['prompt_tokens'] ?? 0 ),
			'output_tokens' => (int) ( $data['usage']['completion_tokens'] ?? 0 ),
			'model'         => (string) ( $data['model'] ?? $model ),
		];
	}

	/**
	 * Test a key by listing models, which costs nothing.
	 *
	 * @param string $key API key.
	 * @return bool|\WP_Error
	 */
	public function validate_key( string $key ): bool|\WP_Error {
		$response = wp_remote_get(
			self::MODELS_ENDPOINT,
			[
				'timeout' => 15,
				'headers' => [ 'Authorization' => 'Bearer ' . $key ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $this->network_error();
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status >= 200 && $status < 300 ) {
			return true;
		}

		return $this->http_error( $status, $this->vendor_message( (string) wp_remote_retrieve_body( $response ) ), $key );
	}
}
