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
		add_action('admin_init', [$this, 'handle_activation_redirect']);
		add_action('admin_head', [$this, 'render_admin_menu_icon_css']);
		add_filter('plugin_action_links_' . MYSITEHAND_BASENAME, [$this, 'add_plugin_action_links']);
	}

	/**
	 * Register the admin menu and subpages.
	 *
	 * @return void
	 */
	public function register_menus(): void
	{
		// Versioned like the CSS/JS enqueues, so a released icon change is not
		// served from the browser cache.
		$icon = add_query_arg('ver', MYSITEHAND_VERSION, MYSITEHAND_URL . 'assets/logo.png');

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

		// Site Builder first, then Site Health, both above Dashboard. Positions
		// only reorder the submenu: the parent slug is unchanged, so existing
		// links to admin.php?page=my-site-hand still land on the Dashboard.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('Site Builder - My Site Hand', 'my-site-hand'),
			__('Site Builder', 'my-site-hand'),
			'manage_options',
			'my-site-hand-builder',
			[$this, 'render_site_builder'],
			0
		);

		// Site Health: the page that delivers value with no setup at all.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('Site Health - My Site Hand', 'my-site-hand'),
			__('Site Health', 'my-site-hand'),
			'manage_options',
			'my-site-hand-health',
			[$this, 'render_health'],
			1
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

		// Documentation submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('How to use - My Site Hand', 'my-site-hand'),
			__('How to use', 'my-site-hand'),
			'manage_options',
			'my-site-hand-documentation',
			[$this, 'render_documentation']
		);

		// Tools submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('About & Info - My Site Hand', 'my-site-hand'),
			__('About & Info', 'my-site-hand'),
			'manage_options',
			'my-site-hand-tools',
			[$this, 'render_tools']
		);

		// Suggest Feature submenu.
		$this->page_hooks[] = add_submenu_page(
			'my-site-hand',
			__('Suggest Feature - My Site Hand', 'my-site-hand'),
			__('Suggest a Feature', 'my-site-hand'),
			'manage_options',
			'my-site-hand-feature-request',
			[$this, 'render_feature_request']
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
			['mysitehand-admin', 'wp-i18n'],
			MYSITEHAND_VERSION,
			true
		);

		$this->set_script_translations('msh-token-manager');

		// Dashboard Connect JS.
		if ('toplevel_page_my-site-hand' === $hook) {
			wp_enqueue_script(
				'msh-dashboard-connect',
				MYSITEHAND_URL . 'assets/js/dashboard-connect.js',
				['mysitehand-admin', 'wp-i18n'],
				MYSITEHAND_VERSION,
				true
			);

			$this->set_script_translations('msh-dashboard-connect');
		}

		// Site Health scan JS.
		if (str_contains($hook, 'my-site-hand-health')) {
			wp_enqueue_script(
				'msh-health-scan',
				MYSITEHAND_URL . 'assets/js/health-scan.js',
				['mysitehand-admin', 'wp-i18n'],
				MYSITEHAND_VERSION,
				true
			);

			$this->set_script_translations('msh-health-scan');

			// Only data now. The strings this script used to receive here are
			// wp.i18n.__() calls inside health-scan.js, so make-pot can see
			// them and a translator only has to render each one once.
			wp_localize_script(
				'msh-health-scan',
				'mshHealth',
				[
					'restUrl' => rest_url('my-site-hand/v1/health/'),
					'nonce' => wp_create_nonce('wp_rest'),
					'weights' => \MySiteHand\Site_Health_Scanner::ISSUE_WEIGHTS,
					'caps' => \MySiteHand\Site_Health_Scanner::CHECK_CAPS,
				]
			);

			$this->enqueue_ai_bulk_fix();
		}

		// Site Builder.
		if (str_contains($hook, 'my-site-hand-builder')) {
			$this->enqueue_site_builder();
		}

		// AI Assistance settings.
		if (str_contains($hook, 'my-site-hand-settings')) {
			wp_enqueue_script(
				'msh-ai-settings',
				MYSITEHAND_URL . 'assets/js/ai-settings.js',
				['mysitehand-admin', 'wp-i18n'],
				MYSITEHAND_VERSION,
				true
			);

			$this->set_script_translations('msh-ai-settings');
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
	 * Enqueue the Site Builder app and its data.
	 *
	 * @return void
	 */
	private function enqueue_site_builder(): void
	{
		$plugin  = \MySiteHand\Plugin::get_instance();
		$builder = $plugin->get_site_builder();
		$user_id = get_current_user_id();

		// The image fields use the core media library modal.
		wp_enqueue_media();

		wp_enqueue_style(
			'msh-site-builder',
			MYSITEHAND_URL . 'assets/css/site-builder.css',
			['mysitehand-admin'],
			MYSITEHAND_VERSION
		);

		wp_enqueue_script(
			'msh-site-builder',
			MYSITEHAND_URL . 'assets/js/site-builder.js',
			['mysitehand-admin', 'wp-i18n'],
			MYSITEHAND_VERSION,
			true
		);

		$this->set_script_translations('msh-site-builder');

		wp_localize_script(
			'msh-site-builder',
			'mshBuilder',
			apply_filters(
				'my_site_hand_builder_script_data',
				[
					'restUrl'       => rest_url('my-site-hand/v1/builder/'),
					'nonce'         => wp_create_nonce('wp_rest'),
					'sites'         => $builder->list_sites(),
					'staged'        => $builder->get_staged($user_id),
					'live'          => $plugin->get_site_applier()->live_summary(),
					'isBlockTheme'  => wp_is_block_theme(),
					'themeName'     => wp_get_theme()->get('Name'),
					'canSwitchTheme' => current_user_can('switch_themes'),
					'settingsUrl'   => admin_url('admin.php?page=my-site-hand-settings'),
					'siteEditorUrl' => admin_url('site-editor.php'),
					'ai'            => $this->site_builder_ai_data($plugin->get_ai_manager()),
				]
			)
		);
	}

	/**
	 * What the Site Builder editor needs to know about AI. Never the key.
	 *
	 * @param \MySiteHand\AI_Manager $ai AI manager.
	 * @return array<string, mixed>|null Null when no key is set.
	 */
	private function site_builder_ai_data(\MySiteHand\AI_Manager $ai): ?array
	{
		if (!$ai->is_configured()) {
			return null;
		}

		$provider = $ai->get_active_provider();

		return [
			'provider'   => null !== $provider ? $provider->get_label() : '',
			'model'      => $ai->get_model(),
			'todaySpend' => $ai->get_usage('today')['cost'],
			'dailyCap'   => $ai->get_daily_cap(),
		];
	}

	/**
	 * Enqueue the AI Bulk Fix review panel, only when AI is configured.
	 *
	 * With no API key this returns before doing anything, so the Site Health
	 * page loads exactly the scripts and data it did before AI existed.
	 *
	 * @return void
	 */
	private function enqueue_ai_bulk_fix(): void
	{
		$plugin = \MySiteHand\Plugin::get_instance();
		$ai = $plugin->get_ai_manager();

		if (!$ai->is_configured()) {
			return;
		}

		$scanner = $plugin->get_site_health_scanner();
		$checks = [];

		foreach (\MySiteHand\Bulk_Fix_Manager::SUPPORTED_CHECKS as $check_id) {
			$check = $scanner->get_check($check_id);

			if (null === $check || !$check->is_applicable()) {
				continue;
			}

			[$input_tokens, $output_tokens] = \MySiteHand\Bulk_Fix_Manager::ESTIMATED_TOKENS[$check_id];

			$checks[$check_id] = [
				'kind' => 'missing-alt-text' === $check_id ? 'alt' : 'meta',
				'maxLength' => 'missing-alt-text' === $check_id
					? \MySiteHand\Bulk_Fix_Manager::ALT_MAX - 1
					: \MySiteHand\Bulk_Fix_Manager::META_MAX,
				'costPerItem' => $ai->estimate_cost($input_tokens, $output_tokens, $ai->get_model()),
			];
		}

		if (empty($checks)) {
			return;
		}

		wp_enqueue_script(
			'msh-health-ai-fix',
			MYSITEHAND_URL . 'assets/js/health-ai-fix.js',
			['msh-health-scan', 'wp-i18n'],
			MYSITEHAND_VERSION,
			true
		);

		$this->set_script_translations('msh-health-ai-fix');

		$provider = $ai->get_active_provider();
		$models = null !== $provider ? $provider->get_models() : [];
		$model = $ai->get_model();

		wp_localize_script(
			'msh-health-ai-fix',
			'mshAiFix',
			[
				'restUrl' => rest_url('my-site-hand/v1/health/'),
				'nonce' => wp_create_nonce('wp_rest'),
				'checks' => $checks,
				'generateBatch' => \MySiteHand\Bulk_Fix_Manager::GENERATE_BATCH,
				'applyBatch' => \MySiteHand\Bulk_Fix_Manager::APPLY_BATCH,
				'todaySpend' => $ai->get_usage('today')['cost'],
				'dailyCap' => $ai->get_daily_cap(),
				// Never the key. Only which service, and which model.
				'providerLabel' => null !== $provider
					? sprintf(
						/* translators: 1: AI provider name, 2: model name */
						__('%1$s, %2$s', 'my-site-hand'),
						$provider->get_label(),
						$model
					)
					: '',
				'modelLabel' => $models[$model] ?? $model,
			]
		);
	}

	/**
	 * Point one script at the plugin's JSON translation files.
	 *
	 * wp.i18n reads its strings from a per-script JSON file that
	 * translate.wordpress.org generates from the .pot, and WordPress only
	 * knows where to find those if the languages directory is declared here.
	 * Without this call every __() in our JavaScript silently returns English
	 * however well the plugin is translated.
	 *
	 * @param string $handle Registered script handle.
	 * @return void
	 */
	private function set_script_translations(string $handle): void
	{
		if (!function_exists('wp_set_script_translations')) {
			return;
		}

		wp_set_script_translations(
			$handle,
			'my-site-hand',
			MYSITEHAND_PATH . 'languages'
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
	 * Render the Site Builder page.
	 *
	 * @return void
	 */
	public function render_site_builder(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/site-builder.php';
	}

	/**
	 * Render the Site Health page.
	 *
	 * Note: this inspects the user's site. It is not the AI Audit Log, which
	 * lives at templates/admin/audit-log.php.
	 *
	 * @return void
	 */
	public function render_health(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/health.php';
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
	 * Render the documentation page.
	 *
	 * @return void
	 */
	public function render_documentation(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}
		require MYSITEHAND_PATH . 'templates/admin/documentation.php';
	}

	/**
	 * Render the suggest feature page.
	 *
	 * @return void
	 */
	public function render_feature_request(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'my-site-hand'));
		}

		$message = '';
		$message_type = 'success';

		// Handle Form Submission
		if (isset($_POST['msh_feature_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['msh_feature_nonce'])), 'msh_submit_feature')) {
			$title = isset($_POST['feature_title']) ? sanitize_text_field(wp_unslash($_POST['feature_title'])) : '';
			$description = isset($_POST['feature_description']) ? sanitize_textarea_field(wp_unslash($_POST['feature_description'])) : '';
			$user_email = isset($_POST['feature_email']) ? sanitize_email(wp_unslash($_POST['feature_email'])) : '';

			if (empty($title) || empty($description)) {
				$message = __('Please fill in all required fields.', 'my-site-hand');
				$message_type = 'error';
			} else {
				$to = 'builtbytanin@gmail.com';
				$subject = '[My Site Hand Feature Request] ' . $title;

				$body = "New Feature Request Submitted:\n\n";
				$body .= "Title: " . $title . "\n\n";
				$body .= "Description:\n" . $description . "\n\n";
				$body .= "Submitted by: " . $user_email . "\n";
				$body .= "Site: " . esc_url(home_url()) . "\n";
				$body .= "WordPress Version: " . get_bloginfo('version') . "\n";
				$body .= "PHP Version: " . PHP_VERSION . "\n";

				$headers = [];
				if (!empty($user_email)) {
					$headers[] = 'Reply-To: ' . $user_email;
				}

				// Generate a From header using the current site domain to prevent generic host rejection
				$host = wp_parse_url(home_url(), PHP_URL_HOST);
				if ($host) {
					if (substr($host, 0, 4) === 'www.') {
						$host = substr($host, 4);
					}
					$headers[] = 'From: My Site Hand <wordpress@' . $host . '>';
				}

				// Capture any mail errors
				$mail_error = '';
				$capture_error = function ($error) use (&$mail_error) {
					if (is_wp_error($error)) {
						$mail_error = $error->get_error_message();
					}
				};
				add_action('wp_mail_failed', $capture_error);

				$sent = wp_mail($to, $subject, $body, $headers);

				remove_action('wp_mail_failed', $capture_error);

				if ($sent) {
					$message = __('Thank you! Your feature request has been successfully submitted to the developer.', 'my-site-hand');
					$message_type = 'success';
				} else {
					if (!empty($mail_error)) {
						/* translators: %s: the mailer error message */
						$message = sprintf(__('Failed to send email. Mailer error: %s', 'my-site-hand'), $mail_error);
					} else {
						$message = __('Failed to send email. Please check your server SMTP settings or contact builtbytanin@gmail.com directly.', 'my-site-hand');
					}
					$message_type = 'error';
				}
			}
		}

		require MYSITEHAND_PATH . 'templates/admin/feature-request.php';
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

	/**
	 * Redirect to the Site Builder gallery on plugin activation.
	 *
	 * Deliberately not the Dashboard. The Dashboard opens with a token, a
	 * Node.js install and a terminal, which is exactly what non-technical
	 * users bounce off. The gallery shows what the plugin is for at a glance,
	 * and its welcome panel offers the health scan as the other first step.
	 * Nothing runs by itself on arrival.
	 *
	 * @return void
	 */
	public function handle_activation_redirect(): void
	{
		if (get_option('my_site_hand_do_activation_redirect')) {
			delete_option('my_site_hand_do_activation_redirect');

			// Only redirect if this is not a bulk activation, CLI, XML-RPC, AJAX request, etc.
			if (
				!is_network_admin() &&
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 'activate-multi' is set by WP core during bulk plugin activation; no nonce is available.
				!isset($_GET['activate-multi']) &&
				current_user_can('manage_options') &&
				(!defined('DOING_AJAX') || !DOING_AJAX) &&
				(!defined('WP_CLI') || !WP_CLI)
			) {
				wp_safe_redirect(admin_url('admin.php?page=my-site-hand-builder&view=gallery'));
				exit;
			}
		}
	}

	/**
	 * Render custom CSS in the admin head to scale the menu icon.
	 *
	 * @return void
	 */
	public function render_admin_menu_icon_css(): void
	{
		?>
		<style>
			#adminmenu .toplevel_page_my-site-hand .wp-menu-image {
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
			}

			#adminmenu .toplevel_page_my-site-hand .wp-menu-image img {
				/* Core pads menu images; zero it so the icon is not inset. */
				padding: 0 !important;
				max-width: 20px !important;
				max-height: 20px !important;
				width: 20px !important;
				height: 20px !important;
				box-sizing: border-box !important;
				display: block !important;
				margin: 0 !important;
				border: 1px solid white !important;
			}
		</style>
		<?php
	}

	/**
	 * Add custom action links on the plugins page.
	 *
	 * @param array $links Array of plugin action links.
	 * @return array
	 */
	public function add_plugin_action_links(array $links): array
	{
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url(admin_url('admin.php?page=my-site-hand-documentation')),
			esc_html__('How to Use', 'my-site-hand')
		);
		array_unshift($links, $settings_link);
		return $links;
	}
}




