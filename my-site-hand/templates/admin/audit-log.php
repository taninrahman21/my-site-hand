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

// Current filters.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$my_site_hand_filters = [
	'per_page'     => 25,
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'page'         => max( 1, absint( $_GET['paged'] ?? 1 ) ),
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'token_id'     => ! empty( $_GET['token_id'] ) ? absint( $_GET['token_id'] ) : null,
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'ability_name' => ! empty( $_GET['ability'] ) ? sanitize_text_field( wp_unslash( $_GET['ability'] ) ) : null,
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'status'       => ! empty( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null,
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_from'    => ! empty( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : null,
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_to'      => ! empty( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : null,
];

$my_site_hand_filters = array_filter( $my_site_hand_filters );
$my_site_hand_filters['per_page'] = 25;

$my_site_hand_result      = $my_site_hand_audit->get_logs( $my_site_hand_filters );
$my_site_hand_logs        = $my_site_hand_result['logs'];
$my_site_hand_total       = $my_site_hand_result['total'];
$my_site_hand_pages       = $my_site_hand_result['pages'];
$my_site_hand_stats       = $my_site_hand_audit->get_stats();
$my_site_hand_tokens      = $my_site_hand_auth->list_tokens( 0 );
$my_site_hand_ability_names = array_keys( $my_site_hand_registry->get_all() );
$my_site_hand_nonce       = wp_create_nonce( 'my_site_hand_admin' );
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-page-header" style="display: flex; justify-content: space-between; align-items: center;">
				<div>
					<h2><?php echo esc_html__( 'Audit Log', 'my-site-hand' ); ?></h2>
					<p class="msh-page-desc"><?php echo esc_html__( 'Monitor all MCP requests and their execution results.', 'my-site-hand' ); ?></p>
				</div>
				<a href="<?php echo esc_url( rest_url( 'my-site-hand/v1/audit-log/export?nonce=' . $my_site_hand_nonce ) ); ?>" class="msh-btn msh-btn--ghost">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
					<?php echo esc_html__( 'Export CSV', 'my-site-hand' ); ?>
				</a>
			</div>

			<!-- Summary Stats -->
			<div class="msh-stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 32px;">
				<div class="msh-stat-card">
					<div class="msh-stat-value"><?php echo esc_html( number_format( $my_site_hand_stats['calls_today'] ) ); ?></div>
					<div class="msh-stat-label"><?php echo esc_html__( 'Calls Today', 'my-site-hand' ); ?></div>
				</div>
				<div class="msh-stat-card">
					<div class="msh-stat-value"><?php echo esc_html( $my_site_hand_stats['error_rate'] ); ?>%</div>
					<div class="msh-stat-label"><?php echo esc_html__( 'Error Rate', 'my-site-hand' ); ?></div>
				</div>
				<div class="msh-stat-card">
					<div class="msh-stat-value"><?php echo esc_html( $my_site_hand_stats['avg_duration'] ); ?>ms</div>
					<div class="msh-stat-label"><?php echo esc_html__( 'Avg Duration', 'my-site-hand' ); ?></div>
				</div>
				<div class="msh-stat-card">
					<div class="msh-stat-value"><?php echo esc_html( number_format( $my_site_hand_total ) ); ?></div>
					<div class="msh-stat-label"><?php echo esc_html__( 'Total Entries', 'my-site-hand' ); ?></div>
				</div>
			</div>

			<!-- Filters Bar -->
			<div class="msh-card msh-filters-bar" style="margin-bottom: 24px;">
				<div class="msh-card-body" style="padding: 16px 24px;">
					<form method="get" id="msh-audit-filters" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
						<input type="hidden" name="page" value="my-site-hand-audit" />

						<select name="token_id" class="msh-select" style="max-width: 180px;" onchange="this.form.submit()">
							<option value=""><?php echo esc_html__( 'All Tokens', 'my-site-hand' ); ?></option>
							<?php foreach ( $my_site_hand_tokens as $my_site_hand_token ) : ?>
							<option value="<?php echo esc_attr( $my_site_hand_token['id'] ); ?>" <?php selected( $my_site_hand_filters['token_id'] ?? '', $my_site_hand_token['id'] ); ?>>
								<?php echo esc_html( $my_site_hand_token['label'] ); ?>
							</option>
							<?php endforeach; ?>
						</select>

						<select name="ability" class="msh-select" style="max-width: 220px;" onchange="this.form.submit()">
							<option value=""><?php echo esc_html__( 'All Abilities', 'my-site-hand' ); ?></option>
							<?php 
							$my_site_hand_all_abilities = $my_site_hand_registry->get_all();
							foreach ( $my_site_hand_ability_names as $my_site_hand_ability ) : 
								$my_site_hand_label = $my_site_hand_all_abilities[ $my_site_hand_ability ]['label'] ?? $my_site_hand_ability;
							?>
							<option value="<?php echo esc_attr( $my_site_hand_ability ); ?>" <?php selected( $my_site_hand_filters['ability_name'] ?? '', $my_site_hand_ability ); ?>>
								<?php echo esc_html( $my_site_hand_label ); ?>
							</option>
							<?php endforeach; ?>
						</select>

						<select name="status" class="msh-select" style="max-width: 150px;" onchange="this.form.submit()">
							<option value=""><?php echo esc_html__( 'All Statuses', 'my-site-hand' ); ?></option>
							<option value="success" <?php selected( $my_site_hand_filters['status'] ?? '', 'success' ); ?>><?php echo esc_html__( 'Success', 'my-site-hand' ); ?></option>
							<option value="error" <?php selected( $my_site_hand_filters['status'] ?? '', 'error' ); ?>><?php echo esc_html__( 'Error', 'my-site-hand' ); ?></option>
						</select>

						<div style="display: flex; gap: 8px; align-items: center;">
							<input type="date" name="date_from" class="msh-input" style="width: auto; padding: 6px 12px;" value="<?php echo esc_attr( $my_site_hand_filters['date_from'] ?? '' ); ?>" onchange="this.form.submit()" />
							<span style="color: var(--msh-text-muted);">&rarr;</span>
							<input type="date" name="date_to" class="msh-input" style="width: auto; padding: 6px 12px;" value="<?php echo esc_attr( $my_site_hand_filters['date_to'] ?? '' ); ?>" onchange="this.form.submit()" />
						</div>

						<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand-audit' ) ); ?>" class="msh-btn msh-btn--ghost msh-btn--sm" style="margin-left: auto;"><?php echo esc_html__( 'Reset', 'my-site-hand' ); ?></a>
					</form>
				</div>
			</div>

			<!-- Log Table -->
			<div class="msh-card">
				<div class="msh-card-body msh-card--no-pad">
					<?php if ( empty( $my_site_hand_logs ) ) : ?>
					<div class="msh-empty-state" style="padding: 60px 24px; text-align: center;">
						<p style="color: var(--msh-text-muted);"><?php echo esc_html__( 'No log entries found for the current filters.', 'my-site-hand' ); ?></p>
					</div>
					<?php else : ?>
					<div class="msh-table-wrap">
						<table class="msh-table msh-audit-table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Time', 'my-site-hand' ); ?></th>
									<th><?php echo esc_html__( 'Ability', 'my-site-hand' ); ?></th>
									<th><?php echo esc_html__( 'Status', 'my-site-hand' ); ?></th>
									<th><?php echo esc_html__( 'Duration', 'my-site-hand' ); ?></th>
									<th><?php echo esc_html__( 'IP', 'my-site-hand' ); ?></th>
									<th style="width: 100px;"></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $my_site_hand_logs as $my_site_hand_log ) :
								$my_site_hand_input_data = json_decode( $my_site_hand_log['input_json'], true );
							?>
							<tr class="msh-log-row" id="log-row-<?php echo esc_attr( $my_site_hand_log['id'] ); ?>">
								<td>
									<?php 
									echo esc_html( 
										sprintf( 
											/* translators: %s: relative time */
											__( '%s ago', 'my-site-hand' ), 
											human_time_diff( strtotime( $my_site_hand_log['executed_at'] ) ) 
										) 
									); 
									?>
								</td>
								<td>
									<div style="font-weight: 500;"><?php echo esc_html( $my_site_hand_all_abilities[ $my_site_hand_log['ability_name'] ]['label'] ?? $my_site_hand_log['ability_name'] ); ?></div>
								</td>
								<td>
									<span class="msh-badge msh-badge--<?php echo esc_attr( $my_site_hand_log['result_status'] ); ?>">
										<?php 
										$my_site_hand_status = $my_site_hand_log['result_status'];
										if ( 'success' === $my_site_hand_status ) {
											echo esc_html__( 'Success', 'my-site-hand' );
										} elseif ( 'error' === $my_site_hand_status ) {
											echo esc_html__( 'Error', 'my-site-hand' );
										} elseif ( 'rate_limited' === $my_site_hand_status ) {
											echo esc_html__( 'Rate Limited', 'my-site-hand' );
										} else {
											echo esc_html( $my_site_hand_status );
										}
										?>
									</span>
								</td>
								<td style="font-size: 13px; color: var(--msh-text-secondary);">
									<?php 
									echo esc_html( 
										$my_site_hand_log['duration_ms'] !== null 
											? sprintf( 
												/* translators: %d: duration in milliseconds */
												__( '%dms', 'my-site-hand' ), 
												$my_site_hand_log['duration_ms'] 
											) 
											: '—' 
									); 
									?>
								</td>
								<td class="msh-td--ip"><?php echo esc_html( $my_site_hand_log['ip_address'] ); ?></td>
								<td>
									<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" onclick="msh.expandLogRow(<?php echo absint( $my_site_hand_log['id'] ); ?>)">
										<?php echo esc_html__( 'Details', 'my-site-hand' ); ?>
									</button>
								</td>
							</tr>
							<tr class="msh-log-detail" id="log-detail-<?php echo esc_attr( $my_site_hand_log['id'] ); ?>" style="display:none; background: var(--msh-bg);">
								<td colspan="6" style="padding: 0;">
									<div class="msh-log-detail-body" style="padding: 24px; border-bottom: 2px solid var(--msh-border);">
										<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
											<div class="msh-log-detail-section">
												<h4 style="margin: 0 0 12px; font-size: 12px; text-transform: uppercase; color: var(--msh-text-muted);"><?php echo esc_html__( 'Input Parameters', 'my-site-hand' ); ?></h4>
												<pre style="margin: 0; padding: 16px; background: #fff; border: 1px solid var(--msh-border); border-radius: 4px; font-size: 12px; overflow-x: auto; color: #1d2327;"><?php echo esc_html( json_encode( $my_site_hand_input_data, JSON_PRETTY_PRINT ) ); ?></pre>
											</div>
											<?php if ( $my_site_hand_log['result_summary'] ) : ?>
											<div class="msh-log-detail-section">
												<h4 style="margin: 0 0 12px; font-size: 12px; text-transform: uppercase; color: var(--msh-text-muted);"><?php echo esc_html__( 'Execution Result', 'my-site-hand' ); ?></h4>
												<pre style="margin: 0; padding: 16px; background: #fff; border: 1px solid var(--msh-border); border-radius: 4px; font-size: 12px; overflow-x: auto; color: #1d2327;"><?php echo esc_html( $my_site_hand_log['result_summary'] ); ?></pre>
											</div>
											<?php endif; ?>
										</div>
										<div style="margin-top: 16px; font-size: 11px; color: var(--msh-text-muted);">
											<strong><?php echo esc_html__( 'User Agent:', 'my-site-hand' ); ?></strong> <?php echo esc_html( $my_site_hand_log['user_agent'] ?: '—' ); ?>
										</div>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Pagination -->
			<?php if ( $my_site_hand_pages > 1 ) : ?>
			<div class="msh-pagination" style="margin-top: 24px; display: flex; gap: 4px; justify-content: center;">
				<?php for ( $my_site_hand_p = 1; $my_site_hand_p <= $my_site_hand_pages; $my_site_hand_p++ ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'paged', $my_site_hand_p ) ); ?>"
					style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 4px; border: 1px solid var(--msh-border); background: <?php echo ( $my_site_hand_filters['page'] ?? 1 ) === $my_site_hand_p ? 'var(--msh-primary)' : '#fff'; ?>; color: <?php echo ( $my_site_hand_filters['page'] ?? 1 ) === $my_site_hand_p ? '#fff' : 'var(--msh-text)'; ?>; text-decoration: none; font-size: 13px; font-weight: 600;">
					<?php echo esc_html( $my_site_hand_p ); ?>
				</a>
				<?php endfor; ?>
			</div>
			<?php endif; ?>
		</div>

		<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
	</div>
</div>
