<?php
/**
 * Settings page template.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;

$my_site_hand_plugin        = \MySiteHand\Plugin::get_instance();
$my_site_hand_enabled_mods  = $my_site_hand_plugin->get_enabled_modules();

$my_site_hand_all_modules = [
	'content'     => [ 'label' => __( 'Content', 'my-site-hand' ),     'desc' => __( 'Posts, pages, CPT management', 'my-site-hand' ),         'count' => 9 ],
	'seo'         => [ 'label' => __( 'SEO', 'my-site-hand' ),         'desc' => __( 'SEO analysis and meta management', 'my-site-hand' ),     'count' => 6 ],
	'diagnostics' => [ 'label' => __( 'Diagnostics', 'my-site-hand' ), 'desc' => __( 'Site health, error logs, cron', 'my-site-hand' ),        'count' => 7 ],
	'media'       => [ 'label' => __( 'Media', 'my-site-hand' ),       'desc' => __( 'Media library management', 'my-site-hand' ),             'count' => 6 ],
	'users'       => [ 'label' => __( 'Users', 'my-site-hand' ),       'desc' => __( 'User management', 'my-site-hand' ),                      'count' => 6 ],
	'woocommerce' => [ 'label' => __( 'WooCommerce', 'my-site-hand' ), 'desc' => __( 'Products, orders, coupons (requires WooCommerce)', 'my-site-hand' ), 'count' => 12 ],
];
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-page-header" style="margin-bottom: 32px; border-bottom: 1px solid var(--msh-border); padding-bottom: 24px;">
				<h2 style="margin: 0; font-size: 24px; font-weight: 700; color: var(--msh-secondary);"><?php esc_html_e( 'Settings', 'my-site-hand' ); ?></h2>
				<p style="margin: 8px 0 0; font-size: 14px; color: var(--msh-text-muted);"><?php esc_html_e( 'Configure the core behavior of the MCP server and available tool modules.', 'my-site-hand' ); ?></p>
			</div>

			<form method="post" action="options.php">
			<?php settings_fields( 'mysitehand_settings' ); ?>

			<div class="msh-dashboard-body">

				<div class="msh-dashboard-main">
					<!-- General Configuration -->
					<div class="msh-card">
						<div class="msh-card-header">
							<h3><?php esc_html_e( 'General Configuration', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body">
							<div class="msh-setting-item">
								<div class="msh-setting-info">
									<span class="msh-setting-title"><?php esc_html_e( 'Enable MCP Server', 'my-site-hand' ); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e( 'Activate the Model Context Protocol server for this site. Disabling this will shut down the endpoint for all agents.', 'my-site-hand' ); ?></span>
								</div>
								<label class="msh-switch">
									<input type="checkbox" name="mysitehand_enabled" value="1" <?php checked( get_option( 'mysitehand_enabled', true ) ); ?> onchange="msh.saveOption('mysitehand_enabled', this.checked)" />
									<span class="msh-slider"></span>
								</label>
							</div>

							<div class="msh-setting-item">
								<div class="msh-setting-info" style="padding-top: 8px;">
									<span class="msh-setting-title"><?php esc_html_e( 'Agent Display Name', 'my-site-hand' ); ?></span>
									<span class="msh-setting-desc"><?php esc_html_e( 'The localized name that AI clients (like Claude or Cursor) will see during initialization.', 'my-site-hand' ); ?></span>
									<input type="text" id="mysitehand_display_name" name="mysitehand_display_name"
										value="<?php echo esc_attr( get_option( 'mysitehand_display_name', '' ) ); ?>"
										class="msh-input" style="margin-top: 12px; max-width: 400px;" placeholder="<?php esc_attr_e( 'e.g. My Website Agent', 'my-site-hand' ); ?>"
										onblur="msh.saveOption('mysitehand_display_name', this.value)" />
								</div>
							</div>
						</div>
					</div>

					<!-- Active Modules -->
					<div class="msh-card" style="margin-top: 32px;">
						<div class="msh-card-header">
							<h3><?php esc_html_e( 'Active Modules', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body">
							<div class="msh-abilities-grid">
								<?php foreach ( $my_site_hand_all_modules as $my_site_hand_slug => $my_site_hand_mod ) :
									$my_site_hand_wc_available = $my_site_hand_slug !== 'woocommerce' || class_exists( 'WooCommerce' );
									$my_site_hand_is_active = in_array( $my_site_hand_slug, $my_site_hand_enabled_mods, true );
								?>
								<div class="msh-setting-item" style="padding: 16px; border: 1px solid var(--msh-border); border-radius: 8px; opacity: <?php echo esc_attr( $my_site_hand_wc_available ? '1' : '0.5' ); ?>;">
									<div class="msh-setting-info">
										<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px;">
											<span class="msh-setting-title" style="margin-bottom: 0;"><?php echo esc_html( $my_site_hand_mod['label'] ); ?></span>
											<span class="msh-tag msh-tag--public" style="font-size: 10px;"><?php
												printf(
													/* translators: %d: number of tools */
													esc_html__( '%d tools', 'my-site-hand' ),
													(int) $my_site_hand_mod['count']
												); ?></span>
										</div>
										<span class="msh-setting-desc" style="font-size: 12px;"><?php echo esc_html( $my_site_hand_mod['desc'] ); ?></span>
										<?php if ( ! $my_site_hand_wc_available ) : ?>
											<small style="color: var(--msh-danger); display: block; margin-top: 4px; font-weight: 700; font-size: 10px; text-transform: uppercase;"><?php esc_html_e( 'WooCommerce Missing', 'my-site-hand' ); ?></small>
										<?php endif; ?>
									</div>
									<label class="msh-switch">
										<input type="checkbox" name="mysitehand_enabled_modules[]" value="<?php echo esc_attr( $my_site_hand_slug ); ?>" <?php checked( $my_site_hand_is_active ); ?> <?php echo ! $my_site_hand_wc_available ? 'disabled' : ''; ?> 
											onchange="msh.toggleModule('<?php echo esc_js( $my_site_hand_slug ); ?>', this.checked)" />
										<span class="msh-slider"></span>
									</label>
								</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>

				<div class="msh-dashboard-sidebar">
					<!-- Scaling & Rate Limiting -->
					<div class="msh-card">
						<div class="msh-card-header">
							<h3><?php esc_html_e( 'Rate Limiting', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body">
							<div class="msh-form-group">
								<span class="msh-setting-title"><?php esc_html_e( 'Hourly Limit', 'my-site-hand' ); ?></span>
								<input type="number" name="mysitehand_hourly_limit" value="<?php echo esc_attr( get_option( 'mysitehand_hourly_limit', 200 ) ); ?>" class="msh-input" style="margin-top: 8px;" 
									onblur="msh.saveOption('mysitehand_hourly_limit', this.value)" />
								<p class="msh-hint"><?php esc_html_e( 'Calls allowed per hour per token.', 'my-site-hand' ); ?></p>
							</div>
							<div class="msh-form-group" style="margin-top: 20px;">
								<span class="msh-setting-title"><?php esc_html_e( 'Daily Limit', 'my-site-hand' ); ?></span>
								<input type="number" name="mysitehand_daily_limit" value="<?php echo esc_attr( get_option( 'mysitehand_daily_limit', 2000 ) ); ?>" class="msh-input" style="margin-top: 8px;" 
									onblur="msh.saveOption('mysitehand_daily_limit', this.value)" />
								<p class="msh-hint"><?php esc_html_e( 'Calls allowed per day per token.', 'my-site-hand' ); ?></p>
							</div>
						</div>
					</div>

					<!-- System Cache -->
					<div class="msh-card">
						<div class="msh-card-header">
							<h3><?php esc_html_e( 'System Cache', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body">
							<div class="msh-form-group">
								<span class="msh-setting-title"><?php esc_html_e( 'Cache TTL', 'my-site-hand' ); ?></span>
								<div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
									<input type="number" name="mysitehand_cache_ttl" value="<?php echo esc_attr( get_option( 'mysitehand_cache_ttl', 3600 ) ); ?>" class="msh-input" 
										onblur="msh.saveOption('mysitehand_cache_ttl', this.value)" />
									<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" onclick="msh.clearCache()"><?php esc_html_e( 'Flush', 'my-site-hand' ); ?></button>
								</div>
								<p class="msh-hint"><?php esc_html_e( 'Results TTL (seconds).', 'my-site-hand' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Audit Logging -->
					<div class="msh-card">
						<div class="msh-card-header">
							<h3><?php esc_html_e( 'Audit Logging', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body">
							<div class="msh-form-group">
								<span class="msh-setting-title"><?php esc_html_e( 'Retention', 'my-site-hand' ); ?></span>
								<div style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
									<input type="number" name="mysitehand_log_retention_days" value="<?php echo esc_attr( get_option( 'mysitehand_log_retention_days', 30 ) ); ?>" class="msh-input" style="width: 80px;" 
										onblur="msh.saveOption('mysitehand_log_retention_days', this.value)" />
									<span style="font-size: 13px; color: var(--msh-text-muted);"><?php esc_html_e( 'days', 'my-site-hand' ); ?></span>
								</div>
							</div>
							<div class="msh-form-group" style="margin-top: 20px;">
								<span class="msh-setting-title"><?php esc_html_e( 'Detail Level', 'my-site-hand' ); ?></span>
								<select name="mysitehand_log_level" class="msh-select" style="margin-top: 8px;" onchange="msh.saveOption('mysitehand_log_level', this.value)">
									<option value="all" <?php selected( get_option( 'mysitehand_log_level', 'all' ), 'all' ); ?>><?php esc_html_e( 'Detailed (All calls)', 'my-site-hand' ); ?></option>
									<option value="errors-only" <?php selected( get_option( 'mysitehand_log_level' ), 'errors-only' ); ?>><?php esc_html_e( 'Errors Only', 'my-site-hand' ); ?></option>
									<option value="none" <?php selected( get_option( 'mysitehand_log_level' ), 'none' ); ?>><?php esc_html_e( 'Off', 'my-site-hand' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- Danger Zone -->
					<div class="msh-card msh-danger-card">
						<div class="msh-card-header msh-danger-header">
							<h3 class="msh-danger-title"><?php esc_html_e( 'Maintenance', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body" style="padding: 20px;">
							<div style="margin-bottom: 20px;">
								<span class="msh-setting-title" style="font-size: 13px;"><?php esc_html_e( 'Full Reset', 'my-site-hand' ); ?></span>
								<button type="button" class="msh-btn msh-btn--sm msh-btn--ghost" style="width: 100%; margin-top: 8px; color: var(--msh-danger); border-color: rgba(214,54,56,0.1);" onclick="msh.dangerAction('reset_all', '<?php echo esc_js( wp_create_nonce( 'my_site_hand_admin' ) ); ?>')">
									<?php esc_html_e( 'Reset All Data', 'my-site-hand' ); ?>
								</button>
							</div>
							<div style="display: flex; justify-content: space-between; align-items: center;">
								<span class="msh-setting-title" style="margin-bottom: 0; font-size: 13px;"><?php esc_html_e( 'Auto-Cleanup', 'my-site-hand' ); ?></span>
								<label class="msh-switch">
									<input type="checkbox" name="mysitehand_delete_data_on_uninstall" value="1" <?php checked( get_option( 'mysitehand_delete_data_on_uninstall', false ) ); ?> 
										onchange="msh.saveOption('mysitehand_delete_data_on_uninstall', this.checked)" />
									<span class="msh-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>

			</div>

			<div class="msh-settings-footer" style="margin-top:48px; padding-top: 32px; border-top: 1px solid var(--msh-border); display:flex; justify-content:flex-end;">
				<?php submit_button( __( 'Save All Settings', 'my-site-hand' ), 'msh-btn msh-btn--primary', 'submit', false ); ?>
			</div>
			</form>
		</div>

		<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
	</div>
</div>
