<?php
/**
 * Admin page header + sidebar partial.
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
	'polygon' => ['points' => []],
];

$my_site_hand_general_items = [
	'my-site-hand' => [
		'label' => __('Dashboard', 'my-site-hand'),
		'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
	],
	'my-site-hand-abilities' => [
		'label' => __('Abilities', 'my-site-hand'),
		'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>',
	],
	'my-site-hand-tokens' => [
		'label' => __('API Tokens', 'my-site-hand'),
		'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
	],
	'my-site-hand-audit' => [
		'label' => __('Audit Log', 'my-site-hand'),
		'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
	],
	'my-site-hand-settings' => [
		'label' => __('Settings', 'my-site-hand'),
		'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
	],
];

$my_site_hand_support_items = [
	'documentation' => [
		'label' => __('Documentation', 'my-site-hand'),
		'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>',
		'url' => 'https://github.com/taninrahman21/my-site-hand#readme',
		'external' => true,
	],
	'my-site-hand-tools' => [
		'label' => __('About & Info', 'my-site-hand'),
		'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
	],
];
?>

<!-- Header Section: Full Width -->
<header class="msh-brand-header">
	<div class="msh-brand-header-left">
		<button type="button" class="msh-sidebar-toggle" id="msh-sidebar-toggle" aria-label="<?php esc_attr_e('Toggle sidebar', 'my-site-hand'); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
		</button>
		<img src="<?php echo esc_url(MYSITEHAND_URL . 'assets/logo.png'); ?>" alt="My Site Hand" class="msh-brand-logo">
		<div class="msh-brand-info">
			<div class="msh-brand-title-row">
				<span class="msh-brand-name"><?php esc_html_e('My Site Hand', 'my-site-hand'); ?></span>
				<span class="msh-brand-version"><?php echo esc_html('v' . MYSITEHAND_VERSION); ?></span>
			</div>
			<p class="msh-brand-tagline"><?php esc_html_e('Expose your WordPress site to AI clients via the Model Context Protocol.', 'my-site-hand'); ?></p>
		</div>
	</div>
	<div class="msh-brand-header-right">
		<a href="https://github.com/taninrahman21/my-site-hand#readme" target="_blank" rel="noopener noreferrer" class="msh-header-help">
			<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
			<?php esc_html_e('Help & Support', 'my-site-hand'); ?>
		</a>
	</div>
</header>

<!-- Two-Column Layout Body wrapper (closed in footer.php) -->
<div class="msh-layout-body">
	<!-- Left Sidebar -->
	<aside class="msh-sidebar">
		<nav class="msh-sidebar-nav">
			<div class="msh-nav-group">
				<span class="msh-nav-group-label"><?php esc_html_e('GENERAL', 'my-site-hand'); ?></span>
				<?php foreach ($my_site_hand_general_items as $my_site_hand_page => $my_site_hand_item): ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=' . $my_site_hand_page)); ?>"
						class="msh-sidebar-link <?php echo $my_site_hand_current_page === $my_site_hand_page ? 'msh-sidebar-link--active' : ''; ?>">
						<span class="msh-sidebar-icon"><?php echo wp_kses($my_site_hand_item['icon'], $my_site_hand_svg_allowed); ?></span>
						<span class="msh-sidebar-label"><?php echo esc_html($my_site_hand_item['label']); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="msh-nav-group">
				<span class="msh-nav-group-label"><?php esc_html_e('SUPPORT', 'my-site-hand'); ?></span>
				<?php foreach ($my_site_hand_support_items as $my_site_hand_page => $my_site_hand_item):
					$my_site_hand_is_external = !empty($my_site_hand_item['external']);
					$my_site_hand_href = $my_site_hand_is_external
						? $my_site_hand_item['url']
						: admin_url('admin.php?page=' . $my_site_hand_page);
					$my_site_hand_is_active = !$my_site_hand_is_external && $my_site_hand_current_page === $my_site_hand_page;
				?>
					<a href="<?php echo esc_url($my_site_hand_href); ?>"
						class="msh-sidebar-link <?php echo $my_site_hand_is_active ? 'msh-sidebar-link--active' : ''; ?>"
						<?php echo $my_site_hand_is_external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
						<span class="msh-sidebar-icon"><?php echo wp_kses($my_site_hand_item['icon'], $my_site_hand_svg_allowed); ?></span>
						<span class="msh-sidebar-label"><?php echo esc_html($my_site_hand_item['label']); ?></span>
						<?php if ($my_site_hand_is_external): ?>
							<svg class="msh-external-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</nav>
	</aside>