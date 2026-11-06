<?php
/**
 * Checks a starter site package before it ships.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Starter_Site_Validator class.
 *
 * Run through `wp my-site-hand validate-starter-sites`. Errors mean the
 * package must not ship; warnings are worth a look but do not block.
 *
 * Messages are for developers, so they are not translated.
 */
class Starter_Site_Validator {

	/**
	 * Slots that map to site options rather than to tokens in the markup.
	 *
	 * @var array<int, string>
	 */
	public const OPTION_SLOTS = [ 'site.title', 'site.tagline' ];

	/**
	 * Preset slugs every starter site defines (see common.py), and so the
	 * only ones shared library sections may use.
	 *
	 * @var array<string, array<int, string>>
	 */
	public const SHARED_PRESETS = [
		'color'       => Color_Contrast::PALETTE_SLUGS,
		'font-size'   => [ 'small', 'medium', 'large', 'x-large', 'xx-large', 'huge' ],
		'spacing'     => [ '10', '20', '30', '40', '50', '60', '70', '80' ],
		'font-family' => [ 'heading', 'body' ],
	];

	/**
	 * Largest bundled image, in bytes, before it is an error.
	 *
	 * @var int
	 */
	private const IMAGE_HARD_LIMIT = 250 * KB_IN_BYTES;

	/**
	 * Image size that earns a warning.
	 *
	 * @var int
	 */
	private const IMAGE_SOFT_LIMIT = 200 * KB_IN_BYTES;

	/**
	 * Errors for the package being validated.
	 *
	 * @var array<int, string>
	 */
	private array $errors = [];

	/**
	 * Warnings for the package being validated.
	 *
	 * @var array<int, string>
	 */
	private array $warnings = [];

	/**
	 * Validate one package.
	 *
	 * @param Starter_Site $site Package.
	 * @return array{errors: array<int, string>, warnings: array<int, string>}
	 */
	public function validate( Starter_Site $site ): array {
		$this->errors   = [];
		$this->warnings = [];

		$this->check_manifest( $site );

		$palette_slugs = $this->check_palettes( $site );
		$this->check_font_pairs( $site );
		$presets = $this->check_styles( $site, $palette_slugs );
		$slots   = $this->check_slots( $site );

		$files = $this->markup_files( $site );
		$this->check_tokens( $site, $files, $slots );
		$this->check_markup( $site, $files, $presets );
		$this->check_assets( $site );

		return [
			'errors'   => $this->errors,
			'warnings' => $this->warnings,
		];
	}

	/**
	 * Render every markup file with default values.
	 *
	 * @param Starter_Site $site Package.
	 * @return array<string, string> Relative path => resolved markup.
	 */
	public static function render_defaults( Starter_Site $site ): array {
		$page_urls = [];
		foreach ( $site->get_pages() as $page ) {
			$page_urls[ $page['slug'] ] = home_url( '/' . $page['slug'] . '/' );
		}

		$context = [
			'asset_url' => [ $site, 'asset_url' ],
			'page_urls' => $page_urls,
		];

		$out = [];
		foreach ( self::list_markup_files( $site ) as $relative ) {
			$markup = $site->read_file( $relative );
			if ( null !== $markup ) {
				$out[ $relative ] = Slot_Renderer::render( $markup, $site->get_slots(), [], $context );
			}
		}

		return $out;
	}

	/**
	 * Validate the shared section library.
	 *
	 * @param Starter_Site_Library $library Library.
	 * @return array{errors: array<int, string>, warnings: array<int, string>}
	 */
	public function validate_library( Starter_Site_Library $library ): array {
		$errors = [];
		$types  = $library->get_section_types();

		if ( ! $types ) {
			$errors[] = 'library.json: no section types';
		}

		foreach ( $types as $type => $def ) {
			$markup = $library->get_section_markup( $type );
			if ( null === $markup ) {
				$errors[] = "$type: section file not found ({$def['file']})";
				continue;
			}

			$slots = [];
			foreach ( $def['slots'] as $slot ) {
				$id = (string) ( $slot['id'] ?? '' );
				if ( ! str_starts_with( $id, 's.x' ) && ! str_starts_with( $id, 'image:s.x' ) ) {
					$errors[] = "$type: slot \"$id\" must start with s.x or image:s.x";
				}
				if ( '' === trim( (string) ( $slot['default'] ?? '' ) ) ) {
					$errors[] = "$type: slot \"$id\" needs a default";
				}
				if ( 'image' === ( $slot['type'] ?? '' ) && null === Starter_Site::safe_path( trailingslashit( wp_normalize_path( Starter_Site_Library::get_base_dir() . Starter_Site_Library::SECTION_LIBRARY ) ), (string) preg_replace( '/^lib:/', '', (string) $slot['default'] ) ) ) {
					$errors[] = "$type: image \"{$slot['default']}\" not found";
				}
				$slots[ $id ] = $slot;
			}

			$used = [];
			foreach ( Slot_Renderer::token_contexts( $markup ) as $token ) {
				$name = $token['name'];
				if ( 'page:-last' === $name ) {
					continue;
				}
				if ( ! isset( $slots[ $name ] ) ) {
					$errors[] = "$type: token {{{$name}}} has no slot";
					continue;
				}
				$used[ $name ] = true;
				if ( ! in_array( $token['context'], Slot_Renderer::TYPE_CONTEXTS[ $slots[ $name ]['type'] ] ?? [], true ) ) {
					$errors[] = "$type: {{{$name}}} is used where its type is not allowed";
				}
			}
			foreach ( array_keys( $slots ) as $id ) {
				if ( ! isset( $used[ $id ] ) ) {
					$errors[] = "$type: slot \"$id\" is not used";
				}
			}

			if ( preg_match( '/<\?(php|=)?|<script\b|\son[a-z]+\s*=/i', $markup ) ) {
				$errors[] = "$type: contains PHP, a script tag or an inline event handler";
			}

			// Library sections may only use the presets every starter site
			// shares, so they look right in all of them.
			$names        = [];
			$this->errors = [];
			$this->walk_blocks( parse_blocks( $markup ), $type, 'raw', $names, self::SHARED_PRESETS );
			$errors = array_merge( $errors, $this->errors );
		}

		return [
			'errors'   => $errors,
			'warnings' => [],
		];
	}

	/**
	 * Manifest structure and file references.
	 *
	 * @param Starter_Site $site Package.
	 * @return void
	 */
	private function check_manifest( Starter_Site $site ): void {
		$manifest = $site->get_manifest();

		foreach ( [ 'slug', 'name', 'category', 'description', 'version', 'pages', 'template_parts', 'global_styles', 'palettes', 'font_pairs', 'slots_schema', 'navigation' ] as $key ) {
			if ( ! isset( $manifest[ $key ] ) || '' === $manifest[ $key ] ) {
				$this->errors[] = "manifest.json: missing \"$key\"";
			}
		}

		if ( ( $manifest['slug'] ?? '' ) !== $site->get_slug() ) {
			$this->errors[] = 'manifest.json: "slug" must match the folder name (' . $site->get_slug() . ')';
		}

		foreach ( [ 'global_styles', 'palettes', 'font_pairs', 'slots_schema' ] as $key ) {
			$file = (string) ( $manifest[ $key ] ?? '' );
			if ( '' !== $file && null === $site->read_json( $file ) ) {
				$this->errors[] = "manifest.json: \"$key\" points to a missing or invalid JSON file ($file)";
			}
		}

		$pages      = $site->get_pages();
		$page_slugs = array_column( $pages, 'slug' );
		$templates  = $site->get_templates();

		if ( count( $pages ) < 1 ) {
			$this->errors[] = 'manifest.json: no pages';
		}
		if ( count( $page_slugs ) !== count( array_unique( $page_slugs ) ) ) {
			$this->errors[] = 'manifest.json: duplicate page slugs';
		}
		if ( 1 !== count( array_filter( array_column( $pages, 'is_front_page' ) ) ) ) {
			$this->errors[] = 'manifest.json: exactly one page must have "is_front_page": true';
		}

		foreach ( $site->get_pages() as $page ) {
			if ( ! $page['sections'] ) {
				$this->errors[] = "manifest.json: page \"{$page['slug']}\" has no sections";
			}
			$ids = array_column( $page['sections'], 'id' );
			if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
				$this->errors[] = "manifest.json: page \"{$page['slug']}\" has duplicate section ids";
			}
			foreach ( $page['sections'] as $section ) {
				if ( null === $site->resolve_path( $section['file'] ) ) {
					$this->errors[] = "manifest.json: page \"{$page['slug']}\" section \"{$section['id']}\" file not found ({$section['file']})";
				}
			}
			if ( ! empty( $page['template'] ) && ! isset( $templates[ $page['template'] ] ) ) {
				$this->errors[] = "manifest.json: page \"{$page['slug']}\" uses unknown template \"{$page['template']}\"";
			}
		}

		foreach ( $site->get_template_parts() as $area => $file ) {
			if ( null === $site->resolve_path( $file ) ) {
				$this->errors[] = "manifest.json: template part \"$area\" file not found ($file)";
			}
		}

		foreach ( $templates as $slug => $template ) {
			if ( null === $site->resolve_path( $template['file'] ) ) {
				$this->errors[] = "manifest.json: template \"$slug\" file not found ({$template['file']})";
			}
			if ( preg_match( '/^(page|single|archive|category|tag|author|taxonomy|front-page|home|index|search|404)(-|$)/', $slug ) ) {
				$this->errors[] = "manifest.json: template slug \"$slug\" collides with the template hierarchy; use a custom name";
			}
		}

		foreach ( $site->get_navigation() as $item ) {
			if ( ! in_array( $item['page'], $page_slugs, true ) ) {
				$this->errors[] = "manifest.json: navigation links to unknown page \"{$item['page']}\"";
			}
		}

		$screenshots = (array) ( $manifest['screenshots'] ?? [] );
		if ( ! $screenshots ) {
			$this->warnings[] = 'manifest.json: no screenshots yet (the gallery will show a placeholder)';
		}
		foreach ( $screenshots as $file ) {
			if ( null === $site->resolve_path( (string) $file ) ) {
				$this->errors[] = "manifest.json: screenshot not found ($file)";
			}
		}
	}

	/**
	 * Palettes: count, required slugs, valid colours, WCAG AA.
	 *
	 * @param Starter_Site $site Package.
	 * @return array<int, string> Palette colour slugs.
	 */
	private function check_palettes( Starter_Site $site ): array {
		$palettes = $site->get_palettes();
		$count    = count( $palettes );

		if ( $count < 5 || $count > 8 ) {
			$this->errors[] = "palettes.json: $count palettes; ship 5 to 8";
		}

		$ids = [];
		foreach ( $palettes as $palette ) {
			$id     = (string) ( $palette['id'] ?? '' );
			$colors = (array) ( $palette['colors'] ?? [] );
			$ids[]  = $id;

			if ( '' === $id || empty( $palette['name'] ) ) {
				$this->errors[] = 'palettes.json: every palette needs an id and a name';
			}

			foreach ( Color_Contrast::PALETTE_SLUGS as $slug ) {
				if ( ! isset( $colors[ $slug ] ) || null === Color_Contrast::hex_to_rgb( (string) $colors[ $slug ] ) ) {
					$this->errors[] = "palettes.json: \"$id\" is missing a valid hex colour for \"$slug\"";
				}
			}

			foreach ( Color_Contrast::failing_pairs( $colors ) as $fail ) {
				$this->errors[] = sprintf(
					'palettes.json: "%s" fails WCAG AA for %s text on %s (%.2f:1, needs %.1f:1)',
					$id,
					$fail['text'],
					$fail['background'],
					$fail['ratio'],
					Color_Contrast::AA_NORMAL
				);
			}
		}

		if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
			$this->errors[] = 'palettes.json: duplicate palette ids';
		}

		return Color_Contrast::PALETTE_SLUGS;
	}

	/**
	 * Font pairs: count, structure, and no remote font loading.
	 *
	 * @param Starter_Site $site Package.
	 * @return void
	 */
	private function check_font_pairs( Starter_Site $site ): void {
		$pairs = $site->get_font_pairs();
		$count = count( $pairs );

		if ( $count < 3 || $count > 5 ) {
			$this->errors[] = "fonts.json: $count font pairs; ship 3 to 5";
		}

		foreach ( $pairs as $pair ) {
			$id = (string) ( $pair['id'] ?? '' );
			foreach ( [ 'heading', 'body' ] as $role ) {
				if ( empty( $pair[ $role ]['fontFamily'] ) ) {
					$this->errors[] = "fonts.json: \"$id\" has no $role fontFamily";
				}
			}
		}

		$raw = (string) $site->read_file( (string) ( $site->get_manifest()['font_pairs'] ?? '' ) );
		if ( preg_match( '#(https?:)?//|@import|url\s*\(#i', $raw ) ) {
			$this->errors[] = 'fonts.json: references a remote font. Use system stacks or fonts bundled in the plugin.';
		}
	}

	/**
	 * Global styles: shape, and collect the preset slugs it defines.
	 *
	 * @param Starter_Site       $site          Package.
	 * @param array<int, string> $palette_slugs Colour slugs.
	 * @return array<string, array<int, string>> Preset type => slugs.
	 */
	private function check_styles( Starter_Site $site, array $palette_slugs ): array {
		$styles = $site->get_global_styles();

		if ( 3 !== (int) ( $styles['version'] ?? 0 ) ) {
			$this->errors[] = 'styles.json: "version" must be 3 (theme.json v3, WordPress 6.6+)';
		}
		if ( isset( $styles['settings']['color']['palette'] ) ) {
			$this->errors[] = 'styles.json: do not define a palette here; palettes come from palettes.json';
		}
		if ( isset( $styles['settings']['typography']['fontFamilies'] ) ) {
			$this->errors[] = 'styles.json: do not define font families here; they come from fonts.json';
		}

		$raw = (string) $site->read_file( (string) ( $site->get_manifest()['global_styles'] ?? '' ) );
		if ( preg_match( '#https?://|@import|url\s*\(#i', $raw ) ) {
			$this->errors[] = 'styles.json: must not reference remote files';
		}

		$presets = [
			'color'       => $palette_slugs,
			'font-size'   => array_column( (array) ( $styles['settings']['typography']['fontSizes'] ?? [] ), 'slug' ),
			'spacing'     => array_column( (array) ( $styles['settings']['spacing']['spacingSizes'] ?? [] ), 'slug' ),
			'font-family' => [ 'heading', 'body' ],
		];

		$this->check_preset_refs( 'styles.json', $styles['styles'] ?? [], $presets );

		return $presets;
	}

	/**
	 * Slots schema.
	 *
	 * @param Starter_Site $site Package.
	 * @return array<string, array<string, mixed>> Slots keyed by id.
	 */
	private function check_slots( Starter_Site $site ): array {
		$schema    = $site->get_slots_schema();
		$group_ids = array_column( $schema['groups'], 'id' );
		$seen      = [];
		$slots     = [];

		if ( ! $schema['slots'] ) {
			$this->errors[] = 'slots.json: no slots';
		}

		foreach ( $schema['slots'] as $slot ) {
			$id   = (string) ( $slot['id'] ?? '' );
			$type = (string) ( $slot['type'] ?? '' );

			if ( '' === $id ) {
				$this->errors[] = 'slots.json: a slot has no id';
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				$this->errors[] = "slots.json: duplicate slot id \"$id\"";
			}
			$seen[ $id ]  = true;
			$slots[ $id ] = $slot;

			if ( ! preg_match( '/^[a-z0-9_.:-]+$/', $id ) ) {
				$this->errors[] = "slots.json: \"$id\" may only use a-z, 0-9, dot, colon, dash and underscore";
			}
			if ( ! isset( Slot_Renderer::TYPE_CONTEXTS[ $type ] ) ) {
				$this->errors[] = "slots.json: \"$id\" has unknown type \"$type\"";
				continue;
			}
			if ( ( 'image' === $type ) !== str_starts_with( $id, 'image:' ) ) {
				$this->errors[] = "slots.json: \"$id\" image slots, and only image slots, use the \"image:\" prefix";
			}
			if ( ! in_array( (string) ( $slot['group'] ?? '' ), $group_ids, true ) ) {
				$this->errors[] = "slots.json: \"$id\" belongs to unknown group \"" . ( $slot['group'] ?? '' ) . '"';
			}
			if ( empty( $slot['label'] ) ) {
				$this->warnings[] = "slots.json: \"$id\" has no label";
			}

			$default = $slot['default'] ?? null;
			if ( ! is_string( $default ) || '' === trim( $default ) ) {
				$this->errors[] = "slots.json: \"$id\" needs a non-empty default";
				continue;
			}

			if ( in_array( $type, [ 'text', 'richtext' ], true ) ) {
				$max   = (int) ( $slot['max'] ?? 0 );
				$plain = 'richtext' === $type ? wp_strip_all_tags( $default ) : $default;

				if ( $max < 1 ) {
					$this->errors[] = "slots.json: \"$id\" needs a positive \"max\"";
				} elseif ( mb_strlen( $plain ) > $max ) {
					$this->errors[] = "slots.json: \"$id\" default is longer than its max ($max)";
				}

				if ( preg_match( '/lorem|ipsum|dolor sit/i', $default ) ) {
					$this->errors[] = "slots.json: \"$id\" default is placeholder text";
				}

				// Defaults must survive their own sanitizer unchanged, or the
				// editor would show a different value than the preview.
				if ( Slot_Renderer::sanitize_value( $slot, $default ) !== ( 'richtext' === $type ? trim( $default ) : $default ) ) {
					$this->errors[] = "slots.json: \"$id\" default changes when sanitized; remove disallowed markup";
				}
			}

			if ( 'image' === $type && null === $site->resolve_path( $default ) ) {
				$this->errors[] = "slots.json: \"$id\" default image not found ($default)";
			}
			if ( 'color' === $type && ! sanitize_hex_color( $default ) ) {
				$this->errors[] = "slots.json: \"$id\" default is not a hex colour";
			}
			if ( 'url' === $type && esc_url_raw( $default ) !== $default ) {
				$this->errors[] = "slots.json: \"$id\" default is not a clean URL";
			}
		}

		foreach ( self::OPTION_SLOTS as $option_slot ) {
			if ( ! isset( $slots[ $option_slot ] ) ) {
				$this->errors[] = "slots.json: missing \"$option_slot\"";
			}
		}

		return $slots;
	}

	/**
	 * Every token has a slot, every slot has a token, contexts are safe.
	 *
	 * @param Starter_Site                        $site  Package.
	 * @param array<string, string>               $files Relative path => markup.
	 * @param array<string, array<string, mixed>> $slots Slots keyed by id.
	 * @return void
	 */
	private function check_tokens( Starter_Site $site, array $files, array $slots ): void {
		$page_slugs = array_column( $site->get_pages(), 'slug' );
		$used       = [];

		foreach ( $files as $relative => $markup ) {
			foreach ( Slot_Renderer::token_contexts( $markup ) as $token ) {
				$name = $token['name'];

				if ( str_starts_with( $name, 'page:' ) ) {
					if ( ! in_array( substr( $name, 5 ), $page_slugs, true ) ) {
						$this->errors[] = "$relative: {{{$name}}} links to an unknown page";
					}
					continue;
				}

				if ( ! isset( $slots[ $name ] ) ) {
					$this->errors[] = "$relative: token {{{$name}}} has no slot in slots.json";
					continue;
				}

				$used[ $name ] = true;
				$type          = (string) $slots[ $name ]['type'];

				if ( ! in_array( $token['context'], Slot_Renderer::TYPE_CONTEXTS[ $type ] ?? [], true ) ) {
					$this->errors[] = "$relative: {{{$name}}} is a $type slot and cannot be used inside " . [
						'html' => 'text content',
						'attr' => 'an HTML attribute',
						'json' => 'block attributes',
					][ $token['context'] ];
				}
			}
		}

		foreach ( array_keys( $slots ) as $id ) {
			if ( ! isset( $used[ $id ] ) && ! in_array( $id, self::OPTION_SLOTS, true ) ) {
				$this->errors[] = "slots.json: \"$id\" is not used by any page, part or template";
			}
		}
	}

	/**
	 * Block-level checks on raw and resolved markup.
	 *
	 * @param Starter_Site                      $site    Package.
	 * @param array<string, string>             $files   Relative path => markup.
	 * @param array<string, array<int, string>> $presets Preset type => slugs.
	 * @return void
	 */
	private function check_markup( Starter_Site $site, array $files, array $presets ): void {
		$resolved = self::render_defaults( $site );

		foreach ( $files as $relative => $markup ) {
			if ( preg_match( '/<\?(php|=)?|<script\b|\son[a-z]+\s*=/i', $markup ) ) {
				$this->errors[] = "$relative: contains PHP, a script tag or an inline event handler";
			}

			$stages = [
				'raw'      => $markup,
				'resolved' => $resolved[ $relative ] ?? '',
			];

			foreach ( $stages as $stage => $content ) {
				$names = [];
				$this->walk_blocks( parse_blocks( $content ), $relative, $stage, $names, $presets );

				if ( 'raw' === $stage && str_starts_with( $relative, 'parts/header' ) && $site->get_navigation() && 1 !== ( $names['core/navigation'] ?? 0 ) ) {
					$this->errors[] = "$relative: the header must contain exactly one core/navigation block";
				}
			}

			if ( str_contains( $resolved[ $relative ] ?? '', '{{' ) ) {
				$this->errors[] = "$relative: unresolved {{token}} left after rendering defaults";
			}
		}
	}

	/**
	 * Walk parsed blocks: core only, no stray HTML, known presets.
	 *
	 * @param array<int, array<string, mixed>>  $blocks   Parsed blocks.
	 * @param string                            $relative File being checked.
	 * @param string                            $stage    "raw" or "resolved".
	 * @param array<string, int>                $names    Block name counts, by reference.
	 * @param array<string, array<int, string>> $presets  Preset type => slugs.
	 * @return void
	 */
	private function walk_blocks( array $blocks, string $relative, string $stage, array &$names, array $presets ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? null;

			if ( null === $name ) {
				if ( '' !== trim( (string) ( $block['innerHTML'] ?? '' ) ) ) {
					$this->errors[] = "$relative ($stage): HTML outside any block: " . mb_substr( trim( wp_strip_all_tags( (string) $block['innerHTML'] ) ), 0, 60 );
				}
				continue;
			}

			$names[ $name ] = ( $names[ $name ] ?? 0 ) + 1;

			if ( ! str_starts_with( $name, 'core/' ) ) {
				$this->errors[] = "$relative ($stage): non-core block $name";
			} elseif ( 'raw' === $stage && ! \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				$this->errors[] = "$relative: $name is not a registered core block on this WordPress version";
			}

			if ( 'raw' === $stage ) {
				$attrs        = (array) ( $block['attrs'] ?? [] );
				$preset_attrs = [
					'backgroundColor' => 'color',
					'textColor'       => 'color',
					'fontSize'        => 'font-size',
				];

				foreach ( $preset_attrs as $key => $type ) {
					if ( isset( $attrs[ $key ] ) && ! in_array( $attrs[ $key ], $presets[ $type ], true ) ) {
						$this->errors[] = "$relative: $name uses $key \"{$attrs[ $key ]}\", which the starter site does not define";
					}
				}

				$this->check_preset_refs( $relative, $attrs, $presets );
			}

			$this->walk_blocks( (array) ( $block['innerBlocks'] ?? [] ), $relative, $stage, $names, $presets );
		}
	}

	/**
	 * Every var:preset|type|slug reference must exist in the package.
	 *
	 * Anything else would depend on the active theme's presets and look
	 * different (or broken) on another block theme.
	 *
	 * @param string                            $where   Label for messages.
	 * @param mixed                             $data    Attributes or styles.
	 * @param array<string, array<int, string>> $presets Preset type => slugs.
	 * @return void
	 */
	private function check_preset_refs( string $where, $data, array $presets ): void {
		$json = (string) wp_json_encode( $data );

		if ( preg_match_all( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)|var\(--wp--preset--([a-z-]+?)--([a-z0-9-]+)\)/', $json, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$type = '' !== $match[1] ? $match[1] : $match[3];
				$slug = '' !== $match[1] ? $match[2] : $match[4];

				if ( ! isset( $presets[ $type ] ) || ! in_array( $slug, $presets[ $type ], true ) ) {
					$this->errors[] = "$where: references preset $type \"$slug\", which the starter site does not define";
				}
			}
		}
	}

	/**
	 * Bundled assets: format and size.
	 *
	 * @param Starter_Site $site Package.
	 * @return void
	 */
	private function check_assets( Starter_Site $site ): void {
		$referenced = [];
		foreach ( $site->get_slots() as $slot ) {
			if ( 'image' === ( $slot['type'] ?? '' ) ) {
				$referenced[ (string) $slot['default'] ] = true;
			}
		}

		$files = glob( $site->get_dir() . 'assets/*' );
		foreach ( $files ? $files : [] as $file ) {
			$relative = 'assets/' . basename( $file );
			$size     = (int) filesize( $file );

			if ( ! preg_match( '/\.webp$/i', $file ) ) {
				$this->errors[] = "$relative: bundled images must be WebP";
			}
			if ( $size > self::IMAGE_HARD_LIMIT ) {
				$this->errors[] = sprintf( '%s: %d KB is too large (limit %d KB)', $relative, $size / KB_IN_BYTES, self::IMAGE_HARD_LIMIT / KB_IN_BYTES );
			} elseif ( $size > self::IMAGE_SOFT_LIMIT ) {
				$this->warnings[] = sprintf( '%s: %d KB; aim for %d KB or less', $relative, $size / KB_IN_BYTES, self::IMAGE_SOFT_LIMIT / KB_IN_BYTES );
			}
			if ( ! isset( $referenced[ $relative ] ) ) {
				$this->warnings[] = "$relative: not used by any image slot";
			}
		}

		// Nothing executable anywhere in the package.
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $site->get_dir(), \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! preg_match( '/\.(json|html|webp|jpg|png)$/i', $file->getFilename() ) ) {
				$this->errors[] = 'unexpected file type: ' . str_replace( $site->get_dir(), '', wp_normalize_path( $file->getPathname() ) );
			}
		}
	}

	/**
	 * Read every markup file the manifest references.
	 *
	 * @param Starter_Site $site Package.
	 * @return array<string, string>
	 */
	private function markup_files( Starter_Site $site ): array {
		$files = [];
		foreach ( self::list_markup_files( $site ) as $relative ) {
			$markup = $site->read_file( $relative );
			if ( null !== $markup ) {
				$files[ $relative ] = $markup;
			}
		}
		return $files;
	}

	/**
	 * Relative paths of every page, part and template file.
	 *
	 * @param Starter_Site $site Package.
	 * @return array<int, string>
	 */
	private static function list_markup_files( Starter_Site $site ): array {
		$files = [];
		foreach ( $site->get_pages() as $page ) {
			$files = array_merge( $files, array_column( $page['sections'], 'file' ) );
		}

		return array_values(
			array_unique(
				array_merge(
					$files,
					array_values( $site->get_template_parts() ),
					array_column( $site->get_templates(), 'file' )
				)
			)
		);
	}
}
