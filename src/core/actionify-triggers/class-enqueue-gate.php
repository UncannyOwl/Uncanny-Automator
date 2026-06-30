<?php

namespace Uncanny_Automator\Actionify_Triggers;

use Uncanny_Automator\Recipe\Trigger_Metadata_Loader;

/**
 * Generic, declarative enqueue gate.
 *
 * Triggers opt in by declaring `->enqueue_gate( $option, $any, $arg_index )` in
 * their definition(); that spec is compiled into the trigger metadata cache.
 * This class reads every gated trigger's spec from the merged cache and hooks
 * `automator_should_enqueue_trigger` — fired by Trigger_Engine the instant a
 * monitored hook fires, before any queue item or redundancy loopback is
 * created. It vetoes the enqueue when the fired value is not the key any live
 * recipe watches, removing the per-write loopback fan-out on high-frequency
 * hooks such as added_user_meta / updated_user_meta.
 *
 * It knows nothing about specific triggers: the policy travels with each
 * trigger via the metadata cache, so Free, Pro, and addon triggers all gate the
 * same way with zero central registration here.
 *
 * Safety — it only ever vetoes when CERTAIN no recipe watches the value. "Any"
 * sentinels, {{tokens}}, and an empty fired value all fail open. The recipe
 * lookup is the same Trigger_Index-backed source the engine uses to match
 * recipes at drain, so the gate can never veto something the drain would have
 * processed.
 *
 * @package Uncanny_Automator
 */
class Enqueue_Gate {

	/**
	 * Memoized `code => spec` map, or null until first built.
	 *
	 * @var array<string, array{option: string, any: string[], arg_index: int}>|null
	 */
	private $specs = null;

	/**
	 * Constructor — registers the veto filter.
	 */
	public function __construct() {
		add_filter( 'automator_should_enqueue_trigger', array( $this, 'gate' ), 10, 4 );
	}

	/**
	 * Veto a trigger enqueue when no live recipe watches the fired value.
	 *
	 * @param bool   $should_enqueue Current decision.
	 * @param string $trigger_code   The trigger code about to be enqueued.
	 * @param string $hook_name      The WP hook that fired (unused — the spec's
	 *                               arg_index carries the value position).
	 * @param array  $args           The hook arguments.
	 *
	 * @return bool
	 */
	public function gate( $should_enqueue, $trigger_code, $hook_name, $args ) {

		// Respect an earlier veto.
		if ( ! $should_enqueue ) {
			return $should_enqueue;
		}

		$specs = $this->specs();
		$code  = (string) $trigger_code;

		// Trigger declared no gate — nothing to do.
		if ( ! isset( $specs[ $code ] ) ) {
			return $should_enqueue;
		}

		$spec  = $specs[ $code ];
		$value = isset( $args[ $spec['arg_index'] ] ) ? (string) $args[ $spec['arg_index'] ] : '';

		// No value to compare — can't be certain, so allow (fail open).
		if ( '' === $value ) {
			return $should_enqueue;
		}

		return $this->value_is_watched( $code, $spec, $value );
	}

	/**
	 * Build (once) the `code => spec` map from the merged trigger metadata cache.
	 *
	 * Reuses Trigger_Metadata_Loader::load_metadata() — the same merged snapshot
	 * (Free + Pro + addons) the engine reads — so there is no extra SQL and no
	 * trigger instantiation. Built lazily on first gate() call, by which point
	 * every addon has contributed its metadata file.
	 *
	 * @return array<string, array{option: string, any: string[], arg_index: int}>
	 */
	private function specs() {

		if ( null !== $this->specs ) {
			return $this->specs;
		}

		$this->specs = array();

		$metadata = ( new Trigger_Metadata_Loader() )->load_metadata();

		foreach ( (array) $metadata as $entry ) {

			if ( empty( $entry['code'] ) || empty( $entry['enqueue_gate'] ) || ! is_array( $entry['enqueue_gate'] ) ) {
				continue;
			}

			$spec = $entry['enqueue_gate'];

			// A gate without an option key can't match anything — skip it.
			if ( empty( $spec['option'] ) ) {
				continue;
			}

			$this->specs[ (string) $entry['code'] ] = array(
				'option'    => (string) $spec['option'],
				'any'       => array_map( 'strval', (array) ( isset( $spec['any'] ) ? $spec['any'] : array() ) ),
				'arg_index' => isset( $spec['arg_index'] ) ? (int) $spec['arg_index'] : 2,
			);
		}

		return $this->specs;
	}

	/**
	 * Whether any live recipe on this trigger watches the fired value.
	 *
	 * recipes_from_trigger_code() is Trigger_Index-backed (cache/option, no
	 * recipe-runner boot), so each non-matching write costs an O(1) lookup plus a
	 * tiny loop — no queue item, no loopback. This is the same source the engine
	 * matches against at drain, so an empty result here means the drain would
	 * also find nothing: vetoing is safe.
	 *
	 * @param string $trigger_code The trigger code.
	 * @param array  $spec         The gate spec.
	 * @param string $value        The fired value (e.g. the meta key).
	 *
	 * @return bool
	 */
	private function value_is_watched( $trigger_code, $spec, $value ) {

		$recipes = \Automator()->get->recipes_from_trigger_code( $trigger_code );

		// No live recipe watches this code — nothing to enqueue.
		if ( empty( $recipes ) ) {
			return false;
		}

		$option = $spec['option'];
		$any    = $spec['any'];
		$value  = trim( $value );

		foreach ( (array) $recipes as $recipe ) {

			if ( empty( $recipe['triggers'] ) || ! is_array( $recipe['triggers'] ) ) {
				continue;
			}

			foreach ( $recipe['triggers'] as $trigger ) {

				$configured = isset( $trigger['meta'][ $option ] ) ? (string) $trigger['meta'][ $option ] : '';

				// "Any field" sentinel — can't pre-filter, must allow.
				if ( in_array( $configured, $any, true ) ) {
					return true;
				}

				// Token / dynamic value — can't resolve at enqueue, allow.
				if ( false !== strpos( $configured, '{{' ) ) {
					return true;
				}

				// Key match — trimmed and case-insensitive, mirroring the
				// loosest validate() (the anon post-meta trigger compares
				// case-insensitively). A looser match only ever widens what we
				// allow, so the gate can never veto a value a recipe would
				// accept; it still filters every unrelated key (the real win).
				if ( 0 === strcasecmp( trim( $configured ), $value ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
