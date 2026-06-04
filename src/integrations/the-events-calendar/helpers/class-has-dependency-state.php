<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Side-channel state holder for the `Has_Dependency` trait's test override.
 *
 * **Why this exists**: PHP trait `static` properties are per-consuming-class,
 * not shared across consumers. Setting `Has_Dependency::$test_override` (the
 * trait itself) is not legal, and setting it on one consuming class
 * (e.g. `ETP_USER_JOINS_WAITLIST::$test_override = …`) does not propagate
 * to sibling consumers. Tests need a single global slot they can write
 * once to gate every consumer at the same time, so we route the override
 * through this dedicated state class instead of through a trait static.
 *
 * Production code never reads or writes this class — only tests do.
 *
 * @package Uncanny_Automator
 */
final class Has_Dependency_State {

	/**
	 * Per-gate overrides for tests. Keys: 'et', 'etp', 'ecp'.
	 * Values: bool to force, or null to defer to `class_exists()`.
	 *
	 * @var array<string,bool|null>
	 */
	public static $test_override = array();
}
