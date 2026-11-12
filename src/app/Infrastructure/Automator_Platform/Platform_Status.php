<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Backoff;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Door;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Wire;

/**
 * Report-safe view of this site's link to the Automator Platform (the App
 * Integration Platform).
 *
 * Composes a flat, NON-SENSITIVE snapshot of the site↔platform registration
 * for display on the system status page, the licensing screen, or anywhere
 * else that needs it — every field here is safe to paste into a support
 * report. It deliberately never surfaces the signing secret (that is sealed
 * at rest in {@see Site_Credentials_Store}); only its non-secret fingerprint
 * is exposed, which is designed to identify a credential without revealing it.
 *
 * "Site ID" is the platform's tenancy key — the normalized Site-Name header
 * (`home_url()` with the scheme stripped), which is what the platform derives
 * its `site_id` from (see Platform_Site_Registration). It is NOT the local
 * drift-detection UUID in {@see Site_Identity_Service}, which the platform
 * never sees.
 *
 * @since 8.0.0
 * @package Uncanny_Automator\App\Infrastructure\Automator_Platform
 */
final class Platform_Status {

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @var Site_Identity_Service
	 */
	private $identity;

	/**
	 * @var Platform_Site_Registration|null
	 */
	private $registration;

	/**
	 * @var Registration_Backoff
	 */
	private $backoff;

	/**
	 * @param Site_Credentials_Store|null $store    Lazy default — production
	 *                                              gets the real store, tests
	 *                                              inject a fake.
	 * @param Site_Identity_Service|null  $identity Owner of the local site UUID.
	 * @param Platform_Site_Registration|null $registration Where the registration
	 *                                                  stands; the shared one by default.
	 * @param Registration_Backoff|null   $backoff  Owner of when the last attempt was made.
	 */
	public function __construct( ?Site_Credentials_Store $store = null, ?Site_Identity_Service $identity = null, ?Platform_Site_Registration $registration = null, ?Registration_Backoff $backoff = null ) {
		$this->store        = $store ?? new Site_Credentials_Store();
		$this->identity     = $identity ?? new Site_Identity_Service();
		$this->registration = $registration ?? \Uncanny_Automator\App\Infrastructure\automator_platform_site_registration();
		$this->backoff      = $backoff ?? new Registration_Backoff();
	}

	/**
	 * The report-safe snapshot.
	 *
	 * @return array{
	 *   connected: bool,
	 *   status: string,
	 *   status_label: string,
	 *   site_id: string,
	 *   site_uuid: string,
	 *   callback_url: string,
	 *   license_active: bool,
	 *   fingerprint: string,
	 *   credentials_rotated_at: int,
	 *   last_register_attempt: int,
	 *   registration_state: string,
	 *   registration_detail: string
	 * }
	 */
	public function to_array(): array {

		$connected      = $this->store->has();
		$license_active = $this->license_active();
		$status         = $this->resolve_status( $connected, $license_active );

		// Handoff R1 — where the registration stands, from its one owner, in
		// the sentence every other surface tells it with.
		$registration = null === $this->registration
			? Registration_Status::of( Registration_Status::NOT_REGISTERED )
			: $this->registration->status();

		return array(
			'connected'              => $connected,
			'status'                 => $status,
			'status_label'           => $this->status_label( $status ),
			'site_id'                => $this->site_id(),
			'site_uuid'              => (string) $this->identity->get(),
			'callback_url'           => $this->callback_url(),
			'license_active'         => $license_active,
			'fingerprint'            => (string) $this->store->fingerprint(),
			'credentials_rotated_at' => (int) automator_get_option( 'automator_platform_credentials_rotated_at', 0 ),
			'last_register_attempt'  => $this->backoff->last_attempt_at(),
			'registration_state'     => $registration->state(),
			'registration_detail'    => Platform_Failure_Message::for_registration( $registration )->text(),
		);
	}

	/**
	 * Three-state connection status. `connected` means a signing secret is on
	 * file (the site is registered). `pending` means it isn't yet, but the
	 * license is active so the next `init@20` registration pass will attempt
	 * it. `disconnected` means no active license — nothing will register.
	 *
	 * @param bool $connected      Whether a signing secret is on file.
	 * @param bool $license_active Whether a Pro/free license is valid.
	 *
	 * @return string One of: connected, pending, disconnected.
	 */
	private function resolve_status( bool $connected, bool $license_active ): string {

		if ( $connected ) {
			return 'connected';
		}

		return $license_active ? 'pending' : 'disconnected';
	}

	/**
	 * Human-readable label for a status key.
	 *
	 * @param string $status One of: connected, pending, disconnected.
	 *
	 * @return string
	 */
	private function status_label( string $status ): string {

		$labels = array(
			'connected'    => esc_html_x( 'Connected', 'Automator App Platform', 'uncanny-automator' ),
			'pending'      => esc_html_x( 'Awaiting registration', 'Automator App Platform', 'uncanny-automator' ),
			'disconnected' => esc_html_x( 'Not connected', 'Automator App Platform', 'uncanny-automator' ),
		);

		return $labels[ $status ] ?? $labels['disconnected'];
	}

	/**
	 * The platform tenancy key: the normalized Site-Name header. Matches the
	 * `preg_replace('#^https?://#','', get_home_url())` the header injectors
	 * send, which is exactly what the platform stores as its `site_id`.
	 *
	 * @return string
	 */
	private function site_id(): string {

		// Prefer the platform-issued site_id when one is on file
		// (authoritative). The platform does not return one today, so this
		// falls back to the Site-Name this context would send — the
		// normalized home host the platform derives its site_id from.
		$stored = (string) $this->store->site_id();
		if ( '' !== $stored ) {
			return $stored;
		}

		if ( ! function_exists( 'home_url' ) ) {
			return '';
		}

		return (string) preg_replace( '#^https?://#', '', (string) home_url() );
	}

	/**
	 * The plugin's webhook receiver URL the platform calls back to, of the
	 * door the registration gives it: the REST route, or the site's own
	 * address ({@see Query_Door}). A public endpoint — safe to report.
	 *
	 * @return string
	 */
	private function callback_url(): string {

		return ( new Registration_Wire( null, new Registration_Door( $this->store ) ) )->callback_url();
	}

	/**
	 * Whether a valid Pro or free license is on file. Pro status only counts
	 * when Pro is actually active — a leftover key from a removed Pro install
	 * must not read as licensed (mirrors Platform_Site_Registration).
	 *
	 * @return bool
	 */
	private function license_active(): bool {

		$pro_status = ( function_exists( 'is_automator_pro_active' ) && is_automator_pro_active() )
			? (string) automator_get_option( 'uap_automator_pro_license_status', '' )
			: '';

		$free_status = (string) automator_get_option( 'uap_automator_free_license_status', '' );

		return 'valid' === $pro_status || 'valid' === $free_status;
	}
}
