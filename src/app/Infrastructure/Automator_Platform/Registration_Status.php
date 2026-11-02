<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Where this site's registration with the Automator platform stands — the one
 * answer every surface reads (registration and move handoff R1): the
 * registration notice, the system status page, the connect page and the app
 * actions that are held while the site's address is in question (R6).
 *
 * Built only by {@see Platform_Site_Registration::status()}, which owns the
 * order in which the states are decided. A value: it says what the state is
 * and carries the details that state has; the sentences that tell it are
 * {@see Platform_Failure_Message::for_registration()}'s.
 *
 * @since 8.0.0
 */
final class Registration_Status {

	/** The registration is settled for this address and license. */
	public const REGISTERED = 'registered';

	/** No license is active, so nothing registers. */
	public const NO_LICENSE = 'no_license';

	/**
	 * The platform refused this site's license (`status`, `type`, `reason` from
	 * its license reasons, `description` its sentence for the customer), and
	 * has not accepted a request from this site since.
	 *
	 * @since 8.0.0
	 */
	public const LICENSE_REFUSED = 'license_refused';

	/**
	 * States the license decides: told first, ahead of "registered", because
	 * no app works until the license does (decision k, 29 Oct). Neither holds
	 * dispatch, and neither needs attention as a registration: the platform
	 * answers each action with its own reason, and the license has its own
	 * notices.
	 *
	 * @since 8.0.0
	 */
	private const WAITING_ON_LICENSE = array( self::NO_LICENSE, self::LICENSE_REFUSED );

	/** Licensed, never registered, nothing has failed yet. */
	public const NOT_REGISTERED = 'not_registered';

	/**
	 * The platform is confirming this site (an attempt is in flight, since
	 * `since`); once a check of this site's address failed, `reason` names what
	 * it came to and `status` its HTTP status.
	 */
	public const PENDING = 'pending';

	/** The platform asked this site to register again; the next check does. */
	public const REVERIFYING = 'reverifying';

	/** A production site's address changed and the site-address question is open. */
	public const ADDRESS_CHANGED = 'address_changed';

	/** The admin answered "this is a copy" and the copy is not registered yet. */
	public const COPY_PENDING = 'copy_pending';

	/** The admin answered "this site moved here" and the move is being confirmed. */
	public const MOVE_PENDING = 'move_pending';

	/** The platform refused the registration (`status`, `type`, `description`). */
	public const REFUSED = 'refused';

	/**
	 * The platform could not reach this site's challenge endpoint
	 * (`challenge_url`; `reason` and `status`: why the attempt ended, or what
	 * the challenge's last try came to).
	 */
	public const UNREACHABLE = 'unreachable';

	/** The last attempt failed in a way that can pass; retried after `until`. */
	public const RETRYING = 'retrying';

	/**
	 * The `reason` of a REFUSED state the platform's challenge job decided: it
	 * refused this site's address (L286), named by `challenge_url`. Final until
	 * the address changes or an admin re-checks.
	 *
	 * @since 8.0.0
	 */
	public const REASON_HOST = 'host';

	/**
	 * The `reason` of a RETRYING state: the platform's challenge job found no
	 * address for this site's host at its last try, named by
	 * `challenge_url`. Registered again on the refusal ladder, after `until`.
	 *
	 * @since 8.0.0
	 */
	public const REASON_HOST_UNRESOLVED = 'host_unresolved';

	/**
	 * States in which registration could not be confirmed after an attempt:
	 * the admin is told, and offered the re-check.
	 */
	private const NEEDS_ATTENTION = array( self::REFUSED, self::UNREACHABLE, self::RETRYING );

	/**
	 * States in which this site's `/v3` requests are refused before they are
	 * sent (R6): the secret on file may not be this site's to sign with until
	 * the admin's answer lands. A REFUSED status carries the hold as well when
	 * the address is in question as it is refused (`holds_dispatch`, decided by
	 * {@see Platform_Site_Registration::status()}).
	 */
	private const HOLDS_DISPATCH = array( self::ADDRESS_CHANGED, self::COPY_PENDING, self::MOVE_PENDING );

	/**
	 * @var string
	 */
	private $state;

	/**
	 * @var array{since: int, until: int, at: int, status: int, type: string, description: string, challenge_url: string, reason: string, holds_dispatch: bool}
	 */
	private $details;

	/**
	 * @param string               $state   One of the constants.
	 * @param array<string, mixed> $details The state's details.
	 */
	private function __construct( string $state, array $details ) {

		$this->state   = $state;
		$this->details = array(
			'since'           => (int) ( $details['since'] ?? 0 ),
			'until'           => (int) ( $details['until'] ?? 0 ),
			'at'              => (int) ( $details['at'] ?? 0 ),
			'status'          => (int) ( $details['status'] ?? 0 ),
			'type'            => (string) ( $details['type'] ?? '' ),
			'description'     => (string) ( $details['description'] ?? '' ),
			'challenge_url'   => (string) ( $details['challenge_url'] ?? '' ),
			'reason'          => (string) ( $details['reason'] ?? '' ),
			'platform_reason' => (string) ( $details['platform_reason'] ?? '' ),
			'holds_dispatch'  => (bool) ( $details['holds_dispatch'] ?? false ),
		);
	}

	/**
	 * @param string               $state   One of the constants.
	 * @param array<string, mixed> $details since, until, at, status, type,
	 *                                      description, challenge_url, reason —
	 *                                      whichever the state has — and, for a
	 *                                      refusal, platform_reason and
	 *                                      holds_dispatch.
	 *
	 * @return self
	 */
	public static function of( string $state, array $details = array() ): self {
		return new self( $state, $details );
	}

	/**
	 * @return string One of the constants.
	 */
	public function state(): string {
		return $this->state;
	}

	/**
	 * @return bool Whether the registration is settled.
	 */
	public function is_registered(): bool {
		return self::REGISTERED === $this->state;
	}

	/**
	 * @return bool Whether an attempt failed and the admin should be told.
	 */
	public function needs_attention(): bool {
		return in_array( $this->state, self::NEEDS_ATTENTION, true );
	}

	/**
	 * @since 8.0.0
	 *
	 * @return bool Whether the license is what stands between this site and its apps.
	 */
	public function is_waiting_on_license(): bool {
		return in_array( $this->state, self::WAITING_ON_LICENSE, true );
	}

	/**
	 * @return bool Whether app actions are held until the admin's answer lands.
	 */
	public function holds_dispatch(): bool {
		return in_array( $this->state, self::HOLDS_DISPATCH, true ) || $this->details['holds_dispatch'];
	}

	/**
	 * @return int Unix time the attempt in flight began; 0 when none.
	 */
	public function since(): int {
		return $this->details['since'];
	}

	/**
	 * @return int Unix time the next automatic attempt is allowed; 0 when none.
	 */
	public function until(): int {
		return $this->details['until'];
	}

	/**
	 * @return int Unix time the failure was recorded; 0 when none.
	 */
	public function at(): int {
		return $this->details['at'];
	}

	/**
	 * @return int The platform's HTTP status for a refusal or failure; 0 when none.
	 */
	public function status(): int {
		return $this->details['status'];
	}

	/**
	 * @return string The platform's `error.type`; '' when none.
	 */
	public function type(): string {
		return $this->details['type'];
	}

	/**
	 * @return string The platform's description; '' when none.
	 */
	public function description(): string {
		return $this->details['description'];
	}

	/**
	 * @return string The challenge URL the platform could not reach; '' when none.
	 */
	public function challenge_url(): string {
		return $this->details['challenge_url'];
	}

	/**
	 * @return string Why an attempt was given up ('expired', '401', '404'), or
	 *                what the challenge's last try came to (the platform's
	 *                outcome code, for PENDING and UNREACHABLE),
	 *                {@see REASON_HOST}, {@see REASON_HOST_UNRESOLVED}; '' when none.
	 */
	public function reason(): string {
		return $this->details['reason'];
	}

	/**
	 * @return string The platform's `error.reason` for a refusal, e.g.
	 *                {@see Registration\Register_Answer::REASON_HOST_NOT_PINNABLE}; '' when it gave none.
	 */
	public function platform_reason(): string {
		return $this->details['platform_reason'];
	}

	/**
	 * Report-safe form for the system status page: no secret is ever part of
	 * a status.
	 *
	 * @return array{state: string, since: int, until: int, at: int, status: int, type: string, description: string, challenge_url: string, reason: string}
	 */
	public function to_array(): array {
		return array( 'state' => $this->state ) + $this->details;
	}
}
