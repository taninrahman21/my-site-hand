<?php
/**
 * REST routes for the Site Builder admin screen.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Api;

defined( 'ABSPATH' ) || exit;

use MySiteHand\Audit_Logger;
use MySiteHand\Color_Contrast;
use MySiteHand\Plugin;
use MySiteHand\Site_Builder;

/**
 * Site Builder REST controller.
 *
 * Every route needs manage_options. These are cookie-authenticated admin
 * routes, so WordPress also requires the wp_rest nonce (X-WP-Nonce) before
 * the user is recognised at all. MCP clients use the abilities in
 * Module_Site_Builder instead, authenticated by token.
 */
class Site_Builder_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'my-site-hand/v1';

	/**
	 * Audit logger.
	 *
	 * @var Audit_Logger
	 */
	private Audit_Logger $audit;

	/**
	 * Constructor.
	 *
	 * @param Audit_Logger $audit Audit logger.
	 */
	public function __construct( Audit_Logger $audit ) {
		$this->audit = $audit;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$admin = [ $this, 'require_manage_options' ];

		register_rest_route(
			self::NAMESPACE,
			'/builder/sites',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_sites' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/sites/(?P<slug>[a-z0-9_-]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_site' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/staged',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_staged' ],
					'permission_callback' => $admin,
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_staged' ],
					'permission_callback' => $admin,
					'args'                => [
						'slug' => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_staged' ],
					'permission_callback' => $admin,
					'args'                => $this->change_args(),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'discard_staged' ],
					'permission_callback' => $admin,
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/staged/schema',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'staged_schema' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/ai/design',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'ai_design' ],
				'permission_callback' => $admin,
				'args'                => [
					'request' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'preview_url' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/apply',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'apply_plan' ],
					'permission_callback' => $admin,
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'apply' ],
					'permission_callback' => $admin,
					'args'                => [
						'confirm' => [
							'type'     => 'boolean',
							'required' => true,
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/live',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'live' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/live/customize',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'customize_live' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/snapshots',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_snapshots' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/snapshots/(?P<id>[a-f0-9-]{36})/restore',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'restore_snapshot' ],
				'permission_callback' => $admin,
				'args'                => [
					'confirm' => [
						'type'     => 'boolean',
						'required' => true,
					],
				],
			]
		);

		// Optional AI. Each route refuses with a clear message when no key is
		// stored; the editor never calls them in that case.
		register_rest_route(
			self::NAMESPACE,
			'/builder/ai/fill',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'ai_fill' ],
				'permission_callback' => $admin,
				'args'                => [
					'business_type' => [
						'type'     => 'string',
						'required' => true,
					],
					'name'          => [ 'type' => 'string' ],
					'description'   => [ 'type' => 'string' ],
					'tone'          => [
						'type' => 'string',
						'enum' => \MySiteHand\Site_Builder_AI::TONES,
					],
					'replace_edits' => [ 'type' => 'boolean' ],
					'only_groups'   => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/ai/rewrite',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'ai_rewrite' ],
				'permission_callback' => $admin,
				'args'                => [
					'slot'        => [
						'type'     => 'string',
						'required' => true,
					],
					'current'     => [ 'type' => 'string' ],
					'instruction' => [ 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/ai/colors',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'ai_colors' ],
				'permission_callback' => $admin,
				'args'                => [
					'business_type' => [ 'type' => 'string' ],
					'description'   => [ 'type' => 'string' ],
					'tone'          => [ 'type' => 'string' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/welcome',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'dismiss_welcome' ],
				'permission_callback' => $admin,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/palette',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'palette_from_color' ],
				'permission_callback' => $admin,
				'args'                => [
					'color' => [
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static fn( $value ): bool => (bool) sanitize_hex_color( (string) $value ),
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/builder/theme',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'install_block_theme' ],
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ) && current_user_can( 'switch_themes' ),
				'args'                => [
					'confirm' => [
						'type'     => 'boolean',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Shared args for edits.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function change_args(): array {
		return [
			'values'       => [
				'type' => 'object',
			],
			'reset'        => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'palette'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
			'font_pair'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
			'custom_color' => [
				'type' => 'string',
			],
			'design'       => [
				'type' => 'object',
			],
			'layout'       => [
				'type' => 'object',
			],
		];
	}

	/**
	 * Collect edit params that were actually sent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function changes_from( \WP_REST_Request $request ): array {
		$changes = [];

		foreach ( [ 'values', 'reset', 'palette', 'font_pair', 'custom_color', 'design', 'layout' ] as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$changes[ $key ] = $request->get_param( $key );
			}
		}

		return $changes;
	}

	/**
	 * The site builder service.
	 *
	 * @return Site_Builder
	 */
	private function builder(): Site_Builder {
		return Plugin::get_instance()->get_site_builder();
	}

	/**
	 * GET /builder/sites
	 *
	 * @return \WP_REST_Response
	 */
	public function list_sites(): \WP_REST_Response {
		return rest_ensure_response( $this->builder()->list_sites() );
	}

	/**
	 * GET /builder/sites/<slug>
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site( \WP_REST_Request $request ) {
		$details = $this->builder()->get_site_details( (string) $request['slug'] );

		return is_wp_error( $details ) ? $details : rest_ensure_response( $details );
	}

	/**
	 * GET /builder/staged
	 *
	 * @return \WP_REST_Response
	 */
	public function get_staged(): \WP_REST_Response {
		return rest_ensure_response( [ 'staged' => $this->staged_payload() ] );
	}

	/**
	 * POST /builder/staged: start from a starter site.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_staged( \WP_REST_Request $request ) {
		$slug   = (string) $request['slug'];
		$result = $this->builder()->stage( get_current_user_id(), $slug );

		$this->log(
			'my-site-hand/stage-starter-site',
			[ 'slug' => $slug ],
			$result,
			/* translators: %s: starter site slug */
			sprintf( __( 'Staged the "%s" starter site. Nothing on the live site changed.', 'my-site-hand' ), $slug )
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'staged' => $this->staged_payload() ] );
	}

	/**
	 * PATCH /builder/staged: autosave from the editor.
	 *
	 * Autosaves are not written to the audit log: the editor sends one every
	 * time the user pauses typing, which would bury everything else. Staging,
	 * discarding, previewing, applying, undoing and every AI call are logged.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_staged( \WP_REST_Request $request ) {
		$result = $this->builder()->update( get_current_user_id(), $this->changes_from( $request ) );

		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'staged' => $this->staged_payload() ] );
	}

	/**
	 * DELETE /builder/staged
	 *
	 * @return \WP_REST_Response
	 */
	public function discard_staged(): \WP_REST_Response {
		$had = $this->builder()->discard( get_current_user_id() );
		Plugin::get_instance()->get_site_preview()->revoke( get_current_user_id() );

		if ( $had ) {
			$this->log( 'my-site-hand/discard-staged-site', [], true, __( 'Discarded the staged site. Nothing on the live site changed.', 'my-site-hand' ) );
		}

		return rest_ensure_response( [ 'staged' => null ] );
	}

	/**
	 * POST /builder/preview: the user's private preview URL.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview_url() {
		$user_id = get_current_user_id();

		if ( null === $this->builder()->get_staged( $user_id ) ) {
			return $this->builder()->no_staged_error();
		}

		if ( ! wp_is_block_theme() ) {
			return new \WP_Error( 'mysitehand_needs_block_theme', __( 'Preview needs a block theme. Switch to Twenty Twenty-Five using the notice at the top of this page.', 'my-site-hand' ), [ 'status' => 409 ] );
		}

		return rest_ensure_response(
			[
				'url'     => Plugin::get_instance()->get_site_preview()->get_url( $user_id ),
				'devices' => \MySiteHand\Site_Preview::DEVICES,
			]
		);
	}

	/**
	 * GET /builder/apply: exactly what Apply would change.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply_plan() {
		$plan = Plugin::get_instance()->get_site_applier()->plan( get_current_user_id() );

		return is_wp_error( $plan ) ? $plan : rest_ensure_response( $plan );
	}

	/**
	 * POST /builder/apply: make the staged build live. Needs confirm: true.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function apply( \WP_REST_Request $request ) {
		if ( true !== $request['confirm'] ) {
			return new \WP_Error( 'mysitehand_confirm_required', __( 'Apply needs your confirmation.', 'my-site-hand' ), [ 'status' => 400 ] );
		}

		$staged = $this->staged_payload();
		$result = Plugin::get_instance()->get_site_applier()->apply( get_current_user_id() );

		$this->log(
			'my-site-hand/apply-staged-site',
			[ 'slug' => $staged['slug'] ?? '' ],
			$result,
			is_wp_error( $result ) ? '' : sprintf(
				/* translators: 1: starter site name, 2: number of pages */
				__( 'Applied the %1$s starter site: %2$d pages published, header, footer and styles saved. A snapshot was taken for Undo.', 'my-site-hand' ),
				$result['starter'],
				$result['pages']
			)
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response(
			[
				'snapshot' => $result,
				'live'     => Plugin::get_instance()->get_site_applier()->live_summary(),
			]
		);
	}

	/**
	 * GET /builder/live: the starter site that is live now, or null.
	 *
	 * @return \WP_REST_Response
	 */
	public function live(): \WP_REST_Response {
		return rest_ensure_response( Plugin::get_instance()->get_site_applier()->live_summary() );
	}

	/**
	 * POST /builder/live/customize: stage the live site again with its edits.
	 * Changes only the user's private draft.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function customize_live() {
		$result = Plugin::get_instance()->get_site_applier()->customize_live( get_current_user_id() );

		$this->log(
			'my-site-hand/customize-live-site',
			[],
			$result,
			is_wp_error( $result ) ? '' : sprintf(
				/* translators: %s: starter site name */
				__( 'Staged the live %s site again for editing. Nothing on the live site changed.', 'my-site-hand' ),
				$result['staged']['name']
			)
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * GET /builder/snapshots
	 *
	 * @return \WP_REST_Response
	 */
	public function list_snapshots(): \WP_REST_Response {
		return rest_ensure_response( Plugin::get_instance()->get_site_applier()->list_snapshots() );
	}

	/**
	 * POST /builder/snapshots/<id>/restore: Undo. Needs confirm: true.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function restore_snapshot( \WP_REST_Request $request ) {
		if ( true !== $request['confirm'] ) {
			return new \WP_Error( 'mysitehand_confirm_required', __( 'Undo needs your confirmation.', 'my-site-hand' ), [ 'status' => 400 ] );
		}

		$result = Plugin::get_instance()->get_site_applier()->restore( (string) $request['id'] );

		$this->log(
			'my-site-hand/restore-snapshot',
			[ 'snapshot' => (string) $request['id'] ],
			$result,
			is_wp_error( $result ) ? '' : sprintf(
				/* translators: %d: number of builds undone */
				_n( 'Undid %d starter site build. Created pages, menu and images were moved to the trash.', 'Undid %d starter site builds. Created pages, menus and images were moved to the trash.', count( $result['undone'] ), 'my-site-hand' ),
				count( $result['undone'] )
			)
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * The AI helper.
	 *
	 * @return \MySiteHand\Site_Builder_AI
	 */
	private function ai(): \MySiteHand\Site_Builder_AI {
		$plugin = Plugin::get_instance();
		return new \MySiteHand\Site_Builder_AI( $plugin->get_ai_manager(), $plugin->get_site_builder() );
	}

	/**
	 * Log an AI call. Records what was asked for and what it cost; never the
	 * key, and never the generated text.
	 *
	 * @param string               $what   fill, rewrite or colors.
	 * @param array<string, mixed> $input  Input worth recording.
	 * @param mixed                $result Result or WP_Error.
	 * @return void
	 */
	private function log_ai( string $what, array $input, $result ): void {
		$this->log(
			'my-site-hand/site-builder-ai-' . $what,
			$input,
			$result,
			is_wp_error( $result ) ? '' : sprintf(
				/* translators: 1: what the AI did, 2: cost in USD */
				__( 'AI %1$s suggestion returned for review (cost %2$s). Nothing was saved.', 'my-site-hand' ),
				$what,
				\MySiteHand\AI_Manager::format_usd( (float) ( $result['cost'] ?? 0 ) )
			)
		);
	}

	/**
	 * POST /builder/ai/fill
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ai_fill( \WP_REST_Request $request ) {
		$brief  = [
			'business_type' => (string) $request['business_type'],
			'name'          => (string) $request['name'],
			'description'   => (string) $request['description'],
			'tone'          => (string) $request['tone'],
		];
		$result = $this->ai()->fill( get_current_user_id(), $brief, (bool) $request['replace_edits'], array_map( 'sanitize_text_field', (array) $request['only_groups'] ) );

		$this->log_ai( 'fill', \MySiteHand\Site_Builder_AI::clean_brief( $brief ) + [ 'replace_edits' => (bool) $request['replace_edits'] ], $result );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * POST /builder/ai/design: a redesign proposal, for review.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ai_design( \WP_REST_Request $request ) {
		$text   = sanitize_textarea_field( mb_substr( (string) $request['request'], 0, 600 ) );
		$result = $this->ai()->design( get_current_user_id(), $text );

		$this->log_ai( 'design', [ 'request' => $text ], $result );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * GET /builder/staged/schema: the editor's fields, sections and layout.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function staged_schema() {
		$schema = $this->builder()->get_editor_schema( get_current_user_id() );

		return is_wp_error( $schema ) ? $schema : rest_ensure_response( $schema );
	}

	/**
	 * POST /builder/ai/rewrite
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ai_rewrite( \WP_REST_Request $request ) {
		$slot   = sanitize_text_field( (string) $request['slot'] );
		$result = $this->ai()->rewrite(
			get_current_user_id(),
			$slot,
			wp_kses( (string) $request['current'], \MySiteHand\Slot_Renderer::richtext_allowed_html() ),
			sanitize_text_field( mb_substr( (string) $request['instruction'], 0, 120 ) )
		);

		$this->log_ai( 'rewrite', [ 'slot' => $slot ], $result );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * POST /builder/ai/colors
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ai_colors( \WP_REST_Request $request ) {
		$brief  = [
			'business_type' => (string) $request['business_type'],
			'description'   => (string) $request['description'],
			'tone'          => (string) $request['tone'],
		];
		$result = $this->ai()->colors( get_current_user_id(), $brief );

		$this->log_ai( 'colors', \MySiteHand\Site_Builder_AI::clean_brief( $brief ), $result );

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * DELETE /builder/welcome: hide the welcome panel for this user.
	 *
	 * @return \WP_REST_Response
	 */
	public function dismiss_welcome(): \WP_REST_Response {
		update_user_meta( get_current_user_id(), 'mysitehand_welcome_dismissed', 1 );

		return rest_ensure_response( [ 'dismissed' => true ] );
	}

	/**
	 * GET /builder/palette?color=: a full palette from one brand colour.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function palette_from_color( \WP_REST_Request $request ): \WP_REST_Response {
		$colors = Color_Contrast::palette_from_brand( (string) sanitize_hex_color( (string) $request['color'] ) );

		return rest_ensure_response(
			[
				'colors'  => $colors,
				'passes'  => null !== $colors && [] === Color_Contrast::failing_pairs( $colors ),
			]
		);
	}

	/**
	 * POST /builder/theme: install (if needed) and activate Twenty Twenty-Five.
	 *
	 * Only ever runs after the user confirmed in the dialog. The download
	 * comes from WordPress.org through core's own upgrader.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function install_block_theme( \WP_REST_Request $request ) {
		if ( true !== $request['confirm'] ) {
			return new \WP_Error( 'mysitehand_confirm_required', __( 'Please confirm before changing your theme.', 'my-site-hand' ), [ 'status' => 400 ] );
		}

		$result = \MySiteHand\Theme_Installer::install_and_activate( 'twentytwentyfive' );

		$this->log(
			'my-site-hand/install-block-theme',
			[ 'theme' => 'twentytwentyfive' ],
			$result,
			__( 'Installed and activated Twenty Twenty-Five.', 'my-site-hand' )
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'activated' => get_stylesheet() ] );
	}

	/**
	 * Current user's staged summary.
	 *
	 * @return array<string, mixed>|null
	 */
	private function staged_payload(): ?array {
		return $this->builder()->get_staged( get_current_user_id() );
	}

	/**
	 * Write an audit log row for an admin action.
	 *
	 * @param string               $ability Ability-style name.
	 * @param array<string, mixed> $input   Input worth recording.
	 * @param mixed                $result  Result or WP_Error.
	 * @param string               $summary Success summary.
	 * @return void
	 */
	public function log( string $ability, array $input, $result, string $summary ): void {
		$this->audit->log(
			[
				'token_id'       => null,
				'user_id'        => get_current_user_id(),
				'ability_name'   => $ability,
				'input'          => $input,
				'result_status'  => is_wp_error( $result ) ? 'error' : 'success',
				'result_summary' => is_wp_error( $result ) ? $result->get_error_message() : $summary,
				'duration_ms'    => 0,
			]
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function require_manage_options(): bool {
		return current_user_can( 'manage_options' );
	}
}
