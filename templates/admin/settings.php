<?php
/**
 * Settings page template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_plugin = \MySiteHand\Plugin::get_instance();
$my_site_hand_enabled_mods = $my_site_hand_plugin->get_enabled_modules();

$my_site_hand_all_modules = [
	'content' => ['label' => __('Content', 'my-site-hand'), 'desc' => __('Posts, pages, CPT management', 'my-site-hand'), 'count' => 9],
	'seo' => ['label' => __('SEO', 'my-site-hand'), 'desc' => __('SEO analysis and meta management', 'my-site-hand'), 'count' => 6],
	'diagnostics' => ['label' => __('Diagnostics', 'my-site-hand'), 'desc' => __('Site health, error logs, cron', 'my-site-hand'), 'count' => 7],
	'media' => ['label' => __('Media', 'my-site-hand'), 'desc' => __('Media library management', 'my-site-hand'), 'count' => 6],
	'users' => ['label' => __('Users', 'my-site-hand'), 'desc' => __('User management', 'my-site-hand'), 'count' => 5],
	'woocommerce' => ['label' => __('WooCommerce', 'my-site-hand'), 'desc' => __('Products, orders, coupons (requires WooCommerce)', 'my-site-hand'), 'count' => 12],
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
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-page-header">
				<h2><?php esc_html_e('Settings', 'my-site-hand'); ?></h2>
				<p class="msh-page-desc">
					<?php esc_html_e('Configure the core behavior of the MCP server and available tool modules.', 'my-site-hand'); ?>
				</p>
				<div class="msh-settings-autosave-notice" style="display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; color: var(--msh-success); font-weight: 600;">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
					<span><?php esc_html_e('Changes are saved automatically.', 'my-site-hand'); ?></span>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields('mysitehand_settings'); ?>

				<!-- AI Setup Configuration -->
				<div class="msh-card">
					<div class="msh-card-header">
						<h3><?php esc_html_e('AI Provider Setup', 'my-site-hand'); ?></h3>
					</div>
					<div class="msh-card-body" id="msh-api-setup-panel">
						<div class="msh-settings-notice msh-settings-notice--info">
							<strong><?php esc_html_e( 'Free AI included.', 'my-site-hand' ); ?></strong>
							<?php esc_html_e( 'My Site Hand works out of the box with 10 free messages per day — no setup needed.', 'my-site-hand' ); ?>
							<?php esc_html_e( 'Add your own API key below to get unlimited messages.', 'my-site-hand' ); ?>
						</div>

						<div class="msh-api-setup-grid" style="margin-top:20px; display:flex; gap:20px; flex-wrap:wrap;">
							<div class="msh-form-group" style="flex:1; min-width:200px;">
								<label class="msh-setting-title" for="msh-setup-provider"><?php esc_html_e('Provider', 'my-site-hand'); ?></label>
								<select id="msh-setup-provider" class="msh-select" style="margin-top:6px; width:100%;">
									<option value=""><?php esc_html_e('— Select —', 'my-site-hand'); ?></option>
									<option value="openai" <?php selected(get_option('mysitehand_ai_provider'), 'openai'); ?>><?php esc_html_e('OpenAI', 'my-site-hand'); ?></option>
									<option value="gemini" <?php selected(get_option('mysitehand_ai_provider'), 'gemini'); ?>><?php esc_html_e('Google Gemini', 'my-site-hand'); ?></option>
								</select>
							</div>

							<div class="msh-form-group" style="flex:2; min-width:300px;">
								<label class="msh-setting-title" for="msh-setup-key"><?php esc_html_e('API Key', 'my-site-hand'); ?></label>
								<input type="password" id="msh-setup-key" class="msh-input" style="margin-top:6px; width:100%;" autocomplete="off"
									value=""
									placeholder="<?php echo get_option('mysitehand_ai_api_key') ? esc_attr('••••••••••••••••') : esc_attr__('Paste your API key…', 'my-site-hand'); ?>" />
								<div style="font-size: 12px; margin-top: 6px; color: var(--msh-text-muted);">
									<?php esc_html_e('Get your API key: ', 'my-site-hand'); ?>
									<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">OpenAI</a> |
									<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">Google Gemini</a>
								</div>
							</div>
						</div>

						<div class="msh-api-setup-actions" style="margin-top:16px; display:flex; gap:12px; align-items:center;">
							<button type="button" id="msh-setup-save" class="msh-btn msh-btn--primary">
								<?php esc_html_e('Save & Activate', 'my-site-hand'); ?>
							</button>
							<button type="button" id="msh-setup-test" class="msh-btn msh-btn--ghost">
								<?php esc_html_e('Test Connection', 'my-site-hand'); ?>
							</button>
							<span id="msh-setup-result" class="msh-inline-result" style="font-size:13px; font-weight:500;"></span>
						</div>

						<hr style="border:0; border-top:1px solid var(--msh-border); margin:24px 0;" />

						<div class="msh-setting-item">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e('Enable MCP Server', 'my-site-hand'); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e('Activate the Model Context Protocol server for this site. Disabling this will shut down the endpoint for all agents.', 'my-site-hand'); ?></span>
							</div>
							<label class="msh-switch">
								<input type="checkbox" name="mysitehand_enabled" value="1" <?php checked(get_option('mysitehand_enabled', true)); ?>
									onchange="msh.saveOption('mysitehand_enabled', this.checked)" />
								<span class="msh-slider"></span>
							</label>
						</div>

						<div class="msh-setting-item" style="margin-top: 16px;">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e('Agent Display Name', 'my-site-hand'); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e('The localized name that AI clients (like Claude or Cursor) will see during initialization.', 'my-site-hand'); ?></span>
								<input type="text" id="mysitehand_display_name" name="mysitehand_display_name"
									value="<?php echo esc_attr(get_option('mysitehand_display_name', '')); ?>"
									class="msh-input" style="margin-top: 8px; max-width: 400px;"
									placeholder="<?php esc_attr_e('e.g. My Website Agent', 'my-site-hand'); ?>"
									onblur="msh.saveOption('mysitehand_display_name', this.value)" />
							</div>
						</div>
					</div>
				</div>

				<!-- Restructured Settings Columns Grid -->
				<div class="msh-settings-grid">

					<!-- Left Column: Active Modules -->
					<div class="msh-settings-col-left">
						<div class="msh-card">
							<div class="msh-card-header">
								<h3><?php esc_html_e('Active Modules', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-card-body">
								<div class="msh-settings-modules-grid">
									<?php foreach ($my_site_hand_all_modules as $my_site_hand_slug => $my_site_hand_mod):
										$my_site_hand_wc_available = $my_site_hand_slug !== 'woocommerce' || class_exists('WooCommerce');
										$my_site_hand_is_active = in_array($my_site_hand_slug, $my_site_hand_enabled_mods, true);
										$my_site_hand_icon = $my_site_hand_module_icons[$my_site_hand_slug] ?? '';
										?>
										<div class="msh-settings-module-card <?php echo $my_site_hand_is_active ? 'msh-settings-module-card--active' : ''; ?>"
											style="opacity: <?php echo esc_attr($my_site_hand_wc_available ? '1' : '0.5'); ?>;">
											<div class="msh-settings-module-left">
												<div
													class="msh-settings-module-icon msh-settings-module-icon--<?php echo esc_attr($my_site_hand_slug); ?>">
													<?php echo wp_kses($my_site_hand_icon, $my_site_hand_svg_allowed); ?>
												</div>
												<div class="msh-settings-module-info">
													<div class="msh-settings-module-title-row">
														<span
															class="msh-settings-module-title"><?php echo esc_html($my_site_hand_mod['label']); ?></span>
														<span class="msh-settings-module-tag"><?php
														printf(
															/* translators: %d: number of tools */
															esc_html__('%d tools', 'my-site-hand'),
															(int) $my_site_hand_mod['count']
														); ?></span>
													</div>
													<span
														class="msh-settings-module-desc"><?php echo esc_html($my_site_hand_mod['desc']); ?></span>
													<?php if (!$my_site_hand_wc_available): ?>
														<small
															class="msh-settings-module-error"><?php esc_html_e('WooCommerce Missing', 'my-site-hand'); ?></small>
													<?php endif; ?>
												</div>
											</div>
											<div class="msh-settings-module-right">
												<label class="msh-switch">
													<input type="checkbox" name="mysitehand_enabled_modules[]"
														value="<?php echo esc_attr($my_site_hand_slug); ?>" <?php checked($my_site_hand_is_active); ?> 	<?php echo !$my_site_hand_wc_available ? 'disabled' : ''; ?>
														onchange="msh.toggleModule('<?php echo esc_js($my_site_hand_slug); ?>', this.checked)" />
													<span class="msh-slider"></span>
												</label>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<!-- Audit Logging -->
						<div class="msh-card">
							<div class="msh-card-header">
								<h3><?php esc_html_e('Audit Logging', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-card-body">
								<div class="msh-form-group">
									<span
										class="msh-setting-title"><?php esc_html_e('Retention', 'my-site-hand'); ?></span>
									<div style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
										<input type="number" name="mysitehand_log_retention_days"
											value="<?php echo esc_attr(get_option('mysitehand_log_retention_days', 30)); ?>"
											class="msh-input" style="width: 80px;"
											onblur="msh.saveOption('mysitehand_log_retention_days', this.value)" />
										<span
											style="font-size: 13px; color: var(--msh-text-muted);"><?php esc_html_e('days', 'my-site-hand'); ?></span>
									</div>
								</div>
								<div class="msh-form-group" style="margin-top: 20px;">
									<span
										class="msh-setting-title"><?php esc_html_e('Detail Level', 'my-site-hand'); ?></span>
									<select name="mysitehand_log_level" class="msh-select" style="margin-top: 8px;"
										onchange="msh.saveOption('mysitehand_log_level', this.value)">
										<option value="all" <?php selected(get_option('mysitehand_log_level', 'all'), 'all'); ?>><?php esc_html_e('Detailed (All calls)', 'my-site-hand'); ?>
										</option>
										<option value="errors-only" <?php selected(get_option('mysitehand_log_level'), 'errors-only'); ?>><?php esc_html_e('Errors Only', 'my-site-hand'); ?>
										</option>
										<option value="none" <?php selected(get_option('mysitehand_log_level'), 'none'); ?>><?php esc_html_e('Off', 'my-site-hand'); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>

					<!-- Right Column: Sidebar Settings Boxes -->
					<div class="msh-settings-col-right">
						<!-- Scaling & Rate Limiting -->
						<div class="msh-card">
							<div class="msh-card-header">
								<h3><?php esc_html_e('Rate Limiting', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-card-body">
								<div class="msh-form-group">
									<span
										class="msh-setting-title"><?php esc_html_e('Hourly Limit', 'my-site-hand'); ?></span>
									<input type="number" name="mysitehand_hourly_limit"
										value="<?php echo esc_attr(get_option('mysitehand_hourly_limit', 200)); ?>"
										class="msh-input" style="margin-top: 8px;"
										onblur="msh.saveOption('mysitehand_hourly_limit', this.value)" />
									<p class="msh-hint">
										<?php esc_html_e('Calls allowed per hour per token.', 'my-site-hand'); ?></p>
								</div>
								<div class="msh-form-group" style="margin-top: 20px;">
									<span
										class="msh-setting-title"><?php esc_html_e('Daily Limit', 'my-site-hand'); ?></span>
									<input type="number" name="mysitehand_daily_limit"
										value="<?php echo esc_attr(get_option('mysitehand_daily_limit', 2000)); ?>"
										class="msh-input" style="margin-top: 8px;"
										onblur="msh.saveOption('mysitehand_daily_limit', this.value)" />
									<p class="msh-hint">
										<?php esc_html_e('Calls allowed per day per token.', 'my-site-hand'); ?></p>
								</div>
							</div>
						</div>

						<!-- System Cache -->
						<div class="msh-card">
							<div class="msh-card-header">
								<h3><?php esc_html_e('System Cache', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-card-body">
								<div class="msh-form-group">
									<span
										class="msh-setting-title"><?php esc_html_e('Cache TTL', 'my-site-hand'); ?></span>
									<div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
										<input type="number" name="mysitehand_cache_ttl"
											value="<?php echo esc_attr(get_option('mysitehand_cache_ttl', 3600)); ?>"
											class="msh-input"
											onblur="msh.saveOption('mysitehand_cache_ttl', this.value)" />
										<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm"
											onclick="msh.clearCache()"><?php esc_html_e('Flush', 'my-site-hand'); ?></button>
									</div>
									<p class="msh-hint"><?php esc_html_e('Results TTL (seconds).', 'my-site-hand'); ?>
									</p>
								</div>
							</div>
						</div>



						<!-- Danger Zone -->
						<div class="msh-card msh-danger-card">
							<div class="msh-card-header msh-danger-header">
								<h3 class="msh-danger-title"><?php esc_html_e('Maintenance', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-card-body" style="padding: 20px;">
								<div style="margin-bottom: 20px;">
									<span class="msh-setting-title"
										style="font-size: 13px;"><?php esc_html_e('Full Reset', 'my-site-hand'); ?></span>
									<button type="button" class="msh-btn msh-btn--sm msh-btn--ghost"
										style="width: 100%; margin-top: 8px; color: var(--msh-danger); border-color: rgba(214,54,56,0.1);"
										onclick="msh.dangerAction('reset_all', '<?php echo esc_js(wp_create_nonce('my_site_hand_admin')); ?>')">
										<?php esc_html_e('Reset All Data', 'my-site-hand'); ?>
									</button>
								</div>
								<div style="display: flex; justify-content: space-between; align-items: center;">
									<span class="msh-setting-title"
										style="margin-bottom: 0; font-size: 13px;"><?php esc_html_e('Auto-Cleanup', 'my-site-hand'); ?></span>
									<label class="msh-switch">
										<input type="checkbox" name="mysitehand_delete_data_on_uninstall" value="1"
											<?php checked(get_option('mysitehand_delete_data_on_uninstall', false)); ?>
											onchange="msh.saveOption('mysitehand_delete_data_on_uninstall', this.checked)" />
										<span class="msh-slider"></span>
									</label>
								</div>
							</div>
						</div>
					</div>

				</div>


			</form>
			

		</div>

		<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
	</div>
</div>