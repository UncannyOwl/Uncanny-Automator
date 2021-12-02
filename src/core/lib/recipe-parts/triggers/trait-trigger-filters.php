<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Trigger_Filters
 * @package Uncanny_Automator
 * @version 3.0
 * @since   3.0
 * @author  Saad S.
 */


namespace Uncanny_Automator\Recipe;

/**
 * Trait Trigger_Filters
 *
 * @package Uncanny_Automator
 */
trait Trigger_Filters {

	/**
	 * @var bool
	 */
	protected $is_login_required = true;

	/**
	 * @var
	 */
	protected $is_logged_in;

	/**
	 * @return mixed
	 */
	public function get_is_logged_in() {
		return $this->is_logged_in;
	}

	/**
	 * Basic validation when `$this->validate(...$args)` function is called. For example, checking if is_page(), or a
	 * passed argument is not empty.
	 *
	 * @param mixed ...$args
	 *
	 * @return mixed
	 */
	abstract protected function validate_trigger( ...$args );

	/**
	 * @param mixed ...$args
	 *
	 * @return false
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function do_continue_anon_trigger( ...$args ) {
		return false;
	}

	/**
	 * @param $is_login_required
	 */
	public function set_is_login_required( $is_login_required ) {
		$this->is_login_required = $is_login_required;
	}

	/**
	 * @param mixed ...$args
	 *
	 * @return bool
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function is_user_logged_in_required( ...$args ) {
		return $this->is_login_required;
	}

}
