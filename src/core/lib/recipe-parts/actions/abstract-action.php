<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Actions
 * @since   4.14
 * @version 4.14
 * @author  Ajay V.
 * @package Uncanny_Automator
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
	 * Use this variable for dependency injection.
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
	 * @var bool
	 */
	protected $complete_with_notice = false;

	/**
	 * __construct
	 *
	 * @param mixed $args
	 *
	 * @return void
	 */
	final public function __construct( ...$args ) {
		$this->dependencies = $args;

		// Automatically set up helpers from dependencies
		$this->set_helpers_from_dependencies( $this->dependencies );

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
	 * requirements_met
	 *
	 * Override this method if the action has any pre-requisites
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return true;
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function register_action( $actions ) {

		if ( ! $this->requirements_met() ) {
			return $actions;
		}

		$action = array(
			'author'                => $this->get_author(),
			'support_link'          => $this->get_support_link(),
			'integration'           => $this->get_integration(),
			'is_pro'                => $this->is_is_pro(),
			'is_elite'              => $this->is_is_elite(),
			'is_deprecated'         => $this->is_is_deprecated(),
			'requires_user'         => $this->get_requires_user(),
			'code'                  => $this->get_action_code(),
			'meta_code'             => $this->get_action_meta(),
			'sentence'              => $this->get_sentence(),
			'select_option_name'    => $this->get_readable_sentence(),
			'execution_function'    => array( $this, 'do_action' ),
			'background_processing' => $this->get_background_processing(),
			'options_callback'      => array( $this, 'load_options' ),
			'loopable_tokens'       => $this->get_loopable_tokens(),
		);

		if ( ! empty( self::$agent_registry ) && isset( self::$agent_registry[ $this->get_action_code() ] ) ) {
			$action['agent_class'] = self::$agent_registry[ $this->get_action_code() ];
		}

		if ( ! empty( $this->get_buttons() ) ) {
			$action['buttons'] = $this->get_buttons();
		}

		// Extract manifest data if trait is used
		if ( $this->uses_item_manifest_trait() && is_callable( array( $this, 'extract_item_manifest_data' ) ) ) {
			$manifest = call_user_func( array( $this, 'extract_item_manifest_data' ) );
			if ( ! empty( $manifest ) ) {
				$action['manifest'] = $manifest;
			}
		}

		$action = apply_filters( 'automator_register_action', $action );

		$actions[ $this->get_action_code() ] = $action;

		return $actions;
	}

	/**
	 * Check if action uses Item_Manifest trait.
	 *
	 * @return bool True if trait is used
	 */
	private function uses_item_manifest_trait() {
		$traits = class_uses( get_class( $this ) );
		return in_array( 'Uncanny_Automator\Item_Manifest', $traits, true );
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array();
	}

	/**
	 * add_log_error
	 *
	 * Any errors added using this method will display in the error log if the action failed.
	 *
	 * @param string $error
	 *
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
	 * @return bool
	 */
	public function is_complete_with_notice() {
		return $this->complete_with_notice;
	}

	/**
	 * @param $complete_with_notice
	 *
	 * @return void
	 */
	public function set_complete_with_notice( $complete_with_notice ) {
		$this->complete_with_notice = $complete_with_notice;
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

		if ( null === $result && ! $this->is_complete_with_notice() ) {
			$action_data['do_nothing']                 = true;
			$this->action_data['complete_with_errors'] = true;
			$error                                     = $this->get_log_errors();
		}

		if ( null === $result && $this->is_complete_with_notice() ) {
			$this->action_data['complete_with_notice'] = true;
			$error                                     = $this->get_log_errors();
		}

		Automator()->complete->action( $this->user_id, $this->action_data, $this->recipe_id, $error );

		do_action( 'automator_after_process_action', $this->user_id, $this->action_data, $this->recipe_id, $this->args, $this->maybe_parsed );
	}

	/**
	 * get_parsed_meta_value
	 *
	 * @param string $meta
	 * @param mixed $default_value
	 *
	 * @return mixed
	 */
	public function get_parsed_meta_value( $meta, $default_value = '' ) {

		if ( ! isset( $this->maybe_parsed[ $meta ] ) ) {
			return $default_value;
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
