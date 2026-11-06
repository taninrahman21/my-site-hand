<?php
/**
 * Site builder service: the operations the admin UI and MCP share.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Site_Builder class.
 *
 * Choosing, customizing and staging never touch anything a visitor can see:
 * they only read the bundled packages and write the user's private staged
 * build. Apply and restore are the only writes to the live site, and they
 * live in Site_Applier.
 */
class Site_Builder {

	/**
	 * Starter site packages.
	 *
	 * @var Starter_Site_Library
	 */
	private Starter_Site_Library $library;

	/**
	 * Staged build storage.
	 *
	 * @var Staged_Sites
	 */
	private Staged_Sites $staged;

	/**
	 * Constructor.
	 *
	 * @param Starter_Site_Library|null $library Packages.
	 * @param Staged_Sites|null         $staged  Staged build storage.
	 */
	public function __construct( ?Starter_Site_Library $library = null, ?Staged_Sites $staged = null ) {
		$this->library = $library ?? new Starter_Site_Library();
		$this->staged  = $staged ?? new Staged_Sites();
	}

	/**
	 * Package library.
	 *
	 * @return Starter_Site_Library
	 */
	public function library(): Starter_Site_Library {
		return $this->library;
	}

	/**
	 * Staged build storage.
	 *
	 * @return Staged_Sites
	 */
	public function staged(): Staged_Sites {
		return $this->staged;
	}

	/**
	 * Gallery summaries of every starter site.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_sites(): array {
		$out = [];

		foreach ( $this->library->get_all() as $site ) {
			$manifest = $site->get_manifest();
			$out[]    = [
				'slug'        => $site->get_slug(),
				'name'        => $site->get_name(),
				'category'    => (string) ( $manifest['category'] ?? '' ),
				'description' => (string) ( $manifest['description'] ?? '' ),
				'version'     => (string) ( $manifest['version'] ?? '' ),
				'pages'       => array_column( $site->get_pages(), 'title' ),
				'screenshots' => $site->get_screenshot_urls(),
			];
		}

		return $out;
	}

	/**
	 * Everything an editor (human or MCP client) needs about one starter site.
	 *
	 * @param string $slug Starter site slug.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_site_details( string $slug ) {
		$site = $this->get_site( $slug );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$summary = array_values( array_filter( $this->list_sites(), static fn( $s ) => $s['slug'] === $site->get_slug() ) )[0];
		$schema  = $site->get_slots_schema();

		return $summary + [
			'page_list'  => array_map(
				static fn( $p ) => [
					'slug'          => $p['slug'],
					'title'         => $p['title'],
					'is_front_page' => $p['is_front_page'],
					'sections'      => array_map(
						static fn( $sec ) => [
							'id'    => $sec['id'],
							'label' => $sec['label'],
						],
						$p['sections']
					),
				],
				$site->get_pages()
			),
			'groups'         => $schema['groups'],
			'slots'          => $this->format_slots( $site, $schema['slots'] ),
			'palettes'       => $site->get_palettes(),
			'font_pairs'     => $site->get_font_pairs(),
			'navigation'     => $site->get_navigation(),
			'section_types'  => $this->section_types(),
			'design_options' => Site_Build::DESIGN_OPTIONS,
		];
	}

	/**
	 * Section types users can add, without their slot definitions.
	 *
	 * @return array<int, array{type: string, label: string, description: string}>
	 */
	public function section_types(): array {
		return array_values(
			array_map(
				static fn( $t ) => [
					'type'        => $t['type'],
					'label'       => $t['label'],
					'description' => $t['description'],
				],
				$this->library->get_section_types()
			)
		);
	}

	/**
	 * The editor's view of the user's draft: the site details with the
	 * groups and slots of added sections, and the layout with labels.
	 *
	 * @param int $user_id User id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get_editor_schema( int $user_id ) {
		$staged = $this->staged->get( $user_id );
		if ( null === $staged ) {
			return $this->no_staged_error();
		}

		$details = $this->get_site_details( (string) ( $staged['state']['slug'] ?? '' ) );
		if ( is_wp_error( $details ) ) {
			return $details;
		}

		$site  = $this->library->get( $details['slug'] );
		$state = Site_Build::normalize( $site, $staged['state'] );
		$types = $this->library->get_section_types();

		$details['groups'] = Site_Build::groups( $site, $state );
		$details['slots']  = $this->format_slots( $site, array_values( Site_Build::slots( $site, $state ) ) );

		$labels = [];
		foreach ( $site->get_pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				$labels[ $page['slug'] ][ $section['id'] ] = $section['label'];
			}
		}

		$layout = [];
		foreach ( $state['layout'] as $page => $entries ) {
			foreach ( $entries as $entry ) {
				$entry['label']    = ! empty( $entry['type'] )
					? (string) ( $types[ $entry['type'] ]['label'] ?? $entry['type'] )
					: (string) ( $labels[ $page ][ $entry['id'] ] ?? $entry['id'] );
				$layout[ $page ][] = $entry;
			}
		}
		$details['layout'] = $layout;

		return $details;
	}

	/**
	 * Slots as the editor and MCP clients see them.
	 *
	 * @param Starter_Site                     $site  Package.
	 * @param array<int, array<string, mixed>> $slots Slot definitions.
	 * @return array<int, array<string, mixed>>
	 */
	private function format_slots( Starter_Site $site, array $slots ): array {
		$out = [];
		foreach ( $slots as $slot ) {
			$entry = [
				'id'      => (string) $slot['id'],
				'group'   => (string) $slot['group'],
				'type'    => (string) $slot['type'],
				'label'   => (string) ( $slot['label'] ?? $slot['id'] ),
				'default' => 'image' === $slot['type'] ? $site->asset_url( (string) $slot['default'] ) : (string) $slot['default'],
			];
			if ( isset( $slot['max'] ) ) {
				$entry['max'] = (int) $slot['max'];
			}
			if ( ! empty( $slot['hint'] ) ) {
				$entry['hint'] = (string) $slot['hint'];
			}
			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * The user's staged build, summarised for the editor.
	 *
	 * @param int $user_id User id.
	 * @return array<string, mixed>|null
	 */
	public function get_staged( int $user_id ): ?array {
		$staged = $this->staged->get( $user_id );
		if ( null === $staged ) {
			return null;
		}

		$site = $this->library->get( (string) ( $staged['state']['slug'] ?? '' ) );
		if ( null === $site ) {
			return null;
		}

		$state = Site_Build::normalize( $site, $staged['state'] );

		return [
			'slug'     => $site->get_slug(),
			'name'     => $site->get_name(),
			'state'    => $state,
			'colors'   => Site_Build::colors( $site, $state ),
			'images'   => $this->image_previews( $site, $state ),
			'edited'   => count( $state['values'] ),
			'modified' => $staged['modified'],
		];
	}

	/**
	 * Start a staged build from a starter site, replacing any existing one.
	 *
	 * @param int                  $user_id User id.
	 * @param string               $slug    Starter site slug.
	 * @param array<string, mixed> $changes Optional initial values, palette, font_pair, custom_color.
	 * @return array<string, mixed>|\WP_Error The staged summary.
	 */
	public function stage( int $user_id, string $slug, array $changes = [] ) {
		$site = $this->get_site( $slug );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$state = Site_Build::apply_changes( $site, Site_Build::default_state( $site ), $changes );

		return $this->save( $user_id, $site, $state );
	}

	/**
	 * Start a staged build from a complete saved state, such as the one an
	 * earlier apply used.
	 *
	 * @param int                  $user_id User id.
	 * @param string               $slug    Starter site slug.
	 * @param array<string, mixed> $state   Saved state.
	 * @return array<string, mixed>|\WP_Error The staged summary.
	 */
	public function stage_state( int $user_id, string $slug, array $state ) {
		$site = $this->get_site( $slug );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		return $this->save( $user_id, $site, Site_Build::normalize( $site, $state ) );
	}

	/**
	 * Change the user's staged build.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $changes values, reset, palette, font_pair, custom_color.
	 * @return array<string, mixed>|\WP_Error The staged summary.
	 */
	public function update( int $user_id, array $changes ) {
		$staged = $this->staged->get( $user_id );
		if ( null === $staged ) {
			return $this->no_staged_error();
		}

		$site = $this->get_site( (string) ( $staged['state']['slug'] ?? '' ) );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$state = Site_Build::apply_changes( $site, $staged['state'], $changes );

		return $this->save( $user_id, $site, $state );
	}

	/**
	 * Throw the staged build away.
	 *
	 * @param int $user_id User id.
	 * @return bool True if there was one.
	 */
	public function discard( int $user_id ): bool {
		return $this->staged->delete( $user_id );
	}

	/**
	 * Resolve the user's staged build.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $context Site_Build::resolve() context.
	 * @return array{site: Starter_Site, state: array<string, mixed>, build: array<string, mixed>}|\WP_Error
	 */
	public function resolve_staged( int $user_id, array $context = [] ) {
		$staged = $this->staged->get( $user_id );
		if ( null === $staged ) {
			return $this->no_staged_error();
		}

		$site = $this->get_site( (string) ( $staged['state']['slug'] ?? '' ) );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$state = Site_Build::normalize( $site, $staged['state'] );

		return [
			'site'  => $site,
			'state' => $state,
			'build' => Site_Build::resolve( $site, $state, $context ),
		];
	}

	/**
	 * Look up a starter site.
	 *
	 * @param string $slug Slug.
	 * @return Starter_Site|\WP_Error
	 */
	public function get_site( string $slug ) {
		$site = $this->library->get( $slug );

		if ( null === $site ) {
			return new \WP_Error(
				'mysitehand_unknown_starter_site',
				/* translators: %s: starter site slug */
				sprintf( __( 'There is no starter site called "%s".', 'my-site-hand' ), $slug ),
				[ 'status' => 404 ]
			);
		}

		return $site;
	}

	/**
	 * Error for operations that need a staged build when there is none.
	 *
	 * @return \WP_Error
	 */
	public function no_staged_error(): \WP_Error {
		return new \WP_Error(
			'mysitehand_nothing_staged',
			__( 'There is no staged site yet. Choose a starter site first.', 'my-site-hand' ),
			[ 'status' => 404 ]
		);
	}

	/**
	 * Resolve and store.
	 *
	 * @param int                  $user_id User id.
	 * @param Starter_Site         $site    Package.
	 * @param array<string, mixed> $state   State.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function save( int $user_id, Starter_Site $site, array $state ) {
		$saved = $this->staged->save( $user_id, $state, Site_Build::resolve( $site, $state ) );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return (array) $this->get_staged( $user_id );
	}

	/**
	 * Thumbnail URL for every image slot that holds a library image.
	 *
	 * @param Starter_Site         $site  Package.
	 * @param array<string, mixed> $state State.
	 * @return array<string, string> Slot id => URL.
	 */
	private function image_previews( Starter_Site $site, array $state ): array {
		$out = [];
		foreach ( $state['values'] as $id => $value ) {
			if ( 'image' === ( Site_Build::slots( $site, $state )[ $id ]['type'] ?? '' ) ) {
				$url = wp_get_attachment_image_url( (int) $value, 'medium' );
				if ( $url ) {
					$out[ $id ] = $url;
				}
			}
		}
		return $out;
	}
}
