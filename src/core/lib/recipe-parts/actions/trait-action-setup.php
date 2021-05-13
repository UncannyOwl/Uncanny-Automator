<?php


namespace Uncanny_Automator\Recipe;

/**
 * Trait Action_Setup
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
	 * @return mixed
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * @param mixed $integration
	 */
	public function set_integration( $integration ): void {
		$this->integration = $integration;
	}

	/**
	 * @return string
	 */
	public function get_author(): string {
		return $this->author;
	}

	/**
	 * @param string $author
	 */
	public function set_author( string $author ): void {
		$this->author = $author;
	}

	/**
	 * @return string
	 */
	public function get_support_link(): string {
		return $this->support_link;
	}

	/**
	 * @param string $support_link
	 */
	public function set_support_link( string $support_link ): void {
		$this->support_link = $support_link;
	}

	/**
	 * @return bool
	 */
	public function is_is_pro(): bool {
		return $this->is_pro;
	}

	/**
	 * @param bool $is_pro
	 */
	public function set_is_pro( bool $is_pro ): void {
		$this->is_pro = $is_pro;
	}

	/**
	 * @return bool
	 */
	public function is_is_deprecated(): bool {
		return $this->is_deprecated;
	}

	/**
	 * @param bool $is_deprecated
	 */
	public function set_is_deprecated( bool $is_deprecated ): void {
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
	public function set_action_code( $action_code ): void {
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
	public function set_action_meta( $action_meta ): void {
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
	public function set_sentence( $sentence ): void {
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
	public function set_readable_sentence( $readable_sentence ): void {
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
	public function set_options( $options ): void {
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
	public function set_options_group( $options_group ): void {
		$this->options_group = $options_group;
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function register_action() {

		$action = array(
			'author'             => $this->get_author(),
			'support_link'       => $this->get_support_link(),
			'integration'        => $this->get_integration(),
			'is_pro'             => $this->is_is_pro(),
			'is_deprecated'      => $this->is_is_deprecated(),
			'code'               => $this->get_action_code(),
			'sentence'           => $this->get_sentence(),
			'select_option_name' => $this->get_readable_sentence(),
			'execution_function' => array( $this, 'do_action' ),
		);

		if ( ! empty( $this->get_options() ) ) {
			$action['options'] = $this->get_options();
		}

		if ( ! empty( $this->get_options_group() ) ) {
			$action['options_group'] = $this->get_options_group();
		}

		$action = apply_filters( 'automator_register_action', $action );
		Automator()->register->action( $action );
	}

	/**
	 * @return mixed
	 */
	abstract protected function setup_action();
}
