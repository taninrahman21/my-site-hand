<div align="center">

![My Site Hand Banner](https://ps.w.org/my-site-hand/assets/banner-772x250.png?rev=3354882)

# My Site Hand (AI)

### Turn Your WordPress Site Into An AI-Operable Command Layer

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759B?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org/plugins/my-site-hand/)
[![Tested Up To](https://img.shields.io/badge/Tested%20Up%20To-WP%207.0-21759B?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org/plugins/my-site-hand/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Version](https://img.shields.io/badge/Version-1.0.0-FF6B35?style=flat-square)](https://wordpress.org/plugins/my-site-hand/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![MCP](https://img.shields.io/badge/Protocol-MCP%20JSON--RPC%202.0-FF6B35?style=flat-square)](https://modelcontextprotocol.io)
[![Claude](https://img.shields.io/badge/Works%20With-Claude%20Desktop-8B5CF6?style=flat-square)](https://claude.ai)
[![Cursor](https://img.shields.io/badge/Works%20With-Cursor-00C896?style=flat-square)](https://cursor.sh)
[![VS Code](https://img.shields.io/badge/Works%20With-VS%20Code-007ACC?style=flat-square&logo=visualstudiocode)](https://code.visualstudio.com)

**[WordPress.org Plugin Page](https://wordpress.org/plugins/my-site-hand/) · [Report Bug](https://github.com/taninrahman21/my-site-hand/issues) · [Request Feature](https://github.com/taninrahman21/my-site-hand/issues)**

</div>

---

## 🤖 What Is My Site Hand?

**My Site Hand (AI)** is a WordPress plugin that acts as a secure, high-performance bridge between your WordPress site and AI agents like **Claude Desktop**, **Cursor**, and **VS Code**.

Built on the **Model Context Protocol (MCP)** using JSON-RPC 2.0, it exposes **45 WordPress abilities** that AI agents can call using natural language — letting AI read, create, update, and manage your WordPress site without you touching the dashboard.

What makes it special is **Zero-Config Setup** — instead of manually editing JSON config files, My Site Hand generates an automated terminal command that configures your AI client instantly on Windows, macOS, or Linux.

```
You say to Claude:  "Create a blog post about WordPress SEO, set the focus
                     keyword to 'WordPress tips', and publish it."

Claude calls:       create-post → set-focus-keyword → update-post (publish)

WordPress does it:  ✅ Done. Automatically.
```

---

## ✨ Key Features

- 🔌 **Zero-Config Connection** — One terminal command. No manual JSON editing. Works on Windows, macOS, Linux.
- 🧠 **45 WordPress Abilities** — Content, WooCommerce, SEO, Media, Diagnostics, and Users
- 🔐 **SHA-256 Token Security** — Tokens hashed before DB storage. Plain text shown only once.
- 🎯 **Permission Mapping** — AI actions bound to WordPress user capabilities of the token owner
- 📊 **Full Audit Logging** — Every AI request logged with ability name, parameters, and timestamp
- ⚡ **Built-in Rate Limiting** — Configurable hourly and daily request caps per token
- 🔧 **Granular Module Control** — Enable/disable any of the 5 core modules independently
- 🛒 **Auto WooCommerce Detection** — WooCommerce module boots automatically when WC is active

---

## 📋 The 45 Abilities

<details>
<summary><strong>📝 Content Management (9 abilities)</strong></summary>
<br>

| Ability | Description |
|---------|-------------|
| `list-posts` | Filter posts by status, type, category, tag, author |
| `get-post` | Retrieve full HTML content, meta, and SEO data |
| `create-post` | Create posts/pages with featured images and meta |
| `update-post` | Modify title, content, status, or meta |
| `delete-post` | Trash or permanently delete entries |
| `bulk-update-posts` | Apply changes to multiple post IDs at once |
| `list-post-types` | List all registered post types |
| `list-taxonomies` | List all registered taxonomies |
| `get-post-revisions` | View revision history for any post |

</details>

<details>
<summary><strong>🔍 SEO Power Tools — Yoast & RankMath (6 abilities)</strong></summary>
<br>

| Ability | Description |
|---------|-------------|
| `analyze-seo` | Check keyword density, heading hierarchy, link ratios |
| `set-meta-description` | Update meta description via Yoast SEO or RankMath |
| `set-focus-keyword` | Set focus keyword directly in SEO plugin data |
| `bulk-seo-audit` | Scan multiple posts for SEO issues at once |
| `get-sitemap-urls` | Discover all sitemap endpoints |
| `check-broken-links` | HTTP HEAD checks on all outgoing links |

</details>

<details>
<summary><strong>🛒 WooCommerce — Auto-enabled when WC is active (12 abilities)</strong></summary>
<br>

| Ability | Description |
|---------|-------------|
| `woo-list-products` | Browse full product inventory |
| `woo-get-product` | Get detailed product information |
| `woo-create-product` | Add new products to catalog |
| `woo-update-product` | Edit existing product details |
| `woo-list-orders` | View all orders with filters |
| `woo-get-order` | Get full order details |
| `woo-update-order-status` | Change order state with notes |
| `woo-store-summary` | Revenue, order counts, top products |
| `woo-list-coupons` | Browse all coupons |
| `woo-create-coupon` | Generate new discount codes |
| `woo-list-customers` | View customer database |

</details>

<details>
<summary><strong>🖼️ Media Library (6 abilities)</strong></summary>
<br>

| Ability | Description |
|---------|-------------|
| `list-media` | Browse images, videos, and PDFs |
| `update-media-alt-text` | Single AI-driven alt text update |
| `bulk-update-alt-text` | Update alt text across multiple files |
| `get-unattached-media` | Find files not used in any post |
| `get-large-media` | Locate heavy files above custom MB threshold |
| `get-media-library-stats` | Total size, file counts by type |

</details>

<details>
<summary><strong>🩺 Diagnostics & Health (7 abilities)</strong></summary>
<br>

| Ability | Description |
|---------|-------------|
| `site-health-report` | PHP version, DB size, memory, SSL status |
| `get-error-logs` | Read last N lines of debug.log |
| `list-plugin-updates` | All outdated plugins with changelogs |
| `list-cron-jobs` | View all scheduled cron events |
| `get-site-options` | Read WordPress site configuration |
| `list-transients` | View all active transients |
| `get-db-table-sizes` | Database table size breakdown |

</details>

<details>
<summary><strong>👥 User Management (5 abilities)</strong></summary>
<br>

| Ability | Description |
|---------|-------------|
| `list-users` | Browse all users with filters |
| `get-user` | Get full user profile and meta |
| `update-user-role` | Change user permissions |
| `list-roles` | View all registered roles |
| `get-user-stats` | User counts by role |

</details>

---

## 🚀 Installation & Setup

### Step 1 — Install the Plugin

```bash
# Option A: From WordPress.org (Recommended)
# WordPress Admin → Plugins → Add New → Search "My Site Hand" → Install & Activate

# Option B: Manual Upload
# Download zip → Plugins → Add New → Upload Plugin → Install & Activate
```

### Step 2 — Generate Your API Token

1. Go to **WordPress Admin → My Site Hand → API Tokens**
2. Click **"Generate New Token"** — label it for your AI client
3. Copy the token immediately — **it's shown only once**

### Step 3 — Zero-Config Connection

My Site Hand generates the exact terminal command for you. Just copy and run it:

```bash
# The command shown in your dashboard looks like this:
npx mcp-remote https://yoursite.com/wp-json/msh/v1/mcp?token=YOUR_TOKEN

# Works automatically on:
# ✅ Windows  ✅ macOS  ✅ Linux
# Requires Node.js to be installed
```

### Step 4 — Restart Claude Desktop & Start Talking

```
"List all my draft posts"
"Run an SEO audit on my last 10 posts"
"Show me my WooCommerce store summary for this month"
"Find all media files larger than 2MB"
"What's my site health status?"
"Create a new blog post about [topic] and publish it"
```

---

## 🔐 Security Design

Security is a first-class concern in My Site Hand:

| Layer | Implementation |
|-------|---------------|
| **Token Storage** | SHA-256 hashed in DB — plain text shown only once on generation |
| **Permission Mapping** | Every ability checks WordPress capabilities of the token owner |
| **Rate Limiting** | Configurable hourly + daily caps per token to prevent abuse |
| **Audit Logging** | Every request logged: ability name, input, user, timestamp |
| **No External Calls** | Zero data sent to third-party analytics or tracking services |
| **Broken Link Checker** | Only fires on explicit MCP API call — never runs in background |

---

## ⚙️ Plugin Constants

Defined in `my-site-hand.php`:

```php
define('MYSITEHAND_VERSION', '1.0.0');
define('MYSITEHAND_DB_VERSION', '1.0.0');
define('MYSITEHAND_MIN_PHP', '8.1');
define('MYSITEHAND_PATH', plugin_dir_path(__FILE__));
define('MYSITEHAND_BASENAME', plugin_basename(__FILE__));
define('MYSITEHAND_URL', plugin_dir_url(__FILE__));
```

---

## 📁 Project Structure

```
my-site-hand/
├── admin/                  # Admin UI templates and page controllers
├── api/                    # MCP JSON-RPC 2.0 endpoint handler
├── assets/                 # Icons, banners, and compiled frontend assets
├── includes/               # Core plugin classes
│   ├── abilities/          # All 45 ability handlers (one file per ability)
│   ├── auth/               # SHA-256 token generation and validation
│   ├── logging/            # Audit log system and retention management
│   └── modules/            # The 5 functional modules + WooCommerce
├── languages/              # i18n .pot file and translations
├── templates/              # Admin dashboard page templates
├── vendor/                 # Composer autoloader and dependencies
├── my-site-hand.php        # Plugin bootstrap — constants, hooks, boot
├── composer.json           # PHP dependencies
├── readme.txt              # WordPress.org readme
└── uninstall.php           # Clean data removal on uninstall
```

---

## 🛠️ Technical Stack

```
Backend                 Protocol                  Frontend
───────                 ────────                  ────────
PHP 8.1+                MCP (JSON-RPC 2.0)        JavaScript
WordPress 6.2+          HTTP / SSE Transport       CSS / SCSS
REST API                mcp-remote (Node.js)       Admin UI (React)
MySQL                   SHA-256 Auth
Composer autoload       WordPress Capabilities API
```

---

## 🗺️ Roadmap

- [x] Core MCP bridge with JSON-RPC 2.0
- [x] 45 abilities across 5 modules
- [x] WooCommerce auto-detection (module 6)
- [x] SHA-256 token security with permission mapping
- [x] Full audit logging with retention settings
- [x] Zero-config Claude Desktop setup via `mcp-remote`
- [x] Cross-platform setup (Windows, macOS, Linux)
- [ ] Webhook support for real-time AI triggers
- [ ] Multi-site network support
- [ ] Custom ability builder for developers
- [ ] VS Code extension for one-click connect

---

## ❓ FAQ

**Do I need to edit any config files manually?**
No. My Site Hand generates the exact `mcp-remote` command for your site. Copy and run it — done.

**Is Node.js required?**
Yes, Node.js is required to run the `mcp-remote` utility for the automated connection. It's a one-time setup.

**Can I use this with Cursor or VS Code instead of Claude?**
Yes. The plugin dashboard provides the MCP Server URL which you can paste directly into Cursor or any MCP-compatible IDE.

**What happens to my data if I uninstall?**
If enabled in settings, all plugin data including tokens, logs, and options are wiped on uninstall.

---

## 👨‍💻 Author

**Tanin Rahman** — Full-Stack WordPress Developer
Sole developer behind 7 WordPress plugins with 50,000+ combined active installs at bPlugins.

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Tanin%20Rahman-0077B5?style=flat-square&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/taninrahman/)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-builtbytanin-21759B?style=flat-square&logo=wordpress&logoColor=white)](https://profiles.wordpress.org/builtbytanin/)
[![GitHub](https://img.shields.io/badge/GitHub-taninrahman21-181717?style=flat-square&logo=github&logoColor=white)](https://github.com/taninrahman21)

---

## 📄 License

Distributed under the **GPL v2 or later** License.
See [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

<div align="center">

**⭐ If My Site Hand saves you time, please star this repo — it helps others discover it.**

*Built with ❤️ by [Tanin Rahman](https://github.com/taninrahman21)*

</div>
