<?php
/**
 * Legacy-Pro removed-base-class shim.
 *
 * TRANSITIONAL back-compat. When Free modernizes an integration it removes/renames the
 * legacy FLAT base class — e.g. `\Uncanny_Automator\Wpff_Tokens` became
 * `\Uncanny_Automator\Integrations\Wp_Fluent_Forms\Wp_Fluent_Forms_Tokens`. An OLD Pro
 * (< 7.3) still ships `class Wpff_Pro_Tokens extends \Uncanny_Automator\Wpff_Tokens`, so
 * loading/autoloading that Pro class hits a MISSING PARENT → fatal at class DECLARATION
 * time. (That is why a loader try/catch around `new $class()` cannot catch it — the class
 * dies while being declared during include/autoload, before any instantiation.) This
 * recurs in EVERY future migration.
 *
 * Rather than a per-class alias in each modernized file (load-order fragile under
 * demand-loading, and one easily-forgotten edit per migration), a single generic
 * last-resort autoloader synthesizes the missing legacy base as an alias of a no-op stub,
 * so the `extends` resolves and the site degrades gracefully instead of a WSOD. It fires
 * ONLY for the legacy shape (flat `Uncanny_Automator\*_Tokens` / `*_Helpers`) and ONLY
 * when an old Pro (< 7.3) is active — inert on aligned/modern installs (so it cannot
 * affect the modern loader or the reconciler).
 *
 * @todo TRANSITIONAL — remove once the minimum supported Pro ships every integration on
 *       the modern framework (no Pro class extends a removed flat Free base).
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.3
 */

namespace Uncanny_Automator\Integration_Loader;

/**
 * Class Legacy_Pro_Class_Shim
 *
 * Single responsibility: keep an old Pro (< 7.3) that `extends` a flat Free base class
 * Free 7.3 removed from fataling at class-declaration time.
 */
class Legacy_Pro_Class_Shim {

	/**
	 * Register the fallback autoloader. Appended (last-resort) so it only fires when every
	 * prior autoloader — Composer's classmap and automator_autoloader — has already missed.
	 * Idempotent.
	 *
	 * @return void
	 */
	public static function register() {

		static $registered = false;

		if ( $registered ) {
			return;
		}

		$registered = true;

		// ( callback, throw, prepend=false ) — append so this is the last resort.
		spl_autoload_register( array( __CLASS__, 'autoload' ), true, false );
	}

	/**
	 * Synthesize a removed legacy flat Free base class as a no-op stub alias.
	 *
	 * @param string $class FQCN that PHP failed to resolve through every prior autoloader.
	 *
	 * @return void
	 */
	public static function autoload( $class ) {

		// Old Pro (< 7.3) is the only consumer that extends removed flat Free bases. Gate
		// here (lazily) — Pro defines this constant when its plugin file loads (before Free
		// loads recipe parts), and this autoloader fires at class-resolution time (init+),
		// so the constant is always set by the time a legacy Pro class is requested. Absent
		// constant (no Pro) or modern Pro (>= 7.3) → inert.
		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' )
			|| version_compare( AUTOMATOR_PRO_PLUGIN_VERSION, '7.3.0', '>=' ) ) {
			return;
		}

		// Legacy shape only: a FLAT class directly under the Uncanny_Automator namespace
		// whose name ends in _Tokens or _Helpers (the per-integration bases old Pro extends).
		// Modern namespaced classes (…\Integrations\…) and core bases (Recipe, Integration)
		// are never ours.
		if ( 0 !== strpos( $class, 'Uncanny_Automator\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( 'Uncanny_Automator\\' ) );

		if ( false !== strpos( $relative, '\\' ) ) {
			return;
		}

		if ( ! preg_match( '/_(Tokens|Helpers)$/', $relative ) ) {
			return;
		}

		// Alias the missing base to the shared no-op stub so the old-Pro `extends` resolves.
		class_alias( Legacy_Pro_Compat_Stub::class, $class );

		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && AUTOMATOR_DEBUG_MODE ) {
			error_log( '[UA-LEGACY-SHIM] synthesized stub for removed legacy class: ' . $class ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

/**
 * Class Legacy_Pro_Compat_Stub
 *
 * No-op base for synthesized legacy classes. Intentionally empty — its only job is to
 * exist so an old-Pro `extends <removed flat base>` resolves. Old-Pro subclasses
 * self-register their own hooks in their constructor and reach data through the helper
 * chain (Automator()->helpers->recipe->...), not through this base, so an empty stub
 * degrades gracefully: no WSOD, and no functional regression for the token classes
 * observed. Lives in this file (not autoloaded separately) so it is guaranteed present
 * when the autoloader aliases against it.
 */
class Legacy_Pro_Compat_Stub {
}
