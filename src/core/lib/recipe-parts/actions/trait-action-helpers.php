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
	 * @var
	 */
	protected $error_message;

	/**
	 * @return mixed
	 */
	public function get_error_message() {
		if ( ! empty( $this->error_message ) ) {
			return join( ' ', $this->error_message );
		}

		return $this->error_message;
	}

	/**
	 * @param mixed $error_message
	 */
	public function set_error_message( $error_message ) {
		$this->error_message[] = $error_message;
	}
}
