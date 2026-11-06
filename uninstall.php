<?php
/**
 * Uninstall script — runs only when the plugin is deleted via WP Admin.
 *
 * @package MySiteHand
 */

// Security: WordPress must initiate this uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The AI settings go regardless of the opt-in below. The API key is a
// credential for someone else's paid service; it must not outlive the plugin
// that was trusted with it. Re-pasting a key after a reinstall is a small
// price for that.
$my_site_hand_ai_options = [
	'mysitehand_ai_provider',
	'mysitehand_ai_api_key',
	'mysitehand_ai_model',
	'mysitehand_ai_daily_cap',
	'mysitehand_ai_usage',
];

foreach ( $my_site_hand_ai_options as $my_site_hand_ai_option ) {
	delete_option( $my_site_hand_ai_option );
}

global $wpdb;

// Site Builder scratch data goes regardless of the opt-in too: without the
// plugin, drafts, Undo snapshots and preview links can never be used again.
// APPLIED SITES ARE NOT TOUCHED. Pages, menus, images, template parts and
// global styles created by Apply are ordinary WordPress content that belongs
// to the site owner, and they stay exactly as they are.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$my_site_hand_staged_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'msh_staged_site'" );
foreach ( $my_site_hand_staged_ids as $my_site_hand_staged_id ) {
	wp_delete_post( (int) $my_site_hand_staged_id, true );
}

foreach ( [ 'mysitehand_site_snapshots', 'mysitehand_show_welcome', 'mysitehand_site_builder_module_v140' ] as $my_site_hand_builder_option ) {
	delete_option( $my_site_hand_builder_option );
}

delete_metadata( 'user', 0, 'mysitehand_preview_token', '', true );
delete_metadata( 'user', 0, 'mysitehand_welcome_dismissed', '', true );
delete_transient( 'mysitehand_apply_lock' );

// Preview tokens are transients keyed by a hash of the token.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM `{$wpdb->options}`
	WHERE `option_name` LIKE '\\_transient\\_mysitehand\\_preview\\_%'
	OR `option_name` LIKE '\\_transient\\_timeout\\_mysitehand\\_preview\\_%'"
);

// Only delete data if the user opted in.
$my_site_hand_delete = get_option( 'mysitehand_delete_data_on_uninstall', false );

if ( ! $my_site_hand_delete ) {
	return;
}

// Drop custom tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mysitehand_tokens`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mysitehand_audit_log`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mysitehand_rate_limits`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mysitehand_health_history`" );

// Delete all plugin options.
$my_site_hand_options = [
	'mysitehand_version',
	'mysitehand_enabled',
	'mysitehand_display_name',
	'mysitehand_hourly_limit',
	'mysitehand_daily_limit',
	'mysitehand_enabled_modules',
	'mysitehand_cache_ttl',
	'mysitehand_log_retention_days',
	'mysitehand_log_level',
	'mysitehand_delete_data_on_uninstall',
	'mysitehand_last_scan',
	'mysitehand_weekly_report_enabled',
	'mysitehand_weekly_report_email',
	'mysitehand_report_frequency',
	'mysitehand_cron_scan_progress',
	// Already removed above; listed so this array stays the full inventory.
	'mysitehand_ai_provider',
	'mysitehand_ai_api_key',
	'mysitehand_ai_model',
	'mysitehand_ai_daily_cap',
	'mysitehand_ai_usage',
];

foreach ( $my_site_hand_options as $my_site_hand_option ) {
	delete_option( $my_site_hand_option );
}

// Delete all my-site-hand transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM `{$wpdb->options}`
	WHERE `option_name` LIKE '_transient_MYSITEHAND_%'
	OR `option_name` LIKE '_transient_timeout_MYSITEHAND_%'"
);

// Remove cron schedule.
wp_clear_scheduled_hook( 'my_site_hand_cleanup_logs' );
wp_clear_scheduled_hook( 'my_site_hand_cleanup_expired_tokens' );
wp_clear_scheduled_hook( 'my_site_hand_health_scan' );
wp_clear_scheduled_hook( 'my_site_hand_health_scan_continue' );

// Per-user Site Health state.
delete_metadata( 'user', 0, 'mysitehand_review_state', '', true );
delete_metadata( 'user', 0, 'mysitehand_fix_count', '', true );
