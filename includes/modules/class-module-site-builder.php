<?php
/**
 * Site Builder module: starter site abilities for MCP clients.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

use MySiteHand\Plugin;

/**
 * Module Site Builder class.
 *
 * Lets an MCP client (Claude Desktop, Cursor, VS Code) build a site from a
 * starter site: read the slots, fill them, stage, apply and undo. The client
 * brings its own AI; none of this needs an AI key in the plugin. The client
 * only ever sends slot values, which the plugin escapes into the bundled
 * block markup exactly as it does for the admin editor.
 */
class Module_Site_Builder extends Module_Base {

	/**
	 * User the current call runs as, set by the permission check.
	 *
	 * @var int
	 */
	private int $user_id = 0;

	/**
	 * {@inheritdoc}
	 */
	public function get_module_name(): string {
		return 'site-builder';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ability_names(): array {
		return [
			'my-site-hand/list-starter-sites',
			'my-site-hand/get-starter-site',
			'my-site-hand/stage-starter-site',
			'my-site-hand/update-staged-slots',
			'my-site-hand/apply-staged-site',
			'my-site-hand/list-snapshots',
			'my-site-hand/restore-snapshot',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_abilities(): void {
		$permission = [ $this, 'can_build' ];

		$design = [
			'values'       => [
				'type'        => 'object',
				'description' => 'Slot id => value. Get slot ids, types and max lengths from get-starter-site. Text is plain text; richtext allows <strong>, <em>, <a href> and <br> only; image slots take a media library attachment id. Unknown ids are ignored and values are cut to the max length.',
			],
			'palette'      => [
				'type'        => 'string',
				'description' => 'Preset palette id from get-starter-site.',
			],
			'font_pair'    => [
				'type'        => 'string',
				'description' => 'Preset font pair id from get-starter-site.',
			],
			'custom_color' => [
				'type'        => 'string',
				'description' => 'Optional brand colour as #rrggbb. A full palette is generated from it and adjusted until all text passes WCAG AA. Overrides palette.',
			],
			'design'       => [
				'type'        => 'object',
				'description' => 'Optional. mode: auto|light|dark. corners: site|square|soft|rounded|pill. spacing: site|compact|comfortable|airy. palette: all seven colours {base, surface, accent, contrast, muted, primary, on-primary} as #rrggbb, adjusted to pass WCAG AA.',
			],
			'layout'       => [
				'type'        => 'object',
				'description' => 'Optional. Page slug => complete ordered list of sections. Page sections: {"id": "<section id>", "hidden": false}. Add a library section with {"id": "new", "type": "<type>"}. Section ids and library types come from get-starter-site. Added sections bring new slots ("<page>.<id>.<field>") you can then fill.',
			],
		];

		$this->register(
			'my-site-hand/list-starter-sites',
			[
				'label'               => __( 'List Starter Sites', 'my-site-hand' ),
				'description'         => __( 'List the ready-made starter sites bundled with the plugin (name, category, pages, screenshots), and which one is live on the site now. Read only.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'execute_callback'    => [ $this, 'execute_list' ],
				'permission_callback' => $permission,
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);

		$this->register(
			'my-site-hand/get-starter-site',
			[
				'label'               => __( 'Get Starter Site', 'my-site-hand' ),
				'description'         => __( 'Get one starter site: its pages and their sections, every editable slot (id, type, max length, default text, writing hint), preset palettes, font pairs, design options and the library of sections that can be added. Use this before staging. Read only.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug' => [
							'type'        => 'string',
							'description' => 'Starter site slug, e.g. clinic.',
						],
					],
					'required'   => [ 'slug' ],
				],
				'execute_callback'    => [ $this, 'execute_get' ],
				'permission_callback' => $permission,
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);

		$this->register(
			'my-site-hand/stage-starter-site',
			[
				'label'               => __( 'Stage Starter Site', 'my-site-hand' ),
				'description'         => __( 'Start a private draft from a starter site, with optional slot values, palette and fonts. Replaces your previous draft. Does NOT change the live site. Set from_live to true instead of a slug to continue the site that is live now, with the text, colours and sections it was applied with; applying that draft replaces its pages instead of adding a second set. Returns a preview URL that works in a browser where you are logged in to WordPress as this user.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'slug'      => [ 'type' => 'string' ],
						'from_live' => [
							'type'        => 'boolean',
							'description' => 'Continue the live starter site (see list-starter-sites "live") instead of starting fresh.',
						],
					] + $design,
				],
				'execute_callback'    => [ $this, 'execute_stage' ],
				'permission_callback' => $permission,
				'annotations'         => [
					'readonly' => false,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);

		$this->register(
			'my-site-hand/update-staged-slots',
			[
				'label'               => __( 'Update Staged Slots', 'my-site-hand' ),
				'description'         => __( 'Change slot values, palette or fonts in your private draft. Only the values you send change; "reset" puts listed slots back to their default. Does NOT change the live site.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => $design + [
						'reset' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Slot ids to put back to their default.',
						],
					],
				],
				'execute_callback'    => [ $this, 'execute_update' ],
				'permission_callback' => $permission,
				'annotations'         => [
					'readonly' => false,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);

		$this->register(
			'my-site-hand/apply-staged-site',
			[
				'label'               => __( 'Apply Staged Site', 'my-site-hand' ),
				'description'         => __( 'CHANGES THE LIVE SITE. Publishes the draft: creates and publishes its pages, sets the front page, site title and tagline, replaces the header, footer and global styles of the active block theme, creates a menu and adds images to the media library. A snapshot is taken first so restore-snapshot can undo it. Requires "confirm": true. Ask the site owner before calling this.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						// No "type": the registry would cast "false" to true.
						// const true is checked strictly in execute_apply().
						'confirm' => [
							'const'       => true,
							'description' => 'Must be the JSON value true. Confirms the site owner agreed to change the live site.',
						],
					],
					'required'   => [ 'confirm' ],
				],
				'execute_callback'    => [ $this, 'execute_apply' ],
				'permission_callback' => $permission,
				'annotations'         => [
					'readonly'    => false,
					'destructive' => true,
					'meta'        => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);

		$this->register(
			'my-site-hand/list-snapshots',
			[
				'label'               => __( 'List Site Snapshots', 'my-site-hand' ),
				'description'         => __( 'List the last five starter site applies (date, starter site, applied or undone) with the snapshot id needed by restore-snapshot. Read only.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [],
				],
				'execute_callback'    => [ $this, 'execute_list_snapshots' ],
				'permission_callback' => $permission,
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);

		$this->register(
			'my-site-hand/restore-snapshot',
			[
				'label'               => __( 'Restore Site Snapshot', 'my-site-hand' ),
				'description'         => __( 'CHANGES THE LIVE SITE. Undoes a starter site apply: restores the previous front page, title, tagline, header, footer and global styles, and moves the pages, menu and images it created to the trash (nothing is permanently deleted). Later applies are undone first. Requires "confirm": true.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'snapshot_id' => [
							'type'        => 'string',
							'description' => 'Snapshot id from list-snapshots.',
						],
						'confirm'     => [
							'const'       => true,
							'description' => 'Must be the JSON value true.',
						],
					],
					'required'   => [ 'snapshot_id', 'confirm' ],
				],
				'execute_callback'    => [ $this, 'execute_restore' ],
				'permission_callback' => $permission,
				'annotations'         => [
					'readonly'    => false,
					'destructive' => true,
					'meta'        => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Only administrators may build sites. The call then runs as that user,
	 * so the draft belongs to them and core's content filters treat the
	 * writes exactly as if they had been made in the admin.
	 *
	 * @param int $user_id Token owner.
	 * @return bool
	 */
	public function can_build( int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( ! $user_id || ! $this->user_can( 'manage_options', $user_id ) ) {
			return false;
		}

		$this->user_id = $user_id;
		if ( get_current_user_id() !== $user_id ) {
			wp_set_current_user( $user_id );
		}

		return true;
	}

	/**
	 * Collect design changes from input.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	private function changes( array $input ): array {
		return array_intersect_key( $input, array_flip( [ 'values', 'reset', 'palette', 'font_pair', 'custom_color', 'design', 'layout' ] ) );
	}

	/**
	 * Staged summary plus preview URL.
	 *
	 * @param array<string, mixed> $staged Staged summary.
	 * @return array<string, mixed>
	 */
	private function staged_result( array $staged ): array {
		$staged['preview_url'] = wp_is_block_theme() ? Plugin::get_instance()->get_site_preview()->get_url( $this->user_id ) : '';

		$schema = Plugin::get_instance()->get_site_builder()->get_editor_schema( $this->user_id );
		if ( ! is_wp_error( $schema ) ) {
			$staged['layout']      = $schema['layout'];
			$staged['added_slots'] = array_values( array_filter( $schema['slots'], static fn( $slot ) => (bool) preg_match( '/\.x\d+[.:]|\.x\d+$/', $slot['id'] ) ) );
		}
		$staged['editor_url']  = admin_url( 'admin.php?page=my-site-hand-builder&view=editor' );
		$staged['note']        = __( 'This is a private draft. Nothing on the live site has changed.', 'my-site-hand' );
		unset( $staged['images'] );

		return $staged;
	}

	/**
	 * Execute list-starter-sites.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public function execute_list( array $input ): array {
		$sites = Plugin::get_instance()->get_site_builder()->list_sites();

		return [
			'starter_sites'  => $sites,
			'count'          => count( $sites ),
			'is_block_theme' => wp_is_block_theme(),
			'live'           => Plugin::get_instance()->get_site_applier()->live_summary(),
		];
	}

	/**
	 * Execute get-starter-site.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_get( array $input ) {
		return Plugin::get_instance()->get_site_builder()->get_site_details( sanitize_key( (string) ( $input['slug'] ?? '' ) ) );
	}

	/**
	 * Execute stage-starter-site.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_stage( array $input ) {
		if ( true === ( $input['from_live'] ?? null ) ) {
			$live = Plugin::get_instance()->get_site_applier()->customize_live( $this->user_id );
			if ( is_wp_error( $live ) ) {
				return $live;
			}
			$changes = $this->changes( $input );
			$staged  = $changes ? Plugin::get_instance()->get_site_builder()->update( $this->user_id, $changes ) : $live['staged'];

			return is_wp_error( $staged ) ? $staged : $this->staged_result( $staged ) + [ 'continues_live_site' => true ];
		}

		if ( empty( $input['slug'] ) ) {
			return $this->error( __( 'Give a starter site slug, or set from_live to true.', 'my-site-hand' ), 'missing_slug' );
		}

		$staged = Plugin::get_instance()->get_site_builder()->stage( $this->user_id, sanitize_key( (string) ( $input['slug'] ?? '' ) ), $this->changes( $input ) );

		return is_wp_error( $staged ) ? $staged : $this->staged_result( $staged );
	}

	/**
	 * Execute update-staged-slots.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_update( array $input ) {
		$staged = Plugin::get_instance()->get_site_builder()->update( $this->user_id, $this->changes( $input ) );

		return is_wp_error( $staged ) ? $staged : $this->staged_result( $staged );
	}

	/**
	 * Execute apply-staged-site.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_apply( array $input ) {
		if ( true !== ( $input['confirm'] ?? null ) ) {
			return $this->error( __( 'Applying changes the live site, so "confirm": true is required. Ask the site owner first.', 'my-site-hand' ), 'confirm_required' );
		}

		$result = Plugin::get_instance()->get_site_applier()->apply( $this->user_id );

		return is_wp_error( $result ) ? $result : $this->success( $result, __( 'Applied. Use restore-snapshot with this id to undo.', 'my-site-hand' ) );
	}

	/**
	 * Execute list-snapshots.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public function execute_list_snapshots( array $input ): array {
		return [ 'snapshots' => Plugin::get_instance()->get_site_applier()->list_snapshots() ];
	}

	/**
	 * Execute restore-snapshot.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_restore( array $input ) {
		if ( true !== ( $input['confirm'] ?? null ) ) {
			return $this->error( __( 'Restoring changes the live site, so "confirm": true is required.', 'my-site-hand' ), 'confirm_required' );
		}

		$result = Plugin::get_instance()->get_site_applier()->restore( sanitize_text_field( (string) ( $input['snapshot_id'] ?? '' ) ) );

		return is_wp_error( $result ) ? $result : $this->success( $result, __( 'Undone. Created content is in the trash.', 'my-site-hand' ) );
	}
}
