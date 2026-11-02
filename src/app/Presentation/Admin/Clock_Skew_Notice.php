<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Clock_Skew_Monitor;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Hmac_Validator;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "This server's clock is off" notice, shown after a signed exchange with the
 * platform was refused for the clock, in either direction: the platform
 * refused a site-signed request with `CLOCK_SKEW` (platform answers contract
 * 4.2), or this site refused a platform delivery — an app trigger — as
 * `timestamp_skew` (callback delivery contract §4).
 *
 * Names the difference {@see Clock_Skew_Monitor} measured, how far apart the
 * clocks may be before app actions sent to the platform and app triggers
 * delivered from it are refused, and that app connections need an accurate
 * server clock. Both are refused past 5 minutes
 * ({@see Hmac_Validator::SKEW_SECONDS}) unless this site tightened the
 * allowance it gives deliveries ({@see Hmac_Validator::allowance_seconds()});
 * then each allowance is named, so a clock refused at two minutes is not told
 * it is refused only past five. It clears itself: the monitor drops the
 * measurement once a signed exchange is accepted with the clock well inside
 * the allowance, and stops showing it a day after the clock was last refused.
 * Shown by the notice center; no dismiss.
 *
 * @since 8.0.0
 */
final class Clock_Skew_Notice extends Notice {

	/**
	 * @var Clock_Skew_Monitor
	 */
	private $monitor;

	/**
	 * @param Clock_Skew_Monitor|null $monitor The measurement's owner.
	 */
	public function __construct( ?Clock_Skew_Monitor $monitor = null ) {
		$this->monitor = $monitor ?? new Clock_Skew_Monitor();
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-clock-skew';
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

		$recorded = $this->monitor->recorded();
		if ( null === $recorded ) {
			return null;
		}

		return $this->off( $this->sentence( $recorded['seconds'] ) );
	}

	/**
	 * Unmeasured, ahead and behind; then ahead and behind while the
	 * platform's allowance for app triggers is tighter than 5 minutes.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array(
			$this->off( $this->sentence( null ) ),
			$this->off( $this->sentence( 600 ) ),
			$this->off( $this->sentence( -600 ) ),
			$this->off( $this->tightened_sentence( 120, esc_html( human_time_diff( 0, 120 ) ), 60 ) ),
			$this->off( $this->tightened_sentence( -120, esc_html( human_time_diff( 0, 120 ) ), 60 ) ),
		);
	}

	/**
	 * @param string $sentence What is off, escaped.
	 *
	 * @return Notice_Message
	 */
	private function off( string $sentence ): Notice_Message {

		return Notice_Message::error( esc_html_x( "This server's clock is off", 'Admin notice', 'uncanny-automator' ) )
			->with_paragraph( $sentence );
	}

	/**
	 * An unknown difference comes only from the platform refusing a request
	 * (no usable `Date`), which says the clocks are more than 5 minutes apart
	 * — past any allowance this site gives deliveries, so one sentence holds
	 * whatever that allowance is.
	 *
	 * @param int|null $seconds Seconds this server is ahead (negative: behind); null when unknown.
	 *
	 * @return string The sentence, escaped for output.
	 */
	private function sentence( ?int $seconds ): string {

		if ( null === $seconds ) {
			return esc_html_x( "This server's clock is more than 5 minutes off the Automator App Platform's clock. App actions sent to it and app triggers delivered from it are refused while the two clocks are that far apart. App connections need an accurate server clock: ask your host to synchronize it.", 'Automator App Platform', 'uncanny-automator' );
		}

		$difference = esc_html( human_time_diff( 0, abs( $seconds ) ) );
		$allowance  = Hmac_Validator::allowance_seconds();

		if ( Hmac_Validator::SKEW_SECONDS > $allowance ) {
			return $this->tightened_sentence( $seconds, $difference, $allowance );
		}

		return 0 <= $seconds
			/* translators: %s: a duration, e.g. "10 minutes" */
			? sprintf( esc_html_x( "This server's clock is %s ahead of the Automator App Platform's clock. App actions sent to it and app triggers delivered from it are refused while the two clocks are more than 5 minutes apart. App connections need an accurate server clock: ask your host to synchronize it.", 'Automator App Platform', 'uncanny-automator' ), $difference )
			/* translators: %s: a duration, e.g. "10 minutes" */
			: sprintf( esc_html_x( "This server's clock is %s behind the Automator App Platform's clock. App actions sent to it and app triggers delivered from it are refused while the two clocks are more than 5 minutes apart. App connections need an accurate server clock: ask your host to synchronize it.", 'Automator App Platform', 'uncanny-automator' ), $difference );
	}

	/**
	 * The sentence for a site that tightened the allowance it gives deliveries:
	 * app triggers are refused past that allowance, app actions past the
	 * platform's 5 minutes. The allowance is named in exact seconds, never a
	 * rounded duration, because it is a limit.
	 *
	 * @param int    $seconds    Seconds this server is ahead (negative: behind).
	 * @param string $difference The measured difference as a duration, escaped.
	 * @param int    $allowance  The allowance this site gives deliveries, in seconds.
	 *
	 * @return string The sentence, escaped for output.
	 */
	private function tightened_sentence( int $seconds, string $difference, int $allowance ): string {

		$limit = esc_html( number_format_i18n( $allowance ) );

		return 0 <= $seconds
			/* translators: 1: a duration, e.g. "2 minutes"; 2: a number of seconds, e.g. "60" */
			? sprintf( esc_html( _nx( 'This server\'s clock is %1$s ahead of the Automator App Platform\'s clock. App triggers delivered from it are refused while the two clocks are more than %2$s second apart, and app actions sent to it while they are more than 5 minutes apart. App connections need an accurate server clock: ask your host to synchronize it.', 'This server\'s clock is %1$s ahead of the Automator App Platform\'s clock. App triggers delivered from it are refused while the two clocks are more than %2$s seconds apart, and app actions sent to it while they are more than 5 minutes apart. App connections need an accurate server clock: ask your host to synchronize it.', $allowance, 'Automator App Platform', 'uncanny-automator' ) ), $difference, $limit )
			/* translators: 1: a duration, e.g. "2 minutes"; 2: a number of seconds, e.g. "60" */
			: sprintf( esc_html( _nx( 'This server\'s clock is %1$s behind the Automator App Platform\'s clock. App triggers delivered from it are refused while the two clocks are more than %2$s second apart, and app actions sent to it while they are more than 5 minutes apart. App connections need an accurate server clock: ask your host to synchronize it.', 'This server\'s clock is %1$s behind the Automator App Platform\'s clock. App triggers delivered from it are refused while the two clocks are more than %2$s seconds apart, and app actions sent to it while they are more than 5 minutes apart. App connections need an accurate server clock: ask your host to synchronize it.', $allowance, 'Automator App Platform', 'uncanny-automator' ) ), $difference, $limit );
	}
}
