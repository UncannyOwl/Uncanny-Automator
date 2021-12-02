<?php


namespace Uncanny_Automator;

/**
 * Class Automator_Recipe_Process
 *
 * @package Uncanny_Automator
 */
class Automator_Recipe_Process {

	/**
	 * @var
	 */
	public static $instance;
	/**
	 * @var Automator_Recipe_Process_User
	 */
	public $user;
	/**
	 * @var \Uncanny_Automator_Pro\Automator_Pro_Recipe_Process_Anon
	 */
	public $anon;

	/**
	 * Automator_Recipe_Process constructor.
	 */
	public function __construct() {
		$this->user = new Automator_Recipe_Process_User();
	}

	/**
	 * @return Automator_Recipe_Process
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
