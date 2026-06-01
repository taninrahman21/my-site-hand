<?php
/**
 * Content module — post, page, and CPT abilities.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Module Content class.
 *
 * Provides abilities for listing, reading, creating, updating, and deleting
 * WordPress posts, pages, and custom post types. All 9 abilities are fully implemented.
 */
class Module_Content extends Module_Base {

	/**
	 * {@inheritdoc}
	 */
	public function get_module_name(): string {
		return 'content';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ability_names(): array {
		return [
			'my-site-hand/list-posts',
			'my-site-hand/get-post',
			'my-site-hand/create-post',
			'my-site-hand/update-post',
			'my-site-hand/delete-post',
			'my-site-hand/bulk-update-posts',
			'my-site-hand/list-post-types',
			'my-site-hand/list-taxonomies',
			'my-site-hand/get-post-revisions',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_abilities(): void {
		$this->register_list_posts();
		$this->register_get_post();
		$this->register_create_post();
		$this->register_update_post();
		$this->register_delete_post();
		$this->register_bulk_update_posts();
		$this->register_list_post_types();
		$this->register_list_taxonomies();
		$this->register_get_post_revisions();
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-posts
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-posts ability.
	 *
	 * @return void
	 */
	private function register_list_posts(): void {
		$this->register(
			'my-site-hand/list-posts',
			[
				'label'            => __( 'List Posts', 'my-site-hand' ),
				'description'      => __( 'List WordPress posts with filtering, sorting, and pagination.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string', 'default' => 'post', 'description' => 'Post type slug' ],
						'status'    => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ], 'default' => 'publish' ],
						'limit'     => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
						'offset'    => [ 'type' => 'integer', 'default' => 0, 'minimum' => 0 ],
						'category'  => [ 'type' => 'string', 'description' => 'Category slug' ],
						'tag'       => [ 'type' => 'string', 'description' => 'Tag slug' ],
						'search'    => [ 'type' => 'string', 'description' => 'Search query' ],
						'date_after'  => [ 'type' => 'string', 'description' => 'ISO 8601 date' ],
						'date_before' => [ 'type' => 'string', 'description' => 'ISO 8601 date' ],
						'author'    => [ 'type' => 'integer', 'description' => 'Author user ID' ],
						'orderby'   => [ 'type' => 'string', 'enum' => [ 'date', 'title', 'modified', 'menu_order' ], 'default' => 'date' ],
						'order'     => [ 'type' => 'string', 'enum' => [ 'ASC', 'DESC' ], 'default' => 'DESC' ],
					],
				],
				'execute_callback' => [ $this, 'execute_list_posts' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-posts.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_list_posts( array $input ): array {
		$query_args = [
			'post_type'      => sanitize_key( $input['post_type'] ?? 'post' ),
			'post_status'    => sanitize_text_field( $input['status'] ?? 'publish' ),
			'posts_per_page' => (int) ( $input['limit'] ?? 20 ),
			'offset'         => (int) ( $input['offset'] ?? 0 ),
			'orderby'        => sanitize_text_field( $input['orderby'] ?? 'date' ),
			'order'          => strtoupper( sanitize_text_field( $input['order'] ?? 'DESC' ) ),
			'no_found_rows'  => true,
		];

		if ( ! empty( $input['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['author'] ) ) {
			$query_args['author'] = absint( $input['author'] );
		}

		if ( ! empty( $input['category'] ) ) {
			$query_args['category_name'] = sanitize_text_field( $input['category'] );
		}

		if ( ! empty( $input['tag'] ) ) {
			$query_args['tag'] = sanitize_text_field( $input['tag'] );
		}

		if ( ! empty( $input['date_after'] ) ) {
			$query_args['date_query'][] = [ 'after' => sanitize_text_field( $input['date_after'] ), 'inclusive' => false ];
		}

		if ( ! empty( $input['date_before'] ) ) {
			$query_args['date_query'][] = [ 'before' => sanitize_text_field( $input['date_before'] ), 'inclusive' => false ];
		}

		$query = new \WP_Query( $query_args );
		$posts = [];

		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post_summary( $post );
		}

		return [
			'posts'  => $posts,
			'count'  => count( $posts ),
			'offset' => (int) ( $input['offset'] ?? 0 ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-post
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-post ability.
	 *
	 * @return void
	 */
	private function register_get_post(): void {
		$this->register(
			'my-site-hand/get-post',
			[
				'label'            => __( 'Get Post', 'my-site-hand' ),
				'description'      => __( 'Get a single WordPress post with full content, meta, and SEO data.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id' => [ 'type' => 'integer', 'description' => 'The post ID' ],
					],
				],
				'execute_callback' => [ $this, 'execute_get_post' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-post.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_get_post( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		$author    = get_user_by( 'id', $post->post_author );
		$thumbnail = get_the_post_thumbnail_url( $post_id, 'full' );

		// Gather all post meta.
		$all_meta = get_post_meta( $post_id );
		$clean_meta = [];
		if ( $all_meta ) {
			foreach ( $all_meta as $key => $values ) {
				// Skip internal WP meta.
				if ( str_starts_with( $key, '_edit_' ) || str_starts_with( $key, '_wp_trash' ) ) {
					continue;
				}
				$clean_meta[ $key ] = count( $values ) === 1 ? $values[0] : $values;
			}
		}

		// Get category and tag names.
		$categories = array_map(
			static fn( $cat ) => [ 'id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug ],
			wp_get_post_categories( $post_id, [ 'fields' => 'all' ] ) ?: []
		);

		$tags = array_map(
			static fn( $tag ) => [ 'id' => $tag->term_id, 'name' => $tag->name, 'slug' => $tag->slug ],
			wp_get_post_tags( $post_id ) ?: []
		);

		return [
			'id'                  => $post->ID,
			'title'               => $post->post_title,
			'content'             => $post->post_content,
			'excerpt'             => $post->post_excerpt,
			'status'              => $post->post_status,
			'type'                => $post->post_type,
			'slug'                => $post->post_name,
			'url'                 => get_permalink( $post ),
			'date'                => $post->post_date,
			'modified'            => $post->post_modified,
			'author'              => [
				'id'           => (int) $post->post_author,
				'display_name' => $author ? $author->display_name : '',
			],
			'categories'          => $categories,
			'tags'                => $tags,
			'featured_image_url'  => $thumbnail ?: null,
			'menu_order'          => (int) $post->menu_order,
			'comment_status'      => $post->comment_status,
			'meta'                => $clean_meta,
			'word_count'          => str_word_count( wp_strip_all_tags( $post->post_content ) ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_create-post
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_create-post ability.
	 *
	 * @return void
	 */
	private function register_create_post(): void {
		$this->register(
			'my-site-hand/create-post',
			[
				'label'               => __( 'Create Post', 'my-site-hand' ),
				'description'         => __( 'Create a new WordPress post, page, or custom post type entry.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'title', 'content' ],
					'properties' => [
						'title'              => [ 'type' => 'string', 'description' => 'Post title' ],
						'content'            => [ 'type' => 'string', 'description' => 'Post content (HTML allowed)' ],
						'post_type'          => [ 'type' => 'string', 'default' => 'post' ],
						'status'             => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private' ], 'default' => 'draft' ],
						'categories'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Category IDs' ],
						'tags'               => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Tag names' ],
						'excerpt'            => [ 'type' => 'string' ],
						'author'             => [ 'type' => 'integer' ],
						'meta'               => [ 'type' => 'object', 'description' => 'Arbitrary meta key/value pairs' ],
						'featured_image_url' => [ 'type' => 'string', 'description' => 'URL to sideload as featured image' ],
						'menu_order'         => [ 'type' => 'integer', 'default' => 0 ],
						'date'               => [ 'type' => 'string', 'description' => 'ISO 8601 scheduled publish date' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					return $user_id > 0 ? ( get_user_by( 'id', $user_id )->has_cap( 'publish_posts' ) ) : current_user_can( 'publish_posts' );
				},
				'execute_callback'    => [ $this, 'execute_create_post' ],
				'annotations'         => [
					'destructive' => false,
					'idempotent'  => false,
					'meta'        => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_create-post.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_create_post( array $input ): array|\WP_Error {
		$post_data = [
			'post_title'   => sanitize_text_field( $input['title'] ),
			'post_content' => wp_kses_post( $input['content'] ),
			'post_type'    => sanitize_key( $input['post_type'] ?? 'post' ),
			'post_status'  => sanitize_text_field( $input['status'] ?? 'draft' ),
			'menu_order'   => absint( $input['menu_order'] ?? 0 ),
		];

		if ( ! empty( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}

		if ( ! empty( $input['author'] ) ) {
			$post_data['post_author'] = absint( $input['author'] );
		}

		if ( ! empty( $input['date'] ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', strtotime( sanitize_text_field( $input['date'] ) ) );
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
		}

		if ( ! empty( $input['categories'] ) ) {
			$post_data['post_category'] = array_map( 'absint', (array) $input['categories'] );
		}

		if ( ! empty( $input['tags'] ) ) {
			$post_data['tags_input'] = array_map( 'sanitize_text_field', (array) $input['tags'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set post meta.
		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( $key ), $value );
			}
		}

		// Sideload featured image.
		if ( ! empty( $input['featured_image_url'] ) ) {
			$attachment_id = $this->sideload_image( $input['featured_image_url'], $post_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		return [
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
			'status'  => get_post_status( $post_id ),
			'title'   => get_the_title( $post_id ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_update-post
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_update-post ability.
	 *
	 * @return void
	 */
	private function register_update_post(): void {
		$this->register(
			'my-site-hand/update-post',
			[
				'label'               => __( 'Update Post', 'my-site-hand' ),
				'description'         => __( 'Update an existing WordPress post. Only provided fields are changed.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id'            => [ 'type' => 'integer' ],
						'title'              => [ 'type' => 'string' ],
						'content'            => [ 'type' => 'string' ],
						'status'             => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'trash' ] ],
						'categories'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
						'tags'               => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'excerpt'            => [ 'type' => 'string' ],
						'author'             => [ 'type' => 'integer' ],
						'meta'               => [ 'type' => 'object' ],
						'featured_image_url' => [ 'type' => 'string' ],
						'menu_order'         => [ 'type' => 'integer' ],
						'date'               => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function ( int $user_id, array $input ) {
					$post_id = absint( $input['post_id'] ?? 0 );
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_post', $post_id );
					}
					return current_user_can( 'edit_post', $post_id );
				},
				'execute_callback'    => [ $this, 'execute_update_post' ],
				'annotations'         => [
					'destructive' => false,
					'idempotent'  => true,
					'meta'        => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_update-post.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_update_post( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		$post_data = [ 'ID' => $post_id ];

		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['content'] );
		}
		if ( isset( $input['status'] ) ) {
			$post_data['post_status'] = sanitize_text_field( $input['status'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $input['excerpt'] );
		}
		if ( isset( $input['author'] ) ) {
			$post_data['post_author'] = absint( $input['author'] );
		}
		if ( isset( $input['menu_order'] ) ) {
			$post_data['menu_order'] = absint( $input['menu_order'] );
		}
		if ( ! empty( $input['date'] ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', strtotime( sanitize_text_field( $input['date'] ) ) );
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
		}
		if ( isset( $input['categories'] ) ) {
			$post_data['post_category'] = array_map( 'absint', (array) $input['categories'] );
		}
		if ( isset( $input['tags'] ) ) {
			$post_data['tags_input'] = array_map( 'sanitize_text_field', (array) $input['tags'] );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta.
		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( $key ), $value );
			}
		}

		// Update featured image.
		if ( ! empty( $input['featured_image_url'] ) ) {
			$attachment_id = $this->sideload_image( $input['featured_image_url'], $post_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		return [
			'post_id'  => $post_id,
			'url'      => get_permalink( $post_id ),
			'status'   => get_post_status( $post_id ),
			'title'    => get_the_title( $post_id ),
			'modified' => get_post_modified_time( 'Y-m-d H:i:s', false, $post_id ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_delete-post
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_delete-post ability.
	 *
	 * @return void
	 */
	private function register_delete_post(): void {
		$this->register(
			'my-site-hand/delete-post',
			[
				'label'               => __( 'Delete Post', 'my-site-hand' ),
				'description'         => __( 'Delete or trash a WordPress post.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'force'   => [ 'type' => 'boolean', 'default' => false, 'description' => 'true = permanent delete, false = trash' ],
					],
				],
				'permission_callback' => static function ( int $user_id, array $input ) {
					$post_id = absint( $input['post_id'] ?? 0 );
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'delete_post', $post_id );
					}
					return current_user_can( 'delete_post', $post_id );
				},
				'execute_callback'    => [ $this, 'execute_delete_post' ],
				'annotations'         => [
					'destructive' => true,
					'meta'        => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_delete-post.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_delete_post( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] );
		$force   = (bool) ( $input['force'] ?? false );

		if ( ! get_post( $post_id ) ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		$result = wp_delete_post( $post_id, $force );

		if ( ! $result ) {
			return $this->error( __( 'Failed to delete post.', 'my-site-hand' ) );
		}

		return [
			'deleted' => true,
			'method'  => $force ? 'deleted' : 'trashed',
			'post_id' => $post_id,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_bulk-update-posts
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_bulk-update-posts ability.
	 *
	 * @return void
	 */
	private function register_bulk_update_posts(): void {
		$this->register(
			'my-site-hand/bulk-update-posts',
			[
				'label'               => __( 'Bulk Update Posts', 'my-site-hand' ),
				'description'         => __( 'Apply the same updates to multiple posts at once.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_ids', 'updates' ],
					'properties' => [
						'post_ids' => [
							'type'  => 'array',
							'items' => [ 'type' => 'integer' ],
							'description' => 'Array of post IDs to update',
						],
						'updates' => [
							'type'        => 'object',
							'description' => 'Fields to apply to all posts (same fields as update-post)',
						],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'edit_posts' );
					}
					return current_user_can( 'edit_posts' );
				},
				'execute_callback'    => [ $this, 'execute_bulk_update_posts' ],
				'annotations'         => [
					'destructive' => false,
					'idempotent'  => true,
					'meta'        => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_bulk-update-posts.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_bulk_update_posts( array $input ): array {
		$post_ids = array_map( 'absint', (array) $input['post_ids'] );
		$updates  = (array) ( $input['updates'] ?? [] );
		$updated  = 0;
		$failed   = [];

		foreach ( $post_ids as $post_id ) {
			$update_input         = $updates;
			$update_input['post_id'] = $post_id;
			$result               = $this->execute_update_post( $update_input );

			if ( is_wp_error( $result ) ) {
				$failed[] = [
					'post_id' => $post_id,
					'error'   => $result->get_error_message(),
				];
			} else {
				++$updated;
			}
		}

		return [
			'updated' => $updated,
			'failed'  => $failed,
			'total'   => count( $post_ids ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-post-types
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-post-types ability.
	 *
	 * @return void
	 */
	private function register_list_post_types(): void {
		$this->register(
			'my-site-hand/list-post-types',
			[
				'label'            => __( 'List Post Types', 'my-site-hand' ),
				'description'      => __( 'List all registered public post types with their labels and capabilities.', 'my-site-hand' ),
				'execute_callback' => [ $this, 'execute_list_post_types' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-post-types.
	 *
	 * @param array<string, mixed> $input Validated input (unused).
	 * @return array<int, array<string, mixed>>
	 */
	public function execute_list_post_types( array $input ): array {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$result     = [];

		foreach ( $post_types as $pt ) {
			$result[] = [
				'name'          => $pt->name,
				'label'         => $pt->label,
				'singular_label' => $pt->labels->singular_name ?? $pt->label,
				'description'   => $pt->description,
				'hierarchical'  => (bool) $pt->hierarchical,
				'has_archive'   => (bool) $pt->has_archive,
				'supports'      => get_all_post_type_supports( $pt->name ),
			];
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-taxonomies
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-taxonomies ability.
	 *
	 * @return void
	 */
	private function register_list_taxonomies(): void {
		$this->register(
			'my-site-hand/list-taxonomies',
			[
				'label'            => __( 'List Taxonomies', 'my-site-hand' ),
				'description'      => __( 'List all registered taxonomies with term counts.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'post_type' => [ 'type' => 'string', 'description' => 'Filter by post type' ],
					],
				],
				'execute_callback' => [ $this, 'execute_list_taxonomies' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-taxonomies.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<int, array<string, mixed>>
	 */
	public function execute_list_taxonomies( array $input ): array {
		$args = [ 'public' => true ];

		if ( ! empty( $input['post_type'] ) ) {
			$taxonomies = get_object_taxonomies( sanitize_key( $input['post_type'] ), 'objects' );
		} else {
			$taxonomies = get_taxonomies( $args, 'objects' );
		}

		$result = [];
		foreach ( $taxonomies as $tax ) {
			$term_count = wp_count_terms( [ 'taxonomy' => $tax->name, 'hide_empty' => false ] );
			$result[] = [
				'name'         => $tax->name,
				'label'        => $tax->label,
				'hierarchical' => (bool) $tax->hierarchical,
				'post_types'   => $tax->object_type,
				'term_count'   => is_wp_error( $term_count ) ? 0 : (int) $term_count,
			];
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-post-revisions
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-post-revisions ability.
	 *
	 * @return void
	 */
	private function register_get_post_revisions(): void {
		$this->register(
			'my-site-hand/get-post-revisions',
			[
				'label'            => __( 'Get Post Revisions', 'my-site-hand' ),
				'description'      => __( 'Get revision history for a post.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'required'   => [ 'post_id' ],
					'properties' => [
						'post_id' => [ 'type' => 'integer' ],
						'limit'   => [ 'type' => 'integer', 'default' => 10, 'maximum' => 50 ],
					],
				],
				'execute_callback' => [ $this, 'execute_get_post_revisions' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-post-revisions.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_get_post_revisions( array $input ): array|\WP_Error {
		$post_id = absint( $input['post_id'] );
		$limit   = min( absint( $input['limit'] ?? 10 ), 50 );

		if ( ! get_post( $post_id ) ) {
			return $this->error( __( 'Post not found.', 'my-site-hand' ), 'not_found' );
		}

		$revisions = wp_get_post_revisions(
			$post_id,
			[
				'posts_per_page' => $limit,
				'order'          => 'DESC',
			]
		);

		$result = [];
		foreach ( $revisions as $revision ) {
			$author   = get_userdata( $revision->post_author );
			$result[] = [
				'id'           => $revision->ID,
				'date'         => $revision->post_date,
				'author'       => $author ? $author->display_name : '',
				'title'        => $revision->post_title,
				'word_count'   => str_word_count( wp_strip_all_tags( $revision->post_content ) ),
				'restore_url'  => wp_nonce_url(
					admin_url( "revision.php?revision={$revision->ID}" ),
					"restore-post_{$revision->ID}"
				),
			];
		}

		return [
			'revisions' => $result,
			'total'     => count( $result ),
			'post_id'   => $post_id,
		];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Format a post object as a summary array.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	private function format_post_summary( \WP_Post $post ): array {
		$author     = get_userdata( $post->post_author );
		$categories = wp_get_post_categories( $post->ID, [ 'fields' => 'names' ] );
		$tags       = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );

		return [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'status'      => $post->post_status,
			'type'        => $post->post_type,
			'date'        => $post->post_date,
			'modified'    => $post->post_modified,
			'author'      => $author ? $author->display_name : '',
			'categories'  => $categories ?: [],
			'tags'        => $tags ?: [],
			'excerpt'     => $post->post_excerpt ?: wp_trim_words( $post->post_content, 30 ),
			'url'         => get_permalink( $post ),
			'word_count'  => str_word_count( wp_strip_all_tags( $post->post_content ) ),
		];
	}

	/**
	 * Sideload a remote image URL as a media library attachment.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Post ID to attach to.
	 * @return int|\WP_Error Attachment ID or WP_Error.
	 */
	private function sideload_image( string $url, int $post_id ): int|\WP_Error {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( esc_url_raw( $url ), $post_id, null, 'id' );

		return $attachment_id;
	}
}




