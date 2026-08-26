<?php
/**
 * Site Health score trend.
 *
 * Expects $my_site_hand_trend to be an array of history rows (newest first),
 * as returned by Site_Health_Scanner::get_history().
 *
 * Inline SVG, no charting library: this is eight numbers, not a dashboard.
 *
 * @package MySiteHand
 */

defined('ABSPATH') || exit;

$my_site_hand_trend = isset($my_site_hand_trend) && is_array($my_site_hand_trend) ? $my_site_hand_trend : [];

// Oldest first for reading left to right.
$my_site_hand_trend_scores = array_reverse(array_map(
	static fn(array $row): int => (int) $row['score'],
	$my_site_hand_trend
));

// One data point is not a trend.
if (count($my_site_hand_trend_scores) < 2):
	return;
endif;

$my_site_hand_trend_first = $my_site_hand_trend_scores[0];
$my_site_hand_trend_last = $my_site_hand_trend_scores[count($my_site_hand_trend_scores) - 1];
$my_site_hand_trend_delta = $my_site_hand_trend_last - $my_site_hand_trend_first;

$my_site_hand_trend_dir = 0 === $my_site_hand_trend_delta ? 'flat' : ($my_site_hand_trend_delta > 0 ? 'up' : 'down');

// Map scores onto a 0-100 x 0-24 viewbox.
$my_site_hand_trend_w = 100;
$my_site_hand_trend_h = 24;
$my_site_hand_trend_n = count($my_site_hand_trend_scores);
$my_site_hand_trend_step = $my_site_hand_trend_n > 1 ? $my_site_hand_trend_w / ($my_site_hand_trend_n - 1) : 0;

$my_site_hand_trend_points = [];
foreach ($my_site_hand_trend_scores as $my_site_hand_i => $my_site_hand_s) {
	$my_site_hand_trend_points[] = sprintf(
		'%.1f,%.1f',
		$my_site_hand_i * $my_site_hand_trend_step,
		$my_site_hand_trend_h - (max(0, min(100, $my_site_hand_s)) / 100) * $my_site_hand_trend_h
	);
}
$my_site_hand_trend_path = implode(' ', $my_site_hand_trend_points);

// Show at most the last three numbers in the caption; the line carries the rest.
$my_site_hand_trend_caption = array_slice($my_site_hand_trend_scores, -3);
?>
<div class="msh-trend msh-trend--<?php echo esc_attr($my_site_hand_trend_dir); ?>">
	<svg class="msh-trend-spark" viewBox="0 0 <?php echo esc_attr((string) $my_site_hand_trend_w); ?> <?php echo esc_attr((string) $my_site_hand_trend_h); ?>"
		preserveAspectRatio="none" role="img" aria-label="<?php
		echo esc_attr(
			sprintf(
				/* translators: %s: comma separated list of recent scores */
				__('Recent scores: %s', 'my-site-hand'),
				implode(', ', $my_site_hand_trend_scores)
			)
		);
		?>">
		<polyline points="<?php echo esc_attr($my_site_hand_trend_path); ?>" fill="none" stroke="currentColor"
			stroke-width="1.5" vector-effect="non-scaling-stroke" />
	</svg>

	<span class="msh-trend-nums"><?php echo esc_html(implode(' → ', $my_site_hand_trend_caption)); ?></span>

	<?php if ('flat' !== $my_site_hand_trend_dir): ?>
		<span class="msh-trend-delta">
			<?php
			printf(
				/* translators: %s: signed score change across the trend window, e.g. "+16" */
				esc_html__('%s over your last few scans', 'my-site-hand'),
				esc_html(($my_site_hand_trend_delta > 0 ? '+' : '') . $my_site_hand_trend_delta)
			);
			?>
		</span>
	<?php endif; ?>
</div>
