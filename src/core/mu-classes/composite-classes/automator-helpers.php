<?php

namespace Uncanny_Automator;


/**
 * Class Automator_Helpers
 * @package Uncanny_Automator
 */
class Automator_Helpers {
	/**
	 * @var Automator_Helpers_Recipe
	 */
	public $recipe;

	/**
	 * Automator_Helpers constructor.
	 */
	public function __construct() {
		$this->recipe = new Automator_Helpers_Recipe();
		$this->recipe->setOptions( new Automator_Helpers_Recipe() );
	}
}