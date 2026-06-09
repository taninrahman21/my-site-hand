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
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<!-- Header Row -->
			<div class="msh-tokens-header-row">
				<div class="msh-page-header-info">
					<h2 class="msh-page-title"><?php echo esc_html__('API Tokens', 'my-site-hand'); ?></h2>
					<p class="msh-page-desc-inline">
						<?php 
						$my_site_hand_active_tokens_count = count(array_filter($my_site_hand_tokens, function($t) {
							$is_active = (int) $t['is_active'] === 1;
							$is_expired = !empty($t['expires_at']) && strtotime($t['expires_at']) < time();
							return $is_active && !$is_expired;
						}));
						printf(
							/* translators: %d: count of active tokens */
							esc_html__('%d active tokens · used by AI clients to authenticate', 'my-site-hand'),
							(int) $my_site_hand_active_tokens_count
						);
						?>
					</p>
				</div>
				<div class="msh-page-header-actions">
					<button type="button" id="msh-generate-token-btn" class="msh-btn msh-btn--primary" onclick="mshTokens.openGenerateModal()">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
						<?php echo esc_html__('New token', 'my-site-hand'); ?>
					</button>
				</div>
			</div>

			<!-- Warning alert banner -->
			<div class="msh-info-alert-banner">
				<div class="msh-info-alert-icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
				</div>
				<div class="msh-info-alert-text">
					<strong><?php echo esc_html__('Tokens are shown once.', 'my-site-hand'); ?></strong> <?php echo esc_html__('Copy your token immediately after creation — you won\'t see it again.', 'my-site-hand'); ?>
				</div>
			</div>

			<!-- Tokens Card List -->
			<div class="msh-tokens-cards-list">
				<?php if (empty($my_site_hand_tokens)): ?>
					<div class="msh-tokens-empty-state">
						<div class="msh-tokens-empty-icon">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
						</div>
						<h3 class="msh-tokens-empty-heading"><?php echo esc_html__('No tokens yet', 'my-site-hand'); ?></h3>
						<p class="msh-tokens-empty-desc"><?php echo esc_html__('Generate a token to connect an AI client to your MCP server.', 'my-site-hand'); ?></p>
						<button type="button" class="msh-btn msh-btn--primary msh-tokens-empty-btn" onclick="mshTokens.openGenerateModal()">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
							<?php echo esc_html__('Create your first token', 'my-site-hand'); ?>
						</button>
					</div>
				<?php else: ?>
					<?php foreach ($my_site_hand_tokens as $my_site_hand_token):
						$my_site_hand_is_active = (int) $my_site_hand_token['is_active'] === 1;
						$my_site_hand_is_expired = !empty($my_site_hand_token['expires_at']) && strtotime($my_site_hand_token['expires_at']) < time();
						$my_site_hand_status = !$my_site_hand_is_active ? 'revoked' : ($my_site_hand_is_expired ? 'expired' : 'active');

						// Compute Read/Write/Admin permission tags
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
									$my_site_hand_is_readonly = !empty($my_site_hand_ab['annotations']['readonly']);
									$my_site_hand_is_destructive = !empty($my_site_hand_ab['annotations']['destructive']);
									if ($my_site_hand_is_readonly) {
										$my_site_hand_has_read = true;
									} else {
										$my_site_hand_has_write = true;
									}
									if ($my_site_hand_is_destructive) {
										$my_site_hand_has_admin = true;
									}
								}
							}
						}
						?>
						<div class="msh-token-card <?php echo 'revoked' === $my_site_hand_status ? 'msh-token-card--revoked' : ''; ?>">
							<div class="msh-token-card-left">
								<div class="msh-token-card-icon">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
								</div>
								<div class="msh-token-card-info">
									<div class="msh-token-card-title-line">
										<strong class="msh-token-card-label"><?php echo esc_html($my_site_hand_token['label']); ?></strong>
										<span class="msh-token-card-snippet"><?php echo esc_html('msh_pk_' . substr(md5($my_site_hand_token['created_at'] . $my_site_hand_token['id']), 0, 6) . '...'); ?></span>
									</div>
									<div class="msh-token-card-meta-line">
										<span><?php
											/* translators: %s: formatted date when token was created */
											printf( esc_html__( 'Created %s', 'my-site-hand' ), esc_html( wp_date( 'M d, Y', strtotime( $my_site_hand_token['created_at'] ) ) ) );
										?></span>
										<span class="msh-meta-sep">&bull;</span>
										<span><?php
											if ( $my_site_hand_token['last_used'] ) {
												/* translators: %s: human-readable time difference since token was last used */
												printf( esc_html__( 'Last used %s ago', 'my-site-hand' ), esc_html( human_time_diff( strtotime( $my_site_hand_token['last_used'] ) ) ) );
											} else {
												echo esc_html__( 'Never used', 'my-site-hand' );
											}
										?></span>
										<span class="msh-meta-sep">&bull;</span>
										<span><?php 
											if ( $my_site_hand_token['expires_at'] ) {
												$my_site_hand_expiry_time = strtotime( $my_site_hand_token['expires_at'] );
												if ( $my_site_hand_expiry_time < time() ) {
													/* translators: %s: formatted expiry date */
													printf( esc_html__( 'Expired %s', 'my-site-hand' ), esc_html( wp_date( 'M d, Y', $my_site_hand_expiry_time ) ) );
												} else {
													/* translators: %s: formatted expiry date */
													printf( esc_html__( 'Expires %s', 'my-site-hand' ), esc_html( wp_date( 'M d, Y', $my_site_hand_expiry_time ) ) );
												}
											} else {
												echo esc_html__('Expires never', 'my-site-hand');
											}
										?></span>
										<?php if (!$my_site_hand_is_active): ?>
											<span class="msh-meta-sep">&bull;</span>
											<span class="msh-token-revoked-badge"><?php echo esc_html__('Revoked', 'my-site-hand'); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<div class="msh-token-card-right">
								<div class="msh-token-card-permissions">
									<?php if ($my_site_hand_has_read): ?>
										<span class="msh-token-perm-tag"><?php echo esc_html__('READ', 'my-site-hand'); ?></span>
									<?php endif; ?>
									<?php if ($my_site_hand_has_write): ?>
										<span class="msh-token-perm-tag"><?php echo esc_html__('WRITE', 'my-site-hand'); ?></span>
									<?php endif; ?>
									<?php if ($my_site_hand_has_admin): ?>
										<span class="msh-token-perm-tag msh-token-perm-tag--admin"><?php echo esc_html__('ADMIN', 'my-site-hand'); ?></span>
									<?php endif; ?>
								</div>
								<div class="msh-token-card-actions">
									<?php if ($my_site_hand_is_active && !$my_site_hand_is_expired): ?>
										<button type="button" class="msh-token-delete-btn" title="<?php esc_attr_e('Revoke token', 'my-site-hand'); ?>"
											onclick="mshTokens.revokeToken(<?php echo absint($my_site_hand_token['id']); ?>, '<?php echo esc_attr($my_site_hand_token['label']); ?>')">
											<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
										</button>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
	</div>

	<!-- Generate Token Modal -->
	<div id="msh-generate-modal" class="msh-modal-overlay" style="display:none;" role="dialog" aria-modal="true"
		aria-labelledby="msh-modal-title">
		<div class="msh-modal">
			<!-- Step 1: Create Token Form -->
			<div id="msh-modal-create-step">
				<div class="msh-modal-header">
					<h3 id="msh-modal-title"><?php echo esc_html__('New API token', 'my-site-hand'); ?></h3>
					<button type="button" class="msh-modal-close" onclick="mshTokens.closeModal()">&times;</button>
				</div>
				<div class="msh-modal-body">
					<form id="msh-generate-token-form">
						<!-- Token name -->
						<div class="msh-form-group">
							<label class="msh-form-label-caps" for="msh-token-label"><?php echo esc_html__('TOKEN NAME', 'my-site-hand'); ?></label>
							<input type="text" id="msh-token-label" name="label" class="msh-input"
								placeholder="<?php esc_attr_e('e.g. Claude Desktop — MacBook', 'my-site-hand'); ?>"
								required oninput="mshTokens.updateSubmitButtonState()" />
						</div>

						<!-- Scopes -->
						<div class="msh-form-group">
							<label class="msh-form-label-caps"><?php echo esc_html__('SCOPES', 'my-site-hand'); ?></label>
							<div class="msh-scopes-list">
								<!-- Read Card -->
								<div class="msh-scope-card" onclick="mshTokens.toggleScopeCard('read')">
									<input type="checkbox" id="msh-scope-read" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'read')" onclick="event.stopPropagation()" />
									<div class="msh-scope-card-info">
										<strong class="msh-scope-card-title"><?php echo esc_html__('Read', 'my-site-hand'); ?></strong>
										<p class="msh-scope-card-desc"><?php echo esc_html__('View posts, pages, media, users', 'my-site-hand'); ?></p>
									</div>
								</div>

								<!-- Write Card -->
								<div class="msh-scope-card" onclick="mshTokens.toggleScopeCard('write')">
									<input type="checkbox" id="msh-scope-write" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'write')" onclick="event.stopPropagation()" />
									<div class="msh-scope-card-info">
										<strong class="msh-scope-card-title"><?php echo esc_html__('Write', 'my-site-hand'); ?></strong>
										<p class="msh-scope-card-desc"><?php echo esc_html__('Create and edit content', 'my-site-hand'); ?></p>
									</div>
								</div>

								<!-- Admin Card -->
								<div class="msh-scope-card" onclick="mshTokens.toggleScopeCard('admin')">
									<input type="checkbox" id="msh-scope-admin" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'admin')" onclick="event.stopPropagation()" />
									<div class="msh-scope-card-info">
										<strong class="msh-scope-card-title"><?php echo esc_html__('Admin', 'my-site-hand'); ?></strong>
										<p class="msh-scope-card-desc"><?php echo esc_html__('Manage settings, delete content', 'my-site-hand'); ?></p>
									</div>
								</div>

								<!-- Custom Card -->
								<div class="msh-scope-card" id="msh-scope-card-custom" onclick="mshTokens.toggleScopeCard('custom')">
									<input type="checkbox" id="msh-scope-custom" class="msh-scope-checkbox" onchange="mshTokens.onScopeCheckboxChange(event, 'custom')" onclick="event.stopPropagation()" />
									<div class="msh-scope-card-info">
										<strong class="msh-scope-card-title"><?php echo esc_html__('Custom', 'my-site-hand'); ?></strong>
										<p class="msh-scope-card-desc"><?php echo esc_html__('Choose specific abilities to restrict this token', 'my-site-hand'); ?></p>
									</div>
								</div>
							</div>

							<!-- Custom Abilities list container (initially hidden) -->
							<div id="msh-custom-abilities-wrapper" class="msh-custom-abilities-wrapper" style="display: none;">
								<div class="msh-abilities-check" style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; max-height: 200px; overflow-y: auto; padding: 12px; border: 1px solid var(--msh-border); border-radius: 4px; background: var(--msh-bg);">
									<?php foreach ($my_site_hand_abilities as $my_site_hand_ability): 
										$my_site_hand_is_readonly = !empty($my_site_hand_ability['annotations']['readonly']);
										$my_site_hand_is_destructive = !empty($my_site_hand_ability['annotations']['destructive']);
										
										$my_site_hand_scope_type = 'write';
										if ($my_site_hand_is_readonly) {
											$my_site_hand_scope_type = 'read';
										} elseif ($my_site_hand_is_destructive) {
											$my_site_hand_scope_type = 'admin';
										}
									?>
										<label class="msh-checkbox-label" style="padding: 4px 0; display: flex; align-items: center; gap: 8px;">
											<input type="checkbox" name="abilities[]"
												value="<?php echo esc_attr($my_site_hand_ability['name']); ?>"
												data-scope="<?php echo esc_attr($my_site_hand_scope_type); ?>"
												onchange="mshTokens.onCustomAbilityChange('<?php echo esc_js( $my_site_hand_scope_type ); ?>')" />
											<span style="font-size: 13px; font-weight: 500;"><?php echo esc_html($my_site_hand_ability['label']); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<!-- Expires -->
						<div class="msh-form-group">
							<label class="msh-form-label-caps" for="msh-token-expires"><?php echo esc_html__('EXPIRES', 'my-site-hand'); ?></label>
							<select id="msh-token-expires" name="expires_at" class="msh-select">
								<option value="6_months" selected><?php echo esc_html__('In 6 months', 'my-site-hand'); ?></option>
								<option value="30_days"><?php echo esc_html__('In 30 days', 'my-site-hand'); ?></option>
								<option value="90_days"><?php echo esc_html__('In 90 days', 'my-site-hand'); ?></option>
								<option value="1_year"><?php echo esc_html__('In 1 year', 'my-site-hand'); ?></option>
								<option value="never"><?php echo esc_html__('Never', 'my-site-hand'); ?></option>
							</select>
						</div>
					</form>
				</div>
				<div class="msh-modal-footer">
					<button type="button" id="msh-submit-token" class="msh-btn msh-btn--primary msh-btn--disabled" disabled onclick="mshTokens.generateToken()">
						<?php echo esc_html__('Generate token', 'my-site-hand'); ?>
					</button>
					<button type="button" class="msh-btn msh-btn--ghost" onclick="mshTokens.closeModal()">
						<?php echo esc_html__('Cancel', 'my-site-hand'); ?></button>
				</div>
			</div>

			<!-- Step 2: Token Created Success Display & Connectors -->
			<div id="msh-modal-created-step" style="display: none;">
				<div class="msh-modal-header">
					<h3 id="msh-modal-title"><?php echo esc_html__('Token created', 'my-site-hand'); ?></h3>
					<button type="button" class="msh-modal-close" onclick="mshTokens.closeModal()">&times;</button>
				</div>
				<div class="msh-modal-body">
					<!-- Warning alert banner matching screenshot exactly -->
					<div class="msh-token-created-warning">
						<div class="msh-token-created-warning-icon">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 11 2 2 4-4"></path></svg>
						</div>
						<div class="msh-token-created-warning-text">
							<strong><?php echo esc_html__('Copy this token now.', 'my-site-hand'); ?></strong> <?php echo esc_html__('For security, it will not be shown again.', 'my-site-hand'); ?>
						</div>
					</div>

					<!-- Token reveal box matching screenshot -->
					<div class="msh-form-group">
						<label class="msh-form-label-caps"><?php echo esc_html__('YOUR TOKEN', 'my-site-hand'); ?></label>
						<div class="msh-token-reveal-input-wrap">
							<!-- Masked view by default -->
							<input type="password" id="msh-new-token-value-masked" class="msh-token-value-display" readonly value="" />
							<!-- Hidden input with actual token value for copying and toggle -->
							<input type="text" id="msh-new-token-value" class="msh-token-value-display" style="display: none;" readonly value="" />
							<button type="button" class="msh-token-toggle-visibility-btn" onclick="mshTokens.toggleTokenVisibility()">
								<!-- Eye icon -->
								<svg id="msh-eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<!-- Eye-off icon (hidden by default) -->
								<svg id="msh-eye-off-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
							</button>
							<button type="button" id="msh-copy-token-btn" class="msh-btn msh-btn--primary msh-token-copy-btn">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
								<span><?php echo esc_html__('Copy', 'my-site-hand'); ?></span>
							</button>
						</div>
					</div>

					<!-- Connection guides same to same but matching the design pattern -->
					<div class="msh-connection-guide-modal-wrap" style="margin-top: 24px; padding: 20px; border: 1px solid rgba(79, 70, 229, 0.15); border-radius: 8px; background: rgba(79, 70, 229, 0.02);">
						<div class="msh-os-tabs" style="margin-bottom: 20px; border-bottom: 1px solid rgba(79, 70, 229, 0.1); padding: 4px; display: flex; gap: 8px;">
							<button type="button" id="msh-client-tab-claude" class="msh-os-tab msh-os-tab--active" onclick="mshTokens.switchClientTab('claude')">
								<?php echo esc_html__( 'Claude Desktop', 'my-site-hand' ); ?>
							</button>
							<button type="button" id="msh-client-tab-cursor" class="msh-os-tab" onclick="mshTokens.switchClientTab('cursor')">
								<?php echo esc_html__( 'Cursor', 'my-site-hand' ); ?>
							</button>
						</div>

						<!-- Claude Desktop Panel -->
						<div id="msh-claude-panel">
							<h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--msh-primary);">
								<?php echo esc_html__('Connect Claude Desktop', 'my-site-hand'); ?></h4>

							<div class="msh-os-tabs">
								<button type="button" id="msh-os-tab-windows" class="msh-os-tab msh-os-tab--active" onclick="mshTokens.switchOsTab('windows')">
									<?php echo esc_html__( 'Windows', 'my-site-hand' ); ?>
								</button>
								<button type="button" id="msh-os-tab-mac" class="msh-os-tab" onclick="mshTokens.switchOsTab('mac')">
									<?php echo esc_html__( 'macOS', 'my-site-hand' ); ?>
								</button>
								<button type="button" id="msh-os-tab-linux" class="msh-os-tab" onclick="mshTokens.switchOsTab('linux')">
									<?php echo esc_html__( 'Linux', 'my-site-hand' ); ?>
								</button>
							</div>

							<div class="msh-connection-steps" style="margin-top: 16px; display: flex; flex-direction: column; gap: 16px;">
								<div class="msh-step">
									<div style="font-size: 12px; font-weight: 700; color: var(--msh-text-secondary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
										<span style="background: var(--msh-primary); color: #fff; width: 18px; height: 18px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px;">1</span>
										<?php echo esc_html__('Install mcp-remote', 'my-site-hand'); ?>
									</div>
									<div class="msh-token-value-wrap" style="margin-bottom: 0;">
										<input type="text" id="msh-claude-step-1" class="msh-token-value" style="width: 100%; border-color: rgba(79, 70, 229, 0.2);" readonly value="npm install -g mcp-remote" />
										<button type="button" class="msh-btn msh-btn--primary msh-btn--sm" onclick="msh.copyText('msh-claude-step-1')">
											<?php echo esc_html__('Copy', 'my-site-hand'); ?>
										</button>
									</div>
								</div>

								<div class="msh-step">
									<div style="font-size: 12px; font-weight: 700; color: var(--msh-text-secondary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
										<span style="background: var(--msh-primary); color: #fff; width: 18px; height: 18px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px;">2</span>
										<?php echo esc_html__('Connect to Claude Desktop', 'my-site-hand'); ?>
									</div>
									<div class="msh-token-value-wrap" style="margin-bottom: 0;">
										<input type="text" id="msh-claude-step-2" class="msh-token-value" style="width: 100%; border-color: rgba(79, 70, 229, 0.2);" readonly placeholder="..." />
										<button type="button" class="msh-btn msh-btn--primary msh-btn--sm" onclick="msh.copyText('msh-claude-step-2')">
											<?php echo esc_html__('Copy', 'my-site-hand'); ?>
										</button>
									</div>
								</div>
							</div>

							<p style="margin: 16px 0 0; font-size: 11px; font-style: italic; color: var(--msh-text-muted); display: flex; align-items: center; gap: 6px;">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
								<?php echo esc_html__('Run Step 1 first and wait for it to complete before running Step 2.', 'my-site-hand'); ?>
							</p>

							<div class="msh-node-note">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
								<span><?php
									printf(
										/* translators: %s: link to nodejs.org */
										esc_html__('No Node.js? Download LTS from %s first.', 'my-site-hand'),
										'<a href="https://nodejs.org/" target="_blank" rel="noopener">nodejs.org</a>'
									); ?></span>
							</div>
						</div>

						<!-- Cursor Panel -->
						<div id="msh-cursor-panel" style="display:none;">
							<h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--msh-primary);">
								<?php echo esc_html__('Connect Cursor', 'my-site-hand'); ?></h4>

							<div class="msh-form-group" style="margin-bottom: 12px;">
								<label style="font-size: 11px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--msh-text-secondary);"><?php echo esc_html__('MCP Server URL', 'my-site-hand'); ?></label>
								<div class="msh-token-value-wrap">
									<input type="text" id="msh-cursor-url" class="msh-token-value" style="width: 100%; border-color: rgba(79, 70, 229, 0.2);" readonly />
									<button type="button" class="msh-btn msh-btn--primary msh-btn--sm" onclick="msh.copyText('msh-cursor-url')">
										<?php echo esc_html__('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<div class="msh-form-group" style="margin-bottom: 12px;">
								<label style="font-size: 11px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--msh-text-secondary);"><?php echo esc_html__('Type', 'my-site-hand'); ?></label>
								<div class="msh-token-value-wrap">
									<input type="text" id="msh-cursor-type" class="msh-token-value" style="width: 100%; border-color: rgba(79, 70, 229, 0.2);" readonly value="http" />
									<button type="button" class="msh-btn msh-btn--primary msh-btn--sm" onclick="msh.copyText('msh-cursor-type')">
										<?php echo esc_html__('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<div class="msh-form-group" style="margin-bottom: 12px;">
								<label style="font-size: 11px; font-weight: 700; margin-bottom: 4px; display: block; color: var(--msh-text-secondary);"><?php echo esc_html__('Authorization Header', 'my-site-hand'); ?></label>
								<div class="msh-token-value-wrap">
									<input type="text" id="msh-cursor-auth" class="msh-token-value" style="width: 100%; border-color: rgba(79, 70, 229, 0.2);" readonly />
									<button type="button" class="msh-btn msh-btn--primary msh-btn--sm" onclick="msh.copyText('msh-cursor-auth')">
										<?php echo esc_html__('Copy', 'my-site-hand'); ?>
									</button>
								</div>
							</div>

							<p class="msh-hint" style="margin-top: 16px; font-size: 12px; line-height: 1.4; color: var(--msh-text-secondary);">
								<?php echo esc_html__('In Cursor: Settings → Features → MCP Servers → Add new MCP server → set Type to HTTP, paste the URL, and add the Authorization header.', 'my-site-hand'); ?>
							</p>
						</div>
					</div>
				</div>
				<div class="msh-modal-footer" style="padding: 20px 24px; background: none; border-top: none;">
					<button type="button" id="msh-close-done-btn" class="msh-btn msh-btn--primary msh-btn--full-width" onclick="mshTokens.closeModal()">
						<?php echo esc_html__('Done', 'my-site-hand'); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
