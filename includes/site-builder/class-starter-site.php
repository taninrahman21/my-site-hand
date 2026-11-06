<?php
/**
 * One starter site package on disk.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Starter_Site class.
 *
 * A starter site is data, never code: a manifest.json, core block markup with
 * {{slot}} tokens, and a few JSON files. It ships inside the plugin, so
 * nothing here makes a network request.
 *
 * Every path the manifest names is resolved through resolve_path(), which
 * refuses anything that would leave the package folder.
 */
class Starter_Site {

	/**
	 * Package slug, which is also the folder name.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Absolute package directory, with a trailing slash.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Decoded manifest.json.
	 *
	 * @var array<string, mixed>
	 */
	private array $manifest;

	/**
	 * Decoded JSON files, keyed by relative path.
	 *
	 * @var array<string, mixed>
	 */
	private array $json_cache = [];

	/**
	 * Constructor.
	 *
	 * @param string               $slug     Package slug.
	 * @param string               $dir      Absolute package directory.
	 * @param array<string, mixed> $manifest Decoded manifest.
	 */
	public function __construct( string $slug, string $dir, array $manifest ) {
		$this->slug     = $slug;
		$this->dir      = trailingslashit( wp_normalize_path( $dir ) );
		$this->manifest = $manifest;
	}

	/**
	 * Load a package from its directory.
	 *
	 * @param string $dir Absolute package directory.
	 * @return self|\WP_Error
	 */
	public static function from_dir( string $dir ) {
		$slug = basename( untrailingslashit( $dir ) );

		if ( sanitize_key( $slug ) !== $slug ) {
			return new \WP_Error( 'mysitehand_starter_bad_slug', __( 'Starter site folder names may only use lowercase letters, numbers, dashes and underscores.', 'my-site-hand' ) );
		}

		$manifest = self::decode_json_file( trailingslashit( $dir ) . 'manifest.json' );
		if ( is_wp_error( $manifest ) ) {
			return $manifest;
		}

		return new self( $slug, $dir, $manifest );
	}

	/**
	 * Package slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Absolute package directory.
	 *
	 * @return string
	 */
	public function get_dir(): string {
		return $this->dir;
	}

	/**
	 * Raw manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function get_manifest(): array {
		return $this->manifest;
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return (string) ( $this->manifest['name'] ?? $this->slug );
	}

	/**
	 * Pages declared in the manifest.
	 *
	 * Each page is an ordered list of sections, one markup file each.
	 *
	 * @return array<int, array{slug: string, title: string, sections: array<int, array{id: string, label: string, file: string}>, template: string, is_front_page: bool}>
	 */
	public function get_pages(): array {
		$pages = [];

		foreach ( (array) ( $this->manifest['pages'] ?? [] ) as $page ) {
			if ( ! is_array( $page ) || empty( $page['slug'] ) ) {
				continue;
			}

			$sections = [];
			foreach ( (array) ( $page['sections'] ?? [] ) as $section ) {
				if ( is_array( $section ) && ! empty( $section['id'] ) ) {
					$sections[] = [
						'id'    => sanitize_key( (string) $section['id'] ),
						'label' => (string) ( $section['label'] ?? $section['id'] ),
						'file'  => (string) ( $section['file'] ?? '' ),
					];
				}
			}

			$pages[] = [
				'slug'          => sanitize_title( (string) $page['slug'] ),
				'title'         => (string) ( $page['title'] ?? $page['slug'] ),
				'sections'      => $sections,
				'template'      => sanitize_key( (string) ( $page['template'] ?? '' ) ),
				'is_front_page' => ! empty( $page['is_front_page'] ),
			];
		}

		return $pages;
	}

	/**
	 * Template parts, as area slug => relative file.
	 *
	 * @return array<string, string>
	 */
	public function get_template_parts(): array {
		return array_map( 'strval', (array) ( $this->manifest['template_parts'] ?? [] ) );
	}

	/**
	 * Page templates, as template slug => [ title, file ].
	 *
	 * @return array<string, array{title: string, file: string}>
	 */
	public function get_templates(): array {
		$templates = [];

		foreach ( (array) ( $this->manifest['templates'] ?? [] ) as $slug => $template ) {
			if ( ! is_array( $template ) ) {
				continue;
			}
			$templates[ (string) $slug ] = [
				'title' => (string) ( $template['title'] ?? $slug ),
				'file'  => (string) ( $template['file'] ?? '' ),
			];
		}

		return $templates;
	}

	/**
	 * Navigation items, in order.
	 *
	 * @return array<int, array{label: string, page: string}>
	 */
	public function get_navigation(): array {
		$items = [];

		foreach ( (array) ( $this->manifest['navigation'] ?? [] ) as $item ) {
			if ( is_array( $item ) && ! empty( $item['page'] ) ) {
				$items[] = [
					'label' => (string) ( $item['label'] ?? $item['page'] ),
					'page'  => sanitize_title( (string) $item['page'] ),
				];
			}
		}

		return $items;
	}

	/**
	 * Decoded slots.json: [ 'groups' => [...], 'slots' => [...] ].
	 *
	 * @return array{groups: array<int, array<string, mixed>>, slots: array<int, array<string, mixed>>}
	 */
	public function get_slots_schema(): array {
		$schema = $this->read_json( (string) ( $this->manifest['slots_schema'] ?? '' ) );

		return [
			'groups' => is_array( $schema['groups'] ?? null ) ? $schema['groups'] : [],
			'slots'  => is_array( $schema['slots'] ?? null ) ? $schema['slots'] : [],
		];
	}

	/**
	 * Slots keyed by id.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_slots(): array {
		$slots = [];

		foreach ( $this->get_slots_schema()['slots'] as $slot ) {
			if ( is_array( $slot ) && isset( $slot['id'] ) ) {
				$slots[ (string) $slot['id'] ] = $slot;
			}
		}

		return $slots;
	}

	/**
	 * Default value of every slot, keyed by id.
	 *
	 * @return array<string, mixed>
	 */
	public function get_default_values(): array {
		return array_map(
			static fn( array $slot ) => $slot['default'] ?? '',
			$this->get_slots()
		);
	}

	/**
	 * Preset palettes.
	 *
	 * @return array<int, array{id: string, name: string, colors: array<string, string>}>
	 */
	public function get_palettes(): array {
		$data = $this->read_json( (string) ( $this->manifest['palettes'] ?? '' ) );
		return is_array( $data['palettes'] ?? null ) ? $data['palettes'] : [];
	}

	/**
	 * Preset font pairs.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_font_pairs(): array {
		$data = $this->read_json( (string) ( $this->manifest['font_pairs'] ?? '' ) );
		return is_array( $data['font_pairs'] ?? null ) ? $data['font_pairs'] : [];
	}

	/**
	 * Base global styles (theme.json shape, without palette or font families).
	 *
	 * @return array<string, mixed>
	 */
	public function get_global_styles(): array {
		$data = $this->read_json( (string) ( $this->manifest['global_styles'] ?? '' ) );
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Screenshot URLs.
	 *
	 * @return array<int, string>
	 */
	public function get_screenshot_urls(): array {
		$urls = [];

		foreach ( (array) ( $this->manifest['screenshots'] ?? [] ) as $file ) {
			if ( null !== $this->resolve_path( (string) $file ) ) {
				$urls[] = $this->asset_url( (string) $file );
			}
		}

		return $urls;
	}

	/**
	 * Public URL of a file inside the package.
	 *
	 * @param string $relative Package-relative path.
	 * @return string
	 */
	public function asset_url( string $relative ): string {
		// "lib:" paths belong to the shared section library.
		if ( str_starts_with( $relative, 'lib:' ) ) {
			return MYSITEHAND_URL . 'starter-sites/_library/' . ltrim( substr( $relative, 4 ), '/' );
		}

		return MYSITEHAND_URL . 'starter-sites/' . $this->slug . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Read a text file from the package.
	 *
	 * @param string $relative Package-relative path.
	 * @return string|null Contents, or null if missing or outside the package.
	 */
	public function read_file( string $relative ): ?string {
		$path = $this->resolve_path( $relative );
		if ( null === $path ) {
			return null;
		}

		// Local, plugin-shipped file: WP_Filesystem would add nothing here.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $path );

		return false === $contents ? null : $contents;
	}

	/**
	 * Read and decode a JSON file from the package, cached per request.
	 *
	 * @param string $relative Package-relative path.
	 * @return mixed Decoded data, or null if missing or invalid.
	 */
	public function read_json( string $relative ) {
		if ( '' === $relative ) {
			return null;
		}

		if ( ! array_key_exists( $relative, $this->json_cache ) ) {
			$path = $this->resolve_path( $relative );
			$data = null === $path ? null : self::decode_json_file( $path );

			$this->json_cache[ $relative ] = is_wp_error( $data ) ? null : $data;
		}

		return $this->json_cache[ $relative ];
	}

	/**
	 * Resolve a package-relative path to an existing file inside the package.
	 *
	 * @param string $relative Package-relative path.
	 * @return string|null Absolute path, or null if missing or outside the package.
	 */
	public function resolve_path( string $relative ): ?string {
		$base = $this->dir;

		// "lib:" paths belong to the shared section library.
		if ( str_starts_with( $relative, 'lib:' ) ) {
			$base     = trailingslashit( wp_normalize_path( Starter_Site_Library::get_base_dir() . Starter_Site_Library::SECTION_LIBRARY ) );
			$relative = substr( $relative, 4 );
		}

		return self::safe_path( $base, $relative );
	}

	/**
	 * An existing file inside a directory, or null.
	 *
	 * @param string $base     Directory, with trailing slash.
	 * @param string $relative Path relative to it.
	 * @return string|null
	 */
	public static function safe_path( string $base, string $relative ): ?string {
		$relative = ltrim( wp_normalize_path( $relative ), '/' );

		if ( '' === $relative || str_contains( $relative, '..' ) || str_contains( $relative, ':' ) ) {
			return null;
		}

		$real = realpath( $base . $relative );

		if ( false === $real || ! is_file( $real ) ) {
			return null;
		}

		// Belt and braces against symlinks pointing out of the directory.
		if ( ! str_starts_with( wp_normalize_path( $real ), wp_normalize_path( (string) realpath( $base ) ) . '/' ) ) {
			return null;
		}

		return $real;
	}

	/**
	 * Decode a JSON file.
	 *
	 * @param string $path Absolute path.
	 * @return array<mixed>|\WP_Error
	 */
	private static function decode_json_file( string $path ) {
		if ( ! is_readable( $path ) ) {
			return new \WP_Error(
				'mysitehand_starter_missing_file',
				/* translators: %s: file name */
				sprintf( __( 'Missing file: %s', 'my-site-hand' ), basename( $path ) )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'mysitehand_starter_bad_json',
				/* translators: 1: file name 2: JSON error message */
				sprintf( __( 'Invalid JSON in %1$s: %2$s', 'my-site-hand' ), basename( $path ), json_last_error_msg() )
			);
		}

		return $data;
	}
}
