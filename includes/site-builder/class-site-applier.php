<?php
/**
 * Applies a staged build to the live site, and undoes it.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Site_Applier class.
 *
 * The only part of the Site Builder that changes what visitors see. Apply
 * runs only after an explicit confirmation, takes a snapshot first, and
 * writes nothing but ordinary WordPress content: published pages, the active
 * theme's user template and template part customizations, user global
 * styles, a core navigation menu and media library images. Everything stays
 * readable and editable in the Site Editor after this plugin is removed.
 *
 * Undo restores the snapshot and moves everything Apply created to the
 * trash. Nothing is ever deleted permanently.
 */
class Site_Applier {

	/**
	 * Option holding the snapshot list, newest first.
	 *
	 * @var string
	 */
	public const OPTION = 'mysitehand_site_snapshots';

	/**
	 * Snapshots kept.
	 *
	 * @var int
	 */
	public const KEEP = 5;

	/**
	 * Lock so two applies cannot run at once.
	 *
	 * @var string
	 */
	private const LOCK = 'mysitehand_apply_lock';

	/**
	 * Options captured and restored.
	 *
	 * @var array<int, string>
	 */
	private const OPTIONS = [ 'show_on_front', 'page_on_front', 'page_for_posts', 'blogname', 'blogdescription' ];

	/**
	 * Site builder service.
	 *
	 * @var Site_Builder
	 */
	private Site_Builder $builder;

	/**
	 * Constructor.
	 *
	 * @param Site_Builder $builder Site builder service.
	 */
	public function __construct( Site_Builder $builder ) {
		$this->builder = $builder;
	}

	/**
	 * Exactly what Apply would change, for the confirmation dialog.
	 *
	 * @param int $user_id User id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function plan( int $user_id ) {
		$resolved = $this->builder->resolve_staged( $user_id );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$build = $resolved['build'];
		$front = '';
		foreach ( $build['pages'] as $page ) {
			if ( ! empty( $page['is_front_page'] ) ) {
				$front = $page['title'];
			}
		}

		$images = 0;
		foreach ( Site_Build::slots( $resolved['site'], $resolved['state'] ) as $slot ) {
			if ( 'image' === ( $slot['type'] ?? '' ) ) {
				++$images;
			}
		}

		$replace  = $this->replaces( $resolved['state'] );
		$replaced = $replace ? $this->live_page_ids( $replace ) : [];

		// An update takes over the web addresses of the pages it replaces,
		// so only a new build can clash with pages already there.
		$existing = [];
		foreach ( $replace ? [] : $build['pages'] as $page ) {
			if ( get_page_by_path( $page['slug'], OBJECT, 'page' ) ) {
				$existing[] = $page['slug'];
			}
		}

		// Pages from the last apply that were changed afterwards, outside
		// the Site Builder. They go to the trash with the rest.
		$edited = [];
		if ( $replace ) {
			$hashes = (array) ( $replace['page_hashes'] ?? [] );
			foreach ( $replaced as $page_id ) {
				$post = get_post( $page_id );
				if ( $post && isset( $hashes[ $page_id ] ) && md5( $post->post_content ) !== $hashes[ $page_id ] ) {
					$edited[] = get_the_title( $post );
				}
			}
		}

		return [
			'name'          => $build['name'],
			'theme'         => wp_get_theme()->get( 'Name' ),
			'pages'         => array_column( $build['pages'], 'title' ),
			'front_page'    => $front,
			'site_title'    => [
				'from' => (string) get_option( 'blogname' ),
				'to'   => $build['settings']['blogname'],
			],
			'tagline'       => [
				'from' => (string) get_option( 'blogdescription' ),
				'to'   => $build['settings']['blogdescription'],
			],
			'parts'         => array_keys( $build['parts'] ),
			'templates'     => array_column( $build['templates'], 'title' ),
			'menu_items'    => array_column( $build['navigation'], 'label' ),
			'images'        => $images,
			'existing_slugs' => $existing,
			'replaces'      => $replace ? [
				'name'   => (string) $replace['starter']['name'],
				'date'   => wp_date( get_option( 'date_format' ), (int) $replace['created_at'] ),
				'pages'  => count( $replaced ),
				'edited' => $edited,
			] : null,
		];
	}

	/**
	 * Apply the user's staged build.
	 *
	 * @param int $user_id User id.
	 * @return array<string, mixed>|\WP_Error Snapshot summary.
	 */
	public function apply( int $user_id ) {
		if ( ! wp_is_block_theme() ) {
			return new \WP_Error( 'mysitehand_needs_block_theme', __( 'Applying a starter site needs a block theme. Switch to Twenty Twenty-Five first.', 'my-site-hand' ), [ 'status' => 409 ] );
		}

		$staged = $this->builder->resolve_staged( $user_id );
		if ( is_wp_error( $staged ) ) {
			return $staged;
		}

		if ( get_transient( self::LOCK ) ) {
			return new \WP_Error( 'mysitehand_apply_busy', __( 'A starter site is already being applied. Wait a minute and try again.', 'my-site-hand' ), [ 'status' => 409 ] );
		}
		set_transient( self::LOCK, $user_id, 2 * MINUTE_IN_SECONDS );

		$site     = $staged['site'];
		$state    = $staged['state'];
		$snapshot = $this->capture( $site, $staged['build'], $user_id );

		// Kept so the build can be customized again later.
		$snapshot['state'] = array_merge( $state, [ 'based_on' => '' ] );

		$result = $this->run_steps( $site, $state, $snapshot, $this->replaces( $state ) );

		if ( is_wp_error( $result ) ) {
			// Put everything back the way it was, then report.
			$this->restore_one( $snapshot );
			delete_transient( self::LOCK );

			return new \WP_Error(
				$result->get_error_code(),
				sprintf(
					/* translators: %s: the underlying error message */
					__( 'The starter site could not be applied, so nothing was changed: %s', 'my-site-hand' ),
					$result->get_error_message()
				),
				[ 'status' => 500 ]
			);
		}

		$snapshot['status']     = 'applied';
		$snapshot['applied_at'] = time();
		$this->store( $snapshot );

		$this->builder->discard( $user_id );
		Plugin::get_instance()->get_site_preview()->revoke( $user_id );
		$this->clean_caches();
		delete_transient( self::LOCK );

		return $this->summarize( $snapshot );
	}

	/**
	 * Every apply step, recording what it creates in the snapshot as it goes.
	 *
	 * @param Starter_Site              $site     Package.
	 * @param array<string, mixed>      $state    State.
	 * @param array<string, mixed>      $snapshot Snapshot, by reference.
	 * @param array<string, mixed>|null $replace  The live build this one replaces, if any.
	 * @return true|\WP_Error
	 */
	private function run_steps( Starter_Site $site, array $state, array &$snapshot, ?array $replace = null ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$theme = get_stylesheet();

		// 0. Customizing the live build again: its pages and menu go to the
		// trash first, so the new pages keep the same web addresses. Undo
		// brings them back.
		$snapshot['replaced'] = [
			'pages'      => [],
			'navigation' => [],
		];
		if ( $replace ) {
			$step = $this->step( 'replace' );
			if ( is_wp_error( $step ) ) {
				return $step;
			}
			$old = [
				'pages'      => $this->live_page_ids( $replace ),
				'navigation' => array_map( 'intval', (array) ( $replace['created']['navigation'] ?? [] ) ),
			];
			foreach ( $old as $kind => $ids ) {
				foreach ( $ids as $post_id ) {
					$post = get_post( $post_id );
					if ( ! $post || 'trash' === $post->post_status ) {
						continue;
					}
					if ( ! wp_trash_post( $post_id ) ) {
						/* translators: %d: post id */
						return new \WP_Error( 'mysitehand_replace_failed', sprintf( __( 'Could not move item %d to the trash.', 'my-site-hand' ), $post_id ) );
					}
					$snapshot['replaced'][ $kind ][] = $post_id;
				}
			}
		}

		// 1. Pages, empty for now, so their permalinks exist for links.
		$step = $this->step( 'pages' );
		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$page_ids  = [];
		$page_urls = [];
		foreach ( $site->get_pages() as $index => $page ) {
			$id = wp_insert_post(
				[
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_content' => '',
					'menu_order'   => $index,
					'post_author'  => $snapshot['user_id'],
				],
				true
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}

			$snapshot['created']['pages'][] = (int) $id;
			$page_ids[ $page['slug'] ]      = (int) $id;
			$page_urls[ $page['slug'] ]     = (string) get_permalink( $id );
		}

		// 2. Bundled images into the media library.
		$step = $this->step( 'media' );
		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$images = [];
		$reuse  = (array) ( $replace['images'] ?? [] );
		foreach ( Site_Build::slots( $site, $state ) as $id => $slot ) {
			if ( 'image' !== ( $slot['type'] ?? '' ) || isset( $state['values'][ $id ] ) ) {
				continue;
			}

			// The same bundled image the last build already added.
			$previous = get_post( (int) ( $reuse[ $id ] ?? 0 ) );
			if ( $previous && 'attachment' === $previous->post_type && 'trash' !== $previous->post_status ) {
				$images[ $id ] = (int) $previous->ID;
				continue;
			}

			$attachment = $this->sideload( $site, (string) $slot['default'], (string) ( $slot['label'] ?? $id ) );
			if ( is_wp_error( $attachment ) ) {
				return $attachment;
			}
			$snapshot['created']['media'][] = $attachment;
			$images[ $id ]                  = $attachment;
		}
		$snapshot['images'] = $images;

		// 3. The menu.
		$step = $this->step( 'navigation' );
		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$links = '';
		foreach ( $site->get_navigation() as $item ) {
			$links .= serialize_block(
				Site_Build::navigation_link(
					[
						'label' => $item['label'],
						'url'   => $page_urls[ $item['page'] ] ?? '',
						'id'    => $page_ids[ $item['page'] ] ?? 0,
					]
				)
			) . "\n";
		}

		$nav_id = 0;
		if ( '' !== $links ) {
			$nav_id = wp_insert_post(
				[
					'post_type'    => 'wp_navigation',
					'post_status'  => 'publish',
					/* translators: %s: starter site name */
					'post_title'   => sprintf( __( '%s menu', 'my-site-hand' ), $site->get_name() ),
					'post_content' => wp_slash( trim( $links ) ),
				],
				true
			);
			if ( is_wp_error( $nav_id ) ) {
				return $nav_id;
			}
			$snapshot['created']['navigation'][] = (int) $nav_id;
		}

		// 4. Resolve for real: permalinks, attachment ids, the menu ref.
		$build = Site_Build::resolve(
			$site,
			$state,
			[
				'page_urls' => $page_urls,
				'images'    => $images,
				'nav_mode'  => 'ref',
				'nav_ref'   => (int) $nav_id,
			]
		);

		// 5. Page content.
		$step = $this->step( 'content' );
		if ( is_wp_error( $step ) ) {
			return $step;
		}

		foreach ( $build['pages'] as $page ) {
			$updated = wp_update_post(
				[
					'ID'           => $page_ids[ $page['slug'] ],
					'post_content' => wp_slash( $page['content'] ),
				],
				true
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			// As saved, so a later hand edit can be told apart.
			$snapshot['page_hashes'][ $page_ids[ $page['slug'] ] ] = md5( get_post( $page_ids[ $page['slug'] ] )->post_content );
		}

		// 6. Header, footer and the page template, as user customizations
		// of the active theme, the same records the Site Editor writes.
		$step = $this->step( 'templates' );
		if ( is_wp_error( $step ) ) {
			return $step;
		}

		foreach ( $build['parts'] as $slug => $content ) {
			$saved = $this->save_template( 'wp_template_part', (string) $slug, ucfirst( (string) $slug ), (string) $content, $theme, $snapshot );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		foreach ( $build['templates'] as $slug => $template ) {
			$saved = $this->save_template( 'wp_template', (string) $slug, (string) $template['title'], (string) $template['content'], $theme, $snapshot );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		// The page template can only be assigned once it exists: core
		// validates it on every later page update.
		foreach ( $site->get_pages() as $page ) {
			if ( '' !== $page['template'] ) {
				update_post_meta( $page_ids[ $page['slug'] ], '_wp_page_template', $page['template'] );
			}
		}

		// 7. Global styles.
		$step = $this->step( 'styles' );
		if ( is_wp_error( $step ) ) {
			return $step;
		}

		$styles_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		if ( ! $styles_id ) {
			return new \WP_Error( 'mysitehand_styles_failed', __( 'The global styles could not be saved.', 'my-site-hand' ) );
		}
		if ( ! $snapshot['styles']['existed'] ) {
			$snapshot['created']['styles'][] = (int) $styles_id;
		}
		$snapshot['styles']['id'] = (int) $styles_id;

		$updated = wp_update_post(
			[
				'ID'           => $styles_id,
				'post_content' => wp_slash( (string) wp_json_encode( $build['styles'] ) ),
			],
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		// 8. Front page, title and tagline.
		$step = $this->step( 'options' );
		if ( is_wp_error( $step ) ) {
			return $step;
		}

		foreach ( $build['pages'] as $page ) {
			if ( ! empty( $page['is_front_page'] ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page_ids[ $page['slug'] ] );
				if ( (int) get_option( 'page_for_posts' ) === $page_ids[ $page['slug'] ] ) {
					update_option( 'page_for_posts', 0 );
				}
			}
		}
		update_option( 'blogname', $build['settings']['blogname'] );
		update_option( 'blogdescription', $build['settings']['blogdescription'] );

		return true;
	}

	/**
	 * Test seam: lets a test make one step fail to prove the rollback.
	 *
	 * @param string $step Step name.
	 * @return true|\WP_Error
	 */
	private function step( string $step ) {
		$result = apply_filters( 'my_site_hand_apply_step', true, $step );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Copy a bundled image into the media library.
	 *
	 * @param Starter_Site $site     Package.
	 * @param string       $relative Package-relative path.
	 * @param string       $title    Attachment title.
	 * @return int|\WP_Error Attachment id.
	 */
	private function sideload( Starter_Site $site, string $relative, string $title ) {
		$source = $site->resolve_path( $relative );
		if ( null === $source ) {
			return new \WP_Error( 'mysitehand_image_missing', __( 'A bundled image is missing from the starter site.', 'my-site-hand' ) );
		}

		$tmp = wp_tempnam( basename( $source ) );
		if ( ! $tmp || ! copy( $source, $tmp ) ) {
			return new \WP_Error( 'mysitehand_image_copy', __( 'A bundled image could not be copied. Check that the uploads folder is writable.', 'my-site-hand' ) );
		}

		$id = media_handle_sideload(
			[
				'name'     => $site->get_slug() . '-' . basename( $source ),
				'tmp_name' => $tmp,
			],
			0,
			$site->get_name() . ': ' . $title
		);

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
		}

		return $id;
	}

	/**
	 * Create or update a template (part) customization for the theme.
	 *
	 * @param string               $type     wp_template or wp_template_part.
	 * @param string               $slug     Slug.
	 * @param string               $title    Title.
	 * @param string               $content  Block markup.
	 * @param string               $theme    Theme stylesheet.
	 * @param array<string, mixed> $snapshot Snapshot, by reference.
	 * @return int|\WP_Error Post id.
	 */
	private function save_template( string $type, string $slug, string $title, string $content, string $theme, array &$snapshot ) {
		$existing = self::find_template( $type, $slug, $theme );

		if ( $existing ) {
			$id = wp_update_post(
				[
					'ID'           => $existing->ID,
					'post_content' => wp_slash( $content ),
					'post_status'  => 'publish',
				],
				true
			);
		} else {
			$id = wp_insert_post(
				[
					'post_type'    => $type,
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_content' => wp_slash( $content ),
				],
				true
			);

			if ( ! is_wp_error( $id ) ) {
				$snapshot['created']['templates'][] = (int) $id;
			}
		}

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		// Set directly: tax_input is silently skipped without assign_terms.
		wp_set_post_terms( $id, [ $theme ], 'wp_theme' );
		if ( 'wp_template_part' === $type ) {
			wp_set_post_terms( $id, [ in_array( $slug, [ 'header', 'footer' ], true ) ? $slug : 'uncategorized' ], 'wp_template_part_area' );
		}

		return (int) $id;
	}

	/**
	 * A theme's stored customization of a template or part.
	 *
	 * @param string $type  wp_template or wp_template_part.
	 * @param string $slug  Slug.
	 * @param string $theme Theme stylesheet.
	 * @return \WP_Post|null
	 */
	private static function find_template( string $type, string $slug, string $theme ): ?\WP_Post {
		$posts = get_posts(
			[
				'post_type'        => $type,
				'post_status'      => [ 'publish', 'draft', 'auto-draft' ],
				'name'             => $slug,
				'posts_per_page'   => 1,
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One lookup, by the same taxonomy core uses for this.
				'tax_query'        => [
					[
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $theme,
					],
				],
			]
		);

		return $posts[0] ?? null;
	}

	/**
	 * Record everything Apply might change, before it changes anything.
	 *
	 * @param Starter_Site         $site    Package.
	 * @param array<string, mixed> $build   Resolved build.
	 * @param int                  $user_id User id.
	 * @return array<string, mixed>
	 */
	private function capture( Starter_Site $site, array $build, int $user_id ): array {
		$theme = get_stylesheet();

		$options = [];
		foreach ( self::OPTIONS as $option ) {
			$options[ $option ] = get_option( $option );
		}

		$templates = [];
		$by_type = [
			'wp_template_part' => array_keys( $build['parts'] ),
			'wp_template'      => array_keys( $build['templates'] ),
		];
		foreach ( $by_type as $type => $slugs ) {
			foreach ( $slugs as $slug ) {
				$post = self::find_template( $type, (string) $slug, $theme );
				if ( $post ) {
					$templates[] = [
						'id'      => (int) $post->ID,
						'type'    => $type,
						'slug'    => (string) $slug,
						'content' => $post->post_content,
						'status'  => $post->post_status,
					];
				}
			}
		}

		$styles = \WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme() );

		return [
			'id'         => wp_generate_uuid4(),
			'status'     => 'applying',
			'created_at' => time(),
			'user_id'    => $user_id,
			'starter'    => [
				'slug'    => $site->get_slug(),
				'name'    => $site->get_name(),
				'version' => (string) ( $site->get_manifest()['version'] ?? '' ),
			],
			'theme'      => $theme,
			'options'    => $options,
			'templates'  => $templates,
			'styles'     => [
				'existed' => ! empty( $styles['ID'] ),
				'id'      => (int) ( $styles['ID'] ?? 0 ),
				'content' => (string) ( $styles['post_content'] ?? '' ),
			],
			'navigation' => $this->header_navigation_ref( $theme ),
			'created'    => [
				'pages'      => [],
				'media'      => [],
				'navigation' => [],
				'templates'  => [],
				'styles'     => [],
			],
		];
	}

	/**
	 * The wp_navigation post the current header uses, for the record.
	 *
	 * Apply never edits it (it creates a new menu), so restoring the header
	 * restores the reference.
	 *
	 * @param string $theme Theme stylesheet.
	 * @return int
	 */
	private function header_navigation_ref( string $theme ): int {
		$template = get_block_template( $theme . '//header', 'wp_template_part' );
		if ( $template && preg_match( '/<!-- wp:navigation \{[^}]*"ref":(\d+)/', (string) $template->content, $match ) ) {
			return (int) $match[1];
		}
		return 0;
	}

	/**
	 * Snapshot list for the UI, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_snapshots(): array {
		return array_map( [ $this, 'summarize' ], $this->all() );
	}

	/**
	 * Undo an apply. Newer applies are undone first, newest to oldest, so
	 * the site is never left half way between builds.
	 *
	 * @param string $id Snapshot id.
	 * @return array<string, mixed>|\WP_Error Result with the undone snapshot summaries.
	 */
	public function restore( string $id ) {
		$all   = $this->all();
		$index = null;

		foreach ( $all as $i => $snapshot ) {
			if ( $snapshot['id'] === $id ) {
				$index = $i;
				break;
			}
		}

		if ( null === $index ) {
			return new \WP_Error( 'mysitehand_unknown_snapshot', __( 'That snapshot no longer exists. Only the last five are kept.', 'my-site-hand' ), [ 'status' => 404 ] );
		}

		if ( 'applied' !== $all[ $index ]['status'] ) {
			return new \WP_Error( 'mysitehand_already_undone', __( 'This build has already been undone.', 'my-site-hand' ), [ 'status' => 409 ] );
		}

		$undone = [];
		$errors = [];

		for ( $i = 0; $i <= $index; $i++ ) {
			if ( 'applied' !== $all[ $i ]['status'] ) {
				continue;
			}

			$errors             = array_merge( $errors, $this->restore_one( $all[ $i ] ) );
			$all[ $i ]['status'] = 'undone';
			$all[ $i ]['undone_at'] = time();
			$undone[]           = $this->summarize( $all[ $i ] );
		}

		update_option( self::OPTION, $all, false );
		$this->clean_caches();

		return [
			'undone' => $undone,
			'errors' => $errors,
		];
	}

	/**
	 * Put one snapshot back. Best effort: carries on past a failed step and
	 * returns the messages.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array<int, string> Error messages.
	 */
	private function restore_one( array $snapshot ): array {
		$errors = [];

		foreach ( (array) $snapshot['options'] as $option => $value ) {
			update_option( $option, $value );
		}

		foreach ( (array) $snapshot['templates'] as $template ) {
			$result = wp_update_post(
				[
					'ID'           => (int) $template['id'],
					'post_content' => wp_slash( (string) $template['content'] ),
					'post_status'  => (string) $template['status'],
				],
				true
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		if ( $snapshot['styles']['existed'] && $snapshot['styles']['id'] ) {
			$result = wp_update_post(
				[
					'ID'           => (int) $snapshot['styles']['id'],
					'post_content' => wp_slash( (string) $snapshot['styles']['content'] ),
				],
				true
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		// Everything Apply created goes to the trash, never further.
		foreach ( [ 'pages', 'navigation', 'templates', 'styles', 'media' ] as $kind ) {
			foreach ( (array) ( $snapshot['created'][ $kind ] ?? [] ) as $post_id ) {
				$post = get_post( (int) $post_id );
				if ( $post && 'trash' !== $post->post_status && ! wp_trash_post( (int) $post_id ) ) {
					/* translators: %d: post id */
					$errors[] = sprintf( __( 'Could not move item %d to the trash.', 'my-site-hand' ), (int) $post_id );
				}
			}
		}

		// The previous build's pages and menu, replaced by this one, come
		// back with their old status and web addresses.
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );
		foreach ( [ 'pages', 'navigation' ] as $kind ) {
			foreach ( (array) ( $snapshot['replaced'][ $kind ] ?? [] ) as $post_id ) {
				$post = get_post( (int) $post_id );
				if ( $post && 'trash' === $post->post_status && ! wp_untrash_post( (int) $post_id ) ) {
					/* translators: %d: post id */
					$errors[] = sprintf( __( 'Could not restore item %d from the trash.', 'my-site-hand' ), (int) $post_id );
				}
			}
		}
		remove_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10 );

		return $errors;
	}

	/**
	 * The build that is live now: the newest applied snapshot, as long as
	 * its pages have not all been removed since.
	 *
	 * @return array<string, mixed>|null
	 */
	public function live(): ?array {
		foreach ( $this->all() as $snapshot ) {
			if ( 'applied' === $snapshot['status'] ) {
				return $this->live_page_ids( $snapshot ) ? $snapshot : null;
			}
		}
		return null;
	}

	/**
	 * The live build for the UI and MCP clients.
	 *
	 * @return array<string, mixed>|null
	 */
	public function live_summary(): ?array {
		$live = $this->live();
		if ( null === $live ) {
			return null;
		}

		return $this->summarize( $live ) + [
			'slug'      => (string) $live['starter']['slug'],
			'has_edits' => ! empty( $live['state'] ),
		];
	}

	/**
	 * Stage the live build again, with the edits it was applied with, so
	 * the user can keep customizing it. Applying that draft replaces the
	 * live build's pages instead of adding a second set.
	 *
	 * @param int $user_id User id.
	 * @return array{staged: array<string, mixed>, has_edits: bool}|\WP_Error
	 */
	public function customize_live( int $user_id ) {
		$live = $this->live();
		if ( null === $live ) {
			return new \WP_Error( 'mysitehand_nothing_live', __( 'No starter site is live on your site right now.', 'my-site-hand' ), [ 'status' => 404 ] );
		}

		// Builds applied before this version did not keep their edits;
		// those start again from the starter site's own text.
		$state             = is_array( $live['state'] ?? null ) ? $live['state'] : [];
		$state['based_on'] = (string) $live['id'];

		$staged = $this->builder->stage_state( $user_id, (string) $live['starter']['slug'], $state );
		if ( is_wp_error( $staged ) ) {
			return $staged;
		}

		return [
			'staged'    => $staged,
			'has_edits' => ! empty( $live['state'] ),
		];
	}

	/**
	 * The live build a staged state continues, if it is still the live one.
	 *
	 * @param array<string, mixed> $state Staged state.
	 * @return array<string, mixed>|null
	 */
	private function replaces( array $state ): ?array {
		$live = $this->live();
		if ( null === $live || '' === (string) ( $state['based_on'] ?? '' ) ) {
			return null;
		}
		return $state['based_on'] === $live['id'] && ( $state['slug'] ?? '' ) === $live['starter']['slug'] ? $live : null;
	}

	/**
	 * Pages a build created that are still on the site.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array<int, int>
	 */
	private function live_page_ids( array $snapshot ): array {
		$ids = [];
		foreach ( (array) ( $snapshot['created']['pages'] ?? [] ) as $page_id ) {
			$post = get_post( (int) $page_id );
			if ( $post && 'page' === $post->post_type && 'trash' !== $post->post_status ) {
				$ids[] = (int) $page_id;
			}
		}
		return $ids;
	}

	/**
	 * Add a snapshot to the front of the list, keeping KEEP.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return void
	 */
	private function store( array $snapshot ): void {
		$all = $this->all();
		array_unshift( $all, $snapshot );
		update_option( self::OPTION, array_slice( $all, 0, self::KEEP ), false );
	}

	/**
	 * Stored snapshots.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function all(): array {
		$all = get_option( self::OPTION, [] );
		return is_array( $all ) ? array_values( array_filter( $all, 'is_array' ) ) : [];
	}

	/**
	 * Public view of a snapshot (no stored content).
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array<string, mixed>
	 */
	public function summarize( array $snapshot ): array {
		$front = 0;
		foreach ( (array) $snapshot['created']['pages'] as $page_id ) {
			if ( (int) get_option( 'page_on_front' ) === (int) $page_id ) {
				$front = (int) $page_id;
			}
		}

		return [
			'id'         => (string) $snapshot['id'],
			'status'     => (string) $snapshot['status'],
			'starter'    => (string) $snapshot['starter']['name'],
			'theme'      => (string) $snapshot['theme'],
			'created_at' => gmdate( 'c', (int) $snapshot['created_at'] ),
			'date'       => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $snapshot['created_at'] ),
			'undone_at'  => isset( $snapshot['undone_at'] ) ? gmdate( 'c', (int) $snapshot['undone_at'] ) : null,
			'pages'      => count( (array) $snapshot['created']['pages'] ),
			'images'     => count( (array) $snapshot['created']['media'] ),
			'front_url'  => $front ? (string) get_permalink( $front ) : '',
		];
	}

	/**
	 * Clear every cache Apply or Undo affects.
	 *
	 * @return void
	 */
	private function clean_caches(): void {
		wp_clean_theme_json_cache();
		\WP_Theme_JSON_Resolver::clean_cached_data();
		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'theme_json' );
		}
	}
}
