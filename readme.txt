=== My Site Hand (AI) ===
Contributors: builtbytanin
Tags: ai, mcp, claude, automation, agent
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Claude Desktop and other AI agents to WordPress in seconds using automated MCP commands.

== Description ==

**My Site Hand (AI) for WordPress** is the ultimate bridge between your WordPress site and AI agents (like Claude Desktop, Cursor, and VS Code). Built on the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/), it allows AI assistants to manage your site through simple natural language.

What makes My Site Hand special is the **Zero-Config Setup**. Instead of manually editing JSON files, My Site Hand generates automated terminal commands that configure your AI clients for you instantly.

### 🚀 Key Features:

*   **One-Click Automated Setup** – No manual JSON editing. Copy a single command to connect Claude Desktop automatically.
*   **45 Built-in Abilities** – Comprehensive tools for Content, SEO, WooCommerce, Media, and Diagnostics.
*   **Cross-Platform Support** – Dedicated automated setup steps for **Windows, macOS, and Linux**.
*   **Native MCP Support** – Full JSON-RPC 2.0 server implementation compatible with all MCP-compliant clients.
*   **Enterprise-Grade Security** – All tokens are SHA-256 hashed. Granular permissions ensure AI agents only access what you allow.
*   **Detailed Audit Logging** – Watch every action the AI takes in real-time with our built-in logs.

—

### 📦 Included Modules:

**1. Content Management**
List, read, create, and update posts, pages, and custom post types.

**2. SEO Power-Tools (Yoast & RankMath)**
AI-driven SEO audits, meta-description updates, and focus keyword management.

**3. WooCommerce (Auto-enabled)**
Manage products, orders, and store analytics through natural language.

**4. Site Diagnostics & Media**
Site health reports, error log monitoring, bulk media alt-text updates, and more.

== Installation ==

1.  Install and activate the plugin.
2.  Go to **My Site Hand > API Tokens** to generate your secure access key.
3.  Go to the **My Site Hand Dashboard** and paste your token.
4.  **Automated Connection**:
    *   **Step 1**: Run the provided command to install `mcp-remote`.
    *   **Step 2**: Copy the generated "Connect" command and run it in your terminal.
5.  Restart Claude Desktop, and you're ready!

== Frequently Asked Questions ==

= Do I need to edit the Claude config file manually? =
No! My Site Hand generates a command that uses `mcp-remote` to handle the configuration for you automatically on Windows, Mac, or Linux.

= Is Node.js required? =
Yes, to use the automated connection feature, you need Node.js installed on your computer to run the `mcp-remote` utility.

= Can I use this with Cursor or VS Code? =
Absolutely. The Dashboard provides the MCP Server URL which you can paste directly into Cursor or any IDE that supports MCP.


== Changelog ==

= 1.0.0 =
*   Official Initial Release.
*   Automated setup for Claude Desktop using `mcp-remote`.
*   45 abilities across Content, SEO, and WooCommerce modules.
*   Secure hashed tokens and real-time audit logging.

== External Services ==

= Link Checker (SEO Module) =
When the check-broken-links ability is called via the MCP API, this plugin
sends HTTP HEAD requests to URLs found in your post content to verify they
are reachable. No personal user data is transmitted — only a standard HTTP
request is made to each URL being checked. This is triggered only when
explicitly called by an authorized API token holder.

No data is sent to any third-party analytics or tracking service by this plugin.




