<?php
/**
 * Backward-Compatibility Reconciler
 *
 * TRANSITIONAL compatibility layer. When Free runs the modern abstract Integration
 * framework but a paired add-on (e.g. Uncanny Automator Pro < 7.3) still ships a given
 * integration in the LEGACY style, that integration's items are orphaned: the modern
 * Free integration has no `add-*-integration.php` "main", so it never enters
 * Set_Up_Automator::$active_directories, and the standard Recipe_Part_Loader loop
 * skips the add-on's merged files. This class recovers them, from canonical sources
 * only, after the normal loaders have run.
 *
 * Sources (no class-name guessing):
 *   - Gated items (triggers/actions/closures/conditions/loop_filters): the merged item
 *     map (Recipe_Manifest::get_item_map()) — carries the final FQCN, file and code.
 *   - Tokens (no code in the item map): the FILE list from
 *     Set_Up_Automator::$all_integrations[ dir ]['tokens'] + the CLASS name from the
 *     Composer classmap.
 *
 * SELF-NEUTRALIZING: every instantiation is guarded by the Utilities FQCN tracker, and
 * the token pass only runs when a gated item was actually recovered. Once an add-on
 * integration migrates to the modern framework it loads its own items first (init:11),
 * so this pass (init:30) finds them already tracked and does nothing for it. Hence it
 * is safe to leave in during the transition — it never double-instantiates.
 *
 * @todo TRANSITIONAL — remove this class and its single call in
 *       Recipe_Part_Loader::load_recipe_parts() once the minimum supported add-on
 *       version ships every integration on the modern framework and no legacy
 *       third-party add-on is supported.
 *
 * @package Uncanny_Automator\Integration_Loader
 * @since   7.3
 */

namespace Uncanny_Automator\Integration_Loader;

use Uncanny_Automator\Recipe_Manifest;
use Uncanny_Automator\Set_Up_Automator;
use Uncanny_Automator\Utilities;

/**
 * Class Backward_Compat_Reconciler
 *
 * Single responsibility: recover legacy add-on recipe-part and token classes orphaned
 * by a modern-Free + legacy-add-on version mismatch.
 */
class Backward_Compat_Reconciler {

	/**
	 * Cached file-path => FQCN map, merged from every registered Composer ClassLoader
	 * (Free + add-ons). The only pre-built source of token class names — tokens carry
	 * no item-map code. Built once per request.
	 *
	 * @var array<string,string>|null
	 */
	private static $file_to_class_map = null;

	/**
	 * Error handler for instantiation failures (shared with Recipe_Part_Loader).
	 *
	 * @var Load_Error_Handler
	 */
	private $error_handler;

	/**
	 * Backward_Compat_Reconciler constructor.
	 *
	 * @param Load_Error_Handler $error_handler Error handler for instantiation failures.
	 */
	public function __construct( Load_Error_Handler $error_handler ) {
		$this->error_handler = $error_handler;
	}

	/**
	 * Recover orphaned legacy add-on items from the canonical maps.
	 *
	 * @param Recipe_Manifest $manifest The manifest instance.
	 * @param bool            $load_all Whether full-load (editor) mode is active.
	 *
	 * @return void
	 */
	public function reconcile( $manifest, $load_all ) {

		// No external add-on contributed anything — nothing to reconcile (a Free-only
		// install pays zero traversal cost).
		if ( empty( Set_Up_Automator::$external_integrations_namespace ) ) {
			return;
		}

		$item_map = $manifest->get_item_map();

		if ( empty( $item_map ) ) {
			return;
		}

		$this->log( 'start: ' . count( $item_map ) . ' codes; load_all=' . ( $load_all ? 'yes' : 'no' ) );

		// Item-map keys use underscores (loop_filters), NOT the hyphenated folder names
		// returned by automator_get_gated_directory_types() (loop-filters) — do not swap
		// that in here. This mirrors the other item-map reader,
		// Recipe_Part_Loader::find_class_in_integration() (which carries the same note).
		$gated_types = array( 'triggers', 'actions', 'closures', 'conditions', 'loop_filters' );

		foreach ( $item_map as $integration_code => $types ) {

			// Plugin-active gate — only reconcile integrations whose plugin is active
			// (the normal loop is inherently gated this way). Without it, full-load would
			// instantiate items for INACTIVE integrations whose helpers/APIs are absent
			// and fatal in their register() (e.g. a BuddyBoss condition deref).
			if ( ! in_array( $integration_code, Set_Up_Automator::$active_integrations_code, true ) ) {
				continue;
			}

			// Targeted mode: skip integrations no published recipe uses.
			if ( ! $load_all && ! $manifest->is_integration_needed( $integration_code ) ) {
				continue;
			}

			$recovered = 0;

			foreach ( $gated_types as $type ) {

				if ( empty( $types[ $type ] ) ) {
					continue;
				}

				foreach ( $types[ $type ] as $composite_key => $entry ) {
					if ( $this->reconcile_item( $entry, $composite_key, $manifest, $load_all, $type ) ) {
						$recovered++;
					}
				}
			}

			// Recovered gated items → also reconcile this integration's tokens so their
			// runtime parse filters register and old add-on token values hydrate.
			if ( $recovered > 0 ) {
				$this->reconcile_tokens( $integration_code, $manifest, $load_all );
				$this->log( '[' . $integration_code . '] recovered ' . $recovered . ' gated items' );
			}
		}
	}

	/**
	 * Instantiate one gated item from the merged item map.
	 *
	 * Simpler than Recipe_Part_Loader::load_single_gated_file(): the map entry already
	 * carries the final FQCN and an absolute file path, so no resolver, no loop-filter
	 * namespace prepend, and no constructor args.
	 *
	 * @param array           $entry         Item-map entry ( 'class', 'file', 'code' ).
	 * @param string          $composite_key Manifest composite key.
	 * @param Recipe_Manifest $manifest      The manifest instance.
	 * @param bool            $load_all      Whether full-load mode is active.
	 * @param string          $type          Recipe-part type.
	 *
	 * @return bool True if the class was instantiated.
	 */
	private function reconcile_item( $entry, $composite_key, $manifest, $load_all, $type ) {

		$class = isset( $entry['class'] ) ? $entry['class'] : '';
		$file  = isset( $entry['file'] ) ? $entry['file'] : '';

		if ( '' === $class ) {
			return false;
		}

		// Modern-framework items (…\Integrations\… namespace) are owned by a modern
		// Integration subclass that injects helpers through the constructor. This pass
		// instantiates with NO args (legacy orphans pull helpers from
		// Automator()->helpers->recipe at call time), so bare-constructing a modern item
		// hands it a null helper and its register()/fields() fatals on the first
		// $this->item_helpers->* deref. A modern add-on (e.g. Pro 7.3+) is demand-gated and
		// may construct its own items AFTER this pass runs, so the idempotency guard below
		// can miss them — the namespace is the reliable signal. Only LEGACY (flat-namespace)
		// add-on orphans belong here; skip anything modern, its own integration loads it.
		if ( false !== strpos( $class, '\\Integrations\\' ) ) {
			return false;
		}

		// Item-level gate — same semantics as is_class_in_active_map()'s targeted path.
		if ( ! $load_all && ! $manifest->is_code_active( $composite_key ) ) {
			return false;
		}

		// Idempotent: already loaded by the normal loop, the targeted sweep, or a modern
		// add-on's own load.php. This is what makes the pass a no-op once the add-on
		// integration migrates.
		if ( false !== Utilities::get_class_instance( $class ) ) {
			return false;
		}

		// Defer lazy triggers (definition() !== null) to Trigger_Metadata_Loader; legacy
		// add-on triggers have no definition() and fall through to load.
		if ( 'triggers' === $type && class_exists( $class ) && method_exists( $class, 'definition' ) && null !== $class::definition() ) {
			return false;
		}

		if ( '' === $file || ! is_file( $file ) ) {
			return false;
		}

		include_once $file;

		if ( ! class_exists( $class ) ) {
			return false;
		}

		// Legacy add-on recipe-part classes take no constructor args (they pull helpers
		// from Automator()->helpers->recipe at call time). A modern-namespace item would
		// need its helpers injected, but those load via their own integration and are
		// caught by the idempotency guard above, so they never reach this instantiation.
		try {
			Utilities::add_class_instance( $class, new $class() );
			$this->log( 'loaded [' . $type . '] ' . $class );
			return true;
		} catch ( \Throwable $e ) {
			$this->error_handler->handle( $class, $e );
			return false;
		}
	}

	/**
	 * Reconcile orphaned token classes for an integration code.
	 *
	 * Tokens carry no item-map code, so source the FILE list from
	 * Set_Up_Automator::$all_integrations[ dir ]['tokens'] and the CLASS name from the
	 * Composer classmap (never guessed). In full-load all load; in targeted only
	 * external/add-on (e.g. Pro) token classes load — they self-register the runtime
	 * token-parse filters old add-on items need to hydrate. Free token classes stay
	 * editor-only (Free hydrates via its own pipeline and they self-guard against the
	 * modern integration anyway).
	 *
	 * @param string          $integration_code The integration code.
	 * @param Recipe_Manifest $manifest         The manifest instance.
	 * @param bool            $load_all         Whether full-load mode is active.
	 *
	 * @return void
	 */
	private function reconcile_tokens( $integration_code, $manifest, $load_all ) {

		// get_directory_code_map() is one-dir-per-code (extract_directory_from_types()
		// returns the first matching integrations/<dir>/ path), so this recovers tokens for
		// that single directory. A renamed integration whose Free/Pro folders diverge
		// (e.g. EC: the-events-calendar vs old Pro's event-tickets) only gets one bucket
		// here — safe for the WP/LD/EC trio because the other dir ships a Pro legacy main,
		// so its tokens already load via the normal Recipe_Part_Loader::load_tokens() loop.
		// Covering a divergent-dir orphan with NO Pro main would require deriving the dir
		// from each recovered item's own $entry['file']; not reachable for today's set.
		$dir_name = array_search( $integration_code, $manifest->get_directory_code_map(), true );

		if ( false === $dir_name || empty( Set_Up_Automator::$all_integrations[ $dir_name ]['tokens'] ) ) {
			return;
		}

		foreach ( (array) Set_Up_Automator::$all_integrations[ $dir_name ]['tokens'] as $file ) {

			if ( is_array( $file ) || ! is_file( $file ) ) {
				continue;
			}

			$class = $this->class_for_file( $file );

			if ( '' === $class || false !== Utilities::get_class_instance( $class ) || ! class_exists( $class ) ) {
				continue;
			}

			// Targeted: external/add-on token classes only (Free tokens are editor-only).
			if ( ! $load_all && 0 === strpos( ltrim( $class, '\\' ), 'Uncanny_Automator\\' ) ) {
				continue;
			}

			try {
				Utilities::add_class_instance( $class, new $class() );
				$this->log( 'loaded [tokens] ' . $class );
			} catch ( \Throwable $e ) {
				unset( $e ); // Best-effort — token reconcile failures must not surface.
			}
		}
	}

	/**
	 * Resolve a file path to its authoritative FQCN via the Composer classmap.
	 *
	 * Merges the classmap from every registered Composer ClassLoader into a
	 * file => class reverse map, cached for the request.
	 *
	 * @param string $file Absolute path to the PHP file.
	 *
	 * @return string The FQCN, or '' when unknown.
	 */
	private function class_for_file( $file ) {

		if ( null === self::$file_to_class_map ) {

			self::$file_to_class_map = array();

			if ( class_exists( '\Composer\Autoload\ClassLoader' )
				&& method_exists( '\Composer\Autoload\ClassLoader', 'getRegisteredLoaders' ) ) {

				foreach ( \Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader ) {
					foreach ( $loader->getClassMap() as $class => $path ) {
						// Classmap paths use Composer's $baseDir form
						// (…/vendor/composer/../../src/…); collapse to match the clean
						// absolute paths in $all_integrations.
						self::$file_to_class_map[ self::collapse_path( (string) $path ) ] = $class;
					}
				}
			}
		}

		$key = self::collapse_path( $file );

		return isset( self::$file_to_class_map[ $key ] ) ? self::$file_to_class_map[ $key ] : '';
	}

	/**
	 * Collapse '.' / '..' segments — string-only, no filesystem. Preserves a leading
	 * '/' (Unix) or drive prefix (Windows).
	 *
	 * @param string $path The path to collapse.
	 *
	 * @return string The collapsed path.
	 */
	private static function collapse_path( $path ) {

		$path   = wp_normalize_path( $path );
		$prefix = ( '/' === substr( $path, 0, 1 ) ) ? '/' : '';
		$out    = array();

		foreach ( explode( '/', $prefix ? substr( $path, 1 ) : $path ) as $seg ) {

			if ( '' === $seg || '.' === $seg ) {
				continue;
			}

			if ( '..' === $seg ) {
				array_pop( $out );
				continue;
			}

			$out[] = $seg;
		}

		return $prefix . implode( '/', $out );
	}

	/**
	 * Debug trace, gated behind AUTOMATOR_DEBUG_MODE (the same switch
	 * Recipe_Part_Loader::log_unmapped_class() uses). Tagged [UA-RECONCILE] so the
	 * trace is greppable. Silent in production.
	 *
	 * @param string $message The message.
	 *
	 * @return void
	 */
	private function log( $message ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && AUTOMATOR_DEBUG_MODE ) {
			error_log( '[UA-RECONCILE] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Guarantee the helper-chain contracts that OLD Pro (< 7.3) recipe parts dereference at
	 * boot, so a Free/Pro version mismatch degrades gracefully instead of fataling the whole
	 * site (WSOD).
	 *
	 * Old Pro triggers/actions call e.g. recipe->event_tickets->options->all_ec_events()
	 * synchronously in define_trigger()/define_action(). Where Free 7.3 renamed/removed that
	 * slug, the chain hits null → fatal. Called SYNCHRONOUSLY right after Phase 4
	 * (load_helpers) and before recipe parts load — i.e. before any old-Pro item, INCLUDING
	 * versions that eager-boot triggers, can dereference the chain.
	 *
	 * This is a demand-INDEPENDENT backstop. The per-integration alias (e.g.
	 * The_Events_Calendar_Integration) only runs when Free demand-loads that modern
	 * integration; an old Pro that does not advertise the integration in Free's item map
	 * (so Free skips it) but still eager-boots its trigger would otherwise have no bridge.
	 * Idempotent (no-ops once the alias or a prior call satisfied the chain) and never throws.
	 *
	 * @return void
	 */
	public static function bridge_legacy_helper_contracts() {

		try {
			// Event Tickets: Free 7.3 renamed the dir event-tickets → the-events-calendar
			// (code still 'EC'); OLD Pro (< 7.3) items deref
			// recipe->event_tickets->options->all_ec_events().
			self::ensure_legacy_helper_contract(
				'event_tickets',
				'all_ec_events',
				'\Uncanny_Automator_Pro\Event_Tickets_Pro_Helpers',
				'\Uncanny_Automator_Pro\Integrations\The_Events_Calendar\The_Events_Calendar_Pro_Integration',
				'the_events_calendar',
				'\Uncanny_Automator\Integrations\The_Events_Calendar\The_Events_Calendar_Helpers'
			);
		} catch ( \Throwable $e ) {
			unset( $e ); // A back-compat bridge must never break boot.
		}
	}

	/**
	 * Point recipe->{legacy_slug} at a contract-complete modern Free helper when OLD Pro
	 * (< 7.3) is active and the real deref chain recipe->{slug}->options->{probe}() does not
	 * resolve. Self-gating and idempotent; never downgrades a slot that already resolves.
	 *
	 * @param string $legacy_slug         recipe-> slug OLD Pro dereferences (e.g. 'event_tickets').
	 * @param string $probe_method        Method on ->options that proves the chain (e.g. 'all_ec_events').
	 * @param string $old_pro_class       OLD Pro root helper class (present ⇒ old Pro).
	 * @param string $new_pro_integration New namespaced Pro integration (present ⇒ new Pro; skip).
	 * @param string $modern_slug         Free's current slug for the same integration.
	 * @param string $modern_helper_class Free helper FQCN carrying the ported contract.
	 *
	 * @return void
	 */
	private static function ensure_legacy_helper_contract( $legacy_slug, $probe_method, $old_pro_class, $new_pro_integration, $modern_slug, $modern_helper_class ) {

		// OLD Pro only: old root class present, new namespaced integration absent.
		if ( ! class_exists( $old_pro_class ) || class_exists( $new_pro_integration ) ) {
			return;
		}

		$recipe   = \Automator()->helpers->recipe;
		$existing = isset( $recipe->$legacy_slug ) ? $recipe->$legacy_slug : null;

		// Already satisfied — the REAL chain recipe->{slug}->options->{probe}() resolves
		// (e.g. the per-integration alias already wired the modern helper). Probe the chain,
		// NOT method_exists($existing,$probe): the bare Pro helper can carry the method yet
		// have a null ->options, which is exactly the case that fatals.
		if ( self::chain_resolves( $existing, $probe_method ) ) {
			return;
		}

		// Build a contract-complete helper: prefer the modern Free helper already loaded
		// under the renamed slug; else instantiate it (safe standalone — its hook
		// registration is static-guarded).
		$modern = isset( $recipe->$modern_slug ) ? $recipe->$modern_slug : null;
		if ( ! self::chain_resolves( $modern, $probe_method ) && class_exists( $modern_helper_class ) ) {
			$modern = new $modern_helper_class();
		}

		// Cannot satisfy the contract — leave the slot untouched (never make it worse).
		if ( ! self::chain_resolves( $modern, $probe_method ) ) {
			return;
		}

		// Reuse the bare Pro helper (if any) as ->pro so Pro-side chains still resolve.
		if ( null !== $existing && $existing instanceof $old_pro_class && property_exists( $modern, 'pro' ) ) {
			$modern->pro = $existing;
		}

		$recipe->$legacy_slug = $modern;

		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && AUTOMATOR_DEBUG_MODE ) {
			error_log( '[UA-RECONCILE] bridged legacy contract: recipe->' . $legacy_slug . ' → ' . get_class( $modern ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Whether $helper->options->{$method}() would resolve — the exact chain OLD Pro
	 * dereferences. A non-null ->options carrying the probe method is the contract.
	 *
	 * @param mixed  $helper The candidate recipe helper.
	 * @param string $method The probe method expected on ->options.
	 *
	 * @return bool
	 */
	private static function chain_resolves( $helper, $method ) {
		return is_object( $helper )
			&& isset( $helper->options )
			&& is_object( $helper->options )
			&& method_exists( $helper->options, $method );
	}
}
