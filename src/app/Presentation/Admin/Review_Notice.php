<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;
use Uncanny_Automator\Automator_Load;
use Uncanny_Automator\Automator_Review;

/**
 * The review banner. On Automator's pages, not the recipe editor, once a
 * Lite site has used 20 app credits, or any site has sent 30 emails or
 * completed 30 recipes ({@see Automator_Review::get_review_banner_config()}),
 * it asks whether Automator is useful. "I love it" then asks for a
 * WordPress.org review, "Not really..." for feedback: each answer is a link
 * through {@see Automator_Review::handle_feedback()}, which records it in
 * `_uncanny_automator_review_reminder` and returns to the page, where the
 * next step shows. "Maybe later" waits 10 days; the X, the review, the
 * feedback, "I already did" and "No, thanks" put it away for good. Every
 * step is tracked as the banner always was. It never shows while a credits
 * notice is due.
 *
 * @since 8.0.0
 */
final class Review_Notice extends Notice {

	/**
	 * The answer to "Are you finding Automator useful?": I love it.
	 */
	public const ANSWER_LOVE = 'love';

	/**
	 * The answer to "Are you finding Automator useful?": not really.
	 */
	public const ANSWER_DONT_LOVE = 'dont-love';

	/**
	 * Where the answer, "maybe-later" or "hide-forever" is kept.
	 */
	private const REMINDER = '_uncanny_automator_review_reminder';

	/**
	 * When it was last written.
	 */
	private const REMINDER_DATE = '_uncanny_automator_review_reminder_date';

	/**
	 * @var callable(): (Automator_Review|null)
	 */
	private $review;

	/**
	 * The banner and step drawn on this request, for shown().
	 *
	 * @var array{banner: string, step: string}|null
	 */
	private $drawn = null;

	/**
	 * @param callable|null $review Returns the review banner's state and URLs; the running instance by default.
	 */
	public function __construct( ?callable $review = null ) {

		$this->review = $review ?? static function () {
			$review = Automator_Load::get_core_class_instance( 'Automator_Review' );

			return $review instanceof Automator_Review ? $review : null;
		};
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'review';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return automator_get_capability();
	}

	/**
	 * @return string
	 */
	public function placement(): string {

		return self::AUTOMATOR_SCREENS;
	}

	/**
	 * Its X puts it away for good, for the site.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::per_site();
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		$review = ( $this->review )();
		if ( null === $review || ! $this->is_review_screen( $screen ) || ( new App_Credits_Notice() )->is_due() || $this->is_put_away( $review ) ) {
			return null;
		}

		$config = $review->get_review_banner_config();
		if ( empty( $config ) ) {
			return null;
		}

		$answer      = (string) automator_get_option( self::REMINDER, '' );
		$this->drawn = array(
			'banner' => (string) $config['banner'],
			'step'   => in_array( $answer, array( self::ANSWER_LOVE, self::ANSWER_DONT_LOVE ), true ) ? $answer : 'asked',
		);

		if ( self::ANSWER_LOVE === $answer ) {
			return $this->loved( $review, $config['banner'] );
		}

		if ( self::ANSWER_DONT_LOVE === $answer ) {
			return $this->not_loved( $review, $config['banner'] );
		}

		return $this->asked( $review, $config );
	}

	/**
	 * Asked after credits, emails or recipes; then each answer's step.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		$review = ( $this->review )();
		if ( null === $review ) {
			return array();
		}

		return array(
			$this->asked(
				$review,
				array(
					'banner'       => 'review-credits-used',
					'credits_used' => 20,
				)
			),
			$this->asked(
				$review,
				array(
					'banner'      => 'review-emails-sent',
					'emails_sent' => 30,
				)
			),
			$this->asked(
				$review,
				array(
					'banner'                        => 'review-recipes-count',
					'total_recipe_completion_count' => 30,
				)
			),
			$this->loved( $review, 'review-credits-used' ),
			$this->not_loved( $review, 'review-credits-used' ),
		);
	}

	/**
	 * Drawn: the question is tracked as displayed, as the banner always was.
	 *
	 * @return void
	 */
	public function shown(): void {

		$review = ( $this->review )();
		if ( null !== $review && null !== $this->drawn && 'asked' === $this->drawn['step'] ) {
			$review->track_review_step( $this->drawn['banner'], 'displayed' );
		}
	}

	/**
	 * Dismissed by its X: put away for good, and tracked as the banner's X
	 * was.
	 *
	 * @return void
	 */
	public function dismissed(): void {

		$answer = (string) automator_get_option( self::REMINDER, '' );

		automator_update_option( self::REMINDER, 'hide-forever' );
		automator_update_option( self::REMINDER_DATE, time() );

		$review = ( $this->review )();
		$config = null !== $review ? $review->get_review_banner_config() : array();
		if ( ! empty( $config ) ) {
			$review->track_review_step( $config['banner'], in_array( $answer, array( self::ANSWER_LOVE, self::ANSWER_DONT_LOVE ), true ) ? 'second-dismissed' : 'first-dismissed' );
		}
	}

	/**
	 * The question, after the milestone the site reached.
	 *
	 * @param Automator_Review     $review The banner's URLs.
	 * @param array<string, mixed> $config Its banner and count.
	 *
	 * @return Notice_Message
	 */
	private function asked( Automator_Review $review, array $config ): Notice_Message {

		$banner = (string) $config['banner'];

		if ( 'review-emails-sent' === $banner ) {
			/* translators: 1: Number of emails sent */
			$heading  = $this->bold( sprintf( esc_html_x( "You've been busy. Uncanny Automator has already **sent %1\$s emails**!", 'Reviews banner', 'uncanny-automator' ), absint( $config['emails_sent'] ) ) );
			$question = esc_html_x( 'Are you finding Automator useful?', 'Reviews banner', 'uncanny-automator' );
		} elseif ( 'review-recipes-count' === $banner ) {
			/* translators: 1: Number of recipes completed */
			$heading  = $this->bold( sprintf( esc_html_x( "Uncanny Automator has successfully **completed %1\$s recipes**. That's really cool!", 'Reviews banner', 'uncanny-automator' ), absint( $config['total_recipe_completion_count'] ) ) );
			$question = esc_html_x( 'Are you enjoying Uncanny Automator?', 'Reviews banner', 'uncanny-automator' );
		} else {
			$heading = esc_html_x( 'Great work connecting Uncanny Automator to other apps!', 'Reviews banner', 'uncanny-automator' );
			/* translators: 1: Number of credits used, 2: Emoji */
			$question = sprintf( esc_html_x( 'You just used your first %1$s free app credits %2$s with Uncanny Automator. Are you finding Automator useful?', 'Reviews banner', 'uncanny-automator' ), absint( $config['credits_used'] ), '🙌' );
		}

		return Notice_Message::info( $heading )
			->with_state( $banner )
			->with_mascot()
			->with_paragraph( $question )
			->with_action(
				Notice_Action::link(
					esc_html_x( 'I love it', 'Reviews banner', 'uncanny-automator' ) . ' 😍',
					$review->get_banner_url(
						self::ANSWER_LOVE,
						array(
							'banner' => $banner,
							'track'  => 'first-positive',
						)
					)
				)->primary()
			)
			->with_action(
				Notice_Action::link(
					esc_html_x( 'Not really...', 'Reviews banner', 'uncanny-automator' ),
					$review->get_banner_url(
						self::ANSWER_DONT_LOVE,
						array(
							'banner' => $banner,
							'track'  => 'first-negative',
						)
					)
				)
			);
	}

	/**
	 * "I love it": a review on WordPress.org, later, or already done.
	 *
	 * @param Automator_Review $review The banner's URLs.
	 * @param string           $banner The milestone banner.
	 *
	 * @return Notice_Message
	 */
	private function loved( Automator_Review $review, string $banner ): Notice_Message {

		$urls = $review->get_common_vars( $banner );

		return Notice_Message::info( esc_html_x( 'Fantastic!', 'Reviews banner', 'uncanny-automator' ) . ' 🎉' )
			->with_state( 'review-love' )
			->with_paragraph( esc_html_x( 'If you can spare 2 minutes, it would mean a lot to our team if you could leave us a review on WordPress.org. Please also let us know about any feature requests in your review; we always read them!', 'Reviews banner', 'uncanny-automator' ) )
			->with_action( Notice_Action::link( esc_html_x( 'OK, you deserve it!', 'Reviews banner', 'uncanny-automator' ), add_query_arg( 'track', 'second-review', $urls['url_wordpress'] ) )->primary()->in_new_tab() )
			->with_action( Notice_Action::link( esc_html_x( 'Maybe later', 'Reviews banner', 'uncanny-automator' ), add_query_arg( 'track', 'second-later', $urls['url_maybe_later'] ) ) )
			->with_action( Notice_Action::link( esc_html_x( 'I already did', 'Reviews banner', 'uncanny-automator' ), add_query_arg( 'track', 'second-done', $urls['url_already_did'] ) ) );
	}

	/**
	 * "Not really...": feedback, or no thanks.
	 *
	 * @param Automator_Review $review The banner's URLs.
	 * @param string           $banner The milestone banner.
	 *
	 * @return Notice_Message
	 */
	private function not_loved( Automator_Review $review, string $banner ): Notice_Message {

		$urls = $review->get_common_vars( $banner );

		return Notice_Message::info( esc_html_x( "We're sorry to hear that you're not finding Uncanny Automator useful.", 'Reviews banner', 'uncanny-automator' ) )
			->with_state( 'review-dont-love' )
			->with_paragraph( esc_html_x( 'We would love a chance to make it a better fit for you. Could you take a minute and let us know what we can do better?', 'Reviews banner', 'uncanny-automator' ) )
			->with_action( Notice_Action::link( esc_html_x( 'Feedback', 'Reviews banner', 'uncanny-automator' ), add_query_arg( 'track', 'second-feedback', $urls['url_feedback'] ) )->primary()->in_new_tab() )
			->with_action( Notice_Action::link( esc_html_x( 'No, thanks', 'Reviews banner', 'uncanny-automator' ), add_query_arg( 'track', 'second-no', $urls['url_close_button'] ) ) );
	}

	/**
	 * Put away for good, or for the "Maybe later" days.
	 *
	 * @param Automator_Review $review The banner's state.
	 *
	 * @return bool
	 */
	private function is_put_away( Automator_Review $review ): bool {

		return $review->is_banner_hidden_forever()
			|| ( $review->is_banner_hidden_temporarily() && $review->get_banner_hidden_days() <= Automator_Review::REVIEW_BANNER_TMP_NUM_DAYS );
	}

	/**
	 * Where the banner always showed: Automator's lists and pages, never
	 * the recipe editor.
	 *
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return bool
	 */
	private function is_review_screen( Notice_Screen $screen ): bool {

		$id = $screen->id();

		return 'edit-' . AUTOMATOR_POST_TYPE_RECIPE === $id
			|| 0 === strpos( $id, AUTOMATOR_POST_TYPE_RECIPE . '_page_' )
			|| in_array( $id, array( 'edit-recipe_category', 'edit-recipe_tag' ), true );
	}

	/**
	 * The banner's **bold** marks, as the strong text a notice heading
	 * allows.
	 *
	 * @param string $text Escaped text.
	 *
	 * @return string
	 */
	private function bold( string $text ): string {

		return (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
	}
}
