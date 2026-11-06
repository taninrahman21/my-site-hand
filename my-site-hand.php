<?php
/**
 * Plugin Name: My Site Hand
 * Plugin URI:  https://wordpress.org/plugins/my-site-hand
 * Description: Build your site from free starter sites in plain WordPress blocks. Preview it first, undo anytime, no lock-in. Optional AI, an MCP server for Claude, Cursor and VS Code, and a site health scan.
 * Version:     1.4.0
 * Author:      BuiltByTanin
 * Author URI:  https://github.com/taninrahman21
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-site-hand
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Tested up to: 7.1
 *
 * @package My Site Hand
 */

defined('ABSPATH') || exit;

/**
 * My Site Hand (AI) Plugin Constants.
 */
define('MYSITEHAND_VERSION', '1.4.0');
define('MYSITEHAND_DB_VERSION', '1.3.0');
define('MYSITEHAND_MIN_PHP', '8.1');
define('MYSITEHAND_PATH', plugin_dir_path(__FILE__));
define('MYSITEHAND_BASENAME', plugin_basename(__FILE__));
define('MYSITEHAND_URL', plugin_dir_url(__FILE__));

/**
 * Check minimum PHP version before doing anything else.
 */
if (version_compare(PHP_VERSION, MYSITEHAND_MIN_PHP, '<')) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version 2: current PHP version */
						__('My Site Hand requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP.', 'my-site-hand'),
						MYSITEHAND_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

/**
 * Require autoloader and vendor setup.
 */
if (file_exists(MYSITEHAND_PATH . 'vendor/autoload.php')) {
	require_once MYSITEHAND_PATH . 'vendor/autoload.php';
}

/**
 * Load translations.
 *
 * WordPress.org language packs are picked up on their own, but a translation
 * shipped inside the plugin — the Bengali one in /languages — is not, so the
 * folder has to be declared. On init, because that is where WordPress wants
 * translations loaded.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain('my-site-hand', false, dirname(MYSITEHAND_BASENAME) . '/languages');
	},
	0
);

/**
 * Bootstrap the plugin.
 */
add_action(
	'plugins_loaded',
	function () {
		// Initialize the core plugin instance.
		$my_site_hand_plugin = MySiteHand\Plugin::get_instance();
		$my_site_hand_plugin->boot();

		// One-time fix to ensure all modules are enabled on first run.
		if (!get_option('mysitehand_modules_restored_v1')) {
			$my_site_hand_default_modules = ['content', 'seo', 'diagnostics', 'media', 'users', 'site-builder'];
			if (class_exists('WooCommerce')) {
				$my_site_hand_default_modules[] = 'woocommerce';
			}
			update_option('mysitehand_enabled_modules', $my_site_hand_default_modules);
			update_option('mysitehand_modules_restored_v1', 1);
		}

		// One-time migration for token abilities.
		if (!get_option('mysitehand_token_abilities_migrated_v1')) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query("UPDATE {$wpdb->prefix}mysitehand_tokens SET abilities = '[\"*\"]' WHERE abilities = '' OR abilities = '[]' OR abilities IS NULL");
			
				set_transient('mysitehand_token_migration_notice', 1, WEEK_IN_SECONDS);
			update_option('mysitehand_token_abilities_migrated_v1', 1);
		}

		if (get_transient('mysitehand_token_migration_notice')) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-info is-dismissible"><p>' . 
					wp_kses_post(sprintf(
						/* translators: %s: URL to tokens page */
						__('<strong>My Site Hand Security Update:</strong> API Token permissions are now enforced strictly. Your existing tokens have been preserved with full access to prevent connection breaks. Please <a href="%s">review your tokens</a> to narrow their scope.', 'my-site-hand'),
						admin_url('admin.php?page=my-site-hand-tokens')
					)) . 
					'</p></div>';
				delete_transient('mysitehand_token_migration_notice');
			});
		}

		// 1.4.0: switch the new Site Builder module on for existing sites, whose
		// saved module list predates it, and introduce it once with the
		// welcome panel. A user who later turns the module off keeps it off.
		if (!get_option('mysitehand_site_builder_module_v140')) {
			$my_site_hand_modules = (array) get_option('mysitehand_enabled_modules', []);
			if ($my_site_hand_modules && !in_array('site-builder', $my_site_hand_modules, true)) {
				$my_site_hand_modules[] = 'site-builder';
				update_option('mysitehand_enabled_modules', $my_site_hand_modules);
			}
			update_option('mysitehand_site_builder_module_v140', 1);
			update_option('mysitehand_show_welcome', true);
		}

		// Schema upgrade for IP allowlist (Chunk 3).
		if (version_compare(get_option('mysitehand_db_version', '1.0.0'), MYSITEHAND_DB_VERSION, '<')) {
			MySiteHand\Installer::create_tables();
			update_option('mysitehand_db_version', MYSITEHAND_DB_VERSION);
		}
	}
);

/**
 * Activation and Deactivation hooks.
 */
register_activation_hook(__FILE__, ['MySiteHand\Installer', 'activate']);
register_deactivation_hook(__FILE__, ['MySiteHand\Deactivator', 'deactivate']);




