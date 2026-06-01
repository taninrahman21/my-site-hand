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

// Only delete data if the user opted in.
$my_site_hand_delete = get_option( 'mysitehand_delete_data_on_uninstall', false );

if ( ! $my_site_hand_delete ) {
	return;
}

global $wpdb;

// Drop custom tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mysitehand_tokens`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mysitehand_audit_log`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mysitehand_rate_limits`" );

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
