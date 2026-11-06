<?php
/**
 * Site Builder page template.
 *
 * The gallery and editor are drawn by assets/js/site-builder.js from the
 * mshBuilder data. Everything on this screen works without an AI key.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_block_theme = wp_is_block_theme();
?>
<div class="msh-wrap">

	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container msh-sb">

			<?php require MYSITEHAND_PATH . 'templates/partials/welcome.php'; ?>

			<?php if (!$my_site_hand_block_theme): ?>
				<div class="msh-sb-notice" id="msh-sb-theme-notice">
					<div>
						<p class="msh-sb-notice-title"><?php esc_html_e('Starter sites need a block theme', 'my-site-hand'); ?></p>
						<p>
							<?php
							printf(
								/* translators: %s: name of the active theme */
								esc_html__('Your active theme, %s, is a classic theme. Starter sites are built from WordPress blocks and use the Site Editor for the header, footer and styles, which only block themes support.', 'my-site-hand'),
								'<strong>' . esc_html(wp_get_theme()->get('Name')) . '</strong>'
							);
							?>
						</p>
						<p><?php esc_html_e('You can switch to Twenty Twenty-Five, the free default theme from WordPress.org. Your posts and pages stay as they are, and you can switch back under Appearance > Themes at any time.', 'my-site-hand'); ?></p>
					</div>
					<?php if (current_user_can('switch_themes')): ?>
						<button type="button" class="msh-btn msh-btn--primary" id="msh-sb-install-theme">
							<?php echo wp_get_theme('twentytwentyfive')->exists()
								? esc_html__('Activate Twenty Twenty-Five', 'my-site-hand')
								: esc_html__('Install and activate Twenty Twenty-Five', 'my-site-hand'); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div id="msh-sb-app" class="msh-sb-app" aria-live="polite">
				<p class="msh-sb-loading"><?php esc_html_e('Loading starter sites...', 'my-site-hand'); ?></p>
			</div>

			<noscript>
				<p><?php esc_html_e('The Site Builder needs JavaScript. Please enable it in your browser.', 'my-site-hand'); ?></p>
			</noscript>

		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
