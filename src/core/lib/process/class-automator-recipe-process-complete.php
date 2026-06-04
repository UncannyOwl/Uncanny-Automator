<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Log_Properties;
use Uncanny_Automator\App\Recipe_Runner\Stages\Trigger_Complete_Stage as Api_Trigger_Complete;
use Uncanny_Automator\App\Recipe_Runner\Stages\Action_Run_Stage as Api_Action_Run;
use Uncanny_Automator\App\Recipe_Runner\Stages\Recipe_Complete_Stage as Api_Recipe_Complete;

/**
 * Class Automator_Recipe_Process_Complete
 *
 * Facade that delegates to Core API stages for recipe completion processing.
 * Pro extends this class via inheritance — all public methods must remain.
 *
 * @package Uncanny_Automator
 */
class Automator_Recipe_Process_Complete {

	use Log_Properties;

	/**
	 * @var
	 */
	public static $instance;
	/**
	 * @var $this
	 */
	public $user;

	/**
	 * @var Automator_Pro_Recipe_Process_Complete
	 */
	public $anon;

	/**
	 * @var Api_Trigger_Complete
	 */
	protected $trigger_complete;

	/**
	 * @var Api_Action_Run
	 */
	protected $action_run;

	/**
	 * @var Api_Recipe_Complete
	 */
	protected $recipe_complete;

	/**
	 * Automator_Recipe_Process constructor.
	 *
	 * Services are lazy-initialized via getters because Pro subclasses
	 * override this constructor without calling parent::__construct().
	 */
	public function __construct() {
		$this->user = $this;
		// Intentionally empty — services are lazy-loaded in getters.
	}

	/**
	 * Get the Recipe_Runner singleton if available.
	 *
	 * @return \Uncanny_Automator\App\Recipe_Runner\Recipe_Runner|null
	 */
	protected function get_recipe_runner() {
		if ( function_exists( 'Automator' ) && null !== Automator()->recipe_runner ) {
			return Automator()->recipe_runner;
		}
		return null;
	}

	/**
	 * @return Api_Action_Run
	 */
	protected function get_action_run() {
		if ( null === $this->action_run ) {
			$runner           = $this->get_recipe_runner();
			$this->action_run = null !== $runner ? $runner->action_run() : new Api_Action_Run( $this->get_recipe_complete() );
		}
		return $this->action_run;
	}

	/**
	 * @return Api_Trigger_Complete
	 */
	protected function get_trigger_complete() {
		if ( null === $this->trigger_complete ) {
			$runner                 = $this->get_recipe_runner();
			$this->trigger_complete = null !== $runner ? $runner->trigger_complete() : new Api_Trigger_Complete( $this->get_action_run() );
		}
		return $this->trigger_complete;
	}

	/**
	 * @return Api_Recipe_Complete
	 */
	protected function get_recipe_complete() {
		if ( null === $this->recipe_complete ) {
			$runner                = $this->get_recipe_runner();
			$this->recipe_complete = null !== $runner ? $runner->recipe_complete() : new Api_Recipe_Complete();
		}
		return $this->recipe_complete;
	}

	/**
	 * @return Automator_Recipe_Process_Complete
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Complete the trigger for the user
	 *
	 * @param array $args
	 *
	 * @return null
	 */
	public function trigger( $args = array() ) {
		// Route through Recipe_Runner so stages 3-5 execute via execute_stages_from().
		$runner = $this->get_recipe_runner();
		if ( null !== $runner ) {
			return $runner->complete_trigger( (array) $args );
		}
		// Fallback if runner not available.
		return $this->get_trigger_complete()->complete_trigger( (array) $args );
	}

	/**
	 * Adds a backtrace property to all triggers.
	 *
	 * @param mixed $args - Automator process args.
	 *
	 * @return void
	 */
	public function add_backtrace_property( $args ) {
		$this->get_trigger_complete()->add_backtrace_property( (array) $args );
	}

	/**
	 * Are all triggers in the recipe completed
	 *
	 * @param int $recipe_id null||int
	 * @param int $user_id null||int
	 * @param int $recipe_log_id null||int
	 *
	 * @param array $args
	 *
	 * @return bool|null
	 */
	public function triggers_completed( $recipe_id = 0, $user_id = 0, $recipe_log_id = 0, $args = array() ) {
		return $this->get_trigger_complete()->triggers_completed( (int) $recipe_id, (int) $user_id, (int) $recipe_log_id, $args );
	}

	/**
	 * Check if "Any" option is selected for triggers
	 *
	 * @param $recipe_id
	 *
	 * @return bool
	 */
	public function is_any_trigger_option_set( $recipe_id ) {
		return $this->get_trigger_complete()->is_any_trigger_option_set( (int) $recipe_id );
	}

	/**
	 * @param $statuses
	 *
	 * @return bool
	 */
	public function are_all_recipe_triggers_completed( $statuses ) {
		return $this->get_trigger_complete()->are_all_recipe_triggers_completed( $statuses );
	}

	/**
	 * @param $statuses
	 *
	 * @return bool
	 */
	public function is_any_recipe_trigger_completed( $statuses ) {
		return $this->get_trigger_complete()->is_any_recipe_trigger_completed( $statuses );
	}

	/**
	 * Complete all actions in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return bool
	 */
	public function complete_actions( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {
		return $this->get_action_run()->complete_actions( (int) $recipe_id, (int) $user_id, (int) $recipe_log_id, $args );
	}

	/**
	 * Individually complete the action.
	 *
	 * @param mixed[] $action_data
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param int $recipe_log_id
	 * @param array $args
	 */
	public function complete_action( $action_data, $recipe_id, $user_id, $recipe_log_id, $args ) {
		return $this->get_action_run()->complete_action( $action_data, (int) $recipe_id, (int) $user_id, (int) $recipe_log_id, $args );
	}

	/**
	 * Creates error property for stack trace.
	 *
	 * @param string $stack_trace
	 *
	 * @return void
	 */
	protected function create_error_property( $stack_trace ) {
		$this->set_log_properties(
			array(
				'type'       => 'code',
				'label'      => esc_html_x( 'Stacktrace', 'Uncanny Automator', 'uncanny-automator' ),
				'value'      => $stack_trace,
				'attributes' => array(
					'code_language' => 'json',
				),
			)
		);
	}

	/**
	 * @param $action_execution_function
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function verify_execution_function( $action_execution_function ) {
		$this->get_action_run()->verify_execution_function( $action_execution_function );
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $error_message
	 * @param $recipe_log_id
	 * @param $args
	 *
	 * @return void
	 */
	public function complete_with_error( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {
		$this->get_action_run()->complete_with_error( (int) $user_id, $action_data, (int) $recipe_id, $error_message, (int) $recipe_log_id, $args );
	}

	/**
	 * Complete the action for the user
	 *
	 * @param null $user_id
	 * @param array $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return null|void
	 */
	public function action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {
		return $this->get_recipe_complete()->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * @param mixed[] $process_args
	 *
	 * @return void
	 */
	public static function action_tokens_hydrate_default( $process_args ) {
		return Api_Recipe_Complete::action_tokens_hydrate_default( $process_args );
	}

	/**
	 * @param null $user_id
	 * @param array $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return mixed|string
	 */
	public function get_action_error_message( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {
		return $this->get_recipe_complete()->get_action_error_message( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * @param null $user_id
	 * @param null $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return mixed|void
	 */
	public function get_action_completed_status( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {
		return $this->get_recipe_complete()->get_action_completed_status( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * @param null $user_id
	 * @param null $action_data
	 * @param null $recipe_id
	 * @param string $error_message
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return bool
	 */
	public function create_action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {
		return $this->get_action_run()->create_action( (int) $user_id, $action_data, (int) $recipe_id, (string) $error_message, $recipe_log_id, $args );
	}

	/**
	 * This code is to parse new "Use custom value" functionality before an action
	 * function is called. We will not have to modify each integration to support it.
	 *
	 * @param $action_data
	 * @param $user_id
	 * @param $recipe_id
	 * @param $args
	 *
	 * @return mixed
	 * @since  2.8
	 *
	 * @author Saad
	 */
	public function parse_custom_value( $action_data, $user_id, $recipe_id, $args ) {
		return $this->get_action_run()->parse_custom_value( $action_data, (int) $user_id, (int) $recipe_id, $args );
	}

	/**
	 * Complete a recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 *
	 * @param array $args
	 *
	 * @return null|true
	 */
	public function recipe( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {
		return $this->get_recipe_complete()->recipe( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Find the first actionable error from all actions in a recipe.
	 *
	 * This method checks ALL actions with errors (not just the first one returned by the DB)
	 * to find any error that should update the recipe status. This fixes a bug where
	 * SKIPPED actions with "Failed condition" messages could mask actual COMPLETED_WITH_ERRORS actions.
	 *
	 * @since 6.10.0.3
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return object|null Object with error_message and completed properties, or null if no actionable error found.
	 */
	public static function find_actionable_error( $recipe_log_id ) {
		return Api_Recipe_Complete::find_actionable_error( $recipe_log_id );
	}

	/**
	 * @since 6.10.0.3
	 *
	 * @param object $action_error Object with error_message and completed properties.
	 *
	 * @return bool
	 */
	public static function is_actionable_error( $action_error ) {
		return Api_Recipe_Complete::is_actionable_error( $action_error );
	}

	/**
	 * @param $error_message
	 *
	 * @return bool
	 */
	public static function is_condition_block_failed_message( $error_message ) {
		return Api_Recipe_Complete::is_condition_block_failed_message( (string) $error_message );
	}

	/**
	 * @param string $error_message
	 *
	 * @return boolean
	 */
	public static function is_user_selector_user_creation_message( $error_message ) {
		return Api_Recipe_Complete::is_user_selector_user_creation_message( (string) $error_message );
	}

	/**
	 * @param string $error_message
	 *
	 * @return boolean
	 */
	public static function is_user_selector_matching_message( $error_message ) {
		return Api_Recipe_Complete::is_user_selector_matching_message( (string) $error_message );
	}

	/**
	 * Complete all closures in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 * @param array $args
	 * @param array|null $recipe_closure_data Pre-fetched closure data to avoid redundant DB query.
	 *
	 * @return bool
	 */
	public function closures( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array(), $recipe_closure_data = null ) {
		return $this->get_recipe_complete()->closures( $recipe_id, $user_id, $recipe_log_id, $args, $recipe_closure_data );
	}

	/**
	 * When there are multiple triggers in a recipe, $args only contains the last run trigger info.
	 * It creates issues in the parsing of the tokens. This is an attempt to fix the issue by returning
	 * all triggers of a recipe in an already passing $args.
	 *
	 * @param $args
	 *
	 * @return array|mixed|void
	 * @since 4.3
	 * @author Saad
	 */
	public function maybe_get_triggers_of_a_recipe( $args = array() ) {
		return $this->get_trigger_complete()->maybe_get_triggers_of_a_recipe( $args );
	}

	/**
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function add_recipe_count( $recipe_id ) {
		$this->get_trigger_complete()->add_recipe_count( (int) $recipe_id );
	}
}
