<?php


namespace Uncanny_Automator\Recipe;

/**
 * Trait Action_Helpers
 *
 * @package Uncanny_Automator\Recipe
 */
trait Action_Helpers {

	use Action_Helpers_Email;

	/**
	 * The error messages.
	 *
	 * @var string[]
	 */
	protected $error_message;

	/**
	 * Clears error messages.
	 *
	 * @return void
	 */
	public function clear_error_message() {
		$this->error_message = array();
	}

	/**
	 * Retrieves the error message.
	 *
	 * @return mixed
	 */
	public function get_error_message() {
		if ( ! empty( $this->error_message ) ) {
			return join( ' ', $this->error_message );
		}

		return $this->error_message;
	}

	/**
	 * Sets error message to be added in the error_message prop.
	 *
	 * @param mixed $error_message
	 *
	 * @return void
	 */
	public function set_error_message( $error_message ) {
		$this->error_message[] = $error_message;
	}
}
