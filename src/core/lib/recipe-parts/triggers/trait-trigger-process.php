<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Trigger_Process
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator\Recipe;

/**
 * Trait Trigger_Process
 *
 * @package Uncanny_Automator
 */
trait Trigger_Process {
	/**
	 * User ID of the actor who triggered recipe
	 *
	 * @var $user_id
	 */
	protected $user_id = 0;

	/**
	 * @var
	 */
	protected $post_id;

	/**
	 * @var
	 */
	protected $trigger_to_match;

	/**
	 * @var
	 */
	protected $recipe_to_match;

	/**
	 * @var bool
	 */
	protected $ignore_post_id = false;

	/**
	 * @var
	 */
	protected $is_signed_in;

	/**
	 * @var bool
	 */
	protected $trigger_autocomplete = false;

	/**
	 * @var array
	 */
	protected $trigger_args = array();

	/**
	 * @return array
	 */
	public function get_trigger_args() {
		return $this->trigger_args;
	}

	/**
	 * @param $trigger_args
	 */
	public function set_trigger_args( $trigger_args ) {
		$this->trigger_args = $trigger_args;
	}

	/**
	 * @return mixed
	 */
	public function get_user_id() {
		if ( ( empty( $this->user_id ) || 0 === $this->user_id ) && is_user_logged_in() ) {
			$this->user_id = wp_get_current_user()->ID;
		}

		return $this->user_id;
	}

	/**
	 * @param mixed $user_id
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
	}

	/**
	 * @return bool
	 */
	public function do_trigger_autocomplete() {
		return $this->trigger_autocomplete;
	}

	/**
	 * @param $trigger_autocomplete
	 */
	public function set_trigger_autocomplete( $trigger_autocomplete = true ) {
		$this->trigger_autocomplete = $trigger_autocomplete;
	}

	/**
	 * @return mixed
	 */
	public function get_trigger_to_match() {
		return $this->trigger_to_match;
	}

	/**
	 * @param mixed $trigger_to_match
	 */
	public function set_trigger_to_match( $trigger_to_match ) {
		$this->trigger_to_match = $trigger_to_match;
	}

	/**
	 * @return mixed
	 */
	public function get_recipe_to_match() {
		return $this->recipe_to_match;
	}

	/**
	 * @param mixed $recipe_to_match
	 */
	public function set_recipe_to_match( $recipe_to_match ) {
		$this->recipe_to_match = $recipe_to_match;
	}

	/**
	 * @return bool
	 */
	public function is_ignore_post_id() {
		return $this->ignore_post_id;
	}

	/**
	 * @param $ignore_post_id
	 */
	public function set_ignore_post_id( $ignore_post_id ) {
		$this->ignore_post_id = $ignore_post_id;
	}

	/**
	 * @return mixed
	 */
	public function get_is_signed_in() {
		return $this->is_signed_in;
	}

	/**
	 * @param mixed $is_signed_in
	 */
	public function set_is_signed_in( $is_signed_in ) {
		$this->is_signed_in = $is_signed_in;
	}

	/**
	 * @return mixed
	 */
	public function get_post_id() {
		return $this->post_id;
	}

	/**
	 * @param mixed $post_id
	 */
	public function set_post_id( $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * @param $entry_args
	 * @param $args
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function manually_complete_trigger( $entry_args, $args ) {

	}

	/**
	 * @param $args
	 *
	 * @return mixed|void
	 */
	protected function prepare_entry_args( $args ) {
		$pass_args = array(
			'code'    => $this->get_trigger_code(),
			'meta'    => $this->get_trigger_meta(),
			'user_id' => $this->get_user_id(),
		);

		if ( null !== $this->get_post_id() && null === $this->get_trigger_to_match() && null === $this->get_recipe_to_match() ) {
			$pass_args['post_id'] = $this->get_post_id();
		}

		if ( null !== $this->get_trigger_to_match() ) {
			$pass_args['trigger_to_match'] = $this->get_trigger_to_match();
		}

		if ( null !== $this->get_recipe_to_match() ) {
			$pass_args['recipe_to_match'] = $this->get_recipe_to_match();
		}

		if ( null !== $this->get_is_signed_in() ) {
			$pass_args['is_signed_in'] = $this->get_is_signed_in();
		}

		if ( $this->is_ignore_post_id() ) {
			$pass_args['ignore_post_id'] = $this->is_ignore_post_id();
		}

		$this->set_trigger_args( $pass_args );

		return apply_filters( 'automator_trigger_entry_args', $this->get_trigger_args(), $args );
	}
}
