<?php
/**
 * Developer verification script for the Site Health scanner.
 *
 * Runs the large-media check to completion one batch at a time — exactly the
 * way the browser will drive it — then finalizes and prints the report. This
 * is how the scanner is verified before any UI exists.
 *
 * Not part of the shipped plugin: exclude tools/ from the release build.
 *
 * Runs through WP-CLI rather than bootstrapping WordPress itself, so the file
 * can carry the ABSPATH guard that WordPress.org requires of every PHP file.
 * WP-CLI also resolves the database connection, which spares this script the
 * DB_HOST override that a standalone bootstrap needed on Local by Flywheel.
 *
 * Usage (from the plugin directory):
 *
 *   wp eval-file tools/verify-health-scan.php
 *
 * On Local by Flywheel, use the site shell (Local: right-click the site →
 * "Open site shell"), which puts the right PHP and database host on PATH.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;

// Output goes to a terminal, never to a browser, so the HTML escaping the
// sniff asks for would corrupt the report instead of protecting anything.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run this with: wp eval-file tools/verify-health-scan.php\n" );
}

/**
 * Print a labelled section header.
 *
 * @param string $title Section title.
 * @return void
 */
function mysitehand_verify_section( string $title ): void {
	echo "\n" . str_repeat( '=', 68 ) . "\n" . $title . "\n" . str_repeat( '=', 68 ) . "\n";
}

$mysitehand_scanner = MySiteHand\Plugin::get_instance()->get_site_health_scanner();
$mysitehand_checks  = $mysitehand_scanner->get_checks();

mysitehand_verify_section( 'Registered checks' );

foreach ( $mysitehand_checks as $mysitehand_id => $mysitehand_check ) {
	printf(
		"  %-26s %-9s batch:%-4d applicable:%s\n",
		$mysitehand_id,
		$mysitehand_check->get_severity(),
		$mysitehand_check->get_batch_size(),
		$mysitehand_check->is_applicable() ? 'yes' : 'no'
	);
}

// Unknown ids must degrade to WP_Error, never a fatal.
$mysitehand_unknown = $mysitehand_scanner->run_check( 'no-such-check' );
printf(
	"\n  Unknown check id returns: %s\n",
	is_wp_error( $mysitehand_unknown ) ? 'WP_Error (' . $mysitehand_unknown->get_error_code() . ')' : 'UNEXPECTED ' . gettype( $mysitehand_unknown )
);

mysitehand_verify_section( 'Running: large-media' );

$mysitehand_scanner->clear_run_state();

$mysitehand_offset  = 0;
$mysitehand_batch   = 0;
$mysitehand_guard   = 0;
$mysitehand_last_at = -1;

do {
	$mysitehand_result = $mysitehand_scanner->run_check( 'large-media', $mysitehand_offset );

	if ( is_wp_error( $mysitehand_result ) ) {
		printf( "  ERROR: %s\n", $mysitehand_result->get_error_message() );
		break;
	}

	++$mysitehand_batch;

	printf(
		"  batch %-3d offset:%-6d scanned:%-4d cumulative:%-6d issues:%-4d status:%s\n",
		$mysitehand_batch,
		$mysitehand_offset,
		$mysitehand_result['batch_scanned'],
		$mysitehand_result['scanned'],
		$mysitehand_result['issues_found'],
		$mysitehand_result['status']
	);

	foreach ( $mysitehand_result['batch_issues'] as $mysitehand_issue ) {
		printf(
			"      - %s%s\n",
			$mysitehand_issue['title'],
			null !== $mysitehand_issue['context'] ? '  [' . $mysitehand_issue['context'] . ']' : ''
		);

		foreach ( [ 'fix_type', 'fix_meta', 'fixable', 'ability' ] as $mysitehand_key ) {
			if ( ! array_key_exists( $mysitehand_key, $mysitehand_issue ) ) {
				printf( "        !! missing required key: %s\n", $mysitehand_key );
			}
		}
	}

	if ( $mysitehand_result['done'] ) {
		break;
	}

	// Guard against a check that never advances.
	if ( $mysitehand_result['next_offset'] === $mysitehand_last_at ) {
		echo "  !! next_offset did not advance — aborting\n";
		break;
	}

	$mysitehand_last_at = $mysitehand_result['next_offset'];
	$mysitehand_offset  = (int) $mysitehand_result['next_offset'];
	++$mysitehand_guard;
} while ( $mysitehand_guard < 10000 );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$mysitehand_expected = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'trash'"
);

$mysitehand_state = $mysitehand_scanner->get_run_state();

printf(
	"\n  Attachments in database: %d\n  Scanned by check:        %d  %s\n",
	$mysitehand_expected,
	$mysitehand_state['checks']['large-media']['scanned'],
	(int) $mysitehand_state['checks']['large-media']['scanned'] === $mysitehand_expected ? 'MATCH' : 'MISMATCH'
);

$mysitehand_large = $mysitehand_scanner->get_check( 'large-media' );
printf( "  Reclaimable space:       %s\n", size_format( $mysitehand_large->get_total_bytes(), 1 ) );

mysitehand_verify_section( 'Scoring' );

printf( "  no issues                       -> %d\n", $mysitehand_scanner->calculate_score( [] ) );

$mysitehand_many = [
	'large-media' => [
		'status' => 'done',
		'issues' => array_fill( 0, 500, [ 'severity' => 'notice' ] ),
	],
];
printf(
	"  500 notice issues, one check    -> %d  (deduction capped at %d)\n",
	$mysitehand_scanner->calculate_score( $mysitehand_many ),
	$mysitehand_scanner->calculate_deduction( 'notice', $mysitehand_many['large-media']['issues'] )
);

$mysitehand_everything = [];
foreach ( [ 'critical', 'warning', 'notice' ] as $mysitehand_sev ) {
	for ( $mysitehand_i = 0; $mysitehand_i < 4; $mysitehand_i++ ) {
		$mysitehand_everything[ $mysitehand_sev . $mysitehand_i ] = [
			'status'   => 'done',
			'severity' => $mysitehand_sev,
			'issues'   => array_fill( 0, 100, [ 'severity' => $mysitehand_sev ] ),
		];
	}
}
printf( "  every check saturated           -> %d  (floors at 0)\n", $mysitehand_scanner->calculate_score( $mysitehand_everything ) );

$mysitehand_skipped = [
	'a' => [
		'status' => 'skipped',
		'issues' => array_fill( 0, 50, [ 'severity' => 'critical' ] ),
	],
	'b' => [
		'status' => 'error',
		'issues' => array_fill( 0, 50, [ 'severity' => 'critical' ] ),
	],
];
printf( "  skipped + errored checks        -> %d  (deduct nothing)\n", $mysitehand_scanner->calculate_score( $mysitehand_skipped ) );

foreach ( [ 100, 80, 79, 50, 49, 0 ] as $mysitehand_score ) {
	$mysitehand_band = $mysitehand_scanner->get_score_band( $mysitehand_score );
	printf( "  band(%3d) = %-8s %s\n", $mysitehand_score, $mysitehand_band['band'], $mysitehand_band['label'] );
}

mysitehand_verify_section( 'Finalized report' );

$mysitehand_report = $mysitehand_scanner->finalize();

printf( "  version:        %d\n", $mysitehand_report['version'] );
printf( "  score:          %d (%s)\n", $mysitehand_report['score'], $mysitehand_report['band'] );
printf( "  previous score: %s\n", null === $mysitehand_report['previous_score'] ? 'none' : $mysitehand_report['previous_score'] );
printf( "  duration:       %d ms\n", $mysitehand_report['duration_ms'] );

foreach ( $mysitehand_report['checks'] as $mysitehand_id => $mysitehand_entry ) {
	printf(
		"  %-26s status:%-8s scanned:%-6d issues:%-5d stored:%-3d truncated:%s deduction:%d\n",
		$mysitehand_id,
		$mysitehand_entry['status'],
		$mysitehand_entry['scanned'],
		$mysitehand_entry['issue_count'],
		count( $mysitehand_entry['issues'] ),
		$mysitehand_entry['truncated'] ? 'yes' : 'no',
		$mysitehand_entry['deduction']
	);
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$mysitehand_autoload = $wpdb->get_var(
	$wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'mysitehand_last_scan' )
);
printf( "\n  mysitehand_last_scan autoload = %s\n", var_export( $mysitehand_autoload, true ) );

mysitehand_verify_section( 'History' );

foreach ( $mysitehand_scanner->get_history( 8 ) as $mysitehand_row ) {
	printf( "  #%-5d %s  score:%d\n", $mysitehand_row['id'], $mysitehand_row['scanned_at'], $mysitehand_row['score'] );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$mysitehand_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mysitehand_health_history" );
printf( "\n  rows retained: %d (cap %d)\n\n", $mysitehand_rows, MySiteHand\Site_Health_Scanner::HISTORY_LIMIT );
