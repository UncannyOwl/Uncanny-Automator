<?php

namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Automator_Exception;

/**
 * Trait Closure_Setup
 *
 * @package Uncanny_Automator\Recipe
 */
trait Closure_Setup {

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
	protected $closure_code;

	/**
	 * @var
	 */
	protected $closure_meta;

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
	 * @return mixed
	 */
	abstract protected function setup_closure();

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
			$this->author = Automator()->get_author_name( $this->closure_code );
		} else {
			$this->author = $author;
		}
	}

	/**
	 * @param $link
	 */
	protected function set_support_link( $link ) {
		if ( empty( $link ) ) {
			$this->support_link = Automator()->get_author_support_link( $this->closure_code );
		} else {
			$this->support_link = $link;
		}
	}

	/**
	 * @param mixed $closure_code
	 */
	public function set_closure_code( $closure_code ) {
		$this->closure_code = $closure_code;
	}

	/**
	 * @param mixed $closure_meta
	 */
	public function set_closure_meta( $closure_meta ) {
		$this->closure_meta = $closure_meta;
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
	 * @param mixed $options
	 */
	public function set_options( $options ) {
		$this->options = $options;
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
		return $this->closure_code;
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
	public function get_closure_code() {
		return $this->closure_code;
	}

	/**
	 * @return mixed
	 */
	public function get_closure_meta() {
		return $this->closure_meta;
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
	 * Define and register the closure by pushing it into the Automator object
	 *
	 * @throws Automator_Exception
	 */
	protected function register_closure() {

		$closure = array(
			'author'             => $this->get_author(),
			'support_link'       => $this->get_support_link(),
			'is_pro'             => $this->get_is_pro(),
			'is_deprecated'      => $this->get_is_deprecated(),
			'integration'        => $this->get_integration(),
			'code'               => $this->get_code(),
			'sentence'           => $this->get_sentence(),
			'select_option_name' => $this->get_readable_sentence(),
			'execution_function' => array( $this, 'redirect' ),
			'options'            => array( $this->get_options() ),
		);

		$closure = apply_filters( 'automator_register_closure', $closure );

		Automator()->register->closure( $closure );
	}
}
