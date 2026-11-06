<?php
/**
 * Discovers the starter site packages shipped with the plugin.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Starter_Site_Library class.
 *
 * Packages live in /starter-sites/<slug>/ inside the plugin. There is no
 * remote library: everything a user can pick is already on their server.
 */
class Starter_Site_Library {

	/**
	 * Loaded packages, keyed by slug. Null until first scan.
	 *
	 * @var array<string, Starter_Site>|null
	 */
	private ?array $sites = null;

	/**
	 * Packages that failed to load, as slug => WP_Error.
	 *
	 * @var array<string, \WP_Error>
	 */
	private array $errors = [];

	/**
	 * Folder of the shared section library, inside the base directory.
	 *
	 * @var string
	 */
	public const SECTION_LIBRARY = '_library';

	/**
	 * Section types, once loaded.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $section_types = null;

	/**
	 * Absolute directory that holds the packages.
	 *
	 * @return string
	 */
	public static function get_base_dir(): string {
		return MYSITEHAND_PATH . 'starter-sites/';
	}

	/**
	 * All packages that loaded, sorted by name.
	 *
	 * @return array<string, Starter_Site>
	 */
	public function get_all(): array {
		$this->scan();
		return $this->sites;
	}

	/**
	 * One package by slug.
	 *
	 * @param string $slug Package slug.
	 * @return Starter_Site|null
	 */
	public function get( string $slug ): ?Starter_Site {
		$this->scan();
		return $this->sites[ sanitize_key( $slug ) ] ?? null;
	}

	/**
	 * Packages that failed to load.
	 *
	 * @return array<string, \WP_Error>
	 */
	public function get_errors(): array {
		$this->scan();
		return $this->errors;
	}

	/**
	 * Section types users can add to any page, keyed by type.
	 *
	 * Each has a label, a description, its markup file and its slot
	 * definitions, whose ids start with the library prefix ("s.x").
	 *
	 * @return array<string, array{type: string, label: string, description: string, file: string, slots: array<int, array<string, mixed>>}>
	 */
	public function get_section_types(): array {
		if ( null === $this->section_types ) {
			$this->section_types = [];
			$file                = self::get_base_dir() . self::SECTION_LIBRARY . '/library.json';

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local, plugin-shipped file.
			$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;

			foreach ( (array) ( $data['sections'] ?? [] ) as $type ) {
				if ( is_array( $type ) && ! empty( $type['type'] ) ) {
					$this->section_types[ sanitize_key( (string) $type['type'] ) ] = [
						'type'        => sanitize_key( (string) $type['type'] ),
						'label'       => (string) ( $type['label'] ?? $type['type'] ),
						'description' => (string) ( $type['description'] ?? '' ),
						'file'        => (string) ( $type['file'] ?? '' ),
						'slots'       => array_values( array_filter( (array) ( $type['slots'] ?? [] ), 'is_array' ) ),
					];
				}
			}
		}

		return $this->section_types;
	}

	/**
	 * Markup of one library section type.
	 *
	 * @param string $type Section type.
	 * @return string|null
	 */
	public function get_section_markup( string $type ): ?string {
		$types = $this->get_section_types();
		if ( ! isset( $types[ $type ] ) ) {
			return null;
		}

		$path = Starter_Site::safe_path( trailingslashit( wp_normalize_path( self::get_base_dir() . self::SECTION_LIBRARY ) ), $types[ $type ]['file'] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local, plugin-shipped file.
		return null === $path ? null : (string) file_get_contents( $path );
	}

	/**
	 * Scan the base directory once per request.
	 *
	 * @return void
	 */
	private function scan(): void {
		if ( null !== $this->sites ) {
			return;
		}

		$this->sites = [];
		$dirs        = glob( self::get_base_dir() . '*', GLOB_ONLYDIR );

		foreach ( $dirs ? $dirs : [] as $dir ) {
			// Folders starting with "_" are shared resources, not sites.
			if ( str_starts_with( basename( $dir ), '_' ) ) {
				continue;
			}

			$site = Starter_Site::from_dir( $dir );

			if ( is_wp_error( $site ) ) {
				$this->errors[ basename( $dir ) ] = $site;
				continue;
			}

			$this->sites[ $site->get_slug() ] = $site;
		}

		uasort(
			$this->sites,
			static fn( Starter_Site $a, Starter_Site $b ): int => strcasecmp( $a->get_name(), $b->get_name() )
		);
	}
}
