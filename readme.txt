=== My Site Hand (AI) ===
Contributors: builtbytanin
Tags: ai, claude, cursor, agent, automation
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let Claude, Cursor, and AI assistants write posts, manage WooCommerce, optimize SEO, and run diagnostics on your site using simple natural language.

== Description ==

**My Site Hand (AI)** is the ultimate bridge between your WordPress site and your favorite AI tools (like Claude Desktop, Cursor, and VS Code). 

Think of it as giving your AI assistant a secure, virtual "hand" to directly type into your WordPress admin, draft posts, manage products, check site health, and optimize SEO—eliminating the hassle of endless copy-pasting.

Built on the open standard **Model Context Protocol (MCP)** developed by Anthropic (the creators of Claude), this plugin securely exposes safely-gated capabilities (Abilities) to your AI clients so they can interact with your site using simple natural language commands.

### 🚀 What You Can Do with My Site Hand:
*   **Write & Edit Content** – Ask Claude to draft, edit, format, or publish posts and pages directly on your site.
*   **AI-Driven SEO Audits** – Let your AI scan Yoast or RankMath metadata, optimize it for targeted keywords, and update titles/descriptions instantly.
*   **WooCommerce Store Management** – Ask your AI to list low-stock items, draft product listings, check recent orders, or summarize sales analytics.
*   **Site Health & Diagnostics** – Let the AI inspect PHP error logs, check loopback status, or look up site details to debug issues.
*   **Zero-Config Automated Setup** – No manual editing of hidden JSON config files. Copy a single command from your WordPress dashboard, paste it into your terminal, and Claude connects automatically!
*   **Total Safety & Security** – All tokens are SHA-256 hashed. You can toggle specific abilities (like writing or deleting) on/off with simple switches, and monitor everything the AI does in real-time with the built-in **Audit Log**.

---

### 📦 Included Modules:

1.  **Content Manager** (Posts, Pages, and Custom Post Types)
2.  **SEO Power-Tools** (Compatible with Yoast SEO & RankMath)
3.  **WooCommerce Store Layer** (Products, Orders, and Analytics)
4.  **Site Diagnostics & Health** (Error log viewing, system details, image alt-text updates)

---

### ⚠️ Important Requirements (Honest & Transparent):
*   **Node.js**: The automated desktop bridge (`mcp-remote`) requires Node.js installed on your local computer to run.
*   **SSL/HTTPS**: For security, your site must run on HTTPS so your API tokens remain encrypted in transit.
*   **Administrator Access**: You must be an administrator to generate API tokens and toggle module permissions.

== Installation ==

Connecting your AI tools is fast and fully guided:

1.  **Install & Activate**: Search for **My Site Hand** in your WordPress dashboard, install, and activate it.
2.  **Generate a Token**: Go to **My Site Hand > API Tokens** and click **Create Token**. Give it a label (e.g. "Claude Desktop").
3.  **Connect in Seconds**: Go to the **How to Use** page in your dashboard:
    *   **Step 1**: Copy and run the command to install the `mcp-remote` bridge on your computer.
    *   **Step 2**: Copy the auto-generated connection command (it includes your secure token) and run it in your terminal.
4.  **Restart & Use**: Restart your Claude Desktop app, and start talking to your website!

== Frequently Asked Questions ==

= Is node.js is required?? = 
Yes. The automated desktop bridge (`mcp-remote`) requires Node.js installed on your local computer to run.

= Is this plugin secure? =
Yes. Security is our top priority. The plugin uses secure, SHA-256 hashed API tokens. You choose exactly which permissions to grant to each token, and you can revoke access instantly at any time. Furthermore, the built-in **Audit Log** shows you exactly what commands were run, who ran them, and when.

= What AI applications are supported? =
Any application that supports the Model Context Protocol (MCP). This includes **Claude Desktop**, **Cursor IDE**, **VS Code** (via MCP extensions), and **Windsurf**.

= Do I need to edit JSON configuration files? =
No! Unlike other MCP setups that force you to search for hidden directories and edit configuration files manually, My Site Hand uses a lightweight Node bridge utility to write the configuration for you automatically.

= Does this send my data to third-party servers? =
No. All MCP communications occur directly between your local AI client and your WordPress site. The plugin does not track, collect, or store your private data on external servers.

== Changelog ==

= 1.0.1 - 9 June 2026=
*   Added a "Suggest a Feature" page so users can submit feature requests directly to the developer's email.
*   Improved email deliverability with dynamic "From" headers and real-time failure log captures.
*   Styled and aligned the admin menu icon in the sidebar with a white background and centered flex alignment.
*   Added automatic redirection to the dashboard immediately upon plugin activation for faster onboarding.
*   Added a "How to Use" shortcut link directly on the Plugins page.

= 1.0.0 =
*   Official Initial Release.
*   Automated zero-config setup for Claude Desktop.
*   Support for Content, SEO (Yoast, RankMath), WooCommerce, and Diagnostics.
*   Secure token management and real-time audit logs.

== External Services ==

= Link Checker (SEO Module) =
When the check-broken-links ability is called via the MCP API, this plugin sends HTTP HEAD requests to URLs found in your post content to verify they are reachable. No personal user data is transmitted — only a standard HTTP request is made to each URL being checked. This is triggered only when explicitly called by an authorized API token holder.

No data is sent to any third-party analytics or tracking service by this plugin.
