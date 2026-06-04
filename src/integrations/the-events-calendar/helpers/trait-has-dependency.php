<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

// `Has_Dependency_State` lives in its own file (`class-has-dependency-state.php`)
// per PSR-1 (one declaration per file). Both are picked up by the composer
// classmap autoload, so no explicit `require_once` is needed here.

/**
 * Trait Has_Dependency
 *
 * Shared dependency gate used by every trigger/action/condition/loop-filter
 * in the TEC integration that requires an add-on beyond TEC core.
 *
 * Test override pattern: each gate checks `Has_Dependency_State::$test_override`
 * first (a real class static — shared across all consumers), then falls back
 * to `class_exists()` in production. WPUnit tests cannot undo `class_exists()`
 * on a real class mid-process, so the side-channel state class is the only
 * way to gate consumers consistently across tests.
 *
 * @package Uncanny_Automator
 */
trait Has_Dependency {

	/**
	 * Is Event Tickets active?
	 *
	 * @return bool
	 */
	public function et_active() {

		if ( array_key_exists( 'et', Has_Dependency_State::$test_override ) && null !== Has_Dependency_State::$test_override['et'] ) {
			return (bool) Has_Dependency_State::$test_override['et'];
		}

		return class_exists( '\Tribe__Tickets__Main' );
	}

	/**
	 * Is Event Tickets Plus active?
	 *
	 * @return bool
	 */
	public function etp_active() {

		if ( array_key_exists( 'etp', Has_Dependency_State::$test_override ) && null !== Has_Dependency_State::$test_override['etp'] ) {
			return (bool) Has_Dependency_State::$test_override['etp'];
		}

		return class_exists( '\Tribe__Tickets_Plus__Main' );
	}

	/**
	 * Is Events Calendar Pro active?
	 *
	 * Note the **double underscore between Events and Pro** — ECP's main
	 * class is `Tribe__Events__Pro__Main`, not the more guess-able
	 * `Tribe__Events_Pro__Main`. The single-underscore form does not
	 * exist anywhere in ECP's codebase; using it as the gate silently
	 * hides every ECP-only trigger/action/condition/loop-filter even
	 * when ECP is installed and active.
	 *
	 * @return bool
	 */
	public function ecp_active() {

		if ( array_key_exists( 'ecp', Has_Dependency_State::$test_override ) && null !== Has_Dependency_State::$test_override['ecp'] ) {
			return (bool) Has_Dependency_State::$test_override['ecp'];
		}

		return class_exists( '\Tribe__Events__Pro__Main' );
	}
}
