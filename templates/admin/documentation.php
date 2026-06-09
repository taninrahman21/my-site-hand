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
?>

<style>
.msh-doc-layout {
	display: flex;
	flex-direction: row-reverse;
	gap: 32px;
	align-items: flex-start;
	margin-top: 24px;
}

.msh-doc-sidebar {
	width: 240px;
	flex-shrink: 0;
	background: #ffffff;
	border: 1px solid var(--msh-border);
	border-radius: var(--msh-radius);
	padding: 12px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	box-shadow: var(--msh-shadow-sm);
}

.msh-doc-nav-btn {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	background: transparent;
	border: none;
	border-radius: var(--msh-radius-sm);
	color: var(--msh-sidebar-text);
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	text-align: left;
	transition: all var(--msh-transition);
	width: 100%;
}

.msh-doc-nav-btn:hover {
	background: var(--msh-sidebar-hover);
	color: var(--msh-secondary);
}

.msh-doc-nav-btn--active {
	background: #eef2ff !important;
	color: var(--msh-primary) !important;
	font-weight: 600;
}

.msh-doc-nav-icon {
	color: var(--msh-text-muted);
	transition: color var(--msh-transition);
	flex-shrink: 0;
}

.msh-doc-nav-btn:hover .msh-doc-nav-icon,
.msh-doc-nav-btn--active .msh-doc-nav-icon {
	color: var(--msh-primary);
}

.msh-doc-content {
	flex: 1;
	min-width: 0;
}

.msh-doc-panel {
	display: none;
	animation: msh-fade-in 0.25s ease-out forwards;
}

.msh-doc-panel--active {
	display: block;
}

.msh-doc-card {
	background: var(--msh-surface);
	border-radius: var(--msh-radius);
	border: 1px solid var(--msh-border);
	box-shadow: var(--msh-shadow-sm);
	margin-bottom: 24px;
	overflow: hidden;
}

.msh-doc-card-header {
	padding: 20px 24px;
	border-bottom: 1px solid var(--msh-border);
	background: #ffffff;
}

.msh-doc-card-header h3 {
	margin: 0;
	font-size: 16px;
	font-weight: 700;
	color: var(--msh-secondary);
}

.msh-doc-card-body {
	padding: 24px;
}

/* Custom steppers for Quick Start */
.msh-stepper {
	display: flex;
	flex-direction: column;
	gap: 24px;
	position: relative;
	margin-top: 16px;
}

.msh-stepper::before {
	content: '';
	position: absolute;
	left: 20px;
	top: 10px;
	bottom: 10px;
	width: 2px;
	background: var(--msh-border);
	z-index: 1;
}

.msh-step-item {
	display: flex;
	gap: 20px;
	position: relative;
	z-index: 2;
}

.msh-step-badge {
	width: 42px;
	height: 42px;
	border-radius: 50%;
	background: #eef2ff;
	color: var(--msh-primary);
	border: 2px solid #ffffff;
	box-shadow: 0 0 0 1px var(--msh-border);
	display: flex;
	align-items: center;
	justify-content: center;
	font-weight: 700;
	font-size: 16px;
	flex-shrink: 0;
}

.msh-step-content {
	flex: 1;
	padding-top: 8px;
}

.msh-step-title {
	font-size: 15px;
	font-weight: 700;
	color: var(--msh-secondary);
	margin: 0 0 6px 0;
}

.msh-step-desc {
	font-size: 13px;
	color: var(--msh-text-secondary);
	line-height: 1.5;
	margin: 0;
}

.msh-doc-link {
	color: var(--msh-primary);
	font-weight: 600;
	text-decoration: underline;
	transition: color var(--msh-transition);
}

.msh-doc-link:hover {
	color: var(--msh-primary-hover);
}

/* Copy Config block styling */
.msh-doc-copy-block {
	background: #f8fafc;
	border: 1px solid var(--msh-border);
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px 16px;
	margin-top: 8px;
}

.msh-doc-copy-val {
	font-family: var(--msh-font-mono);
	font-size: 12px;
	color: var(--msh-text-secondary);
	word-break: break-all;
	user-select: all;
	padding-right: 12px;
}

/* Prompt Example Cards */
.msh-prompt-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
	margin-top: 16px;
}

.msh-prompt-card {
	border: 1px solid var(--msh-border);
	border-radius: 8px;
	background: var(--msh-surface-soft);
	padding: 16px 20px;
	display: flex;
	flex-direction: column;
	gap: 14px;
	transition: all var(--msh-transition);
}

.msh-prompt-card:hover {
	border-color: var(--msh-primary);
	background: #ffffff;
	box-shadow: var(--msh-shadow);
}

.msh-prompt-header {
	display: flex;
	align-items: center;
	gap: 12px;
}

.msh-prompt-icon-wrap {
	width: 36px;
	height: 36px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.msh-prompt-title {
	font-weight: 700;
	font-size: 14px;
	color: var(--msh-secondary);
	margin: 0;
}

.msh-prompt-bubbles {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.msh-prompt-bubble {
	background: #ffffff;
	border: 1px solid var(--msh-border);
	border-radius: 8px;
	padding: 12px 36px 12px 14px;
	font-size: 13px;
	color: var(--msh-text);
	position: relative;
	line-height: 1.4;
	cursor: pointer;
	transition: border-color var(--msh-transition), background var(--msh-transition);
}

.msh-prompt-bubble:hover {
	border-color: var(--msh-primary);
	background: #fafbff;
}

.msh-prompt-bubble::before {
	content: '“';
	font-weight: 700;
	color: var(--msh-primary);
	margin-right: 4px;
}

.msh-prompt-bubble::after {
	content: '”';
	font-weight: 700;
	color: var(--msh-primary);
	margin-left: 4px;
}

.msh-copy-prompt-btn {
	position: absolute;
	right: 12px;
	top: 50%;
	transform: translateY(-50%);
	background: transparent;
	border: none;
	color: var(--msh-text-muted);
	cursor: pointer;
	opacity: 0;
	transition: opacity var(--msh-transition), color var(--msh-transition);
	display: flex;
	align-items: center;
}

.msh-prompt-bubble:hover .msh-copy-prompt-btn {
	opacity: 1;
}

.msh-copy-prompt-btn:hover {
	color: var(--msh-primary);
}

/* FAQ styling */
.msh-faq-stack {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.msh-faq-item {
	border-bottom: 1px solid var(--msh-border);
	padding-bottom: 20px;
}

.msh-faq-item:last-child {
	border-bottom: none;
	padding-bottom: 0;
}

.msh-faq-question {
	font-weight: 700;
	font-size: 14px;
	color: var(--msh-secondary);
	margin: 0 0 8px 0;
}

.msh-faq-answer {
	font-size: 13px;
	color: var(--msh-text-secondary);
	line-height: 1.5;
	margin: 0;
}

.msh-doc-alert {
	display: flex;
	gap: 12px;
	padding: 16px 20px;
	background: #ecfdf5;
	border: 1px solid #d1fae5;
	border-radius: 8px;
	color: #065f46;
	margin-bottom: 24px;
	font-size: 13px;
	line-height: 1.5;
}

.msh-doc-alert-icon {
	flex-shrink: 0;
	color: #10b981;
}

/* Under the hood list style */
.msh-flow-list {
	list-style: none;
	padding: 0;
	margin: 20px 0 0;
}

.msh-flow-item {
	position: relative;
	padding-left: 28px;
	margin-bottom: 16px;
	font-size: 13px;
	color: var(--msh-text-secondary);
	line-height: 1.5;
}

.msh-flow-item::before {
	content: '→';
	position: absolute;
	left: 0;
	top: 0;
	font-size: 16px;
	font-weight: 700;
	color: var(--msh-primary);
}

@media (max-width: 960px) {
	.msh-doc-layout {
		flex-direction: column;
	}
	.msh-doc-sidebar {
		width: 100%;
	}
	.msh-prompt-grid {
		grid-template-columns: 1fr;
	}
}
</style>

<div class="msh-wrap">
	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content">
		<div class="msh-container">
			<div class="msh-page-header">
				<h2><?php echo esc_html__('Documentation & User Guide', 'my-site-hand'); ?></h2>
				<p class="msh-page-desc"><?php echo esc_html__('Learn how to connect, configure, and communicate with your WordPress site using AI agents.', 'my-site-hand'); ?></p>
			</div>

			<div class="msh-doc-layout">
				<!-- Left sidebar tabs -->
				<div class="msh-doc-sidebar">
					<button class="msh-doc-nav-btn msh-doc-nav-btn--active" data-tab="quickstart" onclick="mshDoc.switchTab('quickstart')">
						<svg class="msh-doc-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
						<?php echo esc_html__('Quick Start', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="clients" onclick="mshDoc.switchTab('clients')">
						<svg class="msh-doc-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
						<?php echo esc_html__('Client Setup', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="prompts" onclick="mshDoc.switchTab('prompts')">
						<svg class="msh-doc-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
						<?php echo esc_html__('Example Prompts', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="security" onclick="mshDoc.switchTab('security')">
						<svg class="msh-doc-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
						<?php echo esc_html__('Security & Audits', 'my-site-hand'); ?>
					</button>
					<button class="msh-doc-nav-btn" data-tab="faq" onclick="mshDoc.switchTab('faq')">
						<svg class="msh-doc-nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
						<?php echo esc_html__('Troubleshooting & FAQ', 'my-site-hand'); ?>
					</button>
				</div>

				<!-- Right Content Panels -->
				<div class="msh-doc-content">

					<!-- Panel 1: Quick Start -->
					<div id="msh-doc-panel-quickstart" class="msh-doc-panel msh-doc-panel--active">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php echo esc_html__('Connecting in Seconds', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p style="margin: 0 0 24px; font-size: 14px; color: var(--msh-text-secondary); line-height: 1.6;">
									<?php echo esc_html__('My Site Hand bridges the gap between WordPress and AI clients using the Model Context Protocol (MCP). By connecting your AI assistant directly to WordPress, you can write posts, review system health, run SEO diagnostics, and check WooCommerce details using simple, conversational commands.', 'my-site-hand'); ?>
								</p>

								<div class="msh-stepper">
									<div class="msh-step-item">
										<div class="msh-step-badge">1</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php echo esc_html__('Generate an API Access Token', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: 1: opening <a> tag link to API Tokens page, 2: closing </a> tag */
														__( 'Go to the %1$sAPI Tokens page%2$s. Click the "New Token" button. Enter a name for the token (for example: "My Laptop Claude") and select a permission scope (Read, Write, or Admin). Click "Generate Token" to create it.', 'my-site-hand' ),
														'<a href="' . esc_url( $my_site_hand_tokens_page_url ) . '" class="msh-doc-link" target="_blank" rel="noopener noreferrer">',
														'</a>'
													),
													[ 'a' => [ 'href' => [], 'class' => [], 'target' => [], 'rel' => [] ] ]
												);
												?>
											</p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">2</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php echo esc_html__('Copy the Hashed Token Immediately', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc">
												<?php echo esc_html__('A success window will pop up showing your secure key. Copy this key immediately! For maximum safety, tokens are strongly hashed and will never be shown again once you close the popup.', 'my-site-hand'); ?>
											</p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">3</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php echo esc_html__('Register Your Token on the Dashboard', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc">
												<?php
												echo wp_kses(
													sprintf(
														/* translators: 1: opening <a> tag link to Dashboard page, 2: closing </a> tag */
														__( 'Next, go to the %1$sDashboard%2$s. Paste your newly copied token into the input box and click "Save Token".', 'my-site-hand' ),
														'<a href="' . esc_url( $my_site_hand_dashboard_page_url ) . '" class="msh-doc-link" target="_blank" rel="noopener noreferrer">',
														'</a>'
													),
													[ 'a' => [ 'href' => [], 'class' => [], 'target' => [], 'rel' => [] ] ]
												);
												?>
											</p>
										</div>
									</div>

									<div class="msh-step-item">
										<div class="msh-step-badge">4</div>
										<div class="msh-step-content">
											<h4 class="msh-step-title"><?php echo esc_html__('Follow the Guided Terminal Commands', 'my-site-hand'); ?></h4>
											<p class="msh-step-desc">
												<?php echo esc_html__('Once your token is saved, the Dashboard will automatically generate custom terminal commands tailored to your local system. Run Step 1 in your terminal to install the client utility, then run Step 2 to configure Claude Desktop automatically.', 'my-site-hand'); ?>
											</p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Panel 2: Client Setup -->
					<div id="msh-doc-panel-clients" class="msh-doc-panel">
						<!-- Under the Hood configuration -->
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php echo esc_html__('How My Site Hand Works Under the Hood', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p style="margin: 0; font-size: 13px; color: var(--msh-text-secondary); line-height: 1.6;">
									<?php echo esc_html__('Instead of forcing you to install complex software on your server, My Site Hand uses your existing WordPress site itself as the connection gateway. Here is exactly what happens when your AI client communicates with your site:', 'my-site-hand'); ?>
								</p>

								<ul class="msh-flow-list">
									<li class="msh-flow-item">
										<strong><?php echo esc_html__('Secure Web Endpoint (SSE)', 'my-site-hand'); ?></strong> — 
										<?php echo esc_html__('The plugin opens a secure web channel on your site using the standard WordPress REST API. AI clients send messages to this endpoint to discover or run tools.', 'my-site-hand'); ?>
									</li>
									<li class="msh-flow-item">
										<strong><?php echo esc_html__('Local Connector (mcp-remote)', 'my-site-hand'); ?></strong> — 
										<?php echo esc_html__('When you run the setup script, it configures a local utility called mcp-remote on your machine. This lightweight utility serves as a secure bridge, passing commands between your local Claude Desktop app and your remote WordPress site.', 'my-site-hand'); ?>
									</li>
									<li class="msh-flow-item">
										<strong><?php echo esc_html__('Dynamic Mapping & Execution', 'my-site-hand'); ?></strong> — 
										<?php echo esc_html__('When you ask Claude "List my draft pages", Claude translates this request into a structured JSON query, passes it to mcp-remote, which forwards it to the plugin. The plugin executes WordPress functions safely (using read-only DB calls), converts the pages to clean text, and sends them back to Claude.', 'my-site-hand'); ?>
									</li>
								</ul>
							</div>
						</div>

						<!-- Claude Desktop -->
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php echo esc_html__('Claude Desktop app (Automated Connection)', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p style="margin: 0 0 16px; font-size: 13px; color: var(--msh-text-secondary); line-height: 1.5;">
									<?php echo esc_html__('The Dashboard provides a guided two-step command panel. Once you copy your token and save it on the dashboard, you can run the commands to auto-configure Claude Desktop for Windows, macOS, or Linux.', 'my-site-hand'); ?>
								</p>
								
								<div class="msh-doc-alert">
									<div class="msh-doc-alert-icon">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
									</div>
									<div>
										<strong><?php echo esc_html__('Get your custom commands:', 'my-site-hand'); ?></strong><br>
										<?php
										echo wp_kses(
											sprintf(
												/* translators: 1: opening <a> tag link to Dashboard page, 2: closing </a> tag */
												__( 'Go to the %1$sDashboard%2$s, paste your token, and the system will instantly output the exact setup commands with your credentials pre-configured.', 'my-site-hand' ),
												'<a href="' . esc_url( $my_site_hand_dashboard_page_url ) . '" class="msh-doc-link" target="_blank" rel="noopener noreferrer">',
												'</a>'
											),
											[ 'a' => [ 'href' => [], 'class' => [], 'target' => [], 'rel' => [] ] ]
										);
										?>
									</div>
								</div>

								<h4 style="margin: 0 0 8px 0; font-size: 14px;"><?php echo esc_html__('Manual Claude Config Structure', 'my-site-hand'); ?></h4>
								<p style="margin: 0 0 12px 0; font-size: 13px; color: var(--msh-text-secondary);"><?php echo esc_html__('If you prefer manual configuration, you can insert this setup entry into your local Claude Desktop config file:', 'my-site-hand'); ?></p>
								
								<div class="msh-tab-content msh-tab-content--active" style="animation: none;">
									<div class="msh-mcp-block" style="border-radius: 6px; overflow: hidden; margin-top: 0;">
										<pre><code>{
  "mcpServers": {
    "my-site-hand": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "<?php echo esc_js($my_site_hand_sse_url); ?>"
      ],
      "env": {
        "MASH_API_KEY": "YOUR_SECRET_TOKEN"
      }
    }
  }
}</code></pre>
									</div>
								</div>
							</div>
						</div>

						<!-- Cursor IDE -->
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php echo esc_html__('Cursor IDE Setup', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p style="margin: 0 0 16px; font-size: 13px; color: var(--msh-text-secondary); line-height: 1.5;">
									<?php echo esc_html__('Cursor supports connecting directly to remote Server-Sent Events (SSE) channels. You can register My Site Hand as a tool in Cursor features settings:', 'my-site-hand'); ?>
								</p>

								<div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
									<div>
										<strong style="display: block; font-size: 13px; margin-bottom: 4px;"><?php echo esc_html__('Step 1: Open Settings', 'my-site-hand'); ?></strong>
										<span style="font-size: 13px; color: var(--msh-text-secondary);"><?php echo esc_html__('Open Cursor, go to Cursor Settings (cog icon in top right) > Features > MCP.', 'my-site-hand'); ?></span>
									</div>
									<div>
										<strong style="display: block; font-size: 13px; margin-bottom: 4px;"><?php echo esc_html__('Step 2: Add New Server', 'my-site-hand'); ?></strong>
										<span style="font-size: 13px; color: var(--msh-text-secondary);"><?php echo esc_html__('Click the "+ Add New MCP Server" button.', 'my-site-hand'); ?></span>
									</div>
									<div>
										<strong style="display: block; font-size: 13px; margin-bottom: 4px;"><?php echo esc_html__('Step 3: Enter Credentials', 'my-site-hand'); ?></strong>
										<span style="font-size: 13px; color: var(--msh-text-secondary);"><?php echo esc_html__('Configure the server panel as follows:', 'my-site-hand'); ?></span>
										<ul style="margin: 6px 0 0 20px; font-size: 13px; color: var(--msh-text-secondary); padding: 0;">
											<li>• <strong>Name</strong>: <code>My Site Hand</code></li>
											<li>• <strong>Type</strong>: <code>SSE</code></li>
											<li>• <strong>URL</strong>: Copy the web address link shown below</li>
										</ul>
									</div>
								</div>

								<strong style="font-size: 13px; display: block; margin-bottom: 6px;"><?php echo esc_html__('Your SSE URL Endpoint:', 'my-site-hand'); ?></strong>
								<div class="msh-doc-copy-block">
									<span class="msh-doc-copy-val"><?php echo esc_html($my_site_hand_sse_url); ?></span>
									<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" onclick="mshDoc.copyText('<?php echo esc_js($my_site_hand_sse_url); ?>', this)">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
										<?php echo esc_html__('Copy', 'my-site-hand'); ?>
									</button>
								</div>
								
								<p style="margin: 12px 0 0; font-size: 12px; color: var(--msh-text-muted); line-height: 1.4;">
									<em>* Note: Make sure to include the API token parameter when connecting manually, or authenticate via the environment headers. Using the full copied URL with your token is recommended.</em>
								</p>
							</div>
						</div>
					</div>

					<!-- Panel 3: Prompts & Use Cases -->
					<div id="msh-doc-panel-prompts" class="msh-doc-panel">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php echo esc_html__('Example Prompts & Use Cases', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p style="margin: 0 0 16px; font-size: 14px; color: var(--msh-text-secondary); line-height: 1.5;">
									<?php echo esc_html__('To help you start working with your AI assistant, we have compiled a set of real-world use cases. Click on any bubble to copy the prompt directly to your clipboard.', 'my-site-hand'); ?>
								</p>

								<div class="msh-prompt-grid">
									<!-- Content Operations -->
									<div class="msh-prompt-card">
										<div class="msh-prompt-header">
											<div class="msh-prompt-icon-wrap" style="background: #eef2ff; color: var(--msh-primary);">
												<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
											</div>
											<h4 class="msh-prompt-title"><?php echo esc_html__('Content Management', 'my-site-hand'); ?></h4>
										</div>
										<div class="msh-prompt-bubbles">
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('List the 5 most recent published posts on my site and summarize them.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('List the 5 most recent published posts on my site and summarize them.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Write a draft post about the importance of web accessibility and save it.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Write a draft post about the importance of web accessibility and save it.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Find page ID 12, append a section explaining our cookie policy, and set its status to draft.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Find page ID 12, append a section explaining our cookie policy, and set its status to draft.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
										</div>
									</div>

									<!-- SEO Optimization -->
									<div class="msh-prompt-card">
										<div class="msh-prompt-header">
											<div class="msh-prompt-icon-wrap" style="background: #ecfdf5; color: #10b981;">
												<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
											</div>
											<h4 class="msh-prompt-title"><?php echo esc_html__('SEO Power-Tools', 'my-site-hand'); ?></h4>
										</div>
										<div class="msh-prompt-bubbles">
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Audit the SEO keyword of our main service page and advise if it is optimized.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Audit the SEO keyword of our main service page and advise if it is optimized.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Generate and write an SEO-optimized meta description for my last blog post.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Generate and write an SEO-optimized meta description for my last blog post.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Scan my latest published article for any broken outbound links.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Scan my latest published article for any broken outbound links.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
										</div>
									</div>

									<!-- WooCommerce -->
									<div class="msh-prompt-card">
										<div class="msh-prompt-header">
											<div class="msh-prompt-icon-wrap" style="background: #eff6ff; color: #2563eb;">
												<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
											</div>
											<h4 class="msh-prompt-title"><?php echo esc_html__('WooCommerce Store', 'my-site-hand'); ?></h4>
										</div>
										<div class="msh-prompt-bubbles">
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Provide a summary of today\'s WooCommerce sales, revenue, and total orders.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Provide a summary of today\'s WooCommerce sales, revenue, and total orders.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Search for order ID #450 and tell me who the customer is and its status.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Search for order ID #450 and tell me who the customer is and its status.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Check if there are any items currently out of stock or low in quantity.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Check if there are any items currently out of stock or low in quantity.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
										</div>
									</div>

									<!-- Diagnostics -->
									<div class="msh-prompt-card">
										<div class="msh-prompt-header">
											<div class="msh-prompt-icon-wrap" style="background: #fff7ed; color: #ea580c;">
												<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>
											</div>
											<h4 class="msh-prompt-title"><?php echo esc_html__('Diagnostics & Alt-Text', 'my-site-hand'); ?></h4>
										</div>
										<div class="msh-prompt-bubbles">
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Provide a system health report of my server requirements.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Provide a system health report of my server requirements.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Scan my error log file and list the most recent warning messages.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Scan my error log file and list the most recent warning messages.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
											<div class="msh-prompt-bubble" onclick="mshDoc.copyText('Find images in the media library that are missing alt tags.', this)" title="<?php esc_attr_e('Click to copy prompt', 'my-site-hand'); ?>">
												<?php echo esc_html__('Find images in the media library that are missing alt tags.', 'my-site-hand'); ?>
												<span class="msh-copy-prompt-btn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg></span>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Panel 4: Security & Auditing -->
					<div id="msh-doc-panel-security" class="msh-doc-panel">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php echo esc_html__('Security Protocols & Permissions', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<p style="margin: 0 0 20px; font-size: 14px; color: var(--msh-text-secondary); line-height: 1.6;">
									<?php echo esc_html__('We treat website security as a top priority. My Site Hand implements robust protective policies so that you have total control over what AI agents can query and manipulate on your server.', 'my-site-hand'); ?>
								</p>

								<div style="display: flex; flex-direction: column; gap: 20px;">
									<div>
										<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: var(--msh-secondary);"><?php echo esc_html__('1. Hashed Access Tokens', 'my-site-hand'); ?></h4>
										<p style="margin: 0; font-size: 13px; color: var(--msh-text-secondary); line-height: 1.5;">
											<?php echo esc_html__('All access keys generated inside My Site Hand are immediately hashed using the SHA-256 standard before database storage. Plaintext keys are never stored in your WordPress database, rendering them immune to database leaks.', 'my-site-hand'); ?>
										</p>
									</div>

									<div>
										<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: var(--msh-secondary);"><?php echo esc_html__('2. Granular Capability Scopes', 'my-site-hand'); ?></h4>
										<p style="margin: 0; font-size: 13px; color: var(--msh-text-secondary); line-height: 1.5;">
											<?php echo esc_html__('You can restrict tokens to specific permission levels:', 'my-site-hand'); ?>
										</p>
										<ul style="margin: 8px 0 0 20px; font-size: 13px; color: var(--msh-text-secondary); padding: 0;">
											<li style="margin-bottom: 6px;">• <strong><?php echo esc_html__('Read-Only', 'my-site-hand'); ?></strong> – <?php echo esc_html__('Allows the agent to read posts, pages, categories, comments, media metadata, and general setup options, but blocks all editing, creating, and deleting capabilities.', 'my-site-hand'); ?></li>
											<li style="margin-bottom: 6px;">• <strong><?php echo esc_html__('Read-Write', 'my-site-hand'); ?></strong> – <?php echo esc_html__('Allows standard CRUD (Create, Read, Update, Delete) access to your posts, pages, and products, but prevents the agent from requesting core diagnostics or system error logs.', 'my-site-hand'); ?></li>
											<li style="margin-bottom: 6px;">• <strong><?php echo esc_html__('Admin Access', 'my-site-hand'); ?></strong> – <?php echo esc_html__('Unlocks full system features, including error log views, PHP parameters check, and database health fixes.', 'my-site-hand'); ?></li>
										</ul>
									</div>

									<div>
										<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: var(--msh-secondary);"><?php echo esc_html__('3. Immutable Real-Time Audit Log', 'my-site-hand'); ?></h4>
										<p style="margin: 0; font-size: 13px; color: var(--msh-text-secondary); line-height: 1.5;">
											<?php
											echo wp_kses(
												sprintf(
													/* translators: 1: opening <a> tag link to Audit Log page, 2: closing </a> tag */
													__( 'Every single action the AI agent performs is recorded in real-time. Navigate to the %1$sAudit Log page%2$s to view the exact capability requested, parameters sent, calling IP address, and execution duration.', 'my-site-hand' ),
													'<a href="' . esc_url( $my_site_hand_audit_page_url ) . '" class="msh-doc-link" target="_blank" rel="noopener noreferrer">',
													'</a>'
												),
												[ 'a' => [ 'href' => [], 'class' => [], 'target' => [], 'rel' => [] ] ]
											);
											?>
										</p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Panel 5: FAQ & Troubleshooting -->
					<div id="msh-doc-panel-faq" class="msh-doc-panel">
						<div class="msh-doc-card">
							<div class="msh-doc-card-header">
								<h3><?php echo esc_html__('Frequently Asked Questions & Support', 'my-site-hand'); ?></h3>
							</div>
							<div class="msh-doc-card-body">
								<div class="msh-faq-stack">
									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php echo esc_html__('Why is the AI unable to connect to my site?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer">
											<?php echo esc_html__('This is usually caused by loopback routing issues on your hosting or local server configuration. Go to the "About & Info" tab and run the "REST API Loopback" test to diagnose if your server can make secure outgoing connections to itself.', 'my-site-hand'); ?>
										</p>
									</div>

									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php echo esc_html__('Is Node.js required for the plugin to work?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer">
											<?php echo esc_html__('Yes, if you want to use the automated integration for Claude Desktop via mcp-remote. Node.js is required to execute the connection utility from your local terminal.', 'my-site-hand'); ?>
										</p>
									</div>

									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php echo esc_html__('Can I use this plugin with VS Code or other developer editors?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer">
											<?php echo esc_html__('Absolutely. Any editor or agent client supporting the Model Context Protocol (like VS Code extensions Roo Code or Cline) can load this plugin using the SSE URL provided in the Client Setup tab.', 'my-site-hand'); ?>
										</p>
									</div>

									<div class="msh-faq-item">
										<h4 class="msh-faq-question"><?php echo esc_html__('Does this plugin send my data to external servers?', 'my-site-hand'); ?></h4>
										<p class="msh-faq-answer">
											<?php echo esc_html__('No, My Site Hand operates entirely locally on your WordPress host. Your content, logs, products, and configurations are never transmitted to external services, maintaining complete data isolation.', 'my-site-hand'); ?>
										</p>
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
</div>

<script>
/**
 * Documentation Tab Switcher & Utilities.
 */
window.mshDoc = {
	switchTab: function(tabId) {
		// Hide all panels.
		var panels = document.querySelectorAll('.msh-doc-panel');
		for (var i = 0; i < panels.length; i++) {
			panels[i].style.display = 'none';
			panels[i].classList.remove('msh-doc-panel--active');
		}

		// Remove active status from all navigation buttons.
		var btns = document.querySelectorAll('.msh-doc-nav-btn');
		for (var j = 0; j < btns.length; j++) {
			btns[j].classList.remove('msh-doc-nav-btn--active');
		}

		// Show target panel.
		var targetPanel = document.getElementById('msh-doc-panel-' + tabId);
		if (targetPanel) {
			targetPanel.style.display = 'block';
			targetPanel.classList.add('msh-doc-panel--active');
		}

		// Set active button.
		var targetBtn = document.querySelector('[data-tab="' + tabId + '"]');
		if (targetBtn) {
			targetBtn.classList.add('msh-doc-nav-btn--active');
		}
	},

	copyText: function(text, btnElement) {
		navigator.clipboard.writeText(text).then(function() {
			var originalHtml = btnElement.innerHTML;
			btnElement.innerHTML = '<?php echo esc_js(__('Copied!', 'my-site-hand')); ?>';
			
			// Visual styling on success.
			var originalBg = btnElement.style.background;
			btnElement.style.background = '#ecfdf5';
			btnElement.style.borderColor = '#10b981';
			btnElement.style.color = '#065f46';

			setTimeout(function() {
				btnElement.innerHTML = originalHtml;
				btnElement.style.background = originalBg;
				btnElement.style.borderColor = '';
				btnElement.style.color = '';
			}, 1500);
		}).catch(function(err) {
			console.error('Failed to copy text: ', err);
		});
	}
};
</script>
