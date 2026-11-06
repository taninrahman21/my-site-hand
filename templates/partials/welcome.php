<?php
/**
 * Welcome panel: offers "Build a site" or "Check my site".
 *
 * Shown on the plugin's main screens after activation (and once after the
 * 1.4.0 upgrade) until the user dismisses it. The automatic first health scan
 * still runs on its own; this only points at the two things the plugin does.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

if (!get_option('mysitehand_show_welcome') || get_user_meta(get_current_user_id(), 'mysitehand_welcome_dismissed', true)) {
	return;
}
?>
<section class="msh-welcome" id="msh-welcome" aria-labelledby="msh-welcome-title">
	<div class="msh-welcome-text">
		<p class="msh-eyebrow"><i></i><?php esc_html_e('Welcome', 'my-site-hand'); ?></p>
		<h2 id="msh-welcome-title"><?php esc_html_e('What would you like to do first?', 'my-site-hand'); ?></h2>
		<p><?php esc_html_e('Nothing changes on your site until you say yes. Everything works without an AI key.', 'my-site-hand'); ?></p>
	</div>
	<div class="msh-welcome-options">
		<a class="msh-welcome-option" href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-builder&view=gallery')); ?>">
			<strong><?php esc_html_e('Build a site', 'my-site-hand'); ?></strong>
			<span><?php esc_html_e('Start from a ready-made starter site, make it yours, preview it, then apply.', 'my-site-hand'); ?></span>
		</a>
		<a class="msh-welcome-option" href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-health&msh-autoscan=1')); ?>">
			<strong><?php esc_html_e('Check my site', 'my-site-hand'); ?></strong>
			<span><?php esc_html_e('Find broken links, missing alt text and SEO gaps in about 30 seconds.', 'my-site-hand'); ?></span>
		</a>
	</div>
	<button type="button" class="msh-welcome-close" id="msh-welcome-close" aria-label="<?php esc_attr_e('Dismiss welcome', 'my-site-hand'); ?>">&times;</button>
</section>
