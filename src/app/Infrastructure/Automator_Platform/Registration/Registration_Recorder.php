<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;

/**
 * Accepts a registration the platform answered: stores the secret, the site identity
 * and the markers the next drift check compares against, settles whatever a register
 * was owed for, records how a restart, a move, a recovery or a move without the old
 * secret ended, and fires the signals other code listens on.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Registration_Recorder {

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @var Reverification_Ledger
	 */
	private $ledger;

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @param Site_Credentials_Store $store    Persists the secret, the identity and the drift markers.
	 * @param Registration_Wire      $wire     Says which address and license the site registered with.
	 * @param Reverification_Ledger  $ledger   Settled by a registration that succeeds.
	 * @param Registration_Outcomes  $outcomes Told how a move ended.
	 */
	public function __construct( Site_Credentials_Store $store, Registration_Wire $wire, Reverification_Ledger $ledger, Registration_Outcomes $outcomes ) {

		$this->store    = $store;
		$this->wire     = $wire;
		$this->ledger   = $ledger;
		$this->outcomes = $outcomes;
	}

	/**
	 * Persist a successful registration response + fire its signals. Shared by
	 * the direct 200 path in {@see Register_Dispatch::dispatch()} and the C1 completion poll in
	 * {@see Completion_Poll::complete_challenge()} — both receive the same `{signing_secret,
	 * signing_secret_fingerprint, site_uuid, ...}` payload shape. Either may
	 * carry `migrated`: a move answered at once (200), or a move completed
	 * through the challenge (L199), which also brings the new secret.
	 *
	 * @param array<string, mixed> $payload      Response `data`.
	 * @param string               $callback_url Callback URL we registered with.
	 *
	 * @return void
	 */
	public function persist_registration( array $payload, string $callback_url ): void {

		// Every value is optional: a re-register returns no `signing_secret`,
		// and store() skips empty values — so the sealed secret already on
		// file survives untouched. The two drift markers are recorded from
		// what we registered WITH (not from the response) so the next drift
		// check compares like with like — and so is the challenge URL, which
		// a move without the secret later names as the old address's.
		$registered_uuid = isset( $payload['site_uuid'] ) ? (string) $payload['site_uuid'] : '';
		$registered_at   = isset( $payload['registered_at'] ) ? (string) $payload['registered_at'] : '';

		// Read before the markers below are rewritten: for a move, the address
		// the site moved from.
		$moved_from = (string) $this->store->registered_site_name();

		$this->store->store(
			array(
				'signing_secret'                 => isset( $payload['signing_secret'] ) ? (string) $payload['signing_secret'] : '',
				'fingerprint'                    => isset( $payload['signing_secret_fingerprint'] ) ? (string) $payload['signing_secret_fingerprint'] : '',
				'site_uuid'                      => $registered_uuid,
				'registered_license_fingerprint' => $this->wire->license_fingerprint(),
				'registered_site_name'           => $this->wire->current_site_name(),
				'callback_url'                   => $callback_url,
				'challenge_url'                  => $this->wire->challenge_url(),
			)
		);

		// The platform accepted this install's credentials — a pending
		// re-verification and a superseded attempt are settled, and so is a
		// move that ended earlier, and a host it refused before.
		$this->ledger->forget_reverification();
		$this->outcomes->forget_failures();

		// L247 — what the platform said about this site's address. A local
		// registration is whole as answered (no challenge to complete); one
		// answered without the flag is a public site's.
		( new Local_Site_Status() )->record( ! empty( $payload['local'] ), $this->wire->current_site_name() );

		// L247 — a local site that could not prove its old secret registered
		// again: the platform gave it a new secret and disconnected every
		// connection at its address. Connection sync reads which are gone, at
		// once, so the apps that need connecting again show.
		if ( ! empty( $payload['restarted'] ) ) {
			Dispatcher::action( 'automator_platform_site_restarted', $registered_uuid );
			automator_log( 'The platform registered this local site again with a new signing secret and disconnected its app connections (the old secret could not be proven). Connect the apps again.', 'Platform_Site_Registration', true );
		}

		// The platform moved this site's row onto the license/address we just
		// registered with, rather than creating a new one — app connections
		// carried over (spec 2026-09-01 §9).
		if ( ! empty( $payload['migrated'] ) ) {
			Dispatcher::action( 'automator_platform_site_migrated', $payload );
			automator_log( 'The platform moved this site to its current license/address; app connections were preserved.', 'Platform_Site_Registration', true );
		}

		// Move contract §10.4 — the site moved to this address, with the old
		// secret or, on the evidence the old address gave, without it. The admin
		// hears where from (and, without the secret, why). A license move keeps
		// the address, so it is no move here.
		if ( ! empty( $payload['migrated'] ) && '' !== $moved_from && $moved_from !== $this->wire->current_site_name() ) {
			$evidence = ! empty( $payload['moved_without_secret'] ) && isset( $payload['old_address'] ) ? (string) $payload['old_address'] : '';
			$this->outcomes->record_move( $moved_from, $evidence );
		}

		// W3 — a recovery: the platform confirmed this site controls its host
		// and replaced the secret it held (the old one stopped verifying at
		// once). Same site_uuid, same app connections.
		if ( ! empty( $payload['recovered'] ) ) {
			Dispatcher::action( 'automator_platform_site_recovered', $registered_uuid );
			automator_log( 'The platform confirmed this site and issued it a new signing secret; the previous one no longer works. App connections were preserved.', 'Platform_Site_Registration', true );
		}

		Dispatcher::action(
			'automator_platform_site_registered',
			$registered_uuid,
			$registered_at
		);
	}
}
