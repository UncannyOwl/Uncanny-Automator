<?php
/**
 * Class - Triggers
 *
 * Has all the functionality to create Triggers
 *
 * @class   Triggers
 * @since   4.14
 * @version 4.14
 * @author  Ajay V.
 * @package Uncanny_Automator
 */


namespace Uncanny_Automator\Recipe;

use Exception;
use Uncanny_Automator\Token_Identifier_Partitioner;

/**
 * Abstract Triggers
 *
 * @package Uncanny_Automator
 */
abstract class Trigger {

	/**
	 * Trigger Setup. This trait handles trigger definitions.
	 */
	use Trigger_Setup;

	/**
	 * True while constructing via late_construct(). When set, the constructor
	 * skips the `automator_triggers` filter registration — the registry stub
	 * was already placed by Trigger_Metadata_Loader.
	 *
	 * Static because the gate is checked once per class during construction.
	 *
	 * @var bool
	 */
	protected static $deferred_construction = false;

	/**
	 * dependencies
	 *
	 * Use this variable for dependency injection
	 *
	 * @var mixed
	 */
	protected $dependencies;

	/**
	 * item_helpers
	 *
	 * @var object|null
	 */
	protected $item_helpers;

	/**
	 * Set item helpers.
	 *
	 * @param object $helpers The helper object to set.
	 * @return void
	 */
	protected function set_item_helpers( $helpers ) {
		$this->item_helpers = $helpers;
	}

	/**
	 * Get item helpers.
	 *
	 * @return object|null The helper object or null if not set.
	 */
	protected function get_item_helpers() {
		return $this->item_helpers ?? null;
	}

	/**
	 * Set multiple helpers from dependencies.
	 *
	 * @param array $dependencies Array of dependency objects.
	 * @return void
	 */
	protected function set_helpers_from_dependencies( $dependencies ) {
		if ( ! empty( $dependencies ) && isset( $dependencies[0] ) ) {
			$this->set_item_helpers( $dependencies[0] );
		}
	}

	/**
	 * user_id
	 *
	 * @var mixed
	 */
	protected $user_id;

	/**
	 * tokens
	 *
	 * @var array
	 */
	protected $tokens = array();

	/**
	 * recipe_log_id
	 *
	 * @var mixed
	 */
	protected $recipe_log_id;

	/**
	 * trigger
	 *
	 * @var mixed
	 */
	protected $trigger;

	/**
	 * hook_args
	 *
	 * @var mixed
	 */
	protected $hook_args;

	/**
	 * trigger_recipes
	 *
	 * @var mixed
	 */
	protected $trigger_recipes;

	/**
	 * recipe_id
	 *
	 * @var mixed
	 */
	protected $recipe_id;

	/**
	 * recipe
	 *
	 * @var mixed
	 */
	protected $recipe;

	/**
	 * trigger_log_entry
	 *
	 * @var mixed
	 */
	protected $trigger_log_entry;

	/**
	 * trigger_log_id
	 *
	 * @var mixed
	 */
	protected $trigger_log_id;

	/**
	 * token_values
	 *
	 * @var mixed
	 */
	protected $token_values;

	/**
	 * trigger_records
	 *
	 * @var mixed
	 */
	protected $trigger_records;

	/**
	 * Token definitions for the current trigger fire, with tokenIdentifier and
	 * tokenType defaults applied. Populated by process() before save_tokens()
	 * runs; read by save_tokens() to bucket runtime values by identifier.
	 *
	 * @var array
	 */
	protected $token_definitions = array();

	/**
	 * run_number
	 *
	 * @var mixed
	 */
	protected $run_number;

	/**
	 * is_login_required
	 *
	 * @var mixed
	 */
	protected $is_login_required = false;

	/**
	 * __construct
	 *
	 * @param mixed $dependencies
	 *
	 * @return void
	 */
	final public function __construct( ...$dependencies ) {

		// Prevent double-instantiation — Free and Pro may both try to load the same trigger.
		static $instantiated = array();
		$fqcn                = static::class;
		if ( isset( $instantiated[ $fqcn ] ) ) {
			return;
		}
		$instantiated[ $fqcn ] = true;

		$this->dependencies = $dependencies;

		// Automatically set up helpers from dependencies
		$this->set_helpers_from_dependencies( $this->dependencies );

		// Apply identity fields from definition() — integration, code,
		// trigger_meta, trigger_type — BEFORE setup_trigger() runs so the
		// concrete class can drop the redundant setter calls and still
		// build sentences that reference get_trigger_meta() etc. When a
		// trigger does not declare definition(), nothing is applied and
		// setup_trigger() continues to be the source as it is today.
		$this->apply_definition();

		$this->setup_trigger();

		// Deferred mode: the registry stub is already in place via
		// Trigger_Metadata_Loader, AND the loader already registered a lazy
		// proxy on `automator_parse_token_for_trigger_{code}` at priority 20.
		// Running register_token_filters() here would bind a SECOND callback
		// at the same priority — on every subsequent filter apply, both the
		// proxy (which delegates to fetch_token_data) AND the direct binding
		// (which also calls fetch_token_data with the proxy's return as
		// $value) would fire. Any trigger override that conditions output on
		// the incoming $value would silently produce wrong data.
		//
		// The `automator_maybe_trigger_{code}_tokens` filter that
		// register_token_filters() also adds is editor-only and the editor
		// path uses should_load_all() → eager construction → this branch
		// registers it normally.
		if ( false === static::$deferred_construction ) {
			add_filter( 'automator_triggers', array( $this, 'register_trigger' ) );
			$this->register_token_filters();
		}
	}

	/**
	 * Declare the static metadata needed to register this trigger without
	 * constructing it. Override in concrete triggers to enable lazy loading.
	 *
	 * Returning null keeps the eager path — the integration constructs the
	 * trigger at boot as it does today.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition|null
	 */
	public static function definition() {
		return null;
	}

	/**
	 * Factory for Trigger_Definition — so concrete triggers don't need a
	 * `use Uncanny_Automator\Recipe\Trigger_Definition;` at the top of every
	 * file just to return one from definition().
	 *
	 * Populates the definition's `class` field via late static binding so
	 * the returned object is a complete metadata entry (ready for
	 * `to_array()` to be dumped straight into the cache file).
	 *
	 * Example:
	 *   public static function definition() {
	 *       return self::new_definition( self::TRIGGER_CODE, 'WPJM' )
	 *           ->trigger_meta( self::TRIGGER_META );
	 *   }
	 *
	 * @param string $code        Unique trigger code.
	 * @param string $integration Integration code.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	protected static function new_definition( $code, $integration ) {
		return Trigger_Definition::create( $code, $integration )->for_class( static::class );
	}

	/**
	 * Apply the identity fields declared by `definition()` so concrete
	 * triggers can drop the redundant setter calls from setup_trigger().
	 *
	 * Runs before setup_trigger() so sentence builders that reference
	 * `get_trigger_meta()` / `get_trigger_code()` still resolve correctly.
	 * No-op when the trigger returns null from definition().
	 *
	 * @return void
	 */
	protected function apply_definition() {

		$definition = static::definition();

		if ( null === $definition ) {
			return;
		}

		$this->set_integration( $definition->integration );
		$this->set_trigger_code( $definition->code );
		$this->set_trigger_meta( $definition->get_trigger_meta() );
		$this->set_trigger_type( $definition->trigger_type );

		foreach ( $definition->hooks as $hook ) {
			list( $name, $priority, $accepted_args ) = $hook;
			$this->add_action( $name, $priority, $accepted_args );
		}
	}

	/**
	 * Construct the trigger for deferred execution.
	 *
	 * Invoked by the lazy proxy when `Trigger_Queue` drains a queued event
	 * or when the `automator_parse_token_for_trigger_{code}` filter fires.
	 * The constructor gate observes `static::$deferred_construction` and
	 * skips the `automator_triggers` filter registration so the registry
	 * stub stays authoritative.
	 *
	 * @param mixed ...$dependencies Same signature as the public constructor.
	 *
	 * @return static
	 */
	public static function late_construct( ...$dependencies ) {

		static::$deferred_construction = true;

		try {
			$instance = new static( ...$dependencies );
		} finally {
			// Always reset even if the constructor throws — a leaked flag
			// would make subsequent eager constructions silently skip the
			// filter registration.
			static::$deferred_construction = false;
		}

		return $instance;
	}

	/**
	 * requirements_met
	 *
	 * Override this method if the trigger has any pre-requisites
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return true;
	}

	/**
	 * register_trigger
	 *
	 * @param mixed $triggers
	 *
	 * @return array
	 */
	public function register_trigger( $triggers ) {

		$requirement_met = $this->requirements_met();
		$requirement_met = apply_filters( 'automator_item_requirement_meta', $requirement_met, $triggers );
		if ( ! $requirement_met ) {
			return $triggers;
		}

		$trigger = array(
			'author'              => $this->get_author(), // author of the trigger.
			'support_link'        => $this->get_support_link(), // hyperlink to support page.
			'type'                => $this->get_trigger_type(), // user|anonymous. user by default.
			'is_pro'              => $this->get_is_pro(), // free or pro trigger.
			'is_elite'            => $this->get_is_elite(), // elite trigger.
			'is_deprecated'       => $this->get_is_deprecated(), // whether trigger is deprecated.
			'integration'         => $this->get_integration(), // trigger the integration belongs to.
			'code'                => $this->get_code(), // unique trigger code.
			'meta_code'           => $this->get_trigger_meta(), // primary meta field code.
			'sentence'            => $this->get_sentence(), // sentence to show in active state.
			'select_option_name'  => $this->get_readable_sentence(), // sentence to show in non-active state.
			'action'              => $this->get_action(), //  trigger fire at this do_action().
			'priority'            => $this->get_action_priority(), // priority of the add_action().
			'accepted_args'       => $this->get_action_args_count(), // accepted args by the add_action().
			'token_parser'        => $this->get_token_parser(), // v3.0, Pass a function to parse tokens.
			'validation_function' => array( $this, 'validate_hook' ), // function to call for add_action().
			'uses_api'            => $this->get_uses_api(),
			'options_callback'    => array( $this, 'load_options' ),
			'loopable_tokens'     => $this->get_loopable_tokens(), //@since 5.10
		);

		if ( ! empty( $this->get_buttons() ) ) {
			$trigger['buttons'] = $this->get_buttons();
		}

		if ( ! empty( $this->get_inline_css() ) ) {
			$trigger['inline_css'] = $this->get_inline_css();
		}

		if ( null !== $this->get_can_log_in_new_user() ) {
			$trigger['can_log_in_new_user'] = $this->get_can_log_in_new_user();
		}

		// Extract manifest data if trait is used
		if ( $this->uses_item_manifest_trait() && is_callable( array( $this, 'extract_item_manifest_data' ) ) ) {
			$manifest = call_user_func( array( $this, 'extract_item_manifest_data' ) );
			if ( ! empty( $manifest ) ) {
				$trigger['manifest'] = $manifest;
			}
		}

		$trigger = apply_filters( 'automator_register_trigger', $trigger );

		$triggers[ $this->get_code() ] = $trigger;

		return $triggers;
	}

	/**
	 * Check if trigger uses Item_Manifest trait.
	 *
	 * @return bool True if trait is used
	 */
	private function uses_item_manifest_trait() {
		$traits = class_uses( get_class( $this ) );
		return in_array( 'Uncanny_Automator\Item_Manifest', $traits, true );
	}

	/**
	 * load_options
	 *
	 * Override this method to display multi-page options or have more granular control over the sentence/fields
	 *
	 * @return array
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->get_trigger_meta() => $this->options(),
			),
		);
	}

	/**
	 * options
	 *
	 * Override this method to display a default option group
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * set_user_id
	 *
	 * @param mixed $user_id
	 *
	 * @return void
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
	}

	/**
	 * register_token_filters
	 *
	 * @return void
	 */
	public function register_token_filters() {

		$integration_trigger_string = strtolower( $this->get_integration() . '_' . $this->get_code() );

		$filter = sprintf(
			'automator_maybe_trigger_%s_tokens',
			$integration_trigger_string
		);

		add_filter( $filter, array( $this, 'register_tokens' ), 10, 2 );

		$filter = sprintf(
			'automator_parse_token_for_trigger_%s',
			$integration_trigger_string
		);

		add_filter( $filter, array( $this, 'fetch_token_data' ), 20, 6 );
	}

	/**
	 * register_tokens
	 *
	 * Override this method if you want to add recipe-specific tokens such as form fields etc.
	 *
	 * @param mixed $tokens
	 * @param mixed $args
	 *
	 * @return array
	 */
	public function register_tokens( $tokens, $trigger ) {

		$trigger['meta'] = $trigger['triggers_meta'];

		// Pass the accumulated filter-chain tokens through as $existing so
		// legacy define_tokens() impls that append with `$tokens[] = ...`
		// don't clobber prior filter contributions when this method loops
		// their return back into the outer $tokens array.
		//
		// Read-only on the $this->token_definitions cache. The cache lives
		// on $this and is populated exclusively by process() because trigger
		// objects are singletons in the registry — caching from this filter
		// callback would let one row's definitions leak into another row's
		// runtime fire.
		return $this->resolve_token_definitions( $trigger, $tokens );
	}

	/**
	 * Resolve the full token definition list for a trigger row, applying the
	 * default tokenIdentifier (trigger code) and tokenType ('text') to any
	 * definition that omits them.
	 *
	 * Shared by register_tokens() (recipe-rendering filter callback) and
	 * process() (runtime fire path):
	 *  - register_tokens() passes the current filter-chain tokens as
	 *    $existing so legacy define_tokens() impls that append don't lose
	 *    prior-filter contributions.
	 *  - process() omits the arg so $this->token_definitions holds only
	 *    this trigger's own contribution, isolated for partitioning at
	 *    save_tokens() time.
	 *
	 * @param array $trigger
	 * @param array $existing
	 *
	 * @return array
	 */
	protected function resolve_token_definitions( $trigger, $existing = array() ) {

		$defined = $this->define_tokens( $trigger, $existing );

		// Start from $existing so return-only define_tokens() impls
		// (`return array( $a, $b );` — common in AIOSEO, EDD, Fluent
		// Community, LearnDash, Rank Math et al.) don't drop prior filter
		// contributions. The loop then layers $defined on top:
		//  - append-style impls already contain $existing inside $defined,
		//    so the loop overwrites prior entries with themselves
		//    (idempotent after defaulting is re-applied).
		//  - return-only impls return just their own tokens; the loop
		//    adds them on top of $existing without clobbering siblings
		//    that live under different keys.
		// Same-key collisions still lose prior entries — unchanged from
		// pre-patch behaviour and no worse than the old single-row save.
		$out = $existing;

		foreach ( $defined as $key => $token ) {
			if ( empty( $token['tokenIdentifier'] ) ) {
				$token['tokenIdentifier'] = $this->get_code();
			}
			if ( empty( $token['tokenType'] ) ) {
				$token['tokenType'] = 'text';
			}
			$out[ $key ] = $token;
		}

		return $out;
	}

	/**
	 * define_tokens
	 *
	 * Override this method if you want to add recipe-specific tokens such as form fields etc.
	 *
	 * @param mixed $tokens
	 * @param mixed $args
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return $tokens;
	}

	/**
	 * This function will run for each trigger instance in each recipe;
	 *
	 * @param mixed ...$args
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function validate_hook( ...$hook_args ) {

		// In case someone wants to pass alter data in hook args, they can do it through this variable
		$this->hook_args = $hook_args;

		/**
		 * Check if user is logged in.
		 */
		if ( 'user' === $this->get_trigger_type() && $this->is_login_required && ! is_user_logged_in() ) {
			return false;
		}

		/**
		 * Populate user_id using WordPress function.
		 */
		$this->set_user_id( get_current_user_id() );

		/**
		 * Get all recipes with the current trigger.
		 */
		$this->trigger_recipes = Automator()->get->recipes_from_trigger_code( $this->get_trigger_code() );

		foreach ( $this->trigger_recipes as $recipe_id => $recipe ) {

			// In case someone wants to pass alter recipe_id or recipe objects, they can do it through these variables
			$this->recipe_id = $recipe_id;
			$this->recipe    = $recipe;

			// Validate the recipe
			$this->validate_recipe( $this->recipe_id, $this->recipe, $this->hook_args );

		}

		return true;
	}

	/**
	 * validate_recipe
	 *
	 * @param mixed $recipe_id
	 * @param mixed $recipe
	 * @param mixed $hook_args
	 *
	 * @return void
	 */
	public function validate_recipe( $recipe_id, $recipe, $hook_args ) {

		foreach ( $recipe['triggers'] as $trigger ) {

			// In case someone wants to pass alter trigger data, they can do it through this variable
			$this->trigger = $trigger;

			// Validate trigger
			$this->validate_trigger( $this->recipe_id, $this->trigger, $this->hook_args );

		}
	}

	/**
	 * validate_trigger
	 *
	 * @param mixed $recipe_id
	 * @param mixed $trigger
	 * @param mixed $hook_args
	 *
	 * @return void
	 */
	public function validate_trigger( $recipe_id, $trigger, $hook_args ) {

		$process_further = $this->validate( $this->trigger, $this->hook_args );

		if ( ! $process_further ) {
			return;
		}

		try {
			$this->process( $this->recipe_id, $this->trigger, $this->hook_args );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch

		}
	}


	/**
	 * validate
	 *
	 * @param mixed $trigger
	 * @param mixed $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return true;
	}

	/**
	 * Processes the trigger.
	 *
	 * @since 6.7.0
	 *        Used Automator()->is_recipe_throttled() to check if the recipe is throttled. Early return if true to avoid creating a recipe log entry.
	 *
	 * @param mixed $recipe_id
	 * @param mixed $trigger
	 * @param mixed $hook_args
	 *
	 * @return void
	 */
	protected function process( $recipe_id, $trigger, $hook_args ) {

		if ( Automator()->is_recipe_throttled( absint( $recipe_id ), absint( $this->user_id ) ) ) {
			return;
		}

		// Single call replaces maybe_create_recipe_log_entry + maybe_create_trigger_log_entry.
		$result = Automator()->recipe_runner->process_trigger(
			absint( $this->recipe_id ),
			absint( $this->user_id ),
			$this->trigger
		);

		$this->recipe_log_id     = $result['recipe_log_id'];
		$this->trigger_log_entry = $result['trigger_log_id'];
		$this->run_number        = $result['run_number'];
		$this->trigger_log_id    = $result['trigger_log_id'];

		$this->trigger_records = array(
			'code'           => $this->get_code(),
			'user_id'        => $this->user_id,
			'trigger_id'     => (int) $this->trigger['ID'],
			'recipe_id'      => $this->recipe_id,
			'trigger_log_id' => $this->trigger_log_entry,
			'recipe_log_id'  => $this->recipe_log_id,
			'run_number'     => (int) Automator()->get->next_run_number( $this->recipe_id, $this->user_id, true ),
			'meta'           => $this->get_trigger_meta(),
			'get_trigger_id' => $this->trigger_log_entry,
			'engine'         => 'recipe_runner',
		);

		// Populate the token-definitions cache for save_tokens() to read.
		// Always re-resolve here, scoped to the row that's actually firing:
		// trigger objects are singletons in Automator's registry, so caching
		// across calls would let one row's definitions leak into another row's
		// fire. Empty $existing because process() needs only this trigger's
		// own contribution — isolated from filter-chain accumulation.
		//
		// Normalize triggers_meta -> meta to match register_tokens(): the recipe
		// row stores selected option_codes under triggers_meta but define_tokens()
		// reads $trigger['meta'][$option_code]. Without this swap, dynamic
		// per-field token discovery sees an empty form_id and emits no tokens.
		$trigger_for_defs         = $this->trigger;
		$trigger_for_defs['meta'] = $trigger_for_defs['triggers_meta'] ?? ( $trigger_for_defs['meta'] ?? array() );
		$this->token_definitions  = $this->resolve_token_definitions( $trigger_for_defs );

		// Token hydration — trigger-specific, stays here.
		$this->token_values = $this->hydrate_tokens( $this->trigger, $this->hook_args );
		$this->save_tokens( $this->get_code(), $this->token_values );

		$do_action = array(
			'trigger_entry' => $this->trigger,
			'entry_args'    => $this->trigger_records,
			'trigger_args'  => $this->hook_args,
		);

		do_action( 'automator_before_trigger_completed', $do_action, $this );

		$process_further = apply_filters( 'automator_trigger_should_complete', true, $do_action, $this );

		if ( $process_further ) {
			$this->register_loopable_trigger_tokens_hooks( $do_action );
			do_action( 'automator_loopable_token_hydrate', $do_action['entry_args'], $do_action['trigger_args'] );
			// Direct Recipe_Runner call — bypasses facade entirely.
			Automator()->recipe_runner->complete_trigger( $this->trigger_records );
		}

		do_action( 'automator_after_maybe_trigger_complete', $do_action, $this );
	}

	/**
	 * Register the loopable-token filter/action hooks for this trigger.
	 *
	 * Source `$loopable_tokens` from the constructed trigger instance
	 * rather than the registry entry. Eager-registered triggers carry the
	 * field on the registry array, but lazy triggers don't — their stub
	 * (`Trigger_Metadata_Loader::register_stub()`) only emits
	 * `code/integration/meta_code/type/validation_function`, and the
	 * `automator_triggers` filter that would have populated the rest is
	 * intentionally skipped in deferred-construction mode. Reading from
	 * `$this->get_loopable_tokens()` works for both paths because by the
	 * time `process()` reaches here the trigger is fully constructed.
	 *
	 * The `$trigger` array is still passed to `register_hooks()` /
	 * `set_trigger()` because the loopable token classes only read
	 * `$trigger['code']` from it — which the lazy stub does carry.
	 *
	 * @param mixed $args
	 * @return void
	 */
	private function register_loopable_trigger_tokens_hooks( $args ) {

		$trigger_code    = $args['entry_args']['code'] ?? null;
		$trigger         = Automator()->get_trigger( $trigger_code );
		$loopable_tokens = $this->get_loopable_tokens();

		foreach ( (array) $loopable_tokens as $token_class ) {
			if ( is_string( $token_class ) && class_exists( $token_class ) ) {
				$token_class = new $token_class( $this->trigger['ID'] );
				$token_class->register_hooks( $trigger );
				$token_class->set_trigger( $trigger );
			}
		}
	}

	/**
	 * hydrate_tokens
	 *
	 * @param mixed $completed_trigger
	 * @param mixed $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		return array();
	}

	/**
	 * Persist hydrated token values, bucketed by tokenIdentifier so existing
	 * recipe references resolve regardless of whether the token uses the
	 * default trigger-code identifier or a custom one.
	 *
	 * @param string $code
	 * @param array  $values
	 *
	 * @return void
	 */
	public function save_tokens( $code, $values ) {

		// Defensive fallback: if token_definitions never got populated but we
		// have values to persist, fall back to the pre-patch single-row save
		// so data isn't silently dropped. This is a misuse path — a subclass
		// overrode process() without populating the cache, OR save_tokens()
		// was called outside process(). Surface it loudly so it gets fixed.
		if ( empty( $this->token_definitions ) && ! empty( $values ) ) {

			_doing_it_wrong(
				__METHOD__,
				sprintf(
					'save_tokens() called with an empty $token_definitions cache for trigger code "%s". '
					. 'This usually means process() was overridden without calling parent::process() '
					. 'or without populating $this->token_definitions. Any tokens declared with a '
					. 'custom tokenIdentifier will fail to resolve for this trigger fire. Falling '
					. 'back to the pre-patch single-row save under the trigger code so data is not lost.',
					esc_html( $code )
				),
				'7.3'
			);

			Automator()->db->token->save( $code, wp_json_encode( $values ), $this->trigger_records );
			return;
		}

		Token_Identifier_Partitioner::partition_and_save(
			$this->token_definitions,
			$values,
			$code,
			$this->trigger_records
		);
	}

	/**
	 * Fetches specific token value from uap_trigger_log_meta.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_arg
	 *
	 * @return mixed
	 */
	public function fetch_token_data( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_arg ) {

		if ( empty( $trigger_data ) || ! isset( $trigger_data[0] ) ) {
			return $value;
		}

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		list( $recipe_id, $token_identifier, $token_id ) = $pieces;

		$data = Automator()->db->token->get( $token_identifier, $replace_arg );

		$data = is_array( $data ) ? $data : json_decode( $data, true );
		if ( isset( $data[ $token_id ] ) ) {
			return $data[ $token_id ];
		}

		return $value;
	}

	/**
	 * @param $is_login_required
	 */
	public function set_is_login_required( $is_login_required ) {
		$this->is_login_required = $is_login_required;
	}

	/**
	 * @param mixed ...$args
	 *
	 * @return bool
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function is_user_logged_in_required( ...$args ) {
		return $this->is_login_required;
	}
}
