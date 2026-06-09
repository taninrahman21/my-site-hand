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

$my_site_hand_module_labels = [
	'content'     => __( 'Content', 'my-site-hand' ),
	'seo'         => __( 'SEO', 'my-site-hand' ),
	'woocommerce' => __( 'WooCommerce', 'my-site-hand' ),
	'diagnostics' => __( 'Diagnostics', 'my-site-hand' ),
	'media'       => __( 'Media', 'my-site-hand' ),
	'users'       => __( 'Users', 'my-site-hand' ),
];

$my_site_hand_module_icons = [
	'content'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
	'seo'         => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
	'woocommerce' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
	'diagnostics' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
	'media'       => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>',
	'users'       => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
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
$my_site_hand_disabled_count = count(array_intersect(array_keys($my_site_hand_abilities), $my_site_hand_disabled_abs));
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<!-- Header Row -->
			<div class="msh-abilities-header-row">
				<div class="msh-page-header-info">
					<h2 class="msh-page-title"><?php echo esc_html__('Abilities', 'my-site-hand'); ?></h2>
					<p class="msh-page-desc-inline"><?php echo esc_html__('Control which actions your AI clients can perform.', 'my-site-hand'); ?></p>
				</div>
				<div class="msh-page-header-actions">
					<button type="button" class="msh-btn msh-btn--ghost" onclick="msh.toggleAllAbilities(true)">
						<?php echo esc_html__('Enable all', 'my-site-hand'); ?>
					</button>
					<button type="button" class="msh-btn msh-btn--ghost" onclick="msh.toggleAllAbilities(false)">
						<?php echo esc_html__('Disable all', 'my-site-hand'); ?>
					</button>
				</div>
			</div>

			<!-- Stats Mini Grid -->
			<div class="msh-abilities-stats-grid">
				<div class="msh-ability-stat-card msh-ability-stat--total">
					<span class="msh-ability-stat-label"><?php echo esc_html__('TOTAL', 'my-site-hand'); ?></span>
					<span class="msh-ability-stat-value"><?php echo (int) count($my_site_hand_abilities); ?></span>
				</div>
				<div class="msh-ability-stat-card msh-ability-stat--enabled">
					<span class="msh-ability-stat-label"><?php echo esc_html__('ENABLED', 'my-site-hand'); ?></span>
					<span class="msh-ability-stat-value"><?php echo (int) (count($my_site_hand_abilities) - $my_site_hand_disabled_count); ?></span>
				</div>
				<div class="msh-ability-stat-card msh-ability-stat--disabled">
					<span class="msh-ability-stat-label"><?php echo esc_html__('DISABLED', 'my-site-hand'); ?></span>
					<span class="msh-ability-stat-value"><?php echo (int) $my_site_hand_disabled_count; ?></span>
				</div>
				<div class="msh-ability-stat-card msh-ability-stat--public">
					<span class="msh-ability-stat-label"><?php echo esc_html__('MCP-PUBLIC', 'my-site-hand'); ?></span>
					<span class="msh-ability-stat-value"><?php echo (int) $my_site_hand_public_count; ?></span>
				</div>
			</div>

			<!-- Search & Filter Bar -->
			<div class="msh-abilities-filters">
				<div class="msh-search-input-wrap">
					<svg class="msh-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
					<input type="text" id="msh-abilities-search" placeholder="<?php esc_attr_e('Search abilities...', 'my-site-hand'); ?>" class="msh-input msh-abilities-search-input">
				</div>
				<div class="msh-filter-select-wrap">
					<select id="msh-abilities-filter" class="msh-select msh-abilities-filter-select">
						<option value="all"><?php esc_html_e('All modules', 'my-site-hand'); ?></option>
						<?php foreach ($my_site_hand_module_labels as $my_site_hand_slug => $my_site_hand_label): ?>
							<option value="<?php echo esc_attr($my_site_hand_slug); ?>"><?php echo esc_html($my_site_hand_label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Abilities list -->
			<div class="msh-abilities-list-wrapper">
				<?php
				$my_site_hand_module_objs = $my_site_hand_plugin->get_modules();
				foreach ($my_site_hand_module_labels as $my_site_hand_slug => $my_site_hand_label):
					$my_site_hand_module_obj = $my_site_hand_module_objs[$my_site_hand_slug] ?? null;
					$my_site_hand_module_ability_names = $my_site_hand_module_obj ? $my_site_hand_module_obj->get_ability_names() : [];

					if ('general' === $my_site_hand_slug) {
						$my_site_hand_module_abilities = array_filter($my_site_hand_abilities, function ($name) use ($my_site_hand_module_objs) {
							foreach ($my_site_hand_module_objs as $m) {
								if (in_array($name, $m->get_ability_names(), true))
									return false;
							}
							return true;
						}, ARRAY_FILTER_USE_KEY);
					} else {
						$my_site_hand_module_abilities = array_intersect_key($my_site_hand_abilities, array_flip($my_site_hand_module_ability_names));
					}

					if (empty($my_site_hand_module_abilities) && 'general' !== $my_site_hand_slug)
						continue;
					?>
					<div class="msh-card msh-module-card-box" data-module="<?php echo esc_attr($my_site_hand_slug); ?>" style="padding: 0; margin-bottom: 24px;">
						<div class="msh-card-header msh-module-card-header">
							<div class="msh-module-header-left">
								<div class="msh-module-header-icon">
									<?php echo wp_kses($my_site_hand_module_icons[$my_site_hand_slug] ?? '', $my_site_hand_svg_allowed); ?>
								</div>
								<div class="msh-module-header-info">
									<h3 class="msh-module-header-title"><?php echo esc_html($my_site_hand_label); ?></h3>
									<span class="msh-module-header-subtext">
										<?php 
										$my_site_hand_module_enabled_count = count(array_filter(array_keys($my_site_hand_module_abilities), function($name) use ($my_site_hand_disabled_abs) {
											return !in_array($name, $my_site_hand_disabled_abs, true);
										}));
										$my_site_hand_module_total_count = count($my_site_hand_module_abilities);
										echo esc_html(
											sprintf(
												/* translators: 1: enabled count, 2: total count */
												__('%1$d of %2$d enabled', 'my-site-hand'),
												$my_site_hand_module_enabled_count,
												$my_site_hand_module_total_count
											)
										);
										?>
									</span>
								</div>
							</div>
							<span class="msh-module-header-right-count"><?php echo esc_html($my_site_hand_module_total_count); ?></span>
						</div>
						<div class="msh-abilities-list">
							<?php foreach ($my_site_hand_module_abilities as $my_site_hand_name => $my_site_hand_ability):
								$my_site_hand_is_public = !empty($my_site_hand_ability['annotations']['meta']['mcp']['public']);
								$my_site_hand_is_readonly = !empty($my_site_hand_ability['annotations']['readonly']);
								$my_site_hand_is_destructive = !empty($my_site_hand_ability['annotations']['destructive']);
								$my_site_hand_is_enabled = !in_array($my_site_hand_name, $my_site_hand_disabled_abs, true);
								?>
								<div class="msh-ability-row" data-name="<?php echo esc_attr($my_site_hand_name); ?>">
									<div class="msh-ability-row-left">
										<div class="msh-ability-row-header-line">
											<span class="msh-ability-name-code"><?php echo esc_html($my_site_hand_ability['label'] ?? $my_site_hand_name); ?></span>
											<div class="msh-ability-tags">
												<?php if ($my_site_hand_is_readonly): ?>
													<span class="msh-tag msh-tag--readonly"><?php echo esc_html__('readonly', 'my-site-hand'); ?></span>
												<?php endif; ?>
												<?php if ($my_site_hand_is_destructive): ?>
													<span class="msh-tag msh-tag--destructive"><?php echo esc_html__('destructive', 'my-site-hand'); ?></span>
												<?php endif; ?>
												<?php if ($my_site_hand_is_public): ?>
													<span class="msh-tag msh-tag--public"><?php echo esc_html__('public', 'my-site-hand'); ?></span>
												<?php else: ?>
													<span class="msh-tag msh-tag--admin"><?php echo esc_html__('admin', 'my-site-hand'); ?></span>
												<?php endif; ?>
											</div>
										</div>
										<p class="msh-ability-description"><?php echo esc_html($my_site_hand_ability['description'] ?? ''); ?></p>
									</div>
									<div class="msh-ability-row-right">
										<label class="msh-switch">
											<input type="checkbox" <?php checked($my_site_hand_is_enabled); ?>
												onchange="msh.toggleAbility('<?php echo esc_js($my_site_hand_name); ?>', this.checked)">
											<span class="msh-slider"></span>
										</label>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>

