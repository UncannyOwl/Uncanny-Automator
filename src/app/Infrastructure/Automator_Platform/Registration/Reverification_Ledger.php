<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Clock_Skew_Monitor;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Rejection_Observer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Value_Objects\Signature_Rejection_Reason;

use function Uncanny_Automator\App\Infrastructure\automator_site_credentials_store;

/**
 * What a register is owed for although the site may hold a secret: the platform
 * rejected this install's credentials (wire contract W4), this site's callback
 * receiver refused a delivery no secret it holds verifies (callback delivery
 * contract section 6), or the platform superseded the last attempt (a 410 at
 * completion, W3).
 *
 * Each cause is kept apart and withdrawn only by what contradicts it. Owns the three
 * options that hold them.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Reverification_Ledger {

	/**
	 * The `error.type` with which the platform rejected this install's site
	 * credentials on another route (wire contract W4), held until a register
	 * settles it. Set by {@see request_reverification()}; cleared through
	 * {@see forget_reverification()} — by {@see Registration_Recorder::persist_registration()}, or
	 * when a dev site's registration is reset.
	 */
	private const OPTION_REVERIFY_REASON = 'automator_site_register_reverify_reason';

	/**
	 * The other cause of a re-verification: this site's callback receiver
	 * refused a platform delivery whose signature no secret it holds verifies
	 * (callback delivery contract §6). Holds the reason it recorded —
	 * {@see REFUSED_DELIVERY_REASON_PREFIX} and the receiver's rejection reason
	 * — until a register settles it ({@see forget_reverification()}) or a
	 * delivery verifies again ({@see forget_refused_delivery()}).
	 *
	 * Kept apart from {@see OPTION_REVERIFY_REASON} so that each cause is
	 * withdrawn only by what contradicts it: a delivery that verifies says the
	 * platform signs with a secret this site holds, which says nothing about
	 * the platform rejecting this site's own requests.
	 */
	private const OPTION_REFUSED_DELIVERY = 'automator_site_register_refused_delivery';

	/**
	 * How the reason a refused delivery records begins, so it says where it
	 * came from: `refused_delivery:signature_mismatch`.
	 */
	private const REFUSED_DELIVERY_REASON_PREFIX = 'refused_delivery:';

	/**
	 * When the platform last superseded this install's registration attempt
	 * (wire contract W3: a 410 at completion), held while no registration has
	 * succeeded since. A register is owed then, and a repeat waits the ten-minute
	 * window ({@see Completion_Poll::retire_superseded_attempt()}). Cleared only through
	 * {@see forget_reverification()} — where a registration succeeds
	 * ({@see Registration_Recorder::persist_registration()}), or when a dev site's registration is
	 * reset — and never by {@see Registration_Backoff::clear_backoff()}: every 202 calls that, which
	 * is exactly when this must survive.
	 */
	private const OPTION_SUPERSEDED_AT = 'automator_site_register_superseded_at';

	/**
	 * How long a re-verification owed only for a refused delivery waits after
	 * the last one: anyone who can post to a registered install can make a
	 * delivery fail to verify, so that cause alone asks for at most one
	 * register an hour, whatever the ten-minute window every re-verification
	 * keeps allows.
	 */
	public const REFUSED_DELIVERY_PACE_SECONDS = HOUR_IN_SECONDS;

	/**
	 * When the last re-verification owed only for a refused delivery was sent.
	 * Not cleared by the register that settles the cause, so the next
	 * unverifiable post cannot ask again at once; an administrator's Re-check
	 * and `composer platform:clear` forget it ({@see forget_refused_delivery_pace()}).
	 */
	private const OPTION_REFUSED_DELIVERY_REVERIFIED_AT = 'automator_site_register_refused_delivery_reverified_at';

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @param Site_Credentials_Store|null $store Says whether this install has registered; the shared store by default.
	 */
	public function __construct( ?Site_Credentials_Store $store = null ) {

		$this->store = $store ?? automator_site_credentials_store() ?? new Site_Credentials_Store();
	}

	/**
	 * Record that the platform does not accept this install's site credentials
	 * (wire contract W4): a response typed `SITE_NOT_REGISTERED`, or a
	 * site-signed request answered `401 VERIFICATION_ERROR`. The next
	 * registration tick re-registers ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::reverify()}). Called by
	 * {@see Site_Credentials_Rejection_Observer} for every platform-plane
	 * response, so only the first rejection writes.
	 *
	 * @since 8.0.0
	 *
	 * @param string $reason The platform's `error.type`.
	 *
	 * @return void
	 */
	public function request_reverification( string $reason ): void {

		if ( '' !== $this->platform_rejection() ) {
			return;
		}

		automator_update_option( self::OPTION_REVERIFY_REASON, $reason );
		automator_log( 'The platform did not accept this site\'s current credentials (' . $reason . '). The next registration check re-registers this site.', 'Platform_Site_Registration', false );
	}

	/**
	 * Record that this site's callback receiver refused a platform delivery
	 * whose signature no secret it holds verifies — `signature_mismatch` or
	 * `stale_fingerprint` (callback delivery contract §6). Holding a secret
	 * proves nothing then either, so the next registration tick re-registers
	 * ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::reverify()}), exactly as for a W4 rejection.
	 *
	 * Only for an install that has registered
	 * ({@see Site_Credentials_Store::has_registered()}), a secret it can no
	 * longer read included. The platform delivers only to a callback URL a
	 * registration gave it, so a refused delivery to an install that never
	 * registered did not come from the platform, and there is no registration
	 * to re-verify: its first registration stays {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::register_if_needed()}'s
	 * ordinary path.
	 *
	 * Idempotent like {@see request_reverification()}: the receiver calls this
	 * for every refused delivery, so only the first of a burst writes, and the
	 * reason it records says it came from a refused delivery. A delivery
	 * refused for its timestamp never reaches here — re-registering fixes no
	 * clock ({@see Clock_Skew_Monitor} owns that).
	 *
	 * Reachable by anyone who can post to the callback route of a registered
	 * install: an unverifiable request is all it takes. What that can cause is
	 * bounded: one option write per burst, and at most one register per ten-minute
	 * window ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::reverify()}), on a wp-admin page load or WP-Cron — never
	 * inside the poster's request. The next delivery that verifies withdraws
	 * it ({@see forget_refused_delivery()}), and so does the register.
	 *
	 * @since 8.0.0
	 *
	 * @param Signature_Rejection_Reason $reason Why the receiver refused the delivery.
	 *
	 * @return void
	 */
	public function request_reverification_for_refused_delivery( Signature_Rejection_Reason $reason ): void {

		if ( '' !== $this->refused_delivery() || ! $this->store->has_registered() ) {
			return;
		}

		$recorded = self::REFUSED_DELIVERY_REASON_PREFIX . $reason->value();

		automator_update_option( self::OPTION_REFUSED_DELIVERY, $recorded, false );
		automator_log( 'This site refused a delivery from the Automator platform (' . $recorded . '): its signature did not verify with this site\'s signing secret. The next registration check re-verifies this site\'s registration.', 'Platform_Site_Registration', false );
	}

	/**
	 * A delivery verified again: the platform signs with a secret this site
	 * holds, so what a refused delivery asked for is withdrawn. A W4 rejection
	 * and a superseded attempt still stand. Reads before it deletes, because
	 * the receiver calls this for every delivery that verifies.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_refused_delivery(): void {

		if ( '' !== $this->refused_delivery() ) {
			automator_delete_option( self::OPTION_REFUSED_DELIVERY );
		}
	}

	/**
	 * Forget every thing a register is owed for: a pending re-verification —
	 * the platform's rejection (W4) and a refused callback delivery — and a
	 * superseded attempt (W3 410). Called where a registration succeeds
	 * ({@see Registration_Recorder::persist_registration()}), and to reset a dev site's platform
	 * registration (`composer platform:clear`) beside forgetting its
	 * credentials, attempt and backoff.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_reverification(): void {

		automator_delete_option( self::OPTION_REVERIFY_REASON );
		automator_delete_option( self::OPTION_REFUSED_DELIVERY );
		automator_delete_option( self::OPTION_SUPERSEDED_AT );
	}

	/**
	 * Whether a re-verification owed only for a refused delivery may be sent
	 * now: none was sent in the last {@see REFUSED_DELIVERY_PACE_SECONDS}.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_refused_delivery_paced_out(): bool {

		$last = (int) automator_get_option( self::OPTION_REFUSED_DELIVERY_REVERIFIED_AT, 0 );

		return 0 === $last || time() - $last >= self::REFUSED_DELIVERY_PACE_SECONDS;
	}

	/**
	 * Record that a re-verification owed only for a refused delivery was sent.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function note_refused_delivery_reverified(): void {

		automator_update_option( self::OPTION_REFUSED_DELIVERY_REVERIFIED_AT, time(), false );
	}

	/**
	 * Forget the refused delivery's pace: an administrator's Re-check, and
	 * `composer platform:clear` when a dev site's registration is reset.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_refused_delivery_pace(): void {

		automator_delete_option( self::OPTION_REFUSED_DELIVERY_REVERIFIED_AT );
	}

	/**
	 * Whether a re-verification is waiting for a register to settle it: the
	 * platform rejected this install's credentials (W4), or this site refused a
	 * platform delivery no secret it holds verifies.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_reverification_requested(): bool {

		return '' !== $this->platform_rejection() || '' !== $this->refused_delivery();
	}

	/**
	 * The `error.type` of a pending W4 rejection, or ''.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function platform_rejection(): string {

		return (string) automator_get_option( self::OPTION_REVERIFY_REASON, '' );
	}

	/**
	 * The reason a pending refused delivery recorded, or ''.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function refused_delivery(): string {

		return (string) automator_get_option( self::OPTION_REFUSED_DELIVERY, '' );
	}

	/**
	 * Whether the platform superseded this install's last attempt (W3 410)
	 * and no registration has succeeded since.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_attempt_superseded(): bool {

		return 0 < (int) automator_get_option( self::OPTION_SUPERSEDED_AT, 0 );
	}

	/**
	 * Whether this install owes the platform a register although it may hold
	 * a secret: the platform rejected its credentials (W4), a refused
	 * delivery's pace has passed ({@see is_refused_delivery_owed()}), or its
	 * last attempt was superseded. Owed, the site is not settled and holds
	 * every request about its connections until a register settles it.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_register_owed(): bool {

		return '' !== $this->platform_rejection() || $this->is_refused_delivery_owed() || $this->is_attempt_superseded();
	}

	/**
	 * Whether a refused delivery owes a register now: one was recorded and its
	 * pace has passed. Anyone who can post to this install can cause one, so
	 * while the pace holds it owes nothing: the site stays settled and keeps
	 * signing with the secret the platform still accepts.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_refused_delivery_owed(): bool {

		return '' !== $this->refused_delivery() && $this->is_refused_delivery_paced_out();
	}

	/**
	 * Record that the platform superseded this install's last registration
	 * attempt (a 410 at completion). A register is owed until one succeeds
	 * ({@see forget_reverification()}).
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function mark_superseded(): void {

		automator_update_option( self::OPTION_SUPERSEDED_AT, time() );
	}
}
