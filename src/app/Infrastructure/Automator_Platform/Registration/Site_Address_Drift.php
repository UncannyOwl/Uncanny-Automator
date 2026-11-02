<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;

use function Uncanny_Automator\App\Infrastructure\automator_site_credentials_store;

/**
 * Whether the address or the license this site registered with is still the one it
 * runs under: no change, a changed license, or a changed address. A changed address is
 * never healed automatically in production, because a staging push produces exactly
 * that signal and carries the production secret.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Site_Address_Drift {

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @param Site_Credentials_Store|null $store The markers this site registered with; the shared store by default.
	 * @param Registration_Wire|null      $wire  The address and license it runs under now.
	 */
	public function __construct( ?Site_Credentials_Store $store = null, ?Registration_Wire $wire = null ) {

		$this->store = $store ?? automator_site_credentials_store() ?? new Site_Credentials_Store();
		$this->wire  = $wire ?? new Registration_Wire();
	}

	/**
	 * Classify what changed since the last successful register.
	 *
	 *  - 'none'    slots empty (never registered under this code) or unchanged.
	 *  - 'url'     the Site-Name changed. Never healed automatically in
	 *              production: a staging push produces exactly this signal
	 *              and carries the production secret (spec Decision 3).
	 *  - 'license' same host, different License-Key. A clone cannot produce
	 *              this, so it re-registers immediately (the free → Pro case).
	 *
	 * @since 8.0.0
	 *
	 * @return string 'none'|'license'|'url'
	 */
	public function drift(): string {

		$stored_host     = (string) $this->store->registered_site_name();
		$stored_license  = (string) $this->store->registered_license_fingerprint();
		$current_license = $this->wire->license_fingerprint();

		// Either marker empty means we have nothing to compare against —
		// a pre-upgrade site, or a register that ran with an empty License-Key
		// (license_fingerprint() is '' then, and store() skips empty values,
		// so the two slots can be written independently).
		if ( '' === $stored_host || '' === $stored_license ) {
			return 'none';
		}

		// Same guard on the CURRENT side. An inconsistent option state (a
		// partial restore, or a license status left 'valid' with no key) makes
		// license_fingerprint() '' while the stored marker is set — that would
		// read as license drift on every init@20, and the success path could
		// never clear it because store() skips the empty value it writes back.
		// Re-registering once per page load forever is the bug; no comparison
		// is the honest answer.
		if ( '' === $current_license ) {
			return 'none';
		}

		if ( $stored_host !== $this->wire->current_site_name() ) {
			return 'url';
		}

		if ( $stored_license !== $current_license ) {
			return 'license';
		}

		return 'none';
	}

	/**
	 * Whether this request is on the address the stored signing secret was
	 * registered under. An empty marker is no answer, so it reads as false.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_on_registered_address(): bool {

		$registered = (string) $this->store->registered_site_name();

		return '' !== $registered
			&& $this->wire->current_site_name() === $registered
			&& $this->store->has();
	}

	/**
	 * Whether WordPress considers this a production environment. Gates the
	 * URL-drift path: only production defers to the notice.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_production(): bool {

		return 'production' === $this->wire->environment_type();
	}

	/**
	 * Whether the site-address question is open: a production site's address
	 * changed and nothing has answered it yet. A local site never asks
	 * (L247), and a pending copy or move has been answered.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_address_question_open(): bool {

		return 'url' === $this->drift()
			&& $this->is_production()
			&& ! ( new Local_Site_Status() )->is_local();
	}
}
