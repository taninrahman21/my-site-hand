<?php
/**
 * AI Assistant chat page template.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

use MySiteHand\AI\AI_Provider;

$my_site_hand_is_configured = AI_Provider::is_configured();
$my_site_hand_provider      = (string) get_option('mysitehand_ai_provider', '');
$my_site_hand_model         = (string) get_option('mysitehand_ai_model', '');
$my_site_hand_key_set       = '' !== (string) get_option('mysitehand_ai_api_key', '');

$my_site_hand_models = [
	'openai' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini'],
	'gemini' => ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash'],
];

// Friendly "Connected to …" label.
$my_site_hand_model_labels = [
	'gpt-4o'           => 'GPT-4o',
	'gpt-4o-mini'      => 'GPT-4o mini',
	'gpt-4.1'          => 'GPT-4.1',
	'gpt-4.1-mini'     => 'GPT-4.1 mini',
	'gemini-2.5-pro'   => 'Gemini 2.5 Pro',
	'gemini-2.5-flash' => 'Gemini 2.5 Flash',
	'gemini-2.0-flash' => 'Gemini 2.0 Flash',
];
$my_site_hand_is_auto = ('auto' === $my_site_hand_model || '' === $my_site_hand_model);
if ($my_site_hand_is_auto) {
	$my_site_hand_model_label = __('Auto (best available)', 'my-site-hand');
} else {
	$my_site_hand_model_label = $my_site_hand_model_labels[$my_site_hand_model] ?? $my_site_hand_model;
}

// Real number of tools wired into the assistant (server-side truth).
$my_site_hand_tool_count = count(
	\MySiteHand\Plugin::get_instance()->get_abilities_registry()->get_all_as_mcp_tool_schemas()
);

// Models available for the current provider (for the inline picker).
$my_site_hand_provider_models = $my_site_hand_models[$my_site_hand_provider] ?? [];

// Build the welcome-screen prompts from the user's ENABLED modules so every
// suggestion maps to an ability the assistant can actually run. Two prompts per
// module; assembled round-robin and capped at six for a useful spread.
$my_site_hand_prompt_pool = [
	'content'     => [
		__('List my 5 most recent posts', 'my-site-hand'),
		__('Create a draft post about my business', 'my-site-hand'),
	],
	'seo'         => [
		__('Run an SEO audit on my posts', 'my-site-hand'),
		__('Find broken links on my site', 'my-site-hand'),
	],
	'diagnostics' => [
		__('Run a site health report', 'my-site-hand'),
		__('Show my recent error logs', 'my-site-hand'),
	],
	'media'       => [
		__('Show my largest media files', 'my-site-hand'),
		__('Show my media library stats', 'my-site-hand'),
	],
	'users'       => [
		__('List my site users', 'my-site-hand'),
		__('Show user statistics', 'my-site-hand'),
	],
	'woocommerce' => [
		__('Show my recent WooCommerce orders', 'my-site-hand'),
		__('Give me a store sales summary', 'my-site-hand'),
	],
];

$my_site_hand_enabled_mods = (array) \MySiteHand\Plugin::get_instance()->get_enabled_modules();

// WooCommerce prompts only make sense when WooCommerce is actually active.
if (!class_exists('WooCommerce')) {
	$my_site_hand_enabled_mods = array_diff($my_site_hand_enabled_mods, ['woocommerce']);
}

// Keep a sensible display order, limited to enabled modules.
$my_site_hand_module_order = array_values(array_filter(
	['content', 'seo', 'diagnostics', 'media', 'users', 'woocommerce'],
	static fn($slug) => in_array($slug, $my_site_hand_enabled_mods, true) && isset($my_site_hand_prompt_pool[$slug])
));

$my_site_hand_prompt_buttons = [];
// Round-robin: first prompt of each module, then second, until we have 6.
for ($my_site_hand_i = 0; $my_site_hand_i < 2 && count($my_site_hand_prompt_buttons) < 6; $my_site_hand_i++) {
	foreach ($my_site_hand_module_order as $my_site_hand_slug) {
		if (count($my_site_hand_prompt_buttons) >= 6) {
			break;
		}
		if (isset($my_site_hand_prompt_pool[$my_site_hand_slug][$my_site_hand_i])) {
			$my_site_hand_prompt_buttons[] = $my_site_hand_prompt_pool[$my_site_hand_slug][$my_site_hand_i];
		}
	}
}

// Fallback if somehow no modules are enabled.
if (empty($my_site_hand_prompt_buttons)) {
	$my_site_hand_prompt_buttons = [
		__('List my 5 most recent posts', 'my-site-hand'),
		__('Run a site health report', 'my-site-hand'),
	];
}
?>
<div class="msh-wrap msh-ai-page-wrap">

	<?php require MYSITEHAND_PATH . 'templates/partials/header.php'; ?>

	<div class="msh-main-content msh-ai-layout">


		<!-- Main Chat Area -->
		<div class="msh-container msh-chat-main">

			<div class="msh-page-header">
				<h2><?php esc_html_e('AI Assistant', 'my-site-hand'); ?></h2>
				<p class="msh-page-desc">
					<?php esc_html_e('Manage your site with natural language. The assistant picks the right tool and runs it for you.', 'my-site-hand'); ?>
				</p>
			</div>

			<!-- Chat header bar -->
			<div id="msh-chat-header">
				<div class="msh-chat-header-left">
					<img src="<?php echo esc_url(MYSITEHAND_URL . 'assets/logo.png'); ?>"
						alt="<?php esc_attr_e('My Site Hand', 'my-site-hand'); ?>" class="msh-chat-header-logo" />
					<div class="msh-chat-header-status">
						<span class="msh-status-dot <?php echo $my_site_hand_is_configured ? '' : 'msh-status-dot--off'; ?>"></span>
						<span id="msh-chat-status-text">
							<?php
							printf(
								/* translators: 1: AI model name, 2: number of tools */
								esc_html__('Connected to %1$s · %2$d tools available', 'my-site-hand'),
								esc_html($my_site_hand_model_label),
								(int) $my_site_hand_tool_count
							);
							?>
						</span>
					</div>
				</div>
				<div class="msh-chat-header-actions">
					<button type="button" id="msh-history-toggle-btn" class="msh-btn msh-btn--ghost msh-btn--sm">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
						<?php esc_html_e('History', 'my-site-hand'); ?>
					</button>
					<button type="button" id="msh-chat-clear-btn" class="msh-btn msh-btn--ghost msh-btn--sm">
						<?php esc_html_e('Clear Chat', 'my-site-hand'); ?>
					</button>
				</div>
			</div>

			<!-- Chat window container -->
			<div class="msh-chat-body-wrap" style="position: relative; display: flex; flex-direction: column; flex: 1; overflow: hidden;">
				<!-- History Drawer Overlay -->
				<div id="msh-chat-sidebar-overlay" class="msh-modal-overlay"></div>

				<!-- History Drawer -->
				<div class="msh-chat-sidebar" id="msh-chat-sidebar">
					<div class="msh-sidebar-header">
						<button type="button" id="msh-new-chat-btn" class="msh-btn msh-btn--primary">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
							<?php esc_html_e('New Chat', 'my-site-hand'); ?>
						</button>
						<button type="button" id="msh-close-sidebar-btn" class="msh-btn msh-btn--ghost msh-btn--sm" style="width: auto; padding: 4px; margin-left: 8px;">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
						</button>
					</div>
					<div class="msh-chat-threads" id="msh-chat-threads">
						<!-- Threads populated by JS -->
						<div class="msh-loading-threads"><?php esc_html_e('Loading history...', 'my-site-hand'); ?></div>
					</div>
				</div>

			<!-- Chat window -->

			<div id="msh-chat-window" aria-live="polite">

				<!-- Welcome screen (shown when empty) -->
				<div class="msh-welcome-screen" id="msh-welcome-screen">
					<h3 class="msh-welcome-title"><?php esc_html_e('What would you like to do?', 'my-site-hand'); ?></h3>
					<div class="msh-welcome-prompts">
						<?php foreach ($my_site_hand_prompt_buttons as $my_site_hand_prompt) : ?>
							<button type="button" class="msh-prompt-btn"
								data-prompt="<?php echo esc_attr($my_site_hand_prompt); ?>">
								<?php echo esc_html($my_site_hand_prompt); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

			</div>

			<!-- Promo to get premium version if using proxy -->
			<?php if ( \MySiteHand\AI\AI_Provider::is_using_proxy() ) : ?>
			<div class="msh-api-promo">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; color:#f59e0b;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
				<span style="font-weight: 500; margin-right: 4px;"><?php esc_html_e('Available chats for today: ', 'my-site-hand'); ?><span id="msh-usage-text">...</span>.</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=my-site-hand-settings&tab=ai-assistant' ) ); ?>"><?php esc_html_e('Set your own API key here', 'my-site-hand'); ?></a>
				<?php esc_html_e("or don't want API hassle?", 'my-site-hand'); ?>
				<a href="mailto:taninrahman21@gmail.com?subject=My Site Hand Premium Request" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apply for Premium', 'my-site-hand'); ?></a>
				<?php esc_html_e('for unlimited chats.', 'my-site-hand'); ?>
			</div>
			<?php endif; ?>

			<!-- Input area: model picker (left) · textarea · send (right) -->
			<div id="msh-chat-input-area">
				<textarea id="msh-chat-input" rows="1"
					placeholder="<?php echo $my_site_hand_is_configured ? esc_attr__('Ask anything about your site...', 'my-site-hand') : esc_attr__('Add your API key in API Setup above to begin…', 'my-site-hand'); ?>"
					<?php echo $my_site_hand_is_configured ? '' : 'disabled'; ?>></textarea>
				<select id="msh-model-select" class="msh-model-select" title="<?php esc_attr_e('Model', 'my-site-hand'); ?>"
					<?php echo $my_site_hand_is_configured ? '' : 'disabled'; ?>>
					<option value="auto" <?php selected($my_site_hand_is_auto); ?>><?php esc_html_e('Auto', 'my-site-hand'); ?></option>
					<?php foreach ($my_site_hand_provider_models as $my_site_hand_model_opt) : ?>
						<option value="<?php echo esc_attr($my_site_hand_model_opt); ?>" <?php selected($my_site_hand_model, $my_site_hand_model_opt); ?>>
							<?php echo esc_html($my_site_hand_model_labels[$my_site_hand_model_opt] ?? $my_site_hand_model_opt); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="button" id="msh-chat-send-btn" class="msh-chat-send-btn"
					aria-label="<?php esc_attr_e('Send message', 'my-site-hand'); ?>"
					<?php echo $my_site_hand_is_configured ? '' : 'disabled'; ?>>
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
						stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="22" y1="2" x2="11" y2="13"></line>
						<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
					</svg>
				</button>
			</div>

		</div>

		</div>

		<?php require MYSITEHAND_PATH . 'templates/partials/footer.php'; ?>
	</div>
</div>
