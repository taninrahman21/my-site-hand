<?php
/**
 * Users module — user management abilities.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Module Users class.
 *
 * Provides abilities for listing, creating, and updating WordPress users
 * and roles. Never exposes password hashes.
 */
class Module_Users extends Module_Base {

	/**
	 * {@inheritdoc}
	 */
	public function get_module_name(): string {
		return 'users';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ability_names(): array {
		return [
			'my-site-hand/list-users',
			'my-site-hand/get-user',
			'my-site-hand/update-user-role',
			'my-site-hand/list-roles',
			'my-site-hand/get-user-stats',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_abilities(): void {
		$this->register_list_users();
		$this->register_get_user();
		$this->register_update_user_role();
		$this->register_list_roles();
		$this->register_get_user_stats();
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-users
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-users ability.
	 *
	 * @return void
	 */
	private function register_list_users(): void {
		$this->register(
			'my-site-hand/list-users',
			[
				'label'               => __( 'List Users', 'my-site-hand' ),
				'description'         => __( 'List WordPress users with filtering and sorting.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'role'          => [ 'type' => 'string', 'description' => 'Filter by role slug' ],
						'limit'         => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
						'offset'        => [ 'type' => 'integer', 'default' => 0 ],
						'search'        => [ 'type' => 'string' ],
						'orderby'       => [ 'type' => 'string', 'enum' => [ 'registered', 'login', 'display_name', 'email' ], 'default' => 'registered' ],
						'inactive_days' => [ 'type' => 'integer', 'description' => 'Filter users with no login in N days' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'list_users' );
					}
					return current_user_can( 'list_users' );
				},
				'execute_callback'    => [ $this, 'execute_list_users' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-users.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_list_users( array $input ): array {
		$args = [
			'number'  => min( absint( $input['limit'] ?? 20 ), 100 ),
			'offset'  => absint( $input['offset'] ?? 0 ),
			'orderby' => sanitize_text_field( $input['orderby'] ?? 'registered' ),
			'order'   => 'DESC',
		];

		if ( ! empty( $input['role'] ) ) {
			$args['role'] = sanitize_text_field( $input['role'] );
		}

		if ( ! empty( $input['search'] ) ) {
			$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}

		$users  = get_users( $args );
		$result = [];

		foreach ( $users as $user ) {
			// Filter by inactive days if requested.
			if ( ! empty( $input['inactive_days'] ) ) {
				$last_login = get_user_meta( $user->ID, 'mysitehand_last_login', true );
				if ( $last_login ) {
					$days_since = ( time() - strtotime( $last_login ) ) / DAY_IN_SECONDS;
					if ( $days_since < (int) $input['inactive_days'] ) {
						continue;
					}
				}
			}

			$result[] = $this->format_user( $user );
		}

		return [
			'users' => $result,
			'count' => count( $result ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-user
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-user ability.
	 *
	 * @return void
	 */
	private function register_get_user(): void {
		$this->register(
			'my-site-hand/get-user',
			[
				'label'               => __( 'Get User', 'my-site-hand' ),
				'description'         => __( 'Get a WordPress user profile (password hash never included).', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'user_id' ],
					'properties' => [
						'user_id' => [ 'type' => 'integer' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'list_users' );
					}
					return current_user_can( 'list_users' );
				},
				'execute_callback'    => [ $this, 'execute_get_user' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-user.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_get_user( array $input ): array|\WP_Error {
		$user = get_user_by( 'id', absint( $input['user_id'] ) );

		if ( ! $user ) {
			return $this->error( __( 'User not found.', 'my-site-hand' ), 'not_found' );
		}

		$post_count = count_user_posts( $user->ID );
		$meta       = get_user_meta( $user->ID );

		// Safe meta — exclude password-related and session keys.
		$safe_meta = [];
		$meta_blocklist = [
			'session_tokens',
			'wp_user_level',
			'_woocommerce_persistent_cart',
		];

		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, $meta_blocklist, true ) || str_starts_with( $key, 'wp_capabilities' ) ) {
				continue;
			}
			$safe_meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
		}

		return [
			'id'           => $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'url'          => $user->user_url,
			'registered'   => $user->user_registered,
			'roles'        => $user->roles,
			'post_count'   => (int) $post_count,
			'meta'         => $safe_meta,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_update-user-role
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_update-user-role ability.
	 *
	 * @return void
	 */
	private function register_update_user_role(): void {
		$this->register(
			'my-site-hand/update-user-role',
			[
				'label'               => __( 'Update User Role', 'my-site-hand' ),
				'description'         => __( 'Change a WordPress user\'s role.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'user_id', 'role' ],
					'properties' => [
						'user_id' => [ 'type' => 'integer' ],
						'role'    => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_users' );
					}
					return current_user_can( 'edit_users' );
				},
				'execute_callback'    => [ $this, 'execute_update_user_role' ],
				'annotations'         => [
					'meta' => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_update-user-role.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_update_user_role( array $input ): array|\WP_Error {
		$target_user_id = absint( $input['user_id'] );
		$new_role       = sanitize_text_field( $input['role'] );

		$target_user = get_user_by( 'id', $target_user_id );
		if ( ! $target_user ) {
			return $this->error( __( 'User not found.', 'my-site-hand' ), 'not_found' );
		}

		// Validate role exists.
		$wp_roles = wp_roles();
		if ( ! isset( $wp_roles->roles[ $new_role ] ) ) {
			return $this->error(
				sprintf(
					/* translators: %s: role name */
					__( 'Role "%s" does not exist.', 'my-site-hand' ),
					$new_role
				),
				'invalid_role'
			);
		}

		// Prevent demoting admins unless acting user is also admin.
		if ( in_array( 'administrator', $target_user->roles, true ) && 'administrator' !== $new_role ) {
			$current_user = wp_get_current_user();
			if ( ! in_array( 'administrator', $current_user->roles, true ) ) {
				return $this->error(
					__( 'Only administrators can demote other administrators.', 'my-site-hand' ),
					'insufficient_permissions'
				);
			}
		}

		$target_user->set_role( $new_role );

		return [
			'updated'      => true,
			'user_id'      => $target_user_id,
			'username'     => $target_user->user_login,
			'new_role'     => $new_role,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-roles
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-roles ability.
	 *
	 * @return void
	 */
	private function register_list_roles(): void {
		$this->register(
			'my-site-hand/list-roles',
			[
				'label'            => __( 'List Roles', 'my-site-hand' ),
				'description'      => __( 'List all registered WordPress roles and their capabilities.', 'my-site-hand' ),
				'execute_callback' => [ $this, 'execute_list_roles' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-roles.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<int, array<string, mixed>>
	 */
	public function execute_list_roles( array $input ): array {
		$wp_roles = wp_roles();
		$roles    = [];

		foreach ( $wp_roles->roles as $role_slug => $role_data ) {
			$roles[] = [
				'slug'         => $role_slug,
				'name'         => $role_data['name'],
				'capabilities' => array_keys( array_filter( $role_data['capabilities'] ) ),
				'user_count'   => count( get_users( [ 'role' => $role_slug, 'fields' => 'ids' ] ) ),
			];
		}

		return $roles;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-user-stats
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-user-stats ability.
	 *
	 * @return void
	 */
	private function register_get_user_stats(): void {
		$this->register(
			'my-site-hand/get-user-stats',
			[
				'label'               => __( 'User Stats', 'my-site-hand' ),
				'description'         => __( 'Get aggregate user statistics including counts by role and registration trends.', 'my-site-hand' ),
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'list_users' );
					}
					return current_user_can( 'list_users' );
				},
				'execute_callback'    => [ $this, 'execute_get_user_stats' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-user-stats.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_get_user_stats( array $input ): array {
		$cache_key = 'user_stats';
		$cached    = get_transient( 'MYSITEHAND_' . $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$wp_roles   = wp_roles();
		$by_role    = [];
		$total_users = 0;

		foreach ( array_keys( $wp_roles->roles ) as $role ) {
			$count          = count( get_users( [ 'role' => $role, 'fields' => 'ids' ] ) );
			$by_role[ $role ] = $count;
			$total_users    += $count;
		}

		// Registrations this month.
		$month_start = gmdate( 'Y-m-01 00:00:00' );
		$week_start  = gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );

		$registered_month = count( get_users( [
			'date_query' => [ [ 'after' => $month_start ] ],
			'fields'     => 'ids',
		] ) );

		$registered_week = count( get_users( [
			'date_query' => [ [ 'after' => $week_start ] ],
			'fields'     => 'ids',
		] ) );

		$stats = [
			'total_users'         => $total_users,
			'by_role'             => $by_role,
			'registered_this_month' => $registered_month,
			'registered_this_week'  => $registered_week,
		];

		set_transient( 'MYSITEHAND_' . $cache_key, $stats, 15 * MINUTE_IN_SECONDS );

		return $stats;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Format a WP_User as a safe summary array (no password hash).
	 *
	 * @param \WP_User $user User object.
	 * @return array<string, mixed>
	 */
	private function format_user( \WP_User $user ): array {
		return [
			'id'           => $user->ID,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'role'         => implode( ', ', $user->roles ),
			'registered'   => $user->user_registered,
			'post_count'   => (int) count_user_posts( $user->ID ),
		];
	}
}




