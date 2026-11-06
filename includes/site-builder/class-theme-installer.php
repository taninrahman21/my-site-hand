<?php
/**
 * Installs and activates a block theme from WordPress.org, on request.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Theme_Installer class.
 *
 * Starter sites need a block theme. When the active theme is a classic one,
 * the Site Builder offers Twenty Twenty-Five, and this class does the work
 * only after the user confirms. It uses core's own Theme_Upgrader, exactly
 * like Appearance > Themes > Add New.
 */
class Theme_Installer {

	/**
	 * Themes this class will install. Never user input.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED = [ 'twentytwentyfive' ];

	/**
	 * Install the theme if missing, then activate it.
	 *
	 * @param string $slug Theme slug.
	 * @return true|\WP_Error
	 */
	public static function install_and_activate( string $slug ) {
		if ( ! in_array( $slug, self::ALLOWED, true ) ) {
			return new \WP_Error( 'mysitehand_theme_not_allowed', __( 'That theme cannot be installed from here.', 'my-site-hand' ), [ 'status' => 400 ] );
		}

		if ( ! current_user_can( 'switch_themes' ) ) {
			return new \WP_Error( 'mysitehand_cannot_switch', __( 'You are not allowed to change the theme.', 'my-site-hand' ), [ 'status' => 403 ] );
		}

		$theme = wp_get_theme( $slug );

		if ( ! $theme->exists() ) {
			if ( ! current_user_can( 'install_themes' ) ) {
				return new \WP_Error( 'mysitehand_cannot_install', __( 'You are not allowed to install themes. Ask a site administrator to install Twenty Twenty-Five.', 'my-site-hand' ), [ 'status' => 403 ] );
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$api = themes_api(
				'theme_information',
				[
					'slug'   => $slug,
					'fields' => [ 'sections' => false ],
				]
			);

			if ( is_wp_error( $api ) ) {
				return $api;
			}

			$upgrader = new \Theme_Upgrader( new \WP_Ajax_Upgrader_Skin() );
			$result   = $upgrader->install( $api->download_link );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( true !== $result ) {
				$errors = $upgrader->skin->get_errors();
				return $errors->has_errors()
					? $errors
					: new \WP_Error( 'mysitehand_theme_install_failed', __( 'The theme could not be installed. Check that WordPress can write to wp-content/themes.', 'my-site-hand' ), [ 'status' => 500 ] );
			}

			$theme = wp_get_theme( $slug );
		}

		if ( ! $theme->exists() || ! $theme->is_block_theme() ) {
			return new \WP_Error( 'mysitehand_theme_missing', __( 'The theme was not found after installing.', 'my-site-hand' ), [ 'status' => 500 ] );
		}

		switch_theme( $theme->get_stylesheet() );

		return true;
	}
}
