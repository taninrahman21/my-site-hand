<?php
/**
 * Admin page header partial: brand row + tab bar.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$my_site_hand_current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'my-site-hand';

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

// Live counts for the tab bar.
$my_site_hand_shell_plugin  = \MySiteHand\Plugin::get_instance();
$my_site_hand_shell_all     = $my_site_hand_shell_plugin->get_abilities_registry()->get_all();
$my_site_hand_shell_off     = (array) get_option('mysitehand_disabled_abilities', []);
$my_site_hand_shell_total   = count($my_site_hand_shell_all);
$my_site_hand_shell_on      = $my_site_hand_shell_total - count(array_intersect(array_keys($my_site_hand_shell_all), $my_site_hand_shell_off));
$my_site_hand_shell_tokens  = count(
	array_filter(
		$my_site_hand_shell_plugin->get_auth_manager()->list_tokens(0),
		static function ($my_site_hand_t) {
			return (int) $my_site_hand_t['is_active'] === 1;
		}
	)
);
$my_site_hand_shell_serving  = (bool) get_option('mysitehand_enabled', true);
$my_site_hand_shell_endpoint = rest_url('my-site-hand/v1/mcp/streamable');

$my_site_hand_shell_health = \MySiteHand\Plugin::get_instance()->get_site_health_scanner()->get_latest_report();

$my_site_hand_primary_tabs = [
	'my-site-hand-health' => [
		'label' => __('Site Health', 'my-site-hand'),
		'count' => null !== $my_site_hand_shell_health ? (string) (int) $my_site_hand_shell_health['score'] : '',
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
	],
	'my-site-hand' => [
		'label' => __('Dashboard', 'my-site-hand'),
		'count' => '',
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
	],
	'my-site-hand-abilities' => [
		'label' => __('Abilities', 'my-site-hand'),
		'count' => $my_site_hand_shell_on . '/' . $my_site_hand_shell_total,
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
	],
	'my-site-hand-tokens' => [
		'label' => __('API Tokens', 'my-site-hand'),
		'count' => (string) $my_site_hand_shell_tokens,
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="11" width="18" height="11"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
	],
	'my-site-hand-audit' => [
		'label' => __('Audit Log', 'my-site-hand'),
		'count' => '',
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line></svg>',
	],
	'my-site-hand-settings' => [
		'label' => __('Settings', 'my-site-hand'),
		'count' => '',
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15H4.5a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 6.26 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 11 4.6V4.5a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 2.82 1.17l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 11h.1a2 2 0 0 1 0 4h-.1z"></path></svg>',
	],
];

$my_site_hand_secondary_tabs = [
	'my-site-hand-documentation'  => __('How to use', 'my-site-hand'),
	'my-site-hand-tools'          => __('About & Info', 'my-site-hand'),
	'my-site-hand-feature-request' => __('Suggest a Feature', 'my-site-hand'),
];
?>

<!-- Brand row -->
<header class="msh-brand-header">
	<div class="msh-brand-header-left">
		<img src="<?php echo esc_url(MYSITEHAND_URL . 'assets/logo.png'); ?>" alt="" class="msh-brand-logo">
		<div class="msh-brand-info">
			<div class="msh-brand-title-row">
				<span class="msh-brand-name"><?php esc_html_e('My Site Hand', 'my-site-hand'); ?></span>
				<span class="msh-brand-version"><?php echo esc_html('v' . MYSITEHAND_VERSION); ?></span>
			</div>
			<p class="msh-brand-tagline"><?php esc_html_e('WordPress over MCP', 'my-site-hand'); ?></p>
		</div>
	</div>

	<div class="msh-brand-endpoint">
		<span class="msh-ep-label"><?php esc_html_e('Endpoint', 'my-site-hand'); ?></span>
		<code class="msh-endpoint-code" id="msh-shell-endpoint"><?php echo esc_html($my_site_hand_shell_endpoint); ?></code>
		<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-shell-endpoint')">
			<?php esc_html_e('Copy', 'my-site-hand'); ?>
		</button>
	</div>

	<div class="msh-brand-status <?php echo $my_site_hand_shell_serving ? '' : 'msh-brand-status--off'; ?>">
		<span class="msh-status-dot"></span>
		<?php echo $my_site_hand_shell_serving ? esc_html__('Serving', 'my-site-hand') : esc_html__('Stopped', 'my-site-hand'); ?>
	</div>

	<div class="msh-brand-header-right">
		<a href="https://github.com/taninrahman21/my-site-hand#readme" target="_blank" rel="noopener noreferrer"
			class="msh-header-help">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
				stroke-linecap="square">
				<circle cx="12" cy="12" r="10"></circle>
				<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
				<line x1="12" y1="17" x2="12.01" y2="17"></line>
			</svg>
			<?php esc_html_e('Help', 'my-site-hand'); ?>
		</a>
	</div>
</header>

<!-- Tab bar -->
<nav class="msh-nav-tabs" aria-label="<?php esc_attr_e('My Site Hand sections', 'my-site-hand'); ?>">
	<?php foreach ($my_site_hand_primary_tabs as $my_site_hand_slug => $my_site_hand_tab):
		$my_site_hand_is_current = $my_site_hand_current_page === $my_site_hand_slug;
		?>
		<a href="<?php echo esc_url(admin_url('admin.php?page=' . $my_site_hand_slug)); ?>"
			class="msh-nav-tab <?php echo $my_site_hand_is_current ? 'msh-nav-tab--active' : ''; ?>"
			<?php echo $my_site_hand_is_current ? 'aria-current="page"' : ''; ?>>
			<?php echo wp_kses($my_site_hand_tab['icon'], $my_site_hand_svg_allowed); ?>
			<?php echo esc_html($my_site_hand_tab['label']); ?>
			<?php if ('' !== $my_site_hand_tab['count']): ?>
				<span class="msh-nav-tab-ct"><?php echo esc_html($my_site_hand_tab['count']); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>

	<span class="msh-nav-spacer"></span>

	<?php foreach ($my_site_hand_secondary_tabs as $my_site_hand_slug => $my_site_hand_label):
		$my_site_hand_is_current = $my_site_hand_current_page === $my_site_hand_slug;
		?>
		<a href="<?php echo esc_url(admin_url('admin.php?page=' . $my_site_hand_slug)); ?>"
			class="msh-nav-tab msh-nav-tab--sec <?php echo $my_site_hand_is_current ? 'msh-nav-tab--active' : ''; ?>"
			<?php echo $my_site_hand_is_current ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html($my_site_hand_label); ?>
		</a>
	<?php endforeach; ?>
</nav>

<!-- Layout body wrapper (closed in footer.php) -->
<div class="msh-layout-body">
