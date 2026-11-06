<?php
/**
 * WP-CLI commands for the site builder.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

// CLI output goes to a terminal, never a browser.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Manage My Site Hand starter sites.
 */
class Site_Builder_CLI {

	/**
	 * Validate the starter site packages shipped with the plugin.
	 *
	 * Checks that every slot token has a slot and every slot is used, that all
	 * markup parses into core blocks only, that manifest references point to
	 * real files, and that every palette passes WCAG AA for text.
	 *
	 * ## OPTIONS
	 *
	 * [<slug>...]
	 * : Only validate these starter sites. Default: all of them.
	 *
	 * [--dump=<dir>]
	 * : Also write each page, part and template, rendered with default values,
	 * to <dir>/<slug>/. Useful for checking the output in the block editor.
	 *
	 * [--strict]
	 * : Treat warnings as errors.
	 *
	 * ## EXAMPLES
	 *
	 *     wp my-site-hand validate-starter-sites
	 *     wp my-site-hand validate-starter-sites clinic --dump=/tmp/starter-sites
	 *
	 * @subcommand validate-starter-sites
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function validate_starter_sites( array $args, array $assoc_args ): void {
		$library   = new Starter_Site_Library();
		$validator = new Starter_Site_Validator();
		$sites     = $library->get_all();
		$strict    = ! empty( $assoc_args['strict'] );
		$dump      = isset( $assoc_args['dump'] ) ? trailingslashit( (string) $assoc_args['dump'] ) : '';

		foreach ( $library->get_errors() as $slug => $error ) {
			\WP_CLI::warning( "$slug: " . $error->get_error_message() );
		}

		if ( $args ) {
			$unknown = array_diff( $args, array_keys( $sites ) );
			if ( $unknown ) {
				\WP_CLI::error( 'Unknown starter site: ' . implode( ', ', $unknown ) );
			}
			$sites = array_intersect_key( $sites, array_flip( $args ) );
		}

		if ( ! $sites ) {
			\WP_CLI::error( 'No starter sites found in ' . Starter_Site_Library::get_base_dir() );
		}

		$failed = 0;

		// The shared section library any page can add sections from.
		$lib = $validator->validate_library( $library );
		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( '%Bsection library%n (' . count( $library->get_section_types() ) . ' section types)' ) );
		foreach ( $lib['errors'] as $message ) {
			\WP_CLI::log( \WP_CLI::colorize( '  %RERROR%n   ' ) . $message );
		}
		if ( $lib['errors'] ) {
			++$failed;
		} else {
			\WP_CLI::log( \WP_CLI::colorize( '  %Gpassed%n' ) );
		}

		foreach ( $sites as $slug => $site ) {
			$result = $validator->validate( $site );
			$errors = $strict ? array_merge( $result['errors'], $result['warnings'] ) : $result['errors'];

			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( "%B$slug%n (" . count( $site->get_pages() ) . ' pages, ' . array_sum( array_map( static fn( $p ) => count( $p['sections'] ), $site->get_pages() ) ) . ' sections, ' . count( $site->get_slots() ) . ' slots, ' . count( $site->get_palettes() ) . ' palettes, ' . count( $site->get_font_pairs() ) . ' font pairs)' ) );

			foreach ( $result['errors'] as $message ) {
				\WP_CLI::log( \WP_CLI::colorize( '  %RERROR%n   ' ) . $message );
			}
			foreach ( $result['warnings'] as $message ) {
				\WP_CLI::log( \WP_CLI::colorize( '  %YWARNING%n ' ) . $message );
			}

			if ( $errors ) {
				++$failed;
			} else {
				\WP_CLI::log( \WP_CLI::colorize( '  %Gpassed%n' ) );
			}

			if ( $dump ) {
				foreach ( Starter_Site_Validator::render_defaults( $site ) as $relative => $markup ) {
					$target = $dump . $slug . '/' . $relative;
					wp_mkdir_p( dirname( $target ) );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					file_put_contents( $target, $markup );
				}
				\WP_CLI::log( "  rendered defaults written to $dump$slug/" );
			}
		}

		\WP_CLI::log( '' );

		if ( $failed ) {
			\WP_CLI::error( "$failed of " . count( $sites ) . ' starter site(s) failed validation.' );
		}

		\WP_CLI::success( count( $sites ) . ' starter site(s) passed validation.' );
	}
}
