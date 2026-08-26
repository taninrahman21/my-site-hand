<?php
/**
 * Feature request page template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_default_email = get_option('admin_email');
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">

			<div class="msh-page-head" style="margin-bottom: 26px;">
				<div class="msh-page-head-info">
					<p class="msh-eyebrow"><i></i><?php esc_html_e('Roadmap', 'my-site-hand'); ?></p>
					<h2 class="msh-page-title"><?php esc_html_e('Suggest a feature', 'my-site-hand'); ?></h2>
					<p class="msh-page-desc"><?php esc_html_e('Tell the developer what is missing. Requests are prioritized by how often they come up, so a specific use case counts for more than a vague wish.', 'my-site-hand'); ?></p>
				</div>
				<div class="msh-page-head-actions">
					<a href="https://github.com/taninrahman21/my-site-hand/issues" target="_blank" rel="noopener noreferrer" class="msh-btn"><?php esc_html_e('Open an issue instead', 'my-site-hand'); ?></a>
				</div>
			</div>

			<?php if (!empty($message)): ?>
				<div class="msh-feature-alert msh-feature-alert--<?php echo esc_attr($message_type); ?>" style="margin-bottom: 26px;">
					<?php if ('success' === $message_type): ?>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square"><polyline points="20 6 9 17 4 12"></polyline></svg>
					<?php else: ?>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					<?php endif; ?>
					<span><?php echo esc_html($message); ?></span>
				</div>
			<?php endif; ?>

			<div class="msh-feature-layout">
				<!-- Form -->
				<div class="msh-feature-main">
					<div class="msh-feature-card">
						<div class="msh-feature-card-header">
							<h3><?php esc_html_e('Your request', 'my-site-hand'); ?></h3>
						</div>
						<div class="msh-feature-card-body">
							<form method="POST" action="">
								<?php wp_nonce_field('msh_submit_feature', 'msh_feature_nonce'); ?>

								<div class="msh-feature-form-group">
									<label class="msh-feature-label" for="feature_title">
										<?php esc_html_e('Title', 'my-site-hand'); ?>
										<span style="color: var(--msh-r);">*</span>
									</label>
									<input type="text" id="feature_title" name="feature_title" class="msh-feature-input"
										placeholder="<?php esc_attr_e('e.g. Add an ability for RankMath meta fields', 'my-site-hand'); ?>" required />
								</div>

								<div class="msh-feature-form-group">
									<label class="msh-feature-label" for="feature_description">
										<?php esc_html_e('What it should do, and why', 'my-site-hand'); ?>
										<span style="color: var(--msh-r);">*</span>
									</label>
									<textarea id="feature_description" name="feature_description" class="msh-feature-textarea"
										placeholder="<?php esc_attr_e('Describe the behaviour you want, the prompt you would type into Claude or Cursor, and what you are doing by hand today.', 'my-site-hand'); ?>" required></textarea>
									<p class="msh-hint"><?php esc_html_e('A concrete example of the request you would make is the most useful thing you can include.', 'my-site-hand'); ?></p>
								</div>

								<div class="msh-feature-form-group">
									<label class="msh-feature-label" for="feature_email">
										<?php esc_html_e('Your email', 'my-site-hand'); ?>
										<span style="font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--msh-n5);">(<?php esc_html_e('optional', 'my-site-hand'); ?>)</span>
									</label>
									<input type="email" id="feature_email" name="feature_email" class="msh-feature-input"
										value="<?php echo esc_attr($my_site_hand_default_email); ?>" />
									<p class="msh-hint"><?php esc_html_e('Only used to reply to you about this request.', 'my-site-hand'); ?></p>
								</div>

								<div class="msh-feature-form-group" style="margin-top: 26px;">
									<button type="submit" class="msh-btn msh-btn--primary">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="square"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
										<?php esc_html_e('Send request', 'my-site-hand'); ?>
									</button>
								</div>
							</form>
						</div>
					</div>
				</div>

				<!-- Sidebar -->
				<div class="msh-feature-sidebar">
					<div class="msh-feature-card">
						<div class="msh-feature-card-header">
							<h3><?php esc_html_e('What happens next', 'my-site-hand'); ?></h3>
						</div>
						<div class="msh-feature-card-body" style="padding: 0 24px 22px;">
							<ul class="msh-sidebar-list">
								<li class="msh-sidebar-list-item">
									<strong><?php esc_html_e('Straight to the developer', 'my-site-hand'); ?></strong> —
									<?php esc_html_e('Your message is emailed to builtbytanin@gmail.com. No tracker in between.', 'my-site-hand'); ?>
								</li>
								<li class="msh-sidebar-list-item">
									<strong><?php esc_html_e('Prioritized by demand', 'my-site-hand'); ?></strong> —
									<?php esc_html_e('Requests that several people ask for get built first.', 'my-site-hand'); ?>
								</li>
								<li class="msh-sidebar-list-item">
									<strong><?php esc_html_e('Only what you typed', 'my-site-hand'); ?></strong> —
									<?php esc_html_e('The email carries your text plus your site URL and PHP version. No tokens, no content, no log entries.', 'my-site-hand'); ?>
								</li>
							</ul>
						</div>
					</div>

					<div class="msh-feature-card">
						<div class="msh-side-note">
							<div class="msh-side-note-icon">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
							</div>
							<h4><?php esc_html_e('Rather use GitHub?', 'my-site-hand'); ?></h4>
							<p><?php esc_html_e('Read the source, follow progress, or send a pull request on the project page.', 'my-site-hand'); ?></p>
							<a href="https://github.com/taninrahman21/my-site-hand" target="_blank" rel="noopener noreferrer" class="msh-btn msh-btn--full-width"><?php esc_html_e('Visit GitHub', 'my-site-hand'); ?></a>
						</div>
					</div>
				</div>
			</div>

		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
