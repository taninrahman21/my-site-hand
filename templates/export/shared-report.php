<?php
/**
 * Public, tokenized Site Health report.
 *
 * Served to anyone holding the link, logged in or not. It renders ONLY the
 * redacted payload built by Shared_Reports::build_payload() — it never sees
 * the stored report, has no access to one, and must never be given one. If a
 * value is not in the payload it cannot appear on this page, which is the
 * entire point of building that payload separately.
 *
 * No admin stylesheet, no admin script, no nonce, no plugin assets.
 *
 * Expects:
 *   $my_site_hand_payload array The redacted snapshot.
 *   $my_site_hand_expires string Expiry, as a formatted local date.
 *
 * @package MySiteHand
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Commenting.DocComment.MissingShort -- Type hints for the caller's locals, not documented blocks.
/** @var array<string, mixed> $my_site_hand_payload The redacted snapshot. */
/** @var string $my_site_hand_expires Expiry, formatted for display. */
// phpcs:enable Generic.Commenting.DocComment.MissingShort

$my_site_hand_delta = isset( $my_site_hand_payload['previous_score'] ) && null !== $my_site_hand_payload['previous_score']
	? (int) $my_site_hand_payload['score'] - (int) $my_site_hand_payload['previous_score']
	: null;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="referrer" content="no-referrer">
	<title><?php
		printf(
			/* translators: %s: site name */
			esc_html__( 'Site Health Report — %s', 'my-site-hand' ),
			esc_html( (string) $my_site_hand_payload['site_name'] )
		);
	?></title>
	<style>
		:root {
			--msh-ink: #1c1c1c;
			--msh-muted: #5f5f5f;
			--msh-rule: #e2e2e2;
			--msh-panel: #fafafa;
			--msh-good: #17734a;
			--msh-warning: #8a5a00;
			--msh-critical: #a11b1b;
		}

		* { box-sizing: border-box; }

		body {
			margin: 0;
			padding: 40px 20px 64px;
			background: #f4f4f4;
			color: var(--msh-ink);
			font: 14px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
		}

		.msh-card {
			max-width: 680px;
			margin: 0 auto;
			background: #fff;
			border: 1px solid var(--msh-rule);
			padding: 28px 26px;
		}

		.msh-site-name { margin: 0; font-size: 20px; line-height: 1.3; word-break: break-word; }
		.msh-scanned { margin: 4px 0 0; font-size: 12px; color: var(--msh-muted); }

		.msh-score {
			display: flex;
			align-items: baseline;
			gap: 12px;
			margin: 24px 0 4px;
			padding: 18px 0;
			border-top: 1px solid var(--msh-rule);
			border-bottom: 1px solid var(--msh-rule);
			flex-wrap: wrap;
		}

		.msh-score-num { font-size: 44px; line-height: 1; font-weight: 700; }
		.msh-score-of { font-size: 15px; color: var(--msh-muted); }
		.msh-score-band { font-size: 14px; font-weight: 600; }
		.msh-score-delta { font-size: 12px; color: var(--msh-muted); }

		.msh-band-good { color: var(--msh-good); }
		.msh-band-warning { color: var(--msh-warning); }
		.msh-band-critical { color: var(--msh-critical); }

		.msh-checks { width: 100%; border-collapse: collapse; margin-top: 18px; }
		.msh-checks th,
		.msh-checks td { text-align: left; padding: 9px 6px; border-bottom: 1px solid var(--msh-rule); }
		.msh-checks th { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--msh-muted); font-weight: 600; }
		.msh-checks td:last-child,
		.msh-checks th:last-child { text-align: right; white-space: nowrap; }
		.msh-checks tr:last-child td { border-bottom: 0; }

		.msh-count { font-variant-numeric: tabular-nums; font-weight: 600; }
		.msh-count--clean { color: var(--msh-good); font-weight: 400; }
		.msh-count--muted { color: var(--msh-muted); font-weight: 400; font-size: 13px; }

		.msh-note {
			margin: 22px 0 0;
			padding: 14px 16px;
			background: var(--msh-panel);
			border: 1px solid var(--msh-rule);
			font-size: 12.5px;
			color: var(--msh-muted);
		}

		.msh-note p { margin: 0; }
		.msh-note p + p { margin-top: 8px; }

		.msh-foot {
			max-width: 680px;
			margin: 16px auto 0;
			font-size: 12px;
			color: var(--msh-muted);
			text-align: center;
		}

		.msh-foot a { color: inherit; }

		@media (max-width: 480px) {
			body { padding: 20px 12px 40px; }
			.msh-card { padding: 20px 16px; }
		}
	</style>
</head>
<body>

	<main class="msh-card">
		<h1 class="msh-site-name"><?php echo esc_html( (string) $my_site_hand_payload['site_name'] ); ?></h1>
		<p class="msh-scanned"><?php
			printf(
				/* translators: %s: date the scan ran */
				esc_html__( 'Site health scan of %s', 'my-site-hand' ),
				esc_html( wp_date( get_option( 'date_format' ), (int) $my_site_hand_payload['generated_at'] ) )
			);
		?></p>

		<div class="msh-score">
			<span class="msh-score-num msh-band-<?php echo esc_attr( (string) $my_site_hand_payload['band'] ); ?>">
				<?php echo esc_html( (string) $my_site_hand_payload['score'] ); ?>
			</span>
			<span class="msh-score-of"><?php esc_html_e( '/ 100', 'my-site-hand' ); ?></span>
			<span class="msh-score-band msh-band-<?php echo esc_attr( (string) $my_site_hand_payload['band'] ); ?>">
				<?php echo esc_html( (string) $my_site_hand_payload['band_label'] ); ?>
			</span>
			<?php if ( null !== $my_site_hand_delta ) : ?>
				<span class="msh-score-delta"><?php
					printf(
						/* translators: %s: signed score change such as "+6" */
						esc_html__( '%s since the previous scan', 'my-site-hand' ),
						esc_html( ( $my_site_hand_delta >= 0 ? '+' : '' ) . $my_site_hand_delta )
					);
				?></span>
			<?php endif; ?>
		</div>

		<table class="msh-checks">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Check', 'my-site-hand' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Issues found', 'my-site-hand' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( (array) $my_site_hand_payload['checks'] as $my_site_hand_check ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $my_site_hand_check['label'] ); ?></td>
						<td>
							<?php
							$my_site_hand_status = (string) $my_site_hand_check['status'];
							$my_site_hand_count  = (int) $my_site_hand_check['issue_count'];

							if ( 'skipped' === $my_site_hand_status ) {
								echo '<span class="msh-count msh-count--muted">' . esc_html__( 'Not applicable', 'my-site-hand' ) . '</span>';
							} elseif ( 'error' === $my_site_hand_status ) {
								echo '<span class="msh-count msh-count--muted">' . esc_html__( 'Could not run', 'my-site-hand' ) . '</span>';
							} elseif ( 0 === $my_site_hand_count ) {
								echo '<span class="msh-count msh-count--clean">' . esc_html__( 'Nothing found', 'my-site-hand' ) . '</span>';
							} else {
								printf(
									'<span class="msh-count msh-band-%1$s">%2$s</span>',
									esc_attr( (string) $my_site_hand_check['severity'] ),
									esc_html(
										sprintf(
											/* translators: %d: number of issues */
											_n( '%d issue', '%d issues', $my_site_hand_count, 'my-site-hand' ),
											$my_site_hand_count
										)
									)
								);
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="msh-note">
			<p><?php esc_html_e( 'This is a summary. It shows how many issues each check found and nothing about what they were — no file names, no page addresses, no plugin or version details.', 'my-site-hand' ); ?></p>
			<p><?php
				printf(
					/* translators: %s: date the link stops working */
					esc_html__( 'This link stops working on %s.', 'my-site-hand' ),
					esc_html( $my_site_hand_expires )
				);
			?></p>
		</div>
	</main>

	<p class="msh-foot">
		<?php esc_html_e( 'Generated by My Site Hand', 'my-site-hand' ); ?> —
		<a href="https://wordpress.org/plugins/my-site-hand/" rel="noopener noreferrer nofollow">wordpress.org/plugins/my-site-hand</a>
	</p>

</body>
</html>
