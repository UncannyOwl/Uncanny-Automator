<?php


namespace Uncanny_Automator\Recipe;

/**
 * Trait Action_Setup
 *
 * @package Uncanny_Automator\Recipe
 */
trait Action_Setup {
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
	protected $is_elite = false;

	/**
	 * @var bool
	 */
	protected $is_deprecated = false;

	/**
	 * @var
	 */
	protected $action_code;

	/**
	 * @var
	 */
	protected $action_meta;

	/**
	 * @var
	 */
	protected $callable_function;

	/**
	 * @var int
	 */
	protected $function_priority = 10;

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
	protected $requires_user = true;

	/**
	 * @var
	 */
	protected $buttons;

	/**
	 * @var
	 */
	protected $background_processing = false;

	/**
	 * Whether the specific action should apply extra formatting or not.
	 *
	 * @var bool $should_apply_extra_formatting Pass true to apply extra formatting. Defaults to false.
	 */
	protected $should_apply_extra_formatting = false;

	/**
	 * @var array{}|array{mixed[]}
	 */
	protected $loopable_tokens = array();

	/**
	 * @var
	 */
	protected $helpers;

	public function get_loopable_tokens() {
		return $this->loopable_tokens;
	}

	public function set_loopable_tokens( $loopable_tokens ) {
		$this->loopable_tokens = $loopable_tokens;
	}

	/**
	 * @return mixed
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * @param mixed $integration
	 */
	public function set_integration( $integration ) {
		$this->integration = $integration;
	}

	/**
	 * @return string
	 */
	public function get_author() {
		return $this->author;
	}

	/**
	 * @param $author
	 */
	public function set_author( $author ) {
		$this->author = $author;
	}

	/**
	 * @return string
	 */
	public function get_support_link() {
		return $this->support_link;
	}

	/**
	 * @param $support_link
	 */
	public function set_support_link( $support_link ) {
		$this->support_link = $support_link;
	}

	/**
	 * @return bool
	 */
	public function is_is_pro() {
		return $this->is_pro;
	}

	/**
	 * @param $is_pro
	 */
	public function set_is_pro( $is_pro ) {
		$this->is_pro = $is_pro;
	}

	/**
	 * @return bool
	 */
	public function is_is_elite() {
		return $this->is_elite;
	}

	/**
	 * @param bool $is_elite
	 *
	 * @return void
	 */
	public function set_is_elite( bool $is_elite ) {
		$this->is_elite = $is_elite;
	}

	/**
	 * @return bool
	 */
	public function is_is_deprecated() {
		return $this->is_deprecated;
	}

	/**
	 * @param $is_deprecated
	 */
	public function set_is_deprecated( $is_deprecated ) {
		$this->is_deprecated = $is_deprecated;
	}

	/**
	 * @return mixed
	 */
	public function get_action_code() {
		return $this->action_code;
	}

	/**
	 * @param mixed $action_code
	 */
	public function set_action_code( $action_code ) {
		$this->action_code = $action_code;
	}

	/**
	 * @return mixed
	 */
	public function get_action_meta() {
		return $this->action_meta;
	}

	/**
	 * @param mixed $action_meta
	 */
	public function set_action_meta( $action_meta ) {
		$this->action_meta = $action_meta;
	}

	/**
	 * @return mixed
	 */
	public function get_sentence() {
		return $this->sentence;
	}

	/**
	 * @param mixed $sentence
	 */
	public function set_sentence( $sentence ) {
		$this->sentence = $sentence;
	}

	/**
	 * @return mixed
	 */
	public function get_readable_sentence() {
		return $this->readable_sentence;
	}

	/**
	 * @param mixed $readable_sentence
	 */
	public function set_readable_sentence( $readable_sentence ) {
		$this->readable_sentence = $readable_sentence;
	}

	/**
	 * @return mixed
	 */
	public function get_options() {
		return (array) $this->options;
	}

	/**
	 * @param mixed $options
	 */
	public function set_options( $options ) {
		$this->options = $options;
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
	 * @return mixed
	 */
	public function get_requires_user() {
		return $this->requires_user;
	}

	/**
	 * @param mixed $requires_user
	 */
	public function set_requires_user( $requires_user ) {
		$this->requires_user = $requires_user;
	}

	/**
	 * @return mixed
	 */
	public function get_buttons() {
		return $this->buttons;
	}

	/**
	 * @param mixed $buttons
	 */
	public function set_buttons( $buttons ) {
		$this->buttons = $buttons;
	}

	/**
	 * @return bool
	 */
	public function get_background_processing() {
		return $this->background_processing;
	}

	/**
	 * @param $background_processing
	 */
	public function set_background_processing( $background_processing ) {
		$this->background_processing = $background_processing;
	}

	/**
	 * @param bool $should_apply_extra_formatting
	 */
	public function set_should_apply_extra_formatting( $should_apply_extra_formatting = false ) {
		$this->should_apply_extra_formatting = (bool) $should_apply_extra_formatting;
	}

	/**
	 * Retrieves the should_apply_extra_formatting property.
	 *
	 * @return bool Whether it should apply an extra formatting or not.
	 */
	public function get_should_apply_extra_formatting() {
		return (bool) $this->should_apply_extra_formatting;
	}

	/**
	 * @return mixed
	 */
	public function get_helpers() {
		return $this->helpers;
	}

	/**
	 * @param mixed $helpers
	 */
	public function set_helpers( $helpers ) {
		$this->helpers = $helpers;
	}


	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function register_action() {

		$action = array(
			'author'                        => $this->get_author(),
			'support_link'                  => $this->get_support_link(),
			'integration'                   => $this->get_integration(),
			'is_pro'                        => $this->is_is_pro(),
			'is_elite'                      => $this->is_is_elite(),
			'is_deprecated'                 => $this->is_is_deprecated(),
			'requires_user'                 => $this->get_requires_user(),
			'code'                          => $this->get_action_code(),
			'sentence'                      => $this->get_sentence(),
			'select_option_name'            => $this->get_readable_sentence(),
			'execution_function'            => array( $this, 'do_action' ),
			'background_processing'         => $this->get_background_processing(),
			'should_apply_extra_formatting' => $this->get_should_apply_extra_formatting(),
			'loopable_tokens'               => $this->get_loopable_tokens(),
		);

		if ( ! empty( $this->get_options() ) ) {
			$action['options'] = $this->get_options();
		}

		if ( ! empty( $this->get_options_group() ) ) {
			$action['options_group'] = $this->get_options_group();
		}

		if ( ! empty( $this->get_options_callback() ) ) {
			$action['options_callback'] = $this->get_options_callback();
		}

		if ( ! empty( $this->get_buttons() ) ) {
			$action['buttons'] = $this->get_buttons();
		}

		$action = apply_filters( 'automator_register_action', $action );
		Automator()->register->action( $action );
	}

	/**
	 * @return mixed
	 */
	abstract protected function setup_action();
}
