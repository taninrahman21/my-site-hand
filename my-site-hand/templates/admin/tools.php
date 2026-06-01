<?php
/**
 * Tools page template.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;

$my_site_hand_plugin   = \MySiteHand\Plugin::get_instance();
$my_site_hand_registry = $my_site_hand_plugin->get_abilities_registry();
$my_site_hand_nonce    = wp_create_nonce( 'my_site_hand_admin' );
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-page-header">
				<h2><?php echo esc_html__( 'Diagnostics & Tools', 'my-site-hand' ); ?></h2>
				<p class="msh-page-desc"><?php echo esc_html__( 'System health checks, connection tests, and troubleshooting utilities for your MCP server.', 'my-site-hand' ); ?></p>
			</div>

			<div class="msh-tools-grid" style="display: grid; grid-template-columns: 1fr; gap: 32px;">

				<!-- System Environment -->
				<div class="msh-card">
					<div class="msh-card-header">
						<h3><?php echo esc_html__( 'System Environment', 'my-site-hand' ); ?></h3>
					</div>
					<div class="msh-card-body msh-card--no-pad">
						<div class="msh-check-list">
							<div class="msh-check-row" style="display:flex; justify-content:space-between; align-items: center; padding:20px 24px; border-bottom:1px solid var(--msh-border);">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--msh-bg); display: flex; align-items: center; justify-content: center; color: var(--msh-text-muted);">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
									</div>
									<span style="font-weight:600; font-size: 14px;"><?php echo esc_html__( 'PHP Version', 'my-site-hand' ); ?></span>
								</div>
								<div style="display: flex; align-items: center; gap: 12px;">
									<code style="background: var(--msh-bg); padding: 4px 8px; border-radius: 4px;"><?php echo esc_html( PHP_VERSION ); ?></code>
									<?php $my_site_hand_php_pass = version_compare( PHP_VERSION, '8.1', '>=' ); ?>
									<span class="msh-badge msh-badge--<?php echo $my_site_hand_php_pass ? 'success' : 'error'; ?>" style="min-width: 80px; text-align: center;">
										<?php echo $my_site_hand_php_pass ? esc_html__( 'Pass', 'my-site-hand' ) : esc_html__( 'Critical', 'my-site-hand' ); ?>
									</span>
								</div>
							</div>

							<div class="msh-check-row" style="display:flex; justify-content:space-between; align-items: center; padding:20px 24px; border-bottom:1px solid var(--msh-border);">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--msh-bg); display: flex; align-items: center; justify-content: center; color: var(--msh-text-muted);">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
									</div>
									<span style="font-weight:600; font-size: 14px;"><?php echo esc_html__( 'WordPress Version', 'my-site-hand' ); ?></span>
								</div>
								<div style="display: flex; align-items: center; gap: 12px;">
									<code style="background: var(--msh-bg); padding: 4px 8px; border-radius: 4px;"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code>
									<?php $my_site_hand_wp_pass = version_compare( get_bloginfo( 'version' ), '6.0', '>=' ); ?>
									<span class="msh-badge msh-badge--<?php echo $my_site_hand_wp_pass ? 'success' : 'warning'; ?>" style="min-width: 80px; text-align: center;">
										<?php echo $my_site_hand_wp_pass ? esc_html__( 'Pass', 'my-site-hand' ) : esc_html__( 'Warn', 'my-site-hand' ); ?>
									</span>
								</div>
							</div>

							<div class="msh-check-row" style="display:flex; justify-content:space-between; align-items: center; padding:20px 24px; border-bottom:1px solid var(--msh-border);">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--msh-bg); display: flex; align-items: center; justify-content: center; color: var(--msh-text-muted);">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
									</div>
									<span style="font-weight:600; font-size: 14px;"><?php echo esc_html__( 'HTTPS Status', 'my-site-hand' ); ?></span>
								</div>
								<div style="display: flex; align-items: center; gap: 12px;">
									<span style="font-size: 13px; color: var(--msh-text-secondary);"><?php echo is_ssl() ? esc_html__( 'Encrypted', 'my-site-hand' ) : esc_html__( 'Unsecured', 'my-site-hand' ); ?></span>
									<span class="msh-badge msh-badge--<?php echo is_ssl() ? 'success' : 'warning'; ?>" style="min-width: 80px; text-align: center;">
										<?php echo is_ssl() ? esc_html__( 'Pass', 'my-site-hand' ) : esc_html__( 'Warn', 'my-site-hand' ); ?>
									</span>
								</div>
							</div>

							<div class="msh-check-row" style="display:flex; justify-content:space-between; align-items: center; padding:20px 24px; border-bottom:1px solid var(--msh-border);">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--msh-bg); display: flex; align-items: center; justify-content: center; color: var(--msh-text-muted);">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
									</div>
									<span style="font-weight:600; font-size: 14px;"><?php echo esc_html__( 'Abilities Registry', 'my-site-hand' ); ?></span>
								</div>
								<div style="display: flex; align-items: center; gap: 12px;">
									<span style="font-size: 13px; color: var(--msh-text-secondary);"><?php
										printf(
											/* translators: %d: number of registered abilities */
											esc_html__( '%d registered', 'my-site-hand' ),
											count( $my_site_hand_registry->get_all() )
										); ?></span>
									<?php $my_site_hand_reg_pass = count( $my_site_hand_registry->get_all() ) > 0; ?>
									<span class="msh-badge msh-badge--<?php echo $my_site_hand_reg_pass ? 'success' : 'error'; ?>" style="min-width: 80px; text-align: center;">
										<?php echo $my_site_hand_reg_pass ? esc_html__( 'Pass', 'my-site-hand' ) : esc_html__( 'Empty', 'my-site-hand' ); ?>
									</span>
								</div>
							</div>

							<div class="msh-check-row" style="display:flex; justify-content:space-between; align-items: center; padding:20px 24px;">
								<div style="display: flex; align-items: center; gap: 12px;">
									<div style="width: 32px; height: 32px; border-radius: 50%; background: var(--msh-bg); display: flex; align-items: center; justify-content: center; color: var(--msh-text-muted);">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
									</div>
									<span style="font-weight:600; font-size: 14px;"><?php echo esc_html__( 'Database Schema', 'my-site-hand' ); ?></span>
								</div>
								<div style="display: flex; align-items: center; gap: 12px;">
									<?php
									global $wpdb;
									// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$my_site_hand_table_exists = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->prefix . 'mysitehand_audit_log' ) );
									?>
									<span style="font-size: 13px; color: var(--msh-text-secondary);"><?php echo $my_site_hand_table_exists ? esc_html__( 'Synchronized', 'my-site-hand' ) : esc_html__( 'Incomplete', 'my-site-hand' ); ?></span>
									<span class="msh-badge msh-badge--<?php echo $my_site_hand_table_exists ? 'success' : 'error'; ?>" style="min-width: 80px; text-align: center;">
										<?php echo $my_site_hand_table_exists ? esc_html__( 'Pass', 'my-site-hand' ) : esc_html__( 'Error', 'my-site-hand' ); ?>
									</span>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Functional Tests -->
				<div style="display:grid; grid-template-columns: 1fr 1fr; gap:32px;">
					<div class="msh-card">
						<div class="msh-card-header">
							<h3><?php echo esc_html__( 'Connection Tests', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body" style="padding: 24px;">
							<div class="msh-test-box" style="margin-bottom: 24px;">
								<strong style="display: block; margin-bottom: 8px;"><?php echo esc_html__( 'REST API Loopback', 'my-site-hand' ); ?></strong>
								<p style="margin:0 0 16px; font-size:13px; color:var(--msh-text-secondary); line-height: 1.5;"><?php echo esc_html__( 'Validates that your server can communicate with its own MCP endpoints.', 'my-site-hand' ); ?></p>
								<button type="button" class="msh-btn msh-btn--primary msh-btn--sm" style="width: 100%;" onclick="msh.runDiagnostic('loopback')">
									<?php echo esc_html__( 'Run Loopback Test', 'my-site-hand' ); ?>
								</button>
							</div>
							<div class="msh-test-box">
								<strong style="display: block; margin-bottom: 8px;"><?php echo esc_html__( 'MCP Discovery', 'my-site-hand' ); ?></strong>
								<p style="margin:0 0 16px; font-size:13px; color:var(--msh-text-secondary); line-height: 1.5;"><?php echo esc_html__( 'Simulates a client handshake to verify tool registration and metadata.', 'my-site-hand' ); ?></p>
								<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" style="width: 100%;" onclick="msh.runDiagnostic('discovery')">
									<?php echo esc_html__( 'Simulate Discovery', 'my-site-hand' ); ?>
								</button>
							</div>
						</div>
					</div>

					<div class="msh-card">
						<div class="msh-card-header">
							<h3><?php echo esc_html__( 'System Recovery', 'my-site-hand' ); ?></h3>
						</div>
						<div class="msh-card-body" style="padding: 24px;">
							<div style="margin-bottom: 24px;">
								<strong style="display: block; margin-bottom: 4px;"><?php echo esc_html__( 'Ability Cache', 'my-site-hand' ); ?></strong>
								<p style="margin:0 0 16px; font-size:13px; color:var(--msh-text-secondary);"><?php echo esc_html__( 'Clear registry cache and force-scan all active modules.', 'my-site-hand' ); ?></p>
								<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" style="width: 100%;" onclick="msh.fixAction('regen_cache')">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
									<?php echo esc_html__( 'Regenerate Registry', 'my-site-hand' ); ?>
								</button>
							</div>
							<div>
								<strong style="display: block; margin-bottom: 4px;"><?php echo esc_html__( 'Database Repair', 'my-site-hand' ); ?></strong>
								<p style="margin:0 0 16px; font-size:13px; color:var(--msh-text-secondary);"><?php echo esc_html__( 'Verify and re-create any missing plugin tables or columns.', 'my-site-hand' ); ?></p>
								<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" style="width: 100%;" onclick="msh.fixAction('repair_tables')">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
									<?php echo esc_html__( 'Repair Tables', 'my-site-hand' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

			</div>
		</div>

		<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
	</div>
</div>
