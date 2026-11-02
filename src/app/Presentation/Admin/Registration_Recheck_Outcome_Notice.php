<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Wire;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Severity;

/**
 * "Registration re-checked": where the registration stands after this user
 * pressed Re-check registration, shown once on the page the re-check sent
 * them back to. On the App Platform status tab the tab shows it in its own alert
 * (the settings page hides notices), so the notice leaves it there.
 *
 * @since 8.0.0
 */
final class Registration_Recheck_Outcome_Notice extends Notice {

	/**
	 * @var Registration_Recheck_Control
	 */
	private $recheck;

	/**
	 * @param Registration_Recheck_Control $recheck Holds the outcome.
	 */
	public function __construct( Registration_Recheck_Control $recheck ) {

		$this->recheck = $recheck;
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-registration-recheck';
	}

	/**
	 * Shown once; its X closes it.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::for_this_view();
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( App_Platform_Status_Tab::is_current_screen() ) {
			return null;
		}

		$outcome = $this->recheck->peek_outcome();
		if ( null === $outcome ) {
			return null;
		}

		return $this->rechecked( $outcome );
	}

	/**
	 * Registered, failed, still confirming.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array(
			$this->rechecked( Registration_Recheck_Control::outcome( Registration_Status::of( Registration_Status::REGISTERED ) ) ),
			$this->rechecked( Registration_Recheck_Control::outcome( Registration_Status::of( Registration_Status::UNREACHABLE, array( 'challenge_url' => ( new Registration_Wire() )->challenge_url() ) ) ) ),
			$this->rechecked( Registration_Recheck_Control::outcome( Registration_Status::of( Registration_Status::PENDING, array( 'since' => time() - 2 * MINUTE_IN_SECONDS ) ) ) ),
		);
	}

	/**
	 * @param array{type: string, message: string} $outcome The re-check's outcome.
	 *
	 * @return Notice_Message
	 */
	private function rechecked( array $outcome ): Notice_Message {

		$severity = Notice_Severity::is_valid( $outcome['type'] ) ? $outcome['type'] : Notice_Severity::INFO;

		return Notice_Message::create( $severity, esc_html_x( 'Registration re-checked', 'Admin notice', 'uncanny-automator' ) )
			->with_paragraph( $outcome['message'] );
	}

	/**
	 * Drawn: forget the outcome, so it is shown once.
	 *
	 * @return void
	 */
	public function shown(): void {

		$this->recheck->take_outcome();
	}
}
