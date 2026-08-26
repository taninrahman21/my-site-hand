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

// Repairs made by hand from the Site Health report share this log with AI
// calls, but they are not registered abilities and need their own label.
$my_site_hand_pseudo_abilities = [
	'my-site-hand/quick-fix' => __( 'Quick Fix', 'my-site-hand' ),
];

$my_site_hand_has_filters = ! empty( $my_site_hand_search_val ) || ! empty( $my_site_hand_token_id_val ) || 'all' !== $my_site_hand_date_range;

// Range of entries shown on this page.
$my_site_hand_first = $my_site_hand_total > 0 ? ( ( $my_site_hand_filters['page'] - 1 ) * 25 ) + 1 : 0;
$my_site_hand_last  = min( $my_site_hand_total, $my_site_hand_filters['page'] * 25 );

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
			<div class="msh-body-grid">

				<!-- On this page -->
				<nav class="msh-index" aria-label="<?php esc_attr_e( 'On this page', 'my-site-hand' ); ?>">
					<p class="msh-index-label"><?php esc_html_e( 'On this page', 'my-site-hand' ); ?></p>
					<a class="msh-index-link msh-index-link--active" href="#msh-aud-today"><span class="msh-index-no">01</span><?php esc_html_e( 'Today', 'my-site-hand' ); ?></a>
					<a class="msh-index-link" href="#msh-aud-filters"><span class="msh-index-no">02</span><?php esc_html_e( 'Filters', 'my-site-hand' ); ?></a>
					<a class="msh-index-link" href="#msh-aud-entries"><span class="msh-index-no">03</span><?php esc_html_e( 'Entries', 'my-site-hand' ); ?><span class="msh-index-ct"><?php echo esc_html( number_format_i18n( $my_site_hand_total ) ); ?></span></a>
					<div class="msh-index-foot">
						<p><?php
						printf(
							/* translators: %d: number of errors in the last 24 hours */
							esc_html( _n( '%d failure in the last 24 hours.', '%d failures in the last 24 hours.', (int) $my_site_hand_stats['errors_24h'], 'my-site-hand' ) ),
							(int) $my_site_hand_stats['errors_24h']
						); ?></p>
						<a href="<?php echo esc_url( rest_url( 'my-site-hand/v1/audit-log/export?nonce=' . $my_site_hand_nonce ) ); ?>"><?php esc_html_e( 'Export CSV', 'my-site-hand' ); ?></a>
					</div>
				</nav>

				<div class="msh-body-main">

					<div class="msh-page-head">
						<div class="msh-page-head-info">
							<p class="msh-eyebrow"><i></i><?php esc_html_e( 'Record', 'my-site-hand' ); ?></p>
							<h2 class="msh-page-title"><?php esc_html_e( 'Audit', 'my-site-hand' ); ?></h2>
							<p class="msh-page-desc"><?php
							printf(
								/* translators: %d: log retention period in days */
								esc_html__( 'Every call, what it sent, and what came back. Kept for %d days.', 'my-site-hand' ),
								(int) get_option( 'mysitehand_log_retention_days', 30 )
							); ?></p>
						</div>
						<div class="msh-page-head-actions">
							<a href="<?php echo esc_url( rest_url( 'my-site-hand/v1/audit-log/export?nonce=' . $my_site_hand_nonce ) ); ?>" class="msh-btn">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="square"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
								<?php esc_html_e( 'Export CSV', 'my-site-hand' ); ?>
							</a>
						</div>
					</div>

					<!-- 01 Today -->
					<div class="msh-audit-stats-grid" id="msh-aud-today">
						<div class="msh-audit-stat-card msh-audit-stat-card--today">
							<div class="msh-audit-stat-label"><?php esc_html_e( 'Entries today', 'my-site-hand' ); ?></div>
							<div class="msh-audit-stat-value"><?php echo esc_html( number_format_i18n( $my_site_hand_today_calls ) ); ?></div>
							<div class="msh-audit-stat-desc"><?php
							printf(
								/* translators: %d: number of tokens */
								esc_html( _n( 'Across %d token', 'Across %d tokens', count( $my_site_hand_tokens ), 'my-site-hand' ) ),
								(int) count( $my_site_hand_tokens )
							); ?></div>
						</div>
						<div class="msh-audit-stat-card msh-audit-stat-card--success">
							<div class="msh-audit-stat-label"><?php esc_html_e( 'Succeeded', 'my-site-hand' ); ?></div>
							<div class="msh-audit-stat-value"><?php echo esc_html( $my_site_hand_success_rate ); ?><small>%</small></div>
							<div class="msh-audit-stat-desc"><?php
							echo esc_html(
								sprintf(
									/* translators: 1: number of successful calls, 2: total calls */
									__( '%1$s of %2$s', 'my-site-hand' ),
									number_format_i18n( $my_site_hand_today_successes ),
									number_format_i18n( $my_site_hand_today_calls )
								)
							);
							?></div>
						</div>
						<div class="msh-audit-stat-card msh-audit-stat-card--duration">
							<div class="msh-audit-stat-label"><?php esc_html_e( 'Average', 'my-site-hand' ); ?></div>
							<div class="msh-audit-stat-value"><?php echo esc_html( number_format_i18n( (float) $my_site_hand_stats['avg_duration'], 0 ) ); ?><small>&thinsp;ms</small></div>
							<div class="msh-audit-stat-desc"><?php esc_html_e( 'Over the last 30 days', 'my-site-hand' ); ?></div>
						</div>
						<div class="msh-audit-stat-card msh-audit-stat-card--errors">
							<div class="msh-audit-stat-label"><?php esc_html_e( 'Failures', 'my-site-hand' ); ?></div>
							<div class="msh-audit-stat-value"><?php echo esc_html( number_format_i18n( (int) $my_site_hand_stats['errors_24h'] ) ); ?></div>
							<div class="msh-audit-stat-desc"><?php esc_html_e( 'Last 24 hours', 'my-site-hand' ); ?></div>
						</div>
					</div>

					<!-- 02 Filters -->
					<div class="msh-audit-filters-bar" id="msh-aud-filters">
						<form method="get" id="msh-audit-filters-form">
							<input type="hidden" name="page" value="my-site-hand-audit" />

							<div class="msh-audit-filters-row-1">
								<div class="msh-audit-search-wrap">
									<svg class="msh-audit-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
									<input type="text" name="search" class="msh-input msh-audit-search-input" placeholder="<?php esc_attr_e( 'Search ability or client', 'my-site-hand' ); ?>" value="<?php echo esc_attr( $my_site_hand_search_val ); ?>" onchange="this.form.submit()" />
								</div>
							</div>

							<div class="msh-audit-filters-row-2">
								<select name="token_id" class="msh-select msh-audit-token-select" onchange="this.form.submit()">
									<option value=""><?php esc_html_e( 'All tokens', 'my-site-hand' ); ?></option>
									<?php foreach ( $my_site_hand_tokens as $my_site_hand_token ) : ?>
										<option value="<?php echo esc_attr( $my_site_hand_token['id'] ); ?>" <?php selected( $my_site_hand_token_id_val, $my_site_hand_token['id'] ); ?>>
											<?php echo esc_html( $my_site_hand_token['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>

								<select name="date_range" class="msh-select msh-audit-range-select" onchange="this.form.submit()">
									<option value="all" <?php selected( $my_site_hand_date_range, 'all' ); ?>><?php esc_html_e( 'All time', 'my-site-hand' ); ?></option>
									<option value="24h" <?php selected( $my_site_hand_date_range, '24h' ); ?>><?php esc_html_e( 'Last 24 hours', 'my-site-hand' ); ?></option>
									<option value="7d" <?php selected( $my_site_hand_date_range, '7d' ); ?>><?php esc_html_e( 'Last 7 days', 'my-site-hand' ); ?></option>
									<option value="30d" <?php selected( $my_site_hand_date_range, '30d' ); ?>><?php esc_html_e( 'Last 30 days', 'my-site-hand' ); ?></option>
								</select>

								<?php if ( $my_site_hand_has_filters ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand-audit' ) ); ?>" class="msh-btn msh-audit-reset-btn">
										<?php esc_html_e( 'Reset', 'my-site-hand' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</form>
					</div>

					<!-- 03 Entries -->
					<div class="msh-card" id="msh-aud-entries">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">03</span>
								<div>
									<h3><?php esc_html_e( 'Entries', 'my-site-hand' ); ?></h3>
									<p><?php esc_html_e( 'Click a row for the payload and the exact reason a call failed.', 'my-site-hand' ); ?></p>
								</div>
							</div>
							<span class="msh-sheet-tag"><?php
							if ( $my_site_hand_total > 0 ) {
								printf(
									/* translators: 1: first entry number, 2: last entry number, 3: total entries */
									esc_html__( '%1$s–%2$s of %3$s', 'my-site-hand' ),
									esc_html( number_format_i18n( $my_site_hand_first ) ),
									esc_html( number_format_i18n( $my_site_hand_last ) ),
									esc_html( number_format_i18n( $my_site_hand_total ) )
								);
							} else {
								esc_html_e( 'No entries', 'my-site-hand' );
							}
							?></span>
						</div>

						<div class="msh-audit-logs-stack" style="border: none;">
							<?php if ( empty( $my_site_hand_logs ) ) : ?>
								<div class="msh-tokens-empty-state">
									<div class="msh-tokens-empty-icon">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
									</div>
									<h3 class="msh-tokens-empty-heading"><?php
									echo $my_site_hand_has_filters
										? esc_html__( 'No entries found', 'my-site-hand' )
										: esc_html__( 'No AI actions recorded yet', 'my-site-hand' );
									?></h3>
									<p class="msh-tokens-empty-desc"><?php
									echo $my_site_hand_has_filters
										? esc_html__( 'No log entries match the selected filters.', 'my-site-hand' )
										: esc_html__( 'This is the record of what an AI assistant did on your site. Entries appear as soon as one connects.', 'my-site-hand' );
									?></p>
									<?php if ( ! $my_site_hand_has_filters ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand' ) ); ?>" class="msh-btn msh-btn--primary msh-tokens-empty-btn">
											<?php esc_html_e( 'Connect an AI assistant', 'my-site-hand' ); ?>
										</a>
										<p class="msh-tokens-empty-desc"><?php
										printf(
											/* translators: %s: link to the Site Health page */
											esc_html__( 'Not using an AI assistant? %s scans your site on its own, with no setup.', 'my-site-hand' ),
											'<a href="' . esc_url( admin_url( 'admin.php?page=my-site-hand-health' ) ) . '">' . esc_html__( 'Site Health', 'my-site-hand' ) . '</a>'
										);
										?></p>
									<?php endif; ?>
								</div>
							<?php else : ?>
								<?php foreach ( $my_site_hand_logs as $my_site_hand_log ) :
									$my_site_hand_id = $my_site_hand_log['id'];
									$my_site_hand_input_data = json_decode( $my_site_hand_log['input_json'], true );

									// Determine status class and label.
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

									if ( ! empty( $my_site_hand_log['token_id'] ) ) {
										$my_site_hand_client_name = $my_site_hand_token_map[ $my_site_hand_log['token_id'] ] ?? __( 'Unknown client', 'my-site-hand' );
									} else {
										// No token means a person did this from the admin — a Quick Fix
										// repair rather than an AI call.
										$my_site_hand_log_user    = ! empty( $my_site_hand_log['user_id'] ) ? get_userdata( (int) $my_site_hand_log['user_id'] ) : null;
										$my_site_hand_client_name = $my_site_hand_log_user
											? sprintf(
												/* translators: %s: WordPress user display name */
												__( '%s (from the admin)', 'my-site-hand' ),
												$my_site_hand_log_user->display_name
											)
											: __( 'Unknown client', 'my-site-hand' );
									}
									$my_site_hand_formatted_time = wp_date( 'H:i:s', strtotime( $my_site_hand_log['executed_at'] ) );
									$my_site_hand_full_timestamp = wp_date( 'j M Y \a\t H:i:s', strtotime( $my_site_hand_log['executed_at'] ) );
									?>
									<div class="msh-audit-log-card">
										<div class="msh-audit-log-header" onclick="
											const detail = document.getElementById('log-detail-<?php echo esc_js( $my_site_hand_id ); ?>');
											const chevron = document.getElementById('log-chevron-<?php echo esc_js( $my_site_hand_id ); ?>');
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
													<svg id="log-chevron-<?php echo esc_attr( $my_site_hand_id ); ?>" class="msh-audit-log-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="square">
														<polyline points="9 18 15 12 9 6"></polyline>
													</svg>
												</div>
												<span class="msh-audit-log-time"><?php echo esc_html( $my_site_hand_formatted_time ); ?></span>
												<span class="msh-audit-status-badge msh-audit-status-badge--<?php echo esc_attr( $my_site_hand_status_class ); ?>"><?php echo esc_html( $my_site_hand_status_code ); ?></span>
												<span class="msh-audit-ability-name"><?php echo esc_html( $my_site_hand_all_abilities[ $my_site_hand_log['ability_name'] ]['label'] ?? ( $my_site_hand_pseudo_abilities[ $my_site_hand_log['ability_name'] ] ?? $my_site_hand_log['ability_name'] ) ); ?></span>
											</div>

											<div class="msh-audit-log-header-right">
												<span class="msh-audit-client-name"><?php echo esc_html( $my_site_hand_client_name ); ?></span>
												<span class="msh-audit-duration"><?php
												echo null !== $my_site_hand_log['duration_ms']
													? esc_html(
														sprintf(
															/* translators: %d: execution duration in milliseconds */
															__( '%d ms', 'my-site-hand' ),
															(int) $my_site_hand_log['duration_ms']
														)
													)
													: esc_html( '—' );
												?></span>
											</div>
										</div>

										<div class="msh-audit-log-body" id="log-detail-<?php echo esc_attr( $my_site_hand_id ); ?>" style="display: none;">
											<div class="msh-audit-meta-row">
												<span class="msh-audit-meta-label"><?php esc_html_e( 'When', 'my-site-hand' ); ?></span>
												<span class="msh-audit-meta-val"><?php echo esc_html( $my_site_hand_full_timestamp ); ?></span>
											</div>

											<div class="msh-audit-meta-row">
												<span class="msh-audit-meta-label"><?php esc_html_e( 'Source', 'my-site-hand' ); ?></span>
												<span class="msh-audit-meta-val" style="font-family: var(--msh-font-mono); font-size: 12px;"><?php echo esc_html( $my_site_hand_log['ip_address'] ); ?></span>
											</div>

											<div class="msh-audit-meta-row">
												<span class="msh-audit-meta-label"><?php esc_html_e( 'Ability', 'my-site-hand' ); ?></span>
												<span class="msh-audit-meta-val" style="font-family: var(--msh-font-mono); font-size: 12px;"><?php echo esc_html( $my_site_hand_log['ability_name'] ); ?></span>
											</div>

											<div class="msh-audit-meta-row">
												<span class="msh-audit-meta-label"><?php esc_html_e( 'Status', 'my-site-hand' ); ?></span>
												<span class="msh-audit-meta-val"><?php echo esc_html( $my_site_hand_status_full ); ?></span>
											</div>

											<div class="msh-audit-meta-row msh-audit-meta-row--block">
												<span class="msh-audit-meta-label"><?php esc_html_e( 'Sent', 'my-site-hand' ); ?></span>
												<pre class="msh-audit-payload-pre"><code><?php echo esc_html( wp_json_encode( $my_site_hand_input_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></code></pre>
											</div>

											<?php if ( ! empty( $my_site_hand_log['result_summary'] ) ) : ?>
												<div class="msh-audit-meta-row msh-audit-meta-row--block">
													<span class="msh-audit-meta-label"><?php
													echo 'success' === $my_site_hand_status
														? esc_html__( 'Came back', 'my-site-hand' )
														: esc_html__( 'Why it failed', 'my-site-hand' );
													?></span>
													<pre class="msh-audit-payload-pre <?php echo 'success' === $my_site_hand_status ? '' : 'msh-pre--err'; ?>"><code><?php echo esc_html( $my_site_hand_log['result_summary'] ); ?></code></pre>
												</div>
											<?php endif; ?>

											<div class="msh-audit-meta-row">
												<span class="msh-audit-meta-label"><?php esc_html_e( 'User agent', 'my-site-hand' ); ?></span>
												<span class="msh-audit-meta-val" style="font-family: var(--msh-font-mono); font-size: 11.5px;"><?php echo esc_html( $my_site_hand_log['user_agent'] ?: '—' ); ?></span>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>

					<!-- Pagination -->
					<?php if ( $my_site_hand_pages > 1 ) : ?>
						<div class="msh-pagination">
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
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
