<?php

namespace Uncanny_Automator\Actionify_Triggers;

use Uncanny_Automator\Recipe_Manifest;

/**
 * Main Trigger Engine - orchestrates the entire system.
 *
 * This class handles trigger discovery, hook registration, and event processing.
 * It's designed to be portable and easy to initialize anywhere.
 *
 * @package Uncanny_Automator\Actionify_Triggers
 * @since 6.7
 */
class Trigger_Engine {

	/**
	 * Map of registered WordPress hooks to trigger codes.
	 *
	 * @var array
	 */
	private $registered_hooks = array();

	/**
	 * Trigger query instance.
	 *
	 * @var Trigger_Query
	 */
	private $query;

	/**
	 * Queue processor instance.
	 *
	 * @var Trigger_Queue
	 */
	private $queue;

	/**
	 * Generic enqueue gate. Registers the `automator_should_enqueue_trigger`
	 * veto so triggers that declared `enqueue_gate()` in their definition can
	 * skip the enqueue (and its loopback fan-out) on high-frequency hooks when
	 * no live recipe watches the fired value.
	 *
	 * @var Enqueue_Gate
	 */
	private $enqueue_gate;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->query        = new Trigger_Query();
		$this->queue        = new Trigger_Queue();
		$this->enqueue_gate = new Enqueue_Gate();
	}

	/**
	 * Initialize the trigger engine.
	 *
	 * Defers WP-listener registration to `automator_after_add_integrations`
	 * so the build-time metadata filter (`automator_lazy_trigger_metadata_files`)
	 * has every addon's contribution wired before the engine reads it. Free's
	 * own bootstrap runs at plugin-file-load time — earlier than any Pro/addon
	 * hook callback can possibly register — so reading the filter eagerly
	 * would miss Pro's metadata file entirely.
	 *
	 * `automator_after_add_integrations` fires once Free's
	 * `Initialize_Automator::automator_configure()` has finished the addon
	 * registration ceremony (`automator_add_integration` → priority 11
	 * addon callbacks → `automator_after_add_integrations`). At that point
	 * every addon that wants to contribute trigger metadata has already
	 * called `Addon_Registry::register_addon()` → `hook_trigger_metadata()`.
	 *
	 * The `did_action` guard handles late callers (tests, addons initializing
	 * after `init`) by firing immediately rather than missing the hook.
	 *
	 * @return void
	 */
	public function init() {

		if ( did_action( 'automator_after_add_integrations' ) ) {
			$this->register_automation_hooks();
			return;
		}

		add_action(
			'automator_after_add_integrations',
			array( $this, 'register_automation_hooks' ),
			99
		);
	}

	/**
	 * Register WordPress hooks for all active triggers.
	 *
	 * Discovers all active triggers and registers WordPress action hooks
	 * that will queue events for processing.
	 *
	 * @return void
	 */
	public function register_automation_hooks() {

		$covered_codes = array();
		$code_defined  = $this->collect_code_defined_hooks();

		foreach ( $code_defined as $row ) {
			$this->register_single_trigger( $row );
			$covered_codes[ $row['code'] ] = true;
		}

		$active_triggers = $this->query->get_active_triggers();

		if ( ! empty( $active_triggers ) ) {

			$flattened_triggers = $this->flatten_trigger_array( $active_triggers );
			$unique_triggers    = $this->remove_duplicate_triggers( $flattened_triggers );

			foreach ( $unique_triggers as $trigger ) {
				$code = (string) ( isset( $trigger['code'] ) ? $trigger['code'] : '' );
				if ( '' !== $code && isset( $covered_codes[ $code ] ) ) {
					continue;
				}
				$this->register_single_trigger( (array) $trigger );
			}
		}

		/**
		 * Fires after all automation triggers have been registered.
		 *
		 * Signature changed in 7.4: previously fired with a single argument
		 * (the unique triggers list from the postmeta path). Now fires with
		 * the covered-codes set and the per-hook rows from the build-time
		 * metadata cache so listeners can see which codes came from code vs.
		 * postmeta.
		 *
		 * @since 7.4
		 *
		 * @param array $covered_codes Trigger codes registered from definition() (map<code, true>).
		 * @param array $code_defined  Per-hook rows registered from the metadata cache.
		 */
		do_action( 'automator_triggers_registered', $covered_codes, $code_defined );
	}

	/**
	 * Read code-defined hook tuples from the build-time metadata cache.
	 *
	 * Emits one row per (code, hook) pair, filtered by Recipe_Manifest so
	 * only active codes register WP listeners. Returns the same `{code, hook}`
	 * shape Trigger_Query produces so register_single_trigger() can consume
	 * both. Priority and accepted_args from the metadata cache are NOT
	 * propagated — register_single_trigger() hardcodes WP priority 10 with
	 * accepted_args=99 (shared-callback design). The cache still carries
	 * the priority/args tuples because Abstract_Trigger::apply_definition()
	 * reads them off the live `Trigger_Definition` object to set instance
	 * metadata; they just don't influence the WP listener.
	 *
	 * @return array<int, array{code: string, hook: string}>
	 */
	private function collect_code_defined_hooks() {

		// Kill switch — emergency fallback to pure postmeta path.
		$enabled = ! defined( 'AUTOMATOR_CODE_DEFINED_HOOKS' ) || true === AUTOMATOR_CODE_DEFINED_HOOKS;

		/**
		 * Filter: automator_code_defined_hooks_enabled
		 *
		 * Emergency kill switch for code-defined trigger hooks. When false,
		 * the engine returns an empty set and falls back to the pure postmeta
		 * path. Mirrors `AUTOMATOR_LAZY_TRIGGERS` from the lazy plan.
		 *
		 * @since 7.4
		 *
		 * @param bool $enabled Whether code-defined hooks are enabled.
		 */
		$enabled = (bool) apply_filters( 'automator_code_defined_hooks_enabled', $enabled );

		if ( false === $enabled ) {
			return array();
		}

		$default_file = UA_ABSPATH . 'vendor/composer/autoload_trigger_metadata.php';

		/**
		 * Filter: automator_lazy_trigger_metadata_files
		 *
		 * Mirrored from Trigger_Metadata_Loader — Pro / addons register
		 * their own metadata cache files here via Addon_Registry.
		 *
		 * @since 7.4
		 *
		 * @param string[] $files Absolute paths to autoload_trigger_metadata.php files.
		 */
		$files = (array) apply_filters( 'automator_lazy_trigger_metadata_files', array( $default_file ) );

		$merged = array();
		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || ! file_exists( $file ) ) {
				continue;
			}
			$data = include $file;
			if ( is_array( $data ) ) {
				// First-write wins on code collisions — mirrors the loader.
				foreach ( $data as $code => $entry ) {
					if ( isset( $merged[ $code ] ) ) {
						continue;
					}
					$merged[ $code ] = $entry;
				}
			}
		}

		if ( empty( $merged ) ) {
			return array();
		}

		$manifest = Recipe_Manifest::get_instance();
		$rows     = array();

		foreach ( $merged as $code => $entry ) {

			if ( empty( $entry['hooks'] ) || ! is_array( $entry['hooks'] ) ) {
				continue;
			}

			if ( empty( $entry['integration'] ) ) {
				continue;
			}

			// Composite key is `INTEGRATION_CODE` (e.g. WC_WCPURCHPROD) —
			// matches the shape Recipe_Manifest stores internally.
			$composite_key = $entry['integration'] . '_' . $code;
			if ( ! $manifest->is_code_active( $composite_key ) ) {
				continue;
			}

			foreach ( $entry['hooks'] as $hook ) {
				$rows[] = array(
					'code' => (string) $code,
					'hook' => (string) ( isset( $hook[0] ) ? $hook[0] : '' ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Publish an event when a WordPress hook fires.
	 *
	 * This method is called by the WordPress action hooks registered in
	 * register_automation_hooks(). It finds matching triggers and queues
	 * events for processing.
	 *
	 * @param string $hook_name The WordPress hook that was triggered.
	 * @param array  $args The arguments passed to the hook.
	 *
	 * @return void
	 */
	public function publish_event( $hook_name, $args ) {

		// Find triggers that should respond to this hook.
		$matching_triggers = $this->find_triggers_for_hook( $hook_name );

		// Opt-in firehose: logs EVERY monitored hook as it fires, the trigger
		// codes it mapped to, and the raw args — the single chokepoint for all
		// code-defined triggers, so no per-trigger instrumentation is needed.
		// Enable with `define( 'AUTOMATOR_DEBUG_TRIGGERS', true )` (or the
		// filter). Writes to the `trigger-engine` log. No-op otherwise.
		if (
			( defined( 'AUTOMATOR_DEBUG_TRIGGERS' ) && AUTOMATOR_DEBUG_TRIGGERS )
			|| apply_filters( 'automator_debug_triggers', false, $hook_name )
		) {
			automator_log(
				array(
					'hook'    => $hook_name,
					'matched' => $matching_triggers,
					'args'    => $args,
				),
				'Trigger hook fired',
				true,
				'hook-' . $hook_name
			);
		}

		foreach ( $matching_triggers as $trigger_code ) {

			/**
			 * Filter: automator_should_enqueue_trigger
			 *
			 * Vetoes a trigger enqueue at the earliest possible point — the
			 * instant a monitored WP hook fires, before any queue item, recipe
			 * load, or redundancy loopback is created.
			 *
			 * High-frequency hooks fire many times per request (e.g.
			 * added_user_meta / updated_user_meta during a MemberPress signup
			 * writes dozens of meta rows). When recipe parts aren't loaded yet
			 * the queue falls back to one loopback HTTP self-request per item;
			 * on hosts with slow loopbacks (proxied/CDN platforms) that adds up
			 * to many seconds. A trigger that can cheaply prove "no active
			 * recipe watches this event" returns false here to skip the enqueue
			 * (and therefore the loopback) entirely. Returning false NEVER
			 * misses a real trigger — gates must only veto when certain.
			 *
			 * @since 7.4
			 *
			 * @param bool   $should_enqueue Whether to enqueue (default true).
			 * @param string $trigger_code   The trigger code about to be enqueued.
			 * @param string $hook_name      The WordPress hook that fired.
			 * @param array  $args           The hook arguments.
			 */
			if ( ! apply_filters( 'automator_should_enqueue_trigger', true, $trigger_code, $hook_name, $args ) ) {
				continue;
			}

			$this->queue->enqueue( $trigger_code, $hook_name, $args );
		}
	}

	/**
	 * Register a single trigger hook with WordPress.
	 *
	 * All hooks share the same callback — on_hook_fired() — instead of allocating
	 * a new closure per hook. With 200+ unique hooks registered, closures add up:
	 * each one captures $this and $hook_name and persists in $wp_filter for the
	 * entire request. A single method reference avoids that overhead entirely.
	 *
	 * @param array $trigger The trigger configuration.
	 *
	 * @return void
	 */
	private function register_single_trigger( array $trigger ) {

		$hook_name    = $trigger['hook'] ?? '';
		$trigger_code = $trigger['code'] ?? '';

		if ( empty( $hook_name ) || empty( $trigger_code ) ) {
			return;
		}

		// Only register each hook once.
		if ( isset( $this->registered_hooks[ $hook_name ] ) ) {
			$this->registered_hooks[ $hook_name ][] = $trigger_code;
			return;
		}

		$this->registered_hooks[ $hook_name ] = array( $trigger_code );

		// One shared method handles every hook. current_action() identifies which
		// hook fired — safe because on_hook_fired() is invoked directly by WP's
		// hook dispatcher, before any $wp_current_filter manipulation in the queue.
		add_action( $hook_name, array( $this, 'on_hook_fired' ), 10, 99 );
	}

	/**
	 * Shared callback registered for every automation hook.
	 *
	 * Called directly by WordPress when a monitored hook fires.
	 * current_action() reliably returns the correct hook name at this point
	 * because we are inside WordPress's own dispatcher — our queue's
	 * call_with_filter_context() manipulation only happens later, during
	 * process_queue(), after this method has already returned.
	 *
	 * @param mixed ...$args Hook arguments passed by WordPress.
	 *
	 * @return void
	 */
	public function on_hook_fired( ...$args ) {
		$this->publish_event( current_action(), $args );
	}

	/**
	 * Find triggers that should respond to a specific hook.
	 *
	 * @param string $hook_name The WordPress hook name.
	 *
	 * @return array Array of trigger codes that respond to this hook.
	 */
	private function find_triggers_for_hook( $hook_name ) {
		return $this->registered_hooks[ $hook_name ] ?? array();
	}

	/**
	 * Flatten nested trigger array structure.
	 *
	 * @param array $triggers The raw triggers array from discovery.
	 *
	 * @return array Flattened array of trigger configurations.
	 */
	private function flatten_trigger_array( array $triggers ) {
		$result = array();

		foreach ( $triggers as $group ) {
			if ( isset( $group[0] ) ) {
				foreach ( $group as $item ) {
					$result[] = $item;
				}
				continue;
			}

			$result[] = $group;
		}

		return $result;
	}

	/**
	 * Remove duplicate triggers based on code and hook combination.
	 *
	 * @param array $triggers Array of trigger configurations.
	 *
	 * @return array Array with duplicates removed.
	 */
	private function remove_duplicate_triggers( array $triggers ) {

		$unique = array();
		$seen   = array();

		foreach ( $triggers as $trigger ) {
			$key = ( $trigger['code'] ?? '' ) . '|' . ( $trigger['hook'] ?? '' );

			if ( ! isset( $seen[ $key ] ) ) {
				$unique[]     = $trigger;
				$seen[ $key ] = true;
			}
		}

		return $unique;
	}

	/**
	 * Get queue instance for external access.
	 *
	 * @return Trigger_Queue
	 */
	public function get_queue() {
		return $this->queue;
	}

	/**
	 * Get query instance for external access.
	 *
	 * @return Trigger_Query
	 */
	public function get_query() {
		return $this->query;
	}
}
