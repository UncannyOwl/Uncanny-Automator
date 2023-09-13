<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Actions
 * @since   4.14
 * @version 4.14
 * @package Uncanny_Automator
 * @author  Ajay V.
 */


namespace Uncanny_Automator\Recipe;

/**
 * Abstract Actions
 *
 * @package Uncanny_Automator\Recipe
 */
abstract class Action {

	/**
	 * Action Setup. This trait handles action definitions.
	 */
	use Action_Setup;

	/**
	 * Action Conditions. This trait handles action conditions. This is where action conditionally executes. For
	 * example, a form ID has to be matched, a specific field needs to have a certain value.
	 */
	use Action_Conditions;

	/**
	 * Action Token Parser. This trait handles action meta's parser.
	 */
	use Action_Parser;

	/**
	 * Action Helpers. This trait repeated action helpers.
	 */
	use Action_Helpers;

	/**
	 * Action Process. This trait handles action execution.
	 */
	use Action_Process;

	/**
	 * Action Tokens.
	 */
	use Action_Tokens;

	/**
	 * errors
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * user_id
	 *
	 * @var mixed
	 */
	public $user_id;

	/**
	 * action_data
	 *
	 * @var mixed
	 */
	protected $action_data;

	/**
	 * recipe_id
	 *
	 * @var mixed
	 */
	protected $recipe_id;

	/**
	 * args
	 *
	 * @var mixed
	 */
	protected $args;

	/**
	 * maybe_parsed
	 *
	 * @var mixed
	 */
	protected $maybe_parsed;

	/**
	 * dependencies
	 *
	 * @var mixed
	 */
	protected $dependencies;

	/**
	 * __construct
	 *
	 * @param  mixed $args
	 * @return void
	 */
	final public function __construct( ...$args ) {
		$this->dependencies = $args;
		$this->setup_action();

		add_filter( 'automator_actions', array( $this, 'register_action' ) );

		if ( ! empty( $this->get_action_code() ) ) {
			$this->set_action_tokens(
				$this->define_tokens(),
				$this->get_action_code()
			);
		}

	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function register_action( $actions ) {

		$action = array(
			'author'                => $this->get_author(),
			'support_link'          => $this->get_support_link(),
			'integration'           => $this->get_integration(),
			'is_pro'                => $this->is_is_pro(),
			'is_deprecated'         => $this->is_is_deprecated(),
			'requires_user'         => $this->get_requires_user(),
			'code'                  => $this->get_action_code(),
			'sentence'              => $this->get_sentence(),
			'select_option_name'    => $this->get_readable_sentence(),
			'execution_function'    => array( $this, 'do_action' ),
			'background_processing' => $this->get_background_processing(),
			'options_callback'      => array( $this, 'load_options' ),
		);

		if ( ! empty( $this->get_buttons() ) ) {
			$action['buttons'] = $this->get_buttons();
		}

		$action = apply_filters( 'automator_register_action', $action );

		$actions[ $this->get_action_code() ] = $action;

		return $actions;
	}

	public function define_tokens() {
		return array();
	}

	/**
	 * add_log_error
	 *
	 * Any errors added using this method will display in the error log if the action failed.
	 *
	 * @param  string $error
	 * @return void
	 */
	public function add_log_error( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * get_log_errors
	 *
	 * @return string
	 */
	public function get_log_errors() {
		return implode( '<br>', $this->errors );
	}

	/**
	 * load_options
	 *
	 * Override this method to display multi-page options or have more granular control over the sentence/fields
	 *
	 * @return void
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->get_action_meta() => $this->options(),
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
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 *
	 * @throws \Exception
	 */
	public function do_action( $user_id, $action_data, $recipe_id, $args ) {

		// Clear errors in case there are some left from a previous action.
		$this->errors = array();

		$this->user_id     = $user_id;
		$this->action_data = $action_data;
		$this->recipe_id   = $recipe_id;
		$this->args        = $args;

		do_action( 'automator_before_process_action', $this->user_id, $this->action_data, $this->recipe_id, $this->args );

		$this->maybe_parsed                = $this->maybe_parse_tokens( $this->user_id, $this->action_data, $this->recipe_id, $this->args );
		$this->action_data['maybe_parsed'] = $this->maybe_parsed;

		$error = null;

		try {
			$result = $this->process_action( $this->user_id, $this->action_data, $this->recipe_id, $this->args, $this->maybe_parsed );
		} catch ( \Error $e ) {
			$result = false;
			$this->add_log_error( $e->getMessage() );
		} catch ( \Exception $e ) {
			$result = false;
			$this->add_log_error( $e->getMessage() );
		}

		if ( false === $result ) {
			$this->action_data['complete_with_errors'] = true;
			$error                                     = $this->get_log_errors();
		}

		if ( null === $result ) {
			$action_data['do_nothing']                 = true;
			$this->action_data['complete_with_errors'] = true;
			$error                                     = $this->get_log_errors();
		}

		Automator()->complete->action( $this->user_id, $this->action_data, $this->recipe_id, $error );

		do_action( 'automator_after_process_action', $this->user_id, $this->action_data, $this->recipe_id, $this->args, $this->maybe_parsed );
	}

	/**
	 * get_parsed_meta_value
	 *
	 * @param  string $meta
	 * @param  mixed $default
	 * @return mixed
	 */
	public function get_parsed_meta_value( $meta, $default = '' ) {

		if ( ! isset( $this->maybe_parsed[ $meta ] ) ) {
			return $default;
		}

		return $this->maybe_parsed[ $meta ];
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return mixed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		throw new \Exception( 'Please override the process_action() method to add the logic to your action.' );
	}
}
