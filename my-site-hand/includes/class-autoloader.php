<?php
/**
 * PSR-4 class autoloader.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 *
 * Maps the MySiteHand namespace to the plugin directory structure.
 */
class Autoloader {

	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private static string $prefix = 'MySiteHand\\';

	/**
	 * Base directory for the namespace prefix.
	 *
	 * @var string
	 */
	private static string $base_dir;

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::$base_dir = MYSITEHAND_PATH;
		spl_autoload_register( [ self::class, 'load_class' ] );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class The fully-qualified class name.
	 * @return void
	 */
	public static function load_class( string $class ): void {
		// Check if class uses our prefix.
		$len = strlen( self::$prefix );
		if ( strncmp( self::$prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Strip prefix and convert to file path.
		$relative_class = substr( $class, $len );

		// Build the file path from the class name.
		$file = self::resolve_file( $relative_class );

		if ( $file && file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Resolve a class name to a file path.
	 *
	 * Maps MySiteHand\Admin\Admin_Dashboard → admin/class-admin-dashboard.php
	 * Maps MySiteHand\Modules\Module_Content → includes/modules/class-module-content.php
	 * Maps MySiteHand\Plugin → includes/class-plugin.php
	 *
	 * @param string $relative_class Class name without namespace prefix.
	 * @return string|null File path or null if not resolvable.
	 */
	private static function resolve_file( string $relative_class ): ?string {
		// Convert underscores to hyphens and make lowercase for WP naming.
		$parts = explode( '\\', $relative_class );

		// Determine subdirectory and filename.
		if ( count( $parts ) === 1 ) {
			// Top-level class: MySiteHand\Plugin → includes/class-plugin.php
			$filename = 'class-' . self::to_kebab( $parts[0] ) . '.php';
			return self::$base_dir . 'includes/' . $filename;
		}

		$sub_namespace = array_shift( $parts );
		$class_name    = array_pop( $parts );
		$filename      = 'class-' . self::to_kebab( $class_name ) . '.php';

		switch ( strtolower( $sub_namespace ) ) {
			case 'admin':
				return self::$base_dir . 'admin/' . $filename;
			case 'modules':
				return self::$base_dir . 'includes/modules/' . $filename;
			case 'api':
				return self::$base_dir . 'api/' . $filename;
			default:
				return self::$base_dir . 'includes/' . $filename;
		}
	}

	/**
	 * Convert a PascalCase class name to kebab-case file name segment.
	 *
	 * @param string $class_name PascalCase class name.
	 * @return string Kebab-case equivalent.
	 */
	private static function to_kebab( string $class_name ): string {
		// Convert underscores to hyphens and lowercase.
		return strtolower( str_replace( '_', '-', $class_name ) );
	}
}




