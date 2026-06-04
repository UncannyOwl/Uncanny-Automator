<?php
/**
 * Trigger Metadata Loader — consumes the build-time extracted trigger metadata
 * and registers registry stubs + lazy token-filter proxies at boot, without
 * constructing the trigger classes themselves.
 *
 * Called from Initialize_Automator::load_recipe_parts() before the per-integration
 * eager loading runs. For each trigger code whose integration is needed by the
 * Recipe_Manifest:
 *
 *   1. A stub is pushed into Automator()->set_triggers() with a lazy closure
 *      as validation_function. Trigger_Queue invokes it at fire time, which
 *      constructs the trigger (once) and delegates to validate_hook().
 *
 *   2. A lazy add_filter proxy is registered for
 *      automator_parse_token_for_trigger_{code}. When an action parses tokens
 *      (even in a request where the trigger did not fire), the proxy constructs
 *      the trigger and delegates to fetch_token_data(). This is the mechanism
 *      that keeps multi-trigger AND recipes and async actions working.
 *
 * The loader is a no-op when:
 *   - AUTOMATOR_LAZY_TRIGGERS is not defined or is false (feature flag).
 *   - Recipe_Manifest::should_load_all() is true (admin/editor contexts
 *     populate the registry via the eager path).
 *   - The metadata file is missing (fresh deploy before composer dump).
 *
 * @package Uncanny_Automator
 * @since   7.3
 */

namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Recipe_Manifest;

use function Automator;

/**
 * Registers registry stubs and lazy token-filter proxies from the extracted
 * trigger metadata cache.
 *
 * Not final — tests subclass to override read_metadata_file() and inject
 * fixtures without touching the on-disk cache.
 *
 * @since 7.3
 */
class Trigger_Metadata_Loader {

	/**
	 * Load metadata and register stubs + lazy token proxies for
	 * manifest-active codes.
	 *
	 * @return void
	 */
	public function load() {

		if ( ! $this->is_lazy_enabled() ) {
			return;
		}

		if ( Recipe_Manifest::get_instance()->should_load_all() ) {
			return;
		}

		$metadata = $this->read_metadata_file();

		if ( empty( $metadata ) ) {
			return;
		}

		// Seed the resolver with the same snapshot we're about to register
		// stubs for. Without this, the stub closures hand a code back to the
		// resolver which would re-read the on-disk metadata cache — wasteful
		// in production and broken in tests that inject a stubbed metadata
		// loader (the test's metadata never reaches the resolver otherwise).
		Trigger_Late_Resolver::prime_metadata( $metadata );

		$manifest = Recipe_Manifest::get_instance();

		foreach ( $metadata as $key => $entry ) {

			if ( ! $this->is_entry_valid( $entry ) ) {
				continue;
			}

			// Code lives in the entry — the map key is a fast-lookup mirror.
			// trigger_meta can be shared across triggers within an integration,
			// but code must be unique, so it is always the identifier.
			$code          = $entry['code'];
			$composite_key = $entry['integration'] . '_' . $code;

			if ( ! $manifest->is_code_active( $composite_key ) ) {
				continue;
			}

			$this->register_stub( $code, $entry );
			$this->register_token_proxy( $code, $entry );
		}
	}

	/**
	 * Circuit breaker — the lazy path is ON by default. Short-circuit only by
	 * explicitly defining `AUTOMATOR_LAZY_TRIGGERS` to false, or by returning
	 * false from the `automator_lazy_triggers_enabled` filter.
	 *
	 * @return bool
	 */
	private function is_lazy_enabled() {

		$enabled = defined( 'AUTOMATOR_LAZY_TRIGGERS' ) ? (bool) AUTOMATOR_LAZY_TRIGGERS : true;

		/**
		 * Filter: automator_lazy_triggers_enabled
		 *
		 * Emergency short-circuit for the lazy trigger metadata loader.
		 * Returning false forces the eager path for this request — use only
		 * if a production issue is traced to lazy loading and you need the
		 * eager fallback without shipping code.
		 *
		 * @since 7.3
		 *
		 * @param bool $enabled Whether the lazy loader should run this request.
		 */
		return (bool) apply_filters( 'automator_lazy_triggers_enabled', $enabled );
	}

	/**
	 * Sanity-check a metadata entry. Keeps the load loop shielded from
	 * stale or hand-edited cache files.
	 *
	 * @param mixed $entry
	 *
	 * @return bool
	 */
	private function is_entry_valid( $entry ) {

		$shape_ok = is_array( $entry )
			&& ! empty( $entry['code'] )
			&& ! empty( $entry['class'] )
			&& ! empty( $entry['integration'] )
			&& is_string( $entry['code'] )
			&& is_string( $entry['class'] )
			&& is_string( $entry['integration'] );

		if ( ! $shape_ok ) {
			return false;
		}

		// Verify the trigger class is autoloadable on this runtime. Protects
		// the cross-version matrix (Pro newer than Free) where an addon's
		// metadata file may reference a class that doesn't exist yet on this
		// Free version. Without this check, the stub registers successfully
		// and `late_construct()` fatals on first fire.
		//
		// `class_exists()` triggers the autoloader, which is the correct
		// behaviour here — we want the class to resolve before we register
		// a stub that will attempt to instantiate it later.
		if ( ! class_exists( $entry['class'] ) ) {
			automator_log(
				'Trigger metadata entry skipped — class not loadable: ' . $entry['class'] . ' (code: ' . $entry['code'] . ')',
				'Trigger_Metadata_Loader'
			);
			return false;
		}

		return true;
	}

	/**
	 * Populate a registry entry whose validation_function is a lazy closure
	 * that constructs the trigger on first invocation.
	 *
	 * Dependencies are resolved ONCE here (at init:30, after all Integrations
	 * have been constructed at init:1) and captured in the closure. The
	 * closure itself does nothing but call `late_construct( ...$deps )`,
	 * consult `requirements_met()`, and delegate — no per-fire lookups, no
	 * hidden side channels. The `$deps` array mirrors exactly what
	 * `Abstract_Integration::get_load_arguments()` passes on the eager path,
	 * so the trigger sees the same shape either way.
	 *
	 * `requirements_met()` is checked on FIRST invocation to mirror the
	 * eager-path `register_trigger()` gate — if the trigger's plugin
	 * dependency is missing, the closure short-circuits and never calls
	 * validate_hook. Result is cached on the closure's static.
	 *
	 * @param string $code  Trigger code.
	 * @param array  $entry Metadata entry.
	 *
	 * @return void
	 */
	private function register_stub( $code, array $entry ) {

		$lazy_validate = static function ( ...$hook_args ) use ( $code ) {

			// Resolver caches per-code and shares construction with the
			// token-parse proxy and Trigger_Late_Resolver consumers (sentence
			// rendering, etc.). Null = unresolvable, treated as "skip".
			$instance = Trigger_Late_Resolver::get( $code );

			if ( null === $instance ) {
				return null;
			}

			return $instance->validate_hook( ...$hook_args );
		};

		$stub = array(
			'code'                => $code,
			'integration'         => $entry['integration'],
			'meta_code'           => isset( $entry['trigger_meta'] ) ? $entry['trigger_meta'] : $code,
			'type'                => isset( $entry['trigger_type'] ) ? $entry['trigger_type'] : 'user',
			'validation_function' => $lazy_validate,
		);

		// Mirror the eager `register_trigger()` contract so Pro / third-party filters
		// can rewrite the stub's registered fields (meta_code, type, etc.).
		$stub = apply_filters( 'automator_register_trigger', $stub );

		Automator()->set_triggers( $stub );
	}

	/**
	 * Register a lazy filter proxy for automator_parse_token_for_trigger_{code}.
	 *
	 * Unlike the stub's validation_function (which only fires when the trigger
	 * itself fires on this request), this proxy MUST register unconditionally so
	 * cross-request token parsing works — e.g. multi-trigger AND recipes where
	 * trigger A fired in request 1 and action B parses A's tokens in request 2.
	 *
	 * @param string $code  Trigger code.
	 * @param array  $entry Metadata entry.
	 *
	 * @return void
	 */
	private function register_token_proxy( $code, array $entry ) {

		$filter = sprintf(
			'automator_parse_token_for_trigger_%s',
			strtolower( $entry['integration'] . '_' . $code )
		);

		$lazy_fetch = static function ( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_arg ) use ( $code ) {

			$instance = Trigger_Late_Resolver::get( $code );

			if ( null === $instance ) {
				return $value;
			}

			return $instance->fetch_token_data( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_arg );
		};

		add_filter( $filter, $lazy_fetch, 20, 6 );
	}

	/**
	 * Public accessor returning the merged metadata snapshot used by the
	 * loader. Exists for consumers that need the data without running
	 * `load()` (which has registration side effects) — e.g.
	 * `Trigger_Late_Resolver` looking up a single entry by code on demand.
	 *
	 * @return array<string, array>
	 */
	public function load_metadata() {
		return $this->read_metadata_file();
	}

	/**
	 * Read the metadata cache. Missing file → empty array (eager fallback).
	 *
	 * Addons (Pro, third-party) contribute their own metadata files via the
	 * `automator_lazy_trigger_metadata_files` filter. `Addon_Registry` wires
	 * this filter automatically when an addon passes `trigger_metadata_file`
	 * in its registration config, so addons never have to hook the filter
	 * directly.
	 *
	 * First-write wins on code collisions: Free's entries load first, and
	 * later files with a duplicate code are ignored. Pro never reuses Free
	 * trigger codes, so this is a safety net rather than a routine path.
	 *
	 * Protected so tests can subclass and inject fixtures.
	 *
	 * @return array
	 */
	protected function read_metadata_file() {

		$files = array( UA_ABSPATH . 'vendor/composer/autoload_trigger_metadata.php' );

		/**
		 * Filter: automator_lazy_trigger_metadata_files
		 *
		 * Register additional lazy trigger metadata cache files from Pro
		 * or third-party addons. Prefer the `trigger_metadata_file` config
		 * key on `Addon_Registry::register()` — that key wires this filter
		 * automatically and keeps addon integration symmetric with the
		 * existing `item_map_file` and `filemap_file` keys.
		 *
		 * @since 7.3
		 *
		 * @param string[] $files Absolute paths to autoload_trigger_metadata.php files.
		 */
		$files = (array) apply_filters( 'automator_lazy_trigger_metadata_files', $files );

		$merged = array();

		foreach ( $files as $file ) {

			if ( ! is_string( $file ) || ! file_exists( $file ) ) {
				continue;
			}

			$data = include $file;

			if ( ! is_array( $data ) ) {
				continue;
			}

			foreach ( $data as $code => $entry ) {
				// First-write wins — Free's codes take precedence over any
				// addon that accidentally reuses a code.
				if ( isset( $merged[ $code ] ) ) {
					continue;
				}
				$merged[ $code ] = $entry;
			}
		}

		return $merged;
	}
}
