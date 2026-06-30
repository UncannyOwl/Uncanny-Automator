<?php
/**
 * Trigger Definition — static metadata carrier for build-time extraction.
 *
 * Declared by concrete triggers via `public static function definition()`.
 * Extracted at `composer post-autoload-dump` into
 * `vendor/composer/autoload_trigger_metadata.php` and consumed at runtime
 * by `Trigger_Metadata_Loader` to populate a registry stub + a lazy
 * `automator_parse_token_for_trigger_{code}` filter proxy.
 *
 * Only carries fields the registry stub needs. Hook name / priority /
 * accepted_args live in postmeta per-trigger-instance and are read by
 * `Trigger_Engine` directly — they do NOT belong on this object.
 *
 * @package Uncanny_Automator
 * @since   7.3
 */

namespace Uncanny_Automator\Recipe;

/**
 * Trigger Definition value object.
 *
 * Fluent builder so adding a field later is one method — zero changes to
 * existing `definition()` callers.
 *
 * As of 7.4, also carries the WP hook(s) the trigger listens on so the
 * engine can register them from code without consulting postmeta. The
 * pre-7.4 comment on this class said hooks "do not belong on this object"
 * — that was correct only while hooks were per-recipe-instance facts.
 * They are per-class facts; the per-instance store was an artefact of the
 * trait-era data model. Triggers whose hook string depends on user input
 * (e.g. Pro's `Add_Action_Trigger`) MUST NOT call `->hook()` — leaving
 * `$hooks` empty routes them through `Trigger_Query`'s postmeta path.
 *
 * @since 7.3
 */
final class Trigger_Definition {

	/**
	 * Unique trigger code (e.g. 'WPJMSUBMITJOB').
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Integration code (e.g. 'WPJM').
	 *
	 * @var string
	 */
	public $integration;

	/**
	 * Fully qualified class name of the concrete trigger this definition
	 * describes. Populated automatically by
	 * `Abstract_Trigger::new_definition()` (via `static::class`). The
	 * metadata cache consumer requires this to instantiate the trigger
	 * on demand.
	 *
	 * @var string
	 */
	public $class = '';

	/**
	 * Trigger type — 'user' or 'anonymous'.
	 *
	 * @var string
	 */
	public $trigger_type = 'user';

	/**
	 * Primary meta field code. Defaults to `$code` if not explicitly set.
	 *
	 * @var string
	 */
	public $trigger_meta = '';

	/**
	 * WP hooks this trigger listens on. Each entry is a tuple
	 * `[$hook_name, $priority, $accepted_args]`. Empty array means
	 * "fall back to the postmeta path in Trigger_Engine".
	 *
	 * The priority and accepted_args fields are ADVISORY — they
	 * populate the trigger's instance metadata (read by registry
	 * consumers via `get_action_priority()` / `get_action_args_count()`)
	 * but do NOT control the WP listener priority. The engine
	 * registers every hook at WP priority 10 with accepted_args=99
	 * via a single shared callback — see
	 * `Trigger_Engine::register_single_trigger()` for the rationale.
	 *
	 * @var array<int, array{0: string, 1: int, 2: int}>
	 */
	public $hooks = array();

	/**
	 * Declarative enqueue-gate spec, or null when the trigger opts out.
	 *
	 * When set, `Enqueue_Gate` vetoes this trigger's enqueue the instant a
	 * monitored hook fires if the value at hook-arg `arg_index` is not the key
	 * any live recipe watches (read from trigger-meta `option`) — unless that
	 * configured value is an `any` sentinel or a `{{token}}`. Compiled into the
	 * metadata cache via to_array() and consumed before `init` without ever
	 * instantiating the trigger.
	 *
	 * @var array{option: string, any: string[], arg_index: int}|null
	 */
	public $enqueue_gate = null;

	/**
	 * Private constructor — use create() as the entry point.
	 *
	 * @param string $code        Unique trigger code.
	 * @param string $integration Integration code.
	 */
	private function __construct( $code, $integration ) {
		$this->code        = $code;
		$this->integration = $integration;
	}

	/**
	 * Named constructor — the only way to create a definition.
	 *
	 * @param string $code        Unique trigger code.
	 * @param string $integration Integration code.
	 *
	 * @return self
	 */
	public static function create( $code, $integration ) {
		return new self( $code, $integration );
	}

	/**
	 * Set the trigger type.
	 *
	 * @param string $type 'user' or 'anonymous'.
	 *
	 * @return $this
	 */
	public function trigger_type( $type ) {
		$this->trigger_type = $type;
		return $this;
	}

	/**
	 * Set the primary meta field code.
	 *
	 * @param string $meta
	 *
	 * @return $this
	 */
	public function trigger_meta( $meta ) {
		$this->trigger_meta = $meta;
		return $this;
	}

	/**
	 * Register a WP hook this trigger should listen on. Chain to add more
	 * than one. The hook string is the class-level fact; per-recipe
	 * overrides are not supported.
	 *
	 * `$priority` and `$accepted_args` are ADVISORY — they populate the
	 * trigger's instance metadata (`$action_priority`, `$action_args_count`)
	 * which is read by registry consumers, but do NOT control the WP
	 * listener priority. `Trigger_Engine` registers every monitored hook
	 * at WP priority 10 with accepted_args=99 via a single shared
	 * callback (see `Trigger_Engine::register_single_trigger()` for the
	 * rationale — avoids closure-per-hook overhead with 200+ registered
	 * hooks). So `->hook( 'foo', 15, 4 )` records advisory metadata; the
	 * WP listener for 'foo' fires at priority 10 regardless.
	 *
	 * @param string $hook          WP hook name.
	 * @param int    $priority      Advisory instance metadata. Engine
	 *                              registers at WP priority 10 regardless.
	 * @param int    $accepted_args Advisory instance metadata. Engine
	 *                              receives up to 99 hook args regardless.
	 *
	 * @return $this
	 */
	public function hook( $hook, $priority = 10, $accepted_args = 1 ) {
		$this->hooks[] = array( (string) $hook, (int) $priority, (int) $accepted_args );
		return $this;
	}

	/**
	 * Declare a declarative enqueue gate for this trigger.
	 *
	 * High-frequency hooks (e.g. added_user_meta / updated_user_meta) fire once
	 * per write. Without a gate the engine enqueues on every fire, and when
	 * recipe parts aren't loaded yet each queued item costs a loopback HTTP
	 * self-request — dozens per request on a meta-heavy signup. The gate lets
	 * the engine skip the enqueue when no live recipe watches the fired value;
	 * it only ever vetoes when certain (sentinels and tokens fail open).
	 *
	 * @param string   $option    Trigger-meta key holding the watched value
	 *                            (e.g. 'UMETAKEY'). This is the key-to-match
	 *                            option, which may differ from trigger_meta().
	 * @param string[] $any       Configured values meaning "any" — cannot be
	 *                            pre-filtered, so the gate always allows them
	 *                            (e.g. array( '-1' ) or array( '-1', '' )).
	 * @param int      $arg_index Hook-arg index carrying the value to compare.
	 *                            Default 2 — the meta_key in *_user_meta hooks.
	 *
	 * @return $this
	 */
	public function enqueue_gate( $option, $any = array(), $arg_index = 2 ) {
		$this->enqueue_gate = array(
			'option'    => (string) $option,
			'any'       => (array) $any,
			'arg_index' => (int) $arg_index,
		);
		return $this;
	}

	/**
	 * Set the concrete trigger class FQCN. Normally populated automatically
	 * by `Abstract_Trigger::new_definition()` or Pro's equivalent helper;
	 * exposed here so callers can override or supply it explicitly.
	 *
	 * @param string $class
	 *
	 * @return $this
	 */
	public function for_class( $class ) {
		$this->class = $class;
		return $this;
	}

	/**
	 * Resolve trigger_meta — falls back to code when not explicitly set.
	 *
	 * @return string
	 */
	public function get_trigger_meta() {
		return '' === $this->trigger_meta ? $this->code : $this->trigger_meta;
	}

	/**
	 * Dump to an array suitable for the metadata cache file.
	 *
	 * Returns a COMPLETE entry — `class`, `code`, `integration`,
	 * `trigger_type`, `trigger_meta` — so the build script can emit the
	 * result directly into `autoload_trigger_metadata.php` without having
	 * to inject any field on the side. `class` is populated automatically
	 * when the definition is built through `Abstract_Trigger::new_definition()`;
	 * callers using `create()` directly must call `for_class()` or the
	 * loader will discard the entry at boot.
	 *
	 * @return array
	 */
	public function to_array() {
		$entry = array(
			'code'         => $this->code,
			'class'        => $this->class,
			'integration'  => $this->integration,
			'trigger_type' => $this->trigger_type,
			'trigger_meta' => $this->get_trigger_meta(),
			'hooks'        => $this->hooks,
		);

		// Emit only when declared so non-gated entries stay byte-identical.
		if ( null !== $this->enqueue_gate ) {
			$entry['enqueue_gate'] = $this->enqueue_gate;
		}

		return $entry;
	}
}
