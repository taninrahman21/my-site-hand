<?php
/**
 * Builds user global styles from a starter site and the user's design choices.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Global_Styles_Builder class.
 *
 * Output is ordinary theme.json data of the kind the Site Editor itself saves
 * to the wp_global_styles post: no plugin-only keys, so it keeps working, and
 * stays editable, after the plugin is removed.
 *
 * Presets are merged over the active theme's own presets by slug instead of
 * replacing them, the way theme style variations do. Templates the starter
 * site does not touch (blog, single post, 404) therefore keep every preset
 * they reference, while shared slugs (base, contrast, small..xx-large, the
 * spacing steps) take the starter site's values so the whole site matches.
 */
class Global_Styles_Builder {

	/**
	 * Corner styles: radius for buttons, cards and images.
	 *
	 * @var array<string, array{button: string, card: string, media: string}>
	 */
	public const CORNERS = [
		'square'  => [
			'button' => '0px',
			'card'   => '0px',
			'media'  => '0px',
		],
		'soft'    => [
			'button' => '6px',
			'card'   => '8px',
			'media'  => '8px',
		],
		'rounded' => [
			'button' => '12px',
			'card'   => '18px',
			'media'  => '18px',
		],
		'pill'    => [
			'button' => '999px',
			'card'   => '28px',
			'media'  => '28px',
		],
	];

	/**
	 * Spacing styles: a factor applied to every spacing preset.
	 *
	 * @var array<string, float>
	 */
	public const SPACING = [
		'compact'     => 0.75,
		'comfortable' => 1.0,
		'airy'        => 1.3,
	];

	/**
	 * Build the user global styles.
	 *
	 * @param Starter_Site          $site      Package.
	 * @param array<string, string> $colors    Palette slug => hex.
	 * @param array<string, mixed>  $font_pair Font pair from fonts.json.
	 * @param array<string, mixed>  $design    Design choices (corners, spacing).
	 * @return array<string, mixed> theme.json data.
	 */
	public static function build( Starter_Site $site, array $colors, array $font_pair, array $design = [] ): array {
		$styles = $site->get_global_styles();
		$theme  = self::theme_settings();

		$palette = [];
		foreach ( Color_Contrast::PALETTE_SLUGS as $slug ) {
			$hex = sanitize_hex_color( (string) ( $colors[ $slug ] ?? '' ) );
			if ( $hex ) {
				$palette[] = [
					'slug'  => $slug,
					'name'  => self::color_name( $slug ),
					'color' => $hex,
				];
			}
		}

		$families = [];
		foreach ( [ 'heading', 'body' ] as $role ) {
			$families[] = [
				'slug'       => $role,
				'name'       => 'heading' === $role ? __( 'Headings', 'my-site-hand' ) : __( 'Body', 'my-site-hand' ),
				'fontFamily' => self::clean_font_stack( (string) ( $font_pair[ $role ]['fontFamily'] ?? '' ) ),
			];
		}

		$settings = (array) ( $styles['settings'] ?? [] );

		$settings['color']['palette']['theme'] = self::merge_presets( $theme['color']['palette']['theme'] ?? [], $palette );

		$settings['typography']['fontFamilies'] = [
			'theme' => self::merge_presets( $theme['typography']['fontFamilies']['theme'] ?? [], $families ),
		];

		if ( isset( $settings['typography']['fontSizes'] ) ) {
			$settings['typography']['fontSizes'] = [
				'theme' => self::merge_presets( $theme['typography']['fontSizes']['theme'] ?? [], (array) $settings['typography']['fontSizes'] ),
			];

			$settings['typography']['fluid'] = true;
		}

		if ( isset( $settings['spacing']['spacingSizes'] ) ) {
			$sizes  = (array) $settings['spacing']['spacingSizes'];
			$factor = self::SPACING[ (string) ( $design['spacing'] ?? '' ) ] ?? 1.0;
			if ( 1.0 !== $factor ) {
				foreach ( $sizes as $i => $size ) {
					$sizes[ $i ]['size'] = self::scale_length( (string) $size['size'], $factor );
				}
			}
			$settings['spacing']['spacingSizes'] = [
				'theme' => self::merge_presets( $theme['spacing']['spacingSizes']['theme'] ?? [], $sizes ),
			];
		}

		// Corners: the starter markup reads these custom properties, with
		// the site's own radius as fallback when none is chosen.
		$corners = self::CORNERS[ (string) ( $design['corners'] ?? '' ) ] ?? null;
		if ( null !== $corners ) {
			$settings['custom']['radius'] = $corners;
		}

		$styles['settings'] = $settings;

		$weight = (string) ( $font_pair['heading']['fontWeight'] ?? '' );
		if ( preg_match( '/^[1-9]00$|^[1-9]50$/', $weight ) ) {
			$styles['styles']['elements']['heading']['typography']['fontWeight'] = $weight;
		}

		$styles['version']                     = 3;
		$styles['isGlobalStylesUserThemeJSON'] = true;

		return $styles;
	}

	/**
	 * The active theme's own settings, presets keyed by origin.
	 *
	 * @return array<string, mixed>
	 */
	private static function theme_settings(): array {
		return \WP_Theme_JSON_Resolver::get_theme_data()->get_settings();
	}

	/**
	 * Merge presets by slug: ours override, the theme's extras are kept.
	 *
	 * @param array<int, array<string, mixed>> $theme Theme presets.
	 * @param array<int, array<string, mixed>> $ours  Starter site presets.
	 * @return array<int, array<string, mixed>>
	 */
	private static function merge_presets( array $theme, array $ours ): array {
		$by_slug = [];

		foreach ( $theme as $preset ) {
			if ( isset( $preset['slug'] ) ) {
				$by_slug[ (string) $preset['slug'] ] = $preset;
			}
		}

		foreach ( $ours as $preset ) {
			if ( isset( $preset['slug'] ) ) {
				$by_slug[ (string) $preset['slug'] ] = $preset;
			}
		}

		return array_values( $by_slug );
	}

	/**
	 * Multiply every length in a CSS value (rem, px, vw), e.g. inside clamp().
	 *
	 * @param string $value  CSS length or clamp().
	 * @param float  $factor Multiplier.
	 * @return string
	 */
	private static function scale_length( string $value, float $factor ): string {
		return (string) preg_replace_callback(
			'/(\d*\.?\d+)(rem|px|vw)/',
			static fn( $m ) => rtrim( rtrim( number_format( (float) $m[1] * $factor, 3, '.', '' ), '0' ), '.' ) . $m[2],
			$value
		);
	}

	/**
	 * Keep a CSS font stack to the characters a stack needs.
	 *
	 * @param string $stack Font stack.
	 * @return string
	 */
	private static function clean_font_stack( string $stack ): string {
		return trim( (string) preg_replace( '/[^a-zA-Z0-9 ,\'"\-]/', '', $stack ) );
	}

	/**
	 * Human name for a palette slug, shown in the Site Editor colour picker.
	 *
	 * @param string $slug Palette slug.
	 * @return string
	 */
	public static function color_name( string $slug ): string {
		$names = [
			'base'       => __( 'Base', 'my-site-hand' ),
			'surface'    => __( 'Surface', 'my-site-hand' ),
			'accent'     => __( 'Accent', 'my-site-hand' ),
			'contrast'   => __( 'Contrast', 'my-site-hand' ),
			'muted'      => __( 'Muted', 'my-site-hand' ),
			'primary'    => __( 'Primary', 'my-site-hand' ),
			'on-primary' => __( 'On primary', 'my-site-hand' ),
		];

		return $names[ $slug ] ?? $slug;
	}
}
