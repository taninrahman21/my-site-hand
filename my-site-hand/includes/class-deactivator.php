<?php
/**
 * Plugin deactivator.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivator class.
 *
 * Handles plugin deactivation: clears scheduled events and transients.
 * Data is preserved — uninstall.php handles data deletion.
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::unschedule_cron();
		flush_rewrite_rules();
	}

	/**
	 * Remove all scheduled WP-Cron events for this plugin.
	 *
	 * @return void
	 */
	private static function unschedule_cron(): void {
		$hooks = [
			'my_site_hand_cleanup_logs',
			'my_site_hand_cleanup_expired_tokens',
		];

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}




