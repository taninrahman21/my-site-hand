<?php
/**
 * AI provider registry, credential storage, usage tracking and spend cap.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * AI Manager class.
 *
 * EVERYTHING AI IN THIS PLUGIN IS OPTIONAL. The scan, Quick Fix, exports,
 * sharing, scheduled reports and MCP all work with no key at all. Every AI
 * code path asks is_configured() first and, when it says no, renders nothing:
 * no notice, no disabled button, no "add a key" prompt. A user who never
 * wants AI must not be able to tell it exists.
 */
class AI_Manager {

	/**
	 * Option: active provider id, or '' for none.
	 *
	 * @var string
	 */
	public const OPTION_PROVIDER = 'mysitehand_ai_provider';

	/**
	 * Option: the API key. A third-party credential — see get_api_key().
	 *
	 * @var string
	 */
	public const OPTION_API_KEY = 'mysitehand_ai_api_key';

	/**
	 * Option: model id.
	 *
	 * @var string
	 */
	public const OPTION_MODEL = 'mysitehand_ai_model';

	/**
	 * Option: daily spend cap in USD.
	 *
	 * @var string
	 */
	public const OPTION_DAILY_CAP = 'mysitehand_ai_daily_cap';

	/**
	 * Option: rolling per-day usage.
	 *
	 * @var string
	 */
	public const OPTION_USAGE = 'mysitehand_ai_usage';

	/**
	 * Default daily spend cap, in USD.
	 *
	 * @var float
	 */
	public const DEFAULT_DAILY_CAP = 1.00;

	/**
	 * Days of usage history kept.
	 *
	 * @var int
	 */
	public const USAGE_RETENTION_DAYS = 30;

	/**
	 * Price assumed for a model with no pricing entry, USD per million tokens.
	 *
	 * Deliberately on the expensive side: an unpriced model added by a filter
	 * should make the daily cap trip early, never late.
	 *
	 * @var array{0: float, 1: float}
	 */
	public const FALLBACK_PRICING = [ 5.00, 25.00 ];

	/**
	 * Memoized providers, keyed by id.
	 *
	 * @var array<string, AI_Provider_Base>|null
	 */
	private ?array $providers = null;

	/**
	 * Every available provider, keyed by id.
	 *
	 * @return array<string, AI_Provider_Base>
	 */
	public function get_providers(): array {
		if ( null !== $this->providers ) {
			return $this->providers;
		}

		/**
		 * Filter the AI providers offered in Settings.
		 *
		 * @param array<int|string, AI_Provider_Base> $providers Provider instances.
		 */
		$registered = apply_filters(
			'my_site_hand_ai_providers',
			[
				new AI_Provider_Anthropic(),
				new AI_Provider_OpenAI(),
			]
		);

		$providers = [];

		foreach ( (array) $registered as $provider ) {
			if ( $provider instanceof AI_Provider_Base && '' !== $provider->get_id() ) {
				$providers[ $provider->get_id() ] = $provider;
			}
		}

		$this->providers = $providers;

		return $this->providers;
	}

	/**
	 * The provider chosen in Settings, if it is still available.
	 *
	 * @return AI_Provider_Base|null
	 */
	public function get_active_provider(): ?AI_Provider_Base {
		$id = (string) get_option( self::OPTION_PROVIDER, '' );

		if ( '' === $id ) {
			return null;
		}

		return $this->get_providers()[ $id ] ?? null;
	}

	/**
	 * Whether AI can be used at all: a known provider and a stored key.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return null !== $this->get_active_provider() && '' !== $this->get_api_key();
	}

	/**
	 * The model to use: the stored choice when the provider offers it,
	 * otherwise the provider's cheapest.
	 *
	 * @return string
	 */
	public function get_model(): string {
		$provider = $this->get_active_provider();

		if ( null === $provider ) {
			return '';
		}

		$model = (string) get_option( self::OPTION_MODEL, '' );

		return array_key_exists( $model, $provider->get_models() ) ? $model : $provider->get_default_model();
	}

	/**
	 * The daily spend cap, in USD.
	 *
	 * @return float
	 */
	public function get_daily_cap(): float {
		return max( 0.0, (float) get_option( self::OPTION_DAILY_CAP, self::DEFAULT_DAILY_CAP ) );
	}

	/**
	 * Run one completion through the active provider.
	 *
	 * The spend cap is enforced here, before any HTTP request, so no caller
	 * can forget it. It exists so a runaway loop cannot drain someone's
	 * account overnight.
	 *
	 * @param array<string, mixed> $args system, messages, max_tokens, temperature.
	 * @return array{text: string, input_tokens: int, output_tokens: int, model: string, cost: float}|\WP_Error
	 */
	public function complete( array $args ): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return $this->not_configured_error();
		}

		$cap = $this->get_daily_cap();

		if ( $this->get_usage( 'today' )['cost'] >= $cap ) {
			return new \WP_Error(
				'ai_daily_cap_reached',
				sprintf(
					/* translators: %s: the daily spend cap, e.g. $1.00 */
					__( 'Daily spend cap of %s reached. Raise it in Settings, or try again tomorrow.', 'my-site-hand' ),
					self::format_usd( $cap )
				),
				[ 'status' => 429 ]
			);
		}

		$provider = $this->get_active_provider();

		$args['api_key'] = $this->get_api_key();
		$args['model']   = $this->get_model();

		$result = $provider->complete( $args );

		// Drop the credential from memory the moment it is no longer needed,
		// so nothing downstream can accidentally serialize it.
		unset( $args['api_key'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$cost = $this->record_usage( $result['input_tokens'], $result['output_tokens'], $result['model'] );

		$result['cost'] = $cost;

		return $result;
	}

	/**
	 * Test the stored key against the active provider.
	 *
	 * @return bool|\WP_Error
	 */
	public function test_connection(): bool|\WP_Error {
		$provider = $this->get_active_provider();

		if ( null === $provider ) {
			return new \WP_Error(
				'ai_no_provider',
				__( 'Choose a provider first.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		$key = $this->get_api_key();

		if ( '' === $key ) {
			return new \WP_Error(
				'ai_no_key',
				__( 'Paste an API key first.', 'my-site-hand' ),
				[ 'status' => 400 ]
			);
		}

		return $provider->validate_key( $key );
	}

	/**
	 * Add one request to today's usage.
	 *
	 * @param int    $input  Input tokens.
	 * @param int    $output Output tokens.
	 * @param string $model  Model that served the request.
	 * @return float Estimated cost of this request, in USD.
	 */
	public function record_usage( int $input, int $output, string $model ): float {
		$cost  = $this->estimate_cost( $input, $output, $model );
		$usage = $this->read_usage();
		$today = $this->today();

		$day = $usage[ $today ] ?? [
			'input'    => 0,
			'output'   => 0,
			'cost'     => 0.0,
			'requests' => 0,
		];

		$day['input']    += max( 0, $input );
		$day['output']   += max( 0, $output );
		$day['cost']      = round( (float) $day['cost'] + $cost, 6 );
		$day['requests'] += 1;

		$usage[ $today ] = $day;

		update_option( self::OPTION_USAGE, $this->prune_usage( $usage ), false );

		return $cost;
	}

	/**
	 * Usage totals for a period.
	 *
	 * @param string $period 'today', 'month' (calendar month) or '30days'.
	 * @return array{input: int, output: int, cost: float, requests: int}
	 */
	public function get_usage( string $period = 'today' ): array {
		$totals = [
			'input'    => 0,
			'output'   => 0,
			'cost'     => 0.0,
			'requests' => 0,
		];

		$today = $this->today();
		$month = substr( $today, 0, 7 );

		foreach ( $this->read_usage() as $date => $day ) {
			$include = match ( $period ) {
				'today' => $date === $today,
				'month' => str_starts_with( (string) $date, $month ),
				default => true,
			};

			if ( ! $include ) {
				continue;
			}

			$totals['input']    += (int) ( $day['input'] ?? 0 );
			$totals['output']   += (int) ( $day['output'] ?? 0 );
			$totals['cost']     += (float) ( $day['cost'] ?? 0 );
			$totals['requests'] += (int) ( $day['requests'] ?? 0 );
		}

		$totals['cost'] = round( $totals['cost'], 6 );

		return $totals;
	}

	/**
	 * Estimated cost of a request, in USD.
	 *
	 * @param int    $input  Input tokens.
	 * @param int    $output Output tokens.
	 * @param string $model  Model id.
	 * @return float
	 */
	public function estimate_cost( int $input, int $output, string $model ): float {
		[ $in_price, $out_price ] = $this->pricing_for( $model );

		return round( ( max( 0, $input ) * $in_price + max( 0, $output ) * $out_price ) / 1000000, 6 );
	}

	/**
	 * Pricing for a model, looked up across every provider.
	 *
	 * The model a response reports can be a dated snapshot of the id that was
	 * requested ("gpt-4o-mini-2024-07-18"), so a prefix match is accepted.
	 *
	 * @param string $model Model id.
	 * @return array{0: float, 1: float}
	 */
	public function pricing_for( string $model ): array {
		foreach ( $this->get_providers() as $provider ) {
			$pricing = $provider->get_pricing( $model );

			if ( null !== $pricing ) {
				return $pricing;
			}

			foreach ( $provider::PRICING as $id => $prices ) {
				if ( str_starts_with( $model, $id ) ) {
					return $prices;
				}
			}
		}

		return self::FALLBACK_PRICING;
	}

	/**
	 * The stored key, masked for display: "sk-ant-...4f2a".
	 *
	 * This is the ONLY form of the key that may ever reach HTML.
	 *
	 * @return string Empty when no key is stored.
	 */
	public function get_masked_key(): string {
		return self::mask_key( $this->get_api_key() );
	}

	/**
	 * Mask a key for display.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function mask_key( string $key ): string {
		$length = strlen( $key );

		if ( 0 === $length ) {
			return '';
		}

		// Too short to show any of it without showing most of it.
		if ( $length < 16 ) {
			return str_repeat( '•', 8 );
		}

		return substr( $key, 0, 7 ) . '...' . substr( $key, -4 );
	}

	/**
	 * Clean a key pasted into Settings.
	 *
	 * @param string $key Raw input.
	 * @return string|null Clean key, '' to remove it, or null when invalid.
	 */
	public static function sanitize_api_key( string $key ): ?string {
		// Pasting from a dashboard often drags whitespace or a newline along.
		$key = (string) preg_replace( '/\s+/', '', $key );

		if ( '' === $key ) {
			return '';
		}

		if ( strlen( $key ) > 400 || ! preg_match( '/^[A-Za-z0-9_\-\.]+$/', $key ) ) {
			return null;
		}

		return $key;
	}

	/**
	 * Format a dollar amount for display.
	 *
	 * Small amounts get more precision: "$0.0031" is information, "$0.00" is
	 * not.
	 *
	 * @param float $amount USD.
	 * @return string
	 */
	public static function format_usd( float $amount ): string {
		if ( $amount > 0 && $amount < 0.01 ) {
			return '$' . number_format_i18n( $amount, 4 );
		}

		return '$' . number_format_i18n( $amount, 2 );
	}

	/**
	 * Error returned by every AI path when no provider or key is set.
	 *
	 * @return \WP_Error
	 */
	public function not_configured_error(): \WP_Error {
		return new \WP_Error(
			'ai_not_configured',
			__( 'AI assistance is not set up. Add an API key in Settings to use it.', 'my-site-hand' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * The stored key.
	 *
	 * Private on purpose. Nothing outside this class needs the raw value:
	 * complete() and test_connection() hand it straight to the provider, and
	 * everything else gets get_masked_key().
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		$key = get_option( self::OPTION_API_KEY, '' );

		return is_string( $key ) ? $key : '';
	}

	/**
	 * Read the usage option.
	 *
	 * @return array<string, array<string, int|float>>
	 */
	private function read_usage(): array {
		$usage = get_option( self::OPTION_USAGE, [] );

		return is_array( $usage ) ? $usage : [];
	}

	/**
	 * Drop days older than the retention window.
	 *
	 * The cutoff is inclusive of the day thirty days ago, so on the 31st of a
	 * month the 1st is still there and "this month" stays complete.
	 *
	 * @param array<string, mixed> $usage Usage keyed by Y-m-d.
	 * @return array<string, mixed>
	 */
	private function prune_usage( array $usage ): array {
		$cutoff = wp_date( 'Y-m-d', time() - self::USAGE_RETENTION_DAYS * DAY_IN_SECONDS );

		foreach ( array_keys( $usage ) as $date ) {
			if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date < $cutoff ) {
				unset( $usage[ $date ] );
			}
		}

		ksort( $usage );

		return $usage;
	}

	/**
	 * Today's date in the site's own timezone.
	 *
	 * The site's day, not UTC's: "today's spend" should reset at the owner's
	 * midnight.
	 *
	 * @return string
	 */
	private function today(): string {
		return (string) wp_date( 'Y-m-d' );
	}
}
