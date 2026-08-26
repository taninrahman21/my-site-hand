=== My Site Hand – AI Assistant, Site Health & MCP Server for WordPress ===
Contributors: builtbytanin
Tags: ai, site health, ai assistant, claude, mcp
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan your site for broken links, missing SEO data and health issues in 30 seconds — then fix them inline or let Claude do it.

== Description ==

**My Site Hand** finds what is wrong with your WordPress site, and helps you fix it.

Activate the plugin and it scans your site straight away. No token, no API key, no Node.js, no terminal. About thirty seconds later you get a health score out of 100 and a list of real problems on your own site — and you can repair most of them inline, right there in the report.

### 🔍 The Site Health Scan (no setup required)

*   **Broken links** — checks the links in your recent posts and pages, and reports only the ones that are genuinely gone. Anything ambiguous is left alone rather than reported as a false alarm.
*   **Missing alt text** — images your visitors using screen readers cannot see, and search engines cannot read.
*   **Missing meta descriptions** — published posts and pages with no SEO description, for Yoast SEO and RankMath.
*   **Oversized media** — files big enough to slow your pages down, with the space you would get back.
*   **Orphaned files** — uploads that nothing on your site appears to use.
*   **Plugin and core health** — pending updates, an unsupported PHP version, missing HTTPS, errors visible to visitors, a stalled cron.

### ⚡ Quick Fix

A list of complaints is not much use on its own. Write alt text straight into the report, replace a dead link, or trash a file you no longer need — one row at a time, with the score climbing as you go. **No AI and no API key required for any of it.**

Your score is kept over time, so you can watch the site improve, and you can have a short report emailed to you when something changes.

### 🤖 And when you want an AI to do the work

Everything above works on its own. Connecting an AI assistant is the optional upgrade.

Built on the open standard **Model Context Protocol (MCP)** developed by Anthropic (the creators of Claude), this plugin securely exposes safely-gated capabilities (Abilities) to clients like **Claude Desktop**, **Cursor**, and **VS Code**, so they can work on your site using plain language.

### 🚀 What an AI assistant can do for you:
*   **Write & Edit Content** – Ask Claude to draft, edit, format, or publish posts and pages directly on your site.
*   **AI-Driven SEO Audits** – Let your AI scan Yoast or RankMath metadata, optimize it for targeted keywords, and update titles/descriptions instantly.
*   **WooCommerce Store Management** – Ask your AI to list low-stock items, draft product listings, check recent orders, or summarize sales analytics.
*   **Site Health & Diagnostics** – Let the AI inspect PHP error logs, check loopback status, or look up site details to debug issues.
*   **Zero-Config Automated Setup** – No manual editing of hidden JSON config files. Copy a single command from your WordPress dashboard, paste it into your terminal, and Claude connects automatically!
*   **Total Safety & Security** – All tokens are SHA-256 hashed. You can toggle specific abilities (like writing or deleting) on/off with simple switches, and monitor everything the AI does in real-time with the built-in **Audit Log**.

---

### 📦 Included Modules:

1.  **Content** — posts, pages and custom post types (9 abilities)
2.  **SEO** — analysis and meta management, for Yoast SEO and RankMath (6)
3.  **Diagnostics** — site health, error logs, cron and database details (7)
4.  **Media** — the media library, including alt text (6)
5.  **Users** — accounts and roles (5)
6.  **WooCommerce** — products, orders and coupons (12, registers only when WooCommerce is active)

That is 33 abilities on a standard install, or 45 with WooCommerce. Every one of them is an individual switch, and a token can only call what you allow.

---

### 🔒 Security

*   API tokens are SHA-256 hashed — the raw token is shown once and never stored.
*   Every token is scoped to specific abilities. A token can only do what you explicitly allow.
*   Optional IP allowlist restricts a token to specific addresses or CIDR ranges.
*   Tokens are sent via the Authorization header, never in the URL.
*   Every action is written to a searchable audit log with configurable retention.

### ⚠️ Important Requirements (Honest & Transparent):

The Site Health scan and Quick Fix need none of the following. They apply only if you choose to connect an AI assistant:

*   **Node.js**: The automated desktop bridge (`mcp-remote`) requires Node.js installed on your local computer to run.
*   **SSL/HTTPS**: For security, your site must run on HTTPS so your API tokens remain encrypted in transit.
*   **Administrator Access**: You must be an administrator to generate API tokens and toggle module permissions.

== Installation ==

**Install & Activate**: Search for **My Site Hand** in your WordPress dashboard, install, and activate it. You land on Site Health and the first scan starts by itself. That is the whole setup.

Connecting an AI assistant is optional, and fully guided:

1.  **Install & Activate**: Search for **My Site Hand** in your WordPress dashboard, install, and activate it.
2.  **Generate a Token**: Go to **My Site Hand > API Tokens** and click **Create Token**. Give it a label (e.g. "Claude Desktop").
3.  **Connect in Seconds**: Go to the **How to Use** page in your dashboard:
    *   **Step 1**: Copy and run the command to install the `mcp-remote` bridge on your computer.
    *   **Step 2**: Copy the auto-generated connection command (it includes your secure token) and run it in your terminal.
4.  **Restart & Use**: Restart your Claude Desktop app, and start talking to your website!

== Frequently Asked Questions ==

= Do I need an AI assistant, an API key or a token to use this? =
No. The Site Health scan and Quick Fix work on their own, with nothing to install and nothing to configure. Connecting Claude, Cursor or VS Code is an optional upgrade, not a requirement.

= What does the scan actually check? =
Broken links in your 50 most recent posts and pages, images with no alternative text, published posts and pages with no SEO meta description (Yoast SEO or RankMath), oversized media, files that nothing on your site appears to use, and platform problems such as pending updates, an outdated PHP version, missing HTTPS or a stalled cron.

= Will it report links that are not really broken? =
It tries hard not to. Only 404 and 410 count as gone. Anything ambiguous — a 403, a 405, a server error — is retried a different way, and if it is still unclear the link is not reported at all. A single false alarm would make you distrust the whole report.

= Does the scan slow my site down? =
No. It runs only when you start it, or on the schedule you choose, and it works in small batches so no single request runs long enough to time out. Scheduled scans skip the link check on sites with more than 500 published posts.

= Do I need Node.js? =
Not for the Site Health scan or Quick Fix. Node.js is only needed if you choose to connect an AI assistant, because the desktop bridge (`mcp-remote`) runs on your own computer.
= Is this plugin secure? =
Yes. Security is our top priority. The plugin uses secure, SHA-256 hashed API tokens. You choose exactly which permissions to grant to each token, and you can revoke access instantly at any time. Furthermore, the built-in **Audit Log** shows you exactly what commands were run, who ran them, and when.

= What AI applications are supported? =
Any application that supports the Model Context Protocol (MCP). This includes **Claude Desktop**, **Cursor IDE**, **VS Code** (via MCP extensions), and **Windsurf**.

= Do I need to edit JSON configuration files? =
No! Unlike other MCP setups that force you to search for hidden directories and edit configuration files manually, My Site Hand uses a lightweight Node bridge utility to write the configuration for you automatically.

= Does this send my data to third-party servers? =
No. All MCP communications occur directly between your local AI client and your WordPress site. The plugin does not track, collect, or store your private data on external servers.

== Screenshots ==

1. One click, no setup at all. Broken links, missing alt text, missing meta descriptions, oversized media, orphaned files and platform problems — scored out of 100.
2. Quick Fix. Write alt text, replace a dead link or trash a file you no longer need, straight from the report. No AI and no API key.
3. Six checks, run one at a time so nothing times out. Results appear as they land, and you can cancel at any point.
4. Every scan is kept, so you can watch the score climb. The optional email arrives only when something actually changed.
5. The dashboard opens with your health score, then the MCP endpoint, live figures and the most recent calls.
6. Set the schedule once and leave it. Choose how often the site scans itself and where the report goes — or switch it off entirely. Rate limits and log retention live here too.
7. Connecting Claude Desktop, Cursor or VS Code is the optional upgrade — the scan and Quick Fix need none of it.
8. Every ability is its own switch, and every token is scoped to exactly what you allow. Tokens are SHA-256 hashed and shown once.
9. Every call recorded with its payload, duration and the exact reason it failed. Quick Fix repairs are logged here too. Exportable to CSV.

== Changelog ==

= 1.1.0 - 26 August 2026 =
*   **New: Site Health Scan** — one click, no setup. Find broken links, missing alt text, missing meta descriptions, oversized media, orphaned files, and plugin or core health problems.
*   **New: Quick Fix** — repair issues inline from the report. No AI or API key required.
*   **New:** Score history and trend so you can watch your site improve.
*   **New:** Optional weekly email report. It stays quiet when there is nothing new to say.
*   **New:** Plugin checks now appear in WordPress's own Tools → Site Health.
*   **Improved:** Activation now takes you straight to a scan of your site instead of setup instructions. The MCP connection steps are still there, collapsed on the Dashboard as an optional upgrade.
*   **New:** Redesigned admin interface. The left sidebar is replaced by a brand row and a tab bar, and every page now has a numbered index so you can jump straight to the section you came for.
*   **Improved:** A single accent colour, flat surfaces, and a monospaced type scale make status, counts, and destructive actions easier to read at a glance.
*   **Improved:** The dashboard now surfaces what needs your attention — missing HTTPS, tokens expiring within 30 days, or a stopped endpoint.
*   **Fixed:** The "Trust proxy headers" and "Allow token in URL query string" settings did not save. Both now work.
*   **Fixed:** The settings page could submit itself and blank stored options if you pressed Enter in a text field. Settings save individually as you change them, so the form has been removed.

= 1.0.2 - 24 August 2026 =
*   **Security:** Tokens created without an explicit ability selection no longer default to full access. Existing tokens are migrated automatically and continue to work unchanged.
*   **Security:** API tokens are no longer accepted via URL query string by default. Use the Authorization header instead. An opt-in setting remains available for clients that cannot send headers.
*   **New:** Optional per-token IP allowlist with CIDR range support.
*   **New:** Trusted proxy setting for sites behind Cloudflare or a reverse proxy.
*   **Improved:** Token creation now clearly distinguishes full access from limited access.
*   **Fixed:** Database schema version was not being stored after an upgrade, causing unnecessary schema checks on every page load.

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

== Upgrade Notice ==

= 1.1.0 =
Adds a one-click Site Health scan with inline Quick Fix — no setup, no API key. Your existing MCP connection, tokens and audit history are unchanged. If screens look unstyled after updating, refresh once.

= 1.0.2 =
Security update. Token permissions are now enforced strictly. Your existing tokens are migrated automatically and will keep working. Please review your tokens after updating to narrow their scope.

== External Services ==

= Link Checker (SEO Module) =
When the check-broken-links ability is called via the MCP API, this plugin sends HTTP HEAD requests to URLs found in your post content to verify they are reachable. No personal user data is transmitted — only a standard HTTP request is made to each URL being checked. This is triggered only when explicitly called by an authorized API token holder.

= Link Checker (Site Health scan) =
The Broken Links check in the Site Health scan sends HTTP HEAD requests (and, where a server refuses HEAD, a single ranged GET) to the links found in your 50 most recent published posts and pages, to see whether they still resolve. The request identifies this plugin and your site URL in its user agent. No personal user data is transmitted. Results are cached for 12 hours. This runs when you start a scan from the admin, and during the scheduled scan if you leave that enabled — on sites with more than 500 published posts the link check is skipped on the schedule. Turning scheduled reports off in **My Site Hand → Settings** stops the scheduled scan entirely.

No data is sent to any third-party analytics or tracking service by this plugin.
