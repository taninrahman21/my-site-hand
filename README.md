<div align="center">

<img src="https://ps.w.org/my-site-hand/assets/banner-772x250.png?rev=3554882" alt="My Site Hand (AI)" width="100%">

# My Site Hand (AI)

### Turn Your WordPress Site Into An AI-Operable Command Layer

[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759B?style=flat-square&logo=wordpress&logoColor=white)](https://wordpress.org/plugins/my-site-hand/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
[![MCP](https://img.shields.io/badge/Protocol-MCP%20JSON--RPC%202.0-FF6B35?style=flat-square)](https://modelcontextprotocol.io)
[![Claude](https://img.shields.io/badge/Works%20With-Claude%20Desktop-8B5CF6?style=flat-square)](https://claude.ai)
[![Cursor](https://img.shields.io/badge/Works%20With-Cursor-00C896?style=flat-square)](https://cursor.sh)

**[WordPress.org](https://wordpress.org/plugins/my-site-hand/) · [Report Bug](https://github.com/taninrahman21/my-site-hand/issues) · [Request Feature](https://github.com/taninrahman21/my-site-hand/issues)**

</div>

---

## 🤖 What Is My Site Hand?

**My Site Hand** is a WordPress plugin that acts as a secure, high-performance bridge between your WordPress site and AI agents like **Claude Desktop**, **Cursor**, and **VS Code**.

It implements the **Model Context Protocol (MCP)** using JSON-RPC 2.0 — exposing **46 WordPress "abilities"** that AI agents can call using natural language to perform site management, content editing, SEO audits, WooCommerce operations, and more.

```
You say to Claude:  "Create a blog post about WordPress SEO, add a featured image, 
                     set the focus keyword to 'WordPress SEO tips', and publish it."

Claude calls:       create-post → set-focus-keyword → update-post (publish)

WordPress does it:  ✅ Done. Automatically.
```

---

## ✨ Key Features

- 🔌 **Zero-Config Connection** — One terminal command connects Claude Desktop automatically. No manual JSON editing.
- 🧠 **46 WordPress Abilities** — Content, WooCommerce, SEO, Media, Diagnostics, and Users
- 🔐 **SHA-256 Token Security** — Tokens hashed in DB, plain text shown only once
- 🎯 **Permission Mapping** — AI actions bound to WordPress user capabilities
- 📊 **Full Audit Logging** — Every AI request logged with timestamp and parameters
- ⚡ **Built-in Rate Limiting** — Hourly and daily request caps per token
- 🔧 **Granular Module Control** — Enable/disable any of the 6 ability modules
- 🖥️ **Cross-Platform Setup** — Automated instructions for Windows, macOS, and Linux

---

## 📋 The 46 Abilities

<details>
<summary><strong>📝 Content Management (9 abilities)</strong></summary>

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
<summary><strong>🔍 SEO Power Tools (6 abilities)</strong></summary>

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
<summary><strong>🛒 WooCommerce (12 abilities)</strong></summary>

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
| *(Auto-activates when WooCommerce is active)* | |

</details>

<details>
<summary><strong>🖼️ Media Library (6 abilities)</strong></summary>

| Ability | Description |
|---------|-------------|
| `list-media` | Browse images, videos, and PDFs |
| `update-media-alt-text` | AI-driven single alt text update |
| `bulk-update-alt-text` | Update alt text across multiple files |
| `get-unattached-media` | Find files not used in any post |
| `get-large-media` | Locate heavy files above custom MB threshold |
| `get-media-library-stats` | Total size, file counts by type |

</details>

<details>
<summary><strong>🩺 Diagnostics & Health (7 abilities)</strong></summary>

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
<summary><strong>👥 User Management (6 abilities)</strong></summary>

| Ability | Description |
|---------|-------------|
| `list-users` | Browse all users with filters |
| `get-user` | Get full user profile and meta |
| `create-user` | Generate accounts with auto-passwords |
| `update-user-role` | Change user permissions |
| `list-roles` | View all registered roles |
| `get-user-stats` | User counts by role |

</details>

---

## 🚀 Installation & Setup

### Step 1 — Install the Plugin

```bash
# Option A: Install from WordPress.org
# Go to WordPress Admin → Plugins → Add New → Search "My Site Hand"

# Option B: Manual install
# Download the zip, upload via Plugins → Add New → Upload Plugin
```

### Step 2 — Generate Your API Token

1. Go to **WordPress Admin → My Site Hand → API Tokens**
2. Click **"Generate New Token"**
3. Copy the token — it's shown **only once**

### Step 3 — Connect to Claude Desktop (Zero-Config)

```bash
# Run the automated setup command shown in your dashboard
# It handles everything automatically on Windows, macOS, and Linux

# Example (your actual command is generated in the plugin dashboard):
npx mcp-remote https://yoursite.com/wp-json/msh/v1/mcp?token=YOUR_TOKEN
```

### Step 4 — Start Using AI to Manage Your Site

Open Claude Desktop and try:

```
"List all my draft posts"
"Run an SEO audit on my last 10 posts"  
"Show me my WooCommerce store summary"
"Find all media files larger than 5MB"
"What's my site health status?"
```

---

## 🔐 Security

Security is a core design principle of My Site Hand:

- **SHA-256 Token Hashing** — API tokens are hashed before storage. Plain text is shown only once upon generation.
- **Permission Mapping** — Every AI ability checks the WordPress capabilities of the token owner. A Contributor token cannot delete posts.
- **Rate Limiting** — Configurable hourly and daily request caps per token prevent abuse.
- **Audit Logging** — Every single AI request is logged with ability name, input parameters, user, and timestamp.
- **No Third-Party Calls** — Zero data sent to external analytics or tracking services.

---

## ⚙️ Configuration

Navigate to **WordPress Admin → My Site Hand → Settings**:

| Setting | Description |
|---------|-------------|
| Plugin Toggle | Enable/disable the entire MCP bridge |
| Module Control | Granularly enable/disable any of the 6 modules |
| Rate Limiting | Set hourly and daily request caps per token |
| Cache TTL | Control how long diagnostic reports are cached |
| Audit Log Retention | Set how many days to keep request logs |
| Data Wipe | Option to remove all data on uninstall |

---

## 🛠️ Technical Stack

```
Backend          Frontend          Protocol
────────         ────────          ────────
PHP 8.1+         JavaScript        MCP (JSON-RPC 2.0)
WordPress 6.2+   React             mcp-remote (Node.js)
REST API         CSS/SCSS          HTTP/SSE Transport
MySQL            Admin UI          SHA-256 Auth
```

---

## 📁 Project Structure

```
my-site-hand/
├── admin/              # Admin UI templates and assets
├── api/                # MCP endpoint and request handler
├── assets/             # Icons, banners, and frontend assets
├── includes/           # Core plugin classes and modules
│   ├── abilities/      # All 46 ability handlers
│   ├── auth/           # Token generation and validation
│   ├── logging/        # Audit log system
│   └── modules/        # The 6 functional modules
├── languages/          # i18n translation files
├── templates/          # Admin page templates
├── vendor/             # Composer dependencies
├── my-site-hand.php    # Plugin bootstrap file
└── uninstall.php       # Clean uninstall handler
```

---

## 🗺️ Roadmap

- [x] Core MCP bridge with JSON-RPC 2.0
- [x] 46 abilities across 6 modules
- [x] SHA-256 token security
- [x] Full audit logging
- [x] Zero-config Claude Desktop setup
- [x] WooCommerce integration
- [ ] Webhook support for real-time AI triggers
- [ ] Multi-site network support
- [ ] Custom ability builder for developers
- [ ] REST API for ability management

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 👨‍💻 Author

**Tanin Rahman**
Full-Stack WordPress Developer · Solo-built 7 plugins · 50K+ active installs

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Connect-0077B5?style=flat-square&logo=linkedin)](https://www.linkedin.com/in/taninrahman/)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-Profile-21759B?style=flat-square&logo=wordpress)](https://profiles.wordpress.org/taninrahman/)
[![GitHub](https://img.shields.io/badge/GitHub-Follow-181717?style=flat-square&logo=github)](https://github.com/taninrahman21)

---

## 📄 License

Distributed under the **GPL v2** License. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for more information.

---

<div align="center">

**If you find My Site Hand useful, please ⭐ star this repo — it helps others discover it.**

*Built with ❤️ by [Tanin Rahman](https://github.com/taninrahman21)*

</div>
