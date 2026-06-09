<?php
/**
 * Feature request page template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_default_email = get_option('admin_email');
?>

<style>
.msh-feature-layout {
	display: flex;
	gap: 32px;
	align-items: flex-start;
	margin-top: 24px;
}

.msh-feature-main {
	flex: 1;
	min-width: 0;
}

.msh-feature-sidebar {
	width: 300px;
	flex-shrink: 0;
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.msh-feature-card {
	background: var(--msh-surface);
	border: 1px solid var(--msh-border);
	border-top: 3px solid var(--msh-primary);
	border-radius: var(--msh-radius);
	box-shadow: var(--msh-shadow-sm);
	overflow: hidden;
}

.msh-feature-card-header {
	padding: 20px 24px;
	border-bottom: 1px solid var(--msh-border);
	background: #ffffff;
}

.msh-feature-card-header h3 {
	margin: 0;
	font-size: 16px;
	font-weight: 700;
	color: var(--msh-secondary);
}

.msh-feature-card-body {
	padding: 24px;
}

.msh-feature-form-group {
	margin-bottom: 20px;
}

.msh-feature-form-group:last-child {
	margin-bottom: 0;
}

.msh-feature-label {
	display: block;
	font-size: 12px;
	font-weight: 700;
	color: var(--msh-text-secondary);
	margin-bottom: 8px;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.msh-feature-input,
.msh-feature-textarea {
	width: 100%;
	padding: 12px;
	border: 1px solid var(--msh-border);
	border-radius: var(--msh-radius-sm);
	font-family: var(--msh-font);
	font-size: 14px;
	color: var(--msh-text);
	background: #ffffff;
	box-shadow: none;
	outline: none;
	transition: border-color var(--msh-transition), box-shadow var(--msh-transition);
}

.msh-feature-input:focus,
.msh-feature-textarea:focus {
	border-color: var(--msh-primary) !important;
	box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1) !important;
	outline: none !important;
}

.msh-feature-textarea {
	height: 150px;
	resize: vertical;
}

.msh-feature-alert {
	padding: 16px 20px;
	border-radius: var(--msh-radius-sm);
	font-size: 13px;
	line-height: 1.5;
	margin-bottom: 24px;
	display: flex;
	align-items: center;
	gap: 12px;
}

.msh-feature-alert--success {
	background: #ecfdf5;
	border: 1px solid #d1fae5;
	color: #065f46;
}

.msh-feature-alert--error {
	background: #fff5f5;
	border: 1px solid #fee2e2;
	color: #9f1239;
}

.msh-sidebar-list {
	list-style: none;
	padding: 0;
	margin: 0;
}

.msh-sidebar-list-item {
	position: relative;
	padding-left: 24px;
	margin-bottom: 12px;
	font-size: 13px;
	color: var(--msh-text-secondary);
	line-height: 1.4;
}

.msh-sidebar-list-item::before {
	content: '✓';
	position: absolute;
	left: 0;
	top: 0;
	font-weight: 700;
	color: var(--msh-primary);
}

@media (max-width: 960px) {
	.msh-feature-layout {
		flex-direction: column;
	}
	.msh-feature-sidebar {
		width: 100%;
	}
}
</style>

<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-page-header">
				<h2><?php echo esc_html__('Suggest a Feature', 'my-site-hand'); ?></h2>
				<p class="msh-page-desc"><?php echo esc_html__('Help shape the future of My Site Hand! Share your ideas, requests, or use cases directly with the developer.', 'my-site-hand'); ?></p>
			</div>

			<?php if (!empty($message)): ?>
				<div class="msh-feature-alert msh-feature-alert--<?php echo esc_attr($message_type); ?>">
					<?php if ($message_type === 'success'): ?>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
					<?php else: ?>
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					<?php endif; ?>
					<span><?php echo esc_html($message); ?></span>
				</div>
			<?php endif; ?>

			<div class="msh-feature-layout">
				<!-- Main Form Column -->
				<div class="msh-feature-main">
					<div class="msh-feature-card">
						<div class="msh-feature-card-header">
							<h3><?php echo esc_html__('Submit a Feature Request', 'my-site-hand'); ?></h3>
						</div>
						<div class="msh-feature-card-body">
							<form method="POST" action="">
								<?php wp_nonce_field('msh_submit_feature', 'msh_feature_nonce'); ?>

								<div class="msh-feature-form-group">
									<label class="msh-feature-label" for="feature_title"><?php echo esc_html__('Feature Title', 'my-site-hand'); ?> <span style="color: var(--msh-danger);">*</span></label>
									<input type="text" id="feature_title" name="feature_title" class="msh-feature-input" placeholder="<?php esc_attr_e('e.g., Add integration for RankMath SEO plugin', 'my-site-hand'); ?>" required />
								</div>

								<div class="msh-feature-form-group">
									<label class="msh-feature-label" for="feature_description"><?php echo esc_html__('Description & Use Case', 'my-site-hand'); ?> <span style="color: var(--msh-danger);">*</span></label>
									<textarea id="feature_description" name="feature_description" class="msh-feature-textarea" placeholder="<?php esc_attr_e('Describe what this feature should do, how you plan to use it with Claude/Cursor, and why it is useful...', 'my-site-hand'); ?>" required></textarea>
								</div>

								<div class="msh-feature-form-group">
									<label class="msh-feature-label" for="feature_email"><?php echo esc_html__('Your Email Address', 'my-site-hand'); ?> <span style="font-weight: normal; text-transform: none; color: var(--msh-text-muted);">(<?php esc_html_e('optional, to receive updates', 'my-site-hand'); ?>)</span></label>
									<input type="email" id="feature_email" name="feature_email" class="msh-feature-input" value="<?php echo esc_attr($my_site_hand_default_email); ?>" />
								</div>

								<div class="msh-feature-form-group" style="margin-top: 28px;">
									<button type="submit" class="msh-btn msh-btn--primary" style="padding: 12px 24px; font-size: 14px;">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
										<?php echo esc_html__('Send Feature Request', 'my-site-hand'); ?>
									</button>
								</div>
							</form>
						</div>
					</div>
				</div>

				<!-- Sidebar Column -->
				<div class="msh-feature-sidebar">
					<div class="msh-feature-card">
						<div class="msh-feature-card-header" style="background: #fafbff;">
							<h3><?php echo esc_html__('How this works', 'my-site-hand'); ?></h3>
						</div>
						<div class="msh-feature-card-body">
							<ul class="msh-sidebar-list">
								<li class="msh-sidebar-list-item">
									<strong><?php echo esc_html__('Direct Developer Link', 'my-site-hand'); ?></strong> — 
									<?php echo esc_html__('Your suggestion goes straight to builtbytanin@gmail.com.', 'my-site-hand'); ?>
								</li>
								<li class="msh-sidebar-list-item">
									<strong><?php echo esc_html__('Open Roadmap', 'my-site-hand'); ?></strong> — 
									<?php echo esc_html__('Features are prioritized based on user interest and frequency of requests.', 'my-site-hand'); ?>
								</li>
								<li class="msh-sidebar-list-item">
									<strong><?php echo esc_html__('Secure Transmission', 'my-site-hand'); ?></strong> — 
									<?php echo esc_html__('Only the text you write is emailed out. Your site dashboard data and API tokens remain completely private.', 'my-site-hand'); ?>
								</li>
							</ul>
						</div>
					</div>

					<div class="msh-feature-card" style="background: #fafbff; border-color: rgba(79, 70, 229, 0.15);">
						<div class="msh-feature-card-body" style="padding: 20px; text-align: center;">
							<div style="width: 48px; height: 48px; border-radius: 50%; background: #eef2ff; color: var(--msh-primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"></path></svg>
							</div>
							<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: var(--msh-secondary);"><?php echo esc_html__('GitHub Project', 'my-site-hand'); ?></h4>
							<p style="margin: 0 0 16px 0; font-size: 13px; color: var(--msh-text-secondary); line-height: 1.4;"><?php echo esc_html__('Check source files, view progress, or submit pull requests directly on our GitHub project page.', 'my-site-hand'); ?></p>
							<a href="https://github.com/taninrahman21/my-site-hand" target="_blank" rel="noopener noreferrer" class="msh-btn msh-btn--ghost msh-btn--sm" style="width: 100%;">
								<?php echo esc_html__('Visit GitHub', 'my-site-hand'); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
	</div>
</div>
