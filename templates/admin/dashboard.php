<?php
/**
 * Admin dashboard template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_plugin = \MySiteHand\Plugin::get_instance();
$my_site_hand_registry = $my_site_hand_plugin->get_abilities_registry();
$my_site_hand_auth = $my_site_hand_plugin->get_auth_manager();
$my_site_hand_audit = $my_site_hand_plugin->get_audit_logger();

$my_site_hand_stats = $my_site_hand_audit->get_stats();
$my_site_hand_tokens = array_filter($my_site_hand_auth->list_tokens(0), function ($t) {
	return (int) $t['is_active'] === 1;
});
$my_site_hand_abilities = $my_site_hand_registry->get_all();
$my_site_hand_recent_logs = $my_site_hand_audit->get_logs(['per_page' => 5, 'page' => 1]);
$my_site_hand_mcp_endpoint = rest_url('my-site-hand/v1/mcp/streamable');
$my_site_hand_site_url = get_site_url();
$my_site_hand_is_enabled = (bool) get_option('mysitehand_enabled', true);
$my_site_hand_modules = $my_site_hand_plugin->get_modules();
$my_site_hand_enabled_mods = $my_site_hand_plugin->get_enabled_modules();

// Dynamic Calculations for Stats Cards
$my_site_hand_calls_today = $my_site_hand_stats['calls_today'];
$my_site_hand_calls_yesterday = $my_site_hand_stats['calls_yesterday'];
$my_site_hand_diff = $my_site_hand_calls_today - $my_site_hand_calls_yesterday;
$my_site_hand_pct_change = $my_site_hand_calls_yesterday > 0 ? round(($my_site_hand_diff / $my_site_hand_calls_yesterday) * 100) : 0;
$my_site_hand_trend_up = $my_site_hand_diff >= 0;

$my_site_hand_expiring_30d = $my_site_hand_auth->get_expiring_count(30);
$my_site_hand_public_count = count($my_site_hand_registry->get_mcp_public());
?>
<div class="msh-wrap">

	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">

			<!-- Page Header -->
			<div class="msh-page-header">
				<h2><?php esc_html_e('Overview', 'my-site-hand'); ?></h2>
				<p class="msh-page-desc"><?php esc_html_e('At-a-glance metrics, server health, and module status.', 'my-site-hand'); ?></p>
			</div>

			<!-- Top Status Bar -->
			<div class="msh-top-status">
				<div class="msh-status-indicator">
					<span class="msh-status-dot"></span>
					<strong><?php esc_html_e('MCP Server Active', 'my-site-hand'); ?></strong>
				</div>
				<span class="msh-status-separator">|</span>
				<div class="msh-status-center">
					<div class="msh-endpoint-code" id="msh-mcp-url-top">
						<?php echo esc_html(str_replace(['https://', 'http://'], '', $my_site_hand_mcp_endpoint)); ?>
					</div>
					<button type="button" class="msh-copy-btn msh-copy-btn--inline"
						onclick="msh.copyText('msh-mcp-url-top')">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
							style="margin-right: 4px; display: inline-block; vertical-align: middle;">
							<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
							<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
						</svg>
						<?php esc_html_e('Copy', 'my-site-hand'); ?>
					</button>
				</div>
				<span class="msh-status-separator">|</span>
				<div class="msh-status-protocol">
					<?php esc_html_e('Protocol 2024-11-05', 'my-site-hand'); ?>
				</div>
			</div>

			<!-- Stats Cards -->
			<div class="msh-stats-grid">
				<div class="msh-stat-card msh-stat--calls">
					<div class="msh-stat-icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
						</svg>
					</div>
					<div class="msh-stat-value" id="stat-calls-today">
						<?php echo esc_html(number_format($my_site_hand_stats['calls_today'])); ?>
					</div>
					<div class="msh-stat-label"><?php esc_html_e('API calls today', 'my-site-hand'); ?></div>
					<div
						class="msh-stat-meta msh-meta--<?php echo esc_attr($my_site_hand_trend_up ? 'up' : 'down'); ?>">
						<span
							class="msh-meta-icon"><?php echo esc_html($my_site_hand_pct_change === 0 ? '~' : ($my_site_hand_trend_up ? '↑' : '↓')); ?></span>
						<span><?php
						printf(
							/* translators: %d: percentage change in API calls */
							esc_html__('%d%% vs yesterday', 'my-site-hand'),
							(int) abs($my_site_hand_pct_change)
						); ?></span>
					</div>
				</div>
				<div class="msh-stat-card msh-stat--tokens">
					<div class="msh-stat-icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path
								d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4">
							</path>
						</svg>
					</div>
					<div class="msh-stat-value" id="stat-tokens"><?php echo esc_html(count($my_site_hand_tokens)); ?>
					</div>
					<div class="msh-stat-label"><?php esc_html_e('Active tokens', 'my-site-hand'); ?></div>
					<div class="msh-stat-meta">
						<span><?php
						printf(
							/* translators: %d: number of tokens expiring in 30 days */
							esc_html__('%d expire in 30d', 'my-site-hand'),
							(int) $my_site_hand_expiring_30d
						); ?></span>
					</div>
				</div>
				<div class="msh-stat-card msh-stat--abilities">
					<div class="msh-stat-icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
							<path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
							<path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"></path>
						</svg>
					</div>
					<div class="msh-stat-value" id="stat-abilities">
						<?php echo esc_html(count($my_site_hand_abilities)); ?></div>
					<div class="msh-stat-label"><?php esc_html_e('Abilities registered', 'my-site-hand'); ?></div>
					<div class="msh-stat-meta">
						<span><?php
						printf(
							/* translators: %d: number of public abilities */
							esc_html__('%d MCP-public', 'my-site-hand'),
							(int) $my_site_hand_public_count
						); ?></span>
					</div>
				</div>
				<div class="msh-stat-card msh-stat--errors">
					<div class="msh-stat-icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="10"></circle>
							<line x1="12" y1="8" x2="12" y2="12"></line>
							<line x1="12" y1="16" x2="12.01" y2="16"></line>
						</svg>
					</div>
					<div class="msh-stat-value" id="stat-errors">
						<?php echo esc_html(number_format($my_site_hand_stats['errors_24h'])); ?>
					</div>
					<div class="msh-stat-label"><?php esc_html_e('Errors (24h)', 'my-site-hand'); ?></div>
					<div class="msh-stat-meta">
						<span><?php
						printf(
							/* translators: %s: error rate percentage */
							esc_html__('Rate: %s%%', 'my-site-hand'),
							esc_html(number_format($my_site_hand_stats['error_rate'], 2))
						); ?></span>
					</div>
				</div>
			</div>

			<div class="msh-dashboard-body">

				<div class="msh-dashboard-main">
					<!-- Recent activity -->
					<div class="msh-card" style="margin-bottom: 0; height: 100%;">
						<div class="msh-card-header"
							style="border-bottom: none; padding-bottom: 0; display: flex; align-items: center; justify-content: space-between;">
							<div>
								<h2 style="font-size: 14px; font-weight: 600; margin: 0; color: var(--msh-secondary);">
									<?php esc_html_e('Recent activity', 'my-site-hand'); ?>
								</h2>
								<p style="font-size: 12px; color: var(--msh-text-muted); margin: 4px 0 0;">
									<?php esc_html_e('Live MCP request stream', 'my-site-hand'); ?>
								</p>
							</div>
							<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-audit')); ?>"
								class="msh-link"
								style="font-size: 12px; color: var(--msh-primary); text-decoration: none; font-weight: 500;"><?php esc_html_e('View audit log →', 'my-site-hand'); ?></a>
						</div>
						<div class="msh-card-body msh-card--no-pad">
							<?php if (empty($my_site_hand_recent_logs['logs'])): ?>
								<div class="msh-recent-activity-empty"
									style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center; gap: 12px;">
									<div class="msh-recent-activity-empty-icon"
										style="background: #f5f3ff; color: #6d28d9; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
										<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
										</svg>
									</div>
									<h3 style="margin: 0; font-size: 15px; font-weight: 600; color: var(--msh-secondary);">
										<?php esc_html_e('Waiting for activity', 'my-site-hand'); ?></h3>
									<p
										style="margin: 0; font-size: 13px; color: var(--msh-text-muted); max-width: 320px; line-height: 1.4;">
										<?php esc_html_e('Activity will appear here once your AI client makes a request.', 'my-site-hand'); ?>
									</p>
								</div>
							<?php else: ?>
								<div class="msh-table-wrap">
									<table class="msh-table" style="border-top: none;">
										<thead>
											<tr style="background: transparent;">
												<th
													style="background: transparent; border-bottom: 1px solid var(--msh-border); padding: 16px 24px; color: var(--msh-text-muted); font-size: 11px;">
													<?php esc_html_e('ABILITY', 'my-site-hand'); ?>
												</th>
												<th
													style="background: transparent; border-bottom: 1px solid var(--msh-border); padding: 16px 24px; color: var(--msh-text-muted); font-size: 11px;">
													<?php esc_html_e('TOKEN', 'my-site-hand'); ?>
												</th>
												<th
													style="background: transparent; border-bottom: 1px solid var(--msh-border); padding: 16px 24px; color: var(--msh-text-muted); font-size: 11px;">
													<?php esc_html_e('STATUS', 'my-site-hand'); ?>
												</th>
												<th
													style="background: transparent; border-bottom: 1px solid var(--msh-border); padding: 16px 24px; color: var(--msh-text-muted); font-size: 11px;">
													<?php esc_html_e('TIME', 'my-site-hand'); ?>
												</th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ($my_site_hand_recent_logs['logs'] as $my_site_hand_log):
												$my_site_hand_token_data = $my_site_hand_auth->get_token($my_site_hand_log['token_id']);
												$my_site_hand_token_name = $my_site_hand_token_data ? ($my_site_hand_token_data['label'] ?? '—') : '—';
												?>
												<tr>
													<td style="font-size: 13px;">
														<div style="font-weight: 500;">
															<?php echo esc_html($my_site_hand_abilities[$my_site_hand_log['ability_name']]['label'] ?? $my_site_hand_log['ability_name']); ?>
														</div>
													</td>
													<td style="color: var(--msh-text-secondary); font-size: 13px;">
														<?php echo esc_html($my_site_hand_token_name); ?>
													</td>
													<td>
														<span
															class="msh-badge msh-badge--<?php echo esc_attr($my_site_hand_log['result_status']); ?>">
															<?php
															$my_site_hand_status = $my_site_hand_log['result_status'];
															if ('success' === $my_site_hand_status) {
																esc_html_e('Success', 'my-site-hand');
															} elseif ('error' === $my_site_hand_status) {
																esc_html_e('Error', 'my-site-hand');
															} elseif ('rate_limited' === $my_site_hand_status) {
																esc_html_e('Rate Limited', 'my-site-hand');
															} else {
																echo esc_html($my_site_hand_status);
															}
															?>
														</span>
													</td>
													<td style="color: var(--msh-text-muted); font-size: 13px;">
														<?php
														echo esc_html(
															sprintf(
																/* translators: %s: relative time */
																__('%s ago', 'my-site-hand'),
																human_time_diff(strtotime($my_site_hand_log['executed_at']))
															)
														);
														?>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- Module Status -->
					<div class="msh-card" style="margin-bottom: 0;">
						<div class="msh-card-header"
							style="border-bottom: none; padding: 20px 24px 8px; display: flex; align-items: center; justify-content: space-between;">
							<div>
								<h2 style="font-size: 14px; font-weight: 600; margin: 0; color: var(--msh-secondary);">
									<?php esc_html_e('Module status', 'my-site-hand'); ?>
								</h2>
								<p style="font-size: 12px; color: var(--msh-text-muted); margin: 4px 0 0;">
									<?php
									printf(
										/* translators: 1: enabled count, 2: total count */
										esc_html__('%1$d of %2$d modules online', 'my-site-hand'),
										count($my_site_hand_enabled_mods),
										count($my_site_hand_modules)
									);
									?>
								</p>
							</div>
							<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-abilities')); ?>"
								class="msh-link"
								style="font-size: 12px; color: var(--msh-primary); text-decoration: none; font-weight: 500;"><?php esc_html_e('Manage', 'my-site-hand'); ?></a>
						</div>
						<div class="msh-card-body" style="padding: 12px 24px 24px;">
							<div class="msh-modules-grid">
								<?php
								$my_site_hand_all_module_defs = [
									'content' => ['label' => __('Content', 'my-site-hand')],
									'seo' => ['label' => __('SEO', 'my-site-hand')],
									'woocommerce' => ['label' => __('WooCommerce', 'my-site-hand')],
									'diagnostics' => ['label' => __('Diagnostics', 'my-site-hand')],
									'media' => ['label' => __('Media', 'my-site-hand')],
									'users' => ['label' => __('Users', 'my-site-hand')],
								];

								$my_site_hand_module_icons = [
									'content' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
									'seo' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
									'woocommerce' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
									'diagnostics' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
									'media' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
									'users' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
								];

								$my_site_hand_svg_allowed = [
									'svg' => [
										'width' => [],
										'height' => [],
										'viewbox' => [],
										'fill' => [],
										'stroke' => [],
										'stroke-width' => [],
										'stroke-linecap' => [],
										'stroke-linejoin' => [],
										'class' => [],
									],
									'path' => ['d' => []],
									'rect' => [
										'x' => [],
										'y' => [],
										'width' => [],
										'height' => [],
										'rx' => [],
										'ry' => [],
									],
									'polyline' => ['points' => []],
									'line' => [
										'x1' => [],
										'y1' => [],
										'x2' => [],
										'y2' => [],
										'stroke' => [],
									],
									'circle' => [
										'cx' => [],
										'cy' => [],
										'r' => [],
									],
									'polygon' => ['points' => []],
								];

								foreach ($my_site_hand_all_module_defs as $my_site_hand_slug => $my_site_hand_def):
									$my_site_hand_module_obj = $my_site_hand_modules[$my_site_hand_slug] ?? null;
									$my_site_hand_ability_count = $my_site_hand_module_obj ? count($my_site_hand_module_obj->get_ability_names()) : 0;
									$my_site_hand_is_mod_enabled = in_array($my_site_hand_slug, $my_site_hand_enabled_mods, true);
									$my_site_hand_status_class = $my_site_hand_is_mod_enabled ? 'msh-module-status-dot--enabled' : '';
									?>
									<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-abilities')); ?>"
										class="msh-module-card msh-module-card--<?php echo esc_attr($my_site_hand_slug); ?>">
										<div class="msh-module-icon-container">
											<?php echo wp_kses($my_site_hand_module_icons[$my_site_hand_slug] ?? '', $my_site_hand_svg_allowed); ?>
										</div>
										<div class="msh-module-info">
											<h4 class="msh-module-name"><?php echo esc_html($my_site_hand_def['label']); ?>
											</h4>
											<span
									class="msh-module-count"><?php
									/* translators: %d: number of abilities in this module */
									printf( esc_html__( '%d abilities', 'my-site-hand' ), (int) $my_site_hand_ability_count );
									?></span>
										</div>
										<div class="msh-module-status-wrapper">
											<span
												class="msh-module-status-dot <?php echo esc_attr($my_site_hand_status_class); ?>"></span>
										</div>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>

				<!-- Connect AI Client -->
				<div class="msh-card" style="margin-bottom: 0;">
					<div class="msh-card-header" style="border-bottom: none; padding-bottom: 0;">
						<h2 style="font-size: 14px; font-weight: 600; margin: 0; color: var(--msh-secondary);">
							<?php esc_html_e('Connect AI client', 'my-site-hand'); ?>
						</h2>
					</div>
					<div
						style="font-size: 12px; color: var(--msh-text-muted); padding: 4px 24px 12px; margin-top: -4px;">
						<?php esc_html_e('Three steps · under a minute', 'my-site-hand'); ?>
					</div>
					<div class="msh-card-body" style="padding-top: 8px;">

						<!-- Token Input -->
						<div class="msh-form-group" style="margin-bottom: 20px;">
							<label for="msh-dash-token"
								style="font-size: 11px; font-weight: 700; margin-bottom: 8px; display: block; color: var(--msh-text-secondary); text-transform: uppercase; letter-spacing: 0.05em;"><?php esc_html_e('Your API Token', 'my-site-hand'); ?></label>
							<input type="password" id="msh-dash-token" class="msh-input"
								placeholder="msh_pk_••••••••••••" />
							<p class="msh-hint" style="margin-top: 8px; font-size: 12px;">
								<?php
								printf(
									/* translators: %s: link to generate a token */
									esc_html__('Don\'t have one? %s', 'my-site-hand'),
									'<a href="' . esc_url(admin_url('admin.php?page=my-site-hand-tokens')) . '" class="msh-link" style="color: var(--msh-primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">' . esc_html__('Generate one', 'my-site-hand') . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>'
								); ?>
							</p>
						</div>

						<!-- Client Tabs -->
						<div class="msh-tab-nav" style="margin-bottom: 20px;">
							<button type="button" id="msh-dash-client-tab-claude"
								class="msh-tab-btn msh-tab-btn--active" onclick="mshDash.switchClient('claude')">
								<?php esc_html_e('Claude Desktop', 'my-site-hand'); ?>
							</button>
							<button type="button" id="msh-dash-client-tab-cursor" class="msh-tab-btn"
								onclick="mshDash.switchClient('cursor')">
								<?php esc_html_e('Cursor / IDEs', 'my-site-hand'); ?>
							</button>
						</div>

						<!-- Claude Desktop Panel -->
						<div id="msh-dash-claude-panel">
							<div class="msh-os-tabs" style="margin-bottom: 16px;">
								<button type="button" id="msh-dash-os-tab-windows" class="msh-os-tab"
									onclick="mshDash.switchOs('windows')"><?php esc_html_e('Windows', 'my-site-hand'); ?></button>
								<button type="button" id="msh-dash-os-tab-mac" class="msh-os-tab"
									onclick="mshDash.switchOs('mac')"><?php esc_html_e('macOS', 'my-site-hand'); ?></button>
								<button type="button" id="msh-dash-os-tab-linux" class="msh-os-tab"
									onclick="mshDash.switchOs('linux')"><?php esc_html_e('Linux', 'my-site-hand'); ?></button>
							</div>

							<div class="msh-tab-content msh-tab-content--active">
								<div class="msh-connection-steps"
									style="margin-top: 0; margin-bottom: 16px; display: flex; flex-direction: column; gap: 12px;">
									<div class="msh-step">
										<div
											style="font-size: 11px; font-weight: 700; color: var(--msh-text-secondary); margin-bottom: 6px; display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.05em;">
											<span
												style="background: var(--msh-primary); color: #fff; width: 16px; height: 16px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 9px;">1</span>
											<?php esc_html_e('Install MCP-Remote', 'my-site-hand'); ?>
										</div>
										<div class="msh-dash-step-input-group">
											<input type="text" id="msh-dash-claude-step-1" class="msh-token-value"
												readonly value="npm install -g mcp-remote" />
											<button type="button" class="msh-copy-btn"
												onclick="msh.copyText('msh-dash-claude-step-1')">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none"
													stroke="currentColor" stroke-width="2" stroke-linecap="round"
													stroke-linejoin="round">
													<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
													<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1">
													</path>
												</svg>
												<?php esc_html_e('Copy', 'my-site-hand'); ?>
											</button>
										</div>
									</div>

									<div class="msh-step">
										<div
											style="font-size: 11px; font-weight: 700; color: var(--msh-text-secondary); margin-bottom: 6px; display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.05em;">
											<span
												style="background: var(--msh-primary); color: #fff; width: 16px; height: 16px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 9px;">2</span>
											<?php esc_html_e('Connect to Claude Desktop', 'my-site-hand'); ?>
										</div>
										<div class="msh-dash-step-input-group">
											<input type="text" id="msh-dash-claude-step-2" class="msh-token-value"
												readonly
												placeholder="<?php esc_attr_e('Paste token first..', 'my-site-hand'); ?>" />
											<button type="button" class="msh-copy-btn"
												onclick="msh.copyText('msh-dash-claude-step-2')">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none"
													stroke="currentColor" stroke-width="2" stroke-linecap="round"
													stroke-linejoin="round">
													<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
													<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1">
													</path>
												</svg>
												<?php esc_html_e('Copy', 'my-site-hand'); ?>
											</button>
										</div>
									</div>
								</div>

								<p
									style="margin: 0 0 16px; font-size: 11px; font-style: italic; color: var(--msh-text-muted); line-height: 1.4;">
									<?php esc_html_e('Run Step 1 first, wait for it to finish, then run Step 2.', 'my-site-hand'); ?>
								</p>
							</div>

							<div class="msh-node-note">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
									stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<circle cx="12" cy="12" r="10"></circle>
									<line x1="12" y1="16" x2="12" y2="12"></line>
									<line x1="12" y1="8" x2="12.01" y2="8"></line>
								</svg>
								<span><?php
								printf(
									/* translators: %s: link to nodejs.org */
									esc_html__('No Node.js? Download LTS from %s first.', 'my-site-hand'),
									'<a href="https://nodejs.org/" target="_blank" rel="noopener">nodejs.org</a>'
								); ?></span>
							</div>
						</div>

						<!-- Cursor Panel -->
						<div id="msh-dash-cursor-panel" style="display:none;">
							<div class="msh-form-group" style="margin-bottom: 12px;">
								<label
									style="font-size: 11px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--msh-text-secondary);"><?php esc_html_e('MCP Server URL', 'my-site-hand'); ?></label>
								<div class="msh-dash-step-input-group">
									<input type="text" id="msh-dash-cursor-url" class="msh-token-value" readonly
										value="<?php echo esc_url($my_site_hand_mcp_endpoint); ?>" />
									<button type="button" class="msh-copy-btn"
										onclick="msh.copyText('msh-dash-cursor-url')">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none"
											stroke="currentColor" stroke-width="2" stroke-linecap="round"
											stroke-linejoin="round">
											<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
											<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
										</svg>
										<?php esc_html_e('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<div class="msh-form-group" style="margin-bottom: 12px;">
								<label
									style="font-size: 11px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--msh-text-secondary);"><?php esc_html_e('Type', 'my-site-hand'); ?></label>
								<div class="msh-dash-step-input-group">
									<input type="text" id="msh-dash-cursor-type" class="msh-token-value" readonly
										value="http" />
									<button type="button" class="msh-copy-btn"
										onclick="msh.copyText('msh-dash-cursor-type')">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none"
											stroke="currentColor" stroke-width="2" stroke-linecap="round"
											stroke-linejoin="round">
											<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
											<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
										</svg>
										<?php esc_html_e('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<div class="msh-form-group" style="margin-bottom: 12px;">
								<label
									style="font-size: 11px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--msh-text-secondary);"><?php esc_html_e('Authorization Header', 'my-site-hand'); ?></label>
								<div class="msh-dash-step-input-group">
									<input type="text" id="msh-dash-cursor-auth" class="msh-token-value" readonly
										placeholder="Bearer YOUR_TOKEN" />
									<button type="button" class="msh-copy-btn"
										onclick="msh.copyText('msh-dash-cursor-auth')">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none"
											stroke="currentColor" stroke-width="2" stroke-linecap="round"
											stroke-linejoin="round">
											<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
											<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
										</svg>
										<?php esc_html_e('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<p class="msh-hint"
								style="margin-top: 16px; font-size: 12px; line-height: 1.4; color: var(--msh-text-secondary);">
								<?php esc_html_e('In Cursor: Settings → Features → MCP Servers → Add new MCP server → set Type to HTTP, paste the URL and Authorization header.', 'my-site-hand'); ?>
							</p>
						</div>

					</div>
				</div>


			</div>

			<!-- Bottom Alert/Notice Banner -->
			<div class="msh-bottom-alert" id="msh-dashboard-bottom-alert">
				<div class="msh-bottom-alert-left">
					<div class="msh-bottom-alert-icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="10"></circle>
							<line x1="12" y1="16" x2="12" y2="12"></line>
							<line x1="12" y1="8" x2="12.01" y2="8"></line>
						</svg>
					</div>
					<div class="msh-bottom-alert-content">
						<h4 class="msh-bottom-alert-title">
							<?php esc_html_e('For site administrators', 'my-site-hand'); ?></h4>
						<p class="msh-bottom-alert-desc">
							<?php
							printf(
								/* translators: %s: link to abilities tab */
								esc_html__('My Site Hand exposes only the abilities you explicitly enable in the %s tab. Review them before sharing tokens with AI clients.', 'my-site-hand'),
								'<a href="' . esc_url(admin_url('admin.php?page=my-site-hand-abilities')) . '" class="msh-alert-link">' . esc_html__('Abilities', 'my-site-hand') . '</a>'
							);
							?>
						</p>
					</div>
				</div>
				<button type="button" class="msh-bottom-alert-close"
					onclick="document.getElementById('msh-dashboard-bottom-alert').style.display='none'">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
						stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>
		</div>


	</div>

</div>

<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>