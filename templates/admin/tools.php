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

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$my_site_hand_table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'mysitehand_audit_log' ) );

$my_site_hand_ability_count = count( $my_site_hand_registry->get_all() );

$my_site_hand_checks = [
	[
		'name'  => __( 'PHP version', 'my-site-hand' ),
		'value' => PHP_VERSION,
		'mono'  => true,
		'pass'  => version_compare( PHP_VERSION, '8.1', '>=' ),
		'fail'  => 'error',
		'ok'    => __( 'Pass', 'my-site-hand' ),
		'bad'   => __( 'Critical', 'my-site-hand' ),
		'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
	],
	[
		'name'  => __( 'WordPress version', 'my-site-hand' ),
		'value' => get_bloginfo( 'version' ),
		'mono'  => true,
		'pass'  => version_compare( get_bloginfo( 'version' ), '6.0', '>=' ),
		'fail'  => 'warning',
		'ok'    => __( 'Pass', 'my-site-hand' ),
		'bad'   => __( 'Warn', 'my-site-hand' ),
		'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><path d="M4 12h16"></path></svg>',
	],
	[
		'name'  => __( 'HTTPS', 'my-site-hand' ),
		'value' => is_ssl() ? __( 'Encrypted', 'my-site-hand' ) : __( 'Unsecured', 'my-site-hand' ),
		'mono'  => false,
		'pass'  => is_ssl(),
		'fail'  => 'warning',
		'ok'    => __( 'Pass', 'my-site-hand' ),
		'bad'   => __( 'Warn', 'my-site-hand' ),
		'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="11" width="18" height="11"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
	],
	[
		'name'  => __( 'Abilities registry', 'my-site-hand' ),
		'value' => sprintf(
			/* translators: %d: number of registered abilities */
			__( '%d registered', 'my-site-hand' ),
			$my_site_hand_ability_count
		),
		'mono'  => false,
		'pass'  => $my_site_hand_ability_count > 0,
		'fail'  => 'error',
		'ok'    => __( 'Pass', 'my-site-hand' ),
		'bad'   => __( 'Empty', 'my-site-hand' ),
		'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
	],
	[
		'name'  => __( 'Database schema', 'my-site-hand' ),
		'value' => $my_site_hand_table_exists ? __( 'Synchronized', 'my-site-hand' ) : __( 'Incomplete', 'my-site-hand' ),
		'mono'  => false,
		'pass'  => $my_site_hand_table_exists,
		'fail'  => 'error',
		'ok'    => __( 'Pass', 'my-site-hand' ),
		'bad'   => __( 'Error', 'my-site-hand' ),
		'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"></path></svg>',
	],
];

$my_site_hand_failing = 0;
foreach ( $my_site_hand_checks as $my_site_hand_check ) {
	if ( ! $my_site_hand_check['pass'] ) {
		$my_site_hand_failing++;
	}
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
	'path' => [ 'd' => [] ],
	'rect' => [
		'x' => [],
		'y' => [],
		'width' => [],
		'height' => [],
		'rx' => [],
		'ry' => [],
	],
	'polyline' => [ 'points' => [] ],
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
	'polygon' => [ 'points' => [] ],
];
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-body-grid">

				<!-- On this page -->
				<nav class="msh-index" aria-label="<?php esc_attr_e( 'On this page', 'my-site-hand' ); ?>">
					<p class="msh-index-label"><?php esc_html_e( 'On this page', 'my-site-hand' ); ?></p>
					<a class="msh-index-link msh-index-link--active" href="#msh-tools-env"><span class="msh-index-no">01</span><?php esc_html_e( 'Environment', 'my-site-hand' ); ?><span class="msh-index-ct"><?php echo esc_html( ( count( $my_site_hand_checks ) - $my_site_hand_failing ) . '/' . count( $my_site_hand_checks ) ); ?></span></a>
					<a class="msh-index-link" href="#msh-tools-tests"><span class="msh-index-no">02</span><?php esc_html_e( 'Connection tests', 'my-site-hand' ); ?></a>
					<a class="msh-index-link" href="#msh-tools-recovery"><span class="msh-index-no">03</span><?php esc_html_e( 'Recovery', 'my-site-hand' ); ?></a>
					<a class="msh-index-link" href="#msh-tools-about"><span class="msh-index-no">04</span><?php esc_html_e( 'About', 'my-site-hand' ); ?></a>
					<div class="msh-index-foot">
						<p><?php esc_html_e( 'Every test here runs on your own server. Nothing is sent anywhere.', 'my-site-hand' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand-documentation' ) ); ?>"><?php esc_html_e( 'Read the guide', 'my-site-hand' ); ?></a>
					</div>
				</nav>

				<div class="msh-body-main">

					<div class="msh-page-head">
						<div class="msh-page-head-info">
							<p class="msh-eyebrow"><i></i><?php esc_html_e( 'Diagnostics', 'my-site-hand' ); ?></p>
							<h2 class="msh-page-title"><?php esc_html_e( 'About & info', 'my-site-hand' ); ?></h2>
							<p class="msh-page-desc"><?php
							echo 0 === $my_site_hand_failing
								? esc_html__( 'Every check passes. Health checks, connection tests, and repair tools live here.', 'my-site-hand' )
								: esc_html(
									sprintf(
										/* translators: %d: number of failing checks */
										_n( '%d check needs your attention. Details below.', '%d checks need your attention. Details below.', (int) $my_site_hand_failing, 'my-site-hand' ),
										(int) $my_site_hand_failing
									)
								);
							?></p>
						</div>
						<div class="msh-page-head-actions">
							<span class="msh-sheet-tag <?php echo 0 === $my_site_hand_failing ? 'msh-sheet-tag--ok' : ''; ?>" style="border: none; padding: 11px 17px;">
								<?php echo esc_html( 'v' . MYSITEHAND_VERSION ); ?>
							</span>
						</div>
					</div>

					<!-- 01 Environment -->
					<div class="msh-card" id="msh-tools-env">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">01</span>
								<div>
									<h3><?php esc_html_e( 'Environment', 'my-site-hand' ); ?></h3>
									<p><?php esc_html_e( 'What this server gives the plugin to work with.', 'my-site-hand' ); ?></p>
								</div>
							</div>
							<span class="msh-sheet-tag <?php echo 0 === $my_site_hand_failing ? 'msh-sheet-tag--ok' : ''; ?>"><?php
							printf(
								/* translators: 1: number of passing checks, 2: total checks */
								esc_html__( '%1$d of %2$d pass', 'my-site-hand' ),
								(int) ( count( $my_site_hand_checks ) - $my_site_hand_failing ),
								(int) count( $my_site_hand_checks )
							); ?></span>
						</div>

						<div class="msh-check-list">
							<?php foreach ( $my_site_hand_checks as $my_site_hand_check ) : ?>
								<div class="msh-check-row">
									<span class="msh-check-icon"><?php echo wp_kses( $my_site_hand_check['icon'], $my_site_hand_svg_allowed ); ?></span>
									<span class="msh-check-name"><?php echo esc_html( $my_site_hand_check['name'] ); ?></span>
									<span class="msh-check-result">
										<span class="<?php echo $my_site_hand_check['mono'] ? 'msh-check-value' : 'msh-table-dim'; ?>"><?php echo esc_html( $my_site_hand_check['value'] ); ?></span>
										<span class="msh-badge msh-badge--<?php echo esc_attr( $my_site_hand_check['pass'] ? 'success' : $my_site_hand_check['fail'] ); ?>">
											<?php echo esc_html( $my_site_hand_check['pass'] ? $my_site_hand_check['ok'] : $my_site_hand_check['bad'] ); ?>
										</span>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- 02 Connection tests -->
					<div class="msh-card" id="msh-tools-tests">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">02</span>
								<div>
									<h3><?php esc_html_e( 'Connection tests', 'my-site-hand' ); ?></h3>
									<p><?php esc_html_e( 'Run these first when a client cannot reach the site.', 'my-site-hand' ); ?></p>
								</div>
							</div>
						</div>

						<div class="msh-setting-item">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e( 'REST API loopback', 'my-site-hand' ); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e( 'Checks that your server can reach its own MCP endpoint. A failure here is almost always host routing, not the plugin.', 'my-site-hand' ); ?></span>
							</div>
							<div class="msh-setting-control">
								<button type="button" class="msh-btn msh-btn--primary" onclick="msh.runDiagnostic('loopback')">
									<?php esc_html_e( 'Run test', 'my-site-hand' ); ?>
								</button>
							</div>
						</div>

						<div class="msh-setting-item">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e( 'MCP discovery', 'my-site-hand' ); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e( 'Simulates a client handshake so you can verify the abilities are registered and the metadata is well formed.', 'my-site-hand' ); ?></span>
							</div>
							<div class="msh-setting-control">
								<button type="button" class="msh-btn" onclick="msh.runDiagnostic('discovery')">
									<?php esc_html_e( 'Simulate', 'my-site-hand' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- 03 Recovery -->
					<div class="msh-card" id="msh-tools-recovery">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">03</span>
								<div>
									<h3><?php esc_html_e( 'Recovery', 'my-site-hand' ); ?></h3>
									<p><?php esc_html_e( 'Safe to run at any time. Neither one deletes your data.', 'my-site-hand' ); ?></p>
								</div>
							</div>
						</div>

						<div class="msh-setting-item">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e( 'Regenerate the registry', 'my-site-hand' ); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e( 'Clears the ability cache and re-scans every active module. Use this after enabling a module that has not appeared yet.', 'my-site-hand' ); ?></span>
							</div>
							<div class="msh-setting-control">
								<button type="button" class="msh-btn" onclick="msh.fixAction('regen_cache')">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
									<?php esc_html_e( 'Regenerate', 'my-site-hand' ); ?>
								</button>
							</div>
						</div>

						<div class="msh-setting-item">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e( 'Repair tables', 'my-site-hand' ); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e( 'Verifies the plugin tables and re-creates anything missing. Existing rows are left alone.', 'my-site-hand' ); ?></span>
							</div>
							<div class="msh-setting-control">
								<button type="button" class="msh-btn" onclick="msh.fixAction('repair_tables')">
									<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
									<?php esc_html_e( 'Repair', 'my-site-hand' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- 04 About -->
					<div class="msh-card" id="msh-tools-about">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">04</span>
								<div>
									<h3><?php esc_html_e( 'About', 'my-site-hand' ); ?></h3>
									<p><?php esc_html_e( 'What this plugin is, and where to take a question.', 'my-site-hand' ); ?></p>
								</div>
							</div>
							<span class="msh-sheet-tag"><?php echo esc_html( 'v' . MYSITEHAND_VERSION ); ?></span>
						</div>

						<div class="msh-setting-item">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e( 'My Site Hand', 'my-site-hand' ); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e( 'An MCP server for WordPress. It exposes only the abilities you switch on, only to tokens you issue, and writes down every call it serves.', 'my-site-hand' ); ?></span>
							</div>
							<div class="msh-setting-control">
								<a href="https://github.com/taninrahman21/my-site-hand" target="_blank" rel="noopener noreferrer" class="msh-btn"><?php esc_html_e( 'Source on GitHub', 'my-site-hand' ); ?></a>
							</div>
						</div>

						<div class="msh-setting-item">
							<div class="msh-setting-info">
								<span class="msh-setting-title"><?php esc_html_e( 'Missing something?', 'my-site-hand' ); ?></span>
								<span class="msh-setting-desc"><?php esc_html_e( 'Requests are prioritized by how often they come up. Send yours and it goes straight to the developer.', 'my-site-hand' ); ?></span>
							</div>
							<div class="msh-setting-control">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand-feature-request' ) ); ?>" class="msh-btn msh-btn--primary"><?php esc_html_e( 'Suggest a feature', 'my-site-hand' ); ?></a>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
