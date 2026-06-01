<?php
/**
 * Admin page sidebar partial.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$my_site_hand_current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'my-site-hand';

$my_site_hand_nav_items = [
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
?>
<header class="msh-header">
	<div class="msh-header-brand">
		<img src="<?php echo esc_url(MYSITEHAND_URL . 'assets/logo.png'); ?>" alt="My Site Hand" class="msh-header-logo">
		<div class="msh-header-title">
			<h1>My Site Hand</h1>
		</div>
	</div>

	<nav class="msh-nav">
		<?php foreach ($my_site_hand_nav_items as $my_site_hand_page => $my_site_hand_item): ?>
			<a href="<?php echo esc_url(admin_url('admin.php?page=' . $my_site_hand_page)); ?>"
				class="msh-nav-link <?php echo $my_site_hand_current_page === $my_site_hand_page ? 'msh-nav-link--active' : ''; ?>">
				<span class="msh-nav-icon"><?php echo wp_kses($my_site_hand_item['icon'], [
					'svg' => [
						'width' => [],
						'height' => [],
						'viewbox' => [],
						'fill' => [],
						'stroke' => [],
						'stroke-width' => [],
						'stroke-linecap' => [],
						'stroke-linejoin' => [],
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
				]); ?></span>
				<span class="msh-nav-label"><?php echo esc_html($my_site_hand_item['label']); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="msh-header-actions">
		<a href="https://github.com/taninrahman21/my-site-hand#readme" target="_blank"
			class="msh-btn msh-btn--primary msh-btn--sm"
			style="background: var(--msh-primary); color: #fff; border: none; padding: 10px 20px; border-radius: 100px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
				stroke-linecap="round" stroke-linejoin="round">
				<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
				<path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
			</svg>
			<?php echo esc_html__('Documentation', 'my-site-hand'); ?>
		</a>
	</div>
</header>