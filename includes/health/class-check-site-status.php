<?php
/**
 * Site Health check: plugin, core, and platform status.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Check: Plugin and Core Health.
 *
 * Pending updates, an unsupported PHP version, a site served over plain HTTP,
 * errors printed to visitors, and a stalled cron. Detection mirrors
 * Module_Diagnostics so the AI report and the Site Health page never disagree.
 *
 * Everything here is read from data WordPress already has, so the check has no
 * meaningful batching: it completes in a single pass.
 */
class Check_Site_Status extends Health_Check_Base {

	/**
	 * Lowest PHP version still receiving security fixes.
	 *
	 * PHP 8.1 reached end of life in December 2025. The plugin still runs on
	 * it, but the user is running unpatched software and should know.
	 *
	 * @var string
	 */
	private const SUPPORTED_PHP = '8.2';

	/**
	 * How overdue cron events must be before cron counts as stalled.
	 *
	 * @var int
	 */
	private const CRON_STALE_AFTER = HOUR_IN_SECONDS;

	/**
	 * Stable machine id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'site-status';
	}

	/**
	 * Translated check name.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Plugin & Core Health', 'my-site-hand' );
	}

	/**
	 * Translated one-line description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Checks for pending updates, an outdated PHP version, missing HTTPS, and other platform problems.', 'my-site-hand' );
	}

	/**
	 * Default severity for issues from this check.
	 *
	 * @return string
	 */
	public function get_severity(): string {
		return self::SEVERITY_CRITICAL;
	}

	/**
	 * Single pass — nothing here benefits from batching.
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 1;
	}

	/**
	 * Evaluate every status condition in one pass.
	 *
	 * @param int $offset Unused; this check always completes in one call.
	 * @return array<string, mixed>
	 */
	public function run( int $offset = 0 ): array {
		$issues = array_merge(
			$this->check_plugin_updates(),
			$this->check_core_update(),
			$this->check_php_version(),
			$this->check_https(),
			$this->check_debug_display(),
			$this->check_cron()
		);

		// Six conditions, evaluated whether or not they produced an issue.
		return $this->make_result( true, null, 6, $issues );
	}

	/**
	 * Plugins with a pending update.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function check_plugin_updates(): array {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$updates = get_plugin_updates();

		if ( ! is_array( $updates ) || empty( $updates ) ) {
			return [];
		}

		$issues = [];
		$url    = admin_url( 'plugins.php' );

		foreach ( $updates as $plugin_data ) {
			$name        = isset( $plugin_data->Name ) ? (string) $plugin_data->Name : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$current     = isset( $plugin_data->Version ) ? (string) $plugin_data->Version : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$new_version = isset( $plugin_data->update->new_version ) ? (string) $plugin_data->update->new_version : '';

			if ( '' === $name ) {
				continue;
			}

			$issues[] = $this->make_issue(
				[
					'title'    => sprintf(
						/* translators: 1: plugin name, 2: installed version, 3: available version */
						__( '%1$s %2$s is out of date (%3$s available)', 'my-site-hand' ),
						$name,
						$current,
						'' !== $new_version ? $new_version : __( 'a newer version', 'my-site-hand' )
					),
					'context'  => __( 'Pending plugin update', 'my-site-hand' ),
					'severity' => $this->is_security_release( $plugin_data ) ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING,
					'link'     => $url,
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => $url ],
					'fixable'  => false,
					'ability'  => null,
				]
			);
		}

		return $issues;
	}

	/**
	 * Whether an update advertises itself as a security release.
	 *
	 * WordPress does not flag security releases in a structured way, so this
	 * reads the upgrade notice when there is one. Absence of evidence is not
	 * treated as evidence.
	 *
	 * @param object $plugin_data Plugin data with an attached update object.
	 * @return bool
	 */
	private function is_security_release( object $plugin_data ): bool {
		$notice = $plugin_data->update->upgrade_notice ?? '';

		if ( ! is_string( $notice ) || '' === $notice ) {
			return false;
		}

		return false !== stripos( $notice, 'security' ) || false !== stripos( $notice, 'vulnerability' );
	}

	/**
	 * Pending WordPress core update.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function check_core_update(): array {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates = get_core_updates();

		if ( ! is_array( $updates ) || empty( $updates[0] ) || 'upgrade' !== ( $updates[0]->response ?? '' ) ) {
			return [];
		}

		$url = admin_url( 'update-core.php' );

		return [
			$this->make_issue(
				[
					'title'    => sprintf(
						/* translators: %s: WordPress version */
						__( 'WordPress %s is available', 'my-site-hand' ),
						(string) ( $updates[0]->current ?? '' )
					),
					'context'  => sprintf(
						/* translators: %s: installed WordPress version */
						__( 'You are running %s', 'my-site-hand' ),
						get_bloginfo( 'version' )
					),
					'severity' => self::SEVERITY_WARNING,
					'link'     => $url,
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => $url ],
					'fixable'  => false,
					'ability'  => null,
				]
			),
		];
	}

	/**
	 * PHP version below the actively supported minimum.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function check_php_version(): array {
		/**
		 * Filter the lowest PHP version considered actively supported.
		 *
		 * @param string $version Minimum version.
		 */
		$minimum = (string) apply_filters( 'my_site_hand_health_supported_php', self::SUPPORTED_PHP );

		if ( version_compare( PHP_VERSION, $minimum, '>=' ) ) {
			return [];
		}

		$url = admin_url( 'site-health.php' );

		return [
			$this->make_issue(
				[
					'title'    => sprintf(
						/* translators: %s: PHP version */
						__( 'PHP %s no longer receives security updates', 'my-site-hand' ),
						PHP_VERSION
					),
					'context'  => sprintf(
						/* translators: %s: minimum supported PHP version */
						__( 'Ask your host to move you to PHP %s or newer', 'my-site-hand' ),
						$minimum
					),
					'severity' => self::SEVERITY_CRITICAL,
					'link'     => $url,
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => $url ],
					'fixable'  => false,
					'ability'  => null,
				]
			),
		];
	}

	/**
	 * Site not served over HTTPS.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function check_https(): array {
		if ( 'https' === strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ) ) {
			return [];
		}

		$url = admin_url( 'options-general.php' );

		return [
			$this->make_issue(
				[
					'title'    => __( 'Your site is not served over HTTPS', 'my-site-hand' ),
					'context'  => __( 'Browsers mark plain HTTP sites as insecure', 'my-site-hand' ),
					'severity' => self::SEVERITY_CRITICAL,
					'link'     => $url,
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => $url ],
					'fixable'  => false,
					'ability'  => null,
				]
			),
		];
	}

	/**
	 * PHP errors printed to visitors on a production site.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function check_debug_display(): array {
		$debug_on = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$displays = ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY;

		if ( ! $debug_on || ! $displays ) {
			return [];
		}

		// Debug output on a staging or local site is intentional.
		if ( function_exists( 'wp_get_environment_type' ) && 'production' !== wp_get_environment_type() ) {
			return [];
		}

		$url = admin_url( 'site-health.php?tab=debug' );

		return [
			$this->make_issue(
				[
					'title'    => __( 'Debug output is visible to your visitors', 'my-site-hand' ),
					'context'  => __( 'Set WP_DEBUG_DISPLAY to false in wp-config.php', 'my-site-hand' ),
					'severity' => self::SEVERITY_CRITICAL,
					'link'     => $url,
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => $url ],
					'fixable'  => false,
					'ability'  => null,
				]
			),
		];
	}

	/**
	 * Scheduled tasks not running.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function check_cron(): array {
		$url = admin_url( 'site-health.php' );

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return [
				$this->make_issue(
					[
						'title'    => __( 'WordPress cron is disabled', 'my-site-hand' ),
						'context'  => __( 'Scheduled tasks only run if a real cron job calls wp-cron.php', 'my-site-hand' ),
						'severity' => self::SEVERITY_WARNING,
						'link'     => $url,
						'fix_type' => 'external_link',
						'fix_meta' => [ 'url' => $url ],
						'fixable'  => false,
						'ability'  => null,
					]
				),
			];
		}

		$crons = _get_cron_array();

		if ( ! is_array( $crons ) || empty( $crons ) ) {
			return [];
		}

		$cutoff  = time() - self::CRON_STALE_AFTER;
		$overdue = 0;

		foreach ( $crons as $timestamp => $hooks ) {
			if ( (int) $timestamp < $cutoff ) {
				$overdue += count( (array) $hooks );
			}
		}

		if ( 0 === $overdue ) {
			return [];
		}

		return [
			$this->make_issue(
				[
					'title'    => sprintf(
						/* translators: %d: number of overdue scheduled tasks */
						_n(
							'%d scheduled task is overdue',
							'%d scheduled tasks are overdue',
							$overdue,
							'my-site-hand'
						),
						$overdue
					),
					'context'  => __( 'Cron may not be running on this site', 'my-site-hand' ),
					'severity' => self::SEVERITY_WARNING,
					'link'     => $url,
					'fix_type' => 'external_link',
					'fix_meta' => [ 'url' => $url ],
					'fixable'  => false,
					'ability'  => null,
				]
			),
		];
	}
}
