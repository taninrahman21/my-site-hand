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
$my_site_hand_recent_logs = $my_site_hand_audit->get_logs(['per_page' => 6, 'page' => 1]);
$my_site_hand_mcp_endpoint = rest_url('my-site-hand/v1/mcp/streamable');
$my_site_hand_is_enabled = (bool) get_option('mysitehand_enabled', true);
$my_site_hand_modules = $my_site_hand_plugin->get_modules();
$my_site_hand_enabled_mods = $my_site_hand_plugin->get_enabled_modules();
$my_site_hand_disabled_abs = (array) get_option('mysitehand_disabled_abilities', []);

// Repairs made by hand from the Site Health report are recorded alongside AI
// calls, but they are not registered abilities, so they need their own label.
$my_site_hand_pseudo_abilities = [
	'my-site-hand/quick-fix' => __('Quick Fix', 'my-site-hand'),
];

// Site Health — the one thing on this page that needs no setup at all.
$my_site_hand_health_scanner = $my_site_hand_plugin->get_site_health_scanner();
$my_site_hand_health_report = $my_site_hand_health_scanner->get_latest_report();
$my_site_hand_health_url = admin_url('admin.php?page=my-site-hand-health');
$my_site_hand_health_history = $my_site_hand_health_scanner->get_history(8);

// Dynamic calculations for the figure strip.
$my_site_hand_calls_today = (int) $my_site_hand_stats['calls_today'];
$my_site_hand_calls_yesterday = (int) $my_site_hand_stats['calls_yesterday'];
$my_site_hand_diff = $my_site_hand_calls_today - $my_site_hand_calls_yesterday;
$my_site_hand_pct_change = $my_site_hand_calls_yesterday > 0 ? round(($my_site_hand_diff / $my_site_hand_calls_yesterday) * 100) : 0;
$my_site_hand_trend_up = $my_site_hand_diff >= 0;

$my_site_hand_expiring_30d = $my_site_hand_auth->get_expiring_count(30);
$my_site_hand_public_count = count($my_site_hand_registry->get_mcp_public());
$my_site_hand_total_abilities = count($my_site_hand_abilities);
$my_site_hand_abilities_on = $my_site_hand_total_abilities - count(array_intersect(array_keys($my_site_hand_abilities), $my_site_hand_disabled_abs));
$my_site_hand_abilities_off = $my_site_hand_total_abilities - $my_site_hand_abilities_on;

// Things that need the admin's attention.
$my_site_hand_attention = [];
if (!is_ssl()) {
	$my_site_hand_attention[] = [
		'title' => __('No HTTPS on this site', 'my-site-hand'),
		'body'  => __('Every token you issue travels in plain text.', 'my-site-hand'),
		'link'  => admin_url('admin.php?page=my-site-hand-tools'),
		'cta'   => __('Run the checks', 'my-site-hand'),
		'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
	];
}
if ($my_site_hand_expiring_30d > 0) {
	$my_site_hand_attention[] = [
		'title' => sprintf(
			/* translators: %d: number of tokens expiring within 30 days */
			_n('%d token expires within 30 days', '%d tokens expire within 30 days', (int) $my_site_hand_expiring_30d, 'my-site-hand'),
			(int) $my_site_hand_expiring_30d
		),
		'body'  => __('Clients using them will stop working the moment they lapse.', 'my-site-hand'),
		'link'  => admin_url('admin.php?page=my-site-hand-tokens'),
		'cta'   => __('Rotate them now', 'my-site-hand'),
		'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><rect x="3" y="11" width="18" height="11"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
	];
}
if (!$my_site_hand_is_enabled) {
	$my_site_hand_attention[] = [
		'title' => __('The MCP endpoint is switched off', 'my-site-hand'),
		'body'  => __('No agent can reach this site until you turn it back on.', 'my-site-hand'),
		'link'  => admin_url('admin.php?page=my-site-hand-settings'),
		'cta'   => __('Open settings', 'my-site-hand'),
		'icon'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
	];
}

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
	'ellipse' => [
		'cx' => [],
		'cy' => [],
		'rx' => [],
		'ry' => [],
	],
	'polygon' => ['points' => []],
];

$my_site_hand_module_defs = [
	'content' => ['label' => __('Content', 'my-site-hand'), 'desc' => __('Posts, pages, custom post types', 'my-site-hand')],
	'seo' => ['label' => __('SEO', 'my-site-hand'), 'desc' => __('Analysis and meta management', 'my-site-hand')],
	'diagnostics' => ['label' => __('Diagnostics', 'my-site-hand'), 'desc' => __('Site health, error logs, cron', 'my-site-hand')],
	'media' => ['label' => __('Media', 'my-site-hand'), 'desc' => __('Media library', 'my-site-hand')],
	'users' => ['label' => __('Users', 'my-site-hand'), 'desc' => __('Accounts and roles', 'my-site-hand')],
	'woocommerce' => ['label' => __('WooCommerce', 'my-site-hand'), 'desc' => __('Products, orders, coupons', 'my-site-hand')],
];

$my_site_hand_module_icons = [
	'content' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
	'seo' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line></svg>',
	'woocommerce' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
	'diagnostics' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
	'media' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="3" width="18" height="18"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle></svg>',
	'users' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>',
];

$my_site_hand_copy_icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><rect x="9" y="9" width="13" height="13"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
?>
<div class="msh-wrap">

	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-body-grid">

				<!-- On this page -->
				<nav class="msh-index" aria-label="<?php esc_attr_e('On this page', 'my-site-hand'); ?>">
					<p class="msh-index-label"><?php esc_html_e('On this page', 'my-site-hand'); ?></p>
					<a class="msh-index-link msh-index-link--active" href="#msh-dash-today"><span class="msh-index-no">01</span><?php esc_html_e('Today', 'my-site-hand'); ?></a>
					<?php if (!empty($my_site_hand_attention)): ?>
						<a class="msh-index-link" href="#msh-dash-attention"><span class="msh-index-no">02</span><?php esc_html_e('Needs you', 'my-site-hand'); ?><span class="msh-index-ct"><?php echo esc_html(count($my_site_hand_attention)); ?></span></a>
					<?php endif; ?>
					<a class="msh-index-link" href="#msh-dash-stream"><span class="msh-index-no">03</span><?php esc_html_e('Stream', 'my-site-hand'); ?></a>
					<a class="msh-index-link" href="#msh-dash-surface"><span class="msh-index-no">04</span><?php esc_html_e('Surface', 'my-site-hand'); ?><span class="msh-index-ct"><?php echo esc_html(count($my_site_hand_enabled_mods) . '/' . count($my_site_hand_module_defs)); ?></span></a>
					<a class="msh-index-link" href="#msh-dash-connect"><span class="msh-index-no">05</span><?php esc_html_e('Connect', 'my-site-hand'); ?></a>
					<div class="msh-index-foot">
						<p><?php
						printf(
							/* translators: %d: number of active tokens */
							esc_html(_n('%d token issued.', '%d tokens issued.', count($my_site_hand_tokens), 'my-site-hand')),
							(int) count($my_site_hand_tokens)
						); ?></p>
						<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-tokens')); ?>"><?php esc_html_e('Review tokens', 'my-site-hand'); ?></a>
					</div>
				</nav>

				<div class="msh-body-main">

					<!-- Page head -->
					<div class="msh-page-head">
						<div class="msh-page-head-info">
							<p class="msh-eyebrow"><i></i><?php esc_html_e('Overview', 'my-site-hand'); ?></p>
							<h2><?php esc_html_e('Today', 'my-site-hand'); ?></h2>
							<p class="msh-page-desc"><?php
							printf(
								/* translators: 1: number of calls today, 2: number of active tokens, 3: number of errors in the last 24 hours */
								esc_html__('%1$s calls from %2$s. %3$s failed in the last 24 hours.', 'my-site-hand'),
								esc_html(number_format_i18n($my_site_hand_calls_today)),
								esc_html(sprintf(
									/* translators: %d: number of active tokens */
									_n('%d token', '%d tokens', count($my_site_hand_tokens), 'my-site-hand'),
									count($my_site_hand_tokens)
								)),
								esc_html(number_format_i18n((int) $my_site_hand_stats['errors_24h']))
							); ?></p>
						</div>
						<div class="msh-page-head-actions">
							<button type="button" class="msh-btn" onclick="window.location.reload()"><?php esc_html_e('Refresh', 'my-site-hand'); ?></button>
							<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-tokens')); ?>" class="msh-btn msh-btn--primary"><?php esc_html_e('New token', 'my-site-hand'); ?></a>
						</div>
					</div>

					<!-- Site Health score -->
					<?php if (null === $my_site_hand_health_report): ?>
						<div class="msh-dash-health">
							<div class="msh-dash-health-text">
								<p class="msh-dash-health-title"><?php esc_html_e('You have never scanned this site', 'my-site-hand'); ?></p>
								<p class="msh-dash-health-body">
									<?php esc_html_e('Find broken links, missing SEO data, and health issues in about 30 seconds. No setup required.', 'my-site-hand'); ?>
								</p>
							</div>
							<a href="<?php echo esc_url($my_site_hand_health_url); ?>" class="msh-btn msh-btn--primary">
								<?php esc_html_e('Scan My Site', 'my-site-hand'); ?>
							</a>
						</div>
					<?php else:
						$my_site_hand_health_score = (int) $my_site_hand_health_report['score'];
						$my_site_hand_health_band = $my_site_hand_health_scanner->get_score_band($my_site_hand_health_score);
						?>
						<div class="msh-dash-health">
							<div class="msh-health-scorebox msh-health-scorebox--<?php echo esc_attr($my_site_hand_health_band['band']); ?>">
								<span class="msh-health-scorenum"><?php echo esc_html((string) $my_site_hand_health_score); ?></span>
								<span class="msh-health-scoreof"><?php esc_html_e('/ 100', 'my-site-hand'); ?></span>
							</div>
							<div class="msh-dash-health-text">
								<p class="msh-dash-health-title">
									<?php
									printf(
										/* translators: %s: score band, e.g. "Good" */
										esc_html__('Site health: %s', 'my-site-hand'),
										esc_html($my_site_hand_health_band['label'])
									);
									?>
								</p>
								<p class="msh-dash-health-body">
									<?php
									printf(
										/* translators: %s: human readable time difference, e.g. "5 minutes" */
										esc_html__('Last scanned %s ago', 'my-site-hand'),
										esc_html(human_time_diff((int) $my_site_hand_health_report['generated_at'], time()))
									);
									?>
								</p>

								<?php
								$my_site_hand_trend = $my_site_hand_health_history;
								require MYSITEHAND_PATH . 'templates/partials/health-trend.php';
								?>
							</div>
							<a href="<?php echo esc_url($my_site_hand_health_url); ?>" class="msh-btn">
								<?php esc_html_e('View report', 'my-site-hand'); ?>
							</a>
						</div>
					<?php endif; ?>

					<!-- Endpoint strip -->
					<div class="msh-top-status">
						<div class="msh-status-indicator">
							<span class="msh-status-dot"></span>
							<strong><?php echo $my_site_hand_is_enabled ? esc_html__('Server active', 'my-site-hand') : esc_html__('Server stopped', 'my-site-hand'); ?></strong>
						</div>
						<div class="msh-status-center">
							<code class="msh-endpoint-code" id="msh-mcp-url-top"><?php echo esc_html($my_site_hand_mcp_endpoint); ?></code>
							<button type="button" class="msh-copy-btn msh-copy-btn--inline"
								onclick="msh.copyText('msh-mcp-url-top')">
								<?php echo wp_kses($my_site_hand_copy_icon, $my_site_hand_svg_allowed); ?>
								<?php esc_html_e('Copy', 'my-site-hand'); ?>
							</button>
						</div>
						<div class="msh-status-protocol"><?php esc_html_e('Protocol 2024-11-05', 'my-site-hand'); ?></div>
					</div>

					<!-- 01 Today -->
					<div class="msh-stats-grid" id="msh-dash-today">
						<div class="msh-stat-card msh-stat--calls">
							<span class="msh-stat-label"><?php esc_html_e('Calls today', 'my-site-hand'); ?></span>
							<span class="msh-stat-value" id="stat-calls-today"><?php echo esc_html(number_format_i18n($my_site_hand_calls_today)); ?></span>
							<span class="msh-stat-meta msh-meta--<?php echo esc_attr($my_site_hand_trend_up ? 'up' : 'down'); ?>">
								<span class="msh-meta-icon"><?php echo esc_html(0 === $my_site_hand_pct_change ? '~' : ($my_site_hand_trend_up ? '↑' : '↓')); ?></span>
								<?php
								printf(
									/* translators: %d: percentage change in API calls */
									esc_html__('%d%% against yesterday', 'my-site-hand'),
									(int) abs($my_site_hand_pct_change)
								); ?>
							</span>
						</div>
						<div class="msh-stat-card msh-stat--abilities">
							<span class="msh-stat-label"><?php esc_html_e('Abilities on', 'my-site-hand'); ?></span>
							<span class="msh-stat-value" id="stat-abilities"><?php echo esc_html($my_site_hand_abilities_on); ?><small>&thinsp;/&thinsp;<?php echo esc_html($my_site_hand_total_abilities); ?></small></span>
							<span class="msh-stat-meta"><?php
							printf(
								/* translators: %d: number of abilities switched off */
								esc_html__('%d held back by you', 'my-site-hand'),
								(int) $my_site_hand_abilities_off
							); ?></span>
						</div>
						<div class="msh-stat-card msh-stat--tokens">
							<span class="msh-stat-label"><?php esc_html_e('Average duration', 'my-site-hand'); ?></span>
							<span class="msh-stat-value" id="stat-tokens"><?php echo esc_html(number_format_i18n((float) $my_site_hand_stats['avg_duration'], 0)); ?><small>&thinsp;ms</small></span>
							<span class="msh-stat-meta"><?php
							printf(
								/* translators: %d: number of active tokens */
								esc_html__('Across %d active tokens', 'my-site-hand'),
								(int) count($my_site_hand_tokens)
							); ?></span>
						</div>
						<div class="msh-stat-card msh-stat--errors">
							<span class="msh-stat-label"><?php esc_html_e('Failures', 'my-site-hand'); ?></span>
							<span class="msh-stat-value" id="stat-errors"><?php echo esc_html(number_format_i18n((int) $my_site_hand_stats['errors_24h'])); ?></span>
							<span class="msh-stat-meta"><?php
							printf(
								/* translators: %s: error rate percentage */
								esc_html__('%s%% error rate this month', 'my-site-hand'),
								esc_html(number_format_i18n((float) $my_site_hand_stats['error_rate'], 2))
							); ?></span>
						</div>
					</div>

					<!-- 02 Needs you -->
					<?php if (!empty($my_site_hand_attention)): ?>
						<div id="msh-dash-attention" style="display: grid; grid-template-columns: repeat(<?php echo esc_attr(min(2, count($my_site_hand_attention))); ?>, minmax(0, 1fr)); border: 1px solid var(--msh-al); background: var(--msh-aw);">
							<?php foreach ($my_site_hand_attention as $my_site_hand_i => $my_site_hand_item): ?>
								<div style="display: flex; gap: 13px; padding: 16px 20px; <?php echo $my_site_hand_i > 0 ? 'border-left: 1px solid var(--msh-al);' : ''; ?>">
									<span class="msh-bottom-alert-icon"><?php echo wp_kses($my_site_hand_item['icon'], $my_site_hand_svg_allowed); ?></span>
									<div>
										<strong class="msh-bottom-alert-title"><?php echo esc_html($my_site_hand_item['title']); ?></strong>
										<p class="msh-bottom-alert-desc"><?php echo esc_html($my_site_hand_item['body']); ?>
											<a href="<?php echo esc_url($my_site_hand_item['link']); ?>" style="color: var(--msh-a); font-weight: 600; text-decoration: underline; text-underline-offset: 2px;"><?php echo esc_html($my_site_hand_item['cta']); ?></a>
										</p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<!-- 03 Request stream -->
					<div class="msh-card" id="msh-dash-stream">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">03</span>
								<div>
									<h3><?php esc_html_e('Request stream', 'my-site-hand'); ?></h3>
									<p><?php esc_html_e('The most recent calls, newest first.', 'my-site-hand'); ?></p>
								</div>
							</div>
							<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-audit')); ?>"><?php esc_html_e('Full audit →', 'my-site-hand'); ?></a>
						</div>
						<div class="msh-card-body msh-card--no-pad">
							<?php if (empty($my_site_hand_recent_logs['logs'])): ?>
								<div class="msh-recent-activity-empty">
									<div class="msh-recent-activity-empty-icon">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
									</div>
									<h3 class="msh-tokens-empty-heading"><?php esc_html_e('Waiting for activity', 'my-site-hand'); ?></h3>
									<p class="msh-tokens-empty-desc"><?php esc_html_e('Calls appear here the moment your AI client makes its first request.', 'my-site-hand'); ?></p>
								</div>
							<?php else: ?>
								<div class="msh-table-wrap">
									<table class="msh-table">
										<thead>
											<tr>
												<th><?php esc_html_e('Time', 'my-site-hand'); ?></th>
												<th><?php esc_html_e('Ability', 'my-site-hand'); ?></th>
												<th><?php esc_html_e('Client', 'my-site-hand'); ?></th>
												<th><?php esc_html_e('Result', 'my-site-hand'); ?></th>
												<th style="text-align: right;"><?php esc_html_e('Duration', 'my-site-hand'); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($my_site_hand_recent_logs['logs'] as $my_site_hand_log):
												// Not every entry comes from a token: Quick Fix repairs are
												// performed by a signed-in human and carry no token_id.
												$my_site_hand_token_data = !empty($my_site_hand_log['token_id'])
													? $my_site_hand_auth->get_token((int) $my_site_hand_log['token_id'])
													: null;
												if ($my_site_hand_token_data) {
													$my_site_hand_token_name = $my_site_hand_token_data['label'] ?? '—';
												} elseif (!empty($my_site_hand_log['user_id'])) {
													$my_site_hand_log_user = get_userdata((int) $my_site_hand_log['user_id']);
													$my_site_hand_token_name = $my_site_hand_log_user
														? $my_site_hand_log_user->display_name
														: '—';
												} else {
													$my_site_hand_token_name = '—';
												}
												$my_site_hand_status = $my_site_hand_log['result_status'];
												?>
												<tr>
													<td class="msh-table-num" style="text-align: left;"><?php echo esc_html(wp_date('H:i:s', strtotime($my_site_hand_log['executed_at']))); ?></td>
													<td style="font-weight: 600;"><?php echo esc_html($my_site_hand_abilities[$my_site_hand_log['ability_name']]['label'] ?? ($my_site_hand_pseudo_abilities[$my_site_hand_log['ability_name']] ?? $my_site_hand_log['ability_name'])); ?></td>
													<td class="msh-table-dim"><?php echo esc_html($my_site_hand_token_name); ?></td>
													<td>
														<span class="msh-badge msh-badge--<?php echo esc_attr($my_site_hand_status); ?>"><?php
														if ('success' === $my_site_hand_status) {
															esc_html_e('Done', 'my-site-hand');
														} elseif ('error' === $my_site_hand_status) {
															esc_html_e('Failed 500', 'my-site-hand');
														} elseif ('rate_limited' === $my_site_hand_status) {
															esc_html_e('Throttled 429', 'my-site-hand');
														} else {
															echo esc_html($my_site_hand_status);
														}
														?></span>
													</td>
													<td class="msh-table-num" style="text-align: right;"><?php
													echo null !== $my_site_hand_log['duration_ms']
														? esc_html(
															sprintf(
																/* translators: %d: execution duration in milliseconds */
																__('%d ms', 'my-site-hand'),
																(int) $my_site_hand_log['duration_ms']
															)
														)
														: esc_html('—');
													?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<!-- 04 Permission surface -->
					<div class="msh-card" id="msh-dash-surface">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">04</span>
								<div>
									<h3><?php esc_html_e('Permission surface', 'my-site-hand'); ?></h3>
									<p><?php esc_html_e('How much of each module is reachable right now.', 'my-site-hand'); ?></p>
								</div>
							</div>
							<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-abilities')); ?>"><?php esc_html_e('Change →', 'my-site-hand'); ?></a>
						</div>
						<div class="msh-modules-grid">
							<?php foreach ($my_site_hand_module_defs as $my_site_hand_slug => $my_site_hand_def):
								$my_site_hand_module_obj = $my_site_hand_modules[$my_site_hand_slug] ?? null;
								$my_site_hand_names = $my_site_hand_module_obj ? $my_site_hand_module_obj->get_ability_names() : [];
								$my_site_hand_mod_total = count($my_site_hand_names);
								$my_site_hand_mod_on = count(
									array_filter(
										$my_site_hand_names,
										static function ($my_site_hand_n) use ($my_site_hand_disabled_abs) {
											return !in_array($my_site_hand_n, $my_site_hand_disabled_abs, true);
										}
									)
								);
								$my_site_hand_is_mod_enabled = in_array($my_site_hand_slug, $my_site_hand_enabled_mods, true);
								$my_site_hand_pct = $my_site_hand_mod_total > 0 ? round(($my_site_hand_mod_on / $my_site_hand_mod_total) * 100) : 0;
								?>
								<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-abilities')); ?>"
									class="msh-module-card msh-module-card--<?php echo esc_attr($my_site_hand_slug); ?>"
									style="opacity: <?php echo esc_attr($my_site_hand_is_mod_enabled ? '1' : '0.55'); ?>;">
									<span class="msh-module-icon-container"><?php echo wp_kses($my_site_hand_module_icons[$my_site_hand_slug] ?? '', $my_site_hand_svg_allowed); ?></span>
									<span class="msh-settings-module-info">
										<span class="msh-module-name"><?php echo esc_html($my_site_hand_def['label']); ?></span>
										<span class="msh-module-count"><?php
										echo $my_site_hand_is_mod_enabled
											? esc_html($my_site_hand_def['desc'])
											: esc_html__('Switched off in settings', 'my-site-hand');
										?></span>
									</span>
									<span class="msh-module-track"><i style="width: <?php echo esc_attr($my_site_hand_is_mod_enabled ? $my_site_hand_pct : 0); ?>%"></i></span>
									<span class="msh-module-ratio"><?php
									echo $my_site_hand_mod_total > 0
										? esc_html($my_site_hand_mod_on . '/' . $my_site_hand_mod_total)
										: esc_html('—');
									?></span>
									<span class="msh-module-status-wrapper">
										<span class="msh-badge <?php echo $my_site_hand_is_mod_enabled ? 'msh-badge--success' : ''; ?>"><?php
										echo $my_site_hand_is_mod_enabled ? esc_html__('On', 'my-site-hand') : esc_html__('Off', 'my-site-hand');
										?></span>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- 05 Connect a client -->
					<!-- 05 Connect — collapsed on purpose. This is the optional
					     upgrade, not a setup step: the terminal instructions inside
					     are what non-technical users bounce off. -->
					<details class="msh-card msh-card--optional" id="msh-dash-connect">
						<summary class="msh-card-optional-summary">
							<svg class="msh-card-optional-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
							<span class="msh-card-optional-label"><?php esc_html_e('Connect an AI assistant (Claude Desktop, Cursor)', 'my-site-hand'); ?></span>
							<span class="msh-card-optional-tag"><?php esc_html_e('optional', 'my-site-hand'); ?></span>
						</summary>
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">05</span>
								<div>
									<h3><?php esc_html_e('Connect a client', 'my-site-hand'); ?></h3>
									<p><?php esc_html_e('Paste a token, run two commands, restart. Under a minute.', 'my-site-hand'); ?></p>
								</div>
							</div>
							<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-tokens')); ?>"><?php esc_html_e('Make a token →', 'my-site-hand'); ?></a>
						</div>

						<div class="msh-card-body--pad">
							<div class="msh-form-group">
								<label class="msh-field-label" for="msh-dash-token"><?php esc_html_e('01 — Your API token', 'my-site-hand'); ?></label>
								<input type="password" id="msh-dash-token" class="msh-input" style="max-width: 440px;"
									placeholder="msh_pk_••••••••••••" />
								<p class="msh-hint"><?php esc_html_e('Shown once, at creation. Nothing you paste here is stored or sent anywhere.', 'my-site-hand'); ?></p>
							</div>

							<div class="msh-form-group">
								<label class="msh-field-label"><?php esc_html_e('02 — Pick your client', 'my-site-hand'); ?></label>
								<div class="msh-tab-nav">
									<button type="button" id="msh-dash-client-tab-claude" class="msh-tab-btn msh-tab-btn--active"
										onclick="mshDash.switchClient('claude')"><?php esc_html_e('Claude Desktop', 'my-site-hand'); ?></button>
									<button type="button" id="msh-dash-client-tab-cursor" class="msh-tab-btn"
										onclick="mshDash.switchClient('cursor')"><?php esc_html_e('Cursor / IDEs', 'my-site-hand'); ?></button>
								</div>
							</div>
						</div>

						<!-- Claude Desktop -->
						<div id="msh-dash-claude-panel">
							<div class="msh-setting-item" style="grid-template-columns: minmax(0, 1fr);">
								<div>
									<label class="msh-field-label"><?php esc_html_e('03 — Operating system', 'my-site-hand'); ?></label>
									<div class="msh-os-tabs" style="width: fit-content;">
										<button type="button" id="msh-dash-os-tab-windows" class="msh-os-tab"
											onclick="mshDash.switchOs('windows')"><?php esc_html_e('Windows', 'my-site-hand'); ?></button>
										<button type="button" id="msh-dash-os-tab-mac" class="msh-os-tab"
											onclick="mshDash.switchOs('mac')"><?php esc_html_e('macOS', 'my-site-hand'); ?></button>
										<button type="button" id="msh-dash-os-tab-linux" class="msh-os-tab"
											onclick="mshDash.switchOs('linux')"><?php esc_html_e('Linux', 'my-site-hand'); ?></button>
									</div>
								</div>
							</div>

							<div class="msh-connect-grid" style="border-top: 1px solid var(--msh-n2);">
								<div class="msh-connect-cell" style="grid-column: span 3;">
									<div class="msh-connection-steps">
										<div class="msh-step">
											<div class="msh-step-label">
												<span class="msh-step-num">1</span><?php esc_html_e('Install mcp-remote', 'my-site-hand'); ?>
											</div>
											<div class="msh-dash-step-input-group">
												<input type="text" id="msh-dash-claude-step-1" class="msh-token-value"
													readonly value="npm install -g mcp-remote" />
												<button type="button" class="msh-copy-btn"
													onclick="msh.copyText('msh-dash-claude-step-1')">
													<?php echo wp_kses($my_site_hand_copy_icon, $my_site_hand_svg_allowed); ?>
													<?php esc_html_e('Copy', 'my-site-hand'); ?>
												</button>
											</div>
										</div>

										<div class="msh-step">
											<div class="msh-step-label">
												<span class="msh-step-num">2</span><?php esc_html_e('Register this site with Claude Desktop', 'my-site-hand'); ?>
											</div>
											<div class="msh-dash-step-input-group">
												<input type="text" id="msh-dash-claude-step-2" class="msh-token-value"
													readonly
													placeholder="<?php esc_attr_e('Paste your token above first', 'my-site-hand'); ?>" />
												<button type="button" class="msh-copy-btn"
													onclick="msh.copyText('msh-dash-claude-step-2')">
													<?php echo wp_kses($my_site_hand_copy_icon, $my_site_hand_svg_allowed); ?>
													<?php esc_html_e('Copy', 'my-site-hand'); ?>
												</button>
											</div>
										</div>
									</div>

									<div class="msh-node-note">
										<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
										<span><?php esc_html_e('Run step 1 and wait for it to finish before running step 2, then restart Claude Desktop.', 'my-site-hand'); ?>
											<?php
											printf(
												/* translators: %s: link to nodejs.org */
												esc_html__('No Node.js? Download LTS from %s first.', 'my-site-hand'),
												'<a href="https://nodejs.org/" target="_blank" rel="noopener">nodejs.org</a>'
											); ?></span>
									</div>
								</div>
							</div>
						</div>

						<!-- Cursor / IDEs -->
						<div id="msh-dash-cursor-panel" style="display: none;">
							<div class="msh-connect-grid" style="border-top: 1px solid var(--msh-n2);">
								<div class="msh-connect-cell">
									<label class="msh-field-label"><?php esc_html_e('Server URL', 'my-site-hand'); ?></label>
									<div class="msh-dash-step-input-group">
										<input type="text" id="msh-dash-cursor-url" class="msh-token-value" readonly
											value="<?php echo esc_url($my_site_hand_mcp_endpoint); ?>" />
										<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-dash-cursor-url')">
											<?php esc_html_e('Copy', 'my-site-hand'); ?>
										</button>
									</div>
								</div>
								<div class="msh-connect-cell">
									<label class="msh-field-label"><?php esc_html_e('Type', 'my-site-hand'); ?></label>
									<div class="msh-dash-step-input-group">
										<input type="text" id="msh-dash-cursor-type" class="msh-token-value" readonly value="http" />
										<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-dash-cursor-type')">
											<?php esc_html_e('Copy', 'my-site-hand'); ?>
										</button>
									</div>
								</div>
								<div class="msh-connect-cell">
									<label class="msh-field-label"><?php esc_html_e('Authorization header', 'my-site-hand'); ?></label>
									<div class="msh-dash-step-input-group">
										<input type="text" id="msh-dash-cursor-auth" class="msh-token-value" readonly
											placeholder="Bearer YOUR_TOKEN" />
										<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-dash-cursor-auth')">
											<?php esc_html_e('Copy', 'my-site-hand'); ?>
										</button>
									</div>
								</div>
							</div>
							<div class="msh-setting-item" style="grid-template-columns: minmax(0, 1fr);">
								<p class="msh-hint" style="margin: 0;"><?php esc_html_e('In Cursor: Settings → Features → MCP Servers → Add new MCP server. Set Type to HTTP, then paste the URL and the Authorization header.', 'my-site-hand'); ?></p>
							</div>
						</div>
					</details>

					<!-- Footnote -->
					<div class="msh-bottom-alert" id="msh-dashboard-bottom-alert">
						<div class="msh-bottom-alert-left">
							<div class="msh-bottom-alert-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
							</div>
							<div class="msh-bottom-alert-content">
								<h4 class="msh-bottom-alert-title"><?php esc_html_e('For site administrators', 'my-site-hand'); ?></h4>
								<p class="msh-bottom-alert-desc"><?php
								printf(
									/* translators: %s: link to abilities tab */
									esc_html__('My Site Hand exposes only the abilities you explicitly enable in the %s tab. Review them before sharing tokens with AI clients.', 'my-site-hand'),
									'<a href="' . esc_url(admin_url('admin.php?page=my-site-hand-abilities')) . '" class="msh-alert-link">' . esc_html__('Abilities', 'my-site-hand') . '</a>'
								); ?></p>
							</div>
						</div>
						<button type="button" class="msh-bottom-alert-close"
							onclick="document.getElementById('msh-dashboard-bottom-alert').style.display='none'"
							aria-label="<?php esc_attr_e('Dismiss', 'my-site-hand'); ?>">
							<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
						</button>
					</div>

				</div>
			</div>
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
