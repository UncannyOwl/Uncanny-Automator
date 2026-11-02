<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;

/**
 * How registration attempts ended, kept for the admin to be told: why a move ended
 * without moving the site, a move without the old secret that landed, the host the
 * platform refused, how the last attempt failed, and that the platform refused this
 * plugin version as too old. Each is a durable option, shown once or kept until the
 * address changes, a registration succeeds or the plugin is updated.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Registration_Outcomes {

	/**
	 * Why the admin's last "This site moved here" ended without moving the
	 * site, with the platform's description when it gave one: 'refused',
	 * 'not_verified', 'address_in_use', 'not_production', 'license_transfer'
	 * or 'local' (refused at register), 'unconfirmed' (the platform could
	 * not confirm the new address), 'superseded' (the platform dropped the
	 * move before it landed), or — for a move without the secret —
	 * 'old_address_unknown' (refused at register: the platform never
	 * confirmed a challenge address for the old site, so it has nothing to
	 * probe), 'still_live' (another install still answers at the old address)
	 * or 'undetermined' (the old address never answered clearly). Site-wide and durable, because the
	 * ending is often decided on a cron tick and any admin may be the one to
	 * answer again. Held until the drift notice shows it
	 * ({@see take_move_failure()}), the next register starts
	 * ({@see Register_Dispatch::dispatch()}), or a registration succeeds
	 * ({@see Registration_Recorder::persist_registration()}).
	 *
	 * @since 8.0.0
	 */
	private const OPTION_MOVE_FAILURE = 'automator_platform_site_move_failure';

	/**
	 * Refusals of a move — at register, or at completion — for which "try
	 * again" is the wrong advice, keyed by `error.type`. Any other refusal is
	 * plain 'refused'.
	 *
	 * @since 8.0.0
	 */
	private const MOVE_REFUSALS = array(
		'VERIFICATION_ERROR'             => 'not_verified',
		'SITE_MIGRATION_CONFLICT'        => 'address_in_use',
		'SITE_ENVIRONMENT_MISMATCH'      => 'not_production',
		'SITE_LICENSE_TRANSFER_DETECTED' => 'license_transfer',
		'SITE_IS_LOCAL'                  => 'local',
		'SITE_STILL_LIVE'                => 'still_live',
		'OLD_ADDRESS_UNDETERMINED'       => 'undetermined',
	);

	/**
	 * Refusals of a move at register that mean something else there than at
	 * completion, keyed by `error.type`; read before {@see MOVE_REFUSALS}.
	 * OLD_ADDRESS_UNDETERMINED at register: the platform never confirmed a
	 * challenge address for the old site, so it has nothing to probe and
	 * trying again cannot help (move contract §10.2). At completion it means
	 * the old address never answered clearly, which a later try can change.
	 *
	 * @since 8.0.0
	 */
	private const REGISTER_MOVE_REFUSALS = array(
		'OLD_ADDRESS_UNDETERMINED' => 'old_address_unknown',
	);

	/**
	 * A move to a new address that landed, with the old secret or without it
	 * (move contract §10.4): the address the site moved from and, for a move
	 * without the secret, how the old address showed the site left — the
	 * platform's `old_address`: 'does_not_resolve', 'redirects_here' or
	 * 'serves_this_install' ('' for a move with the secret). Site-wide, because
	 * the move lands on whichever request polls completion; held until an
	 * admin is shown it ({@see take_move()}).
	 *
	 * @since 8.0.0
	 */
	public const OPTION_MOVED = 'automator_platform_site_moved';

	/**
	 * The host refusal register/complete answered (L286): the platform's
	 * challenge job refused this site's host — it does not resolve, it is not a
	 * public address, it must be registered in its A-label form. Stored as
	 * {site_name, status, type, description, platform_reason}: the registration's refused
	 * state, shown with the platform's description, and never retried while
	 * the site's address is the one refused — a changed address registers
	 * again ({@see self::host_refusal()}).
	 *
	 * @since 8.0.0
	 */
	public const OPTION_HOST_REFUSED = 'automator_platform_site_host_refused';

	/**
	 * How the last registration attempt failed (handoff R1): the
	 * {@see Registration_Status} state it leaves — refused, unreachable or
	 * retrying — with the platform's status, type and description, the
	 * challenge URL it could not reach, and when. Written where an attempt
	 * fails, read by {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::status()}, cleared when a registration succeeds.
	 *
	 * @since 8.0.0
	 */
	private const OPTION_LAST_FAILURE = 'automator_platform_site_register_last_failure';

	/**
	 * The platform's last refusal of this site's license, with the fingerprint
	 * of the license key it was for.
	 *
	 * @since 8.0.0
	 */
	public const OPTION_LICENSE_REFUSED = 'automator_platform_license_refused';

	/**
	 * Set to the running plugin version when the platform answers
	 * PLUGIN_UPGRADE_REQUIRED. Keyed to the version so it clears itself: once
	 * the plugin updates, the stored value no longer matches and the admin
	 * notice stops rendering without anything having to delete it.
	 *
	 * @since 8.0.0
	 */
	public const OPTION_UPGRADE_REQUIRED = 'automator_platform_upgrade_required_at_version';

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @param Registration_Wire|null $wire Says which address the site runs on.
	 */
	public function __construct( ?Registration_Wire $wire = null ) {

		$this->wire = $wire ?? new Registration_Wire();
	}

	/**
	 * The host refusal that stands for this site's CURRENT address, or null.
	 * A refusal recorded for another address is dropped here: the address
	 * changed, so the next register asks the platform afresh.
	 *
	 * @since 8.0.0
	 *
	 * @return array{site_name: string, status: int, type: string, description: string, platform_reason: string}|null
	 */
	public function host_refusal(): ?array {

		$refusal = automator_get_option( self::OPTION_HOST_REFUSED, null );

		if ( ! is_array( $refusal ) || ! isset( $refusal['site_name'] ) ) {
			return null;
		}

		if ( (string) $refusal['site_name'] !== $this->wire->current_site_name() ) {
			automator_delete_option( self::OPTION_HOST_REFUSED );

			return null;
		}

		return array(
			'site_name'       => (string) $refusal['site_name'],
			'status'          => (int) ( $refusal['status'] ?? 0 ),
			'type'            => (string) ( $refusal['type'] ?? '' ),
			'description'     => (string) ( $refusal['description'] ?? '' ),
			'platform_reason' => (string) ( $refusal['platform_reason'] ?? '' ),
		);
	}

	/**
	 * Keep a host refusal (L286): register/complete answered the challenge
	 * job's refusal of this site's host, or register answered that only its
	 * A-label form reaches it. Stored with the address it was
	 * refused for, so a changed address registers again
	 * ({@see host_refusal()}).
	 *
	 * @since 8.0.0
	 *
	 * @param int    $status          Its status.
	 * @param string $type            Its `error.type`.
	 * @param string $description     The platform's description of it.
	 * @param string $platform_reason Its `error.reason`, '' when it gave none.
	 *
	 * @return void
	 */
	public function record_host_refusal( int $status, string $type, string $description, string $platform_reason ): void {

		automator_update_option(
			self::OPTION_HOST_REFUSED,
			array(
				'site_name'       => $this->wire->current_site_name(),
				'status'          => $status,
				'type'            => $type,
				'description'     => $description,
				'platform_reason' => $platform_reason,
			),
			false
		);
	}

	/**
	 * The platform's refusal of this site's CURRENT license key, or null. A
	 * refusal recorded for another key is dropped here: the license changed,
	 * so the platform has not judged it yet.
	 *
	 * @since 8.0.0
	 *
	 * @return array{status: int, type: string, reason: string, message: string}|null
	 */
	public function license_refusal(): ?array {

		$refusal = automator_get_option( self::OPTION_LICENSE_REFUSED, null );

		if ( ! is_array( $refusal ) || ! isset( $refusal['license'] ) ) {
			return null;
		}

		if ( (string) $refusal['license'] !== $this->wire->license_fingerprint() ) {
			automator_delete_option( self::OPTION_LICENSE_REFUSED );

			return null;
		}

		return array(
			'status'  => (int) ( $refusal['status'] ?? 0 ),
			'type'    => (string) ( $refusal['type'] ?? '' ),
			'reason'  => (string) ( $refusal['reason'] ?? '' ),
			'message' => (string) ( $refusal['message'] ?? '' ),
		);
	}

	/**
	 * Keep the platform's refusal of this site's license, stored with the
	 * fingerprint of the key it was for ({@see license_refusal()}).
	 *
	 * @since 8.0.0
	 *
	 * @param int    $status  Its status.
	 * @param string $type    Its `error.type`.
	 * @param string $reason  Its `error.reason`, one of the platform's license reasons.
	 * @param string $message Its `error.message`, the customer's sentence; '' when it gave none.
	 *
	 * @return void
	 */
	public function record_license_refusal( int $status, string $type, string $reason, string $message ): void {

		automator_update_option(
			self::OPTION_LICENSE_REFUSED,
			array(
				'license' => $this->wire->license_fingerprint(),
				'status'  => $status,
				'type'    => $type,
				'reason'  => $reason,
				'message' => $message,
			),
			false
		);
	}

	/**
	 * Forget the license refusal: the platform accepted a request from this
	 * site. Read before it is deleted, because every accepted platform request
	 * asks.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_license_refusal(): void {

		if ( null !== automator_get_option( self::OPTION_LICENSE_REFUSED, null ) ) {
			automator_delete_option( self::OPTION_LICENSE_REFUSED );
		}
	}

	/**
	 * Forget the host refusal, so the next register asks the platform afresh.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_host_refusal(): void {

		automator_delete_option( self::OPTION_HOST_REFUSED );
	}

	/**
	 * Forget why the last move ended: a new register starts, so it no longer applies.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_move_failure(): void {

		automator_delete_option( self::OPTION_MOVE_FAILURE );
	}

	/**
	 * Forget every ending a registration that succeeded makes obsolete: how the
	 * last move ended, the host refusal, and how the last attempt failed.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_failures(): void {

		$this->forget_move_failure();
		$this->forget_host_refusal();
		automator_delete_option( self::OPTION_LAST_FAILURE );
	}

	/**
	 * How the last registration attempt failed, or null when none did.
	 *
	 * @since 8.0.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function last_failure(): ?array {

		$failure = automator_get_option( self::OPTION_LAST_FAILURE, null );

		return is_array( $failure ) ? $failure : null;
	}

	/**
	 * Record that the platform refused this plugin version as too old
	 * (PLUGIN_UPGRADE_REQUIRED), keyed to the running version.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function record_upgrade_required(): void {

		automator_update_option( self::OPTION_UPGRADE_REQUIRED, AUTOMATOR_PLUGIN_VERSION );
	}

	/**
	 * The plugin version the platform refused as too old, or '' when it has
	 * refused none. It only counts while it is the running version: after an
	 * update it no longer matches.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function upgrade_required_version(): string {

		return (string) automator_get_option( self::OPTION_UPGRADE_REQUIRED, '' );
	}

	/**
	 * Keep that a move to a new address landed: where from, and — for a move
	 * without the old secret — how the old address showed the site left it
	 * ({@see take_move()}).
	 *
	 * @since 8.0.0
	 *
	 * @param string $from     The address the site moved from.
	 * @param string $evidence The platform's `old_address`; '' for a move with the secret.
	 *
	 * @return void
	 */
	public function record_move( string $from, string $evidence ): void {

		automator_update_option(
			self::OPTION_MOVED,
			array(
				'from'     => $from,
				'evidence' => $evidence,
			),
			false
		);
	}

	/**
	 * Where the last move came from and the old address's evidence, without
	 * forgetting it: what a notice reads to say it ({@see OPTION_MOVED}).
	 *
	 * @since 8.0.0
	 *
	 * @return array{from: string, evidence: string}|null
	 */
	public function peek_move(): ?array {

		$moved = automator_get_option( self::OPTION_MOVED, null );
		if ( ! is_array( $moved ) ) {
			return null;
		}

		return array(
			'from'     => (string) ( $moved['from'] ?? '' ),
			'evidence' => (string) ( $moved['evidence'] ?? '' ),
		);
	}

	/**
	 * Where the last move came from and the old address's evidence, then
	 * forget it, so it is shown once ({@see OPTION_MOVED}).
	 *
	 * @since 8.0.0
	 *
	 * @return array{from: string, evidence: string}|null
	 */
	public function take_move(): ?array {

		$moved = $this->peek_move();
		if ( null !== $moved ) {
			automator_delete_option( self::OPTION_MOVED );
		}

		return $moved;
	}

	/**
	 * Why the admin's last move ended without moving the site, with the
	 * platform's description when it gave one, then forget it, so it is shown
	 * once ({@see OPTION_MOVE_FAILURE}).
	 *
	 * @since 8.0.0
	 *
	 * @return array{reason: string, description: string} The reason is '' when
	 *         none; otherwise 'refused', 'not_verified', 'address_in_use',
	 *         'not_production', 'license_transfer', 'local', 'unconfirmed',
	 *         'superseded', 'old_address_unknown', 'still_live' or
	 *         'undetermined'.
	 */
	public function take_move_failure(): array {

		$ending = $this->peek_move_failure();

		// The notice asks on every admin page while the drift stands; only an
		// ending that is there costs a write.
		if ( '' !== $ending['reason'] ) {
			automator_delete_option( self::OPTION_MOVE_FAILURE );
		}

		return $ending;
	}

	/**
	 * Why the admin's last move ended without moving the site, without
	 * forgetting it: what a notice reads to say it.
	 *
	 * @since 8.0.0
	 *
	 * @return array{reason: string, description: string} As {@see take_move_failure()}.
	 */
	public function peek_move_failure(): array {

		$ending = automator_get_option( self::OPTION_MOVE_FAILURE, null );

		return array(
			'reason'      => is_array( $ending ) ? (string) ( $ending['reason'] ?? '' ) : (string) $ending,
			'description' => is_array( $ending ) ? (string) ( $ending['description'] ?? '' ) : '',
		);
	}

	/**
	 * Record why the admin's move ended without moving the site.
	 *
	 * @since 8.0.0
	 *
	 * @param string $reason      See {@see OPTION_MOVE_FAILURE}.
	 * @param string $description The platform's description of it, if any.
	 *
	 * @return void
	 */
	public function fail_move( string $reason, string $description = '' ): void {

		automator_update_option(
			self::OPTION_MOVE_FAILURE,
			array(
				'reason'      => $reason,
				'description' => $description,
			),
			false
		);
		automator_log( 'The site move ended without moving the site (' . $reason . '). The site-address notice asks again.', 'Platform_Site_Registration', false );
	}

	/**
	 * Record how the last registration attempt failed, for {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::status()}.
	 *
	 * @since 8.0.0
	 *
	 * @param string                $state   Registration_Status::REFUSED, UNREACHABLE or RETRYING.
	 * @param Register_Answer       $answer  The platform's answer; an empty one when there is none.
	 * @param int                   $status  Its status; 0 when none.
	 * @param array<string, string> $details challenge_url / reason, for unreachable.
	 *
	 * @return void
	 */
	public function record_failure( string $state, Register_Answer $answer, int $status, array $details = array() ): void {

		automator_update_option(
			self::OPTION_LAST_FAILURE,
			array(
				'state'           => $state,
				'status'          => $status,
				'type'            => $answer->type(),
				'description'     => $answer->message(),
				'platform_reason' => $answer->reason(),
				'at'              => time(),
			) + $details,
			false
		);
	}

	/**
	 * Record a refusal of a host that had no address yet
	 * ({@see Register_Answer::is_unresolved_host()}): a failure that is retried,
	 * told by the host it names.
	 *
	 * @since 8.0.0
	 *
	 * @param Register_Answer $answer        The refusal.
	 * @param string          $challenge_url This site's challenge URL, which names the host.
	 *
	 * @return void
	 */
	public function record_unresolved_host( Register_Answer $answer, string $challenge_url ): void {

		$this->record_failure(
			Registration_Status::RETRYING,
			$answer,
			$answer->status(),
			array(
				'challenge_url' => $challenge_url,
				'reason'        => Registration_Status::REASON_HOST_UNRESOLVED,
			)
		);
	}

	/**
	 * Why a move the platform refused, at completion, ended: the reason the admin is told.
	 *
	 * @since 8.0.0
	 *
	 * @param string $type The refusal's `error.type`.
	 *
	 * @return string Plain 'refused' for a type with no reason of its own.
	 */
	public function move_refusal( string $type ): string {

		return self::MOVE_REFUSALS[ $type ] ?? 'refused';
	}

	/**
	 * Why a move the platform refused at register ended. Read before the
	 * completion reasons, because a type can mean something else here.
	 *
	 * @since 8.0.0
	 *
	 * @param string $type The refusal's `error.type`.
	 *
	 * @return string
	 */
	public function register_move_refusal( string $type ): string {

		return self::REGISTER_MOVE_REFUSALS[ $type ] ?? $this->move_refusal( $type );
	}
}
