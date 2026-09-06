<?php
/**
 * Site Health page template.
 *
 * Inspects the USER'S SITE for problems. This is not the AI Audit Log, which
 * records what an AI assistant did and lives in audit-log.php.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_scanner = \MySiteHand\Plugin::get_instance()->get_site_health_scanner();
$my_site_hand_report = $my_site_hand_scanner->get_latest_report();
$my_site_hand_checks = $my_site_hand_scanner->get_checks();
$my_site_hand_history = $my_site_hand_scanner->get_history(8);
$my_site_hand_has_report = null !== $my_site_hand_report;

$my_site_hand_score = $my_site_hand_has_report ? (int) $my_site_hand_report['score'] : 0;
$my_site_hand_band = $my_site_hand_has_report
	? $my_site_hand_scanner->get_score_band($my_site_hand_score)
	: ['band' => 'good', 'label' => ''];

// Export links are followed, not fetched, so both nonces travel in the query
// string. Two, because they do two different jobs: _wpnonce is what keeps REST
// cookie authentication from treating the request as anonymous, and nonce is
// the plugin's own check, matching the audit log export.
$my_site_hand_export_args = [
	'nonce'    => wp_create_nonce('my_site_hand_admin'),
	'_wpnonce' => wp_create_nonce('wp_rest'),
];
$my_site_hand_export_base = rest_url('my-site-hand/v1/health/export/');
$my_site_hand_print_url = add_query_arg(
	$my_site_hand_export_args + ['print' => '1'],
	$my_site_hand_export_base . 'print'
);
$my_site_hand_csv_url = add_query_arg(
	$my_site_hand_export_args,
	$my_site_hand_export_base . 'csv'
);

$my_site_hand_share_links = (new \MySiteHand\Shared_Reports())->list_active();

// Auto-start only on the post-activation visit, and only when there is
// nothing to show yet. Landing on an existing report must never silently
// kick off a fresh scan.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only UI hint; nothing is written from it.
$my_site_hand_autostart = !$my_site_hand_has_report
	&& isset($_GET['msh-autoscan'])
	&& '1' === sanitize_text_field(wp_unslash($_GET['msh-autoscan']));
// phpcs:enable WordPress.Security.NonceVerification.Recommended

/**
 * Render one issue row.
 *
 * The repair control inside .msh-hissue-fix is built by health-scan.js from
 * the data attributes, so server-rendered and live-rendered rows behave
 * identically.
 *
 * @param array  $issue    Issue array from the stored report.
 * @param string $check_id Check the issue belongs to.
 * @return void
 */
if (!function_exists('my_site_hand_health_issue_row')):
	function my_site_hand_health_issue_row(array $issue, string $check_id): void
	{
	$my_site_hand_fix_meta = wp_json_encode($issue['fix_meta'] ?? []);
	?>
	<li class="msh-hissue msh-hissue--<?php echo esc_attr($issue['severity']); ?>"
		data-check="<?php echo esc_attr($check_id); ?>"
		data-object-id="<?php echo esc_attr((string) ($issue['object_id'] ?? '')); ?>"
		data-fix-type="<?php echo esc_attr((string) ($issue['fix_type'] ?? '')); ?>"
		data-fix-meta="<?php echo esc_attr(false !== $my_site_hand_fix_meta ? $my_site_hand_fix_meta : '{}'); ?>"
		data-severity="<?php echo esc_attr($issue['severity']); ?>">
		<div class="msh-hissue-main">
			<span class="msh-hissue-title"><?php echo esc_html($issue['title']); ?></span>
			<?php if (!empty($issue['context'])): ?>
				<span class="msh-hissue-context"><?php echo esc_html($issue['context']); ?></span>
			<?php endif; ?>
		</div>
		<div class="msh-hissue-fix"></div>
		<?php if (!empty($issue['link'])): ?>
			<a class="msh-hissue-link" href="<?php echo esc_url($issue['link']); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e('Open', 'my-site-hand'); ?>
			</a>
		<?php endif; ?>
	</li>
		<?php
	}
endif;
?>

<div class="msh-wrap">

	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">

			<div class="msh-health" id="msh-health" data-has-report="<?php echo $my_site_hand_has_report ? '1' : '0'; ?>"
				data-autostart="<?php echo $my_site_hand_autostart ? '1' : '0'; ?>">

				<!-- Empty state: no scan has ever run -->
				<div class="msh-health-empty" id="msh-health-empty" <?php echo $my_site_hand_has_report ? 'hidden' : ''; ?>>
					<div class="msh-health-empty-inner">
						<svg class="msh-health-empty-icon" width="34" height="34" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="1.6" stroke-linecap="square" aria-hidden="true">
							<circle cx="11" cy="11" r="7"></circle>
							<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
						</svg>
						<h2 class="msh-health-empty-title"><?php esc_html_e('Scan your site', 'my-site-hand'); ?></h2>
						<p class="msh-health-empty-body">
							<?php esc_html_e('Find broken links, missing SEO data, and health issues in about 30 seconds.', 'my-site-hand'); ?>
						</p>
						<button type="button" class="msh-btn msh-btn--primary msh-health-start" data-msh-health-start>
							<?php esc_html_e('Scan My Site', 'my-site-hand'); ?>
						</button>
						<p class="msh-health-empty-note"><?php esc_html_e('No setup required', 'my-site-hand'); ?></p>
						<?php if ((bool) get_option('mysitehand_weekly_report_enabled', true)): ?>
							<p class="msh-health-empty-disclosure"><?php
							printf(
								/* translators: %s: link to the settings page */
								esc_html__('This site will also scan itself on a schedule and email you when your score changes. You can turn that off in %s.', 'my-site-hand'),
								'<a href="' . esc_url(admin_url('admin.php?page=my-site-hand-settings')) . '">' . esc_html__('Settings', 'my-site-hand') . '</a>'
							);
							?></p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Score card: shown once a report exists -->
				<div class="msh-health-summary" id="msh-health-summary" <?php echo $my_site_hand_has_report ? '' : 'hidden'; ?>>
					<div class="msh-health-scorebox msh-health-scorebox--<?php echo esc_attr($my_site_hand_band['band']); ?>"
						id="msh-health-scorebox">
						<span class="msh-health-scorenum" id="msh-health-scorenum"><?php echo esc_html((string) $my_site_hand_score); ?></span>
						<span class="msh-health-scoreof"><?php esc_html_e('/ 100', 'my-site-hand'); ?></span>
					</div>

					<div class="msh-health-summary-text">
						<p class="msh-health-bandlabel" id="msh-health-bandlabel"><?php echo esc_html($my_site_hand_band['label']); ?></p>
						<p class="msh-health-meta" id="msh-health-meta">
							<?php
							if ($my_site_hand_has_report) {
								printf(
									/* translators: %s: human readable time difference, e.g. "5 minutes" */
									esc_html__('Last scanned %s ago', 'my-site-hand'),
									esc_html(human_time_diff((int) $my_site_hand_report['generated_at'], time()))
								);

								if (null !== $my_site_hand_report['previous_score']) {
									$my_site_hand_delta = $my_site_hand_score - (int) $my_site_hand_report['previous_score'];
									echo ' · ';
									printf(
										/* translators: %s: signed score change, e.g. "+6" */
										esc_html__('%s since your last scan', 'my-site-hand'),
										esc_html(($my_site_hand_delta >= 0 ? '+' : '') . $my_site_hand_delta)
									);
								}
							}
							?>
						</p>

						<?php
						$my_site_hand_trend = $my_site_hand_history;
						require MYSITEHAND_PATH . 'templates/partials/health-trend.php';
						?>
					</div>

					<div class="msh-health-summary-actions">
						<button type="button" class="msh-btn msh-btn--primary" data-msh-health-start>
							<?php esc_html_e('Scan Again', 'my-site-hand'); ?>
						</button>

						<a class="msh-btn msh-btn--ghost" id="msh-health-print"
							href="<?php echo esc_url($my_site_hand_print_url); ?>"
							target="_blank" rel="noopener noreferrer"
							title="<?php esc_attr_e('Opens a printable page and your browser\'s print dialog. Choose "Save as PDF" as the destination.', 'my-site-hand'); ?>">
							<?php esc_html_e('Download PDF', 'my-site-hand'); ?>
						</a>

						<a class="msh-btn msh-btn--ghost" id="msh-health-csv"
							href="<?php echo esc_url($my_site_hand_csv_url); ?>">
							<?php esc_html_e('Export CSV', 'my-site-hand'); ?>
						</a>

						<button type="button" class="msh-btn msh-btn--ghost" id="msh-health-share-toggle"
							aria-expanded="false" aria-controls="msh-health-share">
							<?php esc_html_e('Share', 'my-site-hand'); ?>
						</button>
					</div>

					<p class="msh-health-exportnote">
						<?php esc_html_e('"Download PDF" uses your browser\'s own print-to-PDF — pick "Save as PDF" in the dialog that opens.', 'my-site-hand'); ?>
					</p>
				</div>

				<!-- Share: creates a public, read-only link to a redacted copy -->
				<div class="msh-health-share" id="msh-health-share" hidden>
					<h2 class="msh-health-share-title"><?php esc_html_e('Share this report', 'my-site-hand'); ?></h2>

					<div class="msh-health-share-disclosure">
						<p class="msh-health-share-lead">
							<?php esc_html_e('A share link is public. Anyone who has it can open the report without logging in. Before you create one, this is exactly what it does and does not contain:', 'my-site-hand'); ?>
						</p>
						<div class="msh-health-share-cols">
							<div>
								<h3><?php esc_html_e('They will see', 'my-site-hand'); ?></h3>
								<ul>
									<li><?php esc_html_e('Your site name and the date of the scan', 'my-site-hand'); ?></li>
									<li><?php esc_html_e('The score and its band', 'my-site-hand'); ?></li>
									<li><?php esc_html_e('Each check by name, and how many issues it found', 'my-site-hand'); ?></li>
								</ul>
							</div>
							<div>
								<h3><?php esc_html_e('They will not see', 'my-site-hand'); ?></h3>
								<ul>
									<li><?php esc_html_e('Any file name, page address, or server path', 'my-site-hand'); ?></li>
									<li><?php esc_html_e('Any post, media or user ID, or any edit link', 'my-site-hand'); ?></li>
									<li><?php esc_html_e('Your plugin, theme, PHP or WordPress versions', 'my-site-hand'); ?></li>
									<li><?php esc_html_e('The two security checks — those are left out entirely', 'my-site-hand'); ?></li>
								</ul>
							</div>
						</div>
						<p class="msh-health-share-note">
							<?php esc_html_e('The link shows a copy taken at the moment you create it, so re-scanning your site will not change what you already sent.', 'my-site-hand'); ?>
						</p>
					</div>

					<div class="msh-health-share-create">
						<label for="msh-health-share-days"><?php esc_html_e('Link expires after', 'my-site-hand'); ?></label>
						<select id="msh-health-share-days">
							<?php foreach (\MySiteHand\Shared_Reports::EXPIRY_CHOICES as $my_site_hand_days): ?>
								<option value="<?php echo esc_attr((string) $my_site_hand_days); ?>"
									<?php selected($my_site_hand_days, \MySiteHand\Shared_Reports::DEFAULT_EXPIRY_DAYS); ?>>
									<?php
									printf(
										/* translators: %d: number of days a share link stays valid */
										esc_html(_n('%d day', '%d days', $my_site_hand_days, 'my-site-hand')),
										esc_html((string) $my_site_hand_days)
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="msh-btn msh-btn--primary" id="msh-health-share-create">
							<?php esc_html_e('Create link', 'my-site-hand'); ?>
						</button>
					</div>

					<!-- Shown once, immediately after creation, and never again -->
					<div class="msh-health-share-fresh" id="msh-health-share-fresh" hidden>
						<p class="msh-health-share-once">
							<?php esc_html_e('Copy this link now. For the same reason API tokens are shown once, it is not stored anywhere and cannot be shown again.', 'my-site-hand'); ?>
						</p>
						<div class="msh-health-share-freshrow">
							<input type="text" id="msh-health-share-url" readonly value="">
							<button type="button" class="msh-btn" id="msh-health-share-copy">
								<?php esc_html_e('Copy', 'my-site-hand'); ?>
							</button>
						</div>
					</div>

					<p class="msh-health-share-error" id="msh-health-share-error" role="alert" hidden></p>

					<table class="msh-health-share-table" id="msh-health-share-table"
						<?php echo empty($my_site_hand_share_links) ? 'hidden' : ''; ?>>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e('Created', 'my-site-hand'); ?></th>
								<th scope="col"><?php esc_html_e('Expires', 'my-site-hand'); ?></th>
								<th scope="col"><?php esc_html_e('Views', 'my-site-hand'); ?></th>
								<th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'my-site-hand'); ?></span></th>
							</tr>
						</thead>
						<tbody id="msh-health-share-rows">
							<?php foreach ($my_site_hand_share_links as $my_site_hand_link):
								$my_site_hand_created = strtotime($my_site_hand_link['created_at'] . ' UTC');
								$my_site_hand_expires = strtotime($my_site_hand_link['expires_at'] . ' UTC');
								?>
								<tr data-share-id="<?php echo esc_attr((string) $my_site_hand_link['id']); ?>">
									<td><?php echo esc_html(wp_date(get_option('date_format'), $my_site_hand_created ?: time())); ?></td>
									<td><?php echo esc_html(wp_date(get_option('date_format'), $my_site_hand_expires ?: time())); ?></td>
									<td><?php echo esc_html((string) $my_site_hand_link['view_count']); ?></td>
									<td>
										<button type="button" class="msh-btn msh-btn--ghost msh-health-share-revoke"
											data-share-id="<?php echo esc_attr((string) $my_site_hand_link['id']); ?>">
											<?php esc_html_e('Revoke', 'my-site-hand'); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="msh-health-share-empty" id="msh-health-share-empty"
						<?php echo empty($my_site_hand_share_links) ? '' : 'hidden'; ?>>
						<?php esc_html_e('No share links yet.', 'my-site-hand'); ?>
					</p>
				</div>

				<!-- Progress: shown while a scan is running -->
				<div class="msh-health-progress" id="msh-health-progress" hidden>
					<div class="msh-health-progressbar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
						aria-valuenow="0" id="msh-health-progressbar">
						<span class="msh-health-progressfill" id="msh-health-progressfill"></span>
					</div>
					<div class="msh-health-progressrow">
						<span class="msh-health-progresslabel" id="msh-health-progresslabel" aria-live="polite"></span>
						<button type="button" class="msh-btn msh-btn--ghost" id="msh-health-cancel">
							<?php esc_html_e('Cancel', 'my-site-hand'); ?>
						</button>
					</div>
				</div>

				<p class="msh-health-error" id="msh-health-error" role="alert" hidden></p>

				<!-- Check rows -->
				<ul class="msh-health-checks" id="msh-health-checks">
					<?php foreach ($my_site_hand_checks as $my_site_hand_id => $my_site_hand_check):
						$my_site_hand_entry = $my_site_hand_has_report && isset($my_site_hand_report['checks'][$my_site_hand_id])
							? $my_site_hand_report['checks'][$my_site_hand_id]
							: null;
						$my_site_hand_status = $my_site_hand_entry['status'] ?? 'pending';
						$my_site_hand_count = (int) ($my_site_hand_entry['issue_count'] ?? 0);
						?>
						<li class="msh-hcheck" data-check="<?php echo esc_attr($my_site_hand_id); ?>"
							data-status="<?php echo esc_attr($my_site_hand_status); ?>"
							data-severity="<?php echo esc_attr($my_site_hand_check->get_severity()); ?>"
							data-count="<?php echo esc_attr((string) $my_site_hand_count); ?>">

							<button type="button" class="msh-hcheck-head" aria-expanded="false"
								aria-controls="msh-hcheck-body-<?php echo esc_attr($my_site_hand_id); ?>">
								<span class="msh-hcheck-state" aria-hidden="true"></span>
								<span class="msh-hcheck-name"><?php echo esc_html($my_site_hand_check->get_label()); ?></span>
								<span class="msh-hcheck-count">
									<?php
									if (null === $my_site_hand_entry) {
										echo '';
									} elseif ('skipped' === $my_site_hand_status) {
										esc_html_e('not applicable', 'my-site-hand');
									} elseif ('error' === $my_site_hand_status) {
										esc_html_e('could not run', 'my-site-hand');
									} elseif (0 === $my_site_hand_count) {
										esc_html_e('nothing found', 'my-site-hand');
									} else {
										printf(
											/* translators: %d: number of issues found */
											esc_html(_n('%d found', '%d found', $my_site_hand_count, 'my-site-hand')),
											esc_html((string) $my_site_hand_count)
										);
									}
									?>
								</span>
								<svg class="msh-hcheck-chev" width="14" height="14" viewBox="0 0 24 24" fill="none"
									stroke="currentColor" stroke-width="2" stroke-linecap="square" aria-hidden="true">
									<polyline points="6 9 12 15 18 9"></polyline>
								</svg>
							</button>

							<div class="msh-hcheck-body" id="msh-hcheck-body-<?php echo esc_attr($my_site_hand_id); ?>" hidden>
								<p class="msh-hcheck-desc"><?php echo esc_html($my_site_hand_check->get_description()); ?></p>

								<?php if (null !== $my_site_hand_entry && !empty($my_site_hand_entry['issues'])): ?>
									<ul class="msh-hissues">
										<?php foreach ($my_site_hand_entry['issues'] as $my_site_hand_issue): ?>
											<?php my_site_hand_health_issue_row($my_site_hand_issue, (string) $my_site_hand_id); ?>
										<?php endforeach; ?>
									</ul>
									<?php if (!empty($my_site_hand_entry['truncated'])): ?>
										<p class="msh-hcheck-truncated">
											<?php
											printf(
												/* translators: 1: number of issues shown, 2: total issues found */
												esc_html__('Showing the first %1$d of %2$d. Fix these and scan again to see more.', 'my-site-hand'),
												count($my_site_hand_entry['issues']),
												(int) $my_site_hand_entry['issue_count']
											);
											?>
										</p>
									<?php endif; ?>
								<?php else: ?>
									<ul class="msh-hissues"></ul>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>

			</div>

		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>
