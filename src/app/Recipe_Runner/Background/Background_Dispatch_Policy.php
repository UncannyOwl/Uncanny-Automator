<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Background;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Capability_Discovery;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;

/**
 * Whether an integration's write may go to the platform as a background job right now:
 * the setting is on, the site is not local, and the platform's capability heartbeat reports the integration capable.
 * The action itself adds the structural conditions (it implements process_job(), it is not in loop scope).
 *
 * A local site never receives the job's completion: the Automator App Platform delivers nothing to a site it
 * registered as local ({@see Local_Site_Status}). A background write there would settle only when the
 * fifteen-minute poll asks for it, so a local site's writes stay synchronous, whatever the setting says.
 */
final class Background_Dispatch_Policy {

	/**
	 * Hours after dispatch at which a job nobody reported on is declared lost.
	 *
	 * @var int
	 */
	const LOST_AFTER_HOURS = 24;

	/**
	 * How many times the poll may re-drive a job whose completion died before the job is given up as lost.
	 *
	 * @var int
	 */
	const MAX_REDRIVES = 3;

	/**
	 * Hours a run's snapshot must outlive the lost cap, so the poll that settles a lost job still finds its run context.
	 *
	 * @var int
	 */
	const SNAPSHOT_BUFFER_HOURS = 12;

	/**
	 * The platform's per-integration capability matrix.
	 *
	 * @var Capability_Discovery
	 */
	private Capability_Discovery $capabilities;

	/**
	 * Whether the platform registered this site as local.
	 *
	 * @var Local_Site_Status
	 */
	private Local_Site_Status $local;

	/**
	 * Bind to the capability reader and the local-site record.
	 *
	 * @param Capability_Discovery|null $capabilities Defaults to the shared reader.
	 * @param Local_Site_Status|null    $local        Defaults to the stored record.
	 */
	public function __construct( ?Capability_Discovery $capabilities = null, ?Local_Site_Status $local = null ) {
		$this->capabilities = $capabilities ?? new Capability_Discovery();
		$this->local        = $local ?? new Local_Site_Status();
	}

	/**
	 * Whether this integration's write may be dispatched in the background.
	 *
	 * @param string $integration_code The platform integration the write goes to, e.g. `mailchimp`, or `facebook`
	 *                                 for Instagram; the action's integration code when it has no platform caller.
	 *
	 * @return bool
	 */
	public function allows( string $integration_code ): bool {
		return Background_Actions_Setting::enabled()
			&& ! $this->local->is_local()
			&& $this->capabilities->is_async_supported( strtolower( $integration_code ) );
	}

	/**
	 * Seconds after dispatch at which an open job is declared lost.
	 *
	 * @return int
	 */
	public function lost_after_seconds(): int {
		return self::LOST_AFTER_HOURS * HOUR_IN_SECONDS;
	}

	/**
	 * How long a background job keeps its run's snapshot alive from dispatch: the lost cap plus the buffer.
	 *
	 * @return int Seconds.
	 */
	public function snapshot_keep_seconds(): int {
		return ( self::LOST_AFTER_HOURS + self::SNAPSHOT_BUFFER_HOURS ) * HOUR_IN_SECONDS;
	}
}
