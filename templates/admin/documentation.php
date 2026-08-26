<?php
/**
 * Documentation page template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

// Retrieve site config details dynamically for client configurations.
$my_site_hand_sse_url = rest_url('my-site-hand/v1/mcp/streamable');

// Internal Page Links
$my_site_hand_tokens_page_url    = admin_url('admin.php?page=my-site-hand-tokens');
$my_site_hand_dashboard_page_url = admin_url('admin.php?page=my-site-hand');
$my_site_hand_audit_page_url     = admin_url('admin.php?page=my-site-hand-audit');

$my_site_hand_copy_svg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><rect x="9" y="9" width="13" height="13"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';

$my_site_hand_prompt_groups = [
	[
		'title' => __('Content management', 'my-site-hand'),
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>',
		'items' => [
			__('List the 5 most recent published posts on my site and summarize them.', 'my-site-hand'),
			__('Write a draft post about the importance of web accessibility and save it.', 'my-site-hand'),
			__('Find page ID 12, append a section explaining our cookie policy, and set its status to draft.', 'my-site-hand'),
		],
	],
	[
		'title' => __('SEO power-tools', 'my-site-hand'),
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line></svg>',
		'items' => [
			__('Audit the SEO keyword of our main service page and advise if it is optimized.', 'my-site-hand'),
			__('Generate and write an SEO-optimized meta description for my last blog post.', 'my-site-hand'),
			__('Scan my latest published article for any broken outbound links.', 'my-site-hand'),
		],
	],
	[
		'title' => __('WooCommerce store', 'my-site-hand'),
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>',
		'items' => [
			__("Provide a summary of today's WooCommerce sales, revenue, and total orders.", 'my-site-hand'),
			__('Search for order ID #450 and tell me who the customer is and its status.', 'my-site-hand'),
			__('Check if there are any items currently out of stock or low in quantity.', 'my-site-hand'),
		],
	],
	[
		'title' => __('Diagnostics & alt text', 'my-site-hand'),
		'icon'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>',
		'items' => [
			__('Provide a system health report of my server requirements.', 'my-site-hand'),
			__('Scan my error log file and list the most recent warning messages.', 'my-site-hand'),
			__('Find images in the media library that are missing alt tags.', 'my-site-hand'),
		],
	],
];

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
?>
<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">

			<div class="msh-page-head" style="margin-bottom: 26px;">
				<div class="msh-page-head-info">
					<p class="msh-eyebrow"><i></i><?php esc_html_e('Guide', 'my-site-hand'); ?></p>
					<h2 class="msh-page-title"><?php esc_html_e('How to use', 'my-site-hand'); ?></h2>
					<p class="msh-page-desc"><?php esc_html_e('Connect a client, learn what to ask it, and understand exactly what it can reach.', 'my-site-hand'); ?></p>
				</div>
				<div class="msh-page-head-actions">
					<a href="<?php echo esc_url($my_site_hand_tokens_page_url); ?>" class="msh-btn msh-btn--primary"><?php esc_html_e('Make a token', 'my-site-hand'); ?></a>
				</div>
			</div>

			<div class="msh-doc-layout">
				<!-- Section index -->
				<div class="msh-doc-sidebar">
					<button class="msh-doc-nav-btn msh-doc-nav-btn--active" data-tab="quickstart" onclick="mshDoc.switchTab('quickstart')">
						<svg class="msh-doc-nav-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
						<?php esc_html_e('Quick start', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="clients" onclick="mshDoc.switchTab('clients')">
						<svg class="msh-doc-nav-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="2" y="2" width="20" height="8"></rect><rect x="2" y="14" width="20" height="8"></rect></svg>
						<?php esc_html_e('Client setup', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="prompts" onclick="mshDoc.switchTab('prompts')">
						<svg class="msh-doc-nav-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
						<?php esc_html_e('Example prompts', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="security" onclick="mshDoc.switchTab('security')">
						<svg class="msh-doc-nav-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><rect x="3" y="11" width="18" height="11"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
						<?php esc_html_e('Security & audits', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="faq" onclick="mshDoc.switchTab('faq')">
						<svg class="msh-doc-nav-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
						<?php esc_html_e('Troubleshooting', 'my-site-hand'); ?>
					</button>
					<div class="msh-index-foot">
						<p><?php esc_html_e('Nothing on this page leaves your server. The plugin talks only to the clients you connect.', 'my-site-hand'); ?></p>
						<a href="<?php echo esc_url($my_site_hand_audit_page_url); ?>"><?php esc_html_e('See the audit log', 'my-site-hand'); ?></a>
					</div>
				</div>

				<!-- Panels -->
				<div class="msh-doc-content">

					<!-- Quick start -->
					<div id="msh-doc-panel-quickstart" class="msh-doc-panel msh-doc-panel--active">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php esc_html_e('Connecting in four steps', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p class="msh-step-desc" style="margin-bottom: 4px;">
									<?php esc_html_e('My Site Hand bridges WordPress and AI clients using the Model Context Protocol. Once connected, you can write posts, review system health, run SEO diagnostics, and check WooCommerce details with plain conversational commands.', 'my-site-hand'); ?>
								</p>

								<div class="msh-stepper">
									<div class="msh-step-item">
										<div class="msh-step-badge">1</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('Generate an API token', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php
											echo wp_kses(
												sprintf(
													/* translators: 1: opening <a> tag link to API Tokens page, 2: closing </a> tag */
													__('Open the %1$sAPI Tokens page%2$s and click "New token". Give it a name you will recognise in the audit log, then pick a scope — full access, or a limited set of abilities.', 'my-site-hand'),
													'<a href="' . esc_url($my_site_hand_tokens_page_url) . '" class="msh-doc-link">',
													'</a>'
												),
												['a' => ['href' => [], 'class' => [], 'target' => [], 'rel' => []]]
											);
											?></p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">2</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('Copy the token immediately', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php esc_html_e('Only a SHA-256 hash is stored, so the token itself can never be shown again. Copy it before you close the dialog.', 'my-site-hand'); ?></p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">3</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('Paste it on the dashboard', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php
											echo wp_kses(
												sprintf(
													/* translators: 1: opening <a> tag link to Dashboard page, 2: closing </a> tag */
													__('Go to the %1$sDashboard%2$s and paste the token into the Connect a client field. Nothing is saved — it is used only to build the commands in your browser.', 'my-site-hand'),
													'<a href="' . esc_url($my_site_hand_dashboard_page_url) . '" class="msh-doc-link">',
													'</a>'
												),
												['a' => ['href' => [], 'class' => [], 'target' => [], 'rel' => []]]
											);
											?></p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">4</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('Run the two generated commands', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php esc_html_e('The dashboard writes commands tailored to your operating system. Run step 1 to install the connector, then step 2 to register this site, and restart the client.', 'my-site-hand'); ?></p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Client setup -->
					<div id="msh-doc-panel-clients" class="msh-doc-panel">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php esc_html_e('How the connection works', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p class="msh-step-desc"><?php esc_html_e('Instead of installing software on your server, My Site Hand uses your existing WordPress site as the gateway. Here is what happens on every call:', 'my-site-hand'); ?></p>

								<ul class="msh-flow-list">
									<li class="msh-flow-item">
										<strong><?php esc_html_e('A secure REST endpoint', 'my-site-hand'); ?></strong> —
										<?php esc_html_e('The plugin opens one streamable channel on the standard WordPress REST API. Clients post to it to discover or run abilities.', 'my-site-hand'); ?>
									</li>
									<li class="msh-flow-item">
										<strong><?php esc_html_e('A local connector (mcp-remote)', 'my-site-hand'); ?></strong> —
										<?php esc_html_e('The setup command configures a small utility on your own machine. It bridges your desktop client and this site, carrying the Authorization header with every request.', 'my-site-hand'); ?>
									</li>
									<li class="msh-flow-item">
										<strong><?php esc_html_e('Mapping and execution', 'my-site-hand'); ?></strong> —
										<?php esc_html_e('Ask for your draft pages and the client turns that into a structured call. The plugin runs the matching ability, checks the token scope, writes an audit entry, and returns clean text.', 'my-site-hand'); ?>
									</li>
								</ul>
							</div>
						</div>

						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php esc_html_e('Claude Desktop', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<div class="msh-doc-alert">
									<div class="msh-doc-alert-icon">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
									</div>
									<div>
										<strong><?php esc_html_e('Get your exact commands', 'my-site-hand'); ?></strong><br>
										<?php
										echo wp_kses(
											sprintf(
												/* translators: 1: opening <a> tag link to Dashboard page, 2: closing </a> tag */
												__('Open the %1$sDashboard%2$s and paste your token. The setup commands appear with your endpoint and credentials already filled in.', 'my-site-hand'),
												'<a href="' . esc_url($my_site_hand_dashboard_page_url) . '" class="msh-doc-link">',
												'</a>'
											),
											['a' => ['href' => [], 'class' => [], 'target' => [], 'rel' => []]]
										);
										?>
									</div>
								</div>

								<h4 class="msh-step-title" style="margin: 22px 0 6px;"><?php esc_html_e('Manual config, if you prefer', 'my-site-hand'); ?></h4>
								<p class="msh-step-desc" style="margin-bottom: 12px;"><?php esc_html_e('Add this entry to your local claude_desktop_config.json instead:', 'my-site-hand'); ?></p>

								<div class="msh-mcp-block">
									<pre class="msh-pre"><code>{
  "mcpServers": {
    "my-site-hand": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "<?php echo esc_html($my_site_hand_sse_url); ?>",
        "--header",
        "Authorization: Bearer YOUR_TOKEN"
      ]
    }
  }
}</code></pre>
								</div>
							</div>
						</div>

						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php esc_html_e('Cursor and other IDEs', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p class="msh-step-desc"><?php esc_html_e('Cursor connects to the endpoint directly — no local connector needed.', 'my-site-hand'); ?></p>

								<ul class="msh-flow-list">
									<li class="msh-flow-item">
										<strong><?php esc_html_e('Open settings', 'my-site-hand'); ?></strong> —
										<?php esc_html_e('Cursor Settings (cog icon, top right) → Features → MCP.', 'my-site-hand'); ?>
									</li>
									<li class="msh-flow-item">
										<strong><?php esc_html_e('Add a new server', 'my-site-hand'); ?></strong> —
										<?php esc_html_e('Click "+ Add New MCP Server", name it My Site Hand, and set Type to HTTP.', 'my-site-hand'); ?>
									</li>
									<li class="msh-flow-item">
										<strong><?php esc_html_e('Paste the URL and header', 'my-site-hand'); ?></strong> —
										<?php esc_html_e('Use the endpoint below, and add Authorization: Bearer YOUR_TOKEN as a header.', 'my-site-hand'); ?>
									</li>
								</ul>

								<label class="msh-field-label" style="margin-top: 18px;"><?php esc_html_e('Your endpoint', 'my-site-hand'); ?></label>
								<div class="msh-doc-copy-block">
									<span class="msh-doc-copy-val"><?php echo esc_html($my_site_hand_sse_url); ?></span>
									<button type="button" class="msh-btn msh-btn--sm" onclick="mshDoc.copyText('<?php echo esc_js($my_site_hand_sse_url); ?>', this)">
										<?php echo wp_kses($my_site_hand_copy_svg, $my_site_hand_svg_allowed); ?>
										<?php esc_html_e('Copy', 'my-site-hand'); ?>
									</button>
								</div>

								<p class="msh-hint"><?php esc_html_e('The token must travel in the Authorization header. Passing it in the URL query string is disabled by default, and only worth enabling if your client cannot send headers.', 'my-site-hand'); ?></p>
							</div>
						</div>
					</div>

					<!-- Prompts -->
					<div id="msh-doc-panel-prompts" class="msh-doc-panel">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php esc_html_e('Example prompts', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p class="msh-step-desc" style="margin-bottom: 14px;"><?php esc_html_e('Real requests that work once a client is connected. Click any one to copy it.', 'my-site-hand'); ?></p>

								<div class="msh-prompt-grid">
									<?php foreach ($my_site_hand_prompt_groups as $my_site_hand_group): ?>
										<div class="msh-prompt-card">
											<div class="msh-prompt-header">
												<div class="msh-prompt-icon-wrap"><?php echo wp_kses($my_site_hand_group['icon'], $my_site_hand_svg_allowed); ?></div>
												<h4 class="msh-prompt-title"><?php echo esc_html($my_site_hand_group['title']); ?></h4>
											</div>
											<div class="msh-prompt-bubbles">
												<?php foreach ($my_site_hand_group['items'] as $my_site_hand_prompt): ?>
													<div class="msh-prompt-bubble" onclick="mshDoc.copyText('<?php echo esc_js($my_site_hand_prompt); ?>', this)"
														title="<?php esc_attr_e('Click to copy', 'my-site-hand'); ?>">
														<?php echo esc_html($my_site_hand_prompt); ?>
														<span class="msh-copy-prompt-btn"><?php echo wp_kses($my_site_hand_copy_svg, $my_site_hand_svg_allowed); ?></span>
													</div>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>

					<!-- Security -->
					<div id="msh-doc-panel-security" class="msh-doc-panel">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php esc_html_e('Security and permissions', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p class="msh-step-desc"><?php esc_html_e('Three layers decide whether a call is allowed: the ability must be enabled, the module it belongs to must be on, and the token must carry the scope for it.', 'my-site-hand'); ?></p>

								<div class="msh-stepper">
									<div class="msh-step-item">
										<div class="msh-step-badge">1</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('Hashed access tokens', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php esc_html_e('Every token is hashed with SHA-256 before it touches the database. Plaintext keys are never stored, so a database leak does not hand anyone a working credential.', 'my-site-hand'); ?></p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">2</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('Granular scopes', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php esc_html_e('Read lets an agent look but not touch. Write adds create and update. Admin unlocks the destructive abilities — deleting content, reading error logs, repairing tables. Custom lets you tick abilities one at a time.', 'my-site-hand'); ?></p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">3</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('IP allowlists and rate limits', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php esc_html_e('A token can be pinned to specific IPs or CIDR ranges. Hourly and daily caps are counted per token, so one runaway client cannot starve the others.', 'my-site-hand'); ?></p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">4</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php esc_html_e('A full audit trail', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc"><?php
											echo wp_kses(
												sprintf(
													/* translators: 1: opening <a> tag link to Audit Log page, 2: closing </a> tag */
													__('Every call is recorded as it happens. The %1$saudit log%2$s shows the ability requested, the payload sent, the calling IP, the result, and how long it took.', 'my-site-hand'),
													'<a href="' . esc_url($my_site_hand_audit_page_url) . '" class="msh-doc-link">',
													'</a>'
												),
												['a' => ['href' => [], 'class' => [], 'target' => [], 'rel' => []]]
											);
											?></p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- FAQ -->
					<div id="msh-doc-panel-faq" class="msh-doc-panel">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php esc_html_e('Troubleshooting', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<div class="msh-faq-stack">
									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php esc_html_e('Why can the AI not connect to my site?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer"><?php esc_html_e('Usually loopback routing on the host or a local server setup. Open About & Info and run the REST API loopback test to see whether your server can reach its own endpoints.', 'my-site-hand'); ?></p>
									</div>

									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php esc_html_e('Do I need Node.js?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer"><?php esc_html_e('Only for Claude Desktop, which reaches this site through the mcp-remote connector. Cursor and other HTTP-capable clients connect to the endpoint directly.', 'my-site-hand'); ?></p>
									</div>

									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php esc_html_e('Can I use it with VS Code or other editors?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer"><?php esc_html_e('Yes. Any client that speaks the Model Context Protocol — including VS Code extensions such as Roo Code or Cline — can load this plugin using the endpoint on the Client setup tab.', 'my-site-hand'); ?></p>
									</div>

									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php esc_html_e('Does the plugin send my data anywhere?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer"><?php esc_html_e('No. My Site Hand runs entirely on your WordPress host. Your content, logs, products, and configuration go only to the clients you connect, on the calls they make.', 'my-site-hand'); ?></p>
									</div>

									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php esc_html_e('A call came back as 429. What now?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer"><?php esc_html_e('The token hit its hourly or daily cap. Raise the limits under Settings → Limits, or wait for the window to roll over. Throttled calls are written to the audit log too.', 'my-site-hand'); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
	</div>

	<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
</div>

<script>
/**
 * Documentation tab switcher and clipboard helper.
 */
window.mshDoc = {
	switchTab: function (tabId) {
		var panels = document.querySelectorAll('.msh-doc-panel');
		for (var i = 0; i < panels.length; i++) {
			panels[i].classList.remove('msh-doc-panel--active');
		}

		var btns = document.querySelectorAll('.msh-doc-nav-btn');
		for (var j = 0; j < btns.length; j++) {
			btns[j].classList.remove('msh-doc-nav-btn--active');
		}

		var targetPanel = document.getElementById('msh-doc-panel-' + tabId);
		if (targetPanel) {
			targetPanel.classList.add('msh-doc-panel--active');
		}

		var targetBtn = document.querySelector('[data-tab="' + tabId + '"]');
		if (targetBtn) {
			targetBtn.classList.add('msh-doc-nav-btn--active');
		}
	},

	copyText: function (text, btnElement) {
		var done = function () {
			var originalHtml = btnElement.innerHTML;
			btnElement.innerHTML = '<?php echo esc_js(__('Copied', 'my-site-hand')); ?>';
			btnElement.style.background = 'var(--msh-cw)';
			btnElement.style.borderColor = 'var(--msh-c)';
			btnElement.style.color = 'var(--msh-c2)';

			setTimeout(function () {
				btnElement.innerHTML = originalHtml;
				btnElement.style.background = '';
				btnElement.style.borderColor = '';
				btnElement.style.color = '';
			}, 1500);
		};

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done).catch(function () {
				window.mshDoc._fallback(text, done);
			});
		} else {
			window.mshDoc._fallback(text, done);
		}
	},

	_fallback: function (text, done) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand('copy');
			done();
		} catch (err) {
			// Nothing further to try.
		}
		document.body.removeChild(ta);
	}
};
</script>
