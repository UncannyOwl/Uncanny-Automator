<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "This site's registration is being re-verified" notice (callback delivery
 * contract §6).
 *
 * Shown while a re-verification this site's callback receiver asked for is
 * pending: the receiver refused a delivery from the Automator platform — the
 * delivery that carries app triggers — because no signing secret this site
 * holds verified it, and asked {@see Platform_Site_Registration} to
 * re-verify the registration. Until that is done the platform's deliveries
 * keep being refused, so the admin is told why app triggers have stopped and
 * that they resume on their own.
 *
 * State is {@see Platform_Site_Registration::is_reverifying_for_refused_delivery()},
 * so the notice clears itself: when a delivery verifies again, and when the
 * registration completes. It says a re-verification is under way only while
 * one can run — the site is licensed and the platform plane enabled; one that
 * cannot run yet is still owed, and is told once it can. An install that never
 * registered is asked for no re-verification at all: the platform has no
 * callback URL for it, so a request it refused did not come from the platform.
 * A re-verification only the platform asked for (it rejected this site's own
 * requests, wire contract W4) is told on the step that failed instead. A
 * delivery refused for the clock is the clock notice's ({@see Clock_Skew_Notice}).
 *
 * @since 8.0.0
 */
final class Site_Reverification_Notice extends Notice {

	/**
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * @param Platform_Site_Registration $registration Owner of the re-verification.
	 */
	public function __construct( Platform_Site_Registration $registration ) {
		$this->registration = $registration;
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-reverification';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return 'manage_options';
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( ! $this->registration->is_reverifying_for_refused_delivery() ) {
			return null;
		}

		return $this->reverifying();
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->reverifying() );
	}

	/**
	 * @return Notice_Message
	 */
	private function reverifying(): Notice_Message {

		return Notice_Message::warning( esc_html_x( "Re-verifying this site's registration", 'Admin notice', 'uncanny-automator' ) )
			->with_paragraph( esc_html__( "This site's registration with the Automator App Platform is being re-verified, because this site could not verify a delivery from it. App triggers resume when it is done.", 'uncanny-automator' ) );
	}
}
