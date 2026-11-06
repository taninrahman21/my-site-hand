<?php
/**
 * Abstract base class for AI text-generation providers.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * AI Provider Base abstract class.
 *
 * One provider per vendor. Every provider normalizes its vendor's response to
 * the same shape, so nothing above this layer ever needs to know which one is
 * active.
 *
 * THE API KEY IS A THIRD-PARTY CREDENTIAL. It is passed in per call, sent only
 * to the provider's own endpoint, and must never reach a WP_Error message, an
 * error data payload, a log line or any HTML. Providers echo fragments of a
 * rejected key back in their error bodies (OpenAI does), so vendor error text
 * goes through redact() before it is used anywhere.
 */
abstract class AI_Provider_Base {

	/**
	 * HTTP timeout for a single request, in seconds.
	 *
	 * Deliberately under the 30 second limit most shared hosts enforce, so a
	 * slow provider produces a clean error instead of a killed PHP process.
	 *
	 * @var int
	 */
	public const TIMEOUT = 30;

	/**
	 * Per-model pricing in USD per million tokens: [ model => [ input, output ] ].
	 *
	 * NEEDS MANUAL REVIEW whenever a provider changes its prices. It is used
	 * for the cost readout and for enforcing the daily spend cap, and is never
	 * fetched over the network on purpose: a pricing lookup is one more request
	 * to a third party on every page load, and one more thing that can fail.
	 *
	 * @var array<string, array{0: float, 1: float}>
	 */
	public const PRICING = [];

	/**
	 * Stable provider id, e.g. 'anthropic'. Stored in an option; never change.
	 *
	 * @return string
	 */
	abstract public function get_id(): string;

	/**
	 * Human-readable provider name.
	 *
	 * @return string
	 */
	abstract public function get_label(): string;

	/**
	 * Selectable models, cheapest first.
	 *
	 * @return array<string, string> [ model id => label ]
	 */
	abstract public function get_models(): array;

	/**
	 * Run one completion.
	 *
	 * No retries happen in here. Retry policy belongs to the caller, which
	 * knows whether a retry is worth the money.
	 *
	 * @param array<string, mixed> $args {
	 *     Completion request.
	 *
	 *     @type string $system      System prompt.
	 *     @type array  $messages    [ [ 'role' => 'user'|'assistant', 'content' => string ], ... ].
	 *     @type int    $max_tokens  Output ceiling.
	 *     @type float  $temperature Sampling temperature, where the model accepts one.
	 *     @type string $model       Model id.
	 *     @type string $api_key     Credential. Never logged, never returned.
	 * }
	 * @return array{text: string, input_tokens: int, output_tokens: int, model: string}|\WP_Error
	 */
	abstract public function complete( array $args ): array|\WP_Error;

	/**
	 * Check that a key is accepted, without spending anything.
	 *
	 * @param string $key API key.
	 * @return bool|\WP_Error True when the key works.
	 */
	abstract public function validate_key( string $key ): bool|\WP_Error;

	/**
	 * The model used when none has been chosen: the first, and cheapest.
	 *
	 * @return string
	 */
	public function get_default_model(): string {
		$models = array_keys( $this->get_models() );

		return (string) ( $models[0] ?? '' );
	}

	/**
	 * Pricing for one model, in USD per million tokens.
	 *
	 * @param string $model Model id.
	 * @return array{0: float, 1: float}|null Null when the model is not priced.
	 */
	public function get_pricing( string $model ): ?array {
		return static::PRICING[ $model ] ?? null;
	}

	/**
	 * Turn an HTTP failure into an error the user can act on.
	 *
	 * "Something went wrong" leaves a user stuck; "your API key was rejected"
	 * tells them exactly where to go. Each status gets its own code so the
	 * browser can react to the specific case.
	 *
	 * @param int    $status        HTTP status.
	 * @param string $vendor_detail Vendor error text. Redacted before use.
	 * @param string $api_key       Key to strip from the vendor text.
	 * @return \WP_Error
	 */
	protected function http_error( int $status, string $vendor_detail, string $api_key ): \WP_Error {
		$detail = $this->redact( $vendor_detail, $api_key );

		// Out of credit is reported as 400 by Anthropic and 429 by OpenAI. Both
		// mean the same thing to the user, and neither is fixed by waiting.
		if ( preg_match( '/credit balance|insufficient_quota|exceeded your current quota|billing/i', $detail ) ) {
			return new \WP_Error(
				'ai_quota_exceeded',
				sprintf(
					/* translators: %s: AI provider name, e.g. Anthropic */
					__( 'Your %s account has no credit left. Add credit in your provider account, then try again.', 'my-site-hand' ),
					$this->get_label()
				),
				[ 'status' => 402 ]
			);
		}

		if ( 401 === $status || 403 === $status ) {
			return new \WP_Error(
				'ai_invalid_key',
				__( 'Your API key was rejected. Check it in Settings.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		if ( 429 === $status ) {
			return new \WP_Error(
				'ai_rate_limited',
				__( 'Provider rate limit reached. Try again in a minute.', 'my-site-hand' ),
				[ 'status' => 429 ]
			);
		}

		if ( 529 === $status || 503 === $status ) {
			return new \WP_Error(
				'ai_overloaded',
				sprintf(
					/* translators: %s: AI provider name, e.g. Anthropic */
					__( '%s is overloaded right now. Try again in a few minutes.', 'my-site-hand' ),
					$this->get_label()
				),
				[ 'status' => 503 ]
			);
		}

		if ( 400 === $status || 404 === $status || 422 === $status ) {
			return new \WP_Error(
				'ai_bad_request',
				'' !== $detail
					? sprintf(
						/* translators: %s: the error message returned by the AI provider */
						__( 'The provider refused the request: %s Check the model chosen in Settings.', 'my-site-hand' ),
						$detail
					)
					: __( 'The provider refused the request. Check the model chosen in Settings.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		return new \WP_Error(
			'ai_provider_error',
			sprintf(
				/* translators: 1: AI provider name, 2: HTTP status code */
				__( '%1$s returned an error (HTTP %2$d). This is on the provider\'s side; try again shortly.', 'my-site-hand' ),
				$this->get_label(),
				$status
			),
			[ 'status' => 502 ]
		);
	}

	/**
	 * Error for a request that never got an HTTP response.
	 *
	 * The transport's own message is not used: it can carry hostnames, proxy
	 * details and other things that do not belong in front of a user.
	 *
	 * @return \WP_Error
	 */
	protected function network_error(): \WP_Error {
		return new \WP_Error(
			'ai_network_error',
			sprintf(
				/* translators: %s: AI provider name, e.g. Anthropic */
				__( 'Could not reach %s. Your server may be blocking outgoing requests, or the provider is down.', 'my-site-hand' ),
				$this->get_label()
			),
			[ 'status' => 502 ]
		);
	}

	/**
	 * Error for a 200 response that does not contain what it should.
	 *
	 * @return \WP_Error
	 */
	protected function bad_response_error(): \WP_Error {
		return new \WP_Error(
			'ai_bad_response',
			__( 'The provider sent back a response that could not be read. Try again.', 'my-site-hand' ),
			[ 'status' => 502 ]
		);
	}

	/**
	 * Pull the human-readable message out of a vendor error body.
	 *
	 * Both supported vendors use { "error": { "message": "..." } }.
	 *
	 * @param string $body Raw response body.
	 * @return string
	 */
	protected function vendor_message( string $body ): string {
		$decoded = json_decode( $body, true );

		if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
			return mb_substr( $decoded['error']['message'], 0, 300 );
		}

		return '';
	}

	/**
	 * Remove anything resembling a credential from vendor text.
	 *
	 * @param string $text    Vendor text.
	 * @param string $api_key The key in use.
	 * @return string
	 */
	protected function redact( string $text, string $api_key ): string {
		if ( '' !== $api_key ) {
			$text = str_replace( $api_key, '[redacted]', $text );
		}

		// Partial echoes such as "sk-abc***xyz" as well as whole keys.
		$text = (string) preg_replace( '/\b(sk|key)[-_][A-Za-z0-9_\-\*\.]{4,}/i', '[redacted]', $text );

		return trim( wp_strip_all_tags( $text ) );
	}

	/**
	 * Strip a string down to its first JSON-looking value's surroundings.
	 *
	 * Shared by callers that ask the model for JSON. Kept here because "remove
	 * the code fences the model was told not to write" is a provider quirk, not
	 * business logic.
	 *
	 * @param string $text Model output.
	 * @return string
	 */
	public static function strip_code_fences( string $text ): string {
		$text = trim( $text );
		$text = (string) preg_replace( '/^```[a-zA-Z]*\s*/', '', $text );
		$text = (string) preg_replace( '/\s*```$/', '', $text );

		return trim( $text );
	}
}
