<?php
/**
 * Surfaces plugin checks inside WordPress core's Tools -> Site Health screen.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Core Site Health integration.
 *
 * Puts a handful of results where users who never open this plugin's menu will
 * still see them.
 *
 * Deliberately FOUR tests and no more. Core's Site Health screen is shared with
 * every other plugin on the site; flooding it is hostile, and reviewers notice.
 *
 * The tests read the last stored report rather than scanning. Core's screen has
 * to stay fast, and a live scan there would be both slow and surprising — which
 * is also why these are direct tests rather than async ones: reading one option
 * is quicker than the AJAX round trip an async test would add.
 */
class Core_Site_Health {

	/**
	 * Checks surfaced in core, in display order.
	 *
	 * @var array<int, string>
	 */
	private const SURFACED = [
		'broken-links',
		'missing-alt-text',
		'missing-meta-description',
		'large-media',
	];

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
		add_filter( 'site_status_tests', [ $this, 'register_tests' ] );
	}

	/**
	 * Add our tests to core's list.
	 *
	 * @param array<string, array<string, mixed>> $tests Core's registered tests.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_tests( array $tests ): array {
		foreach ( self::SURFACED as $check_id ) {
			$check = $this->scanner->get_check( $check_id );

			if ( null === $check || ! $check->is_applicable() ) {
				continue;
			}

			$test_id = 'my_site_hand_' . str_replace( '-', '_', $check_id );

			$tests['direct'][ $test_id ] = [
				'label' => $check->get_label(),
				'test'  => function () use ( $check_id ): array {
					return $this->build_result( $check_id );
				},
			];
		}

		return $tests;
	}

	/**
	 * Build one core Site Health result from the stored report.
	 *
	 * @param string $check_id Check id.
	 * @return array<string, mixed>
	 */
	public function build_result( string $check_id ): array {
		$check   = $this->scanner->get_check( $check_id );
		$report  = $this->scanner->get_latest_report();
		$test_id = 'my_site_hand_' . str_replace( '-', '_', $check_id );

		$result = [
			'label'       => null !== $check ? $check->get_label() : $check_id,
			'status'      => 'recommended',
			'badge'       => [
				'label' => __( 'My Site Hand', 'my-site-hand' ),
				'color' => 'blue',
			],
			'description' => '',
			'actions'     => $this->action_link( __( 'Open Site Health', 'my-site-hand' ) ),
			'test'        => $test_id,
		];

		// Nothing scanned yet: say so plainly rather than claiming all is well.
		if ( null === $report || ! isset( $report['checks'][ $check_id ] ) ) {
			$result['badge']['color'] = 'gray';
			$result['description']    = $this->paragraph(
				__( 'My Site Hand has not scanned your site yet, so there is nothing to report here.', 'my-site-hand' )
			);
			$result['actions']        = $this->action_link( __( 'Run your first scan', 'my-site-hand' ) );

			return $result;
		}

		$entry  = $report['checks'][ $check_id ];
		$count  = (int) $entry['issue_count'];
		$status = (string) $entry['status'];

		if ( 'skipped' === $status ) {
			$result['badge']['color'] = 'gray';
			$result['description']    = $this->paragraph(
				__( 'This check does not apply to your site, so it was skipped.', 'my-site-hand' )
			);

			return $result;
		}

		if ( 'error' === $status ) {
			$result['description'] = $this->paragraph(
				__( 'This check could not finish the last time your site was scanned.', 'my-site-hand' )
			);

			return $result;
		}

		if ( 0 === $count ) {
			$result['status']      = 'good';
			$result['label']       = $this->good_label( $check_id );
			$result['description'] = $this->paragraph( $this->good_description( $check_id ) );

			return $result;
		}

		$result['status']      = 'critical' === $entry['severity'] ? 'critical' : 'recommended';
		$result['label']       = $this->problem_label( $check_id, $count );
		$result['description'] = $this->paragraph( $this->problem_description( $check_id, $count ) );
		$result['actions']     = $this->action_link( __( 'Fix these in My Site Hand', 'my-site-hand' ) );

		return $result;
	}

	/**
	 * Headline when a check found nothing.
	 *
	 * @param string $check_id Check id.
	 * @return string
	 */
	private function good_label( string $check_id ): string {
		switch ( $check_id ) {
			case 'broken-links':
				return __( 'Your links all work', 'my-site-hand' );
			case 'missing-alt-text':
				return __( 'Your images all have alt text', 'my-site-hand' );
			case 'missing-meta-description':
				return __( 'Your pages all have meta descriptions', 'my-site-hand' );
			case 'large-media':
				return __( 'None of your files are oversized', 'my-site-hand' );
			default:
				return __( 'Nothing to fix here', 'my-site-hand' );
		}
	}

	/**
	 * One sentence describing a clean result.
	 *
	 * @param string $check_id Check id.
	 * @return string
	 */
	private function good_description( string $check_id ): string {
		switch ( $check_id ) {
			case 'broken-links':
				return __( 'Every link checked in your recent posts and pages resolved successfully.', 'my-site-hand' );
			case 'missing-alt-text':
				return __( 'Every image in your media library has alternative text, which screen readers and search engines both rely on.', 'my-site-hand' );
			case 'missing-meta-description':
				return __( 'Every published post and page has an SEO meta description.', 'my-site-hand' );
			case 'large-media':
				return __( 'No files in your media library are large enough to slow your pages down.', 'my-site-hand' );
			default:
				return __( 'This check found nothing to fix.', 'my-site-hand' );
		}
	}

	/**
	 * Headline when a check found problems.
	 *
	 * @param string $check_id Check id.
	 * @param int    $count    Issue count.
	 * @return string
	 */
	private function problem_label( string $check_id, int $count ): string {
		switch ( $check_id ) {
			case 'broken-links':
				return sprintf(
					/* translators: %d: number of broken links */
					_n( '%d broken link on your site', '%d broken links on your site', $count, 'my-site-hand' ),
					$count
				);
			case 'missing-alt-text':
				return sprintf(
					/* translators: %d: number of images */
					_n( '%d image is missing alt text', '%d images are missing alt text', $count, 'my-site-hand' ),
					$count
				);
			case 'missing-meta-description':
				return sprintf(
					/* translators: %d: number of posts and pages */
					_n( '%d page is missing a meta description', '%d pages are missing a meta description', $count, 'my-site-hand' ),
					$count
				);
			case 'large-media':
				return sprintf(
					/* translators: %d: number of files */
					_n( '%d oversized file in your media library', '%d oversized files in your media library', $count, 'my-site-hand' ),
					$count
				);
			default:
				return sprintf(
					/* translators: %d: number of issues */
					_n( '%d issue found', '%d issues found', $count, 'my-site-hand' ),
					$count
				);
		}
	}

	/**
	 * One sentence describing what was found.
	 *
	 * @param string $check_id Check id.
	 * @param int    $count    Issue count.
	 * @return string
	 */
	private function problem_description( string $check_id, int $count ): string {
		switch ( $check_id ) {
			case 'broken-links':
				return __( 'Visitors following these links land on a page that no longer exists. You can replace them one at a time from the Site Health screen.', 'my-site-hand' );
			case 'missing-alt-text':
				return __( 'Screen readers cannot describe these images, and search engines cannot read them. You can write descriptions inline from the Site Health screen.', 'my-site-hand' );
			case 'missing-meta-description':
				return __( 'Search engines will invent their own snippet for these pages. You can write descriptions inline from the Site Health screen.', 'my-site-hand' );
			case 'large-media':
				return __( 'These files are large enough to slow down the pages that use them, and they take up storage.', 'my-site-hand' );
			default:
				return __( 'Open the Site Health screen for the details.', 'my-site-hand' );
		}
	}

	/**
	 * Wrap a sentence for core's description slot.
	 *
	 * @param string $text Sentence.
	 * @return string
	 */
	private function paragraph( string $text ): string {
		return '<p>' . esc_html( $text ) . '</p>';
	}

	/**
	 * Build the action link into this plugin's Site Health page.
	 *
	 * @param string $label Link text.
	 * @return string
	 */
	private function action_link( string $label ): string {
		return sprintf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=my-site-hand-health' ) ),
			esc_html( $label )
		);
	}
}
