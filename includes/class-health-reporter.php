<?php
/**
 * Scheduled Site Health scan and the report email.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Health Reporter class.
 *
 * Runs the scan on a schedule with no browser involved, and mails the result
 * when there is something worth saying.
 *
 * The checks are built for browser-driven batching, so the cron handler loops
 * the batches itself behind a wall-clock guard: it stops well short of
 * max_execution_time, reschedules a continuation, and resumes where it left
 * off. A cron run must never be the thing that takes a site down.
 */
class Health_Reporter {

	/**
	 * Recurring scan event.
	 *
	 * @var string
	 */
	public const CRON_SCAN = 'my_site_hand_health_scan';

	/**
	 * One-off continuation event for a scan that ran out of time.
	 *
	 * @var string
	 */
	public const CRON_CONTINUE = 'my_site_hand_health_scan_continue';

	/**
	 * Option holding the position of an in-progress scheduled scan.
	 *
	 * @var string
	 */
	private const PROGRESS_OPTION = 'mysitehand_cron_scan_progress';

	/**
	 * Seconds a single cron run may spend scanning.
	 *
	 * Deliberately far below the usual thirty second limit: cron competes with
	 * whatever else the request is doing.
	 *
	 * @var int
	 */
	private const TIME_BUDGET = 20;

	/**
	 * Delay before a continuation run.
	 *
	 * @var int
	 */
	private const CONTINUE_DELAY = 60;

	/**
	 * Published posts above which broken-links is skipped on a schedule.
	 *
	 * Outbound HTTP from cron on shared hosting is where this feature breaks
	 * first, so large sites keep the link check as a manual action.
	 *
	 * @var int
	 */
	private const LINK_SKIP_THRESHOLD = 500;

	/**
	 * Site Health scanner.
	 *
	 * @var Site_Health_Scanner
	 */
	private Site_Health_Scanner $scanner;

	/**
	 * Constructor.
	 *
	 * @param Site_Health_Scanner $scanner Site Health scanner.
	 */
	public function __construct( Site_Health_Scanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'cron_schedules', [ $this, 'register_schedules' ] ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::CRON_SCAN, [ $this, 'run_scheduled_scan' ] );
		add_action( self::CRON_CONTINUE, [ $this, 'run_scheduled_scan' ] );
		add_action( 'admin_post_nopriv_my_site_hand_report_unsubscribe', [ $this, 'handle_unsubscribe' ] );
		add_action( 'admin_post_my_site_hand_report_unsubscribe', [ $this, 'handle_unsubscribe' ] );
		add_action( 'update_option_mysitehand_report_frequency', [ $this, 'sync_schedule' ] );
		add_action( 'update_option_mysitehand_weekly_report_enabled', [ $this, 'sync_schedule' ] );
	}

	/**
	 * Add a monthly interval; WordPress only ships up to weekly.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_schedules( array $schedules ): array {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = [
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'my-site-hand' ),
			];
		}

		return $schedules;
	}

	/**
	 * Bring the scheduled event in line with the current settings.
	 *
	 * @return void
	 */
	public function sync_schedule(): void {
		$frequency = $this->get_frequency();
		$existing  = wp_next_scheduled( self::CRON_SCAN );

		if ( 'never' === $frequency || ! $this->is_enabled() ) {
			if ( $existing ) {
				wp_unschedule_event( $existing, self::CRON_SCAN );
			}

			$continuation = wp_next_scheduled( self::CRON_CONTINUE );

			if ( $continuation ) {
				wp_unschedule_event( $continuation, self::CRON_CONTINUE );
			}

			return;
		}

		// Already scheduled at the right interval: leave it alone.
		if ( $existing && wp_get_schedule( self::CRON_SCAN ) === $frequency ) {
			return;
		}

		if ( $existing ) {
			wp_unschedule_event( $existing, self::CRON_SCAN );
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, self::CRON_SCAN );
	}

	/**
	 * Run (or resume) a scheduled scan.
	 *
	 * @return void
	 */
	public function run_scheduled_scan(): void {
		$checks = array_keys( $this->get_scheduled_checks() );

		if ( empty( $checks ) ) {
			return;
		}

		$progress = $this->get_progress();

		// A fresh run starts from a clean slate.
		if ( 0 === $progress['index'] && 0 === $progress['offset'] ) {
			$this->scanner->clear_run_state();
		}

		$deadline = microtime( true ) + self::TIME_BUDGET;

		for ( $index = $progress['index']; $index < count( $checks ); $index++ ) {
			$offset = ( $index === $progress['index'] ) ? $progress['offset'] : 0;

			do {
				$result = $this->scanner->run_check( $checks[ $index ], $offset );

				if ( is_wp_error( $result ) ) {
					break;
				}

				if ( ! empty( $result['done'] ) ) {
					break;
				}

				$next = isset( $result['next_offset'] ) ? (int) $result['next_offset'] : null;

				// A check that stops advancing would spin forever.
				if ( null === $next || $next <= $offset ) {
					break;
				}

				$offset = $next;

				if ( microtime( true ) >= $deadline ) {
					$this->save_progress( $index, $offset );
					$this->schedule_continuation();
					return;
				}
			} while ( true );

			if ( microtime( true ) >= $deadline && $index + 1 < count( $checks ) ) {
				$this->save_progress( $index + 1, 0 );
				$this->schedule_continuation();
				return;
			}
		}

		$this->clear_progress();

		$previous = $this->scanner->get_history( 1 );
		$previous = $previous[0]['summary'] ?? [];

		$report = $this->scanner->finalize();

		$this->maybe_send_report( $report, is_array( $previous ) ? $previous : [] );
	}

	/**
	 * Checks that take part in a scheduled scan.
	 *
	 * @return array<string, Health_Check_Base>
	 */
	public function get_scheduled_checks(): array {
		$checks = $this->scanner->get_checks();

		if ( isset( $checks['broken-links'] ) && $this->should_skip_link_check() ) {
			unset( $checks['broken-links'] );
		}

		return $checks;
	}

	/**
	 * Whether this site is too large for outbound link checking on a schedule.
	 *
	 * @return bool
	 */
	private function should_skip_link_check(): bool {
		/**
		 * Filter the published post count above which scheduled scans skip
		 * the broken links check.
		 *
		 * @param int $threshold Published post count.
		 */
		$threshold = (int) apply_filters( 'my_site_hand_health_cron_skip_links_threshold', self::LINK_SKIP_THRESHOLD );

		$counts = wp_count_posts( 'post' );
		$pages  = wp_count_posts( 'page' );

		$published = (int) ( $counts->publish ?? 0 ) + (int) ( $pages->publish ?? 0 );

		return $published > $threshold;
	}

	/**
	 * Send the report email, unless there is nothing worth saying.
	 *
	 * @param array<string, mixed> $report   Finished report.
	 * @param array<string, mixed> $previous Previous history summary.
	 * @return bool Whether an email was sent.
	 */
	public function maybe_send_report( array $report, array $previous ): bool {
		if ( ! $this->is_enabled() || 'never' === $this->get_frequency() ) {
			return false;
		}

		$new = $this->find_new_issues( $report, $previous );

		$score_changed = null !== $report['previous_score'] && (int) $report['previous_score'] !== (int) $report['score'];

		// An email with nothing to say is worse than no email.
		if ( empty( $new ) && ! $score_changed ) {
			return false;
		}

		$recipient = $this->get_recipient();

		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Your site report', 'my-site-hand' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$html = $this->render_email_html( $report, $new, $recipient );
		$text = $this->render_email_text( $report, $new, $recipient );

		$html_type = static fn(): string => 'text/html';
		$alt_body  = static function ( $phpmailer ) use ( $text ): void {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		};

		add_filter( 'wp_mail_content_type', $html_type );
		add_action( 'phpmailer_init', $alt_body );

		$sent = wp_mail( $recipient, $subject, $html );

		remove_action( 'phpmailer_init', $alt_body );
		remove_filter( 'wp_mail_content_type', $html_type );

		return (bool) $sent;
	}

	/**
	 * Work out what is new since the previous scan.
	 *
	 * A repeated identical list trains people to ignore the email, so only the
	 * increase per check is reported.
	 *
	 * @param array<string, mixed> $report   Finished report.
	 * @param array<string, mixed> $previous Previous history summary.
	 * @return array<string, array{label: string, severity: string, new: int, total: int}>
	 */
	public function find_new_issues( array $report, array $previous ): array {
		$new = [];

		foreach ( $report['checks'] as $id => $entry ) {
			if ( ! in_array( $entry['status'], [ 'done', 'running' ], true ) ) {
				continue;
			}

			$before = (int) ( $previous[ $id ]['issues'] ?? 0 );
			$after  = (int) $entry['issue_count'];
			$delta  = $after - $before;

			if ( $delta <= 0 ) {
				continue;
			}

			$new[ $id ] = [
				'label'    => (string) $entry['label'],
				'severity' => (string) $entry['severity'],
				'new'      => $delta,
				'total'    => $after,
			];
		}

		return $new;
	}

	/**
	 * Render the HTML email.
	 *
	 * Tables and inline styles on purpose: this has to survive Outlook.
	 *
	 * @param array<string, mixed> $report    Finished report.
	 * @param array<string, mixed> $new       New issues by check.
	 * @param string               $recipient Recipient address.
	 * @return string
	 */
	private function render_email_html( array $report, array $new, string $recipient ): string {
		$score    = (int) $report['score'];
		$band     = $this->scanner->get_score_band( $score );
		$colours  = [
			'good'     => '#0A7A4F',
			'warning'  => '#9A6300',
			'critical' => '#BE2434',
		];
		$colour   = $colours[ $band['band'] ] ?? '#0C1426';
		$page_url = admin_url( 'admin.php?page=my-site-hand-health' );

		$rows = '';

		foreach ( $new as $entry ) {
			$dot   = 'critical' === $entry['severity'] ? '#BE2434' : ( 'warning' === $entry['severity'] ? '#9A6300' : '#939AAB' );
			$rows .= sprintf(
				'<tr><td style="padding:6px 0;font-size:14px;color:#0C1426;"><span style="display:inline-block;width:8px;height:8px;background:%1$s;margin-right:8px;"></span>%2$s</td></tr>',
				esc_attr( $dot ),
				esc_html(
					sprintf(
						/* translators: 1: number of new issues, 2: check name */
						_n( '%1$d new %2$s issue', '%1$d new %2$s issues', $entry['new'], 'my-site-hand' ),
						$entry['new'],
						$entry['label']
					)
				)
			);
		}

		if ( '' === $rows ) {
			$rows = sprintf(
				'<tr><td style="padding:6px 0;font-size:14px;color:#414A5C;">%s</td></tr>',
				esc_html__( 'No new problems since your last scan.', 'my-site-hand' )
			);
		}

		$since = null !== $report['previous_score']
			? sprintf(
				/* translators: %d: previous score */
				esc_html__( 'up from %d last time', 'my-site-hand' ),
				(int) $report['previous_score']
			)
			: esc_html__( 'your first scan', 'my-site-hand' );

		if ( null !== $report['previous_score'] && (int) $report['previous_score'] > $score ) {
			$since = sprintf(
				/* translators: %d: previous score */
				esc_html__( 'down from %d last time', 'my-site-hand' ),
				(int) $report['previous_score']
			);
		}

		return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#F1F3F8;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F3F8;padding:24px 0;">'
			. '<tr><td align="center">'
			. '<table role="presentation" width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #D9DEE8;max-width:520px;width:100%;">'
			. '<tr><td style="padding:24px 24px 8px;">'
			. '<p style="margin:0 0 4px;font-size:12px;letter-spacing:0.04em;text-transform:uppercase;color:#939AAB;">' . esc_html( get_bloginfo( 'name' ) ) . '</p>'
			. '<h1 style="margin:0;font-size:20px;color:#0C1426;font-weight:600;">' . esc_html__( 'Your site report', 'my-site-hand' ) . '</h1>'
			. '</td></tr>'
			. '<tr><td style="padding:12px 24px;">'
			. '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
			. '<td style="font-size:34px;font-weight:600;color:' . esc_attr( $colour ) . ';padding-right:10px;">' . esc_html( (string) $score ) . '</td>'
			. '<td style="font-size:13px;color:#666E80;">' . esc_html__( 'out of 100', 'my-site-hand' ) . '<br>' . $since . '</td>'
			. '</tr></table>'
			. '</td></tr>'
			. '<tr><td style="padding:8px 24px 0;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>'
			. '</td></tr>'
			. '<tr><td style="padding:20px 24px 28px;">'
			. '<a href="' . esc_url( $page_url ) . '" style="display:inline-block;background:#1B4FD8;color:#ffffff;text-decoration:none;padding:10px 18px;font-size:14px;">'
			. esc_html__( 'View full report', 'my-site-hand' ) . '</a>'
			. '</td></tr>'
			. '<tr><td style="padding:14px 24px;border-top:1px solid #E7EAF1;font-size:11px;color:#939AAB;">'
			. esc_html__( 'You are getting this because scheduled site reports are on for this site.', 'my-site-hand' ) . ' '
			. '<a href="' . esc_url( $this->get_unsubscribe_url( $recipient ) ) . '" style="color:#666E80;">' . esc_html__( 'Turn these off', 'my-site-hand' ) . '</a>'
			. '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * Render the plain-text alternative.
	 *
	 * @param array<string, mixed> $report    Finished report.
	 * @param array<string, mixed> $new       New issues by check.
	 * @param string               $recipient Recipient address.
	 * @return string
	 */
	private function render_email_text( array $report, array $new, string $recipient ): string {
		$lines = [];

		$lines[] = get_bloginfo( 'name' );
		$lines[] = __( 'Your site report', 'my-site-hand' );
		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %d: health score out of 100 */
			__( 'Score: %d/100', 'my-site-hand' ),
			(int) $report['score']
		);

		if ( null !== $report['previous_score'] ) {
			$lines[] = sprintf(
				/* translators: %d: previous health score */
				__( 'Previously: %d', 'my-site-hand' ),
				(int) $report['previous_score']
			);
		}

		$lines[] = '';

		if ( empty( $new ) ) {
			$lines[] = __( 'No new problems since your last scan.', 'my-site-hand' );
		} else {
			foreach ( $new as $entry ) {
				$lines[] = '- ' . sprintf(
					/* translators: 1: number of new issues, 2: check name */
					_n( '%1$d new %2$s issue', '%1$d new %2$s issues', $entry['new'], 'my-site-hand' ),
					$entry['new'],
					$entry['label']
				);
			}
		}

		$lines[] = '';
		$lines[] = __( 'View the full report:', 'my-site-hand' );
		$lines[] = admin_url( 'admin.php?page=my-site-hand-health' );
		$lines[] = '';
		$lines[] = __( 'Turn these emails off:', 'my-site-hand' );
		$lines[] = $this->get_unsubscribe_url( $recipient );

		return implode( "\n", $lines );
	}

	/**
	 * Build a signed unsubscribe URL that works without being logged in.
	 *
	 * @param string $recipient Recipient address.
	 * @return string
	 */
	public function get_unsubscribe_url( string $recipient ): string {
		return add_query_arg(
			[
				'action' => 'my_site_hand_report_unsubscribe',
				'token'  => $this->unsubscribe_token( $recipient ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Token proving the unsubscribe link came from an email we sent.
	 *
	 * @param string $recipient Recipient address.
	 * @return string
	 */
	private function unsubscribe_token( string $recipient ): string {
		return hash_hmac( 'sha256', 'my-site-hand-report-unsubscribe|' . strtolower( $recipient ), wp_salt( 'auth' ) );
	}

	/**
	 * Handle the unsubscribe link.
	 *
	 * @return void
	 */
	public function handle_unsubscribe(): void {
		// An unsubscribe link lives in an email that may be opened days later by
		// someone who is not logged in, so a nonce cannot work here. The link is
		// authenticated instead by the HMAC compared just below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authenticated by hash_equals() against unsubscribe_token().
		$token     = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$recipient = $this->get_recipient();

		if ( '' === $token || ! hash_equals( $this->unsubscribe_token( $recipient ), $token ) ) {
			wp_die(
				esc_html__( 'That unsubscribe link is not valid. You can turn reports off in My Site Hand settings.', 'my-site-hand' ),
				esc_html__( 'Link expired', 'my-site-hand' ),
				[ 'response' => 403 ]
			);
		}

		update_option( 'mysitehand_weekly_report_enabled', false );
		$this->sync_schedule();

		wp_die(
			esc_html__( 'Done. This site will not email you site reports again.', 'my-site-hand' ),
			esc_html__( 'Reports turned off', 'my-site-hand' ),
			[ 'response' => 200 ]
		);
	}

	/**
	 * Whether report emails are switched on.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) get_option( 'mysitehand_weekly_report_enabled', true );
	}

	/**
	 * Configured send frequency.
	 *
	 * @return string 'weekly' | 'monthly' | 'never'
	 */
	public function get_frequency(): string {
		$frequency = (string) get_option( 'mysitehand_report_frequency', 'weekly' );

		return in_array( $frequency, [ 'weekly', 'monthly', 'never' ], true ) ? $frequency : 'weekly';
	}

	/**
	 * Configured recipient.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		$recipient = (string) get_option( 'mysitehand_weekly_report_email', '' );

		return '' !== $recipient ? $recipient : (string) get_option( 'admin_email' );
	}

	/**
	 * Read the position of an in-progress scheduled scan.
	 *
	 * @return array{index: int, offset: int}
	 */
	private function get_progress(): array {
		$stored = get_option( self::PROGRESS_OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		return [
			'index'  => max( 0, (int) ( $stored['index'] ?? 0 ) ),
			'offset' => max( 0, (int) ( $stored['offset'] ?? 0 ) ),
		];
	}

	/**
	 * Record where a scheduled scan stopped.
	 *
	 * @param int $index  Index into the check list.
	 * @param int $offset Offset within that check.
	 * @return void
	 */
	private function save_progress( int $index, int $offset ): void {
		update_option(
			self::PROGRESS_OPTION,
			[
				'index'  => $index,
				'offset' => $offset,
			],
			false
		);
	}

	/**
	 * Forget any in-progress position.
	 *
	 * @return void
	 */
	private function clear_progress(): void {
		delete_option( self::PROGRESS_OPTION );
	}

	/**
	 * Queue the continuation of a scan that ran out of time.
	 *
	 * @return void
	 */
	private function schedule_continuation(): void {
		if ( ! wp_next_scheduled( self::CRON_CONTINUE ) ) {
			wp_schedule_single_event( time() + self::CONTINUE_DELAY, self::CRON_CONTINUE );
		}
	}
}
