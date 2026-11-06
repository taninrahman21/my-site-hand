<?php
/**
 * Turns a starter site plus the user's choices into a complete build.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Site_Build class.
 *
 * Pure: reads the package and the state, writes nothing. The staged build,
 * the live preview, Apply and the MCP abilities all resolve through here, so
 * what the user previews is exactly what Apply writes.
 *
 * State shape:
 *   slug          starter site slug
 *   values        slot id => value (only what the user changed)
 *   palette       preset palette id
 *   font_pair     preset font pair id
 *   custom_color  brand hex, or '' to use the preset palette
 *   design        mode, corners, spacing and an optional full custom palette
 *   layout        page slug => ordered sections: { id, hidden } for the
 *                 page's own sections, { id: "x<n>", type, hidden } for
 *                 sections added from the shared library
 *
 * Design and layout are choices from fixed lists, never markup. Added
 * sections come from the bundled library, and their text goes through the
 * same slots and escaping as every other field.
 */
class Site_Build {

	/**
	 * Design choices and their allowed values. The first is the default.
	 *
	 * "site" means "as the starter site was designed".
	 *
	 * @var array<string, array<int, string>>
	 */
	public const DESIGN_OPTIONS = [
		'mode'    => [ 'auto', 'light', 'dark' ],
		'corners' => [ 'site', 'square', 'soft', 'rounded', 'pill' ],
		'spacing' => [ 'site', 'compact', 'comfortable', 'airy' ],
	];

	/**
	 * Most sections a user can add to one page.
	 *
	 * @var int
	 */
	public const MAX_ADDED_SECTIONS = 8;

	/**
	 * Shared section library.
	 *
	 * @var Starter_Site_Library|null
	 */
	private static ?Starter_Site_Library $library = null;

	/**
	 * The section library (shared, read-only).
	 *
	 * @return Starter_Site_Library
	 */
	public static function library(): Starter_Site_Library {
		if ( null === self::$library ) {
			self::$library = new Starter_Site_Library();
		}
		return self::$library;
	}

	/**
	 * Fresh state for a starter site: nothing changed, first palette and font pair.
	 *
	 * @param Starter_Site $site Package.
	 * @return array<string, mixed>
	 */
	public static function default_state( Starter_Site $site ): array {
		return [
			'slug'         => $site->get_slug(),
			'values'       => [],
			'palette'      => (string) ( $site->get_palettes()[0]['id'] ?? '' ),
			'font_pair'    => (string) ( $site->get_font_pairs()[0]['id'] ?? '' ),
			'custom_color' => '',
			'design'       => self::default_design(),
			'layout'       => self::default_layout( $site ),
			'based_on'     => '',
		];
	}

	/**
	 * Default design: everything as the starter site was made.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_design(): array {
		$design = [];
		foreach ( self::DESIGN_OPTIONS as $key => $options ) {
			$design[ $key ] = $options[0];
		}
		$design['palette'] = null;
		return $design;
	}

	/**
	 * Default layout: every page's own sections, in order, all visible.
	 *
	 * @param Starter_Site $site Package.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function default_layout( Starter_Site $site ): array {
		$layout = [];
		foreach ( $site->get_pages() as $page ) {
			$layout[ $page['slug'] ] = array_map(
				static fn( $section ) => [
					'id'     => $section['id'],
					'hidden' => false,
				],
				$page['sections']
			);
		}
		return $layout;
	}

	/**
	 * Repair a layout: known sections only, each once, added sections only
	 * of known library types, new ones given ids. A page section left out is
	 * put back at the end, so nothing the package ships can be lost.
	 *
	 * @param Starter_Site         $site   Package.
	 * @param array<string, mixed> $layout Layout.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function normalize_layout( Starter_Site $site, array $layout ): array {
		$types = self::library()->get_section_types();
		$out   = [];

		foreach ( $site->get_pages() as $page ) {
			$own     = array_column( $page['sections'], 'id' );
			$entries = is_array( $layout[ $page['slug'] ] ?? null ) ? $layout[ $page['slug'] ] : null;

			if ( null === $entries ) {
				$out[ $page['slug'] ] = self::default_layout( $site )[ $page['slug'] ];
				continue;
			}

			$list  = [];
			$seen  = [];
			$added = 0;
			$next  = 1;

			foreach ( $entries as $entry ) {
				if ( is_array( $entry ) && preg_match( '/^x(\d{1,3})$/', (string) ( $entry['id'] ?? '' ), $m ) ) {
					$next = max( $next, (int) $m[1] + 1 );
				}
			}

			foreach ( $entries as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$id     = sanitize_key( (string) ( $entry['id'] ?? '' ) );
				$hidden = ! empty( $entry['hidden'] );
				$type   = sanitize_key( (string) ( $entry['type'] ?? '' ) );

				if ( '' === $type && in_array( $id, $own, true ) && ! isset( $seen[ $id ] ) ) {
					$list[]      = [
						'id'     => $id,
						'hidden' => $hidden,
					];
					$seen[ $id ] = true;
				} elseif ( '' !== $type && isset( $types[ $type ] ) && $added < self::MAX_ADDED_SECTIONS ) {
					if ( ! preg_match( '/^x\d{1,3}$/', $id ) || isset( $seen[ $id ] ) ) {
						$id = 'x' . ( $next++ );
					}
					$list[]      = [
						'id'     => $id,
						'type'   => $type,
						'hidden' => $hidden,
					];
					$seen[ $id ] = true;
					++$added;
				}
			}

			foreach ( $own as $id ) {
				if ( ! isset( $seen[ $id ] ) ) {
					$list[] = [
						'id'     => $id,
						'hidden' => false,
					];
				}
			}

			$out[ $page['slug'] ] = $list;
		}

		return $out;
	}

	/**
	 * Repair a design: allowed values only, palette only if all seven
	 * colours are valid (and then fixed until it passes WCAG AA).
	 *
	 * @param array<string, mixed> $design Design.
	 * @return array<string, mixed>
	 */
	public static function normalize_design( array $design ): array {
		$out = self::default_design();

		foreach ( self::DESIGN_OPTIONS as $key => $options ) {
			if ( in_array( $design[ $key ] ?? null, $options, true ) ) {
				$out[ $key ] = $design[ $key ];
			}
		}

		if ( is_array( $design['palette'] ?? null ) ) {
			$palette = [];
			foreach ( Color_Contrast::PALETTE_SLUGS as $slug ) {
				$hex = sanitize_hex_color( (string) ( $design['palette'][ $slug ] ?? '' ) );
				if ( ! $hex ) {
					$palette = null;
					break;
				}
				$palette[ $slug ] = strtolower( $hex );
			}
			$out['palette'] = null === $palette ? null : Color_Contrast::fix_palette( $palette );
		}

		return $out;
	}

	/**
	 * Every slot: the package's own plus those of sections added from the
	 * library, with the library prefix ("s.x") rewritten to "<page>.<id>".
	 *
	 * @param Starter_Site         $site  Package.
	 * @param array<string, mixed> $state State (layout is read).
	 * @return array<string, array<string, mixed>>
	 */
	public static function slots( Starter_Site $site, array $state ): array {
		$slots = $site->get_slots();
		$types = self::library()->get_section_types();

		foreach ( (array) ( $state['layout'] ?? [] ) as $page => $entries ) {
			foreach ( (array) $entries as $entry ) {
				if ( empty( $entry['type'] ) || ! isset( $types[ $entry['type'] ] ) ) {
					continue;
				}
				foreach ( $types[ $entry['type'] ]['slots'] as $slot ) {
					$slot['id']               = self::instance_id( (string) $slot['id'], (string) $page, (string) $entry['id'] );
					$slot['group']            = $page . '/' . $entry['id'];
					$slots[ $slot['id'] ]     = $slot;
				}
			}
		}

		return $slots;
	}

	/**
	 * Editor groups, including those of added sections.
	 *
	 * @param Starter_Site         $site  Package.
	 * @param array<string, mixed> $state State.
	 * @return array<int, array<string, mixed>>
	 */
	public static function groups( Starter_Site $site, array $state ): array {
		$groups = $site->get_slots_schema()['groups'];
		$types  = self::library()->get_section_types();

		foreach ( (array) ( $state['layout'] ?? [] ) as $page => $entries ) {
			foreach ( (array) $entries as $entry ) {
				if ( ! empty( $entry['type'] ) && isset( $types[ $entry['type'] ] ) ) {
					$groups[] = [
						'id'    => $page . '/' . $entry['id'],
						'page'  => (string) $page,
						'label' => $types[ $entry['type'] ]['label'],
						'added' => true,
					];
				}
			}
		}

		return $groups;
	}

	/**
	 * Rewrite a library slot id or token body for one added section.
	 *
	 * @param string $id   "s.x.heading" or "image:s.x".
	 * @param string $page Page slug.
	 * @param string $inst Instance id, e.g. "x3".
	 * @return string
	 */
	public static function instance_id( string $id, string $page, string $inst ): string {
		if ( str_starts_with( $id, 'image:s.x' ) ) {
			return 'image:' . $page . '.' . $inst . substr( $id, 9 );
		}
		if ( str_starts_with( $id, 's.x' ) ) {
			return $page . '.' . $inst . substr( $id, 3 );
		}
		return $id;
	}

	/**
	 * Merge a change into a state and sanitize the result.
	 *
	 * Unknown slot ids, palettes and font pairs are dropped. A value equal to
	 * the slot default is dropped too, so "edited" always means "different".
	 *
	 * @param Starter_Site         $site    Package.
	 * @param array<string, mixed> $state   Current state.
	 * @param array<string, mixed> $changes Keys: values (merged), reset (slot ids), palette, font_pair, custom_color.
	 * @return array<string, mixed>
	 */
	public static function apply_changes( Starter_Site $site, array $state, array $changes ): array {
		// Layout first: added sections bring their own slots.
		if ( isset( $changes['layout'] ) && is_array( $changes['layout'] ) ) {
			$layout = is_array( $state['layout'] ?? null ) ? $state['layout'] : [];
			foreach ( $changes['layout'] as $page => $entries ) {
				$layout[ (string) $page ] = $entries;
			}
			$state['layout'] = self::normalize_layout( $site, $layout );
		}

		if ( isset( $changes['design'] ) && is_array( $changes['design'] ) ) {
			$state['design'] = self::normalize_design( array_merge( (array) ( $state['design'] ?? [] ), $changes['design'] ) );
		}

		$slots  = self::slots( $site, $state );
		$values = is_array( $state['values'] ?? null ) ? $state['values'] : [];

		if ( isset( $changes['values'] ) && is_array( $changes['values'] ) ) {
			foreach ( $changes['values'] as $id => $value ) {
				$id = (string) $id;
				if ( ! isset( $slots[ $id ] ) ) {
					continue;
				}

				$clean   = Slot_Renderer::sanitize_value( $slots[ $id ], $value );
				$default = Slot_Renderer::sanitize_value( $slots[ $id ], $slots[ $id ]['default'] ?? '' );

				if ( null === $clean || ( 'image' !== ( $slots[ $id ]['type'] ?? '' ) && $clean === $default ) ) {
					unset( $values[ $id ] );
				} else {
					$values[ $id ] = $clean;
				}
			}
		}

		foreach ( (array) ( $changes['reset'] ?? [] ) as $id ) {
			unset( $values[ (string) $id ] );
		}

		$state['values'] = Slot_Renderer::sanitize_values( $slots, $values );

		if ( isset( $changes['palette'] ) && self::find( $site->get_palettes(), (string) $changes['palette'] ) ) {
			$state['palette']      = (string) $changes['palette'];
			$state['custom_color'] = '';
			if ( ! isset( $changes['design']['palette'] ) ) {
				$state['design']['palette'] = null;
			}
		}

		if ( isset( $changes['font_pair'] ) && self::find( $site->get_font_pairs(), (string) $changes['font_pair'] ) ) {
			$state['font_pair'] = (string) $changes['font_pair'];
		}

		if ( array_key_exists( 'custom_color', $changes ) ) {
			$state['custom_color'] = (string) sanitize_hex_color( (string) $changes['custom_color'] );
			if ( '' !== $state['custom_color'] && ! isset( $changes['design']['palette'] ) ) {
				$state['design']['palette'] = null;
			}
		}

		return self::normalize( $site, $state );
	}

	/**
	 * Fill in and repair a stored state.
	 *
	 * @param Starter_Site         $site  Package.
	 * @param array<string, mixed> $state Stored state.
	 * @return array<string, mixed>
	 */
	public static function normalize( Starter_Site $site, array $state ): array {
		$default = self::default_state( $site );
		$layout  = self::normalize_layout( $site, is_array( $state['layout'] ?? null ) ? $state['layout'] : [] );
		$design  = self::normalize_design( is_array( $state['design'] ?? null ) ? $state['design'] : [] );
		$slots   = self::slots( $site, [ 'layout' => $layout ] );

		$state = [
			'slug'         => $site->get_slug(),
			'values'       => Slot_Renderer::sanitize_values( $slots, is_array( $state['values'] ?? null ) ? $state['values'] : [] ),
			'palette'      => self::find( $site->get_palettes(), (string) ( $state['palette'] ?? '' ) ) ? (string) $state['palette'] : $default['palette'],
			'font_pair'    => self::find( $site->get_font_pairs(), (string) ( $state['font_pair'] ?? '' ) ) ? (string) $state['font_pair'] : $default['font_pair'],
			'custom_color' => (string) sanitize_hex_color( (string) ( $state['custom_color'] ?? '' ) ),
			'design'       => $design,
			'layout'       => $layout,
			// The applied build this draft continues, set only by the server.
			'based_on'     => wp_is_uuid( (string) ( $state['based_on'] ?? '' ) ) ? (string) $state['based_on'] : '',
		];

		return $state;
	}

	/**
	 * The colours in use: the custom brand palette, or the chosen preset.
	 *
	 * @param Starter_Site         $site  Package.
	 * @param array<string, mixed> $state State.
	 * @return array<string, string>
	 */
	public static function colors( Starter_Site $site, array $state ): array {
		$colors = null;

		if ( ! empty( $state['design']['palette'] ) && is_array( $state['design']['palette'] ) ) {
			$colors = $state['design']['palette'];
		} elseif ( ! empty( $state['custom_color'] ) ) {
			$colors = Color_Contrast::palette_from_brand( (string) $state['custom_color'] );
		}

		if ( null === $colors ) {
			$palette = self::find( $site->get_palettes(), (string) ( $state['palette'] ?? '' ) );
			$colors  = (array) ( $palette['colors'] ?? ( $site->get_palettes()[0]['colors'] ?? [] ) );
		}

		// Light or dark mode re-derives the palette from its primary colour.
		// Colours the user picked one by one are used as they are.
		$mode    = empty( $state['design']['palette'] ) ? (string) ( $state['design']['mode'] ?? 'auto' ) : 'auto';
		$is_dark = Color_Contrast::luminance( (string) ( $colors['base'] ?? '#ffffff' ) ) < 0.5;

		if ( 'dark' === $mode && ! $is_dark ) {
			$colors = Color_Contrast::palette_from_brand( (string) $colors['primary'], 'dark' ) ?? $colors;
		} elseif ( 'light' === $mode && $is_dark ) {
			$colors = Color_Contrast::palette_from_brand( (string) $colors['primary'], 'light' ) ?? $colors;
		}

		return $colors;
	}

	/**
	 * Resolve everything.
	 *
	 * @param Starter_Site         $site    Package.
	 * @param array<string, mixed> $state   State.
	 * @param array<string, mixed> $context {
	 *     How links and media resolve.
	 *
	 *     @type array<string, string> $page_urls Page slug => URL.
	 *     @type string                $nav_mode  'links' (inline links, for preview) or 'ref'.
	 *     @type int                   $nav_ref   wp_navigation post id, for 'ref'.
	 *     @type array<string, int>    $images    Image slot id => attachment id, overriding state.
	 * }
	 * @return array<string, mixed>
	 */
	public static function resolve( Starter_Site $site, array $state, array $context = [] ): array {
		$state  = self::normalize( $site, $state );
		$slots  = self::slots( $site, $state );
		$values = $state['values'];

		foreach ( (array) ( $context['images'] ?? [] ) as $id => $attachment_id ) {
			$values[ (string) $id ] = (int) $attachment_id;
		}

		$page_urls = (array) ( $context['page_urls'] ?? [] );
		foreach ( $site->get_pages() as $page ) {
			if ( ! isset( $page_urls[ $page['slug'] ] ) ) {
				$page_urls[ $page['slug'] ] = home_url( '/' . $page['slug'] . '/' );
			}
		}
		// "page:-last" is the library's way to say "your contact page": the
		// last page of every starter site.
		$last_page              = end( $page_urls );
		$page_urls['-last']     = false === $last_page ? home_url( '/' ) : $last_page;

		$render_context = [
			'asset_url' => [ $site, 'asset_url' ],
			'page_urls' => $page_urls,
		];

		// Attachment URL => id, so image blocks can carry their id.
		$attachments = [];
		foreach ( $values as $id => $value ) {
			if ( 'image' === ( $slots[ $id ]['type'] ?? '' ) && is_int( $value ) ) {
				$url = wp_get_attachment_image_url( $value, 'large' );
				if ( $url ) {
					$attachments[ $url ] = $value;
				}
			}
		}

		$render_markup = static function ( string $markup ) use ( $slots, $values, $render_context, $attachments ): string {
			$markup = Slot_Renderer::render( $markup, $slots, $values, $render_context );
			return $attachments ? self::link_attachments( $markup, $attachments ) : $markup;
		};
		$render        = static fn( string $relative ): string => $render_markup( (string) $site->read_file( $relative ) );

		$pages = [];
		foreach ( $site->get_pages() as $page ) {
			$files = array_column( $page['sections'], 'file', 'id' );
			$parts = [];

			foreach ( $state['layout'][ $page['slug'] ] ?? [] as $entry ) {
				if ( ! empty( $entry['hidden'] ) ) {
					continue;
				}
				if ( ! empty( $entry['type'] ) ) {
					$markup = (string) self::library()->get_section_markup( (string) $entry['type'] );
					$markup = (string) preg_replace_callback(
						'/\{\{\s*((?:image:)?s\.x[a-z0-9_.:-]*)\s*\}\}/i',
						static fn( $m ) => '{{' . self::instance_id( strtolower( $m[1] ), $page['slug'], (string) $entry['id'] ) . '}}',
						$markup
					);
					$parts[] = $render_markup( $markup );
				} elseif ( isset( $files[ $entry['id'] ] ) ) {
					$parts[] = $render( $files[ $entry['id'] ] );
				}
			}

			$pages[] = [
				'slug'          => $page['slug'],
				'title'         => $page['title'],
				'is_front_page' => $page['is_front_page'],
				'template'      => $page['template'],
				'content'       => implode( "\n\n", $parts ),
			];
		}

		$navigation = [];
		foreach ( $site->get_navigation() as $item ) {
			$navigation[] = [
				'label' => $item['label'],
				'page'  => $item['page'],
				'url'   => (string) ( $page_urls[ $item['page'] ] ?? '' ),
			];
		}

		$parts = [];
		foreach ( $site->get_template_parts() as $area => $file ) {
			$parts[ $area ] = self::set_navigation(
				$render( $file ),
				$navigation,
				(string) ( $context['nav_mode'] ?? 'links' ),
				(int) ( $context['nav_ref'] ?? 0 )
			);
		}

		$templates = [];
		foreach ( $site->get_templates() as $slug => $template ) {
			$templates[ $slug ] = [
				'title'   => $template['title'],
				'content' => $render( $template['file'] ),
			];
		}

		$font_pair = self::find( $site->get_font_pairs(), $state['font_pair'] ) ?? [];

		return [
			'slug'       => $site->get_slug(),
			'name'       => $site->get_name(),
			'version'    => (string) ( $site->get_manifest()['version'] ?? '' ),
			'settings'   => [
				'blogname'        => (string) ( $values['site.title'] ?? $slots['site.title']['default'] ?? get_bloginfo( 'name' ) ),
				'blogdescription' => (string) ( $values['site.tagline'] ?? $slots['site.tagline']['default'] ?? '' ),
			],
			'pages'      => $pages,
			'parts'      => $parts,
			'templates'  => $templates,
			'styles'     => Global_Styles_Builder::build( $site, self::colors( $site, $state ), $font_pair, $state['design'] ),
			'navigation' => $navigation,
		];
	}

	/**
	 * Fill the header's navigation block.
	 *
	 * The starter markup carries one bare core/navigation. Bare is never
	 * rendered: a navigation block with neither a ref nor links falls back
	 * to whatever menu exists, and can even create one.
	 *
	 * @param string                                                      $markup     Part markup.
	 * @param array<int, array{label: string, page: string, url: string}> $navigation Items.
	 * @param string                                                      $mode       'links' or 'ref'.
	 * @param int                                                         $ref        wp_navigation post id for 'ref'.
	 * @return string
	 */
	private static function set_navigation( string $markup, array $navigation, string $mode, int $ref ): string {
		if ( ! str_contains( $markup, '<!-- wp:navigation' ) ) {
			return $markup;
		}

		$blocks = parse_blocks( $markup );

		$walk = static function ( array &$blocks ) use ( &$walk, $navigation, $mode, $ref ): void {
			foreach ( $blocks as &$block ) {
				if ( 'core/navigation' === $block['blockName'] ) {
					if ( 'ref' === $mode && $ref ) {
						$block['attrs']['ref'] = $ref;
						$block['innerBlocks']  = [];
						$block['innerContent'] = [];
					} else {
						unset( $block['attrs']['ref'] );
						$block['innerBlocks']  = array_map( [ self::class, 'navigation_link' ], $navigation );
						$block['innerHTML']    = '';
						$block['innerContent'] = array_fill( 0, count( $navigation ), null );
					}
				} elseif ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};

		$walk( $blocks );

		return serialize_blocks( $blocks );
	}

	/**
	 * A core/navigation-link block for one item.
	 *
	 * @param array{label: string, page: string, url: string} $item Item.
	 * @return array<string, mixed>
	 */
	public static function navigation_link( array $item ): array {
		$attrs = [
			'label'          => $item['label'],
			'url'            => $item['url'],
			'kind'           => 'custom',
			'isTopLevelLink' => true,
		];

		if ( ! empty( $item['id'] ) ) {
			$attrs['type'] = 'page';
			$attrs['id']   = (int) $item['id'];
			$attrs['kind'] = 'post-type';
		}

		return [
			'blockName'    => 'core/navigation-link',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Give core/image blocks that show a library image their attachment id.
	 *
	 * With the id, the editor offers the media library tools (replace, edit,
	 * sizes) exactly as if the image had been inserted by hand.
	 *
	 * @param string             $markup      Block markup.
	 * @param array<string, int> $attachments Image URL => attachment id.
	 * @return string
	 */
	public static function link_attachments( string $markup, array $attachments ): string {
		$blocks = parse_blocks( $markup );

		$walk = static function ( array &$blocks ) use ( &$walk, $attachments ): void {
			foreach ( $blocks as &$block ) {
				if ( 'core/image' === $block['blockName'] && 1 === count( $block['innerContent'] ) ) {
					$html      = (string) $block['innerContent'][0];
					$processor = new \WP_HTML_Tag_Processor( $html );

					if ( $processor->next_tag( 'img' ) ) {
						$src = html_entity_decode( (string) $processor->get_attribute( 'src' ) );
						if ( isset( $attachments[ $src ] ) ) {
							$id = $attachments[ $src ];
							$processor->add_class( 'wp-image-' . $id );
							$block['attrs']        = [ 'id' => $id ] + $block['attrs'];
							$block['innerHTML']    = $processor->get_updated_html();
							$block['innerContent'] = [ $block['innerHTML'] ];
						}
					}
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'] );
				}
			}
		};

		$walk( $blocks );

		return serialize_blocks( $blocks );
	}

	/**
	 * Find an item by id in a list of palettes or font pairs.
	 *
	 * @param array<int, array<string, mixed>> $items List.
	 * @param string                           $id    Id.
	 * @return array<string, mixed>|null
	 */
	public static function find( array $items, string $id ): ?array {
		foreach ( $items as $item ) {
			if ( ( $item['id'] ?? null ) === $id ) {
				return $item;
			}
		}
		return null;
	}
}
