<?php
/**
 * Media module — media library management abilities.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Module Media class.
 *
 * Provides abilities for browsing, auditing, and bulk-updating the WordPress
 * media library, including alt text management and large file detection.
 */
class Module_Media extends Module_Base {

	/**
	 * {@inheritdoc}
	 */
	public function get_module_name(): string {
		return 'media';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ability_names(): array {
		return [
			'my-site-hand/list-media',
			'my-site-hand/get-unattached-media',
			'my-site-hand/update-media-alt-text',
			'my-site-hand/bulk-update-alt-text',
			'my-site-hand/get-large-media',
			'my-site-hand/get-media-library-stats',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_abilities(): void {
		$this->register_list_media();
		$this->register_get_unattached_media();
		$this->register_update_media_alt_text();
		$this->register_bulk_update_alt_text();
		$this->register_get_large_media();
		$this->register_get_media_library_stats();
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-media
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-media ability.
	 *
	 * @return void
	 */
	private function register_list_media(): void {
		$this->register(
			'my-site-hand/list-media',
			[
				'label'            => __( 'List Media', 'my-site-hand' ),
				'description'      => __( 'List media library items with filtering options.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'limit'       => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
						'offset'      => [ 'type' => 'integer', 'default' => 0 ],
						'mime_type'   => [ 'type' => 'string', 'description' => 'e.g. image, video, audio, application/pdf' ],
						'search'      => [ 'type' => 'string' ],
						'attached_to' => [ 'type' => 'integer', 'description' => 'Filter by parent post ID' ],
						'unattached'  => [ 'type' => 'boolean', 'default' => false ],
					],
				],
				'execute_callback' => [ $this, 'execute_list_media' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-media.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_list_media( array $input ): array {
		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => min( absint( $input['limit'] ?? 20 ), 100 ),
			'offset'         => absint( $input['offset'] ?? 0 ),
			'no_found_rows'  => true,
		];

		if ( ! empty( $input['mime_type'] ) ) {
			$query_args['post_mime_type'] = sanitize_text_field( $input['mime_type'] );
		}

		if ( ! empty( $input['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['attached_to'] ) ) {
			$query_args['post_parent'] = absint( $input['attached_to'] );
		}

		if ( ! empty( $input['unattached'] ) ) {
			$query_args['post_parent'] = 0;
		}

		$query = new \WP_Query( $query_args );
		$media = [];

		foreach ( $query->posts as $attachment ) {
			$media[] = $this->format_attachment( $attachment );
		}

		return [
			'media' => $media,
			'count' => count( $media ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-unattached-media
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-unattached-media ability.
	 *
	 * @return void
	 */
	private function register_get_unattached_media(): void {
		$this->register(
			'my-site-hand/get-unattached-media',
			[
				'label'            => __( 'List Unattached Media', 'my-site-hand' ),
				'description'      => __( 'List media items not attached to any post.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'limit' => [ 'type' => 'integer', 'default' => 50, 'maximum' => 200 ],
					],
				],
				'execute_callback' => [ $this, 'execute_get_unattached_media' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-unattached-media.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_get_unattached_media( array $input ): array {
		return $this->execute_list_media( [
			'limit'      => $input['limit'] ?? 50,
			'unattached' => true,
		] );
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_update-media-alt-text
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_update-media-alt-text ability.
	 *
	 * @return void
	 */
	private function register_update_media_alt_text(): void {
		$this->register(
			'my-site-hand/update-media-alt-text',
			[
				'label'               => __( 'Update Alt Text', 'my-site-hand' ),
				'description'         => __( 'Set the alt text for a media attachment.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'attachment_id', 'alt_text' ],
					'properties' => [
						'attachment_id' => [ 'type' => 'integer' ],
						'alt_text'      => [ 'type' => 'string' ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'upload_files' );
					}
					return current_user_can( 'upload_files' );
				},
				'execute_callback'    => [ $this, 'execute_update_media_alt_text' ],
				'annotations'         => [
					'meta' => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_update-media-alt-text.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_update_media_alt_text( array $input ): array|\WP_Error {
		$attachment_id = absint( $input['attachment_id'] );
		$alt_text      = sanitize_text_field( $input['alt_text'] );

		if ( ! get_post( $attachment_id ) ) {
			return $this->error( __( 'Attachment not found.', 'my-site-hand' ), 'not_found' );
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		return [
			'updated'       => true,
			'attachment_id' => $attachment_id,
			'alt_text'      => $alt_text,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_bulk-update-alt-text
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_bulk-update-alt-text ability.
	 *
	 * @return void
	 */
	private function register_bulk_update_alt_text(): void {
		$this->register(
			'my-site-hand/bulk-update-alt-text',
			[
				'label'               => __( 'Bulk Update Alt Text', 'my-site-hand' ),
				'description'         => __( 'Set alt text for multiple media attachments at once.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'items' ],
					'properties' => [
						'items' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'required'   => [ 'attachment_id', 'alt_text' ],
								'properties' => [
									'attachment_id' => [ 'type' => 'integer' ],
									'alt_text'      => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'upload_files' );
					}
					return current_user_can( 'upload_files' );
				},
				'execute_callback'    => [ $this, 'execute_bulk_update_alt_text' ],
				'annotations'         => [
					'idempotent' => true,
					'meta'       => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_bulk-update-alt-text.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_bulk_update_alt_text( array $input ): array {
		$items   = (array) ( $input['items'] ?? [] );
		$updated = 0;
		$failed  = [];

		foreach ( $items as $item ) {
			$result = $this->execute_update_media_alt_text( [
				'attachment_id' => $item['attachment_id'] ?? 0,
				'alt_text'      => $item['alt_text'] ?? '',
			] );

			if ( is_wp_error( $result ) ) {
				$failed[] = [
					'attachment_id' => $item['attachment_id'] ?? 0,
					'error'         => $result->get_error_message(),
				];
			} else {
				++$updated;
			}
		}

		return [
			'updated' => $updated,
			'failed'  => $failed,
			'total'   => count( $items ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-large-media
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-large-media ability.
	 *
	 * @return void
	 */
	private function register_get_large_media(): void {
		$this->register(
			'my-site-hand/get-large-media',
			[
				'label'            => __( 'Find Large Media', 'my-site-hand' ),
				'description'      => __( 'Find media attachments whose file size exceeds a given threshold.', 'my-site-hand' ),
				'input_schema'     => [
					'type'       => 'object',
					'properties' => [
						'threshold_mb' => [ 'type' => 'number', 'default' => 1.0, 'description' => 'Threshold in megabytes' ],
					],
				],
				'execute_callback' => [ $this, 'execute_get_large_media' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-large-media.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_get_large_media( array $input ): array {
		$threshold_mb    = max( 0.1, (float) ( $input['threshold_mb'] ?? 1.0 ) );
		$threshold_bytes = (int) ( $threshold_mb * 1024 * 1024 );

		$attachments = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		$large_files = [];

		foreach ( $attachments as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			$file_size = filesize( $file_path );
			if ( $file_size >= $threshold_bytes ) {
				$alt_text   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$large_files[] = [
					'id'        => $attachment_id,
					'title'     => get_the_title( $attachment_id ),
					'url'       => wp_get_attachment_url( $attachment_id ),
					'file_size' => size_format( $file_size ),
					'size_mb'   => round( $file_size / 1024 / 1024, 2 ),
					'mime_type' => get_post_mime_type( $attachment_id ),
					'alt_text'  => $alt_text ?: '',
				];
			}
		}

		// Sort by size descending.
		usort( $large_files, static fn( $a, $b ) => $b['size_mb'] <=> $a['size_mb'] );

		return [
			'files'         => $large_files,
			'count'         => count( $large_files ),
			'threshold_mb'  => $threshold_mb,
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-media-library-stats
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-media-library-stats ability.
	 *
	 * @return void
	 */
	private function register_get_media_library_stats(): void {
		$this->register(
			'my-site-hand/get-media-library-stats',
			[
				'label'            => __( 'Media Library Stats', 'my-site-hand' ),
				'description'      => __( 'Get aggregate statistics about the WordPress media library.', 'my-site-hand' ),
				'execute_callback' => [ $this, 'execute_get_media_library_stats' ],
				'annotations'      => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-media-library-stats.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_get_media_library_stats( array $input ): array {
		$cache_key = 'media_library_stats';
		$cached    = get_transient( 'MYSITEHAND_' . $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Total attachments grouped by mime type family.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$mime_counts_raw = $wpdb->get_results(
			"SELECT LEFT(post_mime_type, LOCATE('/', post_mime_type) - 1) as mime_family, COUNT(*) as cnt
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment' AND post_status = 'inherit'
			GROUP BY mime_family",
			ARRAY_A
		);

		$by_type    = [];
		$total_files = 0;

		foreach ( $mime_counts_raw as $row ) {
			$family           = $row['mime_family'] ?: 'other';
			$by_type[ $family ] = (int) $row['cnt'];
			$total_files      += (int) $row['cnt'];
		}

		// Total disk size of uploads directory.
		$upload_dir    = wp_upload_dir();
		$total_size_mb = 0.0;

		if ( is_dir( $upload_dir['basedir'] ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $upload_dir['basedir'], \RecursiveDirectoryIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$total_size_mb += $file->getSize();
				}
			}
			$total_size_mb = $total_size_mb / 1024 / 1024;
		}

		// Count missing alt text (images only).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$missing_alt = (int) $wpdb->get_var(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_type = 'attachment'
			AND p.post_status = 'inherit'
			AND p.post_mime_type LIKE 'image/%'
			AND (pm.meta_value IS NULL OR pm.meta_value = '')"
		);

		// Count unattached.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unattached = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_parent = 0"
		);

		$stats = [
			'total_files'       => $total_files,
			'total_size_mb'     => round( $total_size_mb, 2 ),
			'by_type'           => $by_type,
			'missing_alt_text'  => $missing_alt,
			'unattached'        => $unattached,
		];

		set_transient( 'MYSITEHAND_' . $cache_key, $stats, 30 * MINUTE_IN_SECONDS );

		return $stats;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Format an attachment post as a summary array.
	 *
	 * @param \WP_Post $attachment Attachment post object.
	 * @return array<string, mixed>
	 */
	private function format_attachment( \WP_Post $attachment ): array {
		$alt_text  = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		$metadata  = wp_get_attachment_metadata( $attachment->ID );
		$file_path = get_attached_file( $attachment->ID );
		$filesize  = $file_path && file_exists( $file_path ) ? filesize( $file_path ) : null;

		$dimensions = null;
		if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			$dimensions = [
				'width'  => $metadata['width'],
				'height' => $metadata['height'],
			];
		}

		return [
			'id'          => $attachment->ID,
			'title'       => $attachment->post_title,
			'url'         => wp_get_attachment_url( $attachment->ID ),
			'mime_type'   => $attachment->post_mime_type,
			'filesize'    => $filesize ? size_format( $filesize ) : null,
			'dimensions'  => $dimensions,
			'alt_text'    => $alt_text ?: '',
			'date'        => $attachment->post_date,
			'attached_to' => $attachment->post_parent ?: null,
		];
	}
}




