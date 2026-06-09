<?php
/**
 * Audit log template.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;

$my_site_hand_plugin   = \MySiteHand\Plugin::get_instance();
$my_site_hand_audit    = $my_site_hand_plugin->get_audit_logger();
$my_site_hand_auth     = $my_site_hand_plugin->get_auth_manager();
$my_site_hand_registry = $my_site_hand_plugin->get_abilities_registry();

// Nonce validation.
$my_site_hand_nonce = wp_create_nonce( 'my_site_hand_admin' );

// Load date ranges.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$my_site_hand_date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['date_range'] ) ) : 'all';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$my_site_hand_search_val = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$my_site_hand_token_id_val = isset( $_GET['token_id'] ) ? absint( wp_unslash( $_GET['token_id'] ) ) : 0;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$my_site_hand_page_val = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$my_site_hand_date_from = null;
if ( '24h' === $my_site_hand_date_range ) {
	$my_site_hand_date_from = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
} elseif ( '7d' === $my_site_hand_date_range ) {
	$my_site_hand_date_from = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
} elseif ( '30d' === $my_site_hand_date_range ) {
	$my_site_hand_date_from = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
}

// Current filters.
$my_site_hand_filters = [
	'per_page'     => 25,
	'page'         => $my_site_hand_page_val,
	'token_id'     => ! empty( $my_site_hand_token_id_val ) ? $my_site_hand_token_id_val : null,
	'search'       => ! empty( $my_site_hand_search_val ) ? $my_site_hand_search_val : null,
	'date_from'    => $my_site_hand_date_from,
];

$my_site_hand_filters = array_filter( $my_site_hand_filters );
$my_site_hand_filters['per_page'] = 25;
if ( empty( $my_site_hand_filters['page'] ) ) {
	$my_site_hand_filters['page'] = 1;
}

$my_site_hand_result        = $my_site_hand_audit->get_logs( $my_site_hand_filters );
$my_site_hand_logs          = $my_site_hand_result['logs'];
$my_site_hand_total         = $my_site_hand_result['total'];
$my_site_hand_pages         = $my_site_hand_result['pages'];
$my_site_hand_stats         = $my_site_hand_audit->get_stats();
$my_site_hand_tokens        = $my_site_hand_auth->list_tokens( 0 );
$my_site_hand_all_abilities = $my_site_hand_registry->get_all();

// Compute dynamic success rate count for TODAY card.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$my_site_hand_today_successes = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_audit_log WHERE result_status = 'success' AND DATE(executed_at) = CURDATE()"
);
$my_site_hand_today_calls = $my_site_hand_stats['calls_today'];
$my_site_hand_success_rate = $my_site_hand_today_calls > 0
	? round( ( $my_site_hand_today_successes / $my_site_hand_today_calls ) * 100, 1 )
	: 100;

// Build tokens lookup map.
$my_site_hand_token_map = [];
foreach ( $my_site_hand_tokens as $my_site_hand_token ) {
	$my_site_hand_token_map[ $my_site_hand_token['id'] ] = $my_site_hand_token['label'];
}
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<!-- Header Row -->
			<div class="msh-audit-header-row">
				<div class="msh-page-header-info">
					<h2 class="msh-page-title"><?php echo esc_html__( 'Audit Log', 'my-site-hand' ); ?></h2>
					<p class="msh-page-desc-inline"><?php echo esc_html__( 'Every MCP request, with timing, status, and payload.', 'my-site-hand' ); ?></p>
				</div>
				<div class="msh-page-header-actions">
					<a href="<?php echo esc_url( rest_url( 'my-site-hand/v1/audit-log/export?nonce=' . $my_site_hand_nonce ) ); ?>" class="msh-btn msh-btn--ghost">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
						<?php echo esc_html__( 'Export CSV', 'my-site-hand' ); ?>
					</a>
				</div>
			</div>

			<!-- Stats Grid -->
			<div class="msh-audit-stats-grid">
				<!-- Today Calls -->
				<div class="msh-audit-stat-card msh-audit-stat-card--today">
					<div class="msh-audit-stat-label"><?php echo esc_html__( 'TODAY', 'my-site-hand' ); ?></div>
					<div class="msh-audit-stat-value"><?php echo esc_html( number_format( $my_site_hand_today_calls ) ); ?></div>
					<div class="msh-audit-stat-desc"><?php echo esc_html__( 'API calls', 'my-site-hand' ); ?></div>
				</div>

				<!-- Success Rate -->
				<div class="msh-audit-stat-card msh-audit-stat-card--success">
					<div class="msh-audit-stat-label"><?php echo esc_html__( 'SUCCESS', 'my-site-hand' ); ?></div>
					<div class="msh-audit-stat-value"><?php echo esc_html( $my_site_hand_success_rate ); ?>%</div>
					<div class="msh-audit-stat-desc"><?php
					echo esc_html(
						sprintf(
							/* translators: 1: number of successful calls, 2: total calls */
							__('%1$d of %2$d', 'my-site-hand'),
							$my_site_hand_today_successes,
							$my_site_hand_today_calls
						)
					);
					?></div>
				</div>

				<!-- Average Duration -->
				<div class="msh-audit-stat-card msh-audit-stat-card--duration">
					<div class="msh-audit-stat-label"><?php echo esc_html__( 'AVG DURATION', 'my-site-hand' ); ?></div>
					<div class="msh-audit-stat-value"><?php echo esc_html( $my_site_hand_stats['avg_duration'] ); ?>ms</div>
					<div class="msh-audit-stat-desc"><?php echo esc_html__( 'response time', 'my-site-hand' ); ?></div>
				</div>

				<!-- Errors last 24h -->
				<div class="msh-audit-stat-card msh-audit-stat-card--errors">
					<div class="msh-audit-stat-label"><?php echo esc_html__( 'ERRORS', 'my-site-hand' ); ?></div>
					<div class="msh-audit-stat-value"><?php echo esc_html( number_format( $my_site_hand_stats['errors_24h'] ) ); ?></div>
					<div class="msh-audit-stat-desc"><?php echo esc_html__( 'last 24h', 'my-site-hand' ); ?></div>
				</div>
			</div>

			<!-- Filters Bar -->
			<div class="msh-audit-filters-bar">
				<form method="get" id="msh-audit-filters-form">
					<input type="hidden" name="page" value="my-site-hand-audit" />
					
					<div class="msh-audit-filters-row-1">
						<!-- Search input -->
						<div class="msh-audit-search-wrap">
							<svg class="msh-audit-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
							<input type="text" name="search" class="msh-input msh-audit-search-input" placeholder="<?php esc_attr_e( 'Search by ability or client...', 'my-site-hand' ); ?>" value="<?php echo esc_attr( $my_site_hand_search_val ); ?>" onchange="this.form.submit()" />
						</div>

						<!-- Token Filter -->
						<select name="token_id" class="msh-select msh-audit-token-select" onchange="this.form.submit()">
							<option value=""><?php echo esc_html__( 'All', 'my-site-hand' ); ?></option>
							<?php foreach ( $my_site_hand_tokens as $my_site_hand_token ) : ?>
								<option value="<?php echo esc_attr( $my_site_hand_token['id'] ); ?>" <?php selected( $my_site_hand_token_id_val, $my_site_hand_token['id'] ); ?>>
									<?php echo esc_html( $my_site_hand_token['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="msh-audit-filters-row-2">
						<!-- Time Filter select -->
						<select name="date_range" class="msh-select msh-audit-range-select" onchange="this.form.submit()">
							<option value="all" <?php selected( $my_site_hand_date_range, 'all' ); ?>><?php echo esc_html__( 'All time', 'my-site-hand' ); ?></option>
							<option value="24h" <?php selected( $my_site_hand_date_range, '24h' ); ?>><?php echo esc_html__( 'Last 24 hours', 'my-site-hand' ); ?></option>
							<option value="7d" <?php selected( $my_site_hand_date_range, '7d' ); ?>><?php echo esc_html__( 'Last 7 days', 'my-site-hand' ); ?></option>
							<option value="30d" <?php selected( $my_site_hand_date_range, '30d' ); ?>><?php echo esc_html__( 'Last 30 days', 'my-site-hand' ); ?></option>
						</select>

						<?php if ( ! empty( $my_site_hand_search_val ) || ! empty( $my_site_hand_token_id_val ) || $my_site_hand_date_range !== 'all' ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand-audit' ) ); ?>" class="msh-btn msh-btn--ghost msh-btn--sm msh-audit-reset-btn">
								<?php echo esc_html__( 'Reset Filters', 'my-site-hand' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<!-- Collapsible Logs Cards Stack -->
			<div class="msh-audit-logs-stack">
				<?php if ( empty( $my_site_hand_logs ) ) : ?>
					<div class="msh-tokens-empty-state" style="padding: 60px 24px;">
						<div class="msh-tokens-empty-icon" style="background: #f3f4f6; color: #9ca3af;">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
						</div>
						<h3 class="msh-tokens-empty-heading"><?php echo esc_html__( 'No entries found', 'my-site-hand' ); ?></h3>
						<p class="msh-tokens-empty-desc"><?php echo esc_html__( 'No log entries match the selected filters.', 'my-site-hand' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $my_site_hand_logs as $my_site_hand_log ) :
						$my_site_hand_id = $my_site_hand_log['id'];
						$my_site_hand_input_data = json_decode( $my_site_hand_log['input_json'], true );
						
						// Determine status class and label
						$my_site_hand_status = $my_site_hand_log['result_status'];
						$my_site_hand_status_code = '200';
						$my_site_hand_status_class = 'success';
						$my_site_hand_status_full = '200 OK';
						
						if ( 'error' === $my_site_hand_status ) {
							$my_site_hand_status_code = '500';
							$my_site_hand_status_class = 'error';
							$my_site_hand_status_full = '500 Internal Error';
						} elseif ( 'rate_limited' === $my_site_hand_status ) {
							$my_site_hand_status_code = '429';
							$my_site_hand_status_class = 'warning';
							$my_site_hand_status_full = '429 Too Many Requests';
						}
						
						$my_site_hand_client_name = $my_site_hand_token_map[ $my_site_hand_log['token_id'] ] ?? __( 'Unknown Client', 'my-site-hand' );
						$my_site_hand_formatted_time = wp_date( 'H:i:s', strtotime( $my_site_hand_log['executed_at'] ) );
						
						// Beautiful date format for timestamp field
						$my_site_hand_full_timestamp = wp_date( 'M d · H:i:s', strtotime( $my_site_hand_log['executed_at'] ) ) . '.000';
					?>
						<div class="msh-audit-log-card">
							<!-- Header -->
							<div class="msh-audit-log-header" onclick="
								const detail = document.getElementById('log-detail-<?php echo esc_js($my_site_hand_id); ?>');
								const chevron = document.getElementById('log-chevron-<?php echo esc_js($my_site_hand_id); ?>');
								if (detail.style.display === 'none') {
									detail.style.display = 'block';
									chevron.style.transform = 'rotate(90deg)';
								} else {
									detail.style.display = 'none';
									chevron.style.transform = 'none';
								}
							">
								<div class="msh-audit-log-header-left">
									<div class="msh-audit-log-chevron-wrap">
										<svg id="log-chevron-<?php echo esc_attr( $my_site_hand_id ); ?>" class="msh-audit-log-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
											<polyline points="9 18 15 12 9 6"></polyline>
										</svg>
									</div>
									<span class="msh-audit-log-time"><?php echo esc_html( $my_site_hand_formatted_time ); ?></span>
									<span class="msh-audit-status-badge msh-audit-status-badge--<?php echo esc_attr( $my_site_hand_status_class ); ?>">
										<?php echo esc_html( $my_site_hand_status_code ); ?>
									</span>
									<span class="msh-audit-ability-name"><?php echo esc_html( $my_site_hand_all_abilities[ $my_site_hand_log['ability_name'] ]['label'] ?? $my_site_hand_log['ability_name'] ); ?></span>
								</div>
								
								<div class="msh-audit-log-header-right">
									<span class="msh-audit-client-name"><?php echo esc_html( $my_site_hand_client_name ); ?></span>
									<span class="msh-audit-duration">
										<?php 
										echo $my_site_hand_log['duration_ms'] !== null 
											? esc_html(
												sprintf(
													/* translators: %d: execution duration in milliseconds */
													__('%dms', 'my-site-hand'),
													$my_site_hand_log['duration_ms']
												)
											)
											: esc_html__( '—', 'my-site-hand' );
										?>
									</span>
								</div>
							</div>
							
							<!-- Collapsible Details Body -->
							<div class="msh-audit-log-body" id="log-detail-<?php echo esc_attr( $my_site_hand_id ); ?>" style="display: none;">
								<div class="msh-audit-meta-row">
									<span class="msh-audit-meta-label"><?php echo esc_html__( 'TIMESTAMP', 'my-site-hand' ); ?></span>
									<span class="msh-audit-meta-val"><?php echo esc_html( $my_site_hand_full_timestamp ); ?></span>
								</div>
								
								<div class="msh-audit-meta-row">
									<span class="msh-audit-meta-label"><?php echo esc_html__( 'SOURCE IP', 'my-site-hand' ); ?></span>
									<span class="msh-audit-meta-val"><?php echo esc_html( $my_site_hand_log['ip_address'] ); ?></span>
								</div>
								
								<div class="msh-audit-meta-row">
									<span class="msh-audit-meta-label"><?php echo esc_html__( 'STATUS', 'my-site-hand' ); ?></span>
									<span class="msh-audit-meta-val"><?php echo esc_html( $my_site_hand_status_full ); ?></span>
								</div>
								
								<!-- Payload block -->
								<div class="msh-audit-meta-row msh-audit-meta-row--block">
									<span class="msh-audit-meta-label"><?php echo esc_html__( 'PAYLOAD', 'my-site-hand' ); ?></span>
									<pre class="msh-audit-payload-pre"><code><?php echo esc_html( json_encode( $my_site_hand_input_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></code></pre>
								</div>
								
								<!-- Results block if error or summary exists -->
								<?php if ( ! empty( $my_site_hand_log['result_summary'] ) ) : ?>
									<div class="msh-audit-meta-row msh-audit-meta-row--block">
										<span class="msh-audit-meta-label"><?php echo esc_html__( 'EXECUTION RESULT / ERROR SUMMARY', 'my-site-hand' ); ?></span>
										<pre class="msh-audit-payload-pre"><code><?php echo esc_html( $my_site_hand_log['result_summary'] ); ?></code></pre>
									</div>
								<?php endif; ?>
								
								<!-- User Agent block -->
								<div class="msh-audit-meta-row msh-audit-meta-row--block" style="border-bottom: none; padding-bottom: 0; margin-bottom: 0;">
									<span class="msh-audit-meta-label"><?php echo esc_html__( 'USER AGENT', 'my-site-hand' ); ?></span>
									<span class="msh-audit-meta-val" style="font-size: 12px; font-family: var(--msh-font-mono); line-height: 1.4;"><?php echo esc_html( $my_site_hand_log['user_agent'] ?: '—' ); ?></span>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Pagination -->
			<?php if ( $my_site_hand_pages > 1 ) : ?>
				<div class="msh-pagination" style="margin-top: 32px; display: flex; gap: 6px; justify-content: center;">
					<?php for ( $my_site_hand_p = 1; $my_site_hand_p <= $my_site_hand_pages; $my_site_hand_p++ ) : 
						$my_site_hand_page_url = add_query_arg( [
							'paged'      => $my_site_hand_p,
							'search'     => $my_site_hand_search_val,
							'token_id'   => $my_site_hand_token_id_val,
							'date_range' => $my_site_hand_date_range,
						] );
					?>
						<a href="<?php echo esc_url( $my_site_hand_page_url ); ?>"
							class="msh-pagination-btn <?php echo ( $my_site_hand_filters['page'] ?? 1 ) === $my_site_hand_p ? 'msh-pagination-btn--active' : ''; ?>">
							<?php echo esc_html( $my_site_hand_p ); ?>
						</a>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</div>

	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>

