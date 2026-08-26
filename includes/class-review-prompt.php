<?php
/**
 * WordPress.org review prompt.
 *
 * @package MySiteHand
 */

namespace MySiteHand;

defined( 'ABSPATH' ) || exit;

/**
 * Review Prompt class.
 *
 * Asks for a review at the one moment the user has just received value: they
 * ran a scan and repaired real problems on their own site. Never on a timer,
 * never on a day count. A generic "enjoying the plugin?" nag on day seven is
 * the version that earns one-star reviews instead of five-star ones.
 */
class Review_Prompt {

	/**
	 * User meta holding this person's prompt state.
	 *
	 * Per user, not per site: one admin dismissing it must not silence it for
	 * a colleague, and one admin must not be nagged because a colleague never
	 * answered.
	 *
	 * @var string
	 */
	private const STATE_META = 'mysitehand_review_state';

	/**
	 * Repairs required before the prompt is allowed to appear.
	 *
	 * @var int
	 */
	private const REQUIRED_FIXES = 3;

	/**
	 * Hard ceiling on how many times one person is ever asked.
	 *
	 * @var int
	 */
	private const MAX_SHOWINGS = 2;

	/**
	 * How long "Maybe later" holds the prompt back.
	 *
	 * @var int
	 */
	private const SNOOZE = 30 * DAY_IN_SECONDS;

	/**
	 * Minimum gap between two showings, so it cannot follow the user around.
	 *
	 * @var int
	 */
	private const MIN_GAP = DAY_IN_SECONDS;

	/**
	 * Where the review is left.
	 *
	 * @var string
	 */
	private const REVIEW_URL = 'https://wordpress.org/support/plugin/my-site-hand/reviews/#new-post';

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
		add_action( 'admin_notices', [ $this, 'maybe_render' ] );
		add_action( 'wp_ajax_my_site_hand_review_action', [ $this, 'ajax_action' ] );
	}

	/**
	 * Whether this user should be asked right now.
	 *
	 * @return bool
	 */
	public function should_show(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Only on this plugin's own screens: other people's admin pages are
		// not the place for our advertising.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $screen || ! str_contains( (string) $screen->id, 'my-site-hand' ) ) {
			return false;
		}

		// The message names something the user did. It is only true because
		// Quick Fix exists, and only if they actually used it.
		if ( $this->scanner->get_fix_count() < self::REQUIRED_FIXES ) {
			return false;
		}

		if ( null === $this->scanner->get_latest_report() ) {
			return false;
		}

		$state = $this->get_state();

		if ( $state['never'] ) {
			return false;
		}

		if ( $state['count'] >= self::MAX_SHOWINGS ) {
			return false;
		}

		if ( $state['snooze_until'] > time() ) {
			return false;
		}

		if ( $state['last_shown'] > 0 && ( time() - $state['last_shown'] ) < self::MIN_GAP ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the prompt when the moment is right.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		$state               = $this->get_state();
		$state['count']      = $state['count'] + 1;
		$state['last_shown'] = time();
		$this->save_state( $state );

		$fixes = $this->scanner->get_fix_count();
		$nonce = wp_create_nonce( 'my_site_hand_review' );
		?>
		<div class="notice notice-success is-dismissible msh-review-notice" id="msh-review-notice"
			data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong>
				<?php
				printf(
					/* translators: %d: number of issues the user repaired */
					esc_html( _n( 'You fixed %d issue on your site.', 'You fixed %d issues on your site.', $fixes, 'my-site-hand' ) ),
					(int) $fixes
				);
				?>
				</strong>
				<?php esc_html_e( 'If My Site Hand is useful, a review would help a lot.', 'my-site-hand' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( self::REVIEW_URL ); ?>" class="button button-primary" target="_blank"
					rel="noopener noreferrer" onclick="mshReview.act('never')">
					<?php esc_html_e( 'Leave a review', 'my-site-hand' ); ?>
				</a>
				<button type="button" class="button" onclick="mshReview.act('later')">
					<?php esc_html_e( 'Maybe later', 'my-site-hand' ); ?>
				</button>
				<button type="button" class="button-link" onclick="mshReview.act('never')">
					<?php esc_html_e( 'Don\'t ask again', 'my-site-hand' ); ?>
				</button>
			</p>
		</div>
		<script>
			window.mshReview = {
				act: function (choice) {
					var notice = document.getElementById('msh-review-notice');

					if (notice) {
						notice.style.display = 'none';
					}

					var body = new URLSearchParams();
					body.append('action', 'my_site_hand_review_action');
					body.append('nonce', notice ? notice.getAttribute('data-nonce') : '');
					body.append('choice', choice);

					fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body.toString()
					});
				}
			};

			// The X on a dismissible notice only hides it. Make that stick.
			document.addEventListener('click', function (event) {
				var button = event.target.closest('#msh-review-notice .notice-dismiss');

				if (button) {
					window.mshReview.act('later');
				}
			});
		</script>
		<?php
	}

	/**
	 * AJAX: record the user's answer.
	 *
	 * @return void
	 */
	public function ajax_action(): void {
		check_ajax_referer( 'my_site_hand_review', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'my-site-hand' ) ], 403 );
		}

		$choice = sanitize_key( wp_unslash( $_POST['choice'] ?? '' ) );
		$state  = $this->get_state();

		if ( 'never' === $choice ) {
			$state['never'] = true;
		} else {
			$state['snooze_until'] = time() + self::SNOOZE;
		}

		$this->save_state( $state );

		wp_send_json_success( [ 'saved' => true ] );
	}

	/**
	 * Read this user's prompt state.
	 *
	 * @return array{count: int, last_shown: int, snooze_until: int, never: bool}
	 */
	private function get_state(): array {
		$stored = get_user_meta( get_current_user_id(), self::STATE_META, true );
		$stored = is_array( $stored ) ? $stored : [];

		return [
			'count'        => (int) ( $stored['count'] ?? 0 ),
			'last_shown'   => (int) ( $stored['last_shown'] ?? 0 ),
			'snooze_until' => (int) ( $stored['snooze_until'] ?? 0 ),
			'never'        => ! empty( $stored['never'] ),
		];
	}

	/**
	 * Persist this user's prompt state.
	 *
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	private function save_state( array $state ): void {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::STATE_META, $state );
		}
	}
}
