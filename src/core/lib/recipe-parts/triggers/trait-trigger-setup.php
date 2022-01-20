<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Trigger_Setup
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator\Recipe;

/**
 * Trait Trigger_Setup
 *
 * @package Uncanny_Automator\Recipe
 */
trait Trigger_Setup {

	/**
	 * @var
	 */
	protected $integration;

	/**
	 * @var string
	 */
	protected $author = 'Uncanny Automator';

	/**
	 * @var string
	 */
	protected $support_link = 'https://automatorplugin.com/knowledge-base/';

	/**
	 * @var bool
	 */
	protected $is_pro = false;

	/**
	 * @var bool
	 */
	protected $is_anonymous = false;
	/**
	 * @var string
	 */
	protected $trigger_type = 'user';

	/**
	 * @var bool
	 */
	protected $is_deprecated = false;

	/**
	 * @var
	 */
	protected $trigger_code;

	/**
	 * @var
	 */
	protected $trigger_meta;

	/**
	 * @var
	 */
	protected $action_hook;

	/**
	 * @var int
	 */
	protected $action_priority = 10;

	/**
	 * @var int
	 */
	protected $action_args_count = 1;

	/**
	 * @var
	 */
	protected $sentence;

	/**
	 * @var
	 */
	protected $readable_sentence;

	/**
	 * @var
	 */
	protected $options;

	/**
	 * @var
	 */
	protected $options_group;

	/**
	 * @var
	 */
	protected $options_callback;

	/**
	 * @var
	 */
	protected $trigger_tokens = array();
	/**
	 * @var
	 */
	protected $token_parser;

	/**
	 * @return mixed
	 */
	abstract protected function setup_trigger();

	/**
	 * @param $is_anonymous
	 */
	public function set_is_anonymous( $is_anonymous ) {
		$this->is_anonymous = $is_anonymous;
	}

	/**
	 * @return bool
	 */
	public function get_is_anonymous() {
		return $this->is_anonymous;
	}

	/**
	 * @param mixed $integration
	 */
	public function set_integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * @param mixed $action_hook
	 */
	public function set_action_hook( $action_hook ) {
		$this->action_hook = $action_hook;
	}

	/**
	 * @param mixed $sentence
	 */
	public function set_sentence( $sentence ) {
		$this->sentence = $sentence;
	}

	/**
	 * @param mixed $readable_sentence
	 */
	public function set_readable_sentence( $readable_sentence ) {
		$this->readable_sentence = $readable_sentence;
	}

	/**
	 * @param $author
	 */
	protected function set_author( $author ) {
		if ( empty( $author ) ) {
			$this->author = Automator()->get_author_name( $this->trigger_code );
		} else {
			$this->author = $author;
		}
	}

	/**
	 * @param $link
	 */
	protected function set_support_link( $link ) {
		if ( empty( $link ) ) {
			$this->support_link = Automator()->get_author_support_link( $this->trigger_code );
		} else {
			$this->support_link = $link;
		}
	}

	/**
	 * @param mixed $trigger_code
	 */
	public function set_trigger_code( $trigger_code ) {
		$this->trigger_code = $trigger_code;
	}

	/**
	 * @param mixed $trigger_meta
	 */
	public function set_trigger_meta( $trigger_meta ) {
		$this->trigger_meta = $trigger_meta;
	}

	/**
	 * @param $is_pro
	 */
	public function set_is_pro( $is_pro ) {
		$this->is_pro = $is_pro;
	}

	/**
	 * @param $is_deprecated
	 */
	public function set_is_deprecated( $is_deprecated ) {
		$this->is_deprecated = $is_deprecated;
	}

	/**
	 * @param $action_priority
	 */
	public function set_action_priority( $action_priority = 10 ) {
		$this->action_priority = $action_priority;
	}

	/**
	 * @param $arg_count
	 */
	protected function set_action_args_count( $arg_count = 1 ) {
		$this->action_args_count = $arg_count;
	}

	/**
	 * @param mixed $options
	 */
	public function set_options( $options ) {
		$this->options = $options;
	}

	/**
	 * @param mixed $callback
	 */
	public function set_options_callback( $callback ) {
		$this->options_callback = $callback;
	}

	/**
	 * @param mixed $options
	 */
	public function get_options_callback() {
		return $this->options_callback;
	}

	/**
	 * @param $action
	 * @param $priority
	 * @param $args
	 */
	protected function add_action( $action, $priority = 10, $args = 1 ) {
		$this->set_action_hook( $action );
		$this->set_action_priority( $priority );
		$this->set_action_args_count( $args );
	}

	/**
	 * @return mixed
	 */
	protected function get_action() {
		return $this->action_hook;
	}

	/**
	 * @return int
	 */
	protected function get_action_priority() {
		return $this->action_priority;
	}

	/**
	 * @return int
	 */
	protected function get_action_args_count() {
		return $this->action_args_count;
	}

	/**
	 * @return mixed
	 */
	protected function get_integration() {
		return $this->integration;
	}

	/**
	 * @return mixed
	 */
	protected function get_code() {
		return $this->trigger_code;
	}

	/**
	 * @return string
	 */
	protected function get_author() {
		return $this->author;
	}

	/**
	 * @return string
	 */
	protected function get_support_link() {
		return $this->support_link;
	}

	/**
	 * @return bool
	 */
	public function get_is_pro() {
		return $this->is_pro;
	}

	/**
	 * @return bool
	 */
	public function get_is_deprecated() {
		return $this->is_deprecated;
	}

	/**
	 * @return mixed
	 */
	public function get_trigger_code() {
		return $this->trigger_code;
	}

	/**
	 * @return mixed
	 */
	public function get_trigger_meta() {
		return $this->trigger_meta;
	}

	/**
	 * @return mixed
	 */
	public function get_action_hook() {
		return $this->action_hook;
	}

	/**
	 * @return mixed
	 */
	public function get_sentence() {
		return $this->sentence;
	}

	/**
	 * @return mixed
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * @return mixed
	 */
	protected function get_readable_sentence() {
		return $this->readable_sentence;
	}

	/**
	 * @return mixed
	 */
	public function get_options_group() {
		return $this->options_group;
	}

	/**
	 * @param mixed $options_group
	 */
	public function set_options_group( $options_group ) {
		$this->options_group = $options_group;
	}

	/**
	 * @return mixed
	 */
	public function get_trigger_tokens() {
		return $this->trigger_tokens;
	}

	/**
	 * @param mixed $trigger_tokens
	 */
	public function set_trigger_tokens( $trigger_tokens ) {
		$this->trigger_tokens = $trigger_tokens;
	}

	/**
	 * @return mixed
	 */
	public function get_token_parser() {
		return $this->token_parser;
	}

	/**
	 * @param mixed $token_parser
	 */
	public function set_token_parser( $token_parser ) {
		$this->token_parser = $token_parser;
	}

	/**
	 * @return string
	 */
	public function get_trigger_type() {
		return $this->trigger_type;
	}

	/**
	 * @param $trigger_type
	 */
	public function set_trigger_type( $trigger_type ) {
		$this->trigger_type = $trigger_type;
	}


	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	protected function register_trigger() {

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
			'tokens'              => $this->get_trigger_tokens(), // all the linked tokens of the trigger.
			'token_parser'        => $this->get_token_parser(), // v3.0, Pass a function to parse tokens.
			'validation_function' => array( $this, 'validate' ), // function to call for add_action().
		);

		if ( ! empty( $this->get_options() ) ) {
			$trigger['options'] = $this->get_options();
		}

		if ( ! empty( $this->get_options_group() ) ) {
			$trigger['options_group'] = $this->get_options_group();
		}

		if ( ! empty( $this->get_options_callback() ) ) {
			$trigger['options_callback'] = $this->get_options_callback();
		}

		$trigger = apply_filters( 'automator_register_trigger', $trigger );

		Automator()->register->trigger( $trigger );
	}
}
