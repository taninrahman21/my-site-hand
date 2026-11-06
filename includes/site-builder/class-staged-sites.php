<?php
/**
 * Storage for staged (not yet applied) builds.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Staged_Sites class.
 *
 * One staged build per user, kept in a private post type that has no UI, no
 * REST route, no archive and no front end. Nothing in it is visible to
 * visitors; it is a draft the user can preview, apply or throw away.
 */
class Staged_Sites {

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	public const POST_TYPE = 'msh_staged_site';

	/**
	 * Staged builds untouched for this long are deleted by the daily cron.
	 *
	 * @var int
	 */
	public const MAX_AGE = 7 * DAY_IN_SECONDS;

	/**
	 * Register the post type. Hooked to init.
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'               => __( 'Staged sites', 'my-site-hand' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => true,
				'supports'            => [ 'author' ],
			]
		);
	}

	/**
	 * The user's staged build.
	 *
	 * @param int $user_id User id.
	 * @return array{id: int, state: array<string, mixed>, build: array<string, mixed>, modified: string}|null
	 */
	public function get( int $user_id ): ?array {
		$post = $this->find_post( $user_id );
		if ( ! $post ) {
			return null;
		}

		$data = json_decode( $post->post_content, true );
		if ( ! is_array( $data ) || ! is_array( $data['state'] ?? null ) ) {
			return null;
		}

		return [
			'id'       => (int) $post->ID,
			'state'    => $data['state'],
			'build'    => is_array( $data['build'] ?? null ) ? $data['build'] : [],
			'modified' => (string) $post->post_modified_gmt,
		];
	}

	/**
	 * Create or replace the user's staged build.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $state   Choices.
	 * @param array<string, mixed> $build   Resolved build.
	 * @return int|\WP_Error Post id.
	 */
	public function save( int $user_id, array $state, array $build ) {
		// Hex-escaping every markup character keeps the stored JSON free of
		// anything kses would recognise as HTML, so content filters applied
		// on save cannot alter it.
		$content = wp_json_encode(
			[
				'state' => $state,
				'build' => $build,
			],
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
		);

		$post = $this->find_post( $user_id );
		$args = [
			'post_type'    => self::POST_TYPE,
			'post_status'  => 'private',
			'post_author'  => $user_id,
			'post_title'   => sanitize_key( (string) ( $state['slug'] ?? '' ) ),
			'post_content' => wp_slash( (string) $content ),
		];

		if ( $post ) {
			$args['ID'] = $post->ID;
			$id         = wp_update_post( $args, true );
		} else {
			$id = wp_insert_post( $args, true );
		}

		return is_wp_error( $id ) ? $id : (int) $id;
	}

	/**
	 * Delete the user's staged build.
	 *
	 * Staged builds are the plugin's own scratch data, never site content,
	 * so they are deleted outright rather than trashed.
	 *
	 * @param int $user_id User id.
	 * @return bool True if one existed.
	 */
	public function delete( int $user_id ): bool {
		$post = $this->find_post( $user_id );
		if ( ! $post ) {
			return false;
		}

		wp_delete_post( $post->ID, true );
		return true;
	}

	/**
	 * Daily cron: delete staged builds untouched for MAX_AGE.
	 *
	 * @return int Number deleted.
	 */
	public function cleanup(): int {
		$ids = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'date_query'     => [
					[
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - self::MAX_AGE ),
					],
				],
			]
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}

		return count( $ids );
	}

	/**
	 * The user's staged post.
	 *
	 * @param int $user_id User id.
	 * @return \WP_Post|null
	 */
	private function find_post( int $user_id ): ?\WP_Post {
		if ( $user_id <= 0 ) {
			return null;
		}

		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'author'           => $user_id,
				'posts_per_page'   => 1,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => true,
			]
		);

		return $posts[0] ?? null;
	}
}
