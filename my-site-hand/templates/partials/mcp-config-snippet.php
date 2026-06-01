<?php
/**
 * MCP configuration snippet partial - Multi-client edition.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;

$my_site_hand_mcp_endpoint = get_rest_url( null, 'my-site-hand/v1/mcp' );
$my_site_hand_config_json  = wp_json_encode( [
	'mcpServers' => [
		'my-site-hand' => [
			'command' => 'npx',
			'args'    => [ '-y', '@builtbytanin/mcp-remote', '--url', $my_site_hand_mcp_endpoint ],
		],
	],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
?>
<div class="msh-mcp-snippet">
	<!-- Client Tabs -->
	<div class="msh-client-tabs">
		<button type="button" class="msh-tab-btn msh-tab-btn--active" data-client="claude" onclick="msh.switchClientTab('claude')">
			Claude Desktop
		</button>
		<button type="button" class="msh-tab-btn" data-client="cursor" onclick="msh.switchClientTab('cursor')">
			Cursor
		</button>
		<button type="button" class="msh-tab-btn" data-client="windsurf" onclick="msh.switchClientTab('windsurf')">
			Windsurf
		</button>
		<button type="button" class="msh-tab-btn" data-client="generic" onclick="msh.switchClientTab('generic')">
			Generic
		</button>
	</div>

	<!-- Tab Content: Claude Desktop -->
	<div class="msh-tab-content msh-tab-content--active" data-client="claude">
		<div class="msh-snippet-header">
			<div class="msh-snippet-title">claude_desktop_config.json</div>
			<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" onclick="msh.copyMcpConfig()">
				<span id="msh-copy-config-text-claude"><?php echo esc_html__( 'Copy Json', 'my-site-hand' ); ?></span>
			</button>
		</div>
		<div class="msh-snippet-code-container">
			<pre id="msh-mcp-config-code-claude" class="msh-snippet-code"><code><?php echo esc_html( $my_site_hand_config_json ); ?></code></pre>
		</div>
		<div class="msh-setup-instructions">
			<h4><?php echo esc_html__( 'Setup Instructions', 'my-site-hand' ); ?></h4>
			<ul class="msh-setup-steps">
				<li><?php echo esc_html__( 'Locate your Claude Desktop config file.', 'my-site-hand' ); ?></li>
				<li><?php echo esc_html__( 'Add the JSON block above to your mcpServers section.', 'my-site-hand' ); ?></li>
				<li><?php echo esc_html__( 'Restart Claude Desktop.', 'my-site-hand' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Tab Content: Cursor -->
	<div class="msh-tab-content" data-client="cursor">
		<div class="msh-snippet-header">
			<div class="msh-snippet-title">Cursor Settings</div>
			<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" onclick="msh.copyMcpConfig()">
				<span id="msh-copy-config-text-cursor"><?php echo esc_html__( 'Copy URL', 'my-site-hand' ); ?></span>
			</button>
		</div>
		<div class="msh-snippet-code-container" style="display:none;">
			<pre id="msh-mcp-config-code-cursor" class="msh-snippet-code"><code><?php echo esc_url( $my_site_hand_mcp_endpoint ); ?></code></pre>
		</div>
		<div class="msh-setup-instructions">
			<h4><?php echo esc_html__( 'Setup Instructions', 'my-site-hand' ); ?></h4>
			<ul class="msh-setup-steps">
				<li><?php echo esc_html__( 'Open Cursor and navigate to Settings > Features > MCP.', 'my-site-hand' ); ?></li>
				<li><?php echo esc_html__( 'Click "+ Add Server" and paste the endpoint URL.', 'my-site-hand' ); ?></li>
				<li><?php echo esc_html__( 'Add an Authorization header with your Bearer token.', 'my-site-hand' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Tab Content: Windsurf -->
	<div class="msh-tab-content" data-client="windsurf">
		<div class="msh-snippet-header">
			<div class="msh-snippet-title">Windsurf Settings</div>
			<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" onclick="msh.copyMcpConfig()">
				<span id="msh-copy-config-text-windsurf"><?php echo esc_html__( 'Copy Json', 'my-site-hand' ); ?></span>
			</button>
		</div>
		<div class="msh-snippet-code-container">
			<pre id="msh-mcp-config-code-windsurf" class="msh-snippet-code"><code><?php echo esc_html( $my_site_hand_config_json ); ?></code></pre>
		</div>
		<div class="msh-setup-instructions">
			<h4><?php echo esc_html__( 'Setup Instructions', 'my-site-hand' ); ?></h4>
			<ul class="msh-setup-steps">
				<li><?php echo esc_html__( 'Open Windsurf and access MCP configuration.', 'my-site-hand' ); ?></li>
				<li><?php echo esc_html__( 'Add a new HTTP server with the provided configuration.', 'my-site-hand' ); ?></li>
			</ul>
		</div>
	</div>

	<!-- Tab Content: Generic -->
	<div class="msh-tab-content" data-client="generic">
		<div class="msh-snippet-header">
			<div class="msh-snippet-title">HTTP Endpoint</div>
			<button type="button" class="msh-btn msh-btn--ghost msh-btn--sm" onclick="msh.copyMcpConfig()">
				<span id="msh-copy-config-text-generic"><?php echo esc_html__( 'Copy URL', 'my-site-hand' ); ?></span>
			</button>
		</div>
		<div class="msh-snippet-code-container">
			<pre id="msh-mcp-config-code-generic" class="msh-snippet-code"><code><?php echo esc_url( $my_site_hand_mcp_endpoint ); ?></code></pre>
		</div>
		<div class="msh-setup-instructions">
			<p style="color:var(--msh-text-secondary);"><?php echo esc_html__( 'Use this standard HTTP endpoint with any MCP-compatible client.', 'my-site-hand' ); ?></p>
		</div>
	</div>

	<div class="msh-snippet-footer">
		<p>
			<?php echo esc_html__( 'Need a token?', 'my-site-hand' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand-tokens' ) ); ?>"><?php echo esc_html__( 'Generate one here', 'my-site-hand' ); ?></a>
		</p>
	</div>
</div>
