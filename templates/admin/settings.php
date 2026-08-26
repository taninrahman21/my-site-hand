<?php
/**
 * Settings page template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_plugin = \MySiteHand\Plugin::get_instance();
$my_site_hand_enabled_mods = $my_site_hand_plugin->get_enabled_modules();
$my_site_hand_module_objs = $my_site_hand_plugin->get_modules();
$my_site_hand_disabled_abs = (array) get_option('mysitehand_disabled_abilities', []);

$my_site_hand_all_modules = [
	'content' => ['label' => __('Content', 'my-site-hand'), 'desc' => __('Posts, pages, custom post types', 'my-site-hand'), 'count' => 9],
	'seo' => ['label' => __('SEO', 'my-site-hand'), 'desc' => __('Analysis and meta management', 'my-site-hand'), 'count' => 6],
	'diagnostics' => ['label' => __('Diagnostics', 'my-site-hand'), 'desc' => __('Site health, error logs, cron', 'my-site-hand'), 'count' => 7],
	'media' => ['label' => __('Media', 'my-site-hand'), 'desc' => __('Media library', 'my-site-hand'), 'count' => 6],
	'users' => ['label' => __('Users', 'my-site-hand'), 'desc' => __('Accounts and roles', 'my-site-hand'), 'count' => 5],
	'woocommerce' => ['label' => __('WooCommerce', 'my-site-hand'), 'desc' => __('Products, orders, coupons', 'my-site-hand'), 'count' => 12],
];

$my_site_hand_module_icons = [
	'content' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
	'seo' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line></svg>',
	'woocommerce' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
	'diagnostics' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
	'media' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="3" width="18" height="18"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle></svg>',
	'users' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>',
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

$my_site_hand_active_module_count = 0;
foreach ($my_site_hand_all_modules as $my_site_hand_slug => $my_site_hand_mod) {
	if (in_array($my_site_hand_slug, $my_site_hand_enabled_mods, true)) {
		$my_site_hand_active_module_count++;
	}
}
$my_site_hand_module_total = count($my_site_hand_all_modules);
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-body-grid">

				<!-- On this page -->
				<nav class="msh-index" aria-label="<?php esc_attr_e('On this page', 'my-site-hand'); ?>">
					<p class="msh-index-label"><?php esc_html_e('On this page', 'my-site-hand'); ?></p>
					<a class="msh-index-link msh-index-link--active" href="#msh-set-server"><span class="msh-index-no">01</span><?php esc_html_e('Server', 'my-site-hand'); ?></a>
					<a class="msh-index-link" href="#msh-set-modules"><span class="msh-index-no">02</span><?php esc_html_e('Modules', 'my-site-hand'); ?><span class="msh-index-ct"><?php echo esc_html($my_site_hand_active_module_count . '/' . $my_site_hand_module_total); ?></span></a>
					<a class="msh-index-link" href="#msh-set-limits"><span class="msh-index-no">03</span><?php esc_html_e('Limits', 'my-site-hand'); ?></a>
					<a class="msh-index-link" href="#msh-set-audit"><span class="msh-index-no">04</span><?php esc_html_e('Audit log', 'my-site-hand'); ?></a>
					<a class="msh-index-link" href="#msh-set-danger"><span class="msh-index-no">05</span><?php esc_html_e('Irreversible', 'my-site-hand'); ?></a>
					<div class="msh-index-foot">
						<p><?php esc_html_e('Every row states what it changes and what stops working if you change it.', 'my-site-hand'); ?></p>
						<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-documentation')); ?>"><?php esc_html_e('Read the guide', 'my-site-hand'); ?></a>
					</div>
				</nav>

				<div class="msh-body-main">

					<div class="msh-page-head">
						<div class="msh-page-head-info">
							<p class="msh-eyebrow"><i></i><?php esc_html_e('Configuration', 'my-site-hand'); ?></p>
							<h2><?php esc_html_e('Settings', 'my-site-hand'); ?></h2>
							<p class="msh-page-desc"><?php esc_html_e('Five groups. Jump straight to the one you came for.', 'my-site-hand'); ?></p>
						</div>
						<div class="msh-page-head-actions">
							<span class="msh-settings-autosave-notice">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="square"><polyline points="20 6 9 17 4 12"></polyline></svg>
								<?php esc_html_e('Saved automatically', 'my-site-hand'); ?>
							</span>
						</div>
					</div>

					<?php // Every control below saves itself over AJAX, so there is no form to submit. ?>
					<div style="display: flex; flex-direction: column; gap: 26px;">

						<!-- 01 Server -->
						<div class="msh-card" id="msh-set-server">
							<div class="msh-card-header">
								<div class="msh-card-title-group">
									<span class="msh-card-no">01</span>
									<div>
										<h3><?php esc_html_e('Server', 'my-site-hand'); ?></h3>
										<p><?php esc_html_e('Whether clients can reach this site, and what they see it called.', 'my-site-hand'); ?></p>
									</div>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Serve the MCP endpoint', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('The master switch. Off means every connected agent stops working immediately. Your tokens, abilities, and log entries are all kept.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<span class="msh-switch-label">
										<span class="msh-switch-text" data-on="<?php esc_attr_e('Serving', 'my-site-hand'); ?>" data-off="<?php esc_attr_e('Stopped', 'my-site-hand'); ?>"></span>
										<label class="msh-switch">
											<input type="checkbox" name="mysitehand_enabled" value="1" <?php checked(get_option('mysitehand_enabled', true)); ?>
												onchange="msh.saveOption('mysitehand_enabled', this.checked)" />
											<span class="msh-slider"></span>
										</label>
									</span>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Agent display name', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('What Claude, Cursor, and other clients call this site in their tool list.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control msh-setting-control--stack">
									<input type="text" id="mysitehand_display_name" name="mysitehand_display_name"
										value="<?php echo esc_attr(get_option('mysitehand_display_name', '')); ?>"
										class="msh-input"
										placeholder="<?php esc_attr_e('e.g. My Website Agent', 'my-site-hand'); ?>"
										onblur="msh.saveOption('mysitehand_display_name', this.value)" />
									<span class="msh-hint msh-hint--right"><?php esc_html_e('Blank uses the site title.', 'my-site-hand'); ?></span>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Trust proxy headers', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Enable this only if your site sits behind a trusted reverse proxy such as Cloudflare. It allows reading X-Forwarded-For to determine the true client IP for the allowlist.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<span class="msh-switch-label">
										<span class="msh-switch-text" data-on="<?php esc_attr_e('Trusting', 'my-site-hand'); ?>" data-off="<?php esc_attr_e('Ignoring', 'my-site-hand'); ?>"></span>
										<label class="msh-switch">
											<input type="checkbox" name="mysitehand_trust_proxy" value="1" <?php checked(get_option('mysitehand_trust_proxy', false)); ?>
												onchange="msh.saveOption('mysitehand_trust_proxy', this.checked)" />
											<span class="msh-slider"></span>
										</label>
									</span>
								</div>
							</div>
						</div>

						<!-- 02 Modules -->
						<div class="msh-card" id="msh-set-modules">
							<div class="msh-card-header">
								<div class="msh-card-title-group">
									<span class="msh-card-no">02</span>
									<div>
										<h3><?php esc_html_e('Modules', 'my-site-hand'); ?></h3>
										<p><?php esc_html_e('A module is a group of abilities. Switch one off and all of them go.', 'my-site-hand'); ?></p>
									</div>
								</div>
								<span class="msh-sheet-tag msh-sheet-tag--ok"><?php
								printf(
									/* translators: 1: number of enabled modules, 2: total modules */
									esc_html__('%1$d of %2$d on', 'my-site-hand'),
									(int) $my_site_hand_active_module_count,
									(int) $my_site_hand_module_total
								); ?></span>
							</div>

							<div class="msh-settings-modules-grid">
								<?php foreach ($my_site_hand_all_modules as $my_site_hand_slug => $my_site_hand_mod):
									$my_site_hand_wc_available = $my_site_hand_slug !== 'woocommerce' || class_exists('WooCommerce');
									$my_site_hand_is_active = in_array($my_site_hand_slug, $my_site_hand_enabled_mods, true);
									$my_site_hand_icon = $my_site_hand_module_icons[$my_site_hand_slug] ?? '';

									// Real ability counts when the module is loaded, otherwise the declared total.
									$my_site_hand_mod_obj = $my_site_hand_module_objs[$my_site_hand_slug] ?? null;
									$my_site_hand_mod_names = $my_site_hand_mod_obj ? $my_site_hand_mod_obj->get_ability_names() : [];
									$my_site_hand_mod_total = count($my_site_hand_mod_names) ?: (int) $my_site_hand_mod['count'];
									$my_site_hand_mod_on = count(
										array_filter(
											$my_site_hand_mod_names,
											static function ($my_site_hand_n) use ($my_site_hand_disabled_abs) {
												return !in_array($my_site_hand_n, $my_site_hand_disabled_abs, true);
											}
										)
									);
									$my_site_hand_pct = $my_site_hand_mod_total > 0 ? round(($my_site_hand_mod_on / $my_site_hand_mod_total) * 100) : 0;
									?>
									<div class="msh-settings-module-card <?php echo $my_site_hand_is_active ? 'msh-settings-module-card--active' : ''; ?>"
										style="opacity: <?php echo esc_attr($my_site_hand_wc_available ? '1' : '0.55'); ?>;">
										<span class="msh-settings-module-icon msh-settings-module-icon--<?php echo esc_attr($my_site_hand_slug); ?>">
											<?php echo wp_kses($my_site_hand_icon, $my_site_hand_svg_allowed); ?>
										</span>
										<span class="msh-settings-module-info">
											<span class="msh-settings-module-title-row">
												<span class="msh-settings-module-title"><?php echo esc_html($my_site_hand_mod['label']); ?></span>
												<span class="msh-settings-module-tag"><?php
												printf(
													/* translators: %d: number of tools */
													esc_html__('%d tools', 'my-site-hand'),
													(int) $my_site_hand_mod_total
												); ?></span>
											</span>
											<span class="msh-settings-module-desc"><?php echo esc_html($my_site_hand_mod['desc']); ?></span>
											<?php if (!$my_site_hand_wc_available): ?>
												<small class="msh-settings-module-error"><?php esc_html_e('Install WooCommerce to enable', 'my-site-hand'); ?></small>
											<?php endif; ?>
										</span>
										<span class="msh-module-track"><i style="width: <?php echo esc_attr($my_site_hand_wc_available ? $my_site_hand_pct : 0); ?>%"></i></span>
										<span class="msh-module-ratio"><?php
										echo $my_site_hand_wc_available
											? esc_html($my_site_hand_mod_on . '/' . $my_site_hand_mod_total)
											: esc_html('—');
										?></span>
										<span class="msh-settings-module-right">
											<label class="msh-switch">
												<input type="checkbox" name="mysitehand_enabled_modules[]"
													value="<?php echo esc_attr($my_site_hand_slug); ?>" <?php checked($my_site_hand_is_active); ?> <?php echo !$my_site_hand_wc_available ? 'disabled' : ''; ?>
													onchange="msh.toggleModule('<?php echo esc_js($my_site_hand_slug); ?>', this.checked)" />
												<span class="msh-slider"></span>
											</label>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- 03 Limits -->
						<div class="msh-card" id="msh-set-limits">
							<div class="msh-card-header">
								<div class="msh-card-title-group">
									<span class="msh-card-no">03</span>
									<div>
										<h3><?php esc_html_e('Limits', 'my-site-hand'); ?></h3>
										<p><?php esc_html_e('Counted per token, so one busy client cannot starve the others.', 'my-site-hand'); ?></p>
									</div>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Calls per hour', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Over the cap returns 429 and is written to the log. 200 suits conversational use.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<input type="number" name="mysitehand_hourly_limit"
										value="<?php echo esc_attr(get_option('mysitehand_hourly_limit', 200)); ?>"
										class="msh-input msh-num" style="max-width: 130px; text-align: right;"
										onblur="msh.saveOption('mysitehand_hourly_limit', this.value)" />
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Calls per day', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('A backstop in case a client gets stuck in a loop overnight.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<input type="number" name="mysitehand_daily_limit"
										value="<?php echo esc_attr(get_option('mysitehand_daily_limit', 2000)); ?>"
										class="msh-input msh-num" style="max-width: 130px; text-align: right;"
										onblur="msh.saveOption('mysitehand_daily_limit', this.value)" />
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Cache read results for', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Repeat reads come from cache instead of the database. Drop to 60 while testing abilities.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<div class="msh-unit">
										<input type="number" name="mysitehand_cache_ttl"
											value="<?php echo esc_attr(get_option('mysitehand_cache_ttl', 3600)); ?>"
											class="msh-input msh-num" style="max-width: 110px; text-align: right;"
											onblur="msh.saveOption('mysitehand_cache_ttl', this.value)" />
										<span><?php esc_html_e('sec', 'my-site-hand'); ?></span>
										<button type="button" id="msh-clear-cache-btn" class="msh-btn msh-btn--sm"
											onclick="msh.clearCache()"><?php esc_html_e('Flush', 'my-site-hand'); ?></button>
									</div>
									<span class="msh-inline-result" id="msh-cache-result"></span>
								</div>
							</div>
						</div>

						<!-- Site Health reports -->
						<div class="msh-card" id="msh-set-reports">
							<div class="msh-card-header">
								<div class="msh-card-title-group">
									<span class="msh-card-no">SH</span>
									<div>
										<h3><?php esc_html_e('Site Health reports', 'my-site-hand'); ?></h3>
										<p><?php esc_html_e('A scheduled scan of your own site, emailed to you when something changes.', 'my-site-hand'); ?></p>
									</div>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Email me a report', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Nothing is sent when your score is unchanged and no new problems appeared.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<label class="msh-switch">
										<input type="checkbox" name="mysitehand_weekly_report_enabled"
											<?php checked((bool) get_option('mysitehand_weekly_report_enabled', true)); ?>
											onchange="msh.saveOption('mysitehand_weekly_report_enabled', this.checked)" />
										<span class="msh-slider"></span>
									</label>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Send to', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Defaults to the site admin address.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<input type="email" name="mysitehand_weekly_report_email"
										value="<?php echo esc_attr((string) get_option('mysitehand_weekly_report_email', get_option('admin_email'))); ?>"
										class="msh-input" style="max-width: 280px;"
										onblur="msh.saveOption('mysitehand_weekly_report_email', this.value)" />
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('How often', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Choosing Never also stops the scheduled scan itself.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<select name="mysitehand_report_frequency" class="msh-select"
										onchange="msh.saveOption('mysitehand_report_frequency', this.value)">
										<?php
										$my_site_hand_freq = (string) get_option('mysitehand_report_frequency', 'weekly');
										$my_site_hand_freq_options = [
											'weekly'  => __('Weekly', 'my-site-hand'),
											'monthly' => __('Monthly', 'my-site-hand'),
											'never'   => __('Never', 'my-site-hand'),
										];
										foreach ($my_site_hand_freq_options as $my_site_hand_freq_key => $my_site_hand_freq_label):
											?>
											<option value="<?php echo esc_attr($my_site_hand_freq_key); ?>"
												<?php selected($my_site_hand_freq, $my_site_hand_freq_key); ?>>
												<?php echo esc_html($my_site_hand_freq_label); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
						</div>

						<!-- 04 Audit log -->
						<div class="msh-card" id="msh-set-audit">
							<div class="msh-card-header">
								<div class="msh-card-title-group">
									<span class="msh-card-no">04</span>
									<div>
										<h3><?php esc_html_e('Audit log', 'my-site-hand'); ?></h3>
										<p><?php esc_html_e('What gets written down, and how long it survives.', 'my-site-hand'); ?></p>
									</div>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Keep entries for', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Older entries are removed on a daily cron. Export to CSV first if you need them.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<div class="msh-unit">
										<input type="number" name="mysitehand_log_retention_days"
											value="<?php echo esc_attr(get_option('mysitehand_log_retention_days', 30)); ?>"
											class="msh-input msh-num" style="max-width: 100px; text-align: right;"
											onblur="msh.saveOption('mysitehand_log_retention_days', this.value)" />
										<span><?php esc_html_e('days', 'my-site-hand'); ?></span>
									</div>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Level of detail', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Payloads are what make the log useful for debugging. Only turn this down on very high traffic.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<select name="mysitehand_log_level" class="msh-select"
										onchange="msh.saveOption('mysitehand_log_level', this.value)">
										<option value="all" <?php selected(get_option('mysitehand_log_level', 'all'), 'all'); ?>><?php esc_html_e('Every call, with payloads', 'my-site-hand'); ?></option>
										<option value="errors-only" <?php selected(get_option('mysitehand_log_level'), 'errors-only'); ?>><?php esc_html_e('Failures only', 'my-site-hand'); ?></option>
										<option value="none" <?php selected(get_option('mysitehand_log_level'), 'none'); ?>><?php esc_html_e('Nothing', 'my-site-hand'); ?></option>
									</select>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Allow token in URL query string', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Not recommended. Tokens sent this way are recorded in server logs and browser history. Enable only if your MCP client cannot send an Authorization header.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<span class="msh-switch-label">
										<span class="msh-switch-text" data-on="<?php esc_attr_e('Allowed', 'my-site-hand'); ?>" data-off="<?php esc_attr_e('Blocked', 'my-site-hand'); ?>"></span>
										<label class="msh-switch">
											<input type="checkbox" name="mysitehand_allow_query_token" value="1" <?php checked(get_option('mysitehand_allow_query_token', false)); ?>
												onchange="msh.saveOption('mysitehand_allow_query_token', this.checked)" />
											<span class="msh-slider"></span>
										</label>
									</span>
								</div>
							</div>
						</div>

						<!-- 05 Irreversible -->
						<div class="msh-card msh-danger-card" id="msh-set-danger">
							<div class="msh-card-header">
								<div class="msh-card-title-group">
									<span class="msh-card-no">05</span>
									<div>
										<h3 class="msh-danger-title"><?php esc_html_e('Irreversible', 'my-site-hand'); ?></h3>
										<p><?php esc_html_e('Both delete data permanently. There is no undo.', 'my-site-hand'); ?></p>
									</div>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Reset everything', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Deletes all tokens, every log entry, and all settings, then returns the plugin to a fresh install.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<button type="button" class="msh-btn msh-btn--danger"
										onclick="msh.dangerAction('reset_all', '<?php echo esc_js(wp_create_nonce('my_site_hand_admin')); ?>')">
										<?php esc_html_e('Reset all data', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e('Delete data on uninstall', 'my-site-hand'); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e('Off means your setup survives a deactivate and reactivate — useful when moving hosts.', 'my-site-hand'); ?></span>
								</div>
								<div class="msh-setting-control">
									<span class="msh-switch-label">
										<span class="msh-switch-text" data-on="<?php esc_attr_e('Deleting', 'my-site-hand'); ?>" data-off="<?php esc_attr_e('Keeping', 'my-site-hand'); ?>"></span>
										<label class="msh-switch">
											<input type="checkbox" name="mysitehand_delete_data_on_uninstall" value="1"
												<?php checked(get_option('mysitehand_delete_data_on_uninstall', false)); ?>
												onchange="msh.saveOption('mysitehand_delete_data_on_uninstall', this.checked)" />
											<span class="msh-slider"></span>
										</label>
									</span>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
