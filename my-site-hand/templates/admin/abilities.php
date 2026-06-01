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
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-page-header"
				style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; border-bottom: 1px solid var(--msh-border); padding-bottom: 24px;">
				<div class="msh-header-left">
					<h2 style="margin: 0; font-size: 24px; font-weight: 700; color: var(--msh-secondary);">
						<?php echo esc_html__('Abilities', 'my-site-hand'); ?></h2>
					<div style="font-size: 13px; color: var(--msh-text-muted); margin-top: 4px;">
						<?php
						$my_site_hand_disabled_abs = (array) get_option('mysitehand_disabled_abilities', []);
						$my_site_hand_public_abs = $my_site_hand_registry->get_mcp_public();
						$my_site_hand_public_count = count($my_site_hand_public_abs);
						$my_site_hand_disabled_count = count(array_intersect(array_keys($my_site_hand_abilities), $my_site_hand_disabled_abs));

						printf(
							/* translators: 1: total count, 2: public count, 3: disabled count */
							esc_html__('%1$d registered · %2$d MCP-public · %3$d disabled', 'my-site-hand'),
							(int) count($my_site_hand_abilities),
							(int) $my_site_hand_public_count,
							(int) $my_site_hand_disabled_count
						);
						?>
					</div>
				</div>
				<div class="msh-header-right">
					<div class="msh-status-mini-bar">
						<span class="msh-status-dot msh-status-dot--active"></span>
						<span
							style="font-weight: 500; color: var(--msh-secondary);"><?php echo esc_html__('MCP Active', 'my-site-hand'); ?></span>
					</div>
				</div>
			</div>

			<div class="msh-abilities-grid">
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
					<div class="msh-card" style="padding: 0;">
						<div class="msh-card-header"
							style="padding: 16px 24px; background: #fff; border-bottom: 1px solid var(--msh-border);">
								<h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--msh-secondary);">
									<?php 
									printf( 
										/* translators: %s: module name */
										esc_html__( '%s module', 'my-site-hand' ), 
										esc_html( $my_site_hand_label ) 
									); 
									?>
								</h3>
							<span class="msh-tag msh-tag--public"
								style="text-transform: lowercase; font-weight: 500;"><?php
								printf(
									/* translators: %d: number of abilities */
									esc_html__('%d abilities', 'my-site-hand'),
									count($my_site_hand_module_abilities)
								); ?></span>
						</div>
						<div class="msh-abilities-list">
							<?php foreach ($my_site_hand_module_abilities as $my_site_hand_name => $my_site_hand_ability):
								$my_site_hand_is_public = !empty($my_site_hand_ability['annotations']['meta']['mcp']['public']);
								$my_site_hand_is_readonly = !empty($my_site_hand_ability['annotations']['readonly']);
								$my_site_hand_is_destructive = !empty($my_site_hand_ability['annotations']['destructive']);
								$my_site_hand_is_enabled = !in_array($my_site_hand_name, $my_site_hand_disabled_abs, true);
								?>
								<div class="msh-ability-row">
									<div class="msh-ability-info" style="padding: 12px 0;">
										<div class="msh-ability-label" style="font-weight: 600; font-size: 15px; color: var(--msh-secondary);"><?php echo esc_html($my_site_hand_ability['label'] ?? $my_site_hand_name); ?></div>
										<div class="msh-ability-description" style="font-size: 13px; color: var(--msh-text-muted); margin-top: 6px; line-height: 1.5; max-width: 500px;"><?php echo esc_html($my_site_hand_ability['description'] ?? ''); ?></div>
									</div>
									<div class="msh-ability-actions">
										<div class="msh-ability-tags" style="display: flex; gap: 8px;">
											<?php if ($my_site_hand_is_readonly): ?>
												<span class="msh-tag msh-tag--readonly"><?php echo esc_html__('readonly', 'my-site-hand'); ?></span>
											<?php endif; ?>
											<?php if ($my_site_hand_is_destructive): ?>
												<span class="msh-tag msh-tag--destructive"><?php echo esc_html__('destructive', 'my-site-hand'); ?></span>
											<?php endif; ?>
											<?php if ($my_site_hand_is_public): ?>
												<span class="msh-tag msh-tag--public"><?php echo esc_html__('public', 'my-site-hand'); ?></span>
											<?php endif; ?>
										</div>
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
</div>
