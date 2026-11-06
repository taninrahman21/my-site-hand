<?php
/**
 * Live preview of a staged build on the user's own site.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Site_Preview class.
 *
 * A preview URL is home_url( '/?msh_preview=<token>' ). The token is random,
 * belongs to one user, expires after 24 hours and only works for that user
 * while they are logged in with manage_options. Anyone else who opens the
 * URL gets the normal site: the parameter is simply ignored.
 *
 * Nothing is written while previewing. Staged pages are virtual: an
 * in-memory WP_Post is placed in the main query, so core's own singular loop
 * renders it through the post-content block exactly as it would a real page.
 * The header and footer template parts, the page template, the global styles
 * and the site title are swapped in through filters for this request only.
 * Draft posts were rejected because they would be database writes, and a
 * draft wp_navigation was not needed: the staged header carries its links
 * inline, which core's navigation block renders without any stored menu.
 */
class Site_Preview {

	/**
	 * Query var carrying the token.
	 *
	 * @var string
	 */
	public const TOKEN_VAR = 'msh_preview';

	/**
	 * Query var selecting a staged page by slug. Absent means the front page.
	 *
	 * @var string
	 */
	public const PAGE_VAR = 'msh_page';

	/**
	 * Query var set when the preview is inside the admin's device frame.
	 *
	 * @var string
	 */
	public const FRAME_VAR = 'msh_frame';

	/**
	 * Query var for the standalone device view: desktop, tablet or mobile.
	 *
	 * @var string
	 */
	public const DEVICE_VAR = 'msh_device';

	/**
	 * Token lifetime.
	 *
	 * @var int
	 */
	public const TOKEN_TTL = DAY_IN_SECONDS;

	/**
	 * User meta holding the user's current token and its expiry.
	 *
	 * @var string
	 */
	public const TOKEN_META = 'mysitehand_preview_token';

	/**
	 * Device widths shown in the admin and the standalone toggle.
	 *
	 * @var array<string, int>
	 */
	public const DEVICES = [
		'desktop' => 1280,
		'tablet'  => 768,
		'mobile'  => 390,
	];

	/**
	 * First virtual post id. Far above any real id, and positive, because
	 * some core code passes post ids through absint().
	 *
	 * @var int
	 */
	private const VIRTUAL_ID_BASE = 2147480000;

	/**
	 * Site builder service.
	 *
	 * @var Site_Builder
	 */
	private Site_Builder $builder;

	/**
	 * Resolved staged build for this request, once preview is active.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $build = null;

	/**
	 * Token for this request.
	 *
	 * @var string
	 */
	private string $token = '';

	/**
	 * Constructor.
	 *
	 * @param Site_Builder $builder Site builder service.
	 */
	public function __construct( Site_Builder $builder ) {
		$this->builder = $builder;
	}

	/**
	 * Hook in. Cheap: does nothing unless the token parameter is present.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is the credential, checked in maybe_activate().
		if ( ! isset( $_GET[ self::TOKEN_VAR ] ) ) {
			return;
		}

		add_action( 'wp', [ $this, 'maybe_activate' ], 0 );
	}

	/**
	 * Create (or reuse) a preview token for a user and return the URL.
	 *
	 * @param int $user_id User id.
	 * @return string Preview URL.
	 */
	public function get_url( int $user_id ): string {
		$stored = get_user_meta( $user_id, self::TOKEN_META, true );

		// Reuse a token with at least an hour left, so the admin iframe and
		// open tabs keep working while the user edits.
		if ( is_array( $stored ) && ! empty( $stored['token'] ) && (int) ( $stored['expires'] ?? 0 ) > time() + HOUR_IN_SECONDS
			&& (int) get_transient( self::transient_key( (string) $stored['token'] ) ) === $user_id ) {
			$token = (string) $stored['token'];
		} else {
			$token = wp_generate_password( 32, false, false );
			set_transient( self::transient_key( $token ), $user_id, self::TOKEN_TTL );
			update_user_meta(
				$user_id,
				self::TOKEN_META,
				[
					'token'   => $token,
					'expires' => time() + self::TOKEN_TTL,
				]
			);
		}

		return add_query_arg( self::TOKEN_VAR, rawurlencode( $token ), home_url( '/' ) );
	}

	/**
	 * Invalidate the user's token (after apply or discard).
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function revoke( int $user_id ): void {
		$stored = get_user_meta( $user_id, self::TOKEN_META, true );
		if ( is_array( $stored ) && ! empty( $stored['token'] ) ) {
			delete_transient( self::transient_key( (string) $stored['token'] ) );
		}
		delete_user_meta( $user_id, self::TOKEN_META );
	}

	/**
	 * Transient key for a token. Only the hash is stored.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function transient_key( string $token ): string {
		return 'mysitehand_preview_' . substr( hash( 'sha256', $token ), 0, 40 );
	}

	/**
	 * Check the token and the user, then switch this request to preview mode.
	 *
	 * @return void
	 */
	public function maybe_activate(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is the credential.
		$token = sanitize_text_field( wp_unslash( (string) ( $_GET[ self::TOKEN_VAR ] ?? '' ) ) );
		if ( '' === $token ) {
			return;
		}

		$owner = (int) get_transient( self::transient_key( $token ) );
		if ( ! $owner || get_current_user_id() !== $owner || ! current_user_can( 'manage_options' ) ) {
			// Logged out, someone else, or an expired link: the normal site.
			return;
		}

		$resolved = $this->builder->resolve_staged(
			$owner,
			[
				'page_urls' => $this->page_urls( $token ),
				'nav_mode'  => 'links',
			]
		);

		if ( is_wp_error( $resolved ) ) {
			return;
		}

		$this->token = $token;
		$this->build = $resolved['build'];

		$this->isolate_caches();
		$this->add_filters();
	}

	/**
	 * Whether this request is an active preview.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return null !== $this->build;
	}

	/**
	 * Preview URL of every staged page.
	 *
	 * @param string $token Token.
	 * @return array<string, string>
	 */
	private function page_urls( string $token ): array {
		$staged = $this->builder->staged()->get( get_current_user_id() );
		$site   = $staged ? $this->builder->library()->get( (string) ( $staged['state']['slug'] ?? '' ) ) : null;
		$urls   = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag.
		$frame = isset( $_GET[ self::FRAME_VAR ] );

		foreach ( $site ? $site->get_pages() : [] as $page ) {
			$args = [ self::TOKEN_VAR => $token ];
			if ( ! $page['is_front_page'] ) {
				$args[ self::PAGE_VAR ] = $page['slug'];
			}
			if ( $frame ) {
				$args[ self::FRAME_VAR ] = '1';
			}
			$urls[ $page['slug'] ] = add_query_arg( $args, home_url( '/' ) );
		}

		return $urls;
	}

	/**
	 * Keep preview-only data out of any persistent object cache and out of
	 * page caches, so no visitor can ever be served it.
	 *
	 * @return void
	 */
	private function isolate_caches(): void {
		wp_cache_add_non_persistent_groups( [ 'theme_json', 'posts' ] );

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard constant honoured by page cache plugins.
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		wp_clean_theme_json_cache();
		\WP_Theme_JSON_Resolver::clean_cached_data();
	}

	/**
	 * All the request-scoped overrides.
	 *
	 * @return void
	 */
	private function add_filters(): void {
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'show_admin_bar', '__return_false' );

		add_filter( 'wp_theme_json_data_user', [ $this, 'filter_user_styles' ], 999 );
		add_filter( 'pre_option_blogname', [ $this, 'filter_blogname' ] );
		add_filter( 'pre_option_blogdescription', [ $this, 'filter_blogdescription' ] );

		add_filter( 'pre_render_block', [ $this, 'render_template_part' ], 10, 2 );
		add_filter( 'pre_get_block_template', [ $this, 'filter_block_template' ], 10, 3 );
		add_filter( 'get_block_file_template', [ $this, 'filter_block_file_template' ], 10, 3 );
		add_filter( 'get_block_templates', [ $this, 'filter_block_templates' ], 10, 3 );

		add_action( 'template_redirect', [ $this, 'route' ], 0 );
		add_filter( 'template_include', [ $this, 'use_staged_template' ], 99 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_bar' ] );
		add_action( 'wp_footer', [ $this, 'render_bar' ], 99 );
	}

	/**
	 * Replace user global styles with the staged ones.
	 *
	 * @param \WP_Theme_JSON_Data $data User data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function filter_user_styles( $data ) {
		return new \WP_Theme_JSON_Data( (array) $this->build['styles'], 'custom' );
	}

	/**
	 * Staged site title.
	 *
	 * @return string
	 */
	public function filter_blogname(): string {
		return (string) $this->build['settings']['blogname'];
	}

	/**
	 * Staged tagline.
	 *
	 * @return string
	 */
	public function filter_blogdescription(): string {
		return (string) $this->build['settings']['blogdescription'];
	}

	/**
	 * Render staged header and footer in place of the theme's.
	 *
	 * Short-circuiting the block render works whatever lookup path a given
	 * core version uses for template parts (database query, file, or
	 * get_block_template()).
	 *
	 * @param string|null          $pre   Pre-rendered output.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string|null
	 */
	public function render_template_part( $pre, $block ) {
		if ( 'core/template-part' !== ( $block['blockName'] ?? '' ) ) {
			return $pre;
		}

		$slug = (string) ( $block['attrs']['slug'] ?? '' );
		if ( ! isset( $this->build['parts'][ $slug ] ) ) {
			return $pre;
		}

		$tag = (string) ( $block['attrs']['tagName'] ?? $slug );
		if ( ! in_array( $tag, [ 'header', 'footer', 'div', 'section', 'aside', 'main' ], true ) ) {
			$tag = 'div';
		}

		return sprintf( '<%1$s class="wp-block-template-part">%2$s</%1$s>', $tag, do_blocks( (string) $this->build['parts'][ $slug ] ) );
	}

	/**
	 * A staged template or part as a WP_Block_Template.
	 *
	 * @param string $slug Slug.
	 * @param string $type wp_template or wp_template_part.
	 * @return \WP_Block_Template|null
	 */
	private function staged_template( string $slug, string $type ): ?\WP_Block_Template {
		if ( 'wp_template_part' === $type ) {
			$content = $this->build['parts'][ $slug ] ?? null;
			$title   = ucfirst( $slug );
		} else {
			$content = $this->build['templates'][ $slug ]['content'] ?? null;
			$title   = (string) ( $this->build['templates'][ $slug ]['title'] ?? $slug );
		}

		if ( null === $content ) {
			return null;
		}

		$template                 = new \WP_Block_Template();
		$template->id             = get_stylesheet() . '//' . $slug;
		$template->theme          = get_stylesheet();
		$template->slug           = $slug;
		$template->content        = (string) $content;
		$template->source         = 'custom';
		$template->type           = $type;
		$template->title          = $title;
		$template->status         = 'publish';
		$template->has_theme_file = false;
		$template->is_custom      = 'wp_template' === $type;
		$template->area           = 'wp_template_part' === $type ? $slug : '';

		return $template;
	}

	/**
	 * Filter: pre_get_block_template.
	 *
	 * @param \WP_Block_Template|null $template Template.
	 * @param string                  $id       theme//slug.
	 * @param string                  $type     Template type.
	 * @return \WP_Block_Template|null
	 */
	public function filter_block_template( $template, $id, $type ) {
		$parts = explode( '//', (string) $id, 2 );
		if ( 2 === count( $parts ) && get_stylesheet() === $parts[0] ) {
			$staged = $this->staged_template( $parts[1], (string) $type );
			if ( $staged ) {
				return $staged;
			}
		}
		return $template;
	}

	/**
	 * Filter: get_block_file_template.
	 *
	 * @param \WP_Block_Template|null $template Template.
	 * @param string                  $id       theme//slug.
	 * @param string                  $type     Template type.
	 * @return \WP_Block_Template|null
	 */
	public function filter_block_file_template( $template, $id, $type ) {
		return $this->filter_block_template( $template, $id, $type );
	}

	/**
	 * Filter: get_block_templates. Swap in staged versions by slug.
	 *
	 * @param array<int, \WP_Block_Template> $templates Templates.
	 * @param array<string, mixed>           $query     Query.
	 * @param string                         $type      Template type.
	 * @return array<int, \WP_Block_Template>
	 */
	public function filter_block_templates( $templates, $query, $type ) {
		$wanted = (array) ( $query['slug__in'] ?? [] );
		$seen   = [];

		foreach ( $templates as $i => $template ) {
			$staged = $this->staged_template( (string) $template->slug, (string) $type );
			if ( $staged ) {
				$templates[ $i ] = $staged;
			}
			$seen[ $template->slug ] = true;
		}

		foreach ( $wanted as $slug ) {
			if ( ! isset( $seen[ $slug ] ) ) {
				$staged = $this->staged_template( (string) $slug, (string) $type );
				if ( $staged ) {
					$templates[] = $staged;
				}
			}
		}

		return $templates;
	}

	/**
	 * The staged page this request asks for.
	 *
	 * @return array<string, mixed>|null
	 */
	private function requested_page(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$slug = isset( $_GET[ self::PAGE_VAR ] ) ? sanitize_title( wp_unslash( (string) $_GET[ self::PAGE_VAR ] ) ) : '';

		foreach ( (array) $this->build['pages'] as $index => $page ) {
			if ( ( '' === $slug && ! empty( $page['is_front_page'] ) ) || ( '' !== $slug && $page['slug'] === $slug ) ) {
				$page['index'] = $index;
				return $page;
			}
		}

		return null;
	}

	/**
	 * Route the request to a virtual staged page, or show the device shell.
	 *
	 * @return void
	 */
	public function route(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display flags.
		$device = isset( $_GET[ self::DEVICE_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::DEVICE_VAR ] ) ) : '';
		$framed = isset( $_GET[ self::FRAME_VAR ] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $framed && in_array( $device, [ 'tablet', 'mobile' ], true ) ) {
			$this->render_device_shell( $device );
			exit;
		}

		$page = $this->requested_page();
		if ( null === $page ) {
			// Not a staged page (a blog post, say): it still previews with
			// the staged header, footer and styles.
			return;
		}

		$post = new \WP_Post(
			(object) [
				'ID'             => self::VIRTUAL_ID_BASE + (int) $page['index'],
				'post_author'    => get_current_user_id(),
				'post_date'      => current_time( 'mysql' ),
				'post_date_gmt'  => current_time( 'mysql', true ),
				'post_content'   => (string) $page['content'],
				'post_title'     => (string) $page['title'],
				'post_excerpt'   => '',
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_name'      => (string) $page['slug'],
				'post_parent'    => 0,
				'post_type'      => 'page',
				'comment_count'  => 0,
				'filter'         => 'raw',
			]
		);

		// Non-persistent for this request (see isolate_caches()).
		wp_cache_set( $post->ID, $post, 'posts' );

		global $wp_query;
		$wp_query->posts             = [ $post ];
		$wp_query->post              = $post;
		$wp_query->post_count        = 1;
		$wp_query->found_posts       = 1;
		$wp_query->max_num_pages     = 1;
		$wp_query->current_post      = -1;
		$wp_query->queried_object    = $post;
		$wp_query->queried_object_id = $post->ID;
		$wp_query->is_page           = true;
		$wp_query->is_singular       = true;
		$wp_query->is_single         = false;
		$wp_query->is_home           = false;
		$wp_query->is_front_page     = ! empty( $page['is_front_page'] );
		$wp_query->is_archive        = false;
		$wp_query->is_search         = false;
		$wp_query->is_404            = false;
		$wp_query->is_posts_page     = false;

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The virtual page must be the global post for the template loop.

		status_header( 200 );
	}

	/**
	 * Render staged pages with the staged page template.
	 *
	 * @param string $template Template file.
	 * @return string
	 */
	public function use_staged_template( $template ) {
		$page = is_singular() ? $this->requested_page() : null;
		$slug = $page ? (string) $page['template'] : '';

		if ( '' === $slug || ! isset( $this->build['templates'][ $slug ] ) ) {
			return $template;
		}

		global $_wp_current_template_content, $_wp_current_template_id;
		$_wp_current_template_content = (string) $this->build['templates'][ $slug ]['content']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This is how block themes hand the template to template-canvas.php.
		$_wp_current_template_id      = get_stylesheet() . '//' . $slug; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return ABSPATH . WPINC . '/template-canvas.php';
	}

	/**
	 * Preview bar assets.
	 *
	 * @return void
	 */
	public function enqueue_bar(): void {
		wp_enqueue_style( 'msh-preview-bar', MYSITEHAND_URL . 'assets/css/preview-bar.css', [], MYSITEHAND_VERSION );
		wp_enqueue_script( 'msh-preview-bar', MYSITEHAND_URL . 'assets/js/preview-bar.js', [], MYSITEHAND_VERSION, true );
		wp_localize_script(
			'msh-preview-bar',
			'mshPreview',
			[
				'token'   => $this->token,
				'home'    => home_url( '/' ),
				'framed'  => isset( $_GET[ self::FRAME_VAR ] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'adminRe' => '^' . preg_quote( admin_url(), '/' ),
			]
		);
	}

	/**
	 * Links for the preview bar.
	 *
	 * @return array<string, string>
	 */
	private function bar_links(): array {
		$editor = admin_url( 'admin.php?page=my-site-hand-builder&view=editor' );

		return [
			'editor'  => $editor,
			'apply'   => add_query_arg( 'msh-action', 'apply', $editor ),
			'discard' => add_query_arg( 'msh-action', 'discard', $editor ),
		];
	}

	/**
	 * The fixed preview bar (not shown inside the admin's frame).
	 *
	 * @return void
	 */
	public function render_bar(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::FRAME_VAR ] ) ) {
			return;
		}

		$this->bar_markup( 'desktop' );
	}

	/**
	 * Bar markup.
	 *
	 * @param string $current Current device.
	 * @return void
	 */
	private function bar_markup( string $current ): void {
		$links = $this->bar_links();
		$here  = remove_query_arg( [ self::DEVICE_VAR, self::FRAME_VAR ] );
		?>
		<div class="msh-pv-bar" role="region" aria-label="<?php esc_attr_e( 'Site preview', 'my-site-hand' ); ?>">
			<p class="msh-pv-label">
				<strong><?php esc_html_e( 'Preview', 'my-site-hand' ); ?></strong>
				<span><?php esc_html_e( 'Only you can see this. Nothing has changed on your site yet.', 'my-site-hand' ); ?></span>
			</p>
			<div class="msh-pv-devices" role="group" aria-label="<?php esc_attr_e( 'Device', 'my-site-hand' ); ?>">
				<?php
				foreach ( [
					'desktop' => __( 'Desktop', 'my-site-hand' ),
					'tablet'  => __( 'Tablet', 'my-site-hand' ),
					'mobile'  => __( 'Mobile', 'my-site-hand' ),
				] as $device => $label ) :
					$url = 'desktop' === $device ? $here : add_query_arg( self::DEVICE_VAR, $device, $here );
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="msh-pv-device<?php echo $device === $current ? ' is-current' : ''; ?>" <?php echo $device === $current ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>
			<div class="msh-pv-actions">
				<a class="msh-pv-btn" href="<?php echo esc_url( $links['editor'] ); ?>"><?php esc_html_e( 'Back to editor', 'my-site-hand' ); ?></a>
				<a class="msh-pv-btn" href="<?php echo esc_url( $links['discard'] ); ?>"><?php esc_html_e( 'Discard', 'my-site-hand' ); ?></a>
				<a class="msh-pv-btn msh-pv-btn--primary" href="<?php echo esc_url( $links['apply'] ); ?>"><?php esc_html_e( 'Apply to my site', 'my-site-hand' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Standalone tablet or mobile view: the bar plus the page in a frame of
	 * the real device width, so the theme's media queries fire.
	 *
	 * @param string $device tablet or mobile.
	 * @return void
	 */
	private function render_device_shell( string $device ): void {
		$frame = add_query_arg( self::FRAME_VAR, '1', remove_query_arg( self::DEVICE_VAR ) );
		$width = self::DEVICES[ $device ];
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php esc_html_e( 'Site preview', 'my-site-hand' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( 'ver', MYSITEHAND_VERSION, MYSITEHAND_URL . 'assets/css/preview-bar.css' ) ); ?>"><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone shell page outside the theme. ?>
</head>
<body class="msh-pv-shell">
<div class="msh-pv-stage">
	<iframe class="msh-pv-frame" src="<?php echo esc_url( $frame ); ?>" style="width:<?php echo (int) $width; ?>px" title="<?php esc_attr_e( 'Site preview', 'my-site-hand' ); ?>"></iframe>
</div>
		<?php $this->bar_markup( $device ); ?>
</body>
</html>
		<?php
	}
}
