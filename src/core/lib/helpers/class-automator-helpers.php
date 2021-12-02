<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Automator_Trigger_Condition_Helpers;

/**
 * Class Automator_Helpers
 *
 * @package Uncanny_Automator
 */
class Automator_Helpers {

	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @var Automator_Helpers_Recipe
	 */
	public $recipe;

	/**
	 * @var Automator_Trigger_Condition_Helpers
	 */
	public $trigger;

	/**
	 * @var Automator_Email_Helpers
	 */
	public $email;

	/**
	 * @return Automator_Helpers
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Automator_Helpers constructor.
	 */
	public function __construct() {
		$this->recipe  = new Automator_Helpers_Recipe();
		$this->trigger = new Automator_Trigger_Condition_Helpers();
		$this->email   = new Automator_Email_Helpers();
		$this->recipe->setOptions( new Automator_Helpers_Recipe() );
	}

}
