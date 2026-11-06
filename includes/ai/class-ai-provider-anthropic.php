<?php
/**
 * Anthropic (Claude) provider.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * AI Provider Anthropic class.
 *
 * Talks to the Messages API with wp_remote_post(). The official SDK is not
 * used because this plugin ships no Composer runtime dependencies.
 */
class AI_Provider_Anthropic extends AI_Provider_Base {

	/**
	 * Messages endpoint.
	 *
	 * @var string
	 */
	private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * Models endpoint, used to test a key for free.
	 *
	 * @var string
	 */
	private const MODELS_ENDPOINT = 'https://api.anthropic.com/v1/models';

	/**
	 * API version header value.
	 *
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * USD per million tokens, [ input, output ].
	 *
	 * NEEDS MANUAL REVIEW when Anthropic changes prices. Last checked
	 * 2026-10-11 against the published first-party API rates.
	 *
	 * @var array<string, array{0: float, 1: float}>
	 */
	public const PRICING = [
		'claude-haiku-5-5'  => [ 0.10, 0.50 ],
		'claude-sonnet-5-5' => [ 2.00, 10.00 ],
	];

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'anthropic';
	}

	/**
	 * Provider name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Anthropic (Claude)', 'my-site-hand' );
	}

	/**
	 * Selectable models, cheapest first.
	 *
	 * Writing a sentence of alt text is high-volume, low-complexity work, so
	 * the cheapest model is the default and the expensive tiers are left out.
	 *
	 * @return array<string, string>
	 */
	public function get_models(): array {
		return [
			'claude-haiku-5-5'  => __( 'Claude Haiku 5.5 (recommended, lowest cost)', 'my-site-hand' ),
			'claude-sonnet-5-5' => __( 'Claude Sonnet 5.5 (higher quality, about 20x the cost)', 'my-site-hand' ),
		];
	}

	/**
	 * Run one completion.
	 *
	 * Temperature is not sent: current Claude models reject non-default
	 * sampling parameters with a 400. Effort is pinned low because this is
	 * short, factual writing where extra reasoning only adds cost.
	 *
	 * @param array<string, mixed> $args See AI_Provider_Base::complete().
	 * @return array{text: string, input_tokens: int, output_tokens: int, model: string}|\WP_Error
	 */
	public function complete( array $args ): array|\WP_Error {
		$api_key = (string) ( $args['api_key'] ?? '' );
		$model   = (string) ( $args['model'] ?? $this->get_default_model() );

		$body = [
			'model'         => $model,
			'max_tokens'    => max( 1, (int) ( $args['max_tokens'] ?? 1024 ) ),
			'messages'      => array_values( (array) ( $args['messages'] ?? [] ) ),
			'output_config' => [ 'effort' => 'low' ],
		];

		if ( ! empty( $args['system'] ) ) {
			$body['system'] = (string) $args['system'];
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout' => self::TIMEOUT,
				'headers' => [
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
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

		if ( ! is_array( $data ) || ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
			return $this->bad_response_error();
		}

		if ( 'refusal' === ( $data['stop_reason'] ?? '' ) ) {
			return new \WP_Error(
				'ai_refused',
				__( 'The model declined to write this batch. Try again, or fill these items in by hand.', 'my-site-hand' ),
				[ 'status' => 422 ]
			);
		}

		// Content is a list of typed blocks. Only text blocks are the answer;
		// thinking blocks, when present, are not.
		$text = '';

		foreach ( $data['content'] as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) && isset( $block['text'] ) ) {
				$text .= (string) $block['text'];
			}
		}

		return [
			'text'          => $text,
			'input_tokens'  => (int) ( $data['usage']['input_tokens'] ?? 0 ),
			'output_tokens' => (int) ( $data['usage']['output_tokens'] ?? 0 ),
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
				'headers' => [
					'x-api-key'         => $key,
					'anthropic-version' => self::API_VERSION,
				],
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
