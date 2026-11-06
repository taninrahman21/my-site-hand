<?php
/**
 * AI Bulk Fix entry point for one Site Health check.
 *
 * Only ever included when an API key is configured and the check is one AI may
 * fix. Expects $my_site_hand_id (check id) and $my_site_hand_entry (stored
 * report row, or null) from templates/admin/health.php.
 *
 * The review panel itself is built by assets/js/health-ai-fix.js.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_ai_count = null !== $my_site_hand_entry ? count((array) ($my_site_hand_entry['issues'] ?? [])) : 0;
?>
	<div class="msh-aifix" data-check="<?php echo esc_attr($my_site_hand_id); ?>" <?php echo $my_site_hand_ai_count > 0 ? '' : 'hidden'; ?>>
		<div class="msh-aifix-bar">
			<button type="button" class="msh-btn msh-btn--sm msh-aifix-start"
				aria-expanded="false" aria-controls="msh-aifix-panel-<?php echo esc_attr($my_site_hand_id); ?>">
				<span aria-hidden="true">&#10024;</span>
				<span class="msh-aifix-start-label"><?php
				printf(
					/* translators: %d: number of issues AI will write fixes for */
					esc_html(_n('Fix %d with AI', 'Fix all %d with AI', $my_site_hand_ai_count, 'my-site-hand')),
					esc_html((string) $my_site_hand_ai_count)
				);
				?></span>
			</button>
			<span class="msh-aifix-hint"><?php esc_html_e('You review every suggestion before anything is saved.', 'my-site-hand'); ?></span>
		</div>
		<div class="msh-aifix-panel" id="msh-aifix-panel-<?php echo esc_attr($my_site_hand_id); ?>" hidden></div>
	</div>
