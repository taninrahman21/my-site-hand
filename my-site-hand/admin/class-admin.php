<?php
/**
 * Admin menu registration and page routing.
 *
 * @package MySiteHand
 */

namespace MySiteHand\Admin;

defined('ABSPATH') || exit;

use MySiteHand\Abilities_Registry;
use MySiteHand\Auth_Manager;
use MySiteHand\Audit_Logger;

/**
 * Admin class.
 *
 * Registers the my-site-hand admin menu and sub-pages, enqueues assets,
 * and passes localized data to JavaScript.
 */
class Admin
{

	/**
	 * Admin page hook suffixes.
	 *
	 * @var array<string>
	 */
	private array $page_hooks = [];

	/**
	 * Abilities registry.
	 *
	 * @var Abilities_Registry
	 */
	private Abilities_Registry $registry;

	/**
	 * Auth manager.
	 *
	 * @var Auth_Manager
	 */
	private Auth_Manager $auth;

	/**
	 * Audit logger.
	 *
	 * @var Audit_Logger
	 */
	private Audit_Logger $audit;

	/**
	 * Constructor.
	 *
	 * @param Abilities_Registry $registry Abilities registry.
	 * @param Auth_Manager       $auth     Auth manager.
	 * @param Audit_Logger       $audit    Audit logger.
	 */
	public function __construct(
		Abilities_Registry $registry,
		Auth_Manager $auth,
		Audit_Logger $audit
	) {
		$this->registry = $registry;
		$this->auth = $auth;
		$this->audit = $audit;
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init(): void
	{
		add_action('admin_menu', [$this, 'register_menus']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('admin_init', [$this, 'register_settings']);
	}

	/**
	 * Register the admin menu and subpages.
	 *
	 * @return void
	 */
	public function register_menus(): void
	{
		$icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7H4a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2M7 14a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2m10 0a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2m-5 2a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2z"/></svg>');

		// Main menu page.
		$this->page_hooks[] = add_menu_page(
			__('My Site Hand', 'my-site-hand'),
			__('My Site Hand', 'my-site-hand'),
			'manage_options',
			'my-site-hand',
			[$this, 'render_dashboard'],
			$icon,
			80
		);

		// Dashboard submenu.
		add_submenu_page(
			'my-site-hand',
			__('Dashboard - My Site Hand', 'my-site-hand'),
			__('Dashboard', 'my-site-hand'),
			'manage_options',
			'my-site-hand',
			[$this, 'render_dashboard']
		);

		// Abilities submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('Abilities - My Site Hand', 'my-site-hand'),
			__('Abilities', 'my-site-hand'),
			'manage_options',
			'my-site-hand-abilities',
			[$this, 'render_abilities']
		);

		// API Tokens submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('API Tokens - My Site Hand', 'my-site-hand'),
			__('API Tokens', 'my-site-hand'),
			'manage_options',
			'my-site-hand-tokens',
			[$this, 'render_tokens']
		);

		// Audit Log submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('Audit Log - My Site Hand', 'my-site-hand'),
			__('Audit Log', 'my-site-hand'),
			'manage_options',
			'my-site-hand-audit',
			[$this, 'render_audit_log']
		);

		// Settings submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('Settings - My Site Hand', 'my-site-hand'),
			__('Settings', 'my-site-hand'),
			'manage_options',
			'my-site-hand-settings',
			[$this, 'render_settings']
		);

		// Tools submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('Tools - My Site Hand', 'my-site-hand'),
			__('Tools', 'my-site-hand'),
			'manage_options',
			'my-site-hand-tools',
			[$this, 'render_tools']
		);
	}

	/**
	 * Enqueue admin CSS and JS only on my-site-hand pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets(string $hook): void
	{
		// Check if we're on a my-site-hand page.
		$is_mysitehand_page = str_contains($hook, 'my-site-hand');

		if (!$is_mysitehand_page) {
			return;
		}

		// Admin CSS.
		wp_enqueue_style(
			'mysitehand-admin',
			MYSITEHAND_URL . 'assets/css/admin.css',
			[],
			MYSITEHAND_VERSION
		);

		// Admin JS.
		wp_enqueue_script(
			'mysitehand-admin',
			MYSITEHAND_URL . 'assets/js/admin.js',
			[],
			MYSITEHAND_VERSION,
			true
		);

		// Token Manager JS.
		wp_enqueue_script(
			'msh-token-manager',
			MYSITEHAND_URL . 'assets/js/token-manager.js',
			['mysitehand-admin'],
			MYSITEHAND_VERSION,
			true
		);

		// Dashboard Connect JS.
		if ('toplevel_page_my-site-hand' === $hook) {
			wp_enqueue_script(
				'msh-dashboard-connect',
				MYSITEHAND_URL . 'assets/js/dashboard-connect.js',
				['mysitehand-admin'],
				MYSITEHAND_VERSION,
				true
			);
		}

		// Localized data for JavaScript.
		wp_localize_script(
			'mysitehand-admin',
			'mysitehandAdmin',
			[
				'nonce' => wp_create_nonce('my_site_hand_admin'),
				'restNonce' => wp_create_nonce('wp_rest'),
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'restUrl' => rest_url('my-site-hand/v1/'),
				'mcpEndpoint' => rest_url('my-site-hand/v1/mcp/streamable'),
				'pluginUrl' => MYSITEHAND_URL,
				'i18n' => [
					'copied' => __('Copied!', 'my-site-hand'),
					'confirmRevoke' => __('Are you sure you want to revoke this token?', 'my-site-hand'),
					'saving' => __('Saving...', 'my-site-hand'),
					'saved' => __('Saved!', 'my-site-hand'),
					'error' => __('An error occurred. Please try again.', 'my-site-hand'),
					'cacheCleared' => __('Cache cleared!', 'my-site-hand'),
				],
			]
		);
	}

	/**
	 * Register plugin settings using the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void
	{
		register_setting(
			'mysitehand_settings',
			'mysitehand_enabled',
			['sanitize_callback' => 'rest_sanitize_boolean']
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_display_name',
			['sanitize_callback' => 'sanitize_text_field']
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_hourly_limit',
			['sanitize_callback' => 'absint']
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_daily_limit',
			['sanitize_callback' => 'absint']
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_enabled_modules',
			[
				'sanitize_callback' => static function ($value) {
					return is_array($value) ? array_map('sanitize_key', $value) : [];
				},
			]
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_cache_ttl',
			['sanitize_callback' => 'absint']
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_log_retention_days',
			['sanitize_callback' => 'absint']
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_log_level',
			[
				'sanitize_callback' => static function ($value) {
					return in_array($value, ['all', 'errors-only', 'none'], true) ? $value : 'all';
				},
			]
		);

		register_setting(
			'mysitehand_settings',
			'mysitehand_delete_data_on_uninstall',
			['sanitize_callback' => 'rest_sanitize_boolean']
		);
	}

	// -------------------------------------------------------------------------
	// Page render callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/dashboard.php';
	}

	/**
	 * Render the abilities page.
	 *
	 * @return void
	 */
	public function render_abilities(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/abilities.php';
	}

	/**
	 * Render the token management page.
	 *
	 * @return void
	 */
	public function render_tokens(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/tokens.php';
	}

	/**
	 * Render the audit log page.
	 *
	 * @return void
	 */
	public function render_audit_log(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/audit-log.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Render the tools page.
	 *
	 * @return void
	 */
	public function render_tools(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/tools.php';
	}

	/**
	 * Get the abilities registry (for templates).
	 *
	 * @return Abilities_Registry
	 */
	public function get_registry(): Abilities_Registry
	{
		return $this->registry;
	}

	/**
	 * Get the auth manager (for templates).
	 *
	 * @return Auth_Manager
	 */
	public function get_auth(): Auth_Manager
	{
		return $this->auth;
	}

	/**
	 * Get the audit logger (for templates).
	 *
	 * @return Audit_Logger
	 */
	public function get_audit(): Audit_Logger
	{
		return $this->audit;
	}
}




