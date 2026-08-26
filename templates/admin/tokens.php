<?php
/**
 * Token management template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_plugin = \MySiteHand\Plugin::get_instance();
$my_site_hand_auth = $my_site_hand_plugin->get_auth_manager();
$my_site_hand_registry = $my_site_hand_plugin->get_abilities_registry();
$my_site_hand_tokens = array_filter($my_site_hand_auth->list_tokens(0), function ($t) {
	return (int) $t['is_active'] === 1;
});

$my_site_hand_disabled_abs = (array) get_option('mysitehand_disabled_abilities', []);
$my_site_hand_all_abilities = $my_site_hand_registry->get_all();
$my_site_hand_abilities = array_filter($my_site_hand_all_abilities, function ($my_site_hand_ability) use ($my_site_hand_disabled_abs) {
	return !in_array($my_site_hand_ability['name'], $my_site_hand_disabled_abs, true);
});

$my_site_hand_active_tokens_count = count(array_filter($my_site_hand_tokens, function ($t) {
	$is_active = (int) $t['is_active'] === 1;
	$is_expired = !empty($t['expires_at']) && strtotime($t['expires_at']) < time();
	return $is_active && !$is_expired;
}));

$my_site_hand_expiring_30d = $my_site_hand_auth->get_expiring_count(30);
$my_site_hand_never_used = count(array_filter($my_site_hand_tokens, function ($t) {
	return empty($t['last_used']);
}));

$my_site_hand_copy_icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><rect x="9" y="9" width="13" height="13"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';

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

$my_site_hand_scope_reference = [
	[
		'name' => __('Read', 'my-site-hand'),
		'tag'  => __('read only', 'my-site-hand'),
		'tagc' => 'msh-tag--readonly',
		'desc' => __('The agent can look at posts, pages, media metadata, users, and settings. It cannot change anything.', 'my-site-hand'),
	],
	[
		'name' => __('Write', 'my-site-hand'),
		'tag'  => __('public', 'my-site-hand'),
		'tagc' => 'msh-tag--public',
		'desc' => __('Adds create and update on top of read. Deleting and system diagnostics stay out of reach.', 'my-site-hand'),
	],
	[
		'name' => __('Admin', 'my-site-hand'),
		'tag'  => __('destructive', 'my-site-hand'),
		'tagc' => 'msh-tag--destructive',
		'desc' => __('Unlocks the destructive abilities: deleting content, reading error logs, and repairing tables.', 'my-site-hand'),
	],
	[
		'name' => __('Custom', 'my-site-hand'),
		'tag'  => __('admin scope', 'my-site-hand'),
		'tagc' => 'msh-tag--admin',
		'desc' => __('Pick abilities one by one. Anything you do not tick is refused for this token, even if it is enabled globally.', 'my-site-hand'),
	],
];
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-body-grid">

				<!-- On this page -->
				<nav class="msh-index" aria-label="<?php esc_attr_e('On this page', 'my-site-hand'); ?>">
					<p class="msh-index-label"><?php esc_html_e('On this page', 'my-site-hand'); ?></p>
					<a class="msh-index-link msh-index-link--active" href="#msh-tok-list"><span class="msh-index-no">01</span><?php esc_html_e('Tokens', 'my-site-hand'); ?><span class="msh-index-ct"><?php echo esc_html(count($my_site_hand_tokens)); ?></span></a>
					<a class="msh-index-link" href="#msh-tok-scopes"><span class="msh-index-no">02</span><?php esc_html_e('Scopes', 'my-site-hand'); ?></a>
					<div class="msh-index-foot">
						<p><?php
						if ($my_site_hand_never_used > 0) {
							printf(
								/* translators: %d: number of tokens that have never been used */
								esc_html(_n('%d token has never been used.', '%d tokens have never been used.', (int) $my_site_hand_never_used, 'my-site-hand')),
								(int) $my_site_hand_never_used
							);
						} else {
							esc_html_e('Every token here has been used at least once.', 'my-site-hand');
						}
						?></p>
						<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-audit')); ?>"><?php esc_html_e('See what they did', 'my-site-hand'); ?></a>
					</div>
				</nav>

				<div class="msh-body-main">

					<div class="msh-page-head">
						<div class="msh-page-head-info">
							<p class="msh-eyebrow"><i></i><?php esc_html_e('Access', 'my-site-hand'); ?></p>
							<h2 class="msh-page-title"><?php esc_html_e('API tokens', 'my-site-hand'); ?></h2>
							<p class="msh-page-desc"><?php
							printf(
								/* translators: %d: count of active tokens */
								esc_html(_n('%d token is live. Each one is how a single AI client proves it may talk to this site.', '%d tokens are live. Each one is how a single AI client proves it may talk to this site.', (int) $my_site_hand_active_tokens_count, 'my-site-hand')),
								(int) $my_site_hand_active_tokens_count
							); ?></p>
						</div>
						<div class="msh-page-head-actions">
							<button type="button" id="msh-generate-token-btn" class="msh-btn msh-btn--primary" onclick="mshTokens.openGenerateModal()">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
								<?php esc_html_e('New token', 'my-site-hand'); ?>
							</button>
						</div>
					</div>

					<!-- Notice -->
					<div class="msh-info-alert-banner">
						<div class="msh-info-alert-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
						</div>
						<div class="msh-info-alert-text">
							<strong><?php esc_html_e('A token is shown once.', 'my-site-hand'); ?></strong>
							<?php esc_html_e('Copy it the moment it is created — only a hash is kept here, so it cannot be shown again.', 'my-site-hand'); ?>
							<?php if ($my_site_hand_expiring_30d > 0): ?>
								<?php
								printf(
									/* translators: %d: number of tokens expiring within 30 days */
									esc_html(_n(' %d expires within 30 days.', ' %d expire within 30 days.', (int) $my_site_hand_expiring_30d, 'my-site-hand')),
									(int) $my_site_hand_expiring_30d
								);
								?>
							<?php endif; ?>
						</div>
					</div>

					<!-- 01 Tokens -->
					<div class="msh-card" id="msh-tok-list">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">01</span>
								<div>
									<h3><?php esc_html_e('Tokens', 'my-site-hand'); ?></h3>
									<p><?php esc_html_e('Revoking one disconnects its client immediately. The audit entries it made are kept.', 'my-site-hand'); ?></p>
								</div>
							</div>
							<span class="msh-sheet-tag <?php echo $my_site_hand_active_tokens_count > 0 ? 'msh-sheet-tag--ok' : ''; ?>"><?php
							printf(
								/* translators: %d: number of live tokens */
								esc_html__('%d live', 'my-site-hand'),
								(int) $my_site_hand_active_tokens_count
							); ?></span>
						</div>

						<div class="msh-tokens-cards-list" style="border: none;">
							<?php if (empty($my_site_hand_tokens)): ?>
								<div class="msh-tokens-empty-state">
									<div class="msh-tokens-empty-icon">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><rect x="3" y="11" width="18" height="11"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
									</div>
									<h3 class="msh-tokens-empty-heading"><?php esc_html_e('No tokens yet', 'my-site-hand'); ?></h3>
									<p class="msh-tokens-empty-desc"><?php esc_html_e('A token is what lets an AI assistant such as Claude Desktop reach this site. Nothing gets through the endpoint without one.', 'my-site-hand'); ?></p>
									<p class="msh-tokens-empty-desc"><?php
									printf(
										/* translators: %s: link to the Site Health page */
										esc_html__('You may not need one yet. The %s scan works with no token, no API key and no setup at all.', 'my-site-hand'),
										'<a href="' . esc_url(admin_url('admin.php?page=my-site-hand-health')) . '">' . esc_html__('Site Health', 'my-site-hand') . '</a>'
									);
									?></p>
									<button type="button" class="msh-btn msh-btn--primary msh-tokens-empty-btn" onclick="mshTokens.openGenerateModal()">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
										<?php esc_html_e('Create your first token', 'my-site-hand'); ?>
									</button>
								</div>
							<?php else: ?>
								<?php foreach ($my_site_hand_tokens as $my_site_hand_token):
									$my_site_hand_is_active = (int) $my_site_hand_token['is_active'] === 1;
									$my_site_hand_is_expired = !empty($my_site_hand_token['expires_at']) && strtotime($my_site_hand_token['expires_at']) < time();
									$my_site_hand_status = !$my_site_hand_is_active ? 'revoked' : ($my_site_hand_is_expired ? 'expired' : 'active');

									// Compute Read/Write/Admin permission tags.
									$my_site_hand_token_abilities = (array) $my_site_hand_token['abilities'];
									$my_site_hand_has_read = false;
									$my_site_hand_has_write = false;
									$my_site_hand_has_admin = false;

									if (empty($my_site_hand_token_abilities)) {
										$my_site_hand_has_read = true;
										$my_site_hand_has_write = true;
										$my_site_hand_has_admin = true;
									} else {
										foreach ($my_site_hand_token_abilities as $my_site_hand_ab_name) {
											$my_site_hand_ab = $my_site_hand_all_abilities[$my_site_hand_ab_name] ?? null;
											if ($my_site_hand_ab) {
												$my_site_hand_ab_readonly = !empty($my_site_hand_ab['annotations']['readonly']);
												$my_site_hand_ab_destructive = !empty($my_site_hand_ab['annotations']['destructive']);
												if ($my_site_hand_ab_readonly) {
													$my_site_hand_has_read = true;
												} else {
													$my_site_hand_has_write = true;
												}
												if ($my_site_hand_ab_destructive) {
													$my_site_hand_has_admin = true;
												}
											}
										}
									}
									?>
									<div class="msh-token-card <?php echo 'active' !== $my_site_hand_status ? 'msh-token-card--revoked' : ''; ?>">
										<div class="msh-token-card-left">
											<div class="msh-token-card-icon">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="11" width="18" height="11"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
											</div>
											<div class="msh-token-card-info">
												<div class="msh-token-card-title-line">
													<strong class="msh-token-card-label"><?php echo esc_html($my_site_hand_token['label']); ?></strong>
													<span class="msh-token-card-snippet"><?php echo esc_html('msh_pk_' . substr(md5($my_site_hand_token['created_at'] . $my_site_hand_token['id']), 0, 6) . '…'); ?></span>
													<?php if (!empty($my_site_hand_token['allowed_ips'])): ?>
														<?php /* translators: %s: comma-separated list of allowed IPs */ ?>
														<span class="msh-token-ip-lock" title="<?php echo esc_attr(sprintf(__('Restricted to IPs: %s', 'my-site-hand'), $my_site_hand_token['allowed_ips'])); ?>">
															<span class="msh-tag msh-tag--admin"><?php esc_html_e('IP locked', 'my-site-hand'); ?></span>
														</span>
													<?php endif; ?>
													<?php if ($my_site_hand_is_expired): ?>
														<span class="msh-token-revoked-badge"><?php esc_html_e('Expired', 'my-site-hand'); ?></span>
													<?php endif; ?>
													<?php if (!$my_site_hand_is_active): ?>
														<span class="msh-token-revoked-badge"><?php esc_html_e('Revoked', 'my-site-hand'); ?></span>
													<?php endif; ?>
												</div>
												<div class="msh-token-card-meta-line">
													<span><?php
													/* translators: %s: formatted date when token was created */
													printf(esc_html__('Created %s', 'my-site-hand'), esc_html(wp_date('j M Y', strtotime($my_site_hand_token['created_at']))));
													?></span>
													<span class="msh-meta-sep">&middot;</span>
													<span><?php
													if ($my_site_hand_token['last_used']) {
														/* translators: %s: human-readable time difference since token was last used */
														printf(esc_html__('Last used %s ago', 'my-site-hand'), esc_html(human_time_diff(strtotime($my_site_hand_token['last_used']))));
													} else {
														esc_html_e('Never used', 'my-site-hand');
													}
													?></span>
													<span class="msh-meta-sep">&middot;</span>
													<span><?php
													if ($my_site_hand_token['expires_at']) {
														$my_site_hand_expiry_time = strtotime($my_site_hand_token['expires_at']);
														if ($my_site_hand_expiry_time < time()) {
															/* translators: %s: formatted expiry date */
															printf(esc_html__('Expired %s', 'my-site-hand'), esc_html(wp_date('j M Y', $my_site_hand_expiry_time)));
														} else {
															/* translators: %s: formatted expiry date */
															printf(esc_html__('Expires %s', 'my-site-hand'), esc_html(wp_date('j M Y', $my_site_hand_expiry_time)));
														}
													} else {
														esc_html_e('Never expires', 'my-site-hand');
													}
													?></span>
												</div>
											</div>
										</div>
										<div class="msh-token-card-right">
											<div class="msh-token-card-permissions">
												<?php if (in_array('*', $my_site_hand_token_abilities, true) || empty($my_site_hand_token_abilities)): ?>
													<span class="msh-token-perm-tag msh-token-perm-tag--admin"><?php esc_html_e('Full access', 'my-site-hand'); ?></span>
												<?php else: ?>
													<?php if ($my_site_hand_has_read): ?>
														<span class="msh-token-perm-tag"><?php esc_html_e('Read', 'my-site-hand'); ?></span>
													<?php endif; ?>
													<?php if ($my_site_hand_has_write): ?>
														<span class="msh-token-perm-tag"><?php esc_html_e('Write', 'my-site-hand'); ?></span>
													<?php endif; ?>
													<?php if ($my_site_hand_has_admin): ?>
														<span class="msh-token-perm-tag msh-token-perm-tag--admin"><?php esc_html_e('Admin', 'my-site-hand'); ?></span>
													<?php endif; ?>
												<?php endif; ?>
											</div>
											<div class="msh-token-card-actions">
												<?php if ($my_site_hand_is_active && !$my_site_hand_is_expired): ?>
													<button type="button" class="msh-token-delete-btn" title="<?php esc_attr_e('Revoke token', 'my-site-hand'); ?>"
														onclick="mshTokens.revokeToken(<?php echo absint($my_site_hand_token['id']); ?>, '<?php echo esc_attr($my_site_hand_token['label']); ?>')">
														<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
													</button>
												<?php endif; ?>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>

					<!-- 02 Scopes -->
					<div class="msh-card" id="msh-tok-scopes">
						<div class="msh-card-header">
							<div class="msh-card-title-group">
								<span class="msh-card-no">02</span>
								<div>
									<h3><?php esc_html_e('What the scopes mean', 'my-site-hand'); ?></h3>
									<p><?php esc_html_e('A token can never do more than the abilities you have enabled globally.', 'my-site-hand'); ?></p>
								</div>
							</div>
							<a href="<?php echo esc_url(admin_url('admin.php?page=my-site-hand-abilities')); ?>"><?php esc_html_e('Manage abilities →', 'my-site-hand'); ?></a>
						</div>

						<?php foreach ($my_site_hand_scope_reference as $my_site_hand_scope): ?>
							<div class="msh-ability-row">
								<div class="msh-ability-row-left">
									<div class="msh-ability-row-header-line">
										<span class="msh-ability-name-code"><?php echo esc_html($my_site_hand_scope['name']); ?></span>
										<div class="msh-ability-tags">
											<span class="msh-tag <?php echo esc_attr($my_site_hand_scope['tagc']); ?>"><?php echo esc_html($my_site_hand_scope['tag']); ?></span>
										</div>
									</div>
									<p class="msh-ability-description"><?php echo esc_html($my_site_hand_scope['desc']); ?></p>
								</div>
								<div class="msh-ability-row-right"></div>
							</div>
						<?php endforeach; ?>
					</div>

				</div>
			</div>
		</div>
	</div>

	<!-- Generate Token Modal -->
	<div id="msh-generate-modal" class="msh-modal-overlay" style="display:none;" role="dialog" aria-modal="true"
		aria-labelledby="msh-modal-title">
		<div class="msh-modal">
			<!-- Step 1: Create Token Form -->
			<div id="msh-modal-create-step">
				<div class="msh-modal-header">
					<h3 id="msh-modal-title"><?php esc_html_e('New API token', 'my-site-hand'); ?></h3>
					<button type="button" class="msh-modal-close" onclick="mshTokens.closeModal()" aria-label="<?php esc_attr_e('Close', 'my-site-hand'); ?>">&times;</button>
				</div>
				<div class="msh-modal-body">
					<form id="msh-generate-token-form">
						<!-- Token name -->
						<div class="msh-form-group">
							<label class="msh-form-label-caps" for="msh-token-label"><?php esc_html_e('Token name', 'my-site-hand'); ?></label>
							<input type="text" id="msh-token-label" name="label" class="msh-input"
								placeholder="<?php esc_attr_e('e.g. Claude Desktop — MacBook', 'my-site-hand'); ?>"
								required oninput="mshTokens.updateSubmitButtonState()" />
							<p class="msh-hint"><?php esc_html_e('You will see this name in the audit log next to every call the client makes.', 'my-site-hand'); ?></p>
						</div>

						<!-- Scopes -->
						<div class="msh-form-group">
							<label class="msh-form-label-caps"><?php esc_html_e('Scopes', 'my-site-hand'); ?></label>
							<div class="msh-access-type-radios">
								<label class="msh-radio-label">
									<input type="radio" name="access_type" value="full" checked onchange="mshTokens.onAccessTypeChange()" />
									<span><strong><?php esc_html_e('Full access', 'my-site-hand'); ?></strong> — <?php esc_html_e('every ability you have enabled, now and in future', 'my-site-hand'); ?></span>
								</label>
								<label class="msh-radio-label">
									<input type="radio" name="access_type" value="limited" onchange="mshTokens.onAccessTypeChange()" />
									<span><strong><?php esc_html_e('Limited access', 'my-site-hand'); ?></strong> — <?php esc_html_e('pick exactly what this token may do', 'my-site-hand'); ?></span>
								</label>
							</div>

							<div id="msh-scopes-selection-area" style="display: none; margin-top: 14px;">
								<div class="msh-scopes-list">
									<!-- Read -->
									<div class="msh-scope-card" onclick="mshTokens.toggleScopeCard('read')">
										<input type="checkbox" id="msh-scope-read" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'read')" onclick="event.stopPropagation()" />
										<div class="msh-scope-card-info">
											<strong class="msh-scope-card-title"><?php esc_html_e('Read', 'my-site-hand'); ?></strong>
											<p class="msh-scope-card-desc"><?php esc_html_e('View posts, pages, media, users', 'my-site-hand'); ?></p>
										</div>
									</div>

									<!-- Write -->
									<div class="msh-scope-card" onclick="mshTokens.toggleScopeCard('write')">
										<input type="checkbox" id="msh-scope-write" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'write')" onclick="event.stopPropagation()" />
										<div class="msh-scope-card-info">
											<strong class="msh-scope-card-title"><?php esc_html_e('Write', 'my-site-hand'); ?></strong>
											<p class="msh-scope-card-desc"><?php esc_html_e('Create and edit content', 'my-site-hand'); ?></p>
										</div>
									</div>

									<!-- Admin -->
									<div class="msh-scope-card" onclick="mshTokens.toggleScopeCard('admin')">
										<input type="checkbox" id="msh-scope-admin" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'admin')" onclick="event.stopPropagation()" />
										<div class="msh-scope-card-info">
											<strong class="msh-scope-card-title"><?php esc_html_e('Admin', 'my-site-hand'); ?></strong>
											<p class="msh-scope-card-desc"><?php esc_html_e('Manage settings, delete content', 'my-site-hand'); ?></p>
										</div>
									</div>

									<!-- Custom -->
									<div class="msh-scope-card" id="msh-scope-card-custom" onclick="mshTokens.toggleScopeCard('custom')">
										<input type="checkbox" id="msh-scope-custom" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'custom')" onclick="event.stopPropagation()" />
										<div class="msh-scope-card-info">
											<strong class="msh-scope-card-title"><?php esc_html_e('Custom', 'my-site-hand'); ?></strong>
											<p class="msh-scope-card-desc"><?php esc_html_e('Choose specific abilities one by one', 'my-site-hand'); ?></p>
										</div>
									</div>
								</div>

								<!-- Custom abilities list -->
								<div id="msh-custom-abilities-wrapper" class="msh-custom-abilities-wrapper" style="display: none;">
									<div class="msh-abilities-check" style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px; max-height: 220px; overflow-y: auto; padding: 12px;">
										<?php foreach ($my_site_hand_abilities as $my_site_hand_ability):
											$my_site_hand_ab_readonly = !empty($my_site_hand_ability['annotations']['readonly']);
											$my_site_hand_ab_destructive = !empty($my_site_hand_ability['annotations']['destructive']);

											$my_site_hand_scope_type = 'write';
											if ($my_site_hand_ab_readonly) {
												$my_site_hand_scope_type = 'read';
											} elseif ($my_site_hand_ab_destructive) {
												$my_site_hand_scope_type = 'admin';
											}
											?>
											<label class="msh-checkbox-label" style="padding: 3px 0; display: flex; align-items: center; gap: 8px;">
												<input type="checkbox" name="abilities[]"
													value="<?php echo esc_attr($my_site_hand_ability['name']); ?>"
													data-scope="<?php echo esc_attr($my_site_hand_scope_type); ?>"
													onchange="mshTokens.onCustomAbilityChange('<?php echo esc_js($my_site_hand_scope_type); ?>')" />
												<span style="font-size: 12.5px;"><?php echo esc_html($my_site_hand_ability['label']); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</div>

								<div id="msh-scopes-validation-msg" style="display: none; color: var(--msh-r); font-size: 12px; margin-top: 8px;">
									<?php esc_html_e('Please select at least one ability for limited access.', 'my-site-hand'); ?>
								</div>
							</div>
						</div>

						<!-- Expires -->
						<div class="msh-form-group">
							<label class="msh-form-label-caps" for="msh-token-expires"><?php esc_html_e('Expires', 'my-site-hand'); ?></label>
							<select id="msh-token-expires" name="expires_at" class="msh-select">
								<option value="6_months" selected><?php esc_html_e('In 6 months', 'my-site-hand'); ?></option>
								<option value="30_days"><?php esc_html_e('In 30 days', 'my-site-hand'); ?></option>
								<option value="90_days"><?php esc_html_e('In 90 days', 'my-site-hand'); ?></option>
								<option value="1_year"><?php esc_html_e('In 1 year', 'my-site-hand'); ?></option>
								<option value="never"><?php esc_html_e('Never', 'my-site-hand'); ?></option>
							</select>
						</div>

						<!-- Allowed IPs -->
						<div class="msh-form-group">
							<label class="msh-form-label-caps" for="msh-token-allowed-ips"><?php esc_html_e('Allowed IPs (optional)', 'my-site-hand'); ?></label>
							<textarea id="msh-token-allowed-ips" name="allowed_ips" class="msh-input" rows="2"
								placeholder="<?php esc_attr_e('203.0.113.45, 198.51.100.0/24', 'my-site-hand'); ?>" oninput="mshTokens.updateSubmitButtonState()"></textarea>
							<p class="msh-hint"><?php esc_html_e('Comma-separated. CIDR notation is supported for IPv4 only. Leave empty to allow all IPs.', 'my-site-hand'); ?></p>
							<button type="button" class="msh-btn msh-btn--sm" style="margin-top: 8px;" onclick="mshTokens.insertCurrentIp('<?php echo esc_js(\MySiteHand\Ip_Utils::get_client_ip()); ?>')">
								<?php esc_html_e('Insert current IP', 'my-site-hand'); ?>
							</button>
							<div id="msh-ips-validation-msg" style="display: none; color: var(--msh-r); font-size: 12px; margin-top: 6px;"></div>
						</div>
					</form>
				</div>
				<div class="msh-modal-footer">
					<button type="button" class="msh-btn" onclick="mshTokens.closeModal()">
						<?php esc_html_e('Cancel', 'my-site-hand'); ?>
					</button>
					<button type="button" id="msh-submit-token" class="msh-btn msh-btn--primary msh-btn--disabled" disabled onclick="mshTokens.generateToken()">
						<?php esc_html_e('Generate token', 'my-site-hand'); ?>
					</button>
				</div>
			</div>

			<!-- Step 2: Token created -->
			<div id="msh-modal-created-step" style="display: none;">
				<div class="msh-modal-header">
					<h3><?php esc_html_e('Token created', 'my-site-hand'); ?></h3>
					<button type="button" class="msh-modal-close" onclick="mshTokens.closeModal()" aria-label="<?php esc_attr_e('Close', 'my-site-hand'); ?>">&times;</button>
				</div>
				<div class="msh-modal-body">
					<div class="msh-token-created-warning">
						<div class="msh-token-created-warning-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 11 2 2 4-4"></path></svg>
						</div>
						<div class="msh-token-created-warning-text">
							<strong><?php esc_html_e('Copy this token now.', 'my-site-hand'); ?></strong>
							<?php esc_html_e('For security, it will not be shown again.', 'my-site-hand'); ?>
						</div>
					</div>

					<div class="msh-form-group">
						<label class="msh-form-label-caps"><?php esc_html_e('Your token', 'my-site-hand'); ?></label>
						<div class="msh-token-reveal-input-wrap">
							<input type="password" id="msh-new-token-value-masked" class="msh-token-value-display" readonly value="" />
							<input type="text" id="msh-new-token-value" class="msh-token-value-display" style="display: none;" readonly value="" />
							<button type="button" class="msh-token-toggle-visibility-btn" onclick="mshTokens.toggleTokenVisibility()" aria-label="<?php esc_attr_e('Toggle visibility', 'my-site-hand'); ?>">
								<svg id="msh-eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<svg id="msh-eye-off-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
							</button>
							<button type="button" id="msh-copy-token-btn" class="msh-btn msh-btn--primary msh-token-copy-btn">
								<?php echo wp_kses($my_site_hand_copy_icon, $my_site_hand_svg_allowed); ?>
								<span><?php esc_html_e('Copy', 'my-site-hand'); ?></span>
							</button>
						</div>
					</div>

					<!-- Connection guides -->
					<div class="msh-connection-guide-modal-wrap">
						<div class="msh-os-tabs">
							<button type="button" id="msh-client-tab-claude" class="msh-os-tab msh-os-tab--active" onclick="mshTokens.switchClientTab('claude')">
								<?php esc_html_e('Claude Desktop', 'my-site-hand'); ?>
							</button>
							<button type="button" id="msh-client-tab-cursor" class="msh-os-tab" onclick="mshTokens.switchClientTab('cursor')">
								<?php esc_html_e('Cursor', 'my-site-hand'); ?>
							</button>
						</div>

						<!-- Claude Desktop -->
						<div id="msh-claude-panel" class="msh-modal-panel-body">
							<label class="msh-field-label"><?php esc_html_e('Operating system', 'my-site-hand'); ?></label>
							<div class="msh-os-tabs" style="width: fit-content; margin-bottom: 16px;">
								<button type="button" id="msh-os-tab-windows" class="msh-os-tab msh-os-tab--active" onclick="mshTokens.switchOsTab('windows')">
									<?php esc_html_e('Windows', 'my-site-hand'); ?>
								</button>
								<button type="button" id="msh-os-tab-mac" class="msh-os-tab" onclick="mshTokens.switchOsTab('mac')">
									<?php esc_html_e('macOS', 'my-site-hand'); ?>
								</button>
								<button type="button" id="msh-os-tab-linux" class="msh-os-tab" onclick="mshTokens.switchOsTab('linux')">
									<?php esc_html_e('Linux', 'my-site-hand'); ?>
								</button>
							</div>

							<div class="msh-connection-steps">
								<div class="msh-step">
									<div class="msh-step-label">
										<span class="msh-step-num">1</span><?php esc_html_e('Install mcp-remote', 'my-site-hand'); ?>
									</div>
									<div class="msh-token-value-wrap">
										<input type="text" id="msh-claude-step-1" class="msh-token-value" readonly value="npm install -g mcp-remote" />
										<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-claude-step-1')">
											<?php esc_html_e('Copy', 'my-site-hand'); ?>
										</button>
									</div>
								</div>

								<div class="msh-step">
									<div class="msh-step-label">
										<span class="msh-step-num">2</span><?php esc_html_e('Register this site with Claude Desktop', 'my-site-hand'); ?>
									</div>
									<div class="msh-token-value-wrap">
										<input type="text" id="msh-claude-step-2" class="msh-token-value" readonly placeholder="…" />
										<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-claude-step-2')">
											<?php esc_html_e('Copy', 'my-site-hand'); ?>
										</button>
									</div>
								</div>
							</div>

							<div class="msh-node-note">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
								<span><?php esc_html_e('Run step 1 and wait for it to finish before running step 2, then restart Claude Desktop.', 'my-site-hand'); ?>
									<?php
									printf(
										/* translators: %s: link to nodejs.org */
										esc_html__('No Node.js? Download LTS from %s first.', 'my-site-hand'),
										'<a href="https://nodejs.org/" target="_blank" rel="noopener">nodejs.org</a>'
									); ?></span>
							</div>
						</div>

						<!-- Cursor -->
						<div id="msh-cursor-panel" class="msh-modal-panel-body" style="display:none;">
							<div class="msh-form-group">
								<label class="msh-field-label"><?php esc_html_e('Server URL', 'my-site-hand'); ?></label>
								<div class="msh-token-value-wrap">
									<input type="text" id="msh-cursor-url" class="msh-token-value" readonly />
									<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-cursor-url')">
										<?php esc_html_e('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<div class="msh-form-group">
								<label class="msh-field-label"><?php esc_html_e('Type', 'my-site-hand'); ?></label>
								<div class="msh-token-value-wrap">
									<input type="text" id="msh-cursor-type" class="msh-token-value" readonly value="http" />
									<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-cursor-type')">
										<?php esc_html_e('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<div class="msh-form-group">
								<label class="msh-field-label"><?php esc_html_e('Authorization header', 'my-site-hand'); ?></label>
								<div class="msh-token-value-wrap">
									<input type="text" id="msh-cursor-auth" class="msh-token-value" readonly />
									<button type="button" class="msh-copy-btn" onclick="msh.copyText('msh-cursor-auth')">
										<?php esc_html_e('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<p class="msh-hint"><?php esc_html_e('In Cursor: Settings → Features → MCP Servers → Add new MCP server. Set Type to HTTP, then paste the URL and the Authorization header.', 'my-site-hand'); ?></p>
						</div>
					</div>
				</div>
				<div class="msh-modal-footer">
					<button type="button" id="msh-close-done-btn" class="msh-btn msh-btn--primary msh-btn--full-width" onclick="mshTokens.closeModal()">
						<?php esc_html_e('Done', 'my-site-hand'); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
