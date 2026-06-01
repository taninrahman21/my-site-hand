<?php
/**
 * Diagnostics module — site health and environment abilities.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Module Diagnostics class.
 *
 * Provides abilities for checking WordPress environment health, plugins,
 * error logs, cron jobs, database stats, transients, and site options.
 */
class Module_Diagnostics extends Module_Base {

	/**
	 * {@inheritdoc}
	 */
	public function get_module_name(): string {
		return 'diagnostics';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_ability_names(): array {
		return [
			'my-site-hand/site-health-report',
			'my-site-hand/list-plugin-updates',
			'my-site-hand/get-error-logs',
			'my-site-hand/list-cron-jobs',
			'my-site-hand/get-site-options',
			'my-site-hand/list-transients',
			'my-site-hand/get-db-table-sizes',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_abilities(): void {
		$this->register_site_health_report();
		$this->register_list_plugin_updates();
		$this->register_get_error_logs();
		$this->register_list_cron_jobs();
		$this->register_get_site_options();
		$this->register_list_transients();
		$this->register_get_db_table_sizes();
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_site-health-report
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_site-health-report ability.
	 *
	 * @return void
	 */
	private function register_site_health_report(): void {
		$this->register(
			'my-site-hand/site-health-report',
			[
				'label'               => __( 'Site Health Report', 'my-site-hand' ),
				'description'         => __( 'Get a comprehensive site health and environment report.', 'my-site-hand' ),
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_options' );
					}
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_site_health_report' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_site-health-report.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_site_health_report( array $input ): array {
		$cache_key = 'site_health_report';
		$cached    = get_transient( 'MYSITEHAND_' . $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// WordPress info.
		$wp_version = get_bloginfo( 'version' );
		$debug_mode = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// Memory.
		$memory_limit = ini_get( 'memory_limit' );
		$memory_usage = size_format( memory_get_usage( true ) );

		// PHP extensions.
		$extensions = get_loaded_extensions();
		$notable_extensions = array_values( array_filter(
			$extensions,
			static fn( $ext ) => in_array( $ext, [ 'curl', 'gd', 'imagick', 'mbstring', 'openssl', 'zip', 'exif', 'intl' ], true )
		) );

		// Opcache.
		$opcache_enabled = function_exists( 'opcache_get_status' ) && ! empty( opcache_get_status()['opcache_enabled'] );

		// Database.
		$db_version  = $wpdb->db_version();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables      = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		$db_size_mb  = 0.0;
		$table_count = 0;

		foreach ( $tables as $table ) {
			$table_count++;
			$db_size_mb += ( (float) $table['Data_length'] + (float) $table['Index_length'] ) / 1024 / 1024;
		}

		// Plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$plugin_updates = get_plugin_updates();
		$plugins_need_update = [];

		foreach ( $plugin_updates as $plugin_file => $plugin_data ) {
			$plugins_need_update[] = sprintf(
				'%s %s → %s',
				$plugin_data->Name,
				$plugin_data->Version,
				$plugin_data->update->new_version
			);
		}

		// Theme.
		$theme        = wp_get_theme();
		$theme_update = get_theme_updates();
		$theme_needs_update = isset( $theme_update[ $theme->get_stylesheet() ] );

		// Cron.
		$crons        = _get_cron_array();
		$cron_events  = count( is_array( $crons ) ? $crons : [] );
		$overdue      = 0;
		$now          = time();

		foreach ( (array) $crons as $time => $hooks ) {
			if ( $time < $now ) {
				$overdue += count( $hooks );
			}
		}

		// SSL.
		$ssl_enabled = is_ssl();
		$ssl_expires = null;

		if ( $ssl_enabled ) {
			$ssl_context = stream_context_create( [ 'ssl' => [ 'capture_peer_cert' => true ] ] );
			$ssl_host    = wp_parse_url( get_site_url(), PHP_URL_HOST );
			$ssl_conn    = @stream_socket_client( 'ssl://' . $ssl_host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ssl_context );
			if ( $ssl_conn ) {
				$ssl_params = stream_context_get_params( $ssl_conn );
				$cert       = openssl_x509_parse( $ssl_params['options']['ssl']['peer_certificate'] ?? '' );
				if ( ! empty( $cert['validTo_time_t'] ) ) {
					$ssl_expires = gmdate( 'Y-m-d', $cert['validTo_time_t'] );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				fclose( $ssl_conn );
			}
		}

		// Disk: uploads directory size.
		$upload_dir   = wp_upload_dir();
		$uploads_size = $this->get_directory_size_mb( $upload_dir['basedir'] );

		$report = [
			'wordpress'  => [
				'version'          => $wp_version,
				'multisite'        => is_multisite(),
				'debug_mode'       => $debug_mode,
				'memory_limit'     => $memory_limit,
				'memory_usage'     => $memory_usage,
				'max_execution_time' => (int) ini_get( 'max_execution_time' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'post_max_size'    => ini_get( 'post_max_size' ),
			],
			'php'        => [
				'version'          => PHP_VERSION,
				'extensions'       => $notable_extensions,
				'opcache_enabled'  => $opcache_enabled,
			],
			'database'   => [
				'version'          => $db_version,
				'size_mb'          => round( $db_size_mb, 2 ),
				'tables_count'     => $table_count,
			],
			'plugins'    => [
				'active_count'         => count( $active_plugins ),
				'total_installed'      => count( $all_plugins ),
				'plugins_needing_update' => count( $plugins_need_update ),
				'plugins_with_updates'   => $plugins_need_update,
			],
			'themes'     => [
				'active'             => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
				'needs_update'       => $theme_needs_update,
			],
			'cron'       => [
				'scheduled_events'   => $cron_events,
				'overdue_events'     => $overdue,
			],
			'ssl'        => [
				'enabled'            => $ssl_enabled,
				'expires'            => $ssl_expires,
			],
			'disk'       => [
				'uploads_size_mb'    => round( $uploads_size, 2 ),
			],
		];

		set_transient( 'MYSITEHAND_' . $cache_key, $report, 15 * MINUTE_IN_SECONDS );

		return $report;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-plugin-updates
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-plugin-updates ability.
	 *
	 * @return void
	 */
	private function register_list_plugin_updates(): void {
		$this->register(
			'my-site-hand/list-plugin-updates',
			[
				'label'               => __( 'Plugin Update List', 'my-site-hand' ),
				'description'         => __( 'List all plugins that have available updates.', 'my-site-hand' ),
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'update_plugins' );
					}
					return current_user_can( 'update_plugins' );
				},
				'execute_callback'    => [ $this, 'execute_list_plugin_updates' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-plugin-updates.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_list_plugin_updates( array $input ): array {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$plugin_updates = get_plugin_updates();
		$updates        = [];

		foreach ( $plugin_updates as $plugin_file => $plugin_data ) {
			$updates[] = [
				'name'            => $plugin_data->Name,
				'current_version' => $plugin_data->Version,
				'new_version'     => $plugin_data->update->new_version ?? '',
				'changelog_url'   => $plugin_data->update->url ?? '',
				'plugin_file'     => $plugin_file,
			];
		}

		return [
			'updates' => $updates,
			'count'   => count( $updates ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-error-logs
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-error-logs ability.
	 *
	 * @return void
	 */
	private function register_get_error_logs(): void {
		$this->register(
			'my-site-hand/get-error-logs',
			[
				'label'               => __( 'Error Logs', 'my-site-hand' ),
				'description'         => __( 'Read the last N lines from the WordPress debug error log.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'lines' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 500 ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_options' );
					}
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_get_error_logs' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-error-logs.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_get_error_logs( array $input ): array|\WP_Error {
		$lines = min( absint( $input['lines'] ?? 50 ), 500 );

		// Try to locate the debug log.
		$log_path = null;

		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) ) {
			$log_path = WP_DEBUG_LOG;
		} elseif ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG === true ) {
			$log_path = WP_CONTENT_DIR . '/debug.log';
		} else {
			$log_path = WP_CONTENT_DIR . '/debug.log';
		}

		if ( ! file_exists( $log_path ) || ! is_readable( $log_path ) ) {
			return [
				'lines'    => [],
				'log_path' => 'debug.log',
				'message'  => __( 'No error log file found or WP_DEBUG_LOG is not enabled.', 'my-site-hand' ),
			];
		}

		// Read the last N lines efficiently.
		$log_lines = $this->read_last_lines( $log_path, $lines );

		return [
			'lines'     => $log_lines,
			'count'     => count( $log_lines ),
			'log_path'  => basename( $log_path ),
			'file_size' => size_format( filesize( $log_path ) ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-cron-jobs
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-cron-jobs ability.
	 *
	 * @return void
	 */
	private function register_list_cron_jobs(): void {
		$this->register(
			'my-site-hand/list-cron-jobs',
			[
				'label'               => __( 'Cron Job List', 'my-site-hand' ),
				'description'         => __( 'List all scheduled WP-Cron events.', 'my-site-hand' ),
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_options' );
					}
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_list_cron_jobs' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-cron-jobs.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_list_cron_jobs( array $input ): array {
		$crons  = _get_cron_array();
		$events = [];
		$now    = time();

		foreach ( (array) $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $callbacks ) {
				foreach ( $callbacks as $callback_key => $callback_data ) {
					$schedule = $callback_data['schedule'] ?? 'once';
					$interval = isset( $callback_data['interval'] ) ? human_time_diff( 0, $callback_data['interval'] ) : 'once';

					$events[] = [
						'hook'      => $hook,
						'next_run'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
						'overdue'   => $timestamp < $now,
						'schedule'  => $schedule,
						'interval'  => $interval,
						'args'      => $callback_data['args'] ?? [],
					];
				}
			}
		}

		// Sort by next run.
		usort( $events, static fn( $a, $b ) => strcmp( $a['next_run'], $b['next_run'] ) );

		return [
			'events' => $events,
			'count'  => count( $events ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-site-options
	// -------------------------------------------------------------------------

	/**
	 * Allowlisted safe option keys.
	 *
	 * @var array<string>
	 */
	private const SAFE_OPTIONS = [
		'blogname',
		'blogdescription',
		'admin_email',
		'timezone_string',
		'gmt_offset',
		'date_format',
		'time_format',
		'posts_per_page',
		'default_comment_status',
		'comment_moderation',
		'permalink_structure',
		'upload_path',
		'blog_public',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
	];

	/**
	 * Register the mysitehand_get-site-options ability.
	 *
	 * @return void
	 */
	private function register_get_site_options(): void {
		$this->register(
			'my-site-hand/get-site-options',
			[
				'label'               => __( 'Site Settings', 'my-site-hand' ),
				'description'         => __( 'Get WordPress site options (allowlisted safe options only — no passwords or secrets).', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'keys' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'string' ],
							'description' => 'Specific option keys to retrieve (allowlisted)',
						],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_options' );
					}
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_get_site_options' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-site-options.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_get_site_options( array $input ): array {
		$requested = isset( $input['keys'] ) && is_array( $input['keys'] )
			? array_map( 'sanitize_key', $input['keys'] )
			: self::SAFE_OPTIONS;

		// Enforce allowlist.
		$allowed = array_intersect( $requested, self::SAFE_OPTIONS );
		$options = [];

		foreach ( $allowed as $key ) {
			$options[ $key ] = get_option( $key );
		}

		return $options;
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_list-transients
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_list-transients ability.
	 *
	 * @return void
	 */
	private function register_list_transients(): void {
		$this->register(
			'my-site-hand/list-transients',
			[
				'label'               => __( 'Transient List', 'my-site-hand' ),
				'description'         => __( 'List active WordPress transients.', 'my-site-hand' ),
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'search' => [ 'type' => 'string', 'description' => 'Search transient key prefix' ],
						'limit'  => [ 'type' => 'integer', 'default' => 50, 'maximum' => 200 ],
					],
				],
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_options' );
					}
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_list_transients' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_list-transients.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_list_transients( array $input ): array {
		global $wpdb;

		$limit  = min( absint( $input['limit'] ?? 50 ), 200 );
		$search = isset( $input['search'] ) ? $wpdb->esc_like( sanitize_text_field( $input['search'] ) ) . '%' : '%';

		$prefix_like = $wpdb->esc_like( '_transient_timeout_' ) . $search;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				WHERE option_name LIKE %s
				ORDER BY option_value ASC
				LIMIT %d",
				$prefix_like,
				$limit
			),
			ARRAY_A
		);

		$transients = [];
		$now        = time();

		foreach ( $rows as $row ) {
			$key     = str_replace( '_transient_timeout_', '', $row['option_name'] );
			$expires = (int) $row['option_value'];

			$transients[] = [
				'key'        => $key,
				'expires_at' => $expires > 0 ? gmdate( 'Y-m-d H:i:s', $expires ) : 'never',
				'expires_in' => $expires > 0 ? human_time_diff( $now, $expires ) : 'never',
				'expired'    => $expires > 0 && $expires < $now,
			];
		}

		return [
			'transients' => $transients,
			'count'      => count( $transients ),
		];
	}

	// -------------------------------------------------------------------------
	// Ability: mysitehand_get-db-table-sizes
	// -------------------------------------------------------------------------

	/**
	 * Register the mysitehand_get-db-table-sizes ability.
	 *
	 * @return void
	 */
	private function register_get_db_table_sizes(): void {
		$this->register(
			'my-site-hand/get-db-table-sizes',
			[
				'label'               => __( 'Database Sizes', 'my-site-hand' ),
				'description'         => __( 'Get row count and size for every WordPress database table.', 'my-site-hand' ),
				'permission_callback' => static function ( int $user_id ) {
					if ( $user_id > 0 ) {
						$user = get_user_by( 'id', $user_id );
						return $user && $user->has_cap( 'manage_options' );
					}
					return current_user_can( 'manage_options' );
				},
				'execute_callback'    => [ $this, 'execute_get_db_table_sizes' ],
				'annotations'         => [
					'readonly' => true,
					'meta'     => [ 'mcp' => [ 'public' => true ] ],
				],
			]
		);
	}

	/**
	 * Execute mysitehand_get-db-table-sizes.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>
	 */
	public function execute_get_db_table_sizes( array $input ): array {
		$cache_key = 'db_table_sizes';
		$cached    = get_transient( 'MYSITEHAND_' . $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		$result = [];

		foreach ( $tables as $table ) {
			$size_mb = round(
				( (float) $table['Data_length'] + (float) $table['Index_length'] ) / 1024 / 1024,
				3
			);
			$result[] = [
				'name'      => $table['Name'],
				'rows'      => (int) $table['Rows'],
				'size_mb'   => $size_mb,
				'engine'    => $table['Engine'],
				'collation' => $table['Collation'],
			];
		}

		// Sort by size descending.
		usort( $result, static fn( $a, $b ) => $b['size_mb'] <=> $a['size_mb'] );

		$response = [
			'tables'     => $result,
			'count'      => count( $result ),
			'total_mb'   => round( array_sum( array_column( $result, 'size_mb' ) ), 2 ),
		];

		set_transient( 'MYSITEHAND_' . $cache_key, $response, HOUR_IN_SECONDS );

		return $response;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Read the last N lines from a file efficiently using WP_Filesystem.
	 *
	 * @param string $file_path Path to the file.
	 * @param int    $lines     Number of lines to read.
	 * @return array<int, string>
	 */
	private function read_last_lines( string $file_path, int $lines ): array {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return [];
		}

		// Read the last 512KB of the file (should be enough for N lines).
		$file_size = $wp_filesystem->size( $file_path );
		$read_size = min( 512 * 1024, $file_size );
		$offset    = max( 0, $file_size - $read_size );

		// WP_Filesystem doesn't support offsets in get_contents easily across all adapters.
		// For local files (most common), we can use the direct path if needed, or just read the whole thing if it's small.
		// Use WP_Filesystem to read the contents.
		$content = $wp_filesystem->get_contents( $file_path );

		if ( ! $content ) {
			return [];
		}

		$all_lines = explode( "\n", trim( $content ) );

		return array_slice( $all_lines, -$lines );
	}

	/**
	 * Recursively calculate directory size in megabytes.
	 *
	 * @param string $path Directory path.
	 * @return float Size in MB.
	 */
	private function get_directory_size_mb( string $path ): float {
		$size = 0;

		if ( ! is_dir( $path ) ) {
			return 0.0;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$size += $file->getSize();
			}
		}

		return $size / 1024 / 1024;
	}
}




