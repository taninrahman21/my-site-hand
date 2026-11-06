<?php
/**
 * WCAG colour contrast maths.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Color_Contrast class.
 *
 * Pure functions, no WordPress state. Used by the starter site validator to
 * prove every shipped palette is readable, and by the editor to keep a custom
 * brand colour readable.
 */
class Color_Contrast {

	/**
	 * WCAG 2.x AA minimum for normal-size text.
	 *
	 * @var float
	 */
	public const AA_NORMAL = 4.5;

	/**
	 * Text-on-background pairs every palette must pass, as [ text, background ].
	 *
	 * These are the only combinations the starter site markup uses for text.
	 * A starter site that puts text on any other pair is a validator error,
	 * not something this list should grow to excuse.
	 *
	 * @var array<int, array{0: string, 1: string}>
	 */
	public const TEXT_PAIRS = [
		[ 'contrast', 'base' ],
		[ 'contrast', 'surface' ],
		[ 'contrast', 'accent' ],
		[ 'muted', 'base' ],
		[ 'muted', 'surface' ],
		[ 'primary', 'base' ],
		[ 'primary', 'surface' ],
		[ 'on-primary', 'primary' ],
	];

	/**
	 * Palette colour slugs every palette must define.
	 *
	 * @var array<int, string>
	 */
	public const PALETTE_SLUGS = [ 'base', 'surface', 'accent', 'contrast', 'muted', 'primary', 'on-primary' ];

	/**
	 * Parse a #rgb or #rrggbb colour.
	 *
	 * @param string $hex Hex colour.
	 * @return array{0: int, 1: int, 2: int}|null RGB channels, or null if invalid.
	 */
	public static function hex_to_rgb( string $hex ): ?array {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return null;
		}

		return [
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		];
	}

	/**
	 * Format RGB channels as #rrggbb.
	 *
	 * @param float $r Red, 0-255.
	 * @param float $g Green, 0-255.
	 * @param float $b Blue, 0-255.
	 * @return string
	 */
	public static function rgb_to_hex( float $r, float $g, float $b ): string {
		$clamp = static fn( float $c ): int => (int) max( 0, min( 255, round( $c ) ) );
		return sprintf( '#%02x%02x%02x', $clamp( $r ), $clamp( $g ), $clamp( $b ) );
	}

	/**
	 * WCAG relative luminance.
	 *
	 * @param string $hex Hex colour.
	 * @return float 0 (black) to 1 (white). Invalid colours count as black.
	 */
	public static function luminance( string $hex ): float {
		$rgb = self::hex_to_rgb( $hex );
		if ( null === $rgb ) {
			return 0.0;
		}

		$linear = array_map(
			static function ( int $channel ): float {
				$c = $channel / 255;
				return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			},
			$rgb
		);

		return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
	}

	/**
	 * WCAG contrast ratio between two colours.
	 *
	 * @param string $a First hex colour.
	 * @param string $b Second hex colour.
	 * @return float 1 to 21.
	 */
	public static function ratio( string $a, string $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );

		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Build a full palette from one brand colour.
	 *
	 * Deterministic: the same input always gives the same palette. Every
	 * colour keeps the brand's hue. The primary colour is darkened step by
	 * step, and the muted text colour likewise, until every pair in
	 * TEXT_PAIRS passes WCAG AA.
	 *
	 * @param string $brand Hex colour.
	 * @param string $mode  'light' or 'dark'.
	 * @return array<string, string>|null Slug => hex, or null for an invalid colour.
	 */
	public static function palette_from_brand( string $brand, string $mode = 'light' ): ?array {
		$rgb = self::hex_to_rgb( $brand );
		if ( null === $rgb ) {
			return null;
		}

		[ $h, $s, $l ] = self::rgb_to_hsl( ...$rgb );

		if ( 'dark' === $mode ) {
			return self::dark_palette( $h, $s, $l );
		}

		$tint = min( $s, 0.35 );
		$base = '#ffffff';

		$colors = [
			'base'       => $base,
			'surface'    => self::hsl_to_hex( $h, $tint, 0.965 ),
			'accent'     => self::hsl_to_hex( $h, $tint, 0.915 ),
			'contrast'   => self::hsl_to_hex( $h, min( $s, 0.30 ), 0.12 ),
			'muted'      => self::hsl_to_hex( $h, min( $s, 0.15 ), 0.36 ),
			'primary'    => self::hsl_to_hex( $h, $s, min( $l, 0.45 ) ),
			'on-primary' => '#ffffff',
		];

		// Darken primary until white text on it and it on the light
		// backgrounds both pass. Lightness 0.08 always passes, so this ends.
		$primary_l = min( $l, 0.45 );
		while ( $primary_l > 0.08 && (
			self::ratio( '#ffffff', $colors['primary'] ) < self::AA_NORMAL
			|| self::ratio( $colors['primary'], $colors['surface'] ) < self::AA_NORMAL
		) ) {
			$primary_l        -= 0.02;
			$colors['primary'] = self::hsl_to_hex( $h, $s, $primary_l );
		}

		$muted_l = 0.36;
		while ( $muted_l > 0.08 && self::ratio( $colors['muted'], $colors['surface'] ) < self::AA_NORMAL ) {
			$muted_l        -= 0.02;
			$colors['muted'] = self::hsl_to_hex( $h, min( $s, 0.15 ), $muted_l );
		}

		return $colors;
	}

	/**
	 * A dark palette around a hue: near-black backgrounds, light text and a
	 * primary colour lifted until it reads on them.
	 *
	 * @param float $h Hue.
	 * @param float $s Saturation.
	 * @param float $l Lightness of the brand colour.
	 * @return array<string, string>
	 */
	private static function dark_palette( float $h, float $s, float $l ): array {
		$tint   = min( $s, 0.25 );
		$colors = [
			'base'     => self::hsl_to_hex( $h, $tint, 0.07 ),
			'surface'  => self::hsl_to_hex( $h, $tint, 0.11 ),
			'accent'   => self::hsl_to_hex( $h, $tint, 0.16 ),
			'contrast' => self::hsl_to_hex( $h, min( $s, 0.20 ), 0.94 ),
			'muted'    => self::hsl_to_hex( $h, min( $s, 0.12 ), 0.72 ),
		];

		$primary_l         = max( $l, 0.55 );
		$colors['primary'] = self::hsl_to_hex( $h, $s, $primary_l );
		while ( $primary_l < 0.92 && (
			self::ratio( $colors['primary'], $colors['surface'] ) < self::AA_NORMAL
			|| self::ratio( $colors['base'], $colors['primary'] ) < self::AA_NORMAL
		) ) {
			$primary_l        += 0.02;
			$colors['primary'] = self::hsl_to_hex( $h, $s, $primary_l );
		}
		$colors['on-primary'] = $colors['base'];

		return self::fix_palette( $colors );
	}

	/**
	 * Adjust a palette until every text pair passes WCAG AA.
	 *
	 * Only text colours move (and the primary, which is also text): each is
	 * pushed lighter or darker, away from its background, a little at a
	 * time. Backgrounds never change, so the palette keeps its character.
	 *
	 * @param array<string, string> $colors Slug => hex, all seven slugs.
	 * @return array<string, string>
	 */
	public static function fix_palette( array $colors ): array {
		for ( $i = 0; $i < 120; $i++ ) {
			$failing = self::failing_pairs( $colors );
			if ( ! $failing ) {
				break;
			}

			$text = $failing[0]['text'];
			$bg   = $failing[0]['background'];

			if ( 'on-primary' === $text ) {
				// Pick whichever of white and near-black reads better.
				$light = self::ratio( '#ffffff', $colors['primary'] );
				$dark  = self::ratio( '#111111', $colors['primary'] );
				if ( max( $light, $dark ) >= self::AA_NORMAL ) {
					$colors['on-primary'] = $light >= $dark ? '#ffffff' : '#111111';
					continue;
				}
				// Neither does: move the primary away from the page instead.
				$text = 'primary';
				$bg   = 'base';
			}

			[ $h, $s, $l ] = self::rgb_to_hsl( ...( self::hex_to_rgb( $colors[ $text ] ) ?? [ 0, 0, 0 ] ) );
			$step          = self::luminance( $colors[ $bg ] ) > 0.4 ? -0.03 : 0.03;

			$colors[ $text ] = self::hsl_to_hex( $h, $s, max( 0.0, min( 1.0, $l + $step ) ) );
		}

		return $colors;
	}

	/**
	 * RGB to HSL.
	 *
	 * @param int $r Red, 0-255.
	 * @param int $g Green, 0-255.
	 * @param int $b Blue, 0-255.
	 * @return array{0: float, 1: float, 2: float} Hue 0-360, saturation and lightness 0-1.
	 */
	public static function rgb_to_hsl( int $r, int $g, int $b ): array {
		$r /= 255;
		$g /= 255;
		$b /= 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;

		if ( $max === $min ) {
			return [ 0.0, 0.0, $l ];
		}

		$d = $max - $min;
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );

		if ( $max === $r ) {
			$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$h = ( $b - $r ) / $d + 2;
		} else {
			$h = ( $r - $g ) / $d + 4;
		}

		return [ $h * 60, $s, $l ];
	}

	/**
	 * HSL to hex.
	 *
	 * @param float $h Hue, 0-360.
	 * @param float $s Saturation, 0-1.
	 * @param float $l Lightness, 0-1.
	 * @return string
	 */
	public static function hsl_to_hex( float $h, float $s, float $l ): string {
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;

		$sector = (int) floor( fmod( $h, 360 ) / 60 );
		$rgb    = [
			[ $c, $x, 0 ],
			[ $x, $c, 0 ],
			[ 0, $c, $x ],
			[ 0, $x, $c ],
			[ $x, 0, $c ],
			[ $c, 0, $x ],
		][ max( 0, min( 5, $sector ) ) ];

		return self::rgb_to_hex( ( $rgb[0] + $m ) * 255, ( $rgb[1] + $m ) * 255, ( $rgb[2] + $m ) * 255 );
	}

	/**
	 * Check every required text pair of a palette.
	 *
	 * @param array<string, string> $colors Slug => hex.
	 * @return array<int, array{text: string, background: string, ratio: float}> Failing pairs; empty when the palette passes.
	 */
	public static function failing_pairs( array $colors ): array {
		$failures = [];

		foreach ( self::TEXT_PAIRS as $pair ) {
			[ $text, $background ] = $pair;

			$ratio = self::ratio( (string) ( $colors[ $text ] ?? '' ), (string) ( $colors[ $background ] ?? '' ) );
			if ( $ratio < self::AA_NORMAL ) {
				$failures[] = [
					'text'       => $text,
					'background' => $background,
					'ratio'      => round( $ratio, 2 ),
				];
			}
		}

		return $failures;
	}
}
