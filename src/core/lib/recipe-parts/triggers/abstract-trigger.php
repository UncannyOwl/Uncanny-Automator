<?php
/**
 * Class - Triggers
 *
 * Has all the functionality to create Triggers
 *
 * @class   Triggers
 * @since   4.14
 * @version 4.14
 * @package Uncanny_Automator
 * @author  Ajay V.
 */


namespace Uncanny_Automator\Recipe;

use Exception;

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
	 * dependencies
	 *
	 * Use this variable for dependency injection
	 *
	 * @var mixed
	 */
	protected $dependencies;

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
	protected $is_login_required;

	/**
	 * __construct
	 *
	 * @param  mixed $dependencies
	 * @return void
	 */
	final public function __construct( ...$dependencies ) {

		$this->dependencies = $dependencies;

		$this->setup_trigger();

		add_filter( 'automator_triggers', array( $this, 'register_trigger' ) );

		$this->register_token_filters();
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
	 * @param  mixed $triggers
	 * @return array
	 */
	public function register_trigger( $triggers ) {

		if ( ! $this->requirements_met() ) {
			return $triggers;
		}

		$trigger = array(
			'author'              => $this->get_author(), // author of the trigger.
			'support_link'        => $this->get_support_link(), // hyperlink to support page.
			'type'                => $this->get_trigger_type(), // user|anonymous. user by default.
			'is_pro'              => $this->get_is_pro(), // free or pro trigger.
			'is_deprecated'       => $this->get_is_deprecated(), // whether trigger is deprecated.
			'integration'         => $this->get_integration(), // trigger the integration belongs to.
			'code'                => $this->get_code(), // unique trigger code.
			'sentence'            => $this->get_sentence(), // sentence to show in active state.
			'select_option_name'  => $this->get_readable_sentence(), // sentence to show in non-active state.
			'action'              => $this->get_action(), //  trigger fire at this do_action().
			'priority'            => $this->get_action_priority(), // priority of the add_action().
			'accepted_args'       => $this->get_action_args_count(), // accepted args by the add_action().
			'token_parser'        => $this->get_token_parser(), // v3.0, Pass a function to parse tokens.
			'validation_function' => array( $this, 'validate_hook' ), // function to call for add_action().
			'uses_api'            => $this->get_uses_api(),
			'options_callback'    => array( $this, 'load_options' ),
		);

		$trigger = apply_filters( 'automator_register_trigger', $trigger );

		$triggers[ $this->get_code() ] = $trigger;
		return $triggers;
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
	 * @return void
	 */
	public function options() {
		return array();
	}

	/**
	 * set_user_id
	 *
	 * @param  mixed $user_id
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
	 * @param  mixed $tokens
	 * @param  mixed $args
	 * @return array
	 */
	public function register_tokens( $tokens, $trigger ) {

		$trigger['meta'] = $trigger['triggers_meta'];

		$additional_tokens = $this->define_tokens( $trigger, $tokens );

		foreach ( $additional_tokens as $key => $additonal_token ) {
			if ( empty( $additonal_token['tokenIdentifier'] ) ) {
				$additonal_token['tokenIdentifier'] = $this->get_code();
			}

			if ( empty( $additonal_token['tokenType'] ) ) {
				$additonal_token['tokenType'] = 'text';
			}

			$tokens[ $key ] = $additonal_token;
		}

		return $tokens;
	}

	/**
	 * define_tokens
	 *
	 * Override this method if you want to add recipe-specific tokens such as form fields etc.
	 *
	 * @param  mixed $tokens
	 * @param  mixed $args
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
		if ( 'user' === $this->get_trigger_type() && ! is_user_logged_in() ) {
			return false;
		}

		/**
		 * Populate user_id using WordPress function.
		 */
		$this->set_user_id( get_current_user_id() );

		/**
		 * GEt all recipes with the current tirgger
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
	 * @param  mixed $recipe_id
	 * @param  mixed $recipe
	 * @param  mixed $hook_args
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
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger
	 * @param  mixed $hook_args
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
	 * @param  mixed $trigger
	 * @param  mixed $hook_args
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return true;
	}

	/**
	 * process
	 *
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger
	 * @param  mixed $hook_args
	 * @return void
	 */
	protected function process( $recipe_id, $trigger, $hook_args ) {

		$this->recipe_log_id = $this->maybe_create_recipe_log_entry( $this->recipe_id );

		$this->trigger_log_entry = $this->maybe_create_trigger_log_entry( $this->recipe_id, $this->recipe_log_id, $this->trigger );

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
		);

		$this->token_values = $this->hydrate_tokens( $this->trigger, $this->hook_args );
		$this->save_tokens( $this->trigger, $this->trigger_records, $this->token_values );

		$do_action = array(
			'trigger_entry' => $this->trigger,
			'entry_args'    => $this->trigger_records,
			'trigger_args'  => $this->hook_args,
		);

		do_action( 'automator_before_trigger_completed', $do_action, $this );

		$process_further = apply_filters( 'automator_trigger_should_complete', true, $do_action, $this );

		if ( $process_further ) {
			Automator()->complete->trigger( $this->trigger_records );
		}

		do_action( 'automator_after_maybe_trigger_complete', $do_action, $this );

	}

	/**
	 * maybe_create_recipe_log_entry
	 *
	 * @param  mixed $recipe_id
	 * @return int
	 */
	private function maybe_create_recipe_log_entry( $recipe_id ) {

		if ( Automator()->is_recipe_completed( $this->recipe_id, $this->user_id ) ) {
			throw new Exception( 'Recipe has already been completed' );
		}

		$result = Automator()->process->user->maybe_create_recipe_log_entry( $this->recipe_id, $this->user_id );

		if ( empty( $result['recipe_log_id'] ) ) {
			throw new Exception( 'Unable to create recipe log entry' );
		}

		return absint( $result['recipe_log_id'] );
	}

	/**
	 * maybe_create_trigger_log_entry
	 *
	 * @param  mixed $recipe_id
	 * @param  mixed $recipe_log_id
	 * @param  mixed $trigger
	 * @return int
	 */
	private function maybe_create_trigger_log_entry( $recipe_id, $recipe_log_id, $trigger ) {

		$result = Automator()->process->user->maybe_get_trigger_id( $this->user_id, $trigger['ID'], $this->recipe_id, $recipe_log_id );

		if ( empty( $result['trigger_log_id'] ) ) {
			throw new Exception( 'Unable to create trigger log entry' );
		}

		$this->trigger_log_id = $result['trigger_log_id'];

		$times_completed_args = array(
			'recipe_id'      => $this->recipe_id,
			'trigger_id'     => $this->trigger['ID'],
			'trigger'        => $this->trigger,
			'user_id'        => $this->user_id,
			'recipe_log_id'  => $this->recipe_log_id,
			'trigger_log_id' => $this->trigger_log_id,
			'is_signed_in'   => is_user_logged_in(),
		);

		$result = Automator()->process->user->maybe_trigger_num_times_completed( $times_completed_args );

		if ( ! isset( $result['run_number'] ) ) {
			throw new Exception( 'Number of times condition is not completed' );
		}

		$this->run_number = $result['run_number'];

		return absint( $this->trigger_log_id );
	}

	/**
	 * hydrate_tokens
	 *
	 * @param  mixed $completed_trigger
	 * @param  mixed $hook_args
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		return array();
	}

	/**
	 * save_tokens
	 *
	 * @param  mixed $trigger
	 * @param  mixed $trigger_records
	 * @param  mixed $token_values
	 * @return void
	 */
	public function save_tokens( $trigger, $trigger_records, $token_values ) {

		Automator()->db->token->save(
			$this->get_code(),
			wp_json_encode( $this->token_values ),
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
