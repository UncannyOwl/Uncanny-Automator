<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Fail-closed lifecycle flag for a site copy.
 *
 * A cloned WordPress database brings the source site's connections with it, but
 * the platform gives the fresh copy no bindings. Between the copy detecting the
 * drift and quarantining those inherited rows there is a window where the new
 * site identity would be observable ALONGSIDE the inherited (still-active)
 * connections — and a request landing in that window could dispatch as the
 * ORIGINAL tenant.
 *
 * This flag closes the window. It is engaged (`prepared`) BEFORE registration
 * even runs, stays engaged (`active`) while the inherited rows are quarantined,
 * and lifts only on `complete`. While engaged, {@see Connection_Registry} fails
 * closed — it resolves no default connection — so the copy is never usable
 * alongside what it inherited. A failed attempt leaves the block engaged
 * (`failed`) rather than lifting it, so a transient platform failure is
 * never a security downgrade; the next successful attempt clears it.
 *
 * Backed by the plugin's own options store (wp_uap_options via
 * `automator_*_option`), so it is per-site and survives the request.
 *
 * @since 8.0.0
 */
final class Copy_Quarantine_State {

	private const OPTION = 'automator_platform_copy_quarantine_state';

	public const STATE_PREPARED = 'prepared';
	public const STATE_ACTIVE   = 'active';
	public const STATE_FAILED   = 'failed';

	/**
	 * Engage the block before the registration call. Persisted first so a
	 * crash mid-registration still leaves the copy fail-closed.
	 *
	 * @return void
	 */
	public function engage(): void {

		automator_update_option( self::OPTION, self::STATE_PREPARED );
	}

	/**
	 * Registration succeeded; keep the block engaged while the inherited rows
	 * are quarantined.
	 *
	 * @return void
	 */
	public function activate(): void {

		automator_update_option( self::OPTION, self::STATE_ACTIVE );
	}

	/**
	 * The attempt failed. The block STAYS engaged (fail-closed) so the copy
	 * cannot resolve an inherited connection; a later successful attempt
	 * completes and lifts it.
	 *
	 * @return void
	 */
	public function fail(): void {

		automator_update_option( self::OPTION, self::STATE_FAILED );
	}

	/**
	 * The inherited rows are quarantined and cleanup is done — lift the block.
	 * Idempotent: safe to call on a site that was never a copy.
	 *
	 * @return void
	 */
	public function complete(): void {

		automator_delete_option( self::OPTION );
	}

	/**
	 * @return string One of the STATE_* constants, or '' when not engaged.
	 */
	public function state(): string {

		return (string) automator_get_option( self::OPTION, '' );
	}

	/**
	 * Whether connection resolution must fail closed right now. True for every
	 * engaged state (`prepared`, `active`, `failed`); false only once the
	 * lifecycle has completed and the flag is cleared.
	 *
	 * @return bool
	 */
	public function is_blocking(): bool {

		return '' !== $this->state();
	}
}
