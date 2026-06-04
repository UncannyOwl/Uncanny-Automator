<?php
/**
 * Trigger_Late_Resolver — single source of lazy trigger construction.
 *
 * Lazy trigger registration places a minimal stub in the registry; the full
 * trigger class is only constructed when something actually needs the
 * instance. Three runtime consumers trigger that construction:
 *
 *   1. `Trigger_Queue` drains a queued event — invokes the stub's
 *      `validation_function` closure.
 *   2. Action token parsing — the `automator_parse_token_for_trigger_{code}`
 *      filter proxy fires and calls `fetch_token_data()`.
 *   3. `TRIGGER_COMMON:{N}:TITLE` parsing — reads the trigger's
 *      readable sentence via `get_readable_sentence()` / `get_sentence()`.
 *
 * Before this class, consumers 1 and 2 each carried their own
 * `static $instance` memoisation inside `Trigger_Metadata_Loader` closures —
 * so a single trigger needed by both within one request was constructed
 * twice, and consumer 3 had no construction path at all (stub had no
 * sentence; parser returned ''). This resolver collapses that into one
 * per-code construction per request, shared across every consumer.
 *
 * The resolver is intentionally a plain static utility — no DI container,
 * no singleton instance — because its memoisation must be globally visible
 * to closures registered by `Trigger_Metadata_Loader` and to the token
 * parser, neither of which receive an instance reference.
 *
 * @package Uncanny_Automator
 * @since   7.4
 */

namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Integration;

use function Automator;

/**
 * Resolves a trigger code to a fully-constructed trigger instance,
 * deferring instantiation until first need and caching per-request.
 *
 * Not final — tests subclass to inject metadata fixtures without writing
 * to the on-disk cache.
 *
 * @since 7.4
 */
class Trigger_Late_Resolver {

	/**
	 * Per-code construction cache. `object` = ready, `false` = unresolvable
	 * (missing class, failed requirements, exception). Absent key = not yet
	 * attempted.
	 *
	 * @var array<string, object|false>
	 */
	protected static $instances = array();

	/**
	 * Metadata snapshot keyed by trigger code. Cached on first read so the
	 * loader's metadata file is parsed at most once per request.
	 *
	 * @var array<string, array>|null
	 */
	protected static $metadata = null;

	/**
	 * Optional override for the metadata reader. Used by tests to inject a
	 * fixture without touching the cached static state.
	 *
	 * @var Trigger_Metadata_Loader|null
	 */
	protected static $metadata_loader = null;

	/**
	 * Resolve a trigger code to its constructed instance. Returns null when
	 * the trigger can't be constructed (no metadata entry, class missing,
	 * requirements_met() false, or constructor throws).
	 *
	 * Callers MUST treat null as "trigger is unavailable in this request"
	 * and degrade gracefully — never bubble the null into a fatal.
	 *
	 * @param string $code Trigger code (e.g. WP_LOGIN).
	 *
	 * @return object|null Trigger instance, or null when unresolvable.
	 */
	public static function get( $code ) {

		$code = (string) $code;

		if ( '' === $code ) {
			return null;
		}

		if ( array_key_exists( $code, self::$instances ) ) {
			$cached = self::$instances[ $code ];
			return false === $cached ? null : $cached;
		}

		$entry = self::metadata_entry( $code );
		if ( null === $entry ) {
			self::$instances[ $code ] = false;
			return null;
		}

		$fqcn = isset( $entry['class'] ) ? (string) $entry['class'] : '';
		if ( '' === $fqcn || ! class_exists( $fqcn ) ) {
			self::$instances[ $code ] = false;
			return null;
		}

		$integration = isset( $entry['integration'] ) ? (string) $entry['integration'] : '';
		$helpers     = Integration::helpers_for( $integration );
		$deps        = null !== $helpers ? array( $helpers ) : array();

		try {
			$candidate = $fqcn::late_construct( ...$deps );
		} catch ( \Throwable $e ) {
			automator_log(
				'Trigger_Late_Resolver failed for ' . $code . ': ' . $e->getMessage(),
				'Trigger_Late_Resolver'
			);
			self::$instances[ $code ] = false;
			return null;
		}

		// Mirror the eager `register_trigger()` gate so third parties can
		// force-enable a lazy trigger via the same filter they use for the
		// eager path. Failure here is identical to "requirements not met"
		// from the consumer's perspective — trigger is unavailable this
		// request.
		$requirement_met = apply_filters(
			'automator_item_requirement_meta',
			$candidate->requirements_met(),
			Automator()->get_triggers()
		);

		if ( ! $requirement_met ) {
			self::$instances[ $code ] = false;
			return null;
		}

		self::$instances[ $code ] = $candidate;
		return $candidate;
	}

	/**
	 * Seed the resolver's metadata cache directly. Called by
	 * `Trigger_Metadata_Loader::load()` after it reads the on-disk file so
	 * stub closures handing a code back to the resolver don't trigger a
	 * second read.
	 *
	 * Tests can also prime fixtures via this method without subclassing the
	 * loader.
	 *
	 * @param array $metadata Map of trigger code => metadata entry.
	 *
	 * @return void
	 */
	public static function prime_metadata( array $metadata ) {
		self::$metadata = $metadata;
	}

	/**
	 * Look up a single metadata entry by trigger code, populating the
	 * resolver's metadata cache on first call when nobody primed it.
	 *
	 * @param string $code Trigger code.
	 *
	 * @return array|null
	 */
	protected static function metadata_entry( $code ) {

		if ( null === self::$metadata ) {
			$loader         = self::$metadata_loader ?? new Trigger_Metadata_Loader();
			self::$metadata = $loader->load_metadata();
		}

		return self::$metadata[ $code ] ?? null;
	}

	/**
	 * Inject a metadata-loading collaborator (tests only).
	 *
	 * @param Trigger_Metadata_Loader|null $loader Loader to use, or null to
	 *                                             restore default behaviour.
	 *
	 * @return void
	 */
	public static function set_metadata_loader( $loader ) {
		self::$metadata_loader = $loader;
		self::$metadata        = null;
	}

	/**
	 * Reset all cached state. Tests call this between cases to avoid leaks.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instances       = array();
		self::$metadata        = null;
		self::$metadata_loader = null;
	}
}
