<?php
/**
 * Abilities list template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_plugin = \MySiteHand\Plugin::get_instance();
$my_site_hand_registry = $my_site_hand_plugin->get_abilities_registry();
$my_site_hand_abilities = $my_site_hand_registry->get_all();
$my_site_hand_enabled_mods = $my_site_hand_plugin->get_enabled_modules();

$my_site_hand_module_labels = [
	'content'     => __('Content', 'my-site-hand'),
	'seo'         => __('SEO', 'my-site-hand'),
	'diagnostics' => __('Diagnostics', 'my-site-hand'),
	'media'       => __('Media', 'my-site-hand'),
	'users'       => __('Users', 'my-site-hand'),
	'woocommerce' => __('WooCommerce', 'my-site-hand'),
];

$my_site_hand_module_descs = [
	'content'     => __('Posts, pages, and custom post types', 'my-site-hand'),
	'seo'         => __('Analysis and meta management', 'my-site-hand'),
	'diagnostics' => __('Site health, error logs, cron', 'my-site-hand'),
	'media'       => __('Media library', 'my-site-hand'),
	'users'       => __('Accounts and roles', 'my-site-hand'),
	'woocommerce' => __('Products, orders, and coupons', 'my-site-hand'),
];

$my_site_hand_module_icons = [
	'content'     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
	'seo'         => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line></svg>',
	'woocommerce' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
	'diagnostics' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
	'media'       => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="3" width="18" height="18"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle></svg>',
	'users'       => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>',
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

$my_site_hand_disabled_abs = (array) get_option('mysitehand_disabled_abilities', []);
$my_site_hand_public_abs = $my_site_hand_registry->get_mcp_public();
$my_site_hand_public_count = count($my_site_hand_public_abs);
$my_site_hand_total_count = count($my_site_hand_abilities);
$my_site_hand_disabled_count = count(array_intersect(array_keys($my_site_hand_abilities), $my_site_hand_disabled_abs));
$my_site_hand_enabled_count = $my_site_hand_total_count - $my_site_hand_disabled_count;

$my_site_hand_module_objs = $my_site_hand_plugin->get_modules();

// Resolve each module's abilities up front so the index and the sheets agree.
$my_site_hand_groups = [];
foreach ($my_site_hand_module_labels as $my_site_hand_slug => $my_site_hand_label) {
	$my_site_hand_module_obj = $my_site_hand_module_objs[$my_site_hand_slug] ?? null;
	$my_site_hand_names = $my_site_hand_module_obj ? $my_site_hand_module_obj->get_ability_names() : [];
	$my_site_hand_group_abilities = array_intersect_key($my_site_hand_abilities, array_flip($my_site_hand_names));

	if (empty($my_site_hand_group_abilities)) {
		continue;
	}

	$my_site_hand_group_on = count(
		array_filter(
			array_keys($my_site_hand_group_abilities),
			static function ($my_site_hand_n) use ($my_site_hand_disabled_abs) {
				return !in_array($my_site_hand_n, $my_site_hand_disabled_abs, true);
			}
		)
	);

	$my_site_hand_groups[$my_site_hand_slug] = [
		'label'     => $my_site_hand_label,
		'desc'      => $my_site_hand_module_descs[$my_site_hand_slug] ?? '',
		'icon'      => $my_site_hand_module_icons[$my_site_hand_slug] ?? '',
		'abilities' => $my_site_hand_group_abilities,
		'on'        => $my_site_hand_group_on,
		'total'     => count($my_site_hand_group_abilities),
		'active'    => in_array($my_site_hand_slug, $my_site_hand_enabled_mods, true),
	];
}

$my_site_hand_destructive_count = 0;
foreach ($my_site_hand_abilities as $my_site_hand_ab) {
	if (!empty($my_site_hand_ab['annotations']['destructive'])) {
		$my_site_hand_destructive_count++;
	}
}
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-body-grid">

				<!-- Modules index -->
				<nav class="msh-index" aria-label="<?php esc_attr_e('Modules', 'my-site-hand'); ?>">
					<p class="msh-index-label"><?php esc_html_e('Modules', 'my-site-hand'); ?></p>
					<?php $my_site_hand_i = 0; ?>
					<?php foreach ($my_site_hand_groups as $my_site_hand_slug => $my_site_hand_group):
						$my_site_hand_i++;
						?>
						<a class="msh-index-link <?php echo 1 === $my_site_hand_i ? 'msh-index-link--active' : ''; ?>"
							href="#msh-ab-<?php echo esc_attr($my_site_hand_slug); ?>"
							style="<?php echo $my_site_hand_group['active'] ? '' : 'opacity:.5;'; ?>">
							<span class="msh-index-no"><?php echo esc_html(str_pad((string) $my_site_hand_i, 2, '0', STR_PAD_LEFT)); ?></span><?php echo esc_html($my_site_hand_group['label']); ?>
							<span class="msh-index-ct"><?php echo esc_html($my_site_hand_group['on'] . '/' . $my_site_hand_group['total']); ?></span>
						</a>
					<?php endforeach; ?>
					<div class="msh-index-foot">
						<p><?php
						printf(
							/* translators: %d: number of destructive abilities */
							esc_html(_n('%d ability is destructive. It also needs a token with admin scope.', '%d abilities are destructive. Those also need a token with admin scope.', (int) $my_site_hand_destructive_count, 'my-site-hand')),
							(int) $my_site_hand_destructive_count
						); ?></p>
						<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-tokens')); ?>"><?php esc_html_e('See tokens', 'my-site-hand'); ?></a>
					</div>
				</nav>

				<div class="msh-body-main">

					<div class="msh-page-head">
						<div class="msh-page-head-info">
							<p class="msh-eyebrow"><i></i><?php esc_html_e('Permissions', 'my-site-hand'); ?></p>
							<h2 class="msh-page-title"><?php esc_html_e('Abilities', 'my-site-hand'); ?></h2>
							<p class="msh-page-desc"><?php
							printf(
								/* translators: 1: number of enabled abilities, 2: total number of abilities */
								esc_html__('%1$d of %2$d are callable. Each switch is one thing an AI client may do here.', 'my-site-hand'),
								(int) $my_site_hand_enabled_count,
								(int) $my_site_hand_total_count
							); ?></p>
						</div>
						<div class="msh-page-head-actions">
							<button type="button" class="msh-btn" onclick="msh.toggleAllAbilities(false)"><?php esc_html_e('All off', 'my-site-hand'); ?></button>
							<button type="button" class="msh-btn msh-btn--primary" onclick="msh.toggleAllAbilities(true)"><?php esc_html_e('All on', 'my-site-hand'); ?></button>
						</div>
					</div>

					<!-- Figures -->
					<div class="msh-abilities-stats-grid">
						<div class="msh-ability-stat-card msh-ability-stat--total">
							<span class="msh-ability-stat-label"><?php esc_html_e('Registered', 'my-site-hand'); ?></span>
							<span class="msh-ability-stat-value"><?php echo (int) $my_site_hand_total_count; ?></span>
						</div>
						<div class="msh-ability-stat-card msh-ability-stat--enabled">
							<span class="msh-ability-stat-label"><?php esc_html_e('Enabled', 'my-site-hand'); ?></span>
							<span class="msh-ability-stat-value"><?php echo (int) $my_site_hand_enabled_count; ?></span>
						</div>
						<div class="msh-ability-stat-card msh-ability-stat--disabled">
							<span class="msh-ability-stat-label"><?php esc_html_e('Held back', 'my-site-hand'); ?></span>
							<span class="msh-ability-stat-value"><?php echo (int) $my_site_hand_disabled_count; ?></span>
						</div>
						<div class="msh-ability-stat-card msh-ability-stat--public">
							<span class="msh-ability-stat-label"><?php esc_html_e('MCP-public', 'my-site-hand'); ?></span>
							<span class="msh-ability-stat-value"><?php echo (int) $my_site_hand_public_count; ?></span>
						</div>
					</div>

					<!-- Search and filter -->
					<div class="msh-abilities-filters">
						<div class="msh-search-input-wrap">
							<svg class="msh-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
							<input type="text" id="msh-abilities-search" class="msh-input msh-abilities-search-input"
								placeholder="<?php
								printf(
									/* translators: %d: total number of abilities */
									esc_attr__('Search %d abilities', 'my-site-hand'),
									(int) $my_site_hand_total_count
								); ?>" />
						</div>
						<div class="msh-filter-select-wrap">
							<select id="msh-abilities-filter" class="msh-select msh-abilities-filter-select">
								<option value="all"><?php esc_html_e('All modules', 'my-site-hand'); ?></option>
								<?php foreach ($my_site_hand_groups as $my_site_hand_slug => $my_site_hand_group): ?>
									<option value="<?php echo esc_attr($my_site_hand_slug); ?>"><?php echo esc_html($my_site_hand_group['label']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<!-- Module sheets -->
					<div class="msh-abilities-list-wrapper">
						<?php $my_site_hand_i = 0; ?>
						<?php foreach ($my_site_hand_groups as $my_site_hand_slug => $my_site_hand_group):
							$my_site_hand_i++;
							?>
							<div class="msh-card msh-module-card-box" id="msh-ab-<?php echo esc_attr($my_site_hand_slug); ?>"
								data-module="<?php echo esc_attr($my_site_hand_slug); ?>">
								<div class="msh-card-header msh-module-card-header">
									<div class="msh-module-header-left">
										<span class="msh-card-no"><?php echo esc_html(str_pad((string) $my_site_hand_i, 2, '0', STR_PAD_LEFT)); ?></span>
										<span class="msh-module-header-icon"><?php echo wp_kses($my_site_hand_group['icon'], $my_site_hand_svg_allowed); ?></span>
										<div class="msh-module-header-info">
											<h3 class="msh-module-header-title"><?php echo esc_html($my_site_hand_group['label']); ?></h3>
											<span class="msh-module-header-subtext"><?php echo esc_html($my_site_hand_group['desc']); ?></span>
										</div>
									</div>
									<span class="msh-sheet-tag <?php echo $my_site_hand_group['on'] === $my_site_hand_group['total'] ? 'msh-sheet-tag--ok' : ''; ?>"><?php
									printf(
										/* translators: 1: number of enabled abilities, 2: total abilities in the module */
										esc_html__('%1$d of %2$d on', 'my-site-hand'),
										(int) $my_site_hand_group['on'],
										(int) $my_site_hand_group['total']
									); ?></span>
								</div>

								<?php if (!$my_site_hand_group['active']): ?>
									<div class="msh-setting-item" style="grid-template-columns: minmax(0, 1fr);">
										<p class="msh-hint" style="margin: 0;"><?php
										printf(
											/* translators: %s: link to the settings page */
											esc_html__('This module is switched off, so none of the abilities below are reachable. Turn it on in %s.', 'my-site-hand'),
											'<a href="' . esc_url(admin_url('admin.php?page=my-site-hand-settings#msh-set-modules')) . '" class="msh-link">' . esc_html__('Settings', 'my-site-hand') . '</a>'
										); ?></p>
									</div>
								<?php endif; ?>

								<div class="msh-abilities-list">
									<?php foreach ($my_site_hand_group['abilities'] as $my_site_hand_name => $my_site_hand_ability):
										$my_site_hand_is_public = !empty($my_site_hand_ability['annotations']['meta']['mcp']['public']);
										$my_site_hand_is_readonly = !empty($my_site_hand_ability['annotations']['readonly']);
										$my_site_hand_is_destructive = !empty($my_site_hand_ability['annotations']['destructive']);
										$my_site_hand_is_enabled = !in_array($my_site_hand_name, $my_site_hand_disabled_abs, true);
										?>
										<div class="msh-ability-row" data-name="<?php echo esc_attr($my_site_hand_name); ?>">
											<div class="msh-ability-row-left">
												<div class="msh-ability-row-header-line">
													<span class="msh-ability-name-code"><?php echo esc_html($my_site_hand_ability['label'] ?? $my_site_hand_name); ?></span>
													<span class="msh-ability-key"><?php echo esc_html($my_site_hand_name); ?></span>
													<div class="msh-ability-tags">
														<?php if ($my_site_hand_is_readonly): ?>
															<span class="msh-tag msh-tag--readonly"><?php esc_html_e('read only', 'my-site-hand'); ?></span>
														<?php endif; ?>
														<?php if ($my_site_hand_is_destructive): ?>
															<span class="msh-tag msh-tag--destructive"><?php esc_html_e('destructive', 'my-site-hand'); ?></span>
														<?php endif; ?>
														<?php if ($my_site_hand_is_public): ?>
															<span class="msh-tag msh-tag--public"><?php esc_html_e('public', 'my-site-hand'); ?></span>
														<?php else: ?>
															<span class="msh-tag msh-tag--admin"><?php esc_html_e('admin scope', 'my-site-hand'); ?></span>
														<?php endif; ?>
													</div>
												</div>
												<p class="msh-ability-description"><?php echo esc_html($my_site_hand_ability['description'] ?? ''); ?></p>
											</div>
											<div class="msh-ability-row-right">
												<span class="msh-switch-label">
													<span class="msh-switch-text" data-on="<?php esc_attr_e('On', 'my-site-hand'); ?>" data-off="<?php esc_attr_e('Off', 'my-site-hand'); ?>"></span>
													<label class="msh-switch">
														<input type="checkbox" <?php checked($my_site_hand_is_enabled); ?>
															onchange="msh.toggleAbility('<?php echo esc_js($my_site_hand_name); ?>', this.checked)" />
														<span class="msh-slider"></span>
													</label>
												</span>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

				</div>
			</div>
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
