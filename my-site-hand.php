<?php
/**
 * Plugin Name: My Site Hand (AI) - Your Smart Website Assistant
 * Plugin URI:  https://wordpress.org/plugins/my-site-hand
 * Description: Let Claude, Cursor, and AI assistants write posts, manage your store, optimize SEO, and run diagnostics on your site using simple natural language. Includes a built-in chat widget!
 * Version:     1.1.0
 * Author:      BuiltByTanin
 * Author URI:  https://github.com/taninrahman21
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-site-hand
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 7.0
 *
 * @package My Site Hand (AI)
 */

defined('ABSPATH') || exit;

/**
 * My Site Hand (AI) Plugin Constants.
 */
define('MYSITEHAND_VERSION', '1.1.0');
define('MYSITEHAND_DB_VERSION', '1.2.0');
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
 * Register the plugin's own autoloader as a fallback.
 *
 * The Composer autoloader uses a pre-generated classmap, which does not pick up
 * class files added after it was built (such as the MySiteHand\AI\ namespace).
 * Registering this PSR-4-style loader ensures those classes still resolve.
 */
require_once MYSITEHAND_PATH . 'includes/class-autoloader.php';
MySiteHand\Autoloader::register();

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
			$my_site_hand_default_modules = ['content', 'seo', 'diagnostics', 'media', 'users'];
			if (class_exists('WooCommerce')) {
				$my_site_hand_default_modules[] = 'woocommerce';
			}
			update_option('mysitehand_enabled_modules', $my_site_hand_default_modules);
			update_option('mysitehand_modules_restored_v1', 1);
		}
	}
);

/**
 * Activation and Deactivation hooks.
 */
register_activation_hook(__FILE__, ['MySiteHand\Installer', 'activate']);
register_deactivation_hook(__FILE__, ['MySiteHand\Deactivator', 'deactivate']);




